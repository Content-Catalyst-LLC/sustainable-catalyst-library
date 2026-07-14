from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
PHP = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
JS = PLUGIN / "assets" / "js" / "sc-library-foundation-pages-admin.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"
TEMPLATE = PLUGIN / "templates" / "single-sc_foundation_doc.php"


def test_required_files_exist():
    for path in (PHP, JS, CSS, TEMPLATE):
        assert path.is_file(), path


def test_editor_is_stable_and_pdf_is_required():
    text = PHP.read_text(encoding="utf-8")
    assert "use_block_editor_for_post_type" in text
    assert "edit_form_after_title" in text
    assert "data-pdf-required=\"true\"" in text
    assert "prevent_publish_without_pdf" in text
    assert "$data['post_status'] = 'draft';" in text
    assert "Select a valid PDF before publishing" in text


def test_pdf_health_and_admin_diagnostics():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "get_pdf_health",
        "Foundation Docs Health",
        "register_health_page",
        "repair_routes",
        "repair_metadata",
        "sc_library_repair_foundation_routes",
        "sc_library_repair_foundation_metadata",
        "Needs PDF",
        "PDF status",
    ]
    for marker in required:
        assert marker in text, marker


def test_route_repair_contract():
    text = PHP.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.1.2'" in text
    assert "'^foundations/([^/]+)/?$'" in text
    assert "flush_rewrite_rules( false )" in text
    assert "sc_library_foundation_pages_rewrite_version" in text


def test_public_viewer_has_fallback_and_accessibility():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "<iframe",
        "loading=\"lazy\"",
        "Embedded PDF viewer",
        "PDF not visible?",
        "Open PDF",
        "Download PDF",
        "<noscript>",
        "type=\"application/pdf\"",
    ]
    for marker in required:
        assert marker in text, marker


def test_listing_search_and_pagination_are_stable():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "get_queried_object_id()",
        "foundation_q",
        "foundation_page",
        "Clear search",
        "999999999",
        "paginate_links",
        "PDF unavailable",
    ]
    for marker in required:
        assert marker in text, marker


def test_media_selector_validates_mime_type():
    text = JS.read_text(encoding="utf-8")
    assert "attachment.mime !== 'application/pdf'" in text
    assert "config.invalidType" in text
    assert "config.mediaError" in text
    assert "multiple: false" in text


def test_mobile_and_health_css():
    text = CSS.read_text(encoding="utf-8")
    required = [
        ".sc-foundation-health__cards",
        ".sc-foundation-document-single__viewer iframe",
        ".sc-foundation-document-single__viewer-help",
        "@media (max-width: 640px)",
        "grid-template-columns: 1fr",
        "@media (prefers-reduced-motion: reduce)",
    ]
    for marker in required:
        assert marker in text, marker


def main():
    tests = [
        test_required_files_exist,
        test_editor_is_stable_and_pdf_is_required,
        test_pdf_health_and_admin_diagnostics,
        test_route_repair_contract,
        test_public_viewer_has_fallback_and_accessibility,
        test_listing_search_and_pagination_are_stable,
        test_media_selector_validates_mime_type,
        test_mobile_and_health_css,
    ]
    for test in tests:
        test()
    print(f"Foundation Document Production Hardening checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
