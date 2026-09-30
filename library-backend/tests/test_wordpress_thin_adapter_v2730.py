from app.wordpress_thin_adapter import contract, readiness, validate_adapter_claim

def test_contract_identity_and_role():
    c=contract()
    assert c['schema']=='sc-library-wordpress-thin-adapter/1.0'
    assert c['library_version']=='5.62.0' and c['backend_version']=='2.73.0'
    assert c['adapter']['role']=='thin-adapter' and c['adapter']['authoritative'] is False

def test_allowed_and_prohibited_are_disjoint():
    c=contract()
    assert not (set(c['allowed_responsibilities']) & set(c['prohibited_authorities']))
    assert 'public-routing' in c['allowed_responsibilities']
    assert 'session-authority' in c['prohibited_authorities']

def test_runtime_has_zero_wordpress_authoritative_dependencies():
    c=readiness()
    assert c['state']=='ready' and c['wordpress_dependency_count']==0
    assert c['guardrails']['new_research_capability_may_require_wordpress'] is False

def test_identity_handoff_is_non_authoritative():
    c=contract()['identity_handoff']
    assert c['supported_as_adapter_role'] is True
    assert c['authoritative'] is False and c['wordpress_cookie_is_library_session'] is False

def test_invalid_authority_claim_is_rejected():
    v=validate_adapter_claim({'wordpress_authoritative':True,'wordpress_owns_sessions':True})
    assert v['valid'] is False and 'wordpress-authority-prohibited' in v['errors']
