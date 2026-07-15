(function () {
  'use strict';

  var config = window.SCLibraryKnowledgePathways || {};
  var strings = config.strings || {};
  var dragSource = null;

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
          throw new Error(data.message || 'The pathway action failed.');
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

  function renumber(editor) {
    if (!editor) return;
    editor.querySelectorAll('[data-sc-pathway-step-row]').forEach(function (row, index) {
      row.setAttribute('data-index', index);
      row.setAttribute('draggable', 'true');
      row.querySelectorAll('[data-step-field]').forEach(function (field) {
        var key = field.getAttribute('data-step-field');
        field.setAttribute('name', 'sc_pathway_steps[' + index + '][' + key + ']');
      });
    });
  }

  function addStep(editor) {
    var template = editor.querySelector('[data-sc-pathway-step-template]');
    var rows = editor.querySelector('[data-sc-pathway-step-rows]');
    if (!template || !rows) return;
    rows.appendChild(template.content.cloneNode(true));
    renumber(editor);
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function renderSearchResults(container, items, row) {
    if (!container) return;
    if (!items.length) {
      container.hidden = false;
      container.innerHTML = '<p>' + escapeHtml(strings.noResults || 'No matching records found.') + '</p>';
      return;
    }
    var html = '<div class="sc-pathway-search-results">';
    items.forEach(function (item) {
      html += '<button type="button" data-sc-select-pathway-node data-node-id="' + Number(item.id || 0) + '" data-node-label="' + escapeHtml(item.label || '') + '"><strong>' + escapeHtml(item.label || '') + '</strong><span>' + escapeHtml(item.status || '') + '</span></button>';
    });
    html += '</div>';
    container.hidden = false;
    container.innerHTML = html;
    container.querySelectorAll('[data-sc-select-pathway-node]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = row.querySelector('[data-step-field="node_id"]');
        var label = row.querySelector('[data-step-field="label"]');
        if (id) id.value = button.getAttribute('data-node-id') || '';
        if (label) label.value = button.getAttribute('data-node-label') || '';
        container.hidden = true;
        container.innerHTML = '';
      });
    });
  }

  document.addEventListener('click', function (event) {
    var add = event.target.closest('[data-sc-add-pathway-step]');
    if (add) {
      event.preventDefault();
      var editor = add.closest('[data-sc-pathway-editor]');
      if (editor) addStep(editor);
      return;
    }

    var remove = event.target.closest('[data-sc-remove-pathway-step]');
    if (remove) {
      event.preventDefault();
      var row = remove.closest('[data-sc-pathway-step-row]');
      var editor = remove.closest('[data-sc-pathway-editor]');
      if (row) row.remove();
      renumber(editor);
      return;
    }

    var find = event.target.closest('[data-sc-search-pathway-node]');
    if (find) {
      event.preventDefault();
      var searchRow = find.closest('[data-sc-pathway-step-row]');
      var kindField = searchRow ? searchRow.querySelector('[data-step-field="kind"]') : null;
      var labelField = searchRow ? searchRow.querySelector('[data-step-field="label"]') : null;
      var results = searchRow ? searchRow.querySelector('[data-sc-pathway-node-search]') : null;
      var query = window.prompt(strings.searchPrompt || 'Search by title or identifier', labelField ? labelField.value : '');
      if (!query || query.trim().length < 2) return;
      setStatus(results, strings.working || 'Working…', 'working');
      if (results) results.hidden = false;
      request('sc_library_v330_search_nodes', { kind: kindField ? kindField.value : 'document', query: query.trim() })
        .then(function (data) { renderSearchResults(results, Array.isArray(data.items) ? data.items : [], searchRow); })
        .catch(function (error) { setStatus(results, error.message, 'error'); });
      return;
    }

    var derive = event.target.closest('[data-sc-derive-pathway]');
    if (derive) {
      event.preventDefault();
      var scope = derive.closest('.sc-pathway-workspace__derive');
      var select = scope ? scope.querySelector('[data-sc-derived-project]') : null;
      var status = scope ? scope.querySelector('[data-sc-derive-pathway-status]') : null;
      var projectId = select ? select.value : '';
      if (!projectId || projectId === '0') return;
      derive.disabled = true;
      setStatus(status, strings.working || 'Working…', 'working');
      request('sc_library_v330_derive_pathway', { project_id: projectId })
        .then(function (data) {
          setStatus(status, strings.derived || 'Draft pathway created.', 'success');
          if (data.edit_url) window.location.href = data.edit_url;
        })
        .catch(function (error) { setStatus(status, error.message, 'error'); derive.disabled = false; });
      return;
    }

    var preview = event.target.closest('[data-sc-preview-pathway-map]');
    if (preview) {
      event.preventDefault();
      var box = preview.closest('[data-pathway-id]');
      var statusNode = box ? box.querySelector('[data-sc-pathway-map-status]') : null;
      var output = box ? box.querySelector('[data-sc-pathway-map-preview]') : null;
      preview.disabled = true;
      setStatus(statusNode, strings.working || 'Working…', 'working');
      request('sc_library_v330_preview_map', { pathway_id: box ? box.getAttribute('data-pathway-id') : '' })
        .then(function (data) {
          if (output) output.innerHTML = data.html || '';
          setStatus(statusNode, strings.previewed || 'Article map refreshed.', 'success');
        })
        .catch(function (error) { setStatus(statusNode, error.message, 'error'); })
        .finally(function () { preview.disabled = false; });
      return;
    }

    var toggle = event.target.closest('[data-sc-toggle-map-list]');
    if (toggle) {
      event.preventDefault();
      var mapSection = toggle.closest('.sc-public-pathway__map');
      var list = mapSection ? mapSection.querySelector('[data-sc-map-list]') : null;
      if (list) list.hidden = !list.hidden;
    }
  });

  document.addEventListener('dragstart', function (event) {
    var row = event.target.closest('[data-sc-pathway-step-row]');
    if (!row) return;
    dragSource = row;
    row.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });

  document.addEventListener('dragover', function (event) {
    var row = event.target.closest('[data-sc-pathway-step-row]');
    if (!row || !dragSource || row === dragSource) return;
    event.preventDefault();
    var bounds = row.getBoundingClientRect();
    var before = event.clientY < bounds.top + bounds.height / 2;
    row.parentNode.insertBefore(dragSource, before ? row : row.nextSibling);
  });

  document.addEventListener('dragend', function () {
    if (!dragSource) return;
    var editor = dragSource.closest('[data-sc-pathway-editor]');
    dragSource.classList.remove('is-dragging');
    dragSource = null;
    renumber(editor);
  });

  document.querySelectorAll('[data-sc-pathway-editor]').forEach(renumber);
  document.querySelectorAll('[data-sc-map-list]').forEach(function (list) { list.hidden = true; });
})();
