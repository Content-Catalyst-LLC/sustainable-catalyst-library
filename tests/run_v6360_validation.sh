#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.36.0 validation ==="
echo "Library 6.36.0 / backend 3.36.0 / web 2.36.0 / SDK 1.36.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/library_librarian_unified_research_intelligence.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_library_librarian_unified_research_intelligence_v3360.py \
  tests/test_library_librarian_unified_research_intelligence_v6360.py
echo "PASS: backend and static v6.36 tests"

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
grep -q '__version__ = "3.36.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.36.0"' library-web/config.js
grep -q 'version = "1.36.0"' clients/python/pyproject.toml
grep -q '"version": "1.36.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.36.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== LIBRARY–LIBRARIAN UNIFIED RESEARCH INTELLIGENCE CONTRACT ==="
grep -q '/api/library/v1/research-intelligence' library-backend/app/main.py
grep -q 'library_remains_research_state_authority.*True' library-backend/app/library_librarian_unified_research_intelligence.py
grep -q 'librarian_layer_is_truth_authority.*False' library-backend/app/library_librarian_unified_research_intelligence.py
grep -q 'automatic_external_transport.*False' library-backend/app/library_librarian_unified_research_intelligence.py
grep -q 'automatic_source_inclusion.*False' library-backend/app/library_librarian_unified_research_intelligence.py
grep -q 'Library–Librarian Unified Research Intelligence' library-web/index.html
echo "PASS: unified intelligence surface and authority guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.35 + v6.34 + v6.33 + v6.32 + v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research-rooms' library-backend/app/main.py
grep -q 'research-object-exchange' library-backend/app/main.py
grep -q 'research-package-readiness' library-backend/app/main.py
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: v6.35 rooms and prior integration/research surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.36.0 Library–Librarian Unified Research Intelligence validation complete"
