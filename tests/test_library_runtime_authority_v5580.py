from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'library-backend'))
from app import runtime_authority as ra

def read(p): return (ROOT/p).read_text(encoding='utf-8')

def payload(): return {'components':ra.default_component_snapshot(),'clients':ra.default_client_snapshot(),'provenance':{'authority':'test'}}

def test_release_identity_routes_and_plugin():
    assert 'Version: 5.58.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert '__version__ = "2.69.0"' in read('library-backend/app/__init__.py')
    m=read('library-backend/app/main.py')
    assert '/v1/runtime-authority/readiness' in m and '/v1/runtime-authority/certification' in m
    assert 'SC_Library_Runtime_Authority' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')

def test_certification_is_deterministic():
    assert ra.build_certification(payload())['certification_id'] == ra.build_certification(payload())['certification_id']

def test_wordpress_is_not_authoritative():
    c=ra.build_certification(payload())
    assert c['wordpress_role']=='publishing-routing-embed-adapter'
    assert c['clients']['wordpress']['authoritative'] is False
    assert c['clients']['wordpress']['required_for_research_execution'] is False

def test_wordpress_absent_from_authoritative_dependency_graph():
    g=ra.dependency_graph()
    assert g['wordpress_dependency_count']==0
    assert g['wordpress_present_in_authoritative_graph'] is False
    assert 'wordpress' not in g['nodes']

def test_required_runtime_components_certify():
    c=ra.build_certification(payload())
    assert c['authoritative_component_count'] == len(ra.AUTHORITATIVE_COMPONENTS)
    assert c['ready_component_count'] == len(ra.AUTHORITATIVE_COMPONENTS)

def test_missing_authoritative_component_fails():
    p=payload(); p['components']['postgresql']['ready']=False
    v=ra.validate_certification_payload(p)
    assert v['valid'] is False and 'component-not-ready:postgresql' in v['errors']

def test_wordpress_authority_or_dependency_fails():
    p=payload(); p['clients']['wordpress']['authoritative']=True
    assert not ra.validate_certification_payload(p)['valid']
    p=payload(); p['clients']['wordpress']['required_for_research_execution']=True
    assert not ra.validate_certification_payload(p)['valid']

def test_new_capability_cannot_require_wordpress():
    p=payload(); p['new_research_capability_requires_wordpress']=True
    assert not ra.validate_certification_payload(p)['valid']

def test_ownership_boundary():
    o=ra.RESEARCH_OBJECT_OWNERSHIP
    assert o['source-record']=='library' and o['job-execution-state']=='library'
    assert o['publication-editorial-page']=='wordpress' and o['seo-metadata']=='wordpress'

def test_schema_tables():
    s=read('library-backend/app/schema.sql')
    assert 'library_runtime_authority_certifications' in s and 'library_runtime_authority_events' in s

def test_guardrails():
    g=ra.guardrails()
    assert g['library_api_is_authoritative_runtime_boundary']
    assert not g['wordpress_is_authoritative_research_runtime']
    assert not g['wordpress_required_for_research_execution']
    assert not g['new_research_capability_may_require_wordpress']

def test_signed_persistence_and_clean_root():
    s=read('library-backend/app/main.py')
    i=s.index('/v1/admin/runtime-authority-certifications')
    assert 'authorize_write' in s[i:i+1100]
    assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
