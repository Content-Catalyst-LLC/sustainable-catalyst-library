from pathlib import Path
import json,re,subprocess,sys
from jsonschema import Draft202012Validator
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php'; PHP=ROOT/'sustainable-catalyst-library/includes/class-sc-library-energy-systems-intelligence.php'; MAIN=ROOT/'library-backend/app/main.py'; INIT=ROOT/'library-backend/app/__init__.py'; ENERGY=ROOT/'library-backend/app/energy_systems.py'; ECON=ROOT/'library-backend/app/energy_economics.py'; BAL=ROOT/'library-backend/app/energy_balances.py'; JS=ROOT/'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v060.js'; CSS=ROOT/'sustainable-catalyst-library/assets/css/sc-library-energy-systems-v060.css'; EXPORT=ROOT/'data/energy-systems/energy-scenario-economics-v0.6.0.json'; SCHEMAS=[ROOT/'docs/schemas'/n for n in ['energy-cost-comparison-result.json','energy-simple-payback-result.json','energy-npv-result.json','energy-cost-benefit-result.json','energy-cost-efficiency-result.json','energy-levelized-cost-result.json','energy-economic-scenario.json']]
def read(p): return p.read_text(encoding='utf-8')
def test_versions():
 p=read(PLUGIN); assert 'Version: 5.11.0' in p; assert "SC_CARBON_NATURE_VERSION', '0.5.0'" in p; assert "SC_ENERGY_SYSTEMS_VERSION', '0.6.0'" in p; assert '__version__ = "2.12.0"' in read(INIT)
def test_routes_health():
 m=read(MAIN)
 for path in ['/v1/energy-systems/economics-framework','/v1/energy-systems/energy-cost-comparison','/v1/energy-systems/simple-payback','/v1/energy-systems/npv','/v1/energy-systems/cost-benefit','/v1/energy-systems/cost-efficiency','/v1/energy-systems/levelized-energy-cost','/v1/energy-systems/economic-scenario-template']: assert path in m
 assert '"energy_systems_domain_version": "0.6.0"' in m; assert '"energy_scenario_economics": True' in m; assert '"energy_external_price_feed": False' in m; assert '"energy_investment_recommendation": False' in m
def test_wp_surface_read_only():
 p=read(PHP); assert "public const VERSION = '0.6.0'" in p; assert 'Scenario Economics' in p and 'Energy Balance' in p; assert 'Economic scenario arithmetic ≠ forecast' in p; assert 'data-es-npv-form' in p and 'data-es-cba-form' in p and 'data-es-levelized-form' in p; assert 'WP_REST_Server::CREATABLE' not in p; assert 'wp_remote_post' not in p
def test_assets():
 js,css=read(JS),read(CSS)
 for t in ['energyCostComparisonEndpoint','simplePaybackEndpoint','npvEndpoint','costBenefitEndpoint','costEfficiencyEndpoint','levelizedCostEndpoint']: assert t in js
 assert '.sc-es__calc-grid--economics' in css and '.sc-es__economic-contract' in css
def test_schemas_export():
 docs=[json.loads(read(p)) for p in SCHEMAS]
 for d in docs: Draft202012Validator.check_schema(d)
 e=json.loads(read(EXPORT)); assert e['schema']=='sc-energy-scenario-economics-export/1.0'; assert e['version']=='0.6.0'; assert e['framework']['counts']=={'models':7,'executable_models':6,'scenario_contracts':1}; Draft202012Validator(docs[-1]).validate(e['scenario_template']['template'])
def test_examples_validate():
 sys.path.insert(0,str(ROOT/'library-backend')); from app.energy_systems import EnergySystemsKnowledgeFoundation; e=EnergySystemsKnowledgeFoundation(); results=[e.energy_cost_comparison(baseline_energy_kwh='10000',baseline_price_per_kwh='0.15',candidate_energy_kwh='8000',candidate_price_per_kwh='0.15'),e.simple_payback(initial_cost='5000',annual_net_savings='1000'),e.npv(initial_cost='5000',annual_net_cash_flow='1200',discount_rate_pct='5',years='10'),e.cost_benefit(initial_cost='5000',annual_cost='200',annual_benefit='1400',discount_rate_pct='5',years='10'),e.cost_efficiency(total_cost='5000',energy_saved_kwh='25000',co2e_avoided_kg='10000'),e.levelized_energy_cost(initial_cost='100000',annual_operating_cost='3000',annual_energy_kwh='50000',discount_rate_pct='5',years='20')]
 for result,schema in zip(results,SCHEMAS[:6]): Draft202012Validator(json.loads(read(schema))).validate(result)
def test_syntax():
 for p in [PLUGIN,PHP]:
  r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
 r=subprocess.run(['node','--check',str(JS)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
 r=subprocess.run(['python3','-m','py_compile',str(ENERGY),str(ECON),str(BAL),str(MAIN)],capture_output=True,text=True); assert r.returncode==0,r.stdout+r.stderr
def test_no_write_routes(): assert not re.search(r'@app\.(post|put|patch|delete)',read(ENERGY)+read(ECON)+read(BAL))
def test_prior_assets_preserved():
 assert (ROOT/'sustainable-catalyst-library/assets/js/sc-library-energy-systems-v050.js').is_file(); assert (ROOT/'data/energy-systems/energy-balance-model-v0.5.0.json').is_file(); assert (ROOT/'data/energy-systems/renewable-technologies-v0.4.0.json').is_file(); assert (ROOT/'data/energy-systems/eisd-indicators-v0.3.0.json').is_file()
