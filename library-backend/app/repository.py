from __future__ import annotations

import hashlib
import json
import time
from typing import Any

from psycopg.types.json import Jsonb

from .db import get_pool
from .models import EdgeBatch, RecordBatch, RecordPacket


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True, default=str)


def record_hash(record: RecordPacket) -> str:
    material = record.model_dump(mode="json", exclude={"chunks"})
    material["chunks"] = [chunk.model_dump(mode="json") for chunk in record.chunks]
    return hashlib.sha256(canonical_json(material).encode("utf-8")).hexdigest()


def _snapshot(record: RecordPacket, content_hash: str, revision: int) -> dict[str, Any]:
    data = record.model_dump(mode="json")
    data.update({"content_hash": content_hash, "revision": revision})
    return data


def ingest_records(batch: RecordBatch, request_hash: str) -> dict[str, Any]:
    started = time.perf_counter()
    changed = 0
    unchanged = 0
    ids: list[str] = []
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO library_sources(source_key,name,source_type,canonical_url,metadata,updated_at)
                VALUES (%s,%s,%s,%s,%s,now())
                ON CONFLICT (source_key) DO UPDATE SET
                    name=EXCLUDED.name,
                    source_type=EXCLUDED.source_type,
                    canonical_url=EXCLUDED.canonical_url,
                    metadata=EXCLUDED.metadata,
                    updated_at=now()
                """,
                (batch.source.source_key, batch.source.name, batch.source.source_type, batch.source.canonical_url, Jsonb(batch.source.metadata)),
            )

            for record in batch.records:
                ids.append(record.record_id)
                digest = record_hash(record)
                cur.execute("SELECT content_hash, revision FROM library_records WHERE record_id=%s FOR UPDATE", (record.record_id,))
                existing = cur.fetchone()
                if existing and existing["content_hash"] == digest:
                    unchanged += 1
                    continue
                revision = (int(existing["revision"]) + 1) if existing else 1
                cur.execute(
                    """
                    INSERT INTO library_records(
                        record_id,source_key,object_type,title,canonical_url,abstract,body_text,language,
                        visibility,publication_status,published_at,source_updated_at,authors,topics,tags,
                        identifiers,metadata,content_hash,revision,indexed_at
                    ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,now())
                    ON CONFLICT (record_id) DO UPDATE SET
                        source_key=EXCLUDED.source_key,
                        object_type=EXCLUDED.object_type,
                        title=EXCLUDED.title,
                        canonical_url=EXCLUDED.canonical_url,
                        abstract=EXCLUDED.abstract,
                        body_text=EXCLUDED.body_text,
                        language=EXCLUDED.language,
                        visibility=EXCLUDED.visibility,
                        publication_status=EXCLUDED.publication_status,
                        published_at=EXCLUDED.published_at,
                        source_updated_at=EXCLUDED.source_updated_at,
                        authors=EXCLUDED.authors,
                        topics=EXCLUDED.topics,
                        tags=EXCLUDED.tags,
                        identifiers=EXCLUDED.identifiers,
                        metadata=EXCLUDED.metadata,
                        content_hash=EXCLUDED.content_hash,
                        revision=EXCLUDED.revision,
                        indexed_at=now()
                    """,
                    (
                        record.record_id, record.source_key, record.object_type, record.title, record.canonical_url,
                        record.abstract, record.body_text, record.language, record.visibility, record.publication_status,
                        record.published_at, record.source_updated_at, Jsonb(record.authors), Jsonb(record.topics),
                        Jsonb(record.tags), Jsonb(record.identifiers), Jsonb(record.metadata), digest, revision,
                    ),
                )
                cur.execute("DELETE FROM library_record_chunks WHERE record_id=%s", (record.record_id,))
                if record.chunks:
                    cur.executemany(
                        """
                        INSERT INTO library_record_chunks(record_id,ordinal,heading,text,token_count,metadata)
                        VALUES (%s,%s,%s,%s,%s,%s)
                        """,
                        [
                            (record.record_id, chunk.ordinal, chunk.heading, chunk.text, chunk.token_count, Jsonb(chunk.metadata))
                            for chunk in record.chunks
                        ],
                    )
                cur.execute(
                    """
                    INSERT INTO library_record_versions(record_id,revision,content_hash,snapshot)
                    VALUES (%s,%s,%s,%s)
                    ON CONFLICT (record_id,revision) DO NOTHING
                    """,
                    (record.record_id, revision, digest, Jsonb(_snapshot(record, digest, revision))),
                )
                changed += 1

            duration_ms = int((time.perf_counter() - started) * 1000)
            cur.execute(
                """INSERT INTO library_ingest_events(source_key,received_count,changed_count,request_hash,duration_ms)
                   VALUES (%s,%s,%s,%s,%s)""",
                (batch.source.source_key, len(batch.records), changed, request_hash, duration_ms),
            )
        conn.commit()
    return {
        "ok": True,
        "schema": "sc-library-backend-ingest-result/1.0",
        "received": len(batch.records),
        "changed": changed,
        "unchanged": unchanged,
        "record_ids": ids,
        "duration_ms": int((time.perf_counter() - started) * 1000),
    }


def ingest_edges(batch: EdgeBatch) -> dict[str, Any]:
    upserted = 0
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            for edge in batch.edges:
                cur.execute(
                    """
                    INSERT INTO library_edges(source_record_id,target_record_id,relation,weight,directed,provenance,updated_at)
                    VALUES (%s,%s,%s,%s,%s,%s,now())
                    ON CONFLICT (source_record_id,target_record_id,relation) DO UPDATE SET
                        weight=EXCLUDED.weight,
                        directed=EXCLUDED.directed,
                        provenance=EXCLUDED.provenance,
                        updated_at=now()
                    """,
                    (edge.source_record_id, edge.target_record_id, edge.relation, edge.weight, edge.directed, Jsonb(edge.provenance)),
                )
                upserted += 1
        conn.commit()
    return {"ok": True, "schema": "sc-library-backend-edge-result/1.0", "upserted": upserted}


def delete_record(record_id: str) -> bool:
    pool = get_pool()
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute("DELETE FROM library_records WHERE record_id=%s RETURNING record_id", (record_id,))
            deleted = cur.fetchone() is not None
        conn.commit()
    return deleted
