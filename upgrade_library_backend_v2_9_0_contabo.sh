#!/usr/bin/env bash
set -Eeuo pipefail

BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.9.0.zip}"
CONTAINER="sc-library-backend"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ -f "$ZIP" ]] || fail "backend ZIP not found: $ZIP"
[[ -d "$LIVE" ]] || fail "existing backend directory not found: $LIVE"
[[ -f "$LIVE/.env" ]] || fail "existing backend .env not found at $LIVE/.env"
for command_name in unzip rsync docker curl python3; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required"; done

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v290.XXXXXX)"
env_backup="/tmp/sc-library-backend-v2.9.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.9.0-$ts"
trap 'rm -rf "$tmp"' EXIT

printf '=== BACKING UP CURRENT LIBRARY BACKEND ===\n'
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

printf '=== VALIDATING RELEASE ARCHIVE ===\n'
unzip -tq "$ZIP" >/dev/null
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || fail "could not find backend package root"
grep -q '__version__ = "2.9.0"' "$source_dir/app/__init__.py" || fail "release archive is not backend v2.9.0"
grep -q 'DOMAIN_VERSION = "0.3.0"' "$source_dir/app/energy_systems.py" || fail "Energy Systems v0.3.0 domain module missing from archive"
grep -q 'I("ECO13", "Renewable energy share in energy and electricity"' "$source_dir/app/energy_systems.py" || fail "Energy Systems indicator registry missing from archive"
grep -q 'official_eisd_methodology_sheets_loaded": False' "$source_dir/app/energy_systems.py" || fail "indicator methodology guardrail missing from archive"

printf '=== INSTALLING BACKEND v2.9.0 ===\n'
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
framework_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/indicator-framework)"
indicators_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/indicators?limit=100')"
eco13_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/indicators/ECO13)"
eco13_template_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/indicator-observation-template/ECO13)"
conversion_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/convert?value=100000&from=btu&to=kwh')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$framework_json" | python3 -m json.tool

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="2.9.0", d.get("version"); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("energy_systems_intelligence") is True; assert c.get("energy_systems_domain_version")=="0.3.0"; assert c.get("energy_numeric_conversion_registry") is True; assert c.get("energy_indicator_framework") is True; assert c.get("energy_indicator_definition_registry") is True; assert c.get("energy_indicator_observation_contracts") is True; assert c.get("energy_indicator_official_methodology_loaded") is False; assert c.get("energy_indicator_calculation") is False; assert c.get("energy_scenario_modeling") is False' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"' <<<"$carbon_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-systems-sustainability-indicators/1.0"; assert d.get("subsystem",{}).get("version")=="0.3.0"; assert d.get("subsystem",{}).get("backend_version")=="2.9.0"; counts=d.get("counts",{}); assert counts.get("concepts")==75; assert counts.get("relationships")==63; assert counts.get("conversion_factors")==4; assert counts.get("carbon_factors")==24; assert counts.get("heat_content_factors")==16; assert counts.get("indicators")==30; assert counts.get("indicator_dimensions")==3; assert counts.get("indicator_themes")==7; assert counts.get("indicator_subthemes")==19; g=d.get("guardrails",{}); assert g.get("official_eisd_methodology_sheets_loaded") is False; assert g.get("energy_indicator_calculation_activated") is False' <<<"$energy_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-numeric-registry/1.0"; assert d.get("source_binding",{}).get("source_year")==2020; assert d.get("counts",{}).get("carbon_factors")==24' <<<"$registry_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts")=={"indicators":30,"dimensions":3,"themes":7,"subthemes":19}; assert d.get("methodology_status")=="methodology-sheets-not-supplied"' <<<"$framework_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("count")==30; codes={x.get("code") for x in d.get("items",[])}; assert "SOC1" in codes and "ECO13" in codes and "ENV10" in codes' <<<"$indicators_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); i=d.get("indicator",{}); assert i.get("code")=="ECO13"; assert i.get("label")=="Renewable energy share in energy and electricity"; assert i.get("calculation_status")=="not-implemented"' <<<"$eco13_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("contract",{}); assert c.get("indicator_code")=="ECO13"; assert c.get("provenance_required") is True; assert c.get("methodology_reference_required") is True; assert c.get("calculation_status")=="not-implemented"' <<<"$eco13_template_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output")=={"value":"29.31","unit":"kwh"}; assert d.get("status")=="historical-source-bound-calculation"' <<<"$conversion_json"

docker exec -i "$CONTAINER" python - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
engine = EnergySystemsKnowledgeFoundation()
m = engine.manifest()
assert m["subsystem"]["version"] == "0.3.0"
assert m["counts"]["indicators"] == 30
assert m["counts"]["carbon_factors"] == 24
assert m["guardrails"]["official_eisd_methodology_sheets_loaded"] is False
assert engine.indicator("ECO13")["indicator"]["calculation_status"] == "not-implemented"
assert engine.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"
print("PASS: in-container Energy Systems v0.3.0 indicator framework", m["content_fingerprint"])
PY

printf '\nPASS - Library backend v2.9.0 upgraded; Energy Systems Intelligence v0.3.0 is active, the v0.2.0 numeric registry is preserved, and Carbon & Nature v0.5.0 remains available.\n'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
