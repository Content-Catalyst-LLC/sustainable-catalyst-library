from __future__ import annotations

import sys
import types

from fastapi.testclient import TestClient

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

from app import main


def test_public_list_route_exposes_published_visualizations(monkeypatch):
    seen = {}
    def fake_list(record_id: str, *, published_only: bool = True, limit: int = 50):
        seen.update(record_id=record_id, published_only=published_only, limit=limit)
        return {"schema": "sc-library-publication-visualization/1.0", "record_id": record_id, "items": [], "count": 0}
    monkeypatch.setattr(main, "list_publication_visualizations", fake_list)
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-visualizations", params={"record_id": "wordpress:1:post:1621", "limit": 8})
    assert response.status_code == 200, response.text
    assert seen == {"record_id": "wordpress:1:post:1621", "published_only": True, "limit": 8}


def test_visualization_readiness_route(monkeypatch):
    monkeypatch.setattr(main, "visualization_readiness", lambda: {
        "schema": "sc-library-publication-visualization-readiness/1.0",
        "publication_visualizations": True,
        "storage_ready": True,
    })
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-visualizations/readiness")
    assert response.status_code == 200
    assert response.json()["publication_visualizations"] is True
