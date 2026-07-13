#!/usr/bin/env bash
set -euo pipefail

cd "$HOME/Downloads"
ZIP="$(find "$HOME/Downloads" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.17.0-repo*.zip' -print | head -n 1)"
if [[ -z "$ZIP" ]]; then
  echo "ERROR: Library v1.17.0 repository ZIP was not found in Downloads."
  exit 1
fi
rm -rf "$HOME/Downloads/sustainable-catalyst-library-v1.17.0-repo"
unzip -q -o "$ZIP" -d "$HOME/Downloads"
chmod +x "$HOME/Downloads/sustainable-catalyst-library-v1.17.0-repo/push_library_v1_17_0_to_github.sh"
"$HOME/Downloads/sustainable-catalyst-library-v1.17.0-repo/push_library_v1_17_0_to_github.sh"
