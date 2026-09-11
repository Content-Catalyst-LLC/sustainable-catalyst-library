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
BALANCE = ROOT / "library-backend" / "app" / "energy_balances.py"
TECH = ROOT / "library-backend" / "app" / "energy_technologies.py"
JS = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v050.js"
CSS = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-energy-systems-v050.css"
EXPORT = ROOT / "data" / "energy-systems" / "energy-balance-model-v0.5.0.json"
SCHEMAS = [
    ROOT / "docs" / "schemas" / "energy-conversion-chain-result.json",
    ROOT / "docs" / "schemas" / "energy-supply-demand-balance-result.json",
    ROOT / "docs" / "schemas" / "energy-generation-estimate-result.json",
    ROOT / "docs" / "schemas" / "energy-balance-scenario.json",
]


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_versions_and_preserved_release_lines():
    plugin = read(PLUGIN)
    assert "Version: 5.11.0" in plugin
    assert "SC_LIBRARY_VERSION', '5.11.0'" in plugin
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in plugin
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.5.0'" in plugin
    assert '__version__ = "2.11.0"' in read(INIT)


def test_backend_routes_and_capability_boundaries_are_wired():
    main = read(MAIN)
    for path in (
        '/v1/energy-systems/balance-framework',
        '/v1/energy-systems/conversion-chain',
        '/v1/energy-systems/supply-demand-balance',
        '/v1/energy-systems/generation-estimate',
        '/v1/energy-systems/balance-scenario-template',
    ):
        assert path in main
    assert '"energy_systems_domain_version": "0.5.0"' in main
    assert '"energy_scenario_modeling": True' in main
    assert '"energy_balance_framework": True' in main
    assert '"energy_time_series_dispatch_simulation": False' in main
    assert '"energy_grid_reliability_or_adequacy_model": False' in main
    assert '"energy_economic_optimization": False' in main
    assert '"automatic_energy_policy_recommendation": False' in main


def test_wordpress_surface_is_read_only_and_exposes_energy_balance_mode():
    php = read(ENERGY_PHP)
    assert "public const VERSION = '0.5.0'" in php
    assert "Energy Balance" in php
    assert "Scenario arithmetic ≠ forecast" in php
    for path in ('/energy-systems/balance-framework', '/energy-systems/conversion-chain', '/energy-systems/supply-demand-balance', '/energy-systems/generation-estimate', '/energy-systems/balance-scenario-template'):
        assert path in php
    assert "WP_REST_Server::CREATABLE" not in php
    assert "WP_REST_Server::EDITABLE" not in php
    assert "wp_remote_post" not in php


def test_frontend_assets_render_balance_models_and_keep_prior_modes():
    php, js, css = read(ENERGY_PHP), read(JS), read(CSS)
    assert "sc-library-energy-systems-v050" in php
    for token in ("data-es-chain-form", "data-es-balance-form", "data-es-generation-form", "data-es-scenario-contract"):
        assert token in php
    for token in ("efficiencies", "residual_kwh", "generation_kwh", "time_series_dispatch_simulation"):
        assert token in js
    assert ".sc-es__result-metrics" in css
    assert ".sc-es__balance-state" in css
    assert "Technologies &amp; Resources" in php and "Sustainability Indicators" in php and "Numeric Registry" in php


def test_machine_readable_export_and_new_schemas_are_valid():
    from jsonschema import Draft202012Validator
    schema_docs = [json.loads(read(path)) for path in SCHEMAS]
    for schema in schema_docs:
        Draft202012Validator.check_schema(schema)
    export = json.loads(read(EXPORT))
    assert export["schema"] == "sc-energy-balance-model-export/1.0"
    assert export["version"] == "0.5.0"
    assert export["framework"]["counts"]["models"] == 4
    assert export["framework"]["counts"]["executable_models"] == 3
    scenario = export["scenario_template"]["template"]
    Draft202012Validator(schema_docs[3]).validate(scenario)


def test_example_results_validate_against_schemas():
    import sys
    sys.path.insert(0, str(ROOT / "library-backend"))
    from app.energy_systems import EnergySystemsKnowledgeFoundation
    from jsonschema import Draft202012Validator
    e = EnergySystemsKnowledgeFoundation()
    results = [
        e.conversion_chain(input_kwh="1000", efficiencies="90,95,97"),
        e.supply_demand_balance(domestic_supply_kwh="1000", final_demand_kwh="900", losses_kwh="100"),
        e.generation_estimate(capacity_kw="1000", capacity_factor_pct="35", hours="8760"),
    ]
    for result, schema_path in zip(results, SCHEMAS[:3]):
        Draft202012Validator(json.loads(read(schema_path))).validate(result)


def test_changed_php_js_and_python_are_syntax_valid():
    for path in [PLUGIN, ENERGY_PHP]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["node", "--check", str(JS)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(["python3", "-m", "py_compile", str(ENERGY), str(BALANCE), str(TECH), str(MAIN)], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr


def test_energy_modules_contain_no_write_route_decorators():
    text = read(ENERGY) + read(BALANCE) + read(TECH)
    assert not re.search(r"@app\.(post|put|patch|delete)", text)


def test_prior_energy_and_carbon_nature_assets_are_preserved():
    assert (ROOT / "data" / "energy-systems" / "renewable-technologies-v0.4.0.json").is_file()
    assert (ROOT / "data" / "energy-systems" / "eisd-indicators-v0.3.0.json").is_file()
    assert (ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-energy-systems-v040.js").is_file()
    assert (ROOT / "tests" / "test_carbon_nature_intelligence_v050.py").is_file()
