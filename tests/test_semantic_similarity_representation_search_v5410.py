from pathlib import Path
import ast
import json

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.41.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.41.0');" in plugin
    assert '__version__ = "2.52.0"' in read("library-backend/app/__init__.py")


def test_representation_search_module_parses_and_declares_contracts():
    source = read("library-backend/app/representation_search.py")
    ast.parse(source)
    for token in [
        'sc-library-semantic-similarity/1.0',
        'sc-library-representation-search/1.0',
        'sc-library-semantic-representation/1.0',
        'semantic_similarity_is_evidence": False',
        'semantic_similarity_is_truth": False',
        'semantic_similarity_is_causality": False',
        'provider_required_for_seed_search": False',
    ]:
        assert token in source


def test_hybrid_semantic_candidates_require_current_content_and_specification():
    source = read("library-backend/app/hybrid_retrieval.py")
    assert "AND e.content_hash=r.content_hash" in source
    assert "AND e.specification_fingerprint=%s" in source
    assert 'specification_fingerprint=specification["fingerprint_sha256"]' in source
    assert 'semantic_current_specification_only": True' in source
    assert 'semantic_current_content_only": True' in source
    assert 'semantic_similarity_is_truth": False' in source


def test_record_similarity_reuses_stored_vector_without_provider_compute():
    source = read("library-backend/app/representation_search.py")
    start = source.index("def similar_records(")
    similar = source[start:]
    assert "client.embed(" not in similar
    assert "e.specification_fingerprint IS NOT NULL" in similar
    assert "seed_specification_fingerprint" in similar
    assert "AND e.specification_fingerprint=%s" in similar
    assert 'provider_required_for_seed_search": False' in similar


def test_public_backend_routes_exist():
    main = read("library-backend/app/main.py")
    for route in [
        '/v1/semantic-similarity/readiness',
        '/v1/semantic-similarity/search',
        '/v1/semantic-similarity/records/{record_id:path}',
        '/v1/semantic-representations/{record_id:path}',
    ]:
        assert route in main
    for capability in [
        'semantic_similarity_representation_search',
        'semantic_similarity_current_specification_only',
        'semantic_similarity_current_content_only',
        'semantic_record_to_record_without_provider',
        'semantic_similarity_automatic_evidence_promotion',
        'semantic_similarity_automatic_truth_promotion',
        'semantic_similarity_automatic_causality_inference',
    ]:
        assert capability in main


def test_wordpress_proxy_routes_exist():
    source = read("sustainable-catalyst-library/includes/class-sc-library-python-backend.php")
    for route in [
        "/backend/semantic-similarity/readiness",
        "/backend/semantic-similarity/search",
        "/backend/semantic-similarity/record",
        "/backend/semantic-representation",
    ]:
        assert route in source
    assert "proxy_semantic_similarity_search" in source
    assert "proxy_semantic_similarity_record" in source
    assert "proxy_semantic_representation" in source


def test_schema_indexes_support_specification_and_representation_lookup():
    schema = read("library-backend/app/schema.sql")
    assert "library_record_embeddings_specification_idx" in schema
    assert "ON library_record_embeddings(specification_fingerprint,dimensions,updated_at DESC)" in schema
    assert "library_record_embeddings_representation_uidx" in schema
    assert "WHERE representation_id IS NOT NULL" in schema


def test_configuration_exposes_similarity_threshold():
    settings = read("library-backend/app/settings.py")
    env = read("library-backend/.env.example")
    assert 'semantic_min_similarity: float = _as_float("SC_LIBRARY_SEMANTIC_MIN_SIMILARITY", 0.0, -1.0, 1.0)' in settings
    assert "SC_LIBRARY_SEMANTIC_MIN_SIMILARITY=0.0" in env


def test_v541_contract_schemas_parse():
    for name in [
        "semantic-similarity-result.json",
        "representation-search-response.json",
        "semantic-representation-descriptor.json",
    ]:
        data = json.loads(read(f"docs/schemas/{name}"))
        assert data["$schema"].endswith("2020-12/schema")
        assert data["type"] == "object"
