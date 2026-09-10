from app.carbon_nature import (
    CarbonNatureKnowledgeFoundation,
    CONCEPTS,
    DOMAIN_VERSION,
    MEASURES,
    MEASURE_SCHEMA_VERSION,
    RELATIONSHIPS,
)


def test_manifest_identity_measure_registry_and_guardrails():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.manifest()
    assert manifest["schema"] == "sc-carbon-nature-knowledge-foundation/1.1"
    assert manifest["subsystem"]["version"] == "0.2.0"
    assert manifest["subsystem"]["release"] == "Carbon Sequestration Measure Registry"
    assert manifest["subsystem"]["primary_home"] == "Sustainable Catalyst Library"
    assert manifest["subsystem"]["backend_version"] == "2.3.0"
    assert manifest["coverage"]["measure_count"] == 10
    governance = manifest["governance"]
    assert governance["carbon_credit_issuance"] is False
    assert governance["certification_body"] is False
    assert governance["automatic_measure_ranking"] is False
    assert governance["automatic_measure_suitability_determination"] is False
    assert governance["quantified_sequestration_potential"] is False
    assert governance["soc_calculation_engine"] is False
    assert governance["whole_farm_ghg_calculator"] is False


def test_v010_foundation_concepts_relationships_are_preserved():
    keys = [item.key for item in CONCEPTS]
    assert len(keys) == len(set(keys))
    for key in ["afolu", "nature-based-solutions", "carbon-farming", "soil-organic-carbon", "mrv", "policy", "evidence"]:
        assert key in keys
    concept_keys = set(keys)
    for rel in RELATIONSHIPS:
        assert rel.subject in concept_keys
        assert rel.object in concept_keys
        assert rel.inference_allowed is False


def test_measure_keys_are_unique_and_all_concept_links_are_governed():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in MEASURES]
    assert len(keys) == len(set(keys))
    concept_keys = {item.key for item in CONCEPTS}
    for measure in MEASURES:
        linked = engine._measure_concept_keys(measure)
        assert linked
        assert set(linked) <= concept_keys
        assert measure.evidence_requirements
        assert measure.integrity_dimensions
        assert measure.mrv_method_families


def test_expected_measure_families_and_profiles_exist():
    keys = {item.key for item in MEASURES}
    required = {
        "improved-crop-rotation",
        "cover-crop-system",
        "reduced-tillage-system",
        "crop-residue-management",
        "soil-nutrient-management-practice",
        "agroforestry-establishment-management",
        "grassland-restoration-management",
        "woodland-establishment-measure",
        "wetland-restoration-measure",
        "peatland-restoration-rewetting",
    }
    assert required == keys
    families = {item.measure_family for item in MEASURES}
    assert "cropland-soil-carbon" in families
    assert "wetland-and-peatland" in families
    assert "woody-biomass-and-agroforestry" in families


def test_measure_search_filters_land_system_pool_gas_and_text():
    engine = CarbonNatureKnowledgeFoundation()
    peat = engine.measures(q="peatland methane")
    assert peat["schema"] == MEASURE_SCHEMA_VERSION
    assert any(item["key"] == "peatland-restoration-rewetting" for item in peat["measures"])

    cropland_soc = engine.measures(system="cropland", pool="soil-organic-carbon")
    assert cropland_soc["count"] >= 5
    assert all("cropland" in item["applicable_systems"] for item in cropland_soc["measures"])
    assert all("soil-organic-carbon" in item["target_carbon_pools"] for item in cropland_soc["measures"])

    methane = engine.measures(gas="methane")
    keys = {item["key"] for item in methane["measures"]}
    assert "peatland-restoration-rewetting" in keys
    assert "wetland-restoration-measure" in keys
    assert "grassland-restoration-management" in keys


def test_measure_detail_exposes_linked_concepts_without_quantification():
    engine = CarbonNatureKnowledgeFoundation()
    detail = engine.measure("agroforestry-establishment-management")
    assert detail["measure"]["measure_family"] == "woody-biomass-and-agroforestry"
    linked = {item["key"] for item in detail["linked_concepts"]}
    assert "aboveground-biomass" in linked
    assert "soil-organic-carbon" in linked
    assert "additionality" in linked
    assert detail["guardrails"]["quantified_potential_not_provided"] is True
    assert detail["guardrails"]["project_suitability_not_determined"] is True


def test_measure_comparison_is_bounded_and_non_ranking():
    engine = CarbonNatureKnowledgeFoundation()
    comparison = engine.compare_measures(["cover-crop-system", "reduced-tillage-system"])
    assert comparison["measure_keys"] == ["cover-crop-system", "reduced-tillage-system"]
    assert "cropland" in comparison["common"]["applicable_systems"]
    assert "soil-organic-carbon" in comparison["common"]["target_carbon_pools"]
    assert comparison["governance"]["ranking_performed"] is False
    assert comparison["governance"]["preferred_measure_selected"] is False
    assert comparison["governance"]["quantified_climate_benefit_compared"] is False


def test_research_context_includes_measure_matches_but_not_domain_reasoning():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.research_context("cover crops soil carbon")
    assert result["subsystem_version"] == DOMAIN_VERSION
    assert any(item["key"] == "cover-crop-system" for item in result["measures"])
    assert result["handoff"]["research_librarian_ready"] is True
    assert result["handoff"]["measure_registry_context_enabled"] is True
    assert result["handoff"]["domain_aware_reasoning_enabled"] is False
    assert result["guardrails"]["measure_match_is_not_project_suitability"] is True
    assert result["guardrails"]["quantified_sequestration_not_inferred"] is True


def test_fingerprints_are_deterministic_for_same_content():
    engine = CarbonNatureKnowledgeFoundation()
    a = engine.manifest()["content_fingerprint"]
    b = engine.manifest()["content_fingerprint"]
    assert a == b and len(a) == 64
    c = engine.measure("peatland-restoration-rewetting")["content_fingerprint"]
    d = engine.measure("peatland-restoration-rewetting")["content_fingerprint"]
    assert c == d and len(c) == 64
    e = engine.compare_measures(["cover-crop-system", "reduced-tillage-system"])["content_fingerprint"]
    f = engine.compare_measures(["cover-crop-system", "reduced-tillage-system"])["content_fingerprint"]
    assert e == f and len(e) == 64
