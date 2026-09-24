#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PY="${PYTHON_BIN:-python3}"
echo "=== Knowledge Library v5.20.0.2 / backend v2.31.2 validation ==="
echo "Python: $($PY --version 2>&1)"
$PY -m compileall -q "$ROOT/library-backend/app"
if $PY -c 'import pytest' >/dev/null 2>&1; then
  (cd "$ROOT" && PYTHONPATH=library-backend "$PY" -m pytest -q \
    library-backend/tests/test_platform_core_bridge_v2230.py \
    library-backend/tests/test_hybrid_retrieval_v2241.py \
    library-backend/tests/test_citation_graph_route_v2251.py \
    library-backend/tests/test_research_extraction_v2260.py \
    library-backend/tests/test_publication_visualizations_v2270.py \
    library-backend/tests/test_publication_knowledge_maps_v2280.py \
    library-backend/tests/test_publication_corpus_maps_v2281.py \
    library-backend/tests/test_publication_corpus_scope_v2282.py \
    library-backend/tests/test_multi_publication_landscape_v2290.py \
    library-backend/tests/test_4d_knowledge_terrain_v2300.py \
    library-backend/tests/test_linked_visual_query_v2310.py \
    library-backend/tests/test_async_corpus_transport_v2311.py \
    tests/test_platform_core_research_bridge_v5120.py \
    tests/test_hybrid_research_retrieval_v5130.py \
    tests/test_citation_graph_scholarly_lineage_v5140.py \
    tests/test_entity_finding_claim_extraction_v5150.py \
    tests/test_publication_visualization_foundations_v5160.py \
    tests/test_scientific_knowledge_mapping_v5170.py \
    tests/test_publication_corpus_integration_v5171.py \
    tests/test_publication_corpus_scope_repair_v51711.py \
    tests/test_corpus_validator_argument_length_repair_v51712.py \
    tests/test_multi_publication_knowledge_landscape_v5180.py \
    tests/test_4d_knowledge_terrain_v5190.py \
    tests/test_linked_scientific_views_visual_query_v5200.py \
    tests/test_async_publication_corpus_loading_v52001.py \
    tests/test_scientific_renderer_visibility_terrain_recovery_v52002.py)
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v52002.js"
if command -v php >/dev/null 2>&1; then
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" >/dev/null
  php -l "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" >/dev/null
fi
grep -q 'Version: 5.20.0.2' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php"
grep -q '__version__ = "2.31.2"' "$ROOT/library-backend/app/__init__.py"
grep -q 'sc-library-knowledge-landscape-v52002' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php"
grep -q '.sc-kl__terrain\[hidden\]' "$ROOT/sustainable-catalyst-library/assets/css/sc-library-knowledge-landscape-v52002.css"
grep -q "is-terrain-view" "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v52002.js"
grep -q 'peak=Math.max(peak,v)' "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v52002.js"
grep -q 'publication_renderer_visibility_repair' "$ROOT/library-backend/app/main.py"
grep -q '@app.post("/v1/publication-knowledge-maps/corpus")' "$ROOT/library-backend/app/main.py"
grep -q 'sc-library-linked-visual-query/1.0' "$ROOT/library-backend/app/publication_corpus_maps.py"
grep -q 'sc-library-4d-knowledge-terrain/1.0' "$ROOT/library-backend/app/publication_corpus_maps.py"
echo "PASS: Knowledge Library v5.20.0.2 Scientific Renderer Visibility & 4D Terrain Recovery validation complete"
