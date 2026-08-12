from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"

def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")

def test_release_identity_and_cache_busted_field_spotlight_runtime():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    readme = text(PLUGIN / "readme.txt")
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    master = text(PLUGIN / "templates/field-spotlights.php")
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    assert "Version: 4.3.21.1" in main
    assert "SC_LIBRARY_VERSION', '4.3.21.1" in main
    assert "Stable tag: 4.3.21.1" in readme
    assert "public const VERSION = '4.3.21.1'" in src
    assert 'data-sc-field-spotlights="v4.3.21.1"' in master
    assert '[data-sc-field-spotlights="v4.3.21.1"]' in js
    assert "assets/js/sc-library-field-spotlights.js', array(), self::VERSION" in src

def test_render_time_integrity_guard_recovers_single_field_or_panel_signature():
    src = text(PLUGIN / "includes/class-sc-library-field-spotlights.php")
    activator = text(PLUGIN / "includes/class-sc-library-activator.php")
    for token in [
        "public_surface_appears_collapsed",
        "count( $definitions ) > 1 && count( $fields ) <= 1",
        "$canonical_count > 1 && $public_count <= 1",
        "SC_Library_Activator::repair_publication_surface_integrity_runtime();",
        "sc_library_publications_integrity_repair_v43211",
        "runtime_guard",
    ]:
        assert token in src or token in activator

def test_master_field_controls_have_server_side_fallback_routes():
    master = text(PLUGIN / "templates/field-spotlights.php")
    for token in [
        "sc_publication_field",
        "remove_query_arg( 'sc_publication_panel' )",
        '<a role="tab" class="sc-field-master__field-tab',
        "data-initial-field-key",
        "<noscript><style>",
    ]:
        assert token in master

def test_panel_controls_have_server_side_fallback_routes_and_initial_selection():
    stage = text(PLUGIN / "templates/field-spotlight-stage.php")
    for token in [
        "sc_publication_panel",
        "data-initial-panel-key",
        '<a role="tab" class="sc-field-spotlight__tab',
        "$initial_index === $index",
        "$initial = $panels[ $initial_index ];",
    ]:
        assert token in stage

def test_javascript_progressively_enhances_links_instead_of_being_required_for_navigation():
    js = text(PLUGIN / "assets/js/sc-library-field-spotlights.js")
    for token in [
        "const fallbackUrl = (fieldKey, panelKey = '', hash = '') =>",
        "document.createElement('a')",
        "url.searchParams.set('sc_publication_field', fieldKey)",
        "url.searchParams.set('sc_publication_panel', panelKey)",
        "event.preventDefault();",
        "initialPanelKey",
        "initialFieldKey",
    ]:
        assert token in js

def test_repair_remains_bounded_and_editorial_payloads_are_not_deleted():
    activator = text(PLUGIN / "includes/class-sc-library-activator.php")
    assert "visible_fields <= 1" in activator
    assert "visible_field_count <= 1" in activator
    assert "configured_visibility > 1 && $visible_panels <= 1" in activator
    assert "delete_option( $pub_option" not in activator
    assert "delete_option( $fs_option" not in activator
    assert "unset( $fs_panels" not in activator

def test_publications_and_course_features_are_preserved():
    main = text(PLUGIN / "sustainable-catalyst-library.php")
    bootstrap = text(PLUGIN / "includes/class-sc-library-extension-bootstrap-v402.php")
    course = text(PLUGIN / "includes/class-sc-library-open-course-finder.php")
    assert "class-sc-library-publications.php" in main
    assert "class-sc-library-field-spotlights.php" in main
    assert "class-sc-library-open-course-finder.php" in bootstrap
    assert "SC_Library_Open_Course_Finder" in bootstrap
    assert "sc_library_course_plan_v4321" in course
