from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def test_release_identity_and_backend_patch_version():
    plugin = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 5.39.0.1" in plugin
    assert "define('SC_LIBRARY_VERSION', '5.39.0.1');" in plugin
    assert '__version__ = "2.50.1"' in read("library-backend/app/__init__.py")


def test_desktop_workspace_is_height_bounded_and_panels_scroll():
    css = read("sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v5270.css")
    assert "@media(min-width:1101px)" in css
    assert "height:var(--sc-kl-height)" in css
    assert "max-height:var(--sc-kl-height)" in css
    assert "overflow-y:auto" in css
    assert "overscroll-behavior:contain" in css
    assert "@media(max-width:1100px)" in css
    assert "height:auto" in css


def test_semantic_overlay_tab_is_inspectable_not_html_disabled():
    php = read("sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php")
    semantic_button = [line for line in php.splitlines() if 'data-sc-kl-view="semantic-overlay"' in line][0]
    assert "disabled(" not in semantic_button
    assert "aria-describedby" in semantic_button
    assert "data-sc-kl-semantic-notice" in php


def test_semantic_hydration_explains_current_vector_availability():
    js = read("sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js")
    assert "Semantic unavailable · ${count} current" in js
    assert "missing_current_embedding_count" in js
    assert "data-sc-kl-semantic-notice" in js
    assert "x.disabled=false" in js
    assert "x.classList.toggle('is-unavailable',!semantic.available)" in js


def test_corpus_backend_reports_current_stale_missing_and_job_diagnostics():
    corpus = read("library-backend/app/publication_corpus_maps.py")
    for token in [
        '"current_embedding_count"',
        '"missing_current_embedding_count"',
        '"stale_embedding_count"',
        '"embedding_job_counts"',
        '"provider_configured"',
        '"worker_enabled"',
        '"embedding-provider-not-configured"',
        '"embedding-jobs-pending"',
        '"stale-embeddings-require-refresh"',
    ]:
        assert token in corpus


def test_global_semantic_readiness_distinguishes_current_from_stale_vectors():
    semantic = read("library-backend/app/semantic.py")
    assert 'result["current_indexed_records"]' in semantic
    assert 'result["stale_indexed_records"]' in semantic
    assert 'result["worker_enabled"]' in semantic


def test_patch_does_not_fake_semantic_edges():
    corpus = read("library-backend/app/publication_corpus_maps.py")
    assert '"embedding-cosine-similarity"' in corpus
    assert 'score = _cosine(' in corpus
    assert 'if score >= semantic_threshold:' in corpus
    assert 'semantic_edges_are_truth_claims' in corpus
