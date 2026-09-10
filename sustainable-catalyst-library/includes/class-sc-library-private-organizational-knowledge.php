<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.11.0 — Private Organizational Knowledge Foundation.
 *
 * WordPress remains the user/session authority. Private organizational records
 * are stored only in the separate backend private-knowledge plane and are never
 * inserted into the public Library search corpus by this module.
 */
final class SC_Library_Private_Organizational_Knowledge {
    public const VERSION = '5.11.0';
    public const REST_NAMESPACE = 'sc-library/v1';
    public const SHORTCODE = 'sc_private_organizational_knowledge';

    public function register_hooks(): void {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_settings(): void {
        register_setting('sc_library_private_knowledge_settings', 'sc_library_private_org_key', [
            'type' => 'string',
            'sanitize_callback' => static function($value): string {
                $value = strtolower((string)$value);
                $value = preg_replace('/[^a-z0-9_.-]+/', '-', $value) ?: '';
                return substr(trim($value, '-.'), 0, 120);
            },
            'default' => '',
        ]);
        register_setting('sc_library_private_knowledge_settings', 'sc_library_private_org_name', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Private Organizational Knowledge', 'sustainable-catalyst-library'),
            __('Private Knowledge', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-private-knowledge',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) { return; }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Private Organizational Knowledge', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Configure the organization scope used for private Library records. The backend API key remains in the existing Python Backend settings and is never exposed to the browser.', 'sustainable-catalyst-library'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('sc_library_private_knowledge_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sc_library_private_org_key"><?php esc_html_e('Organization key', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input class="regular-text code" id="sc_library_private_org_key" name="sc_library_private_org_key" type="text" maxlength="120" value="<?php echo esc_attr((string)get_option('sc_library_private_org_key', '')); ?>" placeholder="content-catalyst"><p class="description"><?php esc_html_e('Stable tenant identifier. Changing it creates a different private knowledge scope.', 'sustainable-catalyst-library'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_private_org_name"><?php esc_html_e('Organization name', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input class="regular-text" id="sc_library_private_org_name" name="sc_library_private_org_name" type="text" maxlength="255" value="<?php echo esc_attr((string)get_option('sc_library_private_org_name', '')); ?>"></td>
                    </tr>
                </table>
                <?php submit_button(__('Save private knowledge settings', 'sustainable-catalyst-library')); ?>
            </form>
            <hr>
            <p><strong><?php esc_html_e('Storage boundary:', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('Private organizational records use separate backend tables and are not added to public Library search.', 'sustainable-catalyst-library'); ?></p>
            <p><strong><?php esc_html_e('Ingestion boundary:', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v5.11.0 accepts normalized extracted text packets. Existing PDF/DOCX conversion and OCR paths can supply that text; this release does not claim a new binary parser.', 'sustainable-catalyst-library'); ?></p>
        </div>
        <?php
    }

    public function register_assets(): void {
        wp_register_style(
            'sc-library-private-knowledge-v5110',
            SC_LIBRARY_URL . 'assets/css/sc-library-private-knowledge-v5110.css',
            [],
            self::VERSION
        );
        wp_register_script(
            'sc-library-private-knowledge-v5110',
            SC_LIBRARY_URL . 'assets/js/sc-library-private-knowledge-v5110.js',
            [],
            self::VERSION,
            true
        );
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_manifest'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/search', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_search'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/record', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_record'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/versions', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_versions'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/handoff', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_handoff'],
            'permission_callback' => [$this, 'can_read'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/private-knowledge/ingest', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_ingest'],
            'permission_callback' => [$this, 'can_ingest'],
        ]);
    }

    public function can_read(): bool {
        if (!is_user_logged_in() || !current_user_can('read')) { return false; }
        return (bool)apply_filters('sc_library_private_knowledge_access', true, 'read', get_current_user_id());
    }

    public function can_ingest(): bool {
        if (!is_user_logged_in() || !current_user_can('upload_files')) { return false; }
        return (bool)apply_filters('sc_library_private_knowledge_access', true, 'ingest', get_current_user_id());
    }

    private static function organization_key(): string {
        if (defined('SC_LIBRARY_PRIVATE_ORG_KEY') && SC_LIBRARY_PRIVATE_ORG_KEY) {
            return sanitize_key((string)SC_LIBRARY_PRIVATE_ORG_KEY);
        }
        return sanitize_key((string)get_option('sc_library_private_org_key', ''));
    }

    private static function organization_name(): string {
        $name = trim((string)get_option('sc_library_private_org_name', ''));
        return $name ?: get_bloginfo('name');
    }

    private static function actor(): array {
        $user = wp_get_current_user();
        $scopes = ['user:' . (int)$user->ID];
        foreach ((array)$user->roles as $role) {
            $role = sanitize_key((string)$role);
            if ($role) { $scopes[] = 'role:' . $role; }
        }
        $scopes = (array)apply_filters('sc_library_private_knowledge_actor_scopes', $scopes, $user);
        $scopes = array_values(array_unique(array_filter(array_map(static function($scope): string {
            return substr(sanitize_text_field((string)$scope), 0, 191);
        }, $scopes))));
        return [
            'actor_id' => 'wordpress-user:' . (int)$user->ID,
            'scopes' => $scopes,
        ];
    }

    private static function configured() {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_private_knowledge_backend_unconfigured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        if ('' === self::organization_key()) {
            return new WP_Error('sc_private_knowledge_org_unconfigured', __('Private organization key is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        return true;
    }

    private static function signed_post(string $path, array $body) {
        $configured = self::configured();
        if (is_wp_error($configured)) { return $configured; }
        $json = (string)wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return SC_Library_Python_Backend::signed_request('POST', $path, $json);
    }

    public function rest_manifest(WP_REST_Request $request) {
        $configured = self::configured();
        if (is_wp_error($configured)) { return $configured; }
        $response = wp_remote_get(SC_Library_Python_Backend::base_url() . '/v1/private-organizational-knowledge', [
            'timeout' => 8,
            'redirection' => 0,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) { return $response; }
        $code = (int)wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string)wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($decoded)) {
            return new WP_Error('sc_private_knowledge_manifest_failed', __('Private knowledge manifest is unavailable.', 'sustainable-catalyst-library'), ['status' => $code ?: 502]);
        }
        $decoded['wordpress'] = [
            'configured' => true,
            'organization_key' => self::organization_key(),
            'organization_name' => self::organization_name(),
            'private_access' => true,
        ];
        return rest_ensure_response($decoded);
    }

    public function rest_search(WP_REST_Request $request) {
        $payload = [
            'organization_key' => self::organization_key(),
            'actor' => self::actor(),
            'q' => sanitize_text_field((string)$request->get_param('q')),
            'object_type' => self::nullable_key($request->get_param('object_type')),
            'source_key' => self::nullable_key($request->get_param('source_key')),
            'project_key' => self::nullable_key($request->get_param('project_key')),
            'department' => self::nullable_text($request->get_param('department')),
            'limit' => min(50, max(1, (int)($request->get_param('limit') ?: 20))),
            'offset' => max(0, (int)($request->get_param('offset') ?: 0)),
        ];
        return self::response(self::signed_post('/v1/private-organizational-knowledge/search', $payload));
    }

    public function rest_record(WP_REST_Request $request) {
        return self::record_call('/v1/private-organizational-knowledge/record', $request);
    }

    public function rest_versions(WP_REST_Request $request) {
        return self::record_call('/v1/private-organizational-knowledge/versions', $request);
    }

    private function record_call(string $path, WP_REST_Request $request) {
        $record_id = sanitize_text_field((string)$request->get_param('record_id'));
        if ('' === $record_id) {
            return new WP_Error('sc_private_knowledge_record_required', __('record_id is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $payload = [
            'organization_key' => self::organization_key(),
            'actor' => self::actor(),
            'record_id' => substr($record_id, 0, 255),
        ];
        return self::response(self::signed_post($path, $payload));
    }

    public function rest_handoff(WP_REST_Request $request) {
        $record_id = sanitize_text_field((string)$request->get_param('record_id'));
        $target = sanitize_key((string)$request->get_param('target'));
        if (!in_array($target, ['research-librarian', 'workspace', 'lab'], true)) {
            return new WP_Error('sc_private_knowledge_target_invalid', __('Unsupported private knowledge handoff target.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $payload = [
            'organization_key' => self::organization_key(),
            'actor' => self::actor(),
            'record_id' => substr($record_id, 0, 255),
            'target' => $target,
        ];
        return self::response(self::signed_post('/v1/private-organizational-knowledge/handoff', $payload));
    }

    public function rest_ingest(WP_REST_Request $request) {
        $source = $request->get_param('source');
        $records = $request->get_param('records');
        if (!is_array($source) || !is_array($records) || !$records) {
            return new WP_Error('sc_private_knowledge_ingest_invalid', __('A normalized source packet and at least one record are required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $payload = [
            'schema' => 'sc-private-organizational-knowledge-ingest/1.0',
            'actor' => self::actor(),
            'organization' => [
                'org_key' => self::organization_key(),
                'name' => self::organization_name(),
                'metadata' => ['wordpress_site' => home_url('/'), 'plugin_version' => SC_LIBRARY_VERSION],
            ],
            'source' => self::sanitize_source($source),
            'records' => array_values(array_filter(array_map([self::class, 'sanitize_record'], array_slice($records, 0, 100)))),
        ];
        if (!$payload['records']) {
            return new WP_Error('sc_private_knowledge_ingest_empty', __('No valid normalized private records were provided.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        return self::response(self::signed_post('/v1/private-organizational-knowledge/ingest', $payload));
    }

    public static function ingest_normalized_records(array $source, array $records) {
        if (!is_user_logged_in() || !current_user_can('upload_files')) {
            return new WP_Error('sc_private_knowledge_forbidden', __('Private knowledge ingestion requires upload permission.', 'sustainable-catalyst-library'), ['status' => 403]);
        }
        $request = new WP_REST_Request('POST');
        $request->set_param('source', $source);
        $request->set_param('records', $records);
        $instance = new self();
        return $instance->rest_ingest($request);
    }

    private static function sanitize_source(array $source): array {
        return [
            'source_key' => substr(sanitize_key((string)($source['source_key'] ?? 'internal')), 0, 191),
            'name' => substr(sanitize_text_field((string)($source['name'] ?? 'Internal knowledge')), 0, 255),
            'source_type' => substr(sanitize_key((string)($source['source_type'] ?? 'internal')), 0, 80),
            'canonical_url' => isset($source['canonical_url']) ? esc_url_raw((string)$source['canonical_url']) : null,
            'owner' => isset($source['owner']) ? substr(sanitize_text_field((string)$source['owner']), 0, 255) : null,
            'metadata' => is_array($source['metadata'] ?? null) ? $source['metadata'] : [],
        ];
    }

    private static function sanitize_record($row): ?array {
        if (!is_array($row)) { return null; }
        $record_id = substr(sanitize_text_field((string)($row['record_id'] ?? '')), 0, 255);
        $title = substr(sanitize_text_field((string)($row['title'] ?? '')), 0, 1000);
        if ('' === $record_id || '' === $title) { return null; }
        $access_level = sanitize_key((string)($row['access_level'] ?? 'organization'));
        if (!in_array($access_level, ['organization', 'restricted', 'project'], true)) { $access_level = 'organization'; }
        $scopes = is_array($row['access_scopes'] ?? null) ? array_values(array_unique(array_filter(array_map('sanitize_text_field', $row['access_scopes'])))) : [];
        return [
            'record_id' => $record_id,
            'object_type' => substr(sanitize_key((string)($row['object_type'] ?? 'document')), 0, 80),
            'title' => $title,
            'canonical_url' => isset($row['canonical_url']) ? esc_url_raw((string)$row['canonical_url']) : null,
            'abstract' => substr(sanitize_textarea_field((string)($row['abstract'] ?? '')), 0, 40000),
            'body_text' => substr(wp_strip_all_tags((string)($row['body_text'] ?? ''), true), 0, 2000000),
            'language' => substr(sanitize_key((string)($row['language'] ?? 'en')), 0, 16),
            'original_format' => substr(sanitize_key((string)($row['original_format'] ?? 'text')), 0, 40),
            'source_updated_at' => isset($row['source_updated_at']) ? sanitize_text_field((string)$row['source_updated_at']) : null,
            'authors' => self::string_list($row['authors'] ?? []),
            'topics' => self::string_list($row['topics'] ?? []),
            'tags' => self::string_list($row['tags'] ?? []),
            'identifiers' => is_array($row['identifiers'] ?? null) ? $row['identifiers'] : [],
            'metadata' => is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
            'access_level' => $access_level,
            'access_scopes' => $scopes,
            'project_key' => isset($row['project_key']) ? self::nullable_key($row['project_key']) : null,
            'department' => isset($row['department']) ? self::nullable_text($row['department']) : null,
            'retention_label' => isset($row['retention_label']) ? self::nullable_text($row['retention_label']) : null,
        ];
    }

    private static function string_list($value): array {
        if (!is_array($value)) { return []; }
        return array_values(array_unique(array_filter(array_map(static fn($item) => substr(sanitize_text_field((string)$item), 0, 500), $value))));
    }

    private static function nullable_key($value): ?string {
        $value = sanitize_key((string)$value);
        return '' === $value ? null : substr($value, 0, 191);
    }

    private static function nullable_text($value): ?string {
        $value = sanitize_text_field((string)$value);
        return '' === $value ? null : substr($value, 0, 191);
    }

    private static function response($result) {
        if (is_wp_error($result)) { return $result; }
        return rest_ensure_response($result);
    }

    public function shortcode(array $atts = []): string {
        if (!$this->can_read()) {
            return '<section class="sc-pok sc-pok--locked"><p>' . esc_html__('Private organizational knowledge is available only to authorized signed-in members.', 'sustainable-catalyst-library') . '</p></section>';
        }
        $atts = shortcode_atts([
            'title' => 'Private Organizational Knowledge',
            'intro' => 'Search internal research and organizational records inside a separate governed private knowledge plane.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-private-knowledge-v5110');
        wp_enqueue_script('sc-library-private-knowledge-v5110');
        $configured = !is_wp_error(self::configured());
        ob_start(); ?>
        <section class="sc-pok" data-sc-private-knowledge
            data-search-endpoint="<?php echo esc_url(rest_url(self::REST_NAMESPACE . '/private-knowledge/search')); ?>"
            data-record-endpoint="<?php echo esc_url(rest_url(self::REST_NAMESPACE . '/private-knowledge/record')); ?>"
            data-versions-endpoint="<?php echo esc_url(rest_url(self::REST_NAMESPACE . '/private-knowledge/versions')); ?>"
            data-handoff-endpoint="<?php echo esc_url(rest_url(self::REST_NAMESPACE . '/private-knowledge/handoff')); ?>"
            data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
            <header class="sc-pok__header">
                <p class="sc-pok__kicker"><?php esc_html_e('Private Knowledge Infrastructure', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>
            <div class="sc-pok__boundary"><strong><?php esc_html_e('Private by architecture.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('These records are organization-scoped and stored outside the public Library corpus. Handoffs must preserve organization and access scope.', 'sustainable-catalyst-library'); ?></div>
            <?php if (!$configured) : ?>
                <p class="sc-pok__status"><?php esc_html_e('Private knowledge is not configured. An administrator must configure the Library backend and organization key.', 'sustainable-catalyst-library'); ?></p>
            <?php else : ?>
                <form class="sc-pok__search" role="search">
                    <label><span><?php esc_html_e('Search internal knowledge', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" maxlength="500" placeholder="policy, research topic, project, report…"></label>
                    <div class="sc-pok__filters">
                        <input type="text" name="object_type" maxlength="80" placeholder="Type (optional)">
                        <input type="text" name="project_key" maxlength="191" placeholder="Project (optional)">
                        <input type="text" name="department" maxlength="191" placeholder="Department (optional)">
                    </div>
                    <button type="submit"><?php esc_html_e('Search Private Knowledge', 'sustainable-catalyst-library'); ?></button>
                </form>
                <p class="sc-pok__status" aria-live="polite"></p>
                <div class="sc-pok__results"></div>
                <aside class="sc-pok__drawer" hidden aria-live="polite"></aside>
            <?php endif; ?>
            <footer><?php esc_html_e('Search activity is audit-recorded by fingerprint, not by storing the raw query text in the private access log.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}
