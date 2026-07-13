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
        add_action('admin_menu', [$this, 'menu']);
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
            'sanitize_callback' => static function ($value): array {
                $allowed = get_post_types(['public' => true], 'names');
                $value = is_array($value) ? $value : [];
                return array_values(array_intersect(array_map('sanitize_key', $value), $allowed));
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
            'sanitize_callback' => static fn($value) => 'local',
            'default' => 'local',
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
            echo '<div class="notice notice-success is-dismissible"><p><strong>Sustainable Catalyst Library v1.11.0 activated.</strong> Rebuild the Library index, then open SC Library → Planning Analytics and Release Coordination to review workload, dependencies, capacity, and release risk.</p></div>';
        }
        if (get_transient('sc_library_upgrade_notice')) {
            delete_transient('sc_library_upgrade_notice');
            echo '<div class="notice notice-info is-dismissible"><p><strong>Sustainable Catalyst Library upgraded to v1.11.0.</strong> The release adds planning analytics, dependency intelligence, release groups, milestones, effort and progress tracking, capacity warnings, planned-versus-actual timing, printable reports, and expanded portable planning data. Rebuild the index once, then review Planning Analytics and Release Coordination.</p></div>';
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

            <?php if (isset($_GET['indexed'])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html(sprintf(
                    'Index completed: %d indexed, %d failed, %d stale records removed, %d stale relationships removed.',
                    absint($_GET['indexed']),
                    absint($_GET['failed'] ?? 0),
                    absint($_GET['purged'] ?? 0),
                    absint($_GET['relationships_purged'] ?? 0)
                )); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;max-width:980px;margin:20px 0">
                <?php $this->metric_card(__('Indexed records', 'sustainable-catalyst-library'), number_format_i18n($this->indexer->count_indexed())); ?>
                <?php $this->metric_card(__('Typed relationships', 'sustainable-catalyst-library'), number_format_i18n($this->relationships->count())); ?>
                <?php $this->metric_card(__('Library Series', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::SERIES))); ?>
                <?php $this->metric_card(__('Library Concepts', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::CONCEPT))); ?>
                <?php $this->metric_card(__('Library Collections', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::COLLECTION))); ?>
                <?php $this->metric_card(__('Documentation Categories', 'sustainable-catalyst-library'), number_format_i18n($this->term_count(SC_Library_Taxonomies::DOCUMENT_CATEGORY))); ?>
                <?php $this->metric_card(__('Foundations records', 'sustainable-catalyst-library'), number_format_i18n(class_exists('SC_Library_Documentation') ? SC_Library_Documentation::foundation_count() : 0)); ?>
                <?php $this->metric_card(__('Portable export schema', 'sustainable-catalyst-library'), '1.0'); ?>
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
                        <td><label><input name="sc_library_enable_notebook" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_notebook', 1), 1); ?>> <?php esc_html_e('Enable local saved collections, personal notes, external sources, citations, matrices, visual boards, and portable exports.', 'sustainable-catalyst-library'); ?></label><p class="description"><?php esc_html_e('v1.10 stores personal workspace data in each visitor’s browser. It does not write private research into WordPress or expose it through public REST endpoints.', 'sustainable-catalyst-library'); ?></p></td>
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
                            <p class="description"><?php esc_html_e('Book projects remain editable in browser storage. v1.10 generates a browser preview and uses Print/Save as PDF; server-rendered archival PDF packages can be added in a later Render-backed release.', 'sustainable-catalyst-library'); ?></p>
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
                <p><code>[sc_library_book_builder]</code> — standalone Custom Book Builder.</p>
                <p><code>[sc_library_portability]</code> — standalone PostgreSQL and portable browser-workspace export studio.</p>
                <p><code>[sc_library_notebook tab="books"]</code> — open the Notebook directly to saved books.</p>
                <p><code>[sc_library_notebook tab="annotations"]</code> — open the Notebook directly to annotations.</p>
                <p><?php esc_html_e('Place this in a dedicated WordPress Shortcode block.', 'sustainable-catalyst-library'); ?></p>
                <h3><?php esc_html_e('Relationship-aware REST endpoints', 'sustainable-catalyst-library'); ?></h3>
                <?php foreach (['status', 'categories', 'series', 'concepts', 'pathways', 'items', 'items/{id}', 'items/{id}/related', 'source-types', 'citation-formats', 'source-template', 'matrix-templates', 'board-templates', 'integrations', 'integrations/status', 'integration-schema', 'items/{id}/handoff?target=workbench', 'annotation-schema', 'book-schema', 'items/{id}/book', 'documentation', 'documentation/categories', 'documentation/statuses', 'documentation/{id}', 'collections/foundations', 'registry', 'registry/facets', 'roadmap/tracker', 'planner/statuses', 'plans/{id}', 'export/formats', 'export/postgresql-schema', 'export/manifest'] as $endpoint) : ?>
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
