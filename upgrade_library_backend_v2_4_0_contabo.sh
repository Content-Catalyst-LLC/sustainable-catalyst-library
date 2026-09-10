#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.4.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v240.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.4.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.4.0-$ts"
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
methods_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/methodologies?measure=peatland-restoration-rewetting&limit=20')"
evidence_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/evidence?q=IPCC&limit=20')"
graph_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/evidence-graph?node_type=methodology&node_key=soc-direct-measurement&limit=100')"
neighborhood_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/evidence-graph/neighborhood/cover-crop-system?limit=100')"
context_json="$(curl -fsS 'http://127.0.0.1:8087/v1/carbon-nature/research-context?q=cover%20crops%20soil%20carbon&limit=8')"

printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$manifest_json" | python3 -m json.tool
printf '%s\n' "$methods_json" | python3 -m json.tool
printf '%s\n' "$evidence_json" | python3 -m json.tool
printf '%s\n' "$graph_json" | python3 -m json.tool
printf '%s\n' "$neighborhood_json" | python3 -m json.tool
printf '%s\n' "$context_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.4.0" ]] || { echo "ERROR: expected backend v2.4.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("carbon_nature_intelligence") is True; assert c.get("carbon_nature_domain_version")=="0.3.0"; assert c.get("carbon_evidence_registry") is True; assert c.get("carbon_methodology_registry") is True; assert c.get("carbon_evidence_methodology_graph") is True; assert c.get("automatic_carbon_methodology_selection") is False; assert c.get("private_organizational_knowledge") is True' <<<"$health_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); g=d.get("governance",{}); assert d.get("schema")=="sc-carbon-nature-knowledge-foundation/1.2"; assert d.get("subsystem",{}).get("version")=="0.3.0"; assert d.get("coverage",{}).get("measure_count")==10; assert d.get("coverage",{}).get("methodology_count")==7; assert d.get("coverage",{}).get("evidence_record_count")==4; assert g.get("automatic_methodology_selection") is False; assert g.get("automatic_claim_validation") is False; assert g.get("carbon_credit_issuance") is False' <<<"$manifest_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-methodology-registry/1.0"; assert d.get("count",0)>=2; assert d.get("guardrails",{}).get("automatic_methodology_selection") is False' <<<"$methods_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-evidence-registry/1.0"; assert d.get("count")==2; assert d.get("guardrails",{}).get("association_is_not_claim_validation") is True' <<<"$evidence_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-evidence-methodology-graph/1.0"; assert d.get("edge_count",0)>0; assert d.get("governance",{}).get("edge_inference_enabled") is False' <<<"$graph_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-evidence-methodology-neighborhood/1.0"; assert d.get("edges"); assert d.get("governance",{}).get("inference_enabled") is False' <<<"$neighborhood_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("schema")=="sc-carbon-nature-research-context/1.2"; assert d.get("subsystem_version")=="0.3.0"; assert d.get("handoff",{}).get("evidence_methodology_graph_context_enabled") is True; assert d.get("guardrails",{}).get("methodology_match_is_not_methodology_eligibility") is True' <<<"$context_json"

printf '%s\n' 'PASS - Library backend v2.4.0 upgraded; Carbon & Nature Intelligence v0.3.0 Evidence & Methodology Graph is active.'
printf 'Application backup: %s\nEnvironment backup: %s\n' "$app_backup" "$env_backup"
