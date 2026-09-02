#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v1.1.0.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v110.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT

env_backup="/tmp/sc-library-backend-v1.1.0.env.$ts"
app_backup="$BASE/library-backend.before-v1.1.0-$ts"
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
    healthy)
      healthy=1
      break
      ;;
    unhealthy|exited|dead)
      echo "ERROR: $container entered state: $state" >&2
      docker logs --tail=150 "$container" >&2 || true
      exit 1
      ;;
  esac
  sleep 2
done

if [[ "$healthy" != "1" ]]; then
  echo "ERROR: $container did not become healthy during validation." >&2
  docker compose ps >&2 || true
  docker logs --tail=150 "$container" >&2 || true
  exit 1
fi

docker compose ps
health_json="$(curl -fsS http://127.0.0.1:8087/health)"
ready_json="$(curl -fsS http://127.0.0.1:8087/ready)"
bootstrap_json="$(curl -fsS 'http://127.0.0.1:8087/v1/explorer/bootstrap?featured_limit=2&recent_limit=2')"
printf '%s\n' "$health_json" | python3 -m json.tool
printf '%s\n' "$ready_json" | python3 -m json.tool
printf '%s\n' "$bootstrap_json" | python3 -m json.tool

version="$(printf '%s' "$health_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", ""))')"
[[ "$version" == "1.1.0" ]] || { echo "ERROR: expected backend v1.1.0, got: $version" >&2; exit 1; }
bootstrap_schema="$(printf '%s' "$bootstrap_json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("schema", ""))')"
[[ "$bootstrap_schema" == "sc-library-explorer-bootstrap/1.0" ]] || { echo "ERROR: Explorer bootstrap schema is not sc-library-explorer-bootstrap/1.0: $bootstrap_schema" >&2; exit 1; }

printf '\nPASS - Sustainable Catalyst Library backend v1.1.0 upgraded, healthy, and serving Explorer bootstrap.\n'
printf 'Application backup: %s\n' "$app_backup"
printf 'Environment backup: %s\n' "$env_backup"
