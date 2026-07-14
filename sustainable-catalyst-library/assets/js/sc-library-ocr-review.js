(function ($) {
  'use strict';

  var config = window.SCLibraryOcrReview || {};
  var $job = $('[data-sc-ocr-job]');
  var stopped = false;
  var processing = false;
  var clientId = (window.crypto && typeof window.crypto.randomUUID === 'function')
    ? window.crypto.randomUUID()
    : 'ocr-' + Date.now() + '-' + Math.random().toString(36).slice(2);

  $('[data-sc-select-all]').on('change', function () {
    $(this).closest('table').find('input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
  });

  if (!$job.length || !config.jobId) {
    return;
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      window.setTimeout(resolve, ms);
    });
  }

  async function request(action, data, attempt) {
    attempt = attempt || 0;
    try {
      var response = await $.ajax({
        url: config.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        timeout: 300000,
        data: $.extend({
          action: action,
          nonce: config.nonce,
          job_id: config.jobId,
          client_id: clientId
        }, data || {})
      });

      if (!response || !response.success) {
        var payload = response && response.data ? response.data : {};
        var error = new Error(payload.message || 'The OCR request failed.');
        error.payload = payload;
        throw error;
      }
      return response.data;
    } catch (error) {
      if (attempt >= 3) {
        throw error;
      }
      updateMessage((config.strings && config.strings.retrying) || 'Connection interrupted. Retrying…');
      await sleep(900 * Math.pow(2, attempt));
      return request(action, data, attempt + 1);
    }
  }

  function updateMessage(message) {
    $job.find('[data-sc-ocr-job-message]').text(message || '');
  }

  function labelForState(state) {
    var labels = {
      queued: 'Queued',
      processing: 'Processing',
      complete: 'OCR complete',
      low_confidence: 'Low confidence',
      reviewed: 'Reviewed',
      failed: 'Failed',
      unavailable: 'Provider unavailable',
      cancelled: 'Cancelled'
    };
    return labels[state] || String(state || '').replace(/_/g, ' ');
  }

  function updateState(state) {
    if (!state) {
      return;
    }

    $job.attr('data-job-status', state.status || '');
    $job.find('[data-sc-ocr-progress]').attr('value', state.processed || 0).attr('max', Math.max(1, state.total || 0));
    $job.find('[data-sc-ocr-done]').text(state.done || 0);
    $job.find('[data-sc-ocr-queued]').text(state.queued || 0);
    $job.find('[data-sc-ocr-failed]').text(state.failed || 0);
    $job.find('[data-sc-ocr-reviewed]').text(state.reviewed || 0);

    $('[data-sc-ocr-control="pause"]').prop('disabled', state.status !== 'running');
    $('[data-sc-ocr-control="resume"]').prop('disabled', ['paused', 'complete_with_errors'].indexOf(state.status) === -1);
    $('[data-sc-ocr-control="retry"]').prop('disabled', !(state.failed > 0));
    $('[data-sc-ocr-control="cancel"]').prop('disabled', ['complete', 'cancelled'].indexOf(state.status) !== -1);

    (state.items || []).forEach(function (item, index) {
      var $row = $('[data-item-index="' + index + '"]');
      $row.find('[data-item-state]').text(labelForState(item.state));
      $row.find('[data-item-confidence]').text(item.confidence ? Number(item.confidence).toFixed(1) + '%' : '—');
      $row.find('[data-item-message]').text(item.message || '');
    });
  }

  async function runQueue() {
    if (processing || stopped) {
      return;
    }

    processing = true;
    try {
      while (!stopped) {
        var next = await request('sc_library_v240_next_item');
        updateState(next.job);

        if (next.status !== 'running' || !next.item) {
          if (next.status === 'paused') {
            updateMessage((config.strings && config.strings.paused) || 'OCR queue paused.');
          } else if (next.status === 'complete_with_errors') {
            updateMessage((config.strings && config.strings.errors) || 'OCR queue complete with pages requiring attention.');
          } else if (next.status === 'complete') {
            updateMessage((config.strings && config.strings.complete) || 'OCR queue complete.');
          }
          break;
        }

        var item = next.item;
        updateMessage('Processing page ' + item.page + ' of “' + (item.document_title || 'document') + '”…');
        var state = await request('sc_library_v240_process_item', { item_index: item.index, lock_token: item.lock_token || '' });
        updateState(state);
      }
    } catch (error) {
      updateMessage(error && error.message ? error.message : 'OCR queue processing stopped.');
    } finally {
      processing = false;
    }
  }

  async function controlJob(control) {
    if (control === 'pause' || control === 'cancel') {
      stopped = true;
    }

    try {
      var state = await request('sc_library_v240_control_job', { control: control });
      updateState(state);

      if (control === 'resume' || control === 'retry') {
        stopped = false;
        runQueue();
      } else if (control === 'pause') {
        updateMessage((config.strings && config.strings.paused) || 'OCR queue paused.');
      } else if (control === 'cancel') {
        updateMessage('OCR queue cancelled.');
      }
    } catch (error) {
      updateMessage(error && error.message ? error.message : 'OCR queue control failed.');
    }
  }

  $('[data-sc-ocr-control]').on('click', function () {
    controlJob($(this).data('sc-ocr-control'));
  });

  if ($job.attr('data-job-status') === 'running') {
    runQueue();
  }
})(jQuery);
