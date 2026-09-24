from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_4d_assets():
    plugin=(ROOT/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    backend=(ROOT/"library-backend/app/__init__.py").read_text()
    php=(ROOT/"sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    js=(ROOT/"sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5190.js").read_text()
    css=(ROOT/"sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5190.css").read_text()
    corpus=(ROOT/"library-backend/app/publication_corpus_maps.py").read_text()
    assert "Version:" in plugin
    assert "__version__" in backend
    assert "4D Knowledge Terrain" in php
    for token in ["knowledge_terrain_4d","sc-library-4d-knowledge-terrain/1.0","temporal_keyframes","elevation_metrics"]: assert token in corpus
    for token in ["drawTerrain","terrainPlay","data-sc-kl-elevation","knowledge-terrain-4d"]: assert token in js or token in php
    assert ".sc-kl__terrain" in css

def test_research_integrity_boundaries_remain_explicit():
    corpus=(ROOT/"library-backend/app/publication_corpus_maps.py").read_text()
    assert '"terrain_height_is_evidence_of_truth":False' in corpus
    assert '"spatial_proximity_is_causality":False' in corpus
