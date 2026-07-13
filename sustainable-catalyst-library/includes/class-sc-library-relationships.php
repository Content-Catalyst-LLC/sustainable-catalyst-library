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


    public function provenance_types(): array {
        return class_exists('SC_Library_Knowledge_Graph')
            ? SC_Library_Knowledge_Graph::provenance_types()
            : [
                'editorial' => __('Editorially asserted', 'sustainable-catalyst-library'),
                'taxonomy' => __('Taxonomy assignment', 'sustainable-catalyst-library'),
                'article_map' => __('Article map or series', 'sustainable-catalyst-library'),
                'content_plan' => __('Content Planner dependency', 'sustainable-catalyst-library'),
                'post_metadata' => __('Publication metadata', 'sustainable-catalyst-library'),
                'other' => __('Other documented provenance', 'sustainable-catalyst-library'),
            ];
    }

    public function confidence_bases(): array {
        return [
            'declared' => __('Declared in canonical metadata', 'sustainable-catalyst-library'),
            'editorial' => __('Editorial judgment', 'sustainable-catalyst-library'),
            'evidence_review' => __('Evidence review', 'sustainable-catalyst-library'),
            'rule_inferred' => __('Rule inferred', 'sustainable-catalyst-library'),
            'machine_inferred' => __('Machine inferred', 'sustainable-catalyst-library'),
            'unknown' => __('Not yet documented', 'sustainable-catalyst-library'),
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
                'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0.85,
                'confidence_basis' => (string) ($row['confidence_basis'] ?? 'editorial'),
                'provenance_type' => (string) ($row['provenance_type'] ?? 'editorial'),
                'provenance_url' => (string) ($row['provenance_url'] ?? ''),
                'evidence_note' => (string) ($row['evidence_note'] ?? ''),
                'visibility' => (string) ($row['visibility'] ?? 'public'),
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
                    'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : 0.85,
                    'confidence_basis' => (string) ($row['confidence_basis'] ?? 'editorial'),
                    'provenance_type' => (string) ($row['provenance_type'] ?? 'editorial'),
                    'provenance_url' => (string) ($row['provenance_url'] ?? ''),
                    'evidence_note' => (string) ($row['evidence_note'] ?? ''),
                    'visibility' => (string) ($row['visibility'] ?? 'public'),
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
            $manual_target_id = absint($relationship['target_post_id_manual'] ?? 0);
            $target_id = $manual_target_id ?: absint($relationship['target_post_id'] ?? 0);
            $type = sanitize_key((string) ($relationship['relationship_type'] ?? 'related_to'));
            $note = sanitize_textarea_field((string) ($relationship['note'] ?? ''));
            $confidence = min(1, max(0, (float) ($relationship['confidence'] ?? 0.85)));
            $confidence_basis = sanitize_key((string) ($relationship['confidence_basis'] ?? 'editorial'));
            if (!isset($this->confidence_bases()[$confidence_basis])) $confidence_basis = 'editorial';
            $provenance_type = sanitize_key((string) ($relationship['provenance_type'] ?? 'editorial'));
            if (!isset($this->provenance_types()[$provenance_type])) $provenance_type = 'editorial';
            $provenance_url = esc_url_raw((string) ($relationship['provenance_url'] ?? ''));
            $evidence_note = sanitize_textarea_field((string) ($relationship['evidence_note'] ?? ''));
            $visibility = in_array((string) ($relationship['visibility'] ?? 'public'), ['public', 'private', 'organization'], true)
                ? (string) $relationship['visibility']
                : 'public';

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
                'confidence' => $confidence,
                'confidence_basis' => $confidence_basis,
                'provenance_type' => $provenance_type,
                'provenance_url' => $provenance_url,
                'evidence_note' => $evidence_note,
                'visibility' => $visibility,
                'sort_order' => (int) $position,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
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
