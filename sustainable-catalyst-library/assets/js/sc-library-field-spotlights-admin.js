(() => {
  'use strict';
  const root = document.querySelector('[data-sc-field-spotlights-admin="v4.3.13"]');
  if (!root) return;
  const cfg = window.SCFieldSpotlightsAdmin || {};
  const panelSearch = root.querySelector('#sc-fs-panel-search');
  const panelFilter = root.querySelector('#sc-fs-panel-filter');
  const panelRows = Array.from(root.querySelectorAll('[data-panel-row]'));
  const resultCount = root.querySelector('#sc-fs-panel-result-count');

  const filterPanels = () => {
    const term = (panelSearch?.value || '').trim().toLowerCase();
    const filter = panelFilter?.value || 'all';
    let visible = 0;
    panelRows.forEach((row) => {
      const matchesText = !term || (row.dataset.title || '').includes(term);
      const matchesFilter = filter === 'all' || row.dataset.tier === filter || row.dataset.readiness === filter;
      row.hidden = !(matchesText && matchesFilter);
      if (!row.hidden) visible++;
    });
    if (resultCount) resultCount.textContent = `${visible} panel${visible === 1 ? '' : 's'}`;
  };
  panelSearch?.addEventListener('input', filterPanels);
  panelFilter?.addEventListener('change', filterPanels);

  const html = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  let controller = null;
  let timer = null;

  const renderResults = (slot, items, message = '') => {
    const results = slot.querySelector('[data-search-results]');
    if (!results) return;
    if (message) {
      results.innerHTML = `<div class="sc-fs-admin__search-message">${html(message)}</div>`;
      results.hidden = false;
      return;
    }
    if (!items.length) {
      results.innerHTML = `<div class="sc-fs-admin__search-message">${html(cfg.noResults || 'No published Library records found.')}</div>`;
      results.hidden = false;
      return;
    }
    results.innerHTML = items.map((item) => {
      const media = item.thumbnailUrl ? `<img src="${html(item.thumbnailUrl)}" alt="">` : '<span class="sc-fs-admin__result-placeholder">KL</span>';
      return `<button type="button" class="sc-fs-admin__search-result" data-result-id="${Number(item.id)||0}" data-result-title="${html(item.title)}" data-result-url="${html(item.url)}" data-result-meta="${html(item.metadata || item.type || '')}" data-result-thumb="${html(item.thumbnailUrl || '')}">${media}<span><strong>${html(item.title)}</strong><small>${html(item.metadata || item.type || '')}${item.excerpt ? ' · ' + html(item.excerpt) : ''}</small></span><span>${html(cfg.select || 'Select article')} →</span></button>`;
    }).join('');
    results.hidden = false;
  };

  root.querySelectorAll('[data-source-slot]').forEach((slot) => {
    const search = slot.querySelector('[data-source-search]');
    const results = slot.querySelector('[data-search-results]');
    const sourceId = slot.querySelector('[data-source-id]');
    const sourceUrl = slot.querySelector('[data-source-url]');
    const sourceTitle = slot.querySelector('[data-source-title]');
    const enabled = slot.querySelector('[data-source-enabled]');
    const publishState = slot.querySelector('[data-slot-publish-state]');
    const selectedTitle = slot.querySelector('[data-selected-title]');
    const selectedMeta = slot.querySelector('[data-selected-meta]');
    const thumb = slot.querySelector('[data-selected-thumb]');

    search?.addEventListener('input', () => {
      clearTimeout(timer);
      const query = search.value.trim();
      if (query.length < 2) { if (results) results.hidden = true; return; }
      timer = setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        renderResults(slot, [], cfg.searching || 'Searching…');
        try {
          const url = new URL(cfg.ajaxUrl, window.location.origin);
          url.searchParams.set('action', 'sc_library_field_spotlight_search_sources');
          url.searchParams.set('nonce', cfg.nonce || '');
          url.searchParams.set('q', query);
          const response = await fetch(url.toString(), { credentials: 'same-origin', signal: controller.signal });
          const data = await response.json();
          renderResults(slot, data?.success && Array.isArray(data?.data?.items) ? data.data.items : [], data?.success ? '' : (data?.data?.message || cfg.noResults));
        } catch (error) {
          if (error.name !== 'AbortError') renderResults(slot, [], cfg.noResults || 'Search unavailable.');
        }
      }, 280);
    });

    results?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-result-id]');
      if (!button) return;
      if (sourceId) sourceId.value = button.dataset.resultId || '0';
      if (sourceUrl) sourceUrl.value = button.dataset.resultUrl || '';
      if (sourceTitle) sourceTitle.value = '';
      if (enabled) enabled.value = '1';
      if (publishState) publishState.textContent = 'Publishes on save';
      if (selectedTitle) selectedTitle.textContent = button.dataset.resultTitle || cfg.configured || 'Configured';
      if (selectedMeta) selectedMeta.textContent = button.dataset.resultMeta || 'Knowledge Library';
      if (thumb) {
        const src = button.dataset.resultThumb || '';
        thumb.innerHTML = src ? `<img src="${html(src)}" alt="">` : '<span>KL</span>';
      }
      if (search) search.value = button.dataset.resultTitle || '';
      results.hidden = true;
    });

    slot.querySelector('[data-clear-slot]')?.addEventListener('click', () => {
      if (sourceId) sourceId.value = '0';
      if (sourceUrl) sourceUrl.value = '';
      if (sourceTitle) sourceTitle.value = '';
      if (enabled) enabled.value = '0';
      if (publishState) publishState.textContent = cfg.empty || 'Empty slot';
      if (search) search.value = '';
      if (selectedTitle) selectedTitle.textContent = cfg.empty || 'Empty slot';
      if (selectedMeta) selectedMeta.textContent = 'Search the Library to select a published article.';
      if (thumb) thumb.innerHTML = '<span>KL</span>';
      if (results) results.hidden = true;
    });
  });

  document.addEventListener('click', (event) => {
    root.querySelectorAll('[data-search-results]').forEach((results) => {
      if (!results.closest('[data-source-slot]')?.contains(event.target)) results.hidden = true;
    });
  });
})();
