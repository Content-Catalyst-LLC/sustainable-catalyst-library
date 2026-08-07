<?php
/**
 * Major Field Spotlight system for Sustainable Catalyst Library v4.3.11.
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
 * Inherited v4.3.5 contract: Article Map hero: canonical hero destination cannot be replaced. No automatic article backfill.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Field_Spotlights {
    public const VERSION = '4.3.11';
    public const SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434';
    public const SETTINGS_GROUP = 'sc_library_field_spotlights_v4311';
    public const MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v4311';
    public const MODEL_CACHE_TTL = 600;
    public const DEFAULT_PANEL_LIMIT = 8;
    public const DEFAULT_SLOT_COUNT = 4;
    public const DEFAULT_INTERVAL = 14000;
    public const MIN_SLOT_COUNT = 2;
    public const MAX_SLOT_COUNT = 8;
    public const SHORTCODE_STACK = 'sc_field_spotlights';
    public const SHORTCODE_SINGLE = 'sc_field_spotlight';
    public const PUBLIC_CACHE_KEY = 'sc_library_field_spotlights_public_v4311';

    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 41 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_sc_library_save_field_spotlights', array( $this, 'save_settings_transaction' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
        add_action( 'wp_ajax_sc_library_field_spotlight_search_sources', array( $this, 'ajax_search_sources' ) );
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
                'autoplay' => 1,
                'interval' => self::DEFAULT_INTERVAL,
                'pause_on_hover' => 1,
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

    private function save_form_fields(): void {
        ?>
        <input type="hidden" name="action" value="sc_library_save_field_spotlights">
        <?php wp_nonce_field( 'sc_library_save_field_spotlights', '_sc_library_field_spotlight_nonce' ); ?>
        <?php
    }

    public function save_settings_transaction(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage Field Spotlights.', 'sustainable-catalyst-library' ) );
        }
        check_admin_referer( 'sc_library_save_field_spotlights', '_sc_library_field_spotlight_nonce' );

        $posted = isset( $_POST[ self::SETTINGS_OPTION ] ) ? wp_unslash( $_POST[ self::SETTINGS_OPTION ] ) : array();
        $incoming = is_array( $posted ) ? $posted : array();
        $context = sanitize_key( (string) ( $incoming['_context'] ?? 'general' ) );
        if ( ! in_array( $context, array( 'general', 'field', 'panel' ), true ) ) {
            $context = 'general';
            $incoming['_context'] = 'general';
        }

        // Build the expected normalized value for read-after-write verification,
        // but pass the original submitted payload to update_option(). WordPress
        // applies the registered sanitize callback to update_option(), so passing
        // the already-sanitized value would sanitize a second time and lose the
        // panel/field context carried by the partial editor form.
        $expected = $this->sanitize_settings( $incoming );
        $before = get_option( self::SETTINGS_OPTION, array() );
        $changed = $before !== $expected;
        update_option( self::SETTINGS_OPTION, $incoming, false );

        // Always clear both model layers after an intentional save. This makes the
        // public Spotlight reflect content changes immediately even when the option
        // payload is identical after WordPress normalization.
        $this->invalidate_model();

        $persisted = get_option( self::SETTINGS_OPTION, array() );
        $verified = is_array( $persisted ) && $persisted === $expected;
        $saved = $verified;

        $field_slug = sanitize_title( (string) ( $incoming['_field_slug'] ?? '' ) );
        $panel_key = sanitize_title( (string) ( $incoming['_panel_key'] ?? '' ) );
        if ( 'panel' === $context && $panel_key ) {
            $series = self::series_registry();
            if ( isset( $series[ $panel_key ] ) ) {
                $field_slug = sanitize_title( (string) ( $series[ $panel_key ]['field_slug'] ?? $field_slug ) );
            }
        }

        $args = array(
            'page' => 'sc-library-field-spotlights',
            'sc_fs_saved' => $saved ? '1' : '0',
            'sc_fs_changed' => $changed ? '1' : '0',
            'sc_fs_context' => $context,
        );
        if ( $field_slug ) { $args['field'] = $field_slug; }
        if ( $panel_key ) { $args['panel'] = $panel_key; }

        $redirect = add_query_arg( $args, admin_url( 'admin.php' ) );
        if ( $panel_key ) { $redirect .= '#sc-fs-panel-editor'; }
        wp_safe_redirect( $redirect );
        exit;
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
                    'panel_limit' => self::DEFAULT_PANEL_LIMIT,
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
        $existing['general']['panel_limit'] = self::DEFAULT_PANEL_LIMIT;
        $existing['general']['autoplay'] = empty( $raw_general['autoplay'] ) ? 0 : 1;
        $existing['general']['pause_on_hover'] = empty( $raw_general['pause_on_hover'] ) ? 0 : 1;
        $existing['general']['interval'] = max( 8000, min( 60000, absint( $raw_general['interval'] ?? $existing['general']['interval'] ?? self::DEFAULT_INTERVAL ) ) );
        $existing['general']['slot_count'] = max( self::MIN_SLOT_COUNT, min( self::MAX_SLOT_COUNT, absint( $raw_general['slot_count'] ?? $existing['general']['slot_count'] ?? self::DEFAULT_SLOT_COUNT ) ) );
        foreach ( array( 'additional_label', 'hide_additional_label', 'hero_label', 'hero_cta', 'selected_label' ) as $key ) {
            $existing['general'][ $key ] = sanitize_text_field( (string) ( $raw_general[ $key ] ?? $existing['general'][ $key ] ?? '' ) );
        }
        return $existing;
    }

    /** @param array<int,mixed> $articles @return array<int,array<string,mixed>> */
    private function sanitize_article_slots( array $articles ): array {
        $clean = array();
        for ( $position = 0; $position < self::MAX_SLOT_COUNT; $position++ ) {
            if ( ! array_key_exists( $position, $articles ) ) { continue; }
            $article = $articles[ $position ];
            if ( ! is_array( $article ) ) { continue; }
            $url = esc_url_raw( (string) ( $article['url'] ?? '' ) );
            $source_id = absint( $article['source_id'] ?? 0 );
            $source_id = $this->resolve_article_source_id( $source_id, $url );
            $source = $source_id ? get_post( $source_id ) : null;
            $title = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
            if ( ! $title && $source instanceof WP_Post ) { $title = sanitize_text_field( get_the_title( $source ) ); }
            if ( ! $url && $source instanceof WP_Post ) { $url = esc_url_raw( get_permalink( $source ) ); }
            $has_selection = $source_id > 0 || '' !== $url;
            $clean[ $position ] = array(
                'source_id' => $source_id,
                'title' => $title,
                'url' => $url,
                // A populated slot is active by definition. Clearing the slot is the
                // deliberate way to remove it from the public Field Spotlight.
                'enabled' => $has_selection ? 1 : 0,
            );
        }
        ksort( $clean, SORT_NUMERIC );
        return $clean;
    }

    private function resolve_article_source_id( int $source_id, string $url = '' ): int {
        if ( $source_id > 0 ) {
            $source = get_post( $source_id );
            if ( $source instanceof WP_Post ) { return $source_id; }
        }
        if ( ! $url ) { return 0; }
        $resolved = absint( url_to_postid( $url ) );
        if ( $resolved > 0 ) { return $resolved; }
        $path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
        if ( ! $path ) { return 0; }
        $slug = sanitize_title( basename( $path ) );
        if ( ! $slug ) { return 0; }
        $matches = get_posts(
            array(
                'name' => $slug,
                'post_type' => $this->eligible_source_post_types(),
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'suppress_filters' => true,
            )
        );
        return ! empty( $matches[0] ) && $matches[0] instanceof WP_Post ? absint( $matches[0]->ID ) : 0;
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
                'panel_limit' => self::DEFAULT_PANEL_LIMIT,
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
            $configured_count = 0;
            foreach ( $slots as $slot ) {
                if ( ! empty( $slot['source_id'] ) || ! empty( $slot['url'] ) ) { $configured_count++; }
            }
            $readiness = 0 === $configured_count ? 'empty' : ( $configured_count >= $slot_count ? 'ready' : 'partial' );
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
                'configured_article_count' => $configured_count,
                'readiness' => $readiness,
                'articles' => $slots,
                'selection_mode' => 'manual_only',
            );
        }

        foreach ( $fields as &$field ) {
            usort( $field['panels'], static function ( array $a, array $b ): int {
                return ( $a['order'] <=> $b['order'] ) ?: ( $a['canonical_order'] <=> $b['canonical_order'] );
            } );
            $visible_index = 0;
            $ready_count = 0;
            $partial_count = 0;
            $empty_count = 0;
            $hidden_count = 0;
            $configured_articles = 0;
            $supporting_slots = 0;
            foreach ( $field['panels'] as &$panel ) {
                if ( ! $panel['visible'] ) {
                    $panel['disclosure'] = 'hidden';
                    $hidden_count++;
                    continue;
                }
                $panel['disclosure'] = $visible_index < $field['panel_limit'] ? 'primary' : 'additional';
                $visible_index++;
                $configured_articles += absint( $panel['configured_article_count'] ?? 0 );
                $supporting_slots += absint( $panel['slot_count'] ?? 0 );
                if ( 'ready' === $panel['readiness'] ) { $ready_count++; }
                elseif ( 'partial' === $panel['readiness'] ) { $partial_count++; }
                else { $empty_count++; }
            }
            unset( $panel );
            $field['panel_count'] = $visible_index;
            $field['additional_panel_count'] = max( 0, $visible_index - $field['panel_limit'] );
            $field['ready_panel_count'] = $ready_count;
            $field['partial_panel_count'] = $partial_count;
            $field['empty_panel_count'] = $empty_count;
            $field['hidden_panel_count'] = $hidden_count;
            $field['configured_article_count'] = $configured_articles;
            $field['supporting_slot_count'] = $supporting_slots;
            $field['completion_percent'] = $supporting_slots ? min( 100, (int) round( ( $configured_articles / $supporting_slots ) * 100 ) ) : 0;
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
                    if ( empty( $article['source_id'] ) && empty( $article['url'] ) ) { continue; }
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
        $source_id = $this->resolve_article_source_id( $source_id, $url );
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
        $atts = shortcode_atts( array( 'autoplay' => 'true', 'interval' => (string) self::DEFAULT_INTERVAL, 'pause_on_hover' => 'true' ), $atts, self::SHORTCODE_STACK );
        return $this->render_public( '', $atts );
    }

    public function shortcode_single( $atts = array() ): string {
        $atts = shortcode_atts( array( 'field' => '', 'autoplay' => 'true', 'interval' => (string) self::DEFAULT_INTERVAL, 'pause_on_hover' => 'true' ), $atts, self::SHORTCODE_SINGLE );
        $field = sanitize_title( (string) $atts['field'] );
        unset( $atts['field'] );
        return $this->render_public( $field, $atts );
    }

    private function shortcode_bool( $value, bool $default = true ): bool {
        if ( is_bool( $value ) ) { return $value; }
        $value = strtolower( trim( (string) $value ) );
        if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) { return true; }
        if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) { return false; }
        return $default;
    }

    private function render_public( string $only_field = '', array $display = array() ): string {
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
        $autoplay = $this->shortcode_bool( $display['autoplay'] ?? $settings['general']['autoplay'] ?? true, true );
        $pause_on_hover = $this->shortcode_bool( $display['pause_on_hover'] ?? $settings['general']['pause_on_hover'] ?? true, true );
        $interval = max( 8000, min( 60000, absint( $display['interval'] ?? $settings['general']['interval'] ?? self::DEFAULT_INTERVAL ) ) );
        ob_start();
        include SC_LIBRARY_DIR . 'templates/field-spotlights.php';
        return (string) ob_get_clean();
    }

    public function admin_enqueue( string $hook_suffix = '' ): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
        if ( 'sc-library-field-spotlights' !== $page ) { return; }
        wp_enqueue_style( 'sc-library-field-spotlights-admin', SC_LIBRARY_URL . 'assets/css/sc-library-field-spotlights-admin.css', array(), self::VERSION );
        wp_enqueue_script( 'sc-library-field-spotlights-admin', SC_LIBRARY_URL . 'assets/js/sc-library-field-spotlights-admin.js', array(), self::VERSION, true );
        wp_localize_script(
            'sc-library-field-spotlights-admin',
            'SCFieldSpotlightsAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'sc_library_field_spotlight_search' ),
                'searching' => __( 'Searching…', 'sustainable-catalyst-library' ),
                'noResults' => __( 'No published Library records found.', 'sustainable-catalyst-library' ),
                'select' => __( 'Select article', 'sustainable-catalyst-library' ),
                'configured' => __( 'Configured', 'sustainable-catalyst-library' ),
                'empty' => __( 'Empty slot', 'sustainable-catalyst-library' ),
            )
        );
    }

    public function ajax_search_sources(): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'sc_library_field_spotlight_search', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sustainable-catalyst-library' ) ), 403 );
        }
        $query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        if ( strlen( trim( $query ) ) < 2 ) { wp_send_json_success( array( 'items' => array() ) ); }
        $items = array();
        foreach ( $this->search_source_posts( $query, 20 ) as $post ) {
            $type = get_post_type_object( $post->post_type );
            $thumbnail = $this->resolve_source_thumbnail( $post );
            $items[] = array(
                'id' => $post->ID,
                'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
                'excerpt' => $this->source_summary( $post, 20 ),
                'type' => $type ? $type->labels->singular_name : $post->post_type,
                'metadata' => $this->source_metadata( $post ),
                'url' => get_permalink( $post ),
                'thumbnailUrl' => (string) ( $thumbnail['url'] ?? '' ),
                'thumbnailAlt' => (string) ( $thumbnail['alt'] ?? get_the_title( $post ) ),
            );
        }
        wp_send_json_success( array( 'items' => $items ) );
    }

    /** @return string[] */
    private function eligible_source_post_types(): array {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        foreach ( array( 'post', 'page', 'sc_foundation_doc', 'sc_pdf_document', 'sc_research_source', 'sc_knowledge_pathway', 'sc_document_repository' ) as $known_type ) {
            if ( post_type_exists( $known_type ) ) { $post_types[] = $known_type; }
        }
        return array_values( array_diff( array_values( array_unique( array_filter( $post_types ) ) ), array( 'attachment' ) ) );
    }

    private function is_valid_source_record( $post ): bool {
        return $post instanceof WP_Post
            && 'publish' === $post->post_status
            && empty( $post->post_password )
            && in_array( $post->post_type, $this->eligible_source_post_types(), true );
    }

    /** @return WP_Post[] */
    private function search_source_posts( string $query, int $limit = 20 ): array {
        global $wpdb;
        $query = trim( $query );
        $limit = max( 1, min( 50, $limit ) );
        $post_types = $this->eligible_source_post_types();
        $posts_by_id = array();
        $add_post = function ( $candidate ) use ( &$posts_by_id ): void {
            $post = $candidate instanceof WP_Post ? $candidate : get_post( absint( $candidate ) );
            if ( $this->is_valid_source_record( $post ) ) { $posts_by_id[ $post->ID ] = $post; }
        };
        if ( ctype_digit( $query ) ) { $add_post( absint( $query ) ); }
        if ( wp_http_validate_url( $query ) ) {
            $add_post( url_to_postid( $query ) );
            $path = (string) wp_parse_url( $query, PHP_URL_PATH );
            $slug = sanitize_title( basename( untrailingslashit( $path ) ) );
            if ( $slug ) {
                foreach ( get_posts( array( 'name' => $slug, 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'suppress_filters' => true ) ) as $post ) { $add_post( $post ); }
            }
        }
        foreach ( get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => $limit, 's' => $query, 'orderby' => 'relevance date', 'order' => 'DESC', 'suppress_filters' => true ) ) as $post ) { $add_post( $post ); }
        if ( count( $posts_by_id ) < $limit && $post_types ) {
            $type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
            $title_like = '%' . $wpdb->esc_like( $query ) . '%';
            $slug_like = '%' . $wpdb->esc_like( sanitize_title( $query ) ) . '%';
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$type_placeholders}) AND (post_title LIKE %s OR post_name LIKE %s) ORDER BY CASE WHEN post_title = %s THEN 0 ELSE 1 END, post_modified_gmt DESC LIMIT %d";
            $params = array_merge( $post_types, array( $title_like, $slug_like, $query, $limit * 2 ) );
            foreach ( $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) as $candidate_id ) {
                $add_post( $candidate_id );
                if ( count( $posts_by_id ) >= $limit ) { break; }
            }
        }
        return array_slice( array_values( $posts_by_id ), 0, $limit );
    }

    /** @param array<string,mixed> $panel */
    private function panel_status_label( array $panel ): string {
        if ( empty( $panel['visible'] ) ) { return __( 'Hidden', 'sustainable-catalyst-library' ); }
        if ( 'ready' === ( $panel['readiness'] ?? '' ) ) { return __( 'Ready', 'sustainable-catalyst-library' ); }
        if ( 'partial' === ( $panel['readiness'] ?? '' ) ) { return __( 'Partial', 'sustainable-catalyst-library' ); }
        return __( 'Empty', 'sustainable-catalyst-library' );
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
        $selected_panel_key = sanitize_title( (string) ( $_GET['panel'] ?? '' ) );
        $selected_panel = null;
        if ( $selected_panel_key && $field ) {
            foreach ( $field['panels'] as $candidate_panel ) {
                if ( $candidate_panel['key'] === $selected_panel_key ) { $selected_panel = $candidate_panel; break; }
            }
        }

        $total_panels = 0;
        $total_ready = 0;
        $total_partial = 0;
        $total_empty = 0;
        $total_hidden = 0;
        $total_configured = 0;
        $total_slots = 0;
        foreach ( $model as $item ) {
            $total_panels += count( $item['panels'] );
            $total_ready += absint( $item['ready_panel_count'] ?? 0 );
            $total_partial += absint( $item['partial_panel_count'] ?? 0 );
            $total_empty += absint( $item['empty_panel_count'] ?? 0 );
            $total_hidden += absint( $item['hidden_panel_count'] ?? 0 );
            $total_configured += absint( $item['configured_article_count'] ?? 0 );
            $total_slots += absint( $item['supporting_slot_count'] ?? 0 );
        }
        $overall_completion = $total_slots ? min( 100, (int) round( ( $total_configured / $total_slots ) * 100 ) ) : 0;
        ?>
        <div class="wrap sc-fs-admin" data-sc-field-spotlights-admin="v4.3.11">
            <header class="sc-fs-admin__hero">
                <div>
                    <p class="sc-fs-admin__eyebrow">KNOWLEDGE LIBRARY · FIELD SPOTLIGHTS</p>
                    <h1><?php esc_html_e( 'Field Spotlight Console', 'sustainable-catalyst-library' ); ?></h1>
                    <p><?php esc_html_e( 'Curate Article Map-led field panels, supporting articles, panel visibility, and progressive disclosure from one editorial workspace.', 'sustainable-catalyst-library' ); ?></p>
                </div>
                <div class="sc-fs-admin__shortcodes"><span><code>[sc_field_spotlights]</code></span><span><code>[sc_field_spotlight field=&quot;global-governance&quot;]</code></span></div>
            </header>
            <?php
            $save_notice = isset( $_GET['sc_fs_saved'] ) ? sanitize_key( (string) wp_unslash( $_GET['sc_fs_saved'] ) ) : '';
            if ( '1' === $save_notice ) :
            ?>
                <div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'Field Spotlight content saved.', 'sustainable-catalyst-library' ); ?></strong> <?php esc_html_e( 'The public Spotlight cache was cleared and the saved values were verified.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php elseif ( '0' === $save_notice ) : ?>
                <div class="notice notice-error"><p><strong><?php esc_html_e( 'Field Spotlight content could not be verified after saving.', 'sustainable-catalyst-library' ); ?></strong> <?php esc_html_e( 'No public cache was retained. Please retry the save.', 'sustainable-catalyst-library' ); ?></p></div>
            <?php endif; ?>
            <?php settings_errors(); ?>

            <section class="sc-fs-admin__metrics" aria-label="Field Spotlight readiness">
                <article><span>MAJOR FIELDS</span><strong><?php echo esc_html( (string) count( $model ) ); ?></strong><small>Independent Spotlights</small></article>
                <article><span>ARTICLE MAP PANELS</span><strong><?php echo esc_html( (string) $total_panels ); ?></strong><small>Canonical hero panels</small></article>
                <article><span>READY PANELS</span><strong><?php echo esc_html( (string) $total_ready ); ?></strong><small><?php echo esc_html( sprintf( '%d partial · %d empty', $total_partial, $total_empty ) ); ?></small></article>
                <article><span>CURATED ARTICLES</span><strong><?php echo esc_html( sprintf( '%d / %d', $total_configured, $total_slots ) ); ?></strong><small><?php echo esc_html( sprintf( '%d%% configured', $overall_completion ) ); ?></small></article>
            </section>

            <details class="sc-fs-admin__global-rules">
                <summary><span><strong>Global presentation rules</strong><small>Spotlight playback, fixed eight-panel disclosure, slot count, and public labels</small></span><span aria-hidden="true">+</span></summary>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php $this->save_form_fields(); ?>
                    <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="general">
                    <div class="sc-fs-admin__rule-grid">
                        <label><span>Visible panels before expansion</span><input type="number" value="8" readonly><input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][panel_limit]" value="8"><small>Fixed at 8. Panel 9+ remains behind progressive disclosure.</small></label>
                        <label><span>Automatic rotation</span><select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][autoplay]"><option value="1" <?php selected( ! empty( $settings['general']['autoplay'] ) ); ?>>On</option><option value="0" <?php selected( empty( $settings['general']['autoplay'] ) ); ?>>Off</option></select><small>Defaults to the Homepage Spotlight behavior.</small></label>
                        <label><span>Rotation interval (ms)</span><input type="number" min="8000" max="60000" step="1000" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][interval]" value="<?php echo esc_attr( (string) ( $settings['general']['interval'] ?? self::DEFAULT_INTERVAL ) ); ?>"><small>Homepage Spotlight parity default: 14000.</small></label>
                        <label><span>Pause on hover</span><select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][pause_on_hover]"><option value="1" <?php selected( ! empty( $settings['general']['pause_on_hover'] ) ); ?>>On</option><option value="0" <?php selected( empty( $settings['general']['pause_on_hover'] ) ); ?>>Off</option></select><small>Keyboard focus, touch interaction, hidden tabs, and reduced motion also hold playback.</small></label>
                        <label><span>Default supporting article slots</span><input type="number" min="2" max="8" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][slot_count]" value="<?php echo esc_attr( (string) $settings['general']['slot_count'] ); ?>"><small>Article Map remains permanent hero position 0.</small></label>
                        <?php foreach ( array( 'additional_label' => 'Additional panels label', 'hide_additional_label' => 'Hide label', 'hero_label' => 'Article Map label', 'hero_cta' => 'Article Map CTA', 'selected_label' => 'Selected articles label' ) as $key => $label ) : ?>
                            <label><span><?php echo esc_html( $label ); ?></span><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings['general'][ $key ] ); ?>"></label>
                        <?php endforeach; ?>
                    </div>
                    <?php submit_button( __( 'Save global rules', 'sustainable-catalyst-library' ) ); ?>
                </form>
            </details>

            <div class="sc-fs-admin__workspace">
                <aside class="sc-fs-admin__fields">
                    <div class="sc-fs-admin__section-head"><div><p>FIELD INDEX</p><h2>Major fields</h2></div><span><?php echo esc_html( (string) count( $model ) ); ?></span></div>
                    <div class="sc-fs-admin__field-list">
                    <?php foreach ( $model as $field_slug => $item ) :
                        $completion = absint( $item['completion_percent'] ?? 0 );
                        $active_class = $field_slug === $selected_field ? ' is-active' : '';
                    ?>
                        <a class="sc-fs-admin__field<?php echo esc_attr( $active_class ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-field-spotlights&field=' . rawurlencode( $field_slug ) ) ); ?>">
                            <span class="sc-fs-admin__field-order"><?php echo esc_html( str_pad( (string) absint( $item['order'] ), 2, '0', STR_PAD_LEFT ) ); ?></span>
                            <span class="sc-fs-admin__field-copy"><strong><?php echo esc_html( (string) $item['title'] ); ?></strong><small><?php echo esc_html( sprintf( '%d panels · %d ready', absint( $item['panel_count'] ), absint( $item['ready_panel_count'] ?? 0 ) ) ); ?></small><span class="sc-fs-admin__progress"><i style="width:<?php echo esc_attr( (string) $completion ); ?>%"></i></span></span>
                            <span class="sc-fs-admin__field-percent"><?php echo esc_html( (string) $completion ); ?>%</span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </aside>

                <main class="sc-fs-admin__main">
                    <?php if ( $field ) : ?>
                    <section class="sc-fs-admin__field-editor">
                        <header class="sc-fs-admin__field-header">
                            <div><p>ACTIVE FIELD</p><h2><?php echo esc_html( (string) $field['title'] ); ?></h2><span><?php echo esc_html( sprintf( '%d panels · %d additional · %d%% supporting content configured', absint( $field['panel_count'] ), absint( $field['additional_panel_count'] ), absint( $field['completion_percent'] ?? 0 ) ) ); ?></span></div>
                            <a class="button" href="<?php echo esc_url( home_url( (string) $field['browse_url'] ) ); ?>" target="_blank" rel="noopener">Browse field ↗</a>
                        </header>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sc-fs-admin__field-form">
                            <?php $this->save_form_fields(); ?>
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="field">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_field_slug]" value="<?php echo esc_attr( $selected_field ); ?>">
                            <details class="sc-fs-admin__field-settings">
                                <summary><strong>Field settings</strong><span>Edit title, description, order and disclosure threshold</span></summary>
                                <div class="sc-fs-admin__field-settings-grid">
                                    <label><span>Display title</span><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][title]" value="<?php echo esc_attr( (string) ( $field_settings['title'] ?? $field['title'] ) ); ?>"></label>
                                    <label><span>Order</span><input type="number" min="1" max="99" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][order]" value="<?php echo esc_attr( (string) $field['order'] ); ?>"></label>
                                    <label><span>Disclosure threshold</span><input type="number" value="8" readonly><input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][panel_limit]" value="8"><small>Public Field Spotlights always expose only the first eight panels before expansion.</small></label>
                                    <label class="sc-fs-admin__check"><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][visible]" value="1" <?php checked( ! empty( $field['visible'] ) ); ?>><span>Enable this Field Spotlight</span></label>
                                    <label class="sc-fs-admin__wide"><span>Description</span><textarea rows="3" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][description]"><?php echo esc_textarea( (string) ( $field_settings['description'] ?? $field['description'] ) ); ?></textarea></label>
                                </div>
                            </details>

                            <div class="sc-fs-admin__panel-toolbar">
                                <label><span class="screen-reader-text">Search panels</span><input type="search" id="sc-fs-panel-search" placeholder="Search panels or source groups"></label>
                                <label><span class="screen-reader-text">Filter panels</span><select id="sc-fs-panel-filter"><option value="all">All panels</option><option value="primary">Primary</option><option value="additional">Additional</option><option value="ready">Ready</option><option value="partial">Partial</option><option value="empty">Empty</option><option value="hidden">Hidden</option></select></label>
                                <span id="sc-fs-panel-result-count"><?php echo esc_html( sprintf( '%d panels', count( $field['panels'] ) ) ); ?></span>
                            </div>

                            <div class="sc-fs-admin__panel-list" data-panel-list>
                            <?php foreach ( $field['panels'] as $panel ) :
                                $hero_preview = $this->enrich_hero( $panel['hero'] );
                                $status = $this->panel_status_label( $panel );
                                $configured = absint( $panel['configured_article_count'] ?? 0 );
                                $slot_count = absint( $panel['slot_count'] );
                            ?>
                                <article class="sc-fs-admin__panel" data-panel-row data-title="<?php echo esc_attr( strtolower( $panel['title'] . ' ' . $panel['source_group'] ) ); ?>" data-tier="<?php echo esc_attr( (string) $panel['disclosure'] ); ?>" data-readiness="<?php echo esc_attr( empty( $panel['visible'] ) ? 'hidden' : (string) $panel['readiness'] ); ?>">
                                    <div class="sc-fs-admin__panel-media">
                                        <?php if ( ! empty( $hero_preview['thumbnail']['url'] ) ) : ?><img src="<?php echo esc_url( (string) $hero_preview['thumbnail']['url'] ); ?>" alt=""><?php else : ?><span><strong>KL</strong><small>MAP</small></span><?php endif; ?>
                                    </div>
                                    <div class="sc-fs-admin__panel-copy">
                                        <div class="sc-fs-admin__panel-kicker"><span><?php echo esc_html( ucfirst( (string) $panel['disclosure'] ) ); ?></span><?php if ( $panel['source_group'] ) : ?><span><?php echo esc_html( (string) $panel['source_group'] ); ?></span><?php endif; ?></div>
                                        <input class="sc-fs-admin__panel-title" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][title]" value="<?php echo esc_attr( (string) $panel['title'] ); ?>" aria-label="Panel display title">
                                        <a class="sc-fs-admin__map-link" href="<?php echo esc_url( home_url( $panel['canonical_url'] ) ); ?>" target="_blank" rel="noopener">Article Map ↗ <small><?php echo esc_html( (string) $panel['canonical_url'] ); ?></small></a>
                                    </div>
                                    <div class="sc-fs-admin__panel-readiness">
                                        <span class="sc-fs-admin__status sc-fs-admin__status--<?php echo esc_attr( empty( $panel['visible'] ) ? 'hidden' : (string) $panel['readiness'] ); ?>"><?php echo esc_html( $status ); ?></span>
                                        <strong><?php echo esc_html( sprintf( '%d / %d', $configured, $slot_count ) ); ?></strong><small>articles selected</small>
                                        <span class="sc-fs-admin__mini-progress"><i style="width:<?php echo esc_attr( (string) ( $slot_count ? min( 100, (int) round( $configured / $slot_count * 100 ) ) : 0 ) ); ?>%"></i></span>
                                    </div>
                                    <div class="sc-fs-admin__panel-controls">
                                        <label><span>Order</span><input type="number" min="1" max="999" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][order]" value="<?php echo esc_attr( (string) $panel['order'] ); ?>"></label>
                                        <label><span>Slots</span><input type="number" min="2" max="8" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][slot_count]" value="<?php echo esc_attr( (string) $panel['slot_count'] ); ?>"></label>
                                        <label class="sc-fs-admin__check"><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $panel['key'] ); ?>][visible]" value="1" <?php checked( ! empty( $panel['visible'] ) ); ?>><span>Visible</span></label>
                                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-field-spotlights&field=' . rawurlencode( $selected_field ) . '&panel=' . rawurlencode( $panel['key'] ) . '#sc-fs-panel-editor' ) ); ?>">Edit content</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            </div>
                            <div class="sc-fs-admin__savebar"><?php submit_button( __( 'Save field and panel model', 'sustainable-catalyst-library' ), 'primary', 'submit', false ); ?><span>Article Map destinations remain canonical and cannot be replaced here.</span></div>
                        </form>
                    </section>
                    <?php endif; ?>

                    <?php if ( $selected_panel ) :
                        $panel_saved = is_array( $settings['panels'][ $selected_panel_key ] ?? null ) ? $settings['panels'][ $selected_panel_key ] : array();
                        $hero_preview = $this->enrich_hero( $selected_panel['hero'] );
                    ?>
                    <section class="sc-fs-admin__content-editor" id="sc-fs-panel-editor">
                        <header>
                            <div class="sc-fs-admin__editor-hero-media"><?php if ( ! empty( $hero_preview['thumbnail']['url'] ) ) : ?><img src="<?php echo esc_url( (string) $hero_preview['thumbnail']['url'] ); ?>" alt=""><?php else : ?><span><strong>KL</strong><small>ARTICLE MAP</small></span><?php endif; ?></div>
                            <div><p>ARTICLE MAP HERO · PERMANENT POSITION 0</p><h2><?php echo esc_html( (string) $selected_panel['title'] ); ?></h2><a href="<?php echo esc_url( home_url( $selected_panel['canonical_url'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $selected_panel['canonical_url'] ); ?> ↗</a><small>The canonical hero destination is registry-owned and cannot be replaced.</small></div>
                        </header>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php $this->save_form_fields(); ?>
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="panel">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_panel_key]" value="<?php echo esc_attr( $selected_panel_key ); ?>">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][title]" value="<?php echo esc_attr( (string) $selected_panel['title'] ); ?>">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][order]" value="<?php echo esc_attr( (string) $selected_panel['order'] ); ?>">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][visible]" value="<?php echo ! empty( $selected_panel['visible'] ) ? '1' : '0'; ?>">
                            <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][slot_count]" value="<?php echo esc_attr( (string) $selected_panel['slot_count'] ); ?>">
                            <div class="sc-fs-admin__hero-fields">
                                <label><span>Hero display title</span><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_title]" value="<?php echo esc_attr( (string) ( $panel_saved['hero_title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $selected_panel['canonical_title'] ); ?>"></label>
                                <label><span>Hero CTA</span><input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_cta]" value="<?php echo esc_attr( (string) ( $panel_saved['hero_cta'] ?? '' ) ); ?>" placeholder="Explore Article Map"></label>
                                <label class="sc-fs-admin__wide"><span>Hero description</span><textarea rows="4" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][hero_description]" placeholder="Leave blank to use the published Article Map summary."><?php echo esc_textarea( (string) ( $panel_saved['hero_description'] ?? '' ) ); ?></textarea></label>
                            </div>

                            <div class="sc-fs-admin__slot-head"><div><p>CURATED FROM THIS SERIES</p><h3>Supporting articles</h3></div><span><?php echo esc_html( sprintf( '%d slots', absint( $selected_panel['slot_count'] ) ) ); ?></span></div>
                            <div class="sc-fs-admin__slots">
                            <?php for ( $i = 0; $i < absint( $selected_panel['slot_count'] ); $i++ ) :
                                $article = is_array( $panel_saved['articles'][ $i ] ?? null ) ? $panel_saved['articles'][ $i ] : array();
                                $resolved = ( ! empty( $article['source_id'] ) || ! empty( $article['url'] ) ) ? $this->enrich_article( $article ) : null;
                            ?>
                                <fieldset class="sc-fs-admin__slot" data-source-slot>
                                    <legend>POSITION <?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></legend>
                                    <div class="sc-fs-admin__selected-record" data-selected-record>
                                        <div class="sc-fs-admin__selected-thumb" data-selected-thumb><?php if ( $resolved && ! empty( $resolved['thumbnail']['url'] ) ) : ?><img src="<?php echo esc_url( (string) $resolved['thumbnail']['url'] ); ?>" alt=""><?php else : ?><span>KL</span><?php endif; ?></div>
                                        <div><strong data-selected-title><?php echo esc_html( $resolved ? (string) $resolved['title'] : 'Empty slot' ); ?></strong><small data-selected-meta><?php echo esc_html( $resolved ? (string) $resolved['metadata'] : 'Search the Library to select a published article.' ); ?></small></div>
                                    </div>
                                    <label class="sc-fs-admin__source-search"><span>Search Library</span><input type="search" data-source-search placeholder="Type a title or paste a canonical URL" autocomplete="off"><div class="sc-fs-admin__search-results" data-search-results hidden></div></label>
                                    <input type="hidden" data-source-id name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][source_id]" value="<?php echo esc_attr( (string) absint( $article['source_id'] ?? 0 ) ); ?>">
                                    <label><span>Article URL</span><input data-source-url type="url" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][url]" value="<?php echo esc_attr( (string) ( $article['url'] ?? '' ) ); ?>" placeholder="https://sustainablecatalyst.com/..."></label>
                                    <label><span>Optional display title</span><input data-source-title type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( (string) ( $article['title'] ?? '' ) ); ?>"></label>
                                    <input data-source-enabled type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[panels][<?php echo esc_attr( $selected_panel_key ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][enabled]" value="<?php echo ( ! empty( $article['source_id'] ) || ! empty( $article['url'] ) ) ? '1' : '0'; ?>">
                                    <div class="sc-fs-admin__slot-actions"><span class="sc-fs-admin__slot-publish-state" data-slot-publish-state><?php echo ( ! empty( $article['source_id'] ) || ! empty( $article['url'] ) ) ? 'Publishes on save' : 'Empty slot'; ?></span><button type="button" class="button-link-delete" data-clear-slot>Clear slot</button></div>
                                </fieldset>
                            <?php endfor; ?>
                            </div>
                            <div class="sc-fs-admin__savebar"><?php submit_button( __( 'Save Spotlight content', 'sustainable-catalyst-library' ), 'primary', 'submit', false ); ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sc-library-field-spotlights&field=' . rawurlencode( $selected_field ) ) ); ?>">Close editor</a><span>Selecting an article activates that slot automatically. Empty slots remain empty; no automatic backfill occurs.</span></div>
                        </form>
                    </section>
                    <?php endif; ?>
                </main>
            </div>
        </div>
        <?php
    }

}
