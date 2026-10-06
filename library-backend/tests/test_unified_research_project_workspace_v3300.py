from app.unified_research_project_workspace import (
    contract, readiness, bootstrap, compose_project, validate_project,
    inventory, authority_audit, dependency_summary, handoff_manifest, export_project,
)

def sample_payload():
    return {
        "library_project_id": "library-project:1",
        "title": "Accelerated weathering research",
        "research_question": "What evidence supports accelerated weathering?",
        "components": [
            {"object_id": "question:1", "type": "research-question", "authority": "research-investigation"},
            {"object_id": "source:1", "type": "source", "authority": "catalog-source-ingestion", "depends_on": ["question:1"]},
            {"object_id": "dataset:1", "type": "dataset", "authority": "structured-evidence-statistical-evidence", "depends_on": ["source:1"]},
            {"object_id": "synthesis:1", "type": "synthesis", "authority": "research-synthesis", "depends_on": ["dataset:1"]},
        ],
    }

def test_contract_and_readiness():
    c = contract(); assert c["library_version"] == "6.30.0"; assert c["backend_version"] == "3.30.0"
    assert c["route"] == "/research/project"; assert c["project_persistence_authority"] == "python-research-state-postgresql"
    r = readiness(); assert r["ready"] is True; assert r["database_migration_required"] is False; assert r["wordpress_required"] is False
    assert bootstrap()["operations"] == ["compose","validate","inventory","authority-audit","dependency-summary","handoff-manifest","export"]

def test_compose_is_deterministic():
    a = compose_project(sample_payload()); b = compose_project(sample_payload())
    assert a["project_manifest_fingerprint_sha256"] == b["project_manifest_fingerprint_sha256"]
    assert a["persisted"] is False; assert a["component_count"] == 4

def test_validation_and_inventory():
    p = compose_project(sample_payload()); v = validate_project({"project": p}); assert v["valid"] is True
    i = inventory({"project": p}); assert i["by_type"]["dataset"] == 1; assert i["coverage_complete"] is False
    assert i["coverage_complete_implies_research_quality"] is False

def test_authority_and_dependencies():
    p = compose_project(sample_payload()); a = authority_audit({"project": p}); assert a["state"] == "pass"
    assert a["workspace_claims_component_authority"] is False
    d = dependency_summary({"project": p}); assert d["node_count"] == 4; assert d["edge_count"] == 3
    assert d["dependency_count_implies_importance"] is False

def test_handoff_is_preview_only():
    p = compose_project(sample_payload()); h = handoff_manifest({"project": p, "target_product": "workspace", "intent": "run-analysis"})
    assert h["target_product"] == "workspace"; assert h["automatic_delivery"] is False
    assert h["remote_execution_started"] is False; assert h["persisted"] is False

def test_export():
    p = compose_project(sample_payload()); e = export_project({"project": p})
    assert e["media_type"] == "application/json"; assert len(e["sha256"]) == 64; assert e["persisted"] is False
