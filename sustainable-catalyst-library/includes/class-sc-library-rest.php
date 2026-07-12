<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_REST {
    private SC_Library_Indexer $indexer;

    public function __construct(SC_Library_Indexer $indexer) {
        $this->indexer = $indexer;
    }

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('sustainable-catalyst/v1', '/library/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sustainable-catalyst/v1', '/library/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'categories'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sustainable-catalyst/v1', '/library/items', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'items'],
            'permission_callback' => '__return_true',
            'args' => $this->item_args(),
        ]);

        register_rest_route('sustainable-catalyst/v1', '/library/items/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'item'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn($value) => absint($value) > 0,
                ],
            ],
        ]);

        register_rest_route('sustainable-catalyst/v1', '/library/reindex', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reindex'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    private function item_args(): array {
        return [
            'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'category' => ['sanitize_callback' => 'absint', 'default' => 0],
            'include_children' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true],
            'tag' => ['sanitize_callback' => 'absint', 'default' => 0],
            'post_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'sort' => ['sanitize_callback' => 'sanitize_key', 'default' => 'relevance'],
            'page' => ['sanitize_callback' => 'absint', 'default' => 1],
            'per_page' => ['sanitize_callback' => 'absint', 'default' => 10],
        ];
    }

    public function status(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'ok' => true,
            'version' => SC_LIBRARY_VERSION,
            'indexed_records' => $this->indexer->count_indexed(),
            'last_full_index' => get_option('sc_library_last_full_index', ''),
            'post_types' => $this->indexer->configured_post_types(),
            'interface' => 'knowledge-base',
        ]);
    }

    public function categories(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
        }

        $rows = $wpdb->get_results(
            "SELECT post_id, category_ids FROM {$this->indexer->table_name()} WHERE status = 'publish'",
            ARRAY_A
        );

        $direct_sets = [];
        $aggregate_sets = [];

        foreach ($rows ?: [] as $row) {
            $post_id = (int) $row['post_id'];
            $category_ids = json_decode((string) $row['category_ids'], true) ?: [];

            foreach (array_unique(array_map('intval', $category_ids)) as $category_id) {
                if ($category_id <= 0) {
                    continue;
                }
                $direct_sets[$category_id][$post_id] = true;
                $aggregate_sets[$category_id][$post_id] = true;

                foreach (get_ancestors($category_id, 'category', 'taxonomy') as $ancestor_id) {
                    $aggregate_sets[(int) $ancestor_id][$post_id] = true;
                }
            }
        }

        $items = [];
        foreach ($terms as $term) {
            $count = isset($aggregate_sets[$term->term_id]) ? count($aggregate_sets[$term->term_id]) : 0;
            if ($count < 1) {
                continue;
            }

            $term_url = get_term_link($term);
            $items[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $count,
                'direct_count' => isset($direct_sets[$term->term_id]) ? count($direct_sets[$term->term_id]) : 0,
                'parent' => (int) $term->parent,
                'url' => is_wp_error($term_url) ? '' : $term_url,
            ];
        }

        return rest_ensure_response([
            'items' => $items,
            'total_records' => $this->indexer->count_indexed(),
        ]);
    }

    public function items(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $this->indexer->table_name();

        $search = trim((string) $request['search']);
        $category = (int) $request['category'];
        $include_children = rest_sanitize_boolean($request['include_children']);
        $tag = (int) $request['tag'];
        $post_type = (string) $request['post_type'];
        $sort = (string) $request['sort'];
        $page = max(1, (int) $request['page']);
        $per_page = min(30, max(1, (int) $request['per_page']));
        $offset = ($page - 1) * $per_page;

        $where = ['status = %s'];
        $values = ['publish'];

        if ($post_type && in_array($post_type, $this->indexer->configured_post_types(), true)) {
            $where[] = 'post_type = %s';
            $values[] = $post_type;
        }

        if ($category) {
            $category_ids = [$category];
            if ($include_children) {
                $children = get_term_children($category, 'category');
                if (!is_wp_error($children)) {
                    $category_ids = array_merge($category_ids, array_map('intval', $children));
                }
            }
            $category_clauses = [];
            foreach (array_unique($category_ids) as $category_id) {
                $category_clauses[] = 'JSON_CONTAINS(category_ids, %s)';
                $values[] = wp_json_encode((int) $category_id);
            }
            $where[] = '(' . implode(' OR ', $category_clauses) . ')';
        }

        if ($tag) {
            $where[] = 'JSON_CONTAINS(tag_ids, %s)';
            $values[] = wp_json_encode($tag);
        }

        $order_values = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $prefix = $wpdb->esc_like($search) . '%';
            $where[] = '(MATCH(title, excerpt, searchable_text) AGAINST (%s IN NATURAL LANGUAGE MODE) OR title LIKE %s OR excerpt LIKE %s OR searchable_text LIKE %s)';
            $values[] = $search;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;

            if ($sort === 'relevance' || $sort === '') {
                $order_by = "(
                    CASE
                        WHEN LOWER(title) = LOWER(%s) THEN 120
                        WHEN title LIKE %s THEN 90
                        WHEN title LIKE %s THEN 65
                        ELSE 0
                    END + MATCH(title, excerpt, searchable_text) AGAINST (%s IN NATURAL LANGUAGE MODE)
                ) DESC, modified_at DESC";
                $order_values = [$search, $prefix, $like, $search];
            } else {
                $order_by = $this->order_by($sort);
            }
        } else {
            $order_by = $this->order_by($sort === 'relevance' ? 'updated' : $sort);
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$values));

        $data_values = array_merge($values, $order_values, [$per_page, $offset]);
        $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_values), ARRAY_A);

        return rest_ensure_response([
            'items' => array_map([$this, 'hydrate_row'], $rows ?: []),
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 0,
            ],
            'filters' => [
                'search' => $search,
                'category' => $category,
                'tag' => $tag,
                'post_type' => $post_type,
                'sort' => $sort,
            ],
        ]);
    }

    private function order_by(string $sort): string {
        return match ($sort) {
            'oldest' => 'published_at ASC',
            'newest' => 'published_at DESC',
            'title' => 'title ASC',
            default => 'modified_at DESC',
        };
    }

    public function item(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $post_id = (int) $request['id'];
        $table = $this->indexer->table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d AND status = 'publish'", $post_id),
            ARRAY_A
        );

        if (!$row) {
            return new WP_REST_Response(['message' => __('Library record not found.', 'sustainable-catalyst-library')], 404);
        }

        $item = $this->hydrate_row($row);
        $category_ids = json_decode((string) $row['category_ids'], true) ?: [];
        $related = [];

        if ($category_ids) {
            $clauses = [];
            $values = [];
            foreach (array_slice(array_unique(array_map('intval', $category_ids)), 0, 8) as $category_id) {
                $clauses[] = 'JSON_CONTAINS(category_ids, %s)';
                $values[] = wp_json_encode($category_id);
            }
            $values[] = $post_id;
            $related_sql = "SELECT * FROM {$table}
                            WHERE status = 'publish'
                              AND (" . implode(' OR ', $clauses) . ")
                              AND post_id <> %d
                            ORDER BY modified_at DESC
                            LIMIT 5";
            $related_rows = $wpdb->get_results($wpdb->prepare($related_sql, ...$values), ARRAY_A);
            $related = array_map([$this, 'hydrate_row'], $related_rows ?: []);
        }

        $item['related'] = $related;
        return rest_ensure_response($item);
    }

    private function hydrate_row(array $row): array {
        $post_id = (int) $row['post_id'];
        $category_ids = json_decode((string) $row['category_ids'], true) ?: [];
        $tag_ids = json_decode((string) $row['tag_ids'], true) ?: [];

        $categories = array_values(array_filter(array_map(static function ($id) {
            $term = get_term((int) $id, 'category');
            return $term && !is_wp_error($term) ? [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => (int) $term->parent,
            ] : null;
        }, $category_ids)));

        $tags = array_values(array_filter(array_map(static function ($id) {
            $term = get_term((int) $id, 'post_tag');
            return $term && !is_wp_error($term) ? [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ] : null;
        }, $tag_ids)));

        $post_type_object = get_post_type_object((string) $row['post_type']);
        $raw_content = (string) get_post_field('post_content', $post_id);
        $has_code = stripos($raw_content, 'github.com') !== false || preg_match('/<(pre|code)\b/i', $raw_content) === 1;
        $has_equations = preg_match('/(\\\(|\\\[|\$\$|latex|mathjax)/i', $raw_content) === 1;
        $has_video = preg_match('/(youtube\.com|youtu\.be|vimeo\.com|<video\b|wp:video)/i', $raw_content) === 1;

        return [
            'id' => $post_id,
            'post_type' => $row['post_type'],
            'type_label' => $post_type_object ? $post_type_object->labels->singular_name : ucfirst((string) $row['post_type']),
            'title' => $row['title'],
            'excerpt' => $row['excerpt'],
            'url' => $row['permalink'],
            'published_at' => $this->rfc3339_or_empty((string) $row['published_at']),
            'modified_at' => $this->rfc3339_or_empty((string) $row['modified_at']),
            'categories' => $categories,
            'tags' => $tags,
            'resources' => [
                'code' => (bool) $has_code,
                'equations' => (bool) $has_equations,
                'video' => (bool) $has_video,
            ],
        ];
    }

    private function rfc3339_or_empty(string $value): string {
        return $value !== '' ? mysql_to_rfc3339($value) : '';
    }

    public function reindex(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'ok' => true,
            'result' => $this->indexer->rebuild_all(),
        ]);
    }
}
