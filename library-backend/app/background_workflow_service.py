from __future__ import annotations

from hashlib import sha256
import json
from typing import Any

from .durable_job_queue import (
    execution_fabric_readiness,
    validate_job_payload,
    submit_job as durable_submit_job,
    get_job as durable_get_job,
    list_jobs as durable_list_jobs,
    cancel_job as durable_cancel_job,
    recover_expired_leases,
)
from .specialized_worker_runtime import worker_readiness, list_workers, list_dead_letters
from .checkpointed_pipeline import (
    pipeline_readiness,
    validate_pipeline,
    persist_pipeline,
    start_pipeline_run,
    get_pipeline_run,
    resume_pipeline_run,
)
from .ingestion_job_fabric import ingestion_fabric_status

LIBRARY_VERSION = "5.78.0"
BACKEND_VERSION = "2.89.0"
CONTRACT = "sc-library-python-background-job-workflow-service/1.0"
READINESS_CONTRACT = "sc-library-python-background-job-workflow-readiness/1.0"
CONTROL_VALIDATION_CONTRACT = "sc-library-workflow-control-validation/1.0"

def _canon(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str)

def _fp(value: Any) -> str:
    return sha256(_canon(value).encode("utf-8")).hexdigest()

def _clean(value: Any) -> str:
    return str(value or "").strip()

def guardrails() -> dict[str, bool]:
    return {
        "python_is_background_workflow_authority": True,
        "python_is_research_job_control_authority": True,
        "python_is_pipeline_control_authority": True,
        "postgresql_is_authoritative_job_state": True,
        "redis_is_authoritative_job_state": False,
        "redis_dispatch_loss_loses_job": False,
        "workflow_service_is_second_job_scheduler": False,
        "workflow_service_is_second_pipeline_engine": False,
        "go_ingestion_sidecar_is_public_api_authority": False,
        "worker_success_implies_research_truth": False,
        "job_completion_implies_source_validity": False,
        "job_completion_implies_evidence_truth": False,
        "pipeline_completion_implies_research_truth": False,
        "retry_success_implies_research_truth": False,
        "dead_letter_status_implies_research_invalidity": False,
        "wordpress_php_is_background_workflow_authority": False,
        "wordpress_required_for_research_execution": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
    }

def contract() -> dict[str, Any]:
    resources = [
        "durable-research-jobs",
        "job-attempts-and-events",
        "worker-registration-and-leases",
        "worker-heartbeats-and-quarantine",
        "dead-letter-recovery",
        "checkpointed-research-pipelines",
        "pipeline-resume",
        "expired-lease-recovery",
        "go-ingestion-sidecar-status",
    ]
    basis = {"resources": resources, "guardrails": guardrails()}
    return {
        "schema": CONTRACT,
        "service_id": "library-background-workflows:" + _fp(basis)[:32],
        "service_fingerprint_sha256": _fp(basis),
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "authority": "python-backend",
        "state": "authoritative",
        "resources": resources,
        "execution_primitives": {
            "durable_jobs": "postgresql",
            "dispatch_wakeup": "redis-non-authoritative",
            "workers": "specialized-worker-runtime",
            "pipelines": "checkpointed-pipeline-engine",
            "ingestion": "go-sidecar-behind-python-authority",
        },
        "wordpress": {"role": "presentation-and-api-client", "required": False, "authoritative": False},
        "guardrails": guardrails(),
    }

def readiness() -> dict[str, Any]:
    execution = execution_fabric_readiness()
    workers = worker_readiness()
    pipelines = pipeline_readiness()
    ingestion = ingestion_fabric_status()

    execution_pg = str((execution.get("postgresql") or {}).get("state") or "unknown")
    worker_state = str(workers.get("state") or "unknown")
    pipeline_state = str(pipelines.get("state") or "unknown")
    ingestion_state = "ready" if ingestion.get("available") is True else "degraded"
    ready = execution_pg == "ready" and worker_state == "ready" and pipeline_state == "ready"

    return {
        "schema": READINESS_CONTRACT,
        "library_version": LIBRARY_VERSION,
        "backend_version": BACKEND_VERSION,
        "state": "ready" if ready else "degraded",
        "authority": "python-backend",
        "wordpress_required": False,
        "component_states": {
            "execution_fabric": str(execution.get("state") or "unknown"),
            "execution_postgresql": execution_pg,
            "worker_runtime": worker_state,
            "pipeline_engine": pipeline_state,
            "ingestion_sidecar": ingestion_state,
        },
        "components": {
            "execution_fabric": execution,
            "worker_runtime": workers,
            "pipeline_engine": pipelines,
            "ingestion_sidecar": ingestion,
        },
        "guardrails": guardrails(),
    }

def validate_control(payload: dict[str, Any]) -> dict[str, Any]:
    errors = []
    warnings = []
    if not isinstance(payload, dict):
        return {
            "schema": CONTROL_VALIDATION_CONTRACT,
            "valid": False,
            "errors": ["payload-must-be-object"],
            "warnings": [],
            "normalized": {},
            "guardrails": guardrails(),
        }

    operation = _clean(payload.get("operation")).lower()
    normalized: dict[str, Any] = {"operation": operation}

    if operation == "submit-job":
        result = validate_job_payload(payload.get("job") if isinstance(payload.get("job"), dict) else {})
        if not result.get("valid"):
            errors.extend(result.get("errors") or [])
        normalized["job"] = result.get("normalized") or {}
    elif operation == "create-pipeline-run":
        pipeline_payload = payload.get("pipeline") if isinstance(payload.get("pipeline"), dict) else {}
        result = validate_pipeline(pipeline_payload)
        if not result.get("valid"):
            errors.extend(result.get("errors") or [])
        normalized["pipeline"] = result.get("normalized") or pipeline_payload
        normalized["run"] = payload.get("run") if isinstance(payload.get("run"), dict) else {}
    elif operation in {"cancel-job", "resume-pipeline"}:
        object_id = _clean(payload.get("job_id") if operation == "cancel-job" else payload.get("run_id"))
        if not object_id:
            errors.append("object-id-required")
        normalized["object_id"] = object_id
    elif operation == "recover-expired-leases":
        try:
            normalized["limit"] = max(1, min(1000, int(payload.get("limit") or 100)))
        except (TypeError, ValueError):
            errors.append("limit-must-be-integer")
    else:
        errors.append("supported-operation-required")

    return {
        "schema": CONTROL_VALIDATION_CONTRACT,
        "valid": not errors,
        "errors": errors,
        "warnings": warnings,
        "normalized": normalized,
        "guardrails": guardrails(),
    }

def submit_background_job(payload: dict[str, Any]) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-job-response/1.0",
        "job": durable_submit_job(payload),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def background_job(job_id: str) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-job-response/1.0",
        "job": durable_get_job(job_id),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def background_jobs(*, state: str = "", capability: str = "", limit: int = 100) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-job-list-response/1.0",
        "result": durable_list_jobs(state=state, capability=capability, limit=limit),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def cancel_background_job(job_id: str, payload: dict[str, Any] | None = None) -> dict[str, Any]:
    reason = _clean((payload or {}).get("reason"))
    return {
        "schema": "sc-library-workflow-job-response/1.0",
        "job": durable_cancel_job(job_id, reason=reason),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def worker_snapshot() -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-worker-snapshot/1.0",
        "readiness": worker_readiness(),
        "workers": list_workers(),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def dead_letter_snapshot(*, state: str = "open", limit: int = 100) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-dead-letter-snapshot/1.0",
        "dead_letters": list_dead_letters(state=state, limit=limit),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def recover_workflow_leases(*, limit: int = 100) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-recovery/1.0",
        "recovery": recover_expired_leases(limit=limit),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def validate_pipeline_workflow(payload: dict[str, Any]) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-pipeline-validation/1.0",
        "validation": validate_pipeline(payload),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def create_pipeline_workflow(payload: dict[str, Any]) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ValueError("pipeline-workflow-payload-must-be-object")
    pipeline_payload = payload.get("pipeline") if isinstance(payload.get("pipeline"), dict) else {}
    run_payload = payload.get("run") if isinstance(payload.get("run"), dict) else {}
    persisted = persist_pipeline(pipeline_payload)
    pipeline = persisted.get("pipeline") or {}
    pipeline_id = _clean(pipeline.get("pipeline_id"))
    if not pipeline_id:
        raise RuntimeError("pipeline-id-missing-after-persist")
    run = start_pipeline_run(pipeline_id, run_payload)
    return {
        "schema": "sc-library-workflow-pipeline-response/1.0",
        "pipeline": persisted,
        "run": run,
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def pipeline_workflow(run_id: str) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-pipeline-response/1.0",
        "run": get_pipeline_run(run_id),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }

def resume_pipeline_workflow(run_id: str) -> dict[str, Any]:
    return {
        "schema": "sc-library-workflow-pipeline-response/1.0",
        "run": resume_pipeline_run(run_id),
        "authority": "python-backend",
        "guardrails": guardrails(),
    }
