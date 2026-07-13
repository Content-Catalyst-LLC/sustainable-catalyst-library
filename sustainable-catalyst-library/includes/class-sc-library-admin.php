<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Admin {
    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'menu'], 5);
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_post_sc_library_reindex', [$this, 'handle_reindex']);
        add_action('admin_notices', [$this, 'activation_notice']);
    }

    public function menu(): void {
        add_menu_page(
            __('SC Library', 'sustainable-catalyst-library'),
            __('SC Library', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library',
            [$this, 'render_page'],
            'dashicons-book-alt',
            58
        );
    }

    public function settings(): void {
        register_setting('sc_library_settings', 'sc_library_post_types', [
            'type' => 'array',
            'sanitize_callback' => function ($value): array {
                $value = is_array($value) ? $value : [];
                return $this->indexer->normalize_post_types($value);
            },
            'default' => ['post'],
        ]);
        register_setting('sc_library_settings', 'sc_library_items_per_page', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(30, max(1, absint($value))),
            'default' => 10,
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_tags', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_default_mode', [
            'type' => 'string',
            'sanitize_callback' => static fn($value) => in_array($value, ['compact', 'full', 'search', 'domains', 'pathways'], true) ? $value : 'compact',
            'default' => 'compact',
        ]);
        register_setting('sc_library_settings', 'sc_library_initial_results', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 0,
        ]);
        register_setting('sc_library_settings', 'sc_library_result_density', [
            'type' => 'string',
            'sanitize_callback' => static fn($value) => in_array($value, ['compact', 'comfortable'], true) ? $value : 'compact',
            'default' => 'compact',
        ]);
        register_setting('sc_library_settings', 'sc_library_excerpt_words', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(80, max(12, absint($value))),
            'default' => 28,
        ]);
        register_setting('sc_library_settings', 'sc_library_show_pathways', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_search_placeholder', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Search concepts, series, methods, and publications',
        ]);
        register_setting('sc_library_settings', 'sc_library_featured_pathways', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => '',
        ]);
        register_setting('sc_library_settings', 'sc_library_workbench_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/workbench/'),
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_notebook', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_notebook_storage_mode', [
            'type' => 'string',
            'sanitize_callback' => static fn($value) => in_array($value, ['local', 'account', 'hybrid'], true) ? $value : 'hybrid',
            'default' => 'hybrid',
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_persistent_workspaces', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_workspace_storage_mode', [
            'type' => 'string',
            'sanitize_callback' => static fn($value) => in_array($value, ['local', 'account', 'hybrid'], true) ? $value : 'hybrid',
            'default' => 'hybrid',
        ]);
        register_setting('sc_library_settings', 'sc_library_workspace_auto_sync', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 0,
        ]);
        register_setting('sc_library_settings', 'sc_library_workspace_max_mb', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(25, max(1, absint($value))),
            'default' => 8,
        ]);
        register_setting('sc_library_settings', 'sc_library_render_sync_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('sc_library_settings', 'sc_library_render_sync_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('sc_library_settings', 'sc_library_render_sync_timeout', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(30, max(2, absint($value))),
            'default' => 8,
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_translation_matrix', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_default_matrix_template', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key($value);
                return array_key_exists($value, SC_Library_Notebook::matrix_templates()) ? $value : 'technical_translation';
            },
            'default' => 'technical_translation',
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_boards', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_default_board_type', [
            'type' => 'string',
            'sanitize_callback' => static fn($value) => in_array($value, ['whiteboard', 'chalkboard'], true) ? $value : 'whiteboard',
            'default' => 'whiteboard',
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_annotations', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        register_setting('sc_library_settings', 'sc_library_default_annotation_page_style', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key($value);
                return array_key_exists($value, SC_Library_Annotations::page_styles()) ? $value : 'reader';
            },
            'default' => 'reader',
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_books', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        register_setting('sc_library_settings', 'sc_library_default_book_theme', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key($value);
                return array_key_exists($value, SC_Library_Books::themes()) ? $value : 'institutional';
            },
            'default' => 'institutional',
        ]);
        register_setting('sc_library_settings', 'sc_library_default_book_page_size', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key($value);
                return array_key_exists($value, SC_Library_Books::page_sizes()) ? $value : 'letter';
            },
            'default' => 'letter',
        ]);
        register_setting('sc_library_settings', 'sc_library_enable_server_documents', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        register_setting('sc_library_settings', 'sc_library_document_service_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '']);
        register_setting('sc_library_settings', 'sc_library_document_service_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
        register_setting('sc_library_settings', 'sc_library_document_timeout', ['type' => 'integer', 'sanitize_callback' => static fn($value) => min(120, max(5, absint($value))), 'default' => 30]);
        register_setting('sc_library_settings', 'sc_library_document_auto_import', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        register_setting('sc_library_settings', 'sc_library_document_max_request_mb', ['type' => 'integer', 'sanitize_callback' => static fn($value) => min(25, max(1, absint($value))), 'default' => 8]);
        register_setting('sc_library_settings', 'sc_library_document_max_pdf_mb', ['type' => 'integer', 'sanitize_callback' => static fn($value) => min(50, max(1, absint($value))), 'default' => 20]);
        register_setting('sc_library_settings', 'sc_library_document_max_attempts', ['type' => 'integer', 'sanitize_callback' => static fn($value) => min(10, max(1, absint($value))), 'default' => 3]);
        register_setting('sc_library_settings', 'sc_library_document_retention_days', ['type' => 'integer', 'sanitize_callback' => static fn($value) => min(365, max(1, absint($value))), 'default' => 30]);
        register_setting('sc_library_settings', 'sc_library_enable_documentation', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        register_setting('sc_library_settings', 'sc_library_enable_planner', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_main_page_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => home_url('/research-library/')]);
        register_setting('sc_library_settings', 'sc_library_documentation_search_placeholder', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Search titles, descriptions, keywords, and document text']);
        register_setting('sc_library_settings', 'sc_library_documentation_include_archived', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 0]);
        register_setting('sc_library_settings', 'sc_library_enable_integrations', ['type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1]);
        foreach (['workbench_health_url', 'decision_studio_url', 'decision_studio_health_url', 'site_intelligence_url', 'site_intelligence_health_url'] as $option) {
            register_setting('sc_library_settings', 'sc_library_' . $option, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '']);
        }
    }

    public function activation_notice(): void {
        if (get_transient('sc_library_activation_notice')) {
            delete_transient('sc_library_activation_notice');
            echo '<div class="notice notice-success is-dismissible"><p><strong>Sustainable Catalyst Library v1.15.0 activated.</strong> Open SC Library → Editorial Workflow to configure collaboration and create a private test review. Existing index, multimedia, workspace, and document systems remain available.</p></div>';
        }
        if (get_transient('sc_library_upgrade_notice')) {
            delete_transient('sc_library_upgrade_notice');
            echo '<div class="notice notice-info is-dismissible"><p><strong>Sustainable Catalyst Library upgraded to v1.15.0.</strong> Collaboration, review comments, suggested edits, approval states, record locks, and attributed activity are now available under SC Library → Editorial Workflow. No index rebuild is required.</p></div>';
        }
    }

    public function handle_reindex(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_reindex');
        $result = $this->indexer->rebuild_all();
        $url = add_query_arg([
            'page' => 'sc-library',
            'indexed' => $result['indexed'],
            'skipped' => $result['skipped'] ?? 0,
            'failed' => $result['failed'],
            'purged' => $result['purged'],
            'relationships_purged' => $result['relationships_purged'] ?? 0,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function render_page(): void {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected = $this->indexer->configured_post_types();
        $taxonomy_post_type = $selected[0] ?? 'post';
        $documentation_diagnostics = class_exists('SC_Library_Documentation') ? SC_Library_Documentation::admin_diagnostics() : ['count' => 0, 'items' => []];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('A relationship-aware knowledge base for publications, sources, notebooks, visual research, connected tools, annotations, custom books, and living institutional documentation.', 'sustainable-catalyst-library'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=sc-library-index-tools')); ?>"><?php esc_html_e('Open Index Scanner', 'sustainable-catalyst-library'); ?></a></p>

            <?php if (isset($_GET['indexed'])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html(sprintf(
                    'Index completed: %d indexed, %d skipped, %d failed, %d stale records removed, %d stale relationships removed.',
                    absint($_GET['indexed']),
                    absint($_GET['skipped'] ?? 0),
                    absint($_GET['failed'] ?? 0),
                    absint($_GET['purged'] ?? 0),
                    absint($_GET['relationships_purged'] ?? 0)
                )); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;max-width:980px;margin:20px 0">
                <?php $this->metric_card(__('Published Posts in database', 'sustainable-catalyst-library'), number_format_i18n((int) ($this->indexer->database_published_post_type_counts(true)['post'] ?? 0))); ?>
                <?php $this->metric_card(__('Published editorial records', 'sustainable-catalyst-library'), number_format_i18n($this->indexer->global_published_count(false))); ?>
                <?php $this->metric_card(__('Indexed records', 'sustainable-catalyst-library'), number_format_i18n($this->indexer->count_indexed())); ?>
                <?php $this->metric_card(__('Typed relationships', 'sustainable-catalyst-library'), number_format_i18n($this->relationships->count())); ?>
                <?php $this->metric_card(__('Library Series', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::SERIES))); ?>
                <?php $this->metric_card(__('Library Concepts', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::CONCEPT))); ?>
                <?php $this->metric_card(__('Library Collections', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::COLLECTION))); ?>
                <?php $this->metric_card(__('Documentation Categories', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::DOCUMENT_CATEGORY))); ?>
                <?php $this->metric_card(__('Foundations records', 'sustainable-catalyst-library'), number_format_i18n(class_exists('SC_Library_Documentation') ? SC_Library_Documentation::foundation_count() : 0)); ?>
                <?php $this->metric_card(__('Portable export schema', 'sustainable-catalyst-library'), '1.3'); ?>
            </div>

            <div class="card" style="max-width:980px">
                <h2><?php esc_html_e('Index and relationship health', 'sustainable-catalyst-library'); ?></h2>
                <p><strong><?php esc_html_e('Last full index:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(get_option('sc_library_last_full_index', '') ?: 'Not yet run'); ?></p>
                <p>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['taxonomy' => SC_Library_Taxonomies::SERIES, 'post_type' => $taxonomy_post_type], admin_url('edit-tags.php'))); ?>"><?php esc_html_e('Manage Library Series', 'sustainable-catalyst-library'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['taxonomy' => SC_Library_Taxonomies::CONCEPT, 'post_type' => $taxonomy_post_type], admin_url('edit-tags.php'))); ?>"><?php esc_html_e('Manage Library Concepts', 'sustainable-catalyst-library'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['taxonomy' => SC_Library_Taxonomies::COLLECTION, 'post_type' => $taxonomy_post_type], admin_url('edit-tags.php'))); ?>"><?php esc_html_e('Manage Library Collections', 'sustainable-catalyst-library'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['taxonomy' => SC_Library_Taxonomies::DOCUMENT_CATEGORY, 'post_type' => $taxonomy_post_type], admin_url('edit-tags.php'))); ?>"><?php esc_html_e('Manage Documentation Categories', 'sustainable-catalyst-library'); ?></a>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_reindex">
                    <?php wp_nonce_field('sc_library_reindex'); ?>
                    <?php submit_button(__('Rebuild Library Index', 'sustainable-catalyst-library'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <div class="card" style="max-width:980px">
                <h2><?php esc_html_e('Documentation authority diagnostics', 'sustainable-catalyst-library'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%d documentation issue requires review.', '%d documentation issues require review.', (int) $documentation_diagnostics['count'], 'sustainable-catalyst-library'), (int) $documentation_diagnostics['count'])); ?></p>
                <?php if (!empty($documentation_diagnostics['items'])) : ?>
                    <table class="widefat striped"><thead><tr><th><?php esc_html_e('Record', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Issue', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Action', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                    <?php foreach ($documentation_diagnostics['items'] as $issue) : ?>
                        <tr><td><?php echo esc_html($issue['title']); ?></td><td><?php echo esc_html($issue['message']); ?></td><td><?php if ($issue['edit_url']) : ?><a href="<?php echo esc_url($issue['edit_url']); ?>"><?php esc_html_e('Edit record', 'sustainable-catalyst-library'); ?></a><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php else : ?>
                    <p><strong><?php esc_html_e('No documentation authority issues detected.', 'sustainable-catalyst-library'); ?></strong></p>
                <?php endif; ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('sc_library_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Indexed post types', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <?php foreach ($post_types as $type) : ?>
                                <label style="display:block;margin:0 0 8px">
                                    <input type="checkbox" name="sc_library_post_types[]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $selected, true)); ?>>
                                    <?php echo esc_html($type->labels->singular_name . ' (' . $type->name . ')'); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Save changes, then rebuild the index. Library Series and Concepts are registered for these post types.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Foundations Documentation Library', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_documentation" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_documentation', 1), 1); ?>> <?php esc_html_e('Enable the curated institutional documentation interface and authority controls.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_main_page_url"><?php esc_html_e('Main Library page URL:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text" id="sc_library_main_page_url" name="sc_library_main_page_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_main_page_url', home_url('/research-library/'))); ?>"></p>
                            <p><label for="sc_library_documentation_search_placeholder"><?php esc_html_e('Documentation search placeholder:', 'sustainable-catalyst-library'); ?></label> <input class="large-text" id="sc_library_documentation_search_placeholder" name="sc_library_documentation_search_placeholder" type="text" value="<?php echo esc_attr((string) get_option('sc_library_documentation_search_placeholder', 'Search titles, descriptions, keywords, and document text')); ?>"></p>
                            <label><input name="sc_library_documentation_include_archived" type="checkbox" value="1" <?php checked((int) get_option('sc_library_documentation_include_archived', 0), 1); ?>> <?php esc_html_e('Show superseded and archived records by default.', 'sustainable-catalyst-library'); ?></label>
                            <p class="description"><?php esc_html_e('Assign records to the Foundations Documentation Library collection and a Documentation Category. Each record should identify the current authoritative webpage, repository, methodology page, release record, PDF, or archive.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_workbench_url"><?php esc_html_e('Workbench URL', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input class="regular-text" id="sc_library_workbench_url" name="sc_library_workbench_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_workbench_url', home_url('/workbench/'))); ?>"><p class="description"><?php esc_html_e('Record panels pass the Library record ID and stable identifier to this URL.', 'sustainable-catalyst-library'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Connected applications', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_integrations" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_integrations', 1), 1); ?>> <?php esc_html_e('Enable Library-specific Workbench, Decision Studio, and Site Intelligence handoffs.', 'sustainable-catalyst-library'); ?></label>
                            <p class="description"><?php esc_html_e('The Library sends structured context packets and opens the full application only when deeper work is requested. No application is embedded as an iframe.', 'sustainable-catalyst-library'); ?></p>
                            <table class="widefat striped" style="margin-top:12px;max-width:900px"><tbody>
                                <tr><td><strong><?php esc_html_e('Workbench health URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_workbench_health_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_workbench_health_url', '')); ?>" placeholder="https://…/health"></td></tr>
                                <tr><td><strong><?php esc_html_e('Decision Studio URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_decision_studio_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_decision_studio_url', home_url('/decision-studio/'))); ?>"></td></tr>
                                <tr><td><strong><?php esc_html_e('Decision Studio health URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_decision_studio_health_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_decision_studio_health_url', '')); ?>" placeholder="https://…/health"></td></tr>
                                <tr><td><strong><?php esc_html_e('Site Intelligence URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_site_intelligence_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_site_intelligence_url', home_url('/site-intelligence/'))); ?>"></td></tr>
                                <tr><td><strong><?php esc_html_e('Site Intelligence health URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_site_intelligence_health_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_site_intelligence_health_url', '')); ?>" placeholder="https://…/health"></td></tr>
                            </tbody></table>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Research Notebook', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_enable_notebook" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_notebook', 1), 1); ?>> <?php esc_html_e('Enable saved collections, personal notes, external sources, citations, matrices, visual boards, and portable exports.', 'sustainable-catalyst-library'); ?></label><p class="description"><?php esc_html_e('The browser remains a valid local workspace. Signed-in users can optionally migrate or synchronize explicit revisions to their WordPress account.', 'sustainable-catalyst-library'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Persistent workspaces and Render synchronization', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_persistent_workspaces" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_persistent_workspaces', 1), 1); ?>> <?php esc_html_e('Enable account-owned workspaces, revisions, sharing, conflict detection, and optional Render synchronization.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_workspace_storage_mode"><?php esc_html_e('Default storage mode:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_workspace_storage_mode" name="sc_library_workspace_storage_mode"><option value="local" <?php selected(get_option('sc_library_workspace_storage_mode', 'hybrid'), 'local'); ?>><?php esc_html_e('Local browser only', 'sustainable-catalyst-library'); ?></option><option value="account" <?php selected(get_option('sc_library_workspace_storage_mode', 'hybrid'), 'account'); ?>><?php esc_html_e('Account workspace', 'sustainable-catalyst-library'); ?></option><option value="hybrid" <?php selected(get_option('sc_library_workspace_storage_mode', 'hybrid'), 'hybrid'); ?>><?php esc_html_e('Hybrid local + account revision', 'sustainable-catalyst-library'); ?></option></select></p>
                            <p><label><input name="sc_library_workspace_auto_sync" type="checkbox" value="1" <?php checked((int) get_option('sc_library_workspace_auto_sync', 0), 1); ?>> <?php esc_html_e('Automatically save linked browser workspaces after local changes and queue Render synchronization.', 'sustainable-catalyst-library'); ?></label></p>
                            <p><label for="sc_library_workspace_max_mb"><?php esc_html_e('Maximum account workspace size:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_workspace_max_mb" name="sc_library_workspace_max_mb" type="number" min="1" max="25" value="<?php echo esc_attr((int) get_option('sc_library_workspace_max_mb', 8)); ?>"> MB</p>
                            <table class="widefat striped" style="margin-top:12px;max-width:900px"><tbody>
                                <tr><td><strong><?php esc_html_e('Render workspace service URL', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text" name="sc_library_render_sync_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_render_sync_url', '')); ?>" placeholder="https://your-library-workspace-service.onrender.com/"></td></tr>
                                <tr><td><strong><?php esc_html_e('Server-to-server API key', 'sustainable-catalyst-library'); ?></strong></td><td><input class="large-text code" name="sc_library_render_sync_api_key" type="password" autocomplete="new-password" value="<?php echo esc_attr((string) get_option('sc_library_render_sync_api_key', '')); ?>"><p class="description"><?php esc_html_e('The key stays in WordPress and is never sent to the browser.', 'sustainable-catalyst-library'); ?></p></td></tr>
                                <tr><td><strong><?php esc_html_e('Request timeout', 'sustainable-catalyst-library'); ?></strong></td><td><input name="sc_library_render_sync_timeout" type="number" min="2" max="30" value="<?php echo esc_attr((int) get_option('sc_library_render_sync_timeout', 8)); ?>"> seconds</td></tr>
                            </tbody></table>
                            <p class="description"><?php esc_html_e('Render synchronization is optional. WordPress remains the authority for account identity, permissions, and the primary workspace revision.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Technical Translation Matrix', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_translation_matrix" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_translation_matrix', 1), 1); ?>> <?php esc_html_e('Enable configurable matrices, cell validation states, source references, and export tools.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_default_matrix_template"><?php esc_html_e('Default template:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_default_matrix_template" name="sc_library_default_matrix_template">
                                <?php foreach (SC_Library_Notebook::matrix_templates() as $template_id => $template) : ?>
                                    <option value="<?php echo esc_attr($template_id); ?>" <?php selected(get_option('sc_library_default_matrix_template', 'technical_translation'), $template_id); ?>><?php echo esc_html($template['label']); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                            <p class="description"><?php esc_html_e('Matrices are reusable Notebook records and can be exported as JSON, CSV, or landscape print/PDF-ready documents.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Whiteboards and Chalkboards', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_boards" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_boards', 1), 1); ?>> <?php esc_html_e('Enable visual research boards, draggable cards, typed connectors, stylus or mouse handwriting, and export tools.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_default_board_type"><?php esc_html_e('Default board:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_default_board_type" name="sc_library_default_board_type"><option value="whiteboard" <?php selected(get_option('sc_library_default_board_type', 'whiteboard'), 'whiteboard'); ?>><?php esc_html_e('Whiteboard', 'sustainable-catalyst-library'); ?></option><option value="chalkboard" <?php selected(get_option('sc_library_default_board_type', 'whiteboard'), 'chalkboard'); ?>><?php esc_html_e('Chalkboard', 'sustainable-catalyst-library'); ?></option></select></p>
                            <p class="description"><?php esc_html_e('Boards remain editable in browser storage and export as JSON, SVG, PNG, and print/PDF-ready visual research artifacts.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Annotation Studio and handwriting', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_annotations" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_annotations', 1), 1); ?>> <?php esc_html_e('Enable source-aware handwritten annotations, highlights, shapes, anchored notes, layers, transcription, and exports.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_default_annotation_page_style"><?php esc_html_e('Default page style:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_default_annotation_page_style" name="sc_library_default_annotation_page_style">
                                <?php foreach (SC_Library_Annotations::page_styles() as $style_id => $style) : ?>
                                    <option value="<?php echo esc_attr($style_id); ?>" <?php selected(get_option('sc_library_default_annotation_page_style', 'reader'), $style_id); ?>><?php echo esc_html($style['label']); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                            <p class="description"><?php esc_html_e('Annotations remain separate from canonical publications and export as JSON, SVG, PNG, and print/PDF-ready pages.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Custom Book Builder and PDF generation', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_books" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_books', 1), 1); ?>> <?php esc_html_e('Enable saved book projects, chapter assembly, Notebook artifacts, article normalization, multimedia fallbacks, manifests, and print/PDF editions.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_default_book_theme"><?php esc_html_e('Default book theme:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_default_book_theme" name="sc_library_default_book_theme">
                                <?php foreach (SC_Library_Books::themes() as $theme_id => $theme) : ?>
                                    <option value="<?php echo esc_attr($theme_id); ?>" <?php selected(get_option('sc_library_default_book_theme', 'institutional'), $theme_id); ?>><?php echo esc_html($theme['label']); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                            <p><label for="sc_library_default_book_page_size"><?php esc_html_e('Default page size:', 'sustainable-catalyst-library'); ?></label> <select id="sc_library_default_book_page_size" name="sc_library_default_book_page_size">
                                <?php foreach (SC_Library_Books::page_sizes() as $size_id => $size) : ?>
                                    <option value="<?php echo esc_attr($size_id); ?>" <?php selected(get_option('sc_library_default_book_page_size', 'letter'), $size_id); ?>><?php echo esc_html($size['label']); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                            <p class="description"><?php esc_html_e('Browser Print / Save as PDF remains available. v1.13 can also send normalized book packets to the optional Render document service for stable server pagination and frozen editions.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Server-side document production', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_server_documents" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_server_documents', 1), 1); ?>> <?php esc_html_e('Enable queued Render PDF jobs, frozen edition manifests, checksums, retries, diagnostics, and Media Library import.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_document_service_url"><?php esc_html_e('Document service URL:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text code" id="sc_library_document_service_url" name="sc_library_document_service_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_document_service_url', '')); ?>" placeholder="https://your-library-service.onrender.com"></p>
                            <p><label for="sc_library_document_service_api_key"><?php esc_html_e('Document service API key:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text code" id="sc_library_document_service_api_key" name="sc_library_document_service_api_key" type="password" value="<?php echo esc_attr((string) get_option('sc_library_document_service_api_key', '')); ?>" autocomplete="new-password"></p>
                            <p class="description"><?php esc_html_e('Leave these two fields empty to reuse the v1.12 Render workspace service URL and API key.', 'sustainable-catalyst-library'); ?></p>
                            <p><label><input name="sc_library_document_auto_import" type="checkbox" value="1" <?php checked((int) get_option('sc_library_document_auto_import', 1), 1); ?>> <?php esc_html_e('Automatically import completed PDFs into the WordPress Media Library.', 'sustainable-catalyst-library'); ?></label></p>
                            <p><label for="sc_library_document_timeout"><?php esc_html_e('Request timeout:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_document_timeout" name="sc_library_document_timeout" type="number" min="5" max="120" value="<?php echo esc_attr((int) get_option('sc_library_document_timeout', 30)); ?>"> seconds</p>
                            <p><label for="sc_library_document_max_request_mb"><?php esc_html_e('Maximum request:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_document_max_request_mb" name="sc_library_document_max_request_mb" type="number" min="1" max="25" value="<?php echo esc_attr((int) get_option('sc_library_document_max_request_mb', 8)); ?>"> MB &nbsp; <label for="sc_library_document_max_pdf_mb"><?php esc_html_e('Maximum imported PDF:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_document_max_pdf_mb" name="sc_library_document_max_pdf_mb" type="number" min="1" max="50" value="<?php echo esc_attr((int) get_option('sc_library_document_max_pdf_mb', 20)); ?>"> MB</p>
                            <p><label for="sc_library_document_max_attempts"><?php esc_html_e('Maximum render attempts:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_document_max_attempts" name="sc_library_document_max_attempts" type="number" min="1" max="10" value="<?php echo esc_attr((int) get_option('sc_library_document_max_attempts', 3)); ?>"> &nbsp; <label for="sc_library_document_retention_days"><?php esc_html_e('Remote retention:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_document_retention_days" name="sc_library_document_retention_days" type="number" min="1" max="365" value="<?php echo esc_attr((int) get_option('sc_library_document_retention_days', 30)); ?>"> days</p>
                            <p><a href="<?php echo esc_url(admin_url('admin.php?page=sc-library-document-production')); ?>"><?php esc_html_e('Open Document Production dashboard →', 'sustainable-catalyst-library'); ?></a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Multimedia Studio and video snippets', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_multimedia" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_multimedia', 1), 1); ?>> <?php esc_html_e('Enable authorized media records, transcripts, captions, non-destructive clips, annotations, evidence reels, and optional server processing.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_media_service_url"><?php esc_html_e('Media service URL:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text code" id="sc_library_media_service_url" name="sc_library_media_service_url" type="url" value="<?php echo esc_attr((string) get_option('sc_library_media_service_url', '')); ?>" placeholder="https://your-library-service.onrender.com"></p>
                            <p><label for="sc_library_media_service_api_key"><?php esc_html_e('Media service API key:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text code" id="sc_library_media_service_api_key" name="sc_library_media_service_api_key" type="password" value="<?php echo esc_attr((string) get_option('sc_library_media_service_api_key', '')); ?>" autocomplete="new-password"></p>
                            <p class="description"><?php esc_html_e('Leave these fields empty to reuse the document/workspace Render service URL and key. WordPress remains the authority for assets, rights, clips, and reels.', 'sustainable-catalyst-library'); ?></p>
                            <p><label><input name="sc_library_media_allow_remote_urls" type="checkbox" value="1" <?php checked((int) get_option('sc_library_media_allow_remote_urls', 0), 1); ?>> <?php esc_html_e('Allow administrators to register public HTTPS media URLs outside the WordPress Media Library.', 'sustainable-catalyst-library'); ?></label></p>
                            <p><label><input name="sc_library_media_auto_import" type="checkbox" value="1" <?php checked((int) get_option('sc_library_media_auto_import', 1), 1); ?>> <?php esc_html_e('Automatically import completed clips and poster frames into the WordPress Media Library.', 'sustainable-catalyst-library'); ?></label></p>
                            <p><label for="sc_library_media_max_source_mb"><?php esc_html_e('Maximum source size:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_media_max_source_mb" name="sc_library_media_max_source_mb" type="number" min="10" max="2048" value="<?php echo esc_attr((int) get_option('sc_library_media_max_source_mb', 500)); ?>"> MB &nbsp; <label for="sc_library_media_max_clip_minutes"><?php esc_html_e('Maximum clip:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_media_max_clip_minutes" name="sc_library_media_max_clip_minutes" type="number" min="1" max="240" value="<?php echo esc_attr((int) get_option('sc_library_media_max_clip_minutes', 30)); ?>"> minutes</p>
                            <p><label for="sc_library_media_retention_days"><?php esc_html_e('Remote output retention:', 'sustainable-catalyst-library'); ?></label> <input id="sc_library_media_retention_days" name="sc_library_media_retention_days" type="number" min="1" max="365" value="<?php echo esc_attr((int) get_option('sc_library_media_retention_days', 14)); ?>"> days</p>
                            <p><a href="<?php echo esc_url(admin_url('admin.php?page=sc-library-multimedia')); ?>"><?php esc_html_e('Open Multimedia Studio →', 'sustainable-catalyst-library'); ?></a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Content Planner and public registry', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_enable_planner" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_planner', 1), 1); ?>> <?php esc_html_e('Enable planned-content records, Article Map Planner, complete public registry, roadmap tracker, release expectations, and registry exports.', 'sustainable-catalyst-library'); ?></label><p class="description"><?php esc_html_e('Only published planning records explicitly marked public appear in public Library and registry results.', 'sustainable-catalyst-library'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('PostgreSQL and portable data', 'sustainable-catalyst-library'); ?></th>
                        <td>
                            <label><input name="sc_library_enable_portability" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_portability', 1), 1); ?>> <?php esc_html_e('Enable normalized PostgreSQL SQL, CSV, JSONL, JSON, manifest, and browser-workspace export tools.', 'sustainable-catalyst-library'); ?></label>
                            <p><label for="sc_library_export_schema_name"><?php esc_html_e('Default PostgreSQL schema:', 'sustainable-catalyst-library'); ?></label> <input class="regular-text code" id="sc_library_export_schema_name" name="sc_library_export_schema_name" type="text" value="<?php echo esc_attr((string) get_option('sc_library_export_schema_name', 'sustainable_catalyst_library')); ?>"></p>
                            <p class="description"><?php esc_html_e('Server exports are available under SC Library → Portable Data Export. Private Notebook data remains browser-local and exports from the Notebook Import / Export tab.', 'sustainable-catalyst-library'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_default_mode"><?php esc_html_e('Default interface mode', 'sustainable-catalyst-library'); ?></label></th>
                        <td><select id="sc_library_default_mode" name="sc_library_default_mode">
                            <?php foreach (['compact' => 'Compact knowledge base', 'full' => 'Full knowledge base', 'search' => 'Search only', 'domains' => 'Topics only', 'pathways' => 'Pathways only'] as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected(get_option('sc_library_default_mode', 'compact'), $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Initial results', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_initial_results" type="checkbox" value="1" <?php checked((int) get_option('sc_library_initial_results', 0), 1); ?>> <?php esc_html_e('Show records before a visitor searches or selects a topic.', 'sustainable-catalyst-library'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_items_per_page"><?php esc_html_e('Results per page', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input id="sc_library_items_per_page" name="sc_library_items_per_page" type="number" min="1" max="30" value="<?php echo esc_attr((int) get_option('sc_library_items_per_page', 10)); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_result_density"><?php esc_html_e('Result density', 'sustainable-catalyst-library'); ?></label></th>
                        <td><select id="sc_library_result_density" name="sc_library_result_density">
                            <option value="compact" <?php selected(get_option('sc_library_result_density', 'compact'), 'compact'); ?>><?php esc_html_e('Compact', 'sustainable-catalyst-library'); ?></option>
                            <option value="comfortable" <?php selected(get_option('sc_library_result_density', 'compact'), 'comfortable'); ?>><?php esc_html_e('Comfortable', 'sustainable-catalyst-library'); ?></option>
                        </select></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_excerpt_words"><?php esc_html_e('Excerpt length', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input id="sc_library_excerpt_words" name="sc_library_excerpt_words" type="number" min="12" max="80" value="<?php echo esc_attr((int) get_option('sc_library_excerpt_words', 28)); ?>"> <?php esc_html_e('words for generated excerpts', 'sustainable-catalyst-library'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_search_placeholder"><?php esc_html_e('Search placeholder', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input class="regular-text" id="sc_library_search_placeholder" name="sc_library_search_placeholder" type="text" value="<?php echo esc_attr((string) get_option('sc_library_search_placeholder', 'Search concepts, series, methods, and publications')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Featured pathways', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_show_pathways" type="checkbox" value="1" <?php checked((int) get_option('sc_library_show_pathways', 1), 1); ?>> <?php esc_html_e('Show compact curated entry points.', 'sustainable-catalyst-library'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_featured_pathways"><?php esc_html_e('Pathway definitions', 'sustainable-catalyst-library'); ?></label></th>
                        <td><textarea class="large-text code" rows="7" id="sc_library_featured_pathways" name="sc_library_featured_pathways"><?php echo esc_textarea((string) get_option('sc_library_featured_pathways', '')); ?></textarea><p class="description"><?php esc_html_e('One pathway per line: Title|URL|Short description', 'sustainable-catalyst-library'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tag filters', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_enable_tags" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_tags', 1), 1); ?>> <?php esc_html_e('Keep tag filter support available through the REST API.', 'sustainable-catalyst-library'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div class="card" style="max-width:980px">
                <h2><?php esc_html_e('Recommended Research Library shortcode', 'sustainable-catalyst-library'); ?></h2>
                <p><code>[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="true"]</code></p>
                <p><code>[sc_library collection="foundations" mode="documentation"]</code> — curated Foundations Documentation Library.</p>
                <p><code>[sc_library_registry mode="public"]</code> — complete public registry of published, documented, planned, and historical records.</p>
                <p><code>[sc_library_planner_tracker mode="public"]</code> — public roadmap totals and area/product tracker.</p>
                <p><code>[sc_library mode="registry"]</code> — registry through the main Library shortcode.</p>
                <p><code>[sc_foundations_library mode="public"]</code> — convenience alias for the same documentation collection.</p>
                <p><code>[sc_library_notebook]</code> — standalone Research Notebook workspace.</p>
                <p><code>[sc_library_translation_matrix]</code> — standalone Technical Translation Matrix studio.</p>
                <p><code>[sc_library_whiteboard]</code> — standalone Whiteboard launcher.</p>
                <p><code>[sc_library_chalkboard]</code> — standalone Chalkboard launcher.</p>
                <p><code>[sc_library_boards]</code> — combined visual board launcher.</p>
                <p><code>[sc_library_integrations]</code> — standalone connected research-tool studio.</p>
                <p><code>[sc_library_annotation_studio]</code> — standalone annotation and handwriting studio.</p>
                <p><code>[sc_library_multimedia_studio]</code> — multimedia assets, clips, transcripts, captions, annotations, and evidence reels.</p>
                <p><code>[sc_library_evidence_reel id="UUID"]</code> — public evidence reel.</p>
                <p><code>[sc_library_book_builder]</code> — standalone Custom Book Builder.</p>
                <p><code>[sc_library_portability]</code> — standalone PostgreSQL and portable browser-workspace export studio.</p>
                <p><code>[sc_library_account_workspaces]</code> — standalone account workspace and synchronization manager.</p>
                <p><code>[sc_library_notebook tab="sync"]</code> — open the Notebook directly to account and Render synchronization.</p>
                <p><code>[sc_library_notebook tab="books"]</code> — open the Notebook directly to saved books.</p>
                <p><code>[sc_library_notebook tab="annotations"]</code> — open the Notebook directly to annotations.</p>
                <p><?php esc_html_e('Place this in a dedicated WordPress Shortcode block.', 'sustainable-catalyst-library'); ?></p>
                <h3><?php esc_html_e('Relationship-aware REST endpoints', 'sustainable-catalyst-library'); ?></h3>
                <?php foreach (['status', 'categories', 'series', 'concepts', 'pathways', 'items', 'items/{id}', 'items/{id}/related', 'source-types', 'citation-formats', 'source-template', 'matrix-templates', 'board-templates', 'integrations', 'integrations/status', 'integration-schema', 'items/{id}/handoff?target=workbench', 'annotation-schema', 'book-schema', 'items/{id}/book', 'documentation', 'documentation/categories', 'documentation/statuses', 'documentation/{id}', 'collections/foundations', 'registry', 'registry/facets', 'roadmap/tracker', 'planner/statuses', 'plans/{id}', 'export/formats', 'export/postgresql-schema', 'export/manifest', 'account/status', 'workspaces', 'workspaces/{uuid}', 'workspaces/{uuid}/history', 'workspaces/{uuid}/share', 'workspaces/{uuid}/sync', 'workspaces/render/status', 'scanner/status', 'scanner/start', 'scanner/step', 'scanner/pause', 'scanner/resume', 'scanner/cancel', 'scanner/repair', 'scanner/record'] as $endpoint) : ?>
                    <p><code>/wp-json/sustainable-catalyst/v1/library/<?php echo esc_html($endpoint); ?></code></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function metric_card(string $label, string $value): void {
        echo '<div class="card" style="margin:0"><p style="margin:0 0 6px;color:#646970">' . esc_html($label) . '</p><strong style="font-size:24px">' . esc_html($value) . '</strong></div>';
    }

    private function term_count(string $taxonomy): int {
        $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        return is_wp_error($count) ? 0 : (int) $count;
    }
}
