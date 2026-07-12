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
      if (value !== '' && value !== null && value !== undefined && value !== false) {
        url.searchParams.set(key, String(value));
      }
    });
    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Request failed: ${response.status}`);
    return response.json();
  };

  const recentKey = 'scLibraryRecentRecordsV101';
  const getRecent = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(recentKey) || '[]');
      return Array.isArray(parsed) ? parsed.slice(0, 5) : [];
    } catch (error) {
      return [];
    }
  };
  const setRecent = (items) => {
    try {
      window.localStorage.setItem(recentKey, JSON.stringify(items.slice(0, 5)));
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
      sort: 'relevance',
      page: 1,
      per_page: Number(root.dataset.perPage || 10),
      include_children: true,
      hasInteracted: root.dataset.initialResults === '1',
    };

    let categoryItems = [];
    let lastFocusedElement = null;

    const showResultsRegion = () => {
      if (resultsRegion) resultsRegion.hidden = false;
    };

    const updateUrl = () => {
      const url = new URL(window.location.href);
      state.search ? url.searchParams.set('library_search', state.search) : url.searchParams.delete('library_search');
      state.categorySlug ? url.searchParams.set('library_topic', state.categorySlug) : url.searchParams.delete('library_topic');
      state.page > 1 ? url.searchParams.set('library_page', String(state.page)) : url.searchParams.delete('library_page');
      window.history.replaceState({}, '', url);
    };

    const resourceBadges = (resources = {}) => {
      const labels = [];
      if (resources.code) labels.push('Code');
      if (resources.equations) labels.push('Equations');
      if (resources.video) labels.push('Video');
      return labels.map((label) => `<span>${escapeHtml(label)}</span>`).join('');
    };

    const rememberRecord = (item) => {
      const current = getRecent().filter((entry) => Number(entry.id) !== Number(item.id));
      current.unshift({ id: item.id, title: item.title, url: item.url, type_label: item.type_label || 'Publication' });
      setRecent(current);
      renderRecent();
    };

    const renderRecent = () => {
      if (!recentSection || !recentList) return;
      const recent = getRecent();
      recentSection.hidden = recent.length === 0;
      recentList.innerHTML = recent.map((item) => `
        <a href="${escapeHtml(item.url)}" data-recent-id="${Number(item.id)}">
          <span>${escapeHtml(item.type_label || 'Publication')}</span>
          <strong>${escapeHtml(item.title)}</strong>
        </a>
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
        const categories = (item.categories || []).slice(0, 3).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
        const badges = resourceBadges(item.resources);
        const updated = formatDate(item.modified_at);

        article.innerHTML = `
          <div class="sc-library-record__meta">
            <span class="sc-library-record__type">${escapeHtml(item.type_label || 'Publication')}</span>
            ${categories ? `<span class="sc-library-record__topics">${categories}</span>` : ''}
          </div>
          <div class="sc-library-record__body">
            <h4><a href="${escapeHtml(item.url)}" data-record-link>${escapeHtml(item.title)}</a></h4>
            <p>${escapeHtml(item.excerpt || '')}</p>
          </div>
          <div class="sc-library-record__foot">
            <div class="sc-library-record__resources">${badges}</div>
            <div class="sc-library-record__actions">
              ${updated ? `<time datetime="${escapeHtml(item.modified_at)}">Updated ${escapeHtml(updated)}</time>` : ''}
              <button type="button" data-open-context="${Number(item.id)}">View context</button>
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
      if (resultsTitle) {
        resultsTitle.textContent = state.categoryName || (state.search ? `Results for “${state.search}”` : 'Knowledge records');
      }
      if (activeFilter) {
        const parts = [];
        if (state.categoryName) parts.push(`<span>Topic: <strong>${escapeHtml(state.categoryName)}</strong></span>`);
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

    const descendantsOf = (parentId) => categoryItems.filter((item) => Number(item.parent) === Number(parentId));

    const renderCategoryNode = (item, depth = 0) => {
      const children = descendantsOf(item.id);
      const wrapper = document.createElement(children.length ? 'details' : 'div');
      wrapper.className = `sc-library-domain sc-library-domain--depth-${Math.min(depth, 3)}`;
      wrapper.dataset.categoryNode = String(item.id);

      const control = document.createElement(children.length ? 'summary' : 'button');
      if (!children.length) control.type = 'button';
      control.className = 'sc-library-domain__control';
      control.innerHTML = `
        <span><strong>${escapeHtml(item.name)}</strong><small>${Number(item.count).toLocaleString()} records</small></span>
        ${children.length ? '<span class="sc-library-domain__toggle" aria-hidden="true">+</span>' : '<span aria-hidden="true">→</span>'}
      `;
      wrapper.appendChild(control);

      const selectTopic = (event) => {
        if (event) event.preventDefault();
        root.querySelectorAll('[data-category-node]').forEach((node) => node.classList.remove('is-selected'));
        wrapper.classList.add('is-selected');
        state.category = Number(item.id);
        state.categoryName = item.name;
        state.categorySlug = item.slug;
        state.search = searchInput ? searchInput.value.trim() : state.search;
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

        const initialSlug = root.dataset.initialCategory || '';
        const urlSlug = new URL(window.location.href).searchParams.get('library_topic') || '';
        const wantedSlug = urlSlug || initialSlug;
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

    const closeContext = () => {
      if (!context) return;
      context.hidden = true;
      document.documentElement.classList.remove('sc-library-context-open');
      if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    };

    const openContext = async (recordId, trigger) => {
      if (!context || !contextContent) return;
      lastFocusedElement = trigger || document.activeElement;
      context.hidden = false;
      document.documentElement.classList.add('sc-library-context-open');
      contextContent.innerHTML = '<p class="sc-library-context__loading">Loading record context…</p>';

      try {
        const item = await api(`items/${Number(recordId)}`);
        rememberRecord(item);
        const categories = (item.categories || []).map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
        const related = (item.related || []).map((record) => `<li><a href="${escapeHtml(record.url)}">${escapeHtml(record.title)}</a></li>`).join('');
        const badges = resourceBadges(item.resources);
        contextContent.innerHTML = `
          <p class="sc-library-context__eyebrow">${escapeHtml(item.type_label || 'Knowledge record')}</p>
          <h3 id="${escapeHtml(root.id)}-context-title">${escapeHtml(item.title)}</h3>
          ${categories ? `<div class="sc-library-context__topics">${categories}</div>` : ''}
          <p class="sc-library-context__summary">${escapeHtml(item.excerpt || '')}</p>
          ${badges ? `<div class="sc-library-context__resources">${badges}</div>` : ''}
          <dl class="sc-library-context__dates">
            ${item.published_at ? `<div><dt>Published</dt><dd>${escapeHtml(formatDate(item.published_at))}</dd></div>` : ''}
            ${item.modified_at ? `<div><dt>Updated</dt><dd>${escapeHtml(formatDate(item.modified_at))}</dd></div>` : ''}
          </dl>
          <a class="sc-library-context__primary" href="${escapeHtml(item.url)}">Open publication</a>
          ${related ? `<section class="sc-library-context__related"><h4>Related knowledge</h4><ul>${related}</ul></section>` : ''}
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
      state.search = '';
      state.category = 0;
      state.categoryName = '';
      state.categorySlug = '';
      state.sort = 'relevance';
      state.page = 1;
      state.hasInteracted = false;
      if (searchInput) searchInput.value = '';
      if (sortInput) sortInput.value = 'relevance';
      root.querySelectorAll('[data-category-node]').forEach((node) => node.classList.remove('is-selected'));
      if (resultsRegion) resultsRegion.hidden = true;
      if (results) results.innerHTML = '';
      if (pagination) pagination.innerHTML = '';
      const url = new URL(window.location.href);
      ['library_search', 'library_topic', 'library_page'].forEach((key) => url.searchParams.delete(key));
      window.history.replaceState({}, '', url);
    });

    results?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-open-context]');
      if (!button) return;
      openContext(button.dataset.openContext, button);
    });

    context?.addEventListener('click', (event) => {
      if (event.target.closest('[data-context-close]')) closeContext();
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
    renderCategories().finally(() => {
      if (state.hasInteracted) loadItems();
    });
  });
})();
