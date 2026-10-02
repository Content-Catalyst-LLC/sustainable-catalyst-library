#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.74.0 / backend v2.85.0 Python Provenance, Citation & Evidence Graph Service validation ==="
python3 tests/test_python_provenance_graph_v5740.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/provenance_graph_service.py \
  library-backend/app/retrieval_orchestration.py \
  library-backend/app/source_ingestion.py \
  library-backend/app/research_state.py \
  library-backend/app/catalog_service.py \
  library-backend/app/domain_authority.py \
  library-backend/app/independent_api.py \
  library-backend/app/client_framework.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/main.py \
  clients/python/sustainable_catalyst_library/client.py
python3 - <<'PYJSON'
import json
for p in ['docs/library-api-v1-openapi.json','docs/php-domain-retirement-inventory.json','docs/schemas/library-provenance-citation-evidence-graph.json','docs/schemas/library-record-provenance.json','docs/schemas/library-evidence-graph.json']:
    json.load(open(p))
print('PASS: JSON contracts parse')
PYJSON
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; echo 'PASS: JavaScript client syntax'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
git diff --check
echo 'PASS: Knowledge Library v5.74.0 Python Provenance, Citation & Evidence Graph Service validation complete'
