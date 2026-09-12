#!/usr/bin/env bash
set -Eeuo pipefail

ARCHIVE="${1:-/tmp/sustainable-catalyst-library-backend-v2.18.0.zip}"
ROOT=/opt/sustainable-catalyst/library-backend
BACKUP_ROOT=/opt/sustainable-catalyst/backups
TMP="$(mktemp -d /tmp/sc-library-v2180.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }

for c in unzip rsync docker curl python3 tar; do command -v "$c" >/dev/null || fail "$c is required"; done
[[ -f "$ARCHIVE" ]] || fail "archive not found: $ARCHIVE"
unzip -tq "$ARCHIVE" >/dev/null || fail "invalid ZIP"
unzip -q "$ARCHIVE" -d "$TMP"
SRC="$TMP/sustainable-catalyst-library-backend-v2.18.0"
[[ -f "$SRC/app/__init__.py" ]] || fail "backend payload missing"
grep -q '__version__ = "2.18.0"' "$SRC/app/__init__.py" || fail "payload is not backend v2.18.0"
grep -q 'DOMAIN_VERSION = "1.2.0"' "$SRC/app/energy_systems.py" || fail "payload does not contain Energy Systems v1.2.0"
grep -q 'MODEL_VERSION = "1.2.0"' "$SRC/app/energy_runtime.py" || fail "runtime activation v1.2.0 missing"
grep -q 'MODEL_VERSION = "1.0.0"' "$SRC/app/energy_platform.py" || fail "v1.0.0 integrated platform baseline missing"

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
  tar -C "$(dirname "$ROOT")" -czf "$BACKUP_ROOT/library-backend-before-v2.18.0-$stamp.tgz" "$(basename "$ROOT")"
fi

ENV_TMP=""
if [[ -f "$ROOT/.env" ]]; then ENV_TMP="$TMP/existing.env"; cp "$ROOT/.env" "$ENV_TMP"; fi
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
  docker compose logs --tail=180
  fail "sc-library-backend did not become healthy"
}

BASE=http://127.0.0.1:8087
health="$(curl -fsS "$BASE/health")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x.get("version")=="2.18.0",x; c=x.get("capabilities",{}); assert c.get("energy_systems_domain_version")=="1.2.0"; assert c.get("energy_integrated_platform") is True; assert c.get("energy_cross_product_runtime_activation_gateway") is True; assert c.get("energy_cross_product_runtime_targets")==5; assert c.get("energy_cross_product_handoff_packet_builders")==5; assert c.get("energy_cross_product_pull_transport") is True; assert c.get("energy_cross_product_target_consumption_certified") is True; assert c.get("energy_cross_product_outbound_push_delivery") is False; assert c.get("energy_cross_product_persistence") is False' "$health"

for path in \
  /v1/energy-systems \
  /v1/energy-systems/runtime-framework \
  /v1/energy-systems/runtime-targets \
  /v1/energy-systems/runtime-consumers \
  /v1/energy-systems/runtime-targets/lab \
  /v1/energy-systems/runtime-handoff-template/lab \
  /v1/energy-systems/platform-framework \
  /v1/energy-systems/platform-certification \
  /v1/energy-systems/decision-framework \
  /v1/energy-systems/global-energy-framework \
  /v1/energy-systems/bioenergy-framework \
  /v1/energy-systems/economics-framework \
  /v1/energy-systems/balance-framework \
  /v1/carbon-nature; do
  curl -fsS "$BASE$path" >/dev/null || fail "endpoint failed: $path"
done

runtime="$(curl -fsS "$BASE/v1/energy-systems/runtime-framework")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="1.2.0"; c=x["counts"]; assert c=={"external_runtime_targets":5,"target_packet_builders":5,"pull_handoff_contracts":5,"target_runtimes_certified_active":5}; g=x["guardrails"]; assert g["target_runtime_consumption_certified"] is True; assert g["target_contract_intake_certified"] is True; assert g["target_model_execution_certified"] is False; assert g["outbound_push_delivery_activated"] is False; assert g["cross_product_persistence_activated"] is False' "$runtime"

consumers="$(curl -fsS "$BASE/v1/energy-systems/runtime-consumers")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="1.2.0" and x["count"]==5; rows={r["target_key"]:r for r in x["items"]}; expected={"research-librarian":"8.1.0","lab":"0.101.0","workbench":"6.1.0","site-intelligence":"4.40.0","decision-studio":"2.3.0"}; assert set(rows)==set(expected); assert all(rows[k]["minimum_target_version"]==v for k,v in expected.items()); assert all(rows[k]["state"]=="certified-contract-intake" for k in expected); assert all(rows[k]["status_route"]=="/v1/energy-runtime/consumer" for k in expected); assert all(rows[k]["intake_route"]=="/v1/energy-runtime/consume" for k in expected)' "$consumers"

targets="$(curl -fsS "$BASE/v1/energy-systems/runtime-targets")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["count"]==5; rows={r["key"]:r for r in x["items"]}; assert all(r["gateway_state"]=="library-gateway-active" for r in rows.values()); assert all(r["target_runtime_state"]=="certified-contract-intake" for r in rows.values())' "$targets"

study="$(curl -fsS "$BASE/v1/energy-systems/platform-study-template")"
encoded="$(python3 -c 'import json,sys,urllib.parse; x=json.loads(sys.argv[1]); s=x["study"]; s["identity"].update({"study_id":"deploy-smoke","question":"target consumer smoke test","geography":"test","period":"2030"}); s["technologies_and_resources"]["technology_refs"]=["solar-photovoltaic"]; s["energy_balance"]["scenario_refs"]=["balance-smoke"]; s["provenance"]=[{"source_ref":"deployment-smoke"}]; print(urllib.parse.quote(json.dumps(s,separators=(",",":")),safe=""))' "$study")"
handoff="$(curl -fsS "$BASE/v1/energy-systems/runtime-handoff/lab?study=$encoded")"
python3 -c 'import json,sys,re; x=json.loads(sys.argv[1]); p=x["packet"]; assert re.fullmatch(r"es-[a-f0-9]{20}",p["handoff_id"]); assert p["target"]["key"]=="lab"; assert p["target"]["minimum_target_version"]=="0.101.0"; assert p["validation"]["status"] in {"ready","ready-with-warnings"}; assert x["delivery"]=={"mode":"pull-only","outbound_delivery_performed":False,"persistence_performed":False,"target_execution_claimed":False}' "$handoff"

cert="$(curl -fsS "$BASE/v1/energy-systems/platform-certification")"
python3 -c 'import json,sys; x=json.loads(sys.argv[1]); assert x["version"]=="1.0.0"; assert x["status"]=="pass",x; assert x["counts"]=={"checks":20,"passed":20,"failed":0}' "$cert"

echo "PASS: Sustainable Catalyst Library backend v2.18.0 / Energy Systems v1.2.0 deployed and target-consumer registry verified."
