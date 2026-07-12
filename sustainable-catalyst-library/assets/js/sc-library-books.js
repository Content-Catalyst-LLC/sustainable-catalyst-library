(() => {
  'use strict';

  const shared = window.SCBooksShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const workspaceSchema = shared.workspaceSchema || 'sc-library-workspace/1.5';
  const legacySchemas = Array.isArray(shared.legacyWorkspaceSchemas) ? shared.legacyWorkspaceSchemas : [];
  const bookSchema = shared.schema || 'sc-library-book/1.0';
  const version = shared.version || '1.9.0';
  const restBase = String(shared.restBase || '/wp-json/sustainable-catalyst/v1/library').replace(/\/$/, '');
  const themes = Object.fromEntries((shared.themes || []).map((item) => [item.id, item]));
  const pageSizes = Object.fromEntries((shared.pageSizes || []).map((item) => [item.id, item]));
  const mediaModes = Object.fromEntries((shared.mediaModes || []).map((item) => [item.id, item]));
  const defaultTheme = shared.defaultTheme || 'institutional';
  const defaultPageSize = shared.defaultPageSize || 'letter';
  const strings = shared.strings || {};
  const controllers = new WeakMap();

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const escapeAttr = escapeHtml;
  const now = () => new Date().toISOString();
  const today = () => new Date().toISOString().slice(0, 10);
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`;
  const words = (value) => String(value || '').trim();
  const slug = (value) => words(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'book';
  const deepClone = (value) => JSON.parse(JSON.stringify(value));
  const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };

  const emptyWorkspace = () => ({
    schema: workspaceSchema,
    version,
    createdAt: now(),
    updatedAt: now(),
    collections: [{ id: 'collection_inbox', title: 'Research Inbox', description: 'Newly saved Library records and research material.', createdAt: now(), updatedAt: now() }],
    savedRecords: [], notes: [], sources: [], matrices: [], boards: [], handoffs: [], annotations: [], books: [],
  });

  const cleanArray = (value) => Array.isArray(value) ? value.filter((item) => item && typeof item === 'object') : [];
  const loadWorkspace = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) return emptyWorkspace();
      const data = JSON.parse(raw);
      if (![workspaceSchema, ...legacySchemas].includes(data.schema)) return emptyWorkspace();
      return {
        ...emptyWorkspace(),
        ...data,
        schema: workspaceSchema,
        version,
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
    } catch (error) {
      return emptyWorkspace();
    }
  };

  let workspace = loadWorkspace();
  const persist = () => {
    workspace.schema = workspaceSchema;
    workspace.version = version;
    workspace.updatedAt = now();
    window.localStorage.setItem(storageKey, JSON.stringify(workspace));
    window.dispatchEvent(new CustomEvent('sc-library-workspace-updated'));
  };

  const themeOptions = (selected) => Object.values(themes).map((item) => `<option value="${escapeAttr(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('');
  const pageSizeOptions = (selected) => Object.values(pageSizes).map((item) => `<option value="${escapeAttr(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('');
  const mediaModeOptions = (selected) => Object.values(mediaModes).map((item) => `<option value="${escapeAttr(item.id)}" ${item.id === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('');
  const collectionOptions = (selected = []) => workspace.collections.map((item) => `<label><input type="checkbox" name="collectionIds" value="${escapeAttr(item.id)}" ${selected.includes(item.id) ? 'checked' : ''}> ${escapeHtml(item.title)}</label>`).join('');

  const makeBook = (seed = {}) => {
    const createdAt = seed.createdAt || now();
    return {
      schema: bookSchema,
      id: seed.id || uid('book'),
      title: words(seed.title) || 'Untitled Research Book',
      subtitle: words(seed.subtitle),
      description: words(seed.description),
      editor: words(seed.editor),
      edition: words(seed.edition) || 'First edition',
      theme: themes[seed.theme] ? seed.theme : defaultTheme,
      pageSize: pageSizes[seed.pageSize] ? seed.pageSize : defaultPageSize,
      mediaMode: mediaModes[seed.mediaMode] ? seed.mediaMode : 'linked',
      includeToc: seed.includeToc !== false,
      includeManifest: seed.includeManifest !== false,
      includeCitations: seed.includeCitations !== false,
      includeAnnotations: seed.includeAnnotations !== false,
      grayscale: Boolean(seed.grayscale),
      frontMatter: {
        preface: words(seed.frontMatter?.preface),
        introduction: words(seed.frontMatter?.introduction),
      },
      backMatter: {
        conclusion: words(seed.backMatter?.conclusion),
      },
      collectionIds: Array.isArray(seed.collectionIds) && seed.collectionIds.length ? seed.collectionIds : ['collection_inbox'],
      items: cleanArray(seed.items).map((item) => ({
        id: item.id || uid('bookitem'),
        type: words(item.type) || 'custom',
        refId: words(item.refId),
        title: words(item.title) || 'Untitled section',
        excerpt: words(item.excerpt),
        presentation: words(item.presentation) || 'full',
        content: words(item.content),
        sourceUrl: words(item.sourceUrl),
        createdAt: item.createdAt || createdAt,
      })),
      createdAt,
      updatedAt: now(),
    };
  };

  const normalizeBooks = () => { workspace.books = cleanArray(workspace.books).map((item) => makeBook(item)); };
  normalizeBooks();

  const bookById = (id) => workspace.books.find((item) => item.id === id);
  const artifactKey = (type, refId) => `${type}:${String(refId)}`;
  const itemKey = (item) => artifactKey(item.type, item.refId || item.id);

  const allArtifacts = () => [
    ...workspace.savedRecords.map((item) => ({ type: 'record', refId: String(item.recordId), title: item.title, excerpt: item.excerpt || '', sourceUrl: item.url || '', meta: item.typeLabel || 'Library publication' })),
    ...workspace.notes.map((item) => ({ type: 'note', refId: item.id, title: item.title, excerpt: item.body || '', meta: String(item.type || 'Notebook note').replace(/_/g, ' ') })),
    ...workspace.sources.map((item) => ({ type: 'source', refId: item.id, title: item.title, excerpt: item.description || item.notes || '', sourceUrl: item.url || '', meta: String(item.type || 'External source').replace(/_/g, ' ') })),
    ...workspace.matrices.map((item) => ({ type: 'matrix', refId: item.id, title: item.title, excerpt: item.description || '', meta: 'Technical Translation Matrix' })),
    ...workspace.boards.map((item) => ({ type: 'board', refId: item.id, title: item.title, excerpt: item.description || '', meta: item.type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard' })),
    ...workspace.annotations.map((item) => ({ type: 'annotation', refId: item.id, title: item.title, excerpt: item.transcription || item.targetExcerpt || '', sourceUrl: item.targetUrl || '', meta: 'Annotation and handwriting' })),
  ];

  const artifactBy = (type, refId) => {
    if (type === 'record') return workspace.savedRecords.find((item) => String(item.recordId) === String(refId));
    if (type === 'note') return workspace.notes.find((item) => item.id === refId);
    if (type === 'source') return workspace.sources.find((item) => item.id === refId);
    if (type === 'matrix') return workspace.matrices.find((item) => item.id === refId);
    if (type === 'board') return workspace.boards.find((item) => item.id === refId);
    if (type === 'annotation') return workspace.annotations.find((item) => item.id === refId);
    return null;
  };

  const citation = (source) => {
    const creator = words(source.creators || source.organization) || 'Unknown creator';
    const year = words(source.publication_date).slice(0, 4) || 'n.d.';
    const publisher = words(source.publisher || source.organization);
    const pages = words(source.pages);
    const url = words(source.url);
    return `${creator}. (${year}). ${words(source.title)}.${publisher ? ` ${publisher}.` : ''}${pages ? ` ${pages}.` : ''}${url ? ` ${url}` : ''}`.replace(/\s+/g, ' ').trim();
  };

  const paragraphs = (text) => words(text).split(/\n{2,}/).filter(Boolean).map((part) => `<p>${escapeHtml(part).replace(/\n/g, '<br>')}</p>`).join('');
  const directVideo = (url) => /\.mp4(?:$|\?)/i.test(url || '');
  const mediaCard = (source, mode) => {
    const url = words(source.url);
    const timestamp = words(source.chapter || source.pages);
    const description = words(source.description || source.notes);
    const playable = mode === 'preview' && directVideo(url);
    return `<figure class="book-media-card">
      ${playable ? `<video controls preload="metadata" src="${escapeAttr(url)}"></video>` : '<div class="book-media-poster"><span aria-hidden="true">▶</span></div>'}
      <figcaption><strong>${escapeHtml(source.title || 'Video source')}</strong>${timestamp ? `<span>Selected segment: ${escapeHtml(timestamp)}</span>` : ''}${description ? `<p>${escapeHtml(description)}</p>` : ''}${url ? `<a href="${escapeAttr(url)}">Open video or source link</a>` : ''}</figcaption>
    </figure>`;
  };

  const matrixHtml = (matrix) => {
    const columns = matrix.columns || [];
    const rows = matrix.rows || [];
    return `<div class="book-matrix-wrap"><table><thead><tr><th>Knowledge layer</th>${columns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join('')}</tr></thead><tbody>${rows.map((row) => `<tr><th>${escapeHtml(row.label)}</th>${columns.map((column) => { const cell = row.cells?.[column.id] || {}; return `<td>${paragraphs(cell.value || '')}${cell.sourceRef ? `<small>Source: ${escapeHtml(cell.sourceRef)}</small>` : ''}${cell.status ? `<em>${escapeHtml(cell.status)}</em>` : ''}</td>`; }).join('')}</tr>`).join('')}</tbody></table></div>`;
  };

  const boardHtml = (board) => {
    const nodes = cleanArray(board.nodes);
    const edges = cleanArray(board.edges);
    const nodeName = (id) => nodes.find((node) => node.id === id)?.title || id;
    return `<div class="book-board"><div class="book-board-grid">${nodes.map((node) => `<article><span>${escapeHtml(String(node.type || 'card').replace(/_/g, ' '))}</span><h4>${escapeHtml(node.title)}</h4>${paragraphs(node.body || '')}${node.url ? `<a href="${escapeAttr(node.url)}">Open source</a>` : ''}</article>`).join('')}</div>${edges.length ? `<h4>Relationships</h4><ul>${edges.map((edge) => `<li>${escapeHtml(nodeName(edge.from))} → ${escapeHtml(edge.label || edge.type || 'related to')} → ${escapeHtml(nodeName(edge.to))}</li>`).join('')}</ul>` : ''}${board.strokes?.length ? `<p class="book-provenance">This board also contains ${Number(board.strokes.length)} handwritten ink stroke${board.strokes.length === 1 ? '' : 's'} preserved in the editable workspace record.</p>` : ''}</div>`;
  };

  const annotationSvg = (annotation) => {
    const width = Number(annotation.width || 1400);
    const height = Number(annotation.height || 1900);
    const dark = annotation.pageStyle === 'dark';
    const paths = cleanArray(annotation.strokes).map((stroke) => `<path d="${escapeAttr(stroke.d || '')}" fill="none" stroke="${escapeAttr(stroke.color || '#721019')}" stroke-width="${Number(stroke.width || 4)}" stroke-linecap="round" stroke-linejoin="round" opacity="${Number(stroke.opacity ?? 1)}"/>`).join('');
    const shapes = cleanArray(annotation.shapes).map((shape) => {
      const x = Number(shape.x || 0), y = Number(shape.y || 0), w = Number(shape.width || 0), h = Number(shape.height || 0);
      if (shape.type === 'ellipse') return `<ellipse cx="${x + w / 2}" cy="${y + h / 2}" rx="${Math.abs(w / 2)}" ry="${Math.abs(h / 2)}" fill="none" stroke="${escapeAttr(shape.color || '#721019')}" stroke-width="${Number(shape.widthPx || 4)}"/>`;
      return `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="none" stroke="${escapeAttr(shape.color || '#721019')}" stroke-width="${Number(shape.widthPx || 4)}"/>`;
    }).join('');
    return `<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="${escapeAttr(annotation.title || 'Handwritten annotation')}"><rect width="100%" height="100%" fill="${dark ? '#101b18' : '#fff'}"/>${paths}${shapes}</svg>`;
  };

  const resolveItem = async (book, item) => {
    const artifact = artifactBy(item.type, item.refId);
    if (item.type === 'custom') return `<section class="book-chapter book-custom"><h2>${escapeHtml(item.title)}</h2>${paragraphs(item.content || item.excerpt)}</section>`;
    if (item.type === 'record') {
      let record = artifact || { recordId: item.refId, title: item.title, excerpt: item.excerpt, url: item.sourceUrl };
      let detail = null;
      try {
        const response = await fetch(`${restBase}/items/${encodeURIComponent(record.recordId || item.refId)}/book`, { credentials: 'same-origin' });
        if (response.ok) detail = await response.json();
      } catch (error) {}
      const title = detail?.title || record.title || item.title;
      const content = item.presentation === 'summary' ? paragraphs(detail?.excerpt || record.excerpt || item.excerpt) : (detail?.content_html || paragraphs(record.excerpt || item.excerpt));
      const media = cleanArray(detail?.media).map((url) => mediaCard({ title: `Video connected to ${title}`, url }, book.mediaMode)).join('');
      return `<section class="book-chapter"><p class="book-kicker">Sustainable Catalyst publication</p><h2>${escapeHtml(title)}</h2><p class="book-byline">${detail?.author ? `By ${escapeHtml(detail.author)} · ` : ''}${detail?.published_at ? `Published ${escapeHtml(formatDate(detail.published_at))} · ` : ''}<a href="${escapeAttr(detail?.url || record.url || item.sourceUrl)}">Live publication</a></p><div class="book-article-content">${content}</div>${media ? `<section class="book-media-section"><h3>Connected video</h3>${media}</section>` : ''}</section>`;
    }
    if (item.type === 'note' && artifact) return `<section class="book-chapter"><p class="book-kicker">User-authored Notebook note</p><h2>${escapeHtml(item.title || artifact.title)}</h2>${paragraphs(artifact.body || item.excerpt)}</section>`;
    if (item.type === 'source' && artifact) {
      const isVideo = ['video', 'podcast'].includes(artifact.type) || /youtube|youtu\.be|vimeo|\.mp4/i.test(artifact.url || '');
      return `<section class="book-chapter"><p class="book-kicker">Outside research source</p><h2>${escapeHtml(item.title || artifact.title)}</h2>${isVideo ? mediaCard(artifact, book.mediaMode) : `${paragraphs(artifact.description || artifact.notes || '')}<p class="book-citation">${escapeHtml(citation(artifact))}</p>${artifact.url ? `<p><a href="${escapeAttr(artifact.url)}">Open source</a></p>` : ''}`}</section>`;
    }
    if (item.type === 'matrix' && artifact) return `<section class="book-chapter book-landscape"><p class="book-kicker">Technical Translation Matrix</p><h2>${escapeHtml(item.title || artifact.title)}</h2>${paragraphs(artifact.description || '')}${matrixHtml(artifact)}</section>`;
    if (item.type === 'board' && artifact) return `<section class="book-chapter book-landscape"><p class="book-kicker">${artifact.type === 'chalkboard' ? 'Technical Chalkboard' : 'Research Whiteboard'}</p><h2>${escapeHtml(item.title || artifact.title)}</h2>${paragraphs(artifact.description || '')}${boardHtml(artifact)}</section>`;
    if (item.type === 'annotation' && artifact) return `<section class="book-chapter"><p class="book-kicker">Annotated research page</p><h2>${escapeHtml(item.title || artifact.title)}</h2><div class="book-annotation">${annotationSvg(artifact)}</div>${artifact.transcription ? `<h3>Accessible handwriting transcription</h3>${paragraphs(artifact.transcription)}` : ''}${artifact.targetUrl ? `<p><a href="${escapeAttr(artifact.targetUrl)}">Open annotated source</a></p>` : ''}</section>`;
    return `<section class="book-chapter"><h2>${escapeHtml(item.title)}</h2>${paragraphs(item.excerpt || 'The original workspace item is no longer available. Its title and manifest reference have been preserved.')}</section>`;
  };

  const bookCss = (book) => {
    const theme = book.theme || 'institutional';
    const page = pageSizes[book.pageSize]?.css || 'letter';
    const minimal = theme === 'minimal' || book.grayscale;
    const bodyFont = theme === 'academic' ? 'Georgia, serif' : theme === 'technical' ? 'Arial, sans-serif' : 'Georgia, serif';
    const headingFont = theme === 'institutional' ? 'Arial, sans-serif' : bodyFont;
    return `
      @page{size:${page};margin:18mm 17mm 20mm}
      *{box-sizing:border-box}body{margin:0;color:#161616;background:#fff;font-family:${bodyFont};font-size:11.5pt;line-height:1.58}a{color:${minimal ? '#111' : '#6d1022'};word-break:break-word}h1,h2,h3,h4{font-family:${headingFont};line-height:1.18;break-after:avoid}h1{font-size:34pt}h2{font-size:23pt;margin-top:0}h3{font-size:15pt}img,svg,video{max-width:100%;height:auto}.book-cover{min-height:85vh;display:flex;flex-direction:column;justify-content:center;page-break-after:always;border-top:12px solid ${minimal ? '#111' : '#6d1022'}}.book-cover .book-brand{text-transform:uppercase;letter-spacing:.16em;font:700 10pt Arial,sans-serif}.book-cover p{max-width:42em}.book-front,.book-back,.book-manifest,.book-toc{page-break-after:always}.book-chapter{page-break-before:always}.book-kicker{text-transform:uppercase;letter-spacing:.12em;font:700 9pt Arial,sans-serif;color:${minimal ? '#333' : '#6d1022'}}.book-byline,.book-provenance,.book-citation{font-size:9.5pt;color:#555}.book-article-content>h1:first-child,.book-article-content>h2:first-child{display:none}.book-article-content table,.book-matrix-wrap table{width:100%;border-collapse:collapse;font-size:9pt}.book-article-content th,.book-article-content td,.book-matrix-wrap th,.book-matrix-wrap td{border:1px solid #999;padding:6px;vertical-align:top}.book-matrix-wrap{overflow:visible}.book-matrix-wrap td small,.book-matrix-wrap td em{display:block;margin-top:6px;font-size:8pt}.book-board-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.book-board-grid article{border:1px solid #aaa;padding:9px;break-inside:avoid}.book-board-grid h4,.book-board-grid p{margin:.2rem 0}.book-board-grid span{font:700 8pt Arial,sans-serif;text-transform:uppercase}.book-annotation svg{border:1px solid #aaa;max-height:720px}.book-media-card{border:1px solid #aaa;padding:12px;display:grid;grid-template-columns:150px 1fr;gap:14px;break-inside:avoid}.book-media-card video{width:100%}.book-media-poster{min-height:100px;background:#171717;color:#fff;display:grid;place-items:center;font-size:34px}.book-media-card figcaption span{display:block}.book-media-card figcaption p{margin:.4rem 0}.book-toc ol{padding-left:1.4rem}.book-toc li{margin:.35rem 0}.book-manifest pre{white-space:pre-wrap;background:#f2f2f2;padding:10px;font-size:8pt}.book-running-note{font-size:8.5pt;color:#666;border-top:1px solid #bbb;padding-top:8px}.print-toolbar{position:sticky;top:0;z-index:5;background:#111;color:#fff;padding:10px;display:flex;gap:8px;align-items:center;font-family:Arial,sans-serif}.print-toolbar button{padding:8px 12px;font-weight:700}.screen-only{display:block}@media print{.print-toolbar,.screen-only,video{display:none!important}.book-media-card{grid-template-columns:110px 1fr}.book-chapter{break-after:auto}.book-landscape{page:landscape}}`;
  };

  const buildBookHtml = async (book, autoPrint = false) => {
    const chapters = [];
    for (const item of book.items) chapters.push(await resolveItem(book, item));
    const toc = book.includeToc ? `<section class="book-toc"><h2>Table of Contents</h2><ol>${book.items.map((item) => `<li>${escapeHtml(item.title)}</li>`).join('')}</ol></section>` : '';
    const manifest = {
      schema: bookSchema,
      book_id: book.id,
      edition: book.edition,
      generated_at: now(),
      workspace_schema: workspaceSchema,
      items: book.items.map((item, index) => ({ position: index + 1, type: item.type, reference_id: item.refId, title: item.title, source_url: item.sourceUrl || '' })),
    };
    const provenance = book.includeManifest ? `<section class="book-manifest"><h2>Edition Manifest and Provenance</h2><p>This edition was generated from the Sustainable Catalyst Library workspace on ${escapeHtml(formatDate(manifest.generated_at))}. Live publications and outside sources may have changed since generation.</p><pre>${escapeHtml(JSON.stringify(manifest, null, 2))}</pre></section>` : '';
    const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHtml(book.title)}</title><style>${bookCss(book)}</style></head><body><div class="print-toolbar"><strong>Custom Book Preview</strong><button onclick="window.print()">Print / Save as PDF</button><span>Videos use durable link fallbacks in printed PDF editions.</span></div><main><section class="book-cover"><p class="book-brand">Sustainable Catalyst Library</p><h1>${escapeHtml(book.title)}</h1>${book.subtitle ? `<h2>${escapeHtml(book.subtitle)}</h2>` : ''}${book.description ? `<p>${escapeHtml(book.description)}</p>` : ''}<p>${book.editor ? `Compiled or edited by ${escapeHtml(book.editor)}<br>` : ''}${escapeHtml(book.edition)} · ${escapeHtml(formatDate(now()))}</p></section>${book.frontMatter.preface ? `<section class="book-front"><h2>Preface</h2>${paragraphs(book.frontMatter.preface)}</section>` : ''}${book.frontMatter.introduction ? `<section class="book-front"><h2>Introduction</h2>${paragraphs(book.frontMatter.introduction)}</section>` : ''}${toc}${chapters.join('')}${book.backMatter.conclusion ? `<section class="book-back"><h2>Conclusion</h2>${paragraphs(book.backMatter.conclusion)}</section>` : ''}${provenance}<p class="book-running-note">Generated with Sustainable Catalyst Library ${escapeHtml(version)} · ${escapeHtml(bookSchema)}</p></main>${autoPrint ? '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),350))<\/script>' : ''}</body></html>`;
    return html;
  };

  const download = (content, filename, type) => {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = filename; document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  const makeController = (host) => {
    const stage = host.matches('[data-sc-library-books-inline]') ? host : host.querySelector('[data-sc-library-books-stage]');
    if (!stage) return null;
    let activeId = host.dataset.bookId || workspace.books[0]?.id || '';
    let draft = activeId && bookById(activeId) ? deepClone(bookById(activeId)) : makeBook();
    let tab = 'overview';
    let notice = '';
    let noticeType = 'success';

    const setNotice = (message, type = 'success') => { notice = message; noticeType = type; render(); setTimeout(() => { if (notice === message) { notice = ''; render(); } }, 4000); };
    const saveDraft = () => {
      draft.updatedAt = now();
      const existing = bookById(draft.id);
      if (existing) Object.assign(existing, deepClone(draft)); else workspace.books.push(deepClone(draft));
      activeId = draft.id; persist(); setNotice(strings.saved || 'Book project saved.'); renderAll();
    };
    const addArtifact = (artifact) => {
      if (draft.items.some((item) => itemKey(item) === artifactKey(artifact.type, artifact.refId))) return;
      draft.items.push({ id: uid('bookitem'), type: artifact.type, refId: String(artifact.refId), title: artifact.title, excerpt: artifact.excerpt || '', sourceUrl: artifact.sourceUrl || '', presentation: 'full', content: '', createdAt: now() });
      draft.updatedAt = now(); tab = 'contents'; render();
    };

    const bookListHtml = () => workspace.books.length ? workspace.books.slice().sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((book) => `<button type="button" data-select-book="${escapeAttr(book.id)}" class="${book.id === activeId ? 'is-active' : ''}"><strong>${escapeHtml(book.title)}</strong><small>${book.items.length} sections · ${escapeHtml(formatDate(book.updatedAt))}</small></button>`).join('') : '<p>No saved books.</p>';
    const overview = () => `<div class="sc-library-books__summary-grid"><div class="sc-library-books__metric"><strong>${draft.items.length}</strong><span>Book sections</span></div><div class="sc-library-books__metric"><strong>${workspace.savedRecords.length}</strong><span>Available publications</span></div><div class="sc-library-books__metric"><strong>${workspace.notes.length + workspace.sources.length}</strong><span>Notes and sources</span></div><div class="sc-library-books__metric"><strong>${workspace.matrices.length + workspace.boards.length + workspace.annotations.length}</strong><span>Research artifacts</span></div></div><div class="sc-library-books__help"><strong>Build a book from the Library, not a webpage printout.</strong><p>Select full publications or summaries, insert personal notes and sources, add matrices, boards, and handwriting, arrange the sequence, then generate a themed browser preview that can be saved as PDF.</p></div><div class="sc-library-books__actions"><button type="button" data-book-tab="details">Edit book details</button><button type="button" data-book-tab="contents">Build contents</button><button type="button" data-book-tab="export">Generate PDF edition</button></div>`;
    const details = () => `<form class="sc-library-books__form" data-book-details><div class="sc-library-books__grid"><label><span>Book title</span><input required name="title" value="${escapeAttr(draft.title)}"></label><label><span>Subtitle</span><input name="subtitle" value="${escapeAttr(draft.subtitle)}"></label></div><div class="sc-library-books__grid"><label><span>Compiler or editor</span><input name="editor" value="${escapeAttr(draft.editor)}"></label><label><span>Edition</span><input name="edition" value="${escapeAttr(draft.edition)}"></label></div><label><span>Description</span><textarea name="description" rows="3">${escapeHtml(draft.description)}</textarea></label><div class="sc-library-books__grid sc-library-books__grid--three"><label><span>Print theme</span><select name="theme">${themeOptions(draft.theme)}</select></label><label><span>Page size</span><select name="pageSize">${pageSizeOptions(draft.pageSize)}</select></label><label><span>Video treatment</span><select name="mediaMode">${mediaModeOptions(draft.mediaMode)}</select></label></div><label><span>Preface</span><textarea name="preface" rows="5">${escapeHtml(draft.frontMatter.preface)}</textarea></label><label><span>Introduction</span><textarea name="introduction" rows="5">${escapeHtml(draft.frontMatter.introduction)}</textarea></label><label><span>Conclusion</span><textarea name="conclusion" rows="5">${escapeHtml(draft.backMatter.conclusion)}</textarea></label><fieldset><legend>Edition options</legend><div class="sc-library-books__checks"><label><input type="checkbox" name="includeToc" ${draft.includeToc ? 'checked' : ''}> Table of contents</label><label><input type="checkbox" name="includeManifest" ${draft.includeManifest ? 'checked' : ''}> Provenance manifest</label><label><input type="checkbox" name="includeCitations" ${draft.includeCitations ? 'checked' : ''}> Citations</label><label><input type="checkbox" name="includeAnnotations" ${draft.includeAnnotations ? 'checked' : ''}> Annotation layers</label><label><input type="checkbox" name="grayscale" ${draft.grayscale ? 'checked' : ''}> Grayscale / minimal ink</label></div></fieldset><fieldset><legend>Collections</legend><div class="sc-library-books__checks">${collectionOptions(draft.collectionIds)}</div></fieldset><div class="sc-library-books__actions"><button class="is-primary" type="submit">Save book project</button></div></form>`;
    const contents = () => {
      const included = new Set(draft.items.map(itemKey));
      return `<form class="sc-library-books__custom-form" data-custom-section><div class="sc-library-books__grid"><label><span>Custom section title</span><input required name="title" placeholder="Editorial bridge, introduction, reflection, or appendix"></label><label><span>Section type</span><select name="presentation"><option value="full">Chapter or section</option><option value="summary">Short transition</option></select></label></div><label><span>Custom text</span><textarea required name="content" rows="4"></textarea></label><button type="submit">Add custom section</button></form><h3>Current book sequence</h3><div class="sc-library-books__sequence">${draft.items.length ? draft.items.map((item, index) => `<article><span class="sc-library-books__sequence-index">${index + 1}</span><div><span>${escapeHtml(item.type.replace(/_/g, ' '))}</span><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(item.excerpt || item.content || '')}</p>${item.type === 'record' ? `<label><span>Publication treatment</span><select data-book-presentation="${escapeAttr(item.id)}"><option value="full" ${item.presentation === 'full' ? 'selected' : ''}>Full normalized article</option><option value="summary" ${item.presentation === 'summary' ? 'selected' : ''}>Summary only</option></select></label>` : ''}</div><div class="sc-library-books__row-actions"><button type="button" data-move-book-item="${escapeAttr(item.id)}" data-direction="up" ${index === 0 ? 'disabled' : ''}>↑</button><button type="button" data-move-book-item="${escapeAttr(item.id)}" data-direction="down" ${index === draft.items.length - 1 ? 'disabled' : ''}>↓</button><button type="button" data-remove-book-item="${escapeAttr(item.id)}">Remove</button></div></article>`).join('') : '<p class="sc-library-books__empty">No sections yet. Add publications and research artifacts below.</p>'}</div><h3>Available research material</h3><div class="sc-library-books__item-browser">${allArtifacts().map((artifact) => `<article class="sc-library-books__available"><div><span>${escapeHtml(artifact.meta)}</span><h4>${escapeHtml(artifact.title)}</h4><p>${escapeHtml(artifact.excerpt || '')}</p></div><button type="button" data-add-book-artifact="${escapeAttr(artifact.type)}|${escapeAttr(artifact.refId)}" ${included.has(artifactKey(artifact.type, artifact.refId)) ? 'disabled' : ''}>${included.has(artifactKey(artifact.type, artifact.refId)) ? 'Added' : 'Add'}</button></article>`).join('') || '<p class="sc-library-books__empty">Save publications, notes, sources, matrices, boards, or annotations in the Notebook to add them here.</p>'}</div>`;
    };
    const exportTab = () => { const manifest = { schema: bookSchema, id: draft.id, title: draft.title, edition: draft.edition, theme: draft.theme, pageSize: draft.pageSize, mediaMode: draft.mediaMode, sections: draft.items.map((item, index) => ({ position: index + 1, type: item.type, reference_id: item.refId, title: item.title })) }; return `<div class="sc-library-books__help"><strong>PDF generation</strong><p>The preview creates a complete print document using normalized publication content and selected research artifacts. Choose <em>Print / Save as PDF</em> in the preview. Direct MP4 media can play in the browser preview; PDF output always retains a clickable fallback link and source metadata.</p></div><div class="sc-library-books__actions"><button class="is-primary" type="button" data-preview-book>Open preview / Save as PDF</button><button type="button" data-download-book-html>Download portable HTML edition</button><button type="button" data-export-book-json>Export editable book JSON</button><button type="button" data-duplicate-book>Duplicate book</button><button class="sc-library-books__danger" type="button" data-delete-book>Delete book</button></div><h3>Edition manifest</h3><pre class="sc-library-books__manifest">${escapeHtml(JSON.stringify(manifest, null, 2))}</pre>`; };

    const render = () => {
      stage.innerHTML = `<section class="sc-library-books"><header class="sc-library-books__header"><div><p class="sc-library__eyebrow">Local-first book project</p><h3>${escapeHtml(draft.title)}</h3><p>${escapeHtml(draft.subtitle || 'Build, arrange, annotate, and export a custom research edition.')}</p></div><div class="sc-library-books__actions"><button class="is-primary" type="button" data-save-book>Save project</button></div></header>${notice ? `<div class="sc-library-books__notice ${noticeType === 'error' ? 'is-error' : ''}">${escapeHtml(notice)}</div>` : ''}<div class="sc-library-books__layout"><aside class="sc-library-books__sidebar"><button type="button" data-new-book>New book</button><div class="sc-library-books__book-list">${bookListHtml()}</div></aside><main class="sc-library-books__main"><nav class="sc-library-books__tabs"><button type="button" data-book-tab="overview" class="${tab === 'overview' ? 'is-active' : ''}">Overview</button><button type="button" data-book-tab="details" class="${tab === 'details' ? 'is-active' : ''}">Book details</button><button type="button" data-book-tab="contents" class="${tab === 'contents' ? 'is-active' : ''}">Contents</button><button type="button" data-book-tab="export" class="${tab === 'export' ? 'is-active' : ''}">PDF / Export</button></nav>${tab === 'details' ? details() : tab === 'contents' ? contents() : tab === 'export' ? exportTab() : overview()}</main></div></section>`;
    };

    stage.addEventListener('click', async (event) => {
      const newButton = event.target.closest('[data-new-book]');
      if (newButton) { activeId = ''; draft = makeBook(); tab = 'details'; render(); return; }
      const select = event.target.closest('[data-select-book]');
      if (select) { const found = bookById(select.dataset.selectBook); if (found) { activeId = found.id; draft = deepClone(found); tab = 'overview'; render(); } return; }
      const tabButton = event.target.closest('[data-book-tab]');
      if (tabButton) { tab = tabButton.dataset.bookTab; render(); return; }
      if (event.target.closest('[data-save-book]')) { saveDraft(); return; }
      const add = event.target.closest('[data-add-book-artifact]');
      if (add) { const [type, refId] = add.dataset.addBookArtifact.split('|'); const artifact = allArtifacts().find((item) => item.type === type && String(item.refId) === String(refId)); if (artifact) addArtifact(artifact); return; }
      const remove = event.target.closest('[data-remove-book-item]');
      if (remove) { draft.items = draft.items.filter((item) => item.id !== remove.dataset.removeBookItem); render(); return; }
      const move = event.target.closest('[data-move-book-item]');
      if (move) { const index = draft.items.findIndex((item) => item.id === move.dataset.moveBookItem); const next = move.dataset.direction === 'up' ? index - 1 : index + 1; if (index >= 0 && next >= 0 && next < draft.items.length) { [draft.items[index], draft.items[next]] = [draft.items[next], draft.items[index]]; render(); } return; }
      if (event.target.closest('[data-export-book-json]')) { download(JSON.stringify(draft, null, 2), `sustainable-catalyst-book-${slug(draft.title)}-${today()}.json`, 'application/json'); return; }
      if (event.target.closest('[data-duplicate-book]')) { draft = makeBook({ ...deepClone(draft), id: '', title: `${draft.title} — Copy`, createdAt: '' }); activeId = ''; saveDraft(); return; }
      if (event.target.closest('[data-delete-book]')) { if (!window.confirm(strings.confirmDelete || 'Delete this book project?')) return; workspace.books = workspace.books.filter((item) => item.id !== draft.id); persist(); activeId = workspace.books[0]?.id || ''; draft = activeId ? deepClone(bookById(activeId)) : makeBook(); tab = 'overview'; setNotice(strings.deleted || 'Book deleted.'); renderAll(); return; }
      if (event.target.closest('[data-preview-book]')) { saveDraft(); const win = window.open('', '_blank'); if (!win) { setNotice(strings.blockedPopup || 'Popup blocked.', 'error'); return; } win.document.write('<p style="font-family:Arial;padding:30px">Preparing book content…</p>'); try { const html = await buildBookHtml(draft); win.document.open(); win.document.write(html); win.document.close(); } catch (error) { win.document.body.innerHTML = `<p>${escapeHtml(strings.contentError || 'Could not prepare all content.')}</p>`; } return; }
      if (event.target.closest('[data-download-book-html]')) { saveDraft(); const html = await buildBookHtml(draft); download(html, `sustainable-catalyst-book-${slug(draft.title)}-${today()}.html`, 'text/html'); return; }
    });

    stage.addEventListener('change', (event) => {
      const presentation = event.target.closest('[data-book-presentation]');
      if (presentation) { const item = draft.items.find((entry) => entry.id === presentation.dataset.bookPresentation); if (item) item.presentation = presentation.value; }
    });

    stage.addEventListener('submit', (event) => {
      event.preventDefault();
      const form = event.target;
      const data = new FormData(form);
      if (form.matches('[data-book-details]')) {
        draft.title = words(data.get('title')) || 'Untitled Research Book'; draft.subtitle = words(data.get('subtitle')); draft.editor = words(data.get('editor')); draft.edition = words(data.get('edition')) || 'First edition'; draft.description = words(data.get('description')); draft.theme = words(data.get('theme')); draft.pageSize = words(data.get('pageSize')); draft.mediaMode = words(data.get('mediaMode')); draft.frontMatter.preface = words(data.get('preface')); draft.frontMatter.introduction = words(data.get('introduction')); draft.backMatter.conclusion = words(data.get('conclusion')); draft.includeToc = data.has('includeToc'); draft.includeManifest = data.has('includeManifest'); draft.includeCitations = data.has('includeCitations'); draft.includeAnnotations = data.has('includeAnnotations'); draft.grayscale = data.has('grayscale'); draft.collectionIds = data.getAll('collectionIds').map(String); if (!draft.collectionIds.length) draft.collectionIds = ['collection_inbox']; saveDraft(); return;
      }
      if (form.matches('[data-custom-section]')) { draft.items.push({ id: uid('bookitem'), type: 'custom', refId: '', title: words(data.get('title')), excerpt: words(data.get('content')).slice(0, 180), content: words(data.get('content')), sourceUrl: '', presentation: words(data.get('presentation')) || 'full', createdAt: now() }); tab = 'contents'; render(); }
    });

    const seedRecord = (record) => {
      if (!record?.id) return;
      const artifact = { type: 'record', refId: String(record.id), title: record.title, excerpt: record.excerpt || '', sourceUrl: record.url || '', meta: record.type_label || 'Library publication' };
      if (!activeId || !bookById(activeId)) { draft = makeBook({ title: `Research Collection: ${record.title}` }); activeId = ''; }
      addArtifact(artifact); tab = 'contents'; render();
    };

    render();
    return { render, seedRecord, newBook(seed = {}) { activeId = ''; draft = makeBook(seed); tab = 'details'; render(); } };
  };

  const mountAll = () => {
    document.querySelectorAll('[data-sc-library-books-root], [data-sc-library-books-inline]').forEach((host) => {
      if (controllers.has(host)) return;
      const controller = makeController(host);
      if (controller) controllers.set(host, controller);
    });
  };
  const renderAll = () => { workspace = loadWorkspace(); normalizeBooks(); mountAll(); controllers.forEach?.(() => {}); document.querySelectorAll('[data-sc-library-books-root], [data-sc-library-books-inline]').forEach((host) => controllers.get(host)?.render()); };
  const firstController = () => { mountAll(); return Array.from(document.querySelectorAll('[data-sc-library-books-root], [data-sc-library-books-inline]')).map((host) => controllers.get(host)).find(Boolean); };

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-book-library]')) { const controller = firstController(); controller?.render(); }
  });
  document.addEventListener('sc-library-books-render', mountAll);
  document.addEventListener('sc-library-books-seed-record', (event) => { firstController()?.seedRecord(event.detail?.record); });
  document.addEventListener('sc-library-new-book', (event) => { firstController()?.newBook(event.detail || {}); });
  window.addEventListener('storage', (event) => { if (event.key === storageKey) renderAll(); });
  window.addEventListener('sc-library-workspace-updated', () => { workspace = loadWorkspace(); normalizeBooks(); mountAll(); document.querySelectorAll('[data-sc-library-books-root], [data-sc-library-books-inline]').forEach((host) => controllers.get(host)?.render()); });
  mountAll();
})();
