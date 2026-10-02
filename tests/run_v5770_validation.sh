#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "=== Knowledge Library v5.77.0 / backend v2.88.0 Python Connector & Federation Runtime validation ==="
python3 tests/test_python_connector_federation_v5770.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/connector_federation_service.py \
  library-backend/app/research_package_service.py \
  library-backend/app/language_document_service.py \
  library-backend/app/provenance_graph_service.py \
  library-backend/app/retrieval_orchestration.py \
  library-backend/app/domain_authority.py \
  library-backend/app/independent_api.py \
  library-backend/app/client_framework.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/main.py \
  clients/python/sustainable_catalyst_library/client.py
python3 - <<'PYJSON'
import json
for p in [
  'docs/library-api-v1-openapi.json',
  'docs/php-domain-retirement-inventory.json',
  'docs/schemas/library-connector-federation-runtime.json',
  'docs/schemas/library-connector-execution-plan.json',
  'docs/schemas/library-connector-runtime-status.json',
]:
    json.load(open(p))
print('PASS: JSON contracts parse')
PYJSON
if command -v node >/dev/null 2>&1; then
  node --check clients/javascript/src/index.js
  echo 'PASS: JavaScript client syntax'
fi
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0)
  echo 'PASS: WordPress PHP lint'
fi
if [[ -d library-backend/go-ingestion-runtime ]] && command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
fi
git diff --check
echo 'PASS: Knowledge Library v5.77.0 Python Connector & Federation Runtime validation complete'
