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

  document.querySelectorAll('[data-sc-field-spotlights="v4.3.5"] .sc-field-spotlight').forEach((spotlight) => {
    const dataNode = q(spotlight, '.sc-field-spotlight__data');
    let payload;
    try { payload = JSON.parse(dataNode?.textContent || '{}'); } catch { return; }
    const field = payload.field || {};
    const labels = payload.labels || {};
    const panels = Array.isArray(field.panels) ? field.panels : [];
    if (!panels.length) return;
    const tabs = qa(spotlight, '[data-panel-key]');
    const hero = q(spotlight, '.sc-field-spotlight__hero');
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
    let active = 0;

    const panelIndexByKey = (key) => panels.findIndex((panel) => panel.key === key);

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
      active = (next + panels.length) % panels.length;
      const panel = panels[active];
      tabs.forEach((tab) => { const selected = tab.dataset.panelKey === panel.key; tab.classList.toggle('is-active', selected); tab.setAttribute('aria-selected', selected ? 'true' : 'false'); });
      if (panel.disclosure === 'additional' && additional?.hidden) {
        additional.hidden = false;
        if (more) { more.setAttribute('aria-expanded', 'true'); q(more, '.sc-field-spotlight__more-icon').textContent = '−'; q(more, '[data-more-label]').textContent = labels.hide_additional_label || 'Hide additional fields'; }
      }
      const h = panel.hero || {};
      if (heroMedia) { heroMedia.href = h.url || '#'; media(heroMedia, h.thumbnail, false); }
      if (heroLabel) heroLabel.textContent = `${labels.hero_label || 'Article Map'} · HERO`;
      if (heroTitle) heroTitle.textContent = esc(h.title || panel.title);
      if (heroMeta) { heroMeta.textContent = esc(h.metadata || 'Article Map'); heroMeta.hidden = !heroMeta.textContent; }
      if (heroDescription) heroDescription.textContent = esc(h.description || 'Use the Article Map to move through the complete series, its structure, and related research pathways.');
      if (heroAction) { heroAction.href = h.url || '#'; heroAction.firstChild.textContent = `${esc(h.cta || labels.hero_cta || 'Explore Article Map')} `; }
      if (breadcrumb) breadcrumb.textContent = `${field.title || ''}${panel.source_group ? ` / ${panel.source_group}` : ''}`;
      if (position) position.innerHTML = `PANEL <strong>${two(active + 1)}</strong> / ${two(panels.length)}`;
      if (index) { index.replaceChildren(); const strong = document.createElement('strong'); strong.textContent = esc(panel.title); const span = document.createElement('span'); span.textContent = `${two(active + 1)} / ${two(panels.length)}`; index.append(strong, span); }
      renderCards(panel);
      if (focusTab) tabs.find((tab) => tab.dataset.panelKey === panel.key)?.focus();
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => { const i = panelIndexByKey(tab.dataset.panelKey); if (i >= 0) activate(i); });
      tab.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') { event.preventDefault(); activate(active + 1, true); }
        else if (event.key === 'ArrowLeft') { event.preventDefault(); activate(active - 1, true); }
        else if (event.key === 'Home') { event.preventDefault(); activate(0, true); }
        else if (event.key === 'End') { event.preventDefault(); activate(panels.length - 1, true); }
      });
    });
    qa(spotlight, '[data-panel-prev]').forEach((button) => button.addEventListener('click', () => activate(active - 1)));
    qa(spotlight, '[data-panel-next]').forEach((button) => button.addEventListener('click', () => activate(active + 1)));
    more?.addEventListener('click', () => {
      const open = more.getAttribute('aria-expanded') === 'true';
      more.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (additional) additional.hidden = open;
      q(more, '.sc-field-spotlight__more-icon').textContent = open ? '+' : '−';
      q(more, '[data-more-label]').textContent = open ? (labels.additional_label || 'Explore additional fields') : (labels.hide_additional_label || 'Hide additional fields');
    });
    activate(0);
  });
})();
