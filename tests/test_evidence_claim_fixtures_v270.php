<?php
define( 'ABSPATH', __DIR__ );

$GLOBALS['sc_posts'] = array(
    10 => array(
        'post_type' => 'sc_evidence_note',
        'post_status' => 'publish',
        'post_title' => 'Boundary quotation',
        'post_content' => 'Human activity has pushed Earth systems beyond a safe operating space.',
        'post_excerpt' => 'A concise statement of the central finding.',
        'post_name' => 'boundary-quotation',
    ),
    11 => array(
        'post_type' => 'sc_evidence_note',
        'post_status' => 'publish',
        'post_title' => 'Boundary counterevidence',
        'post_content' => 'The global framework can conceal substantial regional variation.',
        'post_excerpt' => 'A qualification of aggregate global analysis.',
        'post_name' => 'boundary-counterevidence',
    ),
    20 => array(
        'post_type' => 'sc_research_claim',
        'post_status' => 'publish',
        'post_title' => 'Planetary boundaries indicate systemic risk',
        'post_content' => 'Crossing several planetary boundaries increases systemic ecological risk.',
        'post_excerpt' => 'A research claim about systemic ecological risk.',
        'post_name' => 'planetary-boundaries-systemic-risk',
    ),
    30 => array(
        'post_type' => 'sc_research_source',
        'post_status' => 'publish',
        'post_title' => 'Planetary boundaries study',
        'post_content' => '',
        'post_excerpt' => '',
        'post_name' => 'planetary-boundaries-study',
    ),
    40 => array(
        'post_type' => 'sc_research_project',
        'post_status' => 'publish',
        'post_title' => 'Planetary Boundaries Research',
        'post_content' => 'Evidence review for planetary boundaries.',
        'post_excerpt' => '',
        'post_name' => 'planetary-boundaries-research',
    ),
);
$GLOBALS['sc_meta'] = array(
    10 => array(
        '_sc_evidence_source_id' => 30,
        '_sc_evidence_project_ids' => array( 40 ),
        '_sc_evidence_claim_links' => array(
            array(
                'schema' => 'sc-library-claim-evidence-link/1.0',
                'claim_id' => 20,
                'relation' => 'supports',
                'strength' => 5,
                'note' => 'Directly supports the central risk claim.',
                'created_at' => '2026-07-14 12:00:00',
                'created_by' => 7,
                'updated_at' => '2026-07-14 12:00:00',
                'updated_by' => 7,
            ),
        ),
        '_sc_evidence_locator_type' => 'page',
        '_sc_evidence_locator_start' => '12',
        '_sc_evidence_locator_end' => '',
        '_sc_evidence_locator_label' => '',
        '_sc_evidence_context_before' => '',
        '_sc_evidence_context_after' => '',
        '_sc_evidence_analysis' => 'This quotation establishes the core system-level concern.',
        '_sc_evidence_transcription_method' => 'manual',
        '_sc_evidence_quote_verified' => '1',
        '_sc_evidence_locator_verified' => '1',
        '_sc_evidence_visibility' => 'public',
        '_sc_evidence_review_status' => 'verified',
        '_sc_evidence_confidence' => 5,
        '_sc_evidence_content_hash' => 'hash-10',
        '_sc_evidence_last_reviewed' => '2026-07-14 12:00:00',
    ),
    11 => array(
        '_sc_evidence_source_id' => 30,
        '_sc_evidence_project_ids' => array( 40 ),
        '_sc_evidence_claim_links' => array(
            array(
                'schema' => 'sc-library-claim-evidence-link/1.0',
                'claim_id' => 20,
                'relation' => 'contradicts',
                'strength' => 3,
                'note' => 'Challenges universal interpretation.',
                'created_at' => '2026-07-14 12:00:00',
                'created_by' => 7,
                'updated_at' => '2026-07-14 12:00:00',
                'updated_by' => 7,
            ),
        ),
        '_sc_evidence_locator_type' => 'pages',
        '_sc_evidence_locator_start' => '44',
        '_sc_evidence_locator_end' => '46',
        '_sc_evidence_locator_label' => '',
        '_sc_evidence_analysis' => 'This evidence qualifies global aggregation.',
        '_sc_evidence_transcription_method' => 'copy-paste',
        '_sc_evidence_quote_verified' => '1',
        '_sc_evidence_locator_verified' => '1',
        '_sc_evidence_visibility' => 'public',
        '_sc_evidence_review_status' => 'reviewed',
        '_sc_evidence_confidence' => 4,
    ),
    20 => array(
        '_sc_claim_project_ids' => array( 40 ),
        '_sc_claim_evidence_ids' => array( 10, 11 ),
        '_sc_claim_status' => 'verified',
        '_sc_claim_confidence' => 82,
        '_sc_claim_visibility' => 'public',
        '_sc_claim_scope' => 'Global Earth-system assessment.',
        '_sc_claim_assumptions' => 'Selected indicators are adequate proxies.',
        '_sc_claim_limitations' => 'Regional variation requires separate analysis.',
        '_sc_claim_counterclaim' => 'Global thresholds may be too coarse for local decisions.',
        '_sc_claim_review_notes' => 'Private reviewer note.',
        '_sc_claim_last_reviewed' => '2026-07-14 12:00:00',
    ),
    40 => array(
        '_sc_project_visibility' => 'public',
        '_sc_project_status' => 'active',
    ),
);
$GLOBALS['sc_terms'] = array(
    'sc_evidence_type' => array(
        10 => array( array( 'slug' => 'direct-quotation', 'name' => 'Direct quotation' ) ),
        11 => array( array( 'slug' => 'counterevidence', 'name' => 'Counterevidence' ) ),
    ),
    'sc_claim_type' => array(
        20 => array( array( 'slug' => 'causal', 'name' => 'Causal' ) ),
    ),
);

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server {
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST';
}
class WP_REST_Request {}
class WP_Query {
    public $posts = array();
    public $found_posts = 0;
    public $max_num_pages = 0;
    public function __construct( $args = array() ) {
        $this->posts = array();
    }
}
class SC_Library_Foundation_Pages {
    const POST_TYPE = 'sc_foundation_doc';
}
class SC_Library_Citation_Source_Manager {
    const SOURCE_POST_TYPE = 'sc_research_source';
    const PROJECT_POST_TYPE = 'sc_research_project';
    const META_PROJECT_STATUS = '_sc_project_status';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    public static function format_citation( $source_id, $style = 'harvard', $mode = 'reference', $locator = '' ) {
        if ( 'in-text' === $mode ) {
            return '(Rockström et al., 2026' . ( $locator ? ', ' . $locator : '' ) . ')';
        }
        return 'Rockström, J. et al. (2026) Planetary boundaries study.';
    }
}

function __( $text, $domain = null ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return is_array( $value ) ? '' : trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $type ) { return '2026-07-14 12:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return false; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function get_post( $post_id ) {
    if ( ! isset( $GLOBALS['sc_posts'][ $post_id ] ) ) return null;
    $post = new stdClass();
    $post->ID = $post_id;
    foreach ( $GLOBALS['sc_posts'][ $post_id ] as $key => $value ) $post->{$key} = $value;
    return $post;
}
function get_post_type( $post_id = 0 ) { return $post_id && isset( $GLOBALS['sc_posts'][ $post_id ] ) ? $GLOBALS['sc_posts'][ $post_id ]['post_type'] : ''; }
function get_post_status( $post_id ) { return $GLOBALS['sc_posts'][ $post_id ]['post_status'] ?? ''; }
function get_the_title( $post_id ) { return $GLOBALS['sc_posts'][ $post_id ]['post_title'] ?? ''; }
function get_post_field( $field, $post_id ) { return $GLOBALS['sc_posts'][ $post_id ][ $field ] ?? ''; }
function get_post_modified_time( $format, $gmt, $post_id ) { return '2026-07-14T12:00:00Z'; }
function get_permalink( $post_id ) { return 'https://example.org/?p=' . $post_id; }
function wp_get_attachment_url( $attachment_id ) { return $attachment_id ? 'https://example.org/file-' . $attachment_id . '.pdf' : ''; }
function get_post_meta( $post_id, $key, $single = true ) { return $GLOBALS['sc_meta'][ $post_id ][ $key ] ?? ''; }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['sc_meta'][ $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['sc_meta'][ $post_id ][ $key ] ); return true; }
function wp_get_object_terms( $post_id, $taxonomy ) {
    $terms = $GLOBALS['sc_terms'][ $taxonomy ][ $post_id ] ?? array();
    return array_map( function ( $term ) {
        $object = new stdClass();
        $object->slug = $term['slug'];
        $object->name = $term['name'];
        return $object;
    }, $terms );
}
function get_posts( $args = array() ) {
    $results = array();
    $statuses = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
    foreach ( $GLOBALS['sc_posts'] as $id => $post ) {
        if ( ! empty( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) continue;
        if ( $statuses && ! in_array( $post['post_status'], $statuses, true ) ) continue;
        if ( isset( $args['meta_key'] ) ) {
            $value = $GLOBALS['sc_meta'][ $id ][ $args['meta_key'] ] ?? null;
            if ( array_key_exists( 'meta_value', $args ) && (string) $value !== (string) $args['meta_value'] ) continue;
            if ( ! array_key_exists( 'meta_value', $args ) && null === $value ) continue;
        }
        if ( ! empty( $args['meta_query'] ) ) {
            $matches = true;
            foreach ( $args['meta_query'] as $query ) {
                if ( ! is_array( $query ) || empty( $query['key'] ) ) continue;
                $value = $GLOBALS['sc_meta'][ $id ][ $query['key'] ] ?? '';
                if ( 'LIKE' === ( $query['compare'] ?? '=' ) ) {
                    if ( false === strpos( serialize( $value ), (string) $query['value'] ) ) $matches = false;
                } elseif ( (string) $value !== (string) ( $query['value'] ?? '' ) ) {
                    $matches = false;
                }
            }
            if ( ! $matches ) continue;
        }
        $results[] = ! empty( $args['fields'] ) && 'ids' === $args['fields'] ? $id : get_post( $id );
    }
    return $results;
}
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function add_shortcode() {}
function register_post_type() {}
function register_taxonomy() {}
function register_rest_route() {}
function term_exists() { return true; }
function wp_insert_term() { return true; }
function get_option( $key, $default = false ) { return $default; }
function update_option() { return true; }
function flush_rewrite_rules() {}

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-evidence-claim-linking.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAILED: {$label}\n" );
        exit( 1 );
    }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

assert_same( 'p. 12', SC_Library_Evidence_Claim_Linking::locator_display(
    array( 'locator_type' => 'page', 'locator_start' => '12', 'locator_end' => '', 'locator_label' => '' )
), 'Single-page locator' );
assert_same( 'pp. 44–46', SC_Library_Evidence_Claim_Linking::locator_display(
    array( 'locator_type' => 'pages', 'locator_start' => '44', 'locator_end' => '46', 'locator_label' => '' )
), 'Page-range locator' );
assert_same( 'Figure 3', SC_Library_Evidence_Claim_Linking::locator_display(
    array( 'locator_type' => 'custom', 'locator_start' => '', 'locator_end' => '', 'locator_label' => 'Figure 3' )
), 'Custom locator' );

$note = SC_Library_Evidence_Claim_Linking::get_evidence_data( 10, false );
assert_same( 'Direct quotation', $note['evidence_type_label'], 'Evidence type mapping' );
assert_same( 'p. 12', SC_Library_Evidence_Claim_Linking::locator_display( $note ), 'Evidence data locator' );
assert_same( true, $note['quote_verified'], 'Quote verification' );
assert_same( true, $note['locator_verified'], 'Locator verification' );
assert_true( false !== strpos( $note['source_in_text'], 'p. 12' ), 'Page locator in Harvard in-text citation' );

$quote = SC_Library_Evidence_Claim_Linking::citation_ready_quote( $note );
assert_true( 0 === strpos( $quote, '“Human activity' ), 'Direct quotation uses quotation marks' );
assert_true( false !== strpos( $quote, '(Rockström et al., 2026, p. 12)' ), 'Citation-ready quotation' );

$markdown = SC_Library_Evidence_Claim_Linking::evidence_markdown( $note );
assert_true( false !== strpos( $markdown, '> Human activity' ), 'Markdown block quotation' );
assert_true( false !== strpos( $markdown, '**Source:** Rockström' ), 'Markdown source citation' );
assert_true( false !== strpos( $markdown, 'Supports: Planetary boundaries indicate systemic risk' ), 'Markdown claim link' );

assert_same( true, SC_Library_Evidence_Claim_Linking::evidence_is_public( 10 ), 'Public evidence boundary' );
assert_same( true, SC_Library_Evidence_Claim_Linking::claim_is_public( 20 ), 'Public claim boundary' );
assert_same( true, SC_Library_Evidence_Claim_Linking::project_is_public( 40 ), 'Public project boundary' );

$packet = SC_Library_Evidence_Claim_Linking::claim_packet( 20, false );
assert_same( 2, $packet['link_count'], 'Claim packet link count' );
assert_same( 'supports', $packet['links'][0]['link']['relation'], 'Supports evidence ordered first' );
assert_same( 'contradicts', $packet['links'][1]['link']['relation'], 'Contradicting evidence retained' );
assert_same( 1, $packet['relation_totals']['supports'], 'Supports total' );
assert_same( 1, $packet['relation_totals']['contradicts'], 'Contradicts total' );

$claim_markdown = SC_Library_Evidence_Claim_Linking::claim_packet_markdown( 20, false );
assert_true( false !== strpos( $claim_markdown, '## Supports' ), 'Claim packet support section' );
assert_true( false !== strpos( $claim_markdown, '## Contradicts' ), 'Claim packet contradiction section' );
assert_true( false !== strpos( $claim_markdown, '**Limitations:** Regional variation' ), 'Claim limitations export' );

$project = SC_Library_Evidence_Claim_Linking::project_packet( 40, false );
assert_same( 1, $project['claim_count'], 'Project claim count' );
assert_same( 2, $project['evidence_count'], 'Project evidence count' );

$reflection = new ReflectionClass( 'SC_Library_Evidence_Claim_Linking' );
$sanitize = $reflection->getMethod( 'sanitize_claim_links' );
$sanitize->setAccessible( true );
$links = $sanitize->invoke(
    null,
    array(
        array( 'claim_id' => 20, 'relation' => 'supports', 'strength' => 9, 'note' => '<b>Strong</b>' ),
        array( 'claim_id' => 20, 'relation' => 'contradicts', 'strength' => 1, 'note' => 'Duplicate' ),
        array( 'claim_id' => 999, 'relation' => 'supports', 'strength' => 3 ),
    ),
    array()
);
assert_same( 1, count( $links ), 'Duplicate and invalid claim links removed' );
assert_same( 5, $links[0]['strength'], 'Link strength bounded to five' );
assert_same( 'Strong', $links[0]['note'], 'Link note sanitized' );

echo "Evidence and claim fixture checks passed: 27\n";
