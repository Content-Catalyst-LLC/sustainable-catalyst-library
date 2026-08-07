#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

python3 -m pytest -q tests/test_field_spotlights_v4311.py
python3 -m pytest -q tests/test_field_spotlights_v4310.py -k 'not release_markers_and_cache_boundaries'
python3 -m pytest -q tests/test_field_spotlights_v439.py -k 'not release_markers_and_durable_settings_boundary'
python3 -m pytest -q tests/test_field_spotlights_v438.py -k 'not release_markers_and_cache_boundaries'
python3 -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'
python3 -m pytest -q tests/test_homepage_spotlight_two_tier_v420.py -k 'not v420_release_markers_and_cache_boundary'

if command -v php >/dev/null 2>&1; then
  find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights-admin.js
fi

echo 'PASS: v4.3.11 Field Spotlight save transaction repair.'
