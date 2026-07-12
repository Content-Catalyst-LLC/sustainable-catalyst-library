(() => {
  'use strict';

  const shared = window.SCAnnotationsShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const workspaceSchema = shared.workspaceSchema || 'sc-library-workspace/1.5';
  const annotationSchema = shared.schema || 'sc-library-annotation/1.0';
  const version = shared.version || '1.9.0';
  const strings = shared.strings || {};
  const tools = Array.isArray(shared.tools) ? shared.tools : [];
  const targetTypes = Array.isArray(shared.targetTypes) ? shared.targetTypes : [];
  const pageStyles = Array.isArray(shared.pageStyles) ? shared.pageStyles : [];
  const defaultPageStyle = shared.defaultPageStyle || 'reader';

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));
  const now = () => new Date().toISOString();
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`;
  const words = (value) => String(value || '').trim();
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const formatDate = (value) => {
    const date = new Date(value || '');
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };
  const slug = (value) => words(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'annotation';
  const deepClone = (value) => JSON.parse(JSON.stringify(value));
  const labelFor = (items, id, fallback = id) => items.find((item) => item.id === id)?.label || fallback;

  let workspace = null;
  let storageAvailable = true;
  let draft = null;
  let activeTool = 'pen';
  let currentStroke = null;
  let shapeStart = null;
  let history = [];
  let future = [];
  let noteDrag = null;
  let globalStudio = null;
  let globalLibrary = null;
  let selectedLayer = 'ink';

  const baseWorkspace = () => ({
    schema: workspaceSchema,
    version,
    createdAt: now(),
    updatedAt: now(),
    collections: [{ id: 'collection_inbox', title: 'Research Inbox', description: 'Newly saved Library records and research material.', createdAt: now(), updatedAt: now() }],
    savedRecords: [], notes: [], sources: [], matrices: [], boards: [], handoffs: [], annotations: [], books: [],
  });

  const normalizeWorkspace = (input) => {
    const data = input && typeof input === 'object' ? input : baseWorkspace();
    return {
      ...baseWorkspace(),
      ...data,
      schema: workspaceSchema,
      version,
      collections: Array.isArray(data.collections) ? data.collections : [],
      savedRecords: Array.isArray(data.savedRecords) ? data.savedRecords : [],
      notes: Array.isArray(data.notes) ? data.notes : [],
      sources: Array.isArray(data.sources) ? data.sources : [],
      matrices: Array.isArray(data.matrices) ? data.matrices : [],
      boards: Array.isArray(data.boards) ? data.boards : [],
      handoffs: Array.isArray(data.handoffs) ? data.handoffs : [],
      annotations: Array.isArray(data.annotations) ? data.annotations.map(normalizeAnnotation) : [],
      books: Array.isArray(data.books) ? data.books : [],
      updatedAt: now(),
    };
  };

  const loadWorkspace = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      workspace = normalizeWorkspace(raw ? JSON.parse(raw) : baseWorkspace());
    } catch (error) {
      storageAvailable = false;
      workspace = baseWorkspace();
    }
    return workspace;
  };

  const persistWorkspace = () => {
    workspace.schema = workspaceSchema;
    workspace.version = version;
    workspace.updatedAt = now();
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

  const makeAnnotation = (seed = {}) => ({
    schema: annotationSchema,
    id: seed.id || uid('annotation'),
    title: words(seed.title) || 'Untitled annotation',
    targetType: seed.targetType || 'custom',
    targetId: words(seed.targetId),
    targetTitle: words(seed.targetTitle),
    targetUrl: words(seed.targetUrl),
    targetExcerpt: words(seed.targetExcerpt),
    pageStyle: seed.pageStyle || defaultPageStyle,
    pageWidth: 900,
    pageHeight: 1120,
    collectionIds: Array.isArray(seed.collectionIds) && seed.collectionIds.length ? seed.collectionIds : ['collection_inbox'],
    layers: {
      ink: { visible: true, locked: false },
      highlights: { visible: true, locked: false },
      shapes: { visible: true, locked: false },
      notes: { visible: true, locked: false },
      ...(seed.layers || {}),
    },
    strokes: Array.isArray(seed.strokes) ? seed.strokes : [],
    shapes: Array.isArray(seed.shapes) ? seed.shapes : [],
    notes: Array.isArray(seed.notes) ? seed.notes : [],
    transcription: words(seed.transcription),
    privateNotes: words(seed.privateNotes),
    tags: Array.isArray(seed.tags) ? seed.tags : [],
    createdAt: seed.createdAt || now(),
    updatedAt: now(),
  });

  function normalizeAnnotation(item) {
    return makeAnnotation(item || {});
  }

  const annotationById = (id) => workspace.annotations.find((item) => item.id === id);
  const targetLabel = (type) => labelFor(targetTypes, type, type.replace(/_/g, ' '));
  const collectionLabel = (id) => workspace.collections.find((item) => item.id === id)?.title || 'Research Inbox';

  const download = (content, filename, type = 'application/json') => {
    const blob = content instanceof Blob ? content : new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  const snapshot = () => {
    if (!draft) return;
    history.push(JSON.stringify(draft));
    if (history.length > 80) history.shift();
    future = [];
  };

  const restoreSnapshot = (value) => {
    if (!value) return;
    draft = normalizeAnnotation(JSON.parse(value));
    renderStudio();
  };

  const studioMarkup = () => `
    <section class="sc-annotation-studio" data-annotation-studio hidden>
      <header class="sc-annotation-studio__header">
        <div><h2 data-annotation-heading>Annotation Studio</h2><p data-annotation-subheading>Local-first annotation and handwriting layer</p></div>
        <div class="sc-annotation-studio__header-actions">
          <button type="button" data-annotation-undo>Undo</button>
          <button type="button" data-annotation-redo>Redo</button>
          <button type="button" data-annotation-export="json">JSON</button>
          <button type="button" data-annotation-export="svg">SVG</button>
          <button type="button" data-annotation-export="png">PNG</button>
          <button type="button" data-annotation-export="print">Print / PDF</button>
          <button type="button" class="sc-annotation-studio__save" data-annotation-save>Save</button>
          <button type="button" data-annotation-close>Close</button>
        </div>
      </header>
      <div class="sc-annotation-studio__layout">
        <aside class="sc-annotation-studio__toolbar">
          <h3>Tools</h3>
          <div class="sc-annotation-tools">${tools.map((tool) => `<button type="button" data-annotation-tool="${escapeHtml(tool.id)}">${escapeHtml(tool.label)}</button>`).join('')}</div>
          <label class="sc-annotation-control"><span>Stroke width</span><input type="range" min="1" max="28" step="1" value="4" data-annotation-width></label>
          <label class="sc-annotation-control"><span>Ink color</span><input type="color" value="#721019" data-annotation-color></label>
          <label class="sc-annotation-control"><span>Opacity</span><input type="range" min="0.1" max="1" step="0.05" value="1" data-annotation-opacity></label>
          <h3>Layers</h3>
          <div class="sc-annotation-layer-list" data-annotation-layers></div>
          <button type="button" data-annotation-clear-layer>Clear active layer</button>
        </aside>
        <main class="sc-annotation-studio__viewport">
          <article class="sc-annotation-page" data-annotation-page>
            <div class="sc-annotation-page__source" data-annotation-source></div>
            <canvas class="sc-annotation-canvas" data-annotation-canvas></canvas>
            <div class="sc-annotation-notes" data-annotation-notes></div>
          </article>
        </main>
        <aside class="sc-annotation-studio__sidebar">
          <h3>Annotation record</h3>
          <div class="sc-annotation-meta">
            <label><span>Title</span><input type="text" data-annotation-field="title"></label>
            <label><span>Target type</span><select data-annotation-field="targetType">${targetTypes.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.label)}</option>`).join('')}</select></label>
            <label><span>Target title</span><input type="text" data-annotation-field="targetTitle"></label>
            <label><span>Target URL</span><input type="url" data-annotation-field="targetUrl"></label>
            <label><span>Target passage or page text</span><textarea data-annotation-field="targetExcerpt"></textarea></label>
            <label><span>Page style</span><select data-annotation-field="pageStyle">${pageStyles.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.label)}</option>`).join('')}</select></label>
            <label><span>Accessible handwriting transcription</span><textarea data-annotation-field="transcription"></textarea></label>
            <label><span>Private editorial notes</span><textarea data-annotation-field="privateNotes"></textarea></label>
            <label><span>Tags</span><input type="text" data-annotation-tags placeholder="comma, separated"></label>
            <label><span>Collection</span><select data-annotation-collection></select></label>
            <label><span>Anchor for next note</span><textarea data-annotation-anchor placeholder="Section, quotation, page, timestamp, figure, or equation"></textarea></label>
          </div>
          <div class="sc-annotation-studio__status" data-annotation-status hidden></div>
        </aside>
      </div>
    </section>`;

  const libraryMarkup = () => `
    <section class="sc-annotation-library" data-annotation-library hidden>
      <div class="sc-annotation-library__panel">
        <header class="sc-annotation-library__header"><div><p class="sc-library__eyebrow">Local-first annotations</p><h2>Saved Annotation Studio records</h2></div><button type="button" data-annotation-library-close>Close</button></header>
        <div class="sc-annotation-library__grid" data-annotation-library-grid></div>
      </div>
    </section>`;

  const ensureGlobalUi = () => {
    if (!globalStudio) {
      document.body.insertAdjacentHTML('beforeend', studioMarkup());
      globalStudio = document.querySelector('[data-annotation-studio]');
      bindStudio(globalStudio);
    }
    if (!globalLibrary) {
      document.body.insertAdjacentHTML('beforeend', libraryMarkup());
      globalLibrary = document.querySelector('[data-annotation-library]');
      bindLibrary(globalLibrary);
    }
  };

  const status = (message, kind = 'success') => {
    const box = globalStudio?.querySelector('[data-annotation-status]');
    if (!box) return;
    box.hidden = !message;
    box.dataset.kind = kind;
    box.textContent = message;
  };

  const canvasAndContext = () => {
    const canvas = globalStudio.querySelector('[data-annotation-canvas]');
    const page = globalStudio.querySelector('[data-annotation-page]');
    const rect = page.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const width = Math.max(1, Math.round(rect.width));
    const height = Math.max(1, Math.round(rect.height));
    if (canvas.width !== Math.round(width * ratio) || canvas.height !== Math.round(height * ratio)) {
      canvas.width = Math.round(width * ratio);
      canvas.height = Math.round(height * ratio);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
    }
    const ctx = canvas.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    return { canvas, ctx, width, height, page };
  };

  const pointFromEvent = (event, page) => {
    const rect = page.getBoundingClientRect();
    return {
      x: clamp((event.clientX - rect.left) / rect.width, 0, 1),
      y: clamp((event.clientY - rect.top) / rect.height, 0, 1),
      p: event.pressure && event.pressure > 0 ? event.pressure : 0.5,
    };
  };

  const drawArrowHead = (ctx, x1, y1, x2, y2, size) => {
    const angle = Math.atan2(y2 - y1, x2 - x1);
    ctx.beginPath();
    ctx.moveTo(x2, y2);
    ctx.lineTo(x2 - size * Math.cos(angle - Math.PI / 6), y2 - size * Math.sin(angle - Math.PI / 6));
    ctx.moveTo(x2, y2);
    ctx.lineTo(x2 - size * Math.cos(angle + Math.PI / 6), y2 - size * Math.sin(angle + Math.PI / 6));
    ctx.stroke();
  };

  const renderCanvas = () => {
    if (!draft || !globalStudio || globalStudio.hidden) return;
    const { ctx, width, height } = canvasAndContext();
    ctx.clearRect(0, 0, width, height);

    draft.strokes.forEach((stroke) => {
      const layer = stroke.kind === 'highlighter' ? 'highlights' : 'ink';
      if (!draft.layers[layer]?.visible || !stroke.points?.length) return;
      ctx.save();
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.globalAlpha = Number(stroke.opacity ?? (stroke.kind === 'highlighter' ? 0.28 : 1));
      ctx.strokeStyle = stroke.color || '#721019';
      ctx.lineWidth = Number(stroke.width || 4);
      ctx.beginPath();
      stroke.points.forEach((point, index) => {
        const x = point.x * width;
        const y = point.y * height;
        if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();
      ctx.restore();
    });

    if (draft.layers.shapes?.visible) {
      draft.shapes.forEach((shape) => {
        const x1 = shape.x1 * width; const y1 = shape.y1 * height;
        const x2 = shape.x2 * width; const y2 = shape.y2 * height;
        ctx.save();
        ctx.globalAlpha = Number(shape.opacity || 1);
        ctx.strokeStyle = shape.color || '#721019';
        ctx.lineWidth = Number(shape.width || 3);
        ctx.setLineDash(shape.dashed ? [8, 6] : []);
        if (shape.type === 'rectangle') ctx.strokeRect(Math.min(x1, x2), Math.min(y1, y2), Math.abs(x2 - x1), Math.abs(y2 - y1));
        else if (shape.type === 'ellipse') {
          ctx.beginPath();
          ctx.ellipse((x1 + x2) / 2, (y1 + y2) / 2, Math.abs(x2 - x1) / 2, Math.abs(y2 - y1) / 2, 0, 0, Math.PI * 2);
          ctx.stroke();
        } else if (shape.type === 'arrow') {
          ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
          drawArrowHead(ctx, x1, y1, x2, y2, Math.max(10, Number(shape.width || 3) * 4));
        }
        ctx.restore();
      });
    }
  };

  const renderNotes = () => {
    const layer = globalStudio.querySelector('[data-annotation-notes]');
    if (!draft.layers.notes?.visible) {
      layer.innerHTML = '';
      return;
    }
    layer.innerHTML = draft.notes.map((note) => `
      <article class="sc-annotation-note" data-annotation-note="${escapeHtml(note.id)}" style="left:${Number(note.x) * 100}%;top:${Number(note.y) * 100}%">
        <button type="button" data-delete-annotation-note="${escapeHtml(note.id)}" aria-label="Delete note">×</button>
        ${escapeHtml(note.text)}
        ${note.anchor ? `<small>Anchor: ${escapeHtml(note.anchor)}</small>` : ''}
      </article>`).join('');
  };

  const renderSource = () => {
    const source = globalStudio.querySelector('[data-annotation-source]');
    source.innerHTML = `
      <span class="sc-annotation-target-type">${escapeHtml(targetLabel(draft.targetType))}</span>
      <h1>${escapeHtml(draft.targetTitle || draft.title || 'Annotation page')}</h1>
      ${draft.targetUrl ? `<p><a href="${escapeHtml(draft.targetUrl)}" target="_blank" rel="noopener">Open original source ↗</a></p>` : ''}
      <p>${escapeHtml(draft.targetExcerpt || 'Use this page for handwriting, highlights, diagrams, anchored notes, and research commentary.')}</p>`;
  };

  const renderLayers = () => {
    const container = globalStudio.querySelector('[data-annotation-layers]');
    container.innerHTML = Object.entries(draft.layers).map(([id, layer]) => `
      <label><span><input type="radio" name="annotation-layer" value="${escapeHtml(id)}" ${selectedLayer === id ? 'checked' : ''}> ${escapeHtml(labelFor(shared.layerTypes || [], id, id))}</span><span><input type="checkbox" data-layer-visible="${escapeHtml(id)}" ${layer.visible ? 'checked' : ''}> visible</span></label>`).join('');
  };

  const renderStudio = () => {
    if (!draft || !globalStudio) return;
    globalStudio.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    globalStudio.querySelector('[data-annotation-heading]').textContent = draft.title || 'Annotation Studio';
    globalStudio.querySelector('[data-annotation-subheading]').textContent = `${targetLabel(draft.targetType)} · ${formatDate(draft.updatedAt)}`;
    globalStudio.querySelectorAll('[data-annotation-tool]').forEach((button) => button.setAttribute('aria-pressed', button.dataset.annotationTool === activeTool ? 'true' : 'false'));
    globalStudio.querySelectorAll('[data-annotation-field]').forEach((field) => { field.value = draft[field.dataset.annotationField] || ''; });
    globalStudio.querySelector('[data-annotation-tags]').value = (draft.tags || []).join(', ');
    const collectionSelect = globalStudio.querySelector('[data-annotation-collection]');
    collectionSelect.innerHTML = workspace.collections.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.title)}</option>`).join('');
    collectionSelect.value = draft.collectionIds?.[0] || 'collection_inbox';
    const page = globalStudio.querySelector('[data-annotation-page]');
    page.className = `sc-annotation-page sc-annotation-page--${draft.pageStyle || defaultPageStyle}`;
    renderLayers();
    renderSource();
    renderNotes();
    requestAnimationFrame(renderCanvas);
    status(storageAvailable ? '' : (strings.storageError || 'Browser storage is unavailable.'), 'error');
  };

  const saveDraft = () => {
    if (!draft) return;
    draft.updatedAt = now();
    const existing = annotationById(draft.id);
    if (existing) Object.assign(existing, deepClone(draft)); else workspace.annotations.push(deepClone(draft));
    const ok = persistWorkspace();
    renderInlineLists();
    renderLibrary();
    status(ok ? (strings.saved || 'Annotation saved.') : (strings.storageError || 'Browser storage is unavailable.'), ok ? 'success' : 'error');
    document.dispatchEvent(new CustomEvent('sc-library-annotation-saved', { detail: { annotation: deepClone(draft) } }));
  };

  const closeStudio = () => {
    if (globalStudio) globalStudio.hidden = true;
    document.documentElement.style.overflow = '';
    currentStroke = null;
    shapeStart = null;
  };

  const openDraft = (seed = {}) => {
    ensureGlobalUi();
    loadWorkspace();
    const existing = seed.id ? annotationById(seed.id) : null;
    draft = normalizeAnnotation(existing ? deepClone(existing) : makeAnnotation(seed));
    history = [];
    future = [];
    selectedLayer = 'ink';
    activeTool = 'pen';
    renderStudio();
  };

  const eraseAt = (point) => {
    const radius = 0.026;
    const closePoint = (p) => Math.hypot(p.x - point.x, p.y - point.y) < radius;
    draft.strokes = draft.strokes.filter((stroke) => !(stroke.points || []).some(closePoint));
    draft.shapes = draft.shapes.filter((shape) => {
      const cx = (shape.x1 + shape.x2) / 2; const cy = (shape.y1 + shape.y2) / 2;
      return Math.hypot(cx - point.x, cy - point.y) >= radius * 1.5;
    });
  };

  const compositeToCanvas = () => {
    const sourceCanvas = globalStudio.querySelector('[data-annotation-canvas]');
    const page = globalStudio.querySelector('[data-annotation-page]');
    const out = document.createElement('canvas');
    out.width = 1400; out.height = 1742;
    const ctx = out.getContext('2d');
    const dark = draft.pageStyle === 'dark';
    ctx.fillStyle = dark ? '#101b18' : '#ffffff';
    ctx.fillRect(0, 0, out.width, out.height);
    ctx.fillStyle = dark ? '#d7ffe4' : '#111111';
    ctx.font = 'bold 42px Arial';
    ctx.fillText(draft.targetTitle || draft.title, 90, 105, out.width - 180);
    ctx.font = '24px Arial';
    const text = draft.targetExcerpt || '';
    const wordsList = text.split(/\s+/); let line = ''; let y = 160;
    wordsList.forEach((word) => {
      const test = `${line}${word} `;
      if (ctx.measureText(test).width > out.width - 180) { ctx.fillText(line, 90, y); line = `${word} `; y += 38; } else line = test;
    });
    if (line) ctx.fillText(line, 90, y);
    ctx.drawImage(sourceCanvas, 0, 0, page.clientWidth, page.clientHeight, 0, 0, out.width, out.height);
    draft.notes.forEach((note) => {
      ctx.fillStyle = '#fff4a7';
      const x = note.x * out.width; const ny = note.y * out.height;
      ctx.fillRect(x, ny, 300, 110);
      ctx.strokeStyle = '#b59a27'; ctx.strokeRect(x, ny, 300, 110);
      ctx.fillStyle = '#111'; ctx.font = '18px Arial'; ctx.fillText(note.text.slice(0, 42), x + 12, ny + 30);
      if (note.anchor) { ctx.font = '14px Arial'; ctx.fillText(`Anchor: ${note.anchor.slice(0, 34)}`, x + 12, ny + 58); }
    });
    return out;
  };

  const annotationSvg = () => {
    const width = draft.pageWidth || 900; const height = draft.pageHeight || 1120;
    const dark = draft.pageStyle === 'dark';
    const paths = draft.strokes.filter((stroke) => draft.layers[stroke.kind === 'highlighter' ? 'highlights' : 'ink']?.visible).map((stroke) => {
      const points = (stroke.points || []).map((point) => `${(point.x * width).toFixed(2)},${(point.y * height).toFixed(2)}`).join(' ');
      return `<polyline points="${points}" fill="none" stroke="${escapeHtml(stroke.color || '#721019')}" stroke-width="${Number(stroke.width || 4)}" stroke-linecap="round" stroke-linejoin="round" opacity="${Number(stroke.opacity || 1)}"/>`;
    }).join('');
    const shapes = draft.layers.shapes?.visible ? draft.shapes.map((shape) => {
      const x1 = shape.x1 * width; const y1 = shape.y1 * height; const x2 = shape.x2 * width; const y2 = shape.y2 * height;
      if (shape.type === 'rectangle') return `<rect x="${Math.min(x1, x2)}" y="${Math.min(y1, y2)}" width="${Math.abs(x2 - x1)}" height="${Math.abs(y2 - y1)}" fill="none" stroke="${escapeHtml(shape.color)}" stroke-width="${shape.width}"/>`;
      if (shape.type === 'ellipse') return `<ellipse cx="${(x1 + x2) / 2}" cy="${(y1 + y2) / 2}" rx="${Math.abs(x2 - x1) / 2}" ry="${Math.abs(y2 - y1) / 2}" fill="none" stroke="${escapeHtml(shape.color)}" stroke-width="${shape.width}"/>`;
      return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${escapeHtml(shape.color)}" stroke-width="${shape.width}" marker-end="url(#arrow)"/>`;
    }).join('') : '';
    const notes = draft.layers.notes?.visible ? draft.notes.map((note) => `<g transform="translate(${note.x * width} ${note.y * height})"><rect width="210" height="88" rx="5" fill="#fff4a7" stroke="#b59a27"/><text x="10" y="24" font-family="Arial" font-size="14">${escapeHtml(note.text.slice(0, 28))}</text>${note.anchor ? `<text x="10" y="48" font-family="Arial" font-size="10">Anchor: ${escapeHtml(note.anchor.slice(0, 30))}</text>` : ''}</g>`).join('') : '';
    return `<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><defs><marker id="arrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#721019"/></marker></defs><rect width="100%" height="100%" fill="${dark ? '#101b18' : '#fff'}"/><text x="70" y="80" font-family="Arial" font-size="30" font-weight="700" fill="${dark ? '#d7ffe4' : '#111'}">${escapeHtml((draft.targetTitle || draft.title).slice(0, 55))}</text>${paths}${shapes}${notes}</svg>`;
  };

  const printHtml = () => {
    const svg = annotationSvg();
    return `<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(draft.title)}</title><style>@page{size:auto;margin:10mm}body{font-family:Arial,sans-serif;margin:0;color:#111}.meta{margin:0 0 12px;font-size:11px;color:#555}.page{width:100%}.page svg{width:100%;height:auto;border:1px solid #bbb}.transcription{margin-top:16px;border-top:1px solid #bbb;padding-top:10px;white-space:pre-wrap;font-size:12px}</style></head><body><h1>${escapeHtml(draft.title)}</h1><p class="meta">Target: ${escapeHtml(targetLabel(draft.targetType))} · Annotation ID: ${escapeHtml(draft.id)} · ${escapeHtml(annotationSchema)}</p><div class="page">${svg.replace(/^<\?xml[^>]+>/, '')}</div>${draft.transcription ? `<div class="transcription"><strong>Accessible handwriting transcription</strong><br>${escapeHtml(draft.transcription)}</div>` : ''}<script>window.addEventListener('load',()=>window.print())<\/script></body></html>`;
  };

  const exportDraft = (type) => {
    if (!draft) return;
    if (type === 'json') {
      download(JSON.stringify({ schema: annotationSchema, workspaceSchema, version, exportScope: { type: 'annotation', id: draft.id, title: draft.title }, annotation: draft }, null, 2), `annotation-${slug(draft.title)}.json`);
    } else if (type === 'svg') {
      download(annotationSvg(), `annotation-${slug(draft.title)}.svg`, 'image/svg+xml');
    } else if (type === 'png') {
      compositeToCanvas().toBlob((blob) => { if (blob) download(blob, `annotation-${slug(draft.title)}.png`, 'image/png'); });
    } else if (type === 'print') {
      const win = window.open('', '_blank');
      if (!win) { status(strings.blockedPopup || 'The browser blocked the print window.', 'error'); return; }
      win.document.open(); win.document.write(printHtml()); win.document.close();
    }
  };

  const bindStudio = (studio) => {
    studio.addEventListener('click', (event) => {
      const tool = event.target.closest('[data-annotation-tool]');
      if (tool) { activeTool = tool.dataset.annotationTool; if (activeTool === 'highlighter') selectedLayer = 'highlights'; else if (['rectangle', 'ellipse', 'arrow'].includes(activeTool)) selectedLayer = 'shapes'; else if (activeTool === 'note') selectedLayer = 'notes'; else selectedLayer = 'ink'; renderStudio(); return; }
      if (event.target.closest('[data-annotation-close]')) { closeStudio(); return; }
      if (event.target.closest('[data-annotation-save]')) { saveDraft(); return; }
      if (event.target.closest('[data-annotation-undo]')) { if (history.length) { future.push(JSON.stringify(draft)); restoreSnapshot(history.pop()); } return; }
      if (event.target.closest('[data-annotation-redo]')) { if (future.length) { history.push(JSON.stringify(draft)); restoreSnapshot(future.pop()); } return; }
      const exp = event.target.closest('[data-annotation-export]');
      if (exp) { exportDraft(exp.dataset.annotationExport); return; }
      if (event.target.closest('[data-annotation-clear-layer]')) {
        if (!window.confirm(strings.confirmClearLayer || 'Clear the active annotation layer?')) return;
        snapshot();
        if (selectedLayer === 'ink') draft.strokes = draft.strokes.filter((item) => item.kind === 'highlighter');
        else if (selectedLayer === 'highlights') draft.strokes = draft.strokes.filter((item) => item.kind !== 'highlighter');
        else if (selectedLayer === 'shapes') draft.shapes = [];
        else if (selectedLayer === 'notes') draft.notes = [];
        renderStudio(); return;
      }
      const removeNote = event.target.closest('[data-delete-annotation-note]');
      if (removeNote) { snapshot(); draft.notes = draft.notes.filter((item) => item.id !== removeNote.dataset.deleteAnnotationNote); renderStudio(); }
    });

    studio.addEventListener('input', (event) => {
      const field = event.target.closest('[data-annotation-field]');
      if (field) { draft[field.dataset.annotationField] = field.value; if (['title','targetTitle','targetUrl','targetExcerpt','pageStyle'].includes(field.dataset.annotationField)) renderStudio(); return; }
      if (event.target.matches('[data-annotation-tags]')) draft.tags = event.target.value.split(',').map((item) => item.trim()).filter(Boolean);
    });

    studio.addEventListener('change', (event) => {
      const visible = event.target.closest('[data-layer-visible]');
      if (visible) { draft.layers[visible.dataset.layerVisible].visible = visible.checked; renderStudio(); return; }
      if (event.target.name === 'annotation-layer') { selectedLayer = event.target.value; return; }
      if (event.target.matches('[data-annotation-collection]')) draft.collectionIds = [event.target.value || 'collection_inbox'];
    });

    const canvas = studio.querySelector('[data-annotation-canvas]');
    const page = studio.querySelector('[data-annotation-page]');
    canvas.addEventListener('pointerdown', (event) => {
      if (!draft) return;
      canvas.setPointerCapture(event.pointerId);
      const point = pointFromEvent(event, page);
      if (activeTool === 'note') {
        const text = window.prompt(strings.notePrompt || 'Enter the note text.');
        if (!words(text)) return;
        snapshot();
        const anchor = words(studio.querySelector('[data-annotation-anchor]').value);
        draft.notes.push({ id: uid('note'), text: words(text), anchor, x: point.x, y: point.y, createdAt: now(), updatedAt: now() });
        studio.querySelector('[data-annotation-anchor]').value = '';
        renderStudio();
        return;
      }
      if (activeTool === 'eraser') { snapshot(); eraseAt(point); renderCanvas(); return; }
      if (['rectangle', 'ellipse', 'arrow'].includes(activeTool)) { snapshot(); shapeStart = point; return; }
      if (['pen','pencil','highlighter'].includes(activeTool)) {
        snapshot();
        const width = Number(studio.querySelector('[data-annotation-width]').value || 4);
        const opacityControl = Number(studio.querySelector('[data-annotation-opacity]').value || 1);
        currentStroke = {
          id: uid('stroke'), kind: activeTool, color: studio.querySelector('[data-annotation-color]').value || '#721019',
          width: activeTool === 'pencil' ? Math.max(1, width * 0.65) : activeTool === 'highlighter' ? Math.max(8, width * 2.8) : width,
          opacity: activeTool === 'pencil' ? Math.min(.7, opacityControl) : activeTool === 'highlighter' ? Math.min(.38, opacityControl) : opacityControl,
          points: [point], createdAt: now(),
        };
        draft.strokes.push(currentStroke);
        renderCanvas();
      }
    });
    canvas.addEventListener('pointermove', (event) => {
      if (!draft) return;
      const point = pointFromEvent(event, page);
      if (activeTool === 'eraser' && (event.buttons || event.pressure > 0)) { eraseAt(point); renderCanvas(); return; }
      if (currentStroke) { currentStroke.points.push(point); renderCanvas(); }
    });
    const finishPointer = (event) => {
      if (!draft) return;
      const point = pointFromEvent(event, page);
      if (shapeStart && ['rectangle','ellipse','arrow'].includes(activeTool)) {
        draft.shapes.push({ id: uid('shape'), type: activeTool, x1: shapeStart.x, y1: shapeStart.y, x2: point.x, y2: point.y, color: studio.querySelector('[data-annotation-color]').value || '#721019', width: Number(studio.querySelector('[data-annotation-width]').value || 3), opacity: Number(studio.querySelector('[data-annotation-opacity]').value || 1), createdAt: now() });
      }
      currentStroke = null; shapeStart = null; renderCanvas();
    };
    canvas.addEventListener('pointerup', finishPointer);
    canvas.addEventListener('pointercancel', finishPointer);

    studio.querySelector('[data-annotation-notes]').addEventListener('pointerdown', (event) => {
      const note = event.target.closest('[data-annotation-note]');
      if (!note || event.target.closest('button')) return;
      const record = draft.notes.find((item) => item.id === note.dataset.annotationNote);
      if (!record) return;
      snapshot();
      noteDrag = { id: record.id, startX: event.clientX, startY: event.clientY, x: record.x, y: record.y, pageRect: page.getBoundingClientRect() };
      note.setPointerCapture(event.pointerId);
    });
    studio.querySelector('[data-annotation-notes]').addEventListener('pointermove', (event) => {
      if (!noteDrag) return;
      const record = draft.notes.find((item) => item.id === noteDrag.id);
      if (!record) return;
      record.x = clamp(noteDrag.x + (event.clientX - noteDrag.startX) / noteDrag.pageRect.width, 0, .82);
      record.y = clamp(noteDrag.y + (event.clientY - noteDrag.startY) / noteDrag.pageRect.height, 0, .92);
      renderNotes();
    });
    studio.querySelector('[data-annotation-notes]').addEventListener('pointerup', () => { noteDrag = null; });
    studio.querySelector('[data-annotation-notes]').addEventListener('dblclick', (event) => {
      const noteEl = event.target.closest('[data-annotation-note]');
      if (!noteEl) return;
      const record = draft.notes.find((item) => item.id === noteEl.dataset.annotationNote);
      if (!record) return;
      const text = window.prompt(strings.notePrompt || 'Enter the note text.', record.text);
      if (text === null) return;
      snapshot(); record.text = words(text); record.updatedAt = now(); renderNotes();
    });
    window.addEventListener('resize', () => requestAnimationFrame(renderCanvas));
  };

  const renderLibrary = () => {
    if (!globalLibrary) return;
    const grid = globalLibrary.querySelector('[data-annotation-library-grid]');
    grid.innerHTML = workspace.annotations.length ? workspace.annotations.slice().sort((a,b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `
      <article class="sc-annotation-library__item">
        <div><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(targetLabel(item.targetType))}${item.targetTitle ? ` · ${escapeHtml(item.targetTitle)}` : ''} · Updated ${escapeHtml(formatDate(item.updatedAt))}</p></div>
        <div class="sc-annotation-library__actions"><button type="button" data-open-annotation="${escapeHtml(item.id)}">Open</button><button type="button" data-export-annotation-json="${escapeHtml(item.id)}">JSON</button><button type="button" data-delete-annotation="${escapeHtml(item.id)}">Delete</button></div>
      </article>`).join('') : `<p>${escapeHtml(strings.empty || 'No annotations have been created yet.')}</p>`;
  };

  const bindLibrary = (library) => {
    library.addEventListener('click', (event) => {
      if (event.target.closest('[data-annotation-library-close]') || event.target === library) { library.hidden = true; return; }
      const open = event.target.closest('[data-open-annotation]');
      if (open) { library.hidden = true; openDraft({ id: open.dataset.openAnnotation }); return; }
      const exp = event.target.closest('[data-export-annotation-json]');
      if (exp) { const item = annotationById(exp.dataset.exportAnnotationJson); if (item) download(JSON.stringify({ schema: annotationSchema, version, annotation: item }, null, 2), `annotation-${slug(item.title)}.json`); return; }
      const del = event.target.closest('[data-delete-annotation]');
      if (del) {
        if (!window.confirm(strings.confirmDelete || 'Delete this annotation?')) return;
        workspace.annotations = workspace.annotations.filter((item) => item.id !== del.dataset.deleteAnnotation);
        workspace.notes.forEach((item) => { if (item.annotationId === del.dataset.deleteAnnotation) item.annotationId = ''; });
        persistWorkspace(); renderLibrary(); renderInlineLists();
      }
    });
  };

  const inlineHtml = () => `
    <section class="sc-annotation-inline">
      <header class="sc-annotation-inline__head"><div><p class="sc-library__eyebrow">Annotation Studio</p><h3>Handwriting and annotation records</h3></div><button type="button" data-inline-new-annotation>New annotation</button></header>
      <p>Annotate publications, notes, sources, matrices, boards, video snippets, book pages, and custom material without changing the original source.</p>
      <div class="sc-annotation-inline__grid">${workspace.annotations.length ? workspace.annotations.slice().sort((a,b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).map((item) => `
        <article class="sc-annotation-inline__card"><div><h4>${escapeHtml(item.title)}</h4><p>${escapeHtml(targetLabel(item.targetType))}${item.targetTitle ? ` · ${escapeHtml(item.targetTitle)}` : ''}<br>${item.strokes.length} strokes · ${item.notes.length} notes · ${escapeHtml(collectionLabel(item.collectionIds?.[0]))}</p></div><div class="sc-annotation-inline__actions"><button type="button" data-inline-open-annotation="${escapeHtml(item.id)}">Open</button><button type="button" data-inline-note-annotation="${escapeHtml(item.id)}">Notebook note</button><button type="button" data-inline-delete-annotation="${escapeHtml(item.id)}">Delete</button></div></article>`).join('') : `<p>${escapeHtml(strings.empty || 'No annotations have been created yet.')}</p>`}</div>
    </section>`;

  const renderInlineLists = () => {
    document.querySelectorAll('[data-sc-library-annotations-inline]').forEach((container) => { container.innerHTML = inlineHtml(); });
  };

  const seedFromRecord = (record) => ({
    title: `Annotations: ${record.title || 'Library record'}`,
    targetType: 'library_record',
    targetId: String(record.id || record.recordId || ''),
    targetTitle: record.title || '',
    targetUrl: record.url || '',
    targetExcerpt: record.excerpt || '',
    collectionIds: ['collection_inbox'],
  });

  const openFromTarget = (target = {}) => openDraft({
    title: target.title ? `Annotations: ${target.title}` : 'Untitled annotation',
    targetType: target.targetType || target.type || 'custom',
    targetId: String(target.targetId || target.id || ''),
    targetTitle: target.targetTitle || target.title || '',
    targetUrl: target.targetUrl || target.url || '',
    targetExcerpt: target.targetExcerpt || target.excerpt || target.body || target.description || '',
    collectionIds: target.collectionIds || ['collection_inbox'],
    pageStyle: target.pageStyle || defaultPageStyle,
  });

  const bindLaunchers = () => {
    document.querySelectorAll('[data-sc-library-annotation-root]').forEach((root) => {
      root.addEventListener('click', (event) => {
        if (event.target.closest('[data-new-annotation]')) {
          openFromTarget({ targetType: root.dataset.targetType, targetId: root.dataset.targetId, targetTitle: root.dataset.targetTitle, targetUrl: root.dataset.targetUrl, targetExcerpt: root.dataset.targetExcerpt, title: root.dataset.targetTitle || 'Annotation page' });
        }
        if (event.target.closest('[data-open-annotation-library]')) { ensureGlobalUi(); loadWorkspace(); renderLibrary(); globalLibrary.hidden = false; }
      });
      if (root.dataset.annotationOpen === '1') {
        if (root.dataset.annotationId) openDraft({ id: root.dataset.annotationId });
        else openFromTarget({ targetType: root.dataset.targetType, targetId: root.dataset.targetId, targetTitle: root.dataset.targetTitle, targetUrl: root.dataset.targetUrl, targetExcerpt: root.dataset.targetExcerpt, title: root.dataset.targetTitle || 'Annotation page' });
      }
    });
  };

  document.addEventListener('click', (event) => {
    const newInline = event.target.closest('[data-inline-new-annotation]');
    if (newInline) { openDraft(); return; }
    const openInline = event.target.closest('[data-inline-open-annotation]');
    if (openInline) { openDraft({ id: openInline.dataset.inlineOpenAnnotation }); return; }
    const deleteInline = event.target.closest('[data-inline-delete-annotation]');
    if (deleteInline) {
      if (!window.confirm(strings.confirmDelete || 'Delete this annotation?')) return;
      workspace.annotations = workspace.annotations.filter((item) => item.id !== deleteInline.dataset.inlineDeleteAnnotation);
      persistWorkspace(); renderInlineLists(); return;
    }
    const noteInline = event.target.closest('[data-inline-note-annotation]');
    if (noteInline) {
      const item = annotationById(noteInline.dataset.inlineNoteAnnotation);
      if (item) document.dispatchEvent(new CustomEvent('sc-library-new-note-for-annotation', { detail: { annotation: item } }));
    }
  });

  document.addEventListener('sc-library-new-annotation-for-record', (event) => { if (event.detail?.record) openDraft(seedFromRecord(event.detail.record)); });
  document.addEventListener('sc-library-new-annotation', (event) => openFromTarget(event.detail || {}));
  document.addEventListener('sc-library-open-annotation', (event) => { if (event.detail?.id) openDraft({ id: event.detail.id }); });
  document.addEventListener('sc-library-open-annotation-library', () => { ensureGlobalUi(); loadWorkspace(); renderLibrary(); globalLibrary.hidden = false; });
  document.addEventListener('sc-library-annotations-render', () => { loadWorkspace(); renderInlineLists(); });
  window.addEventListener('storage', (event) => { if (event.key === storageKey) { loadWorkspace(); renderInlineLists(); renderLibrary(); } });
  window.addEventListener('sc-library-workspace-updated', () => { loadWorkspace(); renderInlineLists(); renderLibrary(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeStudio(); if (globalLibrary) globalLibrary.hidden = true; } });

  loadWorkspace();
  ensureGlobalUi();
  bindLaunchers();
  renderInlineLists();
})();
