<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-sc-library-publication-routes.php';

/** Resolve directory links against this WordPress instance at render time. */
final class SC_Library_Publications_Directory {
    private array $destinations = array();
    private SC_Library_Publication_Routes $routes;

    private function absolute( string $url ): string {
        return str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ? home_url( $url ) : $url;
    }

    public static function enabled(): bool {
        return defined( 'SC_LIBRARY_PUBLICATIONS_DIRECTORY' ) && SC_LIBRARY_PUBLICATIONS_DIRECTORY
            && in_array( wp_get_environment_type(), array( 'local', 'staging' ), true );
    }

    private function destination( string $url ): array {
        $url = $this->absolute( $url );
        if ( isset( $this->destinations[ $url ] ) ) {
            return $this->destinations[ $url ];
        }
        $result = array( 'url' => null, 'post' => null, 'status' => 'unresolved' );
        $parts = wp_parse_url( $url );
        $home = wp_parse_url( home_url( '/' ) );
        if ( ! is_array( $parts ) || empty( $parts['path'] )
            || array_intersect( array( 'user', 'pass', 'query', 'fragment' ), array_keys( $parts ) )
            || array_intersect_key( $parts, array_flip( array( 'scheme', 'host', 'port' ) ) )
                !== array_intersect_key( $home, array_flip( array( 'scheme', 'host', 'port' ) ) ) ) {
            return $this->destinations[ $url ] = $result;
        }
        $post_id = $this->routes->resolve( $url );
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post instanceof WP_Post ) {
            return $this->destinations[ $url ] = $result;
        }
        if ( 'publish' !== $post->post_status || '' !== (string) $post->post_password
            || ! is_post_publicly_viewable( $post ) || 'attachment' === $post->post_type ) {
            $result['status'] = 'unavailable';
            return $this->destinations[ $url ] = $result;
        }
        if ( get_permalink( $post ) === $url ) {
            $result = array( 'url' => $url, 'post' => $post, 'status' => 'verified' );
        }
        return $this->destinations[ $url ] = $result;
    }

    /** This model includes private diagnostics. The template only renders populated entries. */
    public function classify( array $fields ): array {
        $this->destinations = array();
        $urls = array();
        foreach ( $fields as $field ) {
            foreach ( $field['panels'] as $panel ) {
                $urls[] = $this->absolute( (string) ( $panel['canonical_url'] ?? '' ) );
                foreach ( $panel['articles'] ?? array() as $article ) {
                    $urls[] = $this->absolute( (string) ( $article['url'] ?? '' ) );
                }
            }
        }
        $this->routes = new SC_Library_Publication_Routes( $urls );
        $classified = array();
        foreach ( $fields as $field ) {
            foreach ( $field['panels'] as $panel ) {
                $map = $this->destination( (string) ( $panel['canonical_url'] ?? '' ) );
                $articles = array();
                $status = $map['status'];
                if ( 'verified' === $status ) {
                    foreach ( $panel['articles'] ?? array() as $article ) {
                        $destination = $this->destination( (string) ( $article['url'] ?? '' ) );
                        if ( 'verified' === $destination['status'] && $destination['url'] !== $map['url'] ) {
                            $articles[ $destination['url'] ] = array(
                                'url' => $destination['url'],
                                'title' => (string) get_the_title( $destination['post'] ),
                            );
                        }
                    }
                    $content = trim( wp_strip_all_tags( strip_shortcodes( $map['post']->post_content ) ) );
                    if ( '' === $content || empty( $panel['articles'] ) ) {
                        $status = 'empty';
                    } else {
                        $status = $articles ? 'populated' : 'unresolved';
                    }
                }
                $classified[] = array(
                    'field' => (string) $field['title'],
                    'key' => (string) $field['key'],
                    'map_key' => (string) $panel['key'],
                    'registry_url' => (string) ( $panel['canonical_url'] ?? '' ),
                    'url' => $map['url'],
                    'title' => 'verified' === $map['status'] ? (string) get_the_title( $map['post'] ) : '',
                    'status' => $status,
                    'articles' => array_values( $articles ),
                );
            }
        }
        return $classified;
    }

    public function groups( array $fields ): array {
        $groups = array();
        $linked_maps = array();
        $linked_articles = array();
        $classified = $this->classify( $fields );
        foreach ( $classified as $map ) {
            if ( 'populated' === $map['status'] && '' !== trim( $map['title'] ) ) {
                $linked_maps[ $map['url'] ] = false;
            }
        }
        // The first eligible occurrence gets the directory link, in editorial order.
        foreach ( $classified as $map ) {
            if ( ! isset( $linked_maps[ $map['url'] ] ) || $linked_maps[ $map['url'] ]
                || 'populated' !== $map['status'] || '' === trim( $map['title'] ) ) {
                continue;
            }
            $linked_maps[ $map['url'] ] = true;
            $articles = array();
            foreach ( $map['articles'] as $article ) {
                $url = $article['url'];
                if ( isset( $linked_maps[ $url ] ) || isset( $linked_articles[ $url ] ) || '' === trim( $article['title'] ) ) {
                    continue;
                }
                $linked_articles[ $url ] = true;
                $articles[] = $article;
            }
            $map['articles'] = $articles;
            $key = $map['key'];
            $groups[ $key ]['title'] = $map['field'];
            $groups[ $key ]['maps'][] = $map;
        }
        return $groups;
    }
}
