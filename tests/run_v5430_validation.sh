#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.43.0 / backend v2.54.0 / Publication Embedding Maps & Semantic Knowledge Landscape validation ==="

python3 - <<'PY'
import runpy
suite = [
    ('tests/test_publication_embedding_maps_v5430.py', set()),
    ('tests/test_neural_reranking_v5420.py', {'test_release_identity'}),
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
  library-backend/app/publication_embedding_maps.py \
  library-backend/app/publication_corpus_maps.py \
  library-backend/app/neural_reranking.py \
  library-backend/app/representation_search.py \
  library-backend/app/embedding_governance.py \
  library-backend/app/main.py \
  library-backend/app/settings.py

python3 - <<'PY'
import json
from pathlib import Path
for name in [
 'docs/schemas/publication-embedding-map.json',
 'docs/schemas/semantic-knowledge-landscape.json',
 'docs/schemas/semantic-neighborhood.json',
 'docs/schemas/neural-reranker-specification.json',
 'docs/schemas/neural-reranking-response.json',
 'docs/schemas/semantic-similarity-result.json',
 'docs/schemas/embedding-specification.json',
]:
    json.loads(Path(name).read_text(encoding='utf-8'))
print('PASS: v5.43.0 and preserved neural/semantic/embedding JSON schemas parse')
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
  CARGO_TMP_DIR="$(mktemp -d /tmp/sc-library-v5430-cargo.XXXXXX)"
  CARGO_TARGET_DIR="$CARGO_TMP_DIR" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TMP_DIR"
else
  echo "INFO: cargo unavailable; production Docker Rust build remains mandatory"
fi

grep -q 'Version: 5.43.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.43.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.54.0"' library-backend/app/__init__.py
grep -q 'sc-library-publication-embedding-map/1.0' library-backend/app/publication_embedding_maps.py
grep -q 'sc-library-semantic-knowledge-landscape/1.0' library-backend/app/publication_embedding_maps.py
grep -q 'data-sc-kl-view="semantic-embedding-map"' sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php

echo "PASS: Knowledge Library v5.43.0 Publication Embedding Maps & Semantic Knowledge Landscape validation complete"
