<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Indexer {
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Relationships $relationships) {
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('save_post', [$this, 'on_save_post'], 30, 3);
        add_action('before_delete_post', [$this, 'delete_record']);
        add_action('trashed_post', [$this, 'delete_record']);
        add_action('untrashed_post', [$this, 'reindex_post']);
        add_action('set_object_terms', [$this, 'on_terms_changed'], 30, 6);
        add_action('sc_library_daily_reconcile', [$this, 'rebuild_all']);
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_index';
    }

    public function configured_post_types(bool $include_planner = true): array {
        $types = get_option('sc_library_post_types', ['post']);
        $types = is_array($types) && $types ? $types : ['post'];
        $types = $this->normalize_post_types($types);
        if (class_exists('SC_Library_Foundation_Documents') && SC_Library_Foundation_Documents::enabled()) {
            $types[] = SC_Library_Foundation_Documents::POST_TYPE;
        }
        if ($include_planner && class_exists('SC_Library_Planner') && SC_Library_Planner::enabled()) {
            $types[] = SC_Library_Planner::POST_TYPE;
        }
        return array_values(array_unique($types));
    }

    public function scanner_excluded_post_types(): array {
        $excluded = apply_filters('sc_library_scanner_excluded_post_types', [
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
            'wp_font_family',
            'wp_font_face',
            'acf-field',
            'acf-field-group',
            'elementor_library',
            'e-landing-page',
            'shop_order',
            'shop_order_refund',
            'shop_coupon',
            'wpforms',
            'wpforms_log',
        ]);
        return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $excluded))));
    }

    /** Raw published inventory grouped by post type, independent of plugin registration and saved Library settings. */
    public function database_published_post_type_counts(bool $include_technical = false): array {
        global $wpdb;
        static $cache = [];
        $key = $include_technical ? 'all' : 'editorial';
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $rows = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_status = 'publish' GROUP BY post_type ORDER BY post_type ASC",
            ARRAY_A
        ) ?: [];
        $excluded = array_fill_keys($this->scanner_excluded_post_types(), true);
        $counts = [];
        foreach ($rows as $row) {
            $type = sanitize_key((string) ($row['post_type'] ?? ''));
            if ($type === '' || (!$include_technical && isset($excluded[$type]))) {
                continue;
            }
            $counts[$type] = absint($row['total'] ?? 0);
        }
        $cache[$key] = $counts;
        return $counts;
    }

    public function normalize_post_types(array $post_types): array {
        $known = array_merge(get_post_types([], 'names'), array_keys($this->database_published_post_type_counts(false)));
        $known = array_values(array_unique(array_filter(array_map('sanitize_key', $known))));
        $excluded = array_fill_keys($this->scanner_excluded_post_types(), true);
        $types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
        $types = array_values(array_filter($types, static fn(string $type): bool => !isset($excluded[$type])));
        return array_values(array_intersect($types, $known));
    }

    /**
     * Discover editorial post types without relying on a front-end WP_Query or
     * assuming that every stored post type is registered on this admin request.
     */
    public function discoverable_post_types(): array {
        $excluded = array_fill_keys($this->scanner_excluded_post_types(), true);
        $objects = get_post_types([], 'objects');
        $database_types = array_keys($this->database_published_post_type_counts(false));
        $types = [];
        foreach ($objects as $name => $object) {
            if (isset($excluded[$name])) {
                continue;
            }
            if ($name === (class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan')) {
                if (class_exists('SC_Library_Planner') && SC_Library_Planner::enabled()) {
                    $types[] = $name;
                }
                continue;
            }
            $editorial = (bool) ($object->public || $object->publicly_queryable || (!$object->_builtin && $object->show_ui));
            if ($editorial) {
                $types[] = $name;
            }
        }
        // Raw database types are visible even when their registering plugin is
        // late, conditionally loaded, or temporarily inactive.
        foreach ($database_types as $database_type) {
            if (!isset($excluded[$database_type])) {
                $types[] = $database_type;
            }
        }
        $configured = get_option('sc_library_post_types', []);
        if (is_array($configured)) {
            $types = array_merge($types, $configured);
        }
        if (post_type_exists('post')) {
            array_unshift($types, 'post');
        }
        if (post_type_exists('page')) {
            $types[] = 'page';
        }
        return array_values(array_unique($this->normalize_post_types($types)));
    }

    public function recommended_post_types(): array {
        $recommended = [];
        $database_counts = $this->database_published_post_type_counts(false);
        foreach ($this->discoverable_post_types() as $post_type) {
            $object = get_post_type_object($post_type);
            $is_planner = class_exists('SC_Library_Planner') && $post_type === SC_Library_Planner::POST_TYPE;
            $registered_public = $object && ($object->public || $object->publicly_queryable);
            $stored_editorial = absint($database_counts[$post_type] ?? 0) > 0;
            if (in_array($post_type, ['post', 'page'], true) || $is_planner || $registered_public || $stored_editorial) {
                $recommended[] = $post_type;
            }
        }
        return $this->normalize_post_types((array) apply_filters('sc_library_scanner_recommended_post_types', $recommended));
    }

    public function global_published_count(bool $include_technical = false): int {
        return array_sum($this->database_published_post_type_counts($include_technical));
    }

    public function save_configured_post_types(array $post_types): array {
        $types = $this->normalize_post_types($post_types);
        $planner = class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan';
        $stored = array_values(array_filter($types, static fn(string $type): bool => $type !== $planner));
        if (!$stored && post_type_exists('post')) {
            $stored = ['post'];
        }
        update_option('sc_library_post_types', $stored, false);
        return $this->configured_post_types();
    }

    public function on_save_post(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, $this->configured_post_types(), true) || $post->post_status !== 'publish' || (class_exists('SC_Library_Planner') && !SC_Library_Planner::should_index($post_id))) {
            $this->delete_record($post_id);
            return;
        }
        $this->index_post($post_id);
    }

    public function on_terms_changed(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void {
        if (in_array($taxonomy, ['category', 'post_tag', SC_Library_Taxonomies::SERIES, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY], true)) {
            $this->reindex_post($object_id);
        }
    }

    public function reindex_post(int $post_id, ?array $post_types = null): bool {
        $post = get_post($post_id);
        $allowed = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $allowed, true) || (class_exists('SC_Library_Planner') && !SC_Library_Planner::should_index($post_id))) {
            $this->delete_record($post_id);
            return false;
        }
        return $this->index_post($post_id, $allowed);
    }

    public function index_post(int $post_id, ?array $post_types = null): bool {
        global $wpdb;

        $post = get_post($post_id);
        $allowed = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $allowed, true) || (class_exists('SC_Library_Planner') && !SC_Library_Planner::should_index($post_id))) {
            return false;
        }

        $categories = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
        $series_terms = wp_get_post_terms($post_id, SC_Library_Taxonomies::SERIES, ['fields' => 'all']);
        $concept_terms = wp_get_post_terms($post_id, SC_Library_Taxonomies::CONCEPT, ['fields' => 'all']);
        $series_terms = is_wp_error($series_terms) ? [] : $series_terms;
        $concept_terms = is_wp_error($concept_terms) ? [] : $concept_terms;

        $primary_category_id = $categories ? (int) $categories[0] : null;
        $primary_domain_id = absint(get_post_meta($post_id, '_sc_library_primary_domain_id', true));
        if ($primary_domain_id < 1 || !in_array($primary_domain_id, array_map('intval', $categories), true)) {
            $primary_domain_id = $primary_category_id ?: null;
        }

        $series_term_id = $series_terms ? (int) $series_terms[0]->term_id : null;
        $series_order = (float) get_post_meta($post_id, '_sc_library_series_order', true);
        $concept_ids = array_map(static fn($term) => (int) $term->term_id, $concept_terms);

        $raw_content = (string) $post->post_content;
        $content = strip_shortcodes($raw_content);
        $content = wp_strip_all_tags($content, true);
        $content = preg_replace('/\s+/', ' ', $content ?: '');

        $resource_flags = $this->resource_flags($post_id, $raw_content);
        $term_text = implode(' ', array_filter(array_merge(
            wp_list_pluck($series_terms, 'name'),
            wp_list_pluck($concept_terms, 'name'),
            wp_list_pluck((function () use ($post_id) {
                $terms = wp_get_post_terms($post_id, SC_Library_Taxonomies::COLLECTION, ['fields' => 'all']);
                return is_wp_error($terms) ? [] : $terms;
            })(), 'name'),
            wp_list_pluck((function () use ($post_id) {
                $terms = wp_get_post_terms($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['fields' => 'all']);
                return is_wp_error($terms) ? [] : $terms;
            })(), 'name'),
            array_map(static function ($term_id): string {
                $term = get_term((int) $term_id, 'category');
                return $term && !is_wp_error($term) ? $term->name : '';
            }, $categories),
            array_map(static function ($term_id): string {
                $term = get_term((int) $term_id, 'post_tag');
                return $term && !is_wp_error($term) ? $term->name : '';
            }, $tags)
        )));

        $pdf_text = '';
        if (class_exists('SC_Library_Foundation_Documents') && $post->post_type === SC_Library_Foundation_Documents::POST_TYPE) {
            $pdf_text = SC_Library_Foundation_Documents::extracted_text($post_id);
        }

        $resource_text = implode(' ', array_filter([
            (string) get_post_meta($post_id, '_sc_library_github_url', true),
            (string) get_post_meta($post_id, '_sc_library_dataset_urls', true),
            (string) get_post_meta($post_id, '_sc_library_video_urls', true),
            (string) get_post_meta($post_id, '_sc_library_workbench_tools', true),
            (string) get_post_meta($post_id, '_sc_library_workbench_questions', true),
            (string) get_post_meta($post_id, '_sc_library_decision_questions', true),
            (string) get_post_meta($post_id, '_sc_library_decision_methods', true),
            (string) get_post_meta($post_id, '_sc_library_site_places', true),
            (string) get_post_meta($post_id, '_sc_library_site_indicators', true),
            (string) get_post_meta($post_id, '_sc_library_site_sources', true),
            (string) get_post_meta($post_id, '_sc_library_evidence_status', true),
            (string) get_post_meta($post_id, '_sc_library_doc_status', true),
            (string) get_post_meta($post_id, '_sc_library_doc_type', true),
            (string) get_post_meta($post_id, '_sc_library_doc_version', true),
            (string) get_post_meta($post_id, '_sc_library_doc_responsible_area', true),
            (string) get_post_meta($post_id, '_sc_library_doc_authority_type', true),
            (string) get_post_meta($post_id, '_sc_library_doc_authority_note', true),
            (string) get_post_meta($post_id, '_sc_library_doc_authority_url', true),
            (string) get_post_meta($post_id, '_sc_library_doc_webpage_url', true),
            (string) get_post_meta($post_id, '_sc_library_doc_repository_url', true),
            (string) get_post_meta($post_id, '_sc_library_doc_pdf_url', true),
            (string) get_post_meta($post_id, '_sc_library_doc_release_url', true),
            (string) get_post_meta($post_id, '_sc_plan_status', true),
            (string) get_post_meta($post_id, '_sc_plan_content_type', true),
            (string) get_post_meta($post_id, '_sc_plan_area', true),
            (string) get_post_meta($post_id, '_sc_plan_product', true),
            (string) get_post_meta($post_id, '_sc_plan_expected_artifacts', true),
            (string) get_post_meta($post_id, '_sc_plan_sources', true),
            (string) get_post_meta($post_id, '_sc_plan_research_questions', true),
        ]));

        $relationship_text = implode(' ', array_map(static function (array $relation): string {
            return implode(' ', [
                (string) ($relation['type_label'] ?? ''),
                (string) ($relation['note'] ?? ''),
                get_the_title((int) ($relation['direction'] === 'incoming' ? $relation['source_post_id'] : $relation['target_post_id'])),
            ]);
        }, $this->relationships->get_for_post($post_id, false)));

        $searchable_text = trim(implode(' ', [$content, $term_text, $resource_text, $relationship_text, $pdf_text]));
        $excerpt_words = min(80, max(12, (int) get_option('sc_library_excerpt_words', 28)));
        $excerpt = has_excerpt($post_id)
            ? get_the_excerpt($post_id)
            : wp_trim_words($content, $excerpt_words, '…');

        $data = [
            'post_id' => $post_id,
            'record_identifier' => sprintf('sc:library:%s:%d', sanitize_key($post->post_type), $post_id),
            'post_type' => $post->post_type,
            'title' => wp_strip_all_tags(get_the_title($post_id)),
            'excerpt' => wp_strip_all_tags($excerpt),
            'searchable_text' => $searchable_text,
            'permalink' => get_permalink($post_id),
            'primary_category_id' => $primary_category_id,
            'primary_domain_id' => $primary_domain_id,
            'category_ids' => wp_json_encode(array_map('intval', $categories)),
            'tag_ids' => wp_json_encode(array_map('intval', $tags)),
            'series_term_id' => $series_term_id,
            'series_order' => $series_order,
            'concept_ids' => wp_json_encode($concept_ids),
            'resource_flags' => wp_json_encode($resource_flags),
            'published_at' => get_gmt_from_date($post->post_date),
            'modified_at' => get_gmt_from_date($post->post_modified),
            'indexed_at' => current_time('mysql', true),
            'status' => $post->post_status,
        ];

        $formats = [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s',
        ];
        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name()} WHERE post_id = %d", $post_id));

        if ($existing_id) {
            $result = $wpdb->update($this->table_name(), $data, ['post_id' => $post_id], $formats, ['%d']);
        } else {
            $result = $wpdb->insert($this->table_name(), $data, $formats);
        }

        return $result !== false;
    }

    private function resource_flags(int $post_id, string $raw_content): array {
        $github = esc_url_raw((string) get_post_meta($post_id, '_sc_library_github_url', true));
        $dataset_urls = $this->line_values((string) get_post_meta($post_id, '_sc_library_dataset_urls', true));
        $video_urls = $this->line_values((string) get_post_meta($post_id, '_sc_library_video_urls', true));
        $workbench_tools = $this->line_values((string) get_post_meta($post_id, '_sc_library_workbench_tools', true));
        $decision_context = $this->line_values((string) get_post_meta($post_id, '_sc_library_decision_questions', true));
        $site_context = array_merge($this->line_values((string) get_post_meta($post_id, '_sc_library_site_places', true)), $this->line_values((string) get_post_meta($post_id, '_sc_library_site_indicators', true)));

        return [
            'code' => $github !== '' || stripos($raw_content, 'github.com') !== false || preg_match('/<(pre|code)\b/i', $raw_content) === 1,
            'equations' => preg_match('/(\\\(|\\\[|\$\$|latex|mathjax)/i', $raw_content) === 1,
            'video' => !empty($video_urls) || preg_match('/(youtube\.com|youtu\.be|vimeo\.com|<video\b|wp:video)/i', $raw_content) === 1,
            'dataset' => !empty($dataset_urls),
            'workbench' => !empty($workbench_tools),
            'decision_studio' => !empty($decision_context),
            'site_intelligence' => !empty($site_context),
            'pdf' => class_exists('SC_Library_Foundation_Documents') && get_post_type($post_id) === SC_Library_Foundation_Documents::POST_TYPE && absint(get_post_meta($post_id, '_sc_foundation_pdf_attachment_id', true)) > 0,
        ];
    }

    private function line_values(string $raw): array {
        return array_values(array_filter(array_map('trim', preg_split('/\R|,/', $raw) ?: [])));
    }

    public function scan_eligibility(int $post_id, ?array $post_types = null): array {
        $post = get_post($post_id);
        if (!$post) {
            return ['eligible' => false, 'reason' => 'post_missing'];
        }
        if ($post->post_status !== 'publish') {
            return ['eligible' => false, 'reason' => 'not_published'];
        }

        $allowed = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        if (!in_array($post->post_type, $allowed, true)) {
            return ['eligible' => false, 'reason' => 'post_type_not_configured'];
        }
        if (class_exists('SC_Library_Planner') && get_post_type($post_id) === SC_Library_Planner::POST_TYPE) {
            if (!(bool) get_post_meta($post_id, '_sc_plan_public', true)) {
                return ['eligible' => false, 'reason' => 'planner_not_public'];
            }
            $status = (string) get_post_meta($post_id, '_sc_plan_status', true) ?: 'planned';
            if (!in_array($status, SC_Library_Planner::public_statuses(), true)) {
                return ['eligible' => false, 'reason' => 'planner_status_not_public'];
            }
            $published_id = absint(get_post_meta($post_id, '_sc_plan_published_post_id', true));
            if ($status === 'published' && $published_id && get_post_status($published_id) === 'publish') {
                return ['eligible' => false, 'reason' => 'planner_replaced_by_published_record'];
            }
        }
        return ['eligible' => true, 'reason' => 'eligible'];
    }

    private function sql_in(array $values): array {
        $values = array_values($values);
        return [implode(',', array_fill(0, count($values), '%s')), $values];
    }

    private function planner_eligible_sql(string $post_alias = 'p'): array {
        if (!class_exists('SC_Library_Planner') || !SC_Library_Planner::enabled()) {
            return ['1=1', []];
        }
        global $wpdb;
        $statuses = array_values(SC_Library_Planner::public_statuses());
        [$status_placeholders, $status_values] = $this->sql_in($statuses);
        $plan_type = SC_Library_Planner::POST_TYPE;
        $sql = "({$post_alias}.post_type <> %s OR (
            EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_public
                WHERE pm_public.post_id = {$post_alias}.ID
                  AND pm_public.meta_key = '_sc_plan_public'
                  AND pm_public.meta_value NOT IN ('', '0')
            )
            AND COALESCE((
                SELECT pm_status.meta_value FROM {$wpdb->postmeta} pm_status
                WHERE pm_status.post_id = {$post_alias}.ID
                  AND pm_status.meta_key = '_sc_plan_status'
                LIMIT 1
            ), 'planned') IN ({$status_placeholders})
            AND NOT (
                COALESCE((
                    SELECT pm_status2.meta_value FROM {$wpdb->postmeta} pm_status2
                    WHERE pm_status2.post_id = {$post_alias}.ID
                      AND pm_status2.meta_key = '_sc_plan_status'
                    LIMIT 1
                ), 'planned') = 'published'
                AND EXISTS (
                    SELECT 1
                    FROM {$wpdb->postmeta} pm_link
                    INNER JOIN {$wpdb->posts} linked_post
                        ON linked_post.ID = CAST(pm_link.meta_value AS UNSIGNED)
                       AND linked_post.post_status = 'publish'
                    WHERE pm_link.post_id = {$post_alias}.ID
                      AND pm_link.meta_key = '_sc_plan_published_post_id'
                      AND CAST(pm_link.meta_value AS UNSIGNED) > 0
                )
            )
        ))";
        return [$sql, array_merge([$plan_type], $status_values)];
    }

    private function mode_sql(string $mode, string $post_alias = 'p', string $index_alias = 'idx'): string {
        $modified = "CASE
            WHEN {$post_alias}.post_modified_gmt IS NULL OR {$post_alias}.post_modified_gmt = '0000-00-00 00:00:00'
            THEN {$post_alias}.post_modified
            ELSE {$post_alias}.post_modified_gmt
        END";
        return match ($mode) {
            'missing' => "{$index_alias}.post_id IS NULL",
            'outdated' => "{$index_alias}.post_id IS NOT NULL AND ({$index_alias}.indexed_at IS NULL OR {$index_alias}.indexed_at < {$modified})",
            'repair' => "({$index_alias}.post_id IS NULL OR {$index_alias}.indexed_at IS NULL OR {$index_alias}.indexed_at < {$modified})",
            default => '1=1',
        };
    }

    /** Direct database count, immune to pre_get_posts and front-end query filters. */
    public function scan_published_count(?array $post_types = null): int {
        global $wpdb;
        $post_types = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        if (!$post_types) {
            return 0;
        }
        [$placeholders, $values] = $this->sql_in($post_types);
        $sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})";
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$values));
    }

    public function scan_published_counts_by_type(?array $post_types = null): array {
        global $wpdb;
        $post_types = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        if (!$post_types) {
            return [];
        }
        [$placeholders, $values] = $this->sql_in($post_types);
        $sql = "SELECT p.post_type, COUNT(*) AS total
                FROM {$wpdb->posts} p
                WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
                GROUP BY p.post_type";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A) ?: [];
        $counts = array_fill_keys($post_types, 0);
        foreach ($rows as $row) {
            $counts[sanitize_key((string) $row['post_type'])] = absint($row['total'] ?? 0);
        }
        return $counts;
    }

    public function index_table_exists(): bool {
        global $wpdb;
        $table = $this->table_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function scan_count_for_mode(array $post_types, string $mode = 'full', bool $eligible_only = false): int {
        global $wpdb;
        $post_types = $this->normalize_post_types($post_types);
        if (!$post_types) {
            return 0;
        }
        if ($mode !== 'full' && !$this->index_table_exists()) {
            return $mode === 'outdated' ? 0 : $this->scan_count_for_mode($post_types, 'full', true);
        }

        [$placeholders, $values] = $this->sql_in($post_types);
        [$eligible_sql, $eligible_values] = $this->planner_eligible_sql('p');
        $needs_index = $mode !== 'full';
        $join = $needs_index ? " LEFT JOIN {$this->table_name()} idx ON idx.post_id = p.ID" : '';
        $mode_sql = $needs_index ? $this->mode_sql($mode) : '1=1';
        $sql = "SELECT COUNT(*)
                FROM {$wpdb->posts} p{$join}
                WHERE p.post_status = 'publish'
                  AND p.post_type IN ({$placeholders})
                  AND {$mode_sql}";
        $params = $values;
        if ($eligible_only || $mode !== 'full') {
            $sql .= " AND {$eligible_sql}";
            $params = array_merge($params, $eligible_values);
        }
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function scan_fetch_batch(array $post_types, int $cursor, int $limit, string $mode = 'full'): array {
        global $wpdb;
        $post_types = $this->normalize_post_types($post_types);
        if (!$post_types) {
            return [];
        }
        if ($mode !== 'full' && !$this->index_table_exists()) {
            $mode = $mode === 'outdated' ? 'none' : 'full_eligible';
        }
        if ($mode === 'none') {
            return [];
        }

        $limit = min(500, max(1, $limit));
        [$placeholders, $values] = $this->sql_in($post_types);
        [$eligible_sql, $eligible_values] = $this->planner_eligible_sql('p');
        $needs_index = in_array($mode, ['missing', 'outdated', 'repair'], true);
        $join = $needs_index ? " LEFT JOIN {$this->table_name()} idx ON idx.post_id = p.ID" : '';
        $indexed_at = $needs_index ? 'idx.indexed_at' : 'NULL AS indexed_at';
        $mode_sql = $needs_index ? $this->mode_sql($mode) : '1=1';
        $sql = "SELECT p.ID, p.post_type, p.post_modified_gmt, p.post_modified, {$indexed_at}
                FROM {$wpdb->posts} p{$join}
                WHERE p.post_status = 'publish'
                  AND p.post_type IN ({$placeholders})
                  AND p.ID > %d
                  AND {$mode_sql}";
        $params = array_merge($values, [$cursor]);
        if ($mode !== 'full') {
            $sql .= " AND {$eligible_sql}";
            $params = array_merge($params, $eligible_values);
        }
        $sql .= ' ORDER BY p.ID ASC LIMIT %d';
        $params[] = $limit;
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    /**
     * Compatibility helper. It now walks direct SQL batches instead of issuing
     * one unbounded WP_Query. Scanner state never stores this list.
     */
    public function scan_candidate_ids(?array $post_types = null): array {
        $post_types = $this->normalize_post_types($post_types ?: $this->configured_post_types());
        $cursor = 0;
        $ids = [];
        do {
            $rows = $this->scan_fetch_batch($post_types, $cursor, 500, 'full');
            foreach ($rows as $row) {
                $post_id = absint($row['ID'] ?? 0);
                $cursor = max($cursor, $post_id);
                if ($this->scan_eligibility($post_id, $post_types)['eligible']) {
                    $ids[] = $post_id;
                }
            }
        } while ($rows);
        return $ids;
    }

    public function scan_batch_ids(array $post_ids, ?array $post_types = null): array {
        $indexed = 0;
        $excluded = 0;
        $failed = 0;
        $outcomes = [];
        $allowed = $this->normalize_post_types($post_types ?: $this->configured_post_types());

        foreach (array_values(array_unique(array_filter(array_map('absint', $post_ids)))) as $post_id) {
            $post_type = sanitize_key((string) get_post_type($post_id));
            $eligibility = $this->scan_eligibility($post_id, $allowed);
            if (!$eligibility['eligible']) {
                $this->delete_record($post_id);
                $excluded++;
                $outcomes[] = ['post_id' => $post_id, 'post_type' => $post_type, 'outcome' => 'excluded', 'reason' => $eligibility['reason']];
                continue;
            }

            try {
                if ($this->index_post($post_id, $allowed)) {
                    $indexed++;
                    $outcomes[] = ['post_id' => $post_id, 'post_type' => $post_type, 'outcome' => 'indexed', 'reason' => 'indexed'];
                } else {
                    $failed++;
                    $outcomes[] = ['post_id' => $post_id, 'post_type' => $post_type, 'outcome' => 'failed', 'reason' => 'database_write_failed'];
                }
            } catch (Throwable $error) {
                $failed++;
                $outcomes[] = [
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'outcome' => 'failed',
                    'reason' => sanitize_text_field($error->getMessage()),
                ];
            }
        }

        return [
            'examined' => count($post_ids),
            'indexed' => $indexed,
            'skipped' => $excluded,
            'excluded' => $excluded,
            'failed' => $failed,
            'outcomes' => $outcomes,
            'failures' => array_values(array_filter($outcomes, static fn(array $row): bool => $row['outcome'] === 'failed')),
        ];
    }

    public function delete_record(int $post_id): void {
        global $wpdb;
        $wpdb->delete($this->table_name(), ['post_id' => $post_id], ['%d']);
    }

    public function purge_stale_records(?array $post_types = null): int {
        global $wpdb;

        // The optional scope controls metadata reconciliation only. Records
        // belonging to other configured post types must never be deleted when
        // an administrator scans a subset of the Library.
        $configured = $this->normalize_post_types($this->configured_post_types());
        $scope = $this->normalize_post_types($post_types ?: $configured);
        if (!$configured) {
            return 0;
        }

        [$placeholders, $values] = $this->sql_in($configured);
        $sql = "DELETE idx FROM {$this->table_name()} idx
                LEFT JOIN {$wpdb->posts} posts ON posts.ID = idx.post_id
                WHERE posts.ID IS NULL
                   OR posts.post_status <> 'publish'
                   OR posts.post_type NOT IN ({$placeholders})";
        $removed = (int) $wpdb->query($wpdb->prepare($sql, ...$values));

        // Planner visibility requires metadata checks that are intentionally
        // performed in PHP to keep the deletion statement conservative.
        if ($scope) {
            [$scope_placeholders, $scope_values] = $this->sql_in($scope);
            $remaining_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$this->table_name()} WHERE post_type IN ({$scope_placeholders})",
                ...$scope_values
            )) ?: [];
            foreach ($remaining_ids as $post_id) {
                $post_id = absint($post_id);
                if (!$this->scan_eligibility($post_id, $configured)['eligible']) {
                    $this->delete_record($post_id);
                    $removed++;
                }
            }
        }

        return $removed;
    }

    public function rebuild_all(): array {
        $types = $this->configured_post_types();
        $indexed = 0;
        $excluded = 0;
        $failed = 0;
        $cursor = 0;
        $purged = $this->purge_stale_records($types);
        $relationships_purged = $this->relationships->purge_stale();

        do {
            $rows = $this->scan_fetch_batch($types, $cursor, 100, 'full');
            $ids = [];
            foreach ($rows as $row) {
                $post_id = absint($row['ID'] ?? 0);
                $cursor = max($cursor, $post_id);
                $ids[] = $post_id;
            }
            if ($ids) {
                $batch = $this->scan_batch_ids($ids, $types);
                $indexed += absint($batch['indexed'] ?? 0);
                $excluded += absint($batch['excluded'] ?? 0);
                $failed += absint($batch['failed'] ?? 0);
            }
        } while ($rows);

        $purged += $this->purge_stale_records($types);
        update_option('sc_library_last_full_index', current_time('mysql', true));

        return [
            'indexed' => $indexed,
            'skipped' => $excluded,
            'excluded' => $excluded,
            'failed' => $failed,
            'purged' => $purged,
            'relationships_purged' => $relationships_purged,
        ];
    }

    public function count_indexed(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()}");
    }
}
