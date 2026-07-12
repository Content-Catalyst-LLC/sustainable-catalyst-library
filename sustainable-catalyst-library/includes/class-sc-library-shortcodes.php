<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Shortcodes {
    public function register_hooks(): void {
        add_shortcode('sc_library', [$this, 'render']);
    }

    public function render(array $atts = []): string {
        $atts = shortcode_atts([
            'category' => '',
            'per_page' => (string) get_option('sc_library_items_per_page', 12),
            'title' => 'Sustainable Catalyst Library',
            'show_intro' => 'true',
        ], $atts, 'sc_library');

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library', SC_LIBRARY_URL . 'assets/js/sc-library.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library', 'SCLibraryConfig', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'nonce' => wp_create_nonce('wp_rest'),
            'initialCategory' => sanitize_title($atts['category']),
            'perPage' => min(50, max(1, absint($atts['per_page']))),
            'enableTags' => (bool) get_option('sc_library_enable_tags', 1),
            'strings' => [
                'loading' => __('Loading Library records…', 'sustainable-catalyst-library'),
                'empty' => __('No Library records match these filters.', 'sustainable-catalyst-library'),
                'error' => __('The Library could not be loaded. Please try again.', 'sustainable-catalyst-library'),
            ],
        ]);

        $title = sanitize_text_field($atts['title']);
        $show_intro = filter_var($atts['show_intro'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-app.php';
        return (string) ob_get_clean();
    }
}
