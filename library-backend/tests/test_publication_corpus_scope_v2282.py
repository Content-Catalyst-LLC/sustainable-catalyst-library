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

from app.publication_corpus_maps import _eligible_records


class FakeCursor:
    def __init__(self):
        self.calls = []
    def execute(self, sql, params=()):
        self.calls.append((sql, params))
    def fetchone(self):
        return {"n": 2}
    def fetchall(self):
        return [
            {"record_id": "wordpress:1:post:10", "source_key": "wordpress-main", "title": "A"},
            {"record_id": "wordpress:1:post:20", "source_key": "wordpress-main", "title": "B"},
        ]


def test_manifest_selection_uses_record_ids_not_generic_post_type():
    cur = FakeCursor()
    manifest = ["wordpress:1:post:10", "wordpress:1:post:20"]
    records, total, selection = _eligible_records(cur, record_ids=manifest)
    assert total == 2
    assert selection == "publication-library-manifest"
    sql = "\n".join(x[0] for x in cur.calls)
    assert "record_id=ANY(%s)" in sql
    assert "object_type=%s" not in sql
    assert cur.calls[0][1] == ("wordpress-main", manifest)
    assert set(records) == set(manifest)


def test_backend_fallback_excludes_pages_and_custom_document_types():
    cur = FakeCursor()
    _records, _total, selection = _eligible_records(cur)
    sql = "\n".join(x[0] for x in cur.calls)
    assert selection == "wordpress-post-fallback"
    assert "object_type=%s" in sql
    assert cur.calls[0][1] == ("wordpress-main", "post")
