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


def test_foundation_pages_contract():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "sc-library-foundation-page/1.0",
        "public const POST_TYPE = 'sc_foundation_doc'",
        "add_shortcode( 'sc_foundation_documents'",
        "Select PDF",
        "application/pdf",
        "exclude_from_search",
        "has_archive",
        "unregister_taxonomy_for_object_type",
        "Open PDF",
        "Download PDF",
        "Back to Foundations",
        "sc_foundation_advanced",
        "has_shortcode( (string) $post->post_content, 'sc_foundation_documents' )",
    ]
    for marker in required:
        assert marker in text, marker


def test_no_blog_taxonomies_declared():
    text = PHP.read_text(encoding="utf-8")
    assert "$args['taxonomies']          = array();" in text
    assert "register_taxonomy(" not in text


def test_template_uses_page_renderer():
    text = TEMPLATE.read_text(encoding="utf-8")
    assert "SC_Library_Foundation_Pages::render_single_document" in text
    assert "get_header();" in text
    assert "get_footer();" in text


def test_admin_media_selector():
    text = JS.read_text(encoding="utf-8")
    assert "wp.media" in text
    assert "application/pdf" in text
    assert "multiple: false" in text


def test_responsive_css():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-foundation-document-single__viewer" in text
    assert ".sc-foundation-documents__grid" in text
    assert "@media (max-width: 640px)" in text


def main():
    tests = [
        test_required_files_exist,
        test_foundation_pages_contract,
        test_no_blog_taxonomies_declared,
        test_template_uses_page_renderer,
        test_admin_media_selector,
        test_responsive_css,
    ]
    for test in tests:
        test()
    print(f"Foundation Document Pages release checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
