from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'sustainable-catalyst-library'; BACKEND=ROOT/'library-backend'
def read(p): return p.read_text(encoding='utf-8')


def test_release_identity():
    assert 'Version: 5.30.0' in read(PLUGIN/'sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '5.30.0');" in read(PLUGIN/'sustainable-catalyst-library.php')
    assert '__version__ = "2.41.0"' in read(BACKEND/'app/__init__.py')


def test_methodology_runtime_and_guardrails():
    m=read(BACKEND/'app/methodology_intelligence.py')
    for token in ('sc-library-methodology-intelligence/1.0','sc-library-methodology-profile/1.0','sc-library-methodology-comparison/1.0'):
        assert token in m
    for token in ('"automatic_quality_score": False','"automatic_risk_of_bias_judgment": False','"higher_reporting_coverage_means_higher_quality": False'):
        assert token in m


def test_backend_routes_and_health_capabilities():
    m=read(BACKEND/'app/main.py')
    for route in ('/v1/methodology-intelligence/analyze','/v1/publication-knowledge-maps/methodology-intelligence'):
        assert route in m
    for cap in ('"methodology_intelligence": True','"methodology_reporting_coverage": True','"methodology_automatic_quality_score": False','"methodology_automatic_risk_of_bias_judgment": False'):
        assert cap in m


def test_corpus_exposes_methodology_as_first_class_view():
    m=read(BACKEND/'app/publication_corpus_maps.py')
    assert 'build_methodology_intelligence' in m
    assert '"methodology_intelligence": methodology_intelligence' in m
    assert '"key": "methodology-intelligence"' in m
    assert '"method_reporting_coverage_is_quality_score": False' in m


def test_graph_keeps_methodology_out_of_default_evidence_path():
    m=read(BACKEND/'app/research_graph_pathfinding.py')
    assert '"describes-methodology": "explicit-methodology-description"' in m
    default_block=m.split('DEFAULT_TRACE_RELATIONSHIPS = {',1)[1].split('}',1)[0]
    assert 'describes-methodology' not in default_block
    assert '"methodology_description_edges_are_default_evidence_paths": False' in m


def test_wordpress_console_and_proxy_routes():
    b=read(PLUGIN/'includes/class-sc-library-python-backend.php')
    c=read(PLUGIN/'includes/class-sc-library-methodology-intelligence.php')
    main=read(PLUGIN/'sustainable-catalyst-library.php')
    assert '/backend/methodology-intelligence' in b
    assert '/backend/publication-methodology-intelligence' in b
    assert "public const VERSION = '5.30.0';" in c
    assert "public const SHORTCODE = 'sc_library_methodology_intelligence';" in c
    assert 'SC_Library_Methodology_Intelligence' in main


def test_previous_capabilities_are_preserved():
    assert 'sc-library-temporal-knowledge-evolution/1.0' in read(BACKEND/'app/temporal_knowledge.py')
    assert 'sc-library-retrieval-evaluation/1.0' in read(BACKEND/'app/retrieval_evaluation.py')
    assert 'sc-library-source-identity-resolution/1.0' in read(BACKEND/'app/source_identity_resolution.py')
    assert 'sc-library-scientific-document-intelligence/1.0' in read(BACKEND/'app/scientific_document_intelligence.py')
    assert 'sc-library-research-graph-query/1.0' in read(BACKEND/'app/research_graph_pathfinding.py')
    assert 'sc-library-cross-publication-evidence-synthesis/1.0' in read(BACKEND/'app/evidence_synthesis.py')
