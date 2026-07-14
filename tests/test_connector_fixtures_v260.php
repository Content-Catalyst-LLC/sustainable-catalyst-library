<?php
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['sc_test_transients'] = array();

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
    const SOURCE_TYPE_TAXONOMY = 'sc_source_type';
    const SOURCE_TOPIC_TAXONOMY = 'sc_source_topic';
    const META_NORMALIZED_DOI = '_doi_n';
    const META_DOI = '_doi';
    const META_NORMALIZED_ISBN = '_isbn_n';
    const META_ISBN = '_isbn';
    const META_PMID = '_pmid';
}
class SC_Library_Citation_Source_Reliability {}

function __( $text, $domain = null ) { return $text; }
function add_action() {}
function add_shortcode() {}
function apply_filters( $hook, $value ) { return $value; }
function get_option( $key, $default = false ) {
    if ( 'admin_email' === $key ) { return 'research@example.org'; }
    return $default;
}
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_text_field( $value ) {
    if ( is_array( $value ) ) { return ''; }
    return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' ); }
function esc_url_raw( $value ) {
    $value = trim( (string) $value );
    return preg_match( '#^https://#', $value ) ? $value : '';
}
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function absint( $value ) { return abs( (int) $value ); }
function current_user_can() { return true; }
function get_current_user_id() { return 7; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return str_repeat( 'a', $length ); }
function get_posts() { return array(); }
function get_transient( $key ) { return $GLOBALS['sc_test_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['sc_test_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['sc_test_transients'][ $key ] ); return true; }
function current_time( $type ) { return '2026-07-14 12:00:00'; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function rawurlencode_recursive( $value ) { return rawurlencode( $value ); }
function add_query_arg( $args, $url ) {
    $separator = false === strpos( $url, '?' ) ? '?' : '&';
    return $url . $separator . http_build_query( array_filter( $args, function ( $value ) { return null !== $value && '' !== $value; } ) );
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_safe_remote_get( $url, $args = array() ) {
    if ( false !== strpos( $url, 'api.crossref.org' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'headers' => array(),
            'body' => json_encode(
                array(
                    'message' => array(
                        'items' => array(
                            array(
                                'DOI' => '10.1234/Example',
                                'title' => array( 'Open research systems' ),
                                'author' => array(
                                    array( 'family' => 'Ahmad', 'given' => 'Tariq', 'ORCID' => 'https://orcid.org/0000-0002-1825-0097' ),
                                ),
                                'issued' => array( 'date-parts' => array( array( 2026, 7, 14 ) ) ),
                                'container-title' => array( 'Journal of Open Systems' ),
                                'publisher' => 'Catalyst Press',
                                'type' => 'journal-article',
                                'URL' => 'https://doi.org/10.1234/Example',
                                'page' => '41-58',
                                'volume' => '12',
                                'issue' => '3',
                                'abstract' => '<jats:p>Structured discovery.</jats:p>',
                            ),
                        ),
                    ),
                )
            ),
        );
    }
    if ( false !== strpos( $url, 'api.datacite.org' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'headers' => array(),
            'body' => json_encode(
                array(
                    'data' => array(
                        array(
                            'id' => '10.5678/dataset',
                            'attributes' => array(
                                'doi' => '10.5678/dataset',
                                'titles' => array( array( 'title' => 'Sustainability dataset' ) ),
                                'creators' => array(
                                    array( 'familyName' => 'Smith', 'givenName' => 'Jane', 'nameType' => 'Personal' ),
                                ),
                                'publicationYear' => 2025,
                                'types' => array( 'resourceTypeGeneral' => 'Dataset' ),
                                'publisher' => 'Open Data Institute',
                                'url' => 'https://doi.org/10.5678/dataset',
                                'descriptions' => array( array( 'descriptionType' => 'Abstract', 'description' => 'A dataset.' ) ),
                            ),
                        ),
                    ),
                )
            ),
        );
    }
    if ( false !== strpos( $url, 'openlibrary.org/search.json' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'headers' => array(),
            'body' => json_encode(
                array(
                    'docs' => array(
                        array(
                            'key' => '/works/OL123W',
                            'title' => 'Research libraries',
                            'author_name' => array( 'Amira Jones' ),
                            'first_publish_year' => 2024,
                            'publisher' => array( 'Library Press' ),
                            'isbn' => array( '9781234567897' ),
                            'public_scan_b' => true,
                            'ebook_access' => 'public',
                            'ia' => array( 'researchlibraries' ),
                        ),
                    ),
                )
            ),
        );
    }
    return array( 'response' => array( 'code' => 404 ), 'headers' => array(), 'body' => '{}' );
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][ strtolower( $name ) ] ?? ''; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-scholarly-library-connectors.php';

function assert_true( $condition, $label ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAILED: " . $label . "\n" );
        exit( 1 );
    }
}
function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, "FAILED: " . $label . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

$connectors = new SC_Library_Scholarly_Library_Connectors();

$crossref = $connectors->search_provider( 'crossref', 'open research systems', 5, true );
assert_true( ! is_wp_error( $crossref ), 'Crossref search returns a payload' );
assert_same( 1, $crossref['result_count'], 'Crossref result count' );
assert_same( 'journal-article', $crossref['results'][0]['source_type'], 'Crossref source type' );
assert_same( '10.1234/example', $crossref['results'][0]['doi'], 'Crossref DOI normalization' );
assert_same( 'Ahmad', $crossref['results'][0]['authors'][0]['family'], 'Crossref author family' );
assert_true( ! empty( $crossref['results'][0]['import_token'] ), 'Crossref result receives import token' );

$datacite = $connectors->search_provider( 'datacite', 'sustainability dataset', 5, true );
assert_true( ! is_wp_error( $datacite ), 'DataCite search returns a payload' );
assert_same( 'dataset', $datacite['results'][0]['source_type'], 'DataCite dataset mapping' );
assert_same( 'Open Data Institute', $datacite['results'][0]['publisher'], 'DataCite publisher mapping' );

$openlibrary = $connectors->search_provider( 'openlibrary', 'research libraries', 5, true );
assert_true( ! is_wp_error( $openlibrary ), 'Open Library search returns a payload' );
assert_same( 'book', $openlibrary['results'][0]['source_type'], 'Open Library book mapping' );
assert_same( 'open-access', $openlibrary['results'][0]['full_text_status'], 'Open Library public scan mapping' );
assert_same( '9781234567897', $openlibrary['results'][0]['isbn'], 'Open Library ISBN mapping' );

$handoffs = $connectors->discovery_handoffs(
    array(
        'title' => 'Research libraries',
        'authors' => array( array( 'family' => 'Jones', 'given' => 'Amira' ) ),
        'organization' => '',
        'doi' => '10.1234/example',
        'isbn' => '9781234567897',
        'pmid' => '12345678',
    )
);
$handoff_urls = array_column( $handoffs, 'url' );
assert_true( false !== strpos( implode( ' ', $handoff_urls ), 'scholar.google.com' ), 'Google Scholar handoff' );
assert_true( false !== strpos( implode( ' ', $handoff_urls ), 'search.worldcat.org' ), 'WorldCat handoff' );
assert_true( false !== strpos( implode( ' ', $handoff_urls ), 'pubmed.ncbi.nlm.nih.gov' ), 'PubMed handoff' );

$reflection = new ReflectionClass( 'SC_Library_Scholarly_Library_Connectors' );
$unique = $reflection->getMethod( 'unique_locations' );
$unique->setAccessible( true );
$locations = $unique->invoke(
    null,
    array(
        array( 'url' => 'https://example.org/item', 'label' => 'A' ),
        array( 'url' => 'https://example.org/item/', 'label' => 'B' ),
        array( 'url' => 'https://example.org/other', 'label' => 'C' ),
    )
);
assert_same( 2, count( $locations ), 'Location URL deduplication' );

echo "Connector fixture behavior checks passed: 17\n";
