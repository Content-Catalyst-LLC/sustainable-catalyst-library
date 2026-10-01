#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.68.0 / backend v2.79.0 Library SDK, Client & Integration Framework validation ==="
PYTHONPATH=library-backend python3 tests/test_client_framework_v5680.py
python3 -m py_compile library-backend/app/__init__.py library-backend/app/client_framework.py library-backend/app/independent_api.py library-backend/app/runtime_independence.py library-backend/app/release_engineering.py library-backend/app/cross_product_integration.py library-backend/app/main.py clients/python/sustainable_catalyst_library/client.py
PYTHONPATH=library-backend python3 - <<'PY568'
from app.client_framework import readiness
from app.runtime_independence import readiness as runtime_readiness
r=readiness(); assert r['state']=='ready' and r['backend_version']=='2.79.0' and r['library_version']=='5.68.0'
w=runtime_readiness(); assert w['library_version']=='5.68.0' and w['backend_version']=='2.79.0' and 'client-framework' in w['default_certification']['probes']
print('PASS: backend SDK/client readiness and WordPress-independent certification contract')
PY568
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; node --check library-web/assets/app.js; echo 'PASS: JavaScript syntax'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
git diff --check
echo 'PASS: Knowledge Library v5.68.0 Library SDK, Client & Integration Framework validation complete'
