from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_v51711_scope_contract_remains_present():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    readme = (ROOT / "sustainable-catalyst-library/readme.txt").read_text()
    assert "Version: 5." in plugin
    assert "canonical Publication Library" in readme


def test_publications_class_exports_canonical_manifest():
    code = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-publications.php").read_text()
    assert "publication_post_ids" in code
    assert "publication_record_ids" in code
    assert "$this->topics()" in code
    assert "$topic['articles']" in code


def test_shortcode_passes_publications_manifest_to_backend():
    code = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    bridge = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    assert "publication_record_ids($max_publications)" in bridge
    assert "data-sc-kl-async" in code
    assert "publication-corpus-knowledge-map" in code
    assert "Publication Library manifest" in code


def test_backend_manifest_filter_and_safe_fallback_are_present():
    engine = (ROOT / "library-backend/app/publication_corpus_maps.py").read_text()
    main = (ROOT / "library-backend/app/main.py").read_text()
    assert 'selection_mode = "publication-library-manifest"' in engine
    assert 'selection_mode = "wordpress-post-fallback"' in engine
    assert 'object_type = object_type or "post"' in engine
    assert 'record_id=ANY(%s)' in engine
    assert 'record_ids: str = ""' in main


def test_deployment_contract_uses_bounded_summary_not_head_pipeline():
    candidates = [ROOT / "upgrade_library_backend_v2_28_3_contabo.sh", ROOT / "upgrade_library_backend_v2_28_2_contabo.sh"]
    deploy = next(p for p in candidates if p.exists()).read_text()
    assert "CORPUS SUMMARY" in deploy
    assert "python3 -m json.tool | head" not in deploy
