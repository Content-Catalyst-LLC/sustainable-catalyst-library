(() => {
  'use strict';
  const cfg = window.SCOrchestratorShared || {};
  const roots = Array.from(document.querySelectorAll('[data-sc-library-orchestrator]'));
  if (!roots.length) return;
  const storageKey = cfg.storageKey || 'scLibraryWorkspaceV120';
  const schema = cfg.workspaceSchema || 'sc-library-workspace/1.8';
  const now = () => new Date().toISOString();
  const uid = (prefix) => `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 9)}`;
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const endpoint = (path) => `${String(cfg.restBase || '').replace(/\/$/, '')}/${String(path || '').replace(/^\//, '')}`;
  const headers = () => ({'Content-Type':'application/json', ...(cfg.nonce ? {'X-WP-Nonce':cfg.nonce} : {})});
  const fetchJson = async (path, options = {}) => {
    const response = await fetch(endpoint(path), {...options, headers:{...headers(), ...(options.headers || {})}});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
    return data;
  };
  const blankWorkspace = () => ({schema,version:cfg.version || '1.17.0',createdAt:now(),updatedAt:now(),collections:[{id:'collection_inbox',title:'Research Inbox',description:'Newly saved Library records and research material.',createdAt:now(),updatedAt:now()}],savedRecords:[],notes:[],sources:[],matrices:[],boards:[],handoffs:[],annotations:[],books:[]});
  const loadWorkspace = () => {
    try {
      const data = JSON.parse(localStorage.getItem(storageKey) || 'null') || blankWorkspace();
      ['collections','savedRecords','notes','sources','matrices','boards','handoffs','annotations','books'].forEach((key) => { if (!Array.isArray(data[key])) data[key] = []; });
      data.schema = schema;
      return data;
    } catch (_) { return blankWorkspace(); }
  };
  const saveWorkspace = (workspace) => {
    workspace.schema = schema; workspace.version = cfg.version || '1.17.0'; workspace.updatedAt = now();
    localStorage.setItem(storageKey, JSON.stringify(workspace));
    window.dispatchEvent(new CustomEvent('sc-library-workspace-updated'));
  };
  const collectionByTitle = (workspace, title) => workspace.collections.find((item) => String(item.title).toLowerCase() === String(title).toLowerCase());
  const ensureCollection = (workspace, title, description = '') => {
    let collection = collectionByTitle(workspace, title);
    if (!collection) { collection = {id:uid('collection'),title,description,createdAt:now(),updatedAt:now()}; workspace.collections.push(collection); }
    return collection;
  };
  const matrixRows = () => [
    {id:uid('row'),label:'Core concept',cells:{plain_language:'',formal_notation:'',code_logic:'',assumptions:'',validation:''}},
    {id:uid('row'),label:'Evidence and uncertainty',cells:{plain_language:'',formal_notation:'',code_logic:'',assumptions:'',validation:''}},
  ];
  const applyAction = (action, response) => {
    const ws = loadWorkspace();
    const payload = action.payload || {};
    let collection = null;
    if (payload.collection_title) collection = ensureCollection(ws, payload.collection_title, response.prompt || '');
    if (action.type === 'create_collection') {
      collection = ensureCollection(ws, payload.title || 'Research route', payload.description || '');
    } else if (action.type === 'save_records') {
      collection = ensureCollection(ws, payload.collection_title || 'Research route', response.prompt || '');
      (payload.records || []).forEach((record) => {
        let existing = ws.savedRecords.find((item) => Number(item.recordId) === Number(record.recordId));
        const item = {...record,collectionIds:Array.from(new Set([...(existing?.collectionIds || []),collection.id])),createdAt:existing?.createdAt || now(),updatedAt:now()};
        if (existing) Object.assign(existing,item); else ws.savedRecords.push(item);
      });
    } else if (action.type === 'create_note') {
      collection = ensureCollection(ws, payload.collection_title || 'Research route', response.prompt || '');
      ws.notes.push({id:uid('note'),type:'research_librarian_brief',title:payload.title || 'Research brief',body:payload.body || '',tags:payload.tags || ['research-librarian'],collectionIds:[collection.id],createdAt:now(),updatedAt:now()});
    } else if (action.type === 'create_matrix') {
      collection = ensureCollection(ws, response.prompt ? response.prompt.slice(0,80) : 'Research route', response.prompt || '');
      ws.matrices.push({id:uid('matrix'),title:`Technical Translation Matrix: ${payload.title || 'Research route'}`,description:payload.prompt || '',templateId:'technical_translation',status:'draft',recordId:String(payload.record?.recordId || ''),sourceId:'',tags:['research-librarian'],notes:'Seeded by Research Librarian orchestration. Review every field before use.',collectionIds:[collection.id],columns:['plain_language','formal_notation','code_logic','assumptions','validation'],rows:matrixRows(),createdAt:now(),updatedAt:now()});
    } else if (action.type === 'create_board') {
      collection = ensureCollection(ws, payload.title || 'Research route', payload.prompt || '');
      const nodes = [{id:uid('node'),type:'question',title:'Research question',body:payload.prompt || '',x:440,y:260,width:320,height:160}];
      (payload.records || []).slice(0,10).forEach((record,index) => nodes.push({id:uid('node'),type:'record',title:record.title,body:record.excerpt || '',recordId:record.recordId,url:record.url,x:80+(index%3)*380,y:520+Math.floor(index/3)*230,width:320,height:170}));
      ws.boards.push({id:uid('board'),title:`Research map: ${payload.title || 'Research route'}`,description:payload.prompt || '',type:'whiteboard',boardType:'whiteboard',width:1440,height:1100,nodes,edges:[],collectionIds:[collection.id],createdAt:now(),updatedAt:now()});
    } else if (action.type === 'create_book') {
      collection = ensureCollection(ws, payload.title || 'Research route', payload.prompt || '');
      ws.books.push({id:uid('book'),title:payload.title || 'Research edition',subtitle:payload.prompt || '',description:'Seeded by Research Librarian orchestration.',edition:payload.edition || 'Draft',theme:'institutional',pageSize:'letter',mediaMode:'links',collectionIds:[collection.id],items:(payload.records || []).map((record,index) => ({id:uid('book_item'),type:'record',refId:String(record.recordId),title:record.title,excerpt:record.excerpt || '',url:record.url,presentation:'full',position:index+1})),createdAt:now(),updatedAt:now()});
    } else if (action.type === 'create_handoff') {
      collection = ensureCollection(ws, response.prompt ? response.prompt.slice(0,80) : 'Research route', response.prompt || '');
      const handoff = {schema:'sc-library-handoff/1.0',id:uid('handoff'),target:payload.target,created_at:now(),source:{application:'sustainable-catalyst-library',version:cfg.version || '1.17.0'},context:{type:'orchestration_packet',question:payload.prompt || '',intent:payload.intent || response.intent,records:payload.records || []},target_context:{task:`research_librarian_${payload.intent || response.intent}`,requires_review:true},collectionIds:[collection.id],launch_url:payload.launch_url || ''};
      ws.handoffs.unshift(handoff);
      if (payload.launch_url) {
        const launch = new URL(payload.launch_url, window.location.href); launch.searchParams.set('library_handoff_schema','sc-library-handoff/1.0'); launch.searchParams.set('library_source','research-librarian');
        window.open(launch.toString(),'_blank','noopener');
      }
    } else if (action.type === 'open_editorial') {
      collection = ensureCollection(ws, response.prompt ? response.prompt.slice(0,80) : 'Editorial packet', response.prompt || '');
      ws.notes.push({id:uid('note'),type:'editorial_review_packet',title:'Editorial review packet',body:`${payload.prompt || response.prompt}\n\nRecords:\n${(payload.records || []).map((r,i)=>`${i+1}. ${r.title} — ${r.url}`).join('\n')}\n\nNo publication or approval action has been applied.`,tags:['editorial','research-librarian'],collectionIds:[collection.id],createdAt:now(),updatedAt:now()});
      if (payload.url) window.open(payload.url,'_blank','noopener');
    } else if (action.type === 'export_workspace') {
      const blob = new Blob([JSON.stringify(ws,null,2)],{type:'application/json'}); const url=URL.createObjectURL(blob); const link=document.createElement('a'); link.href=url; link.download=`sustainable-catalyst-library-workspace-${new Date().toISOString().slice(0,10)}.json`; link.click(); URL.revokeObjectURL(url); return;
    }
    saveWorkspace(ws);
  };
  const diagnostics = (response) => JSON.stringify({schema:response.schema,id:response.id,intent:response.intent,diagnostics:response.diagnostics,boundaries:response.boundaries},null,2);
  const librarySearchHref = (root, query) => {
    const target = root.dataset.libraryUrl || '#knowledge-explorer';
    if (target.startsWith('#')) return target;
    try {
      const url = new URL(target, window.location.href);
      if (query) url.searchParams.set('library_search', query);
      return url.toString();
    } catch (_) {
      return target;
    }
  };
  const targetContainsRoot = (selector, root) => {
    if (!selector || !selector.startsWith('#')) return false;
    try {
      const target = document.querySelector(selector);
      return Boolean(target && target.contains(root));
    } catch (_) {
      return false;
    }
  };
  const renderCourses = (courses = [], frontDoor = false) => {
    const items = (courses || []).slice(0, frontDoor ? 2 : 4);
    if (!items.length) return '';
    return `<section class="sc-orchestrator__section sc-orchestrator__course-section"><h3>${frontDoor ? 'Courses that can teach the subject' : 'Recommended open courses'}</h3><div class="sc-orchestrator__courses">${items.map((course)=>`<article class="sc-orchestrator__course"><small>${esc(course.institution || '')} · ${esc(course.access_label || 'Check access')}</small><h4><a href="${esc(course.url || '#')}" target="_blank" rel="noopener">${esc(course.title || '')}</a></h4><p>${esc(course.level || '')}${course.duration_label ? ` · ${esc(course.duration_label)}` : ''}</p><p>${esc(course.why || '')}</p>${(course.pathways || []).length ? `<div class="sc-orchestrator__course-pathways">${course.pathways.map((pathway)=>`<a href="${esc(pathway.url || '#')}">${esc(pathway.title || 'Knowledge Pathway')} →</a>`).join('')}</div>` : ''}</article>`).join('')}</div></section>`;
  };
  const renderAccess = (items = [], frontDoor = false) => {
    const accessItems = (items || []).slice(0, frontDoor ? 2 : 6);
    if (!accessItems.length) return '';
    return `<section class="sc-orchestrator__section sc-orchestrator__access-section"><h3>${frontDoor ? 'Can you access it?' : 'Access intelligence'}</h3><div class="sc-orchestrator__access-list">${accessItems.map((item)=>`<article class="sc-orchestrator__access-item is-${esc(item.state || 'unknown')}"><div class="sc-orchestrator__access-head"><div><small>Research Librarian Access Intelligence</small><h4>${esc(item.title || 'Research source')}</h4></div><strong>${esc(item.state_label || 'ACCESS UNCONFIRMED')}</strong></div><p>${esc(item.availability || '')}</p><p class="sc-orchestrator__access-entitlement">${esc(item.entitlement || '')}</p>${item.best_action?.url ? `<a class="sc-orchestrator__button" href="${esc(item.best_action.url)}" target="_blank" rel="noopener">${esc(item.best_action.label || 'Open access route')}</a>` : ''}${item.stale_count ? `<small class="sc-orchestrator__access-stale">${esc(item.stale_count)} stale route(s) should be rechecked.</small>` : ''}</article>`).join('')}</div><p class="sc-orchestrator__access-boundary">Availability does not establish entitlement. Libraries and providers remain authoritative for login, membership, borrowing, request, and rights conditions.</p></section>`;
  };
  const renderResponse = (root, response) => {
    const output = root.querySelector('[data-orchestrator-output]');
    const provider = response.diagnostics?.provider || {};
    const frontDoor = root.dataset.orchestratorMode === 'front-door';
    const fullUrl = root.dataset.fullUrl || '#research-librarian';
    if (frontDoor) {
      const records = (response.records || []).slice(0, 3);
      const pathways = (response.pathways || []).slice(0, 1);
      const routes = (response.routes || []).slice(0, 2);
      output.innerHTML = `<section class="sc-orchestrator__summary sc-orchestrator__summary--front-door"><div><h3>${esc(response.intent_label || 'Research route')}</h3><p>${esc(response.answer || '')}</p><div class="sc-orchestrator__meta"><span>${esc(response.records?.length || 0)} matching records</span><span>${esc(response.diagnostics?.retrieval_mode || 'site-scoped')}</span></div></div></section>
      <section class="sc-orchestrator__section"><h3>Recommended starting points</h3><div class="sc-orchestrator__records sc-orchestrator__records--front-door">${records.map((record) => `<article class="sc-orchestrator__record ${record.graph_related ? 'is-graph' : ''}"><small>${record.graph_related ? 'Graph-connected record' : esc(record.post_type || 'Library record')}</small><h4><a href="${esc(record.url)}">${esc(record.title)}</a></h4><ul class="sc-orchestrator__why">${(record.why || []).slice(0,2).map((reason)=>`<li>${esc(reason)}</li>`).join('')}</ul></article>`).join('') || '<p>No matching records were found.</p>'}</div></section>
      ${renderAccess(response.access || [], true)}
      ${pathways.length ? `<section class="sc-orchestrator__section sc-orchestrator__pathway-section"><h3>Recommended knowledge pathway</h3>${pathways.map((pathway)=>`<article class="sc-orchestrator__pathway"><div class="sc-orchestrator__pathway-head"><div><small>${esc(pathway.level_label || pathway.level || 'Knowledge pathway')} · ${esc(pathway.step_count || 0)} steps</small><h4>${pathway.url ? `<a href="${esc(pathway.url)}">${esc(pathway.title)}</a>` : esc(pathway.title)}</h4><p>${esc(pathway.summary || '')}</p></div>${pathway.url ? `<a class="sc-orchestrator__button" href="${esc(pathway.url)}">Open pathway</a>` : ''}</div><ul class="sc-orchestrator__pathway-steps">${(pathway.steps || []).slice(0,4).map((step)=>`<li><span>${String(step.order || '').padStart(2,'0')}</span>${step.url ? `<a href="${esc(step.url)}">${esc(step.label)}</a>` : `<strong>${esc(step.label)}</strong>`}</li>`).join('')}</ul><p class="sc-orchestrator__pathway-reason">${esc((pathway.reasons || []).slice(0,2).join(' · '))}</p></article>`).join('')}</section>` : ''}
      ${renderCourses(response.courses || [], true)}
      ${routes.length ? `<section class="sc-orchestrator__section"><h3>Continue the route</h3><div class="sc-orchestrator__routes sc-orchestrator__routes--front-door">${routes.map((route)=>`<article class="sc-orchestrator__route"><h4>${esc(route.label)}</h4><p>${esc(route.reason)}</p>${route.url ? `<a class="sc-orchestrator__button" href="${esc(route.url)}">Open ${esc(route.label)}</a>` : ''}</article>`).join('')}</div></section>` : ''}
      <div class="sc-orchestrator__continue"><a class="sc-orchestrator__button is-primary" href="${esc(fullUrl)}">Continue in the full Research Librarian</a><a class="sc-orchestrator__button" data-library-search-from-librarian href="${esc(librarySearchHref(root, response.prompt || ''))}">View all matching Library records</a><span>Move between guided recommendations and conventional Library search without losing the research question.</span></div>`;
      output.dataset.response = JSON.stringify(response);
      return;
    }
    output.innerHTML = `<section class="sc-orchestrator__summary"><div><h3>${esc(response.intent_label || 'Research route')}</h3><p>${esc(response.answer || '')}</p><div class="sc-orchestrator__meta"><span>${esc(response.records?.length || 0)} records</span><span>${esc(response.diagnostics?.retrieval_mode || '')}</span><span>${esc(provider.mode || 'deterministic')}</span></div></div><div class="sc-orchestrator__summary-actions">${cfg.signedIn ? '<button type="button" data-save-orchestration>Save session</button>' : ''}<button type="button" data-copy-orchestration>Copy route</button></div></section>
    <section class="sc-orchestrator__section"><h3>Recommended Library records</h3><div class="sc-orchestrator__records">${(response.records || []).map((record) => `<article class="sc-orchestrator__record ${record.graph_related ? 'is-graph' : ''}"><small>${record.graph_related ? 'Graph-connected record' : esc(record.post_type || 'Library record')}</small><h4><a href="${esc(record.url)}">${esc(record.title)}</a></h4><p>${esc(record.excerpt || '')}</p><ul class="sc-orchestrator__why">${(record.why || []).map((reason)=>`<li>${esc(reason)}</li>`).join('')}</ul></article>`).join('') || '<p>No matching records were found.</p>'}</div></section>
    ${renderAccess(response.access || [], false)}
    ${(response.pathways || []).length ? `<section class="sc-orchestrator__section sc-orchestrator__pathway-section"><h3>Recommended Knowledge Pathways</h3><div class="sc-orchestrator__pathways">${(response.pathways || []).map((pathway)=>`<article class="sc-orchestrator__pathway"><div class="sc-orchestrator__pathway-head"><div><small>${esc(pathway.level_label || pathway.level || 'Knowledge pathway')} · ${esc(pathway.step_count || 0)} steps</small><h4>${pathway.url ? `<a href="${esc(pathway.url)}">${esc(pathway.title)}</a>` : esc(pathway.title)}</h4><p>${esc(pathway.summary || '')}</p></div>${pathway.url ? `<a class="sc-orchestrator__button" href="${esc(pathway.url)}">Open pathway</a>` : ''}</div><ul class="sc-orchestrator__why">${(pathway.reasons || []).map((reason)=>`<li>${esc(reason)}</li>`).join('')}</ul><ol class="sc-orchestrator__pathway-sequence">${(pathway.steps || []).slice(0,5).map((step)=>`<li>${step.url ? `<a href="${esc(step.url)}">${esc(step.label)}</a>` : esc(step.label)}</li>`).join('')}</ol></article>`).join('')}</div></section>` : ''}
    ${renderCourses(response.courses || [], false)}
    <section class="sc-orchestrator__section"><h3>Recommended route</h3><div class="sc-orchestrator__routes">${(response.routes || []).map((route)=>`<article class="sc-orchestrator__route"><h4>${esc(route.label)}</h4><p>${esc(route.reason)}</p><div class="sc-orchestrator__route-footer">${route.url ? `<a class="sc-orchestrator__button" href="${esc(route.url)}">Open ${esc(route.label)}</a>` : ''}</div></article>`).join('')}</div></section>
    <section class="sc-orchestrator__section"><h3>Workspace actions</h3><div class="sc-orchestrator__actions">${(response.actions || []).map((action)=>`<article class="sc-orchestrator__action"><h4>${esc(action.label)}</h4><p>${esc(action.description)}</p><div class="sc-orchestrator__action-footer"><button type="button" data-apply-action="${esc(action.id)}">Apply to workspace</button><small>User confirmation required</small></div></article>`).join('')}</div></section>
    <details class="sc-orchestrator__diagnostics"><summary>Retrieval and safety diagnostics</summary><pre>${esc(diagnostics(response))}</pre></details>`;
    output.dataset.response = JSON.stringify(response);
  };
  const notice = (root, message, error = false) => { const node=root.querySelector('[data-orchestrator-notice]'); node.hidden=!message; node.textContent=message || ''; node.classList.toggle('is-error',error); };
  roots.forEach((root) => {
    const form = root.querySelector('[data-orchestrator-form]'); const select=form.elements.intent; const initial=root.dataset.initialIntent || 'auto';
    const initialRecordIds = String(root.dataset.initialRecordIds || '').split(',').map(Number).filter(Boolean);
    let contextualRecordIds = initialRecordIds.slice(0, 8);
    if (select && select.tagName === 'SELECT') {
      select.innerHTML=(cfg.intents || []).map((item)=>`<option value="${esc(item.id)}" ${item.id===initial?'selected':''}>${esc(item.label)}</option>`).join('');
    } else if (select) {
      select.value = initial;
    }
    let current = null;
    document.addEventListener('sc-library-librarian-request', (event) => {
      const detail = event.detail || {};
      if (detail.target && !targetContainsRoot(detail.target, root)) return;
      const prompt = String(detail.prompt || '').trim();
      if (!prompt) return;
      form.elements.prompt.value = prompt;
      contextualRecordIds = Array.from(new Set([...(detail.recordIds || []).map(Number).filter(Boolean), ...initialRecordIds])).slice(0, 8);
      notice(root, cfg.strings?.libraryContextReady || 'Library search context added. Review the question and ask when ready.');
      form.elements.prompt.focus();
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault(); notice(root,cfg.strings?.working || 'Searching…'); form.querySelector('button[type="submit"]').disabled=true;
      try {
        const selectedRecord = Number(root.dataset.initialRecord || 0);
        const recordIds = Array.from(new Set([...(selectedRecord ? [selectedRecord] : []), ...contextualRecordIds])).slice(0, 8);
        current = await fetchJson('query',{method:'POST',body:JSON.stringify({prompt:form.elements.prompt.value,intent:form.elements.intent.value,max_records:Number(form.elements.max_records.value),record_ids:recordIds})});
        renderResponse(root,current); notice(root,'');
      } catch (error) { notice(root,error.message || cfg.strings?.error || 'Request failed.',true); }
      finally { form.querySelector('button[type="submit"]').disabled=false; }
    });
    root.addEventListener('click', async (event) => {
      const example = event.target.closest('[data-orchestrator-example]');
      if (example) {
        form.elements.prompt.value = example.dataset.orchestratorExample || example.textContent || '';
        form.elements.prompt.focus();
        return;
      }
      const librarySearch = event.target.closest('[data-library-search-from-librarian]');
      if (librarySearch && current) {
        const target = root.dataset.libraryUrl || '#knowledge-explorer';
        if (target.startsWith('#') && document.querySelector(target)) {
          event.preventDefault();
          document.dispatchEvent(new CustomEvent('sc-library-search-request', {detail:{query:current.prompt || form.elements.prompt.value,target,source:'research-librarian'}}));
        }
        return;
      }
      const apply = event.target.closest('[data-apply-action]');
      if (apply && current) {
        const action=(current.actions || []).find((item)=>item.id===apply.dataset.applyAction); if (!action) return;
        if (!window.confirm(cfg.strings?.confirmAction || 'Apply this action?')) return;
        try {
          applyAction(action,current);
          if (current.session_uuid && cfg.signedIn) {
            try { await fetchJson('events',{method:'POST',body:JSON.stringify({session_uuid:current.session_uuid,event_type:'action_applied',payload:{action_id:action.id,action_type:action.type,target:action.target || ''}})}); } catch (_) {}
          }
          notice(root,cfg.strings?.applied || 'Action applied.'); apply.disabled=true; apply.textContent='Applied';
        } catch (error) { notice(root,error.message || 'Could not update local workspace.',true); }
        return;
      }
      if (event.target.closest('[data-copy-orchestration]') && current) {
        try { await navigator.clipboard.writeText(JSON.stringify(current,null,2)); notice(root,'Research route copied.'); } catch (_) { notice(root,'The browser blocked copying.',true); }
        return;
      }
      if (event.target.closest('[data-save-orchestration]') && current) {
        try { const saved=await fetchJson('sessions',{method:'POST',body:JSON.stringify({title:current.prompt,response:current})}); current.session_uuid=saved.uuid; notice(root,cfg.strings?.savedSession || 'Session saved.'); event.target.disabled=true; event.target.textContent='Saved'; } catch (error) { notice(root,error.message || 'Could not save session.',true); }
      }
    });
  });
})();
