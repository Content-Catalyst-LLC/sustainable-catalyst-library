from app.library_workspace_research_integration import (
    bootstrap, contract, export_exchange, library_to_workspace_handoff, readiness,
    round_trip_audit, validate_handoff, workspace_result_registration_preview,
)


def sample_handoff():
    return library_to_workspace_handoff({
        "library_project_id": "library-project:1",
        "workspace_project_id": "workspace-project:1",
        "research_question": "What does the evidence show?",
        "source_objects": [
            {"object_id": "investigation:1", "type": "investigation", "authority": "research-investigation"},
            {"object_id": "package:1", "type": "research-package", "authority": "research-package-composer"},
        ],
        "requested_actions": ["open-project", "run-analysis"],
    })


def test_contract_and_readiness():
    c = contract(); r = readiness(); b = bootstrap()
    assert c["route"] == "/research/workspace"
    assert c["next_release"] == "6.29.0"
    assert r["ready"] is True and r["state"] == "ready"
    assert r["live_workspace_transport_certified"] is False
    assert r["database_migration_required"] is False
    assert b["transport"]["automatic_push"] is False
    assert "workspace-result-registration-preview" in b["operations"]


def test_handoff_is_deterministic_and_nonexecuting():
    a = sample_handoff(); b = sample_handoff()
    assert a["packet_fingerprint_sha256"] == b["packet_fingerprint_sha256"]
    assert a["transport"]["workspace_execution_started"] is False
    assert a["persisted"] is False
    assert len(a["source_objects"]) == 2


def test_handoff_validation():
    packet = sample_handoff()
    result = validate_handoff({"packet": packet})
    assert result["valid"] is True
    assert result["workspace_execution_started"] is False


def test_workspace_result_registration_preview():
    preview = workspace_result_registration_preview({
        "library_project_id": "library-project:1",
        "workspace_project_id": "workspace-project:1",
        "source_library_refs": ["investigation:1", "package:1"],
        "workspace_result": {
            "result_id": "result:1", "type": "analysis", "title": "Analysis result",
            "runtime": "python", "artifact_ids": ["artifact:1"],
        },
    })
    assert preview["direction"] == "workspace-to-library-preview"
    assert preview["persisted"] is False and preview["imported"] is False
    assert preview["library_component_preview"]["requires_explicit_signed_write"] is True


def test_round_trip_audit():
    packet = sample_handoff()
    preview = workspace_result_registration_preview({
        "library_project_id": "library-project:1",
        "workspace_project_id": "workspace-project:1",
        "source_library_refs": ["investigation:1"],
        "workspace_result": {"result_id": "result:1", "type": "analysis"},
    })
    audit = round_trip_audit({"handoff_packet": packet, "registration_preview": preview})
    assert audit["consistent"] is True
    assert audit["live_execution_verified"] is False
    assert audit["persistence_verified"] is False


def test_export_is_deterministic():
    packet = sample_handoff()
    preview = workspace_result_registration_preview({
        "library_project_id": "library-project:1", "workspace_project_id": "workspace-project:1",
        "workspace_result": {"result_id": "result:1", "type": "analysis"},
    })
    audit = round_trip_audit({"handoff_packet": packet, "registration_preview": preview})
    a = export_exchange({"handoff_packet": packet, "registration_preview": preview, "round_trip_audit": audit})
    b = export_exchange({"handoff_packet": packet, "registration_preview": preview, "round_trip_audit": audit})
    assert a["sha256"] == b["sha256"]
    assert a["persisted"] is False
