from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from .db import get_pool
from .models import IntegrityAuditRequest, PruneRequest
from .integrity import classify_integrity
from .query import stats


def operations_status() -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT event_id,source_key,received_count,changed_count,duration_ms,created_at
            FROM library_ingest_events
            ORDER BY event_id DESC
            LIMIT 20
            """
        )
        recent_ingest = list(cur.fetchall())
        cur.execute(
            """
            SELECT min(indexed_at) AS oldest_indexed_at,
                   max(indexed_at) AS newest_indexed_at,
                   max(source_updated_at) AS newest_source_updated_at,
                   count(*) FILTER (WHERE revision > 1) AS revised_records,
                   coalesce(sum(revision),0) AS total_revisions
            FROM library_records
            """
        )
        coverage = dict(cur.fetchone())
        coverage["revised_records"] = int(coverage.get("revised_records") or 0)
        coverage["total_revisions"] = int(coverage.get("total_revisions") or 0)
        cur.execute(
            """
            SELECT count(*) AS chunkless_records
            FROM library_records r
            WHERE length(trim(r.body_text)) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM library_record_chunks c WHERE c.record_id=r.record_id
              )
            """
        )
        chunkless_records = int(cur.fetchone()["chunkless_records"])
        cur.execute(
            """
            SELECT count(*) AS version_rows
            FROM library_record_versions
            """
        )
        version_rows = int(cur.fetchone()["version_rows"])

    return {
        "schema": "sc-library-operations-status/1.0",
        "ok": True,
        "stats": stats(),
        "coverage": {
            **coverage,
            "chunkless_records": chunkless_records,
            "version_rows": version_rows,
        },
        "recent_ingest": recent_ingest,
        "checked_at": datetime.now(timezone.utc).isoformat(),
    }


def integrity_audit(payload: IntegrityAuditRequest) -> dict[str, Any]:
    expected = {item.record_id: item.source_updated_at for item in payload.records}
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT r.record_id,r.source_updated_at,r.indexed_at,
                   length(trim(r.body_text)) AS body_length,
                   EXISTS (SELECT 1 FROM library_record_chunks c WHERE c.record_id=r.record_id) AS has_chunks
            FROM library_records r
            WHERE r.source_key=%s
            """,
            (payload.source_key,),
        )
        rows = list(cur.fetchall())

    classified = classify_integrity(expected, rows)
    missing = classified["missing"]
    stale = classified["stale"]
    orphaned = classified["orphaned"]
    chunkless = classified["chunkless"]
    repair_ids = classified["repair"]
    expected_ids = set(expected)
    backend_ids = {row["record_id"] for row in rows}
    healthy = not missing and not orphaned and not stale and not chunkless
    return {
        "schema": "sc-library-integrity-audit/1.0",
        "ok": True,
        "healthy": healthy,
        "source_key": payload.source_key,
        "expected_records": len(expected_ids),
        "backend_records": len(backend_ids),
        "missing_count": len(missing),
        "stale_count": len(stale),
        "orphan_count": len(orphaned),
        "chunkless_count": len(chunkless),
        "repair_count": len(repair_ids),
        "missing_record_ids": missing,
        "stale_record_ids": stale,
        "orphan_record_ids": orphaned,
        "chunkless_record_ids": chunkless,
        "repair_record_ids": repair_ids,
        "checked_at": datetime.now(timezone.utc).isoformat(),
    }


def prune_records(payload: PruneRequest) -> dict[str, Any]:
    pool = get_pool()
    requested = list(payload.record_ids)
    deleted: list[str] = []
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                DELETE FROM library_records
                WHERE source_key=%s AND record_id = ANY(%s)
                RETURNING record_id
                """,
                (payload.source_key, requested),
            )
            deleted = sorted(row["record_id"] for row in cur.fetchall())
        conn.commit()
    return {
        "schema": "sc-library-prune-result/1.0",
        "ok": True,
        "source_key": payload.source_key,
        "requested": len(requested),
        "deleted": len(deleted),
        "not_found": len(set(requested) - set(deleted)),
        "deleted_record_ids": deleted,
    }
