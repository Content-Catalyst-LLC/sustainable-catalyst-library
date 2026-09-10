from app.carbon_nature import CarbonNatureKnowledgeFoundation, CONCEPTS, RELATIONSHIPS, DOMAIN_VERSION


def test_manifest_identity_and_guardrails():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.manifest()
    assert manifest["schema"] == "sc-carbon-nature-knowledge-foundation/1.0"
    assert manifest["subsystem"]["version"] == "0.1.0"
    assert manifest["subsystem"]["primary_home"] == "Sustainable Catalyst Library"
    assert manifest["subsystem"]["backend_version"] == "2.2.0"
    governance = manifest["governance"]
    assert governance["carbon_credit_issuance"] is False
    assert governance["certification_body"] is False
    assert governance["automatic_nature_positive_score"] is False
    assert governance["soc_calculation_engine"] is False
    assert governance["whole_farm_ghg_calculator"] is False


def test_concept_keys_are_unique_and_foundation_domains_exist():
    keys = [item.key for item in CONCEPTS]
    assert len(keys) == len(set(keys))
    for key in ["afolu", "nature-based-solutions", "carbon-farming", "soil-organic-carbon", "mrv", "policy", "evidence"]:
        assert key in keys


def test_expected_gases_pools_interventions_integrity_and_cobenefits_exist():
    keys = {item.key for item in CONCEPTS}
    required = {
        "carbon-dioxide", "methane", "nitrous-oxide", "soil-organic-carbon", "aboveground-biomass", "belowground-biomass",
        "cover-crops", "reduced-tillage", "agroforestry-intervention", "peatland-restoration",
        "additionality", "leakage", "permanence", "uncertainty", "biodiversity", "water-security",
    }
    assert required <= keys


def test_relationships_have_no_dangling_concepts():
    keys = {item.key for item in CONCEPTS}
    for rel in RELATIONSHIPS:
        assert rel.subject in keys
        assert rel.object in keys
        assert rel.inference_allowed is False


def test_soc_search_and_concept_context():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.concepts(q="soil organic carbon")
    keys = {item["key"] for item in result["concepts"]}
    assert "soil-organic-carbon" in keys
    detail = engine.concept("soil-organic-carbon")
    assert detail["concept"]["concept_type"] == "carbon-pool"
    assert detail["neighbors"]


def test_research_context_is_bounded_and_does_not_claim_domain_reasoning():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.research_context("peatland restoration permanence uncertainty")
    assert result["subsystem_version"] == DOMAIN_VERSION
    assert any(item["key"] == "peatland-restoration" for item in result["concepts"])
    assert result["handoff"]["research_librarian_ready"] is True
    assert result["handoff"]["domain_aware_reasoning_enabled"] is False
    assert result["guardrails"]["concept_match_is_not_evidence"] is True
    assert result["guardrails"]["project_credit_eligibility_not_determined"] is True


def test_fingerprints_are_deterministic_for_same_content():
    engine = CarbonNatureKnowledgeFoundation()
    a = engine.manifest()["content_fingerprint"]
    b = engine.manifest()["content_fingerprint"]
    assert a == b and len(a) == 64
    c = engine.research_context("cover crops soil carbon")["content_fingerprint"]
    d = engine.research_context("cover crops soil carbon")["content_fingerprint"]
    assert c == d and len(c) == 64
