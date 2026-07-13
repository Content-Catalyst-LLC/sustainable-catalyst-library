<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Planner, complete public registry, and roadmap tracker.
 *
 * Planned records remain WordPress content objects so revisions, permissions,
 * taxonomies, drafts, and publication handoffs remain native to WordPress.
 */
final class SC_Library_Planner {
    public const POST_TYPE = 'sc_content_plan';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1/library';

    private SC_Library_Indexer $indexer;
    private SC_Library_Relationships $relationships;

    public function __construct(SC_Library_Indexer $indexer, SC_Library_Relationships $relationships) {
        $this->indexer = $indexer;
        $this->relationships = $relationships;
    }

    public function register_hooks(): void {
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_plan'], 20, 3);
        add_action('transition_post_status', [$this, 'sync_published_plan'], 30, 3);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_sc_library_scan_article_map', [$this, 'handle_scan_article_map']);
        add_action('admin_post_sc_library_create_map_plans', [$this, 'handle_create_map_plans']);
        add_action('admin_post_sc_library_convert_plan', [$this, 'handle_convert_plan']);
        add_action('admin_post_sc_library_export_registry', [$this, 'handle_admin_export']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        add_shortcode('sc_library_registry', [$this, 'render_registry_shortcode']);
        add_shortcode('sc_library_planner_tracker', [$this, 'render_tracker_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_planner', 1);
    }

    public static function statuses(): array {
        return [
            'idea' => __('Idea', 'sustainable-catalyst-library'),
            'proposed' => __('Proposed', 'sustainable-catalyst-library'),
            'planned' => __('Planned', 'sustainable-catalyst-library'),
            'researching' => __('Researching', 'sustainable-catalyst-library'),
            'drafting' => __('Drafting', 'sustainable-catalyst-library'),
            'review' => __('In review', 'sustainable-catalyst-library'),
            'scheduled' => __('Scheduled', 'sustainable-catalyst-library'),
            'published' => __('Published', 'sustainable-catalyst-library'),
            'deferred' => __('Deferred', 'sustainable-catalyst-library'),
            'cancelled' => __('Cancelled', 'sustainable-catalyst-library'),
            'superseded' => __('Superseded', 'sustainable-catalyst-library'),
        ];
    }

    public static function public_statuses(): array {
        return ['planned', 'researching', 'drafting', 'review', 'scheduled', 'published', 'deferred'];
    }

    public static function content_types(): array {
        return [
            'article' => __('Article', 'sustainable-catalyst-library'),
            'article_map' => __('Article map or series', 'sustainable-catalyst-library'),
            'documentation' => __('Documentation', 'sustainable-catalyst-library'),
            'product_brief' => __('Product brief', 'sustainable-catalyst-library'),
            'methodology' => __('Methodology or trust record', 'sustainable-catalyst-library'),
            'dataset' => __('Dataset', 'sustainable-catalyst-library'),
            'calculator' => __('Calculator or analytical tool', 'sustainable-catalyst-library'),
            'code_companion' => __('Code companion', 'sustainable-catalyst-library'),
            'video' => __('Video or multimedia', 'sustainable-catalyst-library'),
            'research_pathway' => __('Research pathway', 'sustainable-catalyst-library'),
            'pdf_snapshot' => __('PDF snapshot', 'sustainable-catalyst-library'),
            'release_record' => __('Release record', 'sustainable-catalyst-library'),
            'policy' => __('Policy or license', 'sustainable-catalyst-library'),
            'custom' => __('Custom planned record', 'sustainable-catalyst-library'),
        ];
    }

    public static function release_types(): array {
        return [
            'none' => __('No expected date', 'sustainable-catalyst-library'),
            'exact' => __('Exact date', 'sustainable-catalyst-library'),
            'month' => __('Month', 'sustainable-catalyst-library'),
            'quarter' => __('Quarter', 'sustainable-catalyst-library'),
            'year' => __('Year', 'sustainable-catalyst-library'),
            'product' => __('Product release', 'sustainable-catalyst-library'),
        ];
    }

    public static function priority_levels(): array {
        return [
            'low' => __('Low', 'sustainable-catalyst-library'),
            'normal' => __('Normal', 'sustainable-catalyst-library'),
            'high' => __('High', 'sustainable-catalyst-library'),
            'critical' => __('Critical', 'sustainable-catalyst-library'),
        ];
    }

    public function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Content Planner', 'sustainable-catalyst-library'),
                'singular_name' => __('Planned Content', 'sustainable-catalyst-library'),
                'add_new' => __('Add planned content', 'sustainable-catalyst-library'),
                'add_new_item' => __('Add planned content record', 'sustainable-catalyst-library'),
                'edit_item' => __('Edit planned content', 'sustainable-catalyst-library'),
                'new_item' => __('New planned content', 'sustainable-catalyst-library'),
                'view_item' => __('View public plan', 'sustainable-catalyst-library'),
                'search_items' => __('Search planned content', 'sustainable-catalyst-library'),
                'not_found' => __('No planned content found.', 'sustainable-catalyst-library'),
                'menu_name' => __('Content Planner', 'sustainable-catalyst-library'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'show_ui' => true,
            'show_in_menu' => 'sc-library',
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => ['slug' => 'knowledge-roadmap', 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'revisions'],
            'taxonomies' => [
                'category',
                'post_tag',
                SC_Library_Taxonomies::SERIES,
                SC_Library_Taxonomies::CONCEPT,
                SC_Library_Taxonomies::COLLECTION,
                SC_Library_Taxonomies::DOCUMENT_CATEGORY,
            ],
            'menu_icon' => 'dashicons-calendar-alt',
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
        if ((int) get_option('sc_library_flush_rewrite', 0) === 1) {
            flush_rewrite_rules(false);
            delete_option('sc_library_flush_rewrite');
        }
    }

    public function add_meta_boxes(): void {
        add_meta_box(
            'sc-library-content-plan',
            __('Content Plan and Roadmap Record', 'sustainable-catalyst-library'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sc_library_save_plan', 'sc_library_plan_nonce');
        $plan = $this->plan_payload($post->ID, true);
        $release = $plan['expected_release'];
        $dependencies = implode(',', array_map('intval', $plan['dependency_ids']));
        ?>
        <div class="sc-library-admin-record sc-library-admin-plan">
            <p class="description"><?php esc_html_e('Use the editor for the public plan description. Categories, Library Series, Concepts, Collections, and Documentation Categories can be assigned through the normal taxonomy panels.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-admin-grid">
                <label><span><?php esc_html_e('Planning status', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_plan_status">
                        <?php foreach (self::statuses() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($plan['status'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Planned content type', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_plan_content_type">
                        <?php foreach (self::content_types() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($plan['content_type'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Priority', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_plan_priority">
                        <?php foreach (self::priority_levels() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($plan['priority'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Knowledge or institutional area', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_area" value="<?php echo esc_attr($plan['area']); ?>" placeholder="Natural Science, Foundations, Governance"></label>
                <label><span><?php esc_html_e('Product or system', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_product" value="<?php echo esc_attr($plan['product']); ?>" placeholder="Library, Lab, Workbench, Platform"></label>
                <label><span><?php esc_html_e('Responsible person or area', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_responsible" value="<?php echo esc_attr($plan['responsible']); ?>" placeholder="Institution, editorial, product area"></label>
                <label><span><?php esc_html_e('Article-map / source post ID', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" name="sc_plan_article_map_id" value="<?php echo esc_attr((string) $plan['article_map_id']); ?>"></label>
                <label><span><?php esc_html_e('Article-map sequence', 'sustainable-catalyst-library'); ?></span><input type="number" step="0.001" min="0" name="sc_plan_series_order" value="<?php echo esc_attr((string) $plan['series_order']); ?>"></label>
                <label><span><?php esc_html_e('Target WordPress post type', 'sustainable-catalyst-library'); ?></span>
                    <select name="sc_plan_target_post_type">
                        <?php foreach ($this->target_post_types() as $type => $label) : ?>
                            <option value="<?php echo esc_attr($type); ?>" <?php selected($plan['target_post_type'], $type); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span><?php esc_html_e('Public roadmap visibility', 'sustainable-catalyst-library'); ?></span><span><input type="checkbox" name="sc_plan_public" value="1" <?php checked($plan['public'], true); ?>> <?php esc_html_e('Show this record in public Library and registry results when published.', 'sustainable-catalyst-library'); ?></span></label>
            </div>

            <fieldset class="sc-library-admin-plan__release">
                <legend><strong><?php esc_html_e('Optional expected release', 'sustainable-catalyst-library'); ?></strong></legend>
                <div class="sc-library-admin-grid">
                    <label><span><?php esc_html_e('Release precision', 'sustainable-catalyst-library'); ?></span>
                        <select name="sc_plan_release_type">
                            <?php foreach (self::release_types() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($release['type'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span><?php esc_html_e('Exact date', 'sustainable-catalyst-library'); ?></span><input type="date" name="sc_plan_release_date" value="<?php echo esc_attr($release['date']); ?>"></label>
                    <label><span><?php esc_html_e('Month', 'sustainable-catalyst-library'); ?></span><input type="month" name="sc_plan_release_month" value="<?php echo esc_attr($release['month']); ?>"></label>
                    <label><span><?php esc_html_e('Quarter', 'sustainable-catalyst-library'); ?></span><select name="sc_plan_release_quarter">
                        <option value=""><?php esc_html_e('Select quarter', 'sustainable-catalyst-library'); ?></option>
                        <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter) : ?><option value="<?php echo esc_attr($quarter); ?>" <?php selected($release['quarter'], $quarter); ?>><?php echo esc_html($quarter); ?></option><?php endforeach; ?>
                    </select></label>
                    <label><span><?php esc_html_e('Year', 'sustainable-catalyst-library'); ?></span><input type="number" min="2020" max="2200" name="sc_plan_release_year" value="<?php echo esc_attr((string) $release['year']); ?>"></label>
                    <label><span><?php esc_html_e('Product release', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_product_release" value="<?php echo esc_attr($release['product_release']); ?>" placeholder="Library v1.12.0"></label>
                    <label><span><?php esc_html_e('Release note', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_release_note" value="<?php echo esc_attr($release['note']); ?>" placeholder="Timing may depend on source review"></label>
                </div>
            </fieldset>

            <div class="sc-library-admin-grid sc-library-admin-grid--wide">
                <label><span><?php esc_html_e('Intended audience', 'sustainable-catalyst-library'); ?></span><textarea rows="2" name="sc_plan_audience"><?php echo esc_textarea($plan['audience']); ?></textarea></label>
                <label><span><?php esc_html_e('Research questions', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_plan_research_questions"><?php echo esc_textarea($plan['research_questions']); ?></textarea></label>
                <label><span><?php esc_html_e('Expected artifacts', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_plan_expected_artifacts"><?php echo esc_textarea($plan['expected_artifacts']); ?></textarea><small><?php esc_html_e('Code, dataset, calculator, PDF, video, documentation, or other companion output.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Planned sources', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_plan_sources"><?php echo esc_textarea($plan['sources']); ?></textarea></label>
                <label><span><?php esc_html_e('Dependency record IDs', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_dependency_ids" value="<?php echo esc_attr($dependencies); ?>" placeholder="102, 305, 818"></label>
                <label><span><?php esc_html_e('Internal planning notes', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_plan_internal_notes"><?php echo esc_textarea($plan['internal_notes']); ?></textarea><small><?php esc_html_e('Never shown in the public registry.', 'sustainable-catalyst-library'); ?></small></label>
            </div>
            <?php if ($plan['linked_draft_id']) : ?>
                <p><strong><?php esc_html_e('Linked draft:', 'sustainable-catalyst-library'); ?></strong> <a href="<?php echo esc_url(get_edit_post_link($plan['linked_draft_id'])); ?>"><?php echo esc_html(get_the_title($plan['linked_draft_id'])); ?></a></p>
            <?php endif; ?>
            <?php if ($plan['published_post_id']) : ?>
                <p><strong><?php esc_html_e('Published record:', 'sustainable-catalyst-library'); ?></strong> <a href="<?php echo esc_url(get_permalink($plan['published_post_id'])); ?>"><?php echo esc_html(get_the_title($plan['published_post_id'])); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_plan(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!isset($_POST['sc_library_plan_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_plan_nonce'])), 'sc_library_save_plan')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = sanitize_key((string) ($_POST['sc_plan_status'] ?? 'planned'));
        $content_type = sanitize_key((string) ($_POST['sc_plan_content_type'] ?? 'article'));
        $priority = sanitize_key((string) ($_POST['sc_plan_priority'] ?? 'normal'));
        $release_type = sanitize_key((string) ($_POST['sc_plan_release_type'] ?? 'none'));
        if (!isset(self::statuses()[$status])) $status = 'planned';
        if (!isset(self::content_types()[$content_type])) $content_type = 'article';
        if (!isset(self::priority_levels()[$priority])) $priority = 'normal';
        if (!isset(self::release_types()[$release_type])) $release_type = 'none';

        $target_type = sanitize_key((string) ($_POST['sc_plan_target_post_type'] ?? 'post'));
        if (!isset($this->target_post_types()[$target_type])) $target_type = 'post';

        $quarter = strtoupper(sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_release_quarter'] ?? ''))));
        if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true)) $quarter = '';
        $release_year = absint($_POST['sc_plan_release_year'] ?? 0);
        if ($release_year && ($release_year < 2020 || $release_year > 2200)) $release_year = 0;

        $values = [
            '_sc_plan_status' => $status,
            '_sc_plan_content_type' => $content_type,
            '_sc_plan_priority' => $priority,
            '_sc_plan_area' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_area'] ?? ''))),
            '_sc_plan_product' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_product'] ?? ''))),
            '_sc_plan_responsible' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_responsible'] ?? ''))),
            '_sc_plan_article_map_id' => absint($_POST['sc_plan_article_map_id'] ?? 0),
            '_sc_plan_series_order' => (float) ($_POST['sc_plan_series_order'] ?? 0),
            '_sc_plan_target_post_type' => $target_type,
            '_sc_plan_public' => isset($_POST['sc_plan_public']) ? 1 : 0,
            '_sc_plan_release_type' => $release_type,
            '_sc_plan_release_date' => $this->sanitize_date((string) ($_POST['sc_plan_release_date'] ?? '')),
            '_sc_plan_release_month' => $this->sanitize_month((string) ($_POST['sc_plan_release_month'] ?? '')),
            '_sc_plan_release_quarter' => $quarter,
            '_sc_plan_release_year' => $release_year,
            '_sc_plan_product_release' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_product_release'] ?? ''))),
            '_sc_plan_release_note' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_release_note'] ?? ''))),
            '_sc_plan_audience' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_audience'] ?? ''))),
            '_sc_plan_research_questions' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_research_questions'] ?? ''))),
            '_sc_plan_expected_artifacts' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_expected_artifacts'] ?? ''))),
            '_sc_plan_sources' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_sources'] ?? ''))),
            '_sc_plan_dependency_ids' => implode(',', $this->sanitize_id_list((string) ($_POST['sc_plan_dependency_ids'] ?? ''))),
            '_sc_plan_internal_notes' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_internal_notes'] ?? ''))),
        ];

        foreach ($values as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    private function sanitize_date(string $value): string {
        $value = sanitize_text_field(wp_unslash($value));
        if ($value === '') return '';
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function sanitize_month(string $value): string {
        $value = sanitize_text_field(wp_unslash($value));
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : '';
    }

    private function sanitize_id_list(string $value): array {
        $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', wp_unslash($value)) ?: []));
        return array_values(array_unique($ids));
    }

    public function target_post_types(): array {
        $types = get_post_types(['public' => true], 'objects');
        $items = [];
        foreach ($types as $type => $object) {
            if ($type === self::POST_TYPE || $type === 'attachment') continue;
            $items[$type] = $object->labels->singular_name;
        }
        return $items ?: ['post' => __('Post', 'sustainable-catalyst-library')];
    }

    public static function is_public_plan(int $post_id): bool {
        if (get_post_type($post_id) !== self::POST_TYPE || get_post_status($post_id) !== 'publish') return false;
        if (!(bool) get_post_meta($post_id, '_sc_plan_public', true)) return false;
        $status = (string) get_post_meta($post_id, '_sc_plan_status', true);
        return in_array($status ?: 'planned', self::public_statuses(), true);
    }

    public static function should_index(int $post_id): bool {
        if (get_post_type($post_id) !== self::POST_TYPE) return true;
        if (!self::is_public_plan($post_id)) return false;
        $status = (string) get_post_meta($post_id, '_sc_plan_status', true);
        $published_id = absint(get_post_meta($post_id, '_sc_plan_published_post_id', true));
        return !($status === 'published' && $published_id && get_post_status($published_id) === 'publish');
    }

    public function expected_release(int $post_id): array {
        $type = (string) get_post_meta($post_id, '_sc_plan_release_type', true) ?: 'none';
        if (!isset(self::release_types()[$type])) $type = 'none';
        $date = (string) get_post_meta($post_id, '_sc_plan_release_date', true);
        $month = (string) get_post_meta($post_id, '_sc_plan_release_month', true);
        $quarter = (string) get_post_meta($post_id, '_sc_plan_release_quarter', true);
        $year = absint(get_post_meta($post_id, '_sc_plan_release_year', true));
        $product = (string) get_post_meta($post_id, '_sc_plan_product_release', true);
        $note = (string) get_post_meta($post_id, '_sc_plan_release_note', true);
        $display = '';
        $sort = '';

        if ($type === 'exact' && $date) {
            $timestamp = strtotime($date . ' 12:00:00');
            $display = $timestamp ? sprintf(__('Expected %s', 'sustainable-catalyst-library'), wp_date(get_option('date_format'), $timestamp)) : '';
            $sort = $date;
        } elseif ($type === 'month' && $month) {
            $timestamp = strtotime($month . '-01 12:00:00');
            $display = $timestamp ? sprintf(__('Expected %s', 'sustainable-catalyst-library'), wp_date('F Y', $timestamp)) : '';
            $sort = $month . '-01';
        } elseif ($type === 'quarter' && $quarter && $year) {
            $display = sprintf(__('Expected %1$s %2$d', 'sustainable-catalyst-library'), $quarter, $year);
            $month_number = ['Q1' => '01', 'Q2' => '04', 'Q3' => '07', 'Q4' => '10'][$quarter] ?? '01';
            $sort = sprintf('%04d-%s-01', $year, $month_number);
        } elseif ($type === 'year' && $year) {
            $display = sprintf(__('Expected %d', 'sustainable-catalyst-library'), $year);
            $sort = sprintf('%04d-01-01', $year);
        } elseif ($type === 'product' && $product !== '') {
            $display = sprintf(__('Targeted for %s', 'sustainable-catalyst-library'), $product);
        }

        return [
            'type' => $type, 'date' => $date, 'month' => $month, 'quarter' => $quarter, 'year' => $year,
            'product_release' => $product, 'note' => $note, 'display' => $display, 'sort' => $sort,
        ];
    }

    public function plan_payload(int $post_id, bool $admin = false): array {
        $post = get_post($post_id);
        $status = (string) get_post_meta($post_id, '_sc_plan_status', true) ?: 'planned';
        $content_type = (string) get_post_meta($post_id, '_sc_plan_content_type', true) ?: 'article';
        $area = (string) get_post_meta($post_id, '_sc_plan_area', true);
        if ($area === '') {
            $categories = get_the_category($post_id);
            $area = $categories ? $categories[0]->name : '';
        }
        $map_id = absint(get_post_meta($post_id, '_sc_plan_article_map_id', true));
        $release = $this->expected_release($post_id);
        $linked_draft_id = absint(get_post_meta($post_id, '_sc_plan_linked_draft_id', true));
        $published_post_id = absint(get_post_meta($post_id, '_sc_plan_published_post_id', true));
        $payload = [
            'id' => $post_id,
            'record_identifier' => sprintf('sc:plan:%d', $post_id),
            'record_state' => $status,
            'record_state_label' => self::statuses()[$status] ?? ucfirst($status),
            'status' => $status,
            'title' => $post ? get_the_title($post_id) : '',
            'excerpt' => $post ? (has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags($post->post_content), 32, '…')) : '',
            'url' => $post ? get_permalink($post_id) : '',
            'post_type' => self::POST_TYPE,
            'type_label' => __('Planned Content', 'sustainable-catalyst-library'),
            'content_type' => $content_type,
            'content_type_label' => self::content_types()[$content_type] ?? ucfirst(str_replace('_', ' ', $content_type)),
            'priority' => (string) get_post_meta($post_id, '_sc_plan_priority', true) ?: 'normal',
            'area' => $area,
            'product' => (string) get_post_meta($post_id, '_sc_plan_product', true),
            'responsible' => (string) get_post_meta($post_id, '_sc_plan_responsible', true),
            'public' => metadata_exists('post', $post_id, '_sc_plan_public') ? (bool) get_post_meta($post_id, '_sc_plan_public', true) : true,
            'article_map_id' => $map_id,
            'article_map_title' => $map_id ? get_the_title($map_id) : '',
            'article_map_url' => $map_id ? get_permalink($map_id) : '',
            'series_order' => (float) get_post_meta($post_id, '_sc_plan_series_order', true),
            'target_post_type' => (string) get_post_meta($post_id, '_sc_plan_target_post_type', true) ?: 'post',
            'expected_release' => $release,
            'audience' => (string) get_post_meta($post_id, '_sc_plan_audience', true),
            'research_questions' => (string) get_post_meta($post_id, '_sc_plan_research_questions', true),
            'expected_artifacts' => (string) get_post_meta($post_id, '_sc_plan_expected_artifacts', true),
            'sources' => (string) get_post_meta($post_id, '_sc_plan_sources', true),
            'dependency_ids' => $this->sanitize_id_list((string) get_post_meta($post_id, '_sc_plan_dependency_ids', true)),
            'linked_draft_id' => $linked_draft_id,
            'linked_draft_url' => $linked_draft_id ? get_edit_post_link($linked_draft_id, 'raw') : '',
            'published_post_id' => $published_post_id,
            'published_url' => $published_post_id ? get_permalink($published_post_id) : '',
            'published_at' => $post ? mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date) : '',
            'modified_at' => $post ? mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified) : '',
            'notice' => __('Planned content, scope, sequence, and expected timing may change as research and development continue.', 'sustainable-catalyst-library'),
        ];
        if ($admin) {
            $payload['internal_notes'] = (string) get_post_meta($post_id, '_sc_plan_internal_notes', true);
        }
        return apply_filters('sc_library_plan_payload', $payload, $post_id, $admin);
    }

    public function sync_published_plan(string $new_status, string $old_status, WP_Post $post): void {
        if ($new_status !== 'publish' || $post->post_type === self::POST_TYPE) return;
        $plan_id = absint(get_post_meta($post->ID, '_sc_library_plan_id', true));
        if (!$plan_id || get_post_type($plan_id) !== self::POST_TYPE) return;
        update_post_meta($plan_id, '_sc_plan_status', 'published');
        update_post_meta($plan_id, '_sc_plan_published_post_id', $post->ID);
        update_post_meta($plan_id, '_sc_plan_actual_publication_date', current_time('Y-m-d'));
        $this->indexer->delete_record($plan_id);
        $this->indexer->reindex_post($post->ID);
    }

    public function handle_convert_plan(): void {
        $plan_id = absint($_GET['plan_id'] ?? 0);
        check_admin_referer('sc_library_convert_plan_' . $plan_id);
        if (!$plan_id || !current_user_can('edit_post', $plan_id)) wp_die(__('You cannot convert this plan.', 'sustainable-catalyst-library'));
        $plan = $this->plan_payload($plan_id, true);
        $target_type = $plan['target_post_type'];
        if (!isset($this->target_post_types()[$target_type])) $target_type = 'post';
        $existing = absint($plan['linked_draft_id']);
        if ($existing && get_post($existing)) {
            wp_safe_redirect(get_edit_post_link($existing, 'raw'));
            exit;
        }
        $source_plan = get_post($plan_id);
        $draft_id = wp_insert_post([
            'post_type' => $target_type,
            'post_status' => 'draft',
            'post_title' => $plan['title'],
            'post_excerpt' => $plan['excerpt'],
            'post_content' => $source_plan ? (string) $source_plan->post_content : '',
        ], true);
        if (is_wp_error($draft_id)) wp_die(esc_html($draft_id->get_error_message()));

        foreach (['category', 'post_tag', SC_Library_Taxonomies::SERIES, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY] as $taxonomy) {
            if (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy($target_type, $taxonomy)) continue;
            $term_ids = wp_get_object_terms($plan_id, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($term_ids) && $term_ids) wp_set_object_terms($draft_id, array_map('intval', $term_ids), $taxonomy);
        }
        update_post_meta($draft_id, '_sc_library_plan_id', $plan_id);
        update_post_meta($draft_id, '_sc_library_article_map_id', $plan['article_map_id']);
        update_post_meta($draft_id, '_sc_library_series_order', $plan['series_order']);
        update_post_meta($draft_id, '_sc_library_plan_research_questions', $plan['research_questions']);
        update_post_meta($draft_id, '_sc_library_plan_expected_artifacts', $plan['expected_artifacts']);
        update_post_meta($draft_id, '_sc_library_plan_sources', $plan['sources']);
        update_post_meta($plan_id, '_sc_plan_linked_draft_id', $draft_id);
        update_post_meta($plan_id, '_sc_plan_status', 'drafting');
        $this->indexer->reindex_post($plan_id);
        wp_safe_redirect(get_edit_post_link($draft_id, 'raw'));
        exit;
    }

    public function row_actions(array $actions, WP_Post $post): array {
        if ($post->post_type !== self::POST_TYPE || !current_user_can('edit_post', $post->ID)) return $actions;
        $plan = $this->plan_payload($post->ID, true);
        if ($plan['status'] !== 'published') {
            $url = wp_nonce_url(admin_url('admin-post.php?action=sc_library_convert_plan&plan_id=' . $post->ID), 'sc_library_convert_plan_' . $post->ID);
            $actions['sc_library_convert'] = '<a href="' . esc_url($url) . '">' . esc_html($plan['linked_draft_id'] ? __('Open linked draft', 'sustainable-catalyst-library') : __('Create WordPress draft', 'sustainable-catalyst-library')) . '</a>';
        }
        return $actions;
    }

    public function columns(array $columns): array {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox">',
            'title' => __('Planned record', 'sustainable-catalyst-library'),
            'sc_plan_status' => __('Status', 'sustainable-catalyst-library'),
            'sc_plan_area' => __('Area / Product', 'sustainable-catalyst-library'),
            'sc_plan_release' => __('Expected release', 'sustainable-catalyst-library'),
            'sc_plan_map' => __('Article map', 'sustainable-catalyst-library'),
            'date' => $columns['date'] ?? __('Date', 'sustainable-catalyst-library'),
        ];
    }

    public function column_content(string $column, int $post_id): void {
        $plan = $this->plan_payload($post_id, true);
        if ($column === 'sc_plan_status') {
            echo '<strong>' . esc_html($plan['record_state_label']) . '</strong><br><small>' . esc_html($plan['content_type_label']) . '</small>';
        } elseif ($column === 'sc_plan_area') {
            echo esc_html($plan['area'] ?: '—');
            if ($plan['product']) echo '<br><small>' . esc_html($plan['product']) . '</small>';
        } elseif ($column === 'sc_plan_release') {
            echo esc_html($plan['expected_release']['display'] ?: __('Not set', 'sustainable-catalyst-library'));
        } elseif ($column === 'sc_plan_map') {
            echo $plan['article_map_id'] ? '<a href="' . esc_url(get_edit_post_link($plan['article_map_id'])) . '">' . esc_html($plan['article_map_title']) . '</a>' : '—';
        }
    }

    public function admin_assets(string $hook): void {
        $screen = get_current_screen();
        if (!$screen) return;
        $is_planner_screen = $screen->post_type === self::POST_TYPE || in_array((string) ($_GET['page'] ?? ''), ['sc-library-roadmap', 'sc-library-article-map-planner'], true);
        if (!$is_planner_screen) return;
        wp_enqueue_style('sc-library-admin', SC_LIBRARY_URL . 'assets/css/sc-library-admin.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-planner', SC_LIBRARY_URL . 'assets/css/sc-library-planner.css', ['sc-library-admin'], SC_LIBRARY_VERSION);
    }

    public function admin_menu(): void {
        add_submenu_page('sc-library', __('Roadmap Tracker', 'sustainable-catalyst-library'), __('Roadmap Tracker', 'sustainable-catalyst-library'), 'edit_posts', 'sc-library-roadmap', [$this, 'render_admin_tracker']);
        add_submenu_page('sc-library', __('Article Map Planner', 'sustainable-catalyst-library'), __('Article Map Planner', 'sustainable-catalyst-library'), 'edit_posts', 'sc-library-article-map-planner', [$this, 'render_article_map_planner']);
    }

    public function render_admin_tracker(): void {
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission to view the roadmap tracker.', 'sustainable-catalyst-library'));
        $tracker = $this->tracker_data(false);
        $export_json = wp_nonce_url(admin_url('admin-post.php?action=sc_library_export_registry&format=json'), 'sc_library_export_registry');
        $export_csv = wp_nonce_url(admin_url('admin-post.php?action=sc_library_export_registry&format=csv'), 'sc_library_export_registry');
        ?>
        <div class="wrap sc-library-planner-admin">
            <h1><?php esc_html_e('Content Planner and Roadmap Tracker', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Track published knowledge, living documentation, active development, planned work, and historical records across the complete Sustainable Catalyst registry.', 'sustainable-catalyst-library'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE)); ?>"><?php esc_html_e('Add planned content', 'sustainable-catalyst-library'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-library-article-map-planner')); ?>"><?php esc_html_e('Open Article Map Planner', 'sustainable-catalyst-library'); ?></a> <a class="button" href="<?php echo esc_url($export_csv); ?>"><?php esc_html_e('Export registry CSV', 'sustainable-catalyst-library'); ?></a> <a class="button" href="<?php echo esc_url($export_json); ?>"><?php esc_html_e('Export registry JSON', 'sustainable-catalyst-library'); ?></a></p>
            <div class="sc-library-planner-metrics">
                <?php foreach ($tracker['summary'] as $key => $value) : ?>
                    <div class="card"><span><?php echo esc_html($this->summary_label($key)); ?></span><strong><?php echo esc_html((string) $value); ?></strong></div>
                <?php endforeach; ?>
            </div>
            <?php $this->render_admin_breakdown(__('By knowledge or institutional area', 'sustainable-catalyst-library'), $tracker['by_area']); ?>
            <?php $this->render_admin_breakdown(__('By product or system', 'sustainable-catalyst-library'), $tracker['by_product']); ?>
            <?php $this->render_admin_breakdown(__('By planning and registry state', 'sustainable-catalyst-library'), $tracker['by_status']); ?>
            <?php $this->render_admin_breakdown(__('By content type', 'sustainable-catalyst-library'), $tracker['by_type']); ?>
            <h2><?php esc_html_e('Article-map progress', 'sustainable-catalyst-library'); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Article map', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Published', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('In development', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Planned', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Total registered', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                <?php if (!$tracker['article_maps']) : ?><tr><td colspan="5"><?php esc_html_e('No article-map planning links have been registered yet.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
                <?php foreach ($tracker['article_maps'] as $map) : ?><tr><td><a href="<?php echo esc_url(get_edit_post_link($map['id'])); ?>"><?php echo esc_html($map['title']); ?></a></td><td><?php echo esc_html((string) $map['published']); ?></td><td><?php echo esc_html((string) $map['in_development']); ?></td><td><?php echo esc_html((string) $map['planned']); ?></td><td><?php echo esc_html((string) $map['total']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <h2><?php esc_html_e('Planning warnings', 'sustainable-catalyst-library'); ?></h2>
            <ul class="ul-disc">
                <?php if (!$tracker['warnings']) : ?><li><?php esc_html_e('No major planning warnings were detected.', 'sustainable-catalyst-library'); ?></li><?php endif; ?>
                <?php foreach ($tracker['warnings'] as $warning) : ?><li><a href="<?php echo esc_url($warning['url']); ?>"><?php echo esc_html($warning['title']); ?></a> — <?php echo esc_html($warning['message']); ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function render_admin_breakdown(string $title, array $rows): void {
        ?>
        <h2><?php echo esc_html($title); ?></h2>
        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Area', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Published/current', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('In development', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Planned', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Archived/historical', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Total', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
        <?php if (!$rows) : ?><tr><td colspan="6"><?php esc_html_e('No records found.', 'sustainable-catalyst-library'); ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $row) : ?><tr><td><?php echo esc_html($row['label']); ?></td><td><?php echo esc_html((string) $row['published']); ?></td><td><?php echo esc_html((string) $row['in_development']); ?></td><td><?php echo esc_html((string) $row['planned']); ?></td><td><?php echo esc_html((string) $row['archived']); ?></td><td><strong><?php echo esc_html((string) $row['total']); ?></strong></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php
    }

    public function render_article_map_planner(): void {
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission to use the Article Map Planner.', 'sustainable-catalyst-library'));
        $scan = get_transient('sc_library_map_scan_' . get_current_user_id());
        $candidate_posts = get_posts([
            'post_type' => $this->indexer->configured_post_types(false),
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 250,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap sc-library-planner-admin">
            <h1><?php esc_html_e('Article Map Planner', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Scan an existing article map, compare its headings and links with published posts, drafts, and planned records, then register missing entries in bulk.', 'sustainable-catalyst-library'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sc_library_scan_article_map">
                <?php wp_nonce_field('sc_library_scan_article_map'); ?>
                <label for="sc-library-map-id"><strong><?php esc_html_e('Article map or series page', 'sustainable-catalyst-library'); ?></strong></label>
                <select id="sc-library-map-id" name="map_id" required><option value=""><?php esc_html_e('Select a page or post', 'sustainable-catalyst-library'); ?></option><?php foreach ($candidate_posts as $candidate) : ?><option value="<?php echo esc_attr((string) $candidate->ID); ?>" <?php selected((int) ($scan['map_id'] ?? 0), $candidate->ID); ?>><?php echo esc_html($candidate->post_title . ' · ' . $candidate->post_type); ?></option><?php endforeach; ?></select>
                <?php submit_button(__('Scan article map', 'sustainable-catalyst-library'), 'primary', 'submit', false); ?>
            </form>
            <?php if (is_array($scan) && !empty($scan['entries'])) : ?>
                <h2><?php echo esc_html(sprintf(__('Scan results: %s', 'sustainable-catalyst-library'), get_the_title((int) $scan['map_id']))); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sc_library_create_map_plans">
                    <input type="hidden" name="map_id" value="<?php echo esc_attr((string) $scan['map_id']); ?>">
                    <?php wp_nonce_field('sc_library_create_map_plans'); ?>
                    <table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" data-sc-check-all></td><th><?php esc_html_e('Order', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Map entry', 'sustainable-catalyst-library'); ?></th><th><?php esc_html_e('Detected state', 'sustainable-catalyst-library'); ?></th></tr></thead><tbody>
                    <?php foreach ($scan['entries'] as $index => $entry) : ?>
                        <tr><th class="check-column"><?php if ($entry['state'] === 'missing') : ?><input type="checkbox" name="selected[]" value="<?php echo esc_attr((string) $index); ?>"><?php endif; ?></th><td><?php echo esc_html((string) $entry['order']); ?></td><td><strong><?php echo esc_html($entry['title']); ?></strong><?php if ($entry['url']) : ?><br><small><?php echo esc_html($entry['url']); ?></small><?php endif; ?><input type="hidden" name="entries[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr($entry['title']); ?>"><input type="hidden" name="entries[<?php echo esc_attr((string) $index); ?>][order]" value="<?php echo esc_attr((string) $entry['order']); ?>"></td><td><?php echo esc_html($entry['state_label']); ?><?php if ($entry['matched_id']) : ?> · <a href="<?php echo esc_url(get_edit_post_link($entry['matched_id'])); ?>"><?php esc_html_e('Open record', 'sustainable-catalyst-library'); ?></a><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <p><label><?php esc_html_e('Area inherited by new plans:', 'sustainable-catalyst-library'); ?> <input type="text" name="area" value=""></label> <label><?php esc_html_e('Product:', 'sustainable-catalyst-library'); ?> <input type="text" name="product" value=""></label> <label><input type="checkbox" name="public" value="1" checked> <?php esc_html_e('Public roadmap records', 'sustainable-catalyst-library'); ?></label></p>
                    <?php submit_button(__('Create selected as planned content', 'sustainable-catalyst-library')); ?>
                </form>
            <?php endif; ?>
        </div>
        <script>document.querySelector('[data-sc-check-all]')?.addEventListener('change',e=>document.querySelectorAll('input[name="selected[]"]').forEach(c=>c.checked=e.target.checked));</script>
        <?php
    }

    public function handle_scan_article_map(): void {
        check_admin_referer('sc_library_scan_article_map');
        if (!current_user_can('edit_posts')) wp_die(__('You cannot scan article maps.', 'sustainable-catalyst-library'));
        $map_id = absint($_POST['map_id'] ?? 0);
        $post = get_post($map_id);
        if (!$post) wp_die(__('Article map not found.', 'sustainable-catalyst-library'));
        $entries = $this->scan_article_map($post);
        foreach ($entries as $entry) {
            $matched_id = absint($entry['matched_id'] ?? 0);
            if (!$matched_id || !current_user_can('edit_post', $matched_id)) continue;
            if (get_post_type($matched_id) === self::POST_TYPE) {
                update_post_meta($matched_id, '_sc_plan_article_map_id', $map_id);
                update_post_meta($matched_id, '_sc_plan_series_order', (float) $entry['order']);
                $this->indexer->reindex_post($matched_id);
            } else {
                update_post_meta($matched_id, '_sc_library_article_map_id', $map_id);
                update_post_meta($matched_id, '_sc_library_series_order', (float) $entry['order']);
                if (get_post_status($matched_id) === 'publish') $this->indexer->reindex_post($matched_id);
            }
        }
        set_transient('sc_library_map_scan_' . get_current_user_id(), ['map_id' => $map_id, 'entries' => $entries], HOUR_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=sc-library-article-map-planner'));
        exit;
    }

    public function scan_article_map(WP_Post $post): array {
        $html = do_blocks((string) $post->post_content);
        if (stripos($html, '<') === false) $html = wpautop($html);
        $titles = [];
        if (preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, $matches)) {
            foreach ($matches[1] as $heading) {
                $title = trim(wp_strip_all_tags($heading));
                if ($this->valid_map_title($title)) $titles[] = ['title' => $title, 'url' => ''];
            }
        }
        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $title = trim(wp_strip_all_tags($match[2]));
                if ($this->valid_map_title($title)) $titles[] = ['title' => $title, 'url' => esc_url_raw($match[1])];
            }
        }
        $seen = [];
        $entries = [];
        foreach ($titles as $candidate) {
            $normalized = $this->normalize_title($candidate['title']);
            if ($normalized === '' || isset($seen[$normalized])) continue;
            $seen[$normalized] = true;
            $match = $this->match_existing_title($normalized);
            $entries[] = [
                'order' => count($entries) + 1,
                'title' => $candidate['title'],
                'url' => $candidate['url'],
                'state' => $match['state'],
                'state_label' => $match['label'],
                'matched_id' => $match['id'],
            ];
        }
        return $entries;
    }

    private function valid_map_title(string $title): bool {
        $title = trim($title);
        if (mb_strlen($title) < 4 || mb_strlen($title) > 220) return false;
        $excluded = ['table of contents', 'back to top', 'references', 'conclusion', 'introduction'];
        return !in_array(strtolower($title), $excluded, true);
    }

    private function normalize_title(string $title): string {
        return strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', html_entity_decode($title, ENT_QUOTES | ENT_HTML5)) ?: ''));
    }

    private function match_existing_title(string $normalized): array {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT ID, post_title, post_status, post_type FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit')", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            if ($this->normalize_title((string) $row['post_title']) !== $normalized) continue;
            $state = $row['post_type'] === self::POST_TYPE ? 'planned' : ($row['post_status'] === 'publish' ? 'published' : 'draft');
            $label = $state === 'planned' ? __('Planned record exists', 'sustainable-catalyst-library') : ($state === 'published' ? __('Published record exists', 'sustainable-catalyst-library') : __('Draft exists', 'sustainable-catalyst-library'));
            return ['state' => $state, 'label' => $label, 'id' => (int) $row['ID']];
        }
        return ['state' => 'missing', 'label' => __('Not registered', 'sustainable-catalyst-library'), 'id' => 0];
    }

    public function handle_create_map_plans(): void {
        check_admin_referer('sc_library_create_map_plans');
        if (!current_user_can('edit_posts')) wp_die(__('You cannot create planned records.', 'sustainable-catalyst-library'));
        $map_id = absint($_POST['map_id'] ?? 0);
        $entries = is_array($_POST['entries'] ?? null) ? wp_unslash($_POST['entries']) : [];
        $selected = array_map('absint', (array) ($_POST['selected'] ?? []));
        $area = sanitize_text_field(wp_unslash((string) ($_POST['area'] ?? '')));
        $product = sanitize_text_field(wp_unslash((string) ($_POST['product'] ?? '')));
        $public = isset($_POST['public']) ? 1 : 0;
        foreach ($selected as $index) {
            if (!isset($entries[$index])) continue;
            $title = sanitize_text_field((string) ($entries[$index]['title'] ?? ''));
            $order = (float) ($entries[$index]['order'] ?? 0);
            if ($title === '' || $this->match_existing_title($this->normalize_title($title))['state'] !== 'missing') continue;
            $plan_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => $public ? 'publish' : 'draft',
                'post_title' => $title,
                'post_content' => '',
                'post_excerpt' => '',
            ]);
            if (!$plan_id || is_wp_error($plan_id)) continue;
            update_post_meta($plan_id, '_sc_plan_status', 'planned');
            update_post_meta($plan_id, '_sc_plan_content_type', 'article');
            update_post_meta($plan_id, '_sc_plan_priority', 'normal');
            update_post_meta($plan_id, '_sc_plan_area', $area);
            update_post_meta($plan_id, '_sc_plan_product', $product);
            update_post_meta($plan_id, '_sc_plan_article_map_id', $map_id);
            update_post_meta($plan_id, '_sc_plan_series_order', $order);
            update_post_meta($plan_id, '_sc_plan_target_post_type', 'post');
            update_post_meta($plan_id, '_sc_plan_public', $public);
            $this->inherit_map_terms($map_id, $plan_id);
            $this->indexer->reindex_post($plan_id);
        }
        delete_transient('sc_library_map_scan_' . get_current_user_id());
        wp_safe_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE));
        exit;
    }

    private function inherit_map_terms(int $map_id, int $plan_id): void {
        foreach (['category', 'post_tag', SC_Library_Taxonomies::SERIES, SC_Library_Taxonomies::CONCEPT, SC_Library_Taxonomies::COLLECTION, SC_Library_Taxonomies::DOCUMENT_CATEGORY] as $taxonomy) {
            if (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy(self::POST_TYPE, $taxonomy)) continue;
            $ids = wp_get_object_terms($map_id, $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($ids) && $ids) wp_set_object_terms($plan_id, array_map('intval', $ids), $taxonomy);
        }
    }

    public function register_rest_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/registry', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_registry'],
            'permission_callback' => '__return_true',
            'args' => $this->registry_args(),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/registry/facets', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_facets'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/roadmap/tracker', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_tracker'],
            'permission_callback' => '__return_true',
            'args' => ['collection' => ['sanitize_callback' => 'sanitize_title', 'default' => '']],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/planner/statuses', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_statuses'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/plans/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_plan'],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['sanitize_callback' => 'absint']],
        ]);
    }

    private function registry_args(): array {
        return [
            'search' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'state' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'area' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'product' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            'collection' => ['sanitize_callback' => 'sanitize_title', 'default' => ''],
            'map' => ['sanitize_callback' => 'absint', 'default' => 0],
            'expected' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            'include_archived' => ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true],
            'sort' => ['sanitize_callback' => 'sanitize_key', 'default' => 'updated'],
            'page' => ['sanitize_callback' => 'absint', 'default' => 1],
            'per_page' => ['sanitize_callback' => 'absint', 'default' => 20],
        ];
    }

    public function rest_registry(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response($this->registry_query($request));
    }

    public function rest_facets(WP_REST_Request $request): WP_REST_Response {
        $records = $this->all_public_registry_records();
        return rest_ensure_response(['facets' => $this->registry_facets($records), 'summary' => $this->summary($records)]);
    }

    public function rest_tracker(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response($this->tracker_data(true, sanitize_title((string) $request['collection'])));
    }

    public function rest_statuses(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'statuses' => $this->label_options(self::statuses()),
            'content_types' => $this->label_options(self::content_types()),
            'release_types' => $this->label_options(self::release_types()),
            'schema' => 'sc-library-registry/1.0',
        ]);
    }

    public function rest_plan(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = absint($request['id']);
        if (!self::is_public_plan($id)) return new WP_Error('sc_plan_not_found', __('Public plan not found.', 'sustainable-catalyst-library'), ['status' => 404]);
        return rest_ensure_response($this->plan_payload($id));
    }

    private function label_options(array $values): array {
        $items = [];
        foreach ($values as $value => $label) $items[] = ['value' => $value, 'label' => $label];
        return $items;
    }

    public function registry_query(WP_REST_Request $request): array {
        $records = $this->all_public_registry_records();
        $search = strtolower(trim((string) $request['search']));
        $state = sanitize_key((string) $request['state']);
        $type = sanitize_key((string) $request['type']);
        $area = trim((string) $request['area']);
        $product = trim((string) $request['product']);
        $collection = sanitize_title((string) $request['collection']);
        $map_id = absint($request['map']);
        $expected = sanitize_key((string) $request['expected']);
        $include_archived = rest_sanitize_boolean($request['include_archived']);
        $sort = sanitize_key((string) $request['sort']);

        $records = array_values(array_filter($records, function (array $record) use ($search, $state, $type, $area, $product, $collection, $map_id, $expected, $include_archived): bool {
            if (!$include_archived && in_array($record['record_state'], ['archived', 'superseded', 'cancelled'], true)) return false;
            if ($state && $record['record_state'] !== $state) return false;
            if ($type && $record['content_type'] !== $type) return false;
            if ($area && strcasecmp($record['area'], $area) !== 0) return false;
            if ($product && strcasecmp($record['product'], $product) !== 0) return false;
            if ($collection && !in_array($collection, $record['collections'], true)) return false;
            if ($map_id && (int) $record['article_map_id'] !== $map_id) return false;
            if ($expected === 'set' && empty($record['expected_release']['display'])) return false;
            if ($expected === 'unset' && !empty($record['expected_release']['display'])) return false;
            if ($expected === 'overdue' && !$this->is_overdue($record)) return false;
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [$record['title'], $record['excerpt'], $record['area'], $record['product'], $record['content_type_label'], implode(' ', $record['keywords'])]));
                if (!str_contains($haystack, $search)) return false;
            }
            return true;
        }));

        usort($records, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'title' => strcasecmp($a['title'], $b['title']),
                'oldest' => strcmp($a['published_at'], $b['published_at']),
                'release' => strcmp($a['expected_release']['sort'] ?: '9999-12-31', $b['expected_release']['sort'] ?: '9999-12-31'),
                'state' => strcasecmp($a['record_state_label'], $b['record_state_label']) ?: strcasecmp($a['title'], $b['title']),
                default => strcmp($b['modified_at'], $a['modified_at']),
            };
        });

        $total = count($records);
        $page = max(1, absint($request['page']));
        $per_page = min(100, max(1, absint($request['per_page'])));
        $offset = ($page - 1) * $per_page;
        return [
            'items' => array_slice($records, $offset, $per_page),
            'pagination' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $total ? (int) ceil($total / $per_page) : 0],
            'summary' => $this->summary($records),
            'facets' => $this->registry_facets($records),
            'schema' => 'sc-library-registry/1.0',
        ];
    }

    public function all_public_registry_records(): array {
        global $wpdb;
        $records = [];
        $rows = $wpdb->get_results("SELECT * FROM {$this->indexer->table_name()} WHERE status = 'publish' ORDER BY modified_at DESC", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $post_id = (int) $row['post_id'];
            if (get_post_type($post_id) === self::POST_TYPE) {
                if (!self::should_index($post_id)) continue;
                $records[] = $this->registry_plan_record($post_id, $row);
            } else {
                $records[] = $this->registry_published_record($post_id, $row);
            }
        }
        return array_values(array_filter($records));
    }

    private function registry_plan_record(int $post_id, array $row): array {
        $plan = $this->plan_payload($post_id);
        return array_merge($this->registry_base($post_id, $row), $plan, [
            'kind' => 'plan',
            'keywords' => $this->post_keywords($post_id),
            'collections' => $this->term_slugs($post_id, SC_Library_Taxonomies::COLLECTION),
            'categories' => $this->term_names($post_id, 'category'),
            'concepts' => $this->term_names($post_id, SC_Library_Taxonomies::CONCEPT),
            'authoritative' => false,
            'authority_label' => '',
            'historical' => in_array($plan['status'], ['deferred', 'cancelled', 'superseded'], true),
        ]);
    }

    private function registry_published_record(int $post_id, array $row): array {
        $doc_status = (string) get_post_meta($post_id, '_sc_library_doc_status', true);
        $doc_type = (string) get_post_meta($post_id, '_sc_library_doc_type', true);
        $doc_terms = wp_get_post_terms($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY, ['fields' => 'ids']);
        $is_document = $doc_status !== '' || (!is_wp_error($doc_terms) && !empty($doc_terms));
        $state = $is_document ? ($doc_status ?: 'current') : 'published';
        $content_type = $is_document ? ($doc_type ?: 'documentation') : $this->infer_content_type($post_id, $row);
        $area = (string) get_post_meta($post_id, '_sc_library_doc_responsible_area', true);
        if ($area === '') {
            $primary = get_term((int) ($row['primary_domain_id'] ?? 0), 'category');
            $area = $primary && !is_wp_error($primary) ? $primary->name : '';
        }
        $product = (string) get_post_meta($post_id, '_sc_library_registry_product', true);
        if ($product === '' && $is_document) $product = $area;
        $authority_type = (string) get_post_meta($post_id, '_sc_library_doc_authority_type', true);
        $authority_types = class_exists('SC_Library_Documentation') ? SC_Library_Documentation::authority_types() : [];
        $map_id = absint(get_post_meta($post_id, '_sc_library_article_map_id', true));
        $plan_id = absint(get_post_meta($post_id, '_sc_library_plan_id', true));
        if (!$map_id && $plan_id) $map_id = absint(get_post_meta($plan_id, '_sc_plan_article_map_id', true));
        return array_merge($this->registry_base($post_id, $row), [
            'kind' => $is_document ? 'document' : 'published',
            'record_state' => $state,
            'record_state_label' => $this->registry_state_label($state),
            'content_type' => $content_type,
            'content_type_label' => $this->registry_type_label($content_type),
            'area' => $area,
            'product' => $product,
            'responsible' => $is_document ? $area : '',
            'expected_release' => ['type' => 'none', 'display' => '', 'sort' => '', 'note' => ''],
            'article_map_id' => $map_id,
            'article_map_title' => $map_id ? get_the_title($map_id) : '',
            'article_map_url' => $map_id ? get_permalink($map_id) : '',
            'series_order' => (float) ($row['series_order'] ?? 0),
            'keywords' => $this->post_keywords($post_id),
            'collections' => $this->term_slugs($post_id, SC_Library_Taxonomies::COLLECTION),
            'categories' => $this->term_names($post_id, 'category'),
            'concepts' => $this->term_names($post_id, SC_Library_Taxonomies::CONCEPT),
            'authoritative' => $authority_type !== '',
            'authority_label' => $authority_types[$authority_type] ?? '',
            'historical' => in_array($state, ['archived', 'superseded', 'pdf_snapshot'], true),
            'plan_id' => $plan_id,
            'notice' => '',
        ]);
    }

    private function registry_base(int $post_id, array $row): array {
        $post = get_post($post_id);
        return [
            'id' => $post_id,
            'record_identifier' => (string) ($row['record_identifier'] ?? sprintf('sc:library:%s:%d', get_post_type($post_id), $post_id)),
            'title' => (string) $row['title'],
            'excerpt' => (string) $row['excerpt'],
            'url' => (string) $row['permalink'],
            'post_type' => (string) $row['post_type'],
            'published_at' => $post ? mysql_to_rfc3339($post->post_date_gmt ?: $post->post_date) : '',
            'modified_at' => $post ? mysql_to_rfc3339($post->post_modified_gmt ?: $post->post_modified) : '',
        ];
    }

    private function infer_content_type(int $post_id, array $row): string {
        $override = sanitize_key((string) get_post_meta($post_id, '_sc_library_registry_type', true));
        if ($override) return $override;
        $post_type = (string) $row['post_type'];
        if ($post_type === 'post') return 'article';
        if ($post_type === 'page') return 'page';
        return $post_type;
    }

    private function registry_state_label(string $state): string {
        if (isset(self::statuses()[$state])) return self::statuses()[$state];
        if (class_exists('SC_Library_Documentation') && isset(SC_Library_Documentation::statuses()[$state])) return SC_Library_Documentation::statuses()[$state];
        return match ($state) {
            'published' => __('Published', 'sustainable-catalyst-library'),
            'current' => __('Current documentation', 'sustainable-catalyst-library'),
            'living' => __('Living documentation', 'sustainable-catalyst-library'),
            'pdf_snapshot' => __('PDF snapshot', 'sustainable-catalyst-library'),
            'archived' => __('Archived record', 'sustainable-catalyst-library'),
            'superseded' => __('Superseded record', 'sustainable-catalyst-library'),
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }

    private function registry_type_label(string $type): string {
        if (isset(self::content_types()[$type])) return self::content_types()[$type];
        $object = get_post_type_object($type);
        return $object ? $object->labels->singular_name : ucfirst(str_replace('_', ' ', $type));
    }

    private function post_keywords(int $post_id): array {
        return array_values(array_unique(array_filter(array_merge(
            $this->term_names($post_id, 'post_tag'),
            $this->term_names($post_id, 'category'),
            $this->term_names($post_id, SC_Library_Taxonomies::CONCEPT),
            $this->term_names($post_id, SC_Library_Taxonomies::SERIES),
            $this->term_names($post_id, SC_Library_Taxonomies::DOCUMENT_CATEGORY)
        ))));
    }

    private function term_names(int $post_id, string $taxonomy): array {
        if (!taxonomy_exists($taxonomy)) return [];
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
        return is_wp_error($terms) ? [] : array_values(array_map('strval', $terms));
    }

    private function term_slugs(int $post_id, string $taxonomy): array {
        if (!taxonomy_exists($taxonomy)) return [];
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
        return is_wp_error($terms) ? [] : array_values(array_map('strval', $terms));
    }

    private function registry_facets(array $records): array {
        return [
            'states' => $this->count_values($records, 'record_state'),
            'types' => $this->count_values($records, 'content_type'),
            'areas' => $this->count_values($records, 'area'),
            'products' => $this->count_values($records, 'product'),
        ];
    }

    private function count_values(array $records, string $key): array {
        $counts = [];
        foreach ($records as $record) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value === '') continue;
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        $items = [];
        foreach ($counts as $value => $count) {
            $label = $key === 'record_state' ? $this->registry_state_label($value) : ($key === 'content_type' ? $this->registry_type_label($value) : $value);
            $items[] = ['value' => $value, 'label' => $label, 'count' => $count];
        }
        return $items;
    }

    private function summary(array $records): array {
        $summary = ['published' => 0, 'current_documentation' => 0, 'in_development' => 0, 'planned' => 0, 'scheduled' => 0, 'superseded' => 0, 'archived' => 0, 'total' => count($records)];
        foreach ($records as $record) {
            $state = $record['record_state'];
            if ($state === 'published') $summary['published']++;
            elseif (in_array($state, ['living', 'current', 'pdf_snapshot'], true)) $summary['current_documentation']++;
            elseif (in_array($state, ['researching', 'drafting', 'review'], true)) $summary['in_development']++;
            elseif (in_array($state, ['planned', 'proposed', 'idea', 'deferred'], true)) $summary['planned']++;
            elseif ($state === 'scheduled') $summary['scheduled']++;
            elseif ($state === 'superseded') $summary['superseded']++;
            elseif (in_array($state, ['archived', 'cancelled'], true)) $summary['archived']++;
        }
        return $summary;
    }

    public function tracker_data(bool $public = true, string $collection = ''): array {
        $records = $this->all_public_registry_records();
        if ($collection) $records = array_values(array_filter($records, static fn(array $r): bool => in_array($collection, $r['collections'], true)));
        $breakdown = function (string $key) use ($records): array {
            $groups = [];
            foreach ($records as $record) {
                $label = trim((string) ($record[$key] ?? '')) ?: __('Unassigned', 'sustainable-catalyst-library');
                if (!isset($groups[$label])) $groups[$label] = ['label' => $label, 'published' => 0, 'in_development' => 0, 'planned' => 0, 'archived' => 0, 'total' => 0];
                $groups[$label]['total']++;
                $bucket = $this->state_bucket($record['record_state']);
                $groups[$label][$bucket]++;
            }
            uasort($groups, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcasecmp($a['label'], $b['label']));
            return array_values($groups);
        };

        $maps = [];
        foreach ($records as $record) {
            $map_id = (int) ($record['article_map_id'] ?? 0);
            if (!$map_id) continue;
            if (!isset($maps[$map_id])) $maps[$map_id] = ['id' => $map_id, 'title' => get_the_title($map_id), 'url' => get_permalink($map_id), 'published' => 0, 'in_development' => 0, 'planned' => 0, 'total' => 0];
            $maps[$map_id]['total']++;
            $bucket = $this->state_bucket($record['record_state']);
            if ($bucket === 'archived') $bucket = 'planned';
            $maps[$map_id][$bucket]++;
        }
        uasort($maps, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

        $warnings = [];
        if (!$public) {
            foreach (get_posts(['post_type' => self::POST_TYPE, 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1]) as $post) {
                $plan = $this->plan_payload($post->ID, true);
                if ($plan['area'] === '') $warnings[] = ['title' => $plan['title'], 'message' => __('Missing area assignment.', 'sustainable-catalyst-library'), 'url' => get_edit_post_link($post->ID, 'raw')];
                if ($plan['status'] === 'scheduled' && !$plan['linked_draft_id']) $warnings[] = ['title' => $plan['title'], 'message' => __('Scheduled without a connected WordPress draft.', 'sustainable-catalyst-library'), 'url' => get_edit_post_link($post->ID, 'raw')];
                if ($plan['status'] === 'published' && !$plan['published_post_id']) $warnings[] = ['title' => $plan['title'], 'message' => __('Marked published without a canonical published post.', 'sustainable-catalyst-library'), 'url' => get_edit_post_link($post->ID, 'raw')];
                if ($this->is_overdue($plan)) $warnings[] = ['title' => $plan['title'], 'message' => __('Expected release window has passed.', 'sustainable-catalyst-library'), 'url' => get_edit_post_link($post->ID, 'raw')];
            }
        }

        return [
            'summary' => $this->summary($records),
            'by_area' => $breakdown('area'),
            'by_product' => $breakdown('product'),
            'by_status' => $breakdown('record_state'),
            'by_type' => $breakdown('content_type'),
            'article_maps' => array_values($maps),
            'warnings' => array_slice($warnings, 0, 100),
            'schema' => 'sc-library-roadmap-tracker/1.0',
        ];
    }

    private function state_bucket(string $state): string {
        if (in_array($state, ['published', 'living', 'current', 'pdf_snapshot'], true)) return 'published';
        if (in_array($state, ['researching', 'drafting', 'review', 'scheduled'], true)) return 'in_development';
        if (in_array($state, ['archived', 'superseded', 'cancelled'], true)) return 'archived';
        return 'planned';
    }

    private function is_overdue(array $record): bool {
        $sort = (string) ($record['expected_release']['sort'] ?? '');
        if ($sort === '' || in_array($record['record_state'] ?? '', ['published', 'cancelled', 'superseded'], true)) return false;
        return $sort < current_time('Y-m-d');
    }

    private function summary_label(string $key): string {
        return match ($key) {
            'published' => __('Published articles and records', 'sustainable-catalyst-library'),
            'current_documentation' => __('Current documentation', 'sustainable-catalyst-library'),
            'in_development' => __('In development', 'sustainable-catalyst-library'),
            'planned' => __('Planned', 'sustainable-catalyst-library'),
            'scheduled' => __('Scheduled', 'sustainable-catalyst-library'),
            'superseded' => __('Superseded', 'sustainable-catalyst-library'),
            'archived' => __('Archived', 'sustainable-catalyst-library'),
            default => __('Total public records', 'sustainable-catalyst-library'),
        };
    }

    public function render_registry_shortcode(array $atts = []): string {
        if (!self::enabled()) return '';
        $atts = shortcode_atts([
            'title' => __('Complete Public Registry', 'sustainable-catalyst-library'),
            'intro' => __('Search published knowledge, living documentation, active development, planned content, and historical records in one registry.', 'sustainable-catalyst-library'),
            'collection' => '',
            'show_tracker' => 'true',
            'per_page' => '20',
        ], $atts, 'sc_library_registry');
        self::enqueue_assets();
        $instance_id = 'sc-library-registry-' . wp_rand(1000, 999999);
        $registry_title = sanitize_text_field((string) $atts['title']);
        $registry_intro = sanitize_text_field((string) $atts['intro']);
        $registry_collection = sanitize_title((string) $atts['collection']);
        $registry_show_tracker = filter_var($atts['show_tracker'], FILTER_VALIDATE_BOOLEAN);
        $registry_per_page = min(100, max(1, absint($atts['per_page'])));
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-registry.php';
        return (string) ob_get_clean();
    }

    public function render_tracker_shortcode(array $atts = []): string {
        if (!self::enabled()) return '';
        $atts = shortcode_atts([
            'title' => __('Knowledge Roadmap Tracker', 'sustainable-catalyst-library'),
            'intro' => __('A public summary of published knowledge, current documentation, active development, planned work, and historical records.', 'sustainable-catalyst-library'),
            'collection' => '',
        ], $atts, 'sc_library_planner_tracker');
        self::enqueue_assets();
        $tracker_id = 'sc-library-tracker-' . wp_rand(1000, 999999);
        $tracker_title = sanitize_text_field((string) $atts['title']);
        $tracker_intro = sanitize_text_field((string) $atts['intro']);
        $tracker_collection = sanitize_title((string) $atts['collection']);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-roadmap-tracker.php';
        return (string) ob_get_clean();
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('sc-library-planner', SC_LIBRARY_URL . 'assets/css/sc-library-planner.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-planner', SC_LIBRARY_URL . 'assets/js/sc-library-planner.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-planner', 'SCLibraryPlanner', [
            'restBase' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
            'version' => SC_LIBRARY_VERSION,
            'strings' => [
                'loading' => __('Loading the public registry…', 'sustainable-catalyst-library'),
                'empty' => __('No public registry records match these filters.', 'sustainable-catalyst-library'),
                'error' => __('The public registry could not be loaded.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function handle_admin_export(): void {
        check_admin_referer('sc_library_export_registry');
        if (!current_user_can('manage_options')) wp_die(__('You cannot export the registry.', 'sustainable-catalyst-library'));
        $format = sanitize_key((string) ($_GET['format'] ?? 'json'));
        $records = $this->all_public_registry_records();
        $stamp = gmdate('Ymd-His');
        if ($format === 'csv') {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="sustainable-catalyst-registry-' . $stamp . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','identifier','title','state','content_type','area','product','expected_release','article_map','url','modified_at']);
            foreach ($records as $record) {
                fputcsv($out, [$record['id'],$record['record_identifier'],$record['title'],$record['record_state'],$record['content_type'],$record['area'],$record['product'],$record['expected_release']['display'],$record['article_map_title'],$record['url'],$record['modified_at']]);
            }
            fclose($out);
            exit;
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sustainable-catalyst-registry-' . $stamp . '.json"');
        echo wp_json_encode(['schema' => 'sc-library-registry/1.0', 'generated_at' => gmdate('c'), 'summary' => $this->summary($records), 'records' => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
