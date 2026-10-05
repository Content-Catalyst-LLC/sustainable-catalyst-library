#!/usr/bin/env bash
set -euo pipefail

REPO="${1:-$(pwd)}"
cd "$REPO"

echo "=== Sustainable Catalyst Library v6.26.0 validation ==="
echo "Library 6.26.0 / backend 3.26.0 / web 2.26.0 / SDK 1.26.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m compileall -q library-backend/app clients/python/sustainable_catalyst_library
printf 'PASS: Python compile\n'

echo
echo "=== BACKEND + STATIC TESTS ==="
PYTHONPATH=library-backend python3 -m pytest -q \
  library-backend/tests/test_research_publication_studio_v3260.py \
  tests/test_research_publication_studio_v6260.py
printf 'PASS: backend and static v6.26 tests\n'

echo
echo "=== JAVASCRIPT SYNTAX ==="
node --check library-web/assets/app.js
node --check clients/javascript/src/index.js
printf 'PASS: JavaScript syntax\n'

echo
echo "=== WORDPRESS PHP LINT ==="
php -l sustainable-catalyst-library/sustainable-catalyst-library.php
printf 'PASS: WordPress PHP lint\n'

echo
echo "=== GENERATION MARKERS ==="
grep -F '__version__ = "3.26.0"' library-backend/app/__init__.py >/dev/null
grep -F 'webVersion: "2.26.0"' library-web/config.js >/dev/null
grep -F 'Web v2.26.0 · API v1' library-web/index.html >/dev/null
grep -F '__version__ = "1.26.0"' clients/python/sustainable_catalyst_library/__init__.py >/dev/null
grep -F 'Version: 6.26.0' sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
grep -F '/research/publication' library-web/index.html >/dev/null
printf 'PASS: generation markers synchronized\n'

echo
echo "=== GIT DIFF CHECK ==="
git diff --check
printf 'PASS: git diff --check\n'

echo
printf 'PASS: Sustainable Catalyst Library v6.26.0 Research Publication Studio validation complete\n'
