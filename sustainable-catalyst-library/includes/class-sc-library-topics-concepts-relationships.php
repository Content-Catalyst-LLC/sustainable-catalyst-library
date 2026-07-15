<?php
/**
 * Topics, Concepts, and Document Relationships.
 *
 * Establishes a canonical topic taxonomy, structured concepts, named entities,
 * controlled vocabularies, typed cross-record relationships, relationship
 * browsers, topic coverage and knowledge-gap analysis, and resumable migration
 * from retained Library topic systems.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Topics_Concepts_Relationships {
    public const VERSION = '3.2.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const TOPIC_TAXONOMY = 'sc_library_topic';
    public const CONCEPT_POST_TYPE = 'sc_library_concept';
    public const ENTITY_POST_TYPE = 'sc_named_entity';
    public const VOCABULARY_POST_TYPE = 'sc_control_vocab';
    public const RELATION_POST_TYPE = 'sc_knowledge_rel';

    public const NODE_SCHEMA = 'sc-library-knowledge-node/1.0';
    public const RELATION_SCHEMA = 'sc-library-knowledge-relation/1.0';
    public const COVERAGE_SCHEMA = 'sc-library-topic-coverage/1.0';
    public const MIGRATION_SCHEMA = 'sc-library-topic-migration/1.0';

    public const META_CONCEPT_IDS = '_sc_library_concept_ids';
    public const META_ENTITY_IDS = '_sc_library_entity_ids';

    public const META_CONCEPT_TYPE = '_sc_concept_type';
    public const META_CONCEPT_STATUS = '_sc_concept_status';
    public const META_CONCEPT_ALT_LABELS = '_sc_concept_alt_labels';
    public const META_CONCEPT_SCOPE_NOTE = '_sc_concept_scope_note';
    public const META_CONCEPT_URI = '_sc_concept_external_uri';
    public const META_CONCEPT_VOCABULARY_ID = '_sc_concept_vocabulary_id';

    public const META_ENTITY_TYPE = '_sc_entity_type';
    public const META_ENTITY_ALIASES = '_sc_entity_aliases';
    public const META_ENTITY_URI = '_sc_entity_external_uri';
    public const META_ENTITY_VOCABULARY_ID = '_sc_entity_vocabulary_id';

    public const META_VOCABULARY_PREFIX = '_sc_vocabulary_prefix';
    public const META_VOCABULARY_URI = '_sc_vocabulary_uri';
    public const META_VOCABULARY_VERSION = '_sc_vocabulary_version';
    public const META_VOCABULARY_LICENSE = '_sc_vocabulary_license';
    public const META_VOCABULARY_LANGUAGE = '_sc_vocabulary_language';
    public const META_VOCABULARY_AUTHORITY = '_sc_vocabulary_authority';

    public const META_TOPIC_ALT_LABELS = '_sc_topic_alt_labels';
    public const META_TOPIC_SCOPE_NOTE = '_sc_topic_scope_note';
    public const META_TOPIC_URI = '_sc_topic_external_uri';
    public const META_TOPIC_VOCABULARY_ID = '_sc_topic_vocabulary_id';
    public const META_TOPIC_STATUS = '_sc_topic_status';
    public const META_TOPIC_LEGACY_TERM_ID = '_sc_topic_legacy_source_term_id';

    public const META_RELATION_FROM_KIND = '_sc_relation_from_kind';
    public const META_RELATION_FROM_ID = '_sc_relation_from_id';
    public const META_RELATION_TO_KIND = '_sc_relation_to_kind';
    public const META_RELATION_TO_ID = '_sc_relation_to_id';
    public const META_RELATION_TYPE = '_sc_relation_type';
    public const META_RELATION_NOTE = '_sc_relation_note';
    public const META_RELATION_WEIGHT = '_sc_relation_weight';
    public const META_RELATION_PUBLIC = '_sc_relation_public';
    public const META_RELATION_STATUS = '_sc_relation_status';
    public const META_RELATION_CREATED_AT = '_sc_relation_created_at';
    public const META_RELATION_CREATED_BY = '_sc_relation_created_by';
    public const META_RELATION_UPDATED_AT = '_sc_relation_updated_at';
    public const META_RELATION_UPDATED_BY = '_sc_relation_updated_by';

    public const OPTION_MIGRATION_STATE = 'sc_library_v320_topic_migration_state';
    public const OPTION_ROUTES_VERSION = 'sc_library_v320_routes_version';
    public const TRANSIENT_PUBLIC_COVERAGE = 'sc_library_v320_public_coverage';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v320_topic_migration_lock';
    public const CRON_HOOK = 'sc_library_v320_topic_migration_tick';
    public const MIGRATION_BATCH = 25;
    public const LOCK_SECONDS = 180;
    public const MAX_RELATIONS_PER_NODE = 200;
    public const MAX_ASSIGNMENTS = 200;
    public const COVERAGE_CACHE_SECONDS = 600;

    private static $saving = false;
    private static $deleting_relation = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ), 260 );
        add_action( 'init', array( $this, 'schedule_migration' ), 982 );
        add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_migration' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 258 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 140 );
        add_action( 'save_post', array( $this, 'save_node_metadata' ), 140, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_node' ), 140 );
        add_action( 'deleted_post', array( $this, 'invalidate_deleted_post' ), 200 );
        add_action( 'set_object_terms', array( $this, 'invalidate_topic_assignment_cache' ), 200, 6 );
        add_action( 'transition_post_status', array( $this, 'invalidate_status_cache' ), 200, 3 );

        add_action( self::TOPIC_TAXONOMY . '_add_form_fields', array( $this, 'topic_add_fields' ) );
        add_action( self::TOPIC_TAXONOMY . '_edit_form_fields', array( $this, 'topic_edit_fields' ) );
        add_action( 'created_' . self::TOPIC_TAXONOMY, array( $this, 'save_topic_fields' ) );
        add_action( 'edited_' . self::TOPIC_TAXONOMY, array( $this, 'save_topic_fields' ) );
        add_action( 'delete_' . self::TOPIC_TAXONOMY, array( $this, 'cleanup_deleted_topic' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 140 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
        add_filter( 'template_include', array( $this, 'template_include' ), 130 );

        add_filter( 'sc_library_source_data', array( $this, 'filter_source_data' ), 40, 3 );
        add_filter( 'sc_library_claim_data', array( $this, 'filter_claim_data' ), 40, 3 );

        add_shortcode( 'sc_knowledge_relationship_browser', array( $this, 'shortcode_relationship_browser' ) );
        add_shortcode( 'sc_topic_coverage', array( $this, 'shortcode_topic_coverage' ) );
        add_shortcode( 'sc_knowledge_concept', array( $this, 'shortcode_concept' ) );

        add_action( 'wp_ajax_sc_library_v320_browse_node', array( $this, 'ajax_browse_node' ) );
        add_action( 'wp_ajax_nopriv_sc_library_v320_browse_node', array( $this, 'ajax_browse_node' ) );
        add_action( 'wp_ajax_sc_library_v320_run_topic_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_sc_library_v320_reset_topic_migration', array( $this, 'ajax_reset_migration' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 35, 3 );

        add_filter( 'manage_' . self::CONCEPT_POST_TYPE . '_posts_columns', array( $this, 'concept_columns' ) );
        add_action( 'manage_' . self::CONCEPT_POST_TYPE . '_posts_custom_column', array( $this, 'concept_column_content' ), 10, 2 );
        add_filter( 'manage_' . self::ENTITY_POST_TYPE . '_posts_columns', array( $this, 'entity_columns' ) );
        add_action( 'manage_' . self::ENTITY_POST_TYPE . '_posts_custom_column', array( $this, 'entity_column_content' ), 10, 2 );

        self::register_cli_commands();
    }

    public function register_content_types() {
        register_post_type(
            self::CONCEPT_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Concepts', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Concept', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Concepts', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Concept', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Concept', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Concepts', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No concepts found.', 'sustainable-catalyst-library' ),
                    'not_found_in_trash' => __( 'No concepts found in Trash.', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => true,
                'exclude_from_search' => false,
                'has_archive'         => 'concepts',
                'rewrite'             => array( 'slug' => 'concepts', 'with_front' => false ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-lightbulb',
                'can_export'          => true,
            )
        );

        register_post_type(
            self::ENTITY_POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Named Entities', 'sustainable-catalyst-library' ),
                    'singular_name'      => __( 'Named Entity', 'sustainable-catalyst-library' ),
                    'menu_name'          => __( 'Named Entities', 'sustainable-catalyst-library' ),
                    'add_new_item'       => __( 'Add Named Entity', 'sustainable-catalyst-library' ),
                    'edit_item'          => __( 'Edit Named Entity', 'sustainable-catalyst-library' ),
                    'search_items'       => __( 'Search Named Entities', 'sustainable-catalyst-library' ),
                    'not_found'          => __( 'No named entities found.', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => true,
                'exclude_from_search' => false,
                'has_archive'         => 'entities',
                'rewrite'             => array( 'slug' => 'entities', 'with_front' => false ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-location-alt',
                'can_export'          => true,
            )
        );

        register_post_type(
            self::VOCABULARY_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Controlled Vocabularies', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Controlled Vocabulary', 'sustainable-catalyst-library' ),
                    'menu_name'     => __( 'Vocabularies', 'sustainable-catalyst-library' ),
                    'add_new_item'  => __( 'Add Controlled Vocabulary', 'sustainable-catalyst-library' ),
                    'edit_item'     => __( 'Edit Controlled Vocabulary', 'sustainable-catalyst-library' ),
                ),
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'sc-library',
                'show_in_rest'        => true,
                'exclude_from_search' => true,
                'has_archive'         => false,
                'rewrite'             => array( 'slug' => 'vocabularies', 'with_front' => false ),
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
                'menu_icon'           => 'dashicons-book-alt',
                'can_export'          => true,
            )
        );

        register_post_type(
            self::RELATION_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Knowledge Relationships', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Knowledge Relationship', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'publicly_queryable'  => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title' ),
                'can_export'          => true,
            )
        );

        $topic_objects = array(
            SC_Library_Foundation_Pages::POST_TYPE,
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            self::CONCEPT_POST_TYPE,
            self::ENTITY_POST_TYPE,
        );

        register_taxonomy(
            self::TOPIC_TAXONOMY,
            $topic_objects,
            array(
                'labels' => array(
                    'name'              => __( 'Knowledge Topics', 'sustainable-catalyst-library' ),
                    'singular_name'     => __( 'Knowledge Topic', 'sustainable-catalyst-library' ),
                    'menu_name'         => __( 'Knowledge Topics', 'sustainable-catalyst-library' ),
                    'search_items'      => __( 'Search Knowledge Topics', 'sustainable-catalyst-library' ),
                    'all_items'         => __( 'All Knowledge Topics', 'sustainable-catalyst-library' ),
                    'parent_item'       => __( 'Broader Topic', 'sustainable-catalyst-library' ),
                    'parent_item_colon' => __( 'Broader Topic:', 'sustainable-catalyst-library' ),
                    'edit_item'         => __( 'Edit Knowledge Topic', 'sustainable-catalyst-library' ),
                    'add_new_item'      => __( 'Add Knowledge Topic', 'sustainable-catalyst-library' ),
                ),
                'public'            => true,
                'publicly_queryable'=> true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_menu'      => 'sc-library',
                'show_in_rest'      => true,
                'hierarchical'      => true,
                'rewrite'           => array( 'slug' => 'library/topic', 'with_front' => false, 'hierarchical' => true ),
            )
        );

        // Foundation Pages deliberately unregister other taxonomies at init 100.
        // Reattach only the canonical topic taxonomy after that simplification.
        foreach ( $topic_objects as $post_type ) {
            if ( post_type_exists( $post_type ) ) {
                register_taxonomy_for_object_type( self::TOPIC_TAXONOMY, $post_type );
            }
        }
    }

    public static function concept_type_options() {
        return array(
            'principle'  => __( 'Principle', 'sustainable-catalyst-library' ),
            'theory'     => __( 'Theory or framework', 'sustainable-catalyst-library' ),
            'method'     => __( 'Method', 'sustainable-catalyst-library' ),
            'metric'     => __( 'Metric or indicator', 'sustainable-catalyst-library' ),
            'process'    => __( 'Process', 'sustainable-catalyst-library' ),
            'system'     => __( 'System', 'sustainable-catalyst-library' ),
            'policy'     => __( 'Policy concept', 'sustainable-catalyst-library' ),
            'legal'      => __( 'Legal concept', 'sustainable-catalyst-library' ),
            'technology' => __( 'Technology', 'sustainable-catalyst-library' ),
            'phenomenon' => __( 'Phenomenon', 'sustainable-catalyst-library' ),
            'other'      => __( 'Other', 'sustainable-catalyst-library' ),
        );
    }

    public static function entity_type_options() {
        return array(
            'person'       => __( 'Person', 'sustainable-catalyst-library' ),
            'organization' => __( 'Organization', 'sustainable-catalyst-library' ),
            'place'        => __( 'Place', 'sustainable-catalyst-library' ),
            'jurisdiction' => __( 'Jurisdiction', 'sustainable-catalyst-library' ),
            'instrument'   => __( 'Law, treaty, or instrument', 'sustainable-catalyst-library' ),
            'program'      => __( 'Program or initiative', 'sustainable-catalyst-library' ),
            'product'      => __( 'Product or platform', 'sustainable-catalyst-library' ),
            'event'        => __( 'Event', 'sustainable-catalyst-library' ),
            'dataset'      => __( 'Dataset', 'sustainable-catalyst-library' ),
            'other'        => __( 'Other', 'sustainable-catalyst-library' ),
        );
    }

    public static function status_options() {
        return array(
            'draft'      => __( 'Draft', 'sustainable-catalyst-library' ),
            'active'     => __( 'Active', 'sustainable-catalyst-library' ),
            'deprecated' => __( 'Deprecated', 'sustainable-catalyst-library' ),
            'archived'   => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public static function relation_type_options() {
        return array(
            'related-to'       => __( 'Related to', 'sustainable-catalyst-library' ),
            'contrasts-with'   => __( 'Contrasts with', 'sustainable-catalyst-library' ),
            'broader-than'     => __( 'Broader than', 'sustainable-catalyst-library' ),
            'narrower-than'    => __( 'Narrower than', 'sustainable-catalyst-library' ),
            'defines'          => __( 'Defines', 'sustainable-catalyst-library' ),
            'exemplifies'      => __( 'Exemplifies', 'sustainable-catalyst-library' ),
            'about-topic'      => __( 'About topic', 'sustainable-catalyst-library' ),
            'uses-concept'     => __( 'Uses concept', 'sustainable-catalyst-library' ),
            'mentions-entity'  => __( 'Mentions entity', 'sustainable-catalyst-library' ),
            'governed-by'      => __( 'Governed by vocabulary', 'sustainable-catalyst-library' ),
            'cites'            => __( 'Cites', 'sustainable-catalyst-library' ),
            'supports'         => __( 'Supports', 'sustainable-catalyst-library' ),
            'derives-from'     => __( 'Derived from', 'sustainable-catalyst-library' ),
            'summarizes'       => __( 'Summarizes', 'sustainable-catalyst-library' ),
            'translates'       => __( 'Translates', 'sustainable-catalyst-library' ),
            'supersedes'       => __( 'Supersedes', 'sustainable-catalyst-library' ),
            'precedes'         => __( 'Precedes', 'sustainable-catalyst-library' ),
            'continues'        => __( 'Continues', 'sustainable-catalyst-library' ),
            'contains'         => __( 'Contains or includes', 'sustainable-catalyst-library' ),
            'companion-to'     => __( 'Companion to', 'sustainable-catalyst-library' ),
            'methodology-for'  => __( 'Provides methodology for', 'sustainable-catalyst-library' ),
        );
    }

    public static function node_type_options() {
        return array(
            'document'   => __( 'Knowledge Library document', 'sustainable-catalyst-library' ),
            'source'     => __( 'Research Source', 'sustainable-catalyst-library' ),
            'claim'      => __( 'Research Claim', 'sustainable-catalyst-library' ),
            'evidence'   => __( 'Evidence Note', 'sustainable-catalyst-library' ),
            'project'    => __( 'Research Project', 'sustainable-catalyst-library' ),
            'concept'    => __( 'Concept', 'sustainable-catalyst-library' ),
            'topic'      => __( 'Knowledge Topic', 'sustainable-catalyst-library' ),
            'entity'     => __( 'Named Entity', 'sustainable-catalyst-library' ),
            'vocabulary' => __( 'Controlled Vocabulary', 'sustainable-catalyst-library' ),
        );
    }

    public static function node_post_type( $kind ) {
        $map = array(
            'document'   => SC_Library_Foundation_Pages::POST_TYPE,
            'source'     => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'claim'      => SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            'evidence'   => SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            'project'    => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'concept'    => self::CONCEPT_POST_TYPE,
            'entity'     => self::ENTITY_POST_TYPE,
            'vocabulary' => self::VOCABULARY_POST_TYPE,
        );
        return $map[ sanitize_key( $kind ) ] ?? '';
    }

    public static function post_kind( $post_type ) {
        foreach ( self::node_type_options() as $kind => $label ) {
            if ( self::node_post_type( $kind ) === $post_type ) {
                return $kind;
            }
        }
        return '';
    }

    public static function inverse_relation_label( $relation ) {
        $labels = array(
            'related-to'      => __( 'Related to', 'sustainable-catalyst-library' ),
            'contrasts-with'  => __( 'Contrasts with', 'sustainable-catalyst-library' ),
            'broader-than'    => __( 'Narrower than', 'sustainable-catalyst-library' ),
            'narrower-than'   => __( 'Broader than', 'sustainable-catalyst-library' ),
            'defines'         => __( 'Defined by', 'sustainable-catalyst-library' ),
            'exemplifies'     => __( 'Exemplified by', 'sustainable-catalyst-library' ),
            'about-topic'     => __( 'Topic of', 'sustainable-catalyst-library' ),
            'uses-concept'    => __( 'Concept used by', 'sustainable-catalyst-library' ),
            'mentions-entity' => __( 'Entity mentioned by', 'sustainable-catalyst-library' ),
            'governed-by'     => __( 'Controls', 'sustainable-catalyst-library' ),
            'cites'           => __( 'Cited by', 'sustainable-catalyst-library' ),
            'supports'        => __( 'Supported by', 'sustainable-catalyst-library' ),
            'derives-from'    => __( 'Source for', 'sustainable-catalyst-library' ),
            'summarizes'      => __( 'Summarized by', 'sustainable-catalyst-library' ),
            'translates'      => __( 'Translation of', 'sustainable-catalyst-library' ),
            'supersedes'      => __( 'Superseded by', 'sustainable-catalyst-library' ),
            'precedes'        => __( 'Follows', 'sustainable-catalyst-library' ),
            'continues'       => __( 'Continued by', 'sustainable-catalyst-library' ),
            'contains'        => __( 'Part of', 'sustainable-catalyst-library' ),
            'companion-to'    => __( 'Companion to', 'sustainable-catalyst-library' ),
            'methodology-for' => __( 'Uses methodology from', 'sustainable-catalyst-library' ),
        );
        return $labels[ sanitize_key( $relation ) ] ?? ucfirst( str_replace( '-', ' ', $relation ) );
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Topics and Relationships', 'sustainable-catalyst-library' ),
            __( 'Topics and Relationships', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-topics-relationships',
            array( $this, 'render_workspace' )
        );
    }

    public function register_meta_boxes() {
        $supported = array(
            SC_Library_Foundation_Pages::POST_TYPE,
            SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            self::CONCEPT_POST_TYPE,
            self::ENTITY_POST_TYPE,
        );
        foreach ( $supported as $post_type ) {
            add_meta_box(
                'sc-library-semantic-connections',
                __( 'Topics, Concepts, Entities, and Relationships', 'sustainable-catalyst-library' ),
                array( $this, 'render_semantic_meta_box' ),
                $post_type,
                'normal',
                'default'
            );
        }
        add_meta_box(
            'sc-library-concept-definition',
            __( 'Concept Record', 'sustainable-catalyst-library' ),
            array( $this, 'render_concept_meta_box' ),
            self::CONCEPT_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-library-entity-record',
            __( 'Named Entity Record', 'sustainable-catalyst-library' ),
            array( $this, 'render_entity_meta_box' ),
            self::ENTITY_POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'sc-library-vocabulary-record',
            __( 'Controlled Vocabulary Record', 'sustainable-catalyst-library' ),
            array( $this, 'render_vocabulary_meta_box' ),
            self::VOCABULARY_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_semantic_meta_box( $post ) {
        $kind = self::post_kind( $post->post_type );
        if ( ! $kind ) {
            return;
        }
        wp_nonce_field( 'sc_library_save_semantic_' . $post->ID, 'sc_library_semantic_nonce' );
        $selected_topics = wp_get_object_terms( $post->ID, self::TOPIC_TAXONOMY, array( 'fields' => 'ids' ) );
        $selected_topics = is_wp_error( $selected_topics ) ? array() : array_map( 'absint', $selected_topics );
        $selected_concepts = self::id_list( get_post_meta( $post->ID, self::META_CONCEPT_IDS, true ) );
        $selected_entities = self::id_list( get_post_meta( $post->ID, self::META_ENTITY_IDS, true ) );
        $topics = get_terms( array( 'taxonomy' => self::TOPIC_TAXONOMY, 'hide_empty' => false, 'number' => 500 ) );
        $topics = is_wp_error( $topics ) ? array() : $topics;
        $concepts = get_posts(
            array(
                'post_type'      => self::CONCEPT_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $entities = get_posts(
            array(
                'post_type'      => self::ENTITY_POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $relations = self::relationships_for_node( $kind, $post->ID, true, 'outgoing' );
        ?>
        <div class="sc-semantic-editor" data-sc-semantic-editor data-node-kind="<?php echo esc_attr( $kind ); ?>" data-node-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><?php esc_html_e( 'Use canonical Topics for broad subject organization, Concepts for defined ideas, Named Entities for specific people/organizations/places/instruments, and typed relationships for directional connections.', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-semantic-assignment-grid">
                <label><strong><?php esc_html_e( 'Knowledge Topics', 'sustainable-catalyst-library' ); ?></strong><select name="sc_library_topic_ids[]" multiple size="9"><?php foreach ( $topics as $topic ) : ?><option value="<?php echo esc_attr( $topic->term_id ); ?>" <?php selected( in_array( absint( $topic->term_id ), $selected_topics, true ) ); ?>><?php echo esc_html( str_repeat( '— ', count( get_ancestors( $topic->term_id, self::TOPIC_TAXONOMY, 'taxonomy' ) ) ) . $topic->name ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Concepts', 'sustainable-catalyst-library' ); ?></strong><select name="sc_library_concept_ids[]" multiple size="9"><?php foreach ( $concepts as $concept ) : ?><option value="<?php echo esc_attr( $concept->ID ); ?>" <?php selected( in_array( absint( $concept->ID ), $selected_concepts, true ) ); ?>><?php echo esc_html( $concept->post_title ); ?></option><?php endforeach; ?></select></label>
                <label><strong><?php esc_html_e( 'Named Entities', 'sustainable-catalyst-library' ); ?></strong><select name="sc_library_entity_ids[]" multiple size="9"><?php foreach ( $entities as $entity ) : ?><option value="<?php echo esc_attr( $entity->ID ); ?>" <?php selected( in_array( absint( $entity->ID ), $selected_entities, true ) ); ?>><?php echo esc_html( $entity->post_title ); ?></option><?php endforeach; ?></select></label>
            </div>

            <h3><?php esc_html_e( 'Outgoing typed relationships', 'sustainable-catalyst-library' ); ?></h3>
            <div class="sc-semantic-relation-rows" data-sc-semantic-relation-rows>
                <?php foreach ( $relations as $index => $relation ) : ?>
                    <?php self::render_relation_row( $index, $relation ); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" data-sc-add-semantic-relation><?php esc_html_e( 'Add Relationship', 'sustainable-catalyst-library' ); ?></button>
            <template data-sc-semantic-relation-template><?php self::render_relation_row( '__INDEX__', array() ); ?></template>
        </div>
        <?php
    }

    private static function render_relation_row( $index, $relation ) {
        $to_kind = sanitize_key( $relation['to_kind'] ?? 'document' );
        $relation_type = sanitize_key( $relation['relation'] ?? 'related-to' );
        ?>
        <div class="sc-semantic-relation-row" data-sc-semantic-relation-row>
            <label><span><?php esc_html_e( 'Relationship', 'sustainable-catalyst-library' ); ?></span><select name="sc_library_relations[<?php echo esc_attr( $index ); ?>][relation]" data-name="relation"><?php foreach ( self::relation_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $relation_type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e( 'Target type', 'sustainable-catalyst-library' ); ?></span><select name="sc_library_relations[<?php echo esc_attr( $index ); ?>][to_kind]" data-name="to_kind"><?php foreach ( self::node_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $to_kind, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e( 'Target ID', 'sustainable-catalyst-library' ); ?></span><input type="number" min="1" name="sc_library_relations[<?php echo esc_attr( $index ); ?>][to_id]" data-name="to_id" value="<?php echo esc_attr( absint( $relation['to_id'] ?? 0 ) ); ?>"></label>
            <label><span><?php esc_html_e( 'Strength', 'sustainable-catalyst-library' ); ?></span><input type="number" min="1" max="5" name="sc_library_relations[<?php echo esc_attr( $index ); ?>][weight]" data-name="weight" value="<?php echo esc_attr( max( 1, min( 5, absint( $relation['weight'] ?? 3 ) ) ) ); ?>"></label>
            <label class="sc-semantic-relation-note"><span><?php esc_html_e( 'Relationship note', 'sustainable-catalyst-library' ); ?></span><input type="text" name="sc_library_relations[<?php echo esc_attr( $index ); ?>][note]" data-name="note" value="<?php echo esc_attr( $relation['note'] ?? '' ); ?>"></label>
            <label class="sc-semantic-relation-public"><input type="checkbox" name="sc_library_relations[<?php echo esc_attr( $index ); ?>][public]" data-name="public" value="1" <?php checked( ! empty( $relation['public'] ) ); ?>> <?php esc_html_e( 'Public', 'sustainable-catalyst-library' ); ?></label>
            <button type="button" class="button" data-sc-remove-semantic-relation><?php esc_html_e( 'Remove', 'sustainable-catalyst-library' ); ?></button>
            <?php if ( ! empty( $relation['relation_id'] ) ) : ?><input type="hidden" name="sc_library_relations[<?php echo esc_attr( $index ); ?>][relation_id]" data-name="relation_id" value="<?php echo esc_attr( absint( $relation['relation_id'] ) ); ?>"><?php endif; ?>
        </div>
        <?php
    }

    public function render_concept_meta_box( $post ) {
        $vocabularies = self::record_options( self::VOCABULARY_POST_TYPE );
        $type = sanitize_key( get_post_meta( $post->ID, self::META_CONCEPT_TYPE, true ) ?: 'other' );
        $status = sanitize_key( get_post_meta( $post->ID, self::META_CONCEPT_STATUS, true ) ?: 'active' );
        ?>
        <div class="sc-semantic-record-fields">
            <label><strong><?php esc_html_e( 'Concept type', 'sustainable-catalyst-library' ); ?></strong><select name="sc_concept_type"><?php foreach ( self::concept_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'Record status', 'sustainable-catalyst-library' ); ?></strong><select name="sc_concept_status"><?php foreach ( self::status_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'Controlled vocabulary', 'sustainable-catalyst-library' ); ?></strong><select name="sc_concept_vocabulary_id"><option value="0"><?php esc_html_e( 'Local vocabulary', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $vocabularies as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( absint( get_post_meta( $post->ID, self::META_CONCEPT_VOCABULARY_ID, true ) ), $id ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'External URI', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_concept_uri" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_CONCEPT_URI, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Alternative labels', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_concept_alt_labels" rows="4" placeholder="One label per line"><?php echo esc_textarea( implode( "\n", self::string_list( get_post_meta( $post->ID, self::META_CONCEPT_ALT_LABELS, true ) ) ) ); ?></textarea></label>
            <label><strong><?php esc_html_e( 'Scope note', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_concept_scope_note" rows="4"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_CONCEPT_SCOPE_NOTE, true ) ); ?></textarea></label>
        </div>
        <?php
    }

    public function render_entity_meta_box( $post ) {
        $vocabularies = self::record_options( self::VOCABULARY_POST_TYPE );
        $type = sanitize_key( get_post_meta( $post->ID, self::META_ENTITY_TYPE, true ) ?: 'other' );
        ?>
        <div class="sc-semantic-record-fields">
            <label><strong><?php esc_html_e( 'Entity type', 'sustainable-catalyst-library' ); ?></strong><select name="sc_entity_type"><?php foreach ( self::entity_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'Controlled vocabulary', 'sustainable-catalyst-library' ); ?></strong><select name="sc_entity_vocabulary_id"><option value="0"><?php esc_html_e( 'Local authority record', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $vocabularies as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( absint( get_post_meta( $post->ID, self::META_ENTITY_VOCABULARY_ID, true ) ), $id ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></label>
            <label><strong><?php esc_html_e( 'Canonical URI', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_entity_uri" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_ENTITY_URI, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Aliases', 'sustainable-catalyst-library' ); ?></strong><textarea name="sc_entity_aliases" rows="4" placeholder="One alias per line"><?php echo esc_textarea( implode( "\n", self::string_list( get_post_meta( $post->ID, self::META_ENTITY_ALIASES, true ) ) ) ); ?></textarea></label>
        </div>
        <?php
    }

    public function render_vocabulary_meta_box( $post ) {
        wp_nonce_field( 'sc_library_save_vocabulary_' . $post->ID, 'sc_library_vocabulary_nonce' );
        ?>
        <div class="sc-semantic-record-fields sc-semantic-record-fields--wide">
            <label><strong><?php esc_html_e( 'Prefix', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_vocabulary_prefix" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_PREFIX, true ) ); ?>" placeholder="skos"></label>
            <label><strong><?php esc_html_e( 'Canonical URI', 'sustainable-catalyst-library' ); ?></strong><input type="url" name="sc_vocabulary_uri" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_URI, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_vocabulary_version" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_VERSION, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'License', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_vocabulary_license" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_LICENSE, true ) ); ?>"></label>
            <label><strong><?php esc_html_e( 'Language', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_vocabulary_language" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_LANGUAGE, true ) ); ?>" placeholder="en"></label>
            <label><strong><?php esc_html_e( 'Authority or steward', 'sustainable-catalyst-library' ); ?></strong><input type="text" name="sc_vocabulary_authority" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_VOCABULARY_AUTHORITY, true ) ); ?>"></label>
        </div>
        <?php
    }

    public function save_node_metadata( $post_id, $post, $update ) {
        if (
            self::$saving
            || ! $post instanceof WP_Post
            || wp_is_post_revision( $post_id )
            || wp_is_post_autosave( $post_id )
            || self::RELATION_POST_TYPE === $post->post_type
        ) {
            return;
        }

        $kind = self::post_kind( $post->post_type );
        if ( ! $kind || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::$saving = true;

        $has_semantic_nonce = isset( $_POST['sc_library_semantic_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_semantic_nonce'] ) ),
                'sc_library_save_semantic_' . $post_id
            );

        if ( $has_semantic_nonce ) {
            $topic_ids = self::validated_term_ids( wp_unslash( $_POST['sc_library_topic_ids'] ?? array() ) );
            wp_set_object_terms( $post_id, $topic_ids, self::TOPIC_TAXONOMY, false );

            $concept_ids = self::validated_post_ids( wp_unslash( $_POST['sc_library_concept_ids'] ?? array() ), self::CONCEPT_POST_TYPE );
            $entity_ids = self::validated_post_ids( wp_unslash( $_POST['sc_library_entity_ids'] ?? array() ), self::ENTITY_POST_TYPE );
            self::update_id_meta( $post_id, self::META_CONCEPT_IDS, $concept_ids );
            self::update_id_meta( $post_id, self::META_ENTITY_IDS, $entity_ids );

            self::replace_outgoing_relationships(
                $kind,
                $post_id,
                wp_unslash( $_POST['sc_library_relations'] ?? array() )
            );
        }

        if ( self::CONCEPT_POST_TYPE === $post->post_type && $has_semantic_nonce ) {
            self::save_concept_record( $post_id );
        } elseif ( self::ENTITY_POST_TYPE === $post->post_type && $has_semantic_nonce ) {
            self::save_entity_record( $post_id );
        } elseif (
            self::VOCABULARY_POST_TYPE === $post->post_type
            && isset( $_POST['sc_library_vocabulary_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sc_library_vocabulary_nonce'] ) ),
                'sc_library_save_vocabulary_' . $post_id
            )
        ) {
            self::save_vocabulary_record( $post_id );
        }

        self::invalidate_coverage_cache();
        self::$saving = false;
    }

    private static function save_concept_record( $post_id ) {
        if ( ! isset( $_POST['sc_concept_type'] ) && ! isset( $_POST['sc_concept_status'] ) ) {
            return;
        }
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_TYPE,
            self::allowed_value( wp_unslash( $_POST['sc_concept_type'] ?? 'other' ), self::concept_type_options(), 'other' )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_STATUS,
            self::allowed_value( wp_unslash( $_POST['sc_concept_status'] ?? 'active' ), self::status_options(), 'active' )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_ALT_LABELS,
            self::string_list( wp_unslash( $_POST['sc_concept_alt_labels'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_SCOPE_NOTE,
            sanitize_textarea_field( wp_unslash( $_POST['sc_concept_scope_note'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_URI,
            esc_url_raw( wp_unslash( $_POST['sc_concept_uri'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_CONCEPT_VOCABULARY_ID,
            self::valid_related_post_id( wp_unslash( $_POST['sc_concept_vocabulary_id'] ?? 0 ), self::VOCABULARY_POST_TYPE )
        );
    }

    private static function save_entity_record( $post_id ) {
        if ( ! isset( $_POST['sc_entity_type'] ) ) {
            return;
        }
        self::update_or_delete_meta(
            $post_id,
            self::META_ENTITY_TYPE,
            self::allowed_value( wp_unslash( $_POST['sc_entity_type'] ?? 'other' ), self::entity_type_options(), 'other' )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_ENTITY_ALIASES,
            self::string_list( wp_unslash( $_POST['sc_entity_aliases'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_ENTITY_URI,
            esc_url_raw( wp_unslash( $_POST['sc_entity_uri'] ?? '' ) )
        );
        self::update_or_delete_meta(
            $post_id,
            self::META_ENTITY_VOCABULARY_ID,
            self::valid_related_post_id( wp_unslash( $_POST['sc_entity_vocabulary_id'] ?? 0 ), self::VOCABULARY_POST_TYPE )
        );
    }

    private static function save_vocabulary_record( $post_id ) {
        if ( ! isset( $_POST['sc_vocabulary_prefix'] ) && ! isset( $_POST['sc_vocabulary_uri'] ) ) {
            return;
        }
        $fields = array(
            self::META_VOCABULARY_PREFIX    => sanitize_key( wp_unslash( $_POST['sc_vocabulary_prefix'] ?? '' ) ),
            self::META_VOCABULARY_URI       => esc_url_raw( wp_unslash( $_POST['sc_vocabulary_uri'] ?? '' ) ),
            self::META_VOCABULARY_VERSION   => sanitize_text_field( wp_unslash( $_POST['sc_vocabulary_version'] ?? '' ) ),
            self::META_VOCABULARY_LICENSE   => sanitize_text_field( wp_unslash( $_POST['sc_vocabulary_license'] ?? '' ) ),
            self::META_VOCABULARY_LANGUAGE  => sanitize_text_field( wp_unslash( $_POST['sc_vocabulary_language'] ?? '' ) ),
            self::META_VOCABULARY_AUTHORITY => sanitize_text_field( wp_unslash( $_POST['sc_vocabulary_authority'] ?? '' ) ),
        );
        foreach ( $fields as $meta_key => $value ) {
            self::update_or_delete_meta( $post_id, $meta_key, $value );
        }
    }

    public function topic_add_fields() {
        $vocabularies = self::record_options( self::VOCABULARY_POST_TYPE );
        ?>
        <div class="form-field"><label for="sc_topic_alt_labels"><?php esc_html_e( 'Alternative labels', 'sustainable-catalyst-library' ); ?></label><textarea id="sc_topic_alt_labels" name="sc_topic_alt_labels" rows="4"></textarea><p><?php esc_html_e( 'One alternative label per line.', 'sustainable-catalyst-library' ); ?></p></div>
        <div class="form-field"><label for="sc_topic_scope_note"><?php esc_html_e( 'Scope note', 'sustainable-catalyst-library' ); ?></label><textarea id="sc_topic_scope_note" name="sc_topic_scope_note" rows="4"></textarea></div>
        <div class="form-field"><label for="sc_topic_uri"><?php esc_html_e( 'External URI', 'sustainable-catalyst-library' ); ?></label><input id="sc_topic_uri" type="url" name="sc_topic_uri"></div>
        <div class="form-field"><label for="sc_topic_vocabulary_id"><?php esc_html_e( 'Controlled vocabulary', 'sustainable-catalyst-library' ); ?></label><select id="sc_topic_vocabulary_id" name="sc_topic_vocabulary_id"><option value="0"><?php esc_html_e( 'Local topic system', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $vocabularies as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="sc_topic_status"><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></label><select id="sc_topic_status" name="sc_topic_status"><?php foreach ( self::status_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( 'active', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
        <?php
    }

    public function topic_edit_fields( $term ) {
        $vocabularies = self::record_options( self::VOCABULARY_POST_TYPE );
        $status = sanitize_key( get_term_meta( $term->term_id, self::META_TOPIC_STATUS, true ) ?: 'active' );
        ?>
        <tr class="form-field"><th scope="row"><label for="sc_topic_alt_labels"><?php esc_html_e( 'Alternative labels', 'sustainable-catalyst-library' ); ?></label></th><td><textarea id="sc_topic_alt_labels" name="sc_topic_alt_labels" rows="4"><?php echo esc_textarea( implode( "\n", self::string_list( get_term_meta( $term->term_id, self::META_TOPIC_ALT_LABELS, true ) ) ) ); ?></textarea><p class="description"><?php esc_html_e( 'One alternative label per line.', 'sustainable-catalyst-library' ); ?></p></td></tr>
        <tr class="form-field"><th scope="row"><label for="sc_topic_scope_note"><?php esc_html_e( 'Scope note', 'sustainable-catalyst-library' ); ?></label></th><td><textarea id="sc_topic_scope_note" name="sc_topic_scope_note" rows="4"><?php echo esc_textarea( get_term_meta( $term->term_id, self::META_TOPIC_SCOPE_NOTE, true ) ); ?></textarea></td></tr>
        <tr class="form-field"><th scope="row"><label for="sc_topic_uri"><?php esc_html_e( 'External URI', 'sustainable-catalyst-library' ); ?></label></th><td><input id="sc_topic_uri" type="url" name="sc_topic_uri" value="<?php echo esc_attr( get_term_meta( $term->term_id, self::META_TOPIC_URI, true ) ); ?>"></td></tr>
        <tr class="form-field"><th scope="row"><label for="sc_topic_vocabulary_id"><?php esc_html_e( 'Controlled vocabulary', 'sustainable-catalyst-library' ); ?></label></th><td><select id="sc_topic_vocabulary_id" name="sc_topic_vocabulary_id"><option value="0"><?php esc_html_e( 'Local topic system', 'sustainable-catalyst-library' ); ?></option><?php foreach ( $vocabularies as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( absint( get_term_meta( $term->term_id, self::META_TOPIC_VOCABULARY_ID, true ) ), $id ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></td></tr>
        <tr class="form-field"><th scope="row"><label for="sc_topic_status"><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></label></th><td><select id="sc_topic_status" name="sc_topic_status"><?php foreach ( self::status_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
        <?php
    }

    public function save_topic_fields( $term_id ) {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        $fields = array(
            self::META_TOPIC_ALT_LABELS    => self::string_list( wp_unslash( $_POST['sc_topic_alt_labels'] ?? '' ) ),
            self::META_TOPIC_SCOPE_NOTE    => sanitize_textarea_field( wp_unslash( $_POST['sc_topic_scope_note'] ?? '' ) ),
            self::META_TOPIC_URI           => esc_url_raw( wp_unslash( $_POST['sc_topic_uri'] ?? '' ) ),
            self::META_TOPIC_VOCABULARY_ID => self::valid_related_post_id( wp_unslash( $_POST['sc_topic_vocabulary_id'] ?? 0 ), self::VOCABULARY_POST_TYPE ),
            self::META_TOPIC_STATUS        => self::allowed_value( wp_unslash( $_POST['sc_topic_status'] ?? 'active' ), self::status_options(), 'active' ),
        );
        foreach ( $fields as $meta_key => $value ) {
            if ( '' === $value || array() === $value || 0 === $value ) {
                delete_term_meta( $term_id, $meta_key );
            } else {
                update_term_meta( $term_id, $meta_key, $value );
            }
        }
        self::invalidate_coverage_cache();
    }

    private static function replace_outgoing_relationships( $from_kind, $from_id, $raw_rows ) {
        $existing = self::relationships_for_node( $from_kind, $from_id, true, 'outgoing' );
        $existing_map = array();
        foreach ( $existing as $relation ) {
            $existing_map[ absint( $relation['relation_id'] ) ] = $relation;
        }

        $kept_ids = array();
        $seen = array();
        foreach ( array_slice( (array) $raw_rows, 0, self::MAX_RELATIONS_PER_NODE ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $to_kind = self::allowed_value( $row['to_kind'] ?? '', self::node_type_options(), '' );
            $to_id = absint( $row['to_id'] ?? 0 );
            $relation_type = self::allowed_value( $row['relation'] ?? '', self::relation_type_options(), '' );
            if ( ! $to_kind || ! $to_id || ! $relation_type || ! self::node_exists( $to_kind, $to_id ) ) {
                continue;
            }
            if ( $from_kind === $to_kind && absint( $from_id ) === $to_id ) {
                continue;
            }
            $key = $relation_type . '|' . $to_kind . '|' . $to_id;
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;

            $relation_id = absint( $row['relation_id'] ?? 0 );
            if ( ! isset( $existing_map[ $relation_id ] ) ) {
                $relation_id = 0;
            }
            $saved_id = self::save_relationship(
                array(
                    'relation_id' => $relation_id,
                    'from_kind'   => $from_kind,
                    'from_id'     => absint( $from_id ),
                    'to_kind'     => $to_kind,
                    'to_id'       => $to_id,
                    'relation'    => $relation_type,
                    'note'        => sanitize_text_field( $row['note'] ?? '' ),
                    'weight'      => max( 1, min( 5, absint( $row['weight'] ?? 3 ) ) ),
                    'public'      => ! empty( $row['public'] ),
                    'status'      => 'active',
                )
            );
            if ( ! is_wp_error( $saved_id ) ) {
                $kept_ids[] = absint( $saved_id );
            }
        }

        foreach ( array_keys( $existing_map ) as $existing_id ) {
            if ( ! in_array( absint( $existing_id ), $kept_ids, true ) ) {
                wp_delete_post( $existing_id, true );
            }
        }
    }

    public static function save_relationship( $data ) {
        $from_kind = self::allowed_value( $data['from_kind'] ?? '', self::node_type_options(), '' );
        $to_kind = self::allowed_value( $data['to_kind'] ?? '', self::node_type_options(), '' );
        $from_id = absint( $data['from_id'] ?? 0 );
        $to_id = absint( $data['to_id'] ?? 0 );
        $relation = self::allowed_value( $data['relation'] ?? '', self::relation_type_options(), '' );
        if ( ! $from_kind || ! $to_kind || ! $from_id || ! $to_id || ! $relation ) {
            return new WP_Error( 'invalid_knowledge_relationship', __( 'The relationship is incomplete.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        if ( ! self::node_exists( $from_kind, $from_id ) || ! self::node_exists( $to_kind, $to_id ) ) {
            return new WP_Error( 'missing_knowledge_node', __( 'A relationship node does not exist.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( $from_kind === $to_kind && $from_id === $to_id ) {
            return new WP_Error( 'self_knowledge_relationship', __( 'A record cannot relate to itself.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $relation_id = absint( $data['relation_id'] ?? 0 );
        $created_at = $relation_id ? get_post_meta( $relation_id, self::META_RELATION_CREATED_AT, true ) : '';
        $created_by = $relation_id ? absint( get_post_meta( $relation_id, self::META_RELATION_CREATED_BY, true ) ) : 0;
        $title = self::node_label( $from_kind, $from_id ) . ' — ' . ( self::relation_type_options()[ $relation ] ?? $relation ) . ' — ' . self::node_label( $to_kind, $to_id );

        $postarr = array(
            'post_type'   => self::RELATION_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field( $title ),
        );
        if ( $relation_id && self::RELATION_POST_TYPE === get_post_type( $relation_id ) ) {
            $postarr['ID'] = $relation_id;
            $relation_id = wp_update_post( wp_slash( $postarr ), true );
        } else {
            $relation_id = wp_insert_post( wp_slash( $postarr ), true );
        }
        if ( is_wp_error( $relation_id ) ) {
            return $relation_id;
        }

        $meta = array(
            self::META_RELATION_FROM_KIND  => $from_kind,
            self::META_RELATION_FROM_ID    => $from_id,
            self::META_RELATION_TO_KIND    => $to_kind,
            self::META_RELATION_TO_ID      => $to_id,
            self::META_RELATION_TYPE       => $relation,
            self::META_RELATION_NOTE       => sanitize_text_field( $data['note'] ?? '' ),
            self::META_RELATION_WEIGHT     => max( 1, min( 5, absint( $data['weight'] ?? 3 ) ) ),
            self::META_RELATION_PUBLIC     => ! empty( $data['public'] ) ? '1' : '0',
            self::META_RELATION_STATUS     => self::allowed_value( $data['status'] ?? 'active', self::status_options(), 'active' ),
            self::META_RELATION_CREATED_AT => $created_at ?: current_time( 'mysql' ),
            self::META_RELATION_CREATED_BY => $created_by ?: get_current_user_id(),
            self::META_RELATION_UPDATED_AT => current_time( 'mysql' ),
            self::META_RELATION_UPDATED_BY => get_current_user_id(),
        );
        foreach ( $meta as $meta_key => $value ) {
            update_post_meta( $relation_id, $meta_key, $value );
        }
        self::invalidate_coverage_cache();
        return $relation_id;
    }

    public static function relationships_for_node( $kind, $id, $include_private = false, $direction = 'both' ) {
        $kind = sanitize_key( $kind );
        $id = absint( $id );
        if ( ! self::node_exists( $kind, $id ) ) {
            return array();
        }

        $meta_query = array( 'relation' => 'OR' );
        if ( in_array( $direction, array( 'both', 'outgoing' ), true ) ) {
            $meta_query[] = array(
                'relation' => 'AND',
                array( 'key' => self::META_RELATION_FROM_KIND, 'value' => $kind ),
                array( 'key' => self::META_RELATION_FROM_ID, 'value' => $id, 'type' => 'NUMERIC' ),
            );
        }
        if ( in_array( $direction, array( 'both', 'incoming' ), true ) ) {
            $meta_query[] = array(
                'relation' => 'AND',
                array( 'key' => self::META_RELATION_TO_KIND, 'value' => $kind ),
                array( 'key' => self::META_RELATION_TO_ID, 'value' => $id, 'type' => 'NUMERIC' ),
            );
        }

        $ids = get_posts(
            array(
                'post_type'      => self::RELATION_POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => self::MAX_RELATIONS_PER_NODE,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_query'     => $meta_query,
            )
        );

        $relations = array();
        foreach ( $ids as $relation_id ) {
            $record = self::relationship_data( $relation_id, $include_private, $kind, $id );
            if ( $record ) {
                $relations[] = $record;
            }
        }
        return $relations;
    }

    public static function relationship_data( $relation_id, $include_private = false, $perspective_kind = '', $perspective_id = 0 ) {
        if ( self::RELATION_POST_TYPE !== get_post_type( $relation_id ) ) {
            return array();
        }
        $public = '1' === get_post_meta( $relation_id, self::META_RELATION_PUBLIC, true );
        $from_kind = sanitize_key( get_post_meta( $relation_id, self::META_RELATION_FROM_KIND, true ) );
        $from_id = absint( get_post_meta( $relation_id, self::META_RELATION_FROM_ID, true ) );
        $to_kind = sanitize_key( get_post_meta( $relation_id, self::META_RELATION_TO_KIND, true ) );
        $to_id = absint( get_post_meta( $relation_id, self::META_RELATION_TO_ID, true ) );
        if ( ! $include_private && ( ! $public || ! self::node_is_public( $from_kind, $from_id ) || ! self::node_is_public( $to_kind, $to_id ) ) ) {
            return array();
        }
        if ( $include_private && ! current_user_can( 'edit_posts' ) && ! $public ) {
            return array();
        }

        $relation = sanitize_key( get_post_meta( $relation_id, self::META_RELATION_TYPE, true ) );
        $is_incoming = $perspective_kind && $perspective_id && $to_kind === $perspective_kind && $to_id === absint( $perspective_id );
        $other_kind = $is_incoming ? $from_kind : $to_kind;
        $other_id = $is_incoming ? $from_id : $to_id;
        $label = $is_incoming
            ? self::inverse_relation_label( $relation )
            : ( self::relation_type_options()[ $relation ] ?? ucfirst( str_replace( '-', ' ', $relation ) ) );

        return array(
            'schema'       => self::RELATION_SCHEMA,
            'relation_id'  => absint( $relation_id ),
            'from_kind'    => $from_kind,
            'from_id'      => $from_id,
            'from_label'   => self::node_label( $from_kind, $from_id ),
            'from_url'     => self::node_url( $from_kind, $from_id ),
            'to_kind'      => $to_kind,
            'to_id'        => $to_id,
            'to_label'     => self::node_label( $to_kind, $to_id ),
            'to_url'       => self::node_url( $to_kind, $to_id ),
            'relation'     => $relation,
            'label'        => $label,
            'direction'    => $is_incoming ? 'incoming' : 'outgoing',
            'other_kind'   => $other_kind,
            'other_id'     => $other_id,
            'other_label'  => self::node_label( $other_kind, $other_id ),
            'other_url'    => self::node_url( $other_kind, $other_id ),
            'note'         => sanitize_text_field( get_post_meta( $relation_id, self::META_RELATION_NOTE, true ) ),
            'weight'       => max( 1, min( 5, absint( get_post_meta( $relation_id, self::META_RELATION_WEIGHT, true ) ?: 3 ) ) ),
            'public'       => $public,
            'status'       => sanitize_key( get_post_meta( $relation_id, self::META_RELATION_STATUS, true ) ?: 'active' ),
            'created_at'   => $include_private ? sanitize_text_field( get_post_meta( $relation_id, self::META_RELATION_CREATED_AT, true ) ) : '',
            'created_by'   => $include_private ? absint( get_post_meta( $relation_id, self::META_RELATION_CREATED_BY, true ) ) : 0,
            'updated_at'   => sanitize_text_field( get_post_meta( $relation_id, self::META_RELATION_UPDATED_AT, true ) ),
        );
    }

    public static function get_node_data( $kind, $id, $include_private = false ) {
        $kind = sanitize_key( $kind );
        $id = absint( $id );
        if ( ! self::node_exists( $kind, $id ) ) {
            return array();
        }
        if ( ! $include_private && ! self::node_is_public( $kind, $id ) ) {
            return array();
        }
        if ( $include_private && ! self::can_edit_node( $kind, $id ) ) {
            $include_private = false;
            if ( ! self::node_is_public( $kind, $id ) ) {
                return array();
            }
        }

        $topics = array();
        $concept_ids = array();
        $entity_ids = array();
        if ( 'topic' === $kind ) {
            $term = get_term( $id, self::TOPIC_TAXONOMY );
            if ( ! $term || is_wp_error( $term ) ) {
                return array();
            }
            $topics[] = self::topic_data( $term, $include_private );
        } else {
            $topics = self::topics_for_post( $id, $include_private );
            $concept_ids = self::validated_post_ids( get_post_meta( $id, self::META_CONCEPT_IDS, true ), self::CONCEPT_POST_TYPE, $include_private );
            $entity_ids = self::validated_post_ids( get_post_meta( $id, self::META_ENTITY_IDS, true ), self::ENTITY_POST_TYPE, $include_private );
        }

        $concepts = array();
        foreach ( $concept_ids as $concept_id ) {
            $concepts[] = self::concept_data( $concept_id, $include_private );
        }
        $entities = array();
        foreach ( $entity_ids as $entity_id ) {
            $entities[] = self::entity_data( $entity_id, $include_private );
        }

        $data = array(
            'schema'        => self::NODE_SCHEMA,
            'kind'          => $kind,
            'id'            => $id,
            'label'         => self::node_label( $kind, $id ),
            'summary'       => self::node_summary( $kind, $id ),
            'url'           => self::node_url( $kind, $id ),
            'public'        => self::node_is_public( $kind, $id ),
            'topics'        => array_values( array_filter( $topics ) ),
            'concepts'      => array_values( array_filter( $concepts ) ),
            'entities'      => array_values( array_filter( $entities ) ),
            'relationships' => self::relationships_for_node( $kind, $id, $include_private, 'both' ),
        );

        if ( 'concept' === $kind ) {
            $data['record'] = self::concept_data( $id, $include_private );
        } elseif ( 'entity' === $kind ) {
            $data['record'] = self::entity_data( $id, $include_private );
        } elseif ( 'vocabulary' === $kind ) {
            $data['record'] = self::vocabulary_data( $id, $include_private );
        } elseif ( 'topic' === $kind ) {
            $data['record'] = $topics ? $topics[0] : array();
        }
        return $data;
    }

    public static function topics_for_post( $post_id, $include_private = false ) {
        $terms = wp_get_object_terms( $post_id, self::TOPIC_TAXONOMY );
        if ( is_wp_error( $terms ) ) {
            return array();
        }
        $topics = array();
        foreach ( $terms as $term ) {
            $data = self::topic_data( $term, $include_private );
            if ( $data ) {
                $topics[] = $data;
            }
        }
        return $topics;
    }

    public static function topic_data( $term, $include_private = false ) {
        if ( is_numeric( $term ) ) {
            $term = get_term( absint( $term ), self::TOPIC_TAXONOMY );
        }
        if ( ! $term || is_wp_error( $term ) || self::TOPIC_TAXONOMY !== $term->taxonomy ) {
            return array();
        }
        $status = sanitize_key( get_term_meta( $term->term_id, self::META_TOPIC_STATUS, true ) ?: 'active' );
        if ( ! $include_private && 'active' !== $status ) {
            return array();
        }
        $children = get_terms(
            array(
                'taxonomy'   => self::TOPIC_TAXONOMY,
                'hide_empty' => false,
                'parent'     => $term->term_id,
                'fields'     => 'ids',
            )
        );
        $children = is_wp_error( $children ) ? array() : array_map( 'absint', $children );
        return array(
            'kind'          => 'topic',
            'id'            => absint( $term->term_id ),
            'name'          => $term->name,
            'slug'          => $term->slug,
            'description'   => $term->description,
            'parent_id'     => absint( $term->parent ),
            'child_ids'     => $children,
            'alternative_labels' => self::string_list( get_term_meta( $term->term_id, self::META_TOPIC_ALT_LABELS, true ) ),
            'scope_note'    => sanitize_textarea_field( get_term_meta( $term->term_id, self::META_TOPIC_SCOPE_NOTE, true ) ),
            'external_uri'  => esc_url_raw( get_term_meta( $term->term_id, self::META_TOPIC_URI, true ) ),
            'vocabulary_id' => absint( get_term_meta( $term->term_id, self::META_TOPIC_VOCABULARY_ID, true ) ),
            'status'        => $status,
            'url'           => get_term_link( $term ),
        );
    }

    public static function concept_data( $concept_id, $include_private = false ) {
        $post = get_post( $concept_id );
        if ( ! $post || self::CONCEPT_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status ) {
            return array();
        }
        return array(
            'kind'               => 'concept',
            'id'                 => absint( $concept_id ),
            'label'              => $post->post_title,
            'definition'         => wp_strip_all_tags( $post->post_content ),
            'summary'            => wp_strip_all_tags( $post->post_excerpt ),
            'concept_type'       => sanitize_key( get_post_meta( $concept_id, self::META_CONCEPT_TYPE, true ) ?: 'other' ),
            'status'             => sanitize_key( get_post_meta( $concept_id, self::META_CONCEPT_STATUS, true ) ?: 'active' ),
            'alternative_labels' => self::string_list( get_post_meta( $concept_id, self::META_CONCEPT_ALT_LABELS, true ) ),
            'scope_note'         => sanitize_textarea_field( get_post_meta( $concept_id, self::META_CONCEPT_SCOPE_NOTE, true ) ),
            'external_uri'       => esc_url_raw( get_post_meta( $concept_id, self::META_CONCEPT_URI, true ) ),
            'vocabulary_id'      => absint( get_post_meta( $concept_id, self::META_CONCEPT_VOCABULARY_ID, true ) ),
            'url'                => 'publish' === $post->post_status ? get_permalink( $concept_id ) : '',
        );
    }

    public static function entity_data( $entity_id, $include_private = false ) {
        $post = get_post( $entity_id );
        if ( ! $post || self::ENTITY_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status ) {
            return array();
        }
        return array(
            'kind'          => 'entity',
            'id'            => absint( $entity_id ),
            'label'         => $post->post_title,
            'description'   => wp_strip_all_tags( $post->post_content ),
            'summary'       => wp_strip_all_tags( $post->post_excerpt ),
            'entity_type'   => sanitize_key( get_post_meta( $entity_id, self::META_ENTITY_TYPE, true ) ?: 'other' ),
            'aliases'       => self::string_list( get_post_meta( $entity_id, self::META_ENTITY_ALIASES, true ) ),
            'external_uri'  => esc_url_raw( get_post_meta( $entity_id, self::META_ENTITY_URI, true ) ),
            'vocabulary_id' => absint( get_post_meta( $entity_id, self::META_ENTITY_VOCABULARY_ID, true ) ),
            'url'           => 'publish' === $post->post_status ? get_permalink( $entity_id ) : '',
        );
    }

    public static function vocabulary_data( $vocabulary_id, $include_private = false ) {
        $post = get_post( $vocabulary_id );
        if ( ! $post || self::VOCABULARY_POST_TYPE !== $post->post_type ) {
            return array();
        }
        if ( ! $include_private && 'publish' !== $post->post_status ) {
            return array();
        }
        return array(
            'kind'      => 'vocabulary',
            'id'        => absint( $vocabulary_id ),
            'label'     => $post->post_title,
            'summary'   => wp_strip_all_tags( $post->post_excerpt ),
            'description' => wp_strip_all_tags( $post->post_content ),
            'prefix'    => sanitize_key( get_post_meta( $vocabulary_id, self::META_VOCABULARY_PREFIX, true ) ),
            'uri'       => esc_url_raw( get_post_meta( $vocabulary_id, self::META_VOCABULARY_URI, true ) ),
            'version'   => sanitize_text_field( get_post_meta( $vocabulary_id, self::META_VOCABULARY_VERSION, true ) ),
            'license'   => sanitize_text_field( get_post_meta( $vocabulary_id, self::META_VOCABULARY_LICENSE, true ) ),
            'language'  => sanitize_text_field( get_post_meta( $vocabulary_id, self::META_VOCABULARY_LANGUAGE, true ) ),
            'authority' => sanitize_text_field( get_post_meta( $vocabulary_id, self::META_VOCABULARY_AUTHORITY, true ) ),
            'url'       => 'publish' === $post->post_status ? get_permalink( $vocabulary_id ) : '',
        );
    }

    public function filter_source_data( $data, $source_id, $include_private ) {
        if ( is_array( $data ) ) {
            $data['knowledge'] = self::get_node_data( 'source', $source_id, $include_private );
        }
        return $data;
    }

    public function filter_claim_data( $data, $claim_id, $include_private ) {
        if ( is_array( $data ) ) {
            $data['knowledge'] = self::get_node_data( 'claim', $claim_id, $include_private );
        }
        return $data;
    }

    public static function render_public_node_panel( $kind, $id, $compact = false ) {
        $data = self::get_node_data( $kind, $id, false );
        if ( ! $data ) {
            return '';
        }
        $has_content = ! empty( $data['topics'] ) || ! empty( $data['concepts'] ) || ! empty( $data['entities'] ) || ! empty( $data['relationships'] );
        if ( ! $has_content ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-topics-concepts-relationships' );
        ob_start();
        ?>
        <section class="sc-public-knowledge-panel<?php echo $compact ? ' sc-public-knowledge-panel--compact' : ''; ?>" aria-label="<?php esc_attr_e( 'Topics, concepts, entities, and relationships', 'sustainable-catalyst-library' ); ?>">
            <header>
                <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge relationships', 'sustainable-catalyst-library' ); ?></p>
                <h2><?php esc_html_e( 'Topics, concepts, and connections', 'sustainable-catalyst-library' ); ?></h2>
            </header>
            <div class="sc-public-knowledge-panel__grid">
                <?php if ( $data['topics'] ) : ?>
                    <section><h3><?php esc_html_e( 'Topics', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['topics'] as $topic ) : ?><li><?php if ( ! is_wp_error( $topic['url'] ) && $topic['url'] ) : ?><a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a><?php else : ?><?php echo esc_html( $topic['name'] ); ?><?php endif; ?></li><?php endforeach; ?></ul></section>
                <?php endif; ?>
                <?php if ( $data['concepts'] ) : ?>
                    <section><h3><?php esc_html_e( 'Concepts', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['concepts'] as $concept ) : ?><li><?php if ( $concept['url'] ) : ?><a href="<?php echo esc_url( $concept['url'] ); ?>"><?php echo esc_html( $concept['label'] ); ?></a><?php else : ?><?php echo esc_html( $concept['label'] ); ?><?php endif; ?><?php if ( $concept['summary'] ) : ?><span><?php echo esc_html( $concept['summary'] ); ?></span><?php endif; ?></li><?php endforeach; ?></ul></section>
                <?php endif; ?>
                <?php if ( $data['entities'] ) : ?>
                    <section><h3><?php esc_html_e( 'Named entities', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['entities'] as $entity ) : ?><li><?php if ( $entity['url'] ) : ?><a href="<?php echo esc_url( $entity['url'] ); ?>"><?php echo esc_html( $entity['label'] ); ?></a><?php else : ?><?php echo esc_html( $entity['label'] ); ?><?php endif; ?><span><?php echo esc_html( self::entity_type_options()[ $entity['entity_type'] ] ?? ucfirst( $entity['entity_type'] ) ); ?></span></li><?php endforeach; ?></ul></section>
                <?php endif; ?>
                <?php if ( $data['relationships'] ) : ?>
                    <section class="sc-public-knowledge-panel__relationships"><h3><?php esc_html_e( 'Relationships', 'sustainable-catalyst-library' ); ?></h3><ul><?php foreach ( $data['relationships'] as $relation ) : ?><li><strong><?php echo esc_html( $relation['label'] ); ?></strong> <?php if ( $relation['other_url'] ) : ?><a href="<?php echo esc_url( $relation['other_url'] ); ?>"><?php echo esc_html( $relation['other_label'] ); ?></a><?php else : ?><?php echo esc_html( $relation['other_label'] ); ?><?php endif; ?><?php if ( $relation['note'] ) : ?><span><?php echo esc_html( $relation['note'] ); ?></span><?php endif; ?></li><?php endforeach; ?></ul></section>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_public_record( $kind, $id ) {
        $data = self::get_node_data( $kind, $id, false );
        if ( ! $data ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-topics-concepts-relationships' );
        $record = $data['record'] ?? array();
        ob_start();
        ?>
        <main class="sc-public-knowledge-record">
            <article>
                <header class="sc-public-knowledge-record__hero">
                    <p class="sc-connected-kicker"><?php echo esc_html( self::node_type_options()[ $kind ] ?? ucfirst( $kind ) ); ?></p>
                    <h1><?php echo esc_html( $data['label'] ); ?></h1>
                    <?php if ( $data['summary'] ) : ?><p><?php echo esc_html( $data['summary'] ); ?></p><?php endif; ?>
                </header>
                <?php if ( ! empty( $record['definition'] ) || ! empty( $record['description'] ) ) : ?><section class="sc-public-knowledge-record__body"><h2><?php esc_html_e( 'Definition and description', 'sustainable-catalyst-library' ); ?></h2><p><?php echo esc_html( $record['definition'] ?? $record['description'] ); ?></p></section><?php endif; ?>
                <?php if ( ! empty( $record['scope_note'] ) ) : ?><section class="sc-public-knowledge-record__body"><h2><?php esc_html_e( 'Scope note', 'sustainable-catalyst-library' ); ?></h2><p><?php echo esc_html( $record['scope_note'] ); ?></p></section><?php endif; ?>
                <?php echo self::render_public_node_panel( $kind, $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </article>
        </main>
        <?php
        return ob_get_clean();
    }

    public function shortcode_concept( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0, 'slug' => '' ), $atts, 'sc_knowledge_concept' );
        $concept_id = absint( $atts['id'] );
        if ( ! $concept_id && $atts['slug'] ) {
            $post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, self::CONCEPT_POST_TYPE );
            $concept_id = $post ? absint( $post->ID ) : 0;
        }
        return self::render_public_record( 'concept', $concept_id );
    }

    public function shortcode_relationship_browser( $atts ) {
        $atts = shortcode_atts(
            array(
                'kind'  => '',
                'id'    => 0,
                'title' => __( 'Knowledge Relationship Browser', 'sustainable-catalyst-library' ),
            ),
            $atts,
            'sc_knowledge_relationship_browser'
        );
        $kind = sanitize_key( $atts['kind'] );
        $id = absint( $atts['id'] );
        wp_enqueue_style( 'sc-library-topics-concepts-relationships' );
        wp_enqueue_script( 'sc-library-topics-concepts-relationships' );
        ob_start();
        ?>
        <section class="sc-knowledge-browser" data-sc-knowledge-browser data-public="1">
            <header><p class="sc-connected-kicker"><?php esc_html_e( 'Structured discovery', 'sustainable-catalyst-library' ); ?></p><h2><?php echo esc_html( $atts['title'] ); ?></h2><p><?php esc_html_e( 'Browse typed relationships among documents, Sources, Claims, Concepts, Topics, Named Entities, and Research Projects.', 'sustainable-catalyst-library' ); ?></p></header>
            <form data-sc-knowledge-browser-form>
                <label><span><?php esc_html_e( 'Node type', 'sustainable-catalyst-library' ); ?></span><select data-sc-browser-kind><?php foreach ( self::node_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $kind, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e( 'Record ID', 'sustainable-catalyst-library' ); ?></span><input type="number" min="1" data-sc-browser-id value="<?php echo esc_attr( $id ); ?>"></label>
                <button type="submit"><?php esc_html_e( 'Browse Relationships', 'sustainable-catalyst-library' ); ?></button>
            </form>
            <div data-sc-knowledge-browser-results aria-live="polite"><?php if ( $kind && $id ) : ?><?php echo self::render_browser_result( $kind, $id, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?></div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_topic_coverage( $atts ) {
        $atts = shortcode_atts( array( 'project' => 0, 'limit' => 20 ), $atts, 'sc_topic_coverage' );
        $project_id = absint( $atts['project'] );
        $report = $project_id ? self::project_coverage_report( $project_id, false ) : self::coverage_report( false );
        if ( ! $report ) {
            return '';
        }
        return self::render_coverage_report( $report, max( 1, min( 100, absint( $atts['limit'] ) ) ) );
    }

    private static function render_browser_result( $kind, $id, $include_private ) {
        $data = self::get_node_data( $kind, $id, $include_private );
        if ( ! $data ) {
            return '<p class="sc-knowledge-browser__empty">' . esc_html__( 'The requested record was not found or is not available.', 'sustainable-catalyst-library' ) . '</p>';
        }
        ob_start();
        ?>
        <article class="sc-knowledge-browser__result">
            <header><span><?php echo esc_html( self::node_type_options()[ $kind ] ?? ucfirst( $kind ) ); ?></span><h3><?php echo esc_html( $data['label'] ); ?></h3><?php if ( $data['summary'] ) : ?><p><?php echo esc_html( $data['summary'] ); ?></p><?php endif; ?></header>
            <div class="sc-knowledge-browser__metrics"><span><?php echo esc_html( count( $data['topics'] ) ); ?> <?php esc_html_e( 'topics', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( count( $data['concepts'] ) ); ?> <?php esc_html_e( 'concepts', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( count( $data['entities'] ) ); ?> <?php esc_html_e( 'entities', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( count( $data['relationships'] ) ); ?> <?php esc_html_e( 'relationships', 'sustainable-catalyst-library' ); ?></span></div>
            <?php if ( $data['relationships'] ) : ?><div class="sc-knowledge-browser__relations"><?php foreach ( $data['relationships'] as $relation ) : ?><article><strong><?php echo esc_html( $relation['label'] ); ?></strong><h4><?php echo esc_html( $relation['other_label'] ); ?></h4><span><?php echo esc_html( self::node_type_options()[ $relation['other_kind'] ] ?? $relation['other_kind'] ); ?> · <?php echo esc_html( $relation['weight'] ); ?>/5</span><?php if ( $relation['note'] ) : ?><p><?php echo esc_html( $relation['note'] ); ?></p><?php endif; ?><?php if ( $relation['other_url'] ) : ?><a href="<?php echo esc_url( $relation['other_url'] ); ?>"><?php esc_html_e( 'Open record', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function coverage_report( $include_private = false ) {
        $can_include_private = $include_private && current_user_can( 'edit_posts' );
        if ( ! $can_include_private ) {
            $cached = get_transient( self::TRANSIENT_PUBLIC_COVERAGE );
            if ( is_array( $cached ) && self::COVERAGE_SCHEMA === ( $cached['schema'] ?? '' ) ) {
                return $cached;
            }
        }
        $post_status = $can_include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish';
        $topics = get_terms( array( 'taxonomy' => self::TOPIC_TAXONOMY, 'hide_empty' => false ) );
        $topics = is_wp_error( $topics ) ? array() : $topics;
        $topic_rows = array();
        foreach ( $topics as $topic ) {
            $counts = self::topic_counts( $topic->term_id, $post_status );
            $gaps = array();
            if ( 0 === $counts['documents'] ) {
                $gaps[] = 'no-documents';
            }
            if ( 0 === $counts['sources'] ) {
                $gaps[] = 'no-sources';
            }
            if ( 0 === $counts['claims'] ) {
                $gaps[] = 'no-claims';
            }
            if ( 0 === $counts['projects'] ) {
                $gaps[] = 'no-projects';
            }
            $topic_rows[] = array(
                'topic'  => self::topic_data( $topic, $include_private ),
                'counts' => $counts,
                'gaps'   => $gaps,
                'score'  => min( 100, ( $counts['documents'] ? 25 : 0 ) + ( $counts['sources'] ? 25 : 0 ) + ( $counts['claims'] ? 25 : 0 ) + ( $counts['projects'] ? 25 : 0 ) ),
            );
        }
        usort( $topic_rows, static function ( $left, $right ) { return $left['score'] <=> $right['score']; } );

        $concept_rows = array();
        $concept_ids = get_posts(
            array(
                'post_type'      => self::CONCEPT_POST_TYPE,
                'post_status'    => $post_status,
                'posts_per_page' => 500,
                'fields'         => 'ids',
            )
        );
        foreach ( $concept_ids as $concept_id ) {
            $counts = self::concept_counts( $concept_id, $post_status );
            $gaps = array();
            if ( 0 === $counts['claims'] ) {
                $gaps[] = 'no-claims';
            }
            if ( 0 === $counts['documents'] && 0 === $counts['sources'] ) {
                $gaps[] = 'no-evidence-base';
            }
            $concept_rows[] = array(
                'concept' => self::concept_data( $concept_id, $include_private ),
                'counts'  => $counts,
                'gaps'    => $gaps,
            );
        }

        $report = array(
            'schema'         => self::COVERAGE_SCHEMA,
            'scope'          => 'library',
            'topics'         => $topic_rows,
            'concepts'       => $concept_rows,
            'topic_count'    => count( $topic_rows ),
            'concept_count'  => count( $concept_rows ),
            'gap_count'      => count( array_filter( $topic_rows, static function ( $row ) { return ! empty( $row['gaps'] ); } ) ) + count( array_filter( $concept_rows, static function ( $row ) { return ! empty( $row['gaps'] ); } ) ),
            'legacy_sources' => self::legacy_source_gap_count( $post_status ),
            'generated_at'   => current_time( 'mysql' ),
        );
        if ( ! $can_include_private ) {
            set_transient( self::TRANSIENT_PUBLIC_COVERAGE, $report, self::COVERAGE_CACHE_SECONDS );
        }
        return $report;
    }

    public static function project_coverage_report( $project_id, $include_private = false ) {
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return array();
        }
        if ( ! $include_private && ! self::node_is_public( 'project', $project_id ) ) {
            return array();
        }
        $source_ids = self::id_list( get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_SOURCE_IDS, true ) );
        $project_data = class_exists( 'SC_Library_Connected_Research_Environment' )
            ? SC_Library_Connected_Research_Environment::project_data( $project_id, $include_private )
            : array();
        $document_ids = self::id_list( $project_data['document_ids'] ?? array() );
        $claim_ids = class_exists( 'SC_Library_Evidence_Claim_Linking' )
            ? SC_Library_Evidence_Claim_Linking::claim_ids_for_project( $project_id, $include_private )
            : array();
        $node_ids = array(
            'project'  => array( $project_id ),
            'source'   => $source_ids,
            'document' => $document_ids,
            'claim'    => $claim_ids,
        );
        $topic_map = array();
        $concept_map = array();
        $gaps = array();
        foreach ( $node_ids as $kind => $ids ) {
            foreach ( $ids as $id ) {
                $node = self::get_node_data( $kind, $id, $include_private );
                if ( ! $node ) {
                    continue;
                }
                if ( ! $node['topics'] ) {
                    $gaps[] = array( 'kind' => $kind, 'id' => $id, 'gap' => 'no-topics' );
                }
                if ( in_array( $kind, array( 'claim', 'document', 'source' ), true ) && ! $node['concepts'] ) {
                    $gaps[] = array( 'kind' => $kind, 'id' => $id, 'gap' => 'no-concepts' );
                }
                foreach ( $node['topics'] as $topic ) {
                    $topic_map[ $topic['id'] ] = $topic;
                }
                foreach ( $node['concepts'] as $concept ) {
                    $concept_map[ $concept['id'] ] = $concept;
                }
            }
        }
        return array(
            'schema'        => self::COVERAGE_SCHEMA,
            'scope'         => 'project',
            'project_id'    => absint( $project_id ),
            'topics'        => array_values( $topic_map ),
            'concepts'      => array_values( $concept_map ),
            'gaps'          => $gaps,
            'source_count'  => count( $source_ids ),
            'document_count'=> count( $document_ids ),
            'claim_count'   => count( $claim_ids ),
            'generated_at'  => current_time( 'mysql' ),
        );
    }

    private static function render_coverage_report( $report, $limit ) {
        wp_enqueue_style( 'sc-library-topics-concepts-relationships' );
        ob_start();
        ?>
        <section class="sc-topic-coverage">
            <header><p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge coverage', 'sustainable-catalyst-library' ); ?></p><h2><?php echo 'project' === $report['scope'] ? esc_html__( 'Project topic and concept coverage', 'sustainable-catalyst-library' ) : esc_html__( 'Library topic and concept coverage', 'sustainable-catalyst-library' ); ?></h2></header>
            <?php if ( 'library' === $report['scope'] ) : ?>
                <div class="sc-topic-coverage__metrics"><article><strong><?php echo esc_html( $report['topic_count'] ); ?></strong><span><?php esc_html_e( 'topics', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $report['concept_count'] ); ?></strong><span><?php esc_html_e( 'concepts', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $report['gap_count'] ); ?></strong><span><?php esc_html_e( 'coverage gaps', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( $report['legacy_sources'] ); ?></strong><span><?php esc_html_e( 'legacy-topic Sources', 'sustainable-catalyst-library' ); ?></span></article></div>
                <div class="sc-topic-coverage__rows"><?php foreach ( array_slice( $report['topics'], 0, $limit ) as $row ) : ?><article><h3><?php echo esc_html( $row['topic']['name'] ); ?></h3><div><span><?php echo esc_html( $row['counts']['documents'] ); ?> <?php esc_html_e( 'documents', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( $row['counts']['sources'] ); ?> <?php esc_html_e( 'Sources', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( $row['counts']['claims'] ); ?> <?php esc_html_e( 'Claims', 'sustainable-catalyst-library' ); ?></span><span><?php echo esc_html( $row['counts']['projects'] ); ?> <?php esc_html_e( 'projects', 'sustainable-catalyst-library' ); ?></span></div><?php if ( $row['gaps'] ) : ?><p><?php echo esc_html( implode( ', ', array_map( static function ( $gap ) { return str_replace( '-', ' ', $gap ); }, $row['gaps'] ) ) ); ?></p><?php endif; ?></article><?php endforeach; ?></div>
            <?php else : ?>
                <div class="sc-topic-coverage__metrics"><article><strong><?php echo esc_html( count( $report['topics'] ) ); ?></strong><span><?php esc_html_e( 'topics', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( count( $report['concepts'] ) ); ?></strong><span><?php esc_html_e( 'concepts', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( count( $report['gaps'] ) ); ?></strong><span><?php esc_html_e( 'unclassified records', 'sustainable-catalyst-library' ); ?></span></article></div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function topic_counts( $term_id, $post_status ) {
        $counts = array(
            'documents' => 0,
            'sources'   => 0,
            'claims'    => 0,
            'evidence'  => 0,
            'projects'  => 0,
            'concepts'  => 0,
            'entities'  => 0,
        );
        $map = array(
            'documents' => SC_Library_Foundation_Pages::POST_TYPE,
            'sources'   => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'claims'    => SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            'evidence'  => SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            'projects'  => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'concepts'  => self::CONCEPT_POST_TYPE,
            'entities'  => self::ENTITY_POST_TYPE,
        );
        foreach ( $map as $key => $post_type ) {
            $query = new WP_Query(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => $post_status,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                    'tax_query'      => array(
                        array(
                            'taxonomy'         => self::TOPIC_TAXONOMY,
                            'field'            => 'term_id',
                            'terms'            => array( absint( $term_id ) ),
                            'include_children' => true,
                        ),
                    ),
                )
            );
            $counts[ $key ] = absint( $query->found_posts );
        }
        return $counts;
    }

    private static function concept_counts( $concept_id, $post_status ) {
        $counts = array(
            'documents' => 0,
            'sources'   => 0,
            'claims'    => 0,
            'evidence'  => 0,
            'projects'  => 0,
        );
        $map = array(
            'documents' => SC_Library_Foundation_Pages::POST_TYPE,
            'sources'   => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
            'claims'    => SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            'evidence'  => SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            'projects'  => SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
        );
        foreach ( $map as $key => $post_type ) {
            $query = new WP_Query(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => $post_status,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                    'meta_query'     => array(
                        array(
                            'key'     => self::META_CONCEPT_IDS,
                            'value'   => 'i:' . absint( $concept_id ) . ';',
                            'compare' => 'LIKE',
                        ),
                    ),
                )
            );
            $counts[ $key ] = absint( $query->found_posts );
        }
        return $counts;
    }

    private static function legacy_source_gap_count( $post_status ) {
        $source_ids = get_posts(
            array(
                'post_type'      => SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                'post_status'    => $post_status,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'tax_query'      => array(
                    array(
                        'taxonomy' => SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY,
                        'operator' => 'EXISTS',
                    ),
                ),
            )
        );
        $count = 0;
        foreach ( $source_ids as $source_id ) {
            $canonical = wp_get_object_terms( $source_id, self::TOPIC_TAXONOMY, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $canonical ) || ! $canonical ) {
                $count++;
            }
        }
        return $count;
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $migration = self::migration_state();
        $coverage = self::coverage_report( true );
        $concept_count = wp_count_posts( self::CONCEPT_POST_TYPE );
        $entity_count = wp_count_posts( self::ENTITY_POST_TYPE );
        $relation_count = wp_count_posts( self::RELATION_POST_TYPE );
        ?>
        <div class="wrap sc-knowledge-workspace">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge Library v3.2.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Topics, Concepts, and Document Relationships', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Organize the Library through a canonical topic system, defined concepts, named entities, controlled vocabularies, typed document relationships, and inspectable coverage analysis.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-knowledge-workspace__metrics">
                <article><strong><?php echo esc_html( $coverage['topic_count'] ); ?></strong><span><?php esc_html_e( 'Knowledge Topics', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( absint( $concept_count->publish ?? 0 ) + absint( $concept_count->draft ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Concepts', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( absint( $entity_count->publish ?? 0 ) + absint( $entity_count->draft ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Named Entities', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( absint( $relation_count->publish ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Typed Relationships', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $coverage['gap_count'] ); ?></strong><span><?php esc_html_e( 'Coverage Gaps', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-knowledge-workspace__migration">
                <div><h2><?php esc_html_e( 'Existing topic migration', 'sustainable-catalyst-library' ); ?></h2><p><?php echo esc_html( sprintf( __( '%1$s · %2$d processed · %3$d failures', 'sustainable-catalyst-library' ), ucfirst( $migration['status'] ), $migration['processed'], count( $migration['failures'] ) ) ); ?></p><p><?php esc_html_e( 'Copies retained Source Topics into the canonical Knowledge Topic taxonomy and preserves the old taxonomy for compatibility.', 'sustainable-catalyst-library' ); ?></p></div>
                <div class="sc-knowledge-workspace__actions"><button type="button" class="button button-primary" data-sc-run-topic-migration><?php esc_html_e( 'Run Next Migration Batch', 'sustainable-catalyst-library' ); ?></button><button type="button" class="button" data-sc-reset-topic-migration><?php esc_html_e( 'Reset Migration State', 'sustainable-catalyst-library' ); ?></button></div>
                <div data-sc-topic-migration-status aria-live="polite"></div>
            </section>

            <section class="sc-knowledge-workspace__browser" data-sc-knowledge-browser data-public="0">
                <h2><?php esc_html_e( 'Relationship Browser', 'sustainable-catalyst-library' ); ?></h2>
                <form data-sc-knowledge-browser-form><label><span><?php esc_html_e( 'Node type', 'sustainable-catalyst-library' ); ?></span><select data-sc-browser-kind><?php foreach ( self::node_type_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e( 'Record ID', 'sustainable-catalyst-library' ); ?></span><input type="number" min="1" data-sc-browser-id></label><button type="submit" class="button button-primary"><?php esc_html_e( 'Browse', 'sustainable-catalyst-library' ); ?></button></form>
                <div data-sc-knowledge-browser-results aria-live="polite"></div>
            </section>

            <?php echo self::render_coverage_report( $coverage, 50 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    public function schedule_migration() {
        if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 900, 'hourly', self::CRON_HOOK );
        }
        $state = get_option( self::OPTION_MIGRATION_STATE, array() );
        if ( ! is_array( $state ) || empty( $state['version'] ) ) {
            update_option( self::OPTION_MIGRATION_STATE, self::default_migration_state(), false );
        }
    }

    public function run_scheduled_migration() {
        $state = self::migration_state();
        if ( 'complete' !== $state['status'] ) {
            self::run_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public static function run_migration_batch( $limit = self::MIGRATION_BATCH ) {
        if ( get_transient( self::TRANSIENT_MIGRATION_LOCK ) ) {
            return new WP_Error( 'topic_migration_locked', __( 'Another topic migration batch is running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );
        $limit = max( 1, min( 100, absint( $limit ) ) );

        try {
            if ( 'terms' === $state['step'] ) {
                $state = self::migrate_topic_terms( $state, $limit );
            } elseif ( 'sources' === $state['step'] ) {
                $state = self::migrate_source_assignments( $state, $limit );
            } elseif ( 'documents' === $state['step'] ) {
                $state = self::migrate_document_tags( $state, $limit );
            } else {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
            self::invalidate_coverage_cache();
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['failures'][] = array( 'step' => $state['step'], 'cursor' => $state['cursor'], 'message' => $state['last_error'], 'time' => current_time( 'mysql' ) );
            $state['failures'] = array_slice( $state['failures'], -100 );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error( 'topic_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
        }
        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    private static function migrate_topic_terms( $state, $limit ) {
        $terms = get_terms(
            array(
                'taxonomy'   => SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY,
                'hide_empty' => false,
                'number'     => $limit,
                'offset'     => absint( $state['cursor'] ),
                'orderby'    => 'term_id',
                'order'      => 'ASC',
            )
        );
        if ( is_wp_error( $terms ) ) {
            throw new RuntimeException( $terms->get_error_message() );
        }
        if ( ! $terms ) {
            $state['step'] = 'sources';
            $state['cursor'] = 0;
            $state['status'] = 'pending';
            return $state;
        }
        foreach ( $terms as $legacy ) {
            $existing = get_term_by( 'slug', $legacy->slug, self::TOPIC_TAXONOMY );
            if ( ! $existing ) {
                $inserted = wp_insert_term(
                    $legacy->name,
                    self::TOPIC_TAXONOMY,
                    array(
                        'slug'        => $legacy->slug,
                        'description' => $legacy->description,
                    )
                );
                if ( is_wp_error( $inserted ) ) {
                    $state['failures'][] = array( 'step' => 'terms', 'legacy_term_id' => $legacy->term_id, 'message' => $inserted->get_error_message(), 'time' => current_time( 'mysql' ) );
                    continue;
                }
                $topic_id = absint( $inserted['term_id'] );
            } else {
                $topic_id = absint( $existing->term_id );
            }
            update_term_meta( $topic_id, self::META_TOPIC_LEGACY_TERM_ID, absint( $legacy->term_id ) );
            if ( ! get_term_meta( $topic_id, self::META_TOPIC_STATUS, true ) ) {
                update_term_meta( $topic_id, self::META_TOPIC_STATUS, 'active' );
            }
            $state['processed']++;
        }
        $state['cursor'] += count( $terms );
        $state['status'] = 'pending';
        $state['failures'] = array_slice( $state['failures'], -100 );
        return $state;
    }

    private static function migrate_source_assignments( $state, $limit ) {
        global $wpdb;
        $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
        $params = array_merge(
            array( SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE ),
            $statuses,
            array( absint( $state['cursor'] ), $limit )
        );
        $ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
        if ( ! $ids ) {
            $state['step'] = 'documents';
            $state['cursor'] = 0;
            $state['status'] = 'pending';
            return $state;
        }
        foreach ( $ids as $source_id ) {
            $legacy = wp_get_object_terms( $source_id, SC_Library_Citation_Source_Manager::SOURCE_TOPIC_TAXONOMY, array( 'fields' => 'slugs' ) );
            if ( ! is_wp_error( $legacy ) && $legacy ) {
                wp_set_object_terms( $source_id, array_map( 'sanitize_title', $legacy ), self::TOPIC_TAXONOMY, true );
            }
            $state['cursor'] = $source_id;
            $state['processed']++;
        }
        $state['status'] = 'pending';
        return $state;
    }

    private static function migrate_document_tags( $state, $limit ) {
        global $wpdb;
        $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
        $params = array_merge(
            array( SC_Library_Foundation_Pages::POST_TYPE ),
            $statuses,
            array( absint( $state['cursor'] ), $limit )
        );
        $ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
        if ( ! $ids ) {
            $state['step'] = 'complete';
            $state['status'] = 'complete';
            $state['completed_at'] = current_time( 'mysql' );
            return $state;
        }
        foreach ( $ids as $document_id ) {
            if ( taxonomy_exists( 'post_tag' ) ) {
                $tags = wp_get_object_terms( $document_id, 'post_tag', array( 'fields' => 'names' ) );
                if ( ! is_wp_error( $tags ) && $tags ) {
                    wp_set_object_terms( $document_id, array_map( 'sanitize_text_field', $tags ), self::TOPIC_TAXONOMY, true );
                }
            }
            $state['cursor'] = $document_id;
            $state['processed']++;
        }
        $state['status'] = 'pending';
        return $state;
    }

    public function ajax_browse_node() {
        $kind = sanitize_key( wp_unslash( $_REQUEST['kind'] ?? '' ) );
        $id = absint( wp_unslash( $_REQUEST['id'] ?? 0 ) );
        $include_private = is_user_logged_in() && current_user_can( 'edit_posts' ) && ! empty( $_REQUEST['include_private'] );
        if ( ! $kind || ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Choose a node type and record ID.', 'sustainable-catalyst-library' ) ), 400 );
        }
        $html = self::render_browser_result( $kind, $id, $include_private );
        $data = self::get_node_data( $kind, $id, $include_private );
        if ( ! $data ) {
            wp_send_json_error( array( 'message' => __( 'The record was not found or is not available.', 'sustainable-catalyst-library' ) ), 404 );
        }
        wp_send_json_success( array( 'html' => $html, 'node' => $data ) );
    }

    public function ajax_run_migration() {
        check_ajax_referer( 'sc_library_topics_relationships_v320', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot run topic migration.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::run_migration_batch( self::MIGRATION_BATCH );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'state' => $result ) );
    }

    public function ajax_reset_migration() {
        check_ajax_referer( 'sc_library_topics_relationships_v320', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot reset topic migration.', 'sustainable-catalyst-library' ) ), 403 );
        }
        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        $state = self::default_migration_state();
        update_option( self::OPTION_MIGRATION_STATE, $state, false );
        self::invalidate_coverage_cache();
        wp_send_json_success( array( 'state' => $state ) );
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/nodes/(?P<kind>[a-z-]+)/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_node' ),
                'permission_callback' => array( $this, 'rest_can_read_node' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/relationships',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_relationships' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_relationship' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'edit_posts' );
                    },
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/topics',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_topics' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/concepts',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_concepts' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/entities',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_entities' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/coverage',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_coverage' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/knowledge-coverage',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_coverage' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/knowledge/migration',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_migration_state' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_migration' ),
                    'permission_callback' => static function () {
                        return current_user_can( 'manage_options' );
                    },
                ),
            )
        );
    }

    public function rest_get_node( WP_REST_Request $request ) {
        $kind = sanitize_key( $request['kind'] );
        $id = absint( $request['id'] );
        return rest_ensure_response( self::get_node_data( $kind, $id, self::can_edit_node( $kind, $id ) ) );
    }

    public function rest_relationships( WP_REST_Request $request ) {
        $kind = sanitize_key( $request->get_param( 'kind' ) );
        $id = absint( $request->get_param( 'id' ) );
        $direction = sanitize_key( $request->get_param( 'direction' ) ?: 'both' );
        if ( ! in_array( $direction, array( 'both', 'incoming', 'outgoing' ), true ) ) {
            $direction = 'both';
        }
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        return rest_ensure_response(
            array(
                'schema'        => self::RELATION_SCHEMA,
                'kind'          => $kind,
                'id'            => $id,
                'relationships' => self::relationships_for_node( $kind, $id, $include_private, $direction ),
            )
        );
    }

    public function rest_create_relationship( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $from_kind = sanitize_key( $payload['from_kind'] ?? '' );
        $from_id = absint( $payload['from_id'] ?? 0 );
        if ( ! self::can_edit_node( $from_kind, $from_id ) ) {
            return new WP_Error( 'relationship_forbidden', __( 'You cannot edit the source node.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $result = self::save_relationship( $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( self::relationship_data( $result, true, $from_kind, $from_id ) );
    }

    public function rest_topics( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        $terms = get_terms(
            array(
                'taxonomy'   => self::TOPIC_TAXONOMY,
                'hide_empty' => ! $include_private,
                'number'     => max( 1, min( 500, absint( $request->get_param( 'per_page' ) ?: 100 ) ) ),
                'search'     => sanitize_text_field( $request->get_param( 'search' ) ),
            )
        );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }
        $items = array();
        foreach ( $terms as $term ) {
            $data = self::topic_data( $term, $include_private );
            if ( $data ) {
                $items[] = $data;
            }
        }
        return rest_ensure_response( array( 'schema' => self::NODE_SCHEMA, 'items' => $items ) );
    }

    public function rest_concepts( WP_REST_Request $request ) {
        return rest_ensure_response( self::rest_record_collection( self::CONCEPT_POST_TYPE, 'concept', $request ) );
    }

    public function rest_entities( WP_REST_Request $request ) {
        return rest_ensure_response( self::rest_record_collection( self::ENTITY_POST_TYPE, 'entity', $request ) );
    }

    private static function rest_record_collection( $post_type, $kind, WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        $query = new WP_Query(
            array(
                'post_type'      => $post_type,
                'post_status'    => $include_private ? array( 'publish', 'draft', 'pending', 'private' ) : 'publish',
                'posts_per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
                'paged'          => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
                's'              => sanitize_text_field( $request->get_param( 'search' ) ),
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $items = array();
        foreach ( $query->posts as $post ) {
            $items[] = 'concept' === $kind ? self::concept_data( $post->ID, $include_private ) : self::entity_data( $post->ID, $include_private );
        }
        return array(
            'schema' => self::NODE_SCHEMA,
            'items'  => array_values( array_filter( $items ) ),
            'total'  => absint( $query->found_posts ),
            'pages'  => absint( $query->max_num_pages ),
        );
    }

    public function rest_coverage( WP_REST_Request $request ) {
        $include_private = current_user_can( 'edit_posts' ) && rest_sanitize_boolean( $request->get_param( 'include_private' ) );
        return rest_ensure_response( self::coverage_report( $include_private ) );
    }

    public function rest_project_coverage( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return rest_ensure_response( self::project_coverage_report( $project_id, current_user_can( 'edit_post', $project_id ) ) );
    }

    public function rest_migration_state() {
        return rest_ensure_response( self::migration_state() );
    }

    public function rest_run_migration( WP_REST_Request $request ) {
        $result = self::run_migration_batch( max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) ) ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_node( WP_REST_Request $request ) {
        $kind = sanitize_key( $request['kind'] );
        $id = absint( $request['id'] );
        return self::can_edit_node( $kind, $id ) || self::node_is_public( $kind, $id );
    }

    public function rest_can_read_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id ) || self::node_is_public( 'project', $project_id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = (string) $request->get_route();
        if ( 0 !== strpos( $route, '/' . self::API_NAMESPACE . '/knowledge/' ) && false === strpos( $route, '/knowledge-coverage' ) ) {
            return $response;
        }
        if ( is_user_logged_in() || rest_sanitize_boolean( $request->get_param( 'include_private' ) ) || false !== strpos( $route, '/migration' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-topics-concepts-relationships',
            SC_LIBRARY_URL . 'assets/css/sc-library-topics-concepts-relationships.css',
            array( 'sc-library-connected-research', 'sc-library-citation-manager' ),
            self::VERSION
        );
        wp_register_script(
            'sc-library-topics-concepts-relationships',
            SC_LIBRARY_URL . 'assets/js/sc-library-topics-concepts-relationships.js',
            array(),
            self::VERSION,
            true
        );
        wp_localize_script(
            'sc-library-topics-concepts-relationships',
            'SCLibraryKnowledgeGraph',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_topics_relationships_v320' ),
                'strings' => array(
                    'working'         => __( 'Working…', 'sustainable-catalyst-library' ),
                    'browsing'        => __( 'Loading relationships…', 'sustainable-catalyst-library' ),
                    'migrationRun'    => __( 'Topic migration batch complete.', 'sustainable-catalyst-library' ),
                    'migrationReset'  => __( 'Topic migration state reset.', 'sustainable-catalyst-library' ),
                    'confirmReset'    => __( 'Reset the topic migration cursor and begin again?', 'sustainable-catalyst-library' ),
                    'remove'          => __( 'Remove', 'sustainable-catalyst-library' ),
                    'missingNode'     => __( 'Enter a record ID.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $supported = in_array(
            $screen->post_type,
            array(
                SC_Library_Foundation_Pages::POST_TYPE,
                SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE,
                SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
                SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
                SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
                self::CONCEPT_POST_TYPE,
                self::ENTITY_POST_TYPE,
                self::VOCABULARY_POST_TYPE,
            ),
            true
        );
        $workspace = false !== strpos( (string) $screen->id, 'sc-library-topics-relationships' );
        $topic_screen = 'edit-' . self::TOPIC_TAXONOMY === $screen->id;
        if ( ! $supported && ! $workspace && ! $topic_screen ) {
            return;
        }
        $this->register_public_assets();
        wp_enqueue_style( 'sc-library-topics-concepts-relationships' );
        wp_enqueue_script( 'sc-library-topics-concepts-relationships' );
    }

    public function template_include( $template ) {
        if ( is_singular( array( self::CONCEPT_POST_TYPE, self::ENTITY_POST_TYPE, self::VOCABULARY_POST_TYPE ) ) ) {
            $candidate = dirname( __DIR__ ) . '/templates/single-sc_knowledge_node.php';
            if ( is_file( $candidate ) ) {
                return $candidate;
            }
        }
        return $template;
    }

    public function cleanup_deleted_node( $post_id ) {
        if ( self::$deleting_relation || self::RELATION_POST_TYPE === get_post_type( $post_id ) ) {
            return;
        }
        $kind = self::post_kind( get_post_type( $post_id ) );
        if ( ! $kind ) {
            return;
        }
        self::$deleting_relation = true;
        foreach ( self::relationships_for_node( $kind, $post_id, true, 'both' ) as $relation ) {
            if ( ! empty( $relation['relation_id'] ) ) {
                wp_delete_post( absint( $relation['relation_id'] ), true );
            }
        }
        self::$deleting_relation = false;
        self::invalidate_coverage_cache();
    }

    public function cleanup_deleted_topic( $term_id ) {
        foreach ( self::relationships_for_node( 'topic', absint( $term_id ), true, 'both' ) as $relation ) {
            if ( ! empty( $relation['relation_id'] ) ) {
                wp_delete_post( absint( $relation['relation_id'] ), true );
            }
        }
        self::invalidate_coverage_cache();
    }

    public function maybe_flush_rewrite_rules() {
        if ( self::VERSION === get_option( self::OPTION_ROUTES_VERSION, '' ) ) {
            return;
        }
        flush_rewrite_rules( false );
        update_option( self::OPTION_ROUTES_VERSION, self::VERSION, false );
    }

    public static function invalidate_coverage_cache() {
        delete_transient( self::TRANSIENT_PUBLIC_COVERAGE );
    }

    public function invalidate_deleted_post( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( self::RELATION_POST_TYPE === $post_type || self::post_kind( $post_type ) || self::VOCABULARY_POST_TYPE === $post_type ) {
            self::invalidate_coverage_cache();
        }
    }

    public function invalidate_topic_assignment_cache( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        if ( self::TOPIC_TAXONOMY === $taxonomy ) {
            self::invalidate_coverage_cache();
        }
    }

    public function invalidate_status_cache( $new_status, $old_status, $post ) {
        if ( $post instanceof WP_Post && ( self::post_kind( $post->post_type ) || self::VOCABULARY_POST_TYPE === $post->post_type ) ) {
            self::invalidate_coverage_cache();
        }
    }

    public function concept_columns( $columns ) {
        $columns['sc_concept_type'] = __( 'Concept Type', 'sustainable-catalyst-library' );
        $columns['sc_concept_links'] = __( 'Connections', 'sustainable-catalyst-library' );
        return $columns;
    }

    public function concept_column_content( $column, $post_id ) {
        if ( 'sc_concept_type' === $column ) {
            $type = sanitize_key( get_post_meta( $post_id, self::META_CONCEPT_TYPE, true ) ?: 'other' );
            echo esc_html( self::concept_type_options()[ $type ] ?? ucfirst( $type ) );
        } elseif ( 'sc_concept_links' === $column ) {
            echo esc_html( count( self::relationships_for_node( 'concept', $post_id, true, 'both' ) ) );
        }
    }

    public function entity_columns( $columns ) {
        $columns['sc_entity_type'] = __( 'Entity Type', 'sustainable-catalyst-library' );
        $columns['sc_entity_links'] = __( 'Connections', 'sustainable-catalyst-library' );
        return $columns;
    }

    public function entity_column_content( $column, $post_id ) {
        if ( 'sc_entity_type' === $column ) {
            $type = sanitize_key( get_post_meta( $post_id, self::META_ENTITY_TYPE, true ) ?: 'other' );
            echo esc_html( self::entity_type_options()[ $type ] ?? ucfirst( $type ) );
        } elseif ( 'sc_entity_links' === $column ) {
            echo esc_html( count( self::relationships_for_node( 'entity', $post_id, true, 'both' ) ) );
        }
    }

    public static function node_exists( $kind, $id ) {
        $kind = sanitize_key( $kind );
        $id = absint( $id );
        if ( 'topic' === $kind ) {
            $term = get_term( $id, self::TOPIC_TAXONOMY );
            return $term && ! is_wp_error( $term );
        }
        $post_type = self::node_post_type( $kind );
        return $post_type && $post_type === get_post_type( $id );
    }

    public static function node_is_public( $kind, $id ) {
        $kind = sanitize_key( $kind );
        $id = absint( $id );
        if ( 'topic' === $kind ) {
            $term = get_term( $id, self::TOPIC_TAXONOMY );
            return $term && ! is_wp_error( $term ) && 'active' === ( get_term_meta( $id, self::META_TOPIC_STATUS, true ) ?: 'active' );
        }
        if ( ! self::node_exists( $kind, $id ) || 'publish' !== get_post_status( $id ) ) {
            return false;
        }
        if ( 'project' === $kind ) {
            return 'public' === get_post_meta( $id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
        }
        if ( 'claim' === $kind ) {
            return SC_Library_Evidence_Claim_Linking::claim_is_public( $id );
        }
        if ( 'evidence' === $kind ) {
            return SC_Library_Evidence_Claim_Linking::evidence_is_public( $id );
        }
        if ( 'concept' === $kind ) {
            return 'active' === ( get_post_meta( $id, self::META_CONCEPT_STATUS, true ) ?: 'active' );
        }
        return true;
    }

    public static function can_edit_node( $kind, $id ) {
        if ( 'topic' === sanitize_key( $kind ) ) {
            return current_user_can( 'manage_categories' );
        }
        return self::node_exists( $kind, $id ) && current_user_can( 'edit_post', absint( $id ) );
    }

    public static function node_label( $kind, $id ) {
        if ( 'topic' === sanitize_key( $kind ) ) {
            $term = get_term( absint( $id ), self::TOPIC_TAXONOMY );
            return $term && ! is_wp_error( $term ) ? $term->name : '';
        }
        return self::node_exists( $kind, $id ) ? get_the_title( absint( $id ) ) : '';
    }

    public static function node_summary( $kind, $id ) {
        if ( 'topic' === sanitize_key( $kind ) ) {
            $term = get_term( absint( $id ), self::TOPIC_TAXONOMY );
            return $term && ! is_wp_error( $term ) ? wp_strip_all_tags( $term->description ) : '';
        }
        if ( ! self::node_exists( $kind, $id ) ) {
            return '';
        }
        $summary = get_post_field( 'post_excerpt', absint( $id ) );
        if ( ! $summary ) {
            $summary = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', absint( $id ) ) ), 36 );
        }
        return wp_strip_all_tags( $summary );
    }

    public static function node_url( $kind, $id ) {
        if ( ! self::node_is_public( $kind, $id ) ) {
            return '';
        }
        if ( 'topic' === sanitize_key( $kind ) ) {
            $url = get_term_link( absint( $id ), self::TOPIC_TAXONOMY );
            return is_wp_error( $url ) ? '' : $url;
        }
        return get_permalink( absint( $id ) );
    }

    private static function record_options( $post_type ) {
        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 500,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $options = array();
        foreach ( $posts as $post ) {
            $options[ $post->ID ] = $post->post_title;
        }
        return $options;
    }

    private static function validated_term_ids( $raw ) {
        $ids = array();
        foreach ( self::id_list( $raw ) as $term_id ) {
            $term = get_term( $term_id, self::TOPIC_TAXONOMY );
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = $term_id;
            }
            if ( count( $ids ) >= self::MAX_ASSIGNMENTS ) {
                break;
            }
        }
        return $ids;
    }

    private static function validated_post_ids( $raw, $post_type, $include_private = true ) {
        $ids = array();
        foreach ( self::id_list( $raw ) as $post_id ) {
            if ( $post_type !== get_post_type( $post_id ) ) {
                continue;
            }
            if ( ! $include_private && 'publish' !== get_post_status( $post_id ) ) {
                continue;
            }
            $ids[] = $post_id;
            if ( count( $ids ) >= self::MAX_ASSIGNMENTS ) {
                break;
            }
        }
        return $ids;
    }

    private static function valid_related_post_id( $value, $post_type ) {
        $post_id = absint( $value );
        return $post_id && $post_type === get_post_type( $post_id ) ? $post_id : 0;
    }

    private static function migration_state() {
        $state = get_option( self::OPTION_MIGRATION_STATE, array() );
        return wp_parse_args( is_array( $state ) ? $state : array(), self::default_migration_state() );
    }

    private static function default_migration_state() {
        return array(
            'schema'       => self::MIGRATION_SCHEMA,
            'version'      => self::VERSION,
            'status'       => 'pending',
            'step'         => 'terms',
            'cursor'       => 0,
            'processed'    => 0,
            'failures'     => array(),
            'last_error'   => '',
            'started_at'   => '',
            'updated_at'   => '',
            'completed_at' => '',
        );
    }

    private static function string_list( $value ) {
        if ( is_string( $value ) ) {
            $value = preg_split( '/[\r\n,]+/', $value );
        }
        return array_slice(
            array_values(
                array_unique(
                    array_filter(
                        array_map( 'sanitize_text_field', (array) $value )
                    )
                )
            ),
            0,
            self::MAX_ASSIGNMENTS
        );
    }

    private static function id_list( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
    }

    private static function allowed_value( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
    }

    private static function update_id_meta( $post_id, $key, $ids ) {
        $ids = array_slice( self::id_list( $ids ), 0, self::MAX_ASSIGNMENTS );
        $ids ? update_post_meta( $post_id, $key, $ids ) : delete_post_meta( $post_id, $key );
    }

    private static function update_or_delete_meta( $post_id, $key, $value ) {
        if ( '' === $value || 0 === $value || array() === $value || null === $value ) {
            delete_post_meta( $post_id, $key );
        } else {
            update_post_meta( $post_id, $key, $value );
        }
    }

    private static function register_cli_commands() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
            return;
        }
        WP_CLI::add_command(
            'sc-library knowledge migrate-topics',
            static function ( $args, $assoc_args ) {
                $limit = absint( $assoc_args['limit'] ?? self::MIGRATION_BATCH );
                $result = self::run_migration_batch( $limit );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library knowledge node',
            static function ( $args, $assoc_args ) {
                $kind = sanitize_key( $args[0] ?? '' );
                $id = absint( $args[1] ?? 0 );
                $data = self::get_node_data( $kind, $id, true );
                if ( ! $data ) {
                    WP_CLI::error( 'Knowledge node not found.' );
                }
                WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library knowledge coverage',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $assoc_args['project'] ?? 0 );
                $report = $project_id ? self::project_coverage_report( $project_id, true ) : self::coverage_report( true );
                WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library knowledge relate',
            static function ( $args, $assoc_args ) {
                $result = self::save_relationship(
                    array(
                        'from_kind' => sanitize_key( $args[0] ?? '' ),
                        'from_id'   => absint( $args[1] ?? 0 ),
                        'relation'  => sanitize_key( $args[2] ?? '' ),
                        'to_kind'   => sanitize_key( $args[3] ?? '' ),
                        'to_id'     => absint( $args[4] ?? 0 ),
                        'note'      => sanitize_text_field( $assoc_args['note'] ?? '' ),
                        'weight'    => absint( $assoc_args['weight'] ?? 3 ),
                        'public'    => ! empty( $assoc_args['public'] ),
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( 'Relationship ' . absint( $result ) . ' saved.' );
            }
        );
    }
}
