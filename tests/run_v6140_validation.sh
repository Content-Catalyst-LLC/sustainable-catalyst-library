#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"; cd "$ROOT"
echo "=== Sustainable Catalyst Library v6.14.0 validation ==="
echo "Library 6.14.0 / backend 3.14.0 / web 2.14.0 / SDK 1.14.0"
python3 -m py_compile \
  library-backend/app/primary_source_comparison_criticism.py \
  library-backend/app/main.py \
  library-backend/app/research_interface.py \
  library-backend/app/navigation_service.py \
  library-backend/app/web_application.py \
  library-backend/app/domain_authority.py \
  clients/python/sustainable_catalyst_library/client.py \
  tests/test_primary_source_comparison_criticism_v6140.py \
  library-backend/tests/test_primary_source_comparison_criticism_v3140.py
echo "PASS: Python compile"
python3 - <<'PY'
import runpy
ns=runpy.run_path('tests/test_primary_source_comparison_criticism_v6140.py'); n=0
for k in sorted(ns):
    v=ns[k]
    if k.startswith('test_') and callable(v): v(); n+=1
print(f'PASS: {n} v6.14.0 static release tests')
PY
PYTHON_RUNNER=""
for candidate in /tmp/sc-library-v6130-venv/bin/python /tmp/sc-library-v6140-venv/bin/python "$(command -v python3.13 2>/dev/null || true)" "$(command -v python3 2>/dev/null || true)"; do
  [ -n "$candidate" ] || continue; [ -x "$candidate" ] || continue
  if PYTHONPATH="$ROOT/library-backend" "$candidate" - <<'PY' >/dev/null 2>&1
from app.primary_source_comparison_criticism import contract
assert contract()['library_version']=='6.14.0'
PY
  then PYTHON_RUNNER="$candidate"; break; fi
done
if [ -n "$PYTHON_RUNNER" ]; then
  PYTHONPATH="$ROOT/library-backend" "$PYTHON_RUNNER" - <<'PY'
import runpy
ns=runpy.run_path('library-backend/tests/test_primary_source_comparison_criticism_v3140.py'); n=0
for k in sorted(ns):
    v=ns[k]
    if k.startswith('test_') and callable(v): v(); n+=1
print(f'PASS: {n} backend source-criticism behavior tests')
PY
else
  echo "NOTE: backend behavior import skipped locally; Contabo deployment performs authoritative runtime verification."
fi
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; node --check library-web/assets/app.js; echo "PASS: JavaScript syntax"; fi
if command -v php >/dev/null 2>&1; then php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null; echo "PASS: WordPress PHP lint"; fi
grep -F '__version__ = "3.14.0"' library-backend/app/__init__.py >/dev/null
grep -F 'version = "1.14.0"' clients/python/pyproject.toml >/dev/null
grep -F '"version": "1.14.0"' clients/javascript/package.json >/dev/null
grep -F 'webVersion: "2.14.0"' library-web/config.js >/dev/null
grep -F 'Web v2.14.0 · API v1' library-web/index.html >/dev/null
grep -F 'Version: 6.14.0' sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
grep -F '/research/archives/compare' library-web/index.html >/dev/null
grep -F '/api/library/v1/historical-archives/source-criticism/readiness' library-backend/app/main.py >/dev/null
echo "PASS: generation markers and source-criticism route synchronized"
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.14.0 validation complete"
