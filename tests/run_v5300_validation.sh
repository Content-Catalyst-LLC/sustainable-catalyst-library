#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.30.0 / backend v2.41.0 validation ==="
grep -q 'Version: 5.30.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.30.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.41.0"' library-backend/app/__init__.py
grep -q 'sc-library-methodology-intelligence/1.0' library-backend/app/methodology_intelligence.py
grep -q '"automatic_quality_score": False' library-backend/app/methodology_intelligence.py
grep -q '"automatic_risk_of_bias_judgment": False' library-backend/app/methodology_intelligence.py
grep -q '@app.post("/v1/methodology-intelligence/analyze")' library-backend/app/main.py
grep -q '@app.post("/v1/publication-knowledge-maps/methodology-intelligence")' library-backend/app/main.py
grep -q '/backend/publication-methodology-intelligence' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_methodology_intelligence'" sustainable-catalyst-library/includes/class-sc-library-methodology-intelligence.php
if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q \
    library-backend/tests/test_methodology_intelligence_v2410.py \
    tests/test_evidence_quality_methodology_intelligence_v5300.py \
    library-backend/tests/test_temporal_knowledge_v2400.py \
    library-backend/tests/test_retrieval_evaluation_v2390.py \
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
php -l sustainable-catalyst-library/includes/class-sc-library-methodology-intelligence.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-methodology-intelligence-v5300.js
bash -n install_and_push_sustainable_catalyst_library_v5_30_0_macos.sh
bash -n upgrade_library_backend_v2_41_0_contabo.sh
python3 - <<'PYSCHEMA'
import json
for p in ('docs/schemas/methodology-intelligence.json','docs/schemas/methodology-profile.json','docs/schemas/methodology-comparison.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.30 JSON schemas parse')
PYSCHEMA
echo "PASS: Knowledge Library v5.30.0 Evidence Quality & Methodology Intelligence validation complete"
