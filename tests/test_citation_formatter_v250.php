<?php
define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
}
if ( ! function_exists( 'remove_accents' ) ) {
    function remove_accents( $value ) { return $value; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $value ) { return (string) $value; }
}
if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp ) { return date( $format, $timestamp ); }
}

require_once dirname( __DIR__ ) . '/sustainable-catalyst-library/includes/class-sc-library-citation-source-manager.php';

function assert_same( $expected, $actual, $label ) {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $label . "\nExpected: " . $expected . "\nActual:   " . $actual . "\n" );
        exit( 1 );
    }
}

$article = array(
    'title' => 'Systems for public knowledge',
    'authors' => array(
        array( 'family' => 'Ahmad', 'given' => 'Tariq', 'suffix' => '', 'orcid' => '' ),
        array( 'family' => 'Smith', 'given' => 'Jane', 'suffix' => '', 'orcid' => '' ),
    ),
    'organization' => '',
    'editors' => array(),
    'year' => '2026',
    'year_suffix' => '',
    'publication_date' => '2026-07-14',
    'access_date' => '',
    'container_title' => 'Journal of Open Systems',
    'publisher' => '',
    'place' => '',
    'edition' => '',
    'volume' => '12',
    'issue' => '3',
    'pages' => '41-58',
    'chapter' => '',
    'report_number' => '',
    'standard_number' => '',
    'jurisdiction' => '',
    'doi' => '10.1234/example',
    'isbn' => '',
    'pmid' => '',
    'url' => '',
    'archive_url' => '',
    'source_type' => 'journal-article',
    'citation_key' => 'Ahmad2026Systems',
);

assert_same(
    "Ahmad, T. and Smith, J. (2026). 'Systems for public knowledge'. Journal of Open Systems, 12(3), pp. 41–58. doi: 10.1234/example.",
    SC_Library_Citation_Source_Manager::format_citation( $article, 'harvard', 'reference' ),
    'Journal article reference'
);
assert_same(
    '(Ahmad and Smith, 2026, p. 44)',
    SC_Library_Citation_Source_Manager::format_citation( $article, 'harvard', 'in-text', '44' ),
    'Journal article in-text citation'
);

$book = $article;
$book['authors'] = array();
$book['organization'] = 'International Knowledge Institute';
$book['title'] = 'Research infrastructure';
$book['source_type'] = 'book';
$book['publisher'] = 'Catalyst Press';
$book['place'] = 'Chicago';
$book['edition'] = '2nd';
$book['container_title'] = '';
$book['volume'] = '';
$book['issue'] = '';
$book['pages'] = '';
$book['doi'] = '';
$book['url'] = 'https://example.org/book';
$book['access_date'] = '2026-07-14';

assert_same(
    'International Knowledge Institute (2026). Research infrastructure. 2nd edn. Chicago: Catalyst Press. Available at: https://example.org/book (Accessed: 14 July 2026).',
    SC_Library_Citation_Source_Manager::format_citation( $book, 'harvard', 'reference' ),
    'Book reference'
);
assert_same(
    '(International Knowledge Institute, 2026)',
    SC_Library_Citation_Source_Manager::format_citation( $book, 'harvard', 'in-text' ),
    'Organizational in-text citation'
);

$chapter = $article;
$chapter['authors'] = array(
    array( 'family' => 'Jones', 'given' => 'Amira', 'suffix' => '', 'orcid' => '' ),
);
$chapter['editors'] = array(
    array( 'family' => 'Lee', 'given' => 'Morgan', 'suffix' => '', 'orcid' => '' ),
);
$chapter['title'] = 'Evidence records';
$chapter['container_title'] = 'Handbook of Research Systems';
$chapter['source_type'] = 'book-chapter';
$chapter['publisher'] = 'Open Press';
$chapter['place'] = 'London';
$chapter['pages'] = '90-104';
$chapter['doi'] = '';

assert_same(
    "Jones, A. (2026). 'Evidence records'. in M. Lee (ed.), Handbook of Research Systems, pp. 90–104. London: Open Press.",
    SC_Library_Citation_Source_Manager::format_citation( $chapter, 'harvard', 'reference' ),
    'Book chapter reference'
);

echo "Citation formatter behavior checks passed: 5\n";
