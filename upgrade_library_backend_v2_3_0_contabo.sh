#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.3.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v230.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.3.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.3.0-$ts"
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || { echo "ERROR: could not find backend package root" >&2; exit 1; }
rsync -a --delete --exclude='.env' "$source_dir/" "$LIVE/"
cp "$env_backup" "$LIVE/.env"
chmod 600 "$LIVE/.env"

cd "$LIVE"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate
container="sc-library-backend"
healthy=0
for _ in $(seq 1 45); do
  state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || true)"
  case "$state" in
    healthy) healthy=1; break ;;
    unhealthy|exited|dead)
      echo "ERROR: $container entered state: $state" >&2
      docker logs --tail=150 "$container" >&2 || true
      exit 1
      ;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker logs --tail=150 "$container" >&2 || true; exit 1; }

health_json="$(curl -fsS http://127.0.0.1:8087/health)"
manifest_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature)"
measures_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/measures?system=cropland&pool=soil-organic-carbon&limit=20')"
compare_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/measures/compare?keys=cover-crop-system,reduced-tillage-system')"
context_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-context?q=peatland%20methane&limit=8')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$manifest_json" | python3 -m json.tool
printf '%s\n' "$measures_json" | python3 -m json.tool
printf '%s\n' "$compare_json" | python3 -m json.tool
printf '%s\n' "$context_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.3.0" ]] || { echo "ERROR: expected backend v2.3.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.2.0"; assert c.get("carbon_sequestration_measure_registry") is True; assert c.get("carbon_measure_comparison_packets") is True; assert c.get("private_organizational_knowledge") is True' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); g=d.get("governance",{}); assert d.get("schema")=="sc-carbon-nature-knowledge-foundation/1.1"; assert d.get("subsystem",{}).get("version")=="0.2.0"; assert d.get("coverage",{}).get("measure_count")==10; assert g.get("automatic_measure_ranking") is False; assert g.get("quantified_sequestration_potential") is False; assert g.get("carbon_credit_issuance") is False' <<<"$manifest_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-sequestration-measure-registry/1.0"; assert d.get("count",0)>=5; assert d.get("guardrails",{}).get("practice_adoption_is_not_quantified_sequestration") is True' <<<"$measures_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("governance",{}).get("ranking_performed") is False; assert d.get("governance",{}).get("project_suitability_determined") is False' <<<"$compare_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem_version")=="0.2.0"; assert d.get("handoff",{}).get("measure_registry_context_enabled") is True; assert d.get("guardrails",{}).get("quantified_sequestration_not_inferred") is True' <<<"$context_json"

printf '%s\n' 'PASS - Library backend v2.3.0 upgraded; Carbon & Nature Intelligence v0.2.0 Measure Registry is active.'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
