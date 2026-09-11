from app.energy_systems import EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_manifest_versions_and_bioenergy_counts():
    d = engine().manifest()
    assert d["subsystem"]["version"] == "0.8.0"
    assert d["subsystem"]["backend_version"] == "2.14.0"
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
    assert h["status"] == "live-data-contract-available"
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
