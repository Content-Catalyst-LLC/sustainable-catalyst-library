#!/usr/bin/env bash
set -euo pipefail

cd "$HOME/Downloads"
ZIP="$(find "$HOME/Downloads" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.18.1-repo*.zip' -print | head -n 1)"
if [[ -z "$ZIP" ]]; then
  echo "ERROR: Library v1.18.1 repository ZIP was not found in Downloads."
  exit 1
fi

rm -rf "$HOME/Downloads/sustainable-catalyst-library-v1.18.1-repo"
unzip -q -o "$ZIP" -d "$HOME/Downloads"
SCRIPT="$HOME/Downloads/sustainable-catalyst-library-v1.18.1-repo/push_library_v1_18_1_to_github.sh"
[[ -f "$SCRIPT" ]] || { echo "ERROR: Push script was not found after extraction."; exit 1; }
chmod +x "$SCRIPT"
"$SCRIPT"
