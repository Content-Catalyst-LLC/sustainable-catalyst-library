from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'
BACKEND=ROOT/'library-backend'
def read(p): return p.read_text(encoding='utf-8')

def test_release_identity():
    assert 'Version: 5.28.0' in read(PLUGIN/'sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '5.28.0');" in read(PLUGIN/'sustainable-catalyst-library.php')
    assert '__version__ = "2.39.0"' in read(BACKEND/'app/__init__.py')

def test_retrieval_evaluation_runtime_and_guardrails():
    m=read(BACKEND/'app/retrieval_evaluation.py')
    for token in ('sc-library-retrieval-evaluation/1.0','sc-library-adaptive-ranking-profile/1.0','sc-library-adaptive-reranking/1.0'):
        assert token in m
    for token in ('"automatic_record_filtering": False','"automatic_truth_promotion": False','"original_rank_preserved": True','"result_set_preserved": True'):
        assert token in m

def test_backend_routes_and_health_capabilities():
    m=read(BACKEND/'app/main.py')
    for route in ('/v1/retrieval-evaluation/evaluate','/v1/retrieval-evaluation/profile','/v1/retrieval-evaluation/rerank','/v1/search/adaptive'):
        assert route in m
    for cap in ('"retrieval_evaluation": True','"adaptive_ranking_profiles": True','"adaptive_ranking_automatic_filtering": False','"adaptive_ranking_truth_promotion": False'):
        assert cap in m

def test_wordpress_console_and_proxy_routes():
    b=read(PLUGIN/'includes/class-sc-library-python-backend.php')
    c=read(PLUGIN/'includes/class-sc-library-retrieval-evaluation.php')
    main=read(PLUGIN/'sustainable-catalyst-library.php')
    for route in ('/backend/retrieval-evaluation','/backend/retrieval-adaptive-profile','/backend/retrieval-rerank','/backend/search/adaptive'):
        assert route in b
    assert "public const VERSION = '5.28.0';" in c
    assert "public const SHORTCODE = 'sc_library_retrieval_evaluation';" in c
    assert 'SC_Library_Retrieval_Evaluation' in main

def test_previous_capabilities_are_preserved():
    assert 'sc-library-source-identity-resolution/1.0' in read(BACKEND/'app/source_identity_resolution.py')
    assert 'sc-library-scientific-document-intelligence/1.0' in read(BACKEND/'app/scientific_document_intelligence.py')
    assert 'sc-library-research-graph-query/1.0' in read(BACKEND/'app/research_graph_pathfinding.py')
    assert 'sc-library-evidence-pathfinding/1.0' in read(BACKEND/'app/research_graph_pathfinding.py')
    s=read(BACKEND/'app/evidence_synthesis.py')
    assert '"durable_synthesis_authority": "platform-core"' in s
