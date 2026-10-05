const config = window.SC_LIBRARY_WEB_CONFIG || { apiBase: "/api/library/v1", webVersion: "2.24.0" };
const API = String(config.apiBase || "/api/library/v1").replace(/\/$/, "");
const state = { offset: 0, limit: 20, query: "", mode: "hybrid", total: 0, lastSearch: null, session: null, csrfToken: null, researchOffset: 0, researchTotal: 0, researchBootstrap: null, navigationBootstrap: null, researchNavigationMode: "overview", workingSet: [], workspaceSnapshot: null, activeProjectId: null, livingRefreshes: {}, projectBriefs: {}, structuredDatasetPreview: null, scientificLiteraturePreview: null, researchPackageExportPreview: null, institutionalRepositoryPreview: null, archiveBootstrap: null, archiveSources: [], archiveSearchResult: null, criticismBootstrap: null, timelineBootstrap: null, timelineEvents: [], notesBootstrap: null, researchNotes: [], citationsBootstrap: null, bibliographyItems: [], corpusBootstrap: null, entitiesBootstrap: null, synthesisBootstrap: null, investigationBootstrap: null, evidenceMatrixBootstrap: null, statisticalEvidenceBootstrap: null, geospatialBootstrap: null };

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
  const geospatialResearch = name === "research" && rest[0] === "geospatial";
  const dataResearch = name === "research" && rest[0] === "data";
  const evidenceResearch = name === "research" && rest[0] === "evidence";
  const investigationResearch = name === "research" && rest[0] === "investigation";
  const synthesisResearch = name === "research" && rest[0] === "synthesis";
  const entitiesResearch = name === "research" && rest[0] === "entities";
  const corpusResearch = name === "research" && rest[0] === "corpus";
  const citationsResearch = name === "research" && rest[0] === "citations";
  const notesResearch = name === "research" && rest[0] === "notes";
  const timelineResearch = name === "research" && rest[0] === "archives" && rest[1] === "timeline";
  const criticismResearch = name === "research" && rest[0] === "archives" && rest[1] === "compare";
  const archiveResearch = name === "research" && rest[0] === "archives" && !criticismResearch && !timelineResearch;
  const view = geospatialResearch ? "geospatial" : dataResearch ? "data" : evidenceResearch ? "evidence" : investigationResearch ? "investigation" : synthesisResearch ? "synthesis" : entitiesResearch ? "entities" : corpusResearch ? "corpus" : citationsResearch ? "citations" : notesResearch ? "notes" : timelineResearch ? "timeline" : criticismResearch ? "criticism" : archiveResearch ? "archives" : compatibilityResearch ? "research" : ["research","system","account"].includes(name) ? name : name === "record" ? "record" : "research";
  $$(".view").forEach(el => { el.hidden = el.dataset.view !== view; });
  const navView=["archives","criticism","timeline","notes","citations","corpus","entities","synthesis","investigation","evidence","data","geospatial"].includes(view) ? "research" : view;
  $$('[data-nav]').forEach(a => a.setAttribute('aria-current', a.dataset.nav===navView ? 'page' : 'false'));
  updatePublicMetadata(view, rest);
  if (view === "research") loadUnifiedNavigation(resolveUnifiedResearchMode(name));
  if (view === "archives") loadHistoricalArchiveWorkspace();
  if (view === "criticism") loadPrimarySourceCriticismWorkspace();
  if (view === "timeline") loadHistoricalEventTimelineWorkspace();
  if (view === "notes") loadResearchNotesWorkspace();
  if (view === "citations") loadCitationWorkspace();
  if (view === "corpus") loadCorpusWorkspace();
  if (view === "entities") loadEntityPlaceWorkspace();
  if (view === "synthesis") loadResearchSynthesisWorkspace();
  if (view === "investigation") loadResearchInvestigationWorkspace();
  if (view === "evidence") loadEvidenceMatrixWorkspace();
  if (view === "data") loadStatisticalEvidenceWorkspace();
  if (view === "geospatial") loadGeospatialResearchWorkspace();
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
  let path=view === "research" ? "/research" : view === "archives" ? "/research/archives" : view === "criticism" ? "/research/archives/compare" : view === "timeline" ? "/research/archives/timeline" : view === "notes" ? "/research/notes" : view === "citations" ? "/research/citations" : view === "corpus" ? "/research/corpus" : view === "entities" ? "/research/entities" : view === "synthesis" ? "/research/synthesis" : view === "investigation" ? "/research/investigation" : view === "evidence" ? "/research/evidence" : view === "data" ? "/research/data" : view === "geospatial" ? "/research/geospatial" : `/${view}`;
  if (view === "record" && rest.length) path=`/record/${encodeURIComponent(decodeURIComponent(rest.join("/")))}`;
  const canonical=document.querySelector('link[rel="canonical"]') || document.head.appendChild(Object.assign(document.createElement('link'),{rel:'canonical'}));
  canonical.href=origin + (path === "/search" ? path : path);
  const robots=document.querySelector('meta[name="robots"]') || document.head.appendChild(Object.assign(document.createElement('meta'),{name:'robots'}));
  robots.content=["research","archives","criticism","timeline","notes","citations","corpus","entities","synthesis","investigation","evidence","data","geospatial","search","system","account"].includes(view) ? "noindex,follow" : "index,follow";
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

function workingSetExportPayload() {
  const records=state.workingSet.map(item=>({record_id:item.id || item.record_id,title:item.title || item.label || item.id,source_type:item.type || item.object_type || "library-record"}));
  const payload={title:"Working set research package",description:"Portable reproducible export prepared from the browser working set.",records};
  if (state.structuredDatasetPreview) payload.datasets=[state.structuredDatasetPreview];
  if (state.scientificLiteraturePreview) payload.scientific_literature=state.scientificLiteraturePreview;
  return payload;
}

async function previewWorkingSetExport() {
  const target=$("#working-set-export-preview");
  const status=$("#working-set-save-status");
  if (!state.workingSet.length) { if(status) status.textContent="Add records to the working set before preparing an export."; return; }
  if (target) { target.hidden=false; target.innerHTML='<p class="empty-state">Preparing reproducible export preview…</p>'; }
  try {
    const result=await api("/research-package-publishing/preview",{method:"POST",body:JSON.stringify(workingSetExportPayload())});
    state.researchPackageExportPreview=result;
    if (target) target.innerHTML=`<summary>Reproducible export · ${Number(result.file_count||0)} files</summary><div class="form-note">Preview only · deterministic ZIP plan · no persistence or external publication.</div><pre>${escapeHtml(JSON.stringify({export_fingerprint_sha256:result.export_fingerprint_sha256,filename:result.filename,archive_sha256:result.archive_sha256,archive_byte_length:result.archive_byte_length,files:result.files},null,2))}</pre>`;
    if(status) status.textContent="Reproducible export preview created without persistence.";
  } catch (e) { if(target) target.innerHTML=`<summary>Reproducible export</summary><p class="error-state">${escapeHtml(e.message)}</p>`; if(status) status.textContent=e.message; }
}

async function downloadWorkingSetExport() {
  const status=$("#working-set-save-status");
  if (!state.workingSet.length) { if(status) status.textContent="Add records to the working set before exporting."; return; }
  if(status) status.textContent="Building deterministic portable ZIP…";
  try {
    const result=await api("/research-package-publishing/export",{method:"POST",body:JSON.stringify(workingSetExportPayload())});
    const binary=atob(result.archive_base64 || "");
    const bytes=new Uint8Array(binary.length);
    for(let i=0;i<binary.length;i++) bytes[i]=binary.charCodeAt(i);
    const blob=new Blob([bytes],{type:result.media_type || "application/zip"});
    const url=URL.createObjectURL(blob); const a=document.createElement("a"); a.href=url; a.download=result.filename || "research-package-reproducible-export.zip"; document.body.append(a); a.click(); a.remove(); setTimeout(()=>URL.revokeObjectURL(url),0);
    if(status) status.textContent=`Portable ZIP prepared · SHA-256 ${result.archive_sha256 || "unavailable"}`;
  } catch (e) { if(status) status.textContent=e.message; }
}

async function previewInstitutionalRepositories() {
  const target=$("#institutional-repository-preview");
  const status=$("#working-set-save-status");
  const query=$("#research-query")?.value.trim() || "";
  if (!query) { if(status) status.textContent="Enter a research query before searching institutional repositories."; return; }
  if (target) { target.hidden=false; target.innerHTML='<p class="empty-state">Searching configured institutional repositories...</p>'; }
  try {
    const result=await api("/institutional-repositories/search",{method:"POST",body:JSON.stringify({q:query,limit_per_source:5})});
    state.institutionalRepositoryPreview=result;
    const sourceStates=Object.fromEntries(Object.entries(result.source_status||{}).map(([k,v])=>[k,v.state]));
    if (target) target.innerHTML=`<summary>Institutional repositories - ${Number(result.record_count||0)} consolidated records</summary><div class="form-note">Live bounded metadata federation - repository visibility is not endorsement, access entitlement, reuse permission, evidence quality, or truth.</div><pre>${escapeHtml(JSON.stringify({federation_result_id:result.federation_result_id,network_state:result.network_state,source_states:sourceStates,duplicate_observation_consolidation_count:result.duplicate_observation_consolidation_count,records:(result.records||[]).slice(0,30).map(x=>({identity_key:x.identity_key,title:x.title,institution:x.institution,repository:x.repository,doi:x.doi,source_keys:x.source_keys,source_url:x.source_url,license:x.license}))},null,2))}</pre>`;
    if(status) status.textContent="Institutional repository federation completed. No records were imported.";
  } catch (e) { if(target) target.innerHTML=`<summary>Institutional repositories</summary><p class="error-state">${escapeHtml(e.message)}</p>`; if(status) status.textContent=e.message; }
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


function archivePayloadFromEditor() {
  const sourceKey=$("#archive-source-repository-key")?.value.trim() || "";
  return {
    title: $("#archive-source-title")?.value.trim() || "",
    source_type: $("#archive-source-type")?.value || "other-primary",
    date: $("#archive-source-date")?.value.trim() || "",
    repository: {
      name: $("#archive-source-repository")?.value.trim() || "",
      source_key: sourceKey || null,
    },
    archival_context: {
      collection: $("#archive-source-collection")?.value.trim() || "",
      series: $("#archive-source-series")?.value.trim() || "",
      folder: $("#archive-source-folder")?.value.trim() || "",
      shelfmark: $("#archive-source-shelfmark")?.value.trim() || "",
    },
    original_language: $("#archive-source-language")?.value.trim() || "",
    digital_surrogate: { url: $("#archive-source-url")?.value.trim() || "" },
    notes: $("#archive-source-notes")?.value.trim() || "",
  };
}

function archiveRecordToSource(record={}) {
  return {
    title: record.title || record.name || record.label || "Untitled archival record",
    source_type: "other-primary",
    creators: record.authors || record.creators || [],
    date: record.published_at || record.date || "",
    repository: {
      name: record.institution || record.repository || record.source_name || "",
      source_key: record.source_key || (record.source_keys || [])[0] || null,
    },
    archival_context: {
      collection: record.collection || "",
      series: record.series || "",
      folder: record.folder || "",
      shelfmark: record.shelfmark || record.call_number || record.reference_code || "",
    },
    digital_surrogate: { url: record.source_url || record.url || "" },
    identifiers: record.persistent_id ? [{scheme:"repository",value:record.persistent_id}] : [],
    notes: "Added from an explicit institutional repository search result.",
  };
}

function archiveOutput(label, data) {
  const target=$("#archive-output");
  if (!target) return;
  target.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;
}

function renderArchiveSources() {
  const target=$("#archive-source-list"); if (!target) return;
  const sources=state.archiveSources || [];
  if (!sources.length) { target.innerHTML='<p class="empty-state">Add sources from archive results or the source editor.</p>'; return; }
  target.innerHTML=sources.map((source,index)=>{
    const date=source.creation_date?.display || source.date || "undated";
    const shelf=source.archival_context?.shelfmark || "no reference code";
    const repo=source.repository?.name || source.repository?.source_key || "repository not specified";
    return `<article class="archive-source-row"><div><strong>${escapeHtml(source.title || "Untitled source")}</strong><p>${escapeHtml(date)} · ${escapeHtml(repo)} · ${escapeHtml(shelf)}</p></div><div><button type="button" class="text-button" data-archive-inspect="${index}">Inspect</button><button type="button" class="text-button" data-archive-remove="${index}">Remove</button></div></article>`;
  }).join("");
}

function populateArchiveBootstrap(data) {
  state.archiveBootstrap=data;
  const readiness=data.readiness || {};
  const status=$("#archive-readiness");
  if (status) status.innerHTML=`<span><strong>${escapeHtml(readiness.state || "unknown")}</strong> workspace</span><span><strong>${Number(readiness.repository_profile_count || 0)}</strong> repository profiles</span><span>Web ${escapeHtml(data.web_version || config.webVersion || "")}</span><span>No automatic import</span>`;
  const select=$("#archive-source-type");
  if (select) {
    select.innerHTML=(data.source_types || []).map(row=>`<option value="${escapeHtml(row.key)}">${escapeHtml(row.label)}</option>`).join("") || '<option value="other-primary">Other primary source</option>';
  }
  const keys=$("#archive-source-keys");
  if (keys && !keys.getAttribute("list")) {
    const id="archive-repository-keys"; keys.setAttribute("list",id);
    const datalist=document.createElement("datalist"); datalist.id=id;
    datalist.innerHTML=(data.repositories || []).map(row=>`<option value="${escapeHtml(row.source_key)}">${escapeHtml(row.institution || row.repository || row.source_key)}</option>`).join("");
    keys.insertAdjacentElement("afterend",datalist);
  }
}

async function loadHistoricalArchiveWorkspace() {
  if (state.archiveBootstrap) { populateArchiveBootstrap(state.archiveBootstrap); renderArchiveSources(); return; }
  const status=$("#archive-readiness"); if (status) status.innerHTML='<span>Loading archive workspace…</span>';
  try {
    const data=await api('/historical-archives/workspace/bootstrap');
    populateArchiveBootstrap(data); renderArchiveSources();
  } catch (error) {
    if (status) status.innerHTML=`<span class="error-state">${escapeHtml(error.message)}</span>`;
  }
}

async function runHistoricalArchiveSearch(event) {
  event?.preventDefault();
  const q=$("#archive-query")?.value.trim() || "";
  if (!q) return;
  const sourceKeys=($("#archive-source-keys")?.value || "").split(",").map(x=>x.trim()).filter(Boolean);
  const startRaw=$("#archive-start-year")?.value; const endRaw=$("#archive-end-year")?.value;
  const payload={q,source_keys:sourceKeys,execute:true,limit_per_source:8,max_sources:20};
  if (startRaw) payload.start_year=Number(startRaw);
  if (endRaw) payload.end_year=Number(endRaw);
  const target=$("#archive-search-results"); const stateLabel=$("#archive-search-state");
  target.innerHTML='<p class="empty-state">Searching configured repositories…</p>'; if(stateLabel) stateLabel.textContent='Running explicit search…';
  try {
    const data=await api('/historical-archives/workspace/search',{method:'POST',body:JSON.stringify(payload)});
    state.archiveSearchResult=data;
    const records=data.result?.records || [];
    if(stateLabel) stateLabel.textContent=`${data.execution_state || "complete"} · ${records.length} records`;
    if(!records.length) {
      target.innerHTML=`<p class="empty-state">No records returned.${data.error?` ${escapeHtml(data.error)}`:""}</p><details><summary>Search plan</summary><pre>${escapeHtml(JSON.stringify(data.plan,null,2))}</pre></details>`;
      return;
    }
    target.innerHTML=records.map((record,index)=>`<article class="research-result"><div><p class="eyebrow">${escapeHtml((record.source_keys || [record.source_key]).filter(Boolean).join(", "))}</p><h3>${escapeHtml(record.title || "Untitled archival record")}</h3><p>${escapeHtml(record.published_at || record.date || "Date not supplied")}</p></div><button type="button" class="secondary-button" data-archive-add-result="${index}">Add source</button></article>`).join("") + `<details><summary>Search plan & provenance</summary><pre>${escapeHtml(JSON.stringify({plan:data.plan,source_status:data.result?.source_status,reproducibility:data.result?.reproducibility},null,2))}</pre></details>`;
  } catch(error) {
    if(stateLabel) stateLabel.textContent='Search unavailable';
    target.innerHTML=`<p class="error-state">${escapeHtml(error.message)}</p>`;
  }
}

async function addHistoricalArchiveSource(event) {
  event?.preventDefault();
  const payload=archivePayloadFromEditor();
  if(!payload.title) return;
  try {
    const data=await api('/historical-archives/workspace/source',{method:'POST',body:JSON.stringify({source:payload})});
    state.archiveSources.push(data.source); renderArchiveSources(); archiveOutput('Source criticism',data);
    $("#archive-source-form")?.reset();
    populateArchiveBootstrap(state.archiveBootstrap || {});
  } catch(error) { archiveOutput('Source error',{error:error.message}); }
}

async function addArchiveSearchResult(index) {
  const record=state.archiveSearchResult?.result?.records?.[index]; if(!record) return;
  try {
    const data=await api('/historical-archives/workspace/source',{method:'POST',body:JSON.stringify({source:archiveRecordToSource(record)})});
    state.archiveSources.push(data.source); renderArchiveSources(); archiveOutput('Source added from repository result',data);
  } catch(error) { archiveOutput('Source error',{error:error.message}); }
}

async function runArchiveWorkspaceAction(kind) {
  const sources=state.archiveSources || [];
  const project_id=$("#archive-project-id")?.value.trim() || null;
  const research_question=$("#archive-research-question")?.value.trim() || null;
  const routes={compare:'/historical-archives/workspace/compare',timeline:'/historical-archives/workspace/timeline',packet:'/historical-archives/workspace/packet',handoff:'/historical-archives/workspace/handoff'};
  const labels={compare:'Primary-source comparison',timeline:'Historical timeline',packet:'Research packet preview',handoff:'Ingestion handoff preview'};
  if(kind==='compare' && sources.length<2){archiveOutput(labels[kind],{error:'Add at least two sources before comparing.'});return;}
  if(kind!=='compare' && !sources.length){archiveOutput(labels[kind],{error:'Add at least one source first.'});return;}
  try {
    const data=await api(routes[kind],{method:'POST',body:JSON.stringify({sources,project_id,research_question})});
    archiveOutput(labels[kind],data);
  } catch(error) { archiveOutput(labels[kind],{error:error.message}); }
}


function criticismOutput(label,data){const t=$("#criticism-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function renderCriticismSources(){const t=$("#criticism-source-list");if(!t)return;const sources=state.archiveSources||[];if(!sources.length){t.innerHTML='<p class="empty-state">No in-browser archival sources are loaded. Open Archives, add sources, then return here.</p>';return;}t.innerHTML=sources.map((s,i)=>`<article class="archive-source-row"><div><strong>${escapeHtml(s.title||"Untitled source")}</strong><p>${escapeHtml(s.creation_date?.display||"undated")} · ${escapeHtml(s.repository?.name||"repository not specified")}</p></div><span>${i+1}</span></article>`).join("");}
async function loadPrimarySourceCriticismWorkspace(){renderCriticismSources();const status=$("#criticism-readiness");try{const data=state.criticismBootstrap||await api('/historical-archives/source-criticism/bootstrap');state.criticismBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span><strong>${Number(data.dimensions?.length||0)}</strong> criticism dimensions</span><span>No scoring or auto-adjudication</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
function parseCriticismJson(selector,fallback=[]){const raw=$(selector)?.value.trim()||"";if(!raw)return fallback;const value=JSON.parse(raw);if(!Array.isArray(value))throw new Error('Expected a JSON array.');return value;}
async function buildCriticismMatrix(){const sources=state.archiveSources||[];if(sources.length<2){criticismOutput('Source-criticism matrix',{error:'Add at least two sources in the Archives workspace first.'});return;}try{const relationships=parseCriticismJson('#criticism-relationships');const data=await api('/historical-archives/source-criticism/matrix',{method:'POST',body:JSON.stringify({sources,relationships})});criticismOutput('Source-criticism matrix',data);}catch(e){criticismOutput('Source-criticism matrix',{error:e.message});}}
async function buildCorroborationLedger(){const sources=state.archiveSources||[];if(!sources.length){criticismOutput('Corroboration ledger',{error:'Add sources in the Archives workspace first.'});return;}try{const claims=parseCriticismJson('#criticism-claims');if(!claims.length){criticismOutput('Corroboration ledger',{error:'Enter at least one claim observation array.'});return;}const data=await api('/historical-archives/source-criticism/corroboration',{method:'POST',body:JSON.stringify({sources,claims})});criticismOutput('Corroboration ledger',data);}catch(e){criticismOutput('Corroboration ledger',{error:e.message});}}


function timelineOutput(label,data){const t=$("#timeline-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function renderTimelineSources(){const t=$("#timeline-source-list");if(!t)return;const sources=state.archiveSources||[];if(!sources.length){t.innerHTML='<p class="empty-state">No archival sources loaded in this browser session.</p>';return;}t.innerHTML=sources.map(s=>`<article class="archive-source-row"><div><strong>${escapeHtml(s.title||"Untitled source")}</strong><p><code>${escapeHtml(s.primary_source_id||"")}</code> · ${escapeHtml(s.creation_date?.display||"undated")}</p></div></article>`).join("");}
function renderTimelineEvents(){const t=$("#timeline-event-list");if(!t)return;const events=state.timelineEvents||[];if(!events.length){t.innerHTML='<p class="empty-state">No events added.</p>';return;}t.innerHTML=events.map((e,i)=>`<article class="archive-source-row"><div><strong>${escapeHtml(e.title||"Untitled event")}</strong><p>${escapeHtml(e.date?.display||"date unknown")} · ${escapeHtml(e.event_type||"other")} · <code>${escapeHtml(e.event_key||e.event_id||"")}</code></p></div><button type="button" class="text-button" data-timeline-remove="${i}">Remove</button></article>`).join("");}
async function loadHistoricalEventTimelineWorkspace(){renderTimelineSources();renderTimelineEvents();const status=$("#timeline-readiness");try{const data=state.timelineBootstrap||await api('/historical-archives/timeline-workspace/bootstrap');state.timelineBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span><strong>${Number(data.event_types?.length||0)}</strong> event types</span><span>Uncertain dates preserved</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;const select=$("#timeline-event-type");if(select)select.innerHTML=(data.event_types||[]).map(x=>`<option value="${escapeHtml(x)}">${escapeHtml(String(x).replaceAll('-',' '))}</option>`).join("");}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
function parseTimelineArray(selector){const raw=$(selector)?.value.trim()||"";if(!raw)return[];const value=JSON.parse(raw);if(!Array.isArray(value))throw new Error('Expected a JSON array.');return value;}
async function addHistoricalTimelineEvent(event){event?.preventDefault();const sourceIds=($("#timeline-event-source-ids")?.value||"").split(",").map(x=>x.trim()).filter(Boolean);const payload={title:$("#timeline-event-title")?.value.trim()||"",event_type:$("#timeline-event-type")?.value||"other",date:$("#timeline-event-date")?.value.trim()||"",event_key:$("#timeline-event-key")?.value.trim()||null,place:$("#timeline-event-place")?.value.trim()||"",actors:($("#timeline-event-actors")?.value||"").split(",").map(x=>x.trim()).filter(Boolean),description:$("#timeline-event-description")?.value.trim()||"",source_assertions:sourceIds.map(id=>({primary_source_id:id,relation:"attests",human_asserted:true}))};if(!payload.title)return;try{const data=await api('/historical-archives/timeline-workspace/events/normalize',{method:'POST',body:JSON.stringify(payload)});state.timelineEvents.push(data);renderTimelineEvents();timelineOutput('Historical event added',data);$("#timeline-event-form")?.reset();if(state.timelineBootstrap)loadHistoricalEventTimelineWorkspace();}catch(e){timelineOutput('Historical event error',{error:e.message});}}
async function buildHistoricalTimeline(){const events=state.timelineEvents||[];if(!events.length){timelineOutput('Research timeline',{error:'Add at least one historical event first.'});return;}try{const relationships=parseTimelineArray('#timeline-relationships');const data=await api('/historical-archives/timeline-workspace/build',{method:'POST',body:JSON.stringify({title:$("#timeline-title")?.value.trim()||"Research timeline",research_question:$("#timeline-research-question")?.value.trim()||null,events,relationships})});timelineOutput('Research timeline',data);}catch(e){timelineOutput('Research timeline',{error:e.message});}}
async function buildHistoricalTimelineCoverage(){const events=state.timelineEvents||[];const sources=state.archiveSources||[];if(!events.length){timelineOutput('Source coverage',{error:'Add events first.'});return;}try{const data=await api('/historical-archives/timeline-workspace/source-coverage',{method:'POST',body:JSON.stringify({events,sources})});timelineOutput('Historical event source coverage',data);}catch(e){timelineOutput('Source coverage',{error:e.message});}}
async function compareHistoricalTimelineChronologies(){const current=state.timelineEvents||[];if(!current.length){timelineOutput('Chronology comparison',{error:'Build the current event set first.'});return;}try{const others=parseTimelineArray('#timeline-chronologies');if(!others.length){timelineOutput('Chronology comparison',{error:'Enter at least one additional chronology JSON object.'});return;}const chronologies=[{chronology_id:'current-browser-timeline',title:$("#timeline-title")?.value.trim()||'Current timeline',events:current,relationships:parseTimelineArray('#timeline-relationships')},...others];const data=await api('/historical-archives/timeline-workspace/compare',{method:'POST',body:JSON.stringify({chronologies})});timelineOutput('Competing chronology comparison',data);}catch(e){timelineOutput('Chronology comparison',{error:e.message});}}


const NOTES_STORAGE_KEY='sc-library-research-notes-v1';
function notesOutput(label,data){const t=$("#notes-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function loadBrowserResearchNotes(){try{const raw=localStorage.getItem(NOTES_STORAGE_KEY);const parsed=raw?JSON.parse(raw):[];state.researchNotes=Array.isArray(parsed)?parsed:[];}catch{state.researchNotes=[];}}
function persistBrowserResearchNotes(){try{localStorage.setItem(NOTES_STORAGE_KEY,JSON.stringify(state.researchNotes||[]));}catch{}}
function renderResearchNotes(){const t=$("#notes-list");if(!t)return;const notes=state.researchNotes||[];if(!notes.length){t.innerHTML='<p class="empty-state">No notes yet.</p>';return;}t.innerHTML=notes.map((n,i)=>`<article class="archive-source-row"><div><strong>${escapeHtml(n.title||n.note_type||"Note")}</strong><p>${escapeHtml(n.target?.title||n.target?.id||n.target?.uri||"target")} · ${escapeHtml(n.note_type||"observation")}</p><p>${escapeHtml(String(n.body||"").slice(0,240))}</p></div><button type="button" class="text-button" data-note-remove="${i}">Remove</button></article>`).join("");}
async function loadResearchNotesWorkspace(){loadBrowserResearchNotes();renderResearchNotes();const status=$("#notes-readiness");try{const data=state.notesBootstrap||await api('/annotations/bootstrap');state.notesBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span><strong>${Number(data.note_types?.length||0)}</strong> note types</span><span>Browser-local continuity</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;const kinds=$("#notes-target-kind");if(kinds)kinds.innerHTML=(data.target_kinds||[]).map(x=>`<option value="${escapeHtml(x)}">${escapeHtml(String(x).replaceAll('-',' '))}</option>`).join("");const types=$("#notes-type");if(types)types.innerHTML=(data.note_types||[]).map(x=>`<option value="${escapeHtml(x)}">${escapeHtml(String(x).replaceAll('-',' '))}</option>`).join("");}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
function parseNotesRelations(){const raw=$("#notes-relations")?.value.trim()||"";if(!raw)return[];const value=JSON.parse(raw);if(!Array.isArray(value))throw new Error('Expected a JSON array of note relationships.');return value;}
async function addResearchNote(event){event?.preventDefault();const payload={note_type:$("#notes-type")?.value||"observation",title:$("#notes-title")?.value.trim()||null,body:$("#notes-body")?.value.trim()||"",target:{kind:$("#notes-target-kind")?.value||"other",id:$("#notes-target-id")?.value.trim()||null,uri:$("#notes-target-uri")?.value.trim()||null,title:$("#notes-target-title")?.value.trim()||null,version:$("#notes-target-version")?.value.trim()||null,content_sha256:$("#notes-target-hash")?.value.trim()||null},anchor:{page:$("#notes-page")?.value.trim()||null,section:$("#notes-section")?.value.trim()||null,selected_quote:$("#notes-quote")?.value.trim()||null},tags:($("#notes-tags")?.value||"").split(",").map(x=>x.trim()).filter(Boolean),reviewed:!!$("#notes-reviewed")?.checked};if(!payload.body)return;try{const note=await api('/annotations/normalize',{method:'POST',body:JSON.stringify(payload)});state.researchNotes.push(note);persistBrowserResearchNotes();renderResearchNotes();notesOutput('Research annotation added',note);$("#notes-form")?.reset();}catch(e){notesOutput('Annotation error',{error:e.message});}}
async function buildResearchNotebook(){try{const payload={title:$("#notes-notebook-title")?.value.trim()||"Scholarly notes",research_question:$("#notes-research-question")?.value.trim()||null,annotations:state.researchNotes||[],relations:parseNotesRelations()};const data=await api('/annotations/notebook',{method:'POST',body:JSON.stringify(payload)});notesOutput('Scholarly notebook',data);}catch(e){notesOutput('Notebook error',{error:e.message});}}
async function exportResearchNotes(){try{const payload={title:$("#notes-notebook-title")?.value.trim()||"Scholarly notes",research_question:$("#notes-research-question")?.value.trim()||null,annotations:state.researchNotes||[],relations:parseNotesRelations()};const data=await api('/annotations/export',{method:'POST',body:JSON.stringify(payload)});notesOutput('Scholarly notes export',data);const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='sustainable-catalyst-scholarly-notes.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){notesOutput('Export error',{error:e.message});}}

const BIBLIOGRAPHY_STORAGE_KEY='sc-library-bibliography-v1';
function citationsOutput(label,data){const t=$("#citations-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function loadBrowserBibliography(){try{const raw=localStorage.getItem(BIBLIOGRAPHY_STORAGE_KEY);const parsed=raw?JSON.parse(raw):[];state.bibliographyItems=Array.isArray(parsed)?parsed:[];}catch{state.bibliographyItems=[];}}
function persistBrowserBibliography(){try{localStorage.setItem(BIBLIOGRAPHY_STORAGE_KEY,JSON.stringify(state.bibliographyItems||[]));}catch{}}
function renderBibliographyItems(){const t=$("#citations-list");if(!t)return;const items=state.bibliographyItems||[];if(!items.length){t.innerHTML='<p class="empty-state">No bibliographic items yet.</p>';return;}t.innerHTML=items.map((x,i)=>`<article class="archive-source-row"><div><strong>${escapeHtml(x.title||"Untitled")}</strong><p>${escapeHtml((x.authors?.[0]?.family||x.authors?.[0]?.literal||"Anonymous"))} · ${escapeHtml(x.year??"n.d.")} · <code>${escapeHtml(x.citation_key||"")}</code></p><p>${escapeHtml(x.identifiers?.doi?`DOI ${x.identifiers.doi}`:(x.identifiers?.url||x.type||""))}</p></div><button type="button" class="text-button" data-citation-remove="${i}">Remove</button></article>`).join("");}
async function loadCitationWorkspace(){loadBrowserBibliography();renderBibliographyItems();const status=$("#citations-readiness");try{const data=state.citationsBootstrap||await api('/citations/workspace/bootstrap');state.citationsBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span><strong>${Number(data.item_types?.length||0)}</strong> item types</span><span>Durable citation authority preserved</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;const select=$("#citations-type");if(select)select.innerHTML=(data.item_types||[]).map(x=>`<option value="${escapeHtml(x)}">${escapeHtml(String(x).replaceAll('-',' '))}</option>`).join("");}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
function citationAuthors(){return ($("#citations-authors")?.value||"").split(';').map(x=>x.trim()).filter(Boolean);}
async function addBibliographicItem(event){event?.preventDefault();const y=Number($("#citations-year")?.value||0);const payload={type:$("#citations-type")?.value||"other",title:$("#citations-title")?.value.trim()||"",authors:citationAuthors(),year:y||null,container_title:$("#citations-container")?.value.trim()||null,publisher:$("#citations-publisher")?.value.trim()||null,volume:$("#citations-volume")?.value.trim()||null,issue:$("#citations-issue")?.value.trim()||null,pages:$("#citations-pages")?.value.trim()||null,doi:$("#citations-doi")?.value.trim()||null,isbn:$("#citations-isbn")?.value.trim()||null,pmid:$("#citations-pmid")?.value.trim()||null,arxiv:$("#citations-arxiv")?.value.trim()||null,url:$("#citations-url")?.value.trim()||null,linked_record_id:$("#citations-record-id")?.value.trim()||null,tags:($("#citations-tags")?.value||"").split(',').map(x=>x.trim()).filter(Boolean)};if(!payload.title)return;try{const item=await api('/citations/workspace/normalize',{method:'POST',body:JSON.stringify(payload)});state.bibliographyItems.push(item);persistBrowserBibliography();renderBibliographyItems();citationsOutput('Bibliographic item added',item);$("#citations-form")?.reset();}catch(e){citationsOutput('Bibliographic normalization error',{error:e.message});}}
async function buildCitationBibliography(){try{const data=await api('/citations/workspace/bibliography',{method:'POST',body:JSON.stringify({title:$("#citations-bibliography-title")?.value.trim()||"Research bibliography",items:state.bibliographyItems||[]})});citationsOutput('Research bibliography',data);}catch(e){citationsOutput('Bibliography error',{error:e.message});}}
async function findBibliographicDuplicates(){try{const data=await api('/citations/workspace/duplicates',{method:'POST',body:JSON.stringify({items:state.bibliographyItems||[]})});citationsOutput('Duplicate candidates',data);}catch(e){citationsOutput('Duplicate analysis error',{error:e.message});}}
async function exportCitationBibliography(){try{const fmt=$("#citations-export-format")?.value||'json';const data=await api('/citations/workspace/export',{method:'POST',body:JSON.stringify({title:$("#citations-bibliography-title")?.value.trim()||"Research bibliography",items:state.bibliographyItems||[],format:fmt})});citationsOutput('Bibliography export',data);const blob=new Blob([data.content||""],{type:data.media_type||'text/plain'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-bibliography.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){citationsOutput('Bibliography export error',{error:e.message});}}

function corpusOutput(label,data){const t=$("#corpus-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function corpusWorkspacePayload(){const corpusId=$("#corpus-id")?.value.trim()||"";if(corpusId)return {corpus_id:corpusId};return {title:$("#corpus-title")?.value.trim()||"Computational linguistics working corpus",representation_id:$("#corpus-representation-id")?.value.trim()||"workspace-text:1",language_bcp47:$("#corpus-language")?.value.trim()||"und",script_iso15924:$("#corpus-script")?.value.trim()||null,source_kind:$("#corpus-source-kind")?.value||"original",text:$("#corpus-text")?.value||""};}
function corpusAnalysisPayload(){return {...corpusWorkspacePayload(),query:$("#corpus-query")?.value.trim()||"",term:$("#corpus-term")?.value.trim()||"",n:Number($("#corpus-ngram-n")?.value||2),window_tokens:Number($("#corpus-window")?.value||5),limit:Number($("#corpus-limit")?.value||100)};}
async function loadCorpusWorkspace(){const status=$("#corpus-readiness");try{const data=state.corpusBootstrap||await api('/corpus-workspace/bootstrap');state.corpusBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>v5.47 durable corpus authority preserved</span><span><strong>${escapeHtml(data.tokenizer?.profile||"tokenizer")}</strong></span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function runCorpusAction(path,label){try{const data=await api(`/corpus-workspace/${path}`,{method:'POST',body:JSON.stringify(corpusAnalysisPayload())});corpusOutput(label,data);}catch(e){corpusOutput(`${label} error`,{error:e.message});}}
async function previewCorpus(){try{const data=await api('/corpus-workspace/preview',{method:'POST',body:JSON.stringify(corpusWorkspacePayload())});corpusOutput('Corpus preview',data);}catch(e){corpusOutput('Corpus preview error',{error:e.message});}}
async function corpusHandoffPreview(){try{const payload=corpusWorkspacePayload();delete payload.corpus_id;const data=await api('/corpus-workspace/persistence-handoff-preview',{method:'POST',body:JSON.stringify(payload)});corpusOutput('Persistence handoff preview',data);}catch(e){corpusOutput('Persistence handoff error',{error:e.message});}}
async function exportCorpusAnalysis(){try{const data=await api('/corpus-workspace/export',{method:'POST',body:JSON.stringify(corpusAnalysisPayload())});corpusOutput('Computational linguistics export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-computational-linguistics-analysis.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){corpusOutput('Export error',{error:e.message});}}

function entitiesOutput(label,data){const t=$("#entities-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function entityAuthorityPayload(){const raw=$("#entities-authority-json")?.value.trim()||"";if(!raw)throw new Error("Authority JSON is required.");let authority;try{authority=JSON.parse(raw);}catch{throw new Error("Authority JSON is invalid.");}return authority;}
function entityQueryPayload(){const yearRaw=$("#entities-query-year")?.value.trim()||"";return {name:$("#entities-query-name")?.value.trim()||"",year:yearRaw===""?null:Number(yearRaw),language_bcp47:$("#entities-query-language")?.value.trim()||null,script_iso15924:$("#entities-query-script")?.value.trim()||null,entity_type:$("#entities-query-type")?.value||null};}
function entityDecisionPayload(){return {state:$("#entities-decision-state")?.value||"unresolved",selected_candidate_id:$("#entities-selected-candidate")?.value.trim()||null,adjudicator:$("#entities-adjudicator")?.value.trim()||null,rationale:$("#entities-rationale")?.value.trim()||null};}
function entityWorkspacePayload(){return {authority:entityAuthorityPayload(),query:entityQueryPayload(),limit:Number($("#entities-query-limit")?.value||25),decision:entityDecisionPayload(),year:entityQueryPayload().year};}
async function loadEntityPlaceWorkspace(){const status=$("#entities-readiness");try{const data=state.entitiesBootstrap||await api('/entity-place-workspace/bootstrap');state.entitiesBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>v5.48 durable resolution authority preserved</span><span>ambiguity preserved</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function entityPost(path,label,payload=null){try{const body=payload||entityWorkspacePayload();const data=await api(`/entity-place-workspace/${path}`,{method:'POST',body:JSON.stringify(body)});entitiesOutput(label,data);return data;}catch(e){entitiesOutput(`${label} error`,{error:e.message});return null;}}
async function loadEntityPersistedCase(){const id=$("#entities-case-id")?.value.trim()||"";if(!id){entitiesOutput("Persisted case error",{error:"Case ID is required."});return;}try{const data=await api(`/entity-place-workspace/cases/${encodeURIComponent(id)}`);entitiesOutput("Persisted resolution case",data);}catch(e){entitiesOutput("Persisted case error",{error:e.message});}}
async function entityHandoff(type){try{let payload={handoff_type:type};if(type==="authority")payload.authority=entityAuthorityPayload();if(type==="resolution-case"){payload.query=entityQueryPayload();payload.limit=Number($("#entities-query-limit")?.value||25);}if(type==="decision"){payload.case_id=$("#entities-case-id")?.value.trim()||"";payload.decision=entityDecisionPayload();}const data=await api('/entity-place-workspace/persistence-handoff-preview',{method:'POST',body:JSON.stringify(payload)});entitiesOutput(`${type} persistence handoff preview`,data);}catch(e){entitiesOutput("Persistence handoff error",{error:e.message});}}
async function exportEntityWorkspace(){try{const data=await api('/entity-place-workspace/export',{method:'POST',body:JSON.stringify(entityWorkspacePayload())});entitiesOutput('Entity/place research export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-entity-place-historical-toponym-research.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){entitiesOutput("Export error",{error:e.message});}}

function synthesisOutput(label,data){const t=$("#synthesis-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function parseSynthesisArray(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return [];let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!Array.isArray(value))throw new Error(`${label} must be a JSON array.`);return value;}
function synthesisPayload(){return {title:$("#synthesis-title")?.value.trim()||"Research synthesis",research_question:$("#synthesis-question")?.value.trim()||null,scope:$("#synthesis-scope")?.value.trim()||null,sources:parseSynthesisArray("#synthesis-sources","Sources"),claims:parseSynthesisArray("#synthesis-claims","Claims"),relationships:parseSynthesisArray("#synthesis-relationships","Relationships"),unresolved_questions:parseSynthesisArray("#synthesis-questions","Unresolved questions")};}
async function loadResearchSynthesisWorkspace(){const status=$("#synthesis-readiness");try{const data=state.synthesisBootstrap||await api('/research-synthesis/bootstrap');state.synthesisBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>composition only</span><span>no truth adjudication</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function synthesisPost(path,label){try{const data=await api(`/research-synthesis/${path}`,{method:'POST',body:JSON.stringify(synthesisPayload())});synthesisOutput(label,data);return data;}catch(e){synthesisOutput(`${label} error`,{error:e.message});return null;}}
async function exportResearchSynthesis(){try{const data=await api('/research-synthesis/export',{method:'POST',body:JSON.stringify(synthesisPayload())});synthesisOutput('Research synthesis export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-research-synthesis.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){synthesisOutput("Export error",{error:e.message});}}

function investigationOutput(label,data){const t=$("#investigation-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function parseInvestigationArray(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return [];let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!Array.isArray(value))throw new Error(`${label} must be a JSON array.`);return value;}
function parseInvestigationObject(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return {};let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!value||Array.isArray(value)||typeof value!=="object")throw new Error(`${label} must be a JSON object.`);return value;}
function investigationPayload(){return {title:$("#investigation-title")?.value.trim()||"Research investigation",research_question:$("#investigation-question")?.value.trim()||"",scope:$("#investigation-scope")?.value.trim()||null,source_strategy:parseInvestigationObject("#investigation-source-strategy","Source strategy"),subquestions:parseInvestigationArray("#investigation-subquestions","Subquestions"),hypotheses:parseInvestigationArray("#investigation-hypotheses","Hypotheses"),evidence_needs:parseInvestigationArray("#investigation-evidence-needs","Evidence needs"),tasks:parseInvestigationArray("#investigation-tasks","Tasks"),decision_points:parseInvestigationArray("#investigation-decisions","Decision points"),stop_conditions:parseInvestigationArray("#investigation-stops","Stop conditions"),risks:parseInvestigationArray("#investigation-risks","Risks")};}
async function loadResearchInvestigationWorkspace(){const status=$("#investigation-readiness");try{const data=state.investigationBootstrap||await api('/research-investigation/bootstrap');state.investigationBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>planning/control only</span><span>no automatic execution</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function investigationPost(path,label,payload=null){try{const body=payload||investigationPayload();const data=await api(`/research-investigation/${path}`,{method:'POST',body:JSON.stringify(body)});investigationOutput(label,data);return data;}catch(e){investigationOutput(`${label} error`,{error:e.message});return null;}}
async function investigationHandoff(){const payload={...investigationPayload(),handoff_type:$("#investigation-handoff-type")?.value||"research-project"};return investigationPost("handoff-preview","Investigation handoff preview",payload);}
async function exportResearchInvestigation(){try{const data=await api('/research-investigation/export',{method:'POST',body:JSON.stringify(investigationPayload())});investigationOutput('Investigation export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-research-investigation.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){investigationOutput("Export error",{error:e.message});}}

function evidenceMatrixOutput(label,data){const t=$("#evidence-matrix-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function parseEvidenceMatrixArray(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return [];let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!Array.isArray(value))throw new Error(`${label} must be a JSON array.`);return value;}
function evidenceMatrixPayload(){return {title:$("#evidence-matrix-title")?.value.trim()||"Evidence matrix",research_question:$("#evidence-matrix-question")?.value.trim()||null,scope:$("#evidence-matrix-scope")?.value.trim()||null,claims:parseEvidenceMatrixArray("#evidence-matrix-claims","Claims"),evidence:parseEvidenceMatrixArray("#evidence-matrix-evidence","Evidence"),links:parseEvidenceMatrixArray("#evidence-matrix-links","Claim-evidence links")};}
async function loadEvidenceMatrixWorkspace(){const status=$("#evidence-matrix-readiness");try{const data=state.evidenceMatrixBootstrap||await api('/evidence-matrix/bootstrap');state.evidenceMatrixBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>descriptive analysis only</span><span>no truth scoring</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function evidenceMatrixPost(path,label){try{const data=await api(`/evidence-matrix/${path}`,{method:'POST',body:JSON.stringify(evidenceMatrixPayload())});evidenceMatrixOutput(label,data);return data;}catch(e){evidenceMatrixOutput(`${label} error`,{error:e.message});return null;}}
async function exportEvidenceMatrixAnalysis(){try{const data=await api('/evidence-matrix/export',{method:'POST',body:JSON.stringify(evidenceMatrixPayload())});evidenceMatrixOutput('Evidence matrix export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-evidence-matrix.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){evidenceMatrixOutput("Export error",{error:e.message});}}

function statisticalEvidenceOutput(label,data){const t=$("#statistical-evidence-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function parseStatisticalEvidenceArray(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return [];let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!Array.isArray(value))throw new Error(`${label} must be a JSON array.`);return value;}
function parseStatisticalEvidenceObject(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return {};let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!value||Array.isArray(value)||typeof value!=="object")throw new Error(`${label} must be a JSON object.`);return value;}
function statisticalEvidencePayload(){return {title:$("#statistical-evidence-title")?.value.trim()||"Dataset & statistical evidence",research_question:$("#statistical-evidence-question")?.value.trim()||null,query:$("#statistical-evidence-query")?.value.trim()||"",scope:$("#statistical-evidence-scope")?.value.trim()||null,filters:parseStatisticalEvidenceObject("#statistical-evidence-filters","Filters"),datasets:parseStatisticalEvidenceArray("#statistical-evidence-datasets","Datasets"),claims:parseStatisticalEvidenceArray("#statistical-evidence-claims","Claims"),statistical_results:parseStatisticalEvidenceArray("#statistical-evidence-results","Statistical results")};}
async function loadStatisticalEvidenceWorkspace(){const status=$("#statistical-evidence-readiness");try{const data=state.statisticalEvidenceBootstrap||await api('/statistical-evidence/bootstrap');state.statisticalEvidenceBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>metadata discovery only</span><span>no automatic significance/causality inference</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function statisticalEvidencePost(path,label,payload=null){try{const data=await api(`/statistical-evidence/${path}`,{method:'POST',body:JSON.stringify(payload||statisticalEvidencePayload())});statisticalEvidenceOutput(label,data);return data;}catch(e){statisticalEvidenceOutput(`${label} error`,{error:e.message});return null;}}
async function statisticalEvidenceProfile(){const payload=statisticalEvidencePayload();const first=payload.datasets?.[0];if(first)payload.dataset_id=first.dataset_id||first.id;return statisticalEvidencePost("dataset-profile","Dataset profile",payload);}
async function exportStatisticalEvidence(){try{const data=await api('/statistical-evidence/export',{method:'POST',body:JSON.stringify(statisticalEvidencePayload())});statisticalEvidenceOutput('Statistical evidence export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-dataset-statistical-evidence.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){statisticalEvidenceOutput("Export error",{error:e.message});}}

function geospatialOutput(label,data){const t=$("#geospatial-output");if(t)t.innerHTML=`<p class="eyebrow">${escapeHtml(label)}</p><pre>${escapeHtml(JSON.stringify(data,null,2))}</pre>`;}
function parseGeospatialArray(selector,label){const raw=$(selector)?.value.trim()||"";if(!raw)return [];let value;try{value=JSON.parse(raw);}catch{throw new Error(`${label} JSON is invalid.`);}if(!Array.isArray(value))throw new Error(`${label} must be a JSON array.`);return value;}
function geospatialPayload(){return {title:$("#geospatial-title")?.value.trim()||"Geospatial research",research_question:$("#geospatial-question")?.value.trim()||null,scope:$("#geospatial-scope")?.value.trim()||null,claim_id:$("#geospatial-claim-id")?.value.trim()||null,claims:[],places:parseGeospatialArray("#geospatial-places","Places"),layers:parseGeospatialArray("#geospatial-layers","Layers"),features:parseGeospatialArray("#geospatial-features","Features"),relations:parseGeospatialArray("#geospatial-relations","Relations")};}
async function loadGeospatialResearchWorkspace(){const status=$("#geospatial-readiness");try{const data=state.geospatialBootstrap||await api('/geospatial-research/bootstrap');state.geospatialBootstrap=data;const r=data.readiness||{};if(status)status.innerHTML=`<span><strong>${escapeHtml(r.state||"unknown")}</strong> workspace</span><span>CRS explicit</span><span>no spatial causality inference</span><span>Web ${escapeHtml(data.web_version||config.webVersion||"")}</span>`;}catch(e){if(status)status.innerHTML=`<span class="error-state">${escapeHtml(e.message)}</span>`;}}
async function geospatialPost(path,label){try{const data=await api(`/geospatial-research/${path}`,{method:'POST',body:JSON.stringify(geospatialPayload())});geospatialOutput(label,data);return data;}catch(e){geospatialOutput(`${label} error`,{error:e.message});return null;}}
async function exportGeospatialResearch(){try{const data=await api('/geospatial-research/export',{method:'POST',body:JSON.stringify(geospatialPayload())});geospatialOutput('Geospatial export',data);const blob=new Blob([data.content||""],{type:data.media_type||'application/json'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=data.filename||'sustainable-catalyst-geospatial-place-research.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}catch(e){geospatialOutput("Export error",{error:e.message});}}

async function loadSystem() {
  const target=$("#system-grid"); target.innerHTML='<p class="empty-state">Checking runtime…</p>';
  const checks=[['API','/readiness'],['Runtime authority','/runtime-authority'],['Federation','/federation/readiness'],['Global federation II','/federation/global/readiness'],['Research graph','/research-graph/readiness'],['Living research','/living-research/readiness'],['Structured evidence','/structured-evidence/readiness'],['Scientific literature','/scientific-literature/readiness'],['Research package publishing','/research-package-publishing/readiness'],['Institutional repositories','/institutional-repositories/readiness'],['Historical archives & primary sources','/historical-archives/readiness'],['Historical archives workspace','/historical-archives/workspace/readiness'],['Primary-source comparison & source criticism','/historical-archives/source-criticism/readiness'],['Research timeline & historical events','/historical-archives/timeline-workspace/readiness'],['Research annotation & scholarly notes','/annotations/readiness'],['Citation workspace & bibliographic intelligence','/citations/workspace/readiness'],['Corpus & computational linguistics','/corpus-workspace/readiness'],['Entity, place & historical toponym','/entity-place-workspace/readiness'],['Research synthesis','/research-synthesis/readiness'],['Research investigation','/research-investigation/readiness'],['Evidence matrix & claim support','/evidence-matrix/readiness'],['Dataset discovery & statistical evidence','/statistical-evidence/readiness'],['Geospatial & place-based research','/geospatial-research/readiness'],['Artifacts','/artifacts/readiness'],['Pipelines','/pipelines/readiness'],['Compute','/compute/readiness'],['Web application','/web-application/readiness']];
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

$("#geospatial-inventory")?.addEventListener("click",()=>geospatialPost("inventory","Geospatial inventory"));
$("#geospatial-relations")?.addEventListener("click",()=>geospatialPost("relation-preview","Spatial relation preview"));
$("#geospatial-coverage")?.addEventListener("click",()=>geospatialPost("coverage-audit","Spatial coverage audit"));
$("#geospatial-temporal")?.addEventListener("click",()=>geospatialPost("temporal-validity","Temporal validity"));
$("#geospatial-evidence-handoff")?.addEventListener("click",()=>geospatialPost("evidence-handoff-preview","Evidence handoff preview"));
$("#geospatial-investigation-handoff")?.addEventListener("click",()=>geospatialPost("investigation-handoff-preview","Investigation handoff preview"));
$("#geospatial-export")?.addEventListener("click",exportGeospatialResearch);
$("#statistical-evidence-discover")?.addEventListener("click",()=>statisticalEvidencePost("discover","Dataset discovery"));
$("#statistical-evidence-profile")?.addEventListener("click",statisticalEvidenceProfile);
$("#statistical-evidence-table")?.addEventListener("click",()=>statisticalEvidencePost("statistical-table","Statistical evidence table"));
$("#statistical-evidence-uncertainty")?.addEventListener("click",()=>statisticalEvidencePost("uncertainty-audit","Uncertainty audit"));
$("#statistical-evidence-gaps")?.addEventListener("click",()=>statisticalEvidencePost("gaps","Dataset & statistical evidence gaps"));
$("#statistical-evidence-matrix-handoff")?.addEventListener("click",()=>statisticalEvidencePost("evidence-handoff-preview","Evidence Matrix handoff preview"));
$("#statistical-evidence-investigation-handoff")?.addEventListener("click",()=>statisticalEvidencePost("investigation-handoff-preview","Investigation handoff preview"));
$("#statistical-evidence-export")?.addEventListener("click",exportStatisticalEvidence);
$("#evidence-matrix-build")?.addEventListener("click",()=>evidenceMatrixPost("matrix","Claim-evidence matrix"));
$("#evidence-matrix-profiles")?.addEventListener("click",()=>evidenceMatrixPost("support-profiles","Support profiles"));
$("#evidence-matrix-contradictions")?.addEventListener("click",()=>evidenceMatrixPost("contradictions","Contradiction analysis"));
$("#evidence-matrix-provenance")?.addEventListener("click",()=>evidenceMatrixPost("provenance-coverage","Provenance coverage"));
$("#evidence-matrix-dependencies")?.addEventListener("click",()=>evidenceMatrixPost("source-dependencies","Source dependencies"));
$("#evidence-matrix-gaps")?.addEventListener("click",()=>evidenceMatrixPost("gaps","Evidence gaps"));
$("#evidence-matrix-synthesis-handoff")?.addEventListener("click",()=>evidenceMatrixPost("synthesis-handoff-preview","Synthesis handoff preview"));
$("#evidence-matrix-investigation-handoff")?.addEventListener("click",()=>evidenceMatrixPost("investigation-handoff-preview","Investigation handoff preview"));
$("#evidence-matrix-export")?.addEventListener("click",exportEvidenceMatrixAnalysis);
$("#investigation-build")?.addEventListener("click",()=>investigationPost("build","Research investigation"));
$("#investigation-matrix")?.addEventListener("click",()=>investigationPost("matrix","Investigation matrix"));
$("#investigation-coverage")?.addEventListener("click",()=>investigationPost("coverage","Coverage analysis"));
$("#investigation-execution")?.addEventListener("click",()=>investigationPost("execution-plan","Execution plan"));
$("#investigation-risk-register")?.addEventListener("click",()=>investigationPost("risk-register","Risk register"));
$("#investigation-handoff")?.addEventListener("click",investigationHandoff);
$("#investigation-export")?.addEventListener("click",exportResearchInvestigation);
$("#synthesis-build")?.addEventListener("click",()=>synthesisPost("synthesize","Research synthesis"));
$("#synthesis-matrix")?.addEventListener("click",()=>synthesisPost("evidence-matrix","Evidence matrix"));
$("#synthesis-contradictions")?.addEventListener("click",()=>synthesisPost("contradictions","Contradiction ledger"));
$("#synthesis-convergence")?.addEventListener("click",()=>synthesisPost("convergence","Convergence summary"));
$("#synthesis-attribution")?.addEventListener("click",()=>synthesisPost("source-attribution","Source attribution"));
$("#synthesis-gaps")?.addEventListener("click",()=>synthesisPost("gaps","Gap analysis"));
$("#synthesis-handoff")?.addEventListener("click",()=>synthesisPost("publishing-handoff-preview","Publishing handoff preview"));
$("#synthesis-export")?.addEventListener("click",exportResearchSynthesis);
$("#entities-authority-preview")?.addEventListener("click",()=>entityPost("authority-preview","Entity authority preview",{authority:entityAuthorityPayload()}));
$("#entities-toponym-timeline")?.addEventListener("click",()=>entityPost("toponym-timeline","Historical toponym timeline",{authority:entityAuthorityPayload(),year:entityQueryPayload().year}));
$("#entities-authority-handoff")?.addEventListener("click",()=>entityHandoff("authority"));
$("#entities-resolve")?.addEventListener("click",()=>entityPost("resolve-preview","Entity resolution preview"));
$("#entities-matrix")?.addEventListener("click",()=>entityPost("candidate-matrix","Candidate matrix"));
$("#entities-case-handoff")?.addEventListener("click",()=>entityHandoff("resolution-case"));
$("#entities-decision-preview")?.addEventListener("click",()=>entityPost("decision-preview","Decision preview"));
$("#entities-load-case")?.addEventListener("click",loadEntityPersistedCase);
$("#entities-decision-handoff")?.addEventListener("click",()=>entityHandoff("decision"));
$("#entities-export")?.addEventListener("click",exportEntityWorkspace);
$("#corpus-preview")?.addEventListener("click",previewCorpus);
$("#corpus-handoff")?.addEventListener("click",corpusHandoffPreview);
$("#corpus-frequency")?.addEventListener("click",()=>runCorpusAction("frequency","Frequency analysis"));
$("#corpus-kwic")?.addEventListener("click",()=>runCorpusAction("kwic","KWIC"));
$("#corpus-ngrams")?.addEventListener("click",()=>runCorpusAction("ngrams","N-gram analysis"));
$("#corpus-cooccurrence")?.addEventListener("click",()=>runCorpusAction("cooccurrence","Co-occurrence analysis"));
$("#corpus-export")?.addEventListener("click",exportCorpusAnalysis);
$("#citations-form")?.addEventListener("submit",addBibliographicItem);
$("#citations-clear")?.addEventListener("click",()=>{state.bibliographyItems=[];persistBrowserBibliography();renderBibliographyItems();});
$("#citations-build")?.addEventListener("click",buildCitationBibliography);
$("#citations-duplicates")?.addEventListener("click",findBibliographicDuplicates);
$("#citations-export")?.addEventListener("click",exportCitationBibliography);
$("#notes-form")?.addEventListener("submit",addResearchNote);
$("#notes-clear")?.addEventListener("click",()=>{state.researchNotes=[];persistBrowserResearchNotes();renderResearchNotes();});
$("#notes-build")?.addEventListener("click",buildResearchNotebook);
$("#notes-export")?.addEventListener("click",exportResearchNotes);
$("#timeline-event-form")?.addEventListener("submit",addHistoricalTimelineEvent);
$("#timeline-clear-events")?.addEventListener("click",()=>{state.timelineEvents=[];renderTimelineEvents();});
$("#timeline-build")?.addEventListener("click",buildHistoricalTimeline);
$("#timeline-coverage")?.addEventListener("click",buildHistoricalTimelineCoverage);
$("#timeline-compare")?.addEventListener("click",compareHistoricalTimelineChronologies);
$("#criticism-matrix")?.addEventListener("click",buildCriticismMatrix);
$("#criticism-ledger")?.addEventListener("click",buildCorroborationLedger);
$("#archive-search-form")?.addEventListener("submit",runHistoricalArchiveSearch);
$("#archive-source-form")?.addEventListener("submit",addHistoricalArchiveSource);
$("#archive-clear-sources")?.addEventListener("click",()=>{state.archiveSources=[];renderArchiveSources();});
$("#archive-compare")?.addEventListener("click",()=>runArchiveWorkspaceAction("compare"));
$("#archive-timeline")?.addEventListener("click",()=>runArchiveWorkspaceAction("timeline"));
$("#archive-packet")?.addEventListener("click",()=>runArchiveWorkspaceAction("packet"));
$("#archive-handoff")?.addEventListener("click",()=>runArchiveWorkspaceAction("handoff"));
$("#research-form")?.addEventListener("submit", event => { event.preventDefault(); navigate("/research"); researchSearch(); });
$("#research-load-more")?.addEventListener("click",()=>researchSearch({append:true}));
$("#research-clear")?.addEventListener("click",clearResearchScope);
$("#working-set-clear")?.addEventListener("click",()=>{state.workingSet=[];saveWorkingSet();});
$("#working-set-save")?.addEventListener("click",saveWorkingSetToProject);
$("#working-set-dataset-preview-button")?.addEventListener("click",previewWorkingSetDataset);
$("#working-set-literature-preview-button")?.addEventListener("click",previewWorkingSetLiterature);
$("#working-set-export-preview-button")?.addEventListener("click",previewWorkingSetExport);
$("#working-set-export-download-button")?.addEventListener("click",downloadWorkingSetExport);
$("#institutional-repository-search-button")?.addEventListener("click",previewInstitutionalRepositories);
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
document.addEventListener('click',event=>{
  const citationRemove=event.target.closest('[data-citation-remove]'); if(citationRemove){event.preventDefault();state.bibliographyItems.splice(Number(citationRemove.dataset.citationRemove),1);persistBrowserBibliography();renderBibliographyItems();return;}
  const noteRemove=event.target.closest('[data-note-remove]'); if(noteRemove){event.preventDefault();state.researchNotes.splice(Number(noteRemove.dataset.noteRemove),1);persistBrowserResearchNotes();renderResearchNotes();return;}
  const timelineRemove=event.target.closest('[data-timeline-remove]'); if(timelineRemove){event.preventDefault();state.timelineEvents.splice(Number(timelineRemove.dataset.timelineRemove),1);renderTimelineEvents();return;}
  const add=event.target.closest('[data-archive-add-result]'); if(add){event.preventDefault();addArchiveSearchResult(Number(add.dataset.archiveAddResult));return;}
  const inspect=event.target.closest('[data-archive-inspect]'); if(inspect){event.preventDefault();const source=state.archiveSources[Number(inspect.dataset.archiveInspect)];if(source) archiveOutput('Primary source',source);return;}
  const remove=event.target.closest('[data-archive-remove]'); if(remove){event.preventDefault();state.archiveSources.splice(Number(remove.dataset.archiveRemove),1);renderArchiveSources();return;}
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
    const session=await api('/session/login',{method:'POST',body:JSON.stringify({handle,password,client_label:'library-web-v2.11.0'})});
    $("#login-password").value=''; renderSession(session);
  } catch (e) { error.textContent=e.message; error.hidden=false; }
}

async function logout() {
  try {
    await api('/session/logout',{method:'POST',headers: state.csrfToken ? {'X-SC-CSRF-Token':state.csrfToken}: {}});
  } catch (e) { /* cookie is still cleared on the next valid logout/session expiry */ }
  state.session=null; state.csrfToken=null; state.workspaceSnapshot=null; state.activeProjectId=null; renderSavedWorkspaces(); await loadSession();
}
