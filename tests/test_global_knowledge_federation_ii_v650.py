#!/usr/bin/env python3
from __future__ import annotations

import ast
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


module = text("library-backend/app/global_knowledge_federation_ii.py")
ast.parse(module)
require('LIBRARY_VERSION = "6.5.0"' in module, "federation II Library version")
require('BACKEND_VERSION = "3.5.0"' in module, "federation II backend version")
for fn in ("contract", "lenses", "source_profile", "plan", "discover", "readiness"):
    require(f"def {fn}(" in module, f"missing {fn}")
for flag in (
    '"original_language_is_canonical": True',
    '"translation_is_derived_representation": True',
    '"source_quality_signals_separate_from_user_trust_choices": True',
    '"automatic_external_fetch": False',
    '"automatic_import": False',
    '"automatic_truth_promotion": False',
):
    require(flag in module, flag)
for source_id in (
    "doaj", "zenodo", "openaire", "europeana", "hal", "scielo", "la-referencia",
    "cinii", "jstage", "ndl-search", "cnki", "cyberleninka", "elibrary-ru",
    "sid-iran", "irandoc", "dergipark", "dri", "nli", "british-library",
    "qatar-digital-library", "arabic-collections-online", "ajol", "scielo-south-africa",
    "shodhganga", "world-bank-data", "eurostat", "oecd-data", "un-data",
):
    require(f'"{source_id}"' in module, f"missing source lens {source_id}")

registry = text("library-backend/app/global_source_federation.py")
ast.parse(registry)
require('"version": "6.5.0"' in registry, "registry generation")
for source_id in ("doaj", "cinii", "cnki", "sid-iran", "dri", "ajol", "eurostat", "un-data"):
    require(f'_source("{source_id}"' in registry, f"missing registry source {source_id}")
require('"registry-only"' in registry, "registry-only mode missing")
require('"browser-handoff"' in registry, "browser handoff mode missing")

main = text("library-backend/app/main.py")
ast.parse(main)
require(main.count('"library_web_version"') == 1, "health must contain one library_web_version key")
require('"library_web_version": "2.5.0"' in main, "health web generation")
require('"library_sdk_version": "1.5.0"' in main, "health SDK generation")
require('"global_knowledge_federation_ii": True' in main, "health federation II capability")
for route in (
    '/api/library/v1/federation/global',
    '/api/library/v1/federation/global/readiness',
    '/api/library/v1/federation/global/lenses',
    '/api/library/v1/federation/global/sources/{source_id}',
    '/api/library/v1/federation/global/plan',
    '/api/library/v1/federation/global/discover',
):
    require(route in main, f"missing route {route}")
require('/api/library/v1/discovery/readiness' in main, "v6.4 discovery lineage lost")
require('/api/library/v1/saved-workspaces/readiness' in main, "v6.3 saved-workspace lineage lost")

backend_init = text("library-backend/app/__init__.py")
require('3.5.0' in backend_init, "backend generation")

connector = text("library-backend/app/connector_federation_service.py")
require('LIBRARY_VERSION = "6.5.0"' in connector, "connector service library version")
require('BACKEND_VERSION = "3.5.0"' in connector, "connector service backend version")

webapp = text("library-backend/app/web_application.py")
require('WEB_VERSION = "2.5.0"' in webapp, "web application version")
require('"library_version": "6.5.0"' in webapp, "web application Library version")
require('"backend_version": "3.5.0"' in webapp, "web application backend version")

nav = text("library-backend/app/navigation_service.py")
for marker in ('LIBRARY_VERSION = "6.5.0"', 'BACKEND_VERSION = "3.5.0"', 'WEB_VERSION = "2.5.0"', 'SDK_VERSION = "1.5.0"'):
    require(marker in nav, marker)
require('"next_release": "6.6.0"' in nav, "next release")
require('"next_release_name": "Research Graph & Evidence Navigation"' in nav, "next release name")

pyproject = text("clients/python/pyproject.toml")
pyinit = text("clients/python/sustainable_catalyst_library/__init__.py")
require('version = "1.5.0"' in pyproject, "python project SDK version")
require('__version__ = "1.5.0"' in pyinit, "python SDK __version__")
pyclient = text("clients/python/sustainable_catalyst_library/client.py")
for method in ("global_federation", "global_federation_readiness", "global_federation_lenses", "plan_global_federation", "global_federated_discovery"):
    require(f"def {method}(" in pyclient, f"python client missing {method}")

js_pkg = text("clients/javascript/package.json")
js_client = text("clients/javascript/src/index.js")
js_types = text("clients/javascript/src/index.d.ts")
require('"version": "1.5.0"' in js_pkg, "JS SDK version")
require('globalFederation(){' in js_client, "JS client globalFederation")
require('globalFederatedDiscovery(payload)' in js_client, "JS client globalFederatedDiscovery")
require('globalFederation():Promise<any>' in js_types, "JS types federation")

web_config = text("library-web/config.js")
web_js = text("library-web/assets/app.js")
web_html = text("library-web/index.html")
require('webVersion: "2.5.0"' in web_config, "web config generation")
require('webVersion: "2.5.0"' in web_js, "web app fallback generation")
require("'/federation/global/readiness'" in web_js, "web system federation check")
require('Web v2.5.0 · API v1' in web_html, "web footer generation")

plugin = text("sustainable-catalyst-library/sustainable-catalyst-library.php")
for marker in (
    'Version: 6.5.0',
    "SC_LIBRARY_BACKEND_GENERATION', '3.5.0'",
    "SC_LIBRARY_WEB_GENERATION', '2.5.0'",
    "SC_LIBRARY_SDK_GENERATION', '1.5.0'",
    "SC_LIBRARY_GLOBAL_FEDERATION_II_AUTHORITY",
):
    require(marker in plugin, marker)

print("PASS: Library v6.5.0 Global Knowledge Federation II static release contract")
