<?php
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

function __( $text, $domain = null ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function remove_accents( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function wp_parse_url( $value, $component = -1 ) { return parse_url( $value, $component ); }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function wp_date( $format, $timestamp ) { return date( $format, $timestamp ); }
function absint( $value ) { return abs( intval( $value ) ); }
function wp_check_invalid_utf8( $value ) { return (string) $value; }

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-citation-source-manager.php';
require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-citation-source-reliability.php';

function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $label . "\nExpected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}
function assert_true( $actual, $label ) { assert_same( true, (bool) $actual, $label ); }
function assert_false( $actual, $label ) { assert_same( false, (bool) $actual, $label ); }

$base = array(
    'title' => 'Systems for public knowledge',
    'authors' => array( array( 'family' => 'Ahmad', 'given' => 'Tariq', 'suffix' => '', 'orcid' => '' ) ),
    'organization' => '',
    'organization_short' => '',
    'editors' => array(),
    'year' => '2026',
    'year_suffix' => '',
    'publication_date' => '2026-07-14',
    'access_date' => '',
    'container_title' => 'Journal of Open Systems',
    'publisher' => '', 'place' => '', 'edition' => '', 'volume' => '12', 'issue' => '3',
    'pages' => '41', 'chapter' => '', 'report_number' => '', 'standard_number' => '', 'jurisdiction' => '',
    'doi' => '10.1234/example', 'isbn' => '', 'pmid' => '', 'url' => '', 'archive_url' => '',
    'source_type' => 'journal-article', 'citation_key' => 'Ahmad2026Systems',
);

assert_same(
    "Ahmad, T. (2026). 'Systems for public knowledge'. Journal of Open Systems, 12(3), p. 41. doi: 10.1234/example.",
    SC_Library_Citation_Source_Manager::format_citation( $base, 'harvard', 'reference' ),
    'Single journal page uses p.'
);

$range = $base;
$range['pages'] = '41 - 58';
assert_same(
    "Ahmad, T. (2026). 'Systems for public knowledge'. Journal of Open Systems, 12(3), pp. 41–58. doi: 10.1234/example.",
    SC_Library_Citation_Source_Manager::format_citation( $range, 'harvard', 'reference' ),
    'Journal page range normalization'
);
assert_same(
    '(Ahmad, 2026, pp. 44–48)',
    SC_Library_Citation_Source_Manager::format_citation( $range, 'harvard', 'in-text', '44 - 48' ),
    'In-text range locator normalization'
);
assert_same(
    '(Ahmad, 2026, sec. 3.2)',
    SC_Library_Citation_Source_Manager::format_citation( $range, 'harvard', 'in-text', 'sec. 3.2' ),
    'Named locator preservation'
);

$institution = $base;
$institution['authors'] = array();
$institution['organization'] = 'World Health Organization';
$institution['organization_short'] = 'WHO';
assert_same( '(WHO, 2026)', SC_Library_Citation_Source_Manager::format_citation( $institution, 'harvard', 'in-text' ), 'Short institutional in-text author' );
assert_true( 0 === strpos( SC_Library_Citation_Source_Manager::format_citation( $institution, 'harvard', 'reference' ), 'World Health Organization (2026).' ), 'Full institutional reference author' );

$book = $base;
$book['source_type'] = 'book';
$book['container_title'] = '';
$book['publisher'] = 'Catalyst Press';
$book['place'] = 'Chicago';
$book['edition'] = '2';
$book['volume'] = '';
$book['issue'] = '';
$book['pages'] = '';
$book['doi'] = '';
assert_same(
    'Ahmad, T. (2026). Systems for public knowledge. 2nd edn. Chicago: Catalyst Press.',
    SC_Library_Citation_Source_Manager::format_citation( $book, 'harvard', 'reference' ),
    'Numeric edition normalization'
);

$chapter = $base;
$chapter['source_type'] = 'book-chapter';
$chapter['title'] = 'Evidence records';
$chapter['container_title'] = 'Handbook of Research Systems';
$chapter['editors'] = array( array( 'family' => 'Lee', 'given' => 'Morgan', 'suffix' => '', 'orcid' => '' ) );
$chapter['publisher'] = 'Open Press';
$chapter['place'] = 'London';
$chapter['volume'] = '';
$chapter['issue'] = '';
$chapter['pages'] = '90-104';
$chapter['doi'] = '';
assert_same(
    "Ahmad, T. (2026). 'Evidence records'. In: M. Lee (ed.), Handbook of Research Systems, pp. 90–104. London: Open Press.",
    SC_Library_Citation_Source_Manager::format_citation( $chapter, 'harvard', 'reference' ),
    'Book chapter In label and page range'
);

$anonymous = $base;
$anonymous['authors'] = array();
$anonymous['organization'] = '';
$anonymous['year'] = '';
$anonymous['publication_date'] = '';
$anonymous['source_type'] = 'webpage';
$anonymous['container_title'] = '';
$anonymous['volume'] = '';
$anonymous['issue'] = '';
$anonymous['pages'] = '';
$anonymous['doi'] = '';
$anonymous['url'] = 'https://example.org/source';
assert_same(
    'Systems for public knowledge (n.d.). Available at: https://example.org/source.',
    SC_Library_Citation_Source_Manager::format_citation( $anonymous, 'harvard', 'reference' ),
    'Missing author and date fallback'
);

assert_true( SC_Library_Citation_Source_Reliability::valid_doi( '10.1000/xyz-123' ), 'Valid DOI' );
assert_false( SC_Library_Citation_Source_Reliability::valid_doi( 'doi-not-valid' ), 'Invalid DOI' );
assert_true( SC_Library_Citation_Source_Reliability::valid_isbn( '0-306-40615-2' ), 'Valid ISBN-10' );
assert_true( SC_Library_Citation_Source_Reliability::valid_isbn( '978-0-306-40615-7' ), 'Valid ISBN-13' );
assert_false( SC_Library_Citation_Source_Reliability::valid_isbn( '978-0-306-40615-8' ), 'Invalid ISBN checksum' );
assert_true( SC_Library_Citation_Source_Reliability::valid_orcid( '0000-0002-1825-0097' ), 'Valid ORCID checksum' );
assert_false( SC_Library_Citation_Source_Reliability::valid_orcid( '0000-0002-1825-0098' ), 'Invalid ORCID checksum' );
assert_same(
    'https://example.org/article?a=1&b=2',
    SC_Library_Citation_Source_Reliability::canonical_url( 'https://www.example.org/article/?utm_source=newsletter&b=2&a=1#section' ),
    'Canonical URL removes tracking, fragment, www, and trailing slash'
);

$parser = new ReflectionMethod( SC_Library_Citation_Source_Manager::class, 'parse_people_lines' );
$parser->setAccessible( true );
$people = $parser->invoke( null, "de la Cruz, Ana María\nO'Neil | Shaun | Jr. | 0000-0002-1825-0097" );
assert_same( 'de la Cruz', $people[0]['family'], 'Comma author family parsing' );
assert_same( 'Ana María', $people[0]['given'], 'Comma author given parsing' );
assert_same( "O'Neil", $people[1]['family'], 'Apostrophe author preservation' );
assert_same( '0000-0002-1825-0097', $people[1]['orcid'], 'ORCID normalization' );

$checks = 20;
echo "Citation formatting reliability behavior checks passed: {$checks}\n";
