<?php
define( 'ABSPATH', __DIR__ );

$GLOBALS['posts'] = array(
    1 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Climate Evidence Project', 'post_content' => 'Project description.', 'post_excerpt' => 'Summary.', 'post_name' => 'climate-evidence-project' ),
    10 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Alpha Study', 'post_content' => '', 'post_excerpt' => 'Alpha abstract.', 'post_name' => 'alpha-study' ),
    11 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Beta Report', 'post_content' => '', 'post_excerpt' => 'Beta abstract.', 'post_name' => 'beta-report' ),
    12 => array( 'post_type' => 'sc_research_source', 'post_status' => 'draft', 'post_title' => 'Private Candidate', 'post_content' => '', 'post_excerpt' => '', 'post_name' => 'private-candidate' ),
    20 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Foundation Document', 'post_content' => '', 'post_excerpt' => 'Document summary.', 'post_name' => 'foundation-document' ),
);
$GLOBALS['meta'] = array(
    1 => array(
        '_sc_project_visibility' => 'public',
        '_sc_project_status' => 'active',
        '_sc_project_code' => 'SC-CLIMATE-001',
        '_sc_project_citation_style' => 'harvard',
        '_sc_project_bibliography_title' => 'References',
        '_sc_project_source_ids' => array( 10, 11, 12 ),
        '_sc_project_research_question' => 'How should climate evidence inform governance?',
        '_sc_project_objectives' => array( 'Review core evidence', 'Evaluate counterevidence' ),
        '_sc_project_methods' => 'Structured literature review.',
        '_sc_project_scope' => 'Global climate governance.',
        '_sc_project_document_ids' => array( 20 ),
        '_sc_project_bibliography_sections' => array(
            array( 'slug' => 'core-sources', 'title' => 'Core Sources', 'description' => '', 'order' => 0 ),
            array( 'slug' => 'counterevidence', 'title' => 'Counterevidence', 'description' => '', 'order' => 1 ),
        ),
        '_sc_project_source_entries' => array(
            array( 'source_id' => 10, 'role' => 'primary', 'section' => 'core-sources', 'inclusion' => 'included', 'priority' => 5, 'annotation' => 'Central evidence.', 'added_at' => '2026-07-14 12:00:00', 'added_by' => 7, 'updated_at' => '2026-07-14 12:00:00', 'updated_by' => 7 ),
            array( 'source_id' => 11, 'role' => 'counterevidence', 'section' => 'counterevidence', 'inclusion' => 'included', 'priority' => 3, 'annotation' => 'Alternative interpretation.', 'added_at' => '2026-07-14 12:00:00', 'added_by' => 7, 'updated_at' => '2026-07-14 12:00:00', 'updated_by' => 7 ),
            array( 'source_id' => 12, 'role' => 'background', 'section' => 'core-sources', 'inclusion' => 'candidate', 'priority' => 2, 'annotation' => '', 'added_at' => '2026-07-14 12:00:00', 'added_by' => 7, 'updated_at' => '2026-07-14 12:00:00', 'updated_by' => 7 ),
        ),
        '_sc_project_bibliography_sort' => 'section-author',
        '_sc_project_team' => array( array( 'user_id' => 7, 'role' => 'lead' ) ),
    ),
    10 => array( '_sc_source_project_ids' => array( 1 ) ),
    11 => array( '_sc_source_project_ids' => array( 1 ) ),
    12 => array( '_sc_source_project_ids' => array( 1 ) ),
);
$GLOBALS['current_user'] = 0;
$GLOBALS['can_edit'] = false;

class WP_Error {
    private $code; private $message; private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_Citation_Source_Manager {
    const PROJECT_POST_TYPE = 'sc_research_project';
    const SOURCE_POST_TYPE = 'sc_research_source';
    const META_PROJECT_CODE = '_sc_project_code';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    const META_PROJECT_STATUS = '_sc_project_status';
    const META_PROJECT_STYLE = '_sc_project_citation_style';
    const META_PROJECT_SOURCE_IDS = '_sc_project_source_ids';
    const META_PROJECT_BIBLIOGRAPHY_TITLE = '_sc_project_bibliography_title';
    const META_PROJECT_IDS = '_sc_source_project_ids';

    public static function get_source_data( $source_id, $include_private = false ) {
        if ( ! isset( $GLOBALS['posts'][ $source_id ] ) ) return array();
        if ( ! $include_private && 'publish' !== $GLOBALS['posts'][ $source_id ]['post_status'] ) return array();
        $records = array(
            10 => array(
                'id' => 10, 'title' => 'Alpha Study', 'abstract' => 'Alpha abstract.', 'authors' => array( array( 'family' => 'Adams', 'given' => 'A.' ) ),
                'organization' => '', 'year' => '2024', 'container_title' => 'Journal of Climate', 'publisher' => '', 'place' => '', 'volume' => '2', 'issue' => '1',
                'pages' => '10-20', 'doi' => '10.1000/alpha', 'isbn' => '', 'url' => 'https://example.org/alpha', 'language' => 'en', 'source_type' => 'journal-article',
                'citation_key' => 'Adams2024', 'metadata_verified' => true, 'completeness_score' => 95, 'full_text_status' => 'open-access', 'related_document_ids' => array( 20 ),
                'duplicate_matches' => array(),
            ),
            11 => array(
                'id' => 11, 'title' => 'Beta Report', 'abstract' => 'Beta abstract.', 'authors' => array(), 'organization' => 'Beta Institute',
                'year' => '2022', 'container_title' => '', 'publisher' => 'Beta Institute', 'place' => 'Chicago', 'volume' => '', 'issue' => '',
                'pages' => '', 'doi' => '', 'isbn' => '9780000000000', 'url' => 'https://example.org/beta', 'language' => 'en', 'source_type' => 'report',
                'citation_key' => 'Beta2022', 'metadata_verified' => false, 'completeness_score' => 70, 'full_text_status' => 'available', 'related_document_ids' => array(),
                'duplicate_matches' => array( 99 ),
            ),
            12 => array(
                'id' => 12, 'title' => 'Private Candidate', 'abstract' => '', 'authors' => array( array( 'family' => 'Zulu', 'given' => 'Z.' ) ),
                'organization' => '', 'year' => '2025', 'container_title' => '', 'publisher' => '', 'place' => '', 'volume' => '', 'issue' => '',
                'pages' => '', 'doi' => '', 'isbn' => '', 'url' => '', 'language' => 'en', 'source_type' => 'report',
                'citation_key' => 'Zulu2025', 'metadata_verified' => false, 'completeness_score' => 40, 'full_text_status' => 'unknown', 'related_document_ids' => array(),
                'duplicate_matches' => array(),
            ),
        );
        return $records[ $source_id ] ?? array();
    }

    public static function format_citation( $source, $style = 'harvard', $mode = 'reference', $locator = '' ) {
        $id = is_array( $source ) ? ( $source['id'] ?? 0 ) : (int) $source;
        return 10 === $id ? 'Adams, A. (2024) Alpha Study.' : ( 11 === $id ? 'Beta Institute (2022) Beta Report.' : 'Zulu, Z. (2025) Private Candidate.' );
    }
}
class SC_Library_Evidence_Claim_Linking {
    public static function claim_ids_for_project( $project_id, $include_private = false ) { return array( 30, 31 ); }
    public static function evidence_ids_for_project( $project_id, $include_private = false ) { return array( 40, 41, 42 ); }
    public static function project_packet( $project_id, $include_private = false ) { return array( 'project' => array( 'id' => $project_id ), 'claims' => array(), 'evidence' => array() ); }
    public static function project_packet_markdown( $project_id, $include_private = false ) { return '# Evidence Packet'; }
}

function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function sanitize_text_field( $value ) { return is_array( $value ) ? '' : trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $type ) { return '2026-07-14 12:00:00'; }
function get_current_user_id() { return $GLOBALS['current_user']; }
function current_user_can( $capability, $post_id = 0 ) { return $GLOBALS['can_edit']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_post( $post_id ) {
    if ( ! isset( $GLOBALS['posts'][ $post_id ] ) ) return null;
    $object = new stdClass();
    $object->ID = $post_id;
    foreach ( $GLOBALS['posts'][ $post_id ] as $key => $value ) $object->{$key} = $value;
    return $object;
}
function get_post_type( $post_id = 0 ) { return $GLOBALS['posts'][ $post_id ]['post_type'] ?? ''; }
function get_post_status( $post_id ) { return $GLOBALS['posts'][ $post_id ]['post_status'] ?? ''; }
function get_the_title( $post_id ) { return $GLOBALS['posts'][ $post_id ]['post_title'] ?? ''; }
function get_post_field( $field, $post_id ) { return $GLOBALS['posts'][ $post_id ][ $field ] ?? ''; }
function get_post_modified_time( $format, $gmt, $post_id ) { return '2026-07-14T12:00:00Z'; }
function get_post_meta( $post_id, $key, $single = true ) { return $GLOBALS['meta'][ $post_id ][ $key ] ?? ''; }
function update_post_meta( $post_id, $key, $value ) { $GLOBALS['meta'][ $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['meta'][ $post_id ][ $key ] ); return true; }
function get_user_by( $field, $value ) { return 7 === (int) $value ? (object) array( 'ID' => 7, 'display_name' => 'Research Lead' ) : false; }
function wp_list_pluck( $list, $field ) { return array_map( function ( $item ) use ( $field ) { return $item[ $field ]; }, $list ); }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function get_page_by_path() { return null; }
function get_posts( $args = array() ) { return array(); }
function add_action() {}
function add_shortcode() {}
function register_rest_route() {}
function add_meta_box() {}
function add_submenu_page() {}

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-connected-research-environment.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

$sections = SC_Library_Connected_Research_Environment::bibliography_sections( 1 );
assert_same( 2, count( $sections ), 'Stored bibliography sections' );
assert_same( 'core-sources', $sections[0]['slug'], 'First section slug' );

$public_entries = SC_Library_Connected_Research_Environment::source_entries( 1, false );
assert_same( 2, count( $public_entries ), 'Draft candidate excluded from public source entries' );
assert_same( 10, $public_entries[0]['source_id'], 'Section-author sorting places Adams first' );
assert_same( 'included', $public_entries[0]['inclusion'], 'Included source state retained' );

$private_entries = SC_Library_Connected_Research_Environment::source_entries( 1, true );
assert_same( 2, count( $private_entries ), 'Team membership does not bypass Source edit permissions' );

$bibliography = SC_Library_Connected_Research_Environment::bibliography( 1, false );
assert_same( 2, $bibliography['entry_count'], 'Public included bibliography count' );
assert_same( 2, count( $bibliography['sections'] ), 'Two populated bibliography sections' );
assert_same( 'Adams, A. (2024) Alpha Study.', $bibliography['sections'][0]['entries'][0]['citation'], 'Harvard citation retained' );

$health = SC_Library_Connected_Research_Environment::workspace_health( 1, false );
assert_same( 2, $health['included_sources'], 'Health included count' );
assert_same( 1, $health['verified_sources'], 'Health verified count' );
assert_same( 1, $health['incomplete_sources'], 'Health incomplete count' );
assert_same( 1, $health['duplicate_warnings'], 'Health duplicate warnings' );
assert_same( 2, $health['claims'], 'Connected claim count' );
assert_same( 3, $health['evidence_notes'], 'Connected evidence count' );
assert_same( 1, $health['documents'], 'Connected document count' );
assert_true( $health['readiness_score'] >= 0 && $health['readiness_score'] <= 100, 'Readiness is bounded' );

$project = SC_Library_Connected_Research_Environment::project_data( 1, false );
assert_same( 'How should climate evidence inform governance?', $project['research_question'], 'Research question' );
assert_same( 2, count( $project['objectives'] ), 'Objectives' );
assert_true( ! isset( $project['team'] ), 'Public project omits team' );

$GLOBALS['current_user'] = 7;
$GLOBALS['can_edit'] = false;
$private_project = SC_Library_Connected_Research_Environment::project_data( 1, true );
assert_same( 1, count( $private_project['team'] ), 'Team member can read private project environment' );

$reflection = new ReflectionClass( 'SC_Library_Connected_Research_Environment' );
$bibtex = $reflection->getMethod( 'source_to_bibtex' );
$bibtex->setAccessible( true );
$ris = $reflection->getMethod( 'source_to_ris' );
$ris->setAccessible( true );
$csl = $reflection->getMethod( 'source_to_csl' );
$csl->setAccessible( true );

$alpha = SC_Library_Citation_Source_Manager::get_source_data( 10, true );
$bib = $bibtex->invoke( null, $alpha );
assert_true( false !== strpos( $bib, '@article{Adams2024' ), 'BibTeX type and key' );
assert_true( false !== strpos( $bib, 'doi = {10.1000/alpha}' ), 'BibTeX DOI' );

$ris_text = $ris->invoke( null, $alpha );
assert_true( false !== strpos( $ris_text, "TY  - JOUR" ), 'RIS journal type' );
assert_true( false !== strpos( $ris_text, "AU  - Adams, A." ), 'RIS author' );
assert_true( false !== strpos( $ris_text, "ER  -" ), 'RIS terminator' );

$csl_data = $csl->invoke( null, $alpha );
assert_same( 'article-journal', $csl_data['type'], 'CSL type' );
assert_same( 'Adams', $csl_data['author'][0]['family'], 'CSL author family' );
assert_same( 2024, $csl_data['issued']['date-parts'][0][0], 'CSL issued year' );

$markdown = SC_Library_Connected_Research_Environment::export_project( 1, 'markdown', false );
assert_same( 'markdown', $markdown['format'], 'Markdown export format' );
assert_true( false !== strpos( $markdown['content'], '## References' ), 'Markdown bibliography heading' );
assert_true( false !== strpos( $markdown['content'], '# Evidence Packet' ), 'Markdown connected evidence packet' );

$bibtex_export = SC_Library_Connected_Research_Environment::export_project( 1, 'bibtex', false );
assert_true( false !== strpos( $bibtex_export['content'], '@article{Adams2024' ), 'Project BibTeX export' );

$ris_export = SC_Library_Connected_Research_Environment::export_project( 1, 'ris', false );
assert_true( false !== strpos( $ris_export['content'], 'TY  - JOUR' ), 'Project RIS export' );

$csl_export = SC_Library_Connected_Research_Environment::export_project( 1, 'csl-json', false );
assert_same( 2, count( $csl_export['content'] ), 'Project CSL JSON export records' );

$json_export = SC_Library_Connected_Research_Environment::export_project( 1, 'json', false );
assert_same( 'sc-library-project-export/1.0', $json_export['content']['schema'], 'Project JSON export schema' );

echo "Connected research environment fixture checks passed: 29\n";
