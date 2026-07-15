<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['next_id'] = 100;
$GLOBALS['posts'] = array(
    1 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Planetary Boundaries Review Draft', 'post_content' => 'A documented research text with methods, evidence, citations, and limitations.', 'post_name' => 'planetary-boundaries-review-draft', 'post_modified' => '2026-07-15 10:00:00' ),
    2 => array( 'post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'post_title' => 'Climate Governance Review Draft', 'post_content' => 'A second documented research text with governance evidence and publication notes.', 'post_name' => 'climate-governance-review-draft', 'post_modified' => '2026-07-15 11:00:00' ),
    10 => array( 'post_type' => 'sc_review_cycle', 'post_status' => 'publish', 'post_title' => 'Editorial and Evidence Review', 'post_content' => 'Structured collaborative review.', 'post_name' => 'editorial-evidence-review', 'post_modified' => '2026-07-15 12:00:00' ),
    20 => array( 'post_type' => 'sc_pub_package', 'post_status' => 'publish', 'post_title' => 'Research Release Package', 'post_content' => 'Publication package.', 'post_name' => 'research-release-package', 'post_modified' => '2026-07-15 12:30:00' ),
);
$GLOBALS['meta'] = array(
    1 => array(
        '_sc_document_raw_text' => 'A documented research text with methods, evidence, citations, and limitations.',
        '_sc_document_intelligence_source_hash' => 'intel-hash-1',
    ),
    2 => array(
        '_sc_document_raw_text' => 'A second documented research text with governance evidence and publication notes.',
        '_sc_document_intelligence_source_hash' => 'intel-hash-2',
    ),
    10 => array(
        '_sc_review_uuid' => '11111111-2222-4333-8444-555555555555',
        '_sc_review_status' => 'in-review',
        '_sc_review_type' => 'evidence',
        '_sc_review_document_ids' => array( 1, 2 ),
        '_sc_review_project_ids' => array( 77 ),
        '_sc_review_assignments' => array(
            array(
                'assignment_id' => 'assignment-1',
                'user_id' => 7,
                'email' => 'reviewer@example.org',
                'display_name' => 'Reviewer One',
                'role' => 'approver',
                'review_type' => 'evidence',
                'status' => 'responded',
                'decision' => 'approve',
                'decision_note' => 'Evidence reviewed.',
                'conflict' => false,
                'conflict_note' => '',
                'invited_at' => '2026-07-15 09:00:00',
                'responded_at' => '2026-07-15 12:00:00',
            ),
        ),
        '_sc_review_gate' => 'evidence-review',
        '_sc_review_required_approvals' => 1,
        '_sc_review_due_date' => '2026-07-30',
        '_sc_review_started_at' => '2026-07-15 09:00:00',
        '_sc_review_public' => '1',
        '_sc_review_public_summary' => 'The release received an evidence and editorial review.',
        '_sc_review_coi_policy' => 'Reviewers must disclose relevant conflicts.',
        '_sc_review_history' => array(),
    ),
    20 => array(
        '_sc_pub_package_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        '_sc_pub_package_status' => 'review',
        '_sc_pub_package_document_ids' => array( 1, 2 ),
        '_sc_pub_package_project_ids' => array( 77 ),
        '_sc_pub_package_review_ids' => array( 10 ),
        '_sc_pub_package_version' => '1.0.0',
        '_sc_pub_package_release_notes' => 'This release publishes the reviewed research documents and records the completed review.',
        '_sc_pub_package_license' => 'CC BY 4.0',
        '_sc_pub_package_doi' => '10.1234/example.release',
        '_sc_pub_package_canonical_url' => 'https://example.org/research/release',
        '_sc_pub_package_embargo_until' => '',
        '_sc_pub_package_publish_at' => '',
        '_sc_pub_package_public' => '1',
        '_sc_pub_package_history' => array(),
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
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}

class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_PDF_To_Document { const META_RAW_TEXT = '_sc_document_raw_text'; }

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_rest_route() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) {
    if ( 'Y-m-d' === $type ) return '2026-07-15';
    if ( 'timestamp' === $type ) return strtotime( '2026-07-15 14:00:00' );
    return '2026-07-15 14:00:00';
}
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_post_modified_time( $format, $gmt, $id ) { return $GLOBALS['posts'][ $id ]['post_modified'] ?? ''; }
function get_post_time( $format, $gmt, $id ) { return '2026-07-15 14:00:00'; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_insert_post( $args, $wp_error = false ) {
    $id = ++$GLOBALS['next_id'];
    $GLOBALS['posts'][ $id ] = array(
        'post_type' => $args['post_type'],
        'post_status' => $args['post_status'],
        'post_title' => $args['post_title'] ?? '',
        'post_content' => $args['post_content'] ?? '',
        'post_name' => sanitize_title( $args['post_title'] ?? '' ),
        'post_modified' => '2026-07-15 14:00:00',
    );
    $GLOBALS['meta'][ $id ] = array();
    return $id;
}
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ $id ], $GLOBALS['meta'][ $id ] ); return true; }
function get_posts( $args ) {
    $ids = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) continue;
        if ( isset( $args['post_status'] ) && ! in_array( $post['post_status'], (array) $args['post_status'], true ) ) continue;
        if ( isset( $args['meta_key'] ) ) {
            $value = $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? null;
            if ( isset( $args['meta_compare'] ) && 'LIKE' === $args['meta_compare'] ) {
                if ( false === strpos( serialize( $value ), (string) $args['meta_value'] ) ) continue;
            } elseif ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) continue;
        }
        $ids[] = $id;
    }
    return $ids;
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff'; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_trim_words( $text, $number = 55, $more = null ) { $words = preg_split( '/\s+/', trim( $text ) ); return implode( ' ', array_slice( $words, 0, $number ) ) . ( count( $words ) > $number ? ( $more ?? '…' ) : '' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function __( $text, $domain = null ) { return $text; }
function get_page_by_path() { return null; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-collaborative-review-publishing.php';

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

$reflection = new ReflectionClass( 'SC_Library_Collaborative_Review_Publishing' );

$allowed = $reflection->getMethod( 'allowed_value' );
$allowed->setAccessible( true );
assert_same( 'methodology', $allowed->invoke( null, 'methodology', SC_Library_Collaborative_Review_Publishing::review_types(), 'editorial' ), 'Allowed review type retained' );
assert_same( 'editorial', $allowed->invoke( null, 'invalid', SC_Library_Collaborative_Review_Publishing::review_types(), 'editorial' ), 'Invalid review type falls back' );

$date = $reflection->getMethod( 'sanitize_date' );
$date->setAccessible( true );
assert_same( '2026-07-30', $date->invoke( null, '2026-07-30' ), 'Valid date retained' );
assert_same( '', $date->invoke( null, '2026-02-31' ), 'Invalid date rejected' );

$datetime = $reflection->getMethod( 'sanitize_datetime' );
$datetime->setAccessible( true );
assert_same( '2026-07-30 10:15:00', $datetime->invoke( null, '2026-07-30T10:15' ), 'Datetime normalized' );

$ids = $reflection->getMethod( 'id_list' );
$ids->setAccessible( true );
assert_same( array( 1, 2, 3 ), $ids->invoke( null, '1, 2 2, 3' ), 'ID list normalized' );

$assignments = SC_Library_Collaborative_Review_Publishing::sanitize_assignments(
    array(
        array( 'user_id' => 7, 'email' => 'reviewer@example.org', 'role' => 'approver', 'review_type' => 'evidence', 'decision' => 'approve', 'conflict' => false ),
        array( 'user_id' => 0, 'email' => '' ),
    )
);
assert_same( 1, count( $assignments ), 'Empty assignment removed' );
assert_same( 'approver', $assignments[0]['role'], 'Assignment role retained' );
assert_same( 'evidence', $assignments[0]['review_type'], 'Assignment review type retained' );
assert_same( 'approve', $assignments[0]['decision'], 'Assignment decision retained' );
assert_true( ! empty( $assignments[0]['assignment_id'] ), 'Assignment UUID created' );

$snapshots = SC_Library_Collaborative_Review_Publishing::build_snapshots( array( 1, 2 ) );
assert_same( 2, count( $snapshots ), 'Two document snapshots created' );
assert_same( 1, $snapshots[0]['document_id'], 'Snapshot document ID' );
assert_true( strlen( $snapshots[0]['content_hash'] ) === 64, 'Snapshot SHA-256 hash' );
assert_same( 'intel-hash-1', $snapshots[0]['intelligence_hash'], 'Intelligence hash retained' );
$GLOBALS['meta'][10]['_sc_review_snapshots'] = $snapshots;

$changes = SC_Library_Collaborative_Review_Publishing::detect_snapshot_changes( 10 );
assert_same( 'unchanged', $changes[0]['state'], 'Unchanged document detected' );
$GLOBALS['posts'][1]['post_content'] .= ' Revised after review.';
$GLOBALS['meta'][1]['_sc_document_raw_text'] .= ' Revised after review.';
$changes = SC_Library_Collaborative_Review_Publishing::detect_snapshot_changes( 10 );
assert_same( 'changed', $changes[0]['state'], 'Changed document detected' );
$GLOBALS['posts'][1]['post_content'] = 'A documented research text with methods, evidence, citations, and limitations.';
$GLOBALS['meta'][1]['_sc_document_raw_text'] = 'A documented research text with methods, evidence, citations, and limitations.';

$note = SC_Library_Collaborative_Review_Publishing::create_note(
    10,
    array( 'document_id' => 1, 'type' => 'required-change', 'severity' => 'medium', 'section' => 'Methods', 'body' => 'Clarify the method selection criteria.' )
);
assert_true( ! is_wp_error( $note ), 'Review note created' );
assert_same( 'sc-library-review-note/1.0', $note['schema'], 'Note schema' );
assert_same( 'required-change', $note['type'], 'Note type' );
assert_same( 'medium', $note['severity'], 'Note severity' );
assert_same( 'open', $note['status'], 'Note starts open' );

$resolved = SC_Library_Collaborative_Review_Publishing::resolve_note( $note['note_id'], 'Selection criteria were added.', 'resolved' );
assert_true( ! is_wp_error( $resolved ), 'Review note resolved' );
assert_same( 'resolved', $resolved['status'], 'Resolved status' );
assert_same( 'Selection criteria were added.', $resolved['resolution'], 'Resolution recorded' );

$readiness = SC_Library_Collaborative_Review_Publishing::evaluate_review( 10, true );
assert_same( 'sc-library-collaborative-review/1.0', $readiness['schema'], 'Review schema' );
assert_same( 1, $readiness['approval_count'], 'Approval counted' );
assert_same( 0, $readiness['open_note_count'], 'Resolved note not open' );
assert_same( 0, $readiness['changed_document_count'], 'Snapshots currently match' );
assert_true( $readiness['ready'], 'Review becomes ready' );
assert_same( 'approved', $readiness['status'], 'Review status approved' );

$review_data = SC_Library_Collaborative_Review_Publishing::review_data( 10, true );
assert_same( 10, $review_data['review_id'], 'Review data ID' );
assert_same( 2, count( $review_data['document_ids'] ), 'Review document links' );
assert_same( 1, count( $review_data['assignments'] ), 'Review assignments included' );
assert_true( count( $review_data['notes'] ) >= 1, 'Review notes included' );

$transparency = SC_Library_Collaborative_Review_Publishing::transparency_data( 10 );
assert_same( 'sc-library-review-transparency/1.0', $transparency['schema'], 'Transparency schema' );
assert_same( 1, $transparency['approval_count'], 'Transparency approval count' );
assert_true( ! isset( $transparency['assignments'] ), 'Transparency omits assignments' );

$package = SC_Library_Collaborative_Review_Publishing::evaluate_package( 20, true );
assert_same( 'sc-library-research-publication-package/1.0', $package['schema'], 'Publication package schema' );
assert_true( $package['score'] >= 90, 'Publication readiness score' );
assert_true( $package['ready'], 'Package ready' );
assert_same( 1, $package['approved_reviews'], 'Approved review counted' );
assert_same( 0, $package['blocked_reviews'], 'No blocked reviews' );
assert_same( 2, count( $package['manifest']['documents'] ), 'Manifest document count' );

$approved = SC_Library_Collaborative_Review_Publishing::approve_package( 20, 'Approved for publication.' );
assert_true( ! is_wp_error( $approved ), 'Package approved' );
assert_same( 'approved', $approved['status'], 'Package status approved' );
assert_same( 1, count( $approved['approvals'] ), 'Package approval record' );

$published = SC_Library_Collaborative_Review_Publishing::transition_package( 20, 'published', 'Release published.' );
assert_true( ! is_wp_error( $published ), 'Package published' );
assert_same( 'published', $published['status'], 'Package status published' );
assert_true( ! empty( $published['published_at'] ), 'Published timestamp recorded' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.8.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts at zero' );

echo "Collaborative Review and Research Publishing fixture checks passed: 51\n";
