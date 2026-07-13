<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cursor-based, auditable Library index scanner for large WordPress sites.
 *
 * Scanner state remains small regardless of Library size. Candidate discovery
 * uses direct database queries and cannot be altered by pre_get_posts or theme
 * query filters. Every processed record is written to a scan audit table.
 */
final class SC_Library_Scanner {
    public const STATE_OPTION = 'sc_library_scan_state';
    public const LOG_OPTION = 'sc_library_scan_logs';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1/library';
    public const STATE_SCHEMA = 'sc-library-index-scan/2.0';
    public const REPORT_SCHEMA = 'sc-library-index-scan-log/2.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_init', [$this, 'maybe_expand_legacy_scope'], 25);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_sc_library_download_scan_log', [$this, 'download_log']);
        add_action('admin_post_sc_library_server_reconcile', [$this, 'server_reconcile']);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Index Tools', 'sustainable-catalyst-library'),
            __('Index Tools', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-index-tools',
            [$this, 'render_page']
        );
        // Keep the v1.13.1-v1.13.3 route alive as a hidden compatibility alias.
        add_submenu_page(
            null,
            __('Index Scanner', 'sustainable-catalyst-library'),
            '',
            'manage_options',
            'sc-library-scanner',
            [$this, 'render_page']
        );
    }

    public function admin_assets(string $hook): void {
        $is_index_tools = in_array($hook, ['sc-library_page_sc-library-index-tools', 'admin_page_sc-library-scanner'], true);
        $is_main_fallback = $hook === 'toplevel_page_sc-library' && sanitize_key((string) ($_GET['tab'] ?? '')) === 'index-tools';
        if (!$is_index_tools && !$is_main_fallback) {
            return;
        }

        wp_enqueue_style('sc-library-scanner', SC_LIBRARY_URL . 'assets/css/sc-library-scanner.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-scanner', SC_LIBRARY_URL . 'assets/js/sc-library-scanner.js', ['wp-api-fetch'], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-scanner', 'SCLibraryScanner', [
            'path' => '/' . self::REST_NAMESPACE . '/scanner',
            'root' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/scanner')),
            'nonce' => wp_create_nonce('wp_rest'),
            'downloadLogUrl' => wp_nonce_url(admin_url('admin-post.php?action=sc_library_download_scan_log'), 'sc_library_download_scan_log'),
            'strings' => [
                'confirmStart' => __('Start a new cursor-based scan? Any incomplete scan state will be replaced, but existing indexed records remain available.', 'sustainable-catalyst-library'),
                'confirmCancel' => __('Cancel the current scan? Records already processed will remain indexed and the audit report will be preserved.', 'sustainable-catalyst-library'),
                'confirmReset' => __('Reset the saved scanner cursor and counters? This does not delete the Library index.', 'sustainable-catalyst-library'),
                'working' => __('Scanning the WordPress database in bounded batches…', 'sustainable-catalyst-library'),
                'complete' => __('Scan completed and accounting reconciled.', 'sustainable-catalyst-library'),
                'completeErrors' => __('Scan completed with failed records. Download the report and repair the failures.', 'sustainable-catalyst-library'),
                'incomplete' => __('The scan stopped without reconciling all discovered records. Resume or reset and run again.', 'sustainable-catalyst-library'),
                'error' => __('The scanner request failed. Its saved cursor can be resumed after the problem is corrected.', 'sustainable-catalyst-library'),
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
            'args' => ['diagnostics' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true]],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/scanner/start', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_start'],
            'permission_callback' => $permission,
            'args' => [
                'post_types' => ['default' => []],
                'batch_size' => ['sanitize_callback' => 'absint', 'default' => 50],
                'mode' => ['sanitize_callback' => 'sanitize_key', 'default' => 'full'],
                'persist_post_types' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true],
            ],
        ]);
        foreach (['step', 'pause', 'resume', 'cancel', 'reset'] as $action) {
            register_rest_route(self::REST_NAMESPACE, '/scanner/' . $action, [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rest_' . $action],
                'permission_callback' => $permission,
            ]);
        }
        register_rest_route(self::REST_NAMESPACE, '/scanner/repair', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_repair'],
            'permission_callback' => $permission,
            'args' => ['repair' => ['sanitize_callback' => 'sanitize_key', 'default' => 'stale']],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/scanner/record', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_record'],
            'permission_callback' => $permission,
            'args' => ['record' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true]],
        ]);
    }

    public function render_page(): void {
        $diagnostics = $this->diagnostics();
        $state = $this->public_state($this->get_state());
        $post_types = $this->available_post_types();
        $logs = $this->get_logs();
        include SC_LIBRARY_DIR . 'templates/library-index-scanner.php';
    }

    /** Expand the legacy Posts-only scope once when the database clearly contains additional editorial records. */
    public function maybe_expand_legacy_scope(): void {
        if (!current_user_can('manage_options') || get_option('sc_library_scanner_1134_scope_checked')) {
            return;
        }
        $configured = $this->indexer->configured_post_types(false);
        $recommended = $this->indexer->recommended_post_types();
        $configured_count = $this->indexer->scan_published_count($configured);
        $recommended_count = $this->indexer->scan_published_count($recommended);
        $expanded = false;
        if ($recommended && $recommended_count > $configured_count && $configured === ['post']) {
            $this->indexer->save_configured_post_types($recommended);
            $expanded = true;
        }
        update_option('sc_library_scanner_1134_scope_checked', [
            'checked_at' => current_time('mysql', true),
            'expanded' => $expanded,
            'configured_count_before' => $configured_count,
            'recommended_count' => $recommended_count,
        ], false);
    }

    /** No-JavaScript/server fallback. Processes as many cursor batches as fit safely in one request. */
    public function server_reconcile(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to run the Library scanner.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_server_reconcile');
        $restart = !empty($_POST['restart']);
        if ($restart || !$this->get_state() || !in_array((string) ($this->get_state()['status'] ?? ''), ['running', 'paused'], true)) {
            $start = new WP_REST_Request('POST');
            $start->set_param('post_types', $this->indexer->recommended_post_types());
            $start->set_param('batch_size', 100);
            $start->set_param('mode', 'full');
            $start->set_param('persist_post_types', true);
            $this->rest_start($start);
        } else {
            $state = $this->get_state();
            if (($state['status'] ?? '') === 'paused') {
                $this->rest_resume(new WP_REST_Request('POST'));
            }
        }

        $deadline = microtime(true) + 12.0;
        do {
            $state = $this->get_state();
            if (($state['status'] ?? '') !== 'running') {
                break;
            }
            $this->rest_step(new WP_REST_Request('POST'));
        } while (microtime(true) < $deadline);

        $state = $this->public_state($this->get_state());
        $url = add_query_arg([
            'page' => 'sc-library-index-tools',
            'server_scan' => sanitize_key((string) ($state['status'] ?? 'idle')),
            'processed' => absint($state['processed'] ?? 0),
            'total' => absint($state['total'] ?? 0),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function rest_status(WP_REST_Request $request): WP_REST_Response {
        $payload = [
            'ok' => true,
            'state' => $this->public_state($this->get_state()),
            'post_types' => $this->available_post_types(),
            'logs' => array_slice($this->get_logs(), 0, 8),
        ];
        if ((bool) $request->get_param('diagnostics')) {
            $payload['diagnostics'] = $this->diagnostics();
        }
        return rest_ensure_response($payload);
    }

    public function rest_start(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $discoverable = $this->indexer->discoverable_post_types();
        $requested = $request->get_param('post_types');
        $requested = is_array($requested) ? array_map('sanitize_key', $requested) : [];
        $post_types = $this->indexer->normalize_post_types($requested ?: $this->indexer->recommended_post_types());
        $post_types = array_values(array_intersect($post_types, $discoverable));
        if (!$post_types) {
            return new WP_Error('sc_library_scanner_no_types', __('Select at least one discovered editorial post type.', 'sustainable-catalyst-library'), ['status' => 400]);
        }

        if ((bool) $request->get_param('persist_post_types')) {
            $this->indexer->save_configured_post_types($post_types);
        }

        $batch_size = min(500, max(10, absint($request->get_param('batch_size') ?: 50)));
        $mode = sanitize_key((string) $request->get_param('mode'));
        if (!in_array($mode, ['full', 'missing', 'outdated', 'repair'], true)) {
            $mode = 'full';
        }

        SC_Library_Activator::repair_schema();
        $this->cleanup_old_scan_items();
        $total = $this->indexer->scan_count_for_mode($post_types, $mode, $mode !== 'full');
        $discovered = $this->indexer->scan_published_count($post_types);
        $eligible = $this->indexer->scan_count_for_mode($post_types, 'full', true);
        $purged = $mode === 'full' ? $this->indexer->purge_stale_records($post_types) : 0;
        $relationships_purged = $mode === 'full' ? $this->relationships->purge_stale() : 0;
        $now = current_time('mysql', true);

        $state = [
            'schema' => self::STATE_SCHEMA,
            'scan_id' => wp_generate_uuid4(),
            'status' => $total > 0 ? 'running' : 'complete',
            'mode' => $mode,
            'post_types' => $post_types,
            'batch_size' => $batch_size,
            'cursor_id' => 0,
            'total' => $total,
            'discovered_at_start' => $discovered,
            'eligible_at_start' => $eligible,
            'processed' => 0,
            'indexed' => 0,
            'excluded' => 0,
            'skipped' => 0,
            'failed' => 0,
            'purged' => $purged,
            'relationships_purged' => $relationships_purged,
            'last_post_id' => 0,
            'failures' => [],
            'exclusion_samples' => [],
            'exclusion_reasons' => [],
            'per_type' => [],
            'accounted' => 0,
            'accounting_ok' => true,
            'inventory_changed' => false,
            'started_at' => $now,
            'updated_at' => $now,
            'completed_at' => $total > 0 ? '' : $now,
        ];
        $this->save_state($state);
        $this->append_log('scan_started', [
            'scan_id' => $state['scan_id'],
            'mode' => $mode,
            'post_types' => $post_types,
            'total' => $total,
            'discovered' => $discovered,
            'eligible' => $eligible,
            'batch_size' => $batch_size,
        ]);

        if ($total === 0) {
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
        if (in_array((string) ($state['status'] ?? ''), ['complete', 'complete_with_errors', 'incomplete', 'cancelled'], true)) {
            return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
        }

        $post_types = $this->indexer->normalize_post_types((array) ($state['post_types'] ?? []));
        $cursor = absint($state['cursor_id'] ?? 0);
        $batch_size = min(500, max(10, absint($state['batch_size'] ?? 50)));
        $mode = sanitize_key((string) ($state['mode'] ?? 'full'));
        $rows = $this->indexer->scan_fetch_batch($post_types, $cursor, $batch_size, $mode);
        if (!$rows) {
            $this->finish_scan($state);
            return rest_ensure_response(['ok' => true, 'state' => $this->public_state($this->get_state())]);
        }

        $ids = array_values(array_filter(array_map(static fn(array $row): int => absint($row['ID'] ?? 0), $rows)));
        $batch = $this->indexer->scan_batch_ids($ids, $post_types);
        $state['processed'] = absint($state['processed'] ?? 0) + count($ids);
        $state['indexed'] = absint($state['indexed'] ?? 0) + absint($batch['indexed'] ?? 0);
        $state['excluded'] = absint($state['excluded'] ?? 0) + absint($batch['excluded'] ?? 0);
        $state['skipped'] = $state['excluded'];
        $state['failed'] = absint($state['failed'] ?? 0) + absint($batch['failed'] ?? 0);
        $state['cursor_id'] = absint(end($ids));
        $state['last_post_id'] = $state['cursor_id'];
        $state['updated_at'] = current_time('mysql', true);
        $state['status'] = 'running';

        foreach ((array) ($batch['outcomes'] ?? []) as $outcome) {
            $this->record_scan_item((string) $state['scan_id'], $outcome);
            $type = sanitize_key((string) ($outcome['post_type'] ?? 'unknown')) ?: 'unknown';
            $result = sanitize_key((string) ($outcome['outcome'] ?? 'failed')) ?: 'failed';
            $state['per_type'][$type] ??= ['processed' => 0, 'indexed' => 0, 'excluded' => 0, 'failed' => 0];
            $state['per_type'][$type]['processed']++;
            if (isset($state['per_type'][$type][$result])) {
                $state['per_type'][$type][$result]++;
            }
            if ($result === 'excluded') {
                $reason = sanitize_key((string) ($outcome['reason'] ?? 'excluded')) ?: 'excluded';
                $state['exclusion_reasons'][$reason] = absint($state['exclusion_reasons'][$reason] ?? 0) + 1;
                if (count($state['exclusion_samples']) < 50) {
                    $state['exclusion_samples'][] = $outcome;
                }
            } elseif ($result === 'failed' && count($state['failures']) < 100) {
                $state['failures'][] = $outcome;
            }
        }

        $state['accounted'] = absint($state['indexed']) + absint($state['excluded']) + absint($state['failed']);
        $state['accounting_ok'] = absint($state['processed']) === absint($state['accounted']);
        $this->save_state($state);

        if (count($rows) < $batch_size) {
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
            $this->append_log('scan_paused', ['scan_id' => $state['scan_id'], 'cursor_id' => absint($state['cursor_id'] ?? 0)]);
        }
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_resume(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $state = $this->get_state();
        if (!$state) {
            return new WP_Error('sc_library_scanner_no_scan', __('No saved scan is available.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        if (!in_array((string) ($state['status'] ?? ''), ['complete', 'complete_with_errors', 'cancelled'], true)) {
            $state['status'] = 'running';
            $state['updated_at'] = current_time('mysql', true);
            $this->save_state($state);
            $this->append_log('scan_resumed', ['scan_id' => $state['scan_id'], 'cursor_id' => absint($state['cursor_id'] ?? 0)]);
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
            'cursor_id' => absint($state['cursor_id'] ?? 0),
        ]);
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state($state)]);
    }

    public function rest_reset(WP_REST_Request $request): WP_REST_Response {
        $previous = $this->get_state();
        delete_option(self::STATE_OPTION);
        add_option(self::STATE_OPTION, [], '', false);
        $this->append_log('scanner_state_reset', ['previous_scan_id' => (string) ($previous['scan_id'] ?? '')]);
        return rest_ensure_response(['ok' => true, 'state' => $this->public_state([]), 'diagnostics' => $this->diagnostics()]);
    }

    public function rest_repair(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $repair = sanitize_key((string) $request->get_param('repair'));
        $result = ['repair' => $repair];
        if ($repair === 'schema') {
            SC_Library_Activator::repair_schema();
            $result['schema_repaired'] = true;
            $result['scan_items_table'] = $this->scan_items_table_exists();
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
            $ids = $this->invalid_or_outdated_ids(500);
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
        $state = $this->get_state();
        $payload = [
            'schema' => self::REPORT_SCHEMA,
            'generated_at' => gmdate('c'),
            'site_url' => home_url('/'),
            'plugin_version' => SC_LIBRARY_VERSION,
            'state' => $this->public_state($state),
            'diagnostics' => $this->diagnostics(),
            'scan_items' => $this->scan_items((string) ($state['scan_id'] ?? '')),
            'logs' => $this->get_logs(),
        ];
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sc-library-index-scan-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function finish_scan(array $state): void {
        $post_types = $this->indexer->normalize_post_types((array) ($state['post_types'] ?? []));
        $state['purged'] = absint($state['purged'] ?? 0) + $this->indexer->purge_stale_records($post_types);
        $state['relationships_purged'] = absint($state['relationships_purged'] ?? 0) + $this->relationships->purge_stale();
        $state['accounted'] = absint($state['indexed'] ?? 0) + absint($state['excluded'] ?? 0) + absint($state['failed'] ?? 0);
        $state['accounting_ok'] = absint($state['processed'] ?? 0) === absint($state['accounted']);
        $current_discovered = $this->indexer->scan_published_count($post_types);
        $state['discovered_at_finish'] = $current_discovered;
        $state['inventory_changed'] = absint($state['discovered_at_start'] ?? 0) !== $current_discovered;
        $state['updated_at'] = current_time('mysql', true);
        $state['completed_at'] = $state['updated_at'];

        if (!$state['accounting_ok']) {
            $state['status'] = 'incomplete';
        } elseif (absint($state['failed'] ?? 0) > 0) {
            $state['status'] = 'complete_with_errors';
        } else {
            $state['status'] = 'complete';
        }
        $this->save_state($state);

        if (($state['mode'] ?? '') === 'full' && $state['status'] === 'complete') {
            update_option('sc_library_last_full_index', current_time('mysql', true));
        }
        update_option('sc_library_last_scan_summary', $this->public_state($state), false);
        $this->append_log('scan_completed', $this->public_state($state));
    }

    private function diagnostics(?array $post_types = null): array {
        global $wpdb;
        $post_types = $this->indexer->normalize_post_types($post_types ?: $this->indexer->configured_post_types());
        $discoverable = $this->indexer->discoverable_post_types();
        $recommended = $this->indexer->recommended_post_types();
        $published_by_type = $this->indexer->scan_published_counts_by_type($discoverable);
        $database_inventory = $this->indexer->database_published_post_type_counts(false);
        $all_database_inventory = $this->indexer->database_published_post_type_counts(true);
        $global_editorial_published = array_sum($database_inventory);
        $all_database_published = array_sum($all_database_inventory);
        $standard_posts_published = absint($all_database_inventory['post'] ?? 0);
        $table = $this->indexer->table_name();
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $raw = $this->indexer->scan_published_count($post_types);
        $eligible = $this->indexer->scan_count_for_mode($post_types, 'full', true);
        $indexed = 0;
        $global_indexed = 0;
        $indexed_by_type = [];
        $latest_by_type = [];
        if ($table_exists) {
            $global_indexed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        if ($table_exists && $post_types) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT post_type, COUNT(*) AS total, MAX(indexed_at) AS latest FROM {$table} WHERE post_type IN ({$placeholders}) GROUP BY post_type",
                ...$post_types
            ), ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $type = sanitize_key((string) $row['post_type']);
                $indexed_by_type[$type] = absint($row['total'] ?? 0);
                $latest_by_type[$type] = (string) ($row['latest'] ?? '');
                $indexed += absint($row['total'] ?? 0);
            }
        }

        $missing = $table_exists ? $this->indexer->scan_count_for_mode($post_types, 'missing', true) : $eligible;
        $outdated = $table_exists ? $this->indexer->scan_count_for_mode($post_types, 'outdated', true) : 0;
        [$stale_count, $stale_ids] = $this->stale_diagnostics($post_types, 8);
        [$invalid_count, $invalid_ids] = $this->invalid_diagnostics(8);
        $duplicate_count = $table_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT post_id FROM {$table} GROUP BY post_id HAVING COUNT(*) > 1) duplicates") : 0;

        $stats = [];
        foreach ($discoverable as $post_type) {
            $object = get_post_type_object($post_type);
            $discovered_count = absint($published_by_type[$post_type] ?? 0);
            $eligible_count = $this->indexer->scan_count_for_mode([$post_type], 'full', true);
            $stats[] = [
                'post_type' => $post_type,
                'label' => $object ? $object->labels->name : $post_type,
                'configured' => in_array($post_type, $post_types, true),
                'recommended' => in_array($post_type, $recommended, true),
                'database_only' => !post_type_exists($post_type),
                'discovered' => $discovered_count,
                'eligible' => $eligible_count,
                'excluded' => max(0, $discovered_count - $eligible_count),
                'indexed' => absint($indexed_by_type[$post_type] ?? 0),
                'missing' => $table_exists ? $this->indexer->scan_count_for_mode([$post_type], 'missing', true) : $eligible_count,
                'outdated' => $table_exists ? $this->indexer->scan_count_for_mode([$post_type], 'outdated', true) : 0,
                'latest_indexed_at' => (string) ($latest_by_type[$post_type] ?? ''),
            ];
        }

        $fulltext = false;
        if ($table_exists) {
            foreach (($wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A) ?: []) as $index) {
                if (($index['Key_name'] ?? '') === 'sc_library_search' && strtoupper((string) ($index['Index_type'] ?? '')) === 'FULLTEXT') {
                    $fulltext = true;
                    break;
                }
            }
        }

        $unconfigured = [];
        foreach ($stats as $row) {
            if (!$row['configured'] && $row['recommended'] && $row['discovered'] > 0) {
                $unconfigured[] = $row;
            }
        }
        $state = $this->public_state($this->get_state());
        return [
            'table_exists' => $table_exists,
            'scan_items_table_exists' => $this->scan_items_table_exists(),
            'fulltext_index' => $fulltext,
            'daily_reconcile_scheduled' => (bool) wp_next_scheduled('sc_library_daily_reconcile'),
            'configured_post_types' => $post_types,
            'discoverable_post_types' => $discoverable,
            'recommended_post_types' => $recommended,
            'published_candidates' => $raw,
            'selected_published' => $raw,
            'discovered_published' => $global_editorial_published,
            'global_editorial_published' => $global_editorial_published,
            'all_database_published' => $all_database_published,
            'standard_posts_published' => $standard_posts_published,
            'eligible_records' => $eligible,
            'excluded_records' => max(0, $raw - $eligible),
            'indexed_records' => $global_indexed,
            'selected_indexed_records' => $indexed,
            'missing_records' => $missing,
            'outdated_records' => $outdated,
            'stale_records' => $stale_count,
            'duplicate_records' => $duplicate_count,
            'invalid_records' => $invalid_count,
            'failed_records' => absint($state['failed'] ?? 0),
            'relationships' => $this->relationships->count(),
            'last_full_index' => (string) get_option('sc_library_last_full_index', ''),
            'post_types' => $stats,
            'unconfigured_recommended' => $unconfigured,
            'missing_sample' => $this->record_sample(array_column($this->indexer->scan_fetch_batch($post_types, 0, 8, 'missing'), 'ID')),
            'outdated_sample' => $this->record_sample(array_column($this->indexer->scan_fetch_batch($post_types, 0, 8, 'outdated'), 'ID')),
            'stale_sample' => $this->record_sample($stale_ids),
            'invalid_sample' => $this->record_sample($invalid_ids),
        ];
    }

    private function available_post_types(): array {
        $configured = $this->indexer->configured_post_types();
        $recommended = $this->indexer->recommended_post_types();
        $counts = $this->indexer->scan_published_counts_by_type($this->indexer->discoverable_post_types());
        $result = [];
        foreach ($this->indexer->discoverable_post_types() as $post_type) {
            $object = get_post_type_object($post_type);
            $result[] = [
                'name' => $post_type,
                'label' => $object ? $object->labels->name : $post_type,
                'published' => absint($counts[$post_type] ?? 0),
                'configured' => in_array($post_type, $configured, true),
                'recommended' => in_array($post_type, $recommended, true),
                'default_selected' => in_array($post_type, $configured, true) || in_array($post_type, $recommended, true),
                'database_only' => !post_type_exists($post_type),
            ];
        }
        return $result;
    }

    private function stale_diagnostics(array $post_types, int $sample_limit): array {
        global $wpdb;
        $table = $this->indexer->table_name();
        if (!$this->table_exists($table)) {
            return [0, []];
        }
        $placeholders = $post_types ? implode(',', array_fill(0, count($post_types), '%s')) : "''";
        $sql = "SELECT idx.post_id
                FROM {$table} idx
                LEFT JOIN {$wpdb->posts} p ON p.ID = idx.post_id
                WHERE p.ID IS NULL OR p.post_status <> 'publish'";
        $params = [];
        if ($post_types) {
            $sql .= " OR p.post_type NOT IN ({$placeholders})";
            $params = $post_types;
        }
        $base_ids = $params ? ($wpdb->get_col($wpdb->prepare($sql, ...$params)) ?: []) : ($wpdb->get_col($sql) ?: []);
        $planner_ids = [];
        if (class_exists('SC_Library_Planner')) {
            $planner_ids = $wpdb->get_col($wpdb->prepare("SELECT idx.post_id FROM {$table} idx WHERE idx.post_type = %s", SC_Library_Planner::POST_TYPE)) ?: [];
        }
        foreach ($planner_ids as $post_id) {
            if (!$this->indexer->scan_eligibility(absint($post_id), $post_types)['eligible']) {
                $base_ids[] = absint($post_id);
            }
        }
        $ids = array_values(array_unique(array_map('absint', $base_ids)));
        return [count($ids), array_slice($ids, 0, $sample_limit)];
    }

    private function invalid_diagnostics(int $sample_limit): array {
        global $wpdb;
        $table = $this->indexer->table_name();
        if (!$this->table_exists($table)) {
            return [0, []];
        }
        $where = "record_identifier IS NULL OR record_identifier = '' OR title IS NULL OR title = '' OR permalink IS NULL OR permalink = ''";
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$table} WHERE {$where} ORDER BY post_id ASC LIMIT %d", $sample_limit)) ?: [];
        return [$count, array_map('absint', $ids)];
    }

    private function invalid_or_outdated_ids(int $limit): array {
        global $wpdb;
        $table = $this->indexer->table_name();
        if (!$this->table_exists($table)) {
            return [];
        }
        $invalid_where = "idx.record_identifier IS NULL OR idx.record_identifier = '' OR idx.title IS NULL OR idx.title = '' OR idx.permalink IS NULL OR idx.permalink = ''";
        $modified = "CASE WHEN p.post_modified_gmt IS NULL OR p.post_modified_gmt = '0000-00-00 00:00:00' THEN p.post_modified ELSE p.post_modified_gmt END";
        $sql = "SELECT idx.post_id FROM {$table} idx INNER JOIN {$wpdb->posts} p ON p.ID = idx.post_id
                WHERE ({$invalid_where}) OR idx.indexed_at IS NULL OR idx.indexed_at < {$modified}
                ORDER BY idx.post_id ASC LIMIT %d";
        return array_map('absint', $wpdb->get_col($wpdb->prepare($sql, $limit)) ?: []);
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

    private function scan_items_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_scan_items';
    }

    private function scan_items_table_exists(): bool {
        return $this->table_exists($this->scan_items_table());
    }

    private function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function record_scan_item(string $scan_id, array $outcome): void {
        global $wpdb;
        if (!$this->scan_items_table_exists()) {
            return;
        }
        $post_id = absint($outcome['post_id'] ?? 0);
        $wpdb->replace($this->scan_items_table(), [
            'scan_id' => sanitize_text_field($scan_id),
            'post_id' => $post_id,
            'post_type' => sanitize_key((string) ($outcome['post_type'] ?? '')),
            'outcome' => sanitize_key((string) ($outcome['outcome'] ?? 'failed')),
            'reason' => sanitize_text_field((string) ($outcome['reason'] ?? '')),
            'title' => wp_strip_all_tags((string) get_the_title($post_id)),
            'processed_at' => current_time('mysql', true),
        ], ['%s', '%d', '%s', '%s', '%s', '%s', '%s']);
    }

    private function scan_items(string $scan_id): array {
        global $wpdb;
        if ($scan_id === '' || !$this->scan_items_table_exists()) {
            return [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, post_type, outcome, reason, title, processed_at FROM {$this->scan_items_table()} WHERE scan_id = %s ORDER BY post_id ASC",
            $scan_id
        ), ARRAY_A) ?: [];
    }

    private function cleanup_old_scan_items(): void {
        global $wpdb;
        if (!$this->scan_items_table_exists()) {
            return;
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->scan_items_table()} WHERE processed_at < %s", $cutoff));
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
                'schema' => self::STATE_SCHEMA,
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
                'indexed' => 0,
                'excluded' => 0,
                'skipped' => 0,
                'failed' => 0,
                'purged' => 0,
                'relationships_purged' => 0,
                'accounted' => 0,
                'accounting_ok' => true,
                'progress' => 0,
            ];
        }
        unset($state['internal_debug']);
        $total = max(0, absint($state['total'] ?? 0));
        $processed = absint($state['processed'] ?? 0);
        $state['progress'] = $total > 0 ? round((min($processed, $total) / $total) * 100, 1) : (str_starts_with((string) ($state['status'] ?? ''), 'complete') ? 100 : 0);
        return $state;
    }

    private function append_log(string $event, array $context = []): void {
        $logs = $this->get_logs();
        array_unshift($logs, ['event' => sanitize_key($event), 'created_at' => current_time('mysql', true), 'context' => $context]);
        update_option(self::LOG_OPTION, array_slice($logs, 0, 50), false);
    }

    private function get_logs(): array {
        $logs = get_option(self::LOG_OPTION, []);
        return is_array($logs) ? $logs : [];
    }
}
