#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.78.0 / backend v2.89.0 Python Background Job & Workflow Consolidation validation ==="

python3 tests/test_python_background_workflows_v5780.py

python3 -m py_compile   library-backend/app/__init__.py   library-backend/app/background_workflow_service.py   library-backend/app/connector_federation_service.py   library-backend/app/research_package_service.py   library-backend/app/durable_job_queue.py   library-backend/app/specialized_worker_runtime.py   library-backend/app/checkpointed_pipeline.py   library-backend/app/ingestion_job_fabric.py   library-backend/app/domain_authority.py   library-backend/app/independent_api.py   library-backend/app/client_framework.py   library-backend/app/runtime_independence.py   library-backend/app/main.py   clients/python/sustainable_catalyst_library/client.py

python3 - <<'PYJSON'
import json
for p in [
    "docs/library-api-v1-openapi.json",
    "docs/php-domain-retirement-inventory.json",
    "docs/schemas/library-background-workflow-service.json",
    "docs/schemas/library-workflow-control-validation.json",
]:
    json.load(open(p))
print("PASS: JSON contracts parse")
PYJSON

if command -v node >/dev/null 2>&1; then
  node --check clients/javascript/src/index.js
  echo "PASS: JavaScript client syntax"
fi

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0)
  echo "PASS: WordPress PHP lint"
fi

if [[ -d library-backend/go-ingestion-runtime ]] && command -v go >/dev/null 2>&1; then
  (cd library-backend/go-ingestion-runtime && go test ./...)
fi

git diff --check

echo "PASS: Knowledge Library v5.78.0 Python Background Job & Workflow Consolidation validation complete"
