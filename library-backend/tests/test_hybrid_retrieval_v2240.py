from __future__ import annotations

import json
from pathlib import Path
import sys
import types

import httpx
import pytest

# Build-environment shim: production installs psycopg/psycopg-pool from requirements.txt.
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

from app.retrieval_fusion import reciprocal_rank_fusion
from app.semantic import EmbeddingClient, EmbeddingError, embedding_input_from_record


def test_rrf_rewards_records_supported_by_both_rankings():
    lexical = [
        {"record_id": "a", "score": 0.9, "title": "A"},
        {"record_id": "b", "score": 0.7, "title": "B"},
    ]
    semantic = [
        {"record_id": "b", "semantic_score": 0.95, "title": "B"},
        {"record_id": "c", "semantic_score": 0.90, "title": "C"},
    ]
    fused = reciprocal_rank_fusion(lexical, semantic, rrf_k=60)
    assert [row["record_id"] for row in fused] == ["b", "a", "c"]
    assert fused[0]["retrieval_signals"]["lexical_rank"] == 2
    assert fused[0]["retrieval_signals"]["semantic_rank"] == 1
    assert fused[0]["hybrid_score"] > fused[1]["hybrid_score"]


def test_rrf_does_not_mix_raw_lexical_and_semantic_scores():
    lexical = [{"record_id": "a", "score": 999999.0}, {"record_id": "b", "score": 0.000001}]
    semantic = [{"record_id": "b", "semantic_score": 0.51}, {"record_id": "a", "semantic_score": 0.50}]
    fused = reciprocal_rank_fusion(lexical, semantic, rrf_k=60)
    # Equal opposing ranks yield equal RRF scores regardless of incompatible raw scales.
    assert fused[0]["hybrid_score"] == fused[1]["hybrid_score"]


def _gemini_transport(request: httpx.Request) -> httpx.Response:
    assert request.method == "POST"
    assert request.url.path.endswith("/models/gemini-embedding-2:embedContent")
    assert request.headers.get("x-goog-api-key") == "gemini-secret"
    body = json.loads(request.content.decode("utf-8"))
    assert body["output_dimensionality"] == 768
    assert body["content"]["parts"][0]["text"] == "grid storage degradation"
    return httpx.Response(200, json={"embedding": {"values": [3.0, 4.0]}})


def test_gemini_adapter_uses_real_provider_shape_and_normalizes_vector():
    client = EmbeddingClient(
        provider="gemini",
        api_key="gemini-secret",
        model="gemini-embedding-2",
        dimensions=768,
        transport=httpx.MockTransport(_gemini_transport),
    )
    result = client.embed("grid storage degradation")
    assert result.provider == "gemini"
    assert result.model == "gemini-embedding-2"
    assert result.values == pytest.approx([0.6, 0.8])


def _openai_transport(request: httpx.Request) -> httpx.Response:
    assert request.headers.get("Authorization") == "Bearer compat-secret"
    body = json.loads(request.content.decode("utf-8"))
    assert body["model"] == "embed-model"
    return httpx.Response(200, json={"data": [{"embedding": [0.0, 5.0]}]})


def test_openai_compatible_adapter_is_optional_and_normalized():
    client = EmbeddingClient(
        provider="openai_compatible",
        api_key="compat-secret",
        model="embed-model",
        api_url="https://embeddings.example.test/v1/embeddings",
        dimensions=768,
        transport=httpx.MockTransport(_openai_transport),
    )
    result = client.embed("research question")
    assert result.values == pytest.approx([0.0, 1.0])


def test_disabled_provider_never_generates_fake_embeddings():
    client = EmbeddingClient(provider="disabled")
    assert client.configured is False
    with pytest.raises(EmbeddingError):
        client.embed("do not hash this into a fake vector")


def test_embedding_input_is_bounded_and_source_near():
    text = embedding_input_from_record({
        "title": "Battery Storage",
        "abstract": "Degradation under cycling.",
        "topics": ["energy storage"],
        "tags": ["battery"],
        "body_text": "A" * 20000,
    })
    assert "Title: Battery Storage" in text
    assert "Topics: energy storage" in text
    assert len(text) <= 12000


def test_schema_adds_library_owned_vector_store_and_queue():
    repo = Path(__file__).resolve().parents[2]
    schema = (repo / "library-backend/app/schema.sql").read_text()
    assert "CREATE OR REPLACE FUNCTION sc_cosine_similarity" in schema
    assert "CREATE TABLE IF NOT EXISTS library_record_embeddings" in schema
    assert "embedding double precision[] NOT NULL" in schema
    assert "CREATE TABLE IF NOT EXISTS library_embedding_jobs" in schema
    assert "FOR UPDATE SKIP LOCKED" in (repo / "library-backend/app/semantic.py").read_text()


def test_changed_records_stale_mismatched_core_bindings():
    source = (Path(__file__).resolve().parents[1] / "app" / "repository.py").read_text()
    assert "UPDATE library_core_bindings" in source
    assert "sync_status='stale'" in source
    assert "content_hash<>%s" in source


def test_core_aware_results_use_durable_bindings_not_live_core_calls():
    repo = Path(__file__).resolve().parents[2]
    hybrid = (repo / "library-backend/app/hybrid_retrieval.py").read_text()
    assert "library_core_bindings" in hybrid
    assert "platform_core" in hybrid
    assert "PlatformCoreClient" not in hybrid
    assert "/v1/platform-core" not in hybrid


def test_search_contract_reports_explicit_degradation_and_modes():
    repo = Path(__file__).resolve().parents[2]
    hybrid = (repo / "library-backend/app/hybrid_retrieval.py").read_text()
    main = (repo / "library-backend/app/main.py").read_text()
    assert 'MODES = {"hybrid", "lexical", "semantic"}' in hybrid
    assert 'effective_mode = "lexical-fallback"' in hybrid
    assert 'fusion": "weighted-reciprocal-rank-fusion"' in hybrid
    assert '@app.get("/v1/search/readiness")' in main
    assert '@app.post("/v1/admin/embeddings/run-once")' in main


def test_public_semantic_queue_does_not_index_non_public_records():
    repo = Path(__file__).resolve().parents[2]
    repository = (repo / "library-backend/app/repository.py").read_text()
    assert 'record.visibility == "public" and record.publication_status == "published"' in repository
    assert 'DELETE FROM library_record_embeddings WHERE record_id=%s' in repository


def test_release_identity_is_backend_v2240():
    repo = Path(__file__).resolve().parents[2]
    assert '__version__ = "2.24.0"' in (repo / "library-backend/app/__init__.py").read_text()


def test_platform_core_v330_bridge_contracts_remain_intact():
    repo = Path(__file__).resolve().parents[2]
    core = (repo / "library-backend/app/platform_core.py").read_text()
    schema = (repo / "library-backend/app/schema.sql").read_text()
    for route in [
        "/v1/research-objects/readiness",
        "/v1/research/lineage/readiness",
        "/v1/research/intelligence/readiness",
        "/v1/exchange/readiness",
        "/v1/research/scholarly-packages/readiness",
        "/v1/research/runtime-contract/readiness",
        "/v1/visual-runtime/unified/readiness",
        "/v1/analytics/statistical-reasoning/readiness",
    ]:
        assert route in core
    assert "CREATE TABLE IF NOT EXISTS library_core_bindings" in schema
    assert "CREATE TABLE IF NOT EXISTS library_core_sync_outbox" in schema
    assert '"automatic_truth_promotion": False' in core
    assert '"raw_chunks_remain_library_local": True' in core
