from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]; sys.path.insert(0,str(ROOT/'library-backend'))
from app import cross_civilizational_linking as cc
def read(p): return (ROOT/p).read_text()
def sample(t='corroborates'):
 return {'source':{'object_id':'text:1','object_type':'text-representation','evidence_class':'textual','language_bcp47':'ar','script_iso15924':'Arab','time_context':{'start':'1258'},'geographic_context':{'name':'Baghdad'}},'target':{'object_id':'data:1','object_type':'dataset','evidence_class':'environmental','method':{'method_type':'proxy'},'measurement':{'variable':'precipitation','unit':'z-score','value':-1.2}},'link_type':t,'basis':{'basis_type':'reviewed-comparison'},'interpretation_boundary':'Distinct evidence classes; correspondence does not prove causality.','review_state':'human-reviewed'}
def test_release_identity_routes_and_plugin(): assert 'Version: 5.55.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php') and '__version__ = "2.66.0"' in read('library-backend/app/__init__.py') and '/v1/cross-civilizational-linking/readiness' in read('library-backend/app/main.py')
def test_deterministic(): assert cc.build_link(sample())['link_id']==cc.build_link(sample())['link_id']
def test_boundary_required(): p=sample(); p['interpretation_boundary']=''; assert not cc.validate_link_payload(p)['valid']
def test_guardrails(): g=cc.guardrails(); assert not g['link_is_evidence_truth'] and not g['link_implies_civilizational_equivalence'] and g['source_traditions_remain_distinct']
def test_measurement_unit_warning(): p=sample('measurement-correspondence'); p['source']['measurement']={'variable':'temperature','unit':'C','value':20}; p['target']['measurement']={'variable':'temperature','unit':'K','value':293.15}; x=cc.validate_link_payload(p); assert x['valid'] and x['warnings']
def test_package(): a=sample(); b=sample('contrasts'); b['target']['object_id']='data:2'; assert cc.build_link_package({'links':[a,b]})['link_count']==2
def test_schema(): s=read('library-backend/app/schema.sql'); assert 'library_cross_civilizational_links' in s and 'library_cross_civilizational_link_events' in s
def test_worker(): from app.specialized_worker_runtime import PROFILES; assert 'knowledge.link.cross-civilizational' in PROFILES['python.research']['capabilities']
def test_signed_admin(): s=read('library-backend/app/main.py'); i=s.index('/v1/admin/cross-civilizational-links'); assert 'authorize_write' in s[i:i+1800]
def test_alignment_reference(): p=sample(); p['source']['alignment_matrix_id']='alignment:1'; assert cc.build_link(p)['source']['alignment_matrix_id']=='alignment:1'
def test_clean_root(): assert {p.name for p in ROOT.iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}
