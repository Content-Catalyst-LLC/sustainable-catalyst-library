(function () {
  'use strict';

  var config = window.SCLibraryDocumentIntelligence || {};
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
          throw new Error(data.message || strings.error || 'The document-intelligence operation failed.');
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

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function retrievalMarkup(retrieval) {
    var results = retrieval && Array.isArray(retrieval.results) ? retrieval.results : [];
    if (!results.length) {
      return '<p>No matching document-intelligence profiles were found.</p>';
    }
    return '<ol class="sc-doc-intel-search-results">' + results.map(function (item) {
      var title = escapeHtml(item.title || 'Untitled document');
      var url = item.url ? '<a href="' + escapeHtml(item.url) + '">' + title + '</a>' : title;
      var reasons = Array.isArray(item.reasons) ? item.reasons.join(', ') : '';
      return '<li><h3>' + url + '</h3>' +
        '<p>' + escapeHtml(item.summary || '') + '</p>' +
        '<small>Score ' + Number(item.score || 0) + (reasons ? ' · ' + escapeHtml(reasons) : '') + '</small></li>';
    }).join('') + '</ol>';
  }

  function comparisonMarkup(comparison) {
    var documents = comparison && Array.isArray(comparison.documents) ? comparison.documents : [];
    if (!documents.length) {
      return '<p>No comparison result was returned.</p>';
    }
    var documentMarkup = documents.map(function (document) {
      return '<article><h3>' + escapeHtml(document.title || 'Untitled document') + '</h3>' +
        '<p>' + escapeHtml(document.summary || '') + '</p>' +
        '<p><strong>Distinctive terms:</strong> ' +
        escapeHtml((document.unique_terms || []).join(', ') || '—') + '</p></article>';
    }).join('');
    return '<div class="sc-doc-intel-compare-output">' + documentMarkup +
      '<article><h3>Shared terms</h3><p>' +
      escapeHtml((comparison.shared_terms || []).join(', ') || '—') +
      '</p><h3>Shared sections</h3><p>' +
      escapeHtml((comparison.shared_sections || []).join(', ') || '—') +
      '</p></article></div>';
  }

  document.addEventListener('click', function (event) {
    var root = event.target.closest('[data-sc-doc-intel-document]');
    var analyze = event.target.closest('[data-sc-doc-intel-analyze], [data-sc-doc-intel-force]');
    if (analyze && root) {
      event.preventDefault();
      var statusNode = root.querySelector('[data-sc-doc-intel-status]');
      var force = analyze.matches('[data-sc-doc-intel-force]') ? '1' : '';
      root.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
      setStatus(statusNode, strings.working || 'Analyzing document…', 'working');

      request('sc_library_v370_analyze_document', {
        document_id: root.getAttribute('data-sc-doc-intel-document'),
        force: force
      }).then(function () {
        setStatus(statusNode, strings.complete || 'Document intelligence updated.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        setStatus(statusNode, error.message, 'error');
        root.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
      });
      return;
    }

    var migration = event.target.closest('[data-sc-doc-intel-run-migration]');
    if (migration) {
      event.preventDefault();
      var migrationStatus = document.querySelector('[data-sc-doc-intel-migration-status]');
      migration.disabled = true;
      setStatus(migrationStatus, strings.working || 'Analyzing document…', 'working');

      request('sc_library_v370_run_migration', {}).then(function (data) {
        var state = data.migration || {};
        var stateNode = document.querySelector('[data-sc-doc-intel-migration-state]');
        if (stateNode) stateNode.textContent = state.status || 'pending';
        setStatus(
          migrationStatus,
          (strings.complete || 'Document intelligence updated.') + ' ' +
            Number(state.processed || 0) + ' of ' + Number(state.total || 0) + ' processed.',
          'success'
        );
        if (state.status !== 'complete') migration.disabled = false;
      }).catch(function (error) {
        setStatus(migrationStatus, error.message, 'error');
        migration.disabled = false;
      });
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;

    if (form.matches('[data-sc-doc-intel-search-form]')) {
      event.preventDefault();
      var query = form.querySelector('[name="query"]');
      var output = document.querySelector('[data-sc-doc-intel-search-results]');
      var button = form.querySelector('button');
      if (button) button.disabled = true;
      setStatus(output, 'Searching document profiles…', 'working');

      request('sc_library_v370_search_documents', {
        query: query ? query.value : ''
      }).then(function (data) {
        output.classList.remove('is-working', 'is-error');
        output.innerHTML = retrievalMarkup(data.retrieval || {});
        if (button) button.disabled = false;
      }).catch(function (error) {
        setStatus(output, error.message, 'error');
        if (button) button.disabled = false;
      });
      return;
    }

    if (form.matches('[data-sc-doc-intel-compare-form]')) {
      event.preventDefault();
      var ids = form.querySelector('[name="document_ids"]');
      var comparisonOutput = document.querySelector('[data-sc-doc-intel-compare-results]');
      var compareButton = form.querySelector('button');
      if (compareButton) compareButton.disabled = true;
      setStatus(comparisonOutput, 'Comparing documents…', 'working');

      request('sc_library_v370_compare_documents', {
        document_ids: ids ? ids.value : ''
      }).then(function (data) {
        comparisonOutput.classList.remove('is-working', 'is-error');
        comparisonOutput.innerHTML = comparisonMarkup(data.comparison || {});
        if (compareButton) compareButton.disabled = false;
      }).catch(function (error) {
        setStatus(comparisonOutput, error.message, 'error');
        if (compareButton) compareButton.disabled = false;
      });
    }
  });
})();
