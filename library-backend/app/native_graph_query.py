from __future__ import annotations

from collections import Counter, defaultdict, deque
from typing import Any

from .native_graph_runtime import (
    NATIVE_GRAPH_CONTRACT,
    NATIVE_QUERY_CONTRACT,
    native_graph_query_rows,
    native_graph_runtime_status,
)
from .research_graph_pathfinding import (
    ANALYTICAL_RELATIONSHIPS,
    RELATIONSHIP_CLASSES,
    _edge_basis,
    _is_analytical,
    _node_id,
)

SUPPORTED_OPERATIONS = {
    "neighborhood",
    "reachability",
    "connected-components",
    "subgraph",
    "structural-stats",
}

# Native structural queries are broader than default evidence-path traversal.
# By default they may inspect any known non-analytical relationship class, but
# analytical/similarity/candidate edges still require explicit opt-in.
DEFAULT_STRUCTURAL_RELATIONSHIPS = {
    basis for basis in RELATIONSHIP_CLASSES if basis not in ANALYTICAL_RELATIONSHIPS
}


def _clean_ids(value: Any, *, limit: int = 10000) -> list[str]:
    if isinstance(value, str):
        raw = [value]
    elif isinstance(value, (list, tuple, set)):
        raw = list(value)
    else:
        raw = []
    out: list[str] = []
    seen: set[str] = set()
    for item in raw:
        nid = _node_id(item)
        if not nid or nid in seen:
            continue
        seen.add(nid)
        out.append(nid)
        if len(out) >= limit:
            break
    return out


def _normalize_operation(value: Any) -> str:
    raw = str(value or "neighborhood").strip().lower().replace("_", "-")
    aliases = {
        "neighbors": "neighborhood",
        "neighbourhood": "neighborhood",
        "reachable": "reachability",
        "components": "connected-components",
        "component": "connected-components",
        "induced-subgraph": "subgraph",
        "stats": "structural-stats",
        "statistics": "structural-stats",
    }
    raw = aliases.get(raw, raw)
    return raw if raw in SUPPORTED_OPERATIONS else "neighborhood"


def _allowed_edges(
    nodes: list[dict[str, Any]],
    edges: list[dict[str, Any]],
    query: dict[str, Any],
) -> tuple[list[int], set[str], bool]:
    by_id = {_node_id(n.get("id")) for n in nodes if n.get("id")}
    include_analytical = bool(query.get("include_analytical", False))
    requested = set(_clean_ids(query.get("relationship_bases"), limit=500))
    if requested:
        allowed_bases = set(requested)
    else:
        allowed_bases = set(DEFAULT_STRUCTURAL_RELATIONSHIPS)
        if include_analytical:
            allowed_bases |= ANALYTICAL_RELATIONSHIPS

    if not include_analytical:
        allowed_bases -= ANALYTICAL_RELATIONSHIPS

    indexes: list[int] = []
    for idx, edge in enumerate(edges):
        basis = _edge_basis(edge)
        if basis not in allowed_bases:
            continue
        if not include_analytical and _is_analytical(basis, edge):
            continue
        if _node_id(edge.get("source")) not in by_id or _node_id(edge.get("target")) not in by_id:
            continue
        indexes.append(idx)
    return indexes, allowed_bases, include_analytical


def _adjacency(
    edges: list[dict[str, Any]], allowed_indexes: list[int], direction: str
) -> dict[str, list[tuple[str, int]]]:
    out: dict[str, list[tuple[str, int]]] = defaultdict(list)
    allowed = set(allowed_indexes)
    for idx, edge in enumerate(edges):
        if idx not in allowed:
            continue
        source = _node_id(edge.get("source"))
        target = _node_id(edge.get("target"))
        if not source or not target:
            continue
        if not bool(edge.get("directed", False)):
            out[source].append((target, idx))
            out[target].append((source, idx))
        else:
            if direction in {"both", "forward"}:
                out[source].append((target, idx))
            if direction in {"both", "reverse"}:
                out[target].append((source, idx))
    for node_id in out:
        out[node_id].sort(key=lambda row: (row[0], row[1]))
    return out


def _python_bfs(
    starts: list[str],
    adjacency: dict[str, list[tuple[str, int]]],
    max_depth: int,
    max_nodes: int,
) -> dict[str, int]:
    depth: dict[str, int] = {}
    q: deque[str] = deque()
    for node_id in starts:
        if node_id not in depth:
            depth[node_id] = 0
            q.append(node_id)
    while q and len(depth) < max_nodes:
        current = q.popleft()
        current_depth = depth[current]
        if current_depth >= max_depth:
            continue
        for target, _ in adjacency.get(current, []):
            if target in depth:
                continue
            depth[target] = current_depth + 1
            q.append(target)
            if len(depth) >= max_nodes:
                break
    return dict(sorted(depth.items()))


def _induced_edge_indexes(
    edges: list[dict[str, Any]], allowed_indexes: list[int], node_ids: set[str], max_edges: int
) -> list[int]:
    allowed = set(allowed_indexes)
    out = [
        idx
        for idx, edge in enumerate(edges)
        if idx in allowed
        and _node_id(edge.get("source")) in node_ids
        and _node_id(edge.get("target")) in node_ids
    ]
    return out[:max_edges]


def _python_components(
    node_ids: list[str], edges: list[dict[str, Any]], allowed_indexes: list[int]
) -> list[dict[str, Any]]:
    scope = set(node_ids)
    adjacency: dict[str, set[str]] = defaultdict(set)
    for idx in allowed_indexes:
        edge = edges[idx]
        source = _node_id(edge.get("source"))
        target = _node_id(edge.get("target"))
        if source in scope and target in scope:
            adjacency[source].add(target)
            adjacency[target].add(source)
    seen: set[str] = set()
    components: list[dict[str, Any]] = []
    for root in sorted(scope):
        if root in seen:
            continue
        members: list[str] = []
        q: deque[str] = deque([root])
        seen.add(root)
        while q:
            current = q.popleft()
            members.append(current)
            for nxt in sorted(adjacency.get(current, set())):
                if nxt not in seen:
                    seen.add(nxt)
                    q.append(nxt)
        members.sort()
        components.append(
            {
                "component_index": len(components) + 1,
                "node_ids": members,
                "node_count": len(members),
            }
        )
    return components


def _python_stats(
    node_ids: list[str], edges: list[dict[str, Any]], allowed_indexes: list[int]
) -> dict[str, Any]:
    scope = set(node_ids)
    scoped = [
        edges[idx]
        for idx in allowed_indexes
        if _node_id(edges[idx].get("source")) in scope
        and _node_id(edges[idx].get("target")) in scope
    ]
    degree = {node_id: 0 for node_id in node_ids}
    directed = 0
    undirected = 0
    for edge in scoped:
        if bool(edge.get("directed", False)):
            directed += 1
        else:
            undirected += 1
        degree[_node_id(edge.get("source"))] += 1
        degree[_node_id(edge.get("target"))] += 1
    components = _python_components(node_ids, edges, allowed_indexes)
    total_degree = sum(degree.values())
    return {
        "node_count": len(node_ids),
        "edge_count": len(scoped),
        "directed_edge_count": directed,
        "undirected_edge_count": undirected,
        "isolated_node_count": sum(1 for value in degree.values() if value == 0),
        "component_count": len(components),
        "max_degree": max(degree.values(), default=0),
        "average_degree_milli": int((total_degree * 1000) / len(node_ids)) if node_ids else 0,
    }


def _fallback_query(
    operation: str,
    nodes: list[dict[str, Any]],
    edges: list[dict[str, Any]],
    *,
    start_ids: list[str],
    node_ids: list[str],
    allowed_edge_indexes: list[int],
    max_depth: int,
    max_nodes: int,
    max_edges: int,
    direction: str,
) -> dict[str, Any]:
    all_ids = sorted(_node_id(n.get("id")) for n in nodes if n.get("id"))
    scope_ids = [nid for nid in node_ids if nid in set(all_ids)] if node_ids else all_ids[:max_nodes]
    adjacency = _adjacency(edges, allowed_edge_indexes, direction)
    if operation == "neighborhood":
        depths = _python_bfs(start_ids, adjacency, max_depth, max_nodes)
        selected = set(depths)
        return {
            "nodes": [{"id": nid, "depth": depth} for nid, depth in depths.items()],
            "edges": _induced_edge_indexes(edges, allowed_edge_indexes, selected, max_edges),
            "reachable": [],
            "components": [],
            "stats": {},
        }
    if operation == "reachability":
        depths = _python_bfs(start_ids, adjacency, max_depth, max_nodes)
        return {
            "nodes": [],
            "edges": [],
            "reachable": [{"id": nid, "depth": depth} for nid, depth in depths.items()],
            "components": [],
            "stats": {},
        }
    if operation == "subgraph":
        selected = set(scope_ids[:max_nodes])
        return {
            "nodes": [{"id": nid, "depth": 0} for nid in sorted(selected)],
            "edges": _induced_edge_indexes(edges, allowed_edge_indexes, selected, max_edges),
            "reachable": [],
            "components": [],
            "stats": {},
        }
    if operation == "connected-components":
        return {
            "nodes": [],
            "edges": [],
            "reachable": [],
            "components": _python_components(scope_ids[:max_nodes], edges, allowed_edge_indexes),
            "stats": {},
        }
    return {
        "nodes": [],
        "edges": [],
        "reachable": [],
        "components": [],
        "stats": _python_stats(scope_ids[:max_nodes], edges, allowed_edge_indexes),
    }


def query_native_graph(corpus: dict[str, Any], query: dict[str, Any] | None = None) -> dict[str, Any]:
    q = dict(query or {})
    nodes = [n for n in (corpus.get("nodes") or []) if isinstance(n, dict) and n.get("id")]
    edges = [e for e in (corpus.get("edges") or []) if isinstance(e, dict) and e.get("source") and e.get("target")]
    by_id = {_node_id(n.get("id")): n for n in nodes}

    operation = _normalize_operation(q.get("operation"))
    direction = str(q.get("direction") or "both").strip().lower()
    if direction not in {"both", "forward", "reverse"}:
        direction = "both"
    runtime_requested = str(q.get("runtime") or "auto").strip().lower()
    if runtime_requested not in {"auto", "rust", "python"}:
        runtime_requested = "auto"
    max_depth = max(0, min(8, int(q.get("max_depth") or q.get("depth") or 2)))
    max_nodes = max(1, min(100000, int(q.get("max_nodes") or 1000)))
    max_edges = max(1, min(250000, int(q.get("max_edges") or 5000)))

    start_ids = [nid for nid in _clean_ids(q.get("start_node_ids"), limit=1000) if nid in by_id]
    selected_ids = [nid for nid in _clean_ids(q.get("node_ids"), limit=100000) if nid in by_id]
    if operation in {"neighborhood", "reachability"} and not start_ids:
        start_ids = sorted(by_id)[:1]

    allowed_edge_indexes, allowed_bases, include_analytical = _allowed_edges(nodes, edges, q)
    runtime_status = native_graph_runtime_status()
    native = None
    runtime_used = "python"
    if runtime_requested in {"auto", "rust"}:
        native = native_graph_query_rows(
            nodes,
            edges,
            operation=operation,
            start_ids=start_ids,
            node_ids=selected_ids,
            allowed_edge_indexes=allowed_edge_indexes,
            max_depth=max_depth,
            max_nodes=max_nodes,
            max_edges=max_edges,
            direction=direction,
        )
        if native is not None:
            runtime_used = "rust"
        elif runtime_requested == "rust":
            runtime_used = "python-fallback"

    rows = native or _fallback_query(
        operation,
        nodes,
        edges,
        start_ids=start_ids,
        node_ids=selected_ids,
        allowed_edge_indexes=allowed_edge_indexes,
        max_depth=max_depth,
        max_nodes=max_nodes,
        max_edges=max_edges,
        direction=direction,
    )

    selected_node_ids = [row["id"] for row in rows.get("nodes", []) if row.get("id") in by_id]
    reachable_node_ids = [row["id"] for row in rows.get("reachable", []) if row.get("id") in by_id]
    edge_indexes = [idx for idx in rows.get("edges", []) if isinstance(idx, int) and 0 <= idx < len(edges)]

    node_snapshots = [by_id[nid] for nid in selected_node_ids]
    reachability = [
        {**row, "node": by_id.get(row.get("id"))}
        for row in rows.get("reachable", [])
        if row.get("id") in by_id
    ]
    edge_snapshots = [edges[idx] for idx in edge_indexes]

    stats = dict(rows.get("stats") or {})
    if operation == "structural-stats":
        scope_ids = selected_ids or sorted(by_id)[:max_nodes]
        scope_set = set(scope_ids)
        scoped_edges = [
            edges[idx]
            for idx in allowed_edge_indexes
            if _node_id(edges[idx].get("source")) in scope_set
            and _node_id(edges[idx].get("target")) in scope_set
        ][:max_edges]
        stats["node_kind_counts"] = dict(
            sorted(Counter(str(by_id[nid].get("kind") or "unknown") for nid in scope_ids).items())
        )
        stats["relationship_basis_counts"] = dict(
            sorted(Counter(_edge_basis(edge) for edge in scoped_edges).items())
        )
        if "average_degree_milli" in stats:
            stats["average_degree"] = round(float(stats["average_degree_milli"]) / 1000.0, 3)

    return {
        "schema": NATIVE_QUERY_CONTRACT,
        "runtime_contract": NATIVE_GRAPH_CONTRACT,
        "operation": operation,
        "query": {
            "start_node_ids": start_ids,
            "node_ids": selected_ids,
            "relationship_bases": sorted(allowed_bases),
            "include_analytical": include_analytical,
            "max_depth": max_depth,
            "max_nodes": max_nodes,
            "max_edges": max_edges,
            "direction": direction,
        },
        "runtime": {
            "requested": runtime_requested,
            "used": runtime_used,
            "native_available": bool(runtime_status.get("available")),
            "native_reported_version": runtime_status.get("reported_version"),
            "python_fallback_available": True,
        },
        "nodes": node_snapshots,
        "node_depths": {row["id"]: row.get("depth", 0) for row in rows.get("nodes", [])},
        "edges": edge_snapshots,
        "edge_indexes": edge_indexes,
        "reachability": reachability,
        "components": rows.get("components", []),
        "stats": stats,
        "metrics": {
            "corpus_node_count": len(nodes),
            "corpus_edge_count": len(edges),
            "policy_allowed_edge_count": len(allowed_edge_indexes),
            "result_node_count": len(node_snapshots),
            "result_edge_count": len(edge_snapshots),
            "reachable_node_count": len(reachability),
            "component_count": len(rows.get("components", [])),
        },
        "interpretation": {
            "native_results_are_structural_only": True,
            "connectivity_implies_evidence_support": False,
            "connectivity_implies_causality": False,
            "component_membership_implies_consensus": False,
            "component_membership_implies_common_authorship_or_identity": False,
            "degree_or_connectivity_is_quality_score": False,
            "analytical_relationships_are_opt_in": True,
            "python_selects_relationship_policy_before_native_execution": True,
            "python_owns_research_semantics_and_provenance": True,
            "platform_core_durable_authority": True,
            "native_runtime_changes_research_semantics": False,
        },
    }
