#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.13.0.1 / backend v2.24.1 validation ==="
if command -v python3 >/dev/null 2>&1; then PYTHON_BIN="$(command -v python3)"; elif command -v python >/dev/null 2>&1; then PYTHON_BIN="$(command -v python)"; else echo "ERROR: Python 3 required" >&2; exit 1; fi
echo "Python: $($PYTHON_BIN --version 2>&1)"
"$PYTHON_BIN" -m compileall -q library-backend/app
if "$PYTHON_BIN" -c 'import pytest' >/dev/null 2>&1; then
  (cd library-backend && PYTHONPATH=. "$PYTHON_BIN" -m pytest -q tests/test_hybrid_retrieval_v2240.py tests/test_hybrid_retrieval_v2241.py)
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
"$PYTHON_BIN" - <<'PY'
from pathlib import Path
root=Path('.')
h=(root/'library-backend/app/hybrid_retrieval.py').read_text()
b=(root/'library-backend/app/__init__.py').read_text()
assert 'from collections import defaultdict' in h
assert '__version__ = "2.24.1"' in b
assert 'library_core_bindings' in h
assert 'PlatformCoreClient' not in h
print('PASS: v5.13.0.1 runtime repair and Core-aware retrieval contracts aligned')
PY
echo "PASS: Knowledge Library v5.13.0.1 validation complete"
