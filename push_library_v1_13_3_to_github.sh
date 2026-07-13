#!/usr/bin/env bash
set -euo pipefail

VERSION="1.13.3"
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
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "ERROR: $command is required."
    exit 1
  fi
done

if [[ ! -f "$PLUGIN_DIR/sustainable-catalyst-library.php" ]]; then
  echo "ERROR: Plugin source was not found beside this script."
  exit 1
fi
if [[ ! -f "$PLUGIN_ZIP" ]]; then
  echo "ERROR: Installable WordPress ZIP is missing: $PLUGIN_ZIP"
  exit 1
fi

validate_marker() {
  local pattern="$1" file="$2" message="$3"
  if ! grep -Fq "$pattern" "$file"; then
    echo "ERROR: $message"
    exit 1
  fi
}

validate_marker "Version: $VERSION" "$PLUGIN_DIR/sustainable-catalyst-library.php" "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" "$PLUGIN_DIR/sustainable-catalyst-library.php" "Runtime version marker validation failed."
validate_marker "sc-library-index-scan/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Scanner-state schema v2.0 is missing."
validate_marker "sc-library-index-scan-log/2.0" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Scanner report schema v2.0 is missing."
validate_marker "p.ID > %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Cursor predicate is missing."
validate_marker "ORDER BY p.ID ASC LIMIT %d" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Bounded cursor query is missing."
validate_marker "sc_library_scan_items" "$PLUGIN_DIR/includes/class-sc-library-activator.php" "Scan audit table is missing."
validate_marker "accounting_ok" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Completion accounting is missing."
validate_marker "'step', 'pause', 'resume', 'cancel', 'reset'" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Scanner reset route is missing."
validate_marker "discoverable_post_types" "$PLUGIN_DIR/includes/class-sc-library-indexer.php" "Post-type discovery is missing."
validate_marker "Large-Library Index Discovery and Batch Reliability Patch" "$SOURCE_DIR/RELEASE_NOTES_1.13.3.md" "Release notes marker is missing."
validate_marker "SC Library → Index Scanner" "$SOURCE_DIR/INDEX_SCANNER_SETUP.md" "Scanner setup guide is incomplete."
validate_marker "menu'], 5" "$PLUGIN_DIR/includes/class-sc-library-admin.php" "SC Library parent menu priority repair is missing."
validate_marker "admin_menu'], 20" "$PLUGIN_DIR/includes/class-sc-library-scanner.php" "Index Scanner submenu priority repair is missing."
validate_marker "sc-library-document-job/1.0" "$PLUGIN_DIR/includes/class-sc-library-document-production.php" "Document production was not retained."
validate_marker "sc-library-portable-export/1.3" "$PLUGIN_DIR/includes/class-sc-library-portability.php" "Portable export schema marker is missing."
validate_marker "sc-library-workspace/1.7" "$PLUGIN_DIR/includes/class-sc-library-workspaces.php" "Workspace schema marker is missing."

if grep -Fq "new WP_Query" "$PLUGIN_DIR/includes/class-sc-library-scanner.php"; then
  echo "ERROR: The scanner must not use WP_Query for discovery."
  exit 1
fi
if grep -Eq "posts_per_page['\"]?[[:space:]]*=>[[:space:]]*-1" "$PLUGIN_DIR/includes/class-sc-library-scanner.php"; then
  echo "ERROR: An unbounded scanner query was detected."
  exit 1
fi
if grep -Fq "'queue'" "$PLUGIN_DIR/includes/class-sc-library-scanner.php"; then
  echo "ERROR: Scanner state must not store a full ID queue."
  exit 1
fi

for required in \
  "$PLUGIN_DIR/includes/class-sc-library-scanner.php" \
  "$PLUGIN_DIR/includes/class-sc-library-indexer.php" \
  "$PLUGIN_DIR/assets/js/sc-library-scanner.js" \
  "$PLUGIN_DIR/assets/css/sc-library-scanner.css" \
  "$PLUGIN_DIR/templates/library-index-scanner.php" \
  "$SOURCE_DIR/tests/test_large_library_scanner_release.py" \
  "$SOURCE_DIR/render-workspace-service/app/documents.py" \
  "$SOURCE_DIR/render-workspace-service/tests/test_documents.py" \
  "$SOURCE_DIR/RELEASE_NOTES_1.13.3.md" \
  "$SOURCE_DIR/INDEX_SCANNER_SETUP.md" \
  "$PLUGIN_ZIP"; do
  if [[ ! -f "$required" ]]; then
    echo "ERROR: Required release file is missing: $required"
    exit 1
  fi
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
  echo "Validating scanner and optional Render service in an isolated Python environment..."
  TEMP_VENV="$(mktemp -d "${TMPDIR:-/tmp}/sc-library-v1133.XXXXXX")"
  "$PYTHON_BIN" -m venv "$TEMP_VENV/venv"
  "$TEMP_VENV/venv/bin/python" -m pip install --upgrade pip >/dev/null
  "$TEMP_VENV/venv/bin/python" -m pip install -r "$SOURCE_DIR/render-workspace-service/requirements-dev.txt" >/dev/null
  "$TEMP_VENV/venv/bin/python" -m pytest -q "$SOURCE_DIR/tests/test_large_library_scanner_release.py"
  (cd "$SOURCE_DIR/render-workspace-service" && PYTHONPATH=. "$TEMP_VENV/venv/bin/python" -m pytest -q)
  "$TEMP_VENV/venv/bin/python" -m py_compile "$SOURCE_DIR"/render-workspace-service/app/*.py "$SOURCE_DIR"/render-workspace-service/tests/*.py
fi

unzip -t "$PLUGIN_ZIP" >/dev/null

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' --exclude-dir='.venv' \
  --exclude='.env.example' --exclude='push_library_v1_13_3_to_github.sh' --exclude='install_and_push_library_v1_13_3.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,})' "$SOURCE_DIR"; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

if ! git ls-remote "$REMOTE_SSH" >/dev/null 2>&1; then
  if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    echo "GitHub repository is missing. Creating it..."
    gh repo create "$REMOTE_SLUG" --public --description "Sustainable Catalyst Library WordPress knowledge base" --disable-wiki
  else
    echo "ERROR: The GitHub repository does not exist or is not accessible: $REMOTE_SLUG"
    echo "Create it first, or authenticate GitHub CLI with: gh auth login"
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
  git commit -m "Build Library v1.13.3 — Large-Library Index Discovery and Batch Reliability Patch"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.13.3 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
