from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]; PLUGIN=ROOT/'sustainable-catalyst-library'
MAIN=(PLUGIN/'sustainable-catalyst-library.php').read_text(); BOOT=(PLUGIN/'includes/class-sc-library-extension-bootstrap-v402.php').read_text(); MOD=(PLUGIN/'includes/class-sc-library-publications-research-graph.php').read_text(); ROUTE=(PLUGIN/'includes/class-sc-library-canonical-route-identity.php').read_text(); PUB=(PLUGIN/'includes/class-sc-library-publications.php').read_text(); GRAPH=(PLUGIN/'includes/class-sc-library-topics-concepts-relationships.php').read_text(); SOURCE=(PLUGIN/'includes/class-sc-library-citation-source-manager.php').read_text(); CLAIM=(PLUGIN/'includes/class-sc-library-evidence-claim-linking.php').read_text(); PATHWAY=(PLUGIN/'includes/class-sc-library-knowledge-pathways-article-maps.php').read_text(); PROJECT=(PLUGIN/'includes/class-sc-library-unified-research-projects-source-bundles.php').read_text(); PAGE=(ROOT/'RESEARCH_LIBRARY_PAGE_v4.3.37.html').read_text(); README=(PLUGIN/'readme.txt').read_text(); DOC=(ROOT/'PUBLICATIONS_RESEARCH_GRAPH_INTEGRATION_v4.3.37.md').read_text(); NOTES=(ROOT/'RELEASE_NOTES_KNOWLEDGE_LIBRARY_4.3.37.md').read_text(); TEMPLATE=(PLUGIN/'templates/field-spotlight-stage.php').read_text(); JS=(PLUGIN/'assets/js/sc-library-field-spotlights.js').read_text(); GJS=(PLUGIN/'assets/js/sc-library-publication-graph-v4337.js').read_text(); CSS=(PLUGIN/'assets/css/sc-library-publication-graph-v4337.css').read_text(); STACK=(PLUGIN/'templates/field-spotlights.php').read_text()

def test_release_identity_and_extension_registration():
    assert 'Version: 4.3.37' in MAIN and "define('SC_LIBRARY_VERSION', '4.3.37');" in MAIN
    assert 'class-sc-library-publications-research-graph.php' in BOOT and 'SC_Library_Publications_Research_Graph' in BOOT
    m=re.search(r'public const MODULE_COUNT = (\d+);',BOOT); assert m and int(m.group(1))==42

def test_graph_reuses_existing_canonical_research_systems():
    for marker in ['SC_Library_Publications::article_map_registry()', 'SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY', 'SC_Library_Citation_Source_Manager::get_source_data', 'SC_Library_Evidence_Claim_Linking::claim_is_public', 'SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE']:
        assert marker in MOD
    assert 'public static function article_map_registry()' in PUB and "public const TOPIC_TAXONOMY = 'sc_library_topic'" in GRAPH

def test_publication_graph_metadata_is_reference_only_and_bounded():
    for marker in ["META_SOURCE_IDS = '_sc_publication_research_source_ids_v4337'", "META_CLAIM_IDS = '_sc_publication_claim_ids_v4337'", "META_PATHWAY_IDS = '_sc_publication_pathway_ids_v4337'", 'public const MAX_LINKS = 40']:
        assert marker in MOD
    assert 'source body' not in MOD.lower() and 'copy_private' not in MOD.lower()

def test_public_graph_excludes_private_research_contracts():
    for marker in ["'private_research_excluded' => true", "'private_projects_excluded' => true", "'private_notebooks_excluded' => true", "'private_evidence_matrices_excluded' => true", "'personal_library_excluded' => true"]:
        assert marker in MOD
    assert 'Private Research Projects, Source Bundles, Reading Notebooks' in DOC

def test_no_automatic_source_claim_entity_or_relationship_inference():
    for marker in ["'automatic_claim_generation' => false", "'automatic_entity_inference' => false", "'automatic_source_inference' => false", "'automatic_inference' => false", "'automatic_publication' => false", "'automatic_workspace_write' => false"]:
        assert marker in MOD
    assert 'does not infer a source, claim, concept, entity, or relationship from article text' in DOC

def test_only_canonical_public_sources_claims_and_pathways_are_exposed():
    assert 'get_source_data( $id, false )' in MOD
    assert 'claim_is_public( $id )' in MOD and 'get_claim_data( $id, false )' in MOD
    assert "'publish' !== get_post_status( $id )" in MOD
    assert 'public static function claim_is_public' in CLAIM and 'public static function get_source_data' in SOURCE

def test_public_graph_manifest_is_deterministic_and_checksummed():
    assert "SCHEMA = 'sc-library-publication-research-graph/1.0'" in MOD
    assert "hash( 'sha256', wp_json_encode" in MOD and "'manifest_sha256'" in MOD
    assert "'counts' => array(" in MOD and "'article_map' =>" in MOD

def test_editorial_mapping_requires_explicit_capability_nonce_and_save():
    assert 'Publication ↔ Research Graph' in MOD and 'current_user_can( \'edit_post\', $post->ID )' in MOD
    assert 'wp_nonce_field( self::NONCE_ACTION . \'_\' . $post->ID' in MOD and 'wp_verify_nonce' in MOD
    assert 'sc_publication_graph_source_ids[]' in MOD and 'sc_publication_graph_claim_ids[]' in MOD and 'sc_publication_graph_pathway_ids[]' in MOD

def test_publication_posts_reuse_canonical_topic_taxonomy():
    assert "$types = array( 'post' );" in MOD
    assert 'register_taxonomy_for_object_type( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, $post_type )' in MOD
    assert 'Knowledge Topics use the standard Knowledge Topics taxonomy panel' in MOD

def test_field_spotlight_model_is_decorated_only_when_public_graph_exists():
    assert "add_filter( 'sc_library_field_spotlight_model'" in MOD
    assert "['research_graph_url'] = self::graph_url_for_article_url" in MOD
    assert 'has_public_graph' in MOD and "return $total > 0 || ! empty( $graph['article_map'] );" in MOD
    assert 'delete_transient( SC_Library_Field_Spotlights::MODEL_CACHE_KEY )' in MOD

def test_server_and_js_field_spotlight_runtime_keep_conditional_graph_link():
    assert "! empty( $article['research_graph_url'] )" in TEMPLATE and 'sc-field-spotlight__research-graph' in TEMPLATE
    assert 'if (article.research_graph_url)' in JS and "graph.textContent = 'Research graph →'" in JS
    assert 'data-sc-field-stack="v4.3.22.4"' in STACK

def test_public_rest_lookup_and_authenticated_project_handoff_exist():
    assert "REST_ROUTE = '/publications-research-graph'" in MOD
    assert "'permission_callback' => '__return_true'" in MOD and "/project-link'" in MOD and 'rest_signed_in' in MOD
    assert 'WP_REST_Server::CREATABLE' in MOD and 'WP_REST_Server::READABLE' in MOD

def test_project_handoff_requires_ownership_and_reuses_v4330_references_only_link():
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project' in MOD
    assert 'SC_Library_Unified_Research_Projects_Source_Bundles::add_link_for_user' in MOD
    assert "'family' => 'external'" in MOD and "'references_only' => true" in MOD
    assert 'publication_remains_public_canonical_record' in MOD and 'public static function add_link_for_user' in PROJECT

def test_research_library_page_adds_graph_after_open_learning_before_librarian():
    assert '[sc_publications_research_graph title="Publications ↔ Research Graph"]' in PAGE
    assert PAGE.index('id="open-learning-ii"') < PAGE.index('id="publication-research-graph-section"') < PAGE.index('id="research-front-door"')
    assert 'private Projects, Notebooks, Evidence Matrices' in PAGE or 'private Projects, Source Bundles, Reading Notebooks' in PAGE

def test_identity_health_and_readme_are_current_without_new_private_store():
    assert "public const VERSION = '4.3.37'" in ROUTE and 'data-sc-library-account-continuity="v4.3.37"' in ROUTE
    assert 'Publications ↔ Research Graph handoffs add only canonical public-publication references' in ROUTE
    assert "'publication_graph'" not in ROUTE
    assert 'Stable tag: 4.3.37' in README and '[sc_publications_research_graph]' in README and '/wp-json/sc-library/v1/publications-research-graph' in README

def test_front_end_is_accessible_same_origin_and_truthful():
    assert 'aria-live="polite"' in MOD and ':focus-visible' in CSS and 'min-height:44px' in CSS and '@media(max-width:760px)' in CSS and '@media(prefers-reduced-motion:reduce)' in CSS
    assert "credentials:'same-origin'" in GJS and "'X-WP-Nonce'" in GJS
    assert 'Public graph only' in MOD and 'Private research remains private' in MOD
    assert 'public records only' in NOTES.lower() and 'references-only' in NOTES.lower()
