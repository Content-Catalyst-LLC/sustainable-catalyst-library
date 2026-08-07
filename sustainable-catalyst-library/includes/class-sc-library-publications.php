<?php
/**
 * Publications editorial surface for Knowledge Library v4.3.0.
 *
 * The public composition intentionally mirrors the Homepage Spotlight's calm
 * editorial density: one Article Map hero followed by four curated articles.
 * There is no blog-roll mode in this release.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications {
    public const VERSION = '4.3.0';
    public const SHORTCODE = 'sc_publications';
    private const PAGE_POST_TYPE = 'sc_spot_page';
    private const ITEM_POST_TYPE = 'sc_home_spotlight';

    /** Spotlight metadata is reused as the v4.3.0 publication curation source. */
    private const META_PAGE_DESCRIPTION = '_sc_spotlight_page_description';
    private const META_PAGE_ENABLED = '_sc_spotlight_page_enabled';
    private const META_PAGE_TIER = '_sc_spotlight_page_tier';
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
    }

    /**
     * Canonical Article Map routes for the subjects already used by Spotlight.
     * Filters let later registry releases or site-specific configuration extend
     * the mapping without changing this renderer.
     *
     * @return array<string,array{title:string,url:string,field:string}>
     */
    public static function article_map_registry(): array {
        $maps = array(
            'sustainable-development' => array( 'title' => 'Sustainable Development', 'url' => '/sustainable-development/', 'field' => 'Sustainable Systems' ),
            'planetary-boundaries' => array( 'title' => 'Planetary Boundaries', 'url' => '/planetary-boundaries/', 'field' => 'Sustainable Systems' ),
            'international-law' => array( 'title' => 'International Law', 'url' => '/international-law/', 'field' => 'Global Governance' ),
            'biology' => array( 'title' => 'Biology', 'url' => '/biology/', 'field' => 'Natural Science' ),
            'systems-thinking' => array( 'title' => 'Systems Thinking', 'url' => '/systems-thinking/', 'field' => 'Thinking' ),
            'economics' => array( 'title' => 'Economics', 'url' => '/economic-systems/', 'field' => 'Sustainable Systems' ),
            'artificial-intelligence' => array( 'title' => 'Artificial Intelligence', 'url' => '/artificial-intelligence-systems/', 'field' => 'Technology & Systems Intelligence' ),
            'artificial-intelligence-systems' => array( 'title' => 'Artificial Intelligence Systems', 'url' => '/artificial-intelligence-systems/', 'field' => 'Technology & Systems Intelligence' ),
            'physics' => array( 'title' => 'Physics', 'url' => '/physics/', 'field' => 'Natural Science' ),
            'embedded-edge-systems' => array( 'title' => 'Embedded & Edge Systems', 'url' => '/embedded-and-edge-systems/', 'field' => 'Technology & Systems Intelligence' ),
            'embedded-and-edge-systems' => array( 'title' => 'Embedded & Edge Systems', 'url' => '/embedded-and-edge-systems/', 'field' => 'Technology & Systems Intelligence' ),
            'psychology' => array( 'title' => 'Psychology', 'url' => '/psychology/', 'field' => 'Psychology' ),
            'decision-science' => array( 'title' => 'Decision Science', 'url' => '/decision-science/', 'field' => 'Problem Solving' ),
            'data-systems-analytics' => array( 'title' => 'Data Systems & Analytics', 'url' => '/data-systems-analytics/', 'field' => 'Technology & Systems Intelligence' ),
        );
        return apply_filters( 'sc_library_publications_article_maps', $maps );
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

    /** @return array<int,array<string,mixed>> */
    private function topics(): array {
        if ( ! post_type_exists( self::PAGE_POST_TYPE ) || ! post_type_exists( self::ITEM_POST_TYPE ) ) {
            return array();
        }

        $registry = self::article_map_registry();
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
            )
        );

        $topics = array();
        foreach ( $pages as $page ) {
            $key = sanitize_title( $page->post_title );
            $map = $registry[ $key ] ?? null;
            if ( ! $map ) { continue; }

            $articles = $this->page_articles( $page->ID, 4 );
            if ( count( $articles ) < 4 ) { continue; }

            $topics[] = array(
                'id' => $page->ID,
                'key' => $key,
                'title' => $page->post_title,
                'description' => sanitize_text_field( (string) get_post_meta( $page->ID, self::META_PAGE_DESCRIPTION, true ) ),
                'tier' => sanitize_key( (string) get_post_meta( $page->ID, self::META_PAGE_TIER, true ) ) ?: 'primary',
                'field' => $map['field'],
                'map_title' => $map['title'],
                'map_url' => $map['url'],
                'articles' => $articles,
            );
        }

        return apply_filters( 'sc_library_publications_topics', $topics );
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
            if ( ! $source || 'publish' !== $source->post_status || ! empty( $source->post_password ) ) { continue; }

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
}
