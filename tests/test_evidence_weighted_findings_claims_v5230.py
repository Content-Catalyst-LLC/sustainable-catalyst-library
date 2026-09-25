from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_contract():
    plugin=(ROOT/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
    mod=(ROOT/'library-backend/app/evidence_weighted_overlays.py').read_text()
    assert 'Version: 5.23.' in plugin
    assert 'sc-library-evidence-weighted-research-overlay/1.0' in mod
    assert 'epistemic_truth_score":False' in mod.replace(' ', '')

def test_corpus_integrates_reviewed_overlays():
    corpus=(ROOT/'library-backend/app/publication_corpus_maps.py').read_text()
    assert 'build_evidence_weighted_overlays(records)' in corpus
    assert 'reviewed-finding-evidence' in corpus
    assert 'reviewed-claim-evidence' in corpus
    assert 'reviewed-explicit-contradiction' in corpus
    assert 'contradiction_requires_explicit_reviewed_relation' in corpus

def test_interface_exposes_research_overlay_views():
    php=(ROOT/'sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php').read_text()
    js=(ROOT/'sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5230.js').read_text()
    assert 'findings-claims' in php and 'contradiction-overlay' in php
    assert 'Reviewed findings' in php and 'Accepted claims' in php
    assert 'reviewed-explicit-contradiction' in js
