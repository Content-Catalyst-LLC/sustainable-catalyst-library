from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
MODULE = PLUGIN / 'includes/class-sc-library-biomedical-evidence-graph.php'
BACKEND = ROOT / 'library-backend/app/main.py'
ENGINE = ROOT / 'library-backend/app/biomedical_evidence_graph.py'


def text(path):
    return path.read_text(encoding='utf-8')


def test_release_identity_and_backend_v180():
    assert 'Version: 5.9.0' in text(MAIN)
    assert "SC_LIBRARY_VERSION', '5.9.0'" in text(MAIN)
    assert '__version__ = "1.8.0"' in text(ROOT / 'library-backend/app/__init__.py')


def test_module_shortcode_and_assets_are_wired():
    main = text(MAIN)
    module = text(MODULE)
    assert 'class-sc-library-biomedical-evidence-graph.php' in main
    assert '$biomedical_evidence_graph->register_hooks();' in main
    assert "public const SHORTCODE = 'sc_biomedical_evidence_graph'" in module
    assert 'sc-library-biomedical-evidence-graph-v590.js' in module
    assert 'sc-library-biomedical-evidence-graph-v590.css' in module


def test_backend_routes_and_capabilities_are_present():
    backend = text(BACKEND)
    for route in [
        '/v1/biomedical-evidence-graph',
        '/v1/biomedical-evidence-graph/build',
        '/v1/biomedical-evidence-graph/synthesis',
        '/v1/biomedical-evidence-graph/trial/{nct_id}',
    ]:
        assert route in backend
    for cap in ['biomedical_evidence_graph', 'evidence_synthesis', 'trial_publication_graph_linkage', 'regulatory_evidence_graph_context', 'terminology_candidate_graph_context']:
        assert f'"{cap}"' in backend
    assert '"automated_pooled_effect": False' in backend
    assert '"automated_clinical_recommendation": False' in backend


def test_graph_guardrails_prevent_unsupported_medical_inference():
    engine = text(ENGINE)
    module = text(MODULE)
    for phrase in [
        'semantic_equivalence_asserted', 'causal_relationship_inferred', 'formal_grade_generated',
        'pooled_effect_generated', 'comparative_effectiveness_conclusion_generated', 'clinical_recommendation_generated',
    ]:
        assert phrase in engine
    assert 'does not assert semantic equivalence, causality, pooled effects, comparative effectiveness' in module


def test_explicit_graph_relationships_are_present():
    engine = text(ENGINE)
    for phrase in [
        'retrieved-for-question', 'studies-condition', 'evaluates-intervention', 'measures-outcome',
        'registry-links-publication', 'candidate-concept-for-question', 'regulatory-record-for-question',
    ]:
        assert phrase in engine
    assert 'exact_identifier_match' in engine
    assert 'explicit-pmid-reference' in engine


def test_php_and_js_syntax():
    for path in [MAIN, MODULE]:
        result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stdout + result.stderr
    result = subprocess.run(['node', '--check', str(PLUGIN / 'assets/js/sc-library-biomedical-evidence-graph-v590.js')], capture_output=True, text=True)
    assert result.returncode == 0, result.stdout + result.stderr
