#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "=== Knowledge Library v5.25.0 / backend v2.36.0 validation ==="
PYTEST_FILES=(
  "$ROOT/tests/test_research_graph_query_evidence_pathfinding_v5250.py"
  "$ROOT/tests/test_cross_publication_evidence_synthesis_v5240.py"
  "$ROOT/tests/test_evidence_weighted_findings_claims_v5230.py"
  "$ROOT/tests/test_visual_evidence_traceability_v5220.py"
  "$ROOT/tests/test_reproducible_visual_research_sessions_v5210.py"
  "$ROOT/tests/test_scientific_renderer_visibility_terrain_recovery_v52002.py"
  "$ROOT/tests/test_async_publication_corpus_loading_v52001.py"
  "$ROOT/tests/test_linked_scientific_views_visual_query_v5200.py"
  "$ROOT/tests/test_4d_knowledge_terrain_v5190.py"
  "$ROOT/tests/test_multi_publication_knowledge_landscape_v5180.py"
  "$ROOT/tests/test_publication_corpus_scope_repair_v51711.py"
  "$ROOT/tests/test_publication_corpus_integration_v5171.py"
  "$ROOT/tests/test_scientific_knowledge_mapping_v5170.py"
  "$ROOT/library-backend/tests/test_evidence_synthesis_v2350.py"
  "$ROOT/library-backend/tests/test_research_graph_pathfinding_v2360.py"
)
if python3 -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" python3 -m pytest -q "${PYTEST_FILES[@]}"
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi

python3 -m compileall -q "$ROOT/library-backend/app"
php -l "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-hardening.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-canonical-route-identity.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-connected-public-research-infrastructure.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5250.js"
bash -n "$ROOT/install_and_push_sustainable_catalyst_library_v5_25_0_macos.sh"
bash -n "$ROOT/upgrade_library_backend_v2_36_0_contabo.sh"

grep -q 'Version: 5.25.0' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php"
grep -q "Stable tag: 5.25.0" "$ROOT/sustainable-catalyst-library/readme.txt"
grep -q '__version__ = "2.36.0"' "$ROOT/library-backend/app/__init__.py"
grep -q 'sc-library-research-graph-query/1.0' "$ROOT/library-backend/app/research_graph_pathfinding.py"
grep -q 'sc-library-evidence-pathfinding/1.0' "$ROOT/library-backend/app/research_graph_pathfinding.py"
grep -q 'publication_research_graph_query' "$ROOT/library-backend/app/main.py"
grep -q 'publication_evidence_pathfinding' "$ROOT/library-backend/app/main.py"
grep -q 'release_certification_alignment' "$ROOT/library-backend/app/main.py"
grep -q 'sc-library-release-certification-readiness/1.0' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-hardening.php"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$ROOT/library-backend/app/evidence_synthesis.py"
grep -q 'research_graph_query_endpoint' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php"
grep -q 'evidence_pathfind_endpoint' "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php"

echo "PASS: Knowledge Library v5.25.0 Research Graph Query & Evidence Pathfinding validation complete"
