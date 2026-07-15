<?php
define( 'ABSPATH', __DIR__ );

$GLOBALS['posts'] = array(
    10 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Original Source' ),
    11 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Corrected Source' ),
    12 => array( 'post_type' => 'sc_research_source', 'post_status' => 'publish', 'post_title' => 'Latest Source' ),
    20 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Research Project' ),
);
$GLOBALS['meta'] = array(
    10 => array(
        '_sc_source_integrity_status' => 'current',
        '_sc_source_integrity_incoming_relations' => array(
            array( 'source_id' => 11, 'relation' => 'corrects', 'public' => true ),
        ),
        '_sc_source_project_ids' => array( 20 ),
    ),
    11 => array(
        '_sc_source_integrity_status' => 'corrected',
        '_sc_source_recommended_replacement_id' => 12,
        '_sc_source_integrity_relations' => array(
            array( 'target_id' => 10, 'relation' => 'corrects', 'public' => true ),
        ),
    ),
    12 => array(
        '_sc_source_integrity_status' => 'current',
    ),
    20 => array(
        '_sc_project_visibility' => 'public',
    ),
);
$GLOBALS['current_user_can'] = true;

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}
class SC_Library_Citation_Source_Manager {
    const SOURCE_POST_TYPE = 'sc_research_source';
    const PROJECT_POST_TYPE = 'sc_research_project';
    const SOURCE_TYPE_TAXONOMY = 'sc_source_type';
    const SOURCE_TOPIC_TAXONOMY = 'sc_source_topic';
    const META_AUTHORS = '_sc_source_authors';
    const META_ORGANIZATION = '_sc_source_organization';
    const META_ORGANIZATION_SHORT = '_sc_source_organization_short';
    const META_EDITORS = '_sc_source_editors';
    const META_YEAR = '_sc_source_year';
    const META_YEAR_SUFFIX = '_sc_source_year_suffix';
    const META_PUBLICATION_DATE = '_sc_source_publication_date';
    const META_ACCESS_DATE = '_sc_source_access_date';
    const META_CONTAINER_TITLE = '_sc_source_container_title';
    const META_PUBLISHER = '_sc_source_publisher';
    const META_PLACE = '_sc_source_place';
    const META_EDITION = '_sc_source_edition';
    const META_VOLUME = '_sc_source_volume';
    const META_ISSUE = '_sc_source_issue';
    const META_PAGES = '_sc_source_pages';
    const META_CHAPTER = '_sc_source_chapter';
    const META_REPORT_NUMBER = '_sc_source_report_number';
    const META_STANDARD_NUMBER = '_sc_source_standard_number';
    const META_JURISDICTION = '_sc_source_jurisdiction';
    const META_DOI = '_sc_source_doi';
    const META_ISBN = '_sc_source_isbn';
    const META_PMID = '_sc_source_pmid';
    const META_URL = '_sc_source_url';
    const META_ARCHIVE_URL = '_sc_source_archive_url';
    const META_LANGUAGE = '_sc_source_language';
    const META_ATTACHMENT_ID = '_sc_source_attachment_id';
    const META_RELATED_DOCUMENT_IDS = '_sc_source_related_document_ids';
    const META_PROJECT_IDS = '_sc_source_project_ids';
    const META_NOTES = '_sc_source_private_notes';
    const META_VERIFIED = '_sc_source_metadata_verified';
    const META_PEER_REVIEWED = '_sc_source_peer_reviewed';
    const META_SOURCE_LEVEL = '_sc_source_level';
    const META_FULL_TEXT_STATUS = '_sc_source_full_text_status';
    const META_CITATION_KEY = '_sc_source_citation_key';
    const META_PROVENANCE = '_sc_source_metadata_provenance';
    const META_LAST_VERIFIED = '_sc_source_last_verified';
    const META_PROJECT_SOURCE_IDS = '_sc_project_source_ids';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    public static function format_citation( $source_id, $style = 'harvard', $mode = 'reference' ) { return 'Example citation ' . (int) $source_id . '.'; }
}
class SC_Library_Connected_Research_Environment {
    const SOURCE_ENTRY_SCHEMA = 'sc-library-project-source-entry/1.0';
    public static function source_entries() { return array(); }
}
class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) { return '2026-07-14 12:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return $GLOBALS['current_user_can']; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_post( $id ) {
    if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return null;
    $post = new stdClass();
    $post->ID = $id;
    $post->post_type = $GLOBALS['posts'][ $id ]['post_type'];
    $post->post_status = $GLOBALS['posts'][ $id ]['post_status'];
    $post->post_title = $GLOBALS['posts'][ $id ]['post_title'];
    $post->post_excerpt = '';
    $post->post_content = '';
    return $post;
}
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/source/' . $id; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = null ) { return $text; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-source-versioning-integrity.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

assert_same( 'none', SC_Library_Source_Versioning_Integrity::severity_for_status( 'current' ), 'Current severity' );
assert_same( 'warning', SC_Library_Source_Versioning_Integrity::severity_for_status( 'superseded' ), 'Superseded severity' );
assert_same( 'high', SC_Library_Source_Versioning_Integrity::severity_for_status( 'expression-of-concern' ), 'Concern severity' );
assert_same( 'critical', SC_Library_Source_Versioning_Integrity::severity_for_status( 'retracted' ), 'Retraction severity' );
assert_true( ! SC_Library_Source_Versioning_Integrity::requires_review( 'current' ), 'Current does not require review' );
assert_true( SC_Library_Source_Versioning_Integrity::requires_review( 'corrected' ), 'Corrected requires review' );

$reflection = new ReflectionClass( 'SC_Library_Source_Versioning_Integrity' );

$sanitize = $reflection->getMethod( 'sanitize_relations' );
$sanitize->setAccessible( true );
$relations = $sanitize->invoke(
    null,
    array(
        array( 'target_id' => 10, 'relation' => 'supersedes', 'public' => 1, 'note' => '<b>Older</b>' ),
        array( 'target_id' => 10, 'relation' => 'supersedes', 'public' => 1, 'note' => 'Duplicate' ),
        array( 'target_id' => 12, 'relation' => 'invalid' ),
        array( 'target_id' => 11, 'relation' => 'corrects' ),
    ),
    array(),
    11
);
assert_same( 1, count( $relations ), 'Duplicate, invalid, and self relations removed' );
assert_same( 10, $relations[0]['target_id'], 'Valid target retained' );
assert_same( 'supersedes', $relations[0]['relation'], 'Valid relation retained' );
assert_same( 'Older', $relations[0]['note'], 'Relation note sanitized' );
assert_same( true, $relations[0]['public'], 'Public relation retained' );

assert_same( 12, SC_Library_Source_Versioning_Integrity::resolve_recommended_source( 11 ), 'Explicit replacement chain resolved' );
assert_same( 12, SC_Library_Source_Versioning_Integrity::resolve_recommended_source( 10 ), 'Incoming correction resolves latest replacement' );

$suggested = $reflection->getMethod( 'suggested_status_from_incoming' );
$suggested->setAccessible( true );
assert_same( 'corrected', $suggested->invoke( null, 10 ), 'Incoming correction suggests corrected status' );

$more = $reflection->getMethod( 'more_severe' );
$more->setAccessible( true );
assert_same( 'critical', $more->invoke( null, 'warning', 'critical' ), 'Severity escalates' );
assert_same( 'high', $more->invoke( null, 'high', 'warning' ), 'Severity does not downgrade' );

$date = $reflection->getMethod( 'sanitize_date' );
$date->setAccessible( true );
assert_same( '2026-07-14', $date->invoke( null, '2026-07-14' ), 'Valid date retained' );
assert_same( '', $date->invoke( null, '2026-02-31' ), 'Invalid date rejected' );

$state = $reflection->getMethod( 'default_scan_state' );
$state->setAccessible( true );
$scan = $state->invoke( null );
assert_same( '3.1.0', $scan['version'], 'Scan state version' );
assert_same( 'pending', $scan['status'], 'Scan starts pending' );
assert_same( 0, $scan['cursor'], 'Scan cursor starts at zero' );

$ids = $reflection->getMethod( 'id_list' );
$ids->setAccessible( true );
assert_same( array( 1, 2, 3 ), $ids->invoke( null, array( 1, '2', 2, 0, -3 ) ), 'ID normalization' );

echo "Source versioning and integrity fixture checks passed: 23\n";
