<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Shortcodes {
    private static int $instance_count = 0;

    public function register_hooks(): void {
        add_shortcode('sc_library', [$this, 'render']);
    }

    public function render(array $atts = []): string {
        self::$instance_count++;

        $default_mode = (string) get_option('sc_library_default_mode', 'compact');
        $atts = shortcode_atts([
            'category' => '',
            'per_page' => (string) get_option('sc_library_items_per_page', 10),
            'title' => 'Explore the knowledge base',
            'intro' => 'Search the Sustainable Catalyst knowledge architecture or open a domain to reveal its pathways and publications.',
            'show_header' => 'true',
            'show_pathways' => (string) (int) get_option('sc_library_show_pathways', 1),
            'initial_results' => (string) (int) get_option('sc_library_initial_results', 0),
            'mode' => $default_mode,
            'density' => (string) get_option('sc_library_result_density', 'compact'),
        ], $atts, 'sc_library');

        $allowed_modes = ['compact', 'full', 'search', 'domains', 'pathways'];
        $mode = in_array($atts['mode'], $allowed_modes, true) ? $atts['mode'] : 'compact';
        $density = in_array($atts['density'], ['compact', 'comfortable'], true) ? $atts['density'] : 'compact';
        $per_page = min(30, max(1, absint($atts['per_page'])));
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $show_pathways = filter_var($atts['show_pathways'], FILTER_VALIDATE_BOOLEAN);
        $initial_results = filter_var($atts['initial_results'], FILTER_VALIDATE_BOOLEAN);
        $title = sanitize_text_field($atts['title']);
        $intro = sanitize_text_field($atts['intro']);
        $initial_category = sanitize_title($atts['category']);
        $instance_id = 'sc-library-' . self::$instance_count . '-' . wp_rand(1000, 9999);
        $pathways = $this->featured_pathways();

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library', SC_LIBRARY_URL . 'assets/js/sc-library.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library', 'SCLibraryShared', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'strings' => [
                'loading' => __('Searching the knowledge base…', 'sustainable-catalyst-library'),
                'empty' => __('No knowledge records match this request.', 'sustainable-catalyst-library'),
                'error' => __('The knowledge base could not be loaded. Please try again.', 'sustainable-catalyst-library'),
                'categoriesError' => __('Topic navigation is temporarily unavailable.', 'sustainable-catalyst-library'),
                'results' => __('results', 'sustainable-catalyst-library'),
                'result' => __('result', 'sustainable-catalyst-library'),
            ],
        ]);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-app.php';
        return (string) ob_get_clean();
    }

    private function featured_pathways(): array {
        $raw = (string) get_option('sc_library_featured_pathways', '');
        $pathways = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $pathways[] = [
                'title' => sanitize_text_field($parts[0]),
                'url' => esc_url($parts[1]),
                'description' => isset($parts[2]) ? sanitize_text_field($parts[2]) : '',
            ];
        }

        return array_slice($pathways, 0, 8);
    }
}
