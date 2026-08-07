<?php
/**
 * Field Spotlight data architecture for Sustainable Catalyst Library v4.3.4.
 *
 * Administration: SC Library -> Field Spotlights.
 * This release establishes the durable editorial model used by later public
 * Field Spotlight presentation releases. It intentionally does not replace the
 * v4.3.3 Publications shortcode or the v4.2.0 Homepage Spotlight.
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
    public const VERSION = '4.3.4';
    public const SETTINGS_OPTION = 'sc_library_field_spotlights_settings_v434';
    public const SETTINGS_GROUP = 'sc_library_field_spotlights_v434';
    public const MODEL_CACHE_KEY = 'sc_library_field_spotlights_model_v434';
    public const MODEL_CACHE_TTL = 600;
    public const DEFAULT_PANEL_LIMIT = 8;
    public const DEFAULT_SLOT_COUNT = 4;
    public const MIN_SLOT_COUNT = 2;
    public const MAX_SLOT_COUNT = 8;

    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 41 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'update_option_' . self::SETTINGS_OPTION, array( $this, 'invalidate_model' ), 10, 2 );
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
            $clean[] = array(
                'source_id' => absint( $article['source_id'] ?? 0 ),
                'title' => sanitize_text_field( (string) ( $article['title'] ?? '' ) ),
                'url' => esc_url_raw( (string) ( $article['url'] ?? '' ) ),
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
            <p><?php esc_html_e( 'Configure the Field Spotlight data model. This release does not replace the public Publications or Homepage Spotlight interfaces.', 'sustainable-catalyst-library' ); ?></p>
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
                            <tr><th>Panel disclosure threshold</th><td><input type="number" min="1" max="24" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $selected_field ); ?>][panel_limit]" value="<?php echo esc_attr( (string) $field['panel_limit'] ); ?>"><p class="description">Panels after this position are marked Additional and will use the + disclosure control in the public presentation release.</p></td></tr>
                        </table>

                        <h3><?php esc_html_e( 'Flattened series panels', 'sustainable-catalyst-library' ); ?></h3>
                        <p><?php esc_html_e( 'Source groups are retained for knowledge architecture, but every Article Map below is a peer panel in this Field Spotlight.', 'sustainable-catalyst-library' ); ?></p>
                        <table class="widefat striped">
                            <thead><tr><th style="width:80px">Order</th><th>Panel</th><th>Source group</th><th>Canonical Article Map</th><th style="width:90px">Visible</th><th style="width:110px">Slots</th><th style="width:90px">Tier</th></tr></thead>
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
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php submit_button( __( 'Save field and panel model', 'sustainable-catalyst-library' ) ); ?>
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
                    <p><strong>v4.3.4 boundary</strong></p>
                    <p class="description">Data architecture and administration only. Public Field Spotlight rendering, panel hero presentation, + disclosure interaction, and visual article curation are scheduled for subsequent builds.</p>
                    <p class="description">No automatic article backfill is defined for Field Spotlight supporting slots. Selection mode is manual only.</p>
                </aside>
            </div>
        </div>
        <?php
    }
}
