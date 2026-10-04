from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text()


def test_backend_contract_and_guardrails():
    source = read("library-backend/app/historical_archive_primary_source.py")
    for marker in [
        'LIBRARY_VERSION = "6.12.0"',
        'BACKEND_VERSION = "3.12.0"',
        'CONTRACT = "sc-library-historical-archive-primary-source-intelligence/1.0"',
        '"primary_source_label_implies_truth": False',
        '"ocr_or_htr_text_is_original_text": False',
        '"translation_is_original_text": False',
        '"date_uncertainty_is_collapsed_to_false_precision": False',
        '"automatic_library_import": False',
        'def normalize_primary_source',
        'def analyze_primary_source',
        'def compare_primary_sources',
        'def plan_archive_search',
        'def build_timeline',
        'def build_primary_source_packet',
        'def ingestion_handoff',
    ]:
        assert marker in source


def test_api_routes():
    source = read("library-backend/app/main.py")
    for route in [
        "/api/library/v1/historical-archives",
        "/api/library/v1/historical-archives/readiness",
        "/api/library/v1/historical-archives/schemas",
        "/api/library/v1/historical-archives/source-types",
        "/api/library/v1/historical-archives/dates/normalize",
        "/api/library/v1/historical-archives/primary-sources/normalize",
        "/api/library/v1/historical-archives/primary-sources/provenance",
        "/api/library/v1/historical-archives/primary-sources/analyze",
        "/api/library/v1/historical-archives/primary-sources/compare",
        "/api/library/v1/historical-archives/search/plan",
        "/api/library/v1/historical-archives/timeline",
        "/api/library/v1/historical-archives/packets",
        "/api/library/v1/historical-archives/handoff",
    ]:
        assert route in source


def test_release_versions_and_wordpress_boundary():
    assert '__version__ = "3.12.0"' in read("library-backend/app/__init__.py")
    assert 'version = "1.12.0"' in read("clients/python/pyproject.toml")
    assert '"version": "1.12.0"' in read("clients/javascript/package.json")
    assert 'webVersion: "2.12.0"' in read("library-web/config.js")
    php = read("sustainable-catalyst-library/sustainable-catalyst-library.php")
    assert "Version: 6.12.0" in php
    assert "SC_LIBRARY_HISTORICAL_ARCHIVE_PRIMARY_SOURCE_AUTHORITY" in php
    assert "SC_LIBRARY_HISTORICAL_ARCHIVE_PRIMARY_SOURCE_WORDPRESS_REQUIRED', false" in php


def test_first_party_clients_expose_historical_archive_api():
    py = read("clients/python/sustainable_catalyst_library/client.py")
    js = read("clients/javascript/src/index.js")
    dts = read("clients/javascript/src/index.d.ts")
    assert "def historical_archives(" in py
    assert "def normalize_primary_source(" in py
    assert "def historical_archive_search_plan(" in py
    assert "historicalArchives()" in js
    assert "normalizePrimarySource(payload)" in js
    assert "historicalArchiveSearchPlan(payload)" in js
    assert "historicalArchives():Promise<any>" in dts
    assert "normalizePrimarySource(payload:Record<string,unknown>):Promise<any>" in dts


def test_release_document_present():
    doc = read("docs/RELEASE_6.12.0_HISTORICAL_ARCHIVE_PRIMARY_SOURCE_INTELLIGENCE.md")
    assert "Historical Archive & Primary-Source Intelligence" in doc
    assert "No database migration is required" in doc
    assert "WordPress remains an optional adapter" in doc
