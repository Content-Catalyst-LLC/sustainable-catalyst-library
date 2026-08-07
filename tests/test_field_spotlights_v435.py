from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
def text(path): return path.read_text(encoding='utf-8')

def test_release_markers_and_public_shortcodes():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt'); src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.5' in main
    assert "SC_LIBRARY_VERSION', '4.3.5" in main
    assert 'Stable tag: 4.3.5' in readme
    assert "public const VERSION = '4.3.5'" in src
    assert "public const SHORTCODE_STACK = 'sc_field_spotlights'" in src
    assert "public const SHORTCODE_SINGLE = 'sc_field_spotlight'" in src
    assert "add_shortcode( self::SHORTCODE_STACK" in src
    assert "add_shortcode( self::SHORTCODE_SINGLE" in src

def test_registry_and_flattened_panel_contract_survive():
    registry=text(PLUGIN/'includes/data/publications-article-map-registry-v431.php'); src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert registry.count("'url' =>") == 170
    assert "SC_Library_Publications::article_map_registry()" in src
    assert "'source_group'" in src and 'parent_panel' not in src
    assert "public const DEFAULT_PANEL_LIMIT = 8;" in src
    assert "? 'primary' : 'additional'" in src

def test_article_map_hero_and_manual_supporting_slots():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php'); template=text(PLUGIN/'templates/field-spotlights.php')
    assert "'role' => 'article_map'" in src
    assert "'selection_mode' => 'manual_only'" in src
    assert 'No automatic article backfill' in src
    assert 'sc-field-spotlight__hero' in template
    assert 'sc-field-spotlight__cards' in template
    assert 'data-supporting-cards' in template
    assert '2-8' in text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.5.md') or '2 through 8' in text(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.5.md')

def test_thumbnail_resolution_matches_spotlight_sources():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    for token in ['featured','_sc_library_thumbnail_id','pdf_preview','attached_image','content_image','_sc_library_thumbnail_url','placeholder']:
        assert token in src
    template=text(PLUGIN/'templates/field-spotlights.php')
    assert '<img src=' in template
    assert 'KL</strong><small>ARTICLE MAP' in template

def test_progressive_disclosure_and_accessibility():
    template=text(PLUGIN/'templates/field-spotlights.php'); js=text(PLUGIN/'assets/js/sc-library-field-spotlights.js')
    assert 'data-more-toggle' in template
    assert 'aria-expanded="false"' in template
    assert 'data-additional-tabs' in template
    assert 'Explore additional fields' in text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert "event.key === 'ArrowRight'" in js
    assert "event.key === 'ArrowLeft'" in js
    assert "event.key === 'Home'" in js
    assert "event.key === 'End'" in js
    assert 'autoplay' not in js.lower()

def test_spotlight_parity_visual_contract():
    css=text(PLUGIN/'assets/css/sc-library-field-spotlights.css')
    for token in ['--scfs-black','--scfs-cream','--scfs-red','--scfs-green','.sc-field-spotlight__hero-media','.sc-field-spotlight__card-media']:
        assert token in css
    assert 'grid-template-columns:minmax(280px,38%) minmax(0,1fr)' in css
    assert 'object-fit:cover' in css

def test_admin_supporting_article_editor_and_immutable_map():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    for token in ['Edit content','Spotlight content','Article Map hero:','canonical hero destination cannot be replaced','Article URL','Enable this slot','Save Spotlight content']:
        assert token in src
    assert "url_to_postid( $url )" in src

def test_publications_and_homepage_spotlight_versions_are_preserved():
    publications=text(PLUGIN/'includes/class-sc-library-publications.php'); homepage=text(PLUGIN/'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const SHORTCODE = 'sc_publications'" in publications
    assert "public const VERSION = '4.2.0'" in homepage
    assert "public const SHORTCODE = 'sc_homepage_spotlight'" in homepage
