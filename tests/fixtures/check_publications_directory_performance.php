<?php
/** Run with wp eval-file in the disposable SC-SEO Publications fixture. */
if ( 'staging' !== wp_get_environment_type()
    || 'http://127.0.0.1:18765' !== home_url()
    || 'Synthetic staging fixture' !== get_bloginfo( 'name' ) ) {
    throw new RuntimeException( 'Expected the disposable Publications fixture.' );
}
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-publications-directory.php';

function check_directory( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}

global $wpdb;
$spotlights = new SC_Library_Field_Spotlights();
$fields = $spotlights->public_model();
$routes = 0;
add_action( 'all', static function ( $hook ) use ( &$routes ): void {
    if ( 'url_to_postid' === $hook ) { ++$routes; }
} );
$directory = new SC_Library_Publications_Directory();
$before = $wpdb->num_queries;
$rows = $directory->classify( $fields );
$queries = $wpdb->num_queries - $before;
check_directory( count( $rows ) === 170 && $routes <= 8 && $queries <= 20, 'Classification query budget regressed.' );
$slow = static fn( $url ) => $url;
add_filter( 'url_to_postid', $slow );
try {
    $routes = 0;
    $reference = $directory->classify( $fields );
    check_directory( $routes >= 170 && $reference === $rows, 'Custom resolver fallback or result parity regressed.' );
} finally {
    remove_filter( 'url_to_postid', $slow );
}
$by_key = static fn( array $result ) => array_column( $result, null, 'map_key' );
$key = 'international-law';
$map = get_page_by_path( $key );
$settings_name = SC_Library_Field_Spotlights::SETTINGS_OPTION;
$settings = get_option( $settings_name );
try {
    wp_update_post( array( 'ID' => $map->ID, 'post_password' => 'synthetic-lock' ), true );
    check_directory( 'unavailable' === $by_key( $directory->classify( $fields ) )[ $key ]['status'],
        'Password-protected map still eligible.' );
    wp_update_post( array( 'ID' => $map->ID, 'post_password' => '', 'post_name' => 'fixture-renamed-map' ), true );
    check_directory( 'unresolved' === $by_key( $directory->classify( $fields ) )[ $key ]['status'],
        'Stale permalink still eligible.' );
    wp_update_post( array( 'ID' => $map->ID, 'post_name' => $key, 'post_status' => 'draft' ), true );
    check_directory( 'populated' !== $by_key( $directory->classify( $fields ) )[ $key ]['status'],
        'Draft map still eligible.' );
    wp_update_post( array( 'ID' => $map->ID, 'post_status' => 'publish' ), true );
    $edited = $settings;
    $edited['panels'][ $key ]['articles'] = array();
    update_option( $settings_name, $edited );
    check_directory( 'empty' === $by_key( $directory->classify( $spotlights->public_model() ) )[ $key ]['status'],
        'Curated slot update did not invalidate the public model.' );
} finally {
    wp_update_post( array( 'ID' => $map->ID, 'post_status' => $map->post_status,
        'post_password' => $map->post_password, 'post_name' => $map->post_name ), true );
    update_option( $settings_name, $settings );
}
WP_CLI::success( 'Four checks passed: query budget, resolver parity, live visibility/permalink checks, and curation invalidation.' );
