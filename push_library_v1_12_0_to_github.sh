#!/usr/bin/env bash
set -euo pipefail

VERSION="1.12.0"
REMOTE_SSH="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-library.git"
REMOTE_SLUG="Content-Catalyst-LLC/sustainable-catalyst-library"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$SCRIPT_DIR"
TARGET_DIR="$HOME/Downloads/sustainable-catalyst-library"
PLUGIN_DIR="$SOURCE_DIR/sustainable-catalyst-library"
SERVICE_DIR="$SOURCE_DIR/render-workspace-service"
PLUGIN_ZIP="$SOURCE_DIR/sustainable-catalyst-library-v1.12.0.zip"

printf '\nSustainable Catalyst Library v%s\n' "$VERSION"
printf '%s\n\n' '===================================='

for command in git rsync grep find; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "ERROR: $command is required."
    exit 1
  fi
done

if [[ ! -f "$PLUGIN_DIR/sustainable-catalyst-library.php" ]]; then
  echo "ERROR: Plugin source was not found beside this script."
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
rsync -a --exclude .git --exclude '__pycache__' --exclude '.pytest_cache' --exclude '.venv' --exclude '.env' "$SOURCE_DIR/" "$TARGET_DIR/"

validate_marker() {
  local pattern="$1" file="$2" message="$3"
  if ! grep -q "$pattern" "$file"; then
    echo "ERROR: $message"
    exit 1
  fi
}

validate_marker "Version: $VERSION" sustainable-catalyst-library/sustainable-catalyst-library.php "Plugin version marker validation failed."
validate_marker "SC_LIBRARY_VERSION', '$VERSION'" sustainable-catalyst-library/sustainable-catalyst-library.php "Runtime version marker validation failed."
validate_marker "Persistent Workspaces, Accounts, and Render Synchronization" README.md "Release marker is missing."
validate_marker "sc-library-workspace/1.7" sustainable-catalyst-library/includes/class-sc-library-workspaces.php "Workspace schema marker is missing."
validate_marker "sc-library-sync/1.0" sustainable-catalyst-library/includes/class-sc-library-workspaces.php "Sync schema marker is missing."
validate_marker "sc-library-portable-export/1.2" sustainable-catalyst-library/includes/class-sc-library-portability.php "Portable export schema marker is missing."
validate_marker "CREATE TABLE IF NOT EXISTS account_workspaces" sustainable-catalyst-library/includes/class-sc-library-portability.php "PostgreSQL account workspace table is missing."
validate_marker "library_account_workspaces" render-workspace-service/app/main.py "Render PostgreSQL workspace table is missing."
validate_marker "X-SC-Library-Timestamp" sustainable-catalyst-library/includes/class-sc-library-workspaces.php "Timestamped HMAC request protection is missing."

for required in \
  sustainable-catalyst-library/includes/class-sc-library-workspaces.php \
  sustainable-catalyst-library/assets/js/sc-library-workspace-sync.js \
  sustainable-catalyst-library/assets/css/sc-library-workspace-sync.css \
  sustainable-catalyst-library/templates/library-account-workspaces.php \
  render-workspace-service/app/main.py \
  render-workspace-service/app/core.py \
  render-workspace-service/render.yaml \
  render-workspace-service/requirements.txt \
  render-workspace-service/requirements-dev.txt \
  sustainable-catalyst-library-v1.12.0.zip \
  RELEASE_NOTES_1.12.0.md \
  WORKSPACE_SYNC_SETUP.md \
  PORTABLE_DATA_SETUP.md; do
  if [[ ! -f "$required" ]]; then
    echo "ERROR: Required release file is missing: $required"
    exit 1
  fi
done

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find sustainable-catalyst-library -name '*.php' -print0)
fi
if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do node --check "$file" >/dev/null; done < <(find sustainable-catalyst-library/assets/js -name '*.js' -print0)
fi
if command -v python >/dev/null 2>&1; then
  python -m py_compile render-workspace-service/app/*.py render-workspace-service/tests/*.py
  if python -c 'import pytest' >/dev/null 2>&1; then
    (cd render-workspace-service && PYTHONPATH=. python -m pytest -q)
  fi
fi
if command -v unzip >/dev/null 2>&1; then
  unzip -t sustainable-catalyst-library-v1.12.0.zip >/dev/null
fi

if grep -RInE --exclude-dir=.git --exclude-dir='__pycache__' --exclude-dir='.pytest_cache' \
  --exclude='.env.example' --exclude='push_library_v1_12_0_to_github.sh' --exclude='install_and_push_library_v1_12_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|DATABASE_URL=postgres(ql)?://[^<[:space:]]+|SC_LIBRARY_SYNC_API_KEY=[A-Za-z0-9_-]{16,})' .; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v1.12.0 — Persistent Workspaces, Accounts, and Render Synchronization"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.12.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
