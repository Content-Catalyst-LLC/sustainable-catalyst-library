from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
CONNECTORS = PLUGIN / "includes" / "class-sc-library-scholarly-library-connectors.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-connector-holdings-reliability.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
SOURCE_RELIABILITY = PLUGIN / "includes" / "class-sc-library-citation-source-reliability.php"
JS = PLUGIN / "assets" / "js" / "sc-library-connectors.js"
RELIABILITY_JS = PLUGIN / "assets" / "js" / "sc-library-connector-reliability.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-connector-reliability.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, CONNECTORS, RELIABILITY, MANAGER, SOURCE_RELIABILITY, JS, RELIABILITY_JS, CSS):
        assert path.is_file(), path


def test_reliability_layer_loads_after_connectors():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "new SC_Library_Connector_Holdings_Reliability",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-scholarly-library-connectors.php") < text.index("class-sc-library-connector-holdings-reliability.php")


def test_reliability_schemas_and_version():
    text = read(RELIABILITY)
    for marker in (
        "public const VERSION = '2.6.1'",
        "sc-library-connector-health/1.0",
        "sc-library-holdings-reliability/1.0",
        "sc-library-connector-conflict/1.0",
        "sc-library-profile-validation/1.0",
    ):
        assert marker in text, marker


def test_connector_transport_delegates_to_reliability_layer():
    connectors = read(CONNECTORS)
    assert "SC_Library_Connector_Holdings_Reliability::request_json" in connectors
    assert "wp_safe_remote_get" not in connectors[connectors.index("private function request_json"):connectors.index("private function search_crossref")]


def test_https_host_allowlist_and_bounded_transport():
    text = read(RELIABILITY)
    for marker in (
        "validate_provider_url",
        "api.crossref.org",
        "api.openalex.org",
        "api.datacite.org",
        "eutils.ncbi.nlm.nih.gov",
        "www.loc.gov",
        "openlibrary.org",
        "www.googleapis.com",
        "api.unpaywall.org",
        "wp_safe_remote_get",
        "'redirection'         => 2",
        "'limit_response_size' => 5 * 1024 * 1024",
    ):
        assert marker in text, marker


def test_bounded_retries_and_retry_after():
    text = read(RELIABILITY)
    for marker in (
        "$max_attempts = 3",
        "short_retry_delay",
        "retry_after_seconds",
        "408, 425, 429, 500, 502, 503, 504",
        "$retry_after <= 2",
        "usleep",
    ):
        assert marker in text, marker


def test_conditional_requests_and_304_recovery():
    text = read(RELIABILITY)
    for marker in (
        "If-None-Match",
        "If-Modified-Since",
        "last-modified",
        "304 === $status",
        "connector_304_cache_miss",
        "unset( $headers['If-None-Match']",
        "HTTP_BODY_TTL",
    ):
        assert marker in text, marker


def test_rate_limit_and_concurrency_header_capture():
    text = read(RELIABILITY)
    for marker in (
        "x-rate-limit-limit",
        "x-rate-limit-interval",
        "x-rate-limit-remaining",
        "x-rate-limit-reset",
        "x-concurrency-limit",
        "ratelimit-limit",
        "ratelimit-remaining",
        "ratelimit-reset",
    ):
        assert marker in text, marker


def test_health_registry_and_circuit_breaker():
    text = read(RELIABILITY)
    for marker in (
        "OPTION_HEALTH",
        "CIRCUIT_THRESHOLD = 3",
        "CIRCUIT_COOLDOWN",
        "provider_request_state",
        "record_success",
        "record_failure",
        "'status' = 'open'",
        "'half-open'",
        "cooldown_until",
        "consecutive_failures",
        "average_latency_ms",
    ):
        if marker == "'status' = 'open'":
            assert "$state['status'] = 'open'" in text
        else:
            assert marker in text, marker


def test_health_event_history_is_bounded():
    text = read(RELIABILITY)
    assert "MAX_HEALTH_EVENTS = 25" in text
    assert "array_slice( $events, -self::MAX_HEALTH_EVENTS )" in text


def test_stale_search_cache_recovery():
    connectors = read(CONNECTORS)
    reliability = read(RELIABILITY)
    for marker in (
        "store_stale_search_cache",
        "get_stale_search_cache",
        "cache_state",
        "recovery_notice",
        "stale_saved_at",
        "STALE_CACHE_TTL",
    ):
        assert marker in connectors or marker in reliability, marker
    assert "live_error" in connectors


def test_shared_cache_has_user_specific_import_tokens():
    connectors = read(CONNECTORS)
    assert "set_transient( $cache_key, $payload" in connectors
    assert "$payload['results'] = $this->seal_results" in connectors
    assert "$cached['results'] = $this->seal_results" in connectors


def test_cache_index_and_clear_include_fresh_stale_and_http_bodies():
    text = read(RELIABILITY)
    for marker in (
        "OPTION_CACHE_INDEX",
        "index_cache_record",
        "'stale-search'",
        "'http-body'",
        "delete_transient( $transient_key )",
        "delete_transient( sanitize_text_field( $meta['cache_key'] ) )",
    ):
        assert marker in text, marker


def test_import_idempotency_replays_before_token_consumption():
    connectors = read(CONNECTORS)
    reliability = read(RELIABILITY)
    for marker in (
        "idempotency_lookup",
        "idempotency_store",
        "idempotent_replay",
        "Idempotency-Key",
        "idempotency_key",
        "find_imported_source",
        "reused_existing_import",
    ):
        assert marker in connectors or marker in reliability, marker
    ajax_lookup = connectors.index("idempotency_lookup")
    token_read = connectors.index("read_sealed_result", ajax_lookup)
    assert ajax_lookup < token_read


def test_browser_import_client_reuses_idempotency_key():
    text = read(JS)
    for marker in (
        "crypto.randomUUID",
        "scIdempotencyKey",
        "idempotency_key",
        "Recovered previous import result",
    ):
        assert marker in text, marker


def test_conflict_detection_covers_structured_and_post_fields():
    connectors = read(CONNECTORS)
    reliability = read(RELIABILITY)
    for marker in (
        "META_CONFLICTS",
        "record_conflict",
        "open_conflicts",
        "resolve_conflict",
        "keep_local",
        "use_provider",
        "dismiss",
        "'title'             => '__post_title'",
        "'abstract'          => '__post_excerpt'",
        "open_conflict_count",
    ):
        assert marker in connectors or marker in reliability, marker


def test_conflict_resolution_rebuilds_citations_and_reliability():
    text = read(RELIABILITY)
    assert "SC_Library_Citation_Source_Manager::rebuild_source_indexes" in text
    assert "SC_Library_Citation_Source_Reliability::recalculate_reliability" in text
    assert "META_VERIFIED" in text


def test_holdings_locations_receive_freshness_metadata():
    text = read(RELIABILITY)
    for marker in (
        "normalize_location",
        "fresh_for_seconds",
        "stale_after",
        "'stale'",
        "'verification'",
        "location_ttl",
        "open-access",
        "library-catalog",
        "openurl",
        "interlibrary-loan",
    ):
        assert marker in text, marker


def test_holdings_merge_deduplicates_and_prefers_newer_checks():
    text = read(RELIABILITY)
    for marker in (
        "merge_locations",
        "untrailingslashit",
        "$new_time >= $old_time",
        "array_slice( $locations",
    ):
        assert marker in text, marker


def test_holdings_summary_and_manual_recheck():
    text = read(RELIABILITY)
    for marker in (
        "holdings_summary",
        "recheck_holdings",
        "next_recheck_at",
        "open_access",
        "library_actions",
        "Recheck Holdings",
    ):
        assert marker in text, marker


def test_hourly_maintenance_is_locked_and_bounded():
    text = read(RELIABILITY)
    for marker in (
        "CRON_HOOK",
        "wp_next_scheduled",
        "wp_schedule_event",
        "maintenance_lock",
        "process_due_holdings( 10 )",
        "$processed >=",
        "max( 50, absint( $limit ) * 5 )",
    ):
        assert marker in text, marker


def test_incremental_location_migration_is_bounded():
    text = read(RELIABILITY)
    for marker in (
        "OPTION_MIGRATION_CURSOR",
        "OPTION_MIGRATION_DONE",
        "'posts_per_page' => 40",
        "'offset'         => $cursor",
        "$cursor + count( $ids )",
    ):
        assert marker in text, marker
    assert "range( 1, $cursor )" not in text


def test_library_profile_validation_rejects_unsafe_urls():
    text = read(RELIABILITY)
    for marker in (
        "validate_library_profile",
        "validate_external_url",
        "https_required",
        "private_host_rejected",
        "private_ip_rejected",
        "FILTER_FLAG_NO_PRIV_RANGE",
        "FILTER_FLAG_NO_RES_RANGE",
        "unsupported_token",
    ):
        assert marker in text, marker


def test_public_profiles_must_be_published_enabled_and_valid():
    connectors = read(CONNECTORS)
    assert "$public_only ? 'publish'" in connectors
    assert "validate_library_profile( $post->ID, false )" in connectors
    assert "empty( $validation['valid'] )" in connectors


def test_admin_diagnostics_panel():
    text = read(RELIABILITY)
    for marker in (
        "Connector Health and Recovery",
        "Clear Retained Connector Cache",
        "Reset Provider State",
        "Last success",
        "Last failure",
        "Latency",
        "Cooldown",
    ):
        assert marker in text, marker


def test_admin_conflict_holdings_and_profile_controls():
    text = read(RELIABILITY)
    for marker in (
        "Connector Metadata Conflicts",
        "Holdings Reliability",
        "Profile Validation",
        "Use Provider Value",
        "Keep Current Value",
        "Validate Profile",
    ):
        assert marker in text, marker


def test_reliability_ajax_client():
    text = read(RELIABILITY_JS)
    for marker in (
        "sc_library_v261_reset_provider",
        "sc_library_v261_clear_cache",
        "sc_library_v261_recheck_holdings",
        "sc_library_v261_resolve_conflict",
        "sc_library_v261_validate_profile",
    ):
        assert marker in text, marker


def test_reliability_rest_api():
    text = read(RELIABILITY)
    for marker in (
        "'/connectors/health'",
        "'/connectors/(?P<provider>[a-z0-9_-]+)/reset'",
        "'/sources/(?P<id>\\d+)/holdings'",
        "'/sources/(?P<id>\\d+)/holdings/recheck'",
        "'/sources/(?P<id>\\d+)/connector-conflicts'",
        "'/library-profiles/(?P<id>\\d+)/validation'",
        "rest_can_edit_source",
    ):
        assert marker in text, marker


def test_responsive_spartan_reliability_css():
    text = read(CSS)
    for marker in (
        ".sc-connector-reliability-panel",
        ".sc-connector-health-grid",
        ".sc-connector-conflict",
        ".sc-connector-conflict__comparison",
        ".sc-holdings-summary",
        "@media (max-width: 760px)",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_connector_class_and_wrapper_versions():
    assert "public const VERSION = '2.6.1'" in read(CONNECTORS)
    assert "SC_LIBRARY_VERSION : '3.0.1'" in read(WRAPPER)


def main():
    tests = [
        test_required_files_exist,
        test_reliability_layer_loads_after_connectors,
        test_reliability_schemas_and_version,
        test_connector_transport_delegates_to_reliability_layer,
        test_https_host_allowlist_and_bounded_transport,
        test_bounded_retries_and_retry_after,
        test_conditional_requests_and_304_recovery,
        test_rate_limit_and_concurrency_header_capture,
        test_health_registry_and_circuit_breaker,
        test_health_event_history_is_bounded,
        test_stale_search_cache_recovery,
        test_shared_cache_has_user_specific_import_tokens,
        test_cache_index_and_clear_include_fresh_stale_and_http_bodies,
        test_import_idempotency_replays_before_token_consumption,
        test_browser_import_client_reuses_idempotency_key,
        test_conflict_detection_covers_structured_and_post_fields,
        test_conflict_resolution_rebuilds_citations_and_reliability,
        test_holdings_locations_receive_freshness_metadata,
        test_holdings_merge_deduplicates_and_prefers_newer_checks,
        test_holdings_summary_and_manual_recheck,
        test_hourly_maintenance_is_locked_and_bounded,
        test_incremental_location_migration_is_bounded,
        test_library_profile_validation_rejects_unsafe_urls,
        test_public_profiles_must_be_published_enabled_and_valid,
        test_admin_diagnostics_panel,
        test_admin_conflict_holdings_and_profile_controls,
        test_reliability_ajax_client,
        test_reliability_rest_api,
        test_responsive_spartan_reliability_css,
        test_connector_class_and_wrapper_versions,
    ]
    for test in tests:
        test()
    print(f"Connector and Holdings Reliability checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
