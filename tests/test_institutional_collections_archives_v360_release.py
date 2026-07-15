from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
ARCHIVES = PLUGIN / "includes" / "class-sc-library-institutional-collections-archives.php"
QUALITY = PLUGIN / "includes" / "class-sc-library-research-quality-governance.php"
HANDOFFS = PLUGIN / "includes" / "class-sc-library-cross-product-research-handoffs.php"
PATHWAYS = PLUGIN / "includes" / "class-sc-library-knowledge-pathways-article-maps.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
JS = PLUGIN / "assets" / "js" / "sc-library-institutional-archives.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-institutional-archives.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, ARCHIVES, QUALITY, HANDOFFS, PATHWAYS, SEMANTIC, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    text = read(WRAPPER)
    assert "class-sc-library-institutional-collections-archives.php" in text
    assert "new SC_Library_Institutional_Collections_Archives" in text
    assert text.index("class-sc-library-research-quality-governance.php") < text.index("class-sc-library-institutional-collections-archives.php")
    assert "SC_LIBRARY_VERSION : '3.8.0'" in text
    assert "public const VERSION = '3.6.0'" in read(ARCHIVES)


def test_schemas():
    text = read(ARCHIVES)
    for marker in (
        "sc-library-institutional-collection/1.0",
        "sc-library-archive-component/1.0",
        "sc-library-archive-accession/1.0",
        "sc-library-finding-aid/1.0",
        "sc-library-preservation-audit/1.0",
        "sc-library-retention-disposition/1.0",
        "sc-library-archive-dashboard/1.0",
    ):
        assert marker in text, marker


def test_record_types():
    text = read(ARCHIVES)
    for marker in (
        "sc_inst_collection",
        "sc_archive_component",
        "sc_archive_accession",
        "sc_archive_disposition",
        "register_record_types",
        "Institutional Collections",
        "Archive Components",
    ):
        assert marker in text, marker


def test_collection_statuses():
    text = read(ARCHIVES)
    for marker in (
        "'draft'",
        "'processing'",
        "'active'",
        "'published'",
        "'closed'",
        "'deaccessioned'",
        "'archived'",
    ):
        assert marker in text, marker


def test_component_levels():
    text = read(ARCHIVES)
    for marker in (
        "'collection'",
        "'fonds'",
        "'record-group'",
        "'series'",
        "'subseries'",
        "'box'",
        "'folder'",
        "'item'",
        "'digital-object'",
    ):
        assert marker in text, marker


def test_access_levels():
    text = read(ARCHIVES)
    for marker in (
        "'public'",
        "'reading-room'",
        "'restricted'",
        "'embargoed'",
        "'confidential'",
        "META_EMBARGO_UNTIL",
    ):
        assert marker in text, marker


def test_accession_methods_and_statuses():
    text = read(ARCHIVES)
    for marker in (
        "'transfer'",
        "'donation'",
        "'deposit'",
        "'purchase'",
        "'born-digital'",
        "'legacy'",
        "'received'",
        "'quarantined'",
        "'inventory'",
        "'cataloged'",
    ):
        assert marker in text, marker


def test_retention_model():
    text = read(ARCHIVES)
    for marker in (
        "'permanent'",
        "'review'",
        "'years'",
        "'transfer'",
        "'destroy'",
        "META_RETENTION_CLASS",
        "META_RETENTION_YEARS",
        "META_RETENTION_TRIGGER",
        "META_RETENTION_REVIEW",
        "META_LEGAL_HOLD",
        "retention_data",
    ):
        assert marker in text, marker


def test_preservation_model():
    text = read(ARCHIVES)
    for marker in (
        "'not-assessed'",
        "'stable'",
        "'monitor'",
        "'at-risk'",
        "'critical'",
        "'missing'",
        "run_preservation_audit",
        "missing_checksums",
        "at_risk_objects",
        "missing_objects",
        "META_LAST_AUDIT_REPORT",
    ):
        assert marker in text, marker


def test_collection_metadata():
    text = read(ARCHIVES)
    for marker in (
        "META_IDENTIFIER",
        "META_INSTITUTION",
        "META_DEPARTMENT",
        "META_CREATOR",
        "META_DATE_START",
        "META_DATE_END",
        "META_EXTENT",
        "META_LANGUAGES",
        "META_SCOPE",
        "META_ARRANGEMENT",
        "META_PROVENANCE",
        "META_ACQUISITION",
        "META_RIGHTS",
        "META_ACCESS_NOTE",
        "META_USE_NOTE",
        "META_CITATION_NOTE",
    ):
        assert marker in text, marker


def test_component_links():
    text = read(ARCHIVES)
    for marker in (
        "META_COMPONENT_COLLECTION",
        "META_COMPONENT_PARENT",
        "META_COMPONENT_LEVEL",
        "META_COMPONENT_DOCUMENT_IDS",
        "META_COMPONENT_SOURCE_IDS",
        "META_COMPONENT_PROJECT_IDS",
        "META_COMPONENT_DIGITAL_OBJECTS",
        "META_COMPONENT_PRESERVATION",
    ):
        assert marker in text, marker


def test_accessions_and_custody():
    text = read(ARCHIVES)
    for marker in (
        "META_ACCESSION_COLLECTION",
        "META_ACCESSION_NUMBER",
        "META_ACCESSION_DATE",
        "META_ACCESSION_METHOD",
        "META_ACCESSION_SOURCE",
        "META_ACCESSION_DONOR",
        "META_ACCESSION_AGREEMENT",
        "META_ACCESSION_RESTRICTIONS",
        "META_ACCESSION_CUSTODY",
        "sanitize_custody_events",
        "custody_history",
    ):
        assert marker in text, marker


def test_digital_objects_and_checksums():
    text = read(ARCHIVES)
    for marker in (
        "sanitize_digital_objects",
        "'checksum'",
        "'checksum_algorithm'",
        "'sha256'",
        "'sha512'",
        "'md5'",
        "'media_type'",
        "'bytes'",
        "'preservation_status'",
    ):
        assert marker in text, marker


def test_finding_aids():
    text = read(ARCHIVES)
    for marker in (
        "finding_aid",
        "component_tree",
        "render_finding_aid_public",
        "render_component_nodes",
        "META_FINDING_AID_PUBLIC",
        "Finding aid",
    ):
        assert marker in text, marker


def test_stable_identity():
    text = read(ARCHIVES)
    for marker in (
        "META_COLLECTION_UUID",
        "ensure_collection_uuid_static",
        "wp_generate_uuid4",
        "Stable collection UUID",
    ):
        assert marker in text, marker


def test_disposition_records():
    text = read(ARCHIVES)
    for marker in (
        "create_disposition",
        "transition_disposition",
        "disposition_data",
        "META_DISPOSITION_ACTION",
        "META_DISPOSITION_REASON",
        "META_DISPOSITION_STATUS",
        "META_DISPOSITION_DUE",
        "META_DISPOSITION_APPROVER",
        "META_DISPOSITION_HISTORY",
    ):
        assert marker in text, marker


def test_legal_hold_controls():
    text = read(ARCHIVES)
    for marker in (
        "disposition_legal_hold",
        "legal or administrative hold",
        "in_array( $action, array( 'destroy', 'deaccession', 'transfer' )",
        "$retention['legal_hold']",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(ARCHIVES)
    for marker in (
        "Institutional Collections and Archive Management",
        "Collections & Archives",
        "Collection register",
        "Archive migration",
        "render_workspace",
        "render_collection_meta_box",
        "render_component_meta_box",
        "render_accession_meta_box",
    ):
        assert marker in text, marker


def test_dashboard():
    text = read(ARCHIVES)
    for marker in (
        "dashboard_report",
        "collection_count",
        "public_count",
        "restricted_count",
        "at_risk_count",
        "retention_due_count",
        "legal_hold_count",
        "digital_objects",
        "missing_checksums",
    ):
        assert marker in text, marker


def test_resumable_migration():
    text = read(ARCHIVES)
    for marker in (
        "OPTION_MIGRATION",
        "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 20",
        "LOCK_SECONDS = 180",
        "run_migration_batch",
        "ID > %d",
        "'collections'",
        "'components'",
        "'accessions'",
        "catch ( Throwable $error )",
        "wp_schedule_event",
    ):
        assert marker in text, marker


def test_preservation_cron():
    text = read(ARCHIVES)
    for marker in (
        "CRON_PRESERVATION",
        "run_scheduled_preservation_audit",
        "'daily'",
        "run_preservation_audit",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(ARCHIVES)
    for marker in (
        "add_shortcode( 'sc_institutional_collection'",
        "add_shortcode( 'sc_archive_finding_aid'",
        "add_shortcode( 'sc_archive_collection_browser'",
        "add_shortcode( 'sc_archive_preservation_status'",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(ARCHIVES)
    for marker in (
        "'/archives/collections'",
        "'/archives/collections/(?P<id>\\d+)'",
        "'/archives/collections/(?P<id>\\d+)/finding-aid'",
        "'/archives/collections/(?P<id>\\d+)/preservation'",
        "'/archives/collections/(?P<id>\\d+)/dispositions'",
        "'/archives/dispositions/(?P<id>\\d+)/status'",
        "'/archives/dashboard'",
        "'/archives/migration'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(ARCHIVES)
    for marker in (
        "rest_can_read_collection",
        "rest_can_read_finding_aid",
        "rest_can_edit_collection",
        "rest_can_edit_disposition",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(ARCHIVES)
    js = read(JS)
    for marker in (
        "sc_library_v360_run_migration",
        "sc_library_v360_run_audit",
        "sc_library_v360_create_disposition",
        "sc_library_v360_transition_disposition",
    ):
        assert marker in text, marker
    for marker in ("sc_library_v360_run_migration", "sc_library_v360_run_audit"):
        assert marker in js, marker


def test_cli_commands():
    text = read(ARCHIVES)
    for marker in (
        "sc-library archives collection",
        "sc-library archives finding-aid",
        "sc-library archives audit",
        "sc-library archives disposition",
        "sc-library archives migrate",
        "sc-library archives dashboard",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_deletion_cleanup():
    text = read(ARCHIVES)
    for marker in (
        "cleanup_deleted_record",
        "before_delete_post",
        "META_COMPONENT_COLLECTION",
        "META_ACCESSION_COLLECTION",
        "META_DISPOSITION_COLLECTION",
        "META_COMPONENT_PARENT",
    ):
        assert marker in text, marker


def test_access_privacy():
    text = read(ARCHIVES)
    for marker in (
        "collection_is_public",
        "component_is_public",
        "'public' !== $access",
        "$embargo <= current_time( 'Y-m-d' )",
        "finding_aid_public",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-archive-center",
        ".sc-institutional-collection",
        ".sc-finding-aid",
        ".sc-archive-browser",
        ".sc-preservation-summary",
        "@media (max-width: 700px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in css, marker
    assert "renumber" in js
    assert "aria-live" in read(ARCHIVES)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-topics-concepts-relationships.php",
        "class-sc-library-knowledge-pathways-article-maps.php",
        "class-sc-library-cross-product-research-handoffs.php",
        "class-sc-library-research-quality-governance.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.5.0'" in read(QUALITY)
    assert "public const VERSION = '3.4.0'" in read(HANDOFFS)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_schemas,
        test_record_types,
        test_collection_statuses,
        test_component_levels,
        test_access_levels,
        test_accession_methods_and_statuses,
        test_retention_model,
        test_preservation_model,
        test_collection_metadata,
        test_component_links,
        test_accessions_and_custody,
        test_digital_objects_and_checksums,
        test_finding_aids,
        test_stable_identity,
        test_disposition_records,
        test_legal_hold_controls,
        test_admin_center,
        test_dashboard,
        test_resumable_migration,
        test_preservation_cron,
        test_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_ajax_actions,
        test_cli_commands,
        test_deletion_cleanup,
        test_access_privacy,
        test_accessible_responsive_ui,
        test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Institutional Collections and Archive Management checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
