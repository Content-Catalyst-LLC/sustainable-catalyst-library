(() => {
  'use strict';
  const q = (root, sel) => root.querySelector(sel);
  const qa = (root, sel) => Array.from(root.querySelectorAll(sel));
  const esc = (value) => String(value ?? '');
  const two = (n) => String(n).padStart(2, '0');

  const placeholder = (small = false) => {
    const span = document.createElement('span');
    span.className = `sc-field-spotlight__placeholder${small ? ' sc-field-spotlight__placeholder--small' : ''}`;
    const strong = document.createElement('strong'); strong.textContent = 'KL'; span.append(strong);
    if (!small) { const smallEl = document.createElement('small'); smallEl.textContent = 'ARTICLE MAP'; span.append(smallEl); }
    return span;
  };

  const media = (root, thumb, small = false) => {
    root.replaceChildren();
    if (thumb?.url) {
      const img = document.createElement('img'); img.src = thumb.url; img.alt = thumb.alt || ''; img.loading = small ? 'lazy' : 'eager';
      img.addEventListener('error', () => root.replaceChildren(placeholder(small)), { once: true });
      root.append(img);
    } else root.append(placeholder(small));
  };

  const initialize = (spotlight) => {
    const dataNode = q(spotlight, '.sc-field-spotlight__data');
    let payload;
    try { payload = JSON.parse(dataNode?.textContent || '{}'); } catch { return; }
    const field = payload.field || {};
    const labels = payload.labels || {};
    const panels = Array.isArray(field.panels) ? field.panels : [];
    if (!panels.length) return;

    const tabs = qa(spotlight, '[data-panel-key]');
    const heroMedia = q(spotlight, '.sc-field-spotlight__hero-media');
    const heroLabel = q(spotlight, '.sc-field-spotlight__hero-label');
    const heroTitle = q(spotlight, '.sc-field-spotlight__hero h3');
    const heroMeta = q(spotlight, '.sc-field-spotlight__hero-meta');
    const heroDescription = q(spotlight, '.sc-field-spotlight__hero-description');
    const heroAction = q(spotlight, '.sc-field-spotlight__hero-action');
    const breadcrumb = q(spotlight, '.sc-field-spotlight__breadcrumb');
    const position = q(spotlight, '.sc-field-spotlight__position');
    const cards = q(spotlight, '[data-supporting-cards]');
    const empty = q(spotlight, '[data-empty-state]');
    const slotCount = q(spotlight, '[data-slot-count]');
    const index = q(spotlight, '[data-panel-index]');
    const more = q(spotlight, '[data-more-toggle]');
    const additional = q(spotlight, '[data-additional-tabs]');
    const status = q(spotlight, '[data-playback-status]');
    const progress = q(spotlight, '[data-panel-progress]');
    const toggle = q(spotlight, '[data-panel-toggle]');
    const toggleIcon = q(spotlight, '[data-panel-toggle-icon]');
    const toggleText = q(spotlight, '[data-panel-toggle-text]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Math.max(8000, Number.parseInt(spotlight.dataset.interval || '14000', 10) || 14000);
    const pauseOnHover = spotlight.dataset.pauseOnHover !== 'false';
    let active = 0;
    let userPaused = spotlight.dataset.autoplay !== 'true';
    let interactionPaused = false;
    let secondaryExpanded = Boolean(additional) && spotlight.dataset.secondaryOpen === 'true';
    let timer = null;
    let touchStartX = null;

    const label = (name, fallback) => spotlight.dataset[name] || fallback;
    const panelIndexByKey = (key) => panels.findIndex((panel) => panel.key === key);
    const panelTier = (panel) => panel?.disclosure === 'additional' ? 'additional' : 'primary';
    const navigableIndexes = () => {
      const all = panels.map((panel, idx) => ({ panel, idx }));
      if (!additional || secondaryExpanded) return all.map(({idx}) => idx);
      const primary = all.filter(({panel}) => panelTier(panel) === 'primary').map(({idx}) => idx);
      return primary.length ? primary : all.map(({idx}) => idx);
    };
    const adjacentIndex = (direction) => {
      const indexes = navigableIndexes();
      if (!indexes.length) return 0;
      let pos = indexes.indexOf(active);
      if (pos < 0) pos = 0;
      return indexes[(pos + direction + indexes.length) % indexes.length];
    };
    const playbackState = () => {
      if (navigableIndexes().length < 2) return 'static';
      if (prefersReducedMotion.matches) return 'reduced';
      if (userPaused) return 'paused';
      if (interactionPaused || document.hidden) return 'hold';
      return 'auto';
    };
    const updatePlaybackState = () => {
      const state = playbackState();
      spotlight.dataset.playbackState = state;
      if (status) {
        const names = {auto:label('statusAuto','Auto'),paused:label('statusPaused','Paused'),hold:label('statusHold','Hold'),static:label('statusStatic','Static'),reduced:label('statusReduced','Reduced motion')};
        status.textContent = (names[state] || names.paused).toUpperCase();
      }
      return state;
    };
    const updateToggle = () => {
      const paused = userPaused || prefersReducedMotion.matches;
      if (toggle) {
        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggle.setAttribute('aria-label', paused ? label('labelPlay','Play automatic rotation') : label('labelPause','Pause automatic rotation'));
        toggle.disabled = prefersReducedMotion.matches;
        if (prefersReducedMotion.matches) toggle.title = 'Automatic rotation is disabled by your reduced-motion preference.';
        else toggle.removeAttribute('title');
      }
      if (toggleIcon) toggleIcon.textContent = paused ? '▶' : 'Ⅱ';
      if (toggleText) toggleText.textContent = paused ? 'Play' : 'Pause';
      updatePlaybackState();
    };
    const clearTimer = () => { window.clearTimeout(timer); timer = null; };
    const restartProgress = () => {
      if (!progress) return;
      progress.classList.remove('is-running');
      void progress.offsetWidth;
      if (playbackState() === 'auto') progress.classList.add('is-running');
    };
    const schedule = () => {
      clearTimer();
      const state = updatePlaybackState();
      restartProgress();
      if (state !== 'auto') return;
      timer = window.setTimeout(() => activate(adjacentIndex(1), false), interval);
    };
    const updateAdditional = () => {
      if (!additional || !more) return;
      additional.hidden = !secondaryExpanded;
      additional.setAttribute('aria-hidden', secondaryExpanded ? 'false' : 'true');
      more.setAttribute('aria-expanded', secondaryExpanded ? 'true' : 'false');
      q(more, '.sc-field-spotlight__more-icon').textContent = secondaryExpanded ? '−' : '+';
      q(more, '[data-more-label]').textContent = secondaryExpanded ? (labels.hide_additional_label || 'Hide additional fields') : (labels.additional_label || 'Explore additional fields');
      spotlight.dataset.secondaryExpanded = secondaryExpanded ? 'true' : 'false';
    };

    const renderCards = (panel) => {
      cards?.replaceChildren();
      const articles = Array.isArray(panel.articles) ? panel.articles : [];
      if (slotCount) slotCount.textContent = `${panel.slot_count || 0} SLOTS`;
      if (empty) empty.hidden = articles.length > 0;
      articles.forEach((article, i) => {
        const card = document.createElement('article'); card.className = 'sc-field-spotlight__card';
        const mediaLink = document.createElement('a'); mediaLink.className = 'sc-field-spotlight__card-media'; mediaLink.href = article.url || '#'; media(mediaLink, article.thumbnail, true);
        const copy = document.createElement('div'); copy.className = 'sc-field-spotlight__card-copy';
        const num = document.createElement('p'); num.className = 'sc-field-spotlight__card-number'; num.textContent = `SELECTED ARTICLE ${two(i + 1)}`;
        const h5 = document.createElement('h5'); const a = document.createElement('a'); a.href = article.url || '#'; a.textContent = esc(article.title); h5.append(a);
        copy.append(num, h5);
        if (article.metadata) { const meta = document.createElement('p'); meta.className = 'sc-field-spotlight__card-meta'; meta.textContent = esc(article.metadata); copy.append(meta); }
        if (article.summary) { const sum = document.createElement('p'); sum.className = 'sc-field-spotlight__card-summary'; sum.textContent = esc(article.summary); copy.append(sum); }
        card.append(mediaLink, copy); cards?.append(card);
      });
    };

    const activate = (next, focusTab = false) => {
      active = Math.max(0, Math.min(panels.length - 1, next));
      const panel = panels[active];
      tabs.forEach((tab) => { const selected = tab.dataset.panelKey === panel.key; tab.classList.toggle('is-active', selected); tab.setAttribute('aria-selected', selected ? 'true' : 'false'); tab.tabIndex = selected ? 0 : -1; });
      const h = panel.hero || {};
      if (heroMedia) { heroMedia.href = h.url || '#'; media(heroMedia, h.thumbnail, false); }
      if (heroLabel) heroLabel.textContent = `${labels.hero_label || 'Article Map'}`;
      if (heroTitle) heroTitle.textContent = esc(h.title || panel.title);
      if (heroMeta) { heroMeta.textContent = esc(h.metadata || 'Article Map'); heroMeta.hidden = !heroMeta.textContent; }
      if (heroDescription) heroDescription.textContent = esc(h.description || 'Use the Article Map to move through the complete series, its structure, and related research pathways.');
      if (heroAction) { heroAction.href = h.url || '#'; heroAction.firstChild.textContent = `${esc(h.cta || labels.hero_cta || 'Explore Article Map')} `; }
      if (breadcrumb) breadcrumb.textContent = `${field.title || ''}${panel.source_group ? ` / ${panel.source_group}` : ''}`;
      if (position) position.innerHTML = `PANEL <strong>${two(active + 1)}</strong> / ${two(panels.length)}`;
      if (index) { index.replaceChildren(); const strong = document.createElement('strong'); strong.textContent = esc(panel.title); const span = document.createElement('span'); span.textContent = `${two(active + 1)} / ${two(panels.length)}`; index.append(strong, span); }
      renderCards(panel);
      if (focusTab) tabs.find((tab) => tab.dataset.panelKey === panel.key)?.focus({preventScroll:true});
      schedule();
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => { const i = panelIndexByKey(tab.dataset.panelKey); if (i >= 0) activate(i); });
      tab.addEventListener('keydown', (event) => {
        if (!['ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
        event.preventDefault();
        const tablist = tab.closest('[role="tablist"]');
        const list = tablist ? Array.from(tablist.querySelectorAll('[data-panel-key]')) : tabs;
        const pos = Math.max(0, list.indexOf(tab));
        let requested = pos;
        if (event.key === 'ArrowRight') requested = pos + 1;
        if (event.key === 'ArrowLeft') requested = pos - 1;
        if (event.key === 'Home') requested = 0;
        if (event.key === 'End') requested = list.length - 1;
        requested = (requested + list.length) % list.length;
        const nextTab = list[requested];
        const i = panelIndexByKey(nextTab?.dataset.panelKey);
        if (i >= 0) activate(i, true);
      });
    });
    qa(spotlight, '[data-panel-prev]').forEach((button) => button.addEventListener('click', () => activate(adjacentIndex(-1))));
    qa(spotlight, '[data-panel-next]').forEach((button) => button.addEventListener('click', () => activate(adjacentIndex(1))));
    toggle?.addEventListener('click', () => {
      if (prefersReducedMotion.matches) return;
      userPaused = !userPaused;
      updateToggle();
      schedule();
    });
    more?.addEventListener('click', () => {
      secondaryExpanded = !secondaryExpanded;
      updateAdditional();
      if (!secondaryExpanded && panelTier(panels[active]) === 'additional') {
        const firstPrimary = panels.findIndex((panel) => panelTier(panel) === 'primary');
        activate(Math.max(0, firstPrimary));
      } else schedule();
    });

    if (pauseOnHover) {
      spotlight.addEventListener('mouseenter', () => { interactionPaused = true; clearTimer(); updatePlaybackState(); restartProgress(); });
      spotlight.addEventListener('mouseleave', () => { interactionPaused = false; schedule(); });
    }
    spotlight.addEventListener('focusin', () => { interactionPaused = true; clearTimer(); updatePlaybackState(); restartProgress(); });
    spotlight.addEventListener('focusout', (event) => { if (!spotlight.contains(event.relatedTarget)) { interactionPaused = false; schedule(); } });
    document.addEventListener('visibilitychange', schedule);
    spotlight.addEventListener('touchstart', (event) => { interactionPaused = true; clearTimer(); updatePlaybackState(); restartProgress(); touchStartX = event.changedTouches[0]?.clientX ?? null; }, {passive:true});
    spotlight.addEventListener('touchend', (event) => {
      const endX = event.changedTouches[0]?.clientX ?? touchStartX;
      const delta = touchStartX === null ? 0 : endX - touchStartX;
      touchStartX = null; interactionPaused = false;
      if (Math.abs(delta) >= 50) activate(delta > 0 ? adjacentIndex(-1) : adjacentIndex(1));
      else schedule();
    }, {passive:true});
    prefersReducedMotion.addEventListener?.('change', () => { updateToggle(); schedule(); });

    updateAdditional();
    updateToggle();
    activate(0);
  };

  const boot = () => document.querySelectorAll('[data-sc-field-spotlights="v4.3.10"] .sc-field-spotlight').forEach(initialize);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
