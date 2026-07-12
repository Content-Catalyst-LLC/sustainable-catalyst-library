<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Editor {
    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save'], 20, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_meta_boxes(): void {
        foreach ($this->indexer->configured_post_types() as $post_type) {
            add_meta_box(
                'sc-library-record',
                __('Library Relationships', 'sustainable-catalyst-library'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    public function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, $this->indexer->configured_post_types(), true)) {
            return;
        }

        wp_enqueue_style('sc-library-admin', SC_LIBRARY_URL . 'assets/css/sc-library-admin.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-admin', SC_LIBRARY_URL . 'assets/js/sc-library-admin.js', [], SC_LIBRARY_VERSION, true);
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sc_library_save_record', 'sc_library_record_nonce');

        $primary_domain = absint(get_post_meta($post->ID, '_sc_library_primary_domain_id', true));
        $series_order = (string) get_post_meta($post->ID, '_sc_library_series_order', true);
        $evidence_status = (string) get_post_meta($post->ID, '_sc_library_evidence_status', true);
        $github_url = (string) get_post_meta($post->ID, '_sc_library_github_url', true);
        $dataset_urls = (string) get_post_meta($post->ID, '_sc_library_dataset_urls', true);
        $video_urls = (string) get_post_meta($post->ID, '_sc_library_video_urls', true);
        $workbench_tools = (string) get_post_meta($post->ID, '_sc_library_workbench_tools', true);
        $workbench_questions = (string) get_post_meta($post->ID, '_sc_library_workbench_questions', true);
        $decision_questions = (string) get_post_meta($post->ID, '_sc_library_decision_questions', true);
        $decision_methods = (string) get_post_meta($post->ID, '_sc_library_decision_methods', true);
        $site_places = (string) get_post_meta($post->ID, '_sc_library_site_places', true);
        $site_indicators = (string) get_post_meta($post->ID, '_sc_library_site_indicators', true);
        $site_sources = (string) get_post_meta($post->ID, '_sc_library_site_sources', true);
        $relations = $this->relationships->get_for_post($post->ID, false);
        $categories = get_categories(['hide_empty' => false, 'orderby' => 'name']);
        $candidate_posts = $this->candidate_posts($post->ID);
        $types = $this->relationships->types();
        ?>
        <div class="sc-library-admin-record">
            <p class="description"><?php esc_html_e('Define where this record sits in the knowledge architecture and how it connects to other Library records. Library Series and Library Concepts are assigned through their taxonomy panels.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-admin-grid">
                <label>
                    <span><?php esc_html_e('Primary Library domain', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_library_primary_domain_id">
                        <option value="0"><?php esc_html_e('Use first assigned category', 'sustainable-catalyst-library'); ?></option>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($primary_domain, (int) $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Series order', 'sustainable-catalyst-library'); ?></span>
                    <input type="number" step="0.001" min="0" name="sc_library_series_order" value="<?php echo esc_attr($series_order); ?>" placeholder="1">
                    <small><?php esc_html_e('Used for previous/next navigation inside the assigned Library Series.', 'sustainable-catalyst-library'); ?></small>
                </label>

                <label>
                    <span><?php esc_html_e('Evidence status', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_library_evidence_status">
                        <?php foreach ($this->evidence_statuses() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($evidence_status, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('GitHub or code companion URL', 'sustainable-catalyst-library'); ?></span>
                    <input type="url" name="sc_library_github_url" value="<?php echo esc_attr($github_url); ?>" placeholder="https://github.com/…">
                </label>
            </div>

            <div class="sc-library-admin-grid sc-library-admin-grid--wide">
                <label>
                    <span><?php esc_html_e('Workbench tool IDs', 'sustainable-catalyst-library'); ?></span>
                    <textarea name="sc_library_workbench_tools" rows="4" placeholder="risk-resilience-scorecard&#10;energy-systems-calculator"><?php echo esc_textarea($workbench_tools); ?></textarea>
                    <small><?php esc_html_e('One tool identifier per line.', 'sustainable-catalyst-library'); ?></small>
                </label>
                <label>
                    <span><?php esc_html_e('Dataset URLs', 'sustainable-catalyst-library'); ?></span>
                    <textarea name="sc_library_dataset_urls" rows="4" placeholder="https://…"><?php echo esc_textarea($dataset_urls); ?></textarea>
                    <small><?php esc_html_e('One public dataset or source URL per line.', 'sustainable-catalyst-library'); ?></small>
                </label>
                <label>
                    <span><?php esc_html_e('Video URLs', 'sustainable-catalyst-library'); ?></span>
                    <textarea name="sc_library_video_urls" rows="4" placeholder="https://youtube.com/…"><?php echo esc_textarea($video_urls); ?></textarea>
                    <small><?php esc_html_e('One video or timestamped clip URL per line.', 'sustainable-catalyst-library'); ?></small>
                </label>
            </div>

            <h3><?php esc_html_e('Connected application context', 'sustainable-catalyst-library'); ?></h3>
            <div class="sc-library-admin-grid sc-library-admin-grid--wide">
                <label><span><?php esc_html_e('Workbench questions', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_workbench_questions" rows="4" placeholder="What should be calculated or validated?"><?php echo esc_textarea($workbench_questions); ?></textarea><small><?php esc_html_e('One technical question or analytical task per line.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Decision Studio questions', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_decision_questions" rows="4" placeholder="What decision does this evidence inform?"><?php echo esc_textarea($decision_questions); ?></textarea><small><?php esc_html_e('One decision, claim, uncertainty, or tradeoff question per line.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Decision methods', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_decision_methods" rows="4" placeholder="scenario comparison&#10;multi-criteria analysis"><?php echo esc_textarea($decision_methods); ?></textarea><small><?php esc_html_e('Methods or canvas types suited to this record.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Site Intelligence places', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_site_places" rows="4" placeholder="KEN&#10;Chicago, Illinois"><?php echo esc_textarea($site_places); ?></textarea><small><?php esc_html_e('Countries, ISO codes, regions, cities, or named places.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Site Intelligence indicators', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_site_indicators" rows="4" placeholder="population_total&#10;renewable_energy_share"><?php echo esc_textarea($site_indicators); ?></textarea><small><?php esc_html_e('Indicator IDs or research measures relevant to the publication.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Site Intelligence source IDs', 'sustainable-catalyst-library'); ?></span><textarea name="sc_library_site_sources" rows="4" placeholder="world-bank&#10;nasa-eo"><?php echo esc_textarea($site_sources); ?></textarea><small><?php esc_html_e('Public source-registry identifiers used for geographic context.', 'sustainable-catalyst-library'); ?></small></label>
            </div>

            <section class="sc-library-admin-relations">
                <div class="sc-library-admin-relations__head">
                    <div>
                        <h3><?php esc_html_e('Typed knowledge relationships', 'sustainable-catalyst-library'); ?></h3>
                        <p><?php esc_html_e('Create intentional connections that will appear in the public record panel and REST API.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                    <button type="button" class="button" data-sc-add-relationship><?php esc_html_e('Add relationship', 'sustainable-catalyst-library'); ?></button>
                </div>

                <div data-sc-relationship-list>
                    <?php foreach ($relations as $index => $relation) : ?>
                        <?php $this->render_relationship_row($index, $relation, $candidate_posts, $types); ?>
                    <?php endforeach; ?>
                </div>

                <template data-sc-relationship-template>
                    <?php $this->render_relationship_row('__INDEX__', [], $candidate_posts, $types); ?>
                </template>

                <p class="sc-library-admin-empty" data-sc-relationship-empty <?php echo $relations ? 'hidden' : ''; ?>><?php esc_html_e('No explicit relationships have been added. Category and series similarity will still provide suggested related knowledge.', 'sustainable-catalyst-library'); ?></p>
            </section>
        </div>
        <?php
    }

    private function render_relationship_row($index, array $relation, array $candidate_posts, array $types): void {
        $target_id = absint($relation['target_post_id'] ?? 0);
        $type = sanitize_key((string) ($relation['type'] ?? 'related_to'));
        $note = (string) ($relation['note'] ?? '');
        $name = 'sc_library_relationships[' . $index . ']';
        ?>
        <div class="sc-library-admin-relation" data-sc-relationship-row>
            <label>
                <span><?php esc_html_e('Target record', 'sustainable-catalyst-library'); ?></span>
                <select name="<?php echo esc_attr($name); ?>[target_post_id]">
                    <option value="0"><?php esc_html_e('Choose a publication', 'sustainable-catalyst-library'); ?></option>
                    <?php foreach ($candidate_posts as $candidate) : ?>
                        <option value="<?php echo esc_attr((string) $candidate->ID); ?>" <?php selected($target_id, (int) $candidate->ID); ?>>
                            <?php echo esc_html($candidate->post_title . ' — #' . $candidate->ID); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Relationship', 'sustainable-catalyst-library'); ?></span>
                <select name="<?php echo esc_attr($name); ?>[relationship_type]">
                    <?php foreach ($types as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="sc-library-admin-relation__note">
                <span><?php esc_html_e('Context note', 'sustainable-catalyst-library'); ?></span>
                <input type="text" name="<?php echo esc_attr($name); ?>[note]" value="<?php echo esc_attr($note); ?>" placeholder="<?php esc_attr_e('Why these records are connected', 'sustainable-catalyst-library'); ?>">
            </label>
            <button type="button" class="button-link-delete" data-sc-remove-relationship><?php esc_html_e('Remove', 'sustainable-catalyst-library'); ?></button>
        </div>
        <?php
    }

    private function candidate_posts(int $exclude_id): array {
        $query = new WP_Query([
            'post_type' => $this->indexer->configured_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => 1500,
            'post__not_in' => [$exclude_id],
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);
        return $query->posts;
    }

    private function evidence_statuses(): array {
        return [
            '' => __('Not specified', 'sustainable-catalyst-library'),
            'foundational' => __('Foundational overview', 'sustainable-catalyst-library'),
            'evidence-linked' => __('Evidence linked', 'sustainable-catalyst-library'),
            'code-backed' => __('Code backed', 'sustainable-catalyst-library'),
            'data-backed' => __('Data backed', 'sustainable-catalyst-library'),
            'reviewed' => __('Reviewed', 'sustainable-catalyst-library'),
            'experimental' => __('Experimental', 'sustainable-catalyst-library'),
            'archival' => __('Archival or historical', 'sustainable-catalyst-library'),
        ];
    }

    public function save(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!in_array($post->post_type, $this->indexer->configured_post_types(), true)) {
            return;
        }
        if (!isset($_POST['sc_library_record_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_record_nonce'])), 'sc_library_save_record')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $primary_domain = absint($_POST['sc_library_primary_domain_id'] ?? 0);
        $series_order_raw = isset($_POST['sc_library_series_order']) ? sanitize_text_field(wp_unslash($_POST['sc_library_series_order'])) : '';
        $series_order = is_numeric($series_order_raw) ? max(0, (float) $series_order_raw) : 0;
        $evidence_status = sanitize_key((string) ($_POST['sc_library_evidence_status'] ?? ''));
        if (!array_key_exists($evidence_status, $this->evidence_statuses())) {
            $evidence_status = '';
        }

        update_post_meta($post_id, '_sc_library_primary_domain_id', $primary_domain);
        update_post_meta($post_id, '_sc_library_series_order', $series_order);
        update_post_meta($post_id, '_sc_library_evidence_status', $evidence_status);
        update_post_meta($post_id, '_sc_library_github_url', esc_url_raw((string) ($_POST['sc_library_github_url'] ?? '')));
        update_post_meta($post_id, '_sc_library_dataset_urls', $this->sanitize_lines((string) ($_POST['sc_library_dataset_urls'] ?? ''), true));
        update_post_meta($post_id, '_sc_library_video_urls', $this->sanitize_lines((string) ($_POST['sc_library_video_urls'] ?? ''), true));
        update_post_meta($post_id, '_sc_library_workbench_tools', $this->sanitize_lines((string) ($_POST['sc_library_workbench_tools'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_workbench_questions', $this->sanitize_lines((string) ($_POST['sc_library_workbench_questions'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_decision_questions', $this->sanitize_lines((string) ($_POST['sc_library_decision_questions'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_decision_methods', $this->sanitize_lines((string) ($_POST['sc_library_decision_methods'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_site_places', $this->sanitize_lines((string) ($_POST['sc_library_site_places'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_site_indicators', $this->sanitize_lines((string) ($_POST['sc_library_site_indicators'] ?? ''), false));
        update_post_meta($post_id, '_sc_library_site_sources', $this->sanitize_lines((string) ($_POST['sc_library_site_sources'] ?? ''), false));

        $raw_relationships = isset($_POST['sc_library_relationships']) && is_array($_POST['sc_library_relationships'])
            ? wp_unslash($_POST['sc_library_relationships'])
            : [];
        $this->relationships->save_for_post($post_id, $raw_relationships);

        if ($post->post_status === 'publish') {
            $this->indexer->index_post($post_id);
        }
    }

    private function sanitize_lines(string $raw, bool $urls): string {
        $lines = preg_split('/\R/', wp_unslash($raw)) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $value = $urls ? esc_url_raw($line) : sanitize_text_field($line);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        return implode("\n", array_values(array_unique($clean)));
    }
}
