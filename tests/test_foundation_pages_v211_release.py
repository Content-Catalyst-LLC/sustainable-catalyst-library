from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
PHP = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
JS = PLUGIN / "assets" / "js" / "sc-library-foundation-pages-admin.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_files_exist():
    for path in (PHP, JS, CSS):
        assert path.is_file(), path


def test_inline_pdf_selector_is_registered():
    text = PHP.read_text(encoding="utf-8")
    assert "edit_form_after_title" in text
    assert "render_inline_pdf_selector" in text
    assert "Foundation PDF" in text
    assert "Select PDF" in text
    assert "add_meta_boxes_' . self::POST_TYPE" not in text


def test_single_route_is_explicit_and_refreshed():
    text = PHP.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.1.1'" in text
    assert "'^foundations/([^/]+)/?$'" in text
    assert "flush_rewrite_rules( false )" in text


def test_unsafe_query_filter_is_not_registered():
    text = PHP.read_text(encoding="utf-8")
    assert "add_filter( 'pre_get_posts'" not in text
    assert "$query->set( 'post__in', array( 0 ) )" not in text


def test_media_assets_use_plugin_root():
    text = PHP.read_text(encoding="utf-8")
    assert "SC_LIBRARY_URL . 'assets/js/sc-library-foundation-pages-admin.js'" in text
    assert "SC_LIBRARY_URL . 'assets/css/sc-library-foundation-pages.css'" in text
    js = JS.read_text(encoding="utf-8")
    assert "wp.media" in js
    assert "application/pdf" in js
    assert "multiple: false" in js


def test_inline_panel_css_exists():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-foundation-page-inline-panel" in text
    assert ".sc-foundation-page-selector__actions" in text


def main():
    tests = [
        test_files_exist,
        test_inline_pdf_selector_is_registered,
        test_single_route_is_explicit_and_refreshed,
        test_unsafe_query_filter_is_not_registered,
        test_media_assets_use_plugin_root,
        test_inline_panel_css_exists,
    ]
    for test in tests:
        test()
    print(f"Foundation Document editor and routing repair checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
