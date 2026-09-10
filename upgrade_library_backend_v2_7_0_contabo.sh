#!/usr/bin/env bash
set -Eeuo pipefail

BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.7.0.zip}"
CONTAINER="sc-library-backend"

fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ -f "$ZIP" ]] || fail "backend ZIP not found: $ZIP"
[[ -d "$LIVE" ]] || fail "existing backend directory not found: $LIVE"
[[ -f "$LIVE/.env" ]] || fail "existing backend .env not found at $LIVE/.env"
for command_name in unzip rsync docker curl python3; do command -v "$command_name" >/dev/null 2>&1 || fail "$command_name is required"; done

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v270.XXXXXX)"
env_backup="/tmp/sc-library-backend-v2.7.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.7.0-$ts"
trap 'rm -rf "$tmp"' EXIT

printf '=== BACKING UP CURRENT LIBRARY BACKEND ===\n'
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

printf '=== VALIDATING RELEASE ARCHIVE ===\n'
unzip -tq "$ZIP" >/dev/null
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || fail "could not find backend package root"
grep -q '__version__ = "2.7.0"' "$source_dir/app/__init__.py" || fail "release archive is not backend v2.7.0"
grep -q 'class EnergySystemsKnowledgeFoundation' "$source_dir/app/energy_systems.py" || fail "Energy Systems domain module missing from archive"

printf '=== INSTALLING BACKEND v2.7.0 ===\n'
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
map_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/knowledge-map)"
sources_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/sources)"
handoffs_json="$(curl -fsS http://127.0.0.1:8087/v1/energy-systems/handoffs)"
solar_json="$(curl -fsS 'http://127.0.0.1:8087/v1/energy-systems/concepts?q=solar&limit=25')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$energy_json" | python3 -m json.tool

python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("version")=="2.7.0", d.get("version"); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.5.0"; assert c.get("energy_systems_intelligence") is True; assert c.get("energy_systems_domain_version")=="0.1.0"; assert c.get("sustainable_energy_knowledge_foundation") is True; assert c.get("energy_concept_registry") is True; assert c.get("energy_relationship_registry") is True; assert c.get("energy_source_provenance_registry") is True; assert c.get("energy_knowledge_map") is True; assert c.get("energy_numeric_conversion_registry") is False; assert c.get("energy_indicator_engine") is False; assert c.get("energy_scenario_modeling") is False' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem",{}).get("version")=="0.5.0"' <<<"$carbon_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-systems-knowledge-foundation/1.0"; assert d.get("subsystem",{}).get("version")=="0.1.0"; assert d.get("subsystem",{}).get("backend_version")=="2.7.0"; counts=d.get("counts",{}); assert counts.get("concepts")==75; assert counts.get("relationships")==63; assert counts.get("sources")==6; g=d.get("guardrails",{}); assert g.get("conversion_factors_activated") is False; assert g.get("energy_indicator_calculation_activated") is False; assert g.get("scenario_modeling_activated") is False' <<<"$energy_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-knowledge-map/1.0"; assert len(d.get("domains",[]))==6; assert len(d.get("relationships",[]))==63; sdg={x.get("goal"):x for x in d.get("sdg_mappings",[])}; assert sdg[14].get("coverage")==3; assert sdg[15].get("coverage") is None' <<<"$map_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-energy-sources/1.0"; assert d.get("count")==6; rows={x.get("key"):x for x in d.get("items",[])}; assert rows["carbon-trust-conversion-2020"].get("numeric_status")=="inactive-historical-numeric-source"' <<<"$sources_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("count")==7; rows={x.get("key"):x for x in d.get("items",[])}; assert rows["soil-carbon-to-carbon-nature"].get("target_refs")==["soil-organic-carbon"]; assert rows["forest-to-carbon-nature"].get("target_refs")==["forest-woodland"]; assert rows["bioenergy-carbon-nature-extension"].get("status")=="planned-extension"; assert rows["bioenergy-carbon-nature-extension"].get("target_refs")==[]' <<<"$handoffs_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); keys={x.get("key") for x in d.get("items",[])}; assert {"solar-energy","solar-photovoltaics","solar-thermal"} <= keys' <<<"$solar_json"

docker exec -i "$CONTAINER" python - <<'PY'
from app.energy_systems import EnergySystemsKnowledgeFoundation
engine = EnergySystemsKnowledgeFoundation()
m = engine.manifest()
assert m["counts"]["concepts"] == 75
assert m["guardrails"]["conversion_factors_activated"] is False
print("PASS: in-container Energy Systems manifest", m["content_fingerprint"])
PY

printf '\nPASS - Library backend v2.7.0 upgraded; Energy Systems Intelligence v0.1.0 is active and Carbon & Nature v0.5.0 remains available.\n'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
