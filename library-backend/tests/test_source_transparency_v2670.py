from app.source_transparency import build_signal,build_profile,build_policy,evaluate_policy,guardrails,validate_signal

def signal(): return {'source_object_id':'x','signal_type':'metadata-completeness','value_kind':'ratio','value':0.75,'provenance':{'method':'field-count'}}
def profile(): return {'source_object_id':'x','signals':[signal()]}
def policy(): return {'owner_id':'u','name':'review','rules':[{'signal_type':'metadata-completeness','operator':'lt','value':0.8,'action':'require-human-review'}]}
def test_signal_contract(): assert build_signal(signal())['schema']=='sc-library-source-quality-signal/1.0'
def test_profile_contract(): assert build_profile(profile())['schema']=='sc-library-source-transparency-profile/1.0'
def test_policy_contract(): assert build_policy(policy())['schema']=='sc-library-user-trust-policy/1.0'
def test_policy_evaluation(): assert evaluate_policy(policy(),profile())['disposition']=='require-human-review'
def test_signal_provenance_required():
 x=signal(); x['provenance']={}; assert not validate_signal(x)['valid']
def test_guardrails():
 g=guardrails(); assert g['source_quality_signals_separate_from_user_trust_choices'] and not g['user_trust_policy_implies_truth'] and not g['quality_signal_is_credibility_verdict']
