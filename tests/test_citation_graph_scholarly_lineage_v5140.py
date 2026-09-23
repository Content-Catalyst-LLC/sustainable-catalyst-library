from pathlib import Path
import subprocess

ROOT=Path(__file__).resolve().parents[1]
BACKEND=ROOT/'library-backend'
PLUGIN=ROOT/'sustainable-catalyst-library'


def t(path): return path.read_text(encoding='utf-8')


def test_identity():
    assert 'Version: 5.15.0' in t(PLUGIN/'sustainable-catalyst-library.php')
    assert "SC_LIBRARY_VERSION', '5.15.0'" in t(PLUGIN/'sustainable-catalyst-library.php')
    assert '__version__ = "2.26.0"' in t(BACKEND/'app/__init__.py')
    assert 'Stable tag: 5.15.0' in t(PLUGIN/'readme.txt')


def test_citation_graph_is_library_owned_but_core_governed_lineage_is_not_duplicated():
    src=t(BACKEND/'app/citation_graph.py')
    assert 'library_citations' in src
    assert 'exact' in src.lower()
    assert 'llm_inferred_citations' in src
    assert 'platform_core_owns_governed_lineage' in src
    assert 'scholarly-citation.create' in src


def test_schema_is_additive_and_preserves_hybrid_and_core_tables():
    schema=t(BACKEND/'app/schema.sql')
    for table in ['library_citations','library_record_embeddings','library_embedding_jobs','library_core_bindings','library_core_sync_outbox']:
        assert f'CREATE TABLE IF NOT EXISTS {table}' in schema
    assert 'ON DELETE SET NULL' in schema


def test_platform_core_v330_contract_is_consumed():
    core=t(BACKEND/'app/platform_core.py')
    assert '"research_lineage": "/v1/research/lineage/readiness"' in core
    assert '"scholarly_interoperability": "/v1/research/scholarly-packages/readiness"' in core
    assert '"scholarly-citation.create": ("POST", "/v1/research/scholarly-packages/citations")' in core


def test_api_and_wordpress_readiness_contracts_are_wired():
    main=t(BACKEND/'app/main.py')
    wp=t(PLUGIN/'includes/class-sc-library-python-backend.php')
    for route in ['/v1/citations/readiness','/v1/citations/{record_id:path}','/v1/citations/{record_id:path}/graph','/v1/citations/{record_id:path}/import-metadata','/v1/citations/core-handoff']:
        assert route in main
    assert '/backend/citations/readiness' in wp
    assert 'citation_readiness' in wp


def test_no_new_frontend_secrets_or_auto_truth_promotion():
    wp=t(PLUGIN/'includes/class-sc-library-python-backend.php')
    src=t(BACKEND/'app/citation_graph.py')
    assert 'SC_LIBRARY_PLATFORM_CORE_WRITE_API_KEY' not in wp
    assert 'automatic_claim_promotion' not in src or 'False' in src
    assert 'llm_inferred_citations": False' in src


def test_python_compiles():
    r=subprocess.run(['python3','-m','compileall','-q',str(BACKEND/'app')],capture_output=True,text=True)
    assert r.returncode==0, r.stdout+r.stderr
