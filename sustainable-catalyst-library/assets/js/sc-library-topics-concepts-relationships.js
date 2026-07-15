(function () {
  'use strict';

  var config = window.SCLibraryKnowledgeGraph || {};
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
          throw new Error(data.message || 'The knowledge relationship action failed.');
        }
        return payload.data || {};
      });
    });
  }

  function renumberRelations(editor) {
    if (!editor) return;
    editor.querySelectorAll('[data-sc-semantic-relation-row]').forEach(function (row, index) {
      row.querySelectorAll('[data-name]').forEach(function (field) {
        var key = field.getAttribute('data-name');
        if (!key) return;
        field.setAttribute('name', 'sc_library_relations[' + index + '][' + key + ']');
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
    var addButton = event.target.closest('[data-sc-add-semantic-relation]');
    if (addButton) {
      event.preventDefault();
      var editor = addButton.closest('[data-sc-semantic-editor]');
      var rows = editor ? editor.querySelector('[data-sc-semantic-relation-rows]') : null;
      var template = editor ? editor.querySelector('[data-sc-semantic-relation-template]') : null;
      if (rows && template) {
        rows.appendChild(template.content.cloneNode(true));
        renumberRelations(editor);
      }
      return;
    }

    var removeButton = event.target.closest('[data-sc-remove-semantic-relation]');
    if (removeButton) {
      event.preventDefault();
      var relationEditor = removeButton.closest('[data-sc-semantic-editor]');
      var row = removeButton.closest('[data-sc-semantic-relation-row]');
      if (row) row.remove();
      renumberRelations(relationEditor);
      return;
    }

    var migrationButton = event.target.closest('[data-sc-run-topic-migration]');
    if (migrationButton) {
      event.preventDefault();
      var status = document.querySelector('[data-sc-topic-migration-status]');
      migrationButton.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');
      request('sc_library_v320_run_topic_migration', {}).then(function (data) {
        var state = data.state || {};
        setStatus(
          status,
          (strings.migrationRun || 'Topic migration batch complete.') + ' ' +
            String(state.step || '') + ' · ' + Number(state.processed || 0) + ' processed.',
          'success'
        );
        if (state.status !== 'complete') migrationButton.disabled = false;
      }).catch(function (error) {
        setStatus(status, error.message, 'error');
        migrationButton.disabled = false;
      });
      return;
    }

    var resetButton = event.target.closest('[data-sc-reset-topic-migration]');
    if (resetButton) {
      event.preventDefault();
      if (!window.confirm(strings.confirmReset || 'Reset topic migration?')) return;
      var resetStatus = document.querySelector('[data-sc-topic-migration-status]');
      resetButton.disabled = true;
      request('sc_library_v320_reset_topic_migration', {}).then(function () {
        setStatus(resetStatus, strings.migrationReset || 'Topic migration state reset.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 500);
      }).catch(function (error) {
        setStatus(resetStatus, error.message, 'error');
        resetButton.disabled = false;
      });
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-sc-knowledge-browser-form]');
    if (!form) return;
    event.preventDefault();
    var browser = form.closest('[data-sc-knowledge-browser]');
    var kindField = form.querySelector('[data-sc-browser-kind]');
    var idField = form.querySelector('[data-sc-browser-id]');
    var results = browser ? browser.querySelector('[data-sc-knowledge-browser-results]') : null;
    var id = idField ? Number(idField.value || 0) : 0;
    if (!id) {
      setStatus(results, strings.missingNode || 'Enter a record ID.', 'error');
      return;
    }
    setStatus(results, strings.browsing || 'Loading relationships…', 'working');
    request('sc_library_v320_browse_node', {
      kind: kindField ? kindField.value : '',
      id: id,
      include_private: browser && browser.getAttribute('data-public') === '0' ? '1' : '0'
    }).then(function (data) {
      if (results) {
        results.classList.remove('is-working', 'is-error', 'is-success');
        results.innerHTML = data.html || '';
      }
    }).catch(function (error) {
      setStatus(results, error.message, 'error');
    });
  });

  document.querySelectorAll('[data-sc-semantic-editor]').forEach(function (editor) {
    renumberRelations(editor);
  });
})();
