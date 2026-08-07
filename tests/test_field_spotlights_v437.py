from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
def text(p): return p.read_text(encoding='utf-8')

def test_v437_release_markers_and_cache_boundaries():
    main=text(PLUGIN/'sustainable-catalyst-library.php'); readme=text(PLUGIN/'readme.txt'); src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    assert 'Version: 4.3.7' in main
    assert "SC_LIBRARY_VERSION', '4.3.7" in main
    assert 'Stable tag: 4.3.7' in readme
    assert "public const VERSION = '4.3.7'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v437'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v437'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v437'" in src

def test_article_map_hero_matches_homepage_spotlight_lead_scale():
    css=text(PLUGIN/'assets/css/sc-library-field-spotlights.css')
    for token in ['grid-template-columns:138px minmax(0,1fr)','min-height:132px','width:138px;height:94px','width:82px;height:62px','width:68px;height:54px','box-shadow:inset 4px 0 0 var(--scfs-red)']:
        assert token in css

def test_autoplay_parity_defaults_and_controls():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php'); template=text(PLUGIN/'templates/field-spotlights.php'); js=text(PLUGIN/'assets/js/sc-library-field-spotlights.js')
    assert 'public const DEFAULT_INTERVAL = 14000' in src
    for token in ['data-autoplay','data-interval','data-pause-on-hover','data-panel-progress','data-playback-status','data-panel-toggle','data-panel-toggle-icon','data-panel-toggle-text']:
        assert token in template
    for token in ["prefers-reduced-motion: reduce", "spotlight.dataset.autoplay !== 'true'", "spotlight.dataset.pauseOnHover !== 'false'", 'visibilitychange', 'touchstart', 'touchend', 'setTimeout', 'playbackState', 'restartProgress']:
        assert token in js

def test_first_eight_panels_are_fixed_primary_tier():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php'); template=text(PLUGIN/'templates/field-spotlights.php')
    assert 'public const DEFAULT_PANEL_LIMIT = 8' in src
    assert "'panel_limit' => self::DEFAULT_PANEL_LIMIT" in src
    assert "$existing['general']['panel_limit'] = self::DEFAULT_PANEL_LIMIT;" in src
    assert '$limit = 8;' in template
    assert 'data-more-toggle' in template and 'data-additional-tabs' in template

def test_collapsed_rotation_excludes_additional_panels():
    js=text(PLUGIN/'assets/js/sc-library-field-spotlights.js')
    for token in ['navigableIndexes', "panelTier(panel) === 'primary'", 'secondaryExpanded', 'adjacentIndex', "panelTier(panels[active]) === 'additional'", 'updateAdditional']:
        assert token in js

def test_progress_and_status_visual_contract():
    css=text(PLUGIN/'assets/css/sc-library-field-spotlights.css'); template=text(PLUGIN/'templates/field-spotlights.php')
    for token in ['sc-field-spotlight__progress','sc-field-spotlight-progress','var(--sc-field-spotlight-interval,14000ms)','sc-field-spotlight__status']:
        assert token in css
    assert '--sc-field-spotlight-interval:' in template

def test_manual_editorial_contract_and_thumbnail_resolver_preserved():
    src=text(PLUGIN/'includes/class-sc-library-field-spotlights.php')
    for token in ["'hero_role' => 'article_map'", "'selection_mode' => 'manual_only'", 'resolve_source_thumbnail', 'thumbnail_placeholder', 'The canonical hero destination is registry-owned and cannot be replaced']:
        assert token in src

def test_other_library_surfaces_remain_separate():
    publications=text(PLUGIN/'includes/class-sc-library-publications.php'); homepage=text(PLUGIN/'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const SHORTCODE = 'sc_publications'" in publications
    assert "public const VERSION = '4.2.0'" in homepage
    assert "public const SHORTCODE = 'sc_homepage_spotlight'" in homepage
