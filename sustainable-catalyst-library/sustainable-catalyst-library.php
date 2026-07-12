<?php
/**
 * Plugin Name: Sustainable Catalyst Library
 * Plugin URI: https://sustainablecatalyst.com/library/
 * Description: A native WordPress knowledge base with structured indexing, relationships, research notebooks, sources, Technical Translation Matrices, Whiteboards, Chalkboards, Annotation Studio handwriting, connected-tool handoffs, portable exports, search, filters, and public REST endpoints.
 * Version: 1.6.0
 * Author: Content Catalyst LLC
 * Author URI: https://sustainablecatalyst.com/
 * Text Domain: sustainable-catalyst-library
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_LIBRARY_VERSION', '1.6.0');
define('SC_LIBRARY_FILE', __FILE__);
define('SC_LIBRARY_DIR', plugin_dir_path(__FILE__));
define('SC_LIBRARY_URL', plugin_dir_url(__FILE__));

require_once SC_LIBRARY_DIR . 'includes/class-sc-library-activator.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-taxonomies.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-relationships.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-indexer.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-editor.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-rest.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-admin.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-notebook.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-boards.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-integrations.php';
require_once SC_LIBRARY_DIR . 'includes/class-sc-library-annotations.php';
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
        SC_Library_Activator::maybe_upgrade();
        load_plugin_textdomain('sustainable-catalyst-library', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $taxonomies = new SC_Library_Taxonomies();
        $relationships = new SC_Library_Relationships();
        $indexer = new SC_Library_Indexer($relationships);
        $editor = new SC_Library_Editor($indexer, $relationships);
        $rest = new SC_Library_REST($indexer, $relationships);
        $admin = new SC_Library_Admin($indexer, $relationships);
        $notebook = new SC_Library_Notebook();
        $boards = new SC_Library_Boards();
        $integrations = new SC_Library_Integrations($indexer, $relationships);
        $annotations = new SC_Library_Annotations();
        $shortcodes = new SC_Library_Shortcodes();

        $taxonomies->register_hooks();
        $relationships->register_hooks();
        $indexer->register_hooks();
        $editor->register_hooks();
        $rest->register_hooks();
        $admin->register_hooks();
        $notebook->register_hooks();
        $boards->register_hooks();
        $integrations->register_hooks();
        $annotations->register_hooks();
        $shortcodes->register_hooks();
    }
}

SC_Library_Plugin::instance();
