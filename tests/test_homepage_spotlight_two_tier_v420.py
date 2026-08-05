#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
TEMPLATE = (PLUGIN / "templates/homepage-spotlight.php").read_text(encoding="utf-8")
CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight.css").read_text(encoding="utf-8")
ADMIN_CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight-admin.css").read_text(encoding="utf-8")
JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight.js").read_text(encoding="utf-8")


def test_v420_release_markers_and_cache_boundary():
    assert "Version: 4.2.0" in MAIN
    assert "SC_LIBRARY_VERSION', '4.2.0" in MAIN
    assert "Stable tag: 4.2.0" in README
    assert "public const VERSION = '4.2.0'" in MODULE
    assert "sc_library_homepage_spotlight_pages_v420" in MODULE


def test_recommended_twelve_topic_structure_is_complete():
    topics = [
        "Sustainable Development",
        "Planetary Boundaries",
        "International Law",
        "Biology",
        "Systems Thinking",
        "Economics",
        "Artificial Intelligence",
        "Physics",
        "Embedded & Edge Systems",
        "Psychology",
        "Decision Science",
        "Data Systems & Analytics",
    ]
    for topic in topics:
        assert topic in MODULE
    assert "'topic_tiers' => array( 'primary' => 8, 'secondary' => 4 )" in MODULE
    assert "'primary_topics_initial' => 8" in MODULE
    assert "'secondary_topics_collapsible' => true" in MODULE


def test_topic_tier_is_editable_and_defaults_safely_to_primary():
    for fragment in [
        "META_PAGE_TIER",
        "Topic tier",
        "Primary — visible initially",
        "Secondary — additional topics",
        "name=\"page_tier\"",
        "private function page_tier",
        "? 'secondary' : 'primary'",
    ]:
        assert fragment in MODULE
    assert "'tier' => 'primary'" in MODULE


def test_starter_action_aligns_existing_pages_without_duplicate_topics():
    for fragment in [
        "$existing_by_title",
        "suggested_topic_tiers",
        "update_post_meta( $page_id, self::META_PAGE_TIER, $tier )",
        "update_post_meta( $page_id, self::META_PAGE_ITEM_LIMIT, 5 )",
        "The existing 12-topic pages were aligned",
    ]:
        assert fragment in MODULE
    assert "Add or align the 12-topic library set" in MODULE


def test_public_navigation_has_primary_and_progressive_secondary_tiers():
    for fragment in [
        "sc-homepage-spotlight__tabs--primary",
        "sc-homepage-spotlight__secondary-tier",
        "data-sc-spotlight-tier-toggle",
        "data-sc-spotlight-secondary-panel",
        "Explore additional topics",
        "Hide additional topics",
        "data-sc-spotlight-tier=\"secondary\"",
    ]:
        assert fragment in (TEMPLATE + MODULE)
    assert "$use_secondary_tier" in TEMPLATE
    assert "role=\"tablist\"" in TEMPLATE
    assert "aria-expanded" in TEMPLATE
    assert "hidden" in TEMPLATE


def test_secondary_rotation_is_opt_in_until_tier_expands():
    for fragment in [
        "let secondaryExpanded",
        "const navigableIndexes",
        "if (!secondaryPanel || secondaryExpanded)",
        "pageTier(page) !== 'secondary'",
        "show(adjacentIndex(1), false)",
        "secondaryExpanded = !secondaryExpanded",
        "updateSecondaryTier",
    ]:
        assert fragment in JS
    assert "secondary_topics_rotation_requires_expansion" in MODULE


def test_shortcode_controls_secondary_tier_without_breaking_default_shortcode():
    for fragment in [
        "'secondary_topics' => 'true'",
        "'secondary_open' => 'false'",
        "'secondary_label' => __( 'Explore additional topics'",
        "$secondary_topics = $this->truthy",
        "$secondary_open = $this->truthy",
        "$secondary_label = sanitize_text_field",
    ]:
        assert fragment in MODULE
    assert "[sc_homepage_spotlight" in MODULE


def test_topic_navigation_is_compact_responsive_and_accessible():
    for fragment in [
        "grid-template-columns: repeat(4, minmax(0, 1fr))",
        "grid-template-columns: repeat(2, minmax(0, 1fr))",
        ".sc-homepage-spotlight__tabs--secondary[hidden]",
        ".sc-homepage-spotlight__tier-toggle:focus-visible",
        "@media (max-width: 520px)",
    ]:
        assert fragment in CSS
    assert ".sc-library-spotlight-three-column" in ADMIN_CSS
    assert ".sc-library-spotlight-tier--primary" in ADMIN_CSS
    assert ".sc-library-spotlight-tier--secondary" in ADMIN_CSS


def test_five_article_manual_curation_contract_is_preserved():
    for fragment in [
        "'cards_per_page' => array( 4, 5 )",
        "'manual_card_selection' => true",
        "'automatic_backfill' => false",
        "update_post_meta( $page_id, self::META_PAGE_ITEM_LIMIT, 5 )",
        "five articles per topic",
    ]:
        assert fragment.lower() in MODULE.lower()
