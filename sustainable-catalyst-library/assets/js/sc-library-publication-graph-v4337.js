(function(){'use strict';
  var cfg=window.scPublicationGraph||{};
  function boot(){document.querySelectorAll('[data-sc-publication-graph]').forEach(function(root){
    var form=root.querySelector('[data-sc-publication-project-link]'); if(!form)return;
    var status=form.querySelector('[data-sc-publication-project-status]');
    form.addEventListener('submit',function(ev){ev.preventDefault();var pid=form.querySelector('[name="publication_id"]')?.value||'';var project=form.querySelector('[name="project_id"]')?.value||'';if(!pid||!project)return;
      if(status)status.textContent='Linking publication…';
      fetch(String(cfg.root||'')+'/'+encodeURIComponent(pid)+'/project-link',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':String(cfg.nonce||'')},body:JSON.stringify({project_id:Number(project)})})
      .then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j&&j.message?j.message:'Could not link publication.');return j;});})
      .then(function(){if(status)status.textContent='Publication linked to the private research project.';})
      .catch(function(e){if(status)status.textContent=e.message||'Could not link publication.';});
    });
  });}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
}());
