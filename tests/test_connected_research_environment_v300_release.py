from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
ENVIRONMENT = PLUGIN / "includes" / "class-sc-library-connected-research-environment.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
CONNECTORS = PLUGIN / "includes" / "class-sc-library-scholarly-library-connectors.php"
HOLDINGS = PLUGIN / "includes" / "class-sc-library-connector-holdings-reliability.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
JS = PLUGIN / "assets" / "js" / "sc-library-connected-research.js"
CONNECTOR_JS = PLUGIN / "assets" / "js" / "sc-library-connectors.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-connected-research.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, ENVIRONMENT, MANAGER, CONNECTORS, HOLDINGS, EVIDENCE, JS, CONNECTOR_JS, CSS):
        assert path.is_file(), path


def test_environment_loads_after_retained_layers():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-connected-research-environment.php",
        "new SC_Library_Connected_Research_Environment",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-evidence-claim-linking.php") < text.index("class-sc-library-connected-research-environment.php")


def test_version_and_schemas():
    text = read(ENVIRONMENT)
    for marker in (
        "public const VERSION = '3.0.0'",
        "sc-library-connected-project/1.0",
        "sc-library-project-source-entry/1.0",
        "sc-library-project-bibliography/1.0",
        "sc-library-bibliography-snapshot/1.0",
        "sc-library-project-export/1.0",
    ):
        assert marker in text, marker
    assert "SC_LIBRARY_VERSION : '3.0.0'" in read(WRAPPER)


def test_project_brief_fields():
    text = read(ENVIRONMENT)
    for marker in (
        "META_RESEARCH_QUESTION",
        "META_OBJECTIVES",
        "META_METHODS",
        "META_SCOPE",
        "META_START_DATE",
        "META_TARGET_DATE",
        "Connected Project Brief",
        "Research question",
        "Research objectives",
        "Methods and research approach",
        "Scope, boundaries, and exclusions",
    ):
        assert marker in text, marker


def test_connected_record_fields():
    text = read(ENVIRONMENT)
    for marker in (
        "META_TEAM",
        "META_DOCUMENT_IDS",
        "META_SOURCE_ENTRIES",
        "META_SECTIONS",
        "META_SORT",
        "META_SNAPSHOTS",
        "META_ACTIVITY",
        "META_HEALTH",
    ):
        assert marker in text, marker


def test_source_entry_schema_and_roles():
    text = read(ENVIRONMENT)
    for marker in (
        "'source_id'",
        "'role'",
        "'section'",
        "'inclusion'",
        "'priority'",
        "'annotation'",
        "'added_at'",
        "'added_by'",
        "'updated_at'",
        "'updated_by'",
        "'primary'",
        "'background'",
        "'theory'",
        "'method'",
        "'data'",
        "'law-policy'",
        "'standard'",
        "'counterevidence'",
        "'case-study'",
    ):
        assert marker in text, marker


def test_inclusion_states_and_legacy_compatibility():
    text = read(ENVIRONMENT)
    for marker in (
        "'included'",
        "'candidate'",
        "'excluded'",
        "META_PROJECT_SOURCE_IDS",
        "META_PROJECT_IDS",
        "save_source_entries",
        "source_entries",
    ):
        assert marker in text, marker


def test_default_bibliography_sections():
    text = read(ENVIRONMENT)
    for marker in (
        "Core Sources",
        "Background and Context",
        "Methods and Data",
        "Law, Policy, and Standards",
        "Counterevidence and Alternative Views",
        "default_sections",
    ):
        assert marker in text, marker


def test_bibliography_sort_modes():
    text = read(ENVIRONMENT)
    for marker in (
        "'section-author'",
        "'author-year'",
        "'year-desc'",
        "'title'",
        "'priority'",
        "sort_entries",
        "source_author_sort",
    ):
        assert marker in text, marker


def test_team_roles_and_private_membership():
    text = read(ENVIRONMENT)
    for marker in (
        "'lead'",
        "'researcher'",
        "'librarian'",
        "'reviewer'",
        "'advisor'",
        "'observer'",
        "can_read_private_project",
        "team_entries",
        "get_current_user_id",
    ):
        assert marker in text, marker


def test_private_shortcode_requires_explicit_permission():
    text = read(ENVIRONMENT)
    assert "include_private" in text
    assert "rest_sanitize_boolean( $atts['include_private'] ) && self::can_read_private_project" in text
    assert "rest_sanitize_boolean( $atts['include_private'] ) && self::can_read_project" not in text


def test_project_workspace_tabs():
    text = read(ENVIRONMENT)
    for marker in (
        "Research Environment",
        "Connected Research Project and Bibliography Environment",
        "'overview'",
        "'sources'",
        "'bibliography'",
        "'evidence'",
        "'documents'",
        "'exports'",
        "render_workspace_tab",
    ):
        assert marker in text, marker


def test_project_health_model():
    text = read(ENVIRONMENT)
    for marker in (
        "workspace_health",
        "readiness_score",
        "included_sources",
        "candidate_sources",
        "excluded_sources",
        "verified_sources",
        "incomplete_sources",
        "duplicate_warnings",
        "accessible_sources",
        "evidence_notes",
        "documents",
    ):
        assert marker in text, marker


def test_project_health_is_bounded():
    text = read(ENVIRONMENT)
    assert "min( 100" in text
    assert "max( 1, count( $included ) )" in text
    assert "count( $included ) ? min( 100" in text


def test_bibliography_grouping():
    text = read(ENVIRONMENT)
    for marker in (
        "public static function bibliography",
        "'sections'",
        "'entries'",
        "'entry_count'",
        "'citation_style'",
        "'bibliography_title'",
        "'generated_at'",
    ):
        assert marker in text, marker


def test_multi_format_exports():
    text = read(ENVIRONMENT)
    for marker in (
        "'markdown'",
        "'text'",
        "'html'",
        "'bibtex'",
        "'ris'",
        "'csl-json'",
        "'json'",
        "source_to_bibtex",
        "source_to_ris",
        "source_to_csl",
        "application/x-bibtex",
        "application/x-research-info-systems",
        "application/vnd.citationstyles.csl+json",
    ):
        assert marker in text, marker


def test_bibliography_snapshots_are_bounded_and_hashed():
    text = read(ENVIRONMENT)
    for marker in (
        "MAX_SNAPSHOTS = 20",
        "create_snapshot",
        "delete_snapshot",
        "wp_generate_uuid4",
        "hash( 'sha256'",
        "array_slice( $snapshots, -self::MAX_SNAPSHOTS )",
    ):
        assert marker in text, marker


def test_activity_log_is_bounded():
    text = read(ENVIRONMENT)
    for marker in (
        "MAX_ACTIVITY = 200",
        "append_activity",
        "array_slice( $activity, -self::MAX_ACTIVITY )",
        "'project-updated'",
        "'source-attached'",
        "'bibliography-snapshot'",
    ):
        assert marker in text, marker


def test_source_discovery_project_handoff():
    connector = read(CONNECTORS)
    connector_js = read(CONNECTOR_JS)
    environment = read(ENVIRONMENT)
    for marker in (
        "'projectId'",
        "project_id: config.projectId || 0",
        "attach_source_to_project",
        "SC_Library_Connected_Research_Environment::attach_source_to_project",
    ):
        assert marker in connector or marker in connector_js or marker in environment, marker


def test_source_discovery_defaults_to_candidate():
    connector = read(CONNECTORS)
    assert "attach_source_to_project( $project_id, $source_id, 'candidate', 'background' )" in connector


def test_claim_evidence_and_document_connections():
    text = read(ENVIRONMENT)
    for marker in (
        "SC_Library_Evidence_Claim_Linking::claim_ids_for_project",
        "SC_Library_Evidence_Claim_Linking::evidence_ids_for_project",
        "SC_Library_Evidence_Claim_Linking::project_packet",
        "SC_Library_Evidence_Claim_Linking::project_packet_markdown",
        "SC_Library_Foundation_Pages::POST_TYPE",
        "related_document_ids",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(ENVIRONMENT)
    for marker in (
        "add_shortcode( 'sc_connected_research_project'",
        "add_shortcode( 'sc_project_bibliography_environment'",
        "shortcode_project_environment",
        "shortcode_bibliography_environment",
    ):
        assert marker in text, marker


def test_public_project_boundary():
    text = read(ENVIRONMENT)
    for marker in (
        "project_is_public",
        "'publish' === get_post_status",
        "'public' === get_post_meta",
        "can_read_project",
        "can_read_private_project",
    ):
        assert marker in text, marker


def test_rest_endpoints():
    text = read(ENVIRONMENT)
    for marker in (
        "'/projects/(?P<id>\\d+)/workspace'",
        "'/projects/(?P<id>\\d+)/bibliography-environment'",
        "'/projects/(?P<id>\\d+)/bibliography-snapshots'",
        "'/projects/(?P<id>\\d+)/export'",
        "'/projects/(?P<id>\\d+)/activity'",
        "rest_can_read_project",
        "rest_can_edit_project",
    ):
        assert marker in text, marker


def test_rest_update_validates_connected_ids():
    text = read(ENVIRONMENT)
    for marker in (
        "validated_ids",
        "SC_Library_Foundation_Pages::POST_TYPE",
        "sanitize_source_entries",
        "sanitize_team",
        "sanitize_sections",
    ):
        assert marker in text, marker


def test_dynamic_admin_client():
    text = read(JS)
    for marker in (
        "data-sc-add-source",
        "data-sc-remove-source",
        "data-sc-add-section",
        "data-sc-remove-section",
        "data-sc-add-team-member",
        "data-sc-remove-team-member",
        "renumber",
        "slugify",
    ):
        assert marker in text, marker


def test_snapshot_and_copy_client():
    text = read(JS)
    for marker in (
        "sc_library_v300_create_snapshot",
        "sc_library_v300_delete_snapshot",
        "data-sc-create-snapshot",
        "data-sc-delete-snapshot",
        "navigator.clipboard",
        "document.execCommand('copy')",
    ):
        assert marker in text, marker


def test_responsive_spartan_interface():
    text = read(CSS)
    for marker in (
        ".sc-connected-workspace",
        ".sc-project-source-row",
        ".sc-connected-metrics",
        ".sc-connected-bibliography",
        ".sc-public-research-environment",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_retained_subsystems_remain_loaded():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-document-ocr-processing.php",
    ):
        assert marker in wrapper, marker


def test_prior_component_versions_remain_stable():
    assert "public const VERSION = '2.6.1'" in read(CONNECTORS)
    assert "public const VERSION = '2.6.1'" in read(HOLDINGS)
    assert "public const VERSION = '2.7.0'" in read(EVIDENCE)


def main():
    tests = [
        test_required_files_exist,
        test_environment_loads_after_retained_layers,
        test_version_and_schemas,
        test_project_brief_fields,
        test_connected_record_fields,
        test_source_entry_schema_and_roles,
        test_inclusion_states_and_legacy_compatibility,
        test_default_bibliography_sections,
        test_bibliography_sort_modes,
        test_team_roles_and_private_membership,
        test_private_shortcode_requires_explicit_permission,
        test_project_workspace_tabs,
        test_project_health_model,
        test_project_health_is_bounded,
        test_bibliography_grouping,
        test_multi_format_exports,
        test_bibliography_snapshots_are_bounded_and_hashed,
        test_activity_log_is_bounded,
        test_source_discovery_project_handoff,
        test_source_discovery_defaults_to_candidate,
        test_claim_evidence_and_document_connections,
        test_shortcodes,
        test_public_project_boundary,
        test_rest_endpoints,
        test_rest_update_validates_connected_ids,
        test_dynamic_admin_client,
        test_snapshot_and_copy_client,
        test_responsive_spartan_interface,
        test_retained_subsystems_remain_loaded,
        test_prior_component_versions_remain_stable,
    ]
    for test in tests:
        test()
    print(f"Connected Research Project and Bibliography Environment checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
