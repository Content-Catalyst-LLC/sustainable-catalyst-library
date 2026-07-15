<?php
define( 'ABSPATH', __DIR__ );

$GLOBALS['posts'] = array(
    1 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Document One', 'post_excerpt' => 'Document summary.', 'post_content' => 'Document body.' ),
    2 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Document Two', 'post_excerpt' => '', 'post_content' => 'Second document body.' ),
    10 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Research Source', 'post_excerpt' => 'Source summary.', 'post_content' => '' ),
    20 => array( 'post_type' => 'sc_research_claim', 'post_status' => 'publish', 'post_title' => 'Research Claim', 'post_excerpt' => '', 'post_content' => 'Claim statement.' ),
    30 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Research Project', 'post_excerpt' => '', 'post_content' => '' ),
    40 => array( 'post_type' => 'sc_library_concept', 'post_status' => 'publish', 'post_title' => 'Planetary Boundaries', 'post_excerpt' => 'A framework summary.', 'post_content' => 'A framework defining environmental limits.' ),
    41 => array( 'post_type' => 'sc_library_concept', 'post_status' => 'draft', 'post_title' => 'Draft Concept', 'post_excerpt' => '', 'post_content' => '' ),
    50 => array( 'post_type' => 'sc_named_entity', 'post_status' => 'publish', 'post_title' => 'United Nations', 'post_excerpt' => 'International organization.', 'post_content' => 'An international organization.' ),
    60 => array( 'post_type' => 'sc_control_vocab', 'post_status' => 'publish', 'post_title' => 'Local Sustainability Vocabulary', 'post_excerpt' => '', 'post_content' => 'Vocabulary description.' ),
);
$GLOBALS['meta'] = array(
    30 => array( '_sc_project_visibility' => 'public' ),
    40 => array(
        '_sc_concept_type' => 'framework',
        '_sc_concept_status' => 'active',
        '_sc_concept_alt_labels' => array( 'PB framework', 'Earth-system boundaries' ),
        '_sc_concept_scope_note' => 'Use for the scientific framework.',
        '_sc_concept_external_uri' => 'https://example.org/concept/pb',
        '_sc_concept_vocabulary_id' => 60,
    ),
    41 => array( '_sc_concept_status' => 'draft' ),
    50 => array(
        '_sc_entity_type' => 'organization',
        '_sc_entity_aliases' => array( 'UN' ),
        '_sc_entity_external_uri' => 'https://www.un.org/',
        '_sc_entity_vocabulary_id' => 60,
    ),
    60 => array(
        '_sc_vocabulary_prefix' => 'sc',
        '_sc_vocabulary_uri' => 'https://example.org/vocab/',
        '_sc_vocabulary_version' => '1.0',
        '_sc_vocabulary_license' => 'CC BY 4.0',
        '_sc_vocabulary_language' => 'en',
        '_sc_vocabulary_authority' => 'Sustainable Catalyst',
    ),
);
$GLOBALS['terms'] = array(
    100 => (object) array(
        'term_id' => 100,
        'taxonomy' => 'sc_library_topic',
        'name' => 'Climate Governance',
        'slug' => 'climate-governance',
        'description' => 'Governance responses to climate change.',
        'parent' => 0,
    ),
);
$GLOBALS['term_meta'] = array(
    100 => array(
        '_sc_topic_alt_labels' => array( 'Climate policy governance' ),
        '_sc_topic_scope_note' => 'Use for governance structures and processes.',
        '_sc_topic_external_uri' => 'https://example.org/topic/climate-governance',
        '_sc_topic_vocabulary_id' => 60,
        '_sc_topic_status' => 'active',
    ),
);
$GLOBALS['next_post_id'] = 1000;

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}
class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_Citation_Source_Manager {
    const SOURCE_POST_TYPE = 'sc_research_source';
    const PROJECT_POST_TYPE = 'sc_research_project';
    const SOURCE_TOPIC_TAXONOMY = 'sc_source_topic';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    const META_PROJECT_SOURCE_IDS = '_sc_project_source_ids';
}
class SC_Library_Evidence_Claim_Linking {
    const CLAIM_POST_TYPE = 'sc_research_claim';
    const NOTE_POST_TYPE = 'sc_evidence_note';
    public static function claim_is_public( $id ) { return 'publish' === get_post_status( $id ); }
    public static function evidence_is_public( $id ) { return 'publish' === get_post_status( $id ); }
}

function add_action() {}
function add_filter() {}
function delete_transient( $key ) { return true; }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $expiration ) { return true; }
function add_shortcode() {}
function register_post_type() {}
function register_taxonomy() {}
function register_taxonomy_for_object_type() {}
function post_type_exists() { return true; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_trim_words( $value, $count ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( $value ) ), 0, $count ) ); }
function wp_slash( $value ) { return $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function current_time( $type ) { return '2026-07-15 13:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_post( $id ) {
    if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return null;
    $post = new stdClass();
    $post->ID = $id;
    foreach ( $GLOBALS['posts'][ $id ] as $key => $value ) $post->{$key} = $value;
    return $post;
}
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/record/' . $id; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_insert_post( $postarr, $wp_error = false ) {
    $id = $GLOBALS['next_post_id']++;
    $GLOBALS['posts'][ $id ] = array(
        'post_type' => $postarr['post_type'],
        'post_status' => $postarr['post_status'],
        'post_title' => $postarr['post_title'],
        'post_excerpt' => '',
        'post_content' => '',
    );
    return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
    $id = (int) $postarr['ID'];
    if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return new WP_Error( 'missing', 'Missing post.' );
    $GLOBALS['posts'][ $id ]['post_title'] = $postarr['post_title'];
    return $id;
}
function get_term( $id, $taxonomy = '' ) { return $GLOBALS['terms'][ $id ] ?? null; }
function get_term_meta( $id, $key, $single = true ) { return $GLOBALS['term_meta'][ $id ][ $key ] ?? ''; }
function get_term_link( $term, $taxonomy = '' ) { $id = is_object( $term ) ? $term->term_id : (int) $term; return 'https://example.org/topic/' . $id; }
function get_terms( $args = array() ) {
    if ( isset( $args['parent'] ) ) return array();
    return array_values( $GLOBALS['terms'] );
}
function wp_get_object_terms() { return array(); }
function get_posts() { return array(); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-topics-concepts-relationships.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

assert_same( '3.2.0', SC_Library_Topics_Concepts_Relationships::VERSION, 'Version' );
assert_same( 'sc_foundation_doc', SC_Library_Topics_Concepts_Relationships::node_post_type( 'document' ), 'Document post type mapping' );
assert_same( 'concept', SC_Library_Topics_Concepts_Relationships::post_kind( 'sc_library_concept' ), 'Concept kind mapping' );
assert_same( 'Narrower than', SC_Library_Topics_Concepts_Relationships::inverse_relation_label( 'broader-than' ), 'Broader inverse label' );
assert_same( 'Cited by', SC_Library_Topics_Concepts_Relationships::inverse_relation_label( 'cites' ), 'Citation inverse label' );
assert_true( SC_Library_Topics_Concepts_Relationships::node_exists( 'topic', 100 ), 'Topic exists' );
assert_true( SC_Library_Topics_Concepts_Relationships::node_exists( 'concept', 40 ), 'Concept exists' );
assert_true( SC_Library_Topics_Concepts_Relationships::node_is_public( 'concept', 40 ), 'Published active concept is public' );
assert_true( ! SC_Library_Topics_Concepts_Relationships::node_is_public( 'concept', 41 ), 'Draft concept is private' );
assert_same( 'Planetary Boundaries', SC_Library_Topics_Concepts_Relationships::node_label( 'concept', 40 ), 'Concept label' );
assert_same( 'https://example.org/record/40', SC_Library_Topics_Concepts_Relationships::node_url( 'concept', 40 ), 'Concept URL' );
assert_same( 'A framework summary.', SC_Library_Topics_Concepts_Relationships::node_summary( 'concept', 40 ), 'Concept summary' );

$concept = SC_Library_Topics_Concepts_Relationships::concept_data( 40, false );
assert_same( 'Planetary Boundaries', $concept['label'], 'Concept data label' );
assert_same( array( 'PB framework', 'Earth-system boundaries' ), $concept['alternative_labels'], 'Concept alternate labels' );
assert_same( 60, $concept['vocabulary_id'], 'Concept vocabulary' );

$entity = SC_Library_Topics_Concepts_Relationships::entity_data( 50, false );
assert_same( 'organization', $entity['entity_type'], 'Entity type' );
assert_same( array( 'UN' ), $entity['aliases'], 'Entity aliases' );

$vocabulary = SC_Library_Topics_Concepts_Relationships::vocabulary_data( 60, false );
assert_same( 'sc', $vocabulary['prefix'], 'Vocabulary prefix' );
assert_same( 'CC BY 4.0', $vocabulary['license'], 'Vocabulary license' );

$topic = SC_Library_Topics_Concepts_Relationships::topic_data( 100, false );
assert_same( 'Climate Governance', $topic['name'], 'Topic name' );
assert_same( array( 'Climate policy governance' ), $topic['alternative_labels'], 'Topic alternate label' );
assert_same( 60, $topic['vocabulary_id'], 'Topic vocabulary' );

$self = SC_Library_Topics_Concepts_Relationships::save_relationship(
    array( 'from_kind' => 'document', 'from_id' => 1, 'to_kind' => 'document', 'to_id' => 1, 'relation' => 'related-to' )
);
assert_true( is_wp_error( $self ), 'Self relationship rejected' );
assert_same( 'self_knowledge_relationship', $self->get_error_code(), 'Self relationship error code' );

$relation_id = SC_Library_Topics_Concepts_Relationships::save_relationship(
    array(
        'from_kind' => 'document',
        'from_id' => 1,
        'to_kind' => 'document',
        'to_id' => 2,
        'relation' => 'continues',
        'note' => '<b>Second volume</b>',
        'weight' => 9,
        'public' => true,
    )
);
assert_true( is_int( $relation_id ), 'Relationship saved' );
assert_same( 5, get_post_meta( $relation_id, '_sc_relation_weight', true ), 'Relationship weight bounded' );
assert_same( 'Second volume', get_post_meta( $relation_id, '_sc_relation_note', true ), 'Relationship note sanitized' );
assert_same( '1', get_post_meta( $relation_id, '_sc_relation_public', true ), 'Relationship public flag' );

$outgoing = SC_Library_Topics_Concepts_Relationships::relationship_data( $relation_id, false, 'document', 1 );
assert_same( 'Continues', $outgoing['label'], 'Outgoing relationship label' );
assert_same( 'Document Two', $outgoing['other_label'], 'Outgoing target label' );
$incoming = SC_Library_Topics_Concepts_Relationships::relationship_data( $relation_id, false, 'document', 2 );
assert_same( 'Continued by', $incoming['label'], 'Incoming relationship label' );
assert_same( 'Document One', $incoming['other_label'], 'Incoming source label' );

$reflection = new ReflectionClass( 'SC_Library_Topics_Concepts_Relationships' );
$string_list = $reflection->getMethod( 'string_list' );
$string_list->setAccessible( true );
assert_same( array( 'Alpha', 'Beta' ), $string_list->invoke( null, "Alpha\nBeta\nAlpha" ), 'String list normalization' );
$id_list = $reflection->getMethod( 'id_list' );
$id_list->setAccessible( true );
assert_same( array( 1, 2, 3 ), $id_list->invoke( null, array( 1, '2', 2, 0, -3 ) ), 'ID normalization' );
$state_method = $reflection->getMethod( 'default_migration_state' );
$state_method->setAccessible( true );
$state = $state_method->invoke( null );
assert_same( '3.2.0', $state['version'], 'Migration version' );
assert_same( 'terms', $state['step'], 'Migration first step' );
assert_same( 'pending', $state['status'], 'Migration initial status' );

assert_same( 21, count( SC_Library_Topics_Concepts_Relationships::relation_type_options() ), 'Relationship type count' );
assert_same( 9, count( SC_Library_Topics_Concepts_Relationships::node_type_options() ), 'Node type count' );

echo "Topics, concepts, and relationships fixture checks passed: 36\n";
