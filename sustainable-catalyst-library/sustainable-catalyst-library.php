<?php
/**
 * Plugin Name: Sustainable Catalyst Library
 * Plugin URI: https://sustainablecatalyst.com/library/
 * Description: Structured WordPress indexing, REST API, category navigation, search, and filters for the Sustainable Catalyst Library.
 * Version: 1.0.0
 * Author: Content Catalyst LLC
 * Author URI: https://sustainablecatalyst.com/
 * Text Domain: sustainable-catalyst-library
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_LIBRARY_VERSION', '1.0.0');
define('SC_LIBRARY_FILE', __FILE__);
define('SC_LIBRARY_DIR', plugin_dir_path(__FILE__));
define('SC_LIBRARY_URL', plugin_dir_url(__FILE__));

require_once SC_LIBRARY_DIR . 'includes/class-sc-library-activator.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-indexer.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-rest.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-admin.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-shortcodes.php';

register_activation_hook(__FILE__, ['SC_Library_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SC_Library_Activator', 'deactivate']);

final class SC_Library_Plugin {
    private static ?SC_Library_Plugin $instance = null;

    public static function instance(): SC_Library_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        load_plugin_textdomain('sustainable-catalyst-library', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $indexer = new SC_Library_Indexer();
        $rest = new SC_Library_REST($indexer);
        $admin = new SC_Library_Admin($indexer);
        $shortcodes = new SC_Library_Shortcodes();

        $indexer->register_hooks();
        $rest->register_hooks();
        $admin->register_hooks();
        $shortcodes->register_hooks();
    }
}

SC_Library_Plugin::instance();
