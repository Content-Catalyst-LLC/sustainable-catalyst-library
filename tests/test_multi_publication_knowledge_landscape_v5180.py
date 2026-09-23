from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_and_assets():
    plugin = (ROOT / "sustainable-catalyst-library" / "sustainable-catalyst-library.php").read_text()
    backend = (ROOT / "library-backend" / "app" / "__init__.py").read_text()
    php = (ROOT / "sustainable-catalyst-library" / "includes" / "class-sc-library-knowledge-landscape.php").read_text()
    js = ROOT / "sustainable-catalyst-library" / "assets" / "js" / "sc-library-knowledge-landscape-v5180.js"
    css = ROOT / "sustainable-catalyst-library" / "assets" / "css" / "sc-library-knowledge-landscape-v5180.css"
    assert "Version: 5.18.0" in plugin
    assert '__version__ = "2.29.0"' in backend
    assert "Topic Regions" in php and "Temporal Dynamics" in php and "Relationship Matrix" in php
    assert js.exists() and css.exists()


def test_backend_contract_contains_linked_scientific_views():
    src = (ROOT / "library-backend" / "app" / "publication_corpus_maps.py").read_text()
    for token in [
        'sc-library-publication-corpus-knowledge-map/1.0',
        'publication_relationships',
        'topic_regions',
        'temporal_dynamics',
        'relationship_matrix',
        'four_dimensional_ready',
        'cross-publication-topic-jaccard',
    ]:
        assert token in src
