from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def text(rel): return (ROOT/rel).read_text(encoding='utf-8')

def test_release_identity_and_contracts():
    plugin=text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert 'Version: 5.34.0' in plugin
    assert "define('SC_LIBRARY_VERSION', '5.34.0');" in plugin
    assert '__version__ = "2.45.0"' in text('library-backend/app/__init__.py')
    mod=text('library-backend/app/living_evidence.py')
    for contract in ['sc-library-living-evidence/1.0','sc-library-living-evidence-update-candidate/1.0','sc-library-research-evolution/1.0','sc-library-living-review-surveillance/1.0']:
        assert contract in mod

def test_api_wordpress_and_corpus_surfaces():
    main=text('library-backend/app/main.py')
    assert '@app.post("/v1/living-evidence/analyze")' in main
    assert '@app.post("/v1/publication-knowledge-maps/living-evidence")' in main
    proxy=text('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    assert '/backend/living-evidence' in proxy
    assert '/backend/publication-living-evidence' in proxy
    ui=text('sustainable-catalyst-library/includes/class-sc-library-living-evidence.php')
    assert "SHORTCODE = 'sc_library_living_evidence'" in ui
    corpus=text('library-backend/app/publication_corpus_maps.py')
    assert '"key": "living-evidence"' in corpus
    assert 'inspect-living-update-candidate' in corpus

def test_guardrails_and_rust_continuity():
    mod=text('library-backend/app/living_evidence.py')
    for line in [
        '"new_record_is_automatically_included": False',
        '"changed_record_invalidates_prior_review": False',
        '"newer_evidence_is_better_or_truer": False',
        '"source_status_change_automatically_changes_conclusion": False',
        '"automatic_meta_analysis": False',
        '"automatic_consensus_inference": False',
    ]:
        assert line in mod
    assert 'use std::collections' in text('library-backend/native-graph-runtime/src/main.rs').splitlines()[0]
    assert 'library-backend/native-graph-runtime/target/' in text('.gitignore')
