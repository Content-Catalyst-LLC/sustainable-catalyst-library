from __future__ import annotations

from datetime import datetime, timezone
from hashlib import sha256
import json
import re
from typing import Any

PIPELINE_CONTRACT = "sc-library-research-pipeline/1.0"
RUN_CONTRACT = "sc-library-pipeline-run/1.0"
STAGE_RUN_CONTRACT = "sc-library-pipeline-stage-run/1.0"
CHECKPOINT_CONTRACT = "sc-library-pipeline-checkpoint/1.0"
READINESS_CONTRACT = "sc-library-pipeline-engine-readiness/1.0"
VALIDATION_CONTRACT = "sc-library-pipeline-validation/1.0"

STAGE_STATES = {"pending", "queued", "leased", "running", "retry", "complete", "failed", "cancelled", "blocked", "skipped"}
RUN_STATES = {"pending", "running", "retry", "complete", "failed", "cancelled", "blocked"}
SLUG_RE = re.compile(r"^[a-z0-9][a-z0-9._:-]{0,127}$")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _hash(value: Any) -> str:
    return sha256(json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False, default=str).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, Any]:
    return {
        "postgresql_authoritative_pipeline_state": True,
        "pipeline_engine_is_second_job_scheduler": False,
        "stages_compile_to_durable_research_jobs": True,
        "completed_checkpoint_is_reused_on_resume": True,
        "completed_stage_reruns_implicitly": False,
        "checkpoint_output_requires_stage_success": True,
        "artifact_outputs_preserve_v551_identity": True,
        "pipeline_completion_implies_evidence_truth": False,
        "pipeline_completion_implies_source_validity": False,
        "automatic_platform_core_promotion": False,
    }


def _normalize_stage(raw: dict[str, Any], position: int) -> tuple[dict[str, Any], list[str]]:
    errors: list[str] = []
    stage_id = _clean(raw.get("stage_id") or f"stage-{position+1}").lower()
    capability = _clean(raw.get("capability")).lower()
    runtime = _clean(raw.get("requested_runtime") or "auto").lower()
    if not SLUG_RE.fullmatch(stage_id):
        errors.append(f"invalid-stage-id:{stage_id or position}")
    if not capability or not SLUG_RE.fullmatch(capability):
        errors.append(f"invalid-stage-capability:{stage_id or position}")
    depends: list[str] = []
    for item in raw.get("depends_on") or []:
        dep = _clean(item).lower()
        if dep and dep not in depends:
            depends.append(dep)
    if stage_id in depends:
        errors.append(f"stage-self-dependency:{stage_id}")
    stage = {
        "stage_id": stage_id,
        "name": _clean(raw.get("name")) or stage_id,
        "capability": capability,
        "requested_runtime": runtime,
        "depends_on": depends,
        "optional": bool(raw.get("optional", False)),
        "max_attempts": max(1, min(20, int(raw.get("max_attempts") or 5))),
        "input_selector": raw.get("input_selector") if isinstance(raw.get("input_selector"), dict) else {},
        "output_contract": raw.get("output_contract") if isinstance(raw.get("output_contract"), dict) else {},
        "metadata": raw.get("metadata") if isinstance(raw.get("metadata"), dict) else {},
    }
    stage["stage_fingerprint_sha256"] = _hash({k: v for k, v in stage.items() if k != "stage_fingerprint_sha256"})
    return stage, errors


def _topological_order(stages: list[dict[str, Any]]) -> tuple[list[str], list[str]]:
    ids = {s["stage_id"] for s in stages}
    errors: list[str] = []
    for s in stages:
        for dep in s["depends_on"]:
            if dep not in ids:
                errors.append(f"unknown-dependency:{s['stage_id']}:{dep}")
    if errors:
        return [], errors
    indegree = {s["stage_id"]: 0 for s in stages}
    children: dict[str, list[str]] = {s["stage_id"]: [] for s in stages}
    for s in stages:
        for dep in s["depends_on"]:
            indegree[s["stage_id"]] += 1
            children[dep].append(s["stage_id"])
    ready = sorted([k for k, v in indegree.items() if v == 0])
    order: list[str] = []
    while ready:
        current = ready.pop(0)
        order.append(current)
        for child in sorted(children[current]):
            indegree[child] -= 1
            if indegree[child] == 0:
                ready.append(child)
                ready.sort()
    if len(order) != len(stages):
        errors.append("pipeline-cycle-detected")
    return order, errors


def validate_pipeline(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    name = _clean(payload.get("name") or "research-pipeline")
    version = _clean(payload.get("pipeline_version") or "1")
    raw_stages = payload.get("stages")
    if not isinstance(raw_stages, list) or not raw_stages:
        errors.append("stages-required")
        raw_stages = []
    stages: list[dict[str, Any]] = []
    for i, raw in enumerate(raw_stages):
        if not isinstance(raw, dict):
            errors.append(f"invalid-stage:{i}")
            continue
        stage, stage_errors = _normalize_stage(raw, i)
        stages.append(stage)
        errors.extend(stage_errors)
    ids = [s["stage_id"] for s in stages]
    if len(ids) != len(set(ids)):
        errors.append("duplicate-stage-id")
    order, dag_errors = _topological_order(stages)
    errors.extend(dag_errors)
    normalized = {
        "name": name,
        "pipeline_version": version,
        "description": _clean(payload.get("description")) or None,
        "stages": stages,
        "topological_order": order,
        "metadata": payload.get("metadata") if isinstance(payload.get("metadata"), dict) else {},
    }
    fingerprint = _hash(normalized)
    normalized["pipeline_fingerprint_sha256"] = fingerprint
    normalized["pipeline_id"] = _clean(payload.get("pipeline_id")) or f"pipeline:{fingerprint[:32]}"
    return {
        "schema": VALIDATION_CONTRACT,
        "version": "5.52.0",
        "backend_version": "2.63.0",
        "valid": not errors,
        "errors": errors,
        "normalized": normalized,
        "guardrails": guardrails(),
    }


def build_pipeline_package(payload: dict[str, Any]) -> dict[str, Any]:
    validation = validate_pipeline(payload)
    if not validation["valid"]:
        raise ValueError("; ".join(validation["errors"]))
    return {
        "schema": PIPELINE_CONTRACT,
        "version": "5.52.0",
        "backend_version": "2.63.0",
        "pipeline": validation["normalized"],
        "guardrails": guardrails(),
    }


def build_run_plan(pipeline: dict[str, Any], input_manifest: dict[str, Any] | None = None, *, idempotency_key: str = "") -> dict[str, Any]:
    package = build_pipeline_package(pipeline)
    p = package["pipeline"]
    inputs = input_manifest if isinstance(input_manifest, dict) else {}
    idem = _clean(idempotency_key) or _hash({"pipeline_id": p["pipeline_id"], "inputs": inputs})[:40]
    run_fingerprint = _hash({"pipeline_fingerprint": p["pipeline_fingerprint_sha256"], "idempotency_key": idem, "inputs": inputs})
    run_id = f"pipeline-run:{run_fingerprint[:32]}"
    stages = []
    for stage in p["stages"]:
        stage_key = _hash({"run_id": run_id, "stage": stage["stage_fingerprint_sha256"], "depends_on": stage["depends_on"]})
        stages.append({
            "stage_run_id": f"pipeline-stage:{stage_key[:32]}",
            "run_id": run_id,
            "stage_id": stage["stage_id"],
            "capability": stage["capability"],
            "requested_runtime": stage["requested_runtime"],
            "depends_on": stage["depends_on"],
            "state": "pending",
            "max_attempts": stage["max_attempts"],
            "optional": stage["optional"],
            "stage_fingerprint_sha256": stage["stage_fingerprint_sha256"],
        })
    initial_ready = [x["stage_id"] for x in stages if not x["depends_on"]]
    return {
        "schema": RUN_CONTRACT,
        "version": "5.52.0",
        "backend_version": "2.63.0",
        "run_id": run_id,
        "pipeline_id": p["pipeline_id"],
        "pipeline_fingerprint_sha256": p["pipeline_fingerprint_sha256"],
        "idempotency_key": idem,
        "input_manifest": inputs,
        "state": "pending",
        "stages": stages,
        "initial_ready_stage_ids": initial_ready,
        "run_fingerprint_sha256": run_fingerprint,
        "guardrails": guardrails(),
    }


def stage_job_payload(run: dict[str, Any], stage: dict[str, Any], *, resolved_inputs: dict[str, Any] | None = None) -> dict[str, Any]:
    resolved = resolved_inputs if isinstance(resolved_inputs, dict) else {}
    idem = _hash({"run_id": run["run_id"], "stage_id": stage["stage_id"], "inputs": resolved, "stage_fingerprint": stage["stage_fingerprint_sha256"]})[:64]
    return {
        "job_type": "pipeline-stage",
        "capability": stage["capability"],
        "requested_runtime": stage["requested_runtime"],
        "priority": 0,
        "max_attempts": stage["max_attempts"],
        "idempotency_key": f"pipeline:{idem}",
        "input_manifest": resolved,
        "provenance_context": {
            "pipeline_run_id": run["run_id"],
            "pipeline_id": run["pipeline_id"],
            "stage_id": stage["stage_id"],
            "stage_run_id": stage["stage_run_id"],
            "pipeline_fingerprint_sha256": run["pipeline_fingerprint_sha256"],
        },
    }


def completed_dependencies(stage: dict[str, Any], stage_states: dict[str, str]) -> bool:
    return all(stage_states.get(dep) in {"complete", "skipped"} for dep in stage.get("depends_on") or [])


def resumable_stage_ids(run_plan: dict[str, Any], stage_states: dict[str, str]) -> list[str]:
    ready: list[str] = []
    for stage in run_plan.get("stages") or []:
        state = stage_states.get(stage["stage_id"], stage.get("state", "pending"))
        if state == "complete":
            continue
        if state in {"pending", "retry", "failed", "blocked"} and completed_dependencies(stage, stage_states):
            ready.append(stage["stage_id"])
    return ready


def checkpoint_manifest(*, run_id: str, stage_id: str, job_id: str, output_manifest: dict[str, Any] | None = None, artifact_ids: list[str] | None = None) -> dict[str, Any]:
    outputs = output_manifest if isinstance(output_manifest, dict) else {}
    arts: list[str] = []
    for value in artifact_ids or []:
        value = _clean(value)
        if value and value not in arts:
            if not value.startswith("artifact:sha256:"):
                raise ValueError("invalid-artifact-id")
            arts.append(value)
    core = {"run_id": _clean(run_id), "stage_id": _clean(stage_id), "job_id": _clean(job_id), "output_manifest": outputs, "artifact_ids": arts}
    return {
        "schema": CHECKPOINT_CONTRACT,
        "version": "5.52.0",
        "backend_version": "2.63.0",
        **core,
        "checkpoint_fingerprint_sha256": _hash(core),
        "created_at": _now(),
        "guardrails": guardrails(),
    }


def persist_pipeline(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    package = build_pipeline_package(payload)
    p = package["pipeline"]
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_research_pipeline_definitions
               (pipeline_id,name,pipeline_version,description,definition,topological_order,pipeline_fingerprint,metadata)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
               ON CONFLICT (pipeline_id) DO UPDATE SET
                 name=EXCLUDED.name,pipeline_version=EXCLUDED.pipeline_version,description=EXCLUDED.description,
                 definition=EXCLUDED.definition,topological_order=EXCLUDED.topological_order,
                 pipeline_fingerprint=EXCLUDED.pipeline_fingerprint,metadata=EXCLUDED.metadata,updated_at=now()
               RETURNING *""",
            (p["pipeline_id"],p["name"],p["pipeline_version"],p["description"],Jsonb(p),p["topological_order"],p["pipeline_fingerprint_sha256"],Jsonb(p["metadata"])),
        )
        row = cur.fetchone(); conn.commit()
    return {"schema": PIPELINE_CONTRACT, "pipeline": dict(row), "guardrails": guardrails()}


def start_pipeline_run(pipeline_id: str, payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    from .durable_job_queue import submit_job
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT definition FROM library_research_pipeline_definitions WHERE pipeline_id=%s", (_clean(pipeline_id),))
        row = cur.fetchone()
    if not row:
        raise KeyError("pipeline-not-found")
    plan = build_run_plan(dict(row["definition"]), payload.get("input_manifest") if isinstance(payload, dict) else {}, idempotency_key=_clean(payload.get("idempotency_key") if isinstance(payload, dict) else ""))
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO library_research_pipeline_runs
               (run_id,pipeline_id,state,idempotency_key,input_manifest,run_fingerprint)
               VALUES (%s,%s,'running',%s,%s,%s)
               ON CONFLICT(idempotency_key) DO UPDATE SET updated_at=now()
               RETURNING *""",
            (plan["run_id"],plan["pipeline_id"],plan["idempotency_key"],Jsonb(plan["input_manifest"]),plan["run_fingerprint_sha256"]),
        )
        runrow = cur.fetchone()
        for stage in plan["stages"]:
            cur.execute(
                """INSERT INTO library_research_pipeline_stage_runs
                   (stage_run_id,run_id,stage_id,capability,requested_runtime,depends_on,state,max_attempts,optional,stage_fingerprint)
                   VALUES (%s,%s,%s,%s,%s,%s,'pending',%s,%s,%s)
                   ON CONFLICT(stage_run_id) DO NOTHING""",
                (stage["stage_run_id"],stage["run_id"],stage["stage_id"],stage["capability"],stage["requested_runtime"],stage["depends_on"],stage["max_attempts"],stage["optional"],stage["stage_fingerprint_sha256"]),
            )
        conn.commit()
    # Queue only root stages. Job submission is idempotent.
    queued = []
    stage_map = {s["stage_id"]: s for s in plan["stages"]}
    for sid in plan["initial_ready_stage_ids"]:
        stage = stage_map[sid]
        job = submit_job(stage_job_payload(plan, stage, resolved_inputs=plan["input_manifest"]))
        _link_stage_job(stage["stage_run_id"], job["job_id"], "queued")
        queued.append(job["job_id"])
    return {"schema": RUN_CONTRACT, "run": dict(runrow), "queued_job_ids": queued, "guardrails": guardrails()}


def _link_stage_job(stage_run_id: str, job_id: str, state: str) -> None:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("UPDATE library_research_pipeline_stage_runs SET job_id=%s,state=%s,updated_at=now() WHERE stage_run_id=%s", (job_id,state,stage_run_id)); conn.commit()


def _stage_rows(run_id: str) -> list[dict[str, Any]]:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_pipeline_stage_runs WHERE run_id=%s ORDER BY created_at,stage_id", (_clean(run_id),)); rows=cur.fetchall()
    return [dict(x) for x in rows]


def get_pipeline_run(run_id: str) -> dict[str, Any]:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_pipeline_runs WHERE run_id=%s", (_clean(run_id),)); row=cur.fetchone()
    if not row: raise KeyError("pipeline-run-not-found")
    return {"schema": RUN_CONTRACT, "run": dict(row), "stages": _stage_rows(run_id), "guardrails": guardrails()}


def record_job_completion(job_id: str, output_manifest: dict[str, Any] | None = None) -> dict[str, Any] | None:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    from .durable_job_queue import submit_job
    output = output_manifest if isinstance(output_manifest, dict) else {}
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_pipeline_stage_runs WHERE job_id=%s", (_clean(job_id),)); stage=cur.fetchone()
        if not stage: return None
        stage=dict(stage)
        arts=[]
        for v in output.get("artifact_ids") or []:
            if isinstance(v,str) and v.startswith("artifact:sha256:") and v not in arts: arts.append(v)
        if isinstance(output.get("artifact"),dict):
            aid=_clean(output["artifact"].get("artifact_id"))
            if aid.startswith("artifact:sha256:") and aid not in arts: arts.append(aid)
        checkpoint=checkpoint_manifest(run_id=stage["run_id"],stage_id=stage["stage_id"],job_id=job_id,output_manifest=output,artifact_ids=arts)
        cur.execute("""UPDATE library_research_pipeline_stage_runs SET state='complete',checkpoint=%s,checkpoint_fingerprint=%s,output_artifact_ids=%s,completed_at=now(),updated_at=now() WHERE stage_run_id=%s""",(Jsonb(checkpoint),checkpoint["checkpoint_fingerprint_sha256"],arts,stage["stage_run_id"]))
        cur.execute("INSERT INTO library_research_pipeline_events(run_id,stage_run_id,event_type,details) VALUES (%s,%s,'checkpoint-complete',%s)",(stage["run_id"],stage["stage_run_id"],Jsonb({"job_id":job_id,"checkpoint_fingerprint_sha256":checkpoint["checkpoint_fingerprint_sha256"]})))
        conn.commit()
    rows=_stage_rows(stage["run_id"]); states={x["stage_id"]:x["state"] for x in rows}; queued=[]
    # Load definition to build deterministic child jobs.
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT r.*,d.definition FROM library_research_pipeline_runs r JOIN library_research_pipeline_definitions d ON d.pipeline_id=r.pipeline_id WHERE r.run_id=%s",(stage["run_id"],)); runrow=dict(cur.fetchone())
    plan=build_run_plan(dict(runrow["definition"]),dict(runrow.get("input_manifest") or {}),idempotency_key=str(runrow["idempotency_key"]))
    plan_map={x["stage_id"]:x for x in plan["stages"]}
    for row in rows:
        if row["state"] not in {"pending","retry","blocked"}: continue
        plan_stage=plan_map[row["stage_id"]]
        if completed_dependencies(plan_stage,states):
            parents=[x for x in rows if x["stage_id"] in plan_stage["depends_on"]]
            resolved={"pipeline_input":dict(runrow.get("input_manifest") or {}),"dependency_checkpoints":{x["stage_id"]:x.get("checkpoint") for x in parents}}
            job=submit_job(stage_job_payload(plan,plan_stage,resolved_inputs=resolved)); _link_stage_job(row["stage_run_id"],job["job_id"],"queued"); queued.append(job["job_id"])
    rows=_stage_rows(stage["run_id"])
    if all(x["state"] in {"complete","skipped"} for x in rows):
        with get_pool().connection() as conn, conn.cursor() as cur:
            cur.execute("UPDATE library_research_pipeline_runs SET state='complete',completed_at=now(),updated_at=now() WHERE run_id=%s",(stage["run_id"],)); conn.commit()
    return {"schema": CHECKPOINT_CONTRACT, "checkpoint": checkpoint, "queued_job_ids": queued, "guardrails": guardrails()}


def record_job_failure(job_id: str, *, failure_class: str = "", failure_detail: str = "") -> dict[str, Any] | None:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_pipeline_stage_runs WHERE job_id=%s", (_clean(job_id),)); row=cur.fetchone()
        if not row: return None
        row=dict(row)
        cur.execute("UPDATE library_research_pipeline_stage_runs SET state='failed',failure_class=%s,failure_detail=%s,updated_at=now() WHERE stage_run_id=%s",(_clean(failure_class),_clean(failure_detail),row["stage_run_id"]))
        cur.execute("UPDATE library_research_pipeline_runs SET state='retry',updated_at=now() WHERE run_id=%s",(row["run_id"],))
        cur.execute("INSERT INTO library_research_pipeline_events(run_id,stage_run_id,event_type,details) VALUES (%s,%s,'stage-failed',%s)",(row["run_id"],row["stage_run_id"],Jsonb({"job_id":job_id,"failure_class":failure_class,"failure_detail":failure_detail})))
        conn.commit()
    return {"schema": STAGE_RUN_CONTRACT, "run_id": row["run_id"], "stage_id": row["stage_id"], "state": "failed", "guardrails": guardrails()}


def resume_pipeline_run(run_id: str) -> dict[str, Any]:
    from .db import get_pool
    from .durable_job_queue import submit_job
    current=get_pipeline_run(run_id); run=current["run"]; rows=current["stages"]
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT definition FROM library_research_pipeline_definitions WHERE pipeline_id=%s",(run["pipeline_id"],)); definition=dict(cur.fetchone()["definition"])
    plan=build_run_plan(definition,dict(run.get("input_manifest") or {}),idempotency_key=str(run["idempotency_key"])); states={x["stage_id"]:x["state"] for x in rows}; ready=resumable_stage_ids(plan,states); queued=[]; plan_map={x["stage_id"]:x for x in plan["stages"]}; row_map={x["stage_id"]:x for x in rows}
    for sid in ready:
        row=row_map[sid]; stage=plan_map[sid]
        parents=[x for x in rows if x["stage_id"] in stage["depends_on"]]
        resolved={"pipeline_input":dict(run.get("input_manifest") or {}),"dependency_checkpoints":{x["stage_id"]:x.get("checkpoint") for x in parents}}
        job=submit_job(stage_job_payload(plan,stage,resolved_inputs=resolved)); _link_stage_job(row["stage_run_id"],job["job_id"],"queued"); queued.append(job["job_id"])
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("UPDATE library_research_pipeline_runs SET state='running',resume_count=resume_count+1,updated_at=now() WHERE run_id=%s",(_clean(run_id),)); conn.commit()
    return {"schema": RUN_CONTRACT,"run_id":run_id,"resumed":True,"queued_stage_ids":ready,"queued_job_ids":queued,"reused_completed_checkpoints":[x["stage_id"] for x in rows if x["state"]=="complete"],"guardrails":guardrails()}


def pipeline_readiness() -> dict[str, Any]:
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            counts={}
            for table,key in [("library_research_pipeline_definitions","pipelines"),("library_research_pipeline_runs","runs"),("library_research_pipeline_stage_runs","stage_runs")]:
                cur.execute(f"SELECT count(*) AS n FROM {table}"); counts[key]=int(cur.fetchone()["n"])
    except Exception as exc:
        return {"schema":READINESS_CONTRACT,"version":"5.52.0","backend_version":"2.63.0","state":"unavailable","error_class":exc.__class__.__name__,"guardrails":guardrails()}
    return {
        "schema": READINESS_CONTRACT,
        "version": "5.52.0",
        "backend_version": "2.63.0",
        "state": "ready",
        "counts": counts,
        "capabilities": {
            "durable_pipeline_definitions": True,
            "dag_dependency_validation": True,
            "stage_compiles_to_durable_job": True,
            "stage_checkpoints": True,
            "resume_after_failure": True,
            "completed_checkpoint_reuse": True,
            "idempotent_stage_jobs": True,
            "artifact_backed_outputs": True,
            "pipeline_event_lineage": True,
        },
        "guardrails": guardrails(),
    }
