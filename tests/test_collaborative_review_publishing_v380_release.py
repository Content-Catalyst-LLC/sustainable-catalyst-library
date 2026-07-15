from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
REVIEW = PLUGIN / "includes" / "class-sc-library-collaborative-review-publishing.php"
INTEL = PLUGIN / "includes" / "class-sc-library-research-librarian-document-intelligence.php"
ARCHIVES = PLUGIN / "includes" / "class-sc-library-institutional-collections-archives.php"
QUALITY = PLUGIN / "includes" / "class-sc-library-research-quality-governance.php"
HANDOFFS = PLUGIN / "includes" / "class-sc-library-cross-product-research-handoffs.php"
JS = PLUGIN / "assets" / "js" / "sc-library-collaborative-review-publishing.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-collaborative-review-publishing.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, REVIEW, INTEL, ARCHIVES, QUALITY, HANDOFFS, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    text = read(WRAPPER)
    assert "class-sc-library-collaborative-review-publishing.php" in text
    assert "new SC_Library_Collaborative_Review_Publishing" in text
    assert text.index("class-sc-library-research-librarian-document-intelligence.php") < text.index("class-sc-library-collaborative-review-publishing.php")
    assert "SC_LIBRARY_VERSION : '3.8.0'" in text
    assert "public const VERSION = '3.8.0'" in read(REVIEW)


def test_schemas():
    text = read(REVIEW)
    for marker in (
        "sc-library-collaborative-review/1.0",
        "sc-library-review-assignment/1.0",
        "sc-library-review-note/1.0",
        "sc-library-review-decision/1.0",
        "sc-library-research-publication-package/1.0",
        "sc-library-review-transparency/1.0",
        "sc-library-collaborative-publishing-dashboard/1.0",
        "sc-platform-handoff/collaborative-review-publishing/1.0",
    ):
        assert marker in text, marker


def test_record_types():
    text = read(REVIEW)
    for marker in ("sc_review_cycle", "sc_review_note", "sc_pub_package", "register_record_types"):
        assert marker in text, marker


def test_review_statuses():
    text = read(REVIEW)
    for marker in (
        "'draft'", "'invited'", "'in-review'", "'changes-requested'",
        "'revised'", "'approved'", "'closed'", "'archived'",
    ):
        assert marker in text, marker


def test_review_types():
    text = read(REVIEW)
    for marker in (
        "'editorial'", "'methodology'", "'evidence'", "'citations'",
        "'governance'", "'accessibility'", "'privacy'", "'legal'", "'publication'",
    ):
        assert marker in text, marker


def test_roles_and_decisions():
    text = read(REVIEW)
    for marker in (
        "'author'", "'editor'", "'reviewer'", "'approver'", "'observer'",
        "'approve'", "'approve-minor'", "'request-changes'", "'reject'", "'recuse'",
    ):
        assert marker in text, marker


def test_review_metadata():
    text = read(REVIEW)
    for marker in (
        "META_REVIEW_UUID", "META_REVIEW_STATUS", "META_REVIEW_TYPE",
        "META_REVIEW_DOCUMENT_IDS", "META_REVIEW_PROJECT_IDS",
        "META_REVIEW_ASSIGNMENTS", "META_REVIEW_SNAPSHOTS",
        "META_REVIEW_DECISIONS", "META_REVIEW_GATE",
        "META_REVIEW_REQUIRED_APPROVALS", "META_REVIEW_DUE_DATE",
        "META_REVIEW_PUBLIC", "META_REVIEW_PUBLIC_SUMMARY",
        "META_REVIEW_COI_POLICY", "META_REVIEW_READINESS", "META_REVIEW_HISTORY",
    ):
        assert marker in text, marker


def test_assignment_model():
    text = read(REVIEW)
    for marker in (
        "sanitize_assignments", "assignment_id", "display_name", "review_type",
        "decision_note", "conflict_note", "invited_at", "responded_at",
        "MAX_ASSIGNMENTS = 100",
    ):
        assert marker in text, marker


def test_snapshot_change_detection():
    text = read(REVIEW)
    for marker in (
        "build_snapshots", "detect_snapshot_changes", "content_hash",
        "intelligence_hash", "snapshot_at", "hash_equals",
        "The document changed after the review snapshot",
    ):
        assert marker in text, marker


def test_review_notes():
    text = read(REVIEW)
    for marker in (
        "create_note", "resolve_note", "note_data", "review_notes",
        "META_NOTE_REVIEW_ID", "META_NOTE_DOCUMENT_ID", "META_NOTE_PARENT_ID",
        "META_NOTE_TYPE", "META_NOTE_SEVERITY", "META_NOTE_STATUS",
        "META_NOTE_SECTION", "META_NOTE_ANCHOR", "META_NOTE_QUOTE",
        "META_NOTE_RESOLUTION",
    ):
        assert marker in text, marker


def test_note_types():
    text = read(REVIEW)
    for marker in (
        "'comment'", "'question'", "'required-change'", "'suggestion'",
        "'citation'", "'evidence'", "'integrity'", "'accessibility'",
    ):
        assert marker in text, marker


def test_decision_and_conflict_workflow():
    text = read(REVIEW)
    for marker in (
        "record_decision", "META_REVIEW_DECISIONS", "conflict",
        "conflict_note", "review_assignment_not_found", "decision-recorded",
    ):
        assert marker in text, marker


def test_review_readiness():
    text = read(REVIEW)
    for marker in (
        "evaluate_review", "required_approvals", "approval_count",
        "minor_approval_count", "changes_requested_count", "rejection_count",
        "recused_count", "conflict_count", "open_note_count",
        "critical_note_count", "changed_document_count", "'ready' =>",
        "'blocked' =>",
    ):
        assert marker in text, marker


def test_publication_statuses():
    text = read(REVIEW)
    for marker in (
        "'assembling'", "'review'", "'approved'", "'scheduled'",
        "'published'", "'withdrawn'", "'archived'",
    ):
        assert marker in text, marker


def test_publication_metadata():
    text = read(REVIEW)
    for marker in (
        "META_PACKAGE_UUID", "META_PACKAGE_STATUS", "META_PACKAGE_DOCUMENT_IDS",
        "META_PACKAGE_PROJECT_IDS", "META_PACKAGE_REVIEW_IDS",
        "META_PACKAGE_VERSION", "META_PACKAGE_RELEASE_NOTES",
        "META_PACKAGE_LICENSE", "META_PACKAGE_DOI",
        "META_PACKAGE_CANONICAL_URL", "META_PACKAGE_EMBARGO_UNTIL",
        "META_PACKAGE_PUBLISH_AT", "META_PACKAGE_READINESS",
        "META_PACKAGE_APPROVALS", "META_PACKAGE_MANIFEST",
        "META_PACKAGE_PUBLISHED_AT", "META_PACKAGE_HISTORY",
    ):
        assert marker in text, marker


def test_publication_readiness():
    text = read(REVIEW)
    for marker in (
        "evaluate_package", "readiness_check", "documents-selected",
        "reviews-selected", "release-notes", "approved_reviews",
        "blocked_reviews", "critical_failures", "high_failures",
        "content_hash", "manifest",
    ):
        assert marker in text, marker


def test_package_transitions():
    text = read(REVIEW)
    for marker in (
        "approve_package", "transition_package",
        "publication_package_not_ready", "package-approved",
        "package-transition", "META_PACKAGE_PUBLISHED_AT",
    ):
        assert marker in text, marker


def test_scheduled_publication():
    text = read(REVIEW)
    for marker in (
        "CRON_PUBLICATION", "run_scheduled_publications",
        "Scheduled publication executed by WordPress cron",
        "strtotime", "current_time( 'timestamp' )",
    ):
        assert marker in text, marker


def test_transparency():
    text = read(REVIEW)
    for marker in (
        "transparency_data", "TRANSPARENCY_SCHEMA",
        "public_summary", "approval_count", "open_note_count",
        "changed_document_count", "private reviewer identities",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(REVIEW)
    for marker in (
        "Collaborative Review and Research Publishing",
        "Review & Publishing", "Research Reviews", "Publication Packages",
        "render_workspace", "render_review_meta_box",
        "render_review_status_box", "render_package_meta_box",
        "render_package_status_box",
    ):
        assert marker in text, marker


def test_dashboard():
    text = read(REVIEW)
    for marker in (
        "dashboard_report", "review_count", "active_review_count",
        "approved_review_count", "open_note_count", "conflict_count",
        "package_count", "ready_package_count", "scheduled_package_count",
        "published_package_count",
    ):
        assert marker in text, marker


def test_migration():
    text = read(REVIEW)
    for marker in (
        "OPTION_MIGRATION", "TRANSIENT_LOCK", "MIGRATION_BATCH = 20",
        "LOCK_SECONDS = 180", "run_migration_batch", "ID > %d",
        "catch ( Throwable $error )", "wp_schedule_event",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(REVIEW)
    for marker in (
        "add_shortcode( 'sc_review_transparency'",
        "add_shortcode( 'sc_publication_record'",
        "add_shortcode( 'sc_research_release_history'",
        "add_shortcode( 'sc_collaborative_review_dashboard'",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(REVIEW)
    for marker in (
        "'/reviews'", "'/reviews/(?P<id>\\d+)'",
        "'/reviews/(?P<id>\\d+)/notes'",
        "'/review-notes/(?P<id>\\d+)'",
        "'/reviews/(?P<id>\\d+)/decision'",
        "'/reviews/(?P<id>\\d+)/transparency'",
        "'/publication-packages'",
        "'/publication-packages/(?P<id>\\d+)'",
        "'/publication-packages/(?P<id>\\d+)/evaluate'",
        "'/publication-packages/(?P<id>\\d+)/transition'",
        "'/review-publishing/dashboard'",
        "'/review-publishing/migration'",
    ):
        assert marker in text, marker


def test_rest_permissions_cache():
    text = read(REVIEW)
    for marker in (
        "rest_can_read_review", "rest_can_edit_review", "rest_can_edit_note",
        "rest_can_read_package", "rest_can_edit_package",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(REVIEW)
    js = read(JS)
    for marker in (
        "sc_library_v380_run_migration", "sc_library_v380_refresh_review",
        "sc_library_v380_add_note", "sc_library_v380_record_decision",
        "sc_library_v380_evaluate_package",
    ):
        assert marker in text, marker
    for marker in (
        "sc_library_v380_run_migration", "sc_library_v380_refresh_review",
        "sc_library_v380_add_note", "sc_library_v380_evaluate_package",
    ):
        assert marker in js, marker


def test_handoffs():
    text = read(REVIEW)
    for marker in (
        "sc_library_research_librarian_project_context",
        "sc_library_cross_product_handoff_sections",
        "filter_project_context", "filter_handoff_sections",
        "collaborative_review_publishing", "HANDOFF_SCHEMA",
    ):
        assert marker in text, marker


def test_cleanup():
    text = read(REVIEW)
    for marker in (
        "cleanup_deleted_record", "before_delete_post",
        "wp_delete_post", "META_PACKAGE_REVIEW_IDS",
        "META_REVIEW_DOCUMENT_IDS", "META_PACKAGE_DOCUMENT_IDS",
    ):
        assert marker in text, marker


def test_cli_commands():
    text = read(REVIEW)
    for marker in (
        "sc-library reviews evaluate", "sc-library reviews note",
        "sc-library reviews decision", "sc-library publishing evaluate",
        "sc-library publishing transition", "sc-library reviews migrate",
        "sc-library reviews dashboard", "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-review-center", ".sc-review-editor", ".sc-publication-editor",
        ".sc-review-transparency", ".sc-publication-record",
        ".sc-release-history", "@media (max-width: 700px)",
        "@media print", "focus-visible",
    ):
        assert marker in css, marker
    assert "renumberAssignments" in js
    assert "aria-live" in read(REVIEW)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-research-librarian-document-intelligence.php",
        "class-sc-library-institutional-collections-archives.php",
        "class-sc-library-research-quality-governance.php",
        "class-sc-library-cross-product-research-handoffs.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.7.0'" in read(INTEL)
    assert "public const VERSION = '3.6.0'" in read(ARCHIVES)
    assert "public const VERSION = '3.5.0'" in read(QUALITY)


def main():
    tests = [
        test_required_files, test_load_order_and_version, test_schemas,
        test_record_types, test_review_statuses, test_review_types,
        test_roles_and_decisions, test_review_metadata, test_assignment_model,
        test_snapshot_change_detection, test_review_notes, test_note_types,
        test_decision_and_conflict_workflow, test_review_readiness,
        test_publication_statuses, test_publication_metadata,
        test_publication_readiness, test_package_transitions,
        test_scheduled_publication, test_transparency, test_admin_center,
        test_dashboard, test_migration, test_shortcodes, test_rest_routes,
        test_rest_permissions_cache, test_ajax_actions, test_handoffs,
        test_cleanup, test_cli_commands, test_accessible_responsive_ui,
        test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Collaborative Review and Research Publishing checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
