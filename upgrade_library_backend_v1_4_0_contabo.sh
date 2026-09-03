#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v1.4.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }
ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v140.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT
env_backup="/tmp/sc-library-backend-v1.4.0.env.$ts"
app_backup="$BASE/library-backend.before-v1.4.0-$ts"
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
  case "$state" in healthy) healthy=1; break;; unhealthy|exited|dead) echo "ERROR: $container entered state: $state" >&2; docker logs --tail=150 "$container" >&2 || true; exit 1;; esac
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "ERROR: $container did not become healthy." >&2; docker compose ps >&2 || true; docker logs --tail=150 "$container" >&2 || true; exit 1; }
health_json="$(curl -fsS http://127.0.0.1:8087/health)"
bio_json="$(curl -fsS http://127.0.0.1:8087/v1/biomedical-sources)"
fda_json="$(curl -fsS http://127.0.0.1:8087/v1/fda-sources)"
printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$fda_json" | python3 -m json.tool
version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "1.4.0" ]] || { echo "ERROR: expected backend v1.4.0, got: $version" >&2; exit 1; }
python3 -c 'import json,sys; d=json.load(sys.stdin); keys=[x.get("key") for x in d.get("sources",[])]; required=["pubmed","pmc","clinicaltrials","mesh","rxnorm"]; missing=[x for x in required if x not in keys]; assert not missing, f"missing biomedical sources: {missing}"' <<<"$bio_json"
python3 -c 'import json,sys; d=json.load(sys.stdin); keys=[x.get("key") for x in d.get("sources",[])]; required=["drugsfda","fda-labels","fda-ndc","fda-adverse-events","fda-recalls","fda-shortages","orange-book"]; missing=[x for x in required if x not in keys]; assert not missing, f"missing FDA sources: {missing}"' <<<"$fda_json"
printf '\nPASS - Library backend v1.4.0 upgraded; biomedical and FDA regulatory registries are active.\n'
printf 'Application backup: %s\n' "$app_backup"
printf 'Environment backup: %s\n' "$env_backup"
