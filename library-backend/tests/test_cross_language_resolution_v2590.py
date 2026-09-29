from app.cross_language_resolution import build_authority_package, build_resolution_case, normalize_name_key, validate_decision_payload

def fixture():
    return {'entities':[{'entity_id':'entity:kyiv','entity_type':'place','canonical_name':'Київ','names':[{'text':'Київ','relation_type':'canonical','language_bcp47':'uk','script_iso15924':'Cyrl'},{'text':'Kyiv','relation_type':'transliteration','language_bcp47':'en','script_iso15924':'Latn','transliteration_system':'official'},{'text':'Kiev','relation_type':'historical','language_bcp47':'en','script_iso15924':'Latn','valid_to_year':1991}]}]}

def test_authority_package_keeps_cross_script_forms():
    p=build_authority_package(fixture()); assert p['entity_count']==1 and p['name_form_count']>=3
    assert {x['script_iso15924'] for x in p['entities'][0]['names']}=={'Cyrl','Latn'}

def test_temporal_case_is_deterministic():
    a=build_resolution_case(fixture(),{'name':'Kiev','year':1980}); b=build_resolution_case(fixture(),{'name':'Kiev','year':1980}); assert a['case_id']==b['case_id']; assert a['candidates'][0]['temporal_status']=='within-window'

def test_normalization_is_comparison_only():
    assert normalize_name_key('  São-Paulo ')=='são paulo'

def test_decision_does_not_claim_truth():
    c=build_resolution_case(fixture(),{'name':'Kyiv'}); cid=c['candidates'][0]['candidate_id']; d=validate_decision_payload(c,{'state':'accepted','selected_candidate_id':cid,'rationale':'authority review'}); assert d['valid']; assert d['guardrails']['decision_is_truth'] is False
