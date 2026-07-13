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
    public const EXPORT_SCHEMA = 'sc-library-portable-export/1.1';
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
            'relationships' => __('Relationships, terms, and resources', 'sustainable-catalyst-library'),
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
        } elseif ($scope === 'planner') {
            $records = [];
            $terms = [];
            $record_terms = [];
            $relationships = [];
            $resources = [];
            $documentation = [];
        } elseif ($scope === 'documentation') {
            $doc_ids = array_map(static fn($row) => (int) $row['record_id'], $documentation);
            $records = $this->filter_rows_by_ids($records, 'record_id', $doc_ids);
            $record_terms = $this->filter_rows_by_ids($record_terms, 'record_id', $doc_ids);
            $resources = $this->filter_rows_by_ids($resources, 'record_id', $doc_ids);
            $relationships = array_values(array_filter($relationships, static fn($row) => in_array((int) $row['source_post_id'], $doc_ids, true) || in_array((int) $row['target_post_id'], $doc_ids, true)));
            $plans = [];
            $plan_dependencies = [];
        } elseif ($scope === 'relationships') {
            $records = [];
            $documentation = [];
            $plans = [];
            $plan_dependencies = [];
        } elseif ($scope === 'schema') {
            $records = $terms = $record_terms = $relationships = $resources = $documentation = $plans = $plan_dependencies = [];
        }

        $entities = [
            'records' => $records,
            'terms' => $terms,
            'record_terms' => $record_terms,
            'relationships' => array_map([$this, 'normalize_relationship'], $relationships),
            'resources' => $resources,
            'documentation' => $documentation,
            'plans' => $plans,
            'plan_dependencies' => $plan_dependencies,
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
                'Private planning data is excluded unless an administrator explicitly enables it.',
                'PDFs and repository records remain references; binary media is not embedded in data exports.',
            ],
        ];
        return ['manifest' => $manifest, 'entities' => $entities];
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

    private function normalize_relationship(array $row): array {
        return [
            'relationship_id' => (int) $row['id'],
            'source_record_id' => (int) $row['source_post_id'],
            'target_record_id' => (int) $row['target_post_id'],
            'relationship_type' => (string) $row['relationship_type'],
            'note' => (string) $row['note'],
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
    sort_order integer NOT NULL DEFAULT 0,
    created_at timestamptz,
    updated_at timestamptz,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    UNIQUE (source_record_id, target_record_id, relationship_type)
);
CREATE INDEX IF NOT EXISTS relationships_source_idx ON relationships(source_record_id);
CREATE INDEX IF NOT EXISTS relationships_target_idx ON relationships(target_record_id);
CREATE INDEX IF NOT EXISTS relationships_type_idx ON relationships(relationship_type);

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
            'relationships' => ['relationship_id','source_record_id','target_record_id','relationship_type','note','sort_order','created_at','updated_at','payload'],
            'resources' => ['resource_id','record_id','resource_type','url','label','sort_order','payload'],
            'documentation' => ['record_id','document_status','document_type','document_version','responsible_area','authority_type','authority_url','webpage_url','repository_url','pdf_url','release_url','last_reviewed','review_interval_days','featured','supersedes_record_id','superseded_by_record_id','dependency_ids','correction_url','authority_note','payload'],
            'plans' => ['plan_id','title','description','wordpress_status','plan_status','priority','content_type','area','product','responsible_area','public','expected_release','article_map_id','series_order','linked_draft_id','published_record_id','dependency_ids','release_group','release_track','milestone','capacity_owner','estimated_effort','effort_unit','actual_effort','progress_percent','planned_start','actual_start','dependency_policy','blocked_override','blocked_reason','actual_publication_date','created_at','modified_at','payload'],
            'plan_dependencies' => ['plan_id','dependency_record_id','dependency_order','dependency_policy','payload'],
            default => [],
        };
        if (!$columns) return '';
        $values = [];
        foreach ($rows as $row) {
            $literals = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if (in_array($column, ['payload', 'expected_release'], true)) $literals[] = $this->sql_json($value ?: []);
                elseif (in_array($column, ['dependency_ids'], true)) $literals[] = $this->sql_bigint_array(is_array($value) ? $value : []);
                elseif (in_array($column, ['published_at','modified_at','created_at','updated_at'], true)) $literals[] = $this->sql_timestamp($value);
                elseif (in_array($column, ['last_reviewed','planned_start','actual_start','actual_publication_date'], true)) $literals[] = $value ? $this->sql_text((string) $value) . '::date' : 'NULL';
                elseif (in_array($column, ['authoritative','historical','featured','public','blocked_override'], true)) $literals[] = $value ? 'TRUE' : 'FALSE';
                elseif (in_array($column, ['record_id','term_id','parent_term_id','article_map_id','relationship_id','source_record_id','target_record_id','sort_order','review_interval_days','supersedes_record_id','superseded_by_record_id','plan_id','linked_draft_id','published_record_id','term_order','dependency_record_id','dependency_order','progress_percent'], true)) $literals[] = ((int) $value > 0 || in_array($column, ['sort_order','term_order','review_interval_days','dependency_order','progress_percent'], true)) ? (string) (int) $value : 'NULL';
                elseif (in_array($column, ['series_order','estimated_effort','actual_effort'], true)) $literals[] = (string) (float) $value;
                else $literals[] = $this->sql_text((string) ($value ?? ''));
            }
            $values[] = '(' . implode(', ', $literals) . ')';
        }
        $conflict = match ($entity) {
            'records' => 'record_id',
            'terms' => 'taxonomy, term_id',
            'record_terms' => 'record_id, taxonomy, term_id',
            'relationships' => 'relationship_id',
            'resources' => 'resource_id',
            'documentation' => 'record_id',
            'plans' => 'plan_id',
            'plan_dependencies' => 'plan_id, dependency_record_id',
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
