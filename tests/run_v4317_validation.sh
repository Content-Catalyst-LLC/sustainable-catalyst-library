#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
python3 -m pytest -q tests/test_federated_research_access_v4317.py
python3 -m pytest -q tests/test_pathway_aware_research_guidance_v4316.py -k 'not release_identity_and_merged_page_contract'
python3 -m pytest -q tests/test_unified_search_guided_discovery_v4315.py -k 'not release_identity and not page_enables_bridge_only_where_intended'
python3 -m pytest -q tests/test_research_librarian_front_door_v4314.py -k 'not release_identity_and_readme_contract and not research_library_page_promotes_librarian_and_reorders_institutional_material'
python3 -m pytest -q tests/test_field_spotlights_v4313.py -k 'not release_markers_and_master_field_spotlight_contract'
python3 -m pytest -q tests/test_field_spotlights_v4312.py -k 'not release_markers_and_dedicated_panel_content_store'
python3 -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-connectors.js
  node --check sustainable-catalyst-library/assets/js/sc-library.js
  node --check sustainable-catalyst-library/assets/js/sc-library-orchestrator.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
fi
echo 'PASS: v4.3.17 Federated Library Access Foundation and retained compatibility.'
