from app.energy_systems import EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_manifest_versions_and_bioenergy_counts():
    d = engine().manifest()
    assert d["subsystem"]["version"] == "1.1.0"
    assert d["subsystem"]["backend_version"] == "2.17.0"
    assert d["counts"]["bioenergy_feedstock_classes"] == 5
    assert d["counts"]["bioenergy_pathways"] == 6
    assert d["counts"]["carbon_nature_bridges"] == 6
    assert d["counts"]["bioenergy_executable_models"] == 4
    assert d["counts"]["bioenergy_scenario_contracts"] == 1


def test_bioenergy_framework_guardrails():
    d = engine().bioenergy_framework()
    assert d["version"] == "0.7.0"
    assert d["guardrails"]["biomass_carbon_neutrality_assumed"] is False
    assert d["guardrails"]["avoided_emissions_inferred"] is False
    assert d["guardrails"]["carbon_credit_eligibility_determined"] is False
    assert d["guardrails"]["cross_domain_carbon_nature_targets_validated"] is True


def test_feedstock_and_pathway_registries():
    e = engine()
    feeds = e.bioenergy_feedstocks()
    paths = e.bioenergy_pathways()
    assert feeds["count"] == 5
    assert paths["count"] == 6
    assert {x["key"] for x in paths["items"]} >= {
        "anaerobic-digestion-biogas", "digestate-management", "biochar-production", "biomass-to-oil", "co2-to-energy"
    }


def test_pathway_detail_has_carbon_nature_bridge():
    d = engine().bioenergy_pathway("anaerobic-digestion-biogas")
    assert d["pathway"]["coproducts"] == ["digestate"]
    assert d["carbon_nature_bridges"]


def test_carbon_nature_bridges_resolve_existing_targets():
    d = engine().biological_carbon_bridges()
    assert d["count"] == 6
    lookup = {x["key"]: x for x in d["items"]}
    assert "soil-organic-carbon" in lookup["soil-carbon-bridge"]["carbon_nature_concepts"]
    assert "whole-system-ghg-accounting" in lookup["biochar-bridge"]["carbon_nature_methodologies"]
    assert "biomass-inventory-measurement" in lookup["forest-carbon-bridge"]["carbon_nature_methodologies"]


def test_feedstock_energy_estimate():
    d = engine().feedstock_energy_estimate(
        mass_tonnes="10", energy_content_kwh_per_tonne="4000", conversion_efficiency_pct="80"
    )
    assert d["output"] == {
        "gross_energy_kwh": "40000", "useful_energy_kwh": "32000", "conversion_loss_kwh": "8000"
    }
    assert d["provenance"]["default_feedstock_heating_value_used"] is False


def test_anaerobic_digestion_energy_estimate():
    d = engine().anaerobic_digestion_energy_estimate(
        feedstock_mass_tonnes="10", biogas_yield_m3_per_tonne="100", methane_fraction_pct="60",
        methane_energy_kwh_per_m3="10", conversion_efficiency_pct="40"
    )
    assert d["output"]["biogas_volume_m3"] == "1000"
    assert d["output"]["methane_volume_m3"] == "600"
    assert d["output"]["gross_methane_energy_kwh"] == "6000"
    assert d["output"]["useful_energy_kwh"] == "2400"


def test_biochar_stoichiometric_carbon_estimate():
    d = engine().biochar_carbon_estimate(
        biochar_mass_kg="1000", carbon_fraction_pct="70", stable_fraction_pct="80"
    )
    assert d["output"]["carbon_mass_kg_c"] == "700"
    assert d["output"]["stable_carbon_mass_kg_c"] == "560"
    assert d["method"]["carbon_to_co2_mass_ratio"] == "44/12"
    assert d["output"]["stoichiometric_co2_equivalent_kg"].startswith("2053.333333")
    assert "not a lifecycle removal" in d["boundary"]


def test_biomass_to_oil_energy_estimate():
    d = engine().biomass_to_oil_energy_estimate(
        feedstock_mass_tonnes="10", oil_yield_mass_pct="30", oil_energy_content_kwh_per_tonne="9000",
        downstream_conversion_efficiency_pct="90"
    )
    assert d["output"] == {
        "oil_product_mass_tonnes": "3", "gross_product_energy_kwh": "27000", "useful_energy_kwh": "24300"
    }


def test_percentages_fail_closed():
    e = engine()
    try:
        e.biochar_carbon_estimate(biochar_mass_kg="1", carbon_fraction_pct="101", stable_fraction_pct="80")
        assert False, "expected ValueError"
    except ValueError:
        pass
    try:
        e.anaerobic_digestion_energy_estimate(feedstock_mass_tonnes="1", biogas_yield_m3_per_tonne="1", methane_fraction_pct="nan", methane_energy_kwh_per_m3="1")
        assert False, "expected ValueError"
    except ValueError:
        pass


def test_bioenergy_scenario_contract():
    d = engine().bioenergy_scenario_template()
    c = d["contract"]
    assert c["provenance_required"] is True
    assert c["energy_balance_handoff_ready"] is True
    assert c["economic_scenario_handoff_ready"] is True
    assert c["carbon_nature_handoff_ready"] is True
    assert c["project_crediting_status"] == "not-implemented"
    assert c["lifecycle_assessment_status"] == "not-implemented"


def test_handoffs_promoted_for_carbon_nature():
    lookup = {x["key"]: x for x in engine().handoffs()["items"]}
    b = lookup["bioenergy-carbon-nature-extension"]
    assert b["status"] == "cross-domain-contract-available"
    assert "soil-organic-carbon" in b["target_refs"]
    assert "whole-system-ghg-accounting" in b["target_refs"]
    assert "bioenergy-carbon-scenario-contract" in lookup["energy-to-lab"]["target_refs"]


def test_v060_and_prior_are_preserved():
    e = engine()
    assert e.economics_framework()["version"] == "0.6.0"
    assert e.npv(initial_cost="5000", annual_net_cash_flow="1200", discount_rate_pct="0", years="5")["output"]["npv"] == "1000"
    assert e.balance_framework()["version"] == "0.5.0"
    assert e.generation_estimate(capacity_kw="1000", capacity_factor_pct="35", hours="8760")["output"]["generation_kwh"] == "3066000"
    assert e.technology_framework()["counts"]["technologies"] == 7
    assert e.indicator_framework()["counts"]["indicators"] == 30
    assert e.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"


def test_methodology_rule_added_without_current_default():
    rows = {x["key"]: x for x in engine().methodology_rules()["items"]}
    assert "biological-carbon-bioenergy-boundary" in rows
    assert rows["biological-carbon-bioenergy-boundary"]["current_default"] is False


from app.energy_global import GlobalEnergyDataError, GlobalEnergyIntelligence


def sample_world_bank_payload_multi():
    return [
        {"page": 1, "pages": 1, "per_page": 20000, "total": 5, "lastupdated": "2026-08-01"},
        [
            {"indicator":{"id":"EG.ELC.ACCS.ZS","value":"Access to electricity (% of population)"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2024","value":100.0,"obs_status":"","decimal":1},
            {"indicator":{"id":"EG.ELC.ACCS.ZS","value":"Access to electricity (% of population)"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2023","value":100.0,"obs_status":"","decimal":1},
            {"indicator":{"id":"EG.FEC.RNEW.ZS","value":"Renewable energy consumption (% of total final energy consumption)"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2022","value":12.5,"obs_status":"","decimal":1},
            {"indicator":{"id":"EG.IMP.CONS.ZS","value":"Energy imports, net (% of energy use)"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2023","value":-3.2,"obs_status":"","decimal":1},
            {"indicator":{"id":"EG.USE.ELEC.KH.PC","value":"Electric power consumption (kWh per capita)"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2024","value":12000.0,"obs_status":"","decimal":1}
        ]
    ]


def test_global_energy_framework_and_counts():
    d = engine().global_energy_framework()
    assert d["version"] == "0.8.0"
    assert d["counts"] == {"metrics": 9, "sources": 4, "live_connectors": 1, "profile_contracts": 1, "comparison_contracts": 1}
    assert d["guardrails"]["latest_available_is_not_current_year"] is True
    assert d["guardrails"]["embedded_current_country_values"] is False


def test_global_energy_metric_registry_contains_expected_world_bank_codes():
    rows = {x["key"]: x for x in engine().global_energy_metrics()["items"]}
    assert len(rows) == 9
    assert rows["electricity-access"]["source_indicator"] == "EG.ELC.ACCS.ZS"
    assert rows["renewable-final-energy-share"]["source_indicator"] == "EG.FEC.RNEW.ZS"
    assert rows["net-energy-import-dependency"]["source_indicator"] == "EG.IMP.CONS.ZS"
    assert rows["transmission-distribution-losses"]["source_indicator"] == "EG.ELC.LOSS.ZS"
    assert rows["energy-productivity"]["source_indicator"] == "EG.GDP.PUSE.KO.PP.KD"


def test_global_source_registry_has_one_active_connector_and_no_key_requirement_for_it():
    rows = {x["key"]: x for x in engine().global_energy_sources()["items"]}
    active = [x for x in rows.values() if x["status"] == "active"]
    assert len(active) == 1
    assert active[0]["key"] == "world-bank-wdi"
    assert active[0]["authentication"] == "none"
    assert rows["ember-api"]["status"] == "contract-ready-not-activated"
    assert rows["eia-api-v2"]["authentication"] == "api-key-required"


def test_global_country_profile_parses_live_source_payload_without_interpolation():
    g = GlobalEnergyIntelligence(request_json=lambda url: sample_world_bank_payload_multi())
    d = g.country_profile(country="USA", start_year=2020, end_year=2026)
    assert d["country_code"] == "USA"
    assert d["country_name"] == "United States"
    by_key = {x["metric"]["key"]: x for x in d["metrics"]}
    assert by_key["electricity-access"]["latest"]["year"] == 2024
    assert by_key["electricity-access"]["observation_count"] == 2
    assert by_key["renewable-final-energy-share"]["latest"]["value"] == 12.5
    assert by_key["net-energy-import-dependency"]["latest"]["value"] == -3.2
    assert by_key["energy-use-per-capita"]["missing"] is True
    assert d["guardrails"]["no_interpolation"] is True


def test_global_country_profile_url_is_source_coded_and_dated():
    seen=[]
    g = GlobalEnergyIntelligence(request_json=lambda url: (seen.append(url) or sample_world_bank_payload_multi()))
    g.country_profile(country="USA", start_year=2020, end_year=2024)
    assert seen
    assert "/v2/country/USA/indicator/" in seen[0]
    assert "EG.ELC.ACCS.ZS" in seen[0]
    assert "EG.GDP.PUSE.KO.PP.KD" in seen[0]
    assert "date=2020%3A2024" in seen[0]
    assert "source=2" in seen[0]


def test_global_compare_same_indicator_preserves_country_years():
    payload=[
        {"page":1,"pages":1,"total":2,"lastupdated":"2026-08-01"},
        [
            {"indicator":{"id":"EG.ELC.ACCS.ZS","value":"Access"},"country":{"id":"US","value":"United States"},"countryiso3code":"USA","date":"2024","value":100,"obs_status":"","decimal":1},
            {"indicator":{"id":"EG.ELC.ACCS.ZS","value":"Access"},"country":{"id":"IE","value":"Ireland"},"countryiso3code":"IRL","date":"2023","value":100,"obs_status":"","decimal":1}
        ]
    ]
    g=GlobalEnergyIntelligence(request_json=lambda url: payload)
    d=g.compare(countries="USA,IRL", metric_key="electricity-access", start_year=2020, end_year=2026)
    assert d["metric"]["source_indicator"] == "EG.ELC.ACCS.ZS"
    assert [x["latest"]["year"] for x in d["countries"]] == [2024, 2023]
    assert "Latest available years may differ" in d["guardrail"]


def test_global_input_validation_fails_closed():
    g=GlobalEnergyIntelligence(request_json=lambda url: sample_world_bank_payload_multi())
    for bad in ["", "1W", "UNITEDSTATES", "U1"]:
        try:
            g.country_profile(country=bad)
            assert False, bad
        except ValueError:
            pass
    try:
        g.compare(countries="USA,IRL,KEN,FRA,DEU,ESP,ITA,CAN,JPN", metric_key="electricity-access")
        assert False
    except ValueError:
        pass


def test_global_source_failure_does_not_fabricate_fallback():
    def bad(_url):
        raise GlobalEnergyDataError("source offline")
    g=GlobalEnergyIntelligence(request_json=bad)
    try:
        g.country_profile(country="USA")
        assert False
    except GlobalEnergyDataError as exc:
        assert "source offline" in str(exc)


def test_global_profile_contract_handoff_flags():
    c=engine().global_energy_profile_template()["contract"]
    assert c["provenance_required"] is True
    assert c["site_intelligence_handoff_ready"] is True
    assert c["lab_handoff_ready"] is True
    assert c["decision_studio_handoff_ready"] is True
    assert c["missing_value_policy"] == "omit-null-observation-no-interpolation"


def test_global_handoff_promotes_site_intelligence_contract():
    lookup={x["key"]:x for x in engine().handoffs()["items"]}
    h=lookup["energy-to-site-intelligence"]
    assert h["status"] == "runtime-gateway-active-target-consumer-pending"
    assert "global-energy-country-profile-contract" in h["target_refs"]
    assert "world-bank-wdi-live-connector" in h["target_refs"]


def test_v070_and_prior_remain_preserved_under_global_layer():
    e=engine()
    assert e.bioenergy_framework()["version"] == "0.7.0"
    assert e.economics_framework()["version"] == "0.6.0"
    assert e.balance_framework()["version"] == "0.5.0"
    assert e.technology_framework()["version"] == "0.4.0"
    assert e.indicator_framework()["schema"] == "sc-energy-indicator-framework/1.0"
    assert e.registry()["schema"] == "sc-energy-numeric-registry/1.0"



def decision_packet(unit_b="USD", period_b="2030", source_b="source-b"):
    return {
        "identity": {"decision_id":"energy-2030","title":"Energy options","question":"How do alternatives compare?","geography":"Example","period":"2030"},
        "decision_context": {"objectives":["reliable service"],"constraints":[],"stakeholders":[],"notes":""},
        "alternatives": [
            {"key":"option-a","label":"Option A","description":"","technology_refs":["solar-pv"],"scenario_refs":["scenario-a"],"evidence_refs":[],"uncertainty_note":"",
             "criteria_observations":[
                {"criterion_key":"capital-cost","value":"100000","unit":"USD","period":"2030","source_ref":"source-a","methodology_ref":"cost-model-a","uncertainty":"±10%","notes":""},
                {"criterion_key":"renewable-energy-share","value":"60","unit":"%","period":"2030","source_ref":"source-a","methodology_ref":"mix-model-a","uncertainty":"scenario range","notes":""}
             ]},
            {"key":"option-b","label":"Option B","description":"","technology_refs":["wind"],"scenario_refs":["scenario-b"],"evidence_refs":[],"uncertainty_note":"",
             "criteria_observations":[
                {"criterion_key":"capital-cost","value":"120000","unit":unit_b,"period":period_b,"source_ref":source_b,"methodology_ref":"cost-model-b","uncertainty":"±15%","notes":""},
                {"criterion_key":"renewable-energy-share","value":"70","unit":"%","period":"2030","source_ref":"source-b","methodology_ref":"mix-model-b","uncertainty":"scenario range","notes":""}
             ]}
        ],
        "review": {"assumptions":[],"evidence_gaps":["reliability model"],"open_questions":[],"reviewer_notes":""}
    }


def test_decision_framework_counts_and_guardrails():
    d=engine().decision_framework()
    assert d["version"] == "0.9.0"
    assert d["counts"]["criteria"] == 12
    assert d["counts"]["dimensions"] == 9
    assert d["counts"]["decision_packet_contracts"] == 1
    assert d["guardrails"]["automatic_alternative_ranking"] is False
    assert d["guardrails"]["composite_sustainability_score"] is False
    assert d["guardrails"]["matrix_is_not_decision"] is True


def test_decision_criteria_cover_prior_energy_layers():
    rows={x["key"]:x for x in engine().decision_criteria()["items"]}
    assert len(rows)==12
    assert "energy-npv-result" in rows["net-present-value"]["evidence_refs"]
    assert "net-energy-import-dependency" in rows["energy-security-import-dependency"]["evidence_refs"]
    assert "whole-system-ghg-accounting" in rows["ghg-emissions"]["evidence_refs"]
    assert rows["reliability-flexibility"]["comparison_semantics"] == "context-only"


def test_decision_packet_template_is_blank_and_non_persistent():
    d=engine().decision_packet_template()
    assert d["version"] == "0.9.0"
    assert len(d["packet"]["alternatives"]) == 2
    assert d["packet"]["alternatives"][0]["criteria_observations"] == []
    assert d["guardrails"]["scenario_persistence"] is False
    assert d["guardrails"]["decision_studio_execution"] is False


def test_decision_comparison_matrix_neutral_and_provenance_visible():
    import json
    d=engine().decision_comparison_matrix(packet_json=json.dumps(decision_packet()))
    assert len(d["alternatives"]) == 2
    assert len(d["rows"]) == 2
    capital=next(x for x in d["rows"] if x["criterion"]["key"]=="capital-cost")
    assert [x["value"] for x in capital["cells"]] == ["100000","120000"]
    assert capital["cells"][0]["source_ref"] == "source-a"
    assert d["incompatibilities"] == []
    assert d["guardrails"]["no_ranking"] is True
    assert d["guardrails"]["no_composite_score"] is True


def test_decision_matrix_flags_unit_and_period_mismatch_without_harmonizing():
    import json
    d=engine().decision_comparison_matrix(packet_json=json.dumps(decision_packet(unit_b="EUR",period_b="2031")))
    capital=next(x for x in d["rows"] if x["criterion"]["key"]=="capital-cost")
    assert set(capital["comparison_flags"]) == {"unit-mismatch","period-mismatch"}
    assert d["incompatibilities"][0]["units"] == ["EUR","USD"]
    assert d["incompatibilities"][0]["periods"] == ["2030","2031"]


def test_decision_readiness_reports_completeness_not_merit():
    import json
    d=engine().decision_readiness(packet_json=json.dumps(decision_packet()))
    assert d["identity"]["complete"] is True
    assert d["criteria"]["observed_count"] == 2
    assert d["declared_evidence_gap_count"] == 1
    assert all(x["provenance_coverage_pct"] == 100.0 for x in d["alternatives"])
    assert "not a merit score" in d["readiness_interpretation"]


def test_decision_packet_unknown_criterion_fails_closed():
    import json
    p=decision_packet(); p["alternatives"][0]["criteria_observations"][0]["criterion_key"]="invented-score"
    try:
        engine().decision_readiness(packet_json=json.dumps(p))
        assert False
    except ValueError as exc:
        assert "unknown decision criterion" in str(exc)


def test_decision_packet_requires_two_unique_alternatives():
    import json
    p=decision_packet(); p["alternatives"]=p["alternatives"][:1]
    try:
        engine().decision_readiness(packet_json=json.dumps(p)); assert False
    except ValueError as exc: assert "at least two alternatives" in str(exc)
    p=decision_packet(); p["alternatives"][1]["key"]="option-a"
    try:
        engine().decision_readiness(packet_json=json.dumps(p)); assert False
    except ValueError as exc: assert "alternative keys must be unique" in str(exc)


def test_v080_global_energy_remains_preserved_under_decision_layer():
    e=engine()
    assert e.global_energy_framework()["version"] == "0.8.0"
    assert e.bioenergy_framework()["version"] == "0.7.0"
    assert e.economics_framework()["version"] == "0.6.0"
    assert e.balance_framework()["version"] == "0.5.0"



def test_v100_integrated_platform_framework():
    d = engine().platform_framework()
    assert d["version"] == "1.0.0"
    assert d["release"] == "Integrated Sustainable Energy Systems Platform"
    assert d["counts"] == {
        "release_layers": 9,
        "cross_product_contracts": 6,
        "integrated_study_contracts": 1,
        "structural_certification_models": 1,
    }
    assert [x["version"] for x in d["release_layers"]] == ["0.1.0","0.2.0","0.3.0","0.4.0","0.5.0","0.6.0","0.7.0","0.8.0","0.9.0"]
    assert d["guardrails"]["cross_product_execution_claimed_by_this_release"] is False
    assert d["guardrails"]["certification_is_scientific_validation"] is False


def test_v100_cross_product_contracts_are_available_without_execution_claims():
    d = engine().platform_contracts()
    assert d["count"] == 6
    rows = {x["target"]: x for x in d["items"]}
    assert set(rows) == {"Library","Research Librarian","Lab","Workbench","Site Intelligence","Decision Studio"}
    assert rows["Library"]["execution_state"] == "host-runtime-active"
    for name in ["Research Librarian","Lab","Workbench","Site Intelligence","Decision Studio"]:
        assert rows[name]["availability"] == "contract-available"
        assert rows[name]["execution_state"] == "not-activated-by-this-release"


def test_v100_integrated_study_contract_is_blank_provenance_first_and_nonranking():
    d = engine().platform_study_template()
    assert d["version"] == "1.0.0"
    study = d["study"]
    assert study["identity"]["study_id"] == ""
    assert study["research_context"]["source_refs"] == []
    assert study["global_context"]["observation_years"] == []
    assert study["decision"]["decision_packet_ref"] == ""
    assert d["contract"]["provenance_required"] is True
    assert d["contract"]["automatic_ranking"] is False
    assert d["contract"]["automatic_recommendation"] is False
    assert d["contract"]["persistence_status"] == "not-implemented"


def test_v100_structural_certification_passes_expected_contract_stack():
    d = engine().platform_certification()
    assert d["ok"] is True
    assert d["status"] == "pass"
    assert d["counts"] == {"checks": 20, "passed": 20, "failed": 0}
    assert all(x["status"] == "pass" for x in d["checks"])
    assert d["guardrails"]["scientific_validation"] is False
    assert d["guardrails"]["live_deployment_audit"] is False
    assert "repository" in d["interpretation"].lower()


def test_v100_manifest_integrates_without_replacing_prior_layers():
    d = engine().manifest()
    assert d["counts"]["integrated_platform_release_layers"] == 9
    assert d["counts"]["integrated_platform_cross_product_contracts"] == 6
    assert d["counts"]["methodology_rules"] == 16
    assert d["energy_decision_intelligence"]["version"] == "0.9.0"
    assert d["global_energy_intelligence"]["version"] == "0.8.0"
    assert d["biological_carbon_bioenergy_integration"]["version"] == "0.7.0"
    assert d["energy_scenario_economics"]["version"] == "0.6.0"
    assert d["energy_balance_systems_model"]["version"] == "0.5.0"
    assert d["renewable_technology_resource_model"]["version"] == "0.4.0"



def populated_runtime_study():
    d = engine().platform_study_template()["study"]
    d["identity"].update({"study_id":"study-001","title":"Runtime activation study","question":"How should the energy scenario be evaluated?","geography":"Example region","period":"2030"})
    d["research_context"]["source_refs"]=["source-1"]
    d["research_context"]["research_questions"]=["What evidence is missing?"]
    d["numeric_registry"]["conversion_refs"]=["btu-to-kwh"]
    d["technologies_and_resources"]["technology_refs"]=["solar-photovoltaic"]
    d["technologies_and_resources"]["resource_observations"]=[{"ref":"resource-1"}]
    d["energy_balance"]["scenario_refs"]=["balance-1"]
    d["economics"]["scenario_refs"]=["econ-1"]
    d["global_context"]["country_profile_refs"]=["USA"]
    d["global_context"]["observation_years"]=[2024]
    d["decision"]["decision_packet_ref"]="decision-1"
    d["uncertainty"]=[{"ref":"u-1"}]
    d["provenance"]=[{"source_ref":"source-1"}]
    return d


def test_v110_runtime_activation_framework():
    d=engine().runtime_framework()
    assert d["version"]=="1.1.0"
    assert d["release"]=="Cross-Product Runtime Activation Gateway"
    assert d["counts"]=={
        "external_runtime_targets":5,
        "target_packet_builders":5,
        "pull_handoff_contracts":5,
        "target_runtimes_certified_active":0,
    }
    assert set(d["targets"])=={"Research Librarian","Lab","Workbench","Site Intelligence","Decision Studio"}
    assert d["guardrails"]["outbound_push_delivery_activated"] is False
    assert d["guardrails"]["target_runtime_consumption_certified"] is False


def test_v110_runtime_target_registry_is_gateway_active_without_execution_claims():
    d=engine().runtime_targets()
    assert d["count"]==5
    rows={x["key"]:x for x in d["items"]}
    assert set(rows)=={"research-librarian","lab","workbench","site-intelligence","decision-studio"}
    assert all(x["gateway_state"]=="library-gateway-active" for x in rows.values())
    assert all(x["target_runtime_state"]=="target-consumer-not-certified" for x in rows.values())
    assert rows["lab"]["consumer_contract"]=="sc-energy-runtime-lab-handoff/1.0"
    assert "global-energy-country-profile-contract" in rows["site-intelligence"]["source_contracts"]


def test_v110_handoff_builder_is_deterministic_target_shaped_and_stateless():
    import json
    study=populated_runtime_study()
    a=engine().runtime_handoff(target_key="lab",study_json=json.dumps(study))
    b=engine().runtime_handoff(target_key="lab",study_json=json.dumps(study,indent=2))
    assert a["packet"]["handoff_id"]==b["packet"]["handoff_id"]
    assert a["packet"]["target"]["product"]=="Lab"
    assert set(a["packet"]["payload"])=={"identity","technologies_and_resources","energy_balance","economics","bioenergy_and_carbon","uncertainty","provenance","review"}
    assert a["packet"]["validation"]["status"]=="ready"
    assert a["delivery"]=={"mode":"pull-only","outbound_delivery_performed":False,"persistence_performed":False,"target_execution_claimed":False}


def test_v110_target_payloads_select_only_governed_sections():
    import json
    study=populated_runtime_study()
    expected={
        "research-librarian":{"identity","research_context","sustainability_indicators","global_context","provenance","review"},
        "workbench":{"identity","numeric_registry","energy_balance","economics","bioenergy_and_carbon","provenance","review"},
        "site-intelligence":{"identity","technologies_and_resources","global_context","provenance","review"},
        "decision-studio":{"identity","decision","economics","sustainability_indicators","global_context","uncertainty","provenance","review"},
    }
    for key,sections in expected.items():
        d=engine().runtime_handoff(target_key=key,study_json=json.dumps(study))
        assert set(d["packet"]["payload"])==sections
        assert d["packet"]["validation"]["status"]=="ready"


def test_v110_runtime_readiness_surfaces_missing_context_without_scoring():
    import json
    blank=engine().platform_study_template()["study"]
    d=engine().runtime_readiness(target_key="site-intelligence",study_json=json.dumps(blank))
    assert d["status"]=="ready-with-warnings"
    keys={x["key"] for x in d["issues"]}
    assert {"missing-study-id","missing-question","missing-provenance","missing-spatial-energy-context"}.issubset(keys)
    assert "decision quality" in d["interpretation"]


def test_v110_runtime_input_validation_fails_closed():
    import json
    try:
        engine().runtime_handoff(target_key="unknown",study_json="{}")
        assert False
    except KeyError:
        pass
    try:
        engine().runtime_handoff(target_key="lab",study_json="not-json")
        assert False
    except ValueError as exc:
        assert "valid JSON" in str(exc)
    bad=populated_runtime_study(); bad["unexpected"]={}
    try:
        engine().runtime_handoff(target_key="lab",study_json=json.dumps(bad))
        assert False
    except ValueError as exc:
        assert "unknown top-level" in str(exc)


def test_v110_manifest_adds_runtime_activation_without_overwriting_v100_certification_baseline():
    d=engine().manifest()
    assert d["subsystem"]["version"]=="1.1.0"
    assert d["subsystem"]["backend_version"]=="2.17.0"
    assert d["counts"]["runtime_activation_targets"]==5
    assert d["counts"]["runtime_handoff_packet_builders"]==5
    assert d["counts"]["runtime_target_runtimes_certified_active"]==0
    assert d["cross_product_runtime_activation"]["version"]=="1.1.0"
    cert=engine().platform_certification()
    assert cert["version"]=="1.0.0"
    assert cert["status"]=="pass"
    assert cert["counts"]=={"checks":20,"passed":20,"failed":0}


def test_v110_platform_handoffs_promote_gateway_status_without_claiming_target_consumption():
    rows={x["key"]:x for x in engine().handoffs()["items"]}
    for key in ["energy-to-research-librarian","energy-to-workbench","energy-to-lab","energy-to-site-intelligence","energy-to-decision-studio"]:
        assert rows[key]["status"]=="runtime-gateway-active-target-consumer-pending"
    assert len(rows)==8
