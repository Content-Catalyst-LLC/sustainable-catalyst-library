<?php
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['next_id'] = 100;
$GLOBALS['posts'] = array(
	1 => array(
		'post_type' => 'sc_foundation_doc',
		'post_status' => 'publish',
		'post_title' => 'Planetary Boundaries and Earth System Stability',
		'post_name' => 'planetary-boundaries-earth-system-stability',
		'post_excerpt' => 'A public Knowledge Library document.',
		'post_content' => 'Planetary boundaries describe environmental processes and Earth system stability.',
		'post_author' => 7,
		'post_date_gmt' => '2026-07-15 10:00:00',
		'post_modified_gmt' => '2026-07-15 11:00:00',
	),
	2 => array(
		'post_type' => 'sc_foundation_doc',
		'post_status' => 'publish',
		'post_title' => 'Climate Governance and Institutional Resilience',
		'post_name' => 'climate-governance-institutional-resilience',
		'post_excerpt' => 'A second public document.',
		'post_content' => 'Climate governance requires evidence, accountability, and institutional resilience.',
		'post_author' => 7,
		'post_date_gmt' => '2026-07-15 12:00:00',
		'post_modified_gmt' => '2026-07-15 13:00:00',
	),
);
$GLOBALS['meta'] = array(
	1 => array(
		'_sc_document_intelligence_profile' => array(
			'status' => 'ready',
			'summary' => 'The document explains planetary boundaries and Earth system stability.',
			'key_points' => array( 'Environmental systems interact.' ),
			'analyzed_at' => '2026-07-15 11:30:00',
		),
	),
	2 => array(),
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
class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_title;
	public $post_name;
	public $post_excerpt;
	public $post_content;
	public $post_author;
}
class WP_REST_Server { const READABLE = 'GET'; const EDITABLE = 'POST'; const CREATABLE = 'POST'; }
class WP_REST_Request {}
class WP_REST_Response {}

class SC_Library_Foundation_Pages { const POST_TYPE = 'sc_foundation_doc'; }
class SC_Library_PDF_To_Document {
	const META_SOURCE_ATTACHMENT = '_sc_document_source_attachment';
}
class SC_Library_Connected_Research_Environment { const PROJECT_POST_TYPE = 'sc_research_project'; }
class SC_Library_Citation_Source_Manager { const SOURCE_POST_TYPE = 'sc_research_source'; }
class SC_Library_Knowledge_Pathways_Article_Maps { const PATHWAY_POST_TYPE = 'sc_knowledge_pathway'; }
class SC_Library_Institutional_Collections_Archives { const COLLECTION_POST_TYPE = 'sc_inst_collection'; }
class SC_Library_Collaborative_Review_Publishing {
	const PACKAGE_POST_TYPE = 'sc_pub_package';
	public static function package_data() { return array(); }
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_post_type() {}
function register_rest_route() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-:]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._\-]/', '-', (string) $value ); }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) {
	if ( 'timestamp' === $type ) return strtotime( '2026-07-15 14:00:00' );
	if ( 'Y-m-d' === $type ) return '2026-07-15';
	return '2026-07-15 14:00:00';
}
function get_current_user_id() { return 7; }
function current_user_can() { return true; }
function get_bloginfo( $field ) { return 'Sustainable Catalyst'; }
function home_url( $path = '' ) { return 'https://example.org' . $path; }
function rest_url( $path = '' ) { return 'https://example.org/wp-json/' . ltrim( $path, '/' ); }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['post_type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['post_status'] ?? ''; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['post_title'] ?? ''; }
function get_the_excerpt( $id ) { return $GLOBALS['posts'][ $id ]['post_excerpt'] ?? ''; }
function get_permalink( $id ) { return 'https://example.org/library/' . $id; }
function get_post_field( $field, $id ) { return $GLOBALS['posts'][ $id ][ $field ] ?? ''; }
function get_post_time( $format, $gmt, $id ) { return '2026-07-15T10:00:00+00:00'; }
function get_post_modified_time( $format, $gmt, $id ) { return '2026-07-15T11:00:00+00:00'; }
function get_post( $id ) {
	if ( empty( $GLOBALS['posts'][ $id ] ) ) return null;
	$post = new WP_Post();
	$post->ID = $id;
	foreach ( $GLOBALS['posts'][ $id ] as $key => $value ) $post->{$key} = $value;
	return $post;
}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_insert_post( $args, $wp_error = false ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = array(
		'post_type' => $args['post_type'],
		'post_status' => $args['post_status'],
		'post_title' => $args['post_title'] ?? '',
		'post_name' => sanitize_key( $args['post_title'] ?? '' ),
		'post_excerpt' => '',
		'post_content' => '',
		'post_author' => $args['post_author'] ?? 0,
		'post_date_gmt' => '2026-07-15 14:00:00',
		'post_modified_gmt' => '2026-07-15 14:00:00',
	);
	$GLOBALS['meta'][ $id ] = array();
	return $id;
}
function get_posts( $args ) {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) ) {
			$types = (array) $args['post_type'];
			if ( ! in_array( $post['post_type'], $types, true ) ) continue;
		}
		if ( isset( $args['post_status'] ) && ! in_array( $post['post_status'], (array) $args['post_status'], true ) ) continue;
		if ( isset( $args['post__not_in'] ) && in_array( $id, (array) $args['post__not_in'], true ) ) continue;
		if ( isset( $args['meta_key'] ) ) {
			$value = $GLOBALS['meta'][ $id ][ $args['meta_key'] ] ?? null;
			if ( isset( $args['meta_value'] ) && (string) $value !== (string) $args['meta_value'] ) continue;
		}
		if ( ! empty( $args['s'] ) && false === stripos( $post['post_title'] . ' ' . $post['post_content'], $args['s'] ) ) continue;
		$ids[] = $id;
	}
	sort( $ids );
	$limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : count( $ids );
	return array_slice( $ids, 0, $limit );
}
function wp_count_posts( $post_type ) {
	$count = 0;
	foreach ( $GLOBALS['posts'] as $post ) if ( $post['post_type'] === $post_type && 'publish' === $post['post_status'] ) $count++;
	return (object) array( 'publish' => $count );
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_hash_password( $value ) { return 'hash:' . hash( 'sha256', $value ); }
function wp_salt( $scheme = 'auth' ) { return 'fixture-salt'; }
function wp_unslash( $value ) { return $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function apply_filters( $tag, $value ) { return $value; }
function taxonomy_exists() { return false; }
function wp_get_object_terms() { return array(); }
function wp_get_attachment_url( $id ) { return 'https://example.org/media/' . $id . '.pdf'; }
function get_post_mime_type( $id ) { return 'application/pdf'; }
function __( $text, $domain = null ) { return $text; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-public-api-export-federation.php';

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

$reflection = new ReflectionClass( 'SC_Library_Public_API_Export_Federation' );

$allowed = $reflection->getMethod( 'allowed_value' );
$allowed->setAccessible( true );
assert_same( 'jsonld', $allowed->invoke( null, 'jsonld', SC_Library_Public_API_Export_Federation::export_formats(), 'json' ), 'Allowed export format retained' );
assert_same( 'json', $allowed->invoke( null, 'invalid', SC_Library_Public_API_Export_Federation::export_formats(), 'json' ), 'Invalid export format falls back' );

$datetime = $reflection->getMethod( 'sanitize_datetime' );
$datetime->setAccessible( true );
assert_same( '2026-07-30 10:15:00', $datetime->invoke( null, '2026-07-30T10:15' ), 'Datetime normalized' );
assert_same( '', $datetime->invoke( null, 'not-a-date' ), 'Invalid datetime rejected' );

$encode = $reflection->getMethod( 'encode_cursor' );
$decode = $reflection->getMethod( 'decode_cursor' );
$encode->setAccessible( true );
$decode->setAccessible( true );
$cursor = $encode->invoke( null, array( 'after_id' => 42, 'type' => 'documents' ) );
assert_true( false === strpos( $cursor, '=' ), 'Cursor padding removed' );
$decoded = $decode->invoke( null, $cursor );
assert_same( 42, $decoded['after_id'], 'Cursor ID round trip' );
assert_same( 'documents', $decoded['type'], 'Cursor type round trip' );
assert_same( array(), $decode->invoke( null, '***' ), 'Invalid cursor returns empty array' );

$sort = $reflection->getMethod( 'recursive_sort' );
$sort->setAccessible( true );
$sorted = $sort->invoke( null, array( 'z' => 1, 'a' => array( 'b' => 2, 'a' => 1 ) ) );
assert_same( array( 'a', 'z' ), array_keys( $sorted ), 'Top-level keys sorted' );
assert_same( array( 'a', 'b' ), array_keys( $sorted['a'] ), 'Nested keys sorted' );

$canonical = $reflection->getMethod( 'canonical_json' );
$canonical->setAccessible( true );
assert_same( '{"a":1,"b":2}', $canonical->invoke( null, array( 'b' => 2, 'a' => 1 ) ), 'Canonical JSON stable' );

$private_ip = $reflection->getMethod( 'private_ip' );
$private_ip->setAccessible( true );
assert_true( $private_ip->invoke( null, '127.0.0.1' ), 'Loopback is private' );
assert_true( $private_ip->invoke( null, '10.0.0.5' ), 'RFC1918 address is private' );
assert_true( ! $private_ip->invoke( null, '8.8.8.8' ), 'Public IP accepted' );

$safe_url = $reflection->getMethod( 'safe_federation_url' );
$safe_url->setAccessible( true );
assert_true( $safe_url->invoke( null, 'https://peer.example.org/' ), 'HTTPS peer URL accepted' );
assert_true( ! $safe_url->invoke( null, 'http://peer.example.org/' ), 'HTTP peer URL rejected' );
assert_true( ! $safe_url->invoke( null, 'https://localhost/' ), 'Localhost rejected' );
assert_true( ! $safe_url->invoke( null, 'https://127.0.0.1/' ), 'Loopback URL rejected' );

$token = SC_Library_Public_API_Export_Federation::create_token(
	'Research client',
	array( 'catalog:read', 'exports:create', 'invalid:scope' ),
	'2026-08-01T12:00',
	200
);
assert_true( ! is_wp_error( $token ), 'Token created' );
assert_true( 0 === strpos( $token['token'], 'sckl_' ), 'Token prefix' );
assert_true( strlen( $token['token'] ) > 60, 'Token entropy length' );
assert_same( array( 'catalog:read', 'exports:create' ), $token['scopes'], 'Invalid token scope removed' );
assert_same( 200, $GLOBALS['meta'][ $token['token_id'] ]['_sc_api_token_rate_limit'], 'Token rate limit stored' );
assert_true( strlen( $GLOBALS['meta'][ $token['token_id'] ]['_sc_api_token_hash'] ) === 64, 'Token SHA-256 hash stored' );
assert_true( false === strpos( $GLOBALS['meta'][ $token['token_id'] ]['_sc_api_token_hash'], $token['token'] ), 'Raw token not stored in hash' );

$rate1 = SC_Library_Public_API_Export_Federation::enforce_rate_limit( 'fixture-client', 2 );
$rate2 = SC_Library_Public_API_Export_Federation::enforce_rate_limit( 'fixture-client', 2 );
$rate3 = SC_Library_Public_API_Export_Federation::enforce_rate_limit( 'fixture-client', 2 );
assert_true( ! is_wp_error( $rate1 ), 'First rate-limit request allowed' );
assert_same( 1, $rate1['remaining'], 'First rate-limit remaining count' );
assert_same( 0, $rate2['remaining'], 'Second rate-limit remaining count' );
assert_true( is_wp_error( $rate3 ), 'Third rate-limit request blocked' );

$capabilities = SC_Library_Public_API_Export_Federation::capabilities( true );
assert_same( 'sc-library-api-capabilities/1.0', $capabilities['schema'], 'Capability schema' );
assert_same( '1.0', $capabilities['api_version'], 'API version' );
assert_true( in_array( 'jsonld', $capabilities['formats'], true ), 'JSON-LD advertised' );
assert_same( 'opaque-cursor', $capabilities['pagination']['type'], 'Opaque cursor advertised' );
assert_true( $capabilities['conditional_get']['etag'], 'ETag support advertised' );
assert_true( ! isset( $capabilities['token_scopes'] ), 'Public capabilities omit private token scopes' );

$record = SC_Library_Public_API_Export_Federation::serialize_record( 1, 'documents', false );
assert_same( 1, $record['id'], 'Serialized record ID' );
assert_same( 'documents', $record['type'], 'Serialized record type' );
assert_same( 'Planetary Boundaries and Earth System Stability', $record['title'], 'Serialized title' );
assert_true( strlen( $record['content_hash'] ) === 64, 'Serialized content hash' );
assert_true( isset( $record['document_intelligence']['summary'] ), 'Public document intelligence included' );
assert_true( ! isset( $record['raw_content'] ), 'Public record omits raw content' );
assert_true( ! isset( $record['author_id'] ), 'Public record omits author ID' );

$private_record = SC_Library_Public_API_Export_Federation::serialize_record( 1, 'documents', true );
assert_true( isset( $private_record['raw_content'] ), 'Private record can include raw content' );
assert_same( 7, $private_record['author_id'], 'Private record author ID' );

$catalog = SC_Library_Public_API_Export_Federation::public_catalog( array( 'type' => 'documents', 'limit' => 1 ) );
assert_same( 'sc-library-public-api/1.0', $catalog['schema'], 'Catalog schema' );
assert_same( 1, $catalog['count'], 'Catalog limit applied' );
assert_true( ! empty( $catalog['next_cursor'] ), 'Catalog next cursor returned' );
assert_true( 0 === strpos( $catalog['etag'], '"' ), 'Catalog ETag quoted' );

$next = SC_Library_Public_API_Export_Federation::public_catalog(
	array( 'type' => 'documents', 'limit' => 1, 'cursor' => $catalog['next_cursor'] )
);
assert_same( 2, $next['records'][0]['id'], 'Cursor advances to next record' );

$job = SC_Library_Public_API_Export_Federation::create_export_job(
	array( 'format' => 'ndjson', 'scope' => 'documents', 'filters' => array( 'search' => 'climate' ), 'public' => true ),
	array( 'type' => 'fixture' )
);
assert_true( ! is_wp_error( $job ), 'Export job created' );
assert_same( 'queued', $job['status'], 'Export starts queued' );
assert_same( 'ndjson', $job['format'], 'Export format retained' );
assert_same( 'documents', $job['scope'], 'Export scope retained' );
assert_true( $job['public'], 'Public export flag retained' );
assert_same( 'climate', $job['filters']['search'], 'Export search filter sanitized' );

$valid_payload = array(
	'schema' => 'sc-library-export-manifest/1.0',
	'records' => array(
		array( 'id' => 'peer:1', 'type' => 'documents', 'title' => 'Peer document' ),
	),
);
$validation = SC_Library_Public_API_Export_Federation::validate_import_payload( $valid_payload, 0 );
assert_true( $validation['valid'], 'Valid federation payload accepted for quarantine' );
assert_true( ! $validation['automatic_import_allowed'], 'Automatic import disabled' );

$invalid = SC_Library_Public_API_Export_Federation::validate_import_payload( array( 'records' => array() ), 0 );
assert_true( ! $invalid['valid'], 'Invalid federation payload rejected' );
assert_true( in_array( 'schema-missing', $invalid['errors'], true ), 'Missing schema detected' );
assert_true( in_array( 'records-missing', $invalid['errors'], true ), 'Missing records detected' );

$import = SC_Library_Public_API_Export_Federation::quarantine_import( $valid_payload, 0 );
assert_true( ! is_wp_error( $import ), 'Federation import quarantined' );
assert_same( 'sc-library-federation-import/1.0', $import['schema'], 'Import schema' );
assert_same( 'quarantined', $import['status'], 'Import status quarantined' );
assert_true( strlen( $import['sha256'] ) === 64, 'Import SHA-256 hash' );
assert_true( isset( $import['payload']['records'] ), 'Private import data includes payload' );

$decision = SC_Library_Public_API_Export_Federation::decide_import( $import['import_id'], 'approve-metadata', 'Metadata reviewed.' );
assert_true( ! is_wp_error( $decision ), 'Metadata import approved' );
assert_same( 'approved-metadata', $decision['status'], 'Import decision status' );
assert_true( ! $decision['decision']['automatic_content_import'], 'Approval does not auto-import content' );

$audit = $reflection->getMethod( 'redact_audit_context' );
$audit->setAccessible( true );
$redacted = $audit->invoke( null, array( 'token' => 'secret-token', 'nested' => array( 'authorization' => 'Bearer secret' ), 'safe' => 'value' ) );
assert_same( '[redacted]', $redacted['token'], 'Token redacted from audit' );
assert_same( '[redacted]', $redacted['nested']['authorization'], 'Nested authorization redacted' );
assert_same( 'value', $redacted['safe'], 'Safe audit context preserved' );

$state = $reflection->getMethod( 'default_migration_state' );
$state->setAccessible( true );
$migration = $state->invoke( null );
assert_same( '3.9.0', $migration['version'], 'Migration version' );
assert_same( 'pending', $migration['status'], 'Migration starts pending' );
assert_same( 0, $migration['cursor'], 'Migration cursor starts at zero' );

echo "Public API, Export, and Federation Hardening fixture checks passed: 67\n";
