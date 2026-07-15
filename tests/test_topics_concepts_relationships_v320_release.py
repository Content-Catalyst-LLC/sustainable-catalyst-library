from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
SOURCE_INTEGRITY = PLUGIN / "includes" / "class-sc-library-source-versioning-integrity.php"
JS = PLUGIN / "assets" / "js" / "sc-library-topics-concepts-relationships.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-topics-concepts-relationships.css"
TEMPLATE = PLUGIN / "templates" / "single-sc_knowledge_node.php"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, MANAGER, EVIDENCE, SEMANTIC, SOURCE_INTEGRITY, JS, CSS, TEMPLATE):
        assert path.is_file(), path


def test_load_order_and_version():
    wrapper = read(WRAPPER)
    assert "class-sc-library-source-versioning-integrity.php" in wrapper
    assert "class-sc-library-topics-concepts-relationships.php" in wrapper
    assert wrapper.index("class-sc-library-source-versioning-integrity.php") < wrapper.index("class-sc-library-topics-concepts-relationships.php")
    assert "new SC_Library_Topics_Concepts_Relationships" in wrapper
    assert "SC_LIBRARY_VERSION : '3.3.0'" in wrapper
    assert "public const VERSION = '3.2.0'" in read(SEMANTIC)


def test_semantic_schemas():
    text = read(SEMANTIC)
    for marker in (
        "sc-library-knowledge-node/1.0",
        "sc-library-knowledge-relation/1.0",
        "sc-library-topic-coverage/1.0",
        "sc-library-topic-migration/1.0",
    ):
        assert marker in text, marker


def test_content_types_and_taxonomy():
    text = read(SEMANTIC)
    for marker in (
        "sc_library_topic",
        "sc_library_concept",
        "sc_named_entity",
        "sc_control_vocab",
        "sc_knowledge_rel",
        "register_taxonomy_for_object_type",
        "Knowledge Topics",
        "Broader Topic",
    ):
        assert marker in text, marker


def test_topic_attaches_to_core_records():
    text = read(SEMANTIC)
    for marker in (
        "SC_Library_Foundation_Pages::POST_TYPE",
        "SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE",
        "SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE",
        "SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE",
        "SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE",
    ):
        assert marker in text, marker


def test_concept_model():
    text = read(SEMANTIC)
    for marker in (
        "META_CONCEPT_TYPE",
        "META_CONCEPT_STATUS",
        "META_CONCEPT_ALT_LABELS",
        "META_CONCEPT_SCOPE_NOTE",
        "META_CONCEPT_URI",
        "META_CONCEPT_VOCABULARY_ID",
        "principle",
        "theory",
        "method",
        "metric",
        "policy",
        "legal",
    ):
        assert marker in text, marker


def test_named_entity_model():
    text = read(SEMANTIC)
    for marker in (
        "META_ENTITY_TYPE",
        "META_ENTITY_ALIASES",
        "META_ENTITY_URI",
        "META_ENTITY_VOCABULARY_ID",
        "person",
        "organization",
        "place",
        "jurisdiction",
        "instrument",
        "dataset",
    ):
        assert marker in text, marker


def test_controlled_vocabulary_model():
    text = read(SEMANTIC)
    for marker in (
        "META_VOCABULARY_PREFIX",
        "META_VOCABULARY_URI",
        "META_VOCABULARY_VERSION",
        "META_VOCABULARY_LICENSE",
        "META_VOCABULARY_LANGUAGE",
        "META_VOCABULARY_AUTHORITY",
        "Controlled Vocabulary Record",
    ):
        assert marker in text, marker


def test_topic_term_metadata():
    text = read(SEMANTIC)
    for marker in (
        "META_TOPIC_ALT_LABELS",
        "META_TOPIC_SCOPE_NOTE",
        "META_TOPIC_URI",
        "META_TOPIC_VOCABULARY_ID",
        "META_TOPIC_STATUS",
        "topic_add_fields",
        "topic_edit_fields",
        "save_topic_fields",
    ):
        assert marker in text, marker


def test_relation_types():
    text = read(SEMANTIC)
    for marker in (
        "related-to",
        "contrasts-with",
        "broader-than",
        "narrower-than",
        "defines",
        "exemplifies",
        "about-topic",
        "uses-concept",
        "mentions-entity",
        "cites",
        "derives-from",
        "summarizes",
        "translates",
        "supersedes",
        "precedes",
        "continues",
        "contains",
        "companion-to",
        "methodology-for",
    ):
        assert marker in text, marker


def test_document_to_document_relationships():
    text = read(SEMANTIC)
    assert "Outgoing typed relationships" in text
    assert "Target type" in text
    assert "Target ID" in text
    assert "document" in text
    assert "save_relationship" in text
    assert "replace_outgoing_relationships" in text


def test_relation_integrity_and_audit():
    text = read(SEMANTIC)
    for marker in (
        "META_RELATION_CREATED_AT",
        "META_RELATION_CREATED_BY",
        "META_RELATION_UPDATED_AT",
        "META_RELATION_UPDATED_BY",
        "MAX_RELATIONS_PER_NODE = 200",
        "self_knowledge_relationship",
        "$seen[ $key ]",
        "weight",
        "public",
    ):
        assert marker in text, marker


def test_source_topic_and_claim_concept_links():
    semantic = read(SEMANTIC)
    manager = read(MANAGER)
    evidence = read(EVIDENCE)
    assert "META_CONCEPT_IDS" in semantic
    assert "wp_set_object_terms( $post_id, $topic_ids, self::TOPIC_TAXONOMY" in semantic
    assert "add_filter( 'sc_library_source_data'" in semantic
    assert "add_filter( 'sc_library_claim_data'" in semantic
    assert "apply_filters( 'sc_library_claim_data'" in evidence
    assert "apply_filters( 'sc_library_source_data'" in manager


def test_public_document_source_claim_integration():
    wrapper = read(WRAPPER)
    manager = read(MANAGER)
    evidence = read(EVIDENCE)
    assert "render_public_node_panel( 'document', $post_id" in wrapper
    assert "render_public_node_panel( 'source', $source_id" in manager
    assert "render_public_node_panel( 'claim', $claim['id'], true" in evidence


def test_relationship_browser():
    text = read(SEMANTIC)
    js = read(JS)
    for marker in (
        "Relationship Browser",
        "shortcode_relationship_browser",
        "render_browser_result",
        "ajax_browse_node",
        "data-sc-knowledge-browser",
        "data-sc-knowledge-browser-form",
        "sc_library_v320_browse_node",
    ):
        assert marker in text or marker in js, marker


def test_coverage_and_gap_analysis():
    text = read(SEMANTIC)
    for marker in (
        "coverage_report",
        "project_coverage_report",
        "topic_counts",
        "concept_counts",
        "legacy_source_gap_count",
        "no-documents",
        "no-sources",
        "no-claims",
        "no-projects",
        "no-evidence-base",
        "no-topics",
        "no-concepts",
    ):
        assert marker in text, marker


def test_coverage_cache_and_rewrite_reliability():
    text = read(SEMANTIC)
    for marker in (
        "OPTION_ROUTES_VERSION",
        "TRANSIENT_PUBLIC_COVERAGE",
        "COVERAGE_CACHE_SECONDS = 600",
        "maybe_flush_rewrite_rules",
        "flush_rewrite_rules( false )",
        "invalidate_coverage_cache",
        "get_transient( self::TRANSIENT_PUBLIC_COVERAGE )",
        "set_transient( self::TRANSIENT_PUBLIC_COVERAGE",
        "set_object_terms",
        "transition_post_status",
    ):
        assert marker in text, marker


def test_migration_is_resumable_and_non_destructive():
    text = read(SEMANTIC)
    for marker in (
        "OPTION_MIGRATION_STATE",
        "TRANSIENT_MIGRATION_LOCK",
        "MIGRATION_BATCH = 25",
        "LOCK_SECONDS = 180",
        "migrate_topic_terms",
        "migrate_source_assignments",
        "migrate_document_tags",
        "SOURCE_TOPIC_TAXONOMY",
        "META_TOPIC_LEGACY_TERM_ID",
        "wp_schedule_event",
        "catch ( Throwable $error )",
    ):
        assert marker in text, marker
    assert "unregister_taxonomy" not in text


def test_migration_uses_stable_id_cursors():
    text = read(SEMANTIC)
    assert text.count("ID > %d") >= 2
    assert "orderby'    => 'term_id'" in text
    assert "'offset'     => absint( $state['cursor'] )" in text


def test_public_shortcodes():
    text = read(SEMANTIC)
    for marker in (
        "add_shortcode( 'sc_knowledge_relationship_browser'",
        "add_shortcode( 'sc_topic_coverage'",
        "add_shortcode( 'sc_knowledge_concept'",
        "shortcode_relationship_browser",
        "shortcode_topic_coverage",
        "shortcode_concept",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(SEMANTIC)
    for marker in (
        "'/knowledge/nodes/(?P<kind>[a-z-]+)/(?P<id>\\d+)'",
        "'/knowledge/relationships'",
        "'/knowledge/topics'",
        "'/knowledge/concepts'",
        "'/knowledge/entities'",
        "'/knowledge/coverage'",
        "'/projects/(?P<id>\\d+)/knowledge-coverage'",
        "'/knowledge/migration'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(SEMANTIC)
    for marker in (
        "rest_can_read_node",
        "rest_can_read_project",
        "current_user_can( 'edit_posts' )",
        "can_edit_node",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_wp_cli():
    text = read(SEMANTIC)
    for marker in (
        "sc-library knowledge migrate-topics",
        "sc-library knowledge node",
        "sc-library knowledge coverage",
        "sc-library knowledge relate",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_dynamic_editor_client():
    js = read(JS)
    for marker in (
        "renumberRelations",
        "data-sc-add-semantic-relation",
        "data-sc-remove-semantic-relation",
        "sc_library_relations",
        "sc_library_v320_run_topic_migration",
        "sc_library_v320_reset_topic_migration",
    ):
        assert marker in js, marker


def test_responsive_public_interface():
    css = read(CSS)
    for marker in (
        ".sc-semantic-editor",
        ".sc-semantic-relation-row",
        ".sc-knowledge-browser",
        ".sc-topic-coverage",
        ".sc-public-knowledge-panel",
        ".sc-public-knowledge-record",
        "@media (max-width: 760px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in css, marker
    assert "linear-gradient" not in css


def test_public_template():
    text = read(TEMPLATE)
    assert "SC_Library_Topics_Concepts_Relationships::post_kind" in text
    assert "SC_Library_Topics_Concepts_Relationships::render_public_record" in text
    assert "get_header" in text
    assert "get_footer" in text


def test_deletion_cleanup():
    text = read(SEMANTIC)
    for marker in (
        "cleanup_deleted_node",
        "cleanup_deleted_topic",
        "before_delete_post",
        "delete_" ,
        "wp_delete_post",
    ):
        assert marker in text, marker


def test_nonce_and_capability_boundaries():
    text = read(SEMANTIC)
    for marker in (
        "sc_library_semantic_nonce",
        "sc_library_vocabulary_nonce",
        "wp_verify_nonce",
        "current_user_can( 'edit_post', $post_id )",
        "current_user_can( 'manage_categories' )",
        "check_ajax_referer",
    ):
        assert marker in text, marker


def test_retained_subsystems():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-connected-research-environment.php",
        "class-sc-library-connected-research-reliability.php",
        "class-sc-library-source-versioning-integrity.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.1.0'" in read(SOURCE_INTEGRITY)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_semantic_schemas,
        test_content_types_and_taxonomy,
        test_topic_attaches_to_core_records,
        test_concept_model,
        test_named_entity_model,
        test_controlled_vocabulary_model,
        test_topic_term_metadata,
        test_relation_types,
        test_document_to_document_relationships,
        test_relation_integrity_and_audit,
        test_source_topic_and_claim_concept_links,
        test_public_document_source_claim_integration,
        test_relationship_browser,
        test_coverage_and_gap_analysis,
        test_coverage_cache_and_rewrite_reliability,
        test_migration_is_resumable_and_non_destructive,
        test_migration_uses_stable_id_cursors,
        test_public_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_wp_cli,
        test_dynamic_editor_client,
        test_responsive_public_interface,
        test_public_template,
        test_deletion_cleanup,
        test_nonce_and_capability_boundaries,
        test_retained_subsystems,
    ]
    for test in tests:
        test()
    print(f"Topics, Concepts, and Document Relationships checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
