from __future__ import annotations

import hashlib
import json
from collections import deque
from typing import Any

LIBRARY_VERSION = "6.31.0"
BACKEND_VERSION = "3.31.0"
WEB_VERSION = "2.31.0"
SDK_VERSION = "1.31.0"

CONTRACT = "sc-library-research-dependency-lineage-graph/1.0"
READINESS_CONTRACT = "sc-library-research-dependency-lineage-graph-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-research-dependency-lineage-graph-bootstrap/1.0"
GRAPH_CONTRACT = "sc-library-research-dependency-lineage-graph-snapshot/1.0"
VALIDATION_CONTRACT = "sc-library-research-dependency-lineage-validation/1.0"
LINEAGE_CONTRACT = "sc-library-research-lineage-traversal/1.0"
NEIGHBORHOOD_CONTRACT = "sc-library-research-lineage-neighborhood/1.0"
PATH_CONTRACT = "sc-library-research-lineage-path/1.0"
IMPACT_CONTRACT = "sc-library-research-lineage-impact-analysis/1.0"
PROVENANCE_AUDIT_CONTRACT = "sc-library-research-lineage-provenance-audit/1.0"
EXPORT_CONTRACT = "sc-library-research-dependency-lineage-export/1.0"

RELATION_TYPES = (
    "depends-on",
    "derived-from",
    "cites",
    "supports",
    "contradicts",
    "qualifies",
    "uses-input",
    "produced-by",
    "references",
    "supersedes",
    "version-of",
    "other",
)

PROVENANCE_REFERENCE_KEYS = (
    ("derived_from", "derived-from"),
    ("parent_ids", "derived-from"),
    ("source_refs", "references"),
    ("input_refs", "uses-input"),
    ("citation_refs", "cites"),
)


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
        "lineage_graph_is_new_domain_object_authority": False,
        "lineage_graph_is_project_persistence_authority": False,
        "lineage_graph_is_execution_authority": False,
        "lineage_edges_require_explicit_source_material": True,
        "automatic_semantic_edge_inference": False,
        "automatic_causal_edge_inference": False,
        "automatic_missing_lineage_fabrication": False,
        "cycles_are_auto_resolved": False,
        "external_dependencies_are_auto_imported": False,
        "dependency_count_implies_importance": False,
        "centrality_implies_research_quality": False,
        "lineage_completeness_implies_truth": False,
        "path_existence_implies_causality": False,
        "impact_analysis_implies_scientific_effect": False,
        "existing_object_authorities_preserved": True,
        "python_research_state_postgresql_remains_project_persistence_authority": True,
        "server_side_lineage_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "project-dependency-graph",
        "explicit-provenance-lineage",
        "external-boundary-references",
        "cycle-and-dangling-reference-diagnostics",
        "ancestor-descendant-traversal",
        "lineage-neighborhood",
        "explicit-path-tracing",
        "downstream-impact-analysis",
        "provenance-coverage-audit",
        "deterministic-lineage-export",
    ]
    basis = {"resources": resources, "relation_types": RELATION_TYPES, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "graph_id": "research-dependency-lineage-graph:" + _fp(basis)[:32],
        "graph_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/project/lineage",
        "api_base": "/api/library/v1/research-lineage-graph",
        "project_source_contract": "sc-library-unified-research-project/1.0",
        "project_persistence_authority": "python-research-state-postgresql",
        "resources": resources,
        "relation_types": list(RELATION_TYPES),
        "next_release": "6.32.0",
        "next_release_name": "Research Review, Revision & Versioning System",
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
        "dependency_graph_ready": True,
        "provenance_lineage_ready": True,
        "cycle_detection_ready": True,
        "ancestor_descendant_traversal_ready": True,
        "path_tracing_ready": True,
        "impact_analysis_ready": True,
        "provenance_audit_ready": True,
        "existing_object_authorities_preserved": True,
        "server_side_lineage_persistence": False,
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
        "route": "/research/project/lineage",
        "readiness": readiness(),
        "operations": [
            "build",
            "validate",
            "lineage",
            "neighborhood",
            "path",
            "impact-analysis",
            "provenance-audit",
            "export",
        ],
        "relation_types": list(RELATION_TYPES),
        "browser_storage_key": "sc-library-research-dependency-lineage-graph-v1",
        "guardrails": guardrails(),
    }


def _node(raw: Any, index: int) -> dict[str, Any]:
    item = _dict(raw)
    object_id = _clean(item.get("object_id") or item.get("id") or item.get("ref"))
    if not object_id:
        raise ValueError(f"components[{index}] requires object_id/id/ref")
    return {
        "object_id": object_id,
        "type": _clean(item.get("type") or item.get("component_type") or item.get("kind")) or "other",
        "title": _clean(item.get("title")),
        "authority": _clean(item.get("authority")) or "originating-authority",
        "version": _clean(item.get("version")),
        "status": _clean(item.get("status")) or "active",
        "content_fingerprint_sha256": _clean(item.get("content_fingerprint_sha256")),
        "provenance": _dict(item.get("provenance")),
        "metadata": _dict(item.get("metadata")),
    }


def _edge(source: str, target: str, relation: str, origin: str, authority: str | None = None, metadata: dict[str, Any] | None = None) -> dict[str, Any]:
    if relation not in RELATION_TYPES:
        relation = "other"
    basis = {
        "source": source,
        "target": target,
        "relation": relation,
        "origin": origin,
        "authority": authority,
        "metadata": metadata or {},
    }
    return {
        "edge_id": "lineage-edge:" + _fp(basis)[:32],
        "source": source,
        "target": target,
        "relation": relation,
        "origin": origin,
        "authority": authority,
        "metadata": metadata or {},
        "explicit": True,
        "inferred": False,
    }


def _explicit_ref_values(value: Any) -> list[str]:
    refs: list[str] = []
    for raw in _list(value):
        if isinstance(raw, dict):
            ref = _clean(raw.get("object_id") or raw.get("id") or raw.get("ref"))
        else:
            ref = _clean(raw)
        if ref and ref not in refs:
            refs.append(ref)
    return refs


def _build_edges(project: dict[str, Any], extra_relations: list[Any]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    components = [_dict(x) for x in _list(project.get("components"))]
    known = {str(x.get("object_id")) for x in components if x.get("object_id")}
    edges: list[dict[str, Any]] = []
    seen: set[tuple[str, str, str, str]] = set()

    def add(edge: dict[str, Any]) -> None:
        key = (edge["source"], edge["target"], edge["relation"], edge["origin"])
        if key not in seen:
            seen.add(key)
            edges.append(edge)

    for item in components:
        source = _clean(item.get("object_id"))
        if not source:
            continue
        authority = _clean(item.get("authority"))
        for target in _explicit_ref_values(item.get("depends_on")):
            add(_edge(source, target, "depends-on", "project-component.depends_on", authority))
        provenance = _dict(item.get("provenance"))
        for key, relation in PROVENANCE_REFERENCE_KEYS:
            for target in _explicit_ref_values(provenance.get(key)):
                add(_edge(source, target, relation, f"project-component.provenance.{key}", authority))

    for index, raw in enumerate(extra_relations):
        rel = _dict(raw)
        source = _clean(rel.get("source") or rel.get("from"))
        target = _clean(rel.get("target") or rel.get("to"))
        relation = _clean(rel.get("relation") or rel.get("type")) or "other"
        if not source or not target:
            raise ValueError(f"relations[{index}] requires source and target")
        add(_edge(source, target, relation, "explicit-relations", _clean(rel.get("authority")), _dict(rel.get("metadata"))))

    external = []
    for edge in edges:
        if edge["target"] not in known:
            external.append({
                "ref": edge["target"],
                "referenced_by": edge["source"],
                "relation": edge["relation"],
                "origin": edge["origin"],
            })
    return edges, external


def _adjacency(nodes: list[dict[str, Any]], edges: list[dict[str, Any]]) -> tuple[dict[str, list[str]], dict[str, list[str]]]:
    ids = {n["object_id"] for n in nodes}
    outgoing = {node: [] for node in ids}
    incoming = {node: [] for node in ids}
    for edge in edges:
        s, t = edge["source"], edge["target"]
        if s in ids and t in ids:
            if t not in outgoing[s]:
                outgoing[s].append(t)
            if s not in incoming[t]:
                incoming[t].append(s)
    for mapping in (outgoing, incoming):
        for key in mapping:
            mapping[key].sort()
    return outgoing, incoming


def _cycles(nodes: list[dict[str, Any]], edges: list[dict[str, Any]]) -> list[list[str]]:
    outgoing, _ = _adjacency(nodes, edges)
    visited: set[str] = set()
    active: set[str] = set()
    stack: list[str] = []
    found: set[tuple[str, ...]] = set()

    def visit(node: str) -> None:
        visited.add(node)
        active.add(node)
        stack.append(node)
        for nxt in outgoing.get(node, []):
            if nxt not in visited:
                visit(nxt)
            elif nxt in active:
                i = stack.index(nxt)
                cycle = stack[i:] + [nxt]
                body = cycle[:-1]
                if body:
                    rotations = [tuple(body[i:] + body[:i]) for i in range(len(body))]
                    canonical = min(rotations)
                    found.add(canonical + (canonical[0],))
        stack.pop()
        active.remove(node)

    for node in sorted(outgoing):
        if node not in visited:
            visit(node)
    return [list(c) for c in sorted(found)]


def build_graph(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    project = _dict(payload.get("project")) if "project" in payload else payload
    components = [_node(x, i) for i, x in enumerate(_list(project.get("components")))]
    if not components:
        raise ValueError("project must contain at least one component")
    ids = [x["object_id"] for x in components]
    if len(ids) != len(set(ids)):
        raise ValueError("project component object_id values must be unique")
    edges, external = _build_edges(project, _list(payload.get("relations")))
    cycles = _cycles(components, edges)
    outgoing, incoming = _adjacency(components, edges)
    node_rows = []
    for node in components:
        object_id = node["object_id"]
        node_rows.append({
            **node,
            "outgoing_dependency_count": len(outgoing.get(object_id, [])),
            "incoming_dependent_count": len(incoming.get(object_id, [])),
        })
    basis = {
        "project_manifest_id": project.get("project_manifest_id"),
        "library_project_id": project.get("library_project_id"),
        "nodes": node_rows,
        "edges": edges,
        "external_references": external,
    }
    fp = _fp(basis)
    return {
        "schema": GRAPH_CONTRACT,
        "graph_snapshot_id": "research-lineage-snapshot:" + fp[:32],
        "graph_snapshot_fingerprint_sha256": fp,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "project_manifest_id": project.get("project_manifest_id"),
        "library_project_id": project.get("library_project_id"),
        "nodes": node_rows,
        "edges": edges,
        "external_references": external,
        "node_count": len(node_rows),
        "edge_count": len(edges),
        "external_reference_count": len(external),
        "cycles": cycles,
        "cycle_count": len(cycles),
        "persisted": False,
        "guardrails": guardrails(),
    }


def validate_graph(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _dict(payload.get("graph")) if "graph" in _dict(payload) else _dict(payload)
    errors: list[str] = []
    warnings: list[str] = []
    if graph.get("schema") != GRAPH_CONTRACT:
        errors.append("graph schema mismatch")
    nodes = [_dict(x) for x in _list(graph.get("nodes"))]
    ids = [str(x.get("object_id")) for x in nodes if x.get("object_id")]
    if len(ids) != len(set(ids)):
        errors.append("duplicate node object_id")
    known = set(ids)
    for i, raw in enumerate(_list(graph.get("edges"))):
        edge = _dict(raw)
        if not _clean(edge.get("source")) or not _clean(edge.get("target")):
            errors.append(f"edges[{i}] requires source and target")
        if edge.get("inferred") is True:
            errors.append(f"edges[{i}] inferred relationships are not accepted by v6.31")
        if edge.get("source") not in known:
            errors.append(f"edges[{i}] source is not an internal graph node")
        if edge.get("target") not in known:
            warnings.append(f"edges[{i}] target is an external boundary reference")
    cycles = _cycles(nodes, [_dict(x) for x in _list(graph.get("edges"))])
    if cycles:
        warnings.append("dependency cycles detected; they are preserved for review")
    return {
        "schema": VALIDATION_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "cycles": cycles,
        "cycle_count": len(cycles),
        "external_reference_count": len(_list(graph.get("external_references"))),
        "authority_boundaries_preserved": True,
        "persisted": False,
        "guardrails": guardrails(),
    }


def _walk(start: str, mapping: dict[str, list[str]], depth: int | None = None) -> list[dict[str, Any]]:
    seen = {start}
    out: list[dict[str, Any]] = []
    queue = deque([(start, 0)])
    while queue:
        node, distance = queue.popleft()
        if depth is not None and distance >= depth:
            continue
        for nxt in mapping.get(node, []):
            if nxt in seen:
                continue
            seen.add(nxt)
            out.append({"object_id": nxt, "distance": distance + 1})
            queue.append((nxt, distance + 1))
    return out


def lineage(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    graph = _dict(payload.get("graph"))
    node_id = _clean(payload.get("node_id"))
    direction = _clean(payload.get("direction")) or "ancestors"
    if not node_id:
        raise ValueError("node_id is required")
    nodes = [_dict(x) for x in _list(graph.get("nodes"))]
    ids = {str(x.get("object_id")) for x in nodes if x.get("object_id")}
    if node_id not in ids:
        raise ValueError("node_id is not an internal graph node")
    outgoing, incoming = _adjacency(nodes, [_dict(x) for x in _list(graph.get("edges"))])
    if direction == "ancestors":
        rows = _walk(node_id, outgoing)
    elif direction == "descendants":
        rows = _walk(node_id, incoming)
    else:
        raise ValueError("direction must be ancestors or descendants")
    return {
        "schema": LINEAGE_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "node_id": node_id,
        "direction": direction,
        "items": rows,
        "count": len(rows),
        "guardrails": guardrails(),
    }


def neighborhood(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    graph = _dict(payload.get("graph"))
    node_id = _clean(payload.get("node_id"))
    depth = max(0, min(int(payload.get("depth") or 1), 8))
    if not node_id:
        raise ValueError("node_id is required")
    nodes = [_dict(x) for x in _list(graph.get("nodes"))]
    ids = {str(x.get("object_id")) for x in nodes if x.get("object_id")}
    if node_id not in ids:
        raise ValueError("node_id is not an internal graph node")
    outgoing, incoming = _adjacency(nodes, [_dict(x) for x in _list(graph.get("edges"))])
    merged = {key: sorted(set(outgoing.get(key, []) + incoming.get(key, []))) for key in ids}
    rows = _walk(node_id, merged, depth)
    keep = {node_id} | {x["object_id"] for x in rows}
    return {
        "schema": NEIGHBORHOOD_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "node_id": node_id,
        "depth": depth,
        "nodes": [x for x in nodes if x.get("object_id") in keep],
        "edges": [x for x in _list(graph.get("edges")) if _dict(x).get("source") in keep and _dict(x).get("target") in keep],
        "guardrails": guardrails(),
    }


def path(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    graph = _dict(payload.get("graph"))
    source = _clean(payload.get("source"))
    target = _clean(payload.get("target"))
    directed = bool(payload.get("directed", True))
    if not source or not target:
        raise ValueError("source and target are required")
    nodes = [_dict(x) for x in _list(graph.get("nodes"))]
    ids = {str(x.get("object_id")) for x in nodes if x.get("object_id")}
    if source not in ids or target not in ids:
        raise ValueError("source and target must be internal graph nodes")
    outgoing, incoming = _adjacency(nodes, [_dict(x) for x in _list(graph.get("edges"))])
    mapping = outgoing if directed else {key: sorted(set(outgoing.get(key, []) + incoming.get(key, []))) for key in ids}
    queue = deque([source])
    previous: dict[str, str | None] = {source: None}
    while queue:
        node = queue.popleft()
        if node == target:
            break
        for nxt in mapping.get(node, []):
            if nxt not in previous:
                previous[nxt] = node
                queue.append(nxt)
    found = target in previous
    route: list[str] = []
    if found:
        cursor: str | None = target
        while cursor is not None:
            route.append(cursor)
            cursor = previous[cursor]
        route.reverse()
    return {
        "schema": PATH_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source": source,
        "target": target,
        "directed": directed,
        "found": found,
        "path": route,
        "hop_count": max(0, len(route) - 1) if found else None,
        "path_existence_implies_causality": False,
        "guardrails": guardrails(),
    }


def impact_analysis(payload: dict[str, Any]) -> dict[str, Any]:
    payload = _dict(payload)
    graph = _dict(payload.get("graph"))
    node_id = _clean(payload.get("node_id"))
    if not node_id:
        raise ValueError("node_id is required")
    nodes = [_dict(x) for x in _list(graph.get("nodes"))]
    ids = {str(x.get("object_id")) for x in nodes if x.get("object_id")}
    if node_id not in ids:
        raise ValueError("node_id is not an internal graph node")
    _, incoming = _adjacency(nodes, [_dict(x) for x in _list(graph.get("edges"))])
    affected = _walk(node_id, incoming)
    return {
        "schema": IMPACT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "node_id": node_id,
        "downstream_dependents": affected,
        "count": len(affected),
        "impact_analysis_implies_scientific_effect": False,
        "guardrails": guardrails(),
    }


def provenance_audit(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _dict(_dict(payload).get("graph")) if "graph" in _dict(payload) else _dict(payload)
    rows = []
    missing = []
    for raw in _list(graph.get("nodes")):
        node = _dict(raw)
        object_id = _clean(node.get("object_id"))
        provenance = _dict(node.get("provenance"))
        has_provenance = bool(provenance)
        has_content_hash = bool(_clean(node.get("content_fingerprint_sha256")))
        row = {
            "object_id": object_id,
            "authority": node.get("authority"),
            "has_provenance": has_provenance,
            "has_content_fingerprint": has_content_hash,
            "provenance_keys": sorted(provenance.keys()),
        }
        rows.append(row)
        if not has_provenance:
            missing.append(object_id)
    total = len(rows)
    coverage = (total - len(missing)) / total if total else 0.0
    return {
        "schema": PROVENANCE_AUDIT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "rows": rows,
        "node_count": total,
        "nodes_without_provenance": missing,
        "provenance_coverage_ratio": coverage,
        "provenance_coverage_implies_truth": False,
        "guardrails": guardrails(),
    }


def export_graph(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _dict(_dict(payload).get("graph")) if "graph" in _dict(payload) else _dict(payload)
    content_obj = {
        "schema": "sc-library-research-dependency-lineage-export-bundle/1.0",
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "graph": graph,
        "validation": validate_graph({"graph": graph}),
        "provenance_audit": provenance_audit({"graph": graph}),
        "guardrails": guardrails(),
    }
    content = json.dumps(content_obj, sort_keys=True, indent=2, ensure_ascii=False) + "\n"
    return {
        "schema": EXPORT_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "filename": "sustainable-catalyst-research-dependency-lineage-graph.json",
        "media_type": "application/json",
        "sha256": hashlib.sha256(content.encode("utf-8")).hexdigest(),
        "content": content,
        "persisted": False,
        "guardrails": guardrails(),
    }
