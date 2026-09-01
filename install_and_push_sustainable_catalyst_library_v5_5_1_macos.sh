#!/usr/bin/env bash
set -euo pipefail
RELEASE_VERSION="5.5.1"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION} — Ingestion Hardening & Adaptive Reindex Recovery"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPOSITORY_BASENAME="sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip"
CHECKSUM_BASENAME="SHA256SUMS-v${RELEASE_VERSION}.txt"
DEFAULT_REPO="$DOWNLOADS/sustainable-catalyst-library"
REMOTE_URL="https://github.com/Content-Catalyst-LLC/sustainable-catalyst-library.git"

say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

find_release_zip(){
  local c
  for c in "$SCRIPT_DIR/$REPOSITORY_BASENAME" "$DOWNLOADS/$REPOSITORY_BASENAME"; do
    [[ -f "$c" ]] && { printf '%s\n' "$c"; return; }
  done
  find "$DOWNLOADS" -maxdepth 3 -type f -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository*.zip" -print 2>/dev/null | sort | tail -1
}

is_library_repo(){
  local p="$1"
  [[ -d "$p/.git" && -f "$p/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] \
    && grep -q 'Plugin Name: Sustainable Catalyst Library' "$p/sustainable-catalyst-library/sustainable-catalyst-library.php"
}

find_repo(){
  local c remote
  if [[ -n "${SC_LIBRARY_REPO:-}" ]]; then
    is_library_repo "$SC_LIBRARY_REPO" || fail "SC_LIBRARY_REPO is not a Sustainable Catalyst Library Git repository: $SC_LIBRARY_REPO"
    printf '%s\n' "$SC_LIBRARY_REPO"; return
  fi
  for c in "$DEFAULT_REPO" "$DOWNLOADS/sustainable-catalyst-library-main" "$HOME/sustainable-catalyst-library" "$HOME/Documents/sustainable-catalyst-library"; do
    is_library_repo "$c" && { printf '%s\n' "$c"; return; }
  done
  while IFS= read -r c; do
    [[ -n "$c" ]] || continue
    if is_library_repo "$c"; then
      remote="$(git -C "$c" remote get-url origin 2>/dev/null || true)"
      [[ "$remote" == *sustainable-catalyst-library* ]] && { printf '%s\n' "$c"; return; }
    fi
  done < <(find "$DOWNLOADS" "$HOME/Documents" -maxdepth 3 -type d -name .git -print 2>/dev/null | sed 's#/.git$##' | sort)

  if [[ ! -e "$DEFAULT_REPO" ]]; then
    say "No local Git checkout found; cloning canonical repository"
    git clone "$REMOTE_URL" "$DEFAULT_REPO" >&2
    is_library_repo "$DEFAULT_REPO" || fail "Cloned repository did not pass Library identity checks."
    printf '%s\n' "$DEFAULT_REPO"; return
  fi
  return 1
}

verify_checksum(){
  local zip="$1" manifest expected actual base
  base="$(basename "$zip")"
  for manifest in "$SCRIPT_DIR/$CHECKSUM_BASENAME" "$DOWNLOADS/$CHECKSUM_BASENAME"; do
    [[ -f "$manifest" ]] || continue
    expected="$(awk -v file="$base" '$2 == file {print $1}' "$manifest" | head -1)"
    [[ -n "$expected" ]] || continue
    actual="$(shasum -a 256 "$zip" | awk '{print $1}')"
    [[ "$actual" == "$expected" ]] || fail "Repository ZIP checksum does not match $manifest."
    printf 'Checksum verified: %s\n' "$actual"
    return
  done
  fail "Checksum manifest $CHECKSUM_BASENAME was not found or did not contain $base."
}

ensure_pytest(){
  local py venv
  py="${SC_LIBRARY_VALIDATION_PYTHON:-$(command -v python3 2>/dev/null || true)}"
  [[ -n "$py" ]] || fail "Python 3 is required."
  if "$py" -m pytest --version >/dev/null 2>&1; then
    export SC_LIBRARY_VALIDATION_PYTHON="$py"
    return
  fi
  venv="$DOWNLOADS/.sc-library-v551-validation-venv"
  [[ -d "$venv" ]] || "$py" -m venv "$venv"
  "$venv/bin/python3" -m pip install -q 'pytest>=8,<10' 'pydantic>=2.11,<3'
  export SC_LIBRARY_VALIDATION_PYTHON="$venv/bin/python3"
}

ZIP="$(find_release_zip)"
[[ -f "$ZIP" ]] || fail "v5.5.1 repository ZIP was not found."
REPO="$(find_repo || true)"
[[ -n "$REPO" ]] || fail "Could not locate or create the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."

say "$RELEASE_NAME"
printf 'Release ZIP: %s\nGit repository: %s\n' "$ZIP" "$REPO"
verify_checksum "$ZIP"
ensure_pytest

if [[ -n "$(git -C "$REPO" status --porcelain)" && "${SC_LIBRARY_ALLOW_DIRTY:-0}" != "1" ]]; then
  fail "Git working tree has local changes. Commit/stash them first, or rerun with SC_LIBRARY_ALLOW_DIRTY=1 if intentional."
fi

BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-$(date +%Y%m%d-%H%M%S).zip"
say "Creating safety backup"
(cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*' '*/.venv/*' '*/.pytest_cache/*' '*/__pycache__/*')
printf 'Safety backup: %s\n' "$BACKUP"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v551.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -1)"
[[ -f "$MAIN_FILE" ]] || fail "Could not locate plugin entry point in release ZIP."
SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"

say "Installing v5.5.1 repository payload"
rsync -a --exclude='.git/' --exclude='.DS_Store' --exclude='__MACOSX/' --exclude='.venv/' --exclude='.pytest_cache/' --exclude='__pycache__/' "$SOURCE/" "$REPO/"

say "Running v5.5.1 validation"
SC_LIBRARY_VALIDATION_PYTHON="$SC_LIBRARY_VALIDATION_PYTHON" bash "$REPO/tests/run_v551_validation.sh"

grep -Fq 'Version: 5.5.1' "$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "Plugin version marker is not v5.5.1."
grep -Fq "SC_LIBRARY_VERSION', '5.5.1'" "$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "SC_LIBRARY_VERSION is not v5.5.1."
grep -Fq "public const VERSION = '5.5.1'" "$REPO/sustainable-catalyst-library/includes/class-sc-library-python-backend.php" || fail "Python backend bridge is not v5.5.1."
grep -Fq '__version__ = "1.0.1"' "$REPO/library-backend/app/__init__.py" || fail "Python backend service is not v1.0.1."
grep -Fq '127.0.0.1:8087:8080' "$REPO/library-backend/compose.yml" || fail "Backend compose file is not localhost-bound."

if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then
  printf 'Repository already matches v%s; nothing to commit.\n' "$RELEASE_VERSION"
else
  say "Committing release"
  git -C "$REPO" add -A
  git -C "$REPO" commit -m "$RELEASE_NAME"
fi

[[ -z "$(git -C "$REPO" status --porcelain)" ]] || fail "Working tree is not clean after commit."
if git -C "$REPO" remote get-url origin >/dev/null 2>&1 && [[ "${SC_LIBRARY_SKIP_PUSH:-0}" != "1" ]]; then
  say "Pushing release"
  git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"
else
  printf '\nPush skipped or no origin remote is configured.\n'
fi

printf '\nPASS - Sustainable Catalyst Library v%s installed, validated, committed, and pushed.\n' "$RELEASE_VERSION"
