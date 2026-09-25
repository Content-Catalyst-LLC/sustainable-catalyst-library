from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]


def test_release_identity_and_assets():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    landscape=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    assert 'Version: 5.22.0' in plugin
    assert "public const VERSION = '5.22.0'" in landscape
    assert 'sc-library-knowledge-landscape-v5220' in landscape


def test_wordpress_evidence_trace_proxy_and_ui():
    backend=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-python-backend.php').read_text()
    landscape=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    js=(ROOT/'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5220.js').read_text()
    assert '/backend/publication-visual-evidence-trace' in backend
    assert 'proxy_publication_visual_evidence_trace' in backend
    assert '/v1/publication-knowledge-maps/evidence-trace' in backend
    assert 'Trace selection to sources' in landscape
    assert 'data-sc-kl-trace-results' in landscape
    assert 'evidenceTraceEndpoint' in js
    assert 'renderEvidenceTrace' in js


def test_backend_trace_contract_and_integrity_boundaries():
    mod=(ROOT/'library-backend/app/visual_evidence_trace.py').read_text()
    main=(ROOT/'library-backend/app/main.py').read_text()
    assert 'sc-library-visual-evidence-trace/1.0' in mod
    assert 'trace_creates_new_claims": False' in mod
    assert 'relationship_implies_causality": False' in mod
    assert 'library_record_chunks' in mod
    assert 'library_citations' in mod
    assert 'library_research_candidates' in mod
    assert '@app.post("/v1/publication-knowledge-maps/evidence-trace")' in main
    assert 'publication_visual_evidence_trace' in main
