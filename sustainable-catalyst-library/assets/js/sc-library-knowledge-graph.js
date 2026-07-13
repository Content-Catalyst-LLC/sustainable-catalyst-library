(() => {
  'use strict';

  const shared = window.SCLibraryGraph || {};
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));
  const qs = (root, selector) => root.querySelector(selector);
  const typeClass = (value) => `sc-graph-node--${String(value || 'other').replace(/[^a-z0-9_-]+/gi, '-')}`;
  const truncate = (value, length = 120) => String(value || '').length > length ? `${String(value).slice(0, length - 1)}…` : String(value || '');

  const fetchJson = async (url, options = {}) => {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (shared.nonce) headers['X-WP-Nonce'] = shared.nonce;
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
    return data;
  };

  const positionNodes = (nodes, width, height) => {
    const groups = new Map();
    nodes.forEach((node) => {
      if (!groups.has(node.type)) groups.set(node.type, []);
      groups.get(node.type).push(node);
    });
    const orderedTypes = ['record', 'concept', 'series', 'category', 'method', 'tool', 'dataset', 'place', 'source', 'claim', 'evidence', 'question', 'tag', 'organization', 'event', 'other'];
    const types = [...groups.keys()].sort((a, b) => orderedTypes.indexOf(a) - orderedTypes.indexOf(b));
    const centerX = width / 2;
    const centerY = height / 2;
    const positions = new Map();
    types.forEach((type, groupIndex) => {
      const items = groups.get(type) || [];
      const radius = items.length <= 1 ? 0 : Math.min(width, height) * (0.17 + groupIndex * 0.055);
      const phase = (groupIndex * Math.PI) / 7;
      items.forEach((node, index) => {
        const angle = items.length === 1 ? 0 : phase + (index / items.length) * Math.PI * 2;
        const jitter = ((node.id * 37) % 19) - 9;
        positions.set(node.id, {
          x: Math.max(60, Math.min(width - 60, centerX + Math.cos(angle) * radius + jitter)),
          y: Math.max(60, Math.min(height - 60, centerY + Math.sin(angle) * radius - jitter)),
        });
      });
    });
    return positions;
  };

  const renderInspector = (root, item, kind) => {
    const inspector = qs(root, '[data-graph-inspector]');
    if (!inspector) return;
    if (!item) {
      inspector.innerHTML = '<p>Select an entity or relationship to inspect its provenance and context.</p>';
      return;
    }
    if (kind === 'edge') {
      inspector.innerHTML = `
        <p class="sc-library-graph__eyebrow">Relationship</p>
        <h3>${escapeHtml(item.type_label || item.label || item.type)}</h3>
        <dl>
          <div><dt>Confidence</dt><dd>${Math.round(Number(item.confidence || 0) * 100)}%</dd></div>
          <div><dt>Basis</dt><dd>${escapeHtml(item.confidence_basis || 'Not documented')}</dd></div>
          <div><dt>Provenance</dt><dd>${escapeHtml(item.provenance_type || 'Not documented')}</dd></div>
          <div><dt>Visibility</dt><dd>${escapeHtml(item.visibility || 'public')}</dd></div>
        </dl>
        ${item.evidence_note ? `<p>${escapeHtml(item.evidence_note)}</p>` : '<p class="sc-library-graph__muted">No evidence note has been recorded.</p>'}
        ${item.provenance_url ? `<p><a href="${escapeHtml(item.provenance_url)}" target="_blank" rel="noopener">Open provenance source ↗</a></p>` : ''}
      `;
      return;
    }
    inspector.innerHTML = `
      <p class="sc-library-graph__eyebrow">${escapeHtml(item.type_label || item.type)}</p>
      <h3>${escapeHtml(item.label)}</h3>
      ${item.description ? `<p>${escapeHtml(truncate(item.description, 360))}</p>` : ''}
      <dl>
        <div><dt>Entity key</dt><dd><code>${escapeHtml(item.external_key)}</code></dd></div>
        <div><dt>Source</dt><dd>${escapeHtml(item.source_kind || 'unknown')}</dd></div>
        <div><dt>Visibility</dt><dd>${escapeHtml(item.visibility || 'public')}</dd></div>
      </dl>
      ${item.url ? `<p><a href="${escapeHtml(item.url)}">Open canonical record →</a></p>` : ''}
    `;
  };

  const renderGraph = (root, data) => {
    const canvas = qs(root, '[data-graph-canvas]');
    const list = qs(root, '[data-graph-list]');
    const status = qs(root, '[data-graph-status]');
    const nodes = Array.isArray(data.nodes) ? data.nodes : [];
    const edges = Array.isArray(data.edges) ? data.edges : [];
    if (!nodes.length) {
      canvas.innerHTML = `<p class="sc-library-graph__empty">${escapeHtml(shared.strings?.empty || 'No graph entities match these filters.')}</p>`;
      list.innerHTML = '';
      status.textContent = shared.strings?.empty || 'No graph entities match these filters.';
      return;
    }

    const width = Math.max(760, canvas.clientWidth || 980);
    const height = Math.max(540, Math.min(900, 420 + nodes.length * 2.5));
    const positions = positionNodes(nodes, width, height);
    const nodeById = new Map(nodes.map((node) => [node.id, node]));
    const svgParts = [`<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Knowledge graph with ${nodes.length} entities and ${edges.length} relationships">`];
    svgParts.push('<defs><marker id="sc-graph-arrow" markerWidth="8" markerHeight="8" refX="7" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L0,6 L7,3 z"></path></marker></defs>');

    edges.forEach((edge) => {
      const source = positions.get(edge.source);
      const target = positions.get(edge.target);
      if (!source || !target) return;
      const opacity = Math.max(0.25, Number(edge.confidence || 0.5));
      const marker = edge.directionality === 'undirected' ? '' : ' marker-end="url(#sc-graph-arrow)"';
      svgParts.push(`<line class="sc-graph-edge" data-edge-id="${edge.id}" x1="${source.x}" y1="${source.y}" x2="${target.x}" y2="${target.y}" style="opacity:${opacity}"${marker}><title>${escapeHtml(edge.type_label || edge.type)} · confidence ${Math.round(Number(edge.confidence || 0) * 100)}%</title></line>`);
    });

    nodes.forEach((node) => {
      const position = positions.get(node.id);
      if (!position) return;
      const radius = node.type === 'record' ? 13 : node.type === 'concept' ? 11 : 9;
      svgParts.push(`<g class="sc-graph-node ${typeClass(node.type)}" data-node-id="${node.id}" tabindex="0" role="button" aria-label="${escapeHtml(`${node.type_label}: ${node.label}`)}"><circle cx="${position.x}" cy="${position.y}" r="${radius}"></circle><text x="${position.x + radius + 4}" y="${position.y + 4}">${escapeHtml(truncate(node.label, 42))}</text><title>${escapeHtml(node.label)}</title></g>`);
    });
    svgParts.push('</svg>');
    canvas.innerHTML = svgParts.join('');
    status.textContent = `${nodes.length} ${nodes.length === 1 ? (shared.strings?.node || 'entity') : 'entities'} · ${edges.length} ${edges.length === 1 ? (shared.strings?.edge || 'relationship') : 'relationships'}`;

    canvas.querySelectorAll('[data-node-id]').forEach((element) => {
      const activate = () => renderInspector(root, nodeById.get(Number(element.dataset.nodeId)), 'node');
      element.addEventListener('click', activate);
      element.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); activate(); } });
    });
    const edgeById = new Map(edges.map((edge) => [edge.id, edge]));
    canvas.querySelectorAll('[data-edge-id]').forEach((element) => element.addEventListener('click', () => renderInspector(root, edgeById.get(Number(element.dataset.edgeId)), 'edge')));

    const relationshipRows = edges.map((edge) => {
      const source = nodeById.get(edge.source);
      const target = nodeById.get(edge.target);
      return `<li><button type="button" data-list-edge="${edge.id}">${escapeHtml(source?.label || `#${edge.source}`)} <strong>${escapeHtml(edge.type_label || edge.type)}</strong> ${escapeHtml(target?.label || `#${edge.target}`)} <span>${Math.round(Number(edge.confidence || 0) * 100)}%</span></button></li>`;
    });
    list.innerHTML = relationshipRows.length ? `<ol>${relationshipRows.join('')}</ol>` : '<p>No relationships are visible in this filtered view.</p>';
    list.querySelectorAll('[data-list-edge]').forEach((button) => button.addEventListener('click', () => renderInspector(root, edgeById.get(Number(button.dataset.listEdge)), 'edge')));
  };

  const loadGraph = async (root, params = {}) => {
    const status = qs(root, '[data-graph-status]');
    status.textContent = shared.strings?.loading || 'Loading knowledge graph…';
    const query = new URLSearchParams({
      limit: root.dataset.limit || '250',
      depth: root.dataset.depth || '2',
      ...(root.dataset.root ? { root: root.dataset.root } : {}),
      ...(root.dataset.mode === 'admin' ? { context: 'edit' } : {}),
      ...params,
    });
    try {
      const data = await fetchJson(`${shared.restBase}?${query.toString()}`);
      renderGraph(root, data);
    } catch (error) {
      status.textContent = shared.strings?.error || 'The knowledge graph could not be loaded.';
      qs(root, '[data-graph-canvas]').innerHTML = `<p class="sc-library-graph__error">${escapeHtml(error.message)}</p>`;
    }
  };

  document.querySelectorAll('[data-sc-library-graph]').forEach((root) => {
    const requestedRoot = new URLSearchParams(window.location.search).get('root');
    if (!root.dataset.root && requestedRoot && /^[a-z0-9_-]+:[^\s]+$/i.test(requestedRoot)) root.dataset.root = requestedRoot.slice(0, 191);
    const form = qs(root, '[data-graph-filters]');
    if (form) {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(form).entries());
        Object.keys(values).forEach((key) => { if (!values[key]) delete values[key]; });
        loadGraph(root, values);
      });
      form.addEventListener('reset', () => window.setTimeout(() => loadGraph(root), 0));
    }
    loadGraph(root);
  });

  const rebuildText = (state) => {
    if (!state || state.status === 'idle') return 'No graph rebuild is currently running.';
    const phase = String(state.phase || state.status || 'unknown').replace(/_/g, ' ');
    const counts = [
      `${Number(state.records_processed || 0)} records`,
      `${Number(state.relationships_processed || 0)} relationships`,
      `${Number(state.plans_processed || 0)} plans`,
    ].join(' · ');
    if (state.status === 'complete') return `${shared.strings?.rebuildComplete || 'Knowledge graph rebuild complete.'} ${counts}`;
    if (state.status === 'error') return `${shared.strings?.rebuildError || 'The graph rebuild stopped with an error.'} ${state.error || ''}`.trim();
    return `Rebuild running · ${phase} · ${counts}`;
  };

  document.querySelectorAll('[data-graph-rebuild]').forEach((root) => {
    const progress = qs(root, '[data-graph-rebuild-progress]');
    const start = qs(root, '[data-graph-rebuild-start]');
    const resume = qs(root, '[data-graph-rebuild-continue]');
    const batch = qs(root, '[data-graph-rebuild-batch]');
    let running = false;

    const setState = (state) => {
      progress.textContent = rebuildText(state);
      const active = state?.status === 'running';
      start.disabled = running || active;
      resume.disabled = running || !active;
      root.dataset.status = state?.status || 'idle';
      root.dataset.phase = state?.phase || 'idle';
    };

    const continueUntilDone = async (state) => {
      running = true;
      setState(state);
      try {
        let current = state;
        while (current?.status === 'running') {
          current = await fetchJson(shared.rebuildContinue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}',
          });
          setState(current);
          await new Promise((resolve) => window.setTimeout(resolve, 80));
        }
        if (current?.status === 'complete') {
          document.querySelectorAll('[data-sc-library-graph]').forEach((graph) => loadGraph(graph));
        }
      } catch (error) {
        progress.textContent = `${shared.strings?.rebuildError || 'The graph rebuild stopped with an error.'} ${error.message}`;
      } finally {
        running = false;
        try {
          const latest = await fetchJson(shared.rebuildStatus);
          setState(latest);
        } catch (_) {
          start.disabled = false;
          resume.disabled = false;
        }
      }
    };

    start.addEventListener('click', async () => {
      if (running || !shared.rebuildStart) return;
      progress.textContent = shared.strings?.rebuildStarting || 'Starting graph rebuild…';
      try {
        const state = await fetchJson(shared.rebuildStart, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ batch_size: Number(batch.value || 50) }),
        });
        continueUntilDone(state);
      } catch (error) {
        progress.textContent = `${shared.strings?.rebuildError || 'The graph rebuild stopped with an error.'} ${error.message}`;
      }
    });

    resume.addEventListener('click', async () => {
      if (running || !shared.rebuildStatus) return;
      try {
        const state = await fetchJson(shared.rebuildStatus);
        if (state?.status === 'running') continueUntilDone(state);
        else setState(state);
      } catch (error) {
        progress.textContent = `${shared.strings?.rebuildError || 'The graph rebuild stopped with an error.'} ${error.message}`;
      }
    });

    if (shared.rebuildStatus) {
      fetchJson(shared.rebuildStatus).then(setState).catch(() => setState({ status: 'idle', phase: 'idle' }));
    }
  });
})();
