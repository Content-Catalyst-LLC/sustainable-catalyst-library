<?php
/**
 * Institutional & Team Libraries — v4.7.0.
 *
 * Durable, private organizational curation spaces that reuse the canonical
 * v4.0 institutional identities when linked to an institution/research unit.
 * Team Libraries are not Research Rooms, do not transfer ownership of personal
 * research, and expose only references explicitly contributed to the team.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Institutional_Team_Libraries {
    public const VERSION = '4.7.0';
    public const SCHEMA = 'sc-library-institutional-team-library/1.0';
    public const MEMBER_SCHEMA = 'sc-library-team-library-member/1.0';
    public const COLLECTION_SCHEMA = 'sc-library-team-library-collection/1.0';
    public const REFERENCE_SCHEMA = 'sc-library-team-library-reference/1.0';
    public const ACTIVITY_SCHEMA = 'sc-library-team-library-activity/1.0';
    public const POST_TYPE = 'sc_team_library';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/team-libraries';
    public const META_LIBRARY_URN = '_sc_team_library_urn_v470';
    public const META_INSTITUTION_ID = '_sc_team_library_institution_id_v470';
    public const META_UNIT_ID = '_sc_team_library_unit_id_v470';
    public const META_MEMBERS = '_sc_team_library_members_v470';
    public const META_COLLECTIONS = '_sc_team_library_collections_v470';
    public const META_REFERENCES = '_sc_team_library_references_v470';
    public const META_ACTIVITY = '_sc_team_library_activity_v470';
    public const MAX_LIBRARIES_PER_OWNER = 30;
    public const MAX_MEMBERS = 80;
    public const MAX_COLLECTIONS = 80;
    public const MAX_REFERENCES = 600;
    public const MAX_ACTIVITY = 500;

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_institutional_team_libraries', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_personal_research_environment_state_payload', array( $this, 'filter_personal_environment' ), 20, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'visibility' => 'private-members-only',
            'identity_source' => 'wordpress',
            'same_library_workspace_account' => true,
            'canonical_institution_registry_reused' => true,
            'canonical_unit_registry_reused' => true,
            'creates_parallel_institution_registry' => false,
            'institutional_binding_is_context_not_entitlement' => true,
            'owner_is_post_author' => true,
            'membership_grants_personal_library_access' => false,
            'membership_grants_project_access' => false,
            'membership_grants_research_room_access' => false,
            'explicit_contribution_required' => true,
            'references_only' => true,
            'copy_private_source_binaries' => false,
            'copy_personal_library_contents' => false,
            'copy_notebook_bodies' => false,
            'copy_matrix_bodies' => false,
            'copy_project_bodies' => false,
            'automatic_publication' => false,
            'automatic_evidence_promotion' => false,
            'automatic_workspace_write' => false,
            'activity_is_append_only_lineage' => true,
        );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array( 'name' => __( 'Team Libraries', 'sustainable-catalyst-library' ), 'singular_name' => __( 'Team Library', 'sustainable-catalyst-library' ) ),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => array( 'title', 'author' ),
            'map_meta_cap' => true,
        ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-team-libraries-v470', SC_LIBRARY_URL . 'assets/css/sc-library-team-libraries-v470.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-team-libraries-v470', SC_LIBRARY_URL . 'assets/js/sc-library-team-libraries-v470.js', array(), SC_LIBRARY_VERSION, true );
    }

    private static function clean( $value, $limit = 240 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }
    private static function clean_area( $value, $limit = 1200 ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }
    private static function now() { return gmdate( 'c' ); }
    private static function urn( $kind ) { return 'urn:sc:' . sanitize_key( $kind ) . ':' . wp_generate_uuid4(); }

    public static function roles() {
        return array(
            'owner'       => array( 'manage_members' => true,  'manage_collections' => true,  'contribute' => true,  'govern' => true ),
            'steward'     => array( 'manage_members' => true,  'manage_collections' => true,  'contribute' => true,  'govern' => true ),
            'editor'      => array( 'manage_members' => false, 'manage_collections' => true,  'contribute' => true,  'govern' => false ),
            'contributor' => array( 'manage_members' => false, 'manage_collections' => false, 'contribute' => true,  'govern' => false ),
            'reader'      => array( 'manage_members' => false, 'manage_collections' => false, 'contribute' => false, 'govern' => false ),
        );
    }

    public static function members( $library_id ) {
        $raw = get_post_meta( absint( $library_id ), self::META_MEMBERS, true );
        $raw = is_array( $raw ) ? $raw : array(); $out = array();
        foreach ( $raw as $key => $member ) {
            if ( ! is_array( $member ) ) { continue; }
            $uid = absint( $member['user_id'] ?? $key ); if ( ! $uid ) { continue; }
            $role = sanitize_key( (string) ( $member['role'] ?? 'reader' ) );
            if ( ! isset( self::roles()[ $role ] ) || 'owner' === $role ) { $role = 'reader'; }
            $out[ $uid ] = array(
                'schema' => self::MEMBER_SCHEMA,
                'user_id' => $uid,
                'role' => $role,
                'status' => in_array( (string) ( $member['status'] ?? 'active' ), array( 'active', 'removed' ), true ) ? (string) $member['status'] : 'active',
                'added_at' => self::clean( $member['added_at'] ?? '', 80 ),
                'added_by' => absint( $member['added_by'] ?? 0 ),
            );
        }
        return $out;
    }

    public static function user_role( $library_id, $user_id ) {
        $post = get_post( absint( $library_id ) ); $user_id = absint( $user_id );
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'private' !== $post->post_status || ! $user_id ) { return ''; }
        if ( absint( $post->post_author ) === $user_id ) { return 'owner'; }
        $members = self::members( $library_id );
        return isset( $members[ $user_id ] ) && 'active' === ( $members[ $user_id ]['status'] ?? '' ) ? sanitize_key( (string) $members[ $user_id ]['role'] ) : '';
    }

    public static function can( $library_id, $user_id, $capability ) {
        $role = self::user_role( $library_id, $user_id ); if ( '' === $role ) { return false; }
        if ( 'read' === $capability ) { return true; }
        $roles = self::roles(); return ! empty( $roles[ $role ][ $capability ] );
    }

    public static function library_ids_for_user( $user_id ) {
        $user_id = absint( $user_id ); if ( ! $user_id ) { return array(); }
        $owned = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'author' => $user_id, 'fields' => 'ids', 'posts_per_page' => self::MAX_LIBRARIES_PER_OWNER, 'orderby' => 'modified', 'order' => 'DESC' ) );
        $all = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 250, 'orderby' => 'modified', 'order' => 'DESC' ) );
        $ids = array_map( 'absint', $owned );
        foreach ( $all as $id ) { $id = absint( $id ); if ( in_array( $id, $ids, true ) ) { continue; } $m = self::members( $id ); if ( isset( $m[ $user_id ] ) && 'active' === ( $m[ $user_id ]['status'] ?? '' ) ) { $ids[] = $id; } }
        return array_slice( array_values( array_unique( $ids ) ), 0, 120 );
    }

    private static function valid_institution( $institution_id ) {
        $institution_id = absint( $institution_id ); if ( ! $institution_id ) { return 0; }
        $post = get_post( $institution_id );
        return $post instanceof WP_Post && 'sc_institution' === $post->post_type ? $institution_id : 0;
    }
    private static function valid_unit( $unit_id ) {
        $unit_id = absint( $unit_id ); if ( ! $unit_id ) { return 0; }
        $post = get_post( $unit_id );
        return $post instanceof WP_Post && 'sc_research_unit' === $post->post_type ? $unit_id : 0;
    }

    private static function append_activity( $library_id, $actor_id, $action, $object_kind, $object_id = '', $summary = '' ) {
        $items = get_post_meta( absint( $library_id ), self::META_ACTIVITY, true ); $items = is_array( $items ) ? $items : array();
        $items[] = array( 'schema' => self::ACTIVITY_SCHEMA, 'activity_id' => self::urn( 'team-library-activity' ), 'actor_id' => absint( $actor_id ), 'action' => sanitize_key( (string) $action ), 'object_kind' => sanitize_key( (string) $object_kind ), 'object_id' => self::clean( $object_id, 360 ), 'summary' => self::clean( $summary, 500 ), 'created_at' => self::now() );
        if ( count( $items ) > self::MAX_ACTIVITY ) { $items = array_slice( $items, -self::MAX_ACTIVITY ); }
        update_post_meta( absint( $library_id ), self::META_ACTIVITY, $items );
    }

    public static function create_library( $owner_id, array $input ) {
        $owner_id = absint( $owner_id ); if ( ! $owner_id ) { return new WP_Error( 'sc_team_library_signin', __( 'Sign in to create a team library.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        $owned = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'author' => $owner_id, 'fields' => 'ids', 'posts_per_page' => self::MAX_LIBRARIES_PER_OWNER ) );
        if ( count( $owned ) >= self::MAX_LIBRARIES_PER_OWNER ) { return new WP_Error( 'sc_team_library_limit', __( 'You have reached the team-library limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $title = self::clean( $input['title'] ?? '', 180 ); if ( '' === $title ) { return new WP_Error( 'sc_team_library_title', __( 'Add a team-library title.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'post_author' => $owner_id, 'post_title' => $title ), true ); if ( is_wp_error( $id ) ) { return $id; }
        update_post_meta( $id, self::META_LIBRARY_URN, self::urn( 'team-library' ) );
        update_post_meta( $id, self::META_INSTITUTION_ID, self::valid_institution( $input['institution_id'] ?? 0 ) );
        update_post_meta( $id, self::META_UNIT_ID, self::valid_unit( $input['unit_id'] ?? 0 ) );
        update_post_meta( $id, self::META_MEMBERS, array() ); update_post_meta( $id, self::META_COLLECTIONS, array() ); update_post_meta( $id, self::META_REFERENCES, array() ); update_post_meta( $id, self::META_ACTIVITY, array() );
        self::append_activity( $id, $owner_id, 'library_created', 'team_library', (string) $id, $title );
        return self::library_state( $id, $owner_id );
    }

    private static function resolve_user( $value ) {
        if ( is_numeric( $value ) ) { return absint( $value ); }
        $value = trim( sanitize_text_field( (string) $value ) ); $user = is_email( $value ) ? get_user_by( 'email', $value ) : get_user_by( 'login', $value ); return $user instanceof WP_User ? absint( $user->ID ) : 0;
    }

    public static function add_member( $library_id, $actor_id, $user_id, $role ) {
        if ( ! self::can( $library_id, $actor_id, 'manage_members' ) ) { return new WP_Error( 'sc_team_member_permission', __( 'Your team-library role cannot manage members.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $post = get_post( absint( $library_id ) ); $user_id = absint( $user_id ); $role = sanitize_key( (string) $role );
        if ( ! $post instanceof WP_Post || ! $user_id || absint( $post->post_author ) === $user_id || ! get_userdata( $user_id ) ) { return new WP_Error( 'sc_team_member_invalid', __( 'Choose another Sustainable Catalyst account.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        if ( ! isset( self::roles()[ $role ] ) || 'owner' === $role ) { $role = 'reader'; }
        $members = self::members( $library_id ); $active = count( array_filter( $members, static fn( $m ) => 'active' === ( $m['status'] ?? '' ) ) );
        if ( ! isset( $members[ $user_id ] ) && $active >= self::MAX_MEMBERS - 1 ) { return new WP_Error( 'sc_team_member_limit', __( 'This team library has reached its member limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $members[ $user_id ] = array( 'schema' => self::MEMBER_SCHEMA, 'user_id' => $user_id, 'role' => $role, 'status' => 'active', 'added_at' => self::now(), 'added_by' => absint( $actor_id ) ); update_post_meta( absint( $library_id ), self::META_MEMBERS, $members ); self::append_activity( $library_id, $actor_id, 'member_added', 'member', (string) $user_id, $role ); return self::library_state( $library_id, $actor_id );
    }

    public static function remove_member( $library_id, $actor_id, $user_id ) {
        if ( ! self::can( $library_id, $actor_id, 'manage_members' ) ) { return new WP_Error( 'sc_team_member_permission', __( 'Your team-library role cannot manage members.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $members = self::members( $library_id ); $user_id = absint( $user_id ); if ( isset( $members[ $user_id ] ) ) { $members[ $user_id ]['status'] = 'removed'; update_post_meta( absint( $library_id ), self::META_MEMBERS, $members ); self::append_activity( $library_id, $actor_id, 'member_removed', 'member', (string) $user_id ); } return self::library_state( $library_id, $actor_id );
    }

    public static function add_collection( $library_id, $actor_id, array $input ) {
        if ( ! self::can( $library_id, $actor_id, 'manage_collections' ) ) { return new WP_Error( 'sc_team_collection_permission', __( 'Your role cannot manage team collections.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $items = get_post_meta( absint( $library_id ), self::META_COLLECTIONS, true ); $items = is_array( $items ) ? $items : array(); if ( count( $items ) >= self::MAX_COLLECTIONS ) { return new WP_Error( 'sc_team_collection_limit', __( 'This team library has reached its collection limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $title = self::clean( $input['title'] ?? '', 180 ); if ( '' === $title ) { return new WP_Error( 'sc_team_collection_title', __( 'Add a collection title.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $item = array( 'schema' => self::COLLECTION_SCHEMA, 'collection_id' => self::urn( 'team-library-collection' ), 'title' => $title, 'description' => self::clean_area( $input['description'] ?? '', 800 ), 'status' => in_array( sanitize_key( (string) ( $input['status'] ?? 'active' ) ), array( 'active', 'archived' ), true ) ? sanitize_key( (string) ( $input['status'] ?? 'active' ) ) : 'active', 'created_by' => absint( $actor_id ), 'created_at' => self::now() ); $items[] = $item; update_post_meta( absint( $library_id ), self::META_COLLECTIONS, $items ); self::append_activity( $library_id, $actor_id, 'collection_created', 'collection', $item['collection_id'], $title ); return $item;
    }

    public static function contribute_reference( $library_id, $actor_id, array $input ) {
        if ( ! self::can( $library_id, $actor_id, 'contribute' ) ) { return new WP_Error( 'sc_team_reference_permission', __( 'Your role cannot contribute references.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $items = get_post_meta( absint( $library_id ), self::META_REFERENCES, true ); $items = is_array( $items ) ? $items : array(); if ( count( $items ) >= self::MAX_REFERENCES ) { return new WP_Error( 'sc_team_reference_limit', __( 'This team library has reached its reference limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $title = self::clean( $input['title'] ?? '', 220 ); $url = esc_url_raw( (string) ( $input['url'] ?? '' ) ); $canonical_id = self::clean( $input['canonical_id'] ?? '', 360 ); if ( '' === $title && '' === $url && '' === $canonical_id ) { return new WP_Error( 'sc_team_reference_empty', __( 'Add a title, stable ID, or URL.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $collection_id = self::clean( $input['collection_id'] ?? '', 360 );
        $ref = array( 'schema' => self::REFERENCE_SCHEMA, 'reference_id' => self::urn( 'team-library-reference' ), 'collection_id' => $collection_id, 'kind' => sanitize_key( (string) ( $input['kind'] ?? 'external' ) ), 'canonical_id' => $canonical_id, 'title' => $title ?: __( 'Team library reference', 'sustainable-catalyst-library' ), 'url' => $url, 'provenance' => self::clean( $input['provenance'] ?? 'explicit-team-contribution', 140 ), 'contributed_by' => absint( $actor_id ), 'contributed_at' => self::now(), 'references_only' => true );
        $items[] = $ref; update_post_meta( absint( $library_id ), self::META_REFERENCES, $items ); self::append_activity( $library_id, $actor_id, 'reference_contributed', 'reference', $ref['reference_id'], $ref['title'] ); return $ref;
    }

    private static function member_public( $uid, array $member ) {
        $u = get_userdata( absint( $uid ) ); return array( 'schema' => self::MEMBER_SCHEMA, 'user_id' => absint( $uid ), 'display_name' => $u instanceof WP_User ? self::clean( $u->display_name, 120 ) : sprintf( __( 'User %d', 'sustainable-catalyst-library' ), absint( $uid ) ), 'role' => sanitize_key( (string) ( $member['role'] ?? 'reader' ) ), 'status' => self::clean( $member['status'] ?? 'active', 30 ), 'added_at' => self::clean( $member['added_at'] ?? '', 80 ) );
    }

    public static function library_state( $library_id, $viewer_id ) {
        $library_id = absint( $library_id ); $viewer_id = absint( $viewer_id ); $role = self::user_role( $library_id, $viewer_id ); if ( '' === $role ) { return new WP_Error( 'sc_team_library_forbidden', __( 'You are not a member of this team library.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $post = get_post( $library_id ); if ( ! $post instanceof WP_Post ) { return new WP_Error( 'sc_team_library_missing', __( 'Team library not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $members = array(); $owner = get_userdata( absint( $post->post_author ) ); $members[] = array( 'schema' => self::MEMBER_SCHEMA, 'user_id' => absint( $post->post_author ), 'display_name' => $owner instanceof WP_User ? self::clean( $owner->display_name, 120 ) : __( 'Library owner', 'sustainable-catalyst-library' ), 'role' => 'owner', 'status' => 'active', 'added_at' => get_post_time( 'c', true, $post ) ); foreach ( self::members( $library_id ) as $uid => $m ) { if ( 'active' === ( $m['status'] ?? '' ) ) { $members[] = self::member_public( $uid, $m ); } }
        $collections = get_post_meta( $library_id, self::META_COLLECTIONS, true ); $refs = get_post_meta( $library_id, self::META_REFERENCES, true ); $activity = get_post_meta( $library_id, self::META_ACTIVITY, true );
        $institution_id = absint( get_post_meta( $library_id, self::META_INSTITUTION_ID, true ) ); $unit_id = absint( get_post_meta( $library_id, self::META_UNIT_ID, true ) ); $institution = $institution_id ? get_post( $institution_id ) : null; $unit = $unit_id ? get_post( $unit_id ) : null;
        return array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'library_id' => $library_id, 'library_urn' => (string) get_post_meta( $library_id, self::META_LIBRARY_URN, true ), 'title' => self::clean( $post->post_title, 200 ), 'owner_id' => absint( $post->post_author ), 'viewer_role' => $role, 'visibility' => 'private-members-only', 'institution_id' => $institution_id, 'institution_title' => $institution instanceof WP_Post ? self::clean( $institution->post_title, 180 ) : '', 'unit_id' => $unit_id, 'unit_title' => $unit instanceof WP_Post ? self::clean( $unit->post_title, 180 ) : '', 'institutional_binding_is_context_not_entitlement' => true, 'members' => $members, 'collections' => array_values( is_array( $collections ) ? $collections : array() ), 'references' => array_values( is_array( $refs ) ? $refs : array() ), 'activity' => array_reverse( array_slice( array_values( is_array( $activity ) ? $activity : array() ), -80 ) ), 'permissions' => array( 'manage_members' => self::can( $library_id, $viewer_id, 'manage_members' ), 'manage_collections' => self::can( $library_id, $viewer_id, 'manage_collections' ), 'contribute' => self::can( $library_id, $viewer_id, 'contribute' ), 'govern' => self::can( $library_id, $viewer_id, 'govern' ) ), 'contract' => self::contract() );
    }

    public static function state_for_user( $user_id, $selected = 0 ) {
        $user_id = absint( $user_id ); if ( ! $user_id ) { return new WP_Error( 'sc_team_library_signin', __( 'Sign in to open team libraries.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        $ids = self::library_ids_for_user( $user_id ); $libraries = array(); foreach ( $ids as $id ) { $s = self::library_state( $id, $user_id ); if ( is_wp_error( $s ) ) { continue; } $libraries[] = array( 'library_id' => absint( $id ), 'title' => (string) $s['title'], 'viewer_role' => (string) $s['viewer_role'], 'member_count' => count( (array) $s['members'] ), 'collection_count' => count( (array) $s['collections'] ), 'reference_count' => count( (array) $s['references'] ), 'institution_title' => (string) $s['institution_title'], 'unit_title' => (string) $s['unit_title'] ); }
        $selected = absint( $selected ); if ( $selected && ! in_array( $selected, array_map( 'absint', $ids ), true ) ) { $selected = 0; } if ( ! $selected && $ids ) { $selected = absint( $ids[0] ); }
        return array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'visibility' => 'private-members-only', 'user_id' => $user_id, 'generated_at' => self::now(), 'libraries' => $libraries, 'selected_library_id' => $selected, 'selected_library' => $selected ? self::library_state( $selected, $user_id ) : null, 'contract' => self::contract() );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_state' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_create' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_library' ) ) );
        foreach ( array( 'members' => 'rest_members', 'collections' => 'rest_collections', 'references' => 'rest_references' ) as $suffix => $callback ) { register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/' . $suffix, array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, $callback ) ) ); }
    }
    public function rest_signed_in() { return is_user_logged_in(); }
    public function rest_state( WP_REST_Request $r ) { $x = self::state_for_user( get_current_user_id(), absint( $r->get_param( 'library_id' ) ) ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_create( WP_REST_Request $r ) { $x = self::create_library( get_current_user_id(), (array) $r->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_library( WP_REST_Request $r ) { $x = self::library_state( absint( $r['id'] ), get_current_user_id() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_members( WP_REST_Request $r ) { $p = (array) $r->get_json_params(); $uid = self::resolve_user( $p['user'] ?? $p['user_id'] ?? '' ); $x = ! empty( $p['remove'] ) ? self::remove_member( absint( $r['id'] ), get_current_user_id(), $uid ) : self::add_member( absint( $r['id'] ), get_current_user_id(), $uid, $p['role'] ?? 'reader' ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_collections( WP_REST_Request $r ) { $x = self::add_collection( absint( $r['id'] ), get_current_user_id(), (array) $r->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_references( WP_REST_Request $r ) { $x = self::contribute_reference( absint( $r['id'] ), get_current_user_id(), (array) $r->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }

    public function filter_personal_environment( $state, $user_id, $project_id ) {
        if ( ! is_array( $state ) ) { return $state; } $ids = self::library_ids_for_user( absint( $user_id ) ); $state['counts']['team_libraries'] = count( $ids ); $state['team_libraries'] = array(); foreach ( array_slice( $ids, 0, 8 ) as $id ) { $x = self::library_state( $id, $user_id ); if ( ! is_wp_error( $x ) ) { $state['team_libraries'][] = array( 'library_id' => $id, 'title' => (string) $x['title'], 'viewer_role' => (string) $x['viewer_role'], 'reference_count' => count( (array) $x['references'] ), 'target' => '#institutional-team-libraries' ); } } return $state;
    }

    private static function canonical() { return class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ); }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Institutional & Team Libraries', 'sustainable-catalyst-library' ) ), $atts, 'sc_institutional_team_libraries' ); wp_enqueue_style( 'sc-library-team-libraries-v470' ); wp_enqueue_script( 'sc-library-team-libraries-v470' );
        $signed = is_user_logged_in(); $selected = $signed ? absint( $_GET['sc_team_library'] ?? 0 ) : 0; $state = $signed ? self::state_for_user( get_current_user_id(), $selected ) : null;
        if ( $signed ) { wp_localize_script( 'sc-library-team-libraries-v470', 'SCLibraryTeamLibraries', array( 'root' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'canonical' => self::canonical(), 'selectedLibrary' => absint( $state['selected_library_id'] ?? 0 ) ) ); }
        ob_start(); ?>
<section class="sc-team-libraries" data-sc-team-libraries="v4.7.0">
<header class="sc-team-libraries__hero"><div><p><?php esc_html_e( 'Institutional curation · durable team stewardship', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Create durable shared libraries for teams, programs, labs, and institutions without exposing a member’s personal research environment.', 'sustainable-catalyst-library' ); ?></span></div><aside><strong><?php esc_html_e( 'Explicit contribution only', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Membership never exposes My Library, private projects, Research Rooms, notebooks, matrices, or source binaries. Team records contain only what members deliberately contribute as references.', 'sustainable-catalyst-library' ); ?></span></aside></header>
<?php if ( ! $signed || is_wp_error( $state ) ) : ?><div class="sc-team-libraries__signin"><strong><?php esc_html_e( 'Sign in to open your team libraries.', 'sustainable-catalyst-library' ); ?></strong><a href="<?php echo esc_url( wp_login_url( self::canonical() . '#institutional-team-libraries' ) ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
<?php else : $libraries = (array) ( $state['libraries'] ?? array() ); $library = is_array( $state['selected_library'] ?? null ) ? $state['selected_library'] : null; ?>
<div class="sc-team-libraries__toolbar"><form data-sc-team-library-create><label><span><?php esc_html_e( 'New team library', 'sustainable-catalyst-library' ); ?></span><input name="title" required maxlength="180" placeholder="<?php esc_attr_e( 'Team library title', 'sustainable-catalyst-library' ); ?>"></label><label><span><?php esc_html_e( 'Institution ID (optional)', 'sustainable-catalyst-library' ); ?></span><input name="institution_id" inputmode="numeric" placeholder="0"></label><label><span><?php esc_html_e( 'Research unit ID (optional)', 'sustainable-catalyst-library' ); ?></span><input name="unit_id" inputmode="numeric" placeholder="0"></label><button type="submit"><?php esc_html_e( 'Create library', 'sustainable-catalyst-library' ); ?></button></form><?php if ( $libraries ) : ?><form method="get" action="<?php echo esc_url( self::canonical() ); ?>"><label><span><?php esc_html_e( 'Open team library', 'sustainable-catalyst-library' ); ?></span><select name="sc_team_library"><?php foreach ( $libraries as $x ) : ?><option value="<?php echo esc_attr( (string) absint( $x['library_id'] ?? 0 ) ); ?>" <?php selected( absint( $state['selected_library_id'] ?? 0 ), absint( $x['library_id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $x['title'] ?? '' ) . ' · ' . (string) ( $x['viewer_role'] ?? '' ) ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Open', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></div>
<div class="sc-team-libraries__message" data-sc-team-library-message aria-live="polite"></div>
<?php if ( $library ) : ?><div class="sc-team-libraries__library" data-sc-team-library-id="<?php echo esc_attr( (string) absint( $library['library_id'] ) ); ?>"><div class="sc-team-libraries__head"><div><p><?php echo esc_html( strtoupper( (string) $library['viewer_role'] ) ); ?></p><h4><?php echo esc_html( (string) $library['title'] ); ?></h4><?php if ( $library['institution_title'] || $library['unit_title'] ) : ?><span><?php echo esc_html( trim( (string) $library['institution_title'] . ( $library['unit_title'] ? ' · ' . $library['unit_title'] : '' ) ) ); ?></span><?php endif; ?><code><?php echo esc_html( (string) $library['library_urn'] ); ?></code></div><dl><div><dt><?php esc_html_e( 'Members', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $library['members'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Collections', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $library['collections'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'References', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $library['references'] ) ); ?></dd></div></dl></div>
<div class="sc-team-libraries__grid"><section><h5><?php esc_html_e( 'Members', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( (array) $library['members'] as $m ) : ?><li><strong><?php echo esc_html( (string) ( $m['display_name'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $m['role'] ?? '' ) ); ?></span></li><?php endforeach; ?></ul><?php if ( ! empty( $library['permissions']['manage_members'] ) ) : ?><form data-sc-team-library-member><input name="user" required placeholder="<?php esc_attr_e( 'Username or account email', 'sustainable-catalyst-library' ); ?>"><select name="role"><option value="steward">Steward</option><option value="editor">Editor</option><option value="contributor">Contributor</option><option value="reader">Reader</option></select><button><?php esc_html_e( 'Add member', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
<section><h5><?php esc_html_e( 'Collections', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( (array) $library['collections'] as $c ) : ?><li><strong><?php echo esc_html( (string) ( $c['title'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $c['status'] ?? 'active' ) ); ?></span></li><?php endforeach; ?></ul><?php if ( ! empty( $library['permissions']['manage_collections'] ) ) : ?><form data-sc-team-library-collection><input name="title" required maxlength="180" placeholder="<?php esc_attr_e( 'Collection title', 'sustainable-catalyst-library' ); ?>"><textarea name="description" maxlength="800" placeholder="<?php esc_attr_e( 'Purpose or scope (optional)', 'sustainable-catalyst-library' ); ?>"></textarea><button><?php esc_html_e( 'Create collection', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
<section class="sc-team-libraries__references"><h5><?php esc_html_e( 'Shared references', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( array_slice( (array) $library['references'], -40 ) as $r ) : ?><li><strong><?php echo esc_html( (string) ( $r['title'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $r['kind'] ?? 'reference' ) ); ?></span><?php if ( ! empty( $r['url'] ) ) : ?><a href="<?php echo esc_url( (string) $r['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open ↗', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul><?php if ( ! empty( $library['permissions']['contribute'] ) ) : ?><form data-sc-team-library-reference><input name="title" placeholder="<?php esc_attr_e( 'Reference title', 'sustainable-catalyst-library' ); ?>"><input name="canonical_id" placeholder="<?php esc_attr_e( 'Stable ID / URN (optional)', 'sustainable-catalyst-library' ); ?>"><input name="url" type="url" placeholder="https://"><select name="kind"><option value="source">Source</option><option value="publication">Publication</option><option value="document">Document</option><option value="course">Course</option><option value="external">External</option></select><button><?php esc_html_e( 'Contribute reference', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section></div>
<details class="sc-team-libraries__activity"><summary><?php esc_html_e( 'Team activity lineage', 'sustainable-catalyst-library' ); ?></summary><ol><?php foreach ( array_slice( (array) $library['activity'], 0, 35 ) as $a ) : ?><li><strong><?php echo esc_html( str_replace( '_', ' ', (string) ( $a['action'] ?? '' ) ) ); ?></strong><span><?php echo esc_html( (string) ( $a['summary'] ?? '' ) ); ?></span><small><?php echo esc_html( (string) ( $a['created_at'] ?? '' ) ); ?></small></li><?php endforeach; ?></ol></details></div><?php else : ?><p class="sc-team-libraries__empty"><?php esc_html_e( 'Create a team library to establish a durable shared curation space.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
<p class="sc-team-libraries__boundary"><?php esc_html_e( 'Institution and research-unit links reuse the canonical institutional registry as context only; they do not prove legal ownership, membership, subscription entitlement, or access rights. Team Library membership never exposes personal Library records or private research automatically.', 'sustainable-catalyst-library' ); ?></p>
<?php endif; ?></section><?php return (string) ob_get_clean();
    }
}
