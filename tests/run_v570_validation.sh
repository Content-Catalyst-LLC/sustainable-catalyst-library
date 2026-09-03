#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
python3 -m compileall -q library-backend/app
PYTHONPATH="$ROOT/library-backend" python3 -m pytest -q library-backend/tests/test_institutional_sources_v120.py tests/test_institutional_research_sources_v570.py
if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
  php -l sustainable-catalyst-library/includes/class-sc-library-institutional-research-sources.php >/dev/null
fi
# Prior release suites contain identity assertions pinned to 5.6.1.1. Preserve
# all behavioral regressions while deselecting only those superseded identity tests.
python3 -m pytest -q \
  tests/test_dynamic_library_explorer_v560.py \
  tests/test_homepage_research_network_console_v561.py \
  tests/test_publications_spotlight_context_v5611.py \
  -k 'not release_identity and not release_version'
printf 'PASS: Sustainable Catalyst Library v5.7.0 institutional research sources validation\n'
