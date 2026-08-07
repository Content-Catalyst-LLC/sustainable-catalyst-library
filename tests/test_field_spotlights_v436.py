from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
def text(p): return p.read_text(encoding='utf-8')

def test_v436_release_markers_and_settings_compatibility():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt'); src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.6' in main
    assert "SC_LIBRARY_VERSION', '4.3.6" in main
    assert 'Stable tag: 4.3.6' in readme
    assert "public const VERSION = '4.3.6'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v436'" in src

def test_admin_console_readiness_and_scalable_panel_workflow():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    for token in ['Field Spotlight Console','MAJOR FIELDS','ARTICLE MAP PANELS','READY PANELS','CURATED ARTICLES','data-panel-list','data-readiness','Search panels or source groups','All panels','Additional','Partial','Empty','Hidden']:
        assert token in src
    for token in ["'configured_article_count' => $configured_count", "'readiness' => $readiness", "'completion_percent'", "'ready_panel_count'", "'partial_panel_count'", "'empty_panel_count'", "'hidden_panel_count'"]:
        assert token in src

def test_admin_source_search_reuses_library_record_semantics():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php'); js=text(PLUGIN/'assets/js/sc-library-field-spotlights-admin.js')
    for token in ['wp_ajax_sc_library_field_spotlight_search_sources','ajax_search_sources','search_source_posts','eligible_source_post_types','url_to_postid','suppress_filters']:
        assert token in src
    for token in ['sc_library_field_spotlight_search_sources','data-source-search','data-result-id','data-source-id','data-source-url','data-source-enabled','data-clear-slot']:
        assert token in js or token in src

def test_editor_shows_resolved_thumbnail_and_keeps_manual_selection():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    for token in ['Search Library','Type a title or paste a canonical URL','data-selected-thumb','resolve_source_thumbnail','The canonical hero destination is registry-owned and cannot be replaced','Selections are manual-only','no automatic backfill occurs']:
        assert token in src
    assert "'selection_mode' => 'manual_only'" in src

def test_new_admin_assets_are_scoped_and_versioned():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php'); css=text(PLUGIN/'assets/css/sc-library-field-spotlights-admin.css'); js=text(PLUGIN/'assets/js/sc-library-field-spotlights-admin.js')
    assert 'admin_enqueue_scripts' in src
    assert "'sc-library-field-spotlights' !== $page" in src
    assert 'sc-library-field-spotlights-admin.css' in src
    assert 'sc-library-field-spotlights-admin.js' in src
    assert '[data-sc-field-spotlights-admin="v4.3.6"]' in js
    for token in ['.sc-fs-admin__metrics','.sc-fs-admin__field-list','.sc-fs-admin__panel-list','.sc-fs-admin__slots','.sc-fs-admin__search-results']:
        assert token in css

def test_public_v435_spotlight_contract_is_preserved():
    template=text(PLUGIN/'templates/field-spotlights.php'); css=text(PLUGIN/'assets/css/sc-library-field-spotlights.css'); js=text(PLUGIN/'assets/js/sc-library-field-spotlights.js')
    assert 'data-sc-field-spotlights="v4.3.5"' in template
    assert 'sc-field-spotlight__hero-media' in template
    assert 'data-more-toggle' in template
    assert 'grid-template-columns:minmax(280px,38%) minmax(0,1fr)' in css
    assert "event.key === 'ArrowRight'" in js
    assert 'autoplay' not in js.lower()

def test_publications_and_homepage_modules_remain_separate():
    publications=text(PLUGIN/'includes/class-sc-library-publications.php'); homepage=text(PLUGIN/'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const SHORTCODE = 'sc_publications'" in publications
    assert "public const VERSION = '4.2.0'" in homepage
    assert "public const SHORTCODE = 'sc_homepage_spotlight'" in homepage
