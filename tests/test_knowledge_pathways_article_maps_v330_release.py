from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sustainable-catalyst-library"
WRAPPER = PLUGIN / "includes" / "class-sc-library-foundation-pages.php"
PATHWAYS = PLUGIN / "includes" / "class-sc-library-knowledge-pathways-article-maps.php"
SEMANTIC = PLUGIN / "includes" / "class-sc-library-topics-concepts-relationships.php"
MANAGER = PLUGIN / "includes" / "class-sc-library-citation-source-manager.php"
EVIDENCE = PLUGIN / "includes" / "class-sc-library-evidence-claim-linking.php"
JS = PLUGIN / "assets" / "js" / "sc-library-knowledge-pathways-article-maps.js"
CSS = PLUGIN / "assets" / "css" / "sc-library-knowledge-pathways-article-maps.css"
TEMPLATE = PLUGIN / "templates" / "single-sc_knowledge_pathway.php"


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def test_required_files():
    for path in (WRAPPER, PATHWAYS, SEMANTIC, MANAGER, EVIDENCE, JS, CSS, TEMPLATE):
        assert path.is_file(), path


def test_load_order_and_version():
    wrapper = read(WRAPPER)
    assert "class-sc-library-topics-concepts-relationships.php" in wrapper
    assert "class-sc-library-knowledge-pathways-article-maps.php" in wrapper
    assert wrapper.index("class-sc-library-topics-concepts-relationships.php") < wrapper.index("class-sc-library-knowledge-pathways-article-maps.php")
    assert "new SC_Library_Knowledge_Pathways_Article_Maps" in wrapper
    assert "SC_LIBRARY_VERSION : '3.8.0'" in wrapper
    assert "public const VERSION = '3.3.0'" in read(PATHWAYS)


def test_schemas_and_types():
    text = read(PATHWAYS)
    for marker in (
        "sc-library-knowledge-pathway/1.0",
        "sc-library-pathway-step/1.0",
        "sc-library-article-map/1.0",
        "sc-library-pathway-recommendations/1.0",
        "sc_knowledge_path",
        "sc_pathway_type",
    ):
        assert marker in text, marker


def test_curated_pathway_types():
    text = read(PATHWAYS)
    for marker in (
        "'orientation'",
        "'learning-path'",
        "'research-path'",
        "'document-series'",
        "'methodology-guide'",
        "'evidence-trail'",
        "'project-path'",
    ):
        assert marker in text, marker


def test_levels_and_stages():
    text = read(PATHWAYS)
    for marker in (
        "'introductory'",
        "'foundational'",
        "'intermediate'",
        "'advanced'",
        "'expert'",
        "'orientation'",
        "'foundation'",
        "'core'",
        "'evidence'",
        "'application'",
        "'analysis'",
        "'synthesis'",
        "'extension'",
    ):
        assert marker in text, marker


def test_step_model_and_bounds():
    text = read(PATHWAYS)
    for marker in (
        "MAX_STEPS = 200",
        "MAX_OUTCOMES = 50",
        "MAX_PATHWAY_LINKS = 50",
        "META_STEPS",
        "META_NODE_KEYS",
        "sanitize_steps",
        "wp_generate_uuid4",
        "node_key",
        "required",
        "minutes",
        "difficulty",
    ):
        assert marker in text, marker


def test_prerequisites_and_continuations():
    text = read(PATHWAYS)
    for marker in (
        "META_PREREQUISITE_IDS",
        "META_CONTINUATION_IDS",
        "Recommended prerequisites",
        "Continue from here",
        "pathway_id_list",
    ):
        assert marker in text, marker


def test_map_modes_and_map_schema():
    text = read(PATHWAYS)
    for marker in (
        "'sequence'",
        "'stages'",
        "'network'",
        "'compact'",
        "map_data",
        "render_map",
        "sequence-",
        "semantic-",
        "stage_labels",
        "generated_at",
    ):
        assert marker in text, marker


def test_accessible_svg_and_fallback():
    text = read(PATHWAYS)
    for marker in (
        "role=\"img\"",
        "<title id=\"sc-map-title-",
        "<desc id=\"sc-map-desc-",
        "data-sc-map-list",
        "Toggle accessible list",
        "tabindex=\"0\"",
    ):
        assert marker in text, marker


def test_project_derived_pathways():
    text = read(PATHWAYS)
    for marker in (
        "derive_from_project",
        "META_DERIVED_PROJECT_ID",
        "Connected_Research_Environment::project_data",
        "Research Pathway: %s",
        "Create a pathway from a Research Project",
        "post_status'  => 'draft'",
    ):
        assert marker in text, marker


def test_project_derivation_uses_all_research_layers():
    text = read(PATHWAYS)
    for marker in (
        "document_ids",
        "source_entries",
        "claim_ids",
        "evidence_ids",
        "'kind' => 'project'",
        "'kind' => 'document'",
        "'kind' => 'source'",
        "'kind' => 'claim'",
        "'kind' => 'evidence'",
    ):
        assert marker in text, marker


def test_node_membership_panels():
    wrapper = read(WRAPPER)
    manager = read(MANAGER)
    evidence = read(EVIDENCE)
    assert "render_node_pathways( 'document'" in wrapper
    assert "render_node_pathways( 'source'" in manager
    assert "render_node_pathways( 'claim'" in evidence
    assert "pathways_for_node" in read(PATHWAYS)


def test_research_librarian_recommendation_hook():
    text = read(PATHWAYS)
    for marker in (
        "sc_library_research_librarian_pathway_recommendations",
        "filter_research_librarian_recommendations",
        "recommend_pathways",
        "Shared Knowledge Topics",
        "Shared Concepts",
        "Contains a relevant record",
        "Matching level",
    ):
        assert marker in text, marker


def test_recommendation_inputs_and_cache():
    text = read(PATHWAYS)
    for marker in (
        "topic_ids",
        "concept_ids",
        "entity_ids",
        "node_keys",
        "query",
        "audience",
        "RECOMMENDATION_CACHE_SECONDS = 600",
        "set_transient",
        "invalidate_recommendation_cache",
    ):
        assert marker in text, marker


def test_admin_workspace_and_editor():
    text = read(PATHWAYS)
    for marker in (
        "Knowledge Pathways and Article Maps",
        "Pathway Design and Ordered Steps",
        "Article Map and Recommendations",
        "Pathway registry",
        "Add Knowledge Pathway",
        "data-sc-add-pathway-step",
        "data-sc-search-pathway-node",
        "data-sc-preview-pathway-map",
    ):
        assert marker in text, marker


def test_dynamic_client():
    text = read(JS)
    for marker in (
        "renumber",
        "data-sc-add-pathway-step",
        "data-sc-remove-pathway-step",
        "sc_library_v330_search_nodes",
        "sc_library_v330_derive_pathway",
        "sc_library_v330_preview_map",
        "dragstart",
        "dragover",
        "data-sc-toggle-map-list",
    ):
        assert marker in text, marker


def test_public_shortcodes():
    text = read(PATHWAYS)
    for marker in (
        "add_shortcode( 'sc_knowledge_pathway'",
        "add_shortcode( 'sc_article_map'",
        "add_shortcode( 'sc_pathway_recommendations'",
        "include_private",
        "nocache_headers",
    ):
        assert marker in text, marker


def test_rest_routes():
    text = read(PATHWAYS)
    for marker in (
        "'/knowledge/pathways'",
        "'/knowledge/pathways/(?P<id>\\d+)'",
        "'/knowledge/pathways/(?P<id>\\d+)/map'",
        "'/knowledge/pathways/recommendations'",
        "'/knowledge/nodes/(?P<kind>[a-z-]+)/(?P<id>\\d+)/pathways'",
        "'/projects/(?P<id>\\d+)/pathway'",
    ):
        assert marker in text, marker


def test_rest_permissions_and_cache():
    text = read(PATHWAYS)
    for marker in (
        "rest_can_read_pathway",
        "rest_can_edit_pathway",
        "rest_can_edit_project",
        "protect_private_rest_responses",
        "no-store, no-cache, must-revalidate, private",
        "Cookie, Authorization",
    ):
        assert marker in text, marker


def test_wp_cli():
    text = read(PATHWAYS)
    for marker in (
        "sc-library pathways list",
        "sc-library pathways map",
        "sc-library pathways derive",
        "sc-library pathways recommend",
        "WP_CLI::add_command",
    ):
        assert marker in text, marker


def test_rewrite_activation():
    text = read(PATHWAYS)
    assert "OPTION_ROUTES_VERSION" in text
    assert "maybe_flush_rewrite_rules" in text
    assert "flush_rewrite_rules( false )" in text


def test_deletion_cleanup():
    text = read(PATHWAYS)
    for marker in (
        "cleanup_deleted_pathway",
        "META_PREREQUISITE_IDS",
        "META_CONTINUATION_IDS",
        "pathway:",
        "invalidate_recommendation_cache",
    ):
        assert marker in text, marker


def test_public_template():
    text = read(TEMPLATE)
    assert "SC_Library_Knowledge_Pathways_Article_Maps::render_pathway" in text
    assert "get_header" in text
    assert "get_footer" in text


def test_responsive_spartan_styles():
    text = read(CSS)
    for marker in (
        ".sc-pathway-editor",
        ".sc-public-pathway",
        ".sc-article-map",
        ".sc-pathway-step-card",
        ".sc-node-pathways",
        ".sc-pathway-recommendations",
        "@media (max-width: 780px)",
        "@media print",
        "focus-visible",
    ):
        assert marker in text, marker
    assert "linear-gradient" not in text


def test_retained_systems():
    wrapper = read(WRAPPER)
    for marker in (
        "class-sc-library-citation-source-manager.php",
        "class-sc-library-scholarly-library-connectors.php",
        "class-sc-library-connector-holdings-reliability.php",
        "class-sc-library-evidence-claim-linking.php",
        "class-sc-library-connected-research-environment.php",
        "class-sc-library-connected-research-reliability.php",
        "class-sc-library-source-versioning-integrity.php",
        "class-sc-library-topics-concepts-relationships.php",
    ):
        assert marker in wrapper, marker
    assert "public const VERSION = '3.2.0'" in read(SEMANTIC)


def main():
    tests = [
        test_required_files,
        test_load_order_and_version,
        test_schemas_and_types,
        test_curated_pathway_types,
        test_levels_and_stages,
        test_step_model_and_bounds,
        test_prerequisites_and_continuations,
        test_map_modes_and_map_schema,
        test_accessible_svg_and_fallback,
        test_project_derived_pathways,
        test_project_derivation_uses_all_research_layers,
        test_node_membership_panels,
        test_research_librarian_recommendation_hook,
        test_recommendation_inputs_and_cache,
        test_admin_workspace_and_editor,
        test_dynamic_client,
        test_public_shortcodes,
        test_rest_routes,
        test_rest_permissions_and_cache,
        test_wp_cli,
        test_rewrite_activation,
        test_deletion_cleanup,
        test_public_template,
        test_responsive_spartan_styles,
        test_retained_systems,
    ]
    for test in tests:
        test()
    print(f"Knowledge Pathways and Article Maps checks passed: {len(tests)}")


if __name__ == "__main__":
    main()
