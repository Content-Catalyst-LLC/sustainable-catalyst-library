(function ($) {
  'use strict';

  function setTitleIfEmpty(attachment) {
    var $title = $('#title');
    if (!$title.length || $.trim($title.val()) !== '') {
      return;
    }
    var title = attachment.title || attachment.filename || '';
    title = title.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
    if (title) {
      $title.val(title).trigger('input');
    }
  }

  function initializeMediaSelector() {
    var config = window.SCFoundationPagesAdmin || {};

    $('[data-sc-foundation-page-selector]').each(function () {
      var $root = $(this);
      var $input = $root.find('[data-sc-foundation-pdf-id]');
      var $summary = $root.find('[data-sc-foundation-pdf-summary]');
      var $remove = $root.find('[data-sc-foundation-remove-pdf]');
      var frame = null;

      $root.on('click', '[data-sc-foundation-select-pdf]', function (event) {
        event.preventDefault();

        if (!window.wp || !wp.media) {
          window.alert(config.mediaError || 'The WordPress Media Library could not be loaded. Reload this editor and try again.');
          return;
        }

        if (frame) {
          frame.open();
          return;
        }

        frame = wp.media({
          title: config.frameTitle || 'Select PDF',
          button: { text: config.buttonText || 'Use this PDF' },
          library: { type: 'application/pdf' },
          multiple: false
        });

        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          if (attachment.mime !== 'application/pdf' && attachment.subtype !== 'pdf') {
            window.alert(config.invalidType || 'Please select a PDF file.');
            return;
          }
          $input.val(attachment.id).trigger('change');
          $remove.prop('disabled', false);
          $summary.empty()
            .append($('<strong>').text(attachment.title || attachment.filename))
            .append($('<span>').text(attachment.filename || ''))
            .append($('<a>', {
              href: attachment.url,
              target: '_blank',
              rel: 'noopener',
              text: config.openPdf || 'Open selected PDF'
            }));
          setTitleIfEmpty(attachment);
        });

        frame.open();
      });

      $root.on('click', '[data-sc-foundation-remove-pdf]', function (event) {
        event.preventDefault();
        $input.val('').trigger('change');
        $remove.prop('disabled', true);
        $summary.empty()
          .append($('<strong>').text(config.noSelection || 'No PDF selected'))
          .append($('<span>').text(config.chooseHelp || 'Choose an existing PDF from the WordPress Media Library.'));
      });
    });
  }

  function initializeConversion() {
    var baseConfig = window.SCLibraryPdfDocument || {};
    var config = window.SCLibraryPdfReliability || {};
    var strings = config.strings || {};
    var $panel = $('[data-sc-pdf-conversion-panel]');
    var $reliability = $('[data-sc-pdf-reliability]');
    if (!$panel.length) {
      return;
    }

    var $pdfId = $('[data-sc-foundation-pdf-id]').first();
    var $button = $panel.find('[data-sc-create-document]');
    var $status = $panel.find('[data-sc-pdf-status]');
    var $progress = $panel.find('[data-sc-pdf-progress]');
    var $cancel = $reliability.find('[data-sc-cancel-conversion]');
    var $reliabilityState = $reliability.find('[data-sc-reliability-state]');
    var $reliabilityDetail = $reliability.find('[data-sc-reliability-detail]');
    var $log = $reliability.find('[data-sc-conversion-log]');
    var extracting = false;
    var cancelled = false;
    var pdfModulePromise = null;
    var activeSession = null;

    function sleep(ms) {
      return new Promise(function (resolve) { window.setTimeout(resolve, ms); });
    }

    function rawRequest(action, data) {
      return $.ajax({
        url: config.ajaxUrl || baseConfig.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        timeout: 120000,
        data: $.extend({
          action: action,
          nonce: config.nonce,
          post_id: config.postId || baseConfig.postId
        }, data || {})
      }).then(function (response) {
        if (!response || !response.success) {
          var payload = response && response.data ? response.data : {};
          var error = new Error(payload.message || 'The PDF could not be converted.');
          error.payload = payload;
          return $.Deferred().reject(error).promise();
        }
        return response.data;
      });
    }

    async function request(action, data, attempt) {
      attempt = attempt || 0;
      try {
        return await rawRequest(action, data);
      } catch (error) {
        var retries = parseInt(config.requestRetries, 10) || 3;
        if (attempt >= retries || (error.payload && error.payload.code === 'duplicate_pdf')) {
          throw error;
        }
        setStatus('Converting', strings.retrying || 'Connection interrupted. Retrying…', 'extracting');
        await sleep((parseInt(config.retryDelay, 10) || 900) * Math.pow(2, attempt));
        return request(action, data, attempt + 1);
      }
    }

    function setStatus(label, message, state) {
      $status.attr('data-status', state || '');
      $status.find('strong').text(label || '');
      $status.find('span').text(message || '');
      if ($reliabilityState.length) {
        $reliabilityState.text(label || '');
      }
      if ($reliabilityDetail.length) {
        $reliabilityDetail.text(message || '');
      }
    }

    function setProgress(value, visible) {
      $progress.prop('hidden', !visible).attr('value', Math.max(0, Math.min(100, value || 0)));
    }

    function setBusy(value) {
      extracting = value;
      $button.prop('disabled', value || !$pdfId.val());
      $cancel.prop('disabled', !value && !activeSession);
    }

    function updateEditor(data) {
      if (data.title && (!$('#title').val() || $('#title').val() === 'Auto Draft')) {
        $('#title').val(data.title).trigger('change');
      }
      if (typeof data.content === 'string' && data.content) {
        if (window.tinymce && window.tinymce.get('content')) {
          window.tinymce.get('content').setContent(data.content);
        }
        $('#content').val(data.content).trigger('change');
      }
      if (typeof data.summary === 'string' && data.summary) {
        $('#excerpt').val(data.summary).trigger('change');
      }
      window.onbeforeunload = null;
    }

    function renderLogs(logs) {
      if (!$log.length || !Array.isArray(logs)) {
        return;
      }
      $log.empty();
      if (!logs.length) {
        $log.append($('<li>').text('No conversion events recorded yet.'));
        return;
      }
      logs.forEach(function (entry) {
        var $item = $('<li>');
        $item.append($('<time>').text(entry.time || ''));
        $item.append($('<strong>').text(entry.event || ''));
        $item.append($('<span>').text(entry.message || ''));
        $log.append($item);
      });
    }

    async function pdfModule() {
      if (!pdfModulePromise) {
        pdfModulePromise = import(baseConfig.pdfJsUrl).then(function (module) {
          module.GlobalWorkerOptions.workerSrc = baseConfig.workerUrl;
          return module;
        });
      }
      return pdfModulePromise;
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
        var fontName = String(item.fontName || '');
        var row = null;
        for (var i = rows.length - 1; i >= 0 && i >= rows.length - 8; i -= 1) {
          if (Math.abs(rows[i].y - y) <= Math.max(1.5, size * 0.25)) {
            row = rows[i];
            break;
          }
        }
        if (!row) {
          row = { y: y, size: size, bold: /bold|black|heavy/i.test(fontName), parts: [] };
          rows.push(row);
        }
        row.size = Math.max(row.size, size);
        row.bold = row.bold || /bold|black|heavy/i.test(fontName);
        row.parts.push({ x: transform[4] || 0, text: text });
      });

      rows.sort(function (a, b) { return b.y - a.y; });
      var lines = rows.map(function (row) {
        row.parts.sort(function (a, b) { return a.x - b.x; });
        return {
          text: row.parts.map(function (part) { return part.text; }).join(' ').replace(/\s+/g, ' ').trim(),
          size: Math.round(row.size * 100) / 100,
          bold: !!row.bold
        };
      }).filter(function (line) { return line.text; });

      return {
        text: lines.map(function (line) { return line.text; }).join('\n').replace(/\n{3,}/g, '\n\n').trim(),
        lines: lines
      };
    }

    async function openPdf(pdfjs, url) {
      var options = {
        url: url,
        cMapUrl: baseConfig.cMapUrl,
        cMapPacked: true,
        standardFontDataUrl: baseConfig.fontUrl,
        wasmUrl: baseConfig.wasmUrl,
        useWorkerFetch: true
      };
      try {
        return await pdfjs.getDocument(options).promise;
      } catch (error) {
        setStatus('Converting', strings.workerFallback || 'PDF worker unavailable. Continuing in compatibility mode…', 'extracting');
        options.disableWorker = true;
        options.useWorkerFetch = false;
        return pdfjs.getDocument(options).promise;
      }
    }

    async function flushChunk(chunk, attachmentId, sessionId, totalPages) {
      if (!chunk.length) {
        return;
      }
      await request('sc_library_v221_store_pdf_chunk', {
        attachment_id: attachmentId,
        session_id: sessionId,
        total_pages: totalPages,
        pages: JSON.stringify(chunk)
      });
    }

    async function browserExtract(prepared) {
      var pdfjs = await pdfModule();
      var pdf = await openPdf(pdfjs, prepared.pdfUrl);
      var maxPages = parseInt(config.maxPages, 10) || 5000;
      if (pdf.numPages > maxPages) {
        await request('sc_library_v221_mark_failure', { failure_code: 'too_large', message: strings.tooManyPages || 'This PDF exceeds the configured page limit.' });
        throw new Error(strings.tooManyPages || 'This PDF exceeds the configured page limit.');
      }

      var startPage = Math.max(1, parseInt(prepared.resumePage, 10) || 1);
      var chunk = [];
      var chunkCharacters = 0;
      var characters = 0;
      var maxChunkPages = parseInt(config.chunkPages, 10) || 5;
      var maxChunkCharacters = parseInt(config.chunkCharacters, 10) || 240000;

      activeSession = prepared.sessionId;
      $cancel.prop('disabled', false);
      if (prepared.resuming) {
        $button.text(strings.resume || 'Resume Document Conversion');
      }

      for (var pageNumber = startPage; pageNumber <= pdf.numPages; pageNumber += 1) {
        if (cancelled) {
          throw new Error(strings.cancelled || 'Saved conversion cancelled.');
        }
        var page = await pdf.getPage(pageNumber);
        var textContent = await page.getTextContent({ includeMarkedContent: false });
        var pageResult = pageData(textContent.items);
        characters += pageResult.text.replace(/\s+/g, '').length;
        chunkCharacters += pageResult.text.length;
        chunk.push({ page: pageNumber, text: pageResult.text, lines: pageResult.lines });

        setStatus('Converting', 'Reading page ' + pageNumber + ' of ' + pdf.numPages + '…', 'extracting');
        setProgress(Math.round((pageNumber / pdf.numPages) * 91), true);

        if (chunk.length >= maxChunkPages || chunkCharacters >= maxChunkCharacters || pageNumber === pdf.numPages) {
          await flushChunk(chunk, prepared.attachmentId, prepared.sessionId, pdf.numPages);
          chunk = [];
          chunkCharacters = 0;
        }
      }

      if (characters < 80 && startPage === 1) {
        await request('sc_library_v221_mark_failure', { failure_code: 'needs_ocr', message: 'Little or no extractable text was found.' });
        return { status: 'needs_ocr', message: 'Little or no extractable text was found. This PDF needs OCR.' };
      }

      setStatus('Converting', 'Building and saving the readable document…', 'extracting');
      setProgress(96, true);
      return request('sc_library_v221_finalize_pdf_document', {
        attachment_id: prepared.attachmentId,
        session_id: prepared.sessionId
      });
    }

    async function convert() {
      if (extracting) {
        return;
      }
      var attachmentId = parseInt($pdfId.val(), 10) || 0;
      if (!attachmentId) {
        window.alert('Select a PDF first.');
        return;
      }
      cancelled = false;
      setBusy(true);
      setProgress(2, true);
      setStatus('Converting', 'Preparing the PDF and checking for a saved session…', 'extracting');

      try {
        var prepared = await request('sc_library_v221_prepare_pdf_document', { attachment_id: attachmentId });
        var result = prepared;
        if (prepared.status === 'browser_required') {
          result = await browserExtract(prepared);
        }
        if (result.status === 'needs_ocr') {
          setStatus('Needs OCR', result.message, 'needs_ocr');
        } else if (result.status === 'failed') {
          throw new Error(result.message || 'The generated document could not be saved.');
        } else {
          updateEditor(result);
          setStatus('Ready for review', result.message || strings.complete || 'Conversion completed and saved. Review the document below.', 'ready_review');
          $button.text(strings.restart || 'Re-create Document from PDF');
          activeSession = null;
          $cancel.prop('disabled', true);
        }
        setProgress(100, false);
        await refreshStatus();
      } catch (error) {
        var payload = error && error.payload ? error.payload : {};
        if (payload.code === 'duplicate_pdf') {
          var duplicateMessage = payload.message || strings.duplicate || 'This PDF already belongs to another document record.';
          if (payload.editUrl) {
            duplicateMessage += ' Open the existing record: ' + payload.editUrl;
          }
          setStatus('Duplicate PDF', duplicateMessage, 'failed');
        } else if (!cancelled) {
          var message = error && error.message ? error.message : 'The PDF could not be converted.';
          var code = /password/i.test(message) ? 'password_protected' : 'failed';
          try {
            await request('sc_library_v221_mark_failure', { failure_code: code, message: message });
          } catch (ignored) {}
          setStatus(code === 'password_protected' ? 'Password protected' : 'Conversion interrupted', message + ' You can retry or resume.', code);
        }
        setProgress(0, false);
      } finally {
        setBusy(false);
      }
    }

    async function refreshStatus() {
      try {
        var state = await request('sc_library_v221_conversion_status');
        activeSession = state.active ? state.session_id : null;
        renderLogs(state.logs || []);
        if (state.active) {
          $button.text(strings.resume || 'Resume Document Conversion');
          $cancel.prop('disabled', false);
          setStatus('Conversion can be resumed', 'Saved through page ' + state.last_page + ' of ' + (state.total_pages || '?') + '.', 'extracting');
          if (state.total_pages) {
            setProgress(Math.round((state.last_page / state.total_pages) * 91), true);
          }
        }
      } catch (ignored) {}
    }

    async function cancelConversion() {
      if (!activeSession) {
        return;
      }
      cancelled = true;
      try {
        await request('sc_library_v221_cancel_conversion');
        activeSession = null;
        setStatus('Not converted', strings.cancelled || 'Saved conversion cancelled.', 'pending');
        setProgress(0, false);
        $button.text('Create Document from PDF');
        $cancel.prop('disabled', true);
        await refreshStatus();
      } catch (error) {
        setStatus('Cancel failed', error.message || 'The saved conversion could not be cancelled.', 'failed');
      }
    }

    $button.off('click').on('click', convert);
    $cancel.on('click', cancelConversion);

    $(document).on('change', '[data-sc-foundation-pdf-id]', function () {
      activeSession = null;
      setStatus('Not converted', 'Create the readable document from the selected PDF.', 'pending');
      setProgress(0, false);
      $button.text('Create Document from PDF').prop('disabled', !$(this).val());
      $cancel.prop('disabled', true);
    });

    refreshStatus().then(function () {
      if (baseConfig.autoExtract && $pdfId.val() && !extracting) {
        window.setTimeout(convert, 500);
      }
    });
  }

  $(function () {
    initializeMediaSelector();
    initializeConversion();

    $(document).on('change', '[data-sc-select-all-pdfs]', function () {
      $('input[name="attachment_ids[]"]:not(:disabled)').prop('checked', this.checked);
    });
  });
})(jQuery);
