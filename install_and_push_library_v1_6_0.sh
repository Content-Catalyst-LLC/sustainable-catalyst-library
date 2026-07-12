#!/usr/bin/env bash
set -euo pipefail

DOWNLOADS="$HOME/Downloads"
FOLDER="$DOWNLOADS/sustainable-catalyst-library-v1.6.0-repo"

if [[ ! -d "$FOLDER" ]]; then
  ZIP="$(find "$DOWNLOADS" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.6.0-repo*.zip' -print | head -n 1)"
  if [[ -z "$ZIP" ]]; then
    echo "ERROR: sustainable-catalyst-library-v1.6.0-repo.zip was not found in Downloads."
    exit 1
  fi
  rm -rf "$FOLDER"
  unzip -q -o "$ZIP" -d "$DOWNLOADS"
fi

SCRIPT="$FOLDER/push_library_v1_6_0_to_github.sh"
if [[ ! -f "$SCRIPT" ]]; then
  echo "ERROR: Push script was not found after extraction."
  exit 1
fi

chmod +x "$SCRIPT"
"$SCRIPT"
