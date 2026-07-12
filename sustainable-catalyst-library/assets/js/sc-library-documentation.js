(() => {
  'use strict';

  const config = window.SCLibraryDocumentation || {};
  const restBase = String(config.restBase || '').replace(/\/$/, '');
  const strings = config.strings || {};

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
    if (!response.ok) throw new Error(`Documentation request failed: ${response.status}`);
    return response.json();
  };

  const statusClass = (status) => `sc-doc-status--${String(status || 'current').replace(/[^a-z0-9_-]/g, '')}`;

  const actionHtml = (action) => {
    const external = /^https?:/i.test(action.url || '');
    const className = action.type === 'record' ? 'sc-doc-action sc-doc-action--primary' : 'sc-doc-action';
    return `<a class="${className}" href="${escapeHtml(action.url)}"${external ? ' target="_blank" rel="noopener"' : ''}>${escapeHtml(action.label)}</a>`;
  };

  const referenceList = (title, items = []) => {
    if (!items.length) return '';
    return `<section class="sc-doc-record__relations"><h5>${escapeHtml(title)}</h5><ul>${items.map((item) => `<li><a href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener">${escapeHtml(item.title || 'Untitled record')}</a>${item.relationship ? `<span>${escapeHtml(item.relationship)}</span>` : ''}${item.note ? `<p>${escapeHtml(item.note)}</p>` : ''}</li>`).join('')}</ul></section>`;
  };

  const recordHtml = (item, compact = false) => {
    const categories = (item.categories || []).map((category) => `<span>${escapeHtml(category.name)}</span>`).join('');
    const warning = item.warning?.message ? `<div class="sc-doc-record__warning sc-doc-record__warning--${escapeHtml(item.warning.level || 'info')}">${escapeHtml(item.warning.message)}</div>` : '';
    const review = item.review_due ? '<span class="sc-doc-record__review-due">Review due</span>' : '';
    const actions = (item.actions || []).map(actionHtml).join('');
    const dependencyHtml = referenceList('Dependencies', item.dependencies || []);
    const relationHtml = referenceList('Related documents', item.related || []);
    const history = [
      item.supersedes ? referenceList('Supersedes', [item.supersedes]) : '',
      item.superseded_by ? referenceList('Superseded by', [item.superseded_by]) : ''
    ].join('');

    if (compact) {
      return `<article class="sc-doc-feature-card">
        <div class="sc-doc-feature-card__top"><span class="sc-doc-status ${statusClass(item.status)}">${escapeHtml(item.status_label)}</span>${item.authoritative ? '<span class="sc-doc-authority">Authoritative source identified</span>' : ''}</div>
        <h4>${escapeHtml(item.title)}</h4>
        <p>${escapeHtml(item.excerpt || '')}</p>
        <div class="sc-doc-feature-card__meta"><span>${escapeHtml(item.responsible_area || item.document_type_label)}</span>${item.version ? `<span>Version ${escapeHtml(item.version)}</span>` : ''}</div>
        <button type="button" data-doc-open-record="${Number(item.id)}">View document record</button>
      </article>`;
    }

    return `<details class="sc-doc-record" data-doc-record-id="${Number(item.id)}">
      <summary>
        <span class="sc-doc-record__status"><span class="sc-doc-status ${statusClass(item.status)}">${escapeHtml(item.status_label)}</span>${item.authoritative ? '<span class="sc-doc-authority">Authoritative</span>' : ''}${review}</span>
        <span class="sc-doc-record__title"><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(item.document_type_label)}${item.responsible_area ? ` · ${escapeHtml(item.responsible_area)}` : ''}</small></span>
        <span class="sc-doc-record__version">${item.version ? `<strong>${escapeHtml(item.version)}</strong>` : ''}<small>${item.modified_at ? `Updated ${escapeHtml(formatDate(item.modified_at))}` : ''}</small></span>
      </summary>
      <div class="sc-doc-record__panel">
        ${warning}
        <div class="sc-doc-record__intro"><p>${escapeHtml(item.excerpt || '')}</p>${categories ? `<div class="sc-doc-record__categories">${categories}</div>` : ''}</div>
        <section class="sc-doc-record__authority">
          <div><span>Current authority</span><strong>${escapeHtml(item.authority_type_label || 'Not specified')}</strong>${item.authority_note ? `<p>${escapeHtml(item.authority_note)}</p>` : ''}</div>
          ${item.authority_url ? `<a href="${escapeHtml(item.authority_url)}" target="_blank" rel="noopener">Open authoritative source</a>` : '<span class="sc-doc-record__missing">Authoritative source not yet assigned</span>'}
        </section>
        <dl class="sc-doc-record__facts">
          ${item.version ? `<div><dt>Version</dt><dd>${escapeHtml(item.version)}</dd></div>` : ''}
          ${item.last_reviewed ? `<div><dt>Last reviewed</dt><dd>${escapeHtml(formatDate(item.last_reviewed))}</dd></div>` : ''}
          ${item.responsible_area ? `<div><dt>Responsible area</dt><dd>${escapeHtml(item.responsible_area)}</dd></div>` : ''}
          <div><dt>Library record</dt><dd><code>${escapeHtml(item.record_identifier)}</code></dd></div>
        </dl>
        ${(dependencyHtml || relationHtml || history) ? `<div class="sc-doc-record__relationship-grid">${history}${dependencyHtml}${relationHtml}</div>` : ''}
        <div class="sc-doc-record__actions">${actions}</div>
      </div>
    </details>`;
  };

  document.querySelectorAll('[data-sc-doc-library]').forEach((root) => {
    const form = root.querySelector('[data-doc-search-form]');
    const search = root.querySelector('[data-doc-search]');
    const category = root.querySelector('[data-doc-category]');
    const status = root.querySelector('[data-doc-status]');
    const area = root.querySelector('[data-doc-area]');
    const archived = root.querySelector('[data-doc-archived]');
    const sort = root.querySelector('[data-doc-sort]');
    const reset = root.querySelector('[data-doc-reset]');
    const records = root.querySelector('[data-doc-records]');
    const loading = root.querySelector('[data-doc-loading]');
    const pagination = root.querySelector('[data-doc-pagination]');
    const active = root.querySelector('[data-doc-active]');
    const resultsTitle = root.querySelector('[data-doc-results-title]');
    const featuredRegion = root.querySelector('[data-doc-featured-region]');
    const featured = root.querySelector('[data-doc-featured]');

    const state = {
      search: '', category: '', status: '', area: '',
      include_archived: root.dataset.includeArchived === '1',
      sort: 'updated', page: 1,
      per_page: Number(root.dataset.perPage || 12)
    };

    const updateUrl = () => {
      const url = new URL(window.location.href);
      const entries = {
        doc_search: state.search,
        doc_category: state.category,
        doc_status: state.status,
        doc_area: state.area,
        doc_archived: state.include_archived ? '1' : '',
        doc_page: state.page > 1 ? String(state.page) : ''
      };
      Object.entries(entries).forEach(([key, value]) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
      window.history.replaceState({}, '', url);
    };

    const updateActive = () => {
      if (!active) return;
      const labels = [];
      if (state.search) labels.push(`Search: ${state.search}`);
      if (state.category) labels.push(category?.selectedOptions[0]?.textContent || state.category);
      if (state.status) labels.push(status?.selectedOptions[0]?.textContent || state.status);
      if (state.area) labels.push(state.area);
      if (state.include_archived) labels.push('Including archives');
      active.hidden = labels.length === 0;
      active.textContent = labels.join(' · ');
    };

    const renderPagination = (data) => {
      if (!pagination) return;
      const page = Number(data.page || 1);
      const totalPages = Number(data.total_pages || 0);
      if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
      }
      pagination.innerHTML = `<button type="button" data-doc-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${totalPages}</span><button type="button" data-doc-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>Next</button>`;
    };

    const updateAreaOptions = (areas = []) => {
      if (!area) return;
      const current = state.area;
      area.innerHTML = '<option value="">All areas</option>' + areas.map((item) => `<option value="${escapeHtml(item.name)}">${escapeHtml(item.name)} (${Number(item.count)})</option>`).join('');
      area.value = current;
    };

    const loadRecords = async () => {
      if (loading) loading.textContent = strings.loading || 'Loading documentation…';
      if (records) records.setAttribute('aria-busy', 'true');
      updateActive();
      updateUrl();
      try {
        const data = await api('documentation', state);
        if (records) records.innerHTML = (data.items || []).length
          ? data.items.map((item) => recordHtml(item)).join('')
          : `<p class="sc-doc-library__empty">${escapeHtml(strings.empty || 'No documentation records match these filters.')}</p>`;
        if (loading) loading.textContent = `${Number(data.pagination?.total || 0)} ${strings.records || 'documentation records'}`;
        if (resultsTitle) resultsTitle.textContent = state.search ? `Results for “${state.search}”` : 'Foundations documentation';
        renderPagination(data.pagination || {});
        if (!state.area) updateAreaOptions(data.facets?.areas || []);
        const recordId = Number(new URL(window.location.href).searchParams.get('documentation_record') || 0);
        if (recordId) root.querySelector(`[data-doc-record-id="${recordId}"]`)?.setAttribute('open', '');
      } catch (error) {
        if (records) records.innerHTML = `<p class="sc-doc-library__empty">${escapeHtml(strings.error || 'The documentation library could not be loaded.')}</p>`;
        if (loading) loading.textContent = '';
      } finally {
        if (records) records.removeAttribute('aria-busy');
      }
    };

    const loadFeatured = async () => {
      if (!featured || root.dataset.showFeatured !== '1') return;
      try {
        const data = await api('documentation', { featured: true, per_page: 6, include_archived: false, sort: 'updated' });
        const items = data.items || [];
        if (!items.length) return;
        featured.innerHTML = items.map((item) => recordHtml(item, true)).join('');
        if (featuredRegion) featuredRegion.hidden = false;
      } catch (error) {
        if (featuredRegion) featuredRegion.hidden = true;
      }
    };

    const loadFacets = async () => {
      try {
        const [categoryData, statusData] = await Promise.all([api('documentation/categories'), api('documentation/statuses')]);
        if (category) category.innerHTML = '<option value="">All documentation</option>' + (categoryData.items || []).map((item) => `<option value="${escapeHtml(item.slug)}">${escapeHtml(item.name)} (${Number(item.count)})</option>`).join('');
        if (status) status.innerHTML = '<option value="">Current and living</option>' + (statusData.statuses || []).map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`).join('');
        category.value = state.category;
        status.value = state.status;
      } catch (error) {
        // The records endpoint remains usable if facet metadata fails.
      }
    };

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      state.search = search?.value.trim() || '';
      state.page = 1;
      loadRecords();
    });
    category?.addEventListener('change', () => { state.category = category.value; state.page = 1; loadRecords(); });
    status?.addEventListener('change', () => { state.status = status.value; state.page = 1; loadRecords(); });
    area?.addEventListener('change', () => { state.area = area.value; state.page = 1; loadRecords(); });
    archived?.addEventListener('change', () => { state.include_archived = archived.checked; state.page = 1; loadRecords(); });
    sort?.addEventListener('change', () => { state.sort = sort.value; state.page = 1; loadRecords(); });
    reset?.addEventListener('click', () => {
      Object.assign(state, { search: '', category: '', status: '', area: '', include_archived: false, sort: 'updated', page: 1 });
      if (search) search.value = '';
      if (category) category.value = '';
      if (status) status.value = '';
      if (area) area.value = '';
      if (archived) archived.checked = false;
      if (sort) sort.value = 'updated';
      loadRecords();
    });

    root.addEventListener('click', (event) => {
      const pageButton = event.target.closest('[data-doc-page]');
      if (pageButton && !pageButton.disabled) {
        state.page = Math.max(1, Number(pageButton.dataset.docPage || 1));
        loadRecords();
        root.querySelector('[data-doc-results-title]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      const openButton = event.target.closest('[data-doc-open-record]');
      if (openButton) {
        const id = Number(openButton.dataset.docOpenRecord || 0);
        const target = root.querySelector(`[data-doc-record-id="${id}"]`);
        if (target) {
          target.open = true;
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });

    const url = new URL(window.location.href);
    state.search = url.searchParams.get('doc_search') || '';
    state.category = url.searchParams.get('doc_category') || '';
    state.status = url.searchParams.get('doc_status') || '';
    state.area = url.searchParams.get('doc_area') || '';
    state.include_archived = url.searchParams.get('doc_archived') === '1' || state.include_archived;
    state.page = Math.max(1, Number(url.searchParams.get('doc_page') || 1));
    if (search) search.value = state.search;
    if (archived) archived.checked = state.include_archived;

    Promise.allSettled([loadFacets(), loadFeatured()]).finally(loadRecords);
  });
})();
