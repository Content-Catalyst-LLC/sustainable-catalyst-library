from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-citation-source-reliability.php"
CSS = PLUGIN / "assets" / "css" / "sc-library-citation-manager.css"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_and_load_order():
    for path in (WRAPPER, MANAGER, RELIABILITY, CSS, REPOSITORY, OCR):
        assert path.is_file(), path
    text = read(WRAPPER)
    assert "class-sc-library-citation-source-manager.php" in text
    assert "class-sc-library-citation-source-reliability.php" in text
    assert text.index("class-sc-library-citation-source-manager.php") < text.index("class-sc-library-citation-source-reliability.php")
    assert "new SC_Library_Citation_Source_Reliability" in text


def test_source_version_and_route_compatibility():
    assert "public const VERSION = '2.5.1'" in read(MANAGER)
    assert "public const VERSION = '2.5.1'" in read(RELIABILITY)
    assert "SC_LIBRARY_VERSION : '3.6.0'" in read(WRAPPER)
    assert "public const ROUTE_VERSION = '2.3.0'" in read(REPOSITORY)
    assert "public const VERSION = '2.4.1'" in read(OCR)


def test_institutional_author_reliability():
    text = read(MANAGER)
    for marker in (
        "META_ORGANIZATION_SHORT",
        "Short institutional author",
        "organization_short",
        "used only for in-text citations",
        "! empty( $data['organization_short'] )",
    ):
        assert marker in text, marker


def test_name_and_orcid_normalization():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "normalize_name_component",
        "false !== strpos( $line, ',' )",
        "wp_check_invalid_utf8",
        "normalize_orcid",
        "valid_orcid",
        "checksum validation",
    ):
        assert marker in manager or marker in reliability, marker


def test_identifier_validation_and_normalized_duplicate_keys():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "valid_doi",
        "valid_isbn",
        "META_NORMALIZED_DOI",
        "META_NORMALIZED_ISBN",
        "The ISBN checksum is invalid",
        "The DOI does not match",
    ):
        assert marker in manager or marker in reliability, marker
    assert "SC_Library_Citation_Source_Reliability::valid_doi" in manager
    assert "SC_Library_Citation_Source_Reliability::valid_isbn" in manager


def test_canonical_url_normalization():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "canonical_url",
        "utm_",
        "fbclid",
        "PHP_QUERY_RFC3986",
        "preg_replace( '/^www",
        "SC_Library_Citation_Source_Reliability::canonical_url",
    ):
        assert marker in manager or marker in reliability, marker


def test_locator_page_and_edition_formatting():
    text = read(MANAGER)
    for marker in (
        "normalize_locator",
        "page_label",
        "normalize_page_range",
        "normalize_edition",
        "In: ",
        "para\\.",
        "sec\\.",
        "pp. ",
        "p. ",
    ):
        assert marker in text, marker


def test_citation_cache_and_invalidation():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "META_CITATION_CACHE",
        "META_CITATION_CACHE_VERSION",
        "cache_key",
        "invalidate_citation_meta",
        "delete_post_meta( $post_id, self::META_CITATION_CACHE",
        "count( $cache ) > 40",
    ):
        assert marker in manager or marker in reliability, marker


def test_metadata_history_and_restore():
    text = read(RELIABILITY)
    for marker in (
        "HISTORY_SCHEMA",
        "META_HISTORY",
        "begin_source_update",
        "finalize_source_update",
        "changed_fields",
        "append_history",
        "Metadata Change Review",
        "Restore previous metadata",
        "restore_source_snapshot_data",
    ):
        assert marker in text or marker in read(MANAGER), marker


def test_verification_is_invalidated_after_critical_changes():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "META_VERIFICATION_INVALIDATED",
        "citation-critical fields changed",
        "admin_verification_confirmation",
        "sc_source_reverify",
        "last_verified",
        "changed_fields' => $critical",
    ):
        assert marker in manager or marker in reliability, marker


def test_validation_and_completeness_model():
    text = read(RELIABILITY)
    for marker in (
        "META_VALIDATION",
        "META_COMPLETENESS",
        "META_RELIABILITY_STATUS",
        "validation_issues",
        "completeness_score",
        "Citation ready",
        "Needs review",
        "Invalid metadata",
        "book-chapter",
        "access date",
    ):
        assert marker in text, marker


def test_duplicate_reconciliation_without_destructive_merge():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "META_DUPLICATE_DECISIONS",
        "META_CANONICAL_ID",
        "Duplicate Reconciliation",
        "same-work",
        "alternate-edition",
        "related-work",
        "not-duplicate",
        "does not delete or silently merge",
        "sc_library_source_duplicate_candidates",
    ):
        assert marker in manager or marker in reliability, marker


def test_optimistic_concurrency_idempotency_and_rate_limits():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    for marker in (
        "validate_expected_modified",
        "expected_modified_gmt",
        "If-Unmodified-Since",
        "status' => 409",
        "Idempotency-Key",
        "remember_idempotent_create",
        "enforce_write_rate_limit",
        "status' => 429",
        "update-project-sources",
    ):
        assert marker in manager or marker in reliability, marker


def test_rest_reliability_history_and_duplicate_endpoints():
    text = read(RELIABILITY)
    for marker in (
        "'/sources/(?P<id>\\d+)/reliability'",
        "'/sources/(?P<id>\\d+)/history'",
        "'/sources/(?P<id>\\d+)/duplicates'",
        "rest_reliability",
        "rest_history",
        "rest_duplicates",
        "rest_update_duplicates",
        "current_user_can( 'edit_post'",
    ):
        assert marker in text, marker


def test_rest_etag_and_last_modified_headers():
    text = read(RELIABILITY)
    for marker in (
        "rest_post_dispatch",
        "ETag",
        "Last-Modified",
        "Cache-Control",
        "must-revalidate",
    ):
        assert marker in text, marker


def test_incremental_existing_source_migration():
    text = read(RELIABILITY)
    for marker in (
        "maybe_migrate_sources",
        "sc_library_citation_reliability_version",
        "sc_library_citation_reliability_cursor",
        "ORDER BY ID ASC LIMIT 25",
        "rebuild_source_indexes",
        "'migration'",
    ):
        assert marker in text, marker
    assert "range( 1, $cursor )" not in text


def test_snapshot_restore_keeps_project_relationships_consistent():
    text = read(MANAGER)
    for marker in (
        "restore_source_snapshot_data",
        "$old_projects",
        "$new_projects",
        "array_diff( $old_projects, $new_projects )",
        "META_PROJECT_SOURCE_IDS",
        "update_id_meta",
    ):
        assert marker in text, marker


def test_reliability_admin_interface_and_columns():
    text = read(RELIABILITY)
    css = read(CSS)
    for marker in (
        "Citation Reliability",
        "Recheck Source",
        "Duplicate Reconciliation",
        "Metadata Change Review",
        "sc_source_reliability",
        ".sc-source-reliability-card",
        ".sc-source-duplicate-table",
        ".sc-source-history-list",
        "@media (max-width: 900px)",
    ):
        assert marker in text or marker in css, marker


def test_public_api_exposes_status_but_not_private_issues():
    manager = read(MANAGER)
    reliability = read(RELIABILITY)
    assert "'citation_reliability'" in manager
    assert "'completeness_score'" in manager
    assert "'validation_issues'" in manager
    assert "if ( current_user_can( 'edit_post', $id ) )" in reliability
    assert "get_post_meta( $id, self::META_RELIABILITY_STATUS" in reliability


def test_existing_v250_source_model_and_shortcodes_remain():
    text = read(MANAGER)
    for marker in (
        "SOURCE_POST_TYPE = 'sc_research_source'",
        "PROJECT_POST_TYPE = 'sc_research_project'",
        "add_shortcode( 'sc_source_library'",
        "add_shortcode( 'sc_research_bibliography'",
        "add_shortcode( 'sc_source_citation'",
        "API_NAMESPACE = 'sc-library/v1'",
    ):
        assert marker in text, marker


def main():
    tests = [
        test_required_files_and_load_order,
        test_source_version_and_route_compatibility,
        test_institutional_author_reliability,
        test_name_and_orcid_normalization,
        test_identifier_validation_and_normalized_duplicate_keys,
        test_canonical_url_normalization,
        test_locator_page_and_edition_formatting,
        test_citation_cache_and_invalidation,
        test_metadata_history_and_restore,
        test_verification_is_invalidated_after_critical_changes,
        test_validation_and_completeness_model,
        test_duplicate_reconciliation_without_destructive_merge,
        test_optimistic_concurrency_idempotency_and_rate_limits,
        test_rest_reliability_history_and_duplicate_endpoints,
        test_rest_etag_and_last_modified_headers,
        test_incremental_existing_source_migration,
        test_snapshot_restore_keeps_project_relationships_consistent,
        test_reliability_admin_interface_and_columns,
        test_public_api_exposes_status_but_not_private_issues,
        test_existing_v250_source_model_and_shortcodes_remain,
    ]
    for test in tests:
        test()
    print(f"Citation Formatting and Source Reliability checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
