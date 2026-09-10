#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT"

echo "=== Energy Systems Intelligence v0.1.0 validation ==="
python3 -m py_compile library-backend/app/energy_systems.py library-backend/app/main.py
php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
php -l sustainable-catalyst-library/includes/class-sc-library-energy-systems-intelligence.php >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-energy-systems-v010.js >/dev/null
PYTHONPATH=library-backend python3 -m pytest -q library-backend/tests/test_energy_systems_v270.py
python3 -m pytest -q tests/test_energy_systems_intelligence_v010.py
PYTHONPATH=library-backend python3 - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
m = EnergySystemsKnowledgeFoundation().manifest()
assert m["subsystem"]["version"] == "0.1.0"
assert m["subsystem"]["backend_version"] == "2.7.0"
assert m["counts"]["concepts"] == 75
assert m["counts"]["relationships"] == 63
assert m["guardrails"]["conversion_factors_activated"] is False
print("PASS: deterministic manifest", m["content_fingerprint"])
PY

echo "PASS: Energy Systems Intelligence v0.1.0 static identity, source provenance, knowledge map, routes, UI, handoffs, and guardrails"
