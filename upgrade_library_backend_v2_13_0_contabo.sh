#!/usr/bin/env bash
set -Eeuo pipefail

ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.13.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2130.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.13.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.13.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.13.0"

# Ensure the shared backup directory is writable by the deployment account.
# Earlier deployments can leave /opt/sustainable-catalyst/backups root-owned.
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
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.13.0-$stamp.tgz" "$(basename "$ROOT")"
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
docker compose config >/dev/null
docker compose build
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
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x.get("version")=="2.13.0",x; caps=x.get("capabilities",{}); assert caps.get("energy_systems_domain_version")=="0.7.0"; assert caps.get("energy_biological_carbon_bioenergy_integration") is True; assert caps.get("energy_biomass_carbon_neutrality_assumed") is False' "$health"

for path in \
  /v1/energy-systems \
  /v1/energy-systems/economics-framework \
  /v1/energy-systems/balance-framework \
  /v1/energy-systems/bioenergy-framework \
  /v1/energy-systems/bioenergy-feedstocks \
  /v1/energy-systems/bioenergy-pathways \
  /v1/energy-systems/biological-carbon-bridges \
  '/v1/energy-systems/feedstock-energy-estimate?mass_tonnes=10&energy_content_kwh_per_tonne=4000&conversion_efficiency_pct=80' \
  '/v1/energy-systems/anaerobic-digestion-energy-estimate?feedstock_mass_tonnes=10&biogas_yield_m3_per_tonne=100&methane_fraction_pct=60&methane_energy_kwh_per_m3=10&conversion_efficiency_pct=40' \
  '/v1/energy-systems/biochar-carbon-estimate?biochar_mass_kg=1000&carbon_fraction_pct=70&stable_fraction_pct=80' \
  '/v1/energy-systems/biomass-to-oil-energy-estimate?feedstock_mass_tonnes=10&oil_yield_mass_pct=30&oil_energy_content_kwh_per_tonne=9000&downstream_conversion_efficiency_pct=90' \
  /v1/energy-systems/bioenergy-scenario-template \
  /v1/carbon-nature; do
  curl -fsS "$BASE$path" >/dev/null || fail "endpoint failed: $path"
done

bio="$(curl -fsS "$BASE/v1/energy-systems/bioenergy-framework")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="0.7.0"; assert x["counts"]=={"feedstock_classes":5,"pathways":6,"carbon_nature_bridges":6,"executable_models":4,"scenario_contracts":1}; g=x["guardrails"]; assert g["cross_domain_carbon_nature_targets_validated"] is True; assert g["biomass_carbon_neutrality_assumed"] is False; assert g["carbon_credit_eligibility_determined"] is False' "$bio"

feed="$(curl -fsS "$BASE/v1/energy-systems/feedstock-energy-estimate?mass_tonnes=10&energy_content_kwh_per_tonne=4000&conversion_efficiency_pct=80")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["output"]["gross_energy_kwh"]=="40000"; assert x["output"]["useful_energy_kwh"]=="32000"' "$feed"

ad="$(curl -fsS "$BASE/v1/energy-systems/anaerobic-digestion-energy-estimate?feedstock_mass_tonnes=10&biogas_yield_m3_per_tonne=100&methane_fraction_pct=60&methane_energy_kwh_per_m3=10&conversion_efficiency_pct=40")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["output"]["biogas_volume_m3"]=="1000"; assert x["output"]["useful_energy_kwh"]=="2400"' "$ad"

char="$(curl -fsS "$BASE/v1/energy-systems/biochar-carbon-estimate?biochar_mass_kg=1000&carbon_fraction_pct=70&stable_fraction_pct=80")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["output"]["stable_carbon_mass_kg_c"]=="560"; assert x["method"]["carbon_to_co2_mass_ratio"]=="44/12"' "$char"

cn="$(curl -fsS "$BASE/v1/carbon-nature")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["subsystem"]["version"]=="0.5.0"; assert x["governance"]["carbon_credit_issuance"] is False' "$cn"

echo "PASS: Sustainable Catalyst Library backend v2.13.0 / Energy Systems v0.7.0 deployed and verified."
