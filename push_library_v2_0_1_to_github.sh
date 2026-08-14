#!/usr/bin/env bash
set -euo pipefail

VERSION="2.0.1"
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
UNIFIED="$PLUGIN_DIR/includes/class-sc-library-unified-system.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
DEVELOPER="$PLUGIN_DIR/includes/class-sc-library-developer-api.php"
HARDENING="$PLUGIN_DIR/includes/class-sc-library-hardening.php"
CSS="$PLUGIN_DIR/assets/css/sc-library-unified-system.css"
JS="$PLUGIN_DIR/assets/js/sc-library-unified-system.js"
STATIC_SCHEMA="$SOURCE_DIR/docs/postgresql-schema.sql"
OPENAPI="$SOURCE_DIR/docs/openapi.json"
SYSTEM_SCHEMA="$SOURCE_DIR/docs/schemas/system-manifest.json"
EVENT_SCHEMA="$SOURCE_DIR/docs/schemas/system-event.json"
MANIFEST_EXAMPLE="$SOURCE_DIR/docs/portable-export-manifest.example.json"
DISCOVERY_CSS="$PLUGIN_DIR/assets/css/sc-library-discovery.css"
DISCOVERY_JS="$PLUGIN_DIR/assets/js/sc-library.js"
DISCOVERY_TEMPLATE="$PLUGIN_DIR/templates/library-app.php"
DISCOVERY_SCHEMA="$SOURCE_DIR/docs/schemas/discovery-interface.json"
DISCOVERY_SETUP="$SOURCE_DIR/UNIFIED_DISCOVERY_INTERFACE_REPAIR_SETUP_v2.0.1.md"
DISCOVERY_NOTES="$SOURCE_DIR/RELEASE_NOTES_2.0.1.md"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-unified-system.php" "$MAIN" "Unified-system bootstrap is missing."
validate_marker "new SC_Library_Unified_System" "$MAIN" "Unified-system initialization is missing."
validate_marker "sc-library-living-system/1.0" "$UNIFIED" "Living Knowledge System schema marker is missing."
validate_marker "sc-library-system-manifest/1.0" "$UNIFIED" "System manifest schema marker is missing."
validate_marker "sc-library-system-event/1.0" "$UNIFIED" "System event schema marker is missing."
validate_marker "sc_library_living_system" "$UNIFIED" "Unified public portal shortcode is missing."
validate_marker "sc_library_unified_workspace" "$UNIFIED" "Unified workspace shortcode is missing."
validate_marker "sc_library_system_status" "$UNIFIED" "System status shortcode is missing."
validate_marker "/library/system/status" "$UNIFIED" "Public system status route is missing."
validate_marker "/library/system/manifest/create" "$UNIFIED" "Administrative manifest route is missing."
validate_marker "sc_library_system_manifests" "$ACTIVATOR" "System manifest table is missing."
validate_marker "sc_library_system_events" "$ACTIVATOR" "System event table is missing."
validate_marker "sc-library-portable-export/3.0" "$PORTABILITY" "Portable export schema 3.0 is missing."
validate_marker "system_manifests" "$PORTABILITY" "Portable system manifests are missing."
validate_marker "system_events" "$PORTABILITY" "Portable system events are missing."
validate_marker "system.manifest.created" "$DEVELOPER" "System manifest webhook event is missing."
validate_marker "sc-library-unified-system" "$HARDENING" "Hardening integration is missing."
validate_marker "CREATE TABLE IF NOT EXISTS system_manifests" "$STATIC_SCHEMA" "Static system manifest schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS system_events" "$STATIC_SCHEMA" "Static system event schema is missing."
validate_marker '"/system"' "$OPENAPI" "OpenAPI system route is missing."
validate_marker '"sc-library-system-manifest/1.0"' "$SYSTEM_SCHEMA" "System manifest JSON Schema is missing."
validate_marker '"visibility"' "$EVENT_SCHEMA" "System event JSON Schema is missing."
validate_marker "Unified Discovery Interface Repair" "$SOURCE_DIR/RELEASE_NOTES_2.0.1.md" "Release notes marker is missing."
validate_marker "prefers-reduced-motion" "$CSS" "Reduced-motion handling is missing."
validate_marker "navigator.clipboard" "$JS" "Manifest copy behavior is missing."
validate_marker "sc-library-discovery/1.0" "$PLUGIN_DIR/includes/class-sc-library-rest.php" "Unified discovery schema is missing."
validate_marker "/library/discovery" "$PLUGIN_DIR/includes/class-sc-library-rest.php" "Unified discovery route is missing."
validate_marker "data-discovery-ui=\"2.0.1\"" "$DISCOVERY_TEMPLATE" "Discovery interface marker is missing."
validate_marker "data-topic-browser" "$DISCOVERY_TEMPLATE" "Topic browser is missing."
validate_marker "data-relationship-browser" "$DISCOVERY_TEMPLATE" "Relationship browser is missing."
validate_marker "data-pathway-browser" "$DISCOVERY_TEMPLATE" "Pathway browser is missing."
validate_marker "api('discovery')" "$DISCOVERY_JS" "Aggregate discovery loading is missing."
validate_marker "data-discovery-retry" "$DISCOVERY_JS" "Discovery retry behavior is missing."
validate_marker "container-type: inline-size" "$DISCOVERY_CSS" "Component-width discovery layout is missing."
validate_marker "minmax(min(100%, 210px), 1fr)" "$DISCOVERY_CSS" "Responsive pathway grid is missing."
validate_marker '"sc-library-discovery/1.0"' "$DISCOVERY_SCHEMA" "Discovery JSON Schema is missing."
validate_marker '"/discovery"' "$OPENAPI" "OpenAPI discovery route is missing."

for required in \
  "$UNIFIED" "$CSS" "$JS" \
  "$PLUGIN_DIR/templates/library-living-system.php" \
  "$PLUGIN_DIR/templates/library-unified-workspace.php" \
  "$SOURCE_DIR/tests/test_unified_system_release.py" \
  "$SOURCE_DIR/UNIFIED_DISCOVERY_INTERFACE_REPAIR_SETUP_v2.0.1.md" \
  "$SOURCE_DIR/RELEASE_NOTES_2.0.1.md" \
  "$SYSTEM_SCHEMA" "$EVENT_SCHEMA" "$DISCOVERY_SCHEMA" "$DISCOVERY_CSS" "$DISCOVERY_TEMPLATE" \
  "$DISCOVERY_SETUP" "$DISCOVERY_NOTES" "$MANIFEST_EXAMPLE" "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

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
[[ -n "$PYTHON_BIN" ]] || { echo "ERROR: Python 3.12 or newer is required for release validation."; exit 1; }

# Always create a release-specific environment before calling pytest.
echo "Creating isolated Library validation environment..."
TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v2010.XXXXXX")"
"$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
"$TEMP_VENV/venv/bin/python" -m pip install --disable-pip-version-check --upgrade pip pytest >/dev/null

 echo "Validating Library release tests..."
(cd "$SOURCE_DIR" && "$TEMP_VENV/venv/bin/python" -m pytest -q tests)
"$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py
"$TEMP_VENV/venv/bin/python" -m json.tool "$OPENAPI" >/dev/null
"$TEMP_VENV/venv/bin/python" -m json.tool "$SYSTEM_SCHEMA" >/dev/null
"$TEMP_VENV/venv/bin/python" -m json.tool "$EVENT_SCHEMA" >/dev/null
"$TEMP_VENV/venv/bin/python" -m json.tool "$DISCOVERY_SCHEMA" >/dev/null
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

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v2000-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
PACKAGED="$TEMP_EXTRACT/sustainable-catalyst-library"
[[ -f "$PACKAGED/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$PACKAGED/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."
validate_marker "sc-library-living-system/1.0" "$PACKAGED/includes/class-sc-library-unified-system.php" "Packaged unified system is missing."
[[ -f "$PACKAGED/assets/js/sc-library-unified-system.js" ]] || { echo "ERROR: Packaged unified JavaScript is missing."; exit 1; }
[[ -f "$PACKAGED/assets/css/sc-library-unified-system.css" ]] || { echo "ERROR: Packaged unified CSS is missing."; exit 1; }
[[ -f "$PACKAGED/templates/library-living-system.php" ]] || { echo "ERROR: Packaged Living Knowledge portal template is missing."; exit 1; }
[[ -f "$PACKAGED/assets/css/sc-library-discovery.css" ]] || { echo "ERROR: Packaged discovery CSS is missing."; exit 1; }
validate_marker "data-discovery-ui=\"2.0.1\"" "$PACKAGED/templates/library-app.php" "Packaged discovery interface marker is missing."
validate_marker "/library/discovery" "$PACKAGED/includes/class-sc-library-rest.php" "Packaged discovery route is missing."

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' --exclude-dir='.test-venv' --exclude-dir='.release-venv' \
  --exclude='.env.example' --exclude='*.zip' --exclude='pdf.min.js' --exclude='pdf.worker.min.js' --exclude='push_library_v2_0_1_to_github.sh' --exclude='install_and_push_library_v2_0_1.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,}|scl_live_[A-Za-z0-9_-]{20,}|whsec_[A-Za-z0-9_-]{20,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v2.0.1 validation passed."
  exit 0
fi

if ! git ls-remote "$REMOTE_SSH" >/dev/null 2>&1; then
  if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    echo "GitHub repository is missing. Creating it..."
    gh repo create "$REMOTE_SLUG" --public --description "Sustainable Catalyst Library unified living knowledge system" --disable-wiki
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
rsync -a --exclude .git --exclude '__pycache__' --exclude '.pytest_cache' --exclude '.venv' --exclude '.test-venv' --exclude '.release-venv' --exclude '.env' "$SOURCE_DIR/" "$TARGET_DIR/"

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v2.0.1 — Unified Discovery Interface Repair"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v2.0.1 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
