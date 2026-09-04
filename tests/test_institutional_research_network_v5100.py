from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
MODULE = PLUGIN / "includes/class-sc-library-institutional-research-network.php"
NETWORK = PLUGIN / "includes/class-sc-library-research-network-console.php"
JS = PLUGIN / "assets/js/sc-library-institutional-network-v5100.js"
CSS = PLUGIN / "assets/css/sc-library-institutional-network-v5100.css"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_backend_and_module_wiring():
    main = text(MAIN)
    assert "Version: 5.10.0" in main
    assert "SC_LIBRARY_VERSION', '5.10.0'" in main
    assert "class-sc-library-institutional-research-network.php" in main
    assert "SC_Library_Institutional_Research_Network" in main
    assert '__version__ = "2.0.0"' in text(ROOT / "library-backend/app/__init__.py")


def test_shortcode_rest_proxy_and_assets_are_wired():
    module = text(MODULE)
    assert "sc_institutional_research_network" in module
    assert "/institutional-research-network/search" in module
    assert "/institutional-research-network/graph" in module
    assert "SC_Library_Python_Backend::base_url()" in module
    assert "sc-library-institutional-network-v5100.js" in module
    assert JS.exists() and CSS.exists()


def test_interface_preserves_entitlement_affiliation_and_rights_boundaries():
    module = text(MODULE)
    assert "Metadata discovery is not entitlement." in module
    assert "does not imply affiliation, partnership, endorsement, or institutional approval" in module
    assert "Unknown rights remain review-required." in module
    assert "underlying content" in module


def test_network_sources_include_mit_harvard_hopkins_and_ucd_with_connector_keys():
    module = text(MODULE)
    assert "'id' => 'mit'" in module and "'connector' => 'mit-dspace'" in module
    assert "'id' => 'harvard'" in module and "'connector' => 'harvard-dataverse'" in module
    assert "'id' => 'johns-hopkins-dataverse'" in module
    assert "'id' => 'ucd'" in module and "'connector' => 'ucd-research-repository'" in module
    assert "BOUNDED HARVEST" in module


def test_research_network_console_consumes_canonical_v5100_projection_and_deduplicates_ids():
    network = text(NETWORK)
    assert "SC_Library_Institutional_Research_Network::network_sources()" in network
    assert "$by_id = []" in network
    assert "$by_id[$id] = $row" in network
    assert "public const VERSION = '5.10.0'" in network


def test_browser_uses_wordpress_proxy_and_bounded_limit():
    js = text(JS)
    assert "root.dataset.graphEndpoint" in js
    assert "limit_per_source', '8'" in js
    assert "fetch(url" in js
    assert "Institutional Research Network unavailable" in js
    assert "Rights review required" in js


def test_backend_routes_and_health_capabilities_are_wired():
    main = text(ROOT / "library-backend/app/main.py")
    assert '@app.get("/v1/institutional-research-network")' in main
    assert '@app.get("/v1/institutional-research-network/search")' in main
    assert '@app.get("/v1/institutional-research-network/graph")' in main
    assert '"institutional_research_network_ii": True' in main
    assert '"institutional_graph_fingerprint": True' in main


def test_changed_php_and_js_files_are_valid():
    for path in [MAIN, MODULE, NETWORK]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
