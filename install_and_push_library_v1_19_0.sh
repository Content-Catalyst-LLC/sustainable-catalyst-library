#!/usr/bin/env bash
set -euo pipefail

VERSION="1.19.0"
DOWNLOADS="$HOME/Downloads"
ZIP="$(find "$DOWNLOADS" -maxdepth 1 -type f -name "sustainable-catalyst-library-v${VERSION}-repo*.zip" -print | head -n 1)"

if [[ -z "$ZIP" ]]; then
  echo "ERROR: Library v${VERSION} repository ZIP was not found in Downloads."
  exit 1
fi

TARGET="$DOWNLOADS/sustainable-catalyst-library-v${VERSION}-repo"
rm -rf "$TARGET"
unzip -q -o "$ZIP" -d "$DOWNLOADS"

SCRIPT="$TARGET/push_library_v1_19_0_to_github.sh"
[[ -f "$SCRIPT" ]] || { echo "ERROR: Push script was not found after extraction: $SCRIPT"; exit 1; }
chmod +x "$SCRIPT"
exec "$SCRIPT"
