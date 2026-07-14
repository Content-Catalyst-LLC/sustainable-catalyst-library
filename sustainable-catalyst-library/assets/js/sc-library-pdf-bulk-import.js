(function ($) {
  'use strict';

  var config = window.SCLibraryPdfBulk || {};
  var $job = $('[data-sc-bulk-job]');
  var stopped = false;
  var processing = false;
  var pdfModulePromise = null;

  $('[data-sc-select-all]').on('change', function () {
    $(this).closest('table').find('input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
  });

  if (!$job.length || !config.jobId) {
    return;
  }

  function sleep(ms) {
    return new Promise(function (resolve) { window.setTimeout(resolve, ms); });
  }

  function request(action, data, nonce, attempt) {
    attempt = attempt || 0;
    return $.ajax({
      url: config.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      timeout: 120000,
      data: $.extend({ action: action, nonce: nonce, job_id: config.jobId }, data || {})
    }).then(function (response) {
      if (!response || !response.success) {
        var payload = response && response.data ? response.data : {};
        var error = new Error(payload.message || 'The request failed.');
        error.payload = payload;
        return $.Deferred().reject(error).promise();
      }
      return response.data;
    }).catch(async function (error) {
      var retries = parseInt(config.requestRetries, 10) || 3;
      if (attempt >= retries || (error.payload && error.payload.code === 'duplicate_pdf')) {
        throw error;
      }
      updateMessage((config.strings && config.strings.retrying) || 'Connection interrupted. Retrying…');
      await sleep((parseInt(config.retryDelay, 10) || 900) * Math.pow(2, attempt));
      return request(action, data, nonce, attempt + 1);
    });
  }

  function bulkRequest(action, data) {
    return request(action, data, config.nonce, 0);
  }

  function conversionRequest(action, postId, data) {
    return request(action, $.extend({ post_id: postId }, data || {}), config.conversionNonce, 0);
  }

  function updateMessage(message) {
    $job.find('[data-sc-job-message]').text(message || '');
  }

  function updateState(state) {
    if (!state) {
      return;
    }
    $job.attr('data-job-status', state.status || '');
    $job.find('[data-sc-job-progress]').attr('value', state.done || 0).attr('max', Math.max(1, state.total || 0));
    $job.find('[data-sc-job-done]').text(state.done || 0);
    $job.find('[data-sc-job-queued]').text(state.queued || 0);
    $job.find('[data-sc-job-failed]').text(state.failed || 0);
    $job.find('[data-sc-job-skipped]').text(state.skipped || 0);
    $('[data-sc-job-control="pause"]').prop('disabled', state.status !== 'running');
    $('[data-sc-job-control="resume"]').prop('disabled', ['paused', 'complete_with_errors'].indexOf(state.status) === -1);
    $('[data-sc-job-control="retry"]').prop('disabled', !(state.failed > 0));
    $('[data-sc-job-control="cancel"]').prop('disabled', ['complete', 'cancelled'].indexOf(state.status) !== -1);
    (state.items || []).forEach(function (item, index) {
      var $row = $('[data-item-index="' + index + '"]');
      $row.find('[data-item-state]').text(labelForState(item.state));
      $row.find('[data-item-message]').text(item.message || '');
    });
  }

  function labelForState(state) {
    var labels = {
      queued: 'Queued', processing: 'Processing', complete: 'Converted', created: 'Draft created',
      failed: 'Failed', needs_ocr: 'Needs OCR', skipped_duplicate: 'Duplicate skipped',
      skipped_existing: 'Existing record', cancelled: 'Cancelled'
    };
    return labels[state] || String(state || '').replace(/_/g, ' ');
  }

  async function pdfModule() {
    if (!pdfModulePromise) {
      pdfModulePromise = import(config.pdfJsUrl).then(function (module) {
        module.GlobalWorkerOptions.workerSrc = config.workerUrl;
        return module;
      });
    }
    return pdfModulePromise;
  }

  async function openPdf(pdfjs, url) {
    var options = {
      url: url,
      cMapUrl: config.cMapUrl,
      cMapPacked: true,
      standardFontDataUrl: config.fontUrl,
      wasmUrl: config.wasmUrl,
      useWorkerFetch: true
    };
    try {
      return await pdfjs.getDocument(options).promise;
    } catch (error) {
      updateMessage((config.strings && config.strings.workerFallback) || 'PDF worker unavailable. Continuing in compatibility mode…');
      options.disableWorker = true;
      options.useWorkerFetch = false;
      return pdfjs.getDocument(options).promise;
    }
  }

  function pageData(items) {
    var rows = [];
    (items || []).forEach(function (item) {
      if (!item || typeof item.str !== 'string') {
        return;
      }
      var text = item.str.replace(/\s+/g, ' ').trim();
      if (!text) {
        return;
      }
      var transform = item.transform || [];
      var y = Math.round((transform[5] || 0) * 2) / 2;
      var size = Math.abs(transform[3] || item.height || 0);
      var row = null;
      for (var i = rows.length - 1; i >= 0 && i >= rows.length - 8; i -= 1) {
        if (Math.abs(rows[i].y - y) <= Math.max(1.5, size * 0.25)) {
          row = rows[i];
          break;
        }
      }
      if (!row) {
        row = { y: y, size: size, bold: /bold|black|heavy/i.test(String(item.fontName || '')), parts: [] };
        rows.push(row);
      }
      row.size = Math.max(row.size, size);
      row.bold = row.bold || /bold|black|heavy/i.test(String(item.fontName || ''));
      row.parts.push({ x: transform[4] || 0, text: text });
    });
    rows.sort(function (a, b) { return b.y - a.y; });
    var lines = rows.map(function (row) {
      row.parts.sort(function (a, b) { return a.x - b.x; });
      return { text: row.parts.map(function (part) { return part.text; }).join(' ').replace(/\s+/g, ' ').trim(), size: Math.round(row.size * 100) / 100, bold: !!row.bold };
    }).filter(function (line) { return line.text; });
    return { text: lines.map(function (line) { return line.text; }).join('\n').trim(), lines: lines };
  }

  async function browserConvert(item, prepared) {
    var pdfjs = await pdfModule();
    var pdf = await openPdf(pdfjs, prepared.pdfUrl || item.pdf_url);
    var startPage = Math.max(1, parseInt(prepared.resumePage, 10) || 1);
    var chunk = [];
    var characters = 0;
    var chunkCharacters = 0;
    var maxPages = parseInt(config.chunkPages, 10) || 5;
    var maxCharacters = parseInt(config.chunkCharacters, 10) || 240000;

    for (var pageNumber = startPage; pageNumber <= pdf.numPages; pageNumber += 1) {
      if (stopped) {
        throw new Error('Queue paused or cancelled.');
      }
      var page = await pdf.getPage(pageNumber);
      var content = await page.getTextContent({ includeMarkedContent: false });
      var data = pageData(content.items);
      characters += data.text.replace(/\s+/g, '').length;
      chunkCharacters += data.text.length;
      chunk.push({ page: pageNumber, text: data.text, lines: data.lines });
      updateMessage('Reading “' + (item.title || item.filename || 'PDF') + '” — page ' + pageNumber + ' of ' + pdf.numPages + '…');
      if (chunk.length >= maxPages || chunkCharacters >= maxCharacters || pageNumber === pdf.numPages) {
        await conversionRequest('sc_library_v221_store_pdf_chunk', item.document_id, {
          attachment_id: item.attachment_id,
          session_id: prepared.sessionId,
          total_pages: pdf.numPages,
          pages: JSON.stringify(chunk)
        });
        chunk = [];
        chunkCharacters = 0;
      }
    }

    if (characters < 80 && startPage === 1) {
      await conversionRequest('sc_library_v221_mark_failure', item.document_id, { failure_code: 'needs_ocr', message: 'Little or no extractable text was found.' });
      return { status: 'needs_ocr', message: 'The PDF appears to be scanned and needs OCR.' };
    }

    return conversionRequest('sc_library_v221_finalize_pdf_document', item.document_id, {
      attachment_id: item.attachment_id,
      session_id: prepared.sessionId
    });
  }

  async function processItem(item) {
    var prepared = await conversionRequest('sc_library_v221_prepare_pdf_document', item.document_id, { attachment_id: item.attachment_id });
    if (prepared.status === 'browser_required') {
      return browserConvert(item, prepared);
    }
    return prepared;
  }

  async function markItem(item, state, message) {
    return bulkRequest('sc_library_v222_mark_item', {
      item_index: item.index,
      item_state: state,
      message: message || ''
    });
  }

  async function runQueue() {
    if (processing || stopped) {
      return;
    }
    processing = true;
    try {
      while (!stopped) {
        var next = await bulkRequest('sc_library_v222_next_item');
        updateState(next.job);
        if (next.status !== 'running' || !next.item) {
          if (next.status === 'paused') {
            updateMessage((config.strings && config.strings.paused) || 'Queue paused.');
          } else if (next.status === 'complete_with_errors') {
            updateMessage((config.strings && config.strings.errors) || 'Queue complete with errors.');
          } else if (next.status === 'complete') {
            updateMessage((config.strings && config.strings.complete) || 'Queue complete.');
          }
          break;
        }
        var item = next.item;
        updateMessage('Preparing “' + (item.title || item.filename || 'PDF') + '”…');
        try {
          var result = await processItem(item);
          if (result.status === 'needs_ocr') {
            updateState(await markItem(item, 'needs_ocr', result.message || 'Needs OCR.'));
          } else if (result.status === 'failed') {
            updateState(await markItem(item, 'failed', result.message || 'Conversion failed.'));
          } else {
            updateState(await markItem(item, 'complete', result.message || 'Readable document created.'));
          }
        } catch (error) {
          var payload = error && error.payload ? error.payload : {};
          var message = payload.message || (error && error.message) || 'Conversion failed.';
          if (stopped) {
            break;
          }
          updateState(await markItem(item, payload.code === 'duplicate_pdf' ? 'skipped_duplicate' : 'failed', message));
        }
      }
    } finally {
      processing = false;
    }
  }

  async function controlJob(control) {
    if (control === 'pause' || control === 'cancel') {
      stopped = true;
    }
    try {
      var state = await bulkRequest('sc_library_v222_control_job', { control: control });
      updateState(state);
      if (control === 'resume' || control === 'retry') {
        stopped = false;
        runQueue();
      } else if (control === 'pause') {
        updateMessage((config.strings && config.strings.paused) || 'Queue paused.');
      } else if (control === 'cancel') {
        updateMessage('Queue cancelled.');
      }
    } catch (error) {
      updateMessage(error.message || 'Queue control failed.');
    }
  }

  $('[data-sc-job-control]').on('click', function () {
    controlJob($(this).data('sc-job-control'));
  });

  if ($job.attr('data-job-status') === 'running') {
    runQueue();
  }
})(jQuery);
