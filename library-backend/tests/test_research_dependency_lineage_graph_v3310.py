from app.research_dependency_lineage_graph import (
    contract, readiness, bootstrap, build_graph, validate_graph, lineage,
    neighborhood, path, impact_analysis, provenance_audit, export_graph,
)


def project():
    return {
        "schema": "sc-library-unified-research-project/1.0",
        "project_manifest_id": "unified-research-project:test",
        "library_project_id": "library-project:test",
        "components": [
            {"object_id": "question:1", "type": "research-question", "authority": "research-investigation", "provenance": {"source_refs": ["brief:1"]}},
            {"object_id": "source:1", "type": "source", "authority": "catalog-source-ingestion", "depends_on": ["question:1"], "provenance": {"source_refs": ["doi:10/example"]}},
            {"object_id": "dataset:1", "type": "dataset", "authority": "structured-evidence-statistical-evidence", "depends_on": ["source:1"], "provenance": {"derived_from": ["source:1"]}},
            {"object_id": "analysis:1", "type": "statistical-result", "authority": "statistical-evidence", "depends_on": ["dataset:1"], "provenance": {"input_refs": ["dataset:1"]}},
            {"object_id": "synthesis:1", "type": "synthesis", "authority": "research-synthesis", "depends_on": ["analysis:1"]},
        ],
    }


def graph():
    return build_graph({"project": project(), "relations": [{"source": "synthesis:1", "target": "source:1", "relation": "cites", "authority": "research-synthesis"}]})


def test_contract_and_readiness():
    c = contract()
    assert c["library_version"] == "6.31.0"
    assert c["backend_version"] == "3.31.0"
    assert c["route"] == "/research/project/lineage"
    assert c["next_release"] == "6.32.0"
    r = readiness()
    assert r["ready"] is True
    assert r["database_migration_required"] is False
    assert bootstrap()["operations"][-1] == "export"


def test_build_preserves_explicit_edges_only():
    g = graph()
    assert g["node_count"] == 5
    assert g["edge_count"] >= 6
    assert all(e["explicit"] is True and e["inferred"] is False for e in g["edges"])
    assert g["external_reference_count"] == 2
    assert g["persisted"] is False


def test_validation_and_cycle_detection():
    g = graph()
    v = validate_graph({"graph": g})
    assert v["valid"] is True
    assert v["cycle_count"] == 0
    cyc = build_graph({"project": project(), "relations": [{"source": "question:1", "target": "synthesis:1", "relation": "depends-on"}]})
    assert cyc["cycle_count"] == 1


def test_lineage_and_impact():
    g = graph()
    ancestors = lineage({"graph": g, "node_id": "synthesis:1", "direction": "ancestors"})
    assert any(x["object_id"] == "question:1" for x in ancestors["items"])
    descendants = lineage({"graph": g, "node_id": "question:1", "direction": "descendants"})
    assert any(x["object_id"] == "synthesis:1" for x in descendants["items"])
    impact = impact_analysis({"graph": g, "node_id": "source:1"})
    assert any(x["object_id"] == "synthesis:1" for x in impact["downstream_dependents"])
    assert impact["impact_analysis_implies_scientific_effect"] is False


def test_neighborhood_and_path():
    g = graph()
    n = neighborhood({"graph": g, "node_id": "dataset:1", "depth": 1})
    ids = {x["object_id"] for x in n["nodes"]}
    assert {"source:1", "dataset:1", "analysis:1"}.issubset(ids)
    p = path({"graph": g, "source": "synthesis:1", "target": "question:1", "directed": True})
    assert p["found"] is True
    assert p["path_existence_implies_causality"] is False


def test_provenance_and_export():
    g = graph()
    a = provenance_audit({"graph": g})
    assert a["node_count"] == 5
    assert "synthesis:1" in a["nodes_without_provenance"]
    assert a["provenance_coverage_implies_truth"] is False
    e = export_graph({"graph": g})
    assert e["media_type"] == "application/json"
    assert len(e["sha256"]) == 64
    assert e["persisted"] is False
