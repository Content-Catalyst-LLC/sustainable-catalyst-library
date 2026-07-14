(function () {
  'use strict';

  var config = window.SCLibraryConnectorReliability || {};
  var strings = config.strings || {};

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) {
      body.set(key, values[key] === undefined || values[key] === null ? '' : String(values[key]));
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
          throw new Error(data.message || strings.failed || 'Reliability action failed.');
        }
        return payload.data;
      });
    });
  }

  document.addEventListener('click', function (event) {
    var reset = event.target.closest('[data-sc-reset-provider]');
    if (reset) {
      event.preventDefault();
      var card = reset.closest('[data-sc-provider-health]');
      var status = card ? card.querySelector('[data-sc-provider-action-status]') : null;
      reset.disabled = true;
      if (status) status.textContent = strings.working || 'Working…';
      request('sc_library_v261_reset_provider', {
        provider: reset.dataset.scResetProvider
      }).then(function (payload) {
        if (status) status.textContent = payload.message || strings.providerReset || 'Provider state reset.';
        window.setTimeout(function () { window.location.reload(); }, 650);
      }).catch(function (error) {
        if (status) status.textContent = error.message;
        reset.disabled = false;
      });
      return;
    }

    var clear = event.target.closest('[data-sc-clear-connector-cache]');
    if (clear) {
      event.preventDefault();
      var panel = clear.closest('.sc-connector-reliability-panel');
      var cacheStatus = panel ? panel.querySelector('[data-sc-cache-action-status]') : null;
      clear.disabled = true;
      if (cacheStatus) cacheStatus.textContent = strings.working || 'Working…';
      request('sc_library_v261_clear_cache', {}).then(function (payload) {
        if (cacheStatus) {
          cacheStatus.textContent = (payload.message || strings.cacheCleared || 'Connector cache cleared.') +
            ' ' + (payload.removed || 0) + ' record(s) removed.';
        }
        clear.disabled = false;
      }).catch(function (error) {
        if (cacheStatus) cacheStatus.textContent = error.message;
        clear.disabled = false;
      });
      return;
    }

    var recheck = event.target.closest('[data-sc-recheck-holdings]');
    if (recheck) {
      event.preventDefault();
      var holdings = recheck.closest('[data-sc-holdings-source]');
      var holdingsStatus = holdings ? holdings.querySelector('[data-sc-holdings-status]') : null;
      recheck.disabled = true;
      if (holdingsStatus) holdingsStatus.textContent = strings.working || 'Working…';
      request('sc_library_v261_recheck_holdings', {
        source_id: holdings ? holdings.dataset.scHoldingsSource : ''
      }).then(function (payload) {
        if (holdingsStatus) {
          holdingsStatus.textContent =
            (payload.total || 0) + ' locations · ' +
            (payload.fresh || 0) + ' fresh · ' +
            (payload.stale || 0) + ' stale.';
        }
        window.setTimeout(function () { window.location.reload(); }, 800);
      }).catch(function (error) {
        if (holdingsStatus) holdingsStatus.textContent = error.message;
        recheck.disabled = false;
      });
      return;
    }

    var resolve = event.target.closest('[data-sc-conflict-resolution]');
    if (resolve) {
      event.preventDefault();
      var conflict = resolve.closest('[data-conflict-id]');
      var root = resolve.closest('[data-source-id]');
      var conflictStatus = conflict ? conflict.querySelector('[data-sc-conflict-status]') : null;
      var buttons = conflict ? conflict.querySelectorAll('[data-sc-conflict-resolution]') : [];
      buttons.forEach(function (button) { button.disabled = true; });
      if (conflictStatus) conflictStatus.textContent = strings.working || 'Working…';
      request('sc_library_v261_resolve_conflict', {
        source_id: root ? root.dataset.sourceId : '',
        conflict_id: conflict ? conflict.dataset.conflictId : '',
        resolution: resolve.dataset.scConflictResolution
      }).then(function (payload) {
        if (conflictStatus) conflictStatus.textContent = payload.message || strings.conflictDone || 'Conflict resolved.';
        if (conflict) conflict.classList.add('is-resolved');
        window.setTimeout(function () {
          if (conflict) conflict.remove();
        }, 700);
      }).catch(function (error) {
        if (conflictStatus) conflictStatus.textContent = error.message;
        buttons.forEach(function (button) { button.disabled = false; });
      });
      return;
    }

    var validate = event.target.closest('[data-sc-validate-profile]');
    if (validate) {
      event.preventDefault();
      var profile = validate.closest('[data-profile-id]');
      var profileStatus = profile ? profile.querySelector('[data-sc-profile-validation-status]') : null;
      validate.disabled = true;
      if (profileStatus) profileStatus.textContent = strings.working || 'Working…';
      request('sc_library_v261_validate_profile', {
        profile_id: profile ? profile.dataset.profileId : ''
      }).then(function (payload) {
        if (profileStatus) {
          profileStatus.textContent =
            (payload.valid ? 'Valid. ' : 'Needs correction. ') +
            (payload.errors || []).length + ' error(s), ' +
            (payload.warnings || []).length + ' warning(s).';
        }
        window.setTimeout(function () { window.location.reload(); }, 800);
      }).catch(function (error) {
        if (profileStatus) profileStatus.textContent = error.message;
        validate.disabled = false;
      });
    }
  });
})();
