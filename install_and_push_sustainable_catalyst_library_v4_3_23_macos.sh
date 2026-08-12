#!/usr/bin/env bash
set -euo pipefail

RELEASE_VERSION="4.3.23"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION} — Research Document Builder & DOCX/PDF Export"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

say(){ printf '\n==> %s\n' "$*"; }
fail(){ printf 'ERROR: %s\n' "$*" >&2; exit 1; }

find_release_zip(){
  local candidate
  for candidate in \
    "$SCRIPT_DIR/sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip" \
    "$DOWNLOADS/sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip"; do
    [[ -f "$candidate" ]] && { printf '%s\n' "$candidate"; return 0; }
  done
  find "$DOWNLOADS" -maxdepth 2 -type f -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository*.zip" -print 2>/dev/null | sort | tail -n 1
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
  for manifest in "$SCRIPT_DIR/SHA256SUMS-v${RELEASE_VERSION}.txt" "$DOWNLOADS/SHA256SUMS-v${RELEASE_VERSION}.txt"; do
    [[ -f "$manifest" ]] || continue
    expected="$(awk -v file="$base" '$2 == file {print $1}' "$manifest" | head -n 1)"
    [[ -n "$expected" ]] || continue
    actual="$(shasum -a 256 "$zip" | awk '{print $1}')"
    [[ "$actual" == "$expected" ]] || fail "Repository ZIP checksum does not match $manifest."
    printf 'Checksum verified: %s\n' "$actual"; return 0
  done
  printf 'Checksum manifest not found; continuing with release-contract validation.\n'
}

ensure_pytest(){
  local candidate base_py venv
  say "Preparing isolated validation environment"
  for candidate in \
    "${SC_LIBRARY_VALIDATION_PYTHON:-}" \
    "$DOWNLOADS/.sc-library-v4323-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v432224-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v432223-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v43222-validation-venv/bin/python3" \
    "$DOWNLOADS/.sc-library-v43221-validation-venv/bin/python3" \
    "$(command -v python3 2>/dev/null || true)"; do
    [[ -n "$candidate" && -x "$candidate" ]] || continue
    if "$candidate" -m pytest --version >/dev/null 2>&1; then
      export SC_LIBRARY_VALIDATION_PYTHON="$candidate"
      printf 'pytest ready: %s\n' "$candidate"; return 0
    fi
  done
  base_py="$(command -v python3 2>/dev/null || true)"
  [[ -n "$base_py" ]] || fail "Python 3 is required for release validation."
  venv="$DOWNLOADS/.sc-library-v4323-validation-venv"
  rm -rf "$venv"
  "$base_py" -m venv "$venv"
  "$venv/bin/python3" -m pip install -q --upgrade pip
  "$venv/bin/python3" -m pip install -q 'pytest>=8'
  "$venv/bin/python3" -m pytest --version >/dev/null 2>&1 || fail "Could not prepare pytest validation environment."
  export SC_LIBRARY_VALIDATION_PYTHON="$venv/bin/python3"
  printf 'pytest ready: %s\n' "$SC_LIBRARY_VALIDATION_PYTHON"
}

ZIP="$(find_release_zip)"
[[ -f "$ZIP" ]] || fail "Release repository ZIP was not found."
REPO="$(find_repo || true)"
[[ -n "$REPO" ]] || fail "Could not auto-detect the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."

say "$RELEASE_NAME"
printf 'Release ZIP: %s\nGit repository: %s\n' "$ZIP" "$REPO"
verify_checksum "$ZIP"
ensure_pytest

BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-$(date +%Y%m%d-%H%M%S).zip"
say "Creating safety backup"
(cd "$(dirname "$REPO")" && zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*' '*/.venv/*' '*/.pytest_cache/*' '*/__pycache__/*')
printf 'Safety backup: %s\n' "$BACKUP"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v4323.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -n 1)"
[[ -f "$MAIN_FILE" ]] || fail "Could not locate plugin entry point after extraction."
SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"

say "Installing release repository"
rsync -a --delete --exclude='.git/' --exclude='.DS_Store' --exclude='__MACOSX/' --exclude='.venv/' --exclude='.pytest_cache/' --exclude='__pycache__/' "$SOURCE/" "$REPO/"

say "Running v4.3.23 validation"
SC_LIBRARY_VALIDATION_PYTHON="$SC_LIBRARY_VALIDATION_PYTHON" bash "$REPO/tests/run_v4323_validation.sh"

MAIN="$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php"
BUILDER="$REPO/sustainable-catalyst-library/includes/class-sc-library-research-document-builder.php"
BOOTSTRAP="$REPO/sustainable-catalyst-library/includes/class-sc-library-extension-bootstrap-v402.php"
CITATION="$REPO/sustainable-catalyst-library/includes/class-sc-library-citation-studio.php"
STACK="$REPO/sustainable-catalyst-library/templates/field-spotlights.php"
PAGE="$REPO/RESEARCH_LIBRARY_PAGE_v4.3.23.html"

grep -q 'Version: 4.3.23' "$MAIN" || fail "Installed plugin version marker is not v4.3.23."
grep -Eq "define\([[:space:]]*'SC_LIBRARY_VERSION',[[:space:]]*'4\.3\.23'[[:space:]]*\);" "$MAIN" || fail "SC_LIBRARY_VERSION is not v4.3.23."
grep -q "public const VERSION = '4.3.23'" "$BUILDER" || fail "Research Document Builder version marker is missing."
grep -q "add_shortcode( 'sc_research_document_builder'" "$BUILDER" || fail "Research Document Builder shortcode is not registered."
grep -q "class-sc-library-research-document-builder.php" "$BOOTSTRAP" || fail "Research Document Builder bootstrap module is missing."
grep -q 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' "$BUILDER" || fail "DOCX MIME contract is missing."
grep -q "application/pdf" "$BUILDER" || fail "PDF MIME contract is missing."
grep -q 'data-sc-add-source-to-document' "$CITATION" || fail "Citation Studio Add to Document handoff is missing."
[[ -f "$PAGE" ]] || fail "Research Library v4.3.23 page artifact is missing."
grep -q '\[sc_research_document_builder limit="100" style="harvard"\]' "$PAGE" || fail "Research Library page does not include Research Document Builder."
grep -q 'data-sc-field-stack="v4.3.22.4"' "$STACK" || fail "v4.3.22.4 Publications stack marker was not preserved."
grep -q 'data-sc-field-stack-mode="all-fields"' "$STACK" || fail "v4.3.22.4 all-fields Publications mode was not preserved."

say "Preparing Git commit"
if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then
  printf 'Repository already matches v%s; nothing to commit.\n' "$RELEASE_VERSION"
else
  git -C "$REPO" add -A
  git -C "$REPO" commit -m "$RELEASE_NAME"
fi

if git -C "$REPO" remote get-url origin >/dev/null 2>&1; then
  say "Pushing release"
  git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"
else
  printf '\nNo origin remote is configured. The release is installed and committed locally.\n'
fi

say "Complete"
printf 'Installed %s\n' "$RELEASE_NAME"
printf 'Plugin ZIP for WordPress upload: sustainable-catalyst-library-v%s.zip\n' "$RELEASE_VERSION"
printf 'Research Library page replacement: RESEARCH_LIBRARY_PAGE_v%s.html\n' "$RELEASE_VERSION"
printf 'The v4.3.22.4 Publications 14-field stack is preserved; do not replace the Publications page body for this release.\n'
printf 'After plugin deployment, replace the Research Library page body with the v4.3.23 artifact and clear caches.\n'
