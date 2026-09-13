from app.energy_systems import EnergySystemsKnowledgeFoundation


def test_v150_manifest_and_spatial_registry():
    es=EnergySystemsKnowledgeFoundation()
    d=es.manifest()
    assert d['subsystem']['version']=='1.5.0'
    assert d['subsystem']['backend_version']=='2.21.0'
    assert d['subsystem']['release']=='Spatial & Global Energy Intelligence'
    s=d['spatial_global_energy_intelligence']
    assert s['version']=='1.5.0'
    assert s['execution_authority']['minimum_version']=='4.41.0'
    assert len(s['source_crosswalk'])==4
    assert s['guardrails']['site_suitability_scoring'] is False
    assert s['guardrails']['automatic_external_fetch'] is False


def test_v150_runtime_registry_certifies_site_intelligence_spatial_analysis():
    es=EnergySystemsKnowledgeFoundation()
    f=es.runtime_framework()
    assert f['version']=='1.5.0'
    assert f['counts']['spatial_analysis_targets']==1
    site=es.runtime_target('site-intelligence')['target']
    assert site['minimum_target_version']=='4.41.0'
    assert site['execution_state']=='certified-spatial-global-energy-intelligence'
    assert site['execution_framework_route']=='/v1/energy-spatial/framework'
    assert site['execution_plan_route']=='/v1/energy-spatial/source-registry'
    assert site['execution_route']=='/v1/energy-spatial/profile'
    assert site['result_validation_route']=='/v1/energy-spatial/validate-result'


def test_v150_spatial_profile_template_requires_source_bound_records():
    es=EnergySystemsKnowledgeFoundation()
    t=es.spatial_profile_template()
    assert t['version']=='1.5.0'
    assert t['site_intelligence_minimum_version']=='4.41.0'
    assert t['template']['review']['human_review_required'] is True
    assert t['template']['records'][0]['source_ref']==''
    assert 'not recommended site assumptions' in t['guardrail']
