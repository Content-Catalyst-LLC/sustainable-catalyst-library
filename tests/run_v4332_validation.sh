#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"
"$PY" -m pytest -q tests/test_evidence_matrix_claim_intelligence_v4332.py
"$PY" -m pytest -q tests/test_reading_notebook_annotations_v4331.py -k 'not test_release_identity_and_extension_registration and not test_identity_health_is_version_aligned_and_tracks_new_private_records and not test_research_library_page_places_reading_workspace_after_projects_before_courses and not test_readme_and_release_docs_describe_current_boundary_truthfully'
"$PY" -m pytest -q tests/test_unified_research_projects_source_bundles_v4330.py -k 'not test_release_identity_and_extension_registration and not test_identity_health_is_version_aligned_and_tracks_project_continuity and not test_research_library_page_places_projects_after_saved_research_and_before_courses and not test_readme_and_release_docs_describe_current_no_duplication_contract'
"$PY" -m pytest -q tests/test_saved_searches_watchlists_research_queue_v4329.py -k 'not test_release_identity_and_module_registration and not test_identity_health_is_version_aligned_and_tracks_new_private_records and not test_research_library_page_places_saved_research_after_personal_library and not test_readme_and_release_docs_capture_truthful_current_contract and not test_php_fixture_proves_schema_contracts_and_no_monitoring_claim'
"$PY" -m pytest -q tests/test_personal_library_collections_recommendations_v4328.py -k 'not test_release_identity_and_module_registration and not test_account_continuity_health_remains_version_aligned_and_knows_my_library and not test_research_library_page_promotes_my_library_without_replacing_research_tools and not test_readme_and_release_docs_capture_current_contract'
"$PY" -m pytest -q tests/test_canonical_route_identity_v4327.py -k 'not test_release_identity_and_module_registration and not test_php_fixture_proves_path_matching_without_api_collision and not test_readme_marks_current_stable_tag_and_new_health_route'
"$PY" -m pytest -q tests/test_public_library_network_v4326.py -k 'not test_release_identity_and_module_registration and not test_research_library_page_promotes_public_network_inside_research_access'
"$PY" -m pytest -q tests/test_institutional_connector_expansion_v4325.py -k 'not test_release_identity_and_module_registration and not test_research_library_page_embeds_network_in_research_access'
"$PY" -m pytest -q tests/test_research_librarian_access_intelligence_v4324.py -k 'not test_release_identity_and_module_registration and not test_research_library_page_promotes_access_intelligence_without_new_bloat'
"$PY" -m pytest -q tests/test_research_document_builder_v4323.py -k 'not test_release_identity_and_builder_module_registration and not test_page_promotes_builder_between_citation_and_workspace'
"$PY" -m pytest -q tests/test_publications_14_field_stack_restoration_v432224.py -k 'not test_release_identity_and_stack_boundary'
"$PY" -m pytest -q tests/test_citation_studio_source_manager_v4322.py -k 'not test_release_identity_and_citation_studio_page_contract and not test_publications_recovery_course_finder_and_research_access_remain_present'
"$PY" -m pytest -q tests/test_course_access_learning_pathways_v4321.py -k 'not test_release_identity_and_page_contract'
"$PY" -m pytest -q tests/test_global_library_access_v4319.py -k 'not test_release_identity_and_page_contract'
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -P4 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
 node --check sustainable-catalyst-library/assets/js/sc-library-evidence-matrix-v4332.js
 node --check sustainable-catalyst-library/assets/js/sc-library-reading-notebooks-v4331.js
 node --check sustainable-catalyst-library/assets/js/sc-library-unified-projects-v4330.js
 node --check sustainable-catalyst-library/assets/js/sc-library-research-continuity-v4329.js
 node --check sustainable-catalyst-library/assets/js/sc-library-personal-library-v4328.js
 node --check sustainable-catalyst-library/assets/js/sc-library-public-library-network.js
 node --check sustainable-catalyst-library/assets/js/sc-library-orchestrator.js
 node --check sustainable-catalyst-library/assets/js/sc-library-connectors.js
 node --check sustainable-catalyst-library/assets/js/sc-library-research-document-builder.js
 node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
 node --check sustainable-catalyst-library/assets/js/sc-library-open-course-finder.js
 node --check sustainable-catalyst-library/assets/js/sc-library-course-plan.js
fi
printf 'PASS - v4.3.32 Evidence Matrix & Claim Intelligence and retained compatibility.\n'
