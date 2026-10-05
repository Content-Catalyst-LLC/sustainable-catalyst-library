from __future__ import annotations

from collections import deque
from hashlib import sha256
import json
from typing import Any

LIBRARY_VERSION = "6.27.0"
BACKEND_VERSION = "3.27.0"
WEB_VERSION = "2.27.0"
SDK_VERSION = "1.27.0"

CONTRACT = "sc-library-unified-research-knowledge-graph/1.0"
READINESS_CONTRACT = "sc-library-unified-research-knowledge-graph-readiness/1.0"
BOOTSTRAP_CONTRACT = "sc-library-unified-research-knowledge-graph-bootstrap/1.0"
GRAPH_CONTRACT = "sc-library-unified-research-knowledge-graph-snapshot/1.0"
VALIDATION_CONTRACT = "sc-library-unified-research-knowledge-graph-validation/1.0"
NEIGHBORHOOD_CONTRACT = "sc-library-unified-research-knowledge-graph-neighborhood/1.0"
PATH_CONTRACT = "sc-library-unified-research-knowledge-graph-path/1.0"
CHAIN_AUDIT_CONTRACT = "sc-library-unified-research-knowledge-graph-chain-audit/1.0"
EXPORT_CONTRACT = "sc-library-unified-research-knowledge-graph-export/1.0"

MAX_NODES = 10000
MAX_EDGES = 50000
MAX_PATH_DEPTH = 24
MAX_NEIGHBORHOOD_DEPTH = 4

NODE_TYPES = [
    "research-question",
    "investigation",
    "source",
    "claim",
    "evidence",
    "dataset",
    "statistical-result",
    "place",
    "event",
    "annotation",
    "citation",
    "synthesis",
    "research-package",
    "publication",
]

NODE_TYPE_ALIASES = {
    "question": "research-question",
    "research_question": "research-question",
    "research question": "research-question",
    "statistical_result": "statistical-result",
    "statistical result": "statistical-result",
    "package": "research-package",
    "research_package": "research-package",
    "research package": "research-package",
}

RELATION_TYPES = [
    "frames",
    "investigates",
    "uses-source",
    "asserts",
    "supports",
    "contradicts",
    "derived-from",
    "analyzes",
    "located-at",
    "occurs-at",
    "annotates",
    "cites",
    "synthesizes",
    "packages",
    "publishes",
    "references",
    "related-to",
]

CANONICAL_CHAIN = [
    "research-question",
    "investigation",
    "source",
    "claim",
    "evidence",
    "dataset",
    "statistical-result",
    "place",
    "event",
    "annotation",
    "citation",
    "synthesis",
    "research-package",
    "publication",
]

DEFAULT_AUTHORITIES = {
    "research-question": "research-investigation-workspace",
    "investigation": "research-investigation-workspace",
    "source": "library-catalog-and-source-ingestion",
    "claim": "evidence-matrix-and-claim-support-analysis",
    "evidence": "structured-evidence-and-evidence-matrix-services",
    "dataset": "dataset-discovery-and-statistical-evidence-workspace",
    "statistical-result": "dataset-discovery-and-statistical-evidence-workspace",
    "place": "geospatial-and-place-based-research-workspace",
    "event": "research-timeline-and-historical-event-workspace",
    "annotation": "research-annotation-and-scholarly-notes-workspace",
    "citation": "citation-workspace-and-python-citation-service",
    "synthesis": "research-synthesis-workspace",
    "research-package": "research-package-composer-v6.25.0",
    "publication": "research-publication-studio-v6.26.0",
}


def _canon(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()


def _dict(value: Any) -> dict[str, Any]:
    return dict(value) if isinstance(value, dict) else {}


def _list(value: Any) -> list[Any]:
    return list(value) if isinstance(value, (list, tuple)) else []


def _clean(value: Any, limit: int = 16000) -> str:
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


def _node_type(value: Any) -> str:
    raw = _clean(value, 200).lower().replace("_", "-")
    raw = NODE_TYPE_ALIASES.get(raw, raw)
    if raw not in NODE_TYPES:
        raise ValueError(f"unknown-node-type:{raw or 'empty'}")
    return raw


def _relation_type(value: Any) -> str:
    raw = _clean(value, 200).lower().replace("_", "-")
    if raw not in RELATION_TYPES:
        raise ValueError(f"unknown-relation-type:{raw or 'empty'}")
    return raw


def guardrails() -> dict[str, bool]:
    return {
        "knowledge_graph_is_new_source_authority": False,
        "knowledge_graph_is_new_evidence_authority": False,
        "knowledge_graph_is_new_claim_authority": False,
        "knowledge_graph_is_new_dataset_authority": False,
        "knowledge_graph_is_new_citation_authority": False,
        "knowledge_graph_is_new_package_authority": False,
        "knowledge_graph_is_new_publication_authority": False,
        "originating_object_authorities_remain_authoritative": True,
        "existing_research_graph_navigation_remains_record_graph_authority": True,
        "node_payloads_rewritten_automatically": False,
        "node_payloads_merged_automatically": False,
        "edges_imply_truth": False,
        "path_exists_implies_causality": False,
        "graph_connectivity_implies_evidence_strength": False,
        "graph_centrality_implies_importance": False,
        "relation_type_implies_semantic_equivalence": False,
        "automatic_entity_merge": False,
        "automatic_claim_promotion": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "automatic_external_fetch": False,
        "server_side_graph_persistence": False,
        "database_migration_required": False,
        "wordpress_required": False,
    }


def contract() -> dict[str, Any]:
    resources = [
        "typed-research-object-nodes",
        "explicit-research-object-relations",
        "origin-authority-and-version-lineage",
        "deterministic-graph-snapshot",
        "graph-validation",
        "canonical-research-chain-audit",
        "neighborhood-traversal",
        "path-traversal",
        "portable-json-export",
    ]
    basis = {
        "node_types": NODE_TYPES,
        "relation_types": RELATION_TYPES,
        "canonical_chain": CANONICAL_CHAIN,
        "resources": resources,
        "guardrails": guardrails(),
    }
    return {
        "schema": CONTRACT,
        "graph_service_id": "unified-research-knowledge-graph:" + _fp(basis)[:32],
        "graph_service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "web_version": WEB_VERSION,
        "sdk_version": SDK_VERSION,
        "state": "authoritative-composition",
        "authority": "python-backend-composition",
        "route": "/research/graph",
        "api_base": "/api/library/v1/research-knowledge-graph",
        "resources": resources,
        "node_types": NODE_TYPES,
        "relation_types": RELATION_TYPES,
        "canonical_chain": CANONICAL_CHAIN,
        "originating_authorities": DEFAULT_AUTHORITIES,
        "existing_graph_navigation_authority": "/api/library/v1/research-graph",
        "limits": {
            "nodes": MAX_NODES,
            "edges": MAX_EDGES,
            "path_depth": MAX_PATH_DEPTH,
            "neighborhood_depth": MAX_NEIGHBORHOOD_DEPTH,
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
        "existing_research_object_authorities_preserved": True,
        "server_side_graph_persistence": False,
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
        "route": "/research/graph",
        "readiness": readiness(),
        "node_types": NODE_TYPES,
        "relation_types": RELATION_TYPES,
        "canonical_chain": CANONICAL_CHAIN,
        "originating_authorities": DEFAULT_AUTHORITIES,
        "operations": ["build", "validate", "chain-audit", "neighborhood", "path", "export"],
        "browser_storage_key": "sc-library-unified-research-knowledge-graph-v1",
        "guardrails": guardrails(),
    }


def _normalize_node(raw: Any, index: int) -> dict[str, Any]:
    item = _dict(raw)
    node_type = _node_type(item.get("node_type") or item.get("type") or item.get("kind"))
    node_id = _clean(item.get("node_id") or item.get("id"), 2000)
    if not node_id:
        node_id = f"{node_type}:{index}"
    authority = _clean(item.get("authority"), 2000) or DEFAULT_AUTHORITIES[node_type]
    version = _clean(item.get("version") or item.get("object_version"), 1000) or None
    object_ref = _clean(item.get("object_ref") or item.get("ref") or item.get("source_ref"), 4000) or None
    title = _clean(item.get("title") or item.get("label") or item.get("name"), 8000) or node_id
    tags = _unique_strings(item.get("tags"), 250)
    metadata = _dict(item.get("metadata"))
    provenance = _dict(item.get("provenance"))
    return {
        "node_id": node_id,
        "node_type": node_type,
        "title": title,
        "authority": authority,
        "version": version,
        "object_ref": object_ref,
        "tags": tags,
        "metadata": metadata,
        "provenance": provenance,
    }


def _normalize_edge(raw: Any, index: int) -> dict[str, Any]:
    item = _dict(raw)
    source = _clean(item.get("source") or item.get("source_id") or item.get("from"), 2000)
    target = _clean(item.get("target") or item.get("target_id") or item.get("to"), 2000)
    if not source or not target:
        raise ValueError(f"edge-missing-endpoint:{index}")
    relation = _relation_type(item.get("relation") or item.get("relation_type") or item.get("type"))
    edge_id = _clean(item.get("edge_id") or item.get("id"), 2000) or f"edge:{index}:{_fp([source, relation, target])[:16]}"
    return {
        "edge_id": edge_id,
        "source": source,
        "target": target,
        "relation": relation,
        "directed": bool(item.get("directed", True)),
        "authority": _clean(item.get("authority"), 2000) or "explicit-user-or-originating-service-relation",
        "version": _clean(item.get("version"), 1000) or None,
        "metadata": _dict(item.get("metadata")),
        "provenance": _dict(item.get("provenance")),
    }


def normalize_graph_input(payload: dict[str, Any]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    if not isinstance(payload, dict):
        raise ValueError("payload-must-be-object")
    raw_nodes = _list(payload.get("nodes") or payload.get("objects"))
    raw_edges = _list(payload.get("edges") or payload.get("relations"))
    if len(raw_nodes) > MAX_NODES:
        raise ValueError(f"node-limit-exceeded:{MAX_NODES}")
    if len(raw_edges) > MAX_EDGES:
        raise ValueError(f"edge-limit-exceeded:{MAX_EDGES}")
    nodes = [_normalize_node(x, i) for i, x in enumerate(raw_nodes, 1)]
    edges = [_normalize_edge(x, i) for i, x in enumerate(raw_edges, 1)]
    return nodes, edges


def _validation(nodes: list[dict[str, Any]], edges: list[dict[str, Any]]) -> dict[str, Any]:
    node_ids = [x["node_id"] for x in nodes]
    node_set = set(node_ids)
    duplicate_nodes = sorted({x for x in node_ids if node_ids.count(x) > 1})
    edge_ids = [x["edge_id"] for x in edges]
    duplicate_edges = sorted({x for x in edge_ids if edge_ids.count(x) > 1})
    dangling = [x["edge_id"] for x in edges if x["source"] not in node_set or x["target"] not in node_set]
    incident: dict[str, int] = {x: 0 for x in node_ids}
    for edge in edges:
        if edge["source"] in incident:
            incident[edge["source"]] += 1
        if edge["target"] in incident:
            incident[edge["target"]] += 1
    isolated = sorted([node_id for node_id, count in incident.items() if count == 0])
    issues: list[dict[str, Any]] = []
    if duplicate_nodes:
        issues.append({"code": "duplicate-node-ids", "severity": "blocking", "items": duplicate_nodes})
    if duplicate_edges:
        issues.append({"code": "duplicate-edge-ids", "severity": "blocking", "items": duplicate_edges})
    if dangling:
        issues.append({"code": "dangling-edges", "severity": "blocking", "items": dangling})
    if isolated:
        issues.append({"code": "isolated-nodes", "severity": "advisory", "items": isolated})
    blocking = [x for x in issues if x["severity"] == "blocking"]
    return {
        "schema": VALIDATION_CONTRACT,
        "valid": not blocking,
        "blocking_count": len(blocking),
        "issue_count": len(issues),
        "issues": issues,
        "duplicate_node_ids": duplicate_nodes,
        "duplicate_edge_ids": duplicate_edges,
        "dangling_edge_ids": dangling,
        "isolated_node_ids": isolated,
    }


def build_graph(payload: dict[str, Any]) -> dict[str, Any]:
    nodes, edges = normalize_graph_input(payload)
    validation = _validation(nodes, edges)
    if not validation["valid"]:
        raise ValueError("graph-validation-failed")
    type_counts = {node_type: 0 for node_type in NODE_TYPES}
    for node in nodes:
        type_counts[node["node_type"]] += 1
    relation_counts = {relation: 0 for relation in RELATION_TYPES}
    for edge in edges:
        relation_counts[edge["relation"]] += 1
    snapshot_basis = {
        "nodes": nodes,
        "edges": edges,
        "graph_title": _clean(payload.get("graph_title") or payload.get("title"), 8000) or "Unified research knowledge graph",
        "project_id": _clean(payload.get("project_id"), 2000) or None,
    }
    fingerprint = _fp(snapshot_basis)
    return {
        "schema": GRAPH_CONTRACT,
        "graph_id": "research-knowledge-graph:" + fingerprint[:32],
        "graph_fingerprint_sha256": fingerprint,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "graph_title": snapshot_basis["graph_title"],
        "project_id": snapshot_basis["project_id"],
        "state": "deterministic-composition",
        "node_count": len(nodes),
        "edge_count": len(edges),
        "node_type_counts": type_counts,
        "relation_type_counts": relation_counts,
        "nodes": nodes,
        "edges": edges,
        "validation": validation,
        "authority_model": {
            "graph_composition": "python-backend-composition",
            "node_content": "originating-object-authority",
            "edge_semantics": "explicit-relation-authority",
        },
        "guardrails": guardrails(),
    }


def validate_graph(payload: dict[str, Any]) -> dict[str, Any]:
    nodes, edges = normalize_graph_input(payload)
    result = _validation(nodes, edges)
    result.update({
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "node_count": len(nodes),
        "edge_count": len(edges),
        "guardrails": guardrails(),
    })
    return result


def _snapshot(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _dict(payload.get("graph"))
    if graph.get("schema") == GRAPH_CONTRACT and isinstance(graph.get("nodes"), list) and isinstance(graph.get("edges"), list):
        return graph
    return build_graph(payload)


def _adjacency(graph: dict[str, Any], directed: bool = False) -> dict[str, list[tuple[str, dict[str, Any]]]]:
    adjacency: dict[str, list[tuple[str, dict[str, Any]]]] = {x["node_id"]: [] for x in graph.get("nodes", []) if isinstance(x, dict)}
    for edge in graph.get("edges", []):
        if not isinstance(edge, dict):
            continue
        source = edge.get("source")
        target = edge.get("target")
        if source in adjacency and target in adjacency:
            adjacency[source].append((target, edge))
            if not directed or not edge.get("directed", True):
                adjacency[target].append((source, edge))
    return adjacency


def neighborhood(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _snapshot(payload)
    node_id = _clean(payload.get("node_id"), 2000)
    if not node_id:
        raise ValueError("node-id-required")
    depth = int(payload.get("depth", 1) or 1)
    depth = max(0, min(depth, MAX_NEIGHBORHOOD_DEPTH))
    node_map = {x["node_id"]: x for x in graph["nodes"]}
    if node_id not in node_map:
        raise ValueError("node-not-found")
    adjacency = _adjacency(graph, directed=False)
    seen = {node_id}
    q: deque[tuple[str, int]] = deque([(node_id, 0)])
    while q:
        current, d = q.popleft()
        if d >= depth:
            continue
        for neighbor, _edge in adjacency.get(current, []):
            if neighbor not in seen:
                seen.add(neighbor)
                q.append((neighbor, d + 1))
    edges = [x for x in graph["edges"] if x["source"] in seen and x["target"] in seen]
    nodes = [node_map[x] for x in sorted(seen)]
    return {
        "schema": NEIGHBORHOOD_CONTRACT,
        "graph_id": graph["graph_id"],
        "node_id": node_id,
        "depth": depth,
        "node_count": len(nodes),
        "edge_count": len(edges),
        "nodes": nodes,
        "edges": edges,
        "guardrails": guardrails(),
    }


def path(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _snapshot(payload)
    source = _clean(payload.get("source") or payload.get("source_id"), 2000)
    target = _clean(payload.get("target") or payload.get("target_id"), 2000)
    if not source or not target:
        raise ValueError("source-and-target-required")
    node_map = {x["node_id"]: x for x in graph["nodes"]}
    if source not in node_map or target not in node_map:
        raise ValueError("path-endpoint-not-found")
    directed = bool(payload.get("directed", False))
    max_depth = int(payload.get("max_depth", MAX_PATH_DEPTH) or MAX_PATH_DEPTH)
    max_depth = max(1, min(max_depth, MAX_PATH_DEPTH))
    adjacency = _adjacency(graph, directed=directed)
    q: deque[str] = deque([source])
    previous: dict[str, tuple[str, dict[str, Any]] | None] = {source: None}
    depth: dict[str, int] = {source: 0}
    found = False
    while q:
        current = q.popleft()
        if current == target:
            found = True
            break
        if depth[current] >= max_depth:
            continue
        for nxt, edge in adjacency.get(current, []):
            if nxt not in previous:
                previous[nxt] = (current, edge)
                depth[nxt] = depth[current] + 1
                q.append(nxt)
    node_ids: list[str] = []
    path_edges: list[dict[str, Any]] = []
    if target in previous:
        found = True
        cursor = target
        while True:
            node_ids.append(cursor)
            prev = previous[cursor]
            if prev is None:
                break
            cursor, edge = prev
            path_edges.append(edge)
        node_ids.reverse()
        path_edges.reverse()
    return {
        "schema": PATH_CONTRACT,
        "graph_id": graph["graph_id"],
        "source": source,
        "target": target,
        "directed": directed,
        "found": found,
        "hop_count": max(0, len(node_ids) - 1) if found else None,
        "node_ids": node_ids,
        "nodes": [node_map[x] for x in node_ids],
        "edges": path_edges,
        "guardrails": guardrails(),
    }


def chain_audit(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _snapshot(payload)
    present_types = {x["node_type"] for x in graph["nodes"]}
    missing_types = [x for x in CANONICAL_CHAIN if x not in present_types]
    edge_pairs = {(x["source"], x["target"]) for x in graph["edges"]}
    node_by_type: dict[str, list[str]] = {x: [] for x in NODE_TYPES}
    for node in graph["nodes"]:
        node_by_type[node["node_type"]].append(node["node_id"])
    transitions: list[dict[str, Any]] = []
    connected_transitions = 0
    for a, b in zip(CANONICAL_CHAIN, CANONICAL_CHAIN[1:]):
        source_ids = node_by_type[a]
        target_ids = node_by_type[b]
        connected = any((s, t) in edge_pairs or (t, s) in edge_pairs for s in source_ids for t in target_ids)
        if connected:
            connected_transitions += 1
        transitions.append({"from_type": a, "to_type": b, "connected": connected})
    total = max(1, len(CANONICAL_CHAIN) - 1)
    return {
        "schema": CHAIN_AUDIT_CONTRACT,
        "graph_id": graph["graph_id"],
        "canonical_chain": CANONICAL_CHAIN,
        "present_node_types": [x for x in CANONICAL_CHAIN if x in present_types],
        "missing_node_types": missing_types,
        "transition_count": len(transitions),
        "connected_transition_count": connected_transitions,
        "transition_coverage": connected_transitions / total,
        "transitions": transitions,
        "complete_chain_present": not missing_types and connected_transitions == len(transitions),
        "interpretation": "Structural coverage only; completeness does not imply truth, causality, evidence quality, or publication readiness.",
        "guardrails": guardrails(),
    }


def export_graph(payload: dict[str, Any]) -> dict[str, Any]:
    graph = _snapshot(payload)
    content = json.dumps(graph, ensure_ascii=False, sort_keys=True, indent=2)
    return {
        "schema": EXPORT_CONTRACT,
        "graph_id": graph["graph_id"],
        "filename": f"sustainable-catalyst-research-knowledge-graph-{graph['graph_id'].split(':',1)[-1][:12]}.json",
        "media_type": "application/json",
        "sha256": sha256(content.encode("utf-8")).hexdigest(),
        "content": content,
        "server_side_persisted": False,
        "guardrails": guardrails(),
    }
