#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
export PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}"
pytest -q \
  library-backend/tests/test_energy_systems_v2170.py \
  tests/test_energy_systems_intelligence_v110.py
