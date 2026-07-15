(function () {
  'use strict';

  var config = window.SCLibraryReviewPublishing || {};
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
          throw new Error(data.message || strings.error || 'The review or publishing operation failed.');
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

  function renumberAssignments(editor) {
    if (!editor) return;
    editor.querySelectorAll('[data-sc-assignment-row]').forEach(function (row, index) {
      row.querySelectorAll('[data-name]').forEach(function (field) {
        field.name = 'sc_review_assignments[' + index + '][' + field.getAttribute('data-name') + ']';
      });
    });
  }

  document.addEventListener('click', function (event) {
    var addAssignment = event.target.closest('[data-sc-add-assignment]');
    if (addAssignment) {
      event.preventDefault();
      var editor = addAssignment.closest('[data-sc-review-assignment-editor]');
      var rows = editor && editor.querySelector('[data-sc-assignment-rows]');
      var template = editor && editor.querySelector('[data-sc-assignment-template]');
      if (rows && template) {
        rows.appendChild(template.content.cloneNode(true));
        renumberAssignments(editor);
      }
      return;
    }

    var remove = event.target.closest('[data-sc-remove-row]');
    if (remove) {
      event.preventDefault();
      var row = remove.closest('[data-sc-assignment-row]');
      var editorRoot = row && row.closest('[data-sc-review-assignment-editor]');
      if (row) row.remove();
      renumberAssignments(editorRoot);
      return;
    }

    var refresh = event.target.closest('[data-sc-refresh-review]');
    if (refresh) {
      event.preventDefault();
      var reviewStatus = refresh.parentElement.querySelector('[data-sc-review-status]');
      refresh.disabled = true;
      setStatus(reviewStatus, strings.working || 'Working…', 'working');
      request('sc_library_v380_refresh_review', {
        review_id: refresh.getAttribute('data-review-id')
      }).then(function () {
        setStatus(reviewStatus, strings.complete || 'Operation complete.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        setStatus(reviewStatus, error.message, 'error');
        refresh.disabled = false;
      });
      return;
    }

    var addNote = event.target.closest('[data-sc-add-note]');
    if (addNote) {
      event.preventDefault();
      var review = addNote.closest('[data-sc-review-id]');
      var status = review && review.querySelector('[data-sc-review-action-status]');
      var type = review && review.querySelector('[data-sc-note-type]');
      var severity = review && review.querySelector('[data-sc-note-severity]');
      var documentField = review && review.querySelector('[data-sc-note-document]');
      var section = review && review.querySelector('[data-sc-note-section]');
      var body = review && review.querySelector('[data-sc-note-body]');
      addNote.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');
      request('sc_library_v380_add_note', {
        review_id: review ? review.getAttribute('data-sc-review-id') : '',
        type: type ? type.value : 'comment',
        severity: severity ? severity.value : 'info',
        document_id: documentField ? documentField.value : '',
        section: section ? section.value : '',
        body: body ? body.value : ''
      }).then(function () {
        setStatus(status, strings.complete || 'Operation complete.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        setStatus(status, error.message, 'error');
        addNote.disabled = false;
      });
      return;
    }

    var evaluate = event.target.closest('[data-sc-evaluate-package]');
    if (evaluate) {
      event.preventDefault();
      var packageRoot = evaluate.closest('[data-sc-package-id]');
      var packageStatus = packageRoot && packageRoot.querySelector('[data-sc-package-action-status]');
      evaluate.disabled = true;
      setStatus(packageStatus, strings.working || 'Working…', 'working');
      request('sc_library_v380_evaluate_package', {
        package_id: packageRoot ? packageRoot.getAttribute('data-sc-package-id') : ''
      }).then(function () {
        setStatus(packageStatus, strings.complete || 'Operation complete.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        setStatus(packageStatus, error.message, 'error');
        evaluate.disabled = false;
      });
      return;
    }

    var migration = event.target.closest('[data-sc-review-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-review-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Working…', 'working');
      request('sc_library_v380_run_migration', {}).then(function (data) {
        var state = data.migration || {};
        var stateNode = document.querySelector('[data-sc-review-migration-state]');
        if (stateNode) stateNode.textContent = state.status || 'pending';
        setStatus(
          migrationStatus,
          (strings.complete || 'Operation complete.') + ' ' +
            Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
          'success'
        );
        if (state.status !== 'complete') migration.disabled = false;
      }).catch(function (error) {
        setStatus(migrationStatus, error.message, 'error');
        migration.disabled = false;
      });
    }
  });

  document.querySelectorAll('[data-sc-review-assignment-editor]').forEach(renumberAssignments);
})();
