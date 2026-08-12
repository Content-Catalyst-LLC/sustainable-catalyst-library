#!/usr/bin/env bash
set -euo pipefail

RELEASE_VERSION="4.3.23"
REPAIR_REVISION="r1"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION}-${REPAIR_REVISION} — Installer Validation Repair"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPOSITORY_BASENAME="sustainable-catalyst-library-v${RELEASE_VERSION}-repository-${REPAIR_REVISION}.zip"
CHECKSUM_BASENAME="SHA256SUMS-v${RELEASE_VERSION}-${REPAIR_REVISION}.txt"

say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

find_release_zip(){
  local candidate
  for candidate in \
    "$SCRIPT_DIR/$REPOSITORY_BASENAME" \
    "$DOWNLOADS/$REPOSITORY_BASENAME"; do
    [[ -f "$candidate" ]] && { printf '%s\n' "$candidate"; return 0; }
  done
  find "$DOWNLOADS" -maxdepth 2 -type f -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository-${REPAIR_REVISION}*.zip" -print 2>/dev/null | sort | tail -n 1
}

is_library_repo(){
  local path="$1"
  [[ -d "$path/.git" ]] || return 1
  [[ -f "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || return 1
  grep -q "Plugin Name: Sustainable Catalyst Library" "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" || return 1
}

find_repo(){
  local candidate remote
  if [[ -n "${SC_LIBRARY_REPO:-}" ]]; then
    is_library_repo "$SC_LIBRARY_REPO" || fail "SC_LIBRARY_REPO is not a Sustainable Catalyst Library Git repository: $SC_LIBRARY_REPO"
    printf '%s\n' "$SC_LIBRARY_REPO"; return 0
  fi
  for candidate in "$DOWNLOADS/sustainable-catalyst-library" "$DOWNLOADS/sustainable-catalyst-library-main" "$HOME/sustainable-catalyst-library" "$HOME/Documents/sustainable-catalyst-library"; do
    is_library_repo "$candidate" && { printf '%s\n' "$candidate"; return 0; }
  done
  while IFS= read -r candidate; do
    [[ -n "$candidate" ]] || continue
    if is_library_repo "$candidate"; then
      remote="$(git -C "$candidate" remote get-url origin 2>/dev/null || true)"
      [[ "$remote" == *"sustainable-catalyst-library"* ]] && { printf '%s\n' "$candidate"; return 0; }
    fi
  done < <(find "$DOWNLOADS" "$HOME/Documents" -maxdepth 3 -type d -name .git -print 2>/dev/null | sed 's#/.git$##')
  return 1
}

verify_checksum(){
  local zip="$1" manifest expected actual base
  base="$(basename "$zip")"
  for manifest in "$SCRIPT_DIR/$CHECKSUM_BASENAME" "$DOWNLOADS/$CHECKSUM_BASENAME"; do
    [[ -f "$manifest" ]] || continue
    expected="$(awk -v file="$base" '$2 == file {print $1}' "$manifest" | head -n 1)"
    [[ -n "$expected" ]] || continue
    actual="$(shasum -a 256 "$zip" | awk '{print $1}')"
    [[ "$actual" == "$expected" ]] || fail "Repository ZIP checksum does not match $manifest."
    printf 'Checksum verified: %s\n' "$actual"; return 0
  done
  fail "Repair checksum manifest $CHECKSUM_BASENAME was not found or did not contain $base."
}

ensure_pytest(){
  local candidate base_py venv
  say "Preparing isolated validation environment"
  for candidate in \
    "${SC_LIBRARY_VALIDATION_PYTHON:-}" \
    "$DOWNLOADS/.sc-library-v4323-r1-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v4323-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v43222-validation-venv/bin/python3" \
    "$(command -v python3 2>/dev/null || true)"; do
    [[ -n "$candidate" && -x "$candidate" ]] || continue
    if "$candidate" -m pytest --version >/dev/null 2>&1; then
      export SC_LIBRARY_VALIDATION_PYTHON="$candidate"
      printf 'pytest ready: %s\n' "$candidate"; return 0
    fi
  done
  base_py="$(command -v python3 2>/dev/null || true)"
  [[ -n "$base_py" ]] || fail "Python 3 is required for release validation."
  venv="$DOWNLOADS/.sc-library-v4323-r1-validation-venv"
  rm -rf "$venv"
  "$base_py" -m venv "$venv"
  "$venv/bin/python3" -m pip install -q --upgrade pip
  "$venv/bin/python3" -m pip install -q 'pytest>=8'
  "$venv/bin/python3" -m pytest --version >/dev/null 2>&1 || fail "Could not prepare pytest validation environment."
  export SC_LIBRARY_VALIDATION_PYTHON="$venv/bin/python3"
  printf 'pytest ready: %s\n' "$SC_LIBRARY_VALIDATION_PYTHON"
}

ZIP="$(find_release_zip)"
[[ -f "$ZIP" ]] || fail "v4.3.23-r1 repair repository ZIP was not found."
REPO="$(find_repo || true)"
[[ -n "$REPO" ]] || fail "Could not auto-detect the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."

say "$RELEASE_NAME"
printf 'Repair repository ZIP: %s\nGit repository: %s\n' "$ZIP" "$REPO"
verify_checksum "$ZIP"
ensure_pytest

if [[ -n "$(git -C "$REPO" status --porcelain)" ]]; then
  printf 'Detected an existing modified working tree. This is expected after the interrupted v4.3.23 installer; a safety backup will be created before repair.\n'
fi

BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-${REPAIR_REVISION}-$(date +%Y%m%d-%H%M%S).zip"
say "Creating safety backup"
(cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*' '*/.venv/*' '*/.pytest_cache/*' '*/__pycache__/*')
printf 'Safety backup: %s\n' "$BACKUP"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v4323-r1.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -n 1)"
[[ -f "$MAIN_FILE" ]] || fail "Could not locate plugin entry point after repair archive extraction."
SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"

say "Installing repaired v4.3.23 repository"
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.DS_Store' \
  --exclude='__MACOSX/' \
  --exclude='.venv/' \
  --exclude='.pytest_cache/' \
  --exclude='__pycache__/' \
  "$SOURCE/" "$REPO/"

say "Running v4.3.23-r1 validation"
SC_LIBRARY_VALIDATION_PYTHON="$SC_LIBRARY_VALIDATION_PYTHON" bash "$REPO/tests/run_v4323_r1_validation.sh"

MAIN="$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php"
TEST="$REPO/tests/test_research_document_builder_v4323.py"
FIXTURE="$REPO/tests/test_research_document_builder_fixture_v4323.php"
STACK="$REPO/sustainable-catalyst-library/templates/field-spotlights.php"
PAGE="$REPO/RESEARCH_LIBRARY_PAGE_v4.3.23.html"

grep -q 'Version: 4.3.23' "$MAIN" || fail "Product plugin version marker is no longer v4.3.23."
if grep -Fq 'payload["docx_bytes"] > 5000' "$TEST"; then
  fail "Environment-dependent DOCX byte threshold is still present."
fi
grep -Fq 'zipfile.is_zipfile(docx)' "$TEST" || fail "Structural DOCX ZIP validation is missing."
grep -Fq 'archive.testzip() is None' "$TEST" || fail "DOCX ZIP integrity validation is missing."
grep -Fq '"_rels/.rels"' "$TEST" || fail "Required OOXML relationship validation is missing."
grep -Fq "class_exists('ZipArchive') ? 'ZipArchive' : 'PharData'" "$FIXTURE" || fail "Cross-environment ZIP backend diagnostic is missing."
grep -q 'data-sc-field-stack="v4.3.22.4"' "$STACK" || fail "v4.3.22.4 Publications stack marker was not preserved."
grep -q 'data-sc-field-stack-mode="all-fields"' "$STACK" || fail "v4.3.22.4 all-fields Publications mode was not preserved."
[[ -f "$PAGE" ]] || fail "Research Library v4.3.23 page artifact is missing."

say "Preparing Git commit"
if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then
  printf 'Repository already matches the v%s-%s repair; nothing to commit.\n' "$RELEASE_VERSION" "$REPAIR_REVISION"
else
  git -C "$REPO" add -A
  git -C "$REPO" commit -m "$RELEASE_NAME"
fi

if git -C "$REPO" remote get-url origin >/dev/null 2>&1; then
  say "Pushing release"
  git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"
else
  printf '\nNo origin remote is configured. The repaired release is installed and committed locally.\n'
fi

say "Complete"
printf 'Installed v%s-%s installer repair. Product version remains v%s.\n' "$RELEASE_VERSION" "$REPAIR_REVISION" "$RELEASE_VERSION"
printf 'WordPress plugin ZIP remains: sustainable-catalyst-library-v%s.zip\n' "$RELEASE_VERSION"
printf 'Research Library page artifact remains: RESEARCH_LIBRARY_PAGE_v%s.html\n' "$RELEASE_VERSION"
printf 'The v4.3.22.4 Publications 14-field stack is preserved. Do not replace the Publications page body.\n'
printf 'The r1 repair changes installer/test validation only; no rollback is required.\n'
