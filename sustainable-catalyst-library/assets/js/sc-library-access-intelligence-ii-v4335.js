(function(){
  'use strict';
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function render(plan){
    var c=plan&&plan.confidence||{}; var paths=Array.isArray(plan&&plan.paths)?plan.paths:[];
    var html='<div class="sc-access-ii__summary"><strong>'+esc(plan.state_label||'ACCESS UNCONFIRMED')+'</strong><span>'+esc((c.level||'unknown')+' confidence')+'</span><p>'+esc(c.basis||'')+'</p></div>';
    if(paths.length){html+='<ol class="sc-access-ii__paths">'+paths.map(function(p){return '<li><div><small>#'+esc(p.rank||'')+' · '+esc(p.confidence||'unconfirmed')+'</small><strong>'+esc(p.label||'Access route')+'</strong><span>'+esc(p.entitlement_class||'unresolved')+'</span></div>'+(p.url?'<a href="'+esc(p.url)+'" target="_blank" rel="noopener">Open route →</a>':'')+'</li>';}).join('')+'</ol>';}
    return html;
  }
  document.addEventListener('submit',function(event){
    var form=event.target.closest('[data-sc-access-ii-form]'); if(!form||!window.SCAccessIntelligenceII)return;
    event.preventDefault(); var root=form.closest('[data-sc-access-intelligence-ii]'); var status=root&&root.querySelector('[data-sc-access-ii-status]'); var input=form.querySelector('input[name="sc_access_query"]'); var q=input?input.value.trim():''; if(!status||!q)return;
    status.innerHTML='<p>Ranking access evidence and legitimate fallback routes…</p>';
    var url=window.SCAccessIntelligenceII.restUrl+'?q='+encodeURIComponent(q);
    fetch(url,{credentials:'same-origin',headers:{'X-WP-Nonce':window.SCAccessIntelligenceII.nonce||''}}).then(function(r){if(!r.ok)throw new Error('Access planning request failed.');return r.json();}).then(function(data){status.innerHTML=render(data);}).catch(function(){status.innerHTML='<p>Access planning is temporarily unavailable. Use Research Access, My Libraries, or the provider site directly.</p>';});
  });
})();
