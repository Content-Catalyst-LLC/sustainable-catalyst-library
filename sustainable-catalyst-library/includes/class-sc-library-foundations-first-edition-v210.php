<?php
/**
 * Sustainable Catalyst Foundations v2.1.5
 *
 * Automatically creates the 13 Institutional Foundations First Edition
 * records as real, published WordPress HTML Foundation Documents. The earlier
 * manual import button is intentionally removed.
 */
if (!defined('ABSPATH')) { exit; }

final class SC_Library_Foundations_First_Edition_V210 {
    private static ?self $instance = null;

    private const RELEASE = '2.1.6';
    private const CONTENT_EDITION = '2.1.0';
    private const EXPECTED_COUNT = 13;
    private const IMPORT_REL = 'assets/foundations/v2.1.0/import/foundations-first-edition.json';

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    public function register_hooks(): void {
        // A plugin replacement does not necessarily fire a WordPress activation
        // hook. admin_init guarantees provisioning on the first admin request
        // after the replacement while the post type and taxonomies are loaded.
        add_action('admin_init', [$this, 'maybe_provision'], 25);
        add_action('admin_menu', [$this, 'admin_menu'], 90);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('admin_post_sc_foundations_v216_reprovision', [$this, 'handle_reprovision']);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('sc foundations-first-edition', [$this, 'cli_provision']);
        }
    }

    /**
     * Create or repair the collection automatically. No manual import step.
     */
    public function maybe_provision(): void {
        if (wp_installing() || !current_user_can('manage_options')) {
            return;
        }

        if (!post_type_exists('sc_foundation_doc')) {
            $this->store_result([
                'created' => 0,
                'updated' => 0,
                'attachments' => 0,
                'processed' => 0,
                'errors' => ['Foundation Document post type is not registered.'],
            ]);
            return;
        }

        $installed = (string) get_option('sc_library_foundations_first_edition_version', '');
        if (
            $installed === self::RELEASE
            && $this->count_provisioned() === self::EXPECTED_COUNT
            && $this->count_catalog_ready() === self::EXPECTED_COUNT
        ) {
            return;
        }

        $result = $this->provision_all();
        $this->store_result($result);

        if (empty($result['errors']) && (int) $result['processed'] === self::EXPECTED_COUNT) {
            update_option('sc_library_foundations_first_edition_version', self::RELEASE, false);
            update_option('sc_library_foundations_first_edition_content_version', self::CONTENT_EDITION, false);
            set_transient('sc_library_foundations_first_edition_success_notice', $result, 300);
        }
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            'Institutional Foundations First Edition',
            'First Edition Status',
            'manage_options',
            'sc-foundations-first-edition',
            [$this, 'render_page']
        );

        add_management_page(
            'Institutional Foundations First Edition',
            'Foundations First Edition',
            'manage_options',
            'sc-foundations-first-edition-tools',
            [$this, 'render_page']
        );
    }

    public function admin_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $success = get_transient('sc_library_foundations_first_edition_success_notice');
        if (is_array($success)) {
            delete_transient('sc_library_foundations_first_edition_success_notice');
            $list_url = admin_url('edit.php?post_type=sc_foundation_doc');
            echo '<div class="notice notice-success is-dismissible"><p><strong>Institutional Foundations First Edition is live.</strong> ';
            echo esc_html(sprintf('%d HTML Foundation Documents were processed.', (int) $success['processed']));
            echo ' <a class="button button-primary" href="' . esc_url($list_url) . '">View Foundation Documents</a></p></div>';
            return;
        }

        $result = get_option('sc_library_foundations_first_edition_last_result', []);
        if (is_array($result) && !empty($result['errors'])) {
            $status_url = admin_url('admin.php?page=sc-foundations-first-edition');
            echo '<div class="notice notice-error"><p><strong>Foundation Document provisioning needs attention.</strong> ';
            echo esc_html(implode(' ', array_map('strval', (array) $result['errors'])));
            echo ' <a class="button" href="' . esc_url($status_url) . '">Open First Edition Status</a></p></div>';
        }
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $count = $this->count_provisioned();
        $catalog_ready = $this->count_catalog_ready();
        $result = get_option('sc_library_foundations_first_edition_last_result', []);
        $complete = $count === self::EXPECTED_COUNT && $catalog_ready === self::EXPECTED_COUNT;

        echo '<div class="wrap"><h1>Institutional Foundations First Edition</h1>';
        echo '<p>The collection is provisioned automatically as published HTML Foundation Documents. No import is required.</p>';
        echo '<div class="notice ' . ($complete ? 'notice-success' : 'notice-warning') . ' inline"><p>';
        echo '<strong>' . esc_html(sprintf('%d of %d records are present.', $count, self::EXPECTED_COUNT)) . '</strong><br>';
        echo esc_html(sprintf('%d of %d are assigned to the Foundations family and Foundation Document type.', $catalog_ready, self::EXPECTED_COUNT));
        echo '</p></div>';

        if (is_array($result) && $result) {
            echo '<h2>Last provisioning result</h2><ul>';
            echo '<li>Created: ' . esc_html((string) ($result['created'] ?? 0)) . '</li>';
            echo '<li>Updated: ' . esc_html((string) ($result['updated'] ?? 0)) . '</li>';
            echo '<li>PDFs attached: ' . esc_html((string) ($result['attachments'] ?? 0)) . '</li>';
            echo '<li>Processed: ' . esc_html((string) ($result['processed'] ?? 0)) . '</li>';
            echo '<li>Errors: ' . esc_html((string) count((array) ($result['errors'] ?? []))) . '</li>';
            echo '</ul>';
            if (!empty($result['errors'])) {
                echo '<h3>Errors</h3><ul>';
                foreach ((array) $result['errors'] as $error) {
                    echo '<li><code>' . esc_html((string) $error) . '</code></li>';
                }
                echo '</ul>';
            }
        }

        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=sc_foundation_doc')) . '">View Foundation Documents</a> ';
        echo '<a class="button" href="' . esc_url(home_url('/institution/foundations/')) . '" target="_blank" rel="noopener">View Foundations Page</a></p>';

        echo '<hr><h2>Repair or refresh</h2><p>Re-provisioning is idempotent. It updates records by stable document ID and does not create duplicates.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sc_foundations_v216_reprovision');
        echo '<input type="hidden" name="action" value="sc_foundations_v216_reprovision">';
        submit_button('Re-provision all 13 HTML documents', 'secondary');
        echo '</form></div>';
    }

    public function handle_reprovision(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permission.');
        }
        check_admin_referer('sc_foundations_v216_reprovision');

        delete_option('sc_library_foundations_first_edition_version');
        $result = $this->provision_all();
        $this->store_result($result);

        if (empty($result['errors']) && (int) $result['processed'] === self::EXPECTED_COUNT) {
            update_option('sc_library_foundations_first_edition_version', self::RELEASE, false);
            update_option('sc_library_foundations_first_edition_content_version', self::CONTENT_EDITION, false);
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-foundations-first-edition'));
        exit;
    }

    public function cli_provision(array $args, array $assoc_args): void {
        $result = $this->provision_all();
        $this->store_result($result);
        if (!empty($result['errors'])) {
            WP_CLI::error(implode(' | ', (array) $result['errors']));
        }
        update_option('sc_library_foundations_first_edition_version', self::RELEASE, false);
        update_option('sc_library_foundations_first_edition_content_version', self::CONTENT_EDITION, false);
        WP_CLI::success(sprintf(
            'Processed %d records: %d created, %d updated, %d PDFs attached.',
            (int) $result['processed'],
            (int) $result['created'],
            (int) $result['updated'],
            (int) $result['attachments']
        ));
    }

    /**
     * @return array{created:int,updated:int,attachments:int,processed:int,errors:array<int,string>}
     */
    private function provision_all(): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'attachments' => 0,
            'processed' => 0,
            'errors' => [],
        ];

        $path = trailingslashit(SC_LIBRARY_DIR) . self::IMPORT_REL;
        if (!is_readable($path)) {
            $result['errors'][] = 'First Edition payload is missing: ' . $path;
            return $result;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload) || !isset($payload['documents']) || !is_array($payload['documents'])) {
            $result['errors'][] = 'First Edition payload is invalid JSON.';
            return $result;
        }
        if (count($payload['documents']) !== self::EXPECTED_COUNT) {
            $result['errors'][] = sprintf('Expected %d documents; payload contains %d.', self::EXPECTED_COUNT, count($payload['documents']));
            return $result;
        }

        $id_map = [];

        foreach ($payload['documents'] as $record) {
            $doc_id = sanitize_text_field((string) ($record['document_id'] ?? ''));
            if ($doc_id === '') {
                $result['errors'][] = 'A payload record is missing document_id.';
                continue;
            }

            $existing = $this->find_by_document_id($doc_id);
            $content = (string) ($record['content_html'] ?? '');
            if (trim(wp_strip_all_tags($content)) === '') {
                $result['errors'][] = $doc_id . ': HTML content is empty.';
                continue;
            }

            $postarr = [
                'post_type' => 'sc_foundation_doc',
                // Create/update the HTML body as a draft first. The retained PDF
                // publication guards run during wp_insert_post_data and cannot
                // inspect post meta until the record has an ID.
                'post_status' => 'draft',
                'post_title' => sanitize_text_field((string) ($record['title'] ?? $doc_id)),
                'post_name' => sanitize_title((string) ($record['slug'] ?? $doc_id)),
                'post_excerpt' => sanitize_text_field((string) ($record['excerpt'] ?? '')),
                'post_content' => wp_kses_post($content),
                'menu_order' => absint($record['menu_order'] ?? 0),
            ];
            if ($existing > 0) {
                $postarr['ID'] = $existing;
            }

            $post_id = wp_insert_post(wp_slash($postarr), true);
            if (is_wp_error($post_id)) {
                $result['errors'][] = $doc_id . ': ' . $post_id->get_error_message();
                continue;
            }

            $post_id = (int) $post_id;
            $existing > 0 ? $result['updated']++ : $result['created']++;
            $result['processed']++;
            $id_map[$doc_id] = $post_id;

            $this->update_meta($post_id, $record);
            $this->assign_taxonomies($post_id);

            $attachment_result = $this->attach_pdf($post_id, $record);
            if ($attachment_result === true) {
                $result['attachments']++;
            } elseif (is_string($attachment_result) && $attachment_result !== '') {
                // The HTML record remains authoritative even when its optional
                // fixed PDF snapshot needs repair.
                $result['errors'][] = $doc_id . ': ' . $attachment_result;
            }

            $published = wp_update_post(wp_slash([
                'ID' => $post_id,
                'post_status' => 'publish',
            ]), true);
            if (is_wp_error($published)) {
                $result['errors'][] = $doc_id . ': publication failed: ' . $published->get_error_message();
            } elseif ('publish' !== get_post_status($post_id)) {
                $result['errors'][] = $doc_id . ': publication guard left the HTML record as ' . (string) get_post_status($post_id) . '.';
            }
        }

        // Relationships are resolved after all stable IDs have WordPress IDs.
        foreach ($payload['documents'] as $record) {
            $doc_id = (string) ($record['document_id'] ?? '');
            if (empty($id_map[$doc_id])) {
                continue;
            }
            $related = [];
            foreach ((array) ($record['related_documents'] ?? []) as $related_id) {
                if (!empty($id_map[$related_id])) {
                    $related[] = (int) $id_map[$related_id];
                }
            }
            update_post_meta(
                (int) $id_map[$doc_id],
                '_sc_foundation_related_ids',
                implode(',', array_values(array_unique($related)))
            );
        }

        flush_rewrite_rules(false);

        return $result;
    }

    private function find_by_document_id(string $doc_id): int {
        $query = new WP_Query([
            'post_type' => 'sc_foundation_doc',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_sc_foundation_document_id',
            'meta_value' => $doc_id,
            'no_found_rows' => true,
            'cache_results' => false,
        ]);

        return !empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    private function count_provisioned(): int {
        $query = new WP_Query([
            'post_type' => 'sc_foundation_doc',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => self::EXPECTED_COUNT + 10,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_sc_library_foundations_content_edition',
                'value' => self::CONTENT_EDITION,
            ]],
            'no_found_rows' => true,
            'cache_results' => false,
        ]);

        return count($query->posts);
    }

    private function count_catalog_ready(): int {
        if (!taxonomy_exists('sc_document_family') || !taxonomy_exists('sc_document_type')) {
            return 0;
        }

        $query = new WP_Query([
            'post_type' => 'sc_foundation_doc',
            'post_status' => 'publish',
            'posts_per_page' => self::EXPECTED_COUNT + 10,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_sc_library_foundations_content_edition',
                'value' => self::CONTENT_EDITION,
            ]],
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'sc_document_family',
                    'field' => 'slug',
                    'terms' => ['foundations'],
                ],
                [
                    'taxonomy' => 'sc_document_type',
                    'field' => 'slug',
                    'terms' => ['foundation-document'],
                ],
            ],
            'no_found_rows' => true,
            'cache_results' => false,
        ]);

        return count($query->posts);
    }

    private function update_meta(int $post_id, array $record): void {
        $text_fields = [
            'document_id', 'subtitle', 'record_type', 'authority_level', 'status',
            'effective_date', 'last_reviewed', 'review_cycle', 'owner',
            'canonical_record', 'correction_url',
        ];

        foreach ($text_fields as $key) {
            update_post_meta(
                $post_id,
                '_sc_foundation_' . $key,
                sanitize_text_field((string) ($record[$key] ?? ''))
            );
        }

        $version = sanitize_text_field((string) ($record['version'] ?? '1.0.0'));
        update_post_meta($post_id, '_sc_foundation_version', $version);
        update_post_meta($post_id, '_sc_foundation_publication_date', sanitize_text_field((string) ($record['effective_date'] ?? '')));
        update_post_meta($post_id, '_sc_foundation_author', 'Sustainable Catalyst');
        update_post_meta($post_id, '_sc_foundation_publisher', 'Sustainable Catalyst');
        update_post_meta($post_id, '_sc_foundation_language', 'en');
        update_post_meta($post_id, '_sc_foundation_allow_download', 1);
        update_post_meta($post_id, '_sc_foundation_show_viewer', 1);
        update_post_meta($post_id, '_sc_foundation_show_toc', 1);
        update_post_meta($post_id, '_sc_foundation_revision_history', wp_json_encode((array) ($record['revision_history'] ?? [])));
        update_post_meta($post_id, '_sc_foundation_related_product_slugs', implode(',', array_map('sanitize_title', (array) ($record['related_products'] ?? []))));
        update_post_meta($post_id, '_sc_foundation_related_repository_urls', implode("\n", array_map('esc_url_raw', (array) ($record['related_repositories'] ?? []))));
        update_post_meta($post_id, '_sc_foundation_supersedes_labels', implode("\n", array_map('sanitize_text_field', (array) ($record['supersedes'] ?? []))));

        // Keep the retained Documentation Library authority model synchronized.
        update_post_meta($post_id, '_sc_library_doc_status', 'current');
        update_post_meta($post_id, '_sc_library_doc_type', 'institution');
        update_post_meta($post_id, '_sc_library_doc_version', $version);
        update_post_meta($post_id, '_sc_library_doc_authority_type', 'html');
        update_post_meta($post_id, '_sc_library_doc_authority_url', esc_url_raw(get_permalink($post_id)));
        update_post_meta($post_id, '_sc_library_doc_webpage_url', esc_url_raw(get_permalink($post_id)));
        update_post_meta($post_id, '_sc_library_foundations_system_version', self::RELEASE);
        update_post_meta($post_id, '_sc_library_foundations_content_edition', self::CONTENT_EDITION);

        // Synchronize metadata used by the current public Document Repository.
        update_post_meta($post_id, '_sc_document_version', $version);
        update_post_meta($post_id, '_sc_document_publication_date', sanitize_text_field((string) ($record['effective_date'] ?? '')));
        update_post_meta($post_id, '_sc_document_lifecycle_status', 'current');
        update_post_meta($post_id, '_sc_document_repository_order', absint($record['menu_order'] ?? 0));

        // Native HTML records are authored directly in WordPress. Their PDF is
        // a fixed snapshot and must never trigger PDF-to-HTML conversion gates.
        update_post_meta($post_id, '_sc_foundation_source_mode', 'native_html');
        update_post_meta($post_id, '_sc_library_foundation_source_mode', 'native_html');
        update_post_meta($post_id, '_sc_document_extraction_status', 'legacy_content');
        update_post_meta($post_id, '_sc_document_extraction_method', 'native_html');
        update_post_meta($post_id, '_sc_document_reviewed', 1);
    }

    private function assign_taxonomies(int $post_id): void {
        if (class_exists('SC_Library_Taxonomies')) {
            $collection_tax = SC_Library_Taxonomies::COLLECTION;
            if (taxonomy_exists($collection_tax)) {
                $slug = class_exists('SC_Library_Documentation')
                    ? SC_Library_Documentation::COLLECTION_SLUG
                    : 'foundations';
                $term_id = $this->ensure_term($collection_tax, $slug, 'Foundations');
                if ($term_id > 0) {
                    wp_set_object_terms($post_id, [$term_id], $collection_tax, false);
                }
            }

            if (defined('SC_Library_Taxonomies::DOCUMENT_CATEGORY')) {
                $category_tax = SC_Library_Taxonomies::DOCUMENT_CATEGORY;
                if (taxonomy_exists($category_tax)) {
                    $term_id = $this->ensure_term($category_tax, 'institutional-foundations', 'Institutional Foundations');
                    if ($term_id > 0) {
                        wp_set_object_terms($post_id, [$term_id], $category_tax, false);
                    }
                }
            }
        }

        // The current public repository maps the Foundations shortcode to these
        // two taxonomies. Both assignments are required for live visibility.
        foreach ([
            'sc_document_family' => ['foundations', 'Foundations'],
            'sc_document_type' => ['foundation-document', 'Foundation Document'],
        ] as $taxonomy => $definition) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            register_taxonomy_for_object_type($taxonomy, 'sc_foundation_doc');
            $term_id = $this->ensure_term($taxonomy, $definition[0], $definition[1]);
            if ($term_id > 0) {
                wp_set_object_terms($post_id, [$term_id], $taxonomy, false);
            }
        }

        clean_object_term_cache($post_id, 'sc_foundation_doc');
        clean_post_cache($post_id);
    }

    private function ensure_term(string $taxonomy, string $slug, string $name): int {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term instanceof WP_Term) {
            return (int) $term->term_id;
        }
        $term = get_term_by('name', $name, $taxonomy);
        if ($term instanceof WP_Term) {
            return (int) $term->term_id;
        }
        $created = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
        return is_wp_error($created) ? 0 : absint($created['term_id'] ?? 0);
    }

    /**
     * @return bool|string True when attached, false when already current, or an error message.
     */
    private function attach_pdf(int $post_id, array $record) {
        $expected_hash = sanitize_text_field((string) ($record['pdf_sha256'] ?? ''));
        $existing = absint(get_post_meta($post_id, '_sc_foundation_pdf_attachment_id', true));
        if ($existing > 0 && $expected_hash !== '' && get_post_meta($existing, '_sc_foundation_asset_sha256', true) === $expected_hash) {
            $this->synchronize_pdf_meta($post_id, $existing);
            return false;
        }

        $relative = ltrim((string) ($record['plugin_pdf_file'] ?? ''), '/');
        $source = trailingslashit(SC_LIBRARY_DIR) . $relative;
        if (!is_readable($source)) {
            return 'PDF snapshot is missing from the plugin.';
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            return 'PDF snapshot could not be read.';
        }

        $bits = wp_upload_bits(basename($source), null, $contents);
        if (!empty($bits['error'])) {
            return 'WordPress upload failed: ' . (string) $bits['error'];
        }

        $attachment = wp_insert_attachment([
            'post_mime_type' => 'application/pdf',
            'post_title' => sanitize_text_field((string) ($record['title'] ?? '') . ' - Version ' . (string) ($record['version'] ?? '1.0.0')),
            'post_status' => 'inherit',
        ], $bits['file'], $post_id, true);

        if (is_wp_error($attachment)) {
            return 'PDF attachment failed: ' . $attachment->get_error_message();
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $bits['file']));
        update_post_meta($attachment, '_sc_foundation_asset_sha256', $expected_hash);
        $this->synchronize_pdf_meta($post_id, (int) $attachment);
        update_post_meta($post_id, '_sc_library_doc_pdf_url', esc_url_raw((string) wp_get_attachment_url($attachment)));

        return true;
    }

    private function synchronize_pdf_meta(int $post_id, int $attachment_id): void {
        foreach ([
            '_sc_library_foundation_page_pdf_id',
            '_sc_library_pdf_attachment_id',
            '_sc_library_foundation_pdf_attachment_id',
            '_sc_library_foundation_attachment_id',
            '_sc_foundation_pdf_attachment_id',
            'sc_library_pdf_attachment_id',
        ] as $meta_key) {
            update_post_meta($post_id, $meta_key, $attachment_id);
        }
    }

    private function store_result(array $result): void {
        $result['record_count'] = $this->count_provisioned();
        $result['catalog_ready_count'] = $this->count_catalog_ready();
        $result['timestamp'] = current_time('mysql', true);
        update_option('sc_library_foundations_first_edition_last_result', $result, false);
    }
}

SC_Library_Foundations_First_Edition_V210::instance()->register_hooks();
