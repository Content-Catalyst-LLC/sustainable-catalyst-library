(function () {
  'use strict';

  var config = window.SCLibrarySourceIntegrity || {};
  var strings = config.strings || {};

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) {
      body.set(key, values[key] == null ? '' : String(values[key]));
    });

    return fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          var data = payload && payload.data ? payload.data : {};
          throw new Error(data.message || 'The Source integrity action failed.');
        }
        return payload.data || {};
      });
    });
  }

  function renumberRelations(editor) {
    editor.querySelectorAll('[data-sc-integrity-relation-row]').forEach(function (row, index) {
      row.querySelectorAll('[name], [data-name]').forEach(function (field) {
        var key = field.getAttribute('data-name');
        if (!key) {
          var match = (field.getAttribute('name') || '').match(/\[([a-z_]+)\]$/i);
          key = match ? match[1] : '';
        }
        if (key) {
          field.setAttribute('name', 'sc_source_integrity_relations[' + index + '][' + key + ']');
        }
      });
    });
  }

  function setStatus(node, message, state) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-working', 'is-success', 'is-error');
    if (state) node.classList.add('is-' + state);
  }

  document.addEventListener('click', function (event) {
    var addRelation = event.target.closest('[data-sc-add-integrity-relation]');
    if (addRelation) {
      event.preventDefault();
      var editor = addRelation.closest('[data-sc-integrity-editor]');
      var template = editor ? editor.querySelector('[data-sc-integrity-relation-template]') : null;
      var rows = editor ? editor.querySelector('[data-sc-integrity-relation-rows]') : null;
      if (template && rows) {
        rows.appendChild(template.content.cloneNode(true));
        renumberRelations(editor);
      }
      return;
    }

    var removeRelation = event.target.closest('[data-sc-remove-integrity-relation]');
    if (removeRelation) {
      event.preventDefault();
      var relationEditor = removeRelation.closest('[data-sc-integrity-editor]');
      var row = removeRelation.closest('[data-sc-integrity-relation-row]');
      if (row) row.remove();
      if (relationEditor) renumberRelations(relationEditor);
      return;
    }

    var rebuildButton = event.target.closest('[data-sc-rebuild-source-integrity]');
    if (rebuildButton) {
      event.preventDefault();
      var container = rebuildButton.closest('[data-source-id]');
      var sourceId = container ? container.getAttribute('data-source-id') : '';
      var status = container ? container.querySelector('[data-sc-source-integrity-status]') : null;
      rebuildButton.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');

      request('sc_library_v310_rebuild_source_integrity', { source_id: sourceId })
        .then(function () {
          setStatus(status, strings.rebuilt || 'Source integrity indexes rebuilt.', 'success');
          window.setTimeout(function () { window.location.reload(); }, 600);
        })
        .catch(function (error) {
          setStatus(status, error.message, 'error');
          rebuildButton.disabled = false;
        });
      return;
    }

    var scanButton = event.target.closest('[data-sc-run-integrity-scan]');
    if (scanButton) {
      event.preventDefault();
      if (!window.confirm(strings.confirmScan || 'Run the next Source integrity scan batch?')) return;
      var scanStatus = document.querySelector('[data-sc-integrity-scan-status]');
      scanButton.disabled = true;
      setStatus(scanStatus, strings.working || 'Working…', 'working');

      request('sc_library_v310_scan_integrity', {})
        .then(function (data) {
          var state = data.state || {};
          var stateNode = document.querySelector('[data-sc-integrity-scan-state]');
          if (stateNode) stateNode.textContent = state.status || 'pending';
          setStatus(
            scanStatus,
            (strings.scanComplete || 'Integrity scan batch complete.') + ' ' +
              Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
            'success'
          );
          if (state.status !== 'complete') scanButton.disabled = false;
        })
        .catch(function (error) {
          setStatus(scanStatus, error.message, 'error');
          scanButton.disabled = false;
        });
    }
  });

  document.querySelectorAll('[data-sc-integrity-editor]').forEach(function (editor) {
    renumberRelations(editor);
  });
})();
