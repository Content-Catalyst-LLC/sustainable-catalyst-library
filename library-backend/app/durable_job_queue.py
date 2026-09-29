from __future__ import annotations

from datetime import datetime, timezone
from hashlib import sha256
import json
import re
from typing import Any

from .settings import settings

JOB_CONTRACT = "sc-library-research-job/1.0"
ATTEMPT_CONTRACT = "sc-library-job-attempt/1.0"
EVENT_CONTRACT = "sc-library-job-event/1.0"
DISPATCH_CONTRACT = "sc-library-job-dispatch/1.0"
READINESS_CONTRACT = "sc-library-execution-fabric-readiness/1.0"
VALIDATION_CONTRACT = "sc-library-research-job-validation/1.0"

JOB_STATES = {"queued","leased","running","retry","complete","failed","cancelled"}
TERMINAL_STATES = {"complete","failed","cancelled"}
RUNTIMES = {"auto","python","go","rust","ocr","htr","speech","neural","workspace"}
SLUG_RE = re.compile(r"^[a-z0-9][a-z0-9._:-]{1,127}$")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00","Z")


def _clean(value: Any) -> str:
    return str(value or "").strip()


def _canonical_json(value: Any) -> str:
    return json.dumps(value, sort_keys=True, separators=(",",":"), ensure_ascii=False, default=str)


def _hash(value: Any) -> str:
    return sha256(_canonical_json(value).encode("utf-8")).hexdigest()


def guardrails() -> dict[str, Any]:
    return {
        "postgresql_is_authoritative_job_state": True,
        "redis_is_authoritative_job_state": False,
        "redis_dispatch_loss_loses_job": False,
        "job_completion_implies_source_validity": False,
        "job_completion_implies_evidence_truth": False,
        "job_completion_implies_model_correctness": False,
        "automatic_evidence_promotion": False,
        "automatic_truth_promotion": False,
        "automatic_platform_core_promotion": False,
        "worker_fleet_activated_by_this_release": False,
        "specialized_worker_activation_target": "5.50.0",
    }


def validate_job_payload(payload: dict[str, Any]) -> dict[str, Any]:
    errors=[]; warnings=[]
    if not isinstance(payload,dict):
        return {"schema":VALIDATION_CONTRACT,"valid":False,"errors":["payload-must-be-object"],"warnings":[],"guardrails":guardrails()}
    job_type=_clean(payload.get("job_type") or payload.get("type")).lower()
    capability=_clean(payload.get("capability") or job_type).lower()
    runtime=_clean(payload.get("requested_runtime") or payload.get("runtime") or "auto").lower()
    if not job_type or not SLUG_RE.match(job_type): errors.append("valid-job-type-required")
    if not capability or not SLUG_RE.match(capability): errors.append("valid-capability-required")
    if runtime not in RUNTIMES: errors.append("invalid-requested-runtime")
    try: priority=max(-100,min(100,int(payload.get("priority",0))))
    except (ValueError,TypeError): errors.append("priority-must-be-integer"); priority=0
    try: max_attempts=int(payload.get("max_attempts",settings.job_default_max_attempts))
    except (ValueError,TypeError): errors.append("max-attempts-must-be-integer"); max_attempts=settings.job_default_max_attempts
    if not 1 <= max_attempts <= 20: errors.append("max-attempts-out-of-range")
    input_manifest=payload.get("input_manifest") if isinstance(payload.get("input_manifest"),dict) else {}
    provenance=payload.get("provenance_context") if isinstance(payload.get("provenance_context"),dict) else {}
    resource=payload.get("resource_hints") if isinstance(payload.get("resource_hints"),dict) else {}
    explicit_idempotency=_clean(payload.get("idempotency_key"))
    if explicit_idempotency and len(explicit_idempotency)>128: errors.append("idempotency-key-too-long")
    if any(bool(payload.get(k,False)) for k in ["automatic_truth_promotion","automatic_evidence_promotion","automatic_platform_core_promotion"]):
        errors.append("automatic-governed-promotion-prohibited")
    basis={"job_type":job_type,"capability":capability,"requested_runtime":runtime,"input_manifest":input_manifest,"provenance_context":provenance,"resource_hints":resource}
    idempotency=explicit_idempotency or "job:"+_hash(basis)[:48]
    normalized={
        "job_type":job_type,"capability":capability,"requested_runtime":runtime,"priority":priority,
        "max_attempts":max_attempts,"idempotency_key":idempotency,"input_manifest":input_manifest,
        "provenance_context":provenance,"resource_hints":resource,
    }
    normalized["job_id"]="research-job:"+_hash({"idempotency_key":idempotency,"basis":basis})[:32]
    normalized["job_fingerprint_sha256"]=_hash(normalized)
    return {"schema":VALIDATION_CONTRACT,"valid":not errors,"errors":errors,"warnings":warnings,"normalized":normalized,"guardrails":guardrails()}


def build_job_package(payload: dict[str, Any]) -> dict[str, Any]:
    v=validate_job_payload(payload)
    if not v["valid"]: raise ValueError("; ".join(v["errors"]))
    n=v["normalized"]
    return {
        "schema":JOB_CONTRACT,**n,"state":"queued","progress":0.0,"attempt_count":0,
        "output_manifest":{},"lease_owner":None,"lease_expires_at":None,"available_at":_now(),
        "created_at":_now(),"persisted":False,"dispatch":{"schema":DISPATCH_CONTRACT,"state":"not-attempted"},
        "guardrails":v["guardrails"],
    }


def _redis_client():
    if not settings.job_redis_url: return None
    from redis import Redis
    return Redis.from_url(settings.job_redis_url, decode_responses=True, socket_connect_timeout=2, socket_timeout=2, health_check_interval=30)


def broker_status() -> dict[str, Any]:
    if not settings.job_redis_url:
        return {"configured":False,"available":False,"state":"not-configured","stream":settings.job_redis_stream,"authoritative":False}
    try:
        client=_redis_client(); pong=bool(client and client.ping())
        return {"configured":True,"available":pong,"state":"online" if pong else "unavailable","stream":settings.job_redis_stream,"authoritative":False}
    except Exception as exc:
        return {"configured":True,"available":False,"state":"unavailable","stream":settings.job_redis_stream,"authoritative":False,"error_class":exc.__class__.__name__}


def dispatch_job(job: dict[str, Any]) -> dict[str, Any]:
    result={"schema":DISPATCH_CONTRACT,"job_id":job.get("job_id"),"stream":settings.job_redis_stream,"authoritative":False,"dispatched":False,"state":"disabled"}
    if not settings.job_dispatch_enabled: return result
    try:
        client=_redis_client()
        if client is None:
            result["state"]="not-configured"; return result
        event_id=client.xadd(settings.job_redis_stream,{
            "job_id":str(job.get("job_id") or ""),"job_type":str(job.get("job_type") or ""),
            "capability":str(job.get("capability") or ""),"requested_runtime":str(job.get("requested_runtime") or "auto"),
            "priority":str(job.get("priority") or 0),"state":"queued","dispatched_at":_now(),
        },maxlen=settings.job_redis_stream_maxlen,approximate=True)
        result.update({"dispatched":True,"state":"published","broker_event_id":str(event_id)})
    except Exception as exc:
        # PostgreSQL remains authoritative. A broker outage defers wake-up; it never deletes the job.
        result.update({"state":"deferred","error_class":exc.__class__.__name__})
    return result


def _event(cur, job_id: str, event_type: str, state: str, progress: float, details: dict[str, Any] | None=None) -> None:
    from psycopg.types.json import Jsonb
    cur.execute("INSERT INTO library_research_job_events(job_id,event_type,state,progress,details) VALUES (%s,%s,%s,%s,%s)",(job_id,event_type,state,progress,Jsonb(details or {})))


def _public_job(row: dict[str, Any] | None, *, include_manifests: bool=True) -> dict[str, Any] | None:
    if not row: return None
    d=dict(row); d["schema"]=JOB_CONTRACT
    for k in ["created_at","updated_at","available_at","lease_expires_at","started_at","completed_at","cancelled_at","last_dispatched_at"]:
        if d.get(k) is not None and hasattr(d[k],"isoformat"): d[k]=d[k].isoformat().replace("+00:00","Z")
    if not include_manifests:
        d.pop("input_manifest",None); d.pop("output_manifest",None); d.pop("provenance_context",None); d.pop("resource_hints",None)
    d["guardrails"]=guardrails(); return d


def submit_job(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    pkg=build_job_package(payload); n=pkg
    pool=get_pool(); created=False
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("""INSERT INTO library_research_jobs(job_id,job_type,capability,requested_runtime,priority,state,progress,idempotency_key,input_manifest,output_manifest,provenance_context,resource_hints,max_attempts,job_fingerprint)
            VALUES (%s,%s,%s,%s,%s,'queued',0,%s,%s,%s,%s,%s,%s,%s) ON CONFLICT (idempotency_key) DO NOTHING RETURNING *""",
            (n["job_id"],n["job_type"],n["capability"],n["requested_runtime"],n["priority"],n["idempotency_key"],Jsonb(n["input_manifest"]),Jsonb({}),Jsonb(n["provenance_context"]),Jsonb(n["resource_hints"]),n["max_attempts"],n["job_fingerprint_sha256"]))
        row=cur.fetchone()
        if row:
            created=True; _event(cur,n["job_id"],"submitted","queued",0.0,{"idempotency_key":n["idempotency_key"]})
        else:
            cur.execute("SELECT * FROM library_research_jobs WHERE idempotency_key=%s",(n["idempotency_key"],)); row=cur.fetchone()
        conn.commit()
    out=_public_job(row); out["created"]=created; out["idempotent_reuse"]=not created
    if created:
        disp=dispatch_job(out)
        out["dispatch"]=disp
        if disp.get("dispatched"):
            with pool.connection() as conn, conn.cursor() as cur:
                cur.execute("UPDATE library_research_jobs SET dispatch_count=dispatch_count+1,last_dispatched_at=now(),updated_at=now() WHERE job_id=%s",(out["job_id"],)); _event(cur,out["job_id"],"dispatched",out["state"],float(out.get("progress") or 0),{"broker_event_id":disp.get("broker_event_id")}); conn.commit()
    else: out["dispatch"]={"schema":DISPATCH_CONTRACT,"state":"not-repeated","dispatched":False,"authoritative":False}
    return out


def get_job(job_id: str) -> dict[str, Any]:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_jobs WHERE job_id=%s",(_clean(job_id),)); row=cur.fetchone()
        if not row: raise KeyError("job-not-found")
        cur.execute("SELECT * FROM library_research_job_attempts WHERE job_id=%s ORDER BY attempt_no",(_clean(job_id),)); attempts=[dict(x) for x in cur.fetchall()]
        cur.execute("SELECT event_id,event_type,state,progress,details,created_at FROM library_research_job_events WHERE job_id=%s ORDER BY event_id DESC LIMIT 100",(_clean(job_id),)); events=[dict(x) for x in cur.fetchall()]
    for collection in (attempts,events):
        for x in collection:
            for k,v in list(x.items()):
                if hasattr(v,"isoformat"): x[k]=v.isoformat().replace("+00:00","Z")
    out=_public_job(row); out["attempts"]=attempts; out["events"]=events; return out


def list_jobs(*, state: str="", capability: str="", limit: int=100) -> dict[str, Any]:
    from .db import get_pool
    limit=max(1,min(500,int(limit))); clauses=[]; params=[]
    if state:
        if state not in JOB_STATES: raise ValueError("invalid-job-state")
        clauses.append("state=%s"); params.append(state)
    if capability: clauses.append("capability=%s"); params.append(capability)
    where=(" WHERE "+" AND ".join(clauses)) if clauses else ""
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute(f"SELECT * FROM library_research_jobs{where} ORDER BY priority DESC,created_at ASC LIMIT %s",(*params,limit)); rows=cur.fetchall()
    return {"schema":"sc-library-research-job-list/1.0","count":len(rows),"jobs":[_public_job(r,include_manifests=False) for r in rows],"guardrails":guardrails()}


def lease_next_job(payload: dict[str, Any]) -> dict[str, Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    worker_id=_clean(payload.get("worker_id"));
    if not worker_id: raise ValueError("worker-id-required")
    capabilities=[_clean(x).lower() for x in (payload.get("capabilities") or []) if _clean(x)]
    runtimes=[_clean(x).lower() for x in (payload.get("runtimes") or []) if _clean(x)]
    lease_seconds=max(30,min(3600,int(payload.get("lease_seconds") or settings.job_lease_seconds)))
    pool=get_pool(); row=None
    with pool.connection() as conn, conn.cursor() as cur:
        clauses=["state IN ('queued','retry')","available_at<=now()"] ; params=[]
        if capabilities: clauses.append("capability = ANY(%s)"); params.append(capabilities)
        if runtimes: clauses.append("(requested_runtime='auto' OR requested_runtime = ANY(%s))"); params.append(runtimes)
        cur.execute(f"SELECT * FROM library_research_jobs WHERE {' AND '.join(clauses)} ORDER BY priority DESC,available_at ASC,created_at ASC FOR UPDATE SKIP LOCKED LIMIT 1",params)
        row=cur.fetchone()
        if not row: conn.commit(); return {"schema":ATTEMPT_CONTRACT,"leased":False,"worker_id":worker_id,"guardrails":guardrails()}
        attempt_no=int(row["attempt_count"])+1; attempt_id=f"{row['job_id']}:attempt:{attempt_no}"
        cur.execute("""UPDATE library_research_jobs SET state='leased',attempt_count=%s,lease_owner=%s,lease_expires_at=now()+(%s * interval '1 second'),updated_at=now(),started_at=COALESCE(started_at,now()) WHERE job_id=%s RETURNING *""",(attempt_no,worker_id,lease_seconds,row["job_id"])); row=cur.fetchone()
        cur.execute("""INSERT INTO library_research_job_attempts(attempt_id,job_id,attempt_no,runtime_id,worker_id,state,lease_expires_at,execution_metadata) VALUES (%s,%s,%s,%s,%s,'leased',now()+(%s * interval '1 second'),%s)""",(attempt_id,row["job_id"],attempt_no,_clean(payload.get("runtime_id") or row["requested_runtime"]),worker_id,lease_seconds,Jsonb(dict(payload.get("execution_metadata") or {}))))
        _event(cur,row["job_id"],"leased","leased",float(row.get("progress") or 0),{"worker_id":worker_id,"attempt_no":attempt_no,"lease_seconds":lease_seconds})
        conn.commit()
    out=_public_job(row); out.update({"leased":True,"attempt_id":attempt_id,"worker_id":worker_id}); return out


def _owned_transition(job_id: str, worker_id: str, *, action: str, progress: float|None=None, output_manifest: dict[str,Any]|None=None, error_class: str|None=None, error_detail: str|None=None, retryable: bool=True, retry_delay_seconds: int=30) -> dict[str,Any]:
    from psycopg.types.json import Jsonb
    from .db import get_pool
    job_id=_clean(job_id); worker_id=_clean(worker_id)
    if not worker_id: raise ValueError("worker-id-required")
    pool=get_pool(); dispatch_after=False
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_jobs WHERE job_id=%s FOR UPDATE",(job_id,)); row=cur.fetchone()
        if not row: raise KeyError("job-not-found")
        if row["state"]=="cancelled": raise ValueError("job-cancelled")
        if row.get("lease_owner")!=worker_id: raise ValueError("worker-does-not-own-lease")
        attempt_no=int(row["attempt_count"]); attempt_id=f"{job_id}:attempt:{attempt_no}"
        if action=="start":
            cur.execute("UPDATE library_research_jobs SET state='running',updated_at=now() WHERE job_id=%s RETURNING *",(job_id,)); row=cur.fetchone(); cur.execute("UPDATE library_research_job_attempts SET state='running',started_at=COALESCE(started_at,now()) WHERE attempt_id=%s",(attempt_id,)); ev="started"
        elif action=="heartbeat":
            p=max(0.0,min(1.0,float(progress if progress is not None else row.get('progress') or 0.0)))
            cur.execute("UPDATE library_research_jobs SET progress=%s,lease_expires_at=now()+(%s * interval '1 second'),updated_at=now() WHERE job_id=%s RETURNING *",(p,settings.job_lease_seconds,job_id)); row=cur.fetchone(); cur.execute("UPDATE library_research_job_attempts SET heartbeat_at=now(),lease_expires_at=now()+(%s * interval '1 second') WHERE attempt_id=%s",(settings.job_lease_seconds,attempt_id)); ev="heartbeat"
        elif action=="complete":
            cur.execute("UPDATE library_research_jobs SET state='complete',progress=1,output_manifest=%s,lease_owner=NULL,lease_expires_at=NULL,completed_at=now(),updated_at=now() WHERE job_id=%s RETURNING *",(Jsonb(output_manifest or {}),job_id)); row=cur.fetchone(); cur.execute("UPDATE library_research_job_attempts SET state='complete',finished_at=now() WHERE attempt_id=%s",(attempt_id,)); ev="completed"
        elif action=="fail":
            retry=bool(retryable and attempt_no < int(row["max_attempts"])); state="retry" if retry else "failed"; delay=max(0,min(86400,int(retry_delay_seconds)))
            cur.execute("""UPDATE library_research_jobs SET state=%s,last_error_class=%s,last_error_detail=%s,lease_owner=NULL,lease_expires_at=NULL,available_at=CASE WHEN %s THEN now()+(%s * interval '1 second') ELSE available_at END,completed_at=CASE WHEN %s THEN NULL ELSE now() END,updated_at=now() WHERE job_id=%s RETURNING *""",(state,_clean(error_class) or None,_clean(error_detail) or None,retry,delay,retry,job_id)); row=cur.fetchone(); cur.execute("UPDATE library_research_job_attempts SET state=%s,failure_class=%s,failure_detail=%s,finished_at=now() WHERE attempt_id=%s",(state,_clean(error_class) or None,_clean(error_detail) or None,attempt_id)); ev="retry-scheduled" if retry else "failed"; dispatch_after=retry
        else: raise ValueError("unsupported-transition")
        _event(cur,job_id,ev,row["state"],float(row.get("progress") or 0),{"worker_id":worker_id,"attempt_no":attempt_no,"error_class":_clean(error_class) or None})
        conn.commit()
    out=_public_job(row)
    if dispatch_after: out["dispatch"]=dispatch_job(out)
    return out


def start_job(job_id: str, worker_id: str) -> dict[str,Any]: return _owned_transition(job_id,worker_id,action="start")
def heartbeat_job(job_id: str, worker_id: str, progress: float|None=None) -> dict[str,Any]: return _owned_transition(job_id,worker_id,action="heartbeat",progress=progress)
def complete_job(job_id: str, worker_id: str, output_manifest: dict[str,Any]|None=None) -> dict[str,Any]: return _owned_transition(job_id,worker_id,action="complete",output_manifest=output_manifest)
def fail_job(job_id: str, worker_id: str, *, error_class: str="", error_detail: str="", retryable: bool=True, retry_delay_seconds: int=30) -> dict[str,Any]: return _owned_transition(job_id,worker_id,action="fail",error_class=error_class,error_detail=error_detail,retryable=retryable,retry_delay_seconds=retry_delay_seconds)


def cancel_job(job_id: str, *, reason: str="") -> dict[str, Any]:
    from .db import get_pool
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_jobs WHERE job_id=%s FOR UPDATE",(_clean(job_id),)); row=cur.fetchone()
        if not row: raise KeyError("job-not-found")
        if row["state"] not in TERMINAL_STATES:
            cur.execute("UPDATE library_research_jobs SET state='cancelled',cancelled_at=now(),updated_at=now(),last_error_detail=%s WHERE job_id=%s RETURNING *",(_clean(reason) or None,row["job_id"])); row=cur.fetchone(); _event(cur,row["job_id"],"cancelled","cancelled",float(row.get("progress") or 0),{"reason":_clean(reason) or None})
        conn.commit()
    return _public_job(row)


def recover_expired_leases(*, limit: int=100) -> dict[str,Any]:
    from .db import get_pool
    limit=max(1,min(1000,int(limit))); recovered=[]
    with get_pool().connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT * FROM library_research_jobs WHERE state IN ('leased','running') AND lease_expires_at IS NOT NULL AND lease_expires_at<now() ORDER BY lease_expires_at ASC FOR UPDATE SKIP LOCKED LIMIT %s",(limit,))
        rows=cur.fetchall()
        for row in rows:
            retry=int(row["attempt_count"]) < int(row["max_attempts"]); state="retry" if retry else "failed"
            cur.execute("UPDATE library_research_jobs SET state=%s,lease_owner=NULL,lease_expires_at=NULL,available_at=CASE WHEN %s THEN now() ELSE available_at END,completed_at=CASE WHEN %s THEN NULL ELSE now() END,last_error_class='LeaseExpired',last_error_detail='worker lease expired',updated_at=now() WHERE job_id=%s RETURNING *",(state,retry,retry,row["job_id"])); updated=cur.fetchone(); cur.execute("UPDATE library_research_job_attempts SET state=%s,failure_class='LeaseExpired',failure_detail='worker lease expired',finished_at=now() WHERE job_id=%s AND attempt_no=%s",(state,row["job_id"],row["attempt_count"])); _event(cur,row["job_id"],"lease-expired",state,float(row.get("progress") or 0),{"previous_worker":row.get("lease_owner")}); recovered.append(_public_job(updated,include_manifests=False))
        conn.commit()
    for row in recovered:
        if row["state"]=="retry": dispatch_job(row)
    return {"schema":"sc-library-job-lease-recovery/1.0","recovered_count":len(recovered),"jobs":recovered,"guardrails":guardrails()}


def execution_fabric_readiness() -> dict[str, Any]:
    counts={s:0 for s in sorted(JOB_STATES)}; db_state="ready"
    try:
        from .db import get_pool
        with get_pool().connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT state,count(*) AS n FROM library_research_jobs GROUP BY state")
            for row in cur.fetchall(): counts[str(row["state"])]=int(row["n"])
    except Exception as exc:
        db_state="schema-unavailable"; db_error=exc.__class__.__name__
    else: db_error=None
    broker=broker_status(); state="ready" if db_state=="ready" and broker.get("available") else ("degraded" if db_state=="ready" else "unavailable")
    return {
        "schema":READINESS_CONTRACT,"version":"5.49.0","backend_version":"2.60.0","state":state,
        "postgresql":{"state":db_state,"authoritative":True,"error_class":db_error},"redis":broker,"counts":counts,
        "capabilities":{"durable_job_state":True,"idempotent_submission":True,"priority_ordering":True,"worker_leases":True,"lease_heartbeats":True,"progress_reporting":True,"retry_state":True,"cancellation":True,"expired_lease_recovery":True,"redis_dispatch_wakeup":True,"postgresql_recovery_without_redis":True,"worker_fleet_active":False},
        "guardrails":guardrails(),"lineage":{"go_ingestion_job_fabric":"v5.36.0 / Go 0.1.0","unified_runtime_contract":"v5.38.0","cross_language_resolution":"v5.48.0"},"next_lineage":{"specialized_worker_runtime_failure_isolation":"v5.50.0"},
    }
