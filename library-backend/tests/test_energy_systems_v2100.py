from app.energy_systems import DOMAIN_VERSION, EnergySystemsKnowledgeFoundation


def engine():
    return EnergySystemsKnowledgeFoundation()


def test_manifest_preserves_prior_layers_and_adds_renewable_model():
    m = engine().manifest()
    assert DOMAIN_VERSION == "0.4.0"
    assert m["subsystem"]["backend_version"] == "2.10.0"
    assert m["subsystem"]["release"] == "Renewable Technology & Resource Model"
    assert m["counts"]["concepts"] == 75
    assert m["counts"]["relationships"] == 63
    assert m["counts"]["units"] == 8
    assert m["counts"]["conversion_factors"] == 4
    assert m["counts"]["carbon_factors"] == 24
    assert m["counts"]["heat_content_factors"] == 16
    assert m["counts"]["indicators"] == 30
    assert m["counts"]["renewable_technologies"] == 7
    assert m["counts"]["renewable_resource_classes"] == 6


def test_technology_families_match_module_scope():
    rows = engine().technologies(limit=100)["items"]
    keys = {row["key"] for row in rows}
    assert keys == {
        "solar-photovoltaic", "solar-thermal", "wind-energy", "hydropower",
        "tidal-energy", "wave-energy", "bioenergy",
    }
    assert {row["family"] for row in rows} == {"solar", "wind", "hydro", "marine", "bioenergy"}


def test_resource_classes_are_explicit_and_unpopulated():
    rows = engine().resource_classes()["items"]
    assert {row["key"] for row in rows} == {
        "solar-resource", "wind-resource", "hydrological-resource",
        "tidal-resource", "wave-resource", "biomass-resource",
    }
    assert all(row["quantitative_dataset_status"] == "not-loaded" for row in rows)
    assert all(row["suitability_status"] == "not-assessed" for row in rows)


def test_technology_objects_do_not_fabricate_quantitative_profiles():
    for row in engine().technologies(limit=100)["items"]:
        assert row["quantitative_profile_status"] == "not-populated"
        assert row["suitability_status"] == "not-assessed"
        assert row["source_keys"]
        assert row["resource_class"]


def test_technology_assessment_contract_is_provenance_first():
    d = engine().technology_assessment_template("wind-energy")
    c = d["contract"]
    assert c["provenance_required"] is True
    assert c["methodology_required"] is True
    assert c["quantitative_profile_status"] == "not-populated"
    assert c["suitability_status"] == "not-assessed"
    for field in (
        "geography", "period", "technology_configuration", "resource_observation_reference",
        "conversion_efficiency", "capacity_factor", "capital_cost", "lifecycle_emissions",
        "environmental_constraints", "methodology_reference", "data_sources", "uncertainty_or_quality_note",
    ):
        assert field in c["required_structure"]
    assert d["template"]["technology_key"] == "wind-energy"
    assert d["template"]["resource_class"] == "wind-resource"


def test_biomass_resource_contract_requires_land_and_competing_use_context():
    d = engine().resource_observation_template("biomass-resource")
    fields = d["contract"]["required_structure"]
    for field in ("feedstock_type", "feedstock_origin", "competing_use_note", "land_use_note"):
        assert field in fields
    assert "not automatically gross, technical, economic, or sustainable potential" in d["contract"]["guardrail"]


def test_filters_use_semantic_metadata():
    e = engine()
    assert {x["key"] for x in e.technologies(family="marine")["items"]} == {"tidal-energy", "wave-energy"}
    assert {x["key"] for x in e.technologies(output="heat")["items"]} == {"solar-thermal", "bioenergy"}
    assert {x["key"] for x in e.technologies(q="photovoltaic")["items"]} == {"solar-photovoltaic"}


def test_unknown_technology_and_resource_fail_closed():
    e = engine()
    for fn, key in ((e.technology, "fusion"), (e.resource_class, "magic-resource")):
        try:
            fn(key)
        except KeyError:
            pass
        else:
            raise AssertionError("unknown registry keys must fail closed")


def test_comparison_template_has_no_scores_or_ranking():
    d = engine().technology_comparison_template()
    assert d["values_loaded"] is False
    assert d["ranking_enabled"] is False
    assert len(d["candidate_technology_keys"]) == 7
    assert "uncertainty and provenance" in d["dimensions"]


def test_guardrails_expose_renewable_boundaries():
    g = engine().manifest()["guardrails"]
    assert g["renewable_technology_registry_activated"] is True
    assert g["renewable_resource_class_registry_activated"] is True
    assert g["quantitative_technology_profiles_loaded"] is False
    assert g["live_resource_potential_datasets_loaded"] is False
    assert g["renewable_suitability_assessment_activated"] is False
    assert g["automatic_technology_ranking"] is False


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


def test_handoffs_activate_contracts_not_execution():
    items = {x["key"]: x for x in engine().handoffs()["items"]}
    assert items["energy-to-lab"]["status"] == "contract-available"
    assert "renewable-technology-assessment-contract" in items["energy-to-lab"]["target_refs"]
    assert items["energy-to-site-intelligence"]["status"] == "contract-available"
    assert "renewable-resource-observation-contract" in items["energy-to-site-intelligence"]["target_refs"]
