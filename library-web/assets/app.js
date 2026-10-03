const config = window.SC_LIBRARY_WEB_CONFIG || { apiBase: "/api/library/v1", webVersion: "2.9.0" };
const API = String(config.apiBase || "/api/library/v1").replace(/\/$/, "");
const state = { offset: 0, limit: 20, query: "", mode: "hybrid", total: 0, lastSearch: null, session: null, csrfToken: null, researchOffset: 0, researchTotal: 0, researchBootstrap: null, navigationBootstrap: null, researchNavigationMode: "overview", workingSet: [], workspaceSnapshot: null, activeProjectId: null, livingRefreshes: {}, projectBriefs: {}, structuredDatasetPreview: null, scientificLiteraturePreview: null };

const $ = (selector, root=document) => root.querySelector(selector);
const $$ = (selector, root=document) => [...root.querySelectorAll(selector)];
const escapeHtml = (value="") => String(value).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
const text = value => value == null ? "" : String(value);

async function api(path, options={}) {
  const response = await fetch(`${API}${path}`, { credentials: "same-origin", headers: { Accept: "application/json", ...(options.body ? {"Content-Type":"application/json"} : {}), ...(options.headers||{}) }, ...options });
  let body = null;
  try { body = await response.json(); } catch { body = null; }
  if (!response.ok) {
    const message = body?.error?.message || body?.detail?.error?.message || body?.detail || `Request failed (${response.status})`;
    throw new Error(typeof message === "string" ? message : `Request failed (${response.status})`);
  }
  return body;
}

function setApiStatus(ok, label) {
  const pill = $("#api-status"); pill.classList.toggle("offline", !ok); pill.classList.toggle("online", ok);
  pill.lastChild.textContent = label; pill.querySelector("span").setAttribute("aria-label", ok ? "online" : "offline");
}

function currentRoute() {
  const hashRaw=(location.hash || "").replace(/^#\/?/, "");
  if (hashRaw) return hashRaw;
  return location.pathname.replace(/^\/+|\/+$/g, "") || "research";
}

function resolveUnifiedResearchMode(name) {
  const requested=new URLSearchParams(location.search).get("mode");
  if (name === "search" || name === "discover") return name;
  return ["overview","discover","search","working-set"].includes(requested) ? requested : "overview";
}

function route() {
  const raw=currentRoute(); const [name="research", ...rest]=raw.split("/");
  const compatibilityResearch = name === "search" || name === "discover";
  const view = compatibilityResearch ? "research" : ["research","system","account"].includes(name) ? name : name === "record" ? "record" : "research";
  $$(".view").forEach(el => { el.hidden = el.dataset.view !== view; });
  $$('[data-nav]').forEach(a => a.setAttribute('aria-current', a.dataset.nav===view ? 'page' : 'false'));
  updatePublicMetadata(view, rest);
  if (view === "research") loadUnifiedNavigation(resolveUnifiedResearchMode(name));
  if (view === "system") loadSystem();
  if (view === "account") loadSession().then(()=>{ if(new URLSearchParams(location.search).get("section")==="workspaces") requestAnimationFrame(()=>$("#workspace-card")?.scrollIntoView({block:"start"})); });
  if (view === "record" && rest.length) loadRecord(decodeURIComponent(rest.join("/")));
  requestAnimationFrame(() => $("#main")?.focus({preventScroll:true}));
}

function navigate(path) {
  const target=new URL(path,location.origin);
  const current=location.pathname+location.search;
  const next=target.pathname+target.search;
  if (current !== next || location.hash) history.pushState({}, "", next);
  route();
}

function updatePublicMetadata(view, rest=[]) {
  const origin=String(config.publicOrigin || location.origin).replace(/\/$/, "");
  let path=view === "research" ? "/research" : `/${view}`;
  if (view === "record" && rest.length) path=`/record/${encodeURIComponent(decodeURIComponent(rest.join("/")))}`;
  const canonical=document.querySelector('link[rel="canonical"]') || document.head.appendChild(Object.assign(document.createElement('link'),{rel:'canonical'}));
  canonical.href=origin + (path === "/search" ? path : path);
  const robots=document.querySelector('meta[name="robots"]') || document.head.appendChild(Object.assign(document.createElement('meta'),{name:'robots'}));
  robots.content=["research","search","system","account"].includes(view) ? "noindex,follow" : "index,follow";
}

function setMeta(name, content, property=false) {
  const selector=property ? `meta[property="${name}"]` : `meta[name="${name}"]`;
  const node=document.querySelector(selector) || document.head.appendChild(Object.assign(document.createElement('meta'), property ? {property:name}:{name}));
  node.content=content || "";
}

function applySeoDescriptor(seo) {
  if (!seo) return;
  document.title=seo.title ? `${seo.title} — Sustainable Catalyst Knowledge Library` : 'Sustainable Catalyst Knowledge Library';
  setMeta('description', seo.description || '');
  setMeta('robots', seo.robots || 'index,follow');
  setMeta('og:type', seo.open_graph?.type || 'article', true);
  setMeta('og:title', seo.open_graph?.title || seo.title || '', true);
  setMeta('og:description', seo.open_graph?.description || seo.description || '', true);
  setMeta('og:url', seo.open_graph?.url || seo.canonical_url || '', true);
  const canonical=document.querySelector('link[rel="canonical"]'); if (canonical && seo.canonical_url) canonical.href=seo.canonical_url;
}


function pick(record, keys) { for (const k of keys) if (record?.[k] != null && record[k] !== "") return record[k]; return null; }
function resultTitle(record) { return pick(record,["title","name","label","display_title","record_id","id"]) || "Untitled record"; }
function resultSummary(record) { return pick(record,["abstract","summary","description","excerpt","body_text","body"]) || "No summary available."; }
function resultId(record) { return pick(record,["record_id","id","object_id","key"]); }
function objectType(record) { return pick(record,["object_type","type","record_type","kind"]) || "record"; }

function renderResults(items, append=false) {
  const target=$("#search-results"); if (!append) target.innerHTML="";
  if (!items.length && !append) { target.innerHTML='<p class="empty-state">No records matched this search.</p>'; return; }
  const frag=document.createDocumentFragment();
  for (const record of items) {
    const id=resultId(record); const card=document.createElement("article"); card.className="result-card";
    const title=escapeHtml(resultTitle(record)); const summary=escapeHtml(text(resultSummary(record)).slice(0,700)); const type=escapeHtml(objectType(record));
    card.innerHTML=`<div class="result-meta"><span>${type}</span>${record.year?`<span>${escapeHtml(record.year)}</span>`:""}</div><h3>${id?`<a href="/record/${encodeURIComponent(id)}" data-app-link>${title}</a>`:title}</h3><p>${summary}</p>`;
    frag.append(card);
  }
  target.append(frag);
}


const WORKING_SET_KEY="sc-library-research-working-set-v1";

function loadWorkingSet() {
  try {
    const raw=JSON.parse(localStorage.getItem(WORKING_SET_KEY) || "[]");
    state.workingSet=Array.isArray(raw) ? raw.filter(x=>x && x.id).slice(0,100) : [];
  } catch { state.workingSet=[]; }
  renderWorkingSet();
}
function saveWorkingSet() { localStorage.setItem(WORKING_SET_KEY,JSON.stringify(state.workingSet.slice(0,100))); renderWorkingSet(); }
function renderWorkingSet() {
  const target=$("#working-set"); const count=$("#working-set-count"); if (!target || !count) return;
  count.textContent=String(state.workingSet.length);
  if (!state.workingSet.length) { target.innerHTML='<p class="empty-state">No records selected.</p>'; return; }
  target.innerHTML=state.workingSet.map(item=>`<article class="working-item"><a href="/record/${encodeURIComponent(item.id)}" data-app-link>${escapeHtml(item.title || item.id)}</a><button type="button" data-remove-working="${escapeHtml(item.id)}" class="text-button">Remove</button></article>`).join("");
}
function addToWorkingSet(record) {
  const id=resultId(record); if (!id) return;
  if (!state.workingSet.find(x=>x.id===id)) state.workingSet.push({id,title:resultTitle(record),type:objectType(record)});
  saveWorkingSet();
}
function researchFacetOptions(select, values) {
  if (!select || !Array.isArray(values)) return;
  const first=select.firstElementChild?.outerHTML || '<option value="">All</option>'; select.innerHTML=first;
  for (const item of values) {
    const value=typeof item==="string" ? item : (item?.value ?? item?.key ?? item?.id);
    const label=typeof item==="string" ? item : (item?.label ?? item?.name ?? value);
    if (value == null || value === "") continue;
    select.insertAdjacentHTML("beforeend",`<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
  }
}

function renderResearchPathways(groups=[]) {
  const target=$("#research-pathways"); if (!target) return;
  if (!groups.length) { target.innerHTML='<p class="empty-state">No research pathways are available.</p>'; return; }
  target.innerHTML=groups.map(group=>`<article class="research-pathway"><p class="eyebrow">${escapeHtml(group.label || group.id)}</p><h3>${escapeHtml(group.label || group.id)}</h3><ul>${(group.items||[]).map(item=>`<li><strong>${escapeHtml(item.family)}</strong><span>${escapeHtml((item.resources||[]).slice(0,4).join(" · "))}</span></li>`).join("")}</ul></article>`).join("");
}

function applyUnifiedResearchMode(mode="overview") {
  state.researchNavigationMode=mode;
  document.body.dataset.researchMode=mode;
  $$("[data-research-mode-link]").forEach(link=>link.setAttribute("aria-current",link.dataset.researchModeLink===mode ? "page" : "false"));
  if (mode === "discover") requestAnimationFrame(()=>$("#research-discovery")?.scrollIntoView({block:"start"}));
  if (mode === "search") requestAnimationFrame(()=>$("#research-query")?.focus({preventScroll:false}));
  if (mode === "working-set") requestAnimationFrame(()=>$("#working-set-panel")?.scrollIntoView({block:"start"}));
}

async function loadUnifiedNavigation(mode="overview") {
  const note=$("#navigation-status");
  try {
    if (!state.navigationBootstrap) state.navigationBootstrap=await api("/navigation/bootstrap");
    renderResearchPathways(state.navigationBootstrap.pathways || []);
    if (note) note.textContent=`Unified navigation · Web ${config.webVersion || "2.2.0"}`;
  } catch (error) {
    if (note) note.innerHTML=`<span class="error-state">${escapeHtml(error.message)}</span>`;
  }
  await loadResearchBootstrap();
  applyUnifiedResearchMode(mode);
}


function workspaceCsrfHeaders() {
  return state.csrfToken ? {"X-SC-CSRF-Token": state.csrfToken} : {};
}

function populateWorkspaceProjectSelect() {
  const select=$("#working-set-project"); if (!select) return;
  const projects=state.workspaceSnapshot?.projects || [];
  select.innerHTML=projects.length ? '<option value="">Choose a project</option>' + projects.map(project=>`<option value="${escapeHtml(project.project_id)}">${escapeHtml(project.title || project.project_id)}</option>`).join("") : '<option value="">No saved projects yet</option>';
  if (state.activeProjectId && projects.some(x=>x.project_id===state.activeProjectId)) select.value=state.activeProjectId;
}

function populateLivingProjectSelect() {
  const select=$("#workspace-living-project"); if (!select) return;
  const projects=state.workspaceSnapshot?.projects || [];
  select.innerHTML='<option value="">No project link</option>' + projects.map(project=>`<option value="${escapeHtml(project.project_id)}">${escapeHtml(project.title || project.project_id)}</option>`).join("");
  if (state.activeProjectId && projects.some(x=>x.project_id===state.activeProjectId)) select.value=state.activeProjectId;
}

function renderLivingCollections() {
  const target=$("#workspace-living-list"); if (!target) return;
  if (!state.workspaceSnapshot) { target.innerHTML='<p class="empty-state">Sign in to load living collections.</p>'; return; }
  const collections=(state.workspaceSnapshot.collections || []).filter(item=>Boolean(item?.metadata?.living_collection?.enabled));
  if (!collections.length) { target.innerHTML='<p class="empty-state">No living collections yet. Create one from a research query.</p>'; return; }
  target.innerHTML=collections.map(item=>{
    const id=item.collection_id; const cfg=item.metadata?.living_collection || {}; const preview=state.livingRefreshes[id];
    const candidates=preview?.candidate_additions || [];
    const choices=candidates.length ? `<fieldset class="living-candidates"><legend>${candidates.length} candidate addition${candidates.length===1?"":"s"}</legend>${candidates.slice(0,40).map(row=>`<label><input type="checkbox" data-living-candidate="${escapeHtml(id)}" value="${escapeHtml(row.record_id)}"> <span>${escapeHtml(row.title || row.record_id)}</span></label>`).join("")}<button type="button" class="secondary-button" data-apply-living="${escapeHtml(id)}">Apply selected</button></fieldset>` : (preview ? '<p class="form-note">No new candidate additions in the current refresh.</p>' : '');
    return `<article class="workspace-project-card" data-living-collection="${escapeHtml(id)}"><div><p class="eyebrow">Living collection${cfg.project_id?` · project linked`:""}</p><h3>${escapeHtml(item.title || id)}</h3><p>${escapeHtml(cfg.query || "No query configured")}</p></div><div class="result-actions"><button type="button" class="secondary-button" data-refresh-living="${escapeHtml(id)}">Refresh preview</button></div>${preview?`<p class="form-note">Refresh ${escapeHtml(preview.refresh_id || "")} · ${Number(preview.existing_match_count||0)} retained matches</p>`:""}${choices}</article>`;
  }).join("");
}

function renderSavedWorkspaces() {
  const snapshot=state.workspaceSnapshot;
  const status=$("#workspace-status"), summary=$("#workspace-summary"), list=$("#workspace-project-list"), searches=$("#workspace-saved-search-list");
  if (!snapshot) {
    if (status) status.textContent=state.session?.authenticated ? "Workspace unavailable" : "Sign in required";
    if (summary) summary.innerHTML="";
    if (list) list.innerHTML='<p class="empty-state">Sign in to load saved projects.</p>';
    if (searches) searches.innerHTML='<p class="empty-state">No saved searches loaded.</p>';
    populateWorkspaceProjectSelect(); populateLivingProjectSelect(); renderLivingCollections();
    return;
  }
  const meta=snapshot.summary || {};
  const livingCount=(snapshot.collections || []).filter(item=>Boolean(item?.metadata?.living_collection?.enabled)).length;
  if (status) status.textContent=`${meta.project_count || 0} projects · ${meta.reference_count || 0} references`;
  if (summary) summary.innerHTML=`<span><strong>${meta.project_count || 0}</strong> projects</span><span><strong>${meta.reference_count || 0}</strong> references</span><span><strong>${meta.saved_search_count || 0}</strong> saved searches</span><span><strong>${livingCount}</strong> living collections</span>`;
  const refs=snapshot.project_references || [];
  const bundles=snapshot.source_bundles || [];
  const projects=snapshot.projects || [];
  if (list) list.innerHTML=projects.length ? projects.map(project=>{
    const projectRefs=refs.filter(x=>x.project_id===project.project_id).length;
    const projectBundles=bundles.filter(x=>x.project_id===project.project_id).length;
    const brief=state.projectBriefs[project.project_id];
    const briefHtml=brief ? `<div class="context-strip"><span><strong>${Number(brief.summary?.living_collection_count||0)}</strong> living collections</span><span><strong>${Number(brief.summary?.research_queue_count||0)}</strong> queued research</span></div>` : '';
    return `<article class="workspace-project-card" data-project-id="${escapeHtml(project.project_id)}"><div><p class="eyebrow">${escapeHtml(project.status || "active")} · ${escapeHtml(project.visibility || "private")}</p><h3>${escapeHtml(project.title || project.project_id)}</h3><p>${escapeHtml(project.research_question || project.description || "No research question yet.")}</p></div><dl><dt>References</dt><dd>${projectRefs}</dd><dt>Bundles</dt><dd>${projectBundles}</dd></dl>${briefHtml}<div class="result-actions"><button type="button" class="text-button" data-use-project="${escapeHtml(project.project_id)}">Use for working set</button><button type="button" class="text-button" data-project-brief="${escapeHtml(project.project_id)}">Load research brief</button></div></article>`;
  }).join("") : '<p class="empty-state">No projects yet. Create your first research project above.</p>';
  const saved=snapshot.saved_searches || [];
  if (searches) searches.innerHTML=saved.length ? saved.slice(0,20).map(item=>`<article class="workspace-saved-search"><strong>${escapeHtml(item.label || item.query)}</strong><span>${escapeHtml(item.query || "")}</span></article>`).join("") : '<p class="empty-state">No saved searches yet.</p>';
  populateWorkspaceProjectSelect(); populateLivingProjectSelect(); renderLivingCollections();
}

async function createLivingCollection(event) {
  event.preventDefault(); const status=$("#workspace-living-status");
  if (!(await ensureWorkspaceSession())) { if(status) status.textContent="Sign in before creating a living collection."; return; }
  const title=$("#workspace-living-title")?.value.trim() || ""; const query=$("#workspace-living-query")?.value.trim() || "";
  if (!title || !query) { if(status) status.textContent="Title and query are required."; return; }
  const payload={title,query,project_id:$("#workspace-living-project")?.value || "",mode:$("#workspace-living-mode")?.value || "hybrid",cross_language:true,limit:50};
  try {
    const result=await api("/living-research/collections",{method:"POST",headers:workspaceCsrfHeaders(),body:JSON.stringify(payload)});
    if(status) status.textContent=`Created ${result.collection?.title || title}. Refresh to preview current additions.`;
    $("#workspace-living-form")?.reset(); await loadSavedWorkspaces();
  } catch (e) { if(status) status.textContent=e.message; }
}

async function refreshLivingCollection(collectionId) {
  const status=$("#workspace-living-status"); if(status) status.textContent="Refreshing living collection…";
  try {
    const result=await api(`/living-research/collections/${encodeURIComponent(collectionId)}/refresh`,{method:"POST",body:JSON.stringify({})});
    state.livingRefreshes[collectionId]=result; renderLivingCollections();
    if(status) status.textContent=`Refresh preview ready: ${result.candidate_addition_count || 0} candidate additions. Nothing was added automatically.`;
  } catch (e) { if(status) status.textContent=e.message; }
}

async function applyLivingCollection(collectionId) {
  const status=$("#workspace-living-status");
  const selected=$$(`[data-living-candidate="${CSS.escape(collectionId)}"]:checked`).map(input=>input.value);
  if (!selected.length) { if(status) status.textContent="Select one or more refresh candidates first."; return; }
  try {
    const result=await api(`/living-research/collections/${encodeURIComponent(collectionId)}/apply`,{method:"POST",headers:workspaceCsrfHeaders(),body:JSON.stringify({selected_record_ids:selected})});
    if(status) status.textContent=`Applied ${result.saved_count || 0} selected records.`;
    delete state.livingRefreshes[collectionId]; await loadSavedWorkspaces(); await refreshLivingCollection(collectionId);
  } catch (e) { if(status) status.textContent=e.message; }
}

async function loadLivingProjectBrief(projectId) {
  const status=$("#workspace-living-status");
  try {
    state.projectBriefs[projectId]=await api(`/living-research/projects/${encodeURIComponent(projectId)}/brief?include_graph=false`);
    renderSavedWorkspaces(); if(status) status.textContent="Project research brief loaded.";
  } catch (e) { if(status) status.textContent=e.message; }
}

async function loadSavedWorkspaces() {
  if (!state.session?.authenticated) {
    state.workspaceSnapshot=null; renderSavedWorkspaces(); return null;
  }
  try {
    const snapshot=await api("/workspaces");
    state.workspaceSnapshot=snapshot;
    renderSavedWorkspaces();
    return snapshot;
  } catch (error) {
    state.workspaceSnapshot=null; renderSavedWorkspaces();
    const status=$("#workspace-status"); if(status) status.textContent=error.message;
    return null;
  }
}

async function ensureWorkspaceSession() {
  if (state.session?.authenticated && state.csrfToken) return true;
  try {
    const session=await api("/session");
    renderSession(session);
    return Boolean(session?.authenticated && state.csrfToken);
  } catch { return false; }
}

async function createWorkspaceProject(event) {
  event.preventDefault();
  const error=$("#workspace-project-error"); if(error) error.hidden=true;
  if (!(await ensureWorkspaceSession())) {
    if(error){error.textContent="Sign in before creating a saved research project.";error.hidden=false;}
    return;
  }
  const title=$("#workspace-project-title")?.value.trim() || "";
  if (!title) return;
  const payload={title,research_question:$("#workspace-project-question")?.value.trim() || "",description:$("#workspace-project-description")?.value.trim() || "",visibility:"private",status:"active"};
  try {
    const project=await api("/workspaces/projects",{method:"POST",headers:workspaceCsrfHeaders(),body:JSON.stringify(payload)});
    state.activeProjectId=project.project_id;
    $("#workspace-project-form")?.reset();
    await loadSavedWorkspaces();
  } catch (e) {
    if(error){error.textContent=e.message;error.hidden=false;}
  }
}

async function saveWorkingSetToProject() {
  const status=$("#working-set-save-status");
  if (!state.workingSet.length) { if(status) status.textContent="Add records to the working set first."; return; }
  if (!(await ensureWorkspaceSession())) { if(status) status.textContent="Sign in to save this working set."; navigate("/account?section=workspaces"); return; }
  const projectId=$("#working-set-project")?.value || state.activeProjectId || "";
  if (!projectId) { if(status) status.textContent="Choose or create a project first."; navigate("/account?section=workspaces"); return; }
  if(status) status.textContent="Saving…";
  try {
    const result=await api(`/workspaces/projects/${encodeURIComponent(projectId)}/working-set`,{method:"POST",headers:workspaceCsrfHeaders(),body:JSON.stringify({records:state.workingSet})});
    state.activeProjectId=projectId;
    if(status) status.textContent=`Saved ${result.saved_count || 0} records to the project.`;
    await loadSavedWorkspaces();
  } catch (e) { if(status) status.textContent=e.message; }
}

async function previewWorkingSetDataset() {
  const target=$("#working-set-dataset-preview");
  const status=$("#working-set-save-status");
  if (!state.workingSet.length) { if(status) status.textContent="Add records to the working set before previewing a dataset."; return; }
  if (target) { target.hidden=false; target.innerHTML='<p class="empty-state">Building structured dataset preview…</p>'; }
  try {
    const records=state.workingSet.map(item=>({record_id:item.id || item.record_id,title:item.title || item.label || item.id,source_type:item.type || item.object_type || "library-record"}));
    const dataset=await api("/structured-evidence/datasets",{method:"POST",body:JSON.stringify({title:"Working set dataset preview",records,fields:["record_id","title","source_type"]})});
    state.structuredDatasetPreview=dataset;
    const cols=(dataset.columns || []).map(c=>c.name);
    const rows=(dataset.rows || []).slice(0,20);
    if (target) target.innerHTML=`<summary>Structured dataset preview · ${Number(dataset.row_count||0)} rows × ${Number(dataset.column_count||0)} columns</summary><div class="form-note">Preview only · not persisted · structure is not an evidence-strength or truth judgment.</div><pre>${escapeHtml(JSON.stringify({dataset_id:dataset.dataset_id,columns:cols,rows,row_provenance:(dataset.row_provenance||[]).slice(0,20)},null,2))}</pre>`;
    if(status) status.textContent="Structured dataset preview created without persistence.";
  } catch (e) { if(target) target.innerHTML=`<summary>Structured dataset preview</summary><p class="error-state">${escapeHtml(e.message)}</p>`; if(status) status.textContent=e.message; }
}

async function previewWorkingSetLiterature() {
  const target=$("#working-set-literature-preview");
  const status=$("#working-set-save-status");
  if (!state.workingSet.length) { if(status) status.textContent="Add records to the working set before analyzing literature."; return; }
  if (target) { target.hidden=false; target.innerHTML='<p class="empty-state">Analyzing scientific literature set…</p>'; }
  try {
    const publications=state.workingSet.map(item=>({record_id:item.id || item.record_id,title:item.title || item.label || item.id,publication_type:item.type || item.object_type || "scientific-publication",source_key:item.source_key || null}));
    const result=await api("/scientific-literature/sets/analyze",{method:"POST",body:JSON.stringify({title:"Working set scientific literature preview",publications})});
    state.scientificLiteraturePreview=result;
    const metrics=result.metrics || {};
    if (target) target.innerHTML=`<summary>Scientific literature preview · ${Number(metrics.unique_publication_count||0)} unique publications</summary><div class="form-note">Preview only · no persistence · citation counts, venues and study-design indicators are not quality or truth judgments.</div><pre>${escapeHtml(JSON.stringify({literature_set_id:result.literature_set_id,metrics,duplicates:(result.duplicates||[]).slice(0,20),publications:(result.publications||[]).slice(0,20).map(x=>({record_id:x.record_id,title:x.title,identifiers:x.identifiers,study_design_indicators:x.study_design_indicators,correction_retraction:x.correction_retraction}))},null,2))}</pre>`;
    if(status) status.textContent="Scientific literature preview created without persistence.";
  } catch (e) { if(target) target.innerHTML=`<summary>Scientific literature preview</summary><p class="error-state">${escapeHtml(e.message)}</p>`; if(status) status.textContent=e.message; }
}

async function saveCurrentResearchSearch() {
  if (!(await ensureWorkspaceSession())) { navigate("/account?section=workspaces"); return; }
  const params=researchParams();
  const query=params.q || "";
  if (!query) { $("#research-interface-status").textContent="Enter a query before saving a search."; return; }
  const payload={label:query.slice(0,180),query,scope:"all",filters:Object.fromEntries(Object.entries(params).filter(([k])=>!["q","limit","offset"].includes(k)))};
  try {
    await api("/workspaces/saved-searches",{method:"POST",headers:workspaceCsrfHeaders(),body:JSON.stringify(payload)});
    $("#research-interface-status").textContent="Search saved to your Library workspace.";
    await loadSavedWorkspaces();
  } catch (e) { $("#research-interface-status").textContent=e.message; }
}

async function loadResearchBootstrap() {
  if (state.researchBootstrap) { loadWorkingSet(); return; }
  const note=$("#research-interface-status");
  try {
    const data=await api("/research-interface/bootstrap"); state.researchBootstrap=data;
    const facets=data.facets || {};
    researchFacetOptions($("#research-object-type"), facets.object_types || facets.object_type || []);
    researchFacetOptions($("#research-source"), facets.sources || facets.source_keys || facets.source_key || []);
    if (note) note.textContent=`Research interface online · ${data.search?.default_mode || "hybrid"} discovery · API v1`;
  } catch (error) { if (note) note.innerHTML=`<span class="error-state">${escapeHtml(error.message)}</span>`; }
  loadWorkingSet();
}
function researchParams() {
  const p={q:$("#research-query")?.value.trim() || "",mode:$("#research-mode")?.value || "hybrid",sort:$("#research-sort")?.value || "relevance",object_type:$("#research-object-type")?.value || "",source_key:$("#research-source")?.value || "",topic:$("#research-topic")?.value.trim() || "",year_from:$("#research-year-from")?.value || "",year_to:$("#research-year-to")?.value || "",limit:String(state.limit),offset:String(state.researchOffset)};
  return Object.fromEntries(Object.entries(p).filter(([,v])=>v!=="" && v!=null));
}
function renderResearchResults(items,append=false) {
  const target=$("#research-results"); if (!target) return; if (!append) target.innerHTML="";
  if (!items.length && !append) { target.innerHTML='<p class="empty-state">No records matched this research scope.</p>'; return; }
  const frag=document.createDocumentFragment();
  for (const record of items) {
    const id=resultId(record); const card=document.createElement("article"); card.className="result-card research-result-card";
    const title=escapeHtml(resultTitle(record)); const summary=escapeHtml(text(resultSummary(record)).slice(0,700)); const type=escapeHtml(objectType(record));
    card.innerHTML=`<div class="result-meta"><span>${type}</span>${record.year?`<span>${escapeHtml(record.year)}</span>`:""}</div><h3>${id?`<a href="/record/${encodeURIComponent(id)}" data-app-link>${title}</a>`:title}</h3><p>${summary}</p>${id?`<div class="result-actions"><button type="button" class="secondary-button" data-add-working="${escapeHtml(id)}">Add to working set</button><a href="/record/${encodeURIComponent(id)}" data-app-link class="text-button">Open research context</a></div>`:""}`;
    card.dataset.record=JSON.stringify({id,title:resultTitle(record),type:objectType(record)}); frag.append(card);
  }
  target.append(frag);
}
async function researchSearch({append=false}={}) {
  const target=$("#research-results"); if (!target) return; if (!append) state.researchOffset=0; target.setAttribute("aria-busy","true");
  try {
    const params=researchParams();
    const payload={...params,limit:Number(params.limit || state.limit),offset:Number(params.offset || state.researchOffset),cross_language:true,rerank:"neural"};
    if (payload.year_from) payload.year_from=Number(payload.year_from); if (payload.year_to) payload.year_to=Number(payload.year_to);
    const projectId=state.session?.authenticated ? (state.activeProjectId || "") : "";
    const endpoint=projectId ? `/discovery/projects/${encodeURIComponent(projectId)}/search` : "/discovery/search";
    const data=await api(endpoint,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});
    const result=data || {}; const items=result.results || [];
    state.researchTotal=Number(result.total ?? items.length); renderResearchResults(items,append);
    $("#research-result-count").textContent=`${state.researchTotal.toLocaleString()} ${state.researchTotal===1?"result":"results"}`;
    const note=$("#research-interface-status"); if(note){const repCount=(result.query_representations||[]).length;note.textContent=`Advanced discovery · ${repCount || 1} query representation${repCount===1?"":"s"} · ${result.cross_language_expansion_state || "original query"}${projectId?" · project-aware":""}`;}
    state.researchOffset += items.length; $("#research-load-more").hidden = !(items.length === state.limit && (result.total == null || state.researchOffset < Number(result.total)));
  } catch (error) { if (!append) target.innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`; }
  finally { target.removeAttribute("aria-busy"); }
}
function clearResearchScope() {
  for (const selector of ["#research-query","#research-topic","#research-year-from","#research-year-to"]) { const el=$(selector); if(el) el.value=""; }
  if ($("#research-object-type")) $("#research-object-type").value=""; if ($("#research-source")) $("#research-source").value="";
  if ($("#research-mode")) $("#research-mode").value="hybrid"; if ($("#research-sort")) $("#research-sort").value="relevance";
  state.researchOffset=0; $("#research-results").innerHTML='<p class="empty-state">Enter a research question or browse with filters.</p>'; $("#research-result-count").textContent=""; $("#research-load-more").hidden=true;
}

async function search({append=false}={}) {
  const q=$("#query").value.trim(); const mode=$("#search-mode").value; if (!append) state.offset=0;
  state.query=q; state.mode=mode; $("#search-results").setAttribute("aria-busy","true");
  try {
    const params=new URLSearchParams({q,mode,limit:String(state.limit),offset:String(state.offset)}); const data=await api(`/search?${params}`);
    const items=data.items || []; state.total=Number(data.page?.total ?? items.length); state.lastSearch=data;
    renderResults(items,append); $("#result-count").textContent=`${state.total.toLocaleString()} ${state.total===1?"result":"results"}`;
    $("#search-meta").innerHTML=`<dt>Mode</dt><dd>${escapeHtml(mode)}</dd><dt>Offset</dt><dd>${state.offset}</dd><dt>API</dt><dd>v1</dd>`;
    state.offset += items.length; $("#load-more").hidden = !data.page?.has_more;
  } catch (error) { if (!append) $("#search-results").innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`; }
  finally { $("#search-results").removeAttribute("aria-busy"); }
}

async function loadEvidenceNavigation(id) {
  const host=$("#evidence-navigation"); if (!host) return;
  try {
    const graph=await api(`/research-graph/records/${encodeURIComponent(id)}/neighborhood?depth=1&limit=80&include_core=true`);
    const neighbors=(graph.nodes||[]).filter(node=>String(node.record_id||node.id||"")!==String(id)).slice(0,24);
    const family=graph.edge_family_counts || {};
    const cards=neighbors.length ? neighbors.map(node=>{const rid=node.record_id||node.id;const label=node.title||node.name||node.label||rid;return `<li><a href="/record/${encodeURIComponent(rid)}" data-app-link>${escapeHtml(label)}</a></li>`;}).join("") : '<li>No connected public records in this neighborhood.</li>';
    host.innerHTML=`<summary>Evidence navigation · ${graph.node_count||0} nodes · ${graph.edge_count||0} edges</summary><p class="form-note">Graph links describe provenance, citation or declared relationships. Connectivity is not a truth or causality judgment.</p><div class="context-strip"><span><strong>${Number(family.citation||0)}</strong> citation edges</span><span><strong>${Number(family.relationship||0)}</strong> relationship edges</span></div><ul>${cards}</ul><details><summary>Graph contract</summary><pre>${escapeHtml(JSON.stringify({graph_fingerprint_sha256:graph.graph_fingerprint_sha256,edge_family_counts:graph.edge_family_counts,edge_kind_counts:graph.edge_kind_counts,guardrails:graph.guardrails},null,2))}</pre></details>`;
  } catch (error) { host.innerHTML=`<summary>Evidence navigation unavailable</summary><p class="error-state">${escapeHtml(error.message)}</p>`; }
}

async function loadRecord(id) {
  $("#reader-id").textContent=id; const target=$("#reader"); target.innerHTML='<p class="empty-state">Loading record…</p>';
  try {
    const context=await api(`/research-interface/records/${encodeURIComponent(id)}?include_body=true&evidence_depth=1&evidence_limit=120`);
    const obj=context.research_object || {}; const r={...(obj.descriptive||{}),...(obj.source||{}),...(obj.publication||{}),...(obj.identity||{}),body_text:obj.content?.body_text || "",chunks:obj.content?.chunks || [],metadata:obj.metadata || {}};
    api(`/seo/records/${encodeURIComponent(id)}`).then(applySeoDescriptor).catch(()=>{});
    const title=escapeHtml(resultTitle(r)); const body=pick(r,["body_text","body","content","abstract","summary","description"]) || "No readable body is available for this record.";
    const source=pick(r,["source_name","source","source_key"]); const type=objectType(r);
    const versions=context.provenance?.record_versions?.length || 0; const citations=context.citations?.count || 0; const nodes=context.evidence_graph?.node_count || 0; const edges=context.evidence_graph?.edge_count || 0;
    target.innerHTML=`<header><p class="eyebrow">${escapeHtml(type)}</p><h1>${title}</h1><div class="reader-meta">${source?`<span>Source: ${escapeHtml(source)}</span>`:""}${r.published_at?`<span>Published: ${escapeHtml(r.published_at)}</span>`:""}</div></header><aside class="context-strip"><span><strong>${versions}</strong> versions</span><span><strong>${citations}</strong> citations</span><span><strong>${nodes}</strong> evidence nodes</span><span><strong>${edges}</strong> evidence edges</span><button type="button" class="secondary-button" data-reader-add="${escapeHtml(id)}">Add to working set</button></aside><div class="reader-body">${escapeHtml(body).split(/\n{2,}/).map(p=>`<p>${p.replace(/\n/g,"<br>")}</p>`).join("")}</div><details id="evidence-navigation"><summary>Evidence navigation</summary><p class="empty-state">Loading graph…</p></details><details><summary>Research context</summary><pre>${escapeHtml(JSON.stringify({provenance:context.provenance,citations:context.citations,evidence_graph:context.evidence_graph},null,2))}</pre></details><details><summary>Research object metadata</summary><pre>${escapeHtml(JSON.stringify(obj,null,2))}</pre></details>`;
    $("[data-reader-add]")?.addEventListener("click",()=>{addToWorkingSet({record_id:id,title:resultTitle(r),object_type:type});});
    loadEvidenceNavigation(id);
  } catch (error) { target.innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`; }
}

async function loadCapabilities() {
  const target=$("#capability-grid"); if (target.dataset.loaded) return; target.innerHTML='<p class="empty-state">Loading capabilities…</p>';
  try {
    const data=await api('/capabilities'); const families=data.families || {}; target.innerHTML="";
    for (const [name, value] of Object.entries(families)) {
      const resources=(value.resources||[]).map(x=>`<li>${escapeHtml(x)}</li>`).join("");
      target.insertAdjacentHTML('beforeend',`<article class="info-card"><p class="eyebrow">${value.authority?escapeHtml(value.authority):"Library API"}</p><h2>${escapeHtml(name.replaceAll('-',' '))}</h2><ul>${resources}</ul></article>`);
    }
    target.dataset.loaded="1";
  } catch (error) { target.innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`; }
}

async function loadSystem() {
  const target=$("#system-grid"); target.innerHTML='<p class="empty-state">Checking runtime…</p>';
  const checks=[['API','/readiness'],['Runtime authority','/runtime-authority'],['Federation','/federation/readiness'],['Global federation II','/federation/global/readiness'],['Research graph','/research-graph/readiness'],['Living research','/living-research/readiness'],['Structured evidence','/structured-evidence/readiness'],['Scientific literature','/scientific-literature/readiness'],['Artifacts','/artifacts/readiness'],['Pipelines','/pipelines/readiness'],['Compute','/compute/readiness'],['Web application','/web-application/readiness']];
  const results=await Promise.all(checks.map(async ([label,path])=>{try{return {label,path,data:await api(path),ok:true};}catch(error){return {label,path,error:error.message,ok:false};}}));
  target.innerHTML="";
  for (const r of results) {
    const stateLabel=r.ok ? (r.data.state || (r.data.ok ? "ready" : "online")) : "unavailable";
    target.insertAdjacentHTML('beforeend',`<article class="info-card status-card"><div class="status-row"><h2>${escapeHtml(r.label)}</h2><span class="state ${r.ok?'good':'bad'}">${escapeHtml(stateLabel)}</span></div><code>${escapeHtml(r.path)}</code>${r.error?`<p>${escapeHtml(r.error)}</p>`:""}</article>`);
  }
}

async function bootstrap() {
  if (new URLSearchParams(location.search).get('embed') === '1') document.body.classList.add('embed-mode');
  try { const data=await api('/service'); setApiStatus(true,`API ${data.api_version || '1.0'} online`); }
  catch { setApiStatus(false,'API unavailable'); }
  route();
}

$("#research-form")?.addEventListener("submit", event => { event.preventDefault(); navigate("/research"); researchSearch(); });
$("#research-load-more")?.addEventListener("click",()=>researchSearch({append:true}));
$("#research-clear")?.addEventListener("click",clearResearchScope);
$("#working-set-clear")?.addEventListener("click",()=>{state.workingSet=[];saveWorkingSet();});
$("#working-set-save")?.addEventListener("click",saveWorkingSetToProject);
$("#working-set-dataset-preview-button")?.addEventListener("click",previewWorkingSetDataset);
$("#working-set-literature-preview-button")?.addEventListener("click",previewWorkingSetLiterature);
$("#research-save-search")?.addEventListener("click",saveCurrentResearchSearch);
$("#workspace-project-form")?.addEventListener("submit",createWorkspaceProject);
$("#workspace-living-form")?.addEventListener("submit",createLivingCollection);
$("#search-form").addEventListener("submit", event => { event.preventDefault(); navigate("/search"); search(); });
$("#load-more").addEventListener("click",()=>search({append:true}));
$("#reader-back").addEventListener("click",()=>history.length>1?history.back():navigate("/search"));
$("#login-form")?.addEventListener("submit",login);
window.addEventListener("hashchange",route);
window.addEventListener("popstate",route);
document.addEventListener('click',event=>{const a=event.target.closest('a[data-app-link], nav a'); if(!a) return; const url=new URL(a.href,location.href); if(url.origin!==location.origin) return; event.preventDefault(); navigate(url.pathname+url.search);});
document.addEventListener('click',event=>{
  const refresh=event.target.closest('[data-refresh-living]'); if(refresh){event.preventDefault();refreshLivingCollection(refresh.dataset.refreshLiving);return;}
  const apply=event.target.closest('[data-apply-living]'); if(apply){event.preventDefault();applyLivingCollection(apply.dataset.applyLiving);return;}
  const brief=event.target.closest('[data-project-brief]'); if(brief){event.preventDefault();loadLivingProjectBrief(brief.dataset.projectBrief);return;}
});
bootstrap();


function renderSession(session) {
  const target=$("#session-state"); const login=$("#login-card");
  if (!session?.authenticated) {
    state.session=null; state.csrfToken=null; state.workspaceSnapshot=null; state.activeProjectId=null; login.hidden=false;
    target.innerHTML='<p class="empty-state">You are not signed in. Public Library search and reading remain available.</p>';
    renderSavedWorkspaces();
    return;
  }
  state.session=session; if (session.csrf_token) state.csrfToken=session.csrf_token; login.hidden=true;
  loadSavedWorkspaces();
  const identity=session.identity || {}; const roles=(session.roles||[]).map(escapeHtml).join(', ') || 'none';
  const scopes=(session.scopes||[]).map(x=>`<li><code>${escapeHtml(x)}</code></li>`).join('');
  target.innerHTML=`<p class="eyebrow">Authenticated</p><h3>${escapeHtml(identity.display_name || identity.handle || identity.identity_id)}</h3><dl class="account-meta"><dt>Handle</dt><dd>${escapeHtml(identity.handle || '')}</dd><dt>Roles</dt><dd>${roles}</dd><dt>Expires</dt><dd>${escapeHtml(session.expires_at || '')}</dd></dl><h3>Effective scopes</h3><ul>${scopes}</ul><button id="logout-button" class="secondary-button">Sign out</button>`;
  $("#logout-button")?.addEventListener('click', logout);
}

async function loadSession() {
  const target=$("#session-state"); if (!target) return; target.innerHTML='<p class="empty-state">Checking session…</p>';
  try { renderSession(await api('/session')); }
  catch (error) { target.innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`; }
}

async function login(event) {
  event.preventDefault(); const error=$("#login-error"); error.hidden=true;
  const handle=$("#login-handle").value.trim(); const password=$("#login-password").value;
  try {
    const session=await api('/session/login',{method:'POST',body:JSON.stringify({handle,password,client_label:'library-web-v2.9.0'})});
    $("#login-password").value=''; renderSession(session);
  } catch (e) { error.textContent=e.message; error.hidden=false; }
}

async function logout() {
  try {
    await api('/session/logout',{method:'POST',headers: state.csrfToken ? {'X-SC-CSRF-Token':state.csrfToken}: {}});
  } catch (e) { /* cookie is still cleared on the next valid logout/session expiry */ }
  state.session=null; state.csrfToken=null; state.workspaceSnapshot=null; state.activeProjectId=null; renderSavedWorkspaces(); await loadSession();
}
