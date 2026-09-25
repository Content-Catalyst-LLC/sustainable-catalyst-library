from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_and_backend_contract():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    backend = (ROOT / "library-backend/app/__init__.py").read_text()
    synthesis = (ROOT / "library-backend/app/evidence_synthesis.py").read_text()
    assert "Version: 5.24.0" in plugin
    assert '__version__ = "2.35.' in backend
    assert "sc-library-cross-publication-evidence-synthesis/1.0" in synthesis
    assert '"consensus_inferred": False' in synthesis
    assert '"hypotheses_inferred": False' in synthesis
    assert '"durable_synthesis_authority": "platform-core"' in synthesis


def test_corpus_integrates_synthesis_without_automatic_truth_or_hypothesis_inference():
    corpus = (ROOT / "library-backend/app/publication_corpus_maps.py").read_text()
    assert "build_cross_publication_synthesis(research_overlays, records)" in corpus
    assert '"evidence_synthesis": evidence_synthesis' in corpus
    assert "explicit-hypothesis-membership" in corpus
    assert '"consensus_inferred": False' in corpus
    assert '"hypotheses_inferred": False' in corpus
    assert '"durable_cross_study_synthesis_authority": "platform-core"' in corpus


def test_interface_exposes_synthesis_and_explicit_hypothesis_views():
    php = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    js = (ROOT / "sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5240.js").read_text()
    css = (ROOT / "sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5240.css").read_text()
    assert 'data-sc-kl-view="evidence-synthesis"' in php
    assert 'data-sc-kl-view="competing-hypotheses"' in php
    assert 'data-sc-kl-node-kind="hypothesis"' in php
    assert "explicit-hypothesis-membership" in js
    assert "competing-hypotheses" in js
    assert 'data-kind="hypothesis"' in css
