<?php
/**
 * Knowledge Pathways and Article Maps.
 *
 * Adds curated learning and research pathways, ordered cross-record sequences,
 * prerequisites, continuations, public pathway pages, visual article maps,
 * project-derived pathway drafts, and Research Librarian recommendation hooks.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Knowledge_Pathways_Article_Maps {
    public const VERSION = '3.3.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const PATHWAY_POST_TYPE = 'sc_knowledge_path';
    public const PATHWAY_TYPE_TAXONOMY = 'sc_pathway_type';

    public const PATHWAY_SCHEMA = 'sc-library-knowledge-pathway/1.0';
    public const STEP_SCHEMA = 'sc-library-pathway-step/1.0';
    public const MAP_SCHEMA = 'sc-library-article-map/1.0';
    public const RECOMMENDATION_SCHEMA = 'sc-library-pathway-recommendations/1.0';

    public const META_LEVEL = '_sc_pathway_level';
    public const META_AUDIENCE = '_sc_pathway_audience';
    public const META_ESTIMATED_MINUTES = '_sc_pathway_estimated_minutes';
    public const META_OUTCOMES = '_sc_pathway_outcomes';
    public const META_STEPS = '_sc_pathway_steps';
    public const META_PREREQUISITE_IDS = '_sc_pathway_prerequisite_ids';
    public const META_CONTINUATION_IDS = '_sc_pathway_continuation_ids';
    public const META_DERIVED_PROJECT_ID = '_sc_pathway_derived_project_id';
    public const META_MAP_MODE = '_sc_pathway_map_mode';
    public const META_RECOMMENDATION_TERMS = '_sc_pathway_recommendation_terms';
    public const META_CONCEPT_IDS = '_sc_pathway_concept_ids';
    public const META_ENTITY_IDS = '_sc_pathway_entity_ids';
    public const META_NODE_KEYS = '_sc_pathway_node_keys';
    public const META_UPDATED_AT = '_sc_pathway_updated_at';
    public const META_UPDATED_BY = '_sc_pathway_updated_by';

    public const OPTION_ROUTES_VERSION = 'sc_library_v330_routes_version';
    public const TRANSIENT_RECOMMENDATION_PREFIX = 'sc_library_v330_recs_';

    public const MAX_STEPS = 200;
    public const MAX_OUTCOMES = 50;
    public const MAX_PATHWAY_LINKS = 50;
    public const MAX_ASSIGNMENTS = 200;
    public const MAX_RECOMMENDATIONS = 20;
    public const RECOMMENDATION_CACHE_SECONDS = 600;

    private static $saving = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ), 270 );
        add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 260 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 150 );
        add_action( 'save_post_' . self::PATHWAY_POST_TYPE, array( $this, 'save_pathway' ), 150, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_pathway' ), 150 );
        add_action( 'transition_post_status', array( $this, 'invalidate_status_cache' ), 210, 3 );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 150 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_filter( 'template_include', array( $this, 'template_include' ), 140 );

        add_shortcode( 'sc_knowledge_pathway', array( $this, 'shortcode_pathway' ) );
        add_shortcode( 'sc_article_map', array( $this, 'shortcode_article_map' ) );
        add_shortcode( 'sc_pathway_recommendations', array( $this, 'shortcode_recommendations' ) );

        add_action( 'wp_ajax_sc_library_v330_search_nodes', array( $this, 'ajax_search_nodes' ) );
        add_action( 'wp_ajax_sc_library_v330_derive_pathway', array( $this, 'ajax_derive_pathway' ) );
        add_action( 'wp_ajax_sc_library_v330_preview_map', array( $this, 'ajax_preview_map' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 40, 3 );

        add_filter( 'sc_library_research_librarian_pathway_recommendations', array( __CLASS__, 'filter_research_librarian_recommendations' ), 10, 3 );

        add_filter( 'manage_' . self::PATHWAY_POST_TYPE . '_posts_columns', array( $this, 'pathway_columns' ) );
        add_action( 'manage_' . self::PATHWAY_POST_TYPE . '_posts_custom_column', array( $this, 'pathway_column_content' ), 10, 2 );

        self::register_cli_commands();
    }

    public function register_content_types() {
        register_post_type(
            self::PATHWAY_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Knowledge Pathways', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Knowledge Pathway', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Knowledge Pathways', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Knowledge Pathway', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Knowledge Pathway', 'sustainable-catalyst-library' ),
                    'new_item'           => __( 'New Knowledge Pathway', 'sustainable-catalyst-library' ),
                    'view_item'          => __( 'View Knowledge Pathway', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Knowledge Pathways', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No pathways found.', 'sustainable-catalyst-library' ),
                    'not_found_in_trash' => __( 'No pathways found in Trash.', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => true,
                'exclude_from_search' => false,
                'has_archive'         => 'pathways',
                'rewrite'             => array( 'slug' => 'pathways', 'with_front' => false ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-randomize',
                'can_export'          => true,
            )
        );

        register_taxonomy(
            self::PATHWAY_TYPE_TAXONOMY,
            array( self::PATHWAY_POST_TYPE ),
            array(
                'labels' => array(
                    'name'          => __( 'Pathway Types', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Pathway Type', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Pathway Types', 'sustainable-catalyst-library' ),
                ),
                'public'            => true,
                'publicly_queryable'=> true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => array( 'slug' => 'pathway-type', 'with_front' => false ),
            )
        );

        if ( taxonomy_exists( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY ) ) {
            register_taxonomy_for_object_type(
                SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY,
                self::PATHWAY_POST_TYPE
            );
        }

        foreach ( self::default_pathway_types() as $slug => $label ) {
            if ( ! term_exists( $slug, self::PATHWAY_TYPE_TAXONOMY ) ) {
                wp_insert_term( $label, self::PATHWAY_TYPE_TAXONOMY, array( 'slug' => $slug ) );
            }
        }
    }

    public static function default_pathway_types() {
        return array(
            'orientation'       => __( 'Orientation', 'sustainable-catalyst-library' ),
            'learning-path'     => __( 'Learning Path', 'sustainable-catalyst-library' ),
            'research-path'     => __( 'Research Path', 'sustainable-catalyst-library' ),
            'document-series'   => __( 'Document Series', 'sustainable-catalyst-library' ),
            'methodology-guide' => __( 'Methodology Guide', 'sustainable-catalyst-library' ),
            'evidence-trail'    => __( 'Evidence Trail', 'sustainable-catalyst-library' ),
            'project-path'      => __( 'Project-Derived Path', 'sustainable-catalyst-library' ),
        );
    }

    public static function level_options() {
        return array(
            'introductory' => __( 'Introductory', 'sustainable-catalyst-library' ),
            'foundational' => __( 'Foundational', 'sustainable-catalyst-library' ),
            'intermediate' => __( 'Intermediate', 'sustainable-catalyst-library' ),
            'advanced'     => __( 'Advanced', 'sustainable-catalyst-library' ),
            'expert'       => __( 'Expert or specialist', 'sustainable-catalyst-library' ),
            'mixed'        => __( 'Mixed levels', 'sustainable-catalyst-library' ),
        );
    }

    public static function stage_options() {
        return array(
            'orientation' => __( 'Orientation', 'sustainable-catalyst-library' ),
            'foundation'  => __( 'Foundation', 'sustainable-catalyst-library' ),
            'core'        => __( 'Core Reading', 'sustainable-catalyst-library' ),
            'evidence'    => __( 'Sources and Evidence', 'sustainable-catalyst-library' ),
            'application' => __( 'Application', 'sustainable-catalyst-library' ),
            'analysis'    => __( 'Analysis and Critique', 'sustainable-catalyst-library' ),
            'synthesis'   => __( 'Synthesis', 'sustainable-catalyst-library' ),
            'extension'   => __( 'Further Exploration', 'sustainable-catalyst-library' ),
        );
    }

    public static function map_mode_options() {
        return array(
            'sequence' => __( 'Ordered sequence', 'sustainable-catalyst-library' ),
            'stages'   => __( 'Stage map', 'sustainable-catalyst-library' ),
            'network'  => __( 'Semantic network', 'sustainable-catalyst-library' ),
            'compact'  => __( 'Compact article map', 'sustainable-catalyst-library' ),
        );
    }

    public static function step_kind_options() {
        return array_merge(
            SC_Library_Topics_Concepts_Relationships::node_type_options(),
            array( 'pathway' => __( 'Knowledge Pathway', 'sustainable-catalyst-library' ) )
        );
    }

    public static function difficulty_options() {
        return array(
            'introductory' => __( 'Introductory', 'sustainable-catalyst-library' ),
            'foundational' => __( 'Foundational', 'sustainable-catalyst-library' ),
            'intermediate' => __( 'Intermediate', 'sustainable-catalyst-library' ),
            'advanced'     => __( 'Advanced', 'sustainable-catalyst-library' ),
            'expert'       => __( 'Expert', 'sustainable-catalyst-library' ),
        );
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Knowledge Pathways and Article Maps', 'sustainable-catalyst-library' ),
            __( 'Pathways and Maps', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-pathways-maps',
            array( $this, 'render_workspace' )
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'sc-knowledge-pathway-design',
            __( 'Pathway Design and Ordered Steps', 'sustainable-catalyst-library' ),
            array( $this, 'render_pathway_meta_box' ),
            self::PATHWAY_POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'sc-knowledge-pathway-map-preview',
            __( 'Article Map and Recommendations', 'sustainable-catalyst-library' ),
            array( $this, 'render_map_meta_box' ),
            self::PATHWAY_POST_TYPE,
            'side',
            'high'
        );
    }

    public function render_pathway_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_pathway_' . $post->ID, 'sc_library_pathway_nonce' );

        $level = (string) get_post_meta( $post->ID, self::META_LEVEL, true ) ?: 'mixed';
        $audience = (string) get_post_meta( $post->ID, self::META_AUDIENCE, true );
        $minutes = absint( get_post_meta( $post->ID, self::META_ESTIMATED_MINUTES, true ) );
        $outcomes = self::string_list( get_post_meta( $post->ID, self::META_OUTCOMES, true ) );
        $steps = self::pathway_steps( $post->ID, true );
        $prerequisites = self::pathway_id_list( get_post_meta( $post->ID, self::META_PREREQUISITE_IDS, true ), $post->ID, true );
        $continuations = self::pathway_id_list( get_post_meta( $post->ID, self::META_CONTINUATION_IDS, true ), $post->ID, true );
        $map_mode = (string) get_post_meta( $post->ID, self::META_MAP_MODE, true ) ?: 'stages';
        $terms = (string) get_post_meta( $post->ID, self::META_RECOMMENDATION_TERMS, true );
        $concept_ids = self::valid_node_ids( 'concept', get_post_meta( $post->ID, self::META_CONCEPT_IDS, true ), true );
        $entity_ids = self::valid_node_ids( 'entity', get_post_meta( $post->ID, self::META_ENTITY_IDS, true ), true );
        $pathways = get_posts(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 250,
                'post__not_in'   => array( $post->ID ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $concepts = get_posts(
            array(
                'post_type'      => SC_Library_Topics_Concepts_Relationships::CONCEPT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 300,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $entities = get_posts(
            array(
                'post_type'      => SC_Library_Topics_Concepts_Relationships::ENTITY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 300,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        ?>
        <div class="sc-pathway-editor" data-sc-pathway-editor data-pathway-id="<?php echo esc_attr( $post->ID ); ?>">
            <div class="sc-pathway-field-grid">
                <label><strong><?php esc_html_e( 'Pathway level', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_level"><?php foreach ( self::level_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $level, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Intended audience', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_pathway_audience" value="<?php echo esc_attr( $audience ); ?>" placeholder="<?php esc_attr_e( 'Students, researchers, practitioners, decision-makers', 'sustainable-catalyst-library' ); ?>"></label>
                <label><strong><?php esc_html_e( 'Estimated completion time', 'sustainable-catalyst-library' ); ?></strong><input type="number" min="0" max="100000" name="sc_pathway_estimated_minutes" value="<?php echo esc_attr( $minutes ); ?>"><span><?php esc_html_e( 'minutes', 'sustainable-catalyst-library' ); ?></span></label>
                <label><strong><?php esc_html_e( 'Article-map mode', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_map_mode"><?php foreach ( self::map_mode_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $map_mode, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            </div>

            <label><strong><?php esc_html_e( 'Learning or research outcomes', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_pathway_outcomes" rows="5" placeholder="<?php esc_attr_e( 'One outcome per line', 'sustainable-catalyst-library' ); ?>"><?php echo esc_textarea( implode( "\n", $outcomes ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Recommendation terms', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_pathway_recommendation_terms" value="<?php echo esc_attr( $terms ); ?>" placeholder="<?php esc_attr_e( 'Comma-separated terms used by Research Librarian recommendations', 'sustainable-catalyst-library' ); ?>"></label>

            <div class="sc-pathway-link-grid">
                <label><strong><?php esc_html_e( 'Prerequisite pathways', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_prerequisite_ids[]" multiple size="6"><?php foreach ( $pathways as $pathway ) : ?><option value="<?php echo esc_attr( $pathway->ID ); ?>" <?php selected( in_array( $pathway->ID, $prerequisites, true ) ); ?>><?php echo esc_html( $pathway->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Continuation pathways', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_continuation_ids[]" multiple size="6"><?php foreach ( $pathways as $pathway ) : ?><option value="<?php echo esc_attr( $pathway->ID ); ?>" <?php selected( in_array( $pathway->ID, $continuations, true ) ); ?>><?php echo esc_html( $pathway->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Core Concepts', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_concept_ids[]" multiple size="6"><?php foreach ( $concepts as $concept ) : ?><option value="<?php echo esc_attr( $concept->ID ); ?>" <?php selected( in_array( $concept->ID, $concept_ids, true ) ); ?>><?php echo esc_html( $concept->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Named Entities', 'sustainable-catalyst-library' ); ?></strong><select name="sc_pathway_entity_ids[]" multiple size="6"><?php foreach ( $entities as $entity ) : ?><option value="<?php echo esc_attr( $entity->ID ); ?>" <?php selected( in_array( $entity->ID, $entity_ids, true ) ); ?>><?php echo esc_html( $entity->post_title ); ?></option><?php endforeach; ?></select></label>
            </div>

            <div class="sc-pathway-step-heading">
                <div><h3><?php esc_html_e( 'Ordered pathway steps', 'sustainable-catalyst-library' ); ?></h3><p><?php esc_html_e( 'Build a beginner-to-advanced sequence across documents, Sources, Claims, Evidence Notes, Projects, Topics, Concepts, Entities, vocabularies, or other pathways.', 'sustainable-catalyst-library' ); ?></p></div>
                <button type="button" class="button button-primary" data-sc-add-pathway-step><?php esc_html_e( 'Add Step', 'sustainable-catalyst-library' ); ?></button>
            </div>

            <div class="sc-pathway-step-rows" data-sc-pathway-step-rows>
                <?php foreach ( $steps as $index => $step ) : ?>
                    <?php self::render_step_row( $index, $step ); ?>
                <?php endforeach; ?>
            </div>

            <template data-sc-pathway-step-template>
                <?php self::render_step_row( '__INDEX__', self::default_step(), true ); ?>
            </template>
        </div>
        <?php
    }

    private static function render_step_row( $index, $step, $template = false ) {
        $kind = sanitize_key( $step['kind'] ?? 'document' );
        $node_id = absint( $step['node_id'] ?? 0 );
        $label = sanitize_text_field( $step['label'] ?? '' );
        if ( ! $label && $node_id ) {
            $label = self::node_label( $kind, $node_id );
        }
        ?>
        <article class="sc-pathway-step-row" data-sc-pathway-step-row>
            <input type="hidden" data-step-field="uuid" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][uuid]" value="<?php echo esc_attr( $step['uuid'] ?? '' ); ?>">
            <button type="button" class="sc-pathway-step-handle" data-sc-step-handle aria-label="<?php esc_attr_e( 'Reorder pathway step', 'sustainable-catalyst-library' ); ?>">↕</button>
            <label><span><?php esc_html_e( 'Record type', 'sustainable-catalyst-library' ); ?></span><select data-step-field="kind" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][kind]"><?php foreach ( self::step_kind_options() as $value => $option_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $kind, $value ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e( 'Record ID', 'sustainable-catalyst-library' ); ?></span><div class="sc-pathway-node-id"><input type="number" min="1" data-step-field="node_id" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][node_id]" value="<?php echo esc_attr( $node_id ); ?>"><button type="button" class="button" data-sc-search-pathway-node><?php esc_html_e( 'Find', 'sustainable-catalyst-library' ); ?></button></div></label>
            <label class="sc-pathway-step-label"><span><?php esc_html_e( 'Display label', 'sustainable-catalyst-library' ); ?></span><input type="text" data-step-field="label" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>"></label>
            <label><span><?php esc_html_e( 'Stage', 'sustainable-catalyst-library' ); ?></span><select data-step-field="stage" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][stage]"><?php foreach ( self::stage_options() as $value => $option_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $step['stage'] ?? 'core', $value ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e( 'Difficulty', 'sustainable-catalyst-library' ); ?></span><select data-step-field="difficulty" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][difficulty]"><?php foreach ( self::difficulty_options() as $value => $option_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $step['difficulty'] ?? 'intermediate', $value ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e( 'Minutes', 'sustainable-catalyst-library' ); ?></span><input type="number" min="0" max="100000" data-step-field="minutes" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][minutes]" value="<?php echo esc_attr( absint( $step['minutes'] ?? 0 ) ); ?>"></label>
            <label class="sc-pathway-step-note"><span><?php esc_html_e( 'Purpose or transition note', 'sustainable-catalyst-library' ); ?></span><input type="text" data-step-field="note" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][note]" value="<?php echo esc_attr( $step['note'] ?? '' ); ?>"></label>
            <label class="sc-pathway-required"><input type="checkbox" data-step-field="required" name="sc_pathway_steps[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $step['required'] ) ); ?>> <?php esc_html_e( 'Required', 'sustainable-catalyst-library' ); ?></label>
            <button type="button" class="button" data-sc-remove-pathway-step><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
            <div class="sc-pathway-node-search" data-sc-pathway-node-search hidden></div>
        </article>
        <?php
    }

    public function render_map_meta_box( $post ) {
        $data = self::get_pathway_data( $post->ID, true );
        $map = $data ? self::map_data( $post->ID, true ) : array();
        ?>
        <div class="sc-pathway-map-sidebar" data-pathway-id="<?php echo esc_attr( $post->ID ); ?>">
            <dl>
                <div><dt><?php esc_html_e( 'Steps', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $data['steps'] ?? array() ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Required', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $data['required_step_count'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Minutes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( absint( $data['estimated_minutes'] ?? 0 ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Map edges', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( count( $map['edges'] ?? array() ) ); ?></dd></div>
            </dl>
            <button type="button" class="button" data-sc-preview-pathway-map><?php esc_html_e( 'Preview Article Map', 'sustainable-catalyst-library' ); ?></button>
            <?php $project_id = absint( get_post_meta( $post->ID, self::META_DERIVED_PROJECT_ID, true ) ); ?>
            <?php if ( $project_id ) : ?><p><strong><?php esc_html_e( 'Derived from:', 'sustainable-catalyst-library' ); ?></strong> <a href="<?php echo esc_url( get_edit_post_link( $project_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $project_id ) ); ?></a></p><?php endif; ?>
            <div data-sc-pathway-map-status aria-live="polite"></div>
            <div data-sc-pathway-map-preview></div>
        </div>
        <?php
    }

    public function save_pathway( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::PATHWAY_POST_TYPE !== $post->post_type
            || ! isset( $_POST['sc_library_pathway_nonce'] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_pathway_nonce'] ) ),
                'sc_library_save_pathway_' . $post_id
            )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        self::$saving = true;

        $level = self::allowed_value( wp_unslash( $_POST['sc_pathway_level'] ?? 'mixed' ), self::level_options(), 'mixed' );
        $audience = sanitize_text_field( wp_unslash( $_POST['sc_pathway_audience'] ?? '' ) );
        $minutes = max( 0, min( 100000, absint( wp_unslash( $_POST['sc_pathway_estimated_minutes'] ?? 0 ) ) ) );
        $map_mode = self::allowed_value( wp_unslash( $_POST['sc_pathway_map_mode'] ?? 'stages' ), self::map_mode_options(), 'stages' );
        $terms = sanitize_text_field( wp_unslash( $_POST['sc_pathway_recommendation_terms'] ?? '' ) );
        $outcomes = array_slice( self::string_list( wp_unslash( $_POST['sc_pathway_outcomes'] ?? '' ) ), 0, self::MAX_OUTCOMES );
        $steps = self::sanitize_steps( wp_unslash( $_POST['sc_pathway_steps'] ?? array() ), $post_id );
        $prerequisites = self::pathway_id_list( wp_unslash( $_POST['sc_pathway_prerequisite_ids'] ?? array() ), $post_id, true );
        $continuations = self::pathway_id_list( wp_unslash( $_POST['sc_pathway_continuation_ids'] ?? array() ), $post_id, true );
        $concept_ids = self::valid_node_ids( 'concept', wp_unslash( $_POST['sc_pathway_concept_ids'] ?? array() ), true );
        $entity_ids = self::valid_node_ids( 'entity', wp_unslash( $_POST['sc_pathway_entity_ids'] ?? array() ), true );

        update_post_meta( $post_id, self::META_LEVEL, $level );
        self::update_or_delete_meta( $post_id, self::META_AUDIENCE, $audience );
        self::update_or_delete_meta( $post_id, self::META_ESTIMATED_MINUTES, $minutes );
        update_post_meta( $post_id, self::META_MAP_MODE, $map_mode );
        self::update_or_delete_meta( $post_id, self::META_RECOMMENDATION_TERMS, $terms );
        self::update_or_delete_meta( $post_id, self::META_OUTCOMES, $outcomes );
        self::update_or_delete_meta( $post_id, self::META_STEPS, $steps );
        self::update_or_delete_meta( $post_id, self::META_PREREQUISITE_IDS, $prerequisites );
        self::update_or_delete_meta( $post_id, self::META_CONTINUATION_IDS, $continuations );
        self::update_or_delete_meta( $post_id, self::META_CONCEPT_IDS, $concept_ids );
        self::update_or_delete_meta( $post_id, self::META_ENTITY_IDS, $entity_ids );
        self::update_or_delete_meta( $post_id, self::META_NODE_KEYS, wp_list_pluck( $steps, 'node_key' ) );
        update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        update_post_meta( $post_id, self::META_UPDATED_BY, get_current_user_id() );

        self::invalidate_recommendation_cache();
        if ( class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            SC_Library_Topics_Concepts_Relationships::invalidate_coverage_cache();
        }
        do_action( 'sc_library_pathway_saved', $post_id, self::get_pathway_data( $post_id, true ) );
        self::$saving = false;
    }

    private static function default_step() {
        return array(
            'schema'     => self::STEP_SCHEMA,
            'uuid'       => '',
            'kind'       => 'document',
            'node_id'    => 0,
            'node_key'   => '',
            'label'      => '',
            'stage'      => 'core',
            'difficulty' => 'intermediate',
            'minutes'    => 0,
            'required'   => true,
            'note'       => '',
            'order'      => 0,
        );
    }

    public static function sanitize_steps( $raw, $pathway_id = 0 ) {
        $steps = array();
        $seen = array();
        $kinds = self::step_kind_options();

        foreach ( (array) $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $kind = sanitize_key( $item['kind'] ?? '' );
            $node_id = absint( $item['node_id'] ?? 0 );
            if ( ! array_key_exists( $kind, $kinds ) || ! $node_id || ! self::node_exists( $kind, $node_id ) ) {
                continue;
            }
            if ( 'pathway' === $kind && $node_id === absint( $pathway_id ) ) {
                continue;
            }
            $node_key = $kind . ':' . $node_id;
            if ( isset( $seen[ $node_key ] ) ) {
                continue;
            }
            $seen[ $node_key ] = true;
            $uuid = sanitize_text_field( $item['uuid'] ?? '' );
            if ( ! $uuid ) {
                $uuid = wp_generate_uuid4();
            }
            $steps[] = array(
                'schema'     => self::STEP_SCHEMA,
                'uuid'       => $uuid,
                'kind'       => $kind,
                'node_id'    => $node_id,
                'node_key'   => $node_key,
                'label'      => sanitize_text_field( $item['label'] ?? '' ),
                'stage'      => self::allowed_value( $item['stage'] ?? 'core', self::stage_options(), 'core' ),
                'difficulty' => self::allowed_value( $item['difficulty'] ?? 'intermediate', self::difficulty_options(), 'intermediate' ),
                'minutes'    => max( 0, min( 100000, absint( $item['minutes'] ?? 0 ) ) ),
                'required'   => ! empty( $item['required'] ),
                'note'       => sanitize_text_field( $item['note'] ?? '' ),
                'order'      => count( $steps ),
            );
            if ( count( $steps ) >= self::MAX_STEPS ) {
                break;
            }
        }
        return $steps;
    }

    public static function pathway_steps( $pathway_id, $include_private = false ) {
        $stored = get_post_meta( $pathway_id, self::META_STEPS, true );
        $stored = is_array( $stored ) ? $stored : array();
        $steps = array();
        foreach ( $stored as $step ) {
            if ( ! is_array( $step ) ) {
                continue;
            }
            $kind = sanitize_key( $step['kind'] ?? '' );
            $node_id = absint( $step['node_id'] ?? 0 );
            if ( ! self::node_exists( $kind, $node_id ) ) {
                continue;
            }
            if ( ! $include_private && ! self::node_is_public( $kind, $node_id ) ) {
                continue;
            }
            $step['label'] = sanitize_text_field( $step['label'] ?? '' ) ?: self::node_label( $kind, $node_id );
            $step['url'] = self::node_url( $kind, $node_id );
            $step['public'] = self::node_is_public( $kind, $node_id );
            $step['summary'] = self::node_summary( $kind, $node_id );
            $steps[] = $step;
        }
        usort(
            $steps,
            static function ( $left, $right ) {
                return absint( $left['order'] ?? 0 ) <=> absint( $right['order'] ?? 0 );
            }
        );
        return array_values( $steps );
    }

    public static function get_pathway_data( $pathway_id, $include_private = false ) {
        $pathway_id = absint( $pathway_id );
        $post = get_post( $pathway_id );
        if ( ! $post || self::PATHWAY_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_post', $pathway_id ) ) {
            $include_private = false;
            if ( 'publish' !== $post->post_status ) {
                return array();
            }
        }

        $steps = self::pathway_steps( $pathway_id, $include_private );
        $topics = taxonomy_exists( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY )
            ? SC_Library_Topics_Concepts_Relationships::topics_for_post( $pathway_id, $include_private )
            : array();
        $concept_ids = self::valid_node_ids( 'concept', get_post_meta( $pathway_id, self::META_CONCEPT_IDS, true ), $include_private );
        $entity_ids = self::valid_node_ids( 'entity', get_post_meta( $pathway_id, self::META_ENTITY_IDS, true ), $include_private );
        $concepts = array();
        foreach ( $concept_ids as $concept_id ) {
            $concepts[] = SC_Library_Topics_Concepts_Relationships::concept_data( $concept_id, $include_private );
        }
        $entities = array();
        foreach ( $entity_ids as $entity_id ) {
            $entities[] = SC_Library_Topics_Concepts_Relationships::entity_data( $entity_id, $include_private );
        }
        $types = wp_get_post_terms( $pathway_id, self::PATHWAY_TYPE_TAXONOMY );
        $types = is_wp_error( $types ) ? array() : array_map(
            static function ( $term ) {
                return array( 'id' => $term->term_id, 'slug' => $term->slug, 'name' => $term->name );
            },
            $types
        );
        $required = count(
            array_filter(
                $steps,
                static function ( $step ) {
                    return ! empty( $step['required'] );
                }
            )
        );
        $step_minutes = array_sum( wp_list_pluck( $steps, 'minutes' ) );
        $estimated = absint( get_post_meta( $pathway_id, self::META_ESTIMATED_MINUTES, true ) );
        if ( ! $estimated ) {
            $estimated = $step_minutes;
        }

        $data = array(
            'schema'                => self::PATHWAY_SCHEMA,
            'id'                    => $pathway_id,
            'title'                 => get_the_title( $pathway_id ),
            'slug'                  => $post->post_name,
            'summary'               => $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 45 ),
            'description'           => apply_filters( 'the_content', $post->post_content ),
            'level'                 => (string) get_post_meta( $pathway_id, self::META_LEVEL, true ) ?: 'mixed',
            'level_label'           => self::level_options()[ (string) get_post_meta( $pathway_id, self::META_LEVEL, true ) ?: 'mixed' ] ?? __( 'Mixed levels', 'sustainable-catalyst-library' ),
            'audience'              => (string) get_post_meta( $pathway_id, self::META_AUDIENCE, true ),
            'estimated_minutes'     => $estimated,
            'outcomes'              => self::string_list( get_post_meta( $pathway_id, self::META_OUTCOMES, true ) ),
            'steps'                 => $steps,
            'step_count'            => count( $steps ),
            'required_step_count'   => $required,
            'prerequisite_ids'      => self::pathway_id_list( get_post_meta( $pathway_id, self::META_PREREQUISITE_IDS, true ), $pathway_id, $include_private ),
            'continuation_ids'      => self::pathway_id_list( get_post_meta( $pathway_id, self::META_CONTINUATION_IDS, true ), $pathway_id, $include_private ),
            'derived_project_id'    => absint( get_post_meta( $pathway_id, self::META_DERIVED_PROJECT_ID, true ) ),
            'map_mode'              => (string) get_post_meta( $pathway_id, self::META_MAP_MODE, true ) ?: 'stages',
            'recommendation_terms'  => self::string_list( str_replace( ',', "\n", (string) get_post_meta( $pathway_id, self::META_RECOMMENDATION_TERMS, true ) ) ),
            'topics'                => array_values( array_filter( $topics ) ),
            'concepts'              => array_values( array_filter( $concepts ) ),
            'entities'              => array_values( array_filter( $entities ) ),
            'types'                 => $types,
            'url'                   => 'publish' === $post->post_status ? get_permalink( $pathway_id ) : '',
            'public'                => 'publish' === $post->post_status,
            'modified_gmt'          => get_post_modified_time( 'c', true, $pathway_id ),
        );
        if ( $include_private ) {
            $data['status'] = $post->post_status;
            $data['updated_at'] = (string) get_post_meta( $pathway_id, self::META_UPDATED_AT, true );
            $data['updated_by'] = absint( get_post_meta( $pathway_id, self::META_UPDATED_BY, true ) );
        }
        return apply_filters( 'sc_library_pathway_data', $data, $pathway_id, $include_private );
    }

    public static function map_data( $pathway_id, $include_private = false ) {
        $pathway = self::get_pathway_data( $pathway_id, $include_private );
        if ( ! $pathway ) {
            return array();
        }
        $stage_order = array_keys( self::stage_options() );
        $stage_index = array_flip( $stage_order );
        $stage_counts = array_fill_keys( $stage_order, 0 );
        $nodes = array();
        $node_key_to_id = array();

        foreach ( $pathway['steps'] as $index => $step ) {
            $stage = isset( $stage_index[ $step['stage'] ] ) ? $step['stage'] : 'core';
            $column = $stage_counts[ $stage ]++;
            $node_key = $step['node_key'];
            $map_id = 'step-' . ( $index + 1 );
            $node_key_to_id[ $node_key ] = $map_id;
            $nodes[] = array(
                'id'         => $map_id,
                'node_key'   => $node_key,
                'kind'       => $step['kind'],
                'node_id'    => absint( $step['node_id'] ),
                'label'      => $step['label'],
                'url'        => $step['url'],
                'stage'      => $stage,
                'stage_label'=> self::stage_options()[ $stage ],
                'difficulty' => $step['difficulty'],
                'required'   => ! empty( $step['required'] ),
                'minutes'    => absint( $step['minutes'] ),
                'x'          => 150 + ( $column * 240 ),
                'y'          => 90 + ( $stage_index[ $stage ] * 170 ),
                'order'      => $index,
            );
        }

        $edges = array();
        for ( $index = 1; $index < count( $nodes ); $index++ ) {
            $edges[] = array(
                'id'       => 'sequence-' . $index,
                'from'     => $nodes[ $index - 1 ]['id'],
                'to'       => $nodes[ $index ]['id'],
                'type'     => 'sequence',
                'label'    => __( 'Next', 'sustainable-catalyst-library' ),
                'weight'   => 5,
            );
        }

        if ( class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            foreach ( $pathway['steps'] as $step ) {
                if ( 'pathway' === $step['kind'] ) {
                    continue;
                }
                $relationships = SC_Library_Topics_Concepts_Relationships::relationships_for_node(
                    $step['kind'],
                    $step['node_id'],
                    $include_private,
                    'outgoing'
                );
                foreach ( $relationships as $relationship ) {
                    $to_key = sanitize_key( $relationship['to_kind'] ?? '' ) . ':' . absint( $relationship['to_id'] ?? 0 );
                    $from_key = $step['node_key'];
                    if ( empty( $node_key_to_id[ $to_key ] ) || empty( $node_key_to_id[ $from_key ] ) ) {
                        continue;
                    }
                    $edge_id = 'semantic-' . absint( $relationship['id'] ?? 0 );
                    $edges[ $edge_id ] = array(
                        'id'     => $edge_id,
                        'from'   => $node_key_to_id[ $from_key ],
                        'to'     => $node_key_to_id[ $to_key ],
                        'type'   => 'semantic',
                        'label'  => sanitize_text_field( $relationship['relation_label'] ?? $relationship['relation'] ?? __( 'Related', 'sustainable-catalyst-library' ) ),
                        'weight' => max( 1, min( 5, absint( $relationship['weight'] ?? 3 ) ) ),
                    );
                }
            }
        }

        $max_columns = max( array_merge( array( 1 ), array_values( $stage_counts ) ) );
        return array(
            'schema'       => self::MAP_SCHEMA,
            'pathway_id'   => absint( $pathway_id ),
            'title'        => $pathway['title'],
            'mode'         => $pathway['map_mode'],
            'nodes'        => array_values( $nodes ),
            'edges'        => array_values( $edges ),
            'stage_labels' => self::stage_options(),
            'width'        => max( 720, 320 + ( $max_columns * 240 ) ),
            'height'       => max( 360, 140 + ( count( $stage_order ) * 170 ) ),
            'generated_at' => current_time( 'mysql' ),
        );
    }

    public static function render_pathway( $pathway_id, $include_private = false ) {
        $data = self::get_pathway_data( $pathway_id, $include_private );
        if ( ! $data ) {
            return '';
        }
        if ( $include_private ) {
            nocache_headers();
        }
        wp_enqueue_style( 'sc-library-knowledge-pathways' );
        wp_enqueue_script( 'sc-library-knowledge-pathways' );

        $stages = array();
        foreach ( $data['steps'] as $step ) {
            $stages[ $step['stage'] ][] = $step;
        }

        ob_start();
        ?>
        <article class="sc-public-pathway" data-pathway-id="<?php echo esc_attr( $data['id'] ); ?>">
            <header class="sc-public-pathway__hero">
                <div>
                    <p class="sc-pathway-kicker"><?php esc_html_e( 'Knowledge Pathway', 'sustainable-catalyst-library' ); ?></p>
                    <h1><?php echo esc_html( $data['title'] ); ?></h1>
                    <?php if ( $data['summary'] ) : ?><p class="sc-public-pathway__summary"><?php echo esc_html( $data['summary'] ); ?></p><?php endif; ?>
                </div>
                <dl class="sc-public-pathway__metrics">
                    <div><dt><?php esc_html_e( 'Level', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['level_label'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Steps', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['step_count'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Estimated time', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( self::format_minutes( $data['estimated_minutes'] ) ); ?></dd></div>
                    <?php if ( $data['audience'] ) : ?><div><dt><?php esc_html_e( 'Audience', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( $data['audience'] ); ?></dd></div><?php endif; ?>
                </dl>
            </header>

            <?php if ( $data['description'] ) : ?><section class="sc-public-pathway__introduction"><?php echo wp_kses_post( $data['description'] ); ?></section><?php endif; ?>

            <?php if ( $data['outcomes'] ) : ?>
                <section class="sc-public-pathway__outcomes">
                    <h2><?php esc_html_e( 'What this pathway develops', 'sustainable-catalyst-library' ); ?></h2>
                    <ul><?php foreach ( $data['outcomes'] as $outcome ) : ?><li><?php echo esc_html( $outcome ); ?></li><?php endforeach; ?></ul>
                </section>
            <?php endif; ?>

            <?php echo self::render_pathway_links( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <section class="sc-public-pathway__map" aria-labelledby="sc-pathway-map-heading-<?php echo esc_attr( $data['id'] ); ?>">
                <div class="sc-public-pathway__section-heading"><div><p class="sc-pathway-kicker"><?php esc_html_e( 'Article map', 'sustainable-catalyst-library' ); ?></p><h2 id="sc-pathway-map-heading-<?php echo esc_attr( $data['id'] ); ?>"><?php esc_html_e( 'Pathway map', 'sustainable-catalyst-library' ); ?></h2></div><button type="button" data-sc-toggle-map-list><?php esc_html_e( 'Toggle accessible list', 'sustainable-catalyst-library' ); ?></button></div>
                <?php echo self::render_map( $data['id'], $include_private ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

            <section class="sc-public-pathway__sequence">
                <div class="sc-public-pathway__section-heading"><div><p class="sc-pathway-kicker"><?php esc_html_e( 'Curated sequence', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Pathway steps', 'sustainable-catalyst-library' ); ?></h2></div><span><?php echo esc_html( sprintf( _n( '%d required step', '%d required steps', $data['required_step_count'], 'sustainable-catalyst-library' ), $data['required_step_count'] ) ); ?></span></div>
                <?php foreach ( self::stage_options() as $stage => $stage_label ) : ?>
                    <?php if ( empty( $stages[ $stage ] ) ) { continue; } ?>
                    <section class="sc-pathway-stage" data-stage="<?php echo esc_attr( $stage ); ?>">
                        <h3><?php echo esc_html( $stage_label ); ?></h3>
                        <ol>
                            <?php foreach ( $stages[ $stage ] as $step ) : ?>
                                <li class="sc-pathway-step-card">
                                    <div class="sc-pathway-step-card__number"><?php echo esc_html( absint( $step['order'] ) + 1 ); ?></div>
                                    <div class="sc-pathway-step-card__body">
                                        <p class="sc-pathway-step-card__meta"><span><?php echo esc_html( self::step_kind_options()[ $step['kind'] ] ?? ucfirst( $step['kind'] ) ); ?></span><span><?php echo esc_html( self::difficulty_options()[ $step['difficulty'] ] ?? ucfirst( $step['difficulty'] ) ); ?></span><?php if ( $step['minutes'] ) : ?><span><?php echo esc_html( self::format_minutes( $step['minutes'] ) ); ?></span><?php endif; ?><?php if ( $step['required'] ) : ?><strong><?php esc_html_e( 'Required', 'sustainable-catalyst-library' ); ?></strong><?php endif; ?></p>
                                        <h4><?php if ( $step['url'] ) : ?><a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a><?php else : ?><?php echo esc_html( $step['label'] ); ?><?php endif; ?></h4>
                                        <?php if ( $step['note'] ) : ?><p><?php echo esc_html( $step['note'] ); ?></p><?php elseif ( $step['summary'] ) : ?><p><?php echo esc_html( wp_trim_words( $step['summary'], 28 ) ); ?></p><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endforeach; ?>
            </section>

            <?php if ( $data['topics'] || $data['concepts'] || $data['entities'] ) : ?>
                <section class="sc-public-pathway__semantic">
                    <h2><?php esc_html_e( 'Topics and concepts', 'sustainable-catalyst-library' ); ?></h2>
                    <?php echo self::render_semantic_terms( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function render_pathway_links( $data ) {
        $prerequisites = array();
        foreach ( $data['prerequisite_ids'] as $id ) {
            $record = self::get_pathway_data( $id, false );
            if ( $record ) {
                $prerequisites[] = $record;
            }
        }
        $continuations = array();
        foreach ( $data['continuation_ids'] as $id ) {
            $record = self::get_pathway_data( $id, false );
            if ( $record ) {
                $continuations[] = $record;
            }
        }
        if ( ! $prerequisites && ! $continuations ) {
            return '';
        }
        ob_start();
        ?>
        <section class="sc-public-pathway__links">
            <?php if ( $prerequisites ) : ?><div><h2><?php esc_html_e( 'Recommended prerequisites', 'sustainable-catalyst-library' ); ?></h2><ul><?php foreach ( $prerequisites as $record ) : ?><li><a href="<?php echo esc_url( $record['url'] ); ?>"><?php echo esc_html( $record['title'] ); ?></a><span><?php echo esc_html( $record['level_label'] ); ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if ( $continuations ) : ?><div><h2><?php esc_html_e( 'Continue from here', 'sustainable-catalyst-library' ); ?></h2><ul><?php foreach ( $continuations as $record ) : ?><li><a href="<?php echo esc_url( $record['url'] ); ?>"><?php echo esc_html( $record['title'] ); ?></a><span><?php echo esc_html( $record['level_label'] ); ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_semantic_terms( $data ) {
        ob_start();
        ?><div class="sc-pathway-semantic-groups">
        <?php if ( $data['topics'] ) : ?><div><h3><?php esc_html_e( 'Knowledge Topics', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['topics'] as $topic ) : ?><li><?php if ( ! empty( $topic['url'] ) ) : ?><a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a><?php else : ?><?php echo esc_html( $topic['name'] ); ?><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ( $data['concepts'] ) : ?><div><h3><?php esc_html_e( 'Concepts', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['concepts'] as $concept ) : ?><li><?php if ( ! empty( $concept['url'] ) ) : ?><a href="<?php echo esc_url( $concept['url'] ); ?>"><?php echo esc_html( $concept['title'] ); ?></a><?php else : ?><?php echo esc_html( $concept['title'] ); ?><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ( $data['entities'] ) : ?><div><h3><?php esc_html_e( 'Named Entities', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['entities'] as $entity ) : ?><li><?php if ( ! empty( $entity['url'] ) ) : ?><a href="<?php echo esc_url( $entity['url'] ); ?>"><?php echo esc_html( $entity['title'] ); ?></a><?php else : ?><?php echo esc_html( $entity['title'] ); ?><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        </div><?php
        return ob_get_clean();
    }

    public static function render_map( $pathway_id, $include_private = false ) {
        $map = self::map_data( $pathway_id, $include_private );
        if ( ! $map ) {
            return '';
        }
        ob_start();
        ?>
        <div class="sc-article-map" data-sc-article-map>
            <div class="sc-article-map__viewport" tabindex="0" aria-label="<?php echo esc_attr( sprintf( __( 'Article map for %s', 'sustainable-catalyst-library' ), $map['title'] ) ); ?>">
                <svg viewBox="0 0 <?php echo esc_attr( $map['width'] ); ?> <?php echo esc_attr( $map['height'] ); ?>" role="img" aria-labelledby="sc-map-title-<?php echo esc_attr( $pathway_id ); ?> sc-map-desc-<?php echo esc_attr( $pathway_id ); ?>">
                    <title id="sc-map-title-<?php echo esc_attr( $pathway_id ); ?>"><?php echo esc_html( $map['title'] ); ?></title>
                    <desc id="sc-map-desc-<?php echo esc_attr( $pathway_id ); ?>"><?php esc_html_e( 'A visual map of ordered pathway steps and semantic relationships. A complete text list follows the map.', 'sustainable-catalyst-library' ); ?></desc>
                    <defs><marker id="sc-map-arrow-<?php echo esc_attr( $pathway_id ); ?>" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z"></path></marker></defs>
                    <?php foreach ( $map['edges'] as $edge ) : $from = self::map_node_by_id( $map['nodes'], $edge['from'] ); $to = self::map_node_by_id( $map['nodes'], $edge['to'] ); if ( ! $from || ! $to ) { continue; } ?>
                        <line class="sc-map-edge sc-map-edge--<?php echo esc_attr( $edge['type'] ); ?>" x1="<?php echo esc_attr( $from['x'] + 90 ); ?>" y1="<?php echo esc_attr( $from['y'] + 34 ); ?>" x2="<?php echo esc_attr( $to['x'] + 90 ); ?>" y2="<?php echo esc_attr( $to['y'] + 34 ); ?>" marker-end="url(#sc-map-arrow-<?php echo esc_attr( $pathway_id ); ?>)"><title><?php echo esc_html( $edge['label'] ); ?></title></line>
                    <?php endforeach; ?>
                    <?php foreach ( $map['nodes'] as $node ) : ?>
                        <g class="sc-map-node sc-map-node--<?php echo esc_attr( $node['kind'] ); ?>" transform="translate(<?php echo esc_attr( $node['x'] ); ?> <?php echo esc_attr( $node['y'] ); ?>)">
                            <?php if ( $node['url'] ) : ?><a href="<?php echo esc_url( $node['url'] ); ?>"><?php endif; ?>
                            <rect width="180" height="68" rx="0" ry="0"></rect>
                            <text x="12" y="21" class="sc-map-node__number"><?php echo esc_html( $node['order'] + 1 ); ?></text>
                            <text x="38" y="21" class="sc-map-node__kind"><?php echo esc_html( strtoupper( $node['kind'] ) ); ?></text>
                            <text x="12" y="43" class="sc-map-node__label"><?php echo esc_html( self::truncate_label( $node['label'], 27 ) ); ?></text>
                            <text x="12" y="58" class="sc-map-node__stage"><?php echo esc_html( $node['stage_label'] ); ?></text>
                            <?php if ( $node['url'] ) : ?></a><?php endif; ?>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>
            <ol class="sc-article-map__list" data-sc-map-list><?php foreach ( $map['nodes'] as $node ) : ?><li><strong><?php echo esc_html( $node['stage_label'] ); ?>:</strong> <?php if ( $node['url'] ) : ?><a href="<?php echo esc_url( $node['url'] ); ?>"><?php echo esc_html( $node['label'] ); ?></a><?php else : ?><?php echo esc_html( $node['label'] ); ?><?php endif; ?> <span><?php echo esc_html( self::step_kind_options()[ $node['kind'] ] ?? $node['kind'] ); ?></span></li><?php endforeach; ?></ol>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function map_node_by_id( $nodes, $id ) {
        foreach ( $nodes as $node ) {
            if ( ( $node['id'] ?? '' ) === $id ) {
                return $node;
            }
        }
        return array();
    }

    private static function truncate_label( $label, $length ) {
        $label = wp_strip_all_tags( $label );
        $strlen = function_exists( 'mb_strlen' ) ? mb_strlen( $label ) : strlen( $label );
        if ( $strlen <= $length ) { return $label; }
        $slice = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, $length - 1 ) : substr( $label, 0, $length - 1 );
        return $slice . '…';
    }

    public static function pathways_for_node( $kind, $id, $include_private = false, $limit = 20 ) {
        $key = sanitize_key( $kind ) . ':' . absint( $id );
        if ( ! self::node_exists( $kind, $id ) ) {
            return array();
        }
        $query = new WP_Query(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $limit ) ) ),
                'meta_query'     => array(
                    array(
                        'key'     => self::META_NODE_KEYS,
                        'value'   => $key,
                        'compare' => 'LIKE',
                    ),
                ),
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );
        $items = array();
        foreach ( $query->posts as $post ) {
            $data = self::get_pathway_data( $post->ID, $include_private );
            if ( $data ) {
                $items[] = $data;
            }
        }
        return $items;
    }

    public static function render_node_pathways( $kind, $id, $compact = false ) {
        $items = self::pathways_for_node( $kind, $id, false, $compact ? 4 : 8 );
        if ( ! $items ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-knowledge-pathways' );
        ob_start();
        ?>
        <section class="sc-node-pathways <?php echo $compact ? 'is-compact' : ''; ?>">
            <p class="sc-pathway-kicker"><?php esc_html_e( 'Knowledge pathways', 'sustainable-catalyst-library' ); ?></p>
            <h2><?php esc_html_e( 'Explore this record in context', 'sustainable-catalyst-library' ); ?></h2>
            <div><?php foreach ( $items as $item ) : ?><article><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3><p><?php echo esc_html( $item['summary'] ); ?></p><span><?php echo esc_html( $item['level_label'] ); ?> · <?php echo esc_html( sprintf( _n( '%d step', '%d steps', $item['step_count'], 'sustainable-catalyst-library' ), $item['step_count'] ) ); ?></span></article><?php endforeach; ?></div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function recommend_pathways( $context = array(), $include_private = false, $limit = 6 ) {
        $context = wp_parse_args(
            is_array( $context ) ? $context : array(),
            array(
                'query'       => '',
                'topic_ids'   => array(),
                'concept_ids' => array(),
                'entity_ids'  => array(),
                'node_keys'   => array(),
                'level'       => '',
                'audience'    => '',
            )
        );
        $context['topic_ids'] = self::id_list( $context['topic_ids'] );
        $context['concept_ids'] = self::id_list( $context['concept_ids'] );
        $context['entity_ids'] = self::id_list( $context['entity_ids'] );
        $context['node_keys'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $context['node_keys'] ) ) ) );
        $context['level'] = sanitize_key( $context['level'] );
        $context['audience'] = sanitize_text_field( $context['audience'] );
        $context['query'] = sanitize_text_field( $context['query'] );
        $limit = max( 1, min( self::MAX_RECOMMENDATIONS, absint( $limit ) ) );

        $cache_key = self::TRANSIENT_RECOMMENDATION_PREFIX . md5( wp_json_encode( array( $context, $include_private, $limit ) ) );
        if ( ! $include_private ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $posts = get_posts(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => 250,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $results = array();
        $query_terms = self::tokenize( trim( $context['query'] . ' ' . $context['audience'] ) );

        foreach ( $posts as $post ) {
            $data = self::get_pathway_data( $post->ID, $include_private );
            if ( ! $data ) {
                continue;
            }
            $score = 0;
            $reasons = array();
            $topic_ids = self::id_list( wp_list_pluck( $data['topics'], 'id' ) );
            $concept_ids = self::id_list( wp_list_pluck( $data['concepts'], 'id' ) );
            $entity_ids = self::id_list( wp_list_pluck( $data['entities'], 'id' ) );
            $node_keys = wp_list_pluck( $data['steps'], 'node_key' );

            $topic_overlap = array_intersect( $context['topic_ids'], $topic_ids );
            $concept_overlap = array_intersect( $context['concept_ids'], $concept_ids );
            $entity_overlap = array_intersect( $context['entity_ids'], $entity_ids );
            $node_overlap = array_intersect( $context['node_keys'], $node_keys );
            if ( $topic_overlap ) { $score += count( $topic_overlap ) * 14; $reasons[] = __( 'Shared Knowledge Topics', 'sustainable-catalyst-library' ); }
            if ( $concept_overlap ) { $score += count( $concept_overlap ) * 12; $reasons[] = __( 'Shared Concepts', 'sustainable-catalyst-library' ); }
            if ( $entity_overlap ) { $score += count( $entity_overlap ) * 7; $reasons[] = __( 'Shared Named Entities', 'sustainable-catalyst-library' ); }
            if ( $node_overlap ) { $score += count( $node_overlap ) * 18; $reasons[] = __( 'Contains a relevant record', 'sustainable-catalyst-library' ); }
            if ( $context['level'] && $context['level'] === $data['level'] ) { $score += 6; $reasons[] = __( 'Matching level', 'sustainable-catalyst-library' ); }

            $haystack = strtolower( implode( ' ', array_merge( array( $data['title'], $data['summary'], $data['audience'] ), $data['recommendation_terms'] ) ) );
            $matches = 0;
            foreach ( $query_terms as $token ) {
                if ( false !== strpos( $haystack, $token ) ) {
                    $matches++;
                }
            }
            if ( $matches ) { $score += $matches * 3; $reasons[] = __( 'Matches the research question or audience', 'sustainable-catalyst-library' ); }
            if ( ! $score && ! $query_terms && ! $context['topic_ids'] && ! $context['concept_ids'] && ! $context['node_keys'] ) {
                $score = 1;
                $reasons[] = __( 'Recently maintained pathway', 'sustainable-catalyst-library' );
            }
            if ( $score ) {
                $results[] = array(
                    'pathway' => $data,
                    'score'   => $score,
                    'reasons' => array_values( array_unique( $reasons ) ),
                );
            }
        }
        usort( $results, static function ( $left, $right ) { return $right['score'] <=> $left['score']; } );
        $result = array(
            'schema'       => self::RECOMMENDATION_SCHEMA,
            'context'      => $context,
            'items'        => array_slice( $results, 0, $limit ),
            'generated_at' => current_time( 'mysql' ),
        );
        if ( ! $include_private ) {
            set_transient( $cache_key, $result, self::RECOMMENDATION_CACHE_SECONDS );
        }
        return $result;
    }

    public static function filter_research_librarian_recommendations( $recommendations, $context = array(), $limit = 6 ) {
        $result = self::recommend_pathways( $context, false, $limit );
        $items = is_array( $recommendations ) ? $recommendations : array();
        foreach ( $result['items'] as $item ) {
            $items[] = array(
                'type'        => 'knowledge-pathway',
                'id'          => $item['pathway']['id'],
                'title'       => $item['pathway']['title'],
                'url'         => $item['pathway']['url'],
                'summary'     => $item['pathway']['summary'],
                'level'       => $item['pathway']['level'],
                'steps'       => $item['pathway']['step_count'],
                'score'       => $item['score'],
                'reasons'     => $item['reasons'],
                'schema'      => self::RECOMMENDATION_SCHEMA,
            );
        }
        return $items;
    }

    public static function derive_from_project( $project_id, $args = array() ) {
        $project_id = absint( $project_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error( 'invalid_pathway_project', __( 'The Research Project is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            return new WP_Error( 'pathway_project_forbidden', __( 'You cannot create a pathway from this project.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $args = wp_parse_args( $args, array( 'title' => '', 'force_new' => false, 'include_evidence' => true ) );
        if ( ! $args['force_new'] ) {
            $existing = get_posts(
                array(
                    'post_type'      => self::PATHWAY_POST_TYPE,
                    'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                    'posts_per_page' => 1,
                    'meta_key'       => self::META_DERIVED_PROJECT_ID,
                    'meta_value'     => $project_id,
                    'fields'         => 'ids',
                )
            );
            if ( $existing ) {
                return absint( $existing[0] );
            }
        }

        $project = SC_Library_Connected_Research_Environment::project_data( $project_id, true );
        if ( ! $project ) {
            return new WP_Error( 'project_data_unavailable', __( 'The connected project data could not be read.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
        }
        $pathway_id = wp_insert_post(
            array(
                'post_type'    => self::PATHWAY_POST_TYPE,
                'post_status'  => 'draft',
                'post_title'   => sanitize_text_field( $args['title'] ?: sprintf( __( 'Research Pathway: %s', 'sustainable-catalyst-library' ), $project['title'] ) ),
                'post_excerpt' => sanitize_text_field( $project['summary'] ?? '' ),
                'post_content' => wp_kses_post( $project['description'] ?? '' ),
            ),
            true
        );
        if ( is_wp_error( $pathway_id ) ) {
            return $pathway_id;
        }

        $raw_steps = array(
            array( 'kind' => 'project', 'node_id' => $project_id, 'stage' => 'orientation', 'difficulty' => 'foundational', 'required' => 1, 'note' => __( 'Begin with the project question, scope, and methods.', 'sustainable-catalyst-library' ) ),
        );
        foreach ( self::id_list( $project['document_ids'] ?? array() ) as $id ) {
            $raw_steps[] = array( 'kind' => 'document', 'node_id' => $id, 'stage' => 'foundation', 'difficulty' => 'foundational', 'required' => 1 );
        }
        foreach ( (array) ( $project['source_entries'] ?? array() ) as $entry ) {
            if ( 'excluded' === ( $entry['inclusion'] ?? '' ) ) { continue; }
            $raw_steps[] = array( 'kind' => 'source', 'node_id' => absint( $entry['source_id'] ?? 0 ), 'stage' => 'evidence', 'difficulty' => 'intermediate', 'required' => 'included' === ( $entry['inclusion'] ?? '' ), 'note' => sanitize_text_field( $entry['annotation'] ?? '' ) );
        }
        foreach ( self::id_list( $project['claim_ids'] ?? array() ) as $id ) {
            $raw_steps[] = array( 'kind' => 'claim', 'node_id' => $id, 'stage' => 'analysis', 'difficulty' => 'advanced', 'required' => 1 );
        }
        if ( $args['include_evidence'] ) {
            foreach ( self::id_list( $project['evidence_ids'] ?? array() ) as $id ) {
                $raw_steps[] = array( 'kind' => 'evidence', 'node_id' => $id, 'stage' => 'analysis', 'difficulty' => 'advanced', 'required' => 0 );
            }
        }
        $steps = self::sanitize_steps( $raw_steps, $pathway_id );
        update_post_meta( $pathway_id, self::META_STEPS, $steps );
        update_post_meta( $pathway_id, self::META_NODE_KEYS, wp_list_pluck( $steps, 'node_key' ) );
        update_post_meta( $pathway_id, self::META_DERIVED_PROJECT_ID, $project_id );
        update_post_meta( $pathway_id, self::META_LEVEL, 'mixed' );
        update_post_meta( $pathway_id, self::META_MAP_MODE, 'stages' );
        update_post_meta( $pathway_id, self::META_OUTCOMES, self::string_list( $project['objectives'] ?? array() ) );
        wp_set_object_terms( $pathway_id, 'project-path', self::PATHWAY_TYPE_TAXONOMY, false );
        if ( taxonomy_exists( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY ) ) {
            $topic_ids = wp_get_object_terms( $project_id, SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $topic_ids ) ) {
                wp_set_object_terms( $pathway_id, array_map( 'absint', $topic_ids ), SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, false );
            }
        }
        self::invalidate_recommendation_cache();
        return absint( $pathway_id );
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $pathways = get_posts(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $projects = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 250,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $published = count( array_filter( $pathways, static function ( $post ) { return 'publish' === $post->post_status; } ) );
        $total_steps = 0;
        $derived = 0;
        foreach ( $pathways as $pathway ) {
            $total_steps += count( self::pathway_steps( $pathway->ID, true ) );
            if ( get_post_meta( $pathway->ID, self::META_DERIVED_PROJECT_ID, true ) ) {
                $derived++;
            }
        }
        ?>
        <div class="wrap sc-pathway-workspace">
            <p class="sc-pathway-kicker"><?php esc_html_e( 'Knowledge Library v3.3.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Knowledge Pathways and Article Maps', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Create ordered reading and research sequences, connect prerequisites and continuations, map documents and evidence visually, and publish contextual routes through the Knowledge Library.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-pathway-workspace__metrics">
                <article><strong><?php echo esc_html( count( $pathways ) ); ?></strong><span><?php esc_html_e( 'Pathways', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $published ); ?></strong><span><?php esc_html_e( 'Published', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $total_steps ); ?></strong><span><?php esc_html_e( 'Curated steps', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $derived ); ?></strong><span><?php esc_html_e( 'Project-derived', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-pathway-workspace__derive">
                <div><h2><?php esc_html_e( 'Create a pathway from a Research Project', 'sustainable-catalyst-library' ); ?></h2><p><?php esc_html_e( 'Generate a draft sequence from the project brief, connected documents, included Sources, Claims, and Evidence Notes. The draft remains editable and unpublished.', 'sustainable-catalyst-library' ); ?></p></div>
                <div><select data-sc-derived-project><option value="0"><?php esc_html_e( 'Select Research Project', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( $project->ID ); ?>"><?php echo esc_html( $project->post_title ); ?></option><?php endforeach; ?></select><button type="button" class="button button-primary" data-sc-derive-pathway><?php esc_html_e( 'Create Draft Pathway', 'sustainable-catalyst-library' ); ?></button><div data-sc-derive-pathway-status aria-live="polite"></div></div>
            </section>

            <section>
                <div class="sc-pathway-workspace__heading"><h2><?php esc_html_e( 'Pathway registry', 'sustainable-catalyst-library' ); ?></h2><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::PATHWAY_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Knowledge Pathway', 'sustainable-catalyst-library' ); ?></a></div>
                <?php if ( $pathways ) : ?>
                    <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Pathway', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Level', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Steps', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Map', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Project', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $pathways as $pathway ) : $data = self::get_pathway_data( $pathway->ID, true ); ?>
                        <tr><td><a href="<?php echo esc_url( get_edit_post_link( $pathway->ID, 'raw' ) ); ?>"><?php echo esc_html( $pathway->post_title ); ?></a><small><?php echo esc_html( $pathway->post_excerpt ); ?></small></td><td><?php echo esc_html( $data['level_label'] ?? '—' ); ?></td><td><?php echo esc_html( count( $data['steps'] ?? array() ) ); ?></td><td><?php echo esc_html( self::map_mode_options()[ $data['map_mode'] ?? 'stages' ] ?? '—' ); ?></td><td><?php echo ! empty( $data['derived_project_id'] ) ? '<a href="' . esc_url( get_edit_post_link( $data['derived_project_id'], 'raw' ) ) . '">' . esc_html( get_the_title( $data['derived_project_id'] ) ) . '</a>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo esc_html( ucfirst( $pathway->post_status ) ); ?></td></tr>
                    <?php endforeach; ?></tbody></table>
                <?php else : ?><p><?php esc_html_e( 'No pathways have been created yet.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
            </section>
        </div>
        <?php
    }

    public function shortcode_pathway( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'slug' => '', 'include_private' => 'false' ), $atts, 'sc_knowledge_pathway' );
        $pathway_id = absint( $atts['id'] );
        if ( ! $pathway_id && $atts['slug'] ) {
            $post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, self::PATHWAY_POST_TYPE );
            $pathway_id = $post ? absint( $post->ID ) : 0;
        }
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $pathway_id );
        return self::render_pathway( $pathway_id, $include_private );
    }

    public function shortcode_article_map( $atts ) {
        $atts = shortcode_atts( array( 'pathway' => 0, 'include_private' => 'false' ), $atts, 'sc_article_map' );
        $pathway_id = self::resolve_pathway_id( $atts['pathway'] );
        $include_private = rest_sanitize_boolean( $atts['include_private'] ) && current_user_can( 'edit_post', $pathway_id );
        if ( $include_private ) { nocache_headers(); }
        wp_enqueue_style( 'sc-library-knowledge-pathways' );
        wp_enqueue_script( 'sc-library-knowledge-pathways' );
        return self::render_map( $pathway_id, $include_private );
    }

    public function shortcode_recommendations( $atts ) {
        $atts = shortcode_atts( array( 'query' => '', 'level' => '', 'audience' => '', 'limit' => 6, 'topic_ids' => '', 'concept_ids' => '', 'node_keys' => '' ), $atts, 'sc_pathway_recommendations' );
        $result = self::recommend_pathways(
            array(
                'query'       => $atts['query'],
                'level'       => $atts['level'],
                'audience'    => $atts['audience'],
                'topic_ids'   => self::id_list( $atts['topic_ids'] ),
                'concept_ids' => self::id_list( $atts['concept_ids'] ),
                'node_keys'   => preg_split( '/[\s,]+/', $atts['node_keys'] ),
            ),
            false,
            absint( $atts['limit'] )
        );
        if ( ! $result['items'] ) { return ''; }
        wp_enqueue_style( 'sc-library-knowledge-pathways' );
        ob_start(); ?>
        <section class="sc-pathway-recommendations"><p class="sc-pathway-kicker"><?php esc_html_e( 'Guided exploration', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Recommended Knowledge Pathways', 'sustainable-catalyst-library' ); ?></h2><div><?php foreach ( $result['items'] as $item ) : ?><article><h3><a href="<?php echo esc_url( $item['pathway']['url'] ); ?>"><?php echo esc_html( $item['pathway']['title'] ); ?></a></h3><p><?php echo esc_html( $item['pathway']['summary'] ); ?></p><span><?php echo esc_html( $item['pathway']['level_label'] ); ?> · <?php echo esc_html( sprintf( _n( '%d step', '%d steps', $item['pathway']['step_count'], 'sustainable-catalyst-library' ), $item['pathway']['step_count'] ) ); ?></span><?php if ( $item['reasons'] ) : ?><small><?php echo esc_html( implode( ' · ', $item['reasons'] ) ); ?></small><?php endif; ?></article><?php endforeach; ?></div></section>
        <?php return ob_get_clean();
    }

    public function ajax_search_nodes() {
        check_ajax_referer( 'sc_library_pathways_v330', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sustainable-catalyst-library' ) ), 403 ); }
        $kind = sanitize_key( wp_unslash( $_POST['kind'] ?? 'document' ) );
        $query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        if ( strlen( $query ) < 2 ) { wp_send_json_error( array( 'message' => __( 'Enter at least two characters.', 'sustainable-catalyst-library' ) ), 400 ); }
        $items = self::search_nodes( $kind, $query, true, 30 );
        wp_send_json_success( array( 'items' => $items ) );
    }

    public function ajax_derive_pathway() {
        check_ajax_referer( 'sc_library_pathways_v330', 'nonce' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        $result = self::derive_from_project( $project_id );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) ); }
        wp_send_json_success( array( 'pathway_id' => $result, 'edit_url' => get_edit_post_link( $result, 'raw' ) ) );
    }

    public function ajax_preview_map() {
        check_ajax_referer( 'sc_library_pathways_v330', 'nonce' );
        $pathway_id = absint( wp_unslash( $_POST['pathway_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $pathway_id ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sustainable-catalyst-library' ) ), 403 ); }
        wp_send_json_success( array( 'html' => self::render_map( $pathway_id, true ), 'map' => self::map_data( $pathway_id, true ) ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/pathways',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_pathways' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/pathways/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_pathway' ),
                    'permission_callback' => array( $this, 'rest_can_read_pathway' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_update_pathway' ),
                    'permission_callback' => array( $this, 'rest_can_edit_pathway' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/pathways/(?P<id>\d+)/map',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_pathway_map' ),
                'permission_callback' => array( $this, 'rest_can_read_pathway' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/pathways/recommendations',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_recommendations' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/nodes/(?P<kind>[a-z-]+)/(?P<id>\d+)/pathways',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_node_pathways' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/pathway',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_derive_pathway' ),
                'permission_callback' => array( $this, 'rest_can_edit_project' ),
            )
        );
    }

    public function rest_pathways( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        $query = new WP_Query(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
                'paged'          => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
                's'              => sanitize_text_field( $request->get_param( 'search' ) ),
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $items = array();
        foreach ( $query->posts as $post ) {
            $data = self::get_pathway_data( $post->ID, $include_private );
            if ( $data ) { $items[] = $data; }
        }
        return rest_ensure_response( array( 'schema' => self::PATHWAY_SCHEMA, 'items' => $items, 'total' => absint( $query->found_posts ), 'pages' => absint( $query->max_num_pages ) ) );
    }

    public function rest_pathway( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return rest_ensure_response( self::get_pathway_data( $id, current_user_can( 'edit_post', $id ) ) );
    }

    public function rest_update_pathway( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        if ( array_key_exists( 'level', $payload ) ) { update_post_meta( $id, self::META_LEVEL, self::allowed_value( $payload['level'], self::level_options(), 'mixed' ) ); }
        if ( array_key_exists( 'audience', $payload ) ) { self::update_or_delete_meta( $id, self::META_AUDIENCE, sanitize_text_field( $payload['audience'] ) ); }
        if ( array_key_exists( 'estimated_minutes', $payload ) ) { self::update_or_delete_meta( $id, self::META_ESTIMATED_MINUTES, max( 0, min( 100000, absint( $payload['estimated_minutes'] ) ) ) ); }
        if ( array_key_exists( 'outcomes', $payload ) ) { self::update_or_delete_meta( $id, self::META_OUTCOMES, array_slice( self::string_list( $payload['outcomes'] ), 0, self::MAX_OUTCOMES ) ); }
        if ( array_key_exists( 'steps', $payload ) ) { $steps = self::sanitize_steps( $payload['steps'], $id ); self::update_or_delete_meta( $id, self::META_STEPS, $steps ); self::update_or_delete_meta( $id, self::META_NODE_KEYS, wp_list_pluck( $steps, 'node_key' ) ); }
        if ( array_key_exists( 'prerequisite_ids', $payload ) ) { self::update_or_delete_meta( $id, self::META_PREREQUISITE_IDS, self::pathway_id_list( $payload['prerequisite_ids'], $id, true ) ); }
        if ( array_key_exists( 'continuation_ids', $payload ) ) { self::update_or_delete_meta( $id, self::META_CONTINUATION_IDS, self::pathway_id_list( $payload['continuation_ids'], $id, true ) ); }
        if ( array_key_exists( 'map_mode', $payload ) ) { update_post_meta( $id, self::META_MAP_MODE, self::allowed_value( $payload['map_mode'], self::map_mode_options(), 'stages' ) ); }
        if ( array_key_exists( 'recommendation_terms', $payload ) ) { self::update_or_delete_meta( $id, self::META_RECOMMENDATION_TERMS, sanitize_text_field( is_array( $payload['recommendation_terms'] ) ? implode( ', ', $payload['recommendation_terms'] ) : $payload['recommendation_terms'] ) ); }
        if ( array_key_exists( 'concept_ids', $payload ) ) { self::update_or_delete_meta( $id, self::META_CONCEPT_IDS, self::valid_node_ids( 'concept', $payload['concept_ids'], true ) ); }
        if ( array_key_exists( 'entity_ids', $payload ) ) { self::update_or_delete_meta( $id, self::META_ENTITY_IDS, self::valid_node_ids( 'entity', $payload['entity_ids'], true ) ); }
        if ( array_key_exists( 'topic_ids', $payload ) && taxonomy_exists( SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY ) ) { wp_set_object_terms( $id, self::id_list( $payload['topic_ids'] ), SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, false ); }
        update_post_meta( $id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        update_post_meta( $id, self::META_UPDATED_BY, get_current_user_id() );
        self::invalidate_recommendation_cache();
        return rest_ensure_response( self::get_pathway_data( $id, true ) );
    }

    public function rest_pathway_map( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return rest_ensure_response( self::map_data( $id, current_user_can( 'edit_post', $id ) ) );
    }

    public function rest_recommendations( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $include_private = current_user_can( 'edit_posts' ) && ! empty( $payload['include_private'] );
        return rest_ensure_response( self::recommend_pathways( $payload, $include_private, absint( $payload['limit'] ?? 6 ) ) );
    }

    public function rest_node_pathways( WP_REST_Request $request ) {
        $kind = sanitize_key( $request['kind'] );
        $id = absint( $request['id'] );
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        return rest_ensure_response( array( 'schema' => self::PATHWAY_SCHEMA, 'kind' => $kind, 'id' => $id, 'items' => self::pathways_for_node( $kind, $id, $include_private, absint( $request->get_param( 'limit' ) ?: 20 ) ) ) );
    }

    public function rest_derive_pathway( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::derive_from_project( absint( $request['id'] ), $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( self::get_pathway_data( $result, true ) );
    }

    public function rest_can_read_pathway( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'edit_post', $id ) || ( self::PATHWAY_POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) );
    }

    public function rest_can_edit_pathway( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return self::PATHWAY_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) { return $response; }
        $route = (string) $request->get_route();
        if ( false === strpos( $route, '/' . self::API_NAMESPACE . '/knowledge/pathways' ) && false === strpos( $route, '/pathway' ) ) { return $response; }
        $id = absint( $request->get_param( 'id' ) );
        $private = is_user_logged_in() || rest_sanitize_boolean( $request->get_param( 'include_private' ) ) || ( $id && 'publish' !== get_post_status( $id ) );
        if ( $private ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function register_public_assets() {
        wp_register_style( 'sc-library-knowledge-pathways', SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-pathways-article-maps.css', array( 'sc-library-connected-research', 'sc-library-topics-concepts-relationships' ), self::VERSION );
        wp_register_script( 'sc-library-knowledge-pathways', SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-pathways-article-maps.js', array(), self::VERSION, true );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) { return; }
        $supported = self::PATHWAY_POST_TYPE === $screen->post_type || false !== strpos( (string) $screen->id, 'sc-library-pathways-maps' );
        if ( ! $supported ) { return; }
        wp_enqueue_style( 'sc-library-knowledge-pathways', SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-pathways-article-maps.css', array( 'sc-library-connected-research', 'sc-library-topics-concepts-relationships' ), self::VERSION );
        wp_enqueue_script( 'sc-library-knowledge-pathways', SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-pathways-article-maps.js', array(), self::VERSION, true );
        wp_localize_script(
            'sc-library-knowledge-pathways',
            'SCLibraryKnowledgePathways',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_pathways_v330' ),
                'strings' => array(
                    'working'       => __( 'Working…', 'sustainable-catalyst-library' ),
                    'searchPrompt'  => __( 'Search by title or identifier', 'sustainable-catalyst-library' ),
                    'noResults'     => __( 'No matching records found.', 'sustainable-catalyst-library' ),
                    'derived'       => __( 'Draft pathway created.', 'sustainable-catalyst-library' ),
                    'previewed'     => __( 'Article map refreshed.', 'sustainable-catalyst-library' ),
                    'remove'        => __( 'Remove', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function template_include( $template ) {
        if ( is_singular( self::PATHWAY_POST_TYPE ) ) {
            $candidate = SC_LIBRARY_DIR . 'templates/single-sc_knowledge_pathway.php';
            if ( is_file( $candidate ) ) { return $candidate; }
        }
        return $template;
    }

    public function pathway_columns( $columns ) {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'title' === $key ) {
                $result['sc_pathway_level'] = __( 'Level', 'sustainable-catalyst-library' );
                $result['sc_pathway_steps'] = __( 'Steps', 'sustainable-catalyst-library' );
                $result['sc_pathway_project'] = __( 'Project', 'sustainable-catalyst-library' );
            }
        }
        return $result;
    }

    public function pathway_column_content( $column, $post_id ) {
        if ( 'sc_pathway_level' === $column ) {
            $level = (string) get_post_meta( $post_id, self::META_LEVEL, true ) ?: 'mixed';
            echo esc_html( self::level_options()[ $level ] ?? $level );
        } elseif ( 'sc_pathway_steps' === $column ) {
            echo esc_html( count( self::pathway_steps( $post_id, true ) ) );
        } elseif ( 'sc_pathway_project' === $column ) {
            $project_id = absint( get_post_meta( $post_id, self::META_DERIVED_PROJECT_ID, true ) );
            echo $project_id ? '<a href="' . esc_url( get_edit_post_link( $project_id, 'raw' ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    public function cleanup_deleted_pathway( $post_id ) {
        if ( self::PATHWAY_POST_TYPE !== get_post_type( $post_id ) ) { return; }
        $referencing = get_posts(
            array(
                'post_type'      => self::PATHWAY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array( 'key' => self::META_PREREQUISITE_IDS, 'value' => 'i:' . absint( $post_id ) . ';', 'compare' => 'LIKE' ),
                    array( 'key' => self::META_CONTINUATION_IDS, 'value' => 'i:' . absint( $post_id ) . ';', 'compare' => 'LIKE' ),
                    array( 'key' => self::META_NODE_KEYS, 'value' => 'pathway:' . absint( $post_id ), 'compare' => 'LIKE' ),
                ),
            )
        );
        foreach ( $referencing as $id ) {
            self::update_or_delete_meta( $id, self::META_PREREQUISITE_IDS, array_values( array_diff( self::id_list( get_post_meta( $id, self::META_PREREQUISITE_IDS, true ) ), array( absint( $post_id ) ) ) ) );
            self::update_or_delete_meta( $id, self::META_CONTINUATION_IDS, array_values( array_diff( self::id_list( get_post_meta( $id, self::META_CONTINUATION_IDS, true ) ), array( absint( $post_id ) ) ) ) );
            $steps = array_values( array_filter( self::pathway_steps( $id, true ), static function ( $step ) use ( $post_id ) { return ! ( 'pathway' === $step['kind'] && absint( $step['node_id'] ) === absint( $post_id ) ); } ) );
            $steps = self::sanitize_steps( $steps, $id );
            self::update_or_delete_meta( $id, self::META_STEPS, $steps );
            self::update_or_delete_meta( $id, self::META_NODE_KEYS, wp_list_pluck( $steps, 'node_key' ) );
        }
        self::invalidate_recommendation_cache();
    }

    public function invalidate_status_cache( $new_status, $old_status, $post ) {
        if ( $post instanceof WP_Post && self::PATHWAY_POST_TYPE === $post->post_type && $new_status !== $old_status ) { self::invalidate_recommendation_cache(); }
    }

    public function maybe_flush_rewrite_rules() {
        if ( self::VERSION !== get_option( self::OPTION_ROUTES_VERSION ) ) {
            flush_rewrite_rules( false );
            update_option( self::OPTION_ROUTES_VERSION, self::VERSION, false );
        }
    }

    private static function search_nodes( $kind, $query, $include_private, $limit ) {
        $kind = sanitize_key( $kind );
        if ( 'pathway' === $kind ) {
            $post_type = self::PATHWAY_POST_TYPE;
        } elseif ( 'topic' === $kind ) {
            $terms = get_terms( array( 'taxonomy' => SC_Library_Topics_Concepts_Relationships::TOPIC_TAXONOMY, 'search' => $query, 'number' => $limit, 'hide_empty' => false ) );
            if ( is_wp_error( $terms ) ) { return array(); }
            return array_map( static function ( $term ) { return array( 'id' => $term->term_id, 'kind' => 'topic', 'label' => $term->name, 'status' => get_term_meta( $term->term_id, SC_Library_Topics_Concepts_Relationships::META_TOPIC_STATUS, true ) ?: 'active' ); }, $terms );
        } else {
            $post_type = SC_Library_Topics_Concepts_Relationships::node_post_type( $kind );
        }
        if ( ! $post_type ) { return array(); }
        $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish', 'posts_per_page' => max( 1, min( 50, absint( $limit ) ) ), 's' => $query, 'orderby' => 'relevance', 'order' => 'DESC' ) );
        return array_map( static function ( $post ) use ( $kind ) { return array( 'id' => $post->ID, 'kind' => $kind, 'label' => $post->post_title, 'status' => $post->post_status ); }, $posts );
    }

    private static function node_exists( $kind, $id ) {
        if ( 'pathway' === sanitize_key( $kind ) ) { return self::PATHWAY_POST_TYPE === get_post_type( absint( $id ) ); }
        return SC_Library_Topics_Concepts_Relationships::node_exists( $kind, $id );
    }

    private static function node_is_public( $kind, $id ) {
        if ( 'pathway' === sanitize_key( $kind ) ) { return self::PATHWAY_POST_TYPE === get_post_type( absint( $id ) ) && 'publish' === get_post_status( absint( $id ) ); }
        return SC_Library_Topics_Concepts_Relationships::node_is_public( $kind, $id );
    }

    private static function node_label( $kind, $id ) {
        return 'pathway' === sanitize_key( $kind ) ? get_the_title( absint( $id ) ) : SC_Library_Topics_Concepts_Relationships::node_label( $kind, $id );
    }

    private static function node_summary( $kind, $id ) {
        if ( 'pathway' === sanitize_key( $kind ) ) {
            $post = get_post( absint( $id ) );
            return $post ? ( $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ) ) : '';
        }
        return SC_Library_Topics_Concepts_Relationships::node_summary( $kind, $id );
    }

    private static function node_url( $kind, $id ) {
        return 'pathway' === sanitize_key( $kind ) && 'publish' === get_post_status( absint( $id ) ) ? get_permalink( absint( $id ) ) : ( 'pathway' === sanitize_key( $kind ) ? '' : SC_Library_Topics_Concepts_Relationships::node_url( $kind, $id ) );
    }

    private static function valid_node_ids( $kind, $raw, $include_private ) {
        $ids = array();
        foreach ( self::id_list( $raw ) as $id ) {
            if ( self::node_exists( $kind, $id ) && ( $include_private || self::node_is_public( $kind, $id ) ) ) { $ids[] = $id; }
            if ( count( $ids ) >= self::MAX_ASSIGNMENTS ) { break; }
        }
        return array_values( array_unique( $ids ) );
    }

    private static function pathway_id_list( $raw, $exclude_id = 0, $include_private = false ) {
        $ids = array();
        foreach ( self::id_list( $raw ) as $id ) {
            if ( $id === absint( $exclude_id ) || self::PATHWAY_POST_TYPE !== get_post_type( $id ) ) { continue; }
            if ( ! $include_private && 'publish' !== get_post_status( $id ) ) { continue; }
            $ids[] = $id;
            if ( count( $ids ) >= self::MAX_PATHWAY_LINKS ) { break; }
        }
        return array_values( array_unique( $ids ) );
    }

    private static function resolve_pathway_id( $value ) {
        if ( is_numeric( $value ) && self::PATHWAY_POST_TYPE === get_post_type( absint( $value ) ) ) { return absint( $value ); }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, self::PATHWAY_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function string_list( $raw ) {
        if ( is_string( $raw ) ) { $raw = preg_split( '/\r\n|\r|\n/', $raw ); }
        return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $raw ) ) ) );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) { $raw = preg_split( '/[\s,]+/', $raw ); }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function tokenize( $value ) {
        $tokens = preg_split( '/[^\p{L}\p{N}]+/u', strtolower( (string) $value ) );
        return array_values( array_unique( array_filter( $tokens, static function ( $token ) { return ( function_exists( 'mb_strlen' ) ? mb_strlen( $token ) : strlen( $token ) ) >= 3; } ) ) );
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function update_or_delete_meta( $post_id, $key, $value ) {
        if ( '' === $value || 0 === $value || array() === $value || null === $value ) { delete_post_meta( $post_id, $key ); } else { update_post_meta( $post_id, $key, $value ); }
    }

    private static function format_minutes( $minutes ) {
        $minutes = absint( $minutes );
        if ( $minutes < 60 ) { return sprintf( _n( '%d minute', '%d minutes', $minutes, 'sustainable-catalyst-library' ), $minutes ); }
        $hours = floor( $minutes / 60 );
        $remaining = $minutes % 60;
        return $remaining ? sprintf( __( '%1$d hr %2$d min', 'sustainable-catalyst-library' ), $hours, $remaining ) : sprintf( _n( '%d hour', '%d hours', $hours, 'sustainable-catalyst-library' ), $hours );
    }

    public static function invalidate_recommendation_cache() {
        global $wpdb;
        if ( ! isset( $wpdb->options ) ) { return; }
        $like = '_transient_' . self::TRANSIENT_RECOMMENDATION_PREFIX . '%';
        $timeout_like = '_transient_timeout_' . self::TRANSIENT_RECOMMENDATION_PREFIX . '%';
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) { return; }
        WP_CLI::add_command( 'sc-library pathways list', static function () { $posts = get_posts( array( 'post_type' => self::PATHWAY_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1 ) ); foreach ( $posts as $post ) { WP_CLI::log( $post->ID . "\t" . $post->post_status . "\t" . $post->post_title ); } } );
        WP_CLI::add_command( 'sc-library pathways map', static function ( $args ) { $map = self::map_data( absint( $args[0] ?? 0 ), true ); $map ? WP_CLI::log( wp_json_encode( $map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) : WP_CLI::error( 'Pathway not found.' ); } );
        WP_CLI::add_command( 'sc-library pathways derive', static function ( $args, $assoc_args ) { $result = self::derive_from_project( absint( $args[0] ?? 0 ), array( 'force_new' => ! empty( $assoc_args['force-new'] ), 'title' => $assoc_args['title'] ?? '' ) ); is_wp_error( $result ) ? WP_CLI::error( $result->get_error_message() ) : WP_CLI::success( 'Created pathway ' . $result ); } );
        WP_CLI::add_command( 'sc-library pathways recommend', static function ( $args, $assoc_args ) { $result = self::recommend_pathways( array( 'query' => implode( ' ', $args ), 'level' => $assoc_args['level'] ?? '', 'topic_ids' => self::id_list( $assoc_args['topics'] ?? '' ), 'concept_ids' => self::id_list( $assoc_args['concepts'] ?? '' ) ), true, absint( $assoc_args['limit'] ?? 6 ) ); WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); } );
    }
}
