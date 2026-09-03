#!/usr/bin/env bash
set -euo pipefail

VERSION="5.7.1"
TAG="v${VERSION}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
TARGET_REPO="${1:-$HOME/Downloads/sustainable-catalyst-library}"
REPO_ZIP="$SCRIPT_DIR/sustainable-catalyst-library-v${VERSION}-repository.zip"

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

command -v git >/dev/null 2>&1 || fail "git is required."
command -v unzip >/dev/null 2>&1 || fail "unzip is required."
command -v rsync >/dev/null 2>&1 || fail "rsync is required."
command -v python3 >/dev/null 2>&1 || fail "python3 is required."

[[ -f "$REPO_ZIP" ]] || fail "Repository ZIP not found beside installer: $REPO_ZIP"
[[ -d "$TARGET_REPO/.git" ]] || fail "Target is not a Git checkout: $TARGET_REPO"

cd "$TARGET_REPO"
[[ -z "$(git status --porcelain)" ]] || fail "Target Git checkout has uncommitted changes. Commit/stash them first."
BRANCH="$(git branch --show-current)"
[[ -n "$BRANCH" ]] || fail "Detached HEAD is not supported."

git fetch origin
if git rev-parse "$TAG" >/dev/null 2>&1; then
  fail "Local tag $TAG already exists."
fi
if git ls-remote --exit-code --tags origin "refs/tags/$TAG" >/dev/null 2>&1; then
  fail "Remote tag $TAG already exists."
fi

git pull --ff-only origin "$BRANCH"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v571.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$REPO_ZIP" -d "$TMP"
SOURCE="$TMP/sustainable-catalyst-library-v${VERSION}"
[[ -d "$SOURCE" ]] || fail "Expected repository folder missing from archive: $SOURCE"
[[ -x "$SOURCE/tests/run_v571_validation.sh" ]] || chmod +x "$SOURCE/tests/run_v571_validation.sh"

printf '\n=== VALIDATING RELEASE SOURCE ===\n'
cd "$SOURCE"
./tests/run_v571_validation.sh

printf '\n=== INSTALLING v%s INTO GIT CHECKOUT ===\n' "$VERSION"
rsync -a --delete --exclude '.git' --exclude '.pytest_cache' --exclude '__pycache__' "$SOURCE/" "$TARGET_REPO/"

cd "$TARGET_REPO"
printf '\n=== VALIDATING INSTALLED CHECKOUT ===\n'
chmod +x tests/run_v571_validation.sh
./tests/run_v571_validation.sh

git diff --check

printf '\n=== CHANGES ===\n'
git status --short
git diff --stat

git add -A
if git diff --cached --quiet; then
  fail "No v${VERSION} changes were staged."
fi

printf '\n=== COMMITTING ===\n'
git commit -m "Library v5.7.1 Johns Hopkins widget integration and source registry repair"

git tag -a "$TAG" -m "Sustainable Catalyst Library v${VERSION}"

printf '\n=== PUSHING %s ===\n' "$BRANCH"
git push origin "$BRANCH"

printf '\n=== PUSHING TAG ===\n'
git push origin "$TAG"

printf '\n=== FINAL VERIFICATION ===\n'
git status
git log -1 --oneline
git tag --list "$TAG"
git ls-remote --tags origin | grep "refs/tags/$TAG" || fail "Remote tag verification failed."

printf '\nPASS: Sustainable Catalyst Library v%s pushed to GitHub.\n' "$VERSION"
