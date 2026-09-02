#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHON_BIN="${SC_LIBRARY_VALIDATION_PYTHON:-python3}"

echo "==> PHP syntax"
find "$ROOT/sustainable-catalyst-library" -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done

echo "==> JavaScript syntax"
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-dynamic-explorer-v560.js" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-capability-hub-v560r3.js" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-research-network-console-v560r3.js" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-open-course-finder.js" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-course-plan.js" >/dev/null

echo "==> CSS corruption guards"
if grep -q '\\n' "$ROOT/sustainable-catalyst-library/assets/css/sc-library-capability-hub-v560r3.css"; then
  echo "FAIL - literal backslash-n sequence remains in capability CSS" >&2
  exit 1
fi

echo "==> Python compile"
"$PYTHON_BIN" -m compileall -q "$ROOT/library-backend/app" "$ROOT/library-backend/tests"

echo "==> v5.6.0 R3.2 regression + preservation + rendered-interface tests"
PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" "$PYTHON_BIN" -m pytest -q \
  "$ROOT/library-backend/tests/test_models.py" \
  "$ROOT/library-backend/tests/test_security.py" \
  "$ROOT/library-backend/tests/test_ingestion_hardening.py" \
  "$ROOT/library-backend/tests/test_operations_recovery.py" \
  "$ROOT/library-backend/tests/test_dynamic_explorer.py" \
  "$ROOT/tests/test_dynamic_library_explorer_v560.py" \
  "$ROOT/tests/test_capability_preserving_interface_v560r1.py" \
  "$ROOT/tests/test_featured_access_librarian_interface_v560r2.py" \
  "$ROOT/tests/test_visible_research_network_v560r3.py" \
  "$ROOT/tests/test_rendered_interface_delivery_v560r32.py"

echo "PASS - Sustainable Catalyst Library v5.6.0 R3.2 validation"
