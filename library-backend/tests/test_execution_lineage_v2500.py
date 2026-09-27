from __future__ import annotations

import app.execution_lineage as el


def fake_status():
    return {
        "schema": "sc-library-research-runtime-contract/1.0",
        "backend_version": "2.50.0",
        "contract_fingerprint_sha256": "c" * 64,
        "runtimes": [
            {"runtime_id":"python-library-backend","engine":"python","runtime_version":"2.50.0","language_version":"3.13.7","transport":"in-process","available":True,"capabilities":["research-corpus-build","dataset-export"]},
            {"runtime_id":"go-ingestion-runtime","engine":"go","runtime_version":"0.1.0","transport":"internal-http-sidecar","available":True,"capabilities":["ingestion-job-submit"]},
            {"runtime_id":"rust-graph-runtime","engine":"rust","runtime_version":"0.2.0","transport":"local-native-binary","available":True,"capabilities":["native-graph-query"]},
        ],
    }


def fake_execution(runtime="python", workload="research-corpus-build", result=None, state="completed"):
    result = result if result is not None else {"schema":"x/1.0","value":1}
    return {
        "schema":"sc-library-runtime-execution-envelope/1.0",
        "execution_id":"runtime-execution:test",
        "request_fingerprint_sha256":"a"*64,
        "result_fingerprint_sha256":el._stable_hash(result),
        "state":state,
        "workload":workload,
        "routing":{"requested_runtime":runtime,"selected_runtime":runtime,"fallback_used":False},
        "result":result,
    }


def patch(monkeypatch, execution=None):
    monkeypatch.setattr(el,"runtime_contract_status",fake_status)
    if execution is not None:
        monkeypatch.setattr(el,"execute_runtime",lambda payload: execution(payload) if callable(execution) else execution)


def test_status_and_environment_contracts(monkeypatch):
    patch(monkeypatch)
    status=el.reproducibility_status()
    assert status["schema"]=="sc-library-cross-runtime-reproducibility/1.0"
    assert status["backend_version"]=="2.50.0"
    assert status["execution_environment"]["schema"]=="sc-library-execution-environment/1.0"
    assert len(status["execution_environment"]["environment_fingerprint_sha256"])==64
    assert status["guardrails"]["matching_output_fingerprints_prove_scientific_equivalence"] is False


def test_record_captures_input_environment_and_lineage(monkeypatch):
    patch(monkeypatch, fake_execution())
    r=el.create_reproducibility_record({"workload":"research-corpus-build","runtime":"python","input":{"records":[{"record_id":"r1"}]}})
    assert r["schema"]=="sc-library-reproducibility-record/1.0"
    assert len(r["input_fingerprint_sha256"])==64
    assert len(r["semantic_result_fingerprint_sha256"])==64
    assert r["lineage"]["schema"]=="sc-library-execution-lineage/1.0"
    assert r["lineage"]["selected_runtime"]=="python"
    assert r["execution_environment"]["backend_version"]=="2.50.0"


def test_record_parent_lineage_is_explicit(monkeypatch):
    patch(monkeypatch, fake_execution())
    r=el.create_reproducibility_record({"workload":"research-corpus-build","runtime":"python","input":{},"parent_execution_id":"exec-parent","parent_lineage_fingerprint_sha256":"f"*64,"root_execution_id":"exec-root"})
    assert r["lineage"]["parent_execution_id"]=="exec-parent"
    assert r["lineage"]["root_execution_id"]=="exec-root"
    assert r["lineage"]["parent_lineage_fingerprint_sha256"]=="f"*64


def test_semantic_projection_removes_runtime_transport():
    a={"schema":"sc-library-native-graph-query/1.0","runtime":{"used":"rust"},"nodes":[{"id":"a"}],"edges":[]}
    b={"schema":"sc-library-native-graph-query/1.0","runtime":{"used":"python"},"nodes":[{"id":"a"}],"edges":[]}
    assert el._stable_hash(el._semantic_projection("native-graph-query",a))==el._stable_hash(el._semantic_projection("native-graph-query",b))


def test_same_runtime_replay_verifies(monkeypatch):
    patch(monkeypatch, fake_execution())
    base=el.create_reproducibility_record({"workload":"research-corpus-build","runtime":"python","input":{"records":[]}})
    verified=el.verify_reproducibility({"record":base,"runtime":"python"})
    assert verified["status"]=="verified"
    assert verified["cross_runtime"] is False
    assert verified["semantic_result_fingerprint_match"] is True


def test_async_go_is_not_replay_verified(monkeypatch):
    patch(monkeypatch, fake_execution(runtime="go",workload="ingestion-job-submit",state="accepted"))
    base=el.create_reproducibility_record({"workload":"ingestion-job-submit","runtime":"go","input":{"type":"ocr"}})
    out=el.verify_reproducibility({"record":base,"runtime":"go"})
    assert out["status"]=="not-applicable"
    assert out["reason"]=="workload-is-not-deterministic-replay"


def test_cross_runtime_non_graph_is_not_applicable(monkeypatch):
    patch(monkeypatch, fake_execution())
    base=el.create_reproducibility_record({"workload":"research-corpus-build","runtime":"python","input":{}})
    out=el.verify_reproducibility({"record":base,"runtime":"rust"})
    assert out["status"]=="not-applicable"
    assert out["reason"]=="workload-has-no-cross-runtime-implementation"


def test_cross_runtime_graph_reports_observed_match_without_equivalence_claim(monkeypatch):
    def run(payload):
        rt=payload.get("runtime") or "rust"
        result={"schema":"sc-library-native-graph-query/1.0","runtime":{"used":rt},"nodes":[{"id":"a"}],"edges":[]}
        return fake_execution(runtime=rt,workload="native-graph-query",result=result)
    patch(monkeypatch, run)
    base=el.create_reproducibility_record({"workload":"native-graph-query","runtime":"rust","input":{"corpus":{"nodes":[],"edges":[]},"query":{"operation":"structural-stats"}}})
    out=el.verify_reproducibility({"record":base,"runtime":"python"})
    assert out["status"]=="verified"
    assert out["cross_runtime"] is True
    assert out["interpretation"]=="observed-output-match"
    assert out["guardrails"]["cross_runtime_equivalence_proven"] is False


def test_cross_runtime_graph_difference_is_descriptive(monkeypatch):
    calls={"n":0}
    def run(payload):
        calls["n"]+=1; rt=payload.get("runtime") or "rust"
        result={"schema":"sc-library-native-graph-query/1.0","runtime":{"used":rt},"nodes":[{"id":"a" if rt=="rust" else "b"}],"edges":[]}
        return fake_execution(runtime=rt,workload="native-graph-query",result=result)
    patch(monkeypatch, run)
    base=el.create_reproducibility_record({"workload":"native-graph-query","runtime":"rust","input":{"corpus":{"nodes":[],"edges":[]},"query":{"operation":"structural-stats"}}})
    out=el.verify_reproducibility({"record":base,"runtime":"python"})
    assert out["status"]=="differs"
    assert out["guardrails"]["difference_means_one_runtime_is_wrong"] is False
    assert out["guardrails"]["human_interpretation_required_for_material_differences"] is True


def test_record_rejects_invalid_verification_object(monkeypatch):
    patch(monkeypatch)
    try: el.verify_reproducibility({"record":{"schema":"wrong"}})
    except ValueError as exc: assert "reproducibility-record" in str(exc)
    else: raise AssertionError("expected invalid record rejection")
