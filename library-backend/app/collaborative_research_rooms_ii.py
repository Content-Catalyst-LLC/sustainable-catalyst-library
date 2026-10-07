from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.35.0"
BACKEND_VERSION = "3.35.0"
WEB_VERSION = "2.35.0"
SDK_VERSION = "1.35.0"

CONTRACT = "sc-library-collaborative-research-rooms-ii/1.0"
READINESS_CONTRACT = "sc-library-collaborative-research-rooms-ii-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-collaborative-research-rooms-ii-bootstrap/1.0"
ROOM_CONTRACT = "sc-library-collaborative-research-room/2.0"
MEMBERSHIP_CONTRACT = "sc-library-research-room-membership/2.0"
OBJECT_MANIFEST_CONTRACT = "sc-library-research-room-object-manifest/2.0"
ACTIVITY_CONTRACT = "sc-library-research-room-activity-stream/2.0"
REVIEW_REQUEST_CONTRACT = "sc-library-research-room-review-request/2.0"
REVIEW_DECISION_CONTRACT = "sc-library-research-room-review-decision/2.0"
ACCESS_AUDIT_CONTRACT = "sc-library-research-room-access-audit/2.0"
HANDOFF_CONTRACT = "sc-library-research-room-exchange-handoff/2.0"
EXPORT_CONTRACT = "sc-library-research-room-export/2.0"

PORTABLE_EXCHANGE_SCHEMA = "sc-library-research-object-exchange/1.0"

ROLE_PERMISSIONS = {
    "owner": ["view", "share", "comment", "edit", "review", "manage-members", "manage-room", "export", "handoff"],
    "steward": ["view", "share", "comment", "edit", "review", "manage-members", "export", "handoff"],
    "editor": ["view", "share", "comment", "edit", "review", "export", "handoff"],
    "reviewer": ["view", "comment", "review", "export"],
    "contributor": ["view", "share", "comment", "edit", "export"],
    "viewer": ["view", "export"],
}
VALID_ROLES = set(ROLE_PERMISSIONS)
REVIEW_DECISIONS = {"approve", "request-changes", "reject", "abstain"}
MAX_MEMBERS = 500
MAX_OBJECTS = 5000
MAX_EVENTS = 20000
MAX_REVIEWERS = 100


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return hashlib.sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    if isinstance(value, list):
        return list(value)
    if value is None:
        return []
    return [value]


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def _member_id(member: dict[str, Any]) -> str | None:
    return _clean(member.get("member_id") or member.get("user_id") or member.get("id") or member.get("email"))


def guardrails() -> dict[str, Any]:
    return {
        "rooms_layer_is_identity_authority": False,
        "rooms_layer_is_project_persistence_authority": False,
        "rooms_layer_is_research_object_authority": False,
        "rooms_layer_is_citation_authority": False,
        "rooms_layer_is_evidence_authority": False,
        "rooms_layer_is_truth_authority": False,
        "rooms_layer_is_publication_authority": False,
        "room_membership_changes_source_authority": False,
        "room_object_reference_copies_or_rewrites_source_payload": False,
        "room_activity_implies_evidence_strength": False,
        "room_activity_implies_truth": False,
        "room_consensus_implies_truth": False,
        "review_approval_implies_truth": False,
        "review_approval_implies_scientific_validity": False,
        "review_approval_applies_to_subject_automatically": False,
        "access_audit_is_network_authentication": False,
        "access_audit_is_identity_verification": False,
        "automatic_membership": False,
        "automatic_role_escalation": False,
        "automatic_object_mutation": False,
        "automatic_review_decision": False,
        "automatic_external_delivery": False,
        "automatic_import": False,
        "automatic_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "explicit_membership_and_roles_required": True,
        "explicit_room_object_references_required": True,
        "human_review_decisions_are_explicit": True,
        "originating_authority_is_preserved": True,
        "portable_exchange_authority_is_preserved": True,
        "server_side_room_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "deterministic-room-manifest",
        "explicit-membership-and-role-matrix",
        "room-scoped-research-object-reference-manifest",
        "human-authored-collaboration-activity-stream",
        "explicit-review-request-and-decision-packets",
        "room-scope-access-audit",
        "portable-research-object-exchange-handoff",
        "deterministic-portable-room-export",
    ]
    basis = {"resources": resources, "roles": ROLE_PERMISSIONS, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "system_id": "collaborative-research-rooms-ii:" + _fp(basis)[:32],
        "system_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/rooms",
        "api_base": "/api/library/v1/research-rooms",
        "room_schema": ROOM_CONTRACT,
        "portable_exchange_schema": PORTABLE_EXCHANGE_SCHEMA,
        "roles": {k: list(v) for k, v in ROLE_PERMISSIONS.items()},
        "resources": resources,
        "limits": {"members": MAX_MEMBERS, "objects": MAX_OBJECTS, "events": MAX_EVENTS, "reviewers_per_request": MAX_REVIEWERS},
        "next_release": "6.36.0",
        "next_release_name": "Library–Librarian Unified Research Intelligence",
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "ready",
        "ready": True,
        "blocking": [],
        "degraded": [],
        "authority": "python-backend-composition",
        "room_manifest_ready": True,
        "membership_roles_ready": True,
        "room_object_manifest_ready": True,
        "activity_stream_ready": True,
        "review_packets_ready": True,
        "access_audit_ready": True,
        "portable_exchange_handoff_ready": True,
        "portable_room_export_ready": True,
        "server_side_room_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
        "guardrails": guardrails(),
    }


def bootstrap() -> dict[str, Any]:
    return {
        "schema": BOOTSTRAP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "route": "/research/rooms",
        "readiness": readiness(),
        "roles": {k: list(v) for k, v in ROLE_PERMISSIONS.items()},
        "operations": [
            "create-room",
            "membership",
            "object-manifest",
            "activity-stream",
            "review-request",
            "review-decision",
            "access-audit",
            "exchange-handoff",
            "export",
        ],
        "browser_storage_key": "sc-library-collaborative-research-rooms-v2",
        "guardrails": guardrails(),
    }


def _normalize_member(raw: Any, *, default_role: str = "viewer") -> dict[str, Any]:
    member = _dict(raw)
    member_id = _member_id(member)
    if not member_id:
        raise ValueError("member_id/user_id/id/email is required for every member")
    role = (_clean(member.get("role")) or default_role).lower()
    if role not in VALID_ROLES:
        raise ValueError(f"unsupported room role: {role}")
    return {
        "member_id": member_id,
        "name": _clean(member.get("name") or member.get("display_name")),
        "role": role,
        "permissions": list(ROLE_PERMISSIONS[role]),
        "identity_authority": _clean(member.get("identity_authority")) or "external-or-library-identity-authority",
        "explicit": True,
        "metadata": _dict(member.get("metadata")),
    }


def _room(payload: dict[str, Any]) -> dict[str, Any]:
    raw = _dict(payload.get("room"))
    if not raw and payload.get("schema") == ROOM_CONTRACT:
        raw = dict(payload)
    return raw


def create_room(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    title = _clean(payload.get("title"))
    if not title:
        raise ValueError("title is required")
    owner_raw = _dict(payload.get("owner"))
    if not owner_raw:
        raise ValueError("owner is required")
    owner_raw["role"] = "owner"
    owner = _normalize_member(owner_raw, default_role="owner")

    raw_members = _list(payload.get("members"))
    if len(raw_members) > MAX_MEMBERS:
        raise ValueError(f"member limit exceeded: {MAX_MEMBERS}")
    members = [owner]
    seen = {owner["member_id"]}
    for raw in raw_members:
        member = _normalize_member(raw)
        if member["member_id"] in seen:
            if member["member_id"] == owner["member_id"]:
                continue
            raise ValueError(f"duplicate member_id: {member['member_id']}")
        seen.add(member["member_id"])
        members.append(member)

    basis = {
        "title": title,
        "description": _clean(payload.get("description")),
        "project_id": _clean(payload.get("project_id")),
        "owner": owner,
        "members": members,
        "scope": _clean(payload.get("scope")),
        "metadata": _dict(payload.get("metadata")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": ROOM_CONTRACT,
        "room_id": "research-room:" + fingerprint[:32],
        "room_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": title,
        "description": _clean(payload.get("description")),
        "project_id": _clean(payload.get("project_id")),
        "scope": _clean(payload.get("scope")),
        "owner_member_id": owner["member_id"],
        "members": members,
        "member_count": len(members),
        "metadata": _dict(payload.get("metadata")),
        "state": "room-manifest",
        "persisted": False,
        "identity_verified_by_room_layer": False,
        "source_authority_changed": False,
        "guardrails": guardrails(),
    }


def membership_matrix(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    members = [_normalize_member(x) for x in _list(room.get("members"))]
    ids = [x["member_id"] for x in members]
    duplicates = sorted({x for x in ids if ids.count(x) > 1})
    findings = [{"kind": "duplicate-member-id", "member_id": x} for x in duplicates]
    owner_id = _clean(room.get("owner_member_id"))
    if owner_id and owner_id not in set(ids):
        findings.append({"kind": "owner-not-present-in-membership", "member_id": owner_id})
    basis = {"room_id": room.get("room_id"), "members": members, "findings": findings}
    return {
        "schema": MEMBERSHIP_CONTRACT,
        "membership_id": "research-room-membership:" + _fp(basis)[:32],
        "membership_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "members": members,
        "member_count": len(members),
        "findings": findings,
        "finding_count": len(findings),
        "valid": not findings,
        "automatic_membership": False,
        "automatic_role_escalation": False,
        "identity_verified_by_room_layer": False,
        "guardrails": guardrails(),
    }


def object_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    raw_objects = _list(payload.get("objects") or payload.get("object_refs"))
    if len(raw_objects) > MAX_OBJECTS:
        raise ValueError(f"object limit exceeded: {MAX_OBJECTS}")
    objects = []
    seen = set()
    for index, raw in enumerate(raw_objects):
        obj = _dict(raw)
        object_id = _clean(obj.get("object_id") or obj.get("source_object_id") or obj.get("portable_object_id") or obj.get("id"))
        if not object_id:
            raise ValueError(f"object reference {index} requires object_id/source_object_id/portable_object_id/id")
        if object_id in seen:
            raise ValueError(f"duplicate room object reference: {object_id}")
        seen.add(object_id)
        objects.append({
            "object_id": object_id,
            "object_type": _clean(obj.get("object_type") or obj.get("type")) or "research-object",
            "source_schema": _clean(obj.get("source_schema") or obj.get("schema")),
            "source_authority": _clean(obj.get("source_authority") or obj.get("authority")) or "originating-authority-unspecified",
            "portable_object_id": _clean(obj.get("portable_object_id")),
            "relation": _clean(obj.get("relation")) or "shared-in-room",
            "access": _clean(obj.get("access")) or "room-members",
            "metadata": _dict(obj.get("metadata")),
            "explicit": True,
            "payload_copied": False,
            "source_authority_changed": False,
        })
    basis = {"room_id": room.get("room_id"), "objects": objects}
    return {
        "schema": OBJECT_MANIFEST_CONTRACT,
        "manifest_id": "research-room-object-manifest:" + _fp(basis)[:32],
        "manifest_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "objects": objects,
        "object_count": len(objects),
        "explicit_references_only": True,
        "source_payloads_copied": False,
        "source_authority_changed": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def activity_stream(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    raw_events = _list(payload.get("events"))
    if len(raw_events) > MAX_EVENTS:
        raise ValueError(f"event limit exceeded: {MAX_EVENTS}")
    events = []
    for index, raw in enumerate(raw_events):
        event = _dict(raw)
        actor_id = _clean(event.get("actor_id") or event.get("member_id"))
        kind = _clean(event.get("kind") or event.get("event_type"))
        occurred_at = _clean(event.get("occurred_at") or event.get("time"))
        if not actor_id or not kind or not occurred_at:
            raise ValueError(f"event {index} requires actor_id, kind, and occurred_at")
        body = {
            "actor_id": actor_id,
            "kind": kind,
            "occurred_at": occurred_at,
            "object_id": _clean(event.get("object_id")),
            "text": _clean(event.get("text") or event.get("message")),
            "metadata": _dict(event.get("metadata")),
        }
        events.append({
            "event_id": _clean(event.get("event_id")) or "research-room-event:" + _fp(body)[:32],
            **body,
            "human_or_external_asserted": True,
            "evidence_strength": None,
            "truth_status": None,
        })
    basis = {"room_id": room.get("room_id"), "events": events}
    return {
        "schema": ACTIVITY_CONTRACT,
        "activity_stream_id": "research-room-activity:" + _fp(basis)[:32],
        "activity_stream_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "events": events,
        "event_count": len(events),
        "activity_implies_evidence_strength": False,
        "activity_implies_truth": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def review_request(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    subject = _dict(payload.get("subject") or payload.get("subject_ref"))
    subject_id = _clean(subject.get("object_id") or subject.get("source_object_id") or subject.get("id"))
    requested_by = _clean(payload.get("requested_by") or payload.get("requester_id"))
    reviewers = [_clean(x if not isinstance(x, dict) else _member_id(x)) for x in _list(payload.get("reviewers"))]
    reviewers = [x for x in reviewers if x]
    if not subject_id:
        raise ValueError("subject object_id is required")
    if not requested_by:
        raise ValueError("requested_by is required")
    if not reviewers:
        raise ValueError("at least one reviewer is required")
    if len(reviewers) > MAX_REVIEWERS:
        raise ValueError(f"reviewer limit exceeded: {MAX_REVIEWERS}")
    basis = {
        "room_id": room.get("room_id"),
        "subject": subject,
        "requested_by": requested_by,
        "reviewers": reviewers,
        "criteria": _list(payload.get("criteria")),
        "due_at": _clean(payload.get("due_at")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": REVIEW_REQUEST_CONTRACT,
        "review_request_id": "research-room-review-request:" + fingerprint[:32],
        "review_request_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "subject": subject,
        "subject_id": subject_id,
        "requested_by": requested_by,
        "reviewers": reviewers,
        "criteria": _list(payload.get("criteria")),
        "instructions": _clean(payload.get("instructions")),
        "due_at": _clean(payload.get("due_at")),
        "state": "requested",
        "automatic_assignment": False,
        "automatic_decision": False,
        "applied_to_subject": False,
        "guardrails": guardrails(),
    }


def review_decision(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    request = _dict(payload.get("review_request") or payload.get("request"))
    if request.get("schema") != REVIEW_REQUEST_CONTRACT:
        raise ValueError("valid review_request is required")
    decision = (_clean(payload.get("decision")) or "abstain").lower()
    if decision not in REVIEW_DECISIONS:
        raise ValueError(f"unsupported review decision: {decision}")
    decided_by = _clean(payload.get("decided_by") or payload.get("reviewer_id"))
    if not decided_by:
        raise ValueError("decided_by is required")
    basis = {
        "review_request_id": request.get("review_request_id"),
        "decision": decision,
        "decided_by": decided_by,
        "rationale": _clean(payload.get("rationale")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": REVIEW_DECISION_CONTRACT,
        "review_decision_id": "research-room-review-decision:" + fingerprint[:32],
        "review_decision_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": request.get("room_id"),
        "review_request_id": request.get("review_request_id"),
        "subject_id": request.get("subject_id"),
        "decision": decision,
        "decided_by": decided_by,
        "rationale": _clean(payload.get("rationale")),
        "human_asserted": True,
        "automatic": False,
        "applied_to_subject": False,
        "decision_implies_truth": False,
        "decision_implies_scientific_validity": False,
        "guardrails": guardrails(),
    }


def access_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    actor_id = _clean(payload.get("actor_id") or payload.get("member_id"))
    action = (_clean(payload.get("action")) or "view").lower()
    if not actor_id:
        raise ValueError("actor_id is required")
    members = [_normalize_member(x) for x in _list(room.get("members"))]
    member = next((x for x in members if x["member_id"] == actor_id), None)
    allowed = bool(member and action in set(member["permissions"]))
    basis = {"room_id": room.get("room_id"), "actor_id": actor_id, "action": action, "role": member.get("role") if member else None, "allowed": allowed}
    return {
        "schema": ACCESS_AUDIT_CONTRACT,
        "audit_id": "research-room-access-audit:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "actor_id": actor_id,
        "action": action,
        "member_found": member is not None,
        "role": member.get("role") if member else None,
        "allowed_by_room_role_matrix": allowed,
        "network_authentication_performed": False,
        "identity_verification_performed": False,
        "authorization_enforced_externally": True,
        "guardrails": guardrails(),
    }


def exchange_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        raise ValueError("room is required")
    exchange = _dict(payload.get("exchange"))
    if exchange.get("schema") != PORTABLE_EXCHANGE_SCHEMA:
        raise ValueError("portable research object exchange is required")
    target = _dict(payload.get("target"))
    basis = {
        "room_id": room.get("room_id"),
        "exchange_id": exchange.get("exchange_id"),
        "exchange_fingerprint_sha256": exchange.get("exchange_fingerprint_sha256"),
        "target": target,
        "intent": _clean(payload.get("intent")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_id": "research-room-exchange-handoff:" + fingerprint[:32],
        "handoff_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "room_id": room.get("room_id"),
        "exchange_id": exchange.get("exchange_id"),
        "exchange_fingerprint_sha256": exchange.get("exchange_fingerprint_sha256"),
        "target": target,
        "intent": _clean(payload.get("intent")) or "continue-collaborative-research",
        "originating_authority_preserved": True,
        "automatic_delivery": False,
        "automatic_import": False,
        "automatic_persistence": False,
        "delivered": False,
        "guardrails": guardrails(),
    }


def export_room(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    room = _room(payload)
    if not room:
        room = create_room(payload)
    membership = membership_matrix({"room": room})
    manifest = object_manifest({"room": room, "objects": payload.get("objects") or payload.get("object_refs") or []})
    activity = activity_stream({"room": room, "events": payload.get("events") or []})
    review_requests = [_dict(x) for x in _list(payload.get("review_requests"))]
    review_decisions = [_dict(x) for x in _list(payload.get("review_decisions"))]
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "room": room,
        "membership": membership,
        "object_manifest": manifest,
        "activity_stream": activity,
        "review_requests": review_requests,
        "review_decisions": review_decisions,
        "originating_authority_preserved": True,
        "automatic_import": False,
        "automatic_persistence": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
    basis = {
        "room_fingerprint_sha256": room.get("room_fingerprint_sha256"),
        "membership_fingerprint_sha256": membership.get("membership_fingerprint_sha256"),
        "manifest_fingerprint_sha256": manifest.get("manifest_fingerprint_sha256"),
        "activity_stream_fingerprint_sha256": activity.get("activity_stream_fingerprint_sha256"),
        "review_requests": review_requests,
        "review_decisions": review_decisions,
    }
    body["export_id"] = "research-room-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-collaborative-research-room.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
