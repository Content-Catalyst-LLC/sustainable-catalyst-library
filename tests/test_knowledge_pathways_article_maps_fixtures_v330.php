<?php
define( 'ABSPATH', __DIR__ );

$GLOBALS['posts'] = array(
    1 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Foundation Article', 'post_name' => 'foundation-article', 'post_excerpt' => 'Foundation summary.', 'post_content' => 'Foundation content.' ),
    2 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Research Source', 'post_name' => 'research-source', 'post_excerpt' => 'Source summary.', 'post_content' => '' ),
    3 => array( 'post_type' => 'sc_research_claim', 'post_status' => 'publish', 'post_title' => 'Research Claim', 'post_name' => 'research-claim', 'post_excerpt' => 'Claim summary.', 'post_content' => '' ),
    4 => array( 'post_type' => 'sc_library_concept', 'post_status' => 'publish', 'post_title' => 'Systems Thinking', 'post_name' => 'systems-thinking', 'post_excerpt' => 'Concept summary.', 'post_content' => '' ),
    5 => array( 'post_type' => 'sc_named_entity', 'post_status' => 'publish', 'post_title' => 'United Nations', 'post_name' => 'united-nations', 'post_excerpt' => 'Entity summary.', 'post_content' => '' ),
    6 => array( 'post_type' => 'sc_evidence_note', 'post_status' => 'private', 'post_title' => 'Private Evidence', 'post_name' => 'private-evidence', 'post_excerpt' => '', 'post_content' => '' ),
    100 => array( 'post_type' => 'sc_knowledge_path', 'post_status' => 'publish', 'post_title' => 'Climate Foundations', 'post_name' => 'climate-foundations', 'post_excerpt' => 'A guided climate pathway.', 'post_content' => 'Start with foundations and move toward analysis.' ),
    101 => array( 'post_type' => 'sc_knowledge_path', 'post_status' => 'publish', 'post_title' => 'Advanced Systems Path', 'post_name' => 'advanced-systems-path', 'post_excerpt' => 'Advanced pathway.', 'post_content' => 'Advanced synthesis.' ),
    200 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Climate Governance Project', 'post_name' => 'climate-governance-project', 'post_excerpt' => 'Project summary.', 'post_content' => 'Project description.' ),
);

$GLOBALS['meta'] = array(
    100 => array(
        '_sc_pathway_level' => 'foundational',
        '_sc_pathway_audience' => 'Researchers and students',
        '_sc_pathway_estimated_minutes' => 90,
        '_sc_pathway_outcomes' => array( 'Understand foundations', 'Evaluate evidence' ),
        '_sc_pathway_map_mode' => 'stages',
        '_sc_pathway_recommendation_terms' => 'climate, governance, evidence',
        '_sc_pathway_concept_ids' => array( 4 ),
        '_sc_pathway_entity_ids' => array( 5 ),
        '_sc_pathway_steps' => array(
            array( 'schema' => 'sc-library-pathway-step/1.0', 'uuid' => 'a', 'kind' => 'document', 'node_id' => 1, 'node_key' => 'document:1', 'label' => '', 'stage' => 'foundation', 'difficulty' => 'foundational', 'minutes' => 30, 'required' => true, 'note' => 'Begin here.', 'order' => 0 ),
            array( 'schema' => 'sc-library-pathway-step/1.0', 'uuid' => 'b', 'kind' => 'source', 'node_id' => 2, 'node_key' => 'source:2', 'label' => '', 'stage' => 'evidence', 'difficulty' => 'intermediate', 'minutes' => 25, 'required' => true, 'note' => '', 'order' => 1 ),
            array( 'schema' => 'sc-library-pathway-step/1.0', 'uuid' => 'c', 'kind' => 'claim', 'node_id' => 3, 'node_key' => 'claim:3', 'label' => '', 'stage' => 'analysis', 'difficulty' => 'advanced', 'minutes' => 35, 'required' => false, 'note' => '', 'order' => 2 ),
            array( 'schema' => 'sc-library-pathway-step/1.0', 'uuid' => 'd', 'kind' => 'evidence', 'node_id' => 6, 'node_key' => 'evidence:6', 'label' => '', 'stage' => 'analysis', 'difficulty' => 'advanced', 'minutes' => 10, 'required' => false, 'note' => '', 'order' => 3 ),
        ),
        '_sc_pathway_node_keys' => array( 'document:1', 'source:2', 'claim:3', 'evidence:6' ),
        '_sc_pathway_continuation_ids' => array( 101 ),
    ),
    101 => array(
        '_sc_pathway_level' => 'advanced',
        '_sc_pathway_audience' => 'Specialist researchers',
        '_sc_pathway_estimated_minutes' => 45,
        '_sc_pathway_recommendation_terms' => 'systems climate advanced',
        '_sc_pathway_concept_ids' => array( 4 ),
        '_sc_pathway_steps' => array(
            array( 'schema' => 'sc-library-pathway-step/1.0', 'uuid' => 'e', 'kind' => 'concept', 'node_id' => 4, 'node_key' => 'concept:4', 'label' => '', 'stage' => 'synthesis', 'difficulty' => 'advanced', 'minutes' => 45, 'required' => true, 'note' => '', 'order' => 0 ),
        ),
        '_sc_pathway_node_keys' => array( 'concept:4' ),
        '_sc_pathway_prerequisite_ids' => array( 100 ),
    ),
);
$GLOBALS['topics'] = array(
    100 => array( array( 'id' => 10, 'name' => 'Climate Governance', 'url' => 'https://example.org/topic/10' ) ),
    101 => array( array( 'id' => 10, 'name' => 'Climate Governance', 'url' => 'https://example.org/topic/10' ), array( 'id' => 11, 'name' => 'Systems', 'url' => 'https://example.org/topic/11' ) ),
    200 => array( array( 'id' => 10, 'name' => 'Climate Governance', 'url' => 'https://example.org/topic/10' ) ),
);
$GLOBALS['transients'] = array();
$GLOBALS['insert_id'] = 300;
$GLOBALS['current_user_can'] = true;

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public $max_num_pages = 1;
    public function __construct( $args = array() ) {
        $type = $args['post_type'] ?? '';
        foreach ( $GLOBALS['posts'] as $id => $post ) {
            if ( $post['post_type'] !== $type ) continue;
            $statuses = (array) ( $args['post_status'] ?? 'publish' );
            if ( ! in_array( $post['post_status'], $statuses, true ) ) continue;
            if ( ! empty( $args['meta_query'] ) ) {
                $match = false;
                foreach ( $args['meta_query'] as $query ) {
                    if ( ! is_array( $query ) || empty( $query['key'] ) ) continue;
                    $stored = $GLOBALS['meta'][ $id ][ $query['key'] ] ?? array();
                    if ( false !== strpos( serialize( $stored ), (string) ( $query['value'] ?? '' ) ) ) $match = true;
                }
                if ( ! $match ) continue;
            }
            $object = new stdClass();
            $object->ID = $id;
            foreach ( $post as $key => $value ) $object->{$key} = $value;
            $this->posts[] = $object;
        }
        $this->found_posts = count( $this->posts );
    }
}
class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_Citation_Source_Manager { const SOURCE_POST_TYPE = 'sc_research_source'; const PROJECT_POST_TYPE = 'sc_research_project'; }
class SC_Library_Evidence_Claim_Linking { const CLAIM_POST_TYPE = 'sc_research_claim'; const NOTE_POST_TYPE = 'sc_evidence_note'; }
class SC_Library_Topics_Concepts_Relationships {
    const TOPIC_TAXONOMY = 'sc_library_topic';
    const CONCEPT_POST_TYPE = 'sc_library_concept';
    const ENTITY_POST_TYPE = 'sc_named_entity';
    const VOCABULARY_POST_TYPE = 'sc_control_vocab';
    public static function node_type_options() { return array( 'document' => 'Document', 'source' => 'Source', 'claim' => 'Claim', 'evidence' => 'Evidence', 'project' => 'Project', 'concept' => 'Concept', 'topic' => 'Topic', 'entity' => 'Entity', 'vocabulary' => 'Vocabulary' ); }
    public static function node_post_type( $kind ) { $map = array( 'document' => 'sc_foundation_doc', 'source' => 'sc_research_source', 'claim' => 'sc_research_claim', 'evidence' => 'sc_evidence_note', 'project' => 'sc_research_project', 'concept' => 'sc_library_concept', 'entity' => 'sc_named_entity', 'vocabulary' => 'sc_control_vocab' ); return $map[ $kind ] ?? ''; }
    public static function node_exists( $kind, $id ) { if ( 'topic' === $kind ) return 10 === (int) $id || 11 === (int) $id; return self::node_post_type( $kind ) === get_post_type( $id ); }
    public static function node_is_public( $kind, $id ) { return 'topic' === $kind || 'publish' === get_post_status( $id ); }
    public static function node_label( $kind, $id ) { return 'topic' === $kind ? ( 10 === (int) $id ? 'Climate Governance' : 'Systems' ) : get_the_title( $id ); }
    public static function node_summary( $kind, $id ) { return 'Summary ' . $kind . ' ' . $id; }
    public static function node_url( $kind, $id ) { return self::node_is_public( $kind, $id ) ? 'https://example.org/' . $kind . '/' . $id : ''; }
    public static function topics_for_post( $id, $private = false ) { return $GLOBALS['topics'][ $id ] ?? array(); }
    public static function concept_data( $id, $private = false ) { return self::node_exists( 'concept', $id ) ? array( 'id' => $id, 'title' => get_the_title( $id ), 'url' => self::node_url( 'concept', $id ) ) : array(); }
    public static function entity_data( $id, $private = false ) { return self::node_exists( 'entity', $id ) ? array( 'id' => $id, 'title' => get_the_title( $id ), 'url' => self::node_url( 'entity', $id ) ) : array(); }
    public static function relationships_for_node( $kind, $id, $private = false, $direction = 'both' ) { return ( 'document' === $kind && 1 === (int) $id ) ? array( array( 'id' => 55, 'to_kind' => 'source', 'to_id' => 2, 'relation' => 'cites', 'relation_label' => 'Cites', 'weight' => 4 ) ) : array(); }
    public static function invalidate_coverage_cache() {}
}
class SC_Library_Connected_Research_Environment {
    public static function project_data( $id, $private = false ) { return 200 === (int) $id ? array( 'id' => 200, 'title' => 'Climate Governance Project', 'summary' => 'Project summary.', 'description' => 'Project description.', 'objectives' => array( 'Assess evidence', 'Build synthesis' ), 'document_ids' => array( 1 ), 'source_entries' => array( array( 'source_id' => 2, 'inclusion' => 'included', 'annotation' => 'Core evidence.' ) ), 'claim_ids' => array( 3 ), 'evidence_ids' => array( 6 ) ) : array(); }
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_taxonomy() {}
function register_taxonomy_for_object_type() {}
function term_exists() { return true; }
function wp_insert_term() { return array( 'term_id' => 1 ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function current_time() { return '2026-07-15 12:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return $GLOBALS['current_user_can']; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/pathway/' . $id; }
function get_post( $id ) { if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return null; $o = new stdClass(); $o->ID = $id; foreach ( $GLOBALS['posts'][ $id ] as $k => $v ) $o->{$k} = $v; return $o; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function taxonomy_exists() { return true; }
function wp_get_post_terms( $id, $taxonomy ) { return array(); }
function wp_get_object_terms( $id, $taxonomy, $args = array() ) { return array( 10 ); }
function wp_set_object_terms() { return true; }
function wp_list_pluck( $items, $key ) { return array_map( function ( $item ) use ( $key ) { return is_array( $item ) ? ( $item[ $key ] ?? null ) : ( $item->{$key} ?? null ); }, $items ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_trim_words( $text, $num = 55 ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( $text ) ), 0, $num ) ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function apply_filters( $tag, $value ) { return $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_post_modified_time() { return '2026-07-15T12:00:00Z'; }
function get_posts( $args = array() ) {
    $result = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( ( $args['post_type'] ?? '' ) !== $post['post_type'] ) continue;
        if ( ! in_array( $post['post_status'], (array) ( $args['post_status'] ?? 'publish' ), true ) ) continue;
        if ( isset( $args['meta_key'] ) && (string) ( $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? '' ) !== (string) ( $args['meta_value'] ?? '' ) ) continue;
        $o = new stdClass(); $o->ID = $id; foreach ( $post as $k => $v ) $o->{$k} = $v;
        $result[] = ! empty( $args['fields'] ) && 'ids' === $args['fields'] ? $id : $o;
    }
    return $result;
}
function wp_insert_post( $data, $wp_error = false ) { $id = ++$GLOBALS['insert_id']; $GLOBALS['posts'][ $id ] = array( 'post_type' => $data['post_type'], 'post_status' => $data['post_status'], 'post_title' => $data['post_title'], 'post_name' => sanitize_title( $data['post_title'] ), 'post_excerpt' => $data['post_excerpt'] ?? '', 'post_content' => $data['post_content'] ?? '' ); return $id; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function set_transient( $key, $value ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function __( $text ) { return $text; }
function _n( $single, $plural, $number ) { return 1 === (int) $number ? $single : $plural; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function do_action() {}
function wp_kses_post( $value ) { return $value; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-knowledge-pathways-article-maps.php';

function assert_true( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); } }
function assert_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

assert_same( 'Introductory', SC_Library_Knowledge_Pathways_Article_Maps::level_options()['introductory'], 'Level label' );
assert_same( 'Synthesis', SC_Library_Knowledge_Pathways_Article_Maps::stage_options()['synthesis'], 'Stage label' );

$steps = SC_Library_Knowledge_Pathways_Article_Maps::sanitize_steps(
    array(
        array( 'kind' => 'document', 'node_id' => 1, 'stage' => 'foundation', 'required' => 1, 'minutes' => 20 ),
        array( 'kind' => 'document', 'node_id' => 1, 'stage' => 'core' ),
        array( 'kind' => 'pathway', 'node_id' => 100 ),
        array( 'kind' => 'source', 'node_id' => 2, 'difficulty' => 'intermediate' ),
        array( 'kind' => 'invalid', 'node_id' => 2 ),
        array( 'kind' => 'claim', 'node_id' => 999 ),
    ),
    100
);
assert_same( 2, count( $steps ), 'Duplicate, self, invalid kind, and missing nodes removed' );
assert_same( 'document:1', $steps[0]['node_key'], 'Document node key' );
assert_same( true, $steps[0]['required'], 'Required flag' );
assert_same( 'source:2', $steps[1]['node_key'], 'Source node key' );
assert_true( ! empty( $steps[0]['uuid'] ), 'Step UUID generated' );

$public_steps = SC_Library_Knowledge_Pathways_Article_Maps::pathway_steps( 100, false );
assert_same( 3, count( $public_steps ), 'Private Evidence step excluded publicly' );
assert_same( 'Foundation Article', $public_steps[0]['label'], 'Missing label resolved from node' );
$private_steps = SC_Library_Knowledge_Pathways_Article_Maps::pathway_steps( 100, true );
assert_same( 4, count( $private_steps ), 'Private step retained for editor' );

$data = SC_Library_Knowledge_Pathways_Article_Maps::get_pathway_data( 100, false );
assert_same( 'sc-library-knowledge-pathway/1.0', $data['schema'], 'Pathway schema' );
assert_same( 3, $data['step_count'], 'Public step count' );
assert_same( 2, $data['required_step_count'], 'Required public step count' );
assert_same( 90, $data['estimated_minutes'], 'Stored estimated time' );
assert_same( 1, count( $data['concepts'] ), 'Concept assignment' );
assert_same( 1, count( $data['entities'] ), 'Entity assignment' );
assert_same( 1, count( $data['topics'] ), 'Topic assignment' );

$map = SC_Library_Knowledge_Pathways_Article_Maps::map_data( 100, false );
assert_same( 'sc-library-article-map/1.0', $map['schema'], 'Map schema' );
assert_same( 3, count( $map['nodes'] ), 'Map nodes' );
assert_true( count( $map['edges'] ) >= 3, 'Sequence and semantic map edges' );
assert_same( 'Foundation Article', $map['nodes'][0]['label'], 'Map node label' );

$memberships = SC_Library_Knowledge_Pathways_Article_Maps::pathways_for_node( 'document', 1, false, 10 );
assert_same( 1, count( $memberships ), 'Node pathway membership' );
assert_same( 100, $memberships[0]['id'], 'Membership pathway ID' );

$recommendations = SC_Library_Knowledge_Pathways_Article_Maps::recommend_pathways(
    array( 'query' => 'climate evidence', 'topic_ids' => array( 10 ), 'concept_ids' => array( 4 ), 'node_keys' => array( 'document:1' ), 'level' => 'foundational' ),
    false,
    5
);
assert_same( 'sc-library-pathway-recommendations/1.0', $recommendations['schema'], 'Recommendation schema' );
assert_true( count( $recommendations['items'] ) >= 1, 'Recommendation returned' );
assert_same( 100, $recommendations['items'][0]['pathway']['id'], 'Most relevant pathway ranked first' );
assert_true( $recommendations['items'][0]['score'] > 20, 'Recommendation score combines context' );

$derived = SC_Library_Knowledge_Pathways_Article_Maps::derive_from_project( 200, array( 'force_new' => true ) );
assert_true( is_int( $derived ) && $derived > 300, 'Project pathway created' );
assert_same( 200, get_post_meta( $derived, '_sc_pathway_derived_project_id', true ), 'Derived project relationship' );
assert_same( 'draft', get_post_status( $derived ), 'Derived pathway remains draft' );
assert_same( 5, count( get_post_meta( $derived, '_sc_pathway_steps', true ) ), 'Project, document, Source, Claim, and Evidence steps derived' );

$reflection = new ReflectionClass( 'SC_Library_Knowledge_Pathways_Article_Maps' );
$id_list = $reflection->getMethod( 'id_list' ); $id_list->setAccessible( true );
assert_same( array( 1, 2, 3 ), $id_list->invoke( null, '1, 2, 2, -3, 0' ), 'ID normalization' );
$string_list = $reflection->getMethod( 'string_list' ); $string_list->setAccessible( true );
assert_same( array( 'Alpha', 'Beta' ), $string_list->invoke( null, "Alpha\nBeta\nAlpha" ), 'String-list normalization' );
$format = $reflection->getMethod( 'format_minutes' ); $format->setAccessible( true );
assert_same( '1 hr 30 min', $format->invoke( null, 90 ), 'Time formatting' );
assert_same( '45 minutes', $format->invoke( null, 45 ), 'Minute formatting' );
$truncate = $reflection->getMethod( 'truncate_label' ); $truncate->setAccessible( true );
assert_same( 'Long…', $truncate->invoke( null, 'Longer label', 5 ), 'Map label truncation' );

echo "Knowledge pathway fixture checks passed: 32\n";
