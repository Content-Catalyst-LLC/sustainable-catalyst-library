#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHON_BIN="${SC_LIBRARY_VALIDATION_PYTHON:-python3}"

echo "==> PHP syntax"
find "$ROOT/sustainable-catalyst-library" -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done

echo "==> JavaScript syntax"
for file in \
  sc-library-dynamic-explorer-v560.js \
  sc-library-capability-hub-v560r3.js \
  sc-library-research-network-console-v560r3.js \
  sc-library-homepage-console-v561.js \
  sc-library-open-course-finder.js \
  sc-library-course-plan.js \
  sc-library-orchestrator.js; do
  node --check "$ROOT/sustainable-catalyst-library/assets/js/$file" >/dev/null
done

echo "==> Python compile"
"$PYTHON_BIN" -m compileall -q "$ROOT/library-backend/app" "$ROOT/library-backend/tests"

echo "==> v5.6.1.1 regression + preservation + homepage-console + publications-spotlight tests"
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
  "$ROOT/tests/test_rendered_interface_delivery_v560r32.py" \
  "$ROOT/tests/test_homepage_research_network_console_v561.py" \
  "$ROOT/tests/test_publications_spotlight_context_v5611.py"

echo "PASS - Sustainable Catalyst Library v5.6.1.1 validation"
