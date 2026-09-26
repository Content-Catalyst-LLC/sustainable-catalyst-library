#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.27.0 / backend v2.38.0 validation ==="

grep -q 'Version: 5.27.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.27.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.38.0"' library-backend/app/__init__.py
grep -q 'sc-library-source-identity-resolution/1.0' library-backend/app/source_identity_resolution.py
grep -q 'sc-library-source-identity-cluster/1.0' library-backend/app/source_identity_resolution.py
grep -q '"automatic_record_merge": False' library-backend/app/source_identity_resolution.py
grep -q '"title_only_identity_merge": False' library-backend/app/source_identity_resolution.py
grep -q '@app.post("/v1/source-identity/analyze")' library-backend/app/main.py
grep -q '@app.post("/v1/publication-knowledge-maps/source-identity")' library-backend/app/main.py
grep -q 'member-of-source-identity' library-backend/app/research_graph_pathfinding.py
grep -q 'duplicate-candidate' library-backend/app/research_graph_pathfinding.py
grep -q 'data-sc-kl-view="source-identity"' sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php
grep -q '/backend/publication-source-identity' sustainable-catalyst-library/includes/class-sc-library-python-backend.php

if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q \
    library-backend/tests/test_source_identity_resolution_v2380.py \
    tests/test_source_identity_dedup_entity_resolution_v5270.py \
    library-backend/tests/test_scientific_document_intelligence_v2370.py \
    library-backend/tests/test_research_graph_pathfinding_v2360.py \
    library-backend/tests/test_evidence_synthesis_v2350.py \
    library-backend/tests/test_visual_research_sessions_v2320.py
else
  echo "SKIP: pytest is not installed locally; packaged dependency-free release tests were validated before distribution."
fi

python3 -m compileall -q library-backend/app
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-python-backend.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5270.js
bash -n install_and_push_sustainable_catalyst_library_v5_27_0_macos.sh
bash -n upgrade_library_backend_v2_38_0_contabo.sh
python3 - <<'PY'
import json
for path in (
    'docs/schemas/scientific-object.json',
    'docs/schemas/scientific-document-intelligence.json',
    'docs/schemas/source-identity-cluster.json',
    'docs/schemas/source-identity-resolution.json',
):
    with open(path, encoding='utf-8') as fh:
        json.load(fh)
print('PASS: JSON schemas parse')
PY

echo "PASS: Knowledge Library v5.27.0 Source Identity, Deduplication & Entity Resolution validation complete"
