#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.14.0.1-repository.zip}"
TAG="v5.14.0.1"
TMP="$(mktemp -d /tmp/sc-library-v51401.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout."
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.14.0.1"
[[ -f "$SOURCE_DIR/library-backend/app/main.py" ]] || fail "repository payload missing"
cd "$SOURCE_DIR"
./tests/run_v51401_validation.sh
echo "=== SYNCHRONIZING v5.14.0.1 REPAIR INTO GIT CHECKOUT ==="
rsync -a --delete --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"
grep -m1 '__version__' library-backend/app/__init__.py
git status --short
git add -A
if git diff --cached --quiet; then echo "No staged changes; continuing to tag verification."; else git commit -m "Library v5.14.0.1 Citation Graph Route Precedence Repair"; fi
if git rev-parse "$TAG" >/dev/null 2>&1; then
  target="$(git rev-list -n1 "$TAG")"; head="$(git rev-parse HEAD)"
  [[ "$target" == "$head" ]] || fail "$TAG already exists and does not point to HEAD"
else
  git tag -a "$TAG" -m "Sustainable Catalyst Library v5.14.0.1 — Citation Graph Route Precedence Repair"
fi
git push origin HEAD
git push origin "$TAG"
echo "PASS: Knowledge Library v5.14.0.1 committed, tagged, and pushed."
