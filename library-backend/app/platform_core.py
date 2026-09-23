from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
import hashlib
import json
from typing import Any, Callable

import httpx
from psycopg.types.json import Jsonb
from pydantic import BaseModel, Field, field_validator

from .db import get_pool
from .settings import settings

BRIDGE_CONTRACT = "sc-library-platform-core-bridge/1.0"
OUTBOX_CONTRACT = "sc-library-platform-core-outbox/1.0"
BINDING_CONTRACT = "sc-library-platform-core-binding/1.0"

# Only named, reviewed Core operations can pass through the outbox.  A caller
# cannot supply an arbitrary URL or arbitrary Core path.
CORE_OPERATIONS: dict[str, tuple[str, str]] = {
    "research-object.create": ("POST", "/v1/research-objects"),
    "exchange-package.create": ("POST", "/v1/exchange/packages"),
    "scholarly-package.create": ("POST", "/v1/research/scholarly-packages/packages"),
    "runtime-contract.create": ("POST", "/v1/research/runtime-contract/contracts"),
}

CAPABILITY_PROBES: dict[str, str] = {
    "research_objects": "/v1/research-objects/readiness",
    "research_lineage": "/v1/research/lineage/readiness",
    "research_intelligence": "/v1/research/intelligence/readiness",
    "cross_product_exchange": "/v1/exchange/readiness",
    "scholarly_interoperability": "/v1/research/scholarly-packages/readiness",
    "unified_research_runtime": "/v1/research/runtime-contract/readiness",
    "unified_visual_reasoning": "/v1/visual-runtime/unified/readiness",
    "statistical_reasoning": "/v1/analytics/statistical-reasoning/readiness",
}


class CoreBindingRequest(BaseModel):
    library_record_id: str = Field(min_length=1, max_length=500)
    library_object_type: str = Field(default="document", min_length=1, max_length=80)
    core_object_id: str = Field(min_length=1, max_length=500)
    core_object_type: str = Field(min_length=1, max_length=120)
    core_canonical_uri: str = Field(default="", max_length=1000)
    content_hash: str = Field(default="", max_length=64)
    metadata: dict[str, Any] = Field(default_factory=dict)

    @field_validator("content_hash")
    @classmethod
    def validate_hash(cls, value: str) -> str:
        value = value.strip().lower()
        if value and (len(value) != 64 or any(ch not in "0123456789abcdef" for ch in value)):
            raise ValueError("content_hash must be an empty value or a lowercase SHA-256 digest")
        return value


class CoreOutboxRequest(BaseModel):
    library_record_id: str = Field(default="", max_length=500)
    operation: str = Field(min_length=1, max_length=120)
    payload: dict[str, Any] = Field(default_factory=dict)
    idempotency_key: str = Field(default="", max_length=128)
    metadata: dict[str, Any] = Field(default_factory=dict)

    @field_validator("operation")
    @classmethod
    def validate_operation(cls, value: str) -> str:
        value = value.strip()
        if value not in CORE_OPERATIONS:
            raise ValueError(f"unsupported Platform Core operation: {value}")
        return value


@dataclass(frozen=True)
class CoreResponse:
    status_code: int
    data: dict[str, Any]


class PlatformCoreClient:
    def __init__(
        self,
        *,
        base_url: str,
        write_api_key: str = "",
        timeout_seconds: int = 8,
        transport: httpx.BaseTransport | None = None,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.write_api_key = write_api_key
        self.timeout_seconds = timeout_seconds
        self.transport = transport

    @property
    def configured(self) -> bool:
        return bool(self.base_url)

    def _request(self, method: str, path: str, payload: dict[str, Any] | None = None) -> CoreResponse:
        if not self.configured:
            raise RuntimeError("SC_LIBRARY_PLATFORM_CORE_URL is not configured")
        headers = {"Accept": "application/json", "User-Agent": "Sustainable-Catalyst-Library-Core-Bridge/1.0"}
        if method != "GET":
            if not self.write_api_key:
                raise RuntimeError("SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY is not configured")
            headers["X-SC-API-Key"] = self.write_api_key
        with httpx.Client(
            base_url=self.base_url,
            timeout=self.timeout_seconds,
            follow_redirects=False,
            transport=self.transport,
        ) as client:
            response = client.request(method, path, json=payload if method != "GET" else None, headers=headers)
        try:
            body = response.json()
        except ValueError:
            body = {"detail": response.text[:2000]}
        if not isinstance(body, dict):
            body = {"data": body}
        return CoreResponse(status_code=response.status_code, data=body)

    def get(self, path: str) -> CoreResponse:
        return self._request("GET", path)

    def execute(self, operation: str, payload: dict[str, Any]) -> CoreResponse:
        method, path = CORE_OPERATIONS[operation]
        return self._request(method, path, payload)

    def capabilities(self) -> dict[str, Any]:
        if not self.configured:
            return {
                "schema": BRIDGE_CONTRACT,
                "configured": False,
                "reachable": False,
                "core_version": None,
                "capabilities": {},
            }
        result: dict[str, Any] = {
            "schema": BRIDGE_CONTRACT,
            "configured": True,
            "reachable": False,
            "core_version": None,
            "capabilities": {},
        }
        try:
            health = self.get("/health")
        except (httpx.HTTPError, RuntimeError) as exc:
            result["error"] = exc.__class__.__name__
            return result
        result["health_status"] = health.status_code
        result["reachable"] = 200 <= health.status_code < 300
        result["core_version"] = health.data.get("version")
        result["core_service"] = health.data.get("service")
        result["core_health"] = {
            key: value for key, value in health.data.items()
            if key not in {"environment"}
        }
        if not result["reachable"]:
            return result
        for name, path in CAPABILITY_PROBES.items():
            try:
                response = self.get(path)
                result["capabilities"][name] = {
                    "available": 200 <= response.status_code < 300,
                    "status_code": response.status_code,
                    "readiness": response.data if 200 <= response.status_code < 300 else {},
                }
            except httpx.HTTPError as exc:
                result["capabilities"][name] = {
                    "available": False,
                    "status_code": None,
                    "error": exc.__class__.__name__,
                }
        result["ready_capability_count"] = sum(1 for item in result["capabilities"].values() if item["available"])
        result["capability_count"] = len(CAPABILITY_PROBES)
        return result


def default_client() -> PlatformCoreClient:
    return PlatformCoreClient(
        base_url=settings.platform_core_url,
        write_api_key=settings.platform_core_write_api_key,
        timeout_seconds=settings.platform_core_timeout_seconds,
    )


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), default=str)


def stable_hash(value: Any) -> str:
    return hashlib.sha256(canonical_json(value).encode("utf-8")).hexdigest()


def bridge_readiness(client: PlatformCoreClient | None = None) -> dict[str, Any]:
    client = client or default_client()
    capabilities = client.capabilities()
    return {
        "schema": BRIDGE_CONTRACT,
        "library_role": "source-intelligence-and-retrieval",
        "platform_core_role": "governed-research-reasoning-and-provenance",
        "configured": capabilities.get("configured", False),
        "reachable": capabilities.get("reachable", False),
        "core_version": capabilities.get("core_version"),
        "ready_capability_count": capabilities.get("ready_capability_count", 0),
        "capability_count": capabilities.get("capability_count", len(CAPABILITY_PROBES)),
        "promotion_policy": {
            "raw_chunks_remain_library_local": True,
            "automatic_truth_promotion": False,
            "automatic_claim_promotion": False,
            "explicit_governed_promotion_required": True,
            "idempotent_outbox": True,
            "durable_core_bindings": True,
        },
        "operations": sorted(CORE_OPERATIONS),
    }


def upsert_binding(binding: CoreBindingRequest) -> dict[str, Any]:
    pool = get_pool()
    now = datetime.now(timezone.utc)
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO library_core_bindings(
                library_record_id, library_object_type, core_object_id, core_object_type,
                core_canonical_uri, content_hash, metadata, sync_status, last_synced_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,'synced',%s,%s)
            ON CONFLICT (library_record_id, core_object_id) DO UPDATE SET
                library_object_type=EXCLUDED.library_object_type,
                core_object_type=EXCLUDED.core_object_type,
                core_canonical_uri=EXCLUDED.core_canonical_uri,
                content_hash=EXCLUDED.content_hash,
                metadata=EXCLUDED.metadata,
                sync_status='synced',
                last_synced_at=EXCLUDED.last_synced_at,
                updated_at=EXCLUDED.updated_at
            RETURNING binding_id, library_record_id, library_object_type, core_object_id,
                      core_object_type, core_canonical_uri, content_hash, metadata,
                      sync_status, last_synced_at, created_at, updated_at
            """,
            (
                binding.library_record_id,
                binding.library_object_type,
                binding.core_object_id,
                binding.core_object_type,
                binding.core_canonical_uri,
                binding.content_hash or None,
                Jsonb(binding.metadata),
                now,
                now,
            ),
        )
        row = cur.fetchone()
        conn.commit()
    return {"schema": BINDING_CONTRACT, "binding": dict(row)}


def list_bindings(library_record_id: str, limit: int = 100) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            SELECT binding_id, library_record_id, library_object_type, core_object_id,
                   core_object_type, core_canonical_uri, content_hash, metadata,
                   sync_status, last_synced_at, created_at, updated_at
              FROM library_core_bindings
             WHERE library_record_id=%s
             ORDER BY updated_at DESC
             LIMIT %s
            """,
            (library_record_id, max(1, min(500, int(limit)))),
        )
        rows = [dict(row) for row in cur.fetchall()]
    return {"schema": BINDING_CONTRACT, "library_record_id": library_record_id, "items": rows, "count": len(rows)}


def enqueue_operation(request: CoreOutboxRequest) -> dict[str, Any]:
    payload_hash = stable_hash({"operation": request.operation, "payload": request.payload})
    idempotency_key = (request.idempotency_key.strip() or stable_hash({
        "library_record_id": request.library_record_id,
        "operation": request.operation,
        "payload_hash": payload_hash,
    }))[:128]
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO library_core_sync_outbox(
                library_record_id, operation, payload, payload_hash, idempotency_key, metadata
            ) VALUES (%s,%s,%s,%s,%s,%s)
            ON CONFLICT (idempotency_key) DO UPDATE SET
                metadata = library_core_sync_outbox.metadata || EXCLUDED.metadata
            RETURNING event_id, library_record_id, operation, payload_hash, idempotency_key,
                      status, attempt_count, next_attempt_at, last_http_status, last_error,
                      core_object_id, created_at, updated_at, processed_at
            """,
            (
                request.library_record_id or None,
                request.operation,
                Jsonb(request.payload),
                payload_hash,
                idempotency_key,
                Jsonb(request.metadata),
            ),
        )
        row = dict(cur.fetchone())
        conn.commit()
    return {"schema": OUTBOX_CONTRACT, "event": row}


def outbox_status(limit: int = 100) -> dict[str, Any]:
    pool = get_pool()
    with pool.connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT status, count(*) AS count FROM library_core_sync_outbox GROUP BY status ORDER BY status")
        counts = {row["status"]: int(row["count"]) for row in cur.fetchall()}
        cur.execute(
            """
            SELECT event_id, library_record_id, operation, payload_hash, idempotency_key,
                   status, attempt_count, next_attempt_at, last_http_status, last_error,
                   core_object_id, created_at, updated_at, processed_at
              FROM library_core_sync_outbox
             ORDER BY created_at DESC
             LIMIT %s
            """,
            (max(1, min(500, int(limit))),),
        )
        items = [dict(row) for row in cur.fetchall()]
    return {"schema": OUTBOX_CONTRACT, "counts": counts, "items": items}


def _infer_core_object_id(data: dict[str, Any]) -> str:
    for key in ("entity_id", "id", "package_id", "contract_id", "object_id"):
        value = data.get(key)
        if value is not None and str(value).strip():
            return str(value)
    nested = data.get("data")
    if isinstance(nested, dict):
        return _infer_core_object_id(nested)
    return ""


def process_outbox_once(
    *,
    limit: int = 25,
    client: PlatformCoreClient | None = None,
    now_factory: Callable[[], datetime] | None = None,
) -> dict[str, Any]:
    client = client or default_client()
    now_factory = now_factory or (lambda: datetime.now(timezone.utc))
    if not client.configured:
        raise RuntimeError("Platform Core bridge is not configured")
    pool = get_pool()
    processed: list[dict[str, Any]] = []
    with pool.connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT event_id, library_record_id, operation, payload, attempt_count
                  FROM library_core_sync_outbox
                 WHERE status IN ('pending','retry')
                   AND next_attempt_at <= now()
                 ORDER BY created_at ASC
                 FOR UPDATE SKIP LOCKED
                 LIMIT %s
                """,
                (max(1, min(100, int(limit))),),
            )
            events = [dict(row) for row in cur.fetchall()]
            for event in events:
                event_id = event["event_id"]
                cur.execute(
                    "UPDATE library_core_sync_outbox SET status='processing', attempt_count=attempt_count+1, updated_at=now() WHERE event_id=%s",
                    (event_id,),
                )
            conn.commit()

        for event in events:
            event_id = event["event_id"]
            operation = event["operation"]
            try:
                response = client.execute(operation, dict(event["payload"] or {}))
                ok = 200 <= response.status_code < 300
                retryable = response.status_code in {408, 409, 425, 429, 500, 502, 503, 504}
                core_object_id = _infer_core_object_id(response.data) if ok else ""
                with conn.cursor() as cur:
                    if ok:
                        cur.execute(
                            """
                            UPDATE library_core_sync_outbox
                               SET status='complete', last_http_status=%s, last_error=NULL,
                                   core_object_id=%s, processed_at=%s, updated_at=%s
                             WHERE event_id=%s
                            """,
                            (response.status_code, core_object_id or None, now_factory(), now_factory(), event_id),
                        )
                    else:
                        status = "retry" if retryable and int(event["attempt_count"]) + 1 < settings.platform_core_max_attempts else "failed"
                        cur.execute(
                            """
                            UPDATE library_core_sync_outbox
                               SET status=%s, last_http_status=%s, last_error=%s,
                                   next_attempt_at=CASE WHEN %s='retry' THEN now() + interval '5 minutes' ELSE next_attempt_at END,
                                   updated_at=%s
                             WHERE event_id=%s
                            """,
                            (status, response.status_code, str(response.data.get("detail") or "Core request failed")[:2000], status, now_factory(), event_id),
                        )
                    conn.commit()
                processed.append({"event_id": event_id, "operation": operation, "status_code": response.status_code, "ok": ok, "core_object_id": core_object_id})
            except (httpx.HTTPError, RuntimeError) as exc:
                with conn.cursor() as cur:
                    status = "retry" if int(event["attempt_count"]) + 1 < settings.platform_core_max_attempts else "failed"
                    cur.execute(
                        """
                        UPDATE library_core_sync_outbox
                           SET status=%s, last_http_status=NULL, last_error=%s,
                               next_attempt_at=CASE WHEN %s='retry' THEN now() + interval '5 minutes' ELSE next_attempt_at END,
                               updated_at=%s
                         WHERE event_id=%s
                        """,
                        (status, exc.__class__.__name__, status, now_factory(), event_id),
                    )
                    conn.commit()
                processed.append({"event_id": event_id, "operation": operation, "status_code": None, "ok": False, "error": exc.__class__.__name__})
    return {"schema": OUTBOX_CONTRACT, "processed": processed, "count": len(processed)}


def reconcile_binding(library_record_id: str, client: PlatformCoreClient | None = None) -> dict[str, Any]:
    client = client or default_client()
    bindings = list_bindings(library_record_id, limit=500)["items"]
    results: list[dict[str, Any]] = []
    for binding in bindings:
        uri = str(binding.get("core_canonical_uri") or "").strip()
        if not uri.startswith("/v1/"):
            results.append({"binding_id": binding["binding_id"], "state": "unverifiable", "reason": "no_internal_core_uri"})
            continue
        try:
            response = client.get(uri)
            state = "present" if 200 <= response.status_code < 300 else "missing" if response.status_code == 404 else "error"
            results.append({"binding_id": binding["binding_id"], "state": state, "status_code": response.status_code})
        except httpx.HTTPError as exc:
            results.append({"binding_id": binding["binding_id"], "state": "error", "error": exc.__class__.__name__})
    return {"schema": BINDING_CONTRACT, "library_record_id": library_record_id, "results": results}
