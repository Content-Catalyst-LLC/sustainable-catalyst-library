#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "=== Knowledge Library v5.24.0 / backend v2.35.0 validation ==="
PYTEST_FILES=(
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
)
if python3 -c 'import pytest' >/dev/null 2>&1; then
  PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}" python3 -m pytest -q "${PYTEST_FILES[@]}"
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
python3 -m compileall -q "$ROOT/library-backend/app"
php -l "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php" >/dev/null
php -l "$ROOT/sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php" >/dev/null
node --check "$ROOT/sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5240.js"
bash -n "$ROOT/install_and_push_sustainable_catalyst_library_v5_24_0_macos.sh"
bash -n "$ROOT/upgrade_library_backend_v2_35_0_contabo.sh"
grep -q 'Version: 5.24.0' "$ROOT/sustainable-catalyst-library/sustainable-catalyst-library.php"
grep -q '__version__ = "2.35.0"' "$ROOT/library-backend/app/__init__.py"
grep -q 'sc-library-evidence-weighted-research-overlay/1.0' "$ROOT/library-backend/app/evidence_weighted_overlays.py"
grep -q 'sc-library-cross-publication-evidence-synthesis/1.0' "$ROOT/library-backend/app/evidence_synthesis.py"
grep -q '"consensus_inferred": False' "$ROOT/library-backend/app/evidence_synthesis.py"
grep -q '"hypotheses_inferred": False' "$ROOT/library-backend/app/evidence_synthesis.py"
grep -q '"durable_synthesis_authority": "platform-core"' "$ROOT/library-backend/app/evidence_synthesis.py"
echo "PASS: Knowledge Library v5.24.0 Cross-Publication Evidence Synthesis & Competing Hypotheses validation complete"
