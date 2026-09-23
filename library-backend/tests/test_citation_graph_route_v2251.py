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

from app import main


def test_graph_route_precedes_catch_all_and_preserves_record_id(monkeypatch):
    seen = {}

    def fake_graph(record_id: str, depth: int = 2, limit: int = 250, include_core: bool = True):
        seen["record_id"] = record_id
        return {
            "schema": "sc-library-citation-graph/1.0",
            "root_record_id": record_id,
            "depth": depth,
            "items": [],
            "count": 0,
        }

    monkeypatch.setattr(main, "citation_graph", fake_graph)
    with TestClient(main.app) as client:
        response = client.get(
            "/v1/citations/wordpress:1:post:1621/graph",
            params={"depth": 1, "limit": 25, "include_core": "true"},
        )
    assert response.status_code == 200, response.text
    payload = response.json()
    assert payload["schema"] == "sc-library-citation-graph/1.0"
    assert payload["root_record_id"] == "wordpress:1:post:1621"
    assert seen["record_id"] == "wordpress:1:post:1621"


def test_generic_citation_list_route_still_works(monkeypatch):
    seen = {}

    def fake_list(record_id: str, direction: str = "both", limit: int = 100):
        seen.update(record_id=record_id, direction=direction, limit=limit)
        return {"schema": "sc-library-citations/1.0", "record_id": record_id, "items": [], "count": 0}

    monkeypatch.setattr(main, "list_citations", fake_list)
    with TestClient(main.app) as client:
        response = client.get(
            "/v1/citations/wordpress:1:post:1621",
            params={"direction": "both", "limit": 25},
        )
    assert response.status_code == 200, response.text
    assert response.json()["record_id"] == "wordpress:1:post:1621"
    assert seen["record_id"] == "wordpress:1:post:1621"
