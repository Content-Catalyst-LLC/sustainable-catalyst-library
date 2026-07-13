#!/usr/bin/env bash
set -euo pipefail

VERSION="1.14.1"
REMOTE_SSH="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-library.git"
REMOTE_SLUG="Content-Catalyst-LLC/sustainable-catalyst-library"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$SCRIPT_DIR"
TARGET_DIR="$HOME/Downloads/sustainable-catalyst-library"
PLUGIN_DIR="$SOURCE_DIR/sustainable-catalyst-library"
PLUGIN_ZIP="$SOURCE_DIR/sustainable-catalyst-library-v${VERSION}.zip"
TEMP_VENV=""

cleanup() {
  if [[ -n "$TEMP_VENV" && -d "$TEMP_VENV" ]]; then
    rm -rf "$TEMP_VENV"
  fi
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

validate_marker "Version: $VERSION" "$PLUGIN_DIR/sustainable-catalyst-library.php" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$PLUGIN_DIR/sustainable-catalyst-library.php" "Runtime version marker validation failed."
validate_marker "Stable tag: $VERSION" "$PLUGIN_DIR/readme.txt" "Stable tag validation failed."
validate_marker "sc-library-record sc-library-record--responsive" "$PLUGIN_DIR/assets/js/sc-library.js" "Responsive record-card renderer hook is missing."
validate_marker "sc-library-record__excerpt" "$PLUGIN_DIR/assets/js/sc-library.js" "Semantic excerpt hook is missing."
validate_marker "grid-template-columns:minmax(0,1fr);" "$PLUGIN_DIR/assets/css/sc-library.css" "Single-column public card grid is missing."
validate_marker "grid-template-areas:" "$PLUGIN_DIR/assets/css/sc-library.css" "Named public card grid areas are missing."
validate_marker "writing-mode: horizontal-tb !important" "$PLUGIN_DIR/assets/css/sc-library.css" "Horizontal record text safeguard is missing."
validate_marker "overflow-wrap: break-word !important" "$PLUGIN_DIR/assets/css/sc-library.css" "Record text wrapping safeguard is missing."
validate_marker "@media print" "$PLUGIN_DIR/assets/css/sc-library.css" "Print repair rules are missing."
validate_marker "display: none !important" "$PLUGIN_DIR/assets/css/sc-library.css" "Print action suppression is missing."
validate_marker "Public Record Card Layout and Responsive Rendering Repair" "$SOURCE_DIR/RELEASE_NOTES_1.14.1.md" "Release notes marker is missing."
validate_marker "No index rebuild required" "$SOURCE_DIR/PUBLIC_RECORD_LAYOUT_REPAIR_SETUP.md" "Setup guide is incomplete."

# Retained v1.14.0 capability checks.
validate_marker "class-sc-library-multimedia.php" "$PLUGIN_DIR/sustainable-catalyst-library.php" "Multimedia bootstrap is missing."
validate_marker "sc-library-media-asset/1.0" "$PLUGIN_DIR/includes/class-sc-library-multimedia.php" "Media asset schema is missing."
validate_marker "sc-library-portable-export/1.4" "$PLUGIN_DIR/includes/class-sc-library-portability.php" "Portable export schema 1.4 is missing."
validate_marker "sc-library-workspace/1.8" "$PLUGIN_DIR/includes/class-sc-library-workspaces.php" "Workspace schema 1.8 is missing."
validate_marker "MEDIA_PROCESSOR_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/media.py" "Media processor version is incorrect."
validate_marker "RENDERER_VERSION = \"1.14.1\"" "$SOURCE_DIR/render-workspace-service/app/documents.py" "Document renderer version is incorrect."
validate_marker "sc-library-index-scan/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Large-Library scanner was not retained."
validate_marker "p.ID > %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Cursor index discovery was not retained."

if grep -Fq ".sc-library-record{display:grid;grid-template-columns:minmax(0,1fr) auto;" "$PLUGIN_DIR/assets/css/sc-library.css"; then
  echo "ERROR: The collapsing two-column record-card grid is still present."
  exit 1
fi
if grep -Fq ".sc-library-record__actions{display:flex;align-items:center;gap:.65rem;white-space:nowrap}" "$PLUGIN_DIR/assets/css/sc-library.css"; then
  echo "ERROR: The non-wrapping action row is still present."
  exit 1
fi
if grep -Fq "shell=True" "$SOURCE_DIR/render-workspace-service/app/media.py"; then
  echo "ERROR: Media processing must not invoke a command shell."
  exit 1
fi

for required in \
  "$PLUGIN_DIR/assets/css/sc-library.css" \
  "$PLUGIN_DIR/assets/js/sc-library.js" \
  "$SOURCE_DIR/tests/test_public_record_layout_release.py" \
  "$SOURCE_DIR/tests/fixtures/public-record-layout.html" \
  "$SOURCE_DIR/PUBLIC_RECORD_LAYOUT_REPAIR_SETUP.md" \
  "$SOURCE_DIR/RELEASE_NOTES_1.14.1.md" \
  "$PLUGIN_ZIP"; do
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
for candidate in python3.12 python3 python; do
  if command -v "$candidate" >/dev/null 2>&1; then
    PYTHON_BIN="$(command -v "$candidate")"
    break
  fi
done
if [[ -n "$PYTHON_BIN" ]]; then
  echo "Validating release tests and optional Render service in an isolated Python environment..."
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1141.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --upgrade pip >/dev/null
  "$TEMP_VENV/venv/bin/python" -m pip install -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null
  (cd "$SOURCE_DIR" && PYTHONPATH=render-workspace-service "$TEMP_VENV/venv/bin/python" -m pytest -q tests render-workspace-service/tests)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py
fi

unzip -t "$PLUGIN_ZIP" >/dev/null

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' \
  --exclude='.env.example' --exclude='push_library_v1_14_1_to_github.sh' --exclude='install_and_push_library_v1_14_1.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if [[ "${SC_LIBRARY_VALIDATE_ONLY:-0}" == "1" ]]; then
  echo
  echo "Sustainable Catalyst Library v1.14.1 validation passed."
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

if git show-ref --verify --quiet refs/remotes/origin/main; then
  git checkout -B main origin/main
else
  git checkout -B main
fi

find "$TARGET_DIR" -mindepth 1 -maxdepth 1 -not -name .git -exec rm -rf {} +
rsync -a \
  --exclude .git \
  --exclude '__pycache__' \
  --exclude '.pytest_cache' \
  --exclude '.venv' \
  --exclude '.env' \
  "$SOURCE_DIR/" "$TARGET_DIR/"

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v1.14.1 — Public Record Card Layout and Responsive Rendering Repair"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.14.1 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
