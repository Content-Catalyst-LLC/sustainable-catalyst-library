#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/Downloads"
ZIP="$(find "$HOME/Downloads" -maxdepth 1 -type f -name 'sustainable-catalyst-library-v1.20.0-repo*.zip' -print | head -n 1)"
[[ -n "$ZIP" ]] || { echo "ERROR: Library v1.20.0 repository ZIP was not found in Downloads."; exit 1; }
rm -rf "$HOME/Downloads/sustainable-catalyst-library-v1.20.0-repo"
unzip -q -o "$ZIP" -d "$HOME/Downloads"
chmod +x "$HOME/Downloads/sustainable-catalyst-library-v1.20.0-repo/push_library_v1_20_0_to_github.sh"
"$HOME/Downloads/sustainable-catalyst-library-v1.20.0-repo/push_library_v1_20_0_to_github.sh"
