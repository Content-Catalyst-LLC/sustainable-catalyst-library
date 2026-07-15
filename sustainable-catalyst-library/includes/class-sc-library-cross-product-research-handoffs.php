<?php
/**
 * Cross-Product Research Workspace Handoffs.
 *
 * Stable project identities, typed handoff envelopes, target-specific adapters,
 * expiring bearer delivery links, return histories, platform bundle exports,
 * REST and WP-CLI interfaces, and project/workspace administration.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Cross_Product_Research_Handoffs {
    public const VERSION = '3.4.0';
    public const API_NAMESPACE = 'sc-library/v1';

    public const HANDOFF_POST_TYPE = 'sc_workspace_handoff';

    public const PROJECT_IDENTITY_SCHEMA = 'sc-platform-project-identity/1.0';
    public const HANDOFF_SCHEMA = 'sc-platform-research-handoff/1.0';
    public const HISTORY_SCHEMA = 'sc-platform-handoff-history/1.0';
    public const BUNDLE_SCHEMA = 'sc-platform-research-bundle/1.0';
    public const PRODUCT_REGISTRY_SCHEMA = 'sc-platform-product-registry/1.0';

    public const META_PROJECT_UUID = '_sc_platform_project_uuid';
    public const META_PROJECT_URN = '_sc_platform_project_urn';
    public const META_PROJECT_IDENTITY_CREATED_AT = '_sc_platform_project_identity_created_at';
    public const META_PROJECT_ALIASES = '_sc_platform_project_aliases';

    public const META_UUID = '_sc_handoff_uuid';
    public const META_PROJECT_ID = '_sc_handoff_project_id';
    public const META_HANDOFF_PROJECT_UUID = '_sc_handoff_project_uuid';
    public const META_SOURCE_PRODUCT = '_sc_handoff_source_product';
    public const META_TARGET_PRODUCT = '_sc_handoff_target_product';
    public const META_TYPE = '_sc_handoff_type';
    public const META_DIRECTION = '_sc_handoff_direction';
    public const META_STATUS = '_sc_handoff_status';
    public const META_REQUEST = '_sc_handoff_request';
    public const META_SECTIONS = '_sc_handoff_bundle_sections';
    public const META_BUNDLE = '_sc_handoff_bundle';
    public const META_BUNDLE_CHECKSUM = '_sc_handoff_bundle_checksum';
    public const META_RECIPIENT_URL = '_sc_handoff_recipient_url';
    public const META_RETURN_URL = '_sc_handoff_return_url';
    public const META_RESULT_URL = '_sc_handoff_result_url';
    public const META_TOKEN_HASH = '_sc_handoff_token_hash';
    public const META_TOKEN_EXPIRES = '_sc_handoff_token_expires';
    public const META_HISTORY = '_sc_handoff_history';
    public const META_CREATED_AT = '_sc_handoff_created_at';
    public const META_CREATED_BY = '_sc_handoff_created_by';
    public const META_UPDATED_AT = '_sc_handoff_updated_at';
    public const META_UPDATED_BY = '_sc_handoff_updated_by';

    public const OPTION_PRODUCT_SETTINGS = 'sc_library_v340_product_settings';
    public const OPTION_MIGRATION_STATE = 'sc_library_v340_identity_migration_state';
    public const TRANSIENT_MIGRATION_LOCK = 'sc_library_v340_identity_migration_lock';
    public const CRON_HOOK = 'sc_library_v340_identity_migration_tick';

    public const MAX_HISTORY = 200;
    public const MAX_HANDOFFS_PER_PROJECT = 500;
    public const MAX_NODE_KEYS = 500;
    public const MAX_DATASET_REFERENCES = 100;
    public const MAX_PARAMETERS = 100;
    public const MIGRATION_BATCH = 25;
    public const TOKEN_TTL_DAYS = 7;
    public const TOKEN_MAX_DAYS = 30;
    public const LOCK_SECONDS = 180;

    private static $saving_identity = false;

    public function __construct() {
        add_action( 'init', array( $this, 'register_handoff_type' ), 285 );
        add_action( 'init', array( $this, 'schedule_identity_migration' ), 985 );
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_migration' ) );

        add_action( 'admin_menu', array( $this, 'register_workspace' ), 280 );
        add_action( 'add_meta_boxes', array( $this, 'register_project_meta_box' ), 180 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 180 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );

        add_action( 'save_post_' . SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE, array( $this, 'ensure_identity_on_save' ), 180, 3 );
        add_action( 'post_updated', array( $this, 'track_project_aliases' ), 180, 3 );
        add_action( 'before_delete_post', array( $this, 'cleanup_deleted_project_or_handoff' ), 180 );

        add_action( 'wp_ajax_sc_library_v340_create_handoff', array( $this, 'ajax_create_handoff' ) );
        add_action( 'wp_ajax_sc_library_v340_rotate_token', array( $this, 'ajax_rotate_token' ) );
        add_action( 'wp_ajax_sc_library_v340_update_status', array( $this, 'ajax_update_status' ) );
        add_action( 'wp_ajax_sc_library_v340_run_migration', array( $this, 'ajax_run_migration' ) );
        add_action( 'admin_post_sc_library_v340_save_settings', array( $this, 'handle_save_settings' ) );
        add_action( 'admin_post_sc_library_v340_download_bundle', array( $this, 'handle_download_bundle' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'protect_private_rest_responses' ), 60, 3 );

        add_shortcode( 'sc_project_handoff_workspace', array( $this, 'shortcode_project_workspace' ) );
        add_shortcode( 'sc_platform_project_identity', array( $this, 'shortcode_project_identity' ) );

        add_filter( 'sc_library_cross_product_registry', array( __CLASS__, 'filter_product_registry' ), 10, 1 );
        add_filter( 'sc_library_research_librarian_project_context', array( __CLASS__, 'filter_research_librarian_context' ), 20, 3 );
        add_action( 'sc_library_cross_product_return', array( __CLASS__, 'receive_return_event' ), 10, 3 );

        self::register_cli_commands();
    }

    public function register_handoff_type() {
        register_post_type(
            self::HANDOFF_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Research Handoffs', 'sustainable-catalyst-library' ),
                    'singular_name' => __( 'Research Handoff', 'sustainable-catalyst-library' ),
                ),
                'public'              => false,
                'show_ui'             => false,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'supports'            => array( 'title' ),
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
            )
        );
    }

    public static function base_product_registry() {
        return array(
            'research-lab' => array(
                'key'           => 'research-lab',
                'label'         => __( 'Research Lab', 'sustainable-catalyst-library' ),
                'schema'        => 'sc-platform-handoff/research-lab/1.0',
                'default_route' => home_url( '/research-lab/' ),
                'description'   => __( 'Experiments, notebooks, scientific datasets, instrumentation, and research reports.', 'sustainable-catalyst-library' ),
                'types'         => array(
                    'experiment-brief'  => __( 'Experiment brief', 'sustainable-catalyst-library' ),
                    'notebook-context'  => __( 'Notebook context', 'sustainable-catalyst-library' ),
                    'dataset-analysis'  => __( 'Dataset analysis', 'sustainable-catalyst-library' ),
                    'report-review'     => __( 'Research report review', 'sustainable-catalyst-library' ),
                ),
            ),
            'workbench' => array(
                'key'           => 'workbench',
                'label'         => __( 'Workbench', 'sustainable-catalyst-library' ),
                'schema'        => 'sc-platform-handoff/workbench/1.0',
                'default_route' => home_url( '/workbench/' ),
                'description'   => __( 'Calculations, symbolic analysis, models, graphs, simulations, and technical reports.', 'sustainable-catalyst-library' ),
                'types'         => array(
                    'calculation-context'   => __( 'Calculation context', 'sustainable-catalyst-library' ),
                    'model-context'         => __( 'Model context', 'sustainable-catalyst-library' ),
                    'visualization-context' => __( 'Visualization context', 'sustainable-catalyst-library' ),
                    'report-context'        => __( 'Technical report context', 'sustainable-catalyst-library' ),
                ),
            ),
            'decision-studio' => array(
                'key'           => 'decision-studio',
                'label'         => __( 'Decision Studio', 'sustainable-catalyst-library' ),
                'schema'        => 'sc-platform-handoff/decision-studio/1.0',
                'default_route' => home_url( '/decision-studio/' ),
                'description'   => __( 'Decision questions, evidence packets, scenarios, assumptions, and review records.', 'sustainable-catalyst-library' ),
                'types'         => array(
                    'evidence-packet' => __( 'Evidence packet', 'sustainable-catalyst-library' ),
                    'decision-context'=> __( 'Decision context', 'sustainable-catalyst-library' ),
                    'scenario-context'=> __( 'Scenario context', 'sustainable-catalyst-library' ),
                    'review-packet'   => __( 'Decision review packet', 'sustainable-catalyst-library' ),
                ),
            ),
            'research-librarian' => array(
                'key'           => 'research-librarian',
                'label'         => __( 'Research Librarian', 'sustainable-catalyst-library' ),
                'schema'        => 'sc-platform-handoff/research-librarian/1.0',
                'default_route' => home_url( '/research-librarian/' ),
                'description'   => __( 'Project-aware retrieval, Source discovery, pathways, evidence gaps, and grounded research guidance.', 'sustainable-catalyst-library' ),
                'types'         => array(
                    'research-context' => __( 'Research context', 'sustainable-catalyst-library' ),
                    'source-discovery' => __( 'Source discovery', 'sustainable-catalyst-library' ),
                    'pathway-context'  => __( 'Pathway context', 'sustainable-catalyst-library' ),
                    'gap-analysis'     => __( 'Knowledge-gap analysis', 'sustainable-catalyst-library' ),
                ),
            ),
            'site-intelligence' => array(
                'key'           => 'site-intelligence',
                'label'         => __( 'Site Intelligence', 'sustainable-catalyst-library' ),
                'schema'        => 'sc-platform-handoff/site-intelligence/1.0',
                'default_route' => home_url( '/site-intelligence/' ),
                'description'   => __( 'Datasets, saved views, country context, indicators, maps, events, and briefing records.', 'sustainable-catalyst-library' ),
                'types'         => array(
                    'dataset-reference' => __( 'Dataset reference', 'sustainable-catalyst-library' ),
                    'saved-view'        => __( 'Saved intelligence view', 'sustainable-catalyst-library' ),
                    'country-context'   => __( 'Country context', 'sustainable-catalyst-library' ),
                    'briefing-context'  => __( 'Briefing context', 'sustainable-catalyst-library' ),
                ),
            ),
        );
    }

    public static function filter_product_registry( $registry ) {
        $base = self::base_product_registry();
        if ( ! is_array( $registry ) ) {
            return $base;
        }
        return array_replace_recursive( $base, $registry );
    }

    public static function product_registry( $include_disabled = false ) {
        $registry = apply_filters( 'sc_library_cross_product_registry', self::base_product_registry() );
        $registry = is_array( $registry ) ? $registry : self::base_product_registry();
        $settings = self::product_settings();
        $result = array();

        foreach ( $registry as $key => $product ) {
            $key = sanitize_key( $key );
            if ( ! $key || ! is_array( $product ) ) {
                continue;
            }
            $setting = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
            $enabled = ! array_key_exists( 'enabled', $setting ) || ! empty( $setting['enabled'] );
            if ( ! $include_disabled && ! $enabled ) {
                continue;
            }
            $route = esc_url_raw( $setting['route'] ?? ( $product['default_route'] ?? '' ) );
            $result[ $key ] = array(
                'schema'      => self::PRODUCT_REGISTRY_SCHEMA,
                'key'         => $key,
                'label'       => sanitize_text_field( $product['label'] ?? $key ),
                'description' => sanitize_text_field( $product['description'] ?? '' ),
                'contract'    => sanitize_text_field( $product['schema'] ?? '' ),
                'route'       => $route,
                'enabled'     => $enabled,
                'types'       => self::sanitize_type_map( $product['types'] ?? array() ),
                'delivery'    => sanitize_key( $setting['delivery'] ?? 'signed-rest' ),
            );
        }
        return $result;
    }

    public static function handoff_status_options() {
        return array(
            'draft'       => __( 'Draft', 'sustainable-catalyst-library' ),
            'ready'       => __( 'Ready', 'sustainable-catalyst-library' ),
            'sent'        => __( 'Sent', 'sustainable-catalyst-library' ),
            'opened'      => __( 'Opened', 'sustainable-catalyst-library' ),
            'accepted'    => __( 'Accepted', 'sustainable-catalyst-library' ),
            'in-progress' => __( 'In progress', 'sustainable-catalyst-library' ),
            'completed'   => __( 'Completed', 'sustainable-catalyst-library' ),
            'failed'      => __( 'Failed', 'sustainable-catalyst-library' ),
            'cancelled'   => __( 'Cancelled', 'sustainable-catalyst-library' ),
            'archived'    => __( 'Archived', 'sustainable-catalyst-library' ),
        );
    }

    public static function handoff_direction_options() {
        return array(
            'outbound' => __( 'Outbound', 'sustainable-catalyst-library' ),
            'inbound'  => __( 'Inbound', 'sustainable-catalyst-library' ),
            'return'   => __( 'Return', 'sustainable-catalyst-library' ),
        );
    }

    public static function bundle_section_options() {
        return array(
            'project'      => __( 'Project brief and identity', 'sustainable-catalyst-library' ),
            'bibliography' => __( 'Bibliography and Research Sources', 'sustainable-catalyst-library' ),
            'evidence'     => __( 'Claims and Evidence Notes', 'sustainable-catalyst-library' ),
            'semantic'     => __( 'Topics, Concepts, Entities, and relationships', 'sustainable-catalyst-library' ),
            'pathways'     => __( 'Knowledge Pathways and article maps', 'sustainable-catalyst-library' ),
            'integrity'    => __( 'Source integrity review', 'sustainable-catalyst-library' ),
            'datasets'     => __( 'Dataset and saved-view references', 'sustainable-catalyst-library' ),
        );
    }

    public static function status_transition_map() {
        return array(
            'draft'       => array( 'ready', 'cancelled', 'archived' ),
            'ready'       => array( 'draft', 'sent', 'cancelled', 'archived' ),
            'sent'        => array( 'opened', 'accepted', 'in-progress', 'completed', 'failed', 'cancelled', 'archived' ),
            'opened'      => array( 'accepted', 'in-progress', 'completed', 'failed', 'cancelled', 'archived' ),
            'accepted'    => array( 'in-progress', 'completed', 'failed', 'cancelled', 'archived' ),
            'in-progress' => array( 'completed', 'failed', 'cancelled', 'archived' ),
            'completed'   => array( 'archived' ),
            'failed'      => array( 'ready', 'sent', 'archived', 'cancelled' ),
            'cancelled'   => array( 'draft', 'ready', 'archived' ),
            'archived'    => array(),
        );
    }

    public static function can_transition( $from, $to ) {
        $from = sanitize_key( $from );
        $to = sanitize_key( $to );
        if ( $from === $to ) {
            return true;
        }
        $map = self::status_transition_map();
        return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
    }

    public static function allowed_token_statuses() {
        return array( 'opened', 'accepted', 'in-progress', 'completed', 'failed' );
    }

    private static function sanitize_type_map( $types ) {
        $result = array();
        foreach ( (array) $types as $key => $label ) {
            $key = sanitize_key( $key );
            if ( $key ) {
                $result[ $key ] = sanitize_text_field( $label );
            }
        }
        return $result;
    }

    public static function product_settings() {
        $stored = get_option( self::OPTION_PRODUCT_SETTINGS, array() );
        return is_array( $stored ) ? $stored : array();
    }

    public static function ensure_project_identity( $project_id ) {
        $project_id = absint( $project_id );
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error( 'invalid_platform_project', __( 'The Research Project record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }

        $uuid = sanitize_text_field( get_post_meta( $project_id, self::META_PROJECT_UUID, true ) );
        if ( ! self::valid_uuid( $uuid ) ) {
            $uuid = wp_generate_uuid4();
            update_post_meta( $project_id, self::META_PROJECT_UUID, $uuid );
            update_post_meta( $project_id, self::META_PROJECT_IDENTITY_CREATED_AT, current_time( 'mysql' ) );
        }
        $urn = self::project_urn( $uuid );
        update_post_meta( $project_id, self::META_PROJECT_URN, $urn );
        return self::project_identity( $project_id, true );
    }

    public static function project_urn( $uuid ) {
        $uuid = strtolower( sanitize_text_field( $uuid ) );
        return self::valid_uuid( $uuid ) ? 'urn:sc:research-project:' . $uuid : '';
    }

    public static function project_identity( $project_id, $ensure = false ) {
        $project_id = absint( $project_id );
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return array();
        }
        $uuid = sanitize_text_field( get_post_meta( $project_id, self::META_PROJECT_UUID, true ) );
        if ( $ensure && ! self::valid_uuid( $uuid ) ) {
            $created = self::ensure_project_identity( $project_id );
            return is_wp_error( $created ) ? array() : $created;
        }
        if ( ! self::valid_uuid( $uuid ) ) {
            return array();
        }
        $post = get_post( $project_id );
        $aliases = get_post_meta( $project_id, self::META_PROJECT_ALIASES, true );
        $aliases = is_array( $aliases ) ? array_values( array_unique( array_filter( array_map( 'esc_url_raw', $aliases ) ) ) ) : array();
        return array(
            'schema'       => self::PROJECT_IDENTITY_SCHEMA,
            'uuid'         => $uuid,
            'urn'          => self::project_urn( $uuid ),
            'wordpress_id' => $project_id,
            'slug'         => $post ? $post->post_name : '',
            'title'        => get_the_title( $project_id ),
            'canonical_url'=> 'publish' === get_post_status( $project_id ) ? get_permalink( $project_id ) : '',
            'edit_url'     => current_user_can( 'edit_post', $project_id ) ? get_edit_post_link( $project_id, 'raw' ) : '',
            'aliases'      => $aliases,
            'created_at'   => sanitize_text_field( get_post_meta( $project_id, self::META_PROJECT_IDENTITY_CREATED_AT, true ) ),
            'updated_at'   => get_post_modified_time( 'c', true, $project_id ),
        );
    }

    public function ensure_identity_on_save( $post_id, $post, $update ) {
        if ( self::$saving_identity || ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        self::$saving_identity = true;
        self::ensure_project_identity( $post_id );
        self::$saving_identity = false;
    }

    public function track_project_aliases( $post_id, $post_after, $post_before ) {
        if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== $post_after->post_type ) {
            return;
        }
        if ( $post_after->post_name === $post_before->post_name ) {
            return;
        }
        $old_url = get_permalink( $post_before );
        if ( ! $old_url ) {
            return;
        }
        $aliases = get_post_meta( $post_id, self::META_PROJECT_ALIASES, true );
        $aliases = is_array( $aliases ) ? $aliases : array();
        $aliases[] = esc_url_raw( $old_url );
        update_post_meta( $post_id, self::META_PROJECT_ALIASES, array_slice( array_values( array_unique( array_filter( $aliases ) ) ), -50 ) );
    }

    public static function create_handoff( $project_id, $args = array() ) {
        $project_id = absint( $project_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return new WP_Error( 'invalid_handoff_project', __( 'The Research Project record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $identity = self::ensure_project_identity( $project_id );
        if ( is_wp_error( $identity ) ) {
            return $identity;
        }

        $registry = self::product_registry();
        $target = sanitize_key( $args['target_product'] ?? '' );
        if ( ! isset( $registry[ $target ] ) ) {
            return new WP_Error( 'invalid_handoff_product', __( 'The target product is not enabled or does not exist.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $type = sanitize_key( $args['handoff_type'] ?? '' );
        if ( ! isset( $registry[ $target ]['types'][ $type ] ) ) {
            return new WP_Error( 'invalid_handoff_type', __( 'The handoff type is not supported by the target product.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }

        $status = self::allowed_key( $args['status'] ?? 'draft', self::handoff_status_options(), 'draft' );
        $direction = self::allowed_key( $args['direction'] ?? 'outbound', self::handoff_direction_options(), 'outbound' );
        $sections = self::sanitize_sections( $args['sections'] ?? array_keys( self::bundle_section_options() ) );
        $request = self::sanitize_request( $args['request'] ?? $args );
        $uuid = wp_generate_uuid4();
        $title = sanitize_text_field( $args['title'] ?? '' );
        if ( ! $title ) {
            $title = sprintf(
                __( '%1$s → %2$s: %3$s', 'sustainable-catalyst-library' ),
                get_the_title( $project_id ),
                $registry[ $target ]['label'],
                $registry[ $target ]['types'][ $type ]
            );
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => self::HANDOFF_POST_TYPE,
                'post_status' => 'private',
                'post_title'  => $title,
            ),
            true
        );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $return_url = esc_url_raw( $args['return_url'] ?? get_edit_post_link( $project_id, 'raw' ) );
        $recipient_url = esc_url_raw( $args['recipient_url'] ?? $registry[ $target ]['route'] );
        $created_at = current_time( 'mysql' );
        $created_by = get_current_user_id();

        update_post_meta( $post_id, self::META_UUID, $uuid );
        update_post_meta( $post_id, self::META_PROJECT_ID, $project_id );
        update_post_meta( $post_id, self::META_HANDOFF_PROJECT_UUID, $identity['uuid'] );
        update_post_meta( $post_id, self::META_SOURCE_PRODUCT, 'knowledge-library' );
        update_post_meta( $post_id, self::META_TARGET_PRODUCT, $target );
        update_post_meta( $post_id, self::META_TYPE, $type );
        update_post_meta( $post_id, self::META_DIRECTION, $direction );
        update_post_meta( $post_id, self::META_STATUS, $status );
        update_post_meta( $post_id, self::META_REQUEST, $request );
        update_post_meta( $post_id, self::META_SECTIONS, $sections );
        self::update_or_delete_meta( $post_id, self::META_RECIPIENT_URL, $recipient_url );
        self::update_or_delete_meta( $post_id, self::META_RETURN_URL, $return_url );
        update_post_meta( $post_id, self::META_CREATED_AT, $created_at );
        update_post_meta( $post_id, self::META_CREATED_BY, $created_by );
        update_post_meta( $post_id, self::META_UPDATED_AT, $created_at );
        update_post_meta( $post_id, self::META_UPDATED_BY, $created_by );

        $bundle = self::build_bundle( $project_id, $target, $type, array( 'sections' => $sections, 'request' => $request, 'handoff_uuid' => $uuid, 'handoff_id' => $post_id ) );
        if ( is_wp_error( $bundle ) ) {
            wp_delete_post( $post_id, true );
            return $bundle;
        }
        update_post_meta( $post_id, self::META_BUNDLE, $bundle );
        update_post_meta( $post_id, self::META_BUNDLE_CHECKSUM, self::bundle_checksum( $bundle ) );
        self::append_history( $post_id, '', $status, __( 'Handoff created.', 'sustainable-catalyst-library' ), array( 'actor_product' => 'knowledge-library' ) );

        $token = array();
        if ( ! empty( $args['issue_token'] ) || in_array( $status, array( 'ready', 'sent' ), true ) ) {
            $token = self::issue_delivery_token( $post_id, absint( $args['token_days'] ?? self::TOKEN_TTL_DAYS ) );
        }

        $data = self::handoff_data( $post_id, true );
        if ( ! is_wp_error( $token ) && $token ) {
            $data['delivery'] = $token;
        }
        do_action( 'sc_library_cross_product_handoff_created', $post_id, $data );
        do_action( 'sc_library_handoff_to_' . str_replace( '-', '_', $target ), $bundle, $data );
        return $data;
    }

    public static function refresh_bundle( $handoff_id ) {
        $handoff_id = absint( $handoff_id );
        if ( self::HANDOFF_POST_TYPE !== get_post_type( $handoff_id ) ) {
            return new WP_Error( 'invalid_handoff', __( 'The handoff record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        $target = sanitize_key( get_post_meta( $handoff_id, self::META_TARGET_PRODUCT, true ) );
        $type = sanitize_key( get_post_meta( $handoff_id, self::META_TYPE, true ) );
        $sections = get_post_meta( $handoff_id, self::META_SECTIONS, true );
        $request = get_post_meta( $handoff_id, self::META_REQUEST, true );
        $bundle = self::build_bundle( $project_id, $target, $type, array( 'sections' => $sections, 'request' => $request, 'handoff_uuid' => get_post_meta( $handoff_id, self::META_UUID, true ), 'handoff_id' => $handoff_id ) );
        if ( is_wp_error( $bundle ) ) {
            return $bundle;
        }
        update_post_meta( $handoff_id, self::META_BUNDLE, $bundle );
        update_post_meta( $handoff_id, self::META_BUNDLE_CHECKSUM, self::bundle_checksum( $bundle ) );
        update_post_meta( $handoff_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        update_post_meta( $handoff_id, self::META_UPDATED_BY, get_current_user_id() );
        self::append_history( $handoff_id, self::handoff_status( $handoff_id ), self::handoff_status( $handoff_id ), __( 'Research bundle refreshed.', 'sustainable-catalyst-library' ) );
        return $bundle;
    }

    public static function build_bundle( $project_id, $target_product, $handoff_type, $args = array() ) {
        $project_id = absint( $project_id );
        $target_product = sanitize_key( $target_product );
        $handoff_type = sanitize_key( $handoff_type );
        $registry = self::product_registry( true );
        if ( ! isset( $registry[ $target_product ] ) || ! isset( $registry[ $target_product ]['types'][ $handoff_type ] ) ) {
            return new WP_Error( 'invalid_bundle_contract', __( 'The product handoff contract is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $identity = self::project_identity( $project_id, true );
        if ( ! $identity ) {
            return new WP_Error( 'missing_project_identity', __( 'The project does not have a platform identity.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
        }

        $sections = self::sanitize_sections( $args['sections'] ?? array_keys( self::bundle_section_options() ) );
        $request = self::sanitize_request( $args['request'] ?? array() );
        $project = class_exists( 'SC_Library_Connected_Research_Environment' )
            ? SC_Library_Connected_Research_Environment::project_data( $project_id, true )
            : array();
        if ( ! is_array( $project ) || ! $project ) {
            $post = get_post( $project_id );
            $project = array(
                'id'          => $project_id,
                'title'       => get_the_title( $project_id ),
                'summary'     => $post ? $post->post_excerpt : '',
                'description' => $post ? $post->post_content : '',
            );
        }

        $context = array();
        if ( in_array( 'project', $sections, true ) ) {
            $context['project'] = $project;
        }
        if ( in_array( 'bibliography', $sections, true ) && class_exists( 'SC_Library_Connected_Research_Environment' ) ) {
            $context['bibliography'] = SC_Library_Connected_Research_Environment::bibliography( $project_id, true );
        }
        if ( in_array( 'evidence', $sections, true ) && class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
            $context['evidence_packet'] = SC_Library_Evidence_Claim_Linking::project_packet( $project_id, true );
        }
        if ( in_array( 'semantic', $sections, true ) && class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) {
            $context['semantic_context'] = array(
                'project_node' => SC_Library_Topics_Concepts_Relationships::get_node_data( 'project', $project_id, true ),
                'coverage'     => SC_Library_Topics_Concepts_Relationships::project_coverage_report( $project_id, true ),
            );
        }
        if ( in_array( 'pathways', $sections, true ) && class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ) {
            $context['pathways'] = SC_Library_Knowledge_Pathways_Article_Maps::pathways_for_node( 'project', $project_id, true, 50 );
            $context['pathway_recommendations'] = SC_Library_Knowledge_Pathways_Article_Maps::recommend_pathways(
                array(
                    'query'      => sanitize_text_field( $project['research_question'] ?? $project['title'] ?? '' ),
                    'node_keys'  => array( 'project:' . $project_id ),
                    'topic_ids'  => self::extract_ids( $context['semantic_context']['project_node']['topics'] ?? array() ),
                    'concept_ids'=> self::extract_ids( $context['semantic_context']['project_node']['concepts'] ?? array() ),
                ),
                true,
                10
            );
        }
        if ( in_array( 'integrity', $sections, true ) && class_exists( 'SC_Library_Source_Versioning_Integrity' ) ) {
            $context['source_integrity'] = SC_Library_Source_Versioning_Integrity::project_integrity_report( $project_id, true );
        }
        if ( in_array( 'datasets', $sections, true ) ) {
            $context['dataset_references'] = $request['dataset_references'];
        }

        $adapter = self::build_adapter_payload( $target_product, $handoff_type, $project, $context, $request );
        $bundle = array(
            'schema'          => self::BUNDLE_SCHEMA,
            'bundle_id'       => wp_generate_uuid4(),
            'handoff_uuid'    => sanitize_text_field( $args['handoff_uuid'] ?? '' ),
            'handoff_id'      => absint( $args['handoff_id'] ?? 0 ),
            'source_product'  => 'knowledge-library',
            'target_product'  => $target_product,
            'handoff_type'    => $handoff_type,
            'target_contract' => $registry[ $target_product ]['contract'],
            'project_identity'=> $identity,
            'sections'        => $sections,
            'request'         => $request,
            'adapter'         => $adapter,
            'context'         => $context,
            'return'          => array(
                'project_url' => $identity['canonical_url'],
                'edit_url'    => $identity['edit_url'],
                'rest_route'  => rest_url( self::API_NAMESPACE . '/handoffs/' . sanitize_text_field( $args['handoff_uuid'] ?? '' ) . '/status' ),
            ),
            'provenance'      => array(
                'generated_at' => current_time( 'mysql' ),
                'generated_by' => get_current_user_id(),
                'library_version' => self::VERSION,
                'site_url'     => home_url( '/' ),
            ),
        );
        $bundle = apply_filters( 'sc_library_cross_product_handoff_bundle', $bundle, $target_product, $handoff_type, $project_id );
        if ( ! self::validate_bundle( $bundle ) ) {
            return new WP_Error( 'invalid_generated_bundle', __( 'The generated research bundle did not pass structural validation.', 'sustainable-catalyst-library' ), array( 'status' => 500 ) );
        }
        return $bundle;
    }

    public static function build_adapter_payload( $target_product, $handoff_type, $project, $context, $request ) {
        $question = sanitize_text_field( $project['research_question'] ?? $request['instructions'] ?? $project['title'] ?? '' );
        $base = array(
            'schema'       => self::base_product_registry()[ $target_product ]['schema'] ?? '',
            'request_type' => $handoff_type,
            'question'     => $question,
            'instructions' => $request['instructions'],
            'parameters'   => $request['parameters'],
            'node_keys'    => $request['selected_node_keys'],
        );

        switch ( $target_product ) {
            case 'research-lab':
                return array_merge(
                    $base,
                    array(
                        'experiment_context' => array(
                            'objectives' => $project['objectives'] ?? array(),
                            'methods'    => $project['methods'] ?? array(),
                            'scope'      => $project['scope'] ?? '',
                            'datasets'   => $request['dataset_references'],
                            'evidence'   => $context['evidence_packet'] ?? array(),
                        ),
                    )
                );
            case 'workbench':
                return array_merge(
                    $base,
                    array(
                        'calculation_context' => array(
                            'assumptions' => $request['assumptions'],
                            'units'       => $request['units'],
                            'methods'     => $project['methods'] ?? array(),
                            'datasets'    => $request['dataset_references'],
                            'output'      => $request['requested_output'],
                        ),
                    )
                );
            case 'decision-studio':
                return array_merge(
                    $base,
                    array(
                        'decision_context' => array(
                            'decision_question' => $question,
                            'criteria'          => $request['criteria'],
                            'assumptions'       => $request['assumptions'],
                            'scenarios'         => $request['scenarios'],
                            'evidence_packet'   => $context['evidence_packet'] ?? array(),
                            'source_integrity'  => $context['source_integrity'] ?? array(),
                        ),
                    )
                );
            case 'research-librarian':
                return array_merge(
                    $base,
                    array(
                        'research_context' => array(
                            'project'         => $context['project'] ?? $project,
                            'bibliography'    => $context['bibliography'] ?? array(),
                            'semantic'        => $context['semantic_context'] ?? array(),
                            'pathways'        => $context['pathways'] ?? array(),
                            'recommendations' => $context['pathway_recommendations'] ?? array(),
                            'gaps'            => $context['semantic_context']['coverage']['gaps'] ?? array(),
                        ),
                    )
                );
            case 'site-intelligence':
                return array_merge(
                    $base,
                    array(
                        'intelligence_context' => array(
                            'datasets'          => $request['dataset_references'],
                            'country_codes'     => $request['country_codes'],
                            'geographic_scope'  => $request['geographic_scope'],
                            'temporal_scope'    => $request['temporal_scope'],
                            'indicators'        => $request['indicators'],
                            'saved_view_url'    => $request['saved_view_url'],
                        ),
                    )
                );
        }
        return $base;
    }

    public static function validate_bundle( $bundle ) {
        return is_array( $bundle )
            && self::BUNDLE_SCHEMA === ( $bundle['schema'] ?? '' )
            && ! empty( $bundle['bundle_id'] )
            && ! empty( $bundle['target_product'] )
            && ! empty( $bundle['handoff_type'] )
            && ! empty( $bundle['project_identity']['uuid'] )
            && ! empty( $bundle['project_identity']['urn'] )
            && isset( $bundle['context'] )
            && is_array( $bundle['context'] );
    }

    public static function bundle_checksum( $bundle ) {
        return hash( 'sha256', wp_json_encode( $bundle ) );
    }

    public static function sanitize_sections( $sections ) {
        $allowed = self::bundle_section_options();
        $result = array();
        foreach ( (array) $sections as $section ) {
            $section = sanitize_key( $section );
            if ( isset( $allowed[ $section ] ) ) {
                $result[] = $section;
            }
        }
        return array_values( array_unique( $result ) );
    }

    public static function sanitize_request( $request ) {
        $request = is_array( $request ) ? $request : array();
        return array(
            'instructions'       => sanitize_textarea_field( $request['instructions'] ?? $request['notes'] ?? '' ),
            'requested_output'   => sanitize_text_field( $request['requested_output'] ?? '' ),
            'parameters'         => self::sanitize_parameters( $request['parameters'] ?? array() ),
            'assumptions'        => self::sanitize_string_list( $request['assumptions'] ?? array(), 100 ),
            'criteria'           => self::sanitize_string_list( $request['criteria'] ?? array(), 100 ),
            'scenarios'          => self::sanitize_string_list( $request['scenarios'] ?? array(), 100 ),
            'units'              => sanitize_text_field( $request['units'] ?? '' ),
            'geographic_scope'   => sanitize_text_field( $request['geographic_scope'] ?? '' ),
            'temporal_scope'     => sanitize_text_field( $request['temporal_scope'] ?? '' ),
            'country_codes'      => self::sanitize_country_codes( $request['country_codes'] ?? array() ),
            'indicators'         => self::sanitize_string_list( $request['indicators'] ?? array(), 100 ),
            'saved_view_url'     => esc_url_raw( $request['saved_view_url'] ?? '' ),
            'dataset_references' => self::sanitize_dataset_references( $request['dataset_references'] ?? array() ),
            'selected_node_keys' => self::sanitize_node_keys( $request['selected_node_keys'] ?? array() ),
        );
    }

    public static function sanitize_dataset_references( $items ) {
        $result = array();
        foreach ( (array) $items as $item ) {
            if ( is_string( $item ) ) {
                $item = array( 'title' => $item );
            }
            if ( ! is_array( $item ) ) {
                continue;
            }
            $record = array(
                'provider'       => sanitize_text_field( $item['provider'] ?? '' ),
                'dataset_id'     => sanitize_text_field( $item['dataset_id'] ?? $item['id'] ?? '' ),
                'title'          => sanitize_text_field( $item['title'] ?? '' ),
                'url'            => esc_url_raw( $item['url'] ?? '' ),
                'geography'      => sanitize_text_field( $item['geography'] ?? '' ),
                'temporal_scope' => sanitize_text_field( $item['temporal_scope'] ?? '' ),
                'notes'          => sanitize_text_field( $item['notes'] ?? '' ),
            );
            if ( $record['title'] || $record['dataset_id'] || $record['url'] ) {
                $result[] = $record;
            }
            if ( count( $result ) >= self::MAX_DATASET_REFERENCES ) {
                break;
            }
        }
        return $result;
    }

    private static function sanitize_parameters( $parameters ) {
        $result = array();
        foreach ( (array) $parameters as $key => $value ) {
            $key = sanitize_key( is_int( $key ) ? 'parameter_' . $key : $key );
            if ( ! $key ) {
                continue;
            }
            if ( is_array( $value ) ) {
                $value = self::sanitize_string_list( $value, 50 );
            } elseif ( is_bool( $value ) ) {
                $value = $value;
            } elseif ( is_numeric( $value ) ) {
                $value = 0 + $value;
            } else {
                $value = sanitize_text_field( $value );
            }
            $result[ $key ] = $value;
            if ( count( $result ) >= self::MAX_PARAMETERS ) {
                break;
            }
        }
        return $result;
    }

    private static function sanitize_string_list( $items, $limit ) {
        if ( is_string( $items ) ) {
            $items = preg_split( '/[\r\n,]+/', $items );
        }
        $result = array();
        foreach ( (array) $items as $item ) {
            $item = sanitize_text_field( $item );
            if ( '' !== $item ) {
                $result[] = $item;
            }
            if ( count( $result ) >= $limit ) {
                break;
            }
        }
        return array_values( array_unique( $result ) );
    }

    private static function sanitize_country_codes( $codes ) {
        if ( is_string( $codes ) ) {
            $codes = preg_split( '/[\s,]+/', $codes );
        }
        $result = array();
        foreach ( (array) $codes as $code ) {
            $code = strtoupper( preg_replace( '/[^A-Z]/', '', strtoupper( (string) $code ) ) );
            if ( in_array( strlen( $code ), array( 2, 3 ), true ) ) {
                $result[] = $code;
            }
        }
        return array_slice( array_values( array_unique( $result ) ), 0, 100 );
    }

    private static function sanitize_node_keys( $keys ) {
        if ( is_string( $keys ) ) {
            $keys = preg_split( '/[\s,]+/', $keys );
        }
        $result = array();
        foreach ( (array) $keys as $key ) {
            $key = strtolower( sanitize_text_field( $key ) );
            if ( preg_match( '/^[a-z][a-z0-9_-]*:[0-9]+$/', $key ) ) {
                $result[] = $key;
            }
            if ( count( $result ) >= self::MAX_NODE_KEYS ) {
                break;
            }
        }
        return array_values( array_unique( $result ) );
    }

    private static function extract_ids( $items ) {
        $ids = array();
        foreach ( (array) $items as $item ) {
            if ( is_array( $item ) ) {
                $ids[] = absint( $item['id'] ?? $item['term_id'] ?? 0 );
            } elseif ( is_object( $item ) ) {
                $ids[] = absint( $item->term_id ?? $item->ID ?? 0 );
            } else {
                $ids[] = absint( $item );
            }
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }

    public static function issue_delivery_token( $handoff_id, $days = self::TOKEN_TTL_DAYS ) {
        $handoff_id = absint( $handoff_id );
        if ( self::HANDOFF_POST_TYPE !== get_post_type( $handoff_id ) ) {
            return new WP_Error( 'invalid_handoff', __( 'The handoff record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $days = max( 1, min( self::TOKEN_MAX_DAYS, absint( $days ) ) );
        $token = wp_generate_password( 48, false, false );
        $expires = time() + ( DAY_IN_SECONDS * $days );
        update_post_meta( $handoff_id, self::META_TOKEN_HASH, self::token_hash( $token ) );
        update_post_meta( $handoff_id, self::META_TOKEN_EXPIRES, $expires );
        $uuid = sanitize_text_field( get_post_meta( $handoff_id, self::META_UUID, true ) );
        $delivery_url = add_query_arg(
            array( 'token' => rawurlencode( $token ) ),
            rest_url( self::API_NAMESPACE . '/handoffs/' . rawurlencode( $uuid ) )
        );
        $recipient_url = esc_url_raw( get_post_meta( $handoff_id, self::META_RECIPIENT_URL, true ) );
        $launch_url = $recipient_url ? add_query_arg(
            array(
                'sc_project_uuid' => rawurlencode( get_post_meta( $handoff_id, self::META_HANDOFF_PROJECT_UUID, true ) ),
                'sc_handoff_uuid' => rawurlencode( $uuid ),
                'sc_handoff_url'  => rawurlencode( $delivery_url ),
                'sc_return_url'   => rawurlencode( get_post_meta( $handoff_id, self::META_RETURN_URL, true ) ),
            ),
            $recipient_url
        ) : '';
        self::append_history( $handoff_id, self::handoff_status( $handoff_id ), self::handoff_status( $handoff_id ), __( 'Delivery token rotated.', 'sustainable-catalyst-library' ) );
        return array(
            'token'        => $token,
            'expires'      => $expires,
            'expires_iso'  => gmdate( 'c', $expires ),
            'delivery_url' => $delivery_url,
            'launch_url'   => $launch_url,
        );
    }

    public static function validate_delivery_token( $handoff_id, $token ) {
        $handoff_id = absint( $handoff_id );
        $token = (string) $token;
        if ( ! $handoff_id || '' === $token ) {
            return false;
        }
        $stored = (string) get_post_meta( $handoff_id, self::META_TOKEN_HASH, true );
        $expires = absint( get_post_meta( $handoff_id, self::META_TOKEN_EXPIRES, true ) );
        return $stored && $expires >= time() && hash_equals( $stored, self::token_hash( $token ) );
    }

    public static function token_hash( $token ) {
        return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
    }

    public static function update_handoff_status( $handoff_id, $new_status, $args = array() ) {
        $handoff_id = absint( $handoff_id );
        if ( self::HANDOFF_POST_TYPE !== get_post_type( $handoff_id ) ) {
            return new WP_Error( 'invalid_handoff', __( 'The handoff record is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $new_status = self::allowed_key( $new_status, self::handoff_status_options(), '' );
        if ( ! $new_status ) {
            return new WP_Error( 'invalid_handoff_status', __( 'The handoff status is invalid.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $old_status = self::handoff_status( $handoff_id );
        if ( empty( $args['force'] ) && ! self::can_transition( $old_status, $new_status ) ) {
            return new WP_Error(
                'invalid_handoff_transition',
                sprintf( __( 'The handoff cannot move from %1$s to %2$s.', 'sustainable-catalyst-library' ), $old_status, $new_status ),
                array( 'status' => 409 )
            );
        }
        if ( ! empty( $args['token_actor'] ) && ! in_array( $new_status, self::allowed_token_statuses(), true ) ) {
            return new WP_Error( 'token_transition_forbidden', __( 'The delivery token cannot set that handoff status.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }

        update_post_meta( $handoff_id, self::META_STATUS, $new_status );
        update_post_meta( $handoff_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
        update_post_meta( $handoff_id, self::META_UPDATED_BY, get_current_user_id() );
        self::update_or_delete_meta( $handoff_id, self::META_RESULT_URL, esc_url_raw( $args['result_url'] ?? get_post_meta( $handoff_id, self::META_RESULT_URL, true ) ) );
        self::append_history(
            $handoff_id,
            $old_status,
            $new_status,
            sanitize_text_field( $args['note'] ?? '' ),
            array(
                'actor_product' => sanitize_key( $args['actor_product'] ?? ( ! empty( $args['token_actor'] ) ? get_post_meta( $handoff_id, self::META_TARGET_PRODUCT, true ) : 'knowledge-library' ) ),
                'result_url'    => esc_url_raw( $args['result_url'] ?? '' ),
                'metadata'      => self::sanitize_parameters( $args['metadata'] ?? array() ),
            )
        );
        $data = ! empty( $args['token_actor'] ) ? self::handoff_data_for_delivery( $handoff_id ) : self::handoff_data( $handoff_id, true );
        do_action( 'sc_library_cross_product_handoff_status_changed', $handoff_id, $old_status, $new_status, $data );
        return $data;
    }

    public static function receive_return_event( $handoff_uuid, $status = 'completed', $payload = array() ) {
        $handoff_id = self::handoff_id_by_uuid( $handoff_uuid );
        if ( ! $handoff_id ) {
            return new WP_Error( 'handoff_not_found', __( 'The handoff could not be found.', 'sustainable-catalyst-library' ) );
        }
        $payload = is_array( $payload ) ? $payload : array();
        return self::update_handoff_status(
            $handoff_id,
            $status,
            array(
                'note'          => $payload['note'] ?? __( 'Return event received.', 'sustainable-catalyst-library' ),
                'result_url'    => $payload['result_url'] ?? '',
                'actor_product' => $payload['product'] ?? get_post_meta( $handoff_id, self::META_TARGET_PRODUCT, true ),
                'metadata'      => $payload['metadata'] ?? array(),
                'force'         => ! empty( $payload['force'] ),
            )
        );
    }

    public static function append_history( $handoff_id, $from_status, $to_status, $note = '', $args = array() ) {
        $history = get_post_meta( $handoff_id, self::META_HISTORY, true );
        $history = is_array( $history ) ? $history : array();
        $history[] = array(
            'schema'        => self::HISTORY_SCHEMA,
            'event_id'      => wp_generate_uuid4(),
            'from_status'   => sanitize_key( $from_status ),
            'to_status'     => sanitize_key( $to_status ),
            'note'          => sanitize_text_field( $note ),
            'actor_user_id' => get_current_user_id(),
            'actor_product' => sanitize_key( $args['actor_product'] ?? 'knowledge-library' ),
            'result_url'    => esc_url_raw( $args['result_url'] ?? '' ),
            'metadata'      => self::sanitize_parameters( $args['metadata'] ?? array() ),
            'created_at'    => current_time( 'mysql' ),
        );
        update_post_meta( $handoff_id, self::META_HISTORY, array_slice( $history, -self::MAX_HISTORY ) );
    }

    public static function handoff_status( $handoff_id ) {
        $status = sanitize_key( get_post_meta( $handoff_id, self::META_STATUS, true ) );
        return isset( self::handoff_status_options()[ $status ] ) ? $status : 'draft';
    }

    public static function handoff_data( $handoff_id, $include_private = false ) {
        $handoff_id = absint( $handoff_id );
        $post = get_post( $handoff_id );
        if ( ! $post || self::HANDOFF_POST_TYPE !== $post->post_type ) {
            return array();
        }
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        if ( $include_private && ! current_user_can( 'edit_post', $project_id ) ) {
            return array();
        }
        $target = sanitize_key( get_post_meta( $handoff_id, self::META_TARGET_PRODUCT, true ) );
        $registry = self::product_registry( true );
        $status = self::handoff_status( $handoff_id );
        $type = sanitize_key( get_post_meta( $handoff_id, self::META_TYPE, true ) );
        $data = array(
            'schema'          => self::HANDOFF_SCHEMA,
            'id'              => $handoff_id,
            'uuid'            => sanitize_text_field( get_post_meta( $handoff_id, self::META_UUID, true ) ),
            'title'           => $post->post_title,
            'project_id'      => $project_id,
            'project_identity'=> self::project_identity( $project_id, false ),
            'source_product'  => sanitize_key( get_post_meta( $handoff_id, self::META_SOURCE_PRODUCT, true ) ?: 'knowledge-library' ),
            'target_product'  => $target,
            'target_label'    => $registry[ $target ]['label'] ?? $target,
            'handoff_type'    => $type,
            'handoff_label'   => $registry[ $target ]['types'][ $type ] ?? $type,
            'direction'       => sanitize_key( get_post_meta( $handoff_id, self::META_DIRECTION, true ) ?: 'outbound' ),
            'status'          => $status,
            'status_label'    => self::handoff_status_options()[ $status ],
            'recipient_url'   => esc_url_raw( get_post_meta( $handoff_id, self::META_RECIPIENT_URL, true ) ),
            'return_url'      => esc_url_raw( get_post_meta( $handoff_id, self::META_RETURN_URL, true ) ),
            'result_url'      => esc_url_raw( get_post_meta( $handoff_id, self::META_RESULT_URL, true ) ),
            'bundle_checksum' => sanitize_text_field( get_post_meta( $handoff_id, self::META_BUNDLE_CHECKSUM, true ) ),
            'created_at'      => sanitize_text_field( get_post_meta( $handoff_id, self::META_CREATED_AT, true ) ),
            'created_by'      => absint( get_post_meta( $handoff_id, self::META_CREATED_BY, true ) ),
            'updated_at'      => sanitize_text_field( get_post_meta( $handoff_id, self::META_UPDATED_AT, true ) ),
            'updated_by'      => absint( get_post_meta( $handoff_id, self::META_UPDATED_BY, true ) ),
        );
        if ( $include_private ) {
            $data['request'] = get_post_meta( $handoff_id, self::META_REQUEST, true );
            $data['sections'] = get_post_meta( $handoff_id, self::META_SECTIONS, true );
            $data['bundle'] = get_post_meta( $handoff_id, self::META_BUNDLE, true );
            $data['history'] = get_post_meta( $handoff_id, self::META_HISTORY, true );
            $data['token_expires'] = absint( get_post_meta( $handoff_id, self::META_TOKEN_EXPIRES, true ) );
            $data['download_urls'] = array(
                'json'     => self::bundle_download_url( $handoff_id, 'json' ),
                'markdown' => self::bundle_download_url( $handoff_id, 'markdown' ),
                'zip'      => self::bundle_download_url( $handoff_id, 'zip' ),
            );
        }
        return $data;
    }

    public static function handoff_id_by_uuid( $uuid ) {
        $uuid = sanitize_text_field( $uuid );
        if ( ! self::valid_uuid( $uuid ) ) {
            return 0;
        }
        $ids = get_posts(
            array(
                'post_type'      => self::HANDOFF_POST_TYPE,
                'post_status'    => 'private',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::META_UUID,
                'meta_value'     => $uuid,
            )
        );
        return $ids ? absint( $ids[0] ) : 0;
    }

    public static function handoffs_for_project( $project_id, $limit = 50 ) {
        $ids = get_posts(
            array(
                'post_type'      => self::HANDOFF_POST_TYPE,
                'post_status'    => 'private',
                'posts_per_page' => max( 1, min( self::MAX_HANDOFFS_PER_PROJECT, absint( $limit ) ) ),
                'fields'         => 'ids',
                'meta_key'       => self::META_PROJECT_ID,
                'meta_value'     => absint( $project_id ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        $items = array();
        foreach ( $ids as $id ) {
            $data = self::handoff_data( $id, true );
            if ( $data ) {
                $items[] = $data;
            }
        }
        return $items;
    }

    public static function bundle_download_url( $handoff_id, $format = 'json' ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action'     => 'sc_library_v340_download_bundle',
                    'handoff_id' => absint( $handoff_id ),
                    'format'     => sanitize_key( $format ),
                ),
                admin_url( 'admin-post.php' )
            ),
            'sc_library_download_bundle_' . absint( $handoff_id )
        );
    }

    public function register_workspace() {
        add_submenu_page(
            'sc-library',
            __( 'Cross-Product Handoffs', 'sustainable-catalyst-library' ),
            __( 'Research Handoffs', 'sustainable-catalyst-library' ),
            'edit_posts',
            'sc-library-cross-product-handoffs',
            array( $this, 'render_workspace' )
        );
    }

    public function register_project_meta_box() {
        add_meta_box(
            'sc-cross-product-research-handoffs',
            __( 'Cross-Product Research Workspace Handoffs', 'sustainable-catalyst-library' ),
            array( $this, 'render_project_meta_box' ),
            SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function register_public_assets() {
        wp_register_style(
            'sc-library-cross-product-handoffs',
            SC_LIBRARY_URL . 'assets/css/sc-library-cross-product-handoffs.css',
            array( 'sc-library-connected-research' ),
            self::VERSION
        );
        wp_register_script(
            'sc-library-cross-product-handoffs',
            SC_LIBRARY_URL . 'assets/js/sc-library-cross-product-handoffs.js',
            array(),
            self::VERSION,
            true
        );
    }

    public function enqueue_admin_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }
        $is_project = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === $screen->post_type;
        $is_workspace = false !== strpos( (string) $screen->id, 'sc-library-cross-product-handoffs' );
        if ( ! $is_project && ! $is_workspace ) {
            return;
        }
        wp_enqueue_style( 'sc-library-cross-product-handoffs' );
        wp_enqueue_script( 'sc-library-cross-product-handoffs' );
        wp_localize_script(
            'sc-library-cross-product-handoffs',
            'SCLibraryCrossProductHandoffs',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sc_library_cross_product_v340' ),
                'strings' => array(
                    'working'       => __( 'Working…', 'sustainable-catalyst-library' ),
                    'created'       => __( 'Handoff created.', 'sustainable-catalyst-library' ),
                    'tokenRotated'  => __( 'Delivery link created. Copy it now; the raw token is not stored.', 'sustainable-catalyst-library' ),
                    'statusUpdated' => __( 'Handoff status updated.', 'sustainable-catalyst-library' ),
                    'migrationRun'  => __( 'Project identity migration batch complete.', 'sustainable-catalyst-library' ),
                    'copy'          => __( 'Copy', 'sustainable-catalyst-library' ),
                    'copied'        => __( 'Copied', 'sustainable-catalyst-library' ),
                    'confirmToken'  => __( 'Rotate the delivery token? Any previous delivery link will stop working.', 'sustainable-catalyst-library' ),
                ),
            )
        );
    }

    public function render_project_meta_box( $post ) {
        $identity = self::project_identity( $post->ID, true );
        $registry = self::product_registry();
        $handoffs = self::handoffs_for_project( $post->ID, 20 );
        ?>
        <div class="sc-handoff-project-panel" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
            <section class="sc-handoff-identity-card">
                <div>
                    <p class="sc-connected-kicker"><?php esc_html_e( 'Stable platform project identity', 'sustainable-catalyst-library' ); ?></p>
                    <h3><?php echo esc_html( $identity['urn'] ?? '' ); ?></h3>
                    <p><?php esc_html_e( 'This UUID remains stable when the project title, slug, or URL changes.', 'sustainable-catalyst-library' ); ?></p>
                </div>
                <button type="button" class="button" data-sc-copy-value="<?php echo esc_attr( $identity['uuid'] ?? '' ); ?>"><?php esc_html_e( 'Copy UUID', 'sustainable-catalyst-library' ); ?></button>
            </section>

            <section class="sc-handoff-creator">
                <h3><?php esc_html_e( 'Create a typed workspace handoff', 'sustainable-catalyst-library' ); ?></h3>
                <div class="sc-handoff-field-grid">
                    <label><span><?php esc_html_e( 'Target product', 'sustainable-catalyst-library' ); ?></span><select data-sc-handoff-product><?php foreach ( $registry as $key => $product ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $product['label'] ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Handoff type', 'sustainable-catalyst-library' ); ?></span><select data-sc-handoff-type><?php $first = reset( $registry ); foreach ( (array) ( $first['types'] ?? array() ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e( 'Initial status', 'sustainable-catalyst-library' ); ?></span><select data-sc-handoff-status><option value="draft"><?php esc_html_e( 'Draft', 'sustainable-catalyst-library' ); ?></option><option value="ready"><?php esc_html_e( 'Ready and issue delivery link', 'sustainable-catalyst-library' ); ?></option></select></label>
                    <label><span><?php esc_html_e( 'Requested output', 'sustainable-catalyst-library' ); ?></span><input type="text" data-sc-handoff-output placeholder="<?php esc_attr_e( 'Model, report, experiment, briefing…', 'sustainable-catalyst-library' ); ?>"></label>
                </div>
                <label><span><?php esc_html_e( 'Instructions and context', 'sustainable-catalyst-library' ); ?></span><textarea rows="4" data-sc-handoff-instructions></textarea></label>
                <fieldset class="sc-handoff-sections"><legend><?php esc_html_e( 'Bundle sections', 'sustainable-catalyst-library' ); ?></legend><?php foreach ( self::bundle_section_options() as $key => $label ) : ?><label><input type="checkbox" value="<?php echo esc_attr( $key ); ?>" data-sc-handoff-section checked> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset>
                <button type="button" class="button button-primary" data-sc-create-handoff><?php esc_html_e( 'Create Handoff', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-handoff-message aria-live="polite"></div>
                <div data-sc-handoff-delivery></div>
            </section>

            <section>
                <h3><?php esc_html_e( 'Handoff history', 'sustainable-catalyst-library' ); ?></h3>
                <?php echo self::render_handoff_table( $handoffs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
            <script type="application/json" data-sc-handoff-products><?php echo wp_json_encode( $registry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        </div>
        <?php
    }

    private static function render_handoff_table( $handoffs ) {
        if ( ! $handoffs ) {
            return '<p>' . esc_html__( 'No cross-product handoffs have been created for this project.', 'sustainable-catalyst-library' ) . '</p>';
        }
        ob_start();
        ?>
        <div class="sc-handoff-table-wrap">
            <table class="widefat striped sc-handoff-table">
                <thead><tr><th><?php esc_html_e( 'Handoff', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Product', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Updated', 'sustainable-catalyst-library' ); ?></th><th><?php esc_html_e( 'Actions', 'sustainable-catalyst-library' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $handoffs as $handoff ) : ?>
                        <tr data-handoff-id="<?php echo esc_attr( $handoff['id'] ); ?>">
                            <td><strong><?php echo esc_html( $handoff['title'] ); ?></strong><small><?php echo esc_html( $handoff['uuid'] ); ?></small></td>
                            <td><?php echo esc_html( $handoff['target_label'] ); ?><small><?php echo esc_html( $handoff['handoff_label'] ); ?></small></td>
                            <td><select data-sc-handoff-status-select><?php foreach ( self::handoff_status_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $handoff['status'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><button type="button" class="button button-small" data-sc-update-handoff-status><?php esc_html_e( 'Update', 'sustainable-catalyst-library' ); ?></button></td>
                            <td><?php echo esc_html( $handoff['updated_at'] ?: $handoff['created_at'] ); ?></td>
                            <td class="sc-handoff-actions"><button type="button" class="button button-small" data-sc-rotate-handoff-token><?php esc_html_e( 'Delivery Link', 'sustainable-catalyst-library' ); ?></button><a class="button button-small" href="<?php echo esc_url( $handoff['download_urls']['json'] ); ?>"><?php esc_html_e( 'JSON', 'sustainable-catalyst-library' ); ?></a><a class="button button-small" href="<?php echo esc_url( $handoff['download_urls']['zip'] ); ?>"><?php esc_html_e( 'Bundle ZIP', 'sustainable-catalyst-library' ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_workspace() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $registry = self::product_registry( true );
        $state = self::migration_state();
        $recent_ids = get_posts(
            array(
                'post_type'      => self::HANDOFF_POST_TYPE,
                'post_status'    => 'private',
                'posts_per_page' => 100,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        $handoffs = array();
        $counts = array_fill_keys( array_keys( self::handoff_status_options() ), 0 );
        foreach ( $recent_ids as $id ) {
            $data = self::handoff_data( $id, true );
            if ( $data ) {
                $handoffs[] = $data;
                $counts[ $data['status'] ] = ( $counts[ $data['status'] ] ?? 0 ) + 1;
            }
        }
        ?>
        <div class="wrap sc-handoff-workspace">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Knowledge Library v3.4.0', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php esc_html_e( 'Cross-Product Research Workspace Handoffs', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Move structured project context into Research Lab, Workbench, Decision Studio, Research Librarian, and Site Intelligence while preserving stable identity, provenance, return links, status, and history.', 'sustainable-catalyst-library' ); ?></p>

            <div class="sc-handoff-metrics">
                <article><strong><?php echo esc_html( count( $handoffs ) ); ?></strong><span><?php esc_html_e( 'Recent handoffs', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['in-progress'] + $counts['accepted'] + $counts['opened'] ); ?></strong><span><?php esc_html_e( 'Active', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['completed'] ); ?></strong><span><?php esc_html_e( 'Completed', 'sustainable-catalyst-library' ); ?></span></article>
                <article><strong><?php echo esc_html( $counts['failed'] ); ?></strong><span><?php esc_html_e( 'Failed', 'sustainable-catalyst-library' ); ?></span></article>
            </div>

            <section class="sc-handoff-migration" data-sc-handoff-migration>
                <div><h2><?php esc_html_e( 'Stable project identity migration', 'sustainable-catalyst-library' ); ?></h2><p><?php echo esc_html( sprintf( __( '%1$d of %2$d projects processed', 'sustainable-catalyst-library' ), $state['processed'], $state['total'] ) ); ?></p><p><strong><?php esc_html_e( 'Status:', 'sustainable-catalyst-library' ); ?></strong> <span data-sc-handoff-migration-state><?php echo esc_html( ucfirst( $state['status'] ) ); ?></span></p></div>
                <button type="button" class="button button-primary" data-sc-run-handoff-migration><?php esc_html_e( 'Run Next Batch', 'sustainable-catalyst-library' ); ?></button>
                <div data-sc-handoff-migration-message aria-live="polite"></div>
            </section>

            <section><h2><?php esc_html_e( 'Product registry and delivery routes', 'sustainable-catalyst-library' ); ?></h2><?php echo self::render_settings_form( $registry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
            <section><h2><?php esc_html_e( 'Recent handoffs', 'sustainable-catalyst-library' ); ?></h2><?php echo self::render_handoff_table( $handoffs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
        </div>
        <?php
    }

    private static function render_settings_form( $registry ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            $html = '<div class="sc-handoff-products">';
            foreach ( $registry as $product ) {
                $html .= '<article><h3>' . esc_html( $product['label'] ) . '</h3><p>' . esc_html( $product['description'] ) . '</p><code>' . esc_html( $product['route'] ) . '</code></article>';
            }
            return $html . '</div>';
        }
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-handoff-products-form">
            <input type="hidden" name="action" value="sc_library_v340_save_settings">
            <?php wp_nonce_field( 'sc_library_v340_save_settings' ); ?>
            <div class="sc-handoff-products">
                <?php foreach ( $registry as $key => $product ) : ?>
                    <article>
                        <h3><?php echo esc_html( $product['label'] ); ?></h3>
                        <p><?php echo esc_html( $product['description'] ); ?></p>
                        <label><input type="checkbox" name="products[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $product['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'sustainable-catalyst-library' ); ?></label>
                        <label><span><?php esc_html_e( 'Launch route', 'sustainable-catalyst-library' ); ?></span><input type="url" name="products[<?php echo esc_attr( $key ); ?>][route]" value="<?php echo esc_attr( $product['route'] ); ?>"></label>
                        <label><span><?php esc_html_e( 'Delivery mode', 'sustainable-catalyst-library' ); ?></span><select name="products[<?php echo esc_attr( $key ); ?>][delivery]"><option value="signed-rest" <?php selected( $product['delivery'], 'signed-rest' ); ?>><?php esc_html_e( 'Expiring signed REST link', 'sustainable-catalyst-library' ); ?></option><option value="local-action" <?php selected( $product['delivery'], 'local-action' ); ?>><?php esc_html_e( 'Local WordPress action', 'sustainable-catalyst-library' ); ?></option><option value="export-only" <?php selected( $product['delivery'], 'export-only' ); ?>><?php esc_html_e( 'Export only', 'sustainable-catalyst-library' ); ?></option></select></label>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php submit_button( __( 'Save Product Routes', 'sustainable-catalyst-library' ) ); ?>
        </form>
        <?php
        return ob_get_clean();
    }

    public function ajax_create_handoff() {
        $this->verify_ajax( 'edit_posts' );
        $project_id = absint( wp_unslash( $_POST['project_id'] ?? 0 ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot create a handoff for this project.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $status = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
        $result = self::create_handoff(
            $project_id,
            array(
                'target_product'  => wp_unslash( $_POST['target_product'] ?? '' ),
                'handoff_type'    => wp_unslash( $_POST['handoff_type'] ?? '' ),
                'status'          => $status,
                'sections'        => wp_unslash( $_POST['sections'] ?? array() ),
                'issue_token'     => 'ready' === $status,
                'request'         => array(
                    'instructions'     => wp_unslash( $_POST['instructions'] ?? '' ),
                    'requested_output' => wp_unslash( $_POST['requested_output'] ?? '' ),
                ),
            )
        );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'handoff' => $result ) );
    }

    public function ajax_rotate_token() {
        $this->verify_ajax( 'edit_posts' );
        $handoff_id = absint( wp_unslash( $_POST['handoff_id'] ?? 0 ) );
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot create a delivery link for this handoff.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::issue_delivery_token( $handoff_id, absint( wp_unslash( $_POST['days'] ?? self::TOKEN_TTL_DAYS ) ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'delivery' => $result ) );
    }

    public function ajax_update_status() {
        $this->verify_ajax( 'edit_posts' );
        $handoff_id = absint( wp_unslash( $_POST['handoff_id'] ?? 0 ) );
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot update this handoff.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $result = self::update_handoff_status(
            $handoff_id,
            wp_unslash( $_POST['status'] ?? '' ),
            array( 'note' => wp_unslash( $_POST['note'] ?? '' ) )
        );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'handoff' => $result ) );
    }

    public function ajax_run_migration() {
        $this->verify_ajax( 'manage_options' );
        $result = self::run_identity_migration_batch( self::MIGRATION_BATCH );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), absint( $result->get_error_data( 'status' ) ?: 400 ) );
        }
        wp_send_json_success( array( 'state' => $result ) );
    }

    private function verify_ajax( $capability ) {
        check_ajax_referer( 'sc_library_cross_product_v340', 'nonce' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'sustainable-catalyst-library' ) ), 403 );
        }
    }

    public function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change product routes.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_v340_save_settings' );
        $raw = wp_unslash( $_POST['products'] ?? array() );
        $registry = self::base_product_registry();
        $settings = array();
        foreach ( $registry as $key => $product ) {
            $item = is_array( $raw[ $key ] ?? null ) ? $raw[ $key ] : array();
            $delivery = sanitize_key( $item['delivery'] ?? 'signed-rest' );
            if ( ! in_array( $delivery, array( 'signed-rest', 'local-action', 'export-only' ), true ) ) {
                $delivery = 'signed-rest';
            }
            $settings[ $key ] = array(
                'enabled'  => ! empty( $item['enabled'] ),
                'route'    => esc_url_raw( $item['route'] ?? $product['default_route'] ),
                'delivery' => $delivery,
            );
        }
        update_option( self::OPTION_PRODUCT_SETTINGS, $settings, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'sc-library-cross-product-handoffs', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_download_bundle() {
        $handoff_id = absint( wp_unslash( $_GET['handoff_id'] ?? 0 ) );
        check_admin_referer( 'sc_library_download_bundle_' . $handoff_id );
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        if ( ! current_user_can( 'edit_post', $project_id ) ) {
            wp_die( esc_html__( 'You cannot download this research bundle.', 'sustainable-catalyst-library' ) );
        }
        $format = sanitize_key( wp_unslash( $_GET['format'] ?? 'json' ) );
        $bundle = get_post_meta( $handoff_id, self::META_BUNDLE, true );
        if ( ! self::validate_bundle( $bundle ) ) {
            wp_die( esc_html__( 'The stored research bundle is invalid.', 'sustainable-catalyst-library' ) );
        }
        nocache_headers();
        $uuid = sanitize_text_field( get_post_meta( $handoff_id, self::META_UUID, true ) );
        if ( 'markdown' === $format ) {
            header( 'Content-Type: text/markdown; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="research-handoff-' . $uuid . '.md"' );
            echo self::bundle_markdown( $bundle ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }
        if ( 'zip' === $format && class_exists( 'ZipArchive' ) ) {
            self::stream_bundle_zip( $handoff_id, $bundle );
            exit;
        }
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="research-handoff-' . $uuid . '.json"' );
        echo wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function stream_bundle_zip( $handoff_id, $bundle ) {
        $uuid = sanitize_text_field( get_post_meta( $handoff_id, self::META_UUID, true ) );
        $temp = wp_tempnam( 'sc-handoff-' . $uuid . '.zip' );
        if ( ! $temp ) {
            return;
        }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            @unlink( $temp );
            return;
        }
        $files = self::bundle_files( $bundle );
        foreach ( $files as $name => $content ) {
            $zip->addFromString( $name, $content );
        }
        $zip->close();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="research-handoff-' . $uuid . '.zip"' );
        header( 'Content-Length: ' . filesize( $temp ) );
        readfile( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        @unlink( $temp );
    }

    public static function bundle_files( $bundle ) {
        $context = is_array( $bundle['context'] ?? null ) ? $bundle['context'] : array();
        $files = array(
            'manifest.json' => wp_json_encode(
                array(
                    'schema'           => $bundle['schema'],
                    'bundle_id'        => $bundle['bundle_id'],
                    'handoff_uuid'     => $bundle['handoff_uuid'],
                    'source_product'   => $bundle['source_product'],
                    'target_product'   => $bundle['target_product'],
                    'handoff_type'     => $bundle['handoff_type'],
                    'target_contract'  => $bundle['target_contract'],
                    'project_identity' => $bundle['project_identity'],
                    'sections'         => $bundle['sections'],
                    'checksum'         => self::bundle_checksum( $bundle ),
                    'provenance'       => $bundle['provenance'],
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            'handoff.json' => wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            'README.md'    => self::bundle_markdown( $bundle ),
        );
        $section_files = array(
            'project'         => 'project.json',
            'bibliography'    => 'bibliography.json',
            'evidence_packet' => 'evidence-packet.json',
            'semantic_context'=> 'semantic-context.json',
            'pathways'        => 'pathways.json',
            'pathway_recommendations' => 'pathway-recommendations.json',
            'source_integrity'=> 'source-integrity.json',
            'dataset_references' => 'dataset-references.json',
        );
        foreach ( $section_files as $key => $filename ) {
            if ( array_key_exists( $key, $context ) ) {
                $files[ $filename ] = wp_json_encode( $context[ $key ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            }
        }
        $files['adapter.json'] = wp_json_encode( $bundle['adapter'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        return $files;
    }

    public static function bundle_markdown( $bundle ) {
        $identity = $bundle['project_identity'] ?? array();
        $lines = array(
            '# Sustainable Catalyst Cross-Product Research Bundle',
            '',
            '- Bundle ID: `' . sanitize_text_field( $bundle['bundle_id'] ?? '' ) . '`',
            '- Handoff UUID: `' . sanitize_text_field( $bundle['handoff_uuid'] ?? '' ) . '`',
            '- Target: `' . sanitize_text_field( $bundle['target_product'] ?? '' ) . '`',
            '- Contract: `' . sanitize_text_field( $bundle['target_contract'] ?? '' ) . '`',
            '- Project: ' . sanitize_text_field( $identity['title'] ?? '' ),
            '- Project URN: `' . sanitize_text_field( $identity['urn'] ?? '' ) . '`',
            '- Generated: ' . sanitize_text_field( $bundle['provenance']['generated_at'] ?? '' ),
            '',
            '## Request',
            '',
            sanitize_textarea_field( $bundle['request']['instructions'] ?? '' ),
            '',
            '## Included sections',
            '',
        );
        foreach ( (array) ( $bundle['sections'] ?? array() ) as $section ) {
            $lines[] = '- ' . sanitize_text_field( $section );
        }
        $lines[] = '';
        $lines[] = '## Return';
        $lines[] = '';
        $lines[] = '- Project: ' . esc_url_raw( $bundle['return']['project_url'] ?? '' );
        $lines[] = '- Status endpoint: ' . esc_url_raw( $bundle['return']['rest_route'] ?? '' );
        return implode( "\n", $lines ) . "\n";
    }

    public function schedule_identity_migration() {
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
            self::run_identity_migration_batch( self::MIGRATION_BATCH );
        }
    }

    public static function run_identity_migration_batch( $limit = self::MIGRATION_BATCH ) {
        global $wpdb;
        if ( get_transient( self::TRANSIENT_MIGRATION_LOCK ) ) {
            return new WP_Error( 'identity_migration_locked', __( 'Another project identity migration batch is already running.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        set_transient( self::TRANSIENT_MIGRATION_LOCK, wp_generate_uuid4(), self::LOCK_SECONDS );
        $state = self::migration_state();
        $state['status'] = 'running';
        $state['started_at'] = $state['started_at'] ?: current_time( 'mysql' );
        $state['updated_at'] = current_time( 'mysql' );
        try {
            $post_type = SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE;
            $statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $count_sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})";
            $state['total'] = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, array_merge( array( $post_type ), $statuses ) ) ) );
            $query_sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d";
            $params = array_merge( array( $post_type ), $statuses, array( absint( $state['cursor'] ), max( 1, min( 100, absint( $limit ) ) ) ) );
            $ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $query_sql, $params ) ) );
            if ( ! $ids ) {
                $state['status'] = 'complete';
                $state['completed_at'] = current_time( 'mysql' );
                update_option( self::OPTION_MIGRATION_STATE, $state, false );
                delete_transient( self::TRANSIENT_MIGRATION_LOCK );
                return $state;
            }
            foreach ( $ids as $project_id ) {
                $result = self::ensure_project_identity( $project_id );
                if ( is_wp_error( $result ) ) {
                    $state['failures'][] = array(
                        'project_id' => $project_id,
                        'message'    => $result->get_error_message(),
                        'time'       => current_time( 'mysql' ),
                    );
                    $state['failures'] = array_slice( $state['failures'], -100 );
                }
                $state['cursor'] = $project_id;
                $state['processed']++;
            }
            $state['status'] = $state['processed'] >= $state['total'] ? 'complete' : 'pending';
            if ( 'complete' === $state['status'] ) {
                $state['completed_at'] = current_time( 'mysql' );
            }
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
        } catch ( Throwable $error ) {
            $state['status'] = 'failed';
            $state['last_error'] = sanitize_text_field( $error->getMessage() );
            $state['updated_at'] = current_time( 'mysql' );
            update_option( self::OPTION_MIGRATION_STATE, $state, false );
            delete_transient( self::TRANSIENT_MIGRATION_LOCK );
            return new WP_Error( 'identity_migration_failed', $error->getMessage(), array( 'status' => 500 ) );
        }
        delete_transient( self::TRANSIENT_MIGRATION_LOCK );
        return $state;
    }

    private static function migration_state() {
        $state = get_option( self::OPTION_MIGRATION_STATE, array() );
        return wp_parse_args( is_array( $state ) ? $state : array(), self::default_migration_state() );
    }

    private static function default_migration_state() {
        return array(
            'version'      => self::VERSION,
            'status'       => 'pending',
            'cursor'       => 0,
            'total'        => 0,
            'processed'    => 0,
            'failures'     => array(),
            'last_error'   => '',
            'started_at'   => '',
            'updated_at'   => '',
            'completed_at' => '',
        );
    }

    public function cleanup_deleted_project_or_handoff( $post_id ) {
        $post_type = get_post_type( $post_id );
        if ( SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === $post_type ) {
            $handoff_ids = get_posts(
                array(
                    'post_type'      => self::HANDOFF_POST_TYPE,
                    'post_status'    => 'private',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_key'       => self::META_PROJECT_ID,
                    'meta_value'     => absint( $post_id ),
                )
            );
            foreach ( $handoff_ids as $handoff_id ) {
                wp_delete_post( $handoff_id, true );
            }
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/platform/products',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_products' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/platform-identity',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_project_identity' ),
                'permission_callback' => array( $this, 'rest_can_read_project' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/projects/(?P<id>\d+)/handoffs',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_project_handoffs' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'rest_create_handoff' ),
                    'permission_callback' => array( $this, 'rest_can_edit_project' ),
                ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/handoffs/(?P<uuid>[a-f0-9-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_handoff' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/handoffs/(?P<uuid>[a-f0-9-]+)/status',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_handoff_status' ),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/handoffs/(?P<uuid>[a-f0-9-]+)/token',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_rotate_token' ),
                'permission_callback' => array( $this, 'rest_can_edit_handoff' ),
            )
        );
        register_rest_route(
            self::API_NAMESPACE,
            '/handoff-migration',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'rest_migration_state' ),
                    'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'rest_run_migration' ),
                    'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
                ),
            )
        );
    }

    public function rest_products() {
        return rest_ensure_response(
            array(
                'schema'   => self::PRODUCT_REGISTRY_SCHEMA,
                'products' => array_values( self::product_registry() ),
            )
        );
    }

    public function rest_project_identity( WP_REST_Request $request ) {
        return rest_ensure_response( self::project_identity( absint( $request['id'] ), current_user_can( 'edit_post', absint( $request['id'] ) ) ) );
    }

    public function rest_project_handoffs( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'items' => self::handoffs_for_project( absint( $request['id'] ), 100 ) ) );
    }

    public function rest_create_handoff( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $result = self::create_handoff( absint( $request['id'] ), $payload );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_get_handoff( WP_REST_Request $request ) {
        $handoff_id = self::handoff_id_by_uuid( $request['uuid'] );
        if ( ! $handoff_id ) {
            return new WP_Error( 'handoff_not_found', __( 'The handoff could not be found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        $authorized = current_user_can( 'edit_post', $project_id ) || self::validate_delivery_token( $handoff_id, $token );
        if ( ! $authorized ) {
            return new WP_Error( 'handoff_forbidden', __( 'A valid delivery token or project permission is required.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        if ( $token && 'sent' === self::handoff_status( $handoff_id ) ) {
            self::update_handoff_status( $handoff_id, 'opened', array( 'token_actor' => true, 'note' => __( 'Delivery bundle opened.', 'sustainable-catalyst-library' ) ) );
        }
        return rest_ensure_response( self::handoff_data_for_delivery( $handoff_id ) );
    }

    private static function handoff_data_for_delivery( $handoff_id ) {
        $data = self::handoff_data( $handoff_id, false );
        $data['bundle'] = get_post_meta( $handoff_id, self::META_BUNDLE, true );
        $data['history'] = get_post_meta( $handoff_id, self::META_HISTORY, true );
        $data['token_expires'] = absint( get_post_meta( $handoff_id, self::META_TOKEN_EXPIRES, true ) );
        return $data;
    }

    public function rest_update_handoff_status( WP_REST_Request $request ) {
        $handoff_id = self::handoff_id_by_uuid( $request['uuid'] );
        if ( ! $handoff_id ) {
            return new WP_Error( 'handoff_not_found', __( 'The handoff could not be found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : array();
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        $token = sanitize_text_field( $request->get_param( 'token' ) ?: ( $payload['token'] ?? '' ) );
        $token_actor = ! current_user_can( 'edit_post', $project_id );
        if ( $token_actor && ! self::validate_delivery_token( $handoff_id, $token ) ) {
            return new WP_Error( 'handoff_forbidden', __( 'A valid delivery token or project permission is required.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $result = self::update_handoff_status(
            $handoff_id,
            $payload['status'] ?? '',
            array(
                'note'          => $payload['note'] ?? '',
                'result_url'    => $payload['result_url'] ?? '',
                'metadata'      => $payload['metadata'] ?? array(),
                'actor_product' => $payload['product'] ?? get_post_meta( $handoff_id, self::META_TARGET_PRODUCT, true ),
                'token_actor'   => $token_actor,
            )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_rotate_token( WP_REST_Request $request ) {
        $handoff_id = self::handoff_id_by_uuid( $request['uuid'] );
        $result = self::issue_delivery_token( $handoff_id, absint( $request->get_param( 'days' ) ?: self::TOKEN_TTL_DAYS ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_migration_state() {
        return rest_ensure_response( self::migration_state() );
    }

    public function rest_run_migration( WP_REST_Request $request ) {
        $result = self::run_identity_migration_batch( absint( $request->get_param( 'limit' ) ?: self::MIGRATION_BATCH ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function rest_can_read_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return current_user_can( 'edit_post', $project_id ) || self::project_is_public( $project_id );
    }

    public function rest_can_edit_project( WP_REST_Request $request ) {
        $project_id = absint( $request['id'] );
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id ) && current_user_can( 'edit_post', $project_id );
    }

    public function rest_can_edit_handoff( WP_REST_Request $request ) {
        $handoff_id = self::handoff_id_by_uuid( $request['uuid'] );
        $project_id = absint( get_post_meta( $handoff_id, self::META_PROJECT_ID, true ) );
        return $handoff_id && current_user_can( 'edit_post', $project_id );
    }

    public function protect_private_rest_responses( $response, $server, $request ) {
        if ( ! $response instanceof WP_REST_Response ) {
            return $response;
        }
        $route = (string) $request->get_route();
        if ( false === strpos( $route, '/' . self::API_NAMESPACE . '/handoffs/' ) && false === strpos( $route, '/handoff-migration' ) && false === strpos( $route, '/projects/' ) ) {
            return $response;
        }
        if ( is_user_logged_in() || $request->get_param( 'token' ) || false !== strpos( $route, '/handoff-migration' ) ) {
            $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0' );
            $response->header( 'Pragma', 'no-cache' );
            $response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
            $response->header( 'Vary', 'Cookie, Authorization' );
        }
        return $response;
    }

    public function shortcode_project_workspace( $atts ) {
        $atts = shortcode_atts( array( 'project' => '', 'limit' => 20 ), $atts, 'sc_project_handoff_workspace' );
        $project_id = self::resolve_project_id( $atts['project'] );
        if ( ! $project_id || ! current_user_can( 'edit_post', $project_id ) ) {
            return '';
        }
        nocache_headers();
        wp_enqueue_style( 'sc-library-cross-product-handoffs' );
        wp_enqueue_script( 'sc-library-cross-product-handoffs' );
        ob_start();
        ?>
        <section class="sc-public-handoff-workspace" data-project-id="<?php echo esc_attr( $project_id ); ?>">
            <header><p class="sc-connected-kicker"><?php esc_html_e( 'Private project workspace', 'sustainable-catalyst-library' ); ?></p><h2><?php esc_html_e( 'Cross-Product Research Handoffs', 'sustainable-catalyst-library' ); ?></h2></header>
            <?php echo self::render_handoff_table( self::handoffs_for_project( $project_id, absint( $atts['limit'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function shortcode_project_identity( $atts ) {
        $atts = shortcode_atts( array( 'project' => '' ), $atts, 'sc_platform_project_identity' );
        $project_id = self::resolve_project_id( $atts['project'] );
        if ( ! $project_id ) {
            return '';
        }
        $private = current_user_can( 'edit_post', $project_id );
        if ( ! $private && ! self::project_is_public( $project_id ) ) {
            return '';
        }
        $identity = self::project_identity( $project_id, $private );
        if ( ! $identity ) {
            return '';
        }
        wp_enqueue_style( 'sc-library-cross-product-handoffs' );
        ob_start();
        ?>
        <section class="sc-platform-project-identity">
            <p class="sc-connected-kicker"><?php esc_html_e( 'Stable platform identity', 'sustainable-catalyst-library' ); ?></p>
            <h2><?php echo esc_html( $identity['title'] ); ?></h2>
            <dl><div><dt><?php esc_html_e( 'Project UUID', 'sustainable-catalyst-library' ); ?></dt><dd><code><?php echo esc_html( $identity['uuid'] ); ?></code></dd></div><div><dt><?php esc_html_e( 'Project URN', 'sustainable-catalyst-library' ); ?></dt><dd><code><?php echo esc_html( $identity['urn'] ); ?></code></dd></div></dl>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function filter_research_librarian_context( $context, $project_id = 0, $args = array() ) {
        $project_id = absint( $project_id );
        if ( ! $project_id || SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
            return $context;
        }
        $bundle = self::build_bundle(
            $project_id,
            'research-librarian',
            sanitize_key( $args['handoff_type'] ?? 'research-context' ),
            array(
                'sections' => $args['sections'] ?? array( 'project', 'bibliography', 'evidence', 'semantic', 'pathways', 'integrity' ),
                'request'  => $args['request'] ?? array(),
            )
        );
        if ( is_wp_error( $bundle ) ) {
            return $context;
        }
        $context = is_array( $context ) ? $context : array();
        $context['platform_project_identity'] = $bundle['project_identity'];
        $context['knowledge_library_bundle'] = $bundle;
        return $context;
    }

    private static function resolve_project_id( $value ) {
        if ( is_numeric( $value ) && SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( absint( $value ) ) ) {
            return absint( $value );
        }
        $post = get_page_by_path( sanitize_title( $value ), OBJECT, SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE );
        return $post ? absint( $post->ID ) : 0;
    }

    private static function project_is_public( $project_id ) {
        return SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE === get_post_type( $project_id )
            && 'publish' === get_post_status( $project_id )
            && 'public' === get_post_meta( $project_id, SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY, true );
    }

    private static function valid_uuid( $uuid ) {
        return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $uuid );
    }

    private static function allowed_key( $value, $options, $fallback ) {
        $value = sanitize_key( $value );
        return array_key_exists( $value, $options ) ? $value : $fallback;
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
            'sc-library handoffs products',
            static function () {
                WP_CLI::log( wp_json_encode( self::product_registry( true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs identity',
            static function ( $args ) {
                $project_id = absint( $args[0] ?? 0 );
                $identity = self::ensure_project_identity( $project_id );
                if ( is_wp_error( $identity ) ) {
                    WP_CLI::error( $identity->get_error_message() );
                }
                WP_CLI::log( wp_json_encode( $identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs migrate-identities',
            static function ( $args, $assoc_args ) {
                $result = self::run_identity_migration_batch( absint( $assoc_args['limit'] ?? self::MIGRATION_BATCH ) );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs create',
            static function ( $args, $assoc_args ) {
                $project_id = absint( $args[0] ?? 0 );
                $target = sanitize_key( $args[1] ?? '' );
                $type = sanitize_key( $args[2] ?? '' );
                $result = self::create_handoff(
                    $project_id,
                    array(
                        'target_product' => $target,
                        'handoff_type'   => $type,
                        'status'         => sanitize_key( $assoc_args['status'] ?? 'ready' ),
                        'issue_token'    => ! empty( $assoc_args['issue-token'] ),
                        'request'        => array( 'instructions' => $assoc_args['instructions'] ?? '' ),
                    )
                );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs show',
            static function ( $args ) {
                $handoff_id = self::handoff_id_by_uuid( $args[0] ?? '' );
                $data = self::handoff_data( $handoff_id, true );
                if ( ! $data ) {
                    WP_CLI::error( 'Handoff not found.' );
                }
                WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs status',
            static function ( $args, $assoc_args ) {
                $handoff_id = self::handoff_id_by_uuid( $args[0] ?? '' );
                $result = self::update_handoff_status( $handoff_id, $args[1] ?? '', array( 'note' => $assoc_args['note'] ?? '' ) );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                }
                WP_CLI::success( wp_json_encode( $result ) );
            }
        );
        WP_CLI::add_command(
            'sc-library handoffs bundle',
            static function ( $args, $assoc_args ) {
                $handoff_id = self::handoff_id_by_uuid( $args[0] ?? '' );
                $bundle = get_post_meta( $handoff_id, self::META_BUNDLE, true );
                if ( ! self::validate_bundle( $bundle ) ) {
                    WP_CLI::error( 'Bundle not found or invalid.' );
                }
                $format = sanitize_key( $assoc_args['format'] ?? 'json' );
                WP_CLI::log( 'markdown' === $format ? self::bundle_markdown( $bundle ) : wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            }
        );
    }
}
