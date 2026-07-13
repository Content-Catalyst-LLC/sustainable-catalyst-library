<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Living Knowledge System coordination layer.
 *
 * This class does not replace the specialist Library modules. It publishes a
 * stable system manifest, a cross-module activity stream, and coherent public,
 * research, and institutional navigation across those modules.
 */
final class SC_Library_Unified_System {
    public const SCHEMA = 'sc-library-living-system/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-system-manifest/1.0';
    public const EVENT_SCHEMA = 'sc-library-system-event/1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public static function manifests_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_system_manifests';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_system_events';
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_unified_system', 1);
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 16);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_post_sc_library_create_system_manifest', [$this, 'handle_create_manifest']);
        add_action('admin_post_sc_library_create_living_system_page', [$this, 'handle_create_portal_page']);

        add_shortcode('sc_library_living_system', [$this, 'render_portal_shortcode']);
        add_shortcode('sc_library_unified_workspace', [$this, 'render_workspace_shortcode']);
        add_shortcode('sc_library_system_status', [$this, 'render_status_shortcode']);

        add_action('save_post', [$this, 'capture_post_event'], 30, 3);
        add_action('sc_library_workspace_revised', [$this, 'capture_workspace_event'], 10, 1);
        add_action('sc_library_review_transitioned', [$this, 'capture_review_event'], 10, 1);
        add_action('sc_library_media_clip_completed', [$this, 'capture_media_event'], 10, 1);
        add_action('sc_library_document_rendered', [$this, 'capture_document_event'], 10, 1);
        add_action('sc_library_preservation_snapshot_created', [$this, 'capture_preservation_event'], 10, 1);
        add_action('sc_library_integrity_audit_completed', [$this, 'capture_integrity_event'], 10, 1);
        add_action('sc_library_knowledge_graph_rebuilt', [$this, 'capture_graph_event'], 10, 2);
        add_action('sc_library_foundation_document_extracted', [$this, 'capture_foundation_event'], 10, 2);
    }

    public function register_settings(): void {
        register_setting('sc_library_unified_system_settings', 'sc_library_enable_unified_system', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_unified_system_settings', 'sc_library_living_system_page_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/living-knowledge-system/'),
        ]);
        register_setting('sc_library_unified_system_settings', 'sc_library_living_system_title', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Sustainable Catalyst Living Knowledge System',
        ]);
        register_setting('sc_library_unified_system_settings', 'sc_library_living_system_activity_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(50, max(0, absint($value))),
            'default' => 8,
        ]);
        register_setting('sc_library_unified_system_settings', 'sc_library_living_system_embed_library', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Living Knowledge System', 'sustainable-catalyst-library'),
            __('Living Knowledge System', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-living-system',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'sc-library_page_sc-library-living-system') {
            return;
        }
        wp_enqueue_style('sc-library-unified-system', SC_LIBRARY_URL . 'assets/css/sc-library-unified-system.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-unified-system', SC_LIBRARY_URL . 'assets/js/sc-library-unified-system.js', [], SC_LIBRARY_VERSION, true);
    }

    public function frontend_assets(): void {
        if (!self::enabled() || !is_singular()) {
            return;
        }
        $post = get_post();
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        $active = false;
        foreach (['sc_library_living_system', 'sc_library_unified_workspace', 'sc_library_system_status'] as $shortcode) {
            if ($content !== '' && has_shortcode($content, $shortcode)) {
                $active = true;
                break;
            }
        }
        if (!$active) {
            return;
        }
        self::enqueue_assets();
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-unified-system', SC_LIBRARY_URL . 'assets/css/sc-library-unified-system.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-unified-system', SC_LIBRARY_URL . 'assets/js/sc-library-unified-system.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-unified-system', 'SCLibraryUnifiedSystem', [
            'schema' => self::SCHEMA,
            'statusEndpoint' => rest_url('sustainable-catalyst/v1/library/system/status'),
            'activityEndpoint' => rest_url('sustainable-catalyst/v1/library/system/activity'),
            'strings' => [
                'copied' => __('System manifest copied.', 'sustainable-catalyst-library'),
                'copyFailed' => __('The system manifest could not be copied.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';
        register_rest_route($namespace, '/library/system/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->public_manifest()),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/system/capabilities', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response([
                'schema' => self::SCHEMA,
                'version' => SC_LIBRARY_VERSION,
                'capabilities' => array_values($this->components()),
                'journeys' => $this->journeys(),
            ]),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/system/activity', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response {
                $limit = min(50, max(1, absint($request->get_param('limit') ?: 10)));
                return rest_ensure_response([
                    'schema' => self::EVENT_SCHEMA,
                    'events' => $this->recent_events($limit, true),
                ]);
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/library/system/manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->current_manifest(false)),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route($namespace, '/library/system/manifest/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => fn() => rest_ensure_response($this->persist_manifest()),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sustainable-catalyst-library/v1', '/system', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->public_manifest()),
            'permission_callback' => '__return_true',
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage the Living Knowledge System.', 'sustainable-catalyst-library'));
        }
        $manifest = $this->current_manifest(false);
        $events = $this->recent_events(12, false);
        $latest = $this->latest_persisted_manifest();
        ?>
        <div class="wrap sc-library-system-admin">
            <h1><?php esc_html_e('Sustainable Catalyst Living Knowledge System', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Coordinate the public knowledge layer, authenticated research workspace, and institutional operations layer without replacing their specialist tools.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-system-admin__summary">
                <article><strong><?php echo esc_html(number_format_i18n((int) ($manifest['counts']['indexed_records'] ?? 0))); ?></strong><span><?php esc_html_e('Indexed records', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n((int) ($manifest['counts']['graph_nodes'] ?? 0))); ?></strong><span><?php esc_html_e('Graph entities', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n((int) ($manifest['counts']['workspaces'] ?? 0))); ?></strong><span><?php esc_html_e('Account workspaces', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n((int) ($manifest['counts']['snapshots'] ?? 0))); ?></strong><span><?php esc_html_e('Preserved editions', 'sustainable-catalyst-library'); ?></span></article>
            </div>

            <div class="sc-library-system-admin__grid">
                <section class="card">
                    <h2><?php esc_html_e('Unified public portal', 'sustainable-catalyst-library'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('sc_library_unified_system_settings'); ?>
                        <table class="form-table" role="presentation">
                            <tr><th scope="row"><?php esc_html_e('Enable system layer', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_unified_system" value="1" <?php checked((bool) get_option('sc_library_enable_unified_system', 1)); ?>> <?php esc_html_e('Enabled', 'sustainable-catalyst-library'); ?></label></td></tr>
                            <tr><th scope="row"><label for="sc-library-system-title"><?php esc_html_e('Portal title', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-library-system-title" class="regular-text" name="sc_library_living_system_title" value="<?php echo esc_attr((string) get_option('sc_library_living_system_title', 'Sustainable Catalyst Living Knowledge System')); ?>"></td></tr>
                            <tr><th scope="row"><label for="sc-library-system-url"><?php esc_html_e('Portal URL', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-library-system-url" class="regular-text" type="url" name="sc_library_living_system_page_url" value="<?php echo esc_attr((string) get_option('sc_library_living_system_page_url', home_url('/living-knowledge-system/'))); ?>"><p class="description"><code>[sc_library_living_system]</code></p></td></tr>
                            <tr><th scope="row"><?php esc_html_e('Embed Library Explorer', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_living_system_embed_library" value="1" <?php checked((bool) get_option('sc_library_living_system_embed_library', 1)); ?>> <?php esc_html_e('Include the searchable Library Explorer in the complete portal.', 'sustainable-catalyst-library'); ?></label></td></tr>
                            <tr><th scope="row"><label for="sc-library-system-events"><?php esc_html_e('Public activity items', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-library-system-events" type="number" min="0" max="50" name="sc_library_living_system_activity_limit" value="<?php echo esc_attr((string) get_option('sc_library_living_system_activity_limit', 8)); ?>"></td></tr>
                        </table>
                        <?php submit_button(__('Save system settings', 'sustainable-catalyst-library')); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sc_library_create_living_system_page">
                        <?php wp_nonce_field('sc_library_create_living_system_page'); ?>
                        <?php submit_button(__('Create or locate portal page', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
                </section>

                <section class="card">
                    <h2><?php esc_html_e('System manifest', 'sustainable-catalyst-library'); ?></h2>
                    <p><?php esc_html_e('Create a checksummed snapshot of component states, counts, routes, page locations, schemas, and data boundaries.', 'sustainable-catalyst-library'); ?></p>
                    <?php if ($latest) : ?><p><strong><?php esc_html_e('Latest:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html((string) $latest['created_at']); ?> · <code><?php echo esc_html(substr((string) $latest['content_hash'], 0, 16)); ?></code></p><?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sc_library_create_system_manifest">
                        <?php wp_nonce_field('sc_library_create_system_manifest'); ?>
                        <?php submit_button(__('Create system manifest', 'sustainable-catalyst-library'), 'primary', 'submit', false); ?>
                    </form>
                    <details><summary><?php esc_html_e('Inspect current manifest', 'sustainable-catalyst-library'); ?></summary><pre data-system-manifest><?php echo esc_html(wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><button type="button" class="button" data-copy-system-manifest><?php esc_html_e('Copy manifest', 'sustainable-catalyst-library'); ?></button></details>
                </section>
            </div>

            <h2><?php esc_html_e('System components', 'sustainable-catalyst-library'); ?></h2>
            <div class="sc-library-system-components">
                <?php foreach ($manifest['components'] as $component) : ?>
                    <article class="sc-library-system-component sc-library-system-component--<?php echo esc_attr((string) $component['status']); ?>">
                        <span><?php echo esc_html(ucfirst((string) $component['status'])); ?></span>
                        <h3><?php echo esc_html((string) $component['label']); ?></h3>
                        <p><?php echo esc_html((string) $component['description']); ?></p>
                        <strong><?php echo esc_html(number_format_i18n((int) $component['count'])); ?></strong>
                        <?php if (!empty($component['url'])) : ?><a href="<?php echo esc_url((string) $component['url']); ?>"><?php esc_html_e('Open', 'sustainable-catalyst-library'); ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Recent cross-system activity', 'sustainable-catalyst-library'); ?></h2>
            <?php $this->render_event_list($events); ?>
        </div>
        <?php
    }

    public function render_portal_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        self::enqueue_assets();
        $atts = shortcode_atts([
            'mode' => 'complete',
            'title' => (string) get_option('sc_library_living_system_title', 'Sustainable Catalyst Living Knowledge System'),
            'embed_library' => (bool) get_option('sc_library_living_system_embed_library', 1) ? 'true' : 'false',
            'show_activity' => 'true',
        ], $atts, 'sc_library_living_system');
        $system_mode = in_array(sanitize_key((string) $atts['mode']), ['complete', 'public', 'research', 'institutional'], true) ? sanitize_key((string) $atts['mode']) : 'complete';
        $system_title = sanitize_text_field((string) $atts['title']);
        $show_library = filter_var($atts['embed_library'], FILTER_VALIDATE_BOOLEAN) && in_array($system_mode, ['complete', 'public'], true);
        $show_activity = filter_var($atts['show_activity'], FILTER_VALIDATE_BOOLEAN);
        $system_manifest = $this->public_manifest();
        $system_urls = $this->page_urls();
        $system_journeys = $this->journeys();
        $recent_events = $show_activity ? $this->recent_events((int) get_option('sc_library_living_system_activity_limit', 8), true) : [];
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-living-system.php';
        return (string) ob_get_clean();
    }

    public function render_workspace_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        self::enqueue_assets();
        $atts = shortcode_atts(['view' => 'overview', 'title' => __('Unified Research Workspace', 'sustainable-catalyst-library')], $atts, 'sc_library_unified_workspace');
        $requested_view = isset($_GET['workspace_view']) ? sanitize_key(wp_unslash((string) $_GET['workspace_view'])) : '';
        $workspace_view = $requested_view !== '' ? $requested_view : sanitize_key((string) $atts['view']);
        $allowed = ['overview', 'notebook', 'librarian', 'graph', 'books', 'editorial', 'portability'];
        if (!in_array($workspace_view, $allowed, true)) {
            $workspace_view = 'overview';
        }
        $workspace_title = sanitize_text_field((string) $atts['title']);
        $workspace_urls = $this->page_urls();
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-unified-workspace.php';
        return (string) ob_get_clean();
    }

    public function render_status_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        self::enqueue_assets();
        $atts = shortcode_atts(['show_counts' => 'true'], $atts, 'sc_library_system_status');
        $manifest = $this->public_manifest();
        ob_start();
        ?>
        <section class="sc-library-system-status" aria-labelledby="sc-library-system-status-title">
            <header><p><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p><h2 id="sc-library-system-status-title"><?php esc_html_e('Living Knowledge System Status', 'sustainable-catalyst-library'); ?></h2><span><?php echo esc_html((string) $manifest['overall_status']); ?></span></header>
            <div class="sc-library-system-status__grid">
                <?php foreach ($manifest['components'] as $component) : ?>
                    <article><h3><?php echo esc_html((string) $component['label']); ?></h3><p><?php echo esc_html(ucfirst((string) $component['status'])); ?></p><?php if (filter_var($atts['show_counts'], FILTER_VALIDATE_BOOLEAN)) : ?><strong><?php echo esc_html(number_format_i18n((int) $component['count'])); ?></strong><?php endif; ?></article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_create_manifest(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to create a system manifest.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_create_system_manifest');
        $result = $this->persist_manifest();
        $status = is_wp_error($result) ? 'error' : 'created';
        wp_safe_redirect(add_query_arg('manifest', $status, admin_url('admin.php?page=sc-library-living-system')));
        exit;
    }

    public function handle_create_portal_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to create the portal page.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_create_living_system_page');
        $existing = get_page_by_path('living-knowledge-system');
        if (!$existing instanceof WP_Post) {
            $id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_title' => __('Living Knowledge System', 'sustainable-catalyst-library'),
                'post_name' => 'living-knowledge-system',
                'post_content' => '[sc_library_living_system]',
            ], true);
            if (!is_wp_error($id)) {
                $existing = get_post((int) $id);
            }
        }
        if ($existing instanceof WP_Post) {
            update_option('sc_library_living_system_page_url', get_permalink($existing));
            wp_safe_redirect(get_edit_post_link($existing->ID, 'raw'));
            exit;
        }
        wp_safe_redirect(add_query_arg('page_created', 'error', admin_url('admin.php?page=sc-library-living-system')));
        exit;
    }

    public function persist_manifest() {
        global $wpdb;
        $manifest = $this->current_manifest(false);
        $json = (string) wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $uuid = wp_generate_uuid4();
        $hash = hash('sha256', $json);
        $inserted = $wpdb->insert(self::manifests_table(), [
            'manifest_uuid' => $uuid,
            'schema_version' => self::MANIFEST_SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'status' => (string) $manifest['overall_status'],
            'manifest_json' => $json,
            'content_hash' => $hash,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']);
        if ($inserted === false) {
            return new WP_Error('sc_library_manifest_write_failed', __('The system manifest could not be saved.', 'sustainable-catalyst-library'));
        }
        $this->record_event('system.manifest.created', 'system_manifest', $uuid, __('System manifest created', 'sustainable-catalyst-library'), __('A checksummed Living Knowledge System manifest was created.', 'sustainable-catalyst-library'), ['content_hash' => $hash], 'organization');
        do_action('sc_library_system_manifest_created', ['manifest_uuid' => $uuid, 'content_hash' => $hash, 'status' => $manifest['overall_status']]);
        return ['ok' => true, 'manifest_uuid' => $uuid, 'content_hash' => $hash, 'manifest' => $manifest];
    }

    public function current_manifest(bool $public = false): array {
        $components = array_values($this->components());
        $counts = $this->counts();
        $statuses = array_count_values(array_map(static fn($component) => (string) $component['status'], $components));
        $overall = ($statuses['error'] ?? 0) > 0 ? 'action_required' : ((($statuses['warning'] ?? 0) > 0) ? 'review_recommended' : 'ready');
        $manifest = [
            'schema' => self::MANIFEST_SCHEMA,
            'system_schema' => self::SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'generated_at' => gmdate('c'),
            'overall_status' => $overall,
            'source_of_truth' => 'WordPress publishing and Sustainable Catalyst Library metadata',
            'architecture' => [
                'public' => 'Discovery, records, relationships, documents, roadmaps, and archive',
                'research' => 'Notebook, sources, matrices, boards, calculations, investigations, synthesis, and books',
                'institutional' => 'Planning, editorial review, API, preservation, portability, and readiness',
            ],
            'counts' => $counts,
            'components' => $components,
            'journeys' => $this->journeys(),
            'pages' => $this->page_urls(),
            'routes' => $this->routes(),
            'data_boundaries' => [
                'canonical_publishing' => 'WordPress',
                'browser_local_research' => 'Explicit local workspace only',
                'account_research' => 'Authenticated WordPress workspace storage',
                'render_services' => 'Optional processing and synchronization',
                'postgresql' => 'Portable application-oriented exports and optional services',
                'automatic_publication' => false,
                'private_data_publicly_exposed' => false,
            ],
        ];
        if ($public) {
            unset($manifest['routes']['administrative']);
            foreach ($manifest['components'] as &$component) {
                unset($component['admin_url']);
            }
            unset($component);
        }
        return $manifest;
    }

    public function public_manifest(): array {
        return $this->current_manifest(true);
    }

    private function components(): array {
        $counts = $this->counts();
        $urls = $this->page_urls();
        $components = [
            'discovery' => $this->component('discovery', __('Discovery and Registry', 'sustainable-catalyst-library'), __('Searchable canonical records, facets, article maps, documentation, and public roadmap.', 'sustainable-catalyst-library'), $counts['indexed_records'], $urls['library'], true),
            'documents' => $this->component('documents', __('Foundation Documents', 'sustainable-catalyst-library'), __('Embedded PDFs, page-aware full-text search, citations, versions, and related records.', 'sustainable-catalyst-library'), $counts['foundation_documents'], $urls['library'], class_exists('SC_Library_Foundation_Documents') && SC_Library_Foundation_Documents::enabled()),
            'graph' => $this->component('graph', __('Knowledge Graph', 'sustainable-catalyst-library'), __('Relationship intelligence, provenance, confidence, timelines, places, and graph traversal.', 'sustainable-catalyst-library'), $counts['graph_nodes'], $urls['graph'], class_exists('SC_Library_Knowledge_Graph') && SC_Library_Knowledge_Graph::enabled()),
            'workspace' => $this->component('workspace', __('Research Workspace', 'sustainable-catalyst-library'), __('Notebook, sources, matrices, boards, annotations, books, and persistent account revisions.', 'sustainable-catalyst-library'), $counts['workspaces'], $urls['library'], class_exists('SC_Library_Notebook') && SC_Library_Notebook::enabled()),
            'orchestration' => $this->component('orchestration', __('Research Librarian', 'sustainable-catalyst-library'), __('Site-scoped retrieval, transparent recommendations, confirmed actions, and tool routing.', 'sustainable-catalyst-library'), $counts['orchestration_sessions'], $urls['research_librarian'], class_exists('SC_Library_Orchestrator') && SC_Library_Orchestrator::enabled()),
            'planning' => $this->component('planning', __('Planning and Roadmaps', 'sustainable-catalyst-library'), __('Content plans, release coordination, dependencies, public tracking, and article-map progress.', 'sustainable-catalyst-library'), $counts['plans'], $urls['library'], class_exists('SC_Library_Planner') && SC_Library_Planner::enabled()),
            'editorial' => $this->component('editorial', __('Collaboration and Review', 'sustainable-catalyst-library'), __('Participants, comments, suggestions, approvals, locks, and attributed activity.', 'sustainable-catalyst-library'), $counts['reviews'], $urls['library'], class_exists('SC_Library_Collaboration') && SC_Library_Collaboration::enabled()),
            'multimedia' => $this->component('multimedia', __('Multimedia and Evidence Reels', 'sustainable-catalyst-library'), __('Rights-aware media records, non-destructive clips, transcripts, captions, and evidence reels.', 'sustainable-catalyst-library'), $counts['media_assets'], $urls['library'], class_exists('SC_Library_Multimedia') && SC_Library_Multimedia::enabled()),
            'developer' => $this->component('developer', __('Developer Platform', 'sustainable-catalyst-library'), __('Versioned public API, scoped keys, signed webhooks, OpenAPI, and JSON Schemas.', 'sustainable-catalyst-library'), $counts['api_keys'], $urls['developers'], class_exists('SC_Library_Developer_API') && SC_Library_Developer_API::enabled()),
            'preservation' => $this->component('preservation', __('Institutional Archive', 'sustainable-catalyst-library'), __('Frozen editions, checksums, authority history, integrity audits, and retention controls.', 'sustainable-catalyst-library'), $counts['snapshots'], $urls['archive'], class_exists('SC_Library_Preservation') && SC_Library_Preservation::enabled()),
            'readiness' => $this->component('readiness', __('Production Readiness', 'sustainable-catalyst-library'), __('Accessibility, mobile, performance, security, maintenance, and launch diagnostics.', 'sustainable-catalyst-library'), $counts['readiness_runs'], $urls['status'], class_exists('SC_Library_Hardening')),
        ];
        return $components;
    }

    private function component(string $id, string $label, string $description, int $count, string $url, bool $enabled): array {
        $status = !$enabled ? 'disabled' : ($count > 0 || in_array($id, ['workspace', 'developer', 'readiness'], true) ? 'active' : 'warning');
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'status' => $status,
            'count' => $count,
            'url' => $url,
            'admin_url' => admin_url('admin.php?page=sc-library-living-system'),
        ];
    }

    private function counts(): array {
        return [
            'published_posts' => $this->published_count('post'),
            'indexed_records' => $this->indexer->count_indexed(),
            'relationships' => $this->relationships->count(),
            'foundation_documents' => $this->published_count('sc_foundation_doc'),
            'pdf_pages' => $this->table_count('sc_library_pdf_pages'),
            'graph_nodes' => $this->table_count('sc_library_graph_nodes', "status = 'active'"),
            'graph_edges' => $this->table_count('sc_library_graph_edges'),
            'plans' => $this->post_type_total('sc_content_plan'),
            'workspaces' => $this->table_count('sc_library_workspaces'),
            'reviews' => $this->table_count('sc_library_reviews'),
            'media_assets' => $this->table_count('sc_library_media_assets'),
            'media_clips' => $this->table_count('sc_library_media_clips'),
            'document_editions' => $this->table_count('sc_library_document_editions'),
            'orchestration_sessions' => $this->table_count('sc_library_orchestration_sessions'),
            'api_keys' => $this->table_count('sc_library_api_keys', "status = 'active'"),
            'webhooks' => $this->table_count('sc_library_webhooks', "status = 'active'"),
            'snapshots' => $this->table_count('sc_library_preservation_snapshots'),
            'readiness_runs' => $this->table_count('sc_library_readiness_runs'),
            'system_manifests' => $this->table_count('sc_library_system_manifests'),
            'system_events' => $this->table_count('sc_library_system_events'),
        ];
    }

    private function published_count(string $post_type): int {
        $counts = wp_count_posts($post_type);
        return $counts && isset($counts->publish) ? (int) $counts->publish : 0;
    }

    private function post_type_total(string $post_type): int {
        $counts = wp_count_posts($post_type);
        if (!$counts) {
            return 0;
        }
        $total = 0;
        foreach ((array) $counts as $status => $count) {
            if (!in_array($status, ['trash', 'auto-draft'], true)) {
                $total += (int) $count;
            }
        }
        return $total;
    }

    private function table_count(string $suffix, string $where = ''): int {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM {$table}" . ($where !== '' ? " WHERE {$where}" : '');
        return (int) $wpdb->get_var($sql);
    }

    private function page_urls(): array {
        return [
            'living_system' => (string) get_option('sc_library_living_system_page_url', home_url('/living-knowledge-system/')),
            'library' => (string) get_option('sc_library_main_page_url', home_url('/research-library/')),
            'graph' => (string) get_option('sc_library_graph_page_url', home_url('/knowledge-graph/')),
            'research_librarian' => (string) get_option('sc_library_orchestrator_page_url', home_url('/research-librarian/')),
            'developers' => (string) get_option('sc_library_developer_portal_url', home_url('/developers/')),
            'archive' => (string) get_option('sc_library_archive_page_url', home_url('/institutional-archive/')),
            'status' => (string) get_option('sc_library_readiness_page_url', home_url('/library-status/')),
            'workbench' => (string) get_option('sc_library_workbench_url', home_url('/workbench/')),
            'decision_studio' => (string) get_option('sc_library_decision_studio_url', home_url('/decision-studio/')),
            'site_intelligence' => (string) get_option('sc_library_site_intelligence_url', home_url('/site-intelligence/')),
            'lab' => (string) get_option('sc_library_lab_url', home_url('/lab/')),
        ];
    }

    private function routes(): array {
        return [
            'public' => [
                rest_url('sustainable-catalyst/v1/library/system/status'),
                rest_url('sustainable-catalyst/v1/library/system/capabilities'),
                rest_url('sustainable-catalyst/v1/library/system/activity'),
                rest_url('sustainable-catalyst-library/v1/system'),
            ],
            'administrative' => [
                rest_url('sustainable-catalyst/v1/library/system/manifest'),
                rest_url('sustainable-catalyst/v1/library/system/manifest/create'),
            ],
        ];
    }

    private function journeys(): array {
        return [
            ['id' => 'discover', 'label' => __('Discover', 'sustainable-catalyst-library'), 'description' => __('Search publications, documents, concepts, series, and planned knowledge.', 'sustainable-catalyst-library'), 'destination' => 'library'],
            ['id' => 'connect', 'label' => __('Connect', 'sustainable-catalyst-library'), 'description' => __('Follow relationships, provenance, timelines, places, and article-map sequences.', 'sustainable-catalyst-library'), 'destination' => 'graph'],
            ['id' => 'research', 'label' => __('Research', 'sustainable-catalyst-library'), 'description' => __('Save records, collect sources, annotate, translate, and build visual structures.', 'sustainable-catalyst-library'), 'destination' => 'library'],
            ['id' => 'analyze', 'label' => __('Analyze', 'sustainable-catalyst-library'), 'description' => __('Route calculations, scenarios, geographic investigations, and experiments to connected tools.', 'sustainable-catalyst-library'), 'destination' => 'research_librarian'],
            ['id' => 'produce', 'label' => __('Produce', 'sustainable-catalyst-library'), 'description' => __('Create books, documents, media evidence, review packets, and publication plans.', 'sustainable-catalyst-library'), 'destination' => 'library'],
            ['id' => 'publish', 'label' => __('Publish', 'sustainable-catalyst-library'), 'description' => __('Move approved work into canonical WordPress records without automatic publication.', 'sustainable-catalyst-library'), 'destination' => 'library'],
            ['id' => 'preserve', 'label' => __('Preserve', 'sustainable-catalyst-library'), 'description' => __('Freeze editions, verify integrity, export portable data, and maintain authority history.', 'sustainable-catalyst-library'), 'destination' => 'archive'],
        ];
    }

    private function latest_persisted_manifest(): ?array {
        global $wpdb;
        $table = self::manifests_table();
        $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function recent_events(int $limit = 10, bool $public_only = false): array {
        global $wpdb;
        $limit = min(100, max(1, $limit));
        $where = $public_only ? "WHERE visibility = 'public'" : '';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT event_uuid, event_type, object_type, object_id, visibility, actor_user_id, title, summary, payload_json, created_at FROM " . self::events_table() . " {$where} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) $row['payload_json'], true) ?: [];
            unset($row['payload_json']);
            if ($public_only) {
                unset($row['actor_user_id']);
            }
        }
        unset($row);
        return $rows;
    }

    private function render_event_list(array $events): void {
        if (!$events) {
            echo '<p>' . esc_html__('No cross-system events have been recorded yet.', 'sustainable-catalyst-library') . '</p>';
            return;
        }
        echo '<ol class="sc-library-system-events">';
        foreach ($events as $event) {
            echo '<li><span>' . esc_html((string) $event['event_type']) . '</span><strong>' . esc_html((string) $event['title']) . '</strong><p>' . esc_html((string) $event['summary']) . '</p><time>' . esc_html((string) $event['created_at']) . '</time></li>';
        }
        echo '</ol>';
    }

    private function record_event(string $event_type, string $object_type, string $object_id, string $title, string $summary, array $payload = [], string $visibility = 'organization'): void {
        global $wpdb;
        if (!$this->table_exists(self::events_table())) {
            return;
        }
        $visibility = in_array($visibility, ['public', 'organization', 'private'], true) ? $visibility : 'organization';
        $wpdb->insert(self::events_table(), [
            'event_uuid' => wp_generate_uuid4(),
            'event_type' => sanitize_key($event_type),
            'object_type' => sanitize_key($object_type),
            'object_id' => sanitize_text_field($object_id),
            'visibility' => $visibility,
            'actor_user_id' => get_current_user_id(),
            'title' => sanitize_text_field($title),
            'summary' => sanitize_textarea_field($summary),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
    }

    private function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function capture_post_event(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }
        $eligible = in_array($post->post_type, array_merge($this->indexer->configured_post_types(), ['sc_content_plan', 'sc_foundation_doc']), true);
        if (!$eligible) {
            return;
        }
        $visibility = $post->post_status === 'publish' && $post->post_password === '' ? 'public' : 'organization';
        $this->record_event($update ? 'record.updated' : 'record.created', 'wordpress_record', (string) $post_id, get_the_title($post_id), $update ? __('A canonical Library record was updated.', 'sustainable-catalyst-library') : __('A canonical Library record was created.', 'sustainable-catalyst-library'), ['post_type' => $post->post_type, 'status' => $post->post_status, 'url' => $visibility === 'public' ? get_permalink($post_id) : ''], $visibility);
    }

    public function capture_workspace_event(array $payload): void {
        $this->record_event('workspace.revised', 'workspace', (string) ($payload['workspace_uuid'] ?? ''), __('Research workspace revised', 'sustainable-catalyst-library'), __('An authenticated research workspace revision was saved.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'private');
    }

    public function capture_review_event(array $payload): void {
        $this->record_event('review.transitioned', 'editorial_review', (string) ($payload['review_uuid'] ?? ''), __('Editorial review transitioned', 'sustainable-catalyst-library'), __('An editorial review moved to a new workflow state.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'organization');
    }

    public function capture_media_event(array $payload): void {
        $this->record_event('media.clip.completed', 'media_clip', (string) ($payload['clip_uuid'] ?? ''), __('Media clip completed', 'sustainable-catalyst-library'), __('A rights-aware multimedia clip completed processing.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'organization');
    }

    public function capture_document_event(array $payload): void {
        $this->record_event('book.rendered', 'document_edition', (string) ($payload['edition_uuid'] ?? ($payload['job_uuid'] ?? '')), __('Document edition rendered', 'sustainable-catalyst-library'), __('A frozen server-rendered document edition was completed.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'organization');
    }

    public function capture_preservation_event(array $payload): void {
        $this->record_event('preservation.snapshot.created', 'preservation_snapshot', (string) ($payload['snapshot_uuid'] ?? ''), __('Preservation snapshot created', 'sustainable-catalyst-library'), __('A canonical record was frozen as a preserved institutional edition.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'organization');
    }

    public function capture_integrity_event(array $payload): void {
        $this->record_event('integrity.audit.completed', 'integrity_audit', (string) ($payload['audit_uuid'] ?? ''), __('Integrity audit completed', 'sustainable-catalyst-library'), __('A bounded institutional integrity audit completed.', 'sustainable-catalyst-library'), $this->safe_payload($payload), 'organization');
    }

    public function capture_graph_event(array $payload, string $token = ''): void {
        $safe = $this->safe_payload($payload);
        if ($token !== '') {
            $safe['sync_token'] = sanitize_text_field($token);
        }
        $this->record_event('graph.rebuilt', 'knowledge_graph', $token, __('Knowledge Graph rebuilt', 'sustainable-catalyst-library'), __('The generated knowledge-relationship projection completed a rebuild.', 'sustainable-catalyst-library'), $safe, 'public');
    }

    public function capture_foundation_event(int $post_id, array $payload): void {
        $this->record_event('foundation_document.extracted', 'foundation_document', (string) $post_id, get_the_title($post_id), __('Page-aware PDF text extraction completed.', 'sustainable-catalyst-library'), $this->safe_payload($payload), get_post_status($post_id) === 'publish' ? 'public' : 'organization');
    }

    private function safe_payload(array $payload): array {
        $blocked = ['api_key', 'secret', 'token', 'workspace_json', 'payload_json', 'content', 'body', 'email'];
        $safe = [];
        foreach ($payload as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || in_array($key, $blocked, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? sanitize_text_field($value) : $value;
            }
        }
        return $safe;
    }
}
