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

  $(function () {
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
          title: config.frameTitle || 'Select Foundation PDF',
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
  });
}(jQuery));
