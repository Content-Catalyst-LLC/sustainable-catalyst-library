from app.research_graph_pathfinding import (
    GRAPH_CONTRACT,
    PATH_CONTRACT,
    build_research_graph_manifest,
    find_research_paths,
    query_research_graph,
)


def corpus():
    return {
        "nodes": [
            {"id": "pub:a", "kind": "publication", "label": "Publication A"},
            {"id": "pub:b", "kind": "publication", "label": "Publication B"},
            {"id": "finding:1", "kind": "finding", "label": "Observed energy transition finding", "record_id": "pub:a"},
            {"id": "claim:2", "kind": "claim", "label": "Grid flexibility claim", "record_id": "pub:b"},
            {"id": "topic:grid", "kind": "topic", "label": "Grid flexibility"},
        ],
        "edges": [
            {"source": "finding:1", "target": "pub:a", "relationship_basis": "reviewed-finding-evidence", "directed": False, "analytical": False},
            {"source": "claim:2", "target": "pub:b", "relationship_basis": "reviewed-claim-evidence", "directed": False, "analytical": False},
            {"source": "finding:1", "target": "claim:2", "relationship_basis": "reviewed-explicit-support", "directed": False, "explicit_reviewed_relation": True, "analytical": True},
            {"source": "pub:a", "target": "pub:b", "relationship_basis": "explicit-citation", "directed": True, "analytical": False},
            {"source": "pub:b", "target": "topic:grid", "relationship_basis": "metadata-association", "directed": False, "analytical": False},
            {"source": "pub:a", "target": "topic:grid", "relationship_basis": "embedding-cosine-similarity", "directed": False, "analytical": True},
        ],
    }


def test_manifest_exposes_query_and_path_capabilities():
    result = build_research_graph_manifest(corpus())
    assert result["schema"] == GRAPH_CONTRACT
    assert result["query_capabilities"]["deterministic_pathfinding"] is True
    assert result["query_capabilities"]["analytical_relationships_opt_in"] is True
    assert result["interpretation"]["graph_path_implies_truth"] is False
    assert result["node_kinds"]["publication"] == 2


def test_graph_query_is_deterministic_text_filter():
    result = query_research_graph(corpus(), {"text": "grid flexibility", "neighborhood_depth": 1})
    assert result["schema"] == GRAPH_CONTRACT
    ids = {row["id"] for row in result["matches"]}
    assert ids == {"claim:2", "topic:grid"}
    assert result["interpretation"]["text_match_is_semantic_inference"] is False
    # Analytical embedding edge is excluded unless explicitly opted in.
    assert all(edge["relationship_basis"] != "embedding-cosine-similarity" for edge in result["edges"])


def test_pathfinding_defaults_to_source_grounded_relationships():
    result = find_research_paths(corpus(), {
        "start_node_ids": ["finding:1"],
        "target_node_ids": ["pub:b"],
        "max_hops": 4,
    })
    assert result["schema"] == PATH_CONTRACT
    assert result["paths"]
    path = result["paths"][0]
    assert path["node_ids"][0] == "finding:1"
    assert path["node_ids"][-1] == "pub:b"
    assert path["analytical_edge_count"] == 0
    assert "embedding-cosine-similarity" not in path["relationship_bases"]
    assert result["interpretation"]["path_implies_truth"] is False
    assert result["platform_core"]["durable_research_object_authority"] == "platform-core"


def test_reviewed_support_relation_remains_available_even_if_legacy_analytical_flag_is_true():
    result = find_research_paths(corpus(), {
        "start_node_ids": ["finding:1"],
        "target_node_ids": ["claim:2"],
        "max_hops": 2,
    })
    assert result["paths"]
    direct = next(p for p in result["paths"] if p["hop_count"] == 1)
    assert direct["relationship_bases"] == ["reviewed-explicit-support"]
    assert direct["analytical_edge_count"] == 0
    assert direct["reviewed_edge_count"] == 1


def test_analytical_relationships_are_opt_in():
    analytic_only = {
        "nodes": [
            {"id": "pub:a", "kind": "publication", "label": "A"},
            {"id": "topic:x", "kind": "topic", "label": "X"},
        ],
        "edges": [
            {"source": "pub:a", "target": "topic:x", "relationship_basis": "embedding-cosine-similarity", "directed": False, "analytical": True},
        ],
    }
    off = find_research_paths(analytic_only, {"start_node_ids": ["topic:x"], "target_node_ids": ["pub:a"]})
    assert off["paths"] == []
    on = find_research_paths(analytic_only, {
        "start_node_ids": ["topic:x"],
        "target_node_ids": ["pub:a"],
        "include_analytical": True,
    })
    assert on["paths"]
    assert on["paths"][0]["analytical_edge_count"] == 1


def test_directed_citation_respects_direction_setting():
    c = corpus()
    forward = find_research_paths(c, {"start_node_ids": ["pub:a"], "target_node_ids": ["pub:b"], "direction": "forward", "max_hops": 1})
    assert forward["paths"]
    reverse_fail = find_research_paths(c, {"start_node_ids": ["pub:b"], "target_node_ids": ["pub:a"], "direction": "forward", "max_hops": 1})
    assert reverse_fail["paths"] == []
    reverse_ok = find_research_paths(c, {"start_node_ids": ["pub:b"], "target_node_ids": ["pub:a"], "direction": "reverse", "max_hops": 1})
    assert reverse_ok["paths"]
