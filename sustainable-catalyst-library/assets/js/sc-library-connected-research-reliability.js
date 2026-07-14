(function () {
  'use strict';

  var config = window.SCLibraryConnectedResearchReliability || {};
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
          throw new Error(data.message || 'The reliability action failed.');
        }
        return payload.data || {};
      });
    });
  }

  function projectIdFrom(node) {
    var container = node.closest('[data-project-id]');
    return container ? container.getAttribute('data-project-id') || '' : '';
  }

  function setStatus(node, message, state) {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-success', 'is-error', 'is-working');
    if (state) node.classList.add('is-' + state);
  }

  function disable(button, disabled) {
    if (button) button.disabled = !!disabled;
  }

  function summarizeReport(report) {
    if (!report) return '';
    var failures = Array.isArray(report.failures) ? report.failures.length : 0;
    var warnings = Array.isArray(report.warnings) ? report.warnings.length : 0;
    var changes = Array.isArray(report.changes) ? report.changes.length : 0;
    return String(report.status || 'unknown') + ' · ' +
      failures + ' failure(s) · ' +
      warnings + ' warning(s) · ' +
      changes + ' change(s)';
  }

  function renderRecordResults(container, data, projectId, recordType) {
    if (!container) return;
    var items = data && Array.isArray(data.items) ? data.items : [];
    if (!items.length) {
      container.innerHTML = '<p class="description">' + escapeHtml(strings.noResults || 'No matching records found.') + '</p>';
      return;
    }

    var html = '<div class="sc-v301-search-results">';
    items.forEach(function (item) {
      html += '<article>' +
        '<div><strong>' + escapeHtml(item.title || ('Record ' + item.id)) + '</strong>' +
        '<span>' + escapeHtml(item.status || '') + '</span></div>' +
        '<div><a class="button button-small" href="' + escapeAttribute(item.edit || '#') + '">Open</a> ' +
        '<button type="button" class="button button-small" data-sc-v301-attach-record ' +
        'data-record-id="' + Number(item.id || 0) + '" data-record-type="' + escapeAttribute(recordType) + '" ' +
        'data-project-id="' + Number(projectId || 0) + '">Attach</button></div>' +
        '</article>';
    });
    html += '</div>';
    container.innerHTML = html;
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
  }

  document.addEventListener('click', function (event) {
    var searchButton = event.target.closest('[data-sc-v301-search-records]');
    if (searchButton) {
      event.preventDefault();
      var lookup = searchButton.closest('.sc-v301-record-lookup');
      var projectId = projectIdFrom(searchButton);
      var typeField = lookup ? lookup.querySelector('[data-sc-v301-record-type]') : null;
      var queryField = lookup ? lookup.querySelector('[data-sc-v301-record-query]') : null;
      var results = lookup ? lookup.querySelector('[data-sc-v301-record-results]') : null;
      var query = queryField ? queryField.value.trim() : '';
      if (query.length < 2) {
        setStatus(results, 'Enter at least two characters.', 'error');
        return;
      }

      disable(searchButton, true);
      setStatus(results, strings.searching || 'Searching…', 'working');
      request('sc_library_v301_search_records', {
        project_id: projectId,
        record_type: typeField ? typeField.value : 'source',
        query: query
      }).then(function (data) {
        renderRecordResults(results, data, projectId, typeField ? typeField.value : 'source');
      }).catch(function (error) {
        setStatus(results, error.message, 'error');
      }).finally(function () {
        disable(searchButton, false);
      });
      return;
    }

    var attachButton = event.target.closest('[data-sc-v301-attach-record]');
    if (attachButton) {
      event.preventDefault();
      disable(attachButton, true);
      attachButton.textContent = strings.working || 'Working…';
      request('sc_library_v301_attach_record', {
        project_id: attachButton.getAttribute('data-project-id') || '',
        record_id: attachButton.getAttribute('data-record-id') || '',
        record_type: attachButton.getAttribute('data-record-type') || 'source'
      }).then(function () {
        attachButton.textContent = strings.attached || 'Record attached. Reloading…';
        window.setTimeout(function () { window.location.reload(); }, 500);
      }).catch(function (error) {
        attachButton.textContent = error.message;
        disable(attachButton, false);
      });
      return;
    }

    var migrationButton = event.target.closest('[data-sc-v301-run-migration]');
    if (migrationButton) {
      event.preventDefault();
      var migrationMessage = document.querySelector('[data-sc-v301-migration-message]');
      disable(migrationButton, true);
      setStatus(migrationMessage, strings.working || 'Working…', 'working');
      request('sc_library_v301_run_migration', {}).then(function (data) {
        var state = data.state || {};
        var statusNode = document.querySelector('[data-sc-v301-migration-status]');
        if (statusNode) statusNode.textContent = state.status || 'pending';
        setStatus(
          migrationMessage,
          (strings.migrationRun || 'Migration batch complete.') + ' ' +
          Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
          'success'
        );
        if (state.status !== 'complete') disable(migrationButton, false);
      }).catch(function (error) {
        setStatus(migrationMessage, error.message, 'error');
        disable(migrationButton, false);
      });
      return;
    }

    var resetButton = event.target.closest('[data-sc-v301-reset-migration]');
    if (resetButton) {
      event.preventDefault();
      if (!window.confirm(strings.confirmReset || 'Reset migration state?')) return;
      var resetMessage = document.querySelector('[data-sc-v301-migration-message]');
      disable(resetButton, true);
      request('sc_library_v301_reset_migration', {}).then(function () {
        setStatus(resetMessage, strings.migrationReset || 'Migration state reset.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 500);
      }).catch(function (error) {
        setStatus(resetMessage, error.message, 'error');
        disable(resetButton, false);
      });
      return;
    }

    var validateButton = event.target.closest('[data-sc-v301-validate-project]');
    if (validateButton) {
      event.preventDefault();
      var validateContainer = validateButton.closest('[data-project-id]');
      var validateStatus = validateContainer ? validateContainer.querySelector('[data-sc-v301-project-status]') : null;
      disable(validateButton, true);
      setStatus(validateStatus, strings.working || 'Working…', 'working');
      request('sc_library_v301_validate_project', {
        project_id: projectIdFrom(validateButton)
      }).then(function (data) {
        setStatus(validateStatus, (strings.validated || 'Validation complete.') + ' ' + summarizeReport(data.report), 'success');
      }).catch(function (error) {
        setStatus(validateStatus, error.message, 'error');
      }).finally(function () {
        disable(validateButton, false);
      });
      return;
    }

    var repairButton = event.target.closest('[data-sc-v301-repair-project]');
    if (repairButton) {
      event.preventDefault();
      if (!window.confirm(strings.confirmRepair || 'Repair this project?')) return;
      var repairContainer = repairButton.closest('[data-project-id]');
      var repairStatus = repairContainer ? repairContainer.querySelector('[data-sc-v301-project-status]') : null;
      disable(repairButton, true);
      setStatus(repairStatus, strings.working || 'Working…', 'working');
      request('sc_library_v301_repair_project', {
        project_id: projectIdFrom(repairButton)
      }).then(function (data) {
        setStatus(repairStatus, (strings.repaired || 'Repair complete.') + ' ' + summarizeReport(data.report), 'success');
      }).catch(function (error) {
        setStatus(repairStatus, error.message, 'error');
      }).finally(function () {
        disable(repairButton, false);
      });
      return;
    }

    var exportButton = event.target.closest('[data-sc-v301-validate-exports]');
    if (exportButton) {
      event.preventDefault();
      var exportContainer = exportButton.closest('[data-project-id]');
      var exportStatus = exportContainer ? exportContainer.querySelector('[data-sc-v301-project-status]') : null;
      disable(exportButton, true);
      setStatus(exportStatus, strings.working || 'Working…', 'working');
      request('sc_library_v301_validate_exports', {
        project_id: projectIdFrom(exportButton)
      }).then(function (data) {
        var report = data.report || {};
        setStatus(
          exportStatus,
          (strings.exportsValidated || 'Export validation complete.') + ' ' +
          String(report.status || 'unknown') + ' · ' +
          (Array.isArray(report.failures) ? report.failures.length : 0) + ' failure(s).',
          report.status === 'failed' ? 'error' : 'success'
        );
      }).catch(function (error) {
        setStatus(exportStatus, error.message, 'error');
      }).finally(function () {
        disable(exportButton, false);
      });
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    var query = event.target.closest('[data-sc-v301-record-query]');
    if (!query) return;
    event.preventDefault();
    var lookup = query.closest('.sc-v301-record-lookup');
    var searchButton = lookup ? lookup.querySelector('[data-sc-v301-search-records]') : null;
    if (searchButton) searchButton.click();
  });
})();
