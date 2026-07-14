from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
FOUNDATION = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
DOCUMENT = PLUGIN / "includes" / "class-sc-library-pdf-to-document.php"
JS = PLUGIN / "assets" / "js" / "sc-library-foundation-pages-admin.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"
SINGLE = PLUGIN / "templates" / "single-sc_pdf_document.php"
FAMILY = PLUGIN / "templates" / "taxonomy-sc_document_family.php"
PDFJS = PLUGIN / "assets" / "vendor" / "pdfjs" / "build" / "pdf.mjs"
WORKER = PLUGIN / "assets" / "vendor" / "pdfjs" / "build" / "pdf.worker.mjs"
LICENSE = PLUGIN / "assets" / "vendor" / "pdfjs" / "LICENSE"


def test_required_files_exist():
    for path in (FOUNDATION, DOCUMENT, JS, CSS, SINGLE, FAMILY, PDFJS, WORKER, LICENSE):
        assert path.is_file(), path


def test_existing_foundation_type_evolves_in_place():
    old = FOUNDATION.read_text(encoding="utf-8")
    text = DOCUMENT.read_text(encoding="utf-8")
    assert "class-sc-library-pdf-to-document.php" in old
    assert "new SC_Library_PDF_To_Document" in old
    assert "public const POST_TYPE = 'sc_foundation_doc'" in text
    assert "sc-library-pdf-document/2.0" in text
    assert "migrate_existing_records" in text


def test_document_family_taxonomy_is_not_blog_taxonomy():
    text = DOCUMENT.read_text(encoding="utf-8")
    assert "public const TAX_FAMILY = 'sc_document_family'" in text
    assert "register_taxonomy(" in text
    assert "Document Families" in text
    assert "documents/family" in text
    assert "DEFAULT_FAMILY = 'foundations'" in text
    assert "unregister_taxonomy_for_object_type" in text


def test_pdf_to_document_pipeline():
    text = DOCUMENT.read_text(encoding="utf-8")
    for marker in (
        "ajax_prepare",
        "ajax_store_chunk",
        "ajax_finalize",
        "server_extract",
        "sc_library_pdf_to_document_extraction",
        "pdftotext",
        "store_pages",
        "pages_to_html",
        "META_RAW_TEXT",
        "META_PAGE_MAP",
        "META_CHECKSUM",
    ):
        assert marker in text, marker


def test_browser_pdfjs_pipeline_preserves_media_selector():
    text = JS.read_text(encoding="utf-8")
    for marker in (
        "data-sc-foundation-select-pdf",
        "wp.media",
        "application/pdf",
        "import(config.pdfJsUrl)",
        "getTextContent",
        "sc_library_store_pdf_document_chunk",
        "sc_library_finalize_pdf_document",
        "chunk.length >= 10",
        "needs_ocr",
    ):
        assert marker in text, marker


def test_generated_record_is_article_like_with_original_pdf():
    text = DOCUMENT.read_text(encoding="utf-8")
    for marker in (
        "post_content",
        "post_excerpt",
        "Read Document",
        "View Original PDF",
        "Download PDF",
        "sc-pdf-document__content",
        "sc-pdf-document__reader",
        'type="application/pdf"',
    ):
        assert marker in text, marker


def test_family_library_and_legacy_shortcodes():
    text = DOCUMENT.read_text(encoding="utf-8")
    for marker in (
        "sc_pdf_document_library",
        "sc_pdf_library",
        "sc_foundation_documents",
        "sc_foundations_library",
        "bridge_existing_shortcodes",
        "tax_query",
    ):
        assert marker in text, marker


def test_media_library_and_bulk_import():
    text = DOCUMENT.read_text(encoding="utf-8")
    for marker in (
        "media_row_actions",
        "Create Knowledge Document",
        "create_from_attachment",
        "Import Media Library PDFs",
        "import_documents",
        "Create Draft Document Records",
        "sc_auto_extract",
    ):
        assert marker in text, marker


def test_ocr_and_protected_pdf_boundaries():
    text = DOCUMENT.read_text(encoding="utf-8")
    assert "needs_ocr" in text
    assert "password_protected" in text
    assert "public const MIN_TEXT = 80" in text
    assert "image-based PDF" in text


def test_knowledge_library_indexing_filters():
    text = DOCUMENT.read_text(encoding="utf-8")
    for marker in (
        "sc_library_record_post_types",
        "sc_library_searchable_post_types",
        "sc_library_indexable_post_types",
        "sc_research_librarian_post_types",
        "show_in_rest",
        "exclude_from_search'] = false",
    ):
        assert marker in text, marker


def test_spartan_full_page_design():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-pdf-document__reader" in text
    assert "height: 88vh" in text
    assert ".sc-document-library__record" in text
    assert ".sc-pdf-document__content" in text
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_routes_and_query_var_compatibility():
    text = DOCUMENT.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.2.0'" in text
    assert "^documents/([^/]+)/?$" in text
    assert "^foundations/([^/]+)/?$" in text
    assert "add_filter( 'query_vars'" in text
    assert "get_query_var( 'sc_legacy_foundation_route' )" in text


def test_one_admin_script_handle_prevents_duplicate_execution():
    text = DOCUMENT.read_text(encoding="utf-8")
    assert "sc-library-foundation-pages-admin" in text
    assert "sc-library-pdf-document-admin" not in text


def main():
    tests = [
        test_required_files_exist,
        test_existing_foundation_type_evolves_in_place,
        test_document_family_taxonomy_is_not_blog_taxonomy,
        test_pdf_to_document_pipeline,
        test_browser_pdfjs_pipeline_preserves_media_selector,
        test_generated_record_is_article_like_with_original_pdf,
        test_family_library_and_legacy_shortcodes,
        test_media_library_and_bulk_import,
        test_ocr_and_protected_pdf_boundaries,
        test_knowledge_library_indexing_filters,
        test_spartan_full_page_design,
        test_routes_and_query_var_compatibility,
        test_one_admin_script_handle_prevents_duplicate_execution,
    ]
    for test in tests:
        test()
    print(f"PDF-to-Document Knowledge Library checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
