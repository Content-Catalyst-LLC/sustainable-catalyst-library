from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'

def text(path: Path) -> str:
    return path.read_text(encoding='utf-8')

def test_v4310_release_markers_and_cache_boundaries():
    main = text(PLUGIN / 'sustainable-catalyst-library.php')
    readme = text(PLUGIN / 'readme.txt')
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    template = text(PLUGIN / 'templates/field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert 'Version: 4.3.10' in main
    assert "SC_LIBRARY_VERSION', '4.3.10" in main
    assert 'Stable tag: 4.3.10' in readme
    assert "public const VERSION = '4.3.10'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v4310'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v4310'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v4310'" in src
    assert 'data-sc-field-spotlights="v4.3.10"' in template
    assert '[data-sc-field-spotlights="v4.3.10"]' in js

def test_populated_supporting_slot_is_automatically_active():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    assert '$has_selection = $source_id > 0 || \'\' !== $url;' in src
    assert "'enabled' => $has_selection ? 1 : 0" in src
    assert "if ( empty( $article['source_id'] ) && empty( $article['url'] ) ) { continue; }" in src
    assert "if ( ! empty( $slot['source_id'] ) || ! empty( $slot['url'] ) ) { $configured_count++; }" in src

def test_slot_editor_requires_no_second_enable_action():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights-admin.js')
    assert 'data-slot-publish-state' in src
    assert 'Publishes on save' in src
    assert 'Enable this slot' not in src
    assert "enabled.value = '1'" in js
    assert "publishState.textContent = 'Publishes on save'" in js

def test_article_source_resolution_supports_id_url_and_slug_fallback():
    src = text(PLUGIN / 'includes/class-sc-library-field-spotlights.php')
    for token in [
        'private function resolve_article_source_id',
        'url_to_postid( $url )',
        'wp_parse_url( $url, PHP_URL_PATH )',
        "'name' => $slug",
        "'post_status' => 'publish'",
    ]:
        assert token in src

def test_v439_visual_and_disclosure_contract_is_preserved():
    css = text(PLUGIN / 'assets/css/sc-library-field-spotlights.css')
    js = text(PLUGIN / 'assets/js/sc-library-field-spotlights.js')
    assert '.sc-field-spotlight__additional-tabs[hidden]{display:none!important}' in css
    assert '.sc-field-spotlight__tab.is-active,.sc-field-spotlight__tab[aria-selected=true]{background:#fff;color:#090909}' in css
    assert '.sc-field-spotlight{border-radius:0;box-shadow:0 8px 22px rgba(0,0,0,.09)}' in css
    assert 'const navigableIndexes = () =>' in js
