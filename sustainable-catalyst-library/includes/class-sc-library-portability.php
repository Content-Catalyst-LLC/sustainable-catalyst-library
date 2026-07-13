<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PostgreSQL and portable research-data export service.
 *
 * WordPress remains the canonical publishing source. This service creates a
 * normalized, application-oriented export rather than copying raw WordPress
 * tables or serialized plugin internals.
 */
final class SC_Library_Portability {
    public const EXPORT_SCHEMA = 'sc-library-portable-export/2.1';
    public const POSTGRES_SCHEMA = 'sustainable_catalyst_library';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;
    private SC_Library_Planner $planner;

    public function __construct(
        SC_Library_Indexer $indexer,
        SC_Library_Relationships $relationships,
        SC_Library_Planner $planner
    ) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
        $this->planner = $planner;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_sc_library_portable_export', [$this, 'handle_admin_export']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_library_portability', [$this, 'render_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_portability', 1);
    }

    public static function formats(): array {
        return [
            'postgresql_sql' => [
                'id' => 'postgresql_sql',
                'label' => __('PostgreSQL SQL', 'sustainable-catalyst-library'),
                'extension' => 'sql',
                'description' => __('Portable schema and INSERT statements that can be restored with psql.', 'sustainable-catalyst-library'),
            ],
            'csv_bundle' => [
                'id' => 'csv_bundle',
                'label' => __('CSV bundle', 'sustainable-catalyst-library'),
                'extension' => 'zip',
                'description' => __('One CSV per entity plus schema.sql, manifest.json, checksums, and restore notes.', 'sustainable-catalyst-library'),
            ],
            'jsonl_bundle' => [
                'id' => 'jsonl_bundle',
                'label' => __('JSONL bundle', 'sustainable-catalyst-library'),
                'extension' => 'zip',
                'description' => __('Line-delimited JSON files plus schema.sql, manifest.json, checksums, and restore notes.', 'sustainable-catalyst-library'),
            ],
            'json_snapshot' => [
                'id' => 'json_snapshot',
                'label' => __('JSON snapshot', 'sustainable-catalyst-library'),
                'extension' => 'json',
                'description' => __('A single inspectable snapshot of the selected normalized entities.', 'sustainable-catalyst-library'),
            ],
        ];
    }

    public static function scopes(): array {
        return [
            'complete' => __('Complete Library server data', 'sustainable-catalyst-library'),
            'registry' => __('Complete public registry', 'sustainable-catalyst-library'),
            'planner' => __('Content Planner and roadmap', 'sustainable-catalyst-library'),
            'documentation' => __('Foundations and documentation records', 'sustainable-catalyst-library'),
            'foundation_documents' => __('Foundation Documents, page text, and version history', 'sustainable-catalyst-library'),
            'relationships' => __('Relationships, terms, and resources', 'sustainable-catalyst-library'),
            'knowledge_graph' => __('Knowledge graph, relationship provenance, and diagnostics data', 'sustainable-catalyst-library'),
            'orchestration' => __('Research Librarian sessions, routes, and attributed action events', 'sustainable-catalyst-library'),
            'developer_api' => __('Developer API keys, webhook configuration, and delivery history without secrets', 'sustainable-catalyst-library'),
            'preservation' => __('Preservation snapshots, integrity checks, authority history, and archive manifests', 'sustainable-catalyst-library'),
            'readiness' => __('Production-readiness reports without credentials or private payloads', 'sustainable-catalyst-library'),
            'account_workspaces' => __('Persistent account workspaces and revisions', 'sustainable-catalyst-library'),
            'document_editions' => __('Server document jobs and frozen editions', 'sustainable-catalyst-library'),
            'multimedia' => __('Multimedia assets, clips, reels, and processing jobs', 'sustainable-catalyst-library'),
            'editorial' => __('Collaboration, reviews, comments, suggestions, and attribution history', 'sustainable-catalyst-library'),
            'schema' => __('Schema only', 'sustainable-catalyst-library'),
        ];
    }

    public static function modes(): array {
        return [
            'full' => __('Schema and data', 'sustainable-catalyst-library'),
            'schema' => __('Schema only', 'sustainable-catalyst-library'),
            'data' => __('Data only', 'sustainable-catalyst-library'),
        ];
    }

    public function register_settings(): void {
        register_setting('sc_library_settings', 'sc_library_enable_portability', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_export_schema_name', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = strtolower((string) $value);
                $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?: self::POSTGRES_SCHEMA;
                return trim($value, '_') ?: self::POSTGRES_SCHEMA;
            },
            'default' => self::POSTGRES_SCHEMA,
        ]);
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-portability')) return;
        wp_enqueue_style('sc-library-portability-admin', SC_LIBRARY_URL . 'assets/css/sc-library-portability.css', [], SC_LIBRARY_VERSION);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Portable Data Export', 'sustainable-catalyst-library'),
            __('Portable Data Export', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-portability',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export Library data.', 'sustainable-catalyst-library'));
        }
        $summary = $this->data_summary();
        ?>
        <div class="wrap sc-library-portability-admin">
            <h1><?php esc_html_e('Library Portable Data Export', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Create normalized PostgreSQL, CSV, JSONL, or JSON exports without copying raw WordPress tables. Private browser-local Notebook data is exported from the Notebook Import / Export tab.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-portability-admin__metrics">
                <?php foreach ($summary as $label => $value) : ?>
                    <article><strong><?php echo esc_html(number_format_i18n((int) $value)); ?></strong><span><?php echo esc_html($label); ?></span></article>
                <?php endforeach; ?>
            </div>

            <div class="card sc-library-portability-admin__card">
                <h2><?php esc_html_e('Create server export', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_portable_export">
                    <?php wp_nonce_field('sc_library_portable_export'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="sc-library-export-scope"><?php esc_html_e('Export scope', 'sustainable-catalyst-library'); ?></label></th>
                            <td><select id="sc-library-export-scope" name="scope">
                                <?php foreach (self::scopes() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-library-export-format"><?php esc_html_e('Format', 'sustainable-catalyst-library'); ?></label></th>
                            <td><select id="sc-library-export-format" name="format">
                                <?php foreach (self::formats() as $format) : ?><option value="<?php echo esc_attr($format['id']); ?>"><?php echo esc_html($format['label']); ?></option><?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-library-export-mode"><?php esc_html_e('Content mode', 'sustainable-catalyst-library'); ?></label></th>
                            <td><select id="sc-library-export-mode" name="mode">
                                <?php foreach (self::modes() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sc-library-export-schema"><?php esc_html_e('PostgreSQL schema', 'sustainable-catalyst-library'); ?></label></th>
                            <td><input id="sc-library-export-schema" name="schema_name" type="text" class="regular-text code" value="<?php echo esc_attr($this->schema_name()); ?>"><p class="description"><?php esc_html_e('Lowercase letters, numbers, and underscores only.', 'sustainable-catalyst-library'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Export safeguards', 'sustainable-catalyst-library'); ?></th>
                            <td>
                                <label><input type="checkbox" name="include_private_plans" value="1"> <?php esc_html_e('Include private, draft, and pending Content Planner records in administrator exports.', 'sustainable-catalyst-library'); ?></label><br>
                                <label><input type="checkbox" name="include_full_text" value="1"> <?php esc_html_e('Include normalized full post content in record payloads.', 'sustainable-catalyst-library'); ?></label>
                                <p class="description"><?php esc_html_e('Internal planning notes are included only when private plans are explicitly requested by an administrator.', 'sustainable-catalyst-library'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Download export', 'sustainable-catalyst-library')); ?>
                </form>
            </div>

            <div class="card sc-library-portability-admin__card">
                <h2><?php esc_html_e('Restore and archive workflow', 'sustainable-catalyst-library'); ?></h2>
                <pre><code>createdb sustainable_catalyst_library
psql -X --set ON_ERROR_STOP=on sustainable_catalyst_library &lt; sustainable-catalyst-library.sql

# Optional: create a compressed PostgreSQL custom archive after restore
pg_dump -Fc sustainable_catalyst_library -f sustainable-catalyst-library.backup</code></pre>
                <p><?php esc_html_e('The plugin generates portable SQL. PostgreSQL custom archives are created with pg_dump after restoration because WordPress does not run against PostgreSQL directly.', 'sustainable-catalyst-library'); ?></p>
            </div>
        </div>
        <?php
    }

    public function handle_admin_export(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export Library data.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_portable_export');

        $scope = sanitize_key((string) ($_POST['scope'] ?? 'complete'));
        $format = sanitize_key((string) ($_POST['format'] ?? 'postgresql_sql'));
        $mode = sanitize_key((string) ($_POST['mode'] ?? 'full'));
        $schema_name = $this->sanitize_schema_name((string) ($_POST['schema_name'] ?? $this->schema_name()));
        $include_private_plans = !empty($_POST['include_private_plans']);
        $include_full_text = !empty($_POST['include_full_text']);

        if (!isset(self::scopes()[$scope])) $scope = 'complete';
        if (!isset(self::formats()[$format])) $format = 'postgresql_sql';
        if (!isset(self::modes()[$mode])) $mode = 'full';
        if ($scope === 'schema') $mode = 'schema';

        $data = $this->collect_export_data($scope, $include_private_plans, $include_full_text);
        $data['manifest']['postgresql_schema'] = $schema_name;
        $date = gmdate('Ymd-His');
        $base = sanitize_file_name("sustainable-catalyst-library-{$scope}-{$date}");

        if ($format === 'postgresql_sql') {
            $content = $this->postgres_sql($data, $mode, $schema_name);
            $this->send_string_download($content, $base . '.sql', 'application/sql; charset=utf-8');
        }
        if ($format === 'json_snapshot') {
            $snapshot = ['manifest' => $data['manifest'], 'data' => $mode === 'schema' ? [] : $data['entities'], 'postgresql_schema' => $mode === 'data' ? '' : $this->postgres_schema_sql($schema_name)];
            $this->send_string_download(wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $base . '.json', 'application/json; charset=utf-8');
        }
        if (in_array($format, ['csv_bundle', 'jsonl_bundle'], true)) {
            $zip = $this->create_bundle($data, $format, $mode, $schema_name, $base);
            $this->send_file_download($zip['path'], $base . '.zip', 'application/zip', $zip['cleanup']);
        }

        wp_die(esc_html__('Unsupported export format.', 'sustainable-catalyst-library'));
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/library/export/formats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response([
                'schema' => self::EXPORT_SCHEMA,
                'workspace_schema' => SC_Library_Notebook::SCHEMA,
                'formats' => array_values(self::formats()),
                'scopes' => self::scopes(),
                'modes' => self::modes(),
            ]),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::REST_NAMESPACE, '/library/export/postgresql-schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response {
                $schema = $this->sanitize_schema_name((string) ($request['schema'] ?: $this->schema_name()));
                return rest_ensure_response([
                    'schema' => self::EXPORT_SCHEMA,
                    'postgresql_schema' => $schema,
                    'sql' => $this->postgres_schema_sql($schema),
                ]);
            },
            'permission_callback' => '__return_true',
            'args' => ['schema' => ['sanitize_callback' => 'sanitize_key', 'default' => '']],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/library/export/manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (): WP_REST_Response {
                $data = $this->collect_export_data('complete', false, false);
                return rest_ensure_response($data['manifest']);
            },
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) return '';
        self::enqueue_assets();
        $atts = shortcode_atts([
            'title' => __('Portable Research Data', 'sustainable-catalyst-library'),
            'intro' => __('Export this browser’s Research Notebook as PostgreSQL SQL, JSONL, or a versioned JSON manifest. Server registry exports remain available to administrators.', 'sustainable-catalyst-library'),
            'show_server_links' => 'true',
        ], $atts, 'sc_library_portability');
        $portability_title = sanitize_text_field((string) $atts['title']);
        $portability_intro = sanitize_text_field((string) $atts['intro']);
        $portability_show_server_links = filter_var($atts['show_server_links'], FILTER_VALIDATE_BOOLEAN);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-portability.php';
        return (string) ob_get_clean();
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) return;
        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-portability', SC_LIBRARY_URL . 'assets/css/sc-library-portability.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-portability', SC_LIBRARY_URL . 'assets/js/sc-library-portability.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-portability', 'SCLibraryPortability', [
            'version' => SC_LIBRARY_VERSION,
            'exportSchema' => self::EXPORT_SCHEMA,
            'workspaceSchema' => SC_Library_Notebook::SCHEMA,
            'storageKey' => 'scLibraryWorkspaceV120',
            'postgresSchema' => (string) get_option('sc_library_export_schema_name', self::POSTGRES_SCHEMA),
            'schemaEndpoint' => rest_url(self::REST_NAMESPACE . '/library/export/postgresql-schema'),
            'registryEndpoint' => rest_url(self::REST_NAMESPACE . '/library/registry?per_page=100'),
            'strings' => [
                'missingWorkspace' => __('No browser-local Research Notebook workspace was found.', 'sustainable-catalyst-library'),
                'invalidWorkspace' => __('The browser-local workspace could not be read.', 'sustainable-catalyst-library'),
                'downloadReady' => __('Portable workspace export created.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    private function data_summary(): array {
        global $wpdb;
        $plans = wp_count_posts(SC_Library_Planner::POST_TYPE);
        $plan_count = 0;
        if ($plans) {
            foreach ((array) $plans as $status => $count) {
                if (!in_array($status, ['trash', 'auto-draft'], true)) $plan_count += (int) $count;
            }
        }
        return [
            __('Indexed records', 'sustainable-catalyst-library') => $this->indexer->count_indexed(),
            __('Typed relationships', 'sustainable-catalyst-library') => $this->relationships->count(),
            __('Planning records', 'sustainable-catalyst-library') => $plan_count,
            __('Documentation records', 'sustainable-catalyst-library') => (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_sc_library_doc_status'"),
            __('Foundation Documents', 'sustainable-catalyst-library') => class_exists('SC_Library_Foundation_Documents') ? (int) wp_count_posts(SC_Library_Foundation_Documents::POST_TYPE)->publish : 0,
            __('Extracted PDF pages', 'sustainable-catalyst-library') => class_exists('SC_Library_Foundation_Documents') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Foundation_Documents::pages_table()) : 0,
            __('Account workspaces', 'sustainable-catalyst-library') => class_exists('SC_Library_Workspaces') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Workspaces::table()) : 0,
            __('Multimedia assets', 'sustainable-catalyst-library') => class_exists('SC_Library_Multimedia') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Multimedia::assets_table()) : 0,
            __('Multimedia clips', 'sustainable-catalyst-library') => class_exists('SC_Library_Multimedia') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Multimedia::clips_table()) : 0,
            __('Editorial reviews', 'sustainable-catalyst-library') => class_exists('SC_Library_Collaboration') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Collaboration::reviews_table()) : 0,
            __('Knowledge graph nodes', 'sustainable-catalyst-library') => class_exists('SC_Library_Knowledge_Graph') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . (new SC_Library_Knowledge_Graph($this->indexer, $this->relationships))->nodes_table() . " WHERE status = 'active'") : 0,
            __('Knowledge graph edges', 'sustainable-catalyst-library') => class_exists('SC_Library_Knowledge_Graph') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . (new SC_Library_Knowledge_Graph($this->indexer, $this->relationships))->edges_table()) : 0,
            __('Research Librarian sessions', 'sustainable-catalyst-library') => class_exists('SC_Library_Orchestrator') ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SC_Library_Orchestrator::sessions_table()) : 0,
        ];
    }

    private function schema_name(): string {
        return $this->sanitize_schema_name((string) get_option('sc_library_export_schema_name', self::POSTGRES_SCHEMA));
    }

    private function sanitize_schema_name(string $value): string {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?: self::POSTGRES_SCHEMA;
        return trim($value, '_') ?: self::POSTGRES_SCHEMA;
    }

    /**
     * @return array{manifest:array,entities:array<string,array<int,array>>}
     */
    private function collect_export_data(string $scope, bool $include_private_plans, bool $include_full_text): array {
        global $wpdb;

        $index_rows = $wpdb->get_results("SELECT * FROM {$this->indexer->table_name()} WHERE status = 'publish' ORDER BY post_id ASC", ARRAY_A) ?: [];
        $public_registry = $this->planner->all_public_registry_records();
        $registry_by_id = [];
        foreach ($public_registry as $record) $registry_by_id[(int) $record['id']] = $record;

        $records = [];
        foreach ($index_rows as $row) {
            $post_id = (int) $row['post_id'];
            $post = get_post($post_id);
            if (!$post) continue;
            $registry = $registry_by_id[$post_id] ?? [];
            $payload = [
                'index' => $row,
                'registry' => $registry,
                'resource_flags' => json_decode((string) ($row['resource_flags'] ?? '[]'), true) ?: [],
            ];
            if ($include_full_text) {
                $payload['content_html'] = apply_filters('the_content', $post->post_content);
                $payload['content_text'] = wp_strip_all_tags(strip_shortcodes($post->post_content), true);
            }
            $records[] = [
                'record_id' => $post_id,
                'record_identifier' => (string) ($row['record_identifier'] ?: sprintf('sc:library:%s:%d', $post->post_type, $post_id)),
                'kind' => (string) ($registry['kind'] ?? ($post->post_type === SC_Library_Planner::POST_TYPE ? 'plan' : 'published')),
                'post_type' => $post->post_type,
                'title' => (string) $row['title'],
                'excerpt' => (string) $row['excerpt'],
                'canonical_url' => (string) $row['permalink'],
                'record_state' => (string) ($registry['record_state'] ?? 'published'),
                'content_type' => (string) ($registry['content_type'] ?? $post->post_type),
                'area' => (string) ($registry['area'] ?? ''),
                'product' => (string) ($registry['product'] ?? ''),
                'responsible_area' => (string) ($registry['responsible'] ?? ''),
                'published_at' => $this->rfc3339_or_null($post->post_date_gmt ?: $post->post_date),
                'modified_at' => $this->rfc3339_or_null($post->post_modified_gmt ?: $post->post_modified),
                'expected_release' => $registry['expected_release'] ?? [],
                'article_map_id' => (int) ($registry['article_map_id'] ?? 0),
                'series_order' => (float) ($row['series_order'] ?? 0),
                'authoritative' => !empty($registry['authoritative']),
                'authority_label' => (string) ($registry['authority_label'] ?? ''),
                'historical' => !empty($registry['historical']),
                'payload' => $payload,
            ];
        }

        $record_ids = array_map(static fn($row) => (int) $row['record_id'], $records);
        $record_id_set = array_fill_keys($record_ids, true);
        $terms = $this->export_terms();
        $record_terms = $this->export_record_terms($record_ids);
        $relationships = $wpdb->get_results("SELECT * FROM {$this->relationships->table_name()} ORDER BY id ASC", ARRAY_A) ?: [];
        $relationships = array_values(array_filter($relationships, static fn($row) => isset($record_id_set[(int) $row['source_post_id']]) && isset($record_id_set[(int) $row['target_post_id']])));
        $resources = $this->export_resources($record_ids);
        $documentation = $this->export_documentation($record_ids);
        $plans = $this->export_plans($include_private_plans);
        $plan_dependencies = $this->export_plan_dependencies($plans);
        $account_workspaces = $this->export_account_workspaces();
        $account_workspace_revisions = $this->export_account_workspace_revisions();
        $account_workspace_collaborators = $this->export_account_workspace_collaborators();
        $account_workspace_sync_log = $this->export_account_workspace_sync_log();
        $document_jobs = $this->export_document_jobs();
        $document_editions = $this->export_document_editions();
        $foundation_documents = $this->export_foundation_documents();
        $pdf_pages = $this->export_pdf_pages();
        $foundation_versions = $this->export_foundation_versions();
        $media_assets = $this->export_media_assets();
        $media_clips = $this->export_media_clips();
        $media_reels = $this->export_media_reels();
        $media_jobs = $this->export_media_jobs();
        $editorial_reviews = $this->export_editorial_reviews();
        $editorial_participants = $this->export_editorial_participants();
        $editorial_comments = $this->export_editorial_comments();
        $editorial_suggestions = $this->export_editorial_suggestions();
        $editorial_events = $this->export_editorial_events();
        $graph_nodes = $this->export_graph_nodes();
        $graph_edges = $this->export_graph_edges();
        $orchestration_sessions = $this->export_orchestration_sessions();
        $orchestration_events = $this->export_orchestration_events();
        $api_keys = $this->export_api_keys();
        $webhooks = $this->export_webhooks();
        $webhook_deliveries = $this->export_webhook_deliveries();
        $preservation_snapshots = $this->export_preservation_snapshots();
        $integrity_checks = $this->export_integrity_checks();
        $authority_history = $this->export_authority_history();
        $readiness_runs = $this->export_readiness_runs();

        if ($scope === 'registry') {
            $public_ids = array_fill_keys(array_map(static fn($r) => (int) $r['id'], $public_registry), true);
            $records = array_values(array_filter($records, static fn($row) => isset($public_ids[(int) $row['record_id']])));
            $record_ids = array_map(static fn($row) => (int) $row['record_id'], $records);
            $record_terms = $this->filter_rows_by_ids($record_terms, 'record_id', $record_ids);
            $relationships = array_values(array_filter($relationships, static fn($row) => isset($public_ids[(int) $row['source_post_id']]) && isset($public_ids[(int) $row['target_post_id']])));
            $resources = $this->filter_rows_by_ids($resources, 'record_id', $record_ids);
            $documentation = $this->filter_rows_by_ids($documentation, 'record_id', $record_ids);
            $plans = array_values(array_filter($plans, static fn($row) => !empty($row['public'])));
            $plan_dependencies = $this->filter_rows_by_ids($plan_dependencies, 'plan_id', array_column($plans, 'plan_id'));
            $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'planner') {
            $records = [];
            $terms = [];
            $record_terms = [];
            $relationships = [];
            $resources = [];
            $documentation = [];
            $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'documentation') {
            $doc_ids = array_map(static fn($row) => (int) $row['record_id'], $documentation);
            $records = $this->filter_rows_by_ids($records, 'record_id', $doc_ids);
            $record_terms = $this->filter_rows_by_ids($record_terms, 'record_id', $doc_ids);
            $resources = $this->filter_rows_by_ids($resources, 'record_id', $doc_ids);
            $relationships = array_values(array_filter($relationships, static fn($row) => in_array((int) $row['source_post_id'], $doc_ids, true) || in_array((int) $row['target_post_id'], $doc_ids, true)));
            $plans = [];
            $plan_dependencies = [];
            $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'foundation_documents') {
            $foundation_record_ids = array_map(static fn($row) => (int) $row['record_id'], $foundation_documents);
            $records = $this->filter_rows_by_ids($records, 'record_id', $foundation_record_ids);
            $record_terms = $this->filter_rows_by_ids($record_terms, 'record_id', $foundation_record_ids);
            $resources = $this->filter_rows_by_ids($resources, 'record_id', $foundation_record_ids);
            $documentation = $this->filter_rows_by_ids($documentation, 'record_id', $foundation_record_ids);
            $relationships = array_values(array_filter($relationships, static fn($row) => in_array((int) $row['source_post_id'], $foundation_record_ids, true) || in_array((int) $row['target_post_id'], $foundation_record_ids, true)));
            $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'relationships') {
            $records = [];
            $documentation = [];
            $plans = [];
            $plan_dependencies = [];
            $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'account_workspaces') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'document_editions') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'multimedia') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'editorial') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = [];
        } elseif ($scope === 'schema') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        }


        if ($scope === 'knowledge_graph') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = [];
        } elseif ($scope === 'registry') {
            $graph_nodes = array_values(array_filter($graph_nodes, static fn($row) => ($row['visibility'] ?? '') === 'public' && ($row['status'] ?? '') === 'active'));
            $public_graph_ids = array_fill_keys(array_map(static fn($row) => (int) $row['graph_node_id'], $graph_nodes), true);
            $graph_edges = array_values(array_filter($graph_edges, static fn($row) => ($row['visibility'] ?? '') === 'public' && isset($public_graph_ids[(int) $row['source_node_id']]) && isset($public_graph_ids[(int) $row['target_node_id']])));
        } elseif (!in_array($scope, ['complete', 'relationships'], true)) {
            $graph_nodes = $graph_edges = [];
        }

        if ($scope === 'orchestration') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = $graph_nodes = $graph_edges = [];
        } elseif ($scope !== 'complete') {
            $orchestration_sessions = $orchestration_events = [];
        }

        if ($scope === 'developer_api') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = $graph_nodes = $graph_edges = $orchestration_sessions = $orchestration_events = [];
        } elseif ($scope !== 'complete') {
            $api_keys = $webhooks = $webhook_deliveries = [];
        }

        if ($scope === 'preservation') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $foundation_documents = $pdf_pages = $foundation_versions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = $graph_nodes = $graph_edges = $orchestration_sessions = $orchestration_events = $api_keys = $webhooks = $webhook_deliveries = [];
        } elseif ($scope !== 'complete') {
            $preservation_snapshots = $integrity_checks = $authority_history = [];
        }


        if ($scope === 'readiness') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = $account_workspaces = $account_workspace_revisions = $account_workspace_collaborators = $account_workspace_sync_log = $document_jobs = $document_editions = $foundation_documents = $pdf_pages = $foundation_versions = $media_assets = $media_clips = $media_reels = $media_jobs = $editorial_reviews = $editorial_participants = $editorial_comments = $editorial_suggestions = $editorial_events = $graph_nodes = $graph_edges = $orchestration_sessions = $orchestration_events = $api_keys = $webhooks = $webhook_deliveries = $preservation_snapshots = $integrity_checks = $authority_history = [];
        } elseif ($scope !== 'complete') {
            $readiness_runs = [];
        }

        // Embedded Foundation Document data follows the canonical record scope.
        if ($scope === 'registry') {
            $public_ids = array_fill_keys(array_map(static fn($row) => (int) $row['record_id'], $records), true);
            $foundation_documents = array_values(array_filter($foundation_documents, static fn($row) => isset($public_ids[(int) $row['record_id']])));
            $foundation_ids = array_fill_keys(array_map(static fn($row) => (int) $row['record_id'], $foundation_documents), true);
            $pdf_pages = array_values(array_filter($pdf_pages, static fn($row) => isset($foundation_ids[(int) $row['record_id']])));
            $foundation_versions = array_values(array_filter($foundation_versions, static fn($row) => isset($foundation_ids[(int) $row['record_id']])));
        } elseif (in_array($scope, ['documentation', 'foundation_documents'], true)) {
            $foundation_ids = array_fill_keys(array_map(static fn($row) => (int) $row['record_id'], $foundation_documents), true);
            $pdf_pages = array_values(array_filter($pdf_pages, static fn($row) => isset($foundation_ids[(int) $row['record_id']])));
            $foundation_versions = array_values(array_filter($foundation_versions, static fn($row) => isset($foundation_ids[(int) $row['record_id']])));
        } elseif ($scope === 'schema') {
            $foundation_documents = $pdf_pages = $foundation_versions = [];
        } elseif (!in_array($scope, ['complete'], true)) {
            $foundation_documents = $pdf_pages = $foundation_versions = [];
        }

        $entities = [
            'records' => $records,
            'terms' => $terms,
            'record_terms' => $record_terms,
            'relationships' => array_map([$this, 'normalize_relationship'], $relationships),
            'graph_nodes' => $graph_nodes,
            'graph_edges' => $graph_edges,
            'resources' => $resources,
            'documentation' => $documentation,
            'plans' => $plans,
            'plan_dependencies' => $plan_dependencies,
            'account_workspaces' => $account_workspaces,
            'account_workspace_revisions' => $account_workspace_revisions,
            'account_workspace_collaborators' => $account_workspace_collaborators,
            'account_workspace_sync_log' => $account_workspace_sync_log,
            'document_jobs' => $document_jobs,
            'document_editions' => $document_editions,
            'foundation_documents' => $foundation_documents,
            'pdf_pages' => $pdf_pages,
            'foundation_versions' => $foundation_versions,
            'media_assets' => $media_assets,
            'media_clips' => $media_clips,
            'media_reels' => $media_reels,
            'media_jobs' => $media_jobs,
            'editorial_reviews' => $editorial_reviews,
            'editorial_participants' => $editorial_participants,
            'editorial_comments' => $editorial_comments,
            'editorial_suggestions' => $editorial_suggestions,
            'editorial_events' => $editorial_events,
            'orchestration_sessions' => $orchestration_sessions,
            'orchestration_events' => $orchestration_events,
            'api_keys' => $api_keys,
            'webhooks' => $webhooks,
            'webhook_deliveries' => $webhook_deliveries,
            'preservation_snapshots' => $preservation_snapshots,
            'integrity_checks' => $integrity_checks,
            'authority_history' => $authority_history,
            'readiness_runs' => $readiness_runs,
        ];

        $counts = [];
        foreach ($entities as $name => $rows) $counts[$name] = count($rows);
        $manifest = [
            'schema' => self::EXPORT_SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'workspace_schema' => SC_Library_Notebook::SCHEMA,
            'generated_at' => gmdate('c'),
            'site_url' => home_url('/'),
            'scope' => $scope,
            'source_of_truth' => 'WordPress publishing and Sustainable Catalyst Library metadata',
            'postgresql_schema' => $this->schema_name(),
            'counts' => $counts,
            'entities' => array_keys($entities),
            'notes' => [
                'Browser-local Notebook content is exported separately from the Notebook or sc_library_portability shortcode.',
                'Persistent account workspaces are administrator-only server exports and contain user-authored research data.',
                'Private planning data is excluded unless an administrator explicitly enables it.',
                'PDFs and repository records remain references; binary media is not embedded in data exports.',
                'Server document job records and frozen edition manifests are exported without embedding PDF binaries.',
                'Foundation Document exports include citation metadata, extraction diagnostics, page-aware text, and version manifests; PDF binaries remain Media Library references.',
                'Multimedia exports preserve asset, clip, reel, rights, transcript, and processing metadata without embedding video or audio binaries.',
                'Knowledge graph exports preserve normalized entities, relationship confidence, provenance, visibility, verification state, and board-promotion lineage.',
                'Research Librarian orchestration exports are administrator-only and may contain user research questions, recommended routes, and attributed action history.',
                'Developer API exports omit API-key hashes, webhook signing secrets, and full webhook payload bodies.',
                'Preservation exports include immutable snapshot payloads, checksums, manifests, integrity outcomes, authority changes, retention dates, and hold flags without deleting canonical WordPress records.',
                'Production-readiness exports preserve status summaries and remediation findings without API secrets, private workspace content, or authentication material.',
            ],
        ];
        return ['manifest' => $manifest, 'entities' => $entities];
    }

    private function export_foundation_documents(): array {
        if (!class_exists('SC_Library_Foundation_Documents')) return [];
        $posts = get_posts([
            'post_type' => SC_Library_Foundation_Documents::POST_TYPE,
            'post_status' => ['publish','draft','private'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        return array_map(static function (WP_Post $post): array {
            $payload = SC_Library_Foundation_Documents::public_payload($post, true);
            return [
                'record_id' => (int) $post->ID,
                'attachment_id' => absint(get_post_meta($post->ID, '_sc_foundation_pdf_attachment_id', true)),
                'pdf_url' => (string) ($payload['pdf_url'] ?? ''),
                'version_label' => (string) ($payload['metadata']['version'] ?? ''),
                'publication_date' => (string) ($payload['metadata']['publication_date'] ?? ''),
                'author_name' => (string) ($payload['metadata']['author'] ?? ''),
                'publisher_name' => (string) ($payload['metadata']['publisher'] ?? ''),
                'doi' => (string) ($payload['metadata']['doi'] ?? ''),
                'language_code' => (string) ($payload['metadata']['language'] ?? 'en'),
                'download_enabled' => !empty($payload['download_enabled']),
                'viewer_enabled' => !empty($payload['viewer_enabled']),
                'extraction_status' => (string) ($payload['extraction']['status'] ?? 'not_started'),
                'page_count' => (int) ($payload['extraction']['page_count'] ?? 0),
                'character_count' => (int) ($payload['extraction']['character_count'] ?? 0),
                'extracted_at' => ($payload['extraction']['extracted_at'] ?? '') ?: null,
                'extraction_error' => (string) ($payload['extraction']['error'] ?? ''),
                'related_record_ids' => (array) ($payload['metadata']['related_ids'] ?? []),
                'payload' => $payload,
            ];
        }, $posts ?: []);
    }

    private function export_pdf_pages(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Foundation_Documents')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Foundation_Documents::pages_table() . ' ORDER BY post_id ASC, page_number ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'pdf_page_id' => (int) $row['id'],
            'record_id' => (int) $row['post_id'],
            'page_number' => (int) $row['page_number'],
            'page_text' => (string) $row['page_text'],
            'character_count' => (int) $row['character_count'],
            'content_hash' => (string) $row['content_hash'],
            'extracted_at' => mysql_to_rfc3339((string) $row['extracted_at']),
        ], $rows);
    }

    private function export_foundation_versions(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Foundation_Documents')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Foundation_Documents::versions_table() . ' ORDER BY post_id ASC, id ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'foundation_version_id' => (int) $row['id'],
            'record_id' => (int) $row['post_id'],
            'attachment_id' => (int) $row['attachment_id'],
            'version_label' => (string) $row['version_label'],
            'filename' => (string) $row['filename'],
            'pdf_url' => (string) $row['pdf_url'],
            'sha256' => (string) $row['sha256'],
            'page_count' => (int) $row['page_count'],
            'created_by' => (int) $row['created_by'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'payload' => json_decode((string) $row['metadata_json'], true) ?: [],
        ], $rows);
    }

    private function export_preservation_snapshots(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Preservation')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Preservation::snapshots_table() . ' ORDER BY record_id ASC, id ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'preservation_snapshot_id' => (int) $row['id'],
            'snapshot_uuid' => (string) $row['snapshot_uuid'],
            'record_id' => (int) $row['record_id'],
            'record_type' => (string) $row['record_type'],
            'title' => (string) $row['title'],
            'canonical_url' => (string) $row['canonical_url'],
            'version_label' => (string) $row['version_label'],
            'snapshot_status' => (string) $row['snapshot_status'],
            'reason' => (string) $row['reason'],
            'source_hash' => (string) $row['source_hash'],
            'manifest_hash' => (string) $row['manifest_hash'],
            'content_html' => (string) $row['content_html'],
            'content_text' => (string) $row['content_text'],
            'metadata' => json_decode((string) $row['metadata_json'], true) ?: [],
            'relationships' => json_decode((string) $row['relationships_json'], true) ?: [],
            'resources' => json_decode((string) $row['resources_json'], true) ?: [],
            'manifest' => json_decode((string) $row['manifest_json'], true) ?: [],
            'supersedes_uuid' => (string) $row['supersedes_uuid'],
            'is_current' => !empty($row['is_current']),
            'legal_hold' => !empty($row['legal_hold']),
            'retention_until' => $row['retention_until'] ? mysql_to_rfc3339((string) $row['retention_until']) : null,
            'created_by' => (int) $row['created_by'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
        ], $rows);
    }

    private function export_integrity_checks(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Preservation')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Preservation::checks_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'integrity_check_id' => (int) $row['id'],
            'check_uuid' => (string) $row['check_uuid'],
            'run_uuid' => (string) $row['run_uuid'],
            'record_id' => (int) $row['record_id'],
            'object_type' => (string) $row['object_type'],
            'check_type' => (string) $row['check_type'],
            'target_url' => (string) $row['target_url'],
            'expected_hash' => (string) $row['expected_hash'],
            'actual_hash' => (string) $row['actual_hash'],
            'status' => (string) $row['status'],
            'response_code' => (int) $row['response_code'],
            'message' => (string) $row['message'],
            'checked_at' => mysql_to_rfc3339((string) $row['checked_at']),
            'payload' => json_decode((string) $row['details_json'], true) ?: [],
        ], $rows);
    }

    private function export_authority_history(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Preservation')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Preservation::authority_table() . ' ORDER BY record_id ASC, id ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'authority_history_id' => (int) $row['id'],
            'authority_uuid' => (string) $row['authority_uuid'],
            'record_id' => (int) $row['record_id'],
            'document_status' => (string) $row['document_status'],
            'authority_type' => (string) $row['authority_type'],
            'authority_url' => (string) $row['authority_url'],
            'version_label' => (string) $row['version_label'],
            'responsible_area' => (string) $row['responsible_area'],
            'supersedes_id' => (int) $row['supersedes_id'],
            'superseded_by_id' => (int) $row['superseded_by_id'],
            'changed_by' => (int) $row['changed_by'],
            'changed_at' => mysql_to_rfc3339((string) $row['changed_at']),
            'payload' => json_decode((string) $row['payload_json'], true) ?: [],
        ], $rows);
    }


    private function export_readiness_runs(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Hardening')) return [];
        $table = SC_Library_Hardening::runs_table();
        $exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return [];
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'readiness_run_id' => (int) $row['id'],
            'run_uuid' => (string) $row['run_uuid'],
            'overall_status' => (string) $row['overall_status'],
            'score' => (int) $row['score'],
            'created_by' => (int) $row['created_by'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'report' => json_decode((string) $row['report_json'], true) ?: [],
        ], $rows);
    }

    private function export_api_keys(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Developer_API')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Developer_API::keys_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'api_key_id' => (int) $row['id'],
                'key_uuid' => (string) $row['key_uuid'],
                'name' => (string) $row['name'],
                'key_prefix' => (string) $row['key_prefix'],
                'scopes' => json_decode((string) $row['scopes_json'], true) ?: [],
                'rate_limit_per_hour' => (int) $row['rate_limit_per_hour'],
                'status' => (string) $row['status'],
                'last_used_at' => $row['last_used_at'] ? mysql_to_rfc3339((string) $row['last_used_at']) : null,
                'expires_at' => $row['expires_at'] ? mysql_to_rfc3339((string) $row['expires_at']) : null,
                'created_by' => (int) $row['created_by'],
                'created_at' => mysql_to_rfc3339((string) $row['created_at']),
                'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
                'secret_exported' => false,
            ];
        }, $rows);
    }

    private function export_webhooks(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Developer_API')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Developer_API::webhooks_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'webhook_id' => (int) $row['id'],
                'webhook_uuid' => (string) $row['webhook_uuid'],
                'name' => (string) $row['name'],
                'endpoint_url' => (string) $row['endpoint_url'],
                'secret_prefix' => (string) $row['secret_prefix'],
                'events' => json_decode((string) $row['events_json'], true) ?: [],
                'status' => (string) $row['status'],
                'last_delivery_at' => $row['last_delivery_at'] ? mysql_to_rfc3339((string) $row['last_delivery_at']) : null,
                'last_status_code' => (int) $row['last_status_code'],
                'failure_count' => (int) $row['failure_count'],
                'created_by' => (int) $row['created_by'],
                'created_at' => mysql_to_rfc3339((string) $row['created_at']),
                'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
                'secret_exported' => false,
            ];
        }, $rows);
    }

    private function export_webhook_deliveries(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Developer_API')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Developer_API::deliveries_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'webhook_delivery_id' => (int) $row['id'],
                'delivery_uuid' => (string) $row['delivery_uuid'],
                'webhook_id' => (int) $row['webhook_id'],
                'event_id' => (string) $row['event_id'],
                'event_type' => (string) $row['event_type'],
                'attempt' => (int) $row['attempt'],
                'status' => (string) $row['status'],
                'response_code' => (int) $row['response_code'],
                'response_summary' => function_exists('mb_substr') ? mb_substr((string) $row['response_body'], 0, 500) : substr((string) $row['response_body'], 0, 500),
                'next_attempt_at' => $row['next_attempt_at'] ? mysql_to_rfc3339((string) $row['next_attempt_at']) : null,
                'delivered_at' => $row['delivered_at'] ? mysql_to_rfc3339((string) $row['delivered_at']) : null,
                'created_at' => mysql_to_rfc3339((string) $row['created_at']),
                'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
                'payload_exported' => false,
                'signature_exported' => false,
            ];
        }, $rows);
    }

    private function export_media_assets(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Multimedia::assets_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'media_asset_id' => (int) $row['id'],
                'asset_uuid' => (string) $row['asset_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'media_type' => (string) $row['media_type'],
                'source_kind' => (string) $row['source_kind'],
                'attachment_id' => (int) $row['attachment_id'],
                'source_url' => (string) $row['source_url'],
                'duration_ms' => (int) $row['duration_ms'],
                'rights_status' => (string) $row['rights_status'],
                'rights_holder' => (string) $row['rights_holder'],
                'license_name' => (string) $row['license_name'],
                'license_url' => (string) $row['license_url'],
                'rights_note' => (string) $row['rights_note'],
                'source_citation' => (string) $row['source_citation'],
                'transcript_text' => (string) $row['transcript_text'],
                'transcript_vtt' => (string) $row['transcript_vtt'],
                'captions_url' => (string) $row['captions_url'],
                'poster_attachment_id' => (int) $row['poster_attachment_id'],
                'poster_time_ms' => (int) $row['poster_time_ms'],
                'accessibility_text' => (string) $row['accessibility_text'],
                'visibility' => (string) $row['visibility'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => json_decode((string) $row['metadata_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_media_clips(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Multimedia::clips_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            $payload = json_decode((string) $row['metadata_json'], true) ?: [];
            $payload['annotations'] = json_decode((string) $row['annotations_json'], true) ?: [];
            return [
                'media_clip_id' => (int) $row['id'],
                'clip_uuid' => (string) $row['clip_uuid'],
                'asset_uuid' => (string) $row['asset_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'start_ms' => (int) $row['start_ms'],
                'end_ms' => (int) $row['end_ms'],
                'poster_time_ms' => (int) $row['poster_time_ms'],
                'transcript_excerpt' => (string) $row['transcript_excerpt'],
                'caption_text' => (string) $row['caption_text'],
                'status' => (string) $row['status'],
                'visibility' => (string) $row['visibility'],
                'output_attachment_id' => (int) $row['output_attachment_id'],
                'poster_attachment_id' => (int) $row['poster_attachment_id'],
                'remote_job_uuid' => (string) $row['remote_job_uuid'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => $payload,
            ];
        }, $rows);
    }

    private function export_media_reels(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Multimedia::reels_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'media_reel_id' => (int) $row['id'],
                'reel_uuid' => (string) $row['reel_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'clip_uuids' => json_decode((string) $row['clip_uuids_json'], true) ?: [],
                'visibility' => (string) $row['visibility'],
                'edition_mode' => (string) $row['edition_mode'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => json_decode((string) $row['metadata_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_media_jobs(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Multimedia')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Multimedia::jobs_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'media_job_id' => (int) $row['id'],
                'job_uuid' => (string) $row['job_uuid'],
                'clip_uuid' => (string) $row['clip_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'status' => (string) $row['status'],
                'progress' => (int) $row['progress'],
                'attempt' => (int) $row['attempt'],
                'max_attempts' => (int) $row['max_attempts'],
                'remote_job_uuid' => (string) $row['remote_job_uuid'],
                'output_attachment_id' => (int) $row['output_attachment_id'],
                'poster_attachment_id' => (int) $row['poster_attachment_id'],
                'output_sha256' => (string) $row['output_sha256'],
                'output_bytes' => (int) $row['output_bytes'],
                'error_message' => (string) $row['error_message'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'completed_at' => $this->rfc3339_or_null((string) $row['completed_at']),
                'diagnostics' => json_decode((string) $row['diagnostics_json'], true) ?: [],
                'payload' => ['request' => json_decode((string) $row['request_json'], true) ?: []],
            ];
        }, $rows);
    }

    private function export_editorial_reviews(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Collaboration')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Collaboration::reviews_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(fn(array $row): array => [
            'review_id' => (int) $row['id'],
            'review_uuid' => (string) $row['review_uuid'],
            'subject_type' => (string) $row['subject_type'],
            'subject_key' => (string) $row['subject_key'],
            'post_id' => (int) $row['post_id'],
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'owner_user_id' => (int) $row['owner_user_id'],
            'assignee_user_id' => (int) $row['assignee_user_id'],
            'title' => (string) $row['title'],
            'summary' => (string) $row['summary'],
            'status' => (string) $row['status'],
            'priority' => (string) $row['priority'],
            'visibility' => (string) $row['visibility'],
            'due_at' => $this->rfc3339_or_null((string) $row['due_at']),
            'decision_note' => (string) $row['decision_note'],
            'locked_by' => (int) $row['locked_by'],
            'locked_at' => $this->rfc3339_or_null((string) $row['locked_at']),
            'lock_expires_at' => $this->rfc3339_or_null((string) $row['lock_expires_at']),
            'current_revision' => (int) $row['current_revision'],
            'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
            'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
            'completed_at' => $this->rfc3339_or_null((string) $row['completed_at']),
            'payload' => [],
        ], $rows);
    }

    private function export_editorial_participants(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Collaboration')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Collaboration::participants_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(fn(array $row): array => [
            'participant_id' => (int) $row['id'], 'review_id' => (int) $row['review_id'], 'user_id' => (int) $row['user_id'],
            'email' => (string) $row['email'], 'role' => (string) $row['role'], 'status' => (string) $row['status'],
            'invited_by' => (int) $row['invited_by'], 'expires_at' => $this->rfc3339_or_null((string) $row['expires_at']),
            'accepted_at' => $this->rfc3339_or_null((string) $row['accepted_at']), 'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
        ], $rows);
    }

    private function export_editorial_comments(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Collaboration')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Collaboration::comments_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(fn(array $row): array => [
            'comment_id' => (int) $row['id'], 'comment_uuid' => (string) $row['comment_uuid'], 'review_id' => (int) $row['review_id'],
            'parent_id' => (int) $row['parent_id'], 'user_id' => (int) $row['user_id'], 'body' => (string) $row['body'],
            'status' => (string) $row['status'], 'anchor' => json_decode((string) $row['anchor_json'], true) ?: [],
            'resolved_by' => (int) $row['resolved_by'], 'resolved_at' => $this->rfc3339_or_null((string) $row['resolved_at']),
            'created_at' => $this->rfc3339_or_null((string) $row['created_at']), 'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
        ], $rows);
    }

    private function export_editorial_suggestions(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Collaboration')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Collaboration::suggestions_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(fn(array $row): array => [
            'suggestion_id' => (int) $row['id'], 'suggestion_uuid' => (string) $row['suggestion_uuid'], 'review_id' => (int) $row['review_id'],
            'user_id' => (int) $row['user_id'], 'suggestion_type' => (string) $row['suggestion_type'], 'field_key' => (string) $row['field_key'],
            'original_text' => (string) $row['original_text'], 'proposed_text' => (string) $row['proposed_text'], 'rationale' => (string) $row['rationale'],
            'status' => (string) $row['status'], 'decision_note' => (string) $row['decision_note'], 'decided_by' => (int) $row['decided_by'],
            'decided_at' => $this->rfc3339_or_null((string) $row['decided_at']), 'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
            'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
        ], $rows);
    }

    private function export_editorial_events(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Collaboration')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Collaboration::events_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(fn(array $row): array => [
            'event_id' => (int) $row['id'], 'review_id' => (int) $row['review_id'], 'user_id' => (int) $row['user_id'],
            'event_type' => (string) $row['event_type'], 'payload' => json_decode((string) $row['payload_json'], true) ?: [],
            'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
        ], $rows);
    }

    private function export_document_jobs(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Document_Production')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Document_Production::jobs_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'document_job_id' => (int) $row['id'],
                'job_uuid' => (string) $row['job_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'workspace_uuid' => (string) $row['workspace_uuid'],
                'book_id' => (string) $row['book_id'],
                'title' => (string) $row['title'],
                'document_type' => (string) $row['document_type'],
                'status' => (string) $row['status'],
                'progress' => (int) $row['progress'],
                'attempt' => (int) $row['attempt'],
                'max_attempts' => (int) $row['max_attempts'],
                'content_hash' => (string) $row['content_hash'],
                'renderer_version' => (string) $row['renderer_version'],
                'output_attachment_id' => (int) $row['output_attachment_id'],
                'output_sha256' => (string) $row['output_sha256'],
                'output_bytes' => (int) $row['output_bytes'],
                'error_message' => (string) $row['error_message'],
                'created_at' => $row['created_at'] ? mysql_to_rfc3339((string) $row['created_at']) : null,
                'updated_at' => $row['updated_at'] ? mysql_to_rfc3339((string) $row['updated_at']) : null,
                'completed_at' => $row['completed_at'] ? mysql_to_rfc3339((string) $row['completed_at']) : null,
                'manifest' => json_decode((string) $row['manifest_json'], true) ?: [],
                'diagnostics' => json_decode((string) $row['diagnostics_json'], true) ?: [],
                'payload' => ['request' => json_decode((string) $row['request_json'], true) ?: []],
            ];
        }, $rows);
    }

    private function export_document_editions(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Document_Production')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Document_Production::editions_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'document_edition_id' => (int) $row['id'],
                'edition_uuid' => (string) $row['edition_uuid'],
                'job_uuid' => (string) $row['job_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'workspace_uuid' => (string) $row['workspace_uuid'],
                'book_id' => (string) $row['book_id'],
                'title' => (string) $row['title'],
                'edition_label' => (string) $row['edition_label'],
                'content_hash' => (string) $row['content_hash'],
                'output_sha256' => (string) $row['output_sha256'],
                'output_attachment_id' => (int) $row['output_attachment_id'],
                'output_url' => (int) $row['output_attachment_id'] ? wp_get_attachment_url((int) $row['output_attachment_id']) : '',
                'frozen_at' => $row['frozen_at'] ? mysql_to_rfc3339((string) $row['frozen_at']) : null,
                'created_at' => $row['created_at'] ? mysql_to_rfc3339((string) $row['created_at']) : null,
                'manifest' => json_decode((string) $row['manifest_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_account_workspaces(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Workspaces')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Workspaces::table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'workspace_id' => (int) $row['id'],
                'workspace_uuid' => (string) $row['workspace_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'title' => (string) $row['title'],
                'description' => (string) $row['description'],
                'visibility' => (string) $row['visibility'],
                'schema_version' => (string) $row['schema_version'],
                'content_hash' => (string) $row['content_hash'],
                'revision' => (int) $row['revision'],
                'last_synced_revision' => (int) $row['last_synced_revision'],
                'sync_status' => (string) $row['sync_status'],
                'last_synced_at' => $row['last_synced_at'] ? mysql_to_rfc3339((string) $row['last_synced_at']) : null,
                'created_at' => mysql_to_rfc3339((string) $row['created_at']),
                'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
                'payload' => json_decode((string) $row['workspace_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_account_workspace_revisions(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Workspaces')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Workspaces::revisions_table() . ' ORDER BY workspace_id, revision', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'revision_id' => (int) $row['id'],
            'workspace_id' => (int) $row['workspace_id'],
            'revision' => (int) $row['revision'],
            'content_hash' => (string) $row['content_hash'],
            'change_type' => (string) $row['change_type'],
            'created_by' => (int) $row['created_by'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'payload' => json_decode((string) $row['workspace_json'], true) ?: [],
        ], $rows);
    }

    private function export_account_workspace_collaborators(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Workspaces')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Workspaces::collaborators_table() . ' ORDER BY workspace_id, user_id', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'collaboration_id' => (int) $row['id'],
            'workspace_id' => (int) $row['workspace_id'],
            'user_id' => (int) $row['user_id'],
            'role' => (string) $row['role'],
            'invited_by' => (int) $row['invited_by'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'accepted_at' => $row['accepted_at'] ? mysql_to_rfc3339((string) $row['accepted_at']) : null,
        ], $rows);
    }

    private function export_account_workspace_sync_log(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Workspaces')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Workspaces::sync_log_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(static fn(array $row): array => [
            'sync_log_id' => (int) $row['id'],
            'workspace_id' => (int) $row['workspace_id'],
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'direction' => (string) $row['direction'],
            'status' => (string) $row['status'],
            'response_code' => (int) $row['response_code'],
            'message' => (string) $row['message'],
            'content_hash' => (string) $row['content_hash'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
        ], $rows);
    }

    private function export_terms(): array {
        $taxonomies = ['category', 'post_tag', SC_Library_Taxonomies::SERIES, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY];
        $rows = [];
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) continue;
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
            if (is_wp_error($terms)) continue;
            foreach ($terms as $term) {
                $rows[] = [
                    'term_id' => (int) $term->term_id,
                    'taxonomy' => $taxonomy,
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'description' => $term->description,
                    'parent_term_id' => (int) $term->parent,
                    'payload' => ['count' => (int) $term->count, 'term_taxonomy_id' => (int) $term->term_taxonomy_id],
                ];
            }
        }
        return $rows;
    }

    private function export_record_terms(array $record_ids): array {
        $taxonomies = ['category', 'post_tag', SC_Library_Taxonomies::SERIES, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY];
        $rows = [];
        foreach ($record_ids as $record_id) {
            foreach ($taxonomies as $taxonomy) {
                if (!taxonomy_exists($taxonomy)) continue;
                $terms = wp_get_object_terms($record_id, $taxonomy, ['orderby' => 'term_order']);
                if (is_wp_error($terms)) continue;
                foreach ($terms as $position => $term) {
                    $rows[] = ['record_id' => (int) $record_id, 'taxonomy' => $taxonomy, 'term_id' => (int) $term->term_id, 'term_order' => (int) $position];
                }
            }
        }
        return $rows;
    }

    private function export_resources(array $record_ids): array {
        $map = [
            '_sc_library_github_url' => 'repository',
            '_sc_library_dataset_urls' => 'dataset',
            '_sc_library_video_urls' => 'video',
            '_sc_library_workbench_tools' => 'workbench_tool',
            '_sc_library_workbench_questions' => 'workbench_question',
            '_sc_library_decision_questions' => 'decision_question',
            '_sc_library_decision_methods' => 'decision_method',
            '_sc_library_site_places' => 'place',
            '_sc_library_site_indicators' => 'indicator',
            '_sc_library_site_sources' => 'site_source',
        ];
        $rows = [];
        foreach ($record_ids as $record_id) {
            foreach ($map as $meta_key => $resource_type) {
                $raw = (string) get_post_meta($record_id, $meta_key, true);
                if ($raw === '') continue;
                $values = preg_split('/\R|,/', $raw) ?: [];
                foreach (array_values(array_filter(array_map('trim', $values))) as $position => $value) {
                    $is_url = wp_http_validate_url($value) !== false;
                    $rows[] = [
                        'resource_id' => 'resource:' . $record_id . ':' . $resource_type . ':' . substr(hash('sha256', $value), 0, 16),
                        'record_id' => (int) $record_id,
                        'resource_type' => $resource_type,
                        'url' => $is_url ? $value : '',
                        'label' => $is_url ? '' : $value,
                        'sort_order' => (int) $position,
                        'payload' => ['meta_key' => $meta_key, 'raw_value' => $value],
                    ];
                }
            }
        }
        return $rows;
    }

    private function export_documentation(array $record_ids): array {
        $rows = [];
        foreach ($record_ids as $record_id) {
            $status = (string) get_post_meta($record_id, '_sc_library_doc_status', true);
            $categories = wp_get_post_terms($record_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['fields' => 'slugs']);
            if ($status === '' && (is_wp_error($categories) || !$categories)) continue;
            $rows[] = [
                'record_id' => (int) $record_id,
                'document_status' => $status ?: 'current',
                'document_type' => (string) get_post_meta($record_id, '_sc_library_doc_type', true),
                'document_version' => (string) get_post_meta($record_id, '_sc_library_doc_version', true),
                'responsible_area' => (string) get_post_meta($record_id, '_sc_library_doc_responsible_area', true),
                'authority_type' => (string) get_post_meta($record_id, '_sc_library_doc_authority_type', true),
                'authority_url' => (string) get_post_meta($record_id, '_sc_library_doc_authority_url', true),
                'webpage_url' => (string) get_post_meta($record_id, '_sc_library_doc_webpage_url', true),
                'repository_url' => (string) get_post_meta($record_id, '_sc_library_doc_repository_url', true),
                'pdf_url' => (string) get_post_meta($record_id, '_sc_library_doc_pdf_url', true),
                'release_url' => (string) get_post_meta($record_id, '_sc_library_doc_release_url', true),
                'last_reviewed' => (string) get_post_meta($record_id, '_sc_library_doc_last_reviewed', true),
                'review_interval_days' => (int) get_post_meta($record_id, '_sc_library_doc_review_interval', true),
                'featured' => (bool) get_post_meta($record_id, '_sc_library_doc_featured', true),
                'supersedes_record_id' => (int) get_post_meta($record_id, '_sc_library_doc_supersedes_id', true),
                'superseded_by_record_id' => (int) get_post_meta($record_id, '_sc_library_doc_superseded_by_id', true),
                'dependency_ids' => $this->integer_list((string) get_post_meta($record_id, '_sc_library_doc_dependency_ids', true)),
                'correction_url' => (string) get_post_meta($record_id, '_sc_library_doc_correction_url', true),
                'authority_note' => (string) get_post_meta($record_id, '_sc_library_doc_authority_note', true),
                'payload' => ['documentation_categories' => is_wp_error($categories) ? [] : array_values($categories)],
            ];
        }
        return $rows;
    }

    private function export_plans(bool $include_private): array {
        $statuses = $include_private ? ['publish', 'draft', 'private', 'pending', 'future'] : ['publish'];
        $ids = get_posts([
            'post_type' => SC_Library_Planner::POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        $meta_keys = [
            '_sc_plan_status', '_sc_plan_priority', '_sc_plan_release_type', '_sc_plan_release_date', '_sc_plan_release_month',
            '_sc_plan_release_quarter', '_sc_plan_release_year', '_sc_plan_product_release', '_sc_plan_release_note', '_sc_plan_content_type',
            '_sc_plan_area', '_sc_plan_product', '_sc_plan_responsible', '_sc_plan_public', '_sc_plan_published_post_id', '_sc_plan_linked_draft_id',
            '_sc_plan_article_map_id', '_sc_plan_series_order', '_sc_plan_target_post_type', '_sc_plan_audience', '_sc_plan_research_questions',
            '_sc_plan_sources', '_sc_plan_expected_artifacts', '_sc_plan_dependency_ids', '_sc_plan_internal_notes', '_sc_plan_actual_publication_date',
            '_sc_plan_release_group', '_sc_plan_release_track', '_sc_plan_milestone', '_sc_plan_capacity_owner', '_sc_plan_estimated_effort',
            '_sc_plan_effort_unit', '_sc_plan_actual_effort', '_sc_plan_progress_percent', '_sc_plan_planned_start', '_sc_plan_actual_start',
            '_sc_plan_dependency_policy', '_sc_plan_blocked_override', '_sc_plan_blocked_reason',
        ];
        $rows = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post) continue;
            $meta = [];
            foreach ($meta_keys as $key) {
                if ($key === '_sc_plan_internal_notes' && !$include_private) continue;
                $meta[$key] = get_post_meta($id, $key, true);
            }
            $rows[] = [
                'plan_id' => (int) $id,
                'title' => $post->post_title,
                'description' => $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 60),
                'wordpress_status' => $post->post_status,
                'plan_status' => (string) ($meta['_sc_plan_status'] ?? 'planned'),
                'priority' => (string) ($meta['_sc_plan_priority'] ?? 'normal'),
                'content_type' => (string) ($meta['_sc_plan_content_type'] ?? 'article'),
                'area' => (string) ($meta['_sc_plan_area'] ?? ''),
                'product' => (string) ($meta['_sc_plan_product'] ?? ''),
                'responsible_area' => (string) ($meta['_sc_plan_responsible'] ?? ''),
                'public' => !empty($meta['_sc_plan_public']),
                'expected_release' => [
                    'type' => (string) ($meta['_sc_plan_release_type'] ?? 'none'),
                    'date' => (string) ($meta['_sc_plan_release_date'] ?? ''),
                    'month' => (string) ($meta['_sc_plan_release_month'] ?? ''),
                    'quarter' => (string) ($meta['_sc_plan_release_quarter'] ?? ''),
                    'year' => (string) ($meta['_sc_plan_release_year'] ?? ''),
                    'product_release' => (string) ($meta['_sc_plan_product_release'] ?? ''),
                    'note' => (string) ($meta['_sc_plan_release_note'] ?? ''),
                ],
                'article_map_id' => (int) ($meta['_sc_plan_article_map_id'] ?? 0),
                'series_order' => (float) ($meta['_sc_plan_series_order'] ?? 0),
                'linked_draft_id' => (int) ($meta['_sc_plan_linked_draft_id'] ?? 0),
                'published_record_id' => (int) ($meta['_sc_plan_published_post_id'] ?? 0),
                'dependency_ids' => $this->integer_list((string) ($meta['_sc_plan_dependency_ids'] ?? '')),
                'release_group' => (string) ($meta['_sc_plan_release_group'] ?? ''),
                'release_track' => (string) ($meta['_sc_plan_release_track'] ?? ''),
                'milestone' => (string) ($meta['_sc_plan_milestone'] ?? ''),
                'capacity_owner' => (string) ($meta['_sc_plan_capacity_owner'] ?? ''),
                'estimated_effort' => (float) ($meta['_sc_plan_estimated_effort'] ?? 0),
                'effort_unit' => (string) ($meta['_sc_plan_effort_unit'] ?? 'points'),
                'actual_effort' => (float) ($meta['_sc_plan_actual_effort'] ?? 0),
                'progress_percent' => (int) ($meta['_sc_plan_progress_percent'] ?? 0),
                'planned_start' => (string) ($meta['_sc_plan_planned_start'] ?? ''),
                'actual_start' => (string) ($meta['_sc_plan_actual_start'] ?? ''),
                'dependency_policy' => (string) ($meta['_sc_plan_dependency_policy'] ?? 'all'),
                'blocked_override' => !empty($meta['_sc_plan_blocked_override']),
                'blocked_reason' => (string) ($meta['_sc_plan_blocked_reason'] ?? ''),
                'actual_publication_date' => (string) ($meta['_sc_plan_actual_publication_date'] ?? ''),
                'created_at' => $this->rfc3339_or_null($post->post_date_gmt ?: $post->post_date),
                'modified_at' => $this->rfc3339_or_null($post->post_modified_gmt ?: $post->post_modified),
                'payload' => $meta,
            ];
        }
        return $rows;
    }

    private function export_plan_dependencies(array $plans): array {
        $rows = [];
        foreach ($plans as $plan) {
            foreach (array_values($plan['dependency_ids'] ?? []) as $position => $dependency_id) {
                $rows[] = [
                    'plan_id' => (int) ($plan['plan_id'] ?? 0),
                    'dependency_record_id' => (int) $dependency_id,
                    'dependency_order' => (int) $position,
                    'dependency_policy' => (string) ($plan['dependency_policy'] ?? 'all'),
                    'payload' => [],
                ];
            }
        }
        return $rows;
    }

    private function export_orchestration_sessions(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Orchestrator')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Orchestrator::sessions_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'orchestration_session_id' => (int) $row['id'],
                'session_uuid' => (string) $row['session_uuid'],
                'owner_user_id' => (int) $row['owner_user_id'],
                'title' => (string) $row['title'],
                'question' => (string) $row['question'],
                'intent' => (string) $row['intent'],
                'status' => (string) $row['status'],
                'provider' => (string) $row['provider'],
                'model' => (string) $row['model'],
                'retrieval_mode' => (string) $row['retrieval_mode'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => json_decode((string) $row['response_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_orchestration_events(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Orchestrator')) return [];
        $rows = $wpdb->get_results('SELECT * FROM ' . SC_Library_Orchestrator::events_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'orchestration_event_id' => (int) $row['id'],
                'session_id' => (int) $row['session_id'],
                'event_uuid' => (string) $row['event_uuid'],
                'event_type' => (string) $row['event_type'],
                'created_by' => (int) $row['created_by'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'payload' => json_decode((string) $row['payload_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_graph_nodes(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Knowledge_Graph')) return [];
        $graph = new SC_Library_Knowledge_Graph($this->indexer, $this->relationships);
        $rows = $wpdb->get_results('SELECT * FROM ' . $graph->nodes_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'graph_node_id' => (int) $row['id'],
                'node_uuid' => (string) $row['node_uuid'],
                'external_key' => (string) $row['external_key'],
                'node_type' => (string) $row['node_type'],
                'subtype' => (string) $row['subtype'],
                'label' => (string) $row['label'],
                'description' => (string) $row['description'],
                'canonical_url' => (string) $row['canonical_url'],
                'post_id' => (int) $row['post_id'],
                'term_id' => (int) $row['term_id'],
                'taxonomy' => (string) $row['taxonomy'],
                'visibility' => (string) $row['visibility'],
                'source_kind' => (string) $row['source_kind'],
                'source_identifier' => (string) $row['source_identifier'],
                'published_at' => $this->rfc3339_or_null((string) ($row['published_at'] ?? '')),
                'modified_at' => $this->rfc3339_or_null((string) ($row['modified_at'] ?? '')),
                'status' => (string) $row['status'],
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => json_decode((string) $row['metadata_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function export_graph_edges(): array {
        global $wpdb;
        if (!class_exists('SC_Library_Knowledge_Graph')) return [];
        $graph = new SC_Library_Knowledge_Graph($this->indexer, $this->relationships);
        $rows = $wpdb->get_results('SELECT * FROM ' . $graph->edges_table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'graph_edge_id' => (int) $row['id'],
                'edge_uuid' => (string) $row['edge_uuid'],
                'source_node_id' => (int) $row['source_node_id'],
                'target_node_id' => (int) $row['target_node_id'],
                'relationship_type' => (string) $row['relationship_type'],
                'label' => (string) $row['label'],
                'directionality' => (string) $row['directionality'],
                'confidence' => (float) $row['confidence'],
                'confidence_basis' => (string) $row['confidence_basis'],
                'provenance_type' => (string) $row['provenance_type'],
                'provenance_url' => (string) $row['provenance_url'],
                'evidence_note' => (string) $row['evidence_note'],
                'visibility' => (string) $row['visibility'],
                'source_kind' => (string) $row['source_kind'],
                'source_identifier' => (string) $row['source_identifier'],
                'sort_order' => (int) $row['sort_order'],
                'created_by' => (int) $row['created_by'],
                'verified_by' => (int) $row['verified_by'],
                'verified_at' => $this->rfc3339_or_null((string) ($row['verified_at'] ?? '')),
                'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
                'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
                'payload' => json_decode((string) $row['metadata_json'], true) ?: [],
            ];
        }, $rows);
    }

    private function normalize_relationship(array $row): array {
        return [
            'relationship_id' => (int) $row['id'],
            'source_record_id' => (int) $row['source_post_id'],
            'target_record_id' => (int) $row['target_post_id'],
            'relationship_type' => (string) $row['relationship_type'],
            'note' => (string) $row['note'],
            'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0.85,
            'confidence_basis' => (string) ($row['confidence_basis'] ?? 'editorial'),
            'provenance_type' => (string) ($row['provenance_type'] ?? 'editorial'),
            'provenance_url' => (string) ($row['provenance_url'] ?? ''),
            'evidence_note' => (string) ($row['evidence_note'] ?? ''),
            'visibility' => (string) ($row['visibility'] ?? 'public'),
            'sort_order' => (int) $row['sort_order'],
            'created_at' => $this->rfc3339_or_null((string) $row['created_at']),
            'updated_at' => $this->rfc3339_or_null((string) $row['updated_at']),
            'payload' => [],
        ];
    }

    private function integer_list(string $raw): array {
        return array_values(array_unique(array_filter(array_map('absint', preg_split('/\D+/', $raw) ?: []))));
    }

    private function filter_rows_by_ids(array $rows, string $key, array $ids): array {
        $set = array_fill_keys(array_map('intval', $ids), true);
        return array_values(array_filter($rows, static fn($row) => isset($set[(int) ($row[$key] ?? 0)])));
    }

    private function rfc3339_or_null(string $value): ?string {
        if ($value === '' || $value === '0000-00-00 00:00:00') return null;
        $timestamp = strtotime($value . (str_contains($value, 'T') ? '' : ' UTC'));
        return $timestamp ? gmdate('c', $timestamp) : null;
    }

    public function postgres_schema_sql(string $schema_name): string {
        $s = $this->sanitize_schema_name($schema_name);
        return <<<SQL
CREATE SCHEMA IF NOT EXISTS {$s};
SET search_path TO {$s}, public;

CREATE TABLE IF NOT EXISTS export_metadata (
    metadata_key text PRIMARY KEY,
    metadata_value jsonb NOT NULL
);

CREATE TABLE IF NOT EXISTS records (
    record_id bigint PRIMARY KEY,
    record_identifier text UNIQUE NOT NULL,
    kind text NOT NULL,
    post_type text NOT NULL,
    title text NOT NULL,
    excerpt text NOT NULL DEFAULT '',
    canonical_url text NOT NULL DEFAULT '',
    record_state text NOT NULL,
    content_type text NOT NULL,
    area text NOT NULL DEFAULT '',
    product text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    published_at timestamptz,
    modified_at timestamptz,
    expected_release jsonb NOT NULL DEFAULT '{}'::jsonb,
    article_map_id bigint,
    series_order numeric(12,3) NOT NULL DEFAULT 0,
    authoritative boolean NOT NULL DEFAULT false,
    authority_label text NOT NULL DEFAULT '',
    historical boolean NOT NULL DEFAULT false,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS records_state_idx ON records(record_state);
CREATE INDEX IF NOT EXISTS records_type_idx ON records(content_type);
CREATE INDEX IF NOT EXISTS records_area_idx ON records(area);
CREATE INDEX IF NOT EXISTS records_product_idx ON records(product);
CREATE INDEX IF NOT EXISTS records_payload_gin ON records USING gin(payload);

CREATE TABLE IF NOT EXISTS terms (
    term_id bigint NOT NULL,
    taxonomy text NOT NULL,
    slug text NOT NULL,
    name text NOT NULL,
    description text NOT NULL DEFAULT '',
    parent_term_id bigint,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    PRIMARY KEY (taxonomy, term_id)
);

CREATE TABLE IF NOT EXISTS record_terms (
    record_id bigint NOT NULL,
    taxonomy text NOT NULL,
    term_id bigint NOT NULL,
    term_order integer NOT NULL DEFAULT 0,
    PRIMARY KEY (record_id, taxonomy, term_id)
);
CREATE INDEX IF NOT EXISTS record_terms_term_idx ON record_terms(taxonomy, term_id);

CREATE TABLE IF NOT EXISTS relationships (
    relationship_id bigint PRIMARY KEY,
    source_record_id bigint NOT NULL,
    target_record_id bigint NOT NULL,
    relationship_type text NOT NULL,
    note text NOT NULL DEFAULT '',
    confidence numeric(5,4) NOT NULL DEFAULT 0.8500,
    confidence_basis text NOT NULL DEFAULT 'editorial',
    provenance_type text NOT NULL DEFAULT 'editorial',
    provenance_url text NOT NULL DEFAULT '',
    evidence_note text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'public',
    sort_order integer NOT NULL DEFAULT 0,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE (source_record_id, target_record_id, relationship_type)
);
CREATE INDEX IF NOT EXISTS relationships_source_idx ON relationships(source_record_id);
CREATE INDEX IF NOT EXISTS relationships_target_idx ON relationships(target_record_id);
CREATE INDEX IF NOT EXISTS relationships_type_idx ON relationships(relationship_type);


CREATE TABLE IF NOT EXISTS graph_nodes (
    graph_node_id bigint PRIMARY KEY,
    node_uuid uuid UNIQUE NOT NULL,
    external_key text UNIQUE NOT NULL,
    node_type text NOT NULL,
    subtype text NOT NULL DEFAULT '',
    label text NOT NULL,
    description text NOT NULL DEFAULT '',
    canonical_url text NOT NULL DEFAULT '',
    post_id bigint,
    term_id bigint,
    taxonomy text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'public',
    source_kind text NOT NULL,
    source_identifier text NOT NULL DEFAULT '',
    published_at timestamptz,
    modified_at timestamptz,
    status text NOT NULL DEFAULT 'active',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS graph_nodes_type_idx ON graph_nodes(node_type);
CREATE INDEX IF NOT EXISTS graph_nodes_post_idx ON graph_nodes(post_id);
CREATE INDEX IF NOT EXISTS graph_nodes_term_idx ON graph_nodes(term_id, taxonomy);
CREATE INDEX IF NOT EXISTS graph_nodes_visibility_idx ON graph_nodes(visibility);
CREATE INDEX IF NOT EXISTS graph_nodes_payload_gin ON graph_nodes USING gin(payload);

CREATE TABLE IF NOT EXISTS graph_edges (
    graph_edge_id bigint PRIMARY KEY,
    edge_uuid uuid UNIQUE NOT NULL,
    source_node_id bigint NOT NULL REFERENCES graph_nodes(graph_node_id) ON DELETE CASCADE,
    target_node_id bigint NOT NULL REFERENCES graph_nodes(graph_node_id) ON DELETE CASCADE,
    relationship_type text NOT NULL,
    label text NOT NULL DEFAULT '',
    directionality text NOT NULL DEFAULT 'directed',
    confidence numeric(5,4) NOT NULL DEFAULT 0.7500,
    confidence_basis text NOT NULL DEFAULT 'editorial',
    provenance_type text NOT NULL DEFAULT 'editorial',
    provenance_url text NOT NULL DEFAULT '',
    evidence_note text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'public',
    source_kind text NOT NULL,
    source_identifier text NOT NULL DEFAULT '',
    sort_order integer NOT NULL DEFAULT 0,
    created_by bigint NOT NULL DEFAULT 0,
    verified_by bigint NOT NULL DEFAULT 0,
    verified_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS graph_edges_source_idx ON graph_edges(source_node_id);
CREATE INDEX IF NOT EXISTS graph_edges_target_idx ON graph_edges(target_node_id);
CREATE INDEX IF NOT EXISTS graph_edges_type_idx ON graph_edges(relationship_type);
CREATE INDEX IF NOT EXISTS graph_edges_confidence_idx ON graph_edges(confidence);
CREATE INDEX IF NOT EXISTS graph_edges_provenance_idx ON graph_edges(provenance_type);
CREATE INDEX IF NOT EXISTS graph_edges_payload_gin ON graph_edges USING gin(payload);

CREATE TABLE IF NOT EXISTS resources (
    resource_id text PRIMARY KEY,
    record_id bigint NOT NULL,
    resource_type text NOT NULL,
    url text NOT NULL DEFAULT '',
    label text NOT NULL DEFAULT '',
    sort_order integer NOT NULL DEFAULT 0,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS resources_record_idx ON resources(record_id);
CREATE INDEX IF NOT EXISTS resources_type_idx ON resources(resource_type);

CREATE TABLE IF NOT EXISTS documentation (
    record_id bigint PRIMARY KEY,
    document_status text NOT NULL,
    document_type text NOT NULL DEFAULT '',
    document_version text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    authority_type text NOT NULL DEFAULT '',
    authority_url text NOT NULL DEFAULT '',
    webpage_url text NOT NULL DEFAULT '',
    repository_url text NOT NULL DEFAULT '',
    pdf_url text NOT NULL DEFAULT '',
    release_url text NOT NULL DEFAULT '',
    last_reviewed date,
    review_interval_days integer NOT NULL DEFAULT 0,
    featured boolean NOT NULL DEFAULT false,
    supersedes_record_id bigint,
    superseded_by_record_id bigint,
    dependency_ids bigint[] NOT NULL DEFAULT '{}'::bigint[],
    correction_url text NOT NULL DEFAULT '',
    authority_note text NOT NULL DEFAULT '',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);

CREATE TABLE IF NOT EXISTS plans (
    plan_id bigint PRIMARY KEY,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    wordpress_status text NOT NULL,
    plan_status text NOT NULL,
    priority text NOT NULL,
    content_type text NOT NULL,
    area text NOT NULL DEFAULT '',
    product text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    public boolean NOT NULL DEFAULT false,
    expected_release jsonb NOT NULL DEFAULT '{}'::jsonb,
    article_map_id bigint,
    series_order numeric(12,3) NOT NULL DEFAULT 0,
    linked_draft_id bigint,
    published_record_id bigint,
    dependency_ids bigint[] NOT NULL DEFAULT '{}'::bigint[],
    release_group text NOT NULL DEFAULT '',
    release_track text NOT NULL DEFAULT '',
    milestone text NOT NULL DEFAULT '',
    capacity_owner text NOT NULL DEFAULT '',
    estimated_effort numeric(14,3) NOT NULL DEFAULT 0,
    effort_unit text NOT NULL DEFAULT 'points',
    actual_effort numeric(14,3) NOT NULL DEFAULT 0,
    progress_percent integer NOT NULL DEFAULT 0 CHECK (progress_percent BETWEEN 0 AND 100),
    planned_start date,
    actual_start date,
    dependency_policy text NOT NULL DEFAULT 'all',
    blocked_override boolean NOT NULL DEFAULT false,
    blocked_reason text NOT NULL DEFAULT '',
    actual_publication_date date,
    created_at timestamptz,
    modified_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS plans_status_idx ON plans(plan_status);
CREATE INDEX IF NOT EXISTS plans_area_idx ON plans(area);
CREATE INDEX IF NOT EXISTS plans_product_idx ON plans(product);
CREATE INDEX IF NOT EXISTS plans_release_group_idx ON plans(release_group);
CREATE INDEX IF NOT EXISTS plans_capacity_owner_idx ON plans(capacity_owner);

CREATE TABLE IF NOT EXISTS plan_dependencies (
    plan_id bigint NOT NULL,
    dependency_record_id bigint NOT NULL,
    dependency_order integer NOT NULL DEFAULT 0,
    dependency_policy text NOT NULL DEFAULT 'all',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    PRIMARY KEY (plan_id, dependency_record_id)
);
CREATE INDEX IF NOT EXISTS plan_dependencies_target_idx ON plan_dependencies(dependency_record_id);

-- Browser-local Research Notebook tables. These are populated by the
-- [sc_library_portability] or Notebook PostgreSQL export, not by server exports.
CREATE TABLE IF NOT EXISTS workspace_collections (
    collection_id text PRIMARY KEY,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_saved_records (
    saved_record_id text PRIMARY KEY,
    wp_record_id bigint,
    record_identifier text NOT NULL DEFAULT '',
    title text NOT NULL,
    canonical_url text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_notes (
    note_id text PRIMARY KEY,
    title text NOT NULL,
    note_type text NOT NULL DEFAULT 'note',
    body text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_sources (
    source_id text PRIMARY KEY,
    title text NOT NULL,
    source_type text NOT NULL DEFAULT 'custom',
    canonical_url text NOT NULL DEFAULT '',
    doi text NOT NULL DEFAULT '',
    isbn text NOT NULL DEFAULT '',
    collection_ids text[] NOT NULL DEFAULT '{}'::text[],
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE TABLE IF NOT EXISTS workspace_matrices (matrix_id text PRIMARY KEY, title text NOT NULL, status text NOT NULL DEFAULT 'draft', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_boards (board_id text PRIMARY KEY, title text NOT NULL, board_type text NOT NULL DEFAULT 'whiteboard', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_annotations (annotation_id text PRIMARY KEY, title text NOT NULL, target_type text NOT NULL DEFAULT '', target_id text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_books (book_id text PRIMARY KEY, title text NOT NULL, edition text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);
CREATE TABLE IF NOT EXISTS workspace_handoffs (handoff_id text PRIMARY KEY, target text NOT NULL DEFAULT '', collection_ids text[] NOT NULL DEFAULT '{}'::text[], payload jsonb NOT NULL DEFAULT '{}'::jsonb);

CREATE TABLE IF NOT EXISTS account_workspaces (
    workspace_id bigint PRIMARY KEY,
    workspace_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'private',
    schema_version text NOT NULL,
    content_hash char(64) NOT NULL,
    revision bigint NOT NULL,
    last_synced_revision bigint NOT NULL DEFAULT 0,
    sync_status text NOT NULL DEFAULT 'local',
    last_synced_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS account_workspaces_owner_idx ON account_workspaces(owner_user_id);
CREATE TABLE IF NOT EXISTS account_workspace_revisions (
    revision_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    revision bigint NOT NULL,
    content_hash char(64) NOT NULL,
    change_type text NOT NULL,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE(workspace_id, revision)
);
CREATE TABLE IF NOT EXISTS account_workspace_collaborators (
    collaboration_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    user_id bigint NOT NULL,
    role text NOT NULL,
    invited_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    accepted_at timestamptz,
    UNIQUE(workspace_id, user_id)
);
CREATE TABLE IF NOT EXISTS account_workspace_sync_log (
    sync_log_id bigint PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES account_workspaces(workspace_id) ON DELETE CASCADE,
    workspace_uuid uuid NOT NULL,
    direction text NOT NULL,
    status text NOT NULL,
    response_code integer NOT NULL DEFAULT 0,
    message text NOT NULL DEFAULT '',
    content_hash char(64) NOT NULL DEFAULT '',
    created_at timestamptz
);

CREATE TABLE IF NOT EXISTS document_jobs (
    document_job_id bigint PRIMARY KEY,
    job_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL,
    workspace_uuid text NOT NULL DEFAULT '',
    book_id text NOT NULL DEFAULT '',
    title text NOT NULL,
    document_type text NOT NULL DEFAULT 'pdf',
    status text NOT NULL,
    progress integer NOT NULL DEFAULT 0,
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    content_hash char(64) NOT NULL,
    renderer_version text NOT NULL DEFAULT '',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    output_sha256 char(64) NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    completed_at timestamptz,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS document_jobs_owner_idx ON document_jobs(owner_user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS document_jobs_status_idx ON document_jobs(status, updated_at DESC);
CREATE TABLE IF NOT EXISTS document_editions (
    document_edition_id bigint PRIMARY KEY,
    edition_uuid uuid UNIQUE NOT NULL,
    job_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL,
    workspace_uuid text NOT NULL DEFAULT '',
    book_id text NOT NULL DEFAULT '',
    title text NOT NULL,
    edition_label text NOT NULL DEFAULT '',
    content_hash char(64) NOT NULL,
    output_sha256 char(64) NOT NULL,
    output_attachment_id bigint NOT NULL DEFAULT 0,
    output_url text NOT NULL DEFAULT '',
    frozen_at timestamptz,
    created_at timestamptz,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS document_editions_book_idx ON document_editions(book_id, frozen_at DESC);

CREATE TABLE IF NOT EXISTS foundation_documents (
    record_id bigint PRIMARY KEY REFERENCES records(record_id) ON DELETE CASCADE,
    attachment_id bigint,
    pdf_url text NOT NULL DEFAULT '',
    version_label text NOT NULL DEFAULT '',
    publication_date date,
    author_name text NOT NULL DEFAULT '',
    publisher_name text NOT NULL DEFAULT '',
    doi text NOT NULL DEFAULT '',
    language_code text NOT NULL DEFAULT 'en',
    download_enabled boolean NOT NULL DEFAULT false,
    viewer_enabled boolean NOT NULL DEFAULT true,
    extraction_status text NOT NULL DEFAULT 'not_started',
    page_count integer NOT NULL DEFAULT 0,
    character_count bigint NOT NULL DEFAULT 0,
    extracted_at timestamptz,
    extraction_error text NOT NULL DEFAULT '',
    related_record_ids bigint[] NOT NULL DEFAULT '{}',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS foundation_documents_status_idx ON foundation_documents(extraction_status);
CREATE INDEX IF NOT EXISTS foundation_documents_doi_idx ON foundation_documents(doi);

CREATE TABLE IF NOT EXISTS pdf_pages (
    pdf_page_id bigint PRIMARY KEY,
    record_id bigint NOT NULL REFERENCES foundation_documents(record_id) ON DELETE CASCADE,
    page_number integer NOT NULL,
    page_text text NOT NULL,
    character_count bigint NOT NULL DEFAULT 0,
    content_hash text NOT NULL DEFAULT '',
    extracted_at timestamptz,
    UNIQUE (record_id, page_number)
);
CREATE INDEX IF NOT EXISTS pdf_pages_record_idx ON pdf_pages(record_id, page_number);
CREATE INDEX IF NOT EXISTS pdf_pages_search_idx ON pdf_pages USING gin(to_tsvector('simple', page_text));

CREATE TABLE IF NOT EXISTS foundation_versions (
    foundation_version_id bigint PRIMARY KEY,
    record_id bigint NOT NULL REFERENCES foundation_documents(record_id) ON DELETE CASCADE,
    attachment_id bigint,
    version_label text NOT NULL DEFAULT '',
    filename text NOT NULL DEFAULT '',
    pdf_url text NOT NULL DEFAULT '',
    sha256 text NOT NULL DEFAULT '',
    page_count integer NOT NULL DEFAULT 0,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS foundation_versions_record_idx ON foundation_versions(record_id, created_at DESC);

CREATE TABLE IF NOT EXISTS media_assets (
    media_asset_id bigint PRIMARY KEY,
    asset_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    media_type text NOT NULL,
    source_kind text NOT NULL,
    attachment_id bigint NOT NULL DEFAULT 0,
    source_url text NOT NULL DEFAULT '',
    duration_ms bigint NOT NULL DEFAULT 0,
    rights_status text NOT NULL,
    rights_holder text NOT NULL DEFAULT '',
    license_name text NOT NULL DEFAULT '',
    license_url text NOT NULL DEFAULT '',
    rights_note text NOT NULL DEFAULT '',
    source_citation text NOT NULL DEFAULT '',
    transcript_text text NOT NULL DEFAULT '',
    transcript_vtt text NOT NULL DEFAULT '',
    captions_url text NOT NULL DEFAULT '',
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    poster_time_ms bigint NOT NULL DEFAULT 0,
    accessibility_text text NOT NULL DEFAULT '',
    visibility text NOT NULL DEFAULT 'private',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_assets_rights_idx ON media_assets(rights_status);
CREATE INDEX IF NOT EXISTS media_assets_visibility_idx ON media_assets(visibility);

CREATE TABLE IF NOT EXISTS media_clips (
    media_clip_id bigint PRIMARY KEY,
    clip_uuid uuid UNIQUE NOT NULL,
    asset_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    start_ms bigint NOT NULL DEFAULT 0,
    end_ms bigint NOT NULL DEFAULT 0,
    poster_time_ms bigint NOT NULL DEFAULT 0,
    transcript_excerpt text NOT NULL DEFAULT '',
    caption_text text NOT NULL DEFAULT '',
    status text NOT NULL DEFAULT 'draft',
    visibility text NOT NULL DEFAULT 'private',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    remote_job_uuid text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_clips_asset_idx ON media_clips(asset_uuid);
CREATE INDEX IF NOT EXISTS media_clips_status_idx ON media_clips(status);

CREATE TABLE IF NOT EXISTS media_reels (
    media_reel_id bigint PRIMARY KEY,
    reel_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    description text NOT NULL DEFAULT '',
    clip_uuids jsonb NOT NULL DEFAULT '[]'::jsonb,
    visibility text NOT NULL DEFAULT 'private',
    edition_mode text NOT NULL DEFAULT 'linked',
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_reels_visibility_idx ON media_reels(visibility);

CREATE TABLE IF NOT EXISTS media_jobs (
    media_job_id bigint PRIMARY KEY,
    job_uuid uuid UNIQUE NOT NULL,
    clip_uuid uuid NOT NULL,
    owner_user_id bigint NOT NULL DEFAULT 0,
    status text NOT NULL,
    progress integer NOT NULL DEFAULT 0,
    attempt integer NOT NULL DEFAULT 0,
    max_attempts integer NOT NULL DEFAULT 3,
    remote_job_uuid text NOT NULL DEFAULT '',
    output_attachment_id bigint NOT NULL DEFAULT 0,
    poster_attachment_id bigint NOT NULL DEFAULT 0,
    output_sha256 text NOT NULL DEFAULT '',
    output_bytes bigint NOT NULL DEFAULT 0,
    error_message text NOT NULL DEFAULT '',
    created_at timestamptz,
    updated_at timestamptz,
    completed_at timestamptz,
    diagnostics jsonb NOT NULL DEFAULT '{}'::jsonb,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS media_jobs_clip_idx ON media_jobs(clip_uuid, created_at DESC);
CREATE INDEX IF NOT EXISTS media_jobs_status_idx ON media_jobs(status);

CREATE TABLE IF NOT EXISTS editorial_reviews (
    review_id bigint PRIMARY KEY,
    review_uuid uuid UNIQUE NOT NULL,
    subject_type text NOT NULL,
    subject_key text NOT NULL DEFAULT '',
    post_id bigint NOT NULL DEFAULT 0,
    workspace_uuid text NOT NULL DEFAULT '',
    owner_user_id bigint NOT NULL,
    assignee_user_id bigint NOT NULL DEFAULT 0,
    title text NOT NULL,
    summary text NOT NULL DEFAULT '',
    status text NOT NULL,
    priority text NOT NULL,
    visibility text NOT NULL,
    due_at timestamptz,
    decision_note text NOT NULL DEFAULT '',
    locked_by bigint NOT NULL DEFAULT 0,
    locked_at timestamptz,
    lock_expires_at timestamptz,
    current_revision bigint NOT NULL DEFAULT 1,
    created_at timestamptz,
    updated_at timestamptz,
    completed_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS editorial_reviews_status_idx ON editorial_reviews(status, updated_at DESC);
CREATE INDEX IF NOT EXISTS editorial_reviews_owner_idx ON editorial_reviews(owner_user_id, updated_at DESC);

CREATE TABLE IF NOT EXISTS editorial_participants (
    participant_id bigint PRIMARY KEY,
    review_id bigint NOT NULL REFERENCES editorial_reviews(review_id) ON DELETE CASCADE,
    user_id bigint NOT NULL DEFAULT 0,
    email text NOT NULL DEFAULT '',
    role text NOT NULL,
    status text NOT NULL,
    invited_by bigint NOT NULL DEFAULT 0,
    expires_at timestamptz,
    accepted_at timestamptz,
    created_at timestamptz
);
CREATE TABLE IF NOT EXISTS editorial_comments (
    comment_id bigint PRIMARY KEY,
    comment_uuid uuid UNIQUE NOT NULL,
    review_id bigint NOT NULL REFERENCES editorial_reviews(review_id) ON DELETE CASCADE,
    parent_id bigint NOT NULL DEFAULT 0,
    user_id bigint NOT NULL,
    body text NOT NULL,
    status text NOT NULL,
    anchor jsonb NOT NULL DEFAULT '{}'::jsonb,
    resolved_by bigint NOT NULL DEFAULT 0,
    resolved_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE TABLE IF NOT EXISTS editorial_suggestions (
    suggestion_id bigint PRIMARY KEY,
    suggestion_uuid uuid UNIQUE NOT NULL,
    review_id bigint NOT NULL REFERENCES editorial_reviews(review_id) ON DELETE CASCADE,
    user_id bigint NOT NULL,
    suggestion_type text NOT NULL,
    field_key text NOT NULL,
    original_text text NOT NULL DEFAULT '',
    proposed_text text NOT NULL,
    rationale text NOT NULL DEFAULT '',
    status text NOT NULL,
    decision_note text NOT NULL DEFAULT '',
    decided_by bigint NOT NULL DEFAULT 0,
    decided_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz
);
CREATE TABLE IF NOT EXISTS editorial_events (
    event_id bigint PRIMARY KEY,
    review_id bigint NOT NULL REFERENCES editorial_reviews(review_id) ON DELETE CASCADE,
    user_id bigint NOT NULL DEFAULT 0,
    event_type text NOT NULL,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz
);

CREATE TABLE IF NOT EXISTS orchestration_sessions (
    orchestration_session_id bigint PRIMARY KEY,
    session_uuid uuid UNIQUE NOT NULL,
    owner_user_id bigint NOT NULL,
    title text NOT NULL,
    question text NOT NULL,
    intent text NOT NULL,
    status text NOT NULL,
    provider text NOT NULL,
    model text NOT NULL DEFAULT '',
    retrieval_mode text NOT NULL,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS orchestration_sessions_owner_idx ON orchestration_sessions(owner_user_id, updated_at DESC);
CREATE INDEX IF NOT EXISTS orchestration_sessions_intent_idx ON orchestration_sessions(intent, status);

CREATE TABLE IF NOT EXISTS orchestration_events (
    orchestration_event_id bigint PRIMARY KEY,
    session_id bigint NOT NULL REFERENCES orchestration_sessions(orchestration_session_id) ON DELETE CASCADE,
    event_uuid uuid UNIQUE NOT NULL,
    event_type text NOT NULL,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS orchestration_events_session_idx ON orchestration_events(session_id, created_at);

CREATE TABLE IF NOT EXISTS api_keys (
    api_key_id bigint PRIMARY KEY,
    key_uuid uuid UNIQUE NOT NULL,
    name text NOT NULL,
    key_prefix text NOT NULL,
    scopes jsonb NOT NULL DEFAULT '[]'::jsonb,
    rate_limit_per_hour integer NOT NULL DEFAULT 1000,
    status text NOT NULL,
    last_used_at timestamptz,
    expires_at timestamptz,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    updated_at timestamptz,
    secret_exported boolean NOT NULL DEFAULT false
);
CREATE TABLE IF NOT EXISTS webhooks (
    webhook_id bigint PRIMARY KEY,
    webhook_uuid uuid UNIQUE NOT NULL,
    name text NOT NULL,
    endpoint_url text NOT NULL,
    secret_prefix text NOT NULL,
    events jsonb NOT NULL DEFAULT '[]'::jsonb,
    status text NOT NULL,
    last_delivery_at timestamptz,
    last_status_code integer NOT NULL DEFAULT 0,
    failure_count integer NOT NULL DEFAULT 0,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    updated_at timestamptz,
    secret_exported boolean NOT NULL DEFAULT false
);
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    webhook_delivery_id bigint PRIMARY KEY,
    delivery_uuid uuid UNIQUE NOT NULL,
    webhook_id bigint NOT NULL REFERENCES webhooks(webhook_id) ON DELETE CASCADE,
    event_id uuid NOT NULL,
    event_type text NOT NULL,
    attempt integer NOT NULL DEFAULT 0,
    status text NOT NULL,
    response_code integer NOT NULL DEFAULT 0,
    response_summary text NOT NULL DEFAULT '',
    next_attempt_at timestamptz,
    delivered_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz,
    payload_exported boolean NOT NULL DEFAULT false,
    signature_exported boolean NOT NULL DEFAULT false
);


CREATE TABLE IF NOT EXISTS preservation_snapshots (
    preservation_snapshot_id bigint PRIMARY KEY,
    snapshot_uuid uuid UNIQUE NOT NULL,
    record_id bigint NOT NULL,
    record_type text NOT NULL,
    title text NOT NULL,
    canonical_url text NOT NULL DEFAULT '',
    version_label text NOT NULL DEFAULT '',
    snapshot_status text NOT NULL,
    reason text NOT NULL,
    source_hash text NOT NULL,
    manifest_hash text NOT NULL,
    content_html text NOT NULL,
    content_text text NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    relationships jsonb NOT NULL DEFAULT '[]'::jsonb,
    resources jsonb NOT NULL DEFAULT '[]'::jsonb,
    manifest jsonb NOT NULL DEFAULT '{}'::jsonb,
    supersedes_uuid uuid,
    is_current boolean NOT NULL DEFAULT false,
    legal_hold boolean NOT NULL DEFAULT false,
    retention_until timestamptz,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz
);
CREATE INDEX IF NOT EXISTS preservation_snapshots_record_idx ON preservation_snapshots(record_id, created_at DESC);
CREATE INDEX IF NOT EXISTS preservation_snapshots_status_idx ON preservation_snapshots(snapshot_status);
CREATE INDEX IF NOT EXISTS preservation_snapshots_source_hash_idx ON preservation_snapshots(source_hash);
CREATE INDEX IF NOT EXISTS preservation_snapshots_manifest_gin ON preservation_snapshots USING gin(manifest);
CREATE INDEX IF NOT EXISTS preservation_snapshots_search_idx ON preservation_snapshots USING gin(to_tsvector('simple', title || ' ' || content_text));

CREATE TABLE IF NOT EXISTS integrity_checks (
    integrity_check_id bigint PRIMARY KEY,
    check_uuid uuid UNIQUE NOT NULL,
    run_uuid uuid NOT NULL,
    record_id bigint,
    object_type text NOT NULL,
    check_type text NOT NULL,
    target_url text NOT NULL DEFAULT '',
    expected_hash text NOT NULL DEFAULT '',
    actual_hash text NOT NULL DEFAULT '',
    status text NOT NULL,
    response_code integer NOT NULL DEFAULT 0,
    message text NOT NULL DEFAULT '',
    checked_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS integrity_checks_run_idx ON integrity_checks(run_uuid, checked_at);
CREATE INDEX IF NOT EXISTS integrity_checks_record_idx ON integrity_checks(record_id, status);
CREATE INDEX IF NOT EXISTS integrity_checks_type_idx ON integrity_checks(check_type, status);

CREATE TABLE IF NOT EXISTS authority_history (
    authority_history_id bigint PRIMARY KEY,
    authority_uuid uuid UNIQUE NOT NULL,
    record_id bigint NOT NULL,
    document_status text NOT NULL DEFAULT '',
    authority_type text NOT NULL DEFAULT '',
    authority_url text NOT NULL DEFAULT '',
    version_label text NOT NULL DEFAULT '',
    responsible_area text NOT NULL DEFAULT '',
    supersedes_id bigint,
    superseded_by_id bigint,
    changed_by bigint NOT NULL DEFAULT 0,
    changed_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS authority_history_record_idx ON authority_history(record_id, changed_at DESC);
CREATE INDEX IF NOT EXISTS authority_history_type_idx ON authority_history(authority_type, document_status);

CREATE TABLE IF NOT EXISTS readiness_runs (
    readiness_run_id bigint PRIMARY KEY,
    run_uuid uuid UNIQUE NOT NULL,
    overall_status text NOT NULL,
    score integer NOT NULL DEFAULT 0,
    created_by bigint NOT NULL DEFAULT 0,
    created_at timestamptz,
    report jsonb NOT NULL DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS readiness_runs_status_idx ON readiness_runs(overall_status, created_at DESC);
CREATE INDEX IF NOT EXISTS readiness_runs_score_idx ON readiness_runs(score, created_at DESC);

CREATE OR REPLACE VIEW current_registry AS
SELECT * FROM records WHERE historical = false AND record_state NOT IN ('archived', 'superseded', 'cancelled');

CREATE OR REPLACE VIEW public_roadmap AS
SELECT * FROM plans WHERE public = true AND plan_status NOT IN ('cancelled');
SQL;
    }

    private function postgres_sql(array $data, string $mode, string $schema_name): string {
        $s = $this->sanitize_schema_name($schema_name);
        $parts = [
            '-- Sustainable Catalyst Library portable PostgreSQL export',
            '-- Export schema: ' . self::EXPORT_SCHEMA,
            '-- Plugin version: ' . SC_LIBRARY_VERSION,
            '-- Generated: ' . gmdate('c'),
            'SET client_encoding = \'UTF8\';',
            'SET standard_conforming_strings = on;',
            'BEGIN;',
        ];
        if ($mode !== 'data') $parts[] = $this->postgres_schema_sql($s);
        if ($mode !== 'schema') {
            $parts[] = "SET search_path TO {$s}, public;";
            $parts[] = $this->metadata_inserts($data['manifest']);
            foreach ($data['entities'] as $entity => $rows) {
                $parts[] = $this->entity_inserts($entity, $rows);
            }
        }
        $parts[] = 'COMMIT;';
        $parts[] = '-- End Sustainable Catalyst Library export';
        return implode("\n\n", array_values(array_filter($parts, static fn($part) => $part !== ''))) . "\n";
    }

    private function metadata_inserts(array $manifest): string {
        $rows = [];
        foreach ($manifest as $key => $value) {
            $rows[] = "(" . $this->sql_text((string) $key) . ", " . $this->sql_json($value) . ")";
        }
        if (!$rows) return '';
        return "INSERT INTO export_metadata (metadata_key, metadata_value) VALUES\n" . implode(",\n", $rows) . "\nON CONFLICT (metadata_key) DO UPDATE SET metadata_value = EXCLUDED.metadata_value;";
    }

    private function entity_inserts(string $entity, array $rows): string {
        if (!$rows) return '';
        $columns = match ($entity) {
            'records' => ['record_id','record_identifier','kind','post_type','title','excerpt','canonical_url','record_state','content_type','area','product','responsible_area','published_at','modified_at','expected_release','article_map_id','series_order','authoritative','authority_label','historical','payload'],
            'terms' => ['term_id','taxonomy','slug','name','description','parent_term_id','payload'],
            'record_terms' => ['record_id','taxonomy','term_id','term_order'],
            'relationships' => ['relationship_id','source_record_id','target_record_id','relationship_type','note','confidence','confidence_basis','provenance_type','provenance_url','evidence_note','visibility','sort_order','created_at','updated_at','payload'],
            'graph_nodes' => ['graph_node_id','node_uuid','external_key','node_type','subtype','label','description','canonical_url','post_id','term_id','taxonomy','visibility','source_kind','source_identifier','published_at','modified_at','status','created_at','updated_at','payload'],
            'graph_edges' => ['graph_edge_id','edge_uuid','source_node_id','target_node_id','relationship_type','label','directionality','confidence','confidence_basis','provenance_type','provenance_url','evidence_note','visibility','source_kind','source_identifier','sort_order','created_by','verified_by','verified_at','created_at','updated_at','payload'],
            'resources' => ['resource_id','record_id','resource_type','url','label','sort_order','payload'],
            'documentation' => ['record_id','document_status','document_type','document_version','responsible_area','authority_type','authority_url','webpage_url','repository_url','pdf_url','release_url','last_reviewed','review_interval_days','featured','supersedes_record_id','superseded_by_record_id','dependency_ids','correction_url','authority_note','payload'],
            'plans' => ['plan_id','title','description','wordpress_status','plan_status','priority','content_type','area','product','responsible_area','public','expected_release','article_map_id','series_order','linked_draft_id','published_record_id','dependency_ids','release_group','release_track','milestone','capacity_owner','estimated_effort','effort_unit','actual_effort','progress_percent','planned_start','actual_start','dependency_policy','blocked_override','blocked_reason','actual_publication_date','created_at','modified_at','payload'],
            'plan_dependencies' => ['plan_id','dependency_record_id','dependency_order','dependency_policy','payload'],
            'account_workspaces' => ['workspace_id','workspace_uuid','owner_user_id','title','description','visibility','schema_version','content_hash','revision','last_synced_revision','sync_status','last_synced_at','created_at','updated_at','payload'],
            'account_workspace_revisions' => ['revision_id','workspace_id','revision','content_hash','change_type','created_by','created_at','payload'],
            'account_workspace_collaborators' => ['collaboration_id','workspace_id','user_id','role','invited_by','created_at','accepted_at'],
            'account_workspace_sync_log' => ['sync_log_id','workspace_id','workspace_uuid','direction','status','response_code','message','content_hash','created_at'],
            'document_jobs' => ['document_job_id','job_uuid','owner_user_id','workspace_uuid','book_id','title','document_type','status','progress','attempt','max_attempts','content_hash','renderer_version','output_attachment_id','output_sha256','output_bytes','error_message','created_at','updated_at','completed_at','manifest','diagnostics','payload'],
            'foundation_documents' => ['record_id','attachment_id','pdf_url','version_label','publication_date','author_name','publisher_name','doi','language_code','download_enabled','viewer_enabled','extraction_status','page_count','character_count','extracted_at','extraction_error','related_record_ids','payload'],
            'pdf_pages' => ['pdf_page_id','record_id','page_number','page_text','character_count','content_hash','extracted_at'],
            'foundation_versions' => ['foundation_version_id','record_id','attachment_id','version_label','filename','pdf_url','sha256','page_count','created_by','created_at','payload'],
            'document_editions' => ['document_edition_id','edition_uuid','job_uuid','owner_user_id','workspace_uuid','book_id','title','edition_label','content_hash','output_sha256','output_attachment_id','output_url','frozen_at','created_at','manifest'],
            'media_assets' => ['media_asset_id','asset_uuid','owner_user_id','title','description','media_type','source_kind','attachment_id','source_url','duration_ms','rights_status','rights_holder','license_name','license_url','rights_note','source_citation','transcript_text','transcript_vtt','captions_url','poster_attachment_id','poster_time_ms','accessibility_text','visibility','created_at','updated_at','payload'],
            'media_clips' => ['media_clip_id','clip_uuid','asset_uuid','owner_user_id','title','description','start_ms','end_ms','poster_time_ms','transcript_excerpt','caption_text','status','visibility','output_attachment_id','poster_attachment_id','remote_job_uuid','created_at','updated_at','payload'],
            'media_reels' => ['media_reel_id','reel_uuid','owner_user_id','title','description','clip_uuids','visibility','edition_mode','created_at','updated_at','payload'],
            'media_jobs' => ['media_job_id','job_uuid','clip_uuid','owner_user_id','status','progress','attempt','max_attempts','remote_job_uuid','output_attachment_id','poster_attachment_id','output_sha256','output_bytes','error_message','created_at','updated_at','completed_at','diagnostics','payload'],
            'editorial_reviews' => ['review_id','review_uuid','subject_type','subject_key','post_id','workspace_uuid','owner_user_id','assignee_user_id','title','summary','status','priority','visibility','due_at','decision_note','locked_by','locked_at','lock_expires_at','current_revision','created_at','updated_at','completed_at','payload'],
            'editorial_participants' => ['participant_id','review_id','user_id','email','role','status','invited_by','expires_at','accepted_at','created_at'],
            'editorial_comments' => ['comment_id','comment_uuid','review_id','parent_id','user_id','body','status','anchor','resolved_by','resolved_at','created_at','updated_at'],
            'editorial_suggestions' => ['suggestion_id','suggestion_uuid','review_id','user_id','suggestion_type','field_key','original_text','proposed_text','rationale','status','decision_note','decided_by','decided_at','created_at','updated_at'],
            'editorial_events' => ['event_id','review_id','user_id','event_type','payload','created_at'],
            'orchestration_sessions' => ['orchestration_session_id','session_uuid','owner_user_id','title','question','intent','status','provider','model','retrieval_mode','created_at','updated_at','payload'],
            'orchestration_events' => ['orchestration_event_id','session_id','event_uuid','event_type','created_by','created_at','payload'],
            'api_keys' => ['api_key_id','key_uuid','name','key_prefix','scopes','rate_limit_per_hour','status','last_used_at','expires_at','created_by','created_at','updated_at','secret_exported'],
            'webhooks' => ['webhook_id','webhook_uuid','name','endpoint_url','secret_prefix','events','status','last_delivery_at','last_status_code','failure_count','created_by','created_at','updated_at','secret_exported'],
            'webhook_deliveries' => ['webhook_delivery_id','delivery_uuid','webhook_id','event_id','event_type','attempt','status','response_code','response_summary','next_attempt_at','delivered_at','created_at','updated_at','payload_exported','signature_exported'],
            'preservation_snapshots' => ['preservation_snapshot_id','snapshot_uuid','record_id','record_type','title','canonical_url','version_label','snapshot_status','reason','source_hash','manifest_hash','content_html','content_text','metadata','relationships','resources','manifest','supersedes_uuid','is_current','legal_hold','retention_until','created_by','created_at'],
            'integrity_checks' => ['integrity_check_id','check_uuid','run_uuid','record_id','object_type','check_type','target_url','expected_hash','actual_hash','status','response_code','message','checked_at','payload'],
            'authority_history' => ['authority_history_id','authority_uuid','record_id','document_status','authority_type','authority_url','version_label','responsible_area','supersedes_id','superseded_by_id','changed_by','changed_at','payload'],
            'readiness_runs' => ['readiness_run_id','run_uuid','overall_status','score','created_by','created_at','report'],
            default => [],
        };
        if (!$columns) return '';
        $values = [];
        foreach ($rows as $row) {
            $literals = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if (in_array($column, ['payload', 'expected_release', 'manifest', 'metadata', 'relationships', 'resources', 'diagnostics', 'clip_uuids', 'anchor', 'scopes', 'events', 'report'], true)) $literals[] = $this->sql_json($value ?: []);
                elseif (in_array($column, ['dependency_ids','related_record_ids'], true)) $literals[] = $this->sql_bigint_array(is_array($value) ? $value : []);
                elseif (in_array($column, ['published_at','modified_at','created_at','updated_at','last_synced_at','accepted_at','completed_at','frozen_at','due_at','locked_at','lock_expires_at','expires_at','resolved_at','decided_at','verified_at','last_used_at','last_delivery_at','next_attempt_at','delivered_at','retention_until','checked_at','changed_at'], true)) $literals[] = $this->sql_timestamp($value);
                elseif (in_array($column, ['last_reviewed','planned_start','actual_start','actual_publication_date'], true)) $literals[] = $value ? $this->sql_text((string) $value) . '::date' : 'NULL';
                elseif (in_array($column, ['authoritative','historical','featured','public','blocked_override','secret_exported','payload_exported','signature_exported','download_enabled','viewer_enabled','is_current','legal_hold'], true)) $literals[] = $value ? 'TRUE' : 'FALSE';
                elseif (in_array($column, ['record_id','term_id','parent_term_id','article_map_id','relationship_id','source_record_id','target_record_id','sort_order','review_interval_days','supersedes_record_id','superseded_by_record_id','plan_id','linked_draft_id','published_record_id','term_order','dependency_record_id','dependency_order','progress_percent','workspace_id','owner_user_id','revision','last_synced_revision','revision_id','created_by','collaboration_id','user_id','invited_by','sync_log_id','response_code','document_job_id','document_edition_id','media_asset_id','media_clip_id','media_reel_id','media_job_id','attachment_id','duration_ms','poster_attachment_id','poster_time_ms','start_ms','end_ms','progress','attempt','max_attempts','output_attachment_id','output_bytes','review_id','post_id','assignee_user_id','locked_by','current_revision','participant_id','comment_id','parent_id','resolved_by','suggestion_id','decided_by','event_id','graph_node_id','graph_edge_id','source_node_id','target_node_id','verified_by','api_key_id','rate_limit_per_hour','last_status_code','failure_count','webhook_id','webhook_delivery_id','pdf_page_id','foundation_version_id','preservation_snapshot_id','integrity_check_id','authority_history_id','readiness_run_id','supersedes_id','superseded_by_id','page_number','character_count','page_count'], true)) $literals[] = ((int) $value > 0 || in_array($column, ['sort_order','term_order','review_interval_days','dependency_order','progress_percent','workspace_id','owner_user_id','revision','last_synced_revision','revision_id','created_by','collaboration_id','user_id','invited_by','sync_log_id','response_code','document_job_id','document_edition_id','media_asset_id','media_clip_id','media_reel_id','media_job_id','attachment_id','duration_ms','poster_attachment_id','poster_time_ms','start_ms','end_ms','progress','attempt','max_attempts','output_attachment_id','output_bytes','review_id','post_id','assignee_user_id','locked_by','current_revision','participant_id','comment_id','parent_id','resolved_by','suggestion_id','decided_by','event_id','graph_node_id','graph_edge_id','source_node_id','target_node_id','verified_by','api_key_id','rate_limit_per_hour','last_status_code','failure_count','webhook_id','webhook_delivery_id','pdf_page_id','foundation_version_id','preservation_snapshot_id','integrity_check_id','authority_history_id','readiness_run_id','supersedes_id','superseded_by_id','page_number','character_count','page_count'], true)) ? (string) (int) $value : 'NULL';
                elseif (in_array($column, ['series_order','estimated_effort','actual_effort','confidence'], true)) $literals[] = (string) (float) $value;
                else $literals[] = $this->sql_text((string) ($value ?? ''));
            }
            $values[] = '(' . implode(', ', $literals) . ')';
        }
        $conflict = match ($entity) {
            'records' => 'record_id',
            'terms' => 'taxonomy, term_id',
            'record_terms' => 'record_id, taxonomy, term_id',
            'relationships' => 'relationship_id',
            'graph_nodes' => 'graph_node_id',
            'graph_edges' => 'graph_edge_id',
            'resources' => 'resource_id',
            'documentation' => 'record_id',
            'plans' => 'plan_id',
            'plan_dependencies' => 'plan_id, dependency_record_id',
            'account_workspaces' => 'workspace_id',
            'account_workspace_revisions' => 'revision_id',
            'account_workspace_collaborators' => 'collaboration_id',
            'account_workspace_sync_log' => 'sync_log_id',
            'document_jobs' => 'document_job_id',
            'foundation_documents' => 'record_id',
            'pdf_pages' => 'pdf_page_id',
            'foundation_versions' => 'foundation_version_id',
            'document_editions' => 'document_edition_id',
            'media_assets' => 'media_asset_id',
            'media_clips' => 'media_clip_id',
            'media_reels' => 'media_reel_id',
            'media_jobs' => 'media_job_id',
            'editorial_reviews' => 'review_id',
            'editorial_participants' => 'participant_id',
            'editorial_comments' => 'comment_id',
            'editorial_suggestions' => 'suggestion_id',
            'editorial_events' => 'event_id',
            'orchestration_sessions' => 'orchestration_session_id',
            'orchestration_events' => 'orchestration_event_id',
            'api_keys' => 'api_key_id',
            'webhooks' => 'webhook_id',
            'webhook_deliveries' => 'webhook_delivery_id',
            'preservation_snapshots' => 'preservation_snapshot_id',
            'integrity_checks' => 'integrity_check_id',
            'authority_history' => 'authority_history_id',
            'readiness_runs' => 'readiness_run_id',
            default => '',
        };
        return "INSERT INTO {$entity} (" . implode(', ', $columns) . ") VALUES\n" . implode(",\n", $values) . ($conflict ? "\nON CONFLICT ({$conflict}) DO NOTHING;" : ';');
    }

    private function sql_text(string $value): string {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function sql_json($value): string {
        return $this->sql_text(wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '::jsonb';
    }

    private function sql_timestamp($value): string {
        return $value ? $this->sql_text((string) $value) . '::timestamptz' : 'NULL';
    }

    private function sql_bigint_array(array $values): string {
        $values = array_values(array_filter(array_map('intval', $values), static fn($v) => $v > 0));
        return $values ? "ARRAY[" . implode(',', $values) . "]::bigint[]" : "'{}'::bigint[]";
    }

    private function create_bundle(array $data, string $format, string $mode, string $schema_name, string $base): array {
        $root = trailingslashit(get_temp_dir()) . 'sc-library-export-' . wp_generate_password(12, false, false);
        wp_mkdir_p($root);
        if (!is_dir($root)) wp_die(esc_html__('Could not create a temporary export directory.', 'sustainable-catalyst-library'));

        $files = [];
        $write = function (string $name, string $content) use ($root, &$files): void {
            $path = trailingslashit($root) . $name;
            file_put_contents($path, $content);
            $files[] = $path;
        };

        $write('manifest.json', wp_json_encode($data['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($mode !== 'data') $write('schema.sql', $this->postgres_schema_sql($schema_name));
        if ($mode !== 'schema') {
            foreach ($data['entities'] as $entity => $rows) {
                if ($format === 'csv_bundle') $write($entity . '.csv', $this->rows_to_csv($rows));
                else $write($entity . '.jsonl', $this->rows_to_jsonl($rows));
            }
        }
        $write('README.md', $this->bundle_readme($format, $mode, $schema_name));

        $checksum_lines = [];
        foreach ($files as $path) $checksum_lines[] = hash_file('sha256', $path) . '  ' . basename($path);
        $write('checksums.sha256', implode("\n", $checksum_lines) . "\n");

        $zip_path = trailingslashit(get_temp_dir()) . $base . '.zip';
        if (is_file($zip_path)) @unlink($zip_path);
        require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        $archive = new PclZip($zip_path);
        $result = $archive->create($files, PCLZIP_OPT_REMOVE_PATH, $root);
        if ($result === 0) {
            $this->remove_tree($root);
            wp_die(esc_html__('Could not create the export ZIP bundle.', 'sustainable-catalyst-library'));
        }
        return ['path' => $zip_path, 'cleanup' => [$root, $zip_path]];
    }

    private function rows_to_csv(array $rows): string {
        if (!$rows) return "\n";
        $handle = fopen('php://temp', 'w+');
        $columns = array_keys($rows[0]);
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? '';
                $line[] = is_array($value) || is_object($value) ? wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $value;
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);
        return $content;
    }

    private function rows_to_jsonl(array $rows): string {
        return implode("\n", array_map(static fn($row) => wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $rows)) . ($rows ? "\n" : '');
    }

    private function bundle_readme(string $format, string $mode, string $schema_name): string {
        $label = self::formats()[$format]['label'] ?? $format;
        return "# Sustainable Catalyst Library portable export\n\n"
            . "Format: {$label}\n\nMode: {$mode}\n\nPostgreSQL schema: `{$schema_name}`\n\n"
            . "## Restore schema\n\n```bash\ncreatedb sustainable_catalyst_library\npsql -X --set ON_ERROR_STOP=on sustainable_catalyst_library < schema.sql\n```\n\n"
            . "CSV and JSONL files use the normalized entity names defined in `schema.sql`. The bundle includes SHA-256 checksums.\n\n"
            . "To create a PostgreSQL custom archive after loading the data:\n\n```bash\npg_dump -Fc sustainable_catalyst_library -f sustainable-catalyst-library.backup\n```\n";
    }

    private function send_string_download(string $content, string $filename, string $mime): void {
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . strlen($content));
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function send_file_download(string $path, string $filename, string $mime, array $cleanup): void {
        if (!is_readable($path)) wp_die(esc_html__('The export file could not be read.', 'sustainable-catalyst-library'));
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        foreach ($cleanup as $item) {
            if (is_dir($item)) $this->remove_tree($item);
            elseif (is_file($item)) @unlink($item);
        }
        exit;
    }

    private function remove_tree(string $path): void {
        if (!is_dir($path)) return;
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) $this->remove_tree($child); else @unlink($child);
        }
        @rmdir($path);
    }
}
