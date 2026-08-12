<?php
if ( $argc < 1 ) { exit(1); }
define('ABSPATH', __DIR__ . '/');
function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return $number === 1 ? $single : $plural; }
function sanitize_key( $value ) { $value = strtolower((string)$value); return preg_replace('/[^a-z0-9_\-]/', '', $value); }
function sanitize_text_field( $value ) { return trim(strip_tags((string)$value)); }
function esc_url_raw( $value ) { return trim((string)$value); }
function absint( $value ) { return abs((int)$value); }
function current_time( $type ) { return $type === 'mysql' ? '2026-08-12 04:20:00' : '2026-08-12T04:20:00+00:00'; }

require_once dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-research-librarian-access-intelligence.php';

$cases = array(
    'open' => SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result(array(
        'title' => 'Open article',
        'doi' => '10.1000/open',
        'open_access_url' => 'https://example.org/open.pdf',
        'full_text_status' => 'open-access',
        'record_url' => 'https://example.org/record',
    )),
    'public_digital' => SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result(array(
        'title' => 'Archive item',
        'open_access_url' => 'https://archive.example/item',
        'full_text_status' => 'public-digital',
    )),
    'institution' => SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result(array(
        'title' => 'Subscription article',
        'full_text_status' => 'institutional-auth',
        'record_url' => 'https://publisher.example/item',
    )),
    'preview' => SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result(array(
        'title' => 'Book preview',
        'preview_url' => 'https://books.example/preview',
        'full_text_status' => 'preview-only',
    )),
    'worldcat' => SC_Library_Research_Librarian_Access_Intelligence::evaluate_normalized_result(array(
        'title' => 'Catalog title',
        'record_url' => 'https://metadata.example/record',
        'discovery_links' => array(array(
            'provider' => 'worldcat',
            'kind' => 'library-search',
            'label' => 'Search WorldCat',
            'url' => 'https://search.worldcat.org/search?q=test',
        )),
    )),
);

echo json_encode(array(
    'schema' => SC_Library_Research_Librarian_Access_Intelligence::SCHEMA,
    'version' => SC_Library_Research_Librarian_Access_Intelligence::VERSION,
    'cases' => $cases,
), JSON_UNESCAPED_SLASHES);
