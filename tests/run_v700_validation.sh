#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v7.0.0 validation ==="
echo "Library 7.0.0 / backend 4.0.0 / web 3.0.0 / SDK 2.0.0 / API v1 stable"

echo
echo "=== PYTHON COMPILE ==="
"${SC_LIBRARY_V700_TEST_PYTHON:-python3}" -m compileall -q library-backend/app
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" \
"${SC_LIBRARY_V700_TEST_PYTHON:-python3}" -m pytest -q \
  library-backend/tests/test_independent_knowledge_library_platform_v400.py \
  tests/test_independent_knowledge_library_platform_v700.py
echo "PASS: backend and static v7.0 tests"

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
grep -q '__version__ = "4.0.0"' library-backend/app/__init__.py
grep -q 'webVersion: "3.0.0"' library-web/config.js
grep -q '__version__ = "2.0.0"' clients/python/sustainable_catalyst_library/__init__.py
grep -q '"version": "2.0.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '7.0.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== API V1 STABILITY + PLATFORM PROMOTION ==="
grep -q 'API_VERSION = "1.0"' library-backend/app/independent_knowledge_library_platform.py
grep -q 'api_v1_breaking_change.*False' library-backend/app/independent_knowledge_library_platform.py
grep -q '/api/library/v1/platform/readiness' library-backend/app/main.py
echo "PASS: API v1 preserved and Library 7 platform surface present"

echo
echo "=== WORDPRESS INDEPENDENCE ==="
grep -q '"wordpress_required": False' library-backend/app/independent_knowledge_library_platform.py
grep -q "define('SC_LIBRARY_WORDPRESS_AUTHORITATIVE', false);" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: WordPress remains optional and non-authoritative"

echo
echo "=== PRESERVE 6.39 + 6.38 + 6.37 SURFACES ==="
grep -q '/api/library/v1/library7-certification/readiness' library-backend/app/main.py
grep -q '/api/library/v1/research-audit/readiness' library-backend/app/main.py
grep -q '/api/library/v1/research-federation/readiness' library-backend/app/main.py
echo "PASS: predecessor certification, audit, federation, and prior research surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v7.0.0 Independent Knowledge Library Platform validation complete"
