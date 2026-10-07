from app.research_reproducibility_audit_console import (
    bootstrap,
    build_audit_manifest,
    drift_audit,
    export_bundle,
    integrity_audit,
    lineage_audit,
    readiness,
    reproducibility_audit,
)


def manifest(version="abc123"):
    return build_audit_manifest({
        "subject": {"id": "research:weathering", "title": "Accelerated weathering review", "authority": "research-project-workspace"},
        "research_objects": [{"id": "obj:1", "authority": "library", "version": "1"}],
        "source_provenance": [{"record_id": "source:1", "source_authority": "institution:alpha", "doi": "10.1000/example"}],
        "dependencies": [{"id": "python", "version": "3.12.7"}, {"id": "analysis-code", "commit_sha": version}],
        "artifacts": [{"artifact_id": "artifact:1", "authority": "workspace", "content": "abc", "sha256": "ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad"}],
        "environment": {"os": "linux", "container": "sha256:example"},
        "runtime": {"python": "3.12.7"},
        "parameters": {"iterations": 1000},
        "executions": [{"execution_id": "run:1", "runtime": "python", "status": "completed"}],
        "reviews": [{"review_id": "review:1", "decision": "accepted"}],
        "federation": {"result_bundle_id": "federated-result-bundle:1"},
    })


def test_readiness_and_bootstrap():
    r = readiness()
    assert r["library_version"] == "6.38.0"
    assert r["backend_version"] == "3.38.0"
    assert r["ready"] is True
    assert r["code_execution_configured"] is False
    assert r["automatic_reexecution"] is False
    assert r["database_migration_required"] is False
    assert bootstrap()["operations"] == ["manifest", "integrity-audit", "reproducibility-audit", "lineage-audit", "drift-audit", "export"]


def test_manifest_preserves_payloads_and_authority():
    m = manifest()
    assert m["subject_id"] == "research:weathering"
    assert m["payloads_rewritten"] is False
    assert m["executed"] is False
    assert m["persisted"] is False
    assert m["source_provenance"][0]["payload"]["source_authority"] == "institution:alpha"
    assert m["guardrails"]["automatic_reexecution"] is False


def test_integrity_audit_verifies_only_supplied_content():
    a = integrity_audit({"manifest": manifest()})
    assert a["component_fingerprint_mismatch_count"] == 0
    assert a["artifact_hash_mismatch_count"] == 0
    assert a["artifact_findings"][0]["status"] == "verified"
    assert a["external_fetch_performed"] is False
    assert a["integrity_observation_is_reproducibility_certification"] is False


def test_reproducibility_audit_is_observational_not_certification():
    a = reproducibility_audit({"manifest": manifest()})
    assert a["observation_state"] == "complete-observation-set"
    assert a["missing_check_count"] == 0
    assert a["observation_state_is_reproducibility_certification"] is False
    assert a["automatic_reexecution"] is False


def test_lineage_audit_does_not_infer_relationships():
    a = lineage_audit({"manifest": manifest()})
    assert a["finding_count"] >= 6
    assert a["automatic_lineage_inference"] is False
    assert a["lineage_completeness_implies_truth"] is False


def test_drift_audit_reports_change_without_calling_it_error():
    before = manifest("abc123")
    after = manifest("def456")
    a = drift_audit({"baseline_manifest": before, "current_manifest": after})
    assert a["drift_observed"] is True
    assert a["changed_component_count"] == 1
    assert a["drift_implies_error"] is False
    assert a["automatic_baseline_replacement"] is False


def test_export_is_deterministic_and_preserves_guardrails():
    m = manifest()
    i = integrity_audit({"manifest": m})
    r = reproducibility_audit({"manifest": m})
    l = lineage_audit({"manifest": m})
    x1 = export_bundle({"manifest": m, "integrity_audit": i, "reproducibility_audit": r, "lineage_audit": l})
    x2 = export_bundle({"manifest": m, "integrity_audit": i, "reproducibility_audit": r, "lineage_audit": l})
    assert x1["content_sha256"] == x2["content_sha256"]
    assert x1["automatic_external_fetch"] is False
    assert x1["automatic_reexecution"] is False
    assert x1["automatic_persistence"] is False
    assert x1["automatic_reproducibility_certification"] is False
    assert x1["automatic_truth_promotion"] is False
