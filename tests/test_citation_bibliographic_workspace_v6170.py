from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def text(path): return (ROOT/path).read_text()

def test_generation_markers():
    assert '__version__ = "3.17.0"' in text('library-backend/app/__init__.py')
    assert 'webVersion: "2.17.0"' in text('library-web/config.js')
    assert 'Web v2.17.0 · API v1' in text('library-web/index.html')
    assert 'version = "1.17.0"' in text('clients/python/pyproject.toml')
    assert '"version": "1.17.0"' in text('clients/javascript/package.json')

def test_workspace_routes_and_next_release():
    assert '/api/library/v1/citations/workspace/readiness' in text('library-backend/app/main.py')
    assert '/research/citations' in text('library-web/index.html')
    assert 'Citation Workspace & Bibliographic Intelligence' in text('library-web/index.html')
    assert '"next_release": "6.18.0"' in text('library-backend/app/navigation_service.py')
    assert 'Corpus & Computational Linguistics Workspace' in text('library-backend/app/navigation_service.py')

def test_non_authority_markers():
    m=text('library-backend/app/citation_bibliographic_workspace.py')
    assert 'citation_count_implies_quality": False' in m
    assert 'duplicate_candidates_are_auto_merged": False' in m
    assert 'workspace_creates_durable_citation_edges_automatically": False' in m
    assert 'database_migration_required": False' in m
