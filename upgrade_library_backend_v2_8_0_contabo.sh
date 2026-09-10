#!/usr/bin/env bash
set -Eeuo pipefail

BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.8.0.zip}"
CONTAINER="sc-library-backend"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ -f "$ZIP" ]] || fail "backend ZIP not found: $ZIP"
[[ -d "$LIVE" ]] || fail "existing backend directory not found: $LIVE"
[[ -f "$LIVE/.env" ]] || fail "existing backend .env not found at $LIVE/.env"
for command_name in unzip rsync docker curl python3; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required"; done

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v280.XXXXXX)"
env_backup="/tmp/sc-library-backend-v2.8.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.8.0-$ts"
trap 'rm -rf "$tmp"' EXIT

printf '=== BACKING UP CURRENT LIBRARY BACKEND ===\n'
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

printf '=== VALIDATING RELEASE ARCHIVE ===\n'
unzip -tq "$ZIP" >/dev/null
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || fail "could not find backend package root"
grep -q '__version__ = "2.8.0"' "$source_dir/app/__init__.py" || fail "release archive is not backend v2.8.0"
grep -q 'DOMAIN_VERSION = "0.2.0"' "$source_dir/app/energy_systems.py" || fail "Energy Systems v0.2.0 domain module missing from archive"
grep -q 'uk-grid-electricity-kwh-2020' "$source_dir/app/energy_systems.py" || fail "Energy Systems carbon registry missing from archive"

printf '=== INSTALLING BACKEND v2.8.0 ===\n'
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
      docker logs --tail=160 "$CONTAINER" >&2 || true
      fail "$CONTAINER entered state: $state"
      ;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { docker logs --tail=160 "$CONTAINER" >&2 || true; fail "$CONTAINER did not become healthy"; }

printf '=== VERIFYING RETAINED + NEW CAPABILITIES ===\n'
health_json="$(curl -fsS http://127.0.0.1:8087/health)"
carbon_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature)"
energy_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems)"
registry_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/registry)"
conversion_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/convert?value=100000&from=btu&to=kwh')"
carbon_estimate_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/carbon-estimate?factor_key=natural-gas-kwh-2020&quantity=100')"
heat_estimate_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/heat-content-estimate?factor_key=diesel-kwh-per-litre-2020&quantity=10')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$registry_json" | python3 -m json.tool

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="2.8.0", d.get("version"); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("energy_systems_intelligence") is True; assert c.get("energy_systems_domain_version")=="0.2.0"; assert c.get("energy_numeric_conversion_registry") is True; assert c.get("energy_carbon_factor_registry") is True; assert c.get("energy_heat_content_registry") is True; assert c.get("energy_current_factor_defaults") is False; assert c.get("energy_workbench_execution") is False; assert c.get("energy_indicator_engine") is False; assert c.get("energy_scenario_modeling") is False' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"' <<<"$carbon_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-systems-conversion-registry/1.0"; assert d.get("subsystem",{}).get("version")=="0.2.0"; assert d.get("subsystem",{}).get("backend_version")=="2.8.0"; counts=d.get("counts",{}); assert counts.get("concepts")==75; assert counts.get("relationships")==63; assert counts.get("conversion_factors")==4; assert counts.get("carbon_factors")==24; assert counts.get("heat_content_factors")==16; g=d.get("guardrails",{}); assert g.get("conversion_factors_activated") is True; assert g.get("current_factor_defaults_activated") is False; assert g.get("energy_indicator_calculation_activated") is False; assert g.get("scenario_modeling_activated") is False' <<<"$energy_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-numeric-registry/1.0"; assert d.get("source_binding",{}).get("source_year")==2020; assert d.get("source_binding",{}).get("current_default") is False; assert d.get("counts",{}).get("carbon_factors")==24' <<<"$registry_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output")=={"value":"29.31","unit":"kwh"}; assert d.get("status")=="historical-source-bound-calculation"' <<<"$conversion_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output",{}).get("kg_co2e")=="18.387"; assert d.get("factor",{}).get("emissions_boundary")=="direct"; assert d.get("factor",{}).get("source_year")==2020' <<<"$carbon_estimate_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output",{}).get("kwh_gross")=="105.8"; assert d.get("factor",{}).get("calorific_basis")=="gross calorific value"' <<<"$heat_estimate_json"

docker exec -i "$CONTAINER" python - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
engine = EnergySystemsKnowledgeFoundation()
m = engine.manifest()
assert m["subsystem"]["version"] == "0.2.0"
assert m["counts"]["carbon_factors"] == 24
assert m["guardrails"]["current_factor_defaults_activated"] is False
assert engine.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"
print("PASS: in-container Energy Systems v0.2.0 registry", m["content_fingerprint"])
PY

printf '\nPASS - Library backend v2.8.0 upgraded; Energy Systems Intelligence v0.2.0 is active and Carbon & Nature v0.5.0 remains available.\n'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
