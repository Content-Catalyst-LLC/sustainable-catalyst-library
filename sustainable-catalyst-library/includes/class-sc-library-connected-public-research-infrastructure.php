<?php
/**
 * Connected Public Research Infrastructure — v5.0.0.
 *
 * Composes the v4.9 public API with explicit public knowledge, publication,
 * pathway, and federation relationships. No parallel graph/content store is
 * created, and private research never becomes public through this facade.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Connected_Public_Research_Infrastructure {
    public const VERSION = '5.0.0';
    public const SCHEMA = 'sc-library-connected-public-research/1.0';
    public const CONTEXT_SCHEMA = 'sc-library-public-research-context/1.0';
    public const NETWORK_SCHEMA = 'sc-library-public-research-network/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-public-research-manifest/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/connected-public-research';
    public const DEFAULT_RESULTS = 18;
    public const MAX_RESULTS = 60;
    public const MAX_CONNECTIONS = 120;
    public const MAX_FEDERATION_MANIFESTS = 20;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_head', array( $this, 'render_discovery_link' ), 30 );
        add_shortcode( 'sc_connected_public_research', array( $this, 'shortcode_console' ) );
        add_shortcode( 'sc_public_research_context', array( $this, 'shortcode_context' ) );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_response' ), 145, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'v490_public_api_reused' => true,
            'v4337_publication_graph_reused' => true,
            'v330_pathway_graph_reused' => true,
            'v320_public_knowledge_relationships_reused' => true,
            'v480_published_federation_manifests_reused' => true,
            'creates_parallel_public_record_store' => false,
            'creates_parallel_graph_store' => false,
            'creates_parallel_federation_registry' => false,
            'explicit_relationships_only' => true,
            'one_hop_network_only' => true,
            'public_get_only' => true,
            'raw_post_meta_exposed' => false,
            'private_projects_exposed' => false,
            'personal_library_exposed' => false,
            'notebook_bodies_exposed' => false,
            'matrix_bodies_exposed' => false,
            'research_room_membership_exposed' => false,
            'team_library_membership_exposed' => false,
            'private_federation_governance_exposed' => false,
            'credentials_exposed' => false,
            'automatic_semantic_inference' => false,
            'automatic_publication' => false,
            'automatic_federation_acceptance' => false,
            'automatic_evidence_promotion' => false,
            'automatic_workspace_write' => false,
        );
    }

    public static function infrastructure_profile() {
        $api = class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::interoperability_profile() : array();
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'canonical_library' => class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ),
            'public_api' => $api,
            'context_schema' => self::CONTEXT_SCHEMA,
            'network_schema' => self::NETWORK_SCHEMA,
            'manifest_schema' => self::MANIFEST_SCHEMA,
            'connection_policy' => 'explicit-one-hop-only',
            'writes_supported' => false,
            'credentials_supported' => false,
            'private_research_supported' => false,
        );
    }

    public function register_assets() {
        wp_register_style( 'sc-connected-public-research-v500', SC_LIBRARY_URL . 'assets/css/sc-library-connected-public-research-v500.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-connected-public-research-v500', SC_LIBRARY_URL . 'assets/js/sc-library-connected-public-research-v500.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        $read = array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true' );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, $read + array( 'callback' => array( $this, 'rest_capabilities' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/index', $read + array(
            'callback' => array( $this, 'rest_index' ),
            'args' => array(
                'type' => array( 'sanitize_callback' => 'sanitize_key' ),
                'q' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                'per_page' => array( 'sanitize_callback' => 'absint' ),
            ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/context/(?P<type>[a-z0-9-]+)/(?P<id>\\d+)', $read + array( 'callback' => array( $this, 'rest_context' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/network/(?P<type>[a-z0-9-]+)/(?P<id>\\d+)', $read + array( 'callback' => array( $this, 'rest_network' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/manifest/(?P<type>[a-z0-9-]+)/(?P<id>\\d+)', $read + array( 'callback' => array( $this, 'rest_manifest' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/federation-manifests', $read + array( 'callback' => array( $this, 'rest_federation_manifests' ) ) );
    }

    private static function api_profiles() {
        return class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::object_profiles() : array();
    }

    private static function public_object( $type, $id ) {
        $type = sanitize_key( $type );
        $id = absint( $id );
        $profiles = self::api_profiles();
        if ( ! isset( $profiles[ $type ] ) || ! $id ) {
            return new WP_Error( 'sc_public_research_type', __( 'Unknown public Library object type.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        $post = get_post( $id );
        if ( ! $post instanceof WP_Post || $post->post_type !== $profiles[ $type ]['post_type'] || 'publish' !== $post->post_status ) {
            return new WP_Error( 'sc_public_research_missing', __( 'Public Library object not found.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) );
        }
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) {
            return new WP_Error( 'sc_public_research_api_missing', __( 'The public Library API layer is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 503 ) );
        }
        return SC_Library_API_Embeds_Interoperability::normalize_public_object( $type, $post );
    }

    private static function clean_text( $value, $limit = 260 ) {
        $value = trim( preg_replace( '/\\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
        if ( function_exists( 'mb_substr' ) ) { return mb_substr( $value, 0, $limit ); }
        return substr( $value, 0, $limit );
    }

    private static function connection( $type, $id, $title, $url, $relation, $source, $extra = array() ) {
        $type = sanitize_key( $type );
        $relation = sanitize_key( $relation );
        $id_value = is_numeric( $id ) ? absint( $id ) : sanitize_text_field( (string) $id );
        return array_merge( array(
            'type' => $type,
            'id' => $id_value,
            'key' => $type . ':' . (string) $id_value,
            'title' => self::clean_text( $title, 240 ),
            'url' => esc_url_raw( (string) $url ),
            'relation' => $relation,
            'provenance' => sanitize_key( $source ),
            'explicit' => true,
            'public' => true,
        ), is_array( $extra ) ? $extra : array() );
    }

    private static function add_connection( array &$out, array &$seen, array $connection ) {
        if ( count( $out ) >= self::MAX_CONNECTIONS ) { return; }
        $key = (string) ( $connection['key'] ?? '' );
        if ( '' === $key || isset( $seen[ $key ] ) ) { return; }
        $seen[ $key ] = true;
        $out[] = $connection;
    }

    private static function node_type_from_kind( $kind ) {
        $map = array(
            'document' => 'foundation-document', 'source' => 'research-source', 'claim' => 'research-claim',
            'evidence' => 'evidence-note', 'concept' => 'concept', 'entity' => 'named-entity',
            'topic' => 'topic', 'vocabulary' => 'controlled-vocabulary', 'pathway' => 'pathway',
        );
        return $map[ sanitize_key( $kind ) ] ?? sanitize_key( $kind );
    }

    private static function node_kind_from_api_type( $type ) {
        $map = array(
            'foundation-document' => 'document', 'research-source' => 'source',
            'named-entity' => 'entity', 'concept' => 'concept',
        );
        return $map[ sanitize_key( $type ) ] ?? '';
    }

    private static function add_topic_connections( array &$connections, array &$seen, array $topics, $source ) {
        foreach ( $topics as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( 'topic', $item['id'], $item['name'] ?? $item['label'] ?? '', $item['url'] ?? '', 'about-topic', $source ) );
        }
    }

    private static function add_concept_connections( array &$connections, array &$seen, array $items, $source ) {
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( 'concept', $item['id'], $item['label'] ?? $item['title'] ?? '', $item['url'] ?? '', 'uses-concept', $source ) );
        }
    }

    private static function add_entity_connections( array &$connections, array &$seen, array $items, $source ) {
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( 'named-entity', $item['id'], $item['label'] ?? $item['title'] ?? '', $item['url'] ?? '', 'mentions-entity', $source ) );
        }
    }

    private static function publication_connections( $id, array &$connections, array &$seen ) {
        if ( ! class_exists( 'SC_Library_Publications_Research_Graph' ) ) { return; }
        $graph = SC_Library_Publications_Research_Graph::build_graph( absint( $id ) );
        if ( ! is_array( $graph ) || ! $graph ) { return; }
        self::add_topic_connections( $connections, $seen, (array) ( $graph['topics'] ?? array() ), 'publication-research-graph' );
        self::add_concept_connections( $connections, $seen, (array) ( $graph['concepts'] ?? array() ), 'publication-research-graph' );
        self::add_entity_connections( $connections, $seen, (array) ( $graph['entities'] ?? array() ), 'publication-research-graph' );
        foreach ( (array) ( $graph['sources'] ?? array() ) as $item ) {
            if ( is_array( $item ) && ! empty( $item['id'] ) ) self::add_connection( $connections, $seen, self::connection( 'research-source', $item['id'], $item['title'] ?? '', $item['url'] ?? '', 'cites', 'publication-research-graph' ) );
        }
        foreach ( (array) ( $graph['claims'] ?? array() ) as $item ) {
            if ( is_array( $item ) && ! empty( $item['id'] ) ) self::add_connection( $connections, $seen, self::connection( 'research-claim', $item['id'], $item['title'] ?? '', $item['url'] ?? '', 'states-claim', 'publication-research-graph' ) );
        }
        foreach ( (array) ( $graph['pathways'] ?? array() ) as $item ) {
            if ( is_array( $item ) && ! empty( $item['id'] ) ) self::add_connection( $connections, $seen, self::connection( 'pathway', $item['id'], $item['title'] ?? '', $item['url'] ?? '', 'linked-pathway', 'publication-research-graph' ) );
        }
        $map = $graph['article_map'] ?? null;
        if ( is_array( $map ) && ! empty( $map['key'] ) ) {
            self::add_connection( $connections, $seen, self::connection( 'article-map', $map['key'], $map['title'] ?? $map['key'], $map['url'] ?? '', 'article-map', 'publication-research-graph', array( 'field' => self::clean_text( $map['field'] ?? '', 160 ) ) ) );
        }
    }

    private static function pathway_connections( $id, array &$connections, array &$seen ) {
        if ( ! class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) ) { return; }
        $data = SC_Library_Knowledge_Pathways_Article_Maps::get_pathway_data( absint( $id ), false );
        if ( ! is_array( $data ) || ! $data || empty( $data['public'] ) ) { return; }
        self::add_topic_connections( $connections, $seen, (array) ( $data['topics'] ?? array() ), 'knowledge-pathway' );
        self::add_concept_connections( $connections, $seen, (array) ( $data['concepts'] ?? array() ), 'knowledge-pathway' );
        self::add_entity_connections( $connections, $seen, (array) ( $data['entities'] ?? array() ), 'knowledge-pathway' );
        foreach ( (array) ( $data['steps'] ?? array() ) as $step ) {
            if ( ! is_array( $step ) || empty( $step['node_id'] ) || empty( $step['public'] ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( self::node_type_from_kind( $step['kind'] ?? '' ), $step['node_id'], $step['label'] ?? '', $step['url'] ?? '', 'pathway-step', 'knowledge-pathway', array( 'stage' => sanitize_key( $step['stage'] ?? '' ), 'required' => ! empty( $step['required'] ) ) ) );
        }
        foreach ( (array) ( $data['prerequisite_ids'] ?? array() ) as $pathway_id ) {
            if ( 'publish' !== get_post_status( absint( $pathway_id ) ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( 'pathway', $pathway_id, get_the_title( $pathway_id ), get_permalink( $pathway_id ), 'prerequisite', 'knowledge-pathway' ) );
        }
        foreach ( (array) ( $data['continuation_ids'] ?? array() ) as $pathway_id ) {
            if ( 'publish' !== get_post_status( absint( $pathway_id ) ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection( 'pathway', $pathway_id, get_the_title( $pathway_id ), get_permalink( $pathway_id ), 'continues-to', 'knowledge-pathway' ) );
        }
    }

    private static function knowledge_node_connections( $kind, $id, array &$connections, array &$seen ) {
        if ( ! $kind || ! class_exists( 'SC_Library_Topics_Concepts_Relationships' ) ) { return; }
        $data = SC_Library_Topics_Concepts_Relationships::get_node_data( $kind, absint( $id ), false );
        if ( ! is_array( $data ) || ! $data || empty( $data['public'] ) ) { return; }
        self::add_topic_connections( $connections, $seen, (array) ( $data['topics'] ?? array() ), 'public-knowledge-graph' );
        self::add_concept_connections( $connections, $seen, (array) ( $data['concepts'] ?? array() ), 'public-knowledge-graph' );
        self::add_entity_connections( $connections, $seen, (array) ( $data['entities'] ?? array() ), 'public-knowledge-graph' );
        foreach ( (array) ( $data['relationships'] ?? array() ) as $relation ) {
            if ( ! is_array( $relation ) || empty( $relation['public'] ) || empty( $relation['other_id'] ) ) { continue; }
            self::add_connection( $connections, $seen, self::connection(
                self::node_type_from_kind( $relation['other_kind'] ?? '' ),
                $relation['other_id'],
                $relation['other_label'] ?? '',
                $relation['other_url'] ?? '',
                $relation['relation'] ?? 'related-to',
                'public-knowledge-graph',
                array( 'direction' => sanitize_key( $relation['direction'] ?? '' ), 'relationship_id' => absint( $relation['relation_id'] ?? 0 ) )
            ) );
        }
    }

    public static function build_context( $type, $id ) {
        $object = self::public_object( $type, $id );
        if ( is_wp_error( $object ) ) { return $object; }
        $connections = array(); $seen = array();
        if ( 'publication' === sanitize_key( $type ) ) { self::publication_connections( $id, $connections, $seen ); }
        elseif ( 'pathway' === sanitize_key( $type ) ) { self::pathway_connections( $id, $connections, $seen ); }
        else { self::knowledge_node_connections( self::node_kind_from_api_type( $type ), $id, $connections, $seen ); }
        $counts = array();
        foreach ( $connections as $connection ) { $k = (string) ( $connection['type'] ?? 'other' ); $counts[ $k ] = ( $counts[ $k ] ?? 0 ) + 1; }
        ksort( $counts );
        $payload = array(
            'schema' => self::CONTEXT_SCHEMA,
            'version' => self::VERSION,
            'object' => $object,
            'connections' => array_values( $connections ),
            'connection_counts' => $counts,
            'connection_total' => count( $connections ),
            'explicit_relationships_only' => true,
            'one_hop_only' => true,
            'private_research_excluded' => true,
            'private_governance_excluded' => true,
            'automatic_inference' => false,
        );
        $hash_input = $payload;
        $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $hash_input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return apply_filters( 'sc_library_connected_public_research_context', $payload, sanitize_key( $type ), absint( $id ) );
    }

    public static function build_network( $type, $id ) {
        $context = self::build_context( $type, $id );
        if ( is_wp_error( $context ) ) { return $context; }
        $root = (array) ( $context['object'] ?? array() );
        $root_key = (string) ( $root['canonical_id'] ?? ( sanitize_key( $type ) . ':' . absint( $id ) ) );
        $nodes = array( array( 'key' => $root_key, 'type' => sanitize_key( $type ), 'id' => absint( $id ), 'title' => $root['title'] ?? '', 'url' => $root['canonical_url'] ?? '', 'root' => true ) );
        $edges = array();
        foreach ( (array) ( $context['connections'] ?? array() ) as $item ) {
            $key = (string) ( $item['key'] ?? '' );
            if ( ! $key ) { continue; }
            $nodes[] = array( 'key' => $key, 'type' => $item['type'] ?? '', 'id' => $item['id'] ?? 0, 'title' => $item['title'] ?? '', 'url' => $item['url'] ?? '', 'root' => false );
            $edges[] = array( 'from' => $root_key, 'to' => $key, 'relation' => $item['relation'] ?? 'related-to', 'provenance' => $item['provenance'] ?? '' );
        }
        return array(
            'schema' => self::NETWORK_SCHEMA,
            'version' => self::VERSION,
            'root' => $root_key,
            'nodes' => $nodes,
            'edges' => $edges,
            'node_count' => count( $nodes ),
            'edge_count' => count( $edges ),
            'one_hop_only' => true,
            'explicit_relationships_only' => true,
            'private_research_excluded' => true,
        );
    }

    public static function context_manifest( $type, $id ) {
        $context = self::build_context( $type, $id );
        if ( is_wp_error( $context ) ) { return $context; }
        $payload = array(
            'schema' => self::MANIFEST_SCHEMA,
            'version' => self::VERSION,
            'generated_at' => gmdate( 'c' ),
            'canonical_library' => class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' ),
            'context_schema' => self::CONTEXT_SCHEMA,
            'object' => $context['object'],
            'connection_total' => $context['connection_total'],
            'connection_counts' => $context['connection_counts'],
            'context_sha256' => $context['manifest_sha256'],
            'references_only' => true,
            'private_content_included' => false,
            'credentials_included' => false,
            'read_only' => true,
        );
        $payload['sha256'] = hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return $payload;
    }

    public static function public_federation_manifests() {
        if ( ! class_exists( 'SC_Library_Global_Research_Federation' ) ) { return array(); }
        $out = array();
        foreach ( array_slice( SC_Library_Global_Research_Federation::published_manifest_ids(), 0, self::MAX_FEDERATION_MANIFESTS ) as $id ) {
            $state = SC_Library_Global_Research_Federation::manifest_state( absint( $id ), 0, false );
            if ( is_wp_error( $state ) || ! is_array( $state ) || 'published' !== ( $state['status'] ?? '' ) ) { continue; }
            $manifest = (array) ( $state['manifest'] ?? array() );
            $out[] = array(
                'manifest_id' => absint( $state['manifest_id'] ?? $id ),
                'manifest_urn' => sanitize_text_field( (string) ( $state['manifest_urn'] ?? '' ) ),
                'title' => self::clean_text( $state['title'] ?? '', 200 ),
                'published_at' => sanitize_text_field( (string) ( $state['published_at'] ?? '' ) ),
                'sha256' => sanitize_text_field( (string) ( $state['sha256'] ?? '' ) ),
                'reference_count' => absint( $manifest['reference_count'] ?? 0 ),
                'references_only' => true,
                'public_metadata_only' => true,
            );
        }
        return $out;
    }

    public static function index_payload( $type = '', $query = '', $per_page = self::DEFAULT_RESULTS ) {
        $profiles = self::api_profiles();
        $type = sanitize_key( $type );
        $per_page = max( 1, min( self::MAX_RESULTS, absint( $per_page ) ?: self::DEFAULT_RESULTS ) );
        if ( $type && ! isset( $profiles[ $type ] ) ) { return new WP_Error( 'sc_public_research_type', __( 'Unknown public Library object type.', 'sustainable-catalyst-library' ), array( 'status' => 400 ) ); }
        $types = $type ? array( $type ) : array_keys( $profiles );
        $objects = array();
        $per_type = $type ? $per_page : max( 2, (int) ceil( $per_page / max( 1, count( $types ) ) ) );
        foreach ( $types as $api_type ) {
            $args = array( 'post_type' => $profiles[ $api_type ]['post_type'], 'post_status' => 'publish', 'posts_per_page' => $per_type, 'orderby' => 'modified', 'order' => 'DESC' );
            if ( '' !== trim( (string) $query ) ) { $args['s'] = sanitize_text_field( $query ); }
            foreach ( get_posts( $args ) as $post ) {
                $record = SC_Library_API_Embeds_Interoperability::normalize_public_object( $api_type, $post );
                if ( ! is_wp_error( $record ) ) {
                    $record['context_url'] = esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/context/' . $api_type . '/' . absint( $post->ID ) ) );
                    $objects[] = $record;
                }
                if ( count( $objects ) >= $per_page ) { break 2; }
            }
        }
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'objects' => $objects,
            'count' => count( $objects ),
            'object_types' => array_keys( $profiles ),
            'published_federation_manifest_count' => count( self::public_federation_manifests() ),
            'explicit_relationships_only' => true,
            'private_research_excluded' => true,
        );
    }

    public function rest_capabilities() { return rest_ensure_response( array( 'profile' => self::infrastructure_profile(), 'contract' => self::contract(), 'endpoints' => array( 'index' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/index' ), 'context' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/context/{type}/{id}' ), 'network' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/network/{type}/{id}' ), 'manifest' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/manifest/{type}/{id}' ), 'federation_manifests' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/federation-manifests' ) ) ) ); }
    public function rest_index( WP_REST_Request $request ) { $out = self::index_payload( $request->get_param( 'type' ), $request->get_param( 'q' ), $request->get_param( 'per_page' ) ); return is_wp_error( $out ) ? $out : rest_ensure_response( $out ); }
    public function rest_context( WP_REST_Request $request ) { $out = self::build_context( $request['type'], $request['id'] ); return is_wp_error( $out ) ? $out : rest_ensure_response( $out ); }
    public function rest_network( WP_REST_Request $request ) { $out = self::build_network( $request['type'], $request['id'] ); return is_wp_error( $out ) ? $out : rest_ensure_response( $out ); }
    public function rest_manifest( WP_REST_Request $request ) { $out = self::context_manifest( $request['type'], $request['id'] ); return is_wp_error( $out ) ? $out : rest_ensure_response( $out ); }
    public function rest_federation_manifests() { return rest_ensure_response( array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'manifests' => self::public_federation_manifests(), 'references_only' => true, 'private_governance_excluded' => true ) ); }

    public function cors_response( $response, $server, $request ) {
        if ( ! $request instanceof WP_REST_Request || 0 !== strpos( (string) $request->get_route(), '/' . self::REST_NAMESPACE . self::REST_ROUTE ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
        $allowed = class_exists( 'SC_Library_API_Embeds_Interoperability' ) ? SC_Library_API_Embeds_Interoperability::allowed_origins() : array();
        if ( $origin && in_array( $origin, $allowed, true ) && $response instanceof WP_HTTP_Response ) {
            $response->header( 'Access-Control-Allow-Origin', $origin );
            $response->header( 'Access-Control-Allow-Credentials', 'false' );
            $response->header( 'Access-Control-Allow-Methods', 'GET' );
            $response->header( 'Vary', 'Origin' );
        }
        return $response;
    }

    private static function type_for_post( $post ) {
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) { return ''; }
        foreach ( self::api_profiles() as $type => $profile ) { if ( $post->post_type === $profile['post_type'] ) return $type; }
        return '';
    }

    public function render_discovery_link() {
        if ( ! is_singular() ) { return; }
        $post = get_post(); $type = self::type_for_post( $post );
        if ( ! $type ) { return; }
        $href = rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/context/' . $type . '/' . absint( $post->ID ) );
        echo '<link rel="alternate" type="application/vnd.sustainable-catalyst.public-research+json" href="' . esc_url( $href ) . '">' . "\n";
    }

    private static function render_context_card( array $context ) {
        $object = (array) ( $context['object'] ?? array() );
        ob_start(); ?>
        <article class="sc-cpri-context-card">
            <p><?php echo esc_html( (string) ( $object['type_label'] ?? __( 'Public Library object', 'sustainable-catalyst-library' ) ) ); ?></p>
            <h4><?php echo esc_html( (string) ( $object['title'] ?? '' ) ); ?></h4>
            <?php if ( ! empty( $object['summary'] ) ) : ?><p><?php echo esc_html( (string) $object['summary'] ); ?></p><?php endif; ?>
            <dl><div><dt><?php esc_html_e( 'Explicit connections', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) absint( $context['connection_total'] ?? 0 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Network depth', 'sustainable-catalyst-library' ); ?></dt><dd>1</dd></div></dl>
            <?php if ( ! empty( $object['canonical_url'] ) ) : ?><a href="<?php echo esc_url( $object['canonical_url'] ); ?>"><?php esc_html_e( 'Open canonical record →', 'sustainable-catalyst-library' ); ?></a><?php endif; ?>
        </article><?php return (string) ob_get_clean();
    }

    public function shortcode_context( $atts ) {
        $atts = shortcode_atts( array( 'type' => '', 'id' => 0 ), $atts, 'sc_public_research_context' );
        $context = self::build_context( sanitize_key( $atts['type'] ), absint( $atts['id'] ) );
        if ( is_wp_error( $context ) ) { return ''; }
        wp_enqueue_style( 'sc-connected-public-research-v500' );
        return self::render_context_card( $context );
    }

    public function shortcode_console( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Connected Public Research Infrastructure', 'sustainable-catalyst-library' ) ), $atts, 'sc_connected_public_research' );
        wp_enqueue_style( 'sc-connected-public-research-v500' ); wp_enqueue_script( 'sc-connected-public-research-v500' );
        $base = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
        ob_start(); ?>
        <section class="sc-cpri" data-sc-connected-public-research data-api-base="<?php echo esc_attr( $base ); ?>">
            <header class="sc-cpri__hero"><div><p><?php esc_html_e( 'Public research infrastructure · explicit provenance', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( $atts['title'] ); ?></h3><span><?php esc_html_e( 'Move from a public Library record into its declared topics, concepts, entities, sources, claims, pathways, publication graph, and published federation context without exposing private research.', 'sustainable-catalyst-library' ); ?></span></div><aside><strong><?php esc_html_e( 'Connected does not mean inferred', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'Only explicit public relationships are projected, one hop at a time. No private project graph is promoted into this surface.', 'sustainable-catalyst-library' ); ?></span></aside></header>
            <div class="sc-cpri__metrics" aria-label="<?php esc_attr_e( 'Public infrastructure guarantees', 'sustainable-catalyst-library' ); ?>"><article><strong>GET</strong><span><?php esc_html_e( 'Read only', 'sustainable-catalyst-library' ); ?></span></article><article><strong>1</strong><span><?php esc_html_e( 'Hop maximum', 'sustainable-catalyst-library' ); ?></span></article><article><strong><?php echo esc_html( (string) self::MAX_CONNECTIONS ); ?></strong><span><?php esc_html_e( 'Connection cap', 'sustainable-catalyst-library' ); ?></span></article><article><strong>SHA-256</strong><span><?php esc_html_e( 'Context manifests', 'sustainable-catalyst-library' ); ?></span></article></div>
            <div class="sc-cpri__index" data-sc-cpri-index aria-live="polite" aria-busy="true"><p><?php esc_html_e( 'Loading recent public research records…', 'sustainable-catalyst-library' ); ?></p></div>
            <div class="sc-cpri__links"><a href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Infrastructure contract', 'sustainable-catalyst-library' ); ?></a><a href="<?php echo esc_url( $base . '/index' ); ?>"><?php esc_html_e( 'Public research index', 'sustainable-catalyst-library' ); ?></a><a href="<?php echo esc_url( $base . '/federation-manifests' ); ?>"><?php esc_html_e( 'Published federation manifests', 'sustainable-catalyst-library' ); ?></a></div>
            <p class="sc-cpri__boundary"><?php esc_html_e( 'Private Projects, My Library, notebook and matrix bodies, Research Room membership, Team Library membership, private federation governance, credentials, and Workspace state are outside this public infrastructure.', 'sustainable-catalyst-library' ); ?></p>
        </section><?php return (string) ob_get_clean();
    }
}
