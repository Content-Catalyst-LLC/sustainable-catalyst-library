<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['posts'] = array(
    10 => array( 'post_type' => 'sc_inst_collection', 'post_status' => 'publish', 'post_title' => 'Institutional Records', 'post_excerpt' => 'Collection summary', 'post_content' => 'Collection description' ),
    20 => array( 'post_type' => 'sc_archive_component', 'post_status' => 'publish', 'post_title' => 'Series One', 'post_excerpt' => 'Series summary', 'post_content' => 'Series description' ),
    21 => array( 'post_type' => 'sc_archive_component', 'post_status' => 'publish', 'post_title' => 'Folder One', 'post_excerpt' => '', 'post_content' => 'Folder description' ),
    30 => array( 'post_type' => 'sc_archive_accession', 'post_status' => 'publish', 'post_title' => 'Accession 2026-01', 'post_excerpt' => '', 'post_content' => '' ),
    40 => array( 'post_type' => 'sc_archive_disposition', 'post_status' => 'publish', 'post_title' => 'Review Series One', 'post_excerpt' => '', 'post_content' => 'Review after project closure.' ),
);
$GLOBALS['meta'] = array(
    10 => array(
        '_sc_archive_collection_uuid' => '11111111-2222-4333-8444-555555555555',
        '_sc_archive_identifier' => 'SC-ARC-001',
        '_sc_archive_institution' => 'Sustainable Catalyst',
        '_sc_archive_department' => 'Knowledge Library',
        '_sc_archive_creator' => 'Content Catalyst LLC',
        '_sc_archive_date_start' => '2025',
        '_sc_archive_date_end' => '2026',
        '_sc_archive_extent' => '2 series; 3 GB',
        '_sc_archive_languages' => 'English; Urdu',
        '_sc_archive_scope_content' => 'Institutional project records.',
        '_sc_archive_arrangement' => 'Arranged in two series.',
        '_sc_archive_provenance' => 'Transferred from platform repositories.',
        '_sc_archive_rights' => 'Rights retained by Content Catalyst LLC.',
        '_sc_archive_status' => 'published',
        '_sc_archive_access_level' => 'public',
        '_sc_archive_finding_aid_public' => '1',
        '_sc_archive_retention_class' => 'permanent',
        '_sc_archive_retention_years' => 0,
        '_sc_archive_retention_trigger' => 'Project closure',
        '_sc_archive_retention_review' => '2027-07-15',
        '_sc_archive_legal_hold' => '0',
    ),
    20 => array(
        '_sc_component_collection_id' => 10,
        '_sc_component_parent_id' => 0,
        '_sc_component_level' => 'series',
        '_sc_archive_identifier' => 'SC-ARC-001-S1',
        '_sc_component_order' => 1,
        '_sc_archive_access_level' => 'public',
        '_sc_component_preservation_status' => 'monitor',
        '_sc_component_digital_objects' => array(
            array(
                'object_id' => 'obj-1',
                'label' => 'Preservation PDF',
                'uri' => 'https://example.org/file.pdf',
                'media_type' => 'application/pdf',
                'bytes' => 1000,
                'checksum' => 'abcdef',
                'checksum_algorithm' => 'sha256',
                'preservation_status' => 'stable',
            ),
            array(
                'object_id' => 'obj-2',
                'label' => 'Original binary',
                'uri' => 'https://example.org/file.bin',
                'media_type' => 'application/octet-stream',
                'bytes' => 2000,
                'checksum' => '',
                'checksum_algorithm' => 'sha256',
                'preservation_status' => 'at-risk',
            ),
        ),
    ),
    21 => array(
        '_sc_component_collection_id' => 10,
        '_sc_component_parent_id' => 20,
        '_sc_component_level' => 'folder',
        '_sc_archive_identifier' => 'SC-ARC-001-S1-F1',
        '_sc_component_order' => 1,
        '_sc_archive_access_level' => 'public',
        '_sc_component_preservation_status' => 'stable',
        '_sc_component_digital_objects' => array(),
    ),
    30 => array(
        '_sc_accession_collection_id' => 10,
        '_sc_accession_number' => '2026-01',
        '_sc_accession_date' => '2026-07-15',
        '_sc_accession_method' => 'transfer',
        '_sc_accession_source' => 'Research Platform',
        '_sc_accession_donor' => '',
        '_sc_accession_agreement' => 'Institutional transfer agreement.',
        '_sc_accession_extent' => '3 GB',
        '_sc_accession_restrictions' => 'None.',
        '_sc_accession_processing_status' => 'cataloged',
        '_sc_accession_custody_history' => array(
            array( 'event_id' => 'custody-1', 'date' => '2026-07-15', 'from' => 'Research Platform', 'to' => 'Knowledge Library', 'method' => 'Transfer', 'note' => 'Initial custody.' ),
        ),
    ),
    40 => array(
        '_sc_disposition_collection_id' => 10,
        '_sc_disposition_component_id' => 20,
        '_sc_disposition_action' => 'review',
        '_sc_disposition_reason' => 'Review after project closure.',
        '_sc_disposition_status' => 'proposed',
        '_sc_disposition_due_date' => '2027-07-15',
        '_sc_disposition_history' => array( array( 'event' => 'created' ) ),
    ),
);
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();

class WP_Error {
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_rest_route() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) { return 'Y-m-d' === $type ? '2026-07-15' : '2026-07-15 13:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/archive/' . $id; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_post_modified_time() { return '2026-07-15 13:00:00'; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function get_posts( $args ) {
    $ids = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) continue;
        if ( isset( $args['post__not_in'] ) && in_array( $id, $args['post__not_in'], true ) ) continue;
        if ( isset( $args['meta_key'] ) ) {
            $value = $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? null;
            if ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) continue;
        }
        $ids[] = $id;
    }
    return $ids;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'; }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function __( $text, $domain = null ) { return $text; }
function is_singular() { return false; }
function get_queried_object_id() { return 0; }
function get_page_by_path() { return null; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-institutional-collections-archives.php';

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

$reflection = new ReflectionClass( 'SC_Library_Institutional_Collections_Archives' );

$date = $reflection->getMethod( 'sanitize_date' );
$date->setAccessible( true );
assert_same( '2026-07-15', $date->invoke( null, '2026-07-15' ), 'Valid date retained' );
assert_same( '', $date->invoke( null, '2026-02-31' ), 'Invalid date rejected' );

$allowed = $reflection->getMethod( 'allowed_value' );
$allowed->setAccessible( true );
assert_same( 'restricted', $allowed->invoke( null, 'restricted', SC_Library_Institutional_Collections_Archives::access_levels(), 'public' ), 'Allowed access retained' );
assert_same( 'public', $allowed->invoke( null, 'invalid', SC_Library_Institutional_Collections_Archives::access_levels(), 'public' ), 'Invalid access falls back' );

$ids = $reflection->getMethod( 'id_list' );
$ids->setAccessible( true );
assert_same( array( 1, 2, 3 ), $ids->invoke( null, array( 1, '2', 2, 0, -3 ) ), 'ID normalization' );

$text_list = $reflection->getMethod( 'text_list' );
$text_list->setAccessible( true );
assert_same( array( 'English', 'Urdu', 'French' ), $text_list->invoke( null, "English; Urdu\nFrench" ), 'Language list normalization' );

$sanitize_objects = $reflection->getMethod( 'sanitize_digital_objects' );
$sanitize_objects->setAccessible( true );
$objects = $sanitize_objects->invoke(
    null,
    array(
        array( 'label' => ' File ', 'uri' => 'https://example.org/a.pdf', 'media_type' => 'application/pdf', 'bytes' => '99', 'checksum' => 'AB-CD', 'checksum_algorithm' => 'sha256', 'preservation_status' => 'stable' ),
        array( 'label' => '', 'uri' => '' ),
    )
);
assert_same( 1, count( $objects ), 'Empty digital object removed' );
assert_same( 'File', $objects[0]['label'], 'Digital object label sanitized' );
assert_same( 99, $objects[0]['bytes'], 'Digital object bytes normalized' );
assert_same( 'abcd', $objects[0]['checksum'], 'Checksum normalized' );
assert_same( 'sha256', $objects[0]['checksum_algorithm'], 'Checksum algorithm retained' );
assert_same( 'stable', $objects[0]['preservation_status'], 'Preservation state retained' );

$sanitize_custody = $reflection->getMethod( 'sanitize_custody_events' );
$sanitize_custody->setAccessible( true );
$custody = $sanitize_custody->invoke(
    null,
    array(
        array( 'date' => '2026-07-16', 'from' => 'B', 'to' => 'C', 'method' => 'Transfer', 'note' => 'Second' ),
        array( 'date' => '2026-07-15', 'from' => 'A', 'to' => 'B', 'method' => 'Transfer', 'note' => 'First' ),
    )
);
assert_same( 2, count( $custody ), 'Custody events retained' );
assert_same( '2026-07-15', $custody[0]['date'], 'Custody events sorted by date' );
assert_true( ! empty( $custody[0]['event_id'] ), 'Custody event receives UUID' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.6.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 3, count( $migration['steps'] ), 'Three migration stages' );

$collection = SC_Library_Institutional_Collections_Archives::collection_data( 10, true );
assert_same( 'sc-library-institutional-collection/1.0', $collection['schema'], 'Collection schema' );
assert_same( 'SC-ARC-001', $collection['identifier'], 'Collection identifier' );
assert_same( array( 'English', 'Urdu' ), $collection['languages'], 'Collection languages' );
assert_same( 2, $collection['component_count'], 'Collection component count' );
assert_same( 'permanent', $collection['retention']['class'], 'Collection retention class' );
assert_same( 1, count( $collection['accessions'] ), 'Private collection includes accessions' );
assert_same( 1, count( $collection['dispositions'] ), 'Private collection includes dispositions' );

$component = SC_Library_Institutional_Collections_Archives::component_data( 20, true );
assert_same( 'series', $component['level'], 'Component level' );
assert_same( 2, count( $component['digital_objects'] ), 'Component digital objects' );

$tree = SC_Library_Institutional_Collections_Archives::component_tree( 10, true );
assert_same( 1, count( $tree ), 'One root component' );
assert_same( 1, count( $tree[0]['children'] ), 'One child component' );

$aid = SC_Library_Institutional_Collections_Archives::finding_aid( 10, false );
assert_same( 'sc-library-finding-aid/1.0', $aid['schema'], 'Finding aid schema' );
assert_same( 1, count( $aid['hierarchy'] ), 'Finding aid hierarchy' );

$audit = SC_Library_Institutional_Collections_Archives::run_preservation_audit( 10, false );
assert_same( 'sc-library-preservation-audit/1.0', $audit['schema'], 'Preservation schema' );
assert_same( 2, $audit['digital_objects'], 'Preservation object count' );
assert_same( 1, $audit['missing_checksums'], 'Missing checksum count' );
assert_same( 1, $audit['at_risk_objects'], 'At-risk object count' );
assert_true( $audit['score'] < 100, 'Preservation deductions applied' );

$accession = SC_Library_Institutional_Collections_Archives::accession_data( 30, true );
assert_same( '2026-01', $accession['accession_number'], 'Accession number' );
assert_same( 1, count( $accession['custody_history'] ), 'Custody history serialized' );

$disposition = SC_Library_Institutional_Collections_Archives::disposition_data( 40 );
assert_same( 'review', $disposition['action'], 'Disposition action' );
assert_same( 'proposed', $disposition['status'], 'Disposition status' );

echo "Institutional collections and archives fixture checks passed: 34\n";
