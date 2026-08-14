#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
TEMPLATE = (PLUGIN / "templates/homepage-spotlight.php").read_text(encoding="utf-8")
CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight.css").read_text(encoding="utf-8")
ADMIN_JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight-admin.js").read_text(encoding="utf-8")
PUBLIC_JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight.js").read_text(encoding="utf-8")


def test_v413_release_markers_and_cache_boundary():
    assert "Version: 4.2.0" in MAIN
    assert "SC_LIBRARY_VERSION', '4.2.0" in MAIN
    assert "Stable tag: 4.2.0" in README
    assert "public const VERSION = '4.2.0'" in MODULE
    assert "sc_library_homepage_spotlight_pages_v420" in MODULE


def test_light_editorial_palette_with_black_frame_red_and_green():
    for fragment in [
        "--sc-kl-black: #090909",
        "--sc-kl-white: #ffffff",
        "--sc-kl-cream: #f6f1e7",
        "--sc-kl-gray-100: #e5e5e2",
        "--sc-kl-red: #e00000",
        "--sc-kl-green: #168a4a",
        "background: var(--sc-kl-cream)",
        "background: var(--sc-kl-black)",
        "background: var(--sc-kl-white)",
    ]:
        assert fragment in CSS
    assert "--sc-kl-purple" not in CSS
    assert "--sc-kl-pink" not in CSS
    assert "'black', 'white', 'cream', 'gray', 'red', 'green'" in MODULE
    assert "black_frame_light_editorial_rows" in MODULE


def test_thumbnail_resolution_chain_and_placeholder_contract():
    for fragment in [
        "private function resolve_source_thumbnail",
        "get_post_thumbnail_id( $source )",
        "_sc_library_cover_attachment_id",
        "_sc_library_pdf_attachment_id",
        "pdf_preview",
        "get_children(",
        "wp-image-(\\d+)",
        "<img[^>]+src=",
        "_sc_library_thumbnail_url",
        "sc_library_spotlight_thumbnail",
        "'source' => 'placeholder'",
        "'placeholder' => true",
    ]:
        assert fragment in MODULE
    assert "'thumbnail_resolution' => array( 'featured', 'library_meta', 'pdf_preview', 'attached_image', 'content_image', 'image_url', 'placeholder' )" in MODULE


def test_public_thumbnail_markup_uses_eager_first_screen_and_lazy_later_screens():
    for fragment in [
        "$thumbnail_loading = 0 === $page_index ? 'eager' : 'lazy'",
        "$thumbnail_priority = 0 === $page_index && $is_lead ? 'high' : 'auto'",
        "wp_get_attachment_image(",
        "fetchpriority",
        "sc-homepage-spotlight__thumbnail-image",
        "sc-homepage-spotlight__thumbnail--placeholder",
        "sc-homepage-spotlight__thumbnail-placeholder-mark",
        "data-thumbnail-source",
    ]:
        assert fragment in TEMPLATE
    assert "sc-homepage-spotlight__card--has-thumbnail" in TEMPLATE
    assert "sc-homepage-spotlight__card--no-thumbnail" in TEMPLATE


def test_thumbnail_layout_remains_visible_on_mobile():
    for fragment in [
        "grid-template-columns: 44px 90px minmax(0, 1fr) auto",
        "grid-template-columns: 44px 138px minmax(0, 1fr) auto",
        "width: 90px",
        "height: 66px",
        "width: 138px",
        "height: 94px",
        "grid-template-columns: 30px 68px minmax(0, 1fr)",
    ]:
        assert fragment in CSS
    assert ".sc-homepage-spotlight__thumbnail {\n        display: none" not in CSS


def test_broken_image_urls_fall_back_to_library_placeholder_at_runtime():
    for fragment in [
        "installThumbnailFallbacks",
        "image.addEventListener('error', showPlaceholder",
        "image.naturalWidth === 0",
        "runtime-fallback",
        "sc-homepage-spotlight__thumbnail-placeholder-mark",
    ]:
        assert fragment in PUBLIC_JS


def test_new_library_cards_enable_thumbnail_and_selector_checks_it():
    assert "'show_thumbnail' => 1" in MODULE
    assert "sc-library-spotlight-show-thumbnail" in MODULE
    assert "Show resolved thumbnail or Library placeholder" in MODULE
    assert "showThumbnail.checked = true" in ADMIN_JS


def test_manual_selection_and_rotation_contract_are_preserved():
    for fragment in [
        "'manual_card_selection' => true",
        "'automatic_backfill' => false",
        "'minimum_valid_cards_per_page' => 4",
        "'autoplay_default' => true",
        "'interval_default_ms' => 14000",
        "if ( count( $cards ) < 4 )",
    ]:
        assert fragment in MODULE
