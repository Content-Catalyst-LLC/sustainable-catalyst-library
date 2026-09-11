#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PYTHONPATH="$ROOT/library-backend" python3 -m pytest -q \
  library-backend/tests/test_energy_systems_v2130.py \
  tests/test_energy_systems_intelligence_v070.py \
  library-backend/tests/test_carbon_nature_v260.py \
  tests/test_carbon_nature_intelligence_v050.py
