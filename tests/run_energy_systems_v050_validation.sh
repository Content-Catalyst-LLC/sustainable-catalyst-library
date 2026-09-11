#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PYTHONPATH="$ROOT/library-backend${PYTHONPATH:+:$PYTHONPATH}"
python3 -m pytest -q \
  library-backend/tests/test_energy_systems_v2110.py \
  tests/test_energy_systems_intelligence_v050.py \
  tests/test_carbon_nature_intelligence_v050.py
