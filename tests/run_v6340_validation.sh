#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.34.0 validation ==="
echo "Library 6.34.0 / backend 3.34.0 / web 2.34.0 / SDK 1.34.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/portable_research_object_exchange.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_portable_research_object_exchange_v3340.py \
  tests/test_portable_research_object_exchange_v6340.py
echo "PASS: backend and static v6.34 tests"

echo
echo "=== JAVASCRIPT SYNTAX ==="
node --check library-web/assets/app.js
node --check clients/javascript/src/index.js
echo "PASS: JavaScript syntax"

echo
echo "=== WORDPRESS PHP LINT ==="
php -l sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: WordPress PHP lint"

echo
echo "=== GENERATION MARKERS ==="
grep -q '__version__ = "3.34.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.34.0"' library-web/config.js
grep -q 'version = "1.34.0"' clients/python/pyproject.toml
grep -q '"version": "1.34.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.34.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== PORTABLE RESEARCH OBJECT / EXCHANGE CONTRACT ==="
grep -q '/api/library/v1/research-object-exchange' library-backend/app/main.py
grep -q 'originating_authority_is_preserved.*True' library-backend/app/portable_research_object_exchange.py
grep -q 'automatic_import.*False' library-backend/app/portable_research_object_exchange.py
grep -q 'automatic_schema_migration.*False' library-backend/app/portable_research_object_exchange.py
grep -q 'Portable Research Object &amp; Exchange Format' library-web/index.html
echo "PASS: portable object/exchange surface and authority guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.33 + v6.32 + v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research-package-readiness' library-backend/app/main.py
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: v6.33 readiness, v6.32 review/versioning, v6.31 lineage, v6.30 project workspace, v6.29 certification, and v6.28 Workspace integration preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.34.0 Portable Research Object & Exchange Format validation complete"
