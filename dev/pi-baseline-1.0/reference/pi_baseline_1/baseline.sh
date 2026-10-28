#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=/mnt/catalyst-ssd
WB_DIR="$ROOT/workbench-dev/optimized-standalone"
WB_CONTAINER=catalyst-workbench-repair-7y
WB_IMAGE=catalyst-workbench-repair:v540-optimized-20261027
LIB_REPO="$ROOT/repositories/sustainable-catalyst-library/library-backend"
LIB_DIR="$ROOT/library-dev/pi-baseline-1"
PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP=site-wordpress-1
PLUGIN=sc-pi-library-bridge.php
COMPOSE=(sudo docker compose -p sc-library-pi-dev -f "$LIB_DIR/compose.yaml")

say(){ printf '\n=== %s ===\n' "$*"; }
fail(){ echo "STOP: $*" >&2; exit 1; }
# Don't export service credentials or print compose resolved configuration (which would show secrets).

check_environment(){
  say 'Check Pi-only development boundaries'
  test -d "$ROOT" || fail 'SSD root is missing'
  test -d "$LIB_REPO" || fail 'Library backend repository missing'
  test -f "$LIB_REPO/Dockerfile" || fail 'Library Dockerfile missing'
  test -f "$LIB_REPO/app/main.py" || fail 'Library source missing'
  test -f "$WB_DIR/compose.yaml" || fail 'Optimized Workbench Compose file missing'
  sudo docker inspect "$WB_CONTAINER" >/dev/null 2>&1 || fail 'Workbench container not found'
  sudo docker image inspect "$WB_IMAGE" >/dev/null 2>&1 || fail 'Optimized image missing'
  sudo docker network inspect sc-internal >/dev/null 2>&1 || fail 'Private sc-internal network missing'
  sudo docker inspect "$WP" >/dev/null 2>&1 || fail 'Local WordPress dev container missing'
  python3 - "$WP" <<'PY'
import json,subprocess,sys
for name in (sys.argv[1],):
  c=json.loads(subprocess.check_output(['sudo','docker','inspect',name]))[0]
  nets=c['NetworkSettings']['Networks']
  if 'sc-internal' not in nets: raise SystemExit('STOP: WordPress is not on sc-internal')
  if c['HostConfig']['PortBindings']: raise SystemExit('STOP: WordPress itself has host-published ports')
proxy=json.loads(subprocess.check_output(['sudo','docker','inspect','site-proxy-1']))[0]
ports=proxy['HostConfig'].get('PortBindings') or {}
if ports.get('80/tcp') != [{'HostIp':'127.0.0.1','HostPort':'8080'}]:
  raise SystemExit('STOP: WP dev proxy is not loopback-only on port 8080')
wb=json.loads(subprocess.check_output(['sudo','docker','inspect','catalyst-workbench-repair-7y']))[0]
if wb['Config'].get('Labels',{}).get('com.docker.compose.project')!='sc-workbench-pi-dev':
  raise SystemExit('STOP: Unexpected Workbench Compose owner')
if (wb['HostConfig'].get('PortBindings') or {}).get('8088/tcp') != [{'HostIp':'127.0.0.1','HostPort':'8088'}]:
  raise SystemExit('STOP: Workbench port not loopback-only')
if not any(m['Destination']=='/data' and m['Source']=='/mnt/catalyst-ssd/workbench-dev/optimized-standalone/data' for m in wb['Mounts']):
  raise SystemExit('STOP: Workbench SSD data mount mismatch')
print('PASS: isolated WordPress proxy, Workbench owner, and SSD mount')
PY
  sudo docker exec "$WP" test -f /var/www/html/wp-content/mu-plugins/sc-dev-no-egress.php \
    || echo 'NOTE: Standard egress safeguard filename not found; verify existing dev guard before Library activation.'
  curl -fsS --max-time 10 -o /dev/null 'http://127.0.0.1:8088/v540/status' || fail 'Workbench API unhealthy'
  curl -fsS --max-time 10 -o /dev/null 'http://127.0.0.1:8080/wp-json/sc-dev/v1/workbench/v530/status' || fail 'WordPress dev bridge unhealthy'
  df -h "$ROOT" | tail -1
  free -h | sed -n '1,2p'
  echo 'PASS: Preflight complete'
}

wait_workbench(){
  for i in $(seq 1 60); do
    if curl -fsS --max-time 5 -o /dev/null 'http://127.0.0.1:8088/v540/status' 2>/dev/null; then return 0; fi
    sleep 3
  done
  return 1
}

certify_workbench(){
  check_environment
  say 'Prepare consistent offline Workbench data backup before recreation'
  local backup="$ROOT/workbench-dev/optimized-standalone-certification/$(date -u +%Y%m%dT%H%M%SZ)"
  sudo mkdir -p "$backup"
  local source_hash image_hash before after marker expected observed
  source_hash=$(sha256sum "$ROOT/repositories/sustainable-catalyst-workbench/backend/app/v540.py" | awk '{print $1}')
  image_hash=$(sudo docker run --rm --entrypoint sha256sum "$WB_IMAGE" /app/app/v540.py | awk '{print $1}')
  test "$source_hash" = "$image_hash" || fail 'Optimized image and source differ'
  before=$(sudo docker inspect "$WB_CONTAINER" --format '{{.Id}}')
  marker="$WB_DIR/data/.sc-pi-baseline-1-recreation-test"
  test ! -e "$marker" || fail 'A previous recreation-test marker already exists'
  local stop_started=0
  trap 'if [[ "${stop_started:-0}" = 1 ]]; then echo "RECOVERY: trying to start only the existing Workbench Compose service" >&2; (cd "$WB_DIR" && sudo docker compose -p sc-workbench-pi-dev -f compose.yaml up -d --no-deps --no-build workbench) || true; fi' EXIT
  (cd "$WB_DIR" && sudo docker compose -p sc-workbench-pi-dev -f compose.yaml stop workbench) || fail 'Could not stop Workbench safely'
  stop_started=1
  sudo tar -C "$WB_DIR/data" -cf "$backup/workbench-data-before.tar" . || fail 'Consistent SSD data backup failed'
  expected=$(date -u +%Y%m%dT%H%M%SZ)-$(head -c 16 /dev/urandom | od -An -tx1 | tr -d '[:space:]')
  printf '%s\n' "$expected" | sudo tee "$marker" >/dev/null
  say 'Recreate Workbench only under its verified Compose project'
  if ! (cd "$WB_DIR" && sudo docker compose -p sc-workbench-pi-dev -f compose.yaml up -d --no-deps --no-build --force-recreate workbench); then
    fail "Recreation command failed. SSD backup: $backup"
  fi
  wait_workbench || fail "Workbench failed to recover. Inspect Compose logs; SSD backup: $backup"
  after=$(sudo docker inspect "$WB_CONTAINER" --format '{{.Id}}')
  test "$before" != "$after" || fail 'Container ID unchanged; recreation not demonstrated'
  observed=$(sudo cat "$marker")
  test "$observed" = "$expected" || fail "Persistent data changed after recreation. Backup: $backup"
  for v in v530 v590 v601; do
    curl -fsS --max-time 12 -o /dev/null "http://127.0.0.1:8080/wp-json/sc-dev/v1/workbench/$v/status" || fail "WP bridge $v failed"
  done
  test "$(sudo docker inspect "$WB_CONTAINER" --format '{{.State.Health.Status}}')" = healthy || fail 'Workbench Docker health not healthy'
  sudo rm "$marker"
  printf 'Pi Baseline 1.0 Workbench recreation PASS\nold=%s\nnew=%s\nsource_sha256=%s\nbackup=%s\n' "$before" "$after" "$source_hash" "$backup" | sudo tee "$backup/certification.txt" >/dev/null
  stop_started=0
  trap - EXIT
  echo "PASS: Workbench recreated, offline SSD data backup retained, persistence marker survived, source hash preserved, WordPress bridges operational."
  echo "Certification record: $backup/certification.txt"
}

prepare_library(){
  check_environment
  say 'Create isolated Library developer configuration (no existing secrets reused)'
  local existing=0
  if [[ -d "$LIB_DIR" && -e "$LIB_DIR/compose.yaml" ]]; then existing=1; fi
  sudo mkdir -p "$LIB_DIR/postgres-data" "$LIB_DIR/artifacts"
  if [[ -f "$LIB_DIR/.env" ]]; then
    grep -q '^SC_PI_LIBRARY_BASELINE=1.0$' "$LIB_DIR/.env" || fail 'Unrecognized existing Library .env; refusing to overwrite'
    echo 'Existing isolated credentials retained.'
  else
    sudo test ! -e "$LIB_DIR/.env" || fail 'Unexpected existing Library environment path'
    local password api_key
    password=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d '[:space:]')
    api_key=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d '[:space:]')
    printf 'SC_PI_LIBRARY_BASELINE=1.0\nPOSTGRES_USER=sc_pi_library\nPOSTGRES_DB=sc_pi_library\nPOSTGRES_PASSWORD=%s\nDATABASE_URL=postgresql://sc_pi_library:%s@db:5432/sc_pi_library\nSC_LIBRARY_BACKEND_API_KEY=%s\nSC_LIBRARY_ENVIRONMENT=pi-development\n' "$password" "$password" "$api_key" | sudo tee "$LIB_DIR/.env" >/dev/null
    sudo chmod 600 "$LIB_DIR/.env"
  fi
  if [[ "$existing" = 1 ]] && ! cmp -s "$PACKAGE_DIR/compose.library.yaml" "$LIB_DIR/compose.yaml"; then
    fail 'Existing Library Compose manifest differs; refusing overwrite'
  fi
  sudo cp "$PACKAGE_DIR/compose.library.yaml" "$LIB_DIR/compose.yaml"
  sudo cp "$PACKAGE_DIR/adapter.py" "$LIB_DIR/adapter.py"
  sudo chmod 644 "$LIB_DIR/compose.yaml" "$LIB_DIR/adapter.py"
  printf '.env\npostgres-data/\nartifacts/\n' | sudo tee "$LIB_DIR/.gitignore" >/dev/null
  (cd "$LIB_DIR" && sudo docker compose -p sc-library-pi-dev -f compose.yaml config --quiet) || fail 'Invalid isolated Library Compose configuration'
  if grep -qE '^[[:space:]]+ports:' "$LIB_DIR/compose.yaml"; then fail 'Library manifest unexpectedly publishes ports'; fi
  echo "PASS: Library staged in $LIB_DIR"
  echo 'No production credentials, no external published ports, no automatic federation, no workers.'
}

wait_library(){
  local state
  for i in $(seq 1 80); do
    state=$(sudo docker inspect sc-library-pi-backend --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}' 2>/dev/null || true)
    if [[ "$state" = healthy ]]; then return 0; fi
    if [[ "$state" = unhealthy ]]; then sudo docker logs --tail 35 sc-library-pi-backend >&2 || true; fi
    sleep 3
  done
  return 1
}

activate_library(){
  test -f "$LIB_DIR/compose.yaml" && test -f "$LIB_DIR/.env" || fail 'Run prepare-library first'
  check_environment
  say 'Build Pi-only independent Library backend (ARM64 native; no production deployment)'
  (cd "$LIB_DIR" && sudo docker compose -p sc-library-pi-dev -f compose.yaml build library-backend) || fail 'Library image build failed; Workbench unchanged'
  say 'Start isolated PostgreSQL, Library, and read-only adapter'
  (cd "$LIB_DIR" && sudo docker compose -p sc-library-pi-dev -f compose.yaml up -d --no-build) || fail 'Library startup failed; Workbench unchanged'
  wait_library || fail 'Library backend not healthy; check sudo docker logs sc-library-pi-backend'
  local adapter_ok=0
  for i in $(seq 1 30); do
    if sudo docker exec sc-library-pi-adapter python -c 'import urllib.request; urllib.request.urlopen("http://127.0.0.1:8097/health",timeout=5)' >/dev/null 2>&1; then adapter_ok=1; break; fi
    sleep 2
  done
  test "$adapter_ok" = 1 || fail 'Library adapter not ready; leave WP unchanged'
  say 'Install WP development-only Library thin adapter'
  local target=/var/www/html/wp-content/mu-plugins/$PLUGIN
  if sudo docker exec "$WP" test -f "$target"; then
    local installed candidate
    installed=$(sudo docker exec "$WP" sha256sum "$target" | awk '{print $1}')
    candidate=$(sha256sum "$PACKAGE_DIR/$PLUGIN" | awk '{print $1}')
    test "$installed" = "$candidate" || fail 'Existing WordPress Library plugin differs; refusing overwrite'
  else
    sudo docker cp "$PACKAGE_DIR/$PLUGIN" "$WP:$target"
  fi
  sudo docker exec "$WP" php -l "$target" || fail 'WordPress plugin PHP syntax failed'
  say 'Verify WordPress -> Library read-only connection'
  curl -fsS --max-time 20 'http://127.0.0.1:8080/wp-json/sc-dev/v1/library/status' | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("ok") and d.get("role")=="read-only-thin-adapter",d; print("PASS: Library connected through WordPress. Version:",d.get("version"))' || fail 'WP Library status not ready; check dev WP HTTP policy and container networks'
  echo 'PASS: isolated Knowledge Library backend and WordPress thin adapter connected.'
  echo 'Admin UI: http://localhost:8081/wp-admin/tools.php?page=sc-pi-library'
}

verify(){
  say 'Workbench status, health and SSD mount'
  curl -fsS --max-time 15 -o /dev/null 'http://127.0.0.1:8088/v540/status' || fail 'Workbench unavailable'
  sudo docker inspect "$WB_CONTAINER" --format 'Workbench: {{.State.Health.Status}} | {{.HostConfig.RestartPolicy.Name}}'
  sudo docker inspect "$WB_CONTAINER" --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{println}}{{end}}'
  say 'Knowledge Library isolation and status'
  sudo docker inspect sc-library-pi-backend --format 'Library: {{.State.Health.Status}} | Networks: {{range $name,$_ := .NetworkSettings.Networks}}{{$name}} {{end}}'
  sudo docker inspect sc-library-pi-db --format 'PostgreSQL: {{.State.Health.Status}}'
  sudo docker inspect sc-library-pi-adapter --format 'Adapter: {{.State.Health.Status}}'
  curl -fsS --max-time 15 'http://127.0.0.1:8080/wp-json/sc-dev/v1/library/status' | python3 -c 'import json,sys; d=json.load(sys.stdin); assert d.get("ok"),d; print("Library via WordPress: HTTP 200, connected")'
  for v in v530 v590 v601; do curl -fsS --max-time 12 -o /dev/null "http://127.0.0.1:8080/wp-json/sc-dev/v1/workbench/$v/status"; done
  sudo docker exec sc-library-pi-adapter python -c 'import json,urllib.request; d=json.load(urllib.request.urlopen("http://127.0.0.1:8097/search?q=carbon",timeout=12)); assert isinstance(d,dict); print("Library source-search route: PASS (empty results are expected until data is added)")'
  echo 'PASS: Pi Baseline 1.0 services connected and read-only search route responds.'
  echo 'REMAINING: human browser admin search, research object fixtures/real data, and GitHub code review.'
}

help(){
  cat <<'EOF'
Pi Development Baseline 1.0
Usage: bash baseline.sh audit | certify-workbench | prepare-library | activate-library | verify | run
  audit              Read-only safety and inventory checks
  certify-workbench  Back up /data and force-recreate Workbench; check persistence and status
  prepare-library    Create separate local Library DB secrets and Compose configuration
  activate-library   Build/start private Library DB and backend; install read-only WP adapter
  verify             Verify API/WordPress status and Library read-only search
  run                Execute all above in order; stop on first failed gate
EOF
}
case "${1:-help}" in
  audit) check_environment ;;
  certify-workbench) certify_workbench ;;
  prepare-library) prepare_library ;;
  activate-library) activate_library ;;
  verify) verify ;;
  run) check_environment; certify_workbench; prepare_library; activate_library; verify ;;
  *) help ;;
esac
