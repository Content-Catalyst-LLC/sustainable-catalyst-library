<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dedicated, resumable Library index scanner.
 *
 * The scanner deliberately remains WordPress-local. It does not depend on
 * Render, PostgreSQL, document production, or workspace synchronization.
 */
final class SC_Library_Scanner {
    public const STATE_OPTION = 'sc_library_scan_state';
    public const LOG_OPTION = 'sc_library_scan_logs';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1/library';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_sc_library_download_scan_log', [$this, 'download_log']);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Index Scanner', 'sustainable-catalyst-library'),
            __('Index Scanner', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-scanner',
            [$this, 'render_page']
        );
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'sc-library_page_sc-library-scanner') {
            return;
        }

        wp_enqueue_style(
            'sc-library-scanner',
            SC_LIBRARY_URL . 'assets/css/sc-library-scanner.css',
            [],
            SC_LIBRARY_VERSION
        );
        wp_enqueue_script(
            'sc-library-scanner',
            SC_LIBRARY_URL . 'assets/js/sc-library-scanner.js',
            ['wp-api-fetch'],
            SC_LIBRARY_VERSION,
            true
        );
        wp_localize_script('sc-library-scanner', 'SCLibraryScanner', [
            'root' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/scanner')),
            'nonce' => wp_create_nonce('wp_rest'),
            'downloadLogUrl' => wp_nonce_url(
                admin_url('admin-post.php?action=sc_library_download_scan_log'),
                'sc_library_download_scan_log'
            ),
            'strings' => [
                'confirmStart' => __('Start a new scan? Any incomplete scan state will be replaced.', 'sustainable-catalyst-library'),
                'confirmCancel' => __('Cancel the current scan? Indexed records already completed will remain available.', 'sustainable-catalyst-library'),
                'working' => __('Scanning…', 'sustainable-catalyst-library'),
                'complete' => __('Scan completed.', 'sustainable-catalyst-library'),
                'error' => __('The scanner request failed. The saved scan can be resumed after the problem is corrected.', 'sustainable-catalyst-library'),
                'recordRequired' => __('Enter a WordPress post ID or canonical URL.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function register_rest_routes(): void {
        $permission = static fn(): bool => current_user_can('manage_options');

        register_rest_route(self::REST_NAMESPACE, '/scanner/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_status'],
            'permission_callback' => $permission,
            'args' => [
                'diagnostics' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/start', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_start'],
            'permission_callback' => $permission,
            'args' => [
                'post_types' => ['default' => []],
                'batch_size' => ['sanitize_callback' => 'absint', 'default' => 50],
                'mode' => ['sanitize_callback' => 'sanitize_key', 'default' => 'full'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/step', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_step'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/pause', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_pause'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/resume', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_resume'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/cancel', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_cancel'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/repair', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_repair'],
            'permission_callback' => $permission,
            'args' => [
                'repair' => ['sanitize_callback' => 'sanitize_key', 'default' => 'stale'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/scanner/record', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_record'],
            'permission_callback' => $permission,
            'args' => [
                'record' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
            ],
        ]);
    }

    public function render_page(): void {
        $diagnostics = $this->diagnostics();
        $state = $this->public_state($this->get_state());
        $post_types = $this->available_post_types();
        $logs = $this->get_logs();
        include SC_LIBRARY_DIR . 'templates/library-index-scanner.php';
    }

    public function rest_status(WP_REST_Request $request): WP_REST_Response {
        $payload = [
            'ok' => true,
            'state' => $this->public_state($this->get_state()),
            'logs' => array_slice($this->get_logs(), 0, 8),
        ];
        if ((bool) $request->get_param('diagnostics')) {
            $payload['diagnostics'] = $this->diagnostics();
        }
        return rest_ensure_response($payload);
    }

    public function rest_start(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $configured = $this->indexer->configured_post_types();
        $requested = $request->get_param('post_types');
        $requested = is_array($requested) ? array_map('sanitize_key', $requested) : [];
        $post_types = array_values(array_intersect($requested ?: $configured, $configured));
        if (!$post_types) {
            return new WP_Error('sc_library_scanner_no_types', __('Select at least one configured post type.', 'sustainable-catalyst-library'), ['status' => 400]);
        }

        $batch_size = min(250, max(10, absint($request->get_param('batch_size') ?: 50)));
        $mode = sanitize_key((string) $request->get_param('mode'));
        if (!in_array($mode, ['full', 'missing', 'outdated', 'repair'], true)) {
            $mode = 'full';
        }

        $diagnostics = $this->diagnostics($post_types, true);
        $all_candidates = $this->indexer->scan_candidate_ids($post_types);
        $queue = match ($mode) {
            'missing' => $diagnostics['missing_ids'],
            'outdated' => $diagnostics['outdated_ids'],
            'repair' => array_values(array_unique(array_merge($diagnostics['missing_ids'], $diagnostics['outdated_ids']))),
            default => $all_candidates,
        };

        $purged = 0;
        $relationships_purged = 0;
        if ($mode === 'full') {
            $purged = $this->indexer->purge_stale_records();
            $relationships_purged = $this->relationships->purge_stale();
        }

        $now = current_time('mysql', true);
        $state = [
            'schema' => 'sc-library-index-scan/1.0',
            'scan_id' => wp_generate_uuid4(),
            'status' => $queue ? 'running' : 'complete',
            'mode' => $mode,
            'post_types' => $post_types,
            'batch_size' => $batch_size,
            'queue' => array_values(array_map('absint', $queue)),
            'total' => count($queue),
            'processed' => 0,
            'indexed' => 0,
            'skipped' => max(0, $this->indexer->scan_published_count($post_types) - count($all_candidates)),
            'failed' => 0,
            'purged' => $purged,
            'relationships_purged' => $relationships_purged,
            'last_post_id' => 0,
            'failures' => [],
            'started_at' => $now,
            'updated_at' => $now,
            'completed_at' => $queue ? '' : $now,
        ];
        $this->save_state($state);
        $this->append_log('scan_started', [
            'scan_id' => $state['scan_id'],
            'mode' => $mode,
            'post_types' => $post_types,
            'total' => $state['total'],
            'batch_size' => $batch_size,
        ]);

        if (!$queue) {
            $this->finish_scan($state);
            $state = $this->get_state();
        }

        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_step(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $state = $this->get_state();
        if (!$state || empty($state['scan_id'])) {
            return new WP_Error('sc_library_scanner_no_scan', __('No saved scan is available.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        if (($state['status'] ?? '') === 'paused') {
            return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
        }
        if (in_array((string) ($state['status'] ?? ''), ['complete', 'cancelled'], true)) {
            return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
        }

        $offset = absint($state['processed'] ?? 0);
        $batch_size = absint($state['batch_size'] ?? 50);
        $queue = is_array($state['queue'] ?? null) ? $state['queue'] : [];
        $ids = array_slice($queue, $offset, $batch_size);
        if (!$ids) {
            $this->finish_scan($state);
            return rest_ensure_response(['ok' => true, 'state' => $this->public_state($this->get_state())]);
        }

        $batch = $this->indexer->scan_batch_ids($ids);
        $state['processed'] = $offset + count($ids);
        $state['indexed'] = absint($state['indexed'] ?? 0) + absint($batch['indexed']);
        $state['skipped'] = absint($state['skipped'] ?? 0) + absint($batch['skipped']);
        $state['failed'] = absint($state['failed'] ?? 0) + absint($batch['failed']);
        $state['last_post_id'] = absint(end($ids));
        $state['updated_at'] = current_time('mysql', true);
        $state['status'] = 'running';
        $state['failures'] = array_slice(array_merge(
            is_array($state['failures'] ?? null) ? $state['failures'] : [],
            is_array($batch['failures'] ?? null) ? $batch['failures'] : []
        ), -50);
        $this->save_state($state);

        if ($state['processed'] >= absint($state['total'] ?? 0)) {
            $this->finish_scan($state);
            $state = $this->get_state();
        }

        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_pause(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $state = $this->get_state();
        if (!$state) {
            return new WP_Error('sc_library_scanner_no_scan', __('No saved scan is available.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        if (($state['status'] ?? '') === 'running') {
            $state['status'] = 'paused';
            $state['updated_at'] = current_time('mysql', true);
            $this->save_state($state);
            $this->append_log('scan_paused', ['scan_id' => $state['scan_id']]);
        }
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_resume(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $state = $this->get_state();
        if (!$state) {
            return new WP_Error('sc_library_scanner_no_scan', __('No saved scan is available.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        if (!in_array((string) ($state['status'] ?? ''), ['complete', 'cancelled'], true)) {
            $state['status'] = 'running';
            $state['updated_at'] = current_time('mysql', true);
            $this->save_state($state);
            $this->append_log('scan_resumed', ['scan_id' => $state['scan_id']]);
        }
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_cancel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $state = $this->get_state();
        if (!$state) {
            return new WP_Error('sc_library_scanner_no_scan', __('No saved scan is available.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $state['status'] = 'cancelled';
        $state['updated_at'] = current_time('mysql', true);
        $state['completed_at'] = $state['updated_at'];
        $this->save_state($state);
        $this->append_log('scan_cancelled', [
            'scan_id' => $state['scan_id'],
            'processed' => absint($state['processed'] ?? 0),
            'total' => absint($state['total'] ?? 0),
        ]);
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_repair(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $repair = sanitize_key((string) $request->get_param('repair'));
        $result = ['repair' => $repair];

        if ($repair === 'schema') {
            SC_Library_Activator::repair_schema();
            $result['schema_repaired'] = true;
            $result['daily_reconcile_scheduled'] = (bool) wp_next_scheduled('sc_library_daily_reconcile');
        } elseif ($repair === 'relationships') {
            $affected_ids = $this->relationship_record_ids();
            $result['relationships_purged'] = $this->relationships->purge_stale();
            $reindexed = 0;
            foreach ($affected_ids as $post_id) {
                if ($this->indexer->reindex_post($post_id)) {
                    $reindexed++;
                }
            }
            $result['records_reindexed'] = $reindexed;
        } elseif ($repair === 'identifiers') {
            $diagnostics = $this->diagnostics(null, true);
            $ids = array_values(array_unique(array_merge($diagnostics['invalid_ids'], $diagnostics['outdated_ids'])));
            $batch = $this->indexer->scan_batch_ids($ids);
            $result = array_merge($result, $batch);
        } else {
            $result['purged'] = $this->indexer->purge_stale_records();
            $result['relationships_purged'] = $this->relationships->purge_stale();
        }

        $this->append_log('repair_completed', $result);
        return rest_ensure_response(['ok' => true, 'result' => $result, 'diagnostics' => $this->diagnostics()]);
    }

    public function rest_record(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $raw = trim((string) $request->get_param('record'));
        $post_id = ctype_digit($raw) ? absint($raw) : url_to_postid(esc_url_raw($raw));
        if ($post_id < 1 || !get_post($post_id)) {
            return new WP_Error('sc_library_scanner_record_not_found', __('No WordPress record matched that ID or URL.', 'sustainable-catalyst-library'), ['status' => 404]);
        }

        $eligibility = $this->indexer->scan_eligibility($post_id);
        $indexed = false;
        if ($eligibility['eligible']) {
            $indexed = $this->indexer->index_post($post_id);
        } else {
            $this->indexer->delete_record($post_id);
        }

        $result = [
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'eligible' => (bool) $eligibility['eligible'],
            'reason' => (string) $eligibility['reason'],
            'indexed' => $indexed,
            'record_identifier' => sprintf('sc:library:%s:%d', sanitize_key((string) get_post_type($post_id)), $post_id),
        ];
        $this->append_log('record_reindexed', $result);
        return rest_ensure_response(['ok' => true, 'result' => $result, 'diagnostics' => $this->diagnostics()]);
    }

    public function download_log(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to download scanner logs.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_download_scan_log');

        $payload = [
            'schema' => 'sc-library-index-scan-log/1.0',
            'generated_at' => gmdate('c'),
            'site_url' => home_url('/'),
            'plugin_version' => SC_LIBRARY_VERSION,
            'state' => $this->public_state($this->get_state()),
            'diagnostics' => $this->diagnostics(),
            'logs' => $this->get_logs(),
        ];

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sc-library-index-scan-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function finish_scan(array $state): void {
        $state['purged'] = absint($state['purged'] ?? 0) + $this->indexer->purge_stale_records();
        $state['relationships_purged'] = absint($state['relationships_purged'] ?? 0) + $this->relationships->purge_stale();
        $state['status'] = 'complete';
        $state['processed'] = absint($state['total'] ?? 0);
        $state['updated_at'] = current_time('mysql', true);
        $state['completed_at'] = $state['updated_at'];
        $this->save_state($state);

        if (($state['mode'] ?? '') === 'full') {
            update_option('sc_library_last_full_index', current_time('mysql', true));
        }
        update_option('sc_library_last_scan_summary', $this->public_state($state), false);
        $this->append_log('scan_completed', $this->public_state($state));
    }

    private function diagnostics(?array $post_types = null, bool $include_ids = false): array {
        global $wpdb;

        $post_types = $post_types ?: $this->indexer->configured_post_types();
        $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
        $candidate_ids = $this->indexer->scan_candidate_ids($post_types);
        $candidate_lookup = array_fill_keys($candidate_ids, true);
        $table = $this->indexer->table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $rows = $table_exists
            ? ($wpdb->get_results("SELECT post_id, post_type, record_identifier, title, permalink, modified_at, indexed_at FROM {$table}", ARRAY_A) ?: [])
            : [];

        $indexed_lookup = [];
        $indexed_by_type = [];
        $missing_ids = [];
        $stale_ids = [];
        $outdated_ids = [];
        $invalid_ids = [];
        $duplicate_ids = [];

        foreach ($rows as $row) {
            $post_id = absint($row['post_id'] ?? 0);
            if (isset($indexed_lookup[$post_id])) {
                $duplicate_ids[] = $post_id;
            }
            $indexed_lookup[$post_id] = $row;
            $type = sanitize_key((string) ($row['post_type'] ?? ''));
            $indexed_by_type[$type] = ($indexed_by_type[$type] ?? 0) + 1;

            if (!isset($candidate_lookup[$post_id])) {
                $stale_ids[] = $post_id;
                continue;
            }
            if (empty($row['record_identifier']) || empty($row['title']) || empty($row['permalink'])) {
                $invalid_ids[] = $post_id;
            }
            $post = get_post($post_id);
            if ($post) {
                $modified = get_gmt_from_date($post->post_modified);
                $indexed_at = (string) ($row['indexed_at'] ?? '');
                if ($indexed_at === '' || strtotime($indexed_at . ' UTC') < strtotime($modified . ' UTC')) {
                    $outdated_ids[] = $post_id;
                }
            }
        }

        foreach ($candidate_ids as $post_id) {
            if (!isset($indexed_lookup[$post_id])) {
                $missing_ids[] = $post_id;
            }
        }

        $stats = [];
        foreach ($post_types as $post_type) {
            $object = get_post_type_object($post_type);
            $type_candidates = $this->indexer->scan_candidate_ids([$post_type]);
            $type_missing = array_values(array_intersect($type_candidates, $missing_ids));
            $type_outdated = array_values(array_intersect($type_candidates, $outdated_ids));
            $latest_indexed = '';
            foreach ($rows as $row) {
                if (($row['post_type'] ?? '') === $post_type && (string) ($row['indexed_at'] ?? '') > $latest_indexed) {
                    $latest_indexed = (string) $row['indexed_at'];
                }
            }
            $stats[] = [
                'post_type' => $post_type,
                'label' => $object ? $object->labels->name : $post_type,
                'eligible' => count($type_candidates),
                'indexed' => absint($indexed_by_type[$post_type] ?? 0),
                'missing' => count($type_missing),
                'outdated' => count($type_outdated),
                'latest_indexed_at' => $latest_indexed,
            ];
        }

        $fulltext = false;
        if ($table_exists) {
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A) ?: [];
            foreach ($indexes as $index) {
                if (($index['Key_name'] ?? '') === 'sc_library_search' && strtoupper((string) ($index['Index_type'] ?? '')) === 'FULLTEXT') {
                    $fulltext = true;
                    break;
                }
            }
        }

        $diagnostics = [
            'table_exists' => $table_exists,
            'fulltext_index' => $fulltext,
            'daily_reconcile_scheduled' => (bool) wp_next_scheduled('sc_library_daily_reconcile'),
            'configured_post_types' => $post_types,
            'published_candidates' => $this->indexer->scan_published_count($post_types),
            'eligible_records' => count($candidate_ids),
            'indexed_records' => count($rows),
            'missing_records' => count($missing_ids),
            'outdated_records' => count(array_unique($outdated_ids)),
            'stale_records' => count(array_unique($stale_ids)),
            'duplicate_records' => count(array_unique($duplicate_ids)),
            'invalid_records' => count(array_unique($invalid_ids)),
            'relationships' => $this->relationships->count(),
            'last_full_index' => (string) get_option('sc_library_last_full_index', ''),
            'post_types' => $stats,
            'missing_sample' => $this->record_sample($missing_ids),
            'outdated_sample' => $this->record_sample($outdated_ids),
            'stale_sample' => $this->record_sample($stale_ids),
            'invalid_sample' => $this->record_sample($invalid_ids),
        ];

        if ($include_ids) {
            $diagnostics['missing_ids'] = array_values(array_unique(array_map('absint', $missing_ids)));
            $diagnostics['outdated_ids'] = array_values(array_unique(array_map('absint', $outdated_ids)));
            $diagnostics['stale_ids'] = array_values(array_unique(array_map('absint', $stale_ids)));
            $diagnostics['invalid_ids'] = array_values(array_unique(array_map('absint', $invalid_ids)));
            $diagnostics['duplicate_ids'] = array_values(array_unique(array_map('absint', $duplicate_ids)));
        }

        return $diagnostics;
    }

    private function available_post_types(): array {
        $selected = $this->indexer->configured_post_types();
        $objects = get_post_types(['public' => true], 'objects');
        $result = [];
        foreach ($selected as $post_type) {
            $object = $objects[$post_type] ?? get_post_type_object($post_type);
            $result[] = [
                'name' => $post_type,
                'label' => $object ? $object->labels->name : $post_type,
                'eligible' => count($this->indexer->scan_candidate_ids([$post_type])),
            ];
        }
        return $result;
    }

    private function record_sample(array $ids, int $limit = 8): array {
        $sample = [];
        foreach (array_slice(array_values(array_unique(array_map('absint', $ids))), 0, $limit) as $post_id) {
            $post = get_post($post_id);
            $sample[] = [
                'post_id' => $post_id,
                'title' => $post ? get_the_title($post_id) : __('Deleted or unavailable record', 'sustainable-catalyst-library'),
                'post_type' => $post ? $post->post_type : '',
                'edit_url' => $post ? get_edit_post_link($post_id, 'raw') : '',
            ];
        }
        return $sample;
    }

    private function relationship_record_ids(): array {
        global $wpdb;
        $table = $this->relationships->table_name();
        $ids = $wpdb->get_col("SELECT source_post_id FROM {$table} UNION SELECT target_post_id FROM {$table}") ?: [];
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private function get_state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private function save_state(array $state): void {
        update_option(self::STATE_OPTION, $state, false);
    }

    private function public_state(array $state): array {
        if (!$state) {
            return [
                'schema' => 'sc-library-index-scan/1.0',
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'indexed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'purged' => 0,
                'relationships_purged' => 0,
                'progress' => 0,
            ];
        }
        unset($state['queue']);
        $total = max(0, absint($state['total'] ?? 0));
        $processed = min($total, absint($state['processed'] ?? 0));
        $state['progress'] = $total > 0 ? round(($processed / $total) * 100, 1) : (($state['status'] ?? '') === 'complete' ? 100 : 0);
        return $state;
    }

    private function append_log(string $event, array $context = []): void {
        $logs = $this->get_logs();
        array_unshift($logs, [
            'event' => sanitize_key($event),
            'created_at' => current_time('mysql', true),
            'context' => $context,
        ]);
        update_option(self::LOG_OPTION, array_slice($logs, 0, 40), false);
    }

    private function get_logs(): array {
        $logs = get_option(self::LOG_OPTION, []);
        return is_array($logs) ? $logs : [];
    }
}
