<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Notebook {
    public const SCHEMA = 'sc-library-workspace/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_library_notebook', [$this, 'render_shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_notebook', 1);
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-notebook', SC_LIBRARY_URL . 'assets/css/sc-library-notebook.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-notebook', SC_LIBRARY_URL . 'assets/js/sc-library-notebook.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-notebook', 'SCNotebookShared', [
            'version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'storageKey' => 'scLibraryWorkspaceV120',
            'sourceTypes' => self::source_types(),
            'citationFormats' => self::citation_formats(),
            'strings' => [
                'saved' => __('Saved to the Research Notebook.', 'sustainable-catalyst-library'),
                'alreadySaved' => __('This record is already saved.', 'sustainable-catalyst-library'),
                'storageError' => __('Browser storage is unavailable. Export any visible work before leaving this page.', 'sustainable-catalyst-library'),
                'importError' => __('The selected file is not a valid Sustainable Catalyst Library workspace export.', 'sustainable-catalyst-library'),
                'importSuccess' => __('Workspace imported successfully.', 'sustainable-catalyst-library'),
                'copySuccess' => __('Copied to the clipboard.', 'sustainable-catalyst-library'),
                'copyFailure' => __('Copying was blocked by the browser.', 'sustainable-catalyst-library'),
                'confirmClear' => __('Delete all locally stored Library collections, notes, sources, and saved records on this browser?', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('The Research Notebook is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Research Notebook',
            'intro' => 'Combine Sustainable Catalyst records with personal notes, external links, books, reports, datasets, and media sources.',
            'open' => 'true',
        ], $atts, 'sc_library_notebook');

        self::enqueue_assets();
        $workspace_title = sanitize_text_field($atts['title']);
        $workspace_intro = sanitize_text_field($atts['intro']);
        $workspace_standalone = true;
        $workspace_open = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-workspace.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';

        register_rest_route($namespace, '/library/source-types', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response(['items' => array_values(self::source_types())]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/citation-formats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response(['items' => array_values(self::citation_formats())]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/source-template', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'source_template'],
            'permission_callback' => '__return_true',
            'args' => [
                'type' => ['sanitize_callback' => 'sanitize_key', 'default' => 'website'],
                'url' => ['sanitize_callback' => 'esc_url_raw', 'default' => ''],
            ],
        ]);
    }

    public function source_template(WP_REST_Request $request): WP_REST_Response {
        $types = self::source_types();
        $type = (string) $request['type'];
        if (!isset($types[$type])) {
            $type = 'custom';
        }
        $url = esc_url_raw((string) $request['url']);
        $host = '';
        if ($url !== '') {
            $host = (string) wp_parse_url($url, PHP_URL_HOST);
        }

        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'type' => $types[$type],
            'template' => [
                'id' => '',
                'type' => $type,
                'title' => '',
                'creators' => '',
                'organization' => $host,
                'url' => $url,
                'doi' => '',
                'isbn' => '',
                'publisher' => '',
                'publication_date' => '',
                'access_date' => current_time('Y-m-d'),
                'edition' => '',
                'chapter' => '',
                'pages' => '',
                'physical_location' => '',
                'description' => '',
                'notes' => '',
                'tags' => [],
            ],
        ]);
    }

    public static function source_types(): array {
        return [
            'website' => ['id' => 'website', 'label' => __('Website or web article', 'sustainable-catalyst-library'), 'fields' => ['url', 'access_date']],
            'journal_article' => ['id' => 'journal_article', 'label' => __('Journal article', 'sustainable-catalyst-library'), 'fields' => ['doi', 'publisher', 'publication_date', 'pages']],
            'book' => ['id' => 'book', 'label' => __('Physical or digital book', 'sustainable-catalyst-library'), 'fields' => ['isbn', 'publisher', 'publication_date', 'edition', 'physical_location']],
            'book_chapter' => ['id' => 'book_chapter', 'label' => __('Book chapter', 'sustainable-catalyst-library'), 'fields' => ['isbn', 'publisher', 'chapter', 'pages', 'physical_location']],
            'report' => ['id' => 'report', 'label' => __('Report or policy document', 'sustainable-catalyst-library'), 'fields' => ['organization', 'url', 'publication_date']],
            'dataset' => ['id' => 'dataset', 'label' => __('Dataset', 'sustainable-catalyst-library'), 'fields' => ['organization', 'url', 'doi', 'publication_date']],
            'video' => ['id' => 'video', 'label' => __('Video or video timestamp', 'sustainable-catalyst-library'), 'fields' => ['url', 'publication_date', 'pages']],
            'podcast' => ['id' => 'podcast', 'label' => __('Podcast or audio source', 'sustainable-catalyst-library'), 'fields' => ['url', 'publication_date']],
            'interview' => ['id' => 'interview', 'label' => __('Interview or personal communication', 'sustainable-catalyst-library'), 'fields' => ['publication_date', 'notes']],
            'archive' => ['id' => 'archive', 'label' => __('Archival or unpublished material', 'sustainable-catalyst-library'), 'fields' => ['organization', 'physical_location', 'pages']],
            'custom' => ['id' => 'custom', 'label' => __('Custom source', 'sustainable-catalyst-library'), 'fields' => []],
        ];
    }

    public static function citation_formats(): array {
        return [
            'apa' => ['id' => 'apa', 'label' => 'APA'],
            'mla' => ['id' => 'mla', 'label' => 'MLA'],
            'chicago' => ['id' => 'chicago', 'label' => 'Chicago'],
            'harvard' => ['id' => 'harvard', 'label' => 'Harvard'],
            'plain' => ['id' => 'plain', 'label' => __('Plain text', 'sustainable-catalyst-library')],
            'bibtex' => ['id' => 'bibtex', 'label' => 'BibTeX'],
            'ris' => ['id' => 'ris', 'label' => 'RIS'],
            'csl_json' => ['id' => 'csl_json', 'label' => 'CSL JSON'],
        ];
    }
}
