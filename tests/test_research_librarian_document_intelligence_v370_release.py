from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
INTEL = PLUGIN / "includes" / "class-sc-library-research-librarian-document-intelligence.php"
ARCHIVES = PLUGIN / "includes" / "class-sc-library-institutional-collections-archives.php"
QUALITY = PLUGIN / "includes" / "class-sc-library-research-quality-governance.php"
HANDOFFS = PLUGIN / "includes" / "class-sc-library-cross-product-research-handoffs.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
PDF = PLUGIN / "includes" / "class-sc-library-pdf-to-document.php"
JS = PLUGIN / "assets" / "js" / "sc-library-research-librarian-document-intelligence.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-research-librarian-document-intelligence.css"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, INTEL, ARCHIVES, QUALITY, HANDOFFS, SEMANTIC, PDF, JS, CSS):
        assert path.is_file(), path


def test_load_order_and_version():
    text = read(WRAPPER)
    assert "class-sc-library-research-librarian-document-intelligence.php" in text
    assert "new SC_Library_Research_Librarian_Document_Intelligence" in text
    assert text.index("class-sc-library-institutional-collections-archives.php") < text.index("class-sc-library-research-librarian-document-intelligence.php")
    assert "SC_LIBRARY_VERSION : '3.8.0'" in text
    assert "public const VERSION = '3.7.0'" in read(INTEL)


def test_schemas():
    text = read(INTEL)
    for marker in (
        "sc-library-document-intelligence/1.0",
        "sc-library-document-section-index/1.0",
        "sc-library-document-chunk-index/1.0",
        "sc-library-title-aware-retrieval/1.0",
        "sc-library-document-comparison/1.0",
        "sc-platform-handoff/research-librarian-document/1.0",
        "sc-library-document-intelligence-dashboard/1.0",
    ):
        assert marker in text, marker


def test_record_types():
    text = read(INTEL)
    for marker in (
        "sc_doc_intel_job",
        "sc_doc_compare",
        "register_record_types",
        "Document Intelligence Jobs",
        "Document Comparisons",
    ):
        assert marker in text, marker


def test_statuses():
    text = read(INTEL)
    for marker in (
        "'pending'",
        "'indexing'",
        "'ready'",
        "'partial'",
        "'stale'",
        "'failed'",
        "'excluded'",
    ):
        assert marker in text, marker


def test_profile_metadata():
    text = read(INTEL)
    for marker in (
        "META_PROFILE",
        "META_SOURCE_HASH",
        "META_ANALYZER",
        "META_ANALYZED_AT",
        "META_SECTIONS",
        "META_CHUNKS",
        "META_SUMMARY",
        "META_KEY_POINTS",
        "META_QUESTIONS",
        "META_TERMS",
        "META_TITLE_ALIASES",
        "META_GAPS",
        "META_CITATION_SIGNALS",
        "META_PUBLIC",
        "META_EXCLUDED",
    ):
        assert marker in text, marker


def test_source_extraction():
    text = read(INTEL)
    for marker in (
        "document_source",
        "SC_Library_PDF_To_Document::META_RAW_TEXT",
        "SC_Library_PDF_To_Document::META_PAGE_COUNT",
        "MAX_SOURCE_CHARS = 500000",
        "source_truncated",
        "extracted-text",
        "document-content",
    ):
        assert marker in text, marker


def test_section_indexing():
    text = read(INTEL)
    for marker in (
        "extract_sections",
        "section_record",
        "MAX_SECTIONS = 120",
        "SC_HEADING",
        "section_id",
        "word_count",
        "text_hash",
    ):
        assert marker in text, marker


def test_chunk_indexing():
    text = read(INTEL)
    for marker in (
        "build_chunks",
        "MAX_CHUNKS = 500",
        "CHUNK_WORDS = 220",
        "CHUNK_OVERLAP = 40",
        "chunk_id",
        "word_start",
        "word_end",
        "section_title",
    ):
        assert marker in text, marker


def test_summary_and_key_points():
    text = read(INTEL)
    for marker in (
        "build_summary",
        "build_key_points",
        "sentence_split",
        "central",
        "concludes",
        "MAX_KEY_POINTS = 8",
    ):
        assert marker in text, marker


def test_questions_terms_aliases():
    text = read(INTEL)
    for marker in (
        "build_questions",
        "extract_terms",
        "title_aliases",
        "MAX_QUESTIONS = 8",
        "MAX_TERMS = 40",
        "MAX_ALIASES = 20",
        "What is the central argument or purpose",
        "How does the document define or use",
    ):
        assert marker in text, marker


def test_citation_signals():
    text = read(INTEL)
    for marker in (
        "citation_signals",
        "doi_count",
        "url_count",
        "numeric_citation_count",
        "author_year_count",
        "references_heading",
        "claim_like_sentences",
        "uncited_claim_signals",
    ):
        assert marker in text, marker


def test_gap_signals():
    text = read(INTEL)
    for marker in (
        "gap_signals",
        "insufficient-text",
        "missing-structure",
        "methods-not-detected",
        "limitations-not-detected",
        "citations-not-detected",
        "possible-citation-gaps",
        "topics-missing",
        "concepts-missing",
        "index-truncated",
    ):
        assert marker in text, marker


def test_title_aware_retrieval():
    text = read(INTEL)
    for marker in (
        "search_documents",
        "exact-title",
        "title-prefix",
        "title-contains",
        "exact-alias",
        "alias-match",
        "title-token-overlap",
        "document-term-overlap",
        "summary-overlap",
    ):
        assert marker in text, marker


def test_document_comparison():
    text = read(INTEL)
    for marker in (
        "compare_documents",
        "shared_terms",
        "shared_sections",
        "unique_terms",
        "term_similarity",
        "pairwise",
        "Similarity is based on structured titles",
    ):
        assert marker in text, marker


def test_research_librarian_handoffs():
    text = read(INTEL)
    for marker in (
        "sc_library_research_librarian_document_context",
        "sc_library_research_librarian_project_context",
        "filter_document_context",
        "filter_project_context",
        "document_intelligence",
        "HANDOFF_SCHEMA",
    ):
        assert marker in text, marker


def test_admin_center():
    text = read(INTEL)
    for marker in (
        "Research Librarian Document Intelligence",
        "Document Intelligence",
        "Document intelligence register",
        "Title-aware retrieval test",
        "Document comparison",
        "render_workspace",
        "render_document_meta_box",
        "render_document_status_box",
    ):
        assert marker in text, marker


def test_dashboard():
    text = read(INTEL)
    for marker in (
        "dashboard_report",
        "document_count",
        "ready_count",
        "stale_count",
        "pending_count",
        "failed_count",
        "gap_count",
        "section_count",
        "chunk_count",
    ):
        assert marker in text, marker


def test_stale_tracking():
    text = read(INTEL)
    for marker in (
        "mark_document_stale_on_save",
        "META_STALE_REASON",
        "The document was updated after its last intelligence analysis",
        "run_stale_reindex",
        "CRON_STALE",
        "'daily'",
    ):
        assert marker in text, marker


def test_reindex_jobs():
    text = read(INTEL)
    for marker in (
        "create_reindex_job",
        "run_job_batch",
        "job_state",
        "append_job_log",
        "META_JOB_ITEMS",
        "META_JOB_CURSOR",
        "complete-with-errors",
        "queued",
    ):
        assert marker in text, marker


def test_resumable_migration():
    text = read(INTEL)
    for marker in (
        "OPTION_MIGRATION",
        "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 20",
        "LOCK_SECONDS = 180",
        "run_migration_batch",
        "ID > %d",
        "catch ( Throwable $error )",
        "META_MIGRATED",
        "wp_schedule_event",
    ):
        assert marker in text, marker


def test_shortcodes():
    text = read(INTEL)
    for marker in (
        "add_shortcode( 'sc_document_intelligence'",
        "add_shortcode( 'sc_document_key_points'",
        "add_shortcode( 'sc_document_research_questions'",
        "add_shortcode( 'sc_document_comparison'",
        "Generated document intelligence is a navigation and review aid",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(INTEL)
    for marker in (
        "'/documents/(?P<id>\\d+)/intelligence'",
        "'/documents/(?P<id>\\d+)/sections'",
        "'/documents/(?P<id>\\d+)/chunks'",
        "'/document-intelligence/search'",
        "'/document-intelligence/compare'",
        "'/document-intelligence/jobs'",
        "'/document-intelligence/jobs/(?P<id>\\d+)'",
        "'/document-intelligence/dashboard'",
        "'/document-intelligence/migration'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(INTEL)
    for marker in (
        "rest_can_read_intelligence",
        "rest_can_edit_document",
        "rest_can_edit_job",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_ajax_actions():
    text = read(INTEL)
    js = read(JS)
    for marker in (
        "sc_library_v370_analyze_document",
        "sc_library_v370_run_migration",
        "sc_library_v370_search_documents",
        "sc_library_v370_compare_documents",
    ):
        assert marker in text, marker
        assert marker in js, marker


def test_cli_commands():
    text = read(INTEL)
    for marker in (
        "sc-library document-intelligence analyze",
        "sc-library document-intelligence search",
        "sc-library document-intelligence compare",
        "sc-library document-intelligence job-create",
        "sc-library document-intelligence job-run",
        "sc-library document-intelligence migrate",
        "sc-library document-intelligence dashboard",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_public_privacy_boundaries():
    text = read(INTEL)
    for marker in (
        "document_is_public",
        "public_profile",
        "META_PUBLIC",
        "META_EXCLUDED",
        "chunk_index",
        "rest_can_edit_document",
    ):
        assert marker in text, marker


def test_cleanup():
    text = read(INTEL)
    for marker in (
        "cleanup_deleted_document",
        "before_delete_post",
        "META_COMPARISON_DOCUMENT_IDS",
        "META_JOB_ITEMS",
        "wp_delete_post",
    ):
        assert marker in text, marker


def test_provider_extension_boundary():
    text = read(INTEL)
    for marker in (
        "sc_library_document_intelligence_analysis",
        "trusted local or remote provider adapter",
        "preserve the base schema",
        "deterministic-structure-and-retrieval",
    ):
        assert marker in text, marker


def test_accessible_responsive_ui():
    css = read(CSS)
    js = read(JS)
    for marker in (
        ".sc-doc-intel-center",
        ".sc-doc-intel-profile",
        ".sc-doc-intel-list-panel",
        ".sc-doc-intel-comparison",
        "@media (max-width: 700px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in css, marker
    assert "escapeHtml" in js
    assert "aria-live" in read(INTEL)
    assert "linear-gradient" not in css


def test_retained_release_layers():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-institutional-collections-archives.php",
        "class-sc-library-research-quality-governance.php",
        "class-sc-library-cross-product-research-handoffs.php",
        "class-sc-library-topics-concepts-relationships.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.6.0'" in read(ARCHIVES)
    assert "public const VERSION = '3.5.0'" in read(QUALITY)
    assert "public const VERSION = '3.4.0'" in read(HANDOFFS)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_schemas,
        test_record_types,
        test_statuses,
        test_profile_metadata,
        test_source_extraction,
        test_section_indexing,
        test_chunk_indexing,
        test_summary_and_key_points,
        test_questions_terms_aliases,
        test_citation_signals,
        test_gap_signals,
        test_title_aware_retrieval,
        test_document_comparison,
        test_research_librarian_handoffs,
        test_admin_center,
        test_dashboard,
        test_stale_tracking,
        test_reindex_jobs,
        test_resumable_migration,
        test_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_ajax_actions,
        test_cli_commands,
        test_public_privacy_boundaries,
        test_cleanup,
        test_provider_extension_boundary,
        test_accessible_responsive_ui,
        test_retained_release_layers,
    ]
    for test in tests:
        test()
    print(f"Research Librarian Document Intelligence checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
