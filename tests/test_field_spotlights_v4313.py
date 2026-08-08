from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_markers_and_master_field_spotlight_contract():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    master = text(PLUGIN / "templates/field-spotlights.php")
    single = text(PLUGIN / "templates/field-spotlight-single.php")
    partial = text(PLUGIN / "templates/field-spotlight-stage.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    admin_js = text(PLUGIN / "assets/js/sc-library-field-spotlights-admin.js")

    assert "Version: 4.3.13" in main
    assert "SC_LIBRARY_VERSION', '4.3.13" in main
    assert "Stable tag: 4.3.13" in readme
    assert "public const VERSION = '4.3.13'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v4313'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v4313'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v4313'" in src

    # The durable v4.3.12 panel store must not be renamed during a presentation release.
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in src

    assert 'data-sc-field-spotlights="v4.3.13"' in master
    assert 'data-sc-field-spotlights-mode="master"' in master
    assert 'class="sc-field-master__field-tabs"' in master
    assert 'data-field-select-key=' in master
    assert 'data-field-select' in master
    assert "templates/field-spotlight-stage.php" in master
    assert "templates/field-spotlight-stage.php" in single
    assert 'data-primary-tabs' in partial
    assert 'data-additional-tabs' in partial
    assert '[data-sc-field-spotlights="v4.3.13"]' in js
    assert '[data-sc-field-spotlights-admin="v4.3.13"]' in admin_js


def test_stack_shortcode_uses_master_mode_and_single_shortcode_remains_compatible():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    assert "$master_mode = ! $only_field && count( $fields ) > 1;" in src
    assert "include SC_LIBRARY_DIR . 'templates/field-spotlights.php';" in src
    assert "include SC_LIBRARY_DIR . 'templates/field-spotlight-single.php';" in src
    assert "public function shortcode_single" in src
    assert "return $this->render_public( $field, $atts );" in src


def test_master_template_renders_one_shared_stage_not_fourteen_spotlight_sections():
    master = text(PLUGIN / "templates/field-spotlights.php")
    # Field iteration exists only for selector buttons/options; the complete stage is included once.
    assert master.count("include SC_LIBRARY_DIR . 'templates/field-spotlight-stage.php';") == 1
    assert "foreach ( $field_list as $index => $selector_field )" in master
    assert "sc-field-spotlight--master-stage" not in master  # class comes from the shared stage partial
    assert "sc-field-spotlights__master-data" in master
    assert "'fields' => $field_list" in master


def test_master_javascript_switches_field_data_into_shared_stage():
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    for token in [
        "const initializeMaster = (root) =>",
        "const activateField = (next, focusTab = false) =>",
        "stage.setField(field, activeField + 1);",
        "root.dataset.activeField = field.key || '';",
        "const setField = (nextField, nextFieldNumber = 1) =>",
        "buildPanelNavigation();",
        "activate(0);",
        "fieldSelect?.addEventListener('change'",
        "['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End']",
    ]:
        assert token in js


def test_first_eight_panel_disclosure_and_playback_are_preserved():
    partial = text(PLUGIN / "templates/field-spotlight-stage.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    assert "$limit = 8;" in partial
    assert "array_slice( $panels, 0, $limit )" in partial
    assert "array_slice( $panels, $limit )" in partial
    assert "panels.length <= 8" in js
    assert "all.slice(0, 8)" in js
    assert "secondaryExpanded = !secondaryExpanded" in js
    assert "window.setTimeout(() => activate(adjacentIndex(1), false), interval)" in js


def test_master_visual_system_is_sharp_light_and_unified():
    css = text(PLUGIN / "assets/css/sc-library-field-spotlights.css")
    for token in [
        ".sc-field-spotlights--master{",
        "border-top:6px solid #000",
        ".sc-field-master__field-tabs{",
        "grid-template-columns:repeat(4,minmax(0,1fr))",
        ".sc-field-master__field-tab.is-active",
        "box-shadow:inset 0 -3px 0 var(--scfs-red)",
        ".sc-field-spotlights--master>.sc-field-spotlight--master-stage",
        "background:#fff",
        "border-radius:0",
        ".sc-field-master__mobile-select{display:none}",
    ]:
        assert token in css


def test_existing_panel_content_persistence_contract_is_preserved():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    assert "update_option( self::PANEL_CONTENT_OPTION, $store, false )" in src
    assert "$content_store = $this->panel_content_store();" in src
    assert "array( 'hero_title', 'hero_description', 'hero_cta', 'articles' )" in src
    assert "if ( empty( $article['source_id'] ) && empty( $article['url'] ) ) { continue; }" in src
