(function () {
  'use strict';

  var config = window.SCLibraryCitationManager || {};

  function copyText(text, button) {
    if (!text) {
      return;
    }

    function success() {
      if (!button) {
        return;
      }
      var original = button.getAttribute('data-sc-original-label') || button.textContent;
      button.setAttribute('data-sc-original-label', original);
      button.textContent = config.copied || 'Copied';
      window.setTimeout(function () {
        button.textContent = original;
      }, 1800);
    }

    function fallback() {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      try {
        if (document.execCommand('copy')) {
          success();
        } else if (button) {
          button.textContent = config.copyFailed || 'Copy failed';
        }
      } catch (error) {
        if (button) {
          button.textContent = config.copyFailed || 'Copy failed';
        }
      }
      document.body.removeChild(area);
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(success).catch(fallback);
    } else {
      fallback();
    }
  }

  document.addEventListener('click', function (event) {
    var targetButton = event.target.closest('[data-sc-copy-target]');
    if (targetButton) {
      event.preventDefault();
      var selector = targetButton.getAttribute('data-sc-copy-target');
      var target = selector ? document.querySelector(selector) : null;
      if (target) {
        copyText(target.getAttribute('data-sc-copy-value') || target.textContent.trim(), targetButton);
      }
      return;
    }

    var parentButton = event.target.closest('[data-sc-copy-parent]');
    if (parentButton) {
      event.preventDefault();
      var container = parentButton.closest(
        '.sc-source-citation-panel__item, .sc-source-card, .sc-project-bibliography__list li, .sc-inline-citation'
      ) || parentButton.parentElement;
      var valueNode = container ? container.querySelector('[data-sc-copy-value]') : null;
      if (valueNode) {
        copyText(valueNode.getAttribute('data-sc-copy-value') || valueNode.textContent.trim(), parentButton);
      }
    }
  });

  var sourceFilter = document.querySelector('[data-sc-project-source-filter]');
  if (sourceFilter) {
    sourceFilter.addEventListener('input', function () {
      var query = sourceFilter.value.toLowerCase().trim();
      document.querySelectorAll('[data-source-search]').forEach(function (row) {
        var haystack = row.getAttribute('data-source-search') || '';
        row.hidden = query && haystack.indexOf(query) === -1;
      });
    });
  }

  var selectAttachment = document.querySelector('[data-sc-source-select-attachment]');
  var removeAttachment = document.querySelector('[data-sc-source-remove-attachment]');
  var attachmentInput = document.querySelector('[data-sc-source-attachment-id]');
  var attachmentStatus = document.querySelector('[data-sc-source-attachment-status]');
  var frame = null;

  if (selectAttachment && attachmentInput && window.wp && wp.media) {
    selectAttachment.addEventListener('click', function (event) {
      event.preventDefault();
      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: config.mediaTitle || 'Select source material',
        button: { text: config.mediaButton || 'Use this file' },
        multiple: false
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        attachmentInput.value = attachment.id || '';
        if (attachmentStatus) {
          attachmentStatus.innerHTML = '';
          var strong = document.createElement('strong');
          strong.textContent = attachment.title || attachment.filename || 'Selected file';
          attachmentStatus.appendChild(strong);
          var detail = document.createElement('span');
          detail.textContent = attachment.mime || attachment.subtype || '';
          attachmentStatus.appendChild(detail);
        }
        if (removeAttachment) {
          removeAttachment.disabled = false;
        }
      });

      frame.open();
    });
  }

  if (removeAttachment && attachmentInput) {
    removeAttachment.addEventListener('click', function (event) {
      event.preventDefault();
      attachmentInput.value = '';
      removeAttachment.disabled = true;
      if (attachmentStatus) {
        attachmentStatus.innerHTML = '<strong>No source file selected</strong>';
      }
    });
  }
})();
