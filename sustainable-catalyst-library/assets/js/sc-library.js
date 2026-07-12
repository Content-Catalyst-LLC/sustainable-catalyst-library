(() => {
  'use strict';

  const shared = window.SCLibraryShared || {};
  const restBase = String(shared.restBase || '').replace(/\/$/, '');
  const strings = shared.strings || {};

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));

  const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };

  const api = async (path, params = {}) => {
    const url = new URL(`${restBase}/${path}`);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined && value !== false && value !== 0) {
        url.searchParams.set(key, String(value));
      }
    });
    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Request failed: ${response.status}`);
    return response.json();
  };

  const recentKey = 'scLibraryRecentRecordsV110';
  const getRecent = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(recentKey) || '[]');
      return Array.isArray(parsed) ? parsed.slice(0, 6) : [];
    } catch (error) {
      return [];
    }
  };
  const setRecent = (items) => {
    try {
      window.localStorage.setItem(recentKey, JSON.stringify(items.slice(0, 6)));
    } catch (error) {
      // Local storage is optional.
    }
  };

  document.querySelectorAll('[data-sc-library]').forEach((root) => {
    const mode = root.dataset.mode || 'compact';
    if (mode === 'pathways') return;

    const form = root.querySelector('[data-library-form]');
    const searchInput = root.querySelector('[data-library-search]');
    const sortInput = root.querySelector('[data-library-sort]');
    const resultsRegion = root.querySelector('[data-results-region]');
    const results = root.querySelector('[data-library-results]');
    const status = root.querySelector('[data-library-status]');
    const pagination = root.querySelector('[data-library-pagination]');
    const categoryList = root.querySelector('[data-category-list]');
    const seriesList = root.querySelector('[data-series-list]');
    const conceptList = root.querySelector('[data-concept-list]');
    const clearButton = root.querySelector('[data-clear-filters]');
    const activeFilter = root.querySelector('[data-active-filter]');
    const resultsTitle = root.querySelector('[data-results-title]');
    const context = root.querySelector('[data-library-context]');
    const contextContent = root.querySelector('[data-context-content]');
    const recentSection = root.querySelector('[data-library-recent]');
    const recentList = root.querySelector('[data-recent-list]');
    const clearRecent = root.querySelector('[data-clear-recent]');

    const state = {
      search: '',
      category: 0,
      categoryName: '',
      categorySlug: '',
      series: '',
      seriesName: '',
      concept: '',
      conceptName: '',
      sort: 'relevance',
      page: 1,
      per_page: Number(root.dataset.perPage || 10),
      include_children: true,
      hasInteracted: root.dataset.initialResults === '1',
    };

    let categoryItems = [];
    let lastFocusedElement = null;
    let activeRecordId = 0;

    const showResultsRegion = () => {
      if (resultsRegion) resultsRegion.hidden = false;
    };

    const updateUrl = () => {
      const url = new URL(window.location.href);
      const values = {
        library_search: state.search,
        library_topic: state.categorySlug,
        library_series: state.series,
        library_concept: state.concept,
        library_page: state.page > 1 ? String(state.page) : '',
      };
      Object.entries(values).forEach(([key, value]) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
      if (activeRecordId) url.searchParams.set('library_record', String(activeRecordId));
      else url.searchParams.delete('library_record');
      window.history.replaceState({}, '', url);
    };

    const resourceBadges = (resources = {}) => {
      const labels = [];
      if (resources.code) labels.push('Code');
      if (resources.equations) labels.push('Equations');
      if (resources.dataset) labels.push('Dataset');
      if (resources.video) labels.push('Video');
      if (resources.workbench) labels.push('Workbench');
      return labels.map((label) => `<span>${escapeHtml(label)}</span>`).join('');
    };

    const rememberRecord = (item) => {
      const current = getRecent().filter((entry) => Number(entry.id) !== Number(item.id));
      current.unshift({
        id: item.id,
        title: item.title,
        url: item.url,
        type_label: item.type_label || 'Publication',
        record_identifier: item.record_identifier || '',
      });
      setRecent(current);
      renderRecent();
    };

    const renderRecent = () => {
      if (!recentSection || !recentList) return;
      const recent = getRecent();
      recentSection.hidden = recent.length === 0;
      recentList.innerHTML = recent.map((item) => `
        <button type="button" data-open-context="${Number(item.id)}">
          <span>${escapeHtml(item.type_label || 'Publication')}</span>
          <strong>${escapeHtml(item.title)}</strong>
        </button>
      `).join('');
    };

    const renderItems = (items) => {
      if (!results) return;
      results.innerHTML = '';
      if (!items.length) {
        results.innerHTML = `<p class="sc-library__empty">${escapeHtml(strings.empty || 'No knowledge records match this request.')}</p>`;
        return;
      }

      items.forEach((item) => {
        const article = document.createElement('article');
        article.className = 'sc-library-record';
        article.dataset.recordId = String(item.id);
        const categories = (item.categories || []).slice(0, 2).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
        const concepts = (item.concepts || []).slice(0, 3).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
        const badges = resourceBadges(item.resources);
        const updated = formatDate(item.modified_at);
        const placement = item.series?.name
          ? `<span class="sc-library-record__series">Series: ${escapeHtml(item.series.name)}${Number(item.series.order) > 0 ? ` · ${escapeHtml(item.series.order)}` : ''}</span>`
          : '';

        article.innerHTML = `
          <div class="sc-library-record__meta">
            <span class="sc-library-record__type">${escapeHtml(item.type_label || 'Publication')}</span>
            ${placement}
            ${categories ? `<span class="sc-library-record__topics">${categories}</span>` : ''}
          </div>
          <div class="sc-library-record__body">
            <h4><a href="${escapeHtml(item.url)}" data-record-link>${escapeHtml(item.title)}</a></h4>
            <p>${escapeHtml(item.excerpt || '')}</p>
            ${concepts ? `<div class="sc-library-record__concepts">${concepts}</div>` : ''}
          </div>
          <div class="sc-library-record__foot">
            <div class="sc-library-record__resources">${badges}</div>
            <div class="sc-library-record__actions">
              ${updated ? `<time datetime="${escapeHtml(item.modified_at)}">Updated ${escapeHtml(updated)}</time>` : ''}
              <button type="button" data-open-context="${Number(item.id)}">View knowledge record</button>
            </div>
          </div>`;

        article.querySelector('[data-record-link]')?.addEventListener('click', () => rememberRecord(item));
        results.appendChild(article);
      });
    };

    const renderPagination = (meta) => {
      if (!pagination) return;
      pagination.innerHTML = '';
      if (!meta || meta.total_pages <= 1) return;

      const makeButton = (label, page, disabled = false, current = false) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        if (current) button.classList.add('is-current');
        button.addEventListener('click', () => {
          state.page = page;
          loadItems();
          resultsRegion?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        return button;
      };

      pagination.appendChild(makeButton('Previous', Math.max(1, meta.page - 1), meta.page <= 1));
      const start = Math.max(1, meta.page - 2);
      const end = Math.min(meta.total_pages, meta.page + 2);
      for (let page = start; page <= end; page += 1) {
        pagination.appendChild(makeButton(String(page), page, false, page === meta.page));
      }
      pagination.appendChild(makeButton('Next', Math.min(meta.total_pages, meta.page + 1), meta.page >= meta.total_pages));
    };

    const updateResultHeading = () => {
      const selectedName = state.seriesName || state.conceptName || state.categoryName;
      if (resultsTitle) resultsTitle.textContent = selectedName || (state.search ? `Results for “${state.search}”` : 'Knowledge records');
      if (activeFilter) {
        const parts = [];
        if (state.categoryName) parts.push(`<span>Topic: <strong>${escapeHtml(state.categoryName)}</strong></span>`);
        if (state.seriesName) parts.push(`<span>Series: <strong>${escapeHtml(state.seriesName)}</strong></span>`);
        if (state.conceptName) parts.push(`<span>Concept: <strong>${escapeHtml(state.conceptName)}</strong></span>`);
        if (state.search) parts.push(`<span>Search: <strong>${escapeHtml(state.search)}</strong></span>`);
        activeFilter.innerHTML = parts.join('');
        activeFilter.hidden = parts.length === 0;
      }
    };

    const loadItems = async () => {
      if (!state.hasInteracted) return;
      showResultsRegion();
      updateResultHeading();
      if (status) status.textContent = strings.loading || 'Searching the knowledge base…';
      results?.setAttribute('aria-busy', 'true');

      try {
        const data = await api('items', state);
        renderItems(data.items || []);
        renderPagination(data.pagination);
        const total = Number(data.pagination?.total || 0);
        if (status) status.textContent = `${total.toLocaleString()} ${total === 1 ? (strings.result || 'result') : (strings.results || 'results')}`;
        updateUrl();
      } catch (error) {
        if (results) results.innerHTML = `<p class="sc-library__empty">${escapeHtml(strings.error || 'The knowledge base could not be loaded.')}</p>`;
        if (pagination) pagination.innerHTML = '';
        if (status) status.textContent = '';
      } finally {
        results?.removeAttribute('aria-busy');
      }
    };

    const clearSelectedNodes = () => {
      root.querySelectorAll('[data-category-node], [data-series-chip], [data-concept-chip]').forEach((node) => node.classList.remove('is-selected'));
    };

    const chooseFacet = (kind, item, node) => {
      clearSelectedNodes();
      node?.classList.add('is-selected');
      state.category = 0;
      state.categoryName = '';
      state.categorySlug = '';
      state.series = '';
      state.seriesName = '';
      state.concept = '';
      state.conceptName = '';
      if (kind === 'series') {
        state.series = item.slug;
        state.seriesName = item.name;
        state.sort = 'series';
        if (sortInput) sortInput.value = 'series';
      } else {
        state.concept = item.slug;
        state.conceptName = item.name;
        state.sort = 'updated';
        if (sortInput) sortInput.value = 'updated';
      }
      state.search = searchInput ? searchInput.value.trim() : state.search;
      state.page = 1;
      state.hasInteracted = true;
      loadItems();
    };

    const renderFacetList = (container, items, kind) => {
      if (!container) return;
      container.innerHTML = '';
      if (!items.length) {
        container.innerHTML = '<p class="sc-library__empty">No indexed records are assigned yet.</p>';
        return;
      }
      items.slice(0, kind === 'concept' ? 80 : 40).forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset[kind === 'series' ? 'seriesChip' : 'conceptChip'] = item.slug;
        button.innerHTML = `<strong>${escapeHtml(item.name)}</strong><span>${Number(item.count).toLocaleString()}</span>`;
        button.addEventListener('click', () => chooseFacet(kind, item, button));
        container.appendChild(button);
      });
    };

    const renderFacets = async () => {
      if (!seriesList && !conceptList) return;
      if (seriesList) seriesList.innerHTML = '<p class="sc-library__topic-loading">Loading series…</p>';
      if (conceptList) conceptList.innerHTML = '<p class="sc-library__topic-loading">Loading concepts…</p>';
      try {
        const [seriesData, conceptsData] = await Promise.all([api('series'), api('concepts')]);
        renderFacetList(seriesList, seriesData.items || [], 'series');
        renderFacetList(conceptList, conceptsData.items || [], 'concept');

        const url = new URL(window.location.href);
        const wantedSeries = url.searchParams.get('library_series') || root.dataset.initialSeries || '';
        const wantedConcept = url.searchParams.get('library_concept') || root.dataset.initialConcept || '';
        if (wantedSeries) {
          const item = (seriesData.items || []).find((entry) => entry.slug === wantedSeries);
          if (item) {
            state.series = item.slug;
            state.seriesName = item.name;
            state.sort = 'series';
            state.hasInteracted = true;
            seriesList?.querySelector(`[data-series-chip="${CSS.escape(item.slug)}"]`)?.classList.add('is-selected');
          }
        } else if (wantedConcept) {
          const item = (conceptsData.items || []).find((entry) => entry.slug === wantedConcept);
          if (item) {
            state.concept = item.slug;
            state.conceptName = item.name;
            state.hasInteracted = true;
            conceptList?.querySelector(`[data-concept-chip="${CSS.escape(item.slug)}"]`)?.classList.add('is-selected');
          }
        }
      } catch (error) {
        const message = `<p class="sc-library__empty">${escapeHtml(strings.facetsError || 'Series and concept navigation is temporarily unavailable.')}</p>`;
        if (seriesList) seriesList.innerHTML = message;
        if (conceptList) conceptList.innerHTML = message;
      }
    };

    const descendantsOf = (parentId) => categoryItems.filter((item) => Number(item.parent) === Number(parentId));

    const renderCategoryNode = (item, depth = 0) => {
      const children = descendantsOf(item.id);
      const wrapper = document.createElement(children.length ? 'details' : 'div');
      wrapper.className = `sc-library-domain sc-library-domain--depth-${Math.min(depth, 3)}`;
      wrapper.dataset.categoryNode = String(item.id);

      const control = document.createElement(children.length ? 'summary' : 'button');
      if (!children.length) control.type = 'button';
      control.className = 'sc-library-domain__control';
      control.innerHTML = `<span><strong>${escapeHtml(item.name)}</strong><small>${Number(item.count).toLocaleString()} records</small></span>${children.length ? '<span class="sc-library-domain__toggle" aria-hidden="true">+</span>' : '<span aria-hidden="true">→</span>'}`;
      wrapper.appendChild(control);

      const selectTopic = (event) => {
        if (event) event.preventDefault();
        clearSelectedNodes();
        wrapper.classList.add('is-selected');
        state.category = Number(item.id);
        state.categoryName = item.name;
        state.categorySlug = item.slug;
        state.series = '';
        state.seriesName = '';
        state.concept = '';
        state.conceptName = '';
        state.search = searchInput ? searchInput.value.trim() : state.search;
        state.sort = state.search ? 'relevance' : 'updated';
        if (sortInput) sortInput.value = state.sort;
        state.page = 1;
        state.hasInteracted = true;
        loadItems();
      };

      if (children.length) {
        const content = document.createElement('div');
        content.className = 'sc-library-domain__children';
        const parentButton = document.createElement('button');
        parentButton.type = 'button';
        parentButton.className = 'sc-library-domain__all';
        parentButton.textContent = `View all ${item.name}`;
        parentButton.addEventListener('click', selectTopic);
        content.appendChild(parentButton);
        children.forEach((child) => content.appendChild(renderCategoryNode(child, depth + 1)));
        wrapper.appendChild(content);
      } else {
        control.addEventListener('click', selectTopic);
      }
      return wrapper;
    };

    const renderCategories = async () => {
      if (!categoryList) return;
      categoryList.innerHTML = '<p class="sc-library__topic-loading">Loading topic map…</p>';
      try {
        const data = await api('categories');
        categoryItems = Array.isArray(data.items) ? data.items : [];
        categoryList.innerHTML = '';
        const roots = categoryItems.filter((item) => Number(item.parent) === 0);
        roots.forEach((item) => categoryList.appendChild(renderCategoryNode(item)));
        if (!roots.length) categoryList.innerHTML = '<p class="sc-library__empty">No indexed topics are available yet.</p>';

        const urlSlug = new URL(window.location.href).searchParams.get('library_topic') || '';
        const wantedSlug = urlSlug || root.dataset.initialCategory || '';
        if (wantedSlug) {
          const initialItem = categoryItems.find((item) => item.slug === wantedSlug);
          if (initialItem) {
            state.category = Number(initialItem.id);
            state.categoryName = initialItem.name;
            state.categorySlug = initialItem.slug;
            state.hasInteracted = true;
            categoryList.querySelector(`[data-category-node="${initialItem.id}"]`)?.classList.add('is-selected');
          }
        }
      } catch (error) {
        categoryList.innerHTML = `<p class="sc-library__empty">${escapeHtml(strings.categoriesError || 'Topic navigation is temporarily unavailable.')}</p>`;
      }
    };

    const relationGroupHtml = (group) => {
      const items = (group.items || []).map((entry) => `
        <li>
          <button type="button" data-open-context="${Number(entry.record.id)}">${escapeHtml(entry.record.title)}</button>
          ${entry.note ? `<p>${escapeHtml(entry.note)}</p>` : ''}
        </li>`).join('');
      return items ? `<section class="sc-library-context__relation-group"><h4>${escapeHtml(group.label)}</h4><ul>${items}</ul></section>` : '';
    };

    const relatedListHtml = (items, heading) => {
      if (!items?.length) return '';
      return `<section class="sc-library-context__related"><h4>${escapeHtml(heading)}</h4><ul>${items.map((record) => `<li><button type="button" data-open-context="${Number(record.id)}">${escapeHtml(record.title)}</button></li>`).join('')}</ul></section>`;
    };

    const externalLinksHtml = (urls, label) => {
      if (!urls?.length) return '';
      return `<div><dt>${escapeHtml(label)}</dt><dd>${urls.map((url, index) => `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(`${label} ${index + 1}`)}</a>`).join('')}</dd></div>`;
    };

    const seriesNavigationHtml = (series) => {
      if (!series || !series.total) return '';
      return `
        <section class="sc-library-context__series">
          <div><span>Series</span><strong>${escapeHtml(series.name)}</strong><small>${series.position ? `Record ${series.position} of ${series.total}` : `${series.total} records`}</small></div>
          <nav aria-label="Series navigation">
            ${series.previous ? `<button type="button" data-open-context="${Number(series.previous.id)}"><span>Previous</span><strong>${escapeHtml(series.previous.title)}</strong></button>` : '<span></span>'}
            ${series.next ? `<button type="button" data-open-context="${Number(series.next.id)}"><span>Next</span><strong>${escapeHtml(series.next.title)}</strong></button>` : '<span></span>'}
          </nav>
        </section>`;
    };

    const closeContext = () => {
      if (!context) return;
      context.hidden = true;
      activeRecordId = 0;
      updateUrl();
      document.documentElement.classList.remove('sc-library-context-open');
      if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    };

    const openContext = async (recordId, trigger = null) => {
      if (!context || !contextContent) return;
      const id = Number(recordId);
      if (!id) return;
      lastFocusedElement = trigger || document.activeElement;
      activeRecordId = id;
      updateUrl();
      context.hidden = false;
      document.documentElement.classList.add('sc-library-context-open');
      contextContent.innerHTML = `<p class="sc-library-context__loading">${escapeHtml(strings.recordLoading || 'Loading the knowledge record…')}</p>`;

      try {
        const item = await api(`items/${id}`);
        rememberRecord(item);
        const breadcrumbs = (item.breadcrumbs || []).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('<i aria-hidden="true">/</i>');
        const categories = (item.categories || []).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
        const concepts = (item.concepts || []).map((term) => `<button type="button" data-filter-concept="${escapeHtml(term.slug)}" data-filter-name="${escapeHtml(term.name)}">${escapeHtml(term.name)}</button>`).join('');
        const badges = resourceBadges(item.resources);
        const relationGroups = (item.related_groups || []).map(relationGroupHtml).join('');
        const workbenchTools = item.resources?.workbench_tools || [];
        const resourceParts = [
          item.resources?.github_url ? `<div><dt>Code companion</dt><dd><a href="${escapeHtml(item.resources.github_url)}" target="_blank" rel="noopener">Open GitHub repository</a></dd></div>` : '',
          externalLinksHtml(item.resources?.dataset_urls, 'Dataset'),
          externalLinksHtml(item.resources?.video_urls, 'Video'),
          workbenchTools.length ? `<div><dt>Workbench tools</dt><dd>${workbenchTools.map((tool) => `<code>${escapeHtml(tool)}</code>`).join('')}</dd></div>` : '',
        ].filter(Boolean);
        const resources = resourceParts.join('');

        contextContent.innerHTML = `
          ${breadcrumbs ? `<nav class="sc-library-context__breadcrumbs" aria-label="Knowledge hierarchy">${breadcrumbs}</nav>` : ''}
          <p class="sc-library-context__eyebrow">${escapeHtml(item.type_label || 'Knowledge record')}</p>
          <h3 id="${escapeHtml(root.id)}-context-title">${escapeHtml(item.title)}</h3>
          <p class="sc-library-context__identifier">${escapeHtml(item.record_identifier || '')}</p>
          ${categories ? `<div class="sc-library-context__topics">${categories}</div>` : ''}
          <p class="sc-library-context__summary">${escapeHtml(item.excerpt || '')}</p>
          ${badges ? `<div class="sc-library-context__resources">${badges}</div>` : ''}
          ${concepts ? `<section class="sc-library-context__concepts"><h4>Connected concepts</h4><div>${concepts}</div></section>` : ''}
          ${seriesNavigationHtml(item.series)}
          <dl class="sc-library-context__facts">
            ${item.primary_domain ? `<div><dt>Primary domain</dt><dd>${escapeHtml(item.primary_domain.name)}</dd></div>` : ''}
            <div><dt>Evidence status</dt><dd>${escapeHtml(item.evidence?.label || 'Not specified')}</dd></div>
            ${item.published_at ? `<div><dt>Published</dt><dd>${escapeHtml(formatDate(item.published_at))}</dd></div>` : ''}
            ${item.modified_at ? `<div><dt>Updated</dt><dd>${escapeHtml(formatDate(item.modified_at))}</dd></div>` : ''}
          </dl>
          ${resources ? `<dl class="sc-library-context__resource-links">${resources}</dl>` : ''}
          <div class="sc-library-context__actions">
            <a class="sc-library-context__primary" href="${escapeHtml(item.url)}">Read publication</a>
            ${item.handoffs?.workbench?.available ? `<a class="sc-library-context__secondary" href="${escapeHtml(item.handoffs.workbench.url)}">${escapeHtml(item.handoffs.workbench.label)}</a>` : ''}
            <button type="button" class="sc-library-context__secondary" data-copy-record>Copy record link</button>
          </div>
          ${relationGroups ? `<section class="sc-library-context__relationships"><h3>Knowledge relationships</h3>${relationGroups}</section>` : ''}
          ${relatedListHtml(item.suggested_related || [], 'Suggested related knowledge')}
        `;
        context.querySelector('.sc-library-context__close')?.focus();
      } catch (error) {
        contextContent.innerHTML = `<p class="sc-library__empty">${escapeHtml(strings.error || 'The knowledge record could not be loaded.')}</p>`;
      }
    };

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      state.search = searchInput ? searchInput.value.trim() : '';
      state.sort = state.search ? 'relevance' : (sortInput?.value || 'updated');
      if (sortInput) sortInput.value = state.sort;
      state.page = 1;
      state.hasInteracted = true;
      loadItems();
    });

    sortInput?.addEventListener('change', () => {
      state.sort = sortInput.value;
      state.page = 1;
      state.hasInteracted = true;
      loadItems();
    });

    clearButton?.addEventListener('click', () => {
      Object.assign(state, {
        search: '', category: 0, categoryName: '', categorySlug: '',
        series: '', seriesName: '', concept: '', conceptName: '',
        sort: 'relevance', page: 1, hasInteracted: false,
      });
      if (searchInput) searchInput.value = '';
      if (sortInput) sortInput.value = 'relevance';
      clearSelectedNodes();
      if (resultsRegion) resultsRegion.hidden = true;
      if (results) results.innerHTML = '';
      if (pagination) pagination.innerHTML = '';
      updateUrl();
    });

    root.addEventListener('click', (event) => {
      const openButton = event.target.closest('[data-open-context]');
      if (openButton) {
        openContext(openButton.dataset.openContext, openButton);
        return;
      }
      const conceptButton = event.target.closest('[data-filter-concept]');
      if (conceptButton) {
        closeContext();
        state.category = 0;
        state.categoryName = '';
        state.categorySlug = '';
        state.series = '';
        state.seriesName = '';
        state.concept = conceptButton.dataset.filterConcept || '';
        state.conceptName = conceptButton.dataset.filterName || '';
        state.page = 1;
        state.sort = 'updated';
        state.hasInteracted = true;
        if (sortInput) sortInput.value = 'updated';
        loadItems();
      }
    });

    context?.addEventListener('click', async (event) => {
      if (event.target.closest('[data-context-close]')) {
        closeContext();
        return;
      }
      const copyButton = event.target.closest('[data-copy-record]');
      if (copyButton) {
        try {
          await navigator.clipboard.writeText(window.location.href);
          copyButton.textContent = strings.copySuccess || 'Record link copied.';
        } catch (error) {
          copyButton.textContent = strings.copyFailure || 'Copy the address from your browser.';
        }
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && context && !context.hidden) closeContext();
    });

    clearRecent?.addEventListener('click', () => {
      setRecent([]);
      renderRecent();
    });

    const url = new URL(window.location.href);
    const urlSearch = url.searchParams.get('library_search') || '';
    const urlPage = Math.max(1, Number(url.searchParams.get('library_page') || 1));
    if (urlSearch) {
      state.search = urlSearch;
      state.page = urlPage;
      state.hasInteracted = true;
      if (searchInput) searchInput.value = urlSearch;
    }

    renderRecent();
    Promise.allSettled([renderCategories(), renderFacets()]).then(() => {
      if (state.hasInteracted) loadItems();
      const initialRecord = Number(url.searchParams.get('library_record') || root.dataset.initialRecord || 0);
      if (initialRecord) openContext(initialRecord);
    });
  });
})();
