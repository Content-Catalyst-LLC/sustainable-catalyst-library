#!/usr/bin/env bash
set -euo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"
if [[ ! -d "$REPO_DIR/.git" ]]; then
  echo "ERROR: $REPO_DIR is not an existing Git checkout." >&2
  echo "Clone it first: git clone https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library.git \"$REPO_DIR\"" >&2
  exit 1
fi
cd "$SOURCE_DIR"
./tests/run_v570_validation.sh
rsync -a --delete --exclude '.git' --exclude 'install_and_push_sustainable_catalyst_library_v5_7_0_macos.sh' "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"
git status --short
git add -A
git commit -m "Library v5.7.0 institutional research sources and Johns Hopkins connector" || true
git tag -a v5.7.0 -m "Sustainable Catalyst Library v5.7.0"
git push origin HEAD
git push origin v5.7.0
