from __future__ import annotations

import sys
import types

if "psycopg" not in sys.modules:
    psycopg = types.ModuleType("psycopg"); psycopg.__path__ = []
    rows = types.ModuleType("psycopg.rows"); rows.dict_row = object()
    types_pkg = types.ModuleType("psycopg.types"); types_pkg.__path__ = []
    json_pkg = types.ModuleType("psycopg.types.json")
    class Jsonb:
        def __init__(self, value): self.value = value
    json_pkg.Jsonb = Jsonb
    pool_pkg = types.ModuleType("psycopg_pool")
    class ConnectionPool: pass
    pool_pkg.ConnectionPool = ConnectionPool
    sys.modules.update({
        "psycopg": psycopg,
        "psycopg.rows": rows,
        "psycopg.types": types_pkg,
        "psycopg.types.json": json_pkg,
        "psycopg_pool": pool_pkg,
    })

from fastapi.testclient import TestClient
from app import main


def test_publication_knowledge_map_route_preserves_colon_record_id(monkeypatch):
    seen = {}
    def fake(record_id, **kwargs):
        seen["record_id"] = record_id
        seen.update(kwargs)
        return {
            "schema": "sc-library-publication-knowledge-map/1.0",
            "record_id": record_id,
            "nodes": [{"id": record_id, "kind": "publication", "label": "Paper", "root": True}],
            "edges": [],
            "semantic_analysis": {"available": False},
        }
    monkeypatch.setattr(main, "build_publication_knowledge_map", fake)
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-knowledge-maps", params={
            "record_id": "wordpress:1:post:1621",
            "semantic_threshold": 0.78,
            "max_neighbors": 25,
        })
    assert response.status_code == 200
    assert response.json()["record_id"] == "wordpress:1:post:1621"
    assert seen["record_id"] == "wordpress:1:post:1621"
    assert seen["semantic_threshold"] == 0.78
    assert seen["max_neighbors"] == 25


def test_publication_knowledge_map_readiness_route(monkeypatch):
    monkeypatch.setattr(main, "knowledge_map_readiness", lambda: {
        "schema": "sc-library-publication-knowledge-map-readiness/1.0",
        "publication_knowledge_mapping": True,
        "storage_ready": True,
    })
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-knowledge-maps/readiness")
    assert response.status_code == 200
    assert response.json()["publication_knowledge_mapping"] is True
