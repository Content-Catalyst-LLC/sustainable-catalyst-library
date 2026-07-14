from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
DOCUMENT = PLUGIN / "includes" / "class-sc-library-pdf-to-document.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
JS = PLUGIN / "assets" / "js" / "sc-library-foundation-pages-admin.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_required_files_exist():
    for path in (WRAPPER, DOCUMENT, RELIABILITY, JS, CSS):
        assert path.is_file(), path


def test_reliability_layer_is_loaded_without_replacing_v220():
    wrapper = WRAPPER.read_text(encoding="utf-8")
    assert "class-sc-library-pdf-to-document.php" in wrapper
    assert "class-sc-library-pdf-conversion-reliability.php" in wrapper
    assert "new SC_Library_PDF_To_Document" in wrapper
    assert "new SC_Library_PDF_Conversion_Reliability" in wrapper


def test_resumable_conversion_contract():
    text = RELIABILITY.read_text(encoding="utf-8")
    for marker in (
        "META_SESSION",
        "META_LAST_PAGE",
        "META_TOTAL_PAGES",
        "session_state",
        "resumePage",
        "ajax_store_chunk",
        "ajax_finalize",
        "read_buffer_pages",
        "incomplete_conversion",
        "Cancel Saved Conversion",
    ):
        assert marker in text, marker


def test_large_pdf_and_retry_controls():
    php = RELIABILITY.read_text(encoding="utf-8")
    js = JS.read_text(encoding="utf-8")
    for marker in (
        "sc_library_pdf_conversion_max_bytes",
        "sc_library_pdf_server_extraction_max_bytes",
        "sc_library_pdf_conversion_max_pages",
        "sc_library_pdf_conversion_chunk_characters",
        "requestRetries",
        "Connection interrupted. Retrying",
        "chunkCharacters",
        "disableWorker",
    ):
        assert marker in php or marker in js, marker


def test_duplicate_prevention():
    text = RELIABILITY.read_text(encoding="utf-8")
    for marker in (
        "find_duplicate_document",
        "duplicate_pdf",
        "attachment_checksum",
        "Open existing Knowledge Document",
        "post__not_in",
    ):
        assert marker in text, marker


def test_publishing_requires_pdf_conversion_content_and_review():
    text = RELIABILITY.read_text(encoding="utf-8")
    for marker in (
        "missing_pdf",
        "document_required",
        "conversion_incomplete",
        "review_required",
        "META_REVIEWED",
        "force_draft",
    ):
        assert marker in text, marker


def test_persistent_logs_and_health_output():
    text = RELIABILITY.read_text(encoding="utf-8")
    for marker in (
        "META_LOG",
        "LOG_LIMIT = 50",
        "append_health_logs",
        "v2.2.1 Reliability and Conversion Log",
        "migration audit",
        "Conversion log",
    ):
        assert marker.lower() in text.lower(), marker


def test_heading_reconstruction_uses_pdf_font_metadata():
    php = RELIABILITY.read_text(encoding="utf-8")
    js = JS.read_text(encoding="utf-8")
    for marker in (
        "pageData(items)",
        "fontName",
        "transform[3]",
        "sanitize_lines",
        "heading_level",
        "median",
        "remove_repeated_headers_and_footers",
    ):
        assert marker in php or marker in js, marker


def test_conversion_result_is_persisted_server_side():
    text = RELIABILITY.read_text(encoding="utf-8")
    assert "wp_update_post( wp_slash( $update ), true )" in text
    assert "META_RAW_TEXT" in text
    assert "META_PAGE_MAP" in text
    assert "META_STATUS, 'ready_review'" in text
    assert "save-failed" in text


def test_migration_audit_repairs_existing_records():
    text = RELIABILITY.read_text(encoding="utf-8")
    for marker in (
        "run_migration_audit",
        "sc_library_pdf_reliability_migration_version",
        "assign_default_family",
        "checksums",
        "possible duplicates",
        "META_MIGRATION_AUDIT",
    ):
        assert marker.lower() in text.lower(), marker


def test_v221_routes_and_versions():
    document = DOCUMENT.read_text(encoding="utf-8")
    wrapper = WRAPPER.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.2.1'" in document
    assert "SC_LIBRARY_VERSION : '2.2.1'" in document
    assert "SC_LIBRARY_VERSION : '2.2.1'" in wrapper


def test_spartan_reliability_css():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-pdf-reliability" in text
    assert ".sc-pdf-reliability-health" in text
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def main():
    tests = [
        test_required_files_exist,
        test_reliability_layer_is_loaded_without_replacing_v220,
        test_resumable_conversion_contract,
        test_large_pdf_and_retry_controls,
        test_duplicate_prevention,
        test_publishing_requires_pdf_conversion_content_and_review,
        test_persistent_logs_and_health_output,
        test_heading_reconstruction_uses_pdf_font_metadata,
        test_conversion_result_is_persisted_server_side,
        test_migration_audit_repairs_existing_records,
        test_v221_routes_and_versions,
        test_spartan_reliability_css,
    ]
    for test in tests:
        test()
    print(f"PDF Conversion and Publishing Reliability checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
