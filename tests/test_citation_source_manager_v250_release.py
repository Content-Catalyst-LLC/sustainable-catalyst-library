from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
CITATIONS = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
OCR = PLUGIN / "includes" / "class-sc-library-document-ocr-processing.php"
OCR_RELIABILITY = PLUGIN / "includes" / "class-sc-library-document-ocr-reliability.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
CITATION_RELIABILITY = PLUGIN / "includes" / "class-sc-library-citation-source-reliability.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
JS = PLUGIN / "assets" / "js" / "sc-library-citation-manager.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-citation-manager.css"
SINGLE = PLUGIN / "templates" / "single-sc_research_source.php"
ARCHIVE = PLUGIN / "templates" / "archive-sc_research_source.php"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files_exist():
    for path in (WRAPPER, CITATIONS, CITATION_RELIABILITY, OCR, OCR_RELIABILITY, REPOSITORY, BULK, JS, CSS, SINGLE, ARCHIVE):
        assert path.is_file(), path


def test_loader_preserves_existing_systems_and_adds_citations_last():
    text = read(WRAPPER)
    for marker in (
        "class-sc-library-pdf-to-document.php",
        "class-sc-library-pdf-conversion-reliability.php",
        "class-sc-library-pdf-bulk-import-repair.php",
        "class-sc-library-document-ocr-processing.php",
        "class-sc-library-document-ocr-reliability.php",
        "class-sc-library-document-public-repository.php",
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-citation-source-reliability.php",
        "new SC_Library_Citation_Source_Manager",
        "new SC_Library_Citation_Source_Reliability",
    ):
        assert marker in text, marker
    assert text.index("class-sc-library-document-public-repository.php") < text.index("class-sc-library-citation-source-manager.php")


def test_structured_source_and_project_records():
    text = read(CITATIONS)
    for marker in (
        "SOURCE_POST_TYPE = 'sc_research_source'",
        "PROJECT_POST_TYPE = 'sc_research_project'",
        "SOURCE_TYPE_TAXONOMY = 'sc_source_type'",
        "SOURCE_TOPIC_TAXONOMY = 'sc_source_topic'",
        "register_post_type",
        "register_taxonomy",
        "'has_archive'         => 'sources'",
        "'show_in_menu'        => 'sc-library'",
    ):
        assert marker in text, marker


def test_source_metadata_model():
    text = read(CITATIONS)
    for marker in (
        "META_AUTHORS",
        "META_ORGANIZATION",
        "META_EDITORS",
        "META_YEAR",
        "META_CONTAINER_TITLE",
        "META_PUBLISHER",
        "META_EDITION",
        "META_VOLUME",
        "META_ISSUE",
        "META_PAGES",
        "META_DOI",
        "META_ISBN",
        "META_PMID",
        "META_URL",
        "META_ARCHIVE_URL",
        "META_ATTACHMENT_ID",
        "META_RELATED_DOCUMENT_IDS",
        "META_PROVENANCE",
        "META_VERIFIED",
        "META_PEER_REVIEWED",
        "META_FULL_TEXT_STATUS",
    ):
        assert marker in text, marker


def test_harvard_formatter_and_locator_support():
    text = read(CITATIONS)
    for marker in (
        "Harvard — Sustainable Catalyst",
        "format_citation",
        "format_in_text_citation",
        "format_harvard_reference",
        "format_creators_reference",
        "format_creators_in_text",
        "format_editors_for_chapter",
        "normalize_edition",
        "normalize_page_range",
        "Accessed:",
        "doi:",
        "et al.",
        "p. ",
        "pp. ",
    ):
        assert marker in text, marker


def test_same_author_year_suffixes_and_citation_keys():
    text = read(CITATIONS)
    for marker in (
        "META_YEAR_SUFFIX",
        "META_AUTHOR_YEAR_KEY",
        "recalculate_year_suffixes",
        "alpha_suffix",
        "META_CITATION_KEY",
        "update_citation_key",
        "citation-key",
    ):
        assert marker in text, marker


def test_project_source_collections_and_reverse_relationships():
    text = read(CITATIONS)
    for marker in (
        "META_PROJECT_SOURCE_IDS",
        "META_PROJECT_IDS",
        "sync_source_projects",
        "sync_project_sources",
        "project_source_ids",
        "Project Source Library",
        "Research project IDs",
    ):
        assert marker in text, marker



def test_relationship_cleanup_when_records_are_deleted():
    text = read(CITATIONS)
    for marker in (
        "before_delete_record",
        "after_delete_record",
        "deleted_source_group",
        "sync_source_projects",
        "sync_project_sources",
        "recalculate_year_suffixes",
    ):
        assert marker in text, marker


def test_duplicate_detection_uses_identifiers_and_fingerprint():
    text = read(CITATIONS)
    for marker in (
        "META_NORMALIZED_DOI",
        "META_NORMALIZED_ISBN",
        "META_NORMALIZED_URL",
        "META_FINGERPRINT",
        "find_duplicate_sources",
        "update_duplicate_relationships",
        "Possible duplicate sources",
    ):
        assert marker in text, marker


def test_private_notes_and_public_source_boundaries():
    text = read(CITATIONS)
    assert "META_NOTES" in text
    assert "Private notes are excluded from public pages and public API responses" in text
    assert "if ( $include_private && $can_edit_source )" in text
    assert "'private_notes'" in text
    assert "'publish' !== $post->post_status" in text
    assert "project_is_public" in text
    assert "return 'publish' === get_post_status( $document_id )" in text


def test_source_attachments_and_relationships():
    text = read(CITATIONS)
    js = read(JS)
    for marker in (
        "Select Source File",
        "sc_source_attachment_id",
        "wp_enqueue_media",
        "data-sc-source-select-attachment",
        "data-sc-source-attachment-id",
        "Related Knowledge Library document IDs",
        "View Attached Material",
    ):
        assert marker in text or marker in js, marker


def test_public_source_library_and_bibliography_shortcodes():
    text = read(CITATIONS)
    for marker in (
        "add_shortcode( 'sc_source_library'",
        "add_shortcode( 'sc_research_bibliography'",
        "add_shortcode( 'sc_source_citation'",
        "shortcode_source_library",
        "shortcode_research_bibliography",
        "shortcode_source_citation",
        "Copy citation",
        "Research Source Library",
    ):
        assert marker in text, marker


def test_public_templates_and_source_record():
    citations = read(CITATIONS)
    single = read(SINGLE)
    archive = read(ARCHIVE)
    for marker in (
        "render_public_source_page",
        "Cite this source",
        "Reference list",
        "In-text",
        "Source record",
        "Related Knowledge Library documents",
        "Metadata verified",
    ):
        assert marker in citations, marker
    assert "SC_Library_Citation_Source_Manager::render_public_source_page" in single
    assert "SC_Library_Citation_Source_Manager::render_archive_page" in archive


def test_permission_controlled_rest_api():
    text = read(CITATIONS)
    for marker in (
        "API_NAMESPACE = 'sc-library/v1'",
        "'/sources'",
        "'/search'",
        "'/sources/(?P<id>\\d+)'",
        "'/sources/(?P<id>\\d+)/citation'",
        "'/projects/(?P<id>\\d+)/bibliography'",
        "'/projects/(?P<id>\\d+)/sources'",
        "'/citation/styles'",
        "rest_can_read_source",
        "rest_can_edit_source",
        "rest_can_read_project",
        "current_user_can( 'edit_posts' )",
        "current_user_can( 'edit_post'",
    ):
        assert marker in text, marker


def test_api_can_create_update_and_attach_sources():
    text = read(CITATIONS)
    for marker in (
        "rest_create_source",
        "rest_update_source",
        "apply_rest_source_payload",
        "rest_update_project_sources",
        "source_ids",
        "metadata_provenance",
        "metadata_verified",
        "peer_reviewed",
        "related_document_ids",
        "project_ids",
    ):
        assert marker in text, marker


def test_source_types_cover_research_materials():
    text = read(CITATIONS)
    for marker in (
        "journal-article",
        "book-chapter",
        "dataset",
        "legislation",
        "standard",
        "conference-paper",
        "thesis",
        "video",
        "podcast",
        "software",
        "archive",
    ):
        assert marker in text, marker


def test_copy_controls_and_project_filtering():
    text = read(JS)
    for marker in (
        "navigator.clipboard",
        "document.execCommand('copy')",
        "data-sc-copy-target",
        "data-sc-copy-parent",
        "data-sc-project-source-filter",
        "wp.media",
    ):
        assert marker in text, marker


def test_spartan_responsive_public_and_admin_css():
    text = read(CSS)
    for marker in (
        ".sc-citation-field-grid",
        ".sc-citation-dashboard-grid",
        ".sc-source-page",
        ".sc-source-citation-panel",
        ".sc-source-record__layout",
        ".sc-source-library__results",
        ".sc-project-bibliography__list",
        "@media (max-width: 760px)",
        "@media print",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_citation_manager_dashboard_and_api_documentation():
    text = read(CITATIONS)
    for marker in (
        "Citation and Research Source Manager",
        "Published sources",
        "Research projects",
        "Possible duplicates",
        "[sc_source_library]",
        "[sc_research_bibliography",
        "Published source metadata is readable publicly",
    ):
        assert marker in text, marker


def test_version_and_existing_route_compatibility():
    wrapper = read(WRAPPER)
    citations = read(CITATIONS)
    repository = read(REPOSITORY)
    assert "public const VERSION = '2.5.1'" in citations
    assert "SC_LIBRARY_VERSION : '3.1.0'" in wrapper
    assert "public const ROUTE_VERSION = '2.3.0'" in repository
    assert "public const VERSION = '2.4.1'" in read(OCR)
    assert "public const VERSION = '2.4.1'" in read(OCR_RELIABILITY)


def main():
    tests = [
        test_required_files_exist,
        test_loader_preserves_existing_systems_and_adds_citations_last,
        test_structured_source_and_project_records,
        test_source_metadata_model,
        test_harvard_formatter_and_locator_support,
        test_same_author_year_suffixes_and_citation_keys,
        test_project_source_collections_and_reverse_relationships,
        test_relationship_cleanup_when_records_are_deleted,
        test_duplicate_detection_uses_identifiers_and_fingerprint,
        test_private_notes_and_public_source_boundaries,
        test_source_attachments_and_relationships,
        test_public_source_library_and_bibliography_shortcodes,
        test_public_templates_and_source_record,
        test_permission_controlled_rest_api,
        test_api_can_create_update_and_attach_sources,
        test_source_types_cover_research_materials,
        test_copy_controls_and_project_filtering,
        test_spartan_responsive_public_and_admin_css,
        test_citation_manager_dashboard_and_api_documentation,
        test_version_and_existing_route_compatibility,
    ]
    for test in tests:
        test()
    print(f"Citation and Research Source Manager checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
