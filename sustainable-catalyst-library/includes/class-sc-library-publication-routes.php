<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Request-local batch hints for the standard flat WordPress permalink rules. */
final class SC_Library_Publication_Routes {
    private array $route_ids = array();

    /** @param string[] $targets Absolute URLs; unrecognized routes retain core resolution. */
    public function __construct( array $targets ) {
        $this->prime_flat_routes( $targets );
    }

    public function resolve( string $url ): int {
        return (int) ( $this->route_ids[ $url ] ?? url_to_postid( $url ) );
    }

    /** @return int[] */
    public function primed_ids(): array {
        return array_values( array_unique( array_filter( $this->route_ids ) ) );
    }

    /**
     * Resolve only the flat, unmodified core page/post rewrite pair in bulk.
     * Ambiguous or custom routes retain WordPress's resolver. The batch lives
     * for a single classification only, so a subsequent request sees edits.
     */
    private function prime_flat_routes( array $targets ): void {
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
        foreach ( $targets as $url ) {
            if ( str_starts_with( $url, $home ) ) {
                $slug = substr( $url, strlen( $home ) );
                if ( preg_match( '/^[a-z][a-z0-9-]*\/$/D', $slug ) ) {
                    $urls[ rtrim( $slug, '/' ) ] = $url;
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
}
