from pathlib import Path
import importlib.util
import sys
import types

ROOT=Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT/path).read_text(encoding='utf-8')

def load_module():
    pkg=types.ModuleType('app'); pkg.__path__=[str(ROOT/'library-backend'/'app')]; sys.modules['app']=pkg
    path=ROOT/'library-backend'/'app'/'linguistic_corpus.py'
    spec=importlib.util.spec_from_file_location('app.linguistic_corpus',path)
    mod=importlib.util.module_from_spec(spec); assert spec and spec.loader
    sys.modules['app.linguistic_corpus']=mod; spec.loader.exec_module(mod); return mod

def fixture_payload():
    return {
      'title':'Climate discourse sample',
      'documents':[
        {
          'representation_id':'textrep:'+'1'*32,'record_id':'record:1','source_kind':'original',
          'language_bcp47':'en','script_iso15924':'Latn','text':'Climate policy changes climate risk. Policy matters.'
        },
        {
          'representation_id':'textrep:'+'2'*32,'record_id':'record:2','source_kind':'ocr','derivation_run_id':'textrun:'+'2'*32,
          'review_state':'human-reviewed','language_bcp47':'en','script_iso15924':'Latn','text':'Historical climate policy records.'
        }
      ]
    }

def test_release_identity_and_routes():
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.47.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.47.0');" in plugin
    assert '__version__ = "2.58.0"' in read('library-backend/app/__init__.py')
    main=read('library-backend/app/main.py')
    for route in ['/v1/linguistic-corpus/readiness','/v1/linguistic-corpus/validate','/v1/linguistic-corpus/package','/v1/linguistic-corpus/kwic','/v1/linguistic-corpus/frequencies','/v1/admin/linguistic-corpora']:
        assert route in main

def test_schema_adds_corpus_document_token_objects_and_indexes():
    schema=read('library-backend/app/schema.sql')
    assert 'CREATE TABLE IF NOT EXISTS library_linguistic_corpora' in schema
    assert 'CREATE TABLE IF NOT EXISTS library_linguistic_documents' in schema
    assert 'CREATE TABLE IF NOT EXISTS library_linguistic_tokens' in schema
    assert 'representation_id text NOT NULL REFERENCES library_text_representations' in schema
    assert 'tokenizer_spec_fingerprint char(64) NOT NULL' in schema
    assert 'library_linguistic_tokens_corpus_normalized_idx' in schema

def test_tokenizer_is_deterministic_unicode_and_preserves_offsets():
    mod=load_module()
    text="Café naïve — l’été isn't over."
    a=mod.tokenize_text(text); b=mod.tokenize_text(text)
    assert a==b
    assert [t['text'] for t in a][:3]==['Café','naïve','—']
    for token in a:
        assert text[token['start_char']:token['end_char']]==token['text']
    spec=mod.tokenizer_specification()
    assert len(spec['fingerprint_sha256'])==64
    assert spec['language_specific_segmentation'] is False
    assert spec['morphology_inferred'] is False

def test_corpus_package_preserves_original_and_derived_representation_lineage():
    mod=load_module(); a=mod.build_corpus_package(fixture_payload()); b=mod.build_corpus_package(fixture_payload())
    assert a['corpus_id']==b['corpus_id']
    assert a['document_count']==2 and a['token_count']>0
    assert a['documents'][0]['source_kind']=='original'
    assert a['documents'][1]['source_kind']=='ocr'
    assert a['documents'][1]['derivation_run_id']=='textrun:'+'2'*32
    assert a['source_kind_distribution']['ocr']==1
    assert a['guardrails']['tokenization_is_morphological_analysis'] is False

def test_kwic_phrase_query_preserves_context_offsets_and_lineage():
    mod=load_module(); package=mod.build_corpus_package(fixture_payload())
    result=mod.kwic_from_package(package,'climate policy',window_tokens=2)
    assert result['schema']=='sc-library-kwic-result/1.0'
    assert result['total_matches']==2
    first=result['matches'][0]
    assert first['match']=='Climate policy'
    assert first['representation_id']=='textrep:'+'1'*32
    assert first['source_kind']=='original'
    source=fixture_payload()['documents'][0]['text']
    assert source[first['char_start']:first['char_end']]=='Climate policy'
    assert result['guardrails']['kwic_context_establishes_meaning_or_intent'] is False

def test_kwic_case_sensitive_mode_and_frequency_table():
    mod=load_module(); package=mod.build_corpus_package(fixture_payload())
    assert mod.kwic_from_package(package,'Policy',case_sensitive=True)['total_matches']==1
    assert mod.kwic_from_package(package,'policy',case_sensitive=True)['total_matches']==2
    freq=mod.frequency_table_from_package(package,limit=10)
    climate=next(r for r in freq['rows'] if r['normalized_text']=='climate')
    assert climate['count']==3
    assert freq['guardrails']['frequency_implies_importance'] is False

def test_unsafe_automatic_linguistic_inference_is_rejected():
    mod=load_module(); payload=fixture_payload(); payload['automatic_lemmatization']=True; payload['automatic_translation']=True
    result=mod.validate_corpus_payload(payload)
    assert result['valid'] is False
    assert 'automatic-lemmatization-prohibited' in result['errors']
    assert 'automatic-translation-prohibited' in result['errors']
    assert result['guardrails']['automatic_platform_core_promotion'] is False

def test_research_corpus_builder_exports_linguistic_lineage_fields():
    source=read('library-backend/app/research_corpus_builder.py')
    for field in ['linguistic_corpus_id','linguistic_document_id','linguistic_tokenizer_fingerprint','linguistic_token_count']:
        assert field in source

def test_wordpress_surface_is_readiness_focused_and_admin_analysis_proxies_are_gated():
    wp=read('sustainable-catalyst-library/includes/class-sc-library-linguistic-corpus.php')
    assert 'Tokenization is an operational segmentation profile' in wp
    assert 'token_text' not in wp and 'text_content' not in wp
    backend=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    for route in ['/backend/linguistic-corpus/readiness','/backend/linguistic-corpus/validate','/backend/linguistic-corpus/package','/backend/linguistic-corpus/kwic','/backend/linguistic-corpus/frequencies']:
        assert route in backend
    assert "current_user_can('manage_options')" in backend
