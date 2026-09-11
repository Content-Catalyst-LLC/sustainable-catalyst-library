from pathlib import Path
import json, re, subprocess, sys
from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library/sustainable-catalyst-library.php'
PHP = ROOT / 'sustainable-catalyst-library/includes/class-sc-library-energy-systems-intelligence.php'
MAIN = ROOT / 'library-backend/app/main.py'
INIT = ROOT / 'library-backend/app/__init__.py'
ENERGY = ROOT / 'library-backend/app/energy_systems.py'
PLATFORM = ROOT / 'library-backend/app/energy_platform.py'
RUNTIME = ROOT / 'library-backend/app/energy_runtime.py'
GLOBAL = ROOT / 'library-backend/app/energy_global.py'
DECISION = ROOT / 'library-backend/app/energy_decision.py'
JS = ROOT / 'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v110.js'
CSS = ROOT / 'sustainable-catalyst-library/assets/css/sc-library-energy-systems-v110.css'
EXPORT = ROOT / 'data/energy-systems/runtime-activation-v1.1.0.json'
SCHEMAS = [ROOT/'docs/schemas'/n for n in ['energy-runtime-target.json','energy-runtime-handoff.json','energy-runtime-readiness.json']]

def read(p): return p.read_text(encoding='utf-8')

def test_versions():
    p=read(PLUGIN)
    assert 'Version: 5.11.0' in p
    assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in p
    assert "SC_ENERGY_SYSTEMS_VERSION', '1.1.0'" in p
    assert '__version__ = "2.17.0"' in read(INIT)
    assert 'DOMAIN_VERSION = "1.1.0"' in read(ENERGY)
    assert 'MODEL_VERSION = "1.0.0"' in read(PLATFORM)
    assert 'MODEL_VERSION = "1.1.0"' in read(RUNTIME)

def test_backend_runtime_routes_and_capabilities():
    m=read(MAIN)
    for path in [
        '/v1/energy-systems/runtime-framework',
        '/v1/energy-systems/runtime-targets',
        '/v1/energy-systems/runtime-targets/{target_key}',
        '/v1/energy-systems/runtime-handoff-template/{target_key}',
        '/v1/energy-systems/runtime-handoff/{target_key}',
        '/v1/energy-systems/runtime-readiness/{target_key}',
        '/v1/energy-systems/platform-certification',
    ]: assert path in m
    assert '"energy_systems_domain_version": "1.1.0"' in m
    assert '"energy_cross_product_runtime_activation_gateway": True' in m
    assert '"energy_cross_product_runtime_targets": 5' in m
    assert '"energy_cross_product_target_consumption_certified": False' in m
    assert '"energy_cross_product_outbound_push_delivery": False' in m

def test_wp_surface_is_runtime_first_and_read_only():
    p=read(PHP)
    assert "public const VERSION = '1.1.0'" in p
    assert 'Runtime Activation Gateway · v1.1.0' in p
    assert 'Gateway activation ≠ target execution.' in p
    assert 'data-es-mode="runtime"' in p
    assert 'data-es-panel="runtime"' in p
    assert 'data-es-runtime-targets' in p
    assert 'data-es-build-runtime-handoff' in p
    assert 'data-es-runtime-readiness' in p
    assert 'WP_REST_Server::CREATABLE' not in p
    assert 'wp_remote_post' not in p

def test_runtime_assets_and_boundary_copy():
    js,css=read(JS),read(CSS)
    for token in ['runtimeFrameworkEndpoint','runtimeTargetsEndpoint','runtimeHandoffEndpoint','runtimeReadinessEndpoint']:
        assert token in js
    assert 'no outbound delivery performed' in js
    assert 'Library can build target-shaped packets now.' in js
    assert '.sc-es__runtime-builder' in css
    assert '.sc-es__runtime-target-card' in css

def test_runtime_export_and_schemas_validate():
    schemas=[json.loads(read(p)) for p in SCHEMAS]
    for schema in schemas: Draft202012Validator.check_schema(schema)
    e=json.loads(read(EXPORT))
    assert e['schema']=='sc-energy-cross-product-runtime-activation-export/1.0'
    assert e['version']=='1.1.0'
    assert e['framework']['counts']['external_runtime_targets']==5
    assert e['framework']['counts']['target_runtimes_certified_active']==0
    target_validator=Draft202012Validator(schemas[0])
    for target in e['targets']: target_validator.validate(target)
    assert set(e['templates'])=={'research-librarian','lab','workbench','site-intelligence','decision-studio'}

def test_v100_platform_export_remains_preserved():
    e=json.loads(read(ROOT/'data/energy-systems/integrated-energy-platform-v1.0.0.json'))
    assert e['version']=='1.0.0'
    assert e['certification']['status']=='pass'
    assert e['framework']['counts']['release_layers']==9

def test_syntax():
    for p in [PLUGIN,PHP]:
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
    r=subprocess.run(['node','--check',str(JS)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
    mods=[ENERGY,PLATFORM,RUNTIME,GLOBAL,DECISION,MAIN]
    r=subprocess.run(['python3','-m','py_compile',*map(str,mods)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr

def test_domain_modules_add_no_write_routes():
    text=read(ENERGY)+read(PLATFORM)+read(RUNTIME)+read(GLOBAL)+read(DECISION)
    assert not re.search(r'@app\.(post|put|patch|delete)',text)

def test_prior_assets_and_data_preserved():
    for version in ['v100','v090','v080','v070','v060','v050','v040','v030','v020','v010']:
        assert (ROOT/f'sustainable-catalyst-library/assets/js/sc-library-energy-systems-{version}.js').is_file()
    for name in ['integrated-energy-platform-v1.0.0.json','energy-decision-intelligence-v0.9.0.json','global-energy-intelligence-v0.8.0.json','biological-carbon-bioenergy-v0.7.0.json','energy-scenario-economics-v0.6.0.json','energy-balance-model-v0.5.0.json','renewable-technologies-v0.4.0.json','eisd-indicators-v0.3.0.json']:
        assert (ROOT/'data/energy-systems'/name).is_file()

def test_runtime_dynamic_packet_and_readiness_validate_against_schemas():
    sys.path.insert(0,str(ROOT/'library-backend'))
    from app.energy_runtime import EnergyCrossProductRuntimeActivation
    gateway=EnergyCrossProductRuntimeActivation()
    study=gateway._blank_study()
    study['identity'].update({'study_id':'schema-test','question':'validate handoff','geography':'example','period':'2030'})
    study['energy_balance']['scenario_refs']=['balance-1']
    study['technologies_and_resources']['technology_refs']=['solar-photovoltaic']
    study['provenance']=[{'source_ref':'source-1'}]
    packet=gateway.build_handoff('lab',study)['packet']
    readiness=gateway.readiness('lab',study)
    Draft202012Validator(json.loads(read(ROOT/'docs/schemas/energy-runtime-handoff.json'))).validate(packet)
    Draft202012Validator(json.loads(read(ROOT/'docs/schemas/energy-runtime-readiness.json'))).validate({k:readiness[k] for k in ['status','populated_sections','empty_sections','issues']})
