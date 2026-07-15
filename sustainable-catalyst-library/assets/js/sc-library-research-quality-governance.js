(function () {
  'use strict';

  var config = window.SCLibraryQualityGovernance || {};
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
          throw new Error(data.message || strings.error || 'The governance action failed.');
        }
        return payload.data || {};
      });
    });
  }

  function formValues(form) {
    var values = {};
    new FormData(form).forEach(function (value, key) {
      values[key] = value;
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(function (field) {
      if (!field.checked && field.name) values[field.name] = '';
    });
    return values;
  }

  function setStatus(node, message, state) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-working', 'is-success', 'is-error');
    if (state) node.classList.add('is-' + state);
  }

  function openDialog(root, selector) {
    var dialog = root ? root.querySelector(selector) : null;
    if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
  }

  document.addEventListener('click', function (event) {
    var root = event.target.closest('[data-sc-quality-project]');

    if (event.target.closest('[data-sc-quality-review-open]')) {
      event.preventDefault();
      openDialog(root, '[data-sc-quality-review-dialog]');
      return;
    }
    if (event.target.closest('[data-sc-quality-issue-open]')) {
      event.preventDefault();
      openDialog(root, '[data-sc-quality-issue-dialog]');
      return;
    }
    if (event.target.closest('[data-sc-quality-gate-open]')) {
      event.preventDefault();
      openDialog(root, '[data-sc-quality-gate-dialog]');
      return;
    }
    var closer = event.target.closest('[data-sc-quality-dialog-close]');
    if (closer) {
      event.preventDefault();
      var dialog = closer.closest('dialog');
      if (dialog) dialog.close();
      return;
    }

    var evaluate = event.target.closest('[data-sc-quality-evaluate]');
    if (evaluate && root) {
      event.preventDefault();
      var statusNode = root.querySelector('[data-sc-quality-status]');
      evaluate.disabled = true;
      setStatus(statusNode, strings.working || 'Working…', 'working');
      request('sc_library_v350_evaluate_project', {
        project_id: root.getAttribute('data-sc-quality-project')
      }).then(function () {
        setStatus(statusNode, strings.complete || 'Operation complete.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        setStatus(statusNode, error.message, 'error');
        evaluate.disabled = false;
      });
      return;
    }

    var migration = event.target.closest('[data-sc-quality-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-quality-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Working…', 'working');
      request('sc_library_v350_run_migration', {}).then(function (data) {
        var state = data.migration || {};
        var stateNode = document.querySelector('[data-sc-quality-migration-state]');
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

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var action = '';
    if (form.matches('[data-sc-quality-review-form]')) action = 'sc_library_v350_create_review';
    if (form.matches('[data-sc-quality-issue-form]')) action = 'sc_library_v350_create_issue';
    if (form.matches('[data-sc-quality-gate-form]')) action = 'sc_library_v350_transition_gate';
    if (!action) return;

    event.preventDefault();
    var root = form.closest('[data-sc-quality-project]');
    var statusNode = root ? root.querySelector('[data-sc-quality-status]') : null;
    var submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    setStatus(statusNode, strings.working || 'Working…', 'working');

    request(action, formValues(form)).then(function () {
      setStatus(statusNode, strings.complete || 'Operation complete.', 'success');
      var dialog = form.closest('dialog');
      if (dialog) dialog.close();
      window.setTimeout(function () { window.location.reload(); }, 450);
    }).catch(function (error) {
      setStatus(statusNode, error.message, 'error');
      if (submit) submit.disabled = false;
    });
  });
})();
