from app.collaborative_research_rooms_ii import (
    ACCESS_AUDIT_CONTRACT,
    EXPORT_CONTRACT,
    HANDOFF_CONTRACT,
    OBJECT_MANIFEST_CONTRACT,
    REVIEW_DECISION_CONTRACT,
    REVIEW_REQUEST_CONTRACT,
    ROOM_CONTRACT,
    bootstrap,
    contract,
    create_room,
    export_room,
    exchange_handoff,
    access_audit,
    activity_stream,
    membership_matrix,
    object_manifest,
    readiness,
    review_decision,
    review_request,
)


def room_payload():
    return {
        "title": "Collaborative climate research room",
        "description": "Shared room for evidence review",
        "project_id": "research-project:test",
        "owner": {"member_id": "user:owner", "name": "Owner"},
        "members": [
            {"member_id": "user:editor", "name": "Editor", "role": "editor"},
            {"member_id": "user:reviewer", "name": "Reviewer", "role": "reviewer"},
        ],
        "scope": "accelerated weathering evidence",
    }


def test_contract_readiness_and_bootstrap():
    c = contract()
    r = readiness()
    b = bootstrap()
    assert c["library_version"] == "6.35.0"
    assert c["backend_version"] == "3.35.0"
    assert c["route"] == "/research/rooms"
    assert r["ready"] is True
    assert r["server_side_room_persistence"] is False
    assert r["wordpress_required"] is False
    assert b["operations"] == [
        "create-room", "membership", "object-manifest", "activity-stream",
        "review-request", "review-decision", "access-audit", "exchange-handoff", "export",
    ]


def test_room_manifest_is_deterministic_and_explicit():
    a = create_room(room_payload())
    b = create_room(room_payload())
    assert a == b
    assert a["schema"] == ROOM_CONTRACT
    assert a["member_count"] == 3
    assert a["owner_member_id"] == "user:owner"
    assert a["persisted"] is False
    assert a["source_authority_changed"] is False
    roles = {m["member_id"]: m["role"] for m in a["members"]}
    assert roles["user:owner"] == "owner"
    assert roles["user:editor"] == "editor"


def test_membership_and_access_role_matrix_are_non_authentication():
    room = create_room(room_payload())
    m = membership_matrix({"room": room})
    assert m["valid"] is True
    assert m["automatic_membership"] is False
    assert m["automatic_role_escalation"] is False

    allowed = access_audit({"room": room, "actor_id": "user:editor", "action": "edit"})
    denied = access_audit({"room": room, "actor_id": "user:reviewer", "action": "manage-members"})
    assert allowed["schema"] == ACCESS_AUDIT_CONTRACT
    assert allowed["allowed_by_room_role_matrix"] is True
    assert denied["allowed_by_room_role_matrix"] is False
    assert allowed["network_authentication_performed"] is False
    assert allowed["identity_verification_performed"] is False


def test_object_manifest_preserves_source_authority_without_copying_payload():
    room = create_room(room_payload())
    manifest = object_manifest({
        "room": room,
        "objects": [{
            "object_id": "research-package-composition:1",
            "object_type": "research-package",
            "source_schema": "sc-library-research-package-composition/1.0",
            "source_authority": "research-package-composer",
            "portable_object_id": "portable-research-object:1",
        }],
    })
    assert manifest["schema"] == OBJECT_MANIFEST_CONTRACT
    assert manifest["object_count"] == 1
    assert manifest["source_payloads_copied"] is False
    assert manifest["source_authority_changed"] is False
    assert manifest["objects"][0]["source_authority"] == "research-package-composer"


def test_activity_stream_is_descriptive_not_evidence_or_truth():
    room = create_room(room_payload())
    stream = activity_stream({
        "room": room,
        "events": [{
            "actor_id": "user:editor",
            "kind": "comment",
            "occurred_at": "2026-10-07T12:00:00Z",
            "object_id": "analysis:1",
            "text": "Please verify the source provenance.",
        }],
    })
    assert stream["event_count"] == 1
    assert stream["activity_implies_evidence_strength"] is False
    assert stream["activity_implies_truth"] is False
    assert stream["events"][0]["truth_status"] is None


def test_review_request_and_decision_remain_explicit_and_non_mutating():
    room = create_room(room_payload())
    req = review_request({
        "room": room,
        "subject": {"object_id": "analysis:1", "source_authority": "statistical-evidence"},
        "requested_by": "user:editor",
        "reviewers": ["user:reviewer"],
        "criteria": ["provenance", "method clarity"],
    })
    decision = review_decision({
        "review_request": req,
        "decision": "approve",
        "decided_by": "user:reviewer",
        "rationale": "Provenance is explicit.",
    })
    assert req["schema"] == REVIEW_REQUEST_CONTRACT
    assert req["automatic_decision"] is False
    assert req["applied_to_subject"] is False
    assert decision["schema"] == REVIEW_DECISION_CONTRACT
    assert decision["human_asserted"] is True
    assert decision["automatic"] is False
    assert decision["applied_to_subject"] is False
    assert decision["decision_implies_truth"] is False
    assert decision["decision_implies_scientific_validity"] is False


def test_exchange_handoff_and_export_are_non_delivering_and_non_persisting():
    room = create_room(room_payload())
    exchange = {
        "schema": "sc-library-research-object-exchange/1.0",
        "exchange_id": "research-object-exchange:test",
        "exchange_fingerprint_sha256": "abc123",
    }
    handoff = exchange_handoff({
        "room": room,
        "exchange": exchange,
        "target": {"product": "workspace", "authority": "workspace-project-state"},
    })
    assert handoff["schema"] == HANDOFF_CONTRACT
    assert handoff["originating_authority_preserved"] is True
    assert handoff["automatic_delivery"] is False
    assert handoff["automatic_import"] is False
    assert handoff["delivered"] is False

    exported = export_room({
        "room": room,
        "objects": [{"object_id": "analysis:1", "source_authority": "statistical-evidence"}],
        "events": [],
    })
    assert exported["schema"] == EXPORT_CONTRACT
    assert exported["automatic_import"] is False
    assert exported["automatic_persistence"] is False
    assert exported["automatic_truth_promotion"] is False
    assert exported["originating_authority_preserved"] is True
