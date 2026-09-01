#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHON_BIN="${SC_LIBRARY_VALIDATION_PYTHON:-python3}"

echo "==> PHP syntax"
php -l "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-hardening.php" >/dev/null

echo "==> Python compile"
"$PYTHON_BIN" -m compileall -q "$ROOT/library-backend/app" "$ROOT/library-backend/tests"

echo "==> v5.5.0 contract tests"
PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" "$PYTHON_BIN" -m pytest -q \
  "$ROOT/library-backend/tests" \
  "$ROOT/tests/test_python_research_intelligence_backend_v550.py"

echo "PASS - Sustainable Catalyst Library v5.5.0 validation"
