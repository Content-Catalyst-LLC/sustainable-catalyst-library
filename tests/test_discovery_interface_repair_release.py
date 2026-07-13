from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = (PLUGIN / "sustainable-catalyst-library.php").read_text()
SHORTCODES = (PLUGIN / "includes/class-sc-library-shortcodes.php").read_text()
REST = (PLUGIN / "includes/class-sc-library-rest.php").read_text()
DEVELOPER = (PLUGIN / "includes/class-sc-library-developer-api.php").read_text()
TEMPLATE = (PLUGIN / "templates/library-app.php").read_text()
JS = (PLUGIN / "assets/js/sc-library.js").read_text()
CSS = (PLUGIN / "assets/css/sc-library-discovery.css").read_text()
README = (PLUGIN / "readme.txt").read_text()


def test_release_markers():
    assert "Version: 2.0.1" in MAIN
    assert "SC_LIBRARY_VERSION', '2.0.1'" in MAIN
    assert "Stable tag: 2.0.1" in README


def test_discovery_asset_is_plugin_owned_and_loaded_after_core():
    assert "assets/css/sc-library-discovery.css" in SHORTCODES
    assert "['sc-library']" in SHORTCODES
    assert (PLUGIN / "assets/css/sc-library-discovery.css").exists()
    assert "data-discovery-ui=\"2.0.1\"" in TEMPLATE
    assert "cc-research-library-brand" not in CSS


def test_dynamic_topics_relationships_and_pathways_remain_visible():
    for marker in [
        "data-topic-browser",
        "data-relationship-browser",
        "data-pathway-browser",
        "data-category-list",
        "data-series-list",
        "data-concept-list",
        "data-pathway-list",
    ]:
        assert marker in TEMPLATE
    assert "Browse the knowledge architecture" in TEMPLATE
    assert "Browse series and concepts" in TEMPLATE
    assert "Featured pathways" in TEMPLATE


def test_unified_discovery_endpoint_and_contract():
    assert "/library/discovery" in REST
    assert "public function discovery" in REST
    assert "sc-library-discovery/1.0" in REST
    assert "root_count" in REST
    assert "series_count" in REST
    assert "concept_count" in REST
    assert "interface_version' => '2.0.1'" in REST
    assert "register_rest_route($ns, '/discovery'" in DEVELOPER
    assert "public function rest_discovery" in DEVELOPER


def test_loading_retry_and_fallback_states():
    for marker in [
        "renderDiscovery",
        "data-discovery-retry",
        "renderDiscoveryFailure",
        "api('discovery')",
        "api('categories')",
        "api('series')",
        "api('concepts')",
        "api('pathways')",
        "aria-busy",
        "aria-pressed",
    ]:
        assert marker in JS or marker in TEMPLATE


def test_pathway_cards_are_not_forced_tall_or_narrow():
    assert "min-height: 0" in CSS
    assert "height: auto" in CSS
    assert "minmax(min(100%, 210px), 1fr)" in CSS
    assert "grid-template-rows: auto auto 1fr" in CSS
    assert "container-type: inline-size" in CSS


def test_topic_and_relationship_responsive_layouts():
    assert "minmax(min(100%, 220px), 1fr)" in CSS
    assert "grid-template-columns: repeat(2, minmax(0, 1fr))" in CSS
    assert "@container (max-width: 650px)" in CSS
    assert "@media (max-width: 620px)" in CSS


def test_schema_and_openapi():
    schema = json.loads((ROOT / "docs/schemas/discovery-interface.json").read_text())
    assert schema["properties"]["schema"]["const"] == "sc-library-discovery/1.0"
    assert schema["properties"]["interface_version"]["const"] == "2.0.1"
    openapi = json.loads((ROOT / "docs/openapi.json").read_text())
    assert openapi["info"]["version"] == "2.0.1"
    assert "/discovery" in openapi["paths"]


def test_release_documentation_exists():
    assert (ROOT / "UNIFIED_DISCOVERY_INTERFACE_REPAIR_SETUP_v2.0.1.md").exists()
    assert (ROOT / "RELEASE_NOTES_2.0.1.md").exists()
