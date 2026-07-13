<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Foundations and Documentation Library.
 *
 * Documentation records remain canonical WordPress posts. This class adds
 * authority metadata, curated collection membership, public documentation
 * endpoints, and a compact Foundations-facing interface.
 */
final class SC_Library_Documentation {
    public const COLLECTION_SLUG = 'foundations';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('init', [$this, 'ensure_default_terms'], 12);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save'], 25, 3);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_shortcode('sc_foundations_library', [self::class, 'render_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_documentation', 1);
    }

    public static function statuses(): array {
        return [
            'living' => __('Living documentation', 'sustainable-catalyst-library'),
            'current' => __('Current', 'sustainable-catalyst-library'),
            'pdf_snapshot' => __('PDF snapshot', 'sustainable-catalyst-library'),
            'draft' => __('Draft', 'sustainable-catalyst-library'),
            'superseded' => __('Superseded', 'sustainable-catalyst-library'),
            'archived' => __('Archived', 'sustainable-catalyst-library'),
        ];
    }

    public static function authority_types(): array {
        return [
            'webpage' => __('Current public webpage', 'sustainable-catalyst-library'),
            'repository' => __('Repository documentation', 'sustainable-catalyst-library'),
            'methodology' => __('Methodology page', 'sustainable-catalyst-library'),
            'release' => __('Repository release record', 'sustainable-catalyst-library'),
            'pdf' => __('Published PDF', 'sustainable-catalyst-library'),
            'archive' => __('Archived document', 'sustainable-catalyst-library'),
            'custom' => __('Custom authoritative source', 'sustainable-catalyst-library'),
        ];
    }

    public static function document_types(): array {
        return [
            'institution' => __('Institution and foundations', 'sustainable-catalyst-library'),
            'brand' => __('Brand and identity', 'sustainable-catalyst-library'),
            'mission' => __('Mission and positioning', 'sustainable-catalyst-library'),
            'product' => __('Product brief', 'sustainable-catalyst-library'),
            'technical' => __('Technical documentation', 'sustainable-catalyst-library'),
            'methodology' => __('Methodology and trust', 'sustainable-catalyst-library'),
            'policy' => __('Policy or licensing', 'sustainable-catalyst-library'),
            'release' => __('Release documentation', 'sustainable-catalyst-library'),
            'historical' => __('Historical brief or snapshot', 'sustainable-catalyst-library'),
            'custom' => __('Custom documentation', 'sustainable-catalyst-library'),
        ];
    }

    public static function default_categories(): array {
        return [
            'institutional-foundations' => 'Institutional Foundations',
            'brand-and-identity' => 'Brand and Identity',
            'mission-and-positioning' => 'Mission and Positioning',
            'library-documentation' => 'Library Documentation',
            'lab-documentation' => 'Lab Documentation',
            'platform-documentation' => 'Platform Documentation',
            'product-briefs' => 'Product Briefs',
            'architecture' => 'Architecture',
            'methodology-and-trust' => 'Methodology and Trust',
            'policies-and-licensing' => 'Policies and Licensing',
            'release-documentation' => 'Release Documentation',
            'historical-and-archived' => 'Historical and Archived Documents',
        ];
    }

    public function ensure_default_terms(): void {
        if (!taxonomy_exists(SC_Library_Taxonomies::COLLECTION) || !taxonomy_exists(SC_Library_Taxonomies::DOCUMENT_CATEGORY)) {
            return;
        }

        if (!term_exists(self::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION)) {
            wp_insert_term('Foundations Documentation Library', SC_Library_Taxonomies::COLLECTION, [
                'slug' => self::COLLECTION_SLUG,
                'description' => 'Curated institutional, product, methodology, policy, release, and archival documentation.',
            ]);
        }

        foreach (self::default_categories() as $slug => $name) {
            if (!term_exists($slug, SC_Library_Taxonomies::DOCUMENT_CATEGORY)) {
                wp_insert_term($name, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['slug' => $slug]);
            }
        }
    }

    public function add_meta_boxes(): void {
        foreach ($this->indexer->configured_post_types() as $post_type) {
            add_meta_box(
                'sc-library-documentation-authority',
                __('Documentation Authority', 'sustainable-catalyst-library'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sc_library_save_documentation', 'sc_library_documentation_nonce');
        $meta = $this->metadata($post->ID);
        $dependencies = implode(',', array_map('intval', $meta['dependency_ids']));
        ?>
        <div class="sc-library-admin-record sc-library-admin-documentation">
            <p class="description"><?php esc_html_e('Assign the Foundations collection and a Documentation Category using the taxonomy panels. Use this panel to identify which source is current and authoritative.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-admin-grid">
                <label><span><?php esc_html_e('Documentation status', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_library_doc_status">
                        <?php foreach (self::statuses() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['status'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Document type', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_library_doc_type">
                        <?php foreach (self::document_types() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['document_type'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Version', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_library_doc_version" value="<?php echo esc_attr($meta['version']); ?>" placeholder="1.11.0 or July 2026"></label>
                <label><span><?php esc_html_e('Responsible area', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_library_doc_responsible_area" value="<?php echo esc_attr($meta['responsible_area']); ?>" placeholder="Library, Lab, Platform, Institution"></label>
                <label><span><?php esc_html_e('Authoritative source type', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_library_doc_authority_type">
                        <?php foreach (self::authority_types() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['authority_type'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Last reviewed', 'sustainable-catalyst-library'); ?></span><input type="date" name="sc_library_doc_last_reviewed" value="<?php echo esc_attr($meta['last_reviewed']); ?>"></label>
                <label><span><?php esc_html_e('Review interval (months)', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" max="120" name="sc_library_doc_review_interval" value="<?php echo esc_attr((string) $meta['review_interval']); ?>"></label>
                <label><span><?php esc_html_e('Featured living document', 'sustainable-catalyst-library'); ?></span><span><input type="checkbox" name="sc_library_doc_featured" value="1" <?php checked($meta['featured'], true); ?>> <?php esc_html_e('Feature in the Foundations documentation view.', 'sustainable-catalyst-library'); ?></span></label>
            </div>

            <div class="sc-library-admin-grid sc-library-admin-grid--wide">
                <label><span><?php esc_html_e('Authoritative source URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_authority_url" value="<?php echo esc_attr($meta['authority_url']); ?>" placeholder="https://…"></label>
                <label><span><?php esc_html_e('Current webpage URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_webpage_url" value="<?php echo esc_attr($meta['webpage_url']); ?>" placeholder="https://…"></label>
                <label><span><?php esc_html_e('Repository documentation URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_repository_url" value="<?php echo esc_attr($meta['repository_url']); ?>" placeholder="https://github.com/…"></label>
                <label><span><?php esc_html_e('PDF snapshot URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_pdf_url" value="<?php echo esc_attr($meta['pdf_url']); ?>" placeholder="https://…pdf"></label>
                <label><span><?php esc_html_e('Release record URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_release_url" value="<?php echo esc_attr($meta['release_url']); ?>" placeholder="https://github.com/…/releases/…"></label>
                <label><span><?php esc_html_e('Correction or contribution URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_doc_correction_url" value="<?php echo esc_attr($meta['correction_url']); ?>" placeholder="https://…"></label>
                <label><span><?php esc_html_e('Supersedes record ID', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" name="sc_library_doc_supersedes_id" value="<?php echo esc_attr((string) $meta['supersedes_id']); ?>"></label>
                <label><span><?php esc_html_e('Superseded by record ID', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" name="sc_library_doc_superseded_by_id" value="<?php echo esc_attr((string) $meta['superseded_by_id']); ?>"></label>
                <label><span><?php esc_html_e('Dependency record IDs', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_library_doc_dependency_ids" value="<?php echo esc_attr($dependencies); ?>" placeholder="102, 305, 818"><small><?php esc_html_e('Comma-separated WordPress post IDs.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Authority note', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_library_doc_authority_note"><?php echo esc_textarea($meta['authority_note']); ?></textarea><small><?php esc_html_e('Explain why the selected source governs the current record.', 'sustainable-catalyst-library'); ?></small></label>
            </div>
        </div>
        <?php
    }

    public function save(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, $this->indexer->configured_post_types(), true)) {
            return;
        }
        if (!isset($_POST['sc_library_documentation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_documentation_nonce'])), 'sc_library_save_documentation')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = sanitize_key((string) ($_POST['sc_library_doc_status'] ?? 'current'));
        $type = sanitize_key((string) ($_POST['sc_library_doc_type'] ?? 'custom'));
        $authority_type = sanitize_key((string) ($_POST['sc_library_doc_authority_type'] ?? 'webpage'));
        if (!array_key_exists($status, self::statuses())) {
            $status = 'current';
        }
        if (!array_key_exists($type, self::document_types())) {
            $type = 'custom';
        }
        if (!array_key_exists($authority_type, self::authority_types())) {
            $authority_type = 'webpage';
        }

        $values = [
            '_sc_library_doc_status' => $status,
            '_sc_library_doc_type' => $type,
            '_sc_library_doc_version' => sanitize_text_field(wp_unslash((string) ($_POST['sc_library_doc_version'] ?? ''))),
            '_sc_library_doc_responsible_area' => sanitize_text_field(wp_unslash((string) ($_POST['sc_library_doc_responsible_area'] ?? ''))),
            '_sc_library_doc_authority_type' => $authority_type,
            '_sc_library_doc_authority_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_authority_url'] ?? ''))),
            '_sc_library_doc_webpage_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_webpage_url'] ?? ''))),
            '_sc_library_doc_repository_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_repository_url'] ?? ''))),
            '_sc_library_doc_pdf_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_pdf_url'] ?? ''))),
            '_sc_library_doc_release_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_release_url'] ?? ''))),
            '_sc_library_doc_correction_url' => esc_url_raw(wp_unslash((string) ($_POST['sc_library_doc_correction_url'] ?? ''))),
            '_sc_library_doc_last_reviewed' => $this->sanitize_date((string) ($_POST['sc_library_doc_last_reviewed'] ?? '')),
            '_sc_library_doc_review_interval' => min(120, max(0, absint($_POST['sc_library_doc_review_interval'] ?? 12))),
            '_sc_library_doc_featured' => isset($_POST['sc_library_doc_featured']) ? 1 : 0,
            '_sc_library_doc_supersedes_id' => absint($_POST['sc_library_doc_supersedes_id'] ?? 0),
            '_sc_library_doc_superseded_by_id' => absint($_POST['sc_library_doc_superseded_by_id'] ?? 0),
            '_sc_library_doc_dependency_ids' => implode(',', $this->sanitize_id_list((string) ($_POST['sc_library_doc_dependency_ids'] ?? ''))),
            '_sc_library_doc_authority_note' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_library_doc_authority_note'] ?? ''))),
        ];

        foreach ($values as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    private function sanitize_date(string $value): string {
        $value = sanitize_text_field(wp_unslash($value));
        if ($value === '') {
            return '';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function sanitize_id_list(string $value): array {
        $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', wp_unslash($value)) ?: []));
        return array_values(array_unique($ids));
    }

    public function register_rest_routes(): void {
        register_rest_route('sustainable-catalyst/v1/library', '/documentation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_documents'],
            'permission_callback' => '__return_true',
            'args' => $this->documentation_args(),
        ]);
        register_rest_route('sustainable-catalyst/v1/library', '/documentation/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_categories'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('sustainable-catalyst/v1/library', '/documentation/statuses', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_statuses'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('sustainable-catalyst/v1/library', '/documentation/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_document'],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['sanitize_callback' => 'absint']],
        ]);
        register_rest_route('sustainable-catalyst/v1/library', '/collections/foundations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_foundations_collection'],
            'permission_callback' => '__return_true',
            'args' => $this->documentation_args(),
        ]);
    }

    private function documentation_args(): array {
        return [
            'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'category' => ['sanitize_callback' => 'sanitize_title', 'default' => ''],
            'status' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'area' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'include_archived' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false],
            'featured' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false],
            'sort' => ['sanitize_callback' => 'sanitize_key', 'default' => 'updated'],
            'page' => ['sanitize_callback' => 'absint', 'default' => 1],
            'per_page' => ['sanitize_callback' => 'absint', 'default' => 12],
        ];
    }

    public function rest_documents(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response($this->query_documents($request));
    }

    public function rest_foundations_collection(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->query_documents($request);
        $payload['collection'] = [
            'slug' => self::COLLECTION_SLUG,
            'name' => __('Foundations Documentation Library', 'sustainable-catalyst-library'),
            'description' => __('A curated institutional view of current webpages, repository documentation, methodology records, policies, PDF snapshots, and historical records.', 'sustainable-catalyst-library'),
        ];
        return rest_ensure_response($payload);
    }

    private function query_documents(WP_REST_Request $request): array {
        global $wpdb;

        $search = trim((string) $request['search']);
        $category = sanitize_title((string) $request['category']);
        $status = sanitize_key((string) $request['status']);
        $area = trim((string) $request['area']);
        $include_archived = rest_sanitize_boolean($request['include_archived']);
        $featured = rest_sanitize_boolean($request['featured']);
        $sort = sanitize_key((string) $request['sort']);
        $page = max(1, absint($request['page']));
        $per_page = min(50, max(1, absint($request['per_page'])));

        $collection_term = get_term_by('slug', self::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION);
        if (!$collection_term || is_wp_error($collection_term)) {
            return ['items' => [], 'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => 0, 'total_pages' => 0], 'facets' => $this->facets([])];
        }

        $object_ids = get_objects_in_term((int) $collection_term->term_id, SC_Library_Taxonomies::COLLECTION);
        if (is_wp_error($object_ids) || !$object_ids) {
            return ['items' => [], 'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => 0, 'total_pages' => 0], 'facets' => $this->facets([])];
        }

        $object_ids = array_values(array_unique(array_map('absint', $object_ids)));
        $placeholders = implode(',', array_fill(0, count($object_ids), '%d'));
        $values = $object_ids;
        $where = "status = 'publish' AND post_id IN ({$placeholders})";
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (title LIKE %s OR excerpt LIKE %s OR searchable_text LIKE %s)';
            array_push($values, $like, $like, $like);
        }

        $order_by = match ($sort) {
            'title' => 'title ASC',
            'oldest' => 'published_at ASC',
            'status' => 'modified_at DESC',
            default => 'modified_at DESC',
        };
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->indexer->table_name()} WHERE {$where} ORDER BY {$order_by}", ...$values), ARRAY_A) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $item = $this->hydrate_document($row);
            if (!$include_archived && !in_array($status, ['superseded', 'archived'], true) && in_array($item['status'], ['superseded', 'archived'], true)) {
                continue;
            }
            if ($item['status'] === 'draft' && !current_user_can('edit_posts')) {
                continue;
            }
            if ($status !== '' && $item['status'] !== $status) {
                continue;
            }
            if ($area !== '' && strcasecmp($item['responsible_area'], $area) !== 0) {
                continue;
            }
            if ($category !== '' && !in_array($category, array_column($item['categories'], 'slug'), true)) {
                continue;
            }
            if ($featured && !$item['featured']) {
                continue;
            }
            $items[] = $item;
        }

        if ($featured) {
            usort($items, static function (array $a, array $b): int {
                $rank = ['living' => 0, 'current' => 1, 'pdf_snapshot' => 2, 'draft' => 3, 'superseded' => 4, 'archived' => 5];
                return ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9) ?: strcmp((string) $b['modified_at'], (string) $a['modified_at']);
            });
        }

        $total = count($items);
        $offset = ($page - 1) * $per_page;
        $paged = array_slice($items, $offset, $per_page);

        return [
            'items' => $paged,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
            ],
            'facets' => $this->facets($items),
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'area' => $area,
                'include_archived' => $include_archived,
                'featured' => $featured,
                'sort' => $sort,
            ],
        ];
    }

    public function rest_categories(WP_REST_Request $request): WP_REST_Response {
        $terms = get_terms([
            'taxonomy' => SC_Library_Taxonomies::DOCUMENT_CATEGORY,
            'hide_empty' => false,
            'orderby' => 'name',
        ]);
        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
        }

        $items = [];
        foreach ($terms as $term) {
            $count = $this->count_foundation_term((int) $term->term_id);
            if ($count < 1) {
                continue;
            }
            $items[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $count,
                'parent' => (int) $term->parent,
            ];
        }
        return rest_ensure_response(['items' => $items]);
    }

    private function count_foundation_term(int $document_category_id): int {
        $collection = get_term_by('slug', self::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION);
        if (!$collection || is_wp_error($collection)) {
            return 0;
        }
        $query = new WP_Query([
            'post_type' => $this->indexer->configured_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'tax_query' => [
                'relation' => 'AND',
                ['taxonomy' => SC_Library_Taxonomies::COLLECTION, 'field' => 'term_id', 'terms' => [(int) $collection->term_id]],
                ['taxonomy' => SC_Library_Taxonomies::DOCUMENT_CATEGORY, 'field' => 'term_id', 'terms' => [$document_category_id]],
            ],
        ]);
        return (int) $query->found_posts;
    }

    public function rest_statuses(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'statuses' => array_map(static fn($label, $value) => ['value' => $value, 'label' => $label], self::statuses(), array_keys(self::statuses())),
            'authority_types' => array_map(static fn($label, $value) => ['value' => $value, 'label' => $label], self::authority_types(), array_keys(self::authority_types())),
            'document_types' => array_map(static fn($label, $value) => ['value' => $value, 'label' => $label], self::document_types(), array_keys(self::document_types())),
            'source_of_truth' => $this->source_of_truth_model(),
        ]);
    }

    public function rest_document(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $post_id = absint($request['id']);
        if (!$post_id || !has_term(self::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION, $post_id)) {
            return new WP_Error('sc_library_document_not_found', __('Documentation record not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->indexer->table_name()} WHERE post_id = %d AND status = 'publish'", $post_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('sc_library_document_not_indexed', __('Documentation record is not indexed.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        return rest_ensure_response($this->hydrate_document($row));
    }

    private function hydrate_document(array $row): array {
        $post_id = (int) $row['post_id'];
        $meta = $this->metadata($post_id);
        $meta['webpage_url'] = $meta['webpage_url'] ?: (string) $row['permalink'];
        $categories = wp_get_post_terms($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY);
        $categories = is_wp_error($categories) ? [] : array_map(static fn(WP_Term $term) => [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
        ], $categories);

        $related = [];
        foreach ($this->relationships->get_for_post($post_id, true) as $relation) {
            $other_id = $relation['direction'] === 'incoming' ? (int) $relation['source_post_id'] : (int) $relation['target_post_id'];
            $related[] = [
                'id' => $other_id,
                'title' => get_the_title($other_id),
                'url' => get_permalink($other_id),
                'relationship' => $relation['type_label'],
                'note' => $relation['note'],
            ];
        }

        $dependencies = array_values(array_filter(array_map(fn(int $id) => $this->record_reference($id), $meta['dependency_ids'])));
        $supersedes = $this->record_reference($meta['supersedes_id']);
        $superseded_by = $this->record_reference($meta['superseded_by_id']);
        $authority_url = $meta['authority_url'] ?: $this->authority_fallback($meta);
        if ($authority_url === '' && in_array($meta['authority_type'], ['webpage', 'methodology'], true)) {
            $authority_url = (string) $row['permalink'];
        }
        $full_library_url = add_query_arg('library_record', $post_id, (string) get_option('sc_library_main_page_url', home_url('/research-library/')));
        $warning = $this->authority_warning($meta, $superseded_by);

        return [
            'id' => $post_id,
            'record_identifier' => (string) ($row['record_identifier'] ?: sprintf('sc:library:%s:%d', sanitize_key((string) $row['post_type']), $post_id)),
            'title' => (string) $row['title'],
            'excerpt' => (string) $row['excerpt'],
            'url' => (string) $row['permalink'],
            'post_type' => (string) $row['post_type'],
            'status' => $meta['status'],
            'status_label' => self::statuses()[$meta['status']] ?? ucfirst($meta['status']),
            'document_type' => $meta['document_type'],
            'document_type_label' => self::document_types()[$meta['document_type']] ?? ucfirst($meta['document_type']),
            'version' => $meta['version'],
            'responsible_area' => $meta['responsible_area'],
            'authority_type' => $meta['authority_type'],
            'authority_type_label' => self::authority_types()[$meta['authority_type']] ?? ucfirst($meta['authority_type']),
            'authority_url' => $authority_url,
            'authority_note' => $meta['authority_note'],
            'webpage_url' => $meta['webpage_url'] ?: (string) $row['permalink'],
            'repository_url' => $meta['repository_url'],
            'pdf_url' => $meta['pdf_url'],
            'release_url' => $meta['release_url'],
            'correction_url' => $meta['correction_url'] ?: home_url('/feature-suggestions/'),
            'last_reviewed' => $meta['last_reviewed'],
            'review_interval' => $meta['review_interval'],
            'review_due' => $this->review_due($meta['last_reviewed'], $meta['review_interval']),
            'featured' => $meta['featured'],
            'published_at' => $this->rfc3339((string) $row['published_at']),
            'modified_at' => $this->rfc3339((string) $row['modified_at']),
            'categories' => $categories,
            'related' => $related,
            'dependencies' => $dependencies,
            'supersedes' => $supersedes,
            'superseded_by' => $superseded_by,
            'warning' => $warning,
            'authoritative' => $authority_url !== '',
            'full_library_url' => $full_library_url,
            'actions' => array_values(array_filter([
                $meta['webpage_url'] || $row['permalink'] ? ['type' => 'webpage', 'label' => __('Open current webpage', 'sustainable-catalyst-library'), 'url' => $meta['webpage_url'] ?: (string) $row['permalink']] : null,
                ['type' => 'record', 'label' => __('View full Library record', 'sustainable-catalyst-library'), 'url' => $full_library_url],
                $meta['pdf_url'] ? ['type' => 'pdf', 'label' => __('Download PDF snapshot', 'sustainable-catalyst-library'), 'url' => $meta['pdf_url']] : null,
                $meta['repository_url'] ? ['type' => 'repository', 'label' => __('View repository', 'sustainable-catalyst-library'), 'url' => $meta['repository_url']] : null,
                $meta['release_url'] ? ['type' => 'release', 'label' => __('View release record', 'sustainable-catalyst-library'), 'url' => $meta['release_url']] : null,
                ($supersedes || $superseded_by || $dependencies || $related) ? ['type' => 'history', 'label' => __('View document history', 'sustainable-catalyst-library'), 'url' => $full_library_url . '#document-history'] : null,
                ['type' => 'correction', 'label' => __('Report a correction', 'sustainable-catalyst-library'), 'url' => $meta['correction_url'] ?: home_url('/feature-suggestions/')],
            ])),
        ];
    }

    private function metadata(int $post_id): array {
        $status = sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_status', true));
        $document_type = sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_type', true));
        $authority_type = sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_authority_type', true));
        return [
            'status' => array_key_exists($status, self::statuses()) ? $status : 'current',
            'document_type' => array_key_exists($document_type, self::document_types()) ? $document_type : 'custom',
            'version' => (string) get_post_meta($post_id, '_sc_library_doc_version', true),
            'responsible_area' => (string) get_post_meta($post_id, '_sc_library_doc_responsible_area', true),
            'authority_type' => array_key_exists($authority_type, self::authority_types()) ? $authority_type : 'webpage',
            'authority_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_authority_url', true)),
            'authority_note' => (string) get_post_meta($post_id, '_sc_library_doc_authority_note', true),
            'webpage_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_webpage_url', true)),
            'repository_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_repository_url', true)),
            'pdf_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_pdf_url', true)),
            'release_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_release_url', true)),
            'correction_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_correction_url', true)),
            'last_reviewed' => (string) get_post_meta($post_id, '_sc_library_doc_last_reviewed', true),
            'review_interval' => max(0, absint(get_post_meta($post_id, '_sc_library_doc_review_interval', true) ?: 12)),
            'featured' => (bool) get_post_meta($post_id, '_sc_library_doc_featured', true),
            'supersedes_id' => absint(get_post_meta($post_id, '_sc_library_doc_supersedes_id', true)),
            'superseded_by_id' => absint(get_post_meta($post_id, '_sc_library_doc_superseded_by_id', true)),
            'dependency_ids' => $this->sanitize_id_list((string) get_post_meta($post_id, '_sc_library_doc_dependency_ids', true)),
        ];
    }

    private function authority_fallback(array $meta): string {
        return match ($meta['authority_type']) {
            'repository' => $meta['repository_url'],
            'release' => $meta['release_url'],
            'pdf', 'archive' => $meta['pdf_url'],
            default => $meta['webpage_url'],
        };
    }

    private function authority_warning(array $meta, ?array $superseded_by): array {
        if ($meta['status'] === 'superseded') {
            return ['level' => 'warning', 'message' => $superseded_by
                ? sprintf(__('This record has been superseded by %s.', 'sustainable-catalyst-library'), $superseded_by['title'])
                : __('This record has been superseded. Consult the authoritative source before relying on it.', 'sustainable-catalyst-library')];
        }
        if ($meta['status'] === 'archived') {
            return ['level' => 'neutral', 'message' => __('This is an archived historical record and is not the current source of truth.', 'sustainable-catalyst-library')];
        }
        if ($meta['status'] === 'draft') {
            return ['level' => 'warning', 'message' => __('This documentation is a draft and should not be treated as final.', 'sustainable-catalyst-library')];
        }
        if (($meta['status'] === 'pdf_snapshot' || $meta['authority_type'] === 'pdf') && $meta['webpage_url']) {
            return ['level' => 'info', 'message' => __('This PDF is a dated snapshot. The current webpage is the authoritative source.', 'sustainable-catalyst-library')];
        }
        if ($meta['authority_type'] === 'repository') {
            return ['level' => 'info', 'message' => __('Technical behavior is governed by the versioned repository documentation linked below.', 'sustainable-catalyst-library')];
        }
        return [];
    }

    private function review_due(string $last_reviewed, int $interval): bool {
        if ($last_reviewed === '' || $interval < 1) {
            return false;
        }
        $timestamp = strtotime($last_reviewed . ' +' . $interval . ' months');
        return $timestamp !== false && $timestamp < current_time('timestamp');
    }

    private function record_reference(int $post_id): ?array {
        if ($post_id < 1 || get_post_status($post_id) !== 'publish') {
            return null;
        }
        return ['id' => $post_id, 'title' => get_the_title($post_id), 'url' => get_permalink($post_id)];
    }

    private function facets(array $items): array {
        $areas = [];
        $statuses = [];
        foreach ($items as $item) {
            if ($item['responsible_area'] !== '') {
                $areas[$item['responsible_area']] = ($areas[$item['responsible_area']] ?? 0) + 1;
            }
            $statuses[$item['status']] = ($statuses[$item['status']] ?? 0) + 1;
        }
        ksort($areas, SORT_NATURAL | SORT_FLAG_CASE);
        return [
            'areas' => array_map(static fn($name, $count) => ['name' => $name, 'count' => $count], array_keys($areas), array_values($areas)),
            'statuses' => array_map(static fn($value, $count) => ['value' => $value, 'label' => self::statuses()[$value] ?? ucfirst($value), 'count' => $count], array_keys($statuses), array_values($statuses)),
        ];
    }

    private function source_of_truth_model(): array {
        return [
            ['purpose' => 'Institution and product descriptions', 'authority' => 'Current public webpage'],
            ['purpose' => 'Technical behavior', 'authority' => 'Repository documentation'],
            ['purpose' => 'Methodology and boundaries', 'authority' => 'Methodology page'],
            ['purpose' => 'Release state', 'authority' => 'Repository release record'],
            ['purpose' => 'Brand or policy snapshot', 'authority' => 'Published PDF'],
            ['purpose' => 'Historical brief', 'authority' => 'Archived PDF'],
        ];
    }

    private function rfc3339(string $value): string {
        return $value !== '' ? mysql_to_rfc3339($value) : '';
    }



    public static function foundation_record_ids(): array {
        $term = get_term_by('slug', self::COLLECTION_SLUG, SC_Library_Taxonomies::COLLECTION);
        if (!$term || is_wp_error($term)) {
            return [];
        }
        $ids = get_objects_in_term((int) $term->term_id, SC_Library_Taxonomies::COLLECTION);
        return is_wp_error($ids) ? [] : array_values(array_unique(array_map('absint', $ids)));
    }

    public static function foundation_count(): int {
        return count(self::foundation_record_ids());
    }

    public static function admin_diagnostics(): array {
        $issues = [];
        foreach (self::foundation_record_ids() as $post_id) {
            if (get_post_status($post_id) !== 'publish') {
                continue;
            }
            $title = get_the_title($post_id) ?: sprintf(__('Record %d', 'sustainable-catalyst-library'), $post_id);
            $status = sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_status', true)) ?: 'current';
            $authority_type = sanitize_key((string) get_post_meta($post_id, '_sc_library_doc_authority_type', true)) ?: 'webpage';
            $authority_url = esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_authority_url', true));
            $fallback = match ($authority_type) {
                'repository' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_repository_url', true)),
                'release' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_release_url', true)),
                'pdf', 'archive' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_pdf_url', true)),
                default => esc_url_raw((string) get_post_meta($post_id, '_sc_library_doc_webpage_url', true)) ?: get_permalink($post_id),
            };
            $categories = wp_get_post_terms($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['fields' => 'ids']);
            $last_reviewed = (string) get_post_meta($post_id, '_sc_library_doc_last_reviewed', true);
            $interval = max(0, absint(get_post_meta($post_id, '_sc_library_doc_review_interval', true) ?: 12));
            $responsible = trim((string) get_post_meta($post_id, '_sc_library_doc_responsible_area', true));
            $superseded_by = absint(get_post_meta($post_id, '_sc_library_doc_superseded_by_id', true));
            $dependencies = array_filter(array_map('absint', preg_split('/[\s,]+/', (string) get_post_meta($post_id, '_sc_library_doc_dependency_ids', true)) ?: []));

            $add = static function (string $code, string $message) use (&$issues, $post_id, $title): void {
                $issues[] = ['code' => $code, 'post_id' => $post_id, 'title' => $title, 'message' => $message, 'edit_url' => get_edit_post_link($post_id, 'raw') ?: ''];
            };
            if ($authority_url === '' && !$fallback) {
                $add('missing_authority', __('No authoritative source is assigned.', 'sustainable-catalyst-library'));
            }
            if ($responsible === '') {
                $add('missing_area', __('No responsible institutional or product area is assigned.', 'sustainable-catalyst-library'));
            }
            if (is_wp_error($categories) || !$categories) {
                $add('missing_category', __('No Documentation Category is assigned.', 'sustainable-catalyst-library'));
            }
            if ($status === 'draft') {
                $add('published_draft', __('A published WordPress record is marked as draft documentation.', 'sustainable-catalyst-library'));
            }
            if ($status === 'superseded' && $superseded_by < 1) {
                $add('missing_replacement', __('The record is superseded but no replacement record is identified.', 'sustainable-catalyst-library'));
            }
            if ($last_reviewed === '') {
                $add('never_reviewed', __('The document has no last-reviewed date.', 'sustainable-catalyst-library'));
            } elseif ($interval > 0) {
                $due = strtotime($last_reviewed . ' +' . $interval . ' months');
                if ($due !== false && $due < current_time('timestamp')) {
                    $add('review_due', __('The documentation review interval has expired.', 'sustainable-catalyst-library'));
                }
            }
            if (in_array($post_id, $dependencies, true) || absint(get_post_meta($post_id, '_sc_library_doc_supersedes_id', true)) === $post_id || $superseded_by === $post_id) {
                $add('circular_reference', __('The record contains a self-referencing dependency or history link.', 'sustainable-catalyst-library'));
            }
        }
        return ['count' => count($issues), 'items' => array_slice($issues, 0, 30)];
    }

    public static function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        $atts = shortcode_atts([
            'mode' => 'public',
            'collection' => self::COLLECTION_SLUG,
            'title' => 'Foundations Documentation Library',
            'intro' => 'Search current institutional, product, methodology, policy, repository, PDF snapshot, and historical documentation.',
            'show_header' => 'true',
            'show_featured' => 'true',
            'include_archived' => (string) (int) get_option('sc_library_documentation_include_archived', 0),
            'per_page' => '12',
        ], $atts, 'sc_foundations_library');

        $instance_id = 'sc-foundations-library-' . wp_rand(1000, 99999);
        $title = sanitize_text_field((string) $atts['title']);
        $intro = sanitize_text_field((string) $atts['intro']);
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $show_featured = filter_var($atts['show_featured'], FILTER_VALIDATE_BOOLEAN);
        $include_archived = filter_var($atts['include_archived'], FILTER_VALIDATE_BOOLEAN);
        $per_page = min(30, max(1, absint($atts['per_page'])));
        $mode = sanitize_key((string) $atts['mode']);

        wp_enqueue_style('sc-library-documentation', SC_LIBRARY_URL . 'assets/css/sc-library-documentation.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-documentation', SC_LIBRARY_URL . 'assets/js/sc-library-documentation.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-documentation', 'SCLibraryDocumentation', [
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library')),
            'searchPlaceholder' => (string) get_option('sc_library_documentation_search_placeholder', 'Search titles, descriptions, keywords, and document text'),
            'strings' => [
                'loading' => __('Loading documentation…', 'sustainable-catalyst-library'),
                'empty' => __('No documentation records match these filters.', 'sustainable-catalyst-library'),
                'error' => __('The documentation library could not be loaded.', 'sustainable-catalyst-library'),
                'records' => __('documentation records', 'sustainable-catalyst-library'),
            ],
        ]);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-documentation.php';
        return (string) ob_get_clean();
    }
}
