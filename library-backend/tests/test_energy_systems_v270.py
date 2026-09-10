from app.energy_systems import DOMAIN_VERSION, EnergySystemsKnowledgeFoundation


def engine() -> EnergySystemsKnowledgeFoundation:
    return EnergySystemsKnowledgeFoundation()


def test_manifest_identity_and_counts():
    payload = engine().manifest()
    assert payload["ok"] is True
    assert payload["subsystem"]["version"] == "0.1.0"
    assert payload["subsystem"]["backend_version"] == "2.7.0"
    assert payload["counts"] == {
        "concepts": 75,
        "relationships": 63,
        "sources": 6,
        "knowledge_domains": 6,
        "sdg_mappings": 9,
        "handoffs": 7,
    }
    assert len(payload["content_fingerprint"]) == 64


def test_module_scope_is_represented_without_inventing_values():
    e = engine()
    required = {
        "historical-energy-system-evolution",
        "global-energy-importance",
        "energy-resource-estimation",
        "solar-photovoltaics",
        "solar-thermal",
        "bioenergy",
        "hydropower",
        "tidal-energy",
        "wind-energy",
        "wave-energy",
        "soil-carbon",
        "co2-to-energy",
        "forest-ecology",
        "digestate",
        "biochar",
        "biomass-to-oil",
        "energy-balance",
        "cost-benefit-analysis",
        "cost-efficiency-analysis",
    }
    found = {item["key"] for item in e.concepts(limit=250)["items"]}
    assert required <= found
    guardrails = e.manifest()["guardrails"]
    assert guardrails["conversion_factors_activated"] is False
    assert guardrails["energy_indicator_calculation_activated"] is False
    assert guardrails["scenario_modeling_activated"] is False


def test_source_vintage_and_numeric_boundaries_are_explicit():
    sources = {item["key"]: item for item in engine().sources()["items"]}
    assert sources["vera-langlois-2007"]["year"] == 2007
    assert sources["undesa-scp-2010"]["year"] == 2010
    assert sources["carbon-trust-conversion-2020"]["year"] == 2020
    assert sources["carbon-trust-conversion-2020"]["numeric_status"] == "inactive-historical-numeric-source"
    assert engine().manifest()["guardrails"]["historical_source_is_not_current_state"] is True


def test_sdg_15_coverage_is_not_inferred():
    sdgs = {item["goal"]: item for item in engine().manifest()["sdg_mappings"]}
    assert sdgs[14]["coverage"] == 3
    assert sdgs[15]["coverage"] is None
    assert "does not show a coverage value" in sdgs[15]["note"]


def test_relationships_are_non_inferential():
    rows = engine().relationships(limit=500)["items"]
    assert rows
    assert all(row["inference_allowed"] is False for row in rows)
    zero_impact = [row for row in rows if row["predicate"] == "does-not-imply-absence-of"]
    assert zero_impact and zero_impact[0]["object"] == "ecosystem-impact"


def test_carbon_nature_handoffs_only_claim_existing_targets():
    rows = {item["key"]: item for item in engine().handoffs()["items"]}
    assert rows["soil-carbon-to-carbon-nature"]["status"] == "available"
    assert rows["soil-carbon-to-carbon-nature"]["target_refs"] == ["soil-organic-carbon"]
    assert rows["forest-to-carbon-nature"]["target_refs"] == ["forest-woodland"]
    assert rows["bioenergy-carbon-nature-extension"]["status"] == "planned-extension"
    assert rows["bioenergy-carbon-nature-extension"]["target_refs"] == []


def test_concept_search_and_source_filtering():
    e = engine()
    solar = e.concepts(q="solar", limit=250)
    assert {row["key"] for row in solar["items"]} >= {"solar-energy", "solar-photovoltaics", "solar-thermal"}
    ucd = e.concepts(source="ucd-module-sustainable-energy", limit=250)
    assert ucd["count"] > 10
    assert all("ucd-module-sustainable-energy" in row["source_keys"] for row in ucd["items"])


def test_domain_version_constant():
    assert DOMAIN_VERSION == "0.1.0"
