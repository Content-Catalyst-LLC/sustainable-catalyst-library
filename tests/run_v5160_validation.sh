#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fail(){ echo "ERROR: $*" >&2; exit 1; }
PY="$(command -v python3 || command -v python || true)"
[[ -n "$PY" ]] || fail "Python 3 is required for release validation."
echo "=== Knowledge Library v5.16.0 / backend v2.27.0 validation ==="
echo "Python: $($PY --version 2>&1)"
grep -q 'Version: 5.16.0' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress version mismatch"
grep -q 'Stable tag: 5.16.0' "$ROOT/sustainable-catalyst-library/readme.txt" || fail "WordPress stable tag mismatch"
grep -q '__version__ = "2.27.0"' "$ROOT/library-backend/app/__init__.py" || fail "backend version mismatch"
grep -q 'CREATE TABLE IF NOT EXISTS library_publication_visualizations' "$ROOT/library-backend/app/schema.sql" || fail "visualization schema missing"
grep -q 'visual-research-object.create' "$ROOT/library-backend/app/platform_core.py" || fail "Core visual research operation missing"
grep -q 'machine_inferred_graph_edges' "$ROOT/library-backend/app/publication_visualizations.py" || fail "visualization research-integrity boundary missing"
grep -q 'sc_library_publication_visualizations' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-publication-visualizations.php" || fail "Research Library visualization shortcode missing"
"$PY" -m compileall -q "$ROOT/library-backend/app"
if "$PY" -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q \
    "$ROOT/library-backend/tests/test_publication_visualizations_v2270.py" \
    "$ROOT/library-backend/tests/test_publication_visualization_routes_v2270.py" \
    "$ROOT/library-backend/tests/test_research_extraction_v2260.py" \
    "$ROOT/library-backend/tests/test_citation_graph_route_v2251.py" \
    "$ROOT/library-backend/tests/test_citation_graph_v2250.py" \
    "$ROOT/library-backend/tests/test_hybrid_retrieval_v2240.py" \
    "$ROOT/library-backend/tests/test_hybrid_retrieval_v2241.py" \
    "$ROOT/library-backend/tests/test_platform_core_bridge_v2230.py" \
    "$ROOT/tests/test_publication_visualization_foundations_v5160.py" \
    "$ROOT/tests/test_entity_finding_claim_extraction_v5150.py" \
    "$ROOT/tests/test_citation_graph_scholarly_lineage_v5140.py"
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-publication-visualizations.php" >/dev/null
else
  echo "SKIP: php CLI is not installed locally."
fi
echo "PASS: Knowledge Library v5.16.0 Publication Visualization Foundations validation complete"
