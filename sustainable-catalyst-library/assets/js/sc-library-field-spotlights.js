(() => {
  'use strict';

  const q = (root, sel) => root.querySelector(sel);
  const qa = (root, sel) => Array.from(root.querySelectorAll(sel));
  const esc = (value) => String(value ?? '');
  const two = (n) => String(n).padStart(2, '0');
  const markRuntime = (root, state, detail = '') => {
    if (!root) return;
    root.dataset.scFieldSpotlightsRuntimeState = state;
    if (detail) root.dataset.scFieldSpotlightsRuntimeDetail = detail;
    else delete root.dataset.scFieldSpotlightsRuntimeDetail;
  };
  const runtimeFailure = (root, code, error = null) => {
    markRuntime(root, 'fallback', code);
    try { root?.dispatchEvent(new CustomEvent('sc:fieldspotlights:runtimefailure', { bubbles: true, detail: { code, message: error?.message || '' } })); } catch {}
    console?.warn?.('[Sustainable Catalyst Field Spotlight] fail-open navigation:', code, error || '');
  };
  const plainPrimaryClick = (event) => !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
  const absoluteUrl = (value) => {
    try { return new URL(String(value || '/'), window.location.origin).href; }
    catch { return String(value || '#'); }
  };

  const placeholder = (small = false) => {
    const span = document.createElement('span');
    span.className = `sc-field-spotlight__placeholder${small ? ' sc-field-spotlight__placeholder--small' : ''}`;
    const strong = document.createElement('strong'); strong.textContent = 'KL'; span.append(strong);
    if (!small) {
      const smallEl = document.createElement('small');
      smallEl.textContent = 'ARTICLE MAP';
      span.append(smallEl);
    }
    return span;
  };

  const media = (root, thumb, small = false) => {
    if (!root) return;
    root.replaceChildren();
    if (thumb?.url) {
      const img = document.createElement('img');
      img.src = thumb.url;
      img.alt = thumb.alt || '';
      img.loading = small ? 'lazy' : 'eager';
      img.addEventListener('error', () => root.replaceChildren(placeholder(small)), { once: true });
      root.append(img);
    } else {
      root.append(placeholder(small));
    }
  };

  const fallbackUrl = (fieldKey, panelKey = '', hash = '') => {
    try {
      const url = new URL(window.location.href);
      if (fieldKey) url.searchParams.set('sc_publication_field', fieldKey); else url.searchParams.delete('sc_publication_field');
      if (panelKey) url.searchParams.set('sc_publication_panel', panelKey); else url.searchParams.delete('sc_publication_panel');
      if (hash) url.hash = hash;
      return url.href;
    } catch { return '#'; }
  };

  const createPanelTab = (panel, index, selected = false, fieldKey = '', stageId = '') => {
    const button = document.createElement('a');
    button.href = fallbackUrl(fieldKey, panel.key || '', stageId ? `#${stageId}` : '');
    button.setAttribute('role', 'tab');
    button.className = `sc-field-spotlight__tab${selected ? ' is-active' : ''}`;
    button.dataset.panelKey = panel.key || '';
    button.setAttribute('aria-selected', selected ? 'true' : 'false');
    button.tabIndex = selected ? 0 : -1;

    const number = document.createElement('span');
    number.textContent = two(index + 1);
    const title = document.createElement('strong');
    title.textContent = esc(panel.title);
    button.append(number, title);
    return button;
  };

  const initializeStage = (spotlight, initialField, labels = {}, options = {}) => {
    if (!spotlight || !initialField) return null;

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
    const moreLabel = q(spotlight, '[data-more-label]');
    const moreCount = q(spotlight, '[data-more-count]');
    const additional = q(spotlight, '[data-additional-tabs]');
    const primaryTabs = q(spotlight, '[data-primary-tabs]');
    const panelNav = q(spotlight, '[data-panel-nav]');
    const status = q(spotlight, '[data-playback-status]');
    const progress = q(spotlight, '[data-panel-progress]');
    const toggle = q(spotlight, '[data-panel-toggle]');
    const toggleIcon = q(spotlight, '[data-panel-toggle-icon]');
    const toggleText = q(spotlight, '[data-panel-toggle-text]');
    const fieldEyebrow = q(spotlight, '[data-field-eyebrow]');
    const fieldTitle = q(spotlight, '[data-field-title]');
    const fieldDescription = q(spotlight, '[data-field-description]');
    const fieldPanelCount = q(spotlight, '[data-field-panel-count]');
    const fieldBrowse = q(spotlight, '[data-field-browse]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const configuredInterval = options.interval ?? spotlight.dataset.interval ?? '14000';
    const interval = Math.max(8000, Number.parseInt(configuredInterval, 10) || 14000);
    const pauseOnHover = String(options.pauseOnHover ?? spotlight.dataset.pauseOnHover ?? 'true') !== 'false';
    const autoplay = String(options.autoplay ?? spotlight.dataset.autoplay ?? 'true') === 'true';

    let field = initialField;
    let fieldNumber = Number.parseInt(options.fieldNumber || '1', 10) || 1;
    let panels = Array.isArray(field.panels) ? field.panels : [];
    const requestedPanelKey = String(options.initialPanelKey ?? spotlight.dataset.initialPanelKey ?? '');
    let active = Math.max(0, panels.findIndex((panel) => panel.key === requestedPanelKey));
    let userPaused = !autoplay;
    let interactionPaused = false;
    let secondaryExpanded = active >= 8;
    let timer = null;
    let touchStartX = null;

    const label = (name, fallback) => spotlight.dataset[name] || fallback;
    const panelTabs = () => qa(spotlight, '[data-panel-key]');
    const panelIndexByKey = (key) => panels.findIndex((panel) => panel.key === key);
    const navigableIndexes = () => {
      const all = panels.map((panel, idx) => ({ panel, idx }));
      if (!additional || secondaryExpanded || panels.length <= 8) return all.map(({ idx }) => idx);
      return all.slice(0, 8).map(({ idx }) => idx);
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
        const names = {
          auto: label('statusAuto', 'Auto'),
          paused: label('statusPaused', 'Paused'),
          hold: label('statusHold', 'Hold'),
          static: label('statusStatic', 'Static'),
          reduced: label('statusReduced', 'Reduced motion')
        };
        status.textContent = (names[state] || names.paused).toUpperCase();
      }
      return state;
    };
    const updateToggle = () => {
      const paused = userPaused || prefersReducedMotion.matches;
      if (toggle) {
        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggle.setAttribute('aria-label', paused ? label('labelPlay', 'Play automatic rotation') : label('labelPause', 'Pause automatic rotation'));
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
      const extraCount = Math.max(0, panels.length - 8);
      more.hidden = extraCount === 0;
      additional.hidden = extraCount === 0 || !secondaryExpanded;
      additional.setAttribute('aria-hidden', extraCount > 0 && secondaryExpanded ? 'false' : 'true');
      more.setAttribute('aria-expanded', extraCount > 0 && secondaryExpanded ? 'true' : 'false');
      const icon = q(more, '.sc-field-spotlight__more-icon');
      if (icon) icon.textContent = secondaryExpanded ? '−' : '+';
      if (moreLabel) moreLabel.textContent = secondaryExpanded ? (labels.hide_additional_label || 'Hide additional fields') : (labels.additional_label || 'Explore additional fields');
      if (moreCount) moreCount.textContent = String(extraCount);
      spotlight.dataset.secondaryExpanded = secondaryExpanded ? 'true' : 'false';
    };

    const buildPanelNavigation = () => {
      if (!primaryTabs || !additional) return;
      primaryTabs.replaceChildren();
      additional.replaceChildren();
      panels.forEach((panel, idx) => {
        const button = createPanelTab(panel, idx, idx === active, field.key || '', spotlight.id || '');
        if (idx < 8) primaryTabs.append(button);
        else additional.append(button);
      });
      if (panelNav) panelNav.setAttribute('aria-label', `${field.title || 'Field'} series panels`);
      updateAdditional();
    };

    const renderCards = (panel) => {
      cards?.replaceChildren();
      const articles = Array.isArray(panel.articles) ? panel.articles : [];
      if (slotCount) slotCount.textContent = `${panel.slot_count || 0} SLOTS`;
      if (empty) empty.hidden = articles.length > 0;
      articles.forEach((article, i) => {
        const card = document.createElement('article');
        card.className = 'sc-field-spotlight__card';
        const mediaLink = document.createElement('a');
        mediaLink.className = 'sc-field-spotlight__card-media';
        mediaLink.href = article.url || '#';
        media(mediaLink, article.thumbnail, true);
        const copy = document.createElement('div');
        copy.className = 'sc-field-spotlight__card-copy';
        const num = document.createElement('p');
        num.className = 'sc-field-spotlight__card-number';
        num.textContent = `SELECTED ARTICLE ${two(i + 1)}`;
        const h5 = document.createElement('h5');
        const a = document.createElement('a');
        a.href = article.url || '#';
        a.textContent = esc(article.title);
        h5.append(a);
        copy.append(num, h5);
        if (article.metadata) {
          const meta = document.createElement('p');
          meta.className = 'sc-field-spotlight__card-meta';
          meta.textContent = esc(article.metadata);
          copy.append(meta);
        }
        if (article.summary) {
          const sum = document.createElement('p');
          sum.className = 'sc-field-spotlight__card-summary';
          sum.textContent = esc(article.summary);
          copy.append(sum);
        }
        card.append(mediaLink, copy);
        cards?.append(card);
      });
    };

    const activate = (next, focusTab = false) => {
      if (!panels.length) return false;
      active = Math.max(0, Math.min(panels.length - 1, next));
      const panel = panels[active];
      panelTabs().forEach((tab) => {
        const selected = tab.dataset.panelKey === panel.key;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.tabIndex = selected ? 0 : -1;
      });
      const h = panel.hero || {};
      if (heroMedia) { heroMedia.href = h.url || '#'; media(heroMedia, h.thumbnail, false); }
      if (heroLabel) heroLabel.textContent = `${labels.hero_label || 'Article Map'}`;
      if (heroTitle) heroTitle.textContent = esc(h.title || panel.title);
      if (heroMeta) {
        heroMeta.textContent = esc(h.metadata || 'Article Map');
        heroMeta.hidden = !heroMeta.textContent;
      }
      if (heroDescription) heroDescription.textContent = esc(h.description || 'Use the Article Map to move through the complete series, its structure, and related research pathways.');
      if (heroAction) {
        heroAction.href = h.url || '#';
        const textNode = heroAction.firstChild;
        if (textNode) textNode.textContent = `${esc(h.cta || labels.hero_cta || 'Explore Article Map')} `;
      }
      if (breadcrumb) breadcrumb.textContent = `${field.title || ''}${panel.source_group ? ` / ${panel.source_group}` : ''}`;
      if (position) position.innerHTML = `PANEL <strong>${two(active + 1)}</strong> / ${two(panels.length)}`;
      if (index) {
        index.replaceChildren();
        const strong = document.createElement('strong'); strong.textContent = esc(panel.title);
        const span = document.createElement('span'); span.textContent = `${two(active + 1)} / ${two(panels.length)}`;
        index.append(strong, span);
      }
      renderCards(panel);
      if (focusTab) panelTabs().find((tab) => tab.dataset.panelKey === panel.key)?.focus({ preventScroll: true });
      schedule();
      spotlight.dataset.activePanelKey = panel.key || '';
      return spotlight.dataset.activePanelKey === (panel.key || '');
    };

    const updateFieldIdentity = () => {
      spotlight.dataset.fieldKey = field.key || '';
      if (fieldEyebrow) fieldEyebrow.innerHTML = `<span aria-hidden="true">KL</span> KNOWLEDGE LIBRARY · FIELD ${two(fieldNumber)}`;
      if (fieldTitle) fieldTitle.textContent = esc(field.title);
      if (fieldDescription) {
        fieldDescription.textContent = esc(field.description);
        fieldDescription.hidden = !fieldDescription.textContent;
      }
      if (fieldPanelCount) fieldPanelCount.textContent = `${panels.length} PANELS`;
      if (fieldBrowse) fieldBrowse.href = absoluteUrl(field.browse_url || '/library/');
    };

    const setField = (nextField, nextFieldNumber = 1) => {
      if (!nextField || !Array.isArray(nextField.panels) || !nextField.panels.length) return false;
      clearTimer();
      field = nextField;
      fieldNumber = nextFieldNumber;
      panels = nextField.panels;
      active = 0;
      secondaryExpanded = false;
      updateFieldIdentity();
      buildPanelNavigation();
      if (!activate(0)) return false;
      spotlight.dispatchEvent(new CustomEvent('sc:fieldspotlight:fieldchange', { bubbles: true, detail: { fieldKey: field.key || '', fieldNumber } }));
      return spotlight.dataset.fieldKey === (field.key || '');
    };

    panelNav?.addEventListener('click', (event) => {
      const tab = event.target.closest('[data-panel-key]');
      if (tab && panelNav.contains(tab)) {
        // v4.3.22.3: direct panel tabs are server-authoritative links.
        // Never suppress their native navigation. Playback arrows may still rotate within the loaded field.
        return;
      }
      const disclosure = event.target.closest('[data-more-toggle]');
      if (disclosure && panelNav.contains(disclosure)) {
        secondaryExpanded = !secondaryExpanded;
        updateAdditional();
        if (!secondaryExpanded && active >= 8) activate(0);
        else schedule();
      }
    });

    panelNav?.addEventListener('keydown', (event) => {
      const tab = event.target.closest('[data-panel-key]');
      if (!tab || !['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const tablist = tab.closest('[role="tablist"]');
      const list = tablist ? Array.from(tablist.querySelectorAll('[data-panel-key]')) : panelTabs();
      const pos = Math.max(0, list.indexOf(tab));
      let requested = pos;
      if (event.key === 'ArrowRight') requested = pos + 1;
      if (event.key === 'ArrowLeft') requested = pos - 1;
      if (event.key === 'Home') requested = 0;
      if (event.key === 'End') requested = list.length - 1;
      requested = (requested + list.length) % list.length;
      const nextTab = list[requested];
      nextTab?.focus({ preventScroll: true });
    });

    qa(spotlight, '[data-panel-prev]').forEach((button) => button.addEventListener('click', () => activate(adjacentIndex(-1))));
    qa(spotlight, '[data-panel-next]').forEach((button) => button.addEventListener('click', () => activate(adjacentIndex(1))));
    toggle?.addEventListener('click', () => {
      if (prefersReducedMotion.matches) return;
      userPaused = !userPaused;
      updateToggle();
      schedule();
    });

    if (pauseOnHover) {
      spotlight.addEventListener('mouseenter', () => { interactionPaused = true; clearTimer(); updatePlaybackState(); restartProgress(); });
      spotlight.addEventListener('mouseleave', () => { interactionPaused = false; schedule(); });
    }
    spotlight.addEventListener('focusin', () => { interactionPaused = true; clearTimer(); updatePlaybackState(); restartProgress(); });
    spotlight.addEventListener('focusout', (event) => {
      if (!spotlight.contains(event.relatedTarget)) { interactionPaused = false; schedule(); }
    });
    document.addEventListener('visibilitychange', schedule);
    spotlight.addEventListener('touchstart', (event) => {
      interactionPaused = true;
      clearTimer();
      updatePlaybackState();
      restartProgress();
      touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    spotlight.addEventListener('touchend', (event) => {
      const endX = event.changedTouches[0]?.clientX ?? touchStartX;
      const delta = touchStartX === null ? 0 : endX - touchStartX;
      touchStartX = null;
      interactionPaused = false;
      if (Math.abs(delta) >= 50) activate(delta > 0 ? adjacentIndex(-1) : adjacentIndex(1));
      else schedule();
    }, { passive: true });
    prefersReducedMotion.addEventListener?.('change', () => { updateToggle(); schedule(); });

    updateFieldIdentity();
    buildPanelNavigation();
    updateToggle();
    activate(active);

    return { setField };
  };

  const initializeSingle = (root) => {
    markRuntime(root, 'initializing');
    const spotlight = q(root, '.sc-field-spotlight');
    const dataNode = q(root, '.sc-field-spotlight__data');
    let payload;
    try { payload = JSON.parse(dataNode?.textContent || '{}'); } catch { return; }
    const field = payload.field || {};
    if (!Array.isArray(field.panels) || !field.panels.length) return;
    const stage = initializeStage(spotlight, field, payload.labels || {}, {
      autoplay: spotlight.dataset.autoplay,
      interval: spotlight.dataset.interval,
      pauseOnHover: spotlight.dataset.pauseOnHover,
      fieldNumber: Number.parseInt(field.order || '1', 10) || 1
    });
    if (stage) markRuntime(root, 'ready'); else runtimeFailure(root, 'single-stage-init-failed');
  };

  const initializeMaster = (root) => {
    markRuntime(root, 'initializing');
    const dataNode = q(root, '.sc-field-spotlights__master-data');
    let payload;
    try { payload = JSON.parse(dataNode?.textContent || '{}'); } catch { return; }
    const fields = Array.isArray(payload.fields) ? payload.fields : [];
    if (!fields.length) return;
    const fieldIndexSafe = (items, key) => {
      const found = items.findIndex((field) => field.key === key);
      return found >= 0 ? found : 0;
    };

    const spotlight = q(root, '.sc-field-spotlight--master-stage');
    const fieldTabs = qa(root, '[data-field-select-key]');
    const fieldSelect = q(root, '[data-field-select]');
const requestedFieldKey = String(root.dataset.initialFieldKey || fields[0]?.key || '');
    let activeField = Math.max(0, fieldIndexSafe(fields, requestedFieldKey));

    const stage = initializeStage(spotlight, fields[activeField], payload.labels || {}, {
      autoplay: root.dataset.autoplay,
      interval: root.dataset.interval,
      pauseOnHover: root.dataset.pauseOnHover,
      fieldNumber: activeField + 1,
      initialPanelKey: spotlight?.dataset.initialPanelKey || ''
    });
    if (!stage) { runtimeFailure(root, 'master-stage-init-failed'); return; }

    const fieldIndexByKey = (key) => fields.findIndex((field) => field.key === key);
    const activateField = (next, focusTab = false) => {
      const requested = Math.max(0, Math.min(fields.length - 1, next));
      const field = fields[requested];
      if (!stage.setField(field, requested + 1)) return false;
      activeField = requested;
      fieldTabs.forEach((tab) => {
        const selected = tab.dataset.fieldSelectKey === field.key;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.tabIndex = selected ? 0 : -1;
      });
      if (fieldSelect) fieldSelect.value = field.key || '';
      root.dataset.activeField = field.key || '';
      if (focusTab) fieldTabs.find((tab) => tab.dataset.fieldSelectKey === field.key)?.focus({ preventScroll: true });
      return root.dataset.activeField === (field.key || '') && spotlight.dataset.fieldKey === (field.key || '');
    };

    fieldTabs.forEach((tab) => {
      // v4.3.22.3: do not install a click handler. The anchor href is authoritative.
      tab.addEventListener('keydown', (event) => {
        if (!['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const pos = Math.max(0, fieldTabs.indexOf(tab));
        let requested = pos;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') requested = pos + 1;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') requested = pos - 1;
        if (event.key === 'Home') requested = 0;
        if (event.key === 'End') requested = fieldTabs.length - 1;
        requested = (requested + fieldTabs.length) % fieldTabs.length;
        fieldTabs[requested]?.focus({ preventScroll: true });
      });
    });

    fieldSelect?.addEventListener('change', () => {
      const key = String(fieldSelect.value || '');
      if (!key) return;
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('sc_publication_field', key);
        url.searchParams.delete('sc_publication_panel');
        url.hash = spotlight?.id ? '#' + spotlight.id : '';
        window.location.assign(url.href);
      } catch (error) {
        runtimeFailure(root, 'field-select-navigation-exception', error);
      }
    });

    root.dataset.activeField = fields[activeField]?.key || '';
    if (fieldSelect) fieldSelect.value = fields[activeField]?.key || '';
    markRuntime(root, 'ready');
  };

  const boot = () => {
    document.querySelectorAll('[data-sc-field-spotlights="v4.3.22.3"]').forEach((root) => {
      if (root.dataset.scFieldSpotlightsMode === 'master') initializeMaster(root);
      else initializeSingle(root);
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
