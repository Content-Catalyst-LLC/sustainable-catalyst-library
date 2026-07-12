(() => {
  'use strict';
  const shared = window.SCIntegrationShared || {};
  const storageKey = shared.storageKey || 'scLibraryWorkspaceV120';
  const workspaceSchema = shared.workspaceSchema || 'sc-library-workspace/1.3';
  const handoffSchema = shared.handoffSchema || 'sc-library-handoff/1.0';
  const restBase = String(shared.restBase || '').replace(/\/$/, '');
  const targets = Array.isArray(shared.targets) ? shared.targets : [];
  const strings = shared.strings || {};
  const roots = () => Array.from(document.querySelectorAll('[data-sc-library-integrations], [data-sc-library-integrations-inline]'));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const now = () => new Date().toISOString();
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,9)}`;
  const safeParse = (raw, fallback = null) => { try { return JSON.parse(raw); } catch (_) { return fallback; } };
  const loadWorkspace = () => {
    const raw = window.localStorage.getItem(storageKey);
    const data = raw ? safeParse(raw, {}) : {};
    if (!data || typeof data !== 'object') return {};
    data.schema = workspaceSchema;
    data.handoffs = Array.isArray(data.handoffs) ? data.handoffs : [];
    data.savedRecords = Array.isArray(data.savedRecords) ? data.savedRecords : [];
    data.notes = Array.isArray(data.notes) ? data.notes : [];
    data.sources = Array.isArray(data.sources) ? data.sources : [];
    data.matrices = Array.isArray(data.matrices) ? data.matrices : [];
    data.boards = Array.isArray(data.boards) ? data.boards : [];
    data.collections = Array.isArray(data.collections) ? data.collections : [];
    if (!data.collections.length) data.collections.push({id:'collection_inbox',title:'Research Inbox',description:'Newly saved Library records and research material.',createdAt:now(),updatedAt:now()});
    data.createdAt = data.createdAt || now();
    data.updatedAt = data.updatedAt || now();
    return data;
  };
  const saveWorkspace = (data) => {
    data.schema = workspaceSchema;
    data.version = shared.version || data.version || '1.5.0';
    data.updatedAt = now();
    window.localStorage.setItem(storageKey, JSON.stringify(data));
    window.dispatchEvent(new CustomEvent('sc-library-workspace-updated'));
  };
  const getTarget = (id) => targets.find((item) => item.id === id);
  const contextItems = (ws) => [
    ...ws.savedRecords.map((x) => ({type:'library_record', id:String(x.recordId), title:x.title, object:x})),
    ...ws.collections.map((x) => ({type:'collection', id:x.id, title:x.title, object:x})),
    ...ws.notes.map((x) => ({type:'note', id:x.id, title:x.title, object:x})),
    ...ws.sources.map((x) => ({type:'source', id:x.id, title:x.title, object:x})),
    ...ws.matrices.map((x) => ({type:'translation_matrix', id:x.id, title:x.title, object:x})),
    ...ws.boards.map((x) => ({type:x.type === 'chalkboard' ? 'chalkboard' : 'whiteboard', id:x.id, title:x.title, object:x})),
  ];
  const compactObject = (entry, ws) => {
    const object = entry.object || {};
    if (entry.type === 'collection') {
      return {id:entry.id,title:entry.title,description:object.description || '',linked_items:contextItems(ws).filter((x) => x.type !== 'collection' && (x.object.collectionIds || []).includes(entry.id)).slice(0,20).map((x) => ({type:x.type,id:x.id,title:x.title}))};
    }
    if (entry.type === 'translation_matrix') return {id:entry.id,title:entry.title,description:object.description || '',rows:(object.rows || []).length,columns:(object.columns || []).map((c) => c.label || c.title || c.id),recordId:object.recordId || ''};
    if (entry.type === 'whiteboard' || entry.type === 'chalkboard') return {id:entry.id,title:entry.title,description:object.description || '',type:entry.type,nodes:(object.nodes || []).slice(0,20).map((n) => ({type:n.type,title:n.title,body:n.body})),edges:(object.edges || []).slice(0,20).map((e) => ({type:e.type,label:e.label}))};
    return {...object, id:entry.id, type:entry.type, title:entry.title};
  };
  const buildPackage = ({targetId, entry, purpose, question}, ws) => ({
    schema: handoffSchema,
    id: uid('handoff'),
    target: targetId,
    created_at: now(),
    source: {application:'sustainable-catalyst-library',version:shared.version || '1.5.0',url:window.location.href},
    context: {type:entry.type,id:entry.id,title:entry.title,data:compactObject(entry, ws)},
    request: {purpose:purpose || '',question:question || ''},
    collectionIds: Array.isArray(entry.object?.collectionIds) && entry.object.collectionIds.length ? entry.object.collectionIds : ['collection_inbox'],
  });
  const b64url = (object) => {
    const bytes = new TextEncoder().encode(JSON.stringify(object));
    let binary = ''; bytes.forEach((b) => { binary += String.fromCharCode(b); });
    return btoa(binary).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
  };
  const launchUrl = (target, handoff) => {
    const url = new URL(target.url || window.location.href, window.location.href);
    url.searchParams.set('sc_library_handoff_id', handoff.id);
    url.searchParams.set('sc_library_source', 'research-library');
    url.searchParams.set('sc_library_handoff_schema', handoffSchema);
    url.hash = `sc-library-handoff=${b64url(handoff)}`;
    return url.toString();
  };
  const download = (name, data) => {
    const blob = new Blob([JSON.stringify(data, null, 2)], {type:'application/json'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  };
  const statusMap = new Map();
  const loadStatuses = async (refresh = false) => {
    try {
      const response = await fetch(`${restBase}/integrations/status${refresh ? '?refresh=1' : ''}`, {credentials:'same-origin'});
      const data = await response.json();
      (data.items || []).forEach((item) => statusMap.set(item.id, item));
    } catch (_) {}
  };
  const statusHtml = (id) => {
    const item = statusMap.get(id);
    const state = item?.state || 'configured';
    const label = state === 'online' ? 'Online' : state === 'unavailable' ? 'Unavailable' : state === 'not_configured' ? 'Not configured' : 'Configured';
    return `<span class="sc-library-integration-status is-${esc(state)}" title="${esc(item?.message || '')}">${esc(label)}</span>`;
  };
  let selectedTarget = targets[0]?.id || '';
  let seededContext = null;
  const renderRoot = (root) => {
    if (root.matches('[data-sc-library-integrations-inline]') && root.dataset.integrationHydrated !== '1') {
      root.dataset.integrationHydrated = '1';
      root.innerHTML = `<div class="sc-library-workspace__integration-host"><div class="sc-library-integrations__targets" data-integration-targets></div><div data-integration-builder></div><div data-integration-history></div></div>`;
    }
    const targetHost = root.querySelector('[data-integration-targets]') || root;
    const builder = root.querySelector('[data-integration-builder]') || root;
    const history = root.querySelector('[data-integration-history]');
    const ws = loadWorkspace();
    if (targetHost) targetHost.innerHTML = targets.map((t) => `<article class="sc-library-integration-card"><div class="sc-library-integration-card__top"><span class="sc-library__eyebrow">Connected tool</span>${statusHtml(t.id)}</div><h3>${esc(t.label)}</h3><p>${esc(t.description)}</p><ul>${(t.capabilities || []).slice(0,5).map((c) => `<li>${esc(c.replace(/_/g,' '))}</li>`).join('')}</ul><button type="button" data-integration-target="${esc(t.id)}">${esc(t.action_label || 'Prepare handoff')}</button></article>`).join('');
    const items = contextItems(ws);
    const current = seededContext && items.find((x) => x.type === seededContext.type && String(x.id) === String(seededContext.id));
    const selectedValue = current ? `${current.type}:${current.id}` : '';
    if (builder) builder.innerHTML = `<form class="sc-library-integration-form" data-integration-form><input type="hidden" name="target" value="${esc(selectedTarget)}"><div class="sc-library-integration-form__grid"><label><span>Destination</span><select name="targetSelect">${targets.map((t) => `<option value="${esc(t.id)}" ${t.id === selectedTarget ? 'selected' : ''}>${esc(t.label)}</option>`).join('')}</select></label><label><span>Research object</span><select name="context" required><option value="">Choose a saved record, source, note, matrix, board, or collection</option>${items.map((x) => `<option value="${esc(`${x.type}:${x.id}`)}" ${`${x.type}:${x.id}` === selectedValue ? 'selected' : ''}>${esc(x.type.replace(/_/g,' '))} — ${esc(x.title)}</option>`).join('')}</select></label><label><span>Purpose</span><input name="purpose" placeholder="What should the connected tool do with this material?"></label><label><span>Research question</span><input name="question" placeholder="Question, hypothesis, decision, or geographic inquiry"></label></div><div class="sc-library-integration-form__actions"><button type="submit">Prepare handoff</button></div><div data-integration-preview></div></form>`;
    if (history) history.innerHTML = `<div class="sc-library-integration-history"><h3>Saved handoffs</h3>${(ws.handoffs || []).length ? ws.handoffs.slice().sort((a,b) => String(b.created_at).localeCompare(String(a.created_at))).slice(0,10).map((h) => `<article><div><small>${esc(getTarget(h.target)?.label || h.target)}</small><h4>${esc(h.context?.title || 'Research handoff')}</h4><p>${esc(h.request?.purpose || h.request?.question || 'No purpose recorded')}</p></div><div><button type="button" data-open-handoff="${esc(h.id)}">Open</button></div></article>`).join('') : '<p>No handoffs have been saved yet.</p>'}</div>`;
  };
  const renderAll = () => roots().forEach(renderRoot);
  const handlePackage = async (form) => {
    const ws = loadWorkspace();
    const targetId = form.elements.targetSelect.value;
    const key = form.elements.context.value;
    const [type, ...idParts] = key.split(':'); const id = idParts.join(':');
    const entry = contextItems(ws).find((x) => x.type === type && String(x.id) === String(id));
    if (!entry) { window.alert(strings.selectContext || 'Choose a context.'); return; }
    const handoff = buildPackage({targetId,entry,purpose:form.elements.purpose.value,question:form.elements.question.value}, ws);
    ws.handoffs.unshift(handoff); saveWorkspace(ws);
    renderAll();
    const target = getTarget(targetId);
    const activeForm = Array.from(document.querySelectorAll('[data-integration-form]')).find((candidate) => candidate.elements.targetSelect?.value === targetId) || document.querySelector('[data-integration-form]');
    const preview = activeForm?.querySelector('[data-integration-preview]');
    if (!preview) return;
    preview.innerHTML = `<div class="sc-library-integration-preview"><strong>${esc(target?.label || targetId)} handoff ready</strong><pre>${esc(JSON.stringify(handoff,null,2))}</pre><div class="sc-library-integration-form__actions"><button type="button" data-launch-current class="is-secondary">Open connected tool</button><button type="button" data-copy-current class="is-secondary">Copy JSON</button><button type="button" data-download-current class="is-secondary">Download JSON</button></div></div>`;
    preview.querySelector('[data-launch-current]')?.addEventListener('click', () => window.open(launchUrl(target, handoff), '_blank', 'noopener'));
    preview.querySelector('[data-copy-current]')?.addEventListener('click', async () => { try { await navigator.clipboard.writeText(JSON.stringify(handoff,null,2)); } catch (_) { window.alert(strings.copyError || 'Copy failed.'); } });
    preview.querySelector('[data-download-current]')?.addEventListener('click', () => download(`sc-library-handoff-${handoff.id}.json`,handoff));
  };
  document.addEventListener('click', async (event) => {
    const refresh = event.target.closest('[data-integration-refresh]');
    if (refresh) { await loadStatuses(true); renderAll(); return; }
    const target = event.target.closest('[data-integration-target]');
    if (target) { selectedTarget = target.dataset.integrationTarget; renderAll(); document.querySelector('[data-integration-form]')?.scrollIntoView({behavior:'smooth',block:'center'}); return; }
    const open = event.target.closest('[data-open-handoff]');
    if (open) { const ws = loadWorkspace(); const h = (ws.handoffs || []).find((x) => x.id === open.dataset.openHandoff); if (h) window.open(launchUrl(getTarget(h.target),h),'_blank','noopener'); }
  });
  document.addEventListener('change', (event) => { if (event.target.matches('[data-integration-form] select[name="targetSelect"]')) selectedTarget = event.target.value; });
  document.addEventListener('submit', (event) => { const form = event.target.closest('[data-integration-form]'); if (!form) return; event.preventDefault(); handlePackage(form); });
  document.addEventListener('sc-library-integrate-record', (event) => {
    const record = event.detail?.record; const target = event.detail?.target || selectedTarget;
    if (!record?.id) return;
    const ws = loadWorkspace();
    let saved = ws.savedRecords.find((x) => Number(x.recordId) === Number(record.id));
    if (!saved) { saved = {recordId:Number(record.id),recordIdentifier:record.record_identifier || '',title:record.title,url:record.url,excerpt:record.excerpt || '',resources:record.resources || {},categories:record.categories || [],concepts:record.concepts || [],series:record.series || null,collectionIds:['collection_inbox'],createdAt:now(),updatedAt:now()}; ws.savedRecords.push(saved); saveWorkspace(ws); }
    selectedTarget = target; seededContext = {type:'library_record',id:String(record.id)};
    document.querySelectorAll('[data-sc-library-workspace-root]').forEach((root) => root.querySelector('[data-workspace-tab="integrations"]')?.click());
    renderAll();
  });
  window.addEventListener('sc-library-workspace-updated', renderAll);
  window.addEventListener('sc-library-integrations-render', renderAll);
  loadStatuses(false).then(renderAll);
})();
