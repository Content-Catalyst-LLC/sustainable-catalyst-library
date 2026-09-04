(() => {
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const label = (v) => String(v || 'unclassified').replaceAll('-', ' ');
  const card = (row, kind) => {
    const p = row.evidence_profile || {};
    const d = p.study_design || {};
    const integrity = (p.integrity && p.integrity.signals) || [];
    const id = row.identifier || row.nct_id || '';
    const meta = kind === 'trial'
      ? [row.overall_status, (row.study_design || {}).phases?.join(', '), (row.study_design || {}).enrollment ? `n=${row.study_design.enrollment}` : ''].filter(Boolean)
      : [row.journal, row.published_at].filter(Boolean);
    return `<article class="sc-evidence-grading__card">
      <div class="sc-evidence-grading__cardtop"><span>${esc(d.label || label(d.family))}</span><small>${esc(id)}</small></div>
      <h3>${esc(row.title || row.official_title || id || 'Evidence record')}</h3>
      ${meta.length ? `<p class="sc-evidence-grading__meta">${meta.map(esc).join(' · ')}</p>` : ''}
      <dl><div><dt>Design family</dt><dd>${esc(label(d.family))}</dd></div><div><dt>Formal certainty</dt><dd>Not assessed</dd></div></dl>
      ${integrity.length ? `<p class="sc-evidence-grading__flag">Review: ${integrity.map(x => esc(label(x))).join(' · ')}</p>` : ''}
      ${row.source_url ? `<a href="${esc(row.source_url)}" target="_blank" rel="noopener noreferrer">Open source record</a>` : ''}
    </article>`;
  };

  document.querySelectorAll('[data-sc-evidence-grading]').forEach(root => {
    const form = root.querySelector('.sc-evidence-grading__search');
    const status = root.querySelector('.sc-evidence-grading__status');
    const summary = root.querySelector('.sc-evidence-grading__summary');
    const results = root.querySelector('.sc-evidence-grading__results');
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const q = new FormData(form).get('q')?.toString().trim();
      if (!q) return;
      status.textContent = 'Mapping study designs and evidence-review signals…';
      summary.hidden = true; results.innerHTML = '';
      try {
        const url = new URL(root.dataset.searchEndpoint, window.location.origin);
        url.searchParams.set('q', q);
        const response = await fetch(url, {headers:{'Accept':'application/json'}, cache:'no-store'});
        const data = await response.json();
        if (!response.ok) throw new Error(data.detail || 'Evidence map request failed.');
        const s = data.summary || {};
        const dist = Object.entries(s.design_distribution || {}).map(([k,v]) => `<span><b>${esc(v)}</b> ${esc(label(k))}</span>`).join('');
        summary.innerHTML = `<div><strong>${esc(s.record_count || 0)}</strong><span>records mapped</span></div><div><strong>${esc(s.literature_count || 0)}</strong><span>literature</span></div><div><strong>${esc(s.trial_count || 0)}</strong><span>trials</span></div><div><strong>${esc(s.integrity_review_flag_count || 0)}</strong><span>integrity flags</span></div>${dist ? `<p class="sc-evidence-grading__distribution">${dist}</p>` : ''}`;
        summary.hidden = false;
        const literature = data.literature || [], trials = data.trials || [];
        results.innerHTML = `${literature.length ? '<h3>Literature</h3>' + literature.map(r => card(r,'literature')).join('') : ''}${trials.length ? '<h3>Registered studies</h3>' + trials.map(r => card(r,'trial')).join('') : ''}`;
        if (!literature.length && !trials.length) results.innerHTML = '<p>No evidence records were available for this retrieval.</p>';
        status.textContent = data.errors?.length ? `Evidence map complete with ${data.errors.length} contained source error(s).` : 'Evidence map complete.';
      } catch (error) {
        status.textContent = error?.message || 'Evidence map unavailable.';
      }
    });
  });
})();
