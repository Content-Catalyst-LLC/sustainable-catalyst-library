(function () {
  'use strict';

  var config = window.SCLibraryCrossProductHandoffs || {};
  var strings = config.strings || {};

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) {
      var value = values[key];
      if (Array.isArray(value)) {
        value.forEach(function (item) { body.append(key + '[]', String(item)); });
      } else {
        body.set(key, value == null ? '' : String(value));
      }
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
          throw new Error(data.message || 'The handoff action failed.');
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

  function parseProducts(panel) {
    var node = panel ? panel.querySelector('[data-sc-handoff-products]') : null;
    if (!node) return {};
    try { return JSON.parse(node.textContent || '{}'); } catch (error) { return {}; }
  }

  function refreshTypes(panel) {
    var products = parseProducts(panel);
    var product = panel.querySelector('[data-sc-handoff-product]');
    var type = panel.querySelector('[data-sc-handoff-type]');
    if (!product || !type) return;
    var item = products[product.value] || {};
    type.innerHTML = '';
    Object.keys(item.types || {}).forEach(function (key) {
      var option = document.createElement('option');
      option.value = key;
      option.textContent = item.types[key];
      type.appendChild(option);
    });
  }

  function renderDelivery(container, delivery) {
    if (!container || !delivery) return;
    var url = delivery.launch_url || delivery.delivery_url || '';
    container.innerHTML = '';
    if (!url) return;
    var wrap = document.createElement('div');
    wrap.className = 'sc-handoff-delivery-card';
    var label = document.createElement('strong');
    label.textContent = strings.tokenRotated || 'Delivery link created.';
    var input = document.createElement('input');
    input.type = 'text';
    input.readOnly = true;
    input.value = url;
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'button';
    copy.textContent = strings.copy || 'Copy';
    copy.setAttribute('data-sc-copy-value', url);
    var expiry = document.createElement('small');
    expiry.textContent = delivery.expires_iso ? 'Expires ' + delivery.expires_iso : '';
    wrap.appendChild(label);
    wrap.appendChild(input);
    wrap.appendChild(copy);
    wrap.appendChild(expiry);
    container.appendChild(wrap);
  }

  document.addEventListener('change', function (event) {
    var product = event.target.closest('[data-sc-handoff-product]');
    if (product) refreshTypes(product.closest('.sc-handoff-project-panel'));
  });

  document.addEventListener('click', function (event) {
    var copy = event.target.closest('[data-sc-copy-value]');
    if (copy) {
      event.preventDefault();
      var value = copy.getAttribute('data-sc-copy-value') || '';
      if (navigator.clipboard && value) {
        navigator.clipboard.writeText(value).then(function () {
          var original = copy.textContent;
          copy.textContent = strings.copied || 'Copied';
          window.setTimeout(function () { copy.textContent = original; }, 1200);
        });
      }
      return;
    }

    var create = event.target.closest('[data-sc-create-handoff]');
    if (create) {
      event.preventDefault();
      var panel = create.closest('.sc-handoff-project-panel');
      var message = panel.querySelector('[data-sc-handoff-message]');
      var delivery = panel.querySelector('[data-sc-handoff-delivery]');
      var sections = [];
      panel.querySelectorAll('[data-sc-handoff-section]:checked').forEach(function (box) { sections.push(box.value); });
      create.disabled = true;
      setStatus(message, strings.working || 'Working…', 'working');
      request('sc_library_v340_create_handoff', {
        project_id: panel.getAttribute('data-project-id') || '',
        target_product: panel.querySelector('[data-sc-handoff-product]').value,
        handoff_type: panel.querySelector('[data-sc-handoff-type]').value,
        status: panel.querySelector('[data-sc-handoff-status]').value,
        requested_output: panel.querySelector('[data-sc-handoff-output]').value,
        instructions: panel.querySelector('[data-sc-handoff-instructions]').value,
        sections: sections
      }).then(function (data) {
        setStatus(message, strings.created || 'Handoff created.', 'success');
        if (data.handoff && data.handoff.delivery) renderDelivery(delivery, data.handoff.delivery);
        window.setTimeout(function () { window.location.reload(); }, data.handoff && data.handoff.delivery ? 3500 : 700);
      }).catch(function (error) {
        setStatus(message, error.message, 'error');
        create.disabled = false;
      });
      return;
    }

    var rotate = event.target.closest('[data-sc-rotate-handoff-token]');
    if (rotate) {
      event.preventDefault();
      if (!window.confirm(strings.confirmToken || 'Rotate the delivery token?')) return;
      var row = rotate.closest('[data-handoff-id]');
      rotate.disabled = true;
      request('sc_library_v340_rotate_token', { handoff_id: row.getAttribute('data-handoff-id') || '' })
        .then(function (data) {
          var existing = row.querySelector('.sc-handoff-inline-delivery');
          if (!existing) {
            existing = document.createElement('div');
            existing.className = 'sc-handoff-inline-delivery';
            row.querySelector('.sc-handoff-actions').appendChild(existing);
          }
          renderDelivery(existing, data.delivery || {});
        })
        .catch(function (error) { window.alert(error.message); })
        .finally(function () { rotate.disabled = false; });
      return;
    }

    var update = event.target.closest('[data-sc-update-handoff-status]');
    if (update) {
      event.preventDefault();
      var statusRow = update.closest('[data-handoff-id]');
      var select = statusRow.querySelector('[data-sc-handoff-status-select]');
      update.disabled = true;
      request('sc_library_v340_update_status', {
        handoff_id: statusRow.getAttribute('data-handoff-id') || '',
        status: select ? select.value : ''
      }).then(function () {
        update.textContent = strings.statusUpdated || 'Updated';
        window.setTimeout(function () { update.textContent = 'Update'; update.disabled = false; }, 1200);
      }).catch(function (error) {
        window.alert(error.message);
        update.disabled = false;
      });
      return;
    }

    var migration = event.target.closest('[data-sc-run-handoff-migration]');
    if (migration) {
      event.preventDefault();
      var migrationPanel = migration.closest('[data-sc-handoff-migration]');
      var migrationMessage = migrationPanel.querySelector('[data-sc-handoff-migration-message]');
      migration.disabled = true;
      setStatus(migrationMessage, strings.working || 'Working…', 'working');
      request('sc_library_v340_run_migration', {}).then(function (data) {
        var state = data.state || {};
        var stateNode = migrationPanel.querySelector('[data-sc-handoff-migration-state]');
        if (stateNode) stateNode.textContent = state.status || 'pending';
        setStatus(migrationMessage, (strings.migrationRun || 'Migration batch complete.') + ' ' + Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.', 'success');
        if (state.status !== 'complete') migration.disabled = false;
      }).catch(function (error) {
        setStatus(migrationMessage, error.message, 'error');
        migration.disabled = false;
      });
    }
  });

  document.querySelectorAll('.sc-handoff-project-panel').forEach(refreshTypes);
})();
