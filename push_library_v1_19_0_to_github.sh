#!/usr/bin/env bash
set -euo pipefail

VERSION="1.19.0"
REMOTE_SSH="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-library.git"
REMOTE_SLUG="Content-Catalyst-LLC/sustainable-catalyst-library"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$SCRIPT_DIR"
TARGET_DIR="$HOME/Downloads/sustainable-catalyst-library"
PLUGIN_DIR="$SOURCE_DIR/sustainable-catalyst-library"
PLUGIN_ZIP="$SOURCE_DIR/sustainable-catalyst-library-v${VERSION}.zip"
TEMP_VENV=""
TEMP_EXTRACT=""

cleanup() {
  [[ -z "$TEMP_VENV" || ! -d "$TEMP_VENV" ]] || rm -rf "$TEMP_VENV"
  [[ -z "$TEMP_EXTRACT" || ! -d "$TEMP_EXTRACT" ]] || rm -rf "$TEMP_EXTRACT"
}
trap cleanup EXIT

printf '\nSustainable Catalyst Library v%s\n' "$VERSION"
printf '%s\n\n' '===================================='

for command in git rsync grep find unzip; do
  command -v "$command" >/dev/null 2>&1 || { echo "ERROR: $command is required."; exit 1; }
done

[[ -f "$PLUGIN_DIR/sustainable-catalyst-library.php" ]] || { echo "ERROR: Plugin source was not found beside this script."; exit 1; }
[[ -f "$PLUGIN_ZIP" ]] || { echo "ERROR: Installable WordPress ZIP is missing: $PLUGIN_ZIP"; exit 1; }

validate_marker() {
  local pattern="$1" file="$2" message="$3"
  grep -Fq "$pattern" "$file" || { echo "ERROR: $message"; exit 1; }
}

MAIN="$PLUGIN_DIR/sustainable-catalyst-library.php"
ACTIVATOR="$PLUGIN_DIR/includes/class-sc-library-activator.php"
PRESERVATION="$PLUGIN_DIR/includes/class-sc-library-preservation.php"
DEVELOPER="$PLUGIN_DIR/includes/class-sc-library-developer-api.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
STATIC_SCHEMA="$SOURCE_DIR/docs/postgresql-schema.sql"
OPENAPI="$SOURCE_DIR/docs/openapi.json"
PRESERVATION_SCHEMA="$SOURCE_DIR/docs/schemas/preservation-snapshot.json"
MANIFEST_EXAMPLE="$SOURCE_DIR/docs/portable-export-manifest.example.json"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-preservation.php" "$MAIN" "Preservation bootstrap is missing."
validate_marker "new SC_Library_Preservation" "$MAIN" "Preservation service initialization is missing."
validate_marker "sc-library-preservation/1.0" "$PRESERVATION" "Preservation schema marker is missing."
validate_marker "sc-library-preservation-manifest/1.0" "$PRESERVATION" "Preservation manifest schema is missing."
validate_marker "sc-library-integrity-audit/1.0" "$PRESERVATION" "Integrity audit schema is missing."
validate_marker "sc_library_preservation_snapshots" "$ACTIVATOR" "Preservation snapshot table is missing."
validate_marker "sc_library_integrity_checks" "$ACTIVATOR" "Integrity checks table is missing."
validate_marker "sc_library_authority_history" "$ACTIVATOR" "Authority history table is missing."
validate_marker "sc_library_institutional_archive" "$PRESERVATION" "Institutional Archive shortcode is missing."
validate_marker "sc_library_integrity_status" "$PRESERVATION" "Integrity status shortcode is missing."
validate_marker "wp_text_diff" "$PRESERVATION" "Version comparison is missing."
validate_marker "is_current = 0 AND legal_hold = 0" "$PRESERVATION" "Protected retention cleanup rule is missing."
validate_marker "wp_safe_remote_head" "$PRESERVATION" "Safe link validation is missing."
validate_marker "preservation.snapshot.created" "$DEVELOPER" "Preservation webhook event is missing."
validate_marker "integrity.audit.completed" "$DEVELOPER" "Integrity audit webhook event is missing."
validate_marker "sc-library-portable-export/2.0" "$PORTABILITY" "Portable export schema 2.0 is missing."
validate_marker "CREATE TABLE IF NOT EXISTS preservation_snapshots" "$STATIC_SCHEMA" "Static preservation snapshot schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS integrity_checks" "$STATIC_SCHEMA" "Static integrity schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS authority_history" "$STATIC_SCHEMA" "Static authority history schema is missing."
validate_marker '"openapi": "3.1.0"' "$OPENAPI" "OpenAPI document is missing."
validate_marker '"/archive"' "$OPENAPI" "OpenAPI archive route is missing."
validate_marker '"/archive/{uuid}/manifest"' "$OPENAPI" "OpenAPI manifest route is missing."
validate_marker '"sc-library-preservation/1.0"' "$PRESERVATION_SCHEMA" "Preservation JSON Schema is missing."
validate_marker "Preservation, Integrity, and Institutional Archive" "$SOURCE_DIR/RELEASE_NOTES_1.19.0.md" "Release notes marker is missing."
validate_marker "### Integrity audit" "$SOURCE_DIR/PRESERVATION_ARCHIVE_SETUP_v1.19.0.md" "Integrity-audit guidance is missing."

for required in \
  "$PRESERVATION" \
  "$PLUGIN_DIR/assets/js/sc-library-preservation.js" \
  "$PLUGIN_DIR/assets/css/sc-library-preservation.css" \
  "$SOURCE_DIR/tests/test_preservation_release.py" \
  "$SOURCE_DIR/PRESERVATION_ARCHIVE_SETUP_v1.19.0.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.19.0.md" \
  "$PRESERVATION_SCHEMA" \
  "$MANIFEST_EXAMPLE" \
  "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

if grep -RIn "<iframe" "$PRESERVATION" "$PLUGIN_DIR/assets/js/sc-library-preservation.js" "$PLUGIN_DIR/assets/css/sc-library-preservation.css"; then
  echo "ERROR: Institutional Archive must remain native and iframe-free."
  exit 1
fi

if grep -Fq "posts_per_page' => -1" "$PRESERVATION" || grep -Fq 'posts_per_page" => -1' "$PRESERVATION"; then
  echo "ERROR: Preservation auditing must remain bounded and resumable."
  exit 1
fi

if command -v php >/dev/null 2>&1; then
  echo "Validating PHP..."
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$PLUGIN_DIR" -name '*.php' -print0)
fi

if command -v node >/dev/null 2>&1; then
  echo "Validating JavaScript..."
  while IFS= read -r -d '' file; do node --check "$file" >/dev/null; done < <(find "$PLUGIN_DIR/assets/js" -name '*.js' -print0)
fi

PYTHON_BIN=""
for candidate in "/opt/homebrew/opt/python@3.12/bin/python3.12" python3.12 python3.13 python3.14 python3 python; do
  if [[ "$candidate" == /* && -x "$candidate" ]]; then PYTHON_BIN="$candidate"; break; fi
  if command -v "$candidate" >/dev/null 2>&1; then PYTHON_BIN="$(command -v "$candidate")"; break; fi
done

if [[ -n "$PYTHON_BIN" ]]; then
  echo "Creating isolated Library validation environment..."
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1190.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --disable-pip-version-check --upgrade pip pytest >/dev/null

  echo "Validating Library release tests..."
  (cd "$SOURCE_DIR" && "$TEMP_VENV/venv/bin/python" -m pytest -q tests)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py

  "$TEMP_VENV/venv/bin/python" -m json.tool "$OPENAPI" >/dev/null
  "$TEMP_VENV/venv/bin/python" -m json.tool "$PRESERVATION_SCHEMA" >/dev/null
  "$TEMP_VENV/venv/bin/python" -m json.tool "$MANIFEST_EXAMPLE" >/dev/null

  if [[ "${SC_LIBRARY_SKIP_RENDER_TESTS:-0}" != "1" ]]; then
    echo "Validating retained Render document and multimedia tests..."
    if "$TEMP_VENV/venv/bin/python" -m pip install --disable-pip-version-check --timeout 45 -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null 2>&1; then
      (cd "$SOURCE_DIR" && PYTHONPATH=render-workspace-service "$TEMP_VENV/venv/bin/python" -m pytest -q render-workspace-service/tests)
    elif [[ "${SC_LIBRARY_REQUIRE_RENDER_TESTS:-0}" == "1" ]]; then
      echo "ERROR: Render dependencies could not be installed."
      exit 1
    else
      echo "WARNING: Render dependencies could not be installed; retained Render tests were skipped."
    fi
  else
    echo "Render tests skipped by SC_LIBRARY_SKIP_RENDER_TESTS=1."
  fi
else
  echo "ERROR: Python was not found. Python 3.12 or newer is required for release validation."
  exit 1
fi

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1190-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
PACKAGED="$TEMP_EXTRACT/sustainable-catalyst-library"
[[ -f "$PACKAGED/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$PACKAGED/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."
validate_marker "sc-library-preservation/1.0" "$PACKAGED/includes/class-sc-library-preservation.php" "Packaged preservation system is missing."
[[ -f "$PACKAGED/assets/js/sc-library-preservation.js" ]] || { echo "ERROR: Packaged preservation JavaScript is missing."; exit 1; }
[[ -f "$PACKAGED/assets/css/sc-library-preservation.css" ]] || { echo "ERROR: Packaged preservation CSS is missing."; exit 1; }

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' --exclude-dir='.test-venv' \
  --exclude='.env.example' --exclude='*.zip' --exclude='pdf.min.js' --exclude='pdf.worker.min.js' --exclude='push_library_v1_19_0_to_github.sh' --exclude='install_and_push_library_v1_19_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,}|scl_live_[A-Za-z0-9_-]{20,}|whsec_[A-Za-z0-9_-]{20,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.19.0 validation passed."
  exit 0
fi

if ! git ls-remote "$REMOTE_SSH" >/dev/null 2>&1; then
  if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    echo "GitHub repository is missing. Creating it..."
    gh repo create "$REMOTE_SLUG" --public --description "Sustainable Catalyst Library WordPress knowledge system" --disable-wiki
  else
    echo "ERROR: The GitHub repository does not exist or is not accessible: $REMOTE_SLUG"
    exit 1
  fi
fi

if [[ -d "$TARGET_DIR/.git" ]]; then
  echo "Using existing local repository..."
  cd "$TARGET_DIR"
  git remote set-url origin "$REMOTE_SSH"
  git fetch origin --prune || true
else
  echo "Cloning repository..."
  rm -rf "$TARGET_DIR"
  git clone "$REMOTE_SSH" "$TARGET_DIR"
  cd "$TARGET_DIR"
fi

if git show-ref --verify --quiet refs/remotes/origin/main; then git checkout -B main origin/main; else git checkout -B main; fi
find "$TARGET_DIR" -mindepth 1 -maxdepth 1 -not -name .git -exec rm -rf {} +
rsync -a --exclude .git --exclude '__pycache__' --exclude '.pytest_cache' --exclude '.venv' --exclude '.test-venv' --exclude '.env' "$SOURCE_DIR/" "$TARGET_DIR/"

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v1.19.0 — Preservation, Integrity, and Institutional Archive"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.19.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
