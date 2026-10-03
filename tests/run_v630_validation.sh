#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v6.3.0 / backend v3.3.0 Research Projects & Saved Workspaces validation ==="

python3 tests/test_research_projects_saved_workspaces_v630.py

python3 -m py_compile \
  library-backend/app/__init__.py \
  library-backend/app/saved_workspaces.py \
  library-backend/app/research_state.py \
  library-backend/app/research_interface.py \
  library-backend/app/navigation_service.py \
  library-backend/app/independent_library_product.py \
  library-backend/app/web_application.py \
  library-backend/app/independent_api.py \
  library-backend/app/domain_authority.py \
  library-backend/app/client_framework.py \
  library-backend/app/main.py \
  clients/python/sustainable_catalyst_library/client.py

python3 - <<'PYJSON'
import json
for p in [
    "docs/library-api-v1-openapi.json",
    "docs/php-domain-retirement-inventory.json",
]:
    json.load(open(p))
print("PASS: JSON contracts parse")
PYJSON

if command -v node >/dev/null 2>&1; then
  node --check clients/javascript/src/index.js
  node --check library-web/assets/app.js
  node --check library-web/config.js
  echo "PASS: JavaScript syntax"
fi

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null
  done < <(find sustainable-catalyst-library -name '*.php' -print0)
  echo "PASS: WordPress PHP lint"
fi

git diff --check

echo "PASS: Knowledge Library v6.3.0 Research Projects & Saved Workspaces validation complete"
