from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
QUALITY = PLUGIN / "includes" / "class-sc-library-research-quality-governance.php"
HANDOFFS = PLUGIN / "includes" / "class-sc-library-cross-product-research-handoffs.php"
ENVIRONMENT = PLUGIN / "includes" / "class-sc-library-connected-research-environment.php"
INTEGRITY = PLUGIN / "includes" / "class-sc-library-source-versioning-integrity.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
PATHWAYS = PLUGIN / "includes" / "class-sc-library-knowledge-pathways-article-maps.php"
JS = PLUGIN / "assets" / "js" / "sc-library-research-quality-governance.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-research-quality-governance.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, QUALITY, HANDOFFS, ENVIRONMENT, INTEGRITY, SEMANTIC, PATHWAYS, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    text = read(WRAPPER)
    assert "class-sc-library-research-quality-governance.php" in text
    assert "new SC_Library_Research_Quality_Governance" in text
    assert text.index("class-sc-library-cross-product-research-handoffs.php") < text.index("class-sc-library-research-quality-governance.php")
    assert "SC_LIBRARY_VERSION : '3.9.0'" in text
    assert "public const VERSION = '3.5.0'" in read(QUALITY)


def test_schemas():
    text = read(QUALITY)
    for marker in (
        "sc-library-research-quality/1.0",
        "sc-library-governance-policy/1.0",
        "sc-library-quality-review/1.0",
        "sc-library-quality-issue/1.0",
        "sc-library-research-transparency/1.0",
        "sc-library-governance-dashboard/1.0",
    ):
        assert marker in text, marker


def test_record_types():
    text = read(QUALITY)
    for marker in (
        "sc_research_policy",
        "sc_quality_review",
        "sc_quality_issue",
        "register_record_types",
        "Research Policies",
        "Quality Reviews",
        "Quality Issues",
    ):
        assert marker in text, marker


def test_governance_profiles():
    text = read(QUALITY)
    for marker in (
        "'exploratory'",
        "'standard'",
        "'high-assurance'",
        "'public-release'",
        "'institutional'",
    ):
        assert marker in text, marker


def test_review_gates():
    text = read(QUALITY)
    for marker in (
        "'draft'",
        "'internal-review'",
        "'quality-review'",
        "'conditional'",
        "'approved'",
        "'published'",
        "'archived'",
        "transition_gate",
        "quality_gate_blocked",
        "quality_gate_critical",
    ):
        assert marker in text, marker


def test_review_domains():
    text = read(QUALITY)
    for marker in (
        "'methodology'",
        "'evidence'",
        "'citation'",
        "'provenance'",
        "'integrity'",
        "'ethics'",
        "'privacy'",
        "'legal'",
        "'accessibility'",
        "'publication'",
        "'reproducibility'",
        "'cross-product'",
    ):
        assert marker in text, marker


def test_review_outcomes_and_issues():
    text = read(QUALITY)
    for marker in (
        "'pending'",
        "'pass'",
        "'conditional'",
        "'fail'",
        "'waived'",
        "'open'",
        "'in-review'",
        "'mitigated'",
        "'accepted'",
        "'resolved'",
        "'closed'",
        "'low'",
        "'medium'",
        "'high'",
        "'critical'",
    ):
        assert marker in text, marker


def test_quality_dimensions():
    text = read(QUALITY)
    for marker in (
        "'research-design'",
        "'sources'",
        "'evidence'",
        "'provenance'",
        "'semantics'",
        "'pathways'",
        "'handoffs'",
        "'governance'",
        "evaluate_research_design",
        "evaluate_sources",
        "evaluate_evidence",
        "evaluate_provenance",
        "evaluate_semantics",
        "evaluate_pathways",
        "evaluate_handoffs",
        "evaluate_governance",
    ):
        assert marker in text, marker


def test_scoring_and_readiness():
    text = read(QUALITY)
    for marker in (
        "readiness_status",
        "'blocked'",
        "'not-ready'",
        "'needs-review'",
        "'conditionally-ready'",
        "'ready'",
        "$score < 50",
        "$score < 70",
        "$score < 85",
        "maximum_points",
        "earned_points",
    ):
        assert marker in text, marker


def test_review_records():
    text = read(QUALITY)
    for marker in (
        "create_review",
        "review_data",
        "project_review_records",
        "META_PROJECT_REVIEW_IDS",
        "META_RECORD_REVIEWER",
        "META_RECORD_FINDINGS",
        "META_RECORD_ACTIONS",
        "META_RECORD_COMPLETED",
    ):
        assert marker in text, marker


def test_issue_exception_records():
    text = read(QUALITY)
    for marker in (
        "create_issue",
        "issue_data",
        "project_issue_records",
        "META_PROJECT_ISSUE_IDS",
        "META_PROJECT_EXCEPTION_IDS",
        "META_RECORD_EXCEPTION",
        "META_RECORD_EXCEPTION_EXPIRY",
        "META_RECORD_EXCEPTION_APPROVER",
    ):
        assert marker in text, marker


def test_policy_model():
    text = read(QUALITY)
    for marker in (
        "META_POLICY_DOMAIN",
        "META_POLICY_VERSION",
        "META_POLICY_STATUS",
        "META_POLICY_GATE",
        "META_POLICY_CONTROLS",
        "META_POLICY_PUBLIC",
        "META_POLICY_EFFECTIVE",
        "META_POLICY_REVIEW_DATE",
        "META_POLICY_OWNER",
        "save_policy",
        "render_policy_meta_box",
    ):
        assert marker in text, marker


def test_approval_history():
    text = read(QUALITY)
    for marker in (
        "META_PROJECT_HISTORY",
        "META_RECORD_HISTORY",
        "append_project_history",
        "wp_generate_uuid4",
        "gate-transition",
        "quality-evaluation",
        "governance-settings-updated",
    ):
        assert marker in text, marker


def test_handoff_integration():
    text = read(QUALITY)
    for marker in (
        "sc_library_cross_product_handoff_bundle",
        "filter_handoff_bundle",
        "$bundle['quality_governance']",
        "readiness_status",
        "critical_issues",
        "failed_reviews",
    ):
        assert marker in text, marker


def test_retained_platform_integrations():
    text = read(QUALITY)
    for marker in (
        "SC_Library_Connected_Research_Environment",
        "SC_Library_Source_Versioning_Integrity",
        "SC_Library_Topics_Concepts_Relationships",
        "SC_Library_Knowledge_Pathways_Article_Maps",
        "SC_Library_Cross_Product_Research_Handoffs",
        "SC_Library_Evidence_Claim_Linking",
    ):
        assert marker in text, marker


def test_public_transparency():
    text = read(QUALITY)
    for marker in (
        "project_transparency",
        "render_transparency_summary",
        "Research transparency summary",
        "This summary describes process readiness",
        "META_PROJECT_PUBLIC",
        "META_POLICY_PUBLIC",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(QUALITY)
    for marker in (
        "Research Quality and Governance Center",
        "Quality & Governance",
        "Project readiness register",
        "Governance alerts",
        "Governance profile migration",
        "render_workspace",
        "render_project_meta_box",
        "render_project_readiness_box",
    ):
        assert marker in text, marker


def test_dashboard():
    text = read(QUALITY)
    for marker in (
        "dashboard_report",
        "project_count",
        "ready_count",
        "blocked_count",
        "open_issue_count",
        "overdue_count",
        "average_score",
        "high_risk_issues",
        "policy_alerts",
    ):
        assert marker in text, marker


def test_resumable_migration():
    text = read(QUALITY)
    for marker in (
        "OPTION_MIGRATION",
        "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 20",
        "LOCK_SECONDS = 180",
        "run_migration_batch",
        "ID > %d",
        "catch ( Throwable $error )",
        "wp_schedule_event",
        "META_PROJECT_MIGRATED",
    ):
        assert marker in text, marker


def test_stale_review_scan():
    text = read(QUALITY)
    for marker in (
        "CRON_STALE_REVIEW",
        "run_stale_review_scan",
        "daily",
        "due_date",
        "overdue_records",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(QUALITY)
    for marker in (
        "add_shortcode( 'sc_research_quality'",
        "add_shortcode( 'sc_research_governance'",
        "add_shortcode( 'sc_research_transparency'",
        "add_shortcode( 'sc_research_governance_dashboard'",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(QUALITY)
    for marker in (
        "'/projects/(?P<id>\\d+)/quality'",
        "'/projects/(?P<id>\\d+)/governance'",
        "'/projects/(?P<id>\\d+)/reviews'",
        "'/projects/(?P<id>\\d+)/issues'",
        "'/projects/(?P<id>\\d+)/gate'",
        "'/projects/(?P<id>\\d+)/transparency'",
        "'/governance/dashboard'",
        "'/governance/migration'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(QUALITY)
    for marker in (
        "rest_can_read_project",
        "rest_can_edit_project",
        "rest_can_read_transparency",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(QUALITY)
    js = read(JS)
    for marker in (
        "sc_library_v350_evaluate_project",
        "sc_library_v350_create_review",
        "sc_library_v350_create_issue",
        "sc_library_v350_transition_gate",
        "sc_library_v350_run_migration",
    ):
        assert marker in text, marker
        assert marker in js, marker


def test_cli_commands():
    text = read(QUALITY)
    for marker in (
        "sc-library quality evaluate",
        "sc-library quality review",
        "sc-library quality issue",
        "sc-library quality gate",
        "sc-library quality dashboard",
        "sc-library quality migrate",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_deletion_cleanup():
    text = read(QUALITY)
    for marker in (
        "cleanup_deleted_record",
        "before_delete_post",
        "META_PROJECT_REVIEW_IDS",
        "META_PROJECT_ISSUE_IDS",
        "META_PROJECT_EXCEPTION_IDS",
        "META_PROJECT_POLICIES",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-governance-center",
        ".sc-quality-evaluation",
        ".sc-research-transparency",
        ".sc-quality-dialog",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in css, marker
    assert "showModal" in js
    assert "aria-live" in read(QUALITY)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-source-versioning-integrity.php",
        "class-sc-library-topics-concepts-relationships.php",
        "class-sc-library-knowledge-pathways-article-maps.php",
        "class-sc-library-cross-product-research-handoffs.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.4.0'" in read(HANDOFFS)
    assert "public const VERSION = '3.3.0'" in read(PATHWAYS)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_schemas,
        test_record_types,
        test_governance_profiles,
        test_review_gates,
        test_review_domains,
        test_review_outcomes_and_issues,
        test_quality_dimensions,
        test_scoring_and_readiness,
        test_review_records,
        test_issue_exception_records,
        test_policy_model,
        test_approval_history,
        test_handoff_integration,
        test_retained_platform_integrations,
        test_public_transparency,
        test_admin_center,
        test_dashboard,
        test_resumable_migration,
        test_stale_review_scan,
        test_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_ajax_actions,
        test_cli_commands,
        test_deletion_cleanup,
        test_accessible_responsive_ui,
        test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Research Quality and Governance Center checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
