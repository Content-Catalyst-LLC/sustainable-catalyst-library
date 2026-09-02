#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHON_BIN="${SC_LIBRARY_VALIDATION_PYTHON:-python3}"
echo "==> PHP syntax"
find "$ROOT/sustainable-catalyst-library" -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done
echo "==> JavaScript syntax"
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-dynamic-explorer-v560.js" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-capability-hub-v560r2.js" >/dev/null
echo "==> Python compile"
"$PYTHON_BIN" -m compileall -q "$ROOT/library-backend/app" "$ROOT/library-backend/tests"
echo "==> v5.6.0 R2 regression + preservation + interface-density tests"
PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" "$PYTHON_BIN" -m pytest -q \
  "$ROOT/library-backend/tests/test_models.py" \
  "$ROOT/library-backend/tests/test_security.py" \
  "$ROOT/library-backend/tests/test_ingestion_hardening.py" \
  "$ROOT/library-backend/tests/test_operations_recovery.py" \
  "$ROOT/library-backend/tests/test_dynamic_explorer.py" \
  "$ROOT/tests/test_dynamic_library_explorer_v560.py" \
  "$ROOT/tests/test_capability_preserving_interface_v560r1.py" \
  "$ROOT/tests/test_featured_access_librarian_interface_v560r2.py"
echo "PASS - Sustainable Catalyst Library v5.6.0 R2 validation"
