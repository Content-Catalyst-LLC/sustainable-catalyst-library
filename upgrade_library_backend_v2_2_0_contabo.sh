#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.2.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v220.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.2.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.2.0-$ts"
cp "$LIVE/.env" "$env_backup"
cp -a "$LIVE" "$app_backup"

unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h
' | head -1)"
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
  case "$state" in healthy) healthy=1; break;; unhealthy|exited|dead) echo "ERROR: $container entered state: $state" >&2; docker logs --tail=150 "$container" >&2 || true; exit 1;; esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker logs --tail=150 "$container" >&2 || true; exit 1; }

health_json="$(curl -fsS http://127.0.0.1:8087/health)"
manifest_json="$(curl -fsS http://127.0.0.1:8087/v1/carbon-nature)"
context_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-context?q=soil%20organic%20carbon&limit=8')"
printf '%s
' "$health_json" | python3 -m json.tool
printf '%s
' "$manifest_json" | python3 -m json.tool
printf '%s
' "$context_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.2.0" ]] || { echo "ERROR: expected backend v2.2.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.1.0"; assert c.get("afolu_knowledge_foundation") is True; assert c.get("nature_based_solutions_knowledge_foundation") is True; assert c.get("private_organizational_knowledge") is True' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); g=d.get("governance",{}); assert d.get("schema")=="sc-carbon-nature-knowledge-foundation/1.0"; assert d.get("subsystem",{}).get("version")=="0.1.0"; assert g.get("carbon_credit_issuance") is False; assert g.get("certification_body") is False; assert g.get("soc_calculation_engine") is False; assert g.get("whole_farm_ghg_calculator") is False' <<<"$manifest_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("subsystem_version")=="0.1.0"; assert d.get("guardrails",{}).get("concept_match_is_not_evidence") is True; assert d.get("handoff",{}).get("domain_aware_reasoning_enabled") is False' <<<"$context_json"

printf '%s
' 'PASS - Library backend v2.2.0 upgraded; Carbon & Nature Intelligence v0.1.0 is active.'
printf 'Application backup: %s
Environment backup: %s
' "$app_backup" "$env_backup"
