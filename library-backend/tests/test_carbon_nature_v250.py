from copy import deepcopy

from app.carbon_nature import (
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


def test_manifest_advances_to_project_object_model_and_provenance():
    engine = CarbonNatureKnowledgeFoundation()
    manifest = engine.manifest()
    assert manifest["schema"] == "sc-carbon-nature-knowledge-foundation/1.3"
    assert manifest["subsystem"]["version"] == "0.4.0"
    assert manifest["subsystem"]["release"] == "Carbon Project Object Model & Provenance"
    assert manifest["subsystem"]["backend_version"] == "2.5.0"
    assert manifest["coverage"]["measure_count"] == 10
    assert manifest["coverage"]["methodology_count"] == 7
    assert manifest["coverage"]["evidence_record_count"] == 4
    assert manifest["coverage"]["project_object_type_count"] == 10
    assert manifest["coverage"]["provenance_event_type_count"] == 9
    assert manifest["coverage"]["project_link_type_count"] == 9
    g = manifest["governance"]
    assert g["project_packet_persistence"] is False
    assert g["automatic_project_claim_generation"] is False
    assert g["automatic_project_eligibility_determination"] is False
    assert g["cryptographic_attestation_or_signature_service"] is False
    assert g["project_object_validation_is_not_verification"] is True
    assert g["quantified_sequestration_potential"] is False


def test_project_object_type_registry_is_unique_and_parent_resolvable():
    engine = CarbonNatureKnowledgeFoundation()
    keys = [item.key for item in PROJECT_OBJECT_TYPES]
    assert len(keys) == len(set(keys)) == 10
    assert {"project", "farm", "parcel", "baseline", "intervention", "observation", "sample", "model-run", "monitoring-record", "verification-record"} == set(keys)
    for item in PROJECT_OBJECT_TYPES:
        assert item.required_payload_fields
        assert set(item.allowed_parent_types) <= set(engine._project_object_types)
        assert item.lifecycle_states == ("draft", "review", "accepted", "superseded")
    parcel = engine.project_object_type("parcel")
    assert parcel["schema"] == "sc-carbon-project-object-type/1.0"
    assert "spatial_reference" in parcel["object_type"]["required_payload_fields"]
    assert parcel["guardrails"]["required_fields_are_structural_not_scientific_sufficiency"] is True


def test_project_object_model_exposes_envelope_provenance_and_links():
    engine = CarbonNatureKnowledgeFoundation()
    model = engine.project_object_model()
    assert model["schema"] == PROJECT_OBJECT_MODEL_SCHEMA_VERSION
    assert model["project_packet"]["schema"] == PROJECT_PACKET_SCHEMA_VERSION
    assert len(model["object_types"]) == 10
    assert len(model["provenance_event_types"]) == 9
    assert len(model["link_types"]) == 9
    assert "content_fingerprint" in model["object_envelope"]["optional_fields"]
    assert model["governance"]["project_packet_persistence"] is False
    assert model["governance"]["fingerprints_are_integrity_checks_not_signatures"] is True
    assert all(item.inference_allowed is False for item in PROJECT_LINK_TYPES)


def test_provenance_event_registry_is_bounded_and_explicit():
    engine = CarbonNatureKnowledgeFoundation()
    result = engine.provenance_event_types()
    assert result["schema"] == PROJECT_PROVENANCE_SCHEMA_VERSION
    assert result["count"] == len(PROVENANCE_EVENT_TYPES) == 9
    assert result["guardrails"]["fingerprint_is_not_digital_signature"] is True
    keys = {item["key"] for item in result["event_types"]}
    assert {"created", "imported", "observed", "sampled", "transformed", "modeled", "reviewed", "verified", "superseded"} == keys


def test_project_packet_template_validates_without_persistence():
    engine = CarbonNatureKnowledgeFoundation()
    template = engine.project_packet_template()
    packet = template["template"]
    assert packet["schema"] == PROJECT_PACKET_SCHEMA_VERSION
    result = engine.validate_project_packet(packet)
    assert result["schema"] == PROJECT_PACKET_VALIDATION_SCHEMA_VERSION
    assert result["valid"] is True
    assert result["project_id"] == "project:example-carbon-project"
    assert result["counts"] == {"objects": 2, "provenance_events": 2, "links": 1}
    assert len(result["object_fingerprints"]) == 2
    assert len(result["event_fingerprints"]) == 2
    assert result["packet_fingerprint"]
    assert result["guardrails"]["packet_not_persisted"] is True
    assert result["guardrails"]["validation_is_not_scientific_verification"] is True


def test_packet_validator_rejects_unknown_measure_and_bad_parent_type():
    engine = CarbonNatureKnowledgeFoundation()
    packet = deepcopy(engine.project_packet_template()["template"])
    packet["objects"].append({
        "object_id": "intervention:bad",
        "object_type": "intervention",
        "project_id": "project:example-carbon-project",
        "version": 1,
        "status": "draft",
        "payload": {"measure_key": "not-a-measure", "start_date": "2026-02-01", "implementation_status": "planned"},
        "source_refs": [], "provenance_refs": ["event:intervention-created"],
        "parent_object_ids": ["project:example-carbon-project"], "evidence_refs": [], "methodology_refs": [], "measure_refs": [],
    })
    packet["provenance"].append({"event_id": "event:intervention-created", "event_type": "created", "object_id": "intervention:bad", "occurred_at": "2026-02-01T00:00:00Z", "actor_ref": "system:test", "source_refs": []})
    result = engine.validate_project_packet(packet)
    assert result["valid"] is False
    codes = {item["code"] for item in result["errors"]}
    assert "unknown-measure-key" in codes
    # intervention may be under project, so switch its parent to an observation to exercise type control.
    packet["objects"].append({
        "object_id": "observation:test",
        "object_type": "observation",
        "project_id": "project:example-carbon-project",
        "version": 1,
        "status": "draft",
        "payload": {"indicator": "soc", "value": 1, "unit": "unit", "observed_at": "2026-02-01T00:00:00Z"},
        "source_refs": [], "provenance_refs": ["event:observation-created"], "parent_object_ids": ["parcel:example-001"],
    })
    packet["provenance"].append({"event_id": "event:observation-created", "event_type": "observed", "object_id": "observation:test", "occurred_at": "2026-02-01T00:00:00Z", "actor_ref": "system:test", "source_refs": []})
    packet["objects"][-2]["parent_object_ids"] = ["observation:test"]
    result = engine.validate_project_packet(packet)
    codes = {item["code"] for item in result["errors"]}
    assert "invalid-parent-type" in codes


def test_provenance_chain_continuity_detects_breaks_and_accepts_correct_chain():
    engine = CarbonNatureKnowledgeFoundation()
    packet = deepcopy(engine.project_packet_template()["template"])
    first = packet["provenance"][0]
    first_fp = _stable_hash(first)
    second = {
        "event_id": "event:project-reviewed",
        "event_type": "reviewed",
        "object_id": "project:example-carbon-project",
        "occurred_at": "2026-01-02T00:00:00+00:00",
        "actor_ref": "reviewer:test",
        "source_refs": [],
        "previous_event_fingerprint": first_fp,
    }
    packet["provenance"].append(second)
    packet["objects"][0]["provenance_refs"].append("event:project-reviewed")
    valid = engine.validate_project_packet(packet)
    assert valid["valid"] is True
    packet["provenance"][-1]["previous_event_fingerprint"] = "broken"
    invalid = engine.validate_project_packet(packet)
    assert invalid["valid"] is False
    assert "provenance-chain-break" in {item["code"] for item in invalid["errors"]}


def test_object_and_event_fingerprint_mismatches_are_rejected():
    engine = CarbonNatureKnowledgeFoundation()
    packet = deepcopy(engine.project_packet_template()["template"])
    packet["objects"][0]["content_fingerprint"] = "not-the-fingerprint"
    packet["provenance"][0]["event_fingerprint"] = "not-the-fingerprint"
    result = engine.validate_project_packet(packet)
    codes = {item["code"] for item in result["errors"]}
    assert "object-fingerprint-mismatch" in codes
    assert "event-fingerprint-mismatch" in codes


def test_research_context_carries_project_object_and_provenance_handoff():
    engine = CarbonNatureKnowledgeFoundation()
    packet = engine.research_context("parcel baseline soil carbon monitoring", limit=10)
    assert packet["schema"] == "sc-carbon-nature-research-context/1.3"
    assert packet["subsystem_version"] == DOMAIN_VERSION == "0.4.0"
    object_keys = {item["key"] for item in packet["project_object_types"]}
    assert "parcel" in object_keys
    assert "baseline" in object_keys
    assert packet["project_object_model"]["stateless_validation_ready"] is True
    assert packet["project_object_model"]["persistence_enabled"] is False
    assert packet["handoff"]["project_object_model_context_enabled"] is True
    assert packet["handoff"]["provenance_model_context_enabled"] is True
    assert packet["guardrails"]["project_object_validation_is_not_verification"] is True
