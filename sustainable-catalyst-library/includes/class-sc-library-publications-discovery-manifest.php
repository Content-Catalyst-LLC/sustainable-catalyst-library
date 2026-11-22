<?php
/**
 * SC-PUB-SEO-03A — conservative Publications -> Python candidate manifest.
 *
 * This is an opt-in, staging-only extension to the existing signed WordPress
 * indexing packet. It does NOT make WordPress publication state authoritative
 * for Library research records. The map association is an explicit editorial
 * relationship already saved by Publication <-> Research Graph.
 *
 * This metadata is only a snapshot: a future production release also requires
 * successful revocation/tombstone handoff and complete source reconciliation.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications_Discovery_Manifest {
    public const SCHEMA = 'sc-publications-editorial-manifest/0.1';
    public const MAP_META = '_sc_publication_article_map_key_v4337';

    public static function enabled(): bool {
        return defined( 'SC_LIBRARY_PUBLICATIONS_MANIFEST_SYNC' )
            && SC_LIBRARY_PUBLICATIONS_MANIFEST_SYNC
            && function_exists( 'wp_get_environment_type' )
            && in_array( wp_get_environment_type(), array( 'local', 'staging' ), true );
    }

    /** A canonical WordPress URL, without query or fragment, at the configured origin. */
    private static function canonical_url( string $url ): bool {
        if ( '' === $url || ! function_exists( 'wp_parse_url' ) || ! function_exists( 'home_url' ) ) {
            return false;
        }
        $parts = wp_parse_url( $url );
        $home = wp_parse_url( home_url( '/' ) );
        if ( ! is_array( $parts ) || ! is_array( $home ) ) { return false; }
        if ( ! in_array( $parts['scheme'] ?? '', array( 'https', 'http' ), true )
            || ! isset( $parts['host'], $parts['path'] ) || empty( $parts['path'] )
            || isset( $parts['user'] ) || isset( $parts['pass'] )
            || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
            return false;
        }
        foreach ( array( 'scheme', 'host', 'port' ) as $key ) {
            if ( ( $parts[ $key ] ?? null ) !== ( $home[ $key ] ?? null ) ) { return false; }
        }
        return true;
    }

    /** @return array<string,mixed> */
    public static function metadata_for_post( WP_Post $post ): array {
        if ( ! self::enabled() ) { return array(); }
        $result = array(
            'publication_manifest_schema' => self::SCHEMA,
            'publication_discovery_approved' => false,
            'wordpress_public_verified' => false,
            'wordpress_password_protected' => '' !== (string) $post->post_password,
            'publication_field' => '',
            'publication_map_key' => '',
            'publication_fingerprint' => '',
        );

        // This first slice covers WordPress editorial posts. Article Map pages
        // require a separately validated page manifest, not a post-type change.
        if ( 'post' !== $post->post_type
            || 'publish' !== $post->post_status
            || '' !== (string) $post->post_password
            || ! function_exists( 'is_post_publicly_viewable' )
            || ! is_post_publicly_viewable( $post )
            || ! class_exists( 'SC_Library_Field_Spotlights', false ) ) {
            return $result;
        }

        $url = (string) get_permalink( $post );
        if ( ! self::canonical_url( $url ) ) { return $result; }

        $map_key = sanitize_title( (string) get_post_meta( (int) $post->ID, self::MAP_META, true ) );
        if ( '' === $map_key ) { return $result; }
        $registry = SC_Library_Field_Spotlights::series_registry();
        $map = $registry[ $map_key ] ?? null;
        if ( ! is_array( $map ) ) { return $result; }

        $field = sanitize_key( (string) ( $map['field_slug'] ?? '' ) );
        $defs = SC_Library_Field_Spotlights::field_definitions();
        if ( ! $field || ! isset( $defs[ $field ] ) ) { return $result; }

        // Respect editorially hidden fields and maps without rewriting them.
        $settings = get_option( SC_Library_Field_Spotlights::SETTINGS_OPTION, array() );
        if ( ! is_array( $settings ) ) { return $result; }
        if ( isset( $settings['fields'][ $field ]['visible'] )
            && ! $settings['fields'][ $field ]['visible'] ) { return $result; }
        if ( isset( $settings['panels'][ $map_key ]['visible'] )
            && ! $settings['panels'][ $map_key ]['visible'] ) { return $result; }

        // Verify that the associated Article Map resolves to a real, public,
        // exact WordPress permalink. Redirected/404 registry aliases remain
        // unresolved until editorial canonical reconciliation.
        $map_url = home_url( (string) ( $map['canonical_url'] ?? '' ) );
        if ( ! self::canonical_url( $map_url ) ) { return $result; }
        $map_post_id = absint( url_to_postid( $map_url ) );
        $map_post = $map_post_id ? get_post( $map_post_id ) : null;
        if ( ! $map_post instanceof WP_Post || 'publish' !== $map_post->post_status
            || '' !== (string) $map_post->post_password
            || ! is_post_publicly_viewable( $map_post )
            || (string) get_permalink( $map_post ) !== $map_url ) {
            return $result;
        }

        $result['publication_discovery_approved'] = true;
        $result['wordpress_public_verified'] = true;
        $result['publication_field'] = $field;
        $result['publication_map_key'] = $map_key;
        // Bind the server-signed approval event to the *indexed* publication
        // snapshot. No password, private text, or user identity enters hash.
        $result['publication_fingerprint'] = hash( 'sha256', implode( '|', array(
            (string) $post->ID,
            (string) $post->post_modified_gmt,
            (string) $post->post_status,
            $url,
            $field,
            $map_key,
        ) ) );
        return $result;
    }
}
