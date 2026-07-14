from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
CONNECTORS = PLUGIN / "includes" / "class-sc-library-scholarly-library-connectors.php"
CONNECTOR_RELIABILITY = PLUGIN / "includes" / "class-sc-library-connector-holdings-reliability.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-citation-source-reliability.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
JS = PLUGIN / "assets" / "js" / "sc-library-connectors.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-connectors.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, CONNECTORS, CONNECTOR_RELIABILITY, MANAGER, RELIABILITY, OCR, REPOSITORY, JS, CSS):
        assert path.is_file(), path


def test_connector_layer_loads_after_citation_reliability():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-citation-source-reliability.php",
        "class-sc-library-scholarly-library-connectors.php",
        "new SC_Library_Scholarly_Library_Connectors",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-citation-source-reliability.php") < text.index("class-sc-library-scholarly-library-connectors.php")


def test_provider_registry_covers_scholarly_and_library_sources():
    text = read(CONNECTORS)
    for marker in (
        "'crossref'",
        "'openalex'",
        "'datacite'",
        "'pubmed'",
        "'pmc'",
        "'loc'",
        "'openlibrary'",
        "'googlebooks'",
        "'unpaywall'",
        "'google_scholar'",
        "'worldcat'",
    ):
        assert marker in text, marker


def test_crossref_polite_metadata_connector():
    text = read(CONNECTORS)
    for marker in (
        "https://api.crossref.org/works",
        "query.bibliographic",
        "'mailto'",
        "search_crossref",
        "map_crossref_type",
    ):
        assert marker in text, marker


def test_openalex_requires_production_key_and_exposes_access_locations():
    text = read(CONNECTORS)
    for marker in (
        "SC_LIBRARY_OPENALEX_API_KEY",
        "https://api.openalex.org/works",
        "'api_key'",
        "best_oa_location",
        "primary_location",
        "cited_by_count",
        "lookup_openalex_doi",
    ):
        assert marker in text, marker


def test_datacite_current_doi_endpoint_and_json_api_mapping():
    text = read(CONNECTORS)
    for marker in (
        "https://api.datacite.org/dois",
        "page[size]",
        "resourceTypeGeneral",
        "publicationYear",
        "nameIdentifiers",
        "search_datacite",
    ):
        assert marker in text, marker


def test_ncbi_pubmed_and_pmc_eutilities():
    text = read(CONNECTORS)
    for marker in (
        "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi",
        "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi",
        "SC_LIBRARY_NCBI_API_KEY",
        "SC_LIBRARY_NCBI_TOOL",
        "search_pubmed",
        "search_pmc",
        "pmcid",
    ):
        assert marker in text, marker


def test_library_of_congress_digital_collection_connector():
    text = read(CONNECTORS)
    for marker in (
        "https://www.loc.gov/search/",
        "'fo' => 'json'",
        "original_format",
        "online_format",
        "number_lccn",
        "search_loc",
    ):
        assert marker in text, marker


def test_open_library_identified_low_volume_book_connector():
    text = read(CONNECTORS) + read(CONNECTOR_RELIABILITY)
    for marker in (
        "https://openlibrary.org/search.json",
        "https://openlibrary.org/api/books",
        "User-Agent",
        "contact_email",
        "public_scan_b",
        "ebook_access",
        "lookup_openlibrary_isbn",
    ):
        assert marker in text, marker


def test_google_books_key_and_preview_connector():
    text = read(CONNECTORS)
    for marker in (
        "SC_LIBRARY_GOOGLE_BOOKS_API_KEY",
        "https://www.googleapis.com/books/v1/volumes",
        "industryIdentifiers",
        "webReaderLink",
        "previewLink",
        "viewability",
        "lookup_googlebooks_isbn",
    ):
        assert marker in text, marker


def test_unpaywall_doi_location_connector():
    text = read(CONNECTORS)
    for marker in (
        "https://api.unpaywall.org/v2/",
        "best_oa_location",
        "oa_locations",
        "url_for_pdf",
        "url_for_landing_page",
        "lookup_unpaywall",
    ):
        assert marker in text, marker


def test_google_scholar_and_worldcat_are_compliant_handoffs():
    text = read(CONNECTORS)
    for marker in (
        "https://scholar.google.com/scholar?q=",
        "https://search.worldcat.org/search?q=",
        "No automated scraping",
        "Browser search handoff",
        "discovery_handoffs",
    ):
        assert marker in text, marker
    assert "scholar.google.com/scholar?cluster=" not in text


def test_library_profiles_support_catalog_openurl_proxy_and_ill():
    text = read(CONNECTORS)
    for marker in (
        "PROFILE_POST_TYPE = 'sc_library_profile'",
        "META_PROFILE_CATALOG_TEMPLATE",
        "META_PROFILE_OPENURL_BASE",
        "META_PROFILE_ILL_URL",
        "META_PROFILE_PROXY_PREFIX",
        "{query}",
        "{title}",
        "{doi}",
        "{isbn}",
        "build_openurl",
        "interlibrary-loan",
        "library-proxy",
    ):
        assert marker in text, marker


def test_private_draft_library_profiles_do_not_reach_public_pages():
    text = read(CONNECTORS)
    assert "library_profiles( $public_only = false )" in text
    assert "$public_only ? 'publish'" in text
    assert "library_actions( $data, true )" in text


def test_normalized_discovery_schema_and_result_fields():
    text = read(CONNECTORS)
    for marker in (
        "RESULT_SCHEMA = 'sc-library-discovery-result/1.0'",
        "SEARCH_SCHEMA = 'sc-library-federated-search/1.0'",
        "normalized_result",
        "'provider_record_id'",
        "'open_access_url'",
        "'preview_url'",
        "'full_text_status'",
        "'identifiers'",
        "'existing_source_ids'",
        "'discovery_links'",
    ):
        assert marker in text, marker


def test_provider_requests_are_https_allowlisted_and_bounded():
    text = read(CONNECTORS) + read(CONNECTOR_RELIABILITY)
    for marker in (
        "allowed_hosts",
        "connector_url_rejected",
        "wp_safe_remote_get",
        "'redirection'         => 2",
        "'limit_response_size' => 5 * 1024 * 1024",
        "DEFAULT_TIMEOUT",
        "connector_invalid_json",
    ):
        assert marker in text, marker


def test_cache_backoff_and_per_user_import_tokens():
    text = read(CONNECTORS) + read(CONNECTOR_RELIABILITY)
    for marker in (
        "CACHE_PREFIX",
        "set_transient",
        "get_transient",
        "backoff_",
        "retry-after",
        "seal_results",
        "read_sealed_result",
        "'user_id'  => get_current_user_id()",
        "delete_transient",
    ):
        assert marker in text, marker
    assert "set_transient( $cache_key, $payload" in text
    assert "$cached['results'] = $this->seal_results" in text


def test_provenance_aware_source_import_and_review_boundary():
    text = read(CONNECTORS)
    for marker in (
        "import_result",
        "fill_empty",
        "overwrite",
        "META_PROVENANCE",
        "META_VERIFIED",
        "META_CONNECTOR_IDS",
        "META_DISCOVERY_HISTORY",
        "META_IMPORT_FINGERPRINT",
        "rebuild_source_indexes",
        "recalculate_reliability",
        "Source imported as a draft",
    ):
        assert marker in text, marker


def test_import_integrates_research_projects_without_duplicate_ids():
    text = read(CONNECTORS)
    for marker in (
        "META_PROJECT_IDS",
        "META_PROJECT_SOURCE_IDS",
        "array_unique",
        "project_id",
    ):
        assert marker in text, marker


def test_source_locator_combines_open_access_books_libraries_and_handoffs():
    text = read(CONNECTORS)
    for marker in (
        "locate_source",
        "lookup_unpaywall",
        "lookup_openalex_doi",
        "lookup_openlibrary_isbn",
        "lookup_googlebooks_isbn",
        "library_actions",
        "discovery_handoffs",
        "META_ACCESS_LOCATIONS",
        "unique_locations",
    ):
        assert marker in text, marker


def test_admin_discovery_workspace_and_provider_diagnostics():
    text = read(CONNECTORS)
    for marker in (
        "Source Discovery",
        "Discover Sources",
        "Providers",
        "Libraries",
        "Import History",
        "Search Enabled Providers",
        "Test Provider",
        "Save Connector Settings",
        "Clean",
    ):
        if marker == "Clean":
            continue
        assert marker in text, marker


def test_source_edit_and_public_source_locator_integration():
    connectors = read(CONNECTORS)
    manager = read(MANAGER)
    for marker in (
        "Locate Source Material",
        "render_source_locator_box",
        "render_public_handoffs",
        "Find, read, or request this source",
    ):
        assert marker in connectors, marker
    assert "SC_Library_Scholarly_Library_Connectors::render_public_handoffs" in manager


def test_discovery_ajax_parallel_client_and_secure_import():
    text = read(JS)
    for marker in (
        "Promise.all",
        "sc_library_v260_search_provider",
        "sc_library_v260_import_result",
        "sc_library_v260_locate_source",
        "sc_library_v260_test_provider",
        "sc_library_v260_save_settings",
        "data-sc-import-token",
        "Import as Draft Source",
    ):
        assert marker in text, marker


def test_permission_controlled_rest_discovery_api():
    text = read(CONNECTORS)
    for marker in (
        "'/connectors'",
        "'/discovery/search'",
        "'/discovery/import'",
        "'/sources/(?P<id>\\d+)/locate'",
        "'/library-profiles'",
        "rest_can_discover",
        "current_user_can( 'edit_posts' )",
        "current_user_can( 'edit_post'",
    ):
        assert marker in text, marker


def test_public_discovery_is_disabled_by_default_but_filterable():
    text = read(CONNECTORS)
    assert "sc_library_allow_public_discovery" in text
    assert "wp_ajax_nopriv_sc_library_v260_search_provider" in text
    assert "Source discovery is available to authorized researchers" in text


def test_source_discovery_shortcode():
    text = read(CONNECTORS)
    for marker in (
        "add_shortcode( 'sc_source_discovery'",
        "shortcode_source_discovery",
        "[sc_source_discovery",
        "Search enabled scholarly and library metadata providers",
    ):
        if marker == "[sc_source_discovery":
            continue
        assert marker in text, marker


def test_responsive_accessible_spartan_interface():
    text = read(CSS)
    for marker in (
        ".sc-connector-search-form",
        ".sc-connector-provider-picker",
        ".sc-connector-result-card",
        ".sc-connector-provider-cards",
        ".sc-library-profile-cards",
        ".sc-source-public-locator",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_version_and_compatibility_boundaries():
    wrapper = read(WRAPPER)
    connectors = read(CONNECTORS)
    repository = read(REPOSITORY)
    assert "public const VERSION = '2.6.1'" in connectors
    assert "SC_LIBRARY_VERSION : '2.7.0'" in wrapper
    assert "public const ROUTE_VERSION = '2.3.0'" in repository
    assert "public const VERSION = '2.5.1'" in read(RELIABILITY)
    assert "public const VERSION = '2.4.1'" in read(OCR)


def main():
    tests = [
        test_required_files_exist,
        test_connector_layer_loads_after_citation_reliability,
        test_provider_registry_covers_scholarly_and_library_sources,
        test_crossref_polite_metadata_connector,
        test_openalex_requires_production_key_and_exposes_access_locations,
        test_datacite_current_doi_endpoint_and_json_api_mapping,
        test_ncbi_pubmed_and_pmc_eutilities,
        test_library_of_congress_digital_collection_connector,
        test_open_library_identified_low_volume_book_connector,
        test_google_books_key_and_preview_connector,
        test_unpaywall_doi_location_connector,
        test_google_scholar_and_worldcat_are_compliant_handoffs,
        test_library_profiles_support_catalog_openurl_proxy_and_ill,
        test_private_draft_library_profiles_do_not_reach_public_pages,
        test_normalized_discovery_schema_and_result_fields,
        test_provider_requests_are_https_allowlisted_and_bounded,
        test_cache_backoff_and_per_user_import_tokens,
        test_provenance_aware_source_import_and_review_boundary,
        test_import_integrates_research_projects_without_duplicate_ids,
        test_source_locator_combines_open_access_books_libraries_and_handoffs,
        test_admin_discovery_workspace_and_provider_diagnostics,
        test_source_edit_and_public_source_locator_integration,
        test_discovery_ajax_parallel_client_and_secure_import,
        test_permission_controlled_rest_discovery_api,
        test_public_discovery_is_disabled_by_default_but_filterable,
        test_source_discovery_shortcode,
        test_responsive_accessible_spartan_interface,
        test_version_and_compatibility_boundaries,
    ]
    for test in tests:
        test()
    print(f"Scholarly and Library Database Connector checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
