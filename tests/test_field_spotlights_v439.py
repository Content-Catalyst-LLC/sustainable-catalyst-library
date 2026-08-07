from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'


def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')


def test_v439_release_markers_and_durable_settings_boundary():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'Version: 4.3.9' in main
    assert "SC_LIBRARY_VERSION', '4.3.9" in main
    assert 'Stable tag: 4.3.9' in readme
    assert "public const VERSION = '4.3.9'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v439'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v439'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v439'" in src
    assert 'data-sc-field-spotlights="v4.3.9"' in template
    assert '[data-sc-field-spotlights="v4.3.9"]' in js


def test_additional_fields_are_truly_collapsed_until_opened():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert '.sc-field-spotlight__additional-tabs[hidden]{display:none!important}' in css
    assert 'data-additional-tabs hidden aria-hidden="true"' in template
    assert 'aria-controls="<?php echo esc_attr( $additional_id ); ?>"' in template
    assert "additional.hidden = !secondaryExpanded;" in js
    assert "additional.setAttribute('aria-hidden', secondaryExpanded ? 'false' : 'true');" in js
    assert "more.setAttribute('aria-expanded', secondaryExpanded ? 'true' : 'false');" in js
    assert "secondaryExpanded = !secondaryExpanded;" in js


def test_additional_fields_follow_homepage_spotlight_disclosure_contract():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    for token in [
        'grid-template-columns:24px minmax(0,1fr) auto',
        '.sc-field-spotlight__more:hover,.sc-field-spotlight__more[aria-expanded=true]{background:#2a2a2d;color:#fff}',
        'width:22px;height:22px;place-items:center;border:1px solid #57575d;border-radius:0',
        "labels.hide_additional_label || 'Hide additional fields'",
        "labels.additional_label || 'Explore additional fields'",
    ]:
        assert token in css + js


def test_currently_playing_panel_is_white_with_black_text_and_red_number():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    assert '.sc-field-spotlight__tab.is-active,.sc-field-spotlight__tab[aria-selected=true]{background:#fff;color:#090909}' in css
    assert '.sc-field-spotlight__tab.is-active span,.sc-field-spotlight__tab[aria-selected=true] span{color:var(--scfs-red)}' in css
    assert '.sc-field-spotlight__tab.is-active:hover,.sc-field-spotlight__tab[aria-selected=true]:hover{background:#fff;color:#090909}' in css


def test_public_geometry_is_sharp_not_rounded():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    assert '.sc-field-spotlight{border-radius:0;box-shadow:0 8px 22px rgba(0,0,0,.09)}' in css
    assert '.sc-field-spotlight__hero,.sc-field-spotlight__selected,.sc-field-spotlight__hero-media,.sc-field-spotlight__hero-action,.sc-field-spotlight__controls button{border-radius:0}' in css
    # The v4.3.9 overrides must come after the older rounded declarations.
    assert css.rfind('.sc-field-spotlight{border-radius:0') > css.rfind('.sc-field-spotlight{border-radius:12px')


def test_collapsed_rotation_remains_first_eight_then_expands_to_all():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'public const DEFAULT_PANEL_LIMIT = 8' in src
    assert '$limit = 8;' in template
    assert 'const navigableIndexes = () =>' in js
    assert "if (!additional || secondaryExpanded) return all.map(({idx}) => idx);" in js
    assert "const primary = all.filter(({panel}) => panelTier(panel) === 'primary')" in js
    assert "if (!secondaryExpanded && panelTier(panels[active]) === 'additional')" in js


def test_homepage_and_publications_remain_separate_surfaces():
    publications = text(PLUGIN / 'includes/class-sc-library-publications.php')
    homepage = text(PLUGIN / 'includes/class-sc-library-homepage-spotlight.php')
    assert "public const VERSION = '4.3.3'" in publications
    assert "public const VERSION = '4.2.0'" in homepage
