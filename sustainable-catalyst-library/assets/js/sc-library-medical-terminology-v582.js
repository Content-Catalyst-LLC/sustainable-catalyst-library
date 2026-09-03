(function(){
  'use strict';
  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}
  function render(root,payload){
    var out=root.querySelector('.sc-medical-terminology__results');
    var groups=(payload&&payload.groups)||[];
    if(!groups.length){out.innerHTML='<p>No terminology matches returned.</p>';return;}
    out.innerHTML=groups.map(function(group){
      var source=(group.source&&group.source.name)||((group.source_key||'').toUpperCase());
      var rows=(group.results||[]).slice(0,8);
      return '<section class="sc-medical-terminology__group"><h3>'+esc(source)+'</h3>'+rows.map(function(r){
        var label=r.label||r.title||'Untitled concept'; var id=r.code||r.identifier||r.rxcui||'';
        return '<article><strong>'+esc(label)+'</strong>'+(id?'<span>'+esc(id)+'</span>':'')+'<small>'+esc((r.provenance&&r.provenance.steward)||'Authoritative terminology source')+'</small></article>';
      }).join('')+'</section>';
    }).join('');
  }
  document.addEventListener('submit',function(e){
    var form=e.target.closest('.sc-medical-terminology__search'); if(!form)return; e.preventDefault();
    var root=form.closest('[data-sc-medical-terminology]'); var q=(new FormData(form).get('q')||'').trim(); if(!q)return;
    var status=root.querySelector('.sc-medical-terminology__status'); status.textContent='Resolving authoritative terminology…';
    fetch(root.dataset.endpoint+'?q='+encodeURIComponent(q)+'&limit=5',{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.detail||'Terminology lookup failed');return j;});}).then(function(j){render(root,j); var errors=(j.errors||[]).length; status.textContent=errors?'Partial results: one or more terminology sources are unavailable.':'Candidate concepts resolved. Review source meaning before use.';}).catch(function(err){status.textContent=err.message||'Medical terminology is temporarily unavailable.';});
  });
})();
