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
        $requested_mode = sanitize_key((string) ($atts['mode'] ?? ''));
        $requested_collection = sanitize_title((string) ($atts['collection'] ?? ''));
        if (class_exists('SC_Library_Documentation') && ($requested_mode === 'documentation' || ($requested_collection === SC_Library_Documentation::COLLECTION_SLUG && $requested_mode !== 'registry' && $requested_mode !== 'planner'))) {
            return SC_Library_Documentation::render_shortcode($atts);
        }
        if (class_exists('SC_Library_Planner') && in_array($requested_mode, ['registry', 'planner', 'roadmap'], true)) {
            $planner = new SC_Library_Planner(new SC_Library_Indexer(new SC_Library_Relationships()), new SC_Library_Relationships());
            if ($requested_mode === 'registry') {
                return $planner->render_registry_shortcode($atts);
            }
            return $planner->render_tracker_shortcode($atts);
        }

        self::$instance_count++;

        $default_mode = (string) get_option('sc_library_default_mode', 'compact');
        $atts = shortcode_atts([
            'category' => '',
            'collection' => '',
            'series' => '',
            'concept' => '',
            'record' => '',
            'per_page' => (string) get_option('sc_library_items_per_page', 10),
            'title' => 'Explore the knowledge base',
            'intro' => 'Search the Sustainable Catalyst knowledge architecture or open a domain, series, or concept to reveal connected publications.',
            'show_header' => 'true',
            'show_pathways' => (string) (int) get_option('sc_library_show_pathways', 1),
            'initial_results' => (string) (int) get_option('sc_library_initial_results', 0),
            'mode' => $default_mode,
            'density' => (string) get_option('sc_library_result_density', 'compact'),
            'show_workspace' => (string) (int) get_option('sc_library_enable_notebook', 1),
        ], $atts, 'sc_library');

        $allowed_modes = ['compact', 'full', 'search', 'domains', 'pathways'];
        $mode = in_array($atts['mode'], $allowed_modes, true) ? $atts['mode'] : 'compact';
        $density = in_array($atts['density'], ['compact', 'comfortable'], true) ? $atts['density'] : 'compact';
        $per_page = min(30, max(1, absint($atts['per_page'])));
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $show_pathways = filter_var($atts['show_pathways'], FILTER_VALIDATE_BOOLEAN);
        $initial_results = filter_var($atts['initial_results'], FILTER_VALIDATE_BOOLEAN);
        $show_workspace = filter_var($atts['show_workspace'], FILTER_VALIDATE_BOOLEAN) && SC_Library_Notebook::enabled();
        $title = sanitize_text_field($atts['title']);
        $intro = sanitize_text_field($atts['intro']);
        $initial_category = sanitize_title($atts['category']);
        $initial_series = sanitize_title($atts['series']);
        $initial_concept = sanitize_title($atts['concept']);
        $initial_record = absint($atts['record']);
        $instance_id = 'sc-library-' . self::$instance_count . '-' . wp_rand(1000, 9999);
        $pathways = $this->featured_pathways();

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library', SC_LIBRARY_URL . 'assets/js/sc-library.js', [], SC_LIBRARY_VERSION, true);
        if ($show_workspace) {
            SC_Library_Notebook::enqueue_assets();
        }
        if (class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled()) {
            SC_Library_Integrations::enqueue_assets();
        }
        if (class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled()) {
            SC_Library_Annotations::enqueue_assets();
        }
        if (class_exists('SC_Library_Books') && SC_Library_Books::enabled()) {
            SC_Library_Books::enqueue_assets();
        }
        wp_localize_script('sc-library', 'SCLibraryShared', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'matrixEnabled' => SC_Library_Notebook::matrix_enabled(),
            'boardsEnabled' => class_exists('SC_Library_Boards') && SC_Library_Boards::enabled(),
            'integrationsEnabled' => class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled(),
            'annotationsEnabled' => class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled(),
            'booksEnabled' => class_exists('SC_Library_Books') && SC_Library_Books::enabled(),
            'graphEnabled' => class_exists('SC_Library_Knowledge_Graph') && SC_Library_Knowledge_Graph::enabled(),
            'graphPageUrl' => esc_url_raw((string) get_option('sc_library_graph_page_url', home_url('/knowledge-graph/'))),
            'orchestratorEnabled' => class_exists('SC_Library_Orchestrator') && SC_Library_Orchestrator::enabled(),
            'orchestratorPageUrl' => esc_url_raw((string) get_option('sc_library_orchestrator_page_url', home_url('/research-librarian/'))),
            'strings' => [
                'loading' => __('Searching the knowledge base…', 'sustainable-catalyst-library'),
                'empty' => __('No knowledge records match this request.', 'sustainable-catalyst-library'),
                'error' => __('The knowledge base could not be loaded. Please try again.', 'sustainable-catalyst-library'),
                'categoriesError' => __('Topic navigation is temporarily unavailable.', 'sustainable-catalyst-library'),
                'facetsError' => __('Series and concept navigation is temporarily unavailable.', 'sustainable-catalyst-library'),
                'recordLoading' => __('Loading the knowledge record…', 'sustainable-catalyst-library'),
                'copySuccess' => __('Record link copied.', 'sustainable-catalyst-library'),
                'copyFailure' => __('Copy the address from your browser.', 'sustainable-catalyst-library'),
                'saveRecord' => __('Save to Notebook', 'sustainable-catalyst-library'),
                'writeNote' => __('Write note', 'sustainable-catalyst-library'),
                'translateRecord' => __('Open Translation Matrix', 'sustainable-catalyst-library'),
                'whiteboardRecord' => __('Open Whiteboard', 'sustainable-catalyst-library'),
                'chalkboardRecord' => __('Open Chalkboard', 'sustainable-catalyst-library'),
                'workbenchRecord' => __('Analyze in Workbench', 'sustainable-catalyst-library'),
                'decisionRecord' => __('Build Decision Canvas', 'sustainable-catalyst-library'),
                'siteRecord' => __('Investigate Geographic Context', 'sustainable-catalyst-library'),
                'annotateRecord' => __('Annotate and Handwrite', 'sustainable-catalyst-library'),
                'bookRecord' => __('Add to Custom Book', 'sustainable-catalyst-library'),
                'graphRecord' => __('View Relationship Graph', 'sustainable-catalyst-library'),
                'orchestrateRecord' => __('Ask Research Librarian', 'sustainable-catalyst-library'),
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
