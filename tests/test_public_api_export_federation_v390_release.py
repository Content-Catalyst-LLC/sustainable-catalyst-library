from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
HARDENING = PLUGIN / "includes" / "class-sc-library-public-api-export-federation.php"
REVIEW = PLUGIN / "includes" / "class-sc-library-collaborative-review-publishing.php"
INTEL = PLUGIN / "includes" / "class-sc-library-research-librarian-document-intelligence.php"
JS = PLUGIN / "assets" / "js" / "sc-library-public-api-export-federation.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-public-api-export-federation.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, HARDENING, REVIEW, INTEL, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    wrapper = read(WRAPPER)
    assert "class-sc-library-public-api-export-federation.php" in wrapper
    assert "new SC_Library_Public_API_Export_Federation" in wrapper
    assert wrapper.index("class-sc-library-collaborative-review-publishing.php") < wrapper.index("class-sc-library-public-api-export-federation.php")
    assert "SC_LIBRARY_VERSION : '3.9.0'" in wrapper
    assert "public const VERSION = '3.9.0'" in read(HARDENING)


def test_schemas():
    text = read(HARDENING)
    for marker in (
        "sc-library-public-api/1.0",
        "sc-library-api-capabilities/1.0",
        "sc-library-export-manifest/1.0",
        "sc-library-federation-node/1.0",
        "sc-library-federation-peer/1.0",
        "sc-library-signed-webhook/1.0",
        "sc-library-federation-import/1.0",
        "sc-library-api-audit/1.0",
        "sc-platform-handoff/public-api-export-federation/1.0",
    ):
        assert marker in text, marker


def test_record_types():
    text = read(HARDENING)
    for marker in (
        "sc_api_token", "sc_export_job", "sc_federation_peer",
        "sc_federation_hook", "sc_federation_import",
        "register_record_types",
    ):
        assert marker in text, marker


def test_token_scopes():
    text = read(HARDENING)
    for marker in (
        "catalog:read", "documents:read", "projects:read",
        "exports:create", "exports:read", "federation:read",
        "federation:import", "webhooks:manage", "admin:read",
    ):
        assert marker in text, marker


def test_token_hardening():
    text = read(HARDENING)
    for marker in (
        "TOKEN_BYTES = 32", "random_bytes", "hash( 'sha256'",
        "META_TOKEN_HASH", "META_TOKEN_PREFIX", "META_TOKEN_SCOPES",
        "META_TOKEN_EXPIRES", "META_TOKEN_REVOKED",
        "META_TOKEN_LAST_USED", "META_TOKEN_RATE_LIMIT",
        "Bearer", "api_token_invalid", "api_token_expired",
        "api_scope_forbidden",
    ):
        assert marker in text, marker


def test_rate_limits():
    text = read(HARDENING)
    for marker in (
        "DEFAULT_RATE_LIMIT = 120", "MAX_RATE_LIMIT = 5000",
        "enforce_rate_limit", "api_rate_limit_exceeded",
        "retry_after", "reset_seconds", "remaining",
    ):
        assert marker in text, marker


def test_export_formats():
    text = read(HARDENING)
    for marker in ("'json'", "'jsonld'", "'ndjson'", "'csv'", "'bundle'"):
        assert marker in text, marker


def test_export_scopes():
    text = read(HARDENING)
    for marker in (
        "'documents'", "'projects'", "'sources'", "'pathways'",
        "'collections'", "'publications'", "'catalog'",
    ):
        assert marker in text, marker


def test_export_jobs():
    text = read(HARDENING)
    for marker in (
        "create_export_job", "run_export_batch", "export_record_batch",
        "finalize_export", "write_export_file", "export_job_data",
        "EXPORT_BATCH = 100", "MAX_EXPORT_RECORDS = 50000",
        "META_EXPORT_CURSOR", "META_EXPORT_TOTAL",
        "META_EXPORT_PROCESSED", "META_EXPORT_MANIFEST",
        "META_EXPORT_FILE", "META_EXPORT_EXPIRES_AT",
    ):
        assert marker in text, marker


def test_export_formats_behavior():
    text = read(HARDENING)
    for marker in (
        "application/ld+json", "application/x-ndjson", "text/csv",
        "ZipArchive", "manifest.json", "records.json",
        "records.ndjson", "README.txt", "fputcsv",
    ):
        assert marker in text, marker


def test_deterministic_manifests():
    text = read(HARDENING)
    for marker in (
        "canonical_json", "recursive_sort", "ksort",
        "record_hashes", "records_sha256", "manifest_sha256",
        "content_hash", "sha256",
    ):
        assert marker in text, marker


def test_private_export_storage():
    text = read(HARDENING)
    for marker in (
        "sc-library-private-exports", "index.php", ".htaccess",
        "Deny from all", "no-store, private, max-age=0",
        "realpath", "Content-Disposition",
    ):
        assert marker in text, marker


def test_public_catalog():
    text = read(HARDENING)
    for marker in (
        "public_catalog", "serialize_record", "public_terms",
        "public_source_file", "next_cursor", "opaque-cursor",
        "encode_cursor", "decode_cursor", "maximum_limit",
    ):
        assert marker in text, marker


def test_redaction_boundaries():
    text = read(HARDENING)
    for marker in (
        "include_private", "raw_content", "author_id",
        "redact_audit_context", "[redacted]",
        "authorization", "private_key", "request_ip_hash",
    ):
        assert marker in text, marker


def test_capability_discovery():
    text = read(HARDENING)
    for marker in (
        "capabilities", "CAPABILITY_SCHEMA", "api_version",
        "content_types", "conditional_get", "peer_governance",
        "import_quarantine", "signed_webhooks",
    ):
        assert marker in text, marker


def test_conditional_requests():
    text = read(HARDENING)
    for marker in (
        "harden_rest_response", "ETag", "If-None-Match".lower(),
        "stale-while-revalidate", "X-Content-Type-Options",
        "Referrer-Policy", "X-SC-API-Version",
        "X-SC-Plugin-Version", "Vary",
    ):
        assert marker.lower() in text.lower(), marker


def test_federation_node():
    text = read(HARDENING)
    for marker in (
        "OPTION_NODE", "node_data", "node_id", "base_url",
        "api_url", "capabilities", "Federation node discovery is not public",
    ):
        assert marker in text, marker


def test_peer_governance():
    text = read(HARDENING)
    for marker in (
        "peer_statuses", "trust_levels", "'untrusted'", "'discovery'",
        "'metadata'", "'verified'", "'degraded'", "'suspended'",
        "'blocked'", "check_peer", "safe_federation_url",
        "wp_safe_remote_get", "redirection", "incompatible capability",
    ):
        assert marker in text, marker


def test_ssrf_protection():
    text = read(HARDENING)
    for marker in (
        "https", "localhost", "127.0.0.1", "::1",
        "FILTER_FLAG_NO_PRIV_RANGE", "FILTER_FLAG_NO_RES_RANGE",
        "private_ip",
    ):
        assert marker in text, marker


def test_signed_webhooks():
    text = read(HARDENING)
    for marker in (
        "queue_event", "run_webhook_queue", "deliver_webhook_batch",
        "hash_hmac( 'sha256'", "X-SC-Webhook-ID",
        "X-SC-Webhook-Timestamp", "X-SC-Webhook-Signature",
        "attempts", "next_attempt", "pow( 2", "redirection' => 0",
        "MAX_WEBHOOK_QUEUE = 200",
    ):
        assert marker in text, marker


def test_import_quarantine():
    text = read(HARDENING)
    for marker in (
        "quarantine_import", "validate_import_payload", "decide_import",
        "MAX_IMPORT_BYTES = 5242880", "'quarantined'", "'rejected'",
        "'approved-metadata'", "automatic_import_allowed",
        "automatic_content_import", "peer-trust-insufficient",
    ):
        assert marker in text, marker


def test_audit_logging():
    text = read(HARDENING)
    for marker in (
        "audit(", "OPTION_AUDIT", "MAX_AUDIT = 500",
        "audit_id", "ip_hash", "wp_salt( 'auth' )",
        "token-created", "export-complete", "peer-check-success",
        "webhook-delivered", "federation-import-quarantined",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(HARDENING)
    for marker in (
        "Public API, Export, and Federation Hardening",
        "API, Export & Federation", "Federation Peers",
        "Federation Webhooks", "render_workspace",
        "render_peer_meta_box", "render_webhook_meta_box",
        "Issue scoped API token", "Create export job",
        "Quarantined imports",
    ):
        assert marker in text, marker


def test_dashboard():
    text = read(HARDENING)
    for marker in (
        "dashboard_report", "active_tokens", "export_count",
        "queued_exports", "complete_exports", "peer_count",
        "active_peers", "webhook_count", "import_count",
        "quarantined_imports",
    ):
        assert marker in text, marker


def test_migration():
    text = read(HARDENING)
    for marker in (
        "OPTION_MIGRATION", "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 20", "LOCK_SECONDS = 180",
        "run_migration_batch", "ID > %d",
        "catch ( Throwable $error )", "wp_schedule_event",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(HARDENING)
    for marker in (
        "'/capabilities'", "'/catalog'", "'/catalog/(?P<type>[a-z\\-]+)'",
        "'/records/(?P<type>[a-z\\-]+)/(?P<id>\\d+)'",
        "'/exports'", "'/exports/(?P<id>\\d+)'",
        "'/exports/(?P<id>\\d+)/download'",
        "'/federation/node'", "'/federation/peers'",
        "'/federation/peers/(?P<id>\\d+)/check'",
        "'/federation/imports'", "'/federation/imports/(?P<id>\\d+)'",
        "'/api-export-federation/dashboard'",
        "'/api-export-federation/migration'",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(HARDENING)
    js = read(JS)
    for marker in (
        "sc_library_v390_create_token", "sc_library_v390_create_export",
        "sc_library_v390_run_export", "sc_library_v390_run_migration",
        "sc_library_v390_check_peer",
    ):
        assert marker in text, marker
    for marker in (
        "sc_library_v390_create_token", "sc_library_v390_create_export",
        "sc_library_v390_run_migration", "sc_library_v390_check_peer",
    ):
        assert marker in js, marker


def test_shortcodes():
    text = read(HARDENING)
    for marker in (
        "add_shortcode( 'sc_library_api_capabilities'",
        "add_shortcode( 'sc_library_public_catalog'",
        "add_shortcode( 'sc_library_federation_status'",
        "add_shortcode( 'sc_library_export_register'",
    ):
        assert marker in text, marker


def test_handoffs():
    text = read(HARDENING)
    for marker in (
        "sc_library_research_librarian_project_context",
        "sc_library_cross_product_handoff_sections",
        "filter_project_context", "filter_handoff_sections",
        "public_api_export_federation", "HANDOFF_SCHEMA",
    ):
        assert marker in text, marker


def test_cli_commands():
    text = read(HARDENING)
    for marker in (
        "sc-library api token-create", "sc-library api token-revoke",
        "sc-library export create", "sc-library export run",
        "sc-library federation peer-check",
        "sc-library federation import-decide",
        "sc-library api migrate", "sc-library api dashboard",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-api-center", ".sc-api-tools", ".sc-api-public-panel",
        ".sc-api-public-catalog", ".sc-api-token-value",
        "@media (max-width: 700px)", "@media print", "focus-visible",
    ):
        assert marker in css, marker
    assert "escapeHtml" in js
    assert "selectedValues" in js
    assert "aria-live" in read(HARDENING)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    assert "class-sc-library-collaborative-review-publishing.php" in wrapper
    assert "class-sc-library-research-librarian-document-intelligence.php" in wrapper
    assert "public const VERSION = '3.8.0'" in read(REVIEW)
    assert "public const VERSION = '3.7.0'" in read(INTEL)


def main():
    tests = [
        test_required_files, test_load_order_and_version, test_schemas,
        test_record_types, test_token_scopes, test_token_hardening,
        test_rate_limits, test_export_formats, test_export_scopes,
        test_export_jobs, test_export_formats_behavior,
        test_deterministic_manifests, test_private_export_storage,
        test_public_catalog, test_redaction_boundaries,
        test_capability_discovery, test_conditional_requests,
        test_federation_node, test_peer_governance,
        test_ssrf_protection, test_signed_webhooks,
        test_import_quarantine, test_audit_logging, test_admin_center,
        test_dashboard, test_migration, test_rest_routes,
        test_ajax_actions, test_shortcodes, test_handoffs,
        test_cli_commands, test_accessible_responsive_ui,
        test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Public API, Export, and Federation Hardening checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
