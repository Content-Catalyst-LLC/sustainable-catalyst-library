from __future__ import annotations

import sys
import types

# Build-environment shims mirror the retained v2.24.0 tests.
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

import app.hybrid_retrieval as hybrid


class _Cursor:
    def __enter__(self): return self
    def __exit__(self, *args): return False
    def execute(self, *args, **kwargs): return None
    def fetchall(self): return []


class _Connection:
    def __enter__(self): return self
    def __exit__(self, *args): return False
    def cursor(self): return _Cursor()


class _Pool:
    def connection(self): return _Connection()


def test_core_binding_lookup_executes_without_nameerror(monkeypatch):
    monkeypatch.setattr(hybrid, "get_pool", lambda: _Pool())
    assert hybrid._core_bindings(["record-1"]) == {}


def test_core_enrichment_executes_for_unbound_record(monkeypatch):
    monkeypatch.setattr(hybrid, "get_pool", lambda: _Pool())
    rows = hybrid.enrich_with_core_bindings([{"record_id": "record-1", "title": "Example"}])
    assert rows[0]["platform_core"] == {
        "bound": False,
        "binding_count": 0,
        "objects": [],
        "authority": None,
    }
