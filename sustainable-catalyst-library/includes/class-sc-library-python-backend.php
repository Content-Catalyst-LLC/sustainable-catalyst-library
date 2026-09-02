<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.6.0 Python Research Intelligence Backend bridge.
 *
 * WordPress remains authoritative for users, editorial state, and public URLs.
 * The Python service receives bounded server-to-server index packets only.
 * v5.5.1 hardens bulk ingestion with payload-aware adaptive batching.
 * v5.5.2 exposes signed operations/recovery helpers used by the operations console.
 */
final class SC_Library_Python_Backend {
    public const VERSION = '5.6.0';
    public const BACKEND_SCHEMA = 'sc-library-backend-ingest/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const CRON_HOOK = 'sc_library_python_backend_sync_post';
    private const DEFAULT_BATCH_RECORDS = 25;
    private const DEFAULT_TARGET_PAYLOAD_MB = 6;
    private const DEFAULT_RETRY_ATTEMPTS = 2;
    private const MAX_ERROR_DETAILS = 20;

    public function register_hooks(): void {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('save_post', [$this, 'queue_post_sync'], 30, 3);
        add_action('transition_post_status', [$this, 'status_transition'], 30, 3);
        add_action('before_delete_post', [$this, 'delete_remote_post']);
        add_action(self::CRON_HOOK, [$this, 'sync_post'], 10, 1);
        add_action('admin_post_sc_library_backend_sync_all', [$this, 'handle_sync_all']);
        add_action('admin_post_sc_library_backend_resume_sync', [$this, 'handle_resume_sync']);
    }

    public function register_settings(): void {
        register_setting('sc_library_backend_settings', 'sc_library_backend_url', [
            'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '',
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_api_key', [
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_auto_index', [
            'type' => 'boolean', 'sanitize_callback' => static fn($v) => $v ? 1 : 0, 'default' => 1,
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_timeout', [
            'type' => 'integer', 'sanitize_callback' => static fn($v) => min(30, max(2, absint($v))), 'default' => 8,
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_batch_records', [
            'type' => 'integer', 'sanitize_callback' => static fn($v) => min(100, max(5, absint($v))), 'default' => self::DEFAULT_BATCH_RECORDS,
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_target_payload_mb', [
            'type' => 'integer', 'sanitize_callback' => static fn($v) => min(20, max(1, absint($v))), 'default' => self::DEFAULT_TARGET_PAYLOAD_MB,
        ]);
        register_setting('sc_library_backend_settings', 'sc_library_backend_retry_attempts', [
            'type' => 'integer', 'sanitize_callback' => static fn($v) => min(3, max(0, absint($v))), 'default' => self::DEFAULT_RETRY_ATTEMPTS,
        ]);
    }

    public static function configured(): bool {
        return '' !== trim((string) get_option('sc_library_backend_url', ''))
            && '' !== trim((string) get_option('sc_library_backend_api_key', ''));
    }

    public static function base_url(): string {
        return untrailingslashit((string) get_option('sc_library_backend_url', ''));
    }

    private static function timeout(): int {
        return min(30, max(2, (int) get_option('sc_library_backend_timeout', 8)));
    }

    private static function batch_records(): int {
        return min(100, max(5, (int) get_option('sc_library_backend_batch_records', self::DEFAULT_BATCH_RECORDS)));
    }

    private static function target_payload_bytes(): int {
        $mb = min(20, max(1, (int) get_option('sc_library_backend_target_payload_mb', self::DEFAULT_TARGET_PAYLOAD_MB)));
        return $mb * 1024 * 1024;
    }

    private static function retry_attempts(): int {
        return min(3, max(0, (int) get_option('sc_library_backend_retry_attempts', self::DEFAULT_RETRY_ATTEMPTS)));
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Python Backend', 'sustainable-catalyst-library'),
            __('Python Backend', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-python-backend',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) { return; }
        $health = self::health();
        $last = get_option('sc_library_backend_last_sync', []);
        $checkpoint = get_option('sc_library_backend_sync_checkpoint', []);
        $has_failures = is_array($checkpoint) && !empty($checkpoint['failed_record_ids']);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Library Python Research Intelligence Backend', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('WordPress remains the editorial and identity authority. The Python service provides durable indexing, search, provenance, graph relationships, and record-history intelligence.', 'sustainable-catalyst-library'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('sc_library_backend_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="sc_library_backend_url"><?php esc_html_e('Backend URL', 'sustainable-catalyst-library'); ?></label></th><td><input class="regular-text code" id="sc_library_backend_url" name="sc_library_backend_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_backend_url', '')); ?>" placeholder="https://library-api.sustainablecatalyst.com"></td></tr>
                    <tr><th scope="row"><label for="sc_library_backend_api_key"><?php esc_html_e('Server API key', 'sustainable-catalyst-library'); ?></label></th><td><input class="regular-text code" id="sc_library_backend_api_key" name="sc_library_backend_api_key" type="password" autocomplete="new-password" value="<?php echo esc_attr((string) get_option('sc_library_backend_api_key', '')); ?>"><p class="description"><?php esc_html_e('Stored server-side in WordPress and never exposed to the browser.', 'sustainable-catalyst-library'); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Automatic indexing', 'sustainable-catalyst-library'); ?></th><td><label><input name="sc_library_backend_auto_index" type="checkbox" value="1" <?php checked((int) get_option('sc_library_backend_auto_index', 1), 1); ?>> <?php esc_html_e('Queue published Library records after WordPress saves.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th scope="row"><label for="sc_library_backend_timeout"><?php esc_html_e('Request timeout', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc_library_backend_timeout" name="sc_library_backend_timeout" type="number" min="2" max="30" value="<?php echo esc_attr((int) get_option('sc_library_backend_timeout', 8)); ?>"> seconds</td></tr>
                    <tr><th scope="row"><label for="sc_library_backend_batch_records"><?php esc_html_e('Bulk target records', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc_library_backend_batch_records" name="sc_library_backend_batch_records" type="number" min="5" max="100" value="<?php echo esc_attr(self::batch_records()); ?>"><p class="description"><?php esc_html_e('Maximum records in a client-side leaf batch. Payload bytes can force a smaller batch automatically.', 'sustainable-catalyst-library'); ?></p></td></tr>
                    <tr><th scope="row"><label for="sc_library_backend_target_payload_mb"><?php esc_html_e('Bulk target payload', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc_library_backend_target_payload_mb" name="sc_library_backend_target_payload_mb" type="number" min="1" max="20" value="<?php echo esc_attr((int) (self::target_payload_bytes() / 1024 / 1024)); ?>"> MB<p class="description"><?php esc_html_e('WordPress preflights encoded JSON and splits batches before they approach the backend request ceiling.', 'sustainable-catalyst-library'); ?></p></td></tr>
                    <tr><th scope="row"><label for="sc_library_backend_retry_attempts"><?php esc_html_e('Transient retries', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc_library_backend_retry_attempts" name="sc_library_backend_retry_attempts" type="number" min="0" max="3" value="<?php echo esc_attr(self::retry_attempts()); ?>"><p class="description"><?php esc_html_e('Retries are limited to network failures and retryable HTTP statuses. HTTP 413 is split instead of retried.', 'sustainable-catalyst-library'); ?></p></td></tr>
                </table>
                <?php submit_button(__('Save backend settings', 'sustainable-catalyst-library')); ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Service state', 'sustainable-catalyst-library'); ?></h2>
            <pre style="max-width:1100px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;"><?php echo esc_html(wp_json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            <?php if (self::configured()) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                    <input type="hidden" name="action" value="sc_library_backend_sync_all">
                    <?php wp_nonce_field('sc_library_backend_sync_all'); ?>
                    <?php submit_button(__('Reindex all published Library records', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                </form>
                <?php if ($has_failures) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                        <input type="hidden" name="action" value="sc_library_backend_resume_sync">
                        <?php wp_nonce_field('sc_library_backend_resume_sync'); ?>
                        <?php submit_button(__('Resume failed records', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (is_array($checkpoint) && $checkpoint) : ?><h2><?php esc_html_e('Sync checkpoint', 'sustainable-catalyst-library'); ?></h2><pre><?php echo esc_html(wp_json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?>
            <?php if (is_array($last) && $last) : ?><h2><?php esc_html_e('Last bulk sync', 'sustainable-catalyst-library'); ?></h2><pre><?php echo esc_html(wp_json_encode($last, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?>
        </div>
        <?php
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/backend/status', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => static function () {
                $health = self::health();
                unset($health['database_detail']);
                return rest_ensure_response($health);
            },
        ]);
        register_rest_route(self::REST_NAMESPACE, '/backend/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'proxy_search'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'object_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 20],
                'offset' => ['sanitize_callback' => 'absint', 'default' => 0],
            ],
        ]);
    }

    public static function health(): array {
        if (!self::configured()) {
            return [
                'ok' => false,
                'configured' => false,
                'service' => 'sustainable-catalyst-library-backend',
                'version' => null,
                'state' => 'not_configured',
            ];
        }
        $response = wp_remote_get(self::base_url() . '/health', [
            'timeout' => self::timeout(),
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'configured' => true, 'state' => 'unavailable', 'error' => $response->get_error_message()];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) { $body = []; }
        return [
            'ok' => 200 === (int) wp_remote_retrieve_response_code($response) && !empty($body['ok']),
            'configured' => true,
            'state' => $body['database'] ?? 'unknown',
            'service' => $body['service'] ?? 'sustainable-catalyst-library-backend',
            'version' => $body['version'] ?? null,
            'capabilities' => $body['capabilities'] ?? [],
            'ingest_limits' => $body['ingest_limits'] ?? [],
            'database_detail' => $body['database_detail'] ?? null,
        ];
    }

    public function proxy_search(WP_REST_Request $request) {
        if (!self::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $params = [
            'q' => (string) $request->get_param('q'),
            'limit' => min(100, max(1, (int) $request->get_param('limit'))),
            'offset' => min(100000, max(0, (int) $request->get_param('offset'))),
        ];
        $object_type = sanitize_key((string) $request->get_param('object_type'));
        if ($object_type) { $params['object_type'] = $object_type; }
        $url = add_query_arg($params, self::base_url() . '/v1/search');
        $response = wp_remote_get($url, ['timeout' => self::timeout(), 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_backend_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return new WP_REST_Response(is_array($body) ? $body : ['ok' => false], $code ?: 502);
    }

    public static function post_types(): array {
        $types = get_option('sc_library_post_types', ['post']);
        $types = is_array($types) ? array_values(array_filter(array_map('sanitize_key', $types))) : ['post'];
        return $types ?: ['post'];
    }

    public function queue_post_sync(int $post_id, WP_Post $post, bool $update): void {
        unset($update);
        if (!(bool) get_option('sc_library_backend_auto_index', 1) || !self::configured()) { return; }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !in_array($post->post_type, self::post_types(), true)) { return; }
        if ('publish' !== $post->post_status) { return; }
        if (!wp_next_scheduled(self::CRON_HOOK, [$post_id])) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK, [$post_id]);
        }
    }

    public function status_transition(string $new_status, string $old_status, WP_Post $post): void {
        unset($old_status);
        if (!self::configured() || !in_array($post->post_type, self::post_types(), true)) { return; }
        if ('publish' !== $new_status) { self::delete_remote_post((int) $post->ID); }
    }

    public function delete_remote_post(int $post_id): void {
        if (!self::configured()) { return; }
        $post = get_post($post_id);
        $post_type = $post instanceof WP_Post ? $post->post_type : get_post_type($post_id);
        if (!$post_type) { return; }
        $record_id = self::record_id($post_id, (string) $post_type);
        self::signed_request('DELETE', '/v1/records/' . $record_id, '');
    }

    public function sync_post(int $post_id): void {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || 'publish' !== $post->post_status || !in_array($post->post_type, self::post_types(), true)) { return; }
        $summary = self::new_summary(1, 'automatic');
        self::send_records_resilient([$post], $summary);
        if (!empty($summary['failed'])) {
            error_log('[Sustainable Catalyst Library] Python backend automatic sync failed for post ' . $post_id);
        }
    }

    public function handle_sync_all(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'sustainable-catalyst-library')); }
        check_admin_referer('sc_library_backend_sync_all');
        if (!self::configured()) { wp_safe_redirect(add_query_arg('backend_sync', 'not_configured', admin_url('admin.php?page=sc-library-python-backend'))); exit; }
        $ids = get_posts([
            'post_type' => self::post_types(), 'post_status' => 'publish', 'posts_per_page' => -1,
            'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true,
            'update_post_meta_cache' => false, 'update_post_term_cache' => false,
        ]);
        self::run_bulk_sync(array_map('intval', $ids), 'full');
        wp_safe_redirect(admin_url('admin.php?page=sc-library-python-backend'));
        exit;
    }

    public function handle_resume_sync(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'sustainable-catalyst-library')); }
        check_admin_referer('sc_library_backend_resume_sync');
        if (!self::configured()) { wp_safe_redirect(add_query_arg('backend_sync', 'not_configured', admin_url('admin.php?page=sc-library-python-backend'))); exit; }
        $checkpoint = get_option('sc_library_backend_sync_checkpoint', []);
        $ids = is_array($checkpoint) && !empty($checkpoint['failed_record_ids']) && is_array($checkpoint['failed_record_ids'])
            ? array_values(array_unique(array_map('intval', $checkpoint['failed_record_ids'])))
            : [];
        if ($ids) { self::run_bulk_sync($ids, 'resume'); }
        wp_safe_redirect(admin_url('admin.php?page=sc-library-python-backend'));
        exit;
    }

    private static function new_summary(int $records, string $mode): array {
        return [
            'ok' => true,
            'operation_id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('sc-library-', true),
            'mode' => $mode,
            'records' => $records,
            'completed' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'batches' => 0,
            'requests' => 0,
            'splits' => 0,
            'preflight_splits' => 0,
            'http_413_splits' => 0,
            'retries' => 0,
            'compact_single_record_packets' => 0,
            'payload_bytes_sent' => 0,
            'largest_payload_bytes' => 0,
            'error_count' => 0,
            'errors' => [],
            'failed_record_ids' => [],
            'started_at' => current_time('mysql', true),
        ];
    }

    public static function run_bulk_sync(array $ids, string $mode): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $summary = self::new_summary(count($ids), $mode);
        self::save_checkpoint($summary, 'running');

        // Seed groups bound PHP memory. Each seed is split again by record count and encoded byte size.
        foreach (array_chunk($ids, 100) as $seed_ids) {
            $posts = array_values(array_filter(array_map('get_post', $seed_ids), static fn($p) => $p instanceof WP_Post && 'publish' === $p->post_status));
            $missing = count($seed_ids) - count($posts);
            if ($missing > 0) {
                $summary['failed'] += $missing;
                $summary['error_count'] += $missing;
            }
            self::send_records_resilient($posts, $summary);
            self::save_checkpoint($summary, $summary['failed'] > 0 ? 'partial' : 'running');
        }

        $summary['failed_record_ids'] = array_values(array_unique($summary['failed_record_ids']));
        $summary['failed'] = count($summary['failed_record_ids']) + max(0, $summary['failed'] - count($summary['failed_record_ids']));
        $summary['ok'] = 0 === (int) $summary['failed'];
        $summary['finished_at'] = current_time('mysql', true);
        $summary['timestamp'] = $summary['finished_at'];
        update_option('sc_library_backend_last_sync', $summary, false);
        if ($summary['ok']) { update_option('sc_library_backend_last_successful_sync', $summary, false); }
        self::save_checkpoint($summary, $summary['ok'] ? 'complete' : 'partial');
        return $summary;
    }

    private static function save_checkpoint(array $summary, string $status): void {
        update_option('sc_library_backend_sync_checkpoint', [
            'status' => $status,
            'operation_id' => $summary['operation_id'] ?? '',
            'mode' => $summary['mode'] ?? 'full',
            'records' => (int) ($summary['records'] ?? 0),
            'completed' => (int) ($summary['completed'] ?? 0),
            'failed' => (int) ($summary['failed'] ?? 0),
            'failed_record_ids' => array_values(array_unique(array_map('intval', $summary['failed_record_ids'] ?? []))),
            'requests' => (int) ($summary['requests'] ?? 0),
            'splits' => (int) ($summary['splits'] ?? 0),
            'updated_at' => current_time('mysql', true),
        ], false);
    }

    public static function record_id(int $post_id, string $post_type): string {
        return 'wordpress:' . get_current_blog_id() . ':' . sanitize_key($post_type) . ':' . $post_id;
    }

    private static function term_names(int $post_id, string $taxonomy): array {
        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || !$terms) { return []; }
        return array_values(array_unique(array_map(static fn($t) => sanitize_text_field((string) $t->name), $terms)));
    }

    private static function chunks(string $text): array {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
        if ('' === $text) { return []; }
        $chunks = [];
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        $size = 6000;
        for ($offset = 0, $ordinal = 0; $offset < $length && $ordinal < 200; $offset += $size, $ordinal++) {
            $piece = function_exists('mb_substr') ? mb_substr($text, $offset, $size) : substr($text, $offset, $size);
            $piece = trim((string) $piece);
            if ($piece) { $chunks[] = ['ordinal' => $ordinal, 'heading' => '', 'text' => $piece, 'metadata' => ['chunker' => 'wordpress-text-v1']]; }
        }
        return $chunks;
    }

    private static function packet(WP_Post $post, bool $compact = false): array {
        $body = trim(wp_strip_all_tags(strip_shortcodes((string) $post->post_content), true));
        $excerpt = trim(wp_strip_all_tags((string) $post->post_excerpt, true));
        if ('' === $excerpt && '' !== $body) { $excerpt = wp_trim_words($body, 60, '…'); }
        return [
            'record_id' => self::record_id((int) $post->ID, $post->post_type),
            'source_key' => 'wordpress-main',
            'object_type' => $post->post_type,
            'title' => html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'canonical_url' => get_permalink($post),
            'abstract' => $excerpt,
            'body_text' => $body,
            'language' => substr((string) get_locale(), 0, 2) ?: 'en',
            'visibility' => 'public',
            'publication_status' => 'published',
            'published_at' => get_post_time('c', true, $post),
            'source_updated_at' => get_post_modified_time('c', true, $post),
            'authors' => [(string) get_the_author_meta('display_name', (int) $post->post_author)],
            'topics' => self::term_names((int) $post->ID, 'category'),
            'tags' => self::term_names((int) $post->ID, 'post_tag'),
            'identifiers' => ['wordpress_post_id' => (string) $post->ID],
            'metadata' => [
                'wordpress_post_type' => $post->post_type,
                'wordpress_status' => $post->post_status,
            ],
            'chunks' => $compact ? [] : self::chunks($body),
        ];
    }

    private static function encoded_payload(array $posts, bool $compact = false): array {
        $payload = [
            'schema' => self::BACKEND_SCHEMA,
            'source' => [
                'source_key' => 'wordpress-main',
                'name' => get_bloginfo('name') . ' WordPress Library',
                'source_type' => 'wordpress',
                'canonical_url' => home_url('/knowledge-libraries/'),
                'metadata' => ['site_url' => home_url('/'), 'plugin_version' => SC_LIBRARY_VERSION],
            ],
            'records' => array_map(static fn($post) => self::packet($post, $compact), $posts),
        ];
        $body = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ['body' => $body, 'bytes' => strlen($body), 'compact' => $compact];
    }

    private static function split_posts(array $posts): array {
        $mid = max(1, intdiv(count($posts), 2));
        return [array_slice($posts, 0, $mid), array_slice($posts, $mid)];
    }

    private static function send_records_resilient(array $posts, array &$summary): void {
        if (!$posts) { return; }
        $payload = self::encoded_payload($posts, false);
        $summary['largest_payload_bytes'] = max((int) $summary['largest_payload_bytes'], (int) $payload['bytes']);

        if (count($posts) > 1 && (count($posts) > self::batch_records() || $payload['bytes'] > self::target_payload_bytes())) {
            $summary['splits']++;
            $summary['preflight_splits']++;
            [$left, $right] = self::split_posts($posts);
            self::send_records_resilient($left, $summary);
            self::send_records_resilient($right, $summary);
            return;
        }

        if (1 === count($posts) && $payload['bytes'] > self::target_payload_bytes()) {
            $payload = self::encoded_payload($posts, true);
            $summary['compact_single_record_packets']++;
            $summary['largest_payload_bytes'] = max((int) $summary['largest_payload_bytes'], (int) $payload['bytes']);
        }

        $max_attempts = 1 + self::retry_attempts();
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $summary['requests']++;
            $summary['payload_bytes_sent'] += (int) $payload['bytes'];
            $result = self::signed_request('POST', '/v1/ingest/records', (string) $payload['body']);
            if (!is_wp_error($result)) {
                $received = (int) ($result['received'] ?? count($posts));
                $summary['batches']++;
                $summary['completed'] += $received;
                $summary['changed'] += (int) ($result['changed'] ?? 0);
                $summary['unchanged'] += (int) ($result['unchanged'] ?? max(0, $received - (int) ($result['changed'] ?? 0)));
                return;
            }

            $status = self::error_status($result);
            if (413 === $status && count($posts) > 1) {
                $summary['splits']++;
                $summary['http_413_splits']++;
                [$left, $right] = self::split_posts($posts);
                self::send_records_resilient($left, $summary);
                self::send_records_resilient($right, $summary);
                return;
            }

            if (413 === $status && 1 === count($posts) && empty($payload['compact'])) {
                $payload = self::encoded_payload($posts, true);
                $summary['compact_single_record_packets']++;
                $summary['largest_payload_bytes'] = max((int) $summary['largest_payload_bytes'], (int) $payload['bytes']);
                continue;
            }

            if ($attempt < $max_attempts && self::retryable_error($result)) {
                $summary['retries']++;
                usleep(250000 * $attempt);
                continue;
            }

            self::record_failure($posts, $result, $summary);
            return;
        }
    }

    private static function record_failure(array $posts, WP_Error $error, array &$summary): void {
        $summary['ok'] = false;
        $summary['failed'] += count($posts);
        $summary['error_count']++;
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) { $summary['failed_record_ids'][] = (int) $post->ID; }
        }
        if (count($summary['errors']) < self::MAX_ERROR_DETAILS) {
            $status = self::error_status($error);
            $ids = array_map(static fn($post) => $post instanceof WP_Post ? (int) $post->ID : 0, $posts);
            $summary['errors'][] = [
                'message' => $error->get_error_message(),
                'status' => $status ?: null,
                'records' => array_values(array_filter($ids)),
            ];
        }
    }

    private static function error_status(WP_Error $error): int {
        $data = $error->get_error_data();
        return is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;
    }

    private static function retryable_error(WP_Error $error): bool {
        $status = self::error_status($error);
        if (0 === $status) { return true; }
        return in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
    }

    private static function send_records(array $posts) {
        if (!$posts) { return ['ok' => true, 'received' => 0, 'changed' => 0, 'unchanged' => 0]; }
        $payload = self::encoded_payload($posts, false);
        return self::signed_request('POST', '/v1/ingest/records', (string) $payload['body']);
    }

    public static function signed_request(string $method, string $path, string $body) {
        $timestamp = (string) time();
        $key = (string) get_option('sc_library_backend_api_key', '');
        $base = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $base, $key);
        $args = [
            'method' => strtoupper($method),
            'timeout' => self::timeout(),
            'redirection' => 0,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'X-SC-Timestamp' => $timestamp,
                'X-SC-Signature' => $signature,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $body,
        ];
        $response = wp_remote_request(self::base_url() . $path, $args);
        if (is_wp_error($response)) { return $response; }
        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('sc_library_backend_http_error', sprintf(__('Library backend returned HTTP %d.', 'sustainable-catalyst-library'), $code), [
                'status' => $code,
                'body' => $decoded,
                'max_body_bytes' => (int) wp_remote_retrieve_header($response, 'x-sc-max-body-bytes'),
                'max_batch_records' => (int) wp_remote_retrieve_header($response, 'x-sc-max-batch-records'),
            ]);
        }
        return is_array($decoded) ? $decoded : ['ok' => true];
    }
}
