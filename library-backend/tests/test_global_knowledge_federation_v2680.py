from app.global_knowledge_federation import build_certification,default_component_snapshot,guardrails,validate_certification_payload

def p(): return {'components':default_component_snapshot(),'provenance':{'authority':'backend-test'}}
def test_contract(): assert build_certification(p())['schema']=='sc-library-global-knowledge-federation-certification/1.0'
def test_all_components(): assert build_certification(p())['ready_component_count']==13
def test_missing_component_rejected():
 x=p(); x['components']['global-source-federation']['ready']=False; assert not validate_certification_payload(x)['valid']
def test_governance_boundary_cannot_collapse():
 x=p(); x['collapse_governance_boundaries']=True; assert not validate_certification_payload(x)['valid']
def test_guardrails():
 g=guardrails(); assert g['original_language_is_canonical'] and g['source_quality_signals_separate_from_user_trust_choices'] and not g['automatic_platform_core_promotion']
def test_deterministic_identity(): assert build_certification(p())['certification_id']==build_certification(p())['certification_id']
