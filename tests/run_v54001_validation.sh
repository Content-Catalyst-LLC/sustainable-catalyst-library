#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.40.0.1 / backend v2.51.1 / Embedding Backfill Timestamp Repair validation ==="
grep -q 'Version: 5.40.0.1' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.40.0.1');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.51.1"' library-backend/app/__init__.py
python3 -m pytest -q tests/test_embedding_backfill_timestamp_repair_v54001.py tests/test_scientific_embedding_governance_v5400.py
python3 -m pytest -q tests/test_knowledge_landscape_desktop_semantic_repair_v53901.py -k 'not release_identity_and_backend_patch_version'
python3 -m compileall -q library-backend/app
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
if command -v cargo >/dev/null 2>&1; then (cd library-backend/native-graph-runtime && CARGO_TARGET_DIR="$(mktemp -d /tmp/sc-library-v54001-cargo.XXXXXX)" cargo test --locked); fi
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null; fi
python3 - <<'PY'
import json
from pathlib import Path
for p in Path('docs/schemas').glob('embedding-*.json'):
    json.loads(p.read_text())
print('PASS: embedding JSON schemas parse')
PY
echo "PASS: Knowledge Library v5.40.0.1 repair validation complete"
