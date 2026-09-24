from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def test_release_identity_v51712():
    plugin = (ROOT / "sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    readme = (ROOT / "sustainable-catalyst-library/readme.txt").read_text()
    backend = (ROOT / "library-backend/app/__init__.py").read_text()
    assert "SC_LIBRARY_VERSION" in plugin
    assert "Stable tag:" in readme
    assert "__version__" in backend


def test_large_corpus_json_is_not_passed_through_argv():
    deploy = (ROOT / "upgrade_library_backend_v2_28_3_contabo.sh").read_text()
    assert '-o "$TMP/corpus.json"' in deploy
    assert '< "$TMP/corpus.json"' in deploy
    assert '"$corpus"' not in deploy
    assert "json.load(sys.stdin)" in deploy


def test_manifest_and_regressions_parse_from_files_or_stdin():
    deploy = (ROOT / "upgrade_library_backend_v2_28_3_contabo.sh").read_text()
    assert '"$TMP/manifest.json"' in deploy
    assert '< "$TMP/manifest.json"' in deploy
    assert '"$TMP/single.json"' in deploy
    assert '"$TMP/core.json"' in deploy
    assert '"$TMP/search.json"' in deploy


def test_canonical_scope_contract_is_unchanged():
    engine = (ROOT / "library-backend/app/publication_corpus_maps.py").read_text()
    shortcode = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php").read_text()
    assert 'selection_mode = "publication-library-manifest"' in engine
    assert 'selection_mode = "wordpress-post-fallback"' in engine
    assert 'object_type = object_type or "post"' in engine
    bridge = (ROOT / "sustainable-catalyst-library/includes/class-sc-library-python-backend.php").read_text()
    assert "publication_record_ids($max_publications)" in bridge
    assert "data-sc-kl-async" in shortcode
