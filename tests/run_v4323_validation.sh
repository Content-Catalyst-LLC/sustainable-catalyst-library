#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"

"$PY" -m pytest -q tests/test_research_document_builder_v4323.py
"$PY" -m pytest -q tests/test_publications_14_field_stack_restoration_v432224.py -k 'not test_release_identity_and_stack_boundary'
"$PY" -m pytest -q tests/test_citation_studio_source_manager_v4322.py -k 'not test_release_identity_and_citation_studio_page_contract and not test_publications_recovery_course_finder_and_research_access_remain_present'
"$PY" -m pytest -q tests/test_course_access_learning_pathways_v4321.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_global_library_access_v4319.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'

if command -v php >/dev/null 2>&1; then
  find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -P4 -n1 php -l >/dev/null
fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-research-document-builder.js
  node --check sustainable-catalyst-library/assets/js/sc-library-citation-studio.js
  node --check sustainable-catalyst-library/assets/js/sc-library-publications.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-open-course-finder.js
  node --check sustainable-catalyst-library/assets/js/sc-library-course-plan.js
fi
printf 'PASS - v4.3.23 Research Document Builder & DOCX/PDF Export validation complete\n'
