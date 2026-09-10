(() => {
  const roots = document.querySelectorAll('[data-sc-carbon-nature]');
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const chips = (values, cls = '') => (Array.isArray(values) ? values : []).map((value) => `<span class="sc-cn__chip ${cls}">${esc(value)}</span>`).join('');
  const labelForNode = (node) => node?.label || node?.key || 'Unnamed node';

  roots.forEach((root) => {
    const modes = Array.from(root.querySelectorAll('[data-cn-mode]'));
    const panels = Array.from(root.querySelectorAll('[data-cn-panel]'));
    const measureForm = root.querySelector('.sc-cn__measure-search');
    const measureStatus = root.querySelector('[data-cn-measure-status]');
    const measureResults = root.querySelector('[data-cn-measure-results]');
    const graphForm = root.querySelector('.sc-cn__graph-search');
    const graphStatus = root.querySelector('[data-cn-graph-status]');
    const graphSummary = root.querySelector('[data-cn-graph-summary]');
    const graphResults = root.querySelector('[data-cn-graph-results]');
    const measuresEndpoint = root.dataset.measuresEndpoint;
    const graphEndpoint = root.dataset.graphEndpoint;

    const setMode = (mode) => {
      modes.forEach((button) => {
        const active = button.dataset.cnMode === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.cnPanel !== mode;
      });
    };
    modes.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.cnMode)));

    const renderMeasures = (payload) => {
      const measures = Array.isArray(payload.measures) ? payload.measures : [];
      if (!measures.length) {
        measureResults.innerHTML = '<p class="sc-cn__empty">No measure profiles match those filters.</p>';
        return;
      }
      measureResults.innerHTML = measures.map((item) => `
        <article class="sc-cn__card">
          <div class="sc-cn__meta">${esc(item.measure_family)} · ${esc(item.primary_domain)}</div>
          <h3>${esc(item.label)}</h3>
          <p>${esc(item.description)}</p>
          <div class="sc-cn__matrix">
            <div><strong>Systems</strong><div class="sc-cn__chips">${chips(item.applicable_systems)}</div></div>
            <div><strong>Carbon pools</strong><div class="sc-cn__chips">${chips(item.target_carbon_pools)}</div></div>
            <div><strong>GHGs</strong><div class="sc-cn__chips">${chips(item.relevant_gases)}</div></div>
            <div><strong>Outcome types</strong><div class="sc-cn__chips">${chips(item.outcome_types)}</div></div>
          </div>
          <details>
            <summary>MRV, integrity &amp; evidence context</summary>
            <div class="sc-cn__detail-grid">
              <div><strong>MRV families</strong><div class="sc-cn__chips">${chips(item.mrv_method_families)}</div></div>
              <div><strong>Integrity dimensions</strong><div class="sc-cn__chips">${chips(item.integrity_dimensions, 'sc-cn__chip--risk')}</div></div>
              <div><strong>Potential co-benefit contexts</strong><div class="sc-cn__chips">${chips(item.co_benefit_concepts, 'sc-cn__chip--benefit')}</div></div>
              <div><strong>Risk / review contexts</strong><div class="sc-cn__chips">${chips(item.risk_concepts, 'sc-cn__chip--risk')}</div></div>
            </div>
            <ul class="sc-cn__evidence">${(item.evidence_requirements || []).map((text) => `<li>${esc(text)}</li>`).join('')}</ul>
          </details>
        </article>`).join('');
    };

    const loadMeasures = async () => {
      if (!measureForm || !measureStatus || !measureResults || !measuresEndpoint) return;
      const data = new FormData(measureForm);
      const url = new URL(measuresEndpoint, window.location.origin);
      for (const [key, value] of data.entries()) {
        const clean = value.toString().trim();
        if (clean) url.searchParams.set(key, clean);
      }
      url.searchParams.set('limit', '50');
      measureStatus.textContent = 'Loading governed measure profiles…';
      measureResults.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        measureStatus.textContent = `${payload.count || 0} measure profiles · Carbon & Nature v${payload.subsystem_version || '0.3.0'}`;
        renderMeasures(payload);
      } catch (error) {
        measureStatus.textContent = `Carbon & Nature measure registry unavailable: ${error.message || error}`;
      }
    };

    const renderGraph = (payload) => {
      const nodes = Array.isArray(payload.nodes) ? payload.nodes : [];
      const edges = Array.isArray(payload.edges) ? payload.edges : [];
      const counts = nodes.reduce((acc, node) => {
        const type = node.node_type || 'other';
        acc[type] = (acc[type] || 0) + 1;
        return acc;
      }, {});
      graphSummary.innerHTML = `
        <div class="sc-cn__stat"><strong>${esc(payload.node_count || nodes.length)}</strong><span>Nodes</span></div>
        <div class="sc-cn__stat"><strong>${esc(payload.edge_count || edges.length)}</strong><span>Edges</span></div>
        ${Object.entries(counts).map(([key, value]) => `<div class="sc-cn__stat"><strong>${esc(value)}</strong><span>${esc(key)}</span></div>`).join('')}
      `;
      if (!nodes.length && !edges.length) {
        graphResults.innerHTML = '<p class="sc-cn__empty">No graph relationships match that node or type.</p>';
        return;
      }

      const nodeCards = nodes.slice(0, 60).map((node) => {
        const record = node.record || {};
        let description = record.definition || record.description || record.purpose || record.scope || '';
        let detail = '';
        if (node.node_type === 'methodology') {
          detail = `<div class="sc-cn__chips">${chips(record.target_outcomes || [])}</div>`;
        } else if (node.node_type === 'evidence') {
          detail = `<div class="sc-cn__meta">${esc(record.record_type || '')} · ${esc(record.authority_class || '')}</div>`;
        } else if (node.node_type === 'measure') {
          detail = `<div class="sc-cn__meta">${esc(record.measure_family || '')}</div>`;
        } else if (node.node_type === 'concept') {
          detail = `<div class="sc-cn__meta">${esc(record.concept_type || '')} · ${esc(record.domain || '')}</div>`;
        }
        return `<article class="sc-cn__node sc-cn__node--${esc(node.node_type)}">
          <div class="sc-cn__node-type">${esc(node.node_type)}</div>
          <h3>${esc(labelForNode(node))}</h3>
          <code>${esc(node.key)}</code>
          ${detail}
          ${description ? `<p>${esc(description)}</p>` : ''}
        </article>`;
      }).join('');

      const edgeRows = edges.slice(0, 160).map((edge) => `
        <tr>
          <td><span class="sc-cn__edge-type">${esc(edge.subject_type)}</span><code>${esc(edge.subject_key)}</code></td>
          <td><strong>${esc(edge.predicate)}</strong></td>
          <td><span class="sc-cn__edge-type">${esc(edge.object_type)}</span><code>${esc(edge.object_key)}</code></td>
        </tr>`).join('');

      graphResults.innerHTML = `
        <section class="sc-cn__graph-block">
          <h3>Graph Nodes</h3>
          <div class="sc-cn__nodes">${nodeCards}</div>
        </section>
        <section class="sc-cn__graph-block">
          <h3>Explicit Relationships</h3>
          <div class="sc-cn__edge-wrap"><table class="sc-cn__edges">
            <thead><tr><th>Subject</th><th>Relationship</th><th>Object</th></tr></thead>
            <tbody>${edgeRows}</tbody>
          </table></div>
        </section>`;
    };

    const loadGraph = async () => {
      if (!graphForm || !graphStatus || !graphSummary || !graphResults || !graphEndpoint) return;
      const data = new FormData(graphForm);
      const url = new URL(graphEndpoint, window.location.origin);
      for (const [key, value] of data.entries()) {
        const clean = value.toString().trim();
        if (clean) url.searchParams.set(key, clean);
      }
      url.searchParams.set('limit', '200');
      graphStatus.textContent = 'Loading governed evidence-methodology graph…';
      graphSummary.innerHTML = '';
      graphResults.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        graphStatus.textContent = `${payload.node_count || 0} nodes · ${payload.edge_count || 0} explicit relationships · Carbon & Nature v${payload.subsystem_version || '0.3.0'}`;
        renderGraph(payload);
      } catch (error) {
        graphStatus.textContent = `Carbon & Nature evidence graph unavailable: ${error.message || error}`;
      }
    };

    if (measureForm) {
      measureForm.addEventListener('submit', (event) => { event.preventDefault(); loadMeasures(); });
      measureForm.addEventListener('reset', () => { window.setTimeout(loadMeasures, 0); });
    }
    if (graphForm) {
      graphForm.addEventListener('submit', (event) => { event.preventDefault(); loadGraph(); });
      graphForm.addEventListener('reset', () => { window.setTimeout(loadGraph, 0); });
    }

    setMode('graph');
    loadGraph();
    loadMeasures();
  });
})();
