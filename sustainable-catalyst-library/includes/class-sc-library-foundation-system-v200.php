<?php
/**
 * Sustainable Catalyst Foundations v2.0.0.
 *
 * Extends the native Knowledge Library Foundation Document record with a
 * governed metadata model, canonical document templates, catalog rendering,
 * revision history, and public REST discovery.
 *
 * @package Sustainable_Catalyst_Library
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SC_LIBRARY_FOUNDATIONS_VERSION')) {
    define('SC_LIBRARY_FOUNDATIONS_VERSION', '2.0.3');
}

final class SC_Library_Foundation_System_V200 {
    private static ?self $instance = null;

    private const META_PREFIX = '_sc_foundation_';

    private const REWRITE_VERSION_OPTION = 'sc_library_foundations_rewrite_version';

    private const TYPES = [
        'institutional-standard' => 'Institutional Standard',
        'policy-legal-record'    => 'Policy and Legal Record',
        'product-system-brief'   => 'Product and System Brief',
    ];

    private const STATUSES = [
        'draft'                    => 'Draft',
        'under-review'             => 'Under Review',
        'current-approved-record'  => 'Current Approved Record',
        'current-living-document'  => 'Current Living Document',
        'fixed-approved-snapshot'  => 'Fixed Approved Snapshot',
        'superseded'               => 'Superseded',
        'withdrawn'                => 'Withdrawn',
        'historical-record'        => 'Historical Record',
    ];

    private const AUTHORITIES = [
        'institutional' => 'Institutional',
        'methodology'   => 'Methodology',
        'policy'        => 'Policy',
        'product'       => 'Product',
        'historical'    => 'Historical',
    ];

    private const CANONICAL_TYPES = [
        'living-html' => 'Living HTML record',
        'fixed-pdf'   => 'Fixed PDF snapshot',
        'repository'  => 'Repository record',
        'historical'  => 'Historical record',
    ];

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register_hooks(): void {
        add_filter('register_post_type_args', [$this, 'foundation_document_post_type_args'], 100, 2);
        add_filter('rewrite_rules_array', [$this, 'remove_legacy_foundation_document_rules'], 100);
        add_action('parse_request', [$this, 'protect_foundations_page_route'], 0);
        add_action('init', [$this, 'register_meta'], 12);
        add_action('init', [$this, 'maybe_refresh_rewrite_rules'], 100);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes'], 30);
        add_action('save_post_sc_foundation_doc', [$this, 'save'], 40, 3);
        add_action('wp_enqueue_scripts', [$this, 'public_assets'], 30);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_filter('template_include', [$this, 'template_include'], 99);
        add_shortcode('sc_foundations_catalog', [$this, 'catalog_shortcode']);
        add_filter('manage_sc_foundation_doc_posts_columns', [$this, 'admin_columns']);
        add_action('manage_sc_foundation_doc_posts_custom_column', [$this, 'admin_column'], 10, 2);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }


    /**
     * Keep Foundation Document records on a route that cannot collide with
     * the public Foundations page.
     */
    public function foundation_document_post_type_args(array $args, string $post_type): array {
        if ($post_type !== 'sc_foundation_doc') {
            return $args;
        }

        $args['rewrite'] = [
            'slug'       => 'foundation-documents',
            'with_front' => false,
        ];
        $args['has_archive'] = false;

        return $args;
    }

    /**
     * Remove stale document rules that claim the public /foundations/ path.
     */
    public function remove_legacy_foundation_document_rules(array $rules): array {
        foreach ($rules as $pattern => $query) {
            $pattern_text = ltrim((string) $pattern, '^');
            $query_text = (string) $query;

            if (
                str_starts_with($pattern_text, 'foundations/')
                && str_contains($query_text, 'post_type=sc_foundation_doc')
            ) {
                unset($rules[$pattern]);
            }
        }

        return $rules;
    }

    /**
     * Guarantee that the canonical institutional Foundations page resolves as
     * a WordPress page even when stale rewrite or edge-cache state exists.
     */
    public function protect_foundations_page_route(WP $wp): void {
        if (is_admin()) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        if ($path !== 'institution/foundations') {
            return;
        }

        $page = get_page_by_path('institution/foundations', OBJECT, 'page');
        if (!$page instanceof WP_Post || $page->post_status !== 'publish') {
            return;
        }

        $wp->query_vars = [
            'page_id'  => (int) $page->ID,
            'pagename' => 'institution/foundations',
        ];
        $wp->matched_rule = 'sc-foundations-canonical-page';
        $wp->matched_query = 'page_id=' . (int) $page->ID;
    }

    /**
     * Refresh rewrite rules once for each Foundations release.
     *
     * Plugin replacement does not always execute activation hooks. A stale
     * rewrite cache can therefore make an existing Foundations page appear
     * missing even though the page record and content remain intact.
     */
    public function maybe_refresh_rewrite_rules(): void {
        $stored_version = (string) get_option(self::REWRITE_VERSION_OPTION, '');
        if ($stored_version === SC_LIBRARY_FOUNDATIONS_VERSION) {
            return;
        }
        if (!post_type_exists('sc_foundation_doc')) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::REWRITE_VERSION_OPTION, SC_LIBRARY_FOUNDATIONS_VERSION, false);
    }

    public function register_meta(): void {
        $fields = [
            'document_id'             => 'string',
            'subtitle'                => 'string',
            'record_type'             => 'string',
            'authority_level'         => 'string',
            'status'                  => 'string',
            'effective_date'          => 'string',
            'last_reviewed'           => 'string',
            'review_cycle'            => 'string',
            'owner'                   => 'string',
            'canonical_record'        => 'string',
            'supersedes_ids'          => 'string',
            'superseded_by_id'        => 'integer',
            'related_product_slugs'   => 'string',
            'related_repository_urls' => 'string',
            'correction_url'          => 'string',
            'revision_history'        => 'string',
            'show_toc'                => 'boolean',
        ];

        foreach ($fields as $name => $type) {
            register_post_meta('sc_foundation_doc', self::META_PREFIX . $name, [
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $this->sanitize_callback($name),
                'auth_callback'     => static fn(): bool => current_user_can('edit_posts'),
            ]);
        }
    }

    private function sanitize_callback(string $name): callable {
        if (in_array($name, ['superseded_by_id'], true)) {
            return 'absint';
        }
        if ($name === 'show_toc') {
            return static fn($value): bool => (bool) $value;
        }
        if (in_array($name, ['correction_url'], true)) {
            return 'esc_url_raw';
        }
        if (in_array($name, ['revision_history', 'related_repository_urls'], true)) {
            return 'sanitize_textarea_field';
        }
        return 'sanitize_text_field';
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'sc-foundation-governance-v200',
            __('Foundation authority and governance', 'sustainable-catalyst-library'),
            [$this, 'render_governance_box'],
            'sc_foundation_doc',
            'normal',
            'high'
        );
        add_meta_box(
            'sc-foundation-revisions-v200',
            __('Revision history', 'sustainable-catalyst-library'),
            [$this, 'render_revision_box'],
            'sc_foundation_doc',
            'normal',
            'default'
        );
    }

    public function render_governance_box(WP_Post $post): void {
        wp_nonce_field('sc_foundation_system_v200_save', 'sc_foundation_system_v200_nonce');
        $m = fn(string $key, $default = '') => get_post_meta($post->ID, self::META_PREFIX . $key, true) ?: $default;
        $document_id = (string) $m('document_id', self::default_document_id($post->ID));
        ?>
        <div class="sc-foundation-admin-grid">
            <?php $this->field('document_id', __('Document ID', 'sustainable-catalyst-library'), $document_id, 'text', 'SC-FND-001'); ?>
            <?php $this->field('subtitle', __('Subtitle', 'sustainable-catalyst-library'), (string) $m('subtitle'), 'text'); ?>
            <?php $this->select_field('record_type', __('Document type', 'sustainable-catalyst-library'), (string) $m('record_type', 'institutional-standard'), self::TYPES); ?>
            <?php $this->select_field('authority_level', __('Authority', 'sustainable-catalyst-library'), (string) $m('authority_level', 'institutional'), self::AUTHORITIES); ?>
            <?php $this->select_field('status', __('Status', 'sustainable-catalyst-library'), (string) $m('status', 'draft'), self::STATUSES); ?>
            <?php $this->select_field('canonical_record', __('Canonical record', 'sustainable-catalyst-library'), (string) $m('canonical_record', 'living-html'), self::CANONICAL_TYPES); ?>
            <?php $this->field('effective_date', __('Effective date', 'sustainable-catalyst-library'), (string) $m('effective_date'), 'date'); ?>
            <?php $this->field('last_reviewed', __('Last reviewed', 'sustainable-catalyst-library'), (string) $m('last_reviewed'), 'date'); ?>
            <?php $this->field('review_cycle', __('Review cycle', 'sustainable-catalyst-library'), (string) $m('review_cycle', 'Annual'), 'text'); ?>
            <?php $this->field('owner', __('Document owner', 'sustainable-catalyst-library'), (string) $m('owner', 'Sustainable Catalyst'), 'text'); ?>
            <?php $this->field('supersedes_ids', __('Supersedes post IDs', 'sustainable-catalyst-library'), (string) $m('supersedes_ids'), 'text', '12, 34'); ?>
            <?php $this->field('superseded_by_id', __('Superseded by post ID', 'sustainable-catalyst-library'), (string) $m('superseded_by_id'), 'number'); ?>
            <?php $this->field('related_product_slugs', __('Related product slugs', 'sustainable-catalyst-library'), (string) $m('related_product_slugs'), 'text', 'workbench, decision-studio'); ?>
            <?php $this->field('correction_url', __('Correction URL', 'sustainable-catalyst-library'), (string) $m('correction_url'), 'url'); ?>
        </div>
        <p class="sc-foundation-admin-wide">
            <label for="sc_foundation_related_repository_urls"><strong><?php esc_html_e('Related repository URLs', 'sustainable-catalyst-library'); ?></strong></label>
            <textarea id="sc_foundation_related_repository_urls" name="sc_foundation_related_repository_urls" rows="3" class="widefat" placeholder="One URL per line"><?php echo esc_textarea((string) $m('related_repository_urls')); ?></textarea>
        </p>
        <p>
            <label><input type="checkbox" name="sc_foundation_show_toc" value="1" <?php checked((bool) $m('show_toc', 1)); ?>> <?php esc_html_e('Show the generated table of contents', 'sustainable-catalyst-library'); ?></label>
        </p>
        <p class="description"><?php echo esc_html(sprintf(__('Foundations System %s extends the existing Foundation Document record; PDF, citation, and extraction fields remain managed by Knowledge Library.', 'sustainable-catalyst-library'), SC_LIBRARY_FOUNDATIONS_VERSION)); ?></p>
        <?php
    }

    public function render_revision_box(WP_Post $post): void {
        $value = (string) get_post_meta($post->ID, self::META_PREFIX . 'revision_history', true);
        ?>
        <p><?php esc_html_e('Enter a JSON array of revision records. Each record may include version, date, status, and summary.', 'sustainable-catalyst-library'); ?></p>
        <textarea name="sc_foundation_revision_history" rows="8" class="widefat code" placeholder='[{"version":"1.0.0","date":"2026-07-16","status":"Approved","summary":"Initial publication"}]'><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    private function field(string $name, string $label, string $value, string $type = 'text', string $placeholder = ''): void {
        ?>
        <p>
            <label for="sc_foundation_<?php echo esc_attr($name); ?>"><strong><?php echo esc_html($label); ?></strong></label>
            <input id="sc_foundation_<?php echo esc_attr($name); ?>" name="sc_foundation_<?php echo esc_attr($name); ?>" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" class="widefat">
        </p>
        <?php
    }

    private function select_field(string $name, string $label, string $value, array $options): void {
        ?>
        <p>
            <label for="sc_foundation_<?php echo esc_attr($name); ?>"><strong><?php echo esc_html($label); ?></strong></label>
            <select id="sc_foundation_<?php echo esc_attr($name); ?>" name="sc_foundation_<?php echo esc_attr($name); ?>" class="widefat">
                <?php foreach ($options as $key => $option_label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function save(int $post_id, WP_Post $post, bool $update): void {
        if (!$update && $post->post_status === 'auto-draft') {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $nonce = isset($_POST['sc_foundation_system_v200_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['sc_foundation_system_v200_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sc_foundation_system_v200_save')) {
            return;
        }

        $document_id = strtoupper(sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_document_id'] ?? self::default_document_id($post_id)))));
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,39}$/', $document_id)) {
            $document_id = self::default_document_id($post_id);
        }

        $record_type = sanitize_key((string) ($_POST['sc_foundation_record_type'] ?? 'institutional-standard'));
        $status = sanitize_key((string) ($_POST['sc_foundation_status'] ?? 'draft'));
        $authority = sanitize_key((string) ($_POST['sc_foundation_authority_level'] ?? 'institutional'));
        $canonical = sanitize_key((string) ($_POST['sc_foundation_canonical_record'] ?? 'living-html'));

        $values = [
            'document_id'             => $document_id,
            'subtitle'                => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_subtitle'] ?? ''))),
            'record_type'             => array_key_exists($record_type, self::TYPES) ? $record_type : 'institutional-standard',
            'authority_level'         => array_key_exists($authority, self::AUTHORITIES) ? $authority : 'institutional',
            'status'                  => array_key_exists($status, self::STATUSES) ? $status : 'draft',
            'effective_date'          => self::sanitize_date((string) ($_POST['sc_foundation_effective_date'] ?? '')),
            'last_reviewed'           => self::sanitize_date((string) ($_POST['sc_foundation_last_reviewed'] ?? '')),
            'review_cycle'            => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_review_cycle'] ?? 'Annual'))),
            'owner'                   => sanitize_text_field(wp_unslash((string) ($_POST['sc_foundation_owner'] ?? 'Sustainable Catalyst'))),
            'canonical_record'        => array_key_exists($canonical, self::CANONICAL_TYPES) ? $canonical : 'living-html',
            'supersedes_ids'          => implode(',', self::sanitize_ids((string) ($_POST['sc_foundation_supersedes_ids'] ?? ''))),
            'superseded_by_id'        => absint($_POST['sc_foundation_superseded_by_id'] ?? 0),
            'related_product_slugs'   => self::sanitize_slugs((string) ($_POST['sc_foundation_related_product_slugs'] ?? '')),
            'related_repository_urls' => self::sanitize_urls((string) ($_POST['sc_foundation_related_repository_urls'] ?? '')),
            'correction_url'          => esc_url_raw(wp_unslash((string) ($_POST['sc_foundation_correction_url'] ?? ''))),
            'revision_history'        => self::sanitize_revision_history((string) ($_POST['sc_foundation_revision_history'] ?? '')),
            'show_toc'                => isset($_POST['sc_foundation_show_toc']) ? 1 : 0,
        ];

        foreach ($values as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }

        $legacy_status = match ($values['status']) {
            'current-approved-record', 'current-living-document', 'fixed-approved-snapshot' => 'current',
            'superseded' => 'superseded',
            'historical-record' => 'historical',
            default => 'draft',
        };
        update_post_meta($post_id, '_sc_library_doc_status', $legacy_status);
        update_post_meta($post_id, '_sc_library_doc_type', $values['record_type']);
        update_post_meta($post_id, '_sc_library_doc_authority_type', $values['canonical_record']);
        update_post_meta($post_id, '_sc_library_foundations_system_version', SC_LIBRARY_FOUNDATIONS_VERSION);
    }

    private static function sanitize_date(string $value): string {
        $value = sanitize_text_field(wp_unslash($value));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function sanitize_ids(string $value): array {
        return array_values(array_unique(array_filter(array_map('absint', preg_split('/[^0-9]+/', $value) ?: []))));
    }

    private static function sanitize_slugs(string $value): string {
        $slugs = array_filter(array_map('sanitize_title', preg_split('/[,\n]+/', wp_unslash($value)) ?: []));
        return implode(',', array_values(array_unique($slugs)));
    }

    private static function sanitize_urls(string $value): string {
        $urls = [];
        foreach (preg_split('/\R+/', wp_unslash($value)) ?: [] as $url) {
            $url = esc_url_raw(trim($url));
            if ($url) {
                $urls[] = $url;
            }
        }
        return implode("\n", array_values(array_unique($urls)));
    }

    private static function sanitize_revision_history(string $value): string {
        $decoded = json_decode(wp_unslash($value), true);
        if (!is_array($decoded)) {
            return '';
        }
        $clean = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean[] = [
                'version' => sanitize_text_field((string) ($row['version'] ?? '')),
                'date'    => self::sanitize_date((string) ($row['date'] ?? '')),
                'status'  => sanitize_text_field((string) ($row['status'] ?? '')),
                'summary' => sanitize_textarea_field((string) ($row['summary'] ?? '')),
            ];
        }
        return wp_json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function default_document_id(int $post_id): string {
        return 'SC-FND-' . str_pad((string) max(1, $post_id), 4, '0', STR_PAD_LEFT);
    }

    public function template_include(string $template): string {
        if (is_singular('sc_foundation_doc')) {
            $custom = SC_LIBRARY_DIR . 'templates/single-sc_foundation_doc-v200.php';
            return is_readable($custom) ? $custom : $template;
        }
        if (is_post_type_archive('sc_foundation_doc')) {
            $custom = SC_LIBRARY_DIR . 'templates/archive-sc_foundation_doc-v200.php';
            return is_readable($custom) ? $custom : $template;
        }
        return $template;
    }

    public function public_assets(): void {
        $post_id = get_queried_object_id();
        $has_catalog = $post_id && has_shortcode((string) get_post_field('post_content', $post_id), 'sc_foundations_catalog');
        if (!is_singular('sc_foundation_doc') && !is_post_type_archive('sc_foundation_doc') && !$has_catalog) {
            return;
        }
        wp_enqueue_style('sc-library-foundation-system-v200', SC_LIBRARY_URL . 'assets/css/sc-library-foundation-system-v200.css', [], SC_LIBRARY_FOUNDATIONS_VERSION);
        wp_enqueue_script('sc-library-foundation-system-v200', SC_LIBRARY_URL . 'assets/js/sc-library-foundation-system-v200.js', [], SC_LIBRARY_FOUNDATIONS_VERSION, true);
    }

    public function admin_assets(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'sc_foundation_doc') {
            return;
        }
        wp_enqueue_style('sc-library-foundation-system-v200', SC_LIBRARY_URL . 'assets/css/sc-library-foundation-system-v200.css', [], SC_LIBRARY_FOUNDATIONS_VERSION);
    }

    public function render_single(int $post_id): string {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sc_foundation_doc' || $post->post_status !== 'publish') {
            return '';
        }

        $metadata = $this->metadata($post_id);
        [$body, $toc] = $this->prepare_body((string) $post->post_content);
        $citation = $this->citation($post_id, $metadata);
        $related = $this->related_documents($post_id, $metadata);
        $revisions = json_decode((string) $metadata['revision_history'], true);
        $revisions = is_array($revisions) ? $revisions : [];
        $pdf_url = $this->pdf_url($post_id);

        ob_start();
        ?>
        <main class="sc-fnd-document" id="main-content">
            <header class="sc-fnd-masthead">
                <div class="sc-fnd-kicker"><?php esc_html_e('Sustainable Catalyst', 'sustainable-catalyst-library'); ?> <span aria-hidden="true">/</span> <?php esc_html_e('Foundation Document', 'sustainable-catalyst-library'); ?></div>
                <h1><?php echo esc_html(get_the_title($post)); ?></h1>
                <?php if ($metadata['subtitle']) : ?><p class="sc-fnd-subtitle"><?php echo esc_html($metadata['subtitle']); ?></p><?php endif; ?>
                <div class="sc-fnd-badges" aria-label="<?php esc_attr_e('Document classification', 'sustainable-catalyst-library'); ?>">
                    <span class="sc-fnd-badge sc-fnd-status-<?php echo esc_attr($metadata['status']); ?>"><?php echo esc_html(self::STATUSES[$metadata['status']] ?? $metadata['status']); ?></span>
                    <span class="sc-fnd-badge"><?php echo esc_html(self::TYPES[$metadata['record_type']] ?? $metadata['record_type']); ?></span>
                    <span class="sc-fnd-badge"><?php echo esc_html(self::AUTHORITIES[$metadata['authority_level']] ?? $metadata['authority_level']); ?></span>
                </div>
            </header>

            <?php echo $this->metadata_panel($metadata); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <div class="sc-fnd-actions" aria-label="<?php esc_attr_e('Document actions', 'sustainable-catalyst-library'); ?>">
                <?php if ($pdf_url) : ?>
                    <a class="sc-fnd-button" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF', 'sustainable-catalyst-library'); ?></a>
                    <?php if ((int) get_post_meta($post_id, '_sc_foundation_allow_download', true) !== 0) : ?><a class="sc-fnd-button" href="<?php echo esc_url($pdf_url); ?>" download><?php esc_html_e('Download PDF', 'sustainable-catalyst-library'); ?></a><?php endif; ?>
                <?php endif; ?>
                <button class="sc-fnd-button" type="button" data-sc-fnd-copy-citation data-citation="<?php echo esc_attr($citation); ?>"><?php esc_html_e('Copy citation', 'sustainable-catalyst-library'); ?></button>
                <button class="sc-fnd-button" type="button" data-sc-fnd-print><?php esc_html_e('Print', 'sustainable-catalyst-library'); ?></button>
            </div>

            <div class="sc-fnd-layout">
                <?php if ($metadata['show_toc'] && $toc) : ?>
                    <aside class="sc-fnd-toc" aria-label="<?php esc_attr_e('Table of contents', 'sustainable-catalyst-library'); ?>">
                        <button class="sc-fnd-toc-toggle" type="button" aria-expanded="true"><?php esc_html_e('Contents', 'sustainable-catalyst-library'); ?></button>
                        <ol><?php foreach ($toc as $item) : ?><li class="level-<?php echo esc_attr((string) $item['level']); ?>"><a href="#<?php echo esc_attr($item['id']); ?>"><?php echo esc_html($item['label']); ?></a></li><?php endforeach; ?></ol>
                    </aside>
                <?php endif; ?>
                <article class="sc-fnd-body">
                    <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                    <section class="sc-fnd-authority" aria-labelledby="sc-fnd-authority-heading">
                        <h2 id="sc-fnd-authority-heading"><?php esc_html_e('Document authority', 'sustainable-catalyst-library'); ?></h2>
                        <p><?php echo esc_html($this->authority_statement($metadata)); ?></p>
                    </section>

                    <section class="sc-fnd-citation" aria-labelledby="sc-fnd-citation-heading">
                        <h2 id="sc-fnd-citation-heading"><?php esc_html_e('Cite this document', 'sustainable-catalyst-library'); ?></h2>
                        <p class="sc-fnd-citation-text"><?php echo esc_html($citation); ?></p>
                    </section>

                    <?php if ($related) : ?>
                        <section class="sc-fnd-related" aria-labelledby="sc-fnd-related-heading">
                            <h2 id="sc-fnd-related-heading"><?php esc_html_e('Related records', 'sustainable-catalyst-library'); ?></h2>
                            <ul><?php foreach ($related as $item) : ?><li><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></li><?php endforeach; ?></ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($revisions) : ?>
                        <section class="sc-fnd-revisions" aria-labelledby="sc-fnd-revisions-heading">
                            <h2 id="sc-fnd-revisions-heading"><?php esc_html_e('Revision history', 'sustainable-catalyst-library'); ?></h2>
                            <div class="sc-fnd-table-wrap" role="region" tabindex="0" aria-label="<?php esc_attr_e('Revision history table', 'sustainable-catalyst-library'); ?>">
                                <table><thead><tr><th><?php esc_html_e('Version', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Date', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Summary', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                                <?php foreach ($revisions as $revision) : ?><tr><td><?php echo esc_html((string) ($revision['version'] ?? '')); ?></td><td><?php echo esc_html((string) ($revision['date'] ?? '')); ?></td><td><?php echo esc_html((string) ($revision['status'] ?? '')); ?></td><td><?php echo esc_html((string) ($revision['summary'] ?? '')); ?></td></tr><?php endforeach; ?>
                                </tbody></table>
                            </div>
                        </section>
                    <?php endif; ?>
                </article>
            </div>

            <footer class="sc-fnd-footer">
                <strong><?php esc_html_e('Sustainable Catalyst — Foundations', 'sustainable-catalyst-library'); ?></strong>
                <span><?php echo esc_html($metadata['document_id']); ?> · <?php echo esc_html($metadata['version']); ?> · <?php echo esc_html(self::STATUSES[$metadata['status']] ?? $metadata['status']); ?></span>
                <?php if ($metadata['correction_url']) : ?><a href="<?php echo esc_url($metadata['correction_url']); ?>"><?php esc_html_e('Report a correction', 'sustainable-catalyst-library'); ?></a><?php endif; ?>
            </footer>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    private function metadata(int $post_id): array {
        $get = fn(string $key, $default = '') => get_post_meta($post_id, self::META_PREFIX . $key, true) ?: $default;
        return [
            'document_id'       => (string) $get('document_id', self::default_document_id($post_id)),
            'subtitle'          => (string) $get('subtitle'),
            'record_type'       => (string) $get('record_type', 'institutional-standard'),
            'authority_level'   => (string) $get('authority_level', 'institutional'),
            'status'            => (string) $get('status', 'draft'),
            'version'           => (string) get_post_meta($post_id, '_sc_foundation_version', true) ?: '1.0.0',
            'effective_date'    => (string) $get('effective_date'),
            'last_reviewed'     => (string) $get('last_reviewed'),
            'review_cycle'      => (string) $get('review_cycle', 'Annual'),
            'owner'             => (string) $get('owner', 'Sustainable Catalyst'),
            'canonical_record'  => (string) $get('canonical_record', 'living-html'),
            'supersedes_ids'    => (string) $get('supersedes_ids'),
            'superseded_by_id'  => absint($get('superseded_by_id')),
            'correction_url'    => (string) $get('correction_url'),
            'revision_history'  => (string) $get('revision_history'),
            'show_toc'          => (bool) $get('show_toc', 1),
            'publisher'         => (string) get_post_meta($post_id, '_sc_foundation_publisher', true) ?: 'Sustainable Catalyst',
            'author'            => (string) get_post_meta($post_id, '_sc_foundation_author', true) ?: 'Sustainable Catalyst',
        ];
    }

    private function metadata_panel(array $metadata): string {
        $rows = [
            __('Document ID', 'sustainable-catalyst-library') => $metadata['document_id'],
            __('Version', 'sustainable-catalyst-library') => $metadata['version'],
            __('Status', 'sustainable-catalyst-library') => self::STATUSES[$metadata['status']] ?? $metadata['status'],
            __('Authority', 'sustainable-catalyst-library') => self::AUTHORITIES[$metadata['authority_level']] ?? $metadata['authority_level'],
            __('Effective date', 'sustainable-catalyst-library') => $metadata['effective_date'],
            __('Last reviewed', 'sustainable-catalyst-library') => $metadata['last_reviewed'],
            __('Document owner', 'sustainable-catalyst-library') => $metadata['owner'],
            __('Review cycle', 'sustainable-catalyst-library') => $metadata['review_cycle'],
            __('Canonical record', 'sustainable-catalyst-library') => self::CANONICAL_TYPES[$metadata['canonical_record']] ?? $metadata['canonical_record'],
        ];
        ob_start(); ?>
        <dl class="sc-fnd-metadata">
            <?php foreach ($rows as $label => $value) : if (!$value) continue; ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html((string) $value); ?></dd></div><?php endforeach; ?>
        </dl>
        <?php return (string) ob_get_clean();
    }

    private function prepare_body(string $content): array {
        if (has_blocks($content)) {
            $content = do_blocks($content);
        } else {
            $content = wpautop(wptexturize($content));
        }
        $content = do_shortcode($content);
        $toc = [];
        $used = [];
        $content = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', static function(array $matches) use (&$toc, &$used): string {
            $level = (int) $matches[1];
            $attributes = (string) $matches[2];
            $inner = (string) $matches[3];
            $label = trim(wp_strip_all_tags($inner));
            if ($label === '') {
                return $matches[0];
            }
            if (preg_match('/\sid=["\']([^"\']+)["\']/', $attributes, $id_match)) {
                $id = sanitize_title($id_match[1]);
            } else {
                $base = sanitize_title($label) ?: 'section';
                $id = $base;
                $counter = 2;
                while (isset($used[$id])) {
                    $id = $base . '-' . $counter++;
                }
                $attributes .= ' id="' . esc_attr($id) . '"';
            }
            $used[$id] = true;
            $toc[] = ['level' => $level, 'id' => $id, 'label' => $label];
            return '<h' . $level . $attributes . '>' . $inner . '</h' . $level . '>';
        }, $content) ?: $content;
        return [$content, $toc];
    }

    private function authority_statement(array $metadata): string {
        if ($metadata['status'] === 'superseded') {
            return __('This record is retained for historical reference and does not govern where it conflicts with a current approved record.', 'sustainable-catalyst-library');
        }
        if ($metadata['status'] === 'historical-record') {
            return __('This is a historical record preserved by the Knowledge Library. It is not a current institutional authority.', 'sustainable-catalyst-library');
        }
        if ($metadata['canonical_record'] === 'fixed-pdf') {
            return __('The approved PDF snapshot is the controlling record for this version. The HTML page provides discovery, metadata, and accessible navigation.', 'sustainable-catalyst-library');
        }
        return __('This living HTML document is the current authoritative record within its defined scope. Fixed PDF editions preserve approved snapshots and earlier versions remain available for historical reference.', 'sustainable-catalyst-library');
    }

    private function citation(int $post_id, array $metadata): string {
        $year = $metadata['effective_date'] ? substr($metadata['effective_date'], 0, 4) : get_the_date('Y', $post_id);
        return sprintf('%s (%s) %s. Version %s. %s. Available at: %s', $metadata['author'], $year, get_the_title($post_id), $metadata['version'], $metadata['publisher'], get_permalink($post_id));
    }

    private function pdf_url(int $post_id): string {
        $attachment_id = absint(get_post_meta($post_id, '_sc_foundation_pdf_attachment_id', true));
        if ($attachment_id) {
            return (string) wp_get_attachment_url($attachment_id);
        }
        return (string) get_post_meta($post_id, '_sc_foundation_external_pdf_url', true);
    }

    private function related_documents(int $post_id, array $metadata): array {
        $ids = self::sanitize_ids((string) get_post_meta($post_id, '_sc_foundation_related_ids', true));
        $ids = array_merge($ids, self::sanitize_ids($metadata['supersedes_ids']));
        if ($metadata['superseded_by_id']) {
            $ids[] = (int) $metadata['superseded_by_id'];
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id !== $post_id && get_post_status($id) === 'publish')));
        return array_slice($ids, 0, 24);
    }

    public function catalog_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => __('Foundation Documents', 'sustainable-catalyst-library'),
            'status' => '',
            'type' => '',
            'limit' => 100,
            'show_filters' => 'yes',
        ], $atts, 'sc_foundations_catalog');

        $status = sanitize_key((string) ($_GET['foundation_status'] ?? $atts['status']));
        $type = sanitize_key((string) ($_GET['foundation_type'] ?? $atts['type']));
        $search = sanitize_text_field(wp_unslash((string) ($_GET['foundation_search'] ?? '')));
        $meta_query = [];
        if ($status && array_key_exists($status, self::STATUSES)) {
            $meta_query[] = ['key' => self::META_PREFIX . 'status', 'value' => $status];
        }
        if ($type && array_key_exists($type, self::TYPES)) {
            $meta_query[] = ['key' => self::META_PREFIX . 'record_type', 'value' => $type];
        }
        $query = new WP_Query([
            'post_type' => 'sc_foundation_doc',
            'post_status' => 'publish',
            'posts_per_page' => min(250, max(1, absint($atts['limit']))),
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
            's' => $search,
            'meta_query' => $meta_query,
        ]);

        ob_start(); ?>
        <section class="sc-fnd-catalog">
            <header><p class="sc-fnd-kicker"><?php esc_html_e('Sustainable Catalyst Foundations', 'sustainable-catalyst-library'); ?></p><h2><?php echo esc_html((string) $atts['title']); ?></h2></header>
            <?php if ($atts['show_filters'] === 'yes') : ?>
                <form class="sc-fnd-catalog-filters" method="get">
                    <label><span><?php esc_html_e('Search', 'sustainable-catalyst-library'); ?></span><input type="search" name="foundation_search" value="<?php echo esc_attr($search); ?>"></label>
                    <label><span><?php esc_html_e('Type', 'sustainable-catalyst-library'); ?></span><select name="foundation_type"><option value=""><?php esc_html_e('All types', 'sustainable-catalyst-library'); ?></option><?php foreach (self::TYPES as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></span><select name="foundation_status"><option value=""><?php esc_html_e('All statuses', 'sustainable-catalyst-library'); ?></option><?php foreach (self::STATUSES as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <button class="sc-fnd-button" type="submit"><?php esc_html_e('Apply filters', 'sustainable-catalyst-library'); ?></button>
                </form>
            <?php endif; ?>
            <div class="sc-fnd-catalog-grid">
                <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); $m = $this->metadata(get_the_ID()); ?>
                    <article class="sc-fnd-card">
                        <div class="sc-fnd-card-meta"><span><?php echo esc_html($m['document_id']); ?></span><span><?php echo esc_html(self::STATUSES[$m['status']] ?? $m['status']); ?></span></div>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if ($m['subtitle']) : ?><p><?php echo esc_html($m['subtitle']); ?></p><?php elseif (has_excerpt()) : ?><p><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                        <div class="sc-fnd-card-footer"><span><?php echo esc_html(self::TYPES[$m['record_type']] ?? $m['record_type']); ?></span><span><?php echo esc_html__('Version', 'sustainable-catalyst-library') . ' ' . esc_html($m['version']); ?></span></div>
                    </article>
                <?php endwhile; wp_reset_postdata(); else : ?><p class="sc-fnd-empty"><?php esc_html_e('No Foundation Documents match these filters.', 'sustainable-catalyst-library'); ?></p><?php endif; ?>
            </div>
        </section>
        <?php return (string) ob_get_clean();
    }

    public function render_archive(): string {
        return $this->catalog_shortcode(['title' => __('Foundation Documents', 'sustainable-catalyst-library'), 'show_filters' => 'yes']);
    }

    public function admin_columns(array $columns): array {
        $columns['sc_fnd_id'] = __('Document ID', 'sustainable-catalyst-library');
        $columns['sc_fnd_type'] = __('Type', 'sustainable-catalyst-library');
        $columns['sc_fnd_status'] = __('Authority status', 'sustainable-catalyst-library');
        $columns['sc_fnd_version'] = __('Version', 'sustainable-catalyst-library');
        return $columns;
    }

    public function admin_column(string $column, int $post_id): void {
        $metadata = $this->metadata($post_id);
        if ($column === 'sc_fnd_id') echo esc_html($metadata['document_id']);
        if ($column === 'sc_fnd_type') echo esc_html(self::TYPES[$metadata['record_type']] ?? $metadata['record_type']);
        if ($column === 'sc_fnd_status') echo esc_html(self::STATUSES[$metadata['status']] ?? $metadata['status']);
        if ($column === 'sc_fnd_version') echo esc_html($metadata['version']);
    }

    public function register_rest_routes(): void {
        register_rest_route('sustainable-catalyst/v1', '/library/foundations/catalog', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_catalog'],
            'args' => [
                'status' => ['sanitize_callback' => 'sanitize_key'],
                'type' => ['sanitize_callback' => 'sanitize_key'],
                'search' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function rest_catalog(WP_REST_Request $request): WP_REST_Response {
        $meta_query = [];
        $status = sanitize_key((string) $request->get_param('status'));
        $type = sanitize_key((string) $request->get_param('type'));
        if ($status && array_key_exists($status, self::STATUSES)) $meta_query[] = ['key' => self::META_PREFIX . 'status', 'value' => $status];
        if ($type && array_key_exists($type, self::TYPES)) $meta_query[] = ['key' => self::META_PREFIX . 'record_type', 'value' => $type];
        $query = new WP_Query(['post_type' => 'sc_foundation_doc', 'post_status' => 'publish', 'posts_per_page' => 250, 's' => (string) $request->get_param('search'), 'meta_query' => $meta_query, 'orderby' => 'title', 'order' => 'ASC']);
        $records = [];
        foreach ($query->posts as $post) {
            $m = $this->metadata($post->ID);
            $records[] = [
                'id' => $post->ID,
                'document_id' => $m['document_id'],
                'title' => get_the_title($post),
                'subtitle' => $m['subtitle'],
                'type' => $m['record_type'],
                'status' => $m['status'],
                'authority' => $m['authority_level'],
                'version' => $m['version'],
                'effective_date' => $m['effective_date'],
                'canonical_url' => get_permalink($post),
                'pdf_url' => $this->pdf_url($post->ID),
            ];
        }
        return new WP_REST_Response(['schema' => 'sc-foundations-catalog/2.0', 'version' => SC_LIBRARY_FOUNDATIONS_VERSION, 'count' => count($records), 'records' => $records], 200);
    }
}

SC_Library_Foundation_System_V200::instance()->register_hooks();
