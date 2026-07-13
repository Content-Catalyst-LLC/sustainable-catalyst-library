<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Research Librarian Workspace Orchestration.
 *
 * The orchestrator is deliberately bounded to indexed Sustainable Catalyst
 * records and their graph relationships. It can propose and package actions,
 * but it never publishes content, applies editorial approval, or changes a
 * workspace without a user confirmation in the browser.
 */
final class SC_Library_Orchestrator {
    public const SCHEMA = 'sc-library-orchestration/1.0';
    public const ACTION_SCHEMA = 'sc-library-orchestration-action/1.0';
    public const SESSION_SCHEMA = 'sc-library-orchestration-session/1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;
    private SC_Library_Knowledge_Graph $graph;

    public function __construct(
        SC_Library_Indexer $indexer,
        SC_Library_Relationships $relationships,
        SC_Library_Knowledge_Graph $graph
    ) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
        $this->graph = $graph;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 45);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_research_librarian_orchestrator', [$this, 'render_shortcode']);
        add_shortcode('sc_library_orchestrator', [$this, 'render_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_orchestrator', 1);
    }

    public static function sessions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_orchestration_sessions';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_orchestration_events';
    }

    public static function intents(): array {
        return [
            'auto' => __('Auto-detect', 'sustainable-catalyst-library'),
            'discover' => __('Discover and explain', 'sustainable-catalyst-library'),
            'collect' => __('Collect records', 'sustainable-catalyst-library'),
            'source' => __('Build a source plan', 'sustainable-catalyst-library'),
            'translate' => __('Start a Technical Translation Matrix', 'sustainable-catalyst-library'),
            'map' => __('Map a system or concept', 'sustainable-catalyst-library'),
            'calculate' => __('Prepare Workbench analysis', 'sustainable-catalyst-library'),
            'decide' => __('Prepare Decision Studio evidence', 'sustainable-catalyst-library'),
            'investigate' => __('Prepare Site Intelligence research', 'sustainable-catalyst-library'),
            'experiment' => __('Prepare a Lab workflow', 'sustainable-catalyst-library'),
            'write' => __('Build a book or article outline', 'sustainable-catalyst-library'),
            'review' => __('Prepare editorial review', 'sustainable-catalyst-library'),
            'publish' => __('Prepare publication workflow', 'sustainable-catalyst-library'),
            'preserve' => __('Prepare preservation and export', 'sustainable-catalyst-library'),
        ];
    }

    public static function target_definitions(): array {
        $integration_targets = class_exists('SC_Library_Integrations') ? SC_Library_Integrations::targets() : [];
        return [
            'notebook' => [
                'id' => 'notebook',
                'label' => __('Research Notebook', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_main_page_url', home_url('/research-library/'))),
                'capabilities' => ['collections', 'notes', 'sources', 'saved_records'],
            ],
            'translation_matrix' => [
                'id' => 'translation_matrix',
                'label' => __('Technical Translation Matrix', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_main_page_url', home_url('/research-library/'))),
                'capabilities' => ['plain_language', 'formal_notation', 'code', 'validation'],
            ],
            'whiteboard' => [
                'id' => 'whiteboard',
                'label' => __('Whiteboard', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_main_page_url', home_url('/research-library/'))),
                'capabilities' => ['mapping', 'relationships', 'systems'],
            ],
            'book_builder' => [
                'id' => 'book_builder',
                'label' => __('Custom Book Builder', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_main_page_url', home_url('/research-library/'))),
                'capabilities' => ['outline', 'edition', 'pdf'],
            ],
            'editorial_workflow' => [
                'id' => 'editorial_workflow',
                'label' => __('Editorial Workflow', 'sustainable-catalyst-library'),
                'url' => esc_url_raw(admin_url('admin.php?page=sc-library-editorial')),
                'capabilities' => ['review', 'comments', 'suggestions', 'approval'],
            ],
            'workbench' => $integration_targets['workbench'] ?? [
                'id' => 'workbench', 'label' => __('Workbench', 'sustainable-catalyst-library'),
                'url' => home_url('/workbench/'), 'capabilities' => ['calculations', 'validation'],
            ],
            'decision_studio' => $integration_targets['decision_studio'] ?? [
                'id' => 'decision_studio', 'label' => __('Decision Studio', 'sustainable-catalyst-library'),
                'url' => home_url('/decision-studio/'), 'capabilities' => ['evidence_synthesis', 'tradeoffs'],
            ],
            'site_intelligence' => $integration_targets['site_intelligence'] ?? [
                'id' => 'site_intelligence', 'label' => __('Site Intelligence', 'sustainable-catalyst-library'),
                'url' => home_url('/site-intelligence/'), 'capabilities' => ['places', 'indicators', 'events'],
            ],
            'lab' => $integration_targets['lab'] ?? [
                'id' => 'lab', 'label' => __('Sustainable Catalyst Lab', 'sustainable-catalyst-library'),
                'url' => home_url('/lab/'), 'capabilities' => ['experiments', 'scientific_methods', 'validation'],
            ],
        ];
    }

    private static function public_targets(): array {
        return array_map(static function (array $target): array {
            unset($target['health_url'], $target['api_key'], $target['secret']);
            return $target;
        }, array_values(self::target_definitions()));
    }

    private static function safe_substr(string $value, int $start, int $length): string {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
    }

    private static function safe_strlen(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    public function register_settings(): void {
        register_setting('sc_library_orchestrator_settings', 'sc_library_enable_orchestrator', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_public_discovery', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_page_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/research-librarian/'),
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_service_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_service_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_timeout', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(30, max(3, absint($value))),
            'default' => 10,
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_max_records', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(20, max(3, absint($value))),
            'default' => 8,
        ]);
        register_setting('sc_library_orchestrator_settings', 'sc_library_orchestrator_graph_depth', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(3, max(1, absint($value))),
            'default' => 1,
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Research Librarian Orchestration', 'sustainable-catalyst-library'),
            __('Research Librarian', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-orchestrator',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-orchestrator')) return;
        wp_enqueue_style('sc-library-orchestrator-admin', SC_LIBRARY_URL . 'assets/css/sc-library-orchestrator.css', [], SC_LIBRARY_VERSION);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to configure Research Librarian orchestration.', 'sustainable-catalyst-library'));
        }
        global $wpdb;
        $session_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::sessions_table());
        $event_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::events_table());
        $graph_summary = $this->graph->summary();
        ?>
        <div class="wrap sc-library-orchestrator-admin">
            <h1><?php esc_html_e('Research Librarian Workspace Orchestration', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Search the canonical Library and Knowledge Graph, explain recommendations, and prepare user-confirmed workspace actions. The orchestrator never publishes, approves, or changes canonical content automatically.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-orchestrator-metrics">
                <article><strong><?php echo esc_html(number_format_i18n($this->indexer->count_indexed())); ?></strong><span><?php esc_html_e('Indexed records', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n((int) ($graph_summary['nodes'] ?? 0))); ?></strong><span><?php esc_html_e('Graph entities', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($session_count)); ?></strong><span><?php esc_html_e('Saved sessions', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($event_count)); ?></strong><span><?php esc_html_e('Attributed events', 'sustainable-catalyst-library'); ?></span></article>
            </div>
            <form method="post" action="options.php" class="card sc-orchestrator-settings">
                <?php settings_fields('sc_library_orchestrator_settings'); ?>
                <h2><?php esc_html_e('Orchestration settings', 'sustainable-catalyst-library'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e('Enable orchestration', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_orchestrator" value="1" <?php checked((int) get_option('sc_library_enable_orchestrator', 1), 1); ?>> <?php esc_html_e('Enable the Research Librarian orchestration interface and routes.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Public discovery', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_orchestrator_public_discovery" value="1" <?php checked((int) get_option('sc_library_orchestrator_public_discovery', 1), 1); ?>> <?php esc_html_e('Allow signed-out readers to receive read-only, site-scoped recommendations. Workspace actions remain local and require confirmation.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-page-url"><?php esc_html_e('Public page URL', 'sustainable-catalyst-library'); ?></label></th><td><input class="regular-text" type="url" id="sc-library-orchestrator-page-url" name="sc_library_orchestrator_page_url" value="<?php echo esc_attr((string) get_option('sc_library_orchestrator_page_url', home_url('/research-librarian/'))); ?>"><p class="description"><code>[sc_research_librarian_orchestrator]</code></p></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-service-url"><?php esc_html_e('Optional synthesis endpoint', 'sustainable-catalyst-library'); ?></label></th><td><input class="regular-text" type="url" id="sc-library-orchestrator-service-url" name="sc_library_orchestrator_service_url" value="<?php echo esc_attr((string) get_option('sc_library_orchestrator_service_url', '')); ?>"><p class="description"><?php esc_html_e('Optional server-to-server Research Librarian endpoint. It receives only the retrieved site records and cannot create or apply actions.', 'sustainable-catalyst-library'); ?></p></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-service-key"><?php esc_html_e('Endpoint API key', 'sustainable-catalyst-library'); ?></label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="sc-library-orchestrator-service-key" name="sc_library_orchestrator_service_api_key" value="<?php echo esc_attr((string) get_option('sc_library_orchestrator_service_api_key', '')); ?>"></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-timeout"><?php esc_html_e('Endpoint timeout', 'sustainable-catalyst-library'); ?></label></th><td><input type="number" min="3" max="30" id="sc-library-orchestrator-timeout" name="sc_library_orchestrator_timeout" value="<?php echo esc_attr((int) get_option('sc_library_orchestrator_timeout', 10)); ?>"> <?php esc_html_e('seconds', 'sustainable-catalyst-library'); ?></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-max-records"><?php esc_html_e('Recommended records', 'sustainable-catalyst-library'); ?></label></th><td><input type="number" min="3" max="20" id="sc-library-orchestrator-max-records" name="sc_library_orchestrator_max_records" value="<?php echo esc_attr((int) get_option('sc_library_orchestrator_max_records', 8)); ?>"></td></tr>
                    <tr><th scope="row"><label for="sc-library-orchestrator-graph-depth"><?php esc_html_e('Graph expansion depth', 'sustainable-catalyst-library'); ?></label></th><td><input type="number" min="1" max="3" id="sc-library-orchestrator-graph-depth" name="sc_library_orchestrator_graph_depth" value="<?php echo esc_attr((int) get_option('sc_library_orchestrator_graph_depth', 1)); ?>"></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <div class="card sc-orchestrator-boundaries">
                <h2><?php esc_html_e('Required boundaries', 'sustainable-catalyst-library'); ?></h2>
                <ul>
                    <li><?php esc_html_e('Retrieval is restricted to indexed Sustainable Catalyst records and public graph relationships.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('AI synthesis, when configured, cannot alter actions, permissions, records, or publication states.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('Every workspace action is shown before application and requires a user click.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('Publication, approval, scheduling, and canonical edits stay inside their existing WordPress workflows.', 'sustainable-catalyst-library'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) return;
        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-orchestrator', SC_LIBRARY_URL . 'assets/css/sc-library-orchestrator.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-orchestrator', SC_LIBRARY_URL . 'assets/js/sc-library-orchestrator.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-orchestrator', 'SCOrchestratorShared', [
            'version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'actionSchema' => self::ACTION_SCHEMA,
            'workspaceSchema' => SC_Library_Notebook::SCHEMA,
            'storageKey' => 'scLibraryWorkspaceV120',
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library/orchestrator')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'signedIn' => is_user_logged_in(),
            'targets' => self::public_targets(),
            'intents' => array_map(static fn($label, $id) => ['id' => $id, 'label' => $label], self::intents(), array_keys(self::intents())),
            'strings' => [
                'working' => __('Searching the Library and Knowledge Graph…', 'sustainable-catalyst-library'),
                'error' => __('The Research Librarian could not complete this request.', 'sustainable-catalyst-library'),
                'confirmAction' => __('Apply this action to your local Research Notebook?', 'sustainable-catalyst-library'),
                'applied' => __('Action applied to the Research Notebook.', 'sustainable-catalyst-library'),
                'savedSession' => __('Orchestration session saved to your account.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('Research Librarian orchestration is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }
        if (!is_user_logged_in() && !(bool) get_option('sc_library_orchestrator_public_discovery', 1)) {
            return '<p>' . esc_html__('Sign in to use Research Librarian orchestration.', 'sustainable-catalyst-library') . '</p>';
        }
        $atts = shortcode_atts([
            'title' => 'Research Librarian',
            'intro' => 'Ask a site-scoped research question, review why records were recommended, and choose which actions to apply to your workspace.',
            'intent' => 'auto',
            'record' => '0',
            'compact' => 'false',
        ], $atts, 'sc_research_librarian_orchestrator');
        self::enqueue_assets();
        $orchestrator_title = sanitize_text_field((string) $atts['title']);
        $orchestrator_intro = sanitize_text_field((string) $atts['intro']);
        $orchestrator_intent = array_key_exists(sanitize_key((string) $atts['intent']), self::intents()) ? sanitize_key((string) $atts['intent']) : 'auto';
        $requested_record = isset($_GET['record']) ? absint(wp_unslash($_GET['record'])) : 0;
        $orchestrator_record = absint($atts['record']) ?: $requested_record;
        $orchestrator_compact = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-orchestrator.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';
        register_rest_route($namespace, '/library/orchestrator/schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->schema_payload()),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/orchestrator/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->status_payload()),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/orchestrator/query', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_query'],
            'permission_callback' => fn() => self::enabled() && (is_user_logged_in() || (bool) get_option('sc_library_orchestrator_public_discovery', 1)),
            'args' => [
                'prompt' => ['required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
                'intent' => ['sanitize_callback' => 'sanitize_key', 'default' => 'auto'],
                'record_ids' => ['default' => []],
                'max_records' => ['sanitize_callback' => 'absint', 'default' => 0],
            ],
        ]);
        register_rest_route($namespace, '/library/orchestrator/sessions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_sessions'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rest_save_session'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);
        register_rest_route($namespace, '/library/orchestrator/sessions/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_session'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'rest_delete_session'],
                'permission_callback' => static fn() => is_user_logged_in(),
            ],
        ]);
        register_rest_route($namespace, '/library/orchestrator/events', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_event'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
    }

    private function schema_payload(): array {
        return [
            'schema' => self::SCHEMA,
            'session_schema' => self::SESSION_SCHEMA,
            'action_schema' => self::ACTION_SCHEMA,
            'intents' => array_map(static fn($label, $id) => ['id' => $id, 'label' => $label], self::intents(), array_keys(self::intents())),
            'targets' => self::public_targets(),
            'action_types' => ['create_collection', 'save_records', 'create_note', 'create_matrix', 'create_board', 'create_book', 'create_handoff', 'open_editorial', 'export_workspace'],
            'boundaries' => [
                'site_scoped_retrieval' => true,
                'user_confirmation_required' => true,
                'automatic_publication' => false,
                'automatic_approval' => false,
                'remote_synthesis_can_modify_actions' => false,
            ],
        ];
    }

    private function status_payload(): array {
        global $wpdb;
        return [
            'ok' => self::enabled(),
            'version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'indexed_records' => $this->indexer->count_indexed(),
            'graph' => $this->graph->summary(),
            'remote_synthesis' => [
                'configured' => (string) get_option('sc_library_orchestrator_service_url', '') !== '',
                'mode' => (string) get_option('sc_library_orchestrator_service_url', '') !== '' ? 'optional_remote_synthesis' : 'deterministic_site_scoped',
            ],
            'saved_sessions' => is_user_logged_in() ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::sessions_table() . ' WHERE owner_user_id = %d', get_current_user_id())) : 0,
            'targets' => self::public_targets(),
        ];
    }

    public function rest_query(WP_REST_Request $request): WP_REST_Response {
        if (!$this->allow_query()) {
            return new WP_REST_Response(['message' => __('Too many orchestration requests. Please wait before trying again.', 'sustainable-catalyst-library')], 429);
        }
        $prompt = trim(wp_strip_all_tags((string) $request['prompt']));
        $prompt = self::safe_substr($prompt, 0, 1200);
        if (self::safe_strlen($prompt) < 3) {
            return new WP_REST_Response(['message' => __('Enter a more specific research question.', 'sustainable-catalyst-library')], 400);
        }
        $requested_intent = sanitize_key((string) $request['intent']);
        $intent = $requested_intent === 'auto' || !array_key_exists($requested_intent, self::intents()) ? $this->infer_intent($prompt) : $requested_intent;
        $record_ids = array_values(array_unique(array_filter(array_map('absint', is_array($request['record_ids']) ? $request['record_ids'] : []))));
        $max_records = absint($request['max_records']);
        if ($max_records < 1) $max_records = (int) get_option('sc_library_orchestrator_max_records', 8);
        $max_records = min(20, max(3, $max_records));
        $keywords = $this->keywords($prompt);
        $records = $this->search_records($prompt, $keywords, $record_ids, $max_records);
        $graph_expansion = $this->graph_expansion($records, $max_records);
        foreach ($graph_expansion as $graph_record) {
            if (count($records) >= $max_records) break;
            $exists = false;
            foreach ($records as $record) {
                if ((int) $record['id'] === (int) $graph_record['id']) { $exists = true; break; }
            }
            if (!$exists) $records[] = $graph_record;
        }
        $records = array_slice($records, 0, $max_records);
        $routes = $this->route_recommendations($intent, $prompt, $records);
        $actions = $this->action_packets($intent, $prompt, $records, $routes);
        $answer = $this->deterministic_answer($intent, $prompt, $records, $routes);
        $provider = ['mode' => 'deterministic', 'provider' => 'sustainable-catalyst-library', 'model' => 'site-scoped-orchestration-rules'];
        $remote = $this->remote_synthesis($prompt, $intent, $records, $routes, $answer);
        if (is_array($remote) && !empty($remote['answer'])) {
            $answer = sanitize_textarea_field((string) $remote['answer']);
            $provider = [
                'mode' => 'remote_synthesis',
                'provider' => sanitize_text_field((string) ($remote['provider'] ?? 'configured-service')),
                'model' => sanitize_text_field((string) ($remote['model'] ?? '')),
            ];
        }
        $packet = [
            'schema' => self::SCHEMA,
            'id' => wp_generate_uuid4(),
            'created_at' => current_time('c'),
            'prompt' => $prompt,
            'intent' => $intent,
            'intent_label' => self::intents()[$intent] ?? ucfirst($intent),
            'answer' => $answer,
            'records' => $records,
            'routes' => $routes,
            'actions' => $actions,
            'diagnostics' => [
                'retrieval_mode' => 'index_plus_graph',
                'keywords' => $keywords,
                'selected_record_ids' => $record_ids,
                'recommended_record_count' => count($records),
                'graph_expansion_count' => count($graph_expansion),
                'provider' => $provider,
                'scope' => 'sustainable-catalyst-library-and-public-knowledge-graph',
            ],
            'boundaries' => $this->schema_payload()['boundaries'],
        ];
        return rest_ensure_response($packet);
    }

    private function allow_query(): bool {
        if (current_user_can('edit_posts')) return true;
        $address = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'sc_orch_rate_' . md5($address . '|' . gmdate('YmdHi'));
        $count = (int) get_transient($key);
        if ($count >= 12) return false;
        set_transient($key, $count + 1, 2 * MINUTE_IN_SECONDS);
        return true;
    }

    private function infer_intent(string $prompt): string {
        $text = strtolower($prompt);
        $patterns = [
            'calculate' => ['calculate', 'equation', 'formula', 'model', 'quantitative', 'graph', 'simulate'],
            'decide' => ['decision', 'tradeoff', 'trade-off', 'choose', 'recommend policy', 'options'],
            'investigate' => ['country', 'city', 'place', 'map', 'indicator', 'geographic', 'event'],
            'experiment' => ['experiment', 'laboratory', 'lab ', 'measurement', 'instrument', 'sample'],
            'translate' => ['translate', 'plain language', 'formal notation', 'code equivalent', 'matrix'],
            'map' => ['map the system', 'whiteboard', 'relationship map', 'causal', 'stakeholder map'],
            'source' => ['sources', 'bibliography', 'evidence base', 'citations', 'literature review'],
            'write' => ['outline', 'book', 'essay', 'article draft', 'chapter'],
            'review' => ['review', 'fact check', 'edit', 'critique'],
            'publish' => ['publish', 'schedule', 'release', 'editorial workflow'],
            'preserve' => ['archive', 'preserve', 'export', 'snapshot', 'checksum'],
            'collect' => ['collect', 'save', 'reading list', 'collection'],
        ];
        foreach ($patterns as $intent => $needles) {
            foreach ($needles as $needle) if (str_contains($text, $needle)) return $intent;
        }
        return 'discover';
    }

    private function keywords(string $prompt): array {
        $stop = array_fill_keys(['about','after','again','also','among','and','are','because','before','between','build','can','could','does','from','have','how','into','more','most','not','our','should','that','the','their','them','then','there','these','they','this','through','using','want','what','when','where','which','while','with','would','your'], true);
        $parts = preg_split('/[^\pL\pN_-]+/u', strtolower($prompt)) ?: [];
        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (self::safe_strlen($part) < 3 || isset($stop[$part])) continue;
            $keywords[$part] = true;
            if (count($keywords) >= 10) break;
        }
        return array_keys($keywords);
    }

    private function search_records(string $prompt, array $keywords, array $selected_ids, int $limit): array {
        global $wpdb;
        $table = $this->indexer->table_name();
        $rows = [];
        if ($selected_ids) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '%d'));
            $selected = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = 'publish' AND post_id IN ({$placeholders})", ...$selected_ids), ARRAY_A) ?: [];
            foreach ($selected as $row) $rows[(int) $row['post_id']] = $row;
        }
        $terms = array_values(array_unique(array_filter(array_merge([$prompt], $keywords))));
        $clauses = [];
        $where_values = [];
        foreach (array_slice($terms, 0, 8) as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $clauses[] = '(title LIKE %s OR excerpt LIKE %s OR searchable_text LIKE %s)';
            array_push($where_values, $like, $like, $like);
        }
        if ($clauses) {
            $score_parts = [];
            $score_values = [];
            $exact = $prompt;
            $prefix = $wpdb->esc_like($prompt) . '%';
            $like_prompt = '%' . $wpdb->esc_like($prompt) . '%';
            $score_parts[] = 'CASE WHEN LOWER(title) = LOWER(%s) THEN 240 WHEN title LIKE %s THEN 150 WHEN title LIKE %s THEN 95 WHEN excerpt LIKE %s THEN 55 ELSE 10 END';
            array_push($score_values, $exact, $prefix, $like_prompt, $like_prompt);
            foreach (array_slice($keywords, 0, 6) as $keyword) {
                $like = '%' . $wpdb->esc_like($keyword) . '%';
                $score_parts[] = '(CASE WHEN title LIKE %s THEN 18 WHEN excerpt LIKE %s THEN 8 WHEN searchable_text LIKE %s THEN 3 ELSE 0 END)';
                array_push($score_values, $like, $like, $like);
            }
            $sql = "SELECT *, (" . implode(' + ', $score_parts) . ") AS orchestration_score FROM {$table} WHERE status = 'publish' AND (" . implode(' OR ', $clauses) . ") ORDER BY orchestration_score DESC, modified_at DESC LIMIT %d";
            $values = array_merge($score_values, $where_values, [max($limit * 3, 20)]);
            $matched = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A) ?: [];
            foreach ($matched as $row) $rows[(int) $row['post_id']] = $row;
        }
        $records = [];
        foreach (array_values($rows) as $row) {
            $records[] = $this->hydrate_record($row, $prompt, $keywords, in_array((int) $row['post_id'], $selected_ids, true));
        }
        usort($records, static fn($a, $b) => ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0)));
        return array_slice($records, 0, $limit);
    }

    private function hydrate_record(array $row, string $prompt, array $keywords, bool $selected = false): array {
        $post_id = (int) $row['post_id'];
        $category_ids = json_decode((string) ($row['category_ids'] ?? '[]'), true) ?: [];
        $concept_ids = json_decode((string) ($row['concept_ids'] ?? '[]'), true) ?: [];
        $categories = $this->term_names($category_ids, 'category');
        $concepts = $this->term_names($concept_ids, SC_Library_Taxonomies::CONCEPT);
        $series_name = '';
        $series_id = (int) ($row['series_term_id'] ?? 0);
        if ($series_id > 0) {
            $term = get_term($series_id, SC_Library_Taxonomies::SERIES);
            if ($term && !is_wp_error($term)) $series_name = (string) $term->name;
        }
        $haystack = strtolower((string) $row['title'] . ' ' . (string) $row['excerpt']);
        $matched = [];
        foreach ($keywords as $keyword) if (str_contains($haystack, strtolower($keyword))) $matched[] = $keyword;
        $reasons = [];
        if ($selected) $reasons[] = __('You selected this record as context.', 'sustainable-catalyst-library');
        if (strcasecmp(trim((string) $row['title']), trim($prompt)) === 0) $reasons[] = __('Its title exactly matches the request.', 'sustainable-catalyst-library');
        elseif ($matched) $reasons[] = sprintf(__('It matches the request through: %s.', 'sustainable-catalyst-library'), implode(', ', array_slice($matched, 0, 5)));
        if ($series_name !== '') $reasons[] = sprintf(__('It belongs to the “%s” sequence.', 'sustainable-catalyst-library'), $series_name);
        if ($concepts) $reasons[] = sprintf(__('Connected concepts include %s.', 'sustainable-catalyst-library'), implode(', ', array_slice($concepts, 0, 4)));
        $page_hits = [];
        if (class_exists('SC_Library_Foundation_Documents') && (string) $row['post_type'] === SC_Library_Foundation_Documents::POST_TYPE) {
            $page_hits = SC_Library_Foundation_Documents::page_hits($post_id, $prompt, 4);
            if ($page_hits) {
                $pages = implode(', ', array_map(static fn(array $hit): string => (string) $hit['page'], $page_hits));
                $reasons[] = sprintf(__('The extracted PDF text matches on page(s) %s.', 'sustainable-catalyst-library'), $pages);
            }
        }
        if (!$reasons) $reasons[] = __('It is one of the strongest indexed matches.', 'sustainable-catalyst-library');
        return [
            'id' => $post_id,
            'record_identifier' => (string) ($row['record_identifier'] ?? sprintf('sc:library:%s:%d', $row['post_type'], $post_id)),
            'post_type' => (string) $row['post_type'],
            'title' => (string) $row['title'],
            'excerpt' => wp_trim_words(wp_strip_all_tags((string) $row['excerpt']), 42),
            'url' => esc_url_raw((string) $row['permalink']),
            'published_at' => (string) ($row['published_at'] ?? ''),
            'modified_at' => (string) ($row['modified_at'] ?? ''),
            'categories' => $categories,
            'concepts' => $concepts,
            'series' => $series_name,
            'score' => (float) ($row['orchestration_score'] ?? ($selected ? 500 : 0)),
            'why' => $reasons,
            'graph_related' => false,
            'pdf_page_hits' => $page_hits,
        ];
    }

    private function graph_expansion(array $records, int $limit): array {
        $expanded = [];
        $depth = min(3, max(1, (int) get_option('sc_library_orchestrator_graph_depth', 1)));
        foreach (array_slice($records, 0, 3) as $record) {
            $payload = $this->graph->graph_payload(['root' => 'record:' . (int) $record['id'], 'depth' => $depth, 'limit' => 60], false);
            foreach (($payload['nodes'] ?? []) as $node) {
                if (($node['type'] ?? '') !== 'record' || empty($node['post_id']) || (int) $node['post_id'] === (int) $record['id']) continue;
                $row = $this->index_row((int) $node['post_id']);
                if (!$row) continue;
                $item = $this->hydrate_record($row, '', [], false);
                $item['score'] = 25.0;
                $item['graph_related'] = true;
                $item['why'] = [sprintf(__('The Knowledge Graph connects it to “%s”.', 'sustainable-catalyst-library'), $record['title'])];
                $expanded[(int) $item['id']] = $item;
                if (count($expanded) >= $limit) break 2;
            }
        }
        return array_values($expanded);
    }

    private function index_row(int $post_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->indexer->table_name() . " WHERE post_id = %d AND status = 'publish'", $post_id), ARRAY_A);
        return $row ?: null;
    }

    private function term_names(array $ids, string $taxonomy): array {
        $names = [];
        foreach (array_slice(array_unique(array_map('absint', $ids)), 0, 12) as $id) {
            $term = get_term($id, $taxonomy);
            if ($term && !is_wp_error($term)) $names[] = (string) $term->name;
        }
        return $names;
    }

    private function route_recommendations(string $intent, string $prompt, array $records): array {
        $targets = self::target_definitions();
        $route_ids = match ($intent) {
            'collect', 'source', 'discover' => ['notebook'],
            'translate' => ['translation_matrix', 'notebook'],
            'map' => ['whiteboard', 'notebook'],
            'calculate' => ['workbench', 'notebook'],
            'decide' => ['decision_studio', 'notebook'],
            'investigate' => ['site_intelligence', 'notebook'],
            'experiment' => ['lab', 'notebook'],
            'write' => ['book_builder', 'notebook'],
            'review', 'publish' => ['editorial_workflow', 'notebook'],
            'preserve' => ['book_builder', 'notebook'],
            default => ['notebook'],
        };
        $routes = [];
        foreach ($route_ids as $position => $id) {
            if (!isset($targets[$id])) continue;
            $target = $targets[$id];
            $routes[] = [
                'id' => $id,
                'label' => (string) $target['label'],
                'url' => esc_url_raw((string) ($target['url'] ?? '')),
                'priority' => $position + 1,
                'reason' => $this->route_reason($id, $intent, $prompt, $records),
                'capabilities' => array_values($target['capabilities'] ?? []),
            ];
        }
        return $routes;
    }

    private function route_reason(string $target, string $intent, string $prompt, array $records): string {
        return match ($target) {
            'workbench' => __('The request contains a calculation, model, equation, graph, or validation task.', 'sustainable-catalyst-library'),
            'decision_studio' => __('The request needs claims, evidence, assumptions, uncertainty, tradeoffs, or options organized into a decision packet.', 'sustainable-catalyst-library'),
            'site_intelligence' => __('The request depends on places, countries, indicators, events, maps, or source freshness.', 'sustainable-catalyst-library'),
            'lab' => __('The request is best advanced through an experiment, measurement, scientific method, or validation workflow.', 'sustainable-catalyst-library'),
            'translation_matrix' => __('The request asks for translation across plain language, technical notation, code, assumptions, and validation.', 'sustainable-catalyst-library'),
            'whiteboard' => __('The request benefits from a visual system map of concepts, actors, dependencies, or causal relationships.', 'sustainable-catalyst-library'),
            'book_builder' => $intent === 'preserve' ? __('A frozen edition or portable document can preserve the selected research packet.', 'sustainable-catalyst-library') : __('The selected records can become an ordered article, chapter, or book outline.', 'sustainable-catalyst-library'),
            'editorial_workflow' => __('The request needs accountable review, comments, suggestions, approval, or publication coordination.', 'sustainable-catalyst-library'),
            default => sprintf(__('The Notebook can collect the %d recommended records and preserve the research question.', 'sustainable-catalyst-library'), count($records)),
        };
    }

    private function action_packets(string $intent, string $prompt, array $records, array $routes): array {
        $record_payload = array_map(static fn($record) => [
            'recordId' => (int) $record['id'],
            'recordIdentifier' => (string) $record['record_identifier'],
            'title' => (string) $record['title'],
            'url' => (string) $record['url'],
            'excerpt' => (string) $record['excerpt'],
            'categories' => (array) $record['categories'],
            'concepts' => (array) $record['concepts'],
            'series' => (string) $record['series'],
        ], $records);
        $collection_title = wp_trim_words($prompt, 8, '…');
        $actions = [
            $this->action('create_collection', __('Create a research collection', 'sustainable-catalyst-library'), __('Create a dedicated Notebook collection for this question.', 'sustainable-catalyst-library'), [
                'title' => $collection_title,
                'description' => $prompt,
            ]),
            $this->action('save_records', __('Save recommended records', 'sustainable-catalyst-library'), sprintf(__('Save %d records to the new collection.', 'sustainable-catalyst-library'), count($records)), [
                'records' => $record_payload,
                'collection_title' => $collection_title,
            ]),
            $this->action('create_note', __('Create a research brief', 'sustainable-catalyst-library'), __('Create a source-aware Notebook note containing the question, route, and next steps.', 'sustainable-catalyst-library'), [
                'title' => 'Research brief: ' . $collection_title,
                'body' => $this->note_body($prompt, $records, $routes),
                'tags' => [$intent, 'research-librarian'],
                'collection_title' => $collection_title,
            ]),
        ];
        if ($intent === 'translate') {
            $actions[] = $this->action('create_matrix', __('Start a Technical Translation Matrix', 'sustainable-catalyst-library'), __('Create a draft matrix seeded with the research question and primary record.', 'sustainable-catalyst-library'), ['title' => $collection_title, 'prompt' => $prompt, 'record' => $record_payload[0] ?? []]);
        }
        if ($intent === 'map') {
            $actions[] = $this->action('create_board', __('Create a Whiteboard map', 'sustainable-catalyst-library'), __('Create a Whiteboard with record nodes and a central research-question node.', 'sustainable-catalyst-library'), ['title' => $collection_title, 'prompt' => $prompt, 'records' => $record_payload]);
        }
        if ($intent === 'write' || $intent === 'preserve') {
            $actions[] = $this->action('create_book', $intent === 'preserve' ? __('Create a preservation edition', 'sustainable-catalyst-library') : __('Create a book outline', 'sustainable-catalyst-library'), __('Create a local book project containing the recommended records in order.', 'sustainable-catalyst-library'), ['title' => $collection_title, 'prompt' => $prompt, 'records' => $record_payload, 'edition' => $intent === 'preserve' ? 'Frozen research packet' : 'Draft outline']);
        }
        foreach ($routes as $route) {
            if (!in_array($route['id'], ['workbench', 'decision_studio', 'site_intelligence', 'lab'], true)) continue;
            $actions[] = $this->action('create_handoff', sprintf(__('Prepare %s handoff', 'sustainable-catalyst-library'), $route['label']), $route['reason'], [
                'target' => $route['id'],
                'target_label' => $route['label'],
                'launch_url' => $route['url'],
                'prompt' => $prompt,
                'intent' => $intent,
                'records' => $record_payload,
            ]);
        }
        if (in_array($intent, ['review', 'publish'], true)) {
            $actions[] = $this->action('open_editorial', __('Open an editorial review packet', 'sustainable-catalyst-library'), __('Create a local review brief and open the existing accountable editorial workflow. Nothing is published automatically.', 'sustainable-catalyst-library'), ['url' => admin_url('admin.php?page=sc-library-editorial'), 'prompt' => $prompt, 'records' => $record_payload]);
        }
        if ($intent === 'preserve') {
            $actions[] = $this->action('export_workspace', __('Export the workspace before preservation', 'sustainable-catalyst-library'), __('Download a complete browser-workspace JSON backup before creating frozen editions.', 'sustainable-catalyst-library'), []);
        }
        return $actions;
    }

    private function action(string $type, string $label, string $description, array $payload): array {
        return [
            'schema' => self::ACTION_SCHEMA,
            'id' => wp_generate_uuid4(),
            'type' => $type,
            'label' => $label,
            'description' => $description,
            'requires_confirmation' => true,
            'automatic' => false,
            'payload' => $payload,
        ];
    }

    private function note_body(string $prompt, array $records, array $routes): string {
        $lines = ["Research question\n{$prompt}", '', 'Recommended records'];
        foreach ($records as $position => $record) $lines[] = sprintf('%d. %s — %s', $position + 1, $record['title'], $record['url']);
        $lines[] = '';
        $lines[] = 'Recommended route';
        foreach ($routes as $route) $lines[] = sprintf('- %s: %s', $route['label'], $route['reason']);
        $lines[] = '';
        $lines[] = 'Boundary: This packet is a Research Librarian recommendation, not a canonical publication or automatic approval.';
        return implode("\n", $lines);
    }

    private function deterministic_answer(string $intent, string $prompt, array $records, array $routes): string {
        if (!$records) {
            return __('I could not find a strong indexed match. Try a narrower topic, a known article title, or rebuild the Library index if the expected records are missing.', 'sustainable-catalyst-library');
        }
        $titles = array_map(static fn($record) => '“' . $record['title'] . '”', array_slice($records, 0, 3));
        $route = $routes[0]['label'] ?? __('Research Notebook', 'sustainable-catalyst-library');
        return sprintf(
            __('I found %1$d site records that directly or graphically connect to this request. The strongest starting points are %2$s. The recommended next environment is %3$s. Review the retrieval reasons below, then apply only the workspace actions that are useful.', 'sustainable-catalyst-library'),
            count($records),
            implode(', ', $titles),
            $route
        );
    }

    private function remote_synthesis(string $prompt, string $intent, array $records, array $routes, string $fallback): ?array {
        $url = esc_url_raw((string) get_option('sc_library_orchestrator_service_url', ''));
        if ($url === '') return null;
        $key = (string) get_option('sc_library_orchestrator_service_api_key', '');
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'Sustainable-Catalyst-Library/' . SC_LIBRARY_VERSION];
        if ($key !== '') $headers['Authorization'] = 'Bearer ' . $key;
        $response = wp_remote_post($url, [
            'timeout' => (int) get_option('sc_library_orchestrator_timeout', 10),
            'redirection' => 1,
            'headers' => $headers,
            'body' => wp_json_encode([
                'schema' => self::SCHEMA,
                'task' => 'synthesize_site_scoped_research_route',
                'prompt' => $prompt,
                'intent' => $intent,
                'records' => array_map(static fn($record) => [
                    'id' => $record['id'], 'title' => $record['title'], 'excerpt' => $record['excerpt'],
                    'url' => $record['url'], 'why' => $record['why'], 'concepts' => $record['concepts'],
                ], $records),
                'routes' => $routes,
                'fallback_answer' => $fallback,
                'constraints' => [
                    'use_only_supplied_records' => true,
                    'do_not_create_actions' => true,
                    'do_not_claim_publication_or_approval' => true,
                ],
            ]),
        ]);
        if (is_wp_error($response)) return null;
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) return null;
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return null;
        $answer = $body['answer'] ?? $body['response'] ?? $body['message'] ?? '';
        if (!is_string($answer) || trim($answer) === '') return null;
        return ['answer' => $answer, 'provider' => $body['provider'] ?? 'configured-service', 'model' => $body['model'] ?? ''];
    }

    public function rest_sessions(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::sessions_table() . ' WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 50', get_current_user_id()), ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::SESSION_SCHEMA, 'items' => array_map([$this, 'hydrate_session'], $rows)]);
    }

    public function rest_save_session(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $packet = $request->get_json_params();
        $packet = is_array($packet) ? $packet : [];
        $response = is_array($packet['response'] ?? null) ? $packet['response'] : [];
        if (($response['schema'] ?? '') !== self::SCHEMA) return new WP_REST_Response(['message' => __('Invalid orchestration response schema.', 'sustainable-catalyst-library')], 400);
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $title = sanitize_text_field((string) ($packet['title'] ?? wp_trim_words((string) ($response['prompt'] ?? 'Research session'), 8, '…')));
        $wpdb->insert(self::sessions_table(), [
            'session_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'title' => self::safe_substr($title, 0, 255),
            'question' => sanitize_textarea_field((string) ($response['prompt'] ?? '')),
            'intent' => sanitize_key((string) ($response['intent'] ?? 'discover')),
            'status' => 'active',
            'provider' => sanitize_text_field((string) ($response['diagnostics']['provider']['provider'] ?? 'sustainable-catalyst-library')),
            'model' => sanitize_text_field((string) ($response['diagnostics']['provider']['model'] ?? '')),
            'retrieval_mode' => sanitize_text_field((string) ($response['diagnostics']['retrieval_mode'] ?? 'index_plus_graph')),
            'response_json' => wp_json_encode($response),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $session_id = (int) $wpdb->insert_id;
        $this->insert_event($session_id, 'session_saved', ['title' => $title], get_current_user_id());
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::sessions_table() . ' WHERE id = %d', $session_id), ARRAY_A);
        return new WP_REST_Response($this->hydrate_session($row ?: []), 201);
    }

    public function rest_session(WP_REST_Request $request): WP_REST_Response {
        $row = $this->session_row((string) $request['uuid']);
        if (!$row) return new WP_REST_Response(['message' => __('Orchestration session not found.', 'sustainable-catalyst-library')], 404);
        return rest_ensure_response($this->hydrate_session($row));
    }

    public function rest_delete_session(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $row = $this->session_row((string) $request['uuid']);
        if (!$row) return new WP_REST_Response(['message' => __('Orchestration session not found.', 'sustainable-catalyst-library')], 404);
        $wpdb->delete(self::events_table(), ['session_id' => (int) $row['id']], ['%d']);
        $wpdb->delete(self::sessions_table(), ['id' => (int) $row['id']], ['%d']);
        return rest_ensure_response(['deleted' => true, 'uuid' => (string) $row['session_uuid']]);
    }

    public function rest_event(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : [];
        $uuid = sanitize_text_field((string) ($params['session_uuid'] ?? ''));
        $row = $this->session_row($uuid);
        if (!$row) return new WP_REST_Response(['message' => __('Orchestration session not found.', 'sustainable-catalyst-library')], 404);
        $event_type = sanitize_key((string) ($params['event_type'] ?? 'action_applied'));
        $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];
        $event_id = $this->insert_event((int) $row['id'], $event_type, $payload, get_current_user_id());
        return new WP_REST_Response(['id' => $event_id, 'event_type' => $event_type], 201);
    }

    private function session_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::sessions_table() . ' WHERE session_uuid = %s AND owner_user_id = %d', $uuid, get_current_user_id()), ARRAY_A);
        return $row ?: null;
    }

    private function hydrate_session(array $row): array {
        return [
            'schema' => self::SESSION_SCHEMA,
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['session_uuid'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'question' => (string) ($row['question'] ?? ''),
            'intent' => (string) ($row['intent'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'provider' => (string) ($row['provider'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'retrieval_mode' => (string) ($row['retrieval_mode'] ?? ''),
            'response' => json_decode((string) ($row['response_json'] ?? ''), true) ?: [],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function insert_event(int $session_id, string $event_type, array $payload, int $user_id): int {
        global $wpdb;
        $wpdb->insert(self::events_table(), [
            'session_id' => $session_id,
            'event_uuid' => wp_generate_uuid4(),
            'event_type' => sanitize_key($event_type),
            'payload_json' => wp_json_encode($payload),
            'created_by' => $user_id,
            'created_at' => current_time('mysql', true),
        ]);
        return (int) $wpdb->insert_id;
    }
}
