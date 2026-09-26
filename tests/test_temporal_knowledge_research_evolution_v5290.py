from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'; BACKEND=ROOT/'library-backend'
def read(p): return p.read_text(encoding='utf-8')

def test_release_identity():
    assert 'Version: 5.29.0' in read(PLUGIN/'sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '5.29.0');" in read(PLUGIN/'sustainable-catalyst-library.php')
    assert '__version__ = "2.40.0"' in read(BACKEND/'app/__init__.py')

def test_temporal_runtime_and_guardrails():
    m=read(BACKEND/'app/temporal_knowledge.py')
    for token in ('sc-library-temporal-knowledge-evolution/1.0','sc-library-temporal-knowledge-snapshot/1.0','sc-library-temporal-knowledge-change-set/1.0'):
        assert token in m
    for token in ('"later_event_projected_backward_by_default": False','"absence_of_later_evidence_implies_earlier_consensus": False','"temporal_coincidence_implies_causality": False'):
        assert token in m

def test_backend_routes_and_health_capabilities():
    m=read(BACKEND/'app/main.py')
    for route in ('/v1/temporal-knowledge/analyze','/v1/publication-knowledge-maps/temporal-evolution'):
        assert route in m
    for cap in ('"temporal_knowledge_evolution": True','"temporal_historical_availability_snapshots": True','"temporal_retrospective_status_lens": True','"temporal_later_events_projected_backward_by_default": False'):
        assert cap in m

def test_corpus_exposes_temporal_evolution_as_first_class_view():
    m=read(BACKEND/'app/publication_corpus_maps.py')
    assert 'build_temporal_knowledge_evolution' in m
    assert '"temporal_knowledge_evolution": temporal_knowledge_evolution' in m
    assert '"key": "temporal-evolution"' in m

def test_wordpress_console_and_proxy_routes():
    b=read(PLUGIN/'includes/class-sc-library-python-backend.php'); c=read(PLUGIN/'includes/class-sc-library-temporal-evolution.php'); main=read(PLUGIN/'sustainable-catalyst-library.php')
    assert '/backend/temporal-knowledge' in b
    assert '/backend/publication-temporal-evolution' in b
    assert "public const VERSION = '5.29.0';" in c
    assert "public const SHORTCODE = 'sc_library_temporal_evolution';" in c
    assert 'SC_Library_Temporal_Evolution' in main

def test_previous_capabilities_are_preserved():
    assert 'sc-library-retrieval-evaluation/1.0' in read(BACKEND/'app/retrieval_evaluation.py')
    assert 'sc-library-source-identity-resolution/1.0' in read(BACKEND/'app/source_identity_resolution.py')
    assert 'sc-library-scientific-document-intelligence/1.0' in read(BACKEND/'app/scientific_document_intelligence.py')
    assert 'sc-library-research-graph-query/1.0' in read(BACKEND/'app/research_graph_pathfinding.py')
    assert 'sc-library-cross-publication-evidence-synthesis/1.0' in read(BACKEND/'app/evidence_synthesis.py')
