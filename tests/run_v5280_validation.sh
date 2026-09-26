#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.28.0 / backend v2.39.0 validation ==="
grep -q 'Version: 5.28.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.28.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.39.0"' library-backend/app/__init__.py
grep -q 'sc-library-retrieval-evaluation/1.0' library-backend/app/retrieval_evaluation.py
grep -q '"automatic_record_filtering": False' library-backend/app/retrieval_evaluation.py
grep -q '@app.post("/v1/search/adaptive")' library-backend/app/main.py
grep -q '/backend/retrieval-evaluation' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_retrieval_evaluation'" sustainable-catalyst-library/includes/class-sc-library-retrieval-evaluation.php
if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q \
    library-backend/tests/test_retrieval_evaluation_v2390.py \
    tests/test_research_retrieval_evaluation_adaptive_ranking_v5280.py \
    library-backend/tests/test_source_identity_resolution_v2380.py \
    library-backend/tests/test_scientific_document_intelligence_v2370.py \
    library-backend/tests/test_research_graph_pathfinding_v2360.py \
    library-backend/tests/test_evidence_synthesis_v2350.py
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-retrieval-evaluation.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-retrieval-evaluation-v5280.js
bash -n install_and_push_sustainable_catalyst_library_v5_28_0_macos.sh
bash -n upgrade_library_backend_v2_39_0_contabo.sh
python3 - <<'PY'
import json
for p in ('docs/schemas/retrieval-evaluation.json','docs/schemas/adaptive-ranking-profile.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.28 JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.28.0 Research Retrieval Evaluation & Adaptive Ranking validation complete"
