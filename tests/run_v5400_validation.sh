#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
echo "=== Knowledge Library v5.40.0 / backend v2.51.0 / Scientific Embedding Governance & Compute Handoff validation ==="
grep -q 'Version: 5.40.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.40.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.51.0"' library-backend/app/__init__.py
grep -q 'sc-library-embedding-governance/1.0' library-backend/app/embedding_governance.py
grep -q 'sc-core-compatible-embedding-specification/1.0' library-backend/app/embedding_governance.py
grep -q 'sc-core-compatible-embedding-representation/1.0' library-backend/app/embedding_governance.py
grep -q 'sc-library-workspace-embedding-handoff/1.0' library-backend/app/embedding_governance.py
grep -q 'library_embedding_compute_handoffs' library-backend/app/schema.sql
grep -q 'SC_LIBRARY_EMBEDDING_COMPUTE_TARGET=local' library-backend/.env.example
grep -q 'version  = "0.1.0"' library-backend/go-ingestion-runtime/main.go
grep -q 'version = "0.2.0"' library-backend/native-graph-runtime/Cargo.toml
test ! -d library-backend/native-graph-runtime/target
test ! -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime

pytest -q tests/test_scientific_embedding_governance_v5400.py
pytest -q tests/test_knowledge_landscape_desktop_semantic_repair_v53901.py -k 'not release_identity'

if python3 - <<'PY' >/dev/null 2>&1
import psycopg, httpx
PY
then
  PYTHONPATH=library-backend pytest -q library-backend/tests/test_embedding_governance_v2510.py
else
  echo "INFO: psycopg/httpx unavailable locally; backend contract unit tests remain mandatory in Docker/production dependency environment."
fi

if command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
else
  echo "INFO: go unavailable locally; Go compilation remains mandatory in production Docker build."
fi
if command -v cargo >/dev/null 2>&1; then
  CARGO_TARGET_DIR_TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v5400-cargo.XXXXXX")"
  trap 'rm -rf "$CARGO_TARGET_DIR_TMP"' EXIT
  CARGO_TARGET_DIR="$CARGO_TARGET_DIR_TMP" cargo test --manifest-path library-backend/native-graph-runtime/Cargo.toml --locked
  rm -rf "$CARGO_TARGET_DIR_TMP"; trap - EXIT
else
  echo "INFO: cargo unavailable locally; Rust compilation remains mandatory in production Docker build."
fi

python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js
python3 - <<'PY'
import json
for p in [
 'docs/schemas/embedding-specification.json',
 'docs/schemas/embedding-representation.json',
 'docs/schemas/embedding-compute-handoff.json',
 'docs/schemas/research-runtime-contract.json',
 'docs/schemas/execution-lineage.json',
 'docs/schemas/reproducibility-record.json']:
    json.load(open(p,encoding='utf-8'))
print('PASS: v5.40.0 JSON schemas parse')
PY

echo "PASS: Knowledge Library v5.40.0 Scientific Embedding Governance & Compute Handoff validation complete"
