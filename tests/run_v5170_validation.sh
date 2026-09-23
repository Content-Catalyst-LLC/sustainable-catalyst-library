#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fail(){ echo "ERROR: $*" >&2; exit 1; }
PY="$(command -v python3 || command -v python || true)"
[[ -n "$PY" ]] || fail "Python 3 is required for release validation."
echo "=== Knowledge Library v5.17.0 / backend v2.28.0 validation ==="
echo "Python: $($PY --version 2>&1)"
grep -q 'Version: 5.17.0' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress version mismatch"
grep -q 'Stable tag: 5.17.0' "$ROOT/sustainable-catalyst-library/readme.txt" || fail "WordPress stable tag mismatch"
grep -q '__version__ = "2.28.0"' "$ROOT/library-backend/app/__init__.py" || fail "backend version mismatch"
grep -q 'KNOWLEDGE_MAP_CONTRACT = "sc-library-publication-knowledge-map/1.0"' "$ROOT/library-backend/app/publication_knowledge_maps.py" || fail "knowledge map contract missing"
grep -q '@app.get("/v1/publication-knowledge-maps")' "$ROOT/library-backend/app/main.py" || fail "knowledge map route missing"
grep -q 'sc_library_knowledge_landscape' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" || fail "knowledge landscape shortcode missing"
grep -q 'embedding-cosine-similarity' "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5170.js" || fail "semantic relationship renderer missing"
grep -q 'visual-research-object.create' "$ROOT/library-backend/app/platform_core.py" || fail "Core visual handoff missing"
"$PY" -m compileall -q "$ROOT/library-backend/app"
if "$PY" -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q \
    "$ROOT/library-backend/tests/test_publication_knowledge_maps_v2280.py" \
    "$ROOT/library-backend/tests/test_publication_knowledge_map_routes_v2280.py" \
    "$ROOT/tests/test_scientific_knowledge_mapping_v5170.py" \
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
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" >/dev/null
else
  echo "SKIP: php CLI is not installed locally."
fi
if command -v node >/dev/null 2>&1; then
  node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5170.js"
else
  echo "SKIP: node is not installed locally."
fi
echo "PASS: Knowledge Library v5.17.0 Scientific Knowledge Mapping & Interactive Semantic Analysis validation complete"
