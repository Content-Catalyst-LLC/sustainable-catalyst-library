(function () {
  'use strict';

  var config = window.SCLibraryAPIFederation || {};
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
          throw new Error(data.message || strings.error || 'The API, export, or federation operation failed.');
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

  document.addEventListener('submit', function (event) {
    var form = event.target;

    if (form.matches('[data-sc-token-form]')) {
      event.preventDefault();
      var result = document.querySelector('[data-sc-token-result]');
      var button = form.querySelector('button');
      var scopes = selectedValues(form.querySelector('[name="scopes"]'));
      if (button) button.disabled = true;
      setStatus(result, strings.working || 'Working…', 'working');

      request('sc_library_v390_create_token', {
        label: form.querySelector('[name="label"]').value,
        scopes: scopes.join(','),
        rate_limit: form.querySelector('[name="rate_limit"]').value,
        expires: form.querySelector('[name="expires"]').value
      }).then(function (data) {
        var token = data.token || {};
        result.classList.remove('is-working', 'is-error');
        result.classList.add('is-success');
        result.innerHTML =
          '<p><strong>Copy this token now. It will not be shown again.</strong></p>' +
          '<code class="sc-api-token-value">' + escapeHtml(token.token || '') + '</code>' +
          '<p>Scopes: ' + escapeHtml((token.scopes || []).join(', ')) + '</p>';
        if (button) button.disabled = false;
        form.reset();
      }).catch(function (error) {
        setStatus(result, error.message, 'error');
        if (button) button.disabled = false;
      });
      return;
    }

    if (form.matches('[data-sc-export-form]')) {
      event.preventDefault();
      var exportResult = document.querySelector('[data-sc-export-result]');
      var exportButton = form.querySelector('button');
      if (exportButton) exportButton.disabled = true;
      setStatus(exportResult, strings.working || 'Working…', 'working');

      request('sc_library_v390_create_export', {
        scope: form.querySelector('[name="scope"]').value,
        format: form.querySelector('[name="format"]').value,
        search: form.querySelector('[name="search"]').value,
        public: form.querySelector('[name="public"]').checked ? '1' : ''
      }).then(function (data) {
        var item = data.export || {};
        setStatus(
          exportResult,
          'Created export job ' + Number(item.job_id || 0) + '. It will run in bounded batches.',
          'success'
        );
        if (exportButton) exportButton.disabled = false;
      }).catch(function (error) {
        setStatus(exportResult, error.message, 'error');
        if (exportButton) exportButton.disabled = false;
      });
    }
  });

  document.addEventListener('click', function (event) {
    var migration = event.target.closest('[data-sc-api-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-api-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Working…', 'working');
      request('sc_library_v390_run_migration', {}).then(function (data) {
        var state = data.migration || {};
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
      return;
    }

    var checkPeer = event.target.closest('[data-sc-check-peer]');
    if (checkPeer) {
      event.preventDefault();
      var root = checkPeer.closest('[data-sc-peer-id]');
      var status = root && root.querySelector('[data-sc-peer-status]');
      checkPeer.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');
      request('sc_library_v390_check_peer', {
        peer_id: root ? root.getAttribute('data-sc-peer-id') : ''
      }).then(function (data) {
        var peer = data.peer || {};
        setStatus(status, 'Peer status: ' + (peer.status || 'unknown') + '.', 'success');
        checkPeer.disabled = false;
      }).catch(function (error) {
        setStatus(status, error.message, 'error');
        checkPeer.disabled = false;
      });
    }
  });
})();
