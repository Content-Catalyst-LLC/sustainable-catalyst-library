(() => {
  const parse = (value, fallback) => {
    const text = String(value || '').trim();
    if (!text) return fallback;
    return JSON.parse(text);
  };
  document.querySelectorAll('[data-sc-living-evidence-root]').forEach(root => {
    const btn = root.querySelector('[data-sc-living-run]');
    const status = root.querySelector('[data-sc-living-status]');
    const metrics = root.querySelector('[data-sc-living-metrics]');
    const out = root.querySelector('[data-sc-living-output]');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      status.textContent = 'Comparing immutable review snapshots…';
      metrics.textContent = '';
      try {
        const baseline_review = parse(root.querySelector('[data-sc-living-baseline]')?.value, {});
        const current_review = parse(root.querySelector('[data-sc-living-current]')?.value, {});
        const events = parse(root.querySelector('[data-sc-living-events]')?.value, []);
        const res = await fetch(root.dataset.endpoint, {
          method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
          body: JSON.stringify({baseline_review,current_review,events})
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data?.detail || data?.error || 'Living evidence request failed');
        const m = data.metrics || {};
        status.textContent = `Living evidence comparison ${data.living_evidence_id || ''} created.`;
        metrics.innerHTML = [
          ['Update candidates', m.candidate_count ?? 0],
          ['New records', m.new_record_count ?? 0],
          ['Shared records', m.shared_record_count ?? 0],
          ['Explicit events', m.explicit_change_event_count ?? 0]
        ].map(([label,value]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        status.textContent = 'Living evidence comparison unavailable.';
        out.textContent = String(err);
      }
    });
  });
})();
