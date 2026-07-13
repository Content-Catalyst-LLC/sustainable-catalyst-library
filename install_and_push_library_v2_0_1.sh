#!/usr/bin/env bash
set -euo pipefail

DOWNLOADS="$HOME/Downloads"
ZIP="$(find "$DOWNLOADS" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v2.0.1-repo*.zip' -print | head -n 1)"

if [[ -z "$ZIP" ]]; then
  echo "ERROR: Library v2.0.1 repository ZIP was not found in Downloads."
  exit 1
fi

TARGET="$DOWNLOADS/sustainable-catalyst-library-v2.0.1-repo"
rm -rf "$TARGET"
unzip -q -o "$ZIP" -d "$DOWNLOADS"

SCRIPT="$TARGET/push_library_v2_0_1_to_github.sh"
[[ -f "$SCRIPT" ]] || { echo "ERROR: Push helper was not found after extraction."; exit 1; }
chmod +x "$SCRIPT"
exec "$SCRIPT"
