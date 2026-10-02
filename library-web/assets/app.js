const config = window.SC_LIBRARY_WEB_CONFIG || { apiBase: "/api/library/v1", webVersion: "2.1.0" };
const API = String(config.apiBase || "/api/library/v1").replace(/\/$/, "");
const state = { offset: 0, limit: 20, query: "", mode: "hybrid", total: 0, lastSearch: null, session: null, csrfToken: null, researchOffset: 0, researchTotal: 0, researchBootstrap: null, workingSet: [] };

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
  return location.pathname.replace(/^\/+|\/+$/g, "") || "search";
}

function route() {
  const raw=currentRoute(); const [name="search", ...rest]=raw.split("/");
  const view = ["research","search","discover","system","account"].includes(name) ? name : name === "record" ? "record" : "research";
  $$(".view").forEach(el => { el.hidden = el.dataset.view !== view; });
  $$('[data-nav]').forEach(a => a.setAttribute('aria-current', a.dataset.nav===view ? 'page' : 'false'));
  updatePublicMetadata(view, rest);
  if (view === "research") loadResearchBootstrap();
  if (view === "discover") loadCapabilities();
  if (view === "system") loadSystem();
  if (view === "account") loadSession();
  if (view === "record" && rest.length) loadRecord(decodeURIComponent(rest.join("/")));
  requestAnimationFrame(() => $("#main")?.focus({preventScroll:true}));
}

function navigate(path) {
  if (location.pathname !== path || location.hash) history.pushState({}, "", path);
  route();
}

function updatePublicMetadata(view, rest=[]) {
  const origin=String(config.publicOrigin || location.origin).replace(/\/$/, "");
  let path=view === "search" ? "/search" : `/${view}`;
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
    const params=new URLSearchParams(researchParams()); const data=await api(`/research-interface/search?${params}`); const result=data.result || {}; const items=result.results || [];
    state.researchTotal=Number(result.total ?? items.length); renderResearchResults(items,append);
    $("#research-result-count").textContent=`${state.researchTotal.toLocaleString()} ${state.researchTotal===1?"result":"results"}`;
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

async function loadRecord(id) {
  $("#reader-id").textContent=id; const target=$("#reader"); target.innerHTML='<p class="empty-state">Loading record…</p>';
  try {
    const context=await api(`/research-interface/records/${encodeURIComponent(id)}?include_body=true&evidence_depth=1&evidence_limit=120`);
    const obj=context.research_object || {}; const r={...(obj.descriptive||{}),...(obj.source||{}),...(obj.publication||{}),...(obj.identity||{}),body_text:obj.content?.body_text || "",chunks:obj.content?.chunks || [],metadata:obj.metadata || {}};
    api(`/seo/records/${encodeURIComponent(id)}`).then(applySeoDescriptor).catch(()=>{});
    const title=escapeHtml(resultTitle(r)); const body=pick(r,["body_text","body","content","abstract","summary","description"]) || "No readable body is available for this record.";
    const source=pick(r,["source_name","source","source_key"]); const type=objectType(r);
    const versions=context.provenance?.record_versions?.length || 0; const citations=context.citations?.count || 0; const nodes=context.evidence_graph?.node_count || 0; const edges=context.evidence_graph?.edge_count || 0;
    target.innerHTML=`<header><p class="eyebrow">${escapeHtml(type)}</p><h1>${title}</h1><div class="reader-meta">${source?`<span>Source: ${escapeHtml(source)}</span>`:""}${r.published_at?`<span>Published: ${escapeHtml(r.published_at)}</span>`:""}</div></header><aside class="context-strip"><span><strong>${versions}</strong> versions</span><span><strong>${citations}</strong> citations</span><span><strong>${nodes}</strong> evidence nodes</span><span><strong>${edges}</strong> evidence edges</span><button type="button" class="secondary-button" data-reader-add="${escapeHtml(id)}">Add to working set</button></aside><div class="reader-body">${escapeHtml(body).split(/\n{2,}/).map(p=>`<p>${p.replace(/\n/g,"<br>")}</p>`).join("")}</div><details><summary>Research context</summary><pre>${escapeHtml(JSON.stringify({provenance:context.provenance,citations:context.citations,evidence_graph:context.evidence_graph},null,2))}</pre></details><details><summary>Research object metadata</summary><pre>${escapeHtml(JSON.stringify(obj,null,2))}</pre></details>`;
    $("[data-reader-add]")?.addEventListener("click",()=>{addToWorkingSet({record_id:id,title:resultTitle(r),object_type:type});});
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
  const checks=[['API','/readiness'],['Runtime authority','/runtime-authority'],['Federation','/federation/readiness'],['Artifacts','/artifacts/readiness'],['Pipelines','/pipelines/readiness'],['Compute','/compute/readiness'],['Web application','/web-application/readiness']];
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
$("#search-form").addEventListener("submit", event => { event.preventDefault(); navigate("/search"); search(); });
$("#load-more").addEventListener("click",()=>search({append:true}));
$("#reader-back").addEventListener("click",()=>history.length>1?history.back():navigate("/search"));
$("#login-form")?.addEventListener("submit",login);
window.addEventListener("hashchange",route);
window.addEventListener("popstate",route);
document.addEventListener('click',event=>{const a=event.target.closest('a[data-app-link], nav a'); if(!a) return; const url=new URL(a.href,location.href); if(url.origin!==location.origin) return; event.preventDefault(); navigate(url.pathname);});
bootstrap();


function renderSession(session) {
  const target=$("#session-state"); const login=$("#login-card");
  if (!session?.authenticated) {
    state.session=null; state.csrfToken=null; login.hidden=false;
    target.innerHTML='<p class="empty-state">You are not signed in. Public Library search and reading remain available.</p>';
    return;
  }
  state.session=session; if (session.csrf_token) state.csrfToken=session.csrf_token; login.hidden=true;
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
    const session=await api('/session/login',{method:'POST',body:JSON.stringify({handle,password,client_label:'library-web-v2.1.0'})});
    $("#login-password").value=''; renderSession(session);
  } catch (e) { error.textContent=e.message; error.hidden=false; }
}

async function logout() {
  try {
    await api('/session/logout',{method:'POST',headers: state.csrfToken ? {'X-SC-CSRF-Token':state.csrfToken}: {}});
  } catch (e) { /* cookie is still cleared on the next valid logout/session expiry */ }
  state.session=null; state.csrfToken=null; await loadSession();
}
