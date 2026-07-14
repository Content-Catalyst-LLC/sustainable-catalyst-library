from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
REPOSITORY = PLUGIN / "includes" / "class-sc-library-document-public-repository.php"
HARDENING = PLUGIN / "includes" / "class-sc-library-document-repository-hardening.php"
BULK = PLUGIN / "includes" / "class-sc-library-pdf-bulk-import-repair.php"
RELIABILITY = PLUGIN / "includes" / "class-sc-library-pdf-conversion-reliability.php"
CSS = PLUGIN / "assets" / "css" / "sc-library-foundation-pages.css"


def test_required_files_exist():
    for path in (WRAPPER, REPOSITORY, HARDENING, BULK, RELIABILITY, CSS):
        assert path.is_file(), path


def test_hardening_layer_is_loaded_without_replacing_repository():
    text = WRAPPER.read_text(encoding="utf-8")
    for marker in (
        "class-sc-library-document-repository-hardening.php",
        "class-sc-library-document-public-repository.php",
        "new SC_Library_Document_Repository_Hardening",
        "new SC_Library_Document_Public_Repository",
        "new SC_Library_PDF_Bulk_Import_Repair",
    ):
        assert marker in text, marker


def test_unique_repository_landmarks_and_skip_link():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "render_instance",
        "instance_id",
        "sc-repository-skip-link",
        "Skip to document results",
        "aria-labelledby",
        "Document repository results",
        "screen-reader-text",
    ):
        assert marker in text, marker


def test_accessible_filter_form():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "<fieldset>",
        "<legend",
        "filter-help",
        "for=\"<?php echo esc_attr( $instance_id ); ?>-search\"",
        "autocomplete=\"off\"",
        "Apply filters",
        "moves focus to the result summary",
    ):
        assert marker in text, marker


def test_result_status_and_accessible_pagination():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        'role="status"',
        'aria-live="polite"',
        'aria-atomic="true"',
        'tabindex="-1"',
        'aria-current="page"',
        "append_result_fragment",
        "Document result pages",
    ):
        assert marker in text, marker


def test_featured_documents_do_not_repeat_across_pages():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "$featured_display_ids = 1 === $page ? $featured_ids : array();",
        "$this->document_query_args( $filters, $page, $per_page, $featured_ids )",
        "$total_documents = absint( $documents->found_posts ) + count( $featured_ids );",
        "elseif ( ! $featured_display_ids )",
    ):
        assert marker in text, marker


def test_pdf_actions_have_accessible_names():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "Actions for %s",
        "opens in a new tab",
        "aria-hidden=\"true\"",
        "Download PDF",
        "screen-reader-text",
    ):
        assert marker in text, marker


def test_repository_cache_generation_and_invalidation():
    text = HARDENING.read_text(encoding="utf-8")
    for marker in (
        "CACHE_GENERATION_OPTION",
        "cache_generation",
        "cache_key",
        "cache_get",
        "cache_set",
        "save_post_sc_foundation_doc",
        "set_object_terms",
        "created_sc_document_family",
        "created_sc_document_type",
    ):
        assert marker in text, marker


def test_expensive_repository_data_is_cached():
    text = REPOSITORY.read_text(encoding="utf-8")
    for marker in (
        "family-index|",
        "repository-counts",
        "meta-values|",
        "SC_Library_Document_Repository_Hardening::cache_get",
        "SC_Library_Document_Repository_Hardening::cache_set",
    ):
        assert marker in text, marker


def test_repository_cache_admin_control():
    repository = REPOSITORY.read_text(encoding="utf-8")
    hardening = HARDENING.read_text(encoding="utf-8")
    for marker in (
        "Clear Repository Cache",
        "sc_library_v231_clear_repository_cache",
        "Cache generation",
        "Public repository caches were cleared",
    ):
        assert marker in repository or marker in hardening, marker


def test_mobile_focus_reduced_motion_and_forced_colors_css():
    text = CSS.read_text(encoding="utf-8")
    for marker in (
        ":focus-visible",
        "min-height: 44px",
        "prefers-reduced-motion: reduce",
        "forced-colors: active",
        ".sc-repository-skip-link",
        ".sc-public-document-row__actions",
    ):
        assert marker in text, marker


def test_spartan_style_remains_intact():
    text = CSS.read_text(encoding="utf-8")
    assert "linear-gradient" not in text
    assert "border-radius: 12px" not in text
    assert "border-radius: 999px" not in text


def test_route_and_operational_layers_are_preserved():
    repository = REPOSITORY.read_text(encoding="utf-8")
    wrapper = WRAPPER.read_text(encoding="utf-8")
    assert "public const ROUTE_VERSION = '2.3.0'" in repository
    assert "class-sc-library-pdf-conversion-reliability.php" in wrapper
    assert "class-sc-library-pdf-bulk-import-repair.php" in wrapper


def test_version_marker():
    repository = REPOSITORY.read_text(encoding="utf-8")
    hardening = HARDENING.read_text(encoding="utf-8")
    assert "public const VERSION = '2.3.1'" in repository
    assert "public const VERSION = '2.3.1'" in hardening
    assert "SC_LIBRARY_VERSION : '2.3.1'" in WRAPPER.read_text(encoding="utf-8")


def main():
    tests = [
        test_required_files_exist,
        test_hardening_layer_is_loaded_without_replacing_repository,
        test_unique_repository_landmarks_and_skip_link,
        test_accessible_filter_form,
        test_result_status_and_accessible_pagination,
        test_featured_documents_do_not_repeat_across_pages,
        test_pdf_actions_have_accessible_names,
        test_repository_cache_generation_and_invalidation,
        test_expensive_repository_data_is_cached,
        test_repository_cache_admin_control,
        test_mobile_focus_reduced_motion_and_forced_colors_css,
        test_spartan_style_remains_intact,
        test_route_and_operational_layers_are_preserved,
        test_version_marker,
    ]
    for test in tests:
        test()
    print(f"Repository Interface and Accessibility Hardening checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
