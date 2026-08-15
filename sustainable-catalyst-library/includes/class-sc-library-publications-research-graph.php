<?php
/** Publications ↔ Research Graph Integration — v4.3.37. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications_Research_Graph {
    public const VERSION = '4.3.37';
    public const SCHEMA = 'sc-library-publication-research-graph/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/publications-research-graph';
    public const META_MAP_KEY = '_sc_publication_article_map_key_v4337';
    public const META_SOURCE_IDS = '_sc_publication_research_source_ids_v4337';
    public const META_CLAIM_IDS = '_sc_publication_claim_ids_v4337';
    public const META_PATHWAY_IDS = '_sc_publication_pathway_ids_v4337';
    public const META_CONCEPT_IDS = '_sc_library_concept_ids';
    public const META_ENTITY_IDS = '_sc_library_entity_ids';
    public const NONCE_ACTION = 'sc_library_publication_graph_v4337';
    public const MAX_LINKS = 40;

    public function __construct() {
        add_action( 'init', array( $this, 'attach_topics_to_publications' ), 140 );
        add_action( 'init', array( $this, 'invalidate_release_caches_once' ), 145 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 140 );
        add_action( 'save_post', array( $this, 'save_publication_graph' ), 160, 3 );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_shortcode( 'sc_publications_research_graph', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_field_spotlight_model', array( $this, 'decorate_field_spotlight_model' ), 35, 2 );
    }

    public static function contract(): array {
        return array(
            'schema' => self::SCHEMA,
            'public_graph_only' => true,
            'private_research_excluded' => true,
            'manual_editorial_links' => true,
            'canonical_topics_reused' => true,
            'canonical_concepts_reused' => true,
            'canonical_entities_reused' => true,
            'citation_sources_reused' => true,
            'public_claims_reused' => true,
            'knowledge_pathways_reused' => true,
            'project_handoff_references_only' => true,
            'automatic_private_graph_exposure' => false,
            'automatic_claim_generation' => false,
            'automatic_entity_inference' => false,
            'automatic_source_inference' => false,
            'automatic_publication' => false,
            'automatic_workspace_write' => false,
        );
    }

    public static function publication_post_types(): array {
        $types = array( 'post' );
        return array_values( array_unique( array_filter( array_map( 'sanitize_key', apply_filters( 'sc_library_publication_graph_post_types', $types ) ) ) ) );
    }

    public function attach_topics_to_publications(): void {
        if ( ! class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) { return; }
        foreach ( self::publication_post_types() as $post_type ) {
            if ( post_type_exists( $post_type ) ) {
                register_taxonomy_for_object_type( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, $post_type );
            }
        }
    }

    public function invalidate_release_caches_once(): void {
        $option = 'sc_library_publication_graph_cache_marker_v4337';
        if ( get_option( $option ) === self::VERSION ) { return; }
        $this->invalidate_publication_caches();
        update_option( $option, self::VERSION, false );
    }

    private function invalidate_publication_caches(): void {
        if ( class_exists( 'SC_Library_Field_Spotlights' ) ) {
            delete_transient( SC_Library_Field_Spotlights::MODEL_CACHE_KEY );
            delete_transient( SC_Library_Field_Spotlights::PUBLIC_CACHE_KEY );
        }
        if ( class_exists( 'SC_Library_Publications' ) ) { delete_transient( SC_Library_Publications::CACHE_KEY ); }
    }

    public function register_assets(): void {
        wp_register_style( 'sc-library-publication-graph-v4337', SC_LIBRARY_URL . 'assets/css/sc-library-publication-graph-v4337.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-publication-graph-v4337', SC_LIBRARY_URL . 'assets/js/sc-library-publication-graph-v4337.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_meta_box(): void {
        foreach ( self::publication_post_types() as $post_type ) {
            add_meta_box( 'sc-library-publication-research-graph', __( 'Publication ↔ Research Graph', 'sustainable-catalyst-library' ), array( $this, 'render_meta_box' ), $post_type, 'normal', 'default' );
        }
    }

    private static function id_list( $value ): array {
        if ( ! is_array( $value ) ) { $value = array(); }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
        return array_slice( $ids, 0, self::MAX_LINKS );
    }

    private static function selected_options( string $post_type, array $selected, bool $public_only = true ): array {
        $statuses = $public_only ? array( 'publish' ) : array( 'publish', 'draft', 'pending', 'private' );
        $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => $statuses, 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true, 'no_found_rows' => true ) );
        $out = array();
        foreach ( $posts as $post ) {
            $out[] = array( 'id' => absint( $post->ID ), 'title' => sanitize_text_field( $post->post_title ), 'selected' => in_array( absint( $post->ID ), $selected, true ) );
        }
        return $out;
    }

    private static function map_registry(): array {
        return class_exists( 'SC_Library_Publications' ) ? SC_Library_Publications::article_map_registry() : array();
    }

    public function render_meta_box( WP_Post $post ): void {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) { return; }
        wp_nonce_field( self::NONCE_ACTION . '_' . $post->ID, 'sc_library_publication_graph_nonce' );
        $map_key = sanitize_title( (string) get_post_meta( $post->ID, self::META_MAP_KEY, true ) );
        $source_ids = self::id_list( get_post_meta( $post->ID, self::META_SOURCE_IDS, true ) );
        $claim_ids = self::id_list( get_post_meta( $post->ID, self::META_CLAIM_IDS, true ) );
        $pathway_ids = self::id_list( get_post_meta( $post->ID, self::META_PATHWAY_IDS, true ) );
        $concept_ids = self::id_list( get_post_meta( $post->ID, self::META_CONCEPT_IDS, true ) );
        $entity_ids = self::id_list( get_post_meta( $post->ID, self::META_ENTITY_IDS, true ) );
        $sources = class_exists( 'SC_Library_Citation_Source_Manager' ) ? self::selected_options( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE, $source_ids, true ) : array();
        if ( class_exists( 'SC_Library_Citation_Source_Manager' ) ) { $sources = array_values( array_filter( $sources, static fn( $option ) => (bool) SC_Library_Citation_Source_Manager::get_source_data( absint( $option['id'] ?? 0 ), false ) ) ); }
        $claims = class_exists( 'SC_Library_Evidence_Claim_Linking' ) ? self::selected_options( SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE, $claim_ids, true ) : array();
        if ( class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) { $claims = array_values( array_filter( $claims, static fn( $option ) => SC_Library_Evidence_Claim_Linking::claim_is_public( absint( $option['id'] ?? 0 ) ) ) ); }
        $pathways = class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? self::selected_options( SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE, $pathway_ids, true ) : array();
        $concepts = class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? self::selected_options( SC_Library_Topics_Concepts_Relationships::CONCEPT_POST_TYPE, $concept_ids, true ) : array();
        $entities = class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? self::selected_options( SC_Library_Topics_Concepts_Relationships::ENTITY_POST_TYPE, $entity_ids, true ) : array();
        ?>
        <div class="sc-publication-graph-admin">
            <p><?php esc_html_e( 'Connect this public publication to the canonical Research Graph. Only explicitly selected public records are exposed. Private projects, notebooks, evidence matrices, personal-library items, queue entries, and source bundles are never published by this panel.', 'sustainable-catalyst-library' ); ?></p>
            <p><label><strong><?php esc_html_e( 'Article Map', 'sustainable-catalyst-library' ); ?></strong><br><select name="sc_publication_graph_map_key"><option value=""><?php esc_html_e( 'No Article Map selected', 'sustainable-catalyst-library' ); ?></option><?php foreach ( self::map_registry() as $key => $map ) : ?><option value="<?php echo esc_attr( sanitize_title( (string) $key ) ); ?>" <?php selected( $map_key, sanitize_title( (string) $key ) ); ?>><?php echo esc_html( (string) ( $map['field'] ?? '' ) . ' — ' . (string) ( $map['title'] ?? $key ) ); ?></option><?php endforeach; ?></select></label></p>
            <?php $this->render_multiselect( 'sc_publication_graph_source_ids[]', __( 'Public Research Sources', 'sustainable-catalyst-library' ), $sources ); ?>
            <?php $this->render_multiselect( 'sc_publication_graph_claim_ids[]', __( 'Public Research Claims', 'sustainable-catalyst-library' ), $claims ); ?>
            <?php $this->render_multiselect( 'sc_publication_graph_pathway_ids[]', __( 'Published Knowledge Pathways', 'sustainable-catalyst-library' ), $pathways ); ?>
            <?php $this->render_multiselect( 'sc_publication_graph_concept_ids[]', __( 'Canonical Concepts', 'sustainable-catalyst-library' ), $concepts ); ?>
            <?php $this->render_multiselect( 'sc_publication_graph_entity_ids[]', __( 'Named Entities', 'sustainable-catalyst-library' ), $entities ); ?>
            <p><small><?php esc_html_e( 'Knowledge Topics use the standard Knowledge Topics taxonomy panel. This graph layer does not infer relationships from article text.', 'sustainable-catalyst-library' ); ?></small></p>
        </div>
        <?php
    }

    private function render_multiselect( string $name, string $label, array $options ): void {
        echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><select multiple size="6" style="width:100%;max-width:760px" name="' . esc_attr( $name ) . '">';
        foreach ( $options as $option ) {
            echo '<option value="' . esc_attr( (string) $option['id'] ) . '" ' . selected( ! empty( $option['selected'] ), true, false ) . '>' . esc_html( (string) $option['title'] ) . '</option>';
        }
        echo '</select></label></p>';
    }

    public function save_publication_graph( int $post_id, WP_Post $post, bool $update ): void {
        if ( ! in_array( $post->post_type, self::publication_post_types(), true ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        $nonce = isset( $_POST['sc_library_publication_graph_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sc_library_publication_graph_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
        $map_key = sanitize_title( (string) wp_unslash( $_POST['sc_publication_graph_map_key'] ?? '' ) );
        $registry = self::map_registry();
        if ( $map_key && ! isset( $registry[ $map_key ] ) ) { $map_key = ''; }
        $map_key ? update_post_meta( $post_id, self::META_MAP_KEY, $map_key ) : delete_post_meta( $post_id, self::META_MAP_KEY );
        $pairs = array(
            self::META_SOURCE_IDS => array( 'field' => 'sc_publication_graph_source_ids', 'type' => class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE : '' ),
            self::META_CLAIM_IDS => array( 'field' => 'sc_publication_graph_claim_ids', 'type' => class_exists( 'SC_Library_Evidence_Claim_Linking' ) ? SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE : '' ),
            self::META_PATHWAY_IDS => array( 'field' => 'sc_publication_graph_pathway_ids', 'type' => class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : '' ),
            self::META_CONCEPT_IDS => array( 'field' => 'sc_publication_graph_concept_ids', 'type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::CONCEPT_POST_TYPE : '' ),
            self::META_ENTITY_IDS => array( 'field' => 'sc_publication_graph_entity_ids', 'type' => class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::ENTITY_POST_TYPE : '' ),
        );
        foreach ( $pairs as $meta => $config ) {
            $raw = (array) ( $_POST[ $config['field'] ] ?? array() );
            $ids = self::id_list( array_map( 'wp_unslash', $raw ) );
            if ( $config['type'] ) { $ids = array_values( array_filter( $ids, static fn( $id ) => get_post_type( $id ) === $config['type'] && 'publish' === get_post_status( $id ) ) ); }
            $ids ? update_post_meta( $post_id, $meta, $ids ) : delete_post_meta( $post_id, $meta );
        }
        $this->invalidate_publication_caches();
    }

    private static function public_post( int $post_id ): ?WP_Post {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::publication_post_types(), true ) || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) { return null; }
        $obj = get_post_type_object( $post->post_type );
        return $obj && ( $obj->public || $obj->publicly_queryable ) ? $post : null;
    }

    private static function public_source( int $id ): array {
        if ( ! class_exists( 'SC_Library_Citation_Source_Manager' ) ) { return array(); }
        $data = SC_Library_Citation_Source_Manager::get_source_data( $id, false );
        if ( ! is_array( $data ) || ! $data ) { return array(); }
        return array( 'id' => $id, 'title' => sanitize_text_field( (string) ( $data['title'] ?? get_the_title( $id ) ) ), 'url' => esc_url_raw( (string) ( $data['url'] ?? get_permalink( $id ) ) ), 'source_type' => sanitize_key( (string) ( $data['source_type'] ?? '' ) ), 'public' => true );
    }

    private static function public_claim( int $id ): array {
        if ( ! class_exists( 'SC_Library_Evidence_Claim_Linking' ) || ! SC_Library_Evidence_Claim_Linking::claim_is_public( $id ) ) { return array(); }
        $data = SC_Library_Evidence_Claim_Linking::get_claim_data( $id, false );
        if ( ! is_array( $data ) || ! $data ) { return array(); }
        return array( 'id' => $id, 'title' => sanitize_text_field( (string) ( $data['title'] ?? get_the_title( $id ) ) ), 'url' => esc_url_raw( (string) ( $data['url'] ?? get_permalink( $id ) ) ), 'status' => sanitize_key( (string) ( $data['status'] ?? '' ) ), 'public' => true );
    }

    private static function public_pathway( int $id ): array {
        if ( ! class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) || SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) { return array(); }
        return array( 'id' => $id, 'title' => sanitize_text_field( (string) get_the_title( $id ) ), 'url' => esc_url_raw( (string) get_permalink( $id ) ), 'public' => true );
    }

    private static function canonical_record_list( string $kind, array $ids ): array {
        if ( ! class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) { return array(); }
        $out = array();
        foreach ( $ids as $id ) {
            if ( 'concept' === $kind ) { $data = SC_Library_Topics_Concepts_Relationships::concept_data( $id, false ); }
            else { $data = SC_Library_Topics_Concepts_Relationships::entity_data( $id, false ); }
            if ( $data ) { $out[] = $data; }
        }
        return $out;
    }

    public static function build_graph( int $post_id ): array {
        $post = self::public_post( $post_id );
        if ( ! $post ) { return array(); }
        $topics = class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ? SC_Library_Topics_Concepts_Relationships::topics_for_post( $post_id, false ) : array();
        $concepts = self::canonical_record_list( 'concept', self::id_list( get_post_meta( $post_id, self::META_CONCEPT_IDS, true ) ) );
        $entities = self::canonical_record_list( 'entity', self::id_list( get_post_meta( $post_id, self::META_ENTITY_IDS, true ) ) );
        $sources = array_values( array_filter( array_map( array( __CLASS__, 'public_source' ), self::id_list( get_post_meta( $post_id, self::META_SOURCE_IDS, true ) ) ) ) );
        $claims = array_values( array_filter( array_map( array( __CLASS__, 'public_claim' ), self::id_list( get_post_meta( $post_id, self::META_CLAIM_IDS, true ) ) ) ) );
        $pathways = array_values( array_filter( array_map( array( __CLASS__, 'public_pathway' ), self::id_list( get_post_meta( $post_id, self::META_PATHWAY_IDS, true ) ) ) ) );
        $map_key = sanitize_title( (string) get_post_meta( $post_id, self::META_MAP_KEY, true ) );
        $registry = self::map_registry();
        $map = $map_key && isset( $registry[ $map_key ] ) ? $registry[ $map_key ] : array();
        $payload = array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'publication' => array( 'id' => $post_id, 'title' => sanitize_text_field( $post->post_title ), 'url' => esc_url_raw( (string) get_permalink( $post_id ) ), 'excerpt' => sanitize_textarea_field( (string) ( $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 42 ) ) ), 'published_at' => get_post_time( 'c', true, $post_id ) ),
            'article_map' => $map ? array( 'key' => $map_key, 'title' => sanitize_text_field( (string) ( $map['title'] ?? '' ) ), 'field' => sanitize_text_field( (string) ( $map['field'] ?? '' ) ), 'url' => esc_url_raw( home_url( (string) ( $map['url'] ?? '' ) ) ) ) : null,
            'topics' => array_values( array_filter( $topics ) ),
            'concepts' => $concepts,
            'entities' => $entities,
            'sources' => $sources,
            'claims' => $claims,
            'pathways' => $pathways,
            'counts' => array( 'topics' => count( $topics ), 'concepts' => count( $concepts ), 'entities' => count( $entities ), 'sources' => count( $sources ), 'claims' => count( $claims ), 'pathways' => count( $pathways ) ),
            'public_graph_only' => true,
            'private_research_excluded' => true,
            'private_projects_excluded' => true,
            'private_notebooks_excluded' => true,
            'private_evidence_matrices_excluded' => true,
            'personal_library_excluded' => true,
            'automatic_inference' => false,
        );
        $checksum_payload = $payload;
        $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $checksum_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return apply_filters( 'sc_library_publication_research_graph', $payload, $post_id );
    }

    public static function has_public_graph( int $post_id ): bool {
        $graph = self::build_graph( $post_id );
        if ( ! $graph ) { return false; }
        $counts = (array) ( $graph['counts'] ?? array() );
        $total = array_sum( array_map( 'absint', $counts ) );
        return $total > 0 || ! empty( $graph['article_map'] );
    }

    public static function graph_url_for_post( int $post_id ): string {
        if ( ! self::has_public_graph( $post_id ) ) { return ''; }
        return esc_url_raw( add_query_arg( 'publication_id', $post_id, home_url( '/knowledge-libraries/' ) ) . '#publication-research-graph' );
    }

    public static function graph_url_for_article_url( string $url ): string {
        $post_id = url_to_postid( $url );
        return $post_id ? self::graph_url_for_post( $post_id ) : '';
    }

    public function decorate_field_spotlight_model( $fields, $settings ) {
        if ( ! is_array( $fields ) ) { return $fields; }
        foreach ( $fields as &$field ) {
            if ( ! is_array( $field ) || ! is_array( $field['panels'] ?? null ) ) { continue; }
            foreach ( $field['panels'] as &$panel ) {
                if ( ! is_array( $panel ) ) { continue; }
                if ( is_array( $panel['hero'] ?? null ) && ! empty( $panel['hero']['url'] ) ) { $panel['hero']['research_graph_url'] = self::graph_url_for_article_url( (string) $panel['hero']['url'] ); }
                if ( is_array( $panel['articles'] ?? null ) ) {
                    foreach ( $panel['articles'] as &$article ) {
                        if ( is_array( $article ) && ! empty( $article['url'] ) ) { $article['research_graph_url'] = self::graph_url_for_article_url( (string) $article['url'] ); }
                    }
                    unset( $article );
                }
            }
            unset( $panel );
        }
        unset( $field );
        return $fields;
    }

    public function register_rest_routes(): void {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_lookup' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_graph' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/project-link', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_project_link' ) ) );
    }

    public function rest_signed_in(): bool { return is_user_logged_in(); }

    public function rest_lookup( WP_REST_Request $request ) {
        $id = absint( $request->get_param( 'id' ) );
        if ( ! $id ) { $url = esc_url_raw( (string) $request->get_param( 'url' ) ); $id = $url ? absint( url_to_postid( $url ) ) : 0; }
        $graph = $id ? self::build_graph( $id ) : array();
        return $graph ? rest_ensure_response( $graph ) : new WP_Error( 'sc_publication_graph_not_found', __( 'No public publication graph is available for that record.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_graph( WP_REST_Request $request ) {
        $graph = self::build_graph( absint( $request['id'] ) );
        return $graph ? rest_ensure_response( $graph ) : new WP_Error( 'sc_publication_graph_not_found', __( 'No public publication graph is available for that record.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
    }

    public function rest_project_link( WP_REST_Request $request ) {
        $post_id = absint( $request['id'] );
        $project_id = absint( $request->get_param( 'project_id' ) );
        $post = self::public_post( $post_id );
        if ( ! $post || ! $project_id || ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { return new WP_Error( 'sc_publication_graph_handoff', __( 'The publication or research project is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $user_id = get_current_user_id();
        if ( ! SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( $user_id, $project_id ) ) { return new WP_Error( 'sc_publication_graph_project', __( 'That research project does not belong to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $result = SC_Library_Unified_Research_Projects_Source_Bundles::add_link_for_user( $user_id, $project_id, array( 'family' => 'external', 'ref_id' => 'publication-' . $post_id, 'label' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ), 'role' => 'reference', 'note' => __( 'Publication reference from the public Research Graph.', 'sustainable-catalyst-library' ) ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'linked' => true, 'project_id' => $project_id, 'publication_id' => $post_id, 'reference' => $result, 'references_only' => true, 'publication_remains_public_canonical_record' => true ) );
    }

    private static function project_options( int $user_id ): array {
        $out = array();
        if ( ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { return $out; }
        foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( $user_id ) as $id ) { $out[] = array( 'id' => absint( $id ), 'title' => sanitize_text_field( (string) ( get_the_title( $id ) ?: sprintf( 'Project %d', $id ) ) ) ); }
        return $out;
    }

    private static function resolve_shortcode_publication(): int {
        $id = isset( $_GET['publication_id'] ) ? absint( $_GET['publication_id'] ) : 0;
        if ( ! $id && ! empty( $_GET['publication_url'] ) ) { $id = absint( url_to_postid( esc_url_raw( wp_unslash( $_GET['publication_url'] ) ) ) ); }
        if ( ! $id && is_singular( self::publication_post_types() ) ) { $id = get_queried_object_id(); }
        return $id;
    }

    public function shortcode( $atts ): string {
        $atts = shortcode_atts( array( 'title' => __( 'Publications ↔ Research Graph', 'sustainable-catalyst-library' ) ), $atts, 'sc_publications_research_graph' );
        wp_enqueue_style( 'sc-library-publication-graph-v4337' ); wp_enqueue_script( 'sc-library-publication-graph-v4337' );
        $post_id = self::resolve_shortcode_publication(); $graph = $post_id ? self::build_graph( $post_id ) : array(); $signed = is_user_logged_in();
        wp_localize_script( 'sc-library-publication-graph-v4337', 'scPublicationGraph', array( 'root' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'signedIn' => $signed, 'projects' => $signed ? self::project_options( get_current_user_id() ) : array(), 'loginUrl' => wp_login_url( home_url( '/knowledge-libraries/#publication-research-graph' ) ) ) );
        ob_start(); ?>
        <section class="sc-publication-graph" id="publication-research-graph" data-sc-publication-graph data-publication-id="<?php echo esc_attr( (string) $post_id ); ?>">
            <header><p class="sc-publication-graph__kicker"><?php esc_html_e( 'Public knowledge ↔ research context', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( (string) $atts['title'] ); ?></h3><p><?php esc_html_e( 'Trace a public Sustainable Catalyst publication into its declared topics, concepts, entities, public sources, public claims, Article Map, and Knowledge Pathways. Private research remains private.', 'sustainable-catalyst-library' ); ?></p></header>
            <form class="sc-publication-graph__lookup" method="get" action="<?php echo esc_url( home_url( '/knowledge-libraries/' ) ); ?>"><label><span><?php esc_html_e( 'Publication URL', 'sustainable-catalyst-library' ); ?></span><input type="url" name="publication_url" placeholder="https://sustainablecatalyst.com/..." value="<?php echo esc_attr( ! empty( $_GET['publication_url'] ) ? esc_url_raw( wp_unslash( $_GET['publication_url'] ) ) : '' ); ?>"></label><button type="submit"><?php esc_html_e( 'Open Research Graph', 'sustainable-catalyst-library' ); ?></button></form>
            <div class="sc-publication-graph__truth" role="note"><strong><?php esc_html_e( 'Public graph only', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'The graph contains only public records explicitly connected by editors. It does not infer sources or claims from article text and never exposes private projects, notebooks, matrices, personal collections, queues, or bundles.', 'sustainable-catalyst-library' ); ?></span></div>
            <?php if ( $graph ) : $pub = $graph['publication']; ?>
                <article class="sc-publication-graph__record"><div><small><?php echo esc_html( (string) ( $graph['article_map']['field'] ?? 'Publication' ) ); ?></small><h4><a href="<?php echo esc_url( $pub['url'] ); ?>"><?php echo esc_html( $pub['title'] ); ?></a></h4><p><?php echo esc_html( $pub['excerpt'] ); ?></p></div><div class="sc-publication-graph__counts"><?php foreach ( $graph['counts'] as $label => $count ) : ?><span><b><?php echo esc_html( (string) $count ); ?></b><?php echo esc_html( ucfirst( $label ) ); ?></span><?php endforeach; ?></div></article>
                <?php $this->render_graph_groups( $graph ); ?>
                <?php if ( $signed ) : ?><form class="sc-publication-graph__project" data-sc-publication-project-link><input type="hidden" name="publication_id" value="<?php echo esc_attr( (string) $post_id ); ?>"><label><span><?php esc_html_e( 'Continue in Research Project', 'sustainable-catalyst-library' ); ?></span><select name="project_id" required><option value=""><?php esc_html_e( 'Choose a private project…', 'sustainable-catalyst-library' ); ?></option><?php foreach ( self::project_options( get_current_user_id() ) as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>"><?php echo esc_html( $project['title'] ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Link Publication to Project', 'sustainable-catalyst-library' ); ?></button><span data-sc-publication-project-status aria-live="polite"></span></form><?php else : ?><p class="sc-publication-graph__signin"><a href="<?php echo esc_url( wp_login_url( add_query_arg( 'publication_id', $post_id, home_url( '/knowledge-libraries/' ) ) . '#publication-research-graph' ) ); ?>"><?php esc_html_e( 'Sign in with your Sustainable Catalyst / Workspace account to link this publication to a private research project →', 'sustainable-catalyst-library' ); ?></a></p><?php endif; ?>
            <?php else : ?><p class="sc-publication-graph__empty"><?php esc_html_e( 'Enter a Sustainable Catalyst publication URL to inspect its declared public Research Graph. Publications without explicit graph links remain ordinary public articles.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
        </section><?php return (string) ob_get_clean();
    }

    private function render_graph_groups( array $graph ): void {
        $groups = array( 'topics' => 'Knowledge Topics', 'concepts' => 'Concepts', 'entities' => 'Named Entities', 'sources' => 'Public Research Sources', 'claims' => 'Public Claims', 'pathways' => 'Knowledge Pathways' );
        if ( ! empty( $graph['article_map'] ) ) { echo '<div class="sc-publication-graph__map"><small>' . esc_html__( 'Article Map', 'sustainable-catalyst-library' ) . '</small><a href="' . esc_url( (string) $graph['article_map']['url'] ) . '"><strong>' . esc_html( (string) $graph['article_map']['title'] ) . '</strong><span>' . esc_html( (string) $graph['article_map']['field'] ) . '</span></a></div>'; }
        echo '<div class="sc-publication-graph__groups">';
        foreach ( $groups as $key => $label ) {
            $items = (array) ( $graph[ $key ] ?? array() ); if ( ! $items ) { continue; }
            echo '<section><h5>' . esc_html( $label ) . '</h5><ul>';
            foreach ( $items as $item ) { $title = (string) ( $item['label'] ?? $item['title'] ?? $item['name'] ?? '' ); $url = (string) ( $item['url'] ?? '' ); echo '<li>'; if ( $url ) { echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>'; } else { echo esc_html( $title ); } echo '</li>'; }
            echo '</ul></section>';
        }
        echo '</div>';
    }
}
