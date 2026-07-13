<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public API, scoped service keys, signed webhooks, and developer documentation.
 *
 * Public routes expose only records that are already public in the Library.
 * Protected routes require a scoped key. Keys are shown once and stored only as
 * keyed hashes; webhook signing secrets are encrypted at rest because the
 * plaintext is required to sign outbound deliveries.
 */
final class SC_Library_Developer_API {
    public const API_NAMESPACE = 'sustainable-catalyst-library/v1';
    public const SCHEMA = 'sc-library-developer-api/1.0';
    public const WEBHOOK_SCHEMA = 'sc-library-webhook-event/1.0';
    public const OPENAPI_VERSION = '3.1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;
    private SC_Library_Knowledge_Graph $graph;
    private SC_Library_Planner $planner;

    public function __construct(
        SC_Library_Indexer $indexer,
        SC_Library_Relationships $relationships,
        SC_Library_Knowledge_Graph $graph,
        SC_Library_Planner $planner
    ) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
        $this->graph = $graph;
        $this->planner = $planner;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 50);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_pre_serve_request', [$this, 'cors_headers'], 10, 4);
        add_shortcode('sc_library_developer_portal', [$this, 'render_portal_shortcode']);

        add_action('admin_post_sc_library_api_key_create', [$this, 'handle_create_key']);
        add_action('admin_post_sc_library_api_key_revoke', [$this, 'handle_revoke_key']);
        add_action('admin_post_sc_library_webhook_create', [$this, 'handle_create_webhook']);
        add_action('admin_post_sc_library_webhook_toggle', [$this, 'handle_toggle_webhook']);
        add_action('admin_post_sc_library_webhook_delete', [$this, 'handle_delete_webhook']);
        add_action('admin_post_sc_library_webhook_test', [$this, 'handle_test_webhook']);
        add_action('admin_post_sc_library_webhook_redeliver', [$this, 'handle_redeliver']);

        add_action('sc_library_deliver_webhook', [$this, 'deliver_webhook'], 10, 1);
        add_action('transition_post_status', [$this, 'capture_status_transition'], 40, 3);
        add_action('post_updated', [$this, 'capture_post_updated'], 40, 3);
        add_action('sc_library_knowledge_graph_rebuilt', [$this, 'capture_graph_rebuilt'], 10, 2);
        add_action('sc_library_workspace_revised', [$this, 'capture_workspace_revised'], 10, 1);
        add_action('sc_library_review_transitioned', [$this, 'capture_review_transitioned'], 10, 1);
        add_action('sc_library_document_rendered', [$this, 'capture_document_rendered'], 10, 1);
        add_action('sc_library_media_clip_completed', [$this, 'capture_media_completed'], 10, 1);
        add_action('sc_library_foundation_document_extracted', [$this, 'capture_foundation_document_extracted'], 10, 2);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_developer_api', 1);
    }

    public static function keys_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_api_keys';
    }

    public static function webhooks_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_webhooks';
    }

    public static function deliveries_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_webhook_deliveries';
    }

    public static function scopes(): array {
        return [
            'records:read' => __('Read public Library records', 'sustainable-catalyst-library'),
            'graph:read' => __('Read public Knowledge Graph data', 'sustainable-catalyst-library'),
            'roadmap:read' => __('Read public roadmap and registry data', 'sustainable-catalyst-library'),
            'media:read' => __('Read public multimedia metadata', 'sustainable-catalyst-library'),
            'exports:read' => __('Read export manifests and schemas', 'sustainable-catalyst-library'),
            'orchestrator:query' => __('Submit site-scoped orchestration queries', 'sustainable-catalyst-library'),
            'index:write' => __('Start a Library index rebuild', 'sustainable-catalyst-library'),
            'webhooks:write' => __('Send test webhook events', 'sustainable-catalyst-library'),
        ];
    }

    public static function event_types(): array {
        return [
            'record.published' => __('Record published', 'sustainable-catalyst-library'),
            'record.updated' => __('Published record updated', 'sustainable-catalyst-library'),
            'record.archived' => __('Record unpublished, archived, or trashed', 'sustainable-catalyst-library'),
            'plan.created' => __('Content plan created', 'sustainable-catalyst-library'),
            'plan.transitioned' => __('Content plan state changed', 'sustainable-catalyst-library'),
            'documentation.updated' => __('Documentation updated', 'sustainable-catalyst-library'),
            'graph.rebuilt' => __('Knowledge Graph rebuilt', 'sustainable-catalyst-library'),
            'workspace.revised' => __('Persistent workspace revised', 'sustainable-catalyst-library'),
            'review.transitioned' => __('Editorial review state changed', 'sustainable-catalyst-library'),
            'review.approved' => __('Editorial review approved', 'sustainable-catalyst-library'),
            'book.rendered' => __('Server document rendered', 'sustainable-catalyst-library'),
            'media.clip.completed' => __('Media clip processing completed', 'sustainable-catalyst-library'),
            'foundation-document.extracted' => __('Foundation Document PDF extracted and indexed', 'sustainable-catalyst-library'),
            'api.test' => __('Developer webhook test', 'sustainable-catalyst-library'),
        ];
    }

    public function register_settings(): void {
        register_setting('sc_library_developer_api_settings', 'sc_library_enable_developer_api', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_api_public_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(5000, max(30, absint($value))),
            'default' => 300,
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_api_key_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(10000, max(60, absint($value))),
            'default' => 1000,
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_api_allowed_origins', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_origins'],
            'default' => '',
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_developer_portal_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/developers/'),
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_webhook_max_attempts', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(8, max(1, absint($value))),
            'default' => 4,
        ]);
        register_setting('sc_library_developer_api_settings', 'sc_library_webhook_timeout', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(30, max(3, absint($value))),
            'default' => 10,
        ]);
    }

    public function sanitize_origins($value): string {
        $origins = [];
        foreach (preg_split('/[\r\n,]+/', (string) $value) ?: [] as $origin) {
            $origin = untrailingslashit(esc_url_raw(trim($origin)));
            if ($origin !== '' && wp_http_validate_url($origin)) {
                $origins[] = $origin;
            }
        }
        return implode("\n", array_values(array_unique($origins)));
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Public API and Webhooks', 'sustainable-catalyst-library'),
            __('Developer API', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-developer-api',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'sc-library_page_sc-library-developer-api') {
            return;
        }
        wp_enqueue_style('sc-library-developer-api', SC_LIBRARY_URL . 'assets/css/sc-library-developer-api.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-developer-api', SC_LIBRARY_URL . 'assets/js/sc-library-developer-api.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-developer-api', 'SCLibraryDeveloperAdmin', [
            'copy' => __('Copy', 'sustainable-catalyst-library'),
            'copied' => __('Copied', 'sustainable-catalyst-library'),
        ]);
    }

    public function register_routes(): void {
        $ns = self::API_NAMESPACE;
        register_rest_route($ns, '/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_status'],
            'permission_callback' => [$this, 'public_permission'],
        ]);
        register_rest_route($ns, '/records', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_records'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => $this->record_collection_args(),
        ]);
        register_rest_route($ns, '/records/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_record'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => ['id' => ['sanitize_callback' => 'absint', 'validate_callback' => static fn($value) => absint($value) > 0]],
        ]);
        register_rest_route($ns, '/relationships', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_relationships'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'record_id' => ['sanitize_callback' => 'absint', 'default' => 0],
                'type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route($ns, '/graph', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_graph'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'root' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'depth' => ['sanitize_callback' => 'absint', 'default' => 2],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 250],
            ],
        ]);
        register_rest_route($ns, '/roadmap', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_roadmap'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'collection' => ['sanitize_callback' => 'sanitize_title', 'default' => ''],
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route($ns, '/media/reels', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_media_reels'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 25],
            ],
        ]);
        register_rest_route($ns, '/media/reels/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_media_reel'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => ['uuid' => ['sanitize_callback' => 'sanitize_text_field']],
        ]);
        register_rest_route($ns, '/foundation-documents', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_foundation_documents'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 20],
            ],
        ]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_foundation_document'],
            'permission_callback' => [$this, 'public_permission'],
        ]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/pages', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_foundation_pages'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => [
                'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'pdf_page' => ['sanitize_callback' => 'absint', 'default' => 0],
                'page' => ['sanitize_callback' => 'absint', 'default' => 1],
                'per_page' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/citation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_foundation_citation'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => ['format' => ['sanitize_callback' => 'sanitize_key', 'default' => 'csl-json']],
        ]);
        register_rest_route($ns, '/openapi.json', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_openapi'],
            'permission_callback' => [$this, 'public_permission'],
        ]);
        register_rest_route($ns, '/schemas', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_schema_index'],
            'permission_callback' => [$this, 'public_permission'],
        ]);
        register_rest_route($ns, '/schemas/(?P<name>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_schema'],
            'permission_callback' => [$this, 'public_permission'],
            'args' => ['name' => ['sanitize_callback' => 'sanitize_key']],
        ]);
        register_rest_route($ns, '/protected/export-manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_export_manifest'],
            'permission_callback' => fn(WP_REST_Request $request) => $this->key_permission($request, 'exports:read'),
        ]);
        register_rest_route($ns, '/protected/reindex', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_reindex'],
            'permission_callback' => fn(WP_REST_Request $request) => $this->key_permission($request, 'index:write'),
        ]);
        register_rest_route($ns, '/protected/webhooks/test', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_webhook_test'],
            'permission_callback' => fn(WP_REST_Request $request) => $this->key_permission($request, 'webhooks:write'),
        ]);
    }

    private function record_collection_args(): array {
        return [
            'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'post_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'category' => ['sanitize_callback' => 'absint', 'default' => 0],
            'concept' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'series' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'modified_after' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'sort' => ['sanitize_callback' => 'sanitize_key', 'default' => 'modified_desc'],
            'page' => ['sanitize_callback' => 'absint', 'default' => 1],
            'per_page' => ['sanitize_callback' => 'absint', 'default' => 20],
        ];
    }

    public function public_permission(WP_REST_Request $request): bool|WP_Error {
        if (!self::enabled()) {
            return new WP_Error('sc_library_api_disabled', __('The Sustainable Catalyst Library public API is disabled.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        return $this->rate_limit($request, null);
    }

    private function key_permission(WP_REST_Request $request, string $scope): bool|WP_Error {
        if (!self::enabled()) {
            return new WP_Error('sc_library_api_disabled', __('The Sustainable Catalyst Library API is disabled.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $key = $this->extract_key($request);
        if ($key === '') {
            return new WP_Error('sc_library_api_key_required', __('A scoped Sustainable Catalyst Library API key is required.', 'sustainable-catalyst-library'), ['status' => 401]);
        }
        $record = $this->authenticate_key($key);
        if (is_wp_error($record)) {
            return $record;
        }
        $scopes = json_decode((string) ($record['scopes_json'] ?? '[]'), true) ?: [];
        if (!in_array($scope, $scopes, true)) {
            return new WP_Error('sc_library_api_scope_denied', sprintf(__('The API key does not include the required scope: %s', 'sustainable-catalyst-library'), $scope), ['status' => 403]);
        }
        return $this->rate_limit($request, $record);
    }

    private function extract_key(WP_REST_Request $request): string {
        $header = trim((string) $request->get_header('x-sc-library-key'));
        if ($header !== '') {
            return $header;
        }
        $authorization = trim((string) $request->get_header('authorization'));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }
        return '';
    }

    private function authenticate_key(string $key): array|WP_Error {
        global $wpdb;
        if (!preg_match('/^scl_(live|test)_([a-f0-9]{12})_([A-Za-z0-9_-]{24,})$/', $key, $matches)) {
            return new WP_Error('sc_library_api_key_invalid', __('The API key format is invalid.', 'sustainable-catalyst-library'), ['status' => 401]);
        }
        $prefix = $matches[2];
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::keys_table() . ' WHERE key_prefix = %s AND status = %s LIMIT 1',
            $prefix,
            'active'
        ), ARRAY_A);
        if (!$row) {
            return new WP_Error('sc_library_api_key_invalid', __('The API key is invalid or revoked.', 'sustainable-catalyst-library'), ['status' => 401]);
        }
        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            return new WP_Error('sc_library_api_key_expired', __('The API key has expired.', 'sustainable-catalyst-library'), ['status' => 401]);
        }
        $hash = hash_hmac('sha256', $key, wp_salt('auth'));
        if (!hash_equals((string) $row['secret_hash'], $hash)) {
            return new WP_Error('sc_library_api_key_invalid', __('The API key is invalid or revoked.', 'sustainable-catalyst-library'), ['status' => 401]);
        }
        $wpdb->update(self::keys_table(), ['last_used_at' => current_time('mysql', true)], ['id' => (int) $row['id']], ['%s'], ['%d']);
        return $row;
    }

    private function rate_limit(WP_REST_Request $request, ?array $key): bool|WP_Error {
        $hour = gmdate('YmdH');
        if ($key) {
            $identity = 'key:' . (string) $key['key_uuid'];
            $limit = max(1, (int) ($key['rate_limit_per_hour'] ?? get_option('sc_library_api_key_rate_limit', 1000)));
        } else {
            $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $identity = 'public:' . hash('sha256', $ip);
            $limit = max(1, (int) get_option('sc_library_api_public_rate_limit', 300));
        }
        $transient = 'sc_lib_api_' . substr(hash('sha256', $identity . ':' . $hour), 0, 32);
        $count = (int) get_transient($transient);
        if ($count >= $limit) {
            return new WP_Error('sc_library_api_rate_limited', __('The API rate limit has been reached. Try again after the current hour.', 'sustainable-catalyst-library'), ['status' => 429, 'limit' => $limit]);
        }
        set_transient($transient, $count + 1, HOUR_IN_SECONDS + 60);
        return true;
    }

    public function rest_status(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'ok' => true,
            'service' => 'Sustainable Catalyst Library API',
            'api_version' => 'v1',
            'plugin_version' => SC_LIBRARY_VERSION,
            'schema' => self::SCHEMA,
            'indexed_records' => $this->indexer->count_indexed(),
            'relationships' => $this->relationships->count(),
            'openapi' => rest_url(self::API_NAMESPACE . '/openapi.json'),
            'schemas' => rest_url(self::API_NAMESPACE . '/schemas'),
            'documentation' => esc_url_raw((string) get_option('sc_library_developer_portal_url', home_url('/developers/'))),
        ]);
    }

    public function rest_records(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $this->indexer->table_name();
        $where = ["status = 'publish'"];
        $params = [];
        $search = trim((string) $request['search']);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(title LIKE %s OR excerpt LIKE %s OR searchable_text LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        if ((string) $request['post_type'] !== '') {
            $where[] = 'post_type = %s';
            $params[] = (string) $request['post_type'];
        }
        if (absint($request['category']) > 0) {
            $category_id = absint($request['category']);
            $where[] = "(primary_category_id = %d OR FIND_IN_SET(%d, REPLACE(REPLACE(REPLACE(category_ids, '[', ''), ']', ''), ' ', '')) > 0)";
            $params[] = $category_id;
            $params[] = $category_id;
        }
        foreach (['concept' => 'concept_ids', 'series' => 'series_term_id'] as $arg => $column) {
            if ((string) $request[$arg] === '') {
                continue;
            }
            if ($arg === 'series' && ctype_digit((string) $request[$arg])) {
                $where[] = 'series_term_id = %d';
                $params[] = absint($request[$arg]);
            } else {
                $term = get_term_by('slug', sanitize_title((string) $request[$arg]), $arg === 'concept' ? SC_Library_Taxonomies::CONCEPT : SC_Library_Taxonomies::SERIES);
                if ($term) {
                    if ($arg === 'series') {
                        $where[] = 'series_term_id = %d';
                        $params[] = (int) $term->term_id;
                    } else {
                        $where[] = "FIND_IN_SET(%d, REPLACE(REPLACE(REPLACE(concept_ids, '[', ''), ']', ''), ' ', '')) > 0";
                        $params[] = (int) $term->term_id;
                    }
                }
            }
        }
        if ((string) $request['modified_after'] !== '') {
            $timestamp = strtotime((string) $request['modified_after']);
            if ($timestamp) {
                $where[] = 'modified_at > %s';
                $params[] = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }
        $sort_map = [
            'title_asc' => 'title ASC',
            'published_desc' => 'published_at DESC',
            'published_asc' => 'published_at ASC',
            'modified_asc' => 'modified_at ASC',
            'modified_desc' => 'modified_at DESC',
        ];
        $order = $sort_map[(string) $request['sort']] ?? 'modified_at DESC';
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $offset = ($page - 1) * $per_page;
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order} LIMIT %d OFFSET %d";
        $count_params = $params;
        $data_params = array_merge($params, [$per_page, $offset]);
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $count_params)) : $wpdb->get_var($count_sql));
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A) ?: [];
        $items = array_values(array_filter(array_map([$this, 'serialize_record_row'], $rows)));
        $response = rest_ensure_response([
            'schema' => 'sc-library-record-collection/1.0',
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) ceil($total / $per_page));
        return $response;
    }

    public function rest_record(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->indexer->table_name() . " WHERE post_id = %d AND status = 'publish' LIMIT 1",
            absint($request['id'])
        ), ARRAY_A);
        $record = $row ? $this->serialize_record_row($row, true) : null;
        if (!$record) {
            return new WP_Error('sc_library_record_not_found', __('The public Library record was not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        return rest_ensure_response($record);
    }

    private function serialize_record_row(array $row, bool $include_content = false): ?array {
        $post_id = absint($row['post_id'] ?? 0);
        $post = $post_id ? get_post($post_id) : null;
        if (!$post || $post->post_status !== 'publish') {
            return null;
        }
        $terms = static function (string $taxonomy) use ($post_id): array {
            $values = get_the_terms($post_id, $taxonomy);
            if (!$values || is_wp_error($values)) {
                return [];
            }
            return array_map(static fn(WP_Term $term): array => ['id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name], $values);
        };
        $record = [
            'schema' => 'sc-library-record/1.0',
            'id' => $post_id,
            'identifier' => (string) ($row['record_identifier'] ?? ''),
            'type' => (string) ($row['post_type'] ?? $post->post_type),
            'title' => html_entity_decode(wp_strip_all_tags((string) $row['title']), ENT_QUOTES, get_bloginfo('charset')),
            'excerpt' => wp_strip_all_tags((string) ($row['excerpt'] ?? '')),
            'url' => esc_url_raw((string) ($row['permalink'] ?? get_permalink($post_id))),
            'published_at' => $this->iso_date((string) ($row['published_at'] ?? '')),
            'modified_at' => $this->iso_date((string) ($row['modified_at'] ?? '')),
            'categories' => $terms('category'),
            'tags' => $terms('post_tag'),
            'concepts' => $terms(SC_Library_Taxonomies::CONCEPT),
            'series' => $terms(SC_Library_Taxonomies::SERIES),
            'collections' => $terms(SC_Library_Taxonomies::COLLECTION),
            'resources' => json_decode((string) ($row['resource_flags'] ?? '[]'), true) ?: [],
        ];
        if (class_exists('SC_Library_Foundation_Documents') && $post->post_type === SC_Library_Foundation_Documents::POST_TYPE) {
            $record['foundation_document'] = SC_Library_Foundation_Documents::public_payload($post, $include_content);
        }
        if ($include_content) {
            $record['content_html'] = apply_filters('the_content', $post->post_content);
            $record['content_text'] = wp_strip_all_tags(strip_shortcodes($post->post_content));
        }
        return $record;
    }

    private function iso_date(string $mysql): ?string {
        if ($mysql === '' || $mysql === '0000-00-00 00:00:00') {
            return null;
        }
        $timestamp = strtotime($mysql . ' UTC');
        return $timestamp ? gmdate('c', $timestamp) : null;
    }

    public function rest_relationships(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'sc_library_relationships';
        $where = ["visibility = 'public'"];
        $params = [];
        $record_id = absint($request['record_id']);
        if ($record_id) {
            $where[] = '(source_post_id = %d OR target_post_id = %d)';
            $params[] = $record_id;
            $params[] = $record_id;
        }
        if ((string) $request['type'] !== '') {
            $where[] = 'relationship_type = %s';
            $params[] = (string) $request['type'];
        }
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        $items = [];
        foreach ($rows as $row) {
            if (get_post_status((int) $row['source_post_id']) !== 'publish' || get_post_status((int) $row['target_post_id']) !== 'publish') {
                continue;
            }
            $items[] = [
                'schema' => 'sc-library-relationship/1.0',
                'id' => (int) $row['id'],
                'source_record_id' => (int) $row['source_post_id'],
                'target_record_id' => (int) $row['target_post_id'],
                'type' => (string) $row['relationship_type'],
                'note' => (string) $row['note'],
                'confidence' => (float) $row['confidence'],
                'confidence_basis' => (string) $row['confidence_basis'],
                'provenance_type' => (string) $row['provenance_type'],
                'provenance_url' => esc_url_raw((string) $row['provenance_url']),
                'evidence_note' => (string) $row['evidence_note'],
                'updated_at' => $this->iso_date((string) $row['updated_at']),
            ];
        }
        return rest_ensure_response(['schema' => 'sc-library-relationship-collection/1.0', 'items' => $items, 'page' => $page, 'per_page' => $per_page]);
    }

    public function rest_graph(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->graph->graph_payload([
            'root' => sanitize_text_field((string) $request['root']),
            'depth' => min(4, max(1, absint($request['depth']))),
            'limit' => min(500, max(1, absint($request['limit']))),
        ], false);
        return rest_ensure_response($payload);
    }

    public function rest_roadmap(WP_REST_Request $request): WP_REST_Response {
        $collection = sanitize_title((string) $request['collection']);
        $records = $this->planner->all_public_registry_records();
        if ($collection !== '') {
            $records = array_values(array_filter($records, static fn(array $record): bool => in_array($collection, (array) ($record['collections'] ?? []), true)));
        }
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $total = count($records);
        $response = rest_ensure_response([
            'schema' => 'sc-library-public-roadmap/1.0',
            'tracker' => $this->planner->tracker_data(true, $collection),
            'records' => array_slice($records, ($page - 1) * $per_page, $per_page),
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total ? (int) ceil($total / $per_page) : 0,
            ],
        ]);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) ($total ? ceil($total / $per_page) : 0));
        return $response;
    }

    public function rest_media_reels(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) {
            return rest_ensure_response(['schema' => 'sc-library-media-reel-collection/1.0', 'items' => [], 'pagination' => ['page' => 1, 'per_page' => 0, 'total' => 0, 'total_pages' => 0]]);
        }
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $offset = ($page - 1) * $per_page;
        $table = SC_Library_Multimedia::reels_table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE visibility = 'public'");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE visibility = 'public' ORDER BY updated_at DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A) ?: [];
        $response = rest_ensure_response([
            'schema' => 'sc-library-media-reel-collection/1.0',
            'items' => array_map([$this, 'serialize_public_media_reel'], $rows),
            'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $total ? (int) ceil($total / $per_page) : 0],
        ]);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) ($total ? ceil($total / $per_page) : 0));
        return $response;
    }

    public function rest_media_reel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) {
            return new WP_Error('sc_library_media_unavailable', __('Multimedia Studio is unavailable.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $uuid = sanitize_text_field((string) $request['uuid']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . SC_Library_Multimedia::reels_table() . " WHERE reel_uuid = %s AND visibility = 'public' LIMIT 1", $uuid), ARRAY_A);
        if (!$row) {
            return new WP_Error('sc_library_media_reel_not_found', __('The public evidence reel was not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $item = $this->serialize_public_media_reel($row);
        $clip_uuids = array_values(array_filter(array_map('sanitize_text_field', json_decode((string) ($row['clip_uuids_json'] ?? '[]'), true) ?: [])));
        $clips = [];
        foreach ($clip_uuids as $clip_uuid) {
            $clip = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . SC_Library_Multimedia::clips_table() . " WHERE clip_uuid = %s AND visibility = 'public' LIMIT 1", $clip_uuid), ARRAY_A);
            if ($clip) {
                $clips[] = $this->serialize_public_media_clip($clip);
            }
        }
        $item['clips'] = $clips;
        return rest_ensure_response($item);
    }

    public function serialize_public_media_reel(array $row): array {
        $uuid = (string) ($row['reel_uuid'] ?? '');
        return [
            'schema' => class_exists('SC_Library_Multimedia') ? SC_Library_Multimedia::REEL_SCHEMA : 'sc-library-media-reel/1.0',
            'reel_uuid' => $uuid,
            'title' => sanitize_text_field((string) ($row['title'] ?? '')),
            'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
            'clip_uuids' => array_values(array_filter(array_map('sanitize_text_field', json_decode((string) ($row['clip_uuids_json'] ?? '[]'), true) ?: []))),
            'edition_mode' => sanitize_key((string) ($row['edition_mode'] ?? 'linked')),
            'shortcode' => $uuid !== '' ? '[sc_library_evidence_reel id="' . $uuid . '"]' : '',
            'created_at' => $this->iso_date((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->iso_date((string) ($row['updated_at'] ?? '')),
        ];
    }

    private function serialize_public_media_clip(array $row): array {
        $output_id = absint($row['output_attachment_id'] ?? 0);
        $poster_id = absint($row['poster_attachment_id'] ?? 0);
        return [
            'schema' => class_exists('SC_Library_Multimedia') ? SC_Library_Multimedia::CLIP_SCHEMA : 'sc-library-media-clip/1.0',
            'clip_uuid' => (string) ($row['clip_uuid'] ?? ''),
            'asset_uuid' => (string) ($row['asset_uuid'] ?? ''),
            'title' => sanitize_text_field((string) ($row['title'] ?? '')),
            'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
            'start_ms' => absint($row['start_ms'] ?? 0),
            'end_ms' => absint($row['end_ms'] ?? 0),
            'poster_time_ms' => absint($row['poster_time_ms'] ?? 0),
            'transcript_excerpt' => sanitize_textarea_field((string) ($row['transcript_excerpt'] ?? '')),
            'caption_text' => sanitize_textarea_field((string) ($row['caption_text'] ?? '')),
            'status' => sanitize_key((string) ($row['status'] ?? 'draft')),
            'output_url' => $output_id ? esc_url_raw((string) wp_get_attachment_url($output_id)) : '',
            'poster_url' => $poster_id ? esc_url_raw((string) wp_get_attachment_url($poster_id)) : '',
            'created_at' => $this->iso_date((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->iso_date((string) ($row['updated_at'] ?? '')),
        ];
    }

    public function rest_foundation_documents(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('SC_Library_Foundation_Documents')) return rest_ensure_response(['schema' => 'sc-library-foundation-document-collection/1.0', 'items' => [], 'pagination' => ['page' => 1, 'per_page' => 0, 'total' => 0, 'total_pages' => 0]]);
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $search = sanitize_text_field((string) $request['search']);
        $args = ['post_type' => SC_Library_Foundation_Documents::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page];
        if ($search !== '') $args['post__in'] = SC_Library_Foundation_Documents::matching_ids($search, 10000) ?: [0];
        $query = new WP_Query($args);
        $response = rest_ensure_response(['schema' => 'sc-library-foundation-document-collection/1.0', 'items' => array_map([SC_Library_Foundation_Documents::class, 'public_payload'], $query->posts), 'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages]]);
        $response->header('X-WP-Total', (string) $query->found_posts);
        $response->header('X-WP-TotalPages', (string) $query->max_num_pages);
        return $response;
    }

    public function rest_foundation_document(WP_REST_Request $request): WP_REST_Response|WP_Error {
        if (!class_exists('SC_Library_Foundation_Documents')) return new WP_Error('sc_library_foundation_unavailable', __('Foundation Documents are unavailable.', 'sustainable-catalyst-library'), ['status' => 404]);
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== SC_Library_Foundation_Documents::POST_TYPE || $post->post_status !== 'publish') return new WP_Error('sc_library_foundation_not_found', __('Foundation Document not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        return rest_ensure_response(SC_Library_Foundation_Documents::public_payload($post, true));
    }

    public function rest_foundation_pages(WP_REST_Request $request): WP_REST_Response|WP_Error {
        if (!class_exists('SC_Library_Foundation_Documents')) return new WP_Error('sc_library_foundation_unavailable', __('Foundation Documents are unavailable.', 'sustainable-catalyst-library'), ['status' => 404]);
        $id = absint($request['id']);
        $post = get_post($id);
        if (!$post || $post->post_type !== SC_Library_Foundation_Documents::POST_TYPE || $post->post_status !== 'publish') return new WP_Error('sc_library_foundation_not_found', __('Foundation Document not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        global $wpdb;
        $search = trim(sanitize_text_field((string) $request['search']));
        $pdf_page = absint($request['pdf_page']);
        $page = max(1, absint($request['page'] ?: 1));
        $per_page = min(100, max(1, absint($request['per_page'] ?: 50)));
        $where = 'post_id = %d'; $values = [$id];
        if ($search !== '') { $where .= ' AND page_text LIKE %s'; $values[] = '%' . $wpdb->esc_like($search) . '%'; }
        if ($pdf_page > 0) { $where .= ' AND page_number = %d'; $values[] = $pdf_page; }
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . SC_Library_Foundation_Documents::pages_table() . " WHERE {$where}", ...$values));
        $query_values = array_merge($values, [$per_page, ($page - 1) * $per_page]);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT page_number, page_text, character_count, content_hash, extracted_at FROM ' . SC_Library_Foundation_Documents::pages_table() . " WHERE {$where} ORDER BY page_number ASC LIMIT %d OFFSET %d", ...$query_values), ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => SC_Library_Foundation_Documents::EXTRACTION_SCHEMA, 'document_id' => $id, 'items' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => (int) ceil($total / $per_page)]]);
    }

    public function rest_foundation_citation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        if (!class_exists('SC_Library_Foundation_Documents')) return new WP_Error('sc_library_foundation_unavailable', __('Foundation Documents are unavailable.', 'sustainable-catalyst-library'), ['status' => 404]);
        $id = absint($request['id']);
        $post = get_post($id);
        if (!$post || $post->post_type !== SC_Library_Foundation_Documents::POST_TYPE || $post->post_status !== 'publish') return new WP_Error('sc_library_foundation_not_found', __('Foundation Document not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        $format = sanitize_key((string) $request['format']);
        if ($format === 'plain') $format = 'text';
        if ($format === 'csl') $format = 'csl-json';
        $citation = SC_Library_Foundation_Documents::citation($id, $format);
        return rest_ensure_response(['schema' => 'sc-library-citation-export/1.0', 'format' => $format, 'filename' => $citation['filename'], 'content_type' => $citation['content_type'], 'content' => $citation['body']]);
    }

    public function rest_openapi(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response($this->openapi_document());
    }

    public function rest_schema_index(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'items' => array_map(static fn(string $name): array => [
                'name' => $name,
                'url' => rest_url(self::API_NAMESPACE . '/schemas/' . $name),
            ], array_keys($this->schemas())),
        ]);
    }

    public function rest_schema(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $schemas = $this->schemas();
        $name = sanitize_key((string) $request['name']);
        if (!isset($schemas[$name])) {
            return new WP_Error('sc_library_schema_not_found', __('The requested schema was not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        return rest_ensure_response($schemas[$name]);
    }

    public function rest_export_manifest(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'schema' => class_exists('SC_Library_Portability') ? SC_Library_Portability::EXPORT_SCHEMA : 'sc-library-portable-export/1.9',
            'generated_at' => gmdate('c'),
            'site' => home_url('/'),
            'plugin_version' => SC_LIBRARY_VERSION,
            'counts' => [
                'records' => $this->indexer->count_indexed(),
                'relationships' => $this->relationships->count(),
                'graph_nodes' => $this->table_count($this->graph_table('nodes')),
                'graph_edges' => $this->table_count($this->graph_table('edges')),
            ],
            'secrets_included' => false,
        ]);
    }

    private function graph_table(string $kind): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_graph_' . ($kind === 'edges' ? 'edges' : 'nodes');
    }

    private function table_count(string $table): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public function rest_reindex(WP_REST_Request $request): WP_REST_Response {
        $result = $this->indexer->rebuild_all();
        return rest_ensure_response(['ok' => true, 'result' => $result]);
    }

    public function rest_webhook_test(WP_REST_Request $request): WP_REST_Response {
        $event = $this->emit_event('api.test', [
            'message' => sanitize_text_field((string) ($request->get_param('message') ?: 'Developer API test event')),
            'requested_via' => 'protected-api',
        ]);
        return rest_ensure_response(['ok' => true, 'event' => $event]);
    }

    public function cors_headers($served, $result, WP_REST_Request $request, $server) {
        if (strpos($request->get_route(), '/' . self::API_NAMESPACE . '/') !== 0) {
            return $served;
        }
        $origin = untrailingslashit(esc_url_raw((string) get_http_origin()));
        $allowed = array_filter(array_map('trim', preg_split('/\R/', (string) get_option('sc_library_api_allowed_origins', '')) ?: []));
        if ($origin && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-SC-Library-Key');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        return $served;
    }

    public function openapi_document(): array {
        $base = rest_url(self::API_NAMESPACE);
        $paths = [];
        foreach ([
            '/status' => 'API service status',
            '/records' => 'Search and browse public Library records',
            '/records/{id}' => 'Read one public Library record',
            '/relationships' => 'Browse public record relationships',
            '/graph' => 'Read a public Knowledge Graph neighborhood',
            '/roadmap' => 'Read the public registry and roadmap',
            '/media/reels' => 'Browse public multimedia evidence reels',
            '/media/reels/{uuid}' => 'Read one public multimedia evidence reel',
            '/foundation-documents' => 'Browse embedded Foundation Document records',
            '/foundation-documents/{id}' => 'Read one embedded Foundation Document record',
            '/foundation-documents/{id}/pages' => 'Read page-aware extracted PDF text',
            '/foundation-documents/{id}/citation' => 'Export a Foundation Document citation',
            '/schemas' => 'List published JSON Schemas',
            '/schemas/{name}' => 'Read one JSON Schema',
            '/openapi.json' => 'Read this OpenAPI document',
        ] as $path => $summary) {
            $paths[$path] = ['get' => [
                'summary' => $summary,
                'operationId' => 'get' . str_replace(' ', '', ucwords(str_replace(['/', '{', '}', '-'], ' ', $path))),
                'responses' => ['200' => ['description' => 'Successful response']],
            ]];
        }
        $paths['/protected/export-manifest'] = ['get' => [
            'summary' => 'Read the protected portable-export manifest',
            'security' => [['LibraryApiKey' => ['exports:read']]],
            'responses' => ['200' => ['description' => 'Export manifest'], '401' => ['description' => 'Authentication required'], '403' => ['description' => 'Scope denied']],
        ]];
        $paths['/protected/reindex'] = ['post' => [
            'summary' => 'Run the synchronous Library reindex fallback',
            'security' => [['LibraryApiKey' => ['index:write']]],
            'responses' => ['200' => ['description' => 'Reindex result'], '401' => ['description' => 'Authentication required'], '403' => ['description' => 'Scope denied']],
        ]];
        $paths['/protected/webhooks/test'] = ['post' => [
            'summary' => 'Emit a signed test webhook event',
            'security' => [['LibraryApiKey' => ['webhooks:write']]],
            'responses' => ['200' => ['description' => 'Test event created']],
        ]];
        return [
            'openapi' => self::OPENAPI_VERSION,
            'info' => [
                'title' => 'Sustainable Catalyst Library API',
                'version' => '1.1.0',
                'description' => 'Versioned public access to Sustainable Catalyst Library records, embedded Foundation Documents, page-aware PDF text, relationships, graph data, roadmap data, schemas, and explicitly scoped service operations.',
            ],
            'servers' => [['url' => untrailingslashit($base)]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'LibraryApiKey' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-SC-Library-Key',
                        'description' => 'Scoped key generated by a Library administrator. The plaintext key is shown once.',
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
            'externalDocs' => [
                'description' => 'Sustainable Catalyst Library Developer Portal',
                'url' => esc_url_raw((string) get_option('sc_library_developer_portal_url', home_url('/developers/'))),
            ],
        ];
    }

    public function schemas(): array {
        return [
            'record' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => rest_url(self::API_NAMESPACE . '/schemas/record'),
                'title' => 'Sustainable Catalyst Library record',
                'type' => 'object',
                'required' => ['schema', 'id', 'type', 'title', 'url'],
                'properties' => [
                    'schema' => ['const' => 'sc-library-record/1.0'],
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'identifier' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'excerpt' => ['type' => 'string'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'modified_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                ],
                'additionalProperties' => true,
            ],
            'foundation-document' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => rest_url(self::API_NAMESPACE . '/schemas/foundation-document'),
                'title' => 'Sustainable Catalyst Foundation Document',
                'type' => 'object',
                'required' => ['schema','id','title','url','pdf_url','metadata','extraction'],
                'properties' => [
                    'schema' => ['const' => 'sc-library-foundation-document/1.0'],
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'pdf_url' => ['type' => 'string'],
                    'metadata' => ['type' => 'object'],
                    'extraction' => ['type' => 'object'],
                    'versions' => ['type' => 'array'],
                ],
            ],
            'relationship' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => rest_url(self::API_NAMESPACE . '/schemas/relationship'),
                'title' => 'Sustainable Catalyst Library relationship',
                'type' => 'object',
                'required' => ['source_record_id', 'target_record_id', 'type'],
                'properties' => [
                    'source_record_id' => ['type' => 'integer'],
                    'target_record_id' => ['type' => 'integer'],
                    'type' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'provenance_url' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ],
            'webhook-event' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => rest_url(self::API_NAMESPACE . '/schemas/webhook-event'),
                'title' => 'Sustainable Catalyst Library webhook event',
                'type' => 'object',
                'required' => ['schema', 'event_id', 'event_type', 'occurred_at', 'data'],
                'properties' => [
                    'schema' => ['const' => self::WEBHOOK_SCHEMA],
                    'event_id' => ['type' => 'string', 'format' => 'uuid'],
                    'event_type' => ['type' => 'string', 'enum' => array_keys(self::event_types())],
                    'occurred_at' => ['type' => 'string', 'format' => 'date-time'],
                    'site' => ['type' => 'string', 'format' => 'uri'],
                    'data' => ['type' => 'object'],
                ],
                'additionalProperties' => false,
            ],
            'error' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'title' => 'WordPress REST error',
                'type' => 'object',
                'required' => ['code', 'message'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'data' => ['type' => 'object'],
                ],
            ],
        ];
    }

    public function render_portal_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Sustainable Catalyst Library Developer Portal',
            'intro' => 'Versioned public data, signed event delivery, portable schemas, and controlled service integration for the Sustainable Catalyst knowledge system.',
        ], $atts, 'sc_library_developer_portal');
        wp_enqueue_style('sc-library-developer-api', SC_LIBRARY_URL . 'assets/css/sc-library-developer-api.css', [], SC_LIBRARY_VERSION);
        $developer_title = sanitize_text_field((string) $atts['title']);
        $developer_intro = sanitize_text_field((string) $atts['intro']);
        $developer_openapi_url = rest_url(self::API_NAMESPACE . '/openapi.json');
        $developer_schema_url = rest_url(self::API_NAMESPACE . '/schemas');
        $developer_api_base = rest_url(self::API_NAMESPACE);
        $developer_events = self::event_types();
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-developer-portal.php';
        return (string) ob_get_clean();
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $keys = $wpdb->get_results('SELECT * FROM ' . self::keys_table() . ' ORDER BY created_at DESC LIMIT 100', ARRAY_A) ?: [];
        $webhooks = $wpdb->get_results('SELECT * FROM ' . self::webhooks_table() . ' ORDER BY created_at DESC LIMIT 100', ARRAY_A) ?: [];
        $deliveries = $wpdb->get_results('SELECT d.*, w.name AS webhook_name FROM ' . self::deliveries_table() . ' d LEFT JOIN ' . self::webhooks_table() . ' w ON w.id = d.webhook_id ORDER BY d.created_at DESC LIMIT 100', ARRAY_A) ?: [];
        $new_key = get_transient('sc_library_new_api_key_' . get_current_user_id());
        $new_webhook_secret = get_transient('sc_library_new_webhook_secret_' . get_current_user_id());
        if ($new_key) {
            delete_transient('sc_library_new_api_key_' . get_current_user_id());
        }
        if ($new_webhook_secret) {
            delete_transient('sc_library_new_webhook_secret_' . get_current_user_id());
        }
        $notice = sanitize_key((string) ($_GET['sc_notice'] ?? ''));
        include SC_LIBRARY_DIR . 'templates/library-developer-admin.php';
    }

    public function handle_create_key(): void {
        $this->require_admin_post('sc_library_api_key_create');
        global $wpdb;
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $mode = sanitize_key((string) ($_POST['mode'] ?? 'live')) === 'test' ? 'test' : 'live';
        $scopes = array_values(array_intersect(array_keys(self::scopes()), array_map('sanitize_text_field', (array) ($_POST['scopes'] ?? []))));
        if ($name === '' || !$scopes) {
            $this->redirect_admin('key-invalid');
        }
        $prefix = bin2hex(random_bytes(6));
        $secret = rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
        $plaintext = 'scl_' . $mode . '_' . $prefix . '_' . $secret;
        $rate = min(10000, max(60, absint($_POST['rate_limit'] ?? get_option('sc_library_api_key_rate_limit', 1000))));
        $expires = sanitize_text_field((string) ($_POST['expires_at'] ?? ''));
        $expires_mysql = '';
        if ($expires !== '') {
            $timestamp = strtotime($expires . ' 23:59:59 UTC');
            $expires_mysql = $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '';
        }
        $inserted = $wpdb->insert(self::keys_table(), [
            'key_uuid' => wp_generate_uuid4(),
            'name' => $name,
            'key_prefix' => $prefix,
            'secret_hash' => hash_hmac('sha256', $plaintext, wp_salt('auth')),
            'scopes_json' => wp_json_encode($scopes),
            'rate_limit_per_hour' => $rate,
            'status' => 'active',
            'last_used_at' => null,
            'expires_at' => $expires_mysql ?: null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);
        if ($inserted === false) {
            $this->redirect_admin('key-storage-failed');
        }
        set_transient('sc_library_new_api_key_' . get_current_user_id(), ['name' => $name, 'key' => $plaintext], 5 * MINUTE_IN_SECONDS);
        $this->redirect_admin('key-created');
    }

    public function handle_revoke_key(): void {
        $this->require_admin_post('sc_library_api_key_revoke');
        global $wpdb;
        $wpdb->update(self::keys_table(), ['status' => 'revoked', 'updated_at' => current_time('mysql', true)], ['id' => absint($_POST['id'] ?? 0)], ['%s', '%s'], ['%d']);
        $this->redirect_admin('key-revoked');
    }

    public function handle_create_webhook(): void {
        $this->require_admin_post('sc_library_webhook_create');
        global $wpdb;
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $url = esc_url_raw((string) ($_POST['endpoint_url'] ?? ''));
        $events = array_values(array_intersect(array_keys(self::event_types()), array_map('sanitize_text_field', (array) ($_POST['events'] ?? []))));
        if ($name === '' || !$this->safe_webhook_url($url) || !$events) {
            $this->redirect_admin('webhook-invalid');
        }
        $secret = 'whsec_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $encrypted_secret = $this->encrypt_secret($secret);
        if ($encrypted_secret === '') {
            $this->redirect_admin('webhook-encryption-unavailable');
        }
        $uuid = wp_generate_uuid4();
        $inserted = $wpdb->insert(self::webhooks_table(), [
            'webhook_uuid' => $uuid,
            'name' => $name,
            'endpoint_url' => $url,
            'secret_encrypted' => $encrypted_secret,
            'secret_prefix' => substr($secret, 0, 14),
            'events_json' => wp_json_encode($events),
            'status' => 'active',
            'last_delivery_at' => null,
            'last_status_code' => 0,
            'failure_count' => 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']);
        if ($inserted === false) {
            $this->redirect_admin('webhook-storage-failed');
        }
        set_transient('sc_library_new_webhook_secret_' . get_current_user_id(), ['name' => $name, 'secret' => $secret, 'uuid' => $uuid], 5 * MINUTE_IN_SECONDS);
        $this->redirect_admin('webhook-created');
    }

    public function handle_toggle_webhook(): void {
        $this->require_admin_post('sc_library_webhook_toggle');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $status = sanitize_key((string) ($_POST['status'] ?? 'paused')) === 'active' ? 'active' : 'paused';
        $wpdb->update(self::webhooks_table(), ['status' => $status, 'updated_at' => current_time('mysql', true)], ['id' => $id], ['%s', '%s'], ['%d']);
        $this->redirect_admin('webhook-updated');
    }

    public function handle_delete_webhook(): void {
        $this->require_admin_post('sc_library_webhook_delete');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $wpdb->delete(self::deliveries_table(), ['webhook_id' => $id], ['%d']);
        $wpdb->delete(self::webhooks_table(), ['id' => $id], ['%d']);
        $this->redirect_admin('webhook-deleted');
    }

    public function handle_test_webhook(): void {
        $this->require_admin_post('sc_library_webhook_test');
        $this->emit_event('api.test', ['message' => 'Administrator test event', 'requested_via' => 'wordpress-admin'], absint($_POST['id'] ?? 0));
        $this->redirect_admin('webhook-test-queued');
    }

    public function handle_redeliver(): void {
        $this->require_admin_post('sc_library_webhook_redeliver');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        $wpdb->update(self::deliveries_table(), [
            'status' => 'pending',
            'next_attempt_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s', '%s', '%s'], ['%d']);
        wp_schedule_single_event(time() + 1, 'sc_library_deliver_webhook', [$id]);
        $this->redirect_admin('delivery-queued');
    }

    private function require_admin_post(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'sustainable-catalyst-library'));
        }
        check_admin_referer($action);
    }

    private function redirect_admin(string $notice): void {
        wp_safe_redirect(add_query_arg(['page' => 'sc-library-developer-api', 'sc_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public function emit_event(string $event_type, array $data, int $only_webhook_id = 0): array {
        global $wpdb;
        if (!isset(self::event_types()[$event_type])) {
            return [];
        }
        $event = [
            'schema' => self::WEBHOOK_SCHEMA,
            'event_id' => wp_generate_uuid4(),
            'event_type' => $event_type,
            'occurred_at' => gmdate('c'),
            'site' => home_url('/'),
            'data' => $this->public_event_data($data),
        ];
        $sql = 'SELECT * FROM ' . self::webhooks_table() . " WHERE status = 'active'";
        $params = [];
        if ($only_webhook_id > 0) {
            $sql .= ' AND id = %d';
            $params[] = $only_webhook_id;
        }
        $webhooks = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        foreach ($webhooks ?: [] as $webhook) {
            $events = json_decode((string) $webhook['events_json'], true) ?: [];
            if ($event_type !== 'api.test' && !in_array($event_type, $events, true)) {
                continue;
            }
            $delivery_uuid = wp_generate_uuid4();
            $wpdb->insert(self::deliveries_table(), [
                'delivery_uuid' => $delivery_uuid,
                'webhook_id' => (int) $webhook['id'],
                'event_id' => $event['event_id'],
                'event_type' => $event_type,
                'payload_json' => wp_json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'attempt' => 0,
                'status' => 'pending',
                'response_code' => 0,
                'response_body' => '',
                'signature' => '',
                'next_attempt_at' => current_time('mysql', true),
                'delivered_at' => null,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ], ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);
            $delivery_id = (int) $wpdb->insert_id;
            wp_schedule_single_event(time() + 1, 'sc_library_deliver_webhook', [$delivery_id]);
        }
        return $event;
    }

    private function public_event_data(array $data): array {
        $blocked = ['secret', 'token', 'api_key', 'workspace_json', 'response_json', 'payload_json', 'email', 'invitation_token'];
        $clean = [];
        foreach ($data as $key => $value) {
            $safe_key = sanitize_key((string) $key);
            if ($safe_key === '' || in_array($safe_key, $blocked, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$safe_key] = $this->public_event_data($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$safe_key] = $value;
            }
        }
        return $clean;
    }

    public function deliver_webhook(int $delivery_id): void {
        global $wpdb;
        $delivery = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::deliveries_table() . ' WHERE id = %d LIMIT 1', $delivery_id), ARRAY_A);
        if (!$delivery || $delivery['status'] === 'delivered') {
            return;
        }
        $webhook = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::webhooks_table() . " WHERE id = %d AND status = 'active' LIMIT 1", (int) $delivery['webhook_id']), ARRAY_A);
        if (!$webhook || !$this->safe_webhook_url((string) $webhook['endpoint_url'])) {
            $wpdb->update(self::deliveries_table(), ['status' => 'failed', 'response_body' => 'Webhook is inactive or endpoint failed validation.', 'updated_at' => current_time('mysql', true)], ['id' => $delivery_id], ['%s', '%s', '%s'], ['%d']);
            return;
        }
        $secret = $this->decrypt_secret((string) $webhook['secret_encrypted']);
        if ($secret === '') {
            $wpdb->update(self::deliveries_table(), ['status' => 'failed', 'response_body' => 'Webhook secret could not be decrypted.', 'updated_at' => current_time('mysql', true)], ['id' => $delivery_id], ['%s', '%s', '%s'], ['%d']);
            return;
        }
        $payload = (string) $delivery['payload_json'];
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $attempt = (int) $delivery['attempt'] + 1;
        $response = wp_safe_remote_post((string) $webhook['endpoint_url'], [
            'timeout' => min(30, max(3, (int) get_option('sc_library_webhook_timeout', 10))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'Sustainable-Catalyst-Library/' . SC_LIBRARY_VERSION,
                'X-SC-Event' => (string) $delivery['event_type'],
                'X-SC-Delivery' => (string) $delivery['delivery_uuid'],
                'X-SC-Timestamp' => $timestamp,
                'X-SC-Signature' => $signature,
            ],
            'body' => $payload,
        ]);
        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        $body = is_wp_error($response) ? $response->get_error_message() : wp_strip_all_tags((string) wp_remote_retrieve_body($response));
        $body = function_exists('mb_substr') ? mb_substr($body, 0, 2000) : substr($body, 0, 2000);
        $success = $code >= 200 && $code < 300;
        $max_attempts = min(8, max(1, (int) get_option('sc_library_webhook_max_attempts', 4)));
        $status = $success ? 'delivered' : ($attempt >= $max_attempts ? 'failed' : 'retrying');
        $next = null;
        if (!$success && $attempt < $max_attempts) {
            $delays = [60, 300, 1800, 7200, 21600, 43200, 86400];
            $delay = $delays[min($attempt - 1, count($delays) - 1)];
            $next = gmdate('Y-m-d H:i:s', time() + $delay);
            wp_schedule_single_event(time() + $delay, 'sc_library_deliver_webhook', [$delivery_id]);
        }
        $wpdb->update(self::deliveries_table(), [
            'attempt' => $attempt,
            'status' => $status,
            'response_code' => $code,
            'response_body' => $body,
            'signature' => $signature,
            'next_attempt_at' => $next,
            'delivered_at' => $success ? current_time('mysql', true) : null,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $delivery_id], ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s'], ['%d']);
        $wpdb->update(self::webhooks_table(), [
            'last_delivery_at' => current_time('mysql', true),
            'last_status_code' => $code,
            'failure_count' => $success ? 0 : ((int) $webhook['failure_count'] + 1),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $webhook['id']], ['%s', '%d', '%d', '%s'], ['%d']);
    }

    private function safe_webhook_url(string $url): bool {
        if (!wp_http_validate_url($url) || stripos($url, 'https://') !== 0) {
            return false;
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }
        $addresses = gethostbynamel($host) ?: [];
        foreach ($addresses as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    private function encrypt_secret(string $secret): string {
        $key = hash('sha256', wp_salt('auth'), true);
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 'sodium:' . base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $key));
        }
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(16);
            $cipher = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $cipher === false ? '' : 'openssl:' . base64_encode($iv . $cipher);
        }
        return '';
    }

    private function decrypt_secret(string $encrypted): string {
        $key = hash('sha256', wp_salt('auth'), true);
        if (strpos($encrypted, 'sodium:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($encrypted, 7), true);
            if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return '';
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
            return $plain === false ? '' : $plain;
        }
        if (strpos($encrypted, 'openssl:') === 0 && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($encrypted, 8), true);
            if ($raw === false || strlen($raw) <= 16) {
                return '';
            }
            $plain = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));
            return $plain === false ? '' : $plain;
        }
        return '';
    }

    public function capture_status_transition(string $new_status, string $old_status, WP_Post $post): void {
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
            return;
        }
        $configured = $this->indexer->configured_post_types();
        if (!in_array($post->post_type, $configured, true)) {
            return;
        }
        if ($post->post_type === (class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan')) {
            $event = $old_status === 'new' || $old_status === 'auto-draft' ? 'plan.created' : 'plan.transitioned';
            $this->emit_event($event, [
                'record_id' => $post->ID,
                'title' => get_the_title($post),
                'wordpress_status' => $new_status,
                'previous_wordpress_status' => $old_status,
                'planning_status' => (string) get_post_meta($post->ID, '_sc_plan_status', true),
                'public' => (bool) get_post_meta($post->ID, '_sc_plan_public_visibility', true),
            ]);
            return;
        }
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->emit_event('record.published', $this->record_event_data($post));
        } elseif ($old_status === 'publish' && $new_status !== 'publish') {
            $this->emit_event('record.archived', $this->record_event_data($post, ['new_status' => $new_status]));
        }
    }

    public function capture_post_updated(int $post_id, WP_Post $post_after, WP_Post $post_before): void {
        if ($post_after->post_status !== 'publish' || $post_before->post_status !== 'publish' || wp_is_post_revision($post_id)) {
            return;
        }
        if (!in_array($post_after->post_type, $this->indexer->configured_post_types(), true)) {
            return;
        }
        if ($post_after->post_modified_gmt === $post_before->post_modified_gmt) {
            return;
        }
        $event = $post_after->post_type === (class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan') ? 'plan.transitioned' : 'record.updated';
        if (class_exists('SC_Library_Documentation') && has_term(SC_Library_Documentation::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION, $post_id)) {
            $event = 'documentation.updated';
        }
        $this->emit_event($event, $this->record_event_data($post_after));
    }

    private function record_event_data(WP_Post $post, array $extra = []): array {
        return array_merge([
            'record_id' => $post->ID,
            'record_type' => $post->post_type,
            'title' => get_the_title($post),
            'url' => get_permalink($post),
            'modified_at' => get_post_modified_time('c', true, $post),
        ], $extra);
    }

    public function capture_graph_rebuilt(array $summary, string $sync_token = ''): void {
        $this->emit_event('graph.rebuilt', ['summary' => $summary, 'sync_token' => $sync_token]);
    }

    public function capture_workspace_revised(array $payload): void {
        $event_payload = [
            'workspace_uuid' => (string) ($payload['workspace_uuid'] ?? ''),
            'revision' => (int) ($payload['revision'] ?? 0),
            'change_type' => sanitize_key((string) ($payload['change_type'] ?? 'updated')),
            'visibility' => sanitize_key((string) ($payload['visibility'] ?? 'private')),
        ];
        if ($event_payload['visibility'] === 'public') {
            $event_payload['title'] = sanitize_text_field((string) ($payload['title'] ?? ''));
        }
        $this->emit_event('workspace.revised', $event_payload);
    }

    public function capture_review_transitioned(array $payload): void {
        $event = (($payload['status'] ?? '') === 'approved') ? 'review.approved' : 'review.transitioned';
        $event_payload = [
            'review_uuid' => (string) ($payload['review_uuid'] ?? ''),
            'from_status' => sanitize_key((string) ($payload['from_status'] ?? '')),
            'status' => sanitize_key((string) ($payload['status'] ?? '')),
            'object_type' => sanitize_key((string) ($payload['object_type'] ?? '')),
            'visibility' => sanitize_key((string) ($payload['visibility'] ?? 'private')),
            'revision' => (int) ($payload['revision'] ?? 0),
        ];
        if ($event_payload['visibility'] === 'public') {
            $event_payload['title'] = sanitize_text_field((string) ($payload['title'] ?? ''));
            $event_payload['object_identifier'] = sanitize_text_field((string) ($payload['object_identifier'] ?? ''));
        }
        $this->emit_event($event, $event_payload);
    }

    public function capture_document_rendered(array $payload): void {
        $this->emit_event('book.rendered', $payload);
    }

    public function capture_media_completed(array $payload): void {
        $this->emit_event('media.clip.completed', $payload);
    }
    public function capture_foundation_document_extracted(int $post_id, array $summary): void {
        $this->emit_event('foundation-document.extracted', [
            'record_id' => $post_id,
            'record_url' => get_permalink($post_id),
            'page_count' => (int) ($summary['pages'] ?? 0),
            'character_count' => (int) ($summary['chars'] ?? 0),
        ]);
    }

}
