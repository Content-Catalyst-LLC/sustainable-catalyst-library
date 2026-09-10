(() => {
  const roots = document.querySelectorAll('[data-sc-carbon-nature]');
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  roots.forEach((root) => {
    const form = root.querySelector('.sc-cn__search');
    const status = root.querySelector('.sc-cn__status');
    const results = root.querySelector('.sc-cn__results');
    if (!form || !status || !results) return;
    const endpoint = root.dataset.contextEndpoint;
    const render = (payload) => {
      const concepts = Array.isArray(payload.concepts) ? payload.concepts : [];
      if (!concepts.length) { results.innerHTML = '<p>No matching foundation concepts.</p>'; return; }
      results.innerHTML = concepts.map((item) => `<article class="sc-cn__card"><div class="sc-cn__meta">${esc(item.concept_type)} · ${esc(item.domain)}</div><h3>${esc(item.label)}</h3><p>${esc(item.definition)}</p></article>`).join('');
    };
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const q = new FormData(form).get('q')?.toString().trim() || '';
      if (!q) { status.textContent = 'Enter a Carbon & Nature research concept.'; return; }
      status.textContent = 'Resolving governed domain context…';
      results.innerHTML = '';
      try {
        const url = new URL(endpoint, window.location.origin); url.searchParams.set('q', q); url.searchParams.set('limit', '12');
        const response = await fetch(url.toString(), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || payload?.detail || `HTTP ${response.status}`);
        status.textContent = `${payload.concepts?.length || 0} governed concept matches · Carbon & Nature v${payload.subsystem_version || '0.1.0'}`;
        render(payload);
      } catch (error) { status.textContent = `Carbon & Nature context unavailable: ${error.message || error}`; }
    });
  });
})();