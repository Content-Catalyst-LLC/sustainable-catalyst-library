#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.39.0 validation ==="
echo "Library 6.39.0 / backend 3.39.0 / web 2.39.0 / SDK 1.39.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/library7_production_consolidation_certification.py \
  library-backend/app/main.py \
  library-backend/app/independent_api.py \
  clients/python/sustainable_catalyst_library/client.py
echo "PASS: Python compile"

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_library7_production_consolidation_certification_v3390.py \
  tests/test_library7_production_consolidation_certification_v6390.py
echo "PASS: backend and static v6.39 tests"

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
grep -q '__version__ = "3.39.0"' library-backend/app/__init__.py
grep -q 'webVersion: "2.39.0"' library-web/config.js
grep -q 'version = "1.39.0"' clients/python/pyproject.toml
grep -q '"version": "1.39.0"' clients/javascript/package.json
grep -q "define('SC_LIBRARY_VERSION', '6.39.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_NEXT_ARCHITECTURE_RELEASE', '7.0.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
echo "PASS: generation markers synchronized"

echo
echo "=== LIBRARY 7 PRODUCTION CERTIFICATION GATE ==="
grep -q '/api/library/v1/library7-certification' library-backend/app/main.py
grep -q 'library7_ready' library-backend/app/library7_production_consolidation_certification.py
grep -q 'rollback_backup_required.*True' library-backend/app/library7_production_consolidation_certification.py
grep -q 'wordpress_required.*False' library-backend/app/library7_production_consolidation_certification.py
grep -q 'automatic_database_migration.*False' library-backend/app/library7_production_consolidation_certification.py
grep -q 'automatic_truth_promotion.*False' library-backend/app/library7_production_consolidation_certification.py
grep -q 'Library 7 readiness gate' library-web/assets/app.js
echo "PASS: Library 7 consolidation/certification gate present"

echo
echo "=== WEB PORT + SAME-ORIGIN API PRESERVATION ==="
grep -q 'SC_LIBRARY_WEB_BIND_PORT:-8095' library-web/compose.yml
grep -q 'location /api/library/' library-web/nginx.conf
echo "PASS: production Web port 8095 and same-origin proxy preserved"

echo
echo "=== PRESERVE v6.38 + v6.37 + PRIOR RESEARCH SURFACES ==="
for marker in \
  research-audit \
  research-federation \
  research-intelligence \
  research-rooms \
  research-object-exchange \
  research-package-readiness \
  research-review-versioning \
  research-lineage-graph \
  research-project-workspace \
  cross-product-certification \
  workspace-integration
do
  grep -q "$marker" library-backend/app/main.py
done
echo "PASS: v6.38 audit, v6.37 federation, and prior research surfaces preserved"

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.39.0 Library 7 Production Consolidation & Certification validation complete"
