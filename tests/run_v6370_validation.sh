#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.37.0 validation ==="
echo "Library 6.37.0 / backend 3.37.0 / web 2.37.0 / SDK 1.37.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/cross_library_cross_institution_research_federation.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_cross_library_cross_institution_research_federation_v3370.py \
  tests/test_cross_library_cross_institution_research_federation_v6370.py
echo "PASS: backend and static v6.37 tests"

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
grep -q '__version__ = "3.37.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.37.0"' library-web/config.js
grep -q 'version = "1.37.0"' clients/python/pyproject.toml
grep -q '"version": "1.37.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.37.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== CROSS-LIBRARY / CROSS-INSTITUTION RESEARCH FEDERATION CONTRACT ==="
grep -q '/api/library/v1/research-federation' library-backend/app/main.py
grep -q 'institutional_source_authority_preserved.*True' library-backend/app/cross_library_cross_institution_research_federation.py
grep -q 'federation_layer_is_truth_authority.*False' library-backend/app/cross_library_cross_institution_research_federation.py
grep -q 'automatic_external_fetch.*False' library-backend/app/cross_library_cross_institution_research_federation.py
grep -q 'automatic_source_merge.*False' library-backend/app/cross_library_cross_institution_research_federation.py
grep -q 'Cross-Library / Cross-Institution Research Federation' library-web/index.html
echo "PASS: federation surface and authority guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.36 + v6.35 + v6.34 + v6.33 + v6.32 + v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research-intelligence' library-backend/app/main.py
grep -q 'research-rooms' library-backend/app/main.py
grep -q 'research-object-exchange' library-backend/app/main.py
grep -q 'research-package-readiness' library-backend/app/main.py
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: v6.36 intelligence and prior integration/research surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.37.0 Cross-Library / Cross-Institution Research Federation validation complete"
