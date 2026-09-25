from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_is_patch_versioned():
    plugin = (ROOT / 'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    backend = (ROOT / 'library-backend/app/__init__.py').read_text()
    assert 'Version: 5.23.0.1' in plugin
    assert "SC_LIBRARY_VERSION', '5.23.0.1'" in plugin
    assert '__version__ = "2.34.1"' in backend


def test_local_validator_treats_pytest_as_optional():
    validator = (ROOT / 'tests/run_v52301_validation.sh').read_text()
    assert "if python3 -c 'import pytest'" in validator
    assert 'SKIP: pytest is not installed locally; packaged release tests were validated before distribution.' in validator
    assert 'python3 -m compileall' in validator
    assert 'php -l' in validator
    assert 'node --check' in validator


def test_v5230_scientific_behavior_is_preserved():
    overlay = (ROOT / 'library-backend/app/evidence_weighted_overlays.py').read_text()
    corpus = (ROOT / 'library-backend/app/publication_corpus_maps.py').read_text()
    landscape = (ROOT / 'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    assert 'sc-library-evidence-weighted-research-overlay/1.0' in overlay
    assert 'reviewed-explicit-contradiction' in corpus
    assert 'findings-claims' in landscape
    assert 'contradiction-overlay' in landscape
    assert 'sc-library-knowledge-landscape-v5230' in landscape


def test_release_scripts_are_patch_versioned():
    installer = (ROOT / 'install_and_push_sustainable_catalyst_library_v5_23_0_1_macos.sh').read_text()
    deploy = (ROOT / 'upgrade_library_backend_v2_34_1_contabo.sh').read_text()
    assert 'v5.23.0.1' in installer
    assert 'run_v52301_validation.sh' in installer
    assert '2.34.1' in deploy
    assert 'Local Validation Environment Repair' in deploy
