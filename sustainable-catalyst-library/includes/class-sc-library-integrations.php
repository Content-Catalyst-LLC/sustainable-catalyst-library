<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Integrations {
    public const HANDOFF_SCHEMA = 'sc-library-handoff/1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_shortcode('sc_library_integrations', [$this, 'render_shortcode']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function enabled(): bool {
        return SC_Library_Notebook::enabled() && (bool) get_option('sc_library_enable_integrations', 1);
    }

    public static function targets(): array {
        return [
            'workbench' => [
                'id' => 'workbench',
                'label' => __('Library Workbench', 'sustainable-catalyst-library'),
                'action_label' => __('Analyze in Workbench', 'sustainable-catalyst-library'),
                'description' => __('Carry equations, methods, variables, datasets, matrices, and technical notes into a calculation and validation workflow.', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_workbench_url', home_url('/workbench/'))),
                'health_url' => esc_url_raw((string) get_option('sc_library_workbench_health_url', '')),
                'capabilities' => ['calculations', 'equations', 'graphs', 'validation', 'technical_reports'],
            ],
            'decision_studio' => [
                'id' => 'decision_studio',
                'label' => __('Library Decision Studio', 'sustainable-catalyst-library'),
                'action_label' => __('Build Decision Canvas', 'sustainable-catalyst-library'),
                'description' => __('Turn selected knowledge into a focused evidence canvas with claims, assumptions, uncertainties, tradeoffs, and research gaps.', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_decision_studio_url', home_url('/decision-studio/'))),
                'health_url' => esc_url_raw((string) get_option('sc_library_decision_studio_health_url', '')),
                'capabilities' => ['evidence_synthesis', 'claims', 'assumptions', 'uncertainty', 'tradeoffs'],
            ],
            'site_intelligence' => [
                'id' => 'site_intelligence',
                'label' => __('Library Site Intelligence', 'sustainable-catalyst-library'),
                'action_label' => __('Investigate Geographic Context', 'sustainable-catalyst-library'),
                'description' => __('Connect publications and sources to countries, places, indicators, datasets, events, and source-status information.', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_site_intelligence_url', home_url('/site-intelligence/'))),
                'health_url' => esc_url_raw((string) get_option('sc_library_site_intelligence_health_url', '')),
                'capabilities' => ['countries', 'places', 'indicators', 'maps', 'events', 'source_status'],
            ],
            'lab' => [
                'id' => 'lab',
                'label' => __('Sustainable Catalyst Lab', 'sustainable-catalyst-library'),
                'action_label' => __('Prepare Lab Workflow', 'sustainable-catalyst-library'),
                'description' => __('Carry research questions, methods, variables, evidence, and validation requirements into a scientific or engineering laboratory workflow.', 'sustainable-catalyst-library'),
                'url' => esc_url_raw((string) get_option('sc_library_lab_url', home_url('/lab/'))),
                'health_url' => esc_url_raw((string) get_option('sc_library_lab_health_url', '')),
                'capabilities' => ['experiments', 'measurements', 'scientific_methods', 'engineering_validation', 'reproducibility'],
            ],
        ];
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-notebook', SC_LIBRARY_URL . 'assets/css/sc-library-notebook.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-integrations', SC_LIBRARY_URL . 'assets/css/sc-library-integrations.css', ['sc-library-notebook'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-integrations', SC_LIBRARY_URL . 'assets/js/sc-library-integrations.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-integrations', 'SCIntegrationShared', [
            'version' => SC_LIBRARY_VERSION,
            'workspaceSchema' => SC_Library_Notebook::SCHEMA,
            'legacySchemas' => SC_Library_Notebook::legacy_schemas(),
            'handoffSchema' => self::HANDOFF_SCHEMA,
            'storageKey' => 'scLibraryWorkspaceV120',
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'targets' => array_values(self::public_targets()),
            'strings' => [
                'saved' => __('Integration handoff saved to the Research Notebook.', 'sustainable-catalyst-library'),
                'copied' => __('Handoff JSON copied.', 'sustainable-catalyst-library'),
                'copyError' => __('The browser blocked copying. Download the handoff instead.', 'sustainable-catalyst-library'),
                'statusError' => __('Service status could not be checked.', 'sustainable-catalyst-library'),
                'selectContext' => __('Choose a Library or Notebook object first.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    private static function public_targets(): array {
        $targets = self::targets();
        foreach ($targets as &$target) {
            unset($target['health_url']);
            $target['configured'] = $target['url'] !== '';
        }
        return $targets;
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('Library application integrations are currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }

        $atts = shortcode_atts([
            'title' => 'Connected Research Tools',
            'intro' => 'Prepare source-aware handoffs for Workbench, Decision Studio, and Site Intelligence without embedding duplicate applications inside the Library.',
            'open' => 'true',
        ], $atts, 'sc_library_integrations');

        $integration_title = sanitize_text_field($atts['title']);
        $integration_intro = sanitize_text_field($atts['intro']);
        $integration_open = filter_var($atts['open'], FILTER_VALIDATE_BOOLEAN);
        self::enqueue_assets();

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-integrations.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';

        register_rest_route($namespace, '/library/integrations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => self::HANDOFF_SCHEMA,
                'items' => array_values(self::public_targets()),
            ]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/integrations/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
            'args' => [
                'refresh' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false],
            ],
        ]);

        register_rest_route($namespace, '/library/integration-schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'schema' => self::HANDOFF_SCHEMA,
                'targets' => array_values(self::public_targets()),
                'required' => ['schema', 'id', 'target', 'created_at', 'source', 'context'],
                'context_types' => ['library_record', 'collection', 'note', 'source', 'translation_matrix', 'whiteboard', 'chalkboard', 'orchestration_packet'],
            ]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/items/(?P<id>\d+)/handoff', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'record_handoff'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['sanitize_callback' => 'absint'],
                'target' => ['sanitize_callback' => 'sanitize_key', 'required' => true],
            ],
        ]);
    }

    public function status(WP_REST_Request $request): WP_REST_Response {
        $refresh = rest_sanitize_boolean($request['refresh']);
        $items = [];
        foreach (self::targets() as $target) {
            $items[] = $this->target_status($target, $refresh);
        }
        return rest_ensure_response([
            'checked_at' => current_time('c'),
            'items' => $items,
        ]);
    }

    private function target_status(array $target, bool $refresh): array {
        $cache_key = 'sc_lib_int_' . md5((string) $target['id'] . '|' . (string) $target['health_url']);
        if (!$refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = [
            'id' => $target['id'],
            'label' => $target['label'],
            'configured' => $target['url'] !== '',
            'online' => null,
            'state' => $target['url'] !== '' ? 'configured' : 'not_configured',
            'http_status' => 0,
            'message' => $target['url'] !== ''
                ? __('Launch URL configured; no health endpoint supplied.', 'sustainable-catalyst-library')
                : __('Launch URL is not configured.', 'sustainable-catalyst-library'),
            'checked_at' => current_time('c'),
        ];

        if ($target['health_url'] !== '') {
            $response = wp_remote_get($target['health_url'], [
                'timeout' => 5,
                'redirection' => 2,
                'user-agent' => 'Sustainable-Catalyst-Library/' . SC_LIBRARY_VERSION,
            ]);
            if (is_wp_error($response)) {
                $result['online'] = false;
                $result['state'] = 'unavailable';
                $result['message'] = $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $result['http_status'] = $code;
                $result['online'] = $code >= 200 && $code < 400;
                $result['state'] = $result['online'] ? 'online' : 'unavailable';
                $result['message'] = $result['online']
                    ? __('Service health check succeeded.', 'sustainable-catalyst-library')
                    : sprintf(__('Health endpoint returned HTTP %d.', 'sustainable-catalyst-library'), $code);
            }
        }

        set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        return $result;
    }

    public function record_handoff(WP_REST_Request $request): WP_REST_Response {
        $post_id = absint($request['id']);
        $target_id = sanitize_key((string) $request['target']);
        $targets = self::targets();
        if (!isset($targets[$target_id])) {
            return new WP_REST_Response(['message' => __('Unknown integration target.', 'sustainable-catalyst-library')], 400);
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $this->indexer->configured_post_types(), true)) {
            return new WP_REST_Response(['message' => __('Library record not found.', 'sustainable-catalyst-library')], 404);
        }

        $payload = $this->record_payload($post, $target_id);
        $payload['launch_url'] = $this->launch_url($targets[$target_id], $payload);
        return rest_ensure_response($payload);
    }

    private function record_payload(WP_Post $post, string $target_id): array {
        $post_id = (int) $post->ID;
        $categories = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
        $concepts = wp_get_post_terms($post_id, SC_Library_Taxonomies::CONCEPT, ['fields' => 'names']);
        $series = wp_get_post_terms($post_id, SC_Library_Taxonomies::SERIES, ['fields' => 'names']);
        $relationships = array_map(static function (array $relationship): array {
            $related_id = $relationship['direction'] === 'incoming'
                ? (int) $relationship['source_post_id']
                : (int) $relationship['target_post_id'];
            return [
                'type' => (string) $relationship['type'],
                'label' => (string) $relationship['type_label'],
                'direction' => (string) $relationship['direction'],
                'note' => (string) $relationship['note'],
                'record_id' => $related_id,
                'title' => get_the_title($related_id),
                'url' => get_permalink($related_id),
            ];
        }, array_slice($this->relationships->get_for_post($post_id, true), 0, 12));

        $common = [
            'schema' => self::HANDOFF_SCHEMA,
            'id' => wp_generate_uuid4(),
            'target' => $target_id,
            'created_at' => current_time('c'),
            'source' => [
                'application' => 'sustainable-catalyst-library',
                'version' => SC_LIBRARY_VERSION,
                'site_url' => home_url('/'),
            ],
            'context' => [
                'type' => 'library_record',
                'record_id' => $post_id,
                'record_identifier' => sprintf('sc:library:%s:%d', sanitize_key($post->post_type), $post_id),
                'title' => get_the_title($post_id),
                'url' => get_permalink($post_id),
                'excerpt' => wp_strip_all_tags(get_the_excerpt($post_id)),
                'categories' => is_wp_error($categories) ? [] : array_values($categories),
                'concepts' => is_wp_error($concepts) ? [] : array_values($concepts),
                'series' => is_wp_error($series) ? [] : array_values($series),
                'evidence_status' => (string) get_post_meta($post_id, '_sc_library_evidence_status', true),
                'relationships' => $relationships,
                'resources' => [
                    'github_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_github_url', true)),
                    'datasets' => $this->lines((string) get_post_meta($post_id, '_sc_library_dataset_urls', true), true),
                    'videos' => $this->lines((string) get_post_meta($post_id, '_sc_library_video_urls', true), true),
                ],
            ],
            'target_context' => [],
        ];

        if ($target_id === 'workbench') {
            $common['target_context'] = [
                'task' => 'analyze_library_record',
                'tools' => $this->lines((string) get_post_meta($post_id, '_sc_library_workbench_tools', true), false),
                'technical_questions' => $this->lines((string) get_post_meta($post_id, '_sc_library_workbench_questions', true), false),
            ];
        } elseif ($target_id === 'decision_studio') {
            $common['target_context'] = [
                'task' => 'build_library_decision_canvas',
                'questions' => $this->lines((string) get_post_meta($post_id, '_sc_library_decision_questions', true), false),
                'decision_methods' => $this->lines((string) get_post_meta($post_id, '_sc_library_decision_methods', true), false),
                'canvas_sections' => ['research_question', 'claims', 'evidence', 'assumptions', 'uncertainties', 'tradeoffs', 'knowledge_gaps'],
            ];
        } elseif ($target_id === 'site_intelligence') {
            $common['target_context'] = [
                'task' => 'investigate_library_geographic_context',
                'places' => $this->lines((string) get_post_meta($post_id, '_sc_library_site_places', true), false),
                'indicators' => $this->lines((string) get_post_meta($post_id, '_sc_library_site_indicators', true), false),
                'source_ids' => $this->lines((string) get_post_meta($post_id, '_sc_library_site_sources', true), false),
            ];
        } else {
            $common['target_context'] = [
                'task' => 'prepare_library_lab_workflow',
                'research_questions' => $this->lines((string) get_post_meta($post_id, '_sc_library_workbench_questions', true), false),
                'methods' => $this->lines((string) get_post_meta($post_id, '_sc_library_decision_methods', true), false),
                'datasets' => $this->lines((string) get_post_meta($post_id, '_sc_library_dataset_urls', true), true),
                'requirements' => ['hypothesis_or_question', 'variables', 'method', 'measurements', 'validation', 'provenance', 'responsible_use'],
            ];
        }

        return $common;
    }

    private function launch_url(array $target, array $payload): string {
        $context_url = add_query_arg('target', (string) $payload['target'], rest_url(sprintf('sustainable-catalyst/v1/library/items/%d/handoff', (int) $payload['context']['record_id'])));
        return add_query_arg([
            'library_record' => (int) $payload['context']['record_id'],
            'library_identifier' => (string) $payload['context']['record_identifier'],
            'library_context_url' => esc_url_raw($context_url),
            'library_handoff_schema' => self::HANDOFF_SCHEMA,
            'library_source' => 'research-library',
        ], (string) $target['url']);
    }

    private function lines(string $raw, bool $urls): array {
        $values = array_values(array_filter(array_map('trim', preg_split('/\R|,/', $raw) ?: [])));
        return array_values(array_filter(array_map($urls ? 'esc_url_raw' : 'sanitize_text_field', $values)));
    }
}
