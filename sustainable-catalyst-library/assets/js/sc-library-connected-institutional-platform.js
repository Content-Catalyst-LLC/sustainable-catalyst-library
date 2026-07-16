(function () {
  'use strict';

  var config = window.SCLibraryInstitutionalPlatform || {};
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
          throw new Error(data.message || strings.error || 'The institutional platform operation failed.');
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

  function selectedValues(select) {
    if (!select) return [];
    return Array.prototype.slice.call(select.selectedOptions || []).map(function (option) {
      return option.value;
    });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderSearch(result) {
    var records = result && result.records ? result.records : [];
    if (!records.length) {
      return '<p>No matching institutional records were found.</p>';
    }
    return '<ol class="sc-inst-admin-results">' + records.map(function (record) {
      var title = escapeHtml(record.title || 'Untitled');
      var type = escapeHtml(record.type_label || record.type || 'Record');
      var url = record.url ? escapeHtml(record.url) : '';
      var heading = url ? '<a href="' + url + '">' + title + '</a>' : title;
      return '<li><article><p class="sc-inst-record-type">' + type + '</p><h3>' + heading + '</h3><p>' + escapeHtml(record.excerpt || '') + '</p><small>' + escapeHtml(record.urn || '') + '</small></article></li>';
    }).join('') + '</ol>';
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;

    if (form.matches('[data-sc-inst-search-form]')) {
      event.preventDefault();
      var results = document.querySelector('[data-sc-inst-search-results]');
      var button = form.querySelector('button');
      if (button) button.disabled = true;
      setStatus(results, strings.working || 'Working…', 'working');
      request('sc_library_v400_search', {
        query: form.querySelector('[name="query"]').value,
        types: selectedValues(form.querySelector('[name="types"]')).join(','),
        institution_id: form.querySelector('[name="institution_id"]').value
      }).then(function (data) {
        results.classList.remove('is-working', 'is-error');
        results.classList.add('is-success');
        results.innerHTML = renderSearch(data.search || {});
        if (button) button.disabled = false;
      }).catch(function (error) {
        setStatus(results, error.message, 'error');
        if (button) button.disabled = false;
      });
      return;
    }

    if (form.matches('[data-sc-inst-handoff-form]')) {
      event.preventDefault();
      var handoffResult = document.querySelector('[data-sc-inst-handoff-result]');
      var handoffButton = form.querySelector('button');
      if (handoffButton) handoffButton.disabled = true;
      setStatus(handoffResult, strings.working || 'Working…', 'working');
      request('sc_library_v400_create_handoff', {
        project_id: form.querySelector('[name="project_id"]').value,
        target_product: form.querySelector('[name="target_product"]').value,
        handoff_type: form.querySelector('[name="handoff_type"]').value,
        records: form.querySelector('[name="records"]').value
      }).then(function (data) {
        var handoff = data.handoff || {};
        var envelope = handoff.envelope || {};
        setStatus(
          handoffResult,
          'Created institutional envelope ' + (envelope.envelope_id || '') + ' with ' + Number((envelope.records || []).length) + ' records.',
          'success'
        );
        if (handoffButton) handoffButton.disabled = false;
      }).catch(function (error) {
        setStatus(handoffResult, error.message, 'error');
        if (handoffButton) handoffButton.disabled = false;
      });
    }
  });

  document.addEventListener('click', function (event) {
    var migration = event.target.closest('[data-sc-inst-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-inst-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Working…', 'working');
      request('sc_library_v400_run_migration', {}).then(function (data) {
        var state = data.migration || {};
        setStatus(
          migrationStatus,
          (strings.complete || 'Operation complete.') + ' ' + Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
          'success'
        );
        if (state.status !== 'complete') migration.disabled = false;
      }).catch(function (error) {
        setStatus(migrationStatus, error.message, 'error');
        migration.disabled = false;
      });
      return;
    }

    var health = event.target.closest('[data-sc-inst-refresh-health]');
    if (health) {
      event.preventDefault();
      var healthStatus = document.querySelector('[data-sc-inst-health-status]');
      health.disabled = true;
      setStatus(healthStatus, strings.working || 'Working…', 'working');
      request('sc_library_v400_refresh_health', {}).then(function (data) {
        var report = data.health || {};
        setStatus(healthStatus, 'Health: ' + (report.status || 'unknown') + ' · ' + Number(report.score || 0) + '/100.', 'success');
        health.disabled = false;
      }).catch(function (error) {
        setStatus(healthStatus, error.message, 'error');
        health.disabled = false;
      });
    }
  });
})();
