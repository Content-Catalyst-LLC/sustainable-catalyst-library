(function () {
  'use strict';

  var config = window.SCLibraryInstitutionalArchives || {};
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
          throw new Error(data.message || strings.error || 'The archive operation failed.');
        }
        return payload.data || {};
      });
    });
  }

  function setStatus(node, message, state) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-working', 'is-success', 'is-error');
    if (state) node.classList.add('is-' + state);
  }

  function renumber(container, rowSelector, prefix) {
    if (!container) return;
    container.querySelectorAll(rowSelector).forEach(function (row, index) {
      row.querySelectorAll('[data-name]').forEach(function (field) {
        field.name = prefix + '[' + index + '][' + field.getAttribute('data-name') + ']';
      });
    });
  }

  document.addEventListener('click', function (event) {
    var addObject = event.target.closest('[data-sc-add-digital-object]');
    if (addObject) {
      event.preventDefault();
      var editor = addObject.closest('[data-sc-digital-object-editor]');
      var rows = editor && editor.querySelector('[data-sc-digital-object-rows]');
      var template = editor && editor.querySelector('[data-sc-digital-object-template]');
      if (rows && template) {
        rows.appendChild(template.content.cloneNode(true));
        renumber(editor, '[data-sc-digital-object-row]', 'sc_component_digital_objects');
      }
      return;
    }

    var addCustody = event.target.closest('[data-sc-add-custody-event]');
    if (addCustody) {
      event.preventDefault();
      var custodyEditor = addCustody.closest('[data-sc-custody-editor]');
      var custodyRows = custodyEditor && custodyEditor.querySelector('[data-sc-custody-rows]');
      var custodyTemplate = custodyEditor && custodyEditor.querySelector('[data-sc-custody-template]');
      if (custodyRows && custodyTemplate) {
        custodyRows.appendChild(custodyTemplate.content.cloneNode(true));
        renumber(custodyEditor, '[data-sc-custody-row]', 'sc_accession_custody_history');
      }
      return;
    }

    var remove = event.target.closest('[data-sc-remove-row]');
    if (remove) {
      event.preventDefault();
      var row = remove.closest('[data-sc-digital-object-row], [data-sc-custody-row]');
      var parent = row && row.parentElement;
      var editorRoot = row && row.closest('[data-sc-digital-object-editor], [data-sc-custody-editor]');
      if (row) row.remove();
      if (editorRoot && editorRoot.matches('[data-sc-digital-object-editor]')) {
        renumber(editorRoot, '[data-sc-digital-object-row]', 'sc_component_digital_objects');
      } else if (editorRoot) {
        renumber(editorRoot, '[data-sc-custody-row]', 'sc_accession_custody_history');
      }
      return;
    }

    var audit = event.target.closest('[data-sc-archive-audit]');
    if (audit) {
      event.preventDefault();
      var collectionId = audit.getAttribute('data-collection-id');
      var status = audit.parentElement.querySelector('[data-sc-archive-status]');
      audit.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');
      request('sc_library_v360_run_audit', { collection_id: collectionId })
        .then(function () {
          setStatus(status, strings.complete || 'Operation complete.', 'success');
          window.setTimeout(function () { window.location.reload(); }, 450);
        })
        .catch(function (error) {
          setStatus(status, error.message, 'error');
          audit.disabled = false;
        });
      return;
    }

    var migration = event.target.closest('[data-sc-archive-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-archive-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Working…', 'working');
      request('sc_library_v360_run_migration', {})
        .then(function (data) {
          var state = data.migration || {};
          var stateNode = document.querySelector('[data-sc-archive-migration-state]');
          if (stateNode) stateNode.textContent = state.status || 'pending';
          setStatus(
            migrationStatus,
            (strings.complete || 'Operation complete.') + ' ' +
              Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
            'success'
          );
          if (state.status !== 'complete') migration.disabled = false;
        })
        .catch(function (error) {
          setStatus(migrationStatus, error.message, 'error');
          migration.disabled = false;
        });
    }
  });

  document.querySelectorAll('[data-sc-digital-object-editor]').forEach(function (editor) {
    renumber(editor, '[data-sc-digital-object-row]', 'sc_component_digital_objects');
  });
  document.querySelectorAll('[data-sc-custody-editor]').forEach(function (editor) {
    renumber(editor, '[data-sc-custody-row]', 'sc_accession_custody_history');
  });
})();
