#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-$PWD}"
cd "$REPO"

echo "=== Sustainable Catalyst Library v6.31.0 validation ==="
echo "Library 6.31.0 / backend 3.31.0 / web 2.31.0 / SDK 1.31.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/research_dependency_lineage_graph.py \
  library-backend/app/main.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend python3 -m pytest -q \
  library-backend/tests/test_research_dependency_lineage_graph_v3310.py \
  tests/test_research_dependency_lineage_graph_v6310.py
echo "PASS: backend and static v6.31 tests"

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
grep -q 'webVersion: "2.31.0"' library-web/config.js
grep -q '__version__ = "3.31.0"' library-backend/app/__init__.py
grep -q 'Version: 6.31.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q 'version = "1.31.0"' clients/python/pyproject.toml
grep -q '"version": "1.31.0"' clients/javascript/package.json
echo "PASS: generation markers synchronized"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.30 + v6.29 + v6.28 ==="
grep -q 'unified_research_project_workspace' library-backend/app/main.py
grep -q '/api/library/v1/research-project-workspace' library-backend/app/main.py
grep -q 'cross_product_research_handoff_certification' library-backend/app/main.py
grep -q 'library_workspace_research_integration' library-backend/app/main.py
echo "PASS: v6.30 project workspace, v6.29 certification, and v6.28 Workspace integration preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"

echo "PASS: Sustainable Catalyst Library v6.31.0 Research Dependency & Lineage Graph validation complete"
