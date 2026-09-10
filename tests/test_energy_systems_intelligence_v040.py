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
TECH = ROOT / "library-backend" / "app" / "energy_technologies.py"
JS = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v040.js"
CSS = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-energy-systems-v040.css"
TECH_SCHEMA = ROOT / "docs" / "schemas" / "energy-renewable-technology-definition.json"
RESOURCE_SCHEMA = ROOT / "docs" / "schemas" / "energy-renewable-resource-class.json"
ASSESS_SCHEMA = ROOT / "docs" / "schemas" / "energy-renewable-technology-assessment.json"
OBS_SCHEMA = ROOT / "docs" / "schemas" / "energy-renewable-resource-observation.json"
EXPORT = ROOT / "data" / "energy-systems" / "renewable-technologies-v0.4.0.json"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_versions_and_preserved_release_lines():
    plugin = read(PLUGIN)
    assert "Version: 5.11.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.11.0'" in plugin
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in plugin
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.4.0'" in plugin
    assert '__version__ = "2.10.0"' in read(INIT)


def test_backend_routes_and_capability_boundaries_are_wired():
    main = read(MAIN)
    for path in (
        '/v1/energy-systems/technology-framework', '/v1/energy-systems/technologies',
        '/v1/energy-systems/technologies/{technology_key}', '/v1/energy-systems/resource-classes',
        '/v1/energy-systems/resource-classes/{resource_key}',
        '/v1/energy-systems/technology-assessment-template/{technology_key}',
        '/v1/energy-systems/resource-observation-template/{resource_key}',
        '/v1/energy-systems/technology-comparison-template',
    ):
        assert path in main
    assert '"energy_systems_domain_version": "0.4.0"' in main
    assert '"energy_renewable_technology_registry": True' in main
    assert '"energy_renewable_resource_class_registry": True' in main
    assert '"energy_quantitative_technology_profiles_loaded": False' in main
    assert '"energy_live_resource_potential_datasets_loaded": False' in main
    assert '"energy_renewable_suitability_assessment": False' in main
    assert '"automatic_energy_technology_ranking": False' in main


def test_wordpress_surface_is_read_only_and_exposes_technology_mode():
    php = read(ENERGY_PHP)
    assert "public const VERSION = '0.4.0'" in php
    assert "Technologies &amp; Resources" in php
    assert "Technology class ≠ site suitability" in php
    for path in ('/energy-systems/technology-framework', '/energy-systems/technologies', '/energy-systems/resource-classes', '/energy-systems/technology-assessment-template', '/energy-systems/resource-observation-template'):
        assert path in php
    assert "WP_REST_Server::CREATABLE" not in php
    assert "WP_REST_Server::EDITABLE" not in php
    assert "wp_remote_post" not in php


def test_frontend_assets_render_technology_and_resource_contracts():
    php, js, css = read(ENERGY_PHP), read(JS), read(CSS)
    assert "sc-library-energy-systems-v040" in php
    assert JS.is_file() and CSS.is_file()
    for token in ("data-es-technology-form", "data-es-technology-results", "data-es-resource-results", "data-es-technology-detail"):
        assert token in php
    for token in ("View assessment contract", "View observation contract", "ranking_enabled", "resource_class"):
        assert token in js
    assert ".sc-es__technology-card" in css
    assert ".sc-es__resource-card" in css
    assert ".sc-es__technology-flow" in css


def test_machine_readable_export_and_schemas_are_valid():
    from jsonschema import Draft202012Validator
    schemas = [json.loads(read(x)) for x in (TECH_SCHEMA, RESOURCE_SCHEMA, ASSESS_SCHEMA, OBS_SCHEMA)]
    for schema in schemas:
        Draft202012Validator.check_schema(schema)
    export = json.loads(read(EXPORT))
    assert export["schema"] == "sc-energy-renewable-technology-resource-export/1.0"
    assert export["version"] == "0.4.0"
    assert len(export["technologies"]) == 7
    assert len(export["resource_classes"]) == 6
    tech_validator = Draft202012Validator(schemas[0])
    resource_validator = Draft202012Validator(schemas[1])
    for row in export["technologies"]:
        tech_validator.validate(row)
    for row in export["resource_classes"]:
        resource_validator.validate(row)
    assert export["comparison_template"]["ranking_enabled"] is False


def test_changed_php_js_and_python_are_syntax_valid():
    for path in [PLUGIN, ENERGY_PHP]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["python3", "-m", "py_compile", str(ENERGY), str(TECH), str(MAIN)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr


def test_energy_modules_contain_no_write_route_decorators():
    text = read(ENERGY) + read(TECH)
    assert not re.search(r"@app\.(post|put|patch|delete)", text)


def test_prior_assets_and_data_are_still_present():
    assert (ROOT / "data" / "energy-systems" / "eisd-indicators-v0.3.0.json").is_file()
    assert (ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v030.js").is_file()
    assert (ROOT / "tests" / "test_carbon_nature_intelligence_v050.py").is_file()
