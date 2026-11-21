<?php
/** Run with wp eval-file against the disposable Publications fixture only. */
if ( 'staging' !== wp_get_environment_type()
    || 'http://127.0.0.1:18765' !== home_url()
    || 'Synthetic staging fixture' !== get_bloginfo( 'name' ) ) {
    throw new RuntimeException( 'Expected the disposable Publications fixture.' );
}
function check_model( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function map_index( array $fields ): array {
    return array_column( array_merge( ...array_column( $fields, 'panels' ) ), null, 'key' );
}
global $wpdb;
$model = new SC_Library_Field_Spotlights();
$routes = 0;
add_action( 'all', static function ( $hook ) use ( &$routes ): void {
    if ( 'url_to_postid' === $hook ) { ++$routes; }
} );
$model->invalidate_model();
$query_start = $wpdb->num_queries;
$optimized = $model->public_model();
$query_count = $wpdb->num_queries - $query_start;
check_model( $query_count < 230 && $routes < 10 && 170 === count( map_index( $optimized ) ),
    'Cold model exceeds the fixture query or route budget.' );
$before_warm = $wpdb->num_queries;
check_model( $model->public_model() === $optimized && $wpdb->num_queries === $before_warm,
    'Warm model missed the cache or changed the payload.' );

$force_core = static fn( $url ) => $url;
add_filter( 'url_to_postid', $force_core );
try {
    $model->invalidate_model();
    $routes = 0;
    check_model( $model->public_model() === $optimized && $routes >= 170, 'The core route fallback diverged.' );
} finally {
    remove_filter( 'url_to_postid', $force_core );
}

$fields = map_index( $optimized );
check_model( ! str_contains( wp_json_encode( $optimized ), 'Protected fixture body' )
    && 'Article Map' === $fields['common-law-precedent']['hero']['metadata'],
    'Password-protected map content entered the shared cache.' );
$article = get_page_by_path( 'fixture-article-international-law' );
try {
    wp_update_post( array( 'ID' => $article->ID, 'post_password' => 'new-fixture-lock' ), true );
    check_model( array() === map_index( $model->public_model() )['international-law']['articles'],
        'A password-protected article remained in the invalidated public model.' );
} finally {
    wp_update_post( array( 'ID' => $article->ID, 'post_password' => $article->post_password ), true );
}

$permalinks = get_option( 'permalink_structure' );
try {
    $model->public_model();
    update_option( 'permalink_structure', '/%year%/%postname%/' );
    check_model( false === get_transient( SC_Library_Field_Spotlights::PUBLIC_CACHE_KEY ),
        'Permalink setting change did not invalidate the public model.' );
} finally {
    update_option( 'permalink_structure', $permalinks );
}

$key = SC_Library_Field_Spotlights::PANEL_CONTENT_OPTION;
$previous = get_option( $key, false );
try {
    delete_option( $key );
    $model->public_model();
    add_option( $key, array( 'international-law' => array( 'articles' => array() ) ) );
    check_model( array() === map_index( $model->public_model() )['international-law']['articles'],
        'New curated-slot option did not invalidate the model.' );
    delete_option( $key );
    check_model( count( map_index( $model->public_model() )['international-law']['articles'] ) === 1,
        'Removing the curated-slot option did not restore the configured slots.' );
} finally {
    if ( false === $previous ) { delete_option( $key ); } else { update_option( $key, $previous ); }
}
WP_CLI::success( sprintf( 'Six cold-model checks passed; %d SQL queries on the cold budget test.', $query_count ) );
