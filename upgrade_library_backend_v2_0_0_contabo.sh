#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v2.0.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }
ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v200.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v2.0.0.env.$ts"
app_backup="$BASE/library-backend.before-v2.0.0-$ts"
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
    healthy) healthy=1; break;;
    unhealthy|exited|dead) echo "ERROR: $container entered state: $state" >&2; docker logs --tail=150 "$container" >&2 || true; exit 1;;
  esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker logs --tail=150 "$container" >&2 || true; exit 1; }
health_json="$(curl -fsS http://127.0.0.1:8087/health)"
manifest_json="$(curl -fsS http://127.0.0.1:8087/v1/institutional-research-network)"
printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$manifest_json" | python3 -m json.tool
version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "2.0.0" ]] || { echo "ERROR: expected backend v2.0.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); f=d.get("framework",{}); g=f.get("governance",{}); keys={s.get("key") for s in d.get("sources",[])}; assert d.get("schema")=="sc-institutional-research-network-manifest/2.0"; assert f.get("key")=="institutional-research-network-ii"; assert {"mit-dspace","harvard-dataverse","johns-hopkins-dataverse","ucd-research-repository"} <= keys; assert g.get("repository_discovery_is_entitlement") is False; assert g.get("metadata_visibility_is_reuse_permission") is False; assert g.get("title_only_identity_merge") is False; assert g.get("cross_source_author_identity_inferred") is False; assert g.get("affiliation_asserted") is False; assert g.get("endorsement_asserted") is False' <<<"$manifest_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); c=d.get("capabilities",{}); assert c.get("institutional_research_network_ii") is True; assert c.get("institutional_exact_doi_deduplication") is True; assert c.get("institutional_source_failure_containment") is True; assert c.get("institutional_graph_fingerprint") is True' <<<"$health_json"
printf '\nPASS - Library backend v2.0.0 upgraded; Institutional Research Network II manifest is active.\n'
printf 'Application backup: %s\n' "$app_backup"
printf 'Environment backup: %s\n' "$env_backup"
