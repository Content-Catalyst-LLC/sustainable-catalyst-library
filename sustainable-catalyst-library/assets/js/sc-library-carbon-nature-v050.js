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
    const projectStatus = root.querySelector('[data-cn-project-status]');
    const projectSummary = root.querySelector('[data-cn-project-summary]');
    const projectResults = root.querySelector('[data-cn-project-results]');
    const measuresEndpoint = root.dataset.measuresEndpoint;
    const graphEndpoint = root.dataset.graphEndpoint;
    const objectModelEndpoint = root.dataset.objectModelEndpoint;
    const packetTemplateEndpoint = root.dataset.packetTemplateEndpoint;
    const librarianForm = root.querySelector('.sc-cn__librarian-search');
    const librarianStatus = root.querySelector('[data-cn-librarian-status]');
    const librarianSummary = root.querySelector('[data-cn-librarian-summary]');
    const librarianResults = root.querySelector('[data-cn-librarian-results]');
    const guidanceEndpoint = root.dataset.guidanceEndpoint;

    const setMode = (mode) => {
      modes.forEach((button) => {
        const active = button.dataset.cnMode === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach((panel) => { panel.hidden = panel.dataset.cnPanel !== mode; });
    };
    modes.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.cnMode)));

    const renderResearchGuidance = (payload) => {
      const intents = Array.isArray(payload.detected_intents) ? payload.detected_intents : [];
      const roles = Array.isArray(payload.source_plan?.roles) ? payload.source_plan.roles : [];
      const questions = Array.isArray(payload.research_question_frame) ? payload.research_question_frame : [];
      const gaps = Array.isArray(payload.evidence_gaps) ? payload.evidence_gaps : [];
      const handoffs = Array.isArray(payload.handoffs) ? payload.handoffs : [];
      const matched = payload.matched_context || {};
      const freshness = payload.freshness_review || {};
      const contract = payload.answer_contract || {};

      librarianSummary.innerHTML = `
        <div class="sc-cn__stat"><strong>${esc(intents.length)}</strong><span>Detected intents</span></div>
        <div class="sc-cn__stat"><strong>${esc(matched.measure_count || 0)}</strong><span>Measures</span></div>
        <div class="sc-cn__stat"><strong>${esc(matched.methodology_count || 0)}</strong><span>Methods</span></div>
        <div class="sc-cn__stat"><strong>${esc(matched.evidence_count || 0)}</strong><span>Evidence seeds</span></div>
        <div class="sc-cn__stat"><strong>${esc(payload.routing_context_coverage || '—')}</strong><span>Routing context</span></div>`;

      const intentCards = intents.map((item) => `
        <article class="sc-cn__intent-card">
          <div class="sc-cn__intent-score">${esc(item.relative_match ?? '')}</div>
          <h3>${esc(item.label)}</h3>
          <p>${esc(item.purpose)}</p>
          <div class="sc-cn__chips">${chips(item.caution_flags || [], 'sc-cn__chip--risk')}</div>
        </article>`).join('');
      const roleCards = roles.map((item) => `
        <article class="sc-cn__source-card">
          <h4>${esc(item.label)}</h4><p>${esc(item.purpose)}</p>
          <div><strong>Typical records</strong><div class="sc-cn__chips">${chips(item.record_types || [])}</div></div>
          <div><strong>Minimum metadata</strong><ul class="sc-cn__evidence">${(item.minimum_requirements || []).map(v => `<li>${esc(v)}</li>`).join('')}</ul></div>
          ${item.freshness_sensitive ? '<span class="sc-cn__freshness">Current-version check required</span>' : ''}
        </article>`).join('');
      const gapCards = gaps.length ? gaps.map((item) => `
        <article class="sc-cn__gap sc-cn__gap--${esc(item.severity || 'medium')}"><strong>${esc(item.code)}</strong><p>${esc(item.message)}</p></article>`).join('') : '<p class="sc-cn__empty">No deterministic routing gaps were flagged. Source-specific review is still required.</p>';
      const handoffCards = handoffs.map((item) => `<article class="sc-cn__handoff-card"><strong>${esc(item.target)}</strong><p>${esc(item.purpose)}</p></article>`).join('');
      const oldRecords = Array.isArray(freshness.older_matched_records) ? freshness.older_matched_records : [];
      const freshnessText = freshness.current_source_check_required
        ? `<div class="sc-cn__freshness-alert"><strong>Freshness review required.</strong> ${oldRecords.length} matched registry source(s) predate ${esc(freshness.freshness_cutoff_year)}. Current policy, inventory, program, market, or dataset versions must be checked before present-tense claims.</div>`
        : '<div class="sc-cn__freshness-ok">No time-sensitive stale-source flag was triggered by this routing packet.</div>';

      librarianResults.innerHTML = `
        <section class="sc-cn__graph-block"><h3>Detected Research Intents</h3><div class="sc-cn__intent-grid">${intentCards}</div></section>
        <section class="sc-cn__graph-block"><h3>Research Question Frame</h3><ol class="sc-cn__question-frame">${questions.map(q => `<li>${esc(q)}</li>`).join('')}</ol></section>
        <section class="sc-cn__graph-block"><h3>Source Plan</h3><div class="sc-cn__source-grid">${roleCards}</div></section>
        <section class="sc-cn__graph-block"><h3>Evidence & Scope Gaps</h3><div class="sc-cn__gap-grid">${gapCards}</div></section>
        <section class="sc-cn__graph-block"><h3>Freshness Review</h3>${freshnessText}</section>
        <section class="sc-cn__graph-block"><h3>Governed Handoffs</h3><div class="sc-cn__handoff-grid">${handoffCards}</div></section>
        <section class="sc-cn__graph-block"><h3>Answer Contract</h3><div class="sc-cn__contract-grid"><div><strong>May do</strong><ul class="sc-cn__evidence">${(contract.may || []).map(v => `<li>${esc(v)}</li>`).join('')}</ul></div><div><strong>May not do</strong><ul class="sc-cn__evidence">${(contract.may_not || []).map(v => `<li>${esc(v)}</li>`).join('')}</ul></div></div></section>`;
    };

    const loadResearchGuidance = async () => {
      if (!librarianForm || !librarianStatus || !librarianSummary || !librarianResults || !guidanceEndpoint) return;
      const q = String(new FormData(librarianForm).get('q') || '').trim();
      if (!q) {
        librarianStatus.textContent = 'Enter an AFOLU, carbon farming, MRV, inventory, nature-based solutions, or carbon-project research question.';
        librarianSummary.innerHTML = '';
        librarianResults.innerHTML = '';
        return;
      }
      const url = new URL(guidanceEndpoint, window.location.origin);
      url.searchParams.set('q', q);
      url.searchParams.set('limit', '12');
      librarianStatus.textContent = 'Building deterministic AFOLU research routing packet…';
      librarianSummary.innerHTML = '';
      librarianResults.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        const labels = (payload.detected_intents || []).slice(0, 3).map(i => i.label).join(' · ');
        librarianStatus.textContent = `${labels || 'AFOLU research routing'} · Carbon & Nature v${payload.subsystem_version || '0.5.0'}`;
        renderResearchGuidance(payload);
      } catch (error) {
        librarianStatus.textContent = `AFOLU Research Librarian Intelligence unavailable: ${error.message || error}`;
      }
    };

    const renderProjectModel = (payload, templatePayload) => {
      const objectTypes = Array.isArray(payload.object_types) ? payload.object_types : [];
      const eventTypes = Array.isArray(payload.provenance_event_types) ? payload.provenance_event_types : [];
      const linkTypes = Array.isArray(payload.link_types) ? payload.link_types : [];
      projectSummary.innerHTML = `
        <div class="sc-cn__stat"><strong>${esc(objectTypes.length)}</strong><span>Object types</span></div>
        <div class="sc-cn__stat"><strong>${esc(eventTypes.length)}</strong><span>Provenance events</span></div>
        <div class="sc-cn__stat"><strong>${esc(linkTypes.length)}</strong><span>Link types</span></div>
        <div class="sc-cn__stat"><strong>SHA-256</strong><span>Integrity fingerprints</span></div>`;

      const objectCards = objectTypes.map((item) => `
        <article class="sc-cn__object-card">
          <div class="sc-cn__object-type">${esc(item.key)}</div>
          <h3>${esc(item.label)}</h3>
          <p>${esc(item.purpose)}</p>
          <div class="sc-cn__object-fields"><strong>Required payload</strong><div class="sc-cn__chips">${chips(item.required_payload_fields || [])}</div></div>
          <details><summary>Optional fields &amp; provenance</summary>
            <div class="sc-cn__detail-grid">
              <div><strong>Optional payload</strong><div class="sc-cn__chips">${chips(item.optional_payload_fields || [])}</div></div>
              <div><strong>Allowed parents</strong><div class="sc-cn__chips">${chips(item.allowed_parent_types || ['project root'])}</div></div>
            </div>
            <ul class="sc-cn__evidence">${(item.provenance_expectations || []).map((text) => `<li>${esc(text)}</li>`).join('')}</ul>
          </details>
        </article>`).join('');

      const eventRows = eventTypes.map((item) => `
        <tr><td><code>${esc(item.key)}</code></td><td><strong>${esc(item.label)}</strong><br>${esc(item.purpose)}</td><td>${esc(item.chain_semantics)}</td></tr>`).join('');
      const linkRows = linkTypes.map((item) => `
        <tr><td><code>${esc(item.key)}</code></td><td>${esc((item.subject_types || []).join(', '))}</td><td>${esc((item.object_types || []).join(', '))}</td><td>${esc(item.purpose)}</td></tr>`).join('');
      const envelope = payload.object_envelope || {};
      const packet = payload.project_packet || {};
      const template = templatePayload?.template || {};

      projectResults.innerHTML = `
        <section class="sc-cn__graph-block">
          <h3>Project Object Types</h3>
          <div class="sc-cn__object-grid">${objectCards}</div>
        </section>
        <section class="sc-cn__graph-block">
          <h3>Common Versioned Object Envelope</h3>
          <div class="sc-cn__contract-grid">
            <div><strong>Required envelope fields</strong><div class="sc-cn__chips">${chips(envelope.required_fields || [])}</div></div>
            <div><strong>Optional envelope fields</strong><div class="sc-cn__chips">${chips(envelope.optional_fields || [])}</div></div>
          </div>
          <ul class="sc-cn__evidence">${(envelope.identity_rules || []).map((text) => `<li>${esc(text)}</li>`).join('')}</ul>
        </section>
        <section class="sc-cn__graph-block">
          <h3>Provenance Event Contract</h3>
          <div class="sc-cn__edge-wrap"><table class="sc-cn__edges"><thead><tr><th>Event</th><th>Purpose</th><th>Chain semantics</th></tr></thead><tbody>${eventRows}</tbody></table></div>
        </section>
        <section class="sc-cn__graph-block">
          <h3>Explicit Project Links</h3>
          <div class="sc-cn__edge-wrap"><table class="sc-cn__edges"><thead><tr><th>Predicate</th><th>Subject types</th><th>Object types</th><th>Meaning</th></tr></thead><tbody>${linkRows}</tbody></table></div>
        </section>
        <section class="sc-cn__graph-block">
          <h3>Project Packet Contract</h3>
          <p><code>${esc(packet.schema || '')}</code> · ${esc(packet.validation || '')}</p>
          <div class="sc-cn__contract-grid">
            <div><strong>Packet fields</strong><div class="sc-cn__chips">${chips(packet.required_fields || [])}</div></div>
            <div><strong>Template shape</strong><div class="sc-cn__chips">${chips(Object.keys(template))}</div></div>
          </div>
          <div class="sc-cn__guardrail"><strong>Fingerprint ≠ signature.</strong> Deterministic fingerprints detect content changes and chain breaks; they do not prove actor identity, certify a project, or establish carbon-credit entitlement.</div>
        </section>`;
    };

    const loadProjectModel = async () => {
      if (!projectStatus || !projectSummary || !projectResults || !objectModelEndpoint) return;
      projectStatus.textContent = 'Loading governed project object and provenance model…';
      projectSummary.innerHTML = '';
      projectResults.innerHTML = '';
      try {
        const requests = [fetch(objectModelEndpoint, {headers:{'Accept':'application/json'}, credentials:'same-origin'})];
        if (packetTemplateEndpoint) requests.push(fetch(packetTemplateEndpoint, {headers:{'Accept':'application/json'}, credentials:'same-origin'}));
        const responses = await Promise.all(requests);
        const payload = await responses[0].json();
        if (!responses[0].ok) throw new Error(payload?.message || payload?.detail || `HTTP ${responses[0].status}`);
        let templatePayload = null;
        if (responses[1]) {
          templatePayload = await responses[1].json();
          if (!responses[1].ok) templatePayload = null;
        }
        projectStatus.textContent = `${payload.object_types?.length || 0} object types · ${payload.provenance_event_types?.length || 0} provenance events · Carbon & Nature v${payload.subsystem_version || '0.4.0'}`;
        renderProjectModel(payload, templatePayload);
      } catch (error) {
        projectStatus.textContent = `Carbon Project Object Model unavailable: ${error.message || error}`;
      }
    };

    const renderMeasures = (payload) => {
      const measures = Array.isArray(payload.measures) ? payload.measures : [];
      if (!measures.length) { measureResults.innerHTML = '<p class="sc-cn__empty">No measure profiles match those filters.</p>'; return; }
      measureResults.innerHTML = measures.map((item) => `
        <article class="sc-cn__card">
          <div class="sc-cn__meta">${esc(item.measure_family)} · ${esc(item.primary_domain)}</div>
          <h3>${esc(item.label)}</h3><p>${esc(item.description)}</p>
          <div class="sc-cn__matrix">
            <div><strong>Systems</strong><div class="sc-cn__chips">${chips(item.applicable_systems)}</div></div>
            <div><strong>Carbon pools</strong><div class="sc-cn__chips">${chips(item.target_carbon_pools)}</div></div>
            <div><strong>GHGs</strong><div class="sc-cn__chips">${chips(item.relevant_gases)}</div></div>
            <div><strong>Outcome types</strong><div class="sc-cn__chips">${chips(item.outcome_types)}</div></div>
          </div>
          <details><summary>MRV, integrity &amp; evidence context</summary>
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
      for (const [key, value] of data.entries()) { const clean = value.toString().trim(); if (clean) url.searchParams.set(key, clean); }
      url.searchParams.set('limit', '50');
      measureStatus.textContent = 'Loading governed measure profiles…'; measureResults.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        measureStatus.textContent = `${payload.count || 0} measure profiles · Carbon & Nature v${payload.subsystem_version || '0.4.0'}`;
        renderMeasures(payload);
      } catch (error) { measureStatus.textContent = `Carbon & Nature measure registry unavailable: ${error.message || error}`; }
    };

    const renderGraph = (payload) => {
      const nodes = Array.isArray(payload.nodes) ? payload.nodes : [];
      const edges = Array.isArray(payload.edges) ? payload.edges : [];
      const counts = nodes.reduce((acc, node) => { const type = node.node_type || 'other'; acc[type] = (acc[type] || 0) + 1; return acc; }, {});
      graphSummary.innerHTML = `<div class="sc-cn__stat"><strong>${esc(payload.node_count || nodes.length)}</strong><span>Nodes</span></div><div class="sc-cn__stat"><strong>${esc(payload.edge_count || edges.length)}</strong><span>Edges</span></div>${Object.entries(counts).map(([key, value]) => `<div class="sc-cn__stat"><strong>${esc(value)}</strong><span>${esc(key)}</span></div>`).join('')}`;
      if (!nodes.length && !edges.length) { graphResults.innerHTML = '<p class="sc-cn__empty">No graph relationships match that node or type.</p>'; return; }
      const nodeCards = nodes.slice(0, 60).map((node) => {
        const record = node.record || {}; let description = record.definition || record.description || record.purpose || record.scope || ''; let detail = '';
        if (node.node_type === 'methodology') detail = `<div class="sc-cn__chips">${chips(record.target_outcomes || [])}</div>`;
        else if (node.node_type === 'evidence') detail = `<div class="sc-cn__meta">${esc(record.record_type || '')} · ${esc(record.authority_class || '')}</div>`;
        else if (node.node_type === 'measure') detail = `<div class="sc-cn__meta">${esc(record.measure_family || '')}</div>`;
        else if (node.node_type === 'concept') detail = `<div class="sc-cn__meta">${esc(record.concept_type || '')} · ${esc(record.domain || '')}</div>`;
        return `<article class="sc-cn__node sc-cn__node--${esc(node.node_type)}"><div class="sc-cn__node-type">${esc(node.node_type)}</div><h3>${esc(labelForNode(node))}</h3><code>${esc(node.key)}</code>${detail}${description ? `<p>${esc(description)}</p>` : ''}</article>`;
      }).join('');
      const edgeRows = edges.slice(0, 160).map((edge) => `<tr><td><span class="sc-cn__edge-type">${esc(edge.subject_type)}</span><code>${esc(edge.subject_key)}</code></td><td><strong>${esc(edge.predicate)}</strong></td><td><span class="sc-cn__edge-type">${esc(edge.object_type)}</span><code>${esc(edge.object_key)}</code></td></tr>`).join('');
      graphResults.innerHTML = `<section class="sc-cn__graph-block"><h3>Graph Nodes</h3><div class="sc-cn__nodes">${nodeCards}</div></section><section class="sc-cn__graph-block"><h3>Explicit Relationships</h3><div class="sc-cn__edge-wrap"><table class="sc-cn__edges"><thead><tr><th>Subject</th><th>Relationship</th><th>Object</th></tr></thead><tbody>${edgeRows}</tbody></table></div></section>`;
    };

    const loadGraph = async () => {
      if (!graphForm || !graphStatus || !graphSummary || !graphResults || !graphEndpoint) return;
      const data = new FormData(graphForm); const url = new URL(graphEndpoint, window.location.origin);
      for (const [key, value] of data.entries()) { const clean = value.toString().trim(); if (clean) url.searchParams.set(key, clean); }
      url.searchParams.set('limit', '200');
      graphStatus.textContent = 'Loading governed evidence-methodology graph…'; graphSummary.innerHTML = ''; graphResults.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'}); const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        graphStatus.textContent = `${payload.node_count || 0} nodes · ${payload.edge_count || 0} explicit relationships · Carbon & Nature v${payload.subsystem_version || '0.4.0'}`;
        renderGraph(payload);
      } catch (error) { graphStatus.textContent = `Carbon & Nature evidence graph unavailable: ${error.message || error}`; }
    };

    if (librarianForm) { librarianForm.addEventListener('submit', (event) => { event.preventDefault(); loadResearchGuidance(); }); librarianForm.addEventListener('reset', () => { window.setTimeout(loadResearchGuidance, 0); }); }
    if (measureForm) { measureForm.addEventListener('submit', (event) => { event.preventDefault(); loadMeasures(); }); measureForm.addEventListener('reset', () => { window.setTimeout(loadMeasures, 0); }); }
    if (graphForm) { graphForm.addEventListener('submit', (event) => { event.preventDefault(); loadGraph(); }); graphForm.addEventListener('reset', () => { window.setTimeout(loadGraph, 0); }); }

    setMode('librarian');
    if (librarianStatus) librarianStatus.textContent = 'Enter a research question to build an AFOLU domain research plan.';
    loadProjectModel();
    loadGraph();
    loadMeasures();
  });
})();
