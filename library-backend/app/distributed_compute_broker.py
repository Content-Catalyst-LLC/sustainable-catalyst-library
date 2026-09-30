from __future__ import annotations

from datetime import datetime, timezone
from hashlib import sha256
import json
from typing import Any
from uuid import uuid4

from .settings import settings
from .specialized_worker_runtime import PROFILES

BROKER_READINESS_CONTRACT = "sc-library-distributed-compute-broker-readiness/1.0"
CAPABILITY_CATALOG_CONTRACT = "sc-library-compute-capability-catalog/1.0"
PLACEMENT_DECISION_CONTRACT = "sc-library-compute-placement-decision/1.0"
OBSERVABILITY_CONTRACT = "sc-library-runtime-observability/1.0"
RUNTIME_OBSERVATION_CONTRACT = "sc-library-runtime-observation/1.0"
ADMISSION_CONTRACT = "sc-library-compute-admission/1.0"
VALIDATION_CONTRACT = "sc-library-compute-request-validation/1.0"


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _hash(value: Any) -> str:
    return sha256(json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, Any]:
    return {
        "postgresql_authoritative_job_state": True,
        "redis_authoritative_job_state": False,
        "compute_broker_is_second_job_scheduler": False,
        "placement_is_operational_not_scientific": True,
        "runtime_health_implies_research_quality": False,
        "lower_latency_implies_better_research": False,
        "runtime_success_implies_result_truth": False,
        "queue_admission_implies_evidence_validity": False,
        "observability_metrics_are_descriptive": True,
        "automatic_platform_core_promotion": False,
        "standby_runtime_is_silently_activated": False,
    }


def capability_catalog() -> dict[str, Any]:
    capabilities: dict[str, list[dict[str, Any]]] = {}
    profiles = []
    for worker_class, profile in sorted(PROFILES.items()):
        item = {
            "worker_class": worker_class,
            "runtimes": list(profile.get("runtimes") or []),
            "capabilities": list(profile.get("capabilities") or []),
            "execution_mode": profile.get("execution_mode"),
            "profile_active": bool(profile.get("active")),
            "default_concurrency": int(profile.get("default_concurrency") or 1),
        }
        profiles.append(item)
        for capability in item["capabilities"]:
            capabilities.setdefault(capability, []).append({
                "worker_class": worker_class,
                "runtimes": item["runtimes"],
                "profile_active": item["profile_active"],
                "execution_mode": item["execution_mode"],
            })
    return {
        "schema": CAPABILITY_CATALOG_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "profiles": profiles,
        "capabilities": capabilities,
        "guardrails": guardrails(),
    }


def validate_compute_request(payload: dict[str, Any]) -> dict[str, Any]:
    from .durable_job_queue import validate_job_payload
    if not isinstance(payload, dict):
        return {"schema": VALIDATION_CONTRACT, "valid": False, "errors": ["payload-must-be-object"], "guardrails": guardrails()}
    job_payload = payload.get("job") if isinstance(payload.get("job"), dict) else payload
    job_validation = validate_job_payload(job_payload)
    errors = list(job_validation.get("errors") or [])
    normalized_job = dict(job_validation.get("normalized") or {})
    capability = _clean(normalized_job.get("capability")).lower()
    requested_runtime = _clean(normalized_job.get("requested_runtime") or "auto").lower()
    catalog = capability_catalog()
    profile_rows = list(catalog["capabilities"].get(capability) or [])
    if not profile_rows:
        errors.append("capability-not-registered")
    elif not any(row["profile_active"] and (requested_runtime == "auto" or requested_runtime in row["runtimes"]) for row in profile_rows):
        errors.append("no-active-compatible-runtime-profile")
    quota_scope = _clean(payload.get("quota_scope") or "library")[:128]
    normalized = {
        "job": normalized_job,
        "quota_scope": quota_scope,
        "routing_policy": "least-loaded-deterministic",
        "allow_queue_wait": bool(payload.get("allow_queue_wait", True)),
    }
    normalized["compute_request_fingerprint_sha256"] = _hash({
        "job": {k: v for k, v in normalized_job.items() if k not in {"job_id", "job_fingerprint_sha256"}},
        "quota_scope": quota_scope,
        "routing_policy": normalized["routing_policy"],
    })
    return {
        "schema": VALIDATION_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "valid": not errors,
        "errors": errors,
        "normalized": normalized,
        "guardrails": guardrails(),
    }


def _aggregate_worker_candidates(capability: str, requested_runtime: str, workers: list[dict[str, Any]]) -> list[dict[str, Any]]:
    candidates: list[dict[str, Any]] = []
    for raw in workers:
        worker = dict(raw)
        worker_class = _clean(worker.get("worker_class"))
        profile = PROFILES.get(worker_class)
        if not profile or not profile.get("active"):
            continue
        runtimes = [str(x) for x in worker.get("runtimes") or profile.get("runtimes") or []]
        capabilities = [str(x) for x in worker.get("capabilities") or profile.get("capabilities") or []]
        if capability not in capabilities:
            continue
        if requested_runtime != "auto" and requested_runtime not in runtimes:
            continue
        state = _clean(worker.get("state") or "active").lower()
        stale = bool(worker.get("stale", False))
        concurrency = max(1, int(worker.get("concurrency_limit") or profile.get("default_concurrency") or 1))
        active_attempts = max(0, int(worker.get("active_attempts") or 0))
        slots = max(0, concurrency - active_attempts)
        recent_failures = max(0, int(worker.get("recent_failures") or 0))
        recent_completed = max(0, int(worker.get("recent_completed") or 0))
        p50_ms = worker.get("p50_latency_ms")
        candidates.append({
            "worker_id": _clean(worker.get("worker_id")) or f"{worker_class}:unregistered",
            "worker_class": worker_class,
            "runtime": requested_runtime if requested_runtime != "auto" else (runtimes[0] if runtimes else "auto"),
            "state": state,
            "stale": stale,
            "concurrency_limit": concurrency,
            "active_attempts": active_attempts,
            "available_slots": slots,
            "recent_completed": recent_completed,
            "recent_failures": recent_failures,
            "p50_latency_ms": float(p50_ms) if p50_ms is not None else None,
            "eligible": state == "active" and not stale,
        })
    # The order is an operational deterministic tie-break: available capacity, lower utilization,
    # fewer recent failures, then stable worker identity. It is not a research-quality score.
    candidates.sort(key=lambda x: (
        0 if x["eligible"] and x["available_slots"] > 0 else 1,
        0 if x["eligible"] else 1,
        x["active_attempts"] / max(1, x["concurrency_limit"]),
        x["recent_failures"],
        x["worker_class"],
        x["worker_id"],
    ))
    for i, candidate in enumerate(candidates, start=1):
        candidate["operational_rank"] = i
    return candidates


def plan_compute(payload: dict[str, Any], snapshot: dict[str, Any] | None = None) -> dict[str, Any]:
    validation = validate_compute_request(payload)
    if not validation["valid"]:
        return {
            "schema": PLACEMENT_DECISION_CONTRACT,
            "version": "5.53.0",
            "backend_version": "2.64.0",
            "admission": {"schema": ADMISSION_CONTRACT, "state": "rejected", "reason": "invalid-request"},
            "errors": validation["errors"],
            "guardrails": guardrails(),
        }
    normalized = validation["normalized"]
    job = normalized["job"]
    if snapshot is None:
        snapshot = runtime_observability()
    workers = list(snapshot.get("workers") or [])
    queues = dict(snapshot.get("queues") or {})
    capability = job["capability"]
    requested_runtime = job["requested_runtime"]
    global_queued = int(queues.get("queued_retry") or 0)
    global_running = int(queues.get("leased_running") or 0)
    capability_queued = int((queues.get("by_capability") or {}).get(capability, 0))
    active_profiles = [
        (worker_class, profile) for worker_class, profile in sorted(PROFILES.items())
        if profile.get("active") and capability in (profile.get("capabilities") or [])
        and (requested_runtime == "auto" or requested_runtime in (profile.get("runtimes") or []))
    ]
    reason = "admitted"
    state = "admitted"
    if not active_profiles:
        state, reason = "rejected", "no-active-compatible-runtime-profile"
    elif global_queued >= settings.compute_max_queued_jobs:
        state, reason = "deferred", "global-queue-backpressure"
    elif global_running >= settings.compute_max_running_jobs:
        state, reason = "deferred", "global-running-quota"
    elif capability_queued >= settings.compute_max_capability_queue:
        state, reason = "deferred", "capability-queue-backpressure"
    candidates = _aggregate_worker_candidates(capability, requested_runtime, workers)
    selected = next((x for x in candidates if x["eligible"] and x["available_slots"] > 0), None)
    if selected is None and state == "admitted":
        # Durable queueing remains valid when a compatible active profile exists but no slot is live now.
        worker_class, profile = active_profiles[0]
        selected = {
            "worker_id": None,
            "worker_class": worker_class,
            "runtime": requested_runtime if requested_runtime != "auto" else str((profile.get("runtimes") or ["auto"])[0]),
            "state": "awaiting-worker",
            "available_slots": 0,
            "operational_rank": None,
            "eligible": True,
        }
        reason = "admitted-durable-queue-awaiting-worker"
    basis = {
        "compute_request_fingerprint_sha256": normalized["compute_request_fingerprint_sha256"],
        "admission_state": state,
        "reason": reason,
        "selected_worker_class": selected.get("worker_class") if selected else None,
        "selected_runtime": selected.get("runtime") if selected else None,
        "queue_snapshot": {
            "queued_retry": global_queued,
            "leased_running": global_running,
            "capability_queued": capability_queued,
        },
    }
    return {
        "schema": PLACEMENT_DECISION_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "placement_id": "compute-placement:" + uuid4().hex,
        "compute_request_fingerprint_sha256": normalized["compute_request_fingerprint_sha256"],
        "admission": {
            "schema": ADMISSION_CONTRACT,
            "state": state,
            "reason": reason,
            "limits": {
                "max_queued_jobs": settings.compute_max_queued_jobs,
                "max_running_jobs": settings.compute_max_running_jobs,
                "max_capability_queue": settings.compute_max_capability_queue,
            },
        },
        "selected": selected,
        "candidates": candidates,
        "placement_decision_fingerprint_sha256": _hash(basis),
        "queue_snapshot": basis["queue_snapshot"],
        "routing_policy": normalized["routing_policy"],
        "guardrails": guardrails(),
    }


def _runtime_snapshot() -> dict[str, Any]:
    from .db import get_pool
    workers: list[dict[str, Any]] = []
    queues = {"queued_retry": 0, "leased_running": 0, "by_capability": {}, "by_runtime": {}}
    with get_pool().connection(timeout=4) as conn, conn.cursor() as cur:
        cur.execute("""
            SELECT w.*,
                   COALESCE(a.active_attempts,0) AS active_attempts,
                   COALESCE(m.recent_completed,0) AS recent_completed,
                   COALESCE(m.recent_failures,0) AS recent_failures,
                   m.p50_latency_ms,
                   EXTRACT(EPOCH FROM (now()-w.heartbeat_at)) AS heartbeat_age_seconds
            FROM library_research_workers w
            LEFT JOIN (
                SELECT worker_id,count(*) FILTER (WHERE state IN ('leased','running')) AS active_attempts
                FROM library_research_job_attempts GROUP BY worker_id
            ) a ON a.worker_id=w.worker_id
            LEFT JOIN (
                SELECT worker_id,
                       count(*) FILTER (WHERE state='complete') AS recent_completed,
                       count(*) FILTER (WHERE state='failed') AS recent_failures,
                       percentile_cont(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (finished_at-started_at))*1000)
                           FILTER (WHERE finished_at IS NOT NULL AND started_at IS NOT NULL) AS p50_latency_ms
                FROM library_research_job_attempts
                WHERE leased_at >= now() - (%s * interval '1 minute')
                GROUP BY worker_id
            ) m ON m.worker_id=w.worker_id
            ORDER BY w.worker_class,w.worker_id
        """, (settings.compute_observation_window_minutes,))
        for row in cur.fetchall():
            d = dict(row)
            age = float(d.pop("heartbeat_age_seconds") or 0.0)
            d["heartbeat_age_seconds"] = round(age, 3)
            d["stale"] = age > settings.worker_stale_seconds
            if d.get("p50_latency_ms") is not None:
                d["p50_latency_ms"] = round(float(d["p50_latency_ms"]), 3)
            for key, value in list(d.items()):
                if hasattr(value, "isoformat"):
                    d[key] = value.isoformat().replace("+00:00", "Z")
            workers.append(d)
        cur.execute("SELECT state,count(*) AS n FROM library_research_jobs WHERE state IN ('queued','retry','leased','running') GROUP BY state")
        state_counts = {str(x["state"]): int(x["n"]) for x in cur.fetchall()}
        queues["queued_retry"] = state_counts.get("queued", 0) + state_counts.get("retry", 0)
        queues["leased_running"] = state_counts.get("leased", 0) + state_counts.get("running", 0)
        cur.execute("SELECT capability,count(*) AS n FROM library_research_jobs WHERE state IN ('queued','retry') GROUP BY capability")
        queues["by_capability"] = {str(x["capability"]): int(x["n"]) for x in cur.fetchall()}
        cur.execute("SELECT requested_runtime,count(*) AS n FROM library_research_jobs WHERE state IN ('queued','retry','leased','running') GROUP BY requested_runtime")
        queues["by_runtime"] = {str(x["requested_runtime"]): int(x["n"]) for x in cur.fetchall()}
    return {"workers": workers, "queues": queues}


def runtime_observability() -> dict[str, Any]:
    try:
        snap = _runtime_snapshot()
    except Exception as exc:
        return {
            "schema": OBSERVABILITY_CONTRACT,
            "version": "5.53.0",
            "backend_version": "2.64.0",
            "state": "unavailable",
            "error_class": exc.__class__.__name__,
            "workers": [],
            "queues": {},
            "guardrails": guardrails(),
        }
    aggregates: dict[str, dict[str, Any]] = {}
    for worker in snap["workers"]:
        cls = str(worker.get("worker_class") or "unknown")
        row = aggregates.setdefault(cls, {"workers": 0, "active": 0, "stale": 0, "available_slots": 0, "recent_completed": 0, "recent_failures": 0})
        row["workers"] += 1
        row["active"] += 1 if worker.get("state") == "active" else 0
        row["stale"] += 1 if worker.get("stale") else 0
        row["available_slots"] += max(0, int(worker.get("concurrency_limit") or 1) - int(worker.get("active_attempts") or 0))
        row["recent_completed"] += int(worker.get("recent_completed") or 0)
        row["recent_failures"] += int(worker.get("recent_failures") or 0)
    return {
        "schema": OBSERVABILITY_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "state": "ready",
        "observed_at": _now(),
        "observation_window_minutes": settings.compute_observation_window_minutes,
        "workers": snap["workers"],
        "worker_class_aggregates": aggregates,
        "queues": snap["queues"],
        "guardrails": guardrails(),
    }


def capture_runtime_observations() -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    obs = runtime_observability()
    if obs.get("state") != "ready":
        return obs
    inserted = 0
    with get_pool().connection() as conn, conn.cursor() as cur:
        for worker in obs["workers"]:
            cur.execute(
                """INSERT INTO library_compute_runtime_observations
                   (worker_id,worker_class,state,active_attempts,concurrency_limit,available_slots,recent_completed,recent_failures,p50_latency_ms,heartbeat_age_seconds,metrics)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                (
                    worker.get("worker_id"), worker.get("worker_class"), worker.get("state"),
                    int(worker.get("active_attempts") or 0), int(worker.get("concurrency_limit") or 1),
                    max(0, int(worker.get("concurrency_limit") or 1) - int(worker.get("active_attempts") or 0)),
                    int(worker.get("recent_completed") or 0), int(worker.get("recent_failures") or 0),
                    worker.get("p50_latency_ms"), worker.get("heartbeat_age_seconds"), Jsonb({"stale": bool(worker.get("stale"))}),
                ),
            )
            inserted += 1
        conn.commit()
    return {
        "schema": RUNTIME_OBSERVATION_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "captured": inserted,
        "observed_at": obs["observed_at"],
        "guardrails": guardrails(),
    }


def _persist_placement(decision: dict[str, Any], job_id: str | None) -> None:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    selected = decision.get("selected") or {}
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_compute_placement_events
               (placement_id,job_id,compute_request_fingerprint,admission_state,reason,selected_worker_class,selected_runtime,decision_fingerprint,candidates,queue_snapshot)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
            (
                decision["placement_id"], job_id, decision.get("compute_request_fingerprint_sha256"),
                decision.get("admission", {}).get("state"), decision.get("admission", {}).get("reason"),
                selected.get("worker_class"), selected.get("runtime"), decision.get("placement_decision_fingerprint_sha256"),
                Jsonb(decision.get("candidates") or []), Jsonb(decision.get("queue_snapshot") or {}),
            ),
        )
        conn.commit()


def submit_compute(payload: dict[str, Any]) -> dict[str, Any]:
    from .durable_job_queue import submit_job
    validation = validate_compute_request(payload)
    if not validation["valid"]:
        return plan_compute(payload, {"workers": [], "queues": {}})
    obs = runtime_observability()
    if obs.get("state") != "ready":
        return {
            "schema": PLACEMENT_DECISION_CONTRACT,
            "version": "5.53.0",
            "backend_version": "2.64.0",
            "admission": {"schema": ADMISSION_CONTRACT, "state": "deferred", "reason": "observability-unavailable"},
            "observability": obs,
            "guardrails": guardrails(),
        }
    decision = plan_compute(payload, obs)
    if decision.get("admission", {}).get("state") != "admitted":
        _persist_placement(decision, None)
        return decision
    n = validation["normalized"]["job"]
    selected = decision.get("selected") or {}
    hints = dict(n.get("resource_hints") or {})
    hints["compute_broker"] = {
        "placement_id": decision["placement_id"],
        "selected_worker_class": selected.get("worker_class"),
        "selected_runtime": selected.get("runtime"),
        "decision_fingerprint_sha256": decision["placement_decision_fingerprint_sha256"],
    }
    job_payload = {
        "job_type": n["job_type"],
        "capability": n["capability"],
        "requested_runtime": selected.get("runtime") or n["requested_runtime"],
        "priority": n["priority"],
        "max_attempts": n["max_attempts"],
        "idempotency_key": n["idempotency_key"],
        "input_manifest": n["input_manifest"],
        "provenance_context": {**dict(n.get("provenance_context") or {}), "compute_placement_id": decision["placement_id"]},
        "resource_hints": hints,
    }
    job = submit_job(job_payload)
    _persist_placement(decision, job.get("job_id"))
    return {
        "schema": PLACEMENT_DECISION_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "placement": decision,
        "job": job,
        "guardrails": guardrails(),
    }


def broker_readiness() -> dict[str, Any]:
    catalog = capability_catalog()
    obs = runtime_observability()
    active_profiles = sum(1 for p in catalog["profiles"] if p["profile_active"])
    standby_profiles = len(catalog["profiles"]) - active_profiles
    state = "ready" if obs.get("state") == "ready" else "degraded"
    return {
        "schema": BROKER_READINESS_CONTRACT,
        "version": "5.53.0",
        "backend_version": "2.64.0",
        "state": state,
        "profiles": {"total": len(catalog["profiles"]), "active": active_profiles, "standby": standby_profiles},
        "queues": obs.get("queues") or {},
        "capabilities": {
            "runtime_capability_discovery": True,
            "deterministic_operational_placement": True,
            "postgresql_durable_job_submission": True,
            "worker_class_affinity": True,
            "queue_backpressure": True,
            "global_running_quota": True,
            "capability_queue_quota": True,
            "worker_freshness": True,
            "concurrency_awareness": True,
            "latency_and_failure_observability": True,
            "historical_runtime_observations": True,
            "placement_event_lineage": True,
            "workspace_profile_discovery": True,
        },
        "limits": {
            "max_queued_jobs": settings.compute_max_queued_jobs,
            "max_running_jobs": settings.compute_max_running_jobs,
            "max_capability_queue": settings.compute_max_capability_queue,
            "observation_window_minutes": settings.compute_observation_window_minutes,
        },
        "observability_state": obs.get("state"),
        "guardrails": guardrails(),
    }
