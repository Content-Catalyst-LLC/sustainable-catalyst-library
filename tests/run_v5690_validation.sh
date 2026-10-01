#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== Knowledge Library v5.69.0 / backend v2.80.0 Python Domain Service Migration Foundation validation ==="
PYTHONPATH=library-backend python3 tests/test_python_domain_authority_v5690.py
python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/domain_authority.py \
  library-backend/app/independent_api.py \
  library-backend/app/client_framework.py \
  library-backend/app/runtime_independence.py \
  library-backend/app/release_engineering.py \
  library-backend/app/main.py
PYTHONPATH=library-backend python3 - <<'PY569'
from app.domain_authority import readiness
from app.runtime_independence import readiness as runtime_readiness
r=readiness(); assert r['state']=='ready' and r['backend_version']=='2.80.0' and r['library_version']=='5.69.0'
w=runtime_readiness(); assert w['library_version']=='5.69.0' and w['backend_version']=='2.80.0'
assert 'domain-authority' in w['default_certification']['probes']
print('PASS: Python domain authority participates in WordPress-failure certification')
PY569
PYTHONPATH=library-backend python3 - <<'PYCF'
from app.client_framework import readiness as client_readiness
r=client_readiness()
assert r['library_version']=='5.69.0' and r['backend_version']=='2.80.0'
assert r['state']=='ready' and r['product_adapter_count']==6
print('PASS: v5.68 client-framework capability preserved under v5.69 release identity')
PYCF
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0)
  echo 'PASS: WordPress PHP lint'
fi
if command -v node >/dev/null 2>&1; then node --check clients/javascript/src/index.js; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
git diff --check
echo 'PASS: Knowledge Library v5.69.0 Python Domain Service Migration Foundation validation complete'
