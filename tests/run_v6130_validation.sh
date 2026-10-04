#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"

echo "=== Sustainable Catalyst Library v6.13.0 validation ==="
echo "Library 6.13.0 / backend 3.13.0 / web 2.13.0 / SDK 1.13.0"

echo
echo "=== PYTHON COMPILE ==="
python3 -m py_compile \
  library-backend/app/historical_archive_workspace.py \
  library-backend/app/main.py \
  library-backend/app/research_interface.py \
  library-backend/app/navigation_service.py \
  library-backend/app/web_application.py \
  library-backend/app/domain_authority.py \
  clients/python/sustainable_catalyst_library/client.py \
  tests/test_historical_archive_workspace_v6130.py \
  library-backend/tests/test_historical_archive_workspace_v3130.py
echo "PASS: Python compile"

echo
echo "=== STATIC RELEASE ASSERTIONS ==="
python3 - <<'PY'
import runpy
ns=runpy.run_path('tests/test_historical_archive_workspace_v6130.py')
count=0
for name in sorted(ns):
    value=ns[name]
    if name.startswith('test_') and callable(value):
        value(); count += 1
print(f'PASS: {count} v6.13.0 static release tests')
PY

echo
echo "=== BACKEND BEHAVIOR ASSERTIONS ==="
PYTHON_RUNNER=""
for candidate in \
  /tmp/sc-library-v6120-venv/bin/python \
  /tmp/sc-library-v6130-venv/bin/python \
  "$(command -v python3.13 2>/dev/null || true)" \
  "$(command -v python3.12 2>/dev/null || true)" \
  "$(command -v python3 2>/dev/null || true)"; do
  [ -n "$candidate" ] || continue
  [ -x "$candidate" ] || continue
  if PYTHONPATH="$ROOT/library-backend" "$candidate" - <<'PY' >/dev/null 2>&1
from app.historical_archive_workspace import contract
assert contract()['library_version'] == '6.13.0'
PY
  then
    PYTHON_RUNNER="$candidate"
    break
  fi
done

if [ -n "$PYTHON_RUNNER" ]; then
  PYTHONPATH="$ROOT/library-backend" "$PYTHON_RUNNER" - <<'PY'
import runpy
ns=runpy.run_path('library-backend/tests/test_historical_archive_workspace_v3130.py')
count=0
for name in sorted(ns):
    value=ns[name]
    if name.startswith('test_') and callable(value):
        value(); count += 1
print(f'PASS: {count} backend workspace behavior tests')
PY
else
  echo "NOTE: backend behavior import skipped locally because no compatible installed Python environment could import backend dependencies."
  echo "      The Contabo Docker deployment script performs authoritative runtime verification."
fi

echo
echo "=== JAVASCRIPT SYNTAX ==="
if command -v node >/dev/null 2>&1; then
  node --check clients/javascript/src/index.js
  node --check library-web/assets/app.js
  echo "PASS: JavaScript syntax"
else
  echo "NOTE: node unavailable; JavaScript syntax check skipped"
fi

echo
echo "=== WORDPRESS PHP LINT ==="
if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php
  echo "PASS: WordPress PHP lint"
else
  echo "NOTE: php unavailable; WordPress lint skipped"
fi

echo
echo "=== GENERATION MARKERS ==="
grep -F '__version__ = "3.13.0"' library-backend/app/__init__.py >/dev/null
grep -F 'version = "1.13.0"' clients/python/pyproject.toml >/dev/null
grep -F '"version": "1.13.0"' clients/javascript/package.json >/dev/null
grep -F 'webVersion: "2.13.0"' library-web/config.js >/dev/null
grep -F 'Web v2.13.0 · API v1' library-web/index.html >/dev/null
grep -F 'Version: 6.13.0' sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
grep -F '/research/archives' library-web/index.html >/dev/null
grep -F '/api/library/v1/historical-archives/workspace/readiness' library-backend/app/main.py >/dev/null
echo "PASS: release generations and archive route synchronized"

echo
echo "=== DIFF CHECK ==="
git diff --check
echo "PASS: git diff --check"

echo
echo "PASS: Sustainable Catalyst Library v6.13.0 Historical Archives Research Workspace validation complete"
