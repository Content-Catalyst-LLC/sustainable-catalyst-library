from __future__ import annotations

import app.unified_runtime_contract as ur


def fake_go(available=True):
    return {
        "schema": "sc-library-go-ingestion-runtime/1.0",
        "runtime_version": "0.1.0",
        "available": available,
        "reported": {"version": "0.1.0", "durability": "state-file"},
    }


def fake_rust(available=True):
    return {
        "schema": "sc-library-native-graph-runtime/1.0",
        "runtime_version": "0.2.0",
        "reported_version": "0.2.0",
        "available": available,
    }


def patch_status(monkeypatch, *, go=True, rust=True):
    monkeypatch.setattr(ur, "ingestion_fabric_status", lambda: fake_go(go))
    monkeypatch.setattr(ur, "native_graph_runtime_status", lambda: fake_rust(rust))


def test_status_declares_three_runtimes_and_authority(monkeypatch):
    patch_status(monkeypatch)
    status = ur.runtime_contract_status()
    assert status["schema"] == "sc-library-research-runtime-contract/1.0"
    assert status["backend_version"] == "2.49.0"
    assert [r["engine"] for r in status["runtimes"]] == ["python", "go", "rust"]
    assert status["authority"]["research_semantics"] == "python-library-backend"
    assert status["authority"]["durable_governed_research_objects"] == "platform-core"
    assert status["guardrails"]["cross_runtime_result_is_automatically_equivalent"] is False


def test_contract_fingerprint_ignores_transient_availability(monkeypatch):
    patch_status(monkeypatch, go=True, rust=True)
    a = ur.runtime_contract_status()["contract_fingerprint_sha256"]
    patch_status(monkeypatch, go=False, rust=False)
    b = ur.runtime_contract_status()["contract_fingerprint_sha256"]
    assert a == b


def test_auto_routes_primary_runtime(monkeypatch):
    patch_status(monkeypatch)
    assert ur.resolve_runtime({"workload": "research-corpus-build"})["selected_runtime"] == "python"
    assert ur.resolve_runtime({"workload": "ingestion-job-submit"})["selected_runtime"] == "go"
    assert ur.resolve_runtime({"workload": "native-graph-query"})["selected_runtime"] == "rust"


def test_incompatible_explicit_runtime_is_rejected(monkeypatch):
    patch_status(monkeypatch)
    try:
        ur.resolve_runtime({"workload": "dataset-export", "runtime": "rust"})
    except ValueError as exc:
        assert "not compatible" in str(exc)
    else:
        raise AssertionError("expected incompatible runtime to be rejected")


def test_graph_fallback_is_explicit(monkeypatch):
    patch_status(monkeypatch, rust=False)
    decision = ur.resolve_runtime({"workload": "native-graph-query", "runtime": "auto", "allow_fallback": True})
    assert decision["selected_runtime"] == "python"
    assert decision["fallback_used"] is True
    assert decision["reason"] == "primary-runtime-unavailable-explicit-fallback"
    assert decision["guardrails"]["fallback_is_silent"] is False


def test_graph_without_fallback_reports_not_ready(monkeypatch):
    patch_status(monkeypatch, rust=False)
    decision = ur.resolve_runtime({"workload": "native-graph-query", "runtime": "rust", "allow_fallback": False})
    assert decision["selected_runtime"] == "rust"
    assert decision["execution_ready"] is False
    assert decision["fallback_used"] is False


def test_execute_python_corpus_returns_common_envelope(monkeypatch):
    patch_status(monkeypatch)
    result = ur.execute_runtime({
        "workload": "research-corpus-build",
        "input": {"title": "Runtime test", "records": [{"record_id": "r:1", "title": "One"}]},
    })
    assert result["schema"] == "sc-library-runtime-execution-envelope/1.0"
    assert result["state"] == "completed"
    assert result["routing"]["selected_runtime"] == "python"
    assert result["result"]["schema"] == "sc-library-research-corpus/1.0"
    assert result["request_fingerprint_sha256"]
    assert result["result_fingerprint_sha256"]
    assert result["guardrails"]["automatic_platform_core_promotion"] is False


def test_execute_go_returns_accepted_envelope(monkeypatch):
    patch_status(monkeypatch)
    monkeypatch.setattr(ur, "submit_ingestion_job", lambda payload: {"schema": "sc-library-go-ingestion-runtime/1.0", "job": {"id": "job-1", "state": "queued"}})
    result = ur.execute_runtime({"workload": "ingestion-job-submit", "input": {"type": "ocr", "source_key": "x"}})
    assert result["state"] == "accepted"
    assert result["routing"]["selected_runtime"] == "go"
    assert result["result"]["job"]["state"] == "queued"


def test_execute_rust_requires_actual_rust_execution(monkeypatch):
    patch_status(monkeypatch)
    monkeypatch.setattr(ur, "query_native_graph", lambda corpus, query: {"runtime": {"used": "rust"}, "schema": "sc-library-native-graph-query/1.0"})
    result = ur.execute_runtime({"workload": "native-graph-query", "runtime": "rust", "input": {"corpus": {"nodes": [], "edges": []}, "query": {"operation": "structural-stats"}}})
    assert result["routing"]["selected_runtime"] == "rust"
    assert result["result"]["runtime"]["used"] == "rust"


def test_execute_rust_rejects_hidden_native_fallback(monkeypatch):
    patch_status(monkeypatch)
    monkeypatch.setattr(ur, "query_native_graph", lambda corpus, query: {"runtime": {"used": "python-fallback"}})
    try:
        ur.execute_runtime({"workload": "native-graph-query", "runtime": "rust", "input": {"corpus": {}, "query": {}}})
    except RuntimeError as exc:
        assert "did not complete in Rust" in str(exc)
    else:
        raise AssertionError("expected hidden Rust fallback to be rejected")
