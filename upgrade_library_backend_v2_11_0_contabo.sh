#!/usr/bin/env bash
set -Eeuo pipefail

BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.11.0.zip}"
CONTAINER="sc-library-backend"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ -f "$ZIP" ]] || fail "backend ZIP not found: $ZIP"
[[ -d "$LIVE" ]] || fail "existing backend directory not found: $LIVE"
[[ -f "$LIVE/.env" ]] || fail "existing backend .env not found at $LIVE/.env"
for command_name in unzip rsync docker curl python3; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required"; done

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v2110.XXXXXX)"
env_backup="/tmp/sc-library-backend-v2.11.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.11.0-$ts"
trap 'rm -rf "$tmp"' EXIT

printf '=== BACKING UP CURRENT LIBRARY BACKEND ===\n'
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

printf '=== VALIDATING RELEASE ARCHIVE ===\n'
unzip -tq "$ZIP" >/dev/null
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || fail "could not find backend package root"
grep -q '__version__ = "2.11.0"' "$source_dir/app/__init__.py" || fail "release archive is not backend v2.11.0"
grep -q 'DOMAIN_VERSION = "0.5.0"' "$source_dir/app/energy_systems.py" || fail "Energy Systems v0.5.0 domain module missing"
grep -q 'MODEL_VERSION = "0.5.0"' "$source_dir/app/energy_balances.py" || fail "Energy Balance v0.5.0 model module missing"
grep -q 'REGISTRY_VERSION = "0.4.0"' "$source_dir/app/energy_technologies.py" || fail "renewable technology/resource registry missing"
grep -q 'time_series_dispatch_simulation": False' "$source_dir/app/energy_balances.py" || fail "dispatch guardrail missing"
grep -q 'economic_optimization": False' "$source_dir/app/energy_balances.py" || fail "optimization guardrail missing"

printf '=== INSTALLING BACKEND v2.11.0 ===\n'
rsync -a --delete --exclude='.env' "$source_dir/" "$LIVE/"
cp "$env_backup" "$LIVE/.env"
chmod 600 "$LIVE/.env"

cd "$LIVE"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate

printf '=== WAITING FOR CONTAINER HEALTH ===\n'
healthy=0
for _ in $(seq 1 45); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$CONTAINER" 2>/dev/null || true)"
  case "$state" in
    healthy) healthy=1; break ;;
    unhealthy|exited|dead)
      docker logs --tail=180 "$CONTAINER" >&2 || true
      fail "$CONTAINER entered state: $state"
      ;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { docker logs --tail=180 "$CONTAINER" >&2 || true; fail "$CONTAINER did not become healthy"; }

printf '=== VERIFYING RETAINED + NEW CAPABILITIES ===\n'
health_json="$(curl -fsS http://127.0.0.1:8087/health)"
carbon_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature)"
energy_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems)"
registry_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/registry)"
indicator_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/indicator-framework)"
tech_framework_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/technology-framework)"
balance_framework_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/balance-framework)"
chain_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/conversion-chain?input_kwh=1000&efficiencies=90%2C95%2C97&labels=Conversion%2CDistribution%2CEnd%20use')"
balance_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/supply-demand-balance?domestic_supply_kwh=1000&imports_kwh=100&storage_discharge_kwh=50&final_demand_kwh=1000&exports_kwh=50&storage_charge_kwh=25&losses_kwh=75&tolerance_kwh=0.001')"
generation_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/generation-estimate?capacity_kw=1000&capacity_factor_pct=35&hours=8760')"
scenario_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/balance-scenario-template)"
conversion_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/convert?value=100000&from=btu&to=kwh')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$balance_framework_json" | python3 -m json.tool

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="2.11.0", d.get("version"); c=d.get("capabilities",{}); assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("energy_systems_domain_version")=="0.5.0"; assert c.get("energy_balance_framework") is True; assert c.get("energy_conversion_chain_model") is True; assert c.get("energy_supply_demand_balance_model") is True; assert c.get("energy_capacity_factor_generation_estimate") is True; assert c.get("energy_balance_scenario_contracts") is True; assert c.get("energy_time_series_dispatch_simulation") is False; assert c.get("energy_grid_reliability_or_adequacy_model") is False; assert c.get("energy_economic_optimization") is False; assert c.get("automatic_energy_technology_ranking") is False' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"' <<<"$carbon_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"; assert d.get("subsystem",{}).get("backend_version")=="2.11.0"; c=d.get("counts",{}); assert c.get("concepts")==75; assert c.get("relationships")==63; assert c.get("indicators")==30; assert c.get("renewable_technologies")==7; assert c.get("renewable_resource_classes")==6; assert c.get("energy_balance_models")==4; assert c.get("energy_balance_executable_models")==3; g=d.get("guardrails",{}); assert g.get("scenario_modeling_activated") is True; assert g.get("time_series_dispatch_simulation_activated") is False; assert g.get("economic_optimization_activated") is False' <<<"$energy_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts",{}).get("carbon_factors")==24; assert d.get("counts",{}).get("heat_content_factors")==16' <<<"$registry_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts")=={"indicators":30,"dimensions":3,"themes":7,"subthemes":19}' <<<"$indicator_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts",{}).get("technologies")==7; assert d.get("counts",{}).get("resource_classes")==6; assert d.get("guardrails",{}).get("automatic_technology_ranking") is False' <<<"$tech_framework_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="0.5.0"; assert d.get("counts",{}).get("models")==4; assert d.get("counts",{}).get("executable_models")==3; g=d.get("guardrails",{}); assert g.get("time_series_dispatch_simulation") is False; assert g.get("economic_optimization") is False' <<<"$balance_framework_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output",{}).get("final_output_kwh")=="829.35"; assert d.get("output",{}).get("total_loss_kwh")=="170.65"; assert d.get("output",{}).get("overall_efficiency_pct")=="82.935"' <<<"$chain_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); o=d.get("output",{}); assert o.get("available_supply_kwh")=="1150"; assert o.get("accounted_outflows_kwh")=="1150"; assert o.get("residual_kwh")=="0"; assert o.get("balanced") is True' <<<"$balance_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output")=={"generation_kwh":"3066000","average_output_kw":"350"}; assert d.get("provenance",{}).get("capacity_factor_inferred") is False' <<<"$generation_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("contract",{}); assert c.get("persistence_status")=="not-implemented"; assert c.get("optimization_status")=="not-implemented"; assert c.get("ranking_status")=="not-implemented"; assert len(c.get("technology_references",[]))==7' <<<"$scenario_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output")=={"value":"29.31","unit":"kwh"}' <<<"$conversion_json"

docker exec -i "$CONTAINER" python - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
engine = EnergySystemsKnowledgeFoundation()
m = engine.manifest()
assert m["subsystem"]["version"] == "0.5.0"
assert m["counts"]["energy_balance_models"] == 4
assert m["counts"]["renewable_technologies"] == 7
assert m["counts"]["indicators"] == 30
assert engine.conversion_chain(input_kwh="1000", efficiencies="90,95,97")["output"]["final_output_kwh"] == "829.35"
assert engine.supply_demand_balance(domestic_supply_kwh="1000", final_demand_kwh="900", losses_kwh="100")["output"]["balanced"] is True
assert engine.generation_estimate(capacity_kw="1000", capacity_factor_pct="35", hours="8760")["output"]["generation_kwh"] == "3066000"
assert engine.technology_comparison_template()["ranking_enabled"] is False
print("PASS: in-container Energy Systems v0.5.0", m["content_fingerprint"])
PY

printf '\nPASS - Library backend v2.11.0 upgraded; Energy Systems Intelligence v0.5.0 is active, prior Energy Systems layers are preserved, and Carbon & Nature v0.5.0 remains available.\n'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
