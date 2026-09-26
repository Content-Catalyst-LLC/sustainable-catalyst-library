from __future__ import annotations

import json
import os
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

GO_INGESTION_CONTRACT = "sc-library-go-ingestion-runtime/1.0"
GO_INGESTION_VERSION = "0.1.0"
DEFAULT_GO_URL = "http://sc-library-ingestion:8090"
ALLOWED_JOB_TYPES = {
    "connector-fetch",
    "pdf-ingest",
    "ocr",
    "document-parse",
    "metadata-extract",
    "citation-parse",
    "scientific-object-extract",
    "identity-resolution",
    "embedding-index",
    "search-index",
}


def _base_url() -> str:
    return (os.getenv("SC_LIBRARY_GO_INGESTION_URL", DEFAULT_GO_URL).strip() or DEFAULT_GO_URL).rstrip("/")


def _request(path: str, *, method: str = "GET", payload: dict[str, Any] | None = None, timeout: float = 4.0) -> tuple[int, dict[str, Any]]:
    body = None if payload is None else json.dumps(payload, separators=(",", ":")).encode("utf-8")
    req = Request(_base_url() + path, data=body, method=method, headers={"Content-Type": "application/json"})
    try:
        with urlopen(req, timeout=timeout) as response:
            raw = response.read().decode("utf-8")
            return int(response.status), (json.loads(raw) if raw.strip() else {})
    except HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        try:
            data = json.loads(raw) if raw.strip() else {}
        except json.JSONDecodeError:
            data = {"error": raw or exc.reason}
        return int(exc.code), data
    except (URLError, TimeoutError, OSError) as exc:
        return 503, {"schema": GO_INGESTION_CONTRACT, "error": f"{type(exc).__name__}: {exc}", "available": False}


def ingestion_fabric_status() -> dict[str, Any]:
    code, data = _request("/health")
    compatible = bool(code == 200 and data.get("schema") == GO_INGESTION_CONTRACT and data.get("version") == GO_INGESTION_VERSION)
    return {
        "schema": GO_INGESTION_CONTRACT,
        "runtime_version": GO_INGESTION_VERSION,
        "engine": "go",
        "available": compatible,
        "reported": data,
        "job_types": sorted(ALLOWED_JOB_TYPES),
        "public_api_authority": "python-library-backend",
        "research_semantics_authority": "python-library-backend",
        "platform_core_durable_authority": True,
        "job_completion_implies_source_validity": False,
        "job_completion_implies_evidence_truth": False,
        "ingestion_automatically_promotes_core_objects": False,
    }


def submit_ingestion_job(payload: dict[str, Any]) -> dict[str, Any]:
    job_type = str(payload.get("type") or "").strip()
    if job_type not in ALLOWED_JOB_TYPES:
        raise ValueError(f"unsupported ingestion job type: {job_type or '<empty>'}")
    req = {
        "type": job_type,
        "source_key": str(payload.get("source_key") or "").strip(),
        "payload": payload.get("payload") if isinstance(payload.get("payload"), dict) else {},
        "priority": max(-100, min(100, int(payload.get("priority") or 0))),
        "max_attempts": max(1, min(20, int(payload.get("max_attempts") or 3))),
        "idempotency_key": str(payload.get("idempotency_key") or "").strip(),
    }
    code, data = _request("/v1/jobs", method="POST", payload=req)
    if code not in {200, 202}:
        raise RuntimeError(data.get("error") or f"Go ingestion runtime returned HTTP {code}")
    data["interpretation"] = {
        "queued_means_source_verified": False,
        "completed_means_source_valid": False,
        "completed_means_evidence_true": False,
        "worker_result_requires_python_validation": True,
        "platform_core_governance_changed": False,
    }
    return data


def list_ingestion_jobs(*, state: str = "", job_type: str = "") -> dict[str, Any]:
    query = urlencode({k: v for k, v in {"state": state.strip(), "type": job_type.strip()}.items() if v})
    code, data = _request("/v1/jobs" + (("?" + query) if query else ""))
    if code != 200:
        raise RuntimeError(data.get("error") or f"Go ingestion runtime returned HTTP {code}")
    return data


def get_ingestion_job(job_id: str) -> dict[str, Any]:
    safe = str(job_id or "").strip()
    if not safe or "/" in safe:
        raise ValueError("valid job_id is required")
    code, data = _request(f"/v1/jobs/{safe}")
    if code == 404:
        raise LookupError("ingestion job not found")
    if code != 200:
        raise RuntimeError(data.get("error") or f"Go ingestion runtime returned HTTP {code}")
    return data


def cancel_ingestion_job(job_id: str) -> dict[str, Any]:
    safe = str(job_id or "").strip()
    if not safe or "/" in safe:
        raise ValueError("valid job_id is required")
    code, data = _request(f"/v1/jobs/{safe}/cancel", method="POST", payload={})
    if code == 404:
        raise LookupError("ingestion job not found")
    if code not in {200, 409}:
        raise RuntimeError(data.get("error") or f"Go ingestion runtime returned HTTP {code}")
    return data
