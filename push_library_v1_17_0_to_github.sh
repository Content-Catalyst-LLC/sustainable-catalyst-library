#!/usr/bin/env bash
set -euo pipefail

VERSION="1.17.0"
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
ORCH="$PLUGIN_DIR/includes/class-sc-library-orchestrator.php"
ACTIVATOR="$PLUGIN_DIR/includes/class-sc-library-activator.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
INTEGRATIONS="$PLUGIN_DIR/includes/class-sc-library-integrations.php"
SHORTCODES="$PLUGIN_DIR/includes/class-sc-library-shortcodes.php"
ORCH_JS="$PLUGIN_DIR/assets/js/sc-library-orchestrator.js"
ORCH_CSS="$PLUGIN_DIR/assets/css/sc-library-orchestrator.css"
LIBRARY_JS="$PLUGIN_DIR/assets/js/sc-library.js"
STATIC_SCHEMA="$SOURCE_DIR/docs/postgresql-schema.sql"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-orchestrator.php" "$MAIN" "Orchestrator bootstrap is missing."
validate_marker "new SC_Library_Orchestrator" "$MAIN" "Orchestrator service initialization is missing."
validate_marker "sc-library-orchestration/1.0" "$ORCH" "Orchestration schema marker is missing."
validate_marker "sc-library-orchestration-action/1.0" "$ORCH" "Action schema marker is missing."
validate_marker "sc-library-orchestration-session/1.0" "$ORCH" "Session schema marker is missing."
validate_marker "sc_library_orchestration_sessions" "$ACTIVATOR" "Orchestration session table is missing."
validate_marker "sc_library_orchestration_events" "$ACTIVATOR" "Orchestration event table is missing."
validate_marker "/library/orchestrator/query" "$ORCH" "Orchestration query route is missing."
validate_marker "/library/orchestrator/sessions" "$ORCH" "Orchestration sessions route is missing."
validate_marker "/library/orchestrator/events" "$ORCH" "Orchestration events route is missing."
validate_marker "site_scoped_retrieval' => true" "$ORCH" "Site-scoped retrieval boundary is missing."
validate_marker "user_confirmation_required' => true" "$ORCH" "User-confirmation boundary is missing."
validate_marker "automatic_publication' => false" "$ORCH" "Automatic-publication boundary is missing."
validate_marker "remote_synthesis_can_modify_actions' => false" "$ORCH" "Remote synthesis action boundary is missing."
validate_marker "use_only_supplied_records" "$ORCH" "Remote synthesis source restriction is missing."
validate_marker "private static function public_targets" "$ORCH" "Public target privacy filter is missing."
validate_marker "window.confirm" "$ORCH_JS" "Browser action confirmation is missing."
validate_marker "action_applied" "$ORCH_JS" "Applied-action attribution is missing."
validate_marker "Ask Research Librarian" "$LIBRARY_JS" "Library record orchestration action is missing."
validate_marker "orchestratorPageUrl" "$SHORTCODES" "Orchestrator public page URL is missing."
validate_marker "'lab'" "$INTEGRATIONS" "Lab integration target is missing."
validate_marker "orchestration_packet" "$INTEGRATIONS" "Orchestration handoff packet is missing."
validate_marker "sc-library-portable-export/1.7" "$PORTABILITY" "Portable export schema 1.7 is missing."
validate_marker "orchestration_sessions" "$PORTABILITY" "Portable orchestration sessions are missing."
validate_marker "orchestration_events" "$PORTABILITY" "Portable orchestration events are missing."
validate_marker "CREATE TABLE IF NOT EXISTS orchestration_sessions" "$STATIC_SCHEMA" "Static PostgreSQL orchestration session schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS orchestration_events" "$STATIC_SCHEMA" "Static PostgreSQL orchestration event schema is missing."
validate_marker "Research Librarian Workspace Orchestration" "$SOURCE_DIR/RELEASE_NOTES_1.17.0.md" "Release notes marker is missing."
validate_marker "explicit confirmation" "$SOURCE_DIR/RESEARCH_LIBRARIAN_ORCHESTRATION_SETUP.md" "Setup guide confirmation boundary is missing."

# Retained capability checks.
validate_marker "class-sc-library-knowledge-graph.php" "$MAIN" "Knowledge Graph was not retained."
validate_marker "class-sc-library-collaboration.php" "$MAIN" "Editorial collaboration was not retained."
validate_marker "class-sc-library-multimedia.php" "$MAIN" "Multimedia Studio was not retained."
validate_marker "class-sc-library-scanner.php" "$MAIN" "Large-Library scanner was not retained."
validate_marker "sc-library-index-scan/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Large-Library scan schema was not retained."
validate_marker "p.ID > %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Cursor index discovery was not retained."
validate_marker "sc-library-knowledge-graph/1.0" "$PLUGIN_DIR/includes/class-sc-library-knowledge-graph.php" "Knowledge Graph schema was not retained."
validate_marker "sc-library-workspace/1.8" "$PLUGIN_DIR/includes/class-sc-library-workspaces.php" "Workspace schema 1.8 was not retained."
validate_marker "RENDERER_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/documents.py" "Retained document renderer marker is incorrect."
validate_marker "MEDIA_PROCESSOR_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/media.py" "Retained media processor marker is incorrect."

for required in \
  "$ORCH" \
  "$ORCH_JS" \
  "$ORCH_CSS" \
  "$PLUGIN_DIR/templates/library-orchestrator.php" \
  "$SOURCE_DIR/tests/test_orchestration_release.py" \
  "$SOURCE_DIR/RESEARCH_LIBRARIAN_ORCHESTRATION_SETUP.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.17.0.md" \
  "$STATIC_SCHEMA" \
  "$SOURCE_DIR/docs/portable-export-manifest.example.json" \
  "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

if grep -RIn "<iframe" "$ORCH" "$ORCH_JS" "$ORCH_CSS" "$PLUGIN_DIR/templates/library-orchestrator.php"; then
  echo "ERROR: Orchestration interfaces must remain native and iframe-free."
  exit 1
fi

if grep -Fq "posts_per_page' => -1" "$ORCH"; then
  echo "ERROR: Orchestration retrieval must not use an unbounded WordPress post query."
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
  echo "Validating release tests and optional Render service in an isolated Python environment..."
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1170.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --upgrade pip >/dev/null
  "$TEMP_VENV/venv/bin/python" -m pip install -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null
  (cd "$SOURCE_DIR" && PYTHONPATH=render-workspace-service "$TEMP_VENV/venv/bin/python" -m pytest -q tests render-workspace-service/tests)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py
else
  echo "WARNING: Python was not found; Python and Render tests were skipped."
fi

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1170-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
[[ -f "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."
validate_marker "sc-library-orchestration/1.0" "$TEMP_EXTRACT/sustainable-catalyst-library/includes/class-sc-library-orchestrator.php" "Packaged orchestrator is missing."

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' \
  --exclude='.env.example' --exclude='push_library_v1_17_0_to_github.sh' --exclude='install_and_push_library_v1_17_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.17.0 validation passed."
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
rsync -a --exclude .git --exclude '__pycache__' --exclude '.pytest_cache' --exclude '.venv' --exclude '.env' "$SOURCE_DIR/" "$TARGET_DIR/"

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v1.17.0 — Research Librarian Workspace Orchestration"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.17.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
