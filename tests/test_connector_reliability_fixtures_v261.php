<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['sc_options'] = array();
$GLOBALS['sc_transients'] = array();
$GLOBALS['sc_meta'] = array();
$GLOBALS['sc_posts'] = array(
    10 => array( 'post_type' => 'sc_research_source', 'post_title' => 'Local title', 'post_excerpt' => 'Local abstract' ),
    20 => array( 'post_type' => 'sc_library_profile', 'post_title' => 'Test Library', 'post_excerpt' => '' ),
);
$GLOBALS['sc_http_queue'] = array();
$GLOBALS['sc_http_calls'] = 0;

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
    public function get_error_data( $key = '' ) {
        if ( $key && is_array( $this->data ) ) {
            return $this->data[ $key ] ?? null;
        }
        return $this->data;
    }
}
class WP_REST_Server {
    const READABLE = 'GET';
    const CREATABLE = 'POST';
}
class WP_REST_Request {}
class WP_Post {}

class SC_Library_Citation_Source_Manager {
    const SOURCE_POST_TYPE = 'sc_research_source';
    const PROJECT_POST_TYPE = 'sc_research_project';
    const META_AUTHORS = '_authors';
    const META_ORGANIZATION = '_organization';
    const META_EDITORS = '_editors';
    const META_YEAR = '_year';
    const META_PUBLICATION_DATE = '_publication_date';
    const META_CONTAINER_TITLE = '_container';
    const META_PUBLISHER = '_publisher';
    const META_PLACE = '_place';
    const META_EDITION = '_edition';
    const META_VOLUME = '_volume';
    const META_ISSUE = '_issue';
    const META_PAGES = '_pages';
    const META_CHAPTER = '_chapter';
    const META_REPORT_NUMBER = '_report';
    const META_STANDARD_NUMBER = '_standard';
    const META_JURISDICTION = '_jurisdiction';
    const META_DOI = '_doi';
    const META_ISBN = '_isbn';
    const META_PMID = '_pmid';
    const META_URL = '_url';
    const META_ARCHIVE_URL = '_archive_url';
    const META_LANGUAGE = '_language';
    const META_FULL_TEXT_STATUS = '_full_text';
    const META_VERIFIED = '_verified';
    public static $rebuilt = array();
    public static function rebuild_source_indexes( $source_id ) { self::$rebuilt[] = $source_id; }
}
class SC_Library_Citation_Source_Reliability {
    public static $recalculated = array();
    public static function recalculate_reliability( $source_id ) { self::$recalculated[] = $source_id; }
}
class SC_Library_Scholarly_Library_Connectors {
    const API_NAMESPACE = 'sc-library/v1';
    const PROFILE_POST_TYPE = 'sc_library_profile';
    const CACHE_PREFIX = 'sc_lib_v260_';
    const META_ACCESS_LOCATIONS = '_access_locations';
    const META_CONNECTOR_LAST_CHECKED = '_last_checked';
    const META_IMPORT_FINGERPRINT = '_import_fingerprint';
    const META_PROFILE_HOMEPAGE = '_homepage';
    const META_PROFILE_CATALOG_TEMPLATE = '_catalog';
    const META_PROFILE_OPENURL_BASE = '_openurl';
    const META_PROFILE_ILL_URL = '_ill';
    const META_PROFILE_PROXY_PREFIX = '_proxy';
    const META_PROFILE_ENABLED = '_enabled';
}

function __( $text, $domain = null ) { return $text; }
function add_filter() {}
function add_action() {}
function register_rest_route() {}
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) {
    if ( is_array( $value ) ) { return ''; }
    return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function esc_url_raw( $value ) { return preg_match( '#^https://#', (string) $value ) ? (string) $value : ''; }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?: time() ); }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_rand( $min, $max ) { return $min; }
function wp_next_scheduled() { return false; }
function wp_schedule_event() { return true; }
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_slash( $value ) { return $value; }

function get_option( $key, $default = false ) { return $GLOBALS['sc_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['sc_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['sc_options'][ $key ] ); return true; }
function get_transient( $key ) { return $GLOBALS['sc_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['sc_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['sc_transients'][ $key ] ); return true; }

function get_post_meta( $post_id, $key, $single = true ) {
    return $GLOBALS['sc_meta'][ $post_id ][ $key ] ?? '';
}
function update_post_meta( $post_id, $key, $value ) {
    $GLOBALS['sc_meta'][ $post_id ][ $key ] = $value;
    return true;
}
function delete_post_meta( $post_id, $key ) {
    unset( $GLOBALS['sc_meta'][ $post_id ][ $key ] );
    return true;
}
function get_post_type( $post_id ) { return $GLOBALS['sc_posts'][ $post_id ]['post_type'] ?? ''; }
function get_the_title( $post_id ) { return $GLOBALS['sc_posts'][ $post_id ]['post_title'] ?? ''; }
function get_post_field( $field, $post_id ) { return $GLOBALS['sc_posts'][ $post_id ][ $field ] ?? ''; }
function wp_update_post( $args ) {
    $id = (int) $args['ID'];
    foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
        if ( array_key_exists( $field, $args ) ) {
            $GLOBALS['sc_posts'][ $id ][ $field ] = $args[ $field ];
        }
    }
    return $id;
}
function get_posts( $args = array() ) {
    if ( isset( $args['meta_key'], $args['meta_value'] ) && SC_Library_Scholarly_Library_Connectors::META_IMPORT_FINGERPRINT === $args['meta_key'] ) {
        foreach ( $GLOBALS['sc_meta'] as $post_id => $meta ) {
            if ( ( $meta[ $args['meta_key'] ] ?? '' ) === $args['meta_value'] ) {
                return array( $post_id );
            }
        }
    }
    return array();
}

function wp_safe_remote_get( $url, $args = array() ) {
    $GLOBALS['sc_http_calls']++;
    if ( ! $GLOBALS['sc_http_queue'] ) {
        return new WP_Error( 'empty_queue', 'No HTTP fixture queued.' );
    }
    return array_shift( $GLOBALS['sc_http_queue'] );
}
function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['status'] ?? 0 ); }
function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }
function wp_remote_retrieve_header( $response, $name ) {
    $headers = $response['headers'] ?? array();
    return $headers[ strtolower( $name ) ] ?? '';
}

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-connector-holdings-reliability.php';

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

$now = time();
$fresh = SC_Library_Connector_Holdings_Reliability::normalize_location(
    array(
        'kind' => 'open-access',
        'provider' => 'unpaywall',
        'label' => 'Open copy',
        'url' => 'https://example.org/open',
        'checked_at' => gmdate( 'Y-m-d H:i:s', $now ),
    )
);
assert_same( false, $fresh['stale'], 'Fresh location is not stale' );
assert_same( 7 * DAY_IN_SECONDS, $fresh['fresh_for_seconds'], 'Open-access freshness window' );

$stale = SC_Library_Connector_Holdings_Reliability::normalize_location(
    array(
        'kind' => 'openurl',
        'provider' => 'library:20',
        'label' => 'Holdings',
        'url' => 'https://library.example.org/openurl',
        'checked_at' => gmdate( 'Y-m-d H:i:s', $now - 2 * DAY_IN_SECONDS ),
    )
);
assert_same( true, $stale['stale'], 'Old OpenURL location is stale' );

$merged = SC_Library_Connector_Holdings_Reliability::merge_locations(
    array( array( 'kind' => 'preview', 'url' => 'https://example.org/item/', 'checked_at' => gmdate( 'Y-m-d H:i:s', $now - 100 ) ) ),
    array( array( 'kind' => 'preview', 'url' => 'https://example.org/item', 'checked_at' => gmdate( 'Y-m-d H:i:s', $now ), 'label' => 'New' ) ),
    30
);
assert_same( 1, count( $merged ), 'Location deduplication' );
assert_same( 'New', $merged[0]['label'], 'Newer location wins' );

SC_Library_Connector_Holdings_Reliability::record_failure( 'crossref', 'http', 503, 100, 'Unavailable' );
SC_Library_Connector_Holdings_Reliability::record_failure( 'crossref', 'http', 503, 120, 'Unavailable' );
SC_Library_Connector_Holdings_Reliability::record_failure( 'crossref', 'http', 503, 140, 'Unavailable' );
$health = SC_Library_Connector_Holdings_Reliability::provider_health( 'crossref' );
assert_same( 'open', $health['status'], 'Circuit opens after repeated failures' );
assert_same( 3, $health['consecutive_failures'], 'Consecutive failure count' );
assert_true( is_wp_error( SC_Library_Connector_Holdings_Reliability::provider_request_state( 'crossref' ) ), 'Open circuit blocks request' );

SC_Library_Connector_Holdings_Reliability::record_success( 'crossref', 200, 80, array(), false );
$health = SC_Library_Connector_Holdings_Reliability::provider_health( 'crossref' );
assert_same( 'healthy', $health['status'], 'Success closes circuit' );
assert_same( 0, $health['consecutive_failures'], 'Success resets consecutive failures' );

SC_Library_Connector_Holdings_Reliability::store_stale_search_cache(
    'sc_lib_v260_search_example',
    array( 'provider' => 'crossref', 'query' => 'example', 'results' => array( array( 'title' => 'Cached' ) ) )
);
$cached = SC_Library_Connector_Holdings_Reliability::get_stale_search_cache( 'sc_lib_v260_search_example' );
assert_same( true, $cached['stale'], 'Retained cache marked stale' );
assert_same( 'stale', $cached['cache_state'], 'Retained cache state' );

SC_Library_Connector_Holdings_Reliability::idempotency_store( 'import-12345678', array( 'source_id' => 10 ) );
$replay = SC_Library_Connector_Holdings_Reliability::idempotency_lookup( 'import-12345678' );
assert_same( 10, $replay['source_id'], 'Idempotency replay storage' );

$GLOBALS['sc_meta'][10][SC_Library_Scholarly_Library_Connectors::META_IMPORT_FINGERPRINT] =
    hash( 'sha256', 'crossref|10.1234/example' );
$found = SC_Library_Connector_Holdings_Reliability::find_imported_source(
    array( 'provider' => 'crossref', 'provider_record_id' => '10.1234/example' )
);
assert_same( 10, $found, 'Provider import fingerprint lookup' );

$conflict_id = SC_Library_Connector_Holdings_Reliability::record_conflict(
    10, 'title', 'Local title', 'Provider title', 'crossref', '10.1234/example'
);
assert_true( strlen( $conflict_id ) === 20, 'Conflict identifier generated' );
assert_same( 1, count( SC_Library_Connector_Holdings_Reliability::open_conflicts( 10 ) ), 'Open conflict stored' );
$resolved = SC_Library_Connector_Holdings_Reliability::resolve_conflict( 10, $conflict_id, 'use_provider' );
assert_true( ! is_wp_error( $resolved ), 'Conflict resolves' );
assert_same( 'Provider title', $GLOBALS['sc_posts'][10]['post_title'], 'Provider title applied' );
assert_same( 0, count( SC_Library_Connector_Holdings_Reliability::open_conflicts( 10 ) ), 'Resolved conflict closes' );

$GLOBALS['sc_meta'][20][SC_Library_Scholarly_Library_Connectors::META_PROFILE_CATALOG_TEMPLATE] =
    'https://library.example.org/search?q={query}';
$GLOBALS['sc_meta'][20][SC_Library_Scholarly_Library_Connectors::META_PROFILE_OPENURL_BASE] =
    'https://library.example.org/openurl';
$GLOBALS['sc_meta'][20][SC_Library_Scholarly_Library_Connectors::META_PROFILE_ENABLED] = '1';
$profile = SC_Library_Connector_Holdings_Reliability::validate_library_profile( 20, false );
assert_same( true, $profile['valid'], 'Valid library profile' );

$GLOBALS['sc_meta'][20][SC_Library_Scholarly_Library_Connectors::META_PROFILE_OPENURL_BASE] =
    'http://localhost/openurl';
$profile = SC_Library_Connector_Holdings_Reliability::validate_library_profile( 20, false );
assert_same( false, $profile['valid'], 'Unsafe library profile rejected' );

$GLOBALS['sc_http_calls'] = 0;
$GLOBALS['sc_http_queue'] = array(
    new WP_Error( 'temporary', 'Temporary transport error' ),
    array( 'status' => 200, 'headers' => array( 'etag' => '"abc"' ), 'body' => '{"ok":true}' ),
);
$response = SC_Library_Connector_Holdings_Reliability::request_json(
    'crossref',
    'https://api.crossref.org/works?query=test',
    array(),
    10,
    array( 'contact_email' => 'research@example.org' )
);
assert_same( true, $response['ok'], 'Transport retry returns JSON' );
assert_same( 2, $GLOBALS['sc_http_calls'], 'Transport retried once' );

$GLOBALS['sc_http_calls'] = 0;
$GLOBALS['sc_http_queue'] = array(
    array( 'status' => 304, 'headers' => array(), 'body' => '' ),
    array( 'status' => 200, 'headers' => array(), 'body' => '{"recovered":true}' ),
);
$response = SC_Library_Connector_Holdings_Reliability::request_json(
    'datacite',
    'https://api.datacite.org/dois?query=test',
    array(),
    10,
    array( 'contact_email' => 'research@example.org' )
);
assert_same( true, $response['recovered'], '304 cache miss retries without validators' );
assert_same( 2, $GLOBALS['sc_http_calls'], '304 recovery issues second request' );

echo "Connector reliability fixture checks passed: 24\n";
