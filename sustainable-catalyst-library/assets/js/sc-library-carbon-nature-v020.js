(() => {
  const roots = document.querySelectorAll('[data-sc-carbon-nature]');
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  const chips = (values, cls = '') => (Array.isArray(values) ? values : []).map((value) => `<span class="sc-cn__chip ${cls}">${esc(value)}</span>`).join('');

  roots.forEach((root) => {
    const form = root.querySelector('.sc-cn__measure-search');
    const status = root.querySelector('.sc-cn__status');
    const results = root.querySelector('.sc-cn__results');
    const endpoint = root.dataset.measuresEndpoint;
    if (!form || !status || !results || !endpoint) return;

    const render = (payload) => {
      const measures = Array.isArray(payload.measures) ? payload.measures : [];
      if (!measures.length) {
        results.innerHTML = '<p class="sc-cn__empty">No measure profiles match those filters.</p>';
        return;
      }
      results.innerHTML = measures.map((item) => `
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

    const load = async () => {
      const data = new FormData(form);
      const url = new URL(endpoint, window.location.origin);
      for (const [key, value] of data.entries()) {
        const clean = value.toString().trim();
        if (clean) url.searchParams.set(key, clean);
      }
      url.searchParams.set('limit', '50');
      status.textContent = 'Loading governed measure profiles…';
      results.innerHTML = '';
      try {
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        status.textContent = `${payload.count || 0} measure profiles · Carbon & Nature v${payload.subsystem_version || '0.2.0'}`;
        render(payload);
      } catch (error) {
        status.textContent = `Carbon & Nature registry unavailable: ${error.message || error}`;
      }
    };

    form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
    form.addEventListener('reset', () => { window.setTimeout(load, 0); });
    load();
  });
})();
