from app.unified_research_knowledge_graph import (
    GRAPH_CONTRACT,
    bootstrap,
    build_graph,
    chain_audit,
    export_graph,
    neighborhood,
    path,
    readiness,
    validate_graph,
)


def sample_payload():
    nodes = [
        {"id": "q1", "type": "research-question", "title": "Question"},
        {"id": "i1", "type": "investigation", "title": "Investigation"},
        {"id": "s1", "type": "source", "title": "Source"},
        {"id": "c1", "type": "claim", "title": "Claim"},
        {"id": "e1", "type": "evidence", "title": "Evidence"},
        {"id": "d1", "type": "dataset", "title": "Dataset"},
        {"id": "r1", "type": "statistical-result", "title": "Result"},
        {"id": "p1", "type": "place", "title": "Place"},
        {"id": "ev1", "type": "event", "title": "Event"},
        {"id": "a1", "type": "annotation", "title": "Annotation"},
        {"id": "ci1", "type": "citation", "title": "Citation"},
        {"id": "sy1", "type": "synthesis", "title": "Synthesis"},
        {"id": "pk1", "type": "research-package", "title": "Package"},
        {"id": "pub1", "type": "publication", "title": "Publication"},
    ]
    relations = ["frames","investigates","uses-source","asserts","supports","derived-from","located-at","occurs-at","annotates","cites","synthesizes","packages","publishes"]
    edges = []
    for idx, relation in enumerate(relations):
        edges.append({"id": f"edge:{idx+1}", "source": nodes[idx]["id"], "target": nodes[idx+1]["id"], "relation": relation})
    return {"project_id":"project:test","nodes":nodes,"edges":edges}


def test_readiness_and_bootstrap():
    r = readiness()
    assert r["ready"] is True
    assert r["database_migration_required"] is False
    assert r["server_side_graph_persistence"] is False
    b = bootstrap()
    assert b["route"] == "/research/graph"
    assert "publication" in b["node_types"]
    assert b["operations"] == ["build", "validate", "chain-audit", "neighborhood", "path", "export"]


def test_build_graph_is_deterministic():
    payload = sample_payload()
    a = build_graph(payload)
    b = build_graph(payload)
    assert a["schema"] == GRAPH_CONTRACT
    assert a["graph_id"] == b["graph_id"]
    assert a["graph_fingerprint_sha256"] == b["graph_fingerprint_sha256"]
    assert a["node_count"] == 14
    assert a["edge_count"] == 13


def test_validation_rejects_dangling_edge():
    payload = sample_payload()
    payload["edges"].append({"id":"bad","source":"missing","target":"q1","relation":"references"})
    v = validate_graph(payload)
    assert v["valid"] is False
    assert "bad" in v["dangling_edge_ids"]


def test_chain_audit_complete():
    a = chain_audit(sample_payload())
    assert a["complete_chain_present"] is True
    assert a["transition_coverage"] == 1.0


def test_neighborhood_and_path():
    payload = sample_payload()
    n = neighborhood({**payload, "node_id":"q1", "depth":2})
    assert {x["node_id"] for x in n["nodes"]} == {"q1","i1","s1"}
    p = path({**payload, "source":"q1", "target":"pub1", "directed":False})
    assert p["found"] is True
    assert p["hop_count"] == 13
    assert p["node_ids"][0] == "q1" and p["node_ids"][-1] == "pub1"


def test_export_is_portable_and_not_persisted():
    e = export_graph(sample_payload())
    assert e["media_type"] == "application/json"
    assert e["server_side_persisted"] is False
    assert '"schema": "sc-library-unified-research-knowledge-graph-snapshot/1.0"' in e["content"]
