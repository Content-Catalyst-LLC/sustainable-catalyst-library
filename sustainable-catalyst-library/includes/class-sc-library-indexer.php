<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Indexer {
    public function register_hooks(): void {
        add_action('save_post', [$this, 'on_save_post'], 20, 3);
        add_action('before_delete_post', [$this, 'delete_record']);
        add_action('trashed_post', [$this, 'delete_record']);
        add_action('untrashed_post', [$this, 'reindex_post']);
        add_action('set_object_terms', [$this, 'on_terms_changed'], 20, 6);
        add_action('sc_library_daily_reconcile', [$this, 'rebuild_all']);
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_index';
    }

    public function configured_post_types(): array {
        $types = get_option('sc_library_post_types', ['post']);
        return is_array($types) && $types
            ? array_values(array_filter(array_map('sanitize_key', $types)))
            : ['post'];
    }

    public function on_save_post(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, $this->configured_post_types(), true)) {
            $this->delete_record($post_id);
            return;
        }
        if ($post->post_status !== 'publish') {
            $this->delete_record($post_id);
            return;
        }
        $this->index_post($post_id);
    }

    public function on_terms_changed(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void {
        if (in_array($taxonomy, ['category', 'post_tag'], true)) {
            $this->reindex_post($object_id);
        }
    }

    public function reindex_post(int $post_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $this->configured_post_types(), true)) {
            $this->delete_record($post_id);
            return false;
        }
        return $this->index_post($post_id);
    }

    public function index_post(int $post_id): bool {
        global $wpdb;

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, $this->configured_post_types(), true)) {
            return false;
        }

        $categories = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
        $primary_category_id = $categories ? (int) $categories[0] : null;

        $content = strip_shortcodes($post->post_content);
        $content = wp_strip_all_tags($content, true);
        $content = preg_replace('/\s+/', ' ', $content ?: '');

        $excerpt_words = min(80, max(12, (int) get_option('sc_library_excerpt_words', 28)));
        $excerpt = has_excerpt($post_id)
            ? get_the_excerpt($post_id)
            : wp_trim_words($content, $excerpt_words, '…');

        $data = [
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'title' => wp_strip_all_tags(get_the_title($post_id)),
            'excerpt' => wp_strip_all_tags($excerpt),
            'searchable_text' => $content,
            'permalink' => get_permalink($post_id),
            'primary_category_id' => $primary_category_id,
            'category_ids' => wp_json_encode(array_map('intval', $categories)),
            'tag_ids' => wp_json_encode(array_map('intval', $tags)),
            'published_at' => get_gmt_from_date($post->post_date),
            'modified_at' => get_gmt_from_date($post->post_modified),
            'indexed_at' => current_time('mysql', true),
            'status' => $post->post_status,
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'];
        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name()} WHERE post_id = %d", $post_id));

        if ($existing_id) {
            $result = $wpdb->update($this->table_name(), $data, ['post_id' => $post_id], $formats, ['%d']);
        } else {
            $result = $wpdb->insert($this->table_name(), $data, $formats);
        }

        return $result !== false;
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
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
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
        ];
    }

    public function count_indexed(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()}");
    }
}
