<?php
/**
 * Dynamic Publications Spotlight for Knowledge Library v4.3.3.
 *
 * Public composition: fourteen major fields control one shared editorial stage.
 * Each field exposes one active Article Map hero followed by up to four curated
 * publication rows. Users can flip or jump between Article Maps without growing
 * the page. There is intentionally no Blog Roll mode and no reading-time metadata.
 *
 * v4.3.3 also adds a WordPress editorial customization surface for global labels,
 * field titles/descriptions/order/visibility/default maps, Article Map hero copy,
 * CTA labels, visibility, and optional four-slot manual publication overrides.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SC_Library_Publications {
    public const VERSION = '4.3.22.4';
    public const SHORTCODE = 'sc_publications';
    public const CACHE_KEY = 'sc_library_publications_topics_v433';
    public const CACHE_TTL = 600;
    public const SETTINGS_OPTION = 'sc_library_publications_settings_v433';
    public const SETTINGS_GROUP = 'sc_library_publications_v433';

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
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'save_post', array( $this, 'invalidate_cache' ), 120, 3 );
        add_action( 'deleted_post', array( $this, 'invalidate_cache_for_deleted_post' ), 120, 1 );
        add_action( 'transition_post_status', array( $this, 'invalidate_cache_for_status' ), 120, 3 );
        add_action( 'update_option_' . self::SETTINGS_OPTION, array( $this, 'invalidate_cache_for_settings' ), 10, 2 );
    }

    /** @return array<string,array<string,mixed>> */
    public static function article_map_registry(): array {
        $file = SC_LIBRARY_DIR . 'includes/data/publications-article-map-registry-v431.php';
        $maps = is_readable( $file ) ? include $file : array();
        if ( ! is_array( $maps ) ) { $maps = array(); }
        $maps = apply_filters( 'sc_library_publications_article_maps', $maps );
        return apply_filters( 'sc_library_publications_registry', $maps );
    }

    /** @return array<string,mixed> */
    private function default_settings(): array {
        return array(
            'general' => array(
                'eyebrow' => 'KL · Knowledge Library',
                'title' => 'Publications',
                'intro' => 'Explore structured research across the Sustainable Catalyst Knowledge Library.',
                'fields_label' => 'Fields',
                'areas_label' => 'Areas',
                'map_label' => 'Article Map',
                'map_cta' => 'Explore Article Map',
                'previous_label' => 'Previous Area',
                'next_label' => 'Next Area',
                'select_label' => 'Jump to area',
                'hero_description' => 'Explore the complete structured pathway for this subject.',
            ),
            'fields' => array(),
            'maps' => array(),
        );
    }

    /** @return array<string,mixed> */
    private function settings(): array {
        $defaults = $this->default_settings();
        $saved = get_option( self::SETTINGS_OPTION, array() );
        if ( ! is_array( $saved ) ) { return $defaults; }
        $defaults['general'] = array_merge( $defaults['general'], is_array( $saved['general'] ?? null ) ? $saved['general'] : array() );
        $defaults['fields'] = is_array( $saved['fields'] ?? null ) ? $saved['fields'] : array();
        $defaults['maps'] = is_array( $saved['maps'] ?? null ) ? $saved['maps'] : array();
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

        if ( 'map' === $context ) {
            $map_key = sanitize_title( (string) ( $incoming['_map_key'] ?? '' ) );
            if ( $map_key && isset( $incoming['maps'][ $map_key ] ) && is_array( $incoming['maps'][ $map_key ] ) ) {
                $raw = $incoming['maps'][ $map_key ];
                $clean = array(
                    'title' => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
                    'description' => sanitize_textarea_field( (string) ( $raw['description'] ?? '' ) ),
                    'cta' => sanitize_text_field( (string) ( $raw['cta'] ?? '' ) ),
                    'visible' => empty( $raw['visible'] ) ? 0 : 1,
                    'articles' => array(),
                );
                for ( $i = 0; $i < 4; $i++ ) {
                    $article = is_array( $raw['articles'][ $i ] ?? null ) ? $raw['articles'][ $i ] : array();
                    $clean['articles'][] = array(
                        'title' => sanitize_text_field( (string) ( $article['title'] ?? '' ) ),
                        'url' => esc_url_raw( (string) ( $article['url'] ?? '' ) ),
                    );
                }
                $existing['maps'][ $map_key ] = $clean;
            }
            return $existing;
        }

        $general_defaults = $this->default_settings()['general'];
        $general_raw = is_array( $incoming['general'] ?? null ) ? $incoming['general'] : array();
        foreach ( $general_defaults as $key => $default ) {
            $raw = (string) ( $general_raw[ $key ] ?? $existing['general'][ $key ] ?? $default );
            $existing['general'][ $key ] = 'intro' === $key || 'hero_description' === $key ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
        }

        if ( is_array( $incoming['fields'] ?? null ) ) {
            foreach ( $incoming['fields'] as $field_key => $raw ) {
                $field_key = sanitize_title( (string) $field_key );
                if ( ! $field_key || ! is_array( $raw ) ) { continue; }
                $existing['fields'][ $field_key ] = array(
                    'title' => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
                    'description' => sanitize_textarea_field( (string) ( $raw['description'] ?? '' ) ),
                    'order' => max( 1, min( 99, absint( $raw['order'] ?? 99 ) ) ),
                    'visible' => empty( $raw['visible'] ) ? 0 : 1,
                    'default_map' => sanitize_title( (string) ( $raw['default_map'] ?? '' ) ),
                );
            }
        }
        return $existing;
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __( 'Publications', 'sustainable-catalyst-library' ),
            __( 'Publications', 'sustainable-catalyst-library' ),
            'manage_options',
            'sc-library-publications',
            array( $this, 'render_admin_page' )
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $settings = $this->settings();
        $registry = self::article_map_registry();
        $fields = $this->registry_fields( $registry );
        $selected_map = sanitize_title( (string) ( $_GET['map'] ?? '' ) );
        if ( ! $selected_map || ! isset( $registry[ $selected_map ] ) ) {
            $selected_map = sanitize_title( (string) array_key_first( $registry ) );
        }
        $map = $registry[ $selected_map ] ?? array();
        $map_settings = is_array( $settings['maps'][ $selected_map ] ?? null ) ? $settings['maps'][ $selected_map ] : array();
        ?>
        <div class="wrap sc-publications-admin">
            <h1><?php esc_html_e( 'Publications', 'sustainable-catalyst-library' ); ?></h1>
            <p><?php esc_html_e( 'Customize the dynamic Publications Spotlight without editing PHP. Canonical Article Map URLs and Knowledge Library structure remain protected.', 'sustainable-catalyst-library' ); ?></p>
            <?php settings_errors(); ?>

            <div style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.85fr);gap:24px;align-items:start;max-width:1400px">
                <form method="post" action="options.php" style="background:#fff;border:1px solid #c3c4c7;padding:22px">
                    <?php settings_fields( self::SETTINGS_GROUP ); ?>
                    <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="general">
                    <h2><?php esc_html_e( 'Public interface copy', 'sustainable-catalyst-library' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php
                        $labels = array(
                            'eyebrow' => 'Eyebrow', 'title' => 'Title', 'intro' => 'Intro', 'fields_label' => 'Fields label',
                            'areas_label' => 'Areas label', 'map_label' => 'Article Map label', 'map_cta' => 'Article Map CTA',
                            'previous_label' => 'Previous control', 'next_label' => 'Next control', 'select_label' => 'Jump control',
                            'hero_description' => 'Default Article Map description',
                        );
                        foreach ( $labels as $key => $label ) : $is_area = in_array( $key, array( 'intro', 'hero_description' ), true ); ?>
                            <tr><th scope="row"><label for="sc-pub-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
                                <?php if ( $is_area ) : ?>
                                    <textarea id="sc-pub-<?php echo esc_attr( $key ); ?>" class="large-text" rows="3" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( (string) $settings['general'][ $key ] ); ?></textarea>
                                <?php else : ?>
                                    <input id="sc-pub-<?php echo esc_attr( $key ); ?>" class="regular-text" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[general][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings['general'][ $key ] ); ?>">
                                <?php endif; ?>
                            </td></tr>
                        <?php endforeach; ?>
                    </table>

                    <h2><?php esc_html_e( 'Field presentation', 'sustainable-catalyst-library' ); ?></h2>
                    <p><?php esc_html_e( 'Rename, describe, reorder, hide, or choose the default Article Map for each of the fourteen major fields.', 'sustainable-catalyst-library' ); ?></p>
                    <?php foreach ( $fields as $field ) :
                        $field_key = sanitize_title( $field['name'] );
                        $cfg = is_array( $settings['fields'][ $field_key ] ?? null ) ? $settings['fields'][ $field_key ] : array();
                        $visible = array_key_exists( 'visible', $cfg ) ? ! empty( $cfg['visible'] ) : true;
                        ?>
                        <details style="border-top:1px solid #dcdcde;padding:12px 0" <?php echo 1 === (int) $field['order'] ? 'open' : ''; ?>>
                            <summary style="cursor:pointer;font-weight:700"><?php echo esc_html( str_pad( (string) $field['order'], 2, '0', STR_PAD_LEFT ) . ' · ' . $field['name'] . ' · ' . count( $field['maps'] ) ); ?></summary>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">
                                <p><label><strong><?php esc_html_e( 'Display title', 'sustainable-catalyst-library' ); ?></strong><br><input class="widefat" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][title]" value="<?php echo esc_attr( (string) ( $cfg['title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $field['name'] ); ?>"></label></p>
                                <p><label><strong><?php esc_html_e( 'Order', 'sustainable-catalyst-library' ); ?></strong><br><input type="number" min="1" max="99" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][order]" value="<?php echo esc_attr( (string) ( $cfg['order'] ?? $field['order'] ) ); ?>"></label></p>
                                <p style="grid-column:1/-1"><label><strong><?php esc_html_e( 'Description', 'sustainable-catalyst-library' ); ?></strong><br><textarea class="widefat" rows="2" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][description]"><?php echo esc_textarea( (string) ( $cfg['description'] ?? '' ) ); ?></textarea></label></p>
                                <p><label><strong><?php esc_html_e( 'Default Article Map', 'sustainable-catalyst-library' ); ?></strong><br><select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][default_map]">
                                    <?php foreach ( $field['maps'] as $map_key => $field_map ) : ?><option value="<?php echo esc_attr( $map_key ); ?>" <?php selected( (string) ( $cfg['default_map'] ?? '' ), $map_key ); ?>><?php echo esc_html( $field_map['title'] ); ?></option><?php endforeach; ?>
                                </select></label></p>
                                <p><input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][visible]" value="0"><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][visible]" value="1" <?php checked( $visible ); ?>> <?php esc_html_e( 'Visible in Publications', 'sustainable-catalyst-library' ); ?></label></p>
                            </div>
                        </details>
                    <?php endforeach; ?>
                    <?php submit_button( __( 'Save Publications settings', 'sustainable-catalyst-library' ) ); ?>
                </form>

                <div style="background:#fff;border:1px solid #c3c4c7;padding:22px;position:sticky;top:48px">
                    <h2><?php esc_html_e( 'Article Map hero editor', 'sustainable-catalyst-library' ); ?></h2>
                    <form method="get" style="margin-bottom:18px">
                        <input type="hidden" name="page" value="sc-library-publications">
                        <label><strong><?php esc_html_e( 'Choose Article Map', 'sustainable-catalyst-library' ); ?></strong><br>
                        <select name="map" onchange="this.form.submit()" style="width:100%;max-width:100%">
                            <?php foreach ( $registry as $map_key => $candidate ) : ?><option value="<?php echo esc_attr( $map_key ); ?>" <?php selected( $selected_map, $map_key ); ?>><?php echo esc_html( $candidate['field'] . ' · ' . $candidate['title'] ); ?></option><?php endforeach; ?>
                        </select></label>
                        <noscript><?php submit_button( __( 'Load map', 'sustainable-catalyst-library' ), 'secondary', '', false ); ?></noscript>
                    </form>

                    <?php if ( $map ) : ?>
                    <form method="post" action="options.php">
                        <?php settings_fields( self::SETTINGS_GROUP ); ?>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_context]" value="map">
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[_map_key]" value="<?php echo esc_attr( $selected_map ); ?>">
                        <p><strong><?php echo esc_html( $map['title'] ); ?></strong><br><code><?php echo esc_html( $map['url'] ); ?></code></p>
                        <p><label><?php esc_html_e( 'Hero display title', 'sustainable-catalyst-library' ); ?><br><input class="widefat" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][title]" value="<?php echo esc_attr( (string) ( $map_settings['title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $map['title'] ); ?>"></label></p>
                        <p><label><?php esc_html_e( 'Hero description', 'sustainable-catalyst-library' ); ?><br><textarea class="widefat" rows="4" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][description]"><?php echo esc_textarea( (string) ( $map_settings['description'] ?? '' ) ); ?></textarea></label></p>
                        <p><label><?php esc_html_e( 'CTA label', 'sustainable-catalyst-library' ); ?><br><input class="widefat" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][cta]" value="<?php echo esc_attr( (string) ( $map_settings['cta'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $settings['general']['map_cta'] ); ?>"></label></p>
                        <input type="hidden" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][visible]" value="0">
                        <p><label><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][visible]" value="1" <?php checked( ! array_key_exists( 'visible', $map_settings ) || ! empty( $map_settings['visible'] ) ); ?>> <?php esc_html_e( 'Visible in Publications', 'sustainable-catalyst-library' ); ?></label></p>
                        <h3><?php esc_html_e( 'Optional manual four', 'sustainable-catalyst-library' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Leave URLs empty to use the automatic resolver. Manual entries are placed first and any remaining positions are filled from the automatic resolver.', 'sustainable-catalyst-library' ); ?></p>
                        <?php for ( $i = 0; $i < 4; $i++ ) : $article = is_array( $map_settings['articles'][ $i ] ?? null ) ? $map_settings['articles'][ $i ] : array(); ?>
                            <fieldset style="border-top:1px solid #dcdcde;padding-top:10px;margin-top:10px">
                                <legend><strong><?php echo esc_html( sprintf( __( 'Article %d', 'sustainable-catalyst-library' ), $i + 1 ) ); ?></strong></legend>
                                <p><input class="widefat" type="url" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][url]" value="<?php echo esc_attr( (string) ( $article['url'] ?? '' ) ); ?>" placeholder="https://sustainablecatalyst.com/..."></p>
                                <p><input class="widefat" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[maps][<?php echo esc_attr( $selected_map ); ?>][articles][<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( (string) ( $article['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Optional display title', 'sustainable-catalyst-library' ); ?>"></p>
                            </fieldset>
                        <?php endfor; ?>
                        <?php submit_button( __( 'Save Article Map presentation', 'sustainable-catalyst-library' ) ); ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /** @param array<string,array<string,mixed>> $registry @return array<int,array<string,mixed>> */
    private function registry_fields( array $registry ): array {
        $fields = array();
        foreach ( $registry as $key => $map ) {
            $name = sanitize_text_field( (string) ( $map['field'] ?? '' ) );
            if ( ! $name ) { continue; }
            if ( ! isset( $fields[ $name ] ) ) {
                $fields[ $name ] = array( 'name' => $name, 'order' => absint( $map['field_order'] ?? 999 ), 'maps' => array() );
            }
            $fields[ $name ]['maps'][ sanitize_title( (string) $key ) ] = $map;
        }
        uasort( $fields, static fn( $a, $b ) => absint( $a['order'] ) <=> absint( $b['order'] ) );
        return array_values( $fields );
    }

    /** @param array<string,mixed>|string $atts */
    public function shortcode( $atts = array() ): string {
        $atts = shortcode_atts( array( 'title' => '', 'intro' => '', 'empty' => 'hide' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE );

        // v4.3.22.4 canonical Publications restoration. The historical [sc_publications]
        // shortcode is a one-stage interface, while the canonical /publications/ page
        // is again defined as the complete 14-field stack. If an older page body still
        // calls [sc_publications], promote it server-side to the restored Field Spotlight
        // stack so page-content drift cannot recreate the Global-Governance-only layout.
        if ( $this->is_canonical_publications_page() && class_exists( 'SC_Library_Field_Spotlights', false ) ) {
            $stack = new SC_Library_Field_Spotlights();
            return $stack->shortcode_stack( array( 'autoplay' => 'true', 'pause_on_hover' => 'true' ) );
        }
        $settings = $this->settings();
        $fields = $this->fields();

        // v4.3.22.4: the original dynamic Publications shortcode is also a
        // JavaScript-enhanced single-stage surface. If persisted visibility or a
        // stale topics transient collapses the canonical 14-field / 170-map model
        // to one field or one map, run the bounded integrity repair and rebuild
        // before rendering. This mirrors the newer Field Spotlight runtime guard.
        if ( $this->public_surface_appears_collapsed( $fields ) && class_exists( 'SC_Library_Activator', false ) ) {
            SC_Library_Activator::repair_publication_surface_integrity_runtime();
            $this->invalidate_cache();
            $fields = $this->fields();
        }

        if ( empty( $fields ) ) {
            return 'hide' === sanitize_key( (string) $atts['empty'] ) ? '' : '<div class="sc-publications-empty"></div>';
        }

        wp_enqueue_style( 'sc-library-publications', SC_LIBRARY_URL . 'assets/css/sc-library-publications.css', array(), self::VERSION );
        wp_enqueue_script( 'sc-library-publications', SC_LIBRARY_URL . 'assets/js/sc-library-publications.js', array(), self::VERSION, true );
        $heading = sanitize_text_field( (string) ( $atts['title'] ?: $settings['general']['title'] ) );
        $intro = sanitize_textarea_field( (string) ( $atts['intro'] ?: $settings['general']['intro'] ) );
        $labels = $settings['general'];
        $instance_id = wp_unique_id( 'sc-publications-' );
        $template = SC_LIBRARY_DIR . 'templates/publications.php';
        if ( ! is_readable( $template ) ) { return ''; }
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    private function is_canonical_publications_page(): bool {
        if ( function_exists( 'is_page' ) && is_page( 'publications' ) ) { return true; }
        if ( function_exists( 'get_queried_object_id' ) && function_exists( 'get_post_field' ) ) {
            $post_id = absint( get_queried_object_id() );
            if ( $post_id > 0 ) {
                return 'publications' === sanitize_title( (string) get_post_field( 'post_name', $post_id ) );
            }
        }
        return false;
    }

    /** @return array<int,array<string,mixed>> */
    private function fields(): array {
        $topics = $this->topics();
        $settings = $this->settings();
        $fields = array();
        foreach ( $topics as $topic ) {
            $canonical = (string) $topic['field'];
            $field_key = sanitize_title( $canonical );
            $field_cfg = is_array( $settings['fields'][ $field_key ] ?? null ) ? $settings['fields'][ $field_key ] : array();
            if ( array_key_exists( 'visible', $field_cfg ) && empty( $field_cfg['visible'] ) ) { continue; }
            if ( ! isset( $fields[ $field_key ] ) ) {
                $fields[ $field_key ] = array(
                    'key' => $field_key,
                    'canonical_title' => $canonical,
                    'title' => sanitize_text_field( (string) ( $field_cfg['title'] ?? '' ) ) ?: $canonical,
                    'description' => sanitize_textarea_field( (string) ( $field_cfg['description'] ?? '' ) ),
                    'order' => absint( $field_cfg['order'] ?? $topic['field_order'] ),
                    'default_map' => sanitize_title( (string) ( $field_cfg['default_map'] ?? '' ) ),
                    'topics' => array(),
                );
            }
            $fields[ $field_key ]['topics'][] = $topic;
        }
        uasort( $fields, static fn( $a, $b ) => absint( $a['order'] ) <=> absint( $b['order'] ) );
        foreach ( $fields as &$field ) {
            $default_index = 0;
            foreach ( $field['topics'] as $index => $topic ) {
                if ( $field['default_map'] && $field['default_map'] === $topic['key'] ) { $default_index = $index; break; }
            }
            $field['default_index'] = $default_index;
            $field['count'] = count( $field['topics'] );
        }
        unset( $field );
        return array_values( $fields );
    }

    /**
     * v4.3.22.4 structural guard for the original [sc_publications] runtime.
     * The canonical registry defines the expected field/map cardinality. A
     * public model with only one field, or a multi-map canonical field reduced
     * to one map, is treated as corruption/stale state rather than intentional
     * editorial presentation.
     *
     * @param array<int,array<string,mixed>> $fields
     */
    private function public_surface_appears_collapsed( array $fields ): bool {
        $registry = self::article_map_registry();
        $canonical_fields = array();
        foreach ( $registry as $map ) {
            if ( ! is_array( $map ) ) { continue; }
            $field_key = sanitize_title( (string) ( $map['field'] ?? '' ) );
            if ( ! $field_key ) { continue; }
            $canonical_fields[ $field_key ] = absint( $canonical_fields[ $field_key ] ?? 0 ) + 1;
        }
        if ( count( $canonical_fields ) > 1 && count( $fields ) <= 1 ) { return true; }

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) { continue; }
            $field_key = sanitize_title( (string) ( $field['key'] ?? $field['canonical_title'] ?? '' ) );
            $canonical_count = absint( $canonical_fields[ $field_key ] ?? 0 );
            $public_count = is_array( $field['topics'] ?? null ) ? count( $field['topics'] ) : 0;
            if ( $canonical_count > 1 && $public_count <= 1 ) { return true; }
        }
        return false;
    }

    /** @param mixed $articles @return array<int,array{title:string,url:string}> */
    private function manual_articles( $articles, int $limit ): array {
        if ( ! is_array( $articles ) ) { return array(); }
        $normalized = array();
        $seen = array();
        foreach ( $articles as $article ) {
            if ( ! is_array( $article ) ) { continue; }
            $url = esc_url_raw( (string) ( $article['url'] ?? '' ) );
            if ( ! $url || isset( $seen[ $url ] ) ) { continue; }
            $title = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
            if ( ! $title ) {
                $post_id = url_to_postid( $url );
                if ( $post_id ) { $title = sanitize_text_field( (string) get_the_title( $post_id ) ); }
            }
            if ( ! $title ) { continue; }
            $seen[ $url ] = true;
            $normalized[] = array( 'title' => $title, 'url' => $url );
            if ( count( $normalized ) >= $limit ) { break; }
        }
        return $normalized;
    }

    /**
     * Build all registered publication topics in canonical field/topic order,
     * then apply map-level editorial presentation overrides.
     * @return array<int,array<string,mixed>>
     */
    private function topics(): array {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) { return $cached; }
        $settings = $this->settings();
        $registry = self::article_map_registry();
        uasort( $registry, static function ( $left, $right ) {
            $field_cmp = absint( $left['field_order'] ?? 999 ) <=> absint( $right['field_order'] ?? 999 );
            return 0 !== $field_cmp ? $field_cmp : absint( $left['order'] ?? 9999 ) <=> absint( $right['order'] ?? 9999 );
        } );

        $topics = array();
        foreach ( $registry as $key => $map ) {
            if ( empty( $map['title'] ) || empty( $map['url'] ) || empty( $map['field'] ) ) { continue; }
            $topic_key = sanitize_title( (string) $key );
            $map_cfg = is_array( $settings['maps'][ $topic_key ] ?? null ) ? $settings['maps'][ $topic_key ] : array();
            if ( array_key_exists( 'visible', $map_cfg ) && empty( $map_cfg['visible'] ) ) { continue; }
            $topic = array(
                'key' => $topic_key,
                'title' => sanitize_text_field( (string) ( $map_cfg['title'] ?? '' ) ) ?: sanitize_text_field( (string) $map['title'] ),
                'canonical_title' => sanitize_text_field( (string) $map['title'] ),
                'description' => '',
                'field' => sanitize_text_field( (string) $map['field'] ),
                'field_order' => absint( $map['field_order'] ?? 999 ),
                'group' => sanitize_text_field( (string) ( $map['group'] ?? '' ) ),
                'order' => absint( $map['order'] ?? 9999 ),
                'map_title' => sanitize_text_field( (string) ( $map_cfg['title'] ?? '' ) ) ?: sanitize_text_field( (string) $map['title'] ),
                'map_url' => esc_url_raw( (string) $map['url'] ),
                'map_cta' => sanitize_text_field( (string) ( $map_cfg['cta'] ?? '' ) ),
                'aliases' => array_values( array_filter( array_map( 'sanitize_title', (array) ( $map['aliases'] ?? array() ) ) ) ),
                'articles' => array(),
                'article_source' => '',
            );
            $resolved = $this->articles_for_topic( $topic, 4 );
            $manual = $this->manual_articles( $map_cfg['articles'] ?? array(), 4 );
            if ( $manual ) {
                $combined = $manual;
                $seen_urls = array_fill_keys( array_column( $combined, 'url' ), true );
                foreach ( $resolved['articles'] as $article ) {
                    if ( isset( $seen_urls[ $article['url'] ] ) ) { continue; }
                    $seen_urls[ $article['url'] ] = true;
                    $combined[] = $article;
                    if ( count( $combined ) >= 4 ) { break; }
                }
                $topic['articles'] = array_slice( $combined, 0, 4 );
                $topic['article_source'] = $resolved['source'] ? 'manual+' . $resolved['source'] : 'manual';
            } else {
                $topic['articles'] = $resolved['articles'];
                $topic['article_source'] = $resolved['source'];
            }
            $topic['description'] = sanitize_textarea_field( (string) ( $map_cfg['description'] ?? '' ) ) ?: $resolved['description'];
            $topics[] = $topic;
        }
        $topics = apply_filters( 'sc_library_publications_topics', $topics );
        set_transient( self::CACHE_KEY, $topics, self::CACHE_TTL );
        return $topics;
    }

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


    public function invalidate_cache( int $post_id = 0, $post = null, bool $update = false ): void { delete_transient( self::CACHE_KEY ); }
    public function invalidate_cache_for_deleted_post( int $post_id ): void { delete_transient( self::CACHE_KEY ); }
    public function invalidate_cache_for_status( string $new_status, string $old_status, $post ): void { if ( $new_status !== $old_status ) { delete_transient( self::CACHE_KEY ); } }
    public function invalidate_cache_for_settings( $old_value = null, $value = null ): void { delete_transient( self::CACHE_KEY ); }
}
