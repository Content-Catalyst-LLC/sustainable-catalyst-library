(function(){
  'use strict';
  function esc(v){return String(v == null ? '' : v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function arr(v){return Array.isArray(v)?v:[];}
  function phases(t){return arr((t.study_design||{}).phases).join(', ') || '—';}
  function interventionNames(t){return arr(t.interventions).map(function(x){return x && x.name;}).filter(Boolean).slice(0,5).join(', ') || '—';}
  function card(t){
    var rs=t.results_state||{}, pubs=t.publications||{}, design=t.study_design||{};
    return '<article class="sc-ct-card" data-nct="'+esc(t.nct_id)+'">'+
      '<div class="sc-ct-card__select"><label><input type="checkbox" data-ct-select value="'+esc(t.nct_id)+'"> Compare</label></div>'+
      '<p class="sc-ct-card__id">'+esc(t.nct_id||'')+' · '+esc(t.overall_status||'Unknown status')+'</p>'+
      '<h3>'+esc(t.title||'Untitled study')+'</h3>'+
      '<dl><div><dt>Type</dt><dd>'+esc(design.study_type||'—')+'</dd></div><div><dt>Phase</dt><dd>'+esc(phases(t))+'</dd></div><div><dt>Enrollment</dt><dd>'+esc(design.enrollment == null?'—':design.enrollment)+'</dd></div><div><dt>Results</dt><dd>'+esc(rs.has_results?'Posted':'Not posted')+'</dd></div></dl>'+
      '<p><strong>Conditions:</strong> '+esc(arr(t.conditions).slice(0,4).join(', ')||'—')+'</p>'+
      '<p><strong>Interventions:</strong> '+esc(interventionNames(t))+'</p>'+
      '<p class="sc-ct-card__evidence">Linked result publications: '+esc(pubs.results_reference_count||0)+' · Retraction signals: '+esc(pubs.retraction_signal_count||0)+'</p>'+
      '<div class="sc-ct-card__actions"><button type="button" data-ct-detail="'+esc(t.nct_id)+'">View structured detail</button><a href="'+esc(t.source_url||'#')+'" target="_blank" rel="noopener noreferrer">ClinicalTrials.gov</a></div>'+
    '</article>';
  }
  function comparison(data){
    var rows=arr(data.matrix);
    if(!rows.length)return '<p>No comparison data returned.</p>';
    var html='<div class="sc-ct-compare-table"><table><thead><tr><th>Trial</th><th>Status</th><th>Phase</th><th>Enrollment</th><th>Results</th><th>Linked result pubs</th><th>Retraction signals</th></tr></thead><tbody>';
    rows.forEach(function(r){html+='<tr><th>'+esc(r.nct_id)+'</th><td>'+esc(r.overall_status||'—')+'</td><td>'+esc(arr(r.phases).join(', ')||'—')+'</td><td>'+esc(r.enrollment==null?'—':r.enrollment)+'</td><td>'+esc(r.has_results?'Posted':'Not posted')+'</td><td>'+esc(r.linked_results_publications||0)+'</td><td>'+esc(r.retraction_signal_count||0)+'</td></tr>';});
    html+='</tbody></table></div>';
    var common=data.common||{};
    html+='<p><strong>Shared conditions:</strong> '+esc(arr(common.conditions).join(', ')||'None identified across all selected records')+'</p>';
    html+='<p><strong>Shared interventions:</strong> '+esc(arr(common.interventions).join(', ')||'None identified across all selected records')+'</p>';
    html+='<p class="sc-ct-compare-note">Descriptive registry comparison only. No comparative-effectiveness conclusion is generated.</p>';
    return html;
  }
  function detail(t){
    var e=t.eligibility||{}, d=t.study_design||{}, p=t.publications||{}, rs=t.results_state||{}, dates=t.dates||{};
    return '<article class="sc-ct-detail"><button type="button" data-ct-close>Close detail</button><p class="sc-ct-card__id">'+esc(t.nct_id||'')+'</p><h3>'+esc(t.title||'Untitled study')+'</h3>'+
      '<div class="sc-ct-detail__grid"><section><h4>Design</h4><p>'+esc(d.study_type||'—')+' · '+esc(phases(t))+' · Enrollment '+esc(d.enrollment==null?'—':d.enrollment)+'</p><p>Allocation: '+esc(d.allocation||'—')+' · Model: '+esc(d.intervention_model||d.observational_model||'—')+' · Masking: '+esc(d.masking||'—')+'</p></section>'+
      '<section><h4>Population</h4><p>Sex: '+esc(e.sex||'—')+' · Ages: '+esc(e.minimum_age||'—')+' to '+esc(e.maximum_age||'—')+'</p><p>Healthy volunteers: '+esc(e.healthy_volunteers==null?'—':e.healthy_volunteers)+'</p></section>'+
      '<section><h4>Results state</h4><p>'+esc(rs.aggregate_evidence_state||'—')+'</p><p>First posted: '+esc(rs.results_first_posted||'—')+'</p></section>'+
      '<section><h4>Publications</h4><p>'+esc(p.reference_count||0)+' linked references · '+esc(p.results_reference_count||0)+' result references · '+esc(p.retraction_signal_count||0)+' retraction signals</p><p>'+esc(p.absence_notice||'Registry-linked publications are preserved separately from posted registry results.')+'</p></section></div>'+
      '<h4>Primary outcomes</h4><ul>'+arr(t.primary_outcomes).slice(0,10).map(function(o){return '<li><strong>'+esc(o.measure||'Outcome')+'</strong> — '+esc(o.timeFrame||'time frame not stated')+'</li>';}).join('')+'</ul>'+
      '<p><strong>Study completion:</strong> '+esc(dates.study_completion||'—')+'</p></article>';
  }
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-sc-clinical-trials]').forEach(function(root){
      var form=root.querySelector('.sc-clinical-trials__search'), status=root.querySelector('.sc-clinical-trials__status'), results=root.querySelector('.sc-clinical-trials__results'), comp=root.querySelector('.sc-clinical-trials__comparison'), compareBtn=root.querySelector('.sc-clinical-trials__compare');
      function selected(){return Array.from(root.querySelectorAll('[data-ct-select]:checked')).map(function(x){return x.value;});}
      root.addEventListener('change',function(e){if(e.target.matches('[data-ct-select]')){var n=selected().length;compareBtn.disabled=n<2;compareBtn.textContent=n>=2?'Compare Selected ('+n+')':'Compare Selected';}});
      form.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(form), params=new URLSearchParams();fd.forEach(function(v,k){if(String(v).trim())params.set(k,String(v).trim());});params.set('limit','12');if(Array.from(params.keys()).filter(function(k){return k!=='limit';}).length===0){status.textContent='Enter at least one search criterion.';return;}status.textContent='Searching ClinicalTrials.gov…';results.innerHTML='';comp.hidden=true;fetch(root.dataset.searchEndpoint+'?'+params.toString(),{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.detail||'Search failed');return j;});}).then(function(data){var rows=arr(data.results);status.textContent=rows.length+(data.total!=null?' of '+data.total:'')+' trials returned.';results.innerHTML=rows.map(card).join('')||'<p>No matching trials found.</p>';compareBtn.disabled=true;compareBtn.textContent='Compare Selected';}).catch(function(err){status.textContent=err.message;});});
      compareBtn.addEventListener('click',function(){var ids=selected();if(ids.length<2)return;if(ids.length>8){status.textContent='Select no more than eight trials.';return;}status.textContent='Building descriptive comparison…';fetch(root.dataset.compareEndpoint+'?nct_ids='+encodeURIComponent(ids.join(',')),{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.detail||'Comparison failed');return j;});}).then(function(data){comp.innerHTML=comparison(data);comp.hidden=false;status.textContent='Comparison ready.';comp.scrollIntoView({behavior:'smooth',block:'nearest'});}).catch(function(err){status.textContent=err.message;});});
      root.addEventListener('click',function(e){var btn=e.target.closest('[data-ct-detail]');if(btn){status.textContent='Loading '+btn.dataset.ctDetail+'…';fetch(root.dataset.detailEndpoint+encodeURIComponent(btn.dataset.ctDetail),{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.detail||'Detail failed');return j;});}).then(function(data){comp.innerHTML=detail(data);comp.hidden=false;status.textContent='Structured trial detail loaded.';comp.scrollIntoView({behavior:'smooth',block:'nearest'});}).catch(function(err){status.textContent=err.message;});return;}if(e.target.closest('[data-ct-close]')){comp.hidden=true;comp.innerHTML='';}});
    });
  });
})();
