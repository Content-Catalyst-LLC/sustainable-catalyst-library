<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_REST {
    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $namespace = 'sustainable-catalyst/v1';

        register_rest_route($namespace, '/library/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'categories'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/series', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'series'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/concepts', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'concepts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/pathways', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'pathways'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/library/items', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'items'],
            'permission_callback' => '__return_true',
            'args' => $this->item_args(),
        ]);

        register_rest_route($namespace, '/library/items/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'item'],
            'permission_callback' => '__return_true',
            'args' => $this->id_args(),
        ]);

        register_rest_route($namespace, '/library/items/(?P<id>\d+)/related', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'related'],
            'permission_callback' => '__return_true',
            'args' => $this->id_args(),
        ]);

        register_rest_route($namespace, '/library/reindex', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reindex'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    private function id_args(): array {
        return [
            'id' => [
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn($value) => absint($value) > 0,
            ],
        ];
    }

    private function item_args(): array {
        return [
            'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'category' => ['sanitize_callback' => 'absint', 'default' => 0],
            'include_children' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true],
            'tag' => ['sanitize_callback' => 'absint', 'default' => 0],
            'series' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'concept' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
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
            'relationships' => $this->relationships->count(),
            'series' => $this->safe_term_count(SC_Library_Taxonomies::SERIES),
            'concepts' => $this->safe_term_count(SC_Library_Taxonomies::CONCEPT),
            'collections' => $this->safe_term_count(SC_Library_Taxonomies::COLLECTION),
            'documentation_categories' => $this->safe_term_count(SC_Library_Taxonomies::DOCUMENT_CATEGORY),
            'last_full_index' => get_option('sc_library_last_full_index', ''),
            'post_types' => $this->indexer->configured_post_types(),
            'interface' => 'relationship-aware-knowledge-base',
            'notebook' => [
                'enabled' => SC_Library_Notebook::enabled(),
                'storage' => 'browser-local',
                'schema' => SC_Library_Notebook::SCHEMA,
            ],
            'books' => [
                'enabled' => class_exists('SC_Library_Books') && SC_Library_Books::enabled(),
                'schema' => class_exists('SC_Library_Books') ? SC_Library_Books::SCHEMA : '',
                'pdf_mode' => 'browser-print',
            ],
            'documentation' => [
                'enabled' => class_exists('SC_Library_Documentation') && SC_Library_Documentation::enabled(),
                'collection' => class_exists('SC_Library_Documentation') ? SC_Library_Documentation::COLLECTION_SLUG : 'foundations',
                'authority_model' => 'explicit-source-of-truth',
            ],
            'portability' => [
                'enabled' => class_exists('SC_Library_Portability') && SC_Library_Portability::enabled(),
                'export_schema' => class_exists('SC_Library_Portability') ? SC_Library_Portability::EXPORT_SCHEMA : '',
                'postgresql_schema' => (string) get_option('sc_library_export_schema_name', 'sustainable_catalyst_library'),
                'formats' => class_exists('SC_Library_Portability') ? array_keys(SC_Library_Portability::formats()) : [],
            ],
            'knowledge_graph' => [
                'enabled' => class_exists('SC_Library_Knowledge_Graph') && SC_Library_Knowledge_Graph::enabled(),
                'schema' => class_exists('SC_Library_Knowledge_Graph') ? SC_Library_Knowledge_Graph::SCHEMA : '',
                'endpoints' => ['graph', 'diagnostics', 'timeline', 'places', 'board-promotions'],
            ],
            'research_librarian_orchestration' => [
                'enabled' => class_exists('SC_Library_Orchestrator') && SC_Library_Orchestrator::enabled(),
                'schema' => class_exists('SC_Library_Orchestrator') ? SC_Library_Orchestrator::SCHEMA : '',
                'site_scoped' => true,
                'user_confirmation_required' => true,
                'automatic_publication' => false,
                'endpoints' => ['orchestrator/schema', 'orchestrator/status', 'orchestrator/query', 'orchestrator/sessions', 'orchestrator/events'],
            ],
            'developer_api' => [
                'enabled' => class_exists('SC_Library_Developer_API') && SC_Library_Developer_API::enabled(),
                'schema' => class_exists('SC_Library_Developer_API') ? SC_Library_Developer_API::SCHEMA : '',
                'namespace' => class_exists('SC_Library_Developer_API') ? SC_Library_Developer_API::API_NAMESPACE : '',
                'openapi' => class_exists('SC_Library_Developer_API') ? rest_url(SC_Library_Developer_API::API_NAMESPACE . '/openapi.json') : '',
                'webhook_signatures' => 'timestamped-hmac-sha256',
                'public_private_boundary' => true,
            ],
            'integrations' => [
                'enabled' => class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled(),
                'handoff_schema' => class_exists('SC_Library_Integrations') ? SC_Library_Integrations::HANDOFF_SCHEMA : '',
                'targets' => class_exists('SC_Library_Integrations') ? array_keys(SC_Library_Integrations::targets()) : [],
            ],
        ]);
    }

    private function safe_term_count(string $taxonomy): int {
        $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
        return is_wp_error($count) ? 0 : (int) $count;
    }

    public function categories(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'items' => $this->taxonomy_counts('category', 'category_ids', true),
            'total_records' => $this->indexer->count_indexed(),
        ]);
    }

    public function series(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $this->indexer->table_name();
        $counts = $wpdb->get_results(
            "SELECT series_term_id AS term_id, COUNT(*) AS record_count
             FROM {$table}
             WHERE status = 'publish' AND series_term_id IS NOT NULL AND series_term_id > 0
             GROUP BY series_term_id",
            OBJECT_K
        ) ?: [];

        $terms = get_terms([
            'taxonomy' => SC_Library_Taxonomies::SERIES,
            'hide_empty' => false,
            'orderby' => 'name',
        ]);
        if (is_wp_error($terms)) {
            return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
        }

        $items = [];
        foreach ($terms as $term) {
            $count = isset($counts[$term->term_id]) ? (int) $counts[$term->term_id]->record_count : 0;
            if ($count < 1) {
                continue;
            }
            $items[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $count,
            ];
        }

        return rest_ensure_response(['items' => $items]);
    }

    public function concepts(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'items' => $this->taxonomy_counts(SC_Library_Taxonomies::CONCEPT, 'concept_ids', false),
        ]);
    }

    public function pathways(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response(['items' => $this->featured_pathways()]);
    }

    private function taxonomy_counts(string $taxonomy, string $column, bool $with_ancestors): array {
        global $wpdb;
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        if (is_wp_error($terms)) {
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT post_id, {$column} AS term_ids FROM {$this->indexer->table_name()} WHERE status = 'publish'",
            ARRAY_A
        );
        $direct_sets = [];
        $aggregate_sets = [];

        foreach ($rows ?: [] as $row) {
            $post_id = (int) $row['post_id'];
            $term_ids = json_decode((string) $row['term_ids'], true) ?: [];
            foreach (array_unique(array_map('intval', $term_ids)) as $term_id) {
                if ($term_id < 1) {
                    continue;
                }
                $direct_sets[$term_id][$post_id] = true;
                $aggregate_sets[$term_id][$post_id] = true;
                if ($with_ancestors) {
                    foreach (get_ancestors($term_id, $taxonomy, 'taxonomy') as $ancestor_id) {
                        $aggregate_sets[(int) $ancestor_id][$post_id] = true;
                    }
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
                'description' => $term->description,
                'count' => $count,
                'direct_count' => isset($direct_sets[$term->term_id]) ? count($direct_sets[$term->term_id]) : 0,
                'parent' => (int) $term->parent,
                'url' => is_wp_error($term_url) ? '' : $term_url,
            ];
        }
        return $items;
    }

    public function items(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $this->indexer->table_name();

        $search = trim((string) $request['search']);
        $category = (int) $request['category'];
        $include_children = rest_sanitize_boolean($request['include_children']);
        $tag = (int) $request['tag'];
        $series = $this->resolve_term_id((string) $request['series'], SC_Library_Taxonomies::SERIES);
        $concept = $this->resolve_term_id((string) $request['concept'], SC_Library_Taxonomies::CONCEPT);
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
            $clauses = [];
            foreach (array_unique($category_ids) as $category_id) {
                $clauses[] = 'JSON_CONTAINS(category_ids, %s)';
                $values[] = wp_json_encode((int) $category_id);
            }
            if ($clauses) {
                $where[] = '(' . implode(' OR ', $clauses) . ')';
            }
        }

        if ($tag) {
            $where[] = 'JSON_CONTAINS(tag_ids, %s)';
            $values[] = wp_json_encode($tag);
        }
        if ($series) {
            $where[] = 'series_term_id = %d';
            $values[] = $series;
        }
        if ($concept) {
            $where[] = 'JSON_CONTAINS(concept_ids, %s)';
            $values[] = wp_json_encode($concept);
        }

        $order_values = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $prefix = $wpdb->esc_like($search) . '%';
            $where[] = '(title LIKE %s OR excerpt LIKE %s OR searchable_text LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;

            if ($sort === 'relevance' || $sort === '') {
                $order_by = "CASE
                    WHEN LOWER(title) = LOWER(%s) THEN 120
                    WHEN title LIKE %s THEN 90
                    WHEN title LIKE %s THEN 65
                    WHEN excerpt LIKE %s THEN 30
                    ELSE 10
                END DESC, modified_at DESC";
                $order_values = [$search, $prefix, $like, $like];
            } else {
                $order_by = $this->order_by($sort);
            }
        } else {
            $order_by = $this->order_by($sort === 'relevance' ? 'updated' : $sort);
        }

        $where_sql = implode(' AND ', $where);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$values));
        $data_values = array_merge($values, $order_values, [$per_page, $offset]);
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d", ...$data_values),
            ARRAY_A
        );

        return rest_ensure_response([
            'items' => array_map(fn(array $row) => $this->hydrate_row($row, false), $rows ?: []),
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
                'series' => $series,
                'concept' => $concept,
                'post_type' => $post_type,
                'sort' => $sort,
            ],
        ]);
    }

    private function resolve_term_id(string $value, string $taxonomy): int {
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return absint($value);
        }
        $term = get_term_by('slug', sanitize_title($value), $taxonomy);
        return $term && !is_wp_error($term) ? (int) $term->term_id : 0;
    }

    private function order_by(string $sort): string {
        return match ($sort) {
            'oldest' => 'published_at ASC',
            'newest' => 'published_at DESC',
            'title' => 'title ASC',
            'series' => 'series_order ASC, published_at ASC',
            default => 'modified_at DESC',
        };
    }

    public function item(WP_REST_Request $request): WP_REST_Response {
        $row = $this->row_for_post((int) $request['id']);
        if (!$row) {
            return new WP_REST_Response(['message' => __('Library record not found.', 'sustainable-catalyst-library')], 404);
        }
        return rest_ensure_response($this->hydrate_row($row, true));
    }

    public function related(WP_REST_Request $request): WP_REST_Response {
        $row = $this->row_for_post((int) $request['id']);
        if (!$row) {
            return new WP_REST_Response(['message' => __('Library record not found.', 'sustainable-catalyst-library')], 404);
        }
        $detail = $this->relationship_detail((int) $row['post_id'], $row);
        return rest_ensure_response($detail);
    }

    private function row_for_post(int $post_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->indexer->table_name()} WHERE post_id = %d AND status = 'publish'", $post_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function hydrate_row(array $row, bool $detail): array {
        $post_id = (int) $row['post_id'];
        $categories = $this->term_objects(json_decode((string) $row['category_ids'], true) ?: [], 'category');
        $tags = $this->term_objects(json_decode((string) $row['tag_ids'], true) ?: [], 'post_tag');
        $concepts = $this->term_objects(json_decode((string) $row['concept_ids'], true) ?: [], SC_Library_Taxonomies::CONCEPT);
        $primary_domain = $this->term_object((int) ($row['primary_domain_id'] ?? 0), 'category');
        $series = $this->series_summary((int) ($row['series_term_id'] ?? 0), (float) ($row['series_order'] ?? 0));
        $post_type_object = get_post_type_object((string) $row['post_type']);
        $flags = json_decode((string) ($row['resource_flags'] ?? ''), true) ?: [];

        $item = [
            'id' => $post_id,
            'record_identifier' => (string) ($row['record_identifier'] ?? sprintf('sc:library:%s:%d', $row['post_type'], $post_id)),
            'post_type' => $row['post_type'],
            'type_label' => $post_type_object ? $post_type_object->labels->singular_name : ucfirst((string) $row['post_type']),
            'title' => $row['title'],
            'excerpt' => $row['excerpt'],
            'url' => $row['permalink'],
            'published_at' => $this->rfc3339_or_empty((string) $row['published_at']),
            'modified_at' => $this->rfc3339_or_empty((string) $row['modified_at']),
            'categories' => $categories,
            'tags' => $tags,
            'primary_domain' => $primary_domain,
            'series' => $series,
            'concepts' => $concepts,
            'resources' => array_merge([
                'code' => false,
                'equations' => false,
                'video' => false,
                'dataset' => false,
                'workbench' => false,
                'decision_studio' => false,
                'site_intelligence' => false,
            ], array_map('boolval', $flags)),
        ];


        if (class_exists('SC_Library_Planner') && get_post_type($post_id) === SC_Library_Planner::POST_TYPE) {
            $planner = new SC_Library_Planner($this->indexer, $this->relationships);
            $plan = $planner->plan_payload($post_id);
            $item['record_state'] = $plan['record_state'];
            $item['record_state_label'] = $plan['record_state_label'];
            $item['content_type'] = $plan['content_type'];
            $item['content_type_label'] = $plan['content_type_label'];
            $item['expected_release'] = $plan['expected_release'];
            $item['area'] = $plan['area'];
            $item['product'] = $plan['product'];
            $item['article_map_id'] = $plan['article_map_id'];
            $item['article_map_title'] = $plan['article_map_title'];
            $item['article_map_url'] = $plan['article_map_url'];
            $item['notice'] = $plan['notice'];
            $item['type_label'] = __('Planned Content', 'sustainable-catalyst-library');
        } else {
            $item['record_state'] = 'published';
            $item['record_state_label'] = __('Published', 'sustainable-catalyst-library');
            $item['content_type'] = $row['post_type'] === 'post' ? 'article' : $row['post_type'];
            $item['content_type_label'] = $item['type_label'];
            $item['expected_release'] = ['type' => 'none', 'display' => '', 'sort' => '', 'note' => ''];
            $item['area'] = $primary_domain['name'] ?? '';
            $item['product'] = '';
            $item['article_map_id'] = 0;
            $item['article_map_title'] = '';
            $item['article_map_url'] = '';
            $item['notice'] = '';
        }

        if (!$detail) {
            return $item;
        }

        $evidence_value = (string) get_post_meta($post_id, '_sc_library_evidence_status', true);
        $resource_detail = $this->resource_detail($post_id, $flags);
        $relationship_detail = $this->relationship_detail($post_id, $row);
        $breadcrumbs = $this->breadcrumbs($primary_domain, $categories);

        $item['breadcrumbs'] = $breadcrumbs;
        $item['evidence'] = [
            'value' => $evidence_value,
            'label' => $this->evidence_label($evidence_value),
        ];
        $item['resources'] = array_merge($item['resources'], $resource_detail);
        $item['relationships'] = $relationship_detail['relationships'];
        $item['related_groups'] = $relationship_detail['groups'];
        $item['suggested_related'] = $relationship_detail['suggested'];
        $item['related'] = $relationship_detail['flat'];
        $item['handoffs'] = $this->handoffs($item);

        return $item;
    }

    private function relationship_detail(int $post_id, array $row): array {
        $relations = $this->relationships->get_for_post($post_id, true);
        $hydrated = [];
        $groups = [];
        $seen = [];

        foreach ($relations as $relation) {
            if (($relation['visibility'] ?? 'public') !== 'public' && !current_user_can('edit_posts')) {
                continue;
            }
            $related_id = $relation['direction'] === 'incoming'
                ? (int) $relation['source_post_id']
                : (int) $relation['target_post_id'];
            $related_row = $this->row_for_post($related_id);
            if (!$related_row) {
                continue;
            }
            $record = $this->hydrate_row($related_row, false);
            $entry = [
                'direction' => $relation['direction'],
                'type' => $relation['type'],
                'type_label' => $relation['type_label'],
                'note' => $relation['note'],
                'confidence' => (float) ($relation['confidence'] ?? 0.85),
                'confidence_basis' => (string) ($relation['confidence_basis'] ?? 'editorial'),
                'provenance_type' => (string) ($relation['provenance_type'] ?? 'editorial'),
                'provenance_url' => esc_url_raw((string) ($relation['provenance_url'] ?? '')),
                'evidence_note' => (string) ($relation['evidence_note'] ?? ''),
                'visibility' => (string) ($relation['visibility'] ?? 'public'),
                'record' => $record,
            ];
            $hydrated[] = $entry;
            $group_key = $relation['direction'] . ':' . $relation['type'];
            if (!isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'key' => $group_key,
                    'label' => $relation['type_label'],
                    'items' => [],
                ];
            }
            $groups[$group_key]['items'][] = $entry;
            $seen[$related_id] = true;
        }

        $suggested = $this->suggested_related($post_id, $row, array_keys($seen));
        $flat = [];
        $flat_seen = [];
        foreach ($hydrated as $entry) {
            $record_id = (int) $entry['record']['id'];
            if (!isset($flat_seen[$record_id])) {
                $flat[] = $entry['record'];
                $flat_seen[$record_id] = true;
            }
        }
        foreach ($suggested as $record) {
            $record_id = (int) $record['id'];
            if (!isset($flat_seen[$record_id])) {
                $flat[] = $record;
                $flat_seen[$record_id] = true;
            }
        }

        return [
            'relationships' => $hydrated,
            'groups' => array_values($groups),
            'suggested' => $suggested,
            'flat' => array_slice($flat, 0, 12),
        ];
    }

    private function suggested_related(int $post_id, array $row, array $exclude_ids = []): array {
        global $wpdb;
        $table = $this->indexer->table_name();
        $category_ids = json_decode((string) $row['category_ids'], true) ?: [];
        $concept_ids = json_decode((string) $row['concept_ids'], true) ?: [];
        $series_id = (int) ($row['series_term_id'] ?? 0);
        $clauses = [];
        $values = [];

        if ($series_id > 0) {
            $clauses[] = 'series_term_id = %d';
            $values[] = $series_id;
        }
        foreach (array_slice(array_unique(array_map('intval', $concept_ids)), 0, 6) as $concept_id) {
            $clauses[] = 'JSON_CONTAINS(concept_ids, %s)';
            $values[] = wp_json_encode($concept_id);
        }
        foreach (array_slice(array_unique(array_map('intval', $category_ids)), 0, 5) as $category_id) {
            $clauses[] = 'JSON_CONTAINS(category_ids, %s)';
            $values[] = wp_json_encode($category_id);
        }
        if (!$clauses) {
            return [];
        }

        $excluded = array_values(array_unique(array_merge([$post_id], array_map('intval', $exclude_ids))));
        $exclude_placeholders = implode(',', array_fill(0, count($excluded), '%d'));
        $values = array_merge($values, $excluded);
        $sql = "SELECT * FROM {$table}
                WHERE status = 'publish'
                  AND (" . implode(' OR ', $clauses) . ")
                  AND post_id NOT IN ({$exclude_placeholders})
                ORDER BY CASE WHEN series_term_id = %d THEN 0 ELSE 1 END, modified_at DESC
                LIMIT 6";
        $values[] = $series_id;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A);
        return array_map(fn(array $related_row) => $this->hydrate_row($related_row, false), $rows ?: []);
    }

    private function series_summary(int $term_id, float $order): ?array {
        if ($term_id < 1) {
            return null;
        }
        $term = get_term($term_id, SC_Library_Taxonomies::SERIES);
        if (!$term || is_wp_error($term)) {
            return null;
        }
        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'order' => $order,
        ];
    }

    private function series_navigation(int $post_id, int $series_id): array {
        global $wpdb;
        if ($series_id < 1) {
            return ['position' => 0, 'total' => 0, 'previous' => null, 'next' => null];
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->indexer->table_name()}
                 WHERE status = 'publish' AND series_term_id = %d
                 ORDER BY CASE WHEN series_order > 0 THEN 0 ELSE 1 END, series_order ASC, published_at ASC, post_id ASC",
                $series_id
            ),
            ARRAY_A
        ) ?: [];
        $position = 0;
        $previous = null;
        $next = null;
        foreach ($rows as $index => $series_row) {
            if ((int) $series_row['post_id'] !== $post_id) {
                continue;
            }
            $position = $index + 1;
            if ($index > 0) {
                $previous = $this->hydrate_row($rows[$index - 1], false);
            }
            if ($index < count($rows) - 1) {
                $next = $this->hydrate_row($rows[$index + 1], false);
            }
            break;
        }
        return [
            'position' => $position,
            'total' => count($rows),
            'previous' => $previous,
            'next' => $next,
        ];
    }

    private function resource_detail(int $post_id, array $flags): array {
        return [
            'github_url' => esc_url_raw((string) get_post_meta($post_id, '_sc_library_github_url', true)),
            'dataset_urls' => $this->line_values((string) get_post_meta($post_id, '_sc_library_dataset_urls', true), true),
            'video_urls' => $this->line_values((string) get_post_meta($post_id, '_sc_library_video_urls', true), true),
            'workbench_tools' => $this->line_values((string) get_post_meta($post_id, '_sc_library_workbench_tools', true), false),
            'workbench_questions' => $this->line_values((string) get_post_meta($post_id, '_sc_library_workbench_questions', true), false),
            'decision_questions' => $this->line_values((string) get_post_meta($post_id, '_sc_library_decision_questions', true), false),
            'decision_methods' => $this->line_values((string) get_post_meta($post_id, '_sc_library_decision_methods', true), false),
            'site_places' => $this->line_values((string) get_post_meta($post_id, '_sc_library_site_places', true), false),
            'site_indicators' => $this->line_values((string) get_post_meta($post_id, '_sc_library_site_indicators', true), false),
            'site_sources' => $this->line_values((string) get_post_meta($post_id, '_sc_library_site_sources', true), false),
        ];
    }

    private function handoffs(array &$item): array {
        if ($item['series']) {
            $item['series'] = array_merge($item['series'], $this->series_navigation((int) $item['id'], (int) $item['series']['id']));
        }

        $targets = class_exists('SC_Library_Integrations') ? SC_Library_Integrations::targets() : [];
        $handoffs = [];
        foreach ($targets as $target_id => $target) {
            $context_url = add_query_arg('target', $target_id, rest_url(sprintf('sustainable-catalyst/v1/library/items/%d/handoff', (int) $item['id'])));
            $handoffs[$target_id] = [
                'url' => add_query_arg([
                    'library_record' => (int) $item['id'],
                    'library_identifier' => (string) $item['record_identifier'],
                    'library_context_url' => esc_url_raw($context_url),
                    'library_handoff_schema' => class_exists('SC_Library_Integrations') ? SC_Library_Integrations::HANDOFF_SCHEMA : 'sc-library-handoff/1.0',
                    'library_source' => 'research-library',
                ], (string) ($target['url'] ?? '')),
                'label' => (string) ($target['action_label'] ?? $target['label'] ?? ucfirst($target_id)),
                'description' => (string) ($target['description'] ?? ''),
                'available' => !empty($target['url']) && (!class_exists('SC_Library_Integrations') || SC_Library_Integrations::enabled()),
                'context_url' => esc_url_raw($context_url),
            ];
        }
        return $handoffs;
    }

    private function breadcrumbs(?array $primary_domain, array $categories): array {
        $term_id = $primary_domain['id'] ?? ($categories[0]['id'] ?? 0);
        if (!$term_id) {
            return [];
        }
        $ids = array_reverse(get_ancestors((int) $term_id, 'category', 'taxonomy'));
        $ids[] = (int) $term_id;
        return array_values(array_filter(array_map(fn($id) => $this->term_object((int) $id, 'category'), $ids)));
    }

    private function term_objects(array $ids, string $taxonomy): array {
        return array_values(array_filter(array_map(fn($id) => $this->term_object((int) $id, $taxonomy), array_unique(array_map('intval', $ids)))));
    }

    private function term_object(int $id, string $taxonomy): ?array {
        if ($id < 1) {
            return null;
        }
        $term = get_term($id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return null;
        }
        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'description' => $term->description,
        ];
    }

    private function line_values(string $raw, bool $urls): array {
        $values = array_values(array_filter(array_map('trim', preg_split('/\R|,/', $raw) ?: [])));
        return array_values(array_filter(array_map($urls ? 'esc_url_raw' : 'sanitize_text_field', $values)));
    }

    private function evidence_label(string $value): string {
        return match ($value) {
            'foundational' => __('Foundational overview', 'sustainable-catalyst-library'),
            'evidence-linked' => __('Evidence linked', 'sustainable-catalyst-library'),
            'code-backed' => __('Code backed', 'sustainable-catalyst-library'),
            'data-backed' => __('Data backed', 'sustainable-catalyst-library'),
            'reviewed' => __('Reviewed', 'sustainable-catalyst-library'),
            'experimental' => __('Experimental', 'sustainable-catalyst-library'),
            'archival' => __('Archival or historical', 'sustainable-catalyst-library'),
            default => __('Not specified', 'sustainable-catalyst-library'),
        };
    }

    private function featured_pathways(): array {
        $raw = (string) get_option('sc_library_featured_pathways', '');
        $pathways = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $parts = array_map('trim', explode('|', trim($line), 3));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $pathways[] = [
                'title' => sanitize_text_field($parts[0]),
                'url' => esc_url_raw($parts[1]),
                'description' => isset($parts[2]) ? sanitize_text_field($parts[2]) : '',
            ];
        }
        return array_slice($pathways, 0, 12);
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
