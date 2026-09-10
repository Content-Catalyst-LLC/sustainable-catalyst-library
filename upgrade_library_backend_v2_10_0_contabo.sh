#!/usr/bin/env bash
set -Eeuo pipefail

BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.10.0.zip}"
CONTAINER="sc-library-backend"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ -f "$ZIP" ]] || fail "backend ZIP not found: $ZIP"
[[ -d "$LIVE" ]] || fail "existing backend directory not found: $LIVE"
[[ -f "$LIVE/.env" ]] || fail "existing backend .env not found at $LIVE/.env"
for command_name in unzip rsync docker curl python3; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required"; done

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v2100.XXXXXX)"
env_backup="/tmp/sc-library-backend-v2.10.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.10.0-$ts"
trap 'rm -rf "$tmp"' EXIT

printf '=== BACKING UP CURRENT LIBRARY BACKEND ===\n'
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

printf '=== VALIDATING RELEASE ARCHIVE ===\n'
unzip -tq "$ZIP" >/dev/null
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || fail "could not find backend package root"
grep -q '__version__ = "2.10.0"' "$source_dir/app/__init__.py" || fail "release archive is not backend v2.10.0"
grep -q 'DOMAIN_VERSION = "0.4.0"' "$source_dir/app/energy_systems.py" || fail "Energy Systems v0.4.0 domain module missing"
grep -q 'REGISTRY_VERSION = "0.4.0"' "$source_dir/app/energy_technologies.py" || fail "renewable technology/resource registry missing"
grep -q '"solar-photovoltaic"' "$source_dir/app/energy_technologies.py" || fail "solar photovoltaic technology object missing"
grep -q '"wave-energy"' "$source_dir/app/energy_technologies.py" || fail "wave technology object missing"
grep -q '"bioenergy"' "$source_dir/app/energy_technologies.py" || fail "bioenergy technology object missing"
grep -q 'automatic_technology_ranking": False' "$source_dir/app/energy_technologies.py" || fail "technology ranking guardrail missing"

printf '=== INSTALLING BACKEND v2.10.0 ===\n'
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
techs_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/technologies?limit=100')"
resources_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/resource-classes)"
wind_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/technology-assessment-template/wind-energy)"
biomass_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/resource-observation-template/biomass-resource)"
comparison_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/technology-comparison-template)"
conversion_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/convert?value=100000&from=btu&to=kwh')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$tech_framework_json" | python3 -m json.tool

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="2.10.0", d.get("version"); c=d.get("capabilities",{}); assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("energy_systems_domain_version")=="0.4.0"; assert c.get("energy_indicator_framework") is True; assert c.get("energy_renewable_technology_registry") is True; assert c.get("energy_renewable_resource_class_registry") is True; assert c.get("energy_renewable_technology_assessment_contracts") is True; assert c.get("energy_renewable_resource_observation_contracts") is True; assert c.get("energy_quantitative_technology_profiles_loaded") is False; assert c.get("energy_live_resource_potential_datasets_loaded") is False; assert c.get("energy_renewable_suitability_assessment") is False; assert c.get("automatic_energy_technology_ranking") is False' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"' <<<"$carbon_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.4.0"; assert d.get("subsystem",{}).get("backend_version")=="2.10.0"; counts=d.get("counts",{}); assert counts.get("concepts")==75; assert counts.get("relationships")==63; assert counts.get("indicators")==30; assert counts.get("renewable_technologies")==7; assert counts.get("renewable_resource_classes")==6; g=d.get("guardrails",{}); assert g.get("quantitative_technology_profiles_loaded") is False; assert g.get("live_resource_potential_datasets_loaded") is False; assert g.get("renewable_suitability_assessment_activated") is False; assert g.get("automatic_technology_ranking") is False' <<<"$energy_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts",{}).get("carbon_factors")==24; assert d.get("counts",{}).get("heat_content_factors")==16' <<<"$registry_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("counts")=={"indicators":30,"dimensions":3,"themes":7,"subthemes":19}' <<<"$indicator_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("counts",{}); assert c.get("technologies")==7; assert c.get("resource_classes")==6; assert c.get("technology_assessment_contracts")==7; assert c.get("resource_observation_contracts")==6; g=d.get("guardrails",{}); assert g.get("quantitative_technology_profiles_loaded") is False; assert g.get("live_resource_datasets_loaded") is False; assert g.get("automatic_technology_ranking") is False' <<<"$tech_framework_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("count")==7; keys={x.get("key") for x in d.get("items",[])}; assert keys=={"solar-photovoltaic","solar-thermal","wind-energy","hydropower","tidal-energy","wave-energy","bioenergy"}' <<<"$techs_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("count")==6; assert all(x.get("quantitative_dataset_status")=="not-loaded" for x in d.get("items",[]))' <<<"$resources_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("contract",{}); assert d.get("technology",{}).get("key")=="wind-energy"; assert c.get("provenance_required") is True; assert c.get("suitability_status")=="not-assessed"; assert "capacity_factor" in c.get("required_structure",[])' <<<"$wind_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); f=d.get("contract",{}).get("required_structure",[]); assert d.get("resource_class",{}).get("key")=="biomass-resource"; assert "feedstock_origin" in f and "competing_use_note" in f and "land_use_note" in f' <<<"$biomass_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("values_loaded") is False; assert d.get("ranking_enabled") is False; assert len(d.get("candidate_technology_keys",[]))==7' <<<"$comparison_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("output")=={"value":"29.31","unit":"kwh"}' <<<"$conversion_json"

docker exec -i "$CONTAINER" python - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
engine = EnergySystemsKnowledgeFoundation()
m = engine.manifest()
assert m["subsystem"]["version"] == "0.4.0"
assert m["counts"]["renewable_technologies"] == 7
assert m["counts"]["renewable_resource_classes"] == 6
assert m["counts"]["indicators"] == 30
assert engine.technology("solar-photovoltaic")["technology"]["suitability_status"] == "not-assessed"
assert engine.technology_comparison_template()["ranking_enabled"] is False
assert engine.convert_energy(value="100000", from_unit="btu", to_unit="kwh")["output"]["value"] == "29.31"
print("PASS: in-container Energy Systems v0.4.0", m["content_fingerprint"])
PY

printf '\nPASS - Library backend v2.10.0 upgraded; Energy Systems Intelligence v0.4.0 is active, prior Energy Systems layers are preserved, and Carbon & Nature v0.5.0 remains available.\n'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
