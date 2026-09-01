#!/usr/bin/env bash
set -euo pipefail
BASE="/opt/sustainable-catalyst"
LIVE="$BASE/library-backend"
ZIP="${1:-/tmp/sustainable-catalyst-library-backend-v1.0.1.zip}"
[[ -f "$ZIP" ]] || { echo "ERROR: backend ZIP not found: $ZIP" >&2; exit 1; }
[[ -f "$LIVE/.env" ]] || { echo "ERROR: existing backend .env not found at $LIVE/.env" >&2; exit 1; }

ts="$(date +%Y%m%d-%H%M%S)"
tmp="$(mktemp -d /tmp/sc-library-backend-v101.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT

cp "$LIVE/.env" "/tmp/sc-library-backend-v1.0.1.env.$ts"
cp -a "$LIVE" "$BASE/library-backend.before-v1.0.1-$ts"
unzip -q "$ZIP" -d "$tmp"
source_dir="$(find "$tmp" -maxdepth 2 -type f -name compose.yml -printf '%h\n' | head -1)"
[[ -n "$source_dir" ]] || { echo "ERROR: could not find backend package root" >&2; exit 1; }

rsync -a --delete --exclude='.env' "$source_dir/" "$LIVE/"
cp "/tmp/sc-library-backend-v1.0.1.env.$ts" "$LIVE/.env"
chmod 600 "$LIVE/.env"

cd "$LIVE"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate
docker compose ps
curl -fsS http://127.0.0.1:8087/health | python3 -m json.tool
curl -fsS http://127.0.0.1:8087/ready | python3 -m json.tool
