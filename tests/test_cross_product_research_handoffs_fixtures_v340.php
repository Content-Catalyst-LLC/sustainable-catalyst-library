<?php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['posts'] = array(
    100 => array( 'post_type' => 'sc_research_project', 'post_status' => 'publish', 'post_title' => 'Climate Systems Project', 'post_name' => 'climate-systems-project', 'post_excerpt' => 'Project summary.', 'post_content' => 'Project description.' ),
    200 => array( 'post_type' => 'sc_workspace_handoff', 'post_status' => 'private', 'post_title' => 'Workbench Handoff', 'post_name' => 'workbench-handoff', 'post_excerpt' => '', 'post_content' => '' ),
);
$GLOBALS['meta'] = array(
    100 => array( '_sc_project_visibility' => 'public' ),
    200 => array(
        '_sc_handoff_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        '_sc_handoff_project_id' => 100,
        '_sc_handoff_project_uuid' => '11111111-2222-4333-8444-555555555555',
        '_sc_handoff_source_product' => 'knowledge-library',
        '_sc_handoff_target_product' => 'workbench',
        '_sc_handoff_type' => 'calculation-context',
        '_sc_handoff_direction' => 'outbound',
        '_sc_handoff_status' => 'sent',
        '_sc_handoff_created_at' => '2026-07-15 12:00:00',
        '_sc_handoff_created_by' => 7,
        '_sc_handoff_updated_at' => '2026-07-15 12:00:00',
        '_sc_handoff_updated_by' => 7,
    ),
);
$GLOBALS['options'] = array();
$GLOBALS['current_user_can'] = true;
$GLOBALS['uuid_counter'] = 0;

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_message() { return $this->message; }
    public function get_error_data( $key = '' ) { return $key && is_array( $this->data ) ? ( $this->data[ $key ] ?? null ) : $this->data; }
}
class WP_Post {}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}
class SC_Library_Citation_Source_Manager {
    const PROJECT_POST_TYPE = 'sc_research_project';
    const META_PROJECT_VISIBILITY = '_sc_project_visibility';
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = null ) { return $text; }
function home_url( $path = '/' ) { return 'https://example.org' . $path; }
function rest_url( $path = '' ) { return 'https://example.org/wp-json/' . ltrim( $path, '/' ); }
function admin_url( $path = '' ) { return 'https://example.org/wp-admin/' . ltrim( $path, '/' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function apply_filters( $tag, $value ) { return $value; }
function do_action() {}
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/project/' . ( is_object( $id ) ? $id->ID : $id ); }
function get_edit_post_link( $id, $context = '' ) { return 'https://example.org/wp-admin/post.php?post=' . (int) $id; }
function get_post_modified_time() { return '2026-07-15T12:00:00Z'; }
function get_post( $id ) {
    if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return null;
    $post = new WP_Post();
    $post->ID = $id;
    foreach ( $GLOBALS['posts'][ $id ] as $key => $value ) $post->{$key} = $value;
    return $post;
}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function current_time( $type = '' ) { return '2026-07-15 12:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can() { return $GLOBALS['current_user_can']; }
function wp_generate_uuid4() {
    $GLOBALS['uuid_counter']++;
    return 1 === $GLOBALS['uuid_counter'] ? '11111111-2222-4333-8444-555555555555' : '66666666-7777-4888-8999-aaaaaaaaaaaa';
}
function wp_generate_password( $length = 12 ) { return str_repeat( 'T', $length ); }
function wp_salt( $scheme = 'auth' ) { return 'fixture-salt-' . $scheme; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_nonce_url( $url ) { return $url . '&_nonce=fixture'; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function is_user_logged_in() { return true; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-cross-product-research-handoffs.php';

function assert_true( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAILED: {$label}\n" ); exit( 1 ); } }
function assert_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

$registry = SC_Library_Cross_Product_Research_Handoffs::base_product_registry();
assert_same( 5, count( $registry ), 'Five first-party products' );
assert_true( isset( $registry['research-lab']['types']['experiment-brief'] ), 'Research Lab experiment contract' );
assert_true( isset( $registry['workbench']['types']['calculation-context'] ), 'Workbench calculation contract' );
assert_true( isset( $registry['decision-studio']['types']['evidence-packet'] ), 'Decision Studio evidence contract' );
assert_true( isset( $registry['research-librarian']['types']['research-context'] ), 'Research Librarian context contract' );
assert_true( isset( $registry['site-intelligence']['types']['dataset-reference'] ), 'Site Intelligence dataset contract' );

assert_true( SC_Library_Cross_Product_Research_Handoffs::can_transition( 'draft', 'ready' ), 'Draft can become ready' );
assert_true( SC_Library_Cross_Product_Research_Handoffs::can_transition( 'sent', 'completed' ), 'Sent can become completed' );
assert_true( ! SC_Library_Cross_Product_Research_Handoffs::can_transition( 'completed', 'draft' ), 'Completed cannot become draft' );
assert_true( SC_Library_Cross_Product_Research_Handoffs::can_transition( 'failed', 'ready' ), 'Failed can be retried' );
assert_true( in_array( 'completed', SC_Library_Cross_Product_Research_Handoffs::allowed_token_statuses(), true ), 'Token can complete handoff' );
assert_true( ! in_array( 'archived', SC_Library_Cross_Product_Research_Handoffs::allowed_token_statuses(), true ), 'Token cannot archive handoff' );

$identity = SC_Library_Cross_Product_Research_Handoffs::ensure_project_identity( 100 );
assert_same( 'sc-platform-project-identity/1.0', $identity['schema'], 'Identity schema' );
assert_same( '11111111-2222-4333-8444-555555555555', $identity['uuid'], 'Stable project UUID created' );
assert_same( 'urn:sc:research-project:11111111-2222-4333-8444-555555555555', $identity['urn'], 'Stable project URN' );
assert_same( $identity['uuid'], get_post_meta( 100, '_sc_platform_project_uuid', true ), 'UUID persisted' );
$identity_again = SC_Library_Cross_Product_Research_Handoffs::ensure_project_identity( 100 );
assert_same( $identity['uuid'], $identity_again['uuid'], 'Identity remains stable' );

$sections = SC_Library_Cross_Product_Research_Handoffs::sanitize_sections( array( 'project', 'evidence', 'invalid', 'project' ) );
assert_same( array( 'project', 'evidence' ), $sections, 'Bundle sections sanitized and deduplicated' );

$request = SC_Library_Cross_Product_Research_Handoffs::sanitize_request(
    array(
        'instructions' => '<b>Build model</b>',
        'country_codes' => 'us, KEN, invalid1',
        'selected_node_keys' => array( 'document:1', 'bad', 'source:2', 'document:1' ),
        'parameters' => array( 'discount_rate' => '0.05', 'mode' => '<i>baseline</i>' ),
        'dataset_references' => array(
            array( 'provider' => 'World Bank', 'id' => 'SP.POP.TOTL', 'title' => 'Population', 'url' => 'https://data.example/pop' ),
            array( 'title' => '' ),
        ),
    )
);
assert_same( 'Build model', $request['instructions'], 'Instructions sanitized' );
assert_same( array( 'US', 'KEN' ), $request['country_codes'], 'Country codes normalized' );
assert_same( array( 'document:1', 'source:2' ), $request['selected_node_keys'], 'Node keys normalized' );
assert_same( 1, count( $request['dataset_references'] ), 'Empty dataset reference removed' );
assert_same( 'baseline', $request['parameters']['mode'], 'Parameter sanitized' );

$workbench = SC_Library_Cross_Product_Research_Handoffs::build_adapter_payload(
    'workbench',
    'calculation-context',
    array( 'title' => 'Project', 'research_question' => 'What is the result?', 'methods' => array( 'Monte Carlo' ) ),
    array(),
    array_merge( $request, array( 'assumptions' => array( 'Constant rate' ), 'units' => 'kg', 'requested_output' => 'Chart' ) )
);
assert_same( 'sc-platform-handoff/workbench/1.0', $workbench['schema'], 'Workbench adapter schema' );
assert_same( 'What is the result?', $workbench['question'], 'Workbench question' );
assert_same( 'kg', $workbench['calculation_context']['units'], 'Workbench units' );

$decision = SC_Library_Cross_Product_Research_Handoffs::build_adapter_payload(
    'decision-studio',
    'evidence-packet',
    array( 'title' => 'Project' ),
    array( 'evidence_packet' => array( 'claims' => array( 1 ) ), 'source_integrity' => array( 'affected_count' => 1 ) ),
    array_merge( $request, array( 'criteria' => array( 'Cost' ), 'assumptions' => array(), 'scenarios' => array( 'Baseline' ) ) )
);
assert_same( 1, count( $decision['decision_context']['evidence_packet']['claims'] ), 'Decision evidence included' );
assert_same( 'Cost', $decision['decision_context']['criteria'][0], 'Decision criteria included' );

$bundle = array(
    'schema' => 'sc-platform-research-bundle/1.0',
    'bundle_id' => 'bundle-1',
    'handoff_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'source_product' => 'knowledge-library',
    'target_product' => 'workbench',
    'handoff_type' => 'calculation-context',
    'target_contract' => 'sc-platform-handoff/workbench/1.0',
    'project_identity' => $identity,
    'sections' => array( 'project' ),
    'request' => $request,
    'adapter' => $workbench,
    'context' => array( 'project' => array( 'title' => 'Climate Systems Project' ) ),
    'return' => array( 'project_url' => 'https://example.org/project/100', 'rest_route' => 'https://example.org/wp-json/status' ),
    'provenance' => array( 'generated_at' => '2026-07-15 12:00:00' ),
);
assert_true( SC_Library_Cross_Product_Research_Handoffs::validate_bundle( $bundle ), 'Valid bundle accepted' );
assert_same( 64, strlen( SC_Library_Cross_Product_Research_Handoffs::bundle_checksum( $bundle ) ), 'SHA-256 checksum length' );
$files = SC_Library_Cross_Product_Research_Handoffs::bundle_files( $bundle );
assert_true( isset( $files['manifest.json'] ), 'Bundle manifest export' );
assert_true( isset( $files['handoff.json'] ), 'Full handoff export' );
assert_true( isset( $files['project.json'] ), 'Project section export' );
assert_true( isset( $files['adapter.json'] ), 'Adapter export' );
$markdown = SC_Library_Cross_Product_Research_Handoffs::bundle_markdown( $bundle );
assert_true( false !== strpos( $markdown, 'Cross-Product Research Bundle' ), 'Markdown heading' );
assert_true( false !== strpos( $markdown, $identity['urn'] ), 'Markdown project URN' );

$token = str_repeat( 'T', 48 );
update_post_meta( 200, '_sc_handoff_token_hash', SC_Library_Cross_Product_Research_Handoffs::token_hash( $token ) );
update_post_meta( 200, '_sc_handoff_token_expires', time() + 3600 );
assert_true( SC_Library_Cross_Product_Research_Handoffs::validate_delivery_token( 200, $token ), 'Valid delivery token' );
assert_true( ! SC_Library_Cross_Product_Research_Handoffs::validate_delivery_token( 200, 'wrong' ), 'Invalid delivery token rejected' );
update_post_meta( 200, '_sc_handoff_token_expires', time() - 1 );
assert_true( ! SC_Library_Cross_Product_Research_Handoffs::validate_delivery_token( 200, $token ), 'Expired delivery token rejected' );

SC_Library_Cross_Product_Research_Handoffs::append_history( 200, 'sent', 'opened', 'Opened by Workbench', array( 'actor_product' => 'workbench' ) );
$history = get_post_meta( 200, '_sc_handoff_history', true );
assert_same( 1, count( $history ), 'History event persisted' );
assert_same( 'workbench', $history[0]['actor_product'], 'History actor product' );
assert_same( 'opened', $history[0]['to_status'], 'History destination status' );

$reflection = new ReflectionClass( 'SC_Library_Cross_Product_Research_Handoffs' );
$default_state = $reflection->getMethod( 'default_migration_state' );
$default_state->setAccessible( true );
$state = $default_state->invoke( null );
assert_same( '3.4.0', $state['version'], 'Migration state version' );
assert_same( 'pending', $state['status'], 'Migration starts pending' );
assert_same( 0, $state['cursor'], 'Migration cursor starts at zero' );

echo "Cross-product handoff fixture checks passed: 41\n";
