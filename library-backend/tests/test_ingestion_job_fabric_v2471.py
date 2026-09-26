from __future__ import annotations

import pytest

from app import ingestion_job_fabric as fabric


def test_status_contract(monkeypatch):
    monkeypatch.setattr(fabric, "_request", lambda *a, **k: (200, {
        "ok": True, "schema": fabric.GO_INGESTION_CONTRACT, "version": fabric.GO_INGESTION_VERSION,
        "queue": {"queued": 2, "running": 1},
    }))
    status = fabric.ingestion_fabric_status()
    assert status["available"] is True
    assert status["engine"] == "go"
    assert status["job_completion_implies_source_validity"] is False
    assert status["job_completion_implies_evidence_truth"] is False


def test_submit_validates_job_types_and_guardrails(monkeypatch):
    def fake(path, method="GET", payload=None, timeout=4.0):
        assert path == "/v1/jobs"
        assert method == "POST"
        return 202, {"schema": fabric.GO_INGESTION_CONTRACT, "job": {"id": "job-1", "state": "queued", "type": payload["type"]}}
    monkeypatch.setattr(fabric, "_request", fake)
    out = fabric.submit_ingestion_job({"type": "ocr", "source_key": "archive", "payload": {"record_id": "r1"}})
    assert out["job"]["state"] == "queued"
    assert out["interpretation"]["queued_means_source_verified"] is False
    assert out["interpretation"]["completed_means_evidence_true"] is False
    with pytest.raises(ValueError):
        fabric.submit_ingestion_job({"type": "truth-promotion"})


def test_list_get_cancel(monkeypatch):
    calls=[]
    def fake(path, method="GET", payload=None, timeout=4.0):
        calls.append((path,method))
        if path.endswith("/cancel"):
            return 200, {"schema":fabric.GO_INGESTION_CONTRACT,"job":{"id":"job-1","state":"cancelled"}}
        if path.startswith("/v1/jobs/job-1"):
            return 200, {"schema":fabric.GO_INGESTION_CONTRACT,"job":{"id":"job-1","state":"queued"}}
        return 200, {"schema":fabric.GO_INGESTION_CONTRACT,"jobs":[],"counts":{"queued":0}}
    monkeypatch.setattr(fabric, "_request", fake)
    assert fabric.list_ingestion_jobs(state="queued")["counts"]["queued"] == 0
    assert fabric.get_ingestion_job("job-1")["job"]["id"] == "job-1"
    assert fabric.cancel_ingestion_job("job-1")["job"]["state"] == "cancelled"
    assert any(m=="POST" for _,m in calls)
