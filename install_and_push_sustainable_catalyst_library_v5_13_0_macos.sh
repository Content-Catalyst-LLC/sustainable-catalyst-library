#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.13.0-repository.zip}"
REMOTE_URL="https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library.git"
TAG="v5.13.0"
TMP="$(mktemp -d /tmp/sc-library-v5130.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT

fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout. Clone it first: git clone $REMOTE_URL \"$REPO_DIR\""
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.13.0"
[[ -f "$SOURCE_DIR/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || fail "repository payload missing"

cd "$SOURCE_DIR"
./tests/run_v5130_validation.sh

echo "=== SYNCHRONIZING RELEASE INTO GIT CHECKOUT ==="
rsync -a --delete \
  --exclude '.git' \
  --exclude '.pytest_cache' \
  --exclude '__pycache__' \
  --exclude '*.pyc' \
  "$SOURCE_DIR/" "$REPO_DIR/"

cd "$REPO_DIR"
echo "=== RELEASE IDENTITY ==="
grep -m1 'Version:' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -m1 '__version__' library-backend/app/__init__.py

echo "=== GIT STATUS ==="
git status --short

git add -A
if git diff --cached --quiet; then
  echo "No staged changes; continuing to tag verification."
else
  git commit -m "Library v5.13.0 Hybrid Research Retrieval"
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
  target="$(git rev-list -n1 "$TAG")"
  head="$(git rev-parse HEAD)"
  [[ "$target" == "$head" ]] || fail "$TAG already exists and does not point to HEAD"
else
  git tag -a "$TAG" -m "Sustainable Catalyst Library v5.13.0 — Hybrid Research Retrieval & Core-Aware Results"
fi

git push origin HEAD
git push origin "$TAG"

echo "PASS: Knowledge Library v5.13.0 committed, tagged, and pushed."
