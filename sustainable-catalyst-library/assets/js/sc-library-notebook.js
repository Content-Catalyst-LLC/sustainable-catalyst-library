(() => {
  'use strict';

  const shared = window.SCNotebookShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const schema = shared.schema || 'sc-library-workspace/1.6';
  const legacySchema = shared.legacySchema || 'sc-library-workspace/1.6';
  const legacySchemas = Array.isArray(shared.legacySchemas) ? shared.legacySchemas : [legacySchema, 'sc-library-workspace/1.0'];
  const version = shared.version || '1.10.0';
  const strings = shared.strings || {};
  const sourceTypes = shared.sourceTypes || {};
  const citationFormats = shared.citationFormats || {};
  const matrixTemplates = shared.matrixTemplates || {};
  const matrixStatuses = shared.matrixStatuses || {};
  const matrixEnabled = shared.matrixEnabled !== false;
  const boardsEnabled = shared.boardsEnabled !== false;
  const integrationsEnabled = shared.integrationsEnabled !== false;
  const annotationsEnabled = shared.annotationsEnabled !== false;
  const booksEnabled = shared.booksEnabled !== false;
  const defaultMatrixTemplate = shared.defaultMatrixTemplate || 'technical_translation';
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
      matrices: [],
      boards: [],
      handoffs: [],
      annotations: [],
      books: [],
    };
  };

  const sanitizeImported = (data) => {
    if (!data || typeof data !== 'object' || ![schema, ...legacySchemas].includes(data.schema)) throw new Error('Invalid schema');
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
      matrices: cleanArray(data.matrices),
      boards: cleanArray(data.boards),
      handoffs: cleanArray(data.handoffs),
      annotations: cleanArray(data.annotations),
      books: cleanArray(data.books),
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
  const matrixById = (id) => workspace.matrices.find((item) => item.id === id);
  const boardById = (id) => workspace.boards.find((item) => item.id === id);
  const recordCollectionIds = (record) => Array.isArray(record.collectionIds) && record.collectionIds.length ? record.collectionIds : ['collection_inbox'];
  const itemCount = () => workspace.savedRecords.length + workspace.notes.length + workspace.sources.length + workspace.matrices.length + workspace.boards.length + workspace.annotations.length + workspace.books.length + workspace.handoffs.length;
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
      matrices: workspace.matrices.filter((item) => (item.collectionIds || []).includes(collectionId)),
      boards: workspace.boards.filter((item) => (item.collectionIds || []).includes(collectionId)),
      handoffs: workspace.handoffs.filter((item) => (item.collectionIds || []).includes(collectionId)),
      annotations: workspace.annotations.filter((item) => (item.collectionIds || []).includes(collectionId)),
      books: workspace.books.filter((item) => (item.collectionIds || []).includes(collectionId)),
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
  const relatedMatrixOptions = (selected = '') => `<option value="">No attached matrix</option>${workspace.matrices.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.title)}</option>`).join('')}`;
  const relatedBoardOptions = (selected = '') => `<option value="">No attached board</option>${workspace.boards.map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.title)}</option>`).join('')}`;

  const metric = (label, value) => `<div class="sc-library-workspace__metric"><strong>${Number(value).toLocaleString()}</strong><span>${escapeHtml(label)}</span></div>`;
  const collectionNames = (ids = []) => ids.map((id) => collectionById(id)?.title).filter(Boolean);

  const overviewHtml = () => {
    const recent = [
      ...workspace.savedRecords.map((item) => ({ kind: 'Saved record', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.notes.map((item) => ({ kind: 'Note', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.sources.map((item) => ({ kind: typeLabel(item.type), title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.matrices.map((item) => ({ kind: 'Translation Matrix', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.boards.map((item) => ({ kind: item.type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.annotations.map((item) => ({ kind: 'Annotation', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.books.map((item) => ({ kind: 'Custom book', title: item.title, date: item.updatedAt || item.createdAt })),
      ...workspace.handoffs.map((item) => ({ kind: 'Connected tool handoff', title: item.context?.title || item.target || 'Application handoff', date: item.created_at || item.updatedAt || item.createdAt })),
    ].sort((a, b) => String(b.date).localeCompare(String(a.date))).slice(0, 8);
    return `
      <section class="sc-library-workspace__overview">
        <div class="sc-library-workspace__metrics">
          ${metric('Saved records', workspace.savedRecords.length)}
          ${metric('Collections', workspace.collections.length)}
          ${metric('Notes', workspace.notes.length)}
          ${metric('Sources', workspace.sources.length)}
          ${metric('Matrices', workspace.matrices.length)}
          ${metric('Boards', workspace.boards.length)}
          ${annotationsEnabled ? metric('Annotations', workspace.annotations.length) : ''}
          ${booksEnabled ? metric('Books', workspace.books.length) : ''}
          ${integrationsEnabled ? metric('Handoffs', workspace.handoffs.length) : ''}
        </div>
        <div class="sc-library-workspace__quick-grid">
          <button type="button" data-workspace-quick="collection"><strong>Create collection</strong><span>Group publications, notes, and sources.</span></button>
          <button type="button" data-workspace-quick="note"><strong>Write a note</strong><span>Capture a question, quotation, claim, or synthesis.</span></button>
          <button type="button" data-workspace-quick="source"><strong>Add a source</strong><span>Store a link, book, report, dataset, or video timestamp.</span></button>
          ${matrixEnabled ? '<button type="button" data-workspace-quick="matrix"><strong>Build a translation matrix</strong><span>Translate concepts across notation, code, data, validation, and systems meaning.</span></button>' : ''}
          ${boardsEnabled ? '<button type="button" data-workspace-quick="whiteboard"><strong>Open a Whiteboard</strong><span>Map concepts, sources, claims, evidence, and systems relationships.</span></button><button type="button" data-workspace-quick="chalkboard"><strong>Open a Chalkboard</strong><span>Work through equations, derivations, code, and handwritten technical reasoning.</span></button>' : ''}
          ${annotationsEnabled ? '<button type="button" data-workspace-quick="annotation"><strong>Open Annotation Studio</strong><span>Add handwritten notes, highlights, shapes, and anchored comments to research material.</span></button>' : ''}
          ${booksEnabled ? '<button type="button" data-workspace-quick="book"><strong>Build a custom book</strong><span>Arrange publications and research artifacts into a PDF-ready edition.</span></button>' : ''}
          ${integrationsEnabled ? '<button type="button" data-workspace-tab="integrations"><strong>Connect a research tool</strong><span>Prepare a source-aware Workbench, Decision Studio, or Site Intelligence handoff.</span></button>' : ''}
          <button type="button" data-workspace-tab="portability"><strong>Export workspace</strong><span>Create a portable JSON research manifest.</span></button>
        </div>
        <section class="sc-library-workspace__recent-work">
          <h3>Recent research activity</h3>
          ${recent.length ? `<div>${recent.map((item) => `<article><span>${escapeHtml(item.kind)}</span><strong>${escapeHtml(item.title)}</strong><time>${escapeHtml(formatDate(item.date))}</time></article>`).join('')}</div>` : '<p>No saved research yet. Save a knowledge record, add a source, write a note, build a matrix, open a visual board, create an annotation, or begin a custom book.</p>'}
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
            const count = workspace.savedRecords.filter((record) => recordCollectionIds(record).includes(item.id)).length + workspace.notes.filter((note) => (note.collectionIds || []).includes(item.id)).length + workspace.sources.filter((source) => (source.collectionIds || []).includes(item.id)).length + workspace.matrices.filter((matrix) => (matrix.collectionIds || []).includes(item.id)).length + workspace.boards.filter((board) => (board.collectionIds || []).includes(item.id)).length + workspace.annotations.filter((annotation) => (annotation.collectionIds || []).includes(item.id)).length + workspace.handoffs.filter((handoff) => (handoff.collectionIds || []).includes(item.id)).length + workspace.books.filter((book) => (book.collectionIds || []).includes(item.id)).length;
            return `<article><div><span>Collection</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.description || 'No description')}</p><small>${count} linked items</small></div><div class="sc-library-workspace__row-actions"><button type="button" data-export-collection="${escapeHtml(item.id)}">Export</button><button type="button" data-edit-collection="${escapeHtml(item.id)}">Edit</button>${item.id !== 'collection_inbox' ? `<button type="button" data-delete-collection="${escapeHtml(item.id)}">Delete</button>` : ''}</div></article>`;
          }).join('')}
        </div>
        <section class="sc-library-workspace__saved-records">
          <h3>Saved Library records</h3>
          ${workspace.savedRecords.length ? workspace.savedRecords.map((record) => `<article><div><span>${escapeHtml(record.typeLabel || 'Publication')}</span><h4><a href="${escapeHtml(record.url)}">${escapeHtml(record.title)}</a></h4><p>${escapeHtml(record.excerpt || '')}</p><div class="sc-library-workspace__checks">${workspace.collections.map((collection) => `<label class="sc-library-workspace__check"><input type="checkbox" data-record-collection="${Number(record.recordId)}" value="${escapeHtml(collection.id)}" ${recordCollectionIds(record).includes(collection.id) ? 'checked' : ''}> <span>${escapeHtml(collection.title)}</span></label>`).join('')}</div></div><div class="sc-library-workspace__row-actions"><button type="button" data-note-saved-record="${Number(record.recordId)}">Write note</button>${matrixEnabled ? `<button type="button" data-matrix-saved-record="${Number(record.recordId)}">Translate</button>` : ''}${boardsEnabled ? `<button type="button" data-board-saved-record="${Number(record.recordId)}" data-board-type="whiteboard">Whiteboard</button><button type="button" data-board-saved-record="${Number(record.recordId)}" data-board-type="chalkboard">Chalkboard</button>` : ''}${annotationsEnabled ? `<button type="button" data-annotation-saved-record="${Number(record.recordId)}">Annotate</button>` : ''}${booksEnabled ? `<button type="button" data-book-saved-record="${Number(record.recordId)}">Add to Book</button>` : ''}<button type="button" data-delete-saved-record="${Number(record.recordId)}">Remove</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No Library records have been saved yet.</p>'}
        </section>
      </section>`;
  };

  const notesHtml = (editId = '', seed = {}) => {
    const found = workspace.notes.find((item) => item.id === editId);
    const editing = found || {
      id: '', type: seed.type || 'note', title: seed.title || '', body: seed.body || '', recordId: seed.recordId || '', sourceId: seed.sourceId || '', matrixId: seed.matrixId || '', boardId: seed.boardId || '', annotationId: seed.annotationId || '', tags: [], collectionIds: seed.collectionIds || ['collection_inbox']
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
          <input type="hidden" name="annotationId" value="${escapeHtml(editing.annotationId || '')}">
          <div class="sc-library-workspace__form-grid">
            <label><span>Note type</span><select name="type">${htmlOptions(noteTypes, editing.type)}</select></label>
            <label><span>Title</span><input required name="title" value="${escapeHtml(editing.title)}" placeholder="Key question or observation"></label>
          </div>
          <label><span>Note</span><textarea required name="body" rows="7" placeholder="Write your research note here">${escapeHtml(editing.body)}</textarea></label>
          <div class="sc-library-workspace__form-grid sc-library-workspace__form-grid--three">
            <label><span>Attached Library record</span><select name="recordId">${relatedRecordOptions(editing.recordId)}</select></label>
            <label><span>Attached outside source</span><select name="sourceId">${relatedSourceOptions(editing.sourceId)}</select></label>
            <label><span>Attached translation matrix</span><select name="matrixId">${relatedMatrixOptions(editing.matrixId)}</select></label>
          </div>
          <label><span>Attached Whiteboard or Chalkboard</span><select name="boardId">${relatedBoardOptions(editing.boardId)}</select></label>
          <label><span>Tags</span><input name="tags" value="${escapeHtml((editing.tags || []).join(', '))}" placeholder="feedback loops, evidence, review"></label>
          <fieldset><legend>Add to collections</legend><div class="sc-library-workspace__checks">${collectionOptions(editing.collectionIds || ['collection_inbox'])}</div></fieldset>
          <div class="sc-library-workspace__form-actions"><button type="submit">${editing.id ? 'Update note' : 'Save note'}</button>${editing.id ? '<button type="button" data-cancel-edit>Cancel</button>' : ''}</div>
        </form>
        <div class="sc-library-workspace__list">
          ${workspace.notes.length ? workspace.notes.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `<article><div><span>${escapeHtml(item.type.replace(/_/g, ' '))}</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.body)}</p><small>${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small></div><div class="sc-library-workspace__row-actions">${annotationsEnabled ? `<button type="button" data-annotate-note="${escapeHtml(item.id)}">Annotate</button>` : ''}<button type="button" data-edit-note="${escapeHtml(item.id)}">Edit</button><button type="button" data-delete-note="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No notes have been saved.</p>'}
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
          ${workspace.sources.length ? workspace.sources.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `<article><div><span>${escapeHtml(typeLabel(item.type))}</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.description || item.notes || citation(item, 'plain'))}</p><small>${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small><div class="sc-library-workspace__citation"><select data-citation-format="${escapeHtml(item.id)}">${htmlOptions(Object.values(citationFormats), 'apa')}</select><button type="button" data-copy-citation="${escapeHtml(item.id)}">Copy citation</button></div></div><div class="sc-library-workspace__row-actions">${item.url ? `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">Open</a>` : ''}${annotationsEnabled ? `<button type="button" data-annotate-source="${escapeHtml(item.id)}">Annotate</button>` : ''}<button type="button" data-edit-source="${escapeHtml(item.id)}">Edit</button><button type="button" data-delete-source="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No outside sources have been saved.</p>'}
        </div>
      </section>`;
  };


  const clone = (value) => JSON.parse(JSON.stringify(value));
  const matrixTemplate = (id) => matrixTemplates[id] || matrixTemplates[defaultMatrixTemplate] || Object.values(matrixTemplates)[0] || {
    id: 'technical_translation', label: 'Technical Translation', description: '',
    columns: ['Plain language', 'Formal form', 'Computational form', 'Systems interpretation'],
    rows: ['Concept', 'Variables and units', 'Assumptions', 'Procedure', 'Validation', 'Sources'],
  };
  const statusLabel = (status) => matrixStatuses[status]?.label || status || 'Draft';
  const matrixStatusOptions = (selected = 'draft') => Object.values(matrixStatuses).map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('');
  const matrixTemplateOptions = (selected = defaultMatrixTemplate) => Object.values(matrixTemplates).map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('');
  const makeMatrix = (templateId = defaultMatrixTemplate, seed = {}) => {
    const template = matrixTemplate(templateId);
    const createdAt = now();
    const columns = (template.columns || []).map((label) => ({ id: uid('column'), label }));
    const rows = (template.rows || []).map((label) => ({ id: uid('row'), label, cells: {} }));
    rows.forEach((row) => columns.forEach((column) => { row.cells[column.id] = { value: '', status: 'draft', sourceRef: '' }; }));
    return {
      id: seed.id || '',
      title: seed.title || '',
      description: seed.description || template.description || '',
      templateId: template.id,
      status: seed.status || 'draft',
      recordId: seed.recordId || '',
      sourceId: seed.sourceId || '',
      collectionIds: seed.collectionIds || ['collection_inbox'],
      tags: seed.tags || [],
      notes: seed.notes || '',
      columns,
      rows,
      createdAt: seed.createdAt || createdAt,
      updatedAt: createdAt,
    };
  };
  const normalizeMatrix = (matrix) => {
    const normalized = clone(matrix || makeMatrix());
    normalized.columns = Array.isArray(normalized.columns) ? normalized.columns : [];
    normalized.rows = Array.isArray(normalized.rows) ? normalized.rows : [];
    normalized.collectionIds = Array.isArray(normalized.collectionIds) && normalized.collectionIds.length ? normalized.collectionIds : ['collection_inbox'];
    normalized.tags = Array.isArray(normalized.tags) ? normalized.tags : [];
    normalized.status = normalized.status || 'draft';
    normalized.templateId = normalized.templateId || defaultMatrixTemplate;
    normalized.columns.forEach((column) => { column.id = column.id || uid('column'); column.label = words(column.label) || 'Column'; });
    normalized.rows.forEach((row) => {
      row.id = row.id || uid('row'); row.label = words(row.label) || 'Row'; row.cells = row.cells && typeof row.cells === 'object' ? row.cells : {};
      normalized.columns.forEach((column) => {
        const cell = row.cells[column.id];
        row.cells[column.id] = cell && typeof cell === 'object' ? { value: words(cell.value), status: cell.status || 'draft', sourceRef: words(cell.sourceRef) } : { value: '', status: 'draft', sourceRef: '' };
      });
    });
    return normalized;
  };
  const matrixCell = (matrix, rowId, columnId) => matrix.rows.find((row) => row.id === rowId)?.cells?.[columnId];
  const matrixValidationSummary = (matrix) => {
    const counts = {};
    (matrix.rows || []).forEach((row) => (matrix.columns || []).forEach((column) => {
      const cell = row.cells?.[column.id] || {};
      const key = cell.status || 'draft'; counts[key] = (counts[key] || 0) + 1;
    }));
    return counts;
  };
  const matrixCsv = (matrix) => {
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const headers = ['Knowledge layer', ...(matrix.columns || []).map((column) => column.label)];
    const lines = [headers.map(quote).join(',')];
    (matrix.rows || []).forEach((row) => lines.push([row.label, ...(matrix.columns || []).map((column) => row.cells?.[column.id]?.value || '')].map(quote).join(',')));
    return lines.join('\n');
  };
  const matrixPrintableHtml = (matrix) => {
    const record = recordById(matrix.recordId);
    const source = sourceById(matrix.sourceId);
    const tableHead = (matrix.columns || []).map((column) => `<th>${escapeHtml(column.label)}</th>`).join('');
    const tableRows = (matrix.rows || []).map((row) => `<tr><th>${escapeHtml(row.label)}</th>${(matrix.columns || []).map((column) => {
      const cell = row.cells?.[column.id] || {};
      return `<td><div>${escapeHtml(cell.value || '')}</div><small>${escapeHtml(statusLabel(cell.status))}${cell.sourceRef ? ` · ${escapeHtml(cell.sourceRef)}` : ''}</small></td>`;
    }).join('')}</tr>`).join('');
    return `<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(matrix.title || 'Technical Translation Matrix')}</title><style>@page{size:landscape;margin:14mm}body{font-family:Arial,sans-serif;color:#111}h1{font-size:22px;margin:0 0 8px}p{line-height:1.5}.meta{font-size:11px;color:#555;margin-bottom:14px}.status{display:inline-block;padding:3px 7px;border:1px solid #721019}.table-wrap{overflow:visible}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10px}th,td{border:1px solid #777;padding:7px;vertical-align:top;overflow-wrap:anywhere}thead th{background:#111;color:#fff}tbody th{background:#f2eee5;text-align:left;width:15%}td small{display:block;margin-top:6px;color:#666;font-size:8px}.provenance{margin-top:15px;border-top:1px solid #777;padding-top:10px;font-size:10px}@media print{button{display:none}}</style></head><body><h1>${escapeHtml(matrix.title || 'Technical Translation Matrix')}</h1><p>${escapeHtml(matrix.description || '')}</p><div class="meta"><span class="status">${escapeHtml(statusLabel(matrix.status))}</span> · Generated ${escapeHtml(formatDate(now()))}</div><div class="table-wrap"><table><thead><tr><th>Knowledge layer</th>${tableHead}</tr></thead><tbody>${tableRows}</tbody></table></div><div class="provenance"><strong>Provenance</strong><br>${record ? `Library record: ${escapeHtml(record.title)} (${escapeHtml(record.recordIdentifier || String(record.recordId))})<br>` : ''}${source ? `Outside source: ${escapeHtml(source.title)}<br>` : ''}${matrix.notes ? `Notes: ${escapeHtml(matrix.notes)}<br>` : ''}Matrix ID: ${escapeHtml(matrix.id || 'unsaved')} · Workspace schema: ${escapeHtml(schema)}</div><script>window.addEventListener('load',()=>window.print())<\/script></body></html>`;
  };
  const downloadText = (content, filename, type = 'text/plain') => {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a'); link.href = url; link.download = filename;
    document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
  };
  const matrixEditorTable = (matrix) => `
    <div class="sc-library-matrix-editor__toolbar">
      <button type="button" data-matrix-add-row>Add row</button>
      <button type="button" data-matrix-add-column>Add column</button>
      <span>${matrix.rows.length} rows × ${matrix.columns.length} columns</span>
    </div>
    <div class="sc-library-matrix-editor__scroll">
      <table class="sc-library-matrix-editor" data-matrix-grid>
        <thead><tr><th class="sc-library-matrix-editor__corner">Knowledge layer</th>${matrix.columns.map((column) => `<th data-matrix-column="${escapeHtml(column.id)}"><input data-matrix-column-label="${escapeHtml(column.id)}" value="${escapeHtml(column.label)}" aria-label="Column label"><button type="button" data-matrix-remove-column="${escapeHtml(column.id)}" aria-label="Remove ${escapeHtml(column.label)}">×</button></th>`).join('')}</tr></thead>
        <tbody>${matrix.rows.map((row) => `<tr data-matrix-row="${escapeHtml(row.id)}"><th><input data-matrix-row-label="${escapeHtml(row.id)}" value="${escapeHtml(row.label)}" aria-label="Row label"><button type="button" data-matrix-remove-row="${escapeHtml(row.id)}" aria-label="Remove ${escapeHtml(row.label)}">×</button></th>${matrix.columns.map((column) => { const cell = row.cells[column.id] || { value: '', status: 'draft', sourceRef: '' }; return `<td><textarea rows="5" data-matrix-cell="${escapeHtml(row.id)}|${escapeHtml(column.id)}" placeholder="Enter translation">${escapeHtml(cell.value)}</textarea><div class="sc-library-matrix-cell-meta"><select data-matrix-cell-status="${escapeHtml(row.id)}|${escapeHtml(column.id)}" aria-label="Cell validation status">${matrixStatusOptions(cell.status)}</select><input data-matrix-cell-source="${escapeHtml(row.id)}|${escapeHtml(column.id)}" value="${escapeHtml(cell.sourceRef)}" placeholder="Source, page, URL, or note ID" aria-label="Cell source reference"></div></td>`; }).join('')}</tr>`).join('')}</tbody>
      </table>
    </div>`;
  const matricesHtml = (draft) => {
    if (!matrixEnabled) return '<p class="sc-library-workspace__empty">The Technical Translation Matrix is disabled.</p>';
    const editing = normalizeMatrix(draft || makeMatrix(defaultMatrixTemplate));
    const summary = matrixValidationSummary(editing);
    return `
      <section class="sc-library-workspace__section sc-library-matrix-studio">
        <header><div><p class="sc-library__eyebrow">Technical translation</p><h3>Technical Translation Matrix</h3><p>Translate the same idea across language, notation, code, data structure, assumptions, validation, and systems meaning.</p></div></header>
        <form class="sc-library-workspace__form sc-library-matrix-form" data-matrix-form>
          <input type="hidden" name="id" value="${escapeHtml(editing.id)}">
          <div class="sc-library-workspace__form-grid sc-library-workspace__form-grid--three">
            <label><span>Template</span><select data-matrix-template name="templateId">${matrixTemplateOptions(editing.templateId)}</select></label>
            <label><span>Matrix status</span><select data-matrix-field="status" name="status">${matrixStatusOptions(editing.status)}</select></label>
            <label><span>Title</span><input required data-matrix-field="title" name="title" value="${escapeHtml(editing.title)}" placeholder="Discrete Population Growth"></label>
          </div>
          <label><span>Description or research question</span><textarea data-matrix-field="description" name="description" rows="3">${escapeHtml(editing.description)}</textarea></label>
          <div class="sc-library-workspace__form-grid">
            <label><span>Attached Library record</span><select data-matrix-field="recordId" name="recordId">${relatedRecordOptions(editing.recordId)}</select></label>
            <label><span>Attached outside source</span><select data-matrix-field="sourceId" name="sourceId">${relatedSourceOptions(editing.sourceId)}</select></label>
          </div>
          <label><span>Tags</span><input data-matrix-field="tagsText" name="tags" value="${escapeHtml((editing.tags || []).join(', '))}" placeholder="growth model, validation, Python"></label>
          <label><span>Matrix notes and boundaries</span><textarea data-matrix-field="notes" name="notes" rows="3" placeholder="Record uncertainties, exclusions, and review requirements.">${escapeHtml(editing.notes)}</textarea></label>
          <fieldset><legend>Add to collections</legend><div class="sc-library-workspace__checks">${collectionOptions(editing.collectionIds)}</div></fieldset>
          ${matrixEditorTable(editing)}
          <div class="sc-library-matrix-validation"><strong>Cell states</strong>${Object.entries(summary).map(([key, value]) => `<span class="is-${escapeHtml(key)}">${escapeHtml(statusLabel(key))}: ${Number(value)}</span>`).join('')}</div>
          <div class="sc-library-workspace__form-actions"><button type="submit">${editing.id ? 'Update matrix' : 'Save matrix'}</button>${editing.id ? '<button type="button" data-cancel-edit>Cancel</button>' : ''}</div>
        </form>
        <div class="sc-library-workspace__list sc-library-matrix-list">
          ${workspace.matrices.length ? workspace.matrices.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => { const counts = matrixValidationSummary(item); return `<article><div><span>${escapeHtml(matrixTemplate(item.templateId).label || 'Translation Matrix')}</span><h4>${escapeHtml(item.title || 'Untitled matrix')}</h4><p>${escapeHtml(item.description || '')}</p><small>${escapeHtml(statusLabel(item.status))} · ${item.rows?.length || 0} rows × ${item.columns?.length || 0} columns · ${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small><div class="sc-library-matrix-list__states">${Object.entries(counts).map(([key, value]) => `<span class="is-${escapeHtml(key)}">${escapeHtml(statusLabel(key))}: ${Number(value)}</span>`).join('')}</div></div><div class="sc-library-workspace__row-actions"><button type="button" data-edit-matrix="${escapeHtml(item.id)}">Edit</button><button type="button" data-note-matrix="${escapeHtml(item.id)}">Write note</button>${boardsEnabled ? `<button type="button" data-board-matrix="${escapeHtml(item.id)}" data-board-type="whiteboard">Whiteboard</button><button type="button" data-board-matrix="${escapeHtml(item.id)}" data-board-type="chalkboard">Chalkboard</button>` : ''}${annotationsEnabled ? `<button type="button" data-annotate-matrix="${escapeHtml(item.id)}">Annotate</button>` : ''}<button type="button" data-export-matrix-json="${escapeHtml(item.id)}">JSON</button><button type="button" data-export-matrix-csv="${escapeHtml(item.id)}">CSV</button><button type="button" data-print-matrix="${escapeHtml(item.id)}">PDF / Print</button><button type="button" data-delete-matrix="${escapeHtml(item.id)}">Delete</button></div></article>`; }).join('') : '<p class="sc-library-workspace__empty">No Technical Translation Matrices have been saved.</p>'}
        </div>
      </section>`;
  };


  const boardsHtml = () => {
    if (!boardsEnabled) return '<p class="sc-library-workspace__empty">Whiteboards and Chalkboards are disabled.</p>';
    return `
      <section class="sc-library-workspace__section sc-library-board-notebook">
        <header><div><p class="sc-library__eyebrow">Visual research</p><h3>Whiteboards and Chalkboards</h3><p>Map knowledge, connect evidence, draw by hand, and move Library records, sources, notes, and matrices into an editable visual workspace.</p></div></header>
        <div class="sc-library-workspace__quick-grid">
          <button type="button" data-create-board="whiteboard"><strong>New Whiteboard</strong><span>Concept maps, evidence maps, systems maps, and research synthesis.</span></button>
          <button type="button" data-create-board="chalkboard"><strong>New Chalkboard</strong><span>Equations, derivations, code logic, validation, and handwritten technical work.</span></button>
          <button type="button" data-open-board-library><strong>Board library</strong><span>Browse and open every locally saved visual research board.</span></button>
        </div>
        <div class="sc-library-workspace__list sc-library-board-notebook__list">
          ${workspace.boards.length ? workspace.boards.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `<article><div><span>${escapeHtml(item.type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard')}</span><h4>${escapeHtml(item.title || 'Untitled board')}</h4><p>${escapeHtml(item.description || '')}</p><small>${item.nodes?.length || 0} cards · ${item.edges?.length || 0} relationships · ${item.strokes?.length || 0} ink strokes · ${escapeHtml(collectionNames(item.collectionIds).join(' · ') || 'Unfiled')} · Updated ${escapeHtml(formatDate(item.updatedAt))}</small><div class="sc-library-workspace__checks">${workspace.collections.map((collection) => `<label class="sc-library-workspace__check"><input type="checkbox" data-board-collection-id="${escapeHtml(item.id)}" value="${escapeHtml(collection.id)}" ${(item.collectionIds || ['collection_inbox']).includes(collection.id) ? 'checked' : ''}> <span>${escapeHtml(collection.title)}</span></label>`).join('')}</div></div><div class="sc-library-workspace__row-actions"><button type="button" data-open-board="${escapeHtml(item.id)}">Open</button>${annotationsEnabled ? `<button type="button" data-annotate-board="${escapeHtml(item.id)}">Annotate</button>` : ''}<button type="button" data-note-board="${escapeHtml(item.id)}">Write note</button><button type="button" data-delete-board="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : '<p class="sc-library-workspace__empty">No Whiteboards or Chalkboards have been saved.</p>'}
        </div>
      </section>`;
  };

  const portabilityHtml = () => `
    <section class="sc-library-workspace__section">
      <header><div><p class="sc-library__eyebrow">Portable research data</p><h3>Import and export</h3></div></header>
      <div class="sc-library-workspace__portability-grid">
        <article><h4>Export workspace</h4><p>Download collections, saved records, notes, sources, Technical Translation Matrices, Whiteboards, Chalkboards, annotations, custom books, application handoffs, relationships, citations, handwriting, and validation states as a versioned JSON manifest.</p><button type="button" data-export-workspace>Download JSON export</button></article>
        <article><h4>Import workspace</h4><p>Replace this browser’s current workspace with a compatible Sustainable Catalyst Library export.</p><label class="sc-library-workspace__file"><span>Select JSON export</span><input type="file" accept="application/json,.json" data-import-workspace></label></article>
        <article><h4>PostgreSQL workspace</h4><p>Download normalized SQL for collections, saved records, notes, sources, matrices, boards, annotations, books, and application handoffs.</p><button type="button" data-export-workspace-postgresql>Download PostgreSQL SQL</button></article>
        <article><h4>JSONL workspace</h4><p>Download line-delimited JSON for analytics, migration, and data engineering.</p><button type="button" data-export-workspace-jsonl>Download JSONL</button></article>
        <article><h4>Copy JSON</h4><p>Copy the complete workspace manifest for inspection or transfer into another system.</p><button type="button" data-copy-workspace>Copy workspace JSON</button></article>
        <article><h4>Portable schema</h4><p>Download the normalized PostgreSQL schema without local data.</p><button type="button" data-export-workspace-schema>Download schema.sql</button></article>
        <article class="sc-library-workspace__danger"><h4>Clear local workspace</h4><p>Delete all locally stored collections, saved records, notes, sources, matrices, Whiteboards, Chalkboards, annotations, custom books, and connected-tool handoffs from this browser.</p><button type="button" data-clear-workspace>Clear all local data</button></article>
      </div>
      <details class="sc-library-workspace__manifest"><summary>Export manifest details</summary><pre>${escapeHtml(JSON.stringify({ schema: workspace.schema, version: workspace.version, updatedAt: workspace.updatedAt, counts: { collections: workspace.collections.length, savedRecords: workspace.savedRecords.length, notes: workspace.notes.length, sources: workspace.sources.length, matrices: workspace.matrices.length, boards: workspace.boards.length, handoffs: workspace.handoffs.length, annotations: workspace.annotations.length, books: workspace.books.length } }, null, 2))}</pre></details>
    </section>`;

  const controllers = roots.map((root) => {
    const panel = root.querySelector('[data-library-workspace]');
    const content = root.querySelector('[data-workspace-content]');
    const notice = root.querySelector('[data-workspace-notice]');
    const count = root.querySelector('[data-workspace-count]');
    let activeTab = root.dataset.workspaceInitialTab || 'overview';
    let editId = '';
    let noteSeed = {};
    let matrixDraft = makeMatrix(defaultMatrixTemplate);

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
      else if (activeTab === 'matrices') content.innerHTML = matricesHtml(matrixDraft);
      else if (activeTab === 'boards') content.innerHTML = boardsHtml();
      else if (activeTab === 'annotations') content.innerHTML = '<div data-sc-library-annotations-inline></div>';
      else if (activeTab === 'books') content.innerHTML = '<div data-sc-library-books-inline></div>';
      else if (activeTab === 'integrations') content.innerHTML = '<div data-sc-library-integrations-inline></div>';
      else if (activeTab === 'portability') content.innerHTML = portabilityHtml();
      else content.innerHTML = overviewHtml();
      if (activeTab === 'annotations') document.dispatchEvent(new CustomEvent('sc-library-annotations-render'));
      if (activeTab === 'books') document.dispatchEvent(new CustomEvent('sc-library-books-render'));
      if (activeTab === 'integrations') window.dispatchEvent(new CustomEvent('sc-library-integrations-render'));
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
    const setTab = (tab) => { activeTab = tab; editId = ''; noteSeed = {}; if (tab === 'matrices') matrixDraft = makeMatrix(defaultMatrixTemplate); render(); };

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
        else if (kind === 'integrations') open('integrations');
        else if (kind === 'matrix') { matrixDraft = makeMatrix(defaultMatrixTemplate); open('matrices'); }
        else if (kind === 'whiteboard' || kind === 'chalkboard') { document.dispatchEvent(new CustomEvent('sc-library-new-board', { detail: { type: kind } })); open('boards'); }
        else if (kind === 'annotation') { document.dispatchEvent(new CustomEvent('sc-library-new-annotation', { detail: { targetType: 'custom', title: 'Research annotation' } })); open('annotations'); }
        else if (kind === 'book') { open('books'); document.dispatchEvent(new CustomEvent('sc-library-new-book', { detail: {} })); }
        else open('notes');
        return;
      }
      if (event.target.closest('[data-cancel-edit]')) { editId = ''; noteSeed = {}; matrixDraft = makeMatrix(defaultMatrixTemplate); render(); return; }

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
        workspace.matrices.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); if (!item.collectionIds.length) item.collectionIds = ['collection_inbox']; });
        workspace.boards.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); if (!item.collectionIds.length) item.collectionIds = ['collection_inbox']; });
        workspace.annotations.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); if (!item.collectionIds.length) item.collectionIds = ['collection_inbox']; });
        workspace.books.forEach((item) => { item.collectionIds = (item.collectionIds || []).filter((value) => value !== id); if (!item.collectionIds.length) item.collectionIds = ['collection_inbox']; });
        persist(); render(); return;
      }
      const noteSavedRecord = event.target.closest('[data-note-saved-record]');
      if (noteSavedRecord) {
        const record = recordById(noteSavedRecord.dataset.noteSavedRecord);
        if (record) { noteSeed = { title: `Notes on ${record.title}`, recordId: String(record.recordId), collectionIds: recordCollectionIds(record) }; activeTab = 'notes'; editId = ''; render(); }
        return;
      }
      const bookSavedRecord = event.target.closest('[data-book-saved-record]');
      if (bookSavedRecord) {
        const record = recordById(bookSavedRecord.dataset.bookSavedRecord);
        if (record) { activeTab = 'books'; render(); document.dispatchEvent(new CustomEvent('sc-library-books-seed-record', { detail: { record: { id: record.recordId, title: record.title, excerpt: record.excerpt, url: record.url, type_label: record.typeLabel } } })); }
        return;
      }
      const matrixSavedRecord = event.target.closest('[data-matrix-saved-record]');
      if (matrixSavedRecord) {
        const record = recordById(matrixSavedRecord.dataset.matrixSavedRecord);
        if (record) {
          matrixDraft = makeMatrix(defaultMatrixTemplate, { title: `Technical Translation: ${record.title}`, recordId: String(record.recordId), collectionIds: recordCollectionIds(record), description: record.excerpt || '' });
          activeTab = 'matrices'; editId = ''; render();
        }
        return;
      }
      const boardSavedRecord = event.target.closest('[data-board-saved-record]');
      if (boardSavedRecord) {
        const record = recordById(boardSavedRecord.dataset.boardSavedRecord);
        if (record) {
          document.dispatchEvent(new CustomEvent('sc-library-new-board-for-record', { detail: { type: boardSavedRecord.dataset.boardType || 'whiteboard', record: { id: Number(record.recordId), record_identifier: record.recordIdentifier || '', title: record.title, url: record.url, type_label: record.typeLabel || 'Publication', excerpt: record.excerpt || '', resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null } } }));
          activeTab = 'boards'; render();
        }
        return;
      }
      const annotationSavedRecord = event.target.closest('[data-annotation-saved-record]');
      if (annotationSavedRecord) {
        const record = recordById(annotationSavedRecord.dataset.annotationSavedRecord);
        if (record) {
          document.dispatchEvent(new CustomEvent('sc-library-new-annotation-for-record', { detail: { record: { id: Number(record.recordId), record_identifier: record.recordIdentifier || '', title: record.title, url: record.url, type_label: record.typeLabel || 'Publication', excerpt: record.excerpt || '', resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null } } }));
          activeTab = 'annotations'; render();
        }
        return;
      }
      const createBoard = event.target.closest('[data-create-board]');
      if (createBoard) { document.dispatchEvent(new CustomEvent('sc-library-new-board', { detail: { type: createBoard.dataset.createBoard || 'whiteboard' } })); return; }
      if (event.target.closest('[data-open-board-library]')) { document.dispatchEvent(new CustomEvent('sc-library-open-board-library')); return; }
      const openBoard = event.target.closest('[data-open-board]');
      if (openBoard) { document.dispatchEvent(new CustomEvent('sc-library-open-board', { detail: { id: openBoard.dataset.openBoard } })); return; }
      const noteBoard = event.target.closest('[data-note-board]');
      if (noteBoard) { const found = boardById(noteBoard.dataset.noteBoard); if (found) { noteSeed = { title: `Notes on ${found.title}`, boardId: found.id, collectionIds: found.collectionIds || ['collection_inbox'] }; activeTab = 'notes'; editId = ''; render(); } return; }
      const deleteBoard = event.target.closest('[data-delete-board]');
      if (deleteBoard) {
        const id = deleteBoard.dataset.deleteBoard;
        if (!window.confirm('Delete this Whiteboard or Chalkboard?')) return;
        workspace.boards = workspace.boards.filter((item) => item.id !== id);
        workspace.notes.forEach((item) => { if (item.boardId === id) item.boardId = ''; });
        persist(); render(); return;
      }
      const deleteSavedRecord = event.target.closest('[data-delete-saved-record]');
      if (deleteSavedRecord) {
        const recordId = Number(deleteSavedRecord.dataset.deleteSavedRecord);
        workspace.savedRecords = workspace.savedRecords.filter((item) => Number(item.recordId) !== recordId);
        workspace.notes.forEach((item) => { if (Number(item.recordId) === recordId) item.recordId = ''; });
        workspace.matrices.forEach((item) => { if (Number(item.recordId) === recordId) item.recordId = ''; });
        workspace.boards.forEach((item) => { if (Number(item.recordId) === recordId) item.recordId = ''; });
        workspace.annotations.forEach((item) => { if (item.targetType === 'library_record' && Number(item.targetId) === recordId) item.targetId = ''; });
        persist(); render(); return;
      }
      const annotateNote = event.target.closest('[data-annotate-note]');
      if (annotateNote) { const found = workspace.notes.find((item) => item.id === annotateNote.dataset.annotateNote); if (found) document.dispatchEvent(new CustomEvent('sc-library-new-annotation', { detail: { targetType: 'notebook_note', targetId: found.id, title: found.title, targetTitle: found.title, targetExcerpt: found.body, collectionIds: found.collectionIds || ['collection_inbox'] } })); return; }
      const annotateSource = event.target.closest('[data-annotate-source]');
      if (annotateSource) { const found = sourceById(annotateSource.dataset.annotateSource); if (found) document.dispatchEvent(new CustomEvent('sc-library-new-annotation', { detail: { targetType: 'external_source', targetId: found.id, title: found.title, targetTitle: found.title, targetUrl: found.url || '', targetExcerpt: found.description || found.notes || '', collectionIds: found.collectionIds || ['collection_inbox'] } })); return; }
      const annotateMatrix = event.target.closest('[data-annotate-matrix]');
      if (annotateMatrix) { const found = matrixById(annotateMatrix.dataset.annotateMatrix); if (found) document.dispatchEvent(new CustomEvent('sc-library-new-annotation', { detail: { targetType: 'translation_matrix', targetId: found.id, title: found.title, targetTitle: found.title, targetExcerpt: found.description || `${found.rows?.length || 0} rows × ${found.columns?.length || 0} columns`, collectionIds: found.collectionIds || ['collection_inbox'] } })); return; }
      const annotateBoard = event.target.closest('[data-annotate-board]');
      if (annotateBoard) { const found = boardById(annotateBoard.dataset.annotateBoard); if (found) document.dispatchEvent(new CustomEvent('sc-library-new-annotation', { detail: { targetType: found.type === 'chalkboard' ? 'chalkboard' : 'whiteboard', targetId: found.id, title: found.title, targetTitle: found.title, targetExcerpt: found.description || `${found.nodes?.length || 0} cards and ${found.edges?.length || 0} relationships`, collectionIds: found.collectionIds || ['collection_inbox'], pageStyle: found.type === 'chalkboard' ? 'dark' : 'reader' } })); return; }
      const editNote = event.target.closest('[data-edit-note]');
      if (editNote) { editId = editNote.dataset.editNote; activeTab = 'notes'; render(); return; }
      const deleteNote = event.target.closest('[data-delete-note]');
      if (deleteNote) { const id = deleteNote.dataset.deleteNote; workspace.notes = workspace.notes.filter((item) => item.id !== id); workspace.boards.forEach((item) => { if (item.noteId === id) item.noteId = ''; }); persist(); render(); return; }
      const editSource = event.target.closest('[data-edit-source]');
      if (editSource) { editId = editSource.dataset.editSource; activeTab = 'sources'; render(); return; }
      const deleteSource = event.target.closest('[data-delete-source]');
      if (deleteSource) {
        const id = deleteSource.dataset.deleteSource;
        workspace.sources = workspace.sources.filter((item) => item.id !== id);
        workspace.notes.forEach((item) => { if (item.sourceId === id) item.sourceId = ''; });
        workspace.matrices.forEach((item) => { if (item.sourceId === id) item.sourceId = ''; });
        workspace.boards.forEach((item) => { if (item.sourceId === id) item.sourceId = ''; });
        persist(); render(); return;
      }
      const editMatrix = event.target.closest('[data-edit-matrix]');
      if (editMatrix) { const found = matrixById(editMatrix.dataset.editMatrix); if (found) { matrixDraft = normalizeMatrix(found); editId = found.id; activeTab = 'matrices'; render(); } return; }
      const noteMatrix = event.target.closest('[data-note-matrix]');
      if (noteMatrix) { const found = matrixById(noteMatrix.dataset.noteMatrix); if (found) { noteSeed = { title: `Notes on ${found.title}`, matrixId: found.id, collectionIds: found.collectionIds || ['collection_inbox'] }; activeTab = 'notes'; editId = ''; render(); } return; }
      const boardMatrix = event.target.closest('[data-board-matrix]');
      if (boardMatrix) {
        const found = matrixById(boardMatrix.dataset.boardMatrix);
        if (found) {
          const type = boardMatrix.dataset.boardType || 'whiteboard';
          document.dispatchEvent(new CustomEvent('sc-library-new-board', { detail: { type, seed: { templateId: type === 'chalkboard' ? 'equation_workbench' : 'concept_map', title: `${type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard'}: ${found.title}`, description: found.description || '', matrixId: found.id, collectionIds: found.collectionIds || ['collection_inbox'], nodes: [{ type: 'matrix', title: found.title, body: found.description || `${found.rows?.length || 0} rows × ${found.columns?.length || 0} columns`, x: 720, y: 450, width: 360, height: 190, referenceType: 'matrix', referenceId: found.id }] } } }));
          activeTab = 'boards'; render();
        }
        return;
      }
      const deleteMatrix = event.target.closest('[data-delete-matrix]');
      if (deleteMatrix) {
        const id = deleteMatrix.dataset.deleteMatrix;
        if (!window.confirm('Delete this Technical Translation Matrix?')) return;
        workspace.matrices = workspace.matrices.filter((item) => item.id !== id);
        workspace.notes.forEach((item) => { if (item.matrixId === id) item.matrixId = ''; });
        workspace.boards.forEach((item) => { if (item.matrixId === id) item.matrixId = ''; });
        persist(); matrixDraft = makeMatrix(defaultMatrixTemplate); editId = ''; render(); showNotice(strings.matrixDeleted || 'Matrix deleted.'); return;
      }
      const exportMatrixJson = event.target.closest('[data-export-matrix-json]');
      if (exportMatrixJson) { const found = matrixById(exportMatrixJson.dataset.exportMatrixJson); if (found) downloadText(JSON.stringify({ schema, version, exportScope: { type: 'translation-matrix', id: found.id, title: found.title }, matrix: found }, null, 2), `technical-translation-matrix-${slug(found.title)}-${today()}.json`, 'application/json'); return; }
      const exportMatrixCsv = event.target.closest('[data-export-matrix-csv]');
      if (exportMatrixCsv) { const found = matrixById(exportMatrixCsv.dataset.exportMatrixCsv); if (found) downloadText(matrixCsv(found), `technical-translation-matrix-${slug(found.title)}-${today()}.csv`, 'text/csv'); return; }
      const printMatrix = event.target.closest('[data-print-matrix]');
      if (printMatrix) { const found = matrixById(printMatrix.dataset.printMatrix); if (found) { const win = window.open('', '_blank'); if (win) { win.document.open(); win.document.write(matrixPrintableHtml(found)); win.document.close(); } else showNotice('The browser blocked the print window.', 'error'); } return; }
      if (event.target.closest('[data-matrix-add-row]')) { const row = { id: uid('row'), label: 'New knowledge layer', cells: {} }; matrixDraft.columns.forEach((column) => { row.cells[column.id] = { value: '', status: 'draft', sourceRef: '' }; }); matrixDraft.rows.push(row); render(); return; }
      if (event.target.closest('[data-matrix-add-column]')) { const column = { id: uid('column'), label: 'New translation' }; matrixDraft.columns.push(column); matrixDraft.rows.forEach((row) => { row.cells[column.id] = { value: '', status: 'draft', sourceRef: '' }; }); render(); return; }
      const removeRow = event.target.closest('[data-matrix-remove-row]');
      if (removeRow) { if (matrixDraft.rows.length <= 1) return; matrixDraft.rows = matrixDraft.rows.filter((row) => row.id !== removeRow.dataset.matrixRemoveRow); render(); return; }
      const removeColumn = event.target.closest('[data-matrix-remove-column]');
      if (removeColumn) { if (matrixDraft.columns.length <= 1) return; const id = removeColumn.dataset.matrixRemoveColumn; matrixDraft.columns = matrixDraft.columns.filter((column) => column.id !== id); matrixDraft.rows.forEach((row) => { delete row.cells[id]; }); render(); return; }
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
          recordId: words(data.get('recordId')), sourceId: words(data.get('sourceId')), matrixId: words(data.get('matrixId')), boardId: words(data.get('boardId')), annotationId: words(data.get('annotationId')), tags: listFromText(data.get('tags')),
          collectionIds: data.getAll('collectionIds').map(String), createdAt: existing?.createdAt || now(), updatedAt: now(),
        };
        if (!item.collectionIds.length) item.collectionIds = ['collection_inbox'];
        if (existing) Object.assign(existing, item); else workspace.notes.push(item);
        persist(); editId = ''; noteSeed = {}; renderAll(); showNotice('Note saved.'); return;
      }
      if (form.matches('[data-matrix-form]')) {
        const selectedCollections = data.getAll('collectionIds').map(String);
        matrixDraft.id = words(data.get('id')) || matrixDraft.id || uid('matrix');
        matrixDraft.title = words(data.get('title')) || 'Untitled Technical Translation Matrix';
        matrixDraft.description = words(data.get('description'));
        matrixDraft.templateId = words(data.get('templateId')) || matrixDraft.templateId || defaultMatrixTemplate;
        matrixDraft.status = words(data.get('status')) || 'draft';
        matrixDraft.recordId = words(data.get('recordId'));
        matrixDraft.sourceId = words(data.get('sourceId'));
        matrixDraft.tags = listFromText(data.get('tags'));
        matrixDraft.notes = words(data.get('notes'));
        matrixDraft.collectionIds = selectedCollections.length ? selectedCollections : ['collection_inbox'];
        matrixDraft.updatedAt = now();
        const existing = matrixById(matrixDraft.id);
        const saved = normalizeMatrix(matrixDraft);
        if (existing) Object.assign(existing, saved); else workspace.matrices.push(saved);
        persist(); editId = ''; matrixDraft = makeMatrix(defaultMatrixTemplate); renderAll(); showNotice(strings.matrixSaved || 'Matrix saved.'); return;
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

    root.addEventListener('input', (event) => {
      if (activeTab !== 'matrices') return;
      const field = event.target.closest('[data-matrix-field]');
      if (field) {
        const key = field.dataset.matrixField;
        if (key === 'tagsText') matrixDraft.tags = listFromText(field.value);
        else matrixDraft[key] = field.value;
        return;
      }
      const columnLabel = event.target.closest('[data-matrix-column-label]');
      if (columnLabel) { const column = matrixDraft.columns.find((item) => item.id === columnLabel.dataset.matrixColumnLabel); if (column) column.label = columnLabel.value; return; }
      const rowLabel = event.target.closest('[data-matrix-row-label]');
      if (rowLabel) { const row = matrixDraft.rows.find((item) => item.id === rowLabel.dataset.matrixRowLabel); if (row) row.label = rowLabel.value; return; }
      const cellValue = event.target.closest('[data-matrix-cell]');
      if (cellValue) { const [rowId, columnId] = cellValue.dataset.matrixCell.split('|'); const cell = matrixCell(matrixDraft, rowId, columnId); if (cell) cell.value = cellValue.value; return; }
      const cellSource = event.target.closest('[data-matrix-cell-source]');
      if (cellSource) { const [rowId, columnId] = cellSource.dataset.matrixCellSource.split('|'); const cell = matrixCell(matrixDraft, rowId, columnId); if (cell) cell.sourceRef = cellSource.value; }
    });

    root.addEventListener('change', async (event) => {
      const matrixTemplateSelect = event.target.closest('[data-matrix-template]');
      if (matrixTemplateSelect) {
        const next = matrixTemplateSelect.value;
        if (next !== matrixDraft.templateId && window.confirm(strings.matrixTemplateReset || 'Changing templates resets the unsaved grid. Continue?')) {
          const seed = { id: matrixDraft.id, title: matrixDraft.title, description: matrixTemplate(next).description || matrixDraft.description, status: matrixDraft.status, recordId: matrixDraft.recordId, sourceId: matrixDraft.sourceId, collectionIds: matrixDraft.collectionIds, tags: matrixDraft.tags, notes: matrixDraft.notes, createdAt: matrixDraft.createdAt };
          matrixDraft = makeMatrix(next, seed); render();
        } else if (next !== matrixDraft.templateId) {
          matrixTemplateSelect.value = matrixDraft.templateId;
        }
        return;
      }
      const matrixCellStatus = event.target.closest('[data-matrix-cell-status]');
      if (matrixCellStatus) { const [rowId, columnId] = matrixCellStatus.dataset.matrixCellStatus.split('|'); const cell = matrixCell(matrixDraft, rowId, columnId); if (cell) cell.status = matrixCellStatus.value; return; }
      if (event.target.closest('[data-matrix-form] input[name="collectionIds"]')) {
        matrixDraft.collectionIds = Array.from(root.querySelectorAll('[data-matrix-form] input[name="collectionIds"]:checked')).map((input) => input.value);
        if (!matrixDraft.collectionIds.length) matrixDraft.collectionIds = ['collection_inbox'];
        return;
      }
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
      const boardCollection = event.target.closest('[data-board-collection-id]');
      if (boardCollection) {
        const board = boardById(boardCollection.dataset.boardCollectionId);
        if (board) {
          const selected = Array.from(root.querySelectorAll(`[data-board-collection-id="${CSS.escape(String(board.id))}"]:checked`)).map((input) => input.value);
          board.collectionIds = selected.length ? selected : ['collection_inbox'];
          board.updatedAt = now(); persist(); renderAll();
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

    return {
      root, open, close, render, setTab,
      seedNote(seed) { noteSeed = seed || {}; activeTab = 'notes'; editId = ''; open('notes'); },
      seedMatrix(seed) { matrixDraft = makeMatrix(seed?.templateId || defaultMatrixTemplate, seed || {}); activeTab = 'matrices'; editId = ''; open('matrices'); },
      seedBook(record) { activeTab = 'books'; editId = ''; open('books'); document.dispatchEvent(new CustomEvent('sc-library-books-seed-record', { detail: { record } })); },
    };
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

  document.addEventListener('sc-library-new-matrix-for-record', (event) => {
    if (!matrixEnabled) return;
    const record = event.detail?.record;
    if (!record?.id) return;
    let existing = recordById(record.id);
    if (!existing) {
      existing = { recordId: Number(record.id), recordIdentifier: words(record.record_identifier), title: words(record.title), url: words(record.url), typeLabel: words(record.type_label || 'Publication'), excerpt: words(record.excerpt), resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null, collectionIds: ['collection_inbox'], createdAt: now(), updatedAt: now() };
      workspace.savedRecords.push(existing); persist();
    }
    controllerForEvent(event).seedMatrix({ title: `Technical Translation: ${record.title}`, recordId: String(record.id), collectionIds: recordCollectionIds(existing), description: words(record.excerpt) });
  });

  document.addEventListener('sc-library-new-book-for-record', (event) => {
    if (!booksEnabled) return;
    const record = event.detail?.record;
    if (!record?.id) return;
    let existing = recordById(record.id);
    if (!existing) {
      existing = { recordId: Number(record.id), recordIdentifier: words(record.record_identifier), title: words(record.title), url: words(record.url), typeLabel: words(record.type_label || 'Publication'), excerpt: words(record.excerpt), resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null, collectionIds: ['collection_inbox'], createdAt: now(), updatedAt: now() };
      workspace.savedRecords.push(existing); persist();
    }
    controllerForEvent(event).seedBook(record);
  });

  document.addEventListener('sc-library-new-note-for-annotation', (event) => {
    const annotation = event.detail?.annotation;
    if (!annotation?.id) return;
    controllerForEvent(event).seedNote({ title: `Notes on ${annotation.title}`, annotationId: annotation.id, collectionIds: annotation.collectionIds || ['collection_inbox'] });
  });

  window.addEventListener('storage', (event) => { if (event.key === storageKey) { load(); renderAll(); } });
  window.addEventListener('sc-library-workspace-updated', () => { load(); renderAll(); });
  document.addEventListener('sc-library-board-saved', () => { load(); renderAll(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') controllers.forEach((controller) => controller.close()); });
  renderAll();
})();
