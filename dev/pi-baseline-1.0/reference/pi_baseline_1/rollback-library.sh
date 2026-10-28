#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=/mnt/catalyst-ssd/library-dev/pi-baseline-1
PLUGIN=/var/www/html/wp-content/mu-plugins/sc-pi-library-bridge.php
PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
test -f "$ROOT/compose.yaml" || { echo 'No baseline Library manifest; nothing to stop.'; exit 0; }
if sudo docker exec site-wordpress-1 test -f "$PLUGIN"; then
  current=$(sudo docker exec site-wordpress-1 sha256sum "$PLUGIN" | awk '{print $1}')
  expected=$(sha256sum "$PACKAGE_DIR/sc-pi-library-bridge.php" | awk '{print $1}')
  test "$current" = "$expected" || { echo 'Existing WP plugin changed; manual review needed.' >&2; exit 1; }
  sudo docker exec site-wordpress-1 rm "$PLUGIN"
fi
cd "$ROOT"
sudo docker compose -p sc-library-pi-dev -f compose.yaml down
echo 'Pi-only Library bridge removed and isolated services stopped.'
echo 'All PostgreSQL and artifact data retained on SSD. Workbench untouched.'
