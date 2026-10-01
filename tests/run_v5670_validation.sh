#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
echo "=== v5.67.0 / backend v2.78.0 validation ==="
PYTHONPATH=library-backend python3 - <<'PY567'
from app.runtime_independence import evaluate,default_probe_payload,readiness,REQUIRED_PROBES
c=evaluate(default_probe_payload()); assert c['certified'] and len(c['probes'])==len(REQUIRED_PROBES)
p=default_probe_payload(); p['probes']['api']={'ready':False}; assert not evaluate(p)['certified']
p=default_probe_payload(); p['wordpress']['state']='ready'; assert not evaluate(p)['certified']
r=readiness(); assert r['state']=='ready' and r['wordpress_dependency_count']==0
print('PASS: WordPress-failure runtime certification contract and negative controls')
PY567
python3 -m py_compile library-backend/app/runtime_independence.py library-backend/app/release_engineering.py library-backend/app/main.py
python3 - <<'PY567J'
import json
from pathlib import Path
json.loads(Path('docs/library-api-v1-openapi.json').read_text()); php=Path('sustainable-catalyst-library/includes/class-sc-library-runtime-certification.php').read_text(); assert 'WP_REST_Server::READABLE' in php and 'optional-thin-adapter' in php; print('PASS: OpenAPI and read-only WordPress certification adapter')
PY567J
if command -v node >/dev/null 2>&1; then node --check library-web/assets/app.js; echo 'PASS: Library Web JavaScript syntax'; fi
if command -v php >/dev/null 2>&1; then while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0); echo 'PASS: WordPress PHP lint'; fi
if command -v go >/dev/null 2>&1; then (cd library-backend/go-ingestion-runtime && go test ./...); fi
python3 - <<'PY567C'
from pathlib import Path
assert {p.name for p in Path('.').iterdir() if p.is_file()} <= {'.gitignore','README.md','CHANGELOG.md'}; print('PASS: clean repository root')
PY567C
echo 'PASS: Knowledge Library v5.67.0 WordPress-Failure Independence & Runtime Certification validation complete'
