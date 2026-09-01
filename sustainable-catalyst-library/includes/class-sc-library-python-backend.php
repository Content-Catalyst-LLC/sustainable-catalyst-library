<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.5.0 Python Research Intelligence Backend bridge.
 *
 * WordPress remains authoritative for users, editorial state, and public URLs.
 * The Python service receives bounded server-to-server index packets only.
 */
final class SC_Library_Python_Backend {
    public const VERSION = '5.5.0';
    public const BACKEND_SCHEMA = 'sc-library-backend-ingest/1.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const CRON_HOOK = 'sc_library_python_backend_sync_post';

    public function register_hooks(): void {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('save_post', [$this, 'queue_post_sync'], 30, 3);
        add_action('transition_post_status', [$this, 'status_transition'], 30, 3);
        add_action('before_delete_post', [$this, 'delete_remote_post']);
        add_action(self::CRON_HOOK, [$this, 'sync_post'], 10, 1);
        add_action('admin_post_sc_library_backend_sync_all', [$this, 'handle_sync_all']);
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
                </table>
                <?php submit_button(__('Save backend settings', 'sustainable-catalyst-library')); ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Service state', 'sustainable-catalyst-library'); ?></h2>
            <pre style="max-width:1100px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;"><?php echo esc_html(wp_json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            <?php if (self::configured()) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_backend_sync_all">
                    <?php wp_nonce_field('sc_library_backend_sync_all'); ?>
                    <?php submit_button(__('Reindex all published Library records', 'sustainable-catalyst-library'), 'secondary'); ?>
                </form>
            <?php endif; ?>
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

    private static function post_types(): array {
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
        self::send_records([$post]);
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
        $summary = ['ok' => true, 'records' => count($ids), 'batches' => 0, 'changed' => 0, 'errors' => []];
        foreach (array_chunk(array_map('intval', $ids), 100) as $chunk) {
            $posts = array_values(array_filter(array_map('get_post', $chunk), static fn($p) => $p instanceof WP_Post));
            $result = self::send_records($posts);
            $summary['batches']++;
            if (is_wp_error($result)) { $summary['ok'] = false; $summary['errors'][] = $result->get_error_message(); continue; }
            $summary['changed'] += (int) ($result['changed'] ?? 0);
        }
        $summary['timestamp'] = current_time('mysql', true);
        update_option('sc_library_backend_last_sync', $summary, false);
        wp_safe_redirect(admin_url('admin.php?page=sc-library-python-backend'));
        exit;
    }

    private static function record_id(int $post_id, string $post_type): string {
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

    private static function packet(WP_Post $post): array {
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
            'metadata' => ['wordpress_post_type' => $post->post_type, 'wordpress_status' => $post->post_status],
            'chunks' => self::chunks($body),
        ];
    }

    private static function send_records(array $posts) {
        if (!$posts) { return ['ok' => true, 'received' => 0, 'changed' => 0]; }
        $payload = [
            'schema' => self::BACKEND_SCHEMA,
            'source' => [
                'source_key' => 'wordpress-main',
                'name' => get_bloginfo('name') . ' WordPress Library',
                'source_type' => 'wordpress',
                'canonical_url' => home_url('/knowledge-libraries/'),
                'metadata' => ['site_url' => home_url('/'), 'plugin_version' => SC_LIBRARY_VERSION],
            ],
            'records' => array_map([self::class, 'packet'], $posts),
        ];
        $body = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return self::signed_request('POST', '/v1/ingest/records', $body);
    }

    private static function signed_request(string $method, string $path, string $body) {
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
            return new WP_Error('sc_library_backend_http_error', sprintf(__('Library backend returned HTTP %d.', 'sustainable-catalyst-library'), $code), ['status' => $code, 'body' => $decoded]);
        }
        return is_array($decoded) ? $decoded : ['ok' => true];
    }
}
