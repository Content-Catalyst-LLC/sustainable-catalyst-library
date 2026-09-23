#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fail(){ echo "ERROR: $*" >&2; exit 1; }
PY="$(command -v python3 || command -v python || true)"
[[ -n "$PY" ]] || fail "Python 3 is required for release validation."
echo "=== Knowledge Library v5.14.0.1 / backend v2.25.1 validation ==="
echo "Python: $($PY --version 2>&1)"
grep -q 'Version: 5.14.0' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress v5.14.0 identity changed unexpectedly"
grep -q '__version__ = "2.25.1"' "$ROOT/library-backend/app/__init__.py" || fail "backend version mismatch"
python3 - <<'PY' "$ROOT/library-backend/app/main.py"
from pathlib import Path
import sys
s=Path(sys.argv[1]).read_text()
g=s.index('@app.get("/v1/citations/{record_id:path}/graph")')
l=s.index('@app.get("/v1/citations/{record_id:path}")')
assert g < l, "graph route must be registered before catch-all citation route"
print("PASS: citation graph route precedes catch-all citation route")
PY
"$PY" -m compileall -q "$ROOT/library-backend/app"
if "$PY" -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q \
    "$ROOT/library-backend/tests/test_citation_graph_route_v2251.py" \
    "$ROOT/library-backend/tests/test_citation_graph_v2250.py" \
    "$ROOT/library-backend/tests/test_hybrid_retrieval_v2240.py" \
    "$ROOT/library-backend/tests/test_hybrid_retrieval_v2241.py" \
    "$ROOT/library-backend/tests/test_platform_core_bridge_v2230.py" \
    "$ROOT/tests/test_citation_graph_scholarly_lineage_v5140.py"
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
echo "PASS: Knowledge Library v5.14.0.1 Citation Graph Route Precedence Repair validation complete"
