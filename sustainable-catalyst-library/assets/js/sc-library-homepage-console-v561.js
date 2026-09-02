(() => {
  'use strict';
  const cfg = window.SCLibraryHomepageConsoleV561 || {};
  const roots = Array.from(document.querySelectorAll('[data-sc-library-home-console]'));
  if (!roots.length) return;

  const fmt = (value) => Number.isFinite(Number(value)) ? Number(value).toLocaleString() : '—';
  const setMetric = (root, key, value) => {
    const node = root.querySelector(`[data-sc-home-metric="${key}"]`);
    if (node) node.textContent = value;
  };

  const loadMetrics = async (root) => {
    const live = root.querySelector('[data-sc-home-live-state]');
    if (!cfg.bootstrapUrl) return;
    try {
      const response = await fetch(cfg.bootstrapUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      setMetric(root, 'records', fmt(data?.stats?.public_records ?? data?.stats?.records));
      setMetric(root, 'topics', fmt(Array.isArray(data?.facets?.topics) ? data.facets.topics.length : null));
      setMetric(root, 'chunks', data?.stats?.chunks === null || data?.stats?.chunks === undefined ? 'LOCAL' : fmt(data.stats.chunks));
      if (live) {
        live.classList.toggle('is-live', data?.transport === 'python');
        const state = live.querySelector('small');
        if (state) state.textContent = data?.transport === 'python' ? (cfg.strings?.connected || 'Connected index') : (cfg.strings?.local || 'Local index');
      }
    } catch (_) {
      if (live) {
        live.classList.remove('is-live');
        const state = live.querySelector('small');
        if (state) state.textContent = cfg.strings?.unavailable || 'Live counts unavailable';
      }
    }
  };

  const startTicker = (root) => {
    const viewport = root.querySelector('[data-sc-home-network-viewport]');
    const rows = Array.from(root.querySelectorAll('[data-sc-home-network-row]'));
    if (!viewport || rows.length < 6 || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    let paused = false;
    const step = () => {
      if (paused || viewport.matches(':hover') || viewport.contains(document.activeElement)) return;
      const rowHeight = rows[0].getBoundingClientRect().height || 61;
      const max = viewport.scrollHeight - viewport.clientHeight;
      const target = viewport.scrollTop + rowHeight;
      viewport.scrollTo({ top: target >= max - 4 ? 0 : target, behavior: 'smooth' });
    };
    window.setInterval(step, 2600);
    viewport.addEventListener('mouseenter', () => { paused = true; });
    viewport.addEventListener('mouseleave', () => { paused = false; });
    viewport.addEventListener('focusin', () => { paused = true; });
    viewport.addEventListener('focusout', () => { paused = false; });
  };

  const bindSearch = (root) => {
    const form = root.querySelector('[data-sc-home-library-search]');
    if (!form || !cfg.libraryUrl) return;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const query = String(new FormData(form).get('query') || '').trim();
      const target = event.submitter?.dataset?.scHomeSearchTarget || 'knowledge';
      const url = new URL(cfg.libraryUrl, window.location.origin);
      if (target === 'research') {
        if (query) url.searchParams.set('research_query', query);
        url.hash = 'research-access';
      } else if (target === 'librarian') {
        if (query) url.searchParams.set('librarian_query', query);
        url.hash = 'research-front-door';
      } else {
        if (query) url.searchParams.set('library_q', query);
        url.hash = 'knowledge-explorer';
      }
      window.location.assign(url.toString());
    });
  };

  roots.forEach((root) => {
    loadMetrics(root);
    startTicker(root);
    bindSearch(root);
  });
})();
