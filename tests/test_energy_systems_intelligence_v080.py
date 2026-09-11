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
BIO = ROOT / 'library-backend/app/energy_bioenergy.py'
ECON = ROOT / 'library-backend/app/energy_economics.py'
BAL = ROOT / 'library-backend/app/energy_balances.py'
JS = ROOT / 'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v080.js'
CSS = ROOT / 'sustainable-catalyst-library/assets/css/sc-library-energy-systems-v080.css'
EXPORT = ROOT / 'data/energy-systems/global-energy-intelligence-v0.8.0.json'
SCHEMAS = [ROOT / 'docs/schemas' / n for n in [
    'energy-global-metric-definition.json',
    'energy-global-observation.json',
    'energy-global-country-profile.json',
]]


def read(p):
    return p.read_text(encoding='utf-8')


def test_versions():
    p = read(PLUGIN)
    assert 'Version: 5.11.0' in p
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in p
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.8.0'" in p
    assert '__version__ = "2.14.0"' in read(INIT)


def test_routes_and_health_capabilities():
    m = read(MAIN)
    for path in [
        '/v1/energy-systems/global-energy-framework',
        '/v1/energy-systems/global-energy-sources',
        '/v1/energy-systems/global-energy-metrics',
        '/v1/energy-systems/global-energy-profile-template',
        '/v1/energy-systems/global-energy-country-profile',
        '/v1/energy-systems/global-energy-compare',
    ]:
        assert path in m
    assert '"energy_systems_domain_version": "0.8.0"' in m
    assert '"energy_global_energy_live_world_bank": True' in m
    assert '"energy_global_energy_embedded_current_values": False' in m
    assert '"energy_global_energy_cross_source_harmonization_assumed": False' in m


def test_wp_surface_is_read_only_and_global_first():
    p = read(PHP)
    assert "public const VERSION = '0.8.0'" in p
    assert 'Global Intelligence' in p
    assert 'Latest available observation ≠ current-year fact' in p
    assert 'data-es-panel="global"' in p
    assert 'data-es-global-profile-form' in p
    assert 'data-es-global-compare-form' in p
    assert 'WP_REST_Server::CREATABLE' not in p
    assert 'wp_remote_post' not in p


def test_assets_include_global_intelligence_contracts():
    js, css = read(JS), read(CSS)
    for token in ['globalEnergyFrameworkEndpoint','globalEnergySourcesEndpoint','globalEnergyMetricsEndpoint','globalEnergyCountryProfileEndpoint','globalEnergyCompareEndpoint']:
        assert token in js
    assert 'latest_observation_lag_years' in js
    assert '.sc-es__global-grid' in css
    assert '.sc-es__sparkline' in css
    assert '.sc-es__global-table' in css


def test_schemas_validate_and_export_contains_no_current_values():
    schemas = [json.loads(read(p)) for p in SCHEMAS]
    for schema in schemas:
        Draft202012Validator.check_schema(schema)
    e = json.loads(read(EXPORT))
    assert e['schema'] == 'sc-energy-global-intelligence-export/1.0'
    assert e['version'] == '0.8.0'
    assert e['framework']['counts']['metrics'] == 9
    assert e['framework']['counts']['live_connectors'] == 1
    assert e['embedded_current_country_values'] == []
    metric_validator = Draft202012Validator(schemas[0])
    for metric in e['metrics']:
        metric_validator.validate(metric)


def test_global_module_has_one_live_no_auth_source_and_keyed_connectors_gated():
    e = json.loads(read(EXPORT))
    sources = {x['key']:x for x in e['sources']}
    assert sources['world-bank-wdi']['status'] == 'active'
    assert sources['world-bank-wdi']['authentication'] == 'none'
    assert sources['ember-api']['status'] == 'contract-ready-not-activated'
    assert sources['ember-api']['authentication'] == 'api-key-required'
    assert sources['eia-api-v2']['status'] == 'contract-ready-not-activated'


def test_global_metric_codes_are_explicit_and_source_bound():
    e = json.loads(read(EXPORT))
    rows = {x['key']:x for x in e['metrics']}
    expected = {
        'electricity-access':'EG.ELC.ACCS.ZS',
        'renewable-final-energy-share':'EG.FEC.RNEW.ZS',
        'net-energy-import-dependency':'EG.IMP.CONS.ZS',
        'energy-use-per-capita':'EG.USE.PCAP.KG.OE',
        'fossil-fuel-energy-share':'EG.USE.COMM.FO.ZS',
        'renewable-electricity-share':'EG.ELC.RNEW.ZS',
        'electricity-consumption-per-capita':'EG.USE.ELEC.KH.PC',
        'transmission-distribution-losses':'EG.ELC.LOSS.ZS',
        'energy-productivity':'EG.GDP.PUSE.KO.PP.KD',
    }
    assert {k:v['source_indicator'] for k,v in rows.items()} == expected
    assert all(v['current_state_claim'] is False for v in rows.values())


def test_syntax():
    for p in [PLUGIN, PHP]:
        r = subprocess.run(['php','-l',str(p)], capture_output=True, text=True)
        assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['node','--check',str(JS)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['python3','-m','py_compile',str(ENERGY),str(GLOBAL),str(BIO),str(ECON),str(BAL),str(MAIN)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr


def test_energy_domain_modules_add_no_write_routes():
    text = read(ENERGY) + read(GLOBAL) + read(BIO) + read(ECON) + read(BAL)
    assert not re.search(r'@app\.(post|put|patch|delete)', text)


def test_prior_assets_data_and_carbon_nature_are_preserved():
    assert (ROOT/'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v070.js').is_file()
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
