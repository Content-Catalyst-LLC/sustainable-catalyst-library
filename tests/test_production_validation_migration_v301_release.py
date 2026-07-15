from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
ENVIRONMENT = PLUGIN / "includes" / "class-sc-library-connected-research-environment.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-connected-research-reliability.php"
CONNECTORS = PLUGIN / "includes" / "class-sc-library-scholarly-library-connectors.php"
JS = PLUGIN / "assets" / "js" / "sc-library-connected-research-reliability.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-connected-research-reliability.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, ENVIRONMENT, RELIABILITY, CONNECTORS, JS, CSS):
        assert path.is_file(), path


def test_reliability_load_order():
    text = read(WRAPPER)
    assert "class-sc-library-connected-research-environment.php" in text
    assert "class-sc-library-connected-research-reliability.php" in text
    assert text.index("class-sc-library-connected-research-environment.php") < text.index("class-sc-library-connected-research-reliability.php")
    assert "new SC_Library_Connected_Research_Reliability" in text


def test_versions():
    assert "SC_LIBRARY_VERSION : '3.4.0'" in read(WRAPPER)
    assert "public const VERSION = '3.0.1'" in read(ENVIRONMENT)
    assert "public const VERSION = '3.0.1'" in read(RELIABILITY)


def test_resumable_migration_state():
    text = read(RELIABILITY)
    for marker in (
        "OPTION_MIGRATION_STATE",
        "TRANSIENT_MIGRATION_LOCK",
        "CRON_HOOK",
        "'cursor'",
        "'processed'",
        "'repaired'",
        "'failures'",
        "'last_project_id'",
        "'completed_at'",
        "run_migration_batch",
        "ID > %d",
    ):
        assert marker in text, marker


def test_migration_lock_and_exception_boundary():
    text = read(RELIABILITY)
    for marker in (
        "get_transient( self::TRANSIENT_MIGRATION_LOCK",
        "set_transient( self::TRANSIENT_MIGRATION_LOCK",
        "delete_transient( self::TRANSIENT_MIGRATION_LOCK",
        "catch ( Throwable $error )",
        "'migration_exception'",
    ):
        assert marker in text, marker


def test_source_reconciliation():
    text = read(RELIABILITY)
    for marker in (
        "reconcile_sources",
        "META_SOURCE_ENTRIES",
        "META_PROJECT_SOURCE_IDS",
        "META_PROJECT_IDS",
        "Project and Source relationships reconciled",
        "duplicate project Source entry",
        "reverse project relationships",
    ):
        assert marker in text, marker


def test_section_document_team_snapshot_repair():
    text = read(RELIABILITY)
    for marker in (
        "validated_sections",
        "validate_documents",
        "validate_team",
        "validate_snapshots",
        "validate_activity",
        "validate_sort",
        "Bibliography snapshots recovered and rehashed",
    ):
        assert marker in text, marker


def test_snapshot_integrity():
    text = read(RELIABILITY)
    for marker in (
        "MAX_SNAPSHOTS",
        "wp_generate_uuid4",
        "hash( 'sha256'",
        "missing or duplicate identifier",
        "hash did not match",
    ):
        assert marker in text, marker


def test_bounded_repair_queue():
    text = read(RELIABILITY)
    for marker in (
        "OPTION_REPAIR_QUEUE",
        "REPAIR_QUEUE_BATCH",
        "process_repair_queue",
        "array_slice( array_values( array_unique( $queue ) ), -500 )",
    ):
        assert marker in text, marker


def test_post_save_validation_hooks():
    environment = read(ENVIRONMENT)
    reliability = read(RELIABILITY)
    assert "do_action( 'sc_library_connected_research_saved', $post_id )" in environment
    assert "add_action( 'sc_library_connected_research_saved'" in reliability
    assert "save_post_" in reliability
    assert "queue_source_projects" in reliability


def test_large_library_lookup():
    text = read(RELIABILITY)
    js = read(JS)
    for marker in (
        "Large Library Record Lookup",
        "LOOKUP_LIMIT = 30",
        "ajax_search_records",
        "ajax_attach_record",
        "data-sc-v301-search-records",
        "data-sc-v301-attach-record",
    ):
        assert marker in text or marker in js, marker
    assert read(ENVIRONMENT).count("'posts_per_page' => 200,") >= 2


def test_private_cache_protection():
    environment = read(ENVIRONMENT)
    reliability = read(RELIABILITY)
    assert environment.count("nocache_headers();") >= 2
    for marker in (
        "rest_post_dispatch",
        "Cache-Control",
        "no-store, no-cache, must-revalidate, private",
        "Vary",
        "Cookie, Authorization",
    ):
        assert marker in reliability, marker


def test_validation_dashboard():
    text = read(RELIABILITY)
    for marker in (
        "Production Validation",
        "Resumable migration",
        "Recent project integrity",
        "Run Next Batch",
        "Reset Migration State",
        "Validate Project",
        "Repair Project",
        "Validate Exports",
    ):
        assert marker in text, marker


def test_integrity_report_contract():
    text = read(RELIABILITY)
    for marker in (
        "sc-library-production-validation/1.0",
        "'status'",
        "'failures'",
        "'warnings'",
        "'repairable'",
        "'changes'",
        "'repair_mode'",
        "META_INTEGRITY_REPORT",
    ):
        assert marker in text, marker


def test_export_validation_formats():
    text = read(RELIABILITY)
    for marker in (
        "'markdown'",
        "'text'",
        "'html'",
        "'bibtex'",
        "'ris'",
        "'csl-json'",
        "'json'",
        "validate_bibtex",
        "validate_ris",
        "validate_csl_records",
    ):
        assert marker in text, marker


def test_export_reports_persist():
    text = read(RELIABILITY)
    assert "META_EXPORT_REPORT" in text
    assert "update_post_meta( $project_id, self::META_EXPORT_REPORT, $report )" in text
    assert "sc-library-export-validation/1.0" in text


def test_reliability_rest_routes():
    text = read(RELIABILITY)
    for marker in (
        "'/projects/reliability/migration'",
        "'/projects/(?P<id>\\d+)/validation'",
        "'/projects/(?P<id>\\d+)/repair'",
        "'/projects/(?P<id>\\d+)/export-validation'",
        "rest_can_edit_project",
    ):
        assert marker in text, marker


def test_ajax_security():
    text = read(RELIABILITY)
    for marker in (
        "check_ajax_referer",
        "current_user_can( $capability )",
        "current_user_can( 'edit_post', $project_id )",
        "wp_send_json_error",
    ):
        assert marker in text, marker


def test_wp_cli_commands():
    text = read(RELIABILITY)
    for marker in (
        "sc-library projects migrate",
        "sc-library projects validate",
        "sc-library projects exports",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_cron_and_manual_recovery():
    text = read(RELIABILITY)
    for marker in (
        "wp_schedule_event",
        "'hourly'",
        "run_scheduled_reliability",
        "ajax_run_migration",
        "ajax_reset_migration",
    ):
        assert marker in text, marker


def test_no_public_request_migration_loop():
    environment = read(ENVIRONMENT)
    assert "SC_Library_Connected_Research_Reliability::maybe_schedule_migration" in environment
    assert "posts_per_page' => 25" not in environment


def test_javascript_actions():
    text = read(JS)
    for marker in (
        "sc_library_v301_search_records",
        "sc_library_v301_attach_record",
        "sc_library_v301_run_migration",
        "sc_library_v301_reset_migration",
        "sc_library_v301_validate_project",
        "sc_library_v301_repair_project",
        "sc_library_v301_validate_exports",
    ):
        assert marker in text, marker


def test_responsive_interface():
    text = read(CSS)
    for marker in (
        ".sc-v301-project-reliability",
        ".sc-v301-record-lookup",
        ".sc-v301-migration-card",
        ".sc-v301-search-results",
        "@media (max-width: 860px)",
        "@media print",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text


def test_prior_subsystems_retained():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-connected-research-environment.php",
    ):
        assert marker in wrapper, marker


def main():
    tests = [
        test_required_files,
        test_reliability_load_order,
        test_versions,
        test_resumable_migration_state,
        test_migration_lock_and_exception_boundary,
        test_source_reconciliation,
        test_section_document_team_snapshot_repair,
        test_snapshot_integrity,
        test_bounded_repair_queue,
        test_post_save_validation_hooks,
        test_large_library_lookup,
        test_private_cache_protection,
        test_validation_dashboard,
        test_integrity_report_contract,
        test_export_validation_formats,
        test_export_reports_persist,
        test_reliability_rest_routes,
        test_ajax_security,
        test_wp_cli_commands,
        test_cron_and_manual_recovery,
        test_no_public_request_migration_loop,
        test_javascript_actions,
        test_responsive_interface,
        test_prior_subsystems_retained,
    ]
    for test in tests:
        test()
    print(f"Production Validation and Migration Reliability checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
