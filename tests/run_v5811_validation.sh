#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
python3 -m compileall -q library-backend/app
python3 -m pytest -q tests/test_release_console_runtime_sync_v5811.py
PYTHONPATH="$ROOT/library-backend" python3 -m pytest -q library-backend/tests/test_fda_regulatory_v140.py tests/test_fda_regulatory_intelligence_v581.py -k 'not release_identity'
PYTHONPATH="$ROOT/library-backend" python3 -m pytest -q library-backend/tests/test_biomedical_sources_v130.py tests/test_biomedical_evidence_v580.py -k 'not release_identity_and_backend'
python3 -m pytest -q tests/test_johns_hopkins_widget_registry_v571.py -k 'not release_identity_and_backend_preservation'
python3 -m pytest -q tests/test_institutional_research_sources_v570.py -k 'not plugin_version_and_module_wiring'
python3 -m pytest -q tests/test_dynamic_library_explorer_v560.py tests/test_homepage_research_network_console_v561.py tests/test_publications_spotlight_context_v5611.py -k 'not release_identity and not release_version'
if command -v php >/dev/null 2>&1; then
  php -l sustainable-catalyst-library/sustainable-catalyst-library.php >/dev/null
  php -l sustainable-catalyst-library/includes/class-sc-library-homepage-console.php >/dev/null
fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-homepage-console-v561.js >/dev/null
fi
printf 'PASS: Sustainable Catalyst Library v5.8.1.1 release console runtime synchronization validation\n'
