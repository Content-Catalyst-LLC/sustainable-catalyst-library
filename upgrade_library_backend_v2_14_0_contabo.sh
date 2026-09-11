#!/usr/bin/env bash
set -Eeuo pipefail

ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.14.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2140.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.14.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.14.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.14.0"

if [[ ! -d "$BACKUP_ROOT" ]]; then
  if mkdir -p "$BACKUP_ROOT" 2>/dev/null; then :; else
    command -v sudo >/dev/null || fail "$BACKUP_ROOT is not writable and sudo is unavailable"
    sudo install -d -o "$(id -un)" -g "$(id -gn)" -m 750 "$BACKUP_ROOT"
  fi
fi
if [[ ! -w "$BACKUP_ROOT" ]]; then
  command -v sudo >/dev/null || fail "$BACKUP_ROOT is not writable and sudo is unavailable"
  echo "=== REPAIRING BACKUP DIRECTORY OWNERSHIP ==="
  sudo chown "$(id -un):$(id -gn)" "$BACKUP_ROOT"
  sudo chmod 750 "$BACKUP_ROOT"
fi
[[ -w "$BACKUP_ROOT" ]] || fail "backup directory remains unwritable: $BACKUP_ROOT"

stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then
  echo "=== BACKING UP CURRENT LIBRARY BACKEND ==="
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.14.0-$stamp.tgz" "$(basename "$ROOT")"
fi

ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then
  ENV_TMP="$TMP/existing.env"
  cp "$ROOT/.env" "$ENV_TMP"
fi

mkdir -p "$ROOT"
rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"
[[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"

cd "$ROOT"
docker compose config --quiet
docker compose build --pull
docker compose up -d --force-recreate

for i in $(seq 1 60); do
  if docker ps --filter name=sc-library-backend --format '{{.Status}}' | grep -qi healthy; then break; fi
  sleep 2
done
docker ps --filter name=sc-library-backend --format '{{.Status}}' | grep -qi healthy || {
  docker compose logs --tail=160
  fail "sc-library-backend did not become healthy"
}

BASE=http://127.0.0.1:8087
health="$(curl -fsS "$BASE/health")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x.get("version")=="2.14.0",x; c=x.get("capabilities",{}); assert c.get("energy_systems_domain_version")=="0.8.0"; assert c.get("energy_global_energy_live_world_bank") is True; assert c.get("energy_global_energy_embedded_current_values") is False; assert c.get("energy_global_energy_latest_observation_is_current_assumed") is False' "$health"

for path in \
  /v1/energy-systems \
  /v1/energy-systems/global-energy-framework \
  /v1/energy-systems/global-energy-sources \
  /v1/energy-systems/global-energy-metrics \
  /v1/energy-systems/global-energy-profile-template \
  /v1/energy-systems/bioenergy-framework \
  /v1/energy-systems/economics-framework \
  /v1/energy-systems/balance-framework \
  /v1/carbon-nature; do
  curl -fsS "$BASE$path" >/dev/null || fail "endpoint failed: $path"
done

framework="$(curl -fsS "$BASE/v1/energy-systems/global-energy-framework")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="0.8.0"; assert x["counts"]=={"metrics":9,"sources":4,"live_connectors":1,"profile_contracts":1,"comparison_contracts":1}; g=x["guardrails"]; assert g["latest_available_is_not_current_year"] is True; assert g["missing_values_interpolated"] is False; assert g["cross_source_harmonization_assumed"] is False; assert g["embedded_current_country_values"] is False' "$framework"

sources="$(curl -fsS "$BASE/v1/energy-systems/global-energy-sources")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); rows={i["key"]:i for i in x["items"]}; assert rows["world-bank-wdi"]["status"]=="active"; assert rows["world-bank-wdi"]["authentication"]=="none"; assert rows["ember-api"]["status"]=="contract-ready-not-activated"; assert rows["eia-api-v2"]["status"]=="contract-ready-not-activated"' "$sources"

profile_url="$BASE/v1/energy-systems/global-energy-country-profile?country=USA&start_year=2020&end_year=2026"
echo "=== OPTIONAL LIVE WORLD BANK SMOKE TEST ==="
if live="$(curl -fsS --max-time 20 "$profile_url" 2>/dev/null)"; then
  python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x.get("country_code")=="USA"; assert x.get("source",{}).get("key")=="world-bank-wdi"; assert x.get("guardrails",{}).get("no_interpolation") is True' "$live" || fail "live World Bank response failed contract validation"
  echo "PASS: live World Bank profile connector responded."
else
  if [[ "${SC_VERIFY_LIVE_GLOBAL_ENERGY:-0}" == "1" ]]; then
    fail "strict live World Bank smoke test failed"
  fi
  echo "WARNING: live World Bank smoke test unavailable; static backend validation passed and deployment continues."
fi

echo "PASS: Sustainable Catalyst Library backend v2.14.0 / Energy Systems v0.8.0 deployed and verified."
