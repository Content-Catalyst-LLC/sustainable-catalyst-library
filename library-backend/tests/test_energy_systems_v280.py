from decimal import Decimal

from app.energy_systems import DOMAIN_VERSION, EnergySystemsKnowledgeFoundation


def engine() -> EnergySystemsKnowledgeFoundation:
    return EnergySystemsKnowledgeFoundation()


def test_manifest_identity_counts_and_lineage():
    payload = engine().manifest()
    assert payload["ok"] is True
    assert payload["schema"] == "sc-energy-systems-conversion-registry/1.0"
    assert payload["subsystem"]["version"] == "0.2.0"
    assert payload["subsystem"]["backend_version"] == "2.8.0"
    assert payload["subsystem"]["library_version"] == "5.11.0"
    assert payload["counts"] == {
        "concepts": 75,
        "relationships": 63,
        "sources": 6,
        "knowledge_domains": 6,
        "sdg_mappings": 9,
        "handoffs": 7,
        "units": 8,
        "conversion_factors": 4,
        "carbon_factors": 24,
        "heat_content_factors": 16,
        "methodology_rules": 5,
    }
    assert len(payload["content_fingerprint"]) == 64


def test_v010_knowledge_foundation_is_preserved():
    e = engine()
    required = {
        "historical-energy-system-evolution", "global-energy-importance", "energy-resource-estimation",
        "solar-photovoltaics", "solar-thermal", "bioenergy", "hydropower", "tidal-energy",
        "wind-energy", "wave-energy", "soil-carbon", "co2-to-energy", "forest-ecology",
        "digestate", "biochar", "biomass-to-oil", "energy-balance", "cost-benefit-analysis",
        "cost-efficiency-analysis",
    }
    found = {item["key"] for item in e.concepts(limit=250)["items"]}
    assert required <= found
    assert len(e.relationships(limit=500)["items"]) == 63


def test_numeric_source_vintage_and_boundary_are_explicit():
    e = engine()
    sources = {item["key"]: item for item in e.sources()["items"]}
    source = sources["carbon-trust-conversion-2020"]
    assert source["year"] == 2020
    assert source["numeric_status"] == "active-historical-reference-only"
    registry = e.registry()
    assert registry["source_binding"]["source_year"] == 2020
    assert registry["source_binding"]["current_default"] is False
    guardrails = e.manifest()["guardrails"]
    assert guardrails["conversion_factors_activated"] is True
    assert guardrails["current_factor_defaults_activated"] is False
    assert guardrails["historical_calculation_is_not_current_inventory"] is True


def test_energy_conversion_registry_matches_supplied_guide_values():
    rows = {item["from_unit"]: item for item in engine().conversion_factors()["items"]}
    assert rows["therm"]["factor"] == "29.307"
    assert rows["btu"]["factor"] == "0.0002931"
    assert rows["mj"]["factor"] == "0.2778"
    assert rows["toe"]["factor"] == "11630"
    assert all(item["to_unit"] == "kwh" for item in rows.values())
    assert all(item["source_year"] == 2020 for item in rows.values())


def test_energy_conversion_is_source_bound_and_reversible():
    e = engine()
    result = e.convert_energy(value="100000", from_unit="btu", to_unit="kwh")
    assert result["output"] == {"value": "29.31", "unit": "kwh"}
    assert result["source_year"] == 2020
    assert result["status"] == "historical-source-bound-calculation"
    reverse = e.convert_energy(value="29.31", from_unit="kwh", to_unit="btu")
    assert Decimal(reverse["output"]["value"]) == Decimal("100000")


def test_direct_carbon_factor_registry_matches_supplied_guide_values():
    rows = {item["key"]: item for item in engine().carbon_factors(limit=250)["items"]}
    assert len(rows) == 24
    assert rows["uk-grid-electricity-kwh-2020"]["kg_co2e_per_unit"] == "0.23314"
    assert rows["natural-gas-kwh-2020"]["kg_co2e_per_unit"] == "0.18387"
    assert rows["industrial-coal-kwh-2020"]["kg_co2e_per_unit"] == "0.32040"
    assert rows["wood-pellets-kwh-2020"]["kg_co2e_per_unit"] == "0.01545"
    assert all(item["emissions_boundary"] == "direct" for item in rows.values())
    assert all(item["source_year"] == 2020 for item in rows.values())


def test_carbon_estimate_requires_explicit_factor_key_and_preserves_boundary():
    result = engine().estimate_carbon(factor_key="natural-gas-kwh-2020", quantity="100")
    assert result["output"]["kg_co2e"] == "18.387"
    assert result["factor"]["emissions_boundary"] == "direct"
    assert result["factor"]["source_year"] == 2020
    assert "not a current" in result["guardrail"].lower()


def test_heat_content_registry_preserves_gross_calorific_basis():
    rows = {item["key"]: item for item in engine().heat_content_factors(limit=250)["items"]}
    assert len(rows) == 16
    assert rows["diesel-kwh-per-litre-2020"]["kwh_per_unit"] == "10.58"
    assert rows["wood-pellets-kwh-per-tonne-2020"]["kwh_per_unit"] == "5080"
    assert rows["straw-kwh-per-tonne-2020"]["kwh_per_unit"] == "4401"
    assert all(item["calorific_basis"] == "gross calorific value" for item in rows.values())
    result = engine().estimate_heat_content(factor_key="diesel-kwh-per-litre-2020", quantity="10")
    assert result["output"]["kwh_gross"] == "105.8"


def test_renewable_electricity_rule_does_not_fabricate_universal_factor():
    e = engine()
    factors = e.carbon_factors(q="renewable", limit=250)["items"]
    assert factors == []
    rules = {item["key"]: item for item in e.methodology_rules()["items"]}
    assert "renewable-electricity-accounting" in rules
    assert "does not fabricate" in rules["renewable-electricity-accounting"]["rule"]


def test_invalid_numeric_inputs_and_units_fail_closed():
    e = engine()
    for value in ("-1", "nan", "inf", "not-a-number"):
        try:
            e.convert_energy(value=value, from_unit="btu", to_unit="kwh")
        except ValueError:
            pass
        else:
            raise AssertionError(f"invalid value should fail: {value}")
    try:
        e.convert_energy(value="1", from_unit="litre", to_unit="kwh")
    except ValueError:
        pass
    else:
        raise AssertionError("mass/volume units must not be silently treated as energy units")


def test_workbench_handoff_is_contract_only():
    rows = {item["key"]: item for item in engine().handoffs()["items"]}
    handoff = rows["energy-to-workbench"]
    assert handoff["status"] == "contract-available"
    assert "energy-unit-conversion-registry" in handoff["target_refs"]
    assert "does not modify or execute" in handoff["boundary"]
    assert engine().manifest()["guardrails"]["workbench_execution_activated"] is False


def test_indicator_scenario_and_recommendation_boundaries_remain_closed():
    g = engine().manifest()["guardrails"]
    assert g["energy_indicator_calculation_activated"] is False
    assert g["scenario_modeling_activated"] is False
    assert g["automatic_technology_ranking"] is False
    assert g["automatic_policy_recommendation"] is False
    assert DOMAIN_VERSION == "0.2.0"
