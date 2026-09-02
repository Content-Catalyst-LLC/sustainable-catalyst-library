from __future__ import annotations

from contextlib import asynccontextmanager
from datetime import datetime, timezone
from time import perf_counter
from typing import Any

from fastapi import FastAPI, Header, HTTPException, Query, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import ValidationError

from . import __version__
from .db import close_pool, get_pool, initialize_database
from .models import EdgeBatch, IntegrityAuditRequest, PruneRequest, RecordBatch
from .query import facets, get_record, graph_neighborhood, related_records, search_records, stats, timeline
from .repository import delete_record, ingest_edges, ingest_records
from .security import constant_time_equal, sha256_hex, sign_request, valid_timestamp
from .settings import settings
from .operations import integrity_audit, operations_status, prune_records


@asynccontextmanager
async def lifespan(_: FastAPI):
    if settings.database_url:
        initialize_database()
    yield
    close_pool()


app = FastAPI(
    title="Sustainable Catalyst Library Research Intelligence Backend",
    version=__version__,
    docs_url="/docs" if settings.enable_docs else None,
    redoc_url=None,
    openapi_url="/openapi.json" if settings.enable_docs else None,
    lifespan=lifespan,
)

if settings.allowed_origins:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.allowed_origins,
        allow_credentials=False,
        allow_methods=["GET", "HEAD", "OPTIONS"],
        allow_headers=["Accept", "Content-Type"],
        max_age=600,
    )


@app.middleware("http")
async def request_context(request: Request, call_next):
    started = perf_counter()
    response = await call_next(request)
    response.headers["X-SC-Library-Backend-Version"] = __version__
    response.headers["X-Content-Type-Options"] = "nosniff"
    response.headers["Referrer-Policy"] = "no-referrer"
    response.headers["X-Request-Duration-Ms"] = str(int((perf_counter() - started) * 1000))
    return response


async def authorize_write(
    request: Request,
    authorization: str | None,
    timestamp: str | None,
    signature: str | None,
) -> bytes:
    if not settings.api_key:
        raise HTTPException(status_code=503, detail="SC_LIBRARY_BACKEND_API_KEY is not configured")
    token = ""
    if authorization and authorization.lower().startswith("bearer "):
        token = authorization[7:].strip()
    if not token or not constant_time_equal(token, settings.api_key):
        raise HTTPException(status_code=401, detail="invalid server credential")
    if not timestamp or not valid_timestamp(timestamp, settings.request_skew_seconds):
        raise HTTPException(status_code=401, detail="invalid or expired request timestamp")
    body = await request.body()
    if len(body) > settings.max_body_bytes:
        raise HTTPException(
            status_code=413,
            detail="request body exceeds configured limit",
            headers={
                "X-SC-Max-Body-Bytes": str(settings.max_body_bytes),
                "X-SC-Max-Batch-Records": str(settings.max_batch_records),
            },
        )
    expected = sign_request(request.method, request.url.path, timestamp, body, settings.api_key)
    if not signature or not constant_time_equal(signature, expected):
        raise HTTPException(status_code=401, detail="invalid request signature")
    return body


def database_state() -> tuple[str, str | None]:
    if not settings.database_url:
        return "not_configured", "DATABASE_URL is not configured"
    try:
        pool = get_pool()
        with pool.connection(timeout=3) as conn, conn.cursor() as cur:
            cur.execute("SELECT current_database() AS db, current_setting('server_version') AS version")
            row = cur.fetchone()
        return "online", f"{row['db']} / PostgreSQL {row['version']}"
    except Exception as exc:
        return "unavailable", exc.__class__.__name__


@app.get("/health")
def health() -> dict[str, Any]:
    db_state, detail = database_state()
    return {
        "ok": db_state == "online",
        "service": settings.service_name,
        "version": __version__,
        "environment": settings.environment,
        "database": db_state,
        "database_detail": detail,
        "capabilities": {
            "postgresql": True,
            "weighted_full_text_search": True,
            "trigram_title_matching": True,
            "record_chunks": True,
            "provenance": True,
            "knowledge_graph": True,
            "record_timeline": True,
            "facets": True,
            "signed_ingestion": True,
            "adaptive_ingestion": True,
            "server_chunk_fallback": True,
            "operations_recovery": True,
            "integrity_audit": True,
            "targeted_pruning": True,
            "semantic_embeddings": "adapter-ready",
        },
        "ingest_limits": {
            "max_batch_records": settings.max_batch_records,
            "max_body_bytes": settings.max_body_bytes,
            "max_body_mb": settings.max_body_bytes // (1024 * 1024),
        },
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/ready")
def ready() -> JSONResponse:
    db_state, detail = database_state()
    if db_state != "online":
        return JSONResponse(status_code=503, content={"ok": False, "database": db_state, "detail": detail})
    try:
        payload = stats()
    except Exception as exc:
        return JSONResponse(status_code=503, content={"ok": False, "database": "online", "detail": exc.__class__.__name__})
    return JSONResponse(content={"ok": True, "database": "online", "version": __version__, **payload})


@app.post("/v1/ingest/records")
async def ingest_records_route(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        batch = RecordBatch.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    if len(batch.records) > settings.max_batch_records:
        raise HTTPException(
            status_code=413,
            detail="record batch exceeds configured maximum",
            headers={
                "X-SC-Max-Body-Bytes": str(settings.max_body_bytes),
                "X-SC-Max-Batch-Records": str(settings.max_batch_records),
            },
        )
    if any(record.source_key != batch.source.source_key for record in batch.records):
        raise HTTPException(status_code=400, detail="record source_key does not match batch source")
    return ingest_records(batch, sha256_hex(body))


@app.post("/v1/ingest/edges")
async def ingest_edges_route(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        batch = EdgeBatch.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return ingest_edges(batch)


@app.delete("/v1/records/{record_id}")
async def delete_record_route(
    record_id: str,
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return {"ok": True, "record_id": record_id, "deleted": delete_record(record_id)}


@app.get("/v1/search")
def search(
    q: str = Query(default="", max_length=500),
    object_type: str | None = Query(default=None, max_length=80),
    source_key: str | None = Query(default=None, max_length=191),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    return search_records(q, object_type, source_key, limit, offset)


@app.get("/v1/records/{record_id}")
def record(record_id: str) -> dict[str, Any]:
    row = get_record(record_id)
    if row is None:
        raise HTTPException(status_code=404, detail="public record not found")
    return {"schema": "sc-library-record/1.0", "record": row}


@app.get("/v1/records/{record_id}/related")
def related(record_id: str, limit: int = Query(default=12, ge=1, le=50)) -> dict[str, Any]:
    return {"schema": "sc-library-related/1.0", "record_id": record_id, "results": related_records(record_id, limit)}


@app.get("/v1/records/{record_id}/timeline")
def record_timeline(record_id: str, limit: int = Query(default=25, ge=1, le=100)) -> dict[str, Any]:
    return {"schema": "sc-library-record-timeline/1.0", "record_id": record_id, "versions": timeline(record_id, limit)}


@app.get("/v1/graph/{record_id}")
def graph(record_id: str, limit: int = Query(default=100, ge=1, le=500)) -> dict[str, Any]:
    return graph_neighborhood(record_id, limit)


@app.get("/v1/facets")
def public_facets() -> dict[str, Any]:
    return facets()


@app.get("/v1/admin/status")
async def admin_status(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    return operations_status()


@app.post("/v1/admin/integrity")
async def admin_integrity(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = IntegrityAuditRequest.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return integrity_audit(payload)


@app.post("/v1/admin/prune")
async def admin_prune(
    request: Request,
    authorization: str | None = Header(default=None),
    x_sc_timestamp: str | None = Header(default=None),
    x_sc_signature: str | None = Header(default=None),
) -> dict[str, Any]:
    body = await authorize_write(request, authorization, x_sc_timestamp, x_sc_signature)
    try:
        payload = PruneRequest.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc
    return prune_records(payload)
