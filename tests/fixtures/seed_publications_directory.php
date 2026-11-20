<?php
/**
 * Run via wp eval-file on the dedicated disposable fixture database.
 * This creates synthetic posts and must never be run on a real site.
 */
if ( 'staging' !== wp_get_environment_type()
    || 'http://127.0.0.1:18765' !== home_url()
    || 'Synthetic staging fixture' !== get_bloginfo( 'name' ) ) {
    throw new RuntimeException( 'Expected the disposable Publications fixture' );
}

function fixture_page( string $slug, string $title, string $content, string $password = '' ): int {
    $existing = get_page_by_path( $slug );
    $id = wp_insert_post( array(
        'ID' => $existing ? $existing->ID : 0,
        'post_name' => $slug,
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_password' => $password,
    ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    return $id;
}

fixture_page( 'publications', 'Publications', '[sc_field_spotlights autoplay="false"]' );
$counts = array();
$panels = array();
foreach ( SC_Library_Field_Spotlights::series_registry() as $key => $entry ) {
    $field = $entry['field_slug'];
    $counts[ $field ] = 1 + ( $counts[ $field ] ?? 0 );
    $i = $counts[ $field ];
    $slug = trim( $entry['canonical_url'], '/' );
    if ( $i > 4 || str_contains( $slug, '/' ) ) { continue; }
    if ( 3 === $i ) {
        fixture_page( $slug, 'Synthetic empty map', '' );
        continue;
    }
    if ( 4 === $i ) {
        fixture_page( $slug, 'Synthetic protected map', 'Protected fixture body', 'fixture-password' );
        continue;
    }
    $article_id = fixture_page( 'fixture-article-' . $key, 'Fixture article: ' . $entry['title'], 'Synthetic public article.' );
    fixture_page( $slug, $entry['title'], '<p>Synthetic map: <a href="' . esc_url( get_permalink( $article_id ) )
        . '">Read the linked publication</a>.</p>' );
    $panels[ $key ] = array( 'title' => $entry['title'], 'articles' => array(
        array( 'source_id' => $article_id, 'url' => get_permalink( $article_id ), 'title' => 'Fixture article', 'enabled' => 1 ),
    ) );
}
update_option( SC_Library_Field_Spotlights::SETTINGS_OPTION, array( 'panels' => $panels ), false );
flush_rewrite_rules( false );
