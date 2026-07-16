from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
PLATFORM = PLUGIN / "includes" / "class-sc-library-connected-institutional-platform.php"
API = PLUGIN / "includes" / "class-sc-library-public-api-export-federation.php"
REVIEW = PLUGIN / "includes" / "class-sc-library-collaborative-review-publishing.php"
INTELLIGENCE = PLUGIN / "includes" / "class-sc-library-research-librarian-document-intelligence.php"
JS = PLUGIN / "assets" / "js" / "sc-library-connected-institutional-platform.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-connected-institutional-platform.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, PLATFORM, API, REVIEW, INTELLIGENCE, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    wrapper = read(WRAPPER)
    assert "class-sc-library-connected-institutional-platform.php" in wrapper
    assert "new SC_Library_Connected_Institutional_Platform" in wrapper
    assert wrapper.index("class-sc-library-public-api-export-federation.php") < wrapper.index("class-sc-library-connected-institutional-platform.php")
    assert "SC_LIBRARY_VERSION : '4.0.0'" in wrapper
    assert "public const VERSION = '4.0.0'" in read(PLATFORM)


def test_platform_schemas():
    text = read(PLATFORM)
    for marker in (
        "sc-library-connected-institutional-platform/1.0",
        "sc-library-institutional-record-registry/1.0",
        "sc-library-institutional-record/1.0",
        "sc-library-institutional-search/1.0",
        "sc-library-institutional-knowledge-graph/1.0",
        "sc-library-institutional-workspace/1.0",
        "sc-library-institutional-health/1.0",
        "sc-library-institutional-permissions/1.0",
        "sc-platform-handoff/institutional-research/1.0",
        "sc-library-institutional-research-portal/1.0",
        "sc-library-institutional-migration/1.0",
    ):
        assert marker in text, marker


def test_institutional_records():
    text = read(PLATFORM)
    for marker in (
        "sc_institution", "sc_research_unit", "register_record_types",
        "Institution Profile", "Research Unit Profile", "institution_statuses",
        "unit_types", "research-center", "program", "lab", "library",
        "advisory", "publication", "platform",
    ):
        assert marker in text, marker


def test_capability_model():
    text = read(PLATFORM)
    for marker in (
        "sc_library_read_institutional",
        "sc_library_manage_institutional",
        "sc_library_manage_institutional_records",
        "sc_library_publish_institutional",
        "sc_library_manage_institutional_permissions",
        "sc_library_manage_institutional_handoffs",
        "sc_library_view_institutional_health",
        "sc_library_export_institutional",
        "sync_capabilities", "administrator", "editor", "author",
    ):
        assert marker in text, marker


def test_visibility_and_governance():
    text = read(PLATFORM)
    for marker in (
        "'public'", "'institution'", "'unit'", "'restricted'",
        "'unclassified'", "'draft'", "'managed'", "'review'",
        "'approved'", "'published'", "'archived'",
        "META_VISIBILITY", "META_GOVERNANCE_STATE", "META_STEWARD_ID",
    ):
        assert marker in text, marker


def test_record_registry_types():
    text = read(PLATFORM)
    for marker in (
        "'document'", "'source'", "'claim'", "'evidence-note'",
        "'project'", "'concept'", "'entity'", "'vocabulary'",
        "'relationship'", "'pathway'", "'collection'",
        "'archive-component'", "'accession'", "'review'",
        "'publication'", "'quality-policy'", "'quality-issue'",
        "'workspace-handoff'", "'api-export'", "'federation-peer'",
        "'institution'", "'unit'",
    ):
        assert marker in text, marker


def test_record_identity():
    text = read(PLATFORM)
    for marker in (
        "ensure_record_identity", "ensure_uuid_meta", "urn:sc-library:",
        "META_RECORD_URN", "META_REGISTRY_HASH", "META_REGISTERED_AT",
        "META_UPDATED_AT", "register_record", "canonical_json",
        "hash( 'sha256'",
    ):
        assert marker in text, marker


def test_unified_record_serialization():
    text = read(PLATFORM)
    for marker in (
        "serialize_record", "type_label", "visibility", "governance_state",
        "institution_id", "institution", "unit_ids", "units",
        "published_at", "modified_at", "content_hash", "registry_hash",
        "document_intelligence", "raw_content", "steward_id",
    ):
        assert marker in text, marker


def test_search_contract():
    text = read(PLATFORM)
    for marker in (
        "institutional_search", "DEFAULT_SEARCH_LIMIT = 25",
        "MAX_SEARCH_LIMIT = 100", "opaque", "next_cursor",
        "facets", "institution_id", "unit_id", "governance",
        "encode_cursor", "decode_cursor", "stable_etag_data",
    ):
        assert marker.lower() in text.lower(), marker


def test_graph_contract():
    text = read(PLATFORM)
    for marker in (
        "knowledge_graph", "MAX_GRAPH_NODES = 250", "graph_node",
        "semantic_edges_for_node", "institutional_edges_for_node",
        "governed-by-institution", "managed-by-unit",
        "part-of-institution", "graph_sha256", "truncated",
        "META_RELATION_FROM_KIND", "META_RELATION_TO_KIND",
    ):
        assert marker in text, marker


def test_health_center():
    text = read(PLATFORM)
    for marker in (
        "health_report", "refresh_health_cache", "component_count",
        "plugin_version", "cron_migration", "cron_health",
        "record_registry", "SC_Library_Public_API_Export_Federation",
        "SC_Library_Collaborative_Review_Publishing",
        "SC_Library_Research_Librarian_Document_Intelligence",
        "SC_Library_Institutional_Collections_Archives",
        "SC_Library_Research_Quality_Governance",
    ):
        assert marker in text, marker


def test_workspace_dashboard():
    text = read(PLATFORM)
    for marker in (
        "workspace_report", "total_records", "institution_count",
        "unit_count", "document_count", "project_count",
        "publication_count", "governance", "visibility", "activity",
        "Institutional Platform", "Connected Institutional Knowledge and Research Platform",
    ):
        assert marker in text, marker


def test_handoff_envelope():
    text = read(PLATFORM)
    for marker in (
        "build_handoff_envelope", "create_platform_handoff",
        "source_product", "source_version", "target_product",
        "handoff_type", "institutions", "units", "records",
        "checksum", "SC_Library_Cross_Product_Research_Handoffs::create_handoff",
        "institutional_envelope",
    ):
        assert marker in text, marker


def test_research_librarian_and_product_context():
    text = read(PLATFORM)
    for marker in (
        "sc_library_research_librarian_project_context",
        "sc_library_cross_product_handoff_sections",
        "sc_library_public_api_record",
        "filter_research_librarian_context",
        "filter_handoff_sections", "filter_public_api_record",
        "institutional_platform", "public_api_record",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(PLATFORM)
    for marker in (
        "'sc-library'", "Institutional Platform", "Institutions",
        "Research Units", "Unified institutional search",
        "Cross-product institutional handoff", "Institutional registry migration",
        "Institutional registry", "Governance states", "Visibility",
        "render_workspace", "render_record_context_meta_box",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(PLATFORM)
    js = read(JS)
    for marker in (
        "sc_library_v400_run_migration", "sc_library_v400_refresh_health",
        "sc_library_v400_search", "sc_library_v400_create_handoff",
    ):
        assert marker in text, marker
        assert marker in js, marker


def test_rest_routes():
    text = read(PLATFORM)
    for marker in (
        "'/institutional/platform'", "'/institutional/health'",
        "'/institutional/registry'", "'/institutional/search'",
        "'/institutional/records/(?P<type>[a-z\\-]+)/(?P<id>\\d+)'",
        "'/institutional/graph'", "'/institutional/handoffs'",
        "'/institutional/dashboard'", "'/institutional/permissions'",
        "'/institutional/migration'",
    ):
        assert marker in text, marker


def test_rest_hardening():
    text = read(PLATFORM)
    for marker in (
        "harden_rest_responses", "ETag", "If-None-Match".lower(),
        "stale-while-revalidate", "no-store, no-cache, must-revalidate",
        "X-Content-Type-Options", "Referrer-Policy",
        "X-SC-Platform-Version", "Cookie, Authorization",
    ):
        assert marker.lower() in text.lower(), marker


def test_public_portal_and_shortcodes():
    text = read(PLATFORM)
    for marker in (
        "add_shortcode( 'sc_institutional_research_portal'",
        "add_shortcode( 'sc_institutional_search'",
        "add_shortcode( 'sc_institutional_platform_status'",
        "add_shortcode( 'sc_institutional_record'",
        "Discover public documents", "Public research records",
        "Institutional knowledge search", "Institutional platform status",
    ):
        assert marker in text, marker


def test_migration():
    text = read(PLATFORM)
    for marker in (
        "OPTION_MIGRATION", "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 25", "LOCK_SECONDS = 180",
        "run_migration_batch", "ID > %d", "catch ( Throwable $error )",
        "default_institution", "register_record( $post_id, true )",
        "wp_schedule_event",
    ):
        assert marker in text, marker


def test_cleanup_and_activity():
    text = read(PLATFORM)
    for marker in (
        "cleanup_deleted_record", "before_delete_post", "activity(",
        "OPTION_ACTIVITY", "MAX_ACTIVITY = 500", "invalidate_caches",
        "delete_post_meta", "META_UNIT_IDS", "META_INSTITUTION_ID",
    ):
        assert marker in text, marker


def test_cli_commands():
    text = read(PLATFORM)
    for marker in (
        "sc-library institutional health",
        "sc-library institutional registry",
        "sc-library institutional record",
        "sc-library institutional search",
        "sc-library institutional graph",
        "sc-library institutional handoff",
        "sc-library institutional migrate",
        "sc-library institutional dashboard",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-inst-center", ".sc-inst-metrics", ".sc-inst-tools",
        ".sc-inst-record-grid", ".sc-inst-public-portal",
        ".sc-inst-public-search", ".sc-inst-public-status",
        "@media (max-width: 700px)", "@media print", "focus-visible",
    ):
        assert marker in css, marker
    assert "escapeHtml" in js
    assert "selectedValues" in js
    assert "aria-live" in read(PLATFORM)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    assert "class-sc-library-public-api-export-federation.php" in wrapper
    assert "class-sc-library-collaborative-review-publishing.php" in wrapper
    assert "class-sc-library-research-librarian-document-intelligence.php" in wrapper
    assert "public const VERSION = '3.9.0'" in read(API)
    assert "public const VERSION = '3.8.0'" in read(REVIEW)
    assert "public const VERSION = '3.7.0'" in read(INTELLIGENCE)


def main():
    tests = [
        test_required_files, test_load_order_and_version, test_platform_schemas,
        test_institutional_records, test_capability_model,
        test_visibility_and_governance, test_record_registry_types,
        test_record_identity, test_unified_record_serialization,
        test_search_contract, test_graph_contract, test_health_center,
        test_workspace_dashboard, test_handoff_envelope,
        test_research_librarian_and_product_context, test_admin_center,
        test_ajax_actions, test_rest_routes, test_rest_hardening,
        test_public_portal_and_shortcodes, test_migration,
        test_cleanup_and_activity, test_cli_commands,
        test_accessible_responsive_ui, test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Connected Institutional Knowledge and Research Platform checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
