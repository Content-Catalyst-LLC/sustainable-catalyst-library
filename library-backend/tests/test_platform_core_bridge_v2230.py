from __future__ import annotations

import json
from pathlib import Path

import httpx
import pytest
from pydantic import ValidationError

import sys
import types

# Unit-test shim for the build environment, which does not have the deployment
# PostgreSQL wheels installed. Production installs them from requirements.txt.
if "psycopg" not in sys.modules:
    psycopg = types.ModuleType("psycopg")
    psycopg.__path__ = []
    rows = types.ModuleType("psycopg.rows")
    rows.dict_row = object()
    types_pkg = types.ModuleType("psycopg.types")
    types_pkg.__path__ = []
    json_pkg = types.ModuleType("psycopg.types.json")
    class Jsonb:
        def __init__(self, value): self.value = value
    json_pkg.Jsonb = Jsonb
    pool_pkg = types.ModuleType("psycopg_pool")
    class ConnectionPool:
        pass
    pool_pkg.ConnectionPool = ConnectionPool
    sys.modules.update({
        "psycopg": psycopg,
        "psycopg.rows": rows,
        "psycopg.types": types_pkg,
        "psycopg.types.json": json_pkg,
        "psycopg_pool": pool_pkg,
    })

from app.platform_core import (
    CAPABILITY_PROBES,
    CORE_OPERATIONS,
    CoreBindingRequest,
    CoreOutboxRequest,
    PlatformCoreClient,
    bridge_readiness,
    stable_hash,
)


def _transport(request: httpx.Request) -> httpx.Response:
    if request.url.path == "/health":
        return httpx.Response(200, json={"ok": True, "service": "platform-core", "version": "3.3.0"})
    if request.url.path in CAPABILITY_PROBES.values():
        return httpx.Response(200, json={"status": "ready", "release": "3.3.0"})
    if request.url.path == "/v1/research-objects" and request.method == "POST":
        assert request.headers.get("X-SC-API-Key") == "write-key"
        body = json.loads(request.content.decode("utf-8"))
        return httpx.Response(201, json={"entity_id": "entity:research-project:test", "name": body["name"]})
    return httpx.Response(404, json={"detail": "not found"})


def client() -> PlatformCoreClient:
    return PlatformCoreClient(
        base_url="https://core.example.test",
        write_api_key="write-key",
        timeout_seconds=4,
        transport=httpx.MockTransport(_transport),
    )


def test_capability_discovery_covers_core_research_reasoning_surfaces():
    result = client().capabilities()
    assert result["reachable"] is True
    assert result["core_version"] == "3.3.0"
    assert result["capability_count"] == len(CAPABILITY_PROBES) == 8
    assert result["ready_capability_count"] == 8
    assert set(result["capabilities"]) == {
        "research_objects",
        "research_lineage",
        "research_intelligence",
        "cross_product_exchange",
        "scholarly_interoperability",
        "unified_research_runtime",
        "unified_visual_reasoning",
        "statistical_reasoning",
    }


def test_bridge_readiness_enforces_promotion_boundary():
    result = bridge_readiness(client())
    policy = result["promotion_policy"]
    assert policy["raw_chunks_remain_library_local"] is True
    assert policy["automatic_truth_promotion"] is False
    assert policy["automatic_claim_promotion"] is False
    assert policy["explicit_governed_promotion_required"] is True
    assert policy["idempotent_outbox"] is True
    assert policy["durable_core_bindings"] is True


def test_core_write_operation_is_allowlisted_and_authenticated():
    response = client().execute(
        "research-object.create",
        {"object_type": "research-project", "name": "Library bridge test"},
    )
    assert response.status_code == 201
    assert response.data["entity_id"] == "entity:research-project:test"
    assert set(CORE_OPERATIONS) == {
        "research-object.create",
        "exchange-package.create",
        "scholarly-package.create",
        "scholarly-citation.create",
        "runtime-contract.create",
    }


def test_arbitrary_core_path_cannot_be_queued():
    with pytest.raises(ValidationError):
        CoreOutboxRequest(operation="arbitrary.post", payload={"x": 1})


def test_binding_hash_validation():
    valid = CoreBindingRequest(
        library_record_id="library:record:1",
        core_object_id="entity:1",
        core_object_type="research-project",
        content_hash="a" * 64,
    )
    assert valid.content_hash == "a" * 64
    with pytest.raises(ValidationError):
        CoreBindingRequest(
            library_record_id="library:record:1",
            core_object_id="entity:1",
            core_object_type="research-project",
            content_hash="not-a-digest",
        )


def test_stable_hash_is_deterministic_for_idempotency():
    left = stable_hash({"b": 2, "a": [3, 1]})
    right = stable_hash({"a": [3, 1], "b": 2})
    assert left == right
    assert len(left) == 64


def test_v2230_bridge_schema_tables_remain_preserved():
    repo = Path(__file__).resolve().parents[2]
    schema = (repo / "library-backend" / "app" / "schema.sql").read_text()
    assert "CREATE TABLE IF NOT EXISTS library_core_bindings" in schema
    assert "CREATE TABLE IF NOT EXISTS library_core_sync_outbox" in schema
    assert "UNIQUE(library_record_id, core_object_id)" in schema
    assert "idempotency_key varchar(128) NOT NULL UNIQUE" in schema
