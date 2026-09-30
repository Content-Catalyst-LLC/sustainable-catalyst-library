from app.cross_civilizational_linking import build_link,guardrails,validate_link_payload
def p(): return {'source':{'object_id':'a','object_type':'text-representation','evidence_class':'textual'},'target':{'object_id':'b','object_type':'dataset','evidence_class':'environmental'},'link_type':'contextualizes','basis':{'type':'reviewed'},'interpretation_boundary':'Context only.'}
def test_valid(): assert build_link(p())['schema']=='sc-library-cross-civilizational-evidence-link/1.0'
def test_basis_boundary(): x=p(); x['basis']={}; x['interpretation_boundary']=''; assert not validate_link_payload(x)['valid']
def test_promotion(): x=p(); x['promote_to_core']=True; assert not validate_link_payload(x)['valid']
def test_guardrails(): assert guardrails()['source_traditions_remain_distinct'] and not guardrails()['link_is_evidence_truth']
def test_measurements_required(): x=p(); x['link_type']='measurement-correspondence'; assert not validate_link_payload(x)['valid']
