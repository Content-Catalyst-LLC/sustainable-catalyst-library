#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

python3 -m compileall -q library-backend/app

PYTHONPATH="$ROOT/library-backend" python3 -m pytest -q \
  library-backend/tests/test_institutional_sources_v120.py \
  tests/test_johns_hopkins_widget_registry_v571.py

# Re-run the v5.7.0 WordPress behavior tests except the superseded current-version assertion.
python3 -m pytest -q tests/test_institutional_research_sources_v570.py \
  -k 'not plugin_version_and_module_wiring'

# Preserve the pre-v5.7 behavioral gates while excluding historical assertions
# whose only purpose is pinning the former current release identity/backend number.
python3 -m pytest -q \
  tests/test_dynamic_library_explorer_v560.py \
  tests/test_homepage_research_network_console_v561.py \
  tests/test_publications_spotlight_context_v5611.py \
  -k 'not release_identity and not release_version'

for f in \
  sustainable-catalyst-library/sustainable-catalyst-library.php \
  sustainable-catalyst-library/includes/class-sc-library-institutional-research-sources.php \
  sustainable-catalyst-library/includes/class-sc-library-research-network-console.php \
  sustainable-catalyst-library/includes/class-sc-library-homepage-console.php; do
  php -l "$f" >/dev/null
done

node --check sustainable-catalyst-library/assets/js/sc-library-research-network-console-v560r3.js >/dev/null
node --check sustainable-catalyst-library/assets/js/sc-library-homepage-console-v561.js >/dev/null

printf 'PASS: Sustainable Catalyst Library v5.7.1 Johns Hopkins widget integration validation\n'
