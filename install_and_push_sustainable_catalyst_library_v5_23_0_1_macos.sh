#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.23.0.1-repository.zip}"
TAG="v5.23.0.1"
TMP="$(mktemp -d /tmp/sc-library-v52301.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout."
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.23.0.1"
[[ -f "$SOURCE_DIR/library-backend/app/main.py" ]] || fail "repository payload missing backend"
[[ -f "$SOURCE_DIR/upgrade_library_backend_v2_34_1_contabo.sh" ]] || fail "v2.34.1 deployment installer missing"
cd "$SOURCE_DIR"
./tests/run_v52301_validation.sh
echo "=== SYNCHRONIZING v5.23.0.1 INTO GIT CHECKOUT ==="
rsync -a --delete --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"
echo "=== RELEASE IDENTITY ==="
grep -m1 'Version:' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -m1 '__version__' library-backend/app/__init__.py
git status --short
git add -A
if ! git diff --cached --quiet; then git commit -m "Library v5.23.0.1 Local Validation Environment Repair"; fi
if git rev-parse "$TAG" >/dev/null 2>&1; then
  [[ "$(git rev-list -n1 "$TAG")" == "$(git rev-parse HEAD)" ]] || fail "$TAG already exists and does not point to HEAD"
else
  git tag -a "$TAG" -m "Sustainable Catalyst Library v5.23.0.1 — Local Validation Environment Repair"
fi
git push origin HEAD
git push origin "$TAG"
echo "PASS: Knowledge Library v5.23.0.1 Local Validation Environment Repair committed, tagged, and pushed."
