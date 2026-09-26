#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.33.0 / backend v2.44.0 validation ==="
grep -q 'Version: 5.33.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.33.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.44.0"' library-backend/app/__init__.py
grep -q 'sc-library-literature-review/1.0' library-backend/app/literature_review.py
grep -q '@app.post("/v1/literature-reviews/build")' library-backend/app/main.py
grep -q '@app.post("/v1/literature-reviews/compare")' library-backend/app/main.py
grep -q '/backend/literature-review-build' sustainable-catalyst-library/includes/class-sc-library-python-backend.php
grep -q "SHORTCODE = 'sc_library_literature_review'" sustainable-catalyst-library/includes/class-sc-library-literature-review.php
test ! -d library-backend/native-graph-runtime/target
if command -v cargo >/dev/null 2>&1; then
  cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
else
  echo "INFO: cargo unavailable locally; Rust compilation remains mandatory in production Docker build."
fi
PYTHONPATH=library-backend pytest -q \
  library-backend/tests/test_literature_review_v2440.py \
  tests/test_reproducible_literature_review_v5330.py \
  library-backend/tests/test_native_graph_runtime_v2430.py \
  library-backend/tests/test_research_gap_novelty_v2420.py \
  library-backend/tests/test_methodology_intelligence_v2410.py \
  library-backend/tests/test_temporal_knowledge_v2400.py \
  library-backend/tests/test_retrieval_evaluation_v2390.py \
  library-backend/tests/test_source_identity_resolution_v2380.py \
  library-backend/tests/test_scientific_document_intelligence_v2370.py \
  library-backend/tests/test_research_graph_pathfinding_v2360.py \
  library-backend/tests/test_evidence_synthesis_v2350.py
python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-literature-review.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-literature-review-v5330.js
python3 - <<'PY'
import json
for p in ('docs/schemas/literature-review.json','docs/schemas/review-protocol.json','docs/schemas/review-change-set.json'):
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.33 JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.33.0 Reproducible Literature Review Engine validation complete"
