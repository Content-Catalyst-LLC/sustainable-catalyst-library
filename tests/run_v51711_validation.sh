#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fail(){ echo "ERROR: $*" >&2; exit 1; }
PY="$(command -v python3 || command -v python || true)"
[[ -n "$PY" ]] || fail "Python 3 is required for release validation."
echo "=== Knowledge Library v5.17.1.1 / backend v2.28.2 validation ==="
echo "Python: $($PY --version 2>&1)"
grep -q 'Version: 5.17.1.1' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress version mismatch"
grep -q 'Stable tag: 5.17.1.1' "$ROOT/sustainable-catalyst-library/readme.txt" || fail "WordPress stable tag mismatch"
grep -q '__version__ = "2.28.2"' "$ROOT/library-backend/app/__init__.py" || fail "backend version mismatch"
grep -q 'publication_record_ids' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-publications.php" || fail "canonical Publications manifest helper missing"
grep -q 'publication_record_ids($max_publications)' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" || fail "shortcode does not use Publications manifest"
grep -q 'selection_mode = "publication-library-manifest"' "$ROOT/library-backend/app/publication_corpus_maps.py" || fail "manifest selector missing"
grep -q 'selection_mode = "wordpress-post-fallback"' "$ROOT/library-backend/app/publication_corpus_maps.py" || fail "safe fallback selector missing"
grep -q 'object_type = object_type or "post"' "$ROOT/library-backend/app/publication_corpus_maps.py" || fail "non-publication fallback exclusion missing"
grep -q 'CORPUS SUMMARY' "$ROOT/upgrade_library_backend_v2_28_2_contabo.sh" || fail "bounded deployment summary missing"
! grep -q 'python3 -m json.tool | head' "$ROOT/upgrade_library_backend_v2_28_2_contabo.sh" || fail "unsafe head/pipefail deployment pipeline still present"
"$PY" -m compileall -q "$ROOT/library-backend/app"
if "$PY" -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend" "$PY" -m pytest -q \
    "$ROOT/library-backend/tests/test_publication_corpus_scope_v2282.py" \
    "$ROOT/tests/test_publication_corpus_scope_repair_v51711.py" \
    "$ROOT/library-backend/tests/test_publication_corpus_maps_v2281.py" \
    "$ROOT/library-backend/tests/test_publication_corpus_map_routes_v2281.py" \
    "$ROOT/tests/test_publication_corpus_integration_v5171.py" \
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
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-publications.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" >/dev/null
else
  echo "SKIP: php CLI is not installed locally."
fi
if command -v node >/dev/null 2>&1; then
  node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5170.js"
else
  echo "SKIP: node is not installed locally."
fi
echo "PASS: Knowledge Library v5.17.1.1 Publication Corpus Scope & Deployment Repair validation complete"
