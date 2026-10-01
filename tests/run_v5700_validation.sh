#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.70.0 / backend v2.81.0 Python Catalog, Publication & Research Object Service validation ==="
PYTHONPATH=library-backend python3 tests/test_python_catalog_service_v5700.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/catalog_service.py \
  library-backend/app/domain_authority.py \
  library-backend/app/independent_api.py \
  library-backend/app/client_framework.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/release_engineering.py \
  library-backend/app/main.py \
  clients/python/sustainable_catalyst_library/client.py
PYTHONPATH=library-backend python3 - <<'PY570'
from app.domain_authority import readiness as domain_readiness, contract as domain_contract
from app.runtime_independence import readiness as runtime_readiness
from app.client_framework import readiness as client_readiness
d=domain_readiness(); assert d['state']=='ready' and d['library_version']=='5.70.0' and d['backend_version']=='2.81.0'
assert domain_contract()['domains']['publication-catalog-write']['authority']=='python-backend'
r=runtime_readiness(); assert r['library_version']=='5.70.0' and r['backend_version']=='2.81.0' and 'catalog-service' in r['default_certification']['probes']
c=client_readiness(); assert c['sdk_version']=='0.2.0' and c['features']['catalog_domain_client'] is True
print('PASS: catalog service is Python-authoritative and participates in WordPress-failure certification')
PY570
python3 - <<'PYJSON'
import json
for p in ['docs/library-api-v1-openapi.json','docs/php-domain-retirement-inventory.json','docs/schemas/library-catalog-service.json','docs/schemas/library-research-object.json']:
    json.load(open(p))
print('PASS: JSON contracts parse')
PYJSON
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; echo 'PASS: JavaScript client syntax'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
git diff --check
echo 'PASS: Knowledge Library v5.70.0 Python Catalog, Publication & Research Object Service validation complete'
