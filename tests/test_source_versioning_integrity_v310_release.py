from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
ENVIRONMENT = PLUGIN / "includes" / "class-sc-library-connected-research-environment.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-connected-research-reliability.php"
INTEGRITY = PLUGIN / "includes" / "class-sc-library-source-versioning-integrity.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
JS = PLUGIN / "assets" / "js" / "sc-library-source-versioning-integrity.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-source-versioning-integrity.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, MANAGER, ENVIRONMENT, RELIABILITY, INTEGRITY, EVIDENCE, JS, CSS):
        assert path.is_file(), path


def test_load_order():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-connected-research-reliability.php",
        "class-sc-library-source-versioning-integrity.php",
        "new SC_Library_Source_Versioning_Integrity",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-connected-research-reliability.php") < text.index("class-sc-library-source-versioning-integrity.php")


def test_version_and_schemas():
    text = read(INTEGRITY)
    for marker in (
        "public const VERSION = '3.1.0'",
        "sc-library-source-integrity/1.0",
        "sc-library-source-version-relation/1.0",
        "sc-library-source-version-snapshot/1.0",
        "sc-library-source-integrity-impact/1.0",
        "sc-library-project-source-integrity/1.0",
    ):
        assert marker in text, marker
    assert "SC_LIBRARY_VERSION : '3.4.0'" in read(WRAPPER)


def test_integrity_statuses():
    text = read(INTEGRITY)
    for marker in (
        "'current'",
        "'updated'",
        "'corrected'",
        "'superseded'",
        "'deprecated'",
        "'expression-of-concern'",
        "'retracted'",
        "'withdrawn'",
        "'archived'",
    ):
        assert marker in text, marker


def test_severity_model():
    text = read(INTEGRITY)
    for marker in (
        "'none'",
        "'info'",
        "'warning'",
        "'high'",
        "'critical'",
        "severity_for_status",
        "more_severe",
    ):
        assert marker in text, marker


def test_version_relation_model():
    text = read(INTEGRITY)
    for marker in (
        "'version-of'",
        "'supersedes'",
        "'corrects'",
        "'retracts'",
        "'replaces'",
        "'erratum-for'",
        "'supplement-to'",
        "'translation-of'",
        "'derived-from'",
    ):
        assert marker in text, marker


def test_version_identity_fields():
    text = read(INTEGRITY)
    for marker in (
        "META_VERSION_LABEL",
        "META_VERSION_NUMBER",
        "META_RELEASE_DATE",
        "META_FAMILY_ID",
        "META_RECOMMENDED_ID",
        "Version family root",
        "Recommended replacement",
    ):
        assert marker in text, marker


def test_integrity_notice_fields():
    text = read(INTEGRITY)
    for marker in (
        "META_STATUS",
        "META_PUBLIC_NOTICE",
        "META_NOTICE_DATE",
        "META_NOTICE_URL",
        "META_REASON",
        "META_REVIEW_STATUS",
        "META_STATUS_CHANGED_AT",
        "Official notice URL",
    ):
        assert marker in text, marker


def test_pre_and_post_save_snapshots():
    text = read(INTEGRITY)
    for marker in (
        "capture_before_source_save",
        "save_source_integrity",
        "structured_snapshot",
        "append_snapshot",
        "META_CURRENT_HASH",
        "MAX_SNAPSHOTS = 30",
        "hash( 'sha256'",
        "wp_generate_uuid4",
    ):
        assert marker in text, marker


def test_snapshot_is_capability_independent():
    text = read(INTEGRITY)
    snapshot = text[text.index("private static function structured_snapshot"):text.index("private static function append_snapshot")]
    assert "get_post_meta" in snapshot
    assert "META_AUTHORS" in snapshot
    assert "META_PROVENANCE" in snapshot
    assert "current_user_can" not in snapshot
    assert "get_source_data" not in snapshot


def test_relation_sanitization_and_self_protection():
    text = read(INTEGRITY)
    for marker in (
        "sanitize_relations",
        "$target_id === absint( $source_id )",
        "MAX_RELATIONS = 100",
        "$seen[ $key ]",
        "sync_incoming_relations",
        "rebuild_incoming_index",
    ):
        assert marker in text, marker


def test_relationship_conflict_detection():
    text = read(INTEGRITY)
    for marker in (
        "META_RELATION_CONFLICT",
        "suggested_status_from_incoming",
        "relationship_conflict",
        "suggested_status",
        "suggested_label",
    ):
        assert marker in text, marker


def test_recommended_source_resolution():
    text = read(INTEGRITY)
    for marker in (
        "resolve_recommended_source",
        "$depth < 20",
        "$visited",
        "'supersedes'",
        "'corrects'",
        "'replaces'",
    ):
        assert marker in text, marker


def test_version_family():
    text = read(INTEGRITY)
    for marker in (
        "normalize_family",
        "version_family",
        "META_FAMILY_ID",
        "'version-of'",
        "strnatcasecmp",
    ):
        assert marker in text, marker


def test_impact_model():
    text = read(INTEGRITY)
    for marker in (
        "build_impact_report",
        "rebuild_impact",
        "propagate_impact",
        "META_EVIDENCE_IMPACT",
        "META_CLAIM_IMPACTS",
        "META_PROJECT_IMPACTS",
        "evidence_note_ids",
        "claim_ids",
        "document_ids",
        "project_ids",
    ):
        assert marker in text, marker


def test_no_automatic_claim_or_evidence_retraction():
    text = read(INTEGRITY)
    assert "META_CLAIM_STATUS" not in text
    assert "META_REVIEW_STATUS, 'retracted'" not in text
    assert "Citation replaced" in text


def test_project_acknowledgements():
    text = read(INTEGRITY)
    for marker in (
        "META_PROJECT_ACKS",
        "acknowledgement_options",
        "'pending'",
        "'reviewed'",
        "'replacement-planned'",
        "'replaced'",
        "'accepted-for-context'",
        "'excluded'",
        "save_project_acknowledgements",
    ):
        assert marker in text, marker


def test_public_integrity_notices():
    manager = read(MANAGER)
    environment = read(ENVIRONMENT)
    text = read(INTEGRITY)
    assert "SC_Library_Source_Versioning_Integrity::render_public_integrity_notice" in manager
    assert "SC_Library_Source_Versioning_Integrity::render_bibliography_integrity_badge" in environment
    for marker in (
        "Research integrity notice",
        "The historical citation is preserved",
        "Open official notice",
        "Review recommended replacement",
    ):
        assert marker in text, marker


def test_source_data_filter():
    text = read(INTEGRITY)
    manager = read(MANAGER)
    assert "add_filter( 'sc_library_source_data'" in text
    assert "$data['integrity']" in text
    assert "apply_filters( 'sc_library_source_data'" in manager


def test_admin_workspaces():
    text = read(INTEGRITY)
    for marker in (
        "Source Integrity",
        "Version, Supersession, and Integrity",
        "Research Impact Review",
        "Source Integrity Impact",
        "Integrity alerts",
        "Integrity index scan",
    ):
        assert marker in text, marker


def test_bounded_resumable_scan():
    text = read(INTEGRITY)
    for marker in (
        "OPTION_SCAN_STATE",
        "TRANSIENT_SCAN_LOCK",
        "SCAN_BATCH = 20",
        "LOCK_SECONDS = 180",
        "run_scan_batch",
        "ID > %d",
        "catch ( Throwable $error )",
        "wp_schedule_event",
    ):
        assert marker in text, marker


def test_source_columns():
    text = read(INTEGRITY)
    assert "source_columns" in text
    assert "source_column_content" in text
    assert "Version" in text
    assert "Integrity" in text


def test_deletion_cleanup():
    text = read(INTEGRITY)
    for marker in (
        "capture_deleted_source",
        "cleanup_deleted_source",
        "before_delete_post",
        "deleted_post",
        "META_RECOMMENDED_ID",
        "META_FAMILY_ID",
        "META_PROJECT_ACKS",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(INTEGRITY)
    for marker in (
        "add_shortcode( 'sc_source_integrity'",
        "add_shortcode( 'sc_project_source_integrity'",
        "include_private",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(INTEGRITY)
    for marker in (
        "'/sources/(?P<id>\\d+)/integrity'",
        "'/sources/(?P<id>\\d+)/versions'",
        "'/sources/(?P<id>\\d+)/impact'",
        "'/projects/(?P<id>\\d+)/source-integrity'",
        "'/integrity/alerts'",
        "'/integrity/scan'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(INTEGRITY)
    for marker in (
        "rest_can_read_source_integrity",
        "rest_can_edit_source",
        "rest_can_read_project_integrity",
        "rest_can_edit_project",
        "protect_integrity_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_wp_cli():
    text = read(INTEGRITY)
    for marker in (
        "sc-library sources integrity-scan",
        "sc-library sources integrity",
        "sc-library sources integrity-rebuild",
        "sc-library projects integrity",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_dynamic_relation_client():
    text = read(JS)
    for marker in (
        "data-sc-add-integrity-relation",
        "data-sc-remove-integrity-relation",
        "renumberRelations",
        "sc_source_integrity_relations",
        "sc_library_v310_rebuild_source_integrity",
        "sc_library_v310_scan_integrity",
    ):
        assert marker in text, marker


def test_accessible_responsive_styles():
    text = read(CSS)
    for marker in (
        ".sc-integrity-editor",
        ".sc-integrity-relation-row",
        ".sc-public-integrity-notice",
        ".sc-bibliography-integrity-warning",
        ".sc-public-project-integrity",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text


def test_retained_systems():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-connected-research-environment.php",
        "class-sc-library-connected-research-reliability.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.0.1'" in read(ENVIRONMENT)
    assert "public const VERSION = '3.0.1'" in read(RELIABILITY)


def main():
    tests = [
        test_required_files,
        test_load_order,
        test_version_and_schemas,
        test_integrity_statuses,
        test_severity_model,
        test_version_relation_model,
        test_version_identity_fields,
        test_integrity_notice_fields,
        test_pre_and_post_save_snapshots,
        test_snapshot_is_capability_independent,
        test_relation_sanitization_and_self_protection,
        test_relationship_conflict_detection,
        test_recommended_source_resolution,
        test_version_family,
        test_impact_model,
        test_no_automatic_claim_or_evidence_retraction,
        test_project_acknowledgements,
        test_public_integrity_notices,
        test_source_data_filter,
        test_admin_workspaces,
        test_bounded_resumable_scan,
        test_source_columns,
        test_deletion_cleanup,
        test_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_wp_cli,
        test_dynamic_relation_client,
        test_accessible_responsive_styles,
        test_retained_systems,
    ]
    for test in tests:
        test()
    print(f"Source Versioning, Supersession, and Research Integrity checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
