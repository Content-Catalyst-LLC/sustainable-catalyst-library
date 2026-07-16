#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="$ROOT/sustainable-catalyst-library"

find "$PLUGIN" -type f -name '*.php' -print0 |
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done

if command -v node >/dev/null 2>&1; then
  find "$PLUGIN/assets/js" -type f -name '*.js' -print0 |
  while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null
  done
fi

echo "PASS: all shipped PHP and JavaScript files parse."
