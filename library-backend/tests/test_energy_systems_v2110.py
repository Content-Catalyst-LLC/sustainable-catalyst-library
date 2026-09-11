from app.energy_systems import DOMAIN_VERSION, EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_manifest_preserves_prior_layers_and_adds_balance_modeling():
    m = engine().manifest()
    assert DOMAIN_VERSION == "0.5.0"
    assert m["subsystem"]["backend_version"] == "2.11.0"
    assert m["subsystem"]["release"] == "Energy Balance & Systems Modeling"
    assert m["counts"]["concepts"] == 75
    assert m["counts"]["relationships"] == 63
    assert m["counts"]["indicators"] == 30
    assert m["counts"]["renewable_technologies"] == 7
    assert m["counts"]["renewable_resource_classes"] == 6
    assert m["counts"]["energy_balance_models"] == 4
    assert m["counts"]["energy_balance_executable_models"] == 3
    assert m["counts"]["energy_balance_scenario_contracts"] == 1


def test_balance_framework_exposes_bounded_system_identities():
    f = engine().balance_framework()
    assert f["version"] == "0.5.0"
    assert f["counts"] == {
        "models": 4,
        "executable_models": 3,
        "scenario_contracts": 1,
        "renewable_technology_references": 7,
    }
    assert f["system_boundary"]["canonical_energy_unit"] == "kWh-equivalent for v0.5.0 arithmetic"
    assert f["guardrails"]["time_series_dispatch_simulation"] is False
    assert f["guardrails"]["economic_optimization"] is False


def test_conversion_chain_is_deterministic_and_conserves_declared_loss():
    d = engine().conversion_chain(
        input_kwh="1000",
        efficiencies="90,95,97",
        labels="Conversion,Distribution,End use",
    )
    assert d["output"] == {
        "final_output_kwh": "829.35",
        "total_loss_kwh": "170.65",
        "overall_efficiency_pct": "82.935",
    }
    assert [x["label"] for x in d["stages"]] == ["Conversion", "Distribution", "End use"]
    assert sum(float(x["loss_kwh"]) for x in d["stages"]) == 170.65
    assert d["provenance"]["technology_performance_inferred"] is False


def test_conversion_chain_rejects_invalid_efficiency_and_empty_chain():
    e = engine()
    for efficiencies in ("", "101", "-1", "90,not-a-number"):
        try:
            e.conversion_chain(input_kwh="100", efficiencies=efficiencies)
        except ValueError:
            pass
        else:
            raise AssertionError("invalid conversion-chain input must fail closed")


def test_supply_demand_balance_balanced_case():
    d = engine().supply_demand_balance(
        domestic_supply_kwh="1000",
        imports_kwh="100",
        storage_discharge_kwh="50",
        final_demand_kwh="1000",
        exports_kwh="50",
        storage_charge_kwh="25",
        losses_kwh="75",
        tolerance_kwh="0.001",
    )
    assert d["output"]["available_supply_kwh"] == "1150"
    assert d["output"]["accounted_outflows_kwh"] == "1150"
    assert d["output"]["residual_kwh"] == "0"
    assert d["output"]["balanced"] is True


def test_supply_demand_balance_surplus_and_deficit_signs_are_explicit():
    e = engine()
    surplus = e.supply_demand_balance(domestic_supply_kwh="100", final_demand_kwh="90")
    deficit = e.supply_demand_balance(domestic_supply_kwh="100", final_demand_kwh="110")
    assert surplus["output"]["residual_kwh"] == "10"
    assert surplus["output"]["balanced"] is False
    assert deficit["output"]["residual_kwh"] == "-10"
    assert "positive = unallocated surplus" in deficit["accounting"]["residual_sign"]


def test_generation_estimate_uses_only_explicit_capacity_factor():
    d = engine().generation_estimate(capacity_kw="1000", capacity_factor_pct="35", hours="8760")
    assert d["output"] == {"generation_kwh": "3066000", "average_output_kw": "350"}
    assert d["provenance"]["capacity_factor_inferred"] is False
    try:
        engine().generation_estimate(capacity_kw="1000", capacity_factor_pct="101", hours="8760")
    except ValueError:
        pass
    else:
        raise AssertionError("capacity factor above 100 must fail closed")


def test_balance_scenario_contract_is_portable_but_not_persistent_or_optimized():
    d = engine().balance_scenario_template()
    c = d["contract"]
    assert c["canonical_unit"] == "kwh"
    assert c["provenance_required"] is True
    assert c["persistence_status"] == "not-implemented"
    assert c["optimization_status"] == "not-implemented"
    assert c["ranking_status"] == "not-implemented"
    assert len(c["technology_references"]) == 7
    for key in ("supply", "conversion", "demand", "accounting", "evidence", "uncertainty"):
        assert key in d["template"]


def test_guardrails_activate_bounded_modeling_without_overclaiming():
    g = engine().manifest()["guardrails"]
    assert g["scenario_modeling_activated"] is True
    assert g["deterministic_energy_balance_calculation_activated"] is True
    assert g["conversion_chain_model_activated"] is True
    assert g["supply_demand_balance_model_activated"] is True
    assert g["capacity_factor_generation_estimate_activated"] is True
    assert g["scenario_inputs_must_be_explicit"] is True
    assert g["time_series_dispatch_simulation_activated"] is False
    assert g["grid_reliability_or_adequacy_model_activated"] is False
    assert g["economic_optimization_activated"] is False
    assert g["automatic_technology_ranking"] is False
    assert g["automatic_policy_recommendation"] is False


def test_handoffs_advance_to_computational_contracts_without_product_execution():
    items = {x["key"]: x for x in engine().handoffs()["items"]}
    assert items["energy-to-lab"]["status"] == "computational-contract-available"
    assert "energy-balance-scenario-contract" in items["energy-to-lab"]["target_refs"]
    assert "conversion-chain-model" in items["energy-to-lab"]["target_refs"]
    assert items["energy-to-workbench"]["status"] == "computational-contract-available"
    assert "energy-balance-calculation-contract" in items["energy-to-workbench"]["target_refs"]


def test_v040_renewable_model_is_preserved():
    e = engine()
    assert e.technology_framework()["counts"]["technologies"] == 7
    assert e.technology_framework()["counts"]["resource_classes"] == 6
    assert e.technology("wind-energy")["technology"]["suitability_status"] == "not-assessed"
    assert e.technology_comparison_template()["ranking_enabled"] is False


def test_v030_indicator_layer_is_preserved():
    e = engine()
    assert e.indicator_framework()["counts"] == {"indicators": 30, "dimensions": 3, "themes": 7, "subthemes": 19}
    assert e.indicator("ECO13")["indicator"]["label"] == "Renewable energy share in energy and electricity"
    assert e.indicator_observation_template("ENV1")["contract"]["calculation_status"] == "not-implemented"


def test_v020_source_bound_calculators_are_preserved():
    e = engine()
    assert e.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"
    assert e.estimate_carbon(factor_key="natural-gas-kwh-2020", quantity="100")["output"]["kg_co2e"] == "18.387"
    assert e.estimate_heat_content(factor_key="diesel-kwh-per-litre-2020", quantity="10")["output"]["kwh_gross"] == "105.8"
