#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.35.0 validation ==="
echo "Library 6.35.0 / backend 3.35.0 / web 2.35.0 / SDK 1.35.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/collaborative_research_rooms_ii.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_collaborative_research_rooms_ii_v3350.py \
  tests/test_collaborative_research_rooms_ii_v6350.py
echo "PASS: backend and static v6.35 tests"

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
grep -q '__version__ = "3.35.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.35.0"' library-web/config.js
grep -q 'version = "1.35.0"' clients/python/pyproject.toml
grep -q '"version": "1.35.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.35.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== COLLABORATIVE RESEARCH ROOMS II CONTRACT ==="
grep -q '/api/library/v1/research-rooms' library-backend/app/main.py
grep -q 'explicit_membership_and_roles_required.*True' library-backend/app/collaborative_research_rooms_ii.py
grep -q 'automatic_role_escalation.*False' library-backend/app/collaborative_research_rooms_ii.py
grep -q 'review_approval_applies_to_subject_automatically.*False' library-backend/app/collaborative_research_rooms_ii.py
grep -q 'Collaborative Research Rooms II' library-web/index.html
echo "PASS: collaborative room surface and authority guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.34 + v6.33 + v6.32 + v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research-object-exchange' library-backend/app/main.py
grep -q 'research-package-readiness' library-backend/app/main.py
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: v6.34 exchange and prior integration/research surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.35.0 Collaborative Research Rooms II validation complete"
