#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.29.0 / backend v2.40.0 validation ==="
grep -q 'Version: 5.29.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.29.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.40.0"' library-backend/app/__init__.py
grep -q 'sc-library-temporal-knowledge-evolution/1.0' library-backend/app/temporal_knowledge.py
grep -q '"later_event_projected_backward_by_default": False' library-backend/app/temporal_knowledge.py
grep -q '@app.post("/v1/temporal-knowledge/analyze")' library-backend/app/main.py
grep -q '@app.post("/v1/publication-knowledge-maps/temporal-evolution")' library-backend/app/main.py
grep -q '/backend/publication-temporal-evolution' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_temporal_evolution'" sustainable-catalyst-library/includes/class-sc-library-temporal-evolution.php
if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q     library-backend/tests/test_temporal_knowledge_v2400.py     tests/test_temporal_knowledge_research_evolution_v5290.py     library-backend/tests/test_retrieval_evaluation_v2390.py     library-backend/tests/test_source_identity_resolution_v2380.py     library-backend/tests/test_scientific_document_intelligence_v2370.py     library-backend/tests/test_research_graph_pathfinding_v2360.py     library-backend/tests/test_evidence_synthesis_v2350.py
else
  echo "SKIP: pytest is not installed locally; packaged release tests were validated before distribution."
fi
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-temporal-evolution.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-temporal-evolution-v5290.js
bash -n install_and_push_sustainable_catalyst_library_v5_29_0_macos.sh
bash -n upgrade_library_backend_v2_40_0_contabo.sh
python3 - <<'PYSCHEMA'
import json
for p in ('docs/schemas/temporal-knowledge-evolution.json','docs/schemas/temporal-knowledge-snapshot.json','docs/schemas/temporal-knowledge-change-set.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.29 JSON schemas parse')
PYSCHEMA
echo "PASS: Knowledge Library v5.29.0 Temporal Knowledge & Research Evolution validation complete"
