from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]; sys.path.insert(0,str(ROOT/'library-backend'))
from app import global_knowledge_federation as gkf

def read(p): return (ROOT/p).read_text(encoding='utf-8')
def payload(): return {'components':gkf.default_component_snapshot(),'scope':{'mode':'global'},'provenance':{'authority':'test'}}
def test_release_identity_routes_and_plugin():
 assert 'Version: 5.57.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php'); assert '__version__ = "2.68.0"' in read('library-backend/app/__init__.py'); m=read('library-backend/app/main.py'); assert '/v1/global-knowledge-federation/readiness' in m and '/v1/global-knowledge-federation/certification' in m; assert 'SC_Library_Global_Knowledge_Federation' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
def test_certification_is_deterministic(): assert gkf.build_certification(payload())['certification_id']==gkf.build_certification(payload())['certification_id']
def test_all_required_components_certified(): c=gkf.build_certification(payload()); assert c['component_count']==13 and c['ready_component_count']==13 and c['state']=='certified'
def test_missing_component_fails(): p=payload(); p['components']['translation-transliteration-alignment']['ready']=False; assert not gkf.validate_certification_payload(p)['valid']
def test_original_language_guardrail(): assert gkf.guardrails()['original_language_is_canonical'] and gkf.guardrails()['translation_is_derived_representation']
def test_trust_quality_separation_guardrail(): assert gkf.guardrails()['source_quality_signals_separate_from_user_trust_choices']
def test_no_truth_or_core_promotion(): g=gkf.guardrails(); assert not g['federation_membership_implies_evidence_truth'] and not g['automatic_platform_core_promotion']
def test_worker_capability():
 from app.specialized_worker_runtime import PROFILES
 assert 'federation.certify.global' in PROFILES['python.research']['capabilities']
def test_schema_tables():
 s=read('library-backend/app/schema.sql'); assert 'library_global_knowledge_federation_certifications' in s and 'library_global_knowledge_federation_events' in s
def test_existing_milestone_dependencies_remain():
 for p in ['library-backend/app/global_source_federation.py','library-backend/app/original_language_corpus.py','library-backend/app/translation_alignment.py','library-backend/app/cross_civilizational_linking.py','library-backend/app/source_transparency.py','library-backend/app/distributed_compute_broker.py']: assert (ROOT/p).exists()
def test_signed_persistence_and_clean_root():
 s=read('library-backend/app/main.py'); i=s.index('/v1/admin/global-knowledge-federation-certifications'); assert 'authorize_write' in s[i:i+1000]; assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
def test_certification_contract(): assert gkf.build_certification(payload())['schema']=='sc-library-global-knowledge-federation-certification/1.0'
