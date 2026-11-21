<?php
/**
 * Owner-reviewed WP-CLI export. Use a SELECT-only database account and private
 * destination. Nothing from this script is intended for a public repository.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 2 ); }
if ( '1' !== getenv( 'SC_SEO_INVENTORY_APPROVED' ) || ! defined( 'SC_SEO_INVENTORY_VERIFIED_ACCOUNT' ) ) {
    WP_CLI::error( 'Owner approval and a read-only WordPress database account are required.' );
}
if ( ! class_exists( 'SC_Library_Field_Spotlights' ) ) {
    WP_CLI::error( 'Load the approved Library plugin configuration.' );
}

global $wpdb;
if ( $wpdb->get_var( 'SELECT CURRENT_USER()' ) !== SC_SEO_INVENTORY_VERIFIED_ACCOUNT
    || $wpdb->get_var( 'SELECT DATABASE()' ) !== DB_NAME ) {
    WP_CLI::error( 'WordPress database account differs from the pre-bootstrap SELECT-only account.' );
}
if ( false === $wpdb->query( 'START TRANSACTION READ ONLY' ) ) {
    WP_CLI::error( 'Could not start a read-only inventory transaction.' );
}

try {
    $home = home_url( '/' );
    $origin = wp_parse_url( $home );
    $site_identity = array_intersect_key( $origin, array_flip( array( 'scheme', 'host', 'port' ) ) );
    $inspect = static function ( string $candidate, int $source_id = 0 ) use ( $site_identity ): array {
        if ( '/' === substr( $candidate, 0, 1 ) && '//' !== substr( $candidate, 0, 2 ) ) {
            $candidate = home_url( $candidate );
        }
        if ( '' === $candidate && $source_id > 0 ) {
            $candidate = (string) get_permalink( $source_id );
        }
        $parsed = wp_parse_url( $candidate );
        $row = array( 'url' => null, 'exact_permalink' => false, 'status' => null,
                      'publicly_viewable' => null, 'password_protected' => null, 'has_text' => null );
        if ( ! is_array( $parsed ) || empty( $parsed['path'] )
            || array_intersect( array( 'query', 'fragment', 'user', 'pass' ), array_keys( $parsed ) )
            || array_intersect_key( $parsed, $site_identity ) !== $site_identity
            || array_key_exists( 'port', $parsed ) !== array_key_exists( 'port', $site_identity ) ) {
            return $row;
        }
        $row['url'] = $candidate;
        $post_id = $source_id ?: url_to_postid( $candidate );
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post instanceof WP_Post || (string) get_permalink( $post ) !== $candidate ) {
            return $row;
        }
        return array(
            'url' => $candidate,
            'exact_permalink' => true,
            'status' => $post->post_status,
            'publicly_viewable' => is_post_publicly_viewable( $post ),
            'password_protected' => '' !== (string) $post->post_password,
            'has_text' => '' !== trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ),
        );
    };
    $settings = get_option( SC_Library_Field_Spotlights::SETTINGS_OPTION, array() );
    $stored = get_option( SC_Library_Field_Spotlights::PANEL_CONTENT_OPTION, array() );
    $registry = SC_Library_Field_Spotlights::series_registry();
    if ( 170 !== count( $registry ) ) {
        throw new RuntimeException( 'Expected exactly 170 Article Map registry entries.' );
    }
    $rows = array();
    foreach ( $registry as $key => $entry ) {
        $saved = $settings['panels'][ $key ] ?? array();
        $slots = $stored[ $key ]['articles'] ?? $saved['articles'] ?? array();
        $limit = max( SC_Library_Field_Spotlights::MIN_SLOT_COUNT, min( SC_Library_Field_Spotlights::MAX_SLOT_COUNT,
            (int) ( $saved['slot_count'] ?? $settings['general']['slot_count'] ?? SC_Library_Field_Spotlights::DEFAULT_SLOT_COUNT ) ) );
        $articles = array();
        foreach ( array_slice( is_array( $slots ) ? $slots : array(), 0, $limit ) as $slot ) {
            if ( is_array( $slot ) && ( ! empty( $slot['url'] ) || ! empty( $slot['source_id'] ) ) ) {
                $articles[] = $inspect( (string) ( $slot['url'] ?? '' ), (int) ( $slot['source_id'] ?? 0 ) );
            }
        }
        $rows[] = array(
            'map_key' => $key,
            'field' => $entry['field_slug'],
            'editorial_visible' => ! empty( $saved['visible'] ?? true )
                && ! empty( $settings['fields'][ $entry['field_slug'] ]['visible'] ?? true ),
            'map' => $inspect( (string) $entry['canonical_url'] ),
            'curated_articles' => $articles,
        );
    }
    if ( $wpdb->last_error ) {
        throw new RuntimeException( 'Inventory query failed.' );
    }
    $json = wp_json_encode( array(
        'schema' => 'seo.wordpress-publication-inventory.v1',
        'observed_at_utc' => gmdate( 'c' ),
        'property' => $home,
        'maps' => $rows,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    if ( false === $json ) {
        throw new RuntimeException( 'Could not encode publication inventory.' );
    }
} finally {
    $wpdb->query( 'ROLLBACK' );
}
WP_CLI::line( $json );
