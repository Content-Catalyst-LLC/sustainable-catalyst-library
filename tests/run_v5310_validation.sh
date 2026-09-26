#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.31.0 / backend v2.42.0 validation ==="
grep -q 'Version: 5.31.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.31.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.42.0"' library-backend/app/__init__.py
grep -q 'sc-library-research-gap-novelty/1.0' library-backend/app/research_gap_novelty.py
grep -q '"gap_signal_proves_global_absence": False' library-backend/app/research_gap_novelty.py
grep -q '"novelty_candidate_is_novelty_claim": False' library-backend/app/research_gap_novelty.py
grep -q '@app.post("/v1/research-gap-novelty/analyze")' library-backend/app/main.py
grep -q '@app.post("/v1/publication-knowledge-maps/research-gap-novelty")' library-backend/app/main.py
grep -q '/backend/publication-research-gap-novelty' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_research_gap_novelty'" sustainable-catalyst-library/includes/class-sc-library-research-gap-novelty.php
if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q \
    library-backend/tests/test_research_gap_novelty_v2420.py \
    tests/test_research_gap_novelty_v5310.py \
    library-backend/tests/test_methodology_intelligence_v2410.py \
    library-backend/tests/test_temporal_knowledge_v2400.py \
    library-backend/tests/test_retrieval_evaluation_v2390.py \
    library-backend/tests/test_source_identity_resolution_v2380.py \
    library-backend/tests/test_scientific_document_intelligence_v2370.py \
    library-backend/tests/test_research_graph_pathfinding_v2360.py \
    library-backend/tests/test_evidence_synthesis_v2350.py \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_methodology_runtime_and_guardrails \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_backend_routes_and_health_capabilities \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_corpus_exposes_methodology_as_first_class_view \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_graph_keeps_methodology_out_of_default_evidence_path \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_wordpress_console_and_proxy_routes \
    tests/test_evidence_quality_methodology_intelligence_v5300.py::test_previous_capabilities_are_preserved
fi
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-research-gap-novelty.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-research-gap-novelty-v5310.js
bash -n install_and_push_sustainable_catalyst_library_v5_31_0_macos.sh
bash -n upgrade_library_backend_v2_42_0_contabo.sh
python3 - <<'PY'
import json
for p in ('docs/schemas/research-gap-novelty.json','docs/schemas/research-gap-candidate.json','docs/schemas/novelty-candidate.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.31 JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.31.0 Research Gap & Novelty Discovery validation complete"
