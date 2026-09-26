from __future__ import annotations

from collections import Counter, defaultdict, deque
from dataclasses import dataclass
from typing import Any, Iterable

GRAPH_CONTRACT = "sc-library-research-graph-query/1.0"
PATH_CONTRACT = "sc-library-evidence-pathfinding/1.0"

# Relationship semantics are intentionally explicit. These classifications control
# traversal policy and UI labels; they do not convert relationships into truth,
# causality, consensus, or epistemic confidence.
RELATIONSHIP_CLASSES: dict[str, str] = {
    "explicit-citation": "explicit-source-lineage",
    "metadata-association": "explicit-structural",
    "reviewed-concept-association": "reviewed-structural",
    "reviewed-finding-evidence": "reviewed-evidence",
    "reviewed-claim-evidence": "reviewed-evidence",
    "reviewed-explicit-support": "reviewed-argument",
    "reviewed-explicit-contradiction": "reviewed-argument",
    "explicit-hypothesis-membership": "reviewed-hypothesis-metadata",
    "publication-topic-cooccurrence": "measured-analytical",
    "source-span-cooccurrence": "measured-analytical",
    "embedding-cosine-similarity": "measured-analytical",
}

DEFAULT_TRACE_RELATIONSHIPS = {
    "explicit-citation",
    "metadata-association",
    "reviewed-concept-association",
    "reviewed-finding-evidence",
    "reviewed-claim-evidence",
    "reviewed-explicit-support",
    "reviewed-explicit-contradiction",
    "explicit-hypothesis-membership",
}
ANALYTICAL_RELATIONSHIPS = {
    "publication-topic-cooccurrence",
    "source-span-cooccurrence",
    "embedding-cosine-similarity",
}


def _edge_basis(edge: dict[str, Any]) -> str:
    return str(edge.get("relationship_basis") or edge.get("basis") or edge.get("type") or "relationship")


def _node_id(value: Any) -> str:
    return str(value or "").strip()


def _clean_ids(value: Any, *, limit: int = 100) -> list[str]:
    if isinstance(value, str):
        raw: Iterable[Any] = [value]
    elif isinstance(value, (list, tuple, set)):
        raw = value
    else:
        raw = []
    return list(dict.fromkeys(_node_id(x) for x in raw if _node_id(x)))[:limit]


def _clean_strings(value: Any, *, limit: int = 100) -> list[str]:
    return _clean_ids(value, limit=limit)


def _relationship_class(basis: str) -> str:
    return RELATIONSHIP_CLASSES.get(basis, "other")


def _is_analytical(basis: str, edge: dict[str, Any]) -> bool:
    return basis in ANALYTICAL_RELATIONSHIPS or bool(edge.get("analytical")) and basis not in {
        "reviewed-explicit-support",
        "reviewed-explicit-contradiction",
        "explicit-hypothesis-membership",
    }


def _is_reviewed(basis: str, edge: dict[str, Any]) -> bool:
    return basis.startswith("reviewed-") or basis == "explicit-hypothesis-membership" or bool(edge.get("explicit_reviewed_relation"))


def build_research_graph_manifest(corpus: dict[str, Any]) -> dict[str, Any]:
    nodes = [x for x in (corpus.get("nodes") or []) if isinstance(x, dict) and x.get("id")]
    edges = [x for x in (corpus.get("edges") or []) if isinstance(x, dict) and x.get("source") and x.get("target")]
    node_kinds = Counter(str(x.get("kind") or "node") for x in nodes)
    relationship_bases = Counter(_edge_basis(x) for x in edges)
    relationship_classes = Counter(_relationship_class(_edge_basis(x)) for x in edges)
    return {
        "schema": GRAPH_CONTRACT,
        "node_count": len(nodes),
        "edge_count": len(edges),
        "node_kinds": dict(sorted(node_kinds.items())),
        "relationship_bases": dict(sorted(relationship_bases.items())),
        "relationship_classes": dict(sorted(relationship_classes.items())),
        "query_capabilities": {
            "text_node_search": True,
            "node_kind_filter": True,
            "record_filter": True,
            "relationship_basis_filter": True,
            "neighborhood_expansion": True,
            "deterministic_pathfinding": True,
            "direction_aware_traversal": True,
            "analytical_relationships_opt_in": True,
        },
        "interpretation": {
            "graph_path_implies_truth": False,
            "graph_path_implies_causality": False,
            "graph_path_implies_consensus": False,
            "support_requires_explicit_reviewed_relation": True,
            "contradiction_requires_explicit_reviewed_relation": True,
            "analytical_edges_are_evidence_relations": False,
            "platform_core_durable_authority": True,
        },
    }


def query_research_graph(corpus: dict[str, Any], query: dict[str, Any] | None = None) -> dict[str, Any]:
    q = query or {}
    nodes = [x for x in (corpus.get("nodes") or []) if isinstance(x, dict) and x.get("id")]
    edges = [x for x in (corpus.get("edges") or []) if isinstance(x, dict) and x.get("source") and x.get("target")]
    by_id = {_node_id(x.get("id")): x for x in nodes}

    text = str(q.get("text") or q.get("q") or "").strip().casefold()
    kinds = set(_clean_strings(q.get("kinds")))
    record_ids = set(_clean_strings(q.get("record_ids")))
    requested_bases = set(_clean_strings(q.get("relationship_bases")))
    include_analytical = bool(q.get("include_analytical", False))
    neighborhood_depth = max(0, min(3, int(q.get("neighborhood_depth") or 0)))
    limit = max(1, min(250, int(q.get("limit") or 50)))

    matches: list[dict[str, Any]] = []
    for node in nodes:
        nid = _node_id(node.get("id"))
        if kinds and str(node.get("kind") or "") not in kinds:
            continue
        record_id = _node_id(node.get("record_id"))
        if record_ids and nid not in record_ids and record_id not in record_ids:
            continue
        if text:
            hay = " ".join(
                str(node.get(k) or "") for k in ("id", "label", "candidate_text", "hypothesis_key", "source_type")
            ).casefold()
            if text not in hay:
                continue
        matches.append(node)
    matches = matches[:limit]

    visible_ids = {_node_id(x.get("id")) for x in matches}
    selected_edges: list[dict[str, Any]] = []
    frontier = set(visible_ids)
    seen = set(visible_ids)
    for _ in range(neighborhood_depth):
        nxt: set[str] = set()
        for edge in edges:
            basis = _edge_basis(edge)
            if requested_bases and basis not in requested_bases:
                continue
            if not include_analytical and _is_analytical(basis, edge):
                continue
            source, target = _node_id(edge.get("source")), _node_id(edge.get("target"))
            if source in frontier or target in frontier:
                selected_edges.append(edge)
                other_ids = (source, target)
                for oid in other_ids:
                    if oid and oid not in seen:
                        nxt.add(oid)
                        seen.add(oid)
        frontier = nxt
        if not frontier:
            break
    if neighborhood_depth == 0:
        for edge in edges:
            basis = _edge_basis(edge)
            if requested_bases and basis not in requested_bases:
                continue
            if not include_analytical and _is_analytical(basis, edge):
                continue
            if _node_id(edge.get("source")) in visible_ids and _node_id(edge.get("target")) in visible_ids:
                selected_edges.append(edge)

    neighborhood_nodes = [by_id[nid] for nid in seen if nid in by_id]
    neighborhood_nodes.sort(key=lambda x: (_node_id(x.get("kind")), _node_id(x.get("label")), _node_id(x.get("id"))))

    return {
        "schema": GRAPH_CONTRACT,
        "query": {
            "text": str(q.get("text") or q.get("q") or ""),
            "kinds": sorted(kinds),
            "record_ids": sorted(record_ids),
            "relationship_bases": sorted(requested_bases),
            "include_analytical": include_analytical,
            "neighborhood_depth": neighborhood_depth,
            "limit": limit,
        },
        "matches": matches,
        "neighborhood_nodes": neighborhood_nodes,
        "edges": selected_edges[:1000],
        "metrics": {
            "match_count": len(matches),
            "neighborhood_node_count": len(neighborhood_nodes),
            "edge_count": min(1000, len(selected_edges)),
        },
        "manifest": build_research_graph_manifest(corpus),
        "interpretation": {
            "query_is_deterministic": True,
            "text_match_is_semantic_inference": False,
            "analytical_edges_opt_in": True,
            "results_create_new_claims": False,
        },
    }


@dataclass(frozen=True)
class Step:
    from_id: str
    to_id: str
    edge_index: int
    basis: str
    traversed_direction: str


def _edge_snapshot(edge: dict[str, Any], step: Step) -> dict[str, Any]:
    basis = step.basis
    return {
        "from": step.from_id,
        "to": step.to_id,
        "source": _node_id(edge.get("source")),
        "target": _node_id(edge.get("target")),
        "relationship_basis": basis,
        "relationship_class": _relationship_class(basis),
        "directed": bool(edge.get("directed", False)),
        "traversed_direction": step.traversed_direction,
        "weight": edge.get("weight"),
        "evidence_count": edge.get("evidence_count"),
        "provenance": edge.get("provenance") or {},
        "explicit_reviewed_relation": bool(edge.get("explicit_reviewed_relation")),
        "analytical": _is_analytical(basis, edge),
        "truth_assertion": bool(edge.get("truth_assertion", False)),
        "causal_assertion": bool(edge.get("causal_assertion", False)),
    }


def find_research_paths(corpus: dict[str, Any], query: dict[str, Any] | None = None) -> dict[str, Any]:
    q = query or {}
    nodes = [x for x in (corpus.get("nodes") or []) if isinstance(x, dict) and x.get("id")]
    edges = [x for x in (corpus.get("edges") or []) if isinstance(x, dict) and x.get("source") and x.get("target")]
    by_id = {_node_id(x.get("id")): x for x in nodes}

    start_ids = [x for x in _clean_ids(q.get("start_node_ids") or q.get("source_node_ids"), limit=25) if x in by_id]
    target_ids = set(x for x in _clean_ids(q.get("target_node_ids"), limit=100) if x in by_id)
    target_kinds = set(_clean_strings(q.get("target_kinds")))
    if not target_ids and not target_kinds:
        # If two or more nodes are selected, trace between the first and the rest.
        if len(start_ids) >= 2:
            target_ids = set(start_ids[1:])
            start_ids = start_ids[:1]
        else:
            target_kinds = {"publication"}

    include_analytical = bool(q.get("include_analytical", False))
    max_hops = max(1, min(8, int(q.get("max_hops") or 4)))
    max_paths = max(1, min(50, int(q.get("max_paths") or 12)))
    direction = str(q.get("direction") or "both").strip().lower()
    if direction not in {"both", "forward", "reverse"}:
        direction = "both"

    requested_bases = set(_clean_strings(q.get("relationship_bases")))
    if not requested_bases:
        allowed_bases = set(DEFAULT_TRACE_RELATIONSHIPS)
        if include_analytical:
            allowed_bases |= ANALYTICAL_RELATIONSHIPS
    else:
        allowed_bases = set(requested_bases)
        if not include_analytical:
            allowed_bases -= ANALYTICAL_RELATIONSHIPS

    adjacency: dict[str, list[Step]] = defaultdict(list)
    for idx, edge in enumerate(edges):
        basis = _edge_basis(edge)
        if basis not in allowed_bases:
            continue
        if not include_analytical and _is_analytical(basis, edge):
            continue
        source, target = _node_id(edge.get("source")), _node_id(edge.get("target"))
        if source not in by_id or target not in by_id:
            continue
        directed = bool(edge.get("directed", False))
        # Undirected research relations are traversable both ways. Directed citation
        # lineage respects caller direction while retaining original edge direction.
        if not directed:
            adjacency[source].append(Step(source, target, idx, basis, "undirected"))
            adjacency[target].append(Step(target, source, idx, basis, "undirected"))
        else:
            if direction in {"both", "forward"}:
                adjacency[source].append(Step(source, target, idx, basis, "forward"))
            if direction in {"both", "reverse"}:
                adjacency[target].append(Step(target, source, idx, basis, "reverse"))

    for nid in adjacency:
        adjacency[nid].sort(key=lambda s: (s.basis, s.to_id, s.edge_index))

    def is_target(nid: str, source: str) -> bool:
        if nid == source:
            return False
        if target_ids and nid in target_ids:
            return True
        if target_kinds and str((by_id.get(nid) or {}).get("kind") or "") in target_kinds:
            return True
        return False

    path_results: list[dict[str, Any]] = []
    seen_signatures: set[tuple[str, ...]] = set()
    for source in start_ids:
        queue: deque[tuple[str, list[str], list[Step]]] = deque([(source, [source], [])])
        # Track shallowest visit per (node, path basis signature prefix) loosely enough
        # to preserve alternate auditable routes without unbounded graph explosion.
        best_depth: dict[str, int] = {source: 0}
        while queue and len(path_results) < max_paths:
            current, node_path, step_path = queue.popleft()
            depth = len(step_path)
            if depth >= max_hops:
                continue
            for step in adjacency.get(current, []):
                nxt = step.to_id
                if nxt in node_path:
                    continue
                new_nodes = node_path + [nxt]
                new_steps = step_path + [step]
                if is_target(nxt, source):
                    signature = tuple(new_nodes + ["|" + x.basis for x in new_steps])
                    if signature not in seen_signatures:
                        seen_signatures.add(signature)
                        edge_snaps = [_edge_snapshot(edges[s.edge_index], s) for s in new_steps]
                        analytical_count = sum(1 for x in edge_snaps if x["analytical"])
                        reviewed_count = sum(1 for s in new_steps if _is_reviewed(s.basis, edges[s.edge_index]))
                        path_results.append({
                            "path_id": f"path:{len(path_results)+1}",
                            "source_node_id": source,
                            "target_node_id": nxt,
                            "hop_count": len(new_steps),
                            "node_ids": new_nodes,
                            "nodes": [by_id[nid] for nid in new_nodes],
                            "edges": edge_snaps,
                            "relationship_bases": [x["relationship_basis"] for x in edge_snaps],
                            "analytical_edge_count": analytical_count,
                            "reviewed_edge_count": reviewed_count,
                            "all_edges_source_grounded": analytical_count == 0,
                            "path_semantics": "descriptive-connectivity",
                        })
                        if len(path_results) >= max_paths:
                            break
                nd = len(new_steps)
                prior = best_depth.get(nxt)
                if prior is None or nd <= prior + 1:
                    best_depth[nxt] = min(nd, prior if prior is not None else nd)
                    queue.append((nxt, new_nodes, new_steps))
            if len(path_results) >= max_paths:
                break
        if len(path_results) >= max_paths:
            break

    path_results.sort(key=lambda p: (p["hop_count"], p["target_node_id"], p["path_id"]))
    basis_counts = Counter(b for p in path_results for b in p["relationship_bases"])
    return {
        "schema": PATH_CONTRACT,
        "query": {
            "start_node_ids": start_ids,
            "target_node_ids": sorted(target_ids),
            "target_kinds": sorted(target_kinds),
            "relationship_bases": sorted(allowed_bases),
            "include_analytical": include_analytical,
            "max_hops": max_hops,
            "max_paths": max_paths,
            "direction": direction,
        },
        "paths": path_results,
        "metrics": {
            "path_count": len(path_results),
            "shortest_hops": min((p["hop_count"] for p in path_results), default=None),
            "relationship_basis_counts": dict(sorted(basis_counts.items())),
            "paths_with_analytical_edges": sum(1 for p in path_results if p["analytical_edge_count"] > 0),
            "fully_source_grounded_path_count": sum(1 for p in path_results if p["all_edges_source_grounded"]),
        },
        "manifest": build_research_graph_manifest(corpus),
        "platform_core": {
            "durable_research_object_authority": "platform-core",
            "library_role": "source-grounded graph query, evidence pathfinding, and visualization",
            "automatic_core_write": False,
        },
        "interpretation": {
            "path_is_deterministic_graph_traversal": True,
            "path_creates_new_claims": False,
            "path_implies_truth": False,
            "path_implies_causality": False,
            "path_implies_consensus": False,
            "support_requires_explicit_reviewed_relation": True,
            "contradiction_requires_explicit_reviewed_relation": True,
            "analytical_relationships_opt_in": True,
            "absence_of_path_means_no_relationship_exists": False,
        },
    }
