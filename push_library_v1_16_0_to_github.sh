#!/usr/bin/env bash
set -euo pipefail

VERSION="1.16.0"
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
GRAPH="$PLUGIN_DIR/includes/class-sc-library-knowledge-graph.php"
ACTIVATOR="$PLUGIN_DIR/includes/class-sc-library-activator.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
RELATIONSHIPS="$PLUGIN_DIR/includes/class-sc-library-relationships.php"
EDITOR="$PLUGIN_DIR/includes/class-sc-library-editor.php"
REST="$PLUGIN_DIR/includes/class-sc-library-rest.php"
LIBRARY_JS="$PLUGIN_DIR/assets/js/sc-library.js"
GRAPH_JS="$PLUGIN_DIR/assets/js/sc-library-knowledge-graph.js"
GRAPH_CSS="$PLUGIN_DIR/assets/css/sc-library-knowledge-graph.css"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-knowledge-graph.php" "$MAIN" "Knowledge Graph bootstrap is missing."
validate_marker "new SC_Library_Knowledge_Graph" "$MAIN" "Knowledge Graph service initialization is missing."
validate_marker "sc-library-knowledge-graph/1.0" "$GRAPH" "Knowledge Graph schema marker is missing."
validate_marker "sc_library_graph_nodes" "$ACTIVATOR" "Graph node table is missing."
validate_marker "sc_library_graph_edges" "$ACTIVATOR" "Graph edge table is missing."
validate_marker "REBUILD_STATE_OPTION" "$GRAPH" "Resumable graph rebuild state is missing."
validate_marker "post_id > %d" "$GRAPH" "Cursor-based graph record traversal is missing."
validate_marker "/library/graph/rebuild/start" "$GRAPH" "Graph rebuild start route is missing."
validate_marker "/library/graph/rebuild/continue" "$GRAPH" "Graph rebuild continue route is missing."
validate_marker "/library/graph/rebuild/status" "$GRAPH" "Graph rebuild status route is missing."
validate_marker "source_kind NOT IN ('manual','board')" "$GRAPH" "Manual and board graph preservation is missing."
validate_marker "_sc_library_graph_source_claims" "$EDITOR" "Source-to-claim editor field is missing."
validate_marker "Explicit source-to-claim link" "$GRAPH" "Source-to-claim graph projection is missing."
validate_marker "confidence_basis" "$RELATIONSHIPS" "Relationship confidence basis is missing."
validate_marker "provenance_type" "$RELATIONSHIPS" "Relationship provenance is missing."
validate_marker "visibility" "$RELATIONSHIPS" "Relationship visibility is missing."
validate_marker "(\$relation['visibility'] ?? 'public') !== 'public'" "$REST" "Public relationship privacy filter is missing."
validate_marker "e.visibility = 'public'" "$GRAPH" "Public graph edge filter is missing."
validate_marker "n.visibility = 'public'" "$GRAPH" "Public graph node filter is missing."
validate_marker "sc_library_knowledge_graph" "$GRAPH" "Public graph shortcode is missing."
validate_marker "sc_library_relationship_intelligence" "$GRAPH" "Relationship intelligence shortcode is missing."
validate_marker "Promote to Knowledge Graph" "$PLUGIN_DIR/assets/js/sc-library-boards.js" "Whiteboard graph promotion is missing."
validate_marker "graphPageUrl" "$LIBRARY_JS" "Focused record graph links are missing from the Library."
validate_marker "View Relationship Graph" "$PLUGIN_DIR/includes/class-sc-library-shortcodes.php" "Graph action label is missing."
validate_marker "sc-library-portable-export/1.6" "$PORTABILITY" "Portable export schema 1.6 is missing."
validate_marker "graph_nodes" "$PORTABILITY" "Portable graph nodes are missing."
validate_marker "graph_edges" "$PORTABILITY" "Portable graph edges are missing."
validate_marker "Knowledge Graph and Relationship Intelligence" "$SOURCE_DIR/RELEASE_NOTES_1.16.0.md" "Release notes marker is missing."
validate_marker "Start resumable graph rebuild" "$SOURCE_DIR/KNOWLEDGE_GRAPH_SETUP.md" "Graph setup guide is incomplete."
validate_marker "sc_library_graph_settings" "$GRAPH" "Isolated graph settings group is missing."

# Retained capability checks.
validate_marker "sc-library-record sc-library-record--responsive" "$LIBRARY_JS" "Responsive public record cards were not retained."
validate_marker "sc-library-record__actions a" "$PLUGIN_DIR/assets/css/sc-library.css" "Responsive graph action-link styling is missing."
validate_marker "class-sc-library-collaboration.php" "$MAIN" "Editorial collaboration was not retained."
validate_marker "class-sc-library-multimedia.php" "$MAIN" "Multimedia Studio was not retained."
validate_marker "sc-library-workspace/1.8" "$PLUGIN_DIR/includes/class-sc-library-workspaces.php" "Workspace schema 1.8 was not retained."
validate_marker "sc-library-index-scan/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Large-Library scanner was not retained."
validate_marker "p.ID > %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Cursor index discovery was not retained."
validate_marker "RENDERER_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/documents.py" "Retained document renderer marker is incorrect."
validate_marker "MEDIA_PROCESSOR_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/media.py" "Retained media processor marker is incorrect."

for required in \
  "$GRAPH" \
  "$GRAPH_JS" \
  "$GRAPH_CSS" \
  "$SOURCE_DIR/tests/test_knowledge_graph_release.py" \
  "$SOURCE_DIR/KNOWLEDGE_GRAPH_SETUP.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.16.0.md" \
  "$SOURCE_DIR/docs/postgresql-schema.sql" \
  "$SOURCE_DIR/docs/portable-export-manifest.example.json" \
  "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

if grep -RIn "<iframe" "$GRAPH" "$GRAPH_JS" "$GRAPH_CSS"; then
  echo "ERROR: Knowledge Graph interfaces must remain native and iframe-free."
  exit 1
fi

if grep -Fq "posts_per_page' => -1" "$GRAPH"; then
  echo "ERROR: Knowledge Graph rebuild must not use an unbounded WordPress post query."
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
for candidate in python3.12 python3.13 python3 python; do
  if command -v "$candidate" >/dev/null 2>&1; then PYTHON_BIN="$(command -v "$candidate")"; break; fi
done
if [[ -n "$PYTHON_BIN" ]]; then
  echo "Validating release tests and optional Render service in an isolated Python environment..."
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1160.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --upgrade pip >/dev/null
  "$TEMP_VENV/venv/bin/python" -m pip install -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null
  (cd "$SOURCE_DIR" && PYTHONPATH=render-workspace-service "$TEMP_VENV/venv/bin/python" -m pytest -q tests render-workspace-service/tests)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py
else
  echo "WARNING: Python was not found; Python and Render tests were skipped."
fi

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1160-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
[[ -f "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$TEMP_EXTRACT/sustainable-catalyst-library/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' \
  --exclude='.env.example' --exclude='push_library_v1_16_0_to_github.sh' --exclude='install_and_push_library_v1_16_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.16.0 validation passed."
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
  git commit -m "Build Library v1.16.0 — Knowledge Graph and Relationship Intelligence"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.16.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
