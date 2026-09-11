from pathlib import Path
import json, re, subprocess, sys
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library/sustainable-catalyst-library.php'
PHP = ROOT / 'sustainable-catalyst-library/includes/class-sc-library-energy-systems-intelligence.php'
MAIN = ROOT / 'library-backend/app/main.py'
INIT = ROOT / 'library-backend/app/__init__.py'
ENERGY = ROOT / 'library-backend/app/energy_systems.py'
BIO = ROOT / 'library-backend/app/energy_bioenergy.py'
ECON = ROOT / 'library-backend/app/energy_economics.py'
BAL = ROOT / 'library-backend/app/energy_balances.py'
JS = ROOT / 'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v070.js'
CSS = ROOT / 'sustainable-catalyst-library/assets/css/sc-library-energy-systems-v070.css'
EXPORT = ROOT / 'data/energy-systems/biological-carbon-bioenergy-v0.7.0.json'
SCHEMAS = [ROOT / 'docs/schemas' / n for n in [
    'energy-feedstock-energy-result.json',
    'energy-anaerobic-digestion-result.json',
    'energy-biochar-carbon-result.json',
    'energy-biomass-to-oil-result.json',
    'energy-bioenergy-carbon-scenario.json',
]]

def read(p):
    return p.read_text(encoding='utf-8')


def test_versions():
    p = read(PLUGIN)
    assert 'Version: 5.11.0' in p
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in p
    assert "SC_ENERGY_SYSTEMS_VERSION', '0.7.0'" in p
    assert '__version__ = "2.13.0"' in read(INIT)


def test_routes_and_health_capabilities():
    m = read(MAIN)
    for path in [
        '/v1/energy-systems/bioenergy-framework', '/v1/energy-systems/bioenergy-feedstocks',
        '/v1/energy-systems/bioenergy-pathways', '/v1/energy-systems/biological-carbon-bridges',
        '/v1/energy-systems/feedstock-energy-estimate', '/v1/energy-systems/anaerobic-digestion-energy-estimate',
        '/v1/energy-systems/biochar-carbon-estimate', '/v1/energy-systems/biomass-to-oil-energy-estimate',
        '/v1/energy-systems/bioenergy-scenario-template'
    ]:
        assert path in m
    assert '"energy_systems_domain_version": "0.7.0"' in m
    assert '"energy_biological_carbon_bioenergy_integration": True' in m
    assert '"energy_biomass_carbon_neutrality_assumed": False' in m
    assert '"energy_carbon_credit_eligibility_determined": False' in m


def test_wp_surface_is_read_only_and_bioenergy_first():
    p = read(PHP)
    assert "public const VERSION = '0.7.0'" in p
    assert 'Bioenergy &amp; Carbon' in p
    assert 'Bioenergy output ≠ carbon neutrality' in p
    assert 'data-es-panel="bioenergy"' in p
    assert 'data-es-feedstock-energy-form' in p
    assert 'data-es-ad-energy-form' in p
    assert 'data-es-biochar-form' in p
    assert 'data-es-biomass-oil-form' in p
    assert 'WP_REST_Server::CREATABLE' not in p
    assert 'wp_remote_post' not in p


def test_assets_include_bioenergy_contracts():
    js, css = read(JS), read(CSS)
    for token in ['bioenergyFrameworkEndpoint','bioenergyPathwaysEndpoint','bioenergyFeedstocksEndpoint','biologicalCarbonBridgesEndpoint','feedstockEnergyEndpoint','anaerobicDigestionEnergyEndpoint','biocharCarbonEndpoint','biomassToOilEnergyEndpoint','bioenergyScenarioEndpoint']:
        assert token in js
    assert '.sc-es__calc-grid--bioenergy' in css
    assert '.sc-es__bridge-card' in css


def test_schemas_and_export_validate():
    schemas = [json.loads(read(p)) for p in SCHEMAS]
    for schema in schemas:
        Draft202012Validator.check_schema(schema)
    e = json.loads(read(EXPORT))
    assert e['schema'] == 'sc-energy-biological-carbon-bioenergy-export/1.0'
    assert e['version'] == '0.7.0'
    assert e['framework']['counts'] == {'feedstock_classes':5,'pathways':6,'carbon_nature_bridges':6,'executable_models':4,'scenario_contracts':1}
    Draft202012Validator(schemas[-1]).validate(e['scenario_template']['template'])


def test_examples_validate_against_result_schemas():
    sys.path.insert(0, str(ROOT / 'library-backend'))
    from app.energy_systems import EnergySystemsKnowledgeFoundation
    e = EnergySystemsKnowledgeFoundation()
    results = [
        e.feedstock_energy_estimate(mass_tonnes='10',energy_content_kwh_per_tonne='4000',conversion_efficiency_pct='80'),
        e.anaerobic_digestion_energy_estimate(feedstock_mass_tonnes='10',biogas_yield_m3_per_tonne='100',methane_fraction_pct='60',methane_energy_kwh_per_m3='10',conversion_efficiency_pct='40'),
        e.biochar_carbon_estimate(biochar_mass_kg='1000',carbon_fraction_pct='70',stable_fraction_pct='80'),
        e.biomass_to_oil_energy_estimate(feedstock_mass_tonnes='10',oil_yield_mass_pct='30',oil_energy_content_kwh_per_tonne='9000',downstream_conversion_efficiency_pct='90'),
    ]
    for result, schema in zip(results, SCHEMAS[:4]):
        Draft202012Validator(json.loads(read(schema))).validate(result)


def test_syntax():
    for p in [PLUGIN, PHP]:
        r = subprocess.run(['php','-l',str(p)], capture_output=True, text=True)
        assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['node','--check',str(JS)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr
    r = subprocess.run(['python3','-m','py_compile',str(ENERGY),str(BIO),str(ECON),str(BAL),str(MAIN)], capture_output=True, text=True)
    assert r.returncode == 0, r.stdout + r.stderr


def test_energy_domain_modules_add_no_write_routes():
    text = read(ENERGY) + read(BIO) + read(ECON) + read(BAL)
    assert not re.search(r'@app\.(post|put|patch|delete)', text)


def test_prior_assets_and_data_preserved():
    assert (ROOT/'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v060.js').is_file()
    assert (ROOT/'data/energy-systems/energy-scenario-economics-v0.6.0.json').is_file()
    assert (ROOT/'data/energy-systems/energy-balance-model-v0.5.0.json').is_file()
    assert (ROOT/'data/energy-systems/renewable-technologies-v0.4.0.json').is_file()
    assert (ROOT/'data/energy-systems/eisd-indicators-v0.3.0.json').is_file()


def test_carbon_nature_release_identity_preserved():
    sys.path.insert(0, str(ROOT / 'library-backend'))
    from app.carbon_nature import CarbonNatureKnowledgeFoundation
    d = CarbonNatureKnowledgeFoundation().manifest()
    assert d['subsystem']['version'] == '0.5.0'
    assert d['governance']['carbon_credit_issuance'] is False
