<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Resolve directory links against this WordPress instance at render time. */
final class SC_Library_Publications_Directory {
    private array $destinations = array();
    private array $route_ids = array();

    private function absolute( string $url ): string {
        return str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ? home_url( $url ) : $url;
    }

    /**
     * Resolve only the flat, unmodified core page/post rewrite pair in bulk.
     * Ambiguous or custom routes retain WordPress's resolver. The batch lives
     * for a single classification only, so a subsequent request sees edits.
     */
    private function prime_flat_routes( array $fields ): void {
        global $wpdb, $wp_rewrite, $wp_filter, $wp;
        $home = home_url( '/' );
        if ( ! $wp_rewrite->use_verbose_page_rules || '/%postname%/' !== get_option( 'permalink_structure' )
            || '/' !== wp_parse_url( $home, PHP_URL_PATH )
            || array_diff( array( 'pagename', 'name', 'page' ), $wp->public_query_vars ) ) {
            return;
        }
        // Core maps registered post-type query variables before WP_Query.
        foreach ( get_post_types( array(), 'objects' ) as $type ) {
            if ( in_array( $type->query_var, array( 'name', 'pagename', 'page' ), true ) ) {
                return;
            }
        }

        // A route or query callback can change which record a request resolves.
        // Core's old-post comments callback does not change the result set.
        $hooks = array( 'url_to_postid', 'parse_query', 'pre_get_posts', 'split_the_query', 'the_posts', 'query_vars' );
        foreach ( $wp_filter as $hook => $callbacks ) {
            if ( ! in_array( $hook, $hooks, true ) && ! str_starts_with( $hook, 'posts_' ) ) {
                continue;
            }
            foreach ( $callbacks->callbacks as $priority ) {
                foreach ( $priority as $callback ) {
                    if ( 'the_posts' !== $hook || '_close_comments_for_old_posts' !== $callback['function'] ) {
                        return;
                    }
                }
            }
        }

        $urls = array();
        foreach ( $fields as $field ) {
            foreach ( $field['panels'] as $panel ) {
                $targets = array_column( $panel['articles'] ?? array(), 'url' );
                $targets[] = $panel['canonical_url'] ?? '';
                foreach ( $targets as $value ) {
                    $url = $this->absolute( (string) $value );
                    if ( str_starts_with( $url, $home ) ) {
                        $slug = substr( $url, strlen( $home ) );
                        if ( preg_match( '/^[a-z][a-z0-9-]*\/$/D', $slug ) ) {
                            $urls[ rtrim( $slug, '/' ) ] = $url;
                        }
                    }
                }
            }
        }
        if ( ! $urls ) { return; }

        $rules = $wp_rewrite->wp_rewrite_rules();
        $expected = array(
            '(.?.+?)(?:/([0-9]+))?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]',
            '([^/]+)(?:/([0-9]+))?/?$' => 'index.php?name=$matches[1]&page=$matches[2]',
        );
        $special_pages = array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) );
        foreach ( $urls as $slug => $url ) {
            $matches = array();
            foreach ( $rules as $pattern => $query ) {
                if ( preg_match( '#^' . $pattern . '#', $slug ) ) {
                    $matches[ $pattern ] = $query;
                    if ( count( $matches ) === 2 ) { break; }
                }
            }
            if ( $matches !== $expected ) {
                unset( $urls[ $slug ] );
            }
        }
        foreach ( array_chunk( $urls, 200, true ) as $batch ) {
            $placeholders = implode( ', ', array_fill( 0, count( $batch ), '%s' ) );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT ID, post_name, post_type, post_parent FROM {$wpdb->posts} WHERE post_name IN ({$placeholders})",
                array_keys( $batch )
            ) );
            if ( $wpdb->last_error || ! is_array( $rows ) ) { continue; }
            $by_slug = array();
            foreach ( $rows as $row ) {
                $by_slug[ strtolower( $row->post_name ) ][] = $row;
            }
            $ids = array();
            foreach ( $batch as $slug => $url ) {
                $candidates = $by_slug[ $slug ] ?? array();
                if ( ! $candidates ) {
                    $this->route_ids[ $url ] = 0;
                } elseif ( count( $candidates ) === 1 && 'page' === $candidates[0]->post_type
                    && 0 === (int) $candidates[0]->post_parent
                    && ! in_array( (int) $candidates[0]->ID, $special_pages, true ) ) {
                    $this->route_ids[ $url ] = (int) $candidates[0]->ID;
                    $ids[] = (int) $candidates[0]->ID;
                }
            }
            _prime_post_caches( $ids, false, false );
        }
    }

    /**
     * Phase PUB-RELEASE-01: preserve the existing local/staging preview and
     * require TWO explicit, independent config flags for any production render.
     *
     * This has no data writes and does not alter the WordPress publication,
     * permalink, or SEO records. Removing either constant immediately
     * restores the established shortcode HTML on the next uncached request.
     */
    public static function enabled(): bool {
        if ( ! defined( 'SC_LIBRARY_PUBLICATIONS_DIRECTORY' )
            || true !== SC_LIBRARY_PUBLICATIONS_DIRECTORY ) {
            return false;
        }

        $environment = wp_get_environment_type();
        if ( in_array( $environment, array( 'local', 'staging' ), true ) ) {
            return true;
        }

        return 'production' === $environment
            && defined( 'SC_LIBRARY_PUBLICATIONS_DIRECTORY_PRODUCTION_RELEASE' )
            && true === SC_LIBRARY_PUBLICATIONS_DIRECTORY_PRODUCTION_RELEASE;
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
        $post_id = $this->route_ids[ $url ] ?? url_to_postid( $url );
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
        $this->route_ids = array();
        $this->prime_flat_routes( $fields );
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
