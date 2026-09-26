from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def text(rel): return (ROOT/rel).read_text(encoding='utf-8')

def test_release_identity_and_contracts():
    assert 'Version: 5.33.0' in text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert "define('SC_LIBRARY_VERSION', '5.33.0');" in text('sustainable-catalyst-library/sustainable-catalyst-library.php')
    assert '__version__ = "2.44.0"' in text('library-backend/app/__init__.py')
    mod=text('library-backend/app/literature_review.py')
    for contract in ['sc-library-literature-review/1.0','sc-library-review-protocol/1.0','sc-library-review-record-decision/1.0','sc-library-review-extraction/1.0','sc-library-review-snapshot/1.0']:
        assert contract in mod

def test_api_and_wordpress_surfaces():
    main=text('library-backend/app/main.py')
    assert '@app.post("/v1/literature-reviews/build")' in main
    assert '@app.post("/v1/literature-reviews/compare")' in main
    assert '@app.post("/v1/publication-knowledge-maps/literature-review")' in main
    proxy=text('sustainable-catalyst-library/includes/class-sc-library-python-backend.php')
    assert '/backend/literature-review-build' in proxy
    assert '/backend/literature-review-compare' in proxy
    assert '/backend/publication-literature-review' in proxy
    ui=text('sustainable-catalyst-library/includes/class-sc-library-literature-review.php')
    assert "SHORTCODE = 'sc_library_literature_review'" in ui

def test_guardrails_and_rust_continuity():
    mod=text('library-backend/app/literature_review.py')
    assert '"automatic_screening_decisions": False' in mod
    assert '"automatic_inclusion_exclusion": False' in mod
    assert '"automatic_meta_analysis": False' in mod
    assert '"included_record_is_true": False' in mod
    assert '"excluded_record_is_false": False' in mod
    assert '"review_flow_implies_prisma_compliance": False' in mod
    assert 'use std::collections' in text('library-backend/native-graph-runtime/src/main.rs').splitlines()[0]
    assert 'library-backend/native-graph-runtime/target/' in text('.gitignore')
