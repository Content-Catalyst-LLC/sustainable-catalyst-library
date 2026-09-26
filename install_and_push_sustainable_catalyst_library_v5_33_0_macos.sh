#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.33.0-repository.zip}"
TAG="v5.33.0"
TMP="$(mktemp -d /tmp/sc-library-v5330.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout."
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.33.0"
[[ -f "$SOURCE_DIR/library-backend/app/literature_review.py" ]] || fail "v5.33 literature review runtime missing"
[[ -f "$SOURCE_DIR/library-backend/native-graph-runtime/src/main.rs" ]] || fail "v5.32 Rust runtime source missing"
[[ -f "$SOURCE_DIR/upgrade_library_backend_v2_44_0_contabo.sh" ]] || fail "v2.44.0 deployment installer missing"
[[ ! -d "$SOURCE_DIR/library-backend/native-graph-runtime/target" ]] || fail "Rust target/ artifacts must not be packaged"
cd "$SOURCE_DIR"
./tests/run_v5330_validation.sh
echo "=== SYNCHRONIZING v5.33.0 INTO GIT CHECKOUT ==="
rsync -a --delete --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' --exclude 'library-backend/native-graph-runtime/target' "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"
rm -rf library-backend/native-graph-runtime/target
echo "=== RELEASE IDENTITY ==="
grep -m1 'Version:' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -m1 '__version__' library-backend/app/__init__.py
git status --short
git add -A
if ! git diff --cached --quiet; then git commit -m "Library v5.33.0 Reproducible Literature Review Engine"; fi
if git rev-parse "$TAG" >/dev/null 2>&1; then
  [[ "$(git rev-list -n1 "$TAG")" == "$(git rev-parse HEAD)" ]] || fail "$TAG already exists and does not point to HEAD"
else
  git tag -a "$TAG" -m "Sustainable Catalyst Library v5.33.0 — Reproducible Literature Review Engine"
fi
git push origin HEAD
git push origin "$TAG"
echo "PASS: Knowledge Library v5.33.0 Reproducible Literature Review Engine committed, tagged, and pushed."
