<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['posts'] = array(
    1 => array(
        'post_type'    => 'sc_foundation_doc',
        'post_status'  => 'publish',
        'post_title'   => 'Planetary Boundaries and Earth System Stability',
        'post_name'    => 'planetary-boundaries-earth-system-stability',
        'post_excerpt' => 'A structured overview of planetary boundaries.',
        'post_content' => '<h2>Introduction</h2><p>Planetary boundaries provide a framework for understanding environmental limits that support Earth system stability. The framework identifies interacting processes that can increase systemic risk when human pressure exceeds a safe operating space.</p><h2>Methods</h2><p>The document synthesizes peer reviewed research and compares multiple Earth system indicators. The analysis uses published thresholds, uncertainty ranges, and evidence from climate, biosphere, freshwater, and biogeochemical systems.</p><h2>Findings</h2><p>The evidence shows that several boundaries are under increasing pressure and that interactions between boundaries can amplify risk. The document concludes that governance must consider cross-system effects rather than treating each environmental problem in isolation.</p><h2>Limitations</h2><p>The thresholds include uncertainty and should not be treated as exact predictions. Regional variation and data gaps limit the precision of some global estimates.</p><h2>References</h2><p>Rockstrom et al. (2009) and Richardson et al. (2023) provide central sources. See https://example.org/reference and doi:10.1234/example.5678.</p>',
    ),
    2 => array(
        'post_type'    => 'sc_foundation_doc',
        'post_status'  => 'publish',
        'post_title'   => 'Climate Risk and Resilient Institutions',
        'post_name'    => 'climate-risk-resilient-institutions',
        'post_excerpt' => 'Institutional approaches to climate risk.',
        'post_content' => '<h2>Overview</h2><p>Climate risk affects infrastructure, health, finance, and public administration. Resilient institutions use evidence, scenario analysis, and transparent governance to manage uncertainty.</p><h2>Governance</h2><p>The study recommends adaptive planning, public accountability, and cross-sector coordination. Strong institutions document assumptions, monitor outcomes, and revise decisions when conditions change.</p><h2>Limitations</h2><p>Institutional capacity differs across regions, and no single governance model applies everywhere. The analysis does not provide a universal implementation sequence.</p><h2>References</h2><p>Author and Researcher (2024) provide supporting evidence.</p>',
    ),
);
$GLOBALS['meta'] = array(
    1 => array(
        '_sc_document_raw_text' => '',
        '_sc_document_page_count' => 12,
        '_sc_document_version' => '1.0',
        '_sc_document_intelligence_public' => '1',
        '_sc_document_intelligence_excluded' => '0',
        '_sc_library_concept_ids' => array( 501, 502 ),
        '_sc_library_entity_ids' => array( 601 ),
    ),
    2 => array(
        '_sc_document_raw_text' => '',
        '_sc_document_page_count' => 8,
        '_sc_document_version' => '1.0',
        '_sc_document_intelligence_public' => '1',
        '_sc_document_intelligence_excluded' => '0',
        '_sc_library_concept_ids' => array( 502, 503 ),
        '_sc_library_entity_ids' => array( 602 ),
    ),
);
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) {
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_Post {}
class WP_REST_Server {
    const READABLE = 'GET';
    const EDITABLE = 'POST';
    const CREATABLE = 'POST';
}
class WP_REST_Request {}
class WP_REST_Response {}

class SC_Library_Foundation_Pages {
    const POST_TYPE = 'sc_foundation_doc';
}
class SC_Library_PDF_To_Document {
    const META_RAW_TEXT = '_sc_document_raw_text';
    const META_PAGE_COUNT = '_sc_document_page_count';
    const META_VERSION = '_sc_document_version';
}
class SC_Library_Topics_Concepts_Relationships {
    const TOPIC_TAXONOMY = 'sc_library_topic';
    const META_CONCEPT_IDS = '_sc_library_concept_ids';
    const META_ENTITY_IDS = '_sc_library_entity_ids';
}
class SC_Library_Connected_Research_Environment {
    const META_DOCUMENT_IDS = '_sc_project_document_ids';
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_rest_route() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) { return 'Y-m-d' === $type ? '2026-07-15' : '2026-07-15 14:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/document/' . $id; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function get_posts( $args ) {
    $ids = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) continue;
        if ( isset( $args['post_status'] ) ) {
            $statuses = (array) $args['post_status'];
            if ( ! in_array( $post['post_status'], $statuses, true ) ) continue;
        }
        if ( isset( $args['post__in'] ) && ! in_array( $id, (array) $args['post__in'], true ) ) continue;
        $ids[] = $id;
    }
    return $ids;
}
function wp_get_object_terms( $id, $taxonomy, $args = array() ) {
    if ( 'names' === ( $args['fields'] ?? '' ) ) {
        return 1 === (int) $id ? array( 'Planetary Boundaries', 'Earth Systems' ) : array( 'Climate Risk', 'Institutions' );
    }
    return 1 === (int) $id ? array( 101, 102 ) : array( 103, 104 );
}
function apply_filters( $tag, $value ) { return $value; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function __( $text, $domain = null ) { return $text; }
function is_singular() { return false; }
function get_queried_object_id() { return 0; }
function get_page_by_path() { return null; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-research-librarian-document-intelligence.php';

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

$reflection = new ReflectionClass( 'SC_Library_Research_Librarian_Document_Intelligence' );

$normalize = $reflection->getMethod( 'normalize_search_text' );
$normalize->setAccessible( true );
assert_same( 'planetary boundaries earth system', $normalize->invoke( null, 'Planetary Boundaries — Earth System!' ), 'Search normalization' );

$tokens = $reflection->getMethod( 'tokenize' );
$tokens->setAccessible( true );
assert_same( array( 'climate', 'risk', 'institutions' ), $tokens->invoke( null, 'Climate risk in institutions' ), 'Tokenization retains meaningful terms' );

$word_count = $reflection->getMethod( 'word_count' );
$word_count->setAccessible( true );
assert_same( 4, $word_count->invoke( null, 'one two three four' ), 'Word count' );

$sentences = $reflection->getMethod( 'sentence_split' );
$sentences->setAccessible( true );
$split = $sentences->invoke( null, 'This is a sufficiently long first sentence. This is a sufficiently long second sentence.' );
assert_same( 2, count( $split ), 'Sentence splitting' );

$sections = SC_Library_Research_Librarian_Document_Intelligence::extract_sections(
    $GLOBALS['posts'][1]['post_content'],
    strip_tags( $GLOBALS['posts'][1]['post_content'] )
);
assert_true( count( $sections ) >= 5, 'HTML headings create sections' );
assert_same( 'Introduction', $sections[0]['title'], 'First section title' );
assert_same( 'sc-library-document-section-index/1.0', $sections[0]['schema'], 'Section schema' );
assert_true( $sections[0]['word_count'] > 10, 'Section word count' );
assert_true( strlen( $sections[0]['text_hash'] ) === 64, 'Section SHA-256 hash' );

$chunks = SC_Library_Research_Librarian_Document_Intelligence::build_chunks( $sections );
assert_true( count( $chunks ) >= 5, 'Section chunks created' );
assert_same( 'sc-library-document-chunk-index/1.0', $chunks[0]['schema'], 'Chunk schema' );
assert_same( 'section-1', $chunks[0]['section_id'], 'Chunk section relationship' );
assert_true( $chunks[0]['word_count'] > 0, 'Chunk word count' );

$summary = SC_Library_Research_Librarian_Document_Intelligence::build_summary(
    strip_tags( $GLOBALS['posts'][1]['post_content'] ),
    $GLOBALS['posts'][1]['post_title']
);
assert_true( strlen( $summary ) > 50, 'Summary generated' );

$key_points = SC_Library_Research_Librarian_Document_Intelligence::build_key_points(
    strip_tags( $GLOBALS['posts'][1]['post_content'] ),
    $summary
);
assert_true( count( $key_points ) >= 1, 'Key points generated' );
assert_true( count( $key_points ) <= 8, 'Key points bounded' );

$terms = SC_Library_Research_Librarian_Document_Intelligence::extract_terms(
    strip_tags( $GLOBALS['posts'][1]['post_content'] ),
    $GLOBALS['posts'][1]['post_title']
);
assert_true( count( $terms ) >= 3, 'Terms generated' );
assert_true( isset( $terms[0]['term'], $terms[0]['label'], $terms[0]['count'] ), 'Term structure' );

$questions = SC_Library_Research_Librarian_Document_Intelligence::build_questions(
    $GLOBALS['posts'][1]['post_title'],
    $sections,
    $terms,
    $key_points
);
assert_true( count( $questions ) >= 4, 'Questions generated' );
assert_true( count( $questions ) <= 8, 'Questions bounded' );
assert_true( false !== strpos( $questions[0], 'central argument or purpose' ), 'Title-aware question' );

$signals = SC_Library_Research_Librarian_Document_Intelligence::citation_signals(
    strip_tags( $GLOBALS['posts'][1]['post_content'] )
);
assert_true( $signals['doi_count'] >= 1, 'DOI detected' );
assert_true( $signals['url_count'] >= 1, 'URL detected' );
assert_true( $signals['author_year_count'] >= 1, 'Author-year citation detected' );
assert_true( $signals['references_heading'], 'Reference heading detected' );

$profile1 = SC_Library_Research_Librarian_Document_Intelligence::analyze_document( 1, true, true );
assert_true( ! is_wp_error( $profile1 ), 'Document one analyzed' );
assert_same( 'sc-library-document-intelligence/1.0', $profile1['schema'], 'Profile schema' );
assert_same( 1, $profile1['document_id'], 'Profile document ID' );
assert_same( 'ready', $profile1['status'], 'Document one ready' );
assert_true( $profile1['section_count'] >= 5, 'Profile section count' );
assert_true( $profile1['chunk_count'] >= 5, 'Profile chunk count' );
assert_same( 12, $profile1['page_count'], 'Profile page count' );
assert_true( strlen( $profile1['source_hash'] ) === 64, 'Source hash' );

$profile2 = SC_Library_Research_Librarian_Document_Intelligence::analyze_document( 2, true, true );
assert_true( ! is_wp_error( $profile2 ), 'Document two analyzed' );
assert_same( 'ready', $profile2['status'], 'Document two ready' );

$retrieval = SC_Library_Research_Librarian_Document_Intelligence::search_documents(
    'Planetary Boundaries',
    array( 'include_private' => false, 'limit' => 10 )
);
assert_same( 'sc-library-title-aware-retrieval/1.0', $retrieval['schema'], 'Retrieval schema' );
assert_true( $retrieval['result_count'] >= 1, 'Retrieval result returned' );
assert_same( 1, $retrieval['results'][0]['document_id'], 'Title-aware ranking puts document one first' );
assert_true( in_array( 'title-contains', $retrieval['results'][0]['reasons'], true ) || in_array( 'title-token-overlap', $retrieval['results'][0]['reasons'], true ), 'Retrieval reason recorded' );

$comparison = SC_Library_Research_Librarian_Document_Intelligence::compare_documents( array( 1, 2 ), false );
assert_true( ! is_wp_error( $comparison ), 'Comparison generated' );
assert_same( 'sc-library-document-comparison/1.0', $comparison['schema'], 'Comparison schema' );
assert_same( 2, count( $comparison['documents'] ), 'Comparison document count' );
assert_same( 1, count( $comparison['pairwise'] ), 'Pairwise comparison count' );
assert_true( isset( $comparison['pairwise'][0]['term_similarity'] ), 'Similarity metric present' );

$public = SC_Library_Research_Librarian_Document_Intelligence::get_profile( 1, false, false );
assert_true( ! isset( $public['source_hash'] ), 'Public profile omits source hash' );
assert_true( ! isset( $public['title_aliases'] ), 'Public profile omits private title aliases' );
assert_true( isset( $public['summary'], $public['key_points'], $public['suggested_questions'] ), 'Public profile retains reviewed intelligence fields' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.7.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts at zero' );

echo "Research Librarian Document Intelligence fixture checks passed: 53\n";
