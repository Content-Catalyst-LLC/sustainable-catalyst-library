#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.67.0.2 / backend v2.78.0 WordPress Runtime Boot Integrity validation ==="

python3 tests/test_wordpress_boot_integrity_v56702.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/main.py

PYTHONPATH=library-backend python3 - <<'PY56702'
from app import __version__
from app.runtime_independence import readiness
assert __version__ == "2.78.0", __version__
r = readiness()
assert r["library_version"] == "5.67.0.2", r
assert r["backend_version"] == "2.78.0", r
assert r["state"] == "ready", r
assert r["wordpress_dependency_count"] == 0, r
assert r["default_certification"]["certified"] is True, r
print("PASS: backend v2.78.0 certification identity aligned to Library v5.67.0.2")
PY56702

# Preserve the v5.67 WordPress-failure behavior as the functional regression baseline.
./tests/run_v5670_validation.sh

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0)
  php -r 'define("ABSPATH", __DIR__); require "sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php"; if (!class_exists("SC_Library_Runtime_Certification", false)) { fwrite(STDERR, "FAIL: class did not load\n"); exit(1); } echo "PASS: runtime-certification class isolated load\n";'
  echo "PASS: WordPress PHP lint"
fi

if command -v node >/dev/null 2>&1; then
  node --check library-web/assets/app.js
  echo "PASS: Library Web JavaScript syntax"
fi

if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
fi

git diff --check

echo "PASS: Knowledge Library v5.67.0.2 WordPress Runtime Boot Integrity Repair validation complete"
