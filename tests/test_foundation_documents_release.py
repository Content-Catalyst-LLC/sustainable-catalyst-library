from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
FOUNDATION = (PLUGIN / "includes/class-sc-library-foundation-documents.php").read_text()
INDEXER = (PLUGIN / "includes/class-sc-library-indexer.php").read_text()
REST = (PLUGIN / "includes/class-sc-library-rest.php").read_text()
ORCHESTRATOR = (PLUGIN / "includes/class-sc-library-orchestrator.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
LIBRARY_JS = (PLUGIN / "assets/js/sc-library.js").read_text()
VIEWER_JS = (PLUGIN / "assets/js/sc-library-foundation-viewer.js").read_text()
ADMIN_JS = (PLUGIN / "assets/js/sc-library-foundation-admin.js").read_text()
CSS = (PLUGIN / "assets/css/sc-library-foundation-documents.css").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers():
    assert "Version: 1.18.1" in MAIN
    assert "SC_LIBRARY_VERSION', '1.18.1'" in MAIN
    assert "Stable tag: 1.18.1" in README
    assert "embedded Foundation Document records" in MAIN


def test_foundation_component_bootstrapped():
    assert "class-sc-library-foundation-documents.php" in MAIN
    assert "new SC_Library_Foundation_Documents" in MAIN
    assert "$foundation_documents->register_hooks()" in MAIN


def test_native_foundation_document_record_type():
    assert "public const POST_TYPE = 'sc_foundation_doc'" in FOUNDATION
    assert "register_post_type(self::POST_TYPE" in FOUNDATION
    assert "'show_in_rest' => true" in FOUNDATION
    assert "'revisions'" in FOUNDATION


def test_media_library_pdf_selection_and_validation():
    assert "wp_enqueue_media()" in FOUNDATION
    assert "Select PDF from Media Library" in FOUNDATION
    assert "get_post_mime_type($attachment_id) !== 'application/pdf'" in FOUNDATION
    assert "wp.media" in ADMIN_JS


def test_pdfjs_is_bundled_and_no_iframe_reader():
    assert (PLUGIN / "assets/vendor/pdfjs/pdf.min.js").exists()
    assert (PLUGIN / "assets/vendor/pdfjs/pdf.worker.min.js").exists()
    assert (PLUGIN / "assets/vendor/pdfjs/LICENSE").exists()
    assert "getDocument" in VIEWER_JS
    assert "<canvas" in FOUNDATION
    assert "<iframe" not in FOUNDATION.lower()


def test_explicit_open_download_and_mobile_fallback():
    assert "Open PDF" in FOUNDATION
    assert "Download PDF" in FOUNDATION
    assert "Open mobile PDF" in FOUNDATION
    assert "sc-foundation-document__mobile-fallback" in CSS
    assert "@media" in CSS


def test_page_aware_storage_tables():
    assert "sc_library_pdf_pages" in ACTIVATOR
    assert "sc_library_foundation_versions" in ACTIVATOR
    assert "UNIQUE KEY post_page (post_id, page_number)" in ACTIVATOR
    assert "FULLTEXT KEY sc_library_pdf_page_search (page_text)" in ACTIVATOR


def test_extraction_is_page_batched_and_bounded():
    assert "/extract/start" in FOUNDATION
    assert "/extract/pages" in FOUNDATION
    assert "/extract/complete" in FOUNDATION
    assert "/extract/fail" in FOUNDATION
    assert "array_slice($pages, 0, 25)" in FOUNDATION
    assert "1000000" in FOUNDATION
    assert "batch" in ADMIN_JS.lower()


def test_extraction_permissions_are_document_specific():
    assert "get_post_type($id) === self::POST_TYPE" in FOUNDATION
    assert "current_user_can('edit_post', $id)" in FOUNDATION


def test_indexer_receives_pdf_text_and_pdf_resource_flag():
    assert "SC_Library_Foundation_Documents::extracted_text" in INDEXER
    assert "'pdf'" in INDEXER
    assert "GROUP_CONCAT" not in FOUNDATION


def test_library_search_exposes_type_and_page_hits():
    assert "foundation_document" in REST
    assert "page_hits" in REST
    assert "Foundation Documents" in (PLUGIN / "templates/library-app.php").read_text()
    assert "Matching PDF pages" in LIBRARY_JS
    assert "#page=" in LIBRARY_JS


def test_viewer_honors_page_links():
    assert "hashMatch" in VIEWER_JS
    assert "#page=${pageNumber}" in VIEWER_JS


def test_research_librarian_page_evidence():
    assert "SC_Library_Foundation_Documents::page_hits" in ORCHESTRATOR
    assert "pdf_page_hits" in ORCHESTRATOR


def test_metadata_versions_relationships_and_citations():
    for marker in ["Document version", "Publication date", "Author or institution", "Publisher", "DOI", "Related WordPress record IDs"]:
        assert marker in FOUNDATION
    assert "record_version" in FOUNDATION
    assert "version_history" in FOUNDATION
    assert "relationship_type' => 'documents'" in FOUNDATION
    assert "bibtex" in FOUNDATION.lower()
    assert "application/x-research-info-systems" in FOUNDATION
    assert "citationstyles.csl" in FOUNDATION


def test_diagnostics_and_retry_controls():
    assert "Extraction diagnostics" in FOUNDATION
    assert "Retry extraction" in FOUNDATION
    assert "_sc_foundation_extraction_error" in FOUNDATION
    assert "character_count" in FOUNDATION


def test_migration_of_direct_download_links():
    assert "Foundation PDF Migration" in FOUNDATION
    assert "_sc_library_doc_pdf_url" in FOUNDATION
    assert ".pdf" in FOUNDATION
    assert "_sc_foundation_migrated_from_post_id" in FOUNDATION
    assert "Create selected Foundation Document records" in FOUNDATION


def test_public_and_developer_api_routes():
    for marker in [
        "/foundation-documents",
        "/foundation-documents/(?P<id>\\d+)",
        "/foundation-documents/(?P<id>\\d+)/pages",
        "/foundation-documents/(?P<id>\\d+)/citation",
    ]:
        assert marker in FOUNDATION
    developer = (PLUGIN / "includes/class-sc-library-developer-api.php").read_text()
    assert "rest_foundation_documents" in developer
    assert "foundation-document.extracted" in developer


def test_portable_export_schema_and_entities():
    assert "sc-library-portable-export/1.9" in PORTABILITY
    for entity in ["foundation_documents", "pdf_pages", "foundation_versions"]:
        assert entity in PORTABILITY
    static_schema = (ROOT / "docs/postgresql-schema.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS foundation_documents" in static_schema
    assert "CREATE TABLE IF NOT EXISTS pdf_pages" in static_schema
    assert "CREATE TABLE IF NOT EXISTS foundation_versions" in static_schema


def test_openapi_and_json_schema_are_valid_and_documented():
    openapi = json.loads((ROOT / "docs/openapi.json").read_text())
    schema = json.loads((ROOT / "docs/schemas/foundation-document.json").read_text())
    assert openapi["openapi"] == "3.1.0"
    assert "/foundation-documents" in openapi["paths"]
    assert "/foundation-documents/{id}/pages" in openapi["paths"]
    assert schema["properties"]["schema"]["const"] == "sc-library-foundation-document/1.0"


def test_setup_and_release_notes_exist():
    assert (ROOT / "EMBEDDED_DOCUMENT_RECORDS_SETUP_v1.18.1.md").exists()
    assert (ROOT / "RELEASE_NOTES_1.18.1.md").exists()
