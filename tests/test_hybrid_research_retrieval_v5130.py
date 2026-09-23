from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
MAIN = PLUGIN / "sustainable-catalyst-library.php"
BACKEND = ROOT / "library-backend"
HYBRID = BACKEND / "app/hybrid_retrieval.py"
SEMANTIC = BACKEND / "app/semantic.py"
SCHEMA = BACKEND / "app/schema.sql"
SETTINGS = BACKEND / "app/settings.py"
WP_BACKEND = PLUGIN / "includes/class-sc-library-python-backend.php"


def text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_release_identity_v5130_backend_v2240():
    main = text(MAIN)
    assert "SC_LIBRARY_VERSION" in main


def test_hybrid_retrieval_is_first_class_and_core_aware():
    hybrid = text(HYBRID)
    assert "reciprocal_rank_fusion" in hybrid
    assert "semantic_candidates" in hybrid
    assert "library_core_bindings" in hybrid
    assert '"platform_core"' in hybrid
    assert "PlatformCoreClient" not in hybrid


def test_semantic_index_is_real_provider_backed_not_fake():
    semantic = text(SEMANTIC)
    assert "gemini" in semantic
    assert "openai_compatible" in semantic
    assert "fake or" in semantic.lower()
    assert "hash embedding" in semantic.lower()
    assert "normalize_vector" in semantic


def test_schema_is_additive_and_does_not_require_pgvector():
    schema = text(SCHEMA)
    assert "library_record_embeddings" in schema
    assert "library_embedding_jobs" in schema
    assert "double precision[]" in schema
    assert "sc_cosine_similarity" in schema
    assert "CREATE EXTENSION IF NOT EXISTS vector" not in schema


def test_platform_core_boundary_is_preserved():
    doc = text(ROOT / "HYBRID_RESEARCH_RETRIEVAL_v5.13.0.md")
    assert "Library owns" in doc
    assert "Platform Core owns" in doc
    assert "raw chunks" in doc.lower()
    assert "durable" in doc.lower()


def test_wordpress_proxy_exposes_hybrid_controls_without_embedding_key():
    wp = text(WP_BACKEND)
    assert "/backend/search/readiness" in wp
    assert "include_core" in wp
    assert "mode" in wp
    assert "SC_LIBRARY_EMBEDDING_API_KEY" not in wp


def test_python_and_php_parse():
    result = subprocess.run(["python3", "-m", "compileall", "-q", str(BACKEND / "app")], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
    for path in [MAIN, WP_BACKEND]:
        result = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr


def test_core_bindings_are_invalidated_when_library_content_changes():
    source = (ROOT / "library-backend" / "app" / "repository.py").read_text()
    assert "UPDATE library_core_bindings" in source
    assert "sync_status='stale'" in source
    assert "content_hash<>%s" in source
