from app.original_language_corpus import build_capture_package, validate_capture_payload


def test_arabic_original_and_nfc_derivative_are_separate():
    payload = {
        "source_id": "crossref",
        "source_record_id": "arabic-1",
        "language_bcp47": "ar",
        "script_iso15924": "Arab",
        "raw_text": "النص الأصلي",
        "create_normalized_derivative": True,
    }
    package = build_capture_package(payload)
    assert package["representations"][0]["canonical_original"] is True
    assert package["representations"][1]["derived"] is True
    assert package["guardrails"]["translation_is_derived_representation"] is True


def test_automatic_translation_is_not_allowed():
    result = validate_capture_payload({
        "language_bcp47": "de",
        "script_iso15924": "Latn",
        "raw_text": "Originaltext",
        "automatic_translation": True,
    })
    assert result["valid"] is False
    assert "automatic-translation-prohibited" in result["errors"]
