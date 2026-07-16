<?php
define( 'ABSPATH', __DIR__ );

class WP_REST_Request {
    private $route;
    public function __construct( $route ) { $this->route = $route; }
    public function get_route() { return $this->route; }
}
class WP_REST_Response {
    private $data;
    public function __construct( $data ) { $this->data = $data; }
    public function get_data() { return $this->data; }
    public function set_data( $data ) { $this->data = $data; }
}
class WP_Error {}
function add_action() {}
function add_filter() {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can() { return true; }
function is_admin() { return false; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function esc_html_e() {}

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-discovery-interface-reliability.php';

function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}
function assert_true( $condition, $label ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAILED: {$label}\n" );
        exit( 1 );
    }
}

$reflection = new ReflectionClass( 'SC_Library_Discovery_Interface_Reliability' );
$decode = $reflection->getMethod( 'decode_ampersands' );
$decode->setAccessible( true );
$normalize = $reflection->getMethod( 'normalize_value' );
$normalize->setAccessible( true );

assert_same( 'Literature & Cultural Memory', $decode->invoke( null, 'Literature &amp; Cultural Memory' ), 'single encoded ampersand' );
assert_same( 'Technology & Systems Intelligence', $decode->invoke( null, 'Technology &amp;amp; Systems Intelligence' ), 'double encoded ampersand' );
assert_same( 'Research & Development', $decode->invoke( null, 'Research &#38; Development' ), 'decimal entity' );
assert_same( 'Research & Development', $decode->invoke( null, 'Research &#x26; Development' ), 'hex entity' );
assert_same( '&lt;strong&gt;', $decode->invoke( null, '&lt;strong&gt;' ), 'unrelated entities remain encoded' );

$payload = array(
    'title' => 'Literature &amp;amp; Cultural Memory',
    'url' => 'https://example.org/?a=1&amp;b=2',
    'nested' => array(
        'label' => 'Technology &amp; Systems Intelligence',
        'identifier' => 'domain&amp;identifier',
    ),
);
$normalized = $normalize->invoke( null, $payload );
assert_same( 'Literature & Cultural Memory', $normalized['title'], 'title normalized' );
assert_same( 'https://example.org/?a=1&amp;b=2', $normalized['url'], 'URL untouched' );
assert_same( 'Technology & Systems Intelligence', $normalized['nested']['label'], 'nested label normalized' );
assert_same( 'domain&amp;identifier', $normalized['nested']['identifier'], 'identifier untouched' );

$patch = new SC_Library_Discovery_Interface_Reliability();
$response = new WP_REST_Response( $payload );
$request = new WP_REST_Request( '/sustainable-catalyst/v1/library/discovery' );
$result = $patch->normalize_library_rest_response( $response, null, $request );
assert_same( 'Literature & Cultural Memory', $result->get_data()['title'], 'Library response normalized' );

$other = new WP_REST_Response( $payload );
$other_request = new WP_REST_Request( '/wp/v2/posts' );
$other_result = $patch->normalize_library_rest_response( $other, null, $other_request );
assert_same( 'Literature &amp;amp; Cultural Memory', $other_result->get_data()['title'], 'unrelated route untouched' );

assert_true( SC_Library_Discovery_Interface_Reliability::VERSION === '4.0.1', 'version constant' );
assert_true( SC_Library_Discovery_Interface_Reliability::SCHEMA === 'sc-library-discovery-interface-reliability/1.0', 'schema constant' );

echo "Discovery Interface Reliability fixture checks passed: 15\n";
