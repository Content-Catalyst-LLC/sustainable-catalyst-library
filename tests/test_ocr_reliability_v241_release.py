from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-document-ocr-reliability.php"
CONVERSION = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
JS = PLUGIN / "assets" / "js" / "sc-library-ocr-review.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, OCR, RELIABILITY, CONVERSION, BULK, REPOSITORY, JS, CSS):
        assert path.is_file(), path


def test_reliability_layer_load_order_and_compatibility():
    text = read(WRAPPER)
    markers = (
        "class-sc-library-document-ocr-processing.php",
        "class-sc-library-document-ocr-reliability.php",
        "new SC_Library_Document_OCR_Processing",
        "new SC_Library_Document_OCR_Reliability",
        "class-sc-library-document-repository-hardening.php",
        "class-sc-library-document-public-repository.php",
    )
    for marker in markers:
        assert marker in text, marker
    assert text.index("class-sc-library-document-ocr-processing.php") < text.index("class-sc-library-document-ocr-reliability.php")


def test_pdf_source_checksum_and_reconversion_boundary():
    reliability = read(RELIABILITY)
    ocr = read(OCR)
    for marker in (
        "SOURCE_CHECKSUM_META",
        "RECONVERSION_REQUIRED_META",
        "_sc_document_checksum",
        "SOURCE_ARCHIVE_META",
        "must be reconverted",
        "META_RAW_TEXT",
        "META_PAGE_MAP",
        "META_PAGE_COUNT",
        "reconversion_required",
    ):
        assert marker in reliability or marker in ocr, marker


def test_queue_source_checksum_is_pinned():
    text = read(OCR)
    for marker in (
        "'source_checksum'",
        "SOURCE_CHECKSUM_META",
        "expected_checksum",
        "The attached PDF changed after this OCR job was queued",
        "ensure_source_current",
    ):
        assert marker in text, marker


def test_browser_specific_queue_leases():
    php = read(OCR)
    js = read(JS)
    for marker in (
        "lock_token",
        "lock_client",
        "LEASE_SECONDS",
        "client_id",
        "randomUUID",
        "owned by another queue runner",
    ):
        assert marker in php or marker in js, marker


def test_retry_safe_idempotent_processing():
    text = read(OCR)
    assert "intval( wp_unslash( $_POST['item_index'] ) )" in text
    assert "absint( wp_unslash( $_POST['item_index'] ?? -1 ) )" not in text
    assert "in_array( $item_state, array( 'complete', 'low_confidence', 'reviewed', 'failed', 'unavailable', 'cancelled' )" in text
    assert "wp_send_json_success( $this->job_state( $job_id ) );" in text


def test_queue_cancel_and_retry_sync_page_state():
    text = read(OCR)
    for marker in (
        "sync_page_state_from_job_item",
        "Returned to OCR queue",
        "Cancelled by an administrator",
        "OCR result discarded because the queue was cancelled",
    ):
        assert marker in text, marker


def test_manual_queue_repair_and_stale_lease_recovery():
    php = read(RELIABILITY)
    ui = read(OCR)
    for marker in (
        "repair_job",
        "LAST_REPAIR_META",
        "Stale OCR lease repaired",
        "documents_to_refresh",
        "Repair Queue State",
        "sc_library_v241_repair_ocr_job",
    ):
        assert marker in php or marker in ui, marker


def test_local_provider_discovery_and_cache():
    reliability = read(RELIABILITY)
    ocr = read(OCR)
    for marker in (
        "SC_LIBRARY_TESSERACT_BINARY",
        "SC_LIBRARY_PDF_RASTERIZER_BINARY",
        "getenv( 'PATH' )",
        "sc_library_v241_local_ocr_status",
        "10 * MINUTE_IN_SECONDS",
        "language_unavailable",
    ):
        assert marker in reliability or marker in ocr, marker


def test_external_provider_is_signed_https_and_bounded():
    text = read(RELIABILITY)
    for marker in (
        "SC_LIBRARY_OCR_ENDPOINT",
        "SC_LIBRARY_OCR_API_KEY",
        "wp_http_validate_url",
        "sc_library_ocr_allow_http_endpoint",
        "X-SC-OCR-Signature",
        "limit_response_size",
        "sc_library_ocr_external_max_response_bytes",
        "sc_library_ocr_max_page_characters",
    ):
        assert marker in text, marker


def test_provider_errors_can_short_circuit():
    text = read(OCR)
    assert "if ( is_wp_error( $filtered ) )" in text
    assert "return $filtered;" in text
    assert "ocr_response_too_large" in text


def test_published_document_apply_is_reversible_and_returns_to_draft():
    php = read(OCR)
    reliability = read(RELIABILITY)
    for marker in (
        "confirm_published_draft",
        "published_confirmation_required",
        "snapshot_document",
        "BACKUPS_META",
        "Restore Latest Pre-OCR Backup",
        "restore_latest_backup",
        "'post_status'  => 'publish' === get_post_status( $document_id ) ? 'draft'",
    ):
        assert marker in php or marker in reliability, marker


def test_active_ocr_pages_block_apply():
    php = read(OCR)
    reliability = read(RELIABILITY)
    assert "has_active_pages" in reliability
    assert "active_ocr_job" in php
    assert "Pause, complete, or cancel active OCR pages" in reliability


def test_document_filtering_occurs_before_pagination():
    text = read(OCR)
    for marker in (
        "$query_args['meta_query']",
        "'processing'     => array( 'queued', 'processing' )",
        "'low_confidence' => array( 'needs_review' )",
        "'compare' => 'NOT EXISTS'",
    ):
        assert marker in text, marker
    assert "if ( 'all' !== $filter && $filter !== $state )" not in text


def test_workspace_and_provider_diagnostics_are_cached():
    text = read(OCR)
    for marker in (
        "sc_library_v241_ocr_workspace_totals",
        "delete_transient",
        "set_transient",
        "sc_library_v241_local_ocr_status",
    ):
        assert marker in text, marker


def test_temp_cleanup_and_active_job_preservation():
    reliability = read(RELIABILITY)
    ocr = read(OCR)
    for marker in (
        "TEMP_CLEANUP_HOOK",
        "cleanup_temp_files",
        "sc_library_ocr_temp_retention_seconds",
        "Clean Expired OCR Temporary Files",
        "in_array( $status, array( 'running', 'paused', 'created' )",
    ):
        assert marker in reliability or marker in ocr, marker


def test_csv_exports_are_formula_safe():
    reliability = read(RELIABILITY)
    ocr = read(OCR)
    assert "safe_csv_cell" in reliability
    assert "preg_match( '/^[=\\-+@]/'" in reliability
    assert ocr.count("SC_Library_Document_OCR_Reliability', 'safe_csv_cell") >= 2


def test_recovery_css_and_mobile_controls():
    text = read(CSS)
    for marker in (
        ".sc-ocr-inline-form",
        ".sc-ocr-published-confirmation",
        ".sc-ocr-provider-maintenance",
        ".sc-ocr-state.is-cancelled",
        "@media (max-width: 760px)",
    ):
        assert marker in text, marker


def test_version_and_route_compatibility():
    assert "public const VERSION = '2.4.1'" in read(OCR)
    assert "public const VERSION = '2.4.1'" in read(RELIABILITY)
    assert "SC_LIBRARY_VERSION : '2.5.1'" in read(WRAPPER)
    assert "public const ROUTE_VERSION = '2.3.0'" in read(REPOSITORY)
    assert "class-sc-library-pdf-bulk-import-repair.php" in read(WRAPPER)
    assert "class-sc-library-pdf-conversion-reliability.php" in read(WRAPPER)


def main():
    tests = [
        test_required_files_exist,
        test_reliability_layer_load_order_and_compatibility,
        test_pdf_source_checksum_and_reconversion_boundary,
        test_queue_source_checksum_is_pinned,
        test_browser_specific_queue_leases,
        test_retry_safe_idempotent_processing,
        test_queue_cancel_and_retry_sync_page_state,
        test_manual_queue_repair_and_stale_lease_recovery,
        test_local_provider_discovery_and_cache,
        test_external_provider_is_signed_https_and_bounded,
        test_provider_errors_can_short_circuit,
        test_published_document_apply_is_reversible_and_returns_to_draft,
        test_active_ocr_pages_block_apply,
        test_document_filtering_occurs_before_pagination,
        test_workspace_and_provider_diagnostics_are_cached,
        test_temp_cleanup_and_active_job_preservation,
        test_csv_exports_are_formula_safe,
        test_recovery_css_and_mobile_controls,
        test_version_and_route_compatibility,
    ]
    for test in tests:
        test()
    print(f"OCR Reliability and Review Recovery checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
