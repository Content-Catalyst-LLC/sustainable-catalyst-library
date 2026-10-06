#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-$PWD}"
cd "$REPO"

echo "=== Sustainable Catalyst Library v6.32.0 validation ==="
echo "Library 6.32.0 / backend 3.32.0 / web 2.32.0 / SDK 1.32.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/research_review_revision_versioning.py \
  library-backend/app/main.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend python3 -m pytest -q \
  library-backend/tests/test_research_review_revision_versioning_v3320.py \
  tests/test_research_review_revision_versioning_v6320.py
echo "PASS: backend and static v6.32 tests"

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
grep -q 'webVersion: "2.32.0"' library-web/config.js
grep -q '__version__ = "3.32.0"' library-backend/app/__init__.py
grep -q 'Version: 6.32.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q 'version = "1.32.0"' clients/python/pyproject.toml
grep -q '"version": "1.32.0"' clients/javascript/package.json
echo "PASS: generation markers synchronized"

echo
echo "=== REVIEW / VERSIONING CONTRACT ==="
grep -q '/api/library/v1/research-review-versioning' library-backend/app/main.py
grep -q '/research/project/review' library-web/index.html
grep -q 'review_approval_implies_truth.*False' library-backend/app/research_review_revision_versioning.py
grep -q 'automatic_revision_application.*False' library-backend/app/research_review_revision_versioning.py
echo "PASS: review/versioning surface and guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.31 + v6.30 + v6.29 + v6.28 ==="
grep -q 'research_dependency_lineage_graph' library-backend/app/main.py
grep -q 'unified_research_project_workspace' library-backend/app/main.py
grep -q 'cross_product_research_handoff_certification' library-backend/app/main.py
grep -q 'library_workspace_research_integration' library-backend/app/main.py
echo "PASS: v6.31 lineage, v6.30 project workspace, v6.29 certification, and v6.28 Workspace integration preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"

echo "PASS: Sustainable Catalyst Library v6.32.0 Research Review, Revision & Versioning System validation complete"
