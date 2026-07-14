<?php
define( 'ABSPATH', __DIR__ );

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class SC_Library_Citation_Source_Manager {
    const PROJECT_POST_TYPE = 'sc_research_project';
    const SOURCE_POST_TYPE = 'sc_research_source';
    const META_PROJECT_IDS = '_sc_source_project_ids';
    const META_PROJECT_SOURCE_IDS = '_sc_project_source_ids';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
}
class SC_Library_Connected_Research_Environment {
    const META_SECTIONS = '_sc_project_bibliography_sections';
    const META_SOURCE_ENTRIES = '_sc_project_source_entries';
    const META_DOCUMENT_IDS = '_sc_project_document_ids';
    const META_TEAM = '_sc_project_team';
    const META_SNAPSHOTS = '_sc_project_bibliography_snapshots';
    const META_ACTIVITY = '_sc_project_activity';
    const META_SORT = '_sc_project_bibliography_sort';
    const META_HEALTH = '_sc_project_workspace_health';
    const MAX_SNAPSHOTS = 20;
    const MAX_SOURCE_ENTRIES = 1000;
    const MAX_ACTIVITY = 200;
    const SOURCE_ENTRY_SCHEMA = 'sc-library-project-source-entry/1.0';
    const SNAPSHOT_SCHEMA = 'sc-library-bibliography-snapshot/1.0';
    public static function source_role_options() { return array( 'background' => 'Background' ); }
    public static function inclusion_options() { return array( 'included' => 'Included', 'candidate' => 'Candidate', 'excluded' => 'Excluded' ); }
    public static function team_role_options() { return array( 'researcher' => 'Researcher' ); }
    public static function sort_options() { return array( 'section-author' => 'Section' ); }
}
class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }

function add_action() {}
function add_filter() {}
function register_rest_route() {}
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function __( $text, $domain = null ) { return $text; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-connected-research-reliability.php';

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

$reflection = new ReflectionClass( 'SC_Library_Connected_Research_Reliability' );

$bibtex = $reflection->getMethod( 'validate_bibtex' );
$bibtex->setAccessible( true );
assert_true( $bibtex->invoke( null, "@article{Adams2024,\n  title = {Alpha},\n}" ), 'Valid BibTeX' );
assert_true( ! $bibtex->invoke( null, "@article{Adams2024,\n  title = {Alpha},\n" ), 'Unbalanced BibTeX rejected' );
assert_true( $bibtex->invoke( null, '' ), 'Empty BibTeX bibliography allowed' );

$ris = $reflection->getMethod( 'validate_ris' );
$ris->setAccessible( true );
assert_true( $ris->invoke( null, "TY  - JOUR\nTI  - Alpha\nER  -" ), 'Valid RIS' );
assert_true( ! $ris->invoke( null, "TY  - JOUR\nTI  - Alpha" ), 'RIS without terminator rejected' );
assert_true( ! $ris->invoke( null, "ER  -\nTY  - JOUR" ), 'RIS ordering rejected' );
assert_true( $ris->invoke( null, '' ), 'Empty RIS bibliography allowed' );

$csl = $reflection->getMethod( 'validate_csl_records' );
$csl->setAccessible( true );
$warnings = array();
assert_true(
    $csl->invokeArgs(
        null,
        array(
            array(
                array(
                    'id' => 'Adams2024',
                    'type' => 'article-journal',
                    'title' => 'Alpha',
                    'author' => array( array( 'family' => 'Adams' ) ),
                    'issued' => array( 'date-parts' => array( array( 2024 ) ) ),
                ),
            ),
            &$warnings,
        )
    ),
    'Valid CSL record'
);
assert_same( 0, count( $warnings ), 'Complete CSL record has no warning' );

$warnings = array();
assert_true(
    $csl->invokeArgs(
        null,
        array(
            array(
                array(
                    'id' => 'NoAuthor',
                    'type' => 'report',
                    'title' => 'Report',
                ),
            ),
            &$warnings,
        )
    ),
    'Incomplete but structurally valid CSL record'
);
assert_same( 2, count( $warnings ), 'Missing author and date warnings' );

$warnings = array();
assert_true(
    ! $csl->invokeArgs(
        null,
        array(
            array(
                array(
                    'id' => '',
                    'type' => 'report',
                    'title' => '',
                ),
            ),
            &$warnings,
        )
    ),
    'Invalid CSL record rejected'
);

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.0.1', $migration['version'], 'Migration state version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts at zero' );
assert_same( 0, $migration['processed'], 'Migration processed starts at zero' );
assert_true( is_array( $migration['failures'] ), 'Migration failures are structured' );

$id_list = $reflection->getMethod( 'id_list' );
$id_list->setAccessible( true );
assert_same( array( 1, 2, 3 ), $id_list->invoke( null, array( 1, '2', 2, 0, -3 ) ), 'ID list is positive and unique' );
assert_same( array( 4, 5 ), $id_list->invoke( null, '4, 5, 4' ), 'String ID list parsing' );

echo "Production validation fixture checks passed: 20\n";
