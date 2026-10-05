#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-.}"
cd "$REPO"
echo "=== Sustainable Catalyst Library v6.18.0 validation ==="
echo "Library 6.18.0 / backend 3.18.0 / web 2.18.0 / SDK 1.18.0"
python3 -m compileall -q library-backend/app
echo "PASS: Python compile"
PYTHONPATH=library-backend python3 -m pytest -q library-backend/tests/test_corpus_computational_linguistics_workspace_v3180.py
echo "PASS: backend computational-linguistics behavior tests"
python3 -m pytest -q tests/test_corpus_computational_linguistics_workspace_v6180.py
echo "PASS: v6.18 static release tests"
if command -v node >/dev/null 2>&1; then node --check library-web/assets/app.js; echo "PASS: JavaScript syntax"; else echo "NOTE: node unavailable"; fi
if command -v php >/dev/null 2>&1; then php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null; echo "PASS: WordPress PHP lint"; else echo "NOTE: php unavailable"; fi
python3 - <<'PY'
from pathlib import Path
r=Path(".")
assert (r/"library-backend/app/__init__.py").read_text().strip() == '__version__ = "3.18.0"'
assert 'webVersion: "2.18.0"' in (r/"library-web/config.js").read_text()
assert 'version = "1.18.0"' in (r/"clients/python/pyproject.toml").read_text()
assert '"version": "1.18.0"' in (r/"clients/javascript/package.json").read_text()
assert 'Version: 6.18.0' in (r/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
print("PASS: generation markers synchronized")
PY
git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.18.0 validation complete"
