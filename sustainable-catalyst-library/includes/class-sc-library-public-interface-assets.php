<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Public_Interface_Assets {
    public const VERSION = '5.6.0.32';
    public function register_hooks(): void { add_action('wp_enqueue_scripts', [$this,'enqueue'], 40); }
    public function enqueue(): void {
        if (!is_page('knowledge-libraries')) { return; }
        wp_enqueue_style('sc-library', SC_LIBRARY_URL.'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-public-interface-v560r3', SC_LIBRARY_URL.'assets/css/sc-library-public-interface-v560r3.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-dynamic-explorer-v560', SC_LIBRARY_URL.'assets/css/sc-library-dynamic-explorer-v560.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-connectors', SC_LIBRARY_URL.'assets/css/sc-library-connectors.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-research-network-console-v560r3', SC_LIBRARY_URL.'assets/css/sc-library-research-network-console-v560r3.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-account-continuity-v4327', SC_LIBRARY_URL.'assets/css/sc-library-account-continuity-v4327.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-capability-hub-v560r3', SC_LIBRARY_URL.'assets/css/sc-library-capability-hub-v560r3.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-open-course-finder', SC_LIBRARY_URL.'assets/css/sc-library-open-course-finder.css', ['sc-library-public-interface-v560r3'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-research-network-console-v560r3', SC_LIBRARY_URL.'assets/js/sc-library-research-network-console-v560r3.js', [], SC_LIBRARY_VERSION, true);
        wp_enqueue_script('sc-library-capability-hub-v560r3', SC_LIBRARY_URL.'assets/js/sc-library-capability-hub-v560r3.js', [], SC_LIBRARY_VERSION, true);
        wp_enqueue_script('sc-library-open-course-finder', SC_LIBRARY_URL.'assets/js/sc-library-open-course-finder.js', [], SC_LIBRARY_VERSION, true);
        wp_enqueue_script('sc-library-course-plan', SC_LIBRARY_URL.'assets/js/sc-library-course-plan.js', [], SC_LIBRARY_VERSION, true);
    }
}
