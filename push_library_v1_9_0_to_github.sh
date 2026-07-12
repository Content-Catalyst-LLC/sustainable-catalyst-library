#!/usr/bin/env bash
set -euo pipefail

VERSION="1.9.0"
REMOTE_SSH="git@github.com:Content-Catalyst-LLC/sustainable-catalyst-library.git"
REMOTE_SLUG="Content-Catalyst-LLC/sustainable-catalyst-library"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$SCRIPT_DIR"
TARGET_DIR="$HOME/Downloads/sustainable-catalyst-library"

printf '\nSustainable Catalyst Library v%s\n' "$VERSION"
printf '%s\n\n' '===================================='

if [[ ! -f "$SOURCE_DIR/sustainable-catalyst-library/sustainable-catalyst-library.php" ]]; then
  echo "ERROR: Plugin source was not found beside this script."
  exit 1
fi

for command in git rsync; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "ERROR: $command is required."
    exit 1
  fi
done

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
rsync -a --exclude .git "$SOURCE_DIR/" "$TARGET_DIR/"

if ! grep -q "Version: $VERSION" sustainable-catalyst-library/sustainable-catalyst-library.php; then
  echo "ERROR: Plugin version marker validation failed."
  exit 1
fi
if ! grep -q "SC_LIBRARY_VERSION', '$VERSION'" sustainable-catalyst-library/sustainable-catalyst-library.php; then
  echo "ERROR: Runtime version marker validation failed."
  exit 1
fi
if ! grep -q "Content Planner" sustainable-catalyst-library/readme.txt; then
  echo "ERROR: Content Planner release marker is missing."
  exit 1
fi
if ! grep -q "sc_content_plan" sustainable-catalyst-library/includes/class-sc-library-planner.php; then
  echo "ERROR: Planned-content post type marker is missing."
  exit 1
fi
if ! grep -q "Article Map Planner" sustainable-catalyst-library/includes/class-sc-library-planner.php; then
  echo "ERROR: Article Map Planner marker is missing."
  exit 1
fi
if ! grep -q "roadmap/tracker" sustainable-catalyst-library/includes/class-sc-library-planner.php; then
  echo "ERROR: Roadmap tracker REST marker is missing."
  exit 1
fi
if ! grep -q "sc_library_registry" sustainable-catalyst-library/includes/class-sc-library-planner.php; then
  echo "ERROR: Registry shortcode marker is missing."
  exit 1
fi
if ! grep -q "sc-library-workspace/1.5" sustainable-catalyst-library/includes/class-sc-library-notebook.php; then
  echo "ERROR: Workspace compatibility marker is missing."
  exit 1
fi

for required in \
  sustainable-catalyst-library/includes/class-sc-library-planner.php \
  sustainable-catalyst-library/assets/js/sc-library-planner.js \
  sustainable-catalyst-library/assets/css/sc-library-planner.css \
  sustainable-catalyst-library/templates/library-registry.php \
  sustainable-catalyst-library/templates/library-roadmap-tracker.php \
  sustainable-catalyst-library-v1.9.0.zip \
  RELEASE_NOTES_1.9.0.md \
  CONTENT_PLANNER_SETUP.md; do
  if [[ ! -f "$required" ]]; then
    echo "ERROR: Required release file is missing: $required"
    exit 1
  fi
done

if grep -RInE --exclude-dir=.git --exclude='push_library_v1_9_0_to_github.sh' --exclude='install_and_push_library_v1_9_0.sh' \
  '((^|[^A-Za-z0-9])sk-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|password[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']+)' .; then
  echo "ERROR: Potential secret detected. Review the output above."
  exit 1
fi

git add -A
if git diff --cached --quiet; then
  echo "No changes to commit."
else
  git commit -m "Build Library v1.9.0 — Content Planner, Public Registry, and Roadmap Tracker"
fi

git push -u origin main

echo
echo "Sustainable Catalyst Library v1.9.0 pushed successfully."
echo "Repository: https://github.com/$REMOTE_SLUG"
