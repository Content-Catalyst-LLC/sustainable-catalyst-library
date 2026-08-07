from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'

def text(path):
    return path.read_text(encoding='utf-8')

def test_v438_release_markers_and_cache_boundaries():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'Version: 4.3.8' in main
    assert "SC_LIBRARY_VERSION', '4.3.8" in main
    assert 'Stable tag: 4.3.8' in readme
    assert "public const VERSION = '4.3.8'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v438'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v438'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v438'" in src
    assert 'data-sc-field-spotlights="v4.3.8"' in template
    assert '[data-sc-field-spotlights="v4.3.8"]' in js

def test_selector_rail_is_flat_and_quiet():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    for token in [
        '.sc-field-spotlight__panel-nav{padding:0',
        '.sc-field-spotlight__primary-tabs,.sc-field-spotlight__additional-tabs{gap:0}',
        'border-radius:0;background:transparent;color:#bdbdc2',
        '.sc-field-spotlight__tab::after',
        'height:2px;transform:scaleX(0)',
        '.sc-field-spotlight__tab.is-active::after',
    ]:
        assert token in css

def test_additional_fields_disclosure_is_integrated_not_carded():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'data-more-toggle' in template and 'data-additional-tabs' in template
    for token in [
        '.sc-field-spotlight__more{min-height:42px;margin:0',
        'border-radius:0;background:#0f0f11',
        '.sc-field-spotlight__more-icon{width:auto;height:auto;border:0;border-radius:0',
        '.sc-field-spotlight__additional-tabs{padding:0;border-top:1px solid #29292e}',
    ]:
        assert token in css

def test_article_map_keeps_v437_homepage_spotlight_scale():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    for token in [
        'grid-template-columns:138px minmax(0,1fr)',
        'min-height:132px',
        'width:138px;height:94px',
        'width:82px;height:62px',
        'width:68px;height:54px',
    ]:
        assert token in css
    assert '.sc-field-spotlight__hero{background:#fbfaf7}' in css

def test_editorial_surface_removes_redundant_labels_and_heavy_rounding():
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    assert 'CURATED FROM THIS SERIES' not in template
    assert "`${labels.hero_label || 'Article Map'} · HERO`" not in js
    assert "`${labels.hero_label || 'Article Map'}`" in js
    assert '.sc-field-spotlight__hero{border-color:#d4cec5;border-radius:0' in css
    assert '.sc-field-spotlight__selected{border-color:#d4cec5;border-radius:0' in css
    assert '.sc-field-spotlight__selected-head{padding:15px 18px 12px' in css

def test_supporting_articles_retain_thumbnails_but_use_lighter_rows():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'sc-field-spotlight__card-media' in template
    assert 'loading="lazy"' in template
    assert 'sc-field-spotlight__placeholder--small' in template
    for token in [
        'grid-template-columns:124px minmax(0,1fr);min-height:154px',
        'border-color:#ded8d0;background:#fff',
        'box-shadow:inset 3px 0 0 var(--scfs-green)',
    ]:
        assert token in css

def test_telemetry_and_transport_controls_are_visually_secondary():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'sc-field-spotlight__panel-count' in template
    assert 'sc-field-spotlight__browse-link' in template
    for token in [
        '.sc-field-spotlight__telemetry span,.sc-field-spotlight__telemetry a{min-height:0;padding:0;border:0;border-radius:0;background:transparent}',
        '.sc-field-spotlight__controls{display:flex;justify-content:flex-end;gap:8px;padding:12px 0 0}',
        'border:1px solid transparent;border-radius:0;background:transparent',
        '.sc-field-spotlight__status i{width:6px;height:6px;box-shadow:none}',
    ]:
        assert token in css

def test_v437_behavioral_contract_stays_present():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    assert 'public const DEFAULT_PANEL_LIMIT = 8' in src
    assert 'public const DEFAULT_INTERVAL = 14000' in src
    assert '$limit = 8;' in template
    for token in ['navigableIndexes', 'secondaryExpanded', 'playbackState', 'restartProgress', 'visibilitychange', 'touchstart', 'touchend']:
        assert token in js

def test_other_library_surfaces_remain_separate():
    publications = text(PLUGIN / 'includes/class-sc-library-publications.php')
    homepage = text(PLUGIN / 'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const SHORTCODE = 'sc_publications'" in publications
    assert "public const VERSION = '4.2.0'" in homepage
    assert "public const SHORTCODE = 'sc_homepage_spotlight'" in homepage
