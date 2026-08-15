<?php
/**
 * Global Research Federation — v4.8.0.
 *
 * A governed metadata-exchange facade that reuses the v3.9 federation peer,
 * trust, export and quarantine machinery plus v4.7 Team Libraries. It does
 * not create a second peer registry, import queue, institution registry, or
 * research-source store.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Global_Research_Federation {
    public const VERSION = '4.8.0';
    public const SCHEMA = 'sc-library-global-research-federation/1.0';
    public const NODE_SCHEMA = 'sc-library-research-federation-node/2.0';
    public const MANIFEST_SCHEMA = 'sc-library-research-federation-manifest/1.0';
    public const REFERENCE_SCHEMA = 'sc-library-research-federation-reference/1.0';
    public const COMPATIBILITY_SCHEMA = 'sc-library-research-federation-compatibility/1.0';
    public const ACCEPTANCE_SCHEMA = 'sc-library-research-federation-acceptance/1.0';
    public const POST_TYPE = 'sc_federation_share';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-federation';

    public const META_SHARE_URN = '_sc_federation_share_urn_v480';
    public const META_TEAM_LIBRARY_ID = '_sc_federation_share_team_library_id_v480';
    public const META_COLLECTION_ID = '_sc_federation_share_collection_id_v480';
    public const META_REFERENCE_IDS = '_sc_federation_share_reference_ids_v480';
    public const META_STATUS = '_sc_federation_share_status_v480';
    public const META_MANIFEST = '_sc_federation_share_manifest_v480';
    public const META_SHA256 = '_sc_federation_share_sha256_v480';
    public const META_PUBLISHED_AT = '_sc_federation_share_published_at_v480';
    public const META_REVOKED_AT = '_sc_federation_share_revoked_at_v480';
    public const META_SUPERSEDES = '_sc_federation_share_supersedes_v480';

    public const MAX_MANIFESTS_PER_TEAM_LIBRARY = 120;
    public const MAX_REFERENCES_PER_MANIFEST = 200;
    public const MAX_PUBLIC_MANIFESTS = 250;
    public const MAX_INBOUND_RECORDS = 200;

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_global_research_federation', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_personal_research_environment_state_payload', array( $this, 'filter_personal_environment' ), 30, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'legacy_v390_federation_reused' => true,
            'team_library_authority_reused' => true,
            'creates_parallel_peer_registry' => false,
            'creates_parallel_import_quarantine' => false,
            'creates_parallel_institution_registry' => false,
            'creates_parallel_research_source_store' => false,
            'public_exchange_metadata_only' => true,
            'explicit_team_governor_publish_required' => true,
            'explicit_remote_import_review_required' => true,
            'explicit_team_acceptance_required' => true,
            'approved_metadata_does_not_auto_import' => true,
            'peer_trust_is_transport_governance_not_truth' => true,
            'institutional_context_is_not_entitlement' => true,
            'remote_identity_is_not_local_membership' => true,
            'references_only' => true,
            'personal_library_exported' => false,
            'private_project_data_exported' => false,
            'research_room_membership_exported' => false,
            'notebook_bodies_exported' => false,
            'matrix_bodies_exported' => false,
            'source_binaries_exported' => false,
            'credentials_exported' => false,
            'automatic_remote_polling' => false,
            'automatic_metadata_acceptance' => false,
            'automatic_publication' => false,
            'automatic_evidence_promotion' => false,
            'automatic_workspace_write' => false,
            'truth_scoring' => false,
        );
    }

    public static function compatibility_profile() {
        return array(
            'schema' => self::COMPATIBILITY_SCHEMA,
            'version' => self::VERSION,
            'manifest_schema' => self::MANIFEST_SCHEMA,
            'reference_schema' => self::REFERENCE_SCHEMA,
            'minimum_compatible_manifest' => '1.0',
            'maximum_references_per_manifest' => self::MAX_REFERENCES_PER_MANIFEST,
            'exchange_mode' => 'references-only-metadata',
            'quarantine_required' => true,
            'automatic_import_allowed' => false,
        );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name' => __( 'Federation Share Manifests', 'sustainable-catalyst-library' ),
                'singular_name' => __( 'Federation Share Manifest', 'sustainable-catalyst-library' ),
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => array( 'title', 'author' ),
            'map_meta_cap' => true,
        ) );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-global-research-federation-v480', SC_LIBRARY_URL . 'assets/css/sc-library-global-research-federation-v480.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-global-research-federation-v480', SC_LIBRARY_URL . 'assets/js/sc-library-global-research-federation-v480.js', array(), SC_LIBRARY_VERSION, true );
    }

    private static function now() { return gmdate( 'c' ); }
    private static function urn( $kind ) { return 'urn:sc:' . sanitize_key( $kind ) . ':' . wp_generate_uuid4(); }
    private static function clean( $value, $limit = 260 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }
    private static function clean_area( $value, $limit = 1200 ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function canonicalize( $value ) {
        if ( is_array( $value ) ) {
            $is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
            if ( ! $is_list ) { ksort( $value, SORT_STRING ); }
            foreach ( $value as $key => $item ) { $value[ $key ] = self::canonicalize( $item ); }
        }
        return $value;
    }

    private static function canonical_json( $value ) {
        return (string) wp_json_encode( self::canonicalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    private static function manifest_hash( array $manifest ) {
        unset( $manifest['sha256'] );
        return hash( 'sha256', self::canonical_json( $manifest ) );
    }

    private static function local_node_id() {
        $legacy = class_exists( 'SC_Library_Public_API_Export_Federation' ) ? SC_Library_Public_API_Export_Federation::node_data( false ) : array();
        if ( ! empty( $legacy['node_id'] ) ) { return self::clean( $legacy['node_id'], 220 ); }
        return 'urn:sc:federation-node:' . substr( hash( 'sha256', strtolower( untrailingslashit( home_url( '/' ) ) ) ), 0, 24 );
    }

    public static function public_node_manifest() {
        $legacy = class_exists( 'SC_Library_Public_API_Export_Federation' ) ? SC_Library_Public_API_Export_Federation::node_data( false ) : array();
        $published = self::published_manifest_ids();
        return array(
            'schema' => self::NODE_SCHEMA,
            'version' => self::VERSION,
            'node_id' => self::local_node_id(),
            'name' => self::clean( $legacy['name'] ?? get_bloginfo( 'name' ), 180 ),
            'base_url' => esc_url_raw( home_url( '/' ) ),
            'federation_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
            'legacy_federation_reused' => true,
            'legacy_node_public' => ! empty( $legacy['public'] ),
            'public_manifest_count' => count( $published ),
            'capabilities' => array( 'node-discovery', 'public-metadata-manifests', 'quarantined-metadata-intake', 'team-library-reference-acceptance' ),
            'compatibility' => self::compatibility_profile(),
            'governance' => array(
                'peer_trust_is_transport_governance_not_truth' => true,
                'institutional_context_is_not_entitlement' => true,
                'remote_identity_is_not_local_membership' => true,
                'automatic_import_allowed' => false,
            ),
            'generated_at' => self::now(),
        );
    }

    private static function share_ids_for_library( $library_id ) {
        return array_map( 'absint', (array) get_posts( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => self::MAX_MANIFESTS_PER_TEAM_LIBRARY,
            'fields' => 'ids',
            'meta_key' => self::META_TEAM_LIBRARY_ID,
            'meta_value' => absint( $library_id ),
            'orderby' => 'ID',
            'order' => 'DESC',
        ) ) );
    }

    public static function published_manifest_ids() {
        return array_map( 'absint', (array) get_posts( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'posts_per_page' => self::MAX_PUBLIC_MANIFESTS,
            'fields' => 'ids',
            'meta_key' => self::META_STATUS,
            'meta_value' => 'published',
            'orderby' => 'modified',
            'order' => 'DESC',
        ) ) );
    }

    private static function reference_index( array $library_state ) {
        $index = array();
        foreach ( (array) ( $library_state['references'] ?? array() ) as $reference ) {
            if ( ! is_array( $reference ) ) { continue; }
            $id = self::clean( $reference['reference_id'] ?? '', 360 );
            if ( '' !== $id ) { $index[ $id ] = $reference; }
        }
        return $index;
    }

    private static function safe_public_reference( array $reference ) {
        $reference_id = self::clean( $reference['reference_id'] ?? '', 360 );
        $kind = sanitize_key( (string) ( $reference['kind'] ?? 'external' ) );
        $canonical_id = self::clean( $reference['canonical_id'] ?? '', 360 );
        $url = esc_url_raw( (string) ( $reference['url'] ?? '' ) );
        return array(
            'schema' => self::REFERENCE_SCHEMA,
            'reference_id' => $reference_id,
            'id' => $canonical_id ?: ( $reference_id ?: $url ),
            'kind' => $kind,
            'type' => $kind,
            'canonical_id' => $canonical_id,
            'title' => self::clean( $reference['title'] ?? '', 240 ),
            'url' => $url,
            'provenance' => self::clean( $reference['provenance'] ?? 'team-library-reference', 180 ),
            'references_only' => true,
        );
    }

    public static function create_manifest( $actor_id, array $input ) {
        $actor_id = absint( $actor_id );
        $library_id = absint( $input['team_library_id'] ?? 0 );
        if ( ! $actor_id || ! $library_id || ! class_exists( 'SC_Library_Institutional_Team_Libraries' ) ) {
            return new WP_Error( 'sc_federation_manifest_context', __( 'Choose a governed Team Library.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        if ( ! SC_Library_Institutional_Team_Libraries::can( $library_id, $actor_id, 'govern' ) ) {
            return new WP_Error( 'sc_federation_manifest_permission', __( 'Only a Team Library owner or steward can prepare federation manifests.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        if ( count( self::share_ids_for_library( $library_id ) ) >= self::MAX_MANIFESTS_PER_TEAM_LIBRARY ) {
            return new WP_Error( 'sc_federation_manifest_limit', __( 'This Team Library has reached its federation-manifest limit.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        $state = SC_Library_Institutional_Team_Libraries::library_state( $library_id, $actor_id );
        if ( is_wp_error( $state ) ) { return $state; }
        $index = self::reference_index( $state );
        $requested = array_values( array_unique( array_filter( array_map( static fn( $value ) => self::clean( $value, 360 ), (array) ( $input['reference_ids'] ?? array() ) ) ) ) );
        $collection_id = self::clean( $input['collection_id'] ?? '', 360 );
        if ( '' !== $collection_id ) {
            foreach ( $index as $id => $reference ) {
                if ( $collection_id === self::clean( $reference['collection_id'] ?? '', 360 ) ) { $requested[] = $id; }
            }
            $requested = array_values( array_unique( $requested ) );
        }
        if ( ! $requested ) {
            return new WP_Error( 'sc_federation_manifest_references', __( 'Select at least one Team Library reference or collection.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $references = array();
        foreach ( array_slice( $requested, 0, self::MAX_REFERENCES_PER_MANIFEST ) as $id ) {
            if ( isset( $index[ $id ] ) ) { $references[] = self::safe_public_reference( $index[ $id ] ); }
        }
        if ( ! $references ) {
            return new WP_Error( 'sc_federation_manifest_missing_references', __( 'None of the selected references are available in this Team Library.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $title = self::clean( $input['title'] ?? '', 180 );
        if ( '' === $title ) { $title = self::clean( ( $state['title'] ?? __( 'Team Library', 'sustainable-catalyst-library' ) ) . ' — Federation manifest', 180 ); }
        $status = 'published' === sanitize_key( (string) ( $input['status'] ?? 'draft' ) ) ? 'published' : 'draft';
        $share_urn = self::urn( 'federation-share' );
        $manifest = array(
            'schema' => self::MANIFEST_SCHEMA,
            'version' => self::VERSION,
            'manifest_urn' => $share_urn,
            'origin_node_id' => self::local_node_id(),
            'team_library_urn' => self::clean( $state['library_urn'] ?? '', 360 ),
            'team_library_title' => self::clean( $state['title'] ?? '', 200 ),
            'institution_context' => array(
                'institution_title' => self::clean( $state['institution_title'] ?? '', 180 ),
                'research_unit_title' => self::clean( $state['unit_title'] ?? '', 180 ),
                'context_not_entitlement' => true,
            ),
            'title' => $title,
            'collection_id' => $collection_id,
            'reference_count' => count( $references ),
            'records' => $references,
            'references_only' => true,
            'private_content_included' => false,
            'credentials_included' => false,
            'generated_at' => self::now(),
            'supersedes' => self::clean( $input['supersedes'] ?? '', 360 ),
        );
        $manifest['sha256'] = self::manifest_hash( $manifest );
        $post_id = wp_insert_post( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_author' => $actor_id,
            'post_title' => $title,
        ), true );
        if ( is_wp_error( $post_id ) ) { return $post_id; }
        update_post_meta( $post_id, self::META_SHARE_URN, $share_urn );
        update_post_meta( $post_id, self::META_TEAM_LIBRARY_ID, $library_id );
        update_post_meta( $post_id, self::META_COLLECTION_ID, $collection_id );
        update_post_meta( $post_id, self::META_REFERENCE_IDS, array_column( $references, 'reference_id' ) );
        update_post_meta( $post_id, self::META_STATUS, $status );
        update_post_meta( $post_id, self::META_MANIFEST, $manifest );
        update_post_meta( $post_id, self::META_SHA256, $manifest['sha256'] );
        update_post_meta( $post_id, self::META_SUPERSEDES, $manifest['supersedes'] );
        if ( 'published' === $status ) { update_post_meta( $post_id, self::META_PUBLISHED_AT, self::now() ); }
        return self::manifest_state( $post_id, $actor_id, true );
    }

    public static function manifest_state( $post_id, $viewer_id = 0, $include_private = false ) {
        $post_id = absint( $post_id );
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) { return new WP_Error( 'sc_federation_manifest_missing', __( 'Federation manifest not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $status = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS, true ) );
        $library_id = absint( get_post_meta( $post_id, self::META_TEAM_LIBRARY_ID, true ) );
        $can_govern = $viewer_id && class_exists( 'SC_Library_Institutional_Team_Libraries' ) && SC_Library_Institutional_Team_Libraries::can( $library_id, absint( $viewer_id ), 'govern' );
        if ( 'published' !== $status && ! $can_govern ) { return new WP_Error( 'sc_federation_manifest_private', __( 'This federation manifest is not public.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $manifest = get_post_meta( $post_id, self::META_MANIFEST, true );
        $manifest = is_array( $manifest ) ? $manifest : array();
        $out = array(
            'schema' => self::MANIFEST_SCHEMA,
            'manifest_id' => $post_id,
            'manifest_urn' => self::clean( get_post_meta( $post_id, self::META_SHARE_URN, true ), 360 ),
            'status' => $status ?: 'draft',
            'title' => self::clean( $post->post_title, 180 ),
            'published_at' => self::clean( get_post_meta( $post_id, self::META_PUBLISHED_AT, true ), 80 ),
            'revoked_at' => self::clean( get_post_meta( $post_id, self::META_REVOKED_AT, true ), 80 ),
            'sha256' => self::clean( get_post_meta( $post_id, self::META_SHA256, true ), 80 ),
            'manifest' => $manifest,
            'public_metadata_only' => true,
        );
        if ( $include_private && $can_govern ) {
            $out['team_library_id'] = $library_id;
            $out['reference_ids'] = array_values( (array) get_post_meta( $post_id, self::META_REFERENCE_IDS, true ) );
            $out['can_govern'] = true;
        }
        return $out;
    }

    public static function set_manifest_status( $post_id, $actor_id, $status ) {
        $post_id = absint( $post_id ); $actor_id = absint( $actor_id );
        $library_id = absint( get_post_meta( $post_id, self::META_TEAM_LIBRARY_ID, true ) );
        if ( ! class_exists( 'SC_Library_Institutional_Team_Libraries' ) || ! SC_Library_Institutional_Team_Libraries::can( $library_id, $actor_id, 'govern' ) ) {
            return new WP_Error( 'sc_federation_manifest_permission', __( 'Only a Team Library owner or steward can change federation publication state.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $status = sanitize_key( (string) $status );
        if ( ! in_array( $status, array( 'draft', 'published', 'revoked' ), true ) ) {
            return new WP_Error( 'sc_federation_manifest_status', __( 'Choose draft, published, or revoked.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        update_post_meta( $post_id, self::META_STATUS, $status );
        if ( 'published' === $status ) { update_post_meta( $post_id, self::META_PUBLISHED_AT, self::now() ); delete_post_meta( $post_id, self::META_REVOKED_AT ); }
        if ( 'revoked' === $status ) { update_post_meta( $post_id, self::META_REVOKED_AT, self::now() ); }
        return self::manifest_state( $post_id, $actor_id, true );
    }

    public static function validate_exchange_manifest( $payload ) {
        $errors = array();
        if ( ! is_array( $payload ) ) { $errors[] = 'payload-not-object'; }
        else {
            if ( self::MANIFEST_SCHEMA !== (string) ( $payload['schema'] ?? '' ) ) { $errors[] = 'schema-incompatible'; }
            $remote_version = (string) ( $payload['version'] ?? '' );
            $compatible_version = self::VERSION === $remote_version || str_starts_with( $remote_version, '4.' );
            if ( ! $compatible_version ) { $errors[] = 'version-incompatible'; }
            if ( empty( $payload['manifest_urn'] ) || empty( $payload['origin_node_id'] ) ) { $errors[] = 'identity-missing'; }
            if ( empty( $payload['references_only'] ) ) { $errors[] = 'references-only-required'; }
            $records = (array) ( $payload['records'] ?? array() );
            if ( ! $records ) { $errors[] = 'records-missing'; }
            if ( count( $records ) > self::MAX_INBOUND_RECORDS ) { $errors[] = 'too-many-records'; }
            foreach ( array_slice( $records, 0, self::MAX_INBOUND_RECORDS ) as $index => $record ) {
                if ( ! is_array( $record ) || empty( $record['title'] ) || ( empty( $record['canonical_id'] ) && empty( $record['url'] ) && empty( $record['reference_id'] ) ) ) { $errors[] = 'record-invalid-' . absint( $index ); break; }
            }
            $expected = self::clean( $payload['sha256'] ?? '', 80 );
            if ( '' === $expected || ! hash_equals( $expected, self::manifest_hash( $payload ) ) ) { $errors[] = 'sha256-invalid'; }
        }
        return array(
            'schema' => self::COMPATIBILITY_SCHEMA,
            'valid' => ! $errors,
            'errors' => $errors,
            'compatible_version' => is_array( $payload ) ? ( self::VERSION === (string) ( $payload['version'] ?? '' ) || str_starts_with( (string) ( $payload['version'] ?? '' ), '4.' ) ) : false,
            'automatic_import_allowed' => false,
            'quarantine_required' => true,
            'validated_at' => self::now(),
        );
    }

    public static function quarantine_manifest( $payload, $peer_id = 0 ) {
        if ( ! class_exists( 'SC_Library_Public_API_Export_Federation' ) ) {
            return new WP_Error( 'sc_federation_legacy_missing', __( 'The canonical federation quarantine engine is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) );
        }
        $validation = self::validate_exchange_manifest( $payload );
        if ( empty( $validation['valid'] ) ) {
            return new WP_Error( 'sc_federation_manifest_invalid', __( 'The federation manifest failed compatibility or integrity validation.', 'sustainable-catalyst-library' ), array( 'status' => 400, 'validation' => $validation ) );
        }
        return SC_Library_Public_API_Export_Federation::quarantine_import( $payload, absint( $peer_id ) );
    }

    private static function existing_reference_keys( array $state ) {
        $keys = array();
        foreach ( (array) ( $state['references'] ?? array() ) as $reference ) {
            if ( ! is_array( $reference ) ) { continue; }
            $canonical = strtolower( self::clean( $reference['canonical_id'] ?? '', 360 ) );
            $url = strtolower( untrailingslashit( esc_url_raw( (string) ( $reference['url'] ?? '' ) ) ) );
            if ( '' !== $canonical ) { $keys['id:' . $canonical] = true; }
            if ( '' !== $url ) { $keys['url:' . $url] = true; }
        }
        return $keys;
    }

    public static function accept_import_to_team_library( $import_id, $library_id, $actor_id, $collection_id = '' ) {
        $import_id = absint( $import_id ); $library_id = absint( $library_id ); $actor_id = absint( $actor_id );
        if ( ! class_exists( 'SC_Library_Public_API_Export_Federation' ) || ! class_exists( 'SC_Library_Institutional_Team_Libraries' ) ) {
            return new WP_Error( 'sc_federation_dependencies_missing', __( 'Federation or Team Library dependencies are unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) );
        }
        if ( ! SC_Library_Institutional_Team_Libraries::can( $library_id, $actor_id, 'govern' ) ) {
            return new WP_Error( 'sc_federation_accept_permission', __( 'Only a Team Library owner or steward can accept federation metadata.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $import = SC_Library_Public_API_Export_Federation::import_data( $import_id, true );
        if ( ! $import || 'approved-metadata' !== (string) ( $import['status'] ?? '' ) ) {
            return new WP_Error( 'sc_federation_import_not_approved', __( 'The federation import must first be explicitly approved as metadata.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) );
        }
        $payload = is_array( $import['payload'] ?? null ) ? $import['payload'] : array();
        $validation = self::validate_exchange_manifest( $payload );
        if ( empty( $validation['valid'] ) ) { return new WP_Error( 'sc_federation_import_invalid', __( 'The approved payload no longer validates against the v4.8 manifest contract.', 'sustainable-catalyst-library' ), array( 'status' => 409 ) ); }
        $state = SC_Library_Institutional_Team_Libraries::library_state( $library_id, $actor_id );
        if ( is_wp_error( $state ) ) { return $state; }
        $existing = self::existing_reference_keys( $state );
        $accepted = 0; $skipped = 0; $errors = array();
        foreach ( array_slice( (array) ( $payload['records'] ?? array() ), 0, self::MAX_INBOUND_RECORDS ) as $record ) {
            if ( ! is_array( $record ) ) { continue; }
            $canonical = strtolower( self::clean( $record['canonical_id'] ?? $record['reference_id'] ?? '', 360 ) );
            $url = strtolower( untrailingslashit( esc_url_raw( (string) ( $record['url'] ?? '' ) ) ) );
            if ( ( $canonical && isset( $existing['id:' . $canonical] ) ) || ( $url && isset( $existing['url:' . $url] ) ) ) { $skipped++; continue; }
            $provenance = 'federation:' . self::clean( $payload['origin_node_id'] ?? 'remote-node', 120 ) . ':' . self::clean( $payload['manifest_urn'] ?? 'manifest', 120 );
            $result = SC_Library_Institutional_Team_Libraries::contribute_reference( $library_id, $actor_id, array(
                'title' => $record['title'] ?? __( 'Federated reference', 'sustainable-catalyst-library' ),
                'url' => $record['url'] ?? '',
                'canonical_id' => $record['canonical_id'] ?? $record['reference_id'] ?? '',
                'kind' => $record['kind'] ?? 'external',
                'collection_id' => $collection_id,
                'provenance' => $provenance,
            ) );
            if ( is_wp_error( $result ) ) { $errors[] = $result->get_error_code(); continue; }
            $accepted++;
            if ( $canonical ) { $existing['id:' . $canonical] = true; }
            if ( $url ) { $existing['url:' . $url] = true; }
        }
        return array(
            'schema' => self::ACCEPTANCE_SCHEMA,
            'import_id' => $import_id,
            'team_library_id' => $library_id,
            'accepted_reference_count' => $accepted,
            'duplicate_reference_count' => $skipped,
            'errors' => array_values( array_unique( $errors ) ),
            'references_only' => true,
            'automatic_content_import' => false,
            'accepted_at' => self::now(),
        );
    }

    public static function manifests_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! class_exists( 'SC_Library_Institutional_Team_Libraries' ) ) { return array(); }
        $out = array();
        foreach ( SC_Library_Institutional_Team_Libraries::library_ids_for_user( $user_id ) as $library_id ) {
            if ( ! SC_Library_Institutional_Team_Libraries::can( $library_id, $user_id, 'govern' ) ) { continue; }
            foreach ( self::share_ids_for_library( $library_id ) as $share_id ) {
                $state = self::manifest_state( $share_id, $user_id, true );
                if ( ! is_wp_error( $state ) ) { $out[] = $state; }
            }
        }
        usort( $out, static fn( $a, $b ) => absint( $b['manifest_id'] ?? 0 ) <=> absint( $a['manifest_id'] ?? 0 ) );
        return array_slice( $out, 0, 200 );
    }

    public static function catalog_for_user( $user_id, $selected_library = 0 ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_federation_signin', __( 'Sign in to open federation governance.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        $libraries = array(); $governable = array();
        if ( class_exists( 'SC_Library_Institutional_Team_Libraries' ) ) {
            foreach ( SC_Library_Institutional_Team_Libraries::library_ids_for_user( $user_id ) as $library_id ) {
                if ( ! SC_Library_Institutional_Team_Libraries::can( $library_id, $user_id, 'govern' ) ) { continue; }
                $state = SC_Library_Institutional_Team_Libraries::library_state( $library_id, $user_id );
                if ( is_wp_error( $state ) ) { continue; }
                $libraries[] = array( 'library_id' => $library_id, 'title' => (string) $state['title'], 'reference_count' => count( (array) $state['references'] ), 'collection_count' => count( (array) $state['collections'] ), 'viewer_role' => (string) $state['viewer_role'] );
                $governable[ $library_id ] = $state;
            }
        }
        $selected_library = absint( $selected_library );
        if ( ! isset( $governable[ $selected_library ] ) ) { $selected_library = $libraries ? absint( $libraries[0]['library_id'] ) : 0; }
        $peers = array();
        if ( class_exists( 'SC_Library_Public_API_Export_Federation' ) ) {
            $peer_ids = get_posts( array( 'post_type' => SC_Library_Public_API_Export_Federation::PEER_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
            foreach ( $peer_ids as $peer_id ) { $peer = SC_Library_Public_API_Export_Federation::peer_data( absint( $peer_id ), false ); if ( $peer ) { $peers[] = $peer; } }
        }
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'user_id' => $user_id,
            'node' => self::public_node_manifest(),
            'team_libraries' => $libraries,
            'selected_team_library_id' => $selected_library,
            'selected_team_library' => $selected_library ? $governable[ $selected_library ] : null,
            'manifests' => self::manifests_for_user( $user_id ),
            'configured_peers' => $peers,
            'contract' => self::contract(),
            'generated_at' => self::now(),
        );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/node', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_node' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/manifests', array(
            array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_public_manifests' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( $this, 'rest_signed_in' ), 'callback' => array( $this, 'rest_create_manifest' ) ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/manifests/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_manifest' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/manifests/(?P<id>\d+)/status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => array( $this, 'rest_signed_in' ),
            'callback' => array( $this, 'rest_manifest_status' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/catalog', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => array( $this, 'rest_signed_in' ),
            'callback' => array( $this, 'rest_catalog' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/imports', array(
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => array( $this, 'rest_admin' ),
            'callback' => array( $this, 'rest_quarantine_import' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/imports/(?P<id>\d+)/decision', array(
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => array( $this, 'rest_admin' ),
            'callback' => array( $this, 'rest_import_decision' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/imports/(?P<id>\d+)/accept', array(
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => array( $this, 'rest_signed_in' ),
            'callback' => array( $this, 'rest_accept_import' ),
        ) );
    }

    public function rest_signed_in() { return is_user_logged_in(); }
    public function rest_admin() { return current_user_can( 'manage_options' ); }
    public function rest_node() { return rest_ensure_response( self::public_node_manifest() ); }
    public function rest_public_manifests() {
        $items = array();
        foreach ( self::published_manifest_ids() as $id ) { $state = self::manifest_state( $id ); if ( ! is_wp_error( $state ) ) { $items[] = $state; } }
        return rest_ensure_response( array( 'schema' => self::MANIFEST_SCHEMA, 'version' => self::VERSION, 'count' => count( $items ), 'manifests' => $items, 'generated_at' => self::now() ) );
    }
    public function rest_manifest( WP_REST_Request $request ) { $state = self::manifest_state( absint( $request['id'] ), get_current_user_id(), false ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_catalog( WP_REST_Request $request ) { $state = self::catalog_for_user( get_current_user_id(), absint( $request->get_param( 'team_library_id' ) ) ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_create_manifest( WP_REST_Request $request ) { $state = self::create_manifest( get_current_user_id(), (array) $request->get_json_params() ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_manifest_status( WP_REST_Request $request ) { $params = (array) $request->get_json_params(); $state = self::set_manifest_status( absint( $request['id'] ), get_current_user_id(), $params['status'] ?? 'draft' ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_quarantine_import( WP_REST_Request $request ) { $payload = (array) $request->get_json_params(); $peer_id = absint( $request->get_param( 'peer_id' ) ); $state = self::quarantine_manifest( $payload, $peer_id ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }
    public function rest_import_decision( WP_REST_Request $request ) {
        if ( ! class_exists( 'SC_Library_Public_API_Export_Federation' ) ) { return new WP_Error( 'sc_federation_legacy_missing', __( 'The canonical federation quarantine engine is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) ); }
        $params = (array) $request->get_json_params(); $state = SC_Library_Public_API_Export_Federation::decide_import( absint( $request['id'] ), $params['decision'] ?? '', self::clean_area( $params['note'] ?? '', 800 ) ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state );
    }
    public function rest_accept_import( WP_REST_Request $request ) { $params = (array) $request->get_json_params(); $state = self::accept_import_to_team_library( absint( $request['id'] ), absint( $params['team_library_id'] ?? 0 ), get_current_user_id(), self::clean( $params['collection_id'] ?? '', 360 ) ); return is_wp_error( $state ) ? $state : rest_ensure_response( $state ); }

    public function filter_personal_environment( $state, $user_id, $project_id ) {
        if ( ! is_array( $state ) ) { return $state; }
        $manifests = self::manifests_for_user( absint( $user_id ) );
        $state['counts']['federation_manifests'] = count( $manifests );
        $state['federation_manifests'] = array_map( static function ( $item ) {
            return array( 'manifest_id' => absint( $item['manifest_id'] ?? 0 ), 'title' => (string) ( $item['title'] ?? '' ), 'status' => (string) ( $item['status'] ?? 'draft' ), 'target' => '#global-research-federation' );
        }, array_slice( $manifests, 0, 8 ) );
        return $state;
    }

    private static function canonical() { return class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ); }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Global Research Federation', 'sustainable-catalyst-library' ) ), $atts, 'sc_global_research_federation' );
        wp_enqueue_style( 'sc-library-global-research-federation-v480' );
        wp_enqueue_script( 'sc-library-global-research-federation-v480' );
        $signed = is_user_logged_in();
        $selected = $signed ? absint( $_GET['sc_federation_library'] ?? 0 ) : 0;
        $catalog = $signed ? self::catalog_for_user( get_current_user_id(), $selected ) : null;
        if ( $signed && ! is_wp_error( $catalog ) ) {
            wp_localize_script( 'sc-library-global-research-federation-v480', 'SCLibraryResearchFederation', array(
                'root' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'canonical' => self::canonical(),
                'selectedTeamLibrary' => absint( $catalog['selected_team_library_id'] ?? 0 ),
            ) );
        }
        $node = self::public_node_manifest();
        $public_ids = self::published_manifest_ids();
        ob_start(); ?>
<section class="sc-global-federation" data-sc-global-federation="v4.8.0">
<header class="sc-global-federation__hero"><div><p><?php esc_html_e( 'Federated research · governed metadata exchange', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Publish explicit references-only Team Library manifests, discover compatible Sustainable Catalyst nodes, quarantine remote metadata, and accept approved references into governed team collections without exposing private research.', 'sustainable-catalyst-library' ); ?></span></div><aside><strong><?php esc_html_e( 'Trust is not truth', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Peer trust governs transport and review. It never proves a claim, an institution’s authority, a user’s membership, subscription entitlement, or access rights.', 'sustainable-catalyst-library' ); ?></span></aside></header>
<div class="sc-global-federation__node"><div><p><?php esc_html_e( 'Local federation node', 'sustainable-catalyst-library' ); ?></p><strong><?php echo esc_html( (string) $node['name'] ); ?></strong><code><?php echo esc_html( (string) $node['node_id'] ); ?></code></div><dl><div><dt><?php esc_html_e( 'Version', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( self::VERSION ); ?></dd></div><div><dt><?php esc_html_e( 'Public manifests', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) count( $public_ids ) ); ?></dd></div><div><dt><?php esc_html_e( 'Exchange', 'sustainable-catalyst-library' ); ?></dt><dd><?php esc_html_e( 'Metadata references only', 'sustainable-catalyst-library' ); ?></dd></div></dl></div>
<?php if ( $public_ids ) : ?><details class="sc-global-federation__public"><summary><?php esc_html_e( 'Public federation manifests', 'sustainable-catalyst-library' ); ?></summary><ul><?php foreach ( array_slice( $public_ids, 0, 24 ) as $id ) : $m = self::manifest_state( $id ); if ( is_wp_error( $m ) ) { continue; } ?><li><div><strong><?php echo esc_html( (string) $m['title'] ); ?></strong><span><?php echo esc_html( (string) ( $m['manifest']['reference_count'] ?? 0 ) ); ?> <?php esc_html_e( 'references', 'sustainable-catalyst-library' ); ?></span></div><a href="<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/manifests/' . absint( $id ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open manifest ↗', 'sustainable-catalyst-library' ); ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
<?php if ( ! $signed || is_wp_error( $catalog ) ) : ?><div class="sc-global-federation__signin"><strong><?php esc_html_e( 'Sign in to govern federation manifests.', 'sustainable-catalyst-library' ); ?></strong><a href="<?php echo esc_url( wp_login_url( self::canonical() . '#global-research-federation' ) ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
<?php else : $libraries = (array) ( $catalog['team_libraries'] ?? array() ); $library = is_array( $catalog['selected_team_library'] ?? null ) ? $catalog['selected_team_library'] : null; ?>
<div class="sc-global-federation__workspace"><div class="sc-global-federation__message" data-sc-federation-message aria-live="polite"></div>
<?php if ( $libraries ) : ?><form class="sc-global-federation__selector" method="get" action="<?php echo esc_url( self::canonical() ); ?>"><label><span><?php esc_html_e( 'Governed Team Library', 'sustainable-catalyst-library' ); ?></span><select name="sc_federation_library"><?php foreach ( $libraries as $item ) : ?><option value="<?php echo esc_attr( (string) absint( $item['library_id'] ) ); ?>" <?php selected( absint( $catalog['selected_team_library_id'] ?? 0 ), absint( $item['library_id'] ) ); ?>><?php echo esc_html( (string) $item['title'] . ' · ' . (string) $item['viewer_role'] ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Open federation view', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?>
<?php if ( $library ) : $refs = array_slice( (array) ( $library['references'] ?? array() ), -80 ); ?><form class="sc-global-federation__manifest-form" data-sc-federation-manifest><input type="hidden" name="team_library_id" value="<?php echo esc_attr( (string) absint( $library['library_id'] ) ); ?>"><div><label><span><?php esc_html_e( 'Manifest title', 'sustainable-catalyst-library' ); ?></span><input name="title" maxlength="180" required placeholder="<?php esc_attr_e( 'Federated collection title', 'sustainable-catalyst-library' ); ?>"></label><label><span><?php esc_html_e( 'Initial state', 'sustainable-catalyst-library' ); ?></span><select name="status"><option value="draft"><?php esc_html_e( 'Private draft', 'sustainable-catalyst-library' ); ?></option><option value="published"><?php esc_html_e( 'Publish metadata', 'sustainable-catalyst-library' ); ?></option></select></label></div><fieldset><legend><?php esc_html_e( 'Explicitly include references', 'sustainable-catalyst-library' ); ?></legend><?php if ( $refs ) : ?><div class="sc-global-federation__reference-picker"><?php foreach ( $refs as $ref ) : ?><label><input type="checkbox" name="reference_ids[]" value="<?php echo esc_attr( (string) ( $ref['reference_id'] ?? '' ) ); ?>"><span><strong><?php echo esc_html( (string) ( $ref['title'] ?? __( 'Reference', 'sustainable-catalyst-library' ) ) ); ?></strong><small><?php echo esc_html( (string) ( $ref['kind'] ?? 'external' ) ); ?></small></span></label><?php endforeach; ?></div><?php else : ?><p><?php esc_html_e( 'This Team Library does not yet contain references. Add references in Institutional & Team Libraries first.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?></fieldset><button type="submit" <?php disabled( ! $refs ); ?>><?php esc_html_e( 'Prepare federation manifest', 'sustainable-catalyst-library' ); ?></button></form><?php endif; ?>
<?php $manifests = (array) ( $catalog['manifests'] ?? array() ); if ( $manifests ) : ?><div class="sc-global-federation__manifests"><h4><?php esc_html_e( 'Governed manifests', 'sustainable-catalyst-library' ); ?></h4><ul><?php foreach ( array_slice( $manifests, 0, 30 ) as $item ) : ?><li data-sc-federation-manifest-id="<?php echo esc_attr( (string) absint( $item['manifest_id'] ?? 0 ) ); ?>"><div><strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong><span><?php echo esc_html( strtoupper( (string) ( $item['status'] ?? 'draft' ) ) ); ?></span></div><code><?php echo esc_html( (string) ( $item['manifest_urn'] ?? '' ) ); ?></code><div class="sc-global-federation__manifest-actions"><button type="button" data-sc-federation-status="published"><?php esc_html_e( 'Publish', 'sustainable-catalyst-library' ); ?></button><button type="button" data-sc-federation-status="draft"><?php esc_html_e( 'Draft', 'sustainable-catalyst-library' ); ?></button><button type="button" data-sc-federation-status="revoked"><?php esc_html_e( 'Revoke', 'sustainable-catalyst-library' ); ?></button></div></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ( current_user_can( 'manage_options' ) ) : ?><aside class="sc-global-federation__admin"><strong><?php esc_html_e( 'Inbound federation governance', 'sustainable-catalyst-library' ); ?></strong><p><?php esc_html_e( 'Remote manifests continue through the canonical v3.9 federation quarantine and explicit metadata-approval workflow. Approval alone creates no Team Library records; an owner or steward must separately accept approved references into a governed Team Library.', 'sustainable-catalyst-library' ); ?></p></aside><?php endif; ?>
</div>
<?php endif; ?>
<p class="sc-global-federation__boundary"><?php esc_html_e( 'Global Research Federation exchanges public or explicitly shared metadata only. It does not federate private projects, My Library, room membership, notebook or matrix bodies, source binaries, credentials, or Workspace state. Remote node identity, peer trust, and institutional context are never treated as proof of truth, membership, entitlement, or access.', 'sustainable-catalyst-library' ); ?></p>
</section><?php return (string) ob_get_clean();
    }
}
