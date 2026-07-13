<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Planning analytics, dependency intelligence, and release coordination.
 *
 * This layer reads and extends Content Planner records without changing the
 * canonical publication date or automatically scheduling WordPress posts.
 */
final class SC_Library_Planning_Analytics {
    public const REST_NAMESPACE = 'sustainable-catalyst/v1/library';
    public const ANALYTICS_SCHEMA = 'sc-library-planning-analytics/1.0';
    public const DEPENDENCY_SCHEMA = 'sc-library-dependency-graph/1.0';
    public const RELEASE_SCHEMA = 'sc-library-release-coordination/1.0';

    private SC_Library_Planner $planner;

    public function __construct(SC_Library_Planner $planner) {
        $this->planner = $planner;
    }

    public function register_hooks(): void {
        if (!SC_Library_Planner::enabled()) return;
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_' . SC_Library_Planner::POST_TYPE, [$this, 'save_coordination'], 25, 3);
        add_action('admin_menu', [$this, 'admin_menu'], 25);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_post_sc_library_export_planning_analytics', [$this, 'handle_export']);
        add_shortcode('sc_library_planning_analytics', [$this, 'render_analytics_shortcode']);
        add_shortcode('sc_library_release_coordination', [$this, 'render_release_shortcode']);
    }

    public static function effort_units(): array {
        return [
            'points' => __('Points', 'sustainable-catalyst-library'),
            'hours' => __('Hours', 'sustainable-catalyst-library'),
            'days' => __('Days', 'sustainable-catalyst-library'),
        ];
    }

    public static function dependency_policies(): array {
        return [
            'all' => __('All dependencies must be complete', 'sustainable-catalyst-library'),
            'any' => __('Any one dependency may unblock the plan', 'sustainable-catalyst-library'),
            'informational' => __('Informational only', 'sustainable-catalyst-library'),
        ];
    }

    public function register_settings(): void {
        register_setting('sc_library_release_coordination', 'sc_library_release_capacity_threshold', [
            'type' => 'number',
            'sanitize_callback' => static fn($value): float => max(0.0, min(100000.0, (float) $value)),
            'default' => 40,
        ]);
        register_setting('sc_library_release_coordination', 'sc_library_default_effort_unit', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = sanitize_key((string) $value);
                return isset(self::effort_units()[$value]) ? $value : 'points';
            },
            'default' => 'points',
        ]);
        register_setting('sc_library_release_coordination', 'sc_library_on_time_tolerance_days', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value): int => max(0, min(365, absint($value))),
            'default' => 7,
        ]);
    }

    public function add_meta_box(): void {
        add_meta_box(
            'sc-library-plan-coordination',
            __('Planning Analytics and Release Coordination', 'sustainable-catalyst-library'),
            [$this, 'render_meta_box'],
            SC_Library_Planner::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void {
        wp_nonce_field('sc_library_save_plan_coordination', 'sc_library_plan_coordination_nonce');
        $data = $this->coordination_payload($post->ID, true);
        ?>
        <div class="sc-library-admin-record sc-library-plan-coordination">
            <p class="description"><?php esc_html_e('Coordinate workload, milestones, blockers, and release grouping. These fields do not schedule the WordPress post or replace the optional expected release fields above.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-admin-grid">
                <label><span><?php esc_html_e('Release group', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_release_group" value="<?php echo esc_attr($data['release_group']); ?>" placeholder="Library v1.12.0"></label>
                <label><span><?php esc_html_e('Release track', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_release_track" value="<?php echo esc_attr($data['release_track']); ?>" placeholder="Editorial, documentation, backend, public launch"></label>
                <label><span><?php esc_html_e('Milestone', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_milestone" value="<?php echo esc_attr($data['milestone']); ?>" placeholder="Research complete, first draft, validation"></label>
                <label><span><?php esc_html_e('Capacity owner', 'sustainable-catalyst-library'); ?></span><input type="text" name="sc_plan_capacity_owner" value="<?php echo esc_attr($data['capacity_owner']); ?>" placeholder="Editorial, Lab, Platform, Tariq"></label>
                <label><span><?php esc_html_e('Estimated effort', 'sustainable-catalyst-library'); ?></span><input type="number" step="0.25" min="0" name="sc_plan_estimated_effort" value="<?php echo esc_attr((string) $data['estimated_effort']); ?>"></label>
                <label><span><?php esc_html_e('Effort unit', 'sustainable-catalyst-library'); ?></span><select name="sc_plan_effort_unit"><?php foreach (self::effort_units() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($data['effort_unit'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Actual effort', 'sustainable-catalyst-library'); ?></span><input type="number" step="0.25" min="0" name="sc_plan_actual_effort" value="<?php echo esc_attr((string) $data['actual_effort']); ?>"></label>
                <label><span><?php esc_html_e('Progress', 'sustainable-catalyst-library'); ?></span><input type="number" min="0" max="100" name="sc_plan_progress_percent" value="<?php echo esc_attr((string) $data['progress_percent']); ?>"><small><?php esc_html_e('0–100 percent. Leave at the status-derived value or adjust manually.', 'sustainable-catalyst-library'); ?></small></label>
                <label><span><?php esc_html_e('Planned start', 'sustainable-catalyst-library'); ?></span><input type="date" name="sc_plan_planned_start" value="<?php echo esc_attr($data['planned_start']); ?>"></label>
                <label><span><?php esc_html_e('Actual start', 'sustainable-catalyst-library'); ?></span><input type="date" name="sc_plan_actual_start" value="<?php echo esc_attr($data['actual_start']); ?>"></label>
                <label><span><?php esc_html_e('Dependency policy', 'sustainable-catalyst-library'); ?></span><select name="sc_plan_dependency_policy"><?php foreach (self::dependency_policies() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($data['dependency_policy'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Manual blocker', 'sustainable-catalyst-library'); ?></span><span><input type="checkbox" name="sc_plan_blocked_override" value="1" <?php checked($data['blocked_override'], true); ?>> <?php esc_html_e('Mark blocked even when dependencies appear complete.', 'sustainable-catalyst-library'); ?></span></label>
            </div>
            <label class="sc-library-admin-field-wide"><span><?php esc_html_e('Blocker or coordination note', 'sustainable-catalyst-library'); ?></span><textarea rows="3" name="sc_plan_blocked_reason"><?php echo esc_textarea($data['blocked_reason']); ?></textarea></label>
            <div class="sc-library-plan-coordination__status">
                <strong><?php esc_html_e('Current coordination state:', 'sustainable-catalyst-library'); ?></strong>
                <?php echo $data['blocked'] ? '<span class="sc-library-plan-state sc-library-plan-state--blocked">' . esc_html__('Blocked', 'sustainable-catalyst-library') . '</span>' : '<span class="sc-library-plan-state sc-library-plan-state--ready">' . esc_html__('Ready', 'sustainable-catalyst-library') . '</span>'; ?>
                <span><?php echo esc_html(sprintf(__('%1$d of %2$d dependencies resolved', 'sustainable-catalyst-library'), $data['dependencies_resolved'], $data['dependencies_total'])); ?></span>
                <?php if ($data['timing_variance_days'] !== null) : ?><span><?php echo esc_html($this->variance_label((int) $data['timing_variance_days'])); ?></span><?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function save_coordination(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!isset($_POST['sc_library_plan_coordination_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sc_library_plan_coordination_nonce'])), 'sc_library_save_plan_coordination')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $unit = sanitize_key((string) ($_POST['sc_plan_effort_unit'] ?? 'points'));
        if (!isset(self::effort_units()[$unit])) $unit = 'points';
        $policy = sanitize_key((string) ($_POST['sc_plan_dependency_policy'] ?? 'all'));
        if (!isset(self::dependency_policies()[$policy])) $policy = 'all';
        $progress = max(0, min(100, absint($_POST['sc_plan_progress_percent'] ?? $this->status_progress((string) get_post_meta($post_id, '_sc_plan_status', true)))));

        $meta = [
            '_sc_plan_release_group' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_release_group'] ?? ''))),
            '_sc_plan_release_track' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_release_track'] ?? ''))),
            '_sc_plan_milestone' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_milestone'] ?? ''))),
            '_sc_plan_capacity_owner' => sanitize_text_field(wp_unslash((string) ($_POST['sc_plan_capacity_owner'] ?? ''))),
            '_sc_plan_estimated_effort' => max(0.0, (float) ($_POST['sc_plan_estimated_effort'] ?? 0)),
            '_sc_plan_effort_unit' => $unit,
            '_sc_plan_actual_effort' => max(0.0, (float) ($_POST['sc_plan_actual_effort'] ?? 0)),
            '_sc_plan_progress_percent' => $progress,
            '_sc_plan_planned_start' => $this->sanitize_date((string) ($_POST['sc_plan_planned_start'] ?? '')),
            '_sc_plan_actual_start' => $this->sanitize_date((string) ($_POST['sc_plan_actual_start'] ?? '')),
            '_sc_plan_dependency_policy' => $policy,
            '_sc_plan_blocked_override' => isset($_POST['sc_plan_blocked_override']) ? 1 : 0,
            '_sc_plan_blocked_reason' => sanitize_textarea_field(wp_unslash((string) ($_POST['sc_plan_blocked_reason'] ?? ''))),
        ];
        foreach ($meta as $key => $value) update_post_meta($post_id, $key, $value);
    }

    private function sanitize_date(string $value): string {
        $value = sanitize_text_field(wp_unslash($value));
        if ($value === '') return '';
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public function extend_plan_payload(array $payload, int $post_id, bool $admin): array {
        if (get_post_type($post_id) !== SC_Library_Planner::POST_TYPE) return $payload;
        $coordination = $this->coordination_payload($post_id, $admin);
        unset($coordination['dependencies']);
        if (!$admin) unset($coordination['blocked_reason'], $coordination['capacity_owner'], $coordination['actual_effort']);
        return array_merge($payload, ['coordination' => $coordination]);
    }

    public function coordination_payload(int $post_id, bool $admin = false): array {
        $plan = $this->planner->plan_payload($post_id, $admin);
        $dependencies = $this->dependency_details($plan['dependency_ids']);
        $resolved = count(array_filter($dependencies, static fn(array $item): bool => $item['resolved']));
        $policy = (string) get_post_meta($post_id, '_sc_plan_dependency_policy', true) ?: 'all';
        if (!isset(self::dependency_policies()[$policy])) $policy = 'all';
        $override = (bool) get_post_meta($post_id, '_sc_plan_blocked_override', true);
        $blocked = $override || $this->dependencies_block($dependencies, $policy);
        $actual_date = $this->actual_publication_date($plan);
        $deadline = $this->release_deadline($plan['expected_release']);
        $variance = ($actual_date && $deadline) ? $this->days_between($deadline, $actual_date) : null;
        $status = (string) ($plan['status'] ?? 'planned');
        $progress_meta = get_post_meta($post_id, '_sc_plan_progress_percent', true);
        $progress = $progress_meta === '' ? $this->status_progress($status) : max(0, min(100, absint($progress_meta)));
        $effort_unit = (string) get_post_meta($post_id, '_sc_plan_effort_unit', true) ?: (string) get_option('sc_library_default_effort_unit', 'points');
        if (!isset(self::effort_units()[$effort_unit])) $effort_unit = 'points';

        return [
            'release_group' => (string) get_post_meta($post_id, '_sc_plan_release_group', true),
            'release_track' => (string) get_post_meta($post_id, '_sc_plan_release_track', true),
            'milestone' => (string) get_post_meta($post_id, '_sc_plan_milestone', true),
            'capacity_owner' => (string) get_post_meta($post_id, '_sc_plan_capacity_owner', true) ?: (string) ($plan['responsible'] ?? ''),
            'estimated_effort' => (float) get_post_meta($post_id, '_sc_plan_estimated_effort', true),
            'effort_unit' => $effort_unit,
            'actual_effort' => (float) get_post_meta($post_id, '_sc_plan_actual_effort', true),
            'progress_percent' => $progress,
            'planned_start' => (string) get_post_meta($post_id, '_sc_plan_planned_start', true),
            'actual_start' => (string) get_post_meta($post_id, '_sc_plan_actual_start', true),
            'dependency_policy' => $policy,
            'blocked_override' => $override,
            'blocked_reason' => (string) get_post_meta($post_id, '_sc_plan_blocked_reason', true),
            'blocked' => $blocked,
            'dependencies_total' => count($dependencies),
            'dependencies_resolved' => $resolved,
            'dependencies' => $dependencies,
            'release_window' => $this->release_window($plan['expected_release']),
            'release_deadline' => $deadline,
            'actual_publication_date' => $actual_date,
            'timing_variance_days' => $variance,
            'on_time' => $variance === null ? null : $variance <= (int) get_option('sc_library_on_time_tolerance_days', 7),
            'completeness' => $this->completeness($plan),
        ];
    }

    private function dependency_details(array $ids): array {
        $items = [];
        foreach (array_values(array_unique(array_map('absint', $ids))) as $id) {
            if (!$id) continue;
            $post = get_post($id);
            if (!$post) {
                $items[] = ['id' => $id, 'title' => sprintf(__('Missing record %d', 'sustainable-catalyst-library'), $id), 'type' => 'missing', 'state' => 'missing', 'resolved' => false, 'url' => ''];
                continue;
            }
            if ($post->post_type === SC_Library_Planner::POST_TYPE) {
                $state = (string) get_post_meta($id, '_sc_plan_status', true) ?: 'planned';
                $published_id = absint(get_post_meta($id, '_sc_plan_published_post_id', true));
                $resolved = $state === 'published' && (!$published_id || get_post_status($published_id) === 'publish');
                $items[] = ['id' => $id, 'title' => get_the_title($id), 'type' => 'plan', 'state' => $state, 'resolved' => $resolved, 'url' => get_edit_post_link($id, 'raw') ?: ''];
            } else {
                $state = get_post_status($id) ?: 'missing';
                $items[] = ['id' => $id, 'title' => get_the_title($id), 'type' => $post->post_type, 'state' => $state, 'resolved' => $state === 'publish', 'url' => $state === 'publish' ? get_permalink($id) : (get_edit_post_link($id, 'raw') ?: '')];
            }
        }
        return $items;
    }

    private function dependencies_block(array $dependencies, string $policy): bool {
        if (!$dependencies || $policy === 'informational') return false;
        $resolved = count(array_filter($dependencies, static fn(array $item): bool => $item['resolved']));
        return $policy === 'any' ? $resolved === 0 : $resolved < count($dependencies);
    }

    private function actual_publication_date(array $plan): string {
        $date = (string) get_post_meta((int) $plan['id'], '_sc_plan_actual_publication_date', true);
        if ($date) return $date;
        $published_id = absint($plan['published_post_id'] ?? 0);
        if ($published_id && get_post_status($published_id) === 'publish') {
            $post = get_post($published_id);
            return $post ? substr((string) ($post->post_date ?: ''), 0, 10) : '';
        }
        return '';
    }

    private function release_deadline(array $release): string {
        $type = (string) ($release['type'] ?? 'none');
        if ($type === 'exact') return (string) ($release['date'] ?? '');
        if ($type === 'month' && !empty($release['month'])) {
            $stamp = strtotime((string) $release['month'] . '-01 +1 month -1 day');
            return $stamp ? gmdate('Y-m-d', $stamp) : '';
        }
        if ($type === 'quarter' && !empty($release['quarter']) && !empty($release['year'])) {
            $months = ['Q1' => '03-31', 'Q2' => '06-30', 'Q3' => '09-30', 'Q4' => '12-31'];
            return isset($months[$release['quarter']]) ? sprintf('%04d-%s', (int) $release['year'], $months[$release['quarter']]) : '';
        }
        if ($type === 'year' && !empty($release['year'])) return sprintf('%04d-12-31', (int) $release['year']);
        return '';
    }

    private function release_window(array $release): array {
        $type = (string) ($release['type'] ?? 'none');
        $key = 'unscheduled';
        $label = __('No expected release', 'sustainable-catalyst-library');
        $sort = '9999-99';
        if ($type === 'exact' && !empty($release['date'])) {
            $key = substr((string) $release['date'], 0, 7);
            $label = wp_date('F Y', strtotime((string) $release['date']));
            $sort = $key;
        } elseif ($type === 'month' && !empty($release['month'])) {
            $key = (string) $release['month'];
            $label = wp_date('F Y', strtotime($key . '-01'));
            $sort = $key;
        } elseif ($type === 'quarter' && !empty($release['quarter']) && !empty($release['year'])) {
            $key = sprintf('%04d-%s', (int) $release['year'], (string) $release['quarter']);
            $label = sprintf('%s %d', (string) $release['quarter'], (int) $release['year']);
            $sort = sprintf('%04d-%02d', (int) $release['year'], ['Q1' => 1, 'Q2' => 4, 'Q3' => 7, 'Q4' => 10][$release['quarter']] ?? 1);
        } elseif ($type === 'year' && !empty($release['year'])) {
            $key = (string) (int) $release['year'];
            $label = $key;
            $sort = $key . '-12';
        } elseif ($type === 'product' && !empty($release['product_release'])) {
            $key = 'release:' . sanitize_title((string) $release['product_release']);
            $label = (string) $release['product_release'];
            $sort = '9000-' . $label;
        }
        return ['key' => $key, 'label' => $label, 'sort' => $sort, 'type' => $type];
    }

    private function days_between(string $from, string $to): int {
        try {
            $a = new DateTimeImmutable($from);
            $b = new DateTimeImmutable($to);
            return (int) $a->diff($b)->format('%r%a');
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function status_progress(string $status): int {
        return [
            'idea' => 5, 'proposed' => 10, 'planned' => 20, 'researching' => 35,
            'drafting' => 60, 'review' => 80, 'scheduled' => 95, 'published' => 100,
            'deferred' => 15, 'cancelled' => 0, 'superseded' => 100,
        ][$status] ?? 20;
    }

    private function completeness(array $plan): array {
        $checks = [
            'area' => trim((string) ($plan['area'] ?? '')) !== '',
            'product' => trim((string) ($plan['product'] ?? '')) !== '',
            'responsible' => trim((string) ($plan['responsible'] ?? '')) !== '',
            'release' => (string) ($plan['expected_release']['type'] ?? 'none') !== 'none',
            'sources' => trim((string) ($plan['sources'] ?? '')) !== '',
            'artifacts' => trim((string) ($plan['expected_artifacts'] ?? '')) !== '',
            'research_questions' => trim((string) ($plan['research_questions'] ?? '')) !== '',
            'article_map' => !empty($plan['article_map_id']),
        ];
        $complete = count(array_filter($checks));
        return ['score' => (int) round(($complete / count($checks)) * 100), 'complete' => $complete, 'total' => count($checks), 'checks' => $checks];
    }

    private function format_effort(array $by_unit): string {
        $parts = [];
        foreach ($by_unit as $unit => $amount) {
            if ((float) $amount <= 0) continue;
            $label = self::effort_units()[$unit] ?? ucfirst((string) $unit);
            $parts[] = rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.') . ' ' . strtolower($label);
        }
        return $parts ? implode(' · ', $parts) : '0';
    }

    private function variance_label(int $days): string {
        if ($days === 0) return __('Published on the expected deadline', 'sustainable-catalyst-library');
        return $days > 0
            ? sprintf(_n('%d day later than the expected window', '%d days later than the expected window', $days, 'sustainable-catalyst-library'), $days)
            : sprintf(_n('%d day earlier than the expected window', '%d days earlier than the expected window', abs($days), 'sustainable-catalyst-library'), abs($days));
    }

    private function all_plans(bool $public = false, string $collection = ''): array {
        $statuses = $public ? ['publish'] : ['publish', 'draft', 'private', 'pending', 'future'];
        $ids = get_posts([
            'post_type' => SC_Library_Planner::POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        $plans = [];
        foreach ($ids as $id) {
            if ($public && !SC_Library_Planner::is_public_plan((int) $id)) continue;
            if ($collection && taxonomy_exists(SC_Library_Taxonomies::COLLECTION) && !has_term($collection, SC_Library_Taxonomies::COLLECTION, $id)) continue;
            $plan = $this->planner->plan_payload((int) $id, !$public);
            $plan['coordination'] = $this->coordination_payload((int) $id, !$public);
            if ($public) unset($plan['internal_notes']);
            $plans[] = $plan;
        }
        return $plans;
    }

    public function analytics_data(bool $public = false, string $collection = ''): array {
        $plans = $this->all_plans($public, $collection);
        $today = current_time('Y-m-d');
        $in30 = gmdate('Y-m-d', strtotime($today . ' +30 days'));
        $in90 = gmdate('Y-m-d', strtotime($today . ' +90 days'));
        $ago30 = gmdate('Y-m-d', strtotime($today . ' -30 days'));
        $ago90 = gmdate('Y-m-d', strtotime($today . ' -90 days'));
        $metrics = [
            'total_plans' => count($plans), 'active' => 0, 'completed' => 0, 'blocked' => 0,
            'overdue' => 0, 'unscheduled' => 0, 'high_priority' => 0, 'due_30_days' => 0,
            'due_90_days' => 0, 'published_30_days' => 0, 'published_90_days' => 0,
            'estimated_effort' => 0.0, 'actual_effort' => 0.0, 'estimated_effort_by_unit' => [], 'actual_effort_by_unit' => [], 'on_time_rate' => null,
            'average_variance_days' => null, 'average_completeness' => 0,
        ];
        $variance = [];
        $on_time = [];
        $completeness = [];
        $breakdowns = ['area' => [], 'product' => [], 'responsible' => [], 'status' => [], 'content_type' => [], 'release_group' => []];
        $gaps = [];

        foreach ($plans as $plan) {
            $coord = $plan['coordination'];
            $state = (string) $plan['status'];
            $final = in_array($state, ['published', 'cancelled', 'superseded'], true);
            $metrics[$final ? 'completed' : 'active']++;
            if ($coord['blocked']) $metrics['blocked']++;
            if (!$final && $coord['release_deadline'] && $coord['release_deadline'] < $today) $metrics['overdue']++;
            if ((string) $plan['expected_release']['type'] === 'none') $metrics['unscheduled']++;
            if (in_array((string) $plan['priority'], ['high', 'critical'], true)) $metrics['high_priority']++;
            if (!$final && $coord['release_deadline'] && $coord['release_deadline'] >= $today && $coord['release_deadline'] <= $in30) $metrics['due_30_days']++;
            if (!$final && $coord['release_deadline'] && $coord['release_deadline'] >= $today && $coord['release_deadline'] <= $in90) $metrics['due_90_days']++;
            if ($coord['actual_publication_date'] && $coord['actual_publication_date'] >= $ago30) $metrics['published_30_days']++;
            if ($coord['actual_publication_date'] && $coord['actual_publication_date'] >= $ago90) $metrics['published_90_days']++;
            $effort_unit = (string) $coord['effort_unit'];
            $metrics['estimated_effort_by_unit'][$effort_unit] = ($metrics['estimated_effort_by_unit'][$effort_unit] ?? 0) + (float) $coord['estimated_effort'];
            $metrics['actual_effort_by_unit'][$effort_unit] = ($metrics['actual_effort_by_unit'][$effort_unit] ?? 0) + (float) $coord['actual_effort'];
            if ($coord['timing_variance_days'] !== null) $variance[] = (int) $coord['timing_variance_days'];
            if ($coord['on_time'] !== null) $on_time[] = (bool) $coord['on_time'];
            $completeness[] = (int) $coord['completeness']['score'];

            $values = [
                'area' => (string) $plan['area'],
                'product' => (string) $plan['product'],
                'responsible' => (string) ($coord['capacity_owner'] ?: $plan['responsible']),
                'status' => (string) $plan['record_state_label'],
                'content_type' => (string) $plan['content_type_label'],
                'release_group' => (string) ($coord['release_group'] ?: $coord['release_window']['label']),
            ];
            foreach ($values as $dimension => $label) {
                $label = trim($label) ?: __('Unassigned', 'sustainable-catalyst-library');
                if (!isset($breakdowns[$dimension][$label])) $breakdowns[$dimension][$label] = ['label' => $label, 'count' => 0, 'active' => 0, 'completed' => 0, 'blocked' => 0, 'overdue' => 0, 'effort' => 0.0, 'effort_by_unit' => [], 'effort_display' => '', 'average_progress' => 0.0, '_progress_total' => 0];
                $row =& $breakdowns[$dimension][$label];
                $row['count']++;
                $row[$final ? 'completed' : 'active']++;
                if ($coord['blocked']) $row['blocked']++;
                if (!$final && $coord['release_deadline'] && $coord['release_deadline'] < $today) $row['overdue']++;
                $row['effort_by_unit'][$effort_unit] = ($row['effort_by_unit'][$effort_unit] ?? 0) + (float) $coord['estimated_effort'];
                $row['_progress_total'] += (int) $coord['progress_percent'];
                unset($row);
            }

            if (!$public) {
                foreach ($coord['completeness']['checks'] as $field => $complete) {
                    if (!$complete) $gaps[] = ['id' => (int) $plan['id'], 'title' => (string) $plan['title'], 'field' => $field, 'message' => $this->gap_label($field), 'url' => get_edit_post_link((int) $plan['id'], 'raw') ?: ''];
                }
            }
        }

        foreach ($breakdowns as &$dimension) {
            foreach ($dimension as &$row) {
                $row['average_progress'] = $row['count'] ? round($row['_progress_total'] / $row['count'], 1) : 0;
                unset($row['_progress_total']);
                foreach ($row['effort_by_unit'] as $unit => $amount) $row['effort_by_unit'][$unit] = round($amount, 2);
                $row['effort_display'] = $this->format_effort($row['effort_by_unit']);
                $row['effort'] = (float) ($row['effort_by_unit'][(string) get_option('sc_library_default_effort_unit', 'points')] ?? 0);
            }
            uasort($dimension, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']));
            $dimension = array_values($dimension);
        }
        unset($dimension, $row);

        foreach ($metrics['estimated_effort_by_unit'] as $unit => $amount) $metrics['estimated_effort_by_unit'][$unit] = round($amount, 2);
        foreach ($metrics['actual_effort_by_unit'] as $unit => $amount) $metrics['actual_effort_by_unit'][$unit] = round($amount, 2);
        $default_unit = (string) get_option('sc_library_default_effort_unit', 'points');
        $metrics['estimated_effort'] = (float) ($metrics['estimated_effort_by_unit'][$default_unit] ?? 0);
        $metrics['actual_effort'] = (float) ($metrics['actual_effort_by_unit'][$default_unit] ?? 0);
        $metrics['estimated_effort_display'] = $this->format_effort($metrics['estimated_effort_by_unit']);
        $metrics['actual_effort_display'] = $this->format_effort($metrics['actual_effort_by_unit']);
        $metrics['on_time_rate'] = $on_time ? round((count(array_filter($on_time)) / count($on_time)) * 100, 1) : null;
        $metrics['average_variance_days'] = $variance ? round(array_sum($variance) / count($variance), 1) : null;
        $metrics['average_completeness'] = $completeness ? round(array_sum($completeness) / count($completeness), 1) : 0;
        $graph = $this->dependency_graph($plans, $public);
        $releases = $this->release_data($public, $collection, $plans);
        if ($public) unset($metrics['actual_effort'], $metrics['actual_effort_by_unit'], $metrics['actual_effort_display']);

        $documentation_coverage = $this->documentation_coverage($public);
        $knowledge_gaps = $this->knowledge_gap_analysis($plans);

        return [
            'schema' => self::ANALYTICS_SCHEMA,
            'generated_at' => gmdate('c'),
            'public' => $public,
            'metrics' => $metrics,
            'by_area' => $breakdowns['area'],
            'by_product' => $breakdowns['product'],
            'by_responsible' => $public ? [] : $breakdowns['responsible'],
            'by_status' => $breakdowns['status'],
            'by_content_type' => $breakdowns['content_type'],
            'by_release_group' => $breakdowns['release_group'],
            'dependency_summary' => $graph['summary'],
            'release_summary' => $releases['summary'],
            'coverage_gaps' => $public ? [] : array_slice($gaps, 0, 250),
            'documentation_coverage' => $documentation_coverage,
            'knowledge_gap_analysis' => $knowledge_gaps,
        ];
    }

    private function documentation_coverage(bool $public = false): array {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sc_library_doc_status'") ?: [];
        $summary = ['total' => 0, 'authority_complete' => 0, 'responsible_complete' => 0, 'reviewed' => 0, 'overdue_review' => 0, 'current' => 0, 'archived' => 0];
        $today = current_time('Y-m-d');
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($public && get_post_status($id) !== 'publish') continue;
            $summary['total']++;
            $status = (string) get_post_meta($id, '_sc_library_doc_status', true);
            if (in_array($status, ['archived', 'superseded'], true)) $summary['archived']++; else $summary['current']++;
            $authority_type = (string) get_post_meta($id, '_sc_library_doc_authority_type', true);
            $authority_url = (string) get_post_meta($id, '_sc_library_doc_authority_url', true);
            $fallback_url = match ($authority_type) {
                'repository' => (string) get_post_meta($id, '_sc_library_doc_repository_url', true),
                'release' => (string) get_post_meta($id, '_sc_library_doc_release_url', true),
                'pdf', 'archive' => (string) get_post_meta($id, '_sc_library_doc_pdf_url', true),
                default => (string) get_post_meta($id, '_sc_library_doc_webpage_url', true),
            };
            if ($authority_type !== '' && ($authority_url !== '' || $fallback_url !== '')) $summary['authority_complete']++;
            if ((string) get_post_meta($id, '_sc_library_doc_responsible_area', true) !== '') $summary['responsible_complete']++;
            $reviewed = (string) get_post_meta($id, '_sc_library_doc_last_reviewed', true);
            if ($reviewed !== '') {
                $summary['reviewed']++;
                $interval = max(0, (int) get_post_meta($id, '_sc_library_doc_review_interval', true));
                if ($interval && gmdate('Y-m-d', strtotime($reviewed . ' +' . $interval . ' months')) < $today) $summary['overdue_review']++;
            }
        }
        $total = max(1, $summary['total']);
        $summary['authority_coverage_percent'] = round(($summary['authority_complete'] / $total) * 100, 1);
        $summary['responsible_coverage_percent'] = round(($summary['responsible_complete'] / $total) * 100, 1);
        $summary['review_coverage_percent'] = round(($summary['reviewed'] / $total) * 100, 1);
        return $summary;
    }

    private function knowledge_gap_analysis(array $plans): array {
        $areas = [];
        foreach ($this->planner->all_public_registry_records() as $record) {
            $area = trim((string) ($record['area'] ?? '')) ?: __('Unassigned', 'sustainable-catalyst-library');
            if (!isset($areas[$area])) $areas[$area] = ['area' => $area, 'published' => 0, 'active_plans' => 0, 'planned_effort' => 0.0, 'gap_ratio' => 0.0];
            if (in_array((string) ($record['record_state'] ?? ''), ['published', 'living', 'current', 'pdf_snapshot'], true)) $areas[$area]['published']++;
        }
        foreach ($plans as $plan) {
            $area = trim((string) ($plan['area'] ?? '')) ?: __('Unassigned', 'sustainable-catalyst-library');
            if (!isset($areas[$area])) $areas[$area] = ['area' => $area, 'published' => 0, 'active_plans' => 0, 'planned_effort' => 0.0, 'gap_ratio' => 0.0];
            if (!in_array((string) $plan['status'], ['published', 'cancelled', 'superseded'], true)) {
                $areas[$area]['active_plans']++;
                $areas[$area]['planned_effort'] += (float) $plan['coordination']['estimated_effort'];
            }
        }
        foreach ($areas as &$area) {
            $area['planned_effort'] = round($area['planned_effort'], 2);
            $area['gap_ratio'] = $area['published'] > 0 ? round($area['active_plans'] / $area['published'], 2) : ($area['active_plans'] > 0 ? 999 : 0);
        }
        unset($area);
        uasort($areas, static fn(array $a, array $b): int => $b['active_plans'] <=> $a['active_plans'] ?: $a['published'] <=> $b['published']);
        return array_values($areas);
    }

    private function gap_label(string $field): string {
        return [
            'area' => __('Missing knowledge or institutional area.', 'sustainable-catalyst-library'),
            'product' => __('Missing product or system assignment.', 'sustainable-catalyst-library'),
            'responsible' => __('Missing responsible area.', 'sustainable-catalyst-library'),
            'release' => __('No expected release window.', 'sustainable-catalyst-library'),
            'sources' => __('No planned sources recorded.', 'sustainable-catalyst-library'),
            'artifacts' => __('No expected artifacts recorded.', 'sustainable-catalyst-library'),
            'research_questions' => __('No research questions recorded.', 'sustainable-catalyst-library'),
            'article_map' => __('Not associated with an article map.', 'sustainable-catalyst-library'),
        ][$field] ?? __('Planning field is incomplete.', 'sustainable-catalyst-library');
    }

    public function dependency_graph(?array $plans = null, bool $public = false, string $collection = ''): array {
        $plans = $plans ?? $this->all_plans($public, $collection);
        $plan_ids = array_map(static fn(array $plan): int => (int) $plan['id'], $plans);
        $plan_set = array_fill_keys($plan_ids, true);
        $nodes = [];
        $edges = [];
        $adjacency = [];
        foreach ($plans as $plan) {
            $coord = $plan['coordination'];
            $nodes[(int) $plan['id']] = [
                'id' => (int) $plan['id'], 'title' => (string) $plan['title'], 'status' => (string) $plan['status'],
                'blocked' => (bool) $coord['blocked'], 'progress' => (int) $coord['progress_percent'],
                'release' => (string) $coord['release_window']['label'],
                'url' => $public ? (string) $plan['url'] : (get_edit_post_link((int) $plan['id'], 'raw') ?: ''),
            ];
            $adjacency[(int) $plan['id']] = [];
            foreach ($coord['dependencies'] as $dependency) {
                if ($public) {
                    $visible_dependency = $dependency['type'] === 'plan'
                        ? SC_Library_Planner::is_public_plan((int) $dependency['id'])
                        : (string) $dependency['state'] === 'publish';
                    if (!$visible_dependency) continue;
                }
                $edges[] = ['source' => (int) $plan['id'], 'target' => (int) $dependency['id'], 'resolved' => (bool) $dependency['resolved'], 'state' => (string) $dependency['state']];
                if (isset($plan_set[(int) $dependency['id']])) $adjacency[(int) $plan['id']][] = (int) $dependency['id'];
                if (!isset($nodes[(int) $dependency['id']])) $nodes[(int) $dependency['id']] = ['id' => (int) $dependency['id'], 'title' => (string) $dependency['title'], 'status' => (string) $dependency['state'], 'blocked' => !(bool) $dependency['resolved'], 'progress' => $dependency['resolved'] ? 100 : 0, 'release' => '', 'url' => (string) $dependency['url']];
            }
        }
        $cycles = $this->find_cycles($adjacency);
        $unresolved_edges = count(array_filter($edges, static fn(array $edge): bool => !$edge['resolved']));
        $blocked_nodes = count(array_filter($nodes, static fn(array $node): bool => $node['blocked']));
        return [
            'schema' => self::DEPENDENCY_SCHEMA,
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'cycles' => $cycles,
            'summary' => ['nodes' => count($nodes), 'edges' => count($edges), 'unresolved_edges' => $unresolved_edges, 'blocked_nodes' => $blocked_nodes, 'cycles' => count($cycles)],
        ];
    }

    private function find_cycles(array $adjacency): array {
        $state = [];
        $stack = [];
        $cycles = [];
        $visit = function (int $node) use (&$visit, &$state, &$stack, &$cycles, $adjacency): void {
            $state[$node] = 1;
            $stack[] = $node;
            foreach ($adjacency[$node] ?? [] as $next) {
                if (($state[$next] ?? 0) === 0) {
                    $visit($next);
                } elseif (($state[$next] ?? 0) === 1) {
                    $index = array_search($next, $stack, true);
                    if ($index !== false) {
                        $cycle = array_slice($stack, (int) $index);
                        $cycle[] = $next;
                        $key = implode('>', $cycle);
                        $cycles[$key] = $cycle;
                    }
                }
            }
            array_pop($stack);
            $state[$node] = 2;
        };
        foreach (array_keys($adjacency) as $node) if (($state[$node] ?? 0) === 0) $visit((int) $node);
        return array_values($cycles);
    }

    public function release_data(bool $public = false, string $collection = '', ?array $plans = null): array {
        $plans = $plans ?? $this->all_plans($public, $collection);
        $windows = [];
        $threshold = (float) get_option('sc_library_release_capacity_threshold', 40);
        $today = current_time('Y-m-d');
        foreach ($plans as $plan) {
            $coord = $plan['coordination'];
            $window = $coord['release_window'];
            $key = (string) $window['key'];
            if (!isset($windows[$key])) {
                $windows[$key] = ['key' => $key, 'label' => (string) $window['label'], 'sort' => (string) $window['sort'], 'count' => 0, 'active' => 0, 'completed' => 0, 'blocked' => 0, 'overdue' => 0, 'estimated_effort' => 0.0, 'effort_by_unit' => [], 'effort_display' => '', 'tracks' => [], 'plans' => []];
            }
            $row =& $windows[$key];
            $row['count']++;
            $final = in_array((string) $plan['status'], ['published', 'cancelled', 'superseded'], true);
            $row[$final ? 'completed' : 'active']++;
            if ($coord['blocked']) $row['blocked']++;
            if (!$final && $coord['release_deadline'] && $coord['release_deadline'] < $today) $row['overdue']++;
            $unit = (string) $coord['effort_unit'];
            $row['effort_by_unit'][$unit] = ($row['effort_by_unit'][$unit] ?? 0) + (float) $coord['estimated_effort'];
            $track = trim((string) $coord['release_track']) ?: __('Unassigned track', 'sustainable-catalyst-library');
            $row['tracks'][$track] = ($row['tracks'][$track] ?? 0) + 1;
            $row['plans'][] = [
                'id' => (int) $plan['id'], 'title' => (string) $plan['title'], 'status' => (string) $plan['record_state_label'],
                'priority' => (string) $plan['priority'], 'blocked' => (bool) $coord['blocked'],
                'progress' => (int) $coord['progress_percent'], 'effort' => (float) $coord['estimated_effort'],
                'owner' => $public ? '' : (string) $coord['capacity_owner'],
                'url' => $public ? (string) $plan['url'] : (get_edit_post_link((int) $plan['id'], 'raw') ?: ''),
            ];
            unset($row);
        }
        foreach ($windows as &$window) {
            foreach ($window['effort_by_unit'] as $unit => $amount) $window['effort_by_unit'][$unit] = round($amount, 2);
            $default_unit = (string) get_option('sc_library_default_effort_unit', 'points');
            $window['estimated_effort'] = (float) ($window['effort_by_unit'][$default_unit] ?? 0);
            $window['effort_display'] = $this->format_effort($window['effort_by_unit']);
            $window['capacity_threshold'] = $threshold;
            $window['capacity_unit'] = $default_unit;
            $window['over_capacity'] = $threshold > 0 && $window['estimated_effort'] > $threshold;
            arsort($window['tracks']);
            usort($window['plans'], static fn(array $a, array $b): int => (int) $b['blocked'] <=> (int) $a['blocked'] ?: strcmp($a['title'], $b['title']));
        }
        unset($window);
        uasort($windows, static fn(array $a, array $b): int => strcmp($a['sort'], $b['sort']));
        $conflicts = array_values(array_filter($windows, static fn(array $window): bool => $window['over_capacity'] || $window['blocked'] > 0 || $window['overdue'] > 0));
        return [
            'schema' => self::RELEASE_SCHEMA,
            'generated_at' => gmdate('c'),
            'summary' => [
                'windows' => count($windows),
                'over_capacity' => count(array_filter($windows, static fn(array $window): bool => $window['over_capacity'])),
                'with_blockers' => count(array_filter($windows, static fn(array $window): bool => $window['blocked'] > 0)),
                'overdue_windows' => count(array_filter($windows, static fn(array $window): bool => $window['overdue'] > 0)),
                'capacity_threshold' => $threshold,
            ],
            'windows' => array_values($windows),
            'conflicts' => $conflicts,
        ];
    }

    public function admin_menu(): void {
        add_submenu_page('sc-library', __('Planning Analytics', 'sustainable-catalyst-library'), __('Planning Analytics', 'sustainable-catalyst-library'), 'edit_posts', 'sc-library-planning-analytics', [$this, 'render_admin_analytics']);
        add_submenu_page('sc-library', __('Release Coordination', 'sustainable-catalyst-library'), __('Release Coordination', 'sustainable-catalyst-library'), 'edit_posts', 'sc-library-release-coordination', [$this, 'render_admin_release']);
    }

    public function admin_assets(string $hook): void {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        $screen = get_current_screen();
        if (!in_array($page, ['sc-library-planning-analytics', 'sc-library-release-coordination'], true) && (!$screen || $screen->post_type !== SC_Library_Planner::POST_TYPE)) return;
        wp_enqueue_style('sc-library-admin', SC_LIBRARY_URL . 'assets/css/sc-library-admin.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-planning-analytics', SC_LIBRARY_URL . 'assets/css/sc-library-planning-analytics.css', ['sc-library-admin'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-planning-analytics', SC_LIBRARY_URL . 'assets/js/sc-library-planning-analytics.js', [], SC_LIBRARY_VERSION, true);
    }

    public function render_admin_analytics(): void {
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission to view planning analytics.', 'sustainable-catalyst-library'));
        $analytics = $this->analytics_data(false);
        $graph = $this->dependency_graph(null, false);
        $export_json = wp_nonce_url(admin_url('admin-post.php?action=sc_library_export_planning_analytics&format=json'), 'sc_library_export_planning_analytics');
        $export_csv = wp_nonce_url(admin_url('admin-post.php?action=sc_library_export_planning_analytics&format=csv'), 'sc_library_export_planning_analytics');
        $page_title = __('Planning Analytics', 'sustainable-catalyst-library');
        include SC_LIBRARY_DIR . 'templates/library-planning-analytics-admin.php';
    }

    public function render_admin_release(): void {
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission to view release coordination.', 'sustainable-catalyst-library'));
        $release = $this->release_data(false);
        $graph = $this->dependency_graph(null, false);
        include SC_LIBRARY_DIR . 'templates/library-release-coordination-admin.php';
    }

    public function register_rest_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/planning/analytics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response {
                $edit = $request->get_param('context') === 'edit' && current_user_can('edit_posts');
                return rest_ensure_response($this->analytics_data(!$edit, sanitize_title((string) $request->get_param('collection'))));
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/planning/dependencies', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response {
                $edit = $request->get_param('context') === 'edit' && current_user_can('edit_posts');
                return rest_ensure_response($this->dependency_graph(null, !$edit, sanitize_title((string) $request->get_param('collection'))));
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/planning/releases', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function (WP_REST_Request $request): WP_REST_Response {
                $edit = $request->get_param('context') === 'edit' && current_user_can('edit_posts');
                return rest_ensure_response($this->release_data(!$edit, sanitize_title((string) $request->get_param('collection'))));
            },
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/planning/coordination-schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(): WP_REST_Response => rest_ensure_response([
                'analytics_schema' => self::ANALYTICS_SCHEMA,
                'dependency_schema' => self::DEPENDENCY_SCHEMA,
                'release_schema' => self::RELEASE_SCHEMA,
                'effort_units' => self::effort_units(),
                'dependency_policies' => self::dependency_policies(),
            ]),
            'permission_callback' => '__return_true',
        ]);
    }

    public function render_analytics_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => __('Knowledge Planning Analytics', 'sustainable-catalyst-library'),
            'intro' => __('Aggregate public progress across planned knowledge, active development, release windows, and publication completion.', 'sustainable-catalyst-library'),
            'collection' => '',
        ], $atts, 'sc_library_planning_analytics');
        $analytics = $this->analytics_data(true, sanitize_title((string) $atts['collection']));
        $analytics_title = sanitize_text_field((string) $atts['title']);
        $analytics_intro = sanitize_text_field((string) $atts['intro']);
        wp_enqueue_style('sc-library-planning-analytics', SC_LIBRARY_URL . 'assets/css/sc-library-planning-analytics.css', [], SC_LIBRARY_VERSION);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-planning-analytics.php';
        return (string) ob_get_clean();
    }

    public function render_release_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => __('Public Release Roadmap', 'sustainable-catalyst-library'),
            'intro' => __('Expected release windows are planning targets and may change as research, validation, and documentation develop.', 'sustainable-catalyst-library'),
            'collection' => '',
        ], $atts, 'sc_library_release_coordination');
        $release = $this->release_data(true, sanitize_title((string) $atts['collection']));
        $release_title = sanitize_text_field((string) $atts['title']);
        $release_intro = sanitize_text_field((string) $atts['intro']);
        wp_enqueue_style('sc-library-planning-analytics', SC_LIBRARY_URL . 'assets/css/sc-library-planning-analytics.css', [], SC_LIBRARY_VERSION);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-release-coordination.php';
        return (string) ob_get_clean();
    }

    public function handle_export(): void {
        check_admin_referer('sc_library_export_planning_analytics');
        if (!current_user_can('edit_posts')) wp_die(__('You cannot export planning analytics.', 'sustainable-catalyst-library'));
        $format = sanitize_key((string) ($_GET['format'] ?? 'json'));
        $analytics = $this->analytics_data(false);
        $release = $this->release_data(false);
        $graph = $this->dependency_graph(null, false);
        $stamp = gmdate('Ymd-His');
        if ($format === 'csv') {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="sustainable-catalyst-planning-analytics-' . $stamp . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['dimension', 'label', 'count', 'active', 'completed', 'blocked', 'overdue', 'estimated_effort', 'average_progress']);
            foreach (['by_area' => 'area', 'by_product' => 'product', 'by_responsible' => 'responsible', 'by_status' => 'status', 'by_content_type' => 'content_type', 'by_release_group' => 'release_group'] as $key => $dimension) {
                foreach ($analytics[$key] as $row) fputcsv($out, [$dimension, $row['label'], $row['count'], $row['active'], $row['completed'], $row['blocked'], $row['overdue'], $row['effort_display'], $row['average_progress']]);
            }
            fclose($out);
            exit;
        }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sustainable-catalyst-planning-analytics-' . $stamp . '.json"');
        echo wp_json_encode(['schema' => self::ANALYTICS_SCHEMA, 'generated_at' => gmdate('c'), 'analytics' => $analytics, 'releases' => $release, 'dependencies' => $graph], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
