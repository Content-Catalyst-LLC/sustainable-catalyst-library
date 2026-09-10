from app.carbon_nature import (
    CarbonNatureKnowledgeFoundation,
    DOMAIN_VERSION,
    EVIDENCE_GRAPH_EDGES,
    EVIDENCE_GRAPH_SCHEMA_VERSION,
    EVIDENCE_RECORDS,
    EVIDENCE_SCHEMA_VERSION,
    METHODOLOGIES,
    METHODOLOGY_SCHEMA_VERSION,
)


def test_manifest_advances_to_evidence_methodology_graph():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.manifest()
    assert manifest["schema"] == "sc-carbon-nature-knowledge-foundation/1.2"
    assert manifest["subsystem"]["version"] == "0.3.0"
    assert manifest["subsystem"]["release"] == "Carbon Evidence & Methodology Graph"
    assert manifest["subsystem"]["backend_version"] == "2.4.0"
    assert manifest["coverage"]["measure_count"] == 10
    assert manifest["coverage"]["methodology_count"] == 7
    assert manifest["coverage"]["evidence_record_count"] == 4
    assert manifest["coverage"]["evidence_graph_edge_count"] >= 150
    g = manifest["governance"]
    assert g["automatic_methodology_selection"] is False
    assert g["automatic_methodology_eligibility_determination"] is False
    assert g["automatic_evidence_quality_grade"] is False
    assert g["automatic_claim_validation"] is False
    assert g["quantified_sequestration_potential"] is False


def test_methodology_registry_has_stable_unique_links():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in METHODOLOGIES]
    assert len(keys) == len(set(keys))
    for item in METHODOLOGIES:
        assert item.method_family in engine._concepts
        assert set(item.applicable_measure_keys) <= set(engine._measures)
        assert set(item.concept_keys) <= set(engine._concepts)
        assert item.required_inputs
        assert item.uncertainty_requirements
        assert item.verification_requirements
        assert item.method_status == "review-required"


def test_evidence_registry_preserves_identity_version_and_context():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in EVIDENCE_RECORDS]
    assert len(keys) == len(set(keys))
    guidance = engine.evidence_record("eu-carbon-farming-technical-guidance-2021")
    assert guidance["evidence"]["publication_year"] == 2021
    assert guidance["evidence"]["jurisdiction"] == "European Union"
    assert guidance["guardrails"]["record_presence_is_not_endorsement"] is True
    ipcc = engine.evidence_records(q="IPCC")
    assert ipcc["schema"] == EVIDENCE_SCHEMA_VERSION
    assert ipcc["count"] == 2
    assert ipcc["guardrails"]["reference_seed_is_not_current_regulatory_entitlement"] is True


def test_methodology_search_is_bounded_and_nonselecting():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.methodologies(measure="peatland-restoration-rewetting")
    assert result["schema"] == METHODOLOGY_SCHEMA_VERSION
    keys = {item["key"] for item in result["methodologies"]}
    assert "wetland-multigas-monitoring" in keys
    assert "whole-system-ghg-accounting" in keys
    assert result["guardrails"]["automatic_methodology_selection"] is False
    detail = engine.methodology("wetland-multigas-monitoring")
    assert detail["guardrails"]["project_eligibility_not_determined"] is True
    assert any(item["key"] == "peatland-restoration-rewetting" for item in detail["linked_measures"])


def test_measure_detail_now_carries_evidence_methodology_context():
    engine = CarbonNatureKnowledgeFoundation()
    detail = engine.measure("cover-crop-system")
    method_keys = {item["key"] for item in detail["linked_methodologies"]}
    evidence_keys = {item["key"] for item in detail["linked_evidence"]}
    assert "soc-direct-measurement" in method_keys
    assert "whole-system-ghg-accounting" in method_keys
    assert "eu-carbon-farming-technical-guidance-2021" in evidence_keys


def test_graph_edges_are_typed_noninferential_and_resolvable():
    engine = CarbonNatureKnowledgeFoundation()
    assert EVIDENCE_GRAPH_EDGES
    registries = {
        "concept": set(engine._concepts),
        "measure": set(engine._measures),
        "methodology": set(engine._methodologies),
        "evidence": set(engine._evidence),
    }
    for edge in EVIDENCE_GRAPH_EDGES:
        assert edge.subject_key in registries[edge.subject_type]
        assert edge.object_key in registries[edge.object_type]
        assert edge.inference_allowed is False


def test_graph_filter_and_neighborhood_are_deterministic():
    engine = CarbonNatureKnowledgeFoundation()
    graph = engine.evidence_graph(node_type="methodology", node_key="soc-direct-measurement")
    assert graph["schema"] == EVIDENCE_GRAPH_SCHEMA_VERSION
    assert graph["edge_count"] > 0
    assert graph["governance"]["edge_inference_enabled"] is False
    assert all(
        edge["subject_key"] == "soc-direct-measurement" or edge["object_key"] == "soc-direct-measurement"
        for edge in graph["edges"]
    )
    a = engine.evidence_graph_neighborhood("cover-crop-system")
    b = engine.evidence_graph_neighborhood("cover-crop-system")
    assert a["content_fingerprint"] == b["content_fingerprint"]
    assert any(node["node_type"] == "methodology" for node in a["nodes"])
    assert any(node["node_type"] == "evidence" for node in a["nodes"])


def test_research_context_includes_graph_without_domain_reasoning():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.research_context("cover crops soil carbon")
    assert result["schema"] == "sc-carbon-nature-research-context/1.2"
    assert result["subsystem_version"] == DOMAIN_VERSION
    assert result["methodologies"]
    assert result["evidence"]
    assert result["evidence_graph_edges"]
    assert result["handoff"]["evidence_methodology_graph_context_enabled"] is True
    assert result["handoff"]["domain_aware_reasoning_enabled"] is False
    assert result["guardrails"]["evidence_match_is_not_claim_validation"] is True
    assert result["guardrails"]["methodology_match_is_not_methodology_eligibility"] is True


def test_fingerprints_remain_deterministic():
    engine = CarbonNatureKnowledgeFoundation()
    assert engine.manifest()["content_fingerprint"] == engine.manifest()["content_fingerprint"]
    assert engine.methodology("soc-direct-measurement")["content_fingerprint"] == engine.methodology("soc-direct-measurement")["content_fingerprint"]
    assert engine.evidence_record("ipcc-2006-guidelines-volume-4-afolu")["content_fingerprint"] == engine.evidence_record("ipcc-2006-guidelines-volume-4-afolu")["content_fingerprint"]
