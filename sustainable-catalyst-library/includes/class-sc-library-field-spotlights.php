<?php
/**
 * Major Field Spotlight public shell for Sustainable Catalyst Library v4.3.5.
 *
 * Administration: SC Library -> Field Spotlights.
 * This release renders that durable editorial model as Spotlight-parity public
 * field surfaces with thumbnail Article Map heroes, curated article cards, and
 * progressive disclosure while preserving v4.3.3 Publications and v4.2.0 Homepage Spotlight.
 *
 * Model:
 * Major Field -> flattened Series Panels -> permanent Article Map hero ->
 * 2-8 manually curated supporting article slots.
 *
 * Taxonomy groups such as Legal Traditions remain available as source_group
 * metadata, but their child Article Maps are first-class peer panels publicly.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Field_Spotlights {
    public const VERSION = '4.3.5';
    public const SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434';
    public const SETTINGS_GROUP = 'sc_library_field_spotlights_v435';
    public const MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v435';
    public const MODEL_CACHE_TTL = 600;
    public const DEFAULT_PANEL_LIMIT = 8;
    public const DEFAULT_SLOT_COUNT = 4;
    public const MIN_SLOT_COUNT = 2;
    public const MAX_SLOT_COUNT = 8;
    public const SHORTCODE_STACK = 'sc_field_spotlights';
    public const SHORTCODE_SINGLE = 'sc_field_spotlight';
    public const PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v435';

    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 41 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'update_option_' . self::SETTINGS_OPTION, array( $this, 'invalidate_model' ), 10, 2 );
        add_action( 'save_post', array( $this, 'invalidate_for_saved_post' ), 999, 3 );
        add_action( 'transition_post_status', array( $this, 'invalidate_for_status_change' ), 999, 3 );
        add_action( 'before_delete_post', array( $this, 'invalidate_model' ) );
        add_action( 'trashed_post', array( $this, 'invalidate_model' ) );
        add_action( 'untrashed_post', array( $this, 'invalidate_model' ) );
        add_shortcode( self::SHORTCODE_STACK, array( $this, 'shortcode_stack' ) );
        add_shortcode( self::SHORTCODE_SINGLE, array( $this, 'shortcode_single' ) );
    }

    /** @return array<string,array<string,mixed>> */
    public static function field_definitions(): array {
        $file = SC_LIBRARY_DIR . 'includes/data/field-spotlight-fields-v434.php';
        $fields = is_readable( $file ) ? include $file : array();
        if ( ! is_array( $fields ) ) { $fields = array(); }
        return apply_filters( 'sc_library_field_spotlight_fields', $fields );
    }

    /** @return array<string,array<string,mixed>> */
    public static function series_registry(): array {
        $maps = class_exists( 'SC_Library_Publications', false )
            ? SC_Library_Publications::article_map_registry()
            : array();
        $fields = self::field_definitions();
        $field_slugs = array();
        foreach ( $fields as $field_slug => $field ) {
            $title = (string) ( $field['title'] ?? '' );
            if ( $title ) { $field_slugs[ $title ] = $field_slug; }
        }

        $registry = array();
        foreach ( $maps as $map_key => $map ) {
            if ( ! is_array( $map ) ) { continue; }
            $field_title = (string) ( $map['field'] ?? '' );
            $field_slug = $field_slugs[ $field_title ] ?? sanitize_title( $field_title );
            if ( ! $field_slug || ! isset( $fields[ $field_slug ] ) ) { continue; }
            $registry[ $map_key ] = array(
                'key' => $map_key,
                'title' => (string) ( $map['title'] ?? $map_key ),
                'canonical_url' => (string) ( $map['url'] ?? '' ),
                'field' => $field_title,
                'field_slug' => $field_slug,
                'field_order' => absint( $map['field_order'] ?? $fields[ $field_slug ]['order'] ?? 99 ),
                'source_group' => (string) ( $map['group'] ?? '' ),
                'canonical_order' => absint( $map['order'] ?? 999 ),
                'aliases' => is_array( $map['aliases'] ?? null ) ? array_values( $map['aliases'] ) : array(),
                'hero_role' => 'article_map',
            );
        }
        uasort( $registry, static function ( array $a, array $b ): int {
            return ( $a['canonical_order'] <=> $b['canonical_order'] ) ?: strcmp( $a['title'], $b['title'] );
        } );
        return apply_filters( 'sc_library_field_spotlight_series_registry', $registry );
    }

    /** @return array<string,mixed> */
    private function default_settings(): array {
        return array(
            'general' => array(
                'panel_limit' => self::DEFAULT_PANEL_LIMIT,
                'slot_count' => self::DEFAULT_SLOT_COUNT,
                'additional_label' => 'Explore additional fields',
                'hide_additional_label' => 'Hide additional fields',
                'hero_label' => 'Article Map',
                'hero_cta' => 'Explore Article Map',
                'selected_label' => 'Selected from this series',
            ),
            'fields' => array(),
            'panels' => array(),
        );
    }

    /** @return array<string,mixed> */
    private function settings(): array {
        $defaults = $this->default_settings();
        $saved = get_option( self::SETTINGS_OPTION, array() );
        if ( ! is_array( $saved ) ) { return $defaults; }
        $defaults['general'] = array_merge(
            $defaults['general'],
            is_array( $saved['general'] ?? null ) ? $saved['general'] : array()
        );
        $defaults['fields'] = is_array( $saved['fields'] ?? null ) ? $saved['fields'] : array();
        $defaults['panels'] = is_array( $saved['panels'] ?? null ) ? $saved['panels'] : array();
        return $defaults;
    }

    public function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::SETTINGS_OPTION,
            array(
                'type' => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default' => $this->default_settings(),
            )
        );
    }

    /** @param mixed $value @return array<string,mixed> */
    public function sanitize_settings( $value ): array {
        $incoming = is_array( $value ) ? $value : array();
        $existing = $this->settings();
        $context = sanitize_key( (string) ( $incoming['_context'] ?? 'general' ) );

        if ( 'field' === $context ) {
            $field_slug = sanitize_title( (string) ( $incoming['_field_slug'] ?? '' ) );
            $fields = self::field_definitions();
            if ( $field_slug && isset( $fields[ $field_slug ] ) ) {
                $raw_field = is_array( $incoming['fields'][ $field_slug ] ?? null ) ? $incoming['fields'][ $field_slug ] : array();
                $existing['fields'][ $field_slug ] = array(
                    'title' => sanitize_text_field( (string) ( $raw_field['title'] ?? '' ) ),
                    'description' => sanitize_textarea_field( (string) ( $raw_field['description'] ?? '' ) ),
                    'order' => max( 1, min( 99, absint( $raw_field['order'] ?? $fields[ $field_slug ]['order'] ?? 99 ) ) ),
                    'visible' => empty( $raw_field['visible'] ) ? 0 : 1,
                    'panel_limit' => max( 1, min( 24, absint( $raw_field['panel_limit'] ?? self::DEFAULT_PANEL_LIMIT ) ) ),
                );

                $series = self::series_registry();
                foreach ( $incoming['panels'] ?? array() as $panel_key => $raw_panel ) {
                    $panel_key = sanitize_title( (string) $panel_key );
                    if ( ! $panel_key || ! isset( $series[ $panel_key ] ) || $series[ $panel_key ]['field_slug'] !== $field_slug || ! is_array( $raw_panel ) ) { continue; }
                    $slot_count = max( self::MIN_SLOT_COUNT, min( self::MAX_SLOT_COUNT, absint( $raw_panel['slot_count'] ?? self::DEFAULT_SLOT_COUNT ) ) );
                    $current = is_array( $existing['panels'][ $panel_key ] ?? null ) ? $existing['panels'][ $panel_key ] : array();
                    $articles = is_array( $current['articles'] ?? null ) ? $current['articles'] : array();
                    $existing['panels'][ $panel_key ] = array(
                        'title' => sanitize_text_field( (string) ( $raw_panel['title'] ?? '' ) ),
                        'order' => max( 1, min( 999, absint( $raw_panel['order'] ?? $series[ $panel_key ]['canonical_order'] ?? 999 ) ) ),
                        'visible' => empty( $raw_panel['visible'] ) ? 0 : 1,
                        'slot_count' => $slot_count,
                        'hero_title' => sanitize_text_field( (string) ( $current['hero_title'] ?? '' ) ),
                        'hero_description' => sanitize_textarea_field( (string) ( $current['hero_description'] ?? '' ) ),
                        'hero_cta' => sanitize_text_field( (string) ( $current['hero_cta'] ?? '' ) ),
                        'articles' => $this->sanitize_article_slots( $articles ),
                    );
                }
            }
            return $existing;
        }

        if ( 'panel' === $context ) {
            $panel_key = sanitize_title( (string) ( $incoming['_panel_key'] ?? '' ) );
            $series = self::series_registry();
            if ( $panel_key && isset( $series[ $panel_key ] ) ) {
                $raw = is_array( $incoming['panels'][ $panel_key ] ?? null ) ? $incoming['panels'][ $panel_key ] : array();
                $existing['panels'][ $panel_key ] = array(
                    'title' => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
                    'order' => max( 1, min( 999, absint( $raw['order'] ?? $series[ $panel_key ]['canonical_order'] ?? 999 ) ) ),
                    'visible' => empty( $raw['visible'] ) ? 0 : 1,
                    'slot_count' => max( self::MIN_SLOT_COUNT, min( self::MAX_SLOT_COUNT, absint( $raw['slot_count'] ?? self::DEFAULT_SLOT_COUNT ) ) ),
                    'hero_title' => sanitize_text_field( (string) ( $raw['hero_title'] ?? '' ) ),
                    'hero_description' => sanitize_textarea_field( (string) ( $raw['hero_description'] ?? '' ) ),
                    'hero_cta' => sanitize_text_field( (string) ( $raw['hero_cta'] ?? '' ) ),
                    'articles' => $this->sanitize_article_slots( is_array( $raw['articles'] ?? null ) ? $raw['articles'] : array() ),
                );
            }
            return $existing;
        }

        $raw_general = is_array( $incoming['general'] ?? null ) ? $incoming['general'] : array();
        $existing['general']['panel_limit'] = max( 1, min( 24, absint( $raw_general['panel_limit'] ?? $existing['general']['panel_limit'] ?? self::DEFAULT_PANEL_LIMIT ) ) );
        $existing['general']['slot_count'] = max( self::MIN_SLOT_COUNT, min( self::MAX_SLOT_COUNT, absint( $raw_general['slot_count'] ?? $existing['general']['slot_count'] ?? self::DEFAULT_SLOT_COUNT ) ) );
        foreach ( array( 'additional_label', 'hide_additional_label', 'hero_label', 'hero_cta', 'selected_label' ) as $key ) {
            $existing['general'][ $key ] = sanitize_text_field( (string) ( $raw_general[ $key ] ?? $existing['general'][ $key ] ?? '' ) );
        }
        return $existing;
    }

    /** @param array<int,mixed> $articles @return array<int,array<string,mixed>> */
    private function sanitize_article_slots( array $articles ): array {
        $clean = array();
        foreach ( array_slice( $articles, 0, self::MAX_SLOT_COUNT ) as $article ) {
            if ( ! is_array( $article ) ) { continue; }
            $url = esc_url_raw( (string) ( $article['url'] ?? '' ) );
            $source_id = absint( $article['source_id'] ?? 0 );
            if ( ! $source_id && $url ) { $source_id = absint( url_to_postid( $url ) ); }
            $source = $source_id ? get_post( $source_id ) : null;
            $title = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
            if ( ! $title && $source instanceof WP_Post ) { $title = sanitize_text_field( get_the_title( $source ) ); }
            if ( ! $url && $source instanceof WP_Post ) { $url = esc_url_raw( get_permalink( $source ) ); }
            $clean[] = array(
                'source_id' => $source_id,
                'title' => $title,
                'url' => $url,
                'enabled' => empty( $article['enabled'] ) ? 0 : 1,
            );
        }
        return $clean;
    }

    /**
     * Normalized model consumed by later public Field Spotlight rendering.
     *
     * @return array<string,array<string,mixed>>
     */
    public function model(): array {
        $cached = get_transient( self::MODEL_CACHE_KEY );
        if ( is_array( $cached ) ) { return $cached; }

        $settings = $this->settings();
        $field_defs = self::field_definitions();
        $series = self::series_registry();
        $fields = array();

        foreach ( $field_defs as $field_slug => $definition ) {
            $saved_field = is_array( $settings['fields'][ $field_slug ] ?? null ) ? $settings['fields'][ $field_slug ] : array();
            $fields[ $field_slug ] = array(
                'key' => $field_slug,
                'title' => (string) ( $saved_field['title'] ?: $definition['title'] ?? $field_slug ),
                'description' => (string) ( $saved_field['description'] ?: $definition['description'] ?? '' ),
                'browse_url' => (string) ( $definition['browse_url'] ?? '' ),
                'order' => absint( $saved_field['order'] ?? $definition['order'] ?? 99 ),
                'visible' => array_key_exists( 'visible', $saved_field ) ? ! empty( $saved_field['visible'] ) : true,
                'panel_limit' => max( 1, min( 24, absint( $saved_field['panel_limit'] ?? $settings['general']['panel_limit'] ?? self::DEFAULT_PANEL_LIMIT ) ) ),
                'panels' => array(),
            );
        }

        foreach ( $series as $panel_key => $canonical ) {
            $field_slug = (string) $canonical['field_slug'];
            if ( ! isset( $fields[ $field_slug ] ) ) { continue; }
            $saved_panel = is_array( $settings['panels'][ $panel_key ] ?? null ) ? $settings['panels'][ $panel_key ] : array();
            $slot_count = max( self::MIN_SLOT_COUNT, min( self::MAX_SLOT_COUNT, absint( $saved_panel['slot_count'] ?? $settings['general']['slot_count'] ?? self::DEFAULT_SLOT_COUNT ) ) );
            $slots = is_array( $saved_panel['articles'] ?? null ) ? array_slice( $saved_panel['articles'], 0, $slot_count ) : array();
            while ( count( $slots ) < $slot_count ) {
                $slots[] = array( 'source_id' => 0, 'title' => '', 'url' => '', 'enabled' => 0 );
            }
            $fields[ $field_slug ]['panels'][] = array(
                'key' => $panel_key,
                'title' => (string) ( $saved_panel['title'] ?: $canonical['title'] ),
                'canonical_title' => (string) $canonical['title'],
                'canonical_url' => (string) $canonical['canonical_url'],
                'source_group' => (string) $canonical['source_group'],
                'canonical_order' => absint( $canonical['canonical_order'] ),
                'order' => absint( $saved_panel['order'] ?? $canonical['canonical_order'] ),
                'visible' => array_key_exists( 'visible', $saved_panel ) ? ! empty( $saved_panel['visible'] ) : true,
                'hero' => array(
                    'role' => 'article_map',
                    'canonical_url' => (string) $canonical['canonical_url'],
                    'title' => (string) ( $saved_panel['hero_title'] ?: $canonical['title'] ),
                    'description' => (string) ( $saved_panel['hero_description'] ?? '' ),
                    'cta' => (string) ( $saved_panel['hero_cta'] ?: $settings['general']['hero_cta'] ),
                ),
                'slot_count' => $slot_count,
                'articles' => $slots,
                'selection_mode' => 'manual_only',
            );
        }

        foreach ( $fields as &$field ) {
            usort( $field['panels'], static function ( array $a, array $b ): int {
                return ( $a['order'] <=> $b['order'] ) ?: ( $a['canonical_order'] <=> $b['canonical_order'] );
            } );
            $visible_index = 0;
            foreach ( $field['panels'] as &$panel ) {
                if ( ! $panel['visible'] ) {
                    $panel['disclosure'] = 'hidden';
                    continue;
                }
                $panel['disclosure'] = $visible_index < $field['panel_limit'] ? 'primary' : 'additional';
                $visible_index++;
            }
            unset( $panel );
            $field['panel_count'] = $visible_index;
            $field['additional_panel_count'] = max( 0, $visible_index - $field['panel_limit'] );
        }
        unset( $field );

        uasort( $fields, static function ( array $a, array $b ): int {
            return ( $a['order'] <=> $b['order'] ) ?: strcmp( $a['title'], $b['title'] );
        } );

        $fields = apply_filters( 'sc_library_field_spotlight_model', $fields, $settings );
        set_transient( self::MODEL_CACHE_KEY, $fields, self::MODEL_CACHE_TTL );
        return $fields;
    }

    /** @param mixed $old_value @param mixed $value */
    public function invalidate_model( $old_value = null, $value = null ): void {
        delete_transient( self::MODEL_CACHE_KEY );
        delete_transient( self::PUBLIC_CACHE_KEY );
    }

    public function invalidate_for_saved_post( int $post_id = 0, $post = null, bool $update = false ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        delete_transient( self::PUBLIC_CACHE_KEY );
    }

    public function invalidate_for_status_change( string $new_status, string $old_status, $post ): void {
        if ( $new_status !== $old_status ) { delete_transient( self::PUBLIC_CACHE_KEY ); }
    }

    /** @return array<string,array<string,mixed>> */
    public function public_model(): array {
        $cached = get_transient( self::PUBLIC_CACHE_KEY );
        if ( is_array( $cached ) ) { return $cached; }
        $fields = $this->model();
        foreach ( $fields as &$field ) {
            $field['panels'] = array_values( array_filter( $field['panels'], static fn( $panel ) => ! empty( $panel['visible'] ) ) );
            foreach ( $field['panels'] as &$panel ) {
                $panel['hero'] = $this->enrich_hero( $panel['hero'] );
                $public_articles = array();
                foreach ( array_slice( $panel['articles'], 0, absint( $panel['slot_count'] ) ) as $article ) {
                    if ( empty( $article['enabled'] ) || ( empty( $article['source_id'] ) && empty( $article['url'] ) ) ) { continue; }
                    $resolved = $this->enrich_article( $article );
                    if ( $resolved ) { $public_articles[] = $resolved; }
                }
                $panel['articles'] = $public_articles;
                $panel['configured_article_count'] = count( $public_articles );
            }
            unset( $panel );
            $field['panel_count'] = count( $field['panels'] );
            $field['additional_panel_count'] = max( 0, $field['panel_count'] - absint( $field['panel_limit'] ) );
        }
        unset( $field );
        $fields = array_filter( $fields, static fn( $field ) => ! empty( $field['visible'] ) && ! empty( $field['panels'] ) );
        set_transient( self::PUBLIC_CACHE_KEY, $fields, self::MODEL_CACHE_TTL );
        return $fields;
    }

    /** @param array<string,mixed> $hero @return array<string,mixed> */
    private function enrich_hero( array $hero ): array {
        $url = (string) ( $hero['canonical_url'] ?? '' );
        $absolute = $url && 0 !== strpos( $url, 'http' ) ? home_url( $url ) : $url;
        $post_id = $absolute ? absint( url_to_postid( $absolute ) ) : 0;
        $post = $post_id ? get_post( $post_id ) : null;
        if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
            if ( empty( $hero['description'] ) ) { $hero['description'] = $this->source_summary( $post, 42 ); }
            $hero['thumbnail'] = $this->resolve_source_thumbnail( $post );
            $hero['metadata'] = $this->source_metadata( $post );
        } else {
            $hero['thumbnail'] = $this->thumbnail_placeholder( (string) ( $hero['title'] ?? 'Article Map' ) );
            $hero['metadata'] = 'Article Map';
        }
        $hero['url'] = $absolute ?: home_url( '/' );
        return $hero;
    }

    /** @param array<string,mixed> $article @return array<string,mixed>|null */
    private function enrich_article( array $article ): ?array {
        $source_id = absint( $article['source_id'] ?? 0 );
        $url = esc_url_raw( (string) ( $article['url'] ?? '' ) );
        if ( ! $source_id && $url ) { $source_id = absint( url_to_postid( $url ) ); }
        $post = $source_id ? get_post( $source_id ) : null;
        if ( $post instanceof WP_Post ) {
            if ( 'publish' !== $post->post_status || post_password_required( $post ) ) { return null; }
            return array(
                'source_id' => $post->ID,
                'title' => sanitize_text_field( (string) ( $article['title'] ?: get_the_title( $post ) ) ),
                'url' => esc_url_raw( $url ?: get_permalink( $post ) ),
                'summary' => $this->source_summary( $post, 25 ),
                'metadata' => $this->source_metadata( $post ),
                'thumbnail' => $this->resolve_source_thumbnail( $post ),
            );
        }
        if ( ! $url ) { return null; }
        return array(
            'source_id' => 0,
            'title' => sanitize_text_field( (string) ( $article['title'] ?: __( 'Selected article', 'sustainable-catalyst-library' ) ) ),
            'url' => $url,
            'summary' => '',
            'metadata' => __( 'Knowledge Library', 'sustainable-catalyst-library' ),
            'thumbnail' => $this->thumbnail_placeholder( (string) ( $article['title'] ?? 'KL' ) ),
        );
    }

    /** @return array{attachment_id:int,url:string,alt:string,source:string,placeholder:bool} */
    private function resolve_source_thumbnail( WP_Post $source ): array {
        $empty = array( 'attachment_id' => 0, 'url' => '', 'alt' => get_the_title( $source ), 'source' => 'none', 'placeholder' => false );
        $from_attachment = static function ( int $attachment_id, string $kind ) use ( $source, $empty ): array {
            if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) { return $empty; }
            $url = wp_get_attachment_image_url( $attachment_id, 'medium_large' );
            if ( ! $url ) { return $empty; }
            $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            return array( 'attachment_id' => $attachment_id, 'url' => esc_url_raw( $url ), 'alt' => sanitize_text_field( $alt ?: get_the_title( $source ) ), 'source' => sanitize_key( $kind ), 'placeholder' => false );
        };
        $featured_id = get_post_thumbnail_id( $source );
        if ( $featured_id ) { $featured = $from_attachment( $featured_id, 'featured' ); if ( $featured['url'] ) { return $featured; } }
        foreach ( array( '_sc_library_thumbnail_id','_sc_library_cover_attachment_id','_sc_library_cover_image_id','_sc_library_pdf_cover_attachment_id','_sc_library_pdf_attachment_id','_sc_library_foundation_pdf_attachment_id','_sc_library_foundation_attachment_id','_sc_foundation_pdf_attachment_id','sc_library_pdf_attachment_id','_sc_library_source_attachment_id' ) as $meta_key ) {
            $attachment_id = absint( get_post_meta( $source->ID, $meta_key, true ) );
            if ( ! $attachment_id ) { continue; }
            $resolved = $from_attachment( $attachment_id, false !== strpos( $meta_key, 'pdf' ) ? 'pdf_preview' : 'library_meta' );
            if ( $resolved['url'] ) { return $resolved; }
        }
        $attached_images = get_children( array( 'post_parent' => $source->ID, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'menu_order ID', 'order' => 'ASC' ) );
        if ( $attached_images ) { $attached = reset( $attached_images ); if ( $attached instanceof WP_Post ) { $resolved = $from_attachment( $attached->ID, 'attached_image' ); if ( $resolved['url'] ) { return $resolved; } } }
        $content = (string) $source->post_content;
        if ( preg_match( '/\bwp-image-(\d+)\b/', $content, $matches ) ) { $resolved = $from_attachment( absint( $matches[1] ), 'content_image' ); if ( $resolved['url'] ) { return $resolved; } }
        if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
            $content_url = esc_url_raw( html_entity_decode( $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) ) );
            if ( $content_url ) { return array( 'attachment_id' => 0, 'url' => $content_url, 'alt' => get_the_title( $source ), 'source' => 'content_image', 'placeholder' => false ); }
        }
        foreach ( array( '_sc_library_thumbnail_url','_sc_library_cover_image_url','_sc_library_pdf_cover_url','_sc_library_document_thumbnail_url','_thumbnail_url' ) as $meta_key ) {
            $url = esc_url_raw( get_post_meta( $source->ID, $meta_key, true ) );
            if ( $url ) { return array( 'attachment_id' => 0, 'url' => $url, 'alt' => get_the_title( $source ), 'source' => 'image_url', 'placeholder' => false ); }
        }
        $placeholder = $this->thumbnail_placeholder( get_the_title( $source ) );
        $filtered = apply_filters( 'sc_library_field_spotlight_thumbnail', $placeholder, $source );
        return is_array( $filtered ) ? wp_parse_args( $filtered, $placeholder ) : $placeholder;
    }

    /** @return array{attachment_id:int,url:string,alt:string,source:string,placeholder:bool} */
    private function thumbnail_placeholder( string $alt = '' ): array {
        return array( 'attachment_id' => 0, 'url' => '', 'alt' => sanitize_text_field( $alt ), 'source' => 'placeholder', 'placeholder' => true );
    }

    private function source_summary( WP_Post $post, int $words = 30 ): string {
        $summary = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        return wp_trim_words( $summary, max( 10, min( 60, $words ) ), '…' );
    }

    private function source_metadata( WP_Post $post ): string {
        $parts = array();
        $type = get_post_type_object( $post->post_type );
        if ( $type ) { $parts[] = $type->labels->singular_name; }
        foreach ( array( 'sc_document_family', 'sc_document_type', 'category' ) as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) { continue; }
            $terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $terms ) && $terms ) { $parts[] = (string) $terms[0]; break; }
        }
        return implode( ' · ', array_values( array_unique( array_filter( $parts ) ) ) );
    }

    public function shortcode_stack( $atts = array() ): string {
        return $this->render_public( '' );
    }

    public function shortcode_single( $atts = array() ): string {
        $atts = shortcode_atts( array( 'field' => '' ), $atts, self::SHORTCODE_SINGLE );
        return $this->render_public( sanitize_title( (string) $atts['field'] ) );
    }

    private function render_public( string $only_field = '' ): string {
        $fields = $this->public_model();
        if ( $only_field ) {
            if ( ! isset( $fields[ $only_field ] ) ) { return ''; }
            $fields = array( $only_field => $fields[ $only_field ] );
        }
        if ( ! $fields ) { return ''; }
        wp_enqueue_style( 'sc-library-field-spotlights', SC_LIBRARY_URL . 'assets/css/sc-library-field-spotlights.css', array(), self::VERSION );
        wp_enqueue_script( 'sc-library-field-spotlights', SC_LIBRARY_URL . 'assets/js/sc-library-field-spotlights.js', array(), self::VERSION, true );
        $settings = $this->settings();
        $labels = $settings['general'];
        ob_start();
        include SC_LIBRARY_DIR . 'templates/field-spotlights.php';
        return (string) ob_get_clean();
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __( 'Field Spotlights', 'sustainable-catalyst-library' ),
            __( 'Field Spotlights', 'sustainable-catalyst-library' ),
            'manage_options',
            'sc-library-field-spotlights',
            array( $this, 'render_admin_page' )
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $settings = $this->settings();
        $model = $this->model();
        $selected_field = sanitize_title( (string) ( $_GET['field'] ?? array_key_first( $model ) ) );
        if ( ! isset( $model[ $selected_field ] ) ) { $selected_field = (string) array_key_first( $model ); }
        $field = $model[ $selected_field ] ?? array();
        $field_settings = is_array( $settings['fields'][ $selected_field ] ?? null ) ? $settings['fields'][ $selected_field ] : array();
        ?>
        <div class="wrap sc-field-spotlights-admin">
            <h1><?php esc_html_e( 'Field Spotlights', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Configure the Field Spotlight model and the public Spotlight presentation. Publications and Homepage Spotlight remain separate surfaces.', 'sustainable-catalyst-library' ); ?></p>
            <?php settings_errors(); ?>

            <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.8fr);gap:24px;align-items:start;max-width:1480px">
                <div>
                    <form method="post" action="options.php" style="background:#fff;border:1px solid #c3c4c7;padding:22px;margin-bottom:24px">
                        <?php settings_fields( self::SETTINGS_GROUP ); ?>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="general">
                        <h2><?php esc_html_e( 'Global Field Spotlight rules', 'sustainable-catalyst-library' ); ?></h2>
                        <table class="form-table" role="presentation">
                            <tr><th><label for="sc-fs-limit">Visible panels before expansion</label></th><td><input id="sc-fs-limit" type="number" min="1" max="24" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][panel_limit]" value="<?php echo esc_attr( (string) $settings['general']['panel_limit'] ); ?>"><p class="description">Default: 8. Remaining panels are marked Additional rather than removed.</p></td></tr>
                            <tr><th><label for="sc-fs-slots">Default supporting article slots</label></th><td><input id="sc-fs-slots" type="number" min="2" max="8" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][slot_count]" value="<?php echo esc_attr( (string) $settings['general']['slot_count'] ); ?>"><p class="description">Article Map is permanent position 0; supporting slots are positions 1-N.</p></td></tr>
                            <?php foreach ( array( 'additional_label' => 'Additional panels label', 'hide_additional_label' => 'Hide label', 'hero_label' => 'Article Map label', 'hero_cta' => 'Article Map CTA', 'selected_label' => 'Selected articles label' ) as $key => $label ) : ?>
                                <tr><th><label for="sc-fs-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="sc-fs-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings['general'][ $key ] ); ?>"></td></tr>
                            <?php endforeach; ?>
                        </table>
                        <?php submit_button( __( 'Save global rules', 'sustainable-catalyst-library' ) ); ?>
                    </form>

                    <?php if ( $field ) : ?>
                    <form method="post" action="options.php" style="background:#fff;border:1px solid #c3c4c7;padding:22px">
                        <?php settings_fields( self::SETTINGS_GROUP ); ?>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="field">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_field_slug]" value="<?php echo esc_attr( $selected_field ); ?>">
                        <h2><?php echo esc_html( (string) $field['title'] ); ?></h2>
                        <table class="form-table" role="presentation">
                            <tr><th>Display title</th><td><input class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][title]" value="<?php echo esc_attr( (string) ( $field_settings['title'] ?? $field['title'] ) ); ?>"></td></tr>
                            <tr><th>Description</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][description]"><?php echo esc_textarea( (string) ( $field_settings['description'] ?? $field['description'] ) ); ?></textarea></td></tr>
                            <tr><th>Order</th><td><input type="number" min="1" max="99" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][order]" value="<?php echo esc_attr( (string) $field['order'] ); ?>"></td></tr>
                            <tr><th>Visible</th><td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][visible]" value="1" <?php checked( ! empty( $field['visible'] ) ); ?>> Enable this Field Spotlight</label></td></tr>
                            <tr><th>Panel disclosure threshold</th><td><input type="number" min="1" max="24" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][panel_limit]" value="<?php echo esc_attr( (string) $field['panel_limit'] ); ?>"><p class="description">Panels after this position are revealed through the + additional-fields disclosure control on the public Spotlight.</p></td></tr>
                        </table>

                        <h3><?php esc_html_e( 'Flattened series panels', 'sustainable-catalyst-library' ); ?></h3>
                        <p><?php esc_html_e( 'Source groups are retained for knowledge architecture, but every Article Map below is a peer panel in this Field Spotlight.', 'sustainable-catalyst-library' ); ?></p>
                        <table class="widefat striped">
                            <thead><tr><th style="width:80px">Order</th><th>Panel</th><th>Source group</th><th>Canonical Article Map</th><th style="width:90px">Visible</th><th style="width:110px">Slots</th><th style="width:90px">Tier</th><th style="width:100px">Content</th></tr></thead>
                            <tbody>
                            <?php foreach ( $field['panels'] as $panel ) : ?>
                                <tr>
                                    <td><input style="width:70px" type="number" min="1" max="999" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][order]" value="<?php echo esc_attr( (string) $panel['order'] ); ?>"></td>
                                    <td><input class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][title]" value="<?php echo esc_attr( (string) $panel['title'] ); ?>"></td>
                                    <td><?php echo esc_html( $panel['source_group'] ?: '—' ); ?></td>
                                    <td><a href="<?php echo esc_url( home_url( $panel['canonical_url'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $panel['canonical_url'] ); ?></a><br><small>Permanent hero source</small></td>
                                    <td><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][visible]" value="1" <?php checked( ! empty( $panel['visible'] ) ); ?>> Yes</label></td>
                                    <td><input style="width:72px" type="number" min="2" max="8" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][slot_count]" value="<?php echo esc_attr( (string) $panel['slot_count'] ); ?>"></td>
                                    <td><?php echo esc_html( ucfirst( (string) $panel['disclosure'] ) ); ?></td>
                                    <td><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-field-spotlights&field=' . rawurlencode( $selected_field ) . '&panel=' . rawurlencode( $panel['key'] ) ) ); ?>">Edit content</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php submit_button( __( 'Save field and panel model', 'sustainable-catalyst-library' ) ); ?>
                    </form>
                    <?php endif; ?>

                    <?php
                    $selected_panel_key = sanitize_title( (string) ( $_GET['panel'] ?? '' ) );
                    $selected_panel = null;
                    if ( $selected_panel_key && $field ) {
                        foreach ( $field['panels'] as $candidate_panel ) {
                            if ( $candidate_panel['key'] === $selected_panel_key ) { $selected_panel = $candidate_panel; break; }
                        }
                    }
                    if ( $selected_panel ) :
                        $panel_saved = is_array( $settings['panels'][ $selected_panel_key ] ?? null ) ? $settings['panels'][ $selected_panel_key ] : array();
                    ?>
                    <form method="post" action="options.php" style="background:#fff;border:1px solid #c3c4c7;padding:22px;margin-top:24px">
                        <?php settings_fields( self::SETTINGS_GROUP ); ?>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="panel">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_panel_key]" value="<?php echo esc_attr( $selected_panel_key ); ?>">
                        <h2><?php echo esc_html( (string) $selected_panel['title'] ); ?> — Spotlight content</h2>
                        <p class="description"><strong>Article Map hero:</strong> <a href="<?php echo esc_url( home_url( $selected_panel['canonical_url'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $selected_panel['canonical_url'] ); ?></a>. This canonical hero destination cannot be replaced.</p>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][title]" value="<?php echo esc_attr( (string) $selected_panel['title'] ); ?>">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][order]" value="<?php echo esc_attr( (string) $selected_panel['order'] ); ?>">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][visible]" value="<?php echo ! empty( $selected_panel['visible'] ) ? '1' : '0'; ?>">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][slot_count]" value="<?php echo esc_attr( (string) $selected_panel['slot_count'] ); ?>">
                        <table class="form-table" role="presentation">
                            <tr><th>Hero display title</th><td><input class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_title]" value="<?php echo esc_attr( (string) ( $panel_saved['hero_title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $selected_panel['canonical_title'] ); ?>"></td></tr>
                            <tr><th>Hero description</th><td><textarea class="large-text" rows="4" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_description]"><?php echo esc_textarea( (string) ( $panel_saved['hero_description'] ?? '' ) ); ?></textarea><p class="description">Leave blank to use the published Article Map excerpt/content summary.</p></td></tr>
                            <tr><th>Hero CTA</th><td><input class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_cta]" value="<?php echo esc_attr( (string) ( $panel_saved['hero_cta'] ?? '' ) ); ?>" placeholder="Explore Article Map"></td></tr>
                        </table>
                        <h3>Selected articles</h3>
                        <p class="description">Paste the canonical URL of each article you want beneath this Article Map. Thumbnails, summaries, and metadata are resolved from the Library record automatically. Empty slots remain empty.</p>
                        <?php for ( $i = 0; $i < absint( $selected_panel['slot_count'] ); $i++ ) : $article = is_array( $panel_saved['articles'][ $i ] ?? null ) ? $panel_saved['articles'][ $i ] : array(); ?>
                            <fieldset style="border-top:1px solid #dcdcde;padding:14px 0 4px;margin-top:12px">
                                <legend><strong><?php echo esc_html( sprintf( 'Slot %02d', $i + 1 ) ); ?></strong></legend>
                                <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][source_id]" value="<?php echo esc_attr( (string) absint( $article['source_id'] ?? 0 ) ); ?>">
                                <p><label>Article URL<br><input class="widefat" type="url" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][url]" value="<?php echo esc_attr( (string) ( $article['url'] ?? '' ) ); ?>" placeholder="https://sustainablecatalyst.com/..."></label></p>
                                <p><label>Optional display title<br><input class="widefat" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( (string) ( $article['title'] ?? '' ) ); ?>"></label></p>
                                <p><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][enabled]" value="1" <?php checked( ! empty( $article['enabled'] ) ); ?>> Enable this slot</label></p>
                            </fieldset>
                        <?php endfor; ?>
                        <?php submit_button( __( 'Save Spotlight content', 'sustainable-catalyst-library' ) ); ?>
                    </form>
                    <?php endif; ?>
                </div>

                <aside style="background:#fff;border:1px solid #c3c4c7;padding:22px;position:sticky;top:42px">
                    <h2><?php esc_html_e( 'Major fields', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php echo esc_html( sprintf( '%d fields · %d canonical Article Map panels', count( $model ), array_sum( array_map( static fn( $item ) => count( $item['panels'] ), $model ) ) ) ); ?></p>
                    <table class="widefat striped"><thead><tr><th>Field</th><th>Panels</th><th>Additional</th></tr></thead><tbody>
                    <?php foreach ( $model as $field_slug => $item ) : ?>
                        <tr<?php echo $field_slug === $selected_field ? ' style="box-shadow:inset 4px 0 #d63638"' : ''; ?>><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-field-spotlights&field=' . rawurlencode( $field_slug ) ) ); ?>"><strong><?php echo esc_html( (string) $item['title'] ); ?></strong></a></td><td><?php echo esc_html( (string) $item['panel_count'] ); ?></td><td><?php echo esc_html( (string) $item['additional_panel_count'] ); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <hr>
                    <p><strong>v4.3.5 public presentation</strong></p>
                    <p class="description">Use <code>[sc_field_spotlights]</code> for the complete major-field stack or <code>[sc_field_spotlight field=&quot;global-governance&quot;]</code> for one field.</p>
                    <p class="description">Article Map is always the hero. Supporting articles remain manual-only. No automatic article backfill is defined; no latest, popular, taxonomy, random, or automatic substitution path is used.</p>
                </aside>
            </div>
        </div>
        <?php
    }
}
