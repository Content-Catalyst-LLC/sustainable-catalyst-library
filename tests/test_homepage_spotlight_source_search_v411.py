#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text(encoding="utf-8")
README = (PLUGIN / "readme.txt").read_text(encoding="utf-8")
MODULE = (PLUGIN / "includes/class-sc-library-homepage-spotlight.php").read_text(encoding="utf-8")
ADMIN_JS = (PLUGIN / "assets/js/sc-library-homepage-spotlight-admin.js").read_text(encoding="utf-8")


def test_v411_release_markers():
    assert "Version: 4.1.3" in MAIN
    assert "SC_LIBRARY_VERSION', '4.1.3" in MAIN
    assert "Stable tag: 4.1.3" in README
    assert "public const VERSION = '4.1.3'" in MODULE


def test_search_filters_cannot_hide_valid_sources():
    assert "private function search_source_posts" in MODULE
    assert "'suppress_filters' => true" in MODULE
    search_block = MODULE.split("private function search_source_posts", 1)[1].split("private function is_valid_source_record", 1)[0]
    assert "'suppress_filters' => false" not in search_block
    assert "$wpdb->esc_like" in search_block
    assert "post_title LIKE %s" in search_block


def test_title_url_slug_and_id_lookup_paths():
    for fragment in [
        "ctype_digit( $query )",
        "wp_http_validate_url( $query )",
        "url_to_postid( $query )",
        "wp_parse_url( $query, PHP_URL_PATH )",
        "'name' => $slug",
        "'s' => $query",
    ]:
        assert fragment in MODULE
    assert "Search by title or paste a published article URL" in MODULE
    assert "Search by title, post ID, slug, or canonical URL" in MODULE


def test_selection_still_requires_explicit_click_and_valid_source():
    assert "sourceId.value = String(item.id)" in ADMIN_JS
    assert "private function is_valid_source_record" in MODULE
    assert "empty( $post->post_password )" in MODULE
    assert "if ( ! $this->is_valid_source_record( $source ) )" in MODULE
    assert "item.url" in ADMIN_JS
