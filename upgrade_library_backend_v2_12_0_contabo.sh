#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.12.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2120.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
for c in unzip rsync docker curl python3; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.12.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.12.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.12.0"
mkdir -p "$BACKUP_ROOT"; stamp="$(date +%Y%m%d-%H%M%S)"
if [[ -d "$ROOT" ]]; then tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.12.0-$stamp.tgz" "$(basename "$ROOT")"; fi
ENV_TMP=""; if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
mkdir -p "$ROOT"; rsync -a --delete --exclude='.env' "$SRC/" "$ROOT/"; [[ -z "$ENV_TMP" ]] || cp "$ENV_TMP" "$ROOT/.env"
cd "$ROOT"; docker compose config >/dev/null; docker compose build; docker compose up -d
for i in $(seq 1 60); do if docker ps --filter name=sc-library-backend --format '{{.Status}}' | grep -qi healthy; then break; fi; sleep 2; done
docker ps --filter name=sc-library-backend --format '{{.Status}}' | grep -qi healthy || { docker compose logs --tail=120; fail "sc-library-backend did not become healthy"; }
BASE=http://127.0.0.1:8087
health="$(curl -fsS "$BASE/health")"; python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x.get("version")=="2.12.0",x' "$health"
for path in /v1/energy-systems /v1/energy-systems/balance-framework /v1/energy-systems/economics-framework '/v1/energy-systems/energy-cost-comparison?baseline_energy_kwh=10000&baseline_price_per_kwh=0.15&candidate_energy_kwh=8000&candidate_price_per_kwh=0.15' '/v1/energy-systems/simple-payback?initial_cost=5000&annual_net_savings=1000' '/v1/energy-systems/npv?initial_cost=5000&annual_net_cash_flow=1200&discount_rate_pct=0&years=5' '/v1/energy-systems/cost-benefit?initial_cost=1000&annual_cost=100&annual_benefit=400&discount_rate_pct=0&years=5' '/v1/energy-systems/cost-efficiency?total_cost=5000&energy_saved_kwh=25000&co2e_avoided_kg=10000' '/v1/energy-systems/levelized-energy-cost?initial_cost=100000&annual_operating_cost=3000&annual_energy_kwh=50000&discount_rate_pct=0&years=20' /v1/energy-systems/economic-scenario-template /v1/carbon-nature; do curl -fsS "$BASE$path" >/dev/null || fail "endpoint failed: $path"; done
econ="$(curl -fsS "$BASE/v1/energy-systems/economics-framework")"; python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="0.6.0"; assert x["counts"]["models"]==7 and x["counts"]["executable_models"]==6; assert x["guardrails"]["external_price_feed_loaded"] is False; assert x["guardrails"]["investment_recommendation"] is False' "$econ"
echo "PASS: Sustainable Catalyst Library backend v2.12.0 / Energy Systems v0.6.0 deployed and verified."
