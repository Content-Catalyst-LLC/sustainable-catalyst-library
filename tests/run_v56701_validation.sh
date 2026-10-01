#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.67.0.1 / backend v2.78.0 integrity repair validation ==="

PYTHONPATH=library-backend python3 tests/test_release_integrity_v56701.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/main.py

PYTHONPATH=library-backend python3 - <<'PY56701'
from app import __version__
from app.runtime_independence import readiness
assert __version__ == "2.78.0", __version__
r = readiness()
assert r["library_version"] == "5.67.0.1", r
assert r["backend_version"] == "2.78.0", r
assert r["state"] == "ready", r
assert r["wordpress_dependency_count"] == 0, r
assert r["default_certification"]["certified"] is True, r
print("PASS: backend/runtime-certification identity alignment")
PY56701

# Preserve the v5.67 functional certification as a regression check.
./tests/run_v5670_validation.sh

if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
  php -l sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php >/dev/null
  echo "PASS: WordPress PHP lint"
fi

if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
fi

git diff --check

echo "PASS: Knowledge Library v5.67.0.1 Backend Release Identity & Deployment Integrity Repair validation complete"
