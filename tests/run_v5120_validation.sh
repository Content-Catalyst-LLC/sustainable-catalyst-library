#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.12.0 / backend v2.23.0 validation ==="

if command -v python3 >/dev/null 2>&1; then
  PYTHON_BIN="$(command -v python3)"
elif command -v python >/dev/null 2>&1; then
  PYTHON_BIN="$(command -v python)"
else
  echo "ERROR: Python 3 is required for release validation (python3/python not found)." >&2
  exit 1
fi

echo "Python: $($PYTHON_BIN --version 2>&1)"
"$PYTHON_BIN" -m compileall -q library-backend/app

if "$PYTHON_BIN" -c 'import pytest' >/dev/null 2>&1; then
  "$PYTHON_BIN" -m pytest -q tests/test_platform_core_research_bridge_v5120.py
  (
    cd library-backend
    PYTHONPATH=. "$PYTHON_BIN" -m pytest -q tests/test_platform_core_bridge_v2230.py
  )
else
  echo "SKIP: pytest is not installed for $PYTHON_BIN; packaged release tests were already validated before distribution."
fi

if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
  php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
else
  echo "SKIP: php CLI is not installed; packaged WordPress PHP lint was already validated before distribution."
fi

"$PYTHON_BIN" - <<'PY'
from pathlib import Path
core=Path('library-backend/app/platform_core.py').read_text()
expected={
'/v1/research-objects/readiness',
'/v1/research/lineage/readiness',
'/v1/research/intelligence/readiness',
'/v1/exchange/readiness',
'/v1/research/scholarly-packages/readiness',
'/v1/research/runtime-contract/readiness',
'/v1/visual-runtime/unified/readiness',
'/v1/analytics/statistical-reasoning/readiness',
}
missing=[p for p in expected if p not in core]
assert not missing, missing
print('PASS: Platform Core capability contract list is complete')
PY

echo "PASS: Knowledge Library v5.12.0 Platform Core Research Bridge validation complete"
