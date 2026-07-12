(() => {
  'use strict';

  const config = window.SCLibraryConfig || {};
  const root = document.querySelector('[data-sc-library]');
  if (!root) return;

  const form = root.querySelector('[data-library-form]');
  const searchInput = form.querySelector('[name="search"]');
  const sortInput = form.querySelector('[name="sort"]');
  const results = root.querySelector('[data-library-results]');
  const status = root.querySelector('[data-library-status]');
  const pagination = root.querySelector('[data-library-pagination]');
  const categoryList = root.querySelector('[data-category-list]');
  const clearButton = root.querySelector('[data-clear-filters]');

  const state = {
    search: '',
    category: 0,
    sort: 'newest',
    page: 1,
    per_page: Number(config.perPage || 12),
  };

  const api = async (path, params = {}) => {
    const url = new URL(`${config.restBase}/${path}`);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== 0 && value !== null && value !== undefined) {
        url.searchParams.set(key, value);
      }
    });
    const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Request failed: ${response.status}`);
    return response.json();
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));

  const renderCategories = async () => {
    try {
      const data = await api('categories');
      const initialSlug = config.initialCategory || '';
      const allButton = categoryList.querySelector('[data-category-id="0"]');
      data.items.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sc-library__category';
        button.dataset.categoryId = String(item.id);
        button.dataset.categorySlug = item.slug;
        button.innerHTML = `<span>${escapeHtml(item.name)}</span><span>${item.count}</span>`;
        categoryList.appendChild(button);
        if (initialSlug && item.slug === initialSlug) {
          state.category = item.id;
          allButton.classList.remove('is-active');
          button.classList.add('is-active');
        }
      });
    } catch (error) {
      categoryList.insertAdjacentHTML('beforeend', '<p class="sc-library__notice">Categories unavailable.</p>');
    }
  };

  const renderItems = (items) => {
    results.innerHTML = '';
    if (!items.length) {
      results.innerHTML = `<p class="sc-library__empty">${escapeHtml(config.strings?.empty || 'No records found.')}</p>`;
      return;
    }

    items.forEach((item) => {
      const article = document.createElement('article');
      article.className = 'sc-library-card';
      const image = item.image
        ? `<a class="sc-library-card__image" href="${escapeHtml(item.url)}"><img src="${escapeHtml(item.image)}" alt="" loading="lazy"></a>`
        : '<div class="sc-library-card__image sc-library-card__image--placeholder" aria-hidden="true"></div>';
      const categories = item.categories.map((term) => `<span>${escapeHtml(term.name)}</span>`).join('');
      const date = item.published_at ? new Date(item.published_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';

      article.innerHTML = `
        ${image}
        <div class="sc-library-card__body">
          <div class="sc-library-card__meta">${categories}</div>
          <h3><a href="${escapeHtml(item.url)}">${escapeHtml(item.title)}</a></h3>
          <p>${escapeHtml(item.excerpt)}</p>
          <div class="sc-library-card__footer">
            <time>${escapeHtml(date)}</time>
            <a href="${escapeHtml(item.url)}">Read publication</a>
          </div>
        </div>`;
      results.appendChild(article);
    });
  };

  const renderPagination = (meta) => {
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
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

  const loadItems = async () => {
    status.textContent = config.strings?.loading || 'Loading…';
    results.setAttribute('aria-busy', 'true');
    try {
      const data = await api('items', state);
      renderItems(data.items || []);
      renderPagination(data.pagination);
      const total = data.pagination?.total || 0;
      status.textContent = `${total.toLocaleString()} publication${total === 1 ? '' : 's'}`;
      const url = new URL(window.location.href);
      state.search ? url.searchParams.set('library_search', state.search) : url.searchParams.delete('library_search');
      state.category ? url.searchParams.set('library_category', String(state.category)) : url.searchParams.delete('library_category');
      state.page > 1 ? url.searchParams.set('library_page', String(state.page)) : url.searchParams.delete('library_page');
      window.history.replaceState({}, '', url);
    } catch (error) {
      results.innerHTML = `<p class="sc-library__empty">${escapeHtml(config.strings?.error || 'The Library could not be loaded.')}</p>`;
      pagination.innerHTML = '';
      status.textContent = '';
    } finally {
      results.removeAttribute('aria-busy');
    }
  };

  categoryList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-category-id]');
    if (!button) return;
    categoryList.querySelectorAll('[data-category-id]').forEach((item) => item.classList.remove('is-active'));
    button.classList.add('is-active');
    state.category = Number(button.dataset.categoryId || 0);
    state.page = 1;
    loadItems();
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    state.search = searchInput.value.trim();
    state.sort = sortInput.value;
    state.page = 1;
    loadItems();
  });

  sortInput.addEventListener('change', () => {
    state.sort = sortInput.value;
    state.page = 1;
    loadItems();
  });

  clearButton.addEventListener('click', () => {
    state.search = '';
    state.category = 0;
    state.sort = 'newest';
    state.page = 1;
    searchInput.value = '';
    sortInput.value = 'newest';
    categoryList.querySelectorAll('[data-category-id]').forEach((item) => item.classList.toggle('is-active', item.dataset.categoryId === '0'));
    loadItems();
  });

  const url = new URL(window.location.href);
  searchInput.value = url.searchParams.get('library_search') || '';
  state.search = searchInput.value;
  state.page = Math.max(1, Number(url.searchParams.get('library_page') || 1));
  const urlCategory = Number(url.searchParams.get('library_category') || 0);
  if (urlCategory) state.category = urlCategory;

  renderCategories().finally(() => {
    if (state.category) {
      categoryList.querySelectorAll('[data-category-id]').forEach((item) => item.classList.toggle('is-active', Number(item.dataset.categoryId) === state.category));
    }
    loadItems();
  });
})();
