from app.translation_alignment import build_alignment_matrix, guardrails, validate_alignment_payload


def payload():
    return {
      'source':{'representation_id':'s','representation_kind':'original','language_bcp47':'ar','script_iso15924':'Arab','text':'سلام'},
      'target':{'representation_id':'t','representation_kind':'transliteration','language_bcp47':'ar-Latn','script_iso15924':'Latn','text':'salam','transformation_id':'tr'},
      'transformation_kind':'transliteration','transformation_id':'tr','alignment_method':'manual',
      'links':[{'sequence':1,'source_spans':[{'start':0,'end':4,'text':'سلام'}],'target_spans':[{'start':0,'end':5,'text':'salam'}],'relation_type':'aligned','review_state':'human-reviewed'}]
    }

def test_valid_matrix():
    x=build_alignment_matrix(payload()); assert x['schema']=='sc-library-translation-transliteration-alignment-matrix/1.0' and x['link_count']==1

def test_exact_text_binding():
    p=payload(); p['target']['text_sha256']='0'*64; x=validate_alignment_payload(p); assert x['valid'] is False and 'target-text-sha256-mismatch' in x['errors']

def test_prohibits_generation():
    p=payload(); p['generate_transliteration']=True; x=validate_alignment_payload(p); assert x['valid'] is False

def test_guardrails():
    g=guardrails(); assert g['original_language_remains_canonical'] is True and g['alignment_implies_evidence_equivalence'] is False and g['automatic_platform_core_promotion'] is False

def test_many_to_many_supported():
    p=payload(); p['source']['text']='ab cd'; p['target']['text']='xy zw'; p['links']=[{'sequence':1,'source_spans':[{'start':0,'end':2},{'start':3,'end':5}],'target_spans':[{'start':0,'end':2},{'start':3,'end':5}],'relation_type':'aligned'}]; x=build_alignment_matrix(p); assert x['links'][0]['cardinality']=='2:2'

def test_worker_contract_present():
    from app.specialized_worker_runtime import PROFILES
    assert 'language.align' in PROFILES['python.research']['capabilities']
