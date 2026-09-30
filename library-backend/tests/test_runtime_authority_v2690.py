from app import runtime_authority as ra

def payload(): return {'components':ra.default_component_snapshot(),'clients':ra.default_client_snapshot()}

def test_default_certification():
    x=ra.build_certification(payload())
    assert x['version']=='5.58.0' and x['backend_version']=='2.69.0' and x['state']=='certified'

def test_dependency_graph_excludes_wordpress():
    g=ra.dependency_graph(); assert g['wordpress_dependency_count']==0 and not g['wordpress_present_in_authoritative_graph']

def test_wordpress_guardrail():
    g=ra.guardrails(); assert not g['wordpress_is_authoritative_research_runtime'] and not g['wordpress_required_for_research_execution']

def test_research_ownership():
    assert ra.RESEARCH_OBJECT_OWNERSHIP['research-artifact']=='library' and ra.RESEARCH_OBJECT_OWNERSHIP['marketing-copy']=='wordpress'

def test_validation_rejects_wordpress_dependency():
    p=payload(); p['clients']['wordpress']['required_for_research_execution']=True
    assert ra.validate_certification_payload(p)['valid'] is False
