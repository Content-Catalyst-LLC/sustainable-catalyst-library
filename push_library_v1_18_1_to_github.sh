#!/usr/bin/env bash
set -euo pipefail

VERSION="1.18.1"
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
FOUNDATION="$PLUGIN_DIR/includes/class-sc-library-foundation-documents.php"
ACTIVATOR="$PLUGIN_DIR/includes/class-sc-library-activator.php"
INDEXER="$PLUGIN_DIR/includes/class-sc-library-indexer.php"
REST="$PLUGIN_DIR/includes/class-sc-library-rest.php"
ORCHESTRATOR="$PLUGIN_DIR/includes/class-sc-library-orchestrator.php"
DEV="$PLUGIN_DIR/includes/class-sc-library-developer-api.php"
PORTABILITY="$PLUGIN_DIR/includes/class-sc-library-portability.php"
STATIC_SCHEMA="$SOURCE_DIR/docs/postgresql-schema.sql"
OPENAPI="$SOURCE_DIR/docs/openapi.json"
FOUNDATION_SCHEMA="$SOURCE_DIR/docs/schemas/foundation-document.json"

validate_marker "Version: $VERSION" "$MAIN" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$MAIN" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "class-sc-library-foundation-documents.php" "$MAIN" "Foundation Document bootstrap is missing."
validate_marker "new SC_Library_Foundation_Documents" "$MAIN" "Foundation Document service initialization is missing."
validate_marker "sc-library-foundation-document/1.0" "$FOUNDATION" "Foundation Document schema marker is missing."
validate_marker "sc-library-pdf-extraction/1.0" "$FOUNDATION" "PDF extraction schema marker is missing."
validate_marker "public const POST_TYPE = 'sc_foundation_doc'" "$FOUNDATION" "Foundation Document post type is missing."
validate_marker "Select PDF from Media Library" "$FOUNDATION" "Media Library PDF selector is missing."
validate_marker "Extract and index full PDF text" "$FOUNDATION" "PDF extraction control is missing."
validate_marker "/extract/pages" "$FOUNDATION" "Page extraction route is missing."
validate_marker "Foundation PDF Migration" "$FOUNDATION" "Foundation PDF migration is missing."
validate_marker "application/x-bibtex" "$FOUNDATION" "BibTeX citation export is missing."
validate_marker "application/x-research-info-systems" "$FOUNDATION" "RIS citation export is missing."
validate_marker "sc_library_pdf_pages" "$ACTIVATOR" "PDF page table is missing."
validate_marker "sc_library_foundation_versions" "$ACTIVATOR" "Foundation version table is missing."
validate_marker "SC_Library_Foundation_Documents::extracted_text" "$INDEXER" "PDF full text is not connected to the indexer."
validate_marker "page_hits" "$REST" "Library page-aware result payload is missing."
validate_marker "pdf_page_hits" "$ORCHESTRATOR" "Research Librarian page synchronization is missing."
validate_marker "sc-library-portable-export/1.9" "$PORTABILITY" "Portable export schema 1.9 is missing."
validate_marker "CREATE TABLE IF NOT EXISTS foundation_documents" "$STATIC_SCHEMA" "Static Foundation Document schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS pdf_pages" "$STATIC_SCHEMA" "Static PDF page schema is missing."
validate_marker "CREATE TABLE IF NOT EXISTS foundation_versions" "$STATIC_SCHEMA" "Static version-history schema is missing."
validate_marker '"openapi": "3.1.0"' "$OPENAPI" "OpenAPI document is missing."
validate_marker '"/foundation-documents"' "$OPENAPI" "OpenAPI Foundation Document route is missing."
validate_marker '"sc-library-foundation-document/1.0"' "$FOUNDATION_SCHEMA" "Foundation JSON Schema is missing."
validate_marker "Embedded Document Records and Full-Text PDF Indexing" "$SOURCE_DIR/RELEASE_NOTES_1.18.1.md" "Release notes marker is missing."
validate_marker "browser with the bundled PDF.js" "$SOURCE_DIR/EMBEDDED_DOCUMENT_RECORDS_SETUP_v1.18.1.md" "PDF.js extraction guidance is missing."

for required in \
  "$FOUNDATION" \
  "$PLUGIN_DIR/assets/js/sc-library-foundation-admin.js" \
  "$PLUGIN_DIR/assets/js/sc-library-foundation-viewer.js" \
  "$PLUGIN_DIR/assets/css/sc-library-foundation-documents.css" \
  "$PLUGIN_DIR/assets/vendor/pdfjs/pdf.min.js" \
  "$PLUGIN_DIR/assets/vendor/pdfjs/pdf.worker.min.js" \
  "$PLUGIN_DIR/assets/vendor/pdfjs/LICENSE" \
  "$SOURCE_DIR/tests/test_foundation_documents_release.py" \
  "$SOURCE_DIR/EMBEDDED_DOCUMENT_RECORDS_SETUP_v1.18.1.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.18.1.md" \
  "$FOUNDATION_SCHEMA" \
  "$PLUGIN_ZIP"; do
  [[ -f "$required" ]] || { echo "ERROR: Required release file is missing: $required"; exit 1; }
done

if grep -RIn "<iframe" "$FOUNDATION" "$PLUGIN_DIR/assets/js/sc-library-foundation-viewer.js" "$PLUGIN_DIR/assets/css/sc-library-foundation-documents.css"; then
  echo "ERROR: Foundation Document reader must remain native and iframe-free."
  exit 1
fi

if grep -Fq "GROUP_CONCAT" "$FOUNDATION"; then
  echo "ERROR: PDF extraction must not truncate full text through GROUP_CONCAT."
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
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1181.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --disable-pip-version-check --upgrade pip pytest >/dev/null

  echo "Validating Library release tests..."
  (cd "$SOURCE_DIR" && "$TEMP_VENV/venv/bin/python" -m pytest -q tests)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py

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
  echo "WARNING: Python was not found; Python validation was skipped."
fi

python3 -m json.tool "$OPENAPI" >/dev/null 2>&1 || { echo "ERROR: OpenAPI JSON is invalid."; exit 1; }
python3 -m json.tool "$FOUNDATION_SCHEMA" >/dev/null 2>&1 || { echo "ERROR: Foundation Document JSON Schema is invalid."; exit 1; }

unzip -t "$PLUGIN_ZIP" >/dev/null
TEMP_EXTRACT="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1181-zip.XXXXXX")"
unzip -q "$PLUGIN_ZIP" -d "$TEMP_EXTRACT"
PACKAGED="$TEMP_EXTRACT/sustainable-catalyst-library"
[[ -f "$PACKAGED/sustainable-catalyst-library.php" ]] || { echo "ERROR: WordPress ZIP root structure is invalid."; exit 1; }
validate_marker "Version: $VERSION" "$PACKAGED/sustainable-catalyst-library.php" "Packaged plugin version is incorrect."
validate_marker "sc-library-foundation-document/1.0" "$PACKAGED/includes/class-sc-library-foundation-documents.php" "Packaged Foundation Document system is missing."
[[ -f "$PACKAGED/assets/vendor/pdfjs/pdf.min.js" ]] || { echo "ERROR: Packaged PDF.js library is missing."; exit 1; }

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' --exclude-dir='.test-venv' \
  --exclude='.env.example' --exclude='*.zip' --exclude='pdf.min.js' --exclude='pdf.worker.min.js' --exclude='push_library_v1_18_1_to_github.sh' --exclude='install_and_push_library_v1_18_1.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,}|scl_live_[A-Za-z0-9_-]{20,}|whsec_[A-Za-z0-9_-]{20,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.18.1 validation passed."
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
  git commit -m "Build Library v1.18.1 — Embedded Document Records and Full-Text PDF Indexing"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.18.1 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
