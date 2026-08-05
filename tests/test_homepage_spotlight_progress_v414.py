#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
CSS = (PLUGIN / "assets/css/sc-library-homepage-spotlight.css").read_text(encoding="utf-8")
JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight.js").read_text(encoding="utf-8")


def test_v414_release_markers_and_cache_boundary():
    assert "Version: 4.2.0" in MAIN
    assert "SC_LIBRARY_VERSION', '4.2.0" in MAIN
    assert "Stable tag: 4.2.0" in README
    assert "public const VERSION = '4.2.0'" in MODULE
    assert "sc_library_homepage_spotlight_pages_v420" in MODULE


def test_progress_uses_single_red_fill_on_neutral_gray_track():
    assert "--sc-kl-progress-track: #8f8f8a" in CSS
    assert "--sc-kl-progress-fill: #e00000" in CSS
    assert ".sc-homepage-spotlight__progress" in CSS
    assert "background: var(--sc-kl-progress-track)" in CSS
    assert "background: var(--sc-kl-progress-fill)" in CSS


def test_progress_has_no_red_green_gradient_or_green_fill():
    assert "linear-gradient(90deg, var(--sc-kl-red) 0 58%, var(--sc-kl-green) 58% 100%)" not in CSS
    progress_block = CSS.split(".sc-homepage-spotlight__progress span", 1)[1].split("}", 1)[0]
    assert "var(--sc-kl-green)" not in progress_block
    assert "linear-gradient" not in progress_block


def test_green_remains_reserved_for_auto_operational_status():
    assert "--sc-kl-green: #168a4a" in CSS
    assert ".sc-homepage-spotlight__status" in CSS
    assert "color: #55d88a" in CSS
    assert "'progress_green_reserved_for_status' => true" in MODULE
    assert "'progress_gradient' => false" in MODULE


def test_rotation_behavior_is_unchanged():
    for fragment in [
        "'autoplay_default' => true",
        "'interval_default_ms' => 14000",
        "root.dataset.interval || '14000'",
        "restartProgress",
        "is-running",
        "sc-kl-progress var(--sc-spotlight-interval, 14000ms)",
    ]:
        assert fragment in (MODULE + CSS + JS)


def test_thumbnail_and_manual_curation_contracts_remain_intact():
    for fragment in [
        "private function resolve_source_thumbnail",
        "'source' => 'placeholder'",
        "'manual_card_selection' => true",
        "'automatic_backfill' => false",
        "'minimum_valid_cards_per_page' => 4",
    ]:
        assert fragment in MODULE
