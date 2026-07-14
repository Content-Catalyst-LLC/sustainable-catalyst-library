from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"
ARCHIVE = PLUGIN / "templates" / "archive-sc_document_repository.php"
FAMILY = PLUGIN / "templates" / "taxonomy-sc_document_family.php"
TYPE = PLUGIN / "templates" / "taxonomy-sc_document_type.php"


def test_required_files_exist():
    for path in (WRAPPER, REPOSITORY, BULK, RELIABILITY, CSS, ARCHIVE, FAMILY, TYPE):
        assert path.is_file(), path


def test_companion_layer_preserves_existing_operations():
    text = WRAPPER.read_text(encoding="utf-8")
    for marker in (
        "class-sc-library-pdf-to-document.php",
        "class-sc-library-pdf-conversion-reliability.php",
        "class-sc-library-pdf-bulk-import-repair.php",
        "class-sc-library-document-public-repository.php",
        "new SC_Library_Document_Public_Repository",
    ):
        assert marker in text, marker


def test_public_documents_route_and_templates():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "QUERY_VAR = 'sc_document_repository'",
        "^documents/?$",
        "archive-sc_document_repository.php",
        "taxonomy-sc_document_family.php",
        "taxonomy-sc_document_type.php",
        "disable_repository_canonical_redirect",
        "render_repository_page",
    ):
        assert marker in text, marker


def test_document_family_public_landing_pages():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "render_family_page",
        "META_FAMILY_KICKER",
        "META_FAMILY_FEATURED",
        "META_FAMILY_ORDER",
        "term_description",
        "render_family_index",
        "Related document families",
    ):
        assert marker in text, marker


def test_repository_search_and_filters():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "sc_doc_q",
        "sc_doc_family",
        "sc_doc_type",
        "sc_doc_lifecycle",
        "sc_doc_year",
        "sc_doc_version",
        "sc_doc_sort",
        "Search titles, summaries, and readable document text",
        "document_query_args",
    ):
        assert marker in text, marker


def test_document_types_and_default_structure():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "TAX_TYPE = 'sc_document_type'",
        "documents/type",
        "ensure_default_types",
        "Foundation Document",
        "Research Report",
        "Methodology Document",
        "Policy Document",
        "Technical Documentation",
        "Archive Document",
    ):
        assert marker in text, marker


def test_featured_and_repository_order_controls():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "META_FEATURED",
        "META_ORDER",
        "Featured Documents",
        "Pinned repository records",
        "featured_document_ids",
        "Feature and pin this document",
        "Repository order",
    ):
        assert marker in text, marker


def test_lifecycle_grouping_and_compact_rows():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "grouped_posts",
        "current",
        "superseded",
        "archived",
        "historical",
        "render_document_row",
        "Read document",
        "Open PDF",
        "Download PDF",
    ):
        assert marker in text, marker


def test_repository_metrics_and_family_index():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "repository_counts",
        "Documents",
        "Families",
        "Last updated",
        "Browse the repository by family",
        "Open family",
    ):
        assert marker in text, marker


def test_shortcode_compatibility_and_full_repository_shortcode():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "sc_pdf_document_repository",
        "sc_document_repository",
        "sc_pdf_document_library",
        "sc_pdf_library",
        "sc_foundation_documents",
        "sc_foundations_library",
        "pre_do_shortcode_tag",
        "sc_library",
    ):
        assert marker in text, marker


def test_public_repository_admin_and_route_repair():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "Public Repository",
        "render_repository_admin_page",
        "Repair Repository Routes",
        "Seed Recommended Families and Types",
        "sc_library_v230_repair_repository_routes",
        "sc_library_v230_seed_repository",
    ):
        assert marker in text, marker


def test_repository_migration_assigns_types_and_order():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "migrate_public_repository_once",
        "sc_library_document_repository_migration_version",
        "family_type_map",
        "general-document",
        "META_ORDER",
        "sc_library_document_repository_migration_summary",
    ):
        assert marker in text, marker


def test_spartan_public_repository_css():
    text = CSS.read_text(encoding="utf-8")
    for marker in (
        ".sc-public-document-repository",
        ".sc-document-family-index",
        ".sc-public-document-filter",
        ".sc-public-document-row",
        ".sc-public-document-index__pagination",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text


def test_version_marker():
    text = REPOSITORY.read_text(encoding="utf-8")
    assert "public const VERSION = '2.3.0'" in text
    assert "public const ROUTE_VERSION = '2.3.0'" in text
    assert "SC_LIBRARY_VERSION : '2.3.0'" in WRAPPER.read_text(encoding="utf-8")


def main():
    tests = [
        test_required_files_exist,
        test_companion_layer_preserves_existing_operations,
        test_public_documents_route_and_templates,
        test_document_family_public_landing_pages,
        test_repository_search_and_filters,
        test_document_types_and_default_structure,
        test_featured_and_repository_order_controls,
        test_lifecycle_grouping_and_compact_rows,
        test_repository_metrics_and_family_index,
        test_shortcode_compatibility_and_full_repository_shortcode,
        test_public_repository_admin_and_route_repair,
        test_repository_migration_assigns_types_and_order,
        test_spartan_public_repository_css,
        test_version_marker,
    ]
    for test in tests:
        test()
    print(f"Document Families and Public Repository checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
