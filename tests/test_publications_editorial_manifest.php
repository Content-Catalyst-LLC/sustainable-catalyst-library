<?php
/** Minimal isolated WordPress fixture for SC-PUB-SEO-03A's manifest. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
define( 'SC_LIBRARY_PUBLICATIONS_MANIFEST_SYNC', true );

class WP_Post {
    public int $ID;
    public string $post_type;
    public string $post_status;
    public string $post_password;
    public bool $publicly_viewable;
    public string $url;
    public string $post_modified_gmt = '2026-11-22 10:00:00';
    public function __construct( int $id, string $type, string $status, string $url, string $password = '', bool $viewable = true ) {
        $this->ID = $id;
        $this->post_type = $type;
        $this->post_status = $status;
        $this->url = $url;
        $this->post_password = $password;
        $this->publicly_viewable = $viewable;
    }
}
class SC_Library_Field_Spotlights {
    public const SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434';
    public static function field_definitions(): array {
        return array( 'global-governance' => array( 'title' => 'Global Governance' ) );
    }
    public static function series_registry(): array {
        return array( 'international-law' => array(
            'field_slug' => 'global-governance',
            'canonical_url' => '/international-law/',
        ) );
    }
}
function wp_get_environment_type(): string { return $GLOBALS['environment']; }
function home_url( string $path = '/' ): string {
    return 'https://sustainablecatalyst.com/' . ltrim( $path, '/' );
}
function wp_parse_url( string $url ) { return parse_url( $url ); }
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?: ''; }
function sanitize_title( string $value ): string {
    return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $value ) ), '-' );
}
function get_permalink( WP_Post $post ): string { return $post->url; }
function get_post_meta( int $id, string $key, bool $single = false ) {
    return $GLOBALS['post_meta'][ $id ][ $key ] ?? '';
}
function url_to_postid( string $url ): int { return $GLOBALS['route_ids'][ $url ] ?? 0; }
function get_post( int $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_option( string $key, $default = null ) {
    return $GLOBALS['options'][ $key ] ?? $default;
}
function is_post_publicly_viewable( WP_Post $post ): bool { return $post->publicly_viewable; }

require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-publications-discovery-manifest.php';

function check( bool $condition, string $case ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$case}\n" );
        exit( 1 );
    }
    echo "PASS: {$case}\n";
}
function fresh(): WP_Post {
    $GLOBALS['environment'] = 'staging';
    $article = new WP_Post(123, 'post', 'publish', 'https://sustainablecatalyst.com/science-of-law/');
    $map = new WP_Post(501, 'page', 'publish', 'https://sustainablecatalyst.com/international-law/');
    $GLOBALS['posts'] = array( 123 => $article, 501 => $map );
    $GLOBALS['post_meta'] = array( 123 => array(
        '_sc_publication_article_map_key_v4337' => 'international-law',
    ) );
    $GLOBALS['route_ids'] = array( $map->url => 501 );
    $GLOBALS['options'] = array( SC_Library_Field_Spotlights::SETTINGS_OPTION => array() );
    return $article;
}
function denied( WP_Post $article ): bool {
    $data = SC_Library_Publications_Discovery_Manifest::metadata_for_post( $article );
    return $data['publication_discovery_approved'] === false
        && $data['wordpress_public_verified'] === false;
}

$a = fresh();
$m = SC_Library_Publications_Discovery_Manifest::metadata_for_post( $a );
check( $m['publication_discovery_approved'] === true
    && $m['wordpress_public_verified'] === true
    && $m['wordpress_password_protected'] === false
    && $m['publication_field'] === 'global-governance'
    && $m['publication_map_key'] === 'international-law'
    && preg_match( '/^[a-f0-9]{64}$/', $m['publication_fingerprint'] ) === 1,
    'valid public article + exact map approval with fingerprint' );

$GLOBALS['environment'] = 'production';
check( SC_Library_Publications_Discovery_Manifest::metadata_for_post( $a ) === array(), 'flag cannot activate in production' );
$GLOBALS['environment'] = 'staging';

$a = fresh();
unset( $GLOBALS['post_meta'][123] );
check( denied( $a ), 'unassociated article denied' );

$a = fresh();
$GLOBALS['post_meta'][123]['_sc_publication_article_map_key_v4337'] = 'unknown-map';
check( denied( $a ), 'unrecognized map denied' );

$a = fresh(); $a->post_status = 'private';
check( denied( $a ), 'private article denied' );

$a = fresh(); $a->post_password = 'password';
check( denied( $a ), 'protected article denied' );

$a = fresh(); $a->publicly_viewable = false;
check( denied( $a ), 'non-viewable article denied' );

$a = fresh(); $a->post_type = 'page';
check( denied( $a ), 'Article Map page not assumed indexed' );

$a = fresh(); $a->url = 'https://sustainablecatalyst.com/science-of-law/?preview=1';
check( denied( $a ), 'preview query URL denied' );

$a = fresh(); $a->url = 'https://another-domain.test/science-of-law/';
check( denied( $a ), 'foreign-origin article denied' );

$a = fresh(); $GLOBALS['options'][ SC_Library_Field_Spotlights::SETTINGS_OPTION ] = array(
    'fields' => array( 'global-governance' => array( 'visible' => 0 ) ),
);
check( denied( $a ), 'hidden field denied' );

$a = fresh(); $GLOBALS['options'][ SC_Library_Field_Spotlights::SETTINGS_OPTION ] = array(
    'panels' => array( 'international-law' => array( 'visible' => 0 ) ),
);
check( denied( $a ), 'hidden map denied' );

$a = fresh(); $GLOBALS['posts'][501]->post_status = 'draft';
check( denied( $a ), 'draft map destination denied' );

$a = fresh(); $GLOBALS['posts'][501]->post_password = 'password';
check( denied( $a ), 'password-protected map denied' );

$a = fresh(); $GLOBALS['posts'][501]->url = 'https://sustainablecatalyst.com/new-map-url/';
check( denied( $a ), 'noncanonical registry alias denied' );

$a = fresh(); $GLOBALS['route_ids'] = array();
check( denied( $a ), 'missing registry destination denied' );

echo "SC-PUB-SEO-03A editorial manifest fixture: all checks passed.\n";
