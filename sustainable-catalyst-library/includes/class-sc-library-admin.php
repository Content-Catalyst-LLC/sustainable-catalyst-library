<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Admin {
    private SC_Library_Indexer $indexer;

    public function __construct(SC_Library_Indexer $indexer) {
        $this->indexer = $indexer;
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
            'sanitize_callback' => static function ($value): string {
                return in_array($value, ['compact', 'full', 'search', 'domains', 'pathways'], true) ? $value : 'compact';
            },
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
    }

    public function activation_notice(): void {
        if (get_transient('sc_library_activation_notice')) {
            delete_transient('sc_library_activation_notice');
            echo '<div class="notice notice-success is-dismissible"><p><strong>Sustainable Catalyst Library v1.0.1 activated.</strong> Rebuild the Library index, then use <code>[sc_library mode="compact" initial_results="0" show_header="false"]</code> on the Research Library page.</p></div>';
        }

        if (get_transient('sc_library_upgrade_notice')) {
            delete_transient('sc_library_upgrade_notice');
            echo '<div class="notice notice-info is-dismissible"><p><strong>Sustainable Catalyst Library upgraded to v1.0.1.</strong> The public interface is now a compact knowledge-base navigator. Rebuild the index once to refresh excerpts and stale records.</p></div>';
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
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function render_page(): void {
        $post_types = get_post_types(['public' => true], 'objects');
        $selected = $this->indexer->configured_post_types();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Compact knowledge-base navigation, structured indexing, nested topics, contextual records, search, and public REST endpoints.', 'sustainable-catalyst-library'); ?></p>

            <?php if (isset($_GET['indexed'])) : ?>
                <div class="notice notice-success"><p>
                    <?php echo esc_html(sprintf(
                        'Index completed: %d indexed, %d failed, %d stale records removed.',
                        absint($_GET['indexed']),
                        absint($_GET['failed'] ?? 0),
                        absint($_GET['purged'] ?? 0)
                    )); ?>
                </p></div>
            <?php endif; ?>

            <div class="card" style="max-width:980px">
                <h2><?php esc_html_e('Index health', 'sustainable-catalyst-library'); ?></h2>
                <p><strong><?php esc_html_e('Indexed records:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(number_format_i18n($this->indexer->count_indexed())); ?></p>
                <p><strong><?php esc_html_e('Last full index:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(get_option('sc_library_last_full_index', '') ?: 'Not yet run'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_reindex">
                    <?php wp_nonce_field('sc_library_reindex'); ?>
                    <?php submit_button(__('Rebuild Library Index', 'sustainable-catalyst-library'), 'primary', 'submit', false); ?>
                </form>
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_default_mode"><?php esc_html_e('Default interface mode', 'sustainable-catalyst-library'); ?></label></th>
                        <td>
                            <select id="sc_library_default_mode" name="sc_library_default_mode">
                                <?php foreach (['compact' => 'Compact knowledge base', 'full' => 'Full knowledge base', 'search' => 'Search only', 'domains' => 'Topics only', 'pathways' => 'Pathways only'] as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected(get_option('sc_library_default_mode', 'compact'), $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Initial results', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_initial_results" type="checkbox" value="1" <?php checked((int) get_option('sc_library_initial_results', 0), 1); ?>> <?php esc_html_e('Show records before a visitor searches or chooses a topic. Leave off for the shortest page.', 'sustainable-catalyst-library'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_items_per_page"><?php esc_html_e('Results per page', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input id="sc_library_items_per_page" name="sc_library_items_per_page" type="number" min="1" max="30" value="<?php echo esc_attr((int) get_option('sc_library_items_per_page', 10)); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_library_result_density"><?php esc_html_e('Result density', 'sustainable-catalyst-library'); ?></label></th>
                        <td>
                            <select id="sc_library_result_density" name="sc_library_result_density">
                                <option value="compact" <?php selected(get_option('sc_library_result_density', 'compact'), 'compact'); ?>><?php esc_html_e('Compact', 'sustainable-catalyst-library'); ?></option>
                                <option value="comfortable" <?php selected(get_option('sc_library_result_density', 'compact'), 'comfortable'); ?>><?php esc_html_e('Comfortable', 'sustainable-catalyst-library'); ?></option>
                            </select>
                        </td>
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
                        <td>
                            <textarea class="large-text code" rows="7" id="sc_library_featured_pathways" name="sc_library_featured_pathways"><?php echo esc_textarea((string) get_option('sc_library_featured_pathways', '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One pathway per line: Title|URL|Short description', 'sustainable-catalyst-library'); ?></p>
                        </td>
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
                <p><code>[sc_library mode="compact" initial_results="0" show_header="false"]</code></p>
                <p><?php esc_html_e('Place this in a dedicated WordPress Shortcode block, not inside a Custom HTML block.', 'sustainable-catalyst-library'); ?></p>
                <h3><?php esc_html_e('Other modes', 'sustainable-catalyst-library'); ?></h3>
                <p><code>[sc_library mode="search"]</code></p>
                <p><code>[sc_library mode="domains"]</code></p>
                <p><code>[sc_library mode="pathways"]</code></p>
                <h3><?php esc_html_e('REST endpoints', 'sustainable-catalyst-library'); ?></h3>
                <p><code>/wp-json/sustainable-catalyst/v1/library/status</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/categories</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/items</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/items/{id}</code></p>
            </div>
        </div>
        <?php
    }
}
