(() => {
  'use strict';
  const roots = document.querySelectorAll('[data-sc-private-knowledge]');
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const post = async (root, url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json','X-WP-Nonce':root.dataset.restNonce || ''},
      body: JSON.stringify(body || {})
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data?.message || `Request failed (${response.status})`);
    return data;
  };
  const renderRecord = (row) => {
    const meta = [row.object_type, row.department, row.project_key, `rev ${row.revision || 1}`].filter(Boolean).map(esc).join(' · ');
    return `<article class="sc-pok__card" data-record-id="${esc(row.record_id)}"><div class="sc-pok__card-top"><span>${esc(row.access_level || 'organization')}</span><span>${esc(row.original_format || '')}</span></div><h3>${esc(row.title)}</h3><p class="sc-pok__meta">${meta}</p><p>${esc(row.abstract || 'No abstract available.')}</p><div class="sc-pok__actions"><button type="button" data-action="open">Open record</button><button type="button" data-action="versions">Versions</button><button type="button" data-action="research-librarian">Research Librarian</button><button type="button" data-action="workspace">Workspace</button><button type="button" data-action="lab">Lab</button></div></article>`;
  };
  roots.forEach((root) => {
    const form = root.querySelector('.sc-pok__search');
    if (!form) return;
    const status = root.querySelector('.sc-pok__status');
    const results = root.querySelector('.sc-pok__results');
    const drawer = root.querySelector('.sc-pok__drawer');
    const showDrawer = (html) => { drawer.innerHTML = html; drawer.hidden = false; drawer.scrollIntoView({behavior:'smooth', block:'nearest'}); };
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const fd = new FormData(form);
      const body = {q:String(fd.get('q') || ''), object_type:String(fd.get('object_type') || '') || null, project_key:String(fd.get('project_key') || '') || null, department:String(fd.get('department') || '') || null, limit:20, offset:0};
      status.textContent = 'Searching private knowledge…'; results.innerHTML = ''; drawer.hidden = true;
      try {
        const data = await post(root, root.dataset.searchEndpoint, body);
        status.textContent = `${data.record_count || 0} private record${data.record_count === 1 ? '' : 's'} shown${Number.isFinite(data.total) ? ` · ${data.total} accessible` : ''}.`;
        results.innerHTML = (data.records || []).map(renderRecord).join('') || '<p class="sc-pok__empty">No accessible records matched this search.</p>';
      } catch (error) {
        status.textContent = error.message || 'Private knowledge search unavailable.';
      }
    });
    results.addEventListener('click', async (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const card = button.closest('[data-record-id]');
      const recordId = card?.dataset.recordId;
      if (!recordId) return;
      const action = button.dataset.action;
      button.disabled = true;
      try {
        if (action === 'open') {
          const data = await post(root, root.dataset.recordEndpoint, {record_id: recordId});
          const row = data.record || {};
          showDrawer(`<button class="sc-pok__close" type="button" data-close>Close</button><p class="sc-pok__kicker">Private record</p><h3>${esc(row.title)}</h3><p class="sc-pok__meta">${esc(row.object_type)} · revision ${esc(row.revision)} · ${esc(row.access_level)}</p><div class="sc-pok__body">${esc(row.body_text || row.abstract || 'No extracted text is stored.')}</div>`);
        } else if (action === 'versions') {
          const data = await post(root, root.dataset.versionsEndpoint, {record_id: recordId});
          const rows = (data.versions || []).map((v) => `<li><strong>Revision ${esc(v.revision)}</strong><br><code>${esc(v.content_hash)}</code><br>${esc(v.observed_at)}</li>`).join('');
          showDrawer(`<button class="sc-pok__close" type="button" data-close>Close</button><p class="sc-pok__kicker">Version lineage</p><h3>${esc(recordId)}</h3><ol class="sc-pok__versions">${rows || '<li>No version history available.</li>'}</ol>`);
        } else {
          const data = await post(root, root.dataset.handoffEndpoint, {record_id: recordId, target: action});
          const policy = data.policy || {};
          showDrawer(`<button class="sc-pok__close" type="button" data-close>Close</button><p class="sc-pok__kicker">Private handoff</p><h3>${esc(action)}</h3><p><strong>${data.eligible ? 'Eligible' : 'Not eligible'}</strong> · ${esc(policy.payload_mode || '')}</p><p>Organization scope and access scopes must remain attached to this record. This handoff does not publish the record.</p>`);
        }
      } catch (error) {
        showDrawer(`<button class="sc-pok__close" type="button" data-close>Close</button><p>${esc(error.message || 'Private knowledge request unavailable.')}</p>`);
      } finally { button.disabled = false; }
    });
    drawer.addEventListener('click', (event) => { if (event.target.closest('[data-close]')) drawer.hidden = true; });
  });
})();
