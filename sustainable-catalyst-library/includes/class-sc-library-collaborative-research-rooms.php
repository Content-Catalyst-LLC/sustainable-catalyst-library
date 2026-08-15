<?php
/**
 * Collaborative Research Rooms — v4.6.0.
 *
 * Private, project-anchored collaboration spaces for Sustainable Catalyst users.
 * A room never transfers project ownership and never grants blanket access to the
 * owner's project. Only records explicitly shared into the room are visible to
 * room members. Shared records are references-only metadata; private source
 * binaries, personal-library contents, notebook bodies, and matrix bodies are
 * not copied automatically.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Collaborative_Research_Rooms {
    public const VERSION = '4.6.0';
    public const SCHEMA = 'sc-library-collaborative-research-room/1.0';
    public const MEMBER_SCHEMA = 'sc-library-research-room-member/1.0';
    public const REFERENCE_SCHEMA = 'sc-library-research-room-reference/1.0';
    public const NOTE_SCHEMA = 'sc-library-research-room-note/1.0';
    public const DECISION_SCHEMA = 'sc-library-research-room-decision/1.0';
    public const ACTIVITY_SCHEMA = 'sc-library-research-room-activity/1.0';
    public const POST_TYPE = 'sc_research_room';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-rooms';
    public const META_PROJECT_ID = '_sc_research_room_project_id_v460';
    public const META_ROOM_URN = '_sc_research_room_urn_v460';
    public const META_MEMBERS = '_sc_research_room_members_v460';
    public const META_REFERENCES = '_sc_research_room_references_v460';
    public const META_NOTES = '_sc_research_room_notes_v460';
    public const META_DECISIONS = '_sc_research_room_decisions_v460';
    public const META_ACTIVITY = '_sc_research_room_activity_v460';
    public const MAX_ROOMS_PER_OWNER = 40;
    public const MAX_MEMBERS = 30;
    public const MAX_REFERENCES = 160;
    public const MAX_NOTES = 240;
    public const MAX_DECISIONS = 120;
    public const MAX_ACTIVITY = 320;

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_collaborative_research_rooms', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_personal_research_environment_state_payload', array( $this, 'filter_personal_environment' ), 10, 3 );
    }

    public static function contract() {
        return array(
            'schema'                              => self::SCHEMA,
            'visibility'                          => 'private-members-only',
            'identity_source'                     => 'wordpress',
            'same_library_workspace_account'      => true,
            'room_owner_is_post_author'            => true,
            'project_ownership_transferred'       => false,
            'room_membership_grants_project_access'=> false,
            'explicit_share_required'             => true,
            'references_only'                     => true,
            'copy_private_source_binaries'        => false,
            'copy_personal_library_contents'      => false,
            'copy_notebook_bodies_automatically'  => false,
            'copy_matrix_bodies_automatically'    => false,
            'automatic_member_invitation_email'   => false,
            'automatic_publication'               => false,
            'automatic_evidence_promotion'        => false,
            'automatic_project_write'             => false,
            'automatic_workspace_write'           => false,
            'activity_is_append_only_lineage'     => true,
            'decisions_are_human_recorded'        => true,
        );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array( 'name' => __( 'Research Rooms', 'sustainable-catalyst-library' ), 'singular_name' => __( 'Research Room', 'sustainable-catalyst-library' ) ),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => array( 'title', 'author' ),
            'map_meta_cap' => true,
        ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-research-rooms-v460', SC_LIBRARY_URL . 'assets/css/sc-library-research-rooms-v460.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-research-rooms-v460', SC_LIBRARY_URL . 'assets/js/sc-library-research-rooms-v460.js', array(), SC_LIBRARY_VERSION, true );
    }

    private static function clean( $value, $limit = 240 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }
    private static function clean_area( $value, $limit = 1800 ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }
    private static function uuid_urn( $kind ) { return 'urn:sc:' . sanitize_key( $kind ) . ':' . wp_generate_uuid4(); }
    private static function now() { return gmdate( 'c' ); }

    public static function roles() {
        return array(
            'owner'    => array( 'label' => __( 'Owner', 'sustainable-catalyst-library' ), 'manage_members' => true,  'share' => true,  'note' => true, 'decide' => true ),
            'editor'   => array( 'label' => __( 'Editor', 'sustainable-catalyst-library' ), 'manage_members' => false, 'share' => true,  'note' => true, 'decide' => true ),
            'reviewer' => array( 'label' => __( 'Reviewer', 'sustainable-catalyst-library' ), 'manage_members' => false, 'share' => false, 'note' => true, 'decide' => false ),
            'observer' => array( 'label' => __( 'Observer', 'sustainable-catalyst-library' ), 'manage_members' => false, 'share' => false, 'note' => false, 'decide' => false ),
        );
    }

    public static function room_ids_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }
        $owned = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'author' => $user_id, 'fields' => 'ids', 'posts_per_page' => self::MAX_ROOMS_PER_OWNER, 'orderby' => 'modified', 'order' => 'DESC' ) );
        $all = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 200, 'orderby' => 'modified', 'order' => 'DESC' ) );
        $ids = array_map( 'absint', $owned );
        foreach ( $all as $room_id ) {
            $room_id = absint( $room_id );
            if ( in_array( $room_id, $ids, true ) ) { continue; }
            $members = self::members( $room_id );
            if ( isset( $members[ $user_id ] ) && 'active' === ( $members[ $user_id ]['status'] ?? '' ) ) { $ids[] = $room_id; }
        }
        return array_slice( array_values( array_unique( $ids ) ), 0, 100 );
    }

    public static function user_role( $room_id, $user_id ) {
        $room = get_post( absint( $room_id ) );
        $user_id = absint( $user_id );
        if ( ! $room instanceof WP_Post || self::POST_TYPE !== $room->post_type || 'private' !== $room->post_status || ! $user_id ) { return ''; }
        if ( absint( $room->post_author ) === $user_id ) { return 'owner'; }
        $members = self::members( $room_id );
        return isset( $members[ $user_id ] ) && 'active' === ( $members[ $user_id ]['status'] ?? '' ) ? sanitize_key( (string) ( $members[ $user_id ]['role'] ?? 'observer' ) ) : '';
    }

    public static function can( $room_id, $user_id, $capability ) {
        $role = self::user_role( $room_id, $user_id );
        if ( '' === $role ) { return false; }
        if ( 'read' === $capability ) { return true; }
        $roles = self::roles();
        return ! empty( $roles[ $role ][ $capability ] );
    }

    public static function members( $room_id ) {
        $raw = get_post_meta( absint( $room_id ), self::META_MEMBERS, true );
        $raw = is_array( $raw ) ? $raw : array();
        $out = array();
        foreach ( $raw as $key => $member ) {
            if ( ! is_array( $member ) ) { continue; }
            $uid = absint( $member['user_id'] ?? $key );
            if ( ! $uid ) { continue; }
            $role = sanitize_key( (string) ( $member['role'] ?? 'observer' ) );
            if ( ! isset( self::roles()[ $role ] ) || 'owner' === $role ) { $role = 'observer'; }
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

    private static function member_public( $uid, array $member ) {
        $user = get_userdata( absint( $uid ) );
        return array(
            'schema' => self::MEMBER_SCHEMA,
            'user_id' => absint( $uid ),
            'display_name' => $user instanceof WP_User ? self::clean( $user->display_name, 120 ) : sprintf( __( 'User %d', 'sustainable-catalyst-library' ), absint( $uid ) ),
            'role' => sanitize_key( (string) ( $member['role'] ?? 'observer' ) ),
            'status' => self::clean( $member['status'] ?? 'active', 30 ),
            'added_at' => self::clean( $member['added_at'] ?? '', 80 ),
        );
    }

    private static function append_activity( $room_id, $actor_id, $action, $object_kind, $object_id = '', $summary = '' ) {
        $items = get_post_meta( absint( $room_id ), self::META_ACTIVITY, true );
        $items = is_array( $items ) ? $items : array();
        $items[] = array(
            'schema' => self::ACTIVITY_SCHEMA,
            'activity_id' => self::uuid_urn( 'research-room-activity' ),
            'actor_id' => absint( $actor_id ),
            'action' => sanitize_key( (string) $action ),
            'object_kind' => sanitize_key( (string) $object_kind ),
            'object_id' => self::clean( $object_id, 360 ),
            'summary' => self::clean( $summary, 300 ),
            'created_at' => self::now(),
        );
        if ( count( $items ) > self::MAX_ACTIVITY ) { $items = array_slice( $items, -self::MAX_ACTIVITY ); }
        update_post_meta( absint( $room_id ), self::META_ACTIVITY, $items );
    }

    private static function project_owned( $project_id, $user_id ) {
        return class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) && SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( absint( $project_id ), absint( $user_id ) );
    }

    public static function create_room( $user_id, array $input ) {
        $user_id = absint( $user_id ); $project_id = absint( $input['project_id'] ?? 0 );
        if ( ! $user_id ) { return new WP_Error( 'sc_room_signin', __( 'Sign in to create a research room.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        if ( ! self::project_owned( $project_id, $user_id ) ) { return new WP_Error( 'sc_room_project', __( 'Choose a Research Project you own.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $owned = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'author' => $user_id, 'fields' => 'ids', 'posts_per_page' => self::MAX_ROOMS_PER_OWNER + 1 ) );
        if ( count( $owned ) >= self::MAX_ROOMS_PER_OWNER ) { return new WP_Error( 'sc_room_limit', __( 'The private research-room limit has been reached.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $title = self::clean( $input['title'] ?? '', 180 );
        if ( '' === $title ) { $title = __( 'Collaborative Research Room', 'sustainable-catalyst-library' ); }
        $room_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'private', 'post_title' => $title, 'post_author' => $user_id ), true );
        if ( is_wp_error( $room_id ) ) { return $room_id; }
        update_post_meta( $room_id, self::META_PROJECT_ID, $project_id );
        update_post_meta( $room_id, self::META_ROOM_URN, self::uuid_urn( 'research-room' ) );
        update_post_meta( $room_id, self::META_MEMBERS, array() );
        update_post_meta( $room_id, self::META_REFERENCES, array() );
        update_post_meta( $room_id, self::META_NOTES, array() );
        update_post_meta( $room_id, self::META_DECISIONS, array() );
        update_post_meta( $room_id, self::META_ACTIVITY, array() );
        self::append_activity( $room_id, $user_id, 'room_created', 'room', (string) $room_id, $title );
        return self::room_state( $room_id, $user_id );
    }

    public static function add_member( $room_id, $actor_id, $user_id, $role ) {
        $room_id = absint( $room_id ); $actor_id = absint( $actor_id ); $user_id = absint( $user_id ); $role = sanitize_key( (string) $role );
        if ( ! self::can( $room_id, $actor_id, 'manage_members' ) ) { return new WP_Error( 'sc_room_member_permission', __( 'Only the room owner can manage members.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $room = get_post( $room_id );
        if ( ! $room instanceof WP_Post || ! $user_id || absint( $room->post_author ) === $user_id ) { return new WP_Error( 'sc_room_member_invalid', __( 'Choose another Sustainable Catalyst account.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        if ( ! get_userdata( $user_id ) ) { return new WP_Error( 'sc_room_member_missing', __( 'That Sustainable Catalyst account was not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        if ( ! isset( self::roles()[ $role ] ) || 'owner' === $role ) { $role = 'observer'; }
        $members = self::members( $room_id );
        $active_count = count( array_filter( $members, static fn( $m ) => 'active' === ( $m['status'] ?? '' ) ) );
        if ( ! isset( $members[ $user_id ] ) && $active_count >= self::MAX_MEMBERS - 1 ) { return new WP_Error( 'sc_room_member_limit', __( 'This research room has reached its member limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $members[ $user_id ] = array( 'schema' => self::MEMBER_SCHEMA, 'user_id' => $user_id, 'role' => $role, 'status' => 'active', 'added_at' => self::now(), 'added_by' => $actor_id );
        update_post_meta( $room_id, self::META_MEMBERS, $members );
        self::append_activity( $room_id, $actor_id, 'member_added', 'member', (string) $user_id, $role );
        return self::room_state( $room_id, $actor_id );
    }

    public static function remove_member( $room_id, $actor_id, $user_id ) {
        if ( ! self::can( $room_id, $actor_id, 'manage_members' ) ) { return new WP_Error( 'sc_room_member_permission', __( 'Only the room owner can manage members.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $members = self::members( $room_id ); $user_id = absint( $user_id );
        if ( isset( $members[ $user_id ] ) ) { $members[ $user_id ]['status'] = 'removed'; update_post_meta( absint( $room_id ), self::META_MEMBERS, $members ); self::append_activity( $room_id, $actor_id, 'member_removed', 'member', (string) $user_id ); }
        return self::room_state( $room_id, $actor_id );
    }

    private static function resolve_user( $value ) {
        if ( is_numeric( $value ) ) { return absint( $value ); }
        $value = trim( sanitize_text_field( (string) $value ) );
        $user = is_email( $value ) ? get_user_by( 'email', $value ) : get_user_by( 'login', $value );
        return $user instanceof WP_User ? absint( $user->ID ) : 0;
    }

    public static function share_reference( $room_id, $actor_id, array $input ) {
        if ( ! self::can( $room_id, $actor_id, 'share' ) ) { return new WP_Error( 'sc_room_share_permission', __( 'Your room role cannot share references.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $items = get_post_meta( absint( $room_id ), self::META_REFERENCES, true ); $items = is_array( $items ) ? $items : array();
        if ( count( $items ) >= self::MAX_REFERENCES ) { return new WP_Error( 'sc_room_reference_limit', __( 'This room has reached its shared-reference limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $title = self::clean( $input['title'] ?? '', 220 ); $url = esc_url_raw( (string) ( $input['url'] ?? '' ) ); $canonical_id = self::clean( $input['canonical_id'] ?? '', 360 );
        if ( '' === $title && '' === $url && '' === $canonical_id ) { return new WP_Error( 'sc_room_reference_empty', __( 'Add a title, canonical ID, or URL.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $ref = array( 'schema' => self::REFERENCE_SCHEMA, 'reference_id' => self::uuid_urn( 'research-room-reference' ), 'kind' => sanitize_key( (string) ( $input['kind'] ?? 'external' ) ), 'canonical_id' => $canonical_id, 'title' => $title ?: __( 'Shared research reference', 'sustainable-catalyst-library' ), 'url' => $url, 'provenance' => self::clean( $input['provenance'] ?? 'explicit-room-share', 120 ), 'shared_by' => absint( $actor_id ), 'shared_at' => self::now(), 'references_only' => true );
        $items[] = $ref; update_post_meta( absint( $room_id ), self::META_REFERENCES, $items );
        self::append_activity( $room_id, $actor_id, 'reference_shared', 'reference', $ref['reference_id'], $ref['title'] );
        return $ref;
    }

    public static function add_note( $room_id, $actor_id, array $input ) {
        if ( ! self::can( $room_id, $actor_id, 'note' ) ) { return new WP_Error( 'sc_room_note_permission', __( 'Your room role cannot add review notes.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $items = get_post_meta( absint( $room_id ), self::META_NOTES, true ); $items = is_array( $items ) ? $items : array();
        if ( count( $items ) >= self::MAX_NOTES ) { return new WP_Error( 'sc_room_note_limit', __( 'This room has reached its review-note limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $body = self::clean_area( $input['body'] ?? '', 2400 ); if ( '' === $body ) { return new WP_Error( 'sc_room_note_empty', __( 'Add a review note.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $note = array( 'schema' => self::NOTE_SCHEMA, 'note_id' => self::uuid_urn( 'research-room-note' ), 'body' => $body, 'reference_id' => self::clean( $input['reference_id'] ?? '', 360 ), 'status' => in_array( sanitize_key( (string) ( $input['status'] ?? 'open' ) ), array( 'open', 'resolved' ), true ) ? sanitize_key( (string) ( $input['status'] ?? 'open' ) ) : 'open', 'author_id' => absint( $actor_id ), 'created_at' => self::now() );
        $items[] = $note; update_post_meta( absint( $room_id ), self::META_NOTES, $items ); self::append_activity( $room_id, $actor_id, 'note_added', 'note', $note['note_id'], $body ); return $note;
    }

    public static function add_decision( $room_id, $actor_id, array $input ) {
        if ( ! self::can( $room_id, $actor_id, 'decide' ) ) { return new WP_Error( 'sc_room_decision_permission', __( 'Your room role cannot record decisions.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $items = get_post_meta( absint( $room_id ), self::META_DECISIONS, true ); $items = is_array( $items ) ? $items : array();
        if ( count( $items ) >= self::MAX_DECISIONS ) { return new WP_Error( 'sc_room_decision_limit', __( 'This room has reached its decision-record limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $statement = self::clean_area( $input['statement'] ?? '', 1800 ); if ( '' === $statement ) { return new WP_Error( 'sc_room_decision_empty', __( 'Add the decision statement.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $decision = array( 'schema' => self::DECISION_SCHEMA, 'decision_id' => self::uuid_urn( 'research-room-decision' ), 'statement' => $statement, 'rationale' => self::clean_area( $input['rationale'] ?? '', 1800 ), 'status' => in_array( sanitize_key( (string) ( $input['status'] ?? 'recorded' ) ), array( 'proposed', 'recorded', 'superseded' ), true ) ? sanitize_key( (string) ( $input['status'] ?? 'recorded' ) ) : 'recorded', 'recorded_by' => absint( $actor_id ), 'created_at' => self::now() );
        $items[] = $decision; update_post_meta( absint( $room_id ), self::META_DECISIONS, $items ); self::append_activity( $room_id, $actor_id, 'decision_recorded', 'decision', $decision['decision_id'], $statement ); return $decision;
    }

    public static function room_state( $room_id, $viewer_id ) {
        $room_id = absint( $room_id ); $viewer_id = absint( $viewer_id ); $role = self::user_role( $room_id, $viewer_id );
        if ( '' === $role ) { return new WP_Error( 'sc_room_forbidden', __( 'You are not a member of this research room.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) ); }
        $post = get_post( $room_id ); if ( ! $post instanceof WP_Post ) { return new WP_Error( 'sc_room_missing', __( 'Research room not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $members = array();
        $owner = get_userdata( absint( $post->post_author ) );
        $members[] = array( 'schema' => self::MEMBER_SCHEMA, 'user_id' => absint( $post->post_author ), 'display_name' => $owner instanceof WP_User ? self::clean( $owner->display_name, 120 ) : __( 'Room owner', 'sustainable-catalyst-library' ), 'role' => 'owner', 'status' => 'active', 'added_at' => get_post_time( 'c', true, $post ) );
        foreach ( self::members( $room_id ) as $uid => $member ) { if ( 'active' === ( $member['status'] ?? '' ) ) { $members[] = self::member_public( $uid, $member ); } }
        $refs = get_post_meta( $room_id, self::META_REFERENCES, true ); $notes = get_post_meta( $room_id, self::META_NOTES, true ); $decisions = get_post_meta( $room_id, self::META_DECISIONS, true ); $activity = get_post_meta( $room_id, self::META_ACTIVITY, true );
        $project_id = absint( get_post_meta( $room_id, self::META_PROJECT_ID, true ) );
        $project_title = '';
        if ( class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) {
            $project = get_post( $project_id ); if ( $project instanceof WP_Post && 'sc_research_project' === $project->post_type ) { $project_title = self::clean( $project->post_title, 200 ); }
        }
        return array(
            'schema' => self::SCHEMA, 'version' => self::VERSION, 'room_id' => $room_id, 'room_urn' => (string) get_post_meta( $room_id, self::META_ROOM_URN, true ), 'title' => self::clean( $post->post_title, 200 ), 'owner_id' => absint( $post->post_author ), 'viewer_role' => $role, 'project_id' => $project_id, 'project_title' => $project_title, 'visibility' => 'private-members-only', 'members' => $members, 'references' => array_values( is_array( $refs ) ? $refs : array() ), 'notes' => array_values( is_array( $notes ) ? $notes : array() ), 'decisions' => array_values( is_array( $decisions ) ? $decisions : array() ), 'activity' => array_reverse( array_slice( array_values( is_array( $activity ) ? $activity : array() ), -80 ) ), 'permissions' => array( 'manage_members' => self::can( $room_id, $viewer_id, 'manage_members' ), 'share' => self::can( $room_id, $viewer_id, 'share' ), 'note' => self::can( $room_id, $viewer_id, 'note' ), 'decide' => self::can( $room_id, $viewer_id, 'decide' ) ), 'contract' => self::contract(), 'updated_at' => get_post_modified_time( 'c', true, $post ) ?: self::now(),
        );
    }

    public static function state_for_user( $user_id, $room_id = 0 ) {
        $user_id = absint( $user_id ); if ( ! $user_id ) { return new WP_Error( 'sc_room_signin', __( 'Sign in to open Collaborative Research Rooms.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        $rooms = array();
        foreach ( self::room_ids_for_user( $user_id ) as $id ) { $state = self::room_state( $id, $user_id ); if ( ! is_wp_error( $state ) ) { $rooms[] = array( 'room_id' => $id, 'room_urn' => $state['room_urn'], 'title' => $state['title'], 'viewer_role' => $state['viewer_role'], 'project_id' => $state['project_id'], 'project_title' => $state['project_title'], 'member_count' => count( $state['members'] ), 'reference_count' => count( $state['references'] ), 'note_count' => count( $state['notes'] ), 'decision_count' => count( $state['decisions'] ), 'updated_at' => $state['updated_at'] ); } }
        if ( ! $room_id && $rooms ) { $room_id = absint( $rooms[0]['room_id'] ); }
        $selected = $room_id ? self::room_state( $room_id, $user_id ) : null;
        if ( $room_id && is_wp_error( $selected ) ) { return $selected; }
        $projects = array(); if ( class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) ) { foreach ( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( $user_id ) as $pid ) { $p = get_post( $pid ); if ( $p instanceof WP_Post ) { $projects[] = array( 'project_id' => absint( $pid ), 'title' => self::clean( $p->post_title, 180 ) ); } } }
        return array( 'schema' => 'sc-library-collaborative-research-rooms-state/1.0', 'version' => self::VERSION, 'visibility' => 'private', 'rooms' => $rooms, 'room_count' => count( $rooms ), 'selected_room_id' => absint( $room_id ), 'selected_room' => $selected, 'owned_projects' => $projects, 'contract' => self::contract() );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_state' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_create' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_room' ) ) );
        foreach ( array( 'members' => 'rest_members', 'references' => 'rest_references', 'notes' => 'rest_notes', 'decisions' => 'rest_decisions' ) as $suffix => $callback ) {
            register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)/' . $suffix, array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, $callback ) ) );
        }
    }
    public function rest_signed_in() { return is_user_logged_in(); }
    public function rest_state( WP_REST_Request $request ) { $state = self::state_for_user( get_current_user_id(), absint( $request->get_param( 'room_id' ) ) ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_create( WP_REST_Request $request ) { $state = self::create_room( get_current_user_id(), (array) $request->get_json_params() ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_room( WP_REST_Request $request ) { $state = self::room_state( absint( $request['id'] ), get_current_user_id() ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_members( WP_REST_Request $request ) { $p = (array) $request->get_json_params(); $uid = self::resolve_user( $p['user'] ?? $p['user_id'] ?? '' ); $state = ! empty( $p['remove'] ) ? self::remove_member( absint( $request['id'] ), get_current_user_id(), $uid ) : self::add_member( absint( $request['id'] ), get_current_user_id(), $uid, $p['role'] ?? 'observer' ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_references( WP_REST_Request $request ) { $x = self::share_reference( absint( $request['id'] ), get_current_user_id(), (array) $request->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_notes( WP_REST_Request $request ) { $x = self::add_note( absint( $request['id'] ), get_current_user_id(), (array) $request->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }
    public function rest_decisions( WP_REST_Request $request ) { $x = self::add_decision( absint( $request['id'] ), get_current_user_id(), (array) $request->get_json_params() ); return is_wp_error( $x ) ? $x : rest_ensure_response( $x ); }

    public function filter_personal_environment( $state, $user_id, $project_id ) {
        if ( ! is_array( $state ) ) { return $state; }
        $room_ids = self::room_ids_for_user( absint( $user_id ) ); $project_room_count = 0;
        foreach ( $room_ids as $room_id ) { if ( absint( get_post_meta( $room_id, self::META_PROJECT_ID, true ) ) === absint( $project_id ) ) { $project_room_count++; } }
        $state['counts']['research_rooms'] = count( $room_ids );
        if ( ! empty( $state['selected_project'] ) && is_array( $state['selected_project'] ) ) { $state['selected_project']['research_room_count'] = $project_room_count; }
        return $state;
    }

    private static function canonical() { return class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ); }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Collaborative Research Rooms', 'sustainable-catalyst-library' ) ), $atts, 'sc_collaborative_research_rooms' );
        wp_enqueue_style( 'sc-library-research-rooms-v460' ); wp_enqueue_script( 'sc-library-research-rooms-v460' );
        $signed = is_user_logged_in(); $room_id = $signed ? absint( $_GET['sc_room'] ?? 0 ) : 0; $state = $signed ? self::state_for_user( get_current_user_id(), $room_id ) : null;
        if ( $signed ) { wp_localize_script( 'sc-library-research-rooms-v460', 'SCLibraryResearchRooms', array( 'root' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'canonical' => self::canonical(), 'selectedRoom' => absint( $state['selected_room_id'] ?? 0 ) ) ); }
        ob_start(); ?>
<section class="sc-research-rooms" data-sc-research-rooms="v4.6.0">
<header class="sc-research-rooms__hero"><div><p><?php esc_html_e( 'Private collaboration · explicit sharing', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Create project-anchored rooms for shared references, review notes, decisions, and activity lineage. Membership never grants blanket access to the underlying project.', 'sustainable-catalyst-library' ); ?></span></div><aside><strong><?php esc_html_e( 'References first', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Members see only what is deliberately shared into the room. Personal notebooks, My Library records, evidence matrices, and source binaries stay private unless separately represented as a safe reference.', 'sustainable-catalyst-library' ); ?></span></aside></header>
<?php if ( ! $signed || is_wp_error( $state ) ) : ?><div class="sc-research-rooms__signin"><strong><?php esc_html_e( 'Sign in to open your research rooms.', 'sustainable-catalyst-library' ); ?></strong><a href="<?php echo esc_url( wp_login_url( self::canonical() . '#collaborative-research-rooms' ) ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
<?php else : $rooms = (array) ( $state['rooms'] ?? array() ); $room = is_array( $state['selected_room'] ?? null ) ? $state['selected_room'] : null; ?>
<div class="sc-research-rooms__toolbar">
<form data-sc-room-create><label><span><?php esc_html_e( 'New room', 'sustainable-catalyst-library' ); ?></span><input name="title" required maxlength="180" placeholder="<?php esc_attr_e( 'Room title', 'sustainable-catalyst-library' ); ?>"></label><label><span><?php esc_html_e( 'Owner project', 'sustainable-catalyst-library' ); ?></span><select name="project_id" required><option value=""><?php esc_html_e( 'Choose project', 'sustainable-catalyst-library' ); ?></option><?php foreach ( (array) ( $state['owned_projects'] ?? array() ) as $p ) : ?><option value="<?php echo esc_attr( (string) absint( $p['project_id'] ?? 0 ) ); ?>"><?php echo esc_html( (string) ( $p['title'] ?? '' ) ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Create room', 'sustainable-catalyst-library' ); ?></button></form>
<?php if ( $rooms ) : ?><form method="get" action="<?php echo esc_url( self::canonical() ); ?>" data-sc-room-select-form><label><span><?php esc_html_e( 'Open room', 'sustainable-catalyst-library' ); ?></span><select name="sc_room" data-sc-room-select><?php foreach ( $rooms as $r ) : ?><option value="<?php echo esc_attr( (string) absint( $r['room_id'] ?? 0 ) ); ?>" <?php selected( absint( $state['selected_room_id'] ?? 0 ), absint( $r['room_id'] ?? 0 ) ); ?>><?php echo esc_html( (string) ( $r['title'] ?? '' ) . ' · ' . (string) ( $r['viewer_role'] ?? '' ) ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Open', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?>
</div>
<div class="sc-research-rooms__message" data-sc-room-message aria-live="polite"></div>
<?php if ( $room ) : ?><div class="sc-research-rooms__room" data-sc-room-id="<?php echo esc_attr( (string) absint( $room['room_id'] ) ); ?>">
<div class="sc-research-rooms__room-head"><div><p><?php echo esc_html( strtoupper( (string) $room['viewer_role'] ) ); ?></p><h4><?php echo esc_html( (string) $room['title'] ); ?></h4><span><?php echo esc_html( (string) $room['project_title'] ); ?></span><?php if ( ! empty( $room['room_urn'] ) ) : ?><code><?php echo esc_html( (string) $room['room_urn'] ); ?></code><?php endif; ?></div><dl><div><dt><?php esc_html_e( 'Members', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $room['members'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Shared references', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $room['references'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Review notes', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $room['notes'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Decisions', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( (array) $room['decisions'] ) ); ?></dd></div></dl></div>
<div class="sc-research-rooms__columns">
<section><h5><?php esc_html_e( 'Members', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( (array) $room['members'] as $m ) : ?><li><strong><?php echo esc_html( (string) ( $m['display_name'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $m['role'] ?? '' ) ); ?></span></li><?php endforeach; ?></ul><?php if ( ! empty( $room['permissions']['manage_members'] ) ) : ?><form data-sc-room-member><input name="user" placeholder="<?php esc_attr_e( 'Username or account email', 'sustainable-catalyst-library' ); ?>" required><select name="role"><option value="editor"><?php esc_html_e( 'Editor', 'sustainable-catalyst-library' ); ?></option><option value="reviewer"><?php esc_html_e( 'Reviewer', 'sustainable-catalyst-library' ); ?></option><option value="observer"><?php esc_html_e( 'Observer', 'sustainable-catalyst-library' ); ?></option></select><button><?php esc_html_e( 'Add member', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
<section><h5><?php esc_html_e( 'Shared references', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( (array) $room['references'] as $r ) : ?><li><strong><?php echo esc_html( (string) ( $r['title'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $r['kind'] ?? 'reference' ) ); ?></span><?php if ( ! empty( $r['url'] ) ) : ?><a href="<?php echo esc_url( (string) $r['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open ↗', 'sustainable-catalyst-library' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul><?php if ( ! empty( $room['permissions']['share'] ) ) : ?><form data-sc-room-reference><input name="title" placeholder="<?php esc_attr_e( 'Reference title', 'sustainable-catalyst-library' ); ?>"><input name="canonical_id" placeholder="<?php esc_attr_e( 'Stable ID / URN (optional)', 'sustainable-catalyst-library' ); ?>"><input name="url" type="url" placeholder="https://"><select name="kind"><option value="project_reference"><?php esc_html_e( 'Project reference', 'sustainable-catalyst-library' ); ?></option><option value="source"><?php esc_html_e( 'Source', 'sustainable-catalyst-library' ); ?></option><option value="publication"><?php esc_html_e( 'Publication', 'sustainable-catalyst-library' ); ?></option><option value="external"><?php esc_html_e( 'External', 'sustainable-catalyst-library' ); ?></option></select><button><?php esc_html_e( 'Share reference', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
<section><h5><?php esc_html_e( 'Review notes', 'sustainable-catalyst-library' ); ?></h5><ol><?php foreach ( array_slice( (array) $room['notes'], -12 ) as $n ) : ?><li><p><?php echo esc_html( (string) ( $n['body'] ?? '' ) ); ?></p><small><?php echo esc_html( (string) ( $n['created_at'] ?? '' ) ); ?></small></li><?php endforeach; ?></ol><?php if ( ! empty( $room['permissions']['note'] ) ) : ?><form data-sc-room-note><textarea name="body" required maxlength="2400" placeholder="<?php esc_attr_e( 'Add a review note…', 'sustainable-catalyst-library' ); ?>"></textarea><button><?php esc_html_e( 'Add note', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
<section><h5><?php esc_html_e( 'Decision record', 'sustainable-catalyst-library' ); ?></h5><ol><?php foreach ( array_slice( (array) $room['decisions'], -10 ) as $d ) : ?><li><strong><?php echo esc_html( (string) ( $d['statement'] ?? '' ) ); ?></strong><?php if ( ! empty( $d['rationale'] ) ) : ?><p><?php echo esc_html( (string) $d['rationale'] ); ?></p><?php endif; ?><small><?php echo esc_html( (string) ( $d['status'] ?? '' ) . ' · ' . (string) ( $d['created_at'] ?? '' ) ); ?></small></li><?php endforeach; ?></ol><?php if ( ! empty( $room['permissions']['decide'] ) ) : ?><form data-sc-room-decision><textarea name="statement" required maxlength="1800" placeholder="<?php esc_attr_e( 'Decision statement…', 'sustainable-catalyst-library' ); ?>"></textarea><textarea name="rationale" maxlength="1800" placeholder="<?php esc_attr_e( 'Rationale (optional)…', 'sustainable-catalyst-library' ); ?>"></textarea><button><?php esc_html_e( 'Record decision', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?></section>
</div>
<details class="sc-research-rooms__activity"><summary><?php esc_html_e( 'Activity lineage', 'sustainable-catalyst-library' ); ?></summary><ol><?php foreach ( array_slice( (array) $room['activity'], 0, 30 ) as $a ) : ?><li><strong><?php echo esc_html( str_replace( '_', ' ', (string) ( $a['action'] ?? '' ) ) ); ?></strong><span><?php echo esc_html( (string) ( $a['summary'] ?? '' ) ); ?></span><small><?php echo esc_html( (string) ( $a['created_at'] ?? '' ) ); ?></small></li><?php endforeach; ?></ol></details>
</div><?php else : ?><p class="sc-research-rooms__empty"><?php esc_html_e( 'Create a room from one of your Research Projects to begin a controlled collaboration.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?>
<p class="sc-research-rooms__boundary"><?php esc_html_e( 'Research Rooms are private collaboration records. Joining a room does not make someone a project owner, expose the owner’s complete project, copy private binaries or personal-library records, publish research, promote evidence, or write into Workspace.', 'sustainable-catalyst-library' ); ?></p>
<?php endif; ?></section><?php return (string) ob_get_clean();
    }
}
