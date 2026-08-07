<?php
/**
 * Publications editorial surface for Knowledge Library v4.3.2.
 *
 * Public composition: one canonical Article Map hero followed by up to four
 * publication links. There is intentionally no Blog Roll mode.
 *
 * v4.3.2 hardens the public presentation of the complete approved Article Map
 * registry into Spotlight-parity five-row editorial boards. Article resolution is read-only:
 * Spotlight curation is preferred where available, then the canonical Article
 * Map page order, then Knowledge Pathway steps, then same-slug category content.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications {
    public const VERSION = '4.3.2';
    public const SHORTCODE = 'sc_publications';
    public const CACHE_KEY = 'sc_library_publications_topics_v432';
    public const CACHE_TTL = 600;

    private const PAGE_POST_TYPE = 'sc_spot_page';
    private const ITEM_POST_TYPE = 'sc_home_spotlight';

    private const META_PAGE_DESCRIPTION = '_sc_spotlight_page_description';
    private const META_PAGE_ENABLED = '_sc_spotlight_page_enabled';
    private const META_PAGE_ID = '_sc_spotlight_page_id';
    private const META_SOURCE_TYPE = '_sc_spotlight_source_type';
    private const META_SOURCE_ID = '_sc_spotlight_source_id';
    private const META_HEADLINE = '_sc_spotlight_headline';
    private const META_URL = '_sc_spotlight_url';
    private const META_USE_CANONICAL = '_sc_spotlight_use_canonical';
    private const META_ENABLED = '_sc_spotlight_enabled';
    private const META_START_AT = '_sc_spotlight_start_at';
    private const META_END_AT = '_sc_spotlight_end_at';

    public function register_hooks(): void {
        add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
        add_action( 'save_post', array( $this, 'invalidate_cache' ), 120, 3 );
        add_action( 'deleted_post', array( $this, 'invalidate_cache_for_deleted_post' ), 120, 1 );
        add_action( 'transition_post_status', array( $this, 'invalidate_cache_for_status' ), 120, 3 );
    }

    /**
     * Full canonical Article Map registry.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function article_map_registry(): array {
        $file = SC_LIBRARY_DIR . 'includes/data/publications-article-map-registry-v431.php';
        $maps = is_readable( $file ) ? include $file : array();
        if ( ! is_array( $maps ) ) { $maps = array(); }

        // Preserve the v4.3.0 extension point while exposing richer v4.3.1 data.
        $maps = apply_filters( 'sc_library_publications_article_maps', $maps );
        return apply_filters( 'sc_library_publications_registry', $maps );
    }

    /** @param array<string,mixed>|string $atts */
    public function shortcode( $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'title' => __( 'Publications', 'sustainable-catalyst-library' ),
                'intro' => __( 'Structured research and analysis across the Sustainable Catalyst Knowledge Library.', 'sustainable-catalyst-library' ),
                'empty' => 'hide',
            ),
            is_array( $atts ) ? $atts : array(),
            self::SHORTCODE
        );

        $topics = $this->topics();
        if ( empty( $topics ) ) {
            return 'hide' === sanitize_key( (string) $atts['empty'] ) ? '' : '<div class="sc-publications-empty"></div>';
        }

        wp_enqueue_style(
            'sc-library-publications',
            SC_LIBRARY_URL . 'assets/css/sc-library-publications.css',
            array(),
            self::VERSION
        );

        $heading = sanitize_text_field( (string) $atts['title'] );
        $intro = sanitize_textarea_field( (string) $atts['intro'] );
        $instance_id = wp_unique_id( 'sc-publications-' );
        $template = SC_LIBRARY_DIR . 'templates/publications.php';
        if ( ! is_readable( $template ) ) { return ''; }

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    /**
     * Build all registered publication topics in canonical field/topic order.
     *
     * Every Article Map remains visible even when fewer than four companion
     * articles can be resolved; no filler or invented publication is emitted.
     *
     * @return array<int,array<string,mixed>>
     */
    private function topics(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $registry = self::article_map_registry();
        uasort(
            $registry,
            static function ( $left, $right ) {
                $field_cmp = absint( $left['field_order'] ?? 999 ) <=> absint( $right['field_order'] ?? 999 );
                if ( 0 !== $field_cmp ) { return $field_cmp; }
                return absint( $left['order'] ?? 9999 ) <=> absint( $right['order'] ?? 9999 );
            }
        );

        $topics = array();
        foreach ( $registry as $key => $map ) {
            if ( empty( $map['title'] ) || empty( $map['url'] ) || empty( $map['field'] ) ) {
                continue;
            }

            $topic = array(
                'key' => sanitize_title( (string) $key ),
                'title' => sanitize_text_field( (string) $map['title'] ),
                'description' => '',
                'field' => sanitize_text_field( (string) $map['field'] ),
                'field_order' => absint( $map['field_order'] ?? 999 ),
                'group' => sanitize_text_field( (string) ( $map['group'] ?? '' ) ),
                'order' => absint( $map['order'] ?? 9999 ),
                'map_title' => sanitize_text_field( (string) $map['title'] ),
                'map_url' => esc_url_raw( (string) $map['url'] ),
                'aliases' => array_values( array_filter( array_map( 'sanitize_title', (array) ( $map['aliases'] ?? array() ) ) ) ),
                'articles' => array(),
                'article_source' => '',
            );

            $resolved = $this->articles_for_topic( $topic, 4 );
            $topic['articles'] = $resolved['articles'];
            $topic['article_source'] = $resolved['source'];
            $topic['description'] = $resolved['description'];

            $topics[] = $topic;
        }

        $topics = apply_filters( 'sc_library_publications_topics', $topics );
        set_transient( self::CACHE_KEY, $topics, self::CACHE_TTL );
        return $topics;
    }

    /**
     * @param array<string,mixed> $topic
     * @return array{articles:array<int,array{title:string,url:string}>,source:string,description:string}
     */
    private function articles_for_topic( array $topic, int $limit ): array {
        $limit = max( 1, min( 4, $limit ) );
        $description = '';

        $spotlight = $this->spotlight_articles_for_topic( $topic, $limit );
        if ( ! empty( $spotlight['articles'] ) ) {
            return $spotlight;
        }

        $map_post = $this->map_post_for_topic( $topic );
        if ( $map_post instanceof WP_Post ) {
            $description = $this->post_description( $map_post );
            $articles = $this->articles_from_map_post( $map_post, $limit );
            if ( ! empty( $articles ) ) {
                return array( 'articles' => $articles, 'source' => 'article_map', 'description' => $description );
            }
        }

        $pathway = $this->articles_from_pathway( $topic, $limit );
        if ( ! empty( $pathway ) ) {
            return array( 'articles' => $pathway, 'source' => 'knowledge_pathway', 'description' => $description );
        }

        $category = $this->articles_from_category( $topic, $limit, $map_post );
        if ( ! empty( $category ) ) {
            return array( 'articles' => $category, 'source' => 'category', 'description' => $description );
        }

        $articles = apply_filters( 'sc_library_publications_articles_for_topic', array(), $topic, $limit );
        $articles = $this->normalize_articles( $articles, $limit );
        return array(
            'articles' => $articles,
            'source' => $articles ? 'filter' : 'unresolved',
            'description' => $description,
        );
    }

    /**
     * Reuse Homepage Spotlight curation when the registry topic corresponds to
     * an enabled Spotlight subject. Spotlight remains read-only.
     *
     * @param array<string,mixed> $topic
     * @return array{articles:array<int,array{title:string,url:string}>,source:string,description:string}
     */
    private function spotlight_articles_for_topic( array $topic, int $limit ): array {
        if ( ! post_type_exists( self::PAGE_POST_TYPE ) || ! post_type_exists( self::ITEM_POST_TYPE ) ) {
            return array( 'articles' => array(), 'source' => '', 'description' => '' );
        }

        $candidate_keys = array_merge(
            array( sanitize_title( (string) $topic['title'] ), sanitize_title( (string) $topic['key'] ) ),
            (array) ( $topic['aliases'] ?? array() )
        );
        $candidate_keys = array_values( array_unique( array_filter( array_map( 'sanitize_title', $candidate_keys ) ) ) );

        $pages = get_posts(
            array(
                'post_type' => self::PAGE_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_key' => self::META_PAGE_ENABLED,
                'meta_value' => 1,
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            )
        );

        foreach ( $pages as $page ) {
            if ( ! in_array( sanitize_title( $page->post_title ), $candidate_keys, true ) ) {
                continue;
            }
            $articles = $this->page_articles( $page->ID, $limit );
            if ( ! empty( $articles ) ) {
                return array(
                    'articles' => $articles,
                    'source' => 'spotlight',
                    'description' => sanitize_text_field( (string) get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ) ),
                );
            }
        }
        return array( 'articles' => array(), 'source' => '', 'description' => '' );
    }

    /** @return array<int,array{title:string,url:string}> */
    private function page_articles( int $page_id, int $limit ): array {
        $now = current_time( 'timestamp', true );
        $items = get_posts(
            array(
                'post_type' => self::ITEM_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => self::META_PAGE_ID, 'value' => $page_id, 'compare' => '=' ),
                    array( 'key' => self::META_ENABLED, 'value' => 1, 'compare' => '=' ),
                ),
                'orderby' => 'menu_order ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            )
        );

        $articles = array();
        $seen_slots = array();
        foreach ( $items as $item ) {
            $slot = max( 1, min( 5, (int) $item->menu_order ) );
            if ( isset( $seen_slots[ $slot ] ) ) { continue; }

            $start = absint( get_post_meta( $item->ID, self::META_START_AT, true ) );
            $end = absint( get_post_meta( $item->ID, self::META_END_AT, true ) );
            if ( ( $start && $start > $now ) || ( $end && $end <= $now ) ) { continue; }

            $source_type = (string) get_post_meta( $item->ID, self::META_SOURCE_TYPE, true ) ?: 'library';
            if ( 'announcement' === $source_type ) { continue; }
            $source_id = absint( get_post_meta( $item->ID, self::META_SOURCE_ID, true ) );
            $source = $source_id ? get_post( $source_id ) : null;
            if ( ! $this->is_public_source( $source ) ) { continue; }

            $title = (string) get_post_meta( $item->ID, self::META_HEADLINE, true );
            if ( '' === trim( $title ) ) { $title = get_the_title( $source ); }
            $custom_url = esc_url_raw( (string) get_post_meta( $item->ID, self::META_URL, true ) );
            $use_canonical = (bool) get_post_meta( $item->ID, self::META_USE_CANONICAL, true );
            $url = $use_canonical ? get_permalink( $source ) : $custom_url;
            if ( ! $url ) { $url = get_permalink( $source ); }
            if ( ! $title || ! $url ) { continue; }

            $seen_slots[ $slot ] = true;
            $articles[] = array( 'title' => sanitize_text_field( $title ), 'url' => esc_url_raw( $url ) );
            if ( count( $articles ) >= $limit ) { break; }
        }
        return $articles;
    }

    /**
     * Resolve the canonical Article Map URL to a public WordPress post.
     *
     * @param array<string,mixed> $topic
     */
    private function map_post_for_topic( array $topic ): ?WP_Post {
        $relative = (string) ( $topic['map_url'] ?? '' );
        $path = trim( (string) wp_parse_url( $relative, PHP_URL_PATH ), '/' );
        if ( '' === $path ) { return null; }

        $post = get_page_by_path( $path, OBJECT, $this->eligible_source_post_types() );
        if ( $this->is_public_source( $post ) ) { return $post; }

        $post_id = url_to_postid( home_url( '/' . $path . '/' ) );
        $post = $post_id ? get_post( $post_id ) : null;
        return $this->is_public_source( $post ) ? $post : null;
    }

    /**
     * Read the first four public non-map links from the canonical Article Map
     * page itself, preserving authored sequence.
     *
     * @return array<int,array{title:string,url:string}>
     */
    private function articles_from_map_post( WP_Post $map_post, int $limit ): array {
        $content = (string) $map_post->post_content;
        if ( '' === trim( $content ) ) { return array(); }

        if ( ! preg_match_all( '~<a\s[^>]*href=(["\'])(.*?)\1[^>]*>.*?</a>~is', $content, $matches ) ) {
            return array();
        }

        $articles = array();
        $seen = array();
        $map_paths = $this->article_map_path_set();
        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

        foreach ( $matches[2] as $raw_href ) {
            $href = html_entity_decode( trim( (string) $raw_href ), ENT_QUOTES, get_bloginfo( 'charset' ) );
            if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
                continue;
            }

            $absolute = 0 === strpos( $href, 'http://' ) || 0 === strpos( $href, 'https://' )
                ? esc_url_raw( $href )
                : esc_url_raw( home_url( '/' . ltrim( $href, '/' ) ) );
            if ( ! $absolute ) { continue; }

            $link_host = strtolower( (string) wp_parse_url( $absolute, PHP_URL_HOST ) );
            if ( $link_host && $site_host && $link_host !== $site_host ) { continue; }

            $path = '/' . trim( (string) wp_parse_url( $absolute, PHP_URL_PATH ), '/' ) . '/';
            if ( isset( $map_paths[ $path ] ) ) { continue; }

            $post_id = url_to_postid( $absolute );
            if ( ! $post_id ) {
                $page_path = trim( $path, '/' );
                $linked = $page_path ? get_page_by_path( $page_path, OBJECT, $this->eligible_source_post_types() ) : null;
                $post_id = $linked instanceof WP_Post ? absint( $linked->ID ) : 0;
            }
            if ( ! $post_id || $post_id === $map_post->ID || isset( $seen[ $post_id ] ) ) { continue; }

            $post = get_post( $post_id );
            if ( ! $this->is_public_source( $post ) ) { continue; }

            $url = get_permalink( $post );
            $title = get_the_title( $post );
            if ( ! $title || ! $url ) { continue; }

            $seen[ $post_id ] = true;
            $articles[] = array( 'title' => sanitize_text_field( $title ), 'url' => esc_url_raw( $url ) );
            if ( count( $articles ) >= $limit ) { break; }
        }

        return $articles;
    }

    /**
     * Resolve a same-slug Knowledge Pathway and use its first public non-pathway
     * steps as companion publications.
     *
     * @param array<string,mixed> $topic
     * @return array<int,array{title:string,url:string}>
     */
    private function articles_from_pathway( array $topic, int $limit ): array {
        if ( ! class_exists( 'SC_Library_Knowledge_Pathways_Article_Maps' ) || ! post_type_exists( 'sc_knowledge_path' ) ) {
            return array();
        }

        $path = trim( (string) wp_parse_url( (string) $topic['map_url'], PHP_URL_PATH ), '/' );
        $slug = basename( $path );
        $post = get_page_by_path( $slug, OBJECT, 'sc_knowledge_path' );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
            return array();
        }

        $steps = SC_Library_Knowledge_Pathways_Article_Maps::pathway_steps( $post->ID, false );
        $articles = array();
        $seen = array();
        foreach ( $steps as $step ) {
            if ( 'pathway' === ( $step['kind'] ?? '' ) ) { continue; }
            $url = esc_url_raw( (string) ( $step['url'] ?? '' ) );
            $title = sanitize_text_field( (string) ( $step['label'] ?? '' ) );
            if ( ! $url || ! $title || isset( $seen[ $url ] ) ) { continue; }
            $seen[ $url ] = true;
            $articles[] = array( 'title' => $title, 'url' => $url );
            if ( count( $articles ) >= $limit ) { break; }
        }
        return $articles;
    }

    /**
     * Last-resort resolver: same-slug WordPress category, newest public records.
     *
     * @param array<string,mixed> $topic
     * @return array<int,array{title:string,url:string}>
     */
    private function articles_from_category( array $topic, int $limit, ?WP_Post $map_post = null ): array {
        if ( ! taxonomy_exists( 'category' ) ) { return array(); }
        $path = trim( (string) wp_parse_url( (string) $topic['map_url'], PHP_URL_PATH ), '/' );
        $slug = basename( $path );
        $term = get_term_by( 'slug', $slug, 'category' );
        if ( ! $term || is_wp_error( $term ) ) { return array(); }

        $posts = get_posts(
            array(
                'post_type' => $this->eligible_source_post_types(),
                'post_status' => 'publish',
                'posts_per_page' => $limit + 4,
                'orderby' => 'date',
                'order' => 'DESC',
                'post__not_in' => $map_post instanceof WP_Post ? array( $map_post->ID ) : array(),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'category',
                        'field' => 'term_id',
                        'terms' => array( absint( $term->term_id ) ),
                    ),
                ),
                'suppress_filters' => true,
                'no_found_rows' => true,
            )
        );

        $articles = array();
        foreach ( $posts as $post ) {
            if ( ! $this->is_public_source( $post ) ) { continue; }
            $url = get_permalink( $post );
            $title = get_the_title( $post );
            if ( ! $url || ! $title ) { continue; }
            $articles[] = array( 'title' => sanitize_text_field( $title ), 'url' => esc_url_raw( $url ) );
            if ( count( $articles ) >= $limit ) { break; }
        }
        return $articles;
    }

    /** @param mixed $articles @return array<int,array{title:string,url:string}> */
    private function normalize_articles( $articles, int $limit ): array {
        if ( ! is_array( $articles ) ) { return array(); }
        $normalized = array();
        $seen = array();
        foreach ( $articles as $article ) {
            if ( ! is_array( $article ) ) { continue; }
            $title = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
            $url = esc_url_raw( (string) ( $article['url'] ?? '' ) );
            if ( ! $title || ! $url || isset( $seen[ $url ] ) ) { continue; }
            $seen[ $url ] = true;
            $normalized[] = array( 'title' => $title, 'url' => $url );
            if ( count( $normalized ) >= $limit ) { break; }
        }
        return $normalized;
    }

    /** @return array<string,bool> */
    private function article_map_path_set(): array {
        $paths = array();
        foreach ( self::article_map_registry() as $map ) {
            $path = '/' . trim( (string) wp_parse_url( (string) ( $map['url'] ?? '' ), PHP_URL_PATH ), '/' ) . '/';
            if ( '//' !== $path ) { $paths[ $path ] = true; }
        }
        return $paths;
    }

    /** @return string[] */
    private function eligible_source_post_types(): array {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        foreach ( array( 'post', 'page', 'sc_foundation_doc', 'sc_pdf_document', 'sc_research_source', 'sc_document_repository' ) as $known_type ) {
            if ( post_type_exists( $known_type ) ) { $post_types[] = $known_type; }
        }
        $post_types = array_values( array_unique( array_filter( $post_types ) ) );
        return array_values(
            array_diff(
                $post_types,
                array(
                    'attachment',
                    self::ITEM_POST_TYPE,
                    self::PAGE_POST_TYPE,
                    'sc_knowledge_path',
                    'sc_library_concept',
                    'sc_named_entity',
                    'sc_control_vocab',
                    'sc_knowledge_rel',
                )
            )
        );
    }

    /** @param mixed $post */
    private function is_public_source( $post ): bool {
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
            return false;
        }
        $type_object = get_post_type_object( $post->post_type );
        if ( ! $type_object || ( ! $type_object->public && ! $type_object->publicly_queryable ) ) {
            return false;
        }
        if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
            return false;
        }
        return true;
    }

    private function post_description( WP_Post $post ): string {
        $summary = has_excerpt( $post )
            ? get_the_excerpt( $post )
            : wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
        return sanitize_text_field( wp_trim_words( $summary, 28, '…' ) );
    }

    public function invalidate_cache( int $post_id = 0, $post = null, bool $update = false ): void {
        delete_transient( self::CACHE_KEY );
    }

    public function invalidate_cache_for_deleted_post( int $post_id ): void {
        delete_transient( self::CACHE_KEY );
    }

    public function invalidate_cache_for_status( string $new_status, string $old_status, $post ): void {
        if ( $new_status !== $old_status ) { delete_transient( self::CACHE_KEY ); }
    }
}
