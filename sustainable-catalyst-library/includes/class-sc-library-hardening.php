<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Accessibility, mobile, performance, security, and production-readiness layer.
 *
 * The class deliberately keeps hardening reversible: WordPress remains canonical,
 * public caching is limited to an explicit route allowlist, and diagnostics never
 * expose secrets or private workspace data.
 */
final class SC_Library_Hardening {
    public const SCHEMA = 'sc-library-production-readiness/1.0';
    private const CACHE_GENERATION_OPTION = 'sc_library_public_cache_generation';
    private const LAST_REPORT_OPTION = 'sc_library_readiness_last_report';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;
    private array $cache_candidates = [];

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 90);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets'], 999);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_library_readiness_status', [$this, 'render_public_status']);

        add_filter('rest_pre_dispatch', [$this, 'rest_pre_dispatch'], 8, 3);
        add_filter('rest_post_dispatch', [$this, 'rest_post_dispatch'], 90, 3);
        add_filter('wp_headers', [$this, 'security_headers']);

        add_action('save_post', [$this, 'invalidate_public_cache'], 120);
        add_action('deleted_post', [$this, 'invalidate_public_cache'], 120);
        add_action('trashed_post', [$this, 'invalidate_public_cache'], 120);
        add_action('untrashed_post', [$this, 'invalidate_public_cache'], 120);
        add_action('set_object_terms', [$this, 'invalidate_public_cache'], 120);
        add_action('edited_term', [$this, 'invalidate_public_cache'], 120);
        add_action('delete_term', [$this, 'invalidate_public_cache'], 120);
        add_action('sc_library_hardening_daily', [$this, 'daily_maintenance']);

        add_action('admin_post_sc_library_readiness_run', [$this, 'handle_run_report']);
        add_action('admin_post_sc_library_readiness_clear_cache', [$this, 'handle_clear_cache']);
        add_action('admin_post_sc_library_readiness_repair_cron', [$this, 'handle_repair_cron']);
    }

    public static function runs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_readiness_runs';
    }

    public function register_settings(): void {
        register_setting('sc_library_hardening_settings', 'sc_library_enable_hardening', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_hardening_settings', 'sc_library_enable_public_cache', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_hardening_settings', 'sc_library_public_cache_ttl', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(3600, max(30, absint($value))),
            'default' => 300,
        ]);
        register_setting('sc_library_hardening_settings', 'sc_library_public_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(5000, max(0, absint($value))),
            'default' => 240,
        ]);
        register_setting('sc_library_hardening_settings', 'sc_library_readiness_page_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/library-status/'),
        ]);
        register_setting('sc_library_hardening_settings', 'sc_library_touch_target_px', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(56, max(40, absint($value))),
            'default' => 44,
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Production Readiness', 'sustainable-catalyst-library'),
            __('Production Readiness', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-readiness',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'sc-library_page_sc-library-readiness') {
            return;
        }
        wp_enqueue_style('sc-library-hardening', SC_LIBRARY_URL . 'assets/css/sc-library-hardening.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-hardening', SC_LIBRARY_URL . 'assets/js/sc-library-hardening.js', [], SC_LIBRARY_VERSION, true);
    }

    public function frontend_assets(): void {
        if (!(bool) get_option('sc_library_enable_hardening', 1)) {
            return;
        }

        $handles = [
            'sc-library', 'sc-library-notebook', 'sc-library-boards', 'sc-library-annotations',
            'sc-library-books', 'sc-library-documents', 'sc-library-documentation',
            'sc-library-foundation-documents', 'sc-library-multimedia', 'sc-library-planner',
            'sc-library-planning-analytics', 'sc-library-workspace-sync', 'sc-library-collaboration',
            'sc-library-knowledge-graph', 'sc-library-orchestrator', 'sc-library-developer-api',
            'sc-library-preservation', 'sc-library-unified-system',
        ];
        $active = false;
        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'enqueued') || wp_script_is($handle, 'enqueued')) {
                $active = true;
                break;
            }
        }

        if (!$active && is_singular()) {
            $post = get_post();
            $content = $post instanceof WP_Post ? (string) $post->post_content : '';
            foreach (['sc_library', 'sc_library_notebook', 'sc_library_knowledge_graph', 'sc_library_orchestrator', 'sc_foundation_document', 'sc_library_institutional_archive', 'sc_library_developer_portal', 'sc_library_readiness_status', 'sc_library_living_system', 'sc_library_unified_workspace', 'sc_library_system_status'] as $shortcode) {
                if ($content !== '' && has_shortcode($content, $shortcode)) {
                    $active = true;
                    break;
                }
            }
        }

        if (!$active) {
            return;
        }

        wp_enqueue_style('sc-library-hardening', SC_LIBRARY_URL . 'assets/css/sc-library-hardening.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-hardening', SC_LIBRARY_URL . 'assets/js/sc-library-hardening.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-hardening', 'SCLibraryHardening', [
            'touchTarget' => (int) get_option('sc_library_touch_target_px', 44),
            'labels' => [
                'skip' => __('Skip to Library content', 'sustainable-catalyst-library'),
                'table' => __('Scrollable data table', 'sustainable-catalyst-library'),
                'updated' => __('Library content updated.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function security_headers(array $headers): array {
        if (!(bool) get_option('sc_library_enable_hardening', 1)) {
            return $headers;
        }
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
        return $headers;
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';
        register_rest_route($namespace, '/library/readiness/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_public_status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/readiness/report', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_admin_report'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route($namespace, '/library/readiness/run', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_run_report'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sustainable-catalyst-library/v1', '/readiness', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_public_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_public_status(): WP_REST_Response {
        $report = $this->latest_report();
        if (!$report) {
            $report = $this->run_report(false);
        }
        return rest_ensure_response($this->public_report($report));
    }

    public function rest_admin_report(): WP_REST_Response {
        return rest_ensure_response($this->latest_report() ?: $this->run_report(false));
    }

    public function rest_run_report(): WP_REST_Response {
        return rest_ensure_response($this->run_report(true));
    }

    public function render_public_status(array $atts = []): string {
        $atts = shortcode_atts(['show_categories' => 'true'], $atts, 'sc_library_readiness_status');
        $report = $this->latest_report() ?: $this->run_report(false);
        $public = $this->public_report($report);
        $status = sanitize_key((string) ($public['overall_status'] ?? 'unknown'));
        ob_start();
        ?>
        <section class="sc-library-readiness-public" aria-labelledby="sc-library-readiness-title">
            <header>
                <p class="sc-library-readiness-public__eyebrow"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
                <h2 id="sc-library-readiness-title"><?php esc_html_e('Production Status', 'sustainable-catalyst-library'); ?></h2>
                <span class="sc-library-readiness-badge sc-library-readiness-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?></span>
                <p><?php echo esc_html(sprintf(__('Last evaluated %s.', 'sustainable-catalyst-library'), (string) ($public['generated_at'] ?? ''))); ?></p>
            </header>
            <?php if (filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN)) : ?>
                <div class="sc-library-readiness-public__grid">
                    <?php foreach (($public['categories'] ?? []) as $key => $category) : ?>
                        <article>
                            <h3><?php echo esc_html((string) ($category['label'] ?? ucfirst((string) $key))); ?></h3>
                            <p><?php echo esc_html(sprintf(__('%1$d ready · %2$d warnings · %3$d failures', 'sustainable-catalyst-library'), (int) ($category['ready'] ?? 0), (int) ($category['warning'] ?? 0), (int) ($category['fail'] ?? 0))); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function rest_pre_dispatch($result, WP_REST_Server $server, WP_REST_Request $request) {
        if ($result !== null || !$this->is_public_request($request)) {
            return $result;
        }

        $limit = (int) get_option('sc_library_public_rate_limit', 240);
        if ($limit > 0 && !$this->within_rate_limit($request, $limit)) {
            return new WP_Error('sc_library_rate_limited', __('Too many Library requests. Please try again shortly.', 'sustainable-catalyst-library'), [
                'status' => 429,
                'retry_after' => 300,
            ]);
        }

        if (!(bool) get_option('sc_library_enable_public_cache', 1) || !$this->cacheable_route($request->get_route())) {
            return $result;
        }

        $key = $this->cache_key($request);
        $this->cache_candidates[$key] = true;
        $cached = get_transient($key);
        if (!is_array($cached) || !array_key_exists('data', $cached)) {
            return $result;
        }

        $response = new WP_REST_Response($cached['data'], (int) ($cached['status'] ?? 200), (array) ($cached['headers'] ?? []));
        $response->header('X-SC-Library-Cache', 'HIT');
        return $response;
    }

    public function rest_post_dispatch($result, WP_REST_Server $server, WP_REST_Request $request) {
        if (is_wp_error($result) || !$this->is_public_request($request) || !$this->cacheable_route($request->get_route())) {
            return $result;
        }
        $response = rest_ensure_response($result);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!(bool) get_option('sc_library_enable_public_cache', 1) || $response->get_status() !== 200) {
            return $response;
        }
        $key = $this->cache_key($request);
        if (!isset($this->cache_candidates[$key])) {
            return $response;
        }
        $ttl = (int) get_option('sc_library_public_cache_ttl', 300);
        set_transient($key, [
            'data' => $response->get_data(),
            'status' => $response->get_status(),
            'headers' => $response->get_headers(),
        ], $ttl);
        $response->header('X-SC-Library-Cache', 'MISS');
        $response->header('Cache-Control', 'public, max-age=' . $ttl);
        return $response;
    }

    private function is_public_request(WP_REST_Request $request): bool {
        if (strtoupper($request->get_method()) !== 'GET' || is_user_logged_in()) {
            return false;
        }
        if ($request->get_header('authorization') !== '' || $request->get_header('x-sc-library-key') !== '' || $request->get_header('x-wp-nonce') !== '') {
            return false;
        }
        return str_starts_with($request->get_route(), '/sustainable-catalyst/v1/library/')
            || str_starts_with($request->get_route(), '/sustainable-catalyst-library/v1/');
    }

    private function cacheable_route(string $route): bool {
        $blocked = ['protected', 'diagnostics', 'report', '/run', 'sessions', 'events', 'jobs', 'migration', 'extract', 'sync', 'reindex', 'webhooks'];
        foreach ($blocked as $fragment) {
            if (str_contains($route, $fragment)) {
                return false;
            }
        }
        return (bool) preg_match('#/(items|records|categories|series|concepts|pathways|documentation|foundation-documents|graph|roadmap|media/reels|archive|readiness|status)(/|$)#', $route);
    }

    private function cache_key(WP_REST_Request $request): string {
        $generation = (int) get_option(self::CACHE_GENERATION_OPTION, 1);
        $params = $request->get_query_params();
        ksort($params);
        return 'sc_lib_cache_' . md5($generation . '|' . $request->get_route() . '|' . wp_json_encode($params));
    }

    private function within_rate_limit(WP_REST_Request $request, int $limit): bool {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $bucket = (int) floor(time() / 300);
        $key = 'sc_lib_rate_' . hash_hmac('sha256', $ip . '|' . $bucket . '|' . $request->get_route(), wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return false;
        }
        set_transient($key, $count + 1, 330);
        return true;
    }

    public function invalidate_public_cache(...$args): void {
        update_option(self::CACHE_GENERATION_OPTION, ((int) get_option(self::CACHE_GENERATION_OPTION, 1)) + 1, false);
    }

    public function daily_maintenance(): void {
        $this->run_report(true, 0);
        $this->invalidate_public_cache();
    }

    public function handle_run_report(): void {
        $this->assert_admin_action('sc_library_readiness_run');
        $this->run_report(true);
        $this->redirect_admin('report-ran');
    }

    public function handle_clear_cache(): void {
        $this->assert_admin_action('sc_library_readiness_clear_cache');
        $this->invalidate_public_cache();
        $this->redirect_admin('cache-cleared');
    }

    public function handle_repair_cron(): void {
        $this->assert_admin_action('sc_library_readiness_repair_cron');
        if (!wp_next_scheduled('sc_library_daily_reconcile')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sc_library_daily_reconcile');
        }
        if (!wp_next_scheduled('sc_library_preservation_daily')) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', 'sc_library_preservation_daily');
        }
        if (!wp_next_scheduled('sc_library_hardening_daily')) {
            wp_schedule_event(time() + (3 * HOUR_IN_SECONDS), 'daily', 'sc_library_hardening_daily');
        }
        $this->redirect_admin('cron-repaired');
    }

    private function assert_admin_action(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'sustainable-catalyst-library'));
        }
        check_admin_referer($action);
    }

    private function redirect_admin(string $notice): void {
        wp_safe_redirect(add_query_arg(['page' => 'sc-library-readiness', 'readiness_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public function render_admin_page(): void {
        if (isset($_GET['readiness_notice'])) {
            $notice = sanitize_key((string) wp_unslash($_GET['readiness_notice']));
            $messages = [
                'report-ran' => __('Production-readiness report completed.', 'sustainable-catalyst-library'),
                'cache-cleared' => __('Public Library cache generation advanced. Previous responses will no longer be reused.', 'sustainable-catalyst-library'),
                'cron-repaired' => __('Library maintenance schedules were checked and repaired.', 'sustainable-catalyst-library'),
            ];
            if (isset($messages[$notice])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
            }
        }
        $report = $this->latest_report() ?: $this->run_report(false);
        $history = $this->report_history(8);
        ?>
        <div class="wrap sc-library-readiness-admin">
            <h1><?php esc_html_e('Library Production Readiness', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Review accessibility, mobile behavior, performance, security, preservation, and operational health without exposing credentials or private workspace data.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-readiness-summary">
                <div><span><?php esc_html_e('Overall', 'sustainable-catalyst-library'); ?></span><strong class="sc-library-readiness-badge sc-library-readiness-badge--<?php echo esc_attr((string) $report['overall_status']); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', (string) $report['overall_status']))); ?></strong></div>
                <div><span><?php esc_html_e('Score', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html((string) $report['score']); ?>/100</strong></div>
                <div><span><?php esc_html_e('Ready', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html((string) $report['counts']['ready']); ?></strong></div>
                <div><span><?php esc_html_e('Warnings', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html((string) $report['counts']['warning']); ?></strong></div>
                <div><span><?php esc_html_e('Failures', 'sustainable-catalyst-library'); ?></span><strong><?php echo esc_html((string) $report['counts']['fail']); ?></strong></div>
            </div>

            <div class="sc-library-readiness-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_readiness_run">
                    <?php wp_nonce_field('sc_library_readiness_run'); ?>
                    <button class="button button-primary"><?php esc_html_e('Run complete readiness report', 'sustainable-catalyst-library'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_readiness_clear_cache">
                    <?php wp_nonce_field('sc_library_readiness_clear_cache'); ?>
                    <button class="button"><?php esc_html_e('Invalidate public response cache', 'sustainable-catalyst-library'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_readiness_repair_cron">
                    <?php wp_nonce_field('sc_library_readiness_repair_cron'); ?>
                    <button class="button"><?php esc_html_e('Repair maintenance schedules', 'sustainable-catalyst-library'); ?></button>
                </form>
            </div>

            <?php foreach ($report['categories'] as $category_key => $category) : ?>
                <section class="sc-library-readiness-section" aria-labelledby="sc-readiness-<?php echo esc_attr((string) $category_key); ?>">
                    <h2 id="sc-readiness-<?php echo esc_attr((string) $category_key); ?>"><?php echo esc_html((string) $category['label']); ?></h2>
                    <div class="sc-library-readiness-checks">
                        <?php foreach ($category['checks'] as $check) : ?>
                            <article class="sc-library-readiness-check sc-library-readiness-check--<?php echo esc_attr((string) $check['status']); ?>">
                                <header><span><?php echo esc_html(ucfirst((string) $check['status'])); ?></span><h3><?php echo esc_html((string) $check['label']); ?></h3></header>
                                <p><?php echo esc_html((string) $check['summary']); ?></p>
                                <?php if ((string) $check['detail'] !== '') : ?><p class="description"><?php echo esc_html((string) $check['detail']); ?></p><?php endif; ?>
                                <?php if ((string) $check['remediation'] !== '') : ?><p><strong><?php esc_html_e('Next action:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html((string) $check['remediation']); ?></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="sc-library-readiness-settings">
                <h2><?php esc_html_e('Hardening settings', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('sc_library_hardening_settings'); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Enable hardening layer', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_hardening" value="1" <?php checked((bool) get_option('sc_library_enable_hardening', 1)); ?>> <?php esc_html_e('Load accessibility, responsive, and security enhancements on Library interfaces.', 'sustainable-catalyst-library'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Public REST cache', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_public_cache" value="1" <?php checked((bool) get_option('sc_library_enable_public_cache', 1)); ?>> <?php esc_html_e('Cache only explicitly public read routes.', 'sustainable-catalyst-library'); ?></label><p><input type="number" min="30" max="3600" name="sc_library_public_cache_ttl" value="<?php echo esc_attr((string) get_option('sc_library_public_cache_ttl', 300)); ?>"> <?php esc_html_e('seconds', 'sustainable-catalyst-library'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Anonymous REST limit', 'sustainable-catalyst-library'); ?></th><td><input type="number" min="0" max="5000" name="sc_library_public_rate_limit" value="<?php echo esc_attr((string) get_option('sc_library_public_rate_limit', 240)); ?>"> <span><?php esc_html_e('requests per five minutes per route and IP; use 0 to disable.', 'sustainable-catalyst-library'); ?></span></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Minimum touch target', 'sustainable-catalyst-library'); ?></th><td><input type="number" min="40" max="56" name="sc_library_touch_target_px" value="<?php echo esc_attr((string) get_option('sc_library_touch_target_px', 44)); ?>"> px</td></tr>
                        <tr><th scope="row"><?php esc_html_e('Public status page URL', 'sustainable-catalyst-library'); ?></th><td><input class="regular-text" type="url" name="sc_library_readiness_page_url" value="<?php echo esc_attr((string) get_option('sc_library_readiness_page_url', home_url('/library-status/'))); ?>"><p class="description"><code>[sc_library_readiness_status]</code></p></td></tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </section>

            <?php if ($history) : ?>
                <section class="sc-library-readiness-history">
                    <h2><?php esc_html_e('Recent reports', 'sustainable-catalyst-library'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php esc_html_e('Date', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Score', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Run by', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                    <?php foreach ($history as $row) : ?>
                        <tr><td><?php echo esc_html((string) $row['created_at']); ?></td><td><?php echo esc_html((string) $row['overall_status']); ?></td><td><?php echo esc_html((string) $row['score']); ?></td><td><?php echo esc_html((string) $row['created_by']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    public function run_report(bool $persist = true, ?int $user_id = null): array {
        global $wpdb;
        $categories = [
            'platform' => ['label' => __('Platform and runtime', 'sustainable-catalyst-library'), 'checks' => []],
            'accessibility' => ['label' => __('Accessibility', 'sustainable-catalyst-library'), 'checks' => []],
            'mobile' => ['label' => __('Mobile and responsive behavior', 'sustainable-catalyst-library'), 'checks' => []],
            'performance' => ['label' => __('Performance and large-library operations', 'sustainable-catalyst-library'), 'checks' => []],
            'security' => ['label' => __('Security and privacy', 'sustainable-catalyst-library'), 'checks' => []],
            'integrity' => ['label' => __('Preservation, backups, and integrity', 'sustainable-catalyst-library'), 'checks' => []],
        ];

        $categories['platform']['checks'][] = $this->check('wordpress-version', __('WordPress version', 'sustainable-catalyst-library'), version_compare(get_bloginfo('version'), '6.4', '>='), __('WordPress meets the Library minimum.', 'sustainable-catalyst-library'), sprintf(__('Current version: %s', 'sustainable-catalyst-library'), get_bloginfo('version')), __('Upgrade WordPress to 6.4 or later.', 'sustainable-catalyst-library'));
        $categories['platform']['checks'][] = $this->check('php-version', __('PHP version', 'sustainable-catalyst-library'), version_compare(PHP_VERSION, '8.1', '>='), __('PHP meets the Library minimum.', 'sustainable-catalyst-library'), sprintf(__('Current version: %s', 'sustainable-catalyst-library'), PHP_VERSION), __('Upgrade PHP to 8.1 or later.', 'sustainable-catalyst-library'));
        $categories['platform']['checks'][] = $this->check('https', __('HTTPS', 'sustainable-catalyst-library'), is_ssl(), __('The site is using HTTPS.', 'sustainable-catalyst-library'), home_url('/'), __('Enable HTTPS for the public site and WordPress administration.', 'sustainable-catalyst-library'));
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $categories['platform']['checks'][] = $this->check_status('environment', __('WordPress environment', 'sustainable-catalyst-library'), $environment === 'production' ? 'ready' : 'info', sprintf(__('Environment is marked %s.', 'sustainable-catalyst-library'), $environment), '', '');

        $hardening_css = file_exists(SC_LIBRARY_DIR . 'assets/css/sc-library-hardening.css');
        $hardening_js = file_exists(SC_LIBRARY_DIR . 'assets/js/sc-library-hardening.js');
        $categories['accessibility']['checks'][] = $this->check('a11y-assets', __('Accessibility enhancement assets', 'sustainable-catalyst-library'), $hardening_css && $hardening_js, __('Keyboard, focus, reduced-motion, table, and announcement enhancements are installed.', 'sustainable-catalyst-library'), '', __('Reinstall the plugin package if either hardening asset is missing.', 'sustainable-catalyst-library'));
        $categories['accessibility']['checks'][] = $this->check('hardening-enabled', __('Accessibility hardening enabled', 'sustainable-catalyst-library'), (bool) get_option('sc_library_enable_hardening', 1), __('The accessibility layer is enabled.', 'sustainable-catalyst-library'), '', __('Enable the hardening layer in Production Readiness settings.', 'sustainable-catalyst-library'));
        $public_media_missing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sc_library_media_assets WHERE visibility = 'public' AND COALESCE(accessibility_text, '') = ''");
        $categories['accessibility']['checks'][] = $this->check_status('media-descriptions', __('Public media descriptions', 'sustainable-catalyst-library'), $public_media_missing === 0 ? 'ready' : 'warning', $public_media_missing === 0 ? __('Public multimedia assets have accessibility descriptions.', 'sustainable-catalyst-library') : sprintf(_n('%d public media asset lacks an accessibility description.', '%d public media assets lack accessibility descriptions.', $public_media_missing, 'sustainable-catalyst-library'), $public_media_missing), '', __('Add descriptions, captions, and transcripts in Multimedia Studio.', 'sustainable-catalyst-library'));
        $categories['accessibility']['checks'][] = $this->check('pdf-mobile-fallback', __('PDF mobile fallback', 'sustainable-catalyst-library'), file_exists(SC_LIBRARY_DIR . 'assets/js/sc-library-foundation-viewer.js'), __('Foundation Documents include an explicit native-viewer fallback.', 'sustainable-catalyst-library'), '', __('Restore the Foundation Document viewer assets.', 'sustainable-catalyst-library'));

        $categories['mobile']['checks'][] = $this->check('responsive-css', __('Responsive Library styles', 'sustainable-catalyst-library'), $hardening_css && file_exists(SC_LIBRARY_DIR . 'assets/css/sc-library.css'), __('Responsive and touch-target rules are installed.', 'sustainable-catalyst-library'), sprintf(__('Minimum target: %d px', 'sustainable-catalyst-library'), (int) get_option('sc_library_touch_target_px', 44)), __('Restore the Library stylesheet package.', 'sustainable-catalyst-library'));
        $categories['mobile']['checks'][] = $this->check('record-card-repair', __('Public record-card repair retained', 'sustainable-catalyst-library'), str_contains((string) @file_get_contents(SC_LIBRARY_DIR . 'assets/css/sc-library.css'), 'Public Record Card Layout and Responsive Rendering Repair'), __('The v1.14.1 record-card width repair remains installed.', 'sustainable-catalyst-library'), '', __('Reinstall the complete plugin rather than copying individual files.', 'sustainable-catalyst-library'));
        $categories['mobile']['checks'][] = $this->check('no-library-iframes', __('Native Library interfaces', 'sustainable-catalyst-library'), !$this->plugin_contains_iframe(), __('Library templates do not depend on embedded iframes.', 'sustainable-catalyst-library'), '', __('Replace iframe-based Library interfaces with native WordPress components.', 'sustainable-catalyst-library'));

        $index_table = $wpdb->prefix . 'sc_library_index';
        $index_exists = $this->table_exists($index_table);
        $index_count = $index_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$index_table}") : 0;
        $categories['performance']['checks'][] = $this->check('index-table', __('Library index table', 'sustainable-catalyst-library'), $index_exists, __('The Library index table is available.', 'sustainable-catalyst-library'), sprintf(__('Indexed records: %d', 'sustainable-catalyst-library'), $index_count), __('Use SC Library → Index Tools → Repair database schema.', 'sustainable-catalyst-library'));
        $categories['performance']['checks'][] = $this->check_status('index-volume', __('Large-library index volume', 'sustainable-catalyst-library'), $index_count >= 1000 ? 'ready' : ($index_count > 0 ? 'warning' : 'fail'), sprintf(__('The index currently contains %d records.', 'sustainable-catalyst-library'), $index_count), '', __('Run the complete cursor-based reconciliation and compare it with the raw published inventory.', 'sustainable-catalyst-library'));
        $categories['performance']['checks'][] = $this->check('daily-reconcile', __('Daily index reconciliation', 'sustainable-catalyst-library'), (bool) wp_next_scheduled('sc_library_daily_reconcile'), __('The daily cursor reconciliation is scheduled.', 'sustainable-catalyst-library'), '', __('Use Repair maintenance schedules on this page.', 'sustainable-catalyst-library'));
        $categories['performance']['checks'][] = $this->check('public-cache', __('Bounded public response cache', 'sustainable-catalyst-library'), (bool) get_option('sc_library_enable_public_cache', 1), sprintf(__('Public read responses are cached for %d seconds.', 'sustainable-catalyst-library'), (int) get_option('sc_library_public_cache_ttl', 300)), wp_using_ext_object_cache() ? __('A persistent object cache is active.', 'sustainable-catalyst-library') : __('WordPress is using its standard transient store.', 'sustainable-catalyst-library'), __('Enable the bounded cache or add a persistent object cache for very high traffic.', 'sustainable-catalyst-library'));
        $categories['performance']['checks'][] = $this->check_status('object-cache', __('Persistent object cache', 'sustainable-catalyst-library'), wp_using_ext_object_cache() ? 'ready' : 'info', wp_using_ext_object_cache() ? __('A persistent object cache is active.', 'sustainable-catalyst-library') : __('No persistent object cache was detected. This is optional.', 'sustainable-catalyst-library'), '', '');
        $categories['performance']['checks'][] = $this->check('wp-cron', __('WordPress scheduled jobs', 'sustainable-catalyst-library'), !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON), __('WordPress cron is available.', 'sustainable-catalyst-library'), '', __('Configure a real system cron if WP-Cron is disabled.', 'sustainable-catalyst-library'));

        $categories['security']['checks'][] = $this->check_status('debug-display', __('Debug display', 'sustainable-catalyst-library'), (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? 'warning' : 'ready', (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? __('Debug output may be visible to public visitors.', 'sustainable-catalyst-library') : __('Public debug display is disabled.', 'sustainable-catalyst-library'), '', __('Set WP_DEBUG_DISPLAY to false in production.', 'sustainable-catalyst-library'));
        $categories['security']['checks'][] = $this->check_status('file-editor', __('WordPress file editor', 'sustainable-catalyst-library'), (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? 'ready' : 'warning', (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? __('The dashboard file editor is disabled.', 'sustainable-catalyst-library') : __('The dashboard file editor may be available.', 'sustainable-catalyst-library'), '', __('Define DISALLOW_FILE_EDIT as true in production.', 'sustainable-catalyst-library'));
        $categories['security']['checks'][] = $this->check_status('force-ssl-admin', __('Secure administration', 'sustainable-catalyst-library'), (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN) || is_ssl() ? 'ready' : 'warning', __('WordPress administration should remain on HTTPS.', 'sustainable-catalyst-library'), '', __('Enable FORCE_SSL_ADMIN and HTTPS.', 'sustainable-catalyst-library'));
        $categories['security']['checks'][] = $this->check('rate-limit', __('Anonymous REST rate limiting', 'sustainable-catalyst-library'), (int) get_option('sc_library_public_rate_limit', 240) > 0, sprintf(__('Limit: %d requests per five minutes per public route and IP.', 'sustainable-catalyst-library'), (int) get_option('sc_library_public_rate_limit', 240)), '', __('Set a nonzero anonymous REST limit.', 'sustainable-catalyst-library'));
        $categories['security']['checks'][] = $this->check('api-key-storage', __('API-key secret storage', 'sustainable-catalyst-library'), $this->table_has_column($wpdb->prefix . 'sc_library_api_keys', 'secret_hash') && !$this->table_has_column($wpdb->prefix . 'sc_library_api_keys', 'api_key'), __('Service keys are represented by hashes rather than recoverable plaintext columns.', 'sustainable-catalyst-library'), '', __('Repair the Developer API schema before issuing production keys.', 'sustainable-catalyst-library'));
        $categories['security']['checks'][] = $this->check_status('remote-media', __('Remote media ingestion', 'sustainable-catalyst-library'), (bool) get_option('sc_library_media_allow_remote_urls', 0) ? 'warning' : 'ready', (bool) get_option('sc_library_media_allow_remote_urls', 0) ? __('Remote media URLs are enabled and require careful rights and network review.', 'sustainable-catalyst-library') : __('Remote media ingestion is disabled by default.', 'sustainable-catalyst-library'), '', __('Leave remote ingestion disabled unless a controlled workflow requires it.', 'sustainable-catalyst-library'));

        $snapshot_table = $wpdb->prefix . 'sc_library_preservation_snapshots';
        $snapshot_count = $this->table_exists($snapshot_table) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$snapshot_table}") : 0;
        $categories['integrity']['checks'][] = $this->check_status('preservation-snapshots', __('Preservation snapshots', 'sustainable-catalyst-library'), $snapshot_count > 0 ? 'ready' : 'warning', sprintf(_n('%d frozen snapshot is recorded.', '%d frozen snapshots are recorded.', $snapshot_count, 'sustainable-catalyst-library'), $snapshot_count), '', __('Create snapshots for critical public records and documentation.', 'sustainable-catalyst-library'));
        $last_audit = get_option('sc_library_integrity_last_audit', []);
        $audit_date = is_array($last_audit) ? (string) ($last_audit['completed_at'] ?? $last_audit['updated_at'] ?? '') : '';
        $categories['integrity']['checks'][] = $this->check_status('integrity-audit', __('Integrity audit', 'sustainable-catalyst-library'), $audit_date !== '' ? 'ready' : 'warning', $audit_date !== '' ? sprintf(__('Last completed audit: %s', 'sustainable-catalyst-library'), $audit_date) : __('No completed integrity audit was found.', 'sustainable-catalyst-library'), '', __('Run the bounded audit under Preservation & Archive.', 'sustainable-catalyst-library'));
        $pdf_failures = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_sc_foundation_extraction_status', 'failed'));
        $categories['integrity']['checks'][] = $this->check_status('pdf-extraction', __('Foundation PDF extraction', 'sustainable-catalyst-library'), $pdf_failures === 0 ? 'ready' : 'warning', $pdf_failures === 0 ? __('No failed Foundation PDF extractions were found.', 'sustainable-catalyst-library') : sprintf(_n('%d Foundation PDF extraction has failed.', '%d Foundation PDF extractions have failed.', $pdf_failures, 'sustainable-catalyst-library'), $pdf_failures), '', __('Retry extraction or replace image-only PDFs with OCR-enabled files.', 'sustainable-catalyst-library'));
        $categories['integrity']['checks'][] = $this->check_status('backup-boundary', __('Off-site backups', 'sustainable-catalyst-library'), 'info', __('WordPress cannot verify your hosting provider’s off-site backup policy.', 'sustainable-catalyst-library'), '', __('Confirm automated database and uploads backups outside this plugin.', 'sustainable-catalyst-library'));
        $categories['integrity']['checks'][] = $this->check('hardening-cron', __('Daily readiness evaluation', 'sustainable-catalyst-library'), (bool) wp_next_scheduled('sc_library_hardening_daily'), __('The daily readiness check is scheduled.', 'sustainable-catalyst-library'), '', __('Use Repair maintenance schedules on this page.', 'sustainable-catalyst-library'));

        $counts = ['ready' => 0, 'warning' => 0, 'fail' => 0, 'info' => 0];
        foreach ($categories as &$category) {
            foreach ($category['checks'] as $check) {
                $status = (string) $check['status'];
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
        }
        unset($category);
        $penalty = ($counts['warning'] * 4) + ($counts['fail'] * 14);
        $score = max(0, 100 - $penalty);
        $overall = $counts['fail'] > 0 ? 'action_required' : ($counts['warning'] > 0 ? 'review_recommended' : 'ready');
        $report = [
            'schema' => self::SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'run_uuid' => wp_generate_uuid4(),
            'generated_at' => current_time('mysql', true),
            'site_url' => home_url('/'),
            'overall_status' => $overall,
            'score' => $score,
            'counts' => $counts,
            'categories' => $categories,
        ];

        if ($persist) {
            $creator = $user_id === null ? get_current_user_id() : $user_id;
            update_option(self::LAST_REPORT_OPTION, $report, false);
            if ($this->table_exists(self::runs_table())) {
                $wpdb->insert(self::runs_table(), [
                    'run_uuid' => $report['run_uuid'],
                    'overall_status' => $overall,
                    'score' => $score,
                    'report_json' => wp_json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_by' => max(0, (int) $creator),
                    'created_at' => current_time('mysql', true),
                ], ['%s', '%s', '%d', '%s', '%d', '%s']);
            }
        }
        return $report;
    }

    private function check(string $id, string $label, bool $pass, string $summary, string $detail = '', string $remediation = ''): array {
        return $this->check_status($id, $label, $pass ? 'ready' : 'fail', $summary, $detail, $pass ? '' : $remediation);
    }

    private function check_status(string $id, string $label, string $status, string $summary, string $detail = '', string $remediation = ''): array {
        if (!in_array($status, ['ready', 'warning', 'fail', 'info'], true)) {
            $status = 'info';
        }
        return compact('id', 'label', 'status', 'summary', 'detail', 'remediation');
    }

    private function latest_report(): array {
        $report = get_option(self::LAST_REPORT_OPTION, []);
        return is_array($report) ? $report : [];
    }

    private function public_report(array $report): array {
        $categories = [];
        foreach (($report['categories'] ?? []) as $key => $category) {
            $counts = ['ready' => 0, 'warning' => 0, 'fail' => 0, 'info' => 0];
            foreach (($category['checks'] ?? []) as $check) {
                $status = (string) ($check['status'] ?? 'info');
                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }
            $categories[$key] = array_merge(['label' => (string) ($category['label'] ?? ucfirst((string) $key))], $counts);
        }
        return [
            'schema' => self::SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'generated_at' => (string) ($report['generated_at'] ?? ''),
            'overall_status' => (string) ($report['overall_status'] ?? 'unknown'),
            'score' => (int) ($report['score'] ?? 0),
            'counts' => (array) ($report['counts'] ?? []),
            'categories' => $categories,
        ];
    }

    private function report_history(int $limit): array {
        global $wpdb;
        if (!$this->table_exists(self::runs_table())) {
            return [];
        }
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT overall_status, score, created_by, created_at FROM ' . self::runs_table() . ' ORDER BY id DESC LIMIT %d',
            min(50, max(1, $limit))
        ), ARRAY_A);
    }

    private function table_exists(string $table): bool {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function table_has_column(string $table, string $column): bool {
        global $wpdb;
        if (!$this->table_exists($table)) {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return is_string($found) && $found === $column;
    }

    private function plugin_contains_iframe(): bool {
        $files = glob(SC_LIBRARY_DIR . 'templates/*.php') ?: [];
        foreach ($files as $file) {
            if (stripos((string) @file_get_contents($file), '<iframe') !== false) {
                return true;
            }
        }
        return false;
    }
}
