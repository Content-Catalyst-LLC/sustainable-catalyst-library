from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
import hashlib
import math
from typing import Any

import httpx
from psycopg.types.json import Jsonb

from .db import get_pool
from .settings import settings

SEMANTIC_CONTRACT = "sc-library-semantic-index/1.0"
EMBEDDING_JOB_CONTRACT = "sc-library-embedding-jobs/1.0"
SUPPORTED_PROVIDERS = {"disabled", "gemini", "openai_compatible"}


class EmbeddingError(RuntimeError):
    pass


def _clean_text(value: str, limit: int = 12000) -> str:
    return " ".join(str(value or "").split()).strip()[:limit]


def embedding_input_from_record(record: dict[str, Any]) -> str:
    """Build a bounded Library-local semantic representation of one record."""
    parts: list[str] = []
    title = _clean_text(str(record.get("title") or ""), 1200)
    abstract = _clean_text(str(record.get("abstract") or ""), 5000)
    body = _clean_text(str(record.get("body_text") or ""), 5000)
    topics = record.get("topics") or []
    tags = record.get("tags") or []
    if title:
        parts.append(f"Title: {title}")
    if abstract:
        parts.append(f"Abstract: {abstract}")
    if topics:
        parts.append("Topics: " + "; ".join(_clean_text(str(v), 300) for v in topics[:40] if str(v).strip()))
    if tags:
        parts.append("Tags: " + "; ".join(_clean_text(str(v), 300) for v in tags[:60] if str(v).strip()))
    if body:
        parts.append(f"Text: {body}")
    return "\n".join(part for part in parts if part).strip()[:12000]


def vector_norm(values: list[float]) -> float:
    return math.sqrt(sum(value * value for value in values))


def normalize_vector(values: list[float]) -> list[float]:
    if not values:
        raise EmbeddingError("embedding provider returned an empty vector")
    cleaned = [float(value) for value in values]
    norm = vector_norm(cleaned)
    if not math.isfinite(norm) or norm <= 0:
        raise EmbeddingError("embedding provider returned a zero or non-finite vector")
    normalized = [value / norm for value in cleaned]
    if not all(math.isfinite(value) for value in normalized):
        raise EmbeddingError("embedding provider returned non-finite values")
    return normalized


@dataclass(frozen=True)
class EmbeddingResult:
    provider: str
    model: str
    values: list[float]

    @property
    def dimensions(self) -> int:
        return len(self.values)


class EmbeddingClient:
    """Small dependency-light embeddings client.

    Gemini is first-class because Sustainable Catalyst already uses Gemini in its
    research stack. An OpenAI-compatible endpoint is also supported so the
    Library is not tied to one vendor. The client never falls back to a fake or
    hash embedding: when no real provider is configured, hybrid retrieval
    explicitly degrades to lexical retrieval.
    """

    def __init__(
        self,
        *,
        provider: str,
        api_key: str = "",
        model: str = "",
        api_url: str = "",
        dimensions: int = 768,
        timeout_seconds: int = 12,
        transport: httpx.BaseTransport | None = None,
    ) -> None:
        provider = provider.strip().lower()
        if provider not in SUPPORTED_PROVIDERS:
            raise ValueError(f"unsupported embedding provider: {provider}")
        self.provider = provider
        self.api_key = api_key.strip()
        self.model = model.strip()
        self.api_url = api_url.strip().rstrip("/")
        self.dimensions = max(64, min(3072, int(dimensions)))
        self.timeout_seconds = max(2, min(60, int(timeout_seconds)))
        self.transport = transport

    @property
    def configured(self) -> bool:
        if self.provider == "gemini":
            return bool(self.api_key and self.model)
        if self.provider == "openai_compatible":
            return bool(self.api_key and self.model and self.api_url)
        return False

    def embed(self, text: str) -> EmbeddingResult:
        text = _clean_text(text, 12000)
        if not text:
            raise EmbeddingError("cannot embed empty text")
        if not self.configured:
            raise EmbeddingError("semantic embedding provider is not configured")
        if self.provider == "gemini":
            return self._embed_gemini(text)
        if self.provider == "openai_compatible":
            return self._embed_openai_compatible(text)
        raise EmbeddingError("semantic embedding provider is disabled")

    def _embed_gemini(self, text: str) -> EmbeddingResult:
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{self.model}:embedContent"
        payload = {
            "model": f"models/{self.model}",
            "content": {"parts": [{"text": text}]},
            "output_dimensionality": self.dimensions,
        }
        with httpx.Client(timeout=self.timeout_seconds, transport=self.transport, follow_redirects=False) as client:
            response = client.post(
                url,
                headers={"Content-Type": "application/json", "x-goog-api-key": self.api_key},
                json=payload,
            )
        if response.status_code < 200 or response.status_code >= 300:
            raise EmbeddingError(f"Gemini embedding request returned HTTP {response.status_code}")
        try:
            data = response.json()
            values = data["embedding"]["values"]
        except (ValueError, KeyError, TypeError) as exc:
            raise EmbeddingError("Gemini embedding response did not contain embedding.values") from exc
        return EmbeddingResult(provider="gemini", model=self.model, values=normalize_vector(values))

    def _embed_openai_compatible(self, text: str) -> EmbeddingResult:
        payload: dict[str, Any] = {"model": self.model, "input": text}
        if self.dimensions:
            payload["dimensions"] = self.dimensions
        with httpx.Client(timeout=self.timeout_seconds, transport=self.transport, follow_redirects=False) as client:
            response = client.post(
                self.api_url,
                headers={"Accept": "application/json", "Content-Type": "application/json", "Authorization": f"Bearer {self.api_key}"},
                json=payload,
            )
        if response.status_code < 200 or response.status_code >= 300:
            raise EmbeddingError(f"embedding request returned HTTP {response.status_code}")
        try:
            data = response.json()
            values = data["data"][0]["embedding"]
        except (ValueError, KeyError, IndexError, TypeError) as exc:
            raise EmbeddingError("OpenAI-compatible embedding response did not contain data[0].embedding") from exc
        return EmbeddingResult(provider="openai_compatible", model=self.model, values=normalize_vector(values))


def default_embedding_client() -> EmbeddingClient:
    return EmbeddingClient(
        provider=settings.embedding_provider,
        api_key=settings.embedding_api_key,
        model=settings.embedding_model,
        api_url=settings.embedding_api_url,
        dimensions=settings.embedding_dimensions,
        timeout_seconds=settings.embedding_timeout_seconds,
    )


def semantic_readiness(client: EmbeddingClient | None = None) -> dict[str, Any]:
    client = client or default_embedding_client()
    result: dict[str, Any] = {
        "schema": SEMANTIC_CONTRACT,
        "provider": client.provider,
        "model": client.model or None,
        "configured": client.configured,
        "dimensions": client.dimensions if client.configured else None,
        "fake_embeddings": False,
        "library_local_vector_store": True,
        "platform_core_owns_vectors": False,
        "raw_chunks_promoted_to_core": False,
    }
    try:
        pool = get_pool()
        with pool.connection() as conn, conn.cursor() as cur:
            cur.execute("SELECT count(*) AS count FROM library_record_embeddings")
            result["indexed_records"] = int(cur.fetchone()["count"])
            cur.execute("SELECT status,count(*) AS count FROM library_embedding_jobs GROUP BY status ORDER BY status")
            result["job_counts"] = {row["status"]: int(row["count"]) for row in cur.fetchall()}
    except Exception as exc:
        result["index_state"] = "unavailable"
        result["index_error"] = exc.__class__.__name__
    else:
        result["index_state"] = "ready"
    return result


def embedding_jobs_status(limit: int = 100) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT status,count(*) AS count FROM library_embedding_jobs GROUP BY status ORDER BY status")
        counts = {row["status"]: int(row["count"]) for row in cur.fetchall()}
        cur.execute(
            """
            SELECT job_id,record_id,content_hash,input_hash,status,attempt_count,next_attempt_at,
                   provider,model,dimensions,last_error,created_at,updated_at,completed_at
              FROM library_embedding_jobs
             ORDER BY updated_at DESC
             LIMIT %s
            """,
            (max(1, min(500, int(limit))),),
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": EMBEDDING_JOB_CONTRACT, "counts": counts, "items": items}


def queue_record_embedding(record_id: str, content_hash: str) -> None:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO library_embedding_jobs(record_id,content_hash,status,attempt_count,next_attempt_at,updated_at)
            VALUES (%s,%s,'pending',0,now(),now())
            ON CONFLICT (record_id) DO UPDATE SET
                content_hash=EXCLUDED.content_hash,
                input_hash=NULL,
                status='pending',
                attempt_count=0,
                next_attempt_at=now(),
                provider=NULL,
                model=NULL,
                dimensions=NULL,
                last_error=NULL,
                updated_at=now(),
                completed_at=NULL
            """,
            (record_id, content_hash),
        )
        conn.commit()


def process_embedding_jobs_once(limit: int = 25, client: EmbeddingClient | None = None) -> dict[str, Any]:
    client = client or default_embedding_client()
    if not client.configured:
        raise RuntimeError("semantic embedding provider is not configured")
    limit = max(1, min(100, int(limit)))
    pool = get_pool()
    processed: list[dict[str, Any]] = []

    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT job_id,record_id,content_hash,attempt_count
                  FROM library_embedding_jobs
                 WHERE status IN ('pending','retry') AND next_attempt_at <= now()
                 ORDER BY created_at ASC
                 FOR UPDATE SKIP LOCKED
                 LIMIT %s
                """,
                (limit,),
            )
            jobs = [dict(row) for row in cur.fetchall()]
            for job in jobs:
                cur.execute(
                    "UPDATE library_embedding_jobs SET status='processing',attempt_count=attempt_count+1,updated_at=now() WHERE job_id=%s",
                    (job["job_id"],),
                )
        conn.commit()

        for job in jobs:
            job_id = job["job_id"]
            record_id = job["record_id"]
            try:
                with conn.cursor() as cur:
                    cur.execute(
                        """
                        SELECT record_id,title,abstract,body_text,topics,tags,content_hash
                          FROM library_records
                         WHERE record_id=%s
                        """,
                        (record_id,),
                    )
                    record = cur.fetchone()
                if not record:
                    with conn.cursor() as cur:
                        cur.execute("DELETE FROM library_embedding_jobs WHERE job_id=%s", (job_id,))
                    conn.commit()
                    processed.append({"record_id": record_id, "status": "deleted"})
                    continue
                if str(record["content_hash"]) != str(job["content_hash"]):
                    with conn.cursor() as cur:
                        cur.execute(
                            "UPDATE library_embedding_jobs SET content_hash=%s,status='pending',attempt_count=0,next_attempt_at=now(),updated_at=now() WHERE job_id=%s",
                            (record["content_hash"], job_id),
                        )
                    conn.commit()
                    processed.append({"record_id": record_id, "status": "requeued-stale"})
                    continue

                input_text = embedding_input_from_record(dict(record))
                input_hash = hashlib.sha256(input_text.encode("utf-8")).hexdigest()
                embedding = client.embed(input_text)
                now = datetime.now(timezone.utc)
                with conn.cursor() as cur:
                    cur.execute(
                        """
                        INSERT INTO library_record_embeddings(
                            record_id,content_hash,input_hash,provider,model,dimensions,embedding,updated_at
                        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
                        ON CONFLICT (record_id) DO UPDATE SET
                            content_hash=EXCLUDED.content_hash,
                            input_hash=EXCLUDED.input_hash,
                            provider=EXCLUDED.provider,
                            model=EXCLUDED.model,
                            dimensions=EXCLUDED.dimensions,
                            embedding=EXCLUDED.embedding,
                            updated_at=EXCLUDED.updated_at
                        """,
                        (record_id, record["content_hash"], input_hash, embedding.provider, embedding.model, embedding.dimensions, embedding.values, now),
                    )
                    cur.execute(
                        """
                        UPDATE library_embedding_jobs
                           SET input_hash=%s,status='complete',provider=%s,model=%s,dimensions=%s,
                               last_error=NULL,updated_at=%s,completed_at=%s
                         WHERE job_id=%s
                        """,
                        (input_hash, embedding.provider, embedding.model, embedding.dimensions, now, now, job_id),
                    )
                conn.commit()
                processed.append({"record_id": record_id, "status": "complete", "dimensions": embedding.dimensions})
            except Exception as exc:
                attempts = int(job.get("attempt_count") or 0) + 1
                terminal = attempts >= settings.embedding_max_attempts
                next_attempt = datetime.now(timezone.utc) + timedelta(seconds=min(3600, 30 * (2 ** max(0, attempts - 1))))
                with conn.cursor() as cur:
                    cur.execute(
                        """
                        UPDATE library_embedding_jobs
                           SET status=%s,last_error=%s,next_attempt_at=%s,updated_at=now()
                         WHERE job_id=%s
                        """,
                        ("failed" if terminal else "retry", f"{exc.__class__.__name__}: {str(exc)[:500]}", next_attempt, job_id),
                    )
                conn.commit()
                processed.append({"record_id": record_id, "status": "failed" if terminal else "retry", "error": exc.__class__.__name__})

    return {"schema": EMBEDDING_JOB_CONTRACT, "processed": processed, "count": len(processed)}
