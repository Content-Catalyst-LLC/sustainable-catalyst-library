#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-$(pwd)}"
cd "$REPO"
echo "=== Sustainable Catalyst Library v6.30.0 validation ==="
echo "Library 6.30.0 / backend 3.30.0 / web 2.30.0 / SDK 1.30.0"
echo
echo "=== PYTHON COMPILE ==="
python3 -m compileall -q library-backend/app clients/python/sustainable_catalyst_library
echo "PASS: Python compile"
echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend python3 -m pytest -q \
  library-backend/tests/test_unified_research_project_workspace_v3300.py \
  tests/test_unified_research_project_workspace_v6300.py
echo "PASS: backend and static v6.30 tests"
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
grep -F '__version__ = "3.30.0"' library-backend/app/__init__.py >/dev/null
grep -F 'webVersion: "2.30.0"' library-web/config.js >/dev/null
grep -F 'Web v2.30.0 · API v1' library-web/index.html >/dev/null
grep -F '__version__ = "1.30.0"' clients/python/sustainable_catalyst_library/__init__.py >/dev/null
grep -F 'Version: 6.30.0' sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
grep -F '/research/project' library-web/index.html >/dev/null
echo "PASS: generation markers synchronized"
echo
echo "=== PRESERVE v6.29 + v6.28 ==="
grep -F 'cross_product_research_handoff_certification' library-backend/app/main.py >/dev/null
grep -F 'library_workspace_research_integration' library-backend/app/main.py >/dev/null
echo "PASS: v6.29 certification and v6.28 Workspace integration preserved"
echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.30.0 Unified Research Project Workspace validation complete"
