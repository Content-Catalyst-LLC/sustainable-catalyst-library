from pathlib import Path
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library" / "sustainable-catalyst-library.php"
ENERGY_PHP = ROOT / "sustainable-catalyst-library" / "includes" / "class-sc-library-energy-systems-intelligence.php"
MAIN = ROOT / "library-backend" / "app" / "main.py"
INIT = ROOT / "library-backend" / "app" / "__init__.py"
ENERGY = ROOT / "library-backend" / "app" / "energy_systems.py"
JS = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v030.js"
CSS = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-energy-systems-v030.css"
DEF_SCHEMA = ROOT / "docs" / "schemas" / "energy-sustainability-indicator-definition.json"
OBS_SCHEMA = ROOT / "docs" / "schemas" / "energy-sustainability-indicator-observation.json"
EXPORT = ROOT / "data" / "energy-systems" / "eisd-indicators-v0.3.0.json"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_versions_and_preserved_release_lines():
    plugin = read(PLUGIN)
    assert "Version: 5.11.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.11.0'" in plugin
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in plugin
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.3.0'" in plugin
    assert '__version__ = "2.9.0"' in read(INIT)


def test_backend_indicator_routes_and_capability_boundaries_are_wired():
    main = read(MAIN)
    for path in (
        '/v1/energy-systems/indicator-framework', '/v1/energy-systems/indicators',
        '/v1/energy-systems/indicators/{indicator_code}', '/v1/energy-systems/indicator-observation-template/{indicator_code}',
    ):
        assert path in main
    assert '"energy_systems_domain_version": "0.3.0"' in main
    assert '"energy_indicator_framework": True' in main
    assert '"energy_indicator_definition_registry": True' in main
    assert '"energy_indicator_observation_contracts": True' in main
    assert '"energy_indicator_official_methodology_loaded": False' in main
    assert '"energy_indicator_calculation": False' in main
    assert '"energy_scenario_modeling": False' in main


def test_wordpress_indicator_surface_and_get_only_routes_are_present():
    php = read(ENERGY_PHP)
    assert "public const VERSION = '0.3.0'" in php
    assert "Sustainability Indicators" in php
    assert "Indicator definition ≠ observed value or sustainability score." in php
    for path in ('/energy-systems/indicator-framework', '/energy-systems/indicators', '/energy-systems/indicator-observation-template'):
        assert path in php
    assert "WP_REST_Server::CREATABLE" not in php
    assert "WP_REST_Server::EDITABLE" not in php
    assert "wp_remote_post" not in php


def test_frontend_assets_are_versioned_and_render_indicator_contracts():
    php, js, css = read(ENERGY_PHP), read(JS), read(CSS)
    assert "sc-library-energy-systems-v030" in php
    assert JS.is_file() and CSS.is_file()
    for token in ("data-es-indicator-form", "data-es-indicator-framework", "data-es-indicator-results", "data-es-indicator-detail"):
        assert token in php
    for token in ("View observation contract", "methodology", "comparison requirements"):
        assert token.lower() in js.lower()
    assert ".sc-es__framework" in css
    assert ".sc-es__indicator-card" in css
    assert ".sc-es__indicator-detail" in css


def test_machine_readable_indicator_export_and_schemas_are_valid_json():
    from jsonschema import Draft202012Validator
    defs = json.loads(read(DEF_SCHEMA))
    obs = json.loads(read(OBS_SCHEMA))
    export = json.loads(read(EXPORT))
    assert defs["$schema"].endswith("2020-12/schema")
    assert obs["$schema"].endswith("2020-12/schema")
    Draft202012Validator.check_schema(defs)
    Draft202012Validator.check_schema(obs)
    validator = Draft202012Validator(defs)
    assert export["schema"] == "sc-energy-indicator-registry-export/1.0"
    assert export["version"] == "0.3.0"
    assert len(export["indicators"]) == 30
    assert export["methodology_status"] == "methodology-sheets-not-supplied"
    for indicator in export["indicators"]:
        validator.validate(indicator)


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
