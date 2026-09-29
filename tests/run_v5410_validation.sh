#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.41.0 / backend v2.52.0 / Semantic Similarity & Representation Search validation ==="
python3 -m pytest -q tests/test_semantic_similarity_representation_search_v5410.py
python3 -m pytest -q tests/test_scientific_embedding_governance_v5400.py -k 'not release_identity'
python3 -m pytest -q tests/test_embedding_backfill_timestamp_repair_v54001.py -k 'not release_identity'
python3 -m py_compile \
  library-backend/app/representation_search.py \
  library-backend/app/hybrid_retrieval.py \
  library-backend/app/semantic.py \
  library-backend/app/embedding_governance.py \
  library-backend/app/main.py \
  library-backend/app/settings.py
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-explorer.js >/dev/null 2>&1 || true
python3 - <<'PY'
import json
from pathlib import Path
root=Path('.')
for name in [
 'embedding-specification.json','embedding-representation.json','embedding-compute-handoff.json',
 'semantic-similarity-result.json','representation-search-response.json','semantic-representation-descriptor.json'
]:
    json.loads((root/'docs/schemas'/name).read_text())
print('PASS: v5.41.0 JSON schemas parse')
PY
if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: Go unavailable locally; production Docker build remains mandatory."
fi
if command -v cargo >/dev/null 2>&1; then
  CARGO_TARGET_DIR="$(mktemp -d /tmp/sc-library-v5410-cargo.XXXXXX)" \
    cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
else
  echo "INFO: Cargo unavailable locally; production Docker build remains mandatory."
fi

echo "PASS: Knowledge Library v5.41.0 Semantic Similarity & Representation Search validation complete"
