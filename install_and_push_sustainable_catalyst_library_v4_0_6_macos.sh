#!/usr/bin/env bash
set -euo pipefail

RELEASE_VERSION="4.0.6"
RELEASE_NAME="Sustainable Catalyst Library v${RELEASE_VERSION} — Institutional Portal Compact Layout Repair"
DOWNLOADS="${HOME}/Downloads"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

say() { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

find_release_zip() {
  local candidate
  for candidate in \
    "$SCRIPT_DIR/sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip" \
    "$DOWNLOADS/sustainable-catalyst-library-v${RELEASE_VERSION}-repository.zip"; do
    [[ -f "$candidate" ]] && { printf '%s\n' "$candidate"; return 0; }
  done
  find "$DOWNLOADS" -maxdepth 1 -type f \
    -name "sustainable-catalyst-library-v${RELEASE_VERSION}-repository*.zip" \
    -print | sort | tail -n 1
}

is_library_repo() {
  local path="$1"
  [[ -d "$path/.git" ]] || return 1
  [[ -f "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || return 1
  grep -q "Plugin Name: Sustainable Catalyst Library" \
    "$path/sustainable-catalyst-library/sustainable-catalyst-library.php" || return 1
}

find_repo() {
  local candidate remote
  if [[ -n "${SC_LIBRARY_REPO:-}" ]]; then
    is_library_repo "$SC_LIBRARY_REPO" || fail "SC_LIBRARY_REPO is not a Sustainable Catalyst Library Git repository: $SC_LIBRARY_REPO"
    printf '%s\n' "$SC_LIBRARY_REPO"
    return 0
  fi

  for candidate in \
    "$DOWNLOADS/sustainable-catalyst-library" \
    "$DOWNLOADS/sustainable-catalyst-library-main" \
    "$HOME/sustainable-catalyst-library" \
    "$HOME/Documents/sustainable-catalyst-library"; do
    if is_library_repo "$candidate"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done

  while IFS= read -r candidate; do
    [[ -n "$candidate" ]] || continue
    if is_library_repo "$candidate"; then
      remote="$(git -C "$candidate" remote get-url origin 2>/dev/null || true)"
      if [[ "$remote" == *"Content-Catalyst-LLC/sustainable-catalyst-library"* ]] || [[ "$remote" == *"sustainable-catalyst-library"* ]]; then
        printf '%s\n' "$candidate"
        return 0
      fi
    fi
  done < <(find "$DOWNLOADS" "$HOME/Documents" -maxdepth 3 -type d -name .git -print 2>/dev/null | sed 's#/.git$##')

  return 1
}

ZIP="$(find_release_zip)"
[[ -n "$ZIP" && -f "$ZIP" ]] || fail "Release repository ZIP was not found in Downloads or beside this installer."
REPO="$(find_repo || true)"
[[ -n "$REPO" ]] || fail "Could not auto-detect the local Sustainable Catalyst Library Git repository. Set SC_LIBRARY_REPO=/full/path/to/repository and rerun."

say "$RELEASE_NAME"
printf 'Release ZIP: %s\n' "$ZIP"
printf 'Git repository: %s\n' "$REPO"
printf 'Remote: %s\n' "$(git -C "$REPO" remote get-url origin 2>/dev/null || echo '(none)')"
printf 'Branch: %s\n' "$(git -C "$REPO" branch --show-current 2>/dev/null || echo '(detached)')"

BACKUP="$DOWNLOADS/sustainable-catalyst-library-before-v${RELEASE_VERSION}-$(date +%Y%m%d-%H%M%S).zip"
say "Creating safety backup"
(
  cd "$(dirname "$REPO")"
  zip -qry "$BACKUP" "$(basename "$REPO")" -x '*/.git/*' '*/.DS_Store' '*/__MACOSX/*'
)
printf 'Safety backup: %s\n' "$BACKUP"

TMP="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v406.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
unzip -q "$ZIP" -d "$TMP"
MAIN_FILE="$(find "$TMP" -type f -path '*/sustainable-catalyst-library/sustainable-catalyst-library.php' -print | head -n 1)"
[[ -n "$MAIN_FILE" && -f "$MAIN_FILE" ]] || fail "Could not locate the plugin entry point after extraction."
SOURCE="$(dirname "$(dirname "$MAIN_FILE")")"
[[ -d "$SOURCE" ]] || fail "Could not locate the release repository root after extraction."

say "Installing release repository"
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.DS_Store' \
  --exclude='__MACOSX/' \
  "$SOURCE/" "$REPO/"

say "Running release contract and syntax checks"
grep -q 'Version: 4.0.6' "$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "Plugin header version does not match 4.0.6."
grep -q "SC_LIBRARY_VERSION', '4.0.6" "$REPO/sustainable-catalyst-library/sustainable-catalyst-library.php" || fail "Plugin constant does not match 4.0.6."
grep -q 'Stable tag: 4.0.6' "$REPO/sustainable-catalyst-library/readme.txt" || fail "WordPress stable tag does not match 4.0.6."

python3 "$REPO/tests/test_institutional_portal_fatal_containment_v405.py"
python3 "$REPO/tests/test_institutional_portal_compact_layout_v406.py"
bash "$REPO/tests/test_library_package_syntax_v405.sh"

say "Preparing Git commit"
if [[ -z "$(git -C "$REPO" status --porcelain)" ]]; then
  printf 'Repository already matches v%s; nothing to commit.\n' "$RELEASE_VERSION"
else
  git -C "$REPO" add -A
  git -C "$REPO" commit -m "Sustainable Catalyst Library v${RELEASE_VERSION} — Institutional Portal Compact Layout Repair"
fi

if git -C "$REPO" remote get-url origin >/dev/null 2>&1; then
  say "Pushing release"
  git -C "$REPO" push origin "$(git -C "$REPO" branch --show-current)"
else
  printf '\nNo origin remote is configured. The release is installed and committed locally.\n'
fi

say "Complete"
printf 'Installed %s\n' "$RELEASE_NAME"
printf 'WordPress plugin ZIP to upload separately: sustainable-catalyst-library-v%s.zip\n' "$RELEASE_VERSION"
printf 'Research Library shortcode: [sc_institutional_research_portal documents="12" units="0" compact="true" featured="6"]\n'
