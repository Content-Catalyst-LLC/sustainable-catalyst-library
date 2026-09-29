from app.linguistic_corpus import build_corpus_package, kwic_from_package, frequency_table_from_package, validate_corpus_payload


def payload():
    return {'title':'Small corpus','documents':[
        {'representation_id':'textrep:'+'a'*32,'source_kind':'original','language_bcp47':'fr','script_iso15924':'Latn','text':'Énergie propre, énergie durable.'},
        {'representation_id':'textrep:'+'b'*32,'source_kind':'transcription','derivation_run_id':'textrun:'+'b'*32,'language_bcp47':'fr','script_iso15924':'Latn','text':'Énergie et climat.'},
    ]}


def test_corpus_identity_and_kwic_are_deterministic():
    a=build_corpus_package(payload()); b=build_corpus_package(payload())
    assert a['corpus_id']==b['corpus_id']
    x=kwic_from_package(a,'énergie',window_tokens=1)
    y=kwic_from_package(b,'énergie',window_tokens=1)
    assert x['query_fingerprint_sha256']==y['query_fingerprint_sha256']
    assert x['total_matches']==3
    assert all(m['representation_id'].startswith('textrep:') for m in x['matches'])


def test_frequency_and_guardrails_are_descriptive_not_truth_or_importance():
    package=build_corpus_package(payload())
    freq=frequency_table_from_package(package)
    row=next(r for r in freq['rows'] if r['normalized_text']=='énergie')
    assert row['count']==3
    assert freq['guardrails']['frequency_implies_importance'] is False
    invalid=validate_corpus_payload({**payload(),'automatic_pos_tagging':True,'automatic_syntax_parsing':True})
    assert invalid['valid'] is False
    assert 'automatic-pos-tagging-prohibited' in invalid['errors']
    assert 'automatic-syntax-parsing-prohibited' in invalid['errors']
