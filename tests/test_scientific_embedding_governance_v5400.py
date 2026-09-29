from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.40.0" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.40.0');" in plugin
    assert '__version__ = "2.51.0"' in read("library-backend/app/__init__.py")


def test_governance_contract_and_authority_boundary_present():
    source = read("library-backend/app/embedding_governance.py")
    for token in [
        'sc-library-embedding-governance/1.0',
        'sc-core-compatible-embedding-specification/1.0',
        'sc-core-compatible-embedding-representation/1.0',
        'sc-library-workspace-embedding-handoff/1.0',
        'platform_core_owns_governed_representation_contracts',
        'workspace_role": "compute-executor"',
        'embedding_is_evidence": False',
        'similarity_is_truth": False',
        'automatic_platform_core_promotion": False',
    ]:
        assert token in source


def test_existing_semantic_worker_is_retained_as_local_fallback():
    semantic = read("library-backend/app/semantic.py")
    assert "execution_target IN ('local','local-fallback')" in semantic
    assert "representation_metadata(" in semantic
    assert "specification_fingerprint" in semantic
    assert "library-local-embedding-job:" in semantic
    assert "fake_embeddings" in semantic


def test_workspace_handoff_and_backfill_routes_exist():
    main = read("library-backend/app/main.py")
    for route in [
        '/v1/embeddings/specification',
        '/v1/embeddings/governance/readiness',
        '/v1/admin/embeddings/backfill',
        '/v1/admin/embeddings/handoffs/prepare',
        '/v1/admin/embeddings/handoffs/claim',
        '/v1/admin/embeddings/handoffs/{handoff_id}/complete',
        '/v1/admin/embeddings/handoffs/{handoff_id}/fail',
    ]:
        assert route in main
    for capability in [
        'scientific_embedding_governance',
        'embedding_specification_provenance',
        'embedding_representation_lineage',
        'embedding_deterministic_backfill',
        'workspace_embedding_compute_handoff',
        'workspace_embedding_result_ingestion',
        'embedding_automatic_truth_promotion',
    ]:
        assert capability in main


def test_schema_adds_provenance_and_workspace_handoff_without_replacing_vectors():
    schema = read("library-backend/app/schema.sql")
    for token in [
        "ALTER TABLE library_record_embeddings ADD COLUMN IF NOT EXISTS specification_fingerprint",
        "representation_id text",
        "execution_target text NOT NULL DEFAULT 'local'",
        "provenance jsonb NOT NULL DEFAULT '{}'::jsonb",
        "CREATE TABLE IF NOT EXISTS library_embedding_compute_handoffs",
        "workspace_execution_id text",
    ]:
        assert token in schema
    assert "embedding double precision[] NOT NULL" in schema


def test_compute_mode_defaults_to_backward_compatible_local():
    settings = read("library-backend/app/settings.py")
    env = read("library-backend/.env.example")
    assert 'SC_LIBRARY_EMBEDDING_COMPUTE_TARGET", "local"' in settings
    assert "SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=local" in env
    assert "workspace_preferred" in env
    assert "workspace_only" in env


def test_embedding_contract_schemas_parse():
    for name in [
        "embedding-specification.json",
        "embedding-representation.json",
        "embedding-compute-handoff.json",
    ]:
        data = json.loads(read(f"docs/schemas/{name}"))
        assert data["$schema"].endswith("2020-12/schema")
        assert data["type"] == "object"
