<?php
/**
 * Global Research Discovery & Federated Search — v5.1.0.
 *
 * Deterministic public discovery over canonical published Library objects and
 * explicitly published federation manifests already present on this node.
 * No remote crawling, private research inspection, semantic inference, or
 * truth scoring occurs during search.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Global_Research_Discovery_Federated_Search {
    public const VERSION = '5.1.0';
    public const SCHEMA = 'sc-library-global-research-discovery/1.0';
    public const RESULT_SCHEMA = 'sc-library-discovery-result/1.0';
    public const FACET_SCHEMA = 'sc-library-discovery-facets/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/research-discovery';
    public const MAX_RESULTS = 50;
    public const DEFAULT_RESULTS = 20;
    public const MAX_QUERY_LENGTH = 240;
    public const MAX_LOCAL_CANDIDATES = 120;
    public const MAX_FEDERATION_MANIFESTS = 120;
    public const MAX_FEDERATED_CANDIDATES = 240;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_global_research_discovery', array( $this, 'shortcode' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_response' ), 145, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'v490_public_object_api_reused' => true,
            'v500_connected_public_context_reused' => true,
            'v480_published_federation_manifests_reused' => true,
            'canonical_public_records_only' => true,
            'published_federation_metadata_only' => true,
            'remote_network_calls_during_search' => false,
            'remote_crawling' => false,
            'private_projects_searched' => false,
            'personal_library_searched' => false,
            'notebook_bodies_searched' => false,
            'matrix_bodies_searched' => false,
            'research_room_membership_searched' => false,
            'team_library_membership_searched' => false,
            'private_federation_governance_searched' => false,
            'ranking_mode' => 'deterministic-lexical',
            'semantic_inference' => false,
            'truth_scoring' => false,
            'institutional_authority_scoring' => false,
            'access_entitlement_inferred' => false,
            'public_get_only' => true,
            'cors_credentials_allowed' => false,
            'automatic_import' => false,
            'automatic_publication' => false,
            'automatic_workspace_write' => false,
        );
    }

    public static function capabilities() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'search_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/search' ) ),
            'facets_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/facets' ) ),
            'local_public_types' => class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? array_keys( SC_Library_API_Embeds_Interoperability::object_profiles() ) : array(),
            'origins' => array( 'local', 'federated' ),
            'ranking' => array(
                'mode' => 'deterministic-lexical',
                'signals' => array( 'exact-title', 'title-prefix', 'title-token', 'summary-token', 'canonical-id', 'type-label' ),
                'truth_or_quality_score' => false,
            ),
            'federation' => array(
                'published_manifests_only' => true,
                'remote_network_calls_during_search' => false,
                'provenance_preserved' => true,
            ),
            'privacy' => array(
                'private_research_included' => false,
                'membership_data_included' => false,
                'credentials_included' => false,
            ),
        );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-research-discovery-v510', SC_LIBRARY_URL . 'assets/css/sc-library-research-discovery-v510.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-research-discovery-v510', SC_LIBRARY_URL . 'assets/js/sc-library-research-discovery-v510.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_capabilities' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_search' ),
            'args' => array(
                'q' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                'type' => array( 'sanitize_callback' => 'sanitize_key' ),
                'origin' => array( 'sanitize_callback' => 'sanitize_key' ),
                'page' => array( 'sanitize_callback' => 'absint' ),
                'per_page' => array( 'sanitize_callback' => 'absint' ),
            ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/facets', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'rest_facets' ),
        ) );
    }

    private static function clean_query( $query ) {
        $query = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $query ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $query, 0, self::MAX_QUERY_LENGTH ) : substr( $query, 0, self::MAX_QUERY_LENGTH );
    }

    private static function lower( $value ) {
        $value = (string) $value;
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    }

    public static function lexical_score( $query, array $candidate ) {
        $q = self::lower( self::clean_query( $query ) );
        if ( '' === $q ) { return 0; }
        $title = self::lower( (string) ( $candidate['title'] ?? '' ) );
        $summary = self::lower( (string) ( $candidate['summary'] ?? '' ) );
        $canonical = self::lower( (string) ( $candidate['canonical_id'] ?? '' ) );
        $type = self::lower( (string) ( $candidate['type_label'] ?? $candidate['type'] ?? '' ) );
        $score = 0;
        if ( $title === $q ) { $score += 120; }
        elseif ( 0 === strpos( $title, $q ) ) { $score += 80; }
        elseif ( false !== strpos( $title, $q ) ) { $score += 55; }
        if ( false !== strpos( $summary, $q ) ) { $score += 24; }
        if ( false !== strpos( $canonical, $q ) ) { $score += 18; }
        if ( false !== strpos( $type, $q ) ) { $score += 8; }
        $tokens = array_values( array_filter( preg_split( '/[^\p{L}\p{N}]+/u', $q ) ?: array() ) );
        foreach ( array_slice( array_unique( $tokens ), 0, 12 ) as $token ) {
            if ( strlen( $token ) < 2 ) { continue; }
            if ( false !== strpos( $title, $token ) ) { $score += 12; }
            if ( false !== strpos( $summary, $token ) ) { $score += 4; }
        }
        return min( 250, $score );
    }

    private static function local_result( $type, WP_Post $post, $query ) {
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return null; }
        $object = SC_Library_API_Embeds_Interoperability::normalize_public_object( $type, $post );
        if ( is_wp_error( $object ) ) { return null; }
        $score = self::lexical_score( $query, $object );
        if ( $score <= 0 ) { return null; }
        $connection_count = 0;
        $context_url = '';
        if ( class_exists( 'SC_Library_Connected_Public_Research_Infrastructure' ) ) {
            $context = SC_Library_Connected_Public_Research_Infrastructure::build_context( $type, absint( $post->ID ) );
            if ( is_array( $context ) ) { $connection_count = (int) ( $context['connection_count'] ?? count( (array) ( $context['connections'] ?? array() ) ) ); }
            $context_url = rest_url( 'sc-library/v1/connected-public-research/context/' . sanitize_key( $type ) . '/' . absint( $post->ID ) );
        }
        return array(
            'schema' => self::RESULT_SCHEMA,
            'result_id' => 'local:' . sanitize_key( $type ) . ':' . absint( $post->ID ),
            'origin' => 'local',
            'type' => sanitize_key( $type ),
            'type_label' => (string) ( $object['type_label'] ?? $type ),
            'title' => (string) ( $object['title'] ?? '' ),
            'summary' => (string) ( $object['summary'] ?? '' ),
            'canonical_id' => (string) ( $object['canonical_id'] ?? '' ),
            'canonical_url' => esc_url_raw( (string) ( $object['canonical_url'] ?? '' ) ),
            'updated_at' => (string) ( $object['updated_at'] ?? '' ),
            'connection_count' => $connection_count,
            'context_url' => esc_url_raw( $context_url ),
            'score' => $score,
            'score_mode' => 'deterministic-lexical',
            'provenance' => array( 'source' => 'canonical-local-public-record', 'publisher' => get_bloginfo( 'name' ) ),
        );
    }

    private static function local_candidates( $query, $type_filter = '' ) {
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return array(); }
        $profiles = SC_Library_API_Embeds_Interoperability::object_profiles();
        if ( $type_filter && ! isset( $profiles[ $type_filter ] ) ) { return array(); }
        $types = $type_filter ? array( $type_filter ) : array_keys( $profiles );
        $post_types = array_values( array_unique( array_map( static fn( $type ) => $profiles[ $type ]['post_type'], $types ) ) );
        $query_args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => self::MAX_LOCAL_CANDIDATES,
            'no_found_rows' => true,
            'orderby' => 'relevance',
            'order' => 'DESC',
        );
        $posts = get_posts( $query_args );
        $post_type_map = array();
        foreach ( $types as $type ) { $post_type_map[ $profiles[ $type ]['post_type'] ][] = $type; }
        $out = array();
        foreach ( (array) $posts as $post ) {
            if ( ! $post instanceof WP_Post ) { continue; }
            foreach ( (array) ( $post_type_map[ $post->post_type ] ?? array() ) as $type ) {
                $item = self::local_result( $type, $post, $query );
                if ( is_array( $item ) ) { $out[] = $item; break; }
            }
        }
        return $out;
    }

    private static function federated_candidates( $query, $type_filter = '' ) {
        if ( ! class_exists( 'SC_Library_Global_Research_Federation' ) ) { return array(); }
        $out = array(); $seen = array(); $candidate_count = 0;
        foreach ( array_slice( SC_Library_Global_Research_Federation::published_manifest_ids(), 0, self::MAX_FEDERATION_MANIFESTS ) as $manifest_id ) {
            $state = SC_Library_Global_Research_Federation::manifest_state( absint( $manifest_id ) );
            if ( is_wp_error( $state ) || 'published' !== (string) ( $state['status'] ?? '' ) ) { continue; }
            $manifest = (array) ( $state['manifest'] ?? array() );
            $node_id = (string) ( $manifest['origin_node_id'] ?? '' );
            foreach ( (array) ( $manifest['records'] ?? array() ) as $record ) {
                if ( ++$candidate_count > self::MAX_FEDERATED_CANDIDATES ) { break 2; }
                if ( ! is_array( $record ) ) { continue; }
                $record_type = sanitize_key( (string) ( $record['type'] ?? $record['kind'] ?? 'external' ) );
                if ( $type_filter && $record_type !== $type_filter ) { continue; }
                $candidate = array(
                    'title' => (string) ( $record['title'] ?? '' ),
                    'summary' => (string) ( $record['summary'] ?? $record['provenance'] ?? '' ),
                    'canonical_id' => (string) ( $record['canonical_id'] ?? $record['id'] ?? $record['reference_id'] ?? '' ),
                    'type' => $record_type,
                    'type_label' => $record_type,
                );
                $score = self::lexical_score( $query, $candidate );
                if ( $score <= 0 ) { continue; }
                $key = $candidate['canonical_id'] ?: esc_url_raw( (string) ( $record['url'] ?? '' ) );
                if ( ! $key || isset( $seen[ $key ] ) ) { continue; }
                $seen[ $key ] = true;
                $out[] = array(
                    'schema' => self::RESULT_SCHEMA,
                    'result_id' => 'federated:' . absint( $manifest_id ) . ':' . substr( hash( 'sha256', $key ), 0, 16 ),
                    'origin' => 'federated',
                    'type' => $record_type,
                    'type_label' => $record_type,
                    'title' => (string) $candidate['title'],
                    'summary' => (string) $candidate['summary'],
                    'canonical_id' => (string) $candidate['canonical_id'],
                    'canonical_url' => esc_url_raw( (string) ( $record['url'] ?? '' ) ),
                    'updated_at' => (string) ( $state['published_at'] ?? '' ),
                    'connection_count' => 0,
                    'context_url' => '',
                    'score' => $score,
                    'score_mode' => 'deterministic-lexical',
                    'provenance' => array(
                        'source' => 'published-federation-manifest',
                        'node_id' => $node_id,
                        'manifest_id' => absint( $manifest_id ),
                        'manifest_urn' => (string) ( $state['manifest_urn'] ?? '' ),
                        'manifest_sha256' => (string) ( $state['sha256'] ?? '' ),
                        'record_provenance' => (string) ( $record['provenance'] ?? '' ),
                    ),
                );
            }
        }
        return $out;
    }

    private static function sort_results( array &$items ) {
        usort( $items, static function( $a, $b ) {
            $score = (int) ( $b['score'] ?? 0 ) <=> (int) ( $a['score'] ?? 0 );
            if ( 0 !== $score ) { return $score; }
            $origin = ( 'local' === ( $a['origin'] ?? '' ) ? 0 : 1 ) <=> ( 'local' === ( $b['origin'] ?? '' ) ? 0 : 1 );
            if ( 0 !== $origin ) { return $origin; }
            $title = strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
            if ( 0 !== $title ) { return $title; }
            return strcmp( (string) ( $a['result_id'] ?? '' ), (string) ( $b['result_id'] ?? '' ) );
        } );
    }

    public static function search( $query, $type = '', $origin = '', $page = 1, $per_page = self::DEFAULT_RESULTS ) {
        $query = self::clean_query( $query );
        if ( strlen( $query ) < 2 ) {
            return new WP_Error( 'sc_research_discovery_query', __( 'Enter at least two characters to search public research.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $type = sanitize_key( $type ); $origin = sanitize_key( $origin );
        if ( $origin && ! in_array( $origin, array( 'local', 'federated' ), true ) ) {
            return new WP_Error( 'sc_research_discovery_origin', __( 'Choose local or federated discovery.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) );
        }
        $items = array();
        if ( 'federated' !== $origin ) { $items = array_merge( $items, self::local_candidates( $query, $type ) ); }
        if ( 'local' !== $origin ) { $items = array_merge( $items, self::federated_candidates( $query, $type ) ); }
        self::sort_results( $items );
        $total = count( $items ); $page = max( 1, absint( $page ) ); $per_page = min( self::MAX_RESULTS, max( 1, absint( $per_page ) ?: self::DEFAULT_RESULTS ) );
        $offset = ( $page - 1 ) * $per_page;
        $slice = array_slice( $items, $offset, $per_page );
        $origin_counts = array( 'local' => 0, 'federated' => 0 ); $type_counts = array();
        foreach ( $items as $item ) {
            $o = (string) ( $item['origin'] ?? '' ); if ( isset( $origin_counts[ $o ] ) ) { $origin_counts[ $o ]++; }
            $t = (string) ( $item['type'] ?? 'other' ); $type_counts[ $t ] = ( $type_counts[ $t ] ?? 0 ) + 1;
        }
        ksort( $type_counts );
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'query' => $query,
            'filters' => array( 'type' => $type, 'origin' => $origin ),
            'ranking' => 'deterministic-lexical',
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'items' => $slice,
            'facets' => array( 'origins' => $origin_counts, 'types' => $type_counts ),
            'boundaries' => array(
                'remote_network_calls_performed' => false,
                'private_research_searched' => false,
                'truth_scoring_performed' => false,
                'access_entitlement_inferred' => false,
            ),
        );
    }

    public function rest_capabilities() { return rest_ensure_response( self::capabilities() ); }
    public function rest_facets() {
        $profiles = class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::object_profiles() : array();
        $types = array(); foreach ( $profiles as $key => $profile ) { $types[] = array( 'key' => $key, 'label' => (string) ( $profile['label'] ?? $key ) ); }
        return rest_ensure_response( array( 'schema' => self::FACET_SCHEMA, 'version' => self::VERSION, 'types' => $types, 'origins' => array( 'local', 'federated' ) ) );
    }
    public function rest_search( WP_REST_Request $request ) {
        $result = self::search( $request->get_param( 'q' ), $request->get_param( 'type' ), $request->get_param( 'origin' ), $request->get_param( 'page' ), $request->get_param( 'per_page' ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public function cors_response( $response, $server, $request ) {
        $route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
        if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE . self::REST_ROUTE ) || 'GET' !== strtoupper( (string) $request->get_method() ) ) { return $response; }
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? untrailingslashit( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) : '';
        if ( ! $origin || ! in_array( $origin, SC_Library_API_Embeds_Interoperability::allowed_origins(), true ) ) { return $response; }
        if ( method_exists( $response, 'header' ) ) {
            $response->header( 'Access-Control-Allow-Origin', $origin );
            $response->header( 'Vary', 'Origin' );
            $response->header( 'Access-Control-Allow-Credentials', 'false' );
            $response->header( 'Access-Control-Allow-Methods', 'GET' );
            $response->header( 'Access-Control-Expose-Headers', 'X-SC-Library-Cache, X-SC-Library-Cache-Age, X-SC-Library-Data-State, X-SC-Library-Freshness-Window, Retry-After' );
        }
        return $response;
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Global Research Discovery & Federated Search', 'sustainable-catalyst-library' ) ), $atts, 'sc_global_research_discovery' );
        wp_enqueue_style( 'sc-library-research-discovery-v510' );
        wp_enqueue_script( 'sc-library-research-discovery-v510' );
        $id = wp_unique_id( 'sc-research-discovery-' );
        ob_start(); ?>
<section id="<?php echo esc_attr( $id ); ?>" class="sc-research-discovery" data-sc-research-discovery data-endpoint="<?php echo esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/search' ) ); ?>">
<header><p class="sc-research-discovery__kicker"><?php esc_html_e( 'Public discovery', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( (string) $atts['title'] ); ?></h3><p><?php esc_html_e( 'Search canonical public Library records and explicitly published federation metadata with visible provenance. Ranking is deterministic and lexical; it is not a truth, authority, popularity, or entitlement score.', 'sustainable-catalyst-library' ); ?></p></header>
<form class="sc-research-discovery__form" role="search"><label><span><?php esc_html_e( 'Search public research', 'sustainable-catalyst-library' ); ?></span><input name="q" type="search" minlength="2" maxlength="240" required autocomplete="off" placeholder="<?php esc_attr_e( 'Topic, title, source, concept, institution…', 'sustainable-catalyst-library' ); ?>"></label><label><span><?php esc_html_e( 'Origin', 'sustainable-catalyst-library' ); ?></span><select name="origin"><option value=""><?php esc_html_e( 'Local + federated', 'sustainable-catalyst-library' ); ?></option><option value="local"><?php esc_html_e( 'Local public records', 'sustainable-catalyst-library' ); ?></option><option value="federated"><?php esc_html_e( 'Published federation metadata', 'sustainable-catalyst-library' ); ?></option></select></label><button type="submit"><?php esc_html_e( 'Search research', 'sustainable-catalyst-library' ); ?></button></form>
<div class="sc-research-discovery__status" role="status" aria-live="polite"></div><div class="sc-research-discovery__results" data-results></div>
<footer><small><?php esc_html_e( 'No remote crawling occurs during a search. Private Projects, My Library, notebooks, Evidence Matrices, Research Rooms, Team Library membership, credentials, and private federation governance are outside the search corpus.', 'sustainable-catalyst-library' ); ?></small></footer>
</section><?php return (string) ob_get_clean();
    }
}
