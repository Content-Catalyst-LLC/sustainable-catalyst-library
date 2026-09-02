(() => {
  'use strict';

  const cfg = window.SCLibraryExplorerV560 || {};
  const roots = Array.from(document.querySelectorAll('[data-sc-library-explorer]'));
  if (!roots.length || !cfg.restBase) return;

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
  const cleanText = (value) => String(value ?? '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
  const fmt = (value) => Number.isFinite(Number(value)) ? Number(value).toLocaleString() : '—';
  const fmtDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };
  const typeLabel = (value) => ({
    post: 'Article', page: 'Page', sc_foundation_doc: 'Foundation Document', sc_pdf_document: 'Document', sc_content_plan: 'Planned Content'
  }[String(value || '')] || String(value || 'Research record').replace(/^sc_/, '').replace(/_/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase()));

  const request = async (path, params = {}) => {
    const url = new URL(`${String(cfg.restBase).replace(/\/$/, '')}/${String(path).replace(/^\//, '')}`, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value);
    });
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || `Library request failed (${response.status}).`);
      return data;
    } finally {
      window.clearTimeout(timer);
    }
  };

  const cardHtml = (record, compact = false) => {
    const topics = Array.isArray(record.topics) ? record.topics.slice(0, compact ? 2 : 3) : [];
    const abstract = cleanText(record.abstract || record.snippet || '');
    return `
      <article class="sc-library-explorer-card" data-record-id="${esc(record.record_id)}">
        <div class="sc-library-explorer-card__meta">
          <span>${esc(typeLabel(record.object_type))}</span>
          ${record.source_updated_at ? `<time datetime="${esc(record.source_updated_at)}">${esc(fmtDate(record.source_updated_at))}</time>` : ''}
        </div>
        <h4><a href="${esc(record.canonical_url || '#')}">${esc(record.title || 'Untitled record')}</a></h4>
        ${abstract ? `<p>${esc(abstract.length > (compact ? 180 : 260) ? `${abstract.slice(0, compact ? 180 : 260)}…` : abstract)}</p>` : ''}
        ${topics.length ? `<div class="sc-library-explorer-card__topics">${topics.map((topic) => `<button type="button" data-card-topic="${esc(topic)}">${esc(topic)}</button>`).join('')}</div>` : ''}
        <div class="sc-library-explorer-card__actions">
          <button type="button" data-quick-view="${esc(record.record_id)}">${esc(cfg.strings?.quickView || 'Quick view')}</button>
          ${record.canonical_url ? `<a href="${esc(record.canonical_url)}">Read full record →</a>` : ''}
        </div>
      </article>`;
  };

  roots.forEach((root) => {
    const perPage = Math.max(6, Math.min(30, Number(root.dataset.perPage || 12)));
    const stateEl = root.querySelector('[data-explorer-state]');
    const qInput = root.querySelector('[data-explorer-q]');
    const searchForm = root.querySelector('[data-explorer-search]');
    const filterToggle = root.querySelector('[data-explorer-filter-toggle]');
    const filtersPanel = root.querySelector('[data-explorer-filters]');
    const typeSelect = root.querySelector('[data-filter-type]');
    const topicSelect = root.querySelector('[data-filter-topic]');
    const sourceSelect = root.querySelector('[data-filter-source]');
    const yearFrom = root.querySelector('[data-filter-year-from]');
    const yearTo = root.querySelector('[data-filter-year-to]');
    const sortSelect = root.querySelector('[data-filter-sort]');
    const resetButton = root.querySelector('[data-explorer-reset]');
    const topicStrip = root.querySelector('[data-explorer-topic-strip]');
    const featuredSection = root.querySelector('[data-explorer-featured-section]');
    const featured = root.querySelector('[data-explorer-featured]');
    const resultsSection = root.querySelector('[data-explorer-results-section]');
    const results = root.querySelector('[data-explorer-results]');
    const resultsTitle = root.querySelector('[data-results-title]');
    const resultsCount = root.querySelector('[data-results-count]');
    const activeFilters = root.querySelector('[data-active-filters]');
    const loadMore = root.querySelector('[data-explorer-load-more]');
    const drawer = root.querySelector('[data-explorer-drawer]');
    const drawerContent = root.querySelector('[data-drawer-content]');

    const state = { q: '', object_type: '', topic: '', source_key: '', year_from: '', year_to: '', sort: 'relevance', offset: 0, total: 0, transport: '' };
    let bootstrapData = null;
    let lastFocus = null;

    const setStateMessage = (text, mode = '') => {
      if (!stateEl) return;
      stateEl.classList.toggle('is-fallback', mode === 'fallback');
      stateEl.classList.toggle('is-error', mode === 'error');
      const span = stateEl.querySelector('span:last-child');
      if (span) span.textContent = text;
    };

    const populateSelect = (select, items, valueKey, labelKey, countKey = 'count') => {
      if (!select) return;
      const first = select.options[0]?.cloneNode(true);
      select.innerHTML = '';
      if (first) select.appendChild(first);
      (items || []).forEach((item) => {
        const value = item[valueKey];
        if (value === null || value === undefined || value === '') return;
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = `${item[labelKey] ?? value}${item[countKey] !== undefined ? ` (${fmt(item[countKey])})` : ''}`;
        select.appendChild(option);
      });
    };

    const readUrl = () => {
      const url = new URL(window.location.href);
      state.q = url.searchParams.get('library_q') || '';
      state.topic = url.searchParams.get('library_topic') || '';
      state.object_type = url.searchParams.get('library_type') || '';
      state.source_key = url.searchParams.get('library_source') || '';
      state.year_from = url.searchParams.get('library_from') || '';
      state.year_to = url.searchParams.get('library_to') || '';
      state.sort = url.searchParams.get('library_sort') || (state.q ? 'relevance' : 'updated');
      state.offset = 0;
      if (qInput) qInput.value = state.q;
    };

    const syncControls = () => {
      if (typeSelect) typeSelect.value = state.object_type;
      if (topicSelect) topicSelect.value = state.topic;
      if (sourceSelect) sourceSelect.value = state.source_key;
      if (yearFrom) yearFrom.value = state.year_from;
      if (yearTo) yearTo.value = state.year_to;
      if (sortSelect) sortSelect.value = state.sort;
    };

    const updateUrl = (replace = false) => {
      const url = new URL(window.location.href);
      const mapping = {
        library_q: state.q, library_topic: state.topic, library_type: state.object_type,
        library_source: state.source_key, library_from: state.year_from, library_to: state.year_to,
        library_sort: state.sort && state.sort !== (state.q ? 'relevance' : 'updated') ? state.sort : ''
      };
      Object.entries(mapping).forEach(([key, value]) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
      url.searchParams.delete('library_record');
      window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
    };

    const renderTopics = (topics) => {
      if (!topicStrip) return;
      topicStrip.innerHTML = (topics || []).slice(0, 10).map((topic) => `
        <button type="button" data-topic="${esc(topic.topic)}" class="${state.topic === topic.topic ? 'is-active' : ''}" style="display:inline-flex!important;align-items:center!important;gap:7px!important;min-height:34px!important;padding:7px 11px!important;border:1px solid #d7dbd7!important;border-radius:999px!important;background:#fff!important;color:#151515!important;-webkit-text-fill-color:#151515!important;font-size:11px!important;font-weight:800!important;line-height:1.15!important;visibility:visible!important;opacity:1!important;appearance:none!important;-webkit-appearance:none!important;">
          <span style="color:inherit!important;-webkit-text-fill-color:currentColor!important;">${esc(topic.topic)}</span><em style="color:inherit!important;-webkit-text-fill-color:currentColor!important;">${fmt(topic.count)}</em>
        </button>`).join('');
    };

    const renderMetrics = (data) => {
      root.querySelector('[data-metric-records]').textContent = fmt(data.stats?.public_records ?? data.stats?.records);
      root.querySelector('[data-metric-topics]').textContent = fmt(data.facets?.topics?.length);
      root.querySelector('[data-metric-chunks]').textContent = data.stats?.chunks === null || data.stats?.chunks === undefined ? 'Local' : fmt(data.stats.chunks);
    };

    const renderFeatured = (items) => {
      if (!featured) return;
      featured.innerHTML = (items || []).slice(0, 4).map((record) => cardHtml(record, true)).join('');
      if (featuredSection) featuredSection.hidden = !(items || []).length;
    };

    const renderActive = () => {
      if (!activeFilters) return;
      const values = [];
      if (state.q) values.push(`Search: “${state.q}”`);
      if (state.topic) values.push(`Topic: ${state.topic}`);
      if (state.object_type) values.push(`Type: ${typeLabel(state.object_type)}`);
      if (state.year_from) values.push(`From ${state.year_from}`);
      if (state.year_to) values.push(`Through ${state.year_to}`);
      activeFilters.innerHTML = values.map((value) => `<span>${esc(value)}</span>`).join('');
      activeFilters.hidden = values.length === 0;
    };

    const searchParams = () => ({
      q: state.q,
      object_type: state.object_type,
      topic: state.topic,
      source_key: state.source_key,
      year_from: state.year_from,
      year_to: state.year_to,
      sort: state.sort,
      limit: perPage,
      offset: state.offset
    });

    const runSearch = async ({ append = false, push = true } = {}) => {
      if (!resultsSection || !results) return;
      resultsSection.hidden = false;
      if (!append) {
        state.offset = 0;
        results.innerHTML = '<div class="sc-library-explorer__loading">Loading results…</div>';
      }
      if (resultsCount) resultsCount.textContent = cfg.strings?.searching || 'Searching the Library…';
      try {
        const data = await request('search', searchParams());
        state.total = Number(data.total || 0);
        state.transport = data.transport || state.transport;
        const incoming = Array.isArray(data.results) ? data.results : [];
        if (append) results.insertAdjacentHTML('beforeend', incoming.map((record) => cardHtml(record)).join(''));
        else results.innerHTML = incoming.length ? incoming.map((record) => cardHtml(record)).join('') : `<p class="sc-library-explorer__empty">${esc(cfg.strings?.empty || 'No records found.')}</p>`;
        if (resultsTitle) resultsTitle.textContent = state.q ? `Results for “${state.q}”` : (state.topic || 'Browse the Library');
        if (resultsCount) resultsCount.textContent = `${fmt(state.total)} ${state.total === 1 ? 'record' : 'records'}`;
        if (loadMore) loadMore.hidden = state.offset + incoming.length >= state.total || incoming.length === 0;
        renderActive();
        renderTopics(bootstrapData?.facets?.topics || []);
        if (push) updateUrl(false);
        if (data.transport === 'wordpress-fallback') setStateMessage('Local catalog fallback is active.', 'fallback');
      } catch (error) {
        results.innerHTML = `<div class="sc-library-explorer__error"><strong>${esc(cfg.strings?.error || 'Dynamic discovery is unavailable.')}</strong><a href="${esc(cfg.legacyUrl || '#')}">${esc(cfg.strings?.fallback || 'Use the local Library catalog')} →</a></div>`;
        if (resultsCount) resultsCount.textContent = '';
        if (loadMore) loadMore.hidden = true;
        setStateMessage(cfg.strings?.error || 'Dynamic discovery is temporarily unavailable.', 'error');
      }
    };

    const applyControls = () => {
      state.q = qInput?.value.trim() || '';
      state.object_type = typeSelect?.value || '';
      state.topic = topicSelect?.value || '';
      state.source_key = sourceSelect?.value || '';
      state.year_from = yearFrom?.value || '';
      state.year_to = yearTo?.value || '';
      state.sort = sortSelect?.value || (state.q ? 'relevance' : 'updated');
      state.offset = 0;
    };

    const closeDrawer = () => {
      if (!drawer) return;
      drawer.hidden = true;
      document.body.classList.remove('sc-library-explorer-drawer-open');
      const url = new URL(window.location.href);
      url.searchParams.delete('library_record');
      window.history.replaceState({}, '', url);
      lastFocus?.focus?.();
    };

    const drawerTabs = (record) => `
      <div class="sc-library-explorer-drawer__tabs" role="tablist">
        <button type="button" data-drawer-tab="overview" class="is-active">Overview</button>
        <button type="button" data-drawer-tab="related">Related</button>
        <button type="button" data-drawer-tab="provenance">Provenance</button>
        <button type="button" data-drawer-tab="timeline">Timeline</button>
      </div>
      <div data-drawer-tab-content="overview">
        ${record.abstract ? `<p class="sc-library-explorer-drawer__abstract">${esc(cleanText(record.abstract))}</p>` : ''}
        ${record.body_text ? `<p>${esc(cleanText(record.body_text))}</p>` : ''}
      </div>
      <div data-drawer-tab-content="related" hidden><p>Open this tab to load related research.</p></div>
      <div data-drawer-tab-content="provenance" hidden>
        <dl class="sc-library-explorer-drawer__provenance">
          <div><dt>Source</dt><dd>${esc(record.source_key || '—')}</dd></div>
          <div><dt>Published</dt><dd>${esc(fmtDate(record.published_at) || '—')}</dd></div>
          <div><dt>Updated</dt><dd>${esc(fmtDate(record.source_updated_at) || '—')}</dd></div>
          <div><dt>Revision</dt><dd>${esc(record.revision ?? '—')}</dd></div>
        </dl>
        ${(record.identifiers && Object.keys(record.identifiers).length) ? `<h4>Identifiers</h4><pre>${esc(JSON.stringify(record.identifiers, null, 2))}</pre>` : ''}
      </div>
      <div data-drawer-tab-content="timeline" hidden><p>Open this tab to load record history.</p></div>`;

    const loadRelated = async (recordId, container) => {
      container.innerHTML = '<p>Loading related research…</p>';
      try {
        const data = await request(`records/${encodeURIComponent(recordId)}/related`, { limit: 6 });
        const rows = Array.isArray(data.results) ? data.results : [];
        container.innerHTML = rows.length ? `<div class="sc-library-explorer-drawer__related">${rows.map((row) => `<a href="${esc(row.canonical_url || '#')}"><strong>${esc(row.title)}</strong><span>${esc(typeLabel(row.object_type))}</span></a>`).join('')}</div>` : '<p>No related records are available yet.</p>';
      } catch (_) { container.innerHTML = '<p>Related research is temporarily unavailable.</p>'; }
    };

    const loadTimeline = async (recordId, container) => {
      container.innerHTML = '<p>Loading record history…</p>';
      try {
        const data = await request(`records/${encodeURIComponent(recordId)}/timeline`, { limit: 10 });
        const rows = Array.isArray(data.versions) ? data.versions : [];
        container.innerHTML = rows.length ? `<ol class="sc-library-explorer-drawer__timeline">${rows.map((row) => `<li><strong>Revision ${esc(row.revision)}</strong><span>${esc(fmtDate(row.observed_at || row.source_updated_at) || 'Observed')}</span></li>`).join('')}</ol>` : '<p>No record history is available yet.</p>';
      } catch (_) { container.innerHTML = '<p>Record history is temporarily unavailable.</p>'; }
    };

    const openDrawer = async (recordId, trigger = null, updateHistory = true) => {
      if (!drawer || !drawerContent || !recordId) return;
      lastFocus = trigger || document.activeElement;
      drawer.hidden = false;
      document.body.classList.add('sc-library-explorer-drawer-open');
      drawerContent.innerHTML = '<div class="sc-library-explorer__loading">Loading record…</div>';
      try {
        const data = await request(`records/${encodeURIComponent(recordId)}`);
        const record = data.record || {};
        drawerContent.innerHTML = `
          <div class="sc-library-explorer-drawer__heading">
            <p>${esc(typeLabel(record.object_type))}</p>
            <h3 id="${esc(root.id)}-drawer-title">${esc(record.title || 'Knowledge record')}</h3>
            <div>${(record.topics || []).slice(0, 5).map((topic) => `<span>${esc(topic)}</span>`).join('')}</div>
            ${record.canonical_url ? `<a class="sc-library-explorer-drawer__primary" href="${esc(record.canonical_url)}">Read full record →</a>` : ''}
          </div>
          ${drawerTabs(record)}`;
        drawerContent.querySelectorAll('[data-drawer-tab]').forEach((button) => button.addEventListener('click', async () => {
          drawerContent.querySelectorAll('[data-drawer-tab]').forEach((tab) => tab.classList.toggle('is-active', tab === button));
          drawerContent.querySelectorAll('[data-drawer-tab-content]').forEach((panel) => { panel.hidden = panel.dataset.drawerTabContent !== button.dataset.drawerTab; });
          const panel = drawerContent.querySelector(`[data-drawer-tab-content="${button.dataset.drawerTab}"]`);
          if (button.dataset.drawerTab === 'related' && !panel.dataset.loaded) { panel.dataset.loaded = '1'; await loadRelated(recordId, panel); }
          if (button.dataset.drawerTab === 'timeline' && !panel.dataset.loaded) { panel.dataset.loaded = '1'; await loadTimeline(recordId, panel); }
        }));
        if (updateHistory) {
          const url = new URL(window.location.href);
          url.searchParams.set('library_record', recordId);
          window.history.replaceState({}, '', url);
        }
      } catch (error) {
        drawerContent.innerHTML = `<div class="sc-library-explorer__error"><strong>Record preview unavailable.</strong><a href="${esc(cfg.legacyUrl || '#')}">Open local catalog →</a></div>`;
      }
    };

    root.addEventListener('click', (event) => {
      const topicButton = event.target.closest('[data-topic], [data-card-topic]');
      if (topicButton) {
        event.preventDefault();
        state.topic = topicButton.dataset.topic || topicButton.dataset.cardTopic || '';
        if (topicSelect) topicSelect.value = state.topic;
        state.q = qInput?.value.trim() || state.q;
        state.sort = state.q ? 'relevance' : 'updated';
        if (sortSelect) sortSelect.value = state.sort;
        state.offset = 0;
        runSearch();
        return;
      }
      const quick = event.target.closest('[data-quick-view]');
      if (quick) { event.preventDefault(); openDrawer(quick.dataset.quickView, quick); }
      if (event.target.closest('[data-drawer-close]')) closeDrawer();
    });

    searchForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      applyControls();
      if (state.q && sortSelect?.value === 'updated') { state.sort = 'relevance'; sortSelect.value = 'relevance'; }
      runSearch();
    });

    filterToggle?.addEventListener('click', () => {
      const open = filtersPanel?.hidden !== false;
      if (filtersPanel) filtersPanel.hidden = !open;
      filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    [typeSelect, topicSelect, sourceSelect, yearFrom, yearTo, sortSelect].forEach((control) => control?.addEventListener('change', () => {
      applyControls();
      runSearch();
    }));

    resetButton?.addEventListener('click', () => {
      state.q = ''; state.object_type = ''; state.topic = ''; state.source_key = ''; state.year_from = ''; state.year_to = ''; state.sort = 'updated'; state.offset = 0;
      if (qInput) qInput.value = '';
      syncControls();
      runSearch();
    });

    loadMore?.addEventListener('click', () => {
      state.offset += perPage;
      runSearch({ append: true, push: false });
    });

    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer && !drawer.hidden) closeDrawer(); });
    window.addEventListener('popstate', () => {
      readUrl(); syncControls();
      const url = new URL(window.location.href);
      if (state.q || state.topic || state.object_type || state.source_key || state.year_from || state.year_to) runSearch({ push: false });
      const recordId = url.searchParams.get('library_record');
      if (recordId) openDrawer(recordId, null, false); else closeDrawer();
    });

    const boot = async () => {
      readUrl();
      setStateMessage(cfg.strings?.loading || 'Loading Library intelligence…');
      try {
        const data = await request('bootstrap');
        bootstrapData = data;
        state.transport = data.transport || '';
        renderMetrics(data);
        populateSelect(typeSelect, data.facets?.object_types || [], 'object_type', 'label');
        // Python facets do not include labels; preserve a readable type label.
        Array.from(typeSelect?.options || []).slice(1).forEach((option) => { option.textContent = `${typeLabel(option.value)}${option.textContent.match(/ \(.+\)$/)?.[0] || ''}`; });
        populateSelect(topicSelect, data.facets?.topics || [], 'topic', 'topic');
        populateSelect(sourceSelect, data.facets?.sources || [], 'source_key', 'source_key');
        populateSelect(yearFrom, data.facets?.years || [], 'year', 'year');
        populateSelect(yearTo, data.facets?.years || [], 'year', 'year');
        syncControls();
        renderTopics(data.facets?.topics || []);
        renderFeatured(data.featured || data.recent || []);
        setStateMessage(data.transport === 'python' ? 'Python research index online · progressive discovery active' : 'Local catalog fallback active', data.transport === 'python' ? '' : 'fallback');
        const url = new URL(window.location.href);
        if (state.q || state.topic || state.object_type || state.source_key || state.year_from || state.year_to) await runSearch({ push: false });
        const recordId = url.searchParams.get('library_record');
        if (recordId) await openDrawer(recordId, null, false);
      } catch (error) {
        setStateMessage(cfg.strings?.error || 'Dynamic discovery is temporarily unavailable.', 'error');
        if (featured) featured.innerHTML = `<div class="sc-library-explorer__error"><strong>${esc(cfg.strings?.error || 'Dynamic discovery is unavailable.')}</strong><a href="${esc(cfg.legacyUrl || '#')}">${esc(cfg.strings?.fallback || 'Use the local Library catalog')} →</a></div>`;
      }
    };

    boot();
  });
})();
