<?php
/**
 * Knowledge Graph & Evidence Intelligence — v4.5.0.
 *
 * Builds a bounded, rebuildable projection of an authenticated user's explicit
 * research relationships. Canonical Research Projects, Source Bundles, Reading
 * Notebooks, Evidence Matrices, and Open Learning routes remain authoritative.
 * The projection never infers semantic relationships from private text and never
 * changes claim status, confidence, publication state, or Workspace state.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Knowledge_Graph_Evidence_Intelligence {
    public const VERSION = '4.5.0';
    public const SCHEMA = 'sc-library-knowledge-graph-evidence-intelligence/1.0';
    public const GRAPH_SCHEMA = 'sc-library-private-research-graph/1.0';
    public const DIAGNOSTIC_SCHEMA = 'sc-library-project-evidence-intelligence/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const REST_ROUTE = '/knowledge-graph-evidence';
    public const MAX_NODES = 240;
    public const MAX_EDGES = 600;
    public const MAX_RECENT_PROJECTS = 40;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'sc_knowledge_graph_evidence_intelligence', array( $this, 'shortcode' ) );
        add_filter( 'sc_library_knowledge_graph_evidence_state', array( $this, 'filter_state' ), 10, 3 );
    }

    public function register_assets() {
        wp_register_style( 'sc-library-knowledge-graph-evidence-v450', SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-graph-evidence-v450.css', array(), SC_LIBRARY_VERSION );
        wp_register_script( 'sc-library-knowledge-graph-evidence-v450', SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-graph-evidence-v450.js', array(), SC_LIBRARY_VERSION, true );
    }

    public static function contract() {
        return array(
            'schema'                              => self::SCHEMA,
            'visibility'                          => 'private-account-projection',
            'record_owner'                        => 'current-wordpress-user',
            'same_library_workspace_account'      => true,
            'canonical_stores_unchanged'          => true,
            'graph_projection_rebuildable'        => true,
            'new_private_record_store'            => false,
            'explicit_relationships_only'         => true,
            'machine_inferred_relationships'      => false,
            'automatic_entity_resolution'         => false,
            'automatic_claim_generation'          => false,
            'automatic_claim_status_change'       => false,
            'automatic_confidence_scoring'        => false,
            'truth_scoring'                       => false,
            'diagnostics_descriptive_only'        => true,
            'diagnostics_are_not_conclusions'     => true,
            'public_private_graph_boundary'       => 'preserved',
            'private_context_remote_synthesis'    => false,
            'automatic_project_write'             => false,
            'automatic_notebook_write'            => false,
            'automatic_evidence_promotion'        => false,
            'automatic_publication'               => false,
            'automatic_workspace_write'           => false,
        );
    }

    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE . '/catalog', array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => array( $this, 'rest_signed_in' ),
            'callback'            => array( $this, 'rest_catalog' ),
        ) );
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => array( $this, 'rest_signed_in' ),
            'callback'            => array( $this, 'rest_graph' ),
            'args'                => array( 'project_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ),
        ) );
    }

    public function rest_signed_in() { return is_user_logged_in(); }
    public function rest_catalog() { return rest_ensure_response( self::project_catalog( get_current_user_id() ) ); }
    public function rest_graph( WP_REST_Request $request ) {
        $state = self::project_graph( get_current_user_id(), absint( $request->get_param( 'project_id' ) ) );
        return is_wp_error( $state ) ? $state : rest_ensure_response( $state );
    }

    private static function clean( $value, $limit = 220 ) {
        $value = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function clean_area( $value, $limit = 800 ) {
        $value = trim( sanitize_textarea_field( (string) $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
    }

    private static function node_id( $kind, $id ) { return sanitize_key( (string) $kind ) . ':' . substr( hash( 'sha256', (string) $id ), 0, 20 ); }

    private static function add_node( array &$nodes, array &$node_index, array $node ) {
        $id = self::clean( $node['id'] ?? '', 100 );
        if ( '' === $id || isset( $node_index[ $id ] ) || count( $nodes ) >= self::MAX_NODES ) { return $id; }
        $node['id'] = $id;
        $node['type'] = sanitize_key( (string) ( $node['type'] ?? 'record' ) );
        $node['label'] = self::clean( $node['label'] ?? $id, 180 );
        $node['title'] = self::clean( $node['title'] ?? $node['label'], 220 );
        $node['urn'] = self::clean( $node['urn'] ?? '', 320 );
        $node['url'] = esc_url_raw( (string) ( $node['url'] ?? '' ) );
        $node['summary'] = self::clean_area( $node['summary'] ?? '', 500 );
        $node['visibility'] = self::clean( $node['visibility'] ?? 'private', 40 );
        $node_index[ $id ] = count( $nodes );
        $nodes[] = $node;
        return $id;
    }

    private static function add_edge( array &$edges, array &$edge_index, array $edge ) {
        if ( count( $edges ) >= self::MAX_EDGES ) { return; }
        $source = self::clean( $edge['source'] ?? '', 100 );
        $target = self::clean( $edge['target'] ?? '', 100 );
        $relation = sanitize_key( (string) ( $edge['relation'] ?? 'related_to' ) );
        if ( '' === $source || '' === $target || $source === $target ) { return; }
        $key = $source . '|' . $relation . '|' . $target . '|' . self::clean( $edge['record_id'] ?? '', 100 );
        if ( isset( $edge_index[ $key ] ) ) { return; }
        $edge_index[ $key ] = true;
        $edges[] = array(
            'source'     => $source,
            'target'     => $target,
            'relation'   => $relation,
            'label'      => self::clean( $edge['label'] ?? str_replace( '_', ' ', $relation ), 120 ),
            'provenance' => self::clean( $edge['provenance'] ?? 'explicit-record-link', 80 ),
            'record_id'  => self::clean( $edge['record_id'] ?? '', 100 ),
        );
    }

    private static function project_ids( $user_id ) {
        return class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' )
            ? array_slice( SC_Library_Unified_Research_Projects_Source_Bundles::projects_for_user( absint( $user_id ) ), 0, self::MAX_RECENT_PROJECTS )
            : array();
    }

    public static function project_catalog( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_graph_signin', __( 'Sign in to open your private research graph.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        $projects = array();
        foreach ( self::project_ids( $user_id ) as $project_id ) {
            $state = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
            if ( is_wp_error( $state ) ) { continue; }
            $projects[] = array(
                'project_id'      => absint( $project_id ),
                'title'           => self::clean( $state['title'] ?? sprintf( __( 'Project %d', 'sustainable-catalyst-library' ), $project_id ) ),
                'status'          => sanitize_key( (string) ( $state['status'] ?? 'active' ) ),
                'reference_count' => absint( $state['reference_count'] ?? 0 ),
                'bundle_count'    => absint( $state['bundle_count'] ?? 0 ),
                'urn'             => (string) ( $state['project_identity']['urn'] ?? '' ),
            );
        }
        return array( 'schema' => 'sc-library-knowledge-graph-evidence-catalog/1.0', 'version' => self::VERSION, 'visibility' => 'private', 'projects' => $projects, 'project_count' => count( $projects ), 'contract' => self::contract() );
    }

    private static function source_node( array &$nodes, array &$node_index, $source_key, $label, $url = '', $visibility = 'private-reference' ) {
        $source_key = self::clean( $source_key ?: ( $url ?: $label ), 420 );
        $id = self::node_id( 'source', $source_key );
        self::add_node( $nodes, $node_index, array( 'id' => $id, 'type' => 'source', 'label' => $label ?: __( 'Research source', 'sustainable-catalyst-library' ), 'title' => $label ?: __( 'Research source', 'sustainable-catalyst-library' ), 'url' => $url, 'visibility' => $visibility, 'source_key' => $source_key ) );
        return $id;
    }

    private static function project_notebooks( $user_id, $project_id ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Reading_Notebook_Annotations' ) ) { return $out; }
        foreach ( SC_Library_Reading_Notebook_Annotations::notebooks_for_user( $user_id ) as $notebook_id ) {
            $state = SC_Library_Reading_Notebook_Annotations::notebook_state( $notebook_id, $user_id );
            if ( is_wp_error( $state ) || absint( $state['project_context']['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
            $out[] = $state;
        }
        return $out;
    }

    private static function project_matrices( $user_id, $project_id ) {
        $out = array();
        if ( ! class_exists( 'SC_Library_Evidence_Matrix_Claim_Intelligence' ) ) { return $out; }
        foreach ( SC_Library_Evidence_Matrix_Claim_Intelligence::matrices_for_user( $user_id ) as $matrix_id ) {
            $state = SC_Library_Evidence_Matrix_Claim_Intelligence::matrix_state( $matrix_id, $user_id );
            if ( is_wp_error( $state ) || absint( $state['project_context']['project_id'] ?? 0 ) !== absint( $project_id ) ) { continue; }
            $out[] = $state;
        }
        return $out;
    }

    private static function project_learning_routes( $user_id, $project_id ) {
        if ( ! class_exists( 'SC_Library_Open_Learning_II' ) ) { return array(); }
        $routes = get_user_meta( absint( $user_id ), SC_Library_Open_Learning_II::USER_META, true );
        $routes = is_array( $routes ) ? array_values( array_filter( $routes, 'is_array' ) ) : array();
        return array_values( array_filter( $routes, static fn( $route ) => absint( $route['project_id'] ?? 0 ) === absint( $project_id ) ) );
    }

    private static function evidence_intelligence( array $matrices ) {
        $relation_totals = array( 'supports' => 0, 'qualifies' => 0, 'contradicts' => 0, 'contextualizes' => 0, 'unresolved' => 0 );
        $patterns = array( 'no-evidence' => 0, 'support-only' => 0, 'mixed-record' => 0, 'contradiction-heavy' => 0, 'context-or-unresolved-only' => 0 );
        $gaps = array(); $claims = 0; $with_evidence = 0; $with_counterevidence = 0; $unresolved = 0; $checked = 0; $links = 0; $source_keys = array();
        foreach ( $matrices as $matrix ) {
            foreach ( (array) ( $matrix['claims'] ?? array() ) as $claim ) {
                $claims++;
                $diag = (array) ( $matrix['diagnostics'][ $claim['id'] ?? '' ] ?? array() );
                $count = absint( $diag['evidence_count'] ?? 0 );
                if ( $count > 0 ) { $with_evidence++; }
                $links += $count;
                $checked += absint( $diag['fully_checked_link_count'] ?? 0 );
                $unresolved += absint( $diag['unresolved_reference_count'] ?? 0 );
                $pattern = sanitize_key( (string) ( $diag['pattern'] ?? 'no-evidence' ) );
                if ( isset( $patterns[ $pattern ] ) ) { $patterns[ $pattern ]++; }
                foreach ( (array) ( $diag['relation_totals'] ?? array() ) as $relation => $total ) {
                    if ( isset( $relation_totals[ $relation ] ) ) { $relation_totals[ $relation ] += absint( $total ); }
                }
                if ( absint( $diag['relation_totals']['contradicts'] ?? 0 ) > 0 ) { $with_counterevidence++; }
                foreach ( (array) ( $diag['gaps'] ?? array() ) as $gap ) { $gap = sanitize_key( (string) $gap ); if ( $gap ) { $gaps[ $gap ] = absint( $gaps[ $gap ] ?? 0 ) + 1; } }
            }
            foreach ( (array) ( $matrix['evidence_links'] ?? array() ) as $link ) {
                $key = self::clean( $link['source_key'] ?? '', 420 ); if ( $key ) { $source_keys[ $key ] = true; }
            }
        }
        arsort( $gaps );
        return array(
            'schema'                         => self::DIAGNOSTIC_SCHEMA,
            'interpretation'                 => 'descriptive-only',
            'claim_count'                    => $claims,
            'claims_with_evidence'           => $with_evidence,
            'claims_with_counterevidence'    => $with_counterevidence,
            'evidence_link_count'            => $links,
            'unique_source_count'            => count( $source_keys ),
            'fully_checked_link_count'        => $checked,
            'unresolved_reference_count'      => $unresolved,
            'relation_totals'                 => $relation_totals,
            'claim_patterns'                  => $patterns,
            'gap_totals'                      => $gaps,
            'changes_claim_status'            => false,
            'changes_confidence'              => false,
            'scores_truth'                    => false,
            'infers_missing_relationships'    => false,
        );
    }

    public static function project_graph( $user_id, $project_id ) {
        $user_id = absint( $user_id ); $project_id = absint( $project_id );
        if ( ! $user_id ) { return new WP_Error( 'sc_graph_signin', __( 'Sign in to open your private research graph.', 'sustainable-catalyst-library' ), array( 'status' => 401 ) ); }
        if ( ! class_exists( 'SC_Library_Unified_Research_Projects_Source_Bundles' ) || ! SC_Library_Unified_Research_Projects_Source_Bundles::user_owns_project( $project_id, $user_id ) ) {
            return new WP_Error( 'sc_graph_project', __( 'That research project is not available to this account.', 'sustainable-catalyst-library' ), array( 'status' => 403 ) );
        }
        $project = SC_Library_Unified_Research_Projects_Source_Bundles::project_state( $project_id, $user_id );
        if ( is_wp_error( $project ) ) { return $project; }
        $notebooks = self::project_notebooks( $user_id, $project_id );
        $matrices = self::project_matrices( $user_id, $project_id );
        $routes = self::project_learning_routes( $user_id, $project_id );
        $nodes = array(); $edges = array(); $node_index = array(); $edge_index = array(); $reference_nodes = array(); $reference_source_map = array();
        $project_node = 'project:' . $project_id;
        self::add_node( $nodes, $node_index, array( 'id' => $project_node, 'type' => 'project', 'label' => $project['title'] ?? __( 'Research project', 'sustainable-catalyst-library' ), 'title' => $project['title'] ?? '', 'summary' => $project['research_question'] ?? '', 'urn' => $project['project_identity']['urn'] ?? '', 'visibility' => 'private' ) );

        foreach ( (array) ( $project['references'] ?? array() ) as $reference ) {
            $link_id = self::clean( $reference['id'] ?? '', 100 ); if ( ! $link_id ) { continue; }
            $rid = self::node_id( 'reference', $link_id );
            $resolution = (array) ( $reference['resolution'] ?? array() );
            self::add_node( $nodes, $node_index, array( 'id' => $rid, 'type' => 'reference', 'label' => $resolution['label'] ?? $reference['label'] ?? __( 'Project reference', 'sustainable-catalyst-library' ), 'title' => $resolution['label'] ?? $reference['label'] ?? '', 'url' => $resolution['url'] ?? $reference['url'] ?? '', 'summary' => $reference['note'] ?? '', 'visibility' => 'private-reference', 'family' => sanitize_key( (string) ( $reference['family'] ?? 'external' ) ), 'ref_id' => self::clean( $reference['ref_id'] ?? '', 320 ) ) );
            self::add_edge( $edges, $edge_index, array( 'source' => $project_node, 'target' => $rid, 'relation' => sanitize_key( (string) ( $reference['role'] ?? 'reference' ) ), 'label' => $reference['role'] ?? 'reference', 'provenance' => 'explicit-project-link', 'record_id' => $link_id ) );
            $reference_nodes[ $link_id ] = $rid;
            $family_key = sanitize_key( (string) ( $reference['family'] ?? 'external' ) ) . ':' . self::clean( $reference['ref_id'] ?? '', 320 );
            if ( ':' !== $family_key ) { $reference_source_map[ $family_key ] = $rid; }
        }

        foreach ( (array) ( $project['source_bundles'] ?? array() ) as $bundle ) {
            $bundle_id = self::clean( $bundle['bundle_id'] ?? '', 100 ); if ( ! $bundle_id ) { continue; }
            $bid = self::node_id( 'bundle', $bundle_id );
            self::add_node( $nodes, $node_index, array( 'id' => $bid, 'type' => 'source_bundle', 'label' => $bundle['title'] ?? __( 'Source bundle', 'sustainable-catalyst-library' ), 'title' => $bundle['title'] ?? '', 'summary' => $bundle['description'] ?? '', 'urn' => $bundle['urn'] ?? '', 'visibility' => 'private' ) );
            self::add_edge( $edges, $edge_index, array( 'source' => $project_node, 'target' => $bid, 'relation' => 'contains_bundle', 'label' => 'contains bundle', 'provenance' => 'explicit-source-bundle', 'record_id' => $bundle_id ) );
            foreach ( (array) ( $bundle['link_ids'] ?? array() ) as $link_id ) {
                $link_id = self::clean( $link_id, 100 ); if ( isset( $reference_nodes[ $link_id ] ) ) { self::add_edge( $edges, $edge_index, array( 'source' => $bid, 'target' => $reference_nodes[ $link_id ], 'relation' => 'includes_reference', 'label' => 'includes reference', 'provenance' => 'explicit-source-bundle', 'record_id' => $bundle_id . ':' . $link_id ) ); }
            }
        }

        foreach ( $notebooks as $notebook ) {
            $notebook_id = absint( $notebook['notebook_id'] ?? 0 ); if ( ! $notebook_id ) { continue; }
            $nid = self::node_id( 'notebook', $notebook_id );
            self::add_node( $nodes, $node_index, array( 'id' => $nid, 'type' => 'notebook', 'label' => $notebook['title'] ?? __( 'Reading notebook', 'sustainable-catalyst-library' ), 'title' => $notebook['title'] ?? '', 'urn' => $notebook['urn'] ?? '', 'visibility' => 'private' ) );
            self::add_edge( $edges, $edge_index, array( 'source' => $project_node, 'target' => $nid, 'relation' => 'has_notebook', 'label' => 'has notebook', 'provenance' => 'explicit-project-context', 'record_id' => (string) $notebook_id ) );
            foreach ( (array) ( $notebook['notes'] ?? array() ) as $note ) {
                $note_id = self::clean( $note['id'] ?? '', 100 ); if ( ! $note_id || count( $nodes ) >= self::MAX_NODES ) { continue; }
                $nnid = self::node_id( 'note', $note_id );
                self::add_node( $nodes, $node_index, array( 'id' => $nnid, 'type' => 'reading_note', 'label' => $note['title'] ?? __( 'Reading note', 'sustainable-catalyst-library' ), 'title' => $note['title'] ?? '', 'summary' => $note['excerpt'] ?? $note['body'] ?? '', 'urn' => $note['urn'] ?? '', 'visibility' => 'private' ) );
                self::add_edge( $edges, $edge_index, array( 'source' => $nid, 'target' => $nnid, 'relation' => 'contains_note', 'label' => 'contains note', 'provenance' => 'explicit-notebook-record', 'record_id' => $note_id ) );
                $source_key = sanitize_key( (string) ( $note['source_family'] ?? 'external' ) ) . ':' . self::clean( $note['source_ref_id'] ?? '', 320 );
                if ( trim( $source_key, ':' ) && 'external:' !== $source_key ) {
                    $target = $reference_source_map[ $source_key ] ?? self::source_node( $nodes, $node_index, $source_key, $note['source_label'] ?? __( 'Linked source', 'sustainable-catalyst-library' ), $note['source_url'] ?? '' );
                    self::add_edge( $edges, $edge_index, array( 'source' => $nnid, 'target' => $target, 'relation' => 'references_source', 'label' => 'references source', 'provenance' => 'explicit-reading-note-source', 'record_id' => $note_id ) );
                }
            }
            foreach ( (array) ( $notebook['annotations'] ?? array() ) as $annotation ) {
                $annotation_id = self::clean( $annotation['id'] ?? '', 100 ); if ( ! $annotation_id || count( $nodes ) >= self::MAX_NODES ) { continue; }
                $aid = self::node_id( 'annotation', $annotation_id );
                self::add_node( $nodes, $node_index, array( 'id' => $aid, 'type' => 'annotation', 'label' => $annotation['source_label'] ?? __( 'Source annotation', 'sustainable-catalyst-library' ), 'title' => $annotation['source_label'] ?? __( 'Source annotation', 'sustainable-catalyst-library' ), 'summary' => $annotation['quote'] ?? $annotation['note'] ?? '', 'urn' => $annotation['urn'] ?? '', 'visibility' => 'private' ) );
                self::add_edge( $edges, $edge_index, array( 'source' => $nid, 'target' => $aid, 'relation' => 'contains_annotation', 'label' => 'contains annotation', 'provenance' => 'explicit-notebook-record', 'record_id' => $annotation_id ) );
            }
        }

        foreach ( $matrices as $matrix ) {
            $matrix_id = absint( $matrix['matrix_id'] ?? 0 ); if ( ! $matrix_id ) { continue; }
            $mid = self::node_id( 'matrix', $matrix_id );
            self::add_node( $nodes, $node_index, array( 'id' => $mid, 'type' => 'evidence_matrix', 'label' => $matrix['title'] ?? __( 'Evidence matrix', 'sustainable-catalyst-library' ), 'title' => $matrix['title'] ?? '', 'urn' => $matrix['urn'] ?? '', 'visibility' => 'private' ) );
            self::add_edge( $edges, $edge_index, array( 'source' => $project_node, 'target' => $mid, 'relation' => 'has_evidence_matrix', 'label' => 'has evidence matrix', 'provenance' => 'explicit-project-context', 'record_id' => (string) $matrix_id ) );
            $claim_nodes = array();
            foreach ( (array) ( $matrix['claims'] ?? array() ) as $claim ) {
                $claim_id = self::clean( $claim['id'] ?? '', 100 ); if ( ! $claim_id ) { continue; }
                $cid = self::node_id( 'claim', $claim_id );
                self::add_node( $nodes, $node_index, array( 'id' => $cid, 'type' => 'claim', 'label' => $claim['title'] ?? __( 'Research claim', 'sustainable-catalyst-library' ), 'title' => $claim['title'] ?? '', 'summary' => $claim['statement'] ?? '', 'urn' => $claim['urn'] ?? '', 'visibility' => 'private', 'claim_status' => sanitize_key( (string) ( $claim['status'] ?? 'working' ) ), 'user_confidence' => sanitize_key( (string) ( $claim['confidence'] ?? 'unset' ) ) ) );
                self::add_edge( $edges, $edge_index, array( 'source' => $mid, 'target' => $cid, 'relation' => 'contains_claim', 'label' => 'contains claim', 'provenance' => 'explicit-evidence-matrix-record', 'record_id' => $claim_id ) );
                $claim_nodes[ $claim_id ] = $cid;
            }
            foreach ( (array) ( $matrix['evidence_links'] ?? array() ) as $link ) {
                $claim_id = self::clean( $link['claim_id'] ?? '', 100 ); if ( ! isset( $claim_nodes[ $claim_id ] ) ) { continue; }
                $resolution = (array) ( $link['resolution'] ?? array() );
                $source_key = self::clean( $link['source_key'] ?? $resolution['source_key'] ?? '', 420 );
                $source_label = self::clean( $link['source_label'] ?? $resolution['label'] ?? __( 'Evidence source', 'sustainable-catalyst-library' ), 180 );
                $source_url = esc_url_raw( (string) ( $link['source_url'] ?? $resolution['url'] ?? '' ) );
                $source_id = self::source_node( $nodes, $node_index, $source_key, $source_label, $source_url, 'private-reference' );
                self::add_edge( $edges, $edge_index, array( 'source' => $claim_nodes[ $claim_id ], 'target' => $source_id, 'relation' => sanitize_key( (string) ( $link['relation'] ?? 'unresolved' ) ), 'label' => $link['relation'] ?? 'unresolved', 'provenance' => 'explicit-evidence-matrix-link', 'record_id' => $link['id'] ?? '' ) );
            }
        }

        foreach ( $routes as $route ) {
            $route_id = self::clean( $route['id'] ?? $route['uuid'] ?? $route['urn'] ?? $route['title'] ?? '', 160 ); if ( ! $route_id ) { continue; }
            $lrid = self::node_id( 'learning-route', $route_id );
            self::add_node( $nodes, $node_index, array( 'id' => $lrid, 'type' => 'learning_route', 'label' => $route['title'] ?? __( 'Learning route', 'sustainable-catalyst-library' ), 'title' => $route['title'] ?? '', 'summary' => $route['goal'] ?? '', 'urn' => $route['urn'] ?? '', 'visibility' => 'private' ) );
            self::add_edge( $edges, $edge_index, array( 'source' => $project_node, 'target' => $lrid, 'relation' => 'has_learning_route', 'label' => 'has learning route', 'provenance' => 'explicit-learning-route-project-context', 'record_id' => $route_id ) );
        }

        $intelligence = self::evidence_intelligence( $matrices );
        $type_counts = array(); foreach ( $nodes as $node ) { $type = (string) ( $node['type'] ?? 'record' ); $type_counts[ $type ] = absint( $type_counts[ $type ] ?? 0 ) + 1; } ksort( $type_counts );
        $relation_counts = array(); foreach ( $edges as $edge ) { $rel = (string) ( $edge['relation'] ?? 'related_to' ); $relation_counts[ $rel ] = absint( $relation_counts[ $rel ] ?? 0 ) + 1; } ksort( $relation_counts );
        $manifest = array( 'schema' => self::GRAPH_SCHEMA, 'version' => self::VERSION, 'project_id' => $project_id, 'project_urn' => (string) ( $project['project_identity']['urn'] ?? '' ), 'node_count' => count( $nodes ), 'edge_count' => count( $edges ), 'nodes' => $nodes, 'edges' => $edges, 'node_type_counts' => $type_counts, 'relation_counts' => $relation_counts, 'evidence_intelligence' => $intelligence, 'contract' => self::contract() );
        $manifest['checksum_sha256'] = hash( 'sha256', wp_json_encode( $manifest ) );
        $manifest['generated_at'] = gmdate( 'c' );
        return apply_filters( 'sc_library_knowledge_graph_evidence_project_graph', $manifest, $user_id, $project_id );
    }

    public function filter_state( $state, $user_id, $project_id = 0 ) {
        $next = self::project_graph( absint( $user_id ), absint( $project_id ) );
        return is_wp_error( $next ) ? $state : $next;
    }

    private static function canonical_url() {
        return class_exists( 'SC_Library_Canonical_Route_Identity' ) ? SC_Library_Canonical_Route_Identity::canonical_url() : home_url( '/knowledge-libraries/' );
    }

    private static function option_projects( $user_id ) {
        $catalog = self::project_catalog( $user_id );
        return is_wp_error( $catalog ) ? array() : (array) ( $catalog['projects'] ?? array() );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array( 'title' => __( 'Knowledge Graph & Evidence Intelligence', 'sustainable-catalyst-library' ) ), $atts, 'sc_knowledge_graph_evidence_intelligence' );
        wp_enqueue_style( 'sc-library-knowledge-graph-evidence-v450' );
        wp_enqueue_script( 'sc-library-knowledge-graph-evidence-v450' );
        $signed = is_user_logged_in(); $user_id = $signed ? get_current_user_id() : 0; $projects = $signed ? self::option_projects( $user_id ) : array();
        $selected = $signed ? absint( $_GET['sc_graph_project'] ?? $_GET['sc_project'] ?? 0 ) : 0;
        $owned = array_map( static fn( $p ) => absint( $p['project_id'] ?? 0 ), $projects ); if ( $selected && ! in_array( $selected, $owned, true ) ) { $selected = 0; } if ( ! $selected && $projects ) { $selected = absint( $projects[0]['project_id'] ); }
        $graph = $signed && $selected ? self::project_graph( $user_id, $selected ) : null; if ( is_wp_error( $graph ) ) { $graph = null; }
        wp_localize_script( 'sc-library-knowledge-graph-evidence-v450', 'scKnowledgeGraphEvidence', array( 'canonical' => esc_url_raw( self::canonical_url() ), 'restRoot' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'version' => self::VERSION ) );
        ob_start(); ?>
<section class="sc-kge" id="knowledge-graph-evidence-intelligence" data-sc-kge="v4.5.0">
    <header class="sc-kge__header"><p class="sc-kge__kicker"><?php esc_html_e( 'Explicit research graph', 'sustainable-catalyst-library' ); ?></p><h3><?php echo esc_html( (string) $atts['title'] ); ?></h3><p><?php esc_html_e( 'See how a private Research Project connects to the references, Source Bundles, notebooks, notes, annotations, Evidence Matrices, claims, evidence sources, and learning routes you explicitly linked. Evidence diagnostics describe coverage and conflict patterns; they do not decide what is true.', 'sustainable-catalyst-library' ); ?></p></header>
    <div class="sc-kge__boundary" role="note"><strong><?php esc_html_e( 'No inferred relationships', 'sustainable-catalyst-library' ); ?></strong><span><?php esc_html_e( 'v4.5.0 projects only relationships already declared in canonical Library records. It does not read private text to invent connections, score truth, change claim status or confidence, publish research, or write to Workspace.', 'sustainable-catalyst-library' ); ?></span></div>
    <?php if ( ! $signed ) : ?>
        <div class="sc-kge__signin"><p><?php esc_html_e( 'Sign in with your Sustainable Catalyst / Workspace account to open your private research graph.', 'sustainable-catalyst-library' ); ?></p><a href="<?php echo esc_url( wp_login_url( self::canonical_url() . '#knowledge-graph-evidence' ) ); ?>"><?php esc_html_e( 'Sign in →', 'sustainable-catalyst-library' ); ?></a></div>
    <?php elseif ( ! $projects ) : ?>
        <div class="sc-kge__empty"><p><?php esc_html_e( 'Create a Research Project first. Knowledge Graph & Evidence Intelligence composes existing project relationships rather than creating a separate graph store.', 'sustainable-catalyst-library' ); ?></p><a href="#research-projects"><?php esc_html_e( 'Open Research Projects →', 'sustainable-catalyst-library' ); ?></a></div>
    <?php else : ?>
        <form class="sc-kge__project" method="get" action="<?php echo esc_url( self::canonical_url() ); ?>#knowledge-graph-evidence"><label><span><?php esc_html_e( 'Research Project', 'sustainable-catalyst-library' ); ?></span><select name="sc_graph_project" data-sc-kge-project><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['project_id'] ); ?>" <?php selected( $selected, absint( $project['project_id'] ) ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></label><button type="submit"><?php esc_html_e( 'Open graph', 'sustainable-catalyst-library' ); ?></button></form>
        <?php if ( is_array( $graph ) ) : $intel = (array) ( $graph['evidence_intelligence'] ?? array() ); ?>
            <div class="sc-kge__metrics" aria-label="<?php esc_attr_e( 'Research graph summary', 'sustainable-catalyst-library' ); ?>"><div><b><?php echo esc_html( (string) absint( $graph['node_count'] ?? 0 ) ); ?></b><span><?php esc_html_e( 'Explicit nodes', 'sustainable-catalyst-library' ); ?></span></div><div><b><?php echo esc_html( (string) absint( $graph['edge_count'] ?? 0 ) ); ?></b><span><?php esc_html_e( 'Explicit relationships', 'sustainable-catalyst-library' ); ?></span></div><div><b><?php echo esc_html( (string) absint( $intel['claim_count'] ?? 0 ) ); ?></b><span><?php esc_html_e( 'Claims reviewed', 'sustainable-catalyst-library' ); ?></span></div><div><b><?php echo esc_html( (string) absint( $intel['claims_with_counterevidence'] ?? 0 ) ); ?></b><span><?php esc_html_e( 'Claims with counterevidence', 'sustainable-catalyst-library' ); ?></span></div></div>
            <div class="sc-kge__layout"><div class="sc-kge__canvas"><div class="sc-kge__canvas-head"><h4><?php echo esc_html( (string) ( $graph['nodes'][0]['title'] ?? __( 'Project graph', 'sustainable-catalyst-library' ) ) ); ?></h4><p><?php esc_html_e( 'Visual projection of explicit relationships. Use the accessible relationship list below for the full record.', 'sustainable-catalyst-library' ); ?></p></div><div class="sc-kge__svg" data-sc-kge-svg aria-label="<?php esc_attr_e( 'Private project relationship graph', 'sustainable-catalyst-library' ); ?>"></div></div><aside class="sc-kge__intel"><h4><?php esc_html_e( 'Evidence Intelligence', 'sustainable-catalyst-library' ); ?></h4><dl><div><dt><?php esc_html_e( 'Claims with evidence', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) absint( $intel['claims_with_evidence'] ?? 0 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Unique evidence sources', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) absint( $intel['unique_source_count'] ?? 0 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Unresolved references', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) absint( $intel['unresolved_reference_count'] ?? 0 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Checked evidence links', 'sustainable-catalyst-library' ); ?></dt><dd><?php echo esc_html( (string) absint( $intel['fully_checked_link_count'] ?? 0 ) ); ?></dd></div></dl><?php if ( ! empty( $intel['gap_totals'] ) ) : ?><h5><?php esc_html_e( 'Recorded review gaps', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( array_slice( (array) $intel['gap_totals'], 0, 6, true ) as $gap => $count ) : ?><li><span><?php echo esc_html( ucwords( str_replace( '_', ' ', $gap ) ) ); ?></span><b><?php echo esc_html( (string) absint( $count ) ); ?></b></li><?php endforeach; ?></ul><?php else : ?><p class="sc-kge__quiet"><?php esc_html_e( 'No matrix-level review gaps are currently recorded for this project. This is not a truth or completeness certification.', 'sustainable-catalyst-library' ); ?></p><?php endif; ?></aside></div>
            <details class="sc-kge__record"><summary><?php esc_html_e( 'Accessible graph record', 'sustainable-catalyst-library' ); ?></summary><div class="sc-kge__record-grid"><div><h5><?php esc_html_e( 'Nodes', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( array_slice( (array) ( $graph['nodes'] ?? array() ), 0, 80 ) as $node ) : ?><li><b><?php echo esc_html( (string) ( $node['title'] ?? $node['label'] ?? '' ) ); ?></b><span><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) ( $node['type'] ?? 'record' ) ) ) ); ?></span></li><?php endforeach; ?></ul></div><div><h5><?php esc_html_e( 'Relationships', 'sustainable-catalyst-library' ); ?></h5><ul><?php foreach ( array_slice( (array) ( $graph['edges'] ?? array() ), 0, 100 ) as $edge ) : ?><li><code><?php echo esc_html( (string) ( $edge['source'] ?? '' ) ); ?></code><span><?php echo esc_html( str_replace( '_', ' ', (string) ( $edge['relation'] ?? '' ) ) ); ?></span><code><?php echo esc_html( (string) ( $edge['target'] ?? '' ) ); ?></code></li><?php endforeach; ?></ul></div></div></details>
            <script type="application/json" data-sc-kge-data><?php echo wp_json_encode( array( 'nodes' => $graph['nodes'] ?? array(), 'edges' => $graph['edges'] ?? array() ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
        <?php endif; ?>
    <?php endif; ?>
</section>
        <?php return ob_get_clean();
    }
}
