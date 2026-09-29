import base64
from app.ocr_htr_transcription_lineage import build_derivation_package, validate_derivation_payload


def test_pdf_ocr_lineage_is_derived_and_reproducibly_identified():
    payload={
      'derivation_kind':'ocr','media_type':'application/pdf','source_payload_base64':base64.b64encode(b'%PDF-fake').decode(),
      'language_bcp47':'de','script_iso15924':'Latn','output_text':'Erkannter Text',
      'engine':{'provider':'local','name':'ocr-engine','version':'1','model':'deu-v1'},
      'segments':[{'sequence':1,'page_number':1,'text':'Erkannter Text','confidence':0.88}]
    }
    a=build_derivation_package(payload); b=build_derivation_package(payload)
    assert a['run_id']==b['run_id']
    assert a['output_representation']['derived'] is True
    assert a['output_representation']['canonical_original'] is False
    assert a['guardrails']['ocr_htr_transcription_is_evidence_truth'] is False


def test_transcription_bad_time_range_and_automatic_translation_rejected():
    result=validate_derivation_payload({
      'derivation_kind':'transcription','source_asset_id':'srcasset:'+'f'*32,'language_bcp47':'en',
      'output_text':'hello','engine':{'provider':'local','name':'asr'},'automatic_translation':True,
      'segments':[{'sequence':1,'text':'hello','start_ms':500,'end_ms':100}]
    })
    assert result['valid'] is False
    assert 'automatic-translation-prohibited' in result['errors']
    assert 'segment-1-time-range-invalid' in result['errors']
