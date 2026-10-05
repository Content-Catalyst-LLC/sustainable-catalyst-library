from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.25.0"
BACKEND_VERSION = "3.25.0"
WEB_VERSION = "2.25.0"
SDK_VERSION = "1.25.0"

CONTRACT = "sc-library-research-package-composer-workspace/1.0"
READINESS_CONTRACT = "sc-library-research-package-composer-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-package-composer-bootstrap/1.0"
PACKAGE_CONTRACT = "sc-library-research-package-composition/1.0"
COMPLETENESS_CONTRACT = "sc-library-research-package-completeness-audit/1.0"
PROVENANCE_CONTRACT = "sc-library-research-package-provenance-audit/1.0"
DEPENDENCY_CONTRACT = "sc-library-research-package-dependency-map/1.0"
EXPORT_CONTRACT = "sc-library-research-package-composer-export/1.0"

COMPONENT_TYPES = {
    "investigation","synthesis","evidence-matrix","statistical-evidence","geospatial",
    "bibliography","annotations","timeline","corpus","entity-place","primary-source",
    "record-set","dataset","artifact","publication","method","appendix","other",
}
COMPONENT_STATES = {"draft","reviewed","final","reference","unknown"}
PROVENANCE_STATES = {"complete","partial","missing","unknown"}
MAX_COMPONENTS = 2000
MAX_DEPENDENCIES = 10000
MAX_REQUIREMENTS = 500


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 12000) -> str:
    return " ".join(str(value or "").split())[:limit]


def _unique_strings(value: Any, limit: int = 5000) -> list[str]:
    out: list[str] = []
    for raw in _list(value):
        item = _clean(raw, 2000)
        if item and item not in out:
            out.append(item)
        if len(out) >= limit:
            break
    return out


def guardrails() -> dict[str, bool]:
    return {
        "composer_is_new_source_authority": False,
        "composer_is_new_evidence_authority": False,
        "composer_is_research_package_persistence_authority": False,
        "existing_research_package_service_remains_authority": True,
        "existing_research_package_publishing_remains_export_authority": True,
        "artifact_store_remains_persisted_byte_authority": True,
        "component_payloads_are_rewritten_automatically": False,
        "component_semantics_are_merged_automatically": False,
        "missing_component_implies_research_false": False,
        "package_completeness_implies_truth": False,
        "package_integrity_implies_source_validity": False,
        "package_integrity_implies_evidence_strength": False,
        "package_order_implies_importance": False,
        "draft_package_is_published_research": False,
        "publishing_handoff_executes_automatically": False,
        "reproducibility_handoff_executes_automatically": False,
        "automatic_external_publication": False,
        "automatic_artifact_persistence": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_composer_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "typed-research-components",
        "composition-manifest",
        "explicit-section-order",
        "explicit-dependency-map",
        "component-provenance-audit",
        "package-completeness-audit",
        "record-artifact-pipeline-reference-inventory",
        "portable-publishing-handoff-preview",
        "signed-reproducibility-handoff-preview",
        "deterministic-composer-export",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "research-package-composer:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/package",
        "api_base": "/api/library/v1/research-package-composer",
        "resources": resources,
        "existing_authorities": {
            "reproducibility_package": "python-research-package-reproducibility-service",
            "portable_export": "research-package-publishing",
            "persisted_bytes": "content-addressed-artifact-store",
            "structured_state": "postgresql",
        },
        "limits": {
            "components": MAX_COMPONENTS,
            "dependencies": MAX_DEPENDENCIES,
            "requirements": MAX_REQUIREMENTS,
        },
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
        "handoff_authorities": {
            "portable_publishing_preview": "/api/library/v1/research-package-publishing/preview",
            "portable_export": "/api/library/v1/research-package-publishing/export",
            "reproducibility_package_create": "/api/library/v1/admin/reproducibility/packages",
        },
        "signed_reproducibility_persistence_required": True,
        "server_side_composer_persistence": False,
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
        "route": "/research/package",
        "readiness": readiness(),
        "component_types": sorted(COMPONENT_TYPES),
        "component_states": sorted(COMPONENT_STATES),
        "provenance_states": sorted(PROVENANCE_STATES),
        "operations": [
            "compose","completeness-audit","provenance-audit","dependency-map",
            "publishing-handoff-preview","reproducibility-handoff-preview","export",
        ],
        "browser_storage_key": "sc-library-research-package-composer-v1",
        "guardrails": guardrails(),
    }


def _component(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    component_id = _clean(r.get("component_id") or r.get("id"), 1000) or f"component:{index}"
    component_type = _clean(r.get("component_type") or r.get("type"), 100).lower() or "other"
    if component_type not in COMPONENT_TYPES:
        component_type = "other"
    state = _clean(r.get("state"), 100).lower() or "unknown"
    if state not in COMPONENT_STATES:
        state = "unknown"
    provenance_state = _clean(r.get("provenance_state"), 100).lower() or "unknown"
    if provenance_state not in PROVENANCE_STATES:
        provenance_state = "unknown"
    payload = r.get("payload")
    if payload is None:
        payload = {}
    return {
        "component_id": component_id,
        "component_type": component_type,
        "title": _clean(r.get("title") or r.get("label"), 4000) or component_id,
        "state": state,
        "required": bool(r.get("required", False)),
        "source_route": _clean(r.get("source_route"), 4000) or None,
        "source_contract": _clean(r.get("source_contract"), 1000) or None,
        "source_version": _clean(r.get("source_version"), 500) or None,
        "source_object_id": _clean(r.get("source_object_id"), 2000) or None,
        "provenance_state": provenance_state,
        "provenance": _dict(r.get("provenance")),
        "dependencies": _unique_strings(r.get("dependencies"), 1000),
        "record_ids": _unique_strings(r.get("record_ids"), 5000),
        "artifact_ids": _unique_strings(r.get("artifact_ids"), 5000),
        "pipeline_run_ids": _unique_strings(r.get("pipeline_run_ids"), 5000),
        "payload": payload,
        "notes": _clean(r.get("notes"), 16000) or None,
    }


def _requirement(raw: Any, index: int) -> dict[str, Any]:
    r = _dict(raw)
    component_type = _clean(r.get("component_type") or r.get("type"), 100).lower() or "other"
    if component_type not in COMPONENT_TYPES:
        component_type = "other"
    minimum = r.get("minimum")
    try:
        minimum = max(0, int(minimum if minimum is not None else 1))
    except (TypeError, ValueError):
        minimum = 1
    return {
        "requirement_id": _clean(r.get("requirement_id") or r.get("id"), 1000) or f"requirement:{index}",
        "component_type": component_type,
        "minimum": minimum,
        "description": _clean(r.get("description"), 6000) or None,
    }


def normalize_workspace(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    components_raw = _list(payload.get("components"))
    requirements_raw = _list(payload.get("requirements"))
    if len(components_raw) > MAX_COMPONENTS:
        raise ValueError(f"component-limit-exceeded:{MAX_COMPONENTS}")
    if len(requirements_raw) > MAX_REQUIREMENTS:
        raise ValueError(f"requirement-limit-exceeded:{MAX_REQUIREMENTS}")
    components = [_component(x, i) for i, x in enumerate(components_raw, 1)]
    ids = [x["component_id"] for x in components]
    if len(ids) != len(set(ids)):
        raise ValueError("duplicate-component-id")
    dependency_count = sum(len(x["dependencies"]) for x in components)
    if dependency_count > MAX_DEPENDENCIES:
        raise ValueError(f"dependency-limit-exceeded:{MAX_DEPENDENCIES}")
    requirements = [_requirement(x, i) for i, x in enumerate(requirements_raw, 1)]

    declared_order = _unique_strings(payload.get("section_order"), MAX_COMPONENTS)
    known = set(ids)
    section_order = [x for x in declared_order if x in known]
    for component_id in ids:
        if component_id not in section_order:
            section_order.append(component_id)

    references = _dict(payload.get("references"))
    basis = {
        "title": _clean(payload.get("title"), 4000),
        "research_question": _clean(payload.get("research_question"), 16000),
        "description": _clean(payload.get("description"), 16000),
        "components": components,
        "section_order": section_order,
        "requirements": requirements,
        "references": references,
    }
    return {
        "schema": "sc-library-research-package-composer-input/1.0",
        "composer_input_id": "package-composer-input:" + _fp(basis)[:32],
        "composer_input_fingerprint_sha256": _fp(basis),
        "title": basis["title"] or "Research package",
        "research_question": basis["research_question"] or None,
        "description": basis["description"] or None,
        "scope": _clean(payload.get("scope"), 16000) or None,
        "project_id": _clean(payload.get("project_id"), 1000) or None,
        "components": components,
        "section_order": section_order,
        "requirements": requirements,
        "references": {
            "record_ids": _unique_strings(references.get("record_ids"), 5000),
            "artifact_ids": _unique_strings(references.get("artifact_ids"), 5000),
            "pipeline_run_ids": _unique_strings(references.get("pipeline_run_ids"), 5000),
            "reproducibility_records": [
                dict(x) for x in _list(references.get("reproducibility_records")) if isinstance(x, dict)
            ][:5000],
        },
        "portable_export_inputs": {
            "records": [dict(x) for x in _list(payload.get("records")) if isinstance(x, dict)][:5000],
            "datasets": [dict(x) for x in _list(payload.get("datasets")) if isinstance(x, dict)][:100],
            "scientific_literature": [dict(x) for x in _list(payload.get("scientific_literature")) if isinstance(x, dict)][:100],
        },
        "metadata": _dict(payload.get("metadata")),
    }


def dependency_map(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    ids = {x["component_id"] for x in w["components"]}
    edges = []
    missing = []
    for component in w["components"]:
        for dep in component["dependencies"]:
            edge = {"from_component_id": component["component_id"], "depends_on_component_id": dep}
            if dep in ids:
                edges.append(edge)
            else:
                missing.append(edge)
    basis = {"input": w["composer_input_fingerprint_sha256"], "edges": edges, "missing": missing}
    return {
        "schema": DEPENDENCY_CONTRACT,
        "dependency_map_id": "package-dependencies:" + _fp(basis)[:32],
        "dependency_map_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "edges": edges,
        "missing_dependencies": missing,
        "dependency_count": len(edges),
        "missing_dependency_count": len(missing),
        "dependencies_are_explicit_only": True,
        "automatic_dependency_inference": False,
        "guardrails": guardrails(),
    }


def completeness_audit(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    by_type: dict[str, list[dict[str, Any]]] = {}
    for component in w["components"]:
        by_type.setdefault(component["component_type"], []).append(component)

    findings = []
    for req in w["requirements"]:
        actual = len(by_type.get(req["component_type"], []))
        if actual < req["minimum"]:
            findings.append({
                "kind": "requirement-not-met",
                "requirement_id": req["requirement_id"],
                "component_type": req["component_type"],
                "minimum": req["minimum"],
                "actual": actual,
            })
    for component in w["components"]:
        if component["required"] and component["state"] == "draft":
            findings.append({
                "kind": "required-component-still-draft",
                "component_id": component["component_id"],
            })
        if component["payload"] in ({}, [], "", None):
            findings.append({
                "kind": "component-payload-empty",
                "component_id": component["component_id"],
            })

    dep = dependency_map(payload)
    for item in dep["missing_dependencies"]:
        findings.append({"kind": "missing-component-dependency", **item})

    basis = {"input": w["composer_input_fingerprint_sha256"], "findings": findings}
    return {
        "schema": COMPLETENESS_CONTRACT,
        "audit_id": "package-completeness:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "findings": findings,
        "finding_count": len(findings),
        "complete_against_declared_requirements": len(findings) == 0,
        "completeness_implies_truth": False,
        "completeness_implies_publication_readiness": False,
        "guardrails": guardrails(),
    }


def provenance_audit(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    rows = []
    findings = []
    for component in w["components"]:
        has_source_identity = bool(
            component["source_route"]
            or component["source_contract"]
            or component["source_object_id"]
        )
        has_provenance_payload = bool(component["provenance"])
        row = {
            "component_id": component["component_id"],
            "component_type": component["component_type"],
            "provenance_state": component["provenance_state"],
            "has_source_identity": has_source_identity,
            "has_provenance_payload": has_provenance_payload,
            "record_reference_count": len(component["record_ids"]),
            "artifact_reference_count": len(component["artifact_ids"]),
            "pipeline_run_reference_count": len(component["pipeline_run_ids"]),
        }
        rows.append(row)
        if component["provenance_state"] in {"missing", "unknown"}:
            findings.append({
                "kind": "component-provenance-incomplete",
                "component_id": component["component_id"],
            })
        if not has_source_identity:
            findings.append({
                "kind": "component-source-identity-not-recorded",
                "component_id": component["component_id"],
            })

    basis = {"input": w["composer_input_fingerprint_sha256"], "rows": rows, "findings": findings}
    return {
        "schema": PROVENANCE_CONTRACT,
        "audit_id": "package-provenance:" + _fp(basis)[:32],
        "audit_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "findings": findings,
        "finding_count": len(findings),
        "provenance_completeness_implies_source_validity": False,
        "guardrails": guardrails(),
    }


def compose_package(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    components_by_id = {x["component_id"]: x for x in w["components"]}
    ordered = [components_by_id[x] for x in w["section_order"] if x in components_by_id]
    dep = dependency_map(payload)
    completeness = completeness_audit(payload)
    provenance = provenance_audit(payload)

    counts: dict[str, int] = {}
    for component in ordered:
        counts[component["component_type"]] = counts.get(component["component_type"], 0) + 1

    basis = {
        "title": w["title"],
        "research_question": w["research_question"],
        "description": w["description"],
        "scope": w["scope"],
        "project_id": w["project_id"],
        "components": ordered,
        "section_order": w["section_order"],
        "references": w["references"],
        "metadata": w["metadata"],
    }
    fingerprint = _fp(basis)
    return {
        "schema": PACKAGE_CONTRACT,
        "composition_id": "research-package-composition:" + fingerprint[:32],
        "composition_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "title": w["title"],
        "research_question": w["research_question"],
        "description": w["description"],
        "scope": w["scope"],
        "project_id": w["project_id"],
        "components": ordered,
        "section_order": w["section_order"],
        "component_counts": counts,
        "references": w["references"],
        "dependency_map": dep,
        "completeness_audit": completeness,
        "provenance_audit": provenance,
        "package_state": "draft-composition",
        "published": False,
        "persisted": False,
        "truth_adjudicated": False,
        "guardrails": guardrails(),
    }


def publishing_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    composition = compose_package(payload)
    portable = w["portable_export_inputs"]
    publishing_payload = {
        "title": w["title"],
        "description": w["description"] or "Research package composed in Sustainable Catalyst Library.",
        "profile": "portable-research-package",
        "research_package": composition,
        "records": portable["records"],
        "datasets": portable["datasets"],
        "scientific_literature": portable["scientific_literature"],
        "metadata": {
            **w["metadata"],
            "composer_schema": PACKAGE_CONTRACT,
            "composition_id": composition["composition_id"],
            "composition_fingerprint_sha256": composition["composition_fingerprint_sha256"],
        },
    }
    basis = {"endpoint": "/api/library/v1/research-package-publishing/preview", "payload": publishing_payload}
    return {
        "schema": "sc-library-research-package-composer-publishing-handoff-preview/1.0",
        "handoff_id": "package-publishing-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/research-package-publishing/preview",
        "payload": publishing_payload,
        "preview_only": True,
        "automatic_submission": False,
        "automatic_persistence": False,
        "automatic_external_publication": False,
        "guardrails": guardrails(),
    }


def reproducibility_handoff_preview(payload: dict[str, Any]) -> dict[str, Any]:
    w = normalize_workspace(payload)
    composition = compose_package(payload)

    record_ids = list(w["references"]["record_ids"])
    artifact_ids = list(w["references"]["artifact_ids"])
    pipeline_run_ids = list(w["references"]["pipeline_run_ids"])
    for component in w["components"]:
        for value, target in [
            (component["record_ids"], record_ids),
            (component["artifact_ids"], artifact_ids),
            (component["pipeline_run_ids"], pipeline_run_ids),
        ]:
            for item in value:
                if item not in target:
                    target.append(item)

    package_payload = {
        "title": w["title"],
        "description": w["description"] or "Research reproducibility package composed in Sustainable Catalyst Library.",
        "project_id": w["project_id"],
        "record_ids": record_ids,
        "artifact_ids": artifact_ids,
        "pipeline_run_ids": pipeline_run_ids,
        "reproducibility_records": w["references"]["reproducibility_records"],
        "metadata": {
            **w["metadata"],
            "composer_schema": PACKAGE_CONTRACT,
            "composition_id": composition["composition_id"],
            "composition_fingerprint_sha256": composition["composition_fingerprint_sha256"],
        },
    }
    basis = {"endpoint": "/api/library/v1/admin/reproducibility/packages", "payload": package_payload}
    return {
        "schema": "sc-library-research-package-composer-reproducibility-handoff-preview/1.0",
        "handoff_id": "package-reproducibility-handoff:" + _fp(basis)[:32],
        "handoff_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "endpoint": "/api/library/v1/admin/reproducibility/packages",
        "payload": package_payload,
        "preview_only": True,
        "signed_request_required": True,
        "automatic_submission": False,
        "automatic_persistence": False,
        "guardrails": guardrails(),
    }


def export_package_draft(payload: dict[str, Any]) -> dict[str, Any]:
    composition = compose_package(payload)
    body = {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "composition": composition,
        "publishing_handoff_preview": publishing_handoff_preview(payload),
        "reproducibility_handoff_preview": reproducibility_handoff_preview(payload),
        "automatic_import": False,
        "automatic_publication": False,
        "workspace_persisted": False,
        "guardrails": guardrails(),
    }
    basis = {
        "composition_id": composition["composition_id"],
        "composition_fingerprint_sha256": composition["composition_fingerprint_sha256"],
    }
    body["export_id"] = "package-composer-export:" + _fp(basis)[:32]
    body["export_fingerprint_sha256"] = _fp(basis)
    return {
        **body,
        "filename": "sustainable-catalyst-research-package-composition.json",
        "media_type": "application/json",
        "content": json.dumps(body, ensure_ascii=False, sort_keys=True, indent=2),
    }
