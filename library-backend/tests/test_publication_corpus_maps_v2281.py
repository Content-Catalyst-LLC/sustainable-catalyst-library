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

from app.publication_corpus_maps import CORPUS_KNOWLEDGE_MAP_CONTRACT, _eligible_records


class FakeCursor:
    def __init__(self):
        self.calls = []
        self._mode = "count"
    def execute(self, sql, params=()):
        self.calls.append((sql, params))
        self._mode = "records" if "SELECT record_id,source_key" in sql else "count"
    def fetchone(self):
        return {"n": 3}
    def fetchall(self):
        return [
            {"record_id": "wordpress:1:post:3", "source_key": "wordpress-main", "title": "C"},
            {"record_id": "wordpress:1:post:2", "source_key": "wordpress-main", "title": "B"},
        ]


def test_corpus_contract_is_explicit():
    assert CORPUS_KNOWLEDGE_MAP_CONTRACT == "sc-library-publication-corpus-knowledge-map/1.0"


def test_corpus_selector_is_bounded_to_wordpress_main_by_default():
    cur = FakeCursor()
    records, total = _eligible_records(cur)
    assert total == 3
    assert list(records) == ["wordpress:1:post:3", "wordpress:1:post:2"]
    sql_text = "\n".join(call[0] for call in cur.calls)
    assert "source_key=%s" in sql_text
    assert cur.calls[0][1] == ("wordpress-main",)
    assert cur.calls[1][1][-1] == 250
