from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
UNIFIED = (PLUGIN / "includes/class-sc-library-unified-system.php").read_text()
ACTIVATOR = (PLUGIN / "includes/class-sc-library-activator.php").read_text()
PORTABILITY = (PLUGIN / "includes/class-sc-library-portability.php").read_text()
DEVELOPER = (PLUGIN / "includes/class-sc-library-developer-api.php").read_text()
HARDENING = (PLUGIN / "includes/class-sc-library-hardening.php").read_text()
CSS = (PLUGIN / "assets/css/sc-library-unified-system.css").read_text()
JS = (PLUGIN / "assets/js/sc-library-unified-system.js").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_v2_release_markers_and_wiring():
    assert "Version: 2.0.1" in MAIN
    assert "SC_LIBRARY_VERSION', '2.0.1'" in MAIN
    assert "Stable tag: 2.0.1" in README
    assert "class-sc-library-unified-system.php" in MAIN
    assert "new SC_Library_Unified_System" in MAIN
    assert "$unified_system->register_hooks();" in MAIN


def test_unified_schemas_and_shortcodes():
    for marker in [
        "sc-library-living-system/1.0",
        "sc-library-system-manifest/1.0",
        "sc-library-system-event/1.0",
        "sc_library_living_system",
        "sc_library_unified_workspace",
        "sc_library_system_status",
    ]:
        assert marker in UNIFIED


def test_three_layer_architecture_and_journey():
    for marker in [
        "public",
        "research",
        "institutional",
        "Discover",
        "Connect",
        "Research",
        "Analyze",
        "Produce",
        "Publish",
        "Preserve",
    ]:
        assert marker in UNIFIED


def test_manifest_and_event_tables():
    for marker in [
        "sc_library_system_manifests",
        "sc_library_system_events",
        "system_manifests_sql",
        "system_events_sql",
        "dbDelta($system_manifests_sql)",
        "dbDelta($system_events_sql)",
    ]:
        assert marker in ACTIVATOR


def test_manifest_is_checksummed_and_persisted():
    assert "hash('sha256', $json)" in UNIFIED
    assert "manifest_uuid" in UNIFIED
    assert "content_hash" in UNIFIED
    assert "persist_manifest" in UNIFIED
    assert "system.manifest.created" in UNIFIED


def test_public_private_boundaries():
    for marker in [
        "automatic_publication' => false",
        "private_data_publicly_exposed' => false",
        "unset($manifest['routes']['administrative'])",
        "visibility = 'public'",
        "workspace_json",
        "api_key",
        "secret",
        "token",
    ]:
        assert marker in UNIFIED


def test_routes_and_developer_api():
    for route in [
        "/library/system/status",
        "/library/system/capabilities",
        "/library/system/activity",
        "/library/system/manifest",
        "/library/system/manifest/create",
        "sustainable-catalyst-library/v1",
        "/system",
    ]:
        assert route in UNIFIED
    assert "system.manifest.created" in DEVELOPER
    assert "system-manifest" in DEVELOPER


def test_unified_portal_templates_and_assets():
    assert (PLUGIN / "templates/library-living-system.php").exists()
    assert (PLUGIN / "templates/library-unified-workspace.php").exists()
    for marker in [
        "Public Knowledge",
        "Research Workspace",
        "Institutional Operations",
        "[sc_library mode=",
        "Research Librarian",
        "Institutional Archive",
    ]:
        assert marker in (PLUGIN / "templates/library-living-system.php").read_text()
    for marker in [
        "@media(max-width:700px)",
        "prefers-reduced-motion",
        "min-width:0",
        "@media print",
    ]:
        assert marker in CSS or marker in JS
    assert "navigator.clipboard" in JS
    assert "scrollIntoView" in JS


def test_hardening_detects_unified_assets():
    assert "sc-library-unified-system" in HARDENING
    assert "sc_library_living_system" in HARDENING
    assert "sc_library_unified_workspace" in HARDENING
    assert "sc_library_system_status" in HARDENING


def test_portable_export_v3():
    assert "sc-library-portable-export/3.0" in PORTABILITY
    assert "unified_system" in PORTABILITY
    assert "system_manifests" in PORTABILITY
    assert "system_events" in PORTABILITY
    schema = (ROOT / "docs/postgresql-schema.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS system_manifests" in schema
    assert "CREATE TABLE IF NOT EXISTS system_events" in schema
    manifest = json.loads((ROOT / "docs/portable-export-manifest.example.json").read_text())
    assert manifest["schema"] == "sc-library-portable-export/3.0"
    assert manifest["plugin_version"] == "2.0.1"
    assert "system_manifests" in manifest["entities"]
    assert "system_events" in manifest["entities"]


def test_openapi_and_json_schemas():
    openapi = json.loads((ROOT / "docs/openapi.json").read_text())
    assert openapi["info"]["version"] == "2.0.1"
    assert "/system" in openapi["paths"]
    system_manifest = json.loads((ROOT / "docs/schemas/system-manifest.json").read_text())
    assert system_manifest["properties"]["schema"]["const"] == "sc-library-system-manifest/1.0"
    system_event = json.loads((ROOT / "docs/schemas/system-event.json").read_text())
    assert "visibility" in system_event["properties"]


def test_no_iframe_or_automatic_publication():
    combined = UNIFIED + (PLUGIN / "templates/library-living-system.php").read_text()
    assert "<iframe" not in combined.lower()
    assert "wp_publish_post" not in UNIFIED
    assert "post_status' => 'publish'" not in UNIFIED
    assert "post_status' => 'draft'" in UNIFIED


def test_release_documentation_exists():
    assert (ROOT / "UNIFIED_LIVING_KNOWLEDGE_SYSTEM_SETUP_v2.0.0.md").exists()
    assert (ROOT / "RELEASE_NOTES_2.0.0.md").exists()
