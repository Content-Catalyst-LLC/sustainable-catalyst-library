<?php
/**
 * Public Evidence & Claim Navigation — v5.3.0.
 *
 * Read-only navigation over canonical public Research Claims, public Evidence
 * Notes, explicitly linked Publication research graphs, and public Research
 * Sources. Private Evidence Matrices, notebooks, review notes, project context,
 * and non-public evidence remain outside this facade.
 *
 * @package Sustainable_Catalyst_Library
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Public_Evidence_Claim_Navigation {
    public const VERSION = '5.3.0';
    public const SCHEMA = 'sc-library-public-evidence-claim-navigation/1.0';
    public const CLAIM_SCHEMA = 'sc-library-public-claim-context/1.0';
    public const EVIDENCE_SCHEMA = 'sc-library-public-evidence-context/1.0';
    public const PUBLICATION_SCHEMA = 'sc-library-publication-evidence-context/1.0';
    public const SOURCE_SCHEMA = 'sc-library-source-evidence-context/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/public-evidence';
    public const MAX_RECENT = 24;
    public const MAX_EVIDENCE_PER_CLAIM = 80;
    public const MAX_CLAIMS_PER_CONTEXT = 40;
    public const MAX_PUBLICATION_SCAN = 120;
    public const MAX_SOURCE_EVIDENCE = 120;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_public_evidence_claim_navigation', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_api_public_object_payload', array( $this, 'filter_public_object_payload' ), 30, 3 );
        add_filter( 'rest_post_dispatch', array( $this, 'cors_headers' ), 35, 3 );
    }

    public static function contract() {
        return array(
            'schema' => self::SCHEMA,
            'canonical_evidence_claim_store_reused' => true,
            'publication_research_graph_reused' => true,
            'connected_public_research_reused' => true,
            'citation_source_authority_reused' => true,
            'creates_parallel_claim_store' => false,
            'creates_parallel_evidence_store' => false,
            'public_claims_only' => true,
            'public_evidence_notes_only' => true,
            'explicit_publication_links_only' => true,
            'private_evidence_matrix_bodies_excluded' => true,
            'private_notebook_bodies_excluded' => true,
            'private_project_context_excluded' => true,
            'private_review_notes_excluded' => true,
            'private_relation_notes_excluded' => true,
            'full_private_evidence_body_exposed' => false,
            'relation_semantics_preserved' => true,
            'claim_status_descriptive_only' => true,
            'confidence_descriptive_only' => true,
            'truth_scoring' => false,
            'automatic_claim_generation' => false,
            'automatic_evidence_promotion' => false,
            'automatic_claim_status_change' => false,
            'automatic_confidence_change' => false,
            'automatic_publication' => false,
            'automatic_workspace_write' => false,
            'public_get_only' => true,
            'cors_credentials_allowed' => false,
        );
    }

    public static function relation_registry() {
        return array(
            'supports' => array( 'label' => 'Supports', 'meaning' => 'The public evidence was explicitly linked as supporting the claim.' ),
            'qualifies' => array( 'label' => 'Qualifies', 'meaning' => 'The public evidence narrows, conditions, or limits the claim.' ),
            'contradicts' => array( 'label' => 'Contradicts', 'meaning' => 'The public evidence was explicitly linked as contradicting the claim.' ),
            'contextualizes' => array( 'label' => 'Contextualizes', 'meaning' => 'The public evidence supplies context without being treated as direct support.' ),
            'illustrates' => array( 'label' => 'Illustrates', 'meaning' => 'The public evidence illustrates the claim or its context.' ),
            'unresolved' => array( 'label' => 'Unresolved', 'meaning' => 'The relationship remains explicitly unresolved.' ),
        );
    }

    public static function capabilities() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'index_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/index' ) ),
            'claim_template' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/claim/{id}' ) ),
            'evidence_template' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/evidence/{id}' ) ),
            'publication_template' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/publication/{id}' ) ),
            'source_template' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/source/{id}' ) ),
            'relations' => self::relation_registry(),
            'boundaries' => self::contract(),
        );
    }

    private static function clean( $value, $limit = 600 ) {
        $value = trim( wp_strip_all_tags( (string) $value ) );
        $value = preg_replace( '/\s+/u', ' ', $value );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function id_list( $value ) {
        $values = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
        return array_values( array_unique( array_filter( array_map( 'absint', (array) $values ) ) ) );
    }

    private static function navigation_url( array $args ) {
        $base = class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' );
        return esc_url_raw( add_query_arg( $args, $base ) . '#public-evidence-claims' );
    }

    public static function relation_totals( array $items ) {
        $totals = array_fill_keys( array_keys( self::relation_registry() ), 0 );
        foreach ( $items as $item ) {
            $relation = sanitize_key( (string) ( $item['relation'] ?? 'unresolved' ) );
            if ( ! isset( $totals[ $relation ] ) ) { $relation = 'unresolved'; }
            $totals[ $relation ]++;
        }
        return $totals;
    }

    private static function public_claim_record( $claim_id ) {
        $claim_id = absint( $claim_id );
        if ( ! $claim_id || ! class_exists( 'SC_Library_Evidence_Claim_Linking' ) || ! SC_Library_Evidence_Claim_Linking::claim_is_public( $claim_id ) ) { return array(); }
        $data = SC_Library_Evidence_Claim_Linking::get_claim_data( $claim_id, false );
        if ( ! is_array( $data ) || ! $data ) { return array(); }
        return array(
            'id' => $claim_id,
            'canonical_id' => 'urn:sc:research-claim:' . $claim_id,
            'title' => self::clean( $data['title'] ?? get_the_title( $claim_id ), 220 ),
            'statement' => self::clean( $data['statement'] ?? '', 1400 ),
            'summary' => self::clean( $data['summary'] ?? '', 700 ),
            'claim_type' => sanitize_key( (string) ( $data['claim_type'] ?? '' ) ),
            'claim_type_label' => self::clean( $data['claim_type_label'] ?? '', 120 ),
            'declared_status' => sanitize_key( (string) ( $data['status'] ?? '' ) ),
            'declared_confidence' => absint( $data['confidence'] ?? 0 ),
            'scope' => self::clean( $data['scope'] ?? '', 700 ),
            'assumptions' => self::clean( $data['assumptions'] ?? '', 700 ),
            'limitations' => self::clean( $data['limitations'] ?? '', 700 ),
            'counterclaim' => self::clean( $data['counterclaim'] ?? '', 700 ),
            'modified_at' => self::clean( $data['modified_gmt'] ?? '', 80 ),
            'public' => true,
            'status_and_confidence_are_not_truth_scores' => true,
            'navigation_url' => self::navigation_url( array( 'claim_id' => $claim_id ) ),
        );
    }

    private static function public_evidence_record( $note_id, $relation = '' ) {
        $note_id = absint( $note_id );
        if ( ! $note_id || ! class_exists( 'SC_Library_Evidence_Claim_Linking' ) || ! SC_Library_Evidence_Claim_Linking::evidence_is_public( $note_id ) ) { return array(); }
        $data = SC_Library_Evidence_Claim_Linking::get_evidence_data( $note_id, false );
        if ( ! is_array( $data ) || ! $data ) { return array(); }
        $excerpt = (string) ( $data['summary'] ?? '' );
        if ( '' === trim( $excerpt ) ) { $excerpt = (string) ( $data['content'] ?? '' ); }
        $registry = self::relation_registry();
        $relation = sanitize_key( (string) $relation );
        if ( $relation && ! isset( $registry[ $relation ] ) ) { $relation = 'unresolved'; }
        return array(
            'id' => $note_id,
            'canonical_id' => 'urn:sc:evidence-note:' . $note_id,
            'title' => self::clean( $data['title'] ?? get_the_title( $note_id ), 220 ),
            'excerpt' => self::clean( $excerpt, 700 ),
            'evidence_type' => sanitize_key( (string) ( $data['evidence_type'] ?? '' ) ),
            'evidence_type_label' => self::clean( $data['evidence_type_label'] ?? '', 120 ),
            'relation' => $relation,
            'relation_label' => $relation && isset( $registry[ $relation ] ) ? $registry[ $relation ]['label'] : '',
            'source' => array(
                'id' => absint( $data['source_id'] ?? 0 ),
                'title' => self::clean( $data['source_title'] ?? '', 240 ),
                'url' => esc_url_raw( (string) ( $data['source_url'] ?? '' ) ),
                'citation' => self::clean( $data['source_citation'] ?? '', 800 ),
            ),
            'document' => array(
                'id' => absint( $data['document_id'] ?? 0 ),
                'title' => self::clean( $data['document_title'] ?? '', 240 ),
                'url' => esc_url_raw( (string) ( $data['document_url'] ?? '' ) ),
            ),
            'locator' => array(
                'type' => sanitize_key( (string) ( $data['locator_type'] ?? '' ) ),
                'start' => self::clean( $data['locator_start'] ?? '', 80 ),
                'end' => self::clean( $data['locator_end'] ?? '', 80 ),
                'label' => self::clean( $data['locator_label'] ?? '', 180 ),
                'verified' => ! empty( $data['locator_verified'] ),
            ),
            'review_status' => sanitize_key( (string) ( $data['review_status'] ?? '' ) ),
            'quote_verified' => ! empty( $data['quote_verified'] ),
            'declared_confidence' => absint( $data['confidence'] ?? 0 ),
            'modified_at' => self::clean( $data['modified_gmt'] ?? '', 80 ),
            'public' => true,
            'full_evidence_body_exposed' => false,
            'private_analysis_exposed' => false,
            'private_relation_note_exposed' => false,
            'navigation_url' => self::navigation_url( array( 'evidence_id' => $note_id ) ),
        );
    }

    private static function public_source_record( $source_id ) {
        $source_id = absint( $source_id );
        if ( ! $source_id || ! class_exists( 'SC_Library_Citation_Source_Manager' ) ) { return array(); }
        $data = SC_Library_Citation_Source_Manager::get_source_data( $source_id, false );
        if ( ! is_array( $data ) || ! $data ) { return array(); }
        return array(
            'id' => $source_id,
            'title' => self::clean( $data['title'] ?? get_the_title( $source_id ), 240 ),
            'url' => esc_url_raw( (string) ( $data['url'] ?? get_permalink( $source_id ) ) ),
            'source_type' => sanitize_key( (string) ( $data['source_type'] ?? '' ) ),
            'navigation_url' => self::navigation_url( array( 'source_id' => $source_id ) ),
        );
    }

    private static function publications_for_claim( $claim_id ) {
        if ( ! class_exists( 'SC_Library_Publications_Research_Graph' ) ) { return array(); }
        $posts = get_posts( array(
            'post_type' => SC_Library_Publications_Research_Graph::publication_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_PUBLICATION_SCAN,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        $out = array();
        foreach ( (array) $posts as $post_id ) {
            $graph = SC_Library_Publications_Research_Graph::build_graph( absint( $post_id ) );
            if ( ! is_array( $graph ) || ! $graph ) { continue; }
            $matched = false;
            foreach ( (array) ( $graph['claims'] ?? array() ) as $claim ) {
                if ( absint( $claim['id'] ?? 0 ) === absint( $claim_id ) ) { $matched = true; break; }
            }
            if ( ! $matched ) { continue; }
            $out[] = array(
                'id' => absint( $post_id ),
                'title' => self::clean( $graph['publication']['title'] ?? get_the_title( $post_id ), 240 ),
                'url' => esc_url_raw( (string) ( $graph['publication']['url'] ?? get_permalink( $post_id ) ) ),
                'research_graph_url' => SC_Library_Publications_Research_Graph::graph_url_for_post( absint( $post_id ) ),
                'evidence_navigation_url' => self::navigation_url( array( 'publication_id' => absint( $post_id ) ) ),
                'provenance' => 'explicit-publication-research-graph',
            );
            if ( count( $out ) >= self::MAX_CLAIMS_PER_CONTEXT ) { break; }
        }
        return $out;
    }

    public static function claim_context( $claim_id ) {
        $claim_id = absint( $claim_id );
        $claim = self::public_claim_record( $claim_id );
        if ( ! $claim ) { return new WP_Error( 'sc_public_claim_unavailable', __( 'That claim is not available on the public evidence surface.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $packet = SC_Library_Evidence_Claim_Linking::claim_packet( $claim_id, false );
        $evidence = array(); $sources = array();
        foreach ( array_slice( (array) ( $packet['links'] ?? array() ), 0, self::MAX_EVIDENCE_PER_CLAIM ) as $item ) {
            $relation = sanitize_key( (string) ( $item['link']['relation'] ?? 'unresolved' ) );
            $note_id = absint( $item['evidence']['id'] ?? 0 );
            $record = self::public_evidence_record( $note_id, $relation );
            if ( ! $record ) { continue; }
            $evidence[] = $record;
            $source_id = absint( $record['source']['id'] ?? 0 );
            if ( $source_id && ! isset( $sources[ $source_id ] ) ) { $sources[ $source_id ] = self::public_source_record( $source_id ); }
        }
        $payload = array(
            'schema' => self::CLAIM_SCHEMA,
            'version' => self::VERSION,
            'claim' => $claim,
            'evidence' => $evidence,
            'evidence_count' => count( $evidence ),
            'relation_totals' => self::relation_totals( $evidence ),
            'sources' => array_values( array_filter( $sources ) ),
            'publications' => self::publications_for_claim( $claim_id ),
            'boundaries' => array(
                'public_claim_only' => true,
                'public_evidence_only' => true,
                'private_matrix_content_included' => false,
                'private_review_notes_included' => false,
                'truth_scoring_performed' => false,
                'status_or_confidence_mutated' => false,
            ),
        );
        $checksum = $payload; $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $checksum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return $payload;
    }

    public static function evidence_context( $note_id ) {
        $note_id = absint( $note_id );
        $evidence = self::public_evidence_record( $note_id );
        if ( ! $evidence ) { return new WP_Error( 'sc_public_evidence_unavailable', __( 'That evidence note is not available on the public evidence surface.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $data = SC_Library_Evidence_Claim_Linking::get_evidence_data( $note_id, false );
        $claims = array();
        foreach ( array_slice( (array) ( $data['claim_links'] ?? array() ), 0, self::MAX_CLAIMS_PER_CONTEXT ) as $link ) {
            $claim_id = absint( $link['claim_id'] ?? 0 );
            $claim = self::public_claim_record( $claim_id );
            if ( ! $claim ) { continue; }
            $relation = sanitize_key( (string) ( $link['relation'] ?? 'unresolved' ) );
            if ( ! isset( self::relation_registry()[ $relation ] ) ) { $relation = 'unresolved'; }
            $claims[] = array(
                'claim' => $claim,
                'relation' => $relation,
                'relation_label' => self::relation_registry()[ $relation ]['label'],
                'provenance' => 'explicit-canonical-claim-evidence-link',
            );
        }
        $payload = array(
            'schema' => self::EVIDENCE_SCHEMA,
            'version' => self::VERSION,
            'evidence' => $evidence,
            'claims' => $claims,
            'claim_count' => count( $claims ),
            'source' => self::public_source_record( absint( $evidence['source']['id'] ?? 0 ) ),
            'boundaries' => array( 'public_only' => true, 'relation_notes_exposed' => false, 'private_analysis_exposed' => false, 'truth_scoring_performed' => false ),
        );
        $checksum = $payload; $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $checksum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return $payload;
    }

    public static function publication_context( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! class_exists( 'SC_Library_Publications_Research_Graph' ) ) { return new WP_Error( 'sc_publication_evidence_unavailable', __( 'Publication evidence context is unavailable.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $graph = SC_Library_Publications_Research_Graph::build_graph( $post_id );
        if ( ! is_array( $graph ) || ! $graph ) { return new WP_Error( 'sc_publication_evidence_unavailable', __( 'That publication has no public research graph.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $claims = array();
        foreach ( array_slice( (array) ( $graph['claims'] ?? array() ), 0, self::MAX_CLAIMS_PER_CONTEXT ) as $item ) {
            $claim_id = absint( $item['id'] ?? 0 );
            $claim = self::public_claim_record( $claim_id );
            if ( ! $claim ) { continue; }
            $packet = SC_Library_Evidence_Claim_Linking::claim_packet( $claim_id, false );
            $relations = array();
            foreach ( array_slice( (array) ( $packet['links'] ?? array() ), 0, self::MAX_EVIDENCE_PER_CLAIM ) as $link ) { $relations[] = array( 'relation' => sanitize_key( (string) ( $link['link']['relation'] ?? 'unresolved' ) ) ); }
            $claims[] = array(
                'claim' => $claim,
                'evidence_count' => count( $relations ),
                'relation_totals' => self::relation_totals( $relations ),
                'provenance' => 'explicit-publication-research-graph',
            );
        }
        $payload = array(
            'schema' => self::PUBLICATION_SCHEMA,
            'version' => self::VERSION,
            'publication' => (array) ( $graph['publication'] ?? array() ),
            'claims' => $claims,
            'claim_count' => count( $claims ),
            'declared_sources' => array_slice( (array) ( $graph['sources'] ?? array() ), 0, self::MAX_SOURCE_EVIDENCE ),
            'research_graph_url' => SC_Library_Publications_Research_Graph::graph_url_for_post( $post_id ),
            'explicit_publication_links_only' => true,
            'private_research_excluded' => true,
            'truth_scoring_performed' => false,
        );
        $checksum = $payload; $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $checksum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return $payload;
    }

    public static function source_context( $source_id ) {
        $source_id = absint( $source_id );
        $source = self::public_source_record( $source_id );
        if ( ! $source ) { return new WP_Error( 'sc_source_evidence_unavailable', __( 'That source is not available on the public evidence surface.', 'sustainable-catalyst-library' ), array( 'status' => 404 ) ); }
        $ids = SC_Library_Evidence_Claim_Linking::evidence_ids_for_source( $source_id, false );
        $evidence = array(); $claims = array();
        foreach ( array_slice( (array) $ids, 0, self::MAX_SOURCE_EVIDENCE ) as $note_id ) {
            $record = self::public_evidence_record( $note_id );
            if ( ! $record ) { continue; }
            $evidence[] = $record;
            $data = SC_Library_Evidence_Claim_Linking::get_evidence_data( absint( $note_id ), false );
            foreach ( (array) ( $data['claim_links'] ?? array() ) as $link ) {
                $claim_id = absint( $link['claim_id'] ?? 0 );
                if ( $claim_id && ! isset( $claims[ $claim_id ] ) ) {
                    $claim = self::public_claim_record( $claim_id );
                    if ( $claim ) { $claims[ $claim_id ] = $claim; }
                }
            }
        }
        $payload = array(
            'schema' => self::SOURCE_SCHEMA,
            'version' => self::VERSION,
            'source' => $source,
            'evidence' => $evidence,
            'evidence_count' => count( $evidence ),
            'claims' => array_slice( array_values( $claims ), 0, self::MAX_CLAIMS_PER_CONTEXT ),
            'public_only' => true,
            'truth_scoring_performed' => false,
        );
        $checksum = $payload; $payload['manifest_sha256'] = hash( 'sha256', wp_json_encode( $checksum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        return $payload;
    }

    public static function index_payload() {
        if ( ! class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) { return array( 'schema' => self::SCHEMA, 'version' => self::VERSION, 'claims' => array(), 'evidence' => array() ); }
        $claim_ids = get_posts( array(
            'post_type' => SC_Library_Evidence_Claim_Linking::CLAIM_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_RECENT,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'modified',
            'order' => 'DESC',
        ) );
        $evidence_ids = get_posts( array(
            'post_type' => SC_Library_Evidence_Claim_Linking::NOTE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_RECENT,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'modified',
            'order' => 'DESC',
        ) );
        $claims = array_values( array_filter( array_map( array( __CLASS__, 'public_claim_record' ), (array) $claim_ids ) ) );
        $evidence = array_values( array_filter( array_map( array( __CLASS__, 'public_evidence_record' ), (array) $evidence_ids ) ) );
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'claims' => array_slice( $claims, 0, self::MAX_RECENT ),
            'evidence' => array_slice( $evidence, 0, self::MAX_RECENT ),
            'counts' => array( 'claims' => count( $claims ), 'evidence' => count( $evidence ) ),
            'public_only' => true,
            'private_matrix_content_included' => false,
        );
    }

    public function filter_public_object_payload( $payload, $type, $post ) {
        if ( ! is_array( $payload ) || ! $post instanceof WP_Post ) { return $payload; }
        if ( 'publication' === $type && class_exists( 'SC_Library_Publications_Research_Graph' ) ) {
            $graph = SC_Library_Publications_Research_Graph::build_graph( absint( $post->ID ) );
            $count = is_array( $graph ) ? count( (array) ( $graph['claims'] ?? array() ) ) : 0;
            if ( $count > 0 ) {
                $payload['public_evidence'] = array(
                    'claim_count' => $count,
                    'navigation_url' => self::navigation_url( array( 'publication_id' => absint( $post->ID ) ) ),
                    'api_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/publication/' . absint( $post->ID ) ) ),
                );
            }
        }
        if ( 'research-source' === $type && class_exists( 'SC_Library_Evidence_Claim_Linking' ) ) {
            $ids = SC_Library_Evidence_Claim_Linking::evidence_ids_for_source( absint( $post->ID ), false );
            if ( $ids ) {
                $payload['public_evidence'] = array(
                    'evidence_count' => min( self::MAX_SOURCE_EVIDENCE, count( $ids ) ),
                    'navigation_url' => self::navigation_url( array( 'source_id' => absint( $post->ID ) ) ),
                    'api_url' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/source/' . absint( $post->ID ) ) ),
                );
            }
        }
        return $payload;
    }

    public function register_assets() {
        wp_register_style( 'sc-library-public-evidence-v530', SC_LIBRARY_URL . 'assets/css/sc-library-public-evidence-v530.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-public-evidence-v530', SC_LIBRARY_URL . 'assets/js/sc-library-public-evidence-v530.js', array(), SC_LIBRARY_VERSION, true );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_capabilities' ) ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/index', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => array( $this, 'rest_index' ) ) );
        foreach ( array( 'claim', 'evidence', 'publication', 'source' ) as $kind ) {
            register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/' . $kind . '/(?P<id>\d+)', array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array( $this, 'rest_' . $kind ),
                'args' => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
            ) );
        }
    }

    public function rest_capabilities() { return rest_ensure_response( self::capabilities() ); }
    public function rest_index() { return rest_ensure_response( self::index_payload() ); }
    public function rest_claim( WP_REST_Request $request ) { $data = self::claim_context( absint( $request['id'] ) ); return is_wp_error( $data ) ? $data : rest_ensure_response( $data ); }
    public function rest_evidence( WP_REST_Request $request ) { $data = self::evidence_context( absint( $request['id'] ) ); return is_wp_error( $data ) ? $data : rest_ensure_response( $data ); }
    public function rest_publication( WP_REST_Request $request ) { $data = self::publication_context( absint( $request['id'] ) ); return is_wp_error( $data ) ? $data : rest_ensure_response( $data ); }
    public function rest_source( WP_REST_Request $request ) { $data = self::source_context( absint( $request['id'] ) ); return is_wp_error( $data ) ? $data : rest_ensure_response( $data ); }

    public function cors_headers( $response, $server, $request ) {
        $route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
        if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE . self::REST_ROUTE ) || 'GET' !== strtoupper( (string) $request->get_method() ) ) { return $response; }
        if ( ! class_exists( 'SC_Library_API_Embeds_Interoperability' ) ) { return $response; }
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? untrailingslashit( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) : '';
        if ( ! $origin || ! in_array( $origin, SC_Library_API_Embeds_Interoperability::allowed_origins(), true ) ) { return $response; }
        if ( method_exists( $response, 'header' ) ) {
            $response->header( 'Access-Control-Allow-Origin', $origin );
            $response->header( 'Access-Control-Allow-Methods', 'GET' );
            $response->header( 'Access-Control-Allow-Credentials', 'false' );
            $response->header( 'Vary', 'Origin' );
        }
        return $response;
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Public Evidence & Claim Navigation', 'sustainable-catalyst-library' ) ), $atts, 'sc_public_evidence_claim_navigation' );
        wp_enqueue_style( 'sc-library-public-evidence-v530' );
        wp_enqueue_script( 'sc-library-public-evidence-v530' );
        $base = esc_url( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) );
        ob_start(); ?>
        <section class="sc-public-evidence" data-sc-public-evidence data-rest-base="<?php echo $base; ?>">
            <header class="sc-public-evidence__header">
                <p class="sc-public-evidence__kicker"><?php esc_html_e( 'Public research traceability', 'sustainable-catalyst-library' ); ?></p>
                <h3><?php echo esc_html( (string) $atts['title'] ); ?></h3>
                <p><?php esc_html_e( 'Navigate public claims into the public evidence notes and research sources explicitly linked to them. Relation labels preserve the canonical research record; they are not truth scores or automated conclusions.', 'sustainable-catalyst-library' ); ?></p>
            </header>
            <div class="sc-public-evidence__boundary" role="note">
                <strong><?php esc_html_e( 'Public evidence only', 'sustainable-catalyst-library' ); ?></strong>
                <span><?php esc_html_e( 'Private Evidence Matrices, notebooks, project context, review notes, relation notes, and non-public evidence remain excluded.', 'sustainable-catalyst-library' ); ?></span>
            </div>
            <form class="sc-public-evidence__form" role="search" data-sc-public-evidence-form>
                <label><span><?php esc_html_e( 'Open by record type', 'sustainable-catalyst-library' ); ?></span><select data-sc-public-evidence-kind><option value="claim"><?php esc_html_e( 'Public claim', 'sustainable-catalyst-library' ); ?></option><option value="evidence"><?php esc_html_e( 'Public evidence note', 'sustainable-catalyst-library' ); ?></option><option value="publication"><?php esc_html_e( 'Publication', 'sustainable-catalyst-library' ); ?></option><option value="source"><?php esc_html_e( 'Research source', 'sustainable-catalyst-library' ); ?></option></select></label>
                <label><span><?php esc_html_e( 'Record ID', 'sustainable-catalyst-library' ); ?></span><input type="number" min="1" step="1" inputmode="numeric" data-sc-public-evidence-id required></label>
                <button type="submit"><?php esc_html_e( 'Open evidence context', 'sustainable-catalyst-library' ); ?></button>
            </form>
            <p class="sc-public-evidence__status" aria-live="polite" data-sc-public-evidence-status><?php esc_html_e( 'Loading recent public evidence…', 'sustainable-catalyst-library' ); ?></p>
            <div class="sc-public-evidence__results" data-sc-public-evidence-results></div>
            <details class="sc-public-evidence__relations"><summary><?php esc_html_e( 'How relation labels should be read', 'sustainable-catalyst-library' ); ?></summary><ul><?php foreach ( self::relation_registry() as $relation ) : ?><li><strong><?php echo esc_html( $relation['label'] ); ?>:</strong> <?php echo esc_html( $relation['meaning'] ); ?></li><?php endforeach; ?></ul><p><?php esc_html_e( 'These labels describe explicit research relationships. They do not establish truth, certainty, consensus, or institutional endorsement.', 'sustainable-catalyst-library' ); ?></p></details>
            <noscript><p><?php esc_html_e( 'JavaScript is required for the interactive public evidence navigator. The read-only REST endpoints remain available directly.', 'sustainable-catalyst-library' ); ?></p></noscript>
        </section>
        <?php return ob_get_clean();
    }
}
