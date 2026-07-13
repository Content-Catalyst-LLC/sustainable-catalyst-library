<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Notebook {
    public const SCHEMA = 'sc-library-workspace/1.7';
    public const LEGACY_SCHEMA = 'sc-library-workspace/1.6';
    public const EARLIER_SCHEMA = 'sc-library-workspace/1.5';
    public const ORIGINAL_SCHEMA = 'sc-library-workspace/1.4';
    public const FIRST_SCHEMA = 'sc-library-workspace/1.3';
    public const INITIAL_SCHEMA = 'sc-library-workspace/1.2';
    public const FIRST_RELEASE_SCHEMA = 'sc-library-workspace/1.1';
    public const ORIGINAL_RELEASE_SCHEMA = 'sc-library-workspace/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_library_notebook', [$this, 'render_shortcode']);
        add_shortcode('sc_library_translation_matrix', [$this, 'render_matrix_shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_notebook', 1);
    }

    public static function matrix_enabled(): bool {
        return self::enabled() && (bool) get_option('sc_library_enable_translation_matrix', 1);
    }

    public static function legacy_schemas(): array {
        return [self::LEGACY_SCHEMA, self::EARLIER_SCHEMA, self::ORIGINAL_SCHEMA, self::FIRST_SCHEMA, self::INITIAL_SCHEMA, self::FIRST_RELEASE_SCHEMA, self::ORIGINAL_RELEASE_SCHEMA];
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
            'legacySchema' => self::LEGACY_SCHEMA,
            'legacySchemas' => self::legacy_schemas(),
            // Keep the v1.2 key so existing browser-local notebooks migrate in place.
            'storageKey' => 'scLibraryWorkspaceV120',
            'sourceTypes' => self::source_types(),
            'citationFormats' => self::citation_formats(),
            'matrixTemplates' => self::matrix_templates(),
            'matrixStatuses' => self::matrix_statuses(),
            'defaultMatrixTemplate' => (string) get_option('sc_library_default_matrix_template', 'technical_translation'),
            'matrixEnabled' => self::matrix_enabled(),
            'boardsEnabled' => class_exists('SC_Library_Boards') && SC_Library_Boards::enabled(),
            'integrationsEnabled' => class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled(),
            'annotationsEnabled' => class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled(),
            'booksEnabled' => class_exists('SC_Library_Books') && SC_Library_Books::enabled(),
            'persistentWorkspacesEnabled' => class_exists('SC_Library_Workspaces') && SC_Library_Workspaces::enabled(),
            'strings' => [
                'saved' => __('Saved to the Research Notebook.', 'sustainable-catalyst-library'),
                'alreadySaved' => __('This record is already saved.', 'sustainable-catalyst-library'),
                'storageError' => __('Browser storage is unavailable. Export any visible work before leaving this page.', 'sustainable-catalyst-library'),
                'importError' => __('The selected file is not a valid Sustainable Catalyst Library workspace export.', 'sustainable-catalyst-library'),
                'importSuccess' => __('Workspace imported successfully.', 'sustainable-catalyst-library'),
                'copySuccess' => __('Copied to the clipboard.', 'sustainable-catalyst-library'),
                'copyFailure' => __('Copying was blocked by the browser.', 'sustainable-catalyst-library'),
                'confirmClear' => __('Delete all locally stored Library collections, notes, sources, saved records, translation matrices, Whiteboards, Chalkboards, annotations, custom books, and application handoffs on this browser?', 'sustainable-catalyst-library'),
                'matrixSaved' => __('Technical Translation Matrix saved.', 'sustainable-catalyst-library'),
                'matrixDeleted' => __('Technical Translation Matrix deleted.', 'sustainable-catalyst-library'),
                'matrixTemplateReset' => __('Changing templates resets the current unsaved matrix grid. Continue?', 'sustainable-catalyst-library'),
            ],
        ]);

        if (class_exists('SC_Library_Boards') && SC_Library_Boards::enabled()) {
            SC_Library_Boards::enqueue_assets();
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
        if (class_exists('SC_Library_Portability') && SC_Library_Portability::enabled()) {
            SC_Library_Portability::enqueue_assets();
        }
        if (class_exists('SC_Library_Workspaces') && SC_Library_Workspaces::enabled()) {
            SC_Library_Workspaces::enqueue_assets();
        }
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('The Research Notebook is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Research Notebook',
            'intro' => 'Combine Sustainable Catalyst records with personal notes, sources, Technical Translation Matrices, visual boards, handwritten annotations, custom books, PDF-ready editions, and cross-application research handoffs.',
            'open' => 'true',
            'tab' => 'overview',
        ], $atts, 'sc_library_notebook');

        return $this->render_workspace($atts, true);
    }

    public function render_matrix_shortcode(array $atts = []): string {
        if (!self::matrix_enabled()) {
            return '<p>' . esc_html__('The Technical Translation Matrix is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Technical Translation Matrix',
            'intro' => 'Translate concepts across plain language, formal notation, code, data logic, assumptions, validation, and systems interpretation.',
            'open' => 'true',
            'tab' => 'matrices',
        ], $atts, 'sc_library_translation_matrix');

        return $this->render_workspace($atts, true);
    }

    private function render_workspace(array $atts, bool $standalone): string {
        self::enqueue_assets();
        $workspace_title = sanitize_text_field($atts['title'] ?? 'Research Notebook');
        $workspace_intro = sanitize_text_field($atts['intro'] ?? '');
        $workspace_standalone = $standalone;
        $workspace_open = filter_var($atts['open'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $workspace_initial_tab = sanitize_key((string) ($atts['tab'] ?? 'overview'));
        if (!in_array($workspace_initial_tab, ['overview', 'collections', 'notes', 'sources', 'matrices', 'boards', 'annotations', 'books', 'integrations', 'sync', 'portability'], true)) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'matrices' && !self::matrix_enabled()) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'boards' && (!class_exists('SC_Library_Boards') || !SC_Library_Boards::enabled())) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'annotations' && (!class_exists('SC_Library_Annotations') || !SC_Library_Annotations::enabled())) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'books' && (!class_exists('SC_Library_Books') || !SC_Library_Books::enabled())) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'integrations' && (!class_exists('SC_Library_Integrations') || !SC_Library_Integrations::enabled())) {
            $workspace_initial_tab = 'overview';
        }
        if ($workspace_initial_tab === 'sync' && (!class_exists('SC_Library_Workspaces') || !SC_Library_Workspaces::enabled())) {
            $workspace_initial_tab = 'overview';
        }

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

        register_rest_route($namespace, '/library/matrix-templates', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => self::SCHEMA,
                'items' => array_values(self::matrix_templates()),
                'statuses' => array_values(self::matrix_statuses()),
            ]),
            'permission_callback' => '__return_true',
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

    public static function matrix_statuses(): array {
        return [
            'draft' => ['id' => 'draft', 'label' => __('Draft', 'sustainable-catalyst-library')],
            'translated' => ['id' => 'translated', 'label' => __('Translated', 'sustainable-catalyst-library')],
            'reviewed' => ['id' => 'reviewed', 'label' => __('Reviewed', 'sustainable-catalyst-library')],
            'validated' => ['id' => 'validated', 'label' => __('Validated', 'sustainable-catalyst-library')],
            'warning' => ['id' => 'warning', 'label' => __('Warning / requires review', 'sustainable-catalyst-library')],
            'unsupported' => ['id' => 'unsupported', 'label' => __('Unsupported', 'sustainable-catalyst-library')],
        ];
    }

    public static function matrix_templates(): array {
        return [
            'technical_translation' => [
                'id' => 'technical_translation',
                'label' => __('Technical Translation', 'sustainable-catalyst-library'),
                'description' => __('Translate one concept across meaning, notation, procedure, computation, and interpretation.', 'sustainable-catalyst-library'),
                'columns' => ['Plain language', 'Formal or technical form', 'Computational or data form', 'Systems interpretation'],
                'rows' => ['Core concept', 'Variables and units', 'Assumptions and boundaries', 'Procedure or algorithm', 'Validation and checks', 'Sources and provenance'],
            ],
            'equation_to_code' => [
                'id' => 'equation_to_code',
                'label' => __('Equation to Code', 'sustainable-catalyst-library'),
                'description' => __('Connect a model to variables, executable implementations, tests, and domain meaning.', 'sustainable-catalyst-library'),
                'columns' => ['Plain meaning', 'Mathematical notation', 'Python', 'R', 'Julia', 'Rust', 'Validation'],
                'rows' => ['Expression', 'Variable definitions', 'Units and dimensions', 'Inputs and outputs', 'Assumptions', 'Edge cases', 'Interpretation'],
            ],
            'language_comparison' => [
                'id' => 'language_comparison',
                'label' => __('Programming Language Comparison', 'sustainable-catalyst-library'),
                'description' => __('Compare equivalent logic across supported programming and data environments.', 'sustainable-catalyst-library'),
                'columns' => ['Pseudocode', 'Python', 'R', 'Julia', 'Rust', 'JavaScript', 'SQL', 'Spreadsheet'],
                'rows' => ['Inputs', 'Core operation', 'Iteration or vectorization', 'Output', 'Error handling', 'Validation test'],
            ],
            'source_comparison' => [
                'id' => 'source_comparison',
                'label' => __('Source Comparison', 'sustainable-catalyst-library'),
                'description' => __('Compare how publications, books, videos, datasets, and personal notes frame the same question.', 'sustainable-catalyst-library'),
                'columns' => ['Sustainable Catalyst record', 'Outside source', 'User interpretation', 'Evidence assessment'],
                'rows' => ['Definition', 'Central claim', 'Method or evidence', 'Assumptions', 'Limitations', 'Agreement or disagreement', 'Further research'],
            ],
            'domain_translation' => [
                'id' => 'domain_translation',
                'label' => __('Cross-Domain Translation', 'sustainable-catalyst-library'),
                'description' => __('Compare a shared structure across scientific, technical, institutional, and human systems.', 'sustainable-catalyst-library'),
                'columns' => ['General structure', 'Natural science', 'Technology or engineering', 'Economics or policy', 'Human or institutional systems'],
                'rows' => ['Concept', 'State variables', 'Drivers', 'Constraints', 'Feedback', 'Risk or uncertainty', 'Responsible interpretation'],
            ],
        ];
    }
}
