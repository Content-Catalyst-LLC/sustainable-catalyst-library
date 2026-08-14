(function () {
  'use strict';
  var cfg = window.SCLibraryReadingNotebooks || {};
  var root = document.querySelector('[data-sc-reading-notebooks="v4.3.31"]');
  if (!root || !cfg.signedIn) return;

  function status(message, isError) {
    var node = root.querySelector('[data-sc-reading-status]');
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
    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString()
    }).then(function (response) { return response.json(); }).then(function (payload) {
      if (!payload || !payload.success) {
        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Could not update the reading notebook.');
      }
      return payload;
    });
  }

  function formData(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    form.querySelectorAll('input[type="checkbox"][name]').forEach(function (input) {
      data[input.name] = input.checked ? input.value || '1' : '';
    });
    return data;
  }

  function runForm(form, action) {
    var submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
    status('Saving…', false);
    send(action, formData(form)).then(function (payload) {
      status(payload.data.message || 'Saved.', false);
      window.location.reload();
    }).catch(function (error) {
      status(error.message, true);
      if (submit) submit.disabled = false;
    });
  }

  root.addEventListener('submit', function (event) {
    var form = event.target;
    var action = '';
    if (form.matches('[data-sc-reading-create-notebook]')) action = 'sc_library_v4331_create_notebook';
    else if (form.matches('[data-sc-reading-update-notebook]')) action = 'sc_library_v4331_update_notebook';
    else if (form.matches('[data-sc-reading-add-note]')) action = 'sc_library_v4331_add_note';
    else if (form.matches('[data-sc-reading-update-note]')) action = 'sc_library_v4331_update_note';
    else if (form.matches('[data-sc-reading-add-annotation]')) action = 'sc_library_v4331_add_annotation';
    else if (form.matches('[data-sc-reading-update-annotation]')) action = 'sc_library_v4331_update_annotation';
    if (!action) return;
    event.preventDefault();
    runForm(form, action);
  });

  root.addEventListener('click', function (event) {
    var notebookButton = event.target.closest('[data-sc-reading-delete-notebook]');
    var noteButton = event.target.closest('[data-sc-reading-delete-note]');
    var annotationButton = event.target.closest('[data-sc-reading-delete-annotation]');
    var button = notebookButton || noteButton || annotationButton;
    if (!button) return;

    var action = '';
    var data = {};
    var confirmation = 'Delete this private record?';
    if (notebookButton) {
      action = 'sc_library_v4331_delete_notebook';
      data.notebook_id = notebookButton.getAttribute('data-sc-reading-delete-notebook') || '';
      confirmation = 'Delete this notebook and its private notes and annotations?';
    } else if (noteButton) {
      action = 'sc_library_v4331_delete_note';
      data.notebook_id = noteButton.getAttribute('data-notebook-id') || '';
      data.note_id = noteButton.getAttribute('data-sc-reading-delete-note') || '';
      confirmation = 'Delete this note?';
    } else {
      action = 'sc_library_v4331_delete_annotation';
      data.notebook_id = annotationButton.getAttribute('data-notebook-id') || '';
      data.annotation_id = annotationButton.getAttribute('data-sc-reading-delete-annotation') || '';
      confirmation = 'Delete this annotation?';
    }
    if (!window.confirm(confirmation)) return;
    button.disabled = true;
    status('Updating…', false);
    send(action, data).then(function (payload) {
      status(payload.data.message || 'Updated.', false);
      window.location.reload();
    }).catch(function (error) {
      status(error.message, true);
      button.disabled = false;
    });
  });
}());
