from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]


def test_release_identity_and_routes():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    main=(ROOT/'library-backend/app/main.py').read_text()
    proxy=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-python-backend.php').read_text()
    ui=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-research-gap-novelty.php').read_text()
    assert 'Version: 5.31.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.31.0');" in plugin
    assert '@app.post("/v1/research-gap-novelty/analyze")' in main
    assert '@app.post("/v1/publication-knowledge-maps/research-gap-novelty")' in main
    assert '/backend/publication-research-gap-novelty' in proxy
    assert "SHORTCODE = 'sc_library_research_gap_novelty'" in ui


def test_guardrails_are_literal_release_contracts():
    runtime=(ROOT/'library-backend/app/research_gap_novelty.py').read_text()
    assert '"gap_signal_proves_global_absence": False' in runtime
    assert '"novelty_candidate_is_novelty_claim": False' in runtime
    assert '"external_search_required_before_novelty_claim": True' in runtime
    assert '"default_evidence_path": False' in runtime
