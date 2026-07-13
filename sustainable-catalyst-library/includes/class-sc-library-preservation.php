<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Preservation, integrity, and institutional archive services.
 *
 * WordPress remains canonical. Snapshots are immutable projections with
 * checksums and manifests; they never silently replace current content.
 */
final class SC_Library_Preservation {
    public const SCHEMA = 'sc-library-preservation/1.0';
    public const MANIFEST_SCHEMA = 'sc-library-preservation-manifest/1.0';
    public const AUDIT_SCHEMA = 'sc-library-integrity-audit/1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 60);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_archive_meta'], 92, 3);
        add_action('save_post', [$this, 'capture_authority_history'], 96, 3);
        add_action('save_post', [$this, 'automatic_snapshot'], 110, 3);
        add_action('transition_post_status', [$this, 'capture_transition_snapshot'], 110, 3);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_library_institutional_archive', [$this, 'render_archive_shortcode']);
        add_shortcode('sc_library_integrity_status', [$this, 'render_integrity_shortcode']);

        add_action('admin_post_sc_library_preservation_snapshot', [$this, 'handle_snapshot']);
        add_action('admin_post_sc_library_preservation_manifest', [$this, 'handle_manifest']);
        add_action('admin_post_sc_library_preservation_audit_start', [$this, 'handle_audit_start']);
        add_action('admin_post_sc_library_preservation_audit_continue', [$this, 'handle_audit_continue']);
        add_action('admin_post_sc_library_preservation_audit_reset', [$this, 'handle_audit_reset']);
        add_action('admin_post_sc_library_preservation_verify', [$this, 'handle_verify']);
        add_action('admin_post_sc_library_preservation_purge', [$this, 'handle_purge']);
        add_action('admin_post_sc_library_preservation_snapshot_delete', [$this, 'handle_snapshot_delete']);
        add_action('sc_library_preservation_daily', [$this, 'daily_integrity_maintenance']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_preservation', 1);
    }

    public static function snapshots_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_preservation_snapshots';
    }

    public static function checks_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_integrity_checks';
    }

    public static function authority_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_authority_history';
    }

    public static function archive_statuses(): array {
        return [
            'current' => __('Current canonical record', 'sustainable-catalyst-library'),
            'preserved' => __('Preserved current record', 'sustainable-catalyst-library'),
            'superseded' => __('Superseded historical record', 'sustainable-catalyst-library'),
            'archived' => __('Archived institutional record', 'sustainable-catalyst-library'),
        ];
    }

    public function register_settings(): void {
        register_setting('sc_library_preservation_settings', 'sc_library_enable_preservation', [
            'type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_preservation_auto_snapshot', [
            'type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 1,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_preservation_retention_years', [
            'type' => 'integer', 'sanitize_callback' => static fn($value) => min(100, max(1, absint($value))), 'default' => 10,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_preservation_batch_size', [
            'type' => 'integer', 'sanitize_callback' => static fn($value) => min(250, max(10, absint($value))), 'default' => 50,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_integrity_link_timeout', [
            'type' => 'integer', 'sanitize_callback' => static fn($value) => min(30, max(3, absint($value))), 'default' => 8,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_integrity_check_external_links', [
            'type' => 'boolean', 'sanitize_callback' => static fn($value) => $value ? 1 : 0, 'default' => 0,
        ]);
        register_setting('sc_library_preservation_settings', 'sc_library_archive_page_url', [
            'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => home_url('/institutional-archive/'),
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Preservation and Institutional Archive', 'sustainable-catalyst-library'),
            __('Preservation & Archive', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-preservation',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if ($hook !== 'sc-library_page_sc-library-preservation') {
            return;
        }
        wp_enqueue_style('sc-library-preservation', SC_LIBRARY_URL . 'assets/css/sc-library-preservation.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-preservation', SC_LIBRARY_URL . 'assets/js/sc-library-preservation.js', [], SC_LIBRARY_VERSION, true);
    }

    public function add_meta_boxes(): void {
        if (!self::enabled()) {
            return;
        }
        foreach ($this->indexer->discoverable_post_types() as $post_type) {
            if (post_type_supports($post_type, 'editor')) {
                add_meta_box(
                    'sc-library-preservation',
                    __('Preservation and Archive', 'sustainable-catalyst-library'),
                    [$this, 'render_meta_box'],
                    $post_type,
                    'side',
                    'default'
                );
            }
        }
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sc_library_preservation_meta_' . $post->ID, 'sc_library_preservation_nonce');
        $status = sanitize_key((string) get_post_meta($post->ID, '_sc_library_preservation_status', true)) ?: 'current';
        $retention = (string) get_post_meta($post->ID, '_sc_library_retention_until', true);
        $legal_hold = (bool) get_post_meta($post->ID, '_sc_library_legal_hold', true);
        $note = (string) get_post_meta($post->ID, '_sc_library_archive_note', true);
        $latest = $this->latest_snapshot($post->ID);
        ?>
        <p><label for="sc-library-preservation-status"><strong><?php esc_html_e('Institutional state', 'sustainable-catalyst-library'); ?></strong></label></p>
        <select id="sc-library-preservation-status" name="sc_library_preservation_status" style="width:100%">
            <?php foreach (self::archive_statuses() as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p><label><strong><?php esc_html_e('Retention until', 'sustainable-catalyst-library'); ?></strong><br><input type="date" name="sc_library_retention_until" value="<?php echo esc_attr(substr($retention, 0, 10)); ?>" style="width:100%"></label></p>
        <p><label><input type="checkbox" name="sc_library_legal_hold" value="1" <?php checked($legal_hold); ?>> <?php esc_html_e('Legal or institutional hold', 'sustainable-catalyst-library'); ?></label></p>
        <p><label><strong><?php esc_html_e('Archive note', 'sustainable-catalyst-library'); ?></strong><br><textarea name="sc_library_archive_note" rows="3" style="width:100%"><?php echo esc_textarea($note); ?></textarea></label></p>
        <?php if ($latest) : ?>
            <p class="description"><?php echo esc_html(sprintf(__('Latest snapshot: %s', 'sustainable-catalyst-library'), $latest['created_at'])); ?><br><code><?php echo esc_html(substr((string) $latest['source_hash'], 0, 16)); ?>…</code></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No frozen snapshot has been recorded yet.', 'sustainable-catalyst-library'); ?></p>
        <?php endif;
    }

    public function save_archive_meta(int $post_id, WP_Post $post, bool $update): void {
        if (!$update || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!isset($_POST['sc_library_preservation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_preservation_nonce'])), 'sc_library_preservation_meta_' . $post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $status = sanitize_key((string) ($_POST['sc_library_preservation_status'] ?? 'current'));
        if (!isset(self::archive_statuses()[$status])) {
            $status = 'current';
        }
        $retention = sanitize_text_field((string) ($_POST['sc_library_retention_until'] ?? ''));
        if ($retention !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $retention)) {
            $retention = '';
        }
        update_post_meta($post_id, '_sc_library_preservation_status', $status);
        update_post_meta($post_id, '_sc_library_retention_until', $retention);
        update_post_meta($post_id, '_sc_library_legal_hold', !empty($_POST['sc_library_legal_hold']) ? 1 : 0);
        update_post_meta($post_id, '_sc_library_archive_note', sanitize_textarea_field(wp_unslash((string) ($_POST['sc_library_archive_note'] ?? ''))));
    }

    public function automatic_snapshot(int $post_id, WP_Post $post, bool $update): void {
        if (!self::enabled() || !get_option('sc_library_preservation_auto_snapshot', 1) || !$update) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status !== 'publish') {
            return;
        }
        $eligibility = $this->indexer->scan_eligibility($post_id, $this->indexer->configured_post_types());
        if (empty($eligibility['eligible'])) {
            return;
        }
        $this->capture_snapshot($post_id, 'published_update', false);
    }

    public function capture_transition_snapshot(string $new_status, string $old_status, WP_Post $post): void {
        if (!self::enabled() || $new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        $eligibility = $this->indexer->scan_eligibility((int) $post->ID, $this->indexer->configured_post_types());
        if (empty($eligibility['eligible'])) {
            return;
        }
        $this->capture_snapshot((int) $post->ID, 'publication', true);
    }

    /** Capture documentation authority changes as an append-only history. */
    public function capture_authority_history(int $post_id, WP_Post $post, bool $update): void {
        if (!$update || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $authority = [
            'document_status' => sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_status', true)),
            'authority_type' => sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_authority_type', true)),
            'authority_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_authority_url', true)),
            'version_label' => sanitize_text_field((string) get_post_meta($post_id, '_sc_library_doc_version', true)),
            'responsible_area' => sanitize_text_field((string) get_post_meta($post_id, '_sc_library_doc_responsible_area', true)),
            'supersedes_id' => absint(get_post_meta($post_id, '_sc_library_doc_supersedes_id', true)),
            'superseded_by_id' => absint(get_post_meta($post_id, '_sc_library_doc_superseded_by_id', true)),
        ];
        if (!array_filter($authority, static fn($value) => $value !== '' && $value !== 0)) {
            return;
        }
        global $wpdb;
        $latest = $wpdb->get_row($wpdb->prepare(
            'SELECT payload_json FROM ' . self::authority_table() . ' WHERE record_id = %d ORDER BY id DESC LIMIT 1',
            $post_id
        ), ARRAY_A);
        $payload = wp_json_encode($authority, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($latest && hash_equals(hash('sha256', (string) $latest['payload_json']), hash('sha256', (string) $payload))) {
            return;
        }
        $wpdb->insert(self::authority_table(), [
            'authority_uuid' => wp_generate_uuid4(),
            'record_id' => $post_id,
            'document_status' => $authority['document_status'],
            'authority_type' => $authority['authority_type'],
            'authority_url' => $authority['authority_url'],
            'version_label' => $authority['version_label'],
            'responsible_area' => $authority['responsible_area'],
            'supersedes_id' => $authority['supersedes_id'],
            'superseded_by_id' => $authority['superseded_by_id'],
            'payload_json' => $payload ?: '{}',
            'changed_by' => get_current_user_id(),
            'changed_at' => current_time('mysql', true),
        ]);
    }

    public function capture_snapshot(int $post_id, string $reason = 'manual', bool $force = false): array|WP_Error {
        global $wpdb;
        $post = get_post($post_id);
        if (!$post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return new WP_Error('invalid_record', __('The requested record cannot be preserved.', 'sustainable-catalyst-library'));
        }
        $payload = $this->canonical_payload($post);
        $encoded = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return new WP_Error('encoding_failed', __('The preservation payload could not be encoded.', 'sustainable-catalyst-library'));
        }
        $source_hash = hash('sha256', $encoded);
        $latest = $this->latest_snapshot($post_id);
        if (!$force && $latest && hash_equals((string) $latest['source_hash'], $source_hash)) {
            return $latest;
        }
        $snapshot_uuid = wp_generate_uuid4();
        $previous_uuid = $latest ? (string) $latest['snapshot_uuid'] : '';
        $retention_years = max(1, (int) get_option('sc_library_preservation_retention_years', 10));
        $record_retention = sanitize_text_field((string) get_post_meta($post_id, '_sc_library_retention_until', true));
        $retention_until = ($record_retention !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $record_retention))
            ? $record_retention . ' 23:59:59'
            : gmdate('Y-m-d H:i:s', strtotime('+' . $retention_years . ' years'));
        $manifest = $this->build_manifest($post, $payload, $snapshot_uuid, $source_hash, $reason, $previous_uuid);
        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $manifest_hash = hash('sha256', $manifest_json);
        $archive_status = sanitize_key((string) get_post_meta($post_id, '_sc_library_preservation_status', true)) ?: 'current';

        $wpdb->query($wpdb->prepare('UPDATE ' . self::snapshots_table() . ' SET is_current = 0 WHERE record_id = %d', $post_id));
        $inserted = $wpdb->insert(self::snapshots_table(), [
            'snapshot_uuid' => $snapshot_uuid,
            'record_id' => $post_id,
            'record_type' => $post->post_type,
            'title' => wp_strip_all_tags(get_the_title($post_id)),
            'canonical_url' => get_permalink($post_id) ?: '',
            'version_label' => $this->version_label($post_id),
            'snapshot_status' => $archive_status,
            'reason' => sanitize_key($reason),
            'source_hash' => $source_hash,
            'manifest_hash' => $manifest_hash,
            'content_html' => (string) $payload['content_html'],
            'content_text' => (string) $payload['content_text'],
            'metadata_json' => wp_json_encode($payload['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'relationships_json' => wp_json_encode($payload['relationships'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            'resources_json' => wp_json_encode($payload['resources'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            'manifest_json' => $manifest_json,
            'supersedes_uuid' => $previous_uuid,
            'is_current' => 1,
            'legal_hold' => (int) (bool) get_post_meta($post_id, '_sc_library_legal_hold', true),
            'retention_until' => $retention_until,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ]);
        if ($inserted === false) {
            return new WP_Error('snapshot_write_failed', __('The frozen snapshot could not be written.', 'sustainable-catalyst-library'), $wpdb->last_error);
        }
        $row = $this->snapshot_by_uuid($snapshot_uuid);
        do_action('sc_library_preservation_snapshot_created', $row ?: ['snapshot_uuid' => $snapshot_uuid, 'record_id' => $post_id]);
        return $row ?: [];
    }

    private function canonical_payload(WP_Post $post): array {
        global $wpdb;
        $post_id = (int) $post->ID;
        $taxonomies = [];
        foreach (get_object_taxonomies($post->post_type, 'names') as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'all']);
            if (is_wp_error($terms)) {
                continue;
            }
            $taxonomies[$taxonomy] = array_map(static fn(WP_Term $term) => [
                'term_id' => (int) $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
            ], $terms);
        }
        $metadata = [];
        foreach ((array) get_post_meta($post_id) as $key => $values) {
            if (!str_starts_with((string) $key, '_sc_library_')) {
                continue;
            }
            if (preg_match('/(secret|token|password|api_key|private|internal)/i', (string) $key)) {
                continue;
            }
            $metadata[$key] = array_map('maybe_unserialize', (array) $values);
        }
        $relationships = $wpdb->get_results($wpdb->prepare(
            'SELECT source_post_id,target_post_id,relationship_type,note,confidence,confidence_basis,provenance_type,provenance_url,evidence_note,visibility,sort_order FROM ' . $this->relationships->table_name() . ' WHERE source_post_id = %d OR target_post_id = %d ORDER BY id ASC',
            $post_id,
            $post_id
        ), ARRAY_A) ?: [];
        $index_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->indexer->table_name() . ' WHERE post_id = %d', $post_id), ARRAY_A) ?: [];
        $resources = json_decode((string) ($index_row['resource_flags'] ?? '[]'), true);
        if (!is_array($resources)) {
            $resources = [];
        }
        $foundation_versions = [];
        if (class_exists('SC_Library_Foundation_Documents') && $post->post_type === SC_Library_Foundation_Documents::POST_TYPE) {
            $versions_table = $wpdb->prefix . 'sc_library_foundation_versions';
            $foundation_versions = $wpdb->get_results($wpdb->prepare(
                "SELECT version_label,filename,pdf_url,sha256,page_count,created_at FROM {$versions_table} WHERE post_id = %d ORDER BY id ASC",
                $post_id
            ), ARRAY_A) ?: [];
        }
        return [
            'schema' => self::SCHEMA,
            'record_id' => $post_id,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'title' => get_the_title($post_id),
            'slug' => $post->post_name,
            'excerpt' => $post->post_excerpt,
            'content_html' => (string) $post->post_content,
            'content_text' => wp_strip_all_tags(strip_shortcodes((string) $post->post_content), true),
            'canonical_url' => get_permalink($post_id) ?: '',
            'author_id' => (int) $post->post_author,
            'published_at' => get_post_time(DATE_ATOM, true, $post),
            'modified_at' => get_post_modified_time(DATE_ATOM, true, $post),
            'taxonomies' => $taxonomies,
            'metadata' => $metadata,
            'relationships' => $relationships,
            'resources' => $resources,
            'foundation_versions' => $foundation_versions,
        ];
    }

    private function build_manifest(WP_Post $post, array $payload, string $snapshot_uuid, string $source_hash, string $reason, string $previous_uuid): array {
        $attachment_checksums = [];
        foreach ($this->attachment_ids_for_record((int) $post->ID) as $attachment_id) {
            $file = get_attached_file($attachment_id);
            $attachment_checksums[] = [
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id) ?: '',
                'filename' => $file ? wp_basename($file) : '',
                'sha256' => ($file && is_readable($file)) ? hash_file('sha256', $file) : '',
                'bytes' => ($file && is_readable($file)) ? (int) filesize($file) : 0,
            ];
        }
        return [
            'schema' => self::MANIFEST_SCHEMA,
            'plugin_version' => SC_LIBRARY_VERSION,
            'snapshot_uuid' => $snapshot_uuid,
            'supersedes_uuid' => $previous_uuid,
            'reason' => $reason,
            'generated_at' => current_time(DATE_ATOM, true),
            'record' => [
                'id' => (int) $post->ID,
                'post_type' => $post->post_type,
                'title' => (string) $payload['title'],
                'canonical_url' => (string) $payload['canonical_url'],
                'archive_status' => sanitize_key((string) get_post_meta($post->ID, '_sc_library_preservation_status', true)) ?: 'current',
            ],
            'checksums' => [
                'canonical_payload_sha256' => $source_hash,
                'content_html_sha256' => hash('sha256', (string) $payload['content_html']),
                'content_text_sha256' => hash('sha256', (string) $payload['content_text']),
            ],
            'attachments' => $attachment_checksums,
            'relationship_count' => count($payload['relationships']),
            'taxonomy_count' => count($payload['taxonomies']),
            'authority' => [
                'type' => (string) get_post_meta($post->ID, '_sc_library_doc_authority_type', true),
                'url' => (string) get_post_meta($post->ID, '_sc_library_doc_authority_url', true),
                'status' => (string) get_post_meta($post->ID, '_sc_library_doc_status', true),
                'version' => $this->version_label((int) $post->ID),
                'supersedes_id' => absint(get_post_meta($post->ID, '_sc_library_doc_supersedes_id', true)),
                'superseded_by_id' => absint(get_post_meta($post->ID, '_sc_library_doc_superseded_by_id', true)),
            ],
        ];
    }

    private function version_label(int $post_id): string {
        foreach (['_sc_library_foundation_version', '_sc_library_doc_version', '_sc_library_document_version'] as $key) {
            $value = sanitize_text_field((string) get_post_meta($post_id, $key, true));
            if ($value !== '') {
                return $value;
            }
        }
        $revisions = wp_get_post_revisions($post_id, ['numberposts' => 1, 'fields' => 'ids']);
        $revision_id = $revisions ? absint(reset($revisions)) : 0;
        return 'WordPress revision ' . $revision_id;
    }

    private function attachment_ids_for_record(int $post_id): array {
        $ids = [];
        $thumbnail = get_post_thumbnail_id($post_id);
        if ($thumbnail) {
            $ids[] = (int) $thumbnail;
        }
        foreach (['_sc_library_foundation_pdf_id', '_sc_library_doc_pdf_attachment_id', '_sc_library_document_attachment_id'] as $key) {
            $id = absint(get_post_meta($post_id, $key, true));
            if ($id) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    public function latest_snapshot(int $record_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::snapshots_table() . ' WHERE record_id = %d ORDER BY is_current DESC, id DESC LIMIT 1',
            $record_id
        ), ARRAY_A);
        return $row ?: null;
    }

    public function snapshot_by_uuid(string $uuid): ?array {
        global $wpdb;
        if (!wp_is_uuid($uuid)) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::snapshots_table() . ' WHERE snapshot_uuid = %s', $uuid), ARRAY_A);
        return $row ?: null;
    }

    public function snapshots_for_record(int $record_id, int $limit = 100): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::snapshots_table() . ' WHERE record_id = %d ORDER BY id DESC LIMIT %d',
            $record_id,
            min(500, max(1, $limit))
        ), ARRAY_A) ?: [];
    }

    public function register_routes(): void {
        $ns = 'sustainable-catalyst/v1/library';
        register_rest_route($ns, '/preservation/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/archive', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_archive'],
            'permission_callback' => '__return_true',
            'args' => ['record_id' => ['sanitize_callback' => 'absint'], 'page' => ['sanitize_callback' => 'absint', 'default' => 1], 'per_page' => ['sanitize_callback' => 'absint', 'default' => 20]],
        ]);
        register_rest_route($ns, '/archive/(?P<uuid>[0-9a-fA-F-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_snapshot'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/archive/(?P<uuid>[0-9a-fA-F-]{36})/manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_manifest'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/preservation/diagnostics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_diagnostics'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        $public_ns = class_exists('SC_Library_Developer_API') ? SC_Library_Developer_API::API_NAMESPACE : 'sustainable-catalyst-library/v1';
        register_rest_route($public_ns, '/archive', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_archive'],
            'permission_callback' => '__return_true',
            'args' => ['record_id' => ['sanitize_callback' => 'absint'], 'page' => ['sanitize_callback' => 'absint', 'default' => 1], 'per_page' => ['sanitize_callback' => 'absint', 'default' => 20]],
        ]);
        register_rest_route($public_ns, '/archive/(?P<uuid>[0-9a-fA-F-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_snapshot'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($public_ns, '/archive/(?P<uuid>[0-9a-fA-F-]{36})/manifest', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_manifest'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_status(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'ok' => true,
            'schema' => self::SCHEMA,
            'enabled' => self::enabled(),
            'snapshots' => $this->count_snapshots(),
            'archived_records' => $this->count_archived_records(),
            'last_audit' => get_option('sc_library_integrity_last_audit', []),
        ]);
    }

    public function rest_archive(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $record_id = absint($request['record_id']);
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $where = 'WHERE snapshot_status IN (\'preserved\',\'superseded\',\'archived\',\'current\')';
        $params = [];
        if ($record_id) {
            $where .= ' AND record_id = %d';
            $params[] = $record_id;
        }
        $offset = ($page - 1) * $per_page;
        $sql = 'SELECT snapshot_uuid,record_id,record_type,title,canonical_url,version_label,snapshot_status,source_hash,manifest_hash,supersedes_uuid,is_current,created_at FROM ' . self::snapshots_table() . " {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        $rows = array_values(array_filter($rows, fn($row) => $this->snapshot_publicly_visible($row)));
        return new WP_REST_Response(['schema' => self::SCHEMA, 'items' => $rows, 'page' => $page, 'per_page' => $per_page]);
    }

    public function rest_snapshot(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->snapshot_by_uuid(sanitize_text_field((string) $request['uuid']));
        if (!$row || !$this->snapshot_publicly_visible($row)) {
            return new WP_Error('not_found', __('Snapshot not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        return new WP_REST_Response($this->public_snapshot($row));
    }

    public function rest_manifest(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->snapshot_by_uuid(sanitize_text_field((string) $request['uuid']));
        if (!$row || !$this->snapshot_publicly_visible($row)) {
            return new WP_Error('not_found', __('Manifest not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        return new WP_REST_Response(json_decode((string) $row['manifest_json'], true) ?: []);
    }

    public function rest_diagnostics(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response($this->diagnostics());
    }

    private function snapshot_publicly_visible(array $row): bool {
        $post = get_post(absint($row['record_id'] ?? 0));
        if (!$post) {
            return false;
        }
        return $post->post_status === 'publish' && !post_password_required($post);
    }

    private function public_snapshot(array $row): array {
        return [
            'schema' => self::SCHEMA,
            'snapshot_uuid' => $row['snapshot_uuid'],
            'record_id' => (int) $row['record_id'],
            'record_type' => $row['record_type'],
            'title' => $row['title'],
            'canonical_url' => $row['canonical_url'],
            'version_label' => $row['version_label'],
            'snapshot_status' => $row['snapshot_status'],
            'source_hash' => $row['source_hash'],
            'manifest_hash' => $row['manifest_hash'],
            'content_html' => wp_kses_post((string) $row['content_html']),
            'supersedes_uuid' => $row['supersedes_uuid'],
            'created_at' => $row['created_at'],
            'manifest_url' => rest_url('sustainable-catalyst/v1/library/archive/' . rawurlencode((string) $row['snapshot_uuid']) . '/manifest'),
        ];
    }

    public function render_archive_shortcode(array $atts = []): string {
        $atts = shortcode_atts(['record' => 0, 'limit' => 30, 'show_content' => 'true'], $atts, 'sc_library_institutional_archive');
        $record_id = absint($_GET['sc_archive_record'] ?? $atts['record']);
        $uuid = sanitize_text_field((string) ($_GET['sc_archive_snapshot'] ?? ''));
        $compare_uuid = sanitize_text_field((string) ($_GET['sc_archive_compare'] ?? ''));
        wp_enqueue_style('sc-library-preservation', SC_LIBRARY_URL . 'assets/css/sc-library-preservation.css', [], SC_LIBRARY_VERSION);
        ob_start();
        echo '<section class="sc-library-archive" data-sc-library-archive>';
        echo '<header><p class="sc-library-archive__eyebrow">' . esc_html__('Sustainable Catalyst Institutional Archive', 'sustainable-catalyst-library') . '</p><h2>' . esc_html__('Preserved knowledge and historical editions', 'sustainable-catalyst-library') . '</h2><p>' . esc_html__('Frozen snapshots preserve what a public record said at a specific time. Current canonical pages remain the source of truth unless a snapshot is explicitly identified as authoritative.', 'sustainable-catalyst-library') . '</p></header>';
        if ($uuid !== '') {
            $snapshot = $this->snapshot_by_uuid($uuid);
            if ($snapshot && $this->snapshot_publicly_visible($snapshot)) {
                $this->render_public_snapshot($snapshot, $compare_uuid);
            } else {
                echo '<p class="sc-library-archive__notice">' . esc_html__('That preserved snapshot is unavailable.', 'sustainable-catalyst-library') . '</p>';
            }
        } else {
            $this->render_archive_listing($record_id, min(100, max(1, absint($atts['limit']))));
        }
        echo '</section>';
        return (string) ob_get_clean();
    }

    private function render_archive_listing(int $record_id, int $limit): void {
        global $wpdb;
        if ($record_id) {
            $rows = $this->snapshots_for_record($record_id, $limit);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . self::snapshots_table() . " WHERE snapshot_status IN ('preserved','superseded','archived') ORDER BY id DESC LIMIT %d",
                $limit
            ), ARRAY_A) ?: [];
        }
        $rows = array_values(array_filter($rows, fn($row) => $this->snapshot_publicly_visible($row)));
        if (!$rows) {
            echo '<p class="sc-library-archive__notice">' . esc_html__('No public preserved editions are available yet.', 'sustainable-catalyst-library') . '</p>';
            return;
        }
        echo '<div class="sc-library-archive__grid">';
        foreach ($rows as $row) {
            $url = add_query_arg('sc_archive_snapshot', rawurlencode((string) $row['snapshot_uuid']));
            echo '<article class="sc-library-archive__card">';
            echo '<p class="sc-library-archive__state">' . esc_html(ucfirst((string) $row['snapshot_status'])) . '</p>';
            echo '<h3><a href="' . esc_url($url) . '">' . esc_html((string) $row['title']) . '</a></h3>';
            echo '<p>' . esc_html((string) ($row['version_label'] ?: __('Frozen edition', 'sustainable-catalyst-library'))) . '</p>';
            echo '<p><time>' . esc_html(mysql2date(get_option('date_format'), (string) $row['created_at'])) . '</time></p>';
            echo '<p><code>' . esc_html(substr((string) $row['source_hash'], 0, 16)) . '…</code></p>';
            echo '</article>';
        }
        echo '</div>';
    }

    private function render_public_snapshot(array $snapshot, string $compare_uuid = ''): void {
        echo '<article class="sc-library-archive__snapshot">';
        echo '<div class="sc-library-archive__banner"><strong>' . esc_html__('Frozen historical edition', 'sustainable-catalyst-library') . '</strong><span>' . esc_html(sprintf(__('Preserved %s', 'sustainable-catalyst-library'), mysql2date(get_option('date_format'), (string) $snapshot['created_at']))) . '</span></div>';
        echo '<h1>' . esc_html((string) $snapshot['title']) . '</h1>';
        echo '<p class="sc-library-archive__meta">' . esc_html((string) $snapshot['version_label']) . ' · <code>' . esc_html(substr((string) $snapshot['source_hash'], 0, 20)) . '…</code></p>';
        if (!empty($snapshot['canonical_url'])) {
            echo '<p><a class="sc-library-archive__button" href="' . esc_url((string) $snapshot['canonical_url']) . '">' . esc_html__('Open current canonical record', 'sustainable-catalyst-library') . '</a></p>';
        }
        if ($compare_uuid !== '') {
            $other = $this->snapshot_by_uuid($compare_uuid);
            if ($other && (int) $other['record_id'] === (int) $snapshot['record_id'] && $this->snapshot_publicly_visible($other)) {
                echo '<section class="sc-library-archive__diff"><h2>' . esc_html__('Version comparison', 'sustainable-catalyst-library') . '</h2>' . $this->render_diff((string) $other['content_text'], (string) $snapshot['content_text']) . '</section>';
            }
        }
        echo '<div class="sc-library-archive__content">' . wp_kses_post((string) $snapshot['content_html']) . '</div>';
        $siblings = $this->snapshots_for_record((int) $snapshot['record_id'], 30);
        if (count($siblings) > 1) {
            echo '<section class="sc-library-archive__versions"><h2>' . esc_html__('Version chain', 'sustainable-catalyst-library') . '</h2><ol>';
            foreach ($siblings as $row) {
                if (!$this->snapshot_publicly_visible($row)) continue;
                $view_url = add_query_arg('sc_archive_snapshot', rawurlencode((string) $row['snapshot_uuid']));
                $compare_url = add_query_arg(['sc_archive_snapshot' => $snapshot['snapshot_uuid'], 'sc_archive_compare' => $row['snapshot_uuid']]);
                echo '<li><a href="' . esc_url($view_url) . '">' . esc_html((string) ($row['version_label'] ?: $row['created_at'])) . '</a> <a class="sc-library-archive__compare" href="' . esc_url($compare_url) . '">' . esc_html__('Compare', 'sustainable-catalyst-library') . '</a></li>';
            }
            echo '</ol></section>';
        }
        echo '</article>';
    }

    private function render_diff(string $old, string $new): string {
        if (!function_exists('wp_text_diff')) {
            require_once ABSPATH . 'wp-admin/includes/revision.php';
        }
        if (function_exists('wp_text_diff')) {
            return (string) wp_text_diff($old, $new, ['title_left' => __('Earlier edition', 'sustainable-catalyst-library'), 'title_right' => __('Selected edition', 'sustainable-catalyst-library')]);
        }
        return '<p>' . esc_html(sprintf(__('Earlier length: %1$d characters. Selected length: %2$d characters.', 'sustainable-catalyst-library'), strlen($old), strlen($new))) . '</p>';
    }

    public function render_integrity_shortcode(): string {
        $last = get_option('sc_library_integrity_last_audit', []);
        $status = is_array($last) ? (string) ($last['status'] ?? 'not_run') : 'not_run';
        $counts = is_array($last) ? (array) ($last['counts'] ?? []) : [];
        return sprintf(
            '<section class="sc-library-integrity-summary"><p class="sc-library-archive__eyebrow">%s</p><h3>%s</h3><p>%s</p><dl><div><dt>%s</dt><dd>%s</dd></div><div><dt>%s</dt><dd>%s</dd></div><div><dt>%s</dt><dd>%s</dd></div></dl></section>',
            esc_html__('Institutional integrity', 'sustainable-catalyst-library'),
            esc_html(ucwords(str_replace('_', ' ', $status))),
            esc_html((string) ($last['completed_at'] ?? __('No complete audit has been recorded.', 'sustainable-catalyst-library'))),
            esc_html__('Healthy', 'sustainable-catalyst-library'), esc_html(number_format_i18n(absint($counts['healthy'] ?? 0))),
            esc_html__('Warnings', 'sustainable-catalyst-library'), esc_html(number_format_i18n(absint($counts['warning'] ?? 0))),
            esc_html__('Failures', 'sustainable-catalyst-library'), esc_html(number_format_i18n(absint($counts['error'] ?? 0)))
        );
    }

    public function handle_snapshot(): void {
        $this->require_admin('sc_library_preservation_snapshot');
        $record_id = absint($_POST['record_id'] ?? 0);
        $result = $this->capture_snapshot($record_id, 'manual', true);
        $this->redirect_admin(is_wp_error($result) ? 'snapshot_error' : 'snapshot_created', is_wp_error($result) ? $result->get_error_message() : '');
    }

    public function handle_manifest(): void {
        $this->require_admin('sc_library_preservation_manifest');
        $uuid = sanitize_text_field((string) ($_POST['snapshot_uuid'] ?? ''));
        $snapshot = $this->snapshot_by_uuid($uuid);
        if (!$snapshot) {
            wp_die(esc_html__('Snapshot not found.', 'sustainable-catalyst-library'));
        }
        $manifest = json_decode((string) $snapshot['manifest_json'], true) ?: [];
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sc-library-preservation-manifest-' . sanitize_file_name($uuid) . '.json"');
        echo wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function handle_verify(): void {
        $this->require_admin('sc_library_preservation_verify');
        $record_id = absint($_POST['record_id'] ?? 0);
        $result = $this->verify_record($record_id);
        $this->redirect_admin('verified', sprintf('%d', count($result)));
    }

    public function handle_audit_start(): void {
        $this->require_admin('sc_library_preservation_audit_start');
        $this->start_integrity_audit();
        $this->process_integrity_batch();
        $this->redirect_admin('audit_progress');
    }

    public function handle_audit_continue(): void {
        $this->require_admin('sc_library_preservation_audit_continue');
        $this->process_integrity_batch();
        $this->redirect_admin('audit_progress');
    }

    public function handle_audit_reset(): void {
        $this->require_admin('sc_library_preservation_audit_reset');
        delete_option('sc_library_integrity_state');
        $this->redirect_admin('audit_reset');
    }

    public function handle_purge(): void {
        $this->require_admin('sc_library_preservation_purge');
        if (empty($_POST['confirm_purge'])) {
            $this->redirect_admin('purge_cancelled');
        }
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM " . self::snapshots_table() . " WHERE is_current = 0 AND legal_hold = 0 AND retention_until IS NOT NULL AND retention_until < UTC_TIMESTAMP()"
        );
        $this->redirect_admin('purged', (string) max(0, (int) $deleted));
    }

    public function handle_snapshot_delete(): void {
        $this->require_admin('sc_library_preservation_snapshot_delete');
        global $wpdb;
        $uuid = sanitize_text_field((string) ($_POST['snapshot_uuid'] ?? ''));
        $snapshot = $this->snapshot_by_uuid($uuid);
        if (!$snapshot || !empty($snapshot['is_current']) || !empty($snapshot['legal_hold'])) {
            $this->redirect_admin('snapshot_protected');
        }
        $wpdb->delete(self::snapshots_table(), ['snapshot_uuid' => $uuid]);
        $this->redirect_admin('snapshot_deleted');
    }

    private function require_admin(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'sustainable-catalyst-library'));
        }
        check_admin_referer($action);
    }

    private function redirect_admin(string $notice, string $detail = ''): void {
        wp_safe_redirect(add_query_arg(['page' => 'sc-library-preservation', 'preservation_notice' => $notice, 'detail' => rawurlencode($detail)], admin_url('admin.php')));
        exit;
    }

    public function start_integrity_audit(): array {
        global $wpdb;
        $state = [
            'schema' => self::AUDIT_SCHEMA,
            'run_uuid' => wp_generate_uuid4(),
            'status' => 'running',
            'phase' => 'records',
            'cursor' => 0,
            'counts' => ['processed' => 0, 'healthy' => 0, 'warning' => 0, 'error' => 0],
            'started_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ];
        update_option('sc_library_integrity_state', $state, false);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::checks_table() . ' WHERE checked_at < %s', gmdate('Y-m-d H:i:s', strtotime('-180 days'))));
        return $state;
    }

    public function process_integrity_batch(): array {
        global $wpdb;
        $state = get_option('sc_library_integrity_state', []);
        if (!is_array($state) || ($state['status'] ?? '') !== 'running') {
            $state = $this->start_integrity_audit();
        }
        $batch = min(250, max(10, (int) get_option('sc_library_preservation_batch_size', 50)));
        if (($state['phase'] ?? 'records') === 'records') {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT post_id FROM ' . $this->indexer->table_name() . ' WHERE post_id > %d ORDER BY post_id ASC LIMIT %d',
                absint($state['cursor'] ?? 0),
                $batch
            ), ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $post_id = absint($row['post_id']);
                $results = $this->verify_record($post_id, (string) $state['run_uuid']);
                $state['cursor'] = $post_id;
                $state['counts']['processed']++;
                foreach ($results as $result) {
                    $level = (string) ($result['status'] ?? 'warning');
                    $state['counts'][$level] = absint($state['counts'][$level] ?? 0) + 1;
                }
            }
            if (count($rows) < $batch) {
                $state['phase'] = 'relationships';
                $state['cursor'] = 0;
            }
        } elseif ($state['phase'] === 'relationships') {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT id,source_post_id,target_post_id,provenance_url FROM ' . $this->relationships->table_name() . ' WHERE id > %d ORDER BY id ASC LIMIT %d',
                absint($state['cursor'] ?? 0),
                $batch
            ), ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $status = get_post(absint($row['source_post_id'])) && get_post(absint($row['target_post_id'])) ? 'healthy' : 'error';
                $this->record_check([
                    'run_uuid' => $state['run_uuid'],
                    'record_id' => absint($row['source_post_id']),
                    'object_type' => 'relationship',
                    'check_type' => 'relationship_targets',
                    'target_url' => (string) $row['provenance_url'],
                    'status' => $status,
                    'message' => $status === 'healthy' ? __('Relationship endpoints exist.', 'sustainable-catalyst-library') : __('A relationship endpoint no longer exists.', 'sustainable-catalyst-library'),
                    'details' => $row,
                ]);
                $state['cursor'] = absint($row['id']);
                $state['counts'][$status] = absint($state['counts'][$status] ?? 0) + 1;
            }
            if (count($rows) < $batch) {
                $state['phase'] = 'complete';
                $state['status'] = !empty($state['counts']['error']) ? 'complete_with_errors' : 'complete';
                $state['completed_at'] = current_time('mysql', true);
                update_option('sc_library_integrity_last_audit', $state, false);
                do_action('sc_library_integrity_audit_completed', $state);
            }
        }
        $state['updated_at'] = current_time('mysql', true);
        update_option('sc_library_integrity_state', $state, false);
        return $state;
    }

    public function verify_record(int $record_id, string $run_uuid = ''): array {
        $post = get_post($record_id);
        if (!$post) {
            return [];
        }
        $run_uuid = wp_is_uuid($run_uuid) ? $run_uuid : wp_generate_uuid4();
        $results = [];
        $latest = $this->latest_snapshot($record_id);
        if (!$latest) {
            $results[] = $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'record', 'check_type' => 'snapshot_presence',
                'status' => 'warning', 'message' => __('No frozen snapshot exists for this record.', 'sustainable-catalyst-library'),
            ]);
        } else {
            $current = $this->canonical_payload($post);
            $encoded = wp_json_encode($current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            $actual = hash('sha256', $encoded);
            $status = hash_equals((string) $latest['source_hash'], $actual) ? 'healthy' : 'warning';
            $results[] = $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'record', 'check_type' => 'canonical_checksum',
                'expected_hash' => (string) $latest['source_hash'], 'actual_hash' => $actual, 'status' => $status,
                'message' => $status === 'healthy' ? __('Current content matches the latest frozen snapshot.', 'sustainable-catalyst-library') : __('Current content has changed since the latest frozen snapshot.', 'sustainable-catalyst-library'),
            ]);
        }
        foreach ($this->attachment_ids_for_record($record_id) as $attachment_id) {
            $file = get_attached_file($attachment_id);
            $exists = $file && is_readable($file);
            $results[] = $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'attachment', 'check_type' => 'attachment_file',
                'target_url' => wp_get_attachment_url($attachment_id) ?: '', 'status' => $exists ? 'healthy' : 'error',
                'actual_hash' => $exists ? hash_file('sha256', $file) : '',
                'message' => $exists ? __('Attachment exists and is readable.', 'sustainable-catalyst-library') : __('Attachment file is missing or unreadable.', 'sustainable-catalyst-library'),
                'details' => ['attachment_id' => $attachment_id, 'file' => $file ?: ''],
            ]);
        }
        $superseded_by = absint(get_post_meta($record_id, '_sc_library_doc_superseded_by_id', true));
        $doc_status = sanitize_key((string) get_post_meta($record_id, '_sc_library_doc_status', true));
        if ($doc_status === 'superseded' && (!$superseded_by || !get_post($superseded_by))) {
            $results[] = $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'authority', 'check_type' => 'supersession_chain',
                'status' => 'error', 'message' => __('The record is superseded but has no valid replacement record.', 'sustainable-catalyst-library'),
            ]);
        }
        $authority_url = esc_url_raw((string) get_post_meta($record_id, '_sc_library_doc_authority_url', true));
        if ($authority_url !== '') {
            $results[] = $this->check_url($run_uuid, $record_id, 'authority_url', $authority_url);
        }
        if (get_option('sc_library_integrity_check_external_links', 0)) {
            foreach (array_slice($this->extract_links((string) $post->post_content), 0, 20) as $url) {
                $results[] = $this->check_url($run_uuid, $record_id, 'content_link', $url);
            }
        }
        return $results;
    }

    private function check_url(string $run_uuid, int $record_id, string $check_type, string $url): array {
        $url = esc_url_raw($url);
        if (!$this->safe_public_url($url)) {
            return $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'link', 'check_type' => $check_type,
                'target_url' => $url, 'status' => 'error', 'message' => __('The URL is invalid or resolves to a private network.', 'sustainable-catalyst-library'),
            ]);
        }
        $args = ['timeout' => (int) get_option('sc_library_integrity_link_timeout', 8), 'redirection' => 3, 'user-agent' => 'Sustainable-Catalyst-Library-Integrity/' . SC_LIBRARY_VERSION];
        $response = wp_safe_remote_head($url, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 405) {
            $args['headers'] = ['Range' => 'bytes=0-1024'];
            $response = wp_safe_remote_get($url, $args);
        }
        if (is_wp_error($response)) {
            return $this->record_check([
                'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'link', 'check_type' => $check_type,
                'target_url' => $url, 'status' => 'error', 'message' => $response->get_error_message(),
            ]);
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $status = $code >= 200 && $code < 400 ? 'healthy' : ($code === 401 || $code === 403 || $code === 429 ? 'warning' : 'error');
        return $this->record_check([
            'run_uuid' => $run_uuid, 'record_id' => $record_id, 'object_type' => 'link', 'check_type' => $check_type,
            'target_url' => $url, 'status' => $status, 'response_code' => $code,
            'message' => sprintf(__('HTTP status %d.', 'sustainable-catalyst-library'), $code),
        ]);
    }

    private function safe_public_url(string $url): bool {
        if (!wp_http_validate_url($url) || !in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        if ($host === '' || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    private function extract_links(string $html): array {
        if (!preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('esc_url_raw', $matches[1]))));
    }

    private function record_check(array $data): array {
        global $wpdb;
        $row = [
            'check_uuid' => wp_generate_uuid4(),
            'run_uuid' => wp_is_uuid((string) ($data['run_uuid'] ?? '')) ? $data['run_uuid'] : wp_generate_uuid4(),
            'record_id' => absint($data['record_id'] ?? 0),
            'object_type' => sanitize_key((string) ($data['object_type'] ?? 'record')),
            'check_type' => sanitize_key((string) ($data['check_type'] ?? 'unknown')),
            'target_url' => esc_url_raw((string) ($data['target_url'] ?? '')),
            'expected_hash' => sanitize_text_field((string) ($data['expected_hash'] ?? '')),
            'actual_hash' => sanitize_text_field((string) ($data['actual_hash'] ?? '')),
            'status' => in_array((string) ($data['status'] ?? ''), ['healthy', 'warning', 'error'], true) ? $data['status'] : 'warning',
            'response_code' => (int) ($data['response_code'] ?? 0),
            'message' => sanitize_text_field((string) ($data['message'] ?? '')),
            'details_json' => wp_json_encode($data['details'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'checked_at' => current_time('mysql', true),
        ];
        $wpdb->insert(self::checks_table(), $row);
        return $row;
    }

    public function daily_integrity_maintenance(): void {
        if (!self::enabled()) {
            return;
        }
        $state = get_option('sc_library_integrity_state', []);
        if (is_array($state) && ($state['status'] ?? '') === 'running') {
            $this->process_integrity_batch();
            return;
        }
        $last = get_option('sc_library_integrity_last_audit', []);
        $completed = is_array($last) ? strtotime((string) ($last['completed_at'] ?? '')) : false;
        if (!$completed || $completed < strtotime('-7 days')) {
            $this->start_integrity_audit();
            $this->process_integrity_batch();
        }
    }

    public function diagnostics(): array {
        global $wpdb;
        $status_counts = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::checks_table() . ' GROUP BY status', ARRAY_A) ?: [];
        $by_status = ['healthy' => 0, 'warning' => 0, 'error' => 0];
        foreach ($status_counts as $row) {
            $by_status[(string) $row['status']] = absint($row['total']);
        }
        return [
            'schema' => self::AUDIT_SCHEMA,
            'snapshots' => $this->count_snapshots(),
            'archived_records' => $this->count_archived_records(),
            'authority_events' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::authority_table()),
            'checks' => $by_status,
            'current_state' => get_option('sc_library_integrity_state', []),
            'last_audit' => get_option('sc_library_integrity_last_audit', []),
            'expired_unprotected_snapshots' => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::snapshots_table() . " WHERE is_current = 0 AND legal_hold = 0 AND retention_until IS NOT NULL AND retention_until < UTC_TIMESTAMP()"),
        ];
    }

    private function count_snapshots(): int {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::snapshots_table());
    }

    private function count_archived_records(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT record_id) FROM " . self::snapshots_table() . " WHERE snapshot_status IN ('preserved','superseded','archived')");
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $diagnostics = $this->diagnostics();
        $state = is_array($diagnostics['current_state']) ? $diagnostics['current_state'] : [];
        $recent_snapshots = $wpdb->get_results('SELECT * FROM ' . self::snapshots_table() . ' ORDER BY id DESC LIMIT 20', ARRAY_A) ?: [];
        $failures = $wpdb->get_results("SELECT * FROM " . self::checks_table() . " WHERE status IN ('warning','error') ORDER BY id DESC LIMIT 30", ARRAY_A) ?: [];
        $notice = sanitize_key((string) ($_GET['preservation_notice'] ?? ''));
        ?>
        <div class="wrap sc-library-preservation-admin">
            <h1><?php esc_html_e('Preservation, Integrity, and Institutional Archive', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Freeze public records, verify checksums and dependencies, monitor links and attachments, preserve authority history, and expose controlled historical editions without replacing current canonical content.', 'sustainable-catalyst-library'); ?></p>
            <?php if ($notice) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(str_replace('_', ' ', ucfirst($notice))); ?></p></div><?php endif; ?>

            <div class="sc-library-preservation-metrics">
                <?php $this->metric(__('Frozen snapshots', 'sustainable-catalyst-library'), $diagnostics['snapshots']); ?>
                <?php $this->metric(__('Archived records', 'sustainable-catalyst-library'), $diagnostics['archived_records']); ?>
                <?php $this->metric(__('Authority events', 'sustainable-catalyst-library'), $diagnostics['authority_events']); ?>
                <?php $this->metric(__('Integrity failures', 'sustainable-catalyst-library'), $diagnostics['checks']['error']); ?>
                <?php $this->metric(__('Integrity warnings', 'sustainable-catalyst-library'), $diagnostics['checks']['warning']); ?>
                <?php $this->metric(__('Expired snapshots', 'sustainable-catalyst-library'), $diagnostics['expired_unprotected_snapshots']); ?>
            </div>

            <div class="sc-library-preservation-columns">
                <section class="sc-library-preservation-panel">
                    <h2><?php esc_html_e('Integrity audit', 'sustainable-catalyst-library'); ?></h2>
                    <p><strong><?php esc_html_e('State:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html((string) ($state['status'] ?? 'not started')); ?> · <strong><?php esc_html_e('Phase:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html((string) ($state['phase'] ?? '—')); ?> · <strong><?php esc_html_e('Cursor:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(number_format_i18n(absint($state['cursor'] ?? 0))); ?></p>
                    <div class="sc-library-preservation-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_preservation_audit_start'); ?><input type="hidden" name="action" value="sc_library_preservation_audit_start"><button class="button button-primary"><?php esc_html_e('Start complete integrity audit', 'sustainable-catalyst-library'); ?></button></form>
                        <?php if (($state['status'] ?? '') === 'running') : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_preservation_audit_continue'); ?><input type="hidden" name="action" value="sc_library_preservation_audit_continue"><button class="button"><?php esc_html_e('Continue saved audit', 'sustainable-catalyst-library'); ?></button></form><?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_preservation_audit_reset'); ?><input type="hidden" name="action" value="sc_library_preservation_audit_reset"><button class="button"><?php esc_html_e('Reset audit state', 'sustainable-catalyst-library'); ?></button></form>
                    </div>
                </section>

                <section class="sc-library-preservation-panel">
                    <h2><?php esc_html_e('Freeze or verify one record', 'sustainable-catalyst-library'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-library-preservation-inline-form"><?php wp_nonce_field('sc_library_preservation_snapshot'); ?><input type="hidden" name="action" value="sc_library_preservation_snapshot"><label><?php esc_html_e('WordPress record ID', 'sustainable-catalyst-library'); ?><input type="number" name="record_id" min="1" required></label><button class="button button-primary"><?php esc_html_e('Create frozen snapshot', 'sustainable-catalyst-library'); ?></button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-library-preservation-inline-form"><?php wp_nonce_field('sc_library_preservation_verify'); ?><input type="hidden" name="action" value="sc_library_preservation_verify"><label><?php esc_html_e('WordPress record ID', 'sustainable-catalyst-library'); ?><input type="number" name="record_id" min="1" required></label><button class="button"><?php esc_html_e('Verify record integrity', 'sustainable-catalyst-library'); ?></button></form>
                </section>
            </div>

            <section class="sc-library-preservation-panel">
                <h2><?php esc_html_e('Preservation settings', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('sc_library_preservation_settings'); ?>
                    <table class="form-table"><tbody>
                    <tr><th><?php esc_html_e('Enable preservation system', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_enable_preservation" value="1" <?php checked(get_option('sc_library_enable_preservation', 1)); ?>> <?php esc_html_e('Enable snapshots, audits, archive shortcodes, and REST routes.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th><?php esc_html_e('Automatic snapshots', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_preservation_auto_snapshot" value="1" <?php checked(get_option('sc_library_preservation_auto_snapshot', 1)); ?>> <?php esc_html_e('Create a new snapshot only when a published canonical payload changes.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th><?php esc_html_e('Default retention', 'sustainable-catalyst-library'); ?></th><td><input type="number" min="1" max="100" name="sc_library_preservation_retention_years" value="<?php echo esc_attr((string) get_option('sc_library_preservation_retention_years', 10)); ?>"> <?php esc_html_e('years', 'sustainable-catalyst-library'); ?></td></tr>
                    <tr><th><?php esc_html_e('Audit batch size', 'sustainable-catalyst-library'); ?></th><td><input type="number" min="10" max="250" name="sc_library_preservation_batch_size" value="<?php echo esc_attr((string) get_option('sc_library_preservation_batch_size', 50)); ?>"></td></tr>
                    <tr><th><?php esc_html_e('External link checking', 'sustainable-catalyst-library'); ?></th><td><label><input type="checkbox" name="sc_library_integrity_check_external_links" value="1" <?php checked(get_option('sc_library_integrity_check_external_links', 0)); ?>> <?php esc_html_e('Audit up to 20 content links per record. This can be slow and should remain off until needed.', 'sustainable-catalyst-library'); ?></label></td></tr>
                    <tr><th><?php esc_html_e('Link timeout', 'sustainable-catalyst-library'); ?></th><td><input type="number" min="3" max="30" name="sc_library_integrity_link_timeout" value="<?php echo esc_attr((string) get_option('sc_library_integrity_link_timeout', 8)); ?>> <?php esc_html_e('seconds', 'sustainable-catalyst-library'); ?></td></tr>
                    <tr><th><?php esc_html_e('Public archive page URL', 'sustainable-catalyst-library'); ?></th><td><input type="url" class="regular-text" name="sc_library_archive_page_url" value="<?php echo esc_attr((string) get_option('sc_library_archive_page_url', home_url('/institutional-archive/'))); ?>"><p class="description"><code>[sc_library_institutional_archive]</code></p></td></tr>
                    </tbody></table>
                    <?php submit_button(); ?>
                </form>
            </section>

            <section class="sc-library-preservation-panel">
                <h2><?php esc_html_e('Recent frozen snapshots', 'sustainable-catalyst-library'); ?></h2>
                <div class="sc-library-preservation-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Record', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Version', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('State', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Checksum', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Created', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Actions', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                <?php if (!$recent_snapshots) : ?><tr><td colspan="6"><?php esc_html_e('No snapshots yet.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
                <?php foreach ($recent_snapshots as $row) : ?><tr><td><strong><?php echo esc_html((string) $row['title']); ?></strong><br><small>#<?php echo esc_html((string) $row['record_id']); ?></small></td><td><?php echo esc_html((string) $row['version_label']); ?></td><td><?php echo esc_html((string) $row['snapshot_status']); ?></td><td><code><?php echo esc_html(substr((string) $row['source_hash'], 0, 14)); ?>…</code></td><td><?php echo esc_html((string) $row['created_at']); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_preservation_manifest'); ?><input type="hidden" name="action" value="sc_library_preservation_manifest"><input type="hidden" name="snapshot_uuid" value="<?php echo esc_attr((string) $row['snapshot_uuid']); ?>"><button class="button button-small"><?php esc_html_e('Manifest', 'sustainable-catalyst-library'); ?></button></form></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>

            <section class="sc-library-preservation-panel">
                <h2><?php esc_html_e('Recent integrity warnings and failures', 'sustainable-catalyst-library'); ?></h2>
                <div class="sc-library-preservation-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Record', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Check', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Message', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Checked', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                <?php if (!$failures) : ?><tr><td colspan="5"><?php esc_html_e('No recent warnings or failures.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
                <?php foreach ($failures as $row) : ?><tr><td><span class="sc-library-preservation-status sc-library-preservation-status--<?php echo esc_attr((string) $row['status']); ?>"><?php echo esc_html((string) $row['status']); ?></span></td><td><?php echo esc_html((string) $row['record_id']); ?></td><td><?php echo esc_html((string) $row['check_type']); ?></td><td><?php echo esc_html((string) $row['message']); ?><?php if ($row['target_url']) : ?><br><small><?php echo esc_html((string) $row['target_url']); ?></small><?php endif; ?></td><td><?php echo esc_html((string) $row['checked_at']); ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>

            <section class="sc-library-preservation-panel sc-library-preservation-panel--danger">
                <h2><?php esc_html_e('Retention cleanup', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Only expired, non-current snapshots without a legal hold are eligible. Current snapshots and held records are always protected.', 'sustainable-catalyst-library'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_preservation_purge'); ?><input type="hidden" name="action" value="sc_library_preservation_purge"><label><input type="checkbox" name="confirm_purge" value="1" required> <?php esc_html_e('I understand that eligible historical snapshots will be permanently deleted.', 'sustainable-catalyst-library'); ?></label><p><button class="button"><?php esc_html_e('Purge expired unprotected snapshots', 'sustainable-catalyst-library'); ?></button></p></form>
            </section>
        </div>
        <?php
    }

    private function metric(string $label, int $value): void {
        echo '<div class="sc-library-preservation-metric"><strong>' . esc_html(number_format_i18n($value)) . '</strong><span>' . esc_html($label) . '</span></div>';
    }
}
