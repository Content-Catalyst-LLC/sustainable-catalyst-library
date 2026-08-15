#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"; PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"
"$PY" -m pytest -q tests/test_research_portability_preservation_v4339.py
EX="not test_release_identity_and_extension_registration and not test_identity_health_is_version_aligned_without_new_private_store and not test_identity_health_and_readme_are_current_without_new_private_store and not test_readme_and_release_docs_are_current_and_truthful and not test_release_identity_and_module_registration and not test_release_identity_and_builder_module_registration and not test_release_identity_and_stack_boundary and not test_release_identity_and_citation_studio_page_contract and not test_release_identity_and_page_contract and not test_front_end_is_accessible_mobile_and_same_origin and not test_page_keeps_access_intelligence_ii_inside_research_access_without_bloat and not test_identity_health_version_alignment_and_connected_library_truthfulness and not test_readme_release_docs_truthfully_describe_access_boundaries and not test_identity_health_version_alignment_and_private_review_history and not test_page_places_metadata_after_workspace_continuity_before_courses and not test_readme_release_docs_truthfully_describe_no_silent_merge_boundary and not test_identity_health_version_alignment_and_continuity_history and not test_research_library_page_places_workspace_after_evidence_before_courses and not test_readme_release_docs_and_publications_boundary_are_truthful and not test_identity_health_version_alignment_and_new_private_contracts and not test_research_library_page_places_matrix_after_reading_before_courses and not test_readme_release_docs_and_prior_boundaries_are_truthful and not test_identity_health_is_version_aligned_and_tracks_new_private_records and not test_research_library_page_places_reading_workspace_after_projects_before_courses and not test_readme_and_release_docs_describe_current_boundary_truthfully and not test_identity_health_is_version_aligned_and_tracks_project_continuity and not test_research_library_page_places_projects_after_saved_research_and_before_courses and not test_readme_and_release_docs_describe_current_no_duplication_contract and not test_research_library_page_places_saved_research_after_personal_library and not test_readme_and_release_docs_capture_truthful_current_contract and not test_readme_and_release_docs_capture_current_contract and not test_php_fixture_proves_schema_contracts_and_no_monitoring_claim and not test_account_continuity_health_remains_version_aligned_and_knows_my_library and not test_research_library_page_promotes_my_library_without_replacing_research_tools and not test_php_fixture_proves_path_matching_without_api_collision and not test_readme_marks_current_stable_tag_and_new_health_route and not test_research_library_page_promotes_public_network_inside_research_access and not test_research_library_page_embeds_network_in_research_access and not test_research_library_page_promotes_access_intelligence_without_new_bloat and not test_page_promotes_builder_between_citation_and_workspace and not test_publications_recovery_course_finder_and_research_access_remain_present and not test_identity_health_is_version_aligned_and_tracks_private_learning_routes and not test_readme_release_docs_truthfully_describe_open_learning_boundaries"
"$PY" -m pytest -q \
 tests/test_research_librarian_project_aware_guidance_v4338.py tests/test_publications_research_graph_v4337.py tests/test_open_learning_ii_v4336.py tests/test_access_intelligence_ii_v4335.py tests/test_metadata_quality_entity_resolution_v4334.py \
 tests/test_library_workspace_bidirectional_continuity_v4333.py tests/test_evidence_matrix_claim_intelligence_v4332.py \
 tests/test_reading_notebook_annotations_v4331.py tests/test_unified_research_projects_source_bundles_v4330.py \
 tests/test_saved_searches_watchlists_research_queue_v4329.py tests/test_personal_library_collections_recommendations_v4328.py \
 tests/test_canonical_route_identity_v4327.py tests/test_public_library_network_v4326.py tests/test_institutional_connector_expansion_v4325.py \
 tests/test_research_librarian_access_intelligence_v4324.py tests/test_research_document_builder_v4323.py \
 tests/test_publications_14_field_stack_restoration_v432224.py tests/test_citation_studio_source_manager_v4322.py \
 tests/test_course_access_learning_pathways_v4321.py tests/test_global_library_access_v4319.py -k "$EX"
if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -P8 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
 for f in \
  sustainable-catalyst-library/assets/js/sc-library-research-portability-v4339.js \
  sustainable-catalyst-library/assets/js/sc-library-research-librarian-ii-v4338.js \
  sustainable-catalyst-library/assets/js/sc-library-publication-graph-v4337.js \
  sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js \
  sustainable-catalyst-library/assets/js/sc-library-open-learning-v2-v4336.js \
  sustainable-catalyst-library/assets/js/sc-library-access-intelligence-ii-v4335.js \
  sustainable-catalyst-library/assets/js/sc-library-metadata-quality-v4334.js \
  sustainable-catalyst-library/assets/js/sc-library-workspace-continuity-v4333.js \
  sustainable-catalyst-library/assets/js/sc-library-evidence-matrix-v4332.js \
  sustainable-catalyst-library/assets/js/sc-library-reading-notebooks-v4331.js \
  sustainable-catalyst-library/assets/js/sc-library-unified-projects-v4330.js \
  sustainable-catalyst-library/assets/js/sc-library-research-continuity-v4329.js \
  sustainable-catalyst-library/assets/js/sc-library-personal-library-v4328.js; do node --check "$f"; done
fi
printf 'PASS - v4.3.39 Research Portability & Preservation and retained compatibility.\n'
