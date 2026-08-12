<?php
/**
 * Saved Searches, Watchlists & Research Queue.
 *
 * v4.3.29 adds durable private research-continuity records to the shared
 * Sustainable Catalyst account. Watchlists are passive revisit lists in this
 * release: they do not perform background monitoring or create notifications.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Saved_Searches_Watchlists_Queue {
    public const VERSION = '4.3.29';
    public const SCHEMA = 'sc-library-research-continuity/1.0';
    public const USER_META_SEARCHES = 'sc_library_saved_searches_v4329';
    public const USER_META_WATCHLISTS = 'sc_library_watchlists_v4329';
    public const USER_META_QUEUE = 'sc_library_research_queue_v4329';
    public const NONCE_ACTION = 'sc_library_research_continuity_v4329';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-continuity';
    public const MAX_SEARCHES = 100;
    public const MAX_WATCHLISTS = 100;
    public const MAX_QUEUE_ITEMS = 250;

    private const SEARCH_SCOPES = array(
        'all'                 => 'All research access',
        'sustainable-catalyst'=> 'Sustainable Catalyst',
        'libraries'           => 'Libraries',
        'scholarly'           => 'Scholarly research',
        'courses'             => 'Open courses',
        'external'            => 'External sources',
    );

    private const WATCH_KINDS = array(
        'topic'       => 'Topic',
        'query'       => 'Search query',
        'provider'    => 'Provider',
        'source'      => 'Source',
        'author'      => 'Author / creator',
        'institution' => 'Institution',
        'collection'  => 'Collection',
        'course'      => 'Course',
        'other'       => 'Other',
    );

    private const QUEUE_KINDS = array(
        'question' => 'Research question',
        'source'   => 'Source to review',
        'search'   => 'Search to run',
        'task'     => 'Research task',
        'course'   => 'Course to review',
        'dataset'  => 'Dataset to inspect',
        'other'    => 'Other',
    );

    private const QUEUE_STATUSES = array(
        'queued'   => 'Queued',
        'active'   => 'Active',
        'done'     => 'Done',
        'archived' => 'Archived',
    );

    private const PRIORITIES = array(
        'low'    => 'Low',
        'normal' => 'Normal',
        'high'   => 'High',
    );

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_research_continuity', array( $this, 'shortcode' ) );

        add_action( 'wp_ajax_sc_library_v4329_save_search', array( $this, 'ajax_save_search' ) );
        add_action( 'wp_ajax_sc_library_v4329_delete_search', array( $this, 'ajax_delete_search' ) );
        add_action( 'wp_ajax_sc_library_v4329_add_watch', array( $this, 'ajax_add_watch' ) );
        add_action( 'wp_ajax_sc_library_v4329_mark_watch_reviewed', array( $this, 'ajax_mark_watch_reviewed' ) );
        add_action( 'wp_ajax_sc_library_v4329_delete_watch', array( $this, 'ajax_delete_watch' ) );
        add_action( 'wp_ajax_sc_library_v4329_add_queue_item', array( $this, 'ajax_add_queue_item' ) );
        add_action( 'wp_ajax_sc_library_v4329_update_queue_item', array( $this, 'ajax_update_queue_item' ) );
        add_action( 'wp_ajax_sc_library_v4329_delete_queue_item', array( $this, 'ajax_delete_queue_item' ) );

        // Stable handoff boundaries for later Library/Workspace integration.
        add_action( 'sc_library_save_search', array( $this, 'action_save_search' ), 10, 2 );
        add_action( 'sc_library_add_watchlist_item', array( $this, 'action_add_watch' ), 10, 2 );
        add_action( 'sc_library_enqueue_research_item', array( $this, 'action_enqueue_item' ), 10, 2 );
        add_filter( 'sc_library_research_continuity_state', array( $this, 'filter_state' ), 10, 2 );
    }

    public function register_assets() {
        wp_register_style(
            'sc-library-research-continuity-v4329',
            SC_LIBRARY_URL . 'assets/css/sc-library-research-continuity-v4329.css',
            array(),
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-research-continuity-v4329',
            SC_LIBRARY_URL . 'assets/js/sc-library-research-continuity-v4329.js',
            array(),
            SC_LIBRARY_VERSION,
            true
        );
    }

    public static function continuity_contract() {
        return array(
            'schema'                    => self::SCHEMA,
            'record_owner'              => 'current-wordpress-user',
            'visibility'                => 'private',
            'workspace_account_continuity' => true,
            'watchlists_are_passive'    => true,
            'background_monitoring'     => false,
            'automatic_notifications'   => false,
            'automatic_publication'     => false,
            'automatic_editorial_promotion' => false,
        );
    }

    public static function search_scopes() { return self::SEARCH_SCOPES; }
    public static function watch_kinds() { return self::WATCH_KINDS; }
    public static function queue_kinds() { return self::QUEUE_KINDS; }
    public static function queue_statuses() { return self::QUEUE_STATUSES; }
    public static function priorities() { return self::PRIORITIES; }

    private static function enum_value( $value, $allowed, $fallback ) {
        $value = sanitize_key( (string) $value );
        return array_key_exists( $value, $allowed ) ? $value : $fallback;
    }

    private static function clean_text( $value, $limit = 180 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function new_id( $prefix ) {
        return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( $prefix . '-', true );
    }

    private static function now() {
        return current_time( 'mysql', true );
    }

    private static function read_meta_array( $user_id, $key, $limit ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }
        $records = get_user_meta( $user_id, $key, true );
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        return array_slice( $records, -absint( $limit ) );
    }

    private static function write_meta_array( $user_id, $key, $records, $limit ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return false; }
        $records = is_array( $records ) ? array_values( array_filter( $records, 'is_array' ) ) : array();
        $records = array_slice( $records, -absint( $limit ) );
        update_user_meta( $user_id, $key, $records );
        return true;
    }

    private static function sanitize_search( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_id( 'scss' ); }
        return array(
            'id'         => $id,
            'label'      => self::clean_text( $input['label'] ?? '', 120 ),
            'query'      => self::clean_text( $input['query'] ?? '', 300 ),
            'scope'      => self::enum_value( $input['scope'] ?? '', self::SEARCH_SCOPES, 'all' ),
            'provider'   => self::clean_text( $input['provider'] ?? '', 120 ),
            'filters'    => sanitize_textarea_field( (string) ( $input['filters'] ?? '' ) ),
            'notes'      => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
            'visibility' => 'private',
            'created_at' => self::clean_text( $input['created_at'] ?? '', 40 ),
            'updated_at' => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    private static function sanitize_watch( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_id( 'scwl' ); }
        return array(
            'id'              => $id,
            'label'           => self::clean_text( $input['label'] ?? '', 160 ),
            'kind'            => self::enum_value( $input['kind'] ?? '', self::WATCH_KINDS, 'topic' ),
            'target'          => self::clean_text( $input['target'] ?? '', 300 ),
            'url'             => esc_url_raw( (string) ( $input['url'] ?? '' ) ),
            'notes'           => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
            'last_reviewed_at'=> self::clean_text( $input['last_reviewed_at'] ?? '', 40 ),
            'visibility'      => 'private',
            'monitoring'      => 'passive',
            'created_at'      => self::clean_text( $input['created_at'] ?? '', 40 ),
            'updated_at'      => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    private static function sanitize_queue_item( $input, $generate_id = true ) {
        $input = is_array( $input ) ? $input : array();
        $id = self::clean_text( $input['id'] ?? '', 80 );
        if ( $generate_id && '' === $id ) { $id = self::new_id( 'scrq' ); }
        return array(
            'id'         => $id,
            'title'      => self::clean_text( $input['title'] ?? '', 220 ),
            'kind'       => self::enum_value( $input['kind'] ?? '', self::QUEUE_KINDS, 'question' ),
            'status'     => self::enum_value( $input['status'] ?? '', self::QUEUE_STATUSES, 'queued' ),
            'priority'   => self::enum_value( $input['priority'] ?? '', self::PRIORITIES, 'normal' ),
            'url'        => esc_url_raw( (string) ( $input['url'] ?? '' ) ),
            'source_ref' => self::clean_text( $input['source_ref'] ?? '', 160 ),
            'notes'      => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
            'visibility' => 'private',
            'created_at' => self::clean_text( $input['created_at'] ?? '', 40 ),
            'updated_at' => self::clean_text( $input['updated_at'] ?? '', 40 ),
        );
    }

    public static function searches_for_user( $user_id ) {
        $out = array();
        foreach ( self::read_meta_array( $user_id, self::USER_META_SEARCHES, self::MAX_SEARCHES ) as $record ) {
            $clean = self::sanitize_search( $record, false );
            if ( $clean['id'] && $clean['query'] ) { $out[] = $clean; }
        }
        return $out;
    }

    public static function watchlists_for_user( $user_id ) {
        $out = array();
        foreach ( self::read_meta_array( $user_id, self::USER_META_WATCHLISTS, self::MAX_WATCHLISTS ) as $record ) {
            $clean = self::sanitize_watch( $record, false );
            if ( $clean['id'] && $clean['label'] ) { $out[] = $clean; }
        }
        return $out;
    }

    public static function queue_for_user( $user_id ) {
        $out = array();
        foreach ( self::read_meta_array( $user_id, self::USER_META_QUEUE, self::MAX_QUEUE_ITEMS ) as $record ) {
            $clean = self::sanitize_queue_item( $record, false );
            if ( $clean['id'] && $clean['title'] ) { $out[] = $clean; }
        }
        return $out;
    }

    public static function state_for_user( $user_id ) {
        return array(
            'schema'         => self::SCHEMA,
            'version'        => self::VERSION,
            'visibility'     => 'private',
            'user_id'        => absint( $user_id ),
            'saved_searches' => self::searches_for_user( $user_id ),
            'watchlists'     => self::watchlists_for_user( $user_id ),
            'research_queue' => self::queue_for_user( $user_id ),
            'contract'       => self::continuity_contract(),
        );
    }

    public static function add_search_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_library_saved_search_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) ); }
        $record = self::sanitize_search( $input, true );
        if ( '' === $record['query'] ) { return new WP_Error( 'sc_library_saved_search_query_required', __( 'Enter a search query.', 'sustainable-catalyst-library' ) ); }
        if ( '' === $record['label'] ) { $record['label'] = $record['query']; }
        $record['created_at'] = self::now(); $record['updated_at'] = $record['created_at'];
        $records = self::searches_for_user( $user_id ); $records[] = $record;
        self::write_meta_array( $user_id, self::USER_META_SEARCHES, $records, self::MAX_SEARCHES );
        do_action( 'sc_library_saved_search_created', $record, $user_id );
        return $record;
    }

    public static function add_watch_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_library_watch_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) ); }
        $record = self::sanitize_watch( $input, true );
        if ( '' === $record['label'] ) { return new WP_Error( 'sc_library_watch_label_required', __( 'Enter something to watch.', 'sustainable-catalyst-library' ) ); }
        $record['created_at'] = self::now(); $record['updated_at'] = $record['created_at'];
        $records = self::watchlists_for_user( $user_id ); $records[] = $record;
        self::write_meta_array( $user_id, self::USER_META_WATCHLISTS, $records, self::MAX_WATCHLISTS );
        do_action( 'sc_library_watchlist_item_created', $record, $user_id );
        return $record;
    }

    public static function add_queue_item_for_user( $user_id, $input ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_library_queue_no_user', __( 'A signed-in account is required.', 'sustainable-catalyst-library' ) ); }
        $record = self::sanitize_queue_item( $input, true );
        if ( '' === $record['title'] ) { return new WP_Error( 'sc_library_queue_title_required', __( 'Enter a research question, source, or task.', 'sustainable-catalyst-library' ) ); }
        $record['created_at'] = self::now(); $record['updated_at'] = $record['created_at'];
        $records = self::queue_for_user( $user_id ); $records[] = $record;
        self::write_meta_array( $user_id, self::USER_META_QUEUE, $records, self::MAX_QUEUE_ITEMS );
        do_action( 'sc_library_research_queue_item_created', $record, $user_id );
        return $record;
    }

    private static function delete_record( $user_id, $key, $limit, $id ) {
        $id = self::clean_text( $id, 80 );
        $records = self::read_meta_array( $user_id, $key, $limit );
        $next = array(); $deleted = false;
        foreach ( $records as $record ) {
            if ( (string) ( $record['id'] ?? '' ) === $id ) { $deleted = true; continue; }
            $next[] = $record;
        }
        if ( $deleted ) { self::write_meta_array( $user_id, $key, $next, $limit ); }
        return $deleted;
    }

    public static function mark_watch_reviewed_for_user( $user_id, $id ) {
        $id = self::clean_text( $id, 80 );
        $records = self::watchlists_for_user( $user_id ); $found = false;
        foreach ( $records as &$record ) {
            if ( $record['id'] !== $id ) { continue; }
            $record['last_reviewed_at'] = self::now(); $record['updated_at'] = $record['last_reviewed_at']; $found = $record; break;
        }
        unset( $record );
        if ( $found ) { self::write_meta_array( $user_id, self::USER_META_WATCHLISTS, $records, self::MAX_WATCHLISTS ); }
        return $found;
    }

    public static function update_queue_item_for_user( $user_id, $id, $input ) {
        $id = self::clean_text( $id, 80 );
        $records = self::queue_for_user( $user_id ); $found = false;
        foreach ( $records as &$record ) {
            if ( $record['id'] !== $id ) { continue; }
            $merged = array_merge( $record, is_array( $input ) ? $input : array(), array( 'id' => $id, 'created_at' => $record['created_at'] ) );
            $record = self::sanitize_queue_item( $merged, false );
            $record['updated_at'] = self::now(); $found = $record; break;
        }
        unset( $record );
        if ( $found ) { self::write_meta_array( $user_id, self::USER_META_QUEUE, $records, self::MAX_QUEUE_ITEMS ); }
        return $found ?: new WP_Error( 'sc_library_queue_not_found', __( 'That research queue item was not found.', 'sustainable-catalyst-library' ) );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => array( $this, 'rest_can_read' ),
            'callback' => array( $this, 'rest_state' ),
        ) );
    }

    public function rest_can_read() { return is_user_logged_in(); }
    public function rest_state() { return rest_ensure_response( self::state_for_user( get_current_user_id() ) ); }

    private function require_ajax_user() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => __( 'Sign in to manage saved research.', 'sustainable-catalyst-library' ) ), 401 ); }
        return get_current_user_id();
    }

    private function request_payload( $keys ) {
        $payload = array();
        foreach ( $keys as $key ) { $payload[ $key ] = wp_unslash( $_POST[ $key ] ?? '' ); }
        return $payload;
    }

    public function ajax_save_search() {
        $record = self::add_search_for_user( $this->require_ajax_user(), $this->request_payload( array( 'label','query','scope','provider','filters','notes' ) ) );
        if ( is_wp_error( $record ) ) { wp_send_json_error( array( 'message' => $record->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'record' => $record, 'message' => __( 'Search saved.', 'sustainable-catalyst-library' ) ) );
    }
    public function ajax_delete_search() {
        $user_id = $this->require_ajax_user(); $id = wp_unslash( $_POST['record_id'] ?? '' );
        $ok = self::delete_record( $user_id, self::USER_META_SEARCHES, self::MAX_SEARCHES, $id );
        $ok ? wp_send_json_success( array( 'message' => __( 'Saved search removed.', 'sustainable-catalyst-library' ) ) ) : wp_send_json_error( array( 'message' => __( 'Saved search not found.', 'sustainable-catalyst-library' ) ), 404 );
    }
    public function ajax_add_watch() {
        $record = self::add_watch_for_user( $this->require_ajax_user(), $this->request_payload( array( 'label','kind','target','url','notes' ) ) );
        if ( is_wp_error( $record ) ) { wp_send_json_error( array( 'message' => $record->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'record' => $record, 'message' => __( 'Added to watchlist.', 'sustainable-catalyst-library' ) ) );
    }
    public function ajax_mark_watch_reviewed() {
        $record = self::mark_watch_reviewed_for_user( $this->require_ajax_user(), wp_unslash( $_POST['record_id'] ?? '' ) );
        $record ? wp_send_json_success( array( 'record' => $record, 'message' => __( 'Marked reviewed.', 'sustainable-catalyst-library' ) ) ) : wp_send_json_error( array( 'message' => __( 'Watchlist item not found.', 'sustainable-catalyst-library' ) ), 404 );
    }
    public function ajax_delete_watch() {
        $user_id = $this->require_ajax_user(); $ok = self::delete_record( $user_id, self::USER_META_WATCHLISTS, self::MAX_WATCHLISTS, wp_unslash( $_POST['record_id'] ?? '' ) );
        $ok ? wp_send_json_success( array( 'message' => __( 'Watchlist item removed.', 'sustainable-catalyst-library' ) ) ) : wp_send_json_error( array( 'message' => __( 'Watchlist item not found.', 'sustainable-catalyst-library' ) ), 404 );
    }
    public function ajax_add_queue_item() {
        $record = self::add_queue_item_for_user( $this->require_ajax_user(), $this->request_payload( array( 'title','kind','status','priority','url','source_ref','notes' ) ) );
        if ( is_wp_error( $record ) ) { wp_send_json_error( array( 'message' => $record->get_error_message() ), 400 ); }
        wp_send_json_success( array( 'record' => $record, 'message' => __( 'Added to research queue.', 'sustainable-catalyst-library' ) ) );
    }
    public function ajax_update_queue_item() {
        $user_id = $this->require_ajax_user();
        $record = self::update_queue_item_for_user( $user_id, wp_unslash( $_POST['record_id'] ?? '' ), $this->request_payload( array( 'title','kind','status','priority','url','source_ref','notes' ) ) );
        if ( is_wp_error( $record ) ) { wp_send_json_error( array( 'message' => $record->get_error_message() ), 404 ); }
        wp_send_json_success( array( 'record' => $record, 'message' => __( 'Research queue updated.', 'sustainable-catalyst-library' ) ) );
    }
    public function ajax_delete_queue_item() {
        $user_id = $this->require_ajax_user(); $ok = self::delete_record( $user_id, self::USER_META_QUEUE, self::MAX_QUEUE_ITEMS, wp_unslash( $_POST['record_id'] ?? '' ) );
        $ok ? wp_send_json_success( array( 'message' => __( 'Research queue item removed.', 'sustainable-catalyst-library' ) ) ) : wp_send_json_error( array( 'message' => __( 'Research queue item not found.', 'sustainable-catalyst-library' ) ), 404 );
    }

    public function action_save_search( $search, $user_id = 0 ) { $user_id = absint( $user_id ) ?: get_current_user_id(); if ( $user_id && is_array( $search ) ) { self::add_search_for_user( $user_id, $search ); } }
    public function action_add_watch( $watch, $user_id = 0 ) { $user_id = absint( $user_id ) ?: get_current_user_id(); if ( $user_id && is_array( $watch ) ) { self::add_watch_for_user( $user_id, $watch ); } }
    public function action_enqueue_item( $item, $user_id = 0 ) { $user_id = absint( $user_id ) ?: get_current_user_id(); if ( $user_id && is_array( $item ) ) { self::add_queue_item_for_user( $user_id, $item ); } }
    public function filter_state( $state, $user_id ) { return absint( $user_id ) ? self::state_for_user( $user_id ) : ( is_array( $state ) ? $state : array() ); }

    private function options( $values, $selected = '' ) {
        $html = '';
        foreach ( $values as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        return $html;
    }

    private function sign_in_url() { return wp_login_url( home_url( '/knowledge-libraries/#saved-research' ) ); }

    private function render_saved_searches( $records ) {
        if ( ! $records ) { return '<p class="sc-research-continuity__empty">' . esc_html__( 'No saved searches yet.', 'sustainable-catalyst-library' ) . '</p>'; }
        $html = '<div class="sc-research-continuity__records">';
        foreach ( array_reverse( $records ) as $record ) {
            $html .= '<article class="sc-research-continuity__record"><small>' . esc_html( self::SEARCH_SCOPES[ $record['scope'] ] ?? self::SEARCH_SCOPES['all'] ) . '</small><h4>' . esc_html( $record['label'] ) . '</h4><p><code>' . esc_html( $record['query'] ) . '</code></p>';
            if ( $record['provider'] ) { $html .= '<p>' . esc_html__( 'Provider:', 'sustainable-catalyst-library' ) . ' ' . esc_html( $record['provider'] ) . '</p>'; }
            if ( $record['notes'] ) { $html .= '<p>' . esc_html( $record['notes'] ) . '</p>'; }
            $html .= '<button type="button" data-sc-continuity-delete-search="' . esc_attr( $record['id'] ) . '">' . esc_html__( 'Remove', 'sustainable-catalyst-library' ) . '</button></article>';
        }
        return $html . '</div>';
    }

    private function render_watchlists( $records ) {
        if ( ! $records ) { return '<p class="sc-research-continuity__empty">' . esc_html__( 'No watchlist items yet.', 'sustainable-catalyst-library' ) . '</p>'; }
        $html = '<div class="sc-research-continuity__records">';
        foreach ( array_reverse( $records ) as $record ) {
            $html .= '<article class="sc-research-continuity__record"><small>' . esc_html( self::WATCH_KINDS[ $record['kind'] ] ?? self::WATCH_KINDS['other'] ) . ' · ' . esc_html__( 'Passive watchlist', 'sustainable-catalyst-library' ) . '</small><h4>' . esc_html( $record['label'] ) . '</h4>';
            if ( $record['target'] ) { $html .= '<p>' . esc_html( $record['target'] ) . '</p>'; }
            if ( $record['last_reviewed_at'] ) { $html .= '<p class="sc-research-continuity__meta">' . esc_html__( 'Last reviewed:', 'sustainable-catalyst-library' ) . ' ' . esc_html( $record['last_reviewed_at'] ) . ' UTC</p>'; }
            if ( $record['url'] ) { $html .= '<p><a href="' . esc_url( $record['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open target →', 'sustainable-catalyst-library' ) . '</a></p>'; }
            $html .= '<div class="sc-research-continuity__buttons"><button type="button" data-sc-continuity-review-watch="' . esc_attr( $record['id'] ) . '">' . esc_html__( 'Mark reviewed', 'sustainable-catalyst-library' ) . '</button><button type="button" data-sc-continuity-delete-watch="' . esc_attr( $record['id'] ) . '">' . esc_html__( 'Remove', 'sustainable-catalyst-library' ) . '</button></div></article>';
        }
        return $html . '</div>';
    }

    private function render_queue( $records ) {
        if ( ! $records ) { return '<p class="sc-research-continuity__empty">' . esc_html__( 'Your research queue is empty.', 'sustainable-catalyst-library' ) . '</p>'; }
        $html = '<div class="sc-research-continuity__records">';
        foreach ( array_reverse( $records ) as $record ) {
            $html .= '<article class="sc-research-continuity__record" data-sc-queue-record><small>' . esc_html( self::QUEUE_KINDS[ $record['kind'] ] ?? self::QUEUE_KINDS['other'] ) . ' · ' . esc_html( self::PRIORITIES[ $record['priority'] ] ?? self::PRIORITIES['normal'] ) . '</small><h4>' . esc_html( $record['title'] ) . '</h4>';
            if ( $record['notes'] ) { $html .= '<p>' . esc_html( $record['notes'] ) . '</p>'; }
            if ( $record['url'] ) { $html .= '<p><a href="' . esc_url( $record['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open reference →', 'sustainable-catalyst-library' ) . '</a></p>'; }
            $html .= '<form data-sc-continuity-update-queue><input type="hidden" name="record_id" value="' . esc_attr( $record['id'] ) . '"><input type="hidden" name="title" value="' . esc_attr( $record['title'] ) . '"><input type="hidden" name="kind" value="' . esc_attr( $record['kind'] ) . '"><input type="hidden" name="priority" value="' . esc_attr( $record['priority'] ) . '"><input type="hidden" name="url" value="' . esc_attr( $record['url'] ) . '"><input type="hidden" name="source_ref" value="' . esc_attr( $record['source_ref'] ) . '"><input type="hidden" name="notes" value="' . esc_attr( $record['notes'] ) . '"><label><span>' . esc_html__( 'Status', 'sustainable-catalyst-library' ) . '</span><select name="status">' . $this->options( self::QUEUE_STATUSES, $record['status'] ) . '</select></label><button type="submit">' . esc_html__( 'Update', 'sustainable-catalyst-library' ) . '</button><button type="button" data-sc-continuity-delete-queue="' . esc_attr( $record['id'] ) . '">' . esc_html__( 'Remove', 'sustainable-catalyst-library' ) . '</button><span aria-live="polite"></span></form></article>';
        }
        return $html . '</div>';
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => 'Saved Research & Queue' ), $atts, 'sc_research_continuity' );
        wp_enqueue_style( 'sc-library-research-continuity-v4329' ); wp_enqueue_script( 'sc-library-research-continuity-v4329' );
        $signed_in = is_user_logged_in(); $user_id = $signed_in ? get_current_user_id() : 0;
        $searches = $user_id ? self::searches_for_user( $user_id ) : array(); $watchlists = $user_id ? self::watchlists_for_user( $user_id ) : array(); $queue = $user_id ? self::queue_for_user( $user_id ) : array();
        wp_localize_script( 'sc-library-research-continuity-v4329', 'SCLibraryResearchContinuity', array( 'ajaxUrl'=>admin_url( 'admin-ajax.php' ), 'nonce'=>wp_create_nonce( self::NONCE_ACTION ), 'signedIn'=>$signed_in, 'schema'=>self::SCHEMA ) );
        ob_start(); ?>
        <section class="sc-research-continuity" data-sc-research-continuity="v4.3.29">
            <header class="sc-research-continuity__header"><div><p class="sc-research-continuity__kicker"><?php esc_html_e( 'Private research continuity', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><p><?php esc_html_e( 'Save searches you want to repeat, keep passive watchlists of subjects or providers to revisit, and maintain a research queue of questions, sources, searches, and tasks.', 'sustainable-catalyst-library' ); ?></p></div><aside><strong><?php esc_html_e( 'Private by default', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'These records stay with your Sustainable Catalyst account. Watchlists in v4.3.29 do not run background monitoring or notifications.', 'sustainable-catalyst-library' ); ?></span></aside></header>
            <?php if ( ! $signed_in ) : ?><div class="sc-research-continuity__signin"><strong><?php esc_html_e( 'Sign in to save research continuity.', 'sustainable-catalyst-library' ); ?></strong><a href="<?php echo esc_url( $this->sign_in_url() ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
            <?php else : ?>
            <div class="sc-research-continuity__summary"><div><strong><?php echo esc_html( count( $searches ) ); ?></strong><span><?php esc_html_e( 'saved searches', 'sustainable-catalyst-library' ); ?></span></div><div><strong><?php echo esc_html( count( $watchlists ) ); ?></strong><span><?php esc_html_e( 'watchlist items', 'sustainable-catalyst-library' ); ?></span></div><div><strong><?php echo esc_html( count( $queue ) ); ?></strong><span><?php esc_html_e( 'queue items', 'sustainable-catalyst-library' ); ?></span></div></div>
            <div class="sc-research-continuity__columns">
                <section><h4><?php esc_html_e( 'Saved Searches', 'sustainable-catalyst-library' ); ?></h4><details><summary><?php esc_html_e( 'Save a search', 'sustainable-catalyst-library' ); ?></summary><form data-sc-continuity-search-form><label><span><?php esc_html_e( 'Label', 'sustainable-catalyst-library' ); ?></span><input name="label" maxlength="120"></label><label><span><?php esc_html_e( 'Query', 'sustainable-catalyst-library' ); ?></span><input name="query" required maxlength="300"></label><label><span><?php esc_html_e( 'Scope', 'sustainable-catalyst-library' ); ?></span><select name="scope"><?php echo $this->options( self::SEARCH_SCOPES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label><label><span><?php esc_html_e( 'Provider (optional)', 'sustainable-catalyst-library' ); ?></span><input name="provider" maxlength="120"></label><label><span><?php esc_html_e( 'Filters / constraints', 'sustainable-catalyst-library' ); ?></span><textarea name="filters" rows="2"></textarea></label><label><span><?php esc_html_e( 'Private notes', 'sustainable-catalyst-library' ); ?></span><textarea name="notes" rows="2"></textarea></label><button type="submit"><?php esc_html_e( 'Save search', 'sustainable-catalyst-library' ); ?></button><span aria-live="polite"></span></form></details><?php echo $this->render_saved_searches( $searches ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
                <section><h4><?php esc_html_e( 'Watchlists', 'sustainable-catalyst-library' ); ?></h4><p class="sc-research-continuity__note"><?php esc_html_e( 'A watchlist is a reminder to revisit something. It is not an automated alert.', 'sustainable-catalyst-library' ); ?></p><details><summary><?php esc_html_e( 'Add watchlist item', 'sustainable-catalyst-library' ); ?></summary><form data-sc-continuity-watch-form><label><span><?php esc_html_e( 'Label', 'sustainable-catalyst-library' ); ?></span><input name="label" required maxlength="160"></label><label><span><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></span><select name="kind"><?php echo $this->options( self::WATCH_KINDS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label><label><span><?php esc_html_e( 'Target / query', 'sustainable-catalyst-library' ); ?></span><input name="target" maxlength="300"></label><label><span><?php esc_html_e( 'URL (optional)', 'sustainable-catalyst-library' ); ?></span><input name="url" type="url"></label><label><span><?php esc_html_e( 'Private notes', 'sustainable-catalyst-library' ); ?></span><textarea name="notes" rows="2"></textarea></label><button type="submit"><?php esc_html_e( 'Add to watchlist', 'sustainable-catalyst-library' ); ?></button><span aria-live="polite"></span></form></details><?php echo $this->render_watchlists( $watchlists ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
                <section><h4><?php esc_html_e( 'Research Queue', 'sustainable-catalyst-library' ); ?></h4><details><summary><?php esc_html_e( 'Add queue item', 'sustainable-catalyst-library' ); ?></summary><form data-sc-continuity-queue-form><label><span><?php esc_html_e( 'Question, source, or task', 'sustainable-catalyst-library' ); ?></span><input name="title" required maxlength="220"></label><label><span><?php esc_html_e( 'Type', 'sustainable-catalyst-library' ); ?></span><select name="kind"><?php echo $this->options( self::QUEUE_KINDS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label><label><span><?php esc_html_e( 'Priority', 'sustainable-catalyst-library' ); ?></span><select name="priority"><?php echo $this->options( self::PRIORITIES, 'normal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label><input type="hidden" name="status" value="queued"><label><span><?php esc_html_e( 'URL (optional)', 'sustainable-catalyst-library' ); ?></span><input name="url" type="url"></label><label><span><?php esc_html_e( 'Source/reference ID', 'sustainable-catalyst-library' ); ?></span><input name="source_ref" maxlength="160"></label><label><span><?php esc_html_e( 'Private notes', 'sustainable-catalyst-library' ); ?></span><textarea name="notes" rows="2"></textarea></label><button type="submit"><?php esc_html_e( 'Add to queue', 'sustainable-catalyst-library' ); ?></button><span aria-live="polite"></span></form></details><?php echo $this->render_queue( $queue ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
            </div><?php endif; ?>
        </section><?php return ob_get_clean();
    }
}
