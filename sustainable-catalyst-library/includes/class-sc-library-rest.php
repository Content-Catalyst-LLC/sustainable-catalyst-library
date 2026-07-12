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
            'tag' => ['sanitize_callback' => 'absint', 'default' => 0],
            'post_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'sort' => ['sanitize_callback' => 'sanitize_key', 'default' => 'newest'],
            'page' => ['sanitize_callback' => 'absint', 'default' => 1],
            'per_page' => ['sanitize_callback' => 'absint', 'default' => 12],
        ];
    }

    public function status(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'ok' => true,
            'version' => SC_LIBRARY_VERSION,
            'indexed_records' => $this->indexer->count_indexed(),
            'last_full_index' => get_option('sc_library_last_full_index', ''),
            'post_types' => $this->indexer->configured_post_types(),
        ]);
    }

    public function categories(WP_REST_Request $request): WP_REST_Response {
        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
        }

        $items = array_map(static function (WP_Term $term): array {
            return [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => (int) $term->count,
                'parent' => (int) $term->parent,
                'url' => get_term_link($term),
            ];
        }, $terms);

        return rest_ensure_response(['items' => $items]);
    }

    public function items(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $this->indexer->table_name();

        $search = trim((string) $request['search']);
        $category = (int) $request['category'];
        $tag = (int) $request['tag'];
        $post_type = (string) $request['post_type'];
        $sort = (string) $request['sort'];
        $page = max(1, (int) $request['page']);
        $per_page = min(50, max(1, (int) $request['per_page']));
        $offset = ($page - 1) * $per_page;

        $where = ['status = %s'];
        $values = ['publish'];

        if ($post_type && in_array($post_type, $this->indexer->configured_post_types(), true)) {
            $where[] = 'post_type = %s';
            $values[] = $post_type;
        }
        if ($category) {
            $where[] = 'JSON_CONTAINS(category_ids, %s)';
            $values[] = wp_json_encode($category);
        }
        if ($tag) {
            $where[] = 'JSON_CONTAINS(tag_ids, %s)';
            $values[] = wp_json_encode($tag);
        }
        if ($search !== '') {
            $where[] = '(MATCH(title, excerpt, searchable_text) AGAINST (%s IN NATURAL LANGUAGE MODE) OR title LIKE %s OR excerpt LIKE %s)';
            $values[] = $search;
            $like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $like;
            $values[] = $like;
        }

        $order_by = match ($sort) {
            'oldest' => 'published_at ASC',
            'updated' => 'modified_at DESC',
            'title' => 'title ASC',
            default => 'published_at DESC',
        };

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$values));

        $data_values = array_merge($values, [$per_page, $offset]);
        $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$data_values), ARRAY_A);

        $items = array_map([$this, 'hydrate_row'], $rows ?: []);

        return rest_ensure_response([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => (int) ceil($total / $per_page),
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

        return [
            'id' => $post_id,
            'post_type' => $row['post_type'],
            'title' => $row['title'],
            'excerpt' => $row['excerpt'],
            'url' => $row['permalink'],
            'image' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
            'published_at' => mysql_to_rfc3339($row['published_at']),
            'modified_at' => mysql_to_rfc3339($row['modified_at']),
            'categories' => $categories,
            'tags' => $tags,
        ];
    }

    public function reindex(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'ok' => true,
            'result' => $this->indexer->rebuild_all(),
        ]);
    }
}
