(function () {
  'use strict';

  var config = window.SCLibraryEvidenceClaims || {};
  var strings = config.strings || {};

  function copyText(value, statusNode) {
    if (!value) {
      return;
    }
    var promise;
    if (navigator.clipboard && window.isSecureContext) {
      promise = navigator.clipboard.writeText(value);
    } else {
      promise = new Promise(function (resolve, reject) {
        var textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy') ? resolve() : reject(new Error('Copy failed'));
        } catch (error) {
          reject(error);
        }
        textarea.remove();
      });
    }
    promise.then(function () {
      if (statusNode) {
        statusNode.textContent = strings.copied || 'Copied.';
        window.setTimeout(function () { statusNode.textContent = ''; }, 1800);
      }
    }).catch(function () {
      if (statusNode) {
        statusNode.textContent = strings.copyFailed || 'Copy failed.';
      }
    });
  }

  function valueFromNode(node) {
    if (!node) {
      return '';
    }
    if (node.matches('textarea, input')) {
      return node.value || '';
    }
    return node.getAttribute('data-sc-copy-value') || node.textContent || '';
  }

  document.addEventListener('click', function (event) {
    var directCopy = event.target.closest('[data-sc-copy-value].sc-evidence-copy');
    if (directCopy) {
      event.preventDefault();
      copyText(directCopy.getAttribute('data-sc-copy-value') || '', directCopy);
      return;
    }

    var copyButton = event.target.closest('[data-sc-copy-evidence-value]');
    if (copyButton) {
      event.preventDefault();
      var box = copyButton.closest('.sc-evidence-export-box');
      var target = box ? copyButton.previousElementSibling : null;
      if (!target || !target.hasAttribute('data-sc-copy-value')) {
        target = box ? box.querySelector('[data-sc-copy-value]') : null;
      }
      copyText(valueFromNode(target), copyButton);
      return;
    }

    var remove = event.target.closest('[data-sc-remove-evidence-link]');
    if (remove) {
      event.preventDefault();
      var editor = remove.closest('[data-sc-evidence-link-editor]');
      var row = remove.closest('[data-sc-evidence-link-row]');
      if (row) {
        row.remove();
      }
      if (editor) {
        renumberLinks(editor);
      }
      return;
    }

    var add = event.target.closest('[data-sc-add-evidence-link]');
    if (add) {
      event.preventDefault();
      var linkEditor = add.closest('[data-sc-evidence-link-editor]');
      var template = linkEditor ? linkEditor.querySelector('[data-sc-evidence-link-template]') : null;
      var rows = linkEditor ? linkEditor.querySelector('[data-sc-evidence-link-rows]') : null;
      if (template && rows) {
        rows.appendChild(template.content.cloneNode(true));
        renumberLinks(linkEditor);
      }
      return;
    }

    var selectAttachment = event.target.closest('[data-sc-select-evidence-attachment]');
    if (selectAttachment && window.wp && wp.media) {
      event.preventDefault();
      var field = selectAttachment.closest('.sc-evidence-attachment-field');
      var input = field ? field.querySelector('[data-sc-evidence-attachment-id]') : null;
      var status = field ? field.querySelector('[data-sc-evidence-attachment-status]') : null;
      var removeButton = field ? field.querySelector('[data-sc-remove-evidence-attachment]') : null;
      var frame = wp.media({
        title: strings.selectFile || 'Select Supporting Evidence',
        button: { text: strings.useFile || 'Use this file' },
        multiple: false
      });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        if (input) input.value = attachment.id || '';
        if (status) status.textContent = attachment.title || attachment.filename || 'Selected file';
        if (removeButton) removeButton.disabled = false;
      });
      frame.open();
      return;
    }

    var removeAttachment = event.target.closest('[data-sc-remove-evidence-attachment]');
    if (removeAttachment) {
      event.preventDefault();
      var attachmentField = removeAttachment.closest('.sc-evidence-attachment-field');
      var attachmentInput = attachmentField ? attachmentField.querySelector('[data-sc-evidence-attachment-id]') : null;
      var attachmentStatus = attachmentField ? attachmentField.querySelector('[data-sc-evidence-attachment-status]') : null;
      if (attachmentInput) attachmentInput.value = '';
      if (attachmentStatus) attachmentStatus.textContent = strings.noAttachment || 'No attachment selected';
      removeAttachment.disabled = true;
    }
  });

  function renumberLinks(editor) {
    var rows = editor.querySelectorAll('[data-sc-evidence-link-row]');
    rows.forEach(function (row, index) {
      row.querySelectorAll('[name], [data-name]').forEach(function (field) {
        var key = field.getAttribute('data-name');
        if (!key) {
          var match = (field.getAttribute('name') || '').match(/\[([a-z_]+)\]$/i);
          key = match ? match[1] : '';
        }
        if (key) {
          field.setAttribute('name', 'sc_evidence_claim_links[' + index + '][' + key + ']');
        }
      });
    });
  }

  document.querySelectorAll('[data-sc-evidence-link-editor]').forEach(function (editor) {
    renumberLinks(editor);
  });
})();
