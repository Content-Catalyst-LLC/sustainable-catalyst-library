#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.33.0 validation ==="
echo "Library 6.33.0 / backend 3.33.0 / web 2.33.0 / SDK 1.33.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/research_package_validation_readiness.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_research_package_validation_readiness_v3330.py \
  tests/test_research_package_validation_readiness_v6330.py
echo "PASS: backend and static v6.33 tests"

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
grep -q '__version__ = "3.33.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.33.0"' library-web/config.js
grep -q 'version = "1.33.0"' clients/python/pyproject.toml
grep -q '"version": "1.33.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.33.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== PACKAGE VALIDATION / PUBLICATION READINESS CONTRACT ==="
grep -q '/api/library/v1/research-package-readiness' library-backend/app/main.py
grep -q 'ready_for_handoff_implies_truth.*False' library-backend/app/research_package_validation_readiness.py
grep -q 'automatic_publication.*False' library-backend/app/research_package_validation_readiness.py
grep -q 'Research Package Validation &amp; Publication Readiness' library-web/index.html
echo "PASS: package validation/readiness surface and guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.32 + v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: v6.32 review/versioning, v6.31 lineage, v6.30 project workspace, v6.29 certification, and v6.28 Workspace integration preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.33.0 Research Package Validation & Publication Readiness validation complete"
