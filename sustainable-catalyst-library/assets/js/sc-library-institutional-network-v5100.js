(() => {
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const label = (v) => String(v || '').replaceAll('-', ' ');
  const shortHash = (v) => String(v || '').slice(0, 20);

  const renderRecord = (record) => {
    const authors = Array.isArray(record.authors) ? record.authors.slice(0, 4).join(' · ') : '';
    const subjects = Array.isArray(record.subjects) ? record.subjects.slice(0, 4).join(' · ') : '';
    const sources = Array.isArray(record.source_keys) ? record.source_keys.join(' · ') : record.source_key || '';
    const license = record.license || {};
    const rights = license.name || license.url || (license.reuse_requires_review ? 'Rights review required' : 'Rights not supplied');
    const meta = [record.record_type, record.doi ? `DOI ${record.doi}` : record.persistent_id, authors].filter(Boolean).join(' · ');
    return `<article class="sc-irn__card">
      <div class="sc-irn__cardtop"><span>${esc(record.institution || 'Institutional record')}</span><small>${esc(sources)}</small></div>
      <h3>${esc(record.title || 'Untitled research record')}</h3>
      ${meta ? `<p class="sc-irn__meta">${esc(meta)}</p>` : ''}
      ${record.description ? `<p>${esc(record.description)}</p>` : ''}
      ${subjects ? `<p class="sc-irn__subjects">${esc(subjects)}</p>` : ''}
      <div class="sc-irn__rights"><strong>Rights:</strong> ${esc(rights)}</div>
      <div class="sc-irn__links">${record.source_url ? `<a href="${esc(record.source_url)}" target="_blank" rel="noopener noreferrer">Open source record</a>` : ''}</div>
    </article>`;
  };

  const renderSources = (target, statuses) => {
    const rows = Object.entries(statuses || {}).map(([key, row]) => {
      const limitations = Array.isArray(row.search_limitations) ? row.search_limitations : [];
      const state = row.state || 'unknown';
      const count = Number(row.record_count || 0);
      return `<article class="sc-irn__source-state sc-irn__source-state--${esc(state)}">
        <strong>${esc(key)}</strong><span>${esc(state)} · ${count} record${count === 1 ? '' : 's'} · ${esc(label(row.search_mode || 'unknown'))}</span>
        ${limitations.length ? `<small>${limitations.map(esc).join(' ')}</small>` : ''}
      </article>`;
    }).join('');
    target.innerHTML = rows;
    target.hidden = !rows;
  };

  const renderGraphSummary = (target, data) => {
    const graph = data.graph || {};
    const nodes = Array.isArray(graph.nodes) ? graph.nodes : [];
    const counts = nodes.reduce((acc, node) => { acc[node.type] = (acc[node.type] || 0) + 1; return acc; }, {});
    const parts = ['institution','repository','research-record','license'].map(type => `<div><strong>${esc(counts[type] || 0)}</strong><span>${esc(label(type))}</span></div>`).join('');
    target.innerHTML = `<h3>Institutional graph</h3><div class="sc-irn__graph-grid">${parts}</div><p><strong>Graph fingerprint:</strong> <code>${esc(shortHash(graph.content_fingerprint))}</code></p><p>Relationships are explicit query/repository/license relationships. Author identity is not inferred across repositories.</p>`;
    target.hidden = false;
  };

  document.querySelectorAll('[data-sc-institutional-research-network]').forEach(root => {
    const form = root.querySelector('.sc-irn__search');
    const status = root.querySelector('.sc-irn__status');
    const metrics = root.querySelector('.sc-irn__metrics');
    const sourcePanel = root.querySelector('.sc-irn__sources');
    const graphPanel = root.querySelector('.sc-irn__graph');
    const results = root.querySelector('.sc-irn__results');

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      const q = data.get('q')?.toString().trim();
      if (!q) return;
      const sources = data.getAll('sources').map(String).filter(Boolean);
      status.textContent = 'Searching institutional repositories with provenance preserved…';
      metrics.hidden = true; sourcePanel.hidden = true; graphPanel.hidden = true; results.innerHTML = '';
      try {
        const url = new URL(root.dataset.graphEndpoint, window.location.origin);
        url.searchParams.set('q', q);
        url.searchParams.set('limit_per_source', '8');
        if (sources.length) url.searchParams.set('sources', sources.join(','));
        const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.detail || 'Institutional research network request failed.');
        const records = Array.isArray(payload.records) ? payload.records : [];
        const rep = payload.reproducibility || {};
        const duplicateCount = records.reduce((n, r) => n + Math.max(0, Number(r.duplicate_observation_count || 1) - 1), 0);
        metrics.innerHTML = `<div><strong>${esc(records.length)}</strong><span>canonical records</span></div><div><strong>${esc(duplicateCount)}</strong><span>duplicate observations consolidated</span></div><div><strong>${esc(Object.keys(payload.source_status || {}).length)}</strong><span>sources checked</span></div><div><strong>${esc(shortHash(rep.content_fingerprint))}</strong><span>content fingerprint</span></div>`;
        metrics.hidden = false;
        renderSources(sourcePanel, payload.source_status || {});
        renderGraphSummary(graphPanel, payload);
        results.innerHTML = records.length ? records.map(renderRecord).join('') : '<p class="sc-irn__empty">No matching records were returned within the bounded source searches.</p>';
        const errors = Array.isArray(payload.errors) ? payload.errors.length : 0;
        status.textContent = errors ? `Search complete with ${errors} contained source failure${errors === 1 ? '' : 's'}.` : 'Institutional research search complete.';
      } catch (error) {
        status.textContent = error?.message || 'Institutional Research Network unavailable.';
      }
    });
  });
})();
