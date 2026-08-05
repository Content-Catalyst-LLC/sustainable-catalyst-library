#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
TEMPLATE = (PLUGIN / "templates/homepage-spotlight.php").read_text(encoding="utf-8")
CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight.css").read_text(encoding="utf-8")
JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight.js").read_text(encoding="utf-8")


def test_v412_release_markers():
    assert "Version: 4.2.0" in MAIN
    assert "SC_LIBRARY_VERSION', '4.2.0" in MAIN
    assert "Stable tag: 4.2.0" in README
    assert "public const VERSION = '4.2.0'" in MODULE


def test_console_default_rotation_contract():
    assert "'autoplay_default' => true" in MODULE
    assert "'interval_default_ms' => 14000" in MODULE
    assert "'airport_board_rotation' => true" in MODULE
    assert "'presentation' => 'knowledge_library_console'" in MODULE
    assert "'autoplay' => 'true'" in MODULE
    assert "'interval' => '14000'" in MODULE
    assert "root.dataset.autoplay !== 'true'" in JS
    assert "root.dataset.interval || '14000'" in JS


def test_console_frame_identity_and_rotation_surface():
    for fragment in [
        "--sc-kl-black: #090909",
        "--sc-kl-white: #ffffff",
        "--sc-kl-green: #168a4a",
        "sc-homepage-spotlight--console",
        "Knowledge Library",
        "Curated Knowledge Library console",
    ]:
        assert fragment in (CSS + TEMPLATE)


def test_airport_board_rows_and_status_telemetry():
    for fragment in [
        "sc-homepage-spotlight__board",
        "sc-homepage-spotlight__row-number",
        "sc-homepage-spotlight__record-line",
        "data-sc-spotlight-status",
        "data-status-auto",
        "data-status-hold",
        "data-sc-spotlight-progress",
        "AUTO, HOLD, PAUSED, STATIC, or REDUCED MOTION",
    ]:
        assert fragment.lower() in (TEMPLATE + README).lower()
    assert "grid-template-columns: 44px 90px minmax(0, 1fr) auto" in CSS
    assert "background: var(--sc-kl-progress-fill)" in CSS
    assert "background: var(--sc-kl-progress-track)" in CSS


def test_rotation_pause_and_screen_refresh_behavior():
    for fragment in [
        "playbackState",
        "updatePlaybackState",
        "restartProgress",
        "is-refreshing",
        "mouseenter",
        "focusin",
        "touchstart",
        "visibilitychange",
        "ArrowLeft",
        "ArrowRight",
        "prefers-reduced-motion: reduce",
    ]:
        assert fragment in (JS + CSS)


def test_manual_curation_and_minimum_page_rule_remain_intact():
    for fragment in [
        "'manual_category_pages' => true",
        "'manual_card_selection' => true",
        "'automatic_backfill' => false",
        "'minimum_valid_cards_per_page' => 4",
        "if ( count( $cards ) < 4 )",
        "cards.length < 4",
    ]:
        assert fragment in (MODULE + JS)
