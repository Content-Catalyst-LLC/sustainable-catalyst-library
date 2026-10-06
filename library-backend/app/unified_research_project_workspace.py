from __future__ import annotations

import hashlib
import json
from typing import Any

LIBRARY_VERSION = "6.30.0"
BACKEND_VERSION = "3.30.0"
WEB_VERSION = "2.30.0"
SDK_VERSION = "1.30.0"

CONTRACT = "sc-library-unified-research-project-workspace/1.0"
READINESS_CONTRACT = "sc-library-unified-research-project-workspace-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-unified-research-project-workspace-bootstrap/1.0"
PROJECT_CONTRACT = "sc-library-unified-research-project/1.0"
VALIDATION_CONTRACT = "sc-library-unified-research-project-validation/1.0"
INVENTORY_CONTRACT = "sc-library-unified-research-project-inventory/1.0"
AUTHORITY_AUDIT_CONTRACT = "sc-library-unified-research-project-authority-audit/1.0"
DEPENDENCY_CONTRACT = "sc-library-unified-research-project-dependency-summary/1.0"
HANDOFF_CONTRACT = "sc-library-unified-research-project-handoff-manifest/1.0"
EXPORT_CONTRACT = "sc-library-unified-research-project-export/1.0"

COMPONENT_TYPES = (
    "research-question", "investigation", "source", "claim", "evidence", "dataset",
    "statistical-result", "place", "event", "annotation", "citation", "corpus",
    "entity", "synthesis", "research-package", "publication", "knowledge-graph",
    "workspace-handoff", "cross-product-certification", "artifact", "other",
)

DEFAULT_AUTHORITIES = {
    "research-question": "research-investigation",
    "investigation": "research-investigation",
    "source": "catalog-source-ingestion",
    "claim": "evidence-matrix",
    "evidence": "provenance-citation-evidence-graph",
    "dataset": "structured-evidence-statistical-evidence",
    "statistical-result": "statistical-evidence",
    "place": "geospatial-research",
    "event": "historical-event-timeline",
    "annotation": "research-annotations",
    "citation": "citation-workspace",
    "corpus": "corpus-computational-linguistics",
    "entity": "entity-place-resolution",
    "synthesis": "research-synthesis",
    "research-package": "research-package-composer",
    "publication": "research-publication-studio",
    "knowledge-graph": "unified-research-knowledge-graph",
    "workspace-handoff": "library-workspace-research-integration",
    "cross-product-certification": "cross-product-research-certification",
    "artifact": "artifact-service",
    "other": "originating-authority",
}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return hashlib.sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, list) else []


def _clean(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def guardrails() -> dict[str, Any]:
    return {
        "workspace_is_new_project_persistence_authority": False,
        "workspace_is_new_domain_object_authority": False,
        "workspace_is_execution_authority": False,
        "existing_domain_authorities_preserved": True,
        "python_research_state_postgresql_remains_project_persistence_authority": True,
        "component_payloads_are_references_not_copies_by_default": True,
        "project_completeness_implies_research_quality": False,
        "project_coverage_implies_truth": False,
        "dependency_count_implies_importance": False,
        "automatic_component_persistence": False,
        "automatic_cross_product_execution": False,
        "automatic_result_import": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "server_side_workspace_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "project-manifest", "typed-component-references", "authority-lineage",
        "component-inventory", "coverage-diagnostics", "dependency-summary",
        "cross-product-handoff-manifest", "portable-project-export",
    ]
    basis = {"resources": resources, "component_types": COMPONENT_TYPES, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "workspace_id": "unified-research-project-workspace:" + _fp(basis)[:32],
        "workspace_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/project",
        "api_base": "/api/library/v1/research-project-workspace",
        "project_persistence_authority": "python-research-state-postgresql",
        "resources": resources,
        "component_types": list(COMPONENT_TYPES),
        "next_release": "6.31.0",
        "next_release_name": "Research Dependency & Lineage Graph",
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
        "project_manifest_ready": True,
        "typed_component_inventory_ready": True,
        "authority_audit_ready": True,
        "dependency_summary_ready": True,
        "cross_product_handoff_manifest_ready": True,
        "existing_project_persistence_authority_preserved": True,
        "server_side_workspace_persistence": False,
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
        "route": "/research/project",
        "readiness": readiness(),
        "operations": ["compose", "validate", "inventory", "authority-audit", "dependency-summary", "handoff-manifest", "export"],
        "component_types": list(COMPONENT_TYPES),
        "default_authorities": dict(DEFAULT_AUTHORITIES),
        "browser_storage_key": "sc-library-unified-research-project-workspace-v1",
        "guardrails": guardrails(),
    }


def _normalize_component(raw: Any, index: int) -> dict[str, Any]:
    item = _dict(raw)
    component_type = _clean(item.get("type") or item.get("component_type") or item.get("kind"))
    if component_type not in COMPONENT_TYPES:
        raise ValueError(f"components[{index}] has unsupported type: {component_type}")
    object_id = _clean(item.get("object_id") or item.get("id") or item.get("ref"))
    if not object_id:
        raise ValueError(f"components[{index}] requires object_id/id/ref")
    depends_on: list[str] = []
    for dep in _list(item.get("depends_on")):
        value = _clean(dep if not isinstance(dep, dict) else dep.get("object_id") or dep.get("id") or dep.get("ref"))
        if value and value not in depends_on:
            depends_on.append(value)
    return {
        "object_id": object_id,
        "type": component_type,
        "title": _clean(item.get("title")),
        "authority": _clean(item.get("authority")) or DEFAULT_AUTHORITIES[component_type],
        "version": _clean(item.get("version")),
        "status": _clean(item.get("status")) or "active",
        "api_ref": _clean(item.get("api_ref")),
        "content_fingerprint_sha256": _clean(item.get("content_fingerprint_sha256")),
        "depends_on": depends_on,
        "provenance": _dict(item.get("provenance")),
        "metadata": _dict(item.get("metadata")),
    }


def compose_project(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    title = _clean(payload.get("title")) or "Unified research project"
    library_project_id = _clean(payload.get("library_project_id") or payload.get("project_id"))
    components = [_normalize_component(v, i) for i, v in enumerate(_list(payload.get("components")))]
    if not library_project_id and not components:
        raise ValueError("library_project_id or at least one component is required")
    ids = [c["object_id"] for c in components]
    if len(ids) != len(set(ids)):
        raise ValueError("component object_id values must be unique")
    project = {
        "title": title,
        "library_project_id": library_project_id,
        "research_question": _clean(payload.get("research_question")),
        "description": _clean(payload.get("description")),
        "scope": _clean(payload.get("scope")),
        "status": _clean(payload.get("status")) or "active",
        "components": components,
        "cross_product_context": _dict(payload.get("cross_product_context")),
        "metadata": _dict(payload.get("metadata")),
    }
    fp = _fp(project)
    return {
        "schema": PROJECT_CONTRACT,
        "project_manifest_id": "unified-research-project:" + fp[:32],
        "project_manifest_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        **project,
        "component_count": len(components),
        "persisted": False,
        "persistence_authority": "python-research-state-postgresql",
        "guardrails": guardrails(),
    }


def validate_project(payload: dict[str, Any]) -> dict[str, Any]:
    project = _dict(payload.get("project")) if "project" in _dict(payload) else _dict(payload)
    errors: list[str] = []
    warnings: list[str] = []
    if project.get("schema") != PROJECT_CONTRACT:
        errors.append("schema must be sc-library-unified-research-project/1.0")
    components = _list(project.get("components"))
    ids: list[str] = []
    for i, raw in enumerate(components):
        item = _dict(raw)
        object_id = _clean(item.get("object_id"))
        component_type = _clean(item.get("type"))
        authority = _clean(item.get("authority"))
        if not object_id:
            errors.append(f"components[{i}] object_id is required")
        else:
            ids.append(object_id)
        if component_type not in COMPONENT_TYPES:
            errors.append(f"components[{i}] type is unsupported")
        if not authority:
            errors.append(f"components[{i}] authority is required")
    if len(ids) != len(set(ids)):
        errors.append("duplicate component object_id")
    known = set(ids)
    dangling = []
    for raw in components:
        item = _dict(raw)
        for dep in _list(item.get("depends_on")):
            if dep not in known:
                dangling.append({"object_id": item.get("object_id"), "depends_on": dep})
    if dangling:
        warnings.append("project contains dependency references outside the current manifest")
    if not project.get("library_project_id"):
        warnings.append("library_project_id is not bound; manifest remains portable composition")
    return {
        "schema": VALIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "dangling_dependencies": dangling,
        "component_count": len(components),
        "authority_boundaries_preserved": True,
        "persisted": False,
        "guardrails": guardrails(),
    }


def inventory(payload: dict[str, Any]) -> dict[str, Any]:
    project = _dict(payload.get("project")) if "project" in _dict(payload) else _dict(payload)
    components = _list(project.get("components"))
    by_type: dict[str, int] = {}
    by_authority: dict[str, int] = {}
    for raw in components:
        item = _dict(raw)
        t = _clean(item.get("type")) or "other"
        a = _clean(item.get("authority")) or "unspecified"
        by_type[t] = by_type.get(t, 0) + 1
        by_authority[a] = by_authority.get(a, 0) + 1
    coverage = {
        "question_or_investigation": any(by_type.get(x, 0) for x in ("research-question", "investigation")),
        "sources_or_citations": any(by_type.get(x, 0) for x in ("source", "citation")),
        "claims_or_evidence": any(by_type.get(x, 0) for x in ("claim", "evidence")),
        "data_or_results": any(by_type.get(x, 0) for x in ("dataset", "statistical-result")),
        "synthesis": by_type.get("synthesis", 0) > 0,
        "package_or_publication": any(by_type.get(x, 0) for x in ("research-package", "publication")),
    }
    return {
        "schema": INVENTORY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "component_count": len(components),
        "by_type": dict(sorted(by_type.items())),
        "by_authority": dict(sorted(by_authority.items())),
        "coverage": coverage,
        "coverage_complete": all(coverage.values()),
        "coverage_complete_implies_research_quality": False,
        "guardrails": guardrails(),
    }


def authority_audit(payload: dict[str, Any]) -> dict[str, Any]:
    project = _dict(payload.get("project")) if "project" in _dict(payload) else _dict(payload)
    rows = []
    issues = []
    for raw in _list(project.get("components")):
        item = _dict(raw)
        expected = DEFAULT_AUTHORITIES.get(str(item.get("type") or ""), "originating-authority")
        actual = _clean(item.get("authority"))
        rows.append({
            "object_id": item.get("object_id"),
            "type": item.get("type"),
            "authority": actual,
            "default_authority": expected,
            "authority_explicit": bool(actual),
            "authority_preserved": bool(actual),
        })
        if not actual:
            issues.append(f"{item.get('object_id')}: missing authority")
    return {
        "schema": AUTHORITY_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "pass" if not issues else "review-required",
        "issues": issues,
        "rows": rows,
        "workspace_claims_component_authority": False,
        "project_persistence_authority": "python-research-state-postgresql",
        "guardrails": guardrails(),
    }


def dependency_summary(payload: dict[str, Any]) -> dict[str, Any]:
    project = _dict(payload.get("project")) if "project" in _dict(payload) else _dict(payload)
    components = [_dict(x) for x in _list(project.get("components"))]
    known = {str(x.get("object_id")) for x in components if x.get("object_id")}
    edges = []
    external = []
    incoming = {node: 0 for node in known}
    outgoing = {node: 0 for node in known}
    for item in components:
        source = str(item.get("object_id") or "")
        for dep in _list(item.get("depends_on")):
            edge = {"from": source, "to": dep, "relation": "depends-on"}
            edges.append(edge)
            outgoing[source] = outgoing.get(source, 0) + 1
            if dep in known:
                incoming[dep] = incoming.get(dep, 0) + 1
            else:
                external.append(edge)
    return {
        "schema": DEPENDENCY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "node_count": len(known),
        "edge_count": len(edges),
        "edges": edges,
        "external_dependencies": external,
        "roots": sorted([node for node in known if outgoing.get(node, 0) == 0]),
        "leaves": sorted([node for node in known if incoming.get(node, 0) == 0]),
        "dependency_count_implies_importance": False,
        "guardrails": guardrails(),
    }


def handoff_manifest(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    project = _dict(payload.get("project"))
    target_product = _clean(payload.get("target_product"))
    if not target_product:
        raise ValueError("target_product is required")
    selected_ids = [str(x) for x in _list(payload.get("component_ids")) if str(x).strip()]
    components = [_dict(x) for x in _list(project.get("components"))]
    selected = [x for x in components if x.get("object_id") in selected_ids] if selected_ids else components
    basis = {
        "project_manifest_id": project.get("project_manifest_id"),
        "library_project_id": project.get("library_project_id"),
        "target_product": target_product,
        "components": selected,
        "intent": _clean(payload.get("intent")) or "continue-research",
    }
    fp = _fp(basis)
    return {
        "schema": HANDOFF_CONTRACT,
        "handoff_manifest_id": "project-handoff-manifest:" + fp[:32],
        "handoff_manifest_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "target_product": target_product,
        "intent": basis["intent"],
        "library_project_id": project.get("library_project_id"),
        "project_manifest_id": project.get("project_manifest_id"),
        "component_refs": [{
            "object_id": x.get("object_id"),
            "type": x.get("type"),
            "authority": x.get("authority"),
            "version": x.get("version"),
            "content_fingerprint_sha256": x.get("content_fingerprint_sha256"),
            "provenance": x.get("provenance") or {},
        } for x in selected],
        "automatic_delivery": False,
        "remote_execution_started": False,
        "persisted": False,
        "guardrails": guardrails(),
    }


def export_project(payload: dict[str, Any]) -> dict[str, Any]:
    project = _dict(payload.get("project")) if "project" in _dict(payload) else _dict(payload)
    content_obj = {
        "schema": "sc-library-unified-research-project-export-bundle/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "project": project,
        "validation": validate_project({"project": project}),
        "inventory": inventory({"project": project}),
        "authority_audit": authority_audit({"project": project}),
        "dependency_summary": dependency_summary({"project": project}),
        "guardrails": guardrails(),
    }
    content = json.dumps(content_obj, sort_keys=True, indent=2, ensure_ascii=False) + "\n"
    digest = hashlib.sha256(content.encode("utf-8")).hexdigest()
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-unified-research-project.json",
        "media_type": "application/json",
        "sha256": digest,
        "content": content,
        "persisted": False,
        "guardrails": guardrails(),
    }
