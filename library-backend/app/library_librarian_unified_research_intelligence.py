from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.36.0"
BACKEND_VERSION = "3.36.0"
WEB_VERSION = "2.36.0"
SDK_VERSION = "1.36.0"

CONTRACT = "sc-library-librarian-unified-research-intelligence/1.0"
READINESS_CONTRACT = "sc-library-librarian-unified-research-intelligence-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-librarian-unified-research-intelligence-bootstrap/1.0"
CONTEXT_CONTRACT = "sc-library-librarian-research-context/1.0"
CONTEXT_VALIDATION_CONTRACT = "sc-library-librarian-research-context-validation/1.0"
REQUEST_CONTRACT = "sc-library-librarian-intelligence-request/1.0"
ADVISORY_CONTRACT = "sc-library-librarian-intelligence-advisory-preview/1.0"
GROUNDING_CONTRACT = "sc-library-librarian-grounding-audit/1.0"
ACTION_PLAN_CONTRACT = "sc-library-librarian-proposed-action-plan/1.0"
EXPORT_CONTRACT = "sc-library-librarian-unified-research-intelligence-export/1.0"

TARGET_PRODUCT = "research-librarian-ai"
TARGET_CONTRACT = "sc-research-librarian-context-request/1.0"
MAX_OBJECTS = 5000
MAX_CITATIONS = 5000
MAX_PROVENANCE = 10000
MAX_SUGGESTIONS = 500


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


def guardrails() -> dict[str, Any]:
    return {
        "library_remains_research_object_authority": True,
        "library_remains_research_state_authority": True,
        "library_remains_provenance_authority": True,
        "librarian_layer_is_research_state_authority": False,
        "librarian_layer_is_research_object_authority": False,
        "librarian_layer_is_citation_authority": False,
        "librarian_layer_is_evidence_authority": False,
        "librarian_layer_is_truth_authority": False,
        "librarian_layer_is_execution_authority": False,
        "librarian_layer_is_publication_authority": False,
        "context_packet_changes_source_authority": False,
        "context_packet_rewrites_source_objects": False,
        "context_packet_implies_full_project_state": False,
        "librarian_advice_implies_truth": False,
        "librarian_advice_implies_source_validity": False,
        "librarian_advice_implies_evidence_strength": False,
        "librarian_advice_implies_scientific_validity": False,
        "grounding_coverage_implies_truth": False,
        "grounding_coverage_implies_completeness": False,
        "automatic_external_transport": False,
        "automatic_search_execution": False,
        "automatic_source_inclusion": False,
        "automatic_working_set_mutation": False,
        "automatic_project_mutation": False,
        "automatic_room_mutation": False,
        "automatic_review_decision": False,
        "automatic_publication": False,
        "automatic_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "explicit_context_handoff_required": True,
        "explicit_advisory_intake_required": True,
        "explicit_user_or_downstream_action_required": True,
        "source_and_context_lineage_preserved": True,
        "server_side_intelligence_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "authority-preserving-library-research-context-packet",
        "deterministic-context-validation",
        "explicit-research-librarian-request-envelope",
        "advisory-response-intake-preview",
        "source-and-context-grounding-audit",
        "non-executing-proposed-action-plan",
        "cross-product-context-and-advisory-lineage",
        "deterministic-unified-intelligence-export",
    ]
    basis = {"resources": resources, "target": TARGET_PRODUCT, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "system_id": "library-librarian-unified-research-intelligence:" + _fp(basis)[:32],
        "system_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/intelligence",
        "api_base": "/api/library/v1/research-intelligence",
        "target_product": TARGET_PRODUCT,
        "target_contract": TARGET_CONTRACT,
        "resources": resources,
        "next_release": "6.37.0",
        "next_release_name": "Cross-Library / Cross-Institution Research Federation",
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
        "context_packet_ready": True,
        "context_validation_ready": True,
        "librarian_request_ready": True,
        "advisory_intake_preview_ready": True,
        "grounding_audit_ready": True,
        "proposed_action_plan_ready": True,
        "deterministic_export_ready": True,
        "transport_configured": False,
        "automatic_external_transport": False,
        "server_side_intelligence_persistence": False,
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
        "route": "/research/intelligence",
        "readiness": readiness(),
        "target": {
            "product": TARGET_PRODUCT,
            "contract": TARGET_CONTRACT,
            "transport": "explicit-external-handoff",
            "endpoint_configured": False,
        },
        "operations": [
            "context-packet",
            "validate-context",
            "librarian-request",
            "advisory-preview",
            "grounding-audit",
            "action-plan",
            "export",
        ],
        "browser_storage_key": "sc-library-librarian-unified-research-intelligence-v1",
        "guardrails": guardrails(),
    }


def _object_ref(raw: Any, index: int) -> dict[str, Any]:
    obj = _dict(raw)
    object_id = _clean(obj.get("object_id") or obj.get("source_object_id") or obj.get("id") or obj.get("record_id"))
    if not object_id:
        object_id = f"context-object:{index + 1}"
    return {
        "context_source_id": "context-source:" + _fp({"kind": "research-object", "object_id": object_id, "index": index})[:24],
        "kind": "research-object",
        "object_id": object_id,
        "object_type": _clean(obj.get("object_type") or obj.get("type")) or "research-object",
        "source_schema": _clean(obj.get("source_schema") or obj.get("schema")),
        "source_authority": _clean(obj.get("source_authority") or obj.get("authority")) or "originating-authority-unspecified",
        "title": _clean(obj.get("title") or obj.get("label")),
        "locator": _dict(obj.get("locator")),
        "metadata": _dict(obj.get("metadata")),
        "payload_included": bool(obj.get("include_payload", False)),
        "payload": obj.get("payload") if bool(obj.get("include_payload", False)) else None,
        "source_authority_changed": False,
    }


def _citation_ref(raw: Any, index: int) -> dict[str, Any]:
    c = _dict(raw)
    citation_id = _clean(c.get("citation_id") or c.get("id") or c.get("doi") or c.get("url")) or f"citation:{index + 1}"
    return {
        "context_source_id": "context-source:" + _fp({"kind": "citation", "citation_id": citation_id, "index": index})[:24],
        "kind": "citation",
        "citation_id": citation_id,
        "title": _clean(c.get("title")),
        "doi": _clean(c.get("doi")),
        "url": _clean(c.get("url")),
        "source_authority": _clean(c.get("source_authority") or c.get("authority")) or "citation-authority-unspecified",
        "metadata": _dict(c.get("metadata")),
        "source_authority_changed": False,
    }


def build_context_packet(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    question = _clean(payload.get("question") or payload.get("query"))
    if not question:
        raise ValueError("question/query is required")
    raw_objects = _list(payload.get("research_objects") or payload.get("objects"))
    raw_citations = _list(payload.get("citations"))
    raw_provenance = _list(payload.get("provenance"))
    if len(raw_objects) > MAX_OBJECTS:
        raise ValueError(f"research object limit exceeded: {MAX_OBJECTS}")
    if len(raw_citations) > MAX_CITATIONS:
        raise ValueError(f"citation limit exceeded: {MAX_CITATIONS}")
    if len(raw_provenance) > MAX_PROVENANCE:
        raise ValueError(f"provenance limit exceeded: {MAX_PROVENANCE}")

    objects = [_object_ref(x, i) for i, x in enumerate(raw_objects)]
    citations = [_citation_ref(x, i) for i, x in enumerate(raw_citations)]
    context_sources = objects + citations
    source_ids = [x["context_source_id"] for x in context_sources]
    if len(source_ids) != len(set(source_ids)):
        raise ValueError("duplicate context source IDs")

    components = {
        "project": _dict(payload.get("project")),
        "room": _dict(payload.get("room")),
        "working_set": _dict(payload.get("working_set")),
        "package": _dict(payload.get("package")),
        "publication": _dict(payload.get("publication")),
        "exchange": _dict(payload.get("exchange")),
        "research_objects": objects,
        "citations": citations,
        "provenance": raw_provenance,
    }
    basis = {
        "question": question,
        "intent": _clean(payload.get("intent")) or "research-guidance",
        "components": components,
        "metadata": _dict(payload.get("metadata")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": CONTEXT_CONTRACT,
        "context_id": "library-librarian-context:" + fingerprint[:32],
        "context_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "question": question,
        "intent": _clean(payload.get("intent")) or "research-guidance",
        "components": components,
        "context_sources": context_sources,
        "context_source_count": len(context_sources),
        "metadata": _dict(payload.get("metadata")),
        "source_authority_changed": False,
        "source_objects_mutated": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def _context(payload: dict[str, Any]) -> dict[str, Any]:
    context = _dict(payload.get("context"))
    if not context and payload.get("schema") == CONTEXT_CONTRACT:
        context = dict(payload)
    return context


def validate_context(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    context = _context(payload)
    blockers: list[dict[str, Any]] = []
    advisories: list[dict[str, Any]] = []
    if not context:
        blockers.append({"kind": "context-required"})
    if context and context.get("schema") != CONTEXT_CONTRACT:
        blockers.append({"kind": "unexpected-context-schema", "observed": context.get("schema"), "expected": CONTEXT_CONTRACT})
    if context and not _clean(context.get("context_id")):
        blockers.append({"kind": "context-id-missing"})
    if context and not _clean(context.get("question")):
        blockers.append({"kind": "question-missing"})
    sources = [_dict(x) for x in _list(context.get("context_sources"))] if context else []
    ids = [_clean(x.get("context_source_id")) for x in sources]
    if any(not x for x in ids):
        blockers.append({"kind": "context-source-id-missing"})
    ids_nonempty = [x for x in ids if x]
    if len(ids_nonempty) != len(set(ids_nonempty)):
        blockers.append({"kind": "duplicate-context-source-id"})
    if context and not sources:
        advisories.append({"kind": "no-explicit-context-sources"})
    basis = {"context_id": context.get("context_id") if context else None, "blockers": blockers, "advisories": advisories}
    return {
        "schema": CONTEXT_VALIDATION_CONTRACT,
        "validation_id": "library-librarian-context-validation:" + _fp(basis)[:32],
        "validation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not blockers,
        "blockers": blockers,
        "blocker_count": len(blockers),
        "advisories": advisories,
        "advisory_count": len(advisories),
        "context_id": context.get("context_id") if context else None,
        "context_source_count": len(sources),
        "source_authority_changed": False,
        "guardrails": guardrails(),
    }


def librarian_request(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    context = _context(payload)
    if not context:
        context = build_context_packet(payload)
    validation = validate_context({"context": context})
    if not validation["valid"]:
        raise ValueError("context must validate before building a librarian request")
    task = _clean(payload.get("task") or payload.get("instruction")) or context.get("question")
    mode = _clean(payload.get("mode")) or "research-guidance"
    basis = {"context_id": context.get("context_id"), "context_fingerprint": context.get("context_fingerprint_sha256"), "task": task, "mode": mode}
    fingerprint = _fp(basis)
    return {
        "schema": REQUEST_CONTRACT,
        "request_id": "library-librarian-request:" + fingerprint[:32],
        "request_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "target_product": TARGET_PRODUCT,
        "target_contract": TARGET_CONTRACT,
        "mode": mode,
        "task": task,
        "context": context,
        "context_id": context.get("context_id"),
        "context_fingerprint_sha256": context.get("context_fingerprint_sha256"),
        "transport": "explicit-external-handoff",
        "delivered": False,
        "automatic_transport": False,
        "automatic_execution": False,
        "source_authority_changed": False,
        "guardrails": guardrails(),
    }


def advisory_preview(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    request = _dict(payload.get("request"))
    response = _dict(payload.get("response") or payload.get("advisory"))
    if not request:
        raise ValueError("request is required")
    if request.get("schema") != REQUEST_CONTRACT:
        raise ValueError("request schema is invalid")
    if not response:
        raise ValueError("response/advisory is required")
    source_refs = [str(x).strip() for x in _list(response.get("source_refs")) if str(x).strip()]
    suggestions = [_dict(x) for x in _list(response.get("suggestions"))]
    if len(suggestions) > MAX_SUGGESTIONS:
        raise ValueError(f"suggestion limit exceeded: {MAX_SUGGESTIONS}")
    basis = {
        "request_id": request.get("request_id"),
        "answer": response.get("answer"),
        "source_refs": source_refs,
        "suggestions": suggestions,
        "uncertainties": _list(response.get("uncertainties")),
    }
    fingerprint = _fp(basis)
    return {
        "schema": ADVISORY_CONTRACT,
        "advisory_id": "library-librarian-advisory:" + fingerprint[:32],
        "advisory_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "request_id": request.get("request_id"),
        "context_id": request.get("context_id"),
        "context_fingerprint_sha256": request.get("context_fingerprint_sha256"),
        "answer": response.get("answer"),
        "source_refs": source_refs,
        "suggestions": suggestions,
        "uncertainties": _list(response.get("uncertainties")),
        "model_metadata": _dict(response.get("model_metadata")),
        "advisory_only": True,
        "accepted_into_library_state": False,
        "source_inclusion_performed": False,
        "project_mutation_performed": False,
        "truth_adjudicated": False,
        "guardrails": guardrails(),
    }


def grounding_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    request = _dict(payload.get("request"))
    advisory = _dict(payload.get("advisory"))
    if not request or not advisory:
        raise ValueError("request and advisory are required")
    context = _dict(request.get("context"))
    known = {str(x.get("context_source_id")) for x in _list(context.get("context_sources")) if isinstance(x, dict) and x.get("context_source_id")}
    refs = [str(x).strip() for x in _list(advisory.get("source_refs")) if str(x).strip()]
    matched = [x for x in refs if x in known]
    missing = [x for x in refs if x not in known]
    unreferenced = sorted(known.difference(refs))
    coverage = (len(matched) / len(known)) if known else 0.0
    basis = {"request_id": request.get("request_id"), "advisory_id": advisory.get("advisory_id"), "matched": matched, "missing": missing, "unreferenced": unreferenced}
    return {
        "schema": GROUNDING_CONTRACT,
        "audit_id": "library-librarian-grounding:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "known_context_source_count": len(known),
        "referenced_source_count": len(refs),
        "matched_source_refs": matched,
        "missing_source_refs": missing,
        "unreferenced_context_sources": unreferenced,
        "context_source_coverage": coverage,
        "fully_grounded_to_declared_refs": len(missing) == 0,
        "coverage_implies_truth": False,
        "coverage_implies_context_completeness": False,
        "guardrails": guardrails(),
    }


def action_plan(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    advisory = _dict(payload.get("advisory"))
    if not advisory:
        raise ValueError("advisory is required")
    suggestions = [_dict(x) for x in _list(advisory.get("suggestions"))]
    actions = []
    for index, suggestion in enumerate(suggestions):
        action = _clean(suggestion.get("action") or suggestion.get("kind")) or "review-suggestion"
        actions.append({
            "action_id": "proposed-action:" + _fp({"index": index, "suggestion": suggestion})[:24],
            "action": action,
            "target": _dict(suggestion.get("target")),
            "parameters": _dict(suggestion.get("parameters")),
            "rationale": _clean(suggestion.get("rationale") or suggestion.get("reason")),
            "proposed_only": True,
            "requires_explicit_execution": True,
            "executed": False,
        })
    basis = {"advisory_id": advisory.get("advisory_id"), "actions": actions}
    return {
        "schema": ACTION_PLAN_CONTRACT,
        "plan_id": "library-librarian-action-plan:" + _fp(basis)[:32],
        "plan_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "advisory_id": advisory.get("advisory_id"),
        "actions": actions,
        "action_count": len(actions),
        "automatic_execution": False,
        "automatic_search_execution": False,
        "automatic_source_inclusion": False,
        "automatic_project_mutation": False,
        "guardrails": guardrails(),
    }


def export_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    context = _dict(payload.get("context"))
    request = _dict(payload.get("request"))
    advisory = _dict(payload.get("advisory"))
    grounding = _dict(payload.get("grounding"))
    plan = _dict(payload.get("action_plan"))
    bundle = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "context": context,
        "request": request,
        "advisory": advisory,
        "grounding": grounding,
        "action_plan": plan,
        "originating_authority_preserved": True,
        "automatic_transport": False,
        "automatic_execution": False,
        "automatic_persistence": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
    bundle["bundle_fingerprint_sha256"] = _fp(bundle)
    return {
        "schema": EXPORT_CONTRACT,
        "filename": "sustainable-catalyst-library-librarian-unified-research-intelligence.json",
        "media_type": "application/json",
        "content": json.dumps(bundle, indent=2, ensure_ascii=False, sort_keys=True),
        "bundle_fingerprint_sha256": bundle["bundle_fingerprint_sha256"],
        "originating_authority_preserved": True,
        "automatic_transport": False,
        "automatic_execution": False,
        "automatic_persistence": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
