(() => {
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const label = (v) => String(v || '').replaceAll('-', ' ');
  const short = (v, n=52) => { const s=String(v||''); return s.length > n ? s.slice(0,n-1)+'…' : s; };
  const typeOrder = ['publication','clinical-trial','condition','intervention','outcome','terminology-concept','regulatory-record'];

  const renderGraph = (svg, graph) => {
    const all = graph?.nodes || [];
    const edges = graph?.edges || [];
    const question = all.find(n => n.type === 'research-question');
    const chosen = [question, ...typeOrder.flatMap(t => all.filter(n => n.type === t).slice(0,4))].filter(Boolean).slice(0,25);
    const byId = new Map(chosen.map(n => [n.id,n]));
    const positions = new Map();
    if (question) positions.set(question.id, {x:500,y:280});
    const outer = chosen.filter(n => n !== question);
    outer.forEach((n,i) => {
      const angle = (-Math.PI/2) + (Math.PI*2*i/Math.max(1,outer.length));
      positions.set(n.id, {x:500 + Math.cos(angle)*390, y:280 + Math.sin(angle)*205});
    });
    const lineHtml = edges.filter(e => byId.has(e.source) && byId.has(e.target)).map(e => {
      const a=positions.get(e.source), b=positions.get(e.target); if(!a||!b) return '';
      return `<line x1="${a.x}" y1="${a.y}" x2="${b.x}" y2="${b.y}" class="sc-beg__edge"><title>${esc(label(e.type))}</title></line>`;
    }).join('');
    const nodeHtml = chosen.map(n => {
      const p=positions.get(n.id); if(!p) return '';
      const cls = n.type === 'research-question' ? ' sc-beg__node--question' : '';
      return `<g class="sc-beg__node${cls}" transform="translate(${p.x},${p.y})"><circle r="${n.type==='research-question'?54:34}"></circle><text text-anchor="middle" y="4">${esc(short(n.label, n.type==='research-question'?24:18))}</text><title>${esc(n.type)}: ${esc(n.label)}</title></g>`;
    }).join('');
    svg.innerHTML = `<g>${lineHtml}</g><g>${nodeHtml}</g>`;
  };

  const recordCard = (n) => {
    if (!['publication','clinical-trial','regulatory-record','terminology-concept'].includes(n.type)) return '';
    const a=n.attributes||{};
    const meta=[label(n.type), n.identifier, a.evidence_class, a.overall_status].filter(Boolean).join(' · ');
    return `<article class="sc-beg__card"><div class="sc-beg__cardtop"><span>${esc(label(n.type))}</span><small>${esc(n.source_key||'')}</small></div><h3>${esc(n.label||n.identifier||'Evidence node')}</h3>${meta?`<p>${esc(meta)}</p>`:''}${n.url?`<a href="${esc(n.url)}" target="_blank" rel="noopener noreferrer">Open source record</a>`:''}</article>`;
  };

  document.querySelectorAll('[data-sc-biomedical-evidence-graph]').forEach(root => {
    const form=root.querySelector('.sc-beg__search');
    const status=root.querySelector('.sc-beg__status');
    const summary=root.querySelector('.sc-beg__summary');
    const canvas=root.querySelector('.sc-beg__canvas');
    const svg=root.querySelector('.sc-beg__svg');
    const reliability=root.querySelector('.sc-beg__reliability');
    const synthesis=root.querySelector('.sc-beg__synthesis');
    const records=root.querySelector('.sc-beg__records');
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const q=new FormData(form).get('q')?.toString().trim();
      if(!q) return;
      status.textContent='Building provenance-backed biomedical evidence graph…';
      summary.hidden=true; canvas.hidden=true; reliability.hidden=true; synthesis.hidden=true; records.innerHTML='';
      try {
        const url=new URL(root.dataset.buildEndpoint, window.location.origin); url.searchParams.set('q',q);
        const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});
        const data=await response.json(); if(!response.ok) throw new Error(data.detail||'Evidence graph request failed.');
        const g=data.graph||{}, s=data.synthesis||{}, c=s.coverage||{};
        summary.innerHTML=`<div><strong>${esc(c.node_count||0)}</strong><span>nodes</span></div><div><strong>${esc(c.edge_count||0)}</strong><span>relationships</span></div><div><strong>${esc(c.result_bearing_trial_count||0)}</strong><span>trials with results</span></div><div><strong>${esc(c.exact_trial_publication_link_count||0)}</strong><span>exact trial-publication links</span></div>`;
        summary.hidden=false;
        const r=data.reliability||{}, rep=data.reproducibility||{}, src=data.source_status||{};
        const sourceRows=Object.entries(src).map(([key,v])=>`<li><strong>${esc(key)}</strong><span>${esc(v?.state||'unknown')} · ${esc(v?.record_count||0)} records · ${esc(v?.error_count||0)} errors</span></li>`).join('');
        reliability.innerHTML=`<h3>Graph reliability & provenance</h3><div class="sc-beg__reliability-grid"><div><strong>${esc(r.integrity_state||'unknown')}</strong><span>integrity</span></div><div><strong>${esc(r.duplicate_observation_consolidation_count||0)}</strong><span>duplicate observations consolidated</span></div><div><strong>${esc(r.edge_missing_provenance_count||0)}</strong><span>edges missing provenance</span></div><div><strong>${esc(r.dangling_edge_count||0)}</strong><span>dangling edges</span></div></div><p><strong>Fingerprint:</strong> <code>${esc((rep.graph_content_fingerprint||g.content_fingerprint||'').slice(0,24))}</code></p>${sourceRows?`<ul class="sc-beg__sources">${sourceRows}</ul>`:''}<p>Canonical identity is exact-identifier or source-scoped. Title-only merging is disabled. Retrieval timestamps are excluded from graph fingerprints.</p>`;
        reliability.hidden=false;
        renderGraph(svg,g); canvas.hidden=false;
        const findings=(s.evidence_findings||[]).map(x=>`<li>${esc(x)}</li>`).join('');
        const gaps=(s.evidence_gaps||[]).map(x=>`<li>${esc(x)}</li>`).join('');
        synthesis.innerHTML=`<h3>Descriptive synthesis</h3>${findings?`<ul>${findings}</ul>`:''}${gaps?`<h4>Evidence gaps / review limits</h4><ul>${gaps}</ul>`:''}<p><strong>Human review required.</strong> No pooled effect, formal certainty grade, causal relationship, treatment ranking, or clinical recommendation was generated.</p>`;
        synthesis.hidden=false;
        records.innerHTML=(g.nodes||[]).map(recordCard).filter(Boolean).join('');
        status.textContent=data.errors?.length?`Graph complete with ${data.errors.length} contained source error(s).`:'Evidence graph complete.';
      } catch(error) { status.textContent=error?.message||'Evidence graph unavailable.'; }
    });
  });
})();
