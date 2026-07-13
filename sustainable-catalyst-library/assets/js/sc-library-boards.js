(() => {
  'use strict';

  const shared = window.SCBoardShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const schema = shared.schema || 'sc-library-workspace/1.7';
  const legacySchemas = Array.isArray(shared.legacySchemas) ? shared.legacySchemas : ['sc-library-workspace/1.1', 'sc-library-workspace/1.0'];
  const version = shared.version || '1.13.3';
  const templates = shared.templates || {};
  const nodeTypes = shared.nodeTypes || {};
  const edgeTypes = shared.edgeTypes || {};
  const strings = shared.strings || {};
  const BOARD_WIDTH = 1800;
  const BOARD_HEIGHT = 1200;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));
  const escapeXml = escapeHtml;
  const words = (value) => String(value || '').trim();
  const now = () => new Date().toISOString();
  const today = () => new Date().toISOString().slice(0, 10);
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`;
  const clone = (value) => JSON.parse(JSON.stringify(value));
  const slug = (value) => words(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'board';
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const initialWorkspace = () => {
    const createdAt = now();
    return {
      schema,
      version,
      createdAt,
      updatedAt: createdAt,
      collections: [{ id: 'collection_inbox', title: 'Research Inbox', description: 'Newly saved Library records and research material.', createdAt, updatedAt: createdAt }],
      savedRecords: [], notes: [], sources: [], matrices: [], boards: [], handoffs: [], annotations: [], books: [],
    };
  };

  const readWorkspace = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) return initialWorkspace();
      const data = JSON.parse(raw);
      if (!data || typeof data !== 'object' || ![schema, ...legacySchemas].includes(data.schema)) return initialWorkspace();
      return {
        schema,
        version: String(data.version || version),
        createdAt: String(data.createdAt || now()),
        updatedAt: String(data.updatedAt || now()),
        collections: Array.isArray(data.collections) ? data.collections : [],
        savedRecords: Array.isArray(data.savedRecords) ? data.savedRecords : [],
        notes: Array.isArray(data.notes) ? data.notes : [],
        sources: Array.isArray(data.sources) ? data.sources : [],
        matrices: Array.isArray(data.matrices) ? data.matrices : [],
        boards: Array.isArray(data.boards) ? data.boards : [],
        handoffs: Array.isArray(data.handoffs) ? data.handoffs : [],
        annotations: Array.isArray(data.annotations) ? data.annotations : [],
        books: Array.isArray(data.books) ? data.books : [],
      };
    } catch (error) {
      return initialWorkspace();
    }
  };

  const writeWorkspace = (workspace) => {
    try {
      workspace.schema = schema;
      workspace.version = version;
      workspace.updatedAt = now();
      window.localStorage.setItem(storageKey, JSON.stringify(workspace));
      window.dispatchEvent(new CustomEvent('sc-library-workspace-updated'));
      return true;
    } catch (error) {
      return false;
    }
  };

  const nodeColors = {
    concept: '#fff7d6', note: '#fff4b8', question: '#dceeff', claim: '#ffe1df', evidence: '#e2f5df', source: '#f2e8ff',
    publication: '#ffffff', matrix: '#e8f4f6', equation: '#e7f8e7', code: '#e8edf2', result: '#f8eee1'
  };

  const templateFor = (id, type = 'whiteboard') => templates[id] || Object.values(templates).find((item) => item.type === type) || {
    id: type === 'chalkboard' ? 'blank_chalkboard' : 'blank_whiteboard', type, label: type === 'chalkboard' ? 'Blank Chalkboard' : 'Blank Whiteboard', background: type === 'chalkboard' ? 'chalk' : 'grid'
  };

  const makeNode = (type = 'note', seed = {}) => ({
    id: seed.id || uid('node'),
    type,
    title: words(seed.title) || (nodeTypes[type]?.label || 'Note'),
    body: words(seed.body),
    x: Number.isFinite(Number(seed.x)) ? Number(seed.x) : 140,
    y: Number.isFinite(Number(seed.y)) ? Number(seed.y) : 130,
    width: clamp(Number(seed.width) || 250, 170, 520),
    height: clamp(Number(seed.height) || 150, 90, 420),
    url: words(seed.url),
    referenceType: words(seed.referenceType),
    referenceId: words(seed.referenceId),
    color: words(seed.color) || nodeColors[type] || '#ffffff',
    createdAt: seed.createdAt || now(),
    updatedAt: now(),
  });

  const templateNodes = (template) => {
    if (template.id === 'concept_map') {
      return [
        makeNode('concept', { title: 'Central concept', body: 'Define the subject or research problem.', x: 720, y: 450, width: 300, height: 170 }),
        makeNode('question', { title: 'Questions', body: 'What needs to be understood?', x: 180, y: 160 }),
        makeNode('source', { title: 'Sources', body: 'Add publications, books, links, datasets, or video snippets.', x: 1260, y: 160 }),
        makeNode('result', { title: 'Interpretation', body: 'What does the evidence mean?', x: 180, y: 820 }),
        makeNode('claim', { title: 'Implications', body: 'What follows from this analysis?', x: 1260, y: 820 }),
      ];
    }
    if (template.id === 'evidence_map') {
      return [
        makeNode('question', { title: 'Research question', body: 'State the question being examined.', x: 720, y: 80, width: 340 }),
        makeNode('claim', { title: 'Claim', body: 'Enter the proposition to evaluate.', x: 720, y: 390, width: 340 }),
        makeNode('evidence', { title: 'Supporting evidence', body: 'Add evidence and provenance.', x: 180, y: 730, width: 330 }),
        makeNode('evidence', { title: 'Challenging evidence', body: 'Add disagreement, limitations, or counterevidence.', x: 690, y: 800, width: 390 }),
        makeNode('source', { title: 'Knowledge gap', body: 'What evidence is still missing?', x: 1300, y: 730, width: 320 }),
      ];
    }
    if (template.id === 'systems_map') {
      return [
        makeNode('concept', { title: 'System purpose', body: 'What is this system trying to do?', x: 100, y: 100, width: 300 }),
        makeNode('concept', { title: 'Actors and institutions', body: 'Who participates or governs?', x: 550, y: 200, width: 300 }),
        makeNode('equation', { title: 'Stocks and states', body: 'What accumulates or describes system state?', x: 1020, y: 170, width: 330 }),
        makeNode('code', { title: 'Flows and drivers', body: 'What changes the state over time?', x: 1420, y: 500, width: 300 }),
        makeNode('question', { title: 'Feedback and delays', body: 'Where do reinforcing or balancing effects appear?', x: 760, y: 670, width: 360 }),
        makeNode('result', { title: 'Leverage and consequences', body: 'Where might intervention matter?', x: 180, y: 820, width: 360 }),
      ];
    }
    if (template.id === 'equation_workbench') {
      return [
        makeNode('question', { title: 'Problem statement', body: 'Describe the system or calculation in plain language.', x: 100, y: 100, width: 360 }),
        makeNode('equation', { title: 'Formal expression', body: 'Enter equation or notation here.', x: 570, y: 180, width: 420, height: 180 }),
        makeNode('concept', { title: 'Variables and units', body: 'Define each variable and its units.', x: 1110, y: 100, width: 360 }),
        makeNode('code', { title: 'Algorithm or code', body: 'Translate the formal relationship into executable logic.', x: 260, y: 650, width: 440, height: 220 }),
        makeNode('evidence', { title: 'Validation checks', body: 'Test dimensions, edge cases, assumptions, and numerical behavior.', x: 850, y: 700, width: 400, height: 210 }),
        makeNode('result', { title: 'Systems interpretation', body: 'Explain what the result means in the real system.', x: 1350, y: 650, width: 330, height: 220 }),
      ];
    }
    if (template.id === 'technical_derivation') {
      return [
        makeNode('question', { title: 'Given', body: 'Known quantities, source conditions, and constraints.', x: 80, y: 120, width: 330 }),
        makeNode('equation', { title: 'Step 1', body: 'First derivation step.', x: 490, y: 220, width: 320 }),
        makeNode('equation', { title: 'Step 2', body: 'Second derivation step.', x: 900, y: 330, width: 320 }),
        makeNode('equation', { title: 'Result', body: 'Final expression or calculated output.', x: 1320, y: 450, width: 350 }),
        makeNode('evidence', { title: 'Validation', body: 'Dimensional, numerical, and boundary checks.', x: 900, y: 800, width: 390 }),
        makeNode('result', { title: 'Interpretation', body: 'Responsible interpretation and limitations.', x: 300, y: 820, width: 420 }),
      ];
    }
    return [];
  };

  const defaultEdgesFor = (template, nodes) => {
    if (!nodes.length) return [];
    const pair = (from, to, label, type = 'related_to') => (nodes[from] && nodes[to] ? { id: uid('edge'), from: nodes[from].id, to: nodes[to].id, label, type } : null);
    const maps = {
      concept_map: [pair(1, 0, 'frames'), pair(2, 0, 'documents'), pair(0, 3, 'informs'), pair(0, 4, 'leads to')],
      evidence_map: [pair(0, 1, 'frames'), pair(2, 1, 'supports', 'supports'), pair(3, 1, 'challenges', 'challenges'), pair(4, 1, 'limits')],
      systems_map: [pair(0, 1, 'organized by'), pair(1, 2, 'shapes'), pair(2, 3, 'changed by'), pair(3, 4, 'feeds back'), pair(4, 5, 'reveals')],
      equation_workbench: [pair(0, 1, 'formalized as'), pair(1, 2, 'defined by'), pair(1, 3, 'implemented as'), pair(3, 4, 'tested by'), pair(4, 5, 'supports')],
      technical_derivation: [pair(0, 1, 'starts'), pair(1, 2, 'derives'), pair(2, 3, 'produces'), pair(3, 4, 'checked by'), pair(4, 5, 'interpreted as')],
    };
    return (maps[template.id] || []).filter(Boolean);
  };

  const makeBoard = (type = 'whiteboard', seed = {}) => {
    const requestedTemplate = seed.templateId || (type === 'chalkboard' ? 'blank_chalkboard' : 'blank_whiteboard');
    const template = templateFor(requestedTemplate, type);
    const boardType = template.type || type;
    const nodes = Array.isArray(seed.nodes) ? seed.nodes.map((node) => makeNode(node.type || 'note', node)) : templateNodes(template);
    return {
      id: seed.id || uid('board'),
      type: boardType,
      templateId: template.id,
      title: words(seed.title) || (boardType === 'chalkboard' ? 'Untitled Chalkboard' : 'Untitled Whiteboard'),
      description: words(seed.description) || words(template.description),
      background: words(seed.background) || template.background || (boardType === 'chalkboard' ? 'chalk' : 'grid'),
      width: BOARD_WIDTH,
      height: BOARD_HEIGHT,
      nodes,
      edges: Array.isArray(seed.edges) ? seed.edges : defaultEdgesFor(template, nodes),
      strokes: Array.isArray(seed.strokes) ? seed.strokes : [],
      recordId: words(seed.recordId), sourceId: words(seed.sourceId), matrixId: words(seed.matrixId), noteId: words(seed.noteId),
      collectionIds: Array.isArray(seed.collectionIds) && seed.collectionIds.length ? seed.collectionIds : ['collection_inbox'],
      createdAt: seed.createdAt || now(), updatedAt: now(),
    };
  };

  const normalizeBoard = (value) => {
    const type = value?.type === 'chalkboard' ? 'chalkboard' : 'whiteboard';
    const board = makeBoard(type, value || {});
    board.edges = board.edges.filter((edge) => board.nodes.some((node) => node.id === edge.from) && board.nodes.some((node) => node.id === edge.to));
    return board;
  };

  let workspace = readWorkspace();
  let draft = null;
  let dirty = false;
  let zoom = 0.75;
  let mode = 'select';
  let selected = new Set();
  let history = [];
  let future = [];
  let dragState = null;
  let drawState = null;
  let modal = null;
  let noticeTimer = null;

  const boardById = (id) => workspace.boards.find((item) => item.id === id);
  const recordById = (id) => workspace.savedRecords.find((item) => String(item.recordId) === String(id));
  const sourceById = (id) => workspace.sources.find((item) => item.id === id);
  const matrixById = (id) => workspace.matrices.find((item) => item.id === id);
  const noteById = (id) => workspace.notes.find((item) => item.id === id);

  const refreshWorkspace = () => { workspace = readWorkspace(); };
  const setDirty = (value = true) => {
    dirty = value;
    modal?.querySelector('[data-board-dirty]')?.toggleAttribute('hidden', !dirty);
  };
  const snapshot = () => JSON.stringify(draft);
  const commitHistory = () => {
    if (!draft) return;
    history.push(snapshot());
    if (history.length > 35) history.shift();
    future = [];
  };
  const restoreSnapshot = (raw) => {
    draft = normalizeBoard(JSON.parse(raw));
    selected.clear();
    setDirty(true);
    renderEditor();
  };

  const backgroundClass = () => `is-${draft?.background || 'grid'}`;
  const typeLabel = (type) => nodeTypes[type]?.label || String(type || 'Node').replace(/_/g, ' ');
  const edgeLabel = (type) => edgeTypes[type]?.label || String(type || 'Related to').replace(/_/g, ' ');

  const ensureModal = () => {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'sc-board-modal';
    modal.hidden = true;
    modal.innerHTML = `
      <button type="button" class="sc-board-modal__overlay" data-board-close aria-label="Close board"></button>
      <section class="sc-board-studio" role="dialog" aria-modal="true" aria-labelledby="sc-board-title">
        <header class="sc-board-studio__header">
          <div><p class="sc-library__eyebrow" data-board-kind>Visual research board</p><h2 id="sc-board-title" data-board-title>Board</h2><span data-board-dirty hidden>Unsaved changes</span></div>
          <div class="sc-board-studio__header-actions"><button type="button" data-board-save>Save</button><button type="button" data-board-close aria-label="Close">×</button></div>
        </header>
        <div class="sc-board-studio__notice" data-board-notice hidden aria-live="polite"></div>
        <div class="sc-board-studio__toolbar" data-board-toolbar></div>
        <div class="sc-board-studio__body">
          <aside class="sc-board-studio__sidebar" data-board-sidebar></aside>
          <main class="sc-board-studio__viewport" data-board-viewport tabindex="0">
            <div class="sc-board-studio__sizer" data-board-sizer><div class="sc-board-studio__stage" data-board-stage></div></div>
          </main>
        </div>
      </section>`;
    document.body.appendChild(modal);
    bindModalEvents();
    return modal;
  };

  const showNotice = (message, type = 'success') => {
    const box = ensureModal().querySelector('[data-board-notice]');
    if (!box) return;
    window.clearTimeout(noticeTimer);
    box.textContent = message;
    box.className = `sc-board-studio__notice is-${type}`;
    box.hidden = false;
    noticeTimer = window.setTimeout(() => { box.hidden = true; }, 4200);
  };

  const toolbarHtml = () => {
    const workspaceItems = [
      ...workspace.savedRecords.map((item) => ({ value: `publication|${item.recordId}`, label: `Publication: ${item.title}` })),
      ...workspace.sources.map((item) => ({ value: `source|${item.id}`, label: `Source: ${item.title}` })),
      ...workspace.notes.map((item) => ({ value: `note|${item.id}`, label: `Note: ${item.title}` })),
      ...workspace.matrices.map((item) => ({ value: `matrix|${item.id}`, label: `Matrix: ${item.title}` })),
    ];
    return `
      <div class="sc-board-tools" role="toolbar" aria-label="Board tools">
        <button type="button" data-board-mode="select" class="${mode === 'select' ? 'is-active' : ''}">Select</button>
        <button type="button" data-board-mode="pen" class="${mode === 'pen' ? 'is-active' : ''}">Pen</button>
        <button type="button" data-board-mode="highlighter" class="${mode === 'highlighter' ? 'is-active' : ''}">Highlighter</button>
        <button type="button" data-board-mode="eraser" class="${mode === 'eraser' ? 'is-active' : ''}">Eraser</button>
        <span class="sc-board-tools__divider"></span>
        <button type="button" data-add-node="note">Note</button>
        <button type="button" data-add-node="question">Question</button>
        <button type="button" data-add-node="claim">Claim</button>
        <button type="button" data-add-node="evidence">Evidence</button>
        <button type="button" data-add-node="equation">Equation</button>
        <button type="button" data-add-node="code">Code</button>
        <select data-add-workspace-item aria-label="Add a saved workspace item"><option value="">Add from Notebook…</option>${workspaceItems.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`).join('')}</select>
        <span class="sc-board-tools__divider"></span>
        <button type="button" data-connect-selected ${selected.size === 2 ? '' : 'disabled'}>Connect selected</button>
        <button type="button" data-delete-selected ${selected.size ? '' : 'disabled'}>Delete</button>
        <button type="button" data-board-undo ${history.length ? '' : 'disabled'}>Undo</button>
        <button type="button" data-board-redo ${future.length ? '' : 'disabled'}>Redo</button>
        <span class="sc-board-tools__divider"></span>
        <button type="button" data-board-zoom="out">−</button><span class="sc-board-tools__zoom">${Math.round(zoom * 100)}%</span><button type="button" data-board-zoom="in">+</button>
        <span class="sc-board-tools__divider"></span>
        <button type="button" data-board-export="json">JSON</button>
        <button type="button" data-board-export="svg">SVG</button>
        <button type="button" data-board-export="png">PNG</button>
        <button type="button" data-board-export="print">PDF / Print</button>
      </div>`;
  };

  const collectionChecks = () => workspace.collections.map((item) => `<label><input type="checkbox" data-board-collection value="${escapeHtml(item.id)}" ${(draft.collectionIds || []).includes(item.id) ? 'checked' : ''}> ${escapeHtml(item.title)}</label>`).join('');
  const relationSelect = (kind, selectedValue) => {
    const source = kind === 'record' ? workspace.savedRecords.map((item) => ({ id: String(item.recordId), title: item.title })) : kind === 'source' ? workspace.sources : kind === 'matrix' ? workspace.matrices : workspace.notes;
    return `<option value="">None</option>${source.map((item) => `<option value="${escapeHtml(item.id || item.recordId)}" ${String(item.id || item.recordId) === String(selectedValue) ? 'selected' : ''}>${escapeHtml(item.title)}</option>`).join('')}`;
  };

  const sidebarHtml = () => {
    const selectedNodes = draft.nodes.filter((node) => selected.has(node.id));
    const node = selectedNodes.length === 1 ? selectedNodes[0] : null;
    const selectedEdge = selected.size === 1 && String([...selected][0]).startsWith('edge:') ? draft.edges.find((edge) => `edge:${edge.id}` === [...selected][0]) : null;
    if (node) {
      return `<section class="sc-board-properties"><p class="sc-library__eyebrow">Selected ${escapeHtml(typeLabel(node.type))}</p><h3>Edit card</h3>
        <label><span>Card type</span><select data-node-field="type">${Object.values(nodeTypes).map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === node.type ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></label>
        <label><span>Title</span><input data-node-field="title" value="${escapeHtml(node.title)}"></label>
        <label><span>Content</span><textarea rows="9" data-node-field="body">${escapeHtml(node.body)}</textarea></label>
        <label><span>Link</span><input type="url" data-node-field="url" value="${escapeHtml(node.url)}" placeholder="https://"></label>
        <div class="sc-board-properties__grid"><label><span>Width</span><input type="number" min="170" max="520" data-node-field="width" value="${Number(node.width)}"></label><label><span>Height</span><input type="number" min="90" max="420" data-node-field="height" value="${Number(node.height)}"></label></div>
        <label><span>Card color</span><input type="color" data-node-field="color" value="${escapeHtml(node.color || '#ffffff')}"></label>
        <small>Drag the card on the canvas. Use Connect selected after selecting two cards.</small>
      </section>`;
    }
    if (selectedEdge) {
      return `<section class="sc-board-properties"><p class="sc-library__eyebrow">Selected relationship</p><h3>Edit connector</h3>
        <label><span>Relationship type</span><select data-edge-field="type">${Object.values(edgeTypes).map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === selectedEdge.type ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></label>
        <label><span>Label</span><input data-edge-field="label" value="${escapeHtml(selectedEdge.label || '')}"></label>
      </section>`;
    }
    return `<section class="sc-board-properties"><p class="sc-library__eyebrow">Board settings</p><h3>${escapeHtml(draft.type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard')}</h3>
      <label><span>Board template</span><select data-board-template>${Object.values(templates).filter((item) => item.type === draft.type).map((item) => `<option value="${escapeHtml(item.id)}" ${item.id === draft.templateId ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</select></label>
      <label><span>Title</span><input data-board-field="title" value="${escapeHtml(draft.title)}"></label>
      <label><span>Description</span><textarea rows="4" data-board-field="description">${escapeHtml(draft.description)}</textarea></label>
      <label><span>Background</span><select data-board-field="background">${['blank','grid','dots','chalk','chalk_grid'].map((value) => `<option value="${value}" ${value === draft.background ? 'selected' : ''}>${value.replace('_',' ')}</option>`).join('')}</select></label>
      <label><span>Attached Library record</span><select data-board-field="recordId">${relationSelect('record', draft.recordId)}</select></label>
      <label><span>Attached source</span><select data-board-field="sourceId">${relationSelect('source', draft.sourceId)}</select></label>
      <label><span>Attached matrix</span><select data-board-field="matrixId">${relationSelect('matrix', draft.matrixId)}</select></label>
      <label><span>Attached note</span><select data-board-field="noteId">${relationSelect('note', draft.noteId)}</select></label>
      <fieldset><legend>Collections</legend>${collectionChecks()}</fieldset>
      <div class="sc-board-properties__summary"><strong>${draft.nodes.length}</strong> cards · <strong>${draft.edges.length}</strong> relationships · <strong>${draft.strokes.length}</strong> ink strokes</div>
      <button type="button" data-board-clear-ink ${draft.strokes.length ? '' : 'disabled'}>Clear handwriting</button>
    </section>`;
  };

  const edgeGeometry = (edge) => {
    const from = draft.nodes.find((node) => node.id === edge.from);
    const to = draft.nodes.find((node) => node.id === edge.to);
    if (!from || !to) return null;
    const x1 = from.x + from.width / 2;
    const y1 = from.y + from.height / 2;
    const x2 = to.x + to.width / 2;
    const y2 = to.y + to.height / 2;
    const dx = x2 - x1;
    const curve = Math.min(160, Math.max(60, Math.abs(dx) * 0.35));
    const c1x = x1 + (dx >= 0 ? curve : -curve);
    const c2x = x2 - (dx >= 0 ? curve : -curve);
    return { x1, y1, x2, y2, c1x, c2x, d: `M ${x1} ${y1} C ${c1x} ${y1}, ${c2x} ${y2}, ${x2} ${y2}`, mx: (x1 + x2) / 2, my: (y1 + y2) / 2 };
  };

  const renderStage = () => {
    const stage = modal.querySelector('[data-board-stage]');
    const sizer = modal.querySelector('[data-board-sizer]');
    if (!stage || !sizer || !draft) return;
    sizer.style.width = `${draft.width * zoom}px`;
    sizer.style.height = `${draft.height * zoom}px`;
    stage.style.width = `${draft.width}px`;
    stage.style.height = `${draft.height}px`;
    stage.style.transform = `scale(${zoom})`;
    stage.className = `sc-board-studio__stage ${draft.type === 'chalkboard' ? 'is-chalkboard' : 'is-whiteboard'} ${backgroundClass()} is-mode-${mode}`;

    const edges = draft.edges.map((edge) => {
      const geo = edgeGeometry(edge);
      if (!geo) return '';
      const edgeId = `edge:${edge.id}`;
      return `<g class="sc-board-edge ${selected.has(edgeId) ? 'is-selected' : ''}" data-edge-id="${escapeHtml(edge.id)}"><path d="${geo.d}" marker-end="url(#sc-board-arrow)"></path><path class="sc-board-edge__hit" d="${geo.d}"></path>${edge.label ? `<text x="${geo.mx}" y="${geo.my - 8}" text-anchor="middle">${escapeHtml(edge.label)}</text>` : ''}</g>`;
    }).join('');
    const strokes = draft.strokes.map((stroke) => `<path class="sc-board-ink ${stroke.tool === 'highlighter' ? 'is-highlighter' : ''}" data-stroke-id="${escapeHtml(stroke.id)}" d="${escapeHtml(stroke.d)}" stroke="${escapeHtml(stroke.color)}" stroke-width="${Number(stroke.width)}" opacity="${Number(stroke.opacity)}"></path>`).join('');

    stage.innerHTML = `<svg class="sc-board-studio__svg" viewBox="0 0 ${draft.width} ${draft.height}" width="${draft.width}" height="${draft.height}" aria-hidden="true"><defs><marker id="sc-board-arrow" markerWidth="9" markerHeight="9" refX="7" refY="3" orient="auto"><path d="M0,0 L0,6 L8,3 z"></path></marker></defs><g class="sc-board-edges">${edges}</g><g class="sc-board-strokes">${strokes}</g><path class="sc-board-live-stroke" data-live-stroke d=""></path></svg><div class="sc-board-nodes">${draft.nodes.map((node) => `<article class="sc-board-node is-${escapeHtml(node.type)} ${selected.has(node.id) ? 'is-selected' : ''}" data-node-id="${escapeHtml(node.id)}" style="left:${Number(node.x)}px;top:${Number(node.y)}px;width:${Number(node.width)}px;min-height:${Number(node.height)}px;--node-color:${escapeHtml(node.color)}"><span>${escapeHtml(typeLabel(node.type))}</span><h4>${escapeHtml(node.title)}</h4><p>${escapeHtml(node.body)}</p>${node.url ? `<a href="${escapeHtml(node.url)}" target="_blank" rel="noopener">Open source</a>` : ''}<button type="button" data-node-handle aria-label="Move ${escapeHtml(node.title)}">⋮⋮</button></article>`).join('')}</div>`;
  };

  const renderEditor = () => {
    if (!draft || !modal) return;
    modal.querySelector('[data-board-kind]').textContent = draft.type === 'chalkboard' ? 'Technical Chalkboard' : 'Research Whiteboard';
    modal.querySelector('[data-board-title]').textContent = draft.title;
    modal.querySelector('[data-board-toolbar]').innerHTML = toolbarHtml();
    modal.querySelector('[data-board-sidebar]').innerHTML = sidebarHtml();
    renderStage();
  };

  const openEditor = (board, options = {}) => {
    refreshWorkspace();
    draft = normalizeBoard(board);
    zoom = Number(options.zoom) || 0.75;
    mode = 'select';
    selected.clear();
    history = [];
    future = [];
    setDirty(Boolean(options.dirty));
    ensureModal().hidden = false;
    document.documentElement.classList.add('sc-board-open');
    renderEditor();
    modal.querySelector('[data-board-viewport]')?.focus();
  };

  const closeEditor = (force = false) => {
    if (!modal || modal.hidden) return;
    if (!force && dirty && !window.confirm(strings.confirmClose || 'Close without saving?')) return;
    modal.hidden = true;
    document.documentElement.classList.remove('sc-board-open');
    draft = null;
    selected.clear();
  };

  const saveDraft = () => {
    if (!draft) return;
    refreshWorkspace();
    draft.updatedAt = now();
    const index = workspace.boards.findIndex((item) => item.id === draft.id);
    const saved = normalizeBoard(draft);
    if (index >= 0) workspace.boards[index] = saved; else workspace.boards.push(saved);
    if (!writeWorkspace(workspace)) {
      showNotice(strings.storageError || 'Storage unavailable.', 'error');
      return;
    }
    draft = clone(saved);
    setDirty(false);
    showNotice(strings.saved || 'Board saved.');
    document.dispatchEvent(new CustomEvent('sc-library-board-saved', { detail: { board: saved } }));
  };

  const addNode = (type, seed = {}) => {
    if (!draft) return;
    commitHistory();
    const viewport = modal.querySelector('[data-board-viewport]');
    const x = clamp((viewport.scrollLeft / zoom) + 220 + Math.random() * 120, 20, draft.width - 300);
    const y = clamp((viewport.scrollTop / zoom) + 170 + Math.random() * 100, 20, draft.height - 220);
    const node = makeNode(type, { x, y, ...seed });
    draft.nodes.push(node);
    selected.clear();
    selected.add(node.id);
    setDirty(true);
    renderEditor();
  };

  const addWorkspaceItem = (kind, id) => {
    refreshWorkspace();
    if (kind === 'publication') {
      const item = recordById(id);
      if (item) addNode('publication', { title: item.title, body: item.excerpt || '', url: item.url, referenceType: kind, referenceId: String(item.recordId), color: '#ffffff' });
    } else if (kind === 'source') {
      const item = sourceById(id);
      if (item) addNode('source', { title: item.title, body: item.description || item.notes || '', url: item.url || '', referenceType: kind, referenceId: item.id });
    } else if (kind === 'matrix') {
      const item = matrixById(id);
      if (item) addNode('matrix', { title: item.title, body: item.description || `${item.rows?.length || 0} rows × ${item.columns?.length || 0} columns`, referenceType: kind, referenceId: item.id });
    } else if (kind === 'note') {
      const item = noteById(id);
      if (item) addNode(item.type === 'question' ? 'question' : item.type === 'claim' ? 'claim' : 'note', { title: item.title, body: item.body || '', referenceType: kind, referenceId: item.id });
    }
  };

  const deleteSelected = () => {
    if (!draft || !selected.size) return;
    commitHistory();
    const nodeIds = new Set([...selected].filter((id) => !String(id).startsWith('edge:')));
    const edgeIds = new Set([...selected].filter((id) => String(id).startsWith('edge:')).map((id) => String(id).slice(5)));
    draft.nodes = draft.nodes.filter((node) => !nodeIds.has(node.id));
    draft.edges = draft.edges.filter((edge) => !edgeIds.has(edge.id) && !nodeIds.has(edge.from) && !nodeIds.has(edge.to));
    selected.clear();
    setDirty(true);
    renderEditor();
  };

  const connectSelected = () => {
    const ids = [...selected].filter((id) => !String(id).startsWith('edge:'));
    if (ids.length !== 2) return;
    const label = window.prompt(strings.connectionLabel || 'Relationship label', 'related to');
    if (label === null) return;
    commitHistory();
    draft.edges.push({ id: uid('edge'), from: ids[0], to: ids[1], label: words(label), type: 'related_to' });
    selected.clear();
    setDirty(true);
    renderEditor();
  };

  const canvasPoint = (event) => {
    const stage = modal.querySelector('[data-board-stage]');
    const rect = stage.getBoundingClientRect();
    return { x: clamp((event.clientX - rect.left) / zoom, 0, draft.width), y: clamp((event.clientY - rect.top) / zoom, 0, draft.height) };
  };

  const pathFromPoints = (points) => {
    if (!points.length) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y} L ${points[0].x + 0.1} ${points[0].y + 0.1}`;
    return points.map((point, index) => `${index ? 'L' : 'M'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');
  };

  const lineWrap = (value, max = 34) => {
    const wordsList = String(value || '').split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';
    wordsList.forEach((word) => {
      const next = line ? `${line} ${word}` : word;
      if (next.length > max && line) { lines.push(line); line = word; } else line = next;
    });
    if (line) lines.push(line);
    return lines.slice(0, 9);
  };

  const boardSvg = (board) => {
    const normalized = normalizeBoard(board);
    const chalk = normalized.type === 'chalkboard';
    const bg = chalk ? '#101b18' : '#fffdf7';
    const fg = chalk ? '#e9ffe8' : '#161616';
    const edgeColor = chalk ? '#a7e8a0' : '#6b3c42';
    const patterns = normalized.background.includes('grid') ? `<pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="${chalk ? '#29443c' : '#e4ded3'}" stroke-width="1"/></pattern>` : normalized.background === 'dots' ? `<pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.5" fill="#d0c7b8"/></pattern>` : '';
    const patternFill = patterns ? '<rect width="100%" height="100%" fill="url(#grid)"/>' : '';
    const edges = normalized.edges.map((edge) => {
      const previousDraft = draft; draft = normalized; const geo = edgeGeometry(edge); draft = previousDraft;
      if (!geo) return '';
      return `<path d="${geo.d}" fill="none" stroke="${edgeColor}" stroke-width="3" marker-end="url(#arrow)"/>${edge.label ? `<text x="${geo.mx}" y="${geo.my - 10}" fill="${edgeColor}" text-anchor="middle" font-family="Arial" font-size="18">${escapeXml(edge.label)}</text>` : ''}`;
    }).join('');
    const strokes = normalized.strokes.map((stroke) => `<path d="${escapeXml(stroke.d)}" fill="none" stroke="${escapeXml(stroke.color)}" stroke-width="${Number(stroke.width)}" stroke-linecap="round" stroke-linejoin="round" opacity="${Number(stroke.opacity)}"/>`).join('');
    const nodes = normalized.nodes.map((node) => {
      const fill = chalk ? '#172722' : node.color || '#ffffff';
      const border = chalk ? '#79c977' : '#34292b';
      const titleLines = lineWrap(node.title, Math.max(18, Math.floor(node.width / 10)));
      const bodyLines = lineWrap(node.body, Math.max(24, Math.floor(node.width / 8)));
      return `<g transform="translate(${node.x} ${node.y})"><rect width="${node.width}" height="${node.height}" rx="12" fill="${fill}" stroke="${border}" stroke-width="3"/><text x="16" y="24" fill="${chalk ? '#7df27d' : '#721019'}" font-family="Arial" font-size="14" font-weight="bold">${escapeXml(typeLabel(node.type).toUpperCase())}</text><text x="16" y="50" fill="${fg}" font-family="Arial" font-size="20" font-weight="bold">${titleLines.map((line, i) => `<tspan x="16" dy="${i ? 23 : 0}">${escapeXml(line)}</tspan>`).join('')}</text><text x="16" y="${74 + Math.max(0, titleLines.length - 1) * 23}" fill="${fg}" font-family="Arial" font-size="15">${bodyLines.map((line, i) => `<tspan x="16" dy="${i ? 20 : 0}">${escapeXml(line)}</tspan>`).join('')}</text></g>`;
    }).join('');
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${normalized.width}" height="${normalized.height}" viewBox="0 0 ${normalized.width} ${normalized.height}"><defs>${patterns}<marker id="arrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="${edgeColor}"/></marker></defs><rect width="100%" height="100%" fill="${bg}"/>${patternFill}${edges}${strokes}${nodes}</svg>`;
  };

  const downloadBlob = (blob, filename) => {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = filename; document.body.appendChild(link); link.click(); link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
  };

  const exportBoard = async (format) => {
    if (!draft) return;
    const name = `${draft.type}-${slug(draft.title)}-${today()}`;
    if (format === 'json') {
      downloadBlob(new Blob([JSON.stringify({ schema, version, exportScope: { type: draft.type, id: draft.id, title: draft.title }, board: draft }, null, 2)], { type: 'application/json' }), `${name}.json`);
      return;
    }
    const svg = boardSvg(draft);
    if (format === 'svg') {
      downloadBlob(new Blob([svg], { type: 'image/svg+xml' }), `${name}.svg`);
      return;
    }
    if (format === 'png') {
      const img = new Image();
      const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
      img.onload = () => {
        const maxWidth = 2400;
        const scale = Math.min(1, maxWidth / draft.width);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(draft.width * scale);
        canvas.height = Math.round(draft.height * scale);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => { if (blob) downloadBlob(blob, `${name}.png`); URL.revokeObjectURL(url); }, 'image/png');
      };
      img.onerror = () => { URL.revokeObjectURL(url); showNotice('PNG export could not be generated.', 'error'); };
      img.src = url;
      return;
    }
    const win = window.open('', '_blank');
    if (!win) { showNotice('The browser blocked the print window.', 'error'); return; }
    win.document.open();
    win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(draft.title)}</title><style>@page{size:landscape;margin:8mm}body{font-family:Arial,sans-serif;margin:0;color:#111}header{padding:0 0 8px}h1{font-size:20px;margin:0 0 4px}p{font-size:11px;margin:0 0 8px}.board svg{width:100%;height:auto;border:1px solid #777}.meta{font-size:9px;margin-top:6px}</style></head><body><header><h1>${escapeHtml(draft.title)}</h1><p>${escapeHtml(draft.description)}</p></header><div class="board">${svg}</div><p class="meta">Board ID: ${escapeHtml(draft.id)} · ${escapeHtml(schema)} · Generated ${escapeHtml(new Date().toLocaleString())}</p><script>window.addEventListener('load',()=>window.print())<\/script></body></html>`);
    win.document.close();
  };

  const openBoardLibrary = () => {
    refreshWorkspace();
    ensureModal().hidden = false;
    document.documentElement.classList.add('sc-board-open');
    const stage = modal.querySelector('[data-board-stage]');
    const sidebar = modal.querySelector('[data-board-sidebar]');
    modal.querySelector('[data-board-title]').textContent = 'Saved Visual Research Boards';
    modal.querySelector('[data-board-kind]').textContent = 'Research Notebook';
    modal.querySelector('[data-board-toolbar]').innerHTML = `<div class="sc-board-tools"><button type="button" data-new-board-modal="whiteboard">New Whiteboard</button><button type="button" data-new-board-modal="chalkboard">New Chalkboard</button></div>`;
    sidebar.innerHTML = `<section class="sc-board-properties"><p class="sc-library__eyebrow">Local-first boards</p><h3>Saved boards</h3><p>Open a board or create a new visual research workspace.</p></section>`;
    stage.className = 'sc-board-studio__stage sc-board-library-screen';
    stage.style.width = '100%'; stage.style.height = '100%'; stage.style.transform = 'none';
    modal.querySelector('[data-board-sizer]').style.width = '100%'; modal.querySelector('[data-board-sizer]').style.height = '100%';
    stage.innerHTML = `<div class="sc-board-library-list">${workspace.boards.length ? workspace.boards.map((board) => `<article><span>${escapeHtml(board.type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard')}</span><h3>${escapeHtml(board.title)}</h3><p>${escapeHtml(board.description || '')}</p><small>${board.nodes?.length || 0} cards · ${board.edges?.length || 0} relationships · Updated ${escapeHtml(new Date(board.updatedAt || board.createdAt).toLocaleDateString())}</small><div><button type="button" data-open-saved-board="${escapeHtml(board.id)}">Open</button><button type="button" data-export-saved-board="${escapeHtml(board.id)}">JSON</button><button type="button" data-delete-saved-board="${escapeHtml(board.id)}">Delete</button></div></article>`).join('') : '<p>No saved boards yet.</p>'}</div>`;
    draft = null;
    setDirty(false);
  };

  const bindModalEvents = () => {
    modal.addEventListener('click', (event) => {
      if (event.target.closest('[data-board-close]')) { closeEditor(); return; }
      if (event.target.closest('[data-board-save]')) { saveDraft(); return; }
      const newModal = event.target.closest('[data-new-board-modal]');
      if (newModal) { const type = newModal.dataset.newBoardModal; openEditor(makeBoard(type, { templateId: type === 'chalkboard' ? 'blank_chalkboard' : 'blank_whiteboard' }), { dirty: true }); return; }
      const openSaved = event.target.closest('[data-open-saved-board]');
      if (openSaved) { refreshWorkspace(); const found = boardById(openSaved.dataset.openSavedBoard); if (found) openEditor(found); return; }
      const exportSaved = event.target.closest('[data-export-saved-board]');
      if (exportSaved) { refreshWorkspace(); const found = boardById(exportSaved.dataset.exportSavedBoard); if (found) { const previous = draft; draft = clone(found); exportBoard('json'); draft = previous; } return; }
      const deleteSaved = event.target.closest('[data-delete-saved-board]');
      if (deleteSaved) { refreshWorkspace(); if (!window.confirm(strings.confirmDelete || 'Delete this board?')) return; workspace.boards = workspace.boards.filter((item) => item.id !== deleteSaved.dataset.deleteSavedBoard); writeWorkspace(workspace); openBoardLibrary(); return; }
      if (!draft) return;
      const modeButton = event.target.closest('[data-board-mode]');
      if (modeButton) { mode = modeButton.dataset.boardMode; selected.clear(); renderEditor(); return; }
      const addButton = event.target.closest('[data-add-node]');
      if (addButton) { addNode(addButton.dataset.addNode); return; }
      if (event.target.closest('[data-connect-selected]')) { connectSelected(); return; }
      if (event.target.closest('[data-delete-selected]')) { deleteSelected(); return; }
      if (event.target.closest('[data-board-undo]')) { if (history.length) { future.push(snapshot()); restoreSnapshot(history.pop()); } return; }
      if (event.target.closest('[data-board-redo]')) { if (future.length) { history.push(snapshot()); restoreSnapshot(future.pop()); } return; }
      const zoomButton = event.target.closest('[data-board-zoom]');
      if (zoomButton) { zoom = clamp(zoom + (zoomButton.dataset.boardZoom === 'in' ? 0.1 : -0.1), 0.4, 1.4); renderEditor(); return; }
      const exportButton = event.target.closest('[data-board-export]');
      if (exportButton) { exportBoard(exportButton.dataset.boardExport); return; }
      if (event.target.closest('[data-board-clear-ink]')) { if (!draft.strokes.length) return; commitHistory(); draft.strokes = []; setDirty(true); renderEditor(); return; }
      const nodeEl = event.target.closest('[data-node-id]');
      if (nodeEl && mode === 'select' && !event.target.closest('a')) {
        const id = nodeEl.dataset.nodeId;
        if (event.shiftKey) selected.has(id) ? selected.delete(id) : selected.add(id); else { selected.clear(); selected.add(id); }
        renderEditor(); return;
      }
      const edgeEl = event.target.closest('[data-edge-id]');
      if (edgeEl && mode === 'select') { selected.clear(); selected.add(`edge:${edgeEl.dataset.edgeId}`); renderEditor(); return; }
      const stroke = event.target.closest('[data-stroke-id]');
      if (stroke && mode === 'eraser') { commitHistory(); draft.strokes = draft.strokes.filter((item) => item.id !== stroke.dataset.strokeId); setDirty(true); renderEditor(); }
    });

    modal.addEventListener('change', (event) => {
      if (!draft) return;
      const addWorkspace = event.target.closest('[data-add-workspace-item]');
      if (addWorkspace && addWorkspace.value) { const [kind, id] = addWorkspace.value.split('|'); addWorkspaceItem(kind, id); return; }
      const boardTemplate = event.target.closest('[data-board-template]');
      if (boardTemplate && boardTemplate.value !== draft.templateId) {
        if (!window.confirm('Changing the board template replaces the current unsaved cards, connectors, and handwriting. Continue?')) { boardTemplate.value = draft.templateId; return; }
        commitHistory();
        const replacement = makeBoard(draft.type, { templateId: boardTemplate.value, id: draft.id, title: draft.title, description: templateFor(boardTemplate.value, draft.type).description || draft.description, recordId: draft.recordId, sourceId: draft.sourceId, matrixId: draft.matrixId, noteId: draft.noteId, collectionIds: draft.collectionIds, createdAt: draft.createdAt });
        draft = replacement; selected.clear(); setDirty(true); renderEditor(); return;
      }
      const boardField = event.target.closest('[data-board-field]');
      if (boardField) { commitHistory(); draft[boardField.dataset.boardField] = boardField.value; if (boardField.dataset.boardField === 'title') modal.querySelector('[data-board-title]').textContent = boardField.value || 'Untitled board'; setDirty(true); renderEditor(); return; }
      const nodeField = event.target.closest('[data-node-field]');
      if (nodeField) { const node = draft.nodes.find((item) => selected.has(item.id)); if (!node) return; commitHistory(); const key = nodeField.dataset.nodeField; node[key] = ['width','height'].includes(key) ? Number(nodeField.value) : nodeField.value; if (key === 'type' && !node.color) node.color = nodeColors[node.type]; node.updatedAt = now(); setDirty(true); renderEditor(); return; }
      const edgeField = event.target.closest('[data-edge-field]');
      if (edgeField) { const edgeId = [...selected][0]?.replace(/^edge:/, ''); const edge = draft.edges.find((item) => item.id === edgeId); if (!edge) return; commitHistory(); edge[edgeField.dataset.edgeField] = edgeField.value; setDirty(true); renderEditor(); return; }
      if (event.target.closest('[data-board-collection]')) { draft.collectionIds = Array.from(modal.querySelectorAll('[data-board-collection]:checked')).map((input) => input.value); if (!draft.collectionIds.length) draft.collectionIds = ['collection_inbox']; setDirty(true); }
    });

    modal.addEventListener('input', (event) => {
      if (!draft) return;
      const boardField = event.target.closest('[data-board-field]');
      if (boardField && ['title','description'].includes(boardField.dataset.boardField)) { draft[boardField.dataset.boardField] = boardField.value; if (boardField.dataset.boardField === 'title') modal.querySelector('[data-board-title]').textContent = boardField.value || 'Untitled board'; setDirty(true); return; }
      const nodeField = event.target.closest('[data-node-field]');
      if (nodeField && ['title','body','url'].includes(nodeField.dataset.nodeField)) { const node = draft.nodes.find((item) => selected.has(item.id)); if (node) { node[nodeField.dataset.nodeField] = nodeField.value; node.updatedAt = now(); setDirty(true); renderStage(); } return; }
      const edgeField = event.target.closest('[data-edge-field]');
      if (edgeField && edgeField.dataset.edgeField === 'label') { const edgeId = [...selected][0]?.replace(/^edge:/, ''); const edge = draft.edges.find((item) => item.id === edgeId); if (edge) { edge.label = edgeField.value; setDirty(true); renderStage(); } }
    });

    modal.addEventListener('pointerdown', (event) => {
      if (!draft) return;
      const handle = event.target.closest('[data-node-handle]');
      if (handle && mode === 'select') {
        const el = handle.closest('[data-node-id]');
        const node = draft.nodes.find((item) => item.id === el.dataset.nodeId);
        if (!node) return;
        commitHistory();
        const point = canvasPoint(event);
        dragState = { node, offsetX: point.x - node.x, offsetY: point.y - node.y, pointerId: event.pointerId };
        handle.setPointerCapture?.(event.pointerId);
        event.preventDefault();
        return;
      }
      const stage = event.target.closest('[data-board-stage]');
      if (stage && ['pen','highlighter'].includes(mode) && !event.target.closest('[data-node-id]')) {
        commitHistory();
        const point = canvasPoint(event);
        drawState = { points: [point], pointerId: event.pointerId, tool: mode };
        stage.setPointerCapture?.(event.pointerId);
        event.preventDefault();
      }
    });

    modal.addEventListener('pointermove', (event) => {
      if (dragState && event.pointerId === dragState.pointerId) {
        const point = canvasPoint(event);
        dragState.node.x = clamp(point.x - dragState.offsetX, 0, draft.width - dragState.node.width);
        dragState.node.y = clamp(point.y - dragState.offsetY, 0, draft.height - dragState.node.height);
        const el = modal.querySelector(`[data-node-id="${CSS.escape(dragState.node.id)}"]`);
        if (el) { el.style.left = `${dragState.node.x}px`; el.style.top = `${dragState.node.y}px`; }
        setDirty(true);
        return;
      }
      if (drawState && event.pointerId === drawState.pointerId) {
        drawState.points.push(canvasPoint(event));
        const live = modal.querySelector('[data-live-stroke]');
        if (live) {
          live.setAttribute('d', pathFromPoints(drawState.points));
          live.setAttribute('stroke', draft.type === 'chalkboard' ? (drawState.tool === 'highlighter' ? '#a8ff88' : '#e8ffe5') : (drawState.tool === 'highlighter' ? '#ffe47a' : '#721019'));
          live.setAttribute('stroke-width', drawState.tool === 'highlighter' ? '24' : '5');
          live.setAttribute('opacity', drawState.tool === 'highlighter' ? '0.35' : '0.95');
        }
      }
    });

    const finishPointer = (event) => {
      if (dragState && event.pointerId === dragState.pointerId) { dragState.node.updatedAt = now(); dragState = null; renderEditor(); }
      if (drawState && event.pointerId === drawState.pointerId) {
        const chalk = draft.type === 'chalkboard';
        draft.strokes.push({ id: uid('stroke'), d: pathFromPoints(drawState.points), tool: drawState.tool, color: chalk ? (drawState.tool === 'highlighter' ? '#a8ff88' : '#e8ffe5') : (drawState.tool === 'highlighter' ? '#ffe47a' : '#721019'), width: drawState.tool === 'highlighter' ? 24 : 5, opacity: drawState.tool === 'highlighter' ? 0.35 : 0.95, createdAt: now() });
        drawState = null;
        setDirty(true);
        renderEditor();
      }
    };
    modal.addEventListener('pointerup', finishPointer);
    modal.addEventListener('pointercancel', finishPointer);

    modal.addEventListener('keydown', (event) => {
      if (!draft) return;
      const tag = event.target.tagName;
      if (['INPUT','TEXTAREA','SELECT'].includes(tag)) return;
      if ((event.key === 'Delete' || event.key === 'Backspace') && selected.size) { event.preventDefault(); deleteSelected(); }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); saveDraft(); }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); if (event.shiftKey) { if (future.length) { history.push(snapshot()); restoreSnapshot(future.pop()); } } else if (history.length) { future.push(snapshot()); restoreSnapshot(history.pop()); } }
    });
  };

  const seedFromRecord = (record, type = 'whiteboard') => {
    refreshWorkspace();
    let saved = recordById(record.id);
    if (!saved) {
      saved = { recordId: Number(record.id), recordIdentifier: words(record.record_identifier), title: words(record.title), url: words(record.url), typeLabel: words(record.type_label || 'Publication'), excerpt: words(record.excerpt), resources: record.resources || {}, categories: record.categories || [], concepts: record.concepts || [], series: record.series || null, collectionIds: ['collection_inbox'], createdAt: now(), updatedAt: now() };
      workspace.savedRecords.push(saved);
      writeWorkspace(workspace);
    }
    const templateId = type === 'chalkboard' ? 'equation_workbench' : 'concept_map';
    const board = makeBoard(type, { templateId, title: `${type === 'chalkboard' ? 'Chalkboard' : 'Whiteboard'}: ${record.title}`, description: words(record.excerpt), recordId: String(record.id), collectionIds: saved.collectionIds || ['collection_inbox'] });
    const anchor = board.nodes[0] || makeNode(type === 'chalkboard' ? 'equation' : 'publication');
    anchor.type = 'publication'; anchor.title = words(record.title); anchor.body = words(record.excerpt); anchor.url = words(record.url); anchor.referenceType = 'publication'; anchor.referenceId = String(record.id); anchor.color = '#ffffff';
    if (!board.nodes.length) board.nodes.push(anchor);
    openEditor(board, { dirty: true });
  };

  document.addEventListener('click', (event) => {
    const launcher = event.target.closest('[data-sc-library-board-root]');
    if (!launcher) return;
    const newBoard = event.target.closest('[data-new-board]');
    if (newBoard) { const type = newBoard.dataset.newBoard; openEditor(makeBoard(type, { templateId: type === 'chalkboard' ? 'blank_chalkboard' : 'blank_whiteboard' }), { dirty: true }); return; }
    if (event.target.closest('[data-open-board-library]')) { openBoardLibrary(); }
  });

  document.addEventListener('sc-library-new-board-for-record', (event) => {
    const record = event.detail?.record;
    if (!record?.id) return;
    seedFromRecord(record, event.detail?.type === 'chalkboard' ? 'chalkboard' : 'whiteboard');
  });
  document.addEventListener('sc-library-new-board', (event) => {
    const type = event.detail?.type === 'chalkboard' ? 'chalkboard' : 'whiteboard';
    openEditor(makeBoard(type, event.detail?.seed || {}), { dirty: true });
  });
  document.addEventListener('sc-library-open-board', (event) => {
    refreshWorkspace();
    const found = boardById(event.detail?.id);
    if (found) openEditor(found);
  });
  document.addEventListener('sc-library-open-board-library', openBoardLibrary);

  window.addEventListener('storage', (event) => { if (event.key === storageKey) refreshWorkspace(); });

  document.querySelectorAll('[data-sc-library-board-root]').forEach((root) => {
    if (root.dataset.boardOpen === '1') {
      window.setTimeout(() => {
        refreshWorkspace();
        const found = root.dataset.boardId ? boardById(root.dataset.boardId) : null;
        openEditor(found || makeBoard(root.dataset.boardType || shared.defaultType || 'whiteboard'), { dirty: !found });
      }, 0);
    }
  });
})();
