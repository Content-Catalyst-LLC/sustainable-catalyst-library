<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Knowledge Graph and Relationship Intelligence.
 *
 * WordPress publications and taxonomies remain canonical. The graph is a
 * normalized, rebuildable projection with manual and board-promoted additions.
 */
final class SC_Library_Knowledge_Graph {
    public const SCHEMA = 'sc-library-knowledge-graph/1.0';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';
    public const REBUILD_STATE_OPTION = 'sc_library_graph_rebuild_state';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 35);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_sc_library_graph_rebuild', [$this, 'handle_rebuild']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_library_knowledge_graph', [$this, 'render_graph_shortcode']);
        add_shortcode('sc_library_relationship_intelligence', [$this, 'render_intelligence_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_knowledge_graph', 1);
    }

    public function nodes_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_graph_nodes';
    }

    public function edges_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_graph_edges';
    }

    public static function node_types(): array {
        return [
            'record' => __('Library record', 'sustainable-catalyst-library'),
            'concept' => __('Concept', 'sustainable-catalyst-library'),
            'category' => __('Category or domain', 'sustainable-catalyst-library'),
            'series' => __('Article map or series', 'sustainable-catalyst-library'),
            'tag' => __('Tag', 'sustainable-catalyst-library'),
            'place' => __('Place', 'sustainable-catalyst-library'),
            'method' => __('Method', 'sustainable-catalyst-library'),
            'tool' => __('Tool', 'sustainable-catalyst-library'),
            'dataset' => __('Dataset', 'sustainable-catalyst-library'),
            'source' => __('Source', 'sustainable-catalyst-library'),
            'claim' => __('Claim', 'sustainable-catalyst-library'),
            'evidence' => __('Evidence', 'sustainable-catalyst-library'),
            'question' => __('Question', 'sustainable-catalyst-library'),
            'organization' => __('Organization', 'sustainable-catalyst-library'),
            'event' => __('Event', 'sustainable-catalyst-library'),
            'other' => __('Other entity', 'sustainable-catalyst-library'),
        ];
    }

    public static function relationship_types(): array {
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
            'categorized_as' => __('Categorized as', 'sustainable-catalyst-library'),
            'has_concept' => __('Has concept', 'sustainable-catalyst-library'),
            'part_of_series' => __('Part of series', 'sustainable-catalyst-library'),
            'tagged_with' => __('Tagged with', 'sustainable-catalyst-library'),
            'derived_from' => __('Derived from', 'sustainable-catalyst-library'),
            'validates' => __('Validates', 'sustainable-catalyst-library'),
            'influences' => __('Influences', 'sustainable-catalyst-library'),
            'occurs_at' => __('Occurs at', 'sustainable-catalyst-library'),
        ];
    }

    public static function provenance_types(): array {
        return [
            'editorial' => __('Editorially asserted', 'sustainable-catalyst-library'),
            'taxonomy' => __('Taxonomy assignment', 'sustainable-catalyst-library'),
            'article_map' => __('Article map or series', 'sustainable-catalyst-library'),
            'content_plan' => __('Content Planner dependency', 'sustainable-catalyst-library'),
            'post_metadata' => __('Publication metadata', 'sustainable-catalyst-library'),
            'board_promotion' => __('Promoted from a research board', 'sustainable-catalyst-library'),
            'import' => __('Imported graph data', 'sustainable-catalyst-library'),
            'inferred' => __('Machine or rule inferred', 'sustainable-catalyst-library'),
            'other' => __('Other documented provenance', 'sustainable-catalyst-library'),
        ];
    }

    public function register_settings(): void {
        register_setting('sc_library_graph_settings', 'sc_library_enable_knowledge_graph', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_graph_settings', 'sc_library_graph_public_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(1000, max(25, absint($value))),
            'default' => 250,
        ]);
        register_setting('sc_library_graph_settings', 'sc_library_graph_default_depth', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(4, max(1, absint($value))),
            'default' => 2,
        ]);
        register_setting('sc_library_graph_settings', 'sc_library_graph_page_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => home_url('/knowledge-graph/')]);
        register_setting('sc_library_graph_settings', 'sc_library_graph_min_public_confidence', [
            'type' => 'number',
            'sanitize_callback' => static fn($value) => min(1, max(0, (float) $value)),
            'default' => 0.25,
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Knowledge Graph', 'sustainable-catalyst-library'),
            __('Knowledge Graph', 'sustainable-catalyst-library'),
            'manage_options',
            'sc-library-knowledge-graph',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-knowledge-graph')) {
            return;
        }
        self::enqueue_assets();
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('sc-library-knowledge-graph', SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-graph.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-knowledge-graph', SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-graph.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-knowledge-graph', 'SCLibraryGraph', [
            'restBase' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/graph')),
            'nonce' => wp_create_nonce('wp_rest'),
            'canEdit' => current_user_can('edit_posts'),
            'canManage' => current_user_can('manage_options'),
            'schema' => self::SCHEMA,
            'rebuildStart' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/graph/rebuild/start')),
            'rebuildContinue' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/graph/rebuild/continue')),
            'rebuildStatus' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/graph/rebuild/status')),
            'strings' => [
                'loading' => __('Loading knowledge graph…', 'sustainable-catalyst-library'),
                'empty' => __('No graph entities match these filters.', 'sustainable-catalyst-library'),
                'error' => __('The knowledge graph could not be loaded.', 'sustainable-catalyst-library'),
                'node' => __('entity', 'sustainable-catalyst-library'),
                'edge' => __('relationship', 'sustainable-catalyst-library'),
                'rebuildStarting' => __('Starting graph rebuild…', 'sustainable-catalyst-library'),
                'rebuildComplete' => __('Knowledge graph rebuild complete.', 'sustainable-catalyst-library'),
                'rebuildError' => __('The graph rebuild stopped with an error.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage the knowledge graph.', 'sustainable-catalyst-library'));
        }
        $summary = $this->summary();
        $diagnostics = $this->diagnostics(true);
        ?>
        <div class="wrap sc-library-graph-admin">
            <h1><?php esc_html_e('Knowledge Graph and Relationship Intelligence', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Build a rebuildable graph projection from Library records, concepts, article maps, explicit relationships, planning dependencies, places, tools, datasets, and promoted research-board objects.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-graph-metrics">
                <article><strong><?php echo esc_html(number_format_i18n($summary['nodes'])); ?></strong><span><?php esc_html_e('Active entities', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($summary['edges'])); ?></strong><span><?php esc_html_e('Active relationships', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($diagnostics['orphan_count'])); ?></strong><span><?php esc_html_e('Orphaned records', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($diagnostics['duplicate_concept_group_count'])); ?></strong><span><?php esc_html_e('Duplicate concept groups', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($diagnostics['dependency_cycle_count'])); ?></strong><span><?php esc_html_e('Dependency cycles', 'sustainable-catalyst-library'); ?></span></article>
                <article><strong><?php echo esc_html(number_format_i18n($diagnostics['provenance_gap_count'])); ?></strong><span><?php esc_html_e('Provenance gaps', 'sustainable-catalyst-library'); ?></span></article>
            </div>

            <div class="card sc-library-graph-admin__card">
                <h2><?php esc_html_e('Graph publication settings', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="options.php" class="sc-library-graph-settings">
                    <?php settings_fields('sc_library_graph_settings'); ?>
                    <input type="hidden" name="sc_library_enable_knowledge_graph" value="0">
                    <label class="sc-library-graph-settings__check"><input type="checkbox" name="sc_library_enable_knowledge_graph" value="1" <?php checked(self::enabled()); ?>> <span><?php esc_html_e('Enable Knowledge Graph interfaces and endpoints', 'sustainable-catalyst-library'); ?></span></label>
                    <div class="sc-library-graph-settings__grid">
                        <label><span><?php esc_html_e('Maximum public entities', 'sustainable-catalyst-library'); ?></span><input type="number" min="25" max="1000" name="sc_library_graph_public_limit" value="<?php echo esc_attr((string) get_option('sc_library_graph_public_limit', 250)); ?>"></label>
                        <label><span><?php esc_html_e('Default neighborhood depth', 'sustainable-catalyst-library'); ?></span><input type="number" min="1" max="4" name="sc_library_graph_default_depth" value="<?php echo esc_attr((string) get_option('sc_library_graph_default_depth', 2)); ?>"></label>
                        <label><span><?php esc_html_e('Minimum public confidence', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" max="1" step="0.05" name="sc_library_graph_min_public_confidence" value="<?php echo esc_attr((string) get_option('sc_library_graph_min_public_confidence', 0.25)); ?>"></label>
                        <label><span><?php esc_html_e('Public graph page URL', 'sustainable-catalyst-library'); ?></span><input type="url" name="sc_library_graph_page_url" value="<?php echo esc_attr((string) get_option('sc_library_graph_page_url', home_url('/knowledge-graph/'))); ?>" placeholder="<?php echo esc_attr(home_url('/knowledge-graph/')); ?>"></label>
                    </div>
                    <?php submit_button(__('Save graph settings', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div class="card sc-library-graph-admin__card">
                <h2><?php esc_html_e('Rebuild graph projection', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Rebuild generated nodes and relationships from the current Library index and WordPress metadata. Manual and board-promoted graph objects are preserved.', 'sustainable-catalyst-library'); ?></p>
                <div class="sc-library-graph-rebuild" data-graph-rebuild>
                    <label><span><?php esc_html_e('Batch size', 'sustainable-catalyst-library'); ?></span><select data-graph-rebuild-batch><option>25</option><option selected>50</option><option>100</option><option>150</option><option>250</option></select></label>
                    <button type="button" class="button button-primary" data-graph-rebuild-start><?php esc_html_e('Start resumable graph rebuild', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-graph-rebuild-continue><?php esc_html_e('Continue saved rebuild', 'sustainable-catalyst-library'); ?></button>
                    <div class="sc-library-graph-rebuild__progress" data-graph-rebuild-progress role="status" aria-live="polite"><?php esc_html_e('No graph rebuild is currently running.', 'sustainable-catalyst-library'); ?></div>
                </div>
                <noscript>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sc_library_graph_rebuild">
                        <?php wp_nonce_field('sc_library_graph_rebuild'); ?>
                        <?php submit_button(__('Run synchronous graph rebuild', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
                </noscript>
                <p><small><?php echo esc_html(sprintf(__('Last rebuild: %s', 'sustainable-catalyst-library'), get_option('sc_library_graph_last_rebuild', __('Not yet rebuilt', 'sustainable-catalyst-library')))); ?></small></p>
            </div>

            <div class="card sc-library-graph-admin__card">
                <h2><?php esc_html_e('Interactive graph', 'sustainable-catalyst-library'); ?></h2>
                <?php echo $this->render_graph_shell(['mode' => 'admin', 'limit' => 350, 'show_filters' => true]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="card sc-library-graph-admin__card">
                <h2><?php esc_html_e('Relationship diagnostics', 'sustainable-catalyst-library'); ?></h2>
                <?php echo $this->render_diagnostics_markup($diagnostics); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
    }

    public function handle_rebuild(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to rebuild the graph.', 'sustainable-catalyst-library'));
        }
        check_admin_referer('sc_library_graph_rebuild');
        $result = $this->rebuild();
        $url = add_query_arg([
            'page' => 'sc-library-knowledge-graph',
            'sc_graph_rebuilt' => 1,
            'nodes' => $result['nodes'],
            'edges' => $result['edges'],
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function start_cursor_rebuild(int $batch_size = 50): array {
        global $wpdb;
        $batch_size = min(250, max(25, $batch_size));
        $state = [
            'schema' => self::SCHEMA,
            'scan_id' => wp_generate_uuid4(),
            'sync_token' => wp_generate_uuid4(),
            'status' => 'running',
            'phase' => 'records',
            'batch_size' => $batch_size,
            'record_cursor' => 0,
            'relationship_cursor' => 0,
            'planner_cursor' => 0,
            'records_processed' => 0,
            'relationships_processed' => 0,
            'plans_processed' => 0,
            'started_at' => gmdate(DATE_RFC3339),
            'updated_at' => gmdate(DATE_RFC3339),
            'completed_at' => null,
            'error' => '',
        ];
        $wpdb->query("DELETE FROM {$this->edges_table()} WHERE source_kind NOT IN ('manual','board')");
        update_option(self::REBUILD_STATE_OPTION, $state, false);
        return $state + ['summary' => $this->summary()];
    }

    public function cursor_rebuild_state(): array {
        $state = get_option(self::REBUILD_STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    public function continue_cursor_rebuild(): array {
        global $wpdb;
        $state = $this->cursor_rebuild_state();
        if (!$state || ($state['status'] ?? '') !== 'running') {
            return $state ?: ['schema' => self::SCHEMA, 'status' => 'idle', 'phase' => 'idle', 'summary' => $this->summary()];
        }
        $batch = min(250, max(25, absint($state['batch_size'] ?? 50)));
        $sync_token = sanitize_text_field((string) ($state['sync_token'] ?? ''));
        if ($sync_token === '') {
            $state['status'] = 'error';
            $state['error'] = __('The saved graph rebuild token is missing.', 'sustainable-catalyst-library');
            update_option(self::REBUILD_STATE_OPTION, $state, false);
            return $state + ['summary' => $this->summary()];
        }

        try {
            if (($state['phase'] ?? '') === 'records') {
                $cursor = absint($state['record_cursor'] ?? 0);
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->indexer->table_name()} WHERE status = 'publish' AND post_id > %d ORDER BY post_id ASC LIMIT %d",
                    $cursor,
                    $batch
                ), ARRAY_A) ?: [];
                foreach ($rows as $row) {
                    $post_id = (int) $row['post_id'];
                    $source_node_id = $this->upsert_record_node($row, $sync_token);
                    if ($source_node_id) {
                        $this->sync_record_edges($source_node_id, $post_id, $row, $sync_token);
                    }
                    $state['record_cursor'] = $post_id;
                    $state['records_processed'] = (int) $state['records_processed'] + 1;
                }
                if (count($rows) < $batch) {
                    $state['phase'] = 'relationships';
                }
            } elseif (($state['phase'] ?? '') === 'relationships') {
                $cursor = absint($state['relationship_cursor'] ?? 0);
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$this->relationships->table_name()} WHERE id > %d ORDER BY id ASC LIMIT %d",
                    $cursor,
                    $batch
                ), ARRAY_A) ?: [];
                foreach ($rows as $relationship) {
                    $source_id = $this->node_id_for_post((int) $relationship['source_post_id']);
                    $target_id = $this->node_id_for_post((int) $relationship['target_post_id']);
                    if ($source_id && $target_id) {
                        $this->sync_legacy_relationship($relationship, $source_id, $target_id);
                    }
                    $state['relationship_cursor'] = (int) $relationship['id'];
                    $state['relationships_processed'] = (int) $state['relationships_processed'] + 1;
                }
                if (count($rows) < $batch) {
                    $state['phase'] = 'plans';
                }
            } elseif (($state['phase'] ?? '') === 'plans') {
                $cursor = absint($state['planner_cursor'] ?? 0);
                $planner_type = class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan';
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT post_id FROM {$this->nodes_table()} WHERE node_type = 'record' AND subtype = %s AND post_id > %d AND status = 'active' ORDER BY post_id ASC LIMIT %d",
                    $planner_type,
                    $cursor,
                    $batch
                ), ARRAY_A) ?: [];
                foreach ($rows as $row) {
                    $post_id = (int) $row['post_id'];
                    $source_id = $this->node_id_for_post($post_id);
                    if ($source_id) {
                        $this->sync_plan_dependencies($post_id, $source_id);
                    }
                    $state['planner_cursor'] = $post_id;
                    $state['plans_processed'] = (int) $state['plans_processed'] + 1;
                }
                if (count($rows) < $batch) {
                    $state['phase'] = 'finalize';
                }
            }

            if (($state['phase'] ?? '') === 'finalize') {
                $now = current_time('mysql', true);
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$this->nodes_table()} SET status = 'stale', updated_at = %s WHERE source_kind NOT IN ('manual','board') AND sync_token <> %s",
                    $now,
                    $sync_token
                ));
                $state['status'] = 'complete';
                $state['phase'] = 'complete';
                $state['completed_at'] = gmdate(DATE_RFC3339);
                update_option('sc_library_graph_last_rebuild', current_time('mysql'), false);
                update_option('sc_library_graph_last_sync_token', $sync_token, false);
                do_action('sc_library_knowledge_graph_rebuilt', $this->summary(), $sync_token);
            }
        } catch (Throwable $error) {
            $state['status'] = 'error';
            $state['error'] = sanitize_text_field($error->getMessage());
        }
        $state['updated_at'] = gmdate(DATE_RFC3339);
        update_option(self::REBUILD_STATE_OPTION, $state, false);
        return $state + ['summary' => $this->summary()];
    }

    private function upsert_record_node(array $row, string $sync_token): int {
        $post_id = (int) $row['post_id'];
        return $this->upsert_node([
            'external_key' => 'record:' . $post_id,
            'node_type' => 'record',
            'subtype' => (string) $row['post_type'],
            'label' => (string) $row['title'],
            'description' => (string) $row['excerpt'],
            'canonical_url' => (string) $row['permalink'],
            'post_id' => $post_id,
            'visibility' => 'public',
            'source_kind' => 'record',
            'source_identifier' => (string) ($row['record_identifier'] ?: 'post:' . $post_id),
            'metadata' => [
                'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
                'primary_domain_id' => (int) ($row['primary_domain_id'] ?? 0),
                'series_order' => (float) ($row['series_order'] ?? 0),
                'resource_flags' => json_decode((string) ($row['resource_flags'] ?? ''), true) ?: [],
            ],
            'published_at' => (string) ($row['published_at'] ?? ''),
            'modified_at' => (string) ($row['modified_at'] ?? ''),
            'sync_token' => $sync_token,
            'status' => 'active',
        ]);
    }

    private function sync_record_edges(int $source_node_id, int $post_id, array $row, string $sync_token): void {
        $this->sync_taxonomy_edges($source_node_id, $post_id, 'category', 'category_ids', 'category', 'categorized_as', $row, $sync_token);
        $this->sync_taxonomy_edges($source_node_id, $post_id, SC_Library_Taxonomies::CONCEPT, 'concept_ids', 'concept', 'has_concept', $row, $sync_token);
        $this->sync_taxonomy_edges($source_node_id, $post_id, 'post_tag', 'tag_ids', 'tag', 'tagged_with', $row, $sync_token);
        $series_id = (int) ($row['series_term_id'] ?? 0);
        if ($series_id > 0) {
            $this->sync_term_edge($source_node_id, $series_id, SC_Library_Taxonomies::SERIES, 'series', 'part_of_series', 1.0, 'article_map', (float) ($row['series_order'] ?? 0), $sync_token);
        }
        $this->sync_metadata_entities($source_node_id, $post_id, $sync_token);
    }

    private function node_id_for_post(int $post_id): int {
        global $wpdb;
        if ($post_id < 1) return 0;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->nodes_table()} WHERE external_key = %s AND status = 'active' LIMIT 1", 'record:' . $post_id));
    }

    private function sync_legacy_relationship(array $relationship, int $source_id, int $target_id): void {
        $type = (string) ($relationship['relationship_type'] ?? 'related_to');
        $this->insert_edge([
            'source_node_id' => $source_id,
            'target_node_id' => $target_id,
            'relationship_type' => $type,
            'label' => self::relationship_types()[$type] ?? '',
            'directionality' => in_array($type, ['related_to', 'contrasts_with'], true) ? 'undirected' : 'directed',
            'confidence' => isset($relationship['confidence']) ? (float) $relationship['confidence'] : 0.85,
            'confidence_basis' => (string) ($relationship['confidence_basis'] ?? 'editorial'),
            'provenance_type' => (string) ($relationship['provenance_type'] ?? 'editorial'),
            'provenance_url' => (string) ($relationship['provenance_url'] ?? ''),
            'evidence_note' => (string) ($relationship['evidence_note'] ?? $relationship['note'] ?? ''),
            'visibility' => (string) ($relationship['visibility'] ?? 'public'),
            'source_kind' => 'legacy_relationship',
            'source_identifier' => 'relationship:' . (int) $relationship['id'],
            'sort_order' => (int) ($relationship['sort_order'] ?? 0),
            'metadata' => ['legacy_relationship_id' => (int) $relationship['id']],
        ]);
    }

    private function sync_plan_dependencies(int $post_id, int $source_id): void {
        $dependency_ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,]+/', (string) get_post_meta($post_id, '_sc_plan_dependency_ids', true)) ?: []))));
        foreach ($dependency_ids as $dependency_id) {
            $target_id = $this->node_id_for_post($dependency_id);
            if (!$target_id) continue;
            $this->insert_edge([
                'source_node_id' => $source_id,
                'target_node_id' => $target_id,
                'relationship_type' => 'depends_on',
                'label' => __('Depends on', 'sustainable-catalyst-library'),
                'confidence' => 1.0,
                'confidence_basis' => 'declared',
                'provenance_type' => 'content_plan',
                'evidence_note' => __('Declared Content Planner dependency.', 'sustainable-catalyst-library'),
                'visibility' => 'public',
                'source_kind' => 'planner',
                'source_identifier' => 'plan-dependency:' . $post_id . ':' . $dependency_id,
            ]);
        }
    }

    public function rebuild(): array {
        global $wpdb;
        $sync_token = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $nodes_table = $this->nodes_table();
        $edges_table = $this->edges_table();

        // Mark generated nodes stale; stable external keys preserve IDs on upsert.
        $wpdb->query("UPDATE {$nodes_table} SET status = 'stale' WHERE source_kind NOT IN ('manual','board')");
        $wpdb->query("DELETE FROM {$edges_table} WHERE source_kind NOT IN ('manual','board')");

        $rows = $wpdb->get_results("SELECT * FROM {$this->indexer->table_name()} WHERE status = 'publish' ORDER BY post_id ASC", ARRAY_A) ?: [];
        $record_nodes = [];
        foreach ($rows as $row) {
            $post_id = (int) $row['post_id'];
            $record_nodes[$post_id] = $this->upsert_node([
                'external_key' => 'record:' . $post_id,
                'node_type' => 'record',
                'subtype' => (string) $row['post_type'],
                'label' => (string) $row['title'],
                'description' => (string) $row['excerpt'],
                'canonical_url' => (string) $row['permalink'],
                'post_id' => $post_id,
                'visibility' => 'public',
                'source_kind' => 'record',
                'source_identifier' => (string) ($row['record_identifier'] ?: 'post:' . $post_id),
                'metadata' => [
                    'primary_category_id' => (int) ($row['primary_category_id'] ?? 0),
                    'primary_domain_id' => (int) ($row['primary_domain_id'] ?? 0),
                    'series_order' => (float) ($row['series_order'] ?? 0),
                    'resource_flags' => json_decode((string) ($row['resource_flags'] ?? ''), true) ?: [],
                ],
                'published_at' => (string) ($row['published_at'] ?? ''),
                'modified_at' => (string) ($row['modified_at'] ?? ''),
                'sync_token' => $sync_token,
                'status' => 'active',
            ]);
        }

        foreach ($rows as $row) {
            $post_id = (int) $row['post_id'];
            $source_node_id = (int) ($record_nodes[$post_id] ?? 0);
            if (!$source_node_id) continue;
            $this->sync_taxonomy_edges($source_node_id, $post_id, 'category', 'category_ids', 'category', 'categorized_as', $row, $sync_token);
            $this->sync_taxonomy_edges($source_node_id, $post_id, SC_Library_Taxonomies::CONCEPT, 'concept_ids', 'concept', 'has_concept', $row, $sync_token);
            $this->sync_taxonomy_edges($source_node_id, $post_id, 'post_tag', 'tag_ids', 'tag', 'tagged_with', $row, $sync_token);
            $series_id = (int) ($row['series_term_id'] ?? 0);
            if ($series_id > 0) {
                $this->sync_term_edge($source_node_id, $series_id, SC_Library_Taxonomies::SERIES, 'series', 'part_of_series', 1.0, 'article_map', (float) ($row['series_order'] ?? 0), $sync_token);
            }
            $this->sync_metadata_entities($source_node_id, $post_id, $sync_token);
        }

        $legacy = $wpdb->get_results("SELECT * FROM {$this->relationships->table_name()} ORDER BY id ASC", ARRAY_A) ?: [];
        foreach ($legacy as $relationship) {
            $source_id = (int) ($record_nodes[(int) $relationship['source_post_id']] ?? 0);
            $target_id = (int) ($record_nodes[(int) $relationship['target_post_id']] ?? 0);
            if (!$source_id || !$target_id) continue;
            $confidence = isset($relationship['confidence']) ? (float) $relationship['confidence'] : 0.85;
            $this->insert_edge([
                'source_node_id' => $source_id,
                'target_node_id' => $target_id,
                'relationship_type' => (string) $relationship['relationship_type'],
                'label' => self::relationship_types()[(string) $relationship['relationship_type']] ?? '',
                'directionality' => in_array((string) $relationship['relationship_type'], ['related_to', 'contrasts_with'], true) ? 'undirected' : 'directed',
                'confidence' => $confidence,
                'confidence_basis' => (string) ($relationship['confidence_basis'] ?? 'editorial'),
                'provenance_type' => (string) ($relationship['provenance_type'] ?? 'editorial'),
                'provenance_url' => (string) ($relationship['provenance_url'] ?? ''),
                'evidence_note' => (string) ($relationship['evidence_note'] ?? $relationship['note'] ?? ''),
                'visibility' => (string) ($relationship['visibility'] ?? 'public'),
                'source_kind' => 'legacy_relationship',
                'source_identifier' => 'relationship:' . (int) $relationship['id'],
                'sort_order' => (int) ($relationship['sort_order'] ?? 0),
                'metadata' => ['legacy_relationship_id' => (int) $relationship['id']],
            ]);
        }

        foreach ($record_nodes as $post_id => $source_id) {
            if (get_post_type($post_id) !== (class_exists('SC_Library_Planner') ? SC_Library_Planner::POST_TYPE : 'sc_content_plan')) continue;
            $dependency_ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,]+/', (string) get_post_meta($post_id, '_sc_plan_dependency_ids', true)) ?: []))));
            foreach ($dependency_ids as $dependency_id) {
                $target_id = (int) ($record_nodes[$dependency_id] ?? 0);
                if (!$target_id) continue;
                $this->insert_edge([
                    'source_node_id' => $source_id,
                    'target_node_id' => $target_id,
                    'relationship_type' => 'depends_on',
                    'label' => __('Depends on', 'sustainable-catalyst-library'),
                    'confidence' => 1.0,
                    'confidence_basis' => 'declared',
                    'provenance_type' => 'content_plan',
                    'evidence_note' => __('Declared Content Planner dependency.', 'sustainable-catalyst-library'),
                    'visibility' => 'public',
                    'source_kind' => 'planner',
                    'source_identifier' => 'plan-dependency:' . $post_id . ':' . $dependency_id,
                ]);
            }
        }

        $wpdb->query($wpdb->prepare("UPDATE {$nodes_table} SET status = 'stale', updated_at = %s WHERE source_kind NOT IN ('manual','board') AND sync_token <> %s", $now, $sync_token));
        update_option('sc_library_graph_last_rebuild', current_time('mysql'), false);
        update_option('sc_library_graph_last_sync_token', $sync_token, false);

        $summary = $this->summary();
        do_action('sc_library_knowledge_graph_rebuilt', $summary, $sync_token);
        return $summary;
    }

    private function sync_taxonomy_edges(int $source_node_id, int $post_id, string $taxonomy, string $column, string $node_type, string $relationship_type, array $row, string $sync_token): void {
        $ids = json_decode((string) ($row[$column] ?? ''), true) ?: [];
        foreach (array_values(array_unique(array_map('absint', $ids))) as $term_id) {
            if ($term_id > 0) {
                $this->sync_term_edge($source_node_id, $term_id, $taxonomy, $node_type, $relationship_type, 1.0, 'taxonomy', 0, $sync_token);
            }
        }
    }

    private function sync_term_edge(int $source_node_id, int $term_id, string $taxonomy, string $node_type, string $relationship_type, float $confidence, string $provenance_type, float $sort_order, string $sync_token): void {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return;
        $term_url = get_term_link($term);
        if (is_wp_error($term_url)) {
            $term_url = '';
        }
        $target_id = $this->upsert_node([
            'external_key' => 'term:' . $taxonomy . ':' . $term_id,
            'node_type' => $node_type,
            'subtype' => $taxonomy,
            'label' => $term->name,
            'description' => $term->description,
            'canonical_url' => $term_url,
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'visibility' => 'public',
            'source_kind' => 'taxonomy',
            'source_identifier' => $taxonomy . ':' . $term_id,
            'metadata' => ['slug' => $term->slug, 'parent' => (int) $term->parent],
            'sync_token' => $sync_token,
            'status' => 'active',
        ]);
        if (!$target_id) return;
        $this->insert_edge([
            'source_node_id' => $source_node_id,
            'target_node_id' => $target_id,
            'relationship_type' => $relationship_type,
            'label' => self::relationship_types()[$relationship_type] ?? '',
            'confidence' => $confidence,
            'confidence_basis' => 'declared',
            'provenance_type' => $provenance_type,
            'evidence_note' => __('Generated from canonical WordPress taxonomy or sequence metadata.', 'sustainable-catalyst-library'),
            'visibility' => 'public',
            'source_kind' => 'taxonomy',
            'source_identifier' => 'taxonomy-edge:' . $source_node_id . ':' . $taxonomy . ':' . $term_id,
            'sort_order' => (int) round($sort_order * 1000),
        ]);
    }

    private function sync_metadata_entities(int $source_node_id, int $post_id, string $sync_token): void {
        $definitions = [
            ['_sc_library_site_places', 'place', 'associated_with_place', false],
            ['_sc_library_workbench_tools', 'tool', 'uses_tool', false],
            ['_sc_library_dataset_urls', 'dataset', 'uses_dataset', true],
            ['_sc_library_site_sources', 'source', 'cites_source', false],
            ['_sc_library_decision_methods', 'method', 'uses_method', false],
            ['_sc_library_graph_claims', 'claim', 'describes', false],
            ['_sc_library_graph_evidence', 'evidence', 'documents', false],
            ['_sc_library_graph_questions', 'question', 'provides_context_for', false],
            ['_sc_library_graph_organizations', 'organization', 'related_to', false],
            ['_sc_library_graph_events', 'event', 'documents', false],
        ];
        foreach ($definitions as [$meta_key, $node_type, $relationship_type, $is_url]) {
            $values = preg_split('/\R/', (string) get_post_meta($post_id, $meta_key, true)) ?: [];
            foreach ($values as $value) {
                $value = trim($value);
                if ($value === '') continue;
                $label = $value;
                $published_at = '';
                $entity_metadata = ['meta_key' => $meta_key];
                if ($node_type === 'event' && preg_match('/^(\d{4}-\d{2}-\d{2})\s*\|\s*(.+)$/', $value, $matches)) {
                    $published_at = $matches[1];
                    $label = trim($matches[2]);
                    $entity_metadata['event_date'] = $matches[1];
                }
                if ($node_type === 'place' && preg_match('/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\|\s*(.+)$/', $value, $matches)) {
                    $label = trim($matches[3]);
                    $entity_metadata['latitude'] = (float) $matches[1];
                    $entity_metadata['longitude'] = (float) $matches[2];
                }
                $normalized = sanitize_title($label);
                if ($normalized === '') $normalized = substr(hash('sha256', $value), 0, 24);
                $target_id = $this->upsert_node([
                    'external_key' => $node_type . ':' . $normalized,
                    'node_type' => $node_type,
                    'subtype' => $is_url ? 'url' : 'named',
                    'label' => $label,
                    'description' => '',
                    'canonical_url' => $is_url ? esc_url_raw($value) : '',
                    'visibility' => 'public',
                    'source_kind' => 'metadata',
                    'source_identifier' => $meta_key . ':' . $normalized,
                    'metadata' => $entity_metadata,
                    'published_at' => $published_at,
                    'sync_token' => $sync_token,
                    'status' => 'active',
                ]);
                if (!$target_id) continue;
                $this->insert_edge([
                    'source_node_id' => $source_node_id,
                    'target_node_id' => $target_id,
                    'relationship_type' => $relationship_type,
                    'label' => self::relationship_types()[$relationship_type] ?? '',
                    'confidence' => 0.9,
                    'confidence_basis' => 'declared',
                    'provenance_type' => 'post_metadata',
                    'provenance_url' => $is_url ? esc_url_raw($value) : '',
                    'evidence_note' => __('Generated from publication metadata.', 'sustainable-catalyst-library'),
                    'visibility' => 'public',
                    'source_kind' => 'metadata',
                    'source_identifier' => 'metadata-edge:' . $source_node_id . ':' . $meta_key . ':' . $normalized,
                ]);
            }
        }
        $this->sync_source_claim_edges($post_id, $sync_token);
    }

    private function sync_source_claim_edges(int $post_id, string $sync_token): void {
        $lines = preg_split('/\R/', (string) get_post_meta($post_id, '_sc_library_graph_source_claims', true)) ?: [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s*(?:=>|→)\s*/u', trim($line), 2) ?: [];
            if (count($parts) !== 2) continue;
            $source_label = trim((string) $parts[0]);
            $claim_label = trim((string) $parts[1]);
            if ($source_label === '' || $claim_label === '') continue;
            $source_key = sanitize_title($source_label) ?: substr(hash('sha256', $source_label), 0, 24);
            $claim_key = sanitize_title($claim_label) ?: substr(hash('sha256', $claim_label), 0, 24);
            $source_id = $this->upsert_node([
                'external_key' => 'source:' . $source_key,
                'node_type' => 'source',
                'subtype' => filter_var($source_label, FILTER_VALIDATE_URL) ? 'url' : 'named',
                'label' => $source_label,
                'canonical_url' => filter_var($source_label, FILTER_VALIDATE_URL) ? esc_url_raw($source_label) : '',
                'visibility' => 'public',
                'source_kind' => 'metadata',
                'source_identifier' => '_sc_library_graph_source_claims:source:' . $source_key,
                'metadata' => ['meta_key' => '_sc_library_graph_source_claims', 'post_id' => $post_id],
                'sync_token' => $sync_token,
                'status' => 'active',
            ]);
            $claim_id = $this->upsert_node([
                'external_key' => 'claim:' . $claim_key,
                'node_type' => 'claim',
                'subtype' => 'assertion',
                'label' => $claim_label,
                'visibility' => 'public',
                'source_kind' => 'metadata',
                'source_identifier' => '_sc_library_graph_source_claims:claim:' . $claim_key,
                'metadata' => ['meta_key' => '_sc_library_graph_source_claims', 'post_id' => $post_id],
                'sync_token' => $sync_token,
                'status' => 'active',
            ]);
            if (!$source_id || !$claim_id) continue;
            $this->insert_edge([
                'source_node_id' => $source_id,
                'target_node_id' => $claim_id,
                'relationship_type' => 'supports',
                'label' => __('Supports', 'sustainable-catalyst-library'),
                'confidence' => 0.9,
                'confidence_basis' => 'declared',
                'provenance_type' => 'post_metadata',
                'provenance_url' => filter_var($source_label, FILTER_VALIDATE_URL) ? esc_url_raw($source_label) : '',
                'evidence_note' => __('Explicit source-to-claim link recorded in publication metadata.', 'sustainable-catalyst-library'),
                'visibility' => 'public',
                'source_kind' => 'metadata',
                'source_identifier' => 'source-claim:' . $post_id . ':' . $source_key . ':' . $claim_key,
                'metadata' => ['post_id' => $post_id],
            ]);
        }
    }

    private function upsert_node(array $data): int {
        global $wpdb;
        $table = $this->nodes_table();
        $external_key = substr(sanitize_text_field((string) ($data['external_key'] ?? '')), 0, 191);
        if ($external_key === '') return 0;
        $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE external_key = %s", $external_key));
        $now = current_time('mysql', true);
        $record = [
            'node_uuid' => $existing ? (string) $wpdb->get_var($wpdb->prepare("SELECT node_uuid FROM {$table} WHERE id = %d", $existing)) : wp_generate_uuid4(),
            'external_key' => $external_key,
            'node_type' => sanitize_key((string) ($data['node_type'] ?? 'other')),
            'subtype' => sanitize_key((string) ($data['subtype'] ?? '')),
            'label' => sanitize_text_field((string) ($data['label'] ?? 'Untitled entity')),
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'canonical_url' => esc_url_raw((string) ($data['canonical_url'] ?? '')),
            'post_id' => absint($data['post_id'] ?? 0),
            'term_id' => absint($data['term_id'] ?? 0),
            'taxonomy' => sanitize_key((string) ($data['taxonomy'] ?? '')),
            'visibility' => in_array((string) ($data['visibility'] ?? 'public'), ['public', 'private', 'organization'], true) ? (string) $data['visibility'] : 'public',
            'source_kind' => sanitize_key((string) ($data['source_kind'] ?? 'generated')),
            'source_identifier' => substr(sanitize_text_field((string) ($data['source_identifier'] ?? '')), 0, 191),
            'metadata_json' => wp_json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'published_at' => $this->mysql_or_null((string) ($data['published_at'] ?? '')),
            'modified_at' => $this->mysql_or_null((string) ($data['modified_at'] ?? '')),
            'sync_token' => substr(sanitize_text_field((string) ($data['sync_token'] ?? '')), 0, 64),
            'status' => in_array((string) ($data['status'] ?? 'active'), ['active', 'stale', 'archived'], true) ? (string) $data['status'] : 'active',
            'updated_at' => $now,
        ];
        if ($existing) {
            $wpdb->update($table, $record, ['id' => $existing]);
            return $existing;
        }
        $record['created_at'] = $now;
        $inserted = $wpdb->insert($table, $record);
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    private function insert_edge(array $data): int {
        global $wpdb;
        $source = absint($data['source_node_id'] ?? 0);
        $target = absint($data['target_node_id'] ?? 0);
        $type = sanitize_key((string) ($data['relationship_type'] ?? 'related_to'));
        if (!$source || !$target || $source === $target || !isset(self::relationship_types()[$type])) return 0;
        $source_identifier = substr(sanitize_text_field((string) ($data['source_identifier'] ?? '')), 0, 191);
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->edges_table()} WHERE source_node_id = %d AND target_node_id = %d AND relationship_type = %s AND source_identifier = %s LIMIT 1",
            $source,
            $target,
            $type,
            $source_identifier
        ));
        $now = current_time('mysql', true);
        $record = [
            'edge_uuid' => $existing ? (string) $wpdb->get_var($wpdb->prepare("SELECT edge_uuid FROM {$this->edges_table()} WHERE id = %d", $existing)) : wp_generate_uuid4(),
            'source_node_id' => $source,
            'target_node_id' => $target,
            'relationship_type' => $type,
            'label' => sanitize_text_field((string) ($data['label'] ?? self::relationship_types()[$type])),
            'directionality' => in_array((string) ($data['directionality'] ?? 'directed'), ['directed', 'undirected'], true) ? (string) $data['directionality'] : 'directed',
            'confidence' => min(1, max(0, (float) ($data['confidence'] ?? 0.75))),
            'confidence_basis' => sanitize_key((string) ($data['confidence_basis'] ?? 'editorial')),
            'provenance_type' => sanitize_key((string) ($data['provenance_type'] ?? 'editorial')),
            'provenance_url' => esc_url_raw((string) ($data['provenance_url'] ?? '')),
            'evidence_note' => sanitize_textarea_field((string) ($data['evidence_note'] ?? '')),
            'visibility' => in_array((string) ($data['visibility'] ?? 'public'), ['public', 'private', 'organization'], true) ? (string) $data['visibility'] : 'public',
            'source_kind' => sanitize_key((string) ($data['source_kind'] ?? 'manual')),
            'source_identifier' => $source_identifier,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'metadata_json' => wp_json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => absint($data['created_by'] ?? get_current_user_id()),
            'verified_by' => absint($data['verified_by'] ?? 0),
            'verified_at' => $this->mysql_or_null((string) ($data['verified_at'] ?? '')),
            'updated_at' => $now,
        ];
        if ($existing) {
            $wpdb->update($this->edges_table(), $record, ['id' => $existing]);
            return $existing;
        }
        $record['created_at'] = $now;
        $inserted = $wpdb->insert($this->edges_table(), $record);
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    private function mysql_or_null(string $value): ?string {
        $value = trim($value);
        if ($value === '') return null;
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    public function summary(): array {
        global $wpdb;
        return [
            'nodes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->nodes_table()} WHERE status = 'active'"),
            'edges' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->edges_table()}"),
            'public_nodes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->nodes_table()} WHERE status = 'active' AND visibility = 'public'"),
            'public_edges' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->edges_table()} WHERE visibility = 'public'"),
        ];
    }

    public function graph_payload(array $args = [], bool $admin = false): array {
        global $wpdb;
        $limit = min($admin ? 2000 : (int) get_option('sc_library_graph_public_limit', 250), max(1, absint($args['limit'] ?? 250)));
        $search = sanitize_text_field((string) ($args['search'] ?? ''));
        $node_type = sanitize_key((string) ($args['node_type'] ?? ''));
        $edge_type = sanitize_key((string) ($args['edge_type'] ?? ''));
        $root = sanitize_text_field((string) ($args['root'] ?? ''));
        $depth = min(4, max(1, absint($args['depth'] ?? get_option('sc_library_graph_default_depth', 2))));
        $minimum = $admin ? 0.0 : (float) get_option('sc_library_graph_min_public_confidence', 0.25);

        $where = ["n.status = 'active'"];
        $values = [];
        if (!$admin) $where[] = "n.visibility = 'public'";
        if ($node_type && isset(self::node_types()[$node_type])) {
            $where[] = 'n.node_type = %s';
            $values[] = $node_type;
        }
        if ($search !== '') {
            $where[] = '(n.label LIKE %s OR n.description LIKE %s OR n.external_key LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($values, $like, $like, $like);
        }
        if ($root !== '') {
            $root_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->nodes_table()} WHERE node_uuid = %s OR external_key = %s LIMIT 1", $root, $root), ARRAY_A);
            if ($root_row) {
                $node_ids = $this->neighborhood_ids((int) $root_row['id'], $depth, $admin, $minimum, $limit);
                if ($node_ids) {
                    $where[] = 'n.id IN (' . implode(',', array_map('absint', $node_ids)) . ')';
                }
            }
        }
        $sql = "SELECT n.* FROM {$this->nodes_table()} n WHERE " . implode(' AND ', $where) . " ORDER BY CASE n.node_type WHEN 'record' THEN 0 WHEN 'concept' THEN 1 WHEN 'series' THEN 2 ELSE 3 END, n.label ASC LIMIT %d";
        $values[] = $limit;
        $nodes = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A) ?: [];
        $ids = array_map(static fn($row) => (int) $row['id'], $nodes);
        if (!$ids) return ['schema' => self::SCHEMA, 'summary' => $this->summary(), 'nodes' => [], 'edges' => [], 'filters' => $this->filter_payload()];

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $edge_where = ["e.source_node_id IN ({$placeholders})", "e.target_node_id IN ({$placeholders})", 'e.confidence >= %f'];
        $edge_values = array_merge($ids, $ids, [$minimum]);
        if (!$admin) $edge_where[] = "e.visibility = 'public'";
        if ($edge_type && isset(self::relationship_types()[$edge_type])) {
            $edge_where[] = 'e.relationship_type = %s';
            $edge_values[] = $edge_type;
        }
        $edges = $wpdb->get_results($wpdb->prepare("SELECT e.* FROM {$this->edges_table()} e WHERE " . implode(' AND ', $edge_where) . ' ORDER BY e.confidence DESC, e.id ASC', ...$edge_values), ARRAY_A) ?: [];

        return [
            'schema' => self::SCHEMA,
            'generated_at' => gmdate(DATE_RFC3339),
            'summary' => ['nodes' => count($nodes), 'edges' => count($edges)] + $this->summary(),
            'nodes' => array_map([$this, 'hydrate_node'], $nodes),
            'edges' => array_map([$this, 'hydrate_edge'], $edges),
            'filters' => $this->filter_payload(),
        ];
    }

    private function neighborhood_ids(int $root_id, int $depth, bool $admin, float $minimum, int $limit): array {
        global $wpdb;
        $visited = [$root_id => true];
        $frontier = [$root_id];
        for ($level = 0; $level < $depth && $frontier; $level++) {
            $placeholders = implode(',', array_fill(0, count($frontier), '%d'));
            $values = array_merge($frontier, $frontier, [$minimum]);
            $visibility = $admin ? '' : " AND visibility = 'public'";
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT source_node_id, target_node_id FROM {$this->edges_table()} WHERE (source_node_id IN ({$placeholders}) OR target_node_id IN ({$placeholders})) AND confidence >= %f {$visibility} LIMIT 5000",
                ...$values
            ), ARRAY_A) ?: [];
            $next = [];
            foreach ($rows as $row) {
                foreach ([(int) $row['source_node_id'], (int) $row['target_node_id']] as $id) {
                    if (!isset($visited[$id])) {
                        $visited[$id] = true;
                        $next[] = $id;
                        if (count($visited) >= $limit) break 3;
                    }
                }
            }
            $frontier = $next;
        }
        return array_keys($visited);
    }

    private function hydrate_node(array $row): array {
        return [
            'id' => (int) $row['id'],
            'uuid' => (string) $row['node_uuid'],
            'external_key' => (string) $row['external_key'],
            'type' => (string) $row['node_type'],
            'type_label' => self::node_types()[(string) $row['node_type']] ?? ucfirst((string) $row['node_type']),
            'subtype' => (string) $row['subtype'],
            'label' => (string) $row['label'],
            'description' => wp_strip_all_tags((string) $row['description']),
            'url' => esc_url_raw((string) $row['canonical_url']),
            'post_id' => (int) $row['post_id'],
            'term_id' => (int) $row['term_id'],
            'taxonomy' => (string) $row['taxonomy'],
            'visibility' => (string) $row['visibility'],
            'source_kind' => (string) $row['source_kind'],
            'source_identifier' => (string) $row['source_identifier'],
            'published_at' => (string) ($row['published_at'] ?? ''),
            'modified_at' => (string) ($row['modified_at'] ?? ''),
            'metadata' => json_decode((string) $row['metadata_json'], true) ?: [],
        ];
    }

    private function hydrate_edge(array $row): array {
        return [
            'id' => (int) $row['id'],
            'uuid' => (string) $row['edge_uuid'],
            'source' => (int) $row['source_node_id'],
            'target' => (int) $row['target_node_id'],
            'type' => (string) $row['relationship_type'],
            'type_label' => self::relationship_types()[(string) $row['relationship_type']] ?? (string) $row['label'],
            'label' => (string) $row['label'],
            'directionality' => (string) $row['directionality'],
            'confidence' => (float) $row['confidence'],
            'confidence_basis' => (string) $row['confidence_basis'],
            'provenance_type' => (string) $row['provenance_type'],
            'provenance_url' => esc_url_raw((string) $row['provenance_url']),
            'evidence_note' => (string) $row['evidence_note'],
            'visibility' => (string) $row['visibility'],
            'source_kind' => (string) $row['source_kind'],
            'source_identifier' => (string) $row['source_identifier'],
            'verified_by' => (int) $row['verified_by'],
            'verified_at' => (string) ($row['verified_at'] ?? ''),
            'metadata' => json_decode((string) $row['metadata_json'], true) ?: [],
        ];
    }

    private function filter_payload(): array {
        return [
            'node_types' => array_map(static fn($label, $id) => ['id' => $id, 'label' => $label], self::node_types(), array_keys(self::node_types())),
            'relationship_types' => array_map(static fn($label, $id) => ['id' => $id, 'label' => $label], self::relationship_types(), array_keys(self::relationship_types())),
        ];
    }

    public function diagnostics(bool $admin = false): array {
        global $wpdb;
        $visibility_node = $admin ? '' : " AND n.visibility = 'public'";
        $visibility_edge = $admin ? '' : " AND e.visibility = 'public'";
        $orphan_edge_visibility = $admin ? '' : " AND e.visibility = 'public'";
        $orphans = $wpdb->get_results(
            "SELECT n.id, n.node_uuid, n.label, n.external_key, n.post_id FROM {$this->nodes_table()} n
             LEFT JOIN {$this->edges_table()} e ON (e.source_node_id = n.id OR e.target_node_id = n.id) {$orphan_edge_visibility}
             WHERE n.status = 'active' AND n.node_type = 'record' {$visibility_node} AND e.id IS NULL
             ORDER BY n.label ASC LIMIT 250",
            ARRAY_A
        ) ?: [];
        $concepts = $wpdb->get_results("SELECT id, node_uuid, label, external_key FROM {$this->nodes_table()} n WHERE n.status = 'active' AND n.node_type = 'concept' {$visibility_node} ORDER BY n.label ASC", ARRAY_A) ?: [];
        $duplicate_groups = [];
        foreach ($concepts as $concept) {
            $normalized = $this->normalize_concept((string) $concept['label']);
            if ($normalized !== '') $duplicate_groups[$normalized][] = $concept;
        }
        $duplicate_groups = array_values(array_filter($duplicate_groups, static fn($group) => count($group) > 1));
        $endpoint_visibility = $admin ? '' : " AND source.visibility = 'public' AND target.visibility = 'public' AND source.status = 'active' AND target.status = 'active'";
        $provenance_gaps = $wpdb->get_results("SELECT e.id, e.edge_uuid, e.relationship_type, e.source_node_id, e.target_node_id FROM {$this->edges_table()} e JOIN {$this->nodes_table()} source ON source.id = e.source_node_id JOIN {$this->nodes_table()} target ON target.id = e.target_node_id WHERE (e.provenance_type = '' OR (e.evidence_note = '' AND e.provenance_url = '')) {$visibility_edge} {$endpoint_visibility} ORDER BY e.id ASC LIMIT 250", ARRAY_A) ?: [];
        $low_confidence = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->edges_table()} e JOIN {$this->nodes_table()} source ON source.id = e.source_node_id JOIN {$this->nodes_table()} target ON target.id = e.target_node_id WHERE e.confidence < 0.5 {$visibility_edge} {$endpoint_visibility}");
        $unverified = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->edges_table()} e JOIN {$this->nodes_table()} source ON source.id = e.source_node_id JOIN {$this->nodes_table()} target ON target.id = e.target_node_id WHERE e.verified_at IS NULL {$visibility_edge} {$endpoint_visibility}");
        $cycles = $this->dependency_cycles($admin);
        return [
            'orphan_count' => count($orphans),
            'orphans' => array_map(static fn($row) => ['id' => (int) $row['id'], 'uuid' => $row['node_uuid'], 'label' => $row['label'], 'external_key' => $row['external_key'], 'post_id' => (int) $row['post_id']], $orphans),
            'duplicate_concept_group_count' => count($duplicate_groups),
            'duplicate_concepts' => $duplicate_groups,
            'dependency_cycle_count' => count($cycles),
            'dependency_cycles' => $cycles,
            'provenance_gap_count' => count($provenance_gaps),
            'provenance_gaps' => $provenance_gaps,
            'low_confidence_count' => $low_confidence,
            'unverified_count' => $unverified,
        ];
    }

    private function normalize_concept(string $label): string {
        $value = strtolower(remove_accents(wp_strip_all_tags($label)));
        $value = preg_replace('/\b(the|a|an|and|of|for|to)\b/', ' ', $value) ?: $value;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '');
    }

    private function dependency_cycles(bool $admin): array {
        global $wpdb;
        $visibility = $admin ? '' : " AND e.visibility = 'public' AND source.visibility = 'public' AND target.visibility = 'public' AND source.status = 'active' AND target.status = 'active'";
        $rows = $wpdb->get_results("SELECT e.source_node_id, e.target_node_id FROM {$this->edges_table()} e JOIN {$this->nodes_table()} source ON source.id = e.source_node_id JOIN {$this->nodes_table()} target ON target.id = e.target_node_id WHERE e.relationship_type = 'depends_on' {$visibility}", ARRAY_A) ?: [];
        $graph = [];
        foreach ($rows as $row) $graph[(int) $row['source_node_id']][] = (int) $row['target_node_id'];
        $cycles = [];
        $state = [];
        $stack = [];
        $visit = function (int $node) use (&$visit, &$graph, &$cycles, &$state, &$stack): void {
            $state[$node] = 1;
            $stack[] = $node;
            foreach ($graph[$node] ?? [] as $target) {
                if (($state[$target] ?? 0) === 0) {
                    $visit($target);
                } elseif (($state[$target] ?? 0) === 1) {
                    $position = array_search($target, $stack, true);
                    if ($position !== false) {
                        $cycle = array_slice($stack, $position);
                        $cycle[] = $target;
                        $key = implode(':', $cycle);
                        $cycles[$key] = $cycle;
                    }
                }
            }
            array_pop($stack);
            $state[$node] = 2;
        };
        foreach (array_keys($graph) as $node) if (($state[$node] ?? 0) === 0) $visit((int) $node);
        $labels = [];
        foreach ($cycles as $cycle) {
            $ids = array_values(array_unique(array_map('absint', $cycle)));
            if (!$ids) continue;
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $rows = $wpdb->get_results($wpdb->prepare("SELECT id, label FROM {$this->nodes_table()} WHERE id IN ({$placeholders})", ...$ids), OBJECT_K) ?: [];
            $labels[] = array_map(static fn($id) => ['id' => $id, 'label' => isset($rows[$id]) ? $rows[$id]->label : '#' . $id], $cycle);
        }
        return array_values($labels);
    }

    public function timeline_payload(bool $admin = false): array {
        global $wpdb;
        $visibility = $admin ? '' : " AND visibility = 'public'";
        $rows = $wpdb->get_results("SELECT * FROM {$this->nodes_table()} WHERE status = 'active' AND node_type IN ('record','event') AND published_at IS NOT NULL {$visibility} ORDER BY published_at ASC LIMIT 2000", ARRAY_A) ?: [];
        return ['schema' => self::SCHEMA, 'items' => array_map([$this, 'hydrate_node'], $rows)];
    }

    public function places_payload(bool $admin = false): array {
        global $wpdb;
        $visibility = $admin ? '' : " AND n.visibility = 'public'";
        $edge_visibility = $admin ? '' : " AND e.visibility = 'public'";
        $rows = $wpdb->get_results(
            "SELECT n.*, COUNT(e.id) AS relationship_count FROM {$this->nodes_table()} n
             LEFT JOIN {$this->edges_table()} e ON e.target_node_id = n.id AND e.relationship_type IN ('associated_with_place','occurs_at') {$edge_visibility}
             WHERE n.status = 'active' AND n.node_type = 'place' {$visibility}
             GROUP BY n.id ORDER BY relationship_count DESC, n.label ASC LIMIT 500",
            ARRAY_A
        ) ?: [];
        return ['schema' => self::SCHEMA, 'items' => array_map(function ($row) { $item = $this->hydrate_node($row); $item['relationship_count'] = (int) $row['relationship_count']; return $item; }, $rows)];
    }

    public function promote_board(array $board, int $user_id): array|WP_Error {
        if (!$board || empty($board['nodes']) || !is_array($board['nodes'])) {
            return new WP_Error('sc_library_invalid_board', __('A board with nodes is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $board_id = sanitize_text_field((string) ($board['id'] ?? wp_generate_uuid4()));
        $visibility = in_array((string) ($board['visibility'] ?? 'private'), ['public', 'private', 'organization'], true) ? (string) $board['visibility'] : 'private';
        $mapping = [];
        foreach (array_slice($board['nodes'], 0, 500) as $node) {
            if (!is_array($node)) continue;
            $node_id = sanitize_text_field((string) ($node['id'] ?? wp_generate_uuid4()));
            $type = sanitize_key((string) ($node['type'] ?? 'other'));
            if (!isset(self::node_types()[$type])) $type = $type === 'publication' ? 'record' : 'other';
            $record_id = absint($node['recordId'] ?? $node['postId'] ?? 0);
            $external_key = $record_id ? 'record:' . $record_id : 'board:' . $board_id . ':' . $node_id;
            $graph_node_id = $this->upsert_node([
                'external_key' => $external_key,
                'node_type' => $type,
                'subtype' => sanitize_key((string) ($node['subtype'] ?? 'board')),
                'label' => sanitize_text_field((string) ($node['title'] ?? $node['label'] ?? 'Untitled board entity')),
                'description' => sanitize_textarea_field((string) ($node['body'] ?? $node['description'] ?? '')),
                'canonical_url' => esc_url_raw((string) ($node['url'] ?? '')),
                'post_id' => $record_id,
                'visibility' => $visibility,
                'source_kind' => $record_id ? 'record' : 'board',
                'source_identifier' => 'board:' . $board_id . ':' . $node_id,
                'metadata' => ['board_id' => $board_id, 'board_node_id' => $node_id, 'promoted_by' => $user_id],
                'status' => 'active',
            ]);
            if ($graph_node_id) $mapping[$node_id] = $graph_node_id;
        }
        $created_edges = 0;
        foreach (array_slice(is_array($board['edges'] ?? null) ? $board['edges'] : [], 0, 1000) as $edge) {
            if (!is_array($edge)) continue;
            $source_key = sanitize_text_field((string) ($edge['from'] ?? $edge['source'] ?? $edge['sourceId'] ?? ''));
            $target_key = sanitize_text_field((string) ($edge['to'] ?? $edge['target'] ?? $edge['targetId'] ?? ''));
            if (!isset($mapping[$source_key], $mapping[$target_key])) continue;
            $type = sanitize_key((string) ($edge['type'] ?? 'related_to'));
            if (!isset(self::relationship_types()[$type])) $type = 'related_to';
            $created_edges += $this->insert_edge([
                'source_node_id' => $mapping[$source_key],
                'target_node_id' => $mapping[$target_key],
                'relationship_type' => $type,
                'label' => sanitize_text_field((string) ($edge['label'] ?? self::relationship_types()[$type])),
                'directionality' => in_array($type, ['related_to', 'contrasts_with'], true) ? 'undirected' : 'directed',
                'confidence' => min(1, max(0, (float) ($edge['confidence'] ?? 0.65))),
                'confidence_basis' => 'board_assertion',
                'provenance_type' => 'board_promotion',
                'evidence_note' => sanitize_textarea_field((string) ($edge['note'] ?? __('Promoted from a Library research board.', 'sustainable-catalyst-library'))),
                'visibility' => $visibility,
                'source_kind' => 'board',
                'source_identifier' => 'board-edge:' . $board_id . ':' . sanitize_text_field((string) ($edge['id'] ?? wp_generate_uuid4())),
                'metadata' => ['board_id' => $board_id, 'promoted_by' => $user_id],
                'created_by' => $user_id,
            ]) ? 1 : 0;
        }
        return ['schema' => self::SCHEMA, 'board_id' => $board_id, 'nodes_promoted' => count($mapping), 'edges_promoted' => $created_edges];
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/library/graph/schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response(['schema' => self::SCHEMA, 'node_types' => self::node_types(), 'relationship_types' => self::relationship_types(), 'provenance_types' => self::provenance_types()]),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request) {
                return rest_ensure_response($this->graph_payload($request->get_params(), current_user_can('edit_posts') && $request->get_param('context') === 'edit'));
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/diagnostics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => rest_ensure_response($this->diagnostics(current_user_can('manage_options') && $request->get_param('context') === 'edit')),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/timeline', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => rest_ensure_response($this->timeline_payload(current_user_can('edit_posts') && $request->get_param('context') === 'edit')),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/places', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => rest_ensure_response($this->places_payload(current_user_can('edit_posts') && $request->get_param('context') === 'edit')),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/rebuild/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn() => rest_ensure_response($this->cursor_rebuild_state() ?: ['schema' => self::SCHEMA, 'status' => 'idle', 'phase' => 'idle', 'summary' => $this->summary()]),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/rebuild/start', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => fn(WP_REST_Request $request) => rest_ensure_response($this->start_cursor_rebuild(absint($request->get_param('batch_size') ?: 50))),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/rebuild/continue', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => fn() => rest_ensure_response($this->continue_cursor_rebuild()),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/rebuild', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => fn(WP_REST_Request $request) => rest_ensure_response(['ok' => true] + $this->start_cursor_rebuild(absint($request->get_param('batch_size') ?: 50))),
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/board-promotions', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function (WP_REST_Request $request) {
                $result = $this->promote_board((array) $request->get_json_params(), get_current_user_id());
                return is_wp_error($result) ? $result : rest_ensure_response($result);
            },
            'permission_callback' => static fn() => current_user_can('edit_posts'),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/graph/edges', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function (WP_REST_Request $request) {
                $data = (array) $request->get_json_params();
                $id = $this->insert_edge([
                    'source_node_id' => absint($data['source_node_id'] ?? 0),
                    'target_node_id' => absint($data['target_node_id'] ?? 0),
                    'relationship_type' => sanitize_key((string) ($data['relationship_type'] ?? 'related_to')),
                    'label' => sanitize_text_field((string) ($data['label'] ?? '')),
                    'directionality' => sanitize_key((string) ($data['directionality'] ?? 'directed')),
                    'confidence' => (float) ($data['confidence'] ?? 0.75),
                    'confidence_basis' => sanitize_key((string) ($data['confidence_basis'] ?? 'editorial')),
                    'provenance_type' => sanitize_key((string) ($data['provenance_type'] ?? 'editorial')),
                    'provenance_url' => esc_url_raw((string) ($data['provenance_url'] ?? '')),
                    'evidence_note' => sanitize_textarea_field((string) ($data['evidence_note'] ?? '')),
                    'visibility' => sanitize_key((string) ($data['visibility'] ?? 'private')),
                    'source_kind' => 'manual',
                    'source_identifier' => 'manual:' . wp_generate_uuid4(),
                    'created_by' => get_current_user_id(),
                ]);
                return $id ? rest_ensure_response(['ok' => true, 'edge_id' => $id]) : new WP_Error('sc_library_graph_edge_invalid', __('The relationship could not be created.', 'sustainable-catalyst-library'), ['status' => 400]);
            },
            'permission_callback' => static fn() => current_user_can('edit_posts'),
        ]);
    }

    public function render_graph_shortcode(array $atts = []): string {
        if (!self::enabled()) return '<p>' . esc_html__('The Knowledge Graph is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        $atts = shortcode_atts(['title' => 'Knowledge Graph', 'mode' => 'public', 'limit' => '250', 'root' => '', 'depth' => '2', 'show_filters' => 'true'], $atts, 'sc_library_knowledge_graph');
        self::enqueue_assets();
        return '<section class="sc-library-graph-shortcode"><header><p class="sc-library-graph__eyebrow">' . esc_html__('Sustainable Catalyst Knowledge Graph', 'sustainable-catalyst-library') . '</p><h2>' . esc_html(sanitize_text_field($atts['title'])) . '</h2><p>' . esc_html__('Explore publications, concepts, article maps, methods, tools, datasets, places, and documented relationships through a provenance-aware graph.', 'sustainable-catalyst-library') . '</p></header>' . $this->render_graph_shell(['mode' => 'public', 'limit' => absint($atts['limit']), 'root' => sanitize_text_field($atts['root']), 'depth' => absint($atts['depth']), 'show_filters' => filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN)]) . '</section>';
    }

    public function render_intelligence_shortcode(array $atts = []): string {
        if (!self::enabled()) return '';
        $atts = shortcode_atts(['title' => 'Relationship Intelligence'], $atts, 'sc_library_relationship_intelligence');
        self::enqueue_assets();
        $diagnostics = $this->diagnostics(false);
        return '<section class="sc-library-graph-intelligence"><header><p class="sc-library-graph__eyebrow">' . esc_html__('Graph quality and coverage', 'sustainable-catalyst-library') . '</p><h2>' . esc_html(sanitize_text_field($atts['title'])) . '</h2></header>' . $this->render_diagnostics_markup($diagnostics) . '</section>';
    }

    private function render_graph_shell(array $args): string {
        $mode = $args['mode'] === 'admin' ? 'admin' : 'public';
        $limit = min(1000, max(25, absint($args['limit'] ?? 250)));
        $root = sanitize_text_field((string) ($args['root'] ?? ''));
        $depth = min(4, max(1, absint($args['depth'] ?? 2)));
        $show_filters = !empty($args['show_filters']);
        ob_start();
        ?>
        <div class="sc-library-graph" data-sc-library-graph data-mode="<?php echo esc_attr($mode); ?>" data-limit="<?php echo esc_attr((string) $limit); ?>" data-root="<?php echo esc_attr($root); ?>" data-depth="<?php echo esc_attr((string) $depth); ?>">
            <?php if ($show_filters) : ?>
            <form class="sc-library-graph__filters" data-graph-filters>
                <label><span><?php esc_html_e('Search entities', 'sustainable-catalyst-library'); ?></span><input type="search" name="search" placeholder="<?php esc_attr_e('Publication, concept, tool, place…', 'sustainable-catalyst-library'); ?>"></label>
                <label><span><?php esc_html_e('Entity type', 'sustainable-catalyst-library'); ?></span><select name="node_type"><option value=""><?php esc_html_e('All entity types', 'sustainable-catalyst-library'); ?></option><?php foreach (self::node_types() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Relationship', 'sustainable-catalyst-library'); ?></span><select name="edge_type"><option value=""><?php esc_html_e('All relationships', 'sustainable-catalyst-library'); ?></option><?php foreach (self::relationship_types() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <button type="submit"><?php esc_html_e('Update graph', 'sustainable-catalyst-library'); ?></button>
                <button type="reset" class="sc-library-graph__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button>
            </form>
            <?php endif; ?>
            <div class="sc-library-graph__status" data-graph-status aria-live="polite"><?php esc_html_e('Loading knowledge graph…', 'sustainable-catalyst-library'); ?></div>
            <div class="sc-library-graph__layout">
                <div class="sc-library-graph__canvas" data-graph-canvas tabindex="0" aria-label="<?php esc_attr_e('Interactive knowledge graph', 'sustainable-catalyst-library'); ?>"></div>
                <aside class="sc-library-graph__inspector" data-graph-inspector><p><?php esc_html_e('Select an entity or relationship to inspect its provenance and context.', 'sustainable-catalyst-library'); ?></p></aside>
            </div>
            <details class="sc-library-graph__accessible"><summary><?php esc_html_e('Accessible relationship list', 'sustainable-catalyst-library'); ?></summary><div data-graph-list></div></details>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_diagnostics_markup(array $diagnostics): string {
        ob_start();
        ?>
        <div class="sc-library-graph-diagnostics">
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['orphan_count'])); ?></strong><h3><?php esc_html_e('Orphaned records', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Published records with no graph relationships.', 'sustainable-catalyst-library'); ?></p></article>
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['duplicate_concept_group_count'])); ?></strong><h3><?php esc_html_e('Duplicate concept groups', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Concept labels that normalize to the same phrase and may require consolidation.', 'sustainable-catalyst-library'); ?></p></article>
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['dependency_cycle_count'])); ?></strong><h3><?php esc_html_e('Dependency cycles', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Circular depends-on chains that can block planning or sequencing.', 'sustainable-catalyst-library'); ?></p></article>
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['provenance_gap_count'])); ?></strong><h3><?php esc_html_e('Provenance gaps', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Relationships without a documented evidence note or source URL.', 'sustainable-catalyst-library'); ?></p></article>
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['low_confidence_count'])); ?></strong><h3><?php esc_html_e('Low-confidence relationships', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Relationships below 0.50 confidence that should be reviewed before prominent public use.', 'sustainable-catalyst-library'); ?></p></article>
            <article><strong><?php echo esc_html(number_format_i18n((int) $diagnostics['unverified_count'])); ?></strong><h3><?php esc_html_e('Unverified relationships', 'sustainable-catalyst-library'); ?></h3><p><?php esc_html_e('Relationships without an explicit verification event.', 'sustainable-catalyst-library'); ?></p></article>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
