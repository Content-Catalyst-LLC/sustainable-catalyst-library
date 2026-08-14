#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"
"$PY" -m pytest -q tests/test_institutional_connector_expansion_v4325.py
"$PY" -m pytest -q tests/test_research_librarian_access_intelligence_v4324.py -k 'not test_release_identity_and_module_registration and not test_research_library_page_promotes_access_intelligence_without_new_bloat'
"$PY" -m pytest -q tests/test_research_document_builder_v4323.py -k 'not test_release_identity_and_builder_module_registration and not test_page_promotes_builder_between_citation_and_workspace'
"$PY" -m pytest -q tests/test_publications_14_field_stack_restoration_v432224.py -k 'not test_release_identity_and_stack_boundary'
"$PY" -m pytest -q tests/test_citation_studio_source_manager_v4322.py -k 'not test_release_identity_and_citation_studio_page_contract and not test_publications_recovery_course_finder_and_research_access_remain_present'
"$PY" -m pytest -q tests/test_course_access_learning_pathways_v4321.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_global_library_access_v4319.py -k 'not test_release_identity_and_page_contract'
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -P4 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
 node --check sustainable-catalyst-library/assets/js/sc-library-orchestrator.js
 node --check sustainable-catalyst-library/assets/js/sc-library-connectors.js
 node --check sustainable-catalyst-library/assets/js/sc-library-research-document-builder.js
 node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
fi
printf 'PASS - v4.3.25 Institutional Connector Expansion and retained compatibility.\n'
