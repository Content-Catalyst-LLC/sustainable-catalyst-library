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
        $types = is_array($types) && $types
            ? array_values(array_filter(array_map('sanitize_key', $types)))
            : ['post'];
        if ($include_planner && class_exists('SC_Library_Planner') && SC_Library_Planner::enabled()) {
            $types[] = SC_Library_Planner::POST_TYPE;
        }
        return array_values(array_unique($types));
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

    public function reindex_post(int $post_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $this->configured_post_types(), true) || (class_exists('SC_Library_Planner') && !SC_Library_Planner::should_index($post_id))) {
            $this->delete_record($post_id);
            return false;
        }
        return $this->index_post($post_id);
    }

    public function index_post(int $post_id): bool {
        global $wpdb;

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $this->configured_post_types(), true) || (class_exists('SC_Library_Planner') && !SC_Library_Planner::should_index($post_id))) {
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

        $searchable_text = trim(implode(' ', [$content, $term_text, $resource_text, $relationship_text]));
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
        ];
    }

    private function line_values(string $raw): array {
        return array_values(array_filter(array_map('trim', preg_split('/\R|,/', $raw) ?: [])));
    }

    public function delete_record(int $post_id): void {
        global $wpdb;
        $wpdb->delete($this->table_name(), ['post_id' => $post_id], ['%d']);
    }

    public function purge_stale_records(): int {
        global $wpdb;

        $types = $this->configured_post_types();
        if (!$types) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "DELETE idx FROM {$this->table_name()} idx
                LEFT JOIN {$wpdb->posts} posts ON posts.ID = idx.post_id
                WHERE posts.ID IS NULL
                   OR posts.post_status <> 'publish'
                   OR posts.post_type NOT IN ({$placeholders})";

        return (int) $wpdb->query($wpdb->prepare($sql, ...$types));
    }

    public function rebuild_all(): array {
        $page = 1;
        $indexed = 0;
        $failed = 0;
        $purged = $this->purge_stale_records();
        $relationships_purged = $this->relationships->purge_stale();

        do {
            $query = new WP_Query([
                'post_type' => $this->configured_post_types(),
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'paged' => $page,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => false,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
            ]);

            foreach ($query->posts as $post_id) {
                $this->index_post((int) $post_id) ? $indexed++ : $failed++;
            }
            $page++;
        } while ($page <= (int) $query->max_num_pages);

        update_option('sc_library_last_full_index', current_time('mysql', true));

        return [
            'indexed' => $indexed,
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
