#!/usr/bin/env bash
set -euo pipefail

cd "$HOME/Downloads"
ZIP="$(find "$HOME/Downloads" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.11.0-repo*.zip' -print | head -n 1)"
if [[ -z "$ZIP" ]]; then
  echo "ERROR: Library v1.11.0 repository ZIP was not found in Downloads."
  exit 1
fi
DEST="$HOME/Downloads/sustainable-catalyst-library-v1.11.0-repo"
rm -rf "$DEST"
unzip -q -o "$ZIP" -d "$HOME/Downloads"
SCRIPT="$DEST/push_library_v1_11_0_to_github.sh"
if [[ ! -f "$SCRIPT" ]]; then
  echo "ERROR: Push script was not found after extraction."
  exit 1
fi
chmod +x "$SCRIPT"
"$SCRIPT"
