from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
PHP = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_files_exist():
    assert PHP.is_file(), PHP
    assert CSS.is_file(), CSS


def test_existing_foundations_shortcode_is_bridged():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "pre_do_shortcode_tag",
        "bridge_foundations_library_shortcode",
        "'sc_library' !== $tag",
        "'foundations' !== $collection",
        "'documentation'",
        "'sc_foundations_library'",
        "shortcode_foundation_documents",
    ]
    for marker in required:
        assert marker in text, marker


def test_listing_queries_only_foundation_document_pages():
    text = PHP.read_text(encoding="utf-8")
    assert "'post_type'           => self::POST_TYPE" in text
    assert "sc-foundation-library__records" in text
    assert "Read document" in text
    assert "Open PDF" in text
    assert "Foundation Document" in text


def test_single_page_uses_existing_brand_classes():
    text = PHP.read_text(encoding="utf-8")
    required = [
        "cc-research-library-brand cc-rl-v2 sc-foundation-page",
        "cc-rl-hero",
        "cc-rl-button cc-rl-button-primary",
        "cc-rl-section cc-rl-section-white",
        "Back to Foundations",
    ]
    for marker in required:
        assert marker in text, marker


def test_pdf_embed_is_native_and_iframe_free():
    text = PHP.read_text(encoding="utf-8")
    assert "<object" in text
    assert 'type="application/pdf"' in text
    assert "<iframe" not in text
    assert "Open the PDF in a new tab" in text


def test_public_css_is_restrained():
    text = CSS.read_text(encoding="utf-8")
    assert ".sc-foundation-library__record" in text
    assert ".sc-foundation-page__reader object" in text
    assert "border-radius: 12px" not in text
    assert "font-family: Helvetica" not in text
    assert ".sc-foundation-document-card" not in text
    assert ".sc-foundation-document-single__header" not in text


def test_route_version_updated():
    text = PHP.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.1.3'" in text


def main():
    tests = [
        test_files_exist,
        test_existing_foundations_shortcode_is_bridged,
        test_listing_queries_only_foundation_document_pages,
        test_single_page_uses_existing_brand_classes,
        test_pdf_embed_is_native_and_iframe_free,
        test_public_css_is_restrained,
        test_route_version_updated,
    ]
    for test in tests:
        test()
    print(f"Foundation Library integration and viewer refinement checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
