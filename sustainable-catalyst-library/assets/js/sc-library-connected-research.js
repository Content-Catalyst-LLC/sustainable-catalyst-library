(function () {
  'use strict';
  var config = window.SCLibraryConnectedResearch || {};
  var strings = config.strings || {};

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) { body.set(key, values[key] == null ? '' : String(values[key])); });
    return fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          var data = payload && payload.data ? payload.data : {};
          throw new Error(data.message || 'Project action failed.');
        }
        return payload.data;
      });
    });
  }

  function renumber(root, rowSelector, prefix) {
    root.querySelectorAll(rowSelector).forEach(function (row, index) {
      row.querySelectorAll('[name], [data-name]').forEach(function (field) {
        var key = field.getAttribute('data-name');
        if (!key) {
          var match = (field.getAttribute('name') || '').match(/\[([a-z_]+)\]$/i);
          key = match ? match[1] : '';
        }
        if (key) field.setAttribute('name', prefix + '[' + index + '][' + key + ']');
      });
    });
  }

  function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  function copy(value, node) {
    if (!value) return;
    var promise = navigator.clipboard && window.isSecureContext
      ? navigator.clipboard.writeText(value)
      : new Promise(function (resolve, reject) {
          var textarea = document.createElement('textarea');
          textarea.value = value;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          try { document.execCommand('copy') ? resolve() : reject(new Error('copy')); }
          catch (error) { reject(error); }
          textarea.remove();
        });
    promise.then(function () {
      if (node) {
        var original = node.textContent;
        node.textContent = strings.copied || 'Copied.';
        window.setTimeout(function () { node.textContent = original; }, 1600);
      }
    }).catch(function () {
      if (node) node.textContent = strings.copyFailed || 'Copy failed.';
    });
  }

  document.addEventListener('click', function (event) {
    var sourceEnvironment = event.target.closest('[data-sc-source-environment]');
    var addSource = event.target.closest('[data-sc-add-source]');
    if (addSource && sourceEnvironment) {
      event.preventDefault();
      var sourceTemplate = sourceEnvironment.querySelector('[data-sc-source-template]');
      var sourceRows = sourceEnvironment.querySelector('[data-sc-source-rows]');
      if (sourceTemplate && sourceRows) {
        sourceRows.appendChild(sourceTemplate.content.cloneNode(true));
        renumber(sourceEnvironment, '[data-sc-source-row]', 'sc_project_source_entries');
      }
      return;
    }
    var removeSource = event.target.closest('[data-sc-remove-source]');
    if (removeSource && sourceEnvironment) {
      event.preventDefault();
      var sourceRow = removeSource.closest('[data-sc-source-row]');
      if (sourceRow) sourceRow.remove();
      renumber(sourceEnvironment, '[data-sc-source-row]', 'sc_project_source_entries');
      return;
    }
    var addSection = event.target.closest('[data-sc-add-section]');
    if (addSection && sourceEnvironment) {
      event.preventDefault();
      var sectionTemplate = sourceEnvironment.querySelector('[data-sc-section-template]');
      var sectionRows = sourceEnvironment.querySelector('[data-sc-section-rows]');
      if (sectionTemplate && sectionRows) {
        sectionRows.appendChild(sectionTemplate.content.cloneNode(true));
        renumber(sourceEnvironment, '[data-sc-section-row]', 'sc_project_sections');
      }
      return;
    }
    var removeSection = event.target.closest('[data-sc-remove-section]');
    if (removeSection && sourceEnvironment) {
      event.preventDefault();
      var sectionRow = removeSection.closest('[data-sc-section-row]');
      if (sectionRow) sectionRow.remove();
      renumber(sourceEnvironment, '[data-sc-section-row]', 'sc_project_sections');
      return;
    }

    var teamEditor = event.target.closest('[data-sc-team-editor]');
    var addTeam = event.target.closest('[data-sc-add-team-member]');
    if (addTeam && teamEditor) {
      event.preventDefault();
      var teamTemplate = teamEditor.querySelector('[data-sc-team-template]');
      var teamRows = teamEditor.querySelector('[data-sc-team-rows]');
      if (teamTemplate && teamRows) {
        teamRows.appendChild(teamTemplate.content.cloneNode(true));
        renumber(teamEditor, '[data-sc-team-row]', 'sc_project_team');
      }
      return;
    }
    var removeTeam = event.target.closest('[data-sc-remove-team-member]');
    if (removeTeam && teamEditor) {
      event.preventDefault();
      var teamRow = removeTeam.closest('[data-sc-team-row]');
      if (teamRow) teamRow.remove();
      renumber(teamEditor, '[data-sc-team-row]', 'sc_project_team');
      return;
    }

    var createSnapshot = event.target.closest('[data-sc-create-snapshot]');
    if (createSnapshot) {
      event.preventDefault();
      var box = createSnapshot.closest('[data-project-id]');
      var status = box ? box.querySelector('[data-sc-snapshot-status]') : null;
      var label = box ? box.querySelector('[data-sc-snapshot-label]') : null;
      createSnapshot.disabled = true;
      if (status) status.textContent = strings.working || 'Working…';
      request('sc_library_v300_create_snapshot', {
        project_id: box ? box.dataset.projectId : '',
        label: label ? label.value : ''
      }).then(function () { window.location.reload(); }).catch(function (error) {
        if (status) status.textContent = error.message;
        createSnapshot.disabled = false;
      });
      return;
    }
    var deleteSnapshot = event.target.closest('[data-sc-delete-snapshot]');
    if (deleteSnapshot) {
      event.preventDefault();
      if (!window.confirm(strings.confirmDelete || 'Delete this bibliography snapshot?')) return;
      var snapshotBox = deleteSnapshot.closest('[data-project-id]');
      request('sc_library_v300_delete_snapshot', {
        project_id: snapshotBox ? snapshotBox.dataset.projectId : '',
        snapshot_id: deleteSnapshot.dataset.scDeleteSnapshot
      }).then(function () {
        var row = deleteSnapshot.closest('[data-snapshot-id]');
        if (row) row.remove();
      }).catch(function (error) { window.alert(error.message); });
      return;
    }

    var directCopy = event.target.closest('[data-sc-copy-connected]');
    if (directCopy) {
      event.preventDefault();
      var target = directCopy.previousElementSibling;
      copy(target && 'value' in target ? target.value : '', directCopy);
      return;
    }
    var parentCopy = event.target.closest('[data-sc-copy-parent]');
    if (parentCopy) {
      event.preventDefault();
      var parent = parentCopy.closest('li, article, section');
      var valueNode = parent ? parent.querySelector('[data-sc-copy-value]') : null;
      copy(valueNode ? (valueNode.getAttribute('data-sc-copy-value') || valueNode.textContent || '') : '', parentCopy);
    }
  });

  document.addEventListener('input', function (event) {
    var sectionRow = event.target.closest('[data-sc-section-row]');
    if (sectionRow && event.target.matches('input[name$="[title]"], input[data-name="title"]')) {
      var hidden = sectionRow.querySelector('input[name$="[slug]"], input[data-name="slug"]');
      if (hidden && !hidden.value) hidden.value = slugify(event.target.value);
    }
  });

  document.querySelectorAll('[data-sc-source-environment]').forEach(function (root) {
    renumber(root, '[data-sc-source-row]', 'sc_project_source_entries');
    renumber(root, '[data-sc-section-row]', 'sc_project_sections');
  });
  document.querySelectorAll('[data-sc-team-editor]').forEach(function (root) {
    renumber(root, '[data-sc-team-row]', 'sc_project_team');
  });
})();
