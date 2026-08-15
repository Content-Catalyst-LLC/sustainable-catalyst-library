(() => {
  'use strict';
  const cfg = window.SCResearchLibrarianV2 || {};
  const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const post = async (path, body) => {
    const res = await fetch(`${String(cfg.restBase || '').replace(/\/$/,'')}/${path}`, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},body:JSON.stringify(body)});
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
    return data;
  };
  document.querySelectorAll('[data-sc-research-librarian-v2]').forEach((root) => {
    const form = root.querySelector('[data-sc-librarian-v2-form]');
    if (!form) return;
    const catalogNode = root.querySelector('[data-sc-librarian-v2-catalog]');
    let catalog = [];
    try { catalog = JSON.parse(catalogNode?.textContent || '[]'); } catch (_) {}
    const notice = root.querySelector('[data-sc-librarian-v2-notice]');
    const output = root.querySelector('[data-sc-librarian-v2-output]');
    const byId = (id) => catalog.find(p => Number(p.project_id) === Number(id));
    const fill = (select, items, valueKey, labelKey, emptyLabel) => {
      select.innerHTML = `<option value="${valueKey === 'project_id' ? '' : '0'}">${esc(emptyLabel)}</option>` + (items || []).map(i => `<option value="${esc(i[valueKey])}">${esc(i[labelKey])}</option>`).join('');
    };
    const sync = () => {
      const p = byId(form.elements.project_id.value);
      const bundles = p?.bundles || [], notebooks = p?.notebooks || [], matrices = p?.matrices || [];
      form.elements.bundle_id.innerHTML = `<option value="">Whole project</option>` + bundles.map(i => `<option value="${esc(i.bundle_id)}">${esc(i.title)} · ${Number(i.link_count || 0)} refs</option>`).join('');
      fill(form.elements.notebook_id, notebooks, 'notebook_id', 'title', 'No notebook selected');
      fill(form.elements.matrix_id, matrices, 'matrix_id', 'title', 'No matrix selected');
    };
    form.elements.project_id.addEventListener('change', sync);
    const showNotice = (msg, error=false) => { notice.hidden = !msg; notice.textContent = msg || ''; notice.classList.toggle('is-error', error); };
    const render = (packet) => {
      const c = packet.context || {}, p = c.project || {}, n = c.selected_notebook || null, m = c.selected_matrix || null, b = c.selected_bundle || null;
      output.innerHTML = `<div class="sc-librarian-v2__summary"><article><strong>${esc(p.title)}</strong><span>${Number(p.reference_count || 0)} project references · ${Number(p.bundle_count || 0)} bundles</span></article><article><strong>${b ? esc(b.title) : 'Whole project'}</strong><span>${b ? `${Number(b.reference_count || 0)} bundled references` : 'No bundle filter'}</span></article><article><strong>${n ? esc(n.title) : 'No notebook selected'}</strong><span>${n ? `${Number(n.note_count || 0)} notes · ${Number(n.annotation_count || 0)} annotations` : 'Private reading context not included'}</span></article><article><strong>${m ? esc(m.title) : 'No matrix selected'}</strong><span>${m ? `${Number(m.claim_count || 0)} claims · ${Number(m.evidence_link_count || 0)} evidence links` : 'Claim context not included'}</span></article></div>
      <section class="sc-librarian-v2__guidance"><h4>Project-aware guidance</h4>${(packet.guidance || []).map(g => `<article><small>${esc(g.kind).replace(/_/g,' ')}</small><h5>${esc(g.title)}</h5><p>${esc(g.reason)}</p><a href="${esc(g.target)}">Open relevant Library surface →</a></article>`).join('')}</section>
      <div class="sc-librarian-v2__handoff"><button type="button" data-sc-librarian-v2-continue>Continue in the Research Librarian</button><p>Only the question and up to ${Number((packet.orchestrator_handoff?.record_ids || []).length)} public Research Source IDs are handed off. Private project context remains in this view.</p></div>
      <details><summary>Private context and safety diagnostics</summary><pre>${esc(JSON.stringify({context_checksum:c.checksum_sha256,packet_checksum:packet.checksum_sha256,contract:packet.contract,orchestrator_handoff:packet.orchestrator_handoff},null,2))}</pre></details>`;
      output.dataset.packet = JSON.stringify(packet);
    };
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      showNotice('Building private project context…');
      const button = form.querySelector('button[type="submit"]'); button.disabled = true;
      try {
        const packet = await post('guidance', {project_id:Number(form.elements.project_id.value),bundle_id:form.elements.bundle_id.value,notebook_id:Number(form.elements.notebook_id.value || 0),matrix_id:Number(form.elements.matrix_id.value || 0),prompt:form.elements.prompt.value});
        render(packet); showNotice('');
      } catch (e) { showNotice(e.message || 'Could not build project-aware guidance.', true); }
      finally { button.disabled = false; }
    });
    root.addEventListener('click', (event) => {
      const button = event.target.closest('[data-sc-librarian-v2-continue]');
      if (!button) return;
      let packet = null; try { packet = JSON.parse(output.dataset.packet || 'null'); } catch (_) {}
      if (!packet) return;
      const h = packet.orchestrator_handoff || {};
      document.dispatchEvent(new CustomEvent('sc-library-librarian-request',{detail:{prompt:h.prompt || packet.prompt || '',recordIds:h.record_ids || [],target:'#research-librarian',source:'research-librarian-v2'}}));
      document.querySelector('#research-librarian')?.scrollIntoView({behavior:'smooth',block:'start'});
    });
  });
})();
