#!/usr/bin/env bash
set -euo pipefail

VERSION="1.18.0"
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
DEV="$PLUGIN_DIR/includes/class-sc-library-developer-api.php"
ACTIVATOR="$PLUGIN_DIR/includes/class-sc-library-activator.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
DEV_JS="$PLUGIN_DIR/assets/js/sc-library-developer-api.js"
DEV_CSS="$PLUGIN_DIR/assets/css/sc-library-developer-api.css"
STATIC_SCHEMA="$SOURCE_DIR/docs/postgresql-schema.sql"
OPENAPI="$SOURCE_DIR/docs/openapi.json"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-developer-api.php" "$MAIN" "Developer API bootstrap is missing."
validate_marker "new SC_Library_Developer_API" "$MAIN" "Developer API service initialization is missing."
validate_marker "sc-library-developer-api/1.0" "$DEV" "Developer API schema marker is missing."
validate_marker "sc-library-webhook-event/1.0" "$DEV" "Webhook event schema marker is missing."
validate_marker "sustainable-catalyst-library/v1" "$DEV" "Versioned API namespace is missing."
validate_marker "/openapi.json" "$DEV" "OpenAPI route is missing."
validate_marker "/schemas" "$DEV" "JSON Schema routes are missing."
validate_marker "/media/reels" "$DEV" "Public multimedia route is missing."
validate_marker "/protected/export-manifest" "$DEV" "Protected export route is missing."
validate_marker "/protected/reindex" "$DEV" "Protected index route is missing."
validate_marker "/protected/webhooks/test" "$DEV" "Protected webhook test route is missing."
validate_marker "hash_hmac('sha256', \$plaintext, wp_salt('auth'))" "$DEV" "Hashed API-key storage is missing."
validate_marker "hash_equals" "$DEV" "Constant-time API-key verification is missing."
validate_marker "X-SC-Timestamp" "$DEV" "Webhook timestamp header is missing."
validate_marker "X-SC-Signature" "$DEV" "Webhook signature header is missing."
validate_marker "wp_safe_remote_post" "$DEV" "Safe webhook delivery is missing."
validate_marker "reject_unsafe_urls" "$DEV" "Unsafe webhook URL rejection is missing."
validate_marker "redirection' => 0" "$DEV" "Webhook redirect blocking is missing."
validate_marker "sc_library_api_keys" "$ACTIVATOR" "API-key table is missing."
validate_marker "sc_library_webhooks" "$ACTIVATOR" "Webhook table is missing."
validate_marker "sc_library_webhook_deliveries" "$ACTIVATOR" "Webhook-delivery table is missing."
validate_marker "sc-library-portable-export/1.8" "$PORTABILITY" "Portable export schema 1.8 is missing."
validate_marker "secret_exported' => false" "$PORTABILITY" "Portable secret exclusion marker is missing."
validate_marker "payload_exported' => false" "$PORTABILITY" "Portable webhook payload exclusion marker is missing."
validate_marker "CREATE TABLE IF NOT EXISTS api_keys" "$STATIC_SCHEMA" "Static PostgreSQL API-key schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS webhooks" "$STATIC_SCHEMA" "Static PostgreSQL webhook schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS webhook_deliveries" "$STATIC_SCHEMA" "Static PostgreSQL delivery schema is missing."
validate_marker '"openapi": "3.1.0"' "$OPENAPI" "Static OpenAPI document is invalid or missing."
validate_marker '"/media/reels"' "$OPENAPI" "Static OpenAPI multimedia route is missing."
validate_marker "Public API, Webhooks, and Developer Documentation" "$SOURCE_DIR/RELEASE_NOTES_1.18.0.md" "Release notes marker is missing."
validate_marker "plaintext key is shown once" "$SOURCE_DIR/DEVELOPER_API_SETUP.md" "API-key handling guidance is missing."

# Retained capability checks.
validate_marker "class-sc-library-orchestrator.php" "$MAIN" "Research Librarian orchestration was not retained."
validate_marker "class-sc-library-knowledge-graph.php" "$MAIN" "Knowledge Graph was not retained."
validate_marker "class-sc-library-collaboration.php" "$MAIN" "Editorial collaboration was not retained."
validate_marker "class-sc-library-multimedia.php" "$MAIN" "Multimedia Studio was not retained."
validate_marker "class-sc-library-scanner.php" "$MAIN" "Large-Library scanner was not retained."
validate_marker "sc-library-index-scan/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Large-Library scan schema was not retained."
validate_marker "p.ID > %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Cursor index discovery was not retained."
validate_marker "sc-library-knowledge-graph/1.0" "$PLUGIN_DIR/includes/class-sc-library-knowledge-graph.php" "Knowledge Graph schema was not retained."
validate_marker "sc-library-orchestration/1.0" "$PLUGIN_DIR/includes/class-sc-library-orchestrator.php" "Orchestration schema was not retained."
validate_marker "sc-library-workspace/1.8" "$PLUGIN_DIR/includes/class-sc-library-workspaces.php" "Workspace schema 1.8 was not retained."
validate_marker "RENDERER_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/documents.py" "Retained document renderer marker is incorrect."
validate_marker "MEDIA_PROCESSOR_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/media.py" "Retained media processor marker is incorrect."

for required in \
  "$DEV" \
  "$DEV_JS" \
  "$DEV_CSS" \
  "$PLUGIN_DIR/templates/library-developer-portal.php" \
  "$PLUGIN_DIR/templates/library-developer-admin.php" \
  "$SOURCE_DIR/tests/test_developer_api_release.py" \
  "$SOURCE_DIR/DEVELOPER_API_SETUP.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.18.0.md" \
  "$STATIC_SCHEMA" \
  "$OPENAPI" \
  "$SOURCE_DIR/docs/schemas/record.json" \
  "$SOURCE_DIR/docs/schemas/relationship.json" \
  "$SOURCE_DIR/docs/schemas/webhook-event.json" \
  "$SOURCE_DIR/docs/schemas/error.json" \
  "$SOURCE_DIR/docs/examples/javascript-client.mjs" \
  "$SOURCE_DIR/docs/examples/python_client.py" \
  "$SOURCE_DIR/docs/examples/verify-webhook.php" \
  "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

if grep -RIn "<iframe" "$DEV" "$DEV_JS" "$DEV_CSS" "$PLUGIN_DIR/templates/library-developer-portal.php" "$PLUGIN_DIR/templates/library-developer-admin.php"; then
  echo "ERROR: Developer interfaces must remain native and iframe-free."
  exit 1
fi

if grep -Fq "posts_per_page' => -1" "$DEV"; then
  echo "ERROR: Developer API collections must not use an unbounded WordPress post query."
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
for candidate in python3.12 python3.13 python3.14 python3 python; do
  if command -v "$candidate" >/dev/null 2>&1; then PYTHON_BIN="$(command -v "$candidate")"; break; fi
done
if [[ -n "$PYTHON_BIN" ]]; then
  echo "Validating Library release tests..."
  (cd "$SOURCE_DIR" && "$PYTHON_BIN" -m pytest -q tests)
  "$PYTHON_BIN" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py

  if [[ "${SC_LIBRARY_SKIP_RENDER_TESTS:-0}" != "1" ]]; then
    echo "Attempting optional Render service tests in an isolated environment..."
    TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1180.XXXXXX")"
    "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
    if "$TEMP_VENV/venv/bin/python" -m pip install --disable-pip-version-check --timeout 30 -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null 2>&1; then
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
  echo "WARNING: Python was not found; Python tests were skipped."
fi

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1180-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
[[ -f "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."
validate_marker "sc-library-developer-api/1.0" "$TEMP_EXTRACT/sustainable-catalyst-library/includes/class-sc-library-developer-api.php" "Packaged Developer API is missing."

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' --exclude-dir='.test-venv' \
  --exclude='.env.example' --exclude='push_library_v1_18_0_to_github.sh' --exclude='install_and_push_library_v1_18_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,}|scl_live_[A-Za-z0-9_-]{20,}|whsec_[A-Za-z0-9_-]{20,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.18.0 validation passed."
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
  git commit -m "Build Library v1.18.0 — Public API, Webhooks, and Developer Documentation"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.18.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
