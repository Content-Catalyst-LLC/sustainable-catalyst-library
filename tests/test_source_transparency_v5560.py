from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]; sys.path.insert(0,str(ROOT/'library-backend'))
from app import source_transparency as st

def read(p): return (ROOT/p).read_text(encoding='utf-8')
def sig(t='provenance-completeness',kind='ratio',value=0.9):
 return {'source_object_id':'record:1','source_object_type':'record','signal_type':t,'value_kind':kind,'value':value,'observation_basis':'metadata-audit','provenance':{'observer':'library','method':'deterministic'}}
def profile():
 return {'source_object_id':'record:1','source_object_type':'record','signals':[sig(),sig('citation-traceability','boolean',True)],'profile_context':{'source_id':'crossref'}}
def policy():
 return {'owner_id':'user:1','name':'My review policy','scope':'personal','state':'active','rules':[{'signal_type':'provenance-completeness','operator':'lt','value':0.8,'action':'require-human-review','reason':'Low provenance completeness'},{'signal_type':'citation-traceability','operator':'eq','value':False,'action':'flag'}],'default_action':'allow'}

def test_release_identity_routes_and_plugin():
 assert 'Version: 5.56.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
 assert '__version__ = "2.67.0"' in read('library-backend/app/__init__.py')
 m=read('library-backend/app/main.py')
 assert '/v1/source-transparency/readiness' in m and '/v1/source-transparency/evaluate-policy' in m
 assert 'SC_Library_Source_Transparency' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
def test_signal_deterministic(): assert st.build_signal(sig())['signal_id']==st.build_signal(sig())['signal_id']
def test_signal_requires_provenance():
 x=sig(); x['provenance']={}; assert not st.validate_signal(x)['valid']
def test_no_credibility_or_trust_score():
 x=sig(); x['credibility_verdict']='trusted'; assert not st.validate_signal(x)['valid']
 y=sig(); y['trust_score']=0.9; assert not st.validate_signal(y)['valid']
def test_profile_has_no_aggregate_score():
 p=st.build_profile(profile()); assert p['aggregate_quality_score'] is None and p['signal_count']==2
def test_profile_rejects_aggregate_score():
 p=profile(); p['aggregate_quality_score']=0.8; assert not st.validate_profile(p)['valid']
def test_policy_owner_scope_and_rules():
 p=st.build_policy(policy()); assert p['scope']=='personal' and p['owner_id']=='user:1' and len(p['rules'])==2
def test_policy_cannot_override_source_quality():
 p=policy(); p['source_quality_override']='trusted'; assert not st.validate_policy(p)['valid']
def test_policy_evaluation_separate_from_signals():
 result=st.evaluate_policy(policy(),profile()); assert result['disposition']=='allow' and result['source_signals_unchanged'] and result['source_history_unchanged']
def test_guardrails():
 g=st.guardrails(); assert g['source_quality_signals_separate_from_user_trust_choices'] and not g['quality_signal_is_truth'] and not g['system_default_trust_verdict']
def test_worker_and_schema():
 from app.specialized_worker_runtime import PROFILES
 assert 'source.transparency.assess' in PROFILES['python.research']['capabilities']
 s=read('library-backend/app/schema.sql')
 for t in ['library_source_quality_signals','library_source_transparency_profiles','library_user_trust_policies','library_user_trust_policy_events']: assert t in s
def test_signed_admin_and_clean_root():
 s=read('library-backend/app/main.py'); i=s.index('/v1/admin/source-quality-signals'); assert 'authorize_write' in s[i:i+2600]
 assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
