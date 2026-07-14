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
    var config = window.SCLibraryPdfDocument || {};
    var $panel = $('[data-sc-pdf-conversion-panel]');
    if (!$panel.length) {
      return;
    }

    var $pdfId = $('[data-sc-foundation-pdf-id]').first();
    var $button = $panel.find('[data-sc-create-document]');
    var $status = $panel.find('[data-sc-pdf-status]');
    var $progress = $panel.find('[data-sc-pdf-progress]');
    var extracting = false;
    var pdfModulePromise = null;

    function request(action, data) {
      return $.ajax({
        url: config.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        data: $.extend({
          action: action,
          nonce: config.nonce,
          post_id: config.postId
        }, data || {})
      }).then(function (response) {
        if (!response || !response.success) {
          var message = response && response.data && response.data.message ? response.data.message : 'The PDF could not be converted.';
          return $.Deferred().reject(new Error(message)).promise();
        }
        return response.data;
      });
    }

    function setStatus(label, message, state) {
      $status.attr('data-status', state || '');
      $status.find('strong').text(label || '');
      $status.find('span').text(message || '');
    }

    function setProgress(value, visible) {
      $progress.prop('hidden', !visible).attr('value', Math.max(0, Math.min(100, value || 0)));
    }

    function setBusy(value) {
      extracting = value;
      $button.prop('disabled', value || !$pdfId.val());
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

    async function pdfModule() {
      if (!pdfModulePromise) {
        pdfModulePromise = import(config.pdfJsUrl).then(function (module) {
          module.GlobalWorkerOptions.workerSrc = config.workerUrl;
          return module;
        });
      }
      return pdfModulePromise;
    }

    function pageText(items) {
      var lines = [];
      var current = '';
      (items || []).forEach(function (item) {
        if (!item || typeof item.str !== 'string') {
          return;
        }
        var text = item.str.replace(/\s+/g, ' ').trim();
        if (text) {
          current += (current ? ' ' : '') + text;
        }
        if (item.hasEOL) {
          if (current.trim()) {
            lines.push(current.trim());
          }
          current = '';
        }
      });
      if (current.trim()) {
        lines.push(current.trim());
      }
      return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    async function browserExtract(url, attachmentId) {
      var pdfjs = await pdfModule();
      var loadingTask = pdfjs.getDocument({
        url: url,
        cMapUrl: config.cMapUrl,
        cMapPacked: true,
        standardFontDataUrl: config.fontUrl,
        wasmUrl: config.wasmUrl,
        useWorkerFetch: true
      });
      var pdf = await loadingTask.promise;
      var chunk = [];
      var characters = 0;

      for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        var page = await pdf.getPage(pageNumber);
        var textContent = await page.getTextContent({ includeMarkedContent: false });
        var text = pageText(textContent.items);
        characters += text.replace(/\s+/g, '').length;
        chunk.push({ page: pageNumber, text: text });
        setStatus('Converting', 'Reading page ' + pageNumber + ' of ' + pdf.numPages + '…', 'extracting');
        setProgress(Math.round((pageNumber / pdf.numPages) * 88), true);

        if (chunk.length >= 10 || pageNumber === pdf.numPages) {
          await request('sc_library_store_pdf_document_chunk', {
            attachment_id: attachmentId,
            pages: JSON.stringify(chunk),
            reset: pageNumber === chunk.length ? '1' : '0'
          });
          chunk = [];
        }
      }

      if (characters < 80) {
        await request('sc_library_mark_pdf_document_failure', { failure_code: 'needs_ocr' });
        return { status: 'needs_ocr', message: 'Little or no extractable text was found. This PDF needs OCR.' };
      }

      setStatus('Converting', 'Building the readable document…', 'extracting');
      setProgress(94, true);
      return request('sc_library_finalize_pdf_document', { attachment_id: attachmentId });
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

      setBusy(true);
      setProgress(2, true);
      setStatus('Converting', 'Preparing the PDF…', 'extracting');

      try {
        var prepared = await request('sc_library_prepare_pdf_document', { attachment_id: attachmentId });
        var result = prepared;
        if (prepared.status === 'browser_required') {
          result = await browserExtract(prepared.pdfUrl, attachmentId);
        }
        if (result.status === 'needs_ocr') {
          setStatus('Needs OCR', result.message, 'needs_ocr');
        } else {
          updateEditor(result);
          setStatus('Ready for review', result.message || 'Review the generated document below.', 'ready_review');
          $button.text('Re-create Document from PDF');
        }
        setProgress(100, false);
      } catch (error) {
        var message = error && error.message ? error.message : 'The PDF could not be converted.';
        var code = /password/i.test(message) ? 'password_protected' : 'failed';
        try {
          await request('sc_library_mark_pdf_document_failure', { failure_code: code });
        } catch (ignored) {}
        setStatus(code === 'password_protected' ? 'Password protected' : 'Conversion failed', message, code);
        setProgress(0, false);
      } finally {
        setBusy(false);
      }
    }

    $button.on('click', convert);

    if (config.autoExtract && $pdfId.val()) {
      window.setTimeout(convert, 500);
    }

    $(document).on('change', '[data-sc-foundation-pdf-id]', function () {
      setStatus('Not converted', 'Create the readable document from the selected PDF.', 'pending');
      $button.prop('disabled', !$(this).val());
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
