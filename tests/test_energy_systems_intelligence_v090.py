from pathlib import Path
import json, re, subprocess, sys
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library/sustainable-catalyst-library.php'
PHP = ROOT / 'sustainable-catalyst-library/includes/class-sc-library-energy-systems-intelligence.php'
MAIN = ROOT / 'library-backend/app/main.py'
INIT = ROOT / 'library-backend/app/__init__.py'
ENERGY = ROOT / 'library-backend/app/energy_systems.py'
GLOBAL = ROOT / 'library-backend/app/energy_global.py'
DECISION = ROOT / 'library-backend/app/energy_decision.py'
BIO = ROOT / 'library-backend/app/energy_bioenergy.py'
ECON = ROOT / 'library-backend/app/energy_economics.py'
BAL = ROOT / 'library-backend/app/energy_balances.py'
JS = ROOT / 'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v090.js'
CSS = ROOT / 'sustainable-catalyst-library/assets/css/sc-library-energy-systems-v090.css'
EXPORT = ROOT / 'data/energy-systems/energy-decision-intelligence-v0.9.0.json'
SCHEMAS = [ROOT / 'docs/schemas' / n for n in [
    'energy-decision-criterion.json',
    'energy-decision-packet.json',
    'energy-decision-comparison-matrix.json',
    'energy-decision-readiness.json',
]]


def read(p):
    return p.read_text(encoding='utf-8')


def test_versions():
    p = read(PLUGIN)
    assert 'Version: 5.11.0' in p
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in p
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.9.0'" in p
    assert '__version__ = "2.15.0"' in read(INIT)


def test_routes_and_health_capabilities():
    m = read(MAIN)
    for path in [
        '/v1/energy-systems/decision-framework',
        '/v1/energy-systems/decision-criteria',
        '/v1/energy-systems/decision-packet-template',
        '/v1/energy-systems/decision-comparison-matrix',
        '/v1/energy-systems/decision-readiness',
        '/v1/energy-systems/global-energy-country-profile',
    ]:
        assert path in m
    assert '"energy_systems_domain_version": "0.9.0"' in m
    assert '"energy_decision_intelligence": True' in m
    assert '"energy_decision_composite_score": False' in m
    assert '"energy_decision_automatic_ranking": False' in m
    assert '"energy_global_energy_live_world_bank": True' in m


def test_wp_surface_is_read_only_and_decision_first():
    p = read(PHP)
    assert "public const VERSION = '0.9.0'" in p
    assert 'Decision Intelligence' in p
    assert 'Comparison matrix ≠ decision, score, ranking, winner, or recommendation.' in p
    assert 'data-es-panel="decision"' in p
    assert 'data-es-mode="decision"' in p
    assert 'data-es-decision-packet' in p
    assert 'data-es-build-decision-matrix' in p
    assert 'WP_REST_Server::CREATABLE' not in p
    assert 'wp_remote_post' not in p


def test_assets_include_decision_intelligence_contracts():
    js, css = read(JS), read(CSS)
    for token in ['decisionFrameworkEndpoint','decisionCriteriaEndpoint','decisionPacketTemplateEndpoint','decisionComparisonMatrixEndpoint','decisionReadinessEndpoint']:
        assert token in js
    assert 'No normalization, weighting, composite score, ranking or winner selection is performed.' in js
    assert '.sc-es__decision-layout' in css
    assert '.sc-es__decision-table' in css
    assert '.sc-es__decision-flag' in css
    assert 'globalEnergyCountryProfileEndpoint' in js


def test_decision_schemas_validate_and_export_is_non_ranking():
    schemas = [json.loads(read(p)) for p in SCHEMAS]
    for schema in schemas:
        Draft202012Validator.check_schema(schema)
    e = json.loads(read(EXPORT))
    assert e['schema'] == 'sc-energy-decision-intelligence-export/1.0'
    assert e['version'] == '0.9.0'
    assert e['framework']['counts']['criteria'] == 12
    assert e['framework']['counts']['dimensions'] == 9
    assert e['guardrails']['automatic_alternative_ranking'] is False
    assert e['guardrails']['automatic_weight_assignment'] is False
    criterion_validator = Draft202012Validator(schemas[0])
    for criterion in e['criteria']:
        criterion_validator.validate(criterion)
    Draft202012Validator(schemas[1]).validate(e['decision_packet_template'])


def test_decision_export_criteria_reference_prior_layers():
    e = json.loads(read(EXPORT))
    rows = {x['key']:x for x in e['criteria']}
    assert 'energy-npv-result' in rows['net-present-value']['evidence_refs']
    assert 'net-energy-import-dependency' in rows['energy-security-import-dependency']['evidence_refs']
    assert 'whole-system-ghg-accounting' in rows['ghg-emissions']['evidence_refs']
    assert rows['implementation-governance']['comparison_semantics'] == 'context-only'


def test_v080_global_registry_is_preserved():
    g = json.loads(read(ROOT / 'data/energy-systems/global-energy-intelligence-v0.8.0.json'))
    sources = {x['key']:x for x in g['sources']}
    assert g['version'] == '0.8.0'
    assert g['framework']['counts']['metrics'] == 9
    assert sources['world-bank-wdi']['status'] == 'active'
    assert g['embedded_current_country_values'] == []


def test_syntax():
    for p in [PLUGIN, PHP]:
        r = subprocess.run(['php','-l',str(p)], capture_output=True, text=True)
        assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['node','--check',str(JS)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['python3','-m','py_compile',str(ENERGY),str(GLOBAL),str(DECISION),str(BIO),str(ECON),str(BAL),str(MAIN)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr


def test_energy_domain_modules_add_no_write_routes():
    text = read(ENERGY) + read(GLOBAL) + read(BIO) + read(ECON) + read(BAL)
    assert not re.search(r'@app\.(post|put|patch|delete)', text)


def test_prior_assets_data_and_carbon_nature_are_preserved():
    assert (ROOT/'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v080.js').is_file()
    assert (ROOT/'data/energy-systems/global-energy-intelligence-v0.8.0.json').is_file()
    assert (ROOT/'data/energy-systems/biological-carbon-bioenergy-v0.7.0.json').is_file()
    assert (ROOT/'data/energy-systems/energy-scenario-economics-v0.6.0.json').is_file()
    assert (ROOT/'data/energy-systems/energy-balance-model-v0.5.0.json').is_file()
    assert (ROOT/'data/energy-systems/renewable-technologies-v0.4.0.json').is_file()
    assert (ROOT/'data/energy-systems/eisd-indicators-v0.3.0.json').is_file()
    sys.path.insert(0, str(ROOT / 'library-backend'))
    from app.carbon_nature import CarbonNatureKnowledgeFoundation
    d = CarbonNatureKnowledgeFoundation().manifest()
    assert d['subsystem']['version'] == '0.5.0'
    assert d['governance']['carbon_credit_issuance'] is False
