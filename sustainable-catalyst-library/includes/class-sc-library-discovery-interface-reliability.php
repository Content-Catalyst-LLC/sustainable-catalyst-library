<?php
/**
 * Discovery Interface Reliability Patch.
 *
 * Folds the proven standalone interface repair into the main Knowledge Library
 * plugin while remaining safe when that temporary repair plugin is still active.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SC_Library_Discovery_Interface_Reliability {
    public const VERSION = '4.0.1';
    public const SCHEMA = 'sc-library-discovery-interface-reliability/1.0';

    /**
     * Display-only response keys whose encoded ampersands may be normalized.
     * URLs, identifiers, hashes, and raw content are deliberately excluded.
     */
    private const DISPLAY_KEYS = array(
        'name',
        'title',
        'label',
        'description',
        'excerpt',
        'summary',
        'text',
        'message',
        'notice',
        'area',
        'product',
        'type_label',
        'content_type_label',
        'record_state_label',
        'rendered',
    );

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
        add_filter( 'rest_post_dispatch', array( $this, 'normalize_library_rest_response' ), 20, 3 );
        add_action( 'admin_notices', array( $this, 'render_standalone_repair_notice' ) );
    }

    /**
     * Load the repair from the main plugin unless the temporary standalone
     * repair is still active. Loading both would register two capture handlers
     * and could toggle a disclosure twice.
     */
    public function enqueue_assets() {
        if ( is_admin() || class_exists( 'SC_Library_Interface_Repair', false ) ) {
            return;
        }

        wp_enqueue_style(
            'sc-library-discovery-interface-reliability',
            SC_LIBRARY_URL . 'assets/css/sc-library-discovery-interface-reliability.css',
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'sc-library-discovery-interface-reliability',
            SC_LIBRARY_URL . 'assets/js/sc-library-discovery-interface-reliability.js',
            array(),
            self::VERSION,
            true
        );
    }

    /**
     * Normalize display strings before the Library renderer escapes them.
     * Repeated decoding is safe and leaves unrelated entities untouched.
     */
    public function normalize_library_rest_response( $result, $server, $request ) {
        unset( $server );

        if ( ! $request instanceof WP_REST_Request ) {
            return $result;
        }

        $route = (string) $request->get_route();
        if ( 0 !== strpos( $route, '/sustainable-catalyst/v1/library/' ) ) {
            return $result;
        }

        if (
            is_wp_error( $result )
            || ! is_object( $result )
            || ! method_exists( $result, 'get_data' )
            || ! method_exists( $result, 'set_data' )
        ) {
            return $result;
        }

        $result->set_data( self::normalize_value( $result->get_data() ) );
        return $result;
    }

    public function render_standalone_repair_notice() {
        if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'SC_Library_Interface_Repair', false ) ) {
            return;
        }
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong><?php esc_html_e( 'Knowledge Library v4.0.1 includes the Library Interface Repair.', 'sustainable-catalyst-library' ); ?></strong>
            <?php esc_html_e( 'After confirming the Browse Library cards and ampersands work correctly, deactivate and delete the standalone Sustainable Catalyst Library Interface Repair plugin.', 'sustainable-catalyst-library' ); ?></p>
        </div>
        <?php
    }

    private static function normalize_value( $value, $key = '' ) {
        if ( is_array( $value ) ) {
            $normalized = array();
            foreach ( $value as $child_key => $child_value ) {
                $normalized[ $child_key ] = self::normalize_value(
                    $child_value,
                    is_string( $child_key ) ? $child_key : $key
                );
            }
            return $normalized;
        }

        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $child_key => $child_value ) {
                $value->{$child_key} = self::normalize_value( $child_value, (string) $child_key );
            }
            return $value;
        }

        if ( is_string( $value ) && in_array( $key, self::DISPLAY_KEYS, true ) ) {
            return self::decode_ampersands( $value );
        }

        return $value;
    }

    private static function decode_ampersands( $value ) {
        $current = (string) $value;
        for ( $pass = 0; $pass < 5; $pass++ ) {
            $decoded = preg_replace( '/&(?:amp|#0*38|#x0*26);/i', '&', $current );
            if ( null === $decoded || $decoded === $current ) {
                break;
            }
            $current = $decoded;
        }
        return $current;
    }
}
