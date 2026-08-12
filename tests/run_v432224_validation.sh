#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PY="${SC_LIBRARY_VALIDATION_PYTHON:-${PYTHON:-python3}}"

# Release-specific contract.
"$PY" -m pytest -q tests/test_publications_14_field_stack_restoration_v432224.py

# Compact deployment regression gate. Full historical validation is available in
# tests/run_v432224_full_validation.sh and is run before release packaging.
"$PY" -m pytest -q \
  tests/test_publications_server_authoritative_v432223.py::test_core_publications_field_links_are_server_authoritative \
  tests/test_publications_server_authoritative_v432223.py::test_core_article_map_links_are_server_authoritative \
  tests/test_publications_server_authoritative_v432223.py::test_field_spotlight_direct_panel_tabs_are_server_authoritative \
  tests/test_publications_server_authoritative_v432223.py::test_registry_and_current_research_features_are_preserved \
  tests/test_publications_runtime_recovery_v43211.py::test_render_time_integrity_guard_recovers_single_field_or_panel_signature \
  tests/test_publications_runtime_recovery_v43211.py::test_panel_controls_have_server_side_fallback_routes_and_initial_selection \
  tests/test_publications_runtime_recovery_v43211.py::test_repair_remains_bounded_and_editorial_payloads_are_not_deleted \
  tests/test_publications_runtime_recovery_v43211.py::test_publications_and_course_features_are_preserved \
  tests/test_publications_integrity_recovery_v43181.py::test_canonical_registry_still_has_full_publications_model \
  tests/test_publications_integrity_recovery_v43181.py::test_repair_is_bounded_and_preserves_editorial_payloads \
  tests/test_publications_integrity_recovery_v43181.py::test_php_fixture_recovers_collapsed_state \
  tests/test_citation_studio_source_manager_v4322.py::test_citation_studio_is_isolated_extension_and_account_owned \
  tests/test_citation_studio_source_manager_v4322.py::test_multiple_citation_profiles_are_registered_without_removing_harvard \
  tests/test_citation_studio_source_manager_v4322.py::test_source_manager_supports_notes_collections_copy_and_exports \
  tests/test_citation_studio_source_manager_v4322.py::test_research_access_can_save_normalized_results_to_my_sources \
  tests/test_citation_studio_source_manager_v4322.py::test_source_normalization_and_duplicate_boundaries_are_present \
  tests/test_course_access_learning_pathways_v4321.py::test_course_intelligence_adds_pathways_learning_metadata_and_filters \
  tests/test_course_access_learning_pathways_v4321.py::test_ucph_course_connects_to_sustainable_development_and_systems_pathways \
  tests/test_course_access_learning_pathways_v4321.py::test_account_learning_plan_is_user_owned_and_bounded \
  tests/test_course_access_learning_pathways_v4321.py::test_public_course_discovery_remains_open_without_account \
  tests/test_course_access_learning_pathways_v4321.py::test_research_librarian_returns_course_recommendations_and_learn_intent \
  tests/test_field_spotlights_v4312.py::test_panel_editor_uses_dedicated_content_transaction_not_generic_settings_write \
  tests/test_field_spotlights_v4312.py::test_runtime_panel_save_persists_international_law_in_dedicated_store \
  tests/test_field_spotlights_v4312.py::test_settings_overlay_prefers_dedicated_panel_content_over_legacy_panel_content \
  tests/test_publications_v433.py::test_canonical_fourteen_field_170_map_registry_is_preserved \
  tests/test_publications_v433.py::test_article_map_hero_plus_four_article_contract_remains \
  tests/test_publications_v433.py::test_manual_curation_is_optional_and_resolver_cascade_remains \
  tests/test_publications_v433.py::test_homepage_spotlight_remains_isolated_at_v420

if command -v php >/dev/null 2>&1; then find sustainable-catalyst-library -type f -name '*.php' -print0 | xargs -0 -P4 -n1 php -l >/dev/null; fi
if command -v node >/dev/null 2>&1; then
  node --check sustainable-catalyst-library/assets/js/sc-library-publications.js
  node --check sustainable-catalyst-library/assets/js/sc-library-field-spotlights.js
  node --check sustainable-catalyst-library/assets/js/sc-library-citation-studio.js
  node --check sustainable-catalyst-library/assets/js/sc-library-connectors.js
  node --check sustainable-catalyst-library/assets/js/sc-library-open-course-finder.js
  node --check sustainable-catalyst-library/assets/js/sc-library-course-plan.js
fi
printf 'PASS - v4.3.22.4 Publications 14-Field Stack Restoration deployment validation complete\n'
