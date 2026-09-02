<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.5.2 backend operations and recovery console.
 *
 * All destructive actions are admin-only, nonce-protected, and operate on
 * explicit IDs returned by a signed integrity audit. WordPress remains the
 * source of truth for which Library records should exist.
 */
final class SC_Library_Python_Operations {
    public const VERSION = '5.5.2';
    private const SOURCE_KEY = 'wordpress-main';
    private const HISTORY_LIMIT = 12;

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_sc_library_backend_integrity', [$this, 'handle_integrity']);
        add_action('admin_post_sc_library_backend_repair_integrity', [$this, 'handle_repair_integrity']);
        add_action('admin_post_sc_library_backend_prune_orphans', [$this, 'handle_prune_orphans']);
        add_action('admin_post_sc_library_backend_sync_ids', [$this, 'handle_sync_ids']);
        add_action('admin_post_sc_library_backend_sync_collection', [$this, 'handle_sync_collection']);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Backend Operations', 'sustainable-catalyst-library'),
            __('Backend Operations', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-backend-operations',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) { return; }
        $health = SC_Library_Python_Backend::health();
        $status = self::operations_status();
        $audit = get_option('sc_library_backend_last_integrity_audit', []);
        $history = get_option('sc_library_backend_operation_history', []);
        $checkpoint = get_option('sc_library_backend_sync_checkpoint', []);
        $last_success = get_option('sc_library_backend_last_successful_sync', []);
        $collections = taxonomy_exists(SC_Library_Taxonomies::COLLECTION)
            ? get_terms(['taxonomy' => SC_Library_Taxonomies::COLLECTION, 'hide_empty' => false])
            : [];
        if (is_wp_error($collections)) { $collections = []; }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Library Backend Operations & Recovery', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Audit WordPress truth against the Python index, repair missing or stale records, prune verified orphans, and reindex targeted records without running a full Library rebuild.', 'sustainable-catalyst-library'); ?></p>

            <h2><?php esc_html_e('Operational state', 'sustainable-catalyst-library'); ?></h2>
            <pre style="max-width:1200px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;"><?php echo esc_html(wp_json_encode(['health' => $health, 'operations' => $status], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>

            <?php if (SC_Library_Python_Backend::configured()) : ?>
                <h2><?php esc_html_e('Integrity audit', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Compares every published WordPress Library record with the backend index. It detects missing, stale, orphaned, and unexpectedly chunkless records.', 'sustainable-catalyst-library'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                    <input type="hidden" name="action" value="sc_library_backend_integrity">
                    <?php wp_nonce_field('sc_library_backend_integrity'); ?>
                    <?php submit_button(__('Run integrity audit', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                </form>
                <?php if (is_array($audit) && !empty($audit['repair_record_ids'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                        <input type="hidden" name="action" value="sc_library_backend_repair_integrity">
                        <?php wp_nonce_field('sc_library_backend_repair_integrity'); ?>
                        <?php submit_button(sprintf(__('Repair %d missing/stale records', 'sustainable-catalyst-library'), count($audit['repair_record_ids'])), 'primary', 'submit', false); ?>
                    </form>
                <?php endif; ?>
                <?php if (is_array($audit) && !empty($audit['orphan_record_ids'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js(__('Delete only the orphaned backend records verified by the last integrity audit?', 'sustainable-catalyst-library')); ?>');">
                        <input type="hidden" name="action" value="sc_library_backend_prune_orphans">
                        <?php wp_nonce_field('sc_library_backend_prune_orphans'); ?>
                        <?php submit_button(sprintf(__('Prune %d verified orphans', 'sustainable-catalyst-library'), count($audit['orphan_record_ids'])), 'delete', 'submit', false); ?>
                    </form>
                <?php endif; ?>
                <?php if (is_array($audit) && $audit) : ?>
                    <pre style="max-width:1200px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:12px;"><?php echo esc_html(wp_json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                <?php endif; ?>

                <hr>
                <h2><?php esc_html_e('Targeted reindex', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_backend_sync_ids">
                    <?php wp_nonce_field('sc_library_backend_sync_ids'); ?>
                    <p><label for="sc_library_backend_post_ids"><strong><?php esc_html_e('WordPress post IDs', 'sustainable-catalyst-library'); ?></strong></label></p>
                    <textarea id="sc_library_backend_post_ids" name="post_ids" rows="3" class="large-text code" placeholder="123, 456, 789"></textarea>
                    <p class="description"><?php esc_html_e('Published Library records only. Separate IDs with commas, spaces, or new lines.', 'sustainable-catalyst-library'); ?></p>
                    <?php submit_button(__('Reindex selected records', 'sustainable-catalyst-library'), 'secondary'); ?>
                </form>

                <?php if ($collections) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:18px;">
                        <input type="hidden" name="action" value="sc_library_backend_sync_collection">
                        <?php wp_nonce_field('sc_library_backend_sync_collection'); ?>
                        <label for="sc_library_backend_collection"><strong><?php esc_html_e('Library collection', 'sustainable-catalyst-library'); ?></strong></label>
                        <select id="sc_library_backend_collection" name="collection_term_id">
                            <?php foreach ($collections as $term) : ?>
                                <option value="<?php echo esc_attr((int) $term->term_id); ?>"><?php echo esc_html($term->name . ' (' . (int) $term->count . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php submit_button(__('Reindex collection', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (is_array($checkpoint) && $checkpoint) : ?><h2><?php esc_html_e('Current checkpoint', 'sustainable-catalyst-library'); ?></h2><pre><?php echo esc_html(wp_json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?>
            <?php if (is_array($last_success) && $last_success) : ?><h2><?php esc_html_e('Last successful sync', 'sustainable-catalyst-library'); ?></h2><pre><?php echo esc_html(wp_json_encode($last_success, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?>
            <?php if (is_array($history) && $history) : ?><h2><?php esc_html_e('Recent recovery operations', 'sustainable-catalyst-library'); ?></h2><pre><?php echo esc_html(wp_json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?>
        </div>
        <?php
    }

    private static function redirect(): void {
        wp_safe_redirect(admin_url('admin.php?page=sc-library-backend-operations'));
        exit;
    }

    private static function ensure_admin(string $nonce): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Insufficient permissions.', 'sustainable-catalyst-library')); }
        check_admin_referer($nonce);
        if (!SC_Library_Python_Backend::configured()) { wp_die(esc_html__('Library backend is not configured.', 'sustainable-catalyst-library')); }
    }

    private static function operations_status(): array {
        if (!SC_Library_Python_Backend::configured()) { return ['ok' => false, 'state' => 'not_configured']; }
        $result = SC_Library_Python_Backend::signed_request('GET', '/v1/admin/status', '');
        return is_wp_error($result) ? ['ok' => false, 'error' => $result->get_error_message()] : $result;
    }

    private static function published_ids(array $extra_args = []): array {
        $args = array_merge([
            'post_type' => SC_Library_Python_Backend::post_types(),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ], $extra_args);
        return array_values(array_unique(array_map('intval', get_posts($args))));
    }

    private static function expected_states(): array {
        $records = [];
        foreach (self::published_ids() as $post_id) {
            $post = get_post($post_id);
            if (!$post instanceof WP_Post) { continue; }
            $records[] = [
                'record_id' => SC_Library_Python_Backend::record_id($post_id, $post->post_type),
                'source_updated_at' => get_post_modified_time('c', true, $post),
            ];
        }
        return $records;
    }

    public function handle_integrity(): void {
        self::ensure_admin('sc_library_backend_integrity');
        $body = (string) wp_json_encode(['source_key' => self::SOURCE_KEY, 'records' => self::expected_states()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = SC_Library_Python_Backend::signed_request('POST', '/v1/admin/integrity', $body);
        $audit = is_wp_error($result)
            ? ['ok' => false, 'error' => $result->get_error_message(), 'checked_at' => current_time('mysql', true)]
            : $result;
        update_option('sc_library_backend_last_integrity_audit', $audit, false);
        self::record_history('integrity_audit', $audit);
        self::redirect();
    }

    public function handle_repair_integrity(): void {
        self::ensure_admin('sc_library_backend_repair_integrity');
        $audit = get_option('sc_library_backend_last_integrity_audit', []);
        $record_ids = is_array($audit) && !empty($audit['repair_record_ids']) && is_array($audit['repair_record_ids']) ? $audit['repair_record_ids'] : [];
        $ids = [];
        foreach ($record_ids as $record_id) {
            $post_id = self::post_id_from_record_id((string) $record_id);
            if ($post_id > 0) { $ids[] = $post_id; }
        }
        $summary = $ids ? SC_Library_Python_Backend::run_bulk_sync($ids, 'integrity-repair') : ['ok' => true, 'records' => 0, 'completed' => 0];
        self::record_history('integrity_repair', $summary);
        self::redirect();
    }

    public function handle_prune_orphans(): void {
        self::ensure_admin('sc_library_backend_prune_orphans');
        $audit = get_option('sc_library_backend_last_integrity_audit', []);
        $record_ids = is_array($audit) && !empty($audit['orphan_record_ids']) && is_array($audit['orphan_record_ids']) ? array_values(array_unique(array_map('strval', $audit['orphan_record_ids']))) : [];
        if (!$record_ids) { self::redirect(); }

        // Re-check current WordPress publication truth immediately before a destructive prune.
        $current = [];
        foreach (self::published_ids() as $post_id) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post) { $current[SC_Library_Python_Backend::record_id($post_id, $post->post_type)] = true; }
        }
        $record_ids = array_values(array_filter($record_ids, static fn($record_id) => !isset($current[$record_id])));
        if (!$record_ids) {
            self::record_history('orphan_prune', ['ok' => true, 'requested' => 0, 'deleted' => 0, 'note' => 'No audited orphan remained orphaned at prune time.']);
            self::redirect();
        }
        $body = (string) wp_json_encode(['source_key' => self::SOURCE_KEY, 'record_ids' => $record_ids], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = SC_Library_Python_Backend::signed_request('POST', '/v1/admin/prune', $body);
        $summary = is_wp_error($result) ? ['ok' => false, 'error' => $result->get_error_message()] : $result;
        self::record_history('orphan_prune', $summary);
        self::redirect();
    }

    public function handle_sync_ids(): void {
        self::ensure_admin('sc_library_backend_sync_ids');
        $raw = isset($_POST['post_ids']) ? wp_unslash((string) $_POST['post_ids']) : '';
        $parts = preg_split('/[^0-9]+/', $raw) ?: [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $parts))));
        if (!$ids) {
            self::record_history('targeted_records', ['ok' => false, 'records' => 0, 'completed' => 0, 'error' => 'No WordPress post IDs were supplied.']);
            self::redirect();
        }
        $allowed = array_flip(self::published_ids(['post__in' => $ids]));
        $ids = array_values(array_filter($ids, static fn($id) => isset($allowed[$id])));
        $summary = $ids ? SC_Library_Python_Backend::run_bulk_sync($ids, 'targeted-records') : ['ok' => false, 'records' => 0, 'completed' => 0, 'error' => 'No published Library records matched the supplied IDs.'];
        self::record_history('targeted_records', $summary);
        self::redirect();
    }

    public function handle_sync_collection(): void {
        self::ensure_admin('sc_library_backend_sync_collection');
        $term_id = isset($_POST['collection_term_id']) ? absint($_POST['collection_term_id']) : 0;
        $ids = $term_id > 0 ? self::published_ids([
            'tax_query' => [[
                'taxonomy' => SC_Library_Taxonomies::COLLECTION,
                'field' => 'term_id',
                'terms' => [$term_id],
            ]],
        ]) : [];
        $summary = $ids ? SC_Library_Python_Backend::run_bulk_sync($ids, 'collection') : ['ok' => false, 'records' => 0, 'completed' => 0, 'error' => 'No published records were found in the selected collection.'];
        $summary['collection_term_id'] = $term_id;
        self::record_history('collection_reindex', $summary);
        self::redirect();
    }

    private static function post_id_from_record_id(string $record_id): int {
        $parts = explode(':', $record_id);
        if (4 !== count($parts) || 'wordpress' !== $parts[0] || (int) $parts[1] !== get_current_blog_id()) { return 0; }
        return absint($parts[3]);
    }

    private static function record_history(string $operation, array $result): void {
        $history = get_option('sc_library_backend_operation_history', []);
        $history = is_array($history) ? $history : [];
        array_unshift($history, [
            'operation' => $operation,
            'timestamp' => current_time('mysql', true),
            'result' => $result,
        ]);
        update_option('sc_library_backend_operation_history', array_slice($history, 0, self::HISTORY_LIMIT), false);
    }
}
