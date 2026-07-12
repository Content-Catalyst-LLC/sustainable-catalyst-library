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
            'sanitize_callback' => static fn($value) => min(50, max(1, absint($value))),
            'default' => 12,
        ]);

        register_setting('sc_library_settings', 'sc_library_enable_tags', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
    }

    public function activation_notice(): void {
        if (!get_transient('sc_library_activation_notice')) {
            return;
        }
        delete_transient('sc_library_activation_notice');
        echo '<div class="notice notice-success is-dismissible"><p><strong>Sustainable Catalyst Library v1.0.0 activated.</strong> Run the initial index from SC Library → Settings, then add <code>[sc_library]</code> to the Library page.</p></div>';
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
            <p><?php esc_html_e('Structured publication indexing, public REST endpoints, category navigation, search, and filters.', 'sustainable-catalyst-library'); ?></p>

            <?php if (isset($_GET['indexed'])) : ?>
                <div class="notice notice-success"><p>
                    <?php echo esc_html(sprintf('Index completed: %d indexed, %d failed.', absint($_GET['indexed']), absint($_GET['failed'] ?? 0))); ?>
                </p></div>
            <?php endif; ?>

            <div class="card" style="max-width:900px">
                <h2><?php esc_html_e('Index status', 'sustainable-catalyst-library'); ?></h2>
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
                        <th scope="row"><label for="sc_library_items_per_page"><?php esc_html_e('Default results per page', 'sustainable-catalyst-library'); ?></label></th>
                        <td><input id="sc_library_items_per_page" name="sc_library_items_per_page" type="number" min="1" max="50" value="<?php echo esc_attr((int) get_option('sc_library_items_per_page', 12)); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tag filters', 'sustainable-catalyst-library'); ?></th>
                        <td><label><input name="sc_library_enable_tags" type="checkbox" value="1" <?php checked((int) get_option('sc_library_enable_tags', 1), 1); ?>> <?php esc_html_e('Enable tag filter support in the API.', 'sustainable-catalyst-library'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div class="card" style="max-width:900px">
                <h2><?php esc_html_e('Shortcodes and endpoints', 'sustainable-catalyst-library'); ?></h2>
                <p><code>[sc_library]</code></p>
                <p><code>[sc_library category="systems-modeling" per_page="12"]</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/status</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/categories</code></p>
                <p><code>/wp-json/sustainable-catalyst/v1/library/items</code></p>
            </div>
        </div>
        <?php
    }
}
