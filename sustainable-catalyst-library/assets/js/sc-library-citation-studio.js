(function () {
  'use strict';

  var config = window.SCLibraryCitationStudio || {};

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) {
      if (values[key] !== undefined && values[key] !== null) {
        body.set(key, values[key]);
      }
    });
    return fetch(config.ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          var data = payload && payload.data ? payload.data : {};
          throw new Error(data.message || 'Citation Studio request failed.');
        }
        return payload.data;
      });
    });
  }

  function formValues(form) {
    var values = {};
    new FormData(form).forEach(function (value, key) { values[key] = value; });
    return values;
  }

  function copy(text, button) {
    function success() {
      var original = button.textContent;
      button.textContent = 'Copied';
      window.setTimeout(function () { button.textContent = original; }, 1100);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(success).catch(function () {});
      return;
    }
    var area = document.createElement('textarea');
    area.value = text; area.setAttribute('readonly', ''); area.style.position = 'fixed'; area.style.opacity = '0';
    document.body.appendChild(area); area.select();
    try { document.execCommand('copy'); success(); } catch (e) {}
    document.body.removeChild(area);
  }

  function download(filename, mime, content) {
    var blob = new Blob([content], { type: mime || 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url; link.download = filename || 'sources.txt';
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 500);
  }

  document.querySelectorAll('[data-sc-citation-studio]').forEach(function (root) {
    if (!config.signedIn) { return; }
    var list = root.querySelector('[data-sc-source-list]');
    var style = root.querySelector('[data-sc-citation-style]');
    var collection = root.querySelector('[data-sc-source-collection-filter]');
    var search = root.querySelector('[data-sc-source-search]');
    var status = root.querySelector('[data-sc-citation-studio-status]');
    var count = root.querySelector('[data-sc-source-count]');

    function setStatus(text) { if (status) { status.textContent = text || ''; } }

    function applySearch() {
      var query = search ? search.value.toLowerCase().trim() : '';
      root.querySelectorAll('[data-sc-source-item]').forEach(function (item) {
        var matchText = (item.getAttribute('data-source-search') || '').toLowerCase();
        item.hidden = !!query && matchText.indexOf(query) === -1;
      });
    }

    function reload(message) {
      setStatus(message || 'Refreshing My Sources…');
      return request('sc_library_v4322_list_sources', {
        style: style ? style.value : (config.defaultStyle || 'harvard'),
        collection: collection ? collection.value : ''
      }).then(function (payload) {
        if (list) { list.innerHTML = payload.html || ''; }
        if (count) { count.textContent = String(payload.count || 0); }
        setStatus(message || 'My Sources updated.');
        applySearch();
        return payload;
      }).catch(function (error) { setStatus(error.message); });
    }

    document.addEventListener('sc:citation-source-saved', function () { reload('Research Access source added to My Sources.'); });
    if (style) { style.addEventListener('change', function () { reload('Citation style updated.'); }); }
    if (collection) { collection.addEventListener('change', function () { reload('Collection filter updated.'); }); }
    if (search) { search.addEventListener('input', applySearch); }

    var createForm = root.querySelector('[data-sc-create-source]');
    if (createForm) {
      createForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = createForm.querySelector('button[type="submit"]');
        var localStatus = createForm.querySelector('[data-sc-create-source-status]');
        if (button) { button.disabled = true; }
        if (localStatus) { localStatus.textContent = 'Saving source…'; }
        request('sc_library_v4322_create_source', formValues(createForm)).then(function (payload) {
          if (localStatus) { localStatus.textContent = payload.message || 'Source saved.'; }
          createForm.reset();
          return reload('Source saved to My Sources.');
        }).catch(function (error) {
          if (localStatus) { localStatus.textContent = error.message; }
        }).finally(function () { if (button) { button.disabled = false; } });
      });
    }

    var collectionForm = root.querySelector('[data-sc-create-collection]');
    if (collectionForm) {
      collectionForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var localStatus = collectionForm.querySelector('[data-sc-collection-status]');
        request('sc_library_v4322_create_collection', formValues(collectionForm)).then(function (payload) {
          if (localStatus) { localStatus.textContent = payload.message || 'Collection created.'; }
          var nameInput = collectionForm.querySelector('[name="name"]');
          var name = nameInput ? nameInput.value.trim() : '';
          if (collection && name && !Array.prototype.some.call(collection.options, function (option) { return option.value === name; })) {
            var option = document.createElement('option'); option.value = name; option.textContent = name; collection.appendChild(option);
          }
          collectionForm.reset();
        }).catch(function (error) { if (localStatus) { localStatus.textContent = error.message; } });
      });
    }

    root.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-sc-update-source]');
      if (!form) { return; }
      event.preventDefault();
      var localStatus = form.querySelector('[data-sc-source-action-status]');
      if (localStatus) { localStatus.textContent = 'Saving changes…'; }
      request('sc_library_v4322_update_source', formValues(form)).then(function (payload) {
        if (localStatus) { localStatus.textContent = payload.message || 'Source updated.'; }
        return reload('Source updated.');
      }).catch(function (error) { if (localStatus) { localStatus.textContent = error.message; } });
    });

    root.addEventListener('click', function (event) {
      var copyCitation = event.target.closest('[data-sc-copy-citation]');
      if (copyCitation) {
        event.preventDefault();
        var citation = copyCitation.closest('[data-sc-source-item]').querySelector('[data-sc-citation-value]');
        if (citation) { copy(citation.textContent.trim(), copyCitation); }
        return;
      }
      var copyIntext = event.target.closest('[data-sc-copy-intext]');
      if (copyIntext) {
        event.preventDefault(); copy(copyIntext.getAttribute('data-value') || '', copyIntext); return;
      }
      var remove = event.target.closest('[data-sc-delete-source]');
      if (remove) {
        event.preventDefault();
        if (!window.confirm('Remove this source from My Sources?')) { return; }
        remove.disabled = true;
        request('sc_library_v4322_delete_source', { source_id: remove.getAttribute('data-sc-delete-source') }).then(function () {
          return reload('Source removed.');
        }).catch(function (error) { remove.disabled = false; setStatus(error.message); });
        return;
      }
      var exportButton = event.target.closest('[data-sc-export-format]');
      if (exportButton) {
        event.preventDefault(); exportButton.disabled = true; setStatus('Preparing export…');
        request('sc_library_v4322_export_sources', {
          format: exportButton.getAttribute('data-sc-export-format'),
          collection: collection ? collection.value : ''
        }).then(function (payload) {
          download(payload.filename, payload.mime, payload.content || '');
          setStatus((payload.count || 0) + ' sources exported.');
        }).catch(function (error) { setStatus(error.message); }).finally(function () { exportButton.disabled = false; });
      }
    });
  });
})();
