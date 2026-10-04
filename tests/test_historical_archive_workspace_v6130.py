from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text()


def test_backend_workspace_contract_and_guardrails():
    source = read("library-backend/app/historical_archive_workspace.py")
    for marker in [
        'LIBRARY_VERSION = "6.13.0"',
        'BACKEND_VERSION = "3.13.0"',
        'WEB_VERSION = "2.13.0"',
        'CONTRACT = "sc-library-historical-archives-research-workspace/1.0"',
        '"workspace_search_requires_explicit_user_execution": True',
        '"workspace_handoff_is_automatically_executed": False',
        '"automatic_library_import": False',
        '"database_migration_required": False',
        '"wordpress_required": False',
        'def search_workspace',
        'def source_workspace',
        'def compare_workspace',
        'def timeline_workspace',
        'def packet_workspace',
        'def handoff_workspace',
    ]:
        assert marker in source


def test_api_workspace_routes_present():
    source = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/historical-archives/workspace",
        "/api/library/v1/historical-archives/workspace/readiness",
        "/api/library/v1/historical-archives/workspace/bootstrap",
        "/api/library/v1/historical-archives/workspace/search",
        "/api/library/v1/historical-archives/workspace/source",
        "/api/library/v1/historical-archives/workspace/compare",
        "/api/library/v1/historical-archives/workspace/timeline",
        "/api/library/v1/historical-archives/workspace/packet",
        "/api/library/v1/historical-archives/workspace/handoff",
    ]:
        assert route in source


def test_release_generations_are_synchronized():
    assert '__version__ = "3.13.0"' in read("library-backend/app/__init__.py")
    assert 'version = "1.13.0"' in read("clients/python/pyproject.toml")
    assert '"version": "1.13.0"' in read("clients/javascript/package.json")
    assert 'webVersion: "2.13.0"' in read("library-web/config.js")
    for path in [
        "library-backend/app/research_interface.py",
        "library-backend/app/navigation_service.py",
        "library-backend/app/web_application.py",
        "library-backend/app/domain_authority.py",
    ]:
        text = read(path)
        assert "6.13.0" in text
        assert "3.13.0" in text
    php = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.13.0" in php
    assert "SC_LIBRARY_HISTORICAL_ARCHIVES_WORKSPACE_AUTHORITY" in php
    assert "SC_LIBRARY_HISTORICAL_ARCHIVES_WORKSPACE_WORDPRESS_REQUIRED', false" in php


def test_standalone_web_exposes_archives_route():
    index = read("library-web/index.html")
    app = read("library-web/assets/app.js")
    assert 'href="/research/archives"' in index
    assert 'data-view="archives"' in index
    assert 'loadHistoricalArchiveWorkspace' in app
    assert 'view === "archives"' in app
    assert "/historical-archives/workspace/bootstrap" in app
    assert "/historical-archives/workspace/search" in app
    assert "/historical-archives/workspace/source" in app
    assert "/historical-archives/workspace/compare" in app
    assert "/historical-archives/workspace/timeline" in app
    assert "/historical-archives/workspace/packet" in app
    assert "/historical-archives/workspace/handoff" in app


def test_first_party_clients_expose_workspace_api():
    py = read("clients/python/sustainable_catalyst_library/client.py")
    js = read("clients/javascript/src/index.js")
    dts = read("clients/javascript/src/index.d.ts")
    assert "def historical_archives_workspace(" in py
    assert "def historical_archives_workspace_search(" in py
    assert "historicalArchivesWorkspace()" in js
    assert "historicalArchivesWorkspaceSearch(payload)" in js
    assert "historicalArchivesWorkspace():Promise<any>" in dts


def test_release_document_present():
    doc = read("docs/RELEASE_6.13.0_HISTORICAL_ARCHIVES_RESEARCH_WORKSPACE.md")
    assert "Historical Archives Research Workspace" in doc
    assert "No database migration is required" in doc
    assert "WordPress remains an optional adapter" in doc
