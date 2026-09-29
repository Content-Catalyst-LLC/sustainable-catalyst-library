#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.48.0-repository.zip}"
TAG="v5.48.0"
TMP="$(mktemp -d /tmp/sc-library-v5480.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout."
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip python3; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.48.0"
[[ -f "$SOURCE_DIR/library-backend/app/cross_language_resolution.py" ]] || fail "cross-language resolution module missing"
[[ -f "$SOURCE_DIR/upgrade_library_backend_v2_59_0_contabo.sh" ]] || fail "v2.59.0 deployment installer missing"
grep -q '__version__ = "2.59.0"' "$SOURCE_DIR/library-backend/app/__init__.py" || fail "backend is not v2.59.0"
grep -q 'Version: 5.48.0' "$SOURCE_DIR/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress plugin is not v5.48.0"
grep -q 'sc-library-cross-language-resolution-readiness/1.0' "$SOURCE_DIR/library-backend/app/cross_language_resolution.py" || fail "v5.48 contract missing"
[[ ! -d "$SOURCE_DIR/library-backend/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be packaged"
[[ ! -f "$SOURCE_DIR/library-backend/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go binary must not be packaged"
cd "$SOURCE_DIR"
./tests/run_v5480_validation.sh

echo "=== SYNCHRONIZING v5.48.0 INTO GIT CHECKOUT ==="
rsync -a --delete \
  --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' \
  --exclude 'library-backend/native-graph-runtime/target' \
  --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' \
  "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"
rm -rf library-backend/native-graph-runtime/target
rm -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime

git add -A
if ! git diff --cached --quiet; then
  git commit -m "Library v5.48.0 Cross-Language Entity, Name & Historical Toponym Resolution"
fi
if git rev-parse "$TAG" >/dev/null 2>&1; then
  [[ "$(git rev-list -n1 "$TAG")" == "$(git rev-parse HEAD)" ]] || fail "$TAG already exists and does not point to HEAD"
else
  git tag -a "$TAG" -m "Sustainable Catalyst Library v5.48.0 — Cross-Language Entity, Name & Historical Toponym Resolution"
fi
git push origin HEAD
git push origin "$TAG"
echo "PASS: Knowledge Library v5.48.0 committed, tagged, and pushed."
