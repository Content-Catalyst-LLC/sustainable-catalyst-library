<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persistent account workspaces and optional Render synchronization.
 *
 * WordPress owns account identity and permissions. The Render service is an
 * optional secondary application store; browser clients never receive its API
 * key or database credentials.
 */
final class SC_Library_Workspaces {
    public const WORKSPACE_SCHEMA = 'sc-library-workspace/1.8';
    public const SYNC_SCHEMA = 'sc-library-sync/1.0';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('sc_library_sync_workspace', [$this, 'scheduled_sync'], 10, 1);
        add_shortcode('sc_library_account_workspaces', [$this, 'render_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_persistent_workspaces', 1);
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_workspaces';
    }

    public static function revisions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_workspace_revisions';
    }

    public static function collaborators_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_workspace_collaborators';
    }

    public static function sync_log_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_workspace_sync_log';
    }

    public static function storage_mode(): string {
        $mode = (string) get_option('sc_library_workspace_storage_mode', 'hybrid');
        return in_array($mode, ['local', 'account', 'hybrid'], true) ? $mode : 'hybrid';
    }

    public static function render_enabled(): bool {
        return self::enabled() && trim((string) get_option('sc_library_render_sync_url', '')) !== '' && trim((string) get_option('sc_library_render_sync_api_key', '')) !== '';
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }
        wp_enqueue_style('sc-library-workspace-sync', SC_LIBRARY_URL . 'assets/css/sc-library-workspace-sync.css', ['sc-library-notebook'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-workspace-sync', SC_LIBRARY_URL . 'assets/js/sc-library-workspace-sync.js', ['sc-library-notebook'], SC_LIBRARY_VERSION, true);

        $user = wp_get_current_user();
        wp_localize_script('sc-library-workspace-sync', 'SCLibraryAccountSync', [
            'enabled' => true,
            'loggedIn' => is_user_logged_in(),
            'storageMode' => self::storage_mode(),
            'autoSync' => (bool) get_option('sc_library_workspace_auto_sync', 0),
            'renderConfigured' => self::render_enabled(),
            'restRoot' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'loginUrl' => wp_login_url((string) ($_SERVER['REQUEST_URI'] ?? home_url('/'))),
            'user' => is_user_logged_in() ? [
                'id' => (int) $user->ID,
                'name' => (string) $user->display_name,
            ] : null,
            'maxBytes' => self::max_bytes(),
            'storageKey' => 'scLibraryWorkspaceV120',
            'schemas' => [
                'workspace' => self::WORKSPACE_SCHEMA,
                'sync' => self::SYNC_SCHEMA,
            ],
            'strings' => [
                'saved' => __('Workspace saved to your account.', 'sustainable-catalyst-library'),
                'loaded' => __('Account workspace loaded into this browser.', 'sustainable-catalyst-library'),
                'migrated' => __('Local workspace migrated to your account.', 'sustainable-catalyst-library'),
                'conflict' => __('The account copy changed elsewhere. Review the conflict before replacing either version.', 'sustainable-catalyst-library'),
                'syncQueued' => __('Render synchronization queued.', 'sustainable-catalyst-library'),
                'syncComplete' => __('Render synchronization completed.', 'sustainable-catalyst-library'),
                'exportFirst' => __('Export a backup before replacing the current browser workspace.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    private static function max_bytes(): int {
        $mb = min(25, max(1, (int) get_option('sc_library_workspace_max_mb', 8)));
        return $mb * 1024 * 1024;
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        SC_Library_Notebook::enqueue_assets();
        self::enqueue_assets();
        $atts = shortcode_atts([
            'title' => __('Account Workspaces', 'sustainable-catalyst-library'),
            'intro' => __('Save, synchronize, share, restore, and move Library research across devices.', 'sustainable-catalyst-library'),
        ], $atts, 'sc_library_account_workspaces');
        $account_workspace_title = sanitize_text_field((string) $atts['title']);
        $account_workspace_intro = sanitize_text_field((string) $atts['intro']);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-account-workspaces.php';
        return (string) ob_get_clean();
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Workspace Sync', 'sustainable-catalyst-library'),
            __('Workspace Sync', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-workspace-sync',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-workspace-sync')) {
            return;
        }
        wp_enqueue_style('sc-library-workspace-sync-admin', SC_LIBRARY_URL . 'assets/css/sc-library-workspace-sync.css', [], SC_LIBRARY_VERSION);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Library workspaces.', 'sustainable-catalyst-library'));
        }
        global $wpdb;
        $table = self::table();
        $log_table = self::sync_log_table();
        $counts = [
            'workspaces' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'owners' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT owner_user_id) FROM {$table}"),
            'pending' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE sync_status = %s", 'pending')),
            'errors' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE sync_status = %s", 'error')),
            'logs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}"),
        ];
        $health = $this->render_health();
        ?>
        <div class="wrap sc-library-workspace-sync-admin">
            <h1><?php esc_html_e('Persistent Workspaces and Render Synchronization', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('WordPress accounts own workspace permissions. Render synchronization is optional and receives only signed server-to-server workspace packets.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-sync-metrics">
                <?php foreach ($counts as $label => $value) : ?>
                    <article><strong><?php echo esc_html(number_format_i18n($value)); ?></strong><span><?php echo esc_html(ucfirst($label)); ?></span></article>
                <?php endforeach; ?>
            </div>
            <div class="card sc-library-sync-card">
                <h2><?php esc_html_e('Service state', 'sustainable-catalyst-library'); ?></h2>
                <p><strong><?php echo esc_html($health['label']); ?></strong></p>
                <p><?php echo esc_html($health['message']); ?></p>
                <?php if (!empty($health['version'])) : ?><p><code><?php echo esc_html($health['version']); ?></code></p><?php endif; ?>
            </div>
            <div class="card sc-library-sync-card">
                <h2><?php esc_html_e('Storage boundary', 'sustainable-catalyst-library'); ?></h2>
                <ul>
                    <li><?php esc_html_e('Local mode keeps research only in the current browser.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('Account mode stores encrypted-at-rest data according to the database host configuration and makes it available across signed-in devices.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('Hybrid mode keeps a local working copy and an explicit account revision.', 'sustainable-catalyst-library'); ?></li>
                    <li><?php esc_html_e('Render is an optional synchronized application store; WordPress remains the authority for identity and access.', 'sustainable-catalyst-library'); ?></li>
                </ul>
            </div>
            <div class="card sc-library-sync-card">
                <h2><?php esc_html_e('REST routes', 'sustainable-catalyst-library'); ?></h2>
                <?php foreach (['account/status','workspaces','workspaces/{uuid}','workspaces/{uuid}/history','workspaces/{uuid}/share','workspaces/{uuid}/sync','workspaces/render/status'] as $route) : ?>
                    <p><code>/wp-json/<?php echo esc_html(self::REST_NAMESPACE . '/library/' . $route); ?></code></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_health(): array {
        if (!self::render_enabled()) {
            return ['label' => __('Not configured', 'sustainable-catalyst-library'), 'message' => __('Account storage works in WordPress. Add a Render service URL only when secondary synchronization is required.'), 'version' => ''];
        }
        $url = trailingslashit((string) get_option('sc_library_render_sync_url', '')) . 'health';
        $response = wp_remote_get($url, ['timeout' => min(20, max(2, (int) get_option('sc_library_render_sync_timeout', 8)))]);
        if (is_wp_error($response)) {
            return ['label' => __('Unavailable', 'sustainable-catalyst-library'), 'message' => $response->get_error_message(), 'version' => ''];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            return ['label' => __('Unavailable', 'sustainable-catalyst-library'), 'message' => sprintf(__('Health endpoint returned HTTP %d.', 'sustainable-catalyst-library'), $code), 'version' => ''];
        }
        return ['label' => __('Online', 'sustainable-catalyst-library'), 'message' => __('The Render workspace service responded successfully.', 'sustainable-catalyst-library'), 'version' => is_array($body) ? (string) ($body['version'] ?? '') : ''];
    }

    public function register_routes(): void {
        $ns = self::REST_NAMESPACE;
        register_rest_route($ns, '/library/account/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'account_status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/library/workspaces', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_workspaces'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_workspace'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/workspaces/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_workspace'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_workspace'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_workspace'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/workspaces/(?P<uuid>[a-f0-9-]{36})/history', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'workspace_history'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/workspaces/(?P<uuid>[a-f0-9-]{36})/share', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_collaborators'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'share_workspace'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove_collaborator'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/workspaces/(?P<uuid>[a-f0-9-]{36})/sync', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'manual_sync'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/workspaces/render/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'render_status'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/workspaces/public/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'public_workspace'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function require_login(): bool|WP_Error {
        return is_user_logged_in() ? true : new WP_Error('rest_not_logged_in', __('Sign in to use account workspaces.', 'sustainable-catalyst-library'), ['status' => 401]);
    }

    public function account_status(): WP_REST_Response {
        $user = wp_get_current_user();
        return rest_ensure_response([
            'schema' => self::SYNC_SCHEMA,
            'enabled' => self::enabled(),
            'logged_in' => is_user_logged_in(),
            'storage_mode' => self::storage_mode(),
            'render_configured' => self::render_enabled(),
            'workspace_schema' => self::WORKSPACE_SCHEMA,
            'user' => is_user_logged_in() ? ['id' => (int) $user->ID, 'name' => (string) $user->display_name] : null,
        ]);
    }

    public function list_workspaces(): WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = self::table();
        $collab = self::collaborators_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT w.* FROM {$table} w LEFT JOIN {$collab} c ON c.workspace_id = w.id AND c.user_id = %d WHERE w.owner_user_id = %d OR c.user_id = %d ORDER BY w.updated_at DESC",
            $user_id, $user_id, $user_id
        ), ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::SYNC_SCHEMA, 'items' => array_map([$this, 'summary'], $rows)]);
    }

    public function create_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $payload = $this->request_payload($request);
        if (is_wp_error($payload)) return $payload;
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $encoded = wp_json_encode($payload['workspace'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = self::workspace_hash($payload['workspace']);
        $inserted = $wpdb->insert(self::table(), [
            'workspace_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'title' => $payload['title'],
            'description' => $payload['description'],
            'visibility' => $payload['visibility'],
            'schema_version' => self::WORKSPACE_SCHEMA,
            'workspace_json' => $encoded,
            'content_hash' => $hash,
            'revision' => 1,
            'sync_status' => self::render_enabled() ? 'pending' : 'local',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s']);
        if (!$inserted) return new WP_Error('sc_library_workspace_create_failed', __('The workspace could not be saved.', 'sustainable-catalyst-library'), ['status' => 500]);
        $row = $this->row($uuid);
        $this->store_revision($row, get_current_user_id(), 'created');
        $this->queue_sync($uuid);
        return new WP_REST_Response($this->record($row), 201);
    }

    public function get_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, false)) return $this->forbidden();
        return rest_ensure_response($this->record($row));
    }

    public function update_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = (string) $request['uuid'];
        $row = $this->row($uuid);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, true)) return $this->forbidden();
        $payload = $this->request_payload($request, $row);
        if (is_wp_error($payload)) return $payload;
        $expected = (int) ($request->get_param('expected_revision') ?? 0);
        if ($expected > 0 && $expected !== (int) $row['revision']) {
            return new WP_Error('sc_library_revision_conflict', __('The workspace changed after this browser loaded it.', 'sustainable-catalyst-library'), [
                'status' => 409,
                'server' => $this->record($row),
            ]);
        }
        $encoded = wp_json_encode($payload['workspace'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = self::workspace_hash($payload['workspace']);
        if (hash_equals((string) $row['content_hash'], $hash) && $payload['title'] === $row['title'] && $payload['description'] === $row['description'] && $payload['visibility'] === $row['visibility']) {
            return rest_ensure_response($this->record($row));
        }
        $revision = (int) $row['revision'] + 1;
        $updated = $wpdb->update(self::table(), [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'visibility' => $payload['visibility'],
            'schema_version' => self::WORKSPACE_SCHEMA,
            'workspace_json' => $encoded,
            'content_hash' => $hash,
            'revision' => $revision,
            'sync_status' => self::render_enabled() ? 'pending' : 'local',
            'updated_at' => current_time('mysql', true),
        ], ['workspace_uuid' => $uuid], ['%s','%s','%s','%s','%s','%s','%d','%s','%s'], ['%s']);
        if ($updated === false) return new WP_Error('sc_library_workspace_update_failed', __('The workspace could not be updated.', 'sustainable-catalyst-library'), ['status' => 500]);
        $row = $this->row($uuid);
        $this->store_revision($row, get_current_user_id(), 'updated');
        $this->queue_sync($uuid);
        return rest_ensure_response($this->record($row));
    }

    public function delete_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if ((int) $row['owner_user_id'] !== get_current_user_id()) return $this->forbidden();
        $wpdb->delete(self::collaborators_table(), ['workspace_id' => (int) $row['id']], ['%d']);
        $wpdb->delete(self::revisions_table(), ['workspace_id' => (int) $row['id']], ['%d']);
        $wpdb->delete(self::table(), ['id' => (int) $row['id']], ['%d']);
        $remote_warning = '';
        if (self::render_enabled()) {
            $remote_delete = $this->render_request('DELETE', (string) $row['workspace_uuid'], null, (int) $row['owner_user_id']);
            if (is_wp_error($remote_delete) && (int) (($remote_delete->get_error_data()['status'] ?? 0)) !== 404) {
                $remote_warning = $remote_delete->get_error_message();
            }
        }
        return rest_ensure_response(['deleted' => true, 'workspace_uuid' => (string) $row['workspace_uuid'], 'remote_warning' => $remote_warning]);
    }

    public function workspace_history(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, false)) return $this->forbidden();
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT revision, content_hash, change_type, created_by, created_at FROM ' . self::revisions_table() . ' WHERE workspace_id = %d ORDER BY revision DESC LIMIT 50',
            (int) $row['id']
        ), ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::SYNC_SCHEMA, 'workspace_uuid' => $row['workspace_uuid'], 'items' => $items]);
    }

    public function list_collaborators(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, false)) return $this->forbidden();
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT c.user_id, c.role, c.created_at, u.display_name, u.user_email FROM ' . self::collaborators_table() . ' c LEFT JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id WHERE c.workspace_id = %d ORDER BY u.display_name',
            (int) $row['id']
        ), ARRAY_A) ?: [];
        foreach ($items as &$item) unset($item['user_email']);
        return rest_ensure_response(['items' => $items]);
    }

    public function share_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if ((int) $row['owner_user_id'] !== get_current_user_id()) return $this->forbidden();
        $email = sanitize_email((string) $request->get_param('email'));
        $role = sanitize_key((string) $request->get_param('role'));
        if (!in_array($role, ['viewer','editor'], true)) $role = 'viewer';
        $user = get_user_by('email', $email);
        if (!$user) return new WP_Error('sc_library_collaborator_missing', __('That email is not connected to a WordPress account.', 'sustainable-catalyst-library'), ['status' => 404]);
        if ((int) $user->ID === (int) $row['owner_user_id']) return new WP_Error('sc_library_collaborator_owner', __('The owner already has full access.', 'sustainable-catalyst-library'), ['status' => 400]);
        $wpdb->replace(self::collaborators_table(), [
            'workspace_id' => (int) $row['id'],
            'user_id' => (int) $user->ID,
            'role' => $role,
            'invited_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'accepted_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%d','%s','%s']);
        return new WP_REST_Response(['shared' => true, 'user' => ['id' => (int) $user->ID, 'name' => (string) $user->display_name], 'role' => $role], 201);
    }

    public function remove_collaborator(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if ((int) $row['owner_user_id'] !== get_current_user_id()) return $this->forbidden();
        $user_id = absint($request->get_param('user_id'));
        $wpdb->delete(self::collaborators_table(), ['workspace_id' => (int) $row['id'], 'user_id' => $user_id], ['%d','%d']);
        return rest_ensure_response(['removed' => true, 'user_id' => $user_id]);
    }

    public function manual_sync(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, true)) return $this->forbidden();
        if (!self::render_enabled()) return new WP_Error('sc_library_render_not_configured', __('Render synchronization is not configured.', 'sustainable-catalyst-library'), ['status' => 400]);
        $strategy = sanitize_key((string) ($request->get_param('strategy') ?? 'auto'));
        if (!in_array($strategy, ['auto', 'local', 'remote'], true)) $strategy = 'auto';
        $result = $this->sync_workspace((string) $row['workspace_uuid'], $strategy);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function render_status(): WP_REST_Response {
        return rest_ensure_response(['schema' => self::SYNC_SCHEMA, 'configured' => self::render_enabled(), 'health' => $this->render_health()]);
    }

    public function public_workspace(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->row((string) $request['uuid']);
        if (!$row || $row['visibility'] !== 'public') return $this->not_found();
        return rest_ensure_response([
            'schema' => self::SYNC_SCHEMA,
            'workspace_schema' => self::WORKSPACE_SCHEMA,
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'visibility' => 'public',
            'revision' => (int) $row['revision'],
            'workspace' => json_decode((string) $row['workspace_json'], true),
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
        ]);
    }

    public function scheduled_sync(string $uuid): void {
        $this->sync_workspace($uuid, 'auto');
    }

    public function sync_workspace(string $uuid, string $strategy = 'auto'): array|WP_Error {
        global $wpdb;
        $row = $this->row($uuid);
        if (!$row) return $this->not_found();
        if (!self::render_enabled()) return new WP_Error('sc_library_render_not_configured', __('Render synchronization is not configured.', 'sustainable-catalyst-library'));

        $remote = $this->render_request('GET', $uuid, null, (int) $row['owner_user_id']);
        $remote_missing = false;
        if (is_wp_error($remote)) {
            $error_data = $remote->get_error_data();
            $remote_missing = is_array($error_data) && (int) ($error_data['status'] ?? 0) === 404;
            if (!$remote_missing) {
                $wpdb->update(self::table(), ['sync_status' => 'error', 'sync_error' => $remote->get_error_message()], ['id' => (int) $row['id']], ['%s','%s'], ['%d']);
                $this->log_sync($row, 'compare', 'error', $remote->get_error_message(), is_array($error_data) ? (int) ($error_data['status'] ?? 0) : 0);
                return $remote;
            }
            $remote = [];
        }

        $local_revision = (int) $row['revision'];
        $remote_revision = (int) ($remote['revision'] ?? 0);
        $remote_hash = (string) ($remote['content_hash'] ?? '');

        if (!$remote_missing && $strategy === 'remote') {
            return $this->pull_remote($row, $remote);
        }

        if (!$remote_missing && $strategy === 'auto') {
            if ($remote_revision > $local_revision) {
                return $this->pull_remote($row, $remote);
            }
            if ($remote_revision === $local_revision) {
                if ($remote_hash !== '' && hash_equals($remote_hash, (string) $row['content_hash'])) {
                    $wpdb->update(self::table(), [
                        'sync_status' => 'synced',
                        'sync_error' => '',
                        'last_synced_revision' => $remote_revision,
                        'render_etag' => (string) ($remote['etag'] ?? $remote_hash),
                        'last_synced_at' => current_time('mysql', true),
                    ], ['id' => (int) $row['id']], ['%s','%s','%d','%s','%s'], ['%d']);
                    return ['schema' => self::SYNC_SCHEMA, 'synced' => true, 'direction' => 'none', 'workspace_uuid' => $uuid, 'revision' => $remote_revision, 'content_hash' => $remote_hash];
                }
                $wpdb->update(self::table(), ['sync_status' => 'conflict', 'sync_error' => __('The WordPress and Render revisions contain different content.', 'sustainable-catalyst-library')], ['id' => (int) $row['id']], ['%s','%s'], ['%d']);
                $this->log_sync($row, 'compare', 'conflict', 'Equal revisions have different hashes.', 409);
                return new WP_Error('sc_library_render_conflict', __('The WordPress and Render revisions contain different content. Choose a source before continuing.', 'sustainable-catalyst-library'), ['status' => 409, 'local' => $this->record($row), 'remote' => $remote]);
            }
        }

        if (!$remote_missing && $strategy === 'local' && $remote_revision >= $local_revision && !hash_equals($remote_hash, (string) $row['content_hash'])) {
            $local_revision = $remote_revision + 1;
            $wpdb->update(self::table(), ['revision' => $local_revision, 'updated_at' => current_time('mysql', true)], ['id' => (int) $row['id']], ['%d','%s'], ['%d']);
            $row = $this->row($uuid) ?: $row;
            $this->store_revision($row, get_current_user_id(), 'conflict_local_wins');
        }

        $packet = [
            'schema' => self::SYNC_SCHEMA,
            'workspace_schema' => self::WORKSPACE_SCHEMA,
            'workspace_uuid' => $row['workspace_uuid'],
            'owner_external_id' => 'wordpress:' . (int) $row['owner_user_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'visibility' => $row['visibility'],
            'revision' => (int) $row['revision'],
            'content_hash' => $row['content_hash'],
            'workspace' => json_decode((string) $row['workspace_json'], true),
            'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
        ];
        $result = $this->render_request('PUT', $uuid, $packet, (int) $row['owner_user_id']);
        if (is_wp_error($result)) {
            $wpdb->update(self::table(), ['sync_status' => 'error', 'sync_error' => $result->get_error_message()], ['id' => (int) $row['id']], ['%s','%s'], ['%d']);
            $error_data = $result->get_error_data();
            $this->log_sync($row, 'push', 'error', $result->get_error_message(), is_array($error_data) ? (int) ($error_data['status'] ?? 0) : 0);
            return $result;
        }
        $remote_revision = (int) ($result['revision'] ?? $row['revision']);
        $remote_hash = (string) ($result['content_hash'] ?? $row['content_hash']);
        $wpdb->update(self::table(), [
            'sync_status' => 'synced',
            'sync_error' => '',
            'last_synced_revision' => $remote_revision,
            'render_etag' => (string) ($result['etag'] ?? $remote_hash),
            'last_synced_at' => current_time('mysql', true),
        ], ['id' => (int) $row['id']], ['%s','%s','%d','%s','%s'], ['%d']);
        $this->log_sync($row, 'push', 'success', '', 200);
        return ['schema' => self::SYNC_SCHEMA, 'synced' => true, 'direction' => 'push', 'workspace_uuid' => $uuid, 'revision' => $remote_revision, 'content_hash' => $remote_hash];
    }

    private function pull_remote(array $row, array $remote): array|WP_Error {
        global $wpdb;
        $workspace = $remote['workspace'] ?? null;
        if (!is_array($workspace)) {
            return new WP_Error('sc_library_render_invalid_workspace', __('Render returned an invalid workspace packet.', 'sustainable-catalyst-library'), ['status' => 502]);
        }
        $workspace['schema'] = self::WORKSPACE_SCHEMA;
        $encoded = wp_json_encode($workspace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > self::max_bytes()) {
            return new WP_Error('sc_library_render_workspace_too_large', __('The Render workspace exceeds the WordPress account-storage limit.', 'sustainable-catalyst-library'), ['status' => 413]);
        }
        $hash = self::workspace_hash($workspace);
        if (!hash_equals($hash, (string) ($remote['content_hash'] ?? ''))) {
            return new WP_Error('sc_library_render_hash_mismatch', __('Render returned a workspace whose content hash does not match.', 'sustainable-catalyst-library'), ['status' => 502]);
        }
        $wpdb->update(self::table(), [
            'title' => sanitize_text_field((string) ($remote['title'] ?? $row['title'])),
            'description' => sanitize_textarea_field((string) ($remote['description'] ?? $row['description'])),
            'visibility' => in_array(($remote['visibility'] ?? ''), ['private','shared','public'], true) ? $remote['visibility'] : 'private',
            'schema_version' => self::WORKSPACE_SCHEMA,
            'workspace_json' => $encoded,
            'content_hash' => $hash,
            'revision' => (int) ($remote['revision'] ?? $row['revision']),
            'sync_status' => 'synced',
            'sync_error' => '',
            'last_synced_revision' => (int) ($remote['revision'] ?? $row['revision']),
            'render_etag' => (string) ($remote['etag'] ?? $hash),
            'last_synced_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $row['id']], ['%s','%s','%s','%s','%s','%s','%d','%s','%s','%d','%s','%s','%s'], ['%d']);
        $updated = $this->row((string) $row['workspace_uuid']);
        if (!$updated) return $this->not_found();
        $this->store_revision($updated, get_current_user_id(), 'render_pull');
        $this->log_sync($updated, 'pull', 'success', '', 200);
        return ['schema' => self::SYNC_SCHEMA, 'synced' => true, 'direction' => 'pull', 'workspace_uuid' => $row['workspace_uuid'], 'revision' => (int) $updated['revision'], 'content_hash' => (string) $updated['content_hash']];
    }

    private function queue_sync(string $uuid): void {
        if (!self::render_enabled() || !(bool) get_option('sc_library_workspace_auto_sync', 0)) return;
        if (!wp_next_scheduled('sc_library_sync_workspace', [$uuid])) {
            wp_schedule_single_event(time() + 15, 'sc_library_sync_workspace', [$uuid]);
        }
    }

    private function render_request(string $method, string $uuid, ?array $packet, int $owner_user_id): array|WP_Error {
        $base = trailingslashit((string) get_option('sc_library_render_sync_url', ''));
        if ($base === '') return new WP_Error('sc_library_render_missing_url', __('Render synchronization URL is missing.', 'sustainable-catalyst-library'));
        $url = $base . 'api/v1/workspaces/' . rawurlencode($uuid);
        $body = $packet === null ? '' : wp_json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $key = (string) get_option('sc_library_render_sync_api_key', '');
        $timestamp = (string) time();
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $signature_base = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $body;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SC-Owner' => 'wordpress:' . $owner_user_id,
            'X-SC-Library-Timestamp' => $timestamp,
            'X-SC-Library-Signature' => hash_hmac('sha256', $signature_base, $key),
        ];
        if ($key !== '') $headers['Authorization'] = 'Bearer ' . $key;
        $response = wp_remote_request($url, [
            'method' => $method,
            'timeout' => min(30, max(2, (int) get_option('sc_library_render_sync_timeout', 8))),
            'headers' => $headers,
            'body' => $body,
        ]);
        if (is_wp_error($response)) return $response;
        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('sc_library_render_http_error', is_array($decoded) ? (string) ($decoded['detail'] ?? ('Render returned HTTP ' . $code)) : ('Render returned HTTP ' . $code), ['status' => $code, 'remote' => $decoded]);
        }
        return is_array($decoded) ? $decoded : [];
    }

    private static function workspace_hash(array $workspace): string {
        $canonical = self::canonicalize($workspace);
        return hash('sha256', (string) wp_json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'canonicalize'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        return $value;
    }

    private function request_payload(WP_REST_Request $request, ?array $existing = null): array|WP_Error {
        $workspace = $request->get_param('workspace');
        if (is_string($workspace)) $workspace = json_decode($workspace, true);
        if (!is_array($workspace) && $existing) $workspace = json_decode((string) $existing['workspace_json'], true);
        if (!is_array($workspace)) return new WP_Error('sc_library_workspace_invalid', __('A valid workspace object is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        $schema = (string) ($workspace['schema'] ?? '');
        if (!preg_match('/^sc-library-workspace\/1\.[0-7]$/', $schema)) {
            return new WP_Error('sc_library_workspace_schema', __('The workspace schema is not supported.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $workspace['schema'] = self::WORKSPACE_SCHEMA;
        $encoded = wp_json_encode($workspace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > self::max_bytes()) {
            return new WP_Error('sc_library_workspace_too_large', sprintf(__('The workspace exceeds the %d MB account-storage limit.', 'sustainable-catalyst-library'), (int) get_option('sc_library_workspace_max_mb', 8)), ['status' => 413]);
        }
        $visibility = sanitize_key((string) ($request->get_param('visibility') ?? ($existing['visibility'] ?? 'private')));
        if (!in_array($visibility, ['private','shared','public'], true)) $visibility = 'private';
        return [
            'workspace' => $workspace,
            'title' => sanitize_text_field((string) ($request->get_param('title') ?? ($existing['title'] ?? __('Research Workspace', 'sustainable-catalyst-library')))),
            'description' => sanitize_textarea_field((string) ($request->get_param('description') ?? ($existing['description'] ?? ''))),
            'visibility' => $visibility,
        ];
    }

    private function row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE workspace_uuid = %s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function can_access(array $row, bool $write): bool {
        global $wpdb;
        $user_id = get_current_user_id();
        if ($user_id <= 0) return false;
        if ((int) $row['owner_user_id'] === $user_id) return true;
        $role = $wpdb->get_var($wpdb->prepare('SELECT role FROM ' . self::collaborators_table() . ' WHERE workspace_id = %d AND user_id = %d', (int) $row['id'], $user_id));
        return $write ? $role === 'editor' : in_array($role, ['viewer','editor'], true);
    }

    private function summary(array $row): array {
        return [
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'visibility' => (string) $row['visibility'],
            'revision' => (int) $row['revision'],
            'content_hash' => (string) $row['content_hash'],
            'sync_status' => (string) $row['sync_status'],
            'sync_error' => (string) $row['sync_error'],
            'last_synced_revision' => (int) $row['last_synced_revision'],
            'last_synced_at' => $row['last_synced_at'] ? mysql_to_rfc3339((string) $row['last_synced_at']) : null,
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
            'owned' => (int) $row['owner_user_id'] === get_current_user_id(),
        ];
    }

    private function record(array $row): array {
        return array_merge($this->summary($row), [
            'schema' => self::SYNC_SCHEMA,
            'workspace_schema' => self::WORKSPACE_SCHEMA,
            'owner_user_id' => (int) $row['owner_user_id'],
            'workspace' => json_decode((string) $row['workspace_json'], true),
        ]);
    }

    private function store_revision(array $row, int $user_id, string $change_type): void {
        global $wpdb;
        $wpdb->insert(self::revisions_table(), [
            'workspace_id' => (int) $row['id'],
            'revision' => (int) $row['revision'],
            'content_hash' => (string) $row['content_hash'],
            'workspace_json' => (string) $row['workspace_json'],
            'change_type' => sanitize_key($change_type),
            'created_by' => $user_id,
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s','%s','%d','%s']);
    }

    private function log_sync(array $row, string $direction, string $status, string $message, int $response_code): void {
        global $wpdb;
        $wpdb->insert(self::sync_log_table(), [
            'workspace_id' => (int) $row['id'],
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'direction' => sanitize_key($direction),
            'status' => sanitize_key($status),
            'response_code' => $response_code,
            'message' => sanitize_textarea_field($message),
            'content_hash' => (string) $row['content_hash'],
            'created_at' => current_time('mysql', true),
        ], ['%d','%s','%s','%s','%d','%s','%s','%s']);
    }

    private function not_found(): WP_Error {
        return new WP_Error('sc_library_workspace_not_found', __('Workspace not found.', 'sustainable-catalyst-library'), ['status' => 404]);
    }

    private function forbidden(): WP_Error {
        return new WP_Error('sc_library_workspace_forbidden', __('You do not have permission to access this workspace.', 'sustainable-catalyst-library'), ['status' => 403]);
    }
}
