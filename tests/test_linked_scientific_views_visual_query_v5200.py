from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def test_release_identity_and_linked_visual_query_assets():
    plugin=(ROOT/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    backend=(ROOT/"library-backend/app/__init__.py").read_text()
    php=(ROOT/"sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    js=(ROOT/"sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5200.js").read_text()
    css=(ROOT/"sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5200.css").read_text()
    corpus=(ROOT/"library-backend/app/publication_corpus_maps.py").read_text()
    main=(ROOT/"library-backend/app/main.py").read_text()
    assert "Version: 5.20." in plugin
    assert '__version__ = "2.31.' in backend
    assert "Linked Scientific Views" in php or "Linked scientific" in php
    assert "sc-library-linked-visual-query/1.0" in corpus
    for token in ["cross_view_selection","portable_query_state","terrain_peak_selection","matrix_cell_selection","time_crossfilter"]:
        assert token in corpus
    for token in ["querySnapshot","terrainPick","selectRegion","data-sc-kl-query-text","data-sc-kl-query-mode"]:
        assert token in js or token in php
    assert ".sc-kl__query" in css
    assert '"publication_visual_query_contract": True' in main

def test_visual_query_integrity_boundaries_are_explicit():
    corpus=(ROOT/"library-backend/app/publication_corpus_maps.py").read_text()
    assert '"query_creates_new_research_claims":False' in corpus
    assert '"selection_is_research_conclusion":False' in corpus
    assert '"neighbor_highlight_implies_causality":False' in corpus
