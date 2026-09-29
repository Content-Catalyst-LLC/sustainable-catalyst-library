#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.42.0 / backend v2.53.0 / Neural Reranking & Retrieval Evaluation validation ==="

# Dependency-free mandatory Python assertion runner. This intentionally avoids
# requiring pytest on the release workstation.
python3 - <<'PY'
import runpy
suite = [
    ('tests/test_neural_reranking_v5420.py', set()),
    ('tests/test_semantic_similarity_representation_search_v5410.py', {'test_release_identity'}),
    ('tests/test_scientific_embedding_governance_v5400.py', {'test_release_identity'}),
    ('tests/test_embedding_backfill_timestamp_repair_v54001.py', {'test_release_identity'}),
]
total=0
for path, skip in suite:
    ns=runpy.run_path(path)
    count=0
    for name, fn in sorted(ns.items()):
        if name.startswith('test_') and callable(fn) and name not in skip:
            fn(); count += 1; total += 1
    print(f"PASS: {path}: {count} assertions")
print(f"PASS: {total} mandatory Python release assertions")
PY

python3 -m py_compile \
  library-backend/app/neural_reranking.py \
  library-backend/app/retrieval_evaluation.py \
  library-backend/app/hybrid_retrieval.py \
  library-backend/app/representation_search.py \
  library-backend/app/embedding_governance.py \
  library-backend/app/main.py \
  library-backend/app/settings.py

python3 - <<'PY'
import json
from pathlib import Path
for name in [
 'docs/schemas/neural-reranker-specification.json',
 'docs/schemas/neural-reranking-response.json',
 'docs/schemas/neural-reranking-evaluation.json',
 'docs/schemas/semantic-similarity-result.json',
 'docs/schemas/representation-search-response.json',
 'docs/schemas/embedding-specification.json',
]:
    json.loads(Path(name).read_text(encoding='utf-8'))
print('PASS: v5.42.0 and preserved semantic/embedding JSON schemas parse')
PY

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.php' -print0)
  echo "PASS: WordPress PHP lint"
else
  echo "INFO: php unavailable; production/WordPress lint remains required"
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do node --check "$f" >/dev/null; done < <(find sustainable-catalyst-library -type f -name '*.js' -print0)
  echo "PASS: WordPress JavaScript syntax"
else
  echo "INFO: node unavailable; JavaScript syntax check skipped locally"
fi

if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: go unavailable; production Docker Go test remains mandatory"
fi

if command -v cargo >/dev/null 2>&1; then
  CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5420-cargo.XXXXXX)"
  CARGO_TARGET_DIR="$CARGO_TMP_DIR" \
    cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TMP_DIR"
else
  echo "INFO: cargo unavailable; production Docker Rust build remains mandatory"
fi

grep -q 'Version: 5.42.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.42.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.53.0"' library-backend/app/__init__.py
grep -q 'sc-library-neural-reranking/1.0' library-backend/app/neural_reranking.py
grep -q 'SC_LIBRARY_RERANK_PROVIDER=disabled' library-backend/.env.example

echo "PASS: Knowledge Library v5.42.0 Neural Reranking & Retrieval Evaluation validation complete"
