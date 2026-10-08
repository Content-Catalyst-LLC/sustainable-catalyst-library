from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def test_release_generations():
    assert '__version__ = "4.0.0"' in (ROOT/"library-backend/app/__init__.py").read_text()
    assert 'webVersion: "3.0.0"' in (ROOT/"library-web/config.js").read_text()
    assert '__version__ = "2.0.0"' in (ROOT/"clients/python/sustainable_catalyst_library/__init__.py").read_text()
    assert '"version": "2.0.0"' in (ROOT/"clients/javascript/package.json").read_text()

def test_platform_backend_surface_present():
    main = (ROOT/"library-backend/app/main.py").read_text()
    assert 'from .independent_knowledge_library_platform import (' in main
    assert '@app.get("/api/library/v1/platform")' in main
    assert '@app.get("/api/library/v1/platform/readiness")' in main
    assert '@app.get("/api/library/v1/platform/release")' in main
    assert '@app.post("/api/library/v1/platform/evaluate")' in main
    assert '@app.post("/api/library/v1/platform/export")' in main

def test_web_system_surface_present():
    app = (ROOT/"library-web/assets/app.js").read_text()
    assert "['Library 7 platform','/platform/readiness']" in app
    assert "['6.39 promotion gate','/library7-certification/readiness']" in app

def test_wordpress_is_optional_adapter():
    php = (ROOT/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
    assert "Version: 7.0.0" in php
    assert "define('SC_LIBRARY_VERSION', '7.0.0');" in php
    assert "define('SC_LIBRARY_WORDPRESS_AUTHORITATIVE', false);" in php
    assert "define('SC_LIBRARY_PLATFORM_WORDPRESS_REQUIRED', false);" in php

def test_predecessor_surfaces_preserved():
    main = (ROOT/"library-backend/app/main.py").read_text()
    assert "/api/library/v1/library7-certification/readiness" in main
    assert "/api/library/v1/research-audit/readiness" in main
    assert "/api/library/v1/research-federation/readiness" in main


def test_api_v1_catalog_and_client_framework_promoted():
    api = (ROOT/"library-backend/app/independent_api.py").read_text()
    client = (ROOT/"library-backend/app/client_framework.py").read_text()
    assert '"library_version": "7.0.0"' in api
    assert '"backend_version": "4.0.0"' in api
    assert '"/api/library/v1/platform"' in api
    assert 'LIBRARY_VERSION = "7.0.0"' in client
    assert 'BACKEND_VERSION = "4.0.0"' in client
    assert 'SDK_VERSION = "2.0.0"' in client
    assert '"independent_knowledge_library_platform_client": True' in client
