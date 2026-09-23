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


def test_publication_corpus_route_defaults_to_wordpress_publications(monkeypatch):
    seen = {}
    def fake(**kwargs):
        seen.update(kwargs)
        return {
            "schema": "sc-library-publication-corpus-knowledge-map/1.0",
            "scope": "corpus",
            "title": "Publication Corpus Knowledge Landscape",
            "nodes": [{"id": "wordpress:1:post:1", "kind": "publication", "label": "Paper"}],
            "edges": [],
            "corpus": {"source_key": kwargs.get("source_key")},
            "semantic_analysis": {"available": False},
        }
    monkeypatch.setattr(main, "build_publication_corpus_knowledge_map", fake)
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-knowledge-maps/corpus")
    assert response.status_code == 200
    assert response.json()["scope"] == "corpus"
    assert seen["source_key"] == "wordpress-main"
    assert seen["max_publications"] == 250
    assert seen["include_citations"] is True


def test_publication_corpus_route_passes_analysis_controls(monkeypatch):
    seen = {}
    def fake(**kwargs):
        seen.update(kwargs)
        return {"schema": "sc-library-publication-corpus-knowledge-map/1.0", "scope": "corpus", "nodes": [], "edges": []}
    monkeypatch.setattr(main, "build_publication_corpus_knowledge_map", fake)
    with TestClient(main.app) as client:
        response = client.get("/v1/publication-knowledge-maps/corpus", params={
            "source_key": "wordpress-main",
            "object_type": "post",
            "semantic_threshold": 0.81,
            "max_publications": 125,
            "max_topics_per_publication": 24,
        })
    assert response.status_code == 200
    assert seen["object_type"] == "post"
    assert seen["semantic_threshold"] == 0.81
    assert seen["max_publications"] == 125
    assert seen["max_topics_per_publication"] == 24
