(() => {
  const renderCards = (root, data) => {
    const cards = root.querySelector('[data-sc-runtime-cards]');
    if (!cards) return;
    cards.innerHTML = '';
    (data?.runtimes || []).forEach(runtime => {
      const el = document.createElement('article');
      el.className = 'sc-unified-runtime__card';
      const state = runtime.available ? 'Available' : 'Unavailable';
      el.innerHTML = `<strong>${runtime.engine || 'runtime'}</strong><span>${runtime.runtime_version || 'unknown'}</span><em>${state}</em><small>${(runtime.capabilities || []).join(' · ')}</small>`;
      cards.appendChild(el);
    });
  };
  document.querySelectorAll('[data-sc-unified-runtime-root]').forEach(root => {
    const statusEl = root.querySelector('[data-sc-runtime-status]');
    const out = root.querySelector('[data-sc-runtime-output]');
    const load = async () => {
      statusEl.textContent = 'Loading unified runtime contract…';
      try {
        const res = await fetch(root.dataset.statusEndpoint, {credentials:'same-origin'});
        const data = await res.json();
        if (!res.ok) throw new Error(data?.message || data?.error || 'Runtime status failed.');
        renderCards(root, data);
        statusEl.textContent = `Runtime contract ${data.schema || ''} · backend ${data.backend_version || ''}`;
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        statusEl.textContent = 'Runtime status unavailable.';
        out.textContent = String(err);
      }
    };
    root.querySelector('[data-sc-runtime-refresh]')?.addEventListener('click', load);
    root.querySelector('[data-sc-runtime-resolve]')?.addEventListener('click', async () => {
      statusEl.textContent = 'Resolving runtime route…';
      try {
        const body = {
          workload: root.querySelector('[data-sc-runtime-workload]')?.value || 'research-corpus-build',
          runtime: root.querySelector('[data-sc-runtime-preference]')?.value || 'auto',
          allow_fallback: !!root.querySelector('[data-sc-runtime-fallback]')?.checked
        };
        const res = await fetch(root.dataset.resolveEndpoint, {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body:JSON.stringify(body)});
        const data = await res.json();
        if (!res.ok) throw new Error(data?.message || data?.detail || 'Runtime resolution failed.');
        statusEl.textContent = `Selected ${data.selected_runtime || ''} for ${data.workload || ''}${data.fallback_used ? ' using explicit fallback' : ''}.`;
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        statusEl.textContent = 'Runtime resolution failed.';
        out.textContent = String(err);
      }
    });
    load();
  });
})();
