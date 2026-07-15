from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
JS = PLUGIN / "assets" / "js" / "sc-library-ocr-review.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_required_files_exist():
    for path in (WRAPPER, OCR, RELIABILITY, BULK, REPOSITORY, JS, CSS):
        assert path.is_file(), path


def test_ocr_layer_is_loaded_without_replacing_existing_layers():
    text = WRAPPER.read_text(encoding="utf-8")
    for marker in (
        "class-sc-library-pdf-conversion-reliability.php",
        "class-sc-library-pdf-bulk-import-repair.php",
        "class-sc-library-document-ocr-processing.php",
        "class-sc-library-document-repository-hardening.php",
        "class-sc-library-document-public-repository.php",
        "new SC_Library_Document_OCR_Processing",
    ):
        assert marker in text, marker


def test_page_level_scan_detection():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "META_PAGES",
        "META_PAGE_MAP",
        "META_RAW_TEXT",
        "analyze_document",
        "source_chars",
        "sc_library_ocr_min_source_characters",
        "needs_ocr",
        "not_needed",
    ):
        assert marker in text, marker


def test_persistent_ocr_job_model_and_controls():
    php = OCR.read_text(encoding="utf-8")
    js = JS.read_text(encoding="utf-8")
    for marker in (
        "JOB_TYPE = 'sc_pdf_ocr_job'",
        "META_JOB_ITEMS",
        "META_JOB_STATUS",
        "ajax_next_item",
        "ajax_process_item",
        "lock_time",
        "Pause",
        "Resume",
        "Retry Failed",
        "Cancel",
        "sc_library_v240_control_job",
    ):
        assert marker in php or marker in js, marker


def test_local_tesseract_provider():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "local_tesseract",
        "tesseract",
        "pdftoppm",
        "pdftocairo",
        "parse_tesseract_tsv",
        "sc_library_ocr_raster_dpi",
        "sc_library_ocr_max_page_image_bytes",
        "proc_open",
    ):
        assert marker in text, marker


def test_external_and_custom_provider_contracts():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "SC_LIBRARY_OCR_ENDPOINT",
        "SC_LIBRARY_OCR_API_KEY",
        "X-SC-OCR-Signature",
        "sc_library_ocr_providers",
        "sc_library_ocr_process_page",
        "validate_provider_result",
        "external_endpoint",
    ):
        assert marker in text, marker


def test_confidence_language_and_low_confidence_records():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "META_CONFIDENCE_THRESHOLD",
        "DEFAULT_CONFIDENCE_THRESHOLD = 75",
        "confidence",
        "low_confidence",
        "detect_language_hint",
        "language",
        "warnings",
    ):
        assert marker in text, marker


def test_side_by_side_review_and_manual_correction():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "sc-ocr-review-layout",
        "Original PDF",
        "Readable text",
        "corrected_text",
        "mark_reviewed",
        "Save Page Review",
        "reviewed_at",
        "reviewed_by",
    ):
        assert marker in text, marker


def test_selected_page_reprocessing_and_queue_recovery():
    text = OCR.read_text(encoding="utf-8")
    for marker in (
        "page_numbers[]",
        "Queue Selected Pages",
        "Queue Pages Needing OCR",
        "selection_mode",
        "reclaimable",
        "attempts",
        "Returned to OCR queue",
    ):
        assert marker in text, marker


def test_reviewed_ocr_application_and_public_warning():
    text = OCR.read_text(encoding="utf-8").lower()
    for marker in (
        "apply_ocr_to_document",
        "apply reviewed ocr to document",
        "allow_unreviewed",
        "post_content",
        "meta_public_warning",
        "ocr-derived reading layer",
        "original pdf remains the authoritative source",
        "ready_review",
    ):
        assert marker in text, marker


def test_csv_reports_and_provider_diagnostics():
    text = OCR.read_text(encoding="utf-8").lower()
    for marker in (
        "export_document_ocr_csv",
        "export_ocr_job_csv",
        "content-type: text/csv",
        "local provider diagnostics",
        "external provider contract",
        "wordpress integration hooks",
    ):
        assert marker in text, marker


def test_spartan_ocr_css_and_responsive_review():
    text = CSS.read_text(encoding="utf-8")
    for marker in (
        ".sc-ocr-review-layout",
        ".sc-ocr-review-pdf",
        ".sc-ocr-review-text",
        ".sc-ocr-page-inventory",
        ".sc-pdf-document__ocr-notice",
        "@media (max-width: 1080px)",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_version_and_route_compatibility():
    ocr = OCR.read_text(encoding="utf-8")
    repository = REPOSITORY.read_text(encoding="utf-8")
    wrapper = WRAPPER.read_text(encoding="utf-8")
    assert "public const VERSION = '2.4.1'" in ocr
    assert "public const ROUTE_VERSION = '2.3.0'" in repository
    assert "SC_LIBRARY_VERSION : '3.3.0'" in wrapper


def main():
    tests = [
        test_required_files_exist,
        test_ocr_layer_is_loaded_without_replacing_existing_layers,
        test_page_level_scan_detection,
        test_persistent_ocr_job_model_and_controls,
        test_local_tesseract_provider,
        test_external_and_custom_provider_contracts,
        test_confidence_language_and_low_confidence_records,
        test_side_by_side_review_and_manual_correction,
        test_selected_page_reprocessing_and_queue_recovery,
        test_reviewed_ocr_application_and_public_warning,
        test_csv_reports_and_provider_diagnostics,
        test_spartan_ocr_css_and_responsive_review,
        test_version_and_route_compatibility,
    ]
    for test in tests:
        test()
    print(f"OCR and Scanned Document Processing checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
