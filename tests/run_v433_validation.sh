#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
printf 'Sustainable Catalyst Library v4.3.3 validation\n\n'
grep -q 'Version: 4.3.3' sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q "SC_LIBRARY_VERSION', '4.3.3" sustainable-catalyst-library/sustainable-catalyst-library.php
grep -q 'Stable tag: 4.3.3' sustainable-catalyst-library/readme.txt
printf 'PASS: release version markers\n'
python3 -m pytest -q tests/test_publications_v433.py
python3 -m pytest -q tests/test_publications_v431.py -k 'full_registry_contract or nested_maps_and_required_routes_present'
python3 -m pytest -q tests/test_homepage_spotlight_two_tier_v420.py -k 'not release_markers_and_cache_boundary'
grep -q "public const VERSION = '4.2.0'" sustainable-catalyst-library/includes/class-sc-library-homepage-spotlight.php
grep -q 'sc_library_homepage_spotlight_pages_v420' sustainable-catalyst-library/includes/class-sc-library-homepage-spotlight.php
printf 'PASS: v4.2.0 Homepage Spotlight contract preserved\n'
if command -v node >/dev/null 2>&1; then node --check sustainable-catalyst-library/assets/js/sc-library-publications.js >/dev/null; printf 'PASS: Publications JavaScript syntax\n'; fi
find sustainable-catalyst-library -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
printf 'PASS: PHP syntax\n'
printf '\nPASS: v4.3.3 Dynamic Publications Spotlight validation completed.\n'
