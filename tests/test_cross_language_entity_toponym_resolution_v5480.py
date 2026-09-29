from pathlib import Path
import importlib.util, sys, types
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def load_module():
    pkg=types.ModuleType('app'); pkg.__path__=[str(ROOT/'library-backend'/'app')]; sys.modules['app']=pkg
    path=ROOT/'library-backend'/'app'/'cross_language_resolution.py'; spec=importlib.util.spec_from_file_location('app.cross_language_resolution',path); mod=importlib.util.module_from_spec(spec); assert spec and spec.loader; sys.modules['app.cross_language_resolution']=mod; spec.loader.exec_module(mod); return mod

def authority_fixture():
    return {'title':'Historical places','entities':[
      {'entity_id':'entity:istanbul','entity_type':'place','canonical_name':'İstanbul','country_code':'TUR','names':[
        {'text':'İstanbul','relation_type':'canonical','language_bcp47':'tr','script_iso15924':'Latn','valid_from_year':1930},
        {'text':'Istanbul','relation_type':'transliteration','language_bcp47':'en','script_iso15924':'Latn','transliteration_system':'declared-English'},
        {'text':'Constantinople','relation_type':'historical','language_bcp47':'en','script_iso15924':'Latn','valid_to_year':1929},
        {'text':'Κωνσταντινούπολις','relation_type':'historical','language_bcp47':'el','script_iso15924':'Grek','valid_to_year':1929},
      ]},
      {'entity_id':'entity:other','entity_type':'place','canonical_name':'Istanbul Township','names':[{'text':'Istanbul','relation_type':'alias','language_bcp47':'en','script_iso15924':'Latn'}]},
    ]}

def test_release_identity_routes_and_health_capabilities():
    assert 'Version: 5.48.0' in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '5.48.0');" in read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert '__version__ = "2.59.0"' in read('library-backend/app/__init__.py')
    main=read('library-backend/app/main.py')
    for route in ['/v1/cross-language-resolution/readiness','/v1/cross-language-resolution/validate-authority','/v1/cross-language-resolution/candidates','/v1/cross-language-resolution/case','/v1/admin/cross-language-authorities','/v1/admin/cross-language-resolution-cases']:
        assert route in main
    assert 'entity_resolution_automatic_merge": False' in main

def test_schema_adds_authorities_name_forms_cases_candidates_decisions():
    s=read('library-backend/app/schema.sql')
    for table in ['library_cross_language_entities','library_entity_name_forms','library_entity_resolution_cases','library_entity_resolution_candidates','library_entity_resolution_decisions']:
        assert f'CREATE TABLE IF NOT EXISTS {table}' in s
    assert 'library_entity_name_forms_historical_idx' in s
    assert 'state = \'accepted\'' in s

def test_multilingual_name_forms_are_deterministic_and_original_text_preserved():
    m=load_module(); a=m.build_authority_package(authority_fixture()); b=m.build_authority_package(authority_fixture())
    assert a['authority_fingerprint_sha256']==b['authority_fingerprint_sha256']
    forms=a['entities'][0]['names']; greek=next(x for x in forms if x['script_iso15924']=='Grek')
    assert greek['text']=='Κωνσταντινούπολις'
    assert greek['normalized_key'] != ''
    assert m.guardrails()['automatic_transliteration'] is False

def test_declared_transliteration_is_used_but_not_generated_automatically():
    m=load_module(); a=m.build_authority_package(authority_fixture())
    r=m.generate_candidates(a,{'name':'Istanbul','language_bcp47':'en','script_iso15924':'Latn'},limit=10)
    top=next(c for c in r['candidates'] if c['entity_id']=='entity:istanbul')
    assert 'declared-transliteration-form' in top['signals']
    assert top['candidate_is_resolved_identity'] is False and top['score_is_probability'] is False

def test_historical_toponym_year_changes_temporal_signal_without_erasing_candidate():
    m=load_module(); a=m.build_authority_package(authority_fixture())
    old=m.generate_candidates(a,{'name':'Constantinople','year':1910},limit=10)['candidates'][0]
    new=m.generate_candidates(a,{'name':'Constantinople','year':1950},limit=10)['candidates'][0]
    assert old['temporal_status']=='within-window' and 'historical-validity-match' in old['signals']
    assert new['temporal_status']=='outside-window' and 'historical-validity-conflict' in new['signals']
    assert new['entity_id']==old['entity_id']=='entity:istanbul'

def test_ambiguity_is_preserved_and_no_automatic_decision_exists():
    m=load_module(); case=m.build_resolution_case(authority_fixture(),{'name':'Istanbul','language_bcp47':'en'},limit=10)
    assert case['candidate_count']==2
    assert case['ambiguity']['automatic_decision'] is False
    assert 'recommended_candidate_id' not in case and 'resolved_entity_id' not in case

def test_explicit_decision_validation_requires_candidate_and_rationale():
    m=load_module(); case=m.build_resolution_case(authority_fixture(),{'name':'Istanbul'},limit=10)
    cid=case['candidates'][0]['candidate_id']
    bad=m.validate_decision_payload(case,{'state':'accepted'}); assert not bad['valid']
    good=m.validate_decision_payload(case,{'state':'accepted','selected_candidate_id':cid,'adjudicator':'human-review','rationale':'Checked authority record and historical context.'})
    assert good['valid'] and good['guardrails']['decision_is_truth'] is False

def test_unsafe_automatic_resolution_and_translation_are_rejected():
    m=load_module(); p=authority_fixture(); p['automatic_resolution']=True; p['automatic_translation']=True
    v=m.validate_authority_payload(p); assert not v['valid']
    assert 'automatic-resolution-prohibited' in v['errors'] and 'automatic-translation-prohibited' in v['errors']

def test_research_corpus_builder_exports_resolution_lineage_fields():
    s=read('library-backend/app/research_corpus_builder.py')
    for f in ['entity_resolution_case_ids','resolved_entity_ids','entity_resolution_decision_ids']: assert f in s

def test_wordpress_surface_is_readiness_only_and_admin_candidate_proxies_are_gated():
    wp=read('sustainable-catalyst-library/includes/class-sc-library-cross-language-resolution.php')
    assert 'Candidate rank is not identity, evidence, or truth' in wp
    backend=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    for route in ['/backend/cross-language-resolution/readiness','/backend/cross-language-resolution/validate-authority','/backend/cross-language-resolution/candidates','/backend/cross-language-resolution/case']: assert route in backend
    assert "current_user_can('manage_options')" in backend
