#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.38.0 validation ==="
echo "Library 6.38.0 / backend 3.38.0 / web 2.38.0 / SDK 1.38.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/research_reproducibility_audit_console.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_research_reproducibility_audit_console_v3380.py \
  tests/test_research_reproducibility_audit_console_v6380.py
echo "PASS: backend and static v6.38 tests"

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
grep -q '__version__ = "3.38.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.38.0"' library-web/config.js
grep -q 'version = "1.38.0"' clients/python/pyproject.toml
grep -q '"version": "1.38.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.38.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== RESEARCH REPRODUCIBILITY & AUDIT CONTRACT ==="
grep -q '/api/library/v1/research-audit' library-backend/app/main.py
grep -q 'automatic_reexecution.*False' library-backend/app/research_reproducibility_audit_console.py
grep -q 'automatic_external_fetch.*False' library-backend/app/research_reproducibility_audit_console.py
grep -q 'automatic_reproducibility_certification.*False' library-backend/app/research_reproducibility_audit_console.py
grep -q 'audit_completeness_implies_reproducibility.*False' library-backend/app/research_reproducibility_audit_console.py
grep -q 'Research Reproducibility &amp; Audit Console' library-web/index.html
echo "PASS: audit surface and reproducibility guardrails present"

echo
echo "=== WEB PORT PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
echo "PASS: production Web port 8095 preserved"

echo
echo "=== PRESERVE v6.37 + v6.36 + PRIOR RESEARCH SURFACES ==="
grep -q 'research-federation' library-backend/app/main.py
grep -q 'research-intelligence' library-backend/app/main.py
grep -q 'research-rooms' library-backend/app/main.py
grep -q 'research-object-exchange' library-backend/app/main.py
grep -q 'research-package-readiness' library-backend/app/main.py
grep -q 'research-review-versioning' library-backend/app/main.py
grep -q 'research-lineage-graph' library-backend/app/main.py
grep -q 'research-project-workspace' library-backend/app/main.py
grep -q 'cross-product-certification' library-backend/app/main.py
grep -q 'workspace-integration' library-backend/app/main.py
echo "PASS: federation, intelligence, collaboration, exchange and prior integration surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.38.0 Research Reproducibility & Audit Console validation complete"
