<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Relationships {
    public function register_hooks(): void {
        add_action('before_delete_post', [$this, 'delete_for_post']);
    }

    public function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_relationships';
    }

    public function types(): array {
        return [
            'related_to' => __('Related to', 'sustainable-catalyst-library'),
            'precedes' => __('Precedes', 'sustainable-catalyst-library'),
            'follows' => __('Follows', 'sustainable-catalyst-library'),
            'explains' => __('Explains', 'sustainable-catalyst-library'),
            'applies' => __('Applies', 'sustainable-catalyst-library'),
            'supports' => __('Supports', 'sustainable-catalyst-library'),
            'challenges' => __('Challenges', 'sustainable-catalyst-library'),
            'uses_method' => __('Uses method', 'sustainable-catalyst-library'),
            'uses_tool' => __('Uses tool', 'sustainable-catalyst-library'),
            'uses_dataset' => __('Uses dataset', 'sustainable-catalyst-library'),
            'has_code_companion' => __('Has code companion', 'sustainable-catalyst-library'),
            'cites_source' => __('Cites source', 'sustainable-catalyst-library'),
            'supports_pathway' => __('Supports pathway', 'sustainable-catalyst-library'),
            'associated_with_place' => __('Associated with place', 'sustainable-catalyst-library'),
            'provides_context_for' => __('Provides context for', 'sustainable-catalyst-library'),
            'extends' => __('Extends', 'sustainable-catalyst-library'),
            'contrasts_with' => __('Contrasts with', 'sustainable-catalyst-library'),
            'documents' => __('Documents', 'sustainable-catalyst-library'),
            'implements' => __('Implements', 'sustainable-catalyst-library'),
            'governs' => __('Governs', 'sustainable-catalyst-library'),
            'describes' => __('Describes', 'sustainable-catalyst-library'),
            'defines' => __('Defines', 'sustainable-catalyst-library'),
            'depends_on' => __('Depends on', 'sustainable-catalyst-library'),
            'replaces' => __('Replaces', 'sustainable-catalyst-library'),
            'supersedes' => __('Supersedes', 'sustainable-catalyst-library'),
            'superseded_by' => __('Superseded by', 'sustainable-catalyst-library'),
            'snapshot_of' => __('Snapshot of', 'sustainable-catalyst-library'),
            'technical_source_for' => __('Technical source for', 'sustainable-catalyst-library'),
            'methodology_for' => __('Methodology for', 'sustainable-catalyst-library'),
            'policy_governing' => __('Policy governing', 'sustainable-catalyst-library'),
            'release_record_for' => __('Release record for', 'sustainable-catalyst-library'),
        ];
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name()}");
    }

    public function get_for_post(int $post_id, bool $include_incoming = true): array {
        global $wpdb;
        $table = $this->table_name();
        $types = $this->types();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_post_id = %d ORDER BY sort_order ASC, id ASC",
                $post_id
            ),
            ARRAY_A
        ) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $type = (string) $row['relationship_type'];
            if (!isset($types[$type])) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['id'],
                'direction' => 'outgoing',
                'source_post_id' => (int) $row['source_post_id'],
                'target_post_id' => (int) $row['target_post_id'],
                'type' => $type,
                'type_label' => $types[$type],
                'note' => (string) $row['note'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        if ($include_incoming) {
            $incoming = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE target_post_id = %d ORDER BY sort_order ASC, id ASC",
                    $post_id
                ),
                ARRAY_A
            ) ?: [];

            foreach ($incoming as $row) {
                $type = (string) $row['relationship_type'];
                if (!isset($types[$type])) {
                    continue;
                }
                $result[] = [
                    'id' => (int) $row['id'],
                    'direction' => 'incoming',
                    'source_post_id' => (int) $row['source_post_id'],
                    'target_post_id' => (int) $row['target_post_id'],
                    'type' => $type,
                    'type_label' => sprintf(__('Referenced as: %s', 'sustainable-catalyst-library'), $types[$type]),
                    'note' => (string) $row['note'],
                    'sort_order' => (int) $row['sort_order'],
                ];
            }
        }

        return $result;
    }

    public function save_for_post(int $post_id, array $relationships): void {
        global $wpdb;
        $table = $this->table_name();
        $types = $this->types();
        $now = current_time('mysql', true);

        $wpdb->delete($table, ['source_post_id' => $post_id], ['%d']);

        $seen = [];
        foreach ($relationships as $position => $relationship) {
            $target_id = absint($relationship['target_post_id'] ?? 0);
            $type = sanitize_key((string) ($relationship['relationship_type'] ?? 'related_to'));
            $note = sanitize_textarea_field((string) ($relationship['note'] ?? ''));

            if ($target_id < 1 || $target_id === $post_id || !isset($types[$type]) || get_post_status($target_id) !== 'publish') {
                continue;
            }

            $key = $target_id . ':' . $type;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $wpdb->insert($table, [
                'source_post_id' => $post_id,
                'target_post_id' => $target_id,
                'relationship_type' => $type,
                'note' => $note,
                'sort_order' => (int) $position,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s', '%d', '%s', '%s']);
        }
    }

    public function delete_for_post(int $post_id): void {
        global $wpdb;
        $table = $this->table_name();
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE source_post_id = %d OR target_post_id = %d",
            $post_id,
            $post_id
        ));
    }

    public function purge_stale(): int {
        global $wpdb;
        $table = $this->table_name();
        return (int) $wpdb->query(
            "DELETE rel FROM {$table} rel
             LEFT JOIN {$wpdb->posts} source_post ON source_post.ID = rel.source_post_id
             LEFT JOIN {$wpdb->posts} target_post ON target_post.ID = rel.target_post_id
             WHERE source_post.ID IS NULL OR target_post.ID IS NULL
                OR source_post.post_status <> 'publish' OR target_post.post_status <> 'publish'"
        );
    }
}
