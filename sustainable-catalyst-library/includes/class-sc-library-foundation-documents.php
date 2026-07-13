<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Embedded Foundation Document records and page-aware PDF indexing.
 *
 * WordPress posts remain canonical. PDF binaries stay in the Media Library;
 * extracted page text is stored separately so search, citations, and the
 * Research Librarian can use it without rewriting the source PDF.
 */
final class SC_Library_Foundation_Documents {
    public const POST_TYPE = 'sc_foundation_doc';
    public const SCHEMA = 'sc-library-foundation-document/1.0';
    public const EXTRACTION_SCHEMA = 'sc-library-pdf-extraction/1.0';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('init', [$this, 'register_post_type'], 8);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save'], 20, 3);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_filter('rest_pre_serve_request', [$this, 'serve_citation_download'], 10, 4);
        add_action('admin_menu', [$this, 'admin_menu'], 25);
        add_action('admin_post_sc_library_foundation_migrate', [$this, 'handle_migration']);
        add_filter('the_content', [$this, 'filter_content']);
        add_filter('sc_library_scanner_recommended_post_types', [$this, 'recommended_post_types']);
        add_shortcode('sc_foundation_document', [$this, 'shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_foundation_documents', 1);
    }

    public static function pages_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_pdf_pages';
    }

    public static function versions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_foundation_versions';
    }

    public function recommended_post_types(array $types): array {
        if (self::enabled()) {
            $types[] = self::POST_TYPE;
        }
        return array_values(array_unique($types));
    }

    public function register_post_type(): void {
        $labels = [
            'name' => __('Foundation Documents', 'sustainable-catalyst-library'),
            'singular_name' => __('Foundation Document', 'sustainable-catalyst-library'),
            'add_new_item' => __('Add Foundation Document', 'sustainable-catalyst-library'),
            'edit_item' => __('Edit Foundation Document', 'sustainable-catalyst-library'),
            'new_item' => __('New Foundation Document', 'sustainable-catalyst-library'),
            'view_item' => __('View Foundation Document', 'sustainable-catalyst-library'),
            'search_items' => __('Search Foundation Documents', 'sustainable-catalyst-library'),
            'not_found' => __('No Foundation Documents found.', 'sustainable-catalyst-library'),
        ];
        register_post_type(self::POST_TYPE, [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'sc-library',
            'show_in_rest' => true,
            'has_archive' => 'foundation-documents',
            'rewrite' => ['slug' => 'foundation-documents', 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions'],
            'taxonomies' => ['category', 'post_tag', SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::SERIES],
            'menu_icon' => 'dashicons-pdf',
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function add_meta_boxes(): void {
        add_meta_box('sc-foundation-pdf', __('Embedded PDF and extraction', 'sustainable-catalyst-library'), [$this, 'render_pdf_box'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('sc-foundation-metadata', __('Document metadata, citation, and version', 'sustainable-catalyst-library'), [$this, 'render_metadata_box'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('sc-foundation-diagnostics', __('Extraction diagnostics', 'sustainable-catalyst-library'), [$this, 'render_diagnostics_box'], self::POST_TYPE, 'side', 'default');
    }

    public function render_pdf_box(WP_Post $post): void {
        wp_nonce_field('sc_library_save_foundation_document', 'sc_library_foundation_document_nonce');
        $attachment_id = absint(get_post_meta($post->ID, '_sc_foundation_pdf_attachment_id', true));
        $url = $attachment_id ? wp_get_attachment_url($attachment_id) : (string) get_post_meta($post->ID, '_sc_foundation_external_pdf_url', true);
        ?>
        <div class="sc-foundation-admin" data-foundation-document-admin data-post-id="<?php echo esc_attr($post->ID); ?>">
            <input type="hidden" name="sc_foundation_pdf_attachment_id" value="<?php echo esc_attr($attachment_id); ?>" data-pdf-attachment-id>
            <p><button type="button" class="button button-secondary" data-select-pdf><?php esc_html_e('Select PDF from Media Library', 'sustainable-catalyst-library'); ?></button>
            <button type="button" class="button" data-clear-pdf><?php esc_html_e('Clear PDF', 'sustainable-catalyst-library'); ?></button></p>
            <p class="sc-foundation-admin__selection" data-pdf-selection><?php echo $url ? esc_html(basename((string) wp_parse_url($url, PHP_URL_PATH))) : esc_html__('No PDF selected.', 'sustainable-catalyst-library'); ?></p>
            <p><label><input type="checkbox" name="sc_foundation_allow_download" value="1" <?php checked(metadata_exists('post', $post->ID, '_sc_foundation_allow_download') ? (int) get_post_meta($post->ID, '_sc_foundation_allow_download', true) : 1, 1); ?>> <?php esc_html_e('Show explicit Download PDF control', 'sustainable-catalyst-library'); ?></label></p>
            <p><label><input type="checkbox" name="sc_foundation_show_viewer" value="1" <?php checked(metadata_exists('post', $post->ID, '_sc_foundation_show_viewer') ? (int) get_post_meta($post->ID, '_sc_foundation_show_viewer', true) : 1, 1); ?>> <?php esc_html_e('Show inline PDF.js viewer', 'sustainable-catalyst-library'); ?></label></p>
            <p class="description"><?php esc_html_e('Save the record after selecting a PDF. Then run extraction to index every page. Extraction happens in your browser with the bundled PDF.js library, so no external service or PDF upload is required.', 'sustainable-catalyst-library'); ?></p>
            <p><button type="button" class="button button-primary" data-extract-pdf <?php disabled(!$attachment_id); ?>><?php esc_html_e('Extract and index full PDF text', 'sustainable-catalyst-library'); ?></button>
            <button type="button" class="button" data-retry-extraction <?php disabled(!$attachment_id); ?>><?php esc_html_e('Retry extraction', 'sustainable-catalyst-library'); ?></button></p>
            <div class="sc-foundation-admin__progress" data-extraction-progress hidden><progress max="100" value="0"></progress><span></span></div>
            <div class="notice inline" data-extraction-message hidden><p></p></div>
        </div>
        <?php
    }

    public function render_metadata_box(WP_Post $post): void {
        $fields = self::metadata($post->ID);
        ?>
        <div class="sc-library-admin-grid sc-foundation-metadata">
            <label><span><?php esc_html_e('Document version', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_version" value="<?php echo esc_attr($fields['version']); ?>" placeholder="1.0 or July 2026"></label>
            <label><span><?php esc_html_e('Publication date', 'sustainable-catalyst-library'); ?></span><input type="date" name="sc_foundation_publication_date" value="<?php echo esc_attr($fields['publication_date']); ?>"></label>
            <label><span><?php esc_html_e('Author or institution', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_author" value="<?php echo esc_attr($fields['author']); ?>"></label>
            <label><span><?php esc_html_e('Publisher', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_publisher" value="<?php echo esc_attr($fields['publisher']); ?>"></label>
            <label><span><?php esc_html_e('DOI', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_doi" value="<?php echo esc_attr($fields['doi']); ?>"></label>
            <label><span><?php esc_html_e('Language', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_language" value="<?php echo esc_attr($fields['language']); ?>" placeholder="en"></label>
            <label class="sc-library-admin-grid__wide"><span><?php esc_html_e('Related WordPress record IDs', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_foundation_related_ids" value="<?php echo esc_attr(implode(', ', $fields['related_ids'])); ?>" placeholder="123, 456"></label>
            <label class="sc-library-admin-grid__wide"><span><?php esc_html_e('Citation note', 'sustainable-catalyst-library'); ?></span><textarea name="sc_foundation_citation_note" rows="3"><?php echo esc_textarea($fields['citation_note']); ?></textarea></label>
        </div>
        <?php $versions = self::version_history($post->ID, 10); ?>
        <?php if ($versions) : ?>
            <h4><?php esc_html_e('Version history', 'sustainable-catalyst-library'); ?></h4>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Version', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('PDF', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Pages', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Recorded', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
            <?php foreach ($versions as $version) : ?><tr><td><?php echo esc_html($version['version_label']); ?></td><td><?php echo esc_html($version['filename']); ?></td><td><?php echo esc_html(number_format_i18n((int) $version['page_count'])); ?></td><td><?php echo esc_html($version['created_at']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif;
    }

    public function render_diagnostics_box(WP_Post $post): void {
        $status = self::extraction_status($post->ID);
        ?>
        <p><strong><?php esc_html_e('Status:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $status['status']))); ?></p>
        <p><strong><?php esc_html_e('Pages:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(number_format_i18n($status['page_count'])); ?></p>
        <p><strong><?php esc_html_e('Characters:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html(number_format_i18n($status['character_count'])); ?></p>
        <p><strong><?php esc_html_e('Last extraction:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html($status['extracted_at'] ?: '—'); ?></p>
        <?php if ($status['error']) : ?><p class="notice notice-error inline"><span><?php echo esc_html($status['error']); ?></span></p><?php endif; ?>
        <p><a href="<?php echo esc_url(rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $post->ID . '/citation?format=bibtex')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Export BibTeX citation', 'sustainable-catalyst-library'); ?></a></p>
        <?php
    }

    public function save(int $post_id, WP_Post $post, bool $update): void {
        if (!isset($_POST['sc_library_foundation_document_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_foundation_document_nonce'])), 'sc_library_save_foundation_document')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $previous_attachment = absint(get_post_meta($post_id, '_sc_foundation_pdf_attachment_id', true));
        $attachment_id = absint($_POST['sc_foundation_pdf_attachment_id'] ?? 0);
        if ($attachment_id && get_post_mime_type($attachment_id) !== 'application/pdf') $attachment_id = 0;
        $values = [
            '_sc_foundation_pdf_attachment_id' => $attachment_id,
            '_sc_foundation_allow_download' => isset($_POST['sc_foundation_allow_download']) ? 1 : 0,
            '_sc_foundation_show_viewer' => isset($_POST['sc_foundation_show_viewer']) ? 1 : 0,
            '_sc_foundation_version' => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_version'] ?? ''))),
            '_sc_foundation_publication_date' => self::sanitize_date((string) ($_POST['sc_foundation_publication_date'] ?? '')),
            '_sc_foundation_author' => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_author'] ?? ''))),
            '_sc_foundation_publisher' => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_publisher'] ?? ''))),
            '_sc_foundation_doi' => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_doi'] ?? ''))),
            '_sc_foundation_language' => sanitize_key((string) ($_POST['sc_foundation_language'] ?? 'en')),
            '_sc_foundation_related_ids' => implode(',', self::sanitize_ids((string) ($_POST['sc_foundation_related_ids'] ?? ''))),
            '_sc_foundation_citation_note' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_foundation_citation_note'] ?? ''))),
        ];
        foreach ($values as $key => $value) update_post_meta($post_id, $key, $value);

        // Keep the v1.8 Documentation Library authority model synchronized.
        wp_set_object_terms($post_id, [SC_Library_Documentation::COLLECTION_SLUG], SC_Library_Taxonomies::COLLECTION, false);
        $doc_categories = wp_get_post_terms($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['fields' => 'ids']);
        if (is_wp_error($doc_categories) || !$doc_categories) {
            wp_set_object_terms($post_id, ['institutional-foundations'], SC_Library_Taxonomies::DOCUMENT_CATEGORY, false);
        }
        $pdf_url = $attachment_id ? (string) wp_get_attachment_url($attachment_id) : (string) get_post_meta($post_id, '_sc_foundation_external_pdf_url', true);
        update_post_meta($post_id, '_sc_library_doc_status', 'current');
        update_post_meta($post_id, '_sc_library_doc_type', 'institution');
        update_post_meta($post_id, '_sc_library_doc_version', $values['_sc_foundation_version']);
        update_post_meta($post_id, '_sc_library_doc_authority_type', 'pdf');
        update_post_meta($post_id, '_sc_library_doc_pdf_url', esc_url_raw($pdf_url));
        update_post_meta($post_id, '_sc_library_doc_authority_url', esc_url_raw($pdf_url));

        if ($attachment_id !== $previous_attachment) {
            self::clear_pages($post_id);
            update_post_meta($post_id, '_sc_foundation_extraction_status', $attachment_id ? 'pending' : 'not_configured');
            delete_post_meta($post_id, '_sc_foundation_extraction_error');
            $this->record_version($post_id, $attachment_id);
        }
        $this->sync_relationships($post_id, $values['_sc_foundation_related_ids']);
        if ($post->post_status === 'publish') $this->indexer->index_post($post_id, $this->indexer->configured_post_types());
    }

    private function sync_relationships(int $post_id, string $ids): void {
        $existing = $this->relationships->get_for_post($post_id, false);
        $keep = [];
        foreach ($existing as $relationship) {
            if (($relationship['type'] ?? '') !== 'documents') {
                $keep[] = [
                    'target_post_id' => (int) $relationship['target_post_id'],
                    'relationship_type' => (string) $relationship['type'],
                    'note' => (string) $relationship['note'],
                    'confidence' => (float) $relationship['confidence'],
                    'confidence_basis' => (string) $relationship['confidence_basis'],
                    'provenance_type' => (string) $relationship['provenance_type'],
                    'provenance_url' => (string) $relationship['provenance_url'],
                    'evidence_note' => (string) $relationship['evidence_note'],
                    'visibility' => (string) $relationship['visibility'],
                ];
            }
        }
        foreach (self::sanitize_ids($ids) as $target_id) {
            if ($target_id === $post_id || get_post_status($target_id) !== 'publish') continue;
            $keep[] = ['target_post_id' => $target_id, 'relationship_type' => 'documents', 'note' => __('Connected Foundation Document', 'sustainable-catalyst-library'), 'confidence' => 1, 'confidence_basis' => 'declared', 'provenance_type' => 'post_metadata', 'visibility' => 'public'];
        }
        $this->relationships->save_for_post($post_id, $keep);
    }

    private function record_version(int $post_id, int $attachment_id): void {
        if ($attachment_id < 1) return;
        global $wpdb;
        $url = (string) wp_get_attachment_url($attachment_id);
        $path = get_attached_file($attachment_id);
        $sha = $path && is_readable($path) ? hash_file('sha256', $path) : '';
        $wpdb->insert(self::versions_table(), [
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
            'version_label' => (string) get_post_meta($post_id, '_sc_foundation_version', true),
            'filename' => basename((string) wp_parse_url($url, PHP_URL_PATH)),
            'pdf_url' => $url,
            'sha256' => $sha ?: '',
            'page_count' => 0,
            'metadata_json' => wp_json_encode(self::metadata($post_id)),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s','%s','%s','%d','%s','%d','%s']);
    }

    public function admin_assets(string $hook): void {
        $screen = get_current_screen();
        if (!$screen || ($screen->post_type !== self::POST_TYPE && $screen->id !== 'sc-library_page_sc-library-pdf-migration')) return;
        wp_enqueue_media();
        wp_enqueue_style('sc-library-foundation-documents', SC_LIBRARY_URL . 'assets/css/sc-library-foundation-documents.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-pdfjs', SC_LIBRARY_URL . 'assets/vendor/pdfjs/pdf.min.js', [], '3.11.174', true);
        wp_enqueue_script('sc-library-foundation-admin', SC_LIBRARY_URL . 'assets/js/sc-library-foundation-admin.js', ['sc-library-pdfjs'], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-foundation-admin', 'SCLibraryFoundationAdmin', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library/foundation-documents/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'worker' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
            'strings' => ['extracting' => __('Extracting page %1$d of %2$d…', 'sustainable-catalyst-library'), 'complete' => __('Extraction complete. The Library index and Research Librarian are synchronized.', 'sustainable-catalyst-library')],
        ]);
    }

    public function public_assets(): void {
        if (!self::enabled()) return;
        if (is_singular(self::POST_TYPE) || has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'sc_foundation_document')) {
            wp_enqueue_style('sc-library-foundation-documents', SC_LIBRARY_URL . 'assets/css/sc-library-foundation-documents.css', [], SC_LIBRARY_VERSION);
            wp_enqueue_script('sc-library-pdfjs', SC_LIBRARY_URL . 'assets/vendor/pdfjs/pdf.min.js', [], '3.11.174', true);
            wp_enqueue_script('sc-library-foundation-viewer', SC_LIBRARY_URL . 'assets/js/sc-library-foundation-viewer.js', ['sc-library-pdfjs'], SC_LIBRARY_VERSION, true);
            wp_localize_script('sc-library-foundation-viewer', 'SCLibraryFoundationViewer', ['worker' => SC_LIBRARY_URL . 'assets/vendor/pdfjs/pdf.worker.min.js']);
        }
    }

    public function filter_content(string $content): string {
        if (!is_singular(self::POST_TYPE) || !in_the_loop() || !is_main_query()) return $content;
        return $this->render_document(get_the_ID(), $content);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts, 'sc_foundation_document');
        return $this->render_document(absint($atts['id']), '');
    }

    private function render_document(int $post_id, string $body): string {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') return '';
        $attachment_id = absint(get_post_meta($post_id, '_sc_foundation_pdf_attachment_id', true));
        $url = $attachment_id ? wp_get_attachment_url($attachment_id) : (string) get_post_meta($post_id, '_sc_foundation_external_pdf_url', true);
        $meta = self::metadata($post_id);
        $status = self::extraction_status($post_id);
        ob_start();
        ?>
        <article class="sc-foundation-document" data-foundation-viewer data-pdf-url="<?php echo esc_url($url ?: ''); ?>">
            <header class="sc-foundation-document__header">
                <p class="sc-foundation-document__eyebrow"><?php esc_html_e('Foundation Document', 'sustainable-catalyst-library'); ?></p>
                <div class="sc-foundation-document__meta">
                    <?php if ($meta['version']) : ?><span><?php echo esc_html(sprintf(__('Version %s', 'sustainable-catalyst-library'), $meta['version'])); ?></span><?php endif; ?>
                    <?php if ($meta['publication_date']) : ?><time datetime="<?php echo esc_attr($meta['publication_date']); ?>"><?php echo esc_html($meta['publication_date']); ?></time><?php endif; ?>
                    <?php if ($status['page_count']) : ?><span><?php echo esc_html(sprintf(_n('%s page', '%s pages', $status['page_count'], 'sustainable-catalyst-library'), number_format_i18n($status['page_count']))); ?></span><?php endif; ?>
                </div>
                <?php if ($url) : ?><div class="sc-foundation-document__actions"><a class="sc-foundation-document__button sc-foundation-document__button--primary" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF', 'sustainable-catalyst-library'); ?></a><?php if ((int) get_post_meta($post_id, '_sc_foundation_allow_download', true)) : ?><a class="sc-foundation-document__button" href="<?php echo esc_url($url); ?>" download><?php esc_html_e('Download PDF', 'sustainable-catalyst-library'); ?></a><?php endif; ?><a class="sc-foundation-document__button" href="<?php echo esc_url(rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $post_id . '/citation?format=plain')); ?>"><?php esc_html_e('Plain citation', 'sustainable-catalyst-library'); ?></a><a class="sc-foundation-document__button" href="<?php echo esc_url(rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $post_id . '/citation?format=bibtex')); ?>"><?php esc_html_e('BibTeX', 'sustainable-catalyst-library'); ?></a><a class="sc-foundation-document__button" href="<?php echo esc_url(rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $post_id . '/citation?format=ris')); ?>"><?php esc_html_e('RIS', 'sustainable-catalyst-library'); ?></a><a class="sc-foundation-document__button" href="<?php echo esc_url(rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $post_id . '/citation?format=csl')); ?>"><?php esc_html_e('CSL JSON', 'sustainable-catalyst-library'); ?></a></div><?php endif; ?>
            </header>
            <?php if ($meta['author'] || $meta['publisher'] || $meta['doi'] || $meta['language']) : ?><dl class="sc-foundation-document__bibliographic"><?php if ($meta['author']) : ?><div><dt><?php esc_html_e('Author or institution', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($meta['author']); ?></dd></div><?php endif; ?><?php if ($meta['publisher']) : ?><div><dt><?php esc_html_e('Publisher', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($meta['publisher']); ?></dd></div><?php endif; ?><?php if ($meta['doi']) : ?><div><dt><?php esc_html_e('DOI', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($meta['doi']); ?></dd></div><?php endif; ?><?php if ($meta['language']) : ?><div><dt><?php esc_html_e('Language', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($meta['language']); ?></dd></div><?php endif; ?></dl><?php endif; ?>
            <?php if ($body !== '') : ?><div class="sc-foundation-document__description"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
            <?php $related_posts = array_values(array_filter(array_map('get_post', $meta['related_ids']), static fn($related) => $related instanceof WP_Post && $related->post_status === 'publish')); if ($related_posts) : ?><section class="sc-foundation-document__related"><h2><?php esc_html_e('Related Library records', 'sustainable-catalyst-library'); ?></h2><ul><?php foreach ($related_posts as $related) : ?><li><a href="<?php echo esc_url(get_permalink($related)); ?>"><?php echo esc_html(get_the_title($related)); ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
            <?php $versions = self::version_history($post_id, 20); if ($versions) : ?><details class="sc-foundation-document__versions"><summary><?php echo esc_html(sprintf(_n('%s recorded document version', '%s recorded document versions', count($versions), 'sustainable-catalyst-library'), number_format_i18n(count($versions)))); ?></summary><ol><?php foreach ($versions as $version) : ?><li><a href="<?php echo esc_url($version['pdf_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($version['version_label'] ?: $version['filename']); ?></a> <span><?php echo esc_html($version['created_at']); ?></span><?php if (!empty($version['sha256'])) : ?> <code><?php echo esc_html(substr($version['sha256'], 0, 12)); ?>…</code><?php endif; ?></li><?php endforeach; ?></ol></details><?php endif; ?>
            <?php if ($url && (int) get_post_meta($post_id, '_sc_foundation_show_viewer', true)) : ?>
                <div class="sc-foundation-viewer" aria-label="<?php esc_attr_e('PDF document viewer', 'sustainable-catalyst-library'); ?>">
                    <div class="sc-foundation-viewer__toolbar"><button type="button" data-prev-page><?php esc_html_e('Previous', 'sustainable-catalyst-library'); ?></button><span><label class="screen-reader-text" for="sc-foundation-page-<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Page', 'sustainable-catalyst-library'); ?></label><input id="sc-foundation-page-<?php echo esc_attr($post_id); ?>" type="number" min="1" value="1" data-page-number> / <span data-page-count>—</span></span><button type="button" data-next-page><?php esc_html_e('Next', 'sustainable-catalyst-library'); ?></button><button type="button" data-zoom-out aria-label="<?php esc_attr_e('Zoom out', 'sustainable-catalyst-library'); ?>">−</button><button type="button" data-zoom-in aria-label="<?php esc_attr_e('Zoom in', 'sustainable-catalyst-library'); ?>">+</button></div>
                    <div class="sc-foundation-viewer__stage"><canvas data-pdf-canvas></canvas><p data-viewer-status><?php esc_html_e('Loading document…', 'sustainable-catalyst-library'); ?></p></div>
                </div>
                <div class="sc-foundation-document__mobile-fallback"><p><?php esc_html_e('For the most reliable reading experience on a small screen, open the PDF in your device’s document viewer.', 'sustainable-catalyst-library'); ?></p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open mobile PDF', 'sustainable-catalyst-library'); ?></a></div>
            <?php elseif (!$url) : ?><p class="sc-foundation-document__notice"><?php esc_html_e('This record does not currently have a PDF attachment.', 'sustainable-catalyst-library'); ?></p><?php endif; ?>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    public function register_rest_routes(): void {
        $ns = 'sustainable-catalyst/v1/library';
        register_rest_route($ns, '/foundation-documents', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'rest_documents'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'rest_document'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/pages', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'rest_pages'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/citation', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'rest_citation'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/extract/start', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'rest_extract_start'], 'permission_callback' => [$this, 'can_edit_document']]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/extract/pages', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'rest_extract_pages'], 'permission_callback' => [$this, 'can_edit_document']]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/extract/complete', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'rest_extract_complete'], 'permission_callback' => [$this, 'can_edit_document']]);
        register_rest_route($ns, '/foundation-documents/(?P<id>\d+)/extract/fail', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'rest_extract_fail'], 'permission_callback' => [$this, 'can_edit_document']]);
        register_rest_route($ns, '/foundation-documents/migration/scan', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'rest_migration_scan'], 'permission_callback' => static fn() => current_user_can('manage_options')]);
    }

    public function can_edit_document(WP_REST_Request $request): bool {
        $id = absint($request['id']);
        return $id > 0 && get_post_type($id) === self::POST_TYPE && current_user_can('edit_post', $id);
    }

    public function rest_documents(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, absint($request['page'] ?: 1));
        $per_page = min(100, max(1, absint($request['per_page'] ?: 20)));
        $search = sanitize_text_field((string) $request['search']);
        $args = ['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page];
        if ($search !== '') $args['post__in'] = self::matching_ids($search, 10000) ?: [0];
        $query = new WP_Query($args);
        return rest_ensure_response(['schema' => self::SCHEMA, 'items' => array_map([self::class, 'public_payload'], $query->posts), 'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages]]);
    }

    public static function matching_ids(string $search, int $limit = 10000): array {
        global $wpdb;
        $search = trim(self::normalize_text($search));
        if ($search === '') return [];
        $like = '%' . $wpdb->esc_like($search) . '%';
        $sql = $wpdb->prepare(
            'SELECT DISTINCT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . self::pages_table() . ' pg ON pg.post_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND (p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR pg.page_text LIKE %s) ORDER BY p.post_modified_gmt DESC, p.ID DESC LIMIT %d',
            self::POST_TYPE,
            'publish',
            $like,
            $like,
            $like,
            $like,
            min(20000, max(1, $limit))
        );
        return array_map('intval', $wpdb->get_col($sql) ?: []);
    }

    public function rest_document(WP_REST_Request $request): WP_REST_Response {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== self::POST_TYPE || ($post->post_status !== 'publish' && !current_user_can('edit_post', $post->ID))) return new WP_REST_Response(['message' => __('Document not found.', 'sustainable-catalyst-library')], 404);
        return rest_ensure_response(self::public_payload($post, true));
    }

    public function rest_pages(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = absint($request['id']);
        if (get_post_type($id) !== self::POST_TYPE || (get_post_status($id) !== 'publish' && !current_user_can('edit_post', $id))) {
            return new WP_REST_Response(['message' => __('Document not found.', 'sustainable-catalyst-library')], 404);
        }
        $search = trim(sanitize_text_field((string) $request['search']));
        $pdf_page = absint($request['pdf_page']);
        $paged = max(1, absint($request['page'] ?: 1));
        $per_page = min(100, max(1, absint($request['per_page'] ?: 50)));
        $where = 'post_id = %d';
        $values = [$id];
        if ($search !== '') {
            $where .= ' AND page_text LIKE %s';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        if ($pdf_page > 0) {
            $where .= ' AND page_number = %d';
            $values[] = $pdf_page;
        }
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::pages_table() . " WHERE {$where}", ...$values));
        $offset = ($paged - 1) * $per_page;
        $query_values = array_merge($values, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT page_number, page_text, character_count, content_hash, extracted_at FROM ' . self::pages_table() . " WHERE {$where} ORDER BY page_number ASC LIMIT %d OFFSET %d", ...$query_values), ARRAY_A) ?: [];
        return rest_ensure_response([
            'schema' => self::EXTRACTION_SCHEMA,
            'document_id' => $id,
            'items' => $rows,
            'pagination' => ['page' => $paged, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0],
        ]);
    }

    public function rest_extract_start(WP_REST_Request $request): WP_REST_Response {
        $id = absint($request['id']);
        $attachment_id = absint(get_post_meta($id, '_sc_foundation_pdf_attachment_id', true));
        $url = $attachment_id ? wp_get_attachment_url($attachment_id) : (string) get_post_meta($id, '_sc_foundation_external_pdf_url', true);
        if (!$url) return new WP_REST_Response(['message' => __('Select and save a PDF attachment first.', 'sustainable-catalyst-library')], 400);
        self::clear_pages($id);
        update_post_meta($id, '_sc_foundation_extraction_status', 'extracting');
        update_post_meta($id, '_sc_foundation_extraction_started_at', current_time('mysql', true));
        delete_post_meta($id, '_sc_foundation_extraction_error');
        return rest_ensure_response(['schema' => self::EXTRACTION_SCHEMA, 'document_id' => $id, 'pdf_url' => esc_url_raw($url)]);
    }

    public function rest_extract_pages(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = absint($request['id']);
        $pages = is_array($request['pages']) ? $request['pages'] : [];
        $saved = 0;
        foreach (array_slice($pages, 0, 25) as $page) {
            $number = absint($page['page_number'] ?? 0);
            if ($number < 1) continue;
            $text = self::substr(self::normalize_text((string) ($page['text'] ?? '')), 0, 1000000);
            $hash = hash('sha256', $text);
            $result = $wpdb->replace(self::pages_table(), ['post_id' => $id, 'page_number' => $number, 'page_text' => $text, 'character_count' => self::strlen($text), 'content_hash' => $hash, 'extracted_at' => current_time('mysql', true)], ['%d','%d','%s','%d','%s','%s']);
            if ($result !== false) $saved++;
        }
        return rest_ensure_response(['saved' => $saved]);
    }

    public function rest_extract_complete(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = absint($request['id']);
        $summary = $wpdb->get_row($wpdb->prepare('SELECT COUNT(*) AS pages, COALESCE(SUM(character_count),0) AS chars FROM ' . self::pages_table() . ' WHERE post_id = %d', $id), ARRAY_A) ?: ['pages' => 0, 'chars' => 0];
        if ((int) $summary['pages'] < 1) return new WP_REST_Response(['message' => __('No page text was received.', 'sustainable-catalyst-library')], 400);
        update_post_meta($id, '_sc_foundation_extraction_status', 'complete');
        update_post_meta($id, '_sc_foundation_extracted_at', current_time('mysql', true));
        update_post_meta($id, '_sc_foundation_page_count', (int) $summary['pages']);
        update_post_meta($id, '_sc_foundation_character_count', (int) $summary['chars']);
        delete_post_meta($id, '_sc_foundation_extraction_error');
        $this->update_latest_version_pages($id, (int) $summary['pages']);
        $this->indexer->index_post($id, $this->indexer->configured_post_types());
        do_action('sc_library_foundation_document_extracted', $id, $summary);
        do_action('sc_library_record_updated', $id, ['source' => 'pdf_extraction', 'pages' => (int) $summary['pages']]);
        return rest_ensure_response(['schema' => self::EXTRACTION_SCHEMA, 'status' => 'complete', 'page_count' => (int) $summary['pages'], 'character_count' => (int) $summary['chars'], 'research_librarian_synchronized' => true]);
    }

    public function rest_extract_fail(WP_REST_Request $request): WP_REST_Response {
        $id = absint($request['id']);
        $message = sanitize_textarea_field((string) $request['message']);
        update_post_meta($id, '_sc_foundation_extraction_status', 'failed');
        update_post_meta($id, '_sc_foundation_extraction_error', self::substr($message ?: __('Unknown PDF extraction failure.', 'sustainable-catalyst-library'), 0, 1000));
        return rest_ensure_response(['status' => 'failed']);
    }

    private function update_latest_version_pages(int $post_id, int $page_count): void {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::versions_table() . ' WHERE post_id = %d ORDER BY id DESC LIMIT 1', $post_id));
        if ($id) $wpdb->update(self::versions_table(), ['page_count' => $page_count], ['id' => (int) $id], ['%d'], ['%d']);
    }

    public function serve_citation_download(bool $served, $result, WP_REST_Request $request, WP_REST_Server $server): bool {
        if ($served || !preg_match('#^/sustainable-catalyst/v1/library/foundation-documents/(\d+)/citation$#', $request->get_route())) return $served;
        if (!$result instanceof WP_REST_Response || !is_string($result->get_data())) return $served;
        $headers = $result->get_headers();
        status_header($result->get_status());
        foreach ($headers as $name => $value) header($name . ': ' . $value);
        echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return true;
    }

    public function rest_citation(WP_REST_Request $request) {
        $id = absint($request['id']);
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE || ($post->post_status !== 'publish' && !current_user_can('edit_post', $id))) return new WP_REST_Response(['message' => __('Document not found.', 'sustainable-catalyst-library')], 404);
        $format = sanitize_key((string) ($request['format'] ?: 'csl-json'));
        if ($format === 'plain') $format = 'text';
        if ($format === 'csl') $format = 'csl-json';
        $citation = self::citation($id, $format);
        $response = new WP_REST_Response($citation['body'], 200);
        $response->header('Content-Type', $citation['content_type']);
        $response->header('Content-Disposition', 'attachment; filename="' . sanitize_file_name($citation['filename']) . '"');
        return $response;
    }

    public static function citation(int $post_id, string $format): array {
        $post = get_post($post_id); $m = self::metadata($post_id); $url = get_permalink($post_id); $year = $m['publication_date'] ? substr($m['publication_date'], 0, 4) : get_the_date('Y', $post_id); $key = sanitize_key(($m['author'] ?: 'sustainable-catalyst') . '-' . $year . '-' . $post->post_title);
        if ($format === 'bibtex') {
            $body = "@report{{$key},\n  title = {" . self::bib_escape($post->post_title) . "},\n  author = {" . self::bib_escape($m['author'] ?: $m['publisher']) . "},\n  institution = {" . self::bib_escape($m['publisher']) . "},\n  year = {{$year}},\n  url = {{$url}},\n  note = {Version " . self::bib_escape($m['version']) . "}\n}\n";
            return ['body' => $body, 'content_type' => 'application/x-bibtex; charset=utf-8', 'filename' => sanitize_title($post->post_title) . '.bib'];
        }
        if ($format === 'ris') {
            $body = "TY  - RPRT\nTI  - {$post->post_title}\nAU  - {$m['author']}\nPB  - {$m['publisher']}\nPY  - {$year}\nUR  - {$url}\nET  - {$m['version']}\nER  - \n";
            return ['body' => $body, 'content_type' => 'application/x-research-info-systems; charset=utf-8', 'filename' => sanitize_title($post->post_title) . '.ris'];
        }
        if ($format === 'text') {
            $body = trim(($m['author'] ?: $m['publisher']) . '. (' . $year . '). ' . $post->post_title . ($m['version'] ? ' (Version ' . $m['version'] . ')' : '') . '. ' . $url);
            return ['body' => $body, 'content_type' => 'text/plain; charset=utf-8', 'filename' => sanitize_title($post->post_title) . '.txt'];
        }
        $body = wp_json_encode(['type' => 'report', 'id' => $key, 'title' => $post->post_title, 'author' => $m['author'] ? [['literal' => $m['author']]] : [], 'publisher' => $m['publisher'], 'issued' => ['date-parts' => [[(int) $year]]], 'version' => $m['version'], 'DOI' => $m['doi'], 'URL' => $url], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return ['body' => $body, 'content_type' => 'application/vnd.citationstyles.csl+json; charset=utf-8', 'filename' => sanitize_title($post->post_title) . '.json'];
    }

    private static function bib_escape(string $value): string { return str_replace(['{','}'], ['\\{','\\}'], $value); }

    public static function public_payload($post, bool $detail = false): array {
        if (is_numeric($post)) $post = get_post((int) $post);
        $id = (int) $post->ID; $m = self::metadata($id); $s = self::extraction_status($id); $attachment_id = absint(get_post_meta($id, '_sc_foundation_pdf_attachment_id', true)); $url = $attachment_id ? wp_get_attachment_url($attachment_id) : (string) get_post_meta($id, '_sc_foundation_external_pdf_url', true);
        $payload = ['schema' => self::SCHEMA, 'id' => $id, 'record_identifier' => 'sc:library:foundation-document:' . $id, 'title' => get_the_title($id), 'excerpt' => get_the_excerpt($id), 'url' => get_permalink($id), 'pdf_url' => esc_url_raw($url ?: ''), 'open_url' => esc_url_raw($url ?: ''), 'download_url' => esc_url_raw($url ?: ''), 'download_enabled' => (bool) get_post_meta($id, '_sc_foundation_allow_download', true), 'viewer_enabled' => (bool) get_post_meta($id, '_sc_foundation_show_viewer', true), 'metadata' => $m, 'extraction' => $s, 'citation_urls' => ['bibtex' => rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $id . '/citation?format=bibtex'), 'ris' => rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $id . '/citation?format=ris'), 'csl_json' => rest_url('sustainable-catalyst/v1/library/foundation-documents/' . $id . '/citation?format=csl-json')]];
        if ($detail) $payload['versions'] = self::version_history($id, 50);
        return $payload;
    }

    public static function metadata(int $post_id): array {
        return ['version' => (string) get_post_meta($post_id, '_sc_foundation_version', true), 'publication_date' => (string) get_post_meta($post_id, '_sc_foundation_publication_date', true), 'author' => (string) get_post_meta($post_id, '_sc_foundation_author', true), 'publisher' => (string) get_post_meta($post_id, '_sc_foundation_publisher', true), 'doi' => (string) get_post_meta($post_id, '_sc_foundation_doi', true), 'language' => (string) (get_post_meta($post_id, '_sc_foundation_language', true) ?: 'en'), 'related_ids' => self::sanitize_ids((string) get_post_meta($post_id, '_sc_foundation_related_ids', true)), 'citation_note' => (string) get_post_meta($post_id, '_sc_foundation_citation_note', true)];
    }

    public static function extraction_status(int $post_id): array {
        return ['status' => (string) (get_post_meta($post_id, '_sc_foundation_extraction_status', true) ?: 'not_started'), 'page_count' => absint(get_post_meta($post_id, '_sc_foundation_page_count', true)), 'character_count' => absint(get_post_meta($post_id, '_sc_foundation_character_count', true)), 'extracted_at' => (string) get_post_meta($post_id, '_sc_foundation_extracted_at', true), 'error' => (string) get_post_meta($post_id, '_sc_foundation_extraction_error', true)];
    }

    public static function extracted_text(int $post_id): string {
        global $wpdb;
        $pages = $wpdb->get_col($wpdb->prepare('SELECT page_text FROM ' . self::pages_table() . ' WHERE post_id = %d ORDER BY page_number ASC', $post_id)) ?: [];
        return trim(implode("\n\n", array_map('strval', $pages)));
    }

    public static function page_hits(int $post_id, string $search, int $limit = 5): array {
        $search = trim(self::normalize_text($search));
        if ($search === '') return [];
        global $wpdb;
        $terms = [$search];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', strtolower($search)) ?: [] as $term) {
            if (self::strlen($term) >= 4 && !in_array($term, $terms, true)) $terms[] = $term;
            if (count($terms) >= 7) break;
        }
        $clauses = []; $values = [$post_id];
        foreach ($terms as $term) { $clauses[] = 'page_text LIKE %s'; $values[] = '%' . $wpdb->esc_like($term) . '%'; }
        $values[] = min(20, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT page_number, page_text FROM ' . self::pages_table() . ' WHERE post_id = %d AND (' . implode(' OR ', $clauses) . ') ORDER BY page_number ASC LIMIT %d', ...$values), ARRAY_A) ?: [];
        return array_map(static function(array $row) use ($terms): array {
            $text = (string) $row['page_text']; $pos = false;
            foreach ($terms as $term) { $candidate = function_exists('mb_stripos') ? mb_stripos($text, $term) : stripos($text, $term); if ($candidate !== false) { $pos = $candidate; break; } }
            $start = $pos === false ? 0 : max(0, (int) $pos - 90);
            return ['page' => (int) $row['page_number'], 'snippet' => wp_trim_words(self::substr($text, $start, 300), 42, '…')];
        }, $rows);
    }

    public static function version_history(int $post_id, int $limit = 20): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT version_label, filename, pdf_url, sha256, page_count, created_at FROM ' . self::versions_table() . ' WHERE post_id = %d ORDER BY id DESC LIMIT %d', $post_id, min(100, max(1, $limit))), ARRAY_A) ?: [];
    }

    public static function clear_pages(int $post_id): void {
        global $wpdb; $wpdb->delete(self::pages_table(), ['post_id' => $post_id], ['%d']); update_post_meta($post_id, '_sc_foundation_page_count', 0); update_post_meta($post_id, '_sc_foundation_character_count', 0); delete_post_meta($post_id, '_sc_foundation_extracted_at');
    }

    public function admin_menu(): void {
        add_submenu_page('sc-library', __('Foundation PDF Migration', 'sustainable-catalyst-library'), __('PDF Migration', 'sustainable-catalyst-library'), 'manage_options', 'sc-library-pdf-migration', [$this, 'render_migration_page']);
    }

    public function render_migration_page(): void {
        $candidates = $this->migration_candidates(100);
        ?>
        <div class="wrap sc-library-admin-wrap"><h1><?php esc_html_e('Foundation PDF Migration', 'sustainable-catalyst-library'); ?></h1><p><?php esc_html_e('This tool finds published Foundation records that still link directly to PDFs. It creates dedicated Foundation Document records without deleting or rewriting the original page.', 'sustainable-catalyst-library'); ?></p>
        <?php if (!$candidates) : ?><div class="notice notice-success inline"><p><?php esc_html_e('No unmigrated direct-download Foundation PDF links were found.', 'sustainable-catalyst-library'); ?></p></div><?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('sc_library_foundation_migrate'); ?><input type="hidden" name="action" value="sc_library_foundation_migrate"><table class="widefat striped"><thead><tr><th></th><th><?php esc_html_e('Source record', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('PDF', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Foundation classification', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Attachment', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody><?php foreach ($candidates as $candidate) : ?><tr><td><input type="checkbox" name="candidate_ids[]" value="<?php echo esc_attr($candidate['source_post_id']); ?>" checked></td><td><a href="<?php echo esc_url(get_edit_post_link($candidate['source_post_id'])); ?>"><?php echo esc_html($candidate['title']); ?></a></td><td><code><?php echo esc_html($candidate['pdf_url']); ?></code></td><td><?php echo !empty($candidate['foundation_classified']) ? esc_html__('Foundations collection', 'sustainable-catalyst-library') : esc_html__('Review manually', 'sustainable-catalyst-library'); ?></td><td><?php echo $candidate['attachment_id'] ? esc_html(sprintf(__('Media #%d', 'sustainable-catalyst-library'), $candidate['attachment_id'])) : esc_html__('URL only', 'sustainable-catalyst-library'); ?></td></tr><?php endforeach; ?></tbody></table><p><button class="button button-primary"><?php esc_html_e('Create selected Foundation Document records', 'sustainable-catalyst-library'); ?></button></p></form><?php endif; ?></div>
        <?php
    }

    public function rest_migration_scan(): WP_REST_Response { return rest_ensure_response(['items' => $this->migration_candidates(250)]); }

    private function migration_candidates(int $limit): array {
        global $wpdb;
        $limit = min(1000, max(1, $limit));
        $scan_limit = min(20000, max(500, $limit * 20));
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') ORDER BY ID ASC LIMIT %d",
            $scan_limit
        )) ?: []);
        if (!$ids) return [];

        $existing_rows = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sc_foundation_migrated_from_post_id'"
        ) ?: [];
        $already_migrated = array_fill_keys(array_map('intval', $existing_rows), true);
        $items = [];
        foreach ($ids as $id) {
            if (isset($already_migrated[$id])) continue;
            $content = (string) get_post_field('post_content', $id);
            $pdf_url = esc_url_raw((string) get_post_meta($id, '_sc_library_doc_pdf_url', true));
            if (!$pdf_url && preg_match('/href=["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\']/i', $content, $match)) {
                $pdf_url = esc_url_raw(html_entity_decode($match[1]));
            }
            if (!$pdf_url) continue;
            $collection_slugs = wp_get_object_terms($id, SC_Library_Taxonomies::COLLECTION, ['fields' => 'slugs']);
            $is_foundation = !is_wp_error($collection_slugs) && in_array(SC_Library_Documentation::COLLECTION_SLUG, $collection_slugs, true);
            $attachment_id = attachment_url_to_postid($pdf_url);
            $items[] = [
                'source_post_id' => $id,
                'title' => get_the_title($id),
                'pdf_url' => $pdf_url,
                'attachment_id' => $attachment_id,
                'foundation_classified' => $is_foundation,
            ];
            if (count($items) >= $limit) break;
        }
        usort($items, static fn(array $a, array $b): int => ((int) $b['foundation_classified'] <=> (int) $a['foundation_classified']) ?: ($a['source_post_id'] <=> $b['source_post_id']));
        return $items;
    }

    public function handle_migration(): void {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied.', 'sustainable-catalyst-library'));
        check_admin_referer('sc_library_foundation_migrate');
        $selected = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['candidate_ids'] ?? [])))));
        $candidates = $this->migration_candidates(500); $by_id = []; foreach ($candidates as $candidate) $by_id[$candidate['source_post_id']] = $candidate;
        $created = 0;
        foreach ($selected as $source_id) {
            if (!isset($by_id[$source_id])) continue; $candidate = $by_id[$source_id];
            $new_id = wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => get_the_title($source_id), 'post_excerpt' => get_the_excerpt($source_id), 'post_content' => wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $source_id)), 120, '…'), 'post_author' => get_current_user_id()], true);
            if (is_wp_error($new_id)) continue;
            update_post_meta($new_id, '_sc_foundation_migrated_from_post_id', $source_id); update_post_meta($new_id, '_sc_foundation_pdf_attachment_id', absint($candidate['attachment_id'])); update_post_meta($new_id, '_sc_foundation_external_pdf_url', $candidate['attachment_id'] ? '' : $candidate['pdf_url']); update_post_meta($new_id, '_sc_foundation_allow_download', 1); update_post_meta($new_id, '_sc_foundation_show_viewer', 1); update_post_meta($new_id, '_sc_foundation_extraction_status', $candidate['attachment_id'] ? 'pending' : 'external_url_requires_media_import'); update_post_meta($new_id, '_sc_foundation_related_ids', (string) $source_id);
            foreach (['category', 'post_tag', SC_Library_Taxonomies::DOCUMENT_CATEGORY, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::SERIES] as $taxonomy) {
                $term_ids = wp_get_object_terms($source_id, $taxonomy, ['fields' => 'ids']);
                if (!is_wp_error($term_ids) && $term_ids) wp_set_object_terms($new_id, array_map('intval', $term_ids), $taxonomy, false);
            }
            wp_set_object_terms($new_id, [SC_Library_Documentation::COLLECTION_SLUG], SC_Library_Taxonomies::COLLECTION, false);
            if (!empty($candidate['attachment_id'])) $this->record_version($new_id, absint($candidate['attachment_id']));
            $this->sync_relationships($new_id, (string) $source_id); $this->indexer->index_post($new_id, array_values(array_unique(array_merge($this->indexer->configured_post_types(), [self::POST_TYPE])))); $created++;
        }
        wp_safe_redirect(add_query_arg(['page' => 'sc-library-pdf-migration', 'migrated' => $created], admin_url('admin.php'))); exit;
    }

    private static function sanitize_ids(string $value): array { return array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,]+/', wp_unslash($value)) ?: [])))); }
    private static function sanitize_date(string $value): string { $value = sanitize_text_field(wp_unslash($value)); if ($value === '') return ''; $date = DateTime::createFromFormat('Y-m-d', $value); return $date && $date->format('Y-m-d') === $value ? $value : ''; }
    private static function normalize_text(string $text): string { $text = wp_check_invalid_utf8($text, true); $text = preg_replace('/[\x{00A0}\t]+/u', ' ', $text); $text = preg_replace('/\s*\n\s*/u', "\n", $text); return trim((string) preg_replace('/[ ]{2,}/u', ' ', $text)); }
    private static function strlen(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }
    private static function substr(string $value, int $start, int $length): string { return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length); }
}
