from app import independent_api as api

def test_contract_identity():
    x=api.service_contract(); assert x['library_version']=='5.59.0' and x['backend_version']=='2.70.0' and x['api_version']=='1.0'

def test_api_is_wordpress_independent():
    x=api.guardrails(); assert x['api_v1_independent_of_wordpress'] and not x['wordpress_required_for_api_v1']

def test_routes_are_stable_and_scoped():
    r=api.route_catalog(); assert r and all(x['path'].startswith('/api/library/v1') for x in r)

def test_page_envelope_bounds():
    x=api.page_envelope([],limit=1000,offset=-2,total=0); assert x['page']['limit']==100 and x['page']['offset']==0

def test_validation_rejects_breaking_v1():
    assert not api.validate_service_contract({'breaking_changes_within_v1':True})['valid']
