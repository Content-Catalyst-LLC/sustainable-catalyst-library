from __future__ import annotations

import sys
import types

import pytest

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

from app.publication_knowledge_maps import _cosine, _topic_id, _add_edge


def test_cosine_similarity_is_real_vector_math_not_label_guessing():
    assert _cosine([1.0, 0.0], [1.0, 0.0]) == pytest.approx(1.0)
    assert _cosine([1.0, 0.0], [0.0, 1.0]) == pytest.approx(0.0)
    assert _cosine([], [1.0]) == 0.0


def test_topic_ids_are_stable_and_case_normalized():
    assert _topic_id("  Climate   Adaptation ") == _topic_id("climate adaptation")
    assert _topic_id("climate adaptation") != _topic_id("energy systems")


def test_undirected_edges_canonicalize_and_accumulate_measured_weight():
    edges = {}
    _add_edge(edges, "topic:b", "topic:a", "source-span-cooccurrence", directed=False, weight=1.0, evidence_count=1)
    _add_edge(edges, "topic:a", "topic:b", "source-span-cooccurrence", directed=False, weight=1.0, evidence_count=1)
    assert len(edges) == 1
    edge = next(iter(edges.values()))
    assert edge["source"] == "topic:a"
    assert edge["target"] == "topic:b"
    assert edge["weight"] == pytest.approx(2.0)
    assert edge["evidence_count"] == 2
