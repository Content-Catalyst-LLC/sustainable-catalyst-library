#!/usr/bin/env bash
set -Eeuo pipefail
REPO_DIR="${1:-$HOME/Downloads/sustainable-catalyst-library}"
BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_ZIP="${2:-$BUNDLE_DIR/sustainable-catalyst-library-v5.49.0-repository.zip}"
TAG="v5.49.0"
TMP="$(mktemp -d /tmp/sc-library-v5490.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
fail(){ echo "ERROR: $*" >&2; exit 1; }
[[ -d "$REPO_DIR/.git" ]] || fail "$REPO_DIR is not an existing Git checkout."
[[ -f "$RELEASE_ZIP" ]] || fail "release repository ZIP not found: $RELEASE_ZIP"
for c in rsync git unzip python3; do command -v "$c" >/dev/null || fail "$c is required"; done
unzip -tq "$RELEASE_ZIP" >/dev/null || fail "invalid repository ZIP"
unzip -q "$RELEASE_ZIP" -d "$TMP"
SOURCE_DIR="$TMP/sustainable-catalyst-library-v5.49.0"
[[ -f "$SOURCE_DIR/library-backend/app/durable_job_queue.py" ]] || fail "durable job queue module missing"
[[ -f "$SOURCE_DIR/upgrade_library_backend_v2_60_0_contabo.sh" ]] || fail "v2.60.0 deployment installer missing"
grep -q '__version__ = "2.60.0"' "$SOURCE_DIR/library-backend/app/__init__.py" || fail "backend is not v2.60.0"
grep -q 'Version: 5.49.0' "$SOURCE_DIR/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "WordPress plugin is not v5.49.0"
grep -q 'sc-library-execution-fabric-readiness/1.0' "$SOURCE_DIR/library-backend/app/durable_job_queue.py" || fail "execution-fabric contract missing"
grep -q 'sc-library-redis:' "$SOURCE_DIR/library-backend/compose.yml" || fail "Redis compose service missing"
[[ ! -d "$SOURCE_DIR/library-backend/native-graph-runtime/target" ]] || fail "Rust target artifacts must not be packaged"
[[ ! -f "$SOURCE_DIR/library-backend/go-ingestion-runtime/sc-library-ingestion-runtime" ]] || fail "compiled Go binary must not be packaged"
cd "$SOURCE_DIR"
./tests/run_v5490_validation.sh

echo "=== SYNCHRONIZING v5.49.0 INTO GIT CHECKOUT ==="
rsync -a --delete --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' --exclude '*.pyc' --exclude 'library-backend/native-graph-runtime/target' --exclude 'library-backend/go-ingestion-runtime/sc-library-ingestion-runtime' "$SOURCE_DIR/" "$REPO_DIR/"
cd "$REPO_DIR"; rm -rf library-backend/native-graph-runtime/target; rm -f library-backend/go-ingestion-runtime/sc-library-ingestion-runtime
git add -A
if ! git diff --cached --quiet; then git commit -m "Library v5.49.0 Durable Research Job Queue & Execution State"; fi
if git rev-parse "$TAG" >/dev/null 2>&1; then [[ "$(git rev-list -n1 "$TAG")" == "$(git rev-parse HEAD)" ]] || fail "$TAG already exists and does not point to HEAD"; else git tag -a "$TAG" -m "Sustainable Catalyst Library v5.49.0 — Durable Research Job Queue & Execution State"; fi
git push origin HEAD; git push origin "$TAG"
echo "PASS: Knowledge Library v5.49.0 committed, tagged, and pushed."
