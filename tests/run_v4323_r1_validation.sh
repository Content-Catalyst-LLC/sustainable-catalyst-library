#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"

"$PY" -m pytest -q tests/test_installer_repair_v4323_r1.py
SC_LIBRARY_VALIDATION_PYTHON="$PY" bash tests/run_v4323_validation.sh
printf 'PASS - v4.3.23-r1 installer validation repair and full v4.3.23 regression stack complete\n'
