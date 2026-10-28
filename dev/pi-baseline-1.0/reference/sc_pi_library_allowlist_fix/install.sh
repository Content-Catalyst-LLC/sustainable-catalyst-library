#!/usr/bin/env bash
set -Eeuo pipefail
WP=site-wordpress-1
SOURCE=/var/www/html/wp-content/mu-plugins/catalyst-dev-safety.php
SSD=/mnt/catalyst-ssd/wordpress-dev/site/.sc-dev-repair-backups
PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
failed(){ printf '\nSTOP: %s\n' "$*" >&2; exit 1; }
echo '=== Preflight: private Pi development instance ==='
sudo docker inspect "$WP" >/dev/null || failed 'WordPress dev container unavailable'
sudo docker inspect sc-library-pi-backend sc-library-pi-db sc-library-pi-adapter >/dev/null || failed 'Library containers missing'
sudo docker exec "$WP" test -f "$SOURCE" || failed 'WordPress dev safety plugin missing'
sudo docker inspect "$WP" | python3 -c 'import json,sys; j=json.load(sys.stdin)[0]; ports=j["HostConfig"].get("PortBindings"); nets=j["NetworkSettings"]["Networks"]; assert not ports and "sc-internal" in nets, "STOP: unexpected WordPress publication/network"; print("PASS: private WordPress and sc-internal verified")'
for n in sc-library-pi-backend sc-library-pi-db sc-library-pi-adapter; do
    h=$(sudo docker inspect "$n" --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}')
    [[ "$h" = healthy ]] || failed "$n is not healthy ($h)"
done
sudo docker exec sc-library-pi-adapter python -c 'import json,urllib.request; d=json.load(urllib.request.urlopen("http://127.0.0.1:8097/health", timeout=7)); assert d.get("ok")' || failed 'Library adapter not ready'
curl -fsS --max-time 10 -o /dev/null http://127.0.0.1:8080/wp-json/sc-dev/v1/workbench/v530/status || failed 'Existing Workbench bridge unhealthy'

work=$(mktemp -d /tmp/sc-pi-lib-allowlist.XXXXXX)
timestamp=$(date -u +%Y%m%dT%H%M%SZ)
backup="$SSD/library-allowlist-$timestamp"
installed=0
cleanup(){
    code=$?
    trap - EXIT
    if [[ "$code" != 0 && "$installed" = 1 ]]; then
        echo '=== Verification failed: automatically restoring original safety policy ===' >&2
        sudo docker cp "$backup/catalyst-dev-safety.php" "$WP:$SOURCE" || true
        sudo docker exec "$WP" php -l "$SOURCE" || true
    fi
    rm -rf "$work"
    exit "$code"
}
trap cleanup EXIT
sudo mkdir -p "$backup"
sudo chmod 700 "$backup"
sudo docker cp "$WP:$SOURCE" "$backup/catalyst-dev-safety.php"
sudo cp "$backup/catalyst-dev-safety.php" "$work/safety.php"
sudo chown "$(id -u):$(id -g)" "$work/safety.php"

python3 "$PACKAGE_DIR/patch_safety.py" "$work/safety.php" "$work/patched.php" || failed 'Safety plugin source unexpectedly differs'
php -l "$work/patched.php" || failed 'Patched source failed PHP syntax'

printf '\n=== Install only the amended development allowlist ===\n'
installed=1
sudo docker cp "$work/patched.php" "$WP:$SOURCE"
sudo docker exec "$WP" php -l "$SOURCE" || failed 'Installed plugin failed PHP syntax'

printf '\n=== Verify allowed routes and default-deny safety ===\n'
sudo docker exec "$WP" php -r '
define("WP_USE_THEMES", false);
require "/var/www/html/wp-load.php";
function pi_lib_test($url, $method, $expected) {
    $r = wp_remote_request($url, array("method"=>$method,"timeout"=>8,"redirection"=>0));
    $code = is_wp_error($r) ? $r->get_error_code() : wp_remote_retrieve_response_code($r);
    $ok = ($expected === "blocked") ? ($code === "catalyst_dev_http_blocked") : ($code === 200);
    echo ($ok ? "PASS" : "FAIL") . " $method $url -> $code\n";
    if (!$ok) { exit(1); }
}
pi_lib_test("http://sc-library-pi-adapter:8097/health", "GET", "ok");
pi_lib_test("http://sc-library-pi-adapter:8097/search?q=carbon", "GET", "ok");
pi_lib_test("http://sc-library-pi-adapter:8097/readiness", "GET", "blocked");
pi_lib_test("http://sc-library-pi-adapter:8097/health?x=1", "GET", "blocked");
pi_lib_test("http://sc-library-pi-adapter:8097/health", "POST", "blocked");
pi_lib_test("https://example.com/", "GET", "blocked");
pi_lib_test("http://catalyst-workbench-repair-7y:8088/v530/status", "GET", "ok");
' || failed 'WordPress HTTP-safety regression check failed'

printf '\n=== Verify installed Library WordPress route ===\n'
curl -fsS --max-time 20 http://127.0.0.1:8080/wp-json/sc-dev/v1/library/status | python3 -c '
import json,sys
d=json.load(sys.stdin)
assert d.get("ok") and d.get("role")=="read-only-thin-adapter", d
print("PASS: WordPress Library bridge connected, version", d.get("version"))
' || failed 'Library status route failed'
for v in v530 v590 v601; do
    curl -fsS --max-time 12 -o /dev/null "http://127.0.0.1:8080/wp-json/sc-dev/v1/workbench/$v/status" || failed "Workbench $v regression"
done
anon=$(curl -sS --max-time 12 -o /dev/null -w '%{http_code}' 'http://127.0.0.1:8080/wp-json/sc-dev/v1/library/search?q=carbon')
[[ "$anon" = 401 || "$anon" = 403 ]] || failed "Anonymous Library search not denied (HTTP $anon)"
cat <<EOF

PASS — Pi Baseline 1.0 Library WordPress connection verified.
Default-deny external HTTP and blocked POSTs preserved.
Anonymous Library search denied: HTTP $anon.
Original safety plugin backup: $backup/catalyst-dev-safety.php
Rollback:
  sudo docker cp '$backup/catalyst-dev-safety.php' site-wordpress-1:$SOURCE
No Docker service restarted; production untouched.
EOF
