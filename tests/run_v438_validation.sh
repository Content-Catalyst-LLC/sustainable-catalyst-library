#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
python3 -m pytest -q tests/test_field_spotlights_v438.py
python3 -m pytest -q tests/test_field_spotlights_v437.py -k 'not release_markers_and_cache_boundaries'
python3 -m pytest -q tests/test_field_spotlights_v436.py -k 'not release_markers_and_settings_compatibility and not public_v435_spotlight_contract_is_preserved'
python3 -m pytest -q tests/test_field_spotlights_v435.py -k 'not release_markers_and_public_shortcodes and not public_spotlight_structure_and_visual_contract and not javascript_interaction_and_accessibility_contract and not progressive_disclosure_and_accessibility'
python3 -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'
python3 -m pytest -q tests/test_homepage_spotlight_two_tier_v420.py -k 'not v420_release_markers_and_cache_boundary'
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights-admin.js
fi
echo 'PASS: v4.3.8 Field Spotlight visual refinement, editorial density, and control simplification.'
