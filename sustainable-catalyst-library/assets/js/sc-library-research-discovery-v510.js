(() => {
  'use strict';
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const render = (item) => {
    const prov = item.provenance || {};
    const origin = item.origin === 'federated' ? 'Federated metadata' : 'Sustainable Catalyst';
    const lineage = item.origin === 'federated' && prov.node_id ? ` · ${esc(prov.node_id)}` : '';
    const context = item.context_url ? `<a href="${esc(item.context_url)}" target="_blank" rel="noopener noreferrer">Research context ↗</a>` : '';
    return `<article class="sc-research-discovery__result"><p class="sc-research-discovery__meta">${esc(origin)} · ${esc(item.type_label || item.type)}${lineage}</p><h4><a href="${esc(item.canonical_url)}" target="_blank" rel="noopener noreferrer">${esc(item.title || 'Untitled')}</a></h4>${item.summary ? `<p>${esc(item.summary)}</p>` : ''}<footer><span>Lexical score ${esc(item.score)}</span>${context}</footer></article>`;
  };
  document.querySelectorAll('[data-sc-research-discovery]').forEach((root) => {
    const form = root.querySelector('form'); const status = root.querySelector('[role="status"]'); const results = root.querySelector('[data-results]');
    if (!form || !status || !results) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault(); const data = new FormData(form); const q = String(data.get('q') || '').trim();
      if (q.length < 2) { status.textContent = 'Enter at least two characters.'; return; }
      const url = new URL(root.dataset.endpoint, window.location.origin); url.searchParams.set('q', q); url.searchParams.set('per_page', '20');
      const origin = String(data.get('origin') || ''); if (origin) url.searchParams.set('origin', origin);
      status.textContent = 'Searching public research…'; results.innerHTML = '';
      const controller = new AbortController(); const timer = window.setTimeout(() => controller.abort(), 12000);
      try {
        const response = await fetch(url.toString(), {credentials:'omit', signal:controller.signal, headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error(response.status === 429 ? 'Search is temporarily rate limited.' : 'Public research search is temporarily unavailable.');
        const payload = await response.json(); const items = Array.isArray(payload.items) ? payload.items : [];
        status.textContent = `${payload.total || 0} public result${payload.total === 1 ? '' : 's'} found. Ranking is deterministic lexical matching.`;
        results.innerHTML = items.length ? items.map(render).join('') : '<p class="sc-research-discovery__empty">No public results matched this search.</p>';
      } catch (error) { status.textContent = error.name === 'AbortError' ? 'Search timed out before a public response was available.' : error.message; }
      finally { window.clearTimeout(timer); }
    });
  });
})();
