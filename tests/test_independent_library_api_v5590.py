from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import independent_api as api

def read(p): return (ROOT/p).read_text(encoding='utf-8')

def test_release_identity_routes_and_plugin():
    assert 'Version: 5.59.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert '__version__ = "2.70.0"' in read('library-backend/app/__init__.py')
    m=read('library-backend/app/main.py')
    for route in ['/api/library/v1/service','/api/library/v1/readiness','/api/library/v1/search','/api/library/v1/records/{record_id:path}','/api/library/v1/research-jobs']: assert route in m
    assert 'SC_Library_API_V1' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')

def test_service_contract_is_deterministic_and_stable():
    a=api.service_contract(); b=api.service_contract(); assert a['contract_id']==b['contract_id'] and a['state']=='stable'; assert a['api_version']=='1.0' and a['base_path']=='/api/library/v1'

def test_wordpress_is_not_api_dependency():
    c=api.service_contract(); assert c['wordpress']=={'role':'client-adapter','required':False,'authoritative':False}; assert api.guardrails()['api_v1_independent_of_wordpress']; assert not api.guardrails()['wordpress_required_for_api_v1']

def test_route_catalog_is_versioned_and_inside_base_path():
    routes=api.route_catalog(); assert len(routes)>=15; assert all(r['schema']=='sc-library-api-route/1.0' for r in routes); assert all(r['path'].startswith('/api/library/v1') for r in routes)

def test_capability_families_cover_research_stack():
    f=api.capability_catalog()['families']; [(_ for _ in ()).throw(AssertionError(n)) if n not in f else None for n in ['discovery','federation','language','research-execution','artifacts','transparency','cross-civilizational','platform-core']]

def test_error_contract():
    x=api.error_envelope('bad-request','Bad request',status=400,details={'field':'q'}); assert x['schema']=='sc-library-api-error/1.0' and x['error']['status']==400 and x['error']['details']['field']=='q'

def test_page_contract():
    x=api.page_envelope([1,2],limit=2,offset=0,total=5); assert x['schema']=='sc-library-api-page/1.0' and x['page']['has_more'] and x['page']['next_offset']==2

def test_breaking_change_and_wordpress_dependency_rejected():
    assert not api.validate_service_contract({'breaking_changes_within_v1':True})['valid']; assert not api.validate_service_contract({'wordpress_required':True})['valid']; assert not api.validate_service_contract({'wordpress_authoritative':True})['valid']

def test_signed_mutation_routes_are_declared():
    routes={r['name']:r for r in api.route_catalog()}; assert routes['submit-research-job']['access']=='signed-service'; assert routes['persist-service-contract']['access']=='signed-admin'

def test_schema_and_contract_tables():
    s=read('library-backend/app/schema.sql'); assert 'library_api_service_contracts' in s and 'library_api_service_contract_events' in s
    for n in ['library-api-service-contract.json','library-api-readiness.json','library-api-error.json','library-api-page.json','library-api-route.json']: assert (ROOT/'docs/schemas'/n).exists()
    assert (ROOT/'docs/library-api-v1-openapi.json').exists()

def test_legacy_routes_not_silently_declared_stable_api_v1():
    c=api.service_contract(); assert '/v1/*' in c['compatibility']['legacy_routes']; assert not api.guardrails()['legacy_v1_routes_are_api_v1_contract']

def test_clean_root():
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
