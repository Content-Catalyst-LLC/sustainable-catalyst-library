const config = window.SC_LIBRARY_WEB_CONFIG || { apiBase: "/api/library/v1", webVersion: "1.0.0" };
const API = String(config.apiBase || "/api/library/v1").replace(/\/$/, "");
const state = { offset: 0, limit: 20, query: "", mode: "hybrid", total: 0, lastSearch: null };

const $ = (selector, root=document) => root.querySelector(selector);
const $$ = (selector, root=document) => [...root.querySelectorAll(selector)];
const escapeHtml = (value="") => String(value).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));
const text = value => value == null ? "" : String(value);

async function api(path, options={}) {
  const response = await fetch(`${API}${path}`, { headers: { Accept: "application/json", ...(options.headers||{}) }, ...options });
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

function route() {
  const raw=(location.hash || "#/search").slice(2); const [name="search", ...rest]=raw.split("/");
  const view = ["search","discover","system"].includes(name) ? name : name === "record" ? "record" : "search";
  $$(".view").forEach(el => { el.hidden = el.dataset.view !== view; });
  $$('[data-nav]').forEach(a => a.setAttribute('aria-current', a.dataset.nav===view ? 'page' : 'false'));
  if (view === "discover") loadCapabilities();
  if (view === "system") loadSystem();
  if (view === "record" && rest.length) loadRecord(decodeURIComponent(rest.join("/")));
  requestAnimationFrame(() => $("#main")?.focus({preventScroll:true}));
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
    card.innerHTML=`<div class="result-meta"><span>${type}</span>${record.year?`<span>${escapeHtml(record.year)}</span>`:""}</div><h3>${id?`<a href="#/record/${encodeURIComponent(id)}">${title}</a>`:title}</h3><p>${summary}</p>`;
    frag.append(card);
  }
  target.append(frag);
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
    const data=await api(`/records/${encodeURIComponent(id)}?include_body=true`); const r=data.record || {};
    const title=escapeHtml(resultTitle(r)); const body=pick(r,["body_text","body","content","abstract","summary","description"]) || "No readable body is available for this record.";
    const source=pick(r,["source_name","source","source_key"]); const type=objectType(r);
    target.innerHTML=`<header><p class="eyebrow">${escapeHtml(type)}</p><h1>${title}</h1><div class="reader-meta">${source?`<span>Source: ${escapeHtml(source)}</span>`:""}${r.year?`<span>Year: ${escapeHtml(r.year)}</span>`:""}</div></header><div class="reader-body">${escapeHtml(body).split(/\n{2,}/).map(p=>`<p>${p.replace(/\n/g,"<br>")}</p>`).join("")}</div><details><summary>Record metadata</summary><pre>${escapeHtml(JSON.stringify(r,null,2))}</pre></details>`;
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
  try { const data=await api('/service'); setApiStatus(true,`API ${data.api_version || '1.0'} online`); }
  catch { setApiStatus(false,'API unavailable'); }
  route();
}

$("#search-form").addEventListener("submit", event => { event.preventDefault(); location.hash="#/search"; search(); });
$("#load-more").addEventListener("click",()=>search({append:true}));
$("#reader-back").addEventListener("click",()=>history.length>1?history.back():(location.hash="#/search"));
window.addEventListener("hashchange",route);
bootstrap();
