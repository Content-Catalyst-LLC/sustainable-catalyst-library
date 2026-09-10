from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library" / "sustainable-catalyst-library.php"
ENERGY_PHP = ROOT / "sustainable-catalyst-library" / "includes" / "class-sc-library-energy-systems-intelligence.php"
MAIN = ROOT / "library-backend" / "app" / "main.py"
INIT = ROOT / "library-backend" / "app" / "__init__.py"
ENERGY = ROOT / "library-backend" / "app" / "energy_systems.py"
JS = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v020.js"
CSS = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-energy-systems-v020.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_versions_and_preserved_release_lines():
    plugin = read(PLUGIN)
    assert "Version: 5.11.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.11.0'" in plugin
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in plugin
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.2.0'" in plugin
    assert '__version__ = "2.8.0"' in read(INIT)


def test_backend_routes_and_capabilities_are_wired():
    main = read(MAIN)
    for path in (
        '/v1/energy-systems/registry', '/v1/energy-systems/units', '/v1/energy-systems/conversion-factors',
        '/v1/energy-systems/carbon-factors', '/v1/energy-systems/heat-content-factors',
        '/v1/energy-systems/methodology-rules', '/v1/energy-systems/convert',
        '/v1/energy-systems/carbon-estimate', '/v1/energy-systems/heat-content-estimate',
    ):
        assert path in main
    assert '"energy_systems_domain_version": "0.2.0"' in main
    assert '"energy_numeric_conversion_registry": True' in main
    assert '"energy_carbon_factor_registry": True' in main
    assert '"energy_heat_content_registry": True' in main
    assert '"energy_current_factor_defaults": False' in main
    assert '"energy_workbench_execution": False' in main


def test_numeric_tables_are_source_bound_not_current_defaults():
    energy = read(ENERGY)
    for value in ("0.23314", "0.18387", "0.32040", "29.307", "0.0002931", "11630"):
        assert value in energy
    assert '"active-historical-reference-only"' in energy
    assert '"current_factor_defaults_activated": False' in energy
    assert '"historical_calculation_is_not_current_inventory": True' in energy
    assert "universal renewable-electricity factor" in energy


def test_wordpress_numeric_registry_surface_and_get_only_routes_are_present():
    php = read(ENERGY_PHP)
    assert "public const VERSION = '0.2.0'" in php
    assert "Numeric Registry" in php
    assert "Source-bound calculation ≠ current emissions inventory." in php
    for path in (
        '/energy-systems/registry', '/energy-systems/units', '/energy-systems/conversion-factors',
        '/energy-systems/carbon-factors', '/energy-systems/heat-content-factors',
        '/energy-systems/methodology-rules', '/energy-systems/convert',
        '/energy-systems/carbon-estimate', '/energy-systems/heat-content-estimate',
    ):
        assert path in php
    assert "WP_REST_Server::CREATABLE" not in php
    assert "WP_REST_Server::EDITABLE" not in php
    assert "wp_remote_post" not in php


def test_frontend_assets_are_versioned_and_expose_three_source_bound_calculators():
    php, js, css = read(ENERGY_PHP), read(JS), read(CSS)
    assert "sc-library-energy-systems-v020" in php
    assert JS.is_file() and CSS.is_file()
    for token in ("data-es-convert-form", "data-es-carbon-form", "data-es-heat-form"):
        assert token in php
    for token in ("kgCO₂e", "kWh gross", "source "):
        assert token in js
    assert ".sc-es__calc-grid" in css
    assert ".sc-es__registry-warning" in css


def test_changed_php_js_and_python_are_syntax_valid():
    for path in [PLUGIN, ENERGY_PHP]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["python3", "-m", "py_compile", str(ENERGY), str(MAIN)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr


def test_energy_domain_module_contains_no_write_route_decorators():
    energy = read(ENERGY)
    assert not re.search(r"@app\.(post|put|patch|delete)", energy)
