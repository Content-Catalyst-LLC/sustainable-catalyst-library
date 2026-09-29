from __future__ import annotations

from datetime import datetime, timezone
import hashlib
import json
import math
from typing import Any

from psycopg.types.json import Jsonb

from .db import get_pool
from .settings import settings

EMBEDDING_GOVERNANCE_CONTRACT = "sc-library-embedding-governance/1.0"
EMBEDDING_SPECIFICATION_SCHEMA = "sc-core-compatible-embedding-specification/1.0"
EMBEDDING_REPRESENTATION_SCHEMA = "sc-core-compatible-embedding-representation/1.0"
EMBEDDING_HANDOFF_SCHEMA = "sc-library-workspace-embedding-handoff/1.0"
EMBEDDING_BACKFILL_SCHEMA = "sc-library-embedding-backfill/1.0"
INPUT_PROFILE = "library-record-semantic-v1"
NORMALIZATION = "l2-unit"
SUPPORTED_EXECUTION_TARGETS = {"local", "workspace", "local-fallback"}


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def stable_hash(value: Any) -> str:
    return hashlib.sha256(canonical_json(value).encode("utf-8")).hexdigest()


def _normalize_vector(values: list[float]) -> list[float]:
    if not values:
        raise ValueError("embedding result cannot be empty")
    cleaned = [float(value) for value in values]
    if not all(math.isfinite(value) for value in cleaned):
        raise ValueError("embedding result contains non-finite values")
    norm = math.sqrt(sum(value * value for value in cleaned))
    if not math.isfinite(norm) or norm <= 0:
        raise ValueError("embedding result has zero or non-finite norm")
    return [value / norm for value in cleaned]


def configured_execution_target() -> str:
    mode = str(settings.embedding_compute_target or "local").strip().lower()
    if mode == "local":
        return "local"
    if mode in {"workspace_preferred", "workspace_only"}:
        return "workspace"
    return "local"


def current_embedding_specification(client: Any | None = None) -> dict[str, Any]:
    if client is None:
        from .semantic import default_embedding_client

        client = default_embedding_client()
    basis = {
        "schema": EMBEDDING_SPECIFICATION_SCHEMA,
        "provider": str(client.provider),
        "model": str(client.model or ""),
        "dimensions": int(client.dimensions),
        "input_profile": INPUT_PROFILE,
        "input_max_characters": 12000,
        "input_fields": ["title", "abstract", "topics", "tags", "body_text"],
        "normalization": NORMALIZATION,
        "similarity_metric": "cosine",
    }
    fingerprint = stable_hash(basis)
    return {
        **basis,
        "specification_id": f"embedding-specification:{fingerprint[:32]}",
        "fingerprint_sha256": fingerprint,
        "configured": bool(getattr(client, "configured", False)),
        "governance": {
            "library_owns_operational_vector_index": True,
            "platform_core_owns_governed_representation_contracts": True,
            "workspace_may_execute_compute": True,
            "translation_to_evidence_is_automatic": False,
            "embedding_is_evidence": False,
            "similarity_is_truth": False,
            "similarity_is_causality": False,
            "automatic_platform_core_promotion": False,
        },
    }


def representation_metadata(
    *,
    record_id: str,
    content_hash: str,
    input_hash: str,
    specification: dict[str, Any],
    execution_target: str,
    execution_id: str = "",
) -> dict[str, Any]:
    target = execution_target if execution_target in SUPPORTED_EXECUTION_TARGETS else "local"
    basis = {
        "record_id": record_id,
        "content_hash": content_hash,
        "input_hash": input_hash,
        "specification_fingerprint_sha256": specification["fingerprint_sha256"],
    }
    representation_hash = stable_hash(basis)
    return {
        "schema": EMBEDDING_REPRESENTATION_SCHEMA,
        "representation_id": f"embedding-representation:{representation_hash}",
        "source": {
            "library_record_id": record_id,
            "content_hash": content_hash,
            "input_hash": input_hash,
            "input_profile": specification["input_profile"],
        },
        "specification": {
            "specification_id": specification["specification_id"],
            "fingerprint_sha256": specification["fingerprint_sha256"],
            "provider": specification["provider"],
            "model": specification["model"],
            "dimensions": specification["dimensions"],
            "normalization": specification["normalization"],
            "similarity_metric": specification["similarity_metric"],
        },
        "execution": {
            "target": target,
            "execution_id": execution_id or None,
            "executed_by_workspace": target == "workspace",
        },
        "authority": {
            "source_content": "knowledge-library",
            "operational_vector_index": "knowledge-library",
            "governed_representation_contract": "platform-core",
            "compute_runtime": "workspace" if target == "workspace" else "knowledge-library-backend",
        },
        "guardrails": {
            "embedding_is_evidence": False,
            "embedding_is_truth": False,
            "semantic_similarity_is_causality": False,
            "automatic_core_promotion": False,
        },
    }


def build_workspace_handoff_payload(
    *,
    job_id: int,
    record: dict[str, Any],
    input_text: str,
    specification: dict[str, Any] | None = None,
) -> dict[str, Any]:
    specification = specification or current_embedding_specification()
    input_hash = hashlib.sha256(input_text.encode("utf-8")).hexdigest()
    basis = {
        "job_id": int(job_id),
        "record_id": str(record["record_id"]),
        "content_hash": str(record["content_hash"]),
        "input_hash": input_hash,
        "specification_fingerprint_sha256": specification["fingerprint_sha256"],
    }
    handoff_hash = stable_hash(basis)
    return {
        "schema": EMBEDDING_HANDOFF_SCHEMA,
        "handoff_id": f"embedding-handoff:{handoff_hash}",
        "idempotency_key": handoff_hash,
        "workload": "scientific-embedding-compute",
        "source": {
            "library_job_id": int(job_id),
            "library_record_id": str(record["record_id"]),
            "content_hash": str(record["content_hash"]),
            "input_hash": input_hash,
        },
        "specification": specification,
        "input": {
            "text": input_text,
            "profile": specification["input_profile"],
        },
        "requested_output": {
            "schema": EMBEDDING_REPRESENTATION_SCHEMA,
            "normalized": True,
            "dimensions": specification["dimensions"],
            "return_vector": True,
        },
        "authority": {
            "workspace_role": "compute-executor",
            "library_role": "source-index-and-operational-vector-store",
            "platform_core_role": "governed-representation-contract-and-cross-product-exchange",
        },
        "guardrails": {
            "workspace_execution_changes_source_content": False,
            "embedding_is_evidence": False,
            "embedding_is_truth": False,
            "automatic_platform_core_promotion": False,
        },
    }


def embedding_governance_readiness() -> dict[str, Any]:
    specification = current_embedding_specification()
    result: dict[str, Any] = {
        "schema": EMBEDDING_GOVERNANCE_CONTRACT,
        "specification": specification,
        "compute_policy": {
            "configured_mode": settings.embedding_compute_target,
            "new_job_target": configured_execution_target(),
            "local_worker_enabled": bool(settings.embedding_worker_enabled),
            "workspace_handoff_enabled": settings.embedding_compute_target in {"workspace_preferred", "workspace_only"},
            "workspace_failure_falls_back_to_local": settings.embedding_compute_target == "workspace_preferred",
        },
        "contracts": {
            "specification": EMBEDDING_SPECIFICATION_SCHEMA,
            "representation": EMBEDDING_REPRESENTATION_SCHEMA,
            "workspace_handoff": EMBEDDING_HANDOFF_SCHEMA,
            "backfill": EMBEDDING_BACKFILL_SCHEMA,
        },
        "guardrails": {
            "fake_embeddings": False,
            "embedding_is_evidence": False,
            "semantic_similarity_is_truth": False,
            "semantic_similarity_is_causality": False,
            "automatic_core_promotion": False,
        },
    }
    try:
        pool = get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS count FROM library_record_embeddings")
            result["stored_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                "SELECT count(*) AS count FROM library_record_embeddings WHERE specification_fingerprint=%s",
                (specification["fingerprint_sha256"],),
            )
            result["current_specification_representations"] = int(cur.fetchone()["count"])
            cur.execute(
                """
                SELECT count(*) AS count
                  FROM library_record_embeddings e
                  JOIN library_records r ON r.record_id=e.record_id AND r.content_hash=e.content_hash
                 WHERE e.specification_fingerprint=%s
                """,
                (specification["fingerprint_sha256"],),
            )
            result["current_content_current_specification_representations"] = int(cur.fetchone()["count"])
            cur.execute("SELECT execution_target,status,count(*) AS count FROM library_embedding_jobs GROUP BY execution_target,status")
            result["job_counts"] = {
                f"{row['execution_target']}:{row['status']}": int(row["count"]) for row in cur.fetchall()
            }
            cur.execute("SELECT status,count(*) AS count FROM library_embedding_compute_handoffs GROUP BY status")
            result["workspace_handoff_counts"] = {row["status"]: int(row["count"]) for row in cur.fetchall()}
    except Exception as exc:
        result["state"] = "unavailable"
        result["error"] = exc.__class__.__name__
    else:
        result["state"] = "ready"
    return result


def queue_embedding_backfill(
    *,
    dry_run: bool = True,
    limit: int = 1000,
    execution_target: str | None = None,
) -> dict[str, Any]:
    specification = current_embedding_specification()
    target = (execution_target or configured_execution_target()).strip().lower()
    if target not in {"local", "workspace"}:
        raise ValueError("execution_target must be local or workspace")
    limit = max(1, min(10000, int(limit)))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.record_id,r.content_hash,
                   CASE
                     WHEN e.record_id IS NULL THEN 'missing-representation'
                     WHEN e.content_hash<>r.content_hash THEN 'stale-content'
                     WHEN COALESCE(e.specification_fingerprint,'')<>%s THEN 'stale-specification'
                     ELSE 'current'
                   END AS reason
              FROM library_records r
              LEFT JOIN library_record_embeddings e ON e.record_id=r.record_id
             WHERE r.visibility='public'
               AND r.publication_status='published'
               AND (
                    e.record_id IS NULL OR
                    e.content_hash<>r.content_hash OR
                    COALESCE(e.specification_fingerprint,'')<>%s
               )
             ORDER BY r.updated_at ASC,r.record_id ASC
             LIMIT %s
            """,
            (specification["fingerprint_sha256"], specification["fingerprint_sha256"], limit),
        )
        candidates = [dict(row) for row in cur.fetchall()]
        if not dry_run:
            for row in candidates:
                cur.execute(
                    """
                    INSERT INTO library_embedding_jobs(
                        record_id,content_hash,status,attempt_count,next_attempt_at,
                        specification_fingerprint,execution_target,handoff_id,last_error,updated_at,completed_at
                    ) VALUES (%s,%s,'pending',0,now(),%s,%s,NULL,NULL,now(),NULL)
                    ON CONFLICT (record_id) DO UPDATE SET
                        content_hash=EXCLUDED.content_hash,
                        input_hash=NULL,
                        status='pending',
                        attempt_count=0,
                        next_attempt_at=now(),
                        provider=NULL,
                        model=NULL,
                        dimensions=NULL,
                        specification_fingerprint=EXCLUDED.specification_fingerprint,
                        execution_target=EXCLUDED.execution_target,
                        handoff_id=NULL,
                        provenance='{}'::jsonb,
                        last_error=NULL,
                        updated_at=now(),
                        completed_at=NULL
                    """,
                    (
                        row["record_id"],
                        row["content_hash"],
                        specification["fingerprint_sha256"],
                        target,
                    ),
                )
            conn.commit()
    return {
        "schema": EMBEDDING_BACKFILL_SCHEMA,
        "dry_run": bool(dry_run),
        "queued": 0 if dry_run else len(candidates),
        "candidate_count": len(candidates),
        "execution_target": target,
        "specification_fingerprint_sha256": specification["fingerprint_sha256"],
        "candidates": candidates,
    }


def prepare_workspace_handoffs(limit: int = 25) -> dict[str, Any]:
    from .semantic import embedding_input_from_record

    specification = current_embedding_specification()
    limit = max(1, min(100, int(limit)))
    pool = get_pool()
    prepared: list[dict[str, Any]] = []
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT j.job_id,j.record_id,j.content_hash,j.specification_fingerprint,
                       r.title,r.abstract,r.body_text,r.topics,r.tags,r.content_hash AS current_content_hash
                  FROM library_embedding_jobs j
                  JOIN library_records r ON r.record_id=j.record_id
                 WHERE j.execution_target='workspace'
                   AND j.status IN ('pending','retry')
                   AND j.next_attempt_at<=now()
                 ORDER BY j.created_at ASC
                 FOR UPDATE SKIP LOCKED
                 LIMIT %s
                """,
                (limit,),
            )
            jobs = [dict(row) for row in cur.fetchall()]
            for job in jobs:
                if str(job["current_content_hash"]) != str(job["content_hash"]):
                    cur.execute(
                        """
                        UPDATE library_embedding_jobs
                           SET content_hash=%s,input_hash=NULL,specification_fingerprint=%s,handoff_id=NULL,
                               attempt_count=0,status='pending',next_attempt_at=now(),updated_at=now()
                         WHERE job_id=%s
                        """,
                        (job["current_content_hash"], specification["fingerprint_sha256"], job["job_id"]),
                    )
                    continue
                record = {
                    "record_id": job["record_id"],
                    "content_hash": job["content_hash"],
                    "title": job.get("title"),
                    "abstract": job.get("abstract"),
                    "body_text": job.get("body_text"),
                    "topics": job.get("topics") or [],
                    "tags": job.get("tags") or [],
                }
                input_text = embedding_input_from_record(record)
                payload = build_workspace_handoff_payload(
                    job_id=int(job["job_id"]), record=record, input_text=input_text, specification=specification
                )
                input_hash = payload["source"]["input_hash"]
                handoff_id = payload["handoff_id"]
                cur.execute(
                    """
                    INSERT INTO library_embedding_compute_handoffs(
                        handoff_id,job_id,record_id,content_hash,input_hash,specification_fingerprint,
                        status,payload,updated_at
                    ) VALUES (%s,%s,%s,%s,%s,%s,'queued',%s,now())
                    ON CONFLICT (handoff_id) DO UPDATE SET
                        payload=EXCLUDED.payload,
                        updated_at=now(),
                        status=CASE
                            WHEN library_embedding_compute_handoffs.status='complete' THEN 'complete'
                            WHEN library_embedding_compute_handoffs.status='claimed' THEN 'claimed'
                            ELSE 'queued'
                        END
                    """,
                    (
                        handoff_id,
                        job["job_id"],
                        job["record_id"],
                        job["content_hash"],
                        input_hash,
                        specification["fingerprint_sha256"],
                        Jsonb(payload),
                    ),
                )
                cur.execute(
                    """
                    UPDATE library_embedding_jobs
                       SET input_hash=%s,specification_fingerprint=%s,handoff_id=%s,updated_at=now()
                     WHERE job_id=%s
                    """,
                    (input_hash, specification["fingerprint_sha256"], handoff_id, job["job_id"]),
                )
                prepared.append({"handoff_id": handoff_id, "record_id": job["record_id"], "status": "queued"})
        conn.commit()
    return {"schema": EMBEDDING_HANDOFF_SCHEMA, "prepared": prepared, "count": len(prepared)}


def workspace_handoff_status(limit: int = 100) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT status,count(*) AS count FROM library_embedding_compute_handoffs GROUP BY status ORDER BY status")
        counts = {row["status"]: int(row["count"]) for row in cur.fetchall()}
        cur.execute(
            """
            SELECT handoff_id,job_id,record_id,content_hash,input_hash,specification_fingerprint,status,
                   attempt_count,claimed_by,workspace_execution_id,last_error,created_at,updated_at,completed_at
              FROM library_embedding_compute_handoffs
             ORDER BY updated_at DESC
             LIMIT %s
            """,
            (max(1, min(500, int(limit))),),
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": EMBEDDING_HANDOFF_SCHEMA, "counts": counts, "items": items}


def claim_workspace_handoff(worker_id: str) -> dict[str, Any]:
    worker_id = str(worker_id or "").strip()[:200]
    if not worker_id:
        raise ValueError("worker_id is required")
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT handoff_id,job_id,payload
              FROM library_embedding_compute_handoffs
             WHERE status IN ('queued','retry')
             ORDER BY created_at ASC
             FOR UPDATE SKIP LOCKED
             LIMIT 1
            """
        )
        row = cur.fetchone()
        if not row:
            return {"schema": EMBEDDING_HANDOFF_SCHEMA, "claimed": False, "handoff": None}
        cur.execute(
            """
            UPDATE library_embedding_compute_handoffs
               SET status='claimed',attempt_count=attempt_count+1,claimed_by=%s,claimed_at=now(),updated_at=now(),last_error=NULL
             WHERE handoff_id=%s
            """,
            (worker_id, row["handoff_id"]),
        )
        cur.execute(
            "UPDATE library_embedding_jobs SET status='processing',updated_at=now() WHERE job_id=%s",
            (row["job_id"],),
        )
        conn.commit()
        return {"schema": EMBEDDING_HANDOFF_SCHEMA, "claimed": True, "handoff": row["payload"]}


def complete_workspace_handoff(
    *,
    handoff_id: str,
    values: list[float],
    workspace_execution_id: str = "",
) -> dict[str, Any]:
    vector = _normalize_vector(values)
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT h.*,j.status AS job_status
              FROM library_embedding_compute_handoffs h
              JOIN library_embedding_jobs j ON j.job_id=h.job_id
             WHERE h.handoff_id=%s
             FOR UPDATE
            """,
            (handoff_id,),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("embedding handoff not found")
        if row["status"] == "complete":
            return {"schema": EMBEDDING_HANDOFF_SCHEMA, "handoff_id": handoff_id, "status": "complete", "idempotent": True}
        if row["status"] not in {"queued", "claimed", "retry"}:
            raise ValueError(f"embedding handoff cannot complete from status {row['status']}")
        payload = dict(row["payload"] or {})
        spec = dict(payload.get("specification") or {})
        expected_dimensions = int(spec.get("dimensions") or 0)
        if expected_dimensions <= 0 or len(vector) != expected_dimensions:
            raise ValueError(f"embedding dimensions {len(vector)} do not match specification {expected_dimensions}")
        current_spec = current_embedding_specification()
        if str(row["specification_fingerprint"]) != str(spec.get("fingerprint_sha256")):
            raise ValueError("handoff payload specification fingerprint mismatch")
        # A queued handoff may finish after configuration changes; accept its immutable
        # handoff specification, but record that fingerprint rather than silently rewriting it.
        metadata = representation_metadata(
            record_id=str(row["record_id"]),
            content_hash=str(row["content_hash"]),
            input_hash=str(row["input_hash"]),
            specification=spec,
            execution_target="workspace",
            execution_id=workspace_execution_id,
        )
        now = datetime.now(timezone.utc)
        cur.execute(
            """
            INSERT INTO library_record_embeddings(
                record_id,content_hash,input_hash,provider,model,dimensions,embedding,
                specification_fingerprint,representation_id,execution_target,execution_id,provenance,updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,'workspace',%s,%s,%s)
            ON CONFLICT (record_id) DO UPDATE SET
                content_hash=EXCLUDED.content_hash,input_hash=EXCLUDED.input_hash,
                provider=EXCLUDED.provider,model=EXCLUDED.model,dimensions=EXCLUDED.dimensions,
                embedding=EXCLUDED.embedding,specification_fingerprint=EXCLUDED.specification_fingerprint,
                representation_id=EXCLUDED.representation_id,execution_target=EXCLUDED.execution_target,
                execution_id=EXCLUDED.execution_id,provenance=EXCLUDED.provenance,updated_at=EXCLUDED.updated_at
            """,
            (
                row["record_id"], row["content_hash"], row["input_hash"],
                spec.get("provider") or "", spec.get("model") or "", expected_dimensions, vector,
                spec.get("fingerprint_sha256") or "", metadata["representation_id"],
                workspace_execution_id or None, Jsonb(metadata), now,
            ),
        )
        cur.execute(
            """
            UPDATE library_embedding_jobs
               SET status='complete',provider=%s,model=%s,dimensions=%s,
                   specification_fingerprint=%s,execution_target='workspace',execution_id=%s,
                   provenance=%s,last_error=NULL,updated_at=%s,completed_at=%s
             WHERE job_id=%s
            """,
            (
                spec.get("provider") or "", spec.get("model") or "", expected_dimensions,
                spec.get("fingerprint_sha256") or "", workspace_execution_id or None,
                Jsonb(metadata), now, now, row["job_id"],
            ),
        )
        cur.execute(
            """
            UPDATE library_embedding_compute_handoffs
               SET status='complete',workspace_execution_id=%s,last_error=NULL,updated_at=%s,completed_at=%s
             WHERE handoff_id=%s
            """,
            (workspace_execution_id or None, now, now, handoff_id),
        )
        conn.commit()
    return {
        "schema": EMBEDDING_HANDOFF_SCHEMA,
        "handoff_id": handoff_id,
        "status": "complete",
        "representation": metadata,
        "current_runtime_specification_matches_handoff": current_spec["fingerprint_sha256"] == spec.get("fingerprint_sha256"),
    }


def fail_workspace_handoff(*, handoff_id: str, error: str, retry_after_seconds: int = 30) -> dict[str, Any]:
    message = str(error or "workspace embedding execution failed")[:1000]
    retry_after_seconds = max(0, min(3600, int(retry_after_seconds)))
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            "SELECT handoff_id,job_id,status,attempt_count FROM library_embedding_compute_handoffs WHERE handoff_id=%s FOR UPDATE",
            (handoff_id,),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError("embedding handoff not found")
        if row["status"] == "complete":
            raise ValueError("completed embedding handoff cannot be failed")
        terminal = int(row["attempt_count"] or 0) >= settings.embedding_max_attempts
        if terminal and settings.embedding_compute_target == "workspace_preferred":
            cur.execute(
                """
                UPDATE library_embedding_jobs
                   SET execution_target='local-fallback',status='retry',handoff_id=NULL,last_error=%s,
                       next_attempt_at=now(),updated_at=now()
                 WHERE job_id=%s
                """,
                (message, row["job_id"]),
            )
            handoff_status = "failed"
            job_status = "local-fallback"
        elif terminal:
            cur.execute(
                "UPDATE library_embedding_jobs SET status='failed',last_error=%s,updated_at=now() WHERE job_id=%s",
                (message, row["job_id"]),
            )
            handoff_status = "failed"
            job_status = "failed"
        else:
            cur.execute(
                """
                UPDATE library_embedding_jobs
                   SET status='retry',last_error=%s,next_attempt_at=now()+(%s * interval '1 second'),updated_at=now()
                 WHERE job_id=%s
                """,
                (message, retry_after_seconds, row["job_id"]),
            )
            handoff_status = "retry"
            job_status = "retry"
        cur.execute(
            """
            UPDATE library_embedding_compute_handoffs
               SET status=%s,last_error=%s,updated_at=now(),completed_at=CASE WHEN %s='failed' THEN now() ELSE NULL END
             WHERE handoff_id=%s
            """,
            (handoff_status, message, handoff_status, handoff_id),
        )
        conn.commit()
    return {"schema": EMBEDDING_HANDOFF_SCHEMA, "handoff_id": handoff_id, "status": handoff_status, "job_status": job_status}
