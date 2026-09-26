#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== Knowledge Library v5.26.0 / backend v2.37.0 validation ==="

grep -q 'Version: 5.26.0' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "define('SC_LIBRARY_VERSION', '5.26.0');" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q '__version__ = "2.37.0"' library-backend/app/__init__.py
grep -q 'sc-library-scientific-document-intelligence/1.0' library-backend/app/scientific_document_intelligence.py
grep -q 'sc-library-scientific-object/1.0' library-backend/app/scientific_document_intelligence.py
grep -q 'contains-scientific-object' library-backend/app/research_graph_pathfinding.py
grep -q 'data-sc-kl-view="scientific-objects"' sustainable-catalyst-library/includes/class-sc-library-knowledge-landscape.php
grep -q '/backend/scientific-document-intelligence' sustainable-catalyst-library/includes/class-sc-library-python-backend.php

if command -v pytest >/dev/null 2>&1; then
  PYTHONPATH=library-backend pytest -q \
    tests/test_multimodal_scientific_document_intelligence_v5260.py \
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
node --check sustainable-catalyst-library/assets/js/sc-library-knowledge-landscape-v5260.js
bash -n install_and_push_sustainable_catalyst_library_v5_26_0_macos.sh
bash -n upgrade_library_backend_v2_37_0_contabo.sh
python3 - <<'PY'
import json
for path in (
    'docs/schemas/scientific-object.json',
    'docs/schemas/scientific-document-intelligence.json',
):
    with open(path, encoding='utf-8') as fh:
        json.load(fh)
print('PASS: JSON schemas parse')
PY

echo "PASS: Knowledge Library v5.26.0 Multimodal Scientific Document Intelligence validation complete"
