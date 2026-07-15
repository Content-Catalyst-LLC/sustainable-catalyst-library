<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['posts'] = array(
    100 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Governed Project', 'post_content' => 'Project body' ),
    200 => array( 'post_type' => 'sc_quality_review', 'post_status' => 'publish', 'post_title' => 'Methodology Review', 'post_content' => 'Reviewed methods.' ),
    201 => array( 'post_type' => 'sc_quality_issue', 'post_status' => 'publish', 'post_title' => 'Evidence Gap', 'post_content' => 'A material evidence gap.' ),
    300 => array( 'post_type' => 'sc_research_policy', 'post_status' => 'publish', 'post_title' => 'Evidence Policy', 'post_content' => 'Policy text.' ),
);
$GLOBALS['meta'] = array(
    100 => array(
        '_sc_project_visibility' => 'public',
        '_sc_quality_governance_profile' => 'high-assurance',
        '_sc_quality_gate' => 'quality-review',
        '_sc_quality_last_score' => 82,
        '_sc_quality_public_summary' => '1',
        '_sc_quality_policy_ids' => array( 300 ),
        '_sc_quality_review_ids' => array( 200 ),
        '_sc_quality_issue_ids' => array( 201 ),
        '_sc_quality_last_evaluation' => array(
            'schema' => 'sc-library-research-quality/1.0',
            'project_id' => 100,
            'project_title' => 'Governed Project',
            'profile' => 'high-assurance',
            'profile_label' => 'High-assurance research',
            'gate' => 'quality-review',
            'gate_label' => 'Quality review',
            'score' => 82,
            'earned_points' => 82,
            'maximum_points' => 100,
            'status' => 'conditionally-ready',
            'status_label' => 'Conditionally ready',
            'dimensions' => array(
                'research-design' => array( 'label' => 'Research design', 'score' => 13, 'maximum' => 15, 'status' => 'strong' ),
                'sources' => array( 'label' => 'Sources and citations', 'score' => 12, 'maximum' => 15, 'status' => 'adequate' ),
            ),
            'findings' => array(),
            'required_actions' => array( 'Complete publication review.' ),
            'issue_counts' => array( 'total' => 1, 'critical_open' => 0, 'high_open' => 1 ),
            'review_counts' => array( 'total' => 1, 'failed' => 0, 'pending' => 0 ),
            'evaluated_at' => '2026-07-15 13:00:00',
            'evaluated_by' => 7,
            'version' => '3.5.0',
        ),
    ),
    200 => array(
        '_sc_quality_project_id' => 100,
        '_sc_quality_domain' => 'methodology',
        '_sc_quality_review_outcome' => 'pass',
        '_sc_quality_record_status' => 'active',
        '_sc_quality_findings' => 'Methods are suitable.',
        '_sc_quality_required_actions' => '',
        '_sc_quality_reviewer_id' => 7,
        '_sc_quality_due_date' => '2026-07-20',
        '_sc_quality_completed_at' => '2026-07-15 12:00:00',
        '_sc_quality_record_history' => array( array( 'event' => 'created' ) ),
    ),
    201 => array(
        '_sc_quality_project_id' => 100,
        '_sc_quality_domain' => 'evidence',
        '_sc_quality_issue_severity' => 'high',
        '_sc_quality_record_status' => 'open',
        '_sc_quality_required_actions' => 'Add corroborating evidence.',
        '_sc_quality_due_date' => '2026-07-25',
        '_sc_quality_exception' => '1',
        '_sc_quality_exception_expiry' => '2026-08-01',
        '_sc_quality_exception_approver' => 8,
        '_sc_quality_record_history' => array( array( 'event' => 'created' ) ),
    ),
    300 => array(
        '_sc_policy_domain' => 'evidence',
        '_sc_policy_version' => '1.2',
        '_sc_policy_status' => 'active',
        '_sc_policy_required_gate' => 'quality-review',
        '_sc_policy_public' => '1',
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

class SC_Library_Citation_Source_Manager {
    const PROJECT_POST_TYPE = 'sc_research_project';
    const SOURCE_POST_TYPE = 'sc_research_source';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
    const META_VERIFIED = '_sc_source_metadata_verified';
    const META_PEER_REVIEWED = '_sc_source_peer_reviewed';
    const META_PROVENANCE = '_sc_source_metadata_provenance';
}
class SC_Library_Connected_Research_Environment {
    const META_RESEARCH_QUESTION = '_sc_project_research_question';
    const META_OBJECTIVES = '_sc_project_objectives';
    const META_METHODS = '_sc_project_methods';
    const META_SCOPE = '_sc_project_scope';
    const META_DOCUMENT_IDS = '_sc_project_document_ids';
    const META_ACTIVITY = '_sc_project_activity';
    const META_SNAPSHOTS = '_sc_project_bibliography_snapshots';
    public static function source_entries() { return array(); }
}
class SC_Library_Evidence_Claim_Linking {
    const CLAIM_POST_TYPE = 'sc_research_claim';
    const NOTE_POST_TYPE = 'sc_evidence_note';
    const META_CLAIM_PROJECT_IDS = '_sc_claim_project_ids';
    const META_PROJECT_IDS = '_sc_evidence_project_ids';
    const META_CLAIM_EVIDENCE_IDS = '_sc_claim_evidence_ids';
}
class SC_Library_Source_Versioning_Integrity {
    public static function get_integrity_data() { return array( 'severity' => 'none' ); }
}
class SC_Library_Topics_Concepts_Relationships {
    const TOPIC_TAXONOMY = 'sc_library_topic';
    const META_CONCEPT_IDS = '_sc_library_concept_ids';
    const META_ENTITY_IDS = '_sc_library_entity_ids';
}
class SC_Library_Knowledge_Pathways_Article_Maps {
    const PATHWAY_POST_TYPE = 'sc_knowledge_path';
    const META_DERIVED_PROJECT_ID = '_sc_pathway_derived_project_id';
}
class SC_Library_Cross_Product_Research_Handoffs {
    const HANDOFF_POST_TYPE = 'sc_workspace_handoff';
    const META_PROJECT_ID = '_sc_handoff_project_id';
    public static function project_identity() { return array( 'uuid' => '11111111-2222-4333-8444-555555555555' ); }
}

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
function get_permalink( $id ) { return 'https://example.org/project/' . $id; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function has_excerpt() { return false; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function get_posts( $args ) {
    $ids = array();
    foreach ( $GLOBALS['posts'] as $id => $post ) {
        if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) continue;
        if ( isset( $args['post__in'] ) && ! in_array( $id, $args['post__in'], true ) ) continue;
        if ( isset( $args['meta_key'] ) ) {
            $value = $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? null;
            if ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) continue;
        }
        $ids[] = $id;
    }
    return $ids;
}
function wp_get_object_terms() { return array(); }
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
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function is_singular() { return false; }
function get_queried_object_id() { return 0; }
function get_page_by_path() { return null; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-research-quality-governance.php';

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

$reflection = new ReflectionClass( 'SC_Library_Research_Quality_Governance' );

$readiness = $reflection->getMethod( 'readiness_status' );
$readiness->setAccessible( true );
assert_same( 'not-ready', $readiness->invoke( null, 20, 0, 0 ), 'Score below 50 is not ready' );
assert_same( 'needs-review', $readiness->invoke( null, 60, 0, 0 ), 'Score below 70 needs review' );
assert_same( 'conditionally-ready', $readiness->invoke( null, 75, 0, 0 ), 'Score below 85 is conditionally ready' );
assert_same( 'ready', $readiness->invoke( null, 90, 0, 0 ), 'Score at 85 or higher is ready' );
assert_same( 'blocked', $readiness->invoke( null, 95, 1, 0 ), 'Critical issue blocks readiness' );
assert_same( 'blocked', $readiness->invoke( null, 95, 0, 1 ), 'Failed review blocks readiness' );

$dimension = $reflection->getMethod( 'dimension' );
$dimension->setAccessible( true );
$strong = $dimension->invoke( null, 'Example', 9, 10 );
assert_same( 'strong', $strong['status'], 'Strong dimension classification' );
$adequate = $dimension->invoke( null, 'Example', 7, 10 );
assert_same( 'adequate', $adequate['status'], 'Adequate dimension classification' );
$weak = $dimension->invoke( null, 'Example', 5, 10 );
assert_same( 'weak', $weak['status'], 'Weak dimension classification' );
$missing = $dimension->invoke( null, 'Example', 2, 10 );
assert_same( 'missing', $missing['status'], 'Missing dimension classification' );
$clamped = $dimension->invoke( null, 'Example', 99, 10 );
assert_same( 10, $clamped['score'], 'Dimension score is clamped' );

$date = $reflection->getMethod( 'sanitize_date' );
$date->setAccessible( true );
assert_same( '2026-07-15', $date->invoke( null, '2026-07-15' ), 'Valid date retained' );
assert_same( '', $date->invoke( null, '2026-02-31' ), 'Invalid date rejected' );

$allowed = $reflection->getMethod( 'allowed_value' );
$allowed->setAccessible( true );
assert_same( 'approved', $allowed->invoke( null, 'approved', SC_Library_Research_Quality_Governance::gate_options(), 'draft' ), 'Allowed gate retained' );
assert_same( 'draft', $allowed->invoke( null, 'invalid', SC_Library_Research_Quality_Governance::gate_options(), 'draft' ), 'Invalid gate falls back' );

$ids = $reflection->getMethod( 'id_list' );
$ids->setAccessible( true );
assert_same( array( 1, 2, 3 ), $ids->invoke( null, array( 1, '2', 2, 0, -3 ) ), 'ID normalization' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.5.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts at zero' );

$review = SC_Library_Research_Quality_Governance::review_data( 200, true );
assert_same( 'methodology', $review['domain'], 'Review domain serialized' );
assert_same( 'pass', $review['outcome'], 'Review outcome serialized' );
assert_same( 7, $review['reviewer_id'], 'Reviewer ID available privately' );
assert_same( 'Methods are suitable.', $review['findings'], 'Review findings available privately' );

$issue = SC_Library_Research_Quality_Governance::issue_data( 201, true );
assert_same( 'high', $issue['severity'], 'Issue severity serialized' );
assert_same( 'open', $issue['status'], 'Issue status serialized' );
assert_true( $issue['exception'], 'Issue exception flag serialized' );
assert_same( '2026-08-01', $issue['exception_expiry'], 'Exception expiry available privately' );

$transparency = SC_Library_Research_Quality_Governance::project_transparency( 100, false );
assert_same( 'sc-library-research-transparency/1.0', $transparency['schema'], 'Transparency schema' );
assert_same( 82, $transparency['quality_score'], 'Transparency score' );
assert_same( 'conditionally-ready', $transparency['readiness_status'], 'Transparency readiness status' );
assert_same( 1, count( $transparency['policies'] ), 'Public policy included' );
assert_same( 1, count( $transparency['reviews'] ), 'Review summary included' );
assert_same( 1, count( $transparency['issues'] ), 'Issue summary included' );
assert_true( ! isset( $transparency['reviews'][0]['findings'] ), 'Private findings omitted from transparency' );

echo "Research quality and governance fixture checks passed: 32\n";
