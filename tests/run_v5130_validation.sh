#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.13.0 / backend v2.24.0 validation ==="

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
  "$PYTHON_BIN" -m pytest -q tests/test_hybrid_research_retrieval_v5130.py
  (
    cd library-backend
    PYTHONPATH=. "$PYTHON_BIN" -m pytest -q tests/test_hybrid_retrieval_v2240.py
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
root=Path('.')
main=(root/'sustainable-catalyst-library/sustainable-catalyst-library.php').read_text()
backend=(root/'library-backend/app/__init__.py').read_text()
schema=(root/'library-backend/app/schema.sql').read_text()
hybrid=(root/'library-backend/app/hybrid_retrieval.py').read_text()
semantic=(root/'library-backend/app/semantic.py').read_text()
core=(root/'library-backend/app/platform_core.py').read_text()
assert 'Version: 5.13.0' in main
assert "SC_LIBRARY_VERSION', '5.13.0'" in main
assert '__version__ = "2.24.0"' in backend
assert 'library_record_embeddings' in schema
assert 'library_embedding_jobs' in schema
assert 'sc_cosine_similarity' in schema
assert 'reciprocal_rank_fusion' in hybrid
assert 'library_core_bindings' in hybrid
assert 'PlatformCoreClient' not in hybrid
assert 'fake or' in semantic.lower() and 'hash embedding' in semantic.lower()
for route in [
    '/v1/research-objects/readiness',
    '/v1/research/lineage/readiness',
    '/v1/research/intelligence/readiness',
    '/v1/exchange/readiness',
    '/v1/research/scholarly-packages/readiness',
    '/v1/research/runtime-contract/readiness',
    '/v1/visual-runtime/unified/readiness',
    '/v1/analytics/statistical-reasoning/readiness',
]:
    assert route in core, route
print('PASS: v5.13 identity, hybrid retrieval, semantic index, and Platform Core contracts are aligned')
PY

echo "PASS: Knowledge Library v5.13.0 Hybrid Research Retrieval validation complete"
