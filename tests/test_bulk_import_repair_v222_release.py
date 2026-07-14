from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
JS = PLUGIN / "assets" / "js" / "sc-library-pdf-bulk-import.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_required_files_exist():
    for path in (WRAPPER, BULK, RELIABILITY, JS, CSS):
        assert path.is_file(), path


def test_companion_layer_is_loaded():
    text = WRAPPER.read_text(encoding="utf-8")
    assert "class-sc-library-pdf-bulk-import-repair.php" in text
    assert "new SC_Library_PDF_Bulk_Import_Repair" in text
    assert "new SC_Library_PDF_Conversion_Reliability" in text


def test_paginated_media_library_inventory():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "PAGE_SIZE = 50",
        "post_mime_type' => 'application/pdf'",
        "pdf_page",
        "paginate_links",
        "attachment_inventory_state",
        "Unlinked PDF",
        "Possible duplicate",
    ):
        assert marker in text, marker


def test_batch_record_creation_and_family_assignment():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "create_import_job",
        "create_only",
        "create_and_queue",
        "wp_insert_post",
        "write_pdf_meta",
        "wp_set_object_terms",
        "META_LIFECYCLE",
        "Draft document record created",
    ):
        assert marker in text, marker


def test_persistent_conversion_job_model():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "JOB_TYPE = 'sc_pdf_bulk_job'",
        "META_JOB_ITEMS",
        "META_JOB_STATUS",
        "META_JOB_CONFIG",
        "ajax_next_item",
        "processing",
        "lock_user",
        "lock_time",
        "settle_job_status",
    ):
        assert marker in text, marker


def test_pause_resume_retry_cancel():
    php = BULK.read_text(encoding="utf-8")
    js = JS.read_text(encoding="utf-8")
    for marker in (
        "Pause",
        "Resume",
        "Retry Failed",
        "Cancel",
        "controlJob",
        "sc_library_v222_control_job",
        "complete_with_errors",
    ):
        assert marker in php or marker in js, marker


def test_queue_reuses_resumable_v221_conversion():
    text = JS.read_text(encoding="utf-8")
    for marker in (
        "sc_library_v221_prepare_pdf_document",
        "sc_library_v221_store_pdf_chunk",
        "sc_library_v221_finalize_pdf_document",
        "conversionNonce",
        "resumePage",
        "disableWorker",
        "getTextContent",
    ):
        assert marker in text, marker


def test_duplicate_detection_uses_attachment_and_checksum():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "META_ATTACHMENT_CHECKSUM",
        "hash_file( 'sha256'",
        "documents_for_attachment_or_checksum",
        "find_document_by_attachment_or_checksum",
        "skipped_existing",
        "Possible duplicate of document",
    ):
        assert marker in text, marker


def test_collection_repair_and_orphan_detection():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "Collection Repair",
        "document_repair_state",
        "Missing PDF",
        "Broken PDF",
        "No document family",
        "No lifecycle status",
        "safe_repair_document",
        "assign_family",
        "set_lifecycle",
        "reprocess",
    ):
        assert marker in text, marker


def test_reports_and_csv_exports():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "export_job_csv",
        "export_collection_csv",
        "Content-Type: text/csv",
        "Export CSV",
        "Export Collection Report CSV",
        "issues",
        "attempts",
    ):
        assert marker in text, marker


def test_queue_summary_and_error_states():
    text = BULK.read_text(encoding="utf-8")
    for marker in (
        "sc-pdf-job-stats",
        "complete",
        "queued",
        "failed",
        "skipped",
        "needs_ocr",
        "cancelled",
    ):
        assert marker in text, marker


def test_spartan_admin_css():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-pdf-bulk-admin" in text
    assert ".sc-pdf-job-layout" in text
    assert ".sc-pdf-bulk-cards" in text
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_version_marker():
    text = BULK.read_text(encoding="utf-8")
    assert "public const VERSION = '2.2.2'" in text
    assert "SC_LIBRARY_VERSION : '2.2.2'" in WRAPPER.read_text(encoding="utf-8")


def main():
    tests = [
        test_required_files_exist,
        test_companion_layer_is_loaded,
        test_paginated_media_library_inventory,
        test_batch_record_creation_and_family_assignment,
        test_persistent_conversion_job_model,
        test_pause_resume_retry_cancel,
        test_queue_reuses_resumable_v221_conversion,
        test_duplicate_detection_uses_attachment_and_checksum,
        test_collection_repair_and_orphan_detection,
        test_reports_and_csv_exports,
        test_queue_summary_and_error_states,
        test_spartan_admin_css,
        test_version_marker,
    ]
    for test in tests:
        test()
    print(f"Bulk Import and Collection Repair checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
