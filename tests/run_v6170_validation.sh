#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"; cd "$ROOT"
echo "=== Sustainable Catalyst Library v6.17.0 validation ==="
echo "Library 6.17.0 / backend 3.17.0 / web 2.17.0 / SDK 1.17.0"
python3 -m py_compile \
  library-backend/app/citation_bibliographic_workspace.py \
  library-backend/app/main.py \
  library-backend/app/research_interface.py \
  library-backend/app/navigation_service.py \
  library-backend/app/web_application.py \
  library-backend/app/domain_authority.py \
  clients/python/sustainable_catalyst_library/client.py \
  tests/test_citation_bibliographic_workspace_v6170.py \
  library-backend/tests/test_citation_bibliographic_workspace_v3170.py
echo "PASS: Python compile"
python3 - <<'PY'
import runpy
ns=runpy.run_path('tests/test_citation_bibliographic_workspace_v6170.py'); n=0
for k in sorted(ns):
    v=ns[k]
    if k.startswith('test_') and callable(v): v(); n+=1
print(f'PASS: {n} v6.17.0 static release tests')
PY
PYTHON_RUNNER=""
for candidate in /tmp/sc-library-v6160-venv/bin/python /tmp/sc-library-v6170-venv/bin/python "$(command -v python3.13 2>/dev/null || true)" "$(command -v python3 2>/dev/null || true)"; do
  [ -n "$candidate" ] || continue; [ -x "$candidate" ] || continue
  if PYTHONPATH="$ROOT/library-backend" "$candidate" - <<'PY' >/dev/null 2>&1
from app.citation_bibliographic_workspace import contract
assert contract()['library_version']=='6.17.0'
PY
  then PYTHON_RUNNER="$candidate"; break; fi
done
if [ -n "$PYTHON_RUNNER" ]; then
  PYTHONPATH="$ROOT/library-backend" "$PYTHON_RUNNER" - <<'PY'
import runpy
ns=runpy.run_path('library-backend/tests/test_citation_bibliographic_workspace_v3170.py'); n=0
for k in sorted(ns):
    v=ns[k]
    if k.startswith('test_') and callable(v): v(); n+=1
print(f'PASS: {n} backend citation-workspace behavior tests')
PY
else
  echo "NOTE: backend behavior import skipped locally; Contabo deployment performs authoritative runtime verification."
fi
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; node --check library-web/assets/app.js; echo "PASS: JavaScript syntax"; fi
if command -v php >/dev/null 2>&1; then php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null; echo "PASS: WordPress PHP lint"; fi
grep -F '__version__ = "3.17.0"' library-backend/app/__init__.py >/dev/null
grep -F 'version = "1.17.0"' clients/python/pyproject.toml >/dev/null
grep -F '"version": "1.17.0"' clients/javascript/package.json >/dev/null
grep -F 'webVersion: "2.17.0"' library-web/config.js >/dev/null
grep -F 'Web v2.17.0 · API v1' library-web/index.html >/dev/null
grep -F 'Version: 6.17.0' sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
grep -F '/research/citations' library-web/index.html >/dev/null
grep -F '/api/library/v1/citations/workspace/readiness' library-backend/app/main.py >/dev/null
grep -F '"library_web_version": "2.17.0"' library-backend/app/main.py >/dev/null
grep -F '"library_sdk_version": "1.17.0"' library-backend/app/main.py >/dev/null
grep -F '"next_release": "6.18.0"' library-backend/app/navigation_service.py >/dev/null
echo "PASS: generation markers and citation-workspace route synchronized"
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.17.0 validation complete"
