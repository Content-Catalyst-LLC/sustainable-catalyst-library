from __future__ import annotations

from copy import deepcopy
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.37.0"
BACKEND_VERSION = "3.37.0"
WEB_VERSION = "2.37.0"
SDK_VERSION = "1.37.0"

CONTRACT = "sc-library-cross-library-cross-institution-research-federation/1.0"
READINESS_CONTRACT = "sc-library-cross-library-cross-institution-research-federation-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-cross-library-cross-institution-research-federation-bootstrap/1.0"
INSTITUTION_CONTRACT = "sc-library-federated-institution-manifest/1.0"
QUERY_PLAN_CONTRACT = "sc-library-federated-research-query-plan/1.0"
RESULT_BUNDLE_CONTRACT = "sc-library-federated-research-result-bundle/1.0"
IDENTITY_CANDIDATE_CONTRACT = "sc-library-federated-source-identity-candidates/1.0"
PROVENANCE_AUDIT_CONTRACT = "sc-library-federated-provenance-audit/1.0"
FAILURE_CONTAINMENT_CONTRACT = "sc-library-federated-failure-containment/1.0"
EXPORT_CONTRACT = "sc-library-cross-institution-research-federation-export/1.0"

MAX_INSTITUTIONS = 100
MAX_SOURCE_RESULTS = 5000
MAX_RESULT_RECORDS = 25000


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, list) else []


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    value = str(value).strip()
    return value or None


def _canonical(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def _fp(value: Any) -> str:
    return sha256(_canonical(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, Any]:
    return {
        "federation_layer_is_source_authority": False,
        "federation_layer_is_identity_authority": False,
        "federation_layer_is_citation_authority": False,
        "federation_layer_is_evidence_authority": False,
        "federation_layer_is_truth_authority": False,
        "federation_layer_is_project_persistence_authority": False,
        "institutional_source_authority_preserved": True,
        "institutional_policy_boundary_preserved": True,
        "institutional_record_payload_preserved": True,
        "query_plan_executes_network_requests": False,
        "automatic_external_fetch": False,
        "automatic_record_import": False,
        "automatic_source_merge": False,
        "automatic_deduplication": False,
        "automatic_schema_normalization": False,
        "automatic_semantic_equivalence_claim": False,
        "exact_identity_candidate_is_merge_decision": False,
        "cross_source_agreement_implies_truth": False,
        "cross_source_count_implies_evidence_strength": False,
        "source_failure_invalidates_successful_sources": False,
        "federation_availability_implies_source_validity": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_federation_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "institution-capability-manifest",
        "explicit-cross-institution-query-plan",
        "authority-preserving-source-result-bundle",
        "exact-source-identity-candidate-detection",
        "federated-provenance-audit",
        "per-source-failure-containment",
        "deterministic-federation-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "federation_id": "cross-institution-research-federation:" + _fp(basis)[:32],
        "federation_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/federation",
        "api_base": "/api/library/v1/research-federation",
        "composes_existing_authorities": [
            "global-knowledge-federation",
            "institutional-repository-federation",
            "source-identity-resolution",
            "python-provenance-citation-evidence-graph",
            "portable-research-object-exchange",
        ],
        "resources": resources,
        "next_release": "6.38.0",
        "next_release_name": "Research Reproducibility & Audit Console",
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
        "institution_manifest_ready": True,
        "query_planning_ready": True,
        "result_bundle_ready": True,
        "source_identity_candidate_detection_ready": True,
        "provenance_audit_ready": True,
        "failure_containment_ready": True,
        "deterministic_export_ready": True,
        "live_network_transport_configured": False,
        "automatic_external_fetch": False,
        "server_side_federation_persistence": False,
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
        "route": "/research/federation",
        "readiness": readiness(),
        "operations": [
            "institution-manifest",
            "query-plan",
            "result-bundle",
            "identity-candidates",
            "provenance-audit",
            "failure-containment",
            "export",
        ],
        "supported_capabilities": [
            "search",
            "record-lookup",
            "dataset-discovery",
            "publication-discovery",
            "metadata",
            "full-text-locator",
            "citation-export",
        ],
        "browser_storage_key": "sc-library-cross-institution-research-federation-v1",
        "guardrails": guardrails(),
    }


def institution_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    institution_id = _clean(payload.get("institution_id") or payload.get("id"))
    name = _clean(payload.get("name"))
    if not institution_id:
        raise ValueError("institution_id is required")
    if not name:
        raise ValueError("institution name is required")
    capabilities = sorted({_clean(x) for x in _list(payload.get("capabilities")) if _clean(x)})
    endpoints = _dict(payload.get("endpoints"))
    policies = _dict(payload.get("policies"))
    source_authority = _clean(payload.get("source_authority") or payload.get("authority")) or institution_id
    basis = {
        "institution_id": institution_id,
        "name": name,
        "source_authority": source_authority,
        "capabilities": capabilities,
        "endpoints": endpoints,
        "policies": policies,
        "languages": sorted({_clean(x) for x in _list(payload.get("languages")) if _clean(x)}),
    }
    fingerprint = _fp(basis)
    return {
        "schema": INSTITUTION_CONTRACT,
        "manifest_id": "federated-institution:" + fingerprint[:32],
        "manifest_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "institution_id": institution_id,
        "name": name,
        "kind": _clean(payload.get("kind")) or "institution",
        "jurisdiction": _clean(payload.get("jurisdiction")),
        "source_authority": source_authority,
        "capabilities": capabilities,
        "endpoints": endpoints,
        "languages": basis["languages"],
        "policies": policies,
        "metadata": _dict(payload.get("metadata")),
        "registered": False,
        "network_verified": False,
        "source_authority_changed": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def _institution_list(payload: dict[str, Any]) -> list[dict[str, Any]]:
    raw = _list(payload.get("institutions"))
    if len(raw) > MAX_INSTITUTIONS:
        raise ValueError(f"institution limit exceeded: {MAX_INSTITUTIONS}")
    out = []
    seen = set()
    for item in raw:
        item = _dict(item)
        manifest = item if item.get("schema") == INSTITUTION_CONTRACT else institution_manifest(item)
        iid = _clean(manifest.get("institution_id"))
        if iid in seen:
            raise ValueError(f"duplicate institution_id: {iid}")
        seen.add(iid)
        out.append(manifest)
    return out


def query_plan(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    query = _clean(payload.get("query") or payload.get("question"))
    if not query:
        raise ValueError("query/question is required")
    institutions = _institution_list(payload)
    if not institutions:
        raise ValueError("at least one institution is required")
    requested_capabilities = sorted({_clean(x) for x in _list(payload.get("capabilities")) if _clean(x)}) or ["search"]
    requests = []
    for manifest in institutions:
        available = set(manifest.get("capabilities") or [])
        requested = [c for c in requested_capabilities if c in available]
        requests.append({
            "institution_id": manifest["institution_id"],
            "source_authority": manifest["source_authority"],
            "requested_capabilities": requested,
            "unavailable_capabilities": [c for c in requested_capabilities if c not in available],
            "query": query,
            "filters": _dict(payload.get("filters")),
            "endpoint_hints": {k: v for k, v in _dict(manifest.get("endpoints")).items() if k in requested},
            "planned_only": True,
            "executed": False,
        })
    basis = {"query": query, "requests": requests, "strategy": _clean(payload.get("strategy")) or "parallel-explicit"}
    fingerprint = _fp(basis)
    return {
        "schema": QUERY_PLAN_CONTRACT,
        "query_plan_id": "federated-query-plan:" + fingerprint[:32],
        "query_plan_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "query": query,
        "strategy": _clean(payload.get("strategy")) or "parallel-explicit",
        "institution_count": len(institutions),
        "requests": requests,
        "network_requests_executed": 0,
        "automatic_external_fetch": False,
        "guardrails": guardrails(),
    }


def _record_projection(raw: Any, institution_id: str, source_authority: str, source_index: int, record_index: int) -> dict[str, Any]:
    record = _dict(raw)
    exact = deepcopy(record)
    identifiers = _dict(record.get("identifiers"))
    doi = _clean(record.get("doi") or identifiers.get("doi"))
    content_hash = _clean(record.get("content_hash_sha256") or record.get("sha256") or identifiers.get("sha256"))
    url = _clean(record.get("url") or identifiers.get("url"))
    source_record_id = _clean(record.get("record_id") or record.get("id") or identifiers.get("local_id")) or f"record:{record_index + 1}"
    exact_fp = _fp(exact)
    return {
        "federated_result_id": "federated-result:" + _fp({"institution_id": institution_id, "source_record_id": source_record_id, "exact_fp": exact_fp})[:32],
        "institution_id": institution_id,
        "source_authority": source_authority,
        "source_record_id": source_record_id,
        "source_schema": _clean(record.get("schema")),
        "title": _clean(record.get("title")),
        "doi": doi,
        "content_hash_sha256": content_hash,
        "url": url,
        "identifiers": identifiers,
        "source_payload": exact,
        "source_payload_fingerprint_sha256": exact_fp,
        "source_payload_rewritten": False,
        "source_authority_changed": False,
        "source_result_index": source_index,
    }


def result_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    plan = _dict(payload.get("query_plan") or payload.get("plan"))
    if plan.get("schema") != QUERY_PLAN_CONTRACT:
        raise ValueError("valid query_plan is required")
    raw_sources = _list(payload.get("source_results"))
    if len(raw_sources) > MAX_SOURCE_RESULTS:
        raise ValueError(f"source result bundle limit exceeded: {MAX_SOURCE_RESULTS}")
    planned = {x.get("institution_id"): x for x in _list(plan.get("requests")) if isinstance(x, dict)}
    source_bundles = []
    flattened = []
    total_records = 0
    for source_index, raw in enumerate(raw_sources):
        source = _dict(raw)
        institution_id = _clean(source.get("institution_id"))
        if not institution_id:
            raise ValueError("source_results[].institution_id is required")
        request = _dict(planned.get(institution_id))
        source_authority = _clean(source.get("source_authority") or request.get("source_authority")) or institution_id
        records = _list(source.get("records"))
        total_records += len(records)
        if total_records > MAX_RESULT_RECORDS:
            raise ValueError(f"result record limit exceeded: {MAX_RESULT_RECORDS}")
        projected = [_record_projection(x, institution_id, source_authority, source_index, i) for i, x in enumerate(records)]
        flattened.extend(projected)
        source_bundles.append({
            "institution_id": institution_id,
            "source_authority": source_authority,
            "status": _clean(source.get("status")) or "ok",
            "record_count": len(projected),
            "records": projected,
            "source_metadata": _dict(source.get("metadata")),
            "error": _dict(source.get("error")),
            "source_payloads_preserved": True,
        })
    basis = {"query_plan_id": plan.get("query_plan_id"), "sources": source_bundles}
    fingerprint = _fp(basis)
    return {
        "schema": RESULT_BUNDLE_CONTRACT,
        "result_bundle_id": "federated-result-bundle:" + fingerprint[:32],
        "result_bundle_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "query_plan_id": plan.get("query_plan_id"),
        "query": plan.get("query"),
        "source_count": len(source_bundles),
        "record_count": len(flattened),
        "source_results": source_bundles,
        "records": flattened,
        "source_authority_changed": False,
        "source_payloads_rewritten": False,
        "import_performed": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def identity_candidates(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    bundle = _dict(payload.get("result_bundle") or payload.get("bundle"))
    if bundle.get("schema") != RESULT_BUNDLE_CONTRACT:
        raise ValueError("valid result_bundle is required")
    records = [_dict(x) for x in _list(bundle.get("records"))]
    indexes: dict[str, dict[str, list[dict[str, Any]]]] = {"doi": {}, "content-hash": {}, "normalized-url": {}}
    for record in records:
        rid = record.get("federated_result_id")
        values = {
            "doi": (_clean(record.get("doi")) or "").lower(),
            "content-hash": (_clean(record.get("content_hash_sha256")) or "").lower(),
            "normalized-url": (_clean(record.get("url")) or "").strip().lower().rstrip("/"),
        }
        for kind, value in values.items():
            if value:
                indexes[kind].setdefault(value, []).append({
                    "federated_result_id": rid,
                    "institution_id": record.get("institution_id"),
                    "source_authority": record.get("source_authority"),
                    "source_record_id": record.get("source_record_id"),
                })
    candidates = []
    for kind in ("doi", "content-hash", "normalized-url"):
        for value, matches in sorted(indexes[kind].items()):
            institutions = sorted({str(m.get("institution_id")) for m in matches})
            if len(matches) > 1 and len(institutions) > 1:
                candidates.append({
                    "candidate_id": "identity-candidate:" + _fp({"kind": kind, "value": value, "matches": matches})[:24],
                    "match_kind": kind,
                    "match_value": value,
                    "matches": matches,
                    "institution_count": len(institutions),
                    "merge_performed": False,
                    "human_review_required": True,
                })
    return {
        "schema": IDENTITY_CANDIDATE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "candidate_count": len(candidates),
        "candidates": candidates,
        "automatic_merge": False,
        "automatic_deduplication": False,
        "semantic_equivalence_claimed": False,
        "guardrails": guardrails(),
    }


def provenance_audit(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    bundle = _dict(payload.get("result_bundle") or payload.get("bundle"))
    if bundle.get("schema") != RESULT_BUNDLE_CONTRACT:
        raise ValueError("valid result_bundle is required")
    findings = []
    complete = 0
    for record in [_dict(x) for x in _list(bundle.get("records"))]:
        missing = [k for k in ("institution_id", "source_authority", "source_record_id", "source_payload_fingerprint_sha256") if not _clean(record.get(k))]
        if not missing:
            complete += 1
        findings.append({
            "federated_result_id": record.get("federated_result_id"),
            "institution_id": record.get("institution_id"),
            "complete": not missing,
            "missing": missing,
            "source_payload_rewritten": bool(record.get("source_payload_rewritten", False)),
            "source_authority_changed": bool(record.get("source_authority_changed", False)),
        })
    total = len(findings)
    return {
        "schema": PROVENANCE_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "record_count": total,
        "complete_record_count": complete,
        "incomplete_record_count": total - complete,
        "complete_fraction": (complete / total) if total else 1.0,
        "findings": findings,
        "complete_fraction_is_truth_probability": False,
        "complete_fraction_is_source_quality_score": False,
        "guardrails": guardrails(),
    }


def failure_containment(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    bundle = _dict(payload.get("result_bundle") or payload.get("bundle"))
    if bundle.get("schema") != RESULT_BUNDLE_CONTRACT:
        raise ValueError("valid result_bundle is required")
    successes = []
    failures = []
    for source in [_dict(x) for x in _list(bundle.get("source_results"))]:
        status = (_clean(source.get("status")) or "ok").lower()
        row = {
            "institution_id": source.get("institution_id"),
            "source_authority": source.get("source_authority"),
            "status": status,
            "record_count": int(source.get("record_count") or 0),
            "error": _dict(source.get("error")),
        }
        if status in {"ok", "ready", "success", "partial"}:
            successes.append(row)
        else:
            failures.append(row)
    return {
        "schema": FAILURE_CONTAINMENT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "successful_source_count": len(successes),
        "failed_source_count": len(failures),
        "successful_sources": successes,
        "failed_sources": failures,
        "successful_sources_preserved": True,
        "failure_invalidates_successful_sources": False,
        "automatic_retry": False,
        "automatic_source_substitution": False,
        "guardrails": guardrails(),
    }


def export_bundle(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    content = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "institutions": _list(payload.get("institutions")),
        "query_plan": _dict(payload.get("query_plan")),
        "result_bundle": _dict(payload.get("result_bundle")),
        "identity_candidates": _dict(payload.get("identity_candidates")),
        "provenance_audit": _dict(payload.get("provenance_audit")),
        "failure_containment": _dict(payload.get("failure_containment")),
        "originating_authorities_preserved": True,
        "automatic_external_fetch": False,
        "automatic_import": False,
        "automatic_merge": False,
        "automatic_persistence": False,
        "automatic_truth_promotion": False,
        "guardrails": guardrails(),
    }
    filename = "sustainable-catalyst-cross-institution-research-federation.json"
    return {
        "schema": "sc-library-deterministic-export/1.0",
        "filename": filename,
        "media_type": "application/json",
        "content": json.dumps(content, ensure_ascii=False, sort_keys=True, indent=2) + "\n",
        "content_fingerprint_sha256": _fp(content),
        **{k: content[k] for k in (
            "originating_authorities_preserved",
            "automatic_external_fetch",
            "automatic_import",
            "automatic_merge",
            "automatic_persistence",
            "automatic_truth_promotion",
            "guardrails",
        )},
    }
