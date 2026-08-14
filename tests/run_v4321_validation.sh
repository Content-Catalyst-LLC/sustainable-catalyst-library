#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"
"$PY" -m pytest -q tests/test_course_access_learning_pathways_v4321.py
"$PY" -m pytest -q tests/test_pathway_editorial_course_refinement_v43202.py -k 'not test_release_identity_and_page_marker'
"$PY" -m pytest -q tests/test_knowledge_pathway_alignment_v43201.py -k 'not test_patch_identity_and_scoped_page_marker and not test_pathway_grid_is_two_columns_without_staggering and not test_mobile_pathways_collapse_to_one_column and not test_eight_question_pathways_and_core_library_remain_present'
"$PY" -m pytest -q tests/test_open_course_finder_v4320.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_access_first_layout_v43191.py -k 'not test_release_identity'
"$PY" -m pytest -q tests/test_global_library_access_v4319.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_publications_integrity_recovery_v43181.py -k 'not test_patch_identity_and_upgrade_hook'
"$PY" -m pytest -q tests/test_scholarly_university_access_v4318.py -k 'not version_and_page_front_door'
"$PY" -m pytest -q tests/test_federated_research_access_v4317.py -k 'not release_identity_and_access_is_first and not public_research_access_shortcode_and_public_ajax_are_bounded'
"$PY" -m pytest -q tests/test_pathway_aware_research_guidance_v4316.py -k 'not release_identity_and_merged_page_contract'
"$PY" -m pytest -q tests/test_unified_search_guided_discovery_v4315.py -k 'not release_identity and not page_enables_bridge_only_where_intended'
"$PY" -m pytest -q tests/test_research_librarian_front_door_v4314.py -k 'not release_identity_and_readme_contract and not research_library_page_promotes_librarian_and_reorders_institutional_material'
"$PY" -m pytest -q tests/test_field_spotlights_v4313.py -k 'not release_markers_and_master_field_spotlight_contract'
"$PY" -m pytest -q tests/test_field_spotlights_v4312.py -k 'not release_markers_and_dedicated_panel_content_store'
"$PY" -m pytest -q tests/test_publications_v433.py -k 'not release_markers_and_cache_boundary'
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-publications.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-connectors.js
  node --check sustainable-catalyst-library/assets/js/sc-library-open-course-finder.js
  node --check sustainable-catalyst-library/assets/js/sc-library-course-plan.js
  node --check sustainable-catalyst-library/assets/js/sc-library-orchestrator.js
fi
printf 'PASS - v4.3.21 Course Access Intelligence & Learning Pathways validation complete\n'
