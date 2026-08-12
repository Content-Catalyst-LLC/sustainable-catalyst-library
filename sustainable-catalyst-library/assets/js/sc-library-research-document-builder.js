(function () {
  'use strict';

  var config = window.SCLibraryResearchDocumentBuilder || {};

  function postForm(action, formData) {
    var body = formData || new FormData();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          var data = payload && payload.data ? payload.data : {};
          throw new Error(data.message || 'Research Document Builder request failed.');
        }
        return payload.data;
      });
    });
  }

  function selectedSourceIds(root) {
    return Array.prototype.map.call(root.querySelectorAll('[data-sc-document-source-checkbox]:checked'), function (box) {
      return box.value;
    });
  }

  function formDataFor(root, format) {
    var form = root.querySelector('[data-sc-document-form]');
    var data = new FormData(form);
    data.set('source_ids', selectedSourceIds(root).join(','));
    if (format) { data.set('format', format); }
    return data;
  }

  function setStatus(root, text) {
    var status = root.querySelector('[data-sc-document-status]');
    if (status) { status.textContent = text || ''; }
  }

  function triggerDownload(blob, filename) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'sustainable-catalyst-research-document';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 900);
  }

  function filenameFromDisposition(value, fallback) {
    if (!value) { return fallback; }
    var match = value.match(/filename="?([^";]+)"?/i);
    return match && match[1] ? match[1] : fallback;
  }

  function decodeSaved(value) {
    try {
      var bytes = Uint8Array.from(atob(value || ''), function (char) { return char.charCodeAt(0); });
      return JSON.parse(new TextDecoder('utf-8').decode(bytes));
    } catch (error) {
      return null;
    }
  }

  function setCheckedSources(root, ids) {
    var wanted = {};
    (ids || []).forEach(function (id) { wanted[String(id)] = true; });
    root.querySelectorAll('[data-sc-document-source-checkbox]').forEach(function (box) {
      box.checked = !!wanted[String(box.value)];
    });
  }

  function loadDocument(root, documentData) {
    if (!documentData) { return; }
    var form = root.querySelector('[data-sc-document-form]');
    if (!form) { return; }
    ['title', 'template', 'style', 'research_question', 'notes'].forEach(function (name) {
      var field = form.elements[name];
      if (field) { field.value = documentData[name] || ''; }
    });
    var id = form.querySelector('[data-sc-document-id]');
    if (id) { id.value = documentData.id || ''; }
    var sourceNotes = form.elements.include_source_notes;
    var urls = form.elements.include_urls;
    if (sourceNotes) { sourceNotes.checked = !!documentData.include_source_notes; }
    if (urls) { urls.checked = !!documentData.include_urls; }
    setCheckedSources(root, documentData.source_ids || []);
    setStatus(root, 'Draft loaded.');
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function newDocument(root) {
    var form = root.querySelector('[data-sc-document-form]');
    if (!form) { return; }
    form.reset();
    var id = form.querySelector('[data-sc-document-id]');
    if (id) { id.value = ''; }
    var sourceNotes = form.elements.include_source_notes;
    var urls = form.elements.include_urls;
    if (sourceNotes) { sourceNotes.checked = true; }
    if (urls) { urls.checked = true; }
    setCheckedSources(root, []);
    setStatus(root, 'New document ready.');
  }

  function refreshSources(root, preserveIds) {
    var form = root.querySelector('[data-sc-document-form]');
    var style = form && form.elements.style ? form.elements.style.value : 'harvard';
    var data = new FormData();
    data.set('style', style);
    setStatus(root, 'Refreshing My Sources…');
    return postForm('sc_library_v4323_list_builder_sources', data).then(function (payload) {
      var list = root.querySelector('[data-sc-document-source-list]');
      if (list) { list.innerHTML = payload.html || ''; }
      setCheckedSources(root, preserveIds || []);
      setStatus(root, (payload.count || 0) + ' sources available.');
      return payload;
    }).catch(function (error) {
      setStatus(root, error.message);
    });
  }

  document.querySelectorAll('[data-sc-research-document-builder]').forEach(function (root) {
    if (!config.signedIn) { return; }
    var form = root.querySelector('[data-sc-document-form]');
    var savedList = root.querySelector('[data-sc-saved-document-list]');

    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = root.querySelector('[data-sc-document-save]');
        if (button) { button.disabled = true; }
        setStatus(root, 'Saving research document draft…');
        postForm('sc_library_v4323_save_document', formDataFor(root)).then(function (payload) {
          var id = root.querySelector('[data-sc-document-id]');
          if (id && payload.document) { id.value = payload.document.id || ''; }
          if (savedList) { savedList.innerHTML = payload.html || ''; }
          setStatus(root, payload.message || 'Draft saved.');
        }).catch(function (error) {
          setStatus(root, error.message);
        }).finally(function () {
          if (button) { button.disabled = false; }
        });
      });
    }

    root.addEventListener('click', function (event) {
      var selectAll = event.target.closest('[data-sc-document-select-all]');
      if (selectAll) {
        event.preventDefault();
        root.querySelectorAll('[data-sc-document-source-checkbox]').forEach(function (box) { box.checked = true; });
        setStatus(root, selectedSourceIds(root).length + ' sources selected.');
        return;
      }
      var clearAll = event.target.closest('[data-sc-document-clear-all]');
      if (clearAll) {
        event.preventDefault(); setCheckedSources(root, []); setStatus(root, 'Source selection cleared.'); return;
      }
      var refresh = event.target.closest('[data-sc-document-refresh-sources]');
      if (refresh) {
        event.preventDefault(); refreshSources(root, selectedSourceIds(root)); return;
      }
      var createNew = event.target.closest('[data-sc-document-new]');
      if (createNew) {
        event.preventDefault(); newDocument(root); root.scrollIntoView({ behavior: 'smooth', block: 'start' }); return;
      }
      var load = event.target.closest('[data-sc-document-load]');
      if (load) {
        event.preventDefault(); loadDocument(root, decodeSaved(load.getAttribute('data-sc-document-load'))); return;
      }
      var remove = event.target.closest('[data-sc-document-delete]');
      if (remove) {
        event.preventDefault();
        if (!window.confirm('Delete this saved research document draft?')) { return; }
        var data = new FormData();
        data.set('document_id', remove.getAttribute('data-sc-document-delete') || '');
        remove.disabled = true;
        postForm('sc_library_v4323_delete_document', data).then(function (payload) {
          if (savedList) { savedList.innerHTML = payload.html || ''; }
          setStatus(root, payload.message || 'Draft deleted.');
        }).catch(function (error) {
          remove.disabled = false; setStatus(root, error.message);
        });
        return;
      }
      var exportButton = event.target.closest('[data-sc-document-export]');
      if (exportButton) {
        event.preventDefault();
        var format = exportButton.getAttribute('data-sc-document-export') || '';
        exportButton.disabled = true;
        setStatus(root, 'Generating ' + format.toUpperCase() + '…');
        var data = formDataFor(root, format);
        data.set('action', 'sc_library_v4323_export_document');
        data.set('nonce', config.nonce || '');
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data }).then(function (response) {
          var type = response.headers.get('content-type') || '';
          if (!response.ok || type.indexOf('application/json') !== -1) {
            return response.json().then(function (payload) {
              var message = payload && payload.data && payload.data.message ? payload.data.message : 'Document export failed.';
              throw new Error(message);
            });
          }
          var fallback = 'sustainable-catalyst-research-document.' + format;
          var filename = filenameFromDisposition(response.headers.get('content-disposition'), fallback);
          return response.blob().then(function (blob) {
            triggerDownload(blob, filename);
            setStatus(root, format.toUpperCase() + ' downloaded.');
          });
        }).catch(function (error) {
          setStatus(root, error.message);
        }).finally(function () {
          exportButton.disabled = false;
        });
      }
    });

    document.addEventListener('sc:citation-source-saved', function () {
      refreshSources(root, selectedSourceIds(root));
    });
  });

  document.addEventListener('click', function (event) {
    var add = event.target.closest('[data-sc-add-source-to-document]');
    if (!add) { return; }
    var builder = document.querySelector('[data-sc-research-document-builder]');
    if (!builder || !config.signedIn) { return; }
    var id = add.getAttribute('data-sc-add-source-to-document') || '';
    var checkbox = null;
    builder.querySelectorAll('[data-sc-document-source-checkbox]').forEach(function (candidate) {
      if (!checkbox && String(candidate.value) === String(id)) { checkbox = candidate; }
    });
    if (checkbox) {
      checkbox.checked = true;
      var card = checkbox.closest('[data-sc-document-source-card]');
      if (card) {
        card.classList.add('is-added');
        window.setTimeout(function () { card.classList.remove('is-added'); }, 1400);
      }
      setStatus(builder, 'Source added to the current document selection.');
    }
  });
})();
