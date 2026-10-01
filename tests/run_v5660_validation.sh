#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== v5.66.0 / backend v2.77.0 validation ==="
PYTHONPATH=library-backend python3 - <<'PY566'
from app.release_engineering import release_manifest,validate_deployment_plan,readiness
m=release_manifest(); assert m['library_version']=='5.66.0' and m['backend_version']=='2.77.0' and not m['wordpress']['required']
p={'environment':'production','artifacts':[{'name':'backend.zip','sha256':'a'*64}],'rollback_plan':{'strategy':'previous-image'}}
assert validate_deployment_plan(p)['valid']; assert readiness()['state']=='ready'
print('PASS: independent release manifest, preflight and rollback contract')
PY566
python3 -m py_compile library-backend/app/release_engineering.py library-backend/app/main.py
python3 - <<'PY566J'
import json
from pathlib import Path
json.loads(Path('docs/library-api-v1-openapi.json').read_text()); print('PASS: OpenAPI parses')
PY566J
if command -v php >/dev/null 2>&1; then php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null; echo 'PASS: WordPress PHP lint'; fi
python3 - <<'PY566C'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}; print('PASS: clean repository root')
PY566C
echo 'PASS: Knowledge Library v5.66.0 Independent Library Release & Deployment Engineering validation complete'
