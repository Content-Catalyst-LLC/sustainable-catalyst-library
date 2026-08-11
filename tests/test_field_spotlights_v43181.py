from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_and_cache_busting():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    admin_js = text(PLUGIN / "assets/js/sc-library-field-spotlights-admin.js")

    assert "Version: 4.3.18.1" in main
    assert "SC_LIBRARY_VERSION', '4.3.18.1" in main
    assert "Stable tag: 4.3.18.1" in readme
    assert "public const VERSION = '4.3.18.1'" in src
    assert "SETTINGS_GROUP = 'sc_library_field_spotlights_v43181'" in src
    assert "MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v43181'" in src
    assert "PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v43181'" in src
    assert '[data-sc-field-spotlights="v4.3.18.1"]' in js
    assert '[data-sc-field-spotlights-admin="v4.3.18.1"]' in admin_js


def test_stack_shortcode_restores_all_major_field_surfaces():
    master = text(PLUGIN / "templates/field-spotlights.php")
    assert 'class="sc-field-spotlights-stack"' in master
    assert 'foreach ( $field_list as $index => $stack_field )' in master
    assert 'data-sc-field-spotlights-mode="single"' in master
    assert 'data-field-stack-key=' in master
    assert 'templates/field-spotlight-stage.php' in master
    assert 'sc-field-master__selector' not in master
    assert 'sc-field-spotlights__master-data' not in master


def test_each_stacked_field_has_independent_runtime_payload():
    master = text(PLUGIN / "templates/field-spotlights.php")
    stage = text(PLUGIN / "templates/field-spotlight-stage.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    assert '$include_data = true;' in master
    assert 'class="sc-field-spotlight__data"' in stage
    assert "const initializeSingle = (root) =>" in js
    assert "initializeStage(spotlight, field" in js
    assert "data-sc-field-spotlights-mode=\"single\"" in master


def test_approved_panel_disclosure_and_autoplay_contract_remains():
    stage = text(PLUGIN / "templates/field-spotlight-stage.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    assert "$limit = 8;" in stage
    assert "array_slice( $panels, 0, $limit )" in stage
    assert "array_slice( $panels, $limit )" in stage
    assert "all.slice(0, 8)" in js
    assert "secondaryExpanded = !secondaryExpanded" in js
    assert "window.setTimeout(() => activate(adjacentIndex(1), false), interval)" in js
    assert "[data-panel-prev]" in js
    assert "[data-panel-next]" in js


def test_durable_field_content_and_current_research_access_are_preserved():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    connectors = text(PLUGIN / "includes/class-sc-library-scholarly-library-connectors.php")
    readme = text(PLUGIN / "readme.txt")
    assert "PANEL_CONTENT_OPTION = 'sc_library_field_spotlight_panel_content_v4312'" in src
    assert "SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434'" in src
    for token in ["UCD", "arXiv", "OpenAlex", "Crossref", "DataCite", "PubMed"]:
        assert token.lower() in connectors.lower() or token.lower() in readme.lower()


def test_stack_keeps_refined_master_visual_language():
    css = text(PLUGIN / "assets/css/sc-library-field-spotlights.css")
    for token in [
        ".sc-field-spotlights-stack{display:grid;gap:34px;width:100%}",
        ".sc-field-spotlights--stack-item",
        ".sc-field-spotlights--master>.sc-field-spotlight--master-stage",
        "background:#fff",
        "border-radius:0",
        "box-shadow:inset 0 -3px 0 var(--scfs-red)",
    ]:
        assert token in css
