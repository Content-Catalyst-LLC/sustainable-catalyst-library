#!/usr/bin/env bash
set -euo pipefail
REPO="${1:-.}"
cd "$REPO"

echo "=== Sustainable Catalyst Library v6.22.0 validation ==="
echo "Library 6.22.0 / backend 3.22.0 / web 2.22.0 / SDK 1.22.0"

python3 -m compileall -q library-backend/app
echo "PASS: Python compile"

PYTHONPATH="$REPO/library-backend${PYTHONPATH:+:$PYTHONPATH}" python3 - <<'PY'
from pathlib import Path
import importlib.util
for path in [
    Path("library-backend/tests/test_evidence_matrix_claim_support_workspace_v3220.py"),
    Path("tests/test_evidence_matrix_claim_support_workspace_v6220.py"),
]:
    spec=importlib.util.spec_from_file_location("_sc_release_test",path)
    mod=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    tests=[getattr(mod,n) for n in dir(mod) if n.startswith("test_") and callable(getattr(mod,n))]
    for fn in tests: fn()
    print(f"PASS: {path} ({len(tests)} tests)")
PY

echo "PASS: backend and static v6.22 tests"

if command -v node >/dev/null 2>&1; then
  node --check library-web/assets/app.js
  echo "PASS: JavaScript syntax"
else
  echo "NOTE: node unavailable"
fi

if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
  echo "PASS: WordPress PHP lint"
else
  echo "NOTE: php unavailable"
fi

python3 - <<'PY'
from pathlib import Path
r=Path(".")
assert (r/"library-backend/app/__init__.py").read_text().strip()=='__version__ = "3.22.0"'
assert 'webVersion: "2.22.0"' in (r/"library-web/config.js").read_text()
assert 'version = "1.22.0"' in (r/"clients/python/pyproject.toml").read_text()
assert '"version": "1.22.0"' in (r/"clients/javascript/package.json").read_text()
assert 'Version: 6.22.0' in (r/"sustainable-catalyst-library/sustainable-catalyst-library.php").read_text()
print("PASS: generation markers synchronized")
PY

git diff --check
echo "PASS: git diff --check"
echo "PASS: Sustainable Catalyst Library v6.22.0 validation complete"
