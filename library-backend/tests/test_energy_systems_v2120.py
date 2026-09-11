from app.energy_systems import EnergySystemsKnowledgeFoundation

def engine(): return EnergySystemsKnowledgeFoundation()

def test_manifest_versions_and_economic_counts():
 d=engine().manifest(); assert d["subsystem"]["version"]=="0.6.0"; assert d["subsystem"]["backend_version"]=="2.12.0"; assert d["counts"]["energy_economic_models"]==7; assert d["counts"]["energy_economic_executable_models"]==6

def test_energy_cost_comparison():
 d=engine().energy_cost_comparison(baseline_energy_kwh="10000",baseline_price_per_kwh="0.15",candidate_energy_kwh="8000",candidate_price_per_kwh="0.15",currency="USD"); assert d["output"]["baseline_cost"]=="1500"; assert d["output"]["candidate_cost"]=="1200"; assert d["output"]["absolute_savings"]=="300"; assert d["output"]["savings_pct_of_baseline"]=="20"

def test_simple_payback_and_nonpositive_savings():
 assert engine().simple_payback(initial_cost="5000",annual_net_savings="1000")["output"]["payback_years"]=="5"; assert engine().simple_payback(initial_cost="5000",annual_net_savings="0")["output"]["payback_years"] is None

def test_npv_zero_rate():
 d=engine().npv(initial_cost="5000",annual_net_cash_flow="1200",discount_rate_pct="0",years="5"); assert d["output"]["npv"]=="1000"; assert d["output"]["present_value_cash_flows"]=="6000"

def test_cost_benefit_zero_rate():
 d=engine().cost_benefit(initial_cost="1000",annual_cost="100",annual_benefit="400",discount_rate_pct="0",years="5"); assert d["output"]["present_value_costs"]=="1500"; assert d["output"]["present_value_benefits"]=="2000"; assert d["output"]["net_present_benefit"]=="500"; assert d["output"]["benefit_cost_ratio"]=="1.333333333333"

def test_cost_efficiency():
 d=engine().cost_efficiency(total_cost="5000",energy_saved_kwh="25000",co2e_avoided_kg="10000"); assert d["output"]["cost_per_kwh_saved"]=="0.2"; assert d["output"]["cost_per_mwh_saved"]=="200"; assert d["output"]["cost_per_tonne_co2e_avoided"]=="500"

def test_levelized_zero_rate():
 d=engine().levelized_energy_cost(initial_cost="100000",annual_operating_cost="3000",annual_energy_kwh="50000",discount_rate_pct="0",years="20"); assert d["output"]["present_value_costs"]=="160000"; assert d["output"]["present_value_energy_kwh"]=="1000000"; assert d["output"]["levelized_cost_per_kwh"]=="0.16"

def test_contract_and_guardrails():
 e=engine(); c=e.economic_scenario_template()["contract"]; assert c["provenance_required"] is True; assert c["optimization_status"]=="not-implemented"; assert c["recommendation_status"]=="not-implemented"; g=e.manifest()["guardrails"]; assert g["energy_scenario_economics_activated"] is True; assert g["external_energy_price_feed_loaded"] is False; assert g["technology_cost_database_loaded"] is False; assert g["investment_recommendation_activated"] is False

def test_decision_studio_handoff():
 ds={x["key"]:x for x in engine().handoffs()["items"]}["energy-to-decision-studio"]; assert ds["status"]=="decision-contract-available"; assert "energy-economic-scenario-contract" in ds["target_refs"]; assert "energy-npv-result" in ds["target_refs"]

def test_v050_and_prior_preserved():
 e=engine(); assert e.balance_framework()["version"]=="0.5.0"; assert e.generation_estimate(capacity_kw="1000",capacity_factor_pct="35",hours="8760")["output"]["generation_kwh"]=="3066000"; assert e.technology_framework()["counts"]["technologies"]==7; assert e.indicator_framework()["counts"]["indicators"]==30; assert e.convert_energy(value="100000",from_unit="btu",to_unit="kwh")["output"]["value"]=="29.31"
