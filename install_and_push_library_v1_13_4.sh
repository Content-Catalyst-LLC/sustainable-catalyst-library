#!/usr/bin/env bash
set -euo pipefail

DOWNLOADS="$HOME/Downloads"
ZIP="$(find "$DOWNLOADS" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.13.4-repo*.zip' -print | head -n 1)"

if [[ -z "$ZIP" ]]; then
  echo "ERROR: sustainable-catalyst-library-v1.13.4-repo.zip was not found in Downloads."
  exit 1
fi

TARGET="$DOWNLOADS/sustainable-catalyst-library-v1.13.4-repo"
rm -rf "$TARGET"
unzip -q -o "$ZIP" -d "$DOWNLOADS"

SCRIPT="$TARGET/push_library_v1_13_4_to_github.sh"
if [[ ! -f "$SCRIPT" ]]; then
  echo "ERROR: The push script was not found after extraction."
  exit 1
fi

chmod +x "$SCRIPT"
exec "$SCRIPT"
