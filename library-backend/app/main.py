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
from .query import explorer_bootstrap, facets, get_record, graph_neighborhood, related_records, search_records, stats, timeline
from .repository import delete_record, ingest_edges, ingest_records
from .security import constant_time_equal, sha256_hex, sign_request, valid_timestamp
from .settings import settings
from .operations import integrity_audit, operations_status, prune_records
from .institutional_sources import InstitutionalSourceError, build_registry
from .biomedical_sources import BiomedicalSourceError, build_biomedical_registry
from .fda_regulatory import FDARegulatoryError, build_fda_regulatory_registry
from .medical_terminology import MedicalTerminologyError, MedicalTerminologyResolver, WHOICD11Connector


@asynccontextmanager
async def lifespan(_: FastAPI):
    if settings.database_url:
        initialize_database()
    yield
    close_pool()


institutional_sources = build_registry(settings.institutional_source_timeout_seconds)
biomedical_sources = build_biomedical_registry(
    settings.biomedical_source_timeout_seconds,
    ncbi_tool=settings.ncbi_tool, ncbi_email=settings.ncbi_email, ncbi_api_key=settings.ncbi_api_key,
)
fda_regulatory_sources = build_fda_regulatory_registry(
    settings.fda_source_timeout_seconds, api_key=settings.openfda_api_key,
)
icd11_source = WHOICD11Connector(
    settings.medical_terminology_timeout_seconds,
    base_url=settings.who_icd_base_url,
    token_url=settings.who_icd_token_url,
    client_id=settings.who_icd_client_id,
    client_secret=settings.who_icd_client_secret,
    release_id=settings.who_icd_release_id,
    language=settings.who_icd_language,
    local_mode=settings.who_icd_local_mode,
)
medical_terminology = MedicalTerminologyResolver(icd11_source, biomedical_sources)


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
            "dynamic_explorer": True,
            "progressive_discovery": True,
            "filterable_search": True,
            "progressive_record_detail": True,
            "integrity_audit": True,
            "targeted_pruning": True,
            "semantic_embeddings": "adapter-ready",
            "institutional_sources": True,
            "johns_hopkins_dataverse": True,
            "license_reuse_normalization": True,
            "biomedical_evidence": True,
            "pubmed": True,
            "pubmed_central": True,
            "clinicaltrials_gov": True,
            "mesh_2026": True,
            "rxnorm": True,
            "fda_regulatory_intelligence": True,
            "drugs_at_fda": True,
            "fda_drug_labeling": True,
            "fda_ndc_directory": True,
            "faers_adverse_events": True,
            "fda_drug_recalls": True,
            "fda_drug_shortages": True,
            "fda_orange_book": True,
            "medical_terminology": True,
            "icd11_2026": True,
            "mesh_rxnorm_crosswalk": True,
            "semantic_equivalence_guardrail": True,
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


@app.get("/v1/biomedical-sources")
def list_biomedical_sources() -> dict[str, Any]:
    return {"schema": "sc-biomedical-sources/1.0", "sources": biomedical_sources.list_sources()}


@app.get("/v1/biomedical-sources/{source_key}/search")
def biomedical_source_search(
    source_key: str,
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=50),
    cursor: str = Query(default="", max_length=500),
) -> dict[str, Any]:
    try:
        return biomedical_sources.get(source_key).search(q, limit=limit, cursor=cursor)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="biomedical source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except BiomedicalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/biomedical/search")
def biomedical_unified_search(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str = Query(default="", max_length=200),
    limit: int = Query(default=5, ge=1, le=20),
) -> dict[str, Any]:
    requested = [item.strip() for item in sources.split(",") if item.strip()] or None
    try:
        return biomedical_sources.unified_search(q, limit=limit, source_keys=requested)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/fda-sources")
def list_fda_sources() -> dict[str, Any]:
    return {"schema": "sc-fda-sources/1.0", "sources": fda_regulatory_sources.list_sources()}


@app.get("/v1/fda-sources/{source_key}/search")
def fda_source_search(
    source_key: str,
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=20),
    cursor: str = Query(default="", max_length=20),
) -> dict[str, Any]:
    try:
        return fda_regulatory_sources.get(source_key).search(q, limit=limit, cursor=cursor)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="FDA regulatory source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except FDARegulatoryError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/fda/search")
def fda_unified_search(
    q: str = Query(..., min_length=1, max_length=500),
    sources: str = Query(default="", max_length=300),
    limit: int = Query(default=4, ge=1, le=10),
) -> dict[str, Any]:
    requested = [item.strip() for item in sources.split(",") if item.strip()] or None
    try:
        return fda_regulatory_sources.unified_search(q, limit=limit, source_keys=requested)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.get("/v1/biomedical/intelligence/search")
def biomedical_intelligence_search(
    q: str = Query(..., min_length=1, max_length=500),
    biomedical_limit: int = Query(default=3, ge=1, le=10),
    regulatory_limit: int = Query(default=3, ge=1, le=10),
) -> dict[str, Any]:
    return {
        "schema": "sc-biomedical-intelligence/1.0",
        "query": q,
        "biomedical": biomedical_sources.unified_search(q, limit=biomedical_limit),
        "regulatory": fda_regulatory_sources.unified_search(q, limit=regulatory_limit),
        "governance": {
            "research_only": True,
            "clinical_decision_support": False,
            "evidence_classes_preserved": True,
            "notice": "Biomedical literature and FDA regulatory records are returned as separate evidence families and must not be treated as equivalent evidence."
        },
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/v1/medical-terminology")
def medical_terminology_manifest() -> dict[str, Any]:
    return {
        "schema": "sc-medical-terminology-sources/1.0",
        "sources": medical_terminology.source_manifest(),
        "icd11": {
            "configured": icd11_source.configured(),
            "release_id": settings.who_icd_release_id,
            "language": settings.who_icd_language,
            "local_mode": settings.who_icd_local_mode,
        },
        "governance": {
            "research_only": True,
            "clinical_decision_support": False,
            "semantic_equivalence_asserted": False,
        },
    }


@app.get("/v1/medical-terminology/icd11/search")
def icd11_search(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=10, ge=1, le=25),
) -> dict[str, Any]:
    try:
        return icd11_source.search(q, limit=limit)
    except MedicalTerminologyError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/medical-terminology/resolve")
def medical_terminology_resolve(
    q: str = Query(..., min_length=1, max_length=500),
    limit: int = Query(default=5, ge=1, le=10),
) -> dict[str, Any]:
    return medical_terminology.resolve(q, limit=limit)


@app.get("/v1/institutional-sources")
def list_institutional_sources() -> dict[str, Any]:
    return {
        "schema": "sc-institutional-sources/1.0",
        "sources": institutional_sources.list_sources(),
    }


@app.get("/v1/institutional-sources/{source_key}/search")
def institutional_source_search(
    source_key: str,
    q: str = Query(default="", max_length=500),
    object_type: str = Query(default="dataset", max_length=40),
    limit: int = Query(default=10, ge=1, le=50),
    start: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    try:
        source = institutional_sources.get(source_key)
        return source.search(q, limit=limit, start=start, object_type=object_type)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="institutional source not found") from exc
    except InstitutionalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/institutional-sources/{source_key}/record")
def institutional_source_record(
    source_key: str,
    persistent_id: str = Query(..., min_length=1, max_length=300),
) -> dict[str, Any]:
    try:
        source = institutional_sources.get(source_key)
        return source.get_record(persistent_id)
    except KeyError as exc:
        raise HTTPException(status_code=404, detail="institutional source not found") from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except InstitutionalSourceError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc


@app.get("/v1/search")
def search(
    q: str = Query(default="", max_length=500),
    object_type: str | None = Query(default=None, max_length=80),
    source_key: str | None = Query(default=None, max_length=191),
    topic: str | None = Query(default=None, max_length=500),
    year_from: int | None = Query(default=None, ge=1000, le=3000),
    year_to: int | None = Query(default=None, ge=1000, le=3000),
    sort: str = Query(default="relevance", max_length=20),
    limit: int = Query(default=20, ge=1, le=100),
    offset: int = Query(default=0, ge=0, le=100000),
) -> dict[str, Any]:
    return search_records(q, object_type, source_key, topic, year_from, year_to, sort, limit, offset)


@app.get("/v1/explorer/bootstrap")
def explorer_public_bootstrap(
    featured_limit: int = Query(default=4, ge=1, le=12),
    recent_limit: int = Query(default=4, ge=1, le=12),
) -> dict[str, Any]:
    return explorer_bootstrap(featured_limit, recent_limit)


@app.get("/v1/records/{record_id}")
def record(record_id: str, include_body: bool = Query(default=True)) -> dict[str, Any]:
    row = get_record(record_id, include_body=include_body)
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
