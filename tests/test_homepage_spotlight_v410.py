#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
TEMPLATE = (PLUGIN / "templates/homepage-spotlight.php").read_text(encoding="utf-8")
PUBLIC_JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight.js").read_text(encoding="utf-8")
ADMIN_JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight-admin.js").read_text(encoding="utf-8")
PUBLIC_CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight.css").read_text(encoding="utf-8")
ADMIN_CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight-admin.css").read_text(encoding="utf-8")


def test_release_markers_and_contained_bootstrap():
    assert "Version: 4.1.1" in MAIN
    assert "SC_LIBRARY_VERSION', '4.1.1" in MAIN
    assert "Stable tag: 4.1.1" in README
    assert "class-sc-library-homepage-spotlight.php" in MAIN
    assert "new SC_Library_Homepage_Spotlight" in MAIN
    assert "Homepage Spotlight startup failure" in MAIN
    assert "catch (Throwable $error)" in MAIN


def test_configurable_category_page_model():
    assert "PAGE_POST_TYPE = 'sc_spot_page'" in MODULE
    assert "ITEM_POST_TYPE = 'sc_home_spotlight'" in MODULE
    assert "Category name" in MODULE
    assert "Cards on this page" in MODULE
    assert "Category order" in MODULE
    assert "Include this category when it has valid cards" in MODULE
    assert "Create, rename, reorder, enable, or replace" in MODULE
    assert "suggested_starter_pages" in MODULE
    for title in [
        "Sustainable Development",
        "Planetary Boundaries",
        "International Law",
        "Biology",
        "Systems Thinking",
    ]:
        assert title in MODULE
    assert "starter pages were added. They remain fully editable" in MODULE


def test_manual_selection_contract_has_no_automatic_population():
    required_true = [
        "'manual_category_pages' => true",
        "'manual_card_selection' => true",
        "'manual_page_order' => true",
        "'manual_card_order' => true",
        "'category_names_configurable' => true",
        "'category_count_configurable' => true",
        "'taxonomy_assisted_search_only' => true",
    ]
    required_false = [
        "'taxonomy_autopopulation' => false",
        "'automatic_fallback' => false",
        "'automatic_latest' => false",
        "'automatic_popular' => false",
        "'automatic_random' => false",
        "'automatic_backfill' => false",
    ]
    for fragment in required_true + required_false:
        assert fragment in MODULE
    assert "'cards_per_page' => array( 4, 5 )" in MODULE
    assert "'minimum_valid_cards_per_page' => 4" in MODULE
    assert "'autoplay_default' => false" in MODULE
    assert "orderby' => 'date'" not in MODULE
    assert "orderby' => 'rand'" not in MODULE.lower()


def test_category_and_card_admin_controls():
    for fragment in [
        "sc_library_spotlight_save_page",
        "sc_library_spotlight_page_action",
        "sc_library_spotlight_starter_pages",
        "sc_library_spotlight_save_item",
        "sc_library_spotlight_item_action",
        "sc_library_spotlight_orders",
        "spotlight_page_id",
        "Card position",
        "Start",
        "End",
        "Display this card when its schedule and source are valid",
        "Unassigned or unavailable category",
    ]:
        assert fragment in MODULE
    assert "data-spotlight-sortable" in MODULE
    assert "data-order-step" in MODULE
    assert "dragstart" in ADMIN_JS
    assert "sc_library_spotlight_search_sources" in ADMIN_JS
    assert ".sc-library-spotlight-category-queue" in ADMIN_CSS


def test_public_five_page_console_and_four_five_card_layout():
    assert "[sc_homepage_spotlight" in MODULE
    assert "'autoplay' => 'false'" in MODULE
    assert "'interval' => '16000'" in MODULE
    assert "'tabs' => 'true'" in MODULE
    assert "data-sc-spotlight-tab" in TEMPLATE
    assert "data-sc-spotlight-page" in TEMPLATE
    assert "data-sc-spotlight-card" in TEMPLATE
    assert "sc-homepage-spotlight__card--lead" in TEMPLATE
    assert "Previous" in TEMPLATE and "Pause" in TEMPLATE and "Next" in TEMPLATE
    assert "grid-template-columns: repeat(2, minmax(0, 1fr));" in PUBLIC_CSS
    assert ".sc-homepage-spotlight__card--lead" in PUBLIC_CSS
    assert "grid-column: 1 / -1" in PUBLIC_CSS
    assert "@media (max-width: 760px)" in PUBLIC_CSS


def test_rotation_accessibility_and_reduced_motion():
    for fragment in [
        "prefers-reduced-motion: reduce",
        "aria-selected",
        "role=\"tablist\"",
        "aria-live=\"polite\"",
        "pause_on_hover",
        "Pause automatic rotation",
        "Play automatic rotation",
    ]:
        assert fragment in (PUBLIC_CSS + TEMPLATE + MODULE)
    for fragment in [
        "matchMedia('(prefers-reduced-motion: reduce)')",
        "mouseenter",
        "focusin",
        "touchstart",
        "touchend",
        "visibilitychange",
        "schedule()",
    ]:
        assert fragment in PUBLIC_JS


def test_source_validation_and_no_backfill_runtime():
    for fragment in [
        "Linked Library record is missing",
        "Linked Library record is not published",
        "Linked Library record is password protected",
        "is_post_publicly_viewable",
        "Category page is missing or disabled",
        "if ( null === $card )",
        "continue;",
        "if ( count( $cards ) < 4 )",
        "$slot_candidates",
        "$card['slot'] = $slot",
    ]:
        assert fragment in MODULE
    # Runtime selection must be based on explicit page/card records and enabled flags.
    assert re.search(r"post_type'\s*=>\s*self::PAGE_POST_TYPE", MODULE)
    assert re.search(r"post_type'\s*=>\s*self::ITEM_POST_TYPE", MODULE)
    assert "META_PAGE_ENABLED" in MODULE
    assert "META_ENABLED" in MODULE


def test_cache_boundaries_and_empty_behavior():
    assert "CACHE_KEY = 'sc_library_homepage_spotlight_pages_v410'" in MODULE
    assert "next_boundary" in MODULE
    assert "delete_transient( self::CACHE_KEY )" in MODULE
    assert "'empty_queue_behavior' => 'hide'" in MODULE
    assert "return 'hide'" in MODULE
