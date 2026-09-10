from copy import deepcopy

from app.carbon_nature import (
    AFOLU_RESEARCH_INTENTS,
    AFOLU_SOURCE_ROLES,
    AFOLU_RESEARCH_GUIDANCE_SCHEMA_VERSION,
    AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION,
    CarbonNatureKnowledgeFoundation,
    DOMAIN_VERSION,
    PROJECT_LINK_TYPES,
    PROJECT_OBJECT_MODEL_SCHEMA_VERSION,
    PROJECT_OBJECT_TYPES,
    PROJECT_PACKET_SCHEMA_VERSION,
    PROJECT_PACKET_VALIDATION_SCHEMA_VERSION,
    PROJECT_PROVENANCE_SCHEMA_VERSION,
    PROVENANCE_EVENT_TYPES,
    _stable_hash,
)


def test_manifest_advances_to_afolu_research_librarian_intelligence():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.manifest()
    assert manifest["schema"] == "sc-carbon-nature-knowledge-foundation/1.4"
    assert manifest["subsystem"]["version"] == "0.5.0"
    assert manifest["subsystem"]["release"] == "AFOLU Research Librarian Intelligence"
    assert manifest["subsystem"]["backend_version"] == "2.6.0"
    assert manifest["coverage"]["measure_count"] == 10
    assert manifest["coverage"]["methodology_count"] == 7
    assert manifest["coverage"]["evidence_record_count"] == 4
    assert manifest["coverage"]["project_object_type_count"] == 10
    assert manifest["coverage"]["provenance_event_type_count"] == 9
    assert manifest["coverage"]["project_link_type_count"] == 9
    assert manifest["coverage"]["research_intent_count"] == len(AFOLU_RESEARCH_INTENTS) == 11
    assert manifest["coverage"]["research_source_role_count"] == len(AFOLU_SOURCE_ROLES) == 8
    assert "afolu-domain-aware-research-guidance" in manifest["capabilities"]
    assert manifest["governance"]["automatic_research_conclusion_generation"] is False
    assert manifest["governance"]["automatic_current_rule_assertion"] is False
    assert manifest["governance"]["research_guidance_is_deterministic_routing"] is True


def test_research_intent_registry_is_unique_and_source_roles_resolve():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in AFOLU_RESEARCH_INTENTS]
    assert len(keys) == len(set(keys)) == 11
    assert {"measure-identification", "viability-assessment", "mrv-methodology", "inventory-accounting", "monetisation-finance", "nature-based-co-benefits", "negative-emissions-scope"} <= set(keys)
    for item in AFOLU_RESEARCH_INTENTS:
        assert item.trigger_terms
        assert item.research_questions
        assert set(item.evidence_roles) <= set(engine._source_roles)


def test_source_role_registry_marks_time_sensitive_sources():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.research_source_roles()
    assert result["count"] == 8
    keyed = {item["key"]: item for item in result["source_roles"]}
    assert keyed["national-inventory-policy"]["freshness_sensitive"] is True
    assert keyed["program-market-rules"]["freshness_sensitive"] is True
    assert keyed["economic-finance-data"]["freshness_sensitive"] is True
    assert keyed["peer-reviewed-research"]["freshness_sensitive"] is False
    assert result["guardrails"]["freshness_flag_requires_current_source_check"] is True


def test_research_librarian_manifest_declares_question_only_project_augmentation():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.research_librarian_manifest()
    assert manifest["schema"] == AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION
    assert manifest["subsystem_version"] == DOMAIN_VERSION == "0.5.0"
    assert manifest["mode"] == "deterministic-domain-research-routing"
    assert manifest["integration"]["project_aware_research_librarian_packet_augmentation"] is True
    assert manifest["integration"]["private_project_notes_sent_to_library_backend"] is False
    assert manifest["integration"]["project_aware_augmentation_sends_question_only"] is True
    assert manifest["integration"]["optional_remote_synthesis_receives_domain_packet"] is False
    assert manifest["guardrails"]["automatic_research_conclusion_generation"] is False


def test_mrv_inventory_query_detects_multiple_intents_and_current_source_review():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_guidance("How should soil carbon MRV sampling be interpreted in a national GHG inventory under IPCC guidance?", limit=12)
    assert packet["schema"] == AFOLU_RESEARCH_GUIDANCE_SCHEMA_VERSION
    keys = [item["key"] for item in packet["detected_intents"]]
    assert "mrv-methodology" in keys
    assert "inventory-accounting" in keys
    assert packet["freshness_review"]["freshness_sensitive_intent"] is True
    assert packet["freshness_review"]["current_source_check_required"] is True
    assert "national-inventory-policy" in packet["source_plan"]["priority_role_keys"]
    assert "authoritative-methodology-guidance" in packet["source_plan"]["priority_role_keys"]
    codes = {item["code"] for item in packet["evidence_gaps"]}
    assert "current-rule-or-data-check-required" in codes
    assert packet["guardrails"]["policy_and_market_freshness_requires_current_sources"] is True


def test_measure_comparison_is_bounded_non_ranking_research_guidance():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_guidance("Compare cover crops versus reduced tillage for soil organic carbon, uncertainty and co-benefits.", limit=12)
    keys = [item["key"] for item in packet["detected_intents"]]
    assert "measure-comparison" in keys
    assert packet["matched_context"]["measure_count"] >= 2
    assert any("comparison-is-not-ranking" == flag for flag in packet["answer_contract"]["caution_flags"])
    assert "rank or declare a measure suitable without project evidence" in packet["answer_contract"]["may_not"]
    assert packet["guardrails"]["measure_match_is_not_project_suitability"] is True


def test_nbs_query_requires_safeguard_and_counterfactual_evidence():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_guidance("What biodiversity, water security and food security co-benefits could a nature-based solution provide?", limit=12)
    keys = [item["key"] for item in packet["detected_intents"]]
    assert "nature-based-co-benefits" in keys
    assert "safeguards-stakeholder-evidence" in packet["source_plan"]["priority_role_keys"]
    assert packet["guardrails"]["co_benefit_is_not_assumed"] is True
    frame = " ".join(packet["research_question_frame"]).lower()
    assert "counterfactual" in frame
    assert "negative" in frame or "distributional" in frame


def test_negative_emissions_query_surfaces_known_registry_gap():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_guidance("Compare natural carbon sequestration with direct air capture and BECCS as negative emissions options.", limit=12)
    keys = [item["key"] for item in packet["detected_intents"]]
    assert "negative-emissions-scope" in keys
    codes = {item["code"] for item in packet["evidence_gaps"]}
    assert "engineered-removal-registry-not-yet-built" in codes
    assert "do-not-fill-missing-registry-with-assumptions" in packet["answer_contract"]["caution_flags"]


def test_project_provenance_query_routes_to_library_workspace_and_project_types():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_guidance("How should a parcel baseline, samples, observations and model run retain provenance?", limit=12)
    keys = [item["key"] for item in packet["detected_intents"]]
    assert "project-provenance" in keys
    targets = {item["target"] for item in packet["handoffs"]}
    assert {"library", "workspace", "research-librarian"} <= targets
    project_types = set(packet["domain_scope"]["project_object_types"])
    assert {"parcel", "baseline", "sample", "observation", "model-run"} & project_types
    assert packet["guardrails"]["project_object_validation_is_not_verification"] is True


def test_research_context_now_embeds_afolu_librarian_packet():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_context("parcel baseline soil carbon monitoring and MRV", limit=10)
    assert packet["schema"] == "sc-carbon-nature-research-context/1.4"
    assert packet["subsystem_version"] == DOMAIN_VERSION == "0.5.0"
    assert packet["handoff"]["domain_aware_reasoning_enabled"] is True
    assert packet["handoff"]["afolu_research_librarian_intelligence_enabled"] is True
    assert packet["research_librarian"]["schema"] == AFOLU_RESEARCH_LIBRARIAN_SCHEMA_VERSION
    assert packet["research_librarian"]["detected_intents"]
    assert packet["research_librarian"]["source_plan"]["roles"]
    assert packet["research_librarian"]["guardrails"]["deterministic_guidance_is_not_research_conclusion"] is True


def test_existing_project_object_registry_is_preserved():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in PROJECT_OBJECT_TYPES]
    assert len(keys) == len(set(keys)) == 10
    assert {"project", "farm", "parcel", "baseline", "intervention", "observation", "sample", "model-run", "monitoring-record", "verification-record"} == set(keys)
    for item in PROJECT_OBJECT_TYPES:
        assert item.required_payload_fields
        assert set(item.allowed_parent_types) <= set(engine._project_object_types)
    model = engine.project_object_model()
    assert model["schema"] == PROJECT_OBJECT_MODEL_SCHEMA_VERSION
    assert model["project_packet"]["schema"] == PROJECT_PACKET_SCHEMA_VERSION
    assert len(model["object_types"]) == 10
    assert model["governance"]["project_packet_persistence"] is False
    assert all(item.inference_allowed is False for item in PROJECT_LINK_TYPES)


def test_existing_project_packet_template_and_validation_are_preserved():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.project_packet_template()["template"]
    result = engine.validate_project_packet(packet)
    assert result["schema"] == PROJECT_PACKET_VALIDATION_SCHEMA_VERSION
    assert result["valid"] is True
    assert result["project_id"] == "project:example-carbon-project"
    assert result["guardrails"]["packet_not_persisted"] is True
    assert result["guardrails"]["validation_is_not_scientific_verification"] is True


def test_existing_provenance_chain_break_detection_is_preserved():
    engine = CarbonNatureKnowledgeFoundation()
    packet = deepcopy(engine.project_packet_template()["template"])
    first = packet["provenance"][0]
    first_fp = _stable_hash(first)
    packet["provenance"].append({
        "event_id": "event:project-reviewed",
        "event_type": "reviewed",
        "object_id": "project:example-carbon-project",
        "occurred_at": "2026-01-02T00:00:00+00:00",
        "actor_ref": "reviewer:test",
        "source_refs": [],
        "previous_event_fingerprint": first_fp,
    })
    packet["objects"][0]["provenance_refs"].append("event:project-reviewed")
    assert engine.validate_project_packet(packet)["valid"] is True
    packet["provenance"][-1]["previous_event_fingerprint"] = "broken"
    invalid = engine.validate_project_packet(packet)
    assert invalid["valid"] is False
    assert "provenance-chain-break" in {item["code"] for item in invalid["errors"]}


def test_empty_guidance_query_is_rejected():
    engine = CarbonNatureKnowledgeFoundation()
    try:
        engine.research_guidance("   ")
    except ValueError as exc:
        assert "query is required" in str(exc)
    else:
        raise AssertionError("empty research guidance query should fail")
