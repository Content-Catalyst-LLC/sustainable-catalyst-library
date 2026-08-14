(function () {
  'use strict';
  var cfg = window.SCLibraryUnifiedProjects || {};
  var root = document.querySelector('[data-sc-unified-projects="v4.3.30"]');
  if (!root || !cfg.signedIn) return;

  function status(message, isError) {
    var node = root.querySelector('[data-sc-project-status]');
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', !!isError);
  }

  function send(action, data) {
    var body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');
    Object.keys(data || {}).forEach(function (key) {
      var value = data[key];
      if (Array.isArray(value)) value.forEach(function (item) { body.append(key + '[]', item); });
      else body.append(key, value == null ? '' : value);
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.json(); })
      .then(function (payload) { if (!payload || !payload.success) throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Could not update the research project.'); return payload; });
  }

  function formData(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) {
      if (key.slice(-2) === '[]') {
        var clean = key.slice(0, -2); if (!data[clean]) data[clean] = []; data[clean].push(value);
      } else data[key] = value;
    });
    return data;
  }

  root.addEventListener('submit', function (event) {
    var form = event.target;
    var action = '';
    if (form.matches('[data-sc-project-create]')) action = 'sc_library_v4330_create_project';
    else if (form.matches('[data-sc-project-update]')) action = 'sc_library_v4330_update_project';
    else if (form.matches('[data-sc-project-add-link]')) action = 'sc_library_v4330_add_link';
    else if (form.matches('[data-sc-project-create-bundle]')) action = 'sc_library_v4330_create_bundle';
    if (!action) return;
    event.preventDefault();
    var submit = form.querySelector('button[type="submit"]'); if (submit) submit.disabled = true;
    status('Saving…', false);
    send(action, formData(form)).then(function (payload) {
      status(payload.data.message || 'Saved.', false);
      window.location.reload();
    }).catch(function (error) {
      status(error.message, true); if (submit) submit.disabled = false;
    });
  });

  root.addEventListener('click', function (event) {
    var linkButton = event.target.closest('[data-sc-project-delete-link]');
    var bundleButton = event.target.closest('[data-sc-project-delete-bundle]');
    if (!linkButton && !bundleButton) return;
    var button = linkButton || bundleButton;
    var action = linkButton ? 'sc_library_v4330_delete_link' : 'sc_library_v4330_delete_bundle';
    var data = { project_id: button.getAttribute('data-project-id') || '' };
    if (linkButton) data.link_id = linkButton.getAttribute('data-sc-project-delete-link') || '';
    if (bundleButton) data.bundle_id = bundleButton.getAttribute('data-sc-project-delete-bundle') || '';
    button.disabled = true; status('Updating…', false);
    send(action, data).then(function (payload) { status(payload.data.message || 'Updated.', false); window.location.reload(); })
      .catch(function (error) { status(error.message, true); button.disabled = false; });
  });
}());
