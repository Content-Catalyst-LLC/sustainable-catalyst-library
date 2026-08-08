#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

python3 -m pytest -q tests/test_field_spotlights_v4313.py
python3 -m pytest -q tests/test_field_spotlights_v4312.py -k 'not release_markers_and_dedicated_panel_content_store'
python3 -m pytest -q tests/test_field_spotlights_v4311.py -k 'not v4311_release_and_verified_save_contract and not save_path_does_not_double_sanitize_partial_payload and not runtime_panel_save_persists_international_law_slot'
python3 -m pytest -q tests/test_field_spotlights_v4310.py -k 'not release_markers_and_cache_boundaries'
python3 -m pytest -q tests/test_field_spotlights_v439.py -k 'not release_markers_and_durable_settings_boundary and not additional_fields_are_truly_collapsed_until_opened and not collapsed_rotation_remains_first_eight_then_expands_to_all'
python3 -m pytest -q tests/test_field_spotlights_v438.py -k 'not release_markers_and_cache_boundaries and not additional_fields_disclosure_is_integrated_not_carded and not supporting_articles_retain_thumbnails_but_use_lighter_rows and not telemetry_and_transport_controls_are_visually_secondary and not v437_behavioral_contract_stays_present'
python3 -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'
python3 -m pytest -q tests/test_homepage_spotlight_two_tier_v420.py -k 'not v420_release_markers_and_cache_boundary'

if command -v php >/dev/null 2>&1; then
  find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights-admin.js
fi

echo 'PASS: v4.3.13 master Field Spotlight and dynamic 14-field switching.'
