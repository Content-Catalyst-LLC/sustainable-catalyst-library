/* SC-HOME-PUB-GRAPH-02: controls the existing Research Library renderer; no second graph engine. */
(() => {
  'use strict';
  const MODES = [
    'knowledge-landscape',
    'knowledge-terrain-4d',
    'topic-graph',
    'topic-regions',
    'temporal-dynamics',
    'relationship-matrix'
  ];
  const PERIOD = 12000;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const roots = document.querySelectorAll('[data-sc-kl-showcase="1"]');

  roots.forEach(root => {
    const nav = MODES.map(mode => root.querySelector('[data-sc-kl-view="' + mode + '"]')).filter(Boolean);
    const prev = root.querySelector('[data-sc-kl-showcase-prev]');
    const next = root.querySelector('[data-sc-kl-showcase-next]');
    const toggle = root.querySelector('[data-sc-kl-showcase-pause]');
    const search = root.querySelector('[data-sc-kl-showcase-query]');
    const clear = root.querySelector('[data-sc-kl-showcase-clear]');
    const year = root.querySelector('[data-sc-kl-showcase-year]');
    const yearLabel = root.querySelector('.sc-kl__showcase-year-label');
    const sourceQuery = root.querySelector('[data-sc-kl-query-text]');
    const sourceTime = root.querySelector('[data-sc-kl-time]');
    let ready = false;
    let userPaused = reduced.matches;
    let timer = null;
    let automaticChange = false;

    const current = () => Math.max(0, nav.findIndex(button => button.classList.contains('is-active')));
    const syncYear = () => {
      if (!sourceTime || !year) return;
      const active = nav[current()]?.dataset.scKlView === 'knowledge-terrain-4d';
      year.hidden = !active;
      if (yearLabel) yearLabel.hidden = !active;
      year.min = sourceTime.min;
      year.max = sourceTime.max;
      year.value = sourceTime.value;
      year.disabled = Number(year.max) <= Number(year.min);
    };
    const sync = () => {
      if (toggle) {
        toggle.textContent = reduced.matches ? 'Rotation off' : (userPaused ? 'Resume rotation' : 'Pause rotation');
        toggle.disabled = reduced.matches;
        toggle.setAttribute('aria-pressed', String(userPaused || reduced.matches));
      }
      nav.forEach(button => button.setAttribute('aria-current', button.classList.contains('is-active') ? 'true' : 'false'));
      syncYear();
    };
    const step = delta => {
      if (!ready || !nav.length) return;
      const index = (current() + delta + nav.length) % nav.length;
      if (!nav[index].disabled) {
        automaticChange = true;
        try { nav[index].click(); } finally { automaticChange = false; }
      }
      sync();
    };
    const startTimer = () => {
      if (timer !== null) window.clearInterval(timer);
      timer = window.setInterval(() => {
        if (!ready || userPaused || reduced.matches || document.hidden || root.matches(':hover') || root.contains(document.activeElement)) return;
        step(1);
      }, PERIOD);
    };
    const interact = () => {
      userPaused = true;
      sync();
    };
    prev?.addEventListener('click', () => { interact(); step(-1); });
    next?.addEventListener('click', () => { interact(); step(1); });
    toggle?.addEventListener('click', () => {
      userPaused = !userPaused;
      sync();
    });
    nav.forEach(button => button.addEventListener('click', () => {
      if (ready && !automaticChange) { interact(); sync(); }
    }));
    search?.addEventListener('input', () => {
      interact();
      if (sourceQuery) {
        sourceQuery.value = search.value;
        sourceQuery.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
    clear?.addEventListener('click', () => {
      interact();
      if (search) search.value = '';
      root.querySelector('[data-sc-kl-query-clear]')?.click();
    });
    year?.addEventListener('input', () => {
      interact();
      if (sourceTime) {
        sourceTime.value = year.value;
        sourceTime.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
    sourceTime?.addEventListener('input', syncYear);
    root.querySelectorAll('[data-sc-kl-zoom],[data-sc-kl-fit]').forEach(button => button.addEventListener('click', interact));
    root.querySelector('.sc-kl__stage')?.addEventListener('pointerdown', interact);
    const markReady = () => {
      if (ready || root.dataset.scKlReady !== '1') return;
      ready = true;
      nav.forEach(button => { button.disabled = false; });
      sync();
      startTimer();
      observer.disconnect();
    };
    const observer = new MutationObserver(markReady);
    observer.observe(root, { attributes: true, attributeFilter: ['data-sc-kl-ready'] });
    if (root.dataset.scKlReady === '1') markReady();
    window.addEventListener('pagehide', () => { if (timer !== null) window.clearInterval(timer); observer.disconnect(); }, { once: true });
    reduced.addEventListener?.('change', () => {
      if (reduced.matches) userPaused = true;
      sync();
    });
  });
})();
