(() => {
  const lines = (value) => String(value || '').split(/\r?\n/).map(v => v.trim()).filter(Boolean);
  document.querySelectorAll('[data-sc-literature-review-root]').forEach(root => {
    const btn = root.querySelector('[data-sc-review-build]');
    const status = root.querySelector('[data-sc-review-status]');
    const out = root.querySelector('[data-sc-review-output]');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const title = root.querySelector('[data-sc-review-title]')?.value || 'Literature Review';
      const question = root.querySelector('[data-sc-review-question]')?.value || '';
      const inclusion = lines(root.querySelector('[data-sc-review-inclusion]')?.value);
      const exclusion = lines(root.querySelector('[data-sc-review-exclusion]')?.value);
      const query = root.querySelector('[data-sc-review-query]')?.value || '';
      const source = root.querySelector('[data-sc-review-source]')?.value || '';
      status.textContent = 'Building reproducible protocol snapshot…';
      try {
        const res = await fetch(root.dataset.buildEndpoint, {
          method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
          body: JSON.stringify({protocol:{title,research_question:question,inclusion_criteria:inclusion,exclusion_criteria:exclusion,search_strategies: query || source ? [{query,source}] : []},records:[],decisions:[],extractions:[]})
        });
        const data = await res.json();
        status.textContent = res.ok ? `Snapshot ${data.review_id || ''} created.` : 'Review build failed.';
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        status.textContent = 'Review backend unavailable.';
        out.textContent = String(err);
      }
    });
  });
})();
