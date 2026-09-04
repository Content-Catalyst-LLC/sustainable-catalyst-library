from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'sustainable-catalyst-library'
MAIN = PLUGIN / 'sustainable-catalyst-library.php'
MODULE = PLUGIN / 'includes/class-sc-library-biomedical-evidence-graph.php'
BACKEND_MAIN = ROOT / 'library-backend/app/main.py'
BACKEND_INIT = ROOT / 'library-backend/app/__init__.py'
ENGINE = ROOT / 'library-backend/app/biomedical_evidence_graph.py'


def text(path):
    return path.read_text()


def test_release_identity_v591_backend_v190():
    assert 'Version: 5.9.1' in text(MAIN)
    assert "SC_LIBRARY_VERSION', '5.9.1'" in text(MAIN)
    assert '__version__ = "1.9.0"' in text(BACKEND_INIT)


def test_reliability_route_wired_backend_and_wordpress():
    assert '/v1/biomedical-evidence-graph/reproducibility' in text(BACKEND_MAIN)
    assert '/biomedical-evidence-graph/reproducibility' in text(MODULE)
    assert 'reproducibility_capsule' in text(ENGINE)


def test_graph_module_uses_v591_assets_and_ui_panel():
    module = text(MODULE)
    assert "public const VERSION = '5.9.1'" in module
    assert 'sc-library-biomedical-evidence-graph-v591.js' in module
    assert 'sc-library-biomedical-evidence-graph-v591.css' in module
    assert 'sc-beg__reliability' in module


def test_reliability_contract_disables_title_merge_and_staleness_guessing():
    engine = text(ENGINE)
    assert 'title_only_merge_used' in engine
    assert 'staleness_inferred' in engine
    assert 'deterministic_ordering' in engine
    assert 'graph_content_fingerprint' in engine
    assert 'provenance_ledger' in engine


def test_health_capabilities_expose_reliability():
    main = text(BACKEND_MAIN)
    for capability in ['evidence_graph_reliability', 'graph_provenance_ledger', 'graph_content_fingerprint', 'graph_partial_failure_containment']:
        assert capability in main


def test_php_and_js_syntax():
    for path in [MAIN, MODULE]:
        result = subprocess.run(['php', '-l', str(path)], capture_output=True, text=True)
        assert result.returncode == 0, result.stderr + result.stdout
    js = PLUGIN / 'assets/js/sc-library-biomedical-evidence-graph-v591.js'
    result = subprocess.run(['node', '--check', str(js)], capture_output=True, text=True)
    assert result.returncode == 0, result.stderr + result.stdout
