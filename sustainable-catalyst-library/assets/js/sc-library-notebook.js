(() => {
  'use strict';

  const shared = window.SCNotebookShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const schema = shared.schema || 'sc-library-workspace/1.0';
  const version = shared.version || '1.2.0';
  const strings = shared.strings || {};
  const sourceTypes = shared.sourceTypes || {};
  const citationFormats = shared.citationFormats || {};
  const roots = Array.from(document.querySelectorAll('[data-sc-library-workspace-root]'));
  if (!roots.length) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));

  const now = () => new Date().toISOString();
  const today = () => new Date().toISOString().slice(0, 10);
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`;
  const words = (value) => String(value || '').trim();
  const listFromText = (value) => String(value || '').split(',').map((item) => item.trim()).filter(Boolean);
  const slug = (value) => words(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'source';
  const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };

  const initialWorkspace = () => {
    const createdAt = now();
    return {
      schema,
      version,
      createdAt,
      updatedAt: createdAt,
      collections: [{
        id: 'collection_inbox',
        title: 'Research Inbox',
        description: 'Newly saved Library records and research material.',
        createdAt,
        updatedAt: createdAt,
      }],
      savedRecords: [],
      notes: [],
      sources: [],
    };
  };

  const sanitizeImported = (data) => {
    if (!data || typeof data !== 'object' || data.schema !== schema) throw new Error('Invalid schema');
    const cleanArray = (value) => Array.isArray(value) ? value.filter((item) => item && typeof item === 'object') : [];
    return {
      schema,
      version: String(data.version || version),
      createdAt: String(data.createdAt || now()),
      updatedAt: now(),
      collections: cleanArray(data.collections),
      savedRecords: cleanArray(data.savedRecords),
      notes: cleanArray(data.notes),
      sources: cleanArray(data.sources),
    };
  };

  let workspace;
  let storageAvailable = true;
  const load = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      workspace = raw ? sanitizeImported(JSON.parse(raw)) : initialWorkspace();
    } catch (error) {
      storageAvailable = false;
      workspace = initialWorkspace();
    }
  };
  const persist = () => {
    workspace.updatedAt = now();
    workspace.version = version;
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(workspace));
      storageAvailable = true;
      window.dispatchEvent(new CustomEvent('sc-library-workspace-updated'));
      return true;
    } catch (error) {
      storageAvailable = false;
      return false;
    }
  };
  load();

  const collectionById = (id) => workspace.collections.find((item) => item.id === id);
  const recordById = (id) => workspace.savedRecords.find((item) => Number(item.recordId) === Number(id));
  const sourceById = (id) => workspace.sources.find((item) => item.id === id);
  const recordCollectionIds = (record) => Array.isArray(record.collectionIds) && record.collectionIds.length ? record.collectionIds : ['collection_inbox'];
  const itemCount = () => workspace.savedRecords.length + workspace.notes.length + workspace.sources.length;
  const scopedCollectionExport = (collectionId) => {
    const collection = collectionById(collectionId);
    if (!collection) return null;
    return {
      schema, version, createdAt: now(), updatedAt: now(),
      exportScope: { type: 'collection', id: collection.id, title: collection.title },
      collections: [collection],
      savedRecords: workspace.savedRecords.filter((item) => recordCollectionIds(item).includes(collectionId)),
      notes: workspace.notes.filter((item) => (item.collectionIds || []).includes(collectionId)),
      sources: workspace.sources.filter((item) => (item.collectionIds || []).includes(collectionId)),
    };
  };

  const typeLabel = (type) => sourceTypes[type]?.label || type.replace(/_/g, ' ');
  const citationYear = (source) => words(source.publication_date).slice(0, 4) || 'n.d.';
  const authorText = (source) => words(source.creators) || words(source.organization) || 'Unknown author';
  const accessText = (source) => source.access_date ? formatDate(source.access_date) : formatDate(today());
  const citation = (source, format = 'plain') => {
    const author = authorText(source);
    const year = citationYear(source);
    const title = words(source.title) || 'Untitled source';
    const publisher = words(source.publisher) || words(source.organization);
    const location = words(source.url || source.doi || source.isbn);
    const pages = words(source.pages);
    const chapter = words(source.chapter);
    if (format === 'apa') {
      return `${author}. (${year}). ${title}${chapter ? `: ${chapter}` : ''}.${publisher ? ` ${publisher}.` : ''}${location ? ` ${location}` : ''}`.replace(/\s+/g, ' ').trim();
    }
    if (format === 'mla') {
      return `${author}. “${title}.”${publisher ? ` ${publisher},` : ''} ${year}.${pages ? ` pp. ${pages}.` : ''}${location ? ` ${location}.` : ''}`.replace(/\s+/g, ' ').trim();
    }
    if (format === 'chicago') {
      return `${author}. “${title}.”${publisher ? ` ${publisher}.` : ''} ${year}.${pages ? ` ${pages}.` : ''}${location ? ` ${location}.` : ''}`.replace(/\s+/g, ' ').trim();
    }
    if (format === 'harvard') {
      return `${author} (${year}) ${title}.${publisher ? ` ${publisher}.` : ''}${location ? ` Available at: ${location} (Accessed: ${accessText(source)}).` : ''}`.replace(/\s+/g, ' ').trim();
    }
    if (format === 'bibtex') {
      const key = `${slug(author.split(/[ ,]/)[0])}${year}${slug(title).split('-').slice(0, 2).join('')}`;
      const entryType = ['book', 'book_chapter'].includes(source.type) ? 'book' : source.type === 'journal_article' ? 'article' : 'misc';
      const fields = [
        `  author = {${author}}`,
        `  title = {${title}}`,
        `  year = {${year}}`,
        publisher ? `  publisher = {${publisher}}` : '',
        source.url ? `  url = {${source.url}}` : '',
        source.doi ? `  doi = {${source.doi}}` : '',
        source.isbn ? `  isbn = {${source.isbn}}` : '',
      ].filter(Boolean).join(',\n');
      return `@${entryType}{${key},\n${fields}\n}`;
    }
    if (format === 'ris') {
      const ty = ['book', 'book_chapter'].includes(source.type) ? 'BOOK' : source.type === 'journal_article' ? 'JOUR' : 'GEN';
      return [`TY  - ${ty}`, `AU  - ${author}`, `TI  - ${title}`, `PY  - ${year}`, publisher ? `PB  - ${publisher}` : '', source.url ? `UR  - ${source.url}` : '', source.doi ? `DO  - ${source.doi}` : '', pages ? `SP  - ${pages}` : '', 'ER  -'].filter(Boolean).join('\n');
    }
    if (format === 'csl_json') {
      return JSON.stringify({
        id: source.id,
        type: ['book', 'book_chapter'].includes(source.type) ? 'book' : source.type === 'journal_article' ? 'article-journal' : 'webpage',
        title,
        author: [{ literal: author }],
        issued: { 'date-parts': [[Number(year) || undefined].filter(Boolean)] },
        publisher: publisher || undefined,
        URL: source.url || undefined,
        DOI: source.doi || undefined,
        ISBN: source.isbn || undefined,
        page: pages || undefined,
      }, null, 2);
    }
    return `${author}. ${title}.${publisher ? ` ${publisher}.` : ''}${year ? ` ${year}.` : ''}${pages ? ` ${pages}.` : ''}${location ? ` ${location}.` : ''}`.replace(/\s+/g, ' ').trim();
  };

  const htmlOptions = (items, selected = '') => items.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label || item.title)}</option>`).join('');
  const collectionOptions = (selected = []) => workspace.collections.map((item) => `<label class="sc-library-workspace__check"><input type="checkbox" name="collectionIds" value="${escapeHtml(item.id)}" ${selected.includes(item.id) ? 'checked' : ''}> <span>${escapeHtml(item.title)}</span></label>`).join('');
  const relatedRecordOptions = (selected = '') => `<option value="">No attached Library record</option>${workspace.savedRecords.map((item) => `<option value="${Number(item.recordId)}" ${String(item.recordId) === String(selected) ? 'selected' : ''}>${escapeHtml(item.title)}</option>`).join('')}`;
  const relatedSourceOptions = (selected = '') => `<option value="">No attached source</option>${workspace.sources.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.title)}</option>`).join('')}`;

  const metric = (label, value) => `<div class="sc-library-workspace__metric"><strong>${Number(value).toLocaleString()}</strong><span>${escapeHtml(label)}</span></div>`;
  const collectionNames = (ids = []) => ids.map((id) => collectionById(id)?.title).filter(Boolean);

  const overviewHtml = () => {
    const recent = [
      ...workspace.savedRecords.map((item) => ({ kind: 'Saved record', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.notes.map((item) => ({ kind: 'Note', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.sources.map((item) => ({ kind: typeLabel(item.type), title: item.title, date: item.updatedAt || item.createdAt })),
    ].sort((a, b) => String(b.date).localeCompare(String(a.date))).slice(0, 8);
    return `
      <section class="sc-library-workspace__overview">
        <div class="sc-library-workspace__metrics">
          ${metric('Saved records', workspace.savedRecords.length)}
          ${metric('Collections', workspace.collections.length)}
          ${metric('Notes', workspace.notes.length)}
          ${metric('Sources', workspace.sources.length)}
        </div>
        <div class="sc-library-workspace__quick-grid">
          <button type="button" data-workspace-quick="collection"><strong>Create collection</strong><span>Group publications, notes, and sources.</span></button>
          <button type="button" data-workspace-quick="note"><strong>Write a note</strong><span>Capture a question, quotation, claim, or synthesis.</span></button>
          <button type="button" data-workspace-quick="source"><strong>Add a source</strong><span>Store a link, book, report, dataset, or video timestamp.</span></button>
          <button type="button" data-workspace-tab="portability"><strong>Export workspace</strong><span>Create a portable JSON research manifest.</span></button>
        </div>
        <section class="sc-library-workspace__recent-work">
          <h3>Recent research activity</h3>
          ${recent.length ? `<div>${recent.map((item) => `<article><span>${escapeHtml(item.kind)}</span><strong>${escapeHtml(item.title)}</strong><time>${escapeHtml(formatDate(item.date))}</time></article>`).join('')}</div>` : '<p>No saved research yet. Open a knowledge record and choose “Save to Notebook,” or add your own note or source.</p>'}
        </section>
      </section>`;
  };

  const collectionsHtml = (editId = '') => {
    const editing = collectionById(editId) || { id: '', title: '', description: '' };
    return `
      <section class="sc-library-workspace__section">
        <header><div><p class="sc-library__eyebrow">Research organization</p><h3>Collections</h3></div></header>
        <form class="sc-library-workspace__form" data-collection-form>
          <input type="hidden" name="id" value="${escapeHtml(editing.id)}">
          <label><span>Collection title</span><input required name="title" value="${escapeHtml(editing.title)}" placeholder="Planetary Boundaries Research"></label>
          <label><span>Description</span><textarea name="description" rows="3" placeholder="What this collection is for">${escapeHtml(editing.description)}</textarea></label>
          <div class="sc-library-workspace__form-actions"><button type="submit">${editing.id ? 'Update collection' : 'Create collection'}</button>${editing.id ? '<button type="button" data-cancel-edit>Cancel</button>' : ''}</div>
        </form>
        <div class="sc-library-workspace__list">
          ${workspace.collections.map((item) => {
            const count = workspace.savedRecords.filter((record) => recordCollectionIds(record).includes(item.id)).length + workspace.notes.filter((note) => (note.collectionIds || []).includes(item.id)).length + workspace.sources.filter((source) => (source.collectionIds || []).includes(item.id)).length;
            return `<article><div><span>Collection</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.description || 'No description')}</p><small>${count} linked items</small></div><div class="sc-library-workspace__row-actions"><button type="button" data-export-collection="${escapeHtml(item.id)}">Export</button><button type="button" data-edit-collection="${escapeHtml(item.id)}">Edit</button>${item.id !== 'collection_inbox' ? `<button type="button" data-delete-collection="${escapeHtml(item.id)}">Delete</button>` : ''}</div></article>`;
          }).join('')}
        </div>
        <section class="sc-library-workspace__saved-records">
          <h3>Saved Library records</h3>
          ${workspace.savedRecords.length ? workspace.savedRecords.map((record) => `<article><div><span>${escapeHtml(record.typeLabel || 'Publication')}</span><h4><a href="${escapeHtml(record.url)}">${escapeHtml(record.title)}</a></h4><p>${escapeHtml(record.excerpt || '')}</p><div class="sc-library-workspace__checks">${workspace.collections.map((collection) => `<label class="sc-library-workspace__check"><input type="checkbox" data-record-collection="${Number(record.recordId)}" value="${escapeHtml(collection.id)}" ${recordCollectionIds(record).includes(collection.id) ? 'checked' : ''}> <span>${escapeHtml(collection.title)}</span></label>`).join('')}</div></div><div class="sc-library-workspace__row-actions"><button type="button" data-note-saved-record="${Number(record.recordId)}">Write note</button><button type="button" data-delete-saved-record="${Number(record.recordId)}">Remove</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No Library records have been saved yet.</p>'}
        </section>
      </section>`;
  };

  const notesHtml = (editId = '', seed = {}) => {
    const found = workspace.notes.find((item) => item.id === editId);
    const editing = found || {
      id: '', type: seed.type || 'note', title: seed.title || '', body: seed.body || '', recordId: seed.recordId || '', sourceId: seed.sourceId || '', tags: [], collectionIds: seed.collectionIds || ['collection_inbox']
    };
    const noteTypes = [
      { id: 'note', label: 'Research note' }, { id: 'question', label: 'Research question' }, { id: 'summary', label: 'Summary' },
      { id: 'quotation', label: 'Quotation' }, { id: 'claim', label: 'Claim' }, { id: 'counterargument', label: 'Counterargument' },
      { id: 'observation', label: 'Observation' }, { id: 'book_note', label: 'Book note' }, { id: 'video_timestamp', label: 'Video timestamp' }, { id: 'custom_section', label: 'Custom section' }
    ];
    return `
      <section class="sc-library-workspace__section">
        <header><div><p class="sc-library__eyebrow">Personal research</p><h3>Notes</h3></div></header>
        <form class="sc-library-workspace__form" data-note-form>
          <input type="hidden" name="id" value="${escapeHtml(editing.id)}">
          <div class="sc-library-workspace__form-grid">
            <label><span>Note type</span><select name="type">${htmlOptions(noteTypes, editing.type)}</select></label>
            <label><span>Title</span><input required name="title" value="${escapeHtml(editing.title)}" placeholder="Key question or observation"></label>
          </div>
          <label><span>Note</span><textarea required name="body" rows="7" placeholder="Write your research note here">${escapeHtml(editing.body)}</textarea></label>
          <div class="sc-library-workspace__form-grid">
            <label><span>Attached Library record</span><select name="recordId">${relatedRecordOptions(editing.recordId)}</select></label>
            <label><span>Attached outside source</span><select name="sourceId">${relatedSourceOptions(editing.sourceId)}</select></label>
          </div>
          <label><span>Tags</span><input name="tags" value="${escapeHtml((editing.tags || []).join(', '))}" placeholder="feedback loops, evidence, review"></label>
          <fieldset><legend>Add to collections</legend><div class="sc-library-workspace__checks">${collectionOptions(editing.collectionIds || ['collection_inbox'])}</div></fieldset>
          <div class="sc-library-workspace__form-actions"><button type="submit">${editing.id ? 'Update note' : 'Save note'}</button>${editing.id ? '<button type="button" data-cancel-edit>Cancel</button>' : ''}</div>
        </form>
        <div class="sc-library-workspace__list">
          ${workspace.notes.length ? workspace.notes.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `<article><div><span>${escapeHtml(item.type.replace(/_/g, ' '))}</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.body)}</p><small>${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small></div><div class="sc-library-workspace__row-actions"><button type="button" data-edit-note="${escapeHtml(item.id)}">Edit</button><button type="button" data-delete-note="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No notes have been saved.</p>'}
        </div>
      </section>`;
  };

  const sourcesHtml = (editId = '') => {
    const found = sourceById(editId);
    const editing = found || {
      id: '', type: 'website', title: '', creators: '', organization: '', url: '', doi: '', isbn: '', publisher: '', publication_date: '', access_date: today(), edition: '', chapter: '', pages: '', physical_location: '', description: '', notes: '', tags: [], collectionIds: ['collection_inbox']
    };
    return `
      <section class="sc-library-workspace__section">
        <header><div><p class="sc-library__eyebrow">Source collection</p><h3>External and physical sources</h3></div></header>
        <form class="sc-library-workspace__form" data-source-form>
          <input type="hidden" name="id" value="${escapeHtml(editing.id)}">
          <div class="sc-library-workspace__form-grid">
            <label><span>Source type</span><select name="type">${htmlOptions(Object.values(sourceTypes), editing.type)}</select></label>
            <label><span>Title</span><input required name="title" value="${escapeHtml(editing.title)}" placeholder="Book, article, report, dataset, or video title"></label>
          </div>
          <div class="sc-library-workspace__form-grid">
            <label><span>Author, editor, or creator</span><input name="creators" value="${escapeHtml(editing.creators)}"></label>
            <label><span>Organization</span><input name="organization" value="${escapeHtml(editing.organization)}"></label>
          </div>
          <label><span>URL</span><input name="url" type="url" value="${escapeHtml(editing.url)}" placeholder="https://"></label>
          <div class="sc-library-workspace__form-grid sc-library-workspace__form-grid--three">
            <label><span>DOI</span><input name="doi" value="${escapeHtml(editing.doi)}"></label>
            <label><span>ISBN</span><input name="isbn" value="${escapeHtml(editing.isbn)}"></label>
            <label><span>Publisher</span><input name="publisher" value="${escapeHtml(editing.publisher)}"></label>
          </div>
          <div class="sc-library-workspace__form-grid sc-library-workspace__form-grid--three">
            <label><span>Publication date</span><input name="publication_date" type="date" value="${escapeHtml(editing.publication_date)}"></label>
            <label><span>Access date</span><input name="access_date" type="date" value="${escapeHtml(editing.access_date)}"></label>
            <label><span>Edition</span><input name="edition" value="${escapeHtml(editing.edition)}"></label>
          </div>
          <div class="sc-library-workspace__form-grid sc-library-workspace__form-grid--three">
            <label><span>Chapter or timestamp</span><input name="chapter" value="${escapeHtml(editing.chapter)}" placeholder="Chapter 3 or 18:42–20:10"></label>
            <label><span>Pages</span><input name="pages" value="${escapeHtml(editing.pages)}" placeholder="35–48"></label>
            <label><span>Physical location</span><input name="physical_location" value="${escapeHtml(editing.physical_location)}" placeholder="Home office — Shelf 3"></label>
          </div>
          <label><span>Description or abstract</span><textarea name="description" rows="4">${escapeHtml(editing.description)}</textarea></label>
          <label><span>Research notes</span><textarea name="notes" rows="4">${escapeHtml(editing.notes)}</textarea></label>
          <label><span>Tags</span><input name="tags" value="${escapeHtml((editing.tags || []).join(', '))}"></label>
          <fieldset><legend>Add to collections</legend><div class="sc-library-workspace__checks">${collectionOptions(editing.collectionIds || ['collection_inbox'])}</div></fieldset>
          <div class="sc-library-workspace__form-actions"><button type="submit">${editing.id ? 'Update source' : 'Save source'}</button>${editing.id ? '<button type="button" data-cancel-edit>Cancel</button>' : ''}</div>
        </form>
        <div class="sc-library-workspace__list sc-library-workspace__source-list">
          ${workspace.sources.length ? workspace.sources.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `<article><div><span>${escapeHtml(typeLabel(item.type))}</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.description || item.notes || citation(item, 'plain'))}</p><small>${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small><div class="sc-library-workspace__citation"><select data-citation-format="${escapeHtml(item.id)}">${htmlOptions(Object.values(citationFormats), 'apa')}</select><button type="button" data-copy-citation="${escapeHtml(item.id)}">Copy citation</button></div></div><div class="sc-library-workspace__row-actions">${item.url ? `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">Open</a>` : ''}<button type="button" data-edit-source="${escapeHtml(item.id)}">Edit</button><button type="button" data-delete-source="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No outside sources have been saved.</p>'}
        </div>
      </section>`;
  };

  const portabilityHtml = () => `
    <section class="sc-library-workspace__section">
      <header><div><p class="sc-library__eyebrow">Portable research data</p><h3>Import and export</h3></div></header>
      <div class="sc-library-workspace__portability-grid">
        <article><h4>Export workspace</h4><p>Download collections, saved records, notes, sources, relationships, and citations as a versioned JSON manifest.</p><button type="button" data-export-workspace>Download JSON export</button></article>
        <article><h4>Import workspace</h4><p>Replace this browser’s current workspace with a compatible Sustainable Catalyst Library export.</p><label class="sc-library-workspace__file"><span>Select JSON export</span><input type="file" accept="application/json,.json" data-import-workspace></label></article>
        <article><h4>Copy JSON</h4><p>Copy the complete workspace manifest for inspection or transfer into another system.</p><button type="button" data-copy-workspace>Copy workspace JSON</button></article>
        <article class="sc-library-workspace__danger"><h4>Clear local workspace</h4><p>Delete all locally stored collections, saved records, notes, and sources from this browser.</p><button type="button" data-clear-workspace>Clear all local data</button></article>
      </div>
      <details class="sc-library-workspace__manifest"><summary>Export manifest details</summary><pre>${escapeHtml(JSON.stringify({ schema: workspace.schema, version: workspace.version, updatedAt: workspace.updatedAt, counts: { collections: workspace.collections.length, savedRecords: workspace.savedRecords.length, notes: workspace.notes.length, sources: workspace.sources.length } }, null, 2))}</pre></details>
    </section>`;

  const controllers = roots.map((root) => {
    const panel = root.querySelector('[data-library-workspace]');
    const content = root.querySelector('[data-workspace-content]');
    const notice = root.querySelector('[data-workspace-notice]');
    const count = root.querySelector('[data-workspace-count]');
    let activeTab = 'overview';
    let editId = '';
    let noteSeed = {};

    const showNotice = (message, type = 'success') => {
      if (!notice) return;
      notice.hidden = false;
      notice.className = `sc-library-workspace__notice is-${type}`;
      notice.textContent = message;
      window.setTimeout(() => { if (notice.textContent === message) notice.hidden = true; }, 4500);
    };

    const render = () => {
      if (count) count.textContent = `${itemCount()} ${itemCount() === 1 ? 'item' : 'items'}`;
      root.querySelectorAll('[data-workspace-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.workspaceTab === activeTab));
      if (!content) return;
      if (!storageAvailable) showNotice(strings.storageError || 'Browser storage is unavailable.', 'error');
      if (activeTab === 'collections') content.innerHTML = collectionsHtml(editId);
      else if (activeTab === 'notes') content.innerHTML = notesHtml(editId, noteSeed);
      else if (activeTab === 'sources') content.innerHTML = sourcesHtml(editId);
      else if (activeTab === 'portability') content.innerHTML = portabilityHtml();
      else content.innerHTML = overviewHtml();
    };

    const open = (tab = 'overview') => {
      activeTab = tab;
      editId = '';
      if (panel) panel.hidden = false;
      if (root.dataset.workspaceStandalone !== '1') document.documentElement.classList.add('sc-library-workspace-open');
      render();
      panel?.querySelector('.sc-library-workspace__close, [data-workspace-tab]')?.focus();
    };
    const close = () => {
      if (root.dataset.workspaceStandalone === '1') return;
      if (panel) panel.hidden = true;
      document.documentElement.classList.remove('sc-library-workspace-open');
    };
    const setTab = (tab) => { activeTab = tab; editId = ''; noteSeed = {}; render(); };

    root.addEventListener('click', async (event) => {
      const openButton = event.target.closest('[data-workspace-open]');
      if (openButton) { open(openButton.dataset.workspaceOpen || 'overview'); return; }
      if (event.target.closest('[data-workspace-close]')) { close(); return; }
      const tabButton = event.target.closest('[data-workspace-tab]');
      if (tabButton) { setTab(tabButton.dataset.workspaceTab || 'overview'); return; }
      const quick = event.target.closest('[data-workspace-quick]');
      if (quick) {
        const kind = quick.dataset.workspaceQuick;
        if (kind === 'collection') open('collections');
        else if (kind === 'source') open('sources');
        else open('notes');
        return;
      }
      if (event.target.closest('[data-cancel-edit]')) { editId = ''; noteSeed = {}; render(); return; }

      const exportCollection = event.target.closest('[data-export-collection]');
      if (exportCollection) {
        const bundle = scopedCollectionExport(exportCollection.dataset.exportCollection);
        if (bundle) {
          const blob = new Blob([JSON.stringify(bundle, null, 2)], { type: 'application/json' });
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url; link.download = `sustainable-catalyst-collection-${slug(bundle.exportScope.title)}-${today()}.json`;
          document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
        }
        return;
      }
      const editCollection = event.target.closest('[data-edit-collection]');
      if (editCollection) { editId = editCollection.dataset.editCollection; activeTab = 'collections'; render(); return; }
      const deleteCollection = event.target.closest('[data-delete-collection]');
      if (deleteCollection) {
        const id = deleteCollection.dataset.deleteCollection;
        workspace.collections = workspace.collections.filter((item) => item.id !== id);
        workspace.savedRecords.forEach((item) => { item.collectionIds = recordCollectionIds(item).filter((value) => value !== id); if (!item.collectionIds.length) item.collectionIds = ['collection_inbox']; });
        workspace.notes.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); });
        workspace.sources.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); });
        persist(); render(); return;
      }
      const noteSavedRecord = event.target.closest('[data-note-saved-record]');
      if (noteSavedRecord) {
        const record = recordById(noteSavedRecord.dataset.noteSavedRecord);
        if (record) { noteSeed = { title: `Notes on ${record.title}`, recordId: String(record.recordId), collectionIds: recordCollectionIds(record) }; activeTab = 'notes'; editId = ''; render(); }
        return;
      }
      const deleteSavedRecord = event.target.closest('[data-delete-saved-record]');
      if (deleteSavedRecord) {
        const recordId = Number(deleteSavedRecord.dataset.deleteSavedRecord);
        workspace.savedRecords = workspace.savedRecords.filter((item) => Number(item.recordId) !== recordId);
        workspace.notes.forEach((item) => { if (Number(item.recordId) === recordId) item.recordId = ''; });
        persist(); render(); return;
      }
      const editNote = event.target.closest('[data-edit-note]');
      if (editNote) { editId = editNote.dataset.editNote; activeTab = 'notes'; render(); return; }
      const deleteNote = event.target.closest('[data-delete-note]');
      if (deleteNote) { workspace.notes = workspace.notes.filter((item) => item.id !== deleteNote.dataset.deleteNote); persist(); render(); return; }
      const editSource = event.target.closest('[data-edit-source]');
      if (editSource) { editId = editSource.dataset.editSource; activeTab = 'sources'; render(); return; }
      const deleteSource = event.target.closest('[data-delete-source]');
      if (deleteSource) {
        const id = deleteSource.dataset.deleteSource;
        workspace.sources = workspace.sources.filter((item) => item.id !== id);
        workspace.notes.forEach((item) => { if (item.sourceId === id) item.sourceId = ''; });
        persist(); render(); return;
      }
      const copyCitation = event.target.closest('[data-copy-citation]');
      if (copyCitation) {
        const source = sourceById(copyCitation.dataset.copyCitation);
        const select = root.querySelector(`[data-citation-format="${CSS.escape(copyCitation.dataset.copyCitation)}"]`);
        if (source) {
          try { await navigator.clipboard.writeText(citation(source, select?.value || 'apa')); showNotice(strings.copySuccess || 'Copied.'); }
          catch (error) { showNotice(strings.copyFailure || 'Copy failed.', 'error'); }
        }
        return;
      }
      if (event.target.closest('[data-export-workspace]')) {
        const blob = new Blob([JSON.stringify(workspace, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `sustainable-catalyst-library-workspace-${today()}.json`;
        document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
        return;
      }
      if (event.target.closest('[data-copy-workspace]')) {
        try { await navigator.clipboard.writeText(JSON.stringify(workspace, null, 2)); showNotice(strings.copySuccess || 'Copied.'); }
        catch (error) { showNotice(strings.copyFailure || 'Copy failed.', 'error'); }
        return;
      }
      if (event.target.closest('[data-clear-workspace]')) {
        if (window.confirm(strings.confirmClear || 'Clear all local workspace data?')) { workspace = initialWorkspace(); persist(); renderAll(); }
      }
    });

    root.addEventListener('submit', (event) => {
      event.preventDefault();
      const form = event.target;
      const data = new FormData(form);
      if (form.matches('[data-collection-form]')) {
        const id = words(data.get('id'));
        const existing = collectionById(id);
        const item = { id: id || uid('collection'), title: words(data.get('title')), description: words(data.get('description')), createdAt: existing?.createdAt || now(), updatedAt: now() };
        if (existing) Object.assign(existing, item); else workspace.collections.push(item);
        persist(); editId = ''; renderAll(); return;
      }
      if (form.matches('[data-note-form]')) {
        const id = words(data.get('id'));
        const existing = workspace.notes.find((item) => item.id === id);
        const item = {
          id: id || uid('note'), type: words(data.get('type')) || 'note', title: words(data.get('title')), body: words(data.get('body')),
          recordId: words(data.get('recordId')), sourceId: words(data.get('sourceId')), tags: listFromText(data.get('tags')),
          collectionIds: data.getAll('collectionIds').map(String), createdAt: existing?.createdAt || now(), updatedAt: now(),
        };
        if (!item.collectionIds.length) item.collectionIds = ['collection_inbox'];
        if (existing) Object.assign(existing, item); else workspace.notes.push(item);
        persist(); editId = ''; noteSeed = {}; renderAll(); showNotice('Note saved.'); return;
      }
      if (form.matches('[data-source-form]')) {
        const id = words(data.get('id'));
        const existing = sourceById(id);
        const candidate = {
          id: id || uid('source'), type: words(data.get('type')) || 'custom', title: words(data.get('title')), creators: words(data.get('creators')),
          organization: words(data.get('organization')), url: words(data.get('url')), doi: words(data.get('doi')), isbn: words(data.get('isbn')),
          publisher: words(data.get('publisher')), publication_date: words(data.get('publication_date')), access_date: words(data.get('access_date')),
          edition: words(data.get('edition')), chapter: words(data.get('chapter')), pages: words(data.get('pages')), physical_location: words(data.get('physical_location')),
          description: words(data.get('description')), notes: words(data.get('notes')), tags: listFromText(data.get('tags')),
          collectionIds: data.getAll('collectionIds').map(String), createdAt: existing?.createdAt || now(), updatedAt: now(),
        };
        if (!candidate.collectionIds.length) candidate.collectionIds = ['collection_inbox'];
        const duplicate = workspace.sources.find((item) => item.id !== candidate.id && ((candidate.doi && item.doi === candidate.doi) || (candidate.isbn && item.isbn === candidate.isbn && item.title.toLowerCase() === candidate.title.toLowerCase()) || (candidate.url && item.url === candidate.url) || (item.title.toLowerCase() === candidate.title.toLowerCase() && authorText(item).toLowerCase() === authorText(candidate).toLowerCase())));
        if (duplicate && !window.confirm(`A likely duplicate source already exists: “${duplicate.title}”. Save another copy?`)) return;
        if (existing) Object.assign(existing, candidate); else workspace.sources.push(candidate);
        persist(); editId = ''; renderAll(); showNotice('Source saved.');
      }
    });

    root.addEventListener('change', async (event) => {
      const recordCollection = event.target.closest('[data-record-collection]');
      if (recordCollection) {
        const record = recordById(recordCollection.dataset.recordCollection);
        if (record) {
          const selected = Array.from(root.querySelectorAll(`[data-record-collection="${CSS.escape(String(record.recordId))}"]:checked`)).map((input) => input.value);
          record.collectionIds = selected.length ? selected : ['collection_inbox'];
          record.updatedAt = now(); persist(); renderAll();
        }
        return;
      }
      const input = event.target.closest('[data-import-workspace]');
      if (!input?.files?.length) return;
      try {
        const parsed = JSON.parse(await input.files[0].text());
        if (!window.confirm('Importing will replace the current local workspace. Continue?')) return;
        workspace = sanitizeImported(parsed);
        if (!workspace.collections.length) workspace.collections.push(initialWorkspace().collections[0]);
        persist(); renderAll(); showNotice(strings.importSuccess || 'Workspace imported.');
      } catch (error) {
        showNotice(strings.importError || 'Invalid workspace export.', 'error');
      } finally { input.value = ''; }
    });

    return { root, open, close, render, setTab, seedNote(seed) { noteSeed = seed || {}; activeTab = 'notes'; editId = ''; open('notes'); } };
  });

  const renderAll = () => controllers.forEach((controller) => controller.render());
  const controllerForEvent = (event) => {
    const library = event.target?.closest?.('[data-sc-library]');
    const workspaceRoot = library?.querySelector?.('[data-sc-library-workspace-root]');
    return controllers.find((controller) => controller.root === workspaceRoot) || controllers[0];
  };

  document.addEventListener('sc-library-save-record', (event) => {
    const record = event.detail?.record;
    if (!record?.id) return;
    const existing = recordById(record.id);
    const saved = {
      recordId: Number(record.id), recordIdentifier: words(record.record_identifier), title: words(record.title), url: words(record.url),
      typeLabel: words(record.type_label || 'Publication'), excerpt: words(record.excerpt), resources: record.resources || {},
      categories: record.categories || [], concepts: record.concepts || [], series: record.series || null,
      collectionIds: existing ? recordCollectionIds(existing) : ['collection_inbox'], createdAt: existing?.createdAt || now(), updatedAt: now(),
    };
    if (existing) Object.assign(existing, saved); else workspace.savedRecords.push(saved);
    persist(); renderAll();
    const controller = controllerForEvent(event);
    controller.open('overview');
  });

  document.addEventListener('sc-library-new-note-for-record', (event) => {
    const record = event.detail?.record;
    if (!record?.id) return;
    const existing = recordById(record.id);
    if (!existing) {
      workspace.savedRecords.push({ recordId: Number(record.id), recordIdentifier: words(record.record_identifier), title: words(record.title), url: words(record.url), typeLabel: words(record.type_label || 'Publication'), excerpt: words(record.excerpt), resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null, collectionIds: ['collection_inbox'], createdAt: now(), updatedAt: now() });
      persist();
    }
    controllerForEvent(event).seedNote({ title: `Notes on ${record.title}`, recordId: String(record.id), collectionIds: ['collection_inbox'] });
  });

  window.addEventListener('storage', (event) => { if (event.key === storageKey) { load(); renderAll(); } });
  window.addEventListener('sc-library-workspace-updated', renderAll);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') controllers.forEach((controller) => controller.close()); });
  renderAll();
})();
