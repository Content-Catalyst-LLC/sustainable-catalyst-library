from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.28.0"
BACKEND_VERSION = "3.28.0"
WEB_VERSION = "2.28.0"
SDK_VERSION = "1.28.0"

CONTRACT = "sc-library-workspace-research-integration/1.0"
READINESS_CONTRACT = "sc-library-workspace-research-integration-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-workspace-research-integration-bootstrap/1.0"
HANDOFF_CONTRACT = "sc-library-workspace-research-handoff/1.0"
RESULT_PREVIEW_CONTRACT = "sc-library-workspace-result-registration-preview/1.0"
ROUND_TRIP_CONTRACT = "sc-library-workspace-round-trip-audit/1.0"
EXPORT_CONTRACT = "sc-library-workspace-research-exchange-export/1.0"

LIBRARY_OBJECT_TYPES = (
    "research-project", "research-question", "investigation", "source", "claim", "evidence",
    "dataset", "statistical-result", "place", "event", "annotation", "citation", "synthesis",
    "research-package", "publication", "knowledge-graph", "artifact",
)
WORKSPACE_RESULT_TYPES = (
    "analysis", "notebook", "dataset", "table", "figure", "model", "simulation", "calculation",
    "graph", "report", "artifact", "other",
)
REQUESTED_ACTIONS = (
    "open-project", "open-notebook", "run-analysis", "run-calculation", "run-simulation",
    "inspect-data", "visualize", "continue-research",
)


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return hashlib.sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, list) else []


def guardrails() -> dict[str, Any]:
    return {
        "integration_is_new_library_object_authority": False,
        "integration_is_workspace_execution_authority": False,
        "integration_is_workspace_persistence_authority": False,
        "integration_is_library_catalog_persistence_authority": False,
        "integration_is_library_research_state_persistence_authority": False,
        "library_originating_authorities_remain_authoritative": True,
        "workspace_execution_authority_remains_workspace": True,
        "workspace_result_payload_implies_research_validity": False,
        "workspace_result_payload_implies_evidence_truth": False,
        "workspace_result_payload_implies_reproducibility": False,
        "workspace_result_registration_is_preview_only": True,
        "handoff_packet_executes_workspace_automatically": False,
        "handoff_packet_creates_workspace_project_automatically": False,
        "handoff_packet_persists_library_state_automatically": False,
        "automatic_cross_product_push_delivery": False,
        "automatic_result_import": False,
        "automatic_artifact_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "live_workspace_transport_certified": False,
        "live_round_trip_execution_certified": False,
        "server_side_exchange_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "library-to-workspace-handoff-packet",
        "workspace-result-registration-preview",
        "typed-library-object-references",
        "typed-workspace-result-references",
        "project-correlation",
        "provenance-preservation",
        "authority-boundary-manifest",
        "round-trip-audit",
        "portable-exchange-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "integration_id": "library-workspace-research-integration:" + _fp(basis)[:32],
        "integration_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/workspace",
        "api_base": "/api/library/v1/workspace-integration",
        "workspace_contract": "sc-workspace-research-exchange/1.0",
        "resources": resources,
        "library_object_types": list(LIBRARY_OBJECT_TYPES),
        "workspace_result_types": list(WORKSPACE_RESULT_TYPES),
        "requested_actions": list(REQUESTED_ACTIONS),
        "next_release": "6.29.0",
        "next_release_name": "Cross-Product Research Handoff & Contract Certification",
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
        "library_exchange_contract_ready": True,
        "workspace_consumer_contract_defined": True,
        "workspace_execution_authority_preserved": True,
        "library_object_authorities_preserved": True,
        "result_registration_preview_ready": True,
        "round_trip_audit_ready": True,
        "live_workspace_transport_certified": False,
        "live_round_trip_execution_certified": False,
        "server_side_exchange_persistence": False,
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
        "route": "/research/workspace",
        "readiness": readiness(),
        "operations": [
            "library-to-workspace-handoff",
            "validate-handoff",
            "workspace-result-registration-preview",
            "round-trip-audit",
            "export",
        ],
        "library_object_types": list(LIBRARY_OBJECT_TYPES),
        "workspace_result_types": list(WORKSPACE_RESULT_TYPES),
        "requested_actions": list(REQUESTED_ACTIONS),
        "browser_storage_key": "sc-library-workspace-research-integration-v1",
        "transport": {
            "mode": "explicit-export-or-consumer-pull",
            "automatic_push": False,
            "live_transport_certified": False,
            "certification_release": "6.29.0",
        },
        "guardrails": guardrails(),
    }


def _normalize_library_object(raw: Any, index: int) -> dict[str, Any]:
    obj = _dict(raw)
    kind = _clean(obj.get("type") or obj.get("kind")) or "artifact"
    if kind not in LIBRARY_OBJECT_TYPES:
        raise ValueError(f"source_objects[{index}] has unsupported type: {kind}")
    object_id = _clean(obj.get("object_id") or obj.get("id") or obj.get("ref"))
    if not object_id:
        raise ValueError(f"source_objects[{index}] requires object_id/id/ref")
    return {
        "object_id": object_id,
        "type": kind,
        "title": _clean(obj.get("title")),
        "version": _clean(obj.get("version")),
        "authority": _clean(obj.get("authority")) or "originating-library-authority",
        "api_ref": _clean(obj.get("api_ref")),
        "artifact_id": _clean(obj.get("artifact_id")),
        "content_fingerprint_sha256": _clean(obj.get("content_fingerprint_sha256")),
        "provenance": _dict(obj.get("provenance")),
    }


def library_to_workspace_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    library_project_id = _clean(payload.get("library_project_id") or payload.get("project_id"))
    workspace_project_id = _clean(payload.get("workspace_project_id"))
    source_objects = [_normalize_library_object(v, i) for i, v in enumerate(_list(payload.get("source_objects")))]
    if not library_project_id and not source_objects:
        raise ValueError("library_project_id or at least one source_object is required")
    requested = []
    for item in _list(payload.get("requested_actions")):
        action = _clean(item)
        if action and action not in REQUESTED_ACTIONS:
            raise ValueError(f"unsupported requested action: {action}")
        if action and action not in requested:
            requested.append(action)
    research_context = {
        "research_question": _clean(payload.get("research_question")),
        "scope": _clean(payload.get("scope")),
        "notes": _clean(payload.get("notes")),
        "metadata": _dict(payload.get("metadata")),
    }
    packet_basis = {
        "library_project_id": library_project_id,
        "workspace_project_id": workspace_project_id,
        "source_objects": source_objects,
        "requested_actions": requested,
        "research_context": research_context,
    }
    fingerprint = _fp(packet_basis)
    return {
        "schema": HANDOFF_CONTRACT,
        "packet_id": "library-workspace-handoff:" + fingerprint[:32],
        "packet_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "direction": "library-to-workspace",
        "library_project_id": library_project_id,
        "workspace_project_id": workspace_project_id,
        "source_objects": source_objects,
        "requested_actions": requested,
        "research_context": research_context,
        "authority_boundaries": {
            "library_objects": "originating-library-authorities",
            "workspace_execution": "workspace",
            "library_persistence": "existing-library-authorities",
        },
        "transport": {
            "mode": "explicit-export-or-consumer-pull",
            "automatic_push": False,
            "workspace_execution_started": False,
        },
        "persisted": False,
        "guardrails": guardrails(),
    }


def validate_handoff(payload: dict[str, Any]) -> dict[str, Any]:
    packet = _dict(payload.get("packet")) if isinstance(payload, dict) and "packet" in payload else _dict(payload)
    errors: list[str] = []
    warnings: list[str] = []
    if packet.get("schema") != HANDOFF_CONTRACT:
        errors.append("schema must be sc-library-workspace-research-handoff/1.0")
    if packet.get("direction") != "library-to-workspace":
        errors.append("direction must be library-to-workspace")
    objects = _list(packet.get("source_objects"))
    if not packet.get("library_project_id") and not objects:
        errors.append("library_project_id or source_objects is required")
    for i, obj in enumerate(objects):
        if _clean(_dict(obj).get("type")) not in LIBRARY_OBJECT_TYPES:
            errors.append(f"source_objects[{i}] type is unsupported")
        if not _clean(_dict(obj).get("object_id")):
            errors.append(f"source_objects[{i}] object_id is required")
    if packet.get("transport", {}).get("automatic_push") is True:
        errors.append("automatic push transport is not permitted in v6.28")
    if not packet.get("workspace_project_id"):
        warnings.append("workspace_project_id is not bound; consumer may bind explicitly")
    basis = {
        "library_project_id": packet.get("library_project_id"),
        "workspace_project_id": packet.get("workspace_project_id"),
        "source_objects": objects,
        "requested_actions": _list(packet.get("requested_actions")),
        "research_context": _dict(packet.get("research_context")),
    }
    expected = _fp(basis)
    supplied = _clean(packet.get("packet_fingerprint_sha256"))
    if supplied and supplied != expected:
        errors.append("packet_fingerprint_sha256 does not match packet content")
    return {
        "schema": "sc-library-workspace-research-handoff-validation/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "expected_packet_fingerprint_sha256": expected,
        "authority_boundaries_preserved": True,
        "workspace_execution_started": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def workspace_result_registration_preview(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    result = _dict(payload.get("workspace_result"))
    result_id = _clean(result.get("result_id") or result.get("id"))
    result_type = _clean(result.get("type") or result.get("kind"))
    if not result_id:
        raise ValueError("workspace_result.result_id/id is required")
    if result_type not in WORKSPACE_RESULT_TYPES:
        raise ValueError(f"unsupported workspace result type: {result_type}")
    workspace_project_id = _clean(payload.get("workspace_project_id") or result.get("workspace_project_id"))
    if not workspace_project_id:
        raise ValueError("workspace_project_id is required")
    source_refs = []
    for item in _list(payload.get("source_library_refs")):
        value = _clean(item if not isinstance(item, dict) else item.get("object_id") or item.get("id") or item.get("ref"))
        if value and value not in source_refs:
            source_refs.append(value)
    preview_basis = {
        "workspace_project_id": workspace_project_id,
        "library_project_id": _clean(payload.get("library_project_id")),
        "result": result,
        "source_library_refs": source_refs,
    }
    fingerprint = _fp(preview_basis)
    component_type = {
        "analysis": "artifact", "notebook": "artifact", "dataset": "dataset", "table": "artifact",
        "figure": "artifact", "model": "artifact", "simulation": "statistical-result", "calculation": "statistical-result",
        "graph": "knowledge-graph", "report": "artifact", "artifact": "artifact", "other": "artifact",
    }[result_type]
    return {
        "schema": RESULT_PREVIEW_CONTRACT,
        "registration_preview_id": "workspace-result-registration:" + fingerprint[:32],
        "registration_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "direction": "workspace-to-library-preview",
        "library_project_id": _clean(payload.get("library_project_id")),
        "workspace_project_id": workspace_project_id,
        "workspace_result": {
            "result_id": result_id,
            "type": result_type,
            "title": _clean(result.get("title")),
            "runtime": _clean(result.get("runtime")),
            "job_id": _clean(result.get("job_id")),
            "artifact_ids": [str(x) for x in _list(result.get("artifact_ids")) if str(x).strip()],
            "content_fingerprint_sha256": _clean(result.get("content_fingerprint_sha256")),
            "provenance": _dict(result.get("provenance")),
        },
        "library_component_preview": {
            "component_type": component_type,
            "object_id": "workspace-result:" + result_id,
            "source_authority": "workspace",
            "source_library_refs": source_refs,
            "persistence_authority": "existing-library-authorities",
            "requires_explicit_signed_write": True,
        },
        "persisted": False,
        "imported": False,
        "guardrails": guardrails(),
    }


def round_trip_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    packet = _dict(payload.get("handoff_packet"))
    preview = _dict(payload.get("registration_preview"))
    issues: list[str] = []
    handoff_validation = validate_handoff({"packet": packet})
    if not handoff_validation.get("valid"):
        issues.extend(["handoff:" + str(x) for x in handoff_validation.get("errors", [])])
    if preview.get("schema") != RESULT_PREVIEW_CONTRACT:
        issues.append("registration preview schema is invalid")
    hp = _clean(packet.get("workspace_project_id"))
    rp = _clean(preview.get("workspace_project_id"))
    if hp and rp and hp != rp:
        issues.append("workspace_project_id mismatch")
    hl = _clean(packet.get("library_project_id"))
    rl = _clean(preview.get("library_project_id"))
    if hl and rl and hl != rl:
        issues.append("library_project_id mismatch")
    source_ids = {str(_dict(x).get("object_id")) for x in _list(packet.get("source_objects")) if _dict(x).get("object_id")}
    result_refs = set(_list(_dict(preview.get("library_component_preview")).get("source_library_refs")))
    missing_refs = sorted(result_refs - source_ids) if source_ids else []
    if missing_refs:
        issues.append("registration preview references Library objects not present in handoff: " + ", ".join(missing_refs))
    return {
        "schema": ROUND_TRIP_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "pass" if not issues else "review-required",
        "consistent": not issues,
        "issues": issues,
        "library_project_correlated": bool(hl and rl and hl == rl),
        "workspace_project_correlated": bool(hp and rp and hp == rp),
        "source_lineage_preserved": not missing_refs,
        "live_execution_verified": False,
        "persistence_verified": False,
        "guardrails": guardrails(),
    }


def export_exchange(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    content_obj = {
        "schema": "sc-library-workspace-research-exchange-bundle/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "handoff_packet": _dict(payload.get("handoff_packet")),
        "registration_preview": _dict(payload.get("registration_preview")),
        "round_trip_audit": _dict(payload.get("round_trip_audit")),
        "guardrails": guardrails(),
    }
    content = json.dumps(content_obj, sort_keys=True, indent=2, ensure_ascii=False) + "\n"
    digest = hashlib.sha256(content.encode("utf-8")).hexdigest()
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-library-workspace-research-exchange.json",
        "media_type": "application/json",
        "sha256": digest,
        "content": content,
        "persisted": False,
        "guardrails": guardrails(),
    }
