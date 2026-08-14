<?php
/**
 * Unified Research Projects & Source Bundles — v4.3.30.
 *
 * Extends the canonical sc_research_project record with account-owned,
 * references-only links to private Library records and project-level source
 * bundles. No linked source, personal-library item, saved search, document,
 * course, pathway, queue item, or binary attachment is copied into a bundle.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Unified_Research_Projects_Source_Bundles {
    public const VERSION = '4.3.30';
    public const SCHEMA = 'sc-library-unified-research-project/1.0';
    public const LINK_SCHEMA = 'sc-library-project-reference-link/1.0';
    public const BUNDLE_SCHEMA = 'sc-library-source-bundle/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-projects';
    public const NONCE_ACTION = 'sc_library_unified_projects_v4330';

    public const META_ENABLED = '_sc_project_unified_v4330';
    public const META_LINKS = '_sc_project_unified_links_v4330';
    public const META_BUNDLES = '_sc_project_source_bundles_v4330';
    public const META_UPDATED_AT = '_sc_project_unified_updated_at_v4330';

    public const MAX_PROJECTS_PER_USER = 50;
    public const MAX_LINKS_PER_PROJECT = 300;
    public const MAX_BUNDLES_PER_PROJECT = 60;
    public const MAX_LINKS_PER_BUNDLE = 120;

    private const REFERENCE_FAMILIES = array(
        'source'            => 'Citation Studio / Research Source',
        'personal_library'  => 'My Library item',
        'saved_search'      => 'Saved search',
        'watchlist'         => 'Watchlist item',
        'research_queue'    => 'Research queue item',
        'source_collection' => 'Citation Studio collection',
        'research_document' => 'Research document draft',
        'course'            => 'Saved course',
        'pathway'           => 'Knowledge Pathway',
        'external'          => 'External reference',
    );

    private const LINK_ROLES = array(
        'reference'  => 'Reference',
        'background' => 'Background',
        'evidence'   => 'Evidence',
        'method'     => 'Method',
        'dataset'    => 'Dataset / data source',
        'context'    => 'Context',
        'learning'   => 'Learning',
        'follow_up'  => 'Follow-up',
    );

    private const PROJECT_STATUSES = array(
        'active'    => 'Active',
        'on_hold'   => 'On hold',
        'complete'  => 'Complete',
        'archived'  => 'Archived',
    );

    private const BUNDLE_PURPOSES = array(
        'working_set' => 'Working set',
        'evidence'    => 'Evidence set',
        'review'      => 'Review set',
        'learning'    => 'Learning set',
        'briefing'    => 'Briefing set',
        'handoff'     => 'Handoff preparation',
    );

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_unified_research_projects', array( $this, 'shortcode' ) );

        add_action( 'wp_ajax_sc_library_v4330_create_project', array( $this, 'ajax_create_project' ) );
        add_action( 'wp_ajax_sc_library_v4330_update_project', array( $this, 'ajax_update_project' ) );
        add_action( 'wp_ajax_sc_library_v4330_add_link', array( $this, 'ajax_add_link' ) );
        add_action( 'wp_ajax_sc_library_v4330_delete_link', array( $this, 'ajax_delete_link' ) );
        add_action( 'wp_ajax_sc_library_v4330_create_bundle', array( $this, 'ajax_create_bundle' ) );
        add_action( 'wp_ajax_sc_library_v4330_delete_bundle', array( $this, 'ajax_delete_bundle' ) );

        add_filter( 'sc_library_unified_project_state', array( $this, 'filter_project_state' ), 10, 3 );
        add_filter( 'sc_library_source_bundle_manifest', array( $this, 'filter_bundle_manifest' ), 10, 4 );
        add_action( 'sc_library_link_project_reference', array( $this, 'action_link_reference' ), 10, 3 );
        add_action( 'sc_library_create_source_bundle', array( $this, 'action_create_bundle' ), 10, 3 );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-unified-projects-v4330',
            SC_LIBRARY_URL . 'assets/css/sc-library-unified-projects-v4330.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-unified-projects-v4330',
            SC_LIBRARY_URL . 'assets/js/sc-library-unified-projects-v4330.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public static function project_contract() {
        return array(
            'schema'                         => self::SCHEMA,
            'canonical_project_type'         => 'sc_research_project',
            'project_identity'               => 'sc-platform-project-identity/1.0',
            'record_owner'                   => 'wordpress-post-author',
            'visibility'                     => 'private-by-default',
            'workspace_account_continuity'   => true,
            'references_only'                => true,
            'duplicate_linked_records'        => false,
            'copy_source_content'             => false,
            'copy_private_binary_files'       => false,
            'automatic_publication'           => false,
            'automatic_workspace_write'       => false,
        );
    }

    public static function bundle_contract() {
        return array(
            'schema'                    => self::BUNDLE_SCHEMA,
            'stable_bundle_id'          => true,
            'stable_bundle_urn'         => true,
            'payload_strategy'          => 'references-only',
            'project_scoped'            => true,
            'private_by_default'        => true,
            'resolved_on_read'          => true,
            'missing_references_retained'=> true,
            'binary_attachments_copied' => false,
        );
    }

    public static function reference_families() { return self::REFERENCE_FAMILIES; }
    public static function link_roles() { return self::LINK_ROLES; }
    public static function bundle_purposes() { return self::BUNDLE_PURPOSES; }
    public static function project_statuses() { return self::PROJECT_STATUSES; }

    private static function project_post_type() {
        return class_exists( 'SC_Library_Citation_Source_Manager' )
            ? SC_Library_Citation_Source_Manager::PROJECT_POST_TYPE
            : 'sc_research_project';
    }

    private static function source_post_type() {
        return class_exists( 'SC_Library_Citation_Source_Manager' )
            ? SC_Library_Citation_Source_Manager::SOURCE_POST_TYPE
            : 'sc_research_source';
    }

    private static function now() { return current_time( 'mysql', true ); }

    private static function clean_text( $value, $limit = 180 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function clean_textarea( $value, $limit = 4000 ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function enum_value( $value, $allowed, $fallback ) {
        $value = sanitize_key( (string) $value );
        return array_key_exists( $value, $allowed ) ? $value : $fallback;
    }

    private static function new_uuid() {
        return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'sc-', true );
    }

    private static function array_meta( $project_id, $key, $limit ) {
        $records = get_post_meta( absint( $project_id ), $key, true );
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        return array_slice( $records, -absint( $limit ) );
    }

    private static function write_array_meta( $project_id, $key, $records, $limit ) {
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        $records = array_slice( $records, -absint( $limit ) );
        update_post_meta( absint( $project_id ), $key, $records );
        update_post_meta( absint( $project_id ), self::META_ENABLED, '1' );
        update_post_meta( absint( $project_id ), self::META_UPDATED_AT, self::now() );
        return true;
    }

    public static function user_owns_project( $project_id, $user_id ) {
        $project_id = absint( $project_id );
        $user_id = absint( $user_id );
        $post = $project_id ? get_post( $project_id ) : null;
        return $post instanceof WP_Post
            && self::project_post_type() === $post->post_type
            && $user_id > 0
            && absint( $post->post_author ) === $user_id;
    }

    public static function projects_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }
        $ids = get_posts(
            array(
                'post_type'      => self::project_post_type(),
                'post_status'    => array( 'draft', 'private', 'publish', 'pending' ),
                'author'         => $user_id,
                'posts_per_page' => self::MAX_PROJECTS_PER_USER,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'fields'         => 'ids',
            )
        );
        return array_values( array_map( 'absint', $ids ) );
    }

    private static function project_status( $project_id ) {
        $key = class_exists( 'SC_Library_Citation_Source_Manager' )
            ? SC_Library_Citation_Source_Manager::META_PROJECT_STATUS
            : '_sc_project_status';
        return self::enum_value( get_post_meta( $project_id, $key, true ), self::PROJECT_STATUSES, 'active' );
    }

    private static function project_visibility( $project_id ) {
        $key = class_exists( 'SC_Library_Citation_Source_Manager' )
            ? SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY
            : '_sc_project_visibility';
        return 'public' === get_post_meta( $project_id, $key, true ) ? 'public' : 'private';
    }

    private static function project_question( $project_id ) {
        $key = class_exists( 'SC_Library_Connected_Research_Environment' )
            ? SC_Library_Connected_Research_Environment::META_RESEARCH_QUESTION
            : '_sc_project_research_question';
        return (string) get_post_meta( $project_id, $key, true );
    }

    private static function stable_project_identity( $project_id ) {
        if ( class_exists( 'SC_Library_Cross_Product_Research_Handoffs' ) ) {
            $identity = SC_Library_Cross_Product_Research_Handoffs::project_identity( absint( $project_id ), true );
            if ( is_array( $identity ) && ! empty( $identity['uuid'] ) ) { return $identity; }
        }
        return array(
            'schema'       => 'sc-platform-project-identity/1.0',
            'wordpress_id' => absint( $project_id ),
            'uuid'         => '',
            'urn'          => '',
        );
    }

    public static function links_for_project( $project_id ) {
        $out = array();
        foreach ( self::array_meta( $project_id, self::META_LINKS, self::MAX_LINKS_PER_PROJECT ) as $raw ) {
            $link = self::sanitize_link( $raw, false );
            if ( $link['id'] && $link['family'] && $link['ref_id'] ) { $out[] = $link; }
        }
        return $out;
    }

    public static function bundles_for_project( $project_id ) {
        $out = array();
        $valid_links = array_column( self::links_for_project( $project_id ), 'id' );
        foreach ( self::array_meta( $project_id, self::META_BUNDLES, self::MAX_BUNDLES_PER_PROJECT ) as $raw ) {
            $bundle = self::sanitize_bundle( $raw, false );
            if ( ! $bundle['bundle_id'] || ! $bundle['title'] ) { continue; }
            $bundle['link_ids'] = array_values( array_filter( $bundle['link_ids'], static function ( $id ) use ( $valid_links ) { return in_array( $id, $valid_links, true ); } ) );
            $out[] = $bundle;
        }
        return $out;
    }

    private static function sanitize_link( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_uuid(); }
        return array(
            'schema'     => self::LINK_SCHEMA,
            'id'         => $id,
            'family'     => self::enum_value( $input['family'] ?? '', self::REFERENCE_FAMILIES, 'external' ),
            'ref_id'     => self::clean_text( $input['ref_id'] ?? '', 320 ),
            'label'      => self::clean_text( $input['label'] ?? '', 220 ),
            'role'       => self::enum_value( $input['role'] ?? '', self::LINK_ROLES, 'reference' ),
            'url'        => esc_url_raw( (string) ( $input['url'] ?? '' ) ),
            'note'       => self::clean_textarea( $input['note'] ?? '', 2000 ),
            'created_at' => self::clean_text( $input['created_at'] ?? '', 40 ),
            'created_by' => absint( $input['created_by'] ?? 0 ),
        );
    }

    private static function sanitize_bundle( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $bundle_id = self::clean_text( $input['bundle_id'] ?? '', 80 );
        if ( $generate_id && '' === $bundle_id ) { $bundle_id = self::new_uuid(); }
        $link_ids = array_values( array_unique( array_filter( array_map( static function ( $value ) { return self::clean_text( $value, 80 ); }, (array) ( $input['link_ids'] ?? array() ) ) ) ) );
        return array(
            'schema'      => self::BUNDLE_SCHEMA,
            'bundle_id'   => $bundle_id,
            'urn'         => $bundle_id ? 'urn:sc:source-bundle:' . $bundle_id : '',
            'title'       => self::clean_text( $input['title'] ?? '', 180 ),
            'purpose'     => self::enum_value( $input['purpose'] ?? '', self::BUNDLE_PURPOSES, 'working_set' ),
            'description' => self::clean_textarea( $input['description'] ?? '', 2400 ),
            'link_ids'    => array_slice( $link_ids, 0, self::MAX_LINKS_PER_BUNDLE ),
            'created_at'  => self::clean_text( $input['created_at'] ?? '', 40 ),
            'created_by'  => absint( $input['created_by'] ?? 0 ),
            'updated_at'  => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    private static function record_by_id( $records, $id ) {
        foreach ( (array) $records as $record ) {
            if ( is_array( $record ) && (string) ( $record['id'] ?? '' ) === (string) $id ) { return $record; }
        }
        return null;
    }

    public static function resolve_reference( $user_id, $family, $ref_id, $url = '', $fallback_label = '' ) {
        $user_id = absint( $user_id );
        $family = self::enum_value( $family, self::REFERENCE_FAMILIES, 'external' );
        $ref_id = self::clean_text( $ref_id, 320 );
        $fallback_label = self::clean_text( $fallback_label, 220 );
        $result = array(
            'family'   => $family,
            'ref_id'   => $ref_id,
            'resolved' => false,
            'label'    => $fallback_label,
            'url'      => esc_url_raw( $url ),
            'status'   => 'unresolved',
        );

        if ( 'source' === $family ) {
            $source_id = absint( $ref_id );
            if ( $source_id && self::source_post_type() === get_post_type( $source_id ) ) {
                $author = absint( get_post_field( 'post_author', $source_id ) );
                $public = 'publish' === get_post_status( $source_id );
                if ( $public || $author === $user_id ) {
                    $result['resolved'] = true;
                    $result['label'] = get_the_title( $source_id ) ?: $fallback_label;
                    $result['url'] = $public ? (string) get_permalink( $source_id ) : '';
                    $result['status'] = $public ? 'public-source' : 'private-account-source';
                }
            }
            return $result;
        }

        if ( 'personal_library' === $family && class_exists( 'SC_Library_Personal_Collections_Recommendations' ) ) {
            $record = self::record_by_id( SC_Library_Personal_Collections_Recommendations::items_for_user( $user_id ), $ref_id );
            if ( $record ) {
                $result['resolved'] = true; $result['label'] = $record['title'] ?? $fallback_label; $result['url'] = $record['url'] ?? ''; $result['status'] = 'private-account-record';
            }
            return $result;
        }

        if ( class_exists( 'SC_Library_Saved_Searches_Watchlists_Queue' ) ) {
            if ( 'saved_search' === $family ) {
                $record = self::record_by_id( SC_Library_Saved_Searches_Watchlists_Queue::searches_for_user( $user_id ), $ref_id );
                if ( $record ) { $result['resolved'] = true; $result['label'] = $record['label'] ?? $record['query'] ?? $fallback_label; $result['status'] = 'private-account-record'; }
                return $result;
            }
            if ( 'watchlist' === $family ) {
                $record = self::record_by_id( SC_Library_Saved_Searches_Watchlists_Queue::watchlists_for_user( $user_id ), $ref_id );
                if ( $record ) { $result['resolved'] = true; $result['label'] = $record['label'] ?? $fallback_label; $result['url'] = $record['url'] ?? ''; $result['status'] = 'private-account-record'; }
                return $result;
            }
            if ( 'research_queue' === $family ) {
                $record = self::record_by_id( SC_Library_Saved_Searches_Watchlists_Queue::queue_for_user( $user_id ), $ref_id );
                if ( $record ) { $result['resolved'] = true; $result['label'] = $record['title'] ?? $fallback_label; $result['url'] = $record['url'] ?? ''; $result['status'] = 'private-account-record'; }
                return $result;
            }
        }

        if ( 'source_collection' === $family ) {
            $collections = get_user_meta( $user_id, 'sc_library_source_collections_v4322', true );
            $collections = is_array( $collections ) ? array_values( array_filter( array_map( 'sanitize_text_field', $collections ) ) ) : array();
            if ( in_array( $ref_id, $collections, true ) ) {
                $result['resolved'] = true; $result['label'] = $ref_id; $result['status'] = 'private-account-collection';
            }
            return $result;
        }

        if ( 'research_document' === $family ) {
            $documents = get_user_meta( $user_id, 'sc_library_research_documents_v4323', true );
            $record = self::record_by_id( is_array( $documents ) ? $documents : array(), $ref_id );
            if ( $record ) { $result['resolved'] = true; $result['label'] = $record['title'] ?? $fallback_label; $result['status'] = 'private-account-document'; }
            return $result;
        }

        if ( 'course' === $family ) {
            $plan = get_user_meta( $user_id, 'sc_library_course_plan_v4321', true );
            $plan = is_array( $plan ) ? $plan : array();
            if ( array_key_exists( $ref_id, $plan ) ) {
                $result['resolved'] = true; $result['label'] = $fallback_label ?: sprintf( __( 'Saved course %s', 'sustainable-catalyst-library' ), $ref_id ); $result['status'] = 'private-learning-plan-record';
            }
            return $result;
        }

        if ( 'pathway' === $family ) {
            $pathway_id = absint( $ref_id );
            $post_type = class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : 'sc_knowledge_path';
            if ( $pathway_id && $post_type === get_post_type( $pathway_id ) && 'publish' === get_post_status( $pathway_id ) ) {
                $result['resolved'] = true; $result['label'] = get_the_title( $pathway_id ) ?: $fallback_label; $result['url'] = (string) get_permalink( $pathway_id ); $result['status'] = 'public-pathway';
            }
            return $result;
        }

        if ( 'external' === $family ) {
            $external_url = esc_url_raw( $url ?: $ref_id );
            if ( $external_url ) {
                $result['ref_id'] = $ref_id ?: $external_url;
                $result['url'] = $external_url;
                $result['resolved'] = true;
                $result['label'] = $fallback_label ?: $external_url;
                $result['status'] = 'external-reference';
            }
        }
        return $result;
    }

    public static function reference_catalog_for_user( $user_id ) {
        $catalog = array();
        $add = static function ( $family, $ref_id, $label, $url = '' ) use ( &$catalog ) {
            if ( '' === (string) $ref_id || '' === trim( (string) $label ) ) { return; }
            $catalog[] = array( 'family' => $family, 'ref_id' => (string) $ref_id, 'label' => (string) $label, 'url' => (string) $url );
        };

        $source_ids = get_posts( array( 'post_type'=>self::source_post_type(), 'post_status'=>array('draft','private','publish'), 'author'=>absint($user_id), 'posts_per_page'=>100, 'orderby'=>'modified', 'order'=>'DESC', 'fields'=>'ids' ) );
        foreach ( $source_ids as $id ) { $add( 'source', $id, get_the_title( $id ) ?: 'Research Source ' . $id, 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '' ); }

        if ( class_exists( 'SC_Library_Personal_Collections_Recommendations' ) ) {
            foreach ( SC_Library_Personal_Collections_Recommendations::items_for_user( $user_id ) as $record ) { $add( 'personal_library', $record['id'] ?? '', $record['title'] ?? '', $record['url'] ?? '' ); }
        }
        if ( class_exists( 'SC_Library_Saved_Searches_Watchlists_Queue' ) ) {
            foreach ( SC_Library_Saved_Searches_Watchlists_Queue::searches_for_user( $user_id ) as $record ) { $add( 'saved_search', $record['id'] ?? '', $record['label'] ?? $record['query'] ?? '' ); }
            foreach ( SC_Library_Saved_Searches_Watchlists_Queue::watchlists_for_user( $user_id ) as $record ) { $add( 'watchlist', $record['id'] ?? '', $record['label'] ?? '', $record['url'] ?? '' ); }
            foreach ( SC_Library_Saved_Searches_Watchlists_Queue::queue_for_user( $user_id ) as $record ) { $add( 'research_queue', $record['id'] ?? '', $record['title'] ?? '', $record['url'] ?? '' ); }
        }
        $collections = get_user_meta( absint( $user_id ), 'sc_library_source_collections_v4322', true );
        foreach ( is_array( $collections ) ? $collections : array() as $name ) { $name = self::clean_text( $name, 80 ); if ( $name ) { $add( 'source_collection', $name, $name ); } }
        $documents = get_user_meta( absint( $user_id ), 'sc_library_research_documents_v4323', true );
        foreach ( is_array( $documents ) ? $documents : array() as $record ) { if ( is_array( $record ) ) { $add( 'research_document', $record['id'] ?? '', $record['title'] ?? '' ); } }
        $plan = get_user_meta( absint( $user_id ), 'sc_library_course_plan_v4321', true );
        foreach ( is_array( $plan ) ? $plan : array() as $course_id => $state ) { $add( 'course', $course_id, sprintf( 'Saved course: %s (%s)', $course_id, sanitize_key( $state ) ) ); }
        $pathway_type = class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ? SC_Library_Knowledge_Pathways_Article_Maps::PATHWAY_POST_TYPE : 'sc_knowledge_path';
        $pathway_ids = get_posts( array( 'post_type'=>$pathway_type, 'post_status'=>'publish', 'posts_per_page'=>60, 'orderby'=>'title', 'order'=>'ASC', 'fields'=>'ids' ) );
        foreach ( $pathway_ids as $id ) { $add( 'pathway', $id, get_the_title( $id ) ?: 'Knowledge Pathway ' . $id, get_permalink( $id ) ); }

        usort( $catalog, static function ( $a, $b ) { $family = strcmp( $a['family'], $b['family'] ); return 0 !== $family ? $family : strcasecmp( $a['label'], $b['label'] ); } );
        return array_slice( $catalog, 0, 500 );
    }

    public static function create_project_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_library_project_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) ); }
        if ( count( self::projects_for_user( $user_id ) ) >= self::MAX_PROJECTS_PER_USER ) { return new WP_Error( 'sc_library_project_limit', __( 'The private project limit has been reached.', 'sustainable-catalyst-library' ) ); }
        $title = self::clean_text( $input['title'] ?? '', 180 );
        if ( '' === $title ) { return new WP_Error( 'sc_library_project_title_required', __( 'Enter a project title.', 'sustainable-catalyst-library' ) ); }
        $question = self::clean_textarea( $input['research_question'] ?? '', 2000 );
        $project_id = wp_insert_post( array(
            'post_type'    => self::project_post_type(),
            'post_status'  => 'draft',
            'post_author'  => $user_id,
            'post_title'   => $title,
            'post_content' => '',
            'post_excerpt' => '',
        ), true );
        if ( is_wp_error( $project_id ) ) { return $project_id; }

        $visibility_key = class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::META_PROJECT_VISIBILITY : '_sc_project_visibility';
        $status_key = class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::META_PROJECT_STATUS : '_sc_project_status';
        $question_key = class_exists( 'SC_Library_Connected_Research_Environment' ) ? SC_Library_Connected_Research_Environment::META_RESEARCH_QUESTION : '_sc_project_research_question';
        update_post_meta( $project_id, $visibility_key, 'private' );
        update_post_meta( $project_id, $status_key, 'active' );
        update_post_meta( $project_id, $question_key, $question );
        update_post_meta( $project_id, self::META_ENABLED, '1' );
        update_post_meta( $project_id, self::META_UPDATED_AT, self::now() );
        self::stable_project_identity( $project_id );
        do_action( 'sc_library_unified_project_created', $project_id, $user_id );
        return self::project_state( $project_id, $user_id );
    }

    public static function update_project_for_user( $user_id, $project_id, $input ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $title = self::clean_text( $input['title'] ?? '', 180 );
        if ( $title ) { wp_update_post( array( 'ID'=>absint($project_id), 'post_title'=>$title ) ); }
        $status_key = class_exists( 'SC_Library_Citation_Source_Manager' ) ? SC_Library_Citation_Source_Manager::META_PROJECT_STATUS : '_sc_project_status';
        $question_key = class_exists( 'SC_Library_Connected_Research_Environment' ) ? SC_Library_Connected_Research_Environment::META_RESEARCH_QUESTION : '_sc_project_research_question';
        if ( array_key_exists( 'status', (array) $input ) ) { update_post_meta( $project_id, $status_key, self::enum_value( $input['status'], self::PROJECT_STATUSES, 'active' ) ); }
        if ( array_key_exists( 'research_question', (array) $input ) ) { update_post_meta( $project_id, $question_key, self::clean_textarea( $input['research_question'], 2000 ) ); }
        update_post_meta( $project_id, self::META_UPDATED_AT, self::now() );
        do_action( 'sc_library_unified_project_updated', absint( $project_id ), absint( $user_id ) );
        return self::project_state( $project_id, $user_id );
    }

    public static function add_link_for_user( $user_id, $project_id, $input ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $link = self::sanitize_link( $input, true );
        if ( '' === $link['ref_id'] ) { return new WP_Error( 'sc_library_project_ref_required', __( 'Choose a Library record or enter an external URL.', 'sustainable-catalyst-library' ) ); }
        $resolved = self::resolve_reference( $user_id, $link['family'], $link['ref_id'], $link['url'], $link['label'] );
        if ( ! $resolved['resolved'] ) { return new WP_Error( 'sc_library_project_ref_unresolved', __( 'That reference could not be resolved for this account.', 'sustainable-catalyst-library' ) ); }
        $link['label'] = $resolved['label'] ?: $link['label'];
        $link['url'] = $resolved['url'] ?: $link['url'];
        $link['created_at'] = self::now();
        $link['created_by'] = absint( $user_id );
        $links = self::links_for_project( $project_id );
        foreach ( $links as $existing ) {
            if ( $existing['family'] === $link['family'] && $existing['ref_id'] === $link['ref_id'] && $existing['role'] === $link['role'] ) {
                return new WP_Error( 'sc_library_project_ref_duplicate', __( 'That reference is already linked to this project in the same role.', 'sustainable-catalyst-library' ) );
            }
        }
        if ( count( $links ) >= self::MAX_LINKS_PER_PROJECT ) { return new WP_Error( 'sc_library_project_link_limit', __( 'The project reference limit has been reached.', 'sustainable-catalyst-library' ) ); }
        $links[] = $link;
        self::write_array_meta( $project_id, self::META_LINKS, $links, self::MAX_LINKS_PER_PROJECT );
        do_action( 'sc_library_project_reference_linked', $project_id, $link, $user_id );
        return $link;
    }

    public static function delete_link_for_user( $user_id, $project_id, $link_id ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $link_id = self::clean_text( $link_id, 80 );
        $links = self::links_for_project( $project_id );
        $next = array(); $deleted = null;
        foreach ( $links as $link ) { if ( $link['id'] === $link_id ) { $deleted = $link; continue; } $next[] = $link; }
        if ( ! $deleted ) { return new WP_Error( 'sc_library_project_link_not_found', __( 'That project reference was not found.', 'sustainable-catalyst-library' ) ); }
        self::write_array_meta( $project_id, self::META_LINKS, $next, self::MAX_LINKS_PER_PROJECT );
        $bundles = self::bundles_for_project( $project_id );
        foreach ( $bundles as &$bundle ) { $bundle['link_ids'] = array_values( array_diff( $bundle['link_ids'], array( $link_id ) ) ); $bundle['updated_at'] = self::now(); }
        unset( $bundle );
        self::write_array_meta( $project_id, self::META_BUNDLES, $bundles, self::MAX_BUNDLES_PER_PROJECT );
        do_action( 'sc_library_project_reference_unlinked', $project_id, $deleted, $user_id );
        return $deleted;
    }

    public static function create_bundle_for_user( $user_id, $project_id, $input ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $bundle = self::sanitize_bundle( $input, true );
        if ( '' === $bundle['title'] ) { return new WP_Error( 'sc_library_bundle_title_required', __( 'Enter a source-bundle title.', 'sustainable-catalyst-library' ) ); }
        $valid_link_ids = array_column( self::links_for_project( $project_id ), 'id' );
        $bundle['link_ids'] = array_values( array_filter( $bundle['link_ids'], static function ( $id ) use ( $valid_link_ids ) { return in_array( $id, $valid_link_ids, true ); } ) );
        if ( ! $bundle['link_ids'] ) { return new WP_Error( 'sc_library_bundle_links_required', __( 'Select at least one linked project reference.', 'sustainable-catalyst-library' ) ); }
        $bundle['created_at'] = self::now(); $bundle['updated_at'] = $bundle['created_at']; $bundle['created_by'] = absint( $user_id );
        $bundles = self::bundles_for_project( $project_id );
        if ( count( $bundles ) >= self::MAX_BUNDLES_PER_PROJECT ) { return new WP_Error( 'sc_library_bundle_limit', __( 'The source-bundle limit has been reached for this project.', 'sustainable-catalyst-library' ) ); }
        $bundles[] = $bundle;
        self::write_array_meta( $project_id, self::META_BUNDLES, $bundles, self::MAX_BUNDLES_PER_PROJECT );
        do_action( 'sc_library_source_bundle_created', $project_id, $bundle, $user_id );
        return self::bundle_manifest( $project_id, $bundle['bundle_id'], $user_id );
    }

    public static function delete_bundle_for_user( $user_id, $project_id, $bundle_id ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $bundle_id = self::clean_text( $bundle_id, 80 );
        $bundles = self::bundles_for_project( $project_id );
        $next = array(); $deleted = null;
        foreach ( $bundles as $bundle ) { if ( $bundle['bundle_id'] === $bundle_id ) { $deleted = $bundle; continue; } $next[] = $bundle; }
        if ( ! $deleted ) { return new WP_Error( 'sc_library_bundle_not_found', __( 'That source bundle was not found.', 'sustainable-catalyst-library' ) ); }
        self::write_array_meta( $project_id, self::META_BUNDLES, $next, self::MAX_BUNDLES_PER_PROJECT );
        do_action( 'sc_library_source_bundle_deleted', $project_id, $deleted, $user_id );
        return $deleted;
    }

    public static function project_state( $project_id, $user_id ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $post = get_post( $project_id );
        $links = self::links_for_project( $project_id );
        $resolved_links = array();
        foreach ( $links as $link ) {
            $resolved = self::resolve_reference( $user_id, $link['family'], $link['ref_id'], $link['url'], $link['label'] );
            $resolved_links[] = array_merge( $link, array( 'resolution'=>$resolved ) );
        }
        $bundles = self::bundles_for_project( $project_id );
        return array(
            'schema'            => self::SCHEMA,
            'version'           => self::VERSION,
            'project_id'        => absint( $project_id ),
            'title'             => $post ? $post->post_title : '',
            'owner_user_id'     => absint( $user_id ),
            'visibility'        => self::project_visibility( $project_id ),
            'status'            => self::project_status( $project_id ),
            'research_question' => self::project_question( $project_id ),
            'project_identity'  => self::stable_project_identity( $project_id ),
            'references'        => $resolved_links,
            'source_bundles'    => $bundles,
            'reference_count'   => count( $links ),
            'bundle_count'      => count( $bundles ),
            'contract'          => self::project_contract(),
            'bundle_contract'   => self::bundle_contract(),
            'modified_gmt'      => get_post_modified_time( 'c', true, $project_id ),
        );
    }

    public static function state_for_user( $user_id ) {
        $projects = array();
        foreach ( self::projects_for_user( $user_id ) as $project_id ) {
            $state = self::project_state( $project_id, $user_id );
            if ( ! is_wp_error( $state ) ) { $projects[] = $state; }
        }
        return array(
            'schema'            => 'sc-library-unified-research-projects-state/1.0',
            'version'           => self::VERSION,
            'visibility'        => 'private',
            'user_id'           => absint( $user_id ),
            'projects'          => $projects,
            'project_count'     => count( $projects ),
            'reference_catalog' => self::reference_catalog_for_user( $user_id ),
            'contract'          => self::project_contract(),
        );
    }

    public static function bundle_manifest( $project_id, $bundle_id, $user_id ) {
        if ( ! self::user_owns_project( $project_id, $user_id ) ) { return new WP_Error( 'sc_library_project_forbidden', __( 'That project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status'=>403 ) ); }
        $bundle_id = self::clean_text( $bundle_id, 80 );
        $bundle = null;
        foreach ( self::bundles_for_project( $project_id ) as $candidate ) { if ( $candidate['bundle_id'] === $bundle_id ) { $bundle = $candidate; break; } }
        if ( ! $bundle ) { return new WP_Error( 'sc_library_bundle_not_found', __( 'That source bundle was not found.', 'sustainable-catalyst-library' ), array( 'status'=>404 ) ); }
        $link_map = array();
        foreach ( self::links_for_project( $project_id ) as $link ) { $link_map[ $link['id'] ] = $link; }
        $references = array();
        foreach ( $bundle['link_ids'] as $link_id ) {
            if ( empty( $link_map[ $link_id ] ) ) { continue; }
            $link = $link_map[ $link_id ];
            $references[] = array(
                'link'       => $link,
                'resolution' => self::resolve_reference( $user_id, $link['family'], $link['ref_id'], $link['url'], $link['label'] ),
            );
        }
        $manifest = array(
            'schema'           => self::BUNDLE_SCHEMA,
            'version'          => self::VERSION,
            'bundle'           => $bundle,
            'project_identity' => self::stable_project_identity( $project_id ),
            'references'       => $references,
            'reference_count'  => count( $references ),
            'contract'         => self::bundle_contract(),
            'generated_at'     => self::now(),
        );
        $manifest['checksum_sha256'] = hash( 'sha256', wp_json_encode( $manifest ) );
        return $manifest;
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            array( 'methods'=>WP_REST_Server::READABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_state') ),
            array( 'methods'=>WP_REST_Server::CREATABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_create_project') ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array(
            array( 'methods'=>WP_REST_Server::READABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_project') ),
            array( 'methods'=>WP_REST_Server::EDITABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_update_project') ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/links', array(
            array( 'methods'=>WP_REST_Server::CREATABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_add_link') ),
            array( 'methods'=>WP_REST_Server::DELETABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_delete_link') ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/bundles', array(
            'methods'=>WP_REST_Server::CREATABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_create_bundle')
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/bundles/(?P<bundle_id>[A-Za-z0-9\-]+)', array(
            array( 'methods'=>WP_REST_Server::READABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_bundle') ),
            array( 'methods'=>WP_REST_Server::DELETABLE, 'permission_callback'=>array($this,'rest_signed_in'), 'callback'=>array($this,'rest_delete_bundle') ),
        ) );
    }

    public function rest_signed_in() { return is_user_logged_in(); }
    private static function rest_payload( $request ) { $payload = $request instanceof WP_REST_Request ? $request->get_json_params() : array(); return is_array( $payload ) ? $payload : array(); }
    public function rest_state() { return rest_ensure_response( self::state_for_user( get_current_user_id() ) ); }
    public function rest_create_project( WP_REST_Request $request ) { $result=self::create_project_for_user(get_current_user_id(),self::rest_payload($request)); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_project( WP_REST_Request $request ) { $result=self::project_state(absint($request['id']),get_current_user_id()); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_update_project( WP_REST_Request $request ) { $result=self::update_project_for_user(get_current_user_id(),absint($request['id']),self::rest_payload($request)); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_add_link( WP_REST_Request $request ) { $result=self::add_link_for_user(get_current_user_id(),absint($request['id']),self::rest_payload($request)); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_delete_link( WP_REST_Request $request ) { $result=self::delete_link_for_user(get_current_user_id(),absint($request['id']),$request->get_param('link_id')); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_create_bundle( WP_REST_Request $request ) { $result=self::create_bundle_for_user(get_current_user_id(),absint($request['id']),self::rest_payload($request)); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_bundle( WP_REST_Request $request ) { $result=self::bundle_manifest(absint($request['id']),$request['bundle_id'],get_current_user_id()); return is_wp_error($result)?$result:rest_ensure_response($result); }
    public function rest_delete_bundle( WP_REST_Request $request ) { $result=self::delete_bundle_for_user(get_current_user_id(),absint($request['id']),$request['bundle_id']); return is_wp_error($result)?$result:rest_ensure_response($result); }

    private function require_ajax_user() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message'=>__( 'Sign in to manage research projects.', 'sustainable-catalyst-library' ) ), 401 ); }
        return get_current_user_id();
    }
    private function ajax_result( $result, $success_message ) {
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            $status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
            wp_send_json_error( array( 'message'=>$result->get_error_message() ), $status ?: 400 );
        }
        wp_send_json_success( array( 'message'=>$success_message, 'record'=>$result ) );
    }
    public function ajax_create_project() { $user=$this->require_ajax_user(); $this->ajax_result(self::create_project_for_user($user,array('title'=>wp_unslash($_POST['title']??''),'research_question'=>wp_unslash($_POST['research_question']??''))),__( 'Research project created.', 'sustainable-catalyst-library' )); }
    public function ajax_update_project() { $user=$this->require_ajax_user(); $this->ajax_result(self::update_project_for_user($user,absint($_POST['project_id']??0),array('title'=>wp_unslash($_POST['title']??''),'research_question'=>wp_unslash($_POST['research_question']??''),'status'=>wp_unslash($_POST['status']??'active'))),__( 'Research project updated.', 'sustainable-catalyst-library' )); }
    public function ajax_add_link() {
        $user=$this->require_ajax_user();
        $reference_key=self::clean_text(wp_unslash($_POST['reference_key']??''),500); $family=''; $ref_id='';
        if ( false !== strpos( $reference_key, '|' ) ) { list($family,$ref_id)=array_pad(explode('|',$reference_key,2),2,''); }
        if ( 'external' === sanitize_key(wp_unslash($_POST['family']??'')) ) { $family='external'; $ref_id=esc_url_raw(wp_unslash($_POST['url']??'')); }
        $this->ajax_result(self::add_link_for_user($user,absint($_POST['project_id']??0),array('family'=>$family?:wp_unslash($_POST['family']??''),'ref_id'=>$ref_id?:wp_unslash($_POST['ref_id']??''),'label'=>wp_unslash($_POST['label']??''),'role'=>wp_unslash($_POST['role']??''),'url'=>wp_unslash($_POST['url']??''),'note'=>wp_unslash($_POST['note']??''))),__( 'Reference linked to project.', 'sustainable-catalyst-library' ));
    }
    public function ajax_delete_link() { $user=$this->require_ajax_user(); $this->ajax_result(self::delete_link_for_user($user,absint($_POST['project_id']??0),wp_unslash($_POST['link_id']??'')),__( 'Reference removed from project.', 'sustainable-catalyst-library' )); }
    public function ajax_create_bundle() { $user=$this->require_ajax_user(); $this->ajax_result(self::create_bundle_for_user($user,absint($_POST['project_id']??0),array('title'=>wp_unslash($_POST['title']??''),'purpose'=>wp_unslash($_POST['purpose']??''),'description'=>wp_unslash($_POST['description']??''),'link_ids'=>array_map('wp_unslash',(array)($_POST['link_ids']??array())))),__( 'Source bundle created.', 'sustainable-catalyst-library' )); }
    public function ajax_delete_bundle() { $user=$this->require_ajax_user(); $this->ajax_result(self::delete_bundle_for_user($user,absint($_POST['project_id']??0),wp_unslash($_POST['bundle_id']??'')),__( 'Source bundle removed.', 'sustainable-catalyst-library' )); }

    public function action_link_reference( $project_id, $input, $user_id ) { return self::add_link_for_user( $user_id, $project_id, is_array($input)?$input:array() ); }
    public function action_create_bundle( $project_id, $input, $user_id ) { return self::create_bundle_for_user( $user_id, $project_id, is_array($input)?$input:array() ); }
    public function filter_project_state( $state, $project_id, $user_id ) { $result=self::project_state($project_id,$user_id); return is_wp_error($result)?$state:$result; }
    public function filter_bundle_manifest( $manifest, $project_id, $bundle_id, $user_id ) { $result=self::bundle_manifest($project_id,$bundle_id,$user_id); return is_wp_error($result)?$manifest:$result; }

    private function sign_in_url() {
        $request_uri=(string)($_SERVER['REQUEST_URI']??'');
        $return=$request_uri&&0===strpos($request_uri,'/')?home_url($request_uri):home_url('/knowledge-libraries/');
        return wp_login_url($return);
    }

    private static function render_reference_select( $catalog, $project_id ) {
        if ( ! $catalog ) { return '<p class="sc-unified-projects__empty">' . esc_html__( 'No reusable account records are available yet. Save a source, My Library item, search, watchlist item, document, course, or queue item first—or add an external reference below.', 'sustainable-catalyst-library' ) . '</p>'; }
        $groups=array(); foreach($catalog as $item){$groups[$item['family']][]=$item;}
        $html='<label><span>'.esc_html__('Existing Library record','sustainable-catalyst-library').'</span><select name="reference_key" required><option value="">'.esc_html__('Choose a record…','sustainable-catalyst-library').'</option>';
        foreach(self::REFERENCE_FAMILIES as $family=>$label){ if(empty($groups[$family])||'external'===$family)continue; $html.='<optgroup label="'.esc_attr($label).'">'; foreach($groups[$family] as $item){$html.='<option value="'.esc_attr($family.'|'.$item['ref_id']).'">'.esc_html($item['label']).'</option>';}$html.='</optgroup>'; }
        $html.='</select></label>';
        return $html;
    }

    private static function render_links( $project_id, $links, $user_id ) {
        if(!$links){return '<p class="sc-unified-projects__empty">'.esc_html__('No references linked yet.','sustainable-catalyst-library').'</p>';}
        $html='<div class="sc-unified-projects__links">';
        foreach($links as $link){$resolved=self::resolve_reference($user_id,$link['family'],$link['ref_id'],$link['url'],$link['label']);$family=self::REFERENCE_FAMILIES[$link['family']]??$link['family'];$role=self::LINK_ROLES[$link['role']]??$link['role'];$html.='<article class="sc-unified-projects__link"><div><small>'.esc_html($family.' · '.$role).'</small><strong>'.esc_html($resolved['label']?:$link['label']).'</strong>';if($link['note']){$html.='<p>'.esc_html($link['note']).'</p>';}if(!$resolved['resolved']){$html.='<span class="sc-unified-projects__unresolved">'.esc_html__('Reference retained; underlying record is currently unresolved.','sustainable-catalyst-library').'</span>';}$html.='</div><button type="button" data-sc-project-delete-link="'.esc_attr($link['id']).'" data-project-id="'.esc_attr($project_id).'">'.esc_html__('Remove','sustainable-catalyst-library').'</button></article>';}
        return $html.'</div>';
    }

    private static function render_bundles( $project_id, $bundles ) {
        if(!$bundles){return '<p class="sc-unified-projects__empty">'.esc_html__('No source bundles yet.','sustainable-catalyst-library').'</p>';}
        $html='<div class="sc-unified-projects__bundles">';
        foreach($bundles as $bundle){$html.='<article class="sc-unified-projects__bundle"><div><small>'.esc_html(self::BUNDLE_PURPOSES[$bundle['purpose']]??$bundle['purpose']).' · '.esc_html(sprintf(_n('%d reference','%d references',count($bundle['link_ids']),'sustainable-catalyst-library'),count($bundle['link_ids']))).'</small><strong>'.esc_html($bundle['title']).'</strong>';if($bundle['description']){$html.='<p>'.esc_html($bundle['description']).'</p>';}$html.='<code>'.esc_html($bundle['urn']).'</code></div><button type="button" data-sc-project-delete-bundle="'.esc_attr($bundle['bundle_id']).'" data-project-id="'.esc_attr($project_id).'">'.esc_html__('Remove','sustainable-catalyst-library').'</button></article>';}
        return $html.'</div>';
    }

    private static function render_project_card( $project_id, $user_id, $catalog ) {
        $post=get_post($project_id); if(!$post)return '';
        $links=self::links_for_project($project_id); $bundles=self::bundles_for_project($project_id); $identity=self::stable_project_identity($project_id);
        ob_start(); ?>
        <article class="sc-unified-projects__project" data-sc-project="<?php echo esc_attr($project_id); ?>">
            <header><div><small><?php echo esc_html( self::PROJECT_STATUSES[self::project_status($project_id)] ?? 'Active' ); ?> · <?php esc_html_e('Private research project','sustainable-catalyst-library'); ?></small><h4><?php echo esc_html($post->post_title); ?></h4><?php if(self::project_question($project_id)): ?><p><?php echo esc_html(self::project_question($project_id)); ?></p><?php endif; ?></div><div class="sc-unified-projects__counts"><span><b><?php echo esc_html((string)count($links)); ?></b><?php esc_html_e('references','sustainable-catalyst-library'); ?></span><span><b><?php echo esc_html((string)count($bundles)); ?></b><?php esc_html_e('bundles','sustainable-catalyst-library'); ?></span></div></header>
            <?php if(!empty($identity['urn'])): ?><p class="sc-unified-projects__identity"><span><?php esc_html_e('Stable project identity','sustainable-catalyst-library'); ?></span><code><?php echo esc_html($identity['urn']); ?></code></p><?php endif; ?>
            <details><summary><?php esc_html_e('Project settings','sustainable-catalyst-library'); ?></summary><form data-sc-project-update><input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>"><label><span><?php esc_html_e('Title','sustainable-catalyst-library'); ?></span><input name="title" value="<?php echo esc_attr($post->post_title); ?>" required></label><label><span><?php esc_html_e('Research question','sustainable-catalyst-library'); ?></span><textarea name="research_question" rows="3"><?php echo esc_textarea(self::project_question($project_id)); ?></textarea></label><label><span><?php esc_html_e('Status','sustainable-catalyst-library'); ?></span><select name="status"><?php foreach(self::PROJECT_STATUSES as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected(self::project_status($project_id),$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e('Save project','sustainable-catalyst-library'); ?></button></form></details>
            <div class="sc-unified-projects__split">
                <section><h5><?php esc_html_e('Linked research','sustainable-catalyst-library'); ?></h5><?php echo self::render_links($project_id,$links,$user_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details><summary><?php esc_html_e('Link an existing Library record','sustainable-catalyst-library'); ?></summary><form data-sc-project-add-link><input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>"><?php echo self::render_reference_select($catalog,$project_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><label><span><?php esc_html_e('Role in project','sustainable-catalyst-library'); ?></span><select name="role"><?php foreach(self::LINK_ROLES as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e('Project note','sustainable-catalyst-library'); ?></span><textarea name="note" rows="2"></textarea></label><button type="submit"><?php esc_html_e('Link record','sustainable-catalyst-library'); ?></button></form></details>
                    <details><summary><?php esc_html_e('Add an external reference','sustainable-catalyst-library'); ?></summary><form data-sc-project-add-link><input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>"><input type="hidden" name="family" value="external"><label><span><?php esc_html_e('Label','sustainable-catalyst-library'); ?></span><input name="label" required></label><label><span><?php esc_html_e('URL','sustainable-catalyst-library'); ?></span><input name="url" type="url" required></label><label><span><?php esc_html_e('Role in project','sustainable-catalyst-library'); ?></span><select name="role"><?php foreach(self::LINK_ROLES as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e('Project note','sustainable-catalyst-library'); ?></span><textarea name="note" rows="2"></textarea></label><button type="submit"><?php esc_html_e('Link external reference','sustainable-catalyst-library'); ?></button></form></details>
                </section>
                <section><h5><?php esc_html_e('Source bundles','sustainable-catalyst-library'); ?></h5><?php echo self::render_bundles($project_id,$bundles); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if($links): ?><details><summary><?php esc_html_e('Create a source bundle','sustainable-catalyst-library'); ?></summary><form data-sc-project-create-bundle><input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>"><label><span><?php esc_html_e('Bundle title','sustainable-catalyst-library'); ?></span><input name="title" required></label><label><span><?php esc_html_e('Purpose','sustainable-catalyst-library'); ?></span><select name="purpose"><?php foreach(self::BUNDLE_PURPOSES as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><fieldset><legend><?php esc_html_e('Include project references','sustainable-catalyst-library'); ?></legend><?php foreach($links as $link): ?><label class="sc-unified-projects__check"><input type="checkbox" name="link_ids[]" value="<?php echo esc_attr($link['id']); ?>"><span><?php echo esc_html($link['label']); ?></span></label><?php endforeach; ?></fieldset><label><span><?php esc_html_e('Bundle description','sustainable-catalyst-library'); ?></span><textarea name="description" rows="2"></textarea></label><button type="submit"><?php esc_html_e('Create source bundle','sustainable-catalyst-library'); ?></button></form></details><?php else: ?><p class="sc-unified-projects__hint"><?php esc_html_e('Link at least one research record before creating a bundle.','sustainable-catalyst-library'); ?></p><?php endif; ?>
                </section>
            </div>
        </article><?php return ob_get_clean();
    }

    public function shortcode( $atts ) {
        $atts=shortcode_atts(array('title'=>__('Research Projects & Source Bundles','sustainable-catalyst-library')),$atts,'sc_unified_research_projects');
        wp_enqueue_style('sc-library-unified-projects-v4330'); wp_enqueue_script('sc-library-unified-projects-v4330');
        $signed_in=is_user_logged_in(); $user_id=$signed_in?get_current_user_id():0;
        wp_localize_script('sc-library-unified-projects-v4330','SCLibraryUnifiedProjects',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce(self::NONCE_ACTION),'signedIn'=>$signed_in,'schema'=>self::SCHEMA,'bundleSchema'=>self::BUNDLE_SCHEMA));
        ob_start(); ?>
        <section class="sc-unified-projects" data-sc-unified-projects="v4.3.30">
            <header class="sc-unified-projects__header"><div><p><?php esc_html_e('Private project continuity','sustainable-catalyst-library'); ?></p><h3><?php echo esc_html($atts['title']); ?></h3><span><?php esc_html_e('Use one research project to connect sources, My Library items, saved research, documents, courses, pathways, and follow-up work. Build reusable source bundles from those links without copying the underlying records.','sustainable-catalyst-library'); ?></span></div><aside><strong><?php esc_html_e('References, not duplicates','sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('A source bundle stores stable references and project notes. It does not duplicate source content or private files.','sustainable-catalyst-library'); ?></span></aside></header>
            <?php if(!$signed_in): ?><div class="sc-unified-projects__signin"><strong><?php esc_html_e('Sign in to create private research projects.','sustainable-catalyst-library'); ?></strong><a href="<?php echo esc_url($this->sign_in_url()); ?>"><?php esc_html_e('Sign in →','sustainable-catalyst-library'); ?></a></div>
            <?php else: $projects=self::projects_for_user($user_id); $catalog=self::reference_catalog_for_user($user_id); ?>
                <div class="sc-unified-projects__create"><form data-sc-project-create><label><span><?php esc_html_e('Project title','sustainable-catalyst-library'); ?></span><input name="title" required placeholder="<?php esc_attr_e('e.g. Urban heat resilience research','sustainable-catalyst-library'); ?>"></label><label><span><?php esc_html_e('Research question','sustainable-catalyst-library'); ?></span><textarea name="research_question" rows="2" placeholder="<?php esc_attr_e('What are you trying to understand, compare, or establish?','sustainable-catalyst-library'); ?>"></textarea></label><button type="submit"><?php esc_html_e('Create research project','sustainable-catalyst-library'); ?></button><span data-sc-project-status aria-live="polite"></span></form></div>
                <div class="sc-unified-projects__project-list"><?php if(!$projects): ?><p class="sc-unified-projects__empty"><strong><?php esc_html_e('No private research projects yet.','sustainable-catalyst-library'); ?></strong> <?php esc_html_e('Create one above, then link existing Library records instead of copying them into a new silo.','sustainable-catalyst-library'); ?></p><?php else: foreach($projects as $project_id){echo self::render_project_card($project_id,$user_id,$catalog);} endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </section><?php return ob_get_clean();
    }
}
