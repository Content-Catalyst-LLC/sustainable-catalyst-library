<?php
/**
 * Reading, Notebook & Annotation Workspace — v4.3.31.
 *
 * Adds account-persistent reading notebooks that can attach to canonical
 * Research Projects and references-only Source Bundles. Notes, excerpts, and
 * annotations are user-authored records; underlying Library sources and
 * private files are referenced rather than copied.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Reading_Notebook_Annotations {
    public const VERSION = '4.3.31';
    public const SCHEMA = 'sc-library-reading-notebook/1.0';
    public const NOTE_SCHEMA = 'sc-library-reading-note/1.0';
    public const ANNOTATION_SCHEMA = 'sc-library-source-annotation/1.0';
    public const POST_TYPE = 'sc_reading_notebook';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/reading-notebooks';
    public const NONCE_ACTION = 'sc_library_reading_notebooks_v4331';

    public const META_UUID = '_sc_reading_notebook_uuid_v4331';
    public const META_PROJECT_ID = '_sc_reading_notebook_project_id_v4331';
    public const META_BUNDLE_ID = '_sc_reading_notebook_bundle_id_v4331';
    public const META_STATUS = '_sc_reading_notebook_status_v4331';
    public const META_NOTES = '_sc_reading_notebook_notes_v4331';
    public const META_ANNOTATIONS = '_sc_reading_notebook_annotations_v4331';
    public const META_UPDATED_AT = '_sc_reading_notebook_updated_at_v4331';

    public const MAX_NOTEBOOKS_PER_USER = 60;
    public const MAX_NOTES_PER_NOTEBOOK = 300;
    public const MAX_ANNOTATIONS_PER_NOTEBOOK = 500;
    public const MAX_TAGS_PER_RECORD = 20;
    public const MAX_EXCERPT_CHARS = 4000;

    private const NOTE_TYPES = array(
        'note'        => 'Note',
        'excerpt'     => 'Reusable excerpt',
        'question'    => 'Research question',
        'observation' => 'Observation',
        'summary'     => 'User summary',
        'method'      => 'Method / procedure',
    );

    private const ANNOTATION_TYPES = array(
        'highlight' => 'Highlight',
        'excerpt'   => 'Excerpt',
        'comment'   => 'Comment',
        'bookmark'  => 'Bookmark',
    );

    private const LOCATOR_TYPES = array(
        'page'      => 'PDF / document page',
        'section'   => 'Section / heading',
        'timestamp' => 'Audio / video timestamp',
        'paragraph' => 'Paragraph / passage',
        'custom'    => 'Custom locator',
    );

    private const NOTEBOOK_STATUSES = array(
        'active'   => 'Active',
        'on_hold'  => 'On hold',
        'complete' => 'Complete',
        'archived' => 'Archived',
    );

    private const REFERENCE_FAMILIES = array(
        'source'            => 'Citation Studio / Research Source',
        'personal_library'  => 'My Library item',
        'saved_search'      => 'Saved search',
        'watchlist'         => 'Watchlist item',
        'research_queue'    => 'Research queue item',
        'source_collection' => 'Citation Studio collection',
        'research_document' => 'Research document',
        'course'            => 'Saved course',
        'pathway'           => 'Knowledge Pathway',
        'external'          => 'External reference',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ), 11 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_reading_notebook_workspace', array( $this, 'shortcode' ) );

        add_action( 'wp_ajax_sc_library_v4331_create_notebook', array( $this, 'ajax_create_notebook' ) );
        add_action( 'wp_ajax_sc_library_v4331_update_notebook', array( $this, 'ajax_update_notebook' ) );
        add_action( 'wp_ajax_sc_library_v4331_delete_notebook', array( $this, 'ajax_delete_notebook' ) );
        add_action( 'wp_ajax_sc_library_v4331_add_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_sc_library_v4331_update_note', array( $this, 'ajax_update_note' ) );
        add_action( 'wp_ajax_sc_library_v4331_delete_note', array( $this, 'ajax_delete_note' ) );
        add_action( 'wp_ajax_sc_library_v4331_add_annotation', array( $this, 'ajax_add_annotation' ) );
        add_action( 'wp_ajax_sc_library_v4331_update_annotation', array( $this, 'ajax_update_annotation' ) );
        add_action( 'wp_ajax_sc_library_v4331_delete_annotation', array( $this, 'ajax_delete_annotation' ) );

        add_filter( 'sc_library_reading_notebook_state', array( $this, 'filter_notebook_state' ), 10, 3 );
        add_filter( 'sc_library_reading_notebook_manifest', array( $this, 'filter_notebook_manifest' ), 10, 3 );
        add_action( 'sc_library_create_reading_note', array( $this, 'action_create_note' ), 10, 3 );
        add_action( 'sc_library_create_source_annotation', array( $this, 'action_create_annotation' ), 10, 3 );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name'          => __( 'Reading Notebooks', 'sustainable-catalyst-library' ),
                'singular_name' => __( 'Reading Notebook', 'sustainable-catalyst-library' ),
            ),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => array( 'title', 'author' ),
            'capability_type'      => 'post',
            'map_meta_cap'         => true,
        ) );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-reading-notebooks-v4331',
            SC_LIBRARY_URL . 'assets/css/sc-library-reading-notebooks-v4331.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-reading-notebooks-v4331',
            SC_LIBRARY_URL . 'assets/js/sc-library-reading-notebooks-v4331.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public static function contract() {
        return array(
            'schema'                       => self::SCHEMA,
            'record_owner'                 => 'wordpress-post-author',
            'visibility'                   => 'private-by-default',
            'same_library_workspace_account'=> true,
            'account_persistent'            => true,
            'legacy_browser_notebook_preserved' => true,
            'source_links_are_references'   => true,
            'copy_underlying_source_record' => false,
            'copy_private_binary_files'     => false,
            'user_authored_notes'           => true,
            'reusable_excerpts'             => true,
            'pdf_page_annotations'          => true,
            'automatic_ai_generation'       => false,
            'automatic_evidence_promotion'  => false,
            'automatic_publication'         => false,
            'automatic_workspace_write'     => false,
        );
    }

    private static function now() { return gmdate( 'c' ); }

    private static function new_uuid() {
        return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000,
            mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
    }

    private static function clean_text( $value, $limit = 240 ) {
        $value = sanitize_text_field( (string) $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function clean_textarea( $value, $limit = 8000 ) {
        $value = sanitize_textarea_field( (string) $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function enum_value( $value, $allowed, $fallback ) {
        $value = sanitize_key( (string) $value );
        return array_key_exists( $value, $allowed ) ? $value : $fallback;
    }

    private static function clean_tags( $value ) {
        if ( is_string( $value ) ) { $value = preg_split( '/[,\n]+/', $value ); }
        $tags = array();
        foreach ( (array) $value as $tag ) {
            $tag = self::clean_text( $tag, 60 );
            if ( '' !== $tag && ! in_array( $tag, $tags, true ) ) { $tags[] = $tag; }
            if ( count( $tags ) >= self::MAX_TAGS_PER_RECORD ) { break; }
        }
        return $tags;
    }

    private static function array_meta( $notebook_id, $key, $limit ) {
        $records = get_post_meta( absint( $notebook_id ), $key, true );
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        return array_slice( $records, -absint( $limit ) );
    }

    private static function write_array_meta( $notebook_id, $key, $records, $limit ) {
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        $records = array_slice( $records, -absint( $limit ) );
        update_post_meta( absint( $notebook_id ), $key, $records );
        update_post_meta( absint( $notebook_id ), self::META_UPDATED_AT, self::now() );
        return true;
    }

    public static function user_owns_notebook( $notebook_id, $user_id ) {
        $notebook_id = absint( $notebook_id );
        $user_id = absint( $user_id );
        $post = $notebook_id ? get_post( $notebook_id ) : null;
        return $post instanceof WP_Post
            && self::POST_TYPE === $post->post_type
            && $user_id > 0
            && absint( $post->post_author ) === $user_id;
    }

    public static function notebooks_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }
        $ids = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'draft', 'private', 'publish', 'pending' ),
            'author'         => $user_id,
            'posts_per_page' => self::MAX_NOTEBOOKS_PER_USER,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );
        return array_values( array_map( 'absint', $ids ) );
    }

    private static function project_context_is_valid( $user_id, $project_id, $bundle_id = '' ) {
        $project_id = absint( $project_id );
        $bundle_id = self::clean_text( $bundle_id, 80 );
        if ( ! $project_id ) { return '' === $bundle_id; }
        if ( ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { return false; }
        if ( ! SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( $project_id, $user_id ) ) { return false; }
        if ( '' === $bundle_id ) { return true; }
        foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project( $project_id ) as $bundle ) {
            if ( (string) ( $bundle['bundle_id'] ?? '' ) === $bundle_id ) { return true; }
        }
        return false;
    }

    private static function project_context( $notebook_id, $user_id ) {
        $project_id = absint( get_post_meta( $notebook_id, self::META_PROJECT_ID, true ) );
        $bundle_id = self::clean_text( get_post_meta( $notebook_id, self::META_BUNDLE_ID, true ), 80 );
        $context = array(
            'project_id' => $project_id,
            'bundle_id'  => $bundle_id,
            'valid'      => self::project_context_is_valid( $user_id, $project_id, $bundle_id ),
            'project_title' => '',
            'bundle_title'  => '',
        );
        if ( $project_id && $context['valid'] ) {
            $context['project_title'] = get_the_title( $project_id ) ?: '';
            if ( $bundle_id && class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) {
                foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project( $project_id ) as $bundle ) {
                    if ( (string) ( $bundle['bundle_id'] ?? '' ) === $bundle_id ) {
                        $context['bundle_title'] = (string) ( $bundle['title'] ?? '' );
                        break;
                    }
                }
            }
        }
        return $context;
    }

    private static function sanitize_note( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_uuid(); }
        $position = isset( $input['position'] ) ? max( 0, min( 9999, absint( $input['position'] ) ) ) : 0;
        $excerpt = self::clean_textarea( $input['excerpt'] ?? '', self::MAX_EXCERPT_CHARS );
        return array(
            'schema'        => self::NOTE_SCHEMA,
            'id'            => $id,
            'urn'           => $id ? 'urn:sc:reading-note:' . $id : '',
            'type'          => self::enum_value( $input['type'] ?? '', self::NOTE_TYPES, 'note' ),
            'title'         => self::clean_text( $input['title'] ?? '', 180 ),
            'body'          => self::clean_textarea( $input['body'] ?? '', 12000 ),
            'excerpt'       => $excerpt,
            'source_family' => self::enum_value( $input['source_family'] ?? '', self::REFERENCE_FAMILIES, 'external' ),
            'source_ref_id' => self::clean_text( $input['source_ref_id'] ?? '', 320 ),
            'source_label'  => self::clean_text( $input['source_label'] ?? '', 220 ),
            'source_url'    => esc_url_raw( (string) ( $input['source_url'] ?? '' ) ),
            'tags'          => self::clean_tags( $input['tags'] ?? array() ),
            'pinned'        => ! empty( $input['pinned'] ),
            'position'      => $position,
            'created_at'    => self::clean_text( $input['created_at'] ?? '', 40 ),
            'created_by'    => absint( $input['created_by'] ?? 0 ),
            'updated_at'    => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    private static function sanitize_annotation( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_uuid(); }
        $position = isset( $input['position'] ) ? max( 0, min( 9999, absint( $input['position'] ) ) ) : 0;
        return array(
            'schema'         => self::ANNOTATION_SCHEMA,
            'id'             => $id,
            'urn'            => $id ? 'urn:sc:source-annotation:' . $id : '',
            'type'           => self::enum_value( $input['type'] ?? '', self::ANNOTATION_TYPES, 'highlight' ),
            'source_family'  => self::enum_value( $input['source_family'] ?? '', self::REFERENCE_FAMILIES, 'external' ),
            'source_ref_id'  => self::clean_text( $input['source_ref_id'] ?? '', 320 ),
            'source_label'   => self::clean_text( $input['source_label'] ?? '', 220 ),
            'source_url'     => esc_url_raw( (string) ( $input['source_url'] ?? '' ) ),
            'locator_type'   => self::enum_value( $input['locator_type'] ?? '', self::LOCATOR_TYPES, 'page' ),
            'locator_value'  => self::clean_text( $input['locator_value'] ?? '', 180 ),
            'excerpt'        => self::clean_textarea( $input['excerpt'] ?? '', self::MAX_EXCERPT_CHARS ),
            'body'           => self::clean_textarea( $input['body'] ?? '', 8000 ),
            'tags'           => self::clean_tags( $input['tags'] ?? array() ),
            'pinned'         => ! empty( $input['pinned'] ),
            'position'       => $position,
            'created_at'     => self::clean_text( $input['created_at'] ?? '', 40 ),
            'created_by'     => absint( $input['created_by'] ?? 0 ),
            'updated_at'     => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    private static function sort_records( $records ) {
        usort( $records, static function ( $a, $b ) {
            $pin = (int) ! empty( $b['pinned'] ) <=> (int) ! empty( $a['pinned'] );
            if ( 0 !== $pin ) { return $pin; }
            $pos = absint( $a['position'] ?? 0 ) <=> absint( $b['position'] ?? 0 );
            if ( 0 !== $pos ) { return $pos; }
            return strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
        } );
        return $records;
    }

    public static function notes_for_notebook( $notebook_id ) {
        $out = array();
        foreach ( self::array_meta( $notebook_id, self::META_NOTES, self::MAX_NOTES_PER_NOTEBOOK ) as $raw ) {
            $note = self::sanitize_note( $raw, false );
            if ( $note['id'] ) { $out[] = $note; }
        }
        return self::sort_records( $out );
    }

    public static function annotations_for_notebook( $notebook_id ) {
        $out = array();
        foreach ( self::array_meta( $notebook_id, self::META_ANNOTATIONS, self::MAX_ANNOTATIONS_PER_NOTEBOOK ) as $raw ) {
            $annotation = self::sanitize_annotation( $raw, false );
            if ( $annotation['id'] ) { $out[] = $annotation; }
        }
        return self::sort_records( $out );
    }

    private static function source_resolution( $user_id, $family, $ref_id, $url, $label ) {
        if ( class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) {
            return SC_Library_Unified_Research_Projects_Source_Bundles::resolve_reference( $user_id, $family, $ref_id, $url, $label );
        }
        return array(
            'family'   => $family,
            'ref_id'   => $ref_id,
            'resolved' => 'external' === $family && (bool) $url,
            'label'    => $label,
            'url'      => $url,
            'status'   => 'external' === $family ? 'external-reference' : 'resolver-unavailable',
        );
    }

    private static function enrich_source_record( $record, $user_id ) {
        $family = (string) ( $record['source_family'] ?? 'external' );
        $ref_id = (string) ( $record['source_ref_id'] ?? '' );
        $url = (string) ( $record['source_url'] ?? '' );
        $label = (string) ( $record['source_label'] ?? '' );
        $record['source_resolution'] = ( $ref_id || $url ) ? self::source_resolution( $user_id, $family, $ref_id, $url, $label ) : array(
            'family' => '', 'ref_id' => '', 'resolved' => false, 'label' => '', 'url' => '', 'status' => 'personal-note',
        );
        return $record;
    }

    public static function create_notebook_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_library_notebook_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) ); }
        if ( count( self::notebooks_for_user( $user_id ) ) >= self::MAX_NOTEBOOKS_PER_USER ) { return new WP_Error( 'sc_library_notebook_limit', __( 'The private reading-notebook limit has been reached.', 'sustainable-catalyst-library' ) ); }
        $title = self::clean_text( $input['title'] ?? '', 180 );
        if ( '' === $title ) { return new WP_Error( 'sc_library_notebook_title', __( 'A notebook title is required.', 'sustainable-catalyst-library' ) ); }
        $project_id = absint( $input['project_id'] ?? 0 );
        $bundle_id = self::clean_text( $input['bundle_id'] ?? '', 80 );
        if ( ! self::project_context_is_valid( $user_id, $project_id, $bundle_id ) ) { return new WP_Error( 'sc_library_notebook_project', __( 'The selected project or source bundle is not available to this account.', 'sustainable-catalyst-library' ) ); }

        $post_id = wp_insert_post( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'draft',
            'post_title'  => $title,
            'post_author' => $user_id,
        ), true );
        if ( is_wp_error( $post_id ) ) { return $post_id; }
        $uuid = self::new_uuid();
        update_post_meta( $post_id, self::META_UUID, $uuid );
        update_post_meta( $post_id, self::META_PROJECT_ID, $project_id );
        update_post_meta( $post_id, self::META_BUNDLE_ID, $bundle_id );
        update_post_meta( $post_id, self::META_STATUS, 'active' );
        update_post_meta( $post_id, self::META_NOTES, array() );
        update_post_meta( $post_id, self::META_ANNOTATIONS, array() );
        update_post_meta( $post_id, self::META_UPDATED_AT, self::now() );
        do_action( 'sc_library_reading_notebook_created', $post_id, $user_id, $uuid );
        return self::notebook_state( $post_id, $user_id );
    }

    public static function update_notebook_for_user( $user_id, $notebook_id, $input ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $post = get_post( $notebook_id );
        $title = self::clean_text( $input['title'] ?? ( $post ? $post->post_title : '' ), 180 );
        if ( '' === $title ) { return new WP_Error( 'sc_library_notebook_title', __( 'A notebook title is required.', 'sustainable-catalyst-library' ) ); }
        $project_id = array_key_exists( 'project_id', (array) $input ) ? absint( $input['project_id'] ) : absint( get_post_meta( $notebook_id, self::META_PROJECT_ID, true ) );
        $bundle_id = array_key_exists( 'bundle_id', (array) $input ) ? self::clean_text( $input['bundle_id'], 80 ) : self::clean_text( get_post_meta( $notebook_id, self::META_BUNDLE_ID, true ), 80 );
        if ( ! self::project_context_is_valid( $user_id, $project_id, $bundle_id ) ) { return new WP_Error( 'sc_library_notebook_project', __( 'The selected project or source bundle is not available to this account.', 'sustainable-catalyst-library' ) ); }
        $status = self::enum_value( $input['status'] ?? get_post_meta( $notebook_id, self::META_STATUS, true ), self::NOTEBOOK_STATUSES, 'active' );
        wp_update_post( array( 'ID' => absint( $notebook_id ), 'post_title' => $title ) );
        update_post_meta( $notebook_id, self::META_PROJECT_ID, $project_id );
        update_post_meta( $notebook_id, self::META_BUNDLE_ID, $bundle_id );
        update_post_meta( $notebook_id, self::META_STATUS, $status );
        update_post_meta( $notebook_id, self::META_UPDATED_AT, self::now() );
        do_action( 'sc_library_reading_notebook_updated', $notebook_id, $user_id );
        return self::notebook_state( $notebook_id, $user_id );
    }

    public static function delete_notebook_for_user( $user_id, $notebook_id ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $uuid = (string) get_post_meta( $notebook_id, self::META_UUID, true );
        $deleted = wp_delete_post( absint( $notebook_id ), true );
        if ( ! $deleted ) { return new WP_Error( 'sc_library_notebook_delete', __( 'The notebook could not be deleted.', 'sustainable-catalyst-library' ) ); }
        do_action( 'sc_library_reading_notebook_deleted', $notebook_id, $user_id, $uuid );
        return array( 'deleted' => true, 'notebook_id' => absint( $notebook_id ), 'uuid' => $uuid );
    }

    public static function add_note_for_user( $user_id, $notebook_id, $input ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $records = self::notes_for_notebook( $notebook_id );
        if ( count( $records ) >= self::MAX_NOTES_PER_NOTEBOOK ) { return new WP_Error( 'sc_library_note_limit', __( 'This notebook has reached its note limit.', 'sustainable-catalyst-library' ) ); }
        $note = self::sanitize_note( $input, true );
        if ( '' === $note['title'] && '' === $note['body'] && '' === $note['excerpt'] ) { return new WP_Error( 'sc_library_note_empty', __( 'Add a note title, note text, or excerpt.', 'sustainable-catalyst-library' ) ); }
        $note['created_at'] = self::now(); $note['updated_at'] = $note['created_at']; $note['created_by'] = absint( $user_id );
        $records[] = $note;
        self::write_array_meta( $notebook_id, self::META_NOTES, $records, self::MAX_NOTES_PER_NOTEBOOK );
        do_action( 'sc_library_reading_note_created', $notebook_id, $note, $user_id );
        return self::enrich_source_record( $note, $user_id );
    }

    public static function update_note_for_user( $user_id, $notebook_id, $note_id, $input ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $note_id = self::clean_text( $note_id, 80 );
        $records = self::notes_for_notebook( $notebook_id );
        $found = false;
        foreach ( $records as &$record ) {
            if ( (string) $record['id'] !== $note_id ) { continue; }
            $merged = array_merge( $record, is_array( $input ) ? $input : array(), array( 'id' => $record['id'], 'created_at' => $record['created_at'], 'created_by' => $record['created_by'], 'updated_at' => self::now() ) );
            $record = self::sanitize_note( $merged, false ); $found = true; break;
        }
        unset( $record );
        if ( ! $found ) { return new WP_Error( 'sc_library_note_not_found', __( 'That note was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        self::write_array_meta( $notebook_id, self::META_NOTES, $records, self::MAX_NOTES_PER_NOTEBOOK );
        do_action( 'sc_library_reading_note_updated', $notebook_id, $note_id, $user_id );
        foreach ( $records as $record ) { if ( $record['id'] === $note_id ) { return self::enrich_source_record( $record, $user_id ); } }
        return null;
    }

    public static function delete_note_for_user( $user_id, $notebook_id, $note_id ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $note_id = self::clean_text( $note_id, 80 );
        $records = array_values( array_filter( self::notes_for_notebook( $notebook_id ), static function ( $record ) use ( $note_id ) { return (string) $record['id'] !== $note_id; } ) );
        self::write_array_meta( $notebook_id, self::META_NOTES, $records, self::MAX_NOTES_PER_NOTEBOOK );
        do_action( 'sc_library_reading_note_deleted', $notebook_id, $note_id, $user_id );
        return array( 'deleted' => true, 'note_id' => $note_id );
    }

    public static function add_annotation_for_user( $user_id, $notebook_id, $input ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $records = self::annotations_for_notebook( $notebook_id );
        if ( count( $records ) >= self::MAX_ANNOTATIONS_PER_NOTEBOOK ) { return new WP_Error( 'sc_library_annotation_limit', __( 'This notebook has reached its annotation limit.', 'sustainable-catalyst-library' ) ); }
        $annotation = self::sanitize_annotation( $input, true );
        if ( '' === $annotation['source_ref_id'] && '' === $annotation['source_url'] ) { return new WP_Error( 'sc_library_annotation_source', __( 'An annotation must point to a source record or external URL.', 'sustainable-catalyst-library' ) ); }
        if ( '' === $annotation['excerpt'] && '' === $annotation['body'] && '' === $annotation['locator_value'] ) { return new WP_Error( 'sc_library_annotation_empty', __( 'Add a locator, excerpt, or annotation note.', 'sustainable-catalyst-library' ) ); }
        $annotation['created_at'] = self::now(); $annotation['updated_at'] = $annotation['created_at']; $annotation['created_by'] = absint( $user_id );
        $records[] = $annotation;
        self::write_array_meta( $notebook_id, self::META_ANNOTATIONS, $records, self::MAX_ANNOTATIONS_PER_NOTEBOOK );
        do_action( 'sc_library_source_annotation_created', $notebook_id, $annotation, $user_id );
        return self::enrich_source_record( $annotation, $user_id );
    }

    public static function update_annotation_for_user( $user_id, $notebook_id, $annotation_id, $input ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $annotation_id = self::clean_text( $annotation_id, 80 );
        $records = self::annotations_for_notebook( $notebook_id );
        $found = false;
        foreach ( $records as &$record ) {
            if ( (string) $record['id'] !== $annotation_id ) { continue; }
            $merged = array_merge( $record, is_array( $input ) ? $input : array(), array( 'id' => $record['id'], 'created_at' => $record['created_at'], 'created_by' => $record['created_by'], 'updated_at' => self::now() ) );
            $record = self::sanitize_annotation( $merged, false ); $found = true; break;
        }
        unset( $record );
        if ( ! $found ) { return new WP_Error( 'sc_library_annotation_not_found', __( 'That annotation was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        self::write_array_meta( $notebook_id, self::META_ANNOTATIONS, $records, self::MAX_ANNOTATIONS_PER_NOTEBOOK );
        do_action( 'sc_library_source_annotation_updated', $notebook_id, $annotation_id, $user_id );
        foreach ( $records as $record ) { if ( $record['id'] === $annotation_id ) { return self::enrich_source_record( $record, $user_id ); } }
        return null;
    }

    public static function delete_annotation_for_user( $user_id, $notebook_id, $annotation_id ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $annotation_id = self::clean_text( $annotation_id, 80 );
        $records = array_values( array_filter( self::annotations_for_notebook( $notebook_id ), static function ( $record ) use ( $annotation_id ) { return (string) $record['id'] !== $annotation_id; } ) );
        self::write_array_meta( $notebook_id, self::META_ANNOTATIONS, $records, self::MAX_ANNOTATIONS_PER_NOTEBOOK );
        do_action( 'sc_library_source_annotation_deleted', $notebook_id, $annotation_id, $user_id );
        return array( 'deleted' => true, 'annotation_id' => $annotation_id );
    }

    public static function notebook_state( $notebook_id, $user_id ) {
        if ( ! self::user_owns_notebook( $notebook_id, $user_id ) ) { return new WP_Error( 'sc_library_notebook_forbidden', __( 'That notebook is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $post = get_post( $notebook_id );
        $uuid = (string) get_post_meta( $notebook_id, self::META_UUID, true );
        if ( '' === $uuid ) { $uuid = self::new_uuid(); update_post_meta( $notebook_id, self::META_UUID, $uuid ); }
        $notes = array_map( static function ( $record ) use ( $user_id ) { return self::enrich_source_record( $record, $user_id ); }, self::notes_for_notebook( $notebook_id ) );
        $annotations = array_map( static function ( $record ) use ( $user_id ) { return self::enrich_source_record( $record, $user_id ); }, self::annotations_for_notebook( $notebook_id ) );
        return array(
            'schema'           => self::SCHEMA,
            'version'          => self::VERSION,
            'notebook_id'      => absint( $notebook_id ),
            'uuid'             => $uuid,
            'urn'              => 'urn:sc:reading-notebook:' . $uuid,
            'title'            => $post ? $post->post_title : '',
            'owner_user_id'    => absint( $user_id ),
            'visibility'       => 'private',
            'status'           => self::enum_value( get_post_meta( $notebook_id, self::META_STATUS, true ), self::NOTEBOOK_STATUSES, 'active' ),
            'project_context'  => self::project_context( $notebook_id, $user_id ),
            'notes'            => $notes,
            'annotations'      => $annotations,
            'note_count'       => count( $notes ),
            'annotation_count' => count( $annotations ),
            'contract'         => self::contract(),
            'updated_at'       => (string) get_post_meta( $notebook_id, self::META_UPDATED_AT, true ),
        );
    }

    public static function state_for_user( $user_id ) {
        $items = array();
        foreach ( self::notebooks_for_user( $user_id ) as $notebook_id ) {
            $state = self::notebook_state( $notebook_id, $user_id );
            if ( ! is_wp_error( $state ) ) { $items[] = $state; }
        }
        return array(
            'schema'         => 'sc-library-reading-notebooks-state/1.0',
            'version'        => self::VERSION,
            'visibility'     => 'private',
            'user_id'        => absint( $user_id ),
            'notebooks'      => $items,
            'notebook_count' => count( $items ),
            'contract'       => self::contract(),
        );
    }

    public static function notebook_manifest( $notebook_id, $user_id ) {
        $state = self::notebook_state( $notebook_id, $user_id );
        if ( is_wp_error( $state ) ) { return $state; }
        $manifest = array(
            'schema'          => 'sc-library-reading-notebook-manifest/1.0',
            'version'         => self::VERSION,
            'notebook'        => $state,
            'generated_at'    => self::now(),
            'content_contract'=> array(
                'user_authored_notes_only' => true,
                'source_records_referenced_not_copied' => true,
                'private_binaries_excluded' => true,
            ),
        );
        $manifest['checksum_sha256'] = hash( 'sha256', wp_json_encode( $manifest ) );
        return $manifest;
    }

    private static function project_catalog_for_user( $user_id ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { return $out; }
        foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( $user_id ) as $project_id ) {
            $item = array(
                'project_id' => absint( $project_id ),
                'title'      => get_the_title( $project_id ) ?: sprintf( 'Project %d', $project_id ),
                'bundles'    => array(),
            );
            foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::bundles_for_project( $project_id ) as $bundle ) {
                $item['bundles'][] = array( 'bundle_id' => (string) ( $bundle['bundle_id'] ?? '' ), 'title' => (string) ( $bundle['title'] ?? '' ) );
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function source_catalog_for_user( $user_id ) {
        return class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' )
            ? SC_Library_Unified_Research_Projects_Source_Bundles::reference_catalog_for_user( $user_id )
            : array();
    }

    private static function context_from_key( $value ) {
        $value = (string) $value;
        if ( '' === $value ) { return array( 0, '' ); }
        $parts = explode( ':', $value, 3 );
        if ( 'project' === ( $parts[0] ?? '' ) ) { return array( absint( $parts[1] ?? 0 ), '' ); }
        if ( 'bundle' === ( $parts[0] ?? '' ) ) { return array( absint( $parts[1] ?? 0 ), self::clean_text( $parts[2] ?? '', 80 ) ); }
        return array( 0, '' );
    }

    private static function reference_from_key( $value ) {
        $value = (string) $value;
        $parts = explode( '|', $value, 2 );
        if ( 2 !== count( $parts ) ) { return array( '', '' ); }
        $family = self::enum_value( $parts[0], self::REFERENCE_FAMILIES, 'external' );
        return array( $family, self::clean_text( $parts[1], 320 ) );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_state' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_create_notebook' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_notebook' ) ),
            array( 'methods' => WP_REST_Server::EDITABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_update_notebook' ) ),
            array( 'methods' => WP_REST_Server::DELETABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_delete_notebook' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/manifest', array(
            'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_manifest' )
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/notes', array(
            'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_add_note' )
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/notes/(?P<note_id>[A-Za-z0-9\-]+)', array(
            array( 'methods' => WP_REST_Server::EDITABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_update_note' ) ),
            array( 'methods' => WP_REST_Server::DELETABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_delete_note' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/annotations', array(
            'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_add_annotation' )
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/annotations/(?P<annotation_id>[A-Za-z0-9\-]+)', array(
            array( 'methods' => WP_REST_Server::EDITABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_update_annotation' ) ),
            array( 'methods' => WP_REST_Server::DELETABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_delete_annotation' ) ),
        ) );
    }

    public function rest_signed_in() { return is_user_logged_in(); }
    public function rest_state() { return rest_ensure_response( self::state_for_user( get_current_user_id() ) ); }
    public function rest_notebook( WP_REST_Request $request ) { return $this->rest_result( self::notebook_state( absint( $request['id'] ), get_current_user_id() ) ); }
    public function rest_manifest( WP_REST_Request $request ) { return $this->rest_result( self::notebook_manifest( absint( $request['id'] ), get_current_user_id() ) ); }
    public function rest_create_notebook( WP_REST_Request $request ) { return $this->rest_result( self::create_notebook_for_user( get_current_user_id(), $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_update_notebook( WP_REST_Request $request ) { return $this->rest_result( self::update_notebook_for_user( get_current_user_id(), absint( $request['id'] ), $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_delete_notebook( WP_REST_Request $request ) { return $this->rest_result( self::delete_notebook_for_user( get_current_user_id(), absint( $request['id'] ) ) ); }
    public function rest_add_note( WP_REST_Request $request ) { return $this->rest_result( self::add_note_for_user( get_current_user_id(), absint( $request['id'] ), $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_update_note( WP_REST_Request $request ) { return $this->rest_result( self::update_note_for_user( get_current_user_id(), absint( $request['id'] ), $request['note_id'], $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_delete_note( WP_REST_Request $request ) { return $this->rest_result( self::delete_note_for_user( get_current_user_id(), absint( $request['id'] ), $request['note_id'] ) ); }
    public function rest_add_annotation( WP_REST_Request $request ) { return $this->rest_result( self::add_annotation_for_user( get_current_user_id(), absint( $request['id'] ), $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_update_annotation( WP_REST_Request $request ) { return $this->rest_result( self::update_annotation_for_user( get_current_user_id(), absint( $request['id'] ), $request['annotation_id'], $request->get_json_params() ?: $request->get_params() ) ); }
    public function rest_delete_annotation( WP_REST_Request $request ) { return $this->rest_result( self::delete_annotation_for_user( get_current_user_id(), absint( $request['id'] ), $request['annotation_id'] ) ); }

    private function rest_result( $result ) {
        if ( is_wp_error( $result ) ) { return $result; }
        return rest_ensure_response( $result );
    }

    private function require_ajax_user() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Sign in to update your reading notebooks.', 'sustainable-catalyst-library' ) ), 401 ); }
        return get_current_user_id();
    }

    private function ajax_result( $result, $success_message ) {
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 400 ) ); }
        wp_send_json_success( array( 'message' => $success_message, 'record' => $result ) );
    }

    private function ajax_payload_with_reference() {
        $payload = array_map( 'wp_unslash', $_POST );
        list( $family, $ref_id ) = self::reference_from_key( wp_unslash( $_POST['reference_key'] ?? '' ) );
        $payload['source_family'] = $family && $ref_id ? $family : 'external';
        $payload['source_ref_id'] = $family && $ref_id ? $ref_id : '';
        $payload['pinned'] = ! empty( $_POST['pinned'] ) ? 1 : 0;
        return $payload;
    }

    public function ajax_create_notebook() {
        $user = $this->require_ajax_user(); list( $project_id, $bundle_id ) = self::context_from_key( wp_unslash( $_POST['context_key'] ?? '' ) );
        $this->ajax_result( self::create_notebook_for_user( $user, array( 'title' => wp_unslash( $_POST['title'] ?? '' ), 'project_id' => $project_id, 'bundle_id' => $bundle_id ) ), __( 'Reading notebook created.', 'sustainable-catalyst-library' ) );
    }
    public function ajax_update_notebook() {
        $user = $this->require_ajax_user(); list( $project_id, $bundle_id ) = self::context_from_key( wp_unslash( $_POST['context_key'] ?? '' ) );
        $this->ajax_result( self::update_notebook_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), array( 'title' => wp_unslash( $_POST['title'] ?? '' ), 'status' => wp_unslash( $_POST['status'] ?? 'active' ), 'project_id' => $project_id, 'bundle_id' => $bundle_id ) ), __( 'Reading notebook updated.', 'sustainable-catalyst-library' ) );
    }
    public function ajax_delete_notebook() { $user = $this->require_ajax_user(); $this->ajax_result( self::delete_notebook_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ) ), __( 'Reading notebook deleted.', 'sustainable-catalyst-library' ) ); }
    public function ajax_add_note() { $user = $this->require_ajax_user(); $this->ajax_result( self::add_note_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), $this->ajax_payload_with_reference() ), __( 'Note saved.', 'sustainable-catalyst-library' ) ); }
    public function ajax_update_note() { $user = $this->require_ajax_user(); $this->ajax_result( self::update_note_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), wp_unslash( $_POST['note_id'] ?? '' ), $this->ajax_payload_with_reference() ), __( 'Note updated.', 'sustainable-catalyst-library' ) ); }
    public function ajax_delete_note() { $user = $this->require_ajax_user(); $this->ajax_result( self::delete_note_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), wp_unslash( $_POST['note_id'] ?? '' ) ), __( 'Note deleted.', 'sustainable-catalyst-library' ) ); }
    public function ajax_add_annotation() { $user = $this->require_ajax_user(); $this->ajax_result( self::add_annotation_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), $this->ajax_payload_with_reference() ), __( 'Annotation saved.', 'sustainable-catalyst-library' ) ); }
    public function ajax_update_annotation() { $user = $this->require_ajax_user(); $this->ajax_result( self::update_annotation_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), wp_unslash( $_POST['annotation_id'] ?? '' ), $this->ajax_payload_with_reference() ), __( 'Annotation updated.', 'sustainable-catalyst-library' ) ); }
    public function ajax_delete_annotation() { $user = $this->require_ajax_user(); $this->ajax_result( self::delete_annotation_for_user( $user, absint( $_POST['notebook_id'] ?? 0 ), wp_unslash( $_POST['annotation_id'] ?? '' ) ), __( 'Annotation deleted.', 'sustainable-catalyst-library' ) ); }

    public function filter_notebook_state( $state, $notebook_id, $user_id ) { $result = self::notebook_state( $notebook_id, $user_id ); return is_wp_error( $result ) ? $state : $result; }
    public function filter_notebook_manifest( $manifest, $notebook_id, $user_id ) { $result = self::notebook_manifest( $notebook_id, $user_id ); return is_wp_error( $result ) ? $manifest : $result; }
    public function action_create_note( $notebook_id, $payload, $user_id ) { self::add_note_for_user( $user_id, $notebook_id, $payload ); }
    public function action_create_annotation( $notebook_id, $payload, $user_id ) { self::add_annotation_for_user( $user_id, $notebook_id, $payload ); }

    private function sign_in_url() {
        $request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        $return = $request_uri && 0 === strpos( $request_uri, '/' ) ? home_url( $request_uri ) : home_url( '/knowledge-libraries/' );
        return wp_login_url( $return );
    }

    private static function render_context_select( $projects, $selected_project = 0, $selected_bundle = '' ) {
        $selected = $selected_bundle ? 'bundle:' . absint( $selected_project ) . ':' . $selected_bundle : ( $selected_project ? 'project:' . absint( $selected_project ) : '' );
        $html = '<label><span>' . esc_html__( 'Research project / source bundle', 'sustainable-catalyst-library' ) . '</span><select name="context_key"><option value="">' . esc_html__( 'Standalone private notebook', 'sustainable-catalyst-library' ) . '</option>';
        foreach ( $projects as $project ) {
            $project_value = 'project:' . absint( $project['project_id'] );
            $html .= '<option value="' . esc_attr( $project_value ) . '" ' . selected( $selected, $project_value, false ) . '>' . esc_html( $project['title'] ) . '</option>';
            foreach ( (array) $project['bundles'] as $bundle ) {
                $bundle_value = 'bundle:' . absint( $project['project_id'] ) . ':' . (string) $bundle['bundle_id'];
                $html .= '<option value="' . esc_attr( $bundle_value ) . '" ' . selected( $selected, $bundle_value, false ) . '>↳ ' . esc_html( $project['title'] . ' — ' . $bundle['title'] ) . '</option>';
            }
        }
        return $html . '</select></label>';
    }

    private static function render_reference_select( $catalog, $selected_family = '', $selected_ref_id = '', $required = false ) {
        $groups = array(); foreach ( $catalog as $item ) { $groups[ $item['family'] ][] = $item; }
        $current = $selected_family && $selected_ref_id ? $selected_family . '|' . $selected_ref_id : '';
        $html = '<label><span>' . esc_html__( 'Linked Library source', 'sustainable-catalyst-library' ) . '</span><select name="reference_key"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html__( 'No linked Library record', 'sustainable-catalyst-library' ) . '</option>';
        foreach ( self::REFERENCE_FAMILIES as $family => $label ) {
            if ( 'external' === $family || empty( $groups[ $family ] ) ) { continue; }
            $html .= '<optgroup label="' . esc_attr( $label ) . '">';
            foreach ( $groups[ $family ] as $item ) {
                $value = $family . '|' . $item['ref_id'];
                $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $item['label'] ) . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html . '</select></label>';
    }

    private static function render_tags( $tags ) { return $tags ? implode( ', ', array_map( 'esc_html', (array) $tags ) ) : ''; }

    private static function render_notes( $notebook_id, $notes, $catalog ) {
        if ( ! $notes ) { return '<p class="sc-reading-notebooks__empty">' . esc_html__( 'No notes yet.', 'sustainable-catalyst-library' ) . '</p>'; }
        $html = '<div class="sc-reading-notebooks__records">';
        foreach ( $notes as $note ) {
            $source = $note['source_resolution'] ?? array();
            $html .= '<article class="sc-reading-notebooks__record"><header><div><small>' . esc_html( self::NOTE_TYPES[ $note['type'] ] ?? $note['type'] ) . ( $note['pinned'] ? ' · ' . esc_html__( 'Pinned', 'sustainable-catalyst-library' ) : '' ) . '</small><strong>' . esc_html( $note['title'] ?: __( 'Untitled note', 'sustainable-catalyst-library' ) ) . '</strong></div><button type="button" data-sc-reading-delete-note="' . esc_attr( $note['id'] ) . '" data-notebook-id="' . esc_attr( $notebook_id ) . '">' . esc_html__( 'Delete', 'sustainable-catalyst-library' ) . '</button></header>';
            if ( $note['body'] ) { $html .= '<p>' . nl2br( esc_html( $note['body'] ) ) . '</p>'; }
            if ( $note['excerpt'] ) { $html .= '<blockquote><p>' . nl2br( esc_html( $note['excerpt'] ) ) . '</p><small>' . esc_html__( 'Reusable excerpt — user selected', 'sustainable-catalyst-library' ) . '</small></blockquote>'; }
            if ( ! empty( $source['label'] ) ) { $html .= '<p class="sc-reading-notebooks__source"><span>' . esc_html__( 'Source', 'sustainable-catalyst-library' ) . '</span><strong>' . esc_html( $source['label'] ) . '</strong></p>'; }
            if ( $note['tags'] ) { $html .= '<p class="sc-reading-notebooks__tags">' . self::render_tags( $note['tags'] ) . '</p>'; }
            $html .= '<details><summary>' . esc_html__( 'Edit note', 'sustainable-catalyst-library' ) . '</summary><form data-sc-reading-update-note><input type="hidden" name="notebook_id" value="' . esc_attr( $notebook_id ) . '"><input type="hidden" name="note_id" value="' . esc_attr( $note['id'] ) . '"><label><span>' . esc_html__( 'Type', 'sustainable-catalyst-library' ) . '</span><select name="type">';
            foreach ( self::NOTE_TYPES as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $note['type'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            $html .= '</select></label><label><span>' . esc_html__( 'Title', 'sustainable-catalyst-library' ) . '</span><input name="title" value="' . esc_attr( $note['title'] ) . '"></label><label><span>' . esc_html__( 'Note', 'sustainable-catalyst-library' ) . '</span><textarea name="body" rows="4">' . esc_textarea( $note['body'] ) . '</textarea></label><label><span>' . esc_html__( 'Reusable excerpt', 'sustainable-catalyst-library' ) . '</span><textarea name="excerpt" rows="3" maxlength="' . esc_attr( self::MAX_EXCERPT_CHARS ) . '">' . esc_textarea( $note['excerpt'] ) . '</textarea></label>' . self::render_reference_select( $catalog, $note['source_family'], $note['source_ref_id'] ) . '<div class="sc-reading-notebooks__grid"><label><span>' . esc_html__( 'External source label', 'sustainable-catalyst-library' ) . '</span><input name="source_label" value="' . esc_attr( $note['source_label'] ) . '"></label><label><span>' . esc_html__( 'External URL', 'sustainable-catalyst-library' ) . '</span><input type="url" name="source_url" value="' . esc_attr( $note['source_url'] ) . '"></label></div><label><span>' . esc_html__( 'Tags', 'sustainable-catalyst-library' ) . '</span><input name="tags" value="' . esc_attr( implode( ', ', $note['tags'] ) ) . '"></label><div class="sc-reading-notebooks__grid"><label><span>' . esc_html__( 'Order', 'sustainable-catalyst-library' ) . '</span><input type="number" min="0" max="9999" name="position" value="' . esc_attr( $note['position'] ) . '"></label><label class="sc-reading-notebooks__check"><input type="checkbox" name="pinned" value="1" ' . checked( $note['pinned'], true, false ) . '><span>' . esc_html__( 'Pin note', 'sustainable-catalyst-library' ) . '</span></label></div><button type="submit">' . esc_html__( 'Save note', 'sustainable-catalyst-library' ) . '</button></form></details></article>';
        }
        return $html . '</div>';
    }

    private static function render_annotations( $notebook_id, $annotations, $catalog ) {
        if ( ! $annotations ) { return '<p class="sc-reading-notebooks__empty">' . esc_html__( 'No source annotations yet.', 'sustainable-catalyst-library' ) . '</p>'; }
        $html = '<div class="sc-reading-notebooks__records">';
        foreach ( $annotations as $annotation ) {
            $source = $annotation['source_resolution'] ?? array();
            $locator = ( self::LOCATOR_TYPES[ $annotation['locator_type'] ] ?? $annotation['locator_type'] ) . ( $annotation['locator_value'] ? ': ' . $annotation['locator_value'] : '' );
            $html .= '<article class="sc-reading-notebooks__record"><header><div><small>' . esc_html( self::ANNOTATION_TYPES[ $annotation['type'] ] ?? $annotation['type'] ) . ( $annotation['pinned'] ? ' · ' . esc_html__( 'Pinned', 'sustainable-catalyst-library' ) : '' ) . '</small><strong>' . esc_html( $source['label'] ?? $annotation['source_label'] ?: __( 'Source annotation', 'sustainable-catalyst-library' ) ) . '</strong><span>' . esc_html( $locator ) . '</span></div><button type="button" data-sc-reading-delete-annotation="' . esc_attr( $annotation['id'] ) . '" data-notebook-id="' . esc_attr( $notebook_id ) . '">' . esc_html__( 'Delete', 'sustainable-catalyst-library' ) . '</button></header>';
            if ( $annotation['excerpt'] ) { $html .= '<blockquote><p>' . nl2br( esc_html( $annotation['excerpt'] ) ) . '</p><small>' . esc_html__( 'Selected passage', 'sustainable-catalyst-library' ) . '</small></blockquote>'; }
            if ( $annotation['body'] ) { $html .= '<p>' . nl2br( esc_html( $annotation['body'] ) ) . '</p>'; }
            if ( $annotation['tags'] ) { $html .= '<p class="sc-reading-notebooks__tags">' . self::render_tags( $annotation['tags'] ) . '</p>'; }
            $html .= '<details><summary>' . esc_html__( 'Edit annotation', 'sustainable-catalyst-library' ) . '</summary><form data-sc-reading-update-annotation><input type="hidden" name="notebook_id" value="' . esc_attr( $notebook_id ) . '"><input type="hidden" name="annotation_id" value="' . esc_attr( $annotation['id'] ) . '"><label><span>' . esc_html__( 'Annotation type', 'sustainable-catalyst-library' ) . '</span><select name="type">';
            foreach ( self::ANNOTATION_TYPES as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $annotation['type'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            $html .= '</select></label>' . self::render_reference_select( $catalog, $annotation['source_family'], $annotation['source_ref_id'] ) . '<div class="sc-reading-notebooks__grid"><label><span>' . esc_html__( 'External source label', 'sustainable-catalyst-library' ) . '</span><input name="source_label" value="' . esc_attr( $annotation['source_label'] ) . '"></label><label><span>' . esc_html__( 'External URL', 'sustainable-catalyst-library' ) . '</span><input type="url" name="source_url" value="' . esc_attr( $annotation['source_url'] ) . '"></label></div><div class="sc-reading-notebooks__grid"><label><span>' . esc_html__( 'Locator type', 'sustainable-catalyst-library' ) . '</span><select name="locator_type">';
            foreach ( self::LOCATOR_TYPES as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $annotation['locator_type'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            $html .= '</select></label><label><span>' . esc_html__( 'Locator', 'sustainable-catalyst-library' ) . '</span><input name="locator_value" value="' . esc_attr( $annotation['locator_value'] ) . '" placeholder="' . esc_attr__( 'e.g. 17, Methods, 00:14:08', 'sustainable-catalyst-library' ) . '"></label></div><label><span>' . esc_html__( 'Selected passage', 'sustainable-catalyst-library' ) . '</span><textarea name="excerpt" rows="3" maxlength="' . esc_attr( self::MAX_EXCERPT_CHARS ) . '">' . esc_textarea( $annotation['excerpt'] ) . '</textarea></label><label><span>' . esc_html__( 'Annotation note', 'sustainable-catalyst-library' ) . '</span><textarea name="body" rows="3">' . esc_textarea( $annotation['body'] ) . '</textarea></label><label><span>' . esc_html__( 'Tags', 'sustainable-catalyst-library' ) . '</span><input name="tags" value="' . esc_attr( implode( ', ', $annotation['tags'] ) ) . '"></label><div class="sc-reading-notebooks__grid"><label><span>' . esc_html__( 'Order', 'sustainable-catalyst-library' ) . '</span><input type="number" min="0" max="9999" name="position" value="' . esc_attr( $annotation['position'] ) . '"></label><label class="sc-reading-notebooks__check"><input type="checkbox" name="pinned" value="1" ' . checked( $annotation['pinned'], true, false ) . '><span>' . esc_html__( 'Pin annotation', 'sustainable-catalyst-library' ) . '</span></label></div><button type="submit">' . esc_html__( 'Save annotation', 'sustainable-catalyst-library' ) . '</button></form></details></article>';
        }
        return $html . '</div>';
    }

    private static function render_notebook_card( $notebook_id, $user_id, $projects, $catalog ) {
        $state = self::notebook_state( $notebook_id, $user_id ); if ( is_wp_error( $state ) ) { return ''; }
        $context = $state['project_context'];
        ob_start(); ?>
        <article class="sc-reading-notebooks__notebook" data-sc-reading-notebook="<?php echo esc_attr( $notebook_id ); ?>">
            <header>
                <div><small><?php echo esc_html( self::NOTEBOOK_STATUSES[ $state['status'] ] ?? 'Active' ); ?> · <?php esc_html_e( 'Private account notebook', 'sustainable-catalyst-library' ); ?></small><h4><?php echo esc_html( $state['title'] ); ?></h4><?php if ( $context['project_title'] ) : ?><p><?php echo esc_html( $context['project_title'] . ( $context['bundle_title'] ? ' → ' . $context['bundle_title'] : '' ) ); ?></p><?php endif; ?></div>
                <div class="sc-reading-notebooks__counts"><span><b><?php echo esc_html( (string) $state['note_count'] ); ?></b><?php esc_html_e( 'notes', 'sustainable-catalyst-library' ); ?></span><span><b><?php echo esc_html( (string) $state['annotation_count'] ); ?></b><?php esc_html_e( 'annotations', 'sustainable-catalyst-library' ); ?></span></div>
            </header>
            <p class="sc-reading-notebooks__identity"><span><?php esc_html_e( 'Stable notebook identity', 'sustainable-catalyst-library' ); ?></span><code><?php echo esc_html( $state['urn'] ); ?></code></p>
            <details><summary><?php esc_html_e( 'Notebook settings', 'sustainable-catalyst-library' ); ?></summary><form data-sc-reading-update-notebook><input type="hidden" name="notebook_id" value="<?php echo esc_attr( $notebook_id ); ?>"><label><span><?php esc_html_e( 'Title', 'sustainable-catalyst-library' ); ?></span><input name="title" value="<?php echo esc_attr( $state['title'] ); ?>" required></label><?php echo self::render_context_select( $projects, $context['project_id'], $context['bundle_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><label><span><?php esc_html_e( 'Status', 'sustainable-catalyst-library' ); ?></span><select name="status"><?php foreach ( self::NOTEBOOK_STATUSES as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $state['status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><div class="sc-reading-notebooks__form-actions"><button type="submit"><?php esc_html_e( 'Save notebook', 'sustainable-catalyst-library' ); ?></button><button type="button" class="is-danger" data-sc-reading-delete-notebook="<?php echo esc_attr( $notebook_id ); ?>"><?php esc_html_e( 'Delete notebook', 'sustainable-catalyst-library' ); ?></button></div></form></details>
            <div class="sc-reading-notebooks__split">
                <section><h5><?php esc_html_e( 'Reading notes & reusable excerpts', 'sustainable-catalyst-library' ); ?></h5><?php echo self::render_notes( $notebook_id, $state['notes'], $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details><summary><?php esc_html_e( 'Add a note or excerpt', 'sustainable-catalyst-library' ); ?></summary><form data-sc-reading-add-note><input type="hidden" name="notebook_id" value="<?php echo esc_attr( $notebook_id ); ?>"><label><span><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></span><select name="type"><?php foreach ( self::NOTE_TYPES as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e( 'Title', 'sustainable-catalyst-library' ); ?></span><input name="title"></label><label><span><?php esc_html_e( 'Your note', 'sustainable-catalyst-library' ); ?></span><textarea name="body" rows="4"></textarea></label><label><span><?php esc_html_e( 'Reusable excerpt', 'sustainable-catalyst-library' ); ?></span><textarea name="excerpt" rows="3" maxlength="<?php echo esc_attr( self::MAX_EXCERPT_CHARS ); ?>" placeholder="<?php esc_attr_e( 'Optional selected passage; keep excerpts concise.', 'sustainable-catalyst-library' ); ?>"></textarea></label><?php echo self::render_reference_select( $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div class="sc-reading-notebooks__grid"><label><span><?php esc_html_e( 'External source label', 'sustainable-catalyst-library' ); ?></span><input name="source_label"></label><label><span><?php esc_html_e( 'External URL', 'sustainable-catalyst-library' ); ?></span><input type="url" name="source_url"></label></div><label><span><?php esc_html_e( 'Tags', 'sustainable-catalyst-library' ); ?></span><input name="tags" placeholder="<?php esc_attr_e( 'evidence, methods, follow-up', 'sustainable-catalyst-library' ); ?>"></label><div class="sc-reading-notebooks__grid"><label><span><?php esc_html_e( 'Order', 'sustainable-catalyst-library' ); ?></span><input type="number" min="0" max="9999" name="position" value="0"></label><label class="sc-reading-notebooks__check"><input type="checkbox" name="pinned" value="1"><span><?php esc_html_e( 'Pin note', 'sustainable-catalyst-library' ); ?></span></label></div><button type="submit"><?php esc_html_e( 'Save note', 'sustainable-catalyst-library' ); ?></button></form></details>
                </section>
                <section><h5><?php esc_html_e( 'Source annotations', 'sustainable-catalyst-library' ); ?></h5><?php echo self::render_annotations( $notebook_id, $state['annotations'], $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <details><summary><?php esc_html_e( 'Add a source annotation', 'sustainable-catalyst-library' ); ?></summary><form data-sc-reading-add-annotation><input type="hidden" name="notebook_id" value="<?php echo esc_attr( $notebook_id ); ?>"><label><span><?php esc_html_e( 'Annotation type', 'sustainable-catalyst-library' ); ?></span><select name="type"><?php foreach ( self::ANNOTATION_TYPES as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php echo self::render_reference_select( $catalog ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><div class="sc-reading-notebooks__grid"><label><span><?php esc_html_e( 'External source label', 'sustainable-catalyst-library' ); ?></span><input name="source_label"></label><label><span><?php esc_html_e( 'External URL', 'sustainable-catalyst-library' ); ?></span><input type="url" name="source_url"></label></div><div class="sc-reading-notebooks__grid"><label><span><?php esc_html_e( 'Locator type', 'sustainable-catalyst-library' ); ?></span><select name="locator_type"><?php foreach ( self::LOCATOR_TYPES as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e( 'Locator', 'sustainable-catalyst-library' ); ?></span><input name="locator_value" placeholder="<?php esc_attr_e( 'e.g. 17, Methods, 00:14:08', 'sustainable-catalyst-library' ); ?>"></label></div><label><span><?php esc_html_e( 'Selected passage', 'sustainable-catalyst-library' ); ?></span><textarea name="excerpt" rows="3" maxlength="<?php echo esc_attr( self::MAX_EXCERPT_CHARS ); ?>"></textarea></label><label><span><?php esc_html_e( 'Your annotation', 'sustainable-catalyst-library' ); ?></span><textarea name="body" rows="3"></textarea></label><label><span><?php esc_html_e( 'Tags', 'sustainable-catalyst-library' ); ?></span><input name="tags"></label><div class="sc-reading-notebooks__grid"><label><span><?php esc_html_e( 'Order', 'sustainable-catalyst-library' ); ?></span><input type="number" min="0" max="9999" name="position" value="0"></label><label class="sc-reading-notebooks__check"><input type="checkbox" name="pinned" value="1"><span><?php esc_html_e( 'Pin annotation', 'sustainable-catalyst-library' ); ?></span></label></div><button type="submit"><?php esc_html_e( 'Save annotation', 'sustainable-catalyst-library' ); ?></button></form></details>
                </section>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Reading, Notebook & Annotation Workspace', 'sustainable-catalyst-library' ) ), $atts, 'sc_reading_notebook_workspace' );
        wp_enqueue_style( 'sc-library-reading-notebooks-v4331' ); wp_enqueue_script( 'sc-library-reading-notebooks-v4331' );
        $signed_in = is_user_logged_in(); $user_id = $signed_in ? get_current_user_id() : 0;
        wp_localize_script( 'sc-library-reading-notebooks-v4331', 'SCLibraryReadingNotebooks', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::NONCE_ACTION ), 'signedIn' => $signed_in,
            'schema' => self::SCHEMA, 'noteSchema' => self::NOTE_SCHEMA, 'annotationSchema' => self::ANNOTATION_SCHEMA,
        ) );
        ob_start(); ?>
        <section class="sc-reading-notebooks" data-sc-reading-notebooks="v4.3.31">
            <header class="sc-reading-notebooks__header"><div><p><?php esc_html_e( 'Private reading continuity', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Read with context: keep account-persistent notes, reusable excerpts, page- or passage-level annotations, tags, and ordering beside the sources and Research Projects they serve.', 'sustainable-catalyst-library' ); ?></span></div><aside><strong><?php esc_html_e( 'Your interpretation stays yours', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Notes and excerpts are private user-authored records. The Library references underlying sources instead of copying source records or private files into the notebook.', 'sustainable-catalyst-library' ); ?></span></aside></header>
            <?php if ( ! $signed_in ) : ?><div class="sc-reading-notebooks__signin"><strong><?php esc_html_e( 'Sign in to keep reading notebooks across devices.', 'sustainable-catalyst-library' ); ?></strong><a href="<?php echo esc_url( $this->sign_in_url() ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
            <?php else : $projects = self::project_catalog_for_user( $user_id ); $catalog = self::source_catalog_for_user( $user_id ); $notebooks = self::notebooks_for_user( $user_id ); ?>
                <div class="sc-reading-notebooks__create"><form data-sc-reading-create-notebook><label><span><?php esc_html_e( 'Notebook title', 'sustainable-catalyst-library' ); ?></span><input name="title" required placeholder="<?php esc_attr_e( 'e.g. Climate adaptation reading notes', 'sustainable-catalyst-library' ); ?>"></label><?php echo self::render_context_select( $projects ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><button type="submit"><?php esc_html_e( 'Create reading notebook', 'sustainable-catalyst-library' ); ?></button><span data-sc-reading-status aria-live="polite"></span></form></div>
                <div class="sc-reading-notebooks__list"><?php if ( ! $notebooks ) : ?><p class="sc-reading-notebooks__empty"><strong><?php esc_html_e( 'No account-persistent reading notebooks yet.', 'sustainable-catalyst-library' ); ?></strong> <?php esc_html_e( 'Create one above, then attach it to a project or source bundle when useful.', 'sustainable-catalyst-library' ); ?></p><?php else : foreach ( $notebooks as $notebook_id ) { echo self::render_notebook_card( $notebook_id, $user_id, $projects, $catalog ); } endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <footer class="sc-reading-notebooks__contract"><strong><?php esc_html_e( 'v4.3.31 boundary', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'This release adds account persistence and project/source linkage. It does not auto-generate notes, promote annotations to evidence, publish private notebooks, or write into Workspace automatically.', 'sustainable-catalyst-library' ); ?></span></footer>
            <?php endif; ?>
        </section>
        <?php return ob_get_clean();
    }
}
