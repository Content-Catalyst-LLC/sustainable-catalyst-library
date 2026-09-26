from pathlib import Path

from app.native_graph_query import NATIVE_QUERY_CONTRACT, query_native_graph
from app.native_graph_runtime import native_graph_runtime_status


def sample():
    return {
        "nodes": [
            {"id":"pub:a","kind":"publication"},
            {"id":"pub:b","kind":"publication"},
            {"id":"pub:c","kind":"publication"},
            {"id":"finding:1","kind":"finding"},
            {"id":"topic:x","kind":"topic"},
            {"id":"isolated:1","kind":"publication"},
        ],
        "edges": [
            {"source":"finding:1","target":"pub:a","relationship_basis":"reviewed-finding-evidence","directed":False},
            {"source":"pub:a","target":"pub:b","relationship_basis":"explicit-citation","directed":True},
            {"source":"pub:b","target":"pub:c","relationship_basis":"metadata-association","directed":False},
            {"source":"pub:c","target":"topic:x","relationship_basis":"publication-topic-cooccurrence","directed":False,"analytical":True},
        ],
    }


def force_python(monkeypatch):
    monkeypatch.setenv("SC_LIBRARY_NATIVE_GRAPH_BIN", "/definitely/not/a/runtime")


def test_neighborhood_python_fallback_respects_analytical_opt_in(monkeypatch):
    force_python(monkeypatch)
    result=query_native_graph(sample(), {"operation":"neighborhood","start_node_ids":["finding:1"],"max_depth":4,"runtime":"rust"})
    assert result["schema"] == NATIVE_QUERY_CONTRACT
    assert result["runtime"]["used"] == "python-fallback"
    ids={n["id"] for n in result["nodes"]}
    assert {"finding:1","pub:a","pub:b","pub:c"}.issubset(ids)
    assert "topic:x" not in ids
    assert result["interpretation"]["analytical_relationships_are_opt_in"] is True

    analytical=query_native_graph(sample(), {"operation":"neighborhood","start_node_ids":["finding:1"],"max_depth":4,"runtime":"python","include_analytical":True})
    assert "topic:x" in {n["id"] for n in analytical["nodes"]}


def test_reachability_returns_depths_without_truth_semantics(monkeypatch):
    force_python(monkeypatch)
    result=query_native_graph(sample(), {"operation":"reachability","start_node_ids":["finding:1"],"max_depth":3,"runtime":"python"})
    depth={x["id"]:x["depth"] for x in result["reachability"]}
    assert depth["finding:1"] == 0
    assert depth["pub:a"] == 1
    assert depth["pub:b"] == 2
    assert depth["pub:c"] == 3
    assert result["interpretation"]["connectivity_implies_evidence_support"] is False
    assert result["interpretation"]["connectivity_implies_causality"] is False


def test_components_and_structural_stats_are_descriptive(monkeypatch):
    force_python(monkeypatch)
    components=query_native_graph(sample(), {"operation":"connected-components","runtime":"python"})
    sizes=sorted(c["node_count"] for c in components["components"])
    assert sizes == [1,1,4]
    assert components["interpretation"]["component_membership_implies_consensus"] is False

    stats=query_native_graph(sample(), {"operation":"structural-stats","runtime":"python"})
    assert stats["stats"]["node_count"] == 6
    assert stats["stats"]["edge_count"] == 3
    assert stats["stats"]["isolated_node_count"] == 2
    assert stats["stats"]["component_count"] == 3
    assert stats["stats"]["relationship_basis_counts"]["explicit-citation"] == 1
    assert "publication-topic-cooccurrence" not in stats["stats"]["relationship_basis_counts"]
    assert stats["interpretation"]["degree_or_connectivity_is_quality_score"] is False


def test_subgraph_is_induced_by_explicit_node_scope(monkeypatch):
    force_python(monkeypatch)
    result=query_native_graph(sample(), {"operation":"subgraph","node_ids":["pub:a","pub:b","pub:c"],"runtime":"python"})
    assert [n["id"] for n in result["nodes"]] == ["pub:a","pub:b","pub:c"]
    assert len(result["edges"]) == 2
    assert {e["relationship_basis"] for e in result["edges"]} == {"explicit-citation","metadata-association"}


def test_native_query_protocol_parser(tmp_path, monkeypatch):
    fake=tmp_path/"fake-native"
    fake.write_text(
        "#!/bin/sh\n"
        "if [ \"$1\" = status ]; then printf 'STATUS\\tsc-library-native-graph-runtime/1.0\\t0.2.0\\trust\\tstd-only\\tbounded-pathfinding,neighborhood,reachability,connected-components,subgraph,structural-stats\\n'; "
        "else printf 'META\\tsc-library-native-graph-runtime/1.0\\t0.2.0\\tneighborhood\\nNODE\\tfinding:1\\t0\\nNODE\\tpub:a\\t1\\nEDGE\\t0\\n'; fi\n",
        encoding="utf-8",
    )
    fake.chmod(0o755)
    monkeypatch.setenv("SC_LIBRARY_NATIVE_GRAPH_BIN", str(fake))
    status=native_graph_runtime_status()
    assert status["available"] is True
    assert status["reported_version"] == "0.2.0"
    result=query_native_graph(sample(), {"operation":"neighborhood","start_node_ids":["finding:1"],"runtime":"rust"})
    assert result["runtime"]["used"] == "rust"
    assert [n["id"] for n in result["nodes"]] == ["finding:1","pub:a"]
    assert len(result["edges"]) == 1


def test_unknown_operation_fails_closed_to_supported_default(monkeypatch):
    force_python(monkeypatch)
    result=query_native_graph(sample(), {"operation":"centrality-score","start_node_ids":["pub:a"],"runtime":"python"})
    assert result["operation"] == "neighborhood"
    assert result["interpretation"]["native_results_are_structural_only"] is True
