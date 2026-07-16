from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
HANDOFFS = PLUGIN / "includes" / "class-sc-library-cross-product-research-handoffs.php"
PROJECTS = PLUGIN / "includes" / "class-sc-library-connected-research-environment.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
PATHWAYS = PLUGIN / "includes" / "class-sc-library-knowledge-pathways-article-maps.php"
INTEGRITY = PLUGIN / "includes" / "class-sc-library-source-versioning-integrity.php"
JS = PLUGIN / "assets" / "js" / "sc-library-cross-product-handoffs.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-cross-product-handoffs.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, HANDOFFS, PROJECTS, EVIDENCE, SEMANTIC, PATHWAYS, INTEGRITY, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    wrapper = read(WRAPPER)
    assert "class-sc-library-knowledge-pathways-article-maps.php" in wrapper
    assert "class-sc-library-cross-product-research-handoffs.php" in wrapper
    assert wrapper.index("class-sc-library-knowledge-pathways-article-maps.php") < wrapper.index("class-sc-library-cross-product-research-handoffs.php")
    assert "new SC_Library_Cross_Product_Research_Handoffs" in wrapper
    assert "SC_LIBRARY_VERSION : '4.0.0'" in wrapper
    assert "public const VERSION = '3.4.0'" in read(HANDOFFS)


def test_schemas_and_storage():
    text = read(HANDOFFS)
    for marker in (
        "sc-platform-project-identity/1.0",
        "sc-platform-research-handoff/1.0",
        "sc-platform-handoff-history/1.0",
        "sc-platform-research-bundle/1.0",
        "sc-platform-product-registry/1.0",
        "sc_workspace_handoff",
    ):
        assert marker in text, marker


def test_product_registry():
    text = read(HANDOFFS)
    for marker in (
        "'research-lab'",
        "'workbench'",
        "'decision-studio'",
        "'research-librarian'",
        "'site-intelligence'",
        "sc_library_cross_product_registry",
        "OPTION_PRODUCT_SETTINGS",
    ):
        assert marker in text, marker


def test_research_lab_contracts():
    text = read(HANDOFFS)
    for marker in ("experiment-brief", "notebook-context", "dataset-analysis", "report-review", "experiment_context"):
        assert marker in text, marker


def test_workbench_contracts():
    text = read(HANDOFFS)
    for marker in ("calculation-context", "model-context", "visualization-context", "report-context", "calculation_context"):
        assert marker in text, marker


def test_decision_studio_contracts():
    text = read(HANDOFFS)
    for marker in ("evidence-packet", "decision-context", "scenario-context", "review-packet", "decision_context"):
        assert marker in text, marker


def test_research_librarian_contracts():
    text = read(HANDOFFS)
    for marker in (
        "research-context",
        "source-discovery",
        "pathway-context",
        "gap-analysis",
        "sc_library_research_librarian_project_context",
        "filter_research_librarian_context",
    ):
        assert marker in text, marker


def test_site_intelligence_contracts():
    text = read(HANDOFFS)
    for marker in ("dataset-reference", "saved-view", "country-context", "briefing-context", "intelligence_context"):
        assert marker in text, marker


def test_stable_project_identity():
    text = read(HANDOFFS)
    for marker in (
        "META_PROJECT_UUID",
        "META_PROJECT_URN",
        "META_PROJECT_ALIASES",
        "ensure_project_identity",
        "project_urn",
        "urn:sc:research-project:",
        "track_project_aliases",
    ):
        assert marker in text, marker


def test_identity_migration():
    text = read(HANDOFFS)
    for marker in (
        "OPTION_MIGRATION_STATE",
        "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 25",
        "run_identity_migration_batch",
        "ID > %d",
        "wp_schedule_event",
        "catch ( Throwable $error )",
    ):
        assert marker in text, marker


def test_bundle_sections_and_research_layers():
    text = read(HANDOFFS)
    for marker in (
        "'project'",
        "'bibliography'",
        "'evidence'",
        "'semantic'",
        "'pathways'",
        "'integrity'",
        "'datasets'",
        "Connected_Research_Environment::project_data",
        "Connected_Research_Environment::bibliography",
        "Evidence_Claim_Linking::project_packet",
        "Topics_Concepts_Relationships::project_coverage_report",
        "Knowledge_Pathways_Article_Maps::pathways_for_node",
        "Source_Versioning_Integrity::project_integrity_report",
    ):
        assert marker in text, marker


def test_target_specific_adapters():
    text = read(HANDOFFS)
    for marker in (
        "build_adapter_payload",
        "experiment_context",
        "calculation_context",
        "decision_context",
        "research_context",
        "intelligence_context",
        "target_contract",
    ):
        assert marker in text, marker


def test_bundle_integrity_and_exports():
    text = read(HANDOFFS)
    for marker in (
        "validate_bundle",
        "bundle_checksum",
        "hash( 'sha256'",
        "bundle_files",
        "bundle_markdown",
        "stream_bundle_zip",
        "manifest.json",
        "evidence-packet.json",
        "semantic-context.json",
        "adapter.json",
    ):
        assert marker in text, marker


def test_expiring_delivery_tokens():
    text = read(HANDOFFS)
    for marker in (
        "issue_delivery_token",
        "validate_delivery_token",
        "token_hash",
        "hash_hmac( 'sha256'",
        "wp_salt( 'auth' )",
        "DAY_IN_SECONDS",
        "TOKEN_MAX_DAYS = 30",
        "hash_equals",
    ):
        assert marker in text, marker


def test_token_not_stored_in_plaintext():
    text = read(HANDOFFS)
    assert "META_TOKEN_HASH" in text
    assert "META_TOKEN_EXPIRES" in text
    assert "_sc_handoff_token'" not in text
    assert "update_post_meta( $handoff_id, self::META_TOKEN_HASH" in text


def test_status_and_transition_model():
    text = read(HANDOFFS)
    for marker in (
        "'draft'",
        "'ready'",
        "'sent'",
        "'opened'",
        "'accepted'",
        "'in-progress'",
        "'completed'",
        "'failed'",
        "'cancelled'",
        "'archived'",
        "status_transition_map",
        "can_transition",
        "allowed_token_statuses",
    ):
        assert marker in text, marker


def test_handoff_history_and_returns():
    text = read(HANDOFFS)
    for marker in (
        "MAX_HISTORY = 200",
        "append_history",
        "receive_return_event",
        "sc_library_cross_product_return",
        "sc_library_cross_product_handoff_status_changed",
        "result_url",
        "actor_product",
    ):
        assert marker in text, marker


def test_local_product_extension_hooks():
    text = read(HANDOFFS)
    for marker in (
        "sc_library_cross_product_handoff_bundle",
        "sc_library_cross_product_handoff_created",
        "sc_library_handoff_to_",
        "local-action",
        "signed-rest",
        "export-only",
    ):
        assert marker in text, marker


def test_admin_workspace_and_project_editor():
    text = read(HANDOFFS)
    for marker in (
        "Cross-Product Research Workspace Handoffs",
        "Stable platform project identity",
        "Create a typed workspace handoff",
        "Handoff history",
        "Product registry and delivery routes",
        "Stable project identity migration",
        "data-sc-create-handoff",
        "data-sc-rotate-handoff-token",
    ):
        assert marker in text, marker


def test_ajax_security():
    text = read(HANDOFFS)
    for marker in (
        "check_ajax_referer",
        "current_user_can( $capability )",
        "current_user_can( 'edit_post', $project_id )",
        "wp_send_json_error",
        "check_admin_referer",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(HANDOFFS)
    for marker in (
        "'/platform/products'",
        "'/projects/(?P<id>\\d+)/platform-identity'",
        "'/projects/(?P<id>\\d+)/handoffs'",
        "'/handoffs/(?P<uuid>[a-f0-9-]+)'",
        "'/handoffs/(?P<uuid>[a-f0-9-]+)/status'",
        "'/handoffs/(?P<uuid>[a-f0-9-]+)/token'",
        "'/handoff-migration'",
    ):
        assert marker in text, marker


def test_private_cache_boundaries():
    text = read(HANDOFFS)
    for marker in (
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(HANDOFFS)
    assert "add_shortcode( 'sc_project_handoff_workspace'" in text
    assert "add_shortcode( 'sc_platform_project_identity'" in text
    assert "Private project workspace" in text


def test_wp_cli():
    text = read(HANDOFFS)
    for marker in (
        "sc-library handoffs products",
        "sc-library handoffs identity",
        "sc-library handoffs migrate-identities",
        "sc-library handoffs create",
        "sc-library handoffs show",
        "sc-library handoffs status",
        "sc-library handoffs bundle",
    ):
        assert marker in text, marker


def test_client_actions():
    text = read(JS)
    for marker in (
        "sc_library_v340_create_handoff",
        "sc_library_v340_rotate_token",
        "sc_library_v340_update_status",
        "sc_library_v340_run_migration",
        "refreshTypes",
        "renderDelivery",
        "navigator.clipboard",
    ):
        assert marker in text, marker


def test_accessible_responsive_styles():
    text = read(CSS)
    for marker in (
        ".sc-handoff-project-panel",
        ".sc-handoff-identity-card",
        ".sc-handoff-delivery-card",
        ".sc-handoff-workspace",
        ".sc-platform-project-identity",
        "focus-visible",
        "@media (max-width: 760px)",
        "@media print",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text


def test_retained_layers():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-connected-research-environment.php",
        "class-sc-library-connected-research-reliability.php",
        "class-sc-library-source-versioning-integrity.php",
        "class-sc-library-topics-concepts-relationships.php",
        "class-sc-library-knowledge-pathways-article-maps.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.3.0'" in read(PATHWAYS)
    assert "public const VERSION = '3.1.0'" in read(INTEGRITY)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_schemas_and_storage,
        test_product_registry,
        test_research_lab_contracts,
        test_workbench_contracts,
        test_decision_studio_contracts,
        test_research_librarian_contracts,
        test_site_intelligence_contracts,
        test_stable_project_identity,
        test_identity_migration,
        test_bundle_sections_and_research_layers,
        test_target_specific_adapters,
        test_bundle_integrity_and_exports,
        test_expiring_delivery_tokens,
        test_token_not_stored_in_plaintext,
        test_status_and_transition_model,
        test_handoff_history_and_returns,
        test_local_product_extension_hooks,
        test_admin_workspace_and_project_editor,
        test_ajax_security,
        test_rest_routes,
        test_private_cache_boundaries,
        test_shortcodes,
        test_wp_cli,
        test_client_actions,
        test_accessible_responsive_styles,
        test_retained_layers,
    ]
    for test in tests:
        test()
    print(f"Cross-Product Research Workspace Handoffs checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
