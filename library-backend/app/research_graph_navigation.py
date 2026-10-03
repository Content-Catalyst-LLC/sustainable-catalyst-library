from __future__ import annotations

from collections import Counter, deque
from hashlib import sha256
import json
from typing import Any, Iterable

from .provenance_graph_service import evidence_graph, readiness as provenance_graph_readiness

LIBRARY_VERSION = "6.6.0"
BACKEND_VERSION = "3.6.0"
CONTRACT = "sc-library-research-graph-evidence-navigation/1.0"
READINESS_CONTRACT = "sc-library-research-graph-evidence-navigation-readiness/1.0"
NEIGHBORHOOD_CONTRACT = "sc-library-research-graph-neighborhood/1.0"
SUMMARY_CONTRACT = "sc-library-research-graph-summary/1.0"
PATH_CONTRACT = "sc-library-research-evidence-path/1.0"

ALLOWED_EDGE_FAMILIES = {"relationship", "citation"}


def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _clean(value: Any, limit: int = 500) -> str:
    return str(value or "").strip()[:limit]


def _bounded_int(value: Any, default: int, minimum: int, maximum: int) -> int:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        parsed = default
    return max(minimum, min(maximum, parsed))


def _edge_family(edge: dict[str, Any]) -> str:
    family = _clean(edge.get("edge_family"), 80).lower()
    if family in ALLOWED_EDGE_FAMILIES:
        return family
    if edge.get("citation_id") or edge.get("citing_record_id") or edge.get("cited_record_id"):
        return "citation"
    return "relationship"


def _edge_endpoints(edge: dict[str, Any]) -> tuple[str, str]:
    if _edge_family(edge) == "citation":
        source = _clean(edge.get("citing_record_id") or edge.get("source_record_id") or edge.get("source"), 512)
        target = _clean(edge.get("cited_record_id") or edge.get("target_record_id") or edge.get("target"), 512)
    else:
        source = _clean(edge.get("source_record_id") or edge.get("source") or edge.get("citing_record_id"), 512)
        target = _clean(edge.get("target_record_id") or edge.get("target") or edge.get("cited_record_id"), 512)
    return source, target


def _edge_kind(edge: dict[str, Any]) -> str:
    for key in ("relationship_type", "relation_type", "edge_type", "citation_type", "kind", "type"):
        value = _clean(edge.get(key), 160)
        if value:
            return value
    return _edge_family(edge)


def _edge_id(edge: dict[str, Any], ordinal: int = 0) -> str:
    explicit = _clean(edge.get("edge_id") or edge.get("citation_id"), 512)
    if explicit:
        return explicit
    source, target = _edge_endpoints(edge)
    basis = {"family": _edge_family(edge), "source": source, "target": target, "kind": _edge_kind(edge), "ordinal": ordinal}
    return "research-graph-edge:" + _fp(basis)[:32]


def _selected_families(value: Any) -> set[str]:
    if value in (None, "", []):
        return set(ALLOWED_EDGE_FAMILIES)
    raw: Iterable[Any]
    if isinstance(value, str):
        raw = [x for x in value.split(",")]
    elif isinstance(value, (list, tuple, set)):
        raw = value
    else:
        raw = []
    selected = {_clean(x, 80).lower() for x in raw}
    selected &= ALLOWED_EDGE_FAMILIES
    return selected or set(ALLOWED_EDGE_FAMILIES)


def guardrails() -> dict[str, bool]:
    return {
        "research_graph_is_navigation_composition": True,
        "provenance_graph_service_remains_graph_authority": True,
        "citation_graph_remains_citation_authority": True,
        "library_edges_remain_relationship_authority": True,
        "graph_connectivity_implies_truth": False,
        "graph_connectivity_implies_causality": False,
        "citation_implies_support": False,
        "shorter_path_implies_stronger_evidence": False,
        "path_is_evidence_quality_score": False,
        "edge_family_is_preserved": True,
        "public_visibility_boundary_preserved": True,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "record-neighborhoods",
        "evidence-paths",
        "edge-family-filters",
        "graph-summaries",
        "record-navigation-links",
        "provenance-aware-evidence-trails",
    ]
    basis = {
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "resources": resources,
        "edge_families": sorted(ALLOWED_EDGE_FAMILIES),
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "service_id": "library-research-graph-navigation:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "graph_authority": "provenance-graph-service",
        "edge_families": sorted(ALLOWED_EDGE_FAMILIES),
        "resources": resources,
        "database_migration_required": False,
        "wordpress": {"role": "optional-adapter", "required": False, "authoritative": False},
        "guardrails": guardrails(),
    }


def _decorate_node(node: dict[str, Any]) -> dict[str, Any]:
    record_id = _clean(node.get("record_id") or node.get("id") or node.get("object_id"), 512)
    out = dict(node)
    if record_id:
        out["record_id"] = record_id
        out["navigation"] = {
            "record_route": "/record/" + record_id,
            "research_graph_route": "/api/library/v1/research-graph/records/" + record_id + "/neighborhood",
        }
    return out


def _filter_graph(raw: dict[str, Any], families: set[str]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    selected_edges: list[dict[str, Any]] = []
    connected: set[str] = set()
    root = _clean(raw.get("root_record_id"), 512)
    if root:
        connected.add(root)
    for ordinal, edge in enumerate(raw.get("edges") or []):
        if not isinstance(edge, dict):
            continue
        family = _edge_family(edge)
        if family not in families:
            continue
        source, target = _edge_endpoints(edge)
        if not source or not target:
            continue
        normalized = dict(edge)
        normalized["edge_family"] = family
        normalized["edge_kind"] = _edge_kind(edge)
        normalized["edge_id"] = _edge_id(edge, ordinal)
        normalized["source_record_id"] = source
        normalized["target_record_id"] = target
        selected_edges.append(normalized)
        connected.update((source, target))
    nodes = []
    for node in raw.get("nodes") or []:
        if not isinstance(node, dict):
            continue
        record_id = _clean(node.get("record_id") or node.get("id") or node.get("object_id"), 512)
        if record_id and record_id in connected:
            nodes.append(_decorate_node(node))
    return nodes, selected_edges


def neighborhood(
    record_id: str,
    *,
    depth: int = 2,
    limit: int = 250,
    include_core: bool = True,
    edge_families: Any = None,
) -> dict[str, Any]:
    record_id = _clean(record_id, 512)
    if not record_id:
        raise ValueError("record_id is required")
    depth = _bounded_int(depth, 2, 1, 4)
    limit = _bounded_int(limit, 250, 1, 1000)
    families = _selected_families(edge_families)
    raw = evidence_graph(record_id, depth=depth, limit=limit, include_core=bool(include_core))
    nodes, edges = _filter_graph(raw, families)
    family_counts = Counter(_edge_family(edge) for edge in edges)
    kind_counts = Counter(_edge_kind(edge) for edge in edges)
    basis = {
        "root": record_id,
        "depth": depth,
        "families": sorted(families),
        "nodes": sorted(_clean(x.get("record_id"), 512) for x in nodes),
        "edges": sorted(_clean(x.get("edge_id"), 512) for x in edges),
    }
    return {
        "schema": NEIGHBORHOOD_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend-composition",
        "root_record_id": record_id,
        "depth": depth,
        "limit": limit,
        "include_core": bool(include_core),
        "edge_families": sorted(families),
        "nodes": nodes,
        "edges": edges,
        "node_count": len(nodes),
        "edge_count": len(edges),
        "edge_family_counts": dict(sorted(family_counts.items())),
        "edge_kind_counts": dict(sorted(kind_counts.items())),
        "graph_fingerprint_sha256": _fp(basis),
        "guardrails": guardrails(),
    }


def summary(record_id: str, *, depth: int = 1, limit: int = 250, include_core: bool = True) -> dict[str, Any]:
    graph = neighborhood(record_id, depth=depth, limit=limit, include_core=include_core)
    root = graph["root_record_id"]
    degree: Counter[str] = Counter()
    for edge in graph["edges"]:
        source, target = _edge_endpoints(edge)
        if source:
            degree[source] += 1
        if target:
            degree[target] += 1
    root_degree = int(degree.get(root, 0))
    most_connected = sorted(
        ({"record_id": record_id, "degree": count} for record_id, count in degree.items()),
        key=lambda x: (-x["degree"], x["record_id"]),
    )[:20]
    basis = {
        "graph_fingerprint_sha256": graph["graph_fingerprint_sha256"],
        "root_degree": root_degree,
        "most_connected": most_connected,
    }
    return {
        "schema": SUMMARY_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "root_record_id": root,
        "node_count": graph["node_count"],
        "edge_count": graph["edge_count"],
        "root_degree": root_degree,
        "edge_family_counts": graph["edge_family_counts"],
        "edge_kind_counts": graph["edge_kind_counts"],
        "most_connected": most_connected,
        "summary_fingerprint_sha256": _fp(basis),
        "guardrails": guardrails(),
    }


def _adjacency(edges: list[dict[str, Any]], direction: str) -> dict[str, list[tuple[str, int]]]:
    out: dict[str, list[tuple[str, int]]] = {}
    for idx, edge in enumerate(edges):
        source, target = _edge_endpoints(edge)
        if not source or not target:
            continue
        out.setdefault(source, [])
        out.setdefault(target, [])
        if direction in {"both", "forward"}:
            out[source].append((target, idx))
        if direction in {"both", "reverse"}:
            out[target].append((source, idx))
    for key in out:
        out[key].sort(key=lambda item: (item[0], item[1]))
    return out


def path(payload: dict[str, Any] | None) -> dict[str, Any]:
    raw = dict(payload or {})
    source_record_id = _clean(raw.get("source_record_id") or raw.get("source"), 512)
    target_record_id = _clean(raw.get("target_record_id") or raw.get("target"), 512)
    if not source_record_id or not target_record_id:
        raise ValueError("source_record_id and target_record_id are required")
    depth = _bounded_int(raw.get("max_depth"), 4, 1, 4)
    limit = _bounded_int(raw.get("limit"), 1000, 1, 1000)
    direction = _clean(raw.get("direction") or "both", 20).lower()
    if direction not in {"both", "forward", "reverse"}:
        direction = "both"
    families = _selected_families(raw.get("edge_families"))
    graph = neighborhood(
        source_record_id,
        depth=depth,
        limit=limit,
        include_core=bool(raw.get("include_core", True)),
        edge_families=families,
    )
    adjacency = _adjacency(graph["edges"], direction)
    queue: deque[str] = deque([source_record_id])
    parent: dict[str, tuple[str | None, int | None]] = {source_record_id: (None, None)}
    while queue and target_record_id not in parent:
        current = queue.popleft()
        for nxt, edge_idx in adjacency.get(current, []):
            if nxt in parent:
                continue
            parent[nxt] = (current, edge_idx)
            queue.append(nxt)
            if nxt == target_record_id:
                break
    found = target_record_id in parent
    node_ids: list[str] = []
    edge_indexes: list[int] = []
    if found:
        cursor: str | None = target_record_id
        while cursor is not None:
            node_ids.append(cursor)
            previous, edge_idx = parent[cursor]
            if edge_idx is not None:
                edge_indexes.append(edge_idx)
            cursor = previous
        node_ids.reverse()
        edge_indexes.reverse()
    node_map = {_clean(node.get("record_id"), 512): node for node in graph["nodes"]}
    path_nodes = [node_map.get(record_id, {"record_id": record_id, "navigation": {"record_route": "/record/" + record_id}}) for record_id in node_ids]
    path_edges = [graph["edges"][idx] for idx in edge_indexes]
    basis = {
        "source": source_record_id,
        "target": target_record_id,
        "direction": direction,
        "families": sorted(families),
        "node_ids": node_ids,
        "edge_ids": [_clean(x.get("edge_id"), 512) for x in path_edges],
        "graph": graph["graph_fingerprint_sha256"],
    }
    return {
        "schema": PATH_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "source_record_id": source_record_id,
        "target_record_id": target_record_id,
        "found": found,
        "direction": direction,
        "max_depth": depth,
        "edge_families": sorted(families),
        "hop_count": len(path_edges) if found else None,
        "nodes": path_nodes,
        "edges": path_edges,
        "path_fingerprint_sha256": _fp(basis),
        "graph_fingerprint_sha256": graph["graph_fingerprint_sha256"],
        "interpretation": {
            "path_is_navigation_not_truth_judgment": True,
            "shorter_path_is_not_stronger_evidence": True,
            "citation_edge_does_not_imply_support": True,
            "relationship_edge_does_not_imply_causality": True,
        },
        "guardrails": guardrails(),
    }


def readiness() -> dict[str, Any]:
    provenance = provenance_graph_readiness()
    state = "ready" if provenance.get("state") in {"ready", "degraded"} else "degraded"
    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": state,
        "authority": "python-backend-composition",
        "database_migration_required": False,
        "edge_families": sorted(ALLOWED_EDGE_FAMILIES),
        "capabilities": {
            "record_neighborhoods": True,
            "evidence_paths": True,
            "deterministic_shortest_path": True,
            "edge_family_filters": True,
            "graph_summaries": True,
            "reader_navigation_links": True,
            "native_graph_runtime_reused": True,
        },
        "provenance_graph": {
            "state": provenance.get("state"),
            "database": provenance.get("database"),
            "counts": provenance.get("counts") or {},
        },
        "guardrails": guardrails(),
    }
