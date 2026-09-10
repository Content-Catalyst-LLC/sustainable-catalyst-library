from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library" / "sustainable-catalyst-library.php"
ENERGY_PHP = ROOT / "sustainable-catalyst-library" / "includes" / "class-sc-library-energy-systems-intelligence.php"
MAIN = ROOT / "library-backend" / "app" / "main.py"
INIT = ROOT / "library-backend" / "app" / "__init__.py"
ENERGY = ROOT / "library-backend" / "app" / "energy_systems.py"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_versions_and_preserved_library_line():
    plugin = read(PLUGIN)
    assert "Version: 5.11.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.11.0'" in plugin
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in plugin
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.1.0'" in plugin
    assert '__version__ = "2.7.0"' in read(INIT)


def test_wordpress_energy_class_is_wired_and_read_only_surface_is_present():
    plugin = read(PLUGIN)
    php = read(ENERGY_PHP)
    assert "class-sc-library-energy-systems-intelligence.php" in plugin
    assert "new SC_Library_Energy_Systems_Intelligence()" in plugin
    assert "$energy_systems_intelligence->register_hooks();" in plugin
    assert "sc_energy_systems_intelligence" in php
    assert "Knowledge foundation ≠ energy calculator or technology ranking." in php
    for path in (
        "/energy-systems",
        "/energy-systems/concepts",
        "/energy-systems/relationships",
        "/energy-systems/sources",
        "/energy-systems/knowledge-map",
        "/energy-systems/handoffs",
    ):
        assert path in php


def test_backend_routes_and_health_capabilities_are_wired():
    main = read(MAIN)
    assert "EnergySystemsKnowledgeFoundation" in main
    for path in (
        '/v1/energy-systems")',
        '/v1/energy-systems/concepts")',
        '/v1/energy-systems/relationships")',
        '/v1/energy-systems/sources")',
        '/v1/energy-systems/knowledge-map")',
        '/v1/energy-systems/handoffs")',
    ):
        assert path in main
    assert '"energy_systems_intelligence": True' in main
    assert '"energy_systems_domain_version": "0.1.0"' in main
    assert '"energy_numeric_conversion_registry": False' in main
    assert '"energy_indicator_engine": False' in main
    assert '"energy_scenario_modeling": False' in main


def test_energy_module_contains_source_vintage_and_no_active_factor_table():
    energy = read(ENERGY)
    assert '"vera-langlois-2007"' in energy
    assert '"carbon-trust-conversion-2020"' in energy
    assert '"undesa-scp-2010"' in energy
    assert '"inactive-historical-numeric-source"' in energy
    # No fuel-factor numeric table belongs in the v0.1.0 knowledge foundation.
    for historical_factor in ("0.23314", "0.18387", "0.32040"):
        assert historical_factor not in energy


def test_frontend_assets_exist_and_are_versioned():
    php = read(ENERGY_PHP)
    css = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-energy-systems-v010.css"
    js = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v010.js"
    assert css.is_file() and js.is_file()
    assert "sc-library-energy-systems-v010" in php
    assert "Energy Systems Intelligence v0.1.0" in php


def test_no_write_routes_in_energy_php_or_backend_domain_module():
    php = read(ENERGY_PHP)
    energy = read(ENERGY)
    assert "WP_REST_Server::CREATABLE" not in php
    assert "WP_REST_Server::EDITABLE" not in php
    assert "wp_remote_post" not in php
    assert not re.search(r"@app\.(post|put|patch|delete)", energy)
