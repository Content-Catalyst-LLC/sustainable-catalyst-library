from pathlib import Path
import base64
import importlib.util
import sys
import types

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')

def load_module():
    pkg = types.ModuleType('app'); pkg.__path__=[str(ROOT/'library-backend'/'app')]; sys.modules['app']=pkg
    db = types.ModuleType('app.db'); db.get_pool=lambda: (_ for _ in ()).throw(RuntimeError('no-db-test')); sys.modules['app.db']=db
    fed = types.ModuleType('app.global_source_federation')
    class Registry:
        def source(self, source_id):
            if source_id not in {'crossref','pubmed','internetarchive'}: raise KeyError(source_id)
            return {'source_id':source_id}
    fed.registry=Registry(); sys.modules['app.global_source_federation']=fed
    path=ROOT/'library-backend'/'app'/'ocr_htr_transcription_lineage.py'
    spec=importlib.util.spec_from_file_location('app.ocr_htr_transcription_lineage',path)
    mod=importlib.util.module_from_spec(spec); assert spec and spec.loader
    sys.modules['app.ocr_htr_transcription_lineage']=mod; spec.loader.exec_module(mod); return mod

def test_release_identity_and_routes():
    plugin=read('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.46.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.46.0');" in plugin
    assert '__version__ = "2.57.0"' in read('library-backend/app/__init__.py')
    main=read('library-backend/app/main.py')
    for route in ['/v1/ocr-htr-transcription/readiness','/v1/ocr-htr-transcription/validate','/v1/ocr-htr-transcription/package','/v1/admin/ocr-htr-transcription/runs']:
        assert route in main

def test_schema_adds_media_assets_runs_segments_and_allows_media_derived_representations():
    schema=read('library-backend/app/schema.sql')
    assert 'CREATE TABLE IF NOT EXISTS library_source_media_assets' in schema
    assert 'CREATE TABLE IF NOT EXISTS library_text_derivation_runs' in schema
    assert 'CREATE TABLE IF NOT EXISTS library_text_derivation_segments' in schema
    assert 'ALTER TABLE library_text_representations ALTER COLUMN capture_id DROP NOT NULL' in schema
    assert 'source_asset_id text REFERENCES library_source_media_assets' in schema
    assert "derivation_kind IN ('ocr','htr','transcription')" in schema
    assert 'confidence double precision' in schema

def test_ocr_package_preserves_media_hash_engine_identity_and_segment_geometry():
    mod=load_module()
    source=b'\x89PNG\r\n\x1a\nFAKE-SCAN-BYTES'
    payload={
      'derivation_kind':'ocr','source_id':'internetarchive','source_record_id':'scan-1','source_uri':'https://example.test/scan/1',
      'media_type':'image/png','source_payload_base64':base64.b64encode(source).decode('ascii'),
      'language_bcp47':'fr','script_iso15924':'Latn','output_text':'Texte reconnu',
      'engine':{'provider':'local','name':'tesseract','version':'5.4.1','model':'fra'},
      'parameters':{'psm':6},'confidence_summary':{'mean':0.92},
      'segments':[{'sequence':1,'segment_kind':'line','page_number':1,'bounding_box':[0.1,0.2,0.7,0.1],'text':'Texte reconnu','confidence':0.92}]
    }
    a=mod.build_derivation_package(payload); b=mod.build_derivation_package(payload)
    assert a['run_id']==b['run_id']
    assert a['source_asset']['raw_payload_sha256']==a['source_asset_sha256']
    assert a['source_asset']['raw_byte_length']==len(source)
    assert a['engine']['fingerprint_sha256']==b['engine']['fingerprint_sha256']
    assert a['output_representation']['representation_kind']=='ocr'
    assert a['output_representation']['canonical_original'] is False and a['output_representation']['derived'] is True
    assert a['segments'][0]['bounding_box']==[0.1,0.2,0.7,0.1]
    assert a['guardrails']['confidence_is_truth_probability'] is False

def test_htr_and_transcription_are_distinct_derivation_kinds():
    mod=load_module()
    common={'source_asset_id':'srcasset:'+'1'*32,'source_asset_sha256':'a'*64,'language_bcp47':'en','script_iso15924':'Latn','engine':{'provider':'local','name':'engine'},'output_text':'text'}
    htr=mod.build_derivation_package({**common,'derivation_kind':'htr'})
    tr=mod.build_derivation_package({**common,'derivation_kind':'transcription','segments':[{'sequence':1,'text':'hello','start_ms':0,'end_ms':1250,'speaker_label':'speaker-1'}]})
    assert htr['output_representation']['representation_kind']=='htr'
    assert tr['output_representation']['representation_kind']=='transcription'
    assert tr['segments'][0]['start_ms']==0 and tr['segments'][0]['end_ms']==1250
    assert tr['segments'][0]['speaker_label']=='speaker-1'

def test_confidence_review_and_unsafe_promotion_guardrails():
    mod=load_module()
    base={'derivation_kind':'ocr','source_asset_id':'srcasset:'+'2'*32,'language_bcp47':'en','script_iso15924':'Latn','engine':{'provider':'local','name':'ocr'},'output_text':'text'}
    bad=mod.validate_derivation_payload({**base,'confidence_summary':{'mean':1.2}})
    assert bad['valid'] is False and 'confidence-summary-mean-must-be-between-zero-and-one' in bad['errors']
    unsafe=mod.validate_derivation_payload({**base,'output_is_canonical_original':True,'automatic_translation':True})
    assert unsafe['valid'] is False
    assert 'derived-output-cannot-be-canonical-original' in unsafe['errors']
    assert 'automatic-translation-prohibited' in unsafe['errors']
    assert unsafe['guardrails']['automatic_platform_core_promotion'] is False

def test_source_payload_hash_mismatch_is_rejected():
    mod=load_module()
    x=mod.validate_derivation_payload({'derivation_kind':'ocr','source_payload_base64':base64.b64encode(b'abc').decode(),'source_asset_sha256':'0'*64,'media_type':'image/png','language_bcp47':'en','engine':{'provider':'local','name':'ocr'},'output_text':'x'})
    assert x['valid'] is False and 'source-asset-sha256-does-not-match-payload' in x['errors']

def test_wordpress_surface_is_readiness_only_and_no_raw_media_or_text_storage():
    wp=read('sustainable-catalyst-library/includes/class-sc-library-ocr-htr-transcription-lineage.php')
    assert 'OCR, handwritten-text recognition, and transcription outputs remain derived text objects' in wp
    assert 'source_payload_base64' not in wp
    assert 'output_text' not in wp
    backend=read('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    assert '/backend/ocr-htr-transcription/readiness' in backend
    assert '/backend/ocr-htr-transcription/validate' in backend
    assert '/backend/ocr-htr-transcription/package' in backend

def test_json_schema_supports_text_representation_v11_without_capture_id():
    import json
    schema=json.loads(read('docs/schemas/text-representation.json'))
    assert 'capture_id' not in schema['required']
    assert 'source_asset_id' in schema['properties']
    assert 'sc-library-text-representation/1.1' in schema['properties']['schema']['enum']
