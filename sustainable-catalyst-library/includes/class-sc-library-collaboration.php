<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Collaboration, review, and editorial workflow service.
 *
 * WordPress remains the identity and permission authority. Review records are
 * application metadata and never replace canonical post revisions or workspace
 * revisions.
 */
final class SC_Library_Collaboration {
    public const SCHEMA = 'sc-library-editorial-workflow/1.0';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';
    public const LOCK_TTL = 900;

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_menu', [$this, 'admin_menu'], 30);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_shortcode('sc_library_editorial_workflow', [$this, 'render_shortcode']);
        add_action('template_redirect', [$this, 'maybe_accept_invitation']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_collaboration', 1);
    }


    public function register_settings(): void {
        register_setting('sc_library_collaboration_settings', 'sc_library_enable_collaboration', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_collaboration_settings', 'sc_library_review_lock_minutes', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(120, max(5, absint($value))),
            'default' => 15,
        ]);
        register_setting('sc_library_collaboration_settings', 'sc_library_review_invitation_days', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(90, max(1, absint($value))),
            'default' => 14,
        ]);
    }

    public static function lock_ttl(): int {
        return max(300, min(7200, (int) get_option('sc_library_review_lock_minutes', 15) * MINUTE_IN_SECONDS));
    }

    public static function invitation_ttl(): int {
        return max(DAY_IN_SECONDS, min(90 * DAY_IN_SECONDS, (int) get_option('sc_library_review_invitation_days', 14) * DAY_IN_SECONDS));
    }

    public static function reviews_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_reviews';
    }

    public static function participants_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_review_participants';
    }

    public static function comments_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_review_comments';
    }

    public static function suggestions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_review_suggestions';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_review_events';
    }

    public static function statuses(): array {
        return [
            'intake' => __('Intake', 'sustainable-catalyst-library'),
            'drafting' => __('Drafting', 'sustainable-catalyst-library'),
            'internal_review' => __('Internal review', 'sustainable-catalyst-library'),
            'author_revision' => __('Author revision', 'sustainable-catalyst-library'),
            'fact_check' => __('Fact check', 'sustainable-catalyst-library'),
            'accessibility_review' => __('Accessibility review', 'sustainable-catalyst-library'),
            'approval_pending' => __('Approval pending', 'sustainable-catalyst-library'),
            'changes_requested' => __('Changes requested', 'sustainable-catalyst-library'),
            'approved' => __('Approved', 'sustainable-catalyst-library'),
            'scheduled' => __('Scheduled', 'sustainable-catalyst-library'),
            'published' => __('Published', 'sustainable-catalyst-library'),
            'on_hold' => __('On hold', 'sustainable-catalyst-library'),
            'archived' => __('Archived', 'sustainable-catalyst-library'),
        ];
    }

    public static function roles(): array {
        return [
            'observer' => __('Observer', 'sustainable-catalyst-library'),
            'reviewer' => __('Reviewer', 'sustainable-catalyst-library'),
            'editor' => __('Editor', 'sustainable-catalyst-library'),
            'approver' => __('Approver', 'sustainable-catalyst-library'),
        ];
    }

    public static function subject_types(): array {
        return [
            'post' => __('WordPress record', 'sustainable-catalyst-library'),
            'workspace' => __('Research workspace', 'sustainable-catalyst-library'),
            'book' => __('Custom book', 'sustainable-catalyst-library'),
            'board' => __('Whiteboard or Chalkboard', 'sustainable-catalyst-library'),
            'document' => __('Document or PDF edition', 'sustainable-catalyst-library'),
            'media' => __('Multimedia asset, clip, or reel', 'sustainable-catalyst-library'),
            'plan' => __('Content plan', 'sustainable-catalyst-library'),
            'other' => __('Other editorial object', 'sustainable-catalyst-library'),
        ];
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Editorial Workflow', 'sustainable-catalyst-library'),
            __('Editorial Workflow', 'sustainable-catalyst-library'),
            'edit_posts',
            'sc-library-editorial-workflow',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-editorial-workflow')) {
            return;
        }
        $this->enqueue_assets();
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) {
            return;
        }
        wp_enqueue_style('sc-library-collaboration', SC_LIBRARY_URL . 'assets/css/sc-library-collaboration.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-collaboration', SC_LIBRARY_URL . 'assets/js/sc-library-collaboration.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-collaboration', 'SCLibraryCollaboration', [
            'enabled' => true,
            'loggedIn' => is_user_logged_in(),
            'restRoot' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/editorial/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'loginUrl' => wp_login_url((string) ($_SERVER['REQUEST_URI'] ?? home_url('/'))),
            'schema' => self::SCHEMA,
            'statuses' => self::statuses(),
            'roles' => self::roles(),
            'subjectTypes' => self::subject_types(),
            'inviteToken' => isset($_GET['sc_library_review_invite']) ? sanitize_text_field(wp_unslash((string) $_GET['sc_library_review_invite'])) : '',
            'strings' => [
                'loading' => __('Loading editorial workflow…', 'sustainable-catalyst-library'),
                'empty' => __('No editorial review records are available.', 'sustainable-catalyst-library'),
                'error' => __('The editorial workflow could not be loaded.', 'sustainable-catalyst-library'),
                'saved' => __('Editorial review saved.', 'sustainable-catalyst-library'),
                'commented' => __('Comment added.', 'sustainable-catalyst-library'),
                'suggested' => __('Suggested edit recorded.', 'sustainable-catalyst-library'),
                'locked' => __('Editing lock acquired.', 'sustainable-catalyst-library'),
                'conflict' => __('Another editor changed this review. Reload before saving.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access editorial workflow.', 'sustainable-catalyst-library'));
        }
        global $wpdb;
        $reviews = self::reviews_table();
        $comments = self::comments_table();
        $suggestions = self::suggestions_table();
        $metrics = [
            'Open reviews' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reviews} WHERE status NOT IN ('published','archived')"),
            'Approval pending' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$reviews} WHERE status = %s", 'approval_pending')),
            'Open comments' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$comments} WHERE status = %s", 'open')),
            'Pending suggestions' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$suggestions} WHERE status = %s", 'pending')),
        ];
        ?>
        <div class="wrap sc-library-editorial-admin">
            <h1><?php esc_html_e('Collaboration, Review, and Editorial Workflow', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Coordinate reviews, comments, suggested edits, approvals, record locks, revision history, and contributor attribution while WordPress remains the canonical publishing system.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-editorial-metrics">
                <?php foreach ($metrics as $label => $value) : ?>
                    <article><strong><?php echo esc_html(number_format_i18n($value)); ?></strong><span><?php echo esc_html($label); ?></span></article>
                <?php endforeach; ?>
            </div>
            <div class="card sc-library-editorial-settings">
                <h2><?php esc_html_e('Workflow settings', 'sustainable-catalyst-library'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('sc_library_collaboration_settings'); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Editorial workflow', 'sustainable-catalyst-library'); ?></th><td><input type="hidden" name="sc_library_enable_collaboration" value="0"><label><input type="checkbox" name="sc_library_enable_collaboration" value="1" <?php checked(self::enabled()); ?>> <?php esc_html_e('Enable collaboration, reviews, comments, suggestions, and approvals.', 'sustainable-catalyst-library'); ?></label></td></tr>
                        <tr><th scope="row"><label for="sc-library-lock-minutes"><?php esc_html_e('Edit-lock duration', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-library-lock-minutes" name="sc_library_review_lock_minutes" type="number" min="5" max="120" value="<?php echo esc_attr((string) get_option('sc_library_review_lock_minutes', 15)); ?>"> <?php esc_html_e('minutes', 'sustainable-catalyst-library'); ?></td></tr>
                        <tr><th scope="row"><label for="sc-library-invite-days"><?php esc_html_e('Invitation lifetime', 'sustainable-catalyst-library'); ?></label></th><td><input id="sc-library-invite-days" name="sc_library_review_invitation_days" type="number" min="1" max="90" value="<?php echo esc_attr((string) get_option('sc_library_review_invitation_days', 14)); ?>"> <?php esc_html_e('days', 'sustainable-catalyst-library'); ?></td></tr>
                    </table>
                    <?php submit_button(__('Save editorial settings', 'sustainable-catalyst-library')); ?>
                </form>
            </div>
            <div class="sc-library-editorial-shell" data-sc-library-editorial data-admin="true"></div>
            <noscript><p><?php esc_html_e('JavaScript is required for the interactive editorial dashboard.', 'sustainable-catalyst-library'); ?></p></noscript>
        </div>
        <?php
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '';
        }
        self::enqueue_assets();
        $atts = shortcode_atts([
            'review' => '',
            'mode' => 'workspace',
        ], $atts, 'sc_library_editorial_workflow');
        $review = sanitize_text_field((string) $atts['review']);
        $mode = in_array((string) $atts['mode'], ['workspace', 'compact'], true) ? (string) $atts['mode'] : 'workspace';
        ob_start();
        ?>
        <section class="sc-library-editorial-public sc-library-editorial-public--<?php echo esc_attr($mode); ?>">
            <header>
                <p class="sc-library-editorial-kicker"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
                <h2><?php esc_html_e('Editorial Workflow', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Review shared research, resolve comments, propose edits, record approvals, and preserve an attributed editorial history.', 'sustainable-catalyst-library'); ?></p>
            </header>
            <?php if (!is_user_logged_in()) : ?>
                <?php $public_row = $review !== '' ? $this->review_row($review) : null; ?>
                <?php if ($public_row && (string) $public_row['visibility'] === 'public') : ?>
                    <article class="sc-library-editorial-card sc-library-editorial-public-summary">
                        <div class="sc-library-editorial-card__meta"><span class="sc-library-editorial-badge"><?php echo esc_html((string) (self::statuses()[$public_row['status']] ?? $public_row['status'])); ?></span><span><?php echo esc_html((string) $public_row['priority']); ?></span></div>
                        <h3><?php echo esc_html((string) $public_row['title']); ?></h3>
                        <p><?php echo esc_html((string) $public_row['summary']); ?></p>
                        <p><strong><?php esc_html_e('Updated:', 'sustainable-catalyst-library'); ?></strong> <?php echo esc_html((string) $this->rfc3339((string) $public_row['updated_at'])); ?></p>
                    </article>
                <?php else : ?>
                    <p><a class="sc-library-editorial-button" href="<?php echo esc_url(wp_login_url((string) ($_SERVER['REQUEST_URI'] ?? home_url('/')))); ?>"><?php esc_html_e('Sign in to open editorial workflow', 'sustainable-catalyst-library'); ?></a></p>
                <?php endif; ?>
            <?php else : ?>
                <div class="sc-library-editorial-shell" data-sc-library-editorial data-review="<?php echo esc_attr($review); ?>"></div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        $ns = self::REST_NAMESPACE;
        register_rest_route($ns, '/library/editorial/schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'schema'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_reviews'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_review'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_review'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_review'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_review'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'transition_review'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/comments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_comments'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_comment'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/editorial/comments/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_comment'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/suggestions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_suggestions'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_suggestion'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/editorial/suggestions/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_suggestion'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/participants', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_participants'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'invite_participant'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove_participant'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/lock', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'acquire_lock'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/unlock', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'release_lock'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/activity', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'activity'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/reviews/(?P<uuid>[a-f0-9-]{36})/attribution', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'attribution_manifest'],
            'permission_callback' => [$this, 'require_login'],
        ]);
        register_rest_route($ns, '/library/editorial/public/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'public_review'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/library/editorial/invitations/accept', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'accept_invitation'],
            'permission_callback' => [$this, 'require_login'],
        ]);
    }

    public function require_login(): bool|WP_Error {
        if (!self::enabled()) {
            return new WP_Error('sc_library_collaboration_disabled', __('Editorial workflow is disabled.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        return is_user_logged_in() ? true : new WP_Error('rest_not_logged_in', __('Sign in to use editorial workflow.', 'sustainable-catalyst-library'), ['status' => 401]);
    }

    public function schema(): WP_REST_Response {
        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'statuses' => self::statuses(),
            'roles' => self::roles(),
            'subject_types' => self::subject_types(),
            'lock_ttl_seconds' => self::lock_ttl(),
        ]);
    }

    public function list_reviews(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $user_id = get_current_user_id();
        $reviews = self::reviews_table();
        $participants = self::participants_table();
        $status = sanitize_key((string) $request->get_param('status'));
        $where = 'WHERE (r.owner_user_id = %d OR r.assignee_user_id = %d OR p.user_id = %d OR %d = 1)';
        $args = [$user_id, $user_id, $user_id, current_user_can('manage_options') ? 1 : 0];
        if ($status !== '' && isset(self::statuses()[$status])) {
            $where .= ' AND r.status = %s';
            $args[] = $status;
        }
        $sql = "SELECT DISTINCT r.* FROM {$reviews} r LEFT JOIN {$participants} p ON p.review_id = r.id {$where} ORDER BY r.updated_at DESC LIMIT 200";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'items' => array_map(fn(array $row): array => $this->review_record($row), $rows),
        ]);
    }

    public function create_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        if (!current_user_can('edit_posts')) {
            return $this->forbidden();
        }
        $payload = $this->sanitize_review_payload($request);
        if ($payload['title'] === '') {
            return new WP_Error('sc_library_review_title_required', __('A review title is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $now = current_time('mysql', true);
        $uuid = wp_generate_uuid4();
        $inserted = $wpdb->insert(self::reviews_table(), [
            'review_uuid' => $uuid,
            'subject_type' => $payload['subject_type'],
            'subject_key' => $payload['subject_key'],
            'post_id' => $payload['post_id'],
            'workspace_uuid' => $payload['workspace_uuid'],
            'owner_user_id' => get_current_user_id(),
            'assignee_user_id' => $payload['assignee_user_id'],
            'title' => $payload['title'],
            'summary' => $payload['summary'],
            'status' => $payload['status'],
            'priority' => $payload['priority'],
            'visibility' => $payload['visibility'],
            'due_at' => $payload['due_at'],
            'current_revision' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%s','%d','%s','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s']);
        if (!$inserted) {
            return new WP_Error('sc_library_review_create_failed', __('The review could not be created.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        $row = $this->review_row($uuid);
        $this->add_owner_participant($row);
        $this->log_event((int) $row['id'], 'review_created', ['status' => $payload['status']]);
        return new WP_REST_Response($this->review_record($row), 201);
    }

    public function get_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) {
            return $this->not_found();
        }
        if (!$this->can_access($row, 'view')) {
            return $this->forbidden();
        }
        $record = $this->review_record($row);
        $record['comments'] = $this->comments_for((int) $row['id']);
        $record['suggestions'] = $this->suggestions_for((int) $row['id']);
        $record['participants'] = $this->participants_for((int) $row['id']);
        $record['activity'] = $this->events_for((int) $row['id'], 50);
        return rest_ensure_response($record);
    }

    public function update_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) {
            return $this->not_found();
        }
        if (!$this->can_access($row, 'edit')) {
            return $this->forbidden();
        }
        $lock_error = $this->lock_error($row);
        if (is_wp_error($lock_error)) {
            return $lock_error;
        }
        $expected = absint($request->get_param('expected_revision'));
        if ($expected > 0 && $expected !== (int) $row['current_revision']) {
            return new WP_Error('sc_library_review_revision_conflict', __('The review changed after it was loaded.', 'sustainable-catalyst-library'), ['status' => 409, 'server' => $this->review_record($row)]);
        }
        $payload = $this->sanitize_review_payload($request, $row);
        $revision = (int) $row['current_revision'] + 1;
        $updated = $wpdb->update(self::reviews_table(), [
            'subject_type' => $payload['subject_type'],
            'subject_key' => $payload['subject_key'],
            'post_id' => $payload['post_id'],
            'workspace_uuid' => $payload['workspace_uuid'],
            'assignee_user_id' => $payload['assignee_user_id'],
            'title' => $payload['title'],
            'summary' => $payload['summary'],
            'priority' => $payload['priority'],
            'visibility' => $payload['visibility'],
            'due_at' => $payload['due_at'],
            'current_revision' => $revision,
            'updated_at' => current_time('mysql', true),
        ], ['review_uuid' => (string) $row['review_uuid']], ['%s','%s','%d','%s','%d','%s','%s','%s','%s','%s','%d','%s'], ['%s']);
        if ($updated === false) {
            return new WP_Error('sc_library_review_update_failed', __('The review could not be updated.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        $this->log_event((int) $row['id'], 'review_updated', ['revision' => $revision]);
        return rest_ensure_response($this->review_record($this->review_row((string) $row['review_uuid'])));
    }

    public function delete_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) {
            return $this->not_found();
        }
        if (!$this->is_owner($row) && !current_user_can('manage_options')) {
            return $this->forbidden();
        }
        foreach ([self::comments_table(), self::suggestions_table(), self::participants_table(), self::events_table()] as $table) {
            $wpdb->delete($table, ['review_id' => (int) $row['id']], ['%d']);
        }
        $wpdb->delete(self::reviews_table(), ['id' => (int) $row['id']], ['%d']);
        return rest_ensure_response(['deleted' => true, 'review_uuid' => (string) $row['review_uuid']]);
    }

    public function transition_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) {
            return $this->not_found();
        }
        $status = sanitize_key((string) $request->get_param('status'));
        if (!isset(self::statuses()[$status])) {
            return new WP_Error('sc_library_invalid_review_status', __('That review status is not valid.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $action = in_array($status, ['approved','scheduled','published'], true) ? 'approve' : 'edit';
        if (!$this->can_access($row, $action)) {
            return $this->forbidden();
        }
        $note = sanitize_textarea_field((string) $request->get_param('note'));
        $revision = (int) $row['current_revision'] + 1;
        $completed = in_array($status, ['published','archived'], true) ? current_time('mysql', true) : null;
        $wpdb->update(self::reviews_table(), [
            'status' => $status,
            'decision_note' => $note,
            'current_revision' => $revision,
            'updated_at' => current_time('mysql', true),
            'completed_at' => $completed,
        ], ['id' => (int) $row['id']], ['%s','%s','%d','%s','%s'], ['%d']);
        $this->log_event((int) $row['id'], 'status_changed', ['from' => (string) $row['status'], 'to' => $status, 'note' => $note]);
        do_action('sc_library_review_transitioned', [
            'review_uuid' => (string) $row['review_uuid'],
            'title' => (string) $row['title'],
            'from_status' => (string) $row['status'],
            'status' => $status,
            'object_type' => (string) $row['object_type'],
            'object_identifier' => (string) $row['object_identifier'],
            'visibility' => (string) $row['visibility'],
            'revision' => $revision,
        ]);
        return rest_ensure_response($this->review_record($this->review_row((string) $row['review_uuid'])));
    }

    public function list_comments(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'view')) return $this->forbidden();
        return rest_ensure_response(['schema' => self::SCHEMA, 'items' => $this->comments_for((int) $row['id'])]);
    }

    public function create_comment(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'comment')) return $this->forbidden();
        $body = wp_kses_post((string) $request->get_param('body'));
        if (trim(wp_strip_all_tags($body)) === '') {
            return new WP_Error('sc_library_comment_required', __('A comment is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $anchor = $request->get_param('anchor');
        $anchor = is_array($anchor) ? $this->sanitize_recursive($anchor) : [];
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $wpdb->insert(self::comments_table(), [
            'comment_uuid' => $uuid,
            'review_id' => (int) $row['id'],
            'parent_id' => absint($request->get_param('parent_id')),
            'user_id' => get_current_user_id(),
            'body' => $body,
            'status' => 'open',
            'anchor_json' => wp_json_encode($anchor),
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%d','%d','%d','%s','%s','%s','%s','%s']);
        $this->log_event((int) $row['id'], 'comment_added', ['comment_uuid' => $uuid]);
        return new WP_REST_Response(['comment_uuid' => $uuid, 'body' => $body, 'status' => 'open'], 201);
    }

    public function update_comment(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $comment = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::comments_table() . ' WHERE comment_uuid = %s', (string) $request['uuid']), ARRAY_A);
        if (!$comment) return $this->not_found();
        $review = $this->review_row_by_id((int) $comment['review_id']);
        if (!$review || !$this->can_access($review, 'comment')) return $this->forbidden();
        $status = sanitize_key((string) $request->get_param('status'));
        if (!in_array($status, ['open','resolved'], true)) $status = (string) $comment['status'];
        $body = $request->has_param('body') ? wp_kses_post((string) $request->get_param('body')) : (string) $comment['body'];
        $resolved = $status === 'resolved';
        $wpdb->update(self::comments_table(), [
            'body' => $body,
            'status' => $status,
            'updated_at' => current_time('mysql', true),
            'resolved_by' => $resolved ? get_current_user_id() : 0,
            'resolved_at' => $resolved ? current_time('mysql', true) : null,
        ], ['id' => (int) $comment['id']], ['%s','%s','%s','%d','%s'], ['%d']);
        $this->log_event((int) $review['id'], $resolved ? 'comment_resolved' : 'comment_updated', ['comment_uuid' => (string) $comment['comment_uuid']]);
        return rest_ensure_response(['updated' => true, 'comment_uuid' => (string) $comment['comment_uuid'], 'status' => $status]);
    }

    public function list_suggestions(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'view')) return $this->forbidden();
        return rest_ensure_response(['schema' => self::SCHEMA, 'items' => $this->suggestions_for((int) $row['id'])]);
    }

    public function create_suggestion(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'suggest')) return $this->forbidden();
        $proposed = wp_kses_post((string) $request->get_param('proposed_text'));
        if (trim(wp_strip_all_tags($proposed)) === '') {
            return new WP_Error('sc_library_suggestion_required', __('Proposed text is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $wpdb->insert(self::suggestions_table(), [
            'suggestion_uuid' => $uuid,
            'review_id' => (int) $row['id'],
            'user_id' => get_current_user_id(),
            'suggestion_type' => sanitize_key((string) ($request->get_param('suggestion_type') ?: 'replace')),
            'field_key' => sanitize_key((string) ($request->get_param('field_key') ?: 'content')),
            'original_text' => wp_kses_post((string) $request->get_param('original_text')),
            'proposed_text' => $proposed,
            'rationale' => sanitize_textarea_field((string) $request->get_param('rationale')),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s']);
        $this->log_event((int) $row['id'], 'suggestion_added', ['suggestion_uuid' => $uuid]);
        return new WP_REST_Response(['suggestion_uuid' => $uuid, 'status' => 'pending'], 201);
    }

    public function update_suggestion(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $suggestion = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::suggestions_table() . ' WHERE suggestion_uuid = %s', (string) $request['uuid']), ARRAY_A);
        if (!$suggestion) return $this->not_found();
        $review = $this->review_row_by_id((int) $suggestion['review_id']);
        if (!$review) return $this->not_found();
        $status = sanitize_key((string) $request->get_param('status'));
        if (!in_array($status, ['pending','accepted','rejected','withdrawn'], true)) {
            return new WP_Error('sc_library_suggestion_status_invalid', __('That suggestion state is not valid.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $is_author = (int) $suggestion['user_id'] === get_current_user_id();
        if ($status === 'withdrawn') {
            if (!$is_author && !$this->can_access($review, 'edit')) return $this->forbidden();
        } elseif (!$this->can_access($review, 'edit')) {
            return $this->forbidden();
        }
        $decided = in_array($status, ['accepted','rejected'], true);
        $wpdb->update(self::suggestions_table(), [
            'status' => $status,
            'decision_note' => sanitize_textarea_field((string) $request->get_param('decision_note')),
            'decided_by' => $decided ? get_current_user_id() : 0,
            'decided_at' => $decided ? current_time('mysql', true) : null,
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $suggestion['id']], ['%s','%s','%d','%s','%s'], ['%d']);
        $this->log_event((int) $review['id'], 'suggestion_' . $status, ['suggestion_uuid' => (string) $suggestion['suggestion_uuid']]);
        return rest_ensure_response(['updated' => true, 'suggestion_uuid' => (string) $suggestion['suggestion_uuid'], 'status' => $status]);
    }

    public function list_participants(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'view')) return $this->forbidden();
        return rest_ensure_response(['schema' => self::SCHEMA, 'items' => $this->participants_for((int) $row['id'])]);
    }

    public function invite_participant(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->is_owner($row) && !$this->has_role($row, ['editor','approver']) && !current_user_can('manage_options')) return $this->forbidden();
        $email = sanitize_email((string) $request->get_param('email'));
        $role = sanitize_key((string) $request->get_param('role'));
        if (!is_email($email)) return new WP_Error('sc_library_invitation_email_invalid', __('A valid email address is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        if (!isset(self::roles()[$role])) $role = 'reviewer';
        $user = get_user_by('email', $email);
        if ($user && (int) $user->ID === (int) $row['owner_user_id']) return new WP_Error('sc_library_invitation_owner', __('The review owner already has full access.', 'sustainable-catalyst-library'), ['status' => 400]);
        $token = wp_generate_password(48, false, false);
        $token_hash = hash('sha256', $token);
        $now = current_time('mysql', true);
        $expires = gmdate('Y-m-d H:i:s', time() + self::invitation_ttl());
        $wpdb->replace(self::participants_table(), [
            'review_id' => (int) $row['id'],
            'user_id' => $user ? (int) $user->ID : 0,
            'email' => $email,
            'role' => $role,
            'status' => $user ? 'accepted' : 'invited',
            'invited_by' => get_current_user_id(),
            'token_hash' => $user ? '' : $token_hash,
            'expires_at' => $user ? null : $expires,
            'accepted_at' => $user ? $now : null,
            'created_at' => $now,
        ], ['%d','%d','%s','%s','%s','%d','%s','%s','%s','%s']);
        if ($user) {
            $this->sync_workspace_collaborator($row, (int) $user->ID, $role);
        } else {
            $url = add_query_arg('sc_library_review_invite', rawurlencode($token), (string) get_option('sc_library_main_page_url', home_url('/research-library/')));
            wp_mail($email, sprintf(__('Invitation: %s', 'sustainable-catalyst-library'), (string) $row['title']), sprintf("%s\n\n%s", __('You were invited to a Sustainable Catalyst Library editorial review.', 'sustainable-catalyst-library'), $url));
        }
        $this->log_event((int) $row['id'], 'participant_invited', ['email' => $email, 'role' => $role, 'existing_user' => (bool) $user]);
        return new WP_REST_Response(['invited' => true, 'email' => $email, 'role' => $role, 'accepted' => (bool) $user], 201);
    }

    public function remove_participant(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->is_owner($row) && !current_user_can('manage_options')) return $this->forbidden();
        $participant_id = absint($request->get_param('participant_id'));
        $participant = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::participants_table() . ' WHERE id = %d AND review_id = %d', $participant_id, (int) $row['id']), ARRAY_A);
        if (!$participant) return $this->not_found();
        if ((int) $participant['user_id'] === (int) $row['owner_user_id']) return new WP_Error('sc_library_owner_remove_forbidden', __('The review owner cannot be removed.', 'sustainable-catalyst-library'), ['status' => 400]);
        $wpdb->delete(self::participants_table(), ['id' => $participant_id], ['%d']);
        $this->log_event((int) $row['id'], 'participant_removed', ['participant_id' => $participant_id]);
        return rest_ensure_response(['removed' => true, 'participant_id' => $participant_id]);
    }

    public function accept_invitation(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ($token === '') return new WP_Error('sc_library_invitation_token_required', __('An invitation token is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        $hash = hash('sha256', $token);
        $participant = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::participants_table() . ' WHERE token_hash = %s AND status = %s', $hash, 'invited'), ARRAY_A);
        if (!$participant) return new WP_Error('sc_library_invitation_invalid', __('This invitation is invalid or has already been used.', 'sustainable-catalyst-library'), ['status' => 404]);
        if (!empty($participant['expires_at']) && strtotime((string) $participant['expires_at'] . ' UTC') < time()) return new WP_Error('sc_library_invitation_expired', __('This invitation has expired.', 'sustainable-catalyst-library'), ['status' => 410]);
        $user = wp_get_current_user();
        if (strtolower((string) $user->user_email) !== strtolower((string) $participant['email']) && !current_user_can('manage_options')) {
            return new WP_Error('sc_library_invitation_email_mismatch', __('Sign in with the email address that received the invitation.', 'sustainable-catalyst-library'), ['status' => 403]);
        }
        $wpdb->update(self::participants_table(), [
            'user_id' => (int) $user->ID,
            'status' => 'accepted',
            'token_hash' => '',
            'accepted_at' => current_time('mysql', true),
        ], ['id' => (int) $participant['id']], ['%d','%s','%s','%s'], ['%d']);
        $review = $this->review_row_by_id((int) $participant['review_id']);
        if ($review) {
            $this->sync_workspace_collaborator($review, (int) $user->ID, (string) $participant['role']);
            $this->log_event((int) $review['id'], 'participant_accepted', ['user_id' => (int) $user->ID, 'role' => (string) $participant['role']]);
        }
        return rest_ensure_response(['accepted' => true, 'review_uuid' => $review ? (string) $review['review_uuid'] : '']);
    }

    public function acquire_lock(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'edit')) return $this->forbidden();
        $now = time();
        $locked_by = (int) $row['locked_by'];
        $expires = !empty($row['lock_expires_at']) ? strtotime((string) $row['lock_expires_at'] . ' UTC') : 0;
        if ($locked_by > 0 && $locked_by !== get_current_user_id() && $expires > $now) {
            $user = get_userdata($locked_by);
            return new WP_Error('sc_library_review_locked', sprintf(__('This review is locked by %s.', 'sustainable-catalyst-library'), $user ? $user->display_name : __('another editor', 'sustainable-catalyst-library')), ['status' => 423, 'lock' => $this->lock_record($row)]);
        }
        $expires_at = gmdate('Y-m-d H:i:s', $now + self::lock_ttl());
        $wpdb->update(self::reviews_table(), [
            'locked_by' => get_current_user_id(),
            'locked_at' => current_time('mysql', true),
            'lock_expires_at' => $expires_at,
        ], ['id' => (int) $row['id']], ['%d','%s','%s'], ['%d']);
        $this->log_event((int) $row['id'], 'lock_acquired', ['expires_at' => $expires_at]);
        return rest_ensure_response(['locked' => true, 'lock' => $this->lock_record($this->review_row((string) $row['review_uuid']))]);
    }

    public function release_lock(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if ((int) $row['locked_by'] !== get_current_user_id() && !$this->is_owner($row) && !current_user_can('manage_options')) return $this->forbidden();
        $wpdb->update(self::reviews_table(), ['locked_by' => 0, 'locked_at' => null, 'lock_expires_at' => null], ['id' => (int) $row['id']], ['%d','%s','%s'], ['%d']);
        $this->log_event((int) $row['id'], 'lock_released', []);
        return rest_ensure_response(['locked' => false]);
    }

    public function activity(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'view')) return $this->forbidden();
        return rest_ensure_response(['schema' => self::SCHEMA, 'items' => $this->events_for((int) $row['id'], 200)]);
    }

    public function public_review(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row || (string) $row['visibility'] !== 'public') return $this->not_found();
        $participants = array_values(array_filter($this->participants_for((int) $row['id']), static fn(array $item): bool => $item['status'] === 'accepted'));
        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'review_uuid' => (string) $row['review_uuid'],
            'title' => (string) $row['title'],
            'summary' => (string) $row['summary'],
            'subject_type' => (string) $row['subject_type'],
            'subject_key' => (string) $row['subject_key'],
            'status' => (string) $row['status'],
            'status_label' => (string) (self::statuses()[$row['status']] ?? $row['status']),
            'priority' => (string) $row['priority'],
            'due_at' => $this->rfc3339((string) $row['due_at']),
            'contributors' => array_map(static fn(array $item): array => ['name' => $item['name'], 'role' => $item['role_label']], $participants),
            'updated_at' => $this->rfc3339((string) $row['updated_at']),
        ]);
    }

    public function attribution_manifest(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->review_row((string) $request['uuid']);
        if (!$row) return $this->not_found();
        if (!$this->can_access($row, 'view')) return $this->forbidden();
        $participants = $this->participants_for((int) $row['id']);
        $events = $this->events_for((int) $row['id'], 500);
        $counts = [];
        foreach ($events as $event) {
            $id = (int) ($event['actor']['id'] ?? 0);
            if ($id <= 0) continue;
            if (!isset($counts[$id])) $counts[$id] = ['user_id' => $id, 'name' => (string) ($event['actor']['name'] ?? ''), 'events' => 0, 'event_types' => []];
            $counts[$id]['events']++;
            $type = (string) $event['event_type'];
            $counts[$id]['event_types'][$type] = ($counts[$id]['event_types'][$type] ?? 0) + 1;
        }
        return rest_ensure_response([
            'schema' => self::SCHEMA,
            'review_uuid' => (string) $row['review_uuid'],
            'revision' => (int) $row['current_revision'],
            'participants' => $participants,
            'contributors' => array_values($counts),
            'generated_at' => gmdate('c'),
        ]);
    }

    public function maybe_accept_invitation(): void {
        // Acceptance is handled explicitly by the authenticated REST client. This
        // hook exists to avoid WordPress canonical redirects stripping the token.
        if (isset($_GET['sc_library_review_invite'])) {
            nocache_headers();
        }
    }

    private function sanitize_review_payload(WP_REST_Request $request, array $existing = []): array {
        $subject_type = sanitize_key((string) ($request->get_param('subject_type') ?? ($existing['subject_type'] ?? 'other')));
        if (!isset(self::subject_types()[$subject_type])) $subject_type = 'other';
        $status = sanitize_key((string) ($request->get_param('status') ?? ($existing['status'] ?? 'intake')));
        if (!isset(self::statuses()[$status])) $status = 'intake';
        $priority = sanitize_key((string) ($request->get_param('priority') ?? ($existing['priority'] ?? 'normal')));
        if (!in_array($priority, ['low','normal','high','urgent'], true)) $priority = 'normal';
        $visibility = sanitize_key((string) ($request->get_param('visibility') ?? ($existing['visibility'] ?? 'private')));
        if (!in_array($visibility, ['private','participants','organization','public'], true)) $visibility = 'private';
        $due = sanitize_text_field((string) ($request->get_param('due_at') ?? ($existing['due_at'] ?? '')));
        if ($due !== '' && strtotime($due) === false) $due = '';
        return [
            'subject_type' => $subject_type,
            'subject_key' => sanitize_text_field((string) ($request->get_param('subject_key') ?? ($existing['subject_key'] ?? ''))),
            'post_id' => absint($request->get_param('post_id') ?? ($existing['post_id'] ?? 0)),
            'workspace_uuid' => sanitize_text_field((string) ($request->get_param('workspace_uuid') ?? ($existing['workspace_uuid'] ?? ''))),
            'assignee_user_id' => absint($request->get_param('assignee_user_id') ?? ($existing['assignee_user_id'] ?? 0)),
            'title' => sanitize_text_field((string) ($request->get_param('title') ?? ($existing['title'] ?? ''))),
            'summary' => sanitize_textarea_field((string) ($request->get_param('summary') ?? ($existing['summary'] ?? ''))),
            'status' => $status,
            'priority' => $priority,
            'visibility' => $visibility,
            'due_at' => $due !== '' ? gmdate('Y-m-d H:i:s', strtotime($due)) : null,
        ];
    }

    private function review_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::reviews_table() . ' WHERE review_uuid = %s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function review_row_by_id(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::reviews_table() . ' WHERE id = %d', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function review_record(array $row): array {
        $owner = get_userdata((int) $row['owner_user_id']);
        $assignee = get_userdata((int) $row['assignee_user_id']);
        return [
            'schema' => self::SCHEMA,
            'review_uuid' => (string) $row['review_uuid'],
            'subject_type' => (string) $row['subject_type'],
            'subject_key' => (string) $row['subject_key'],
            'post_id' => (int) $row['post_id'],
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'title' => (string) $row['title'],
            'summary' => (string) $row['summary'],
            'status' => (string) $row['status'],
            'status_label' => (string) (self::statuses()[$row['status']] ?? $row['status']),
            'priority' => (string) $row['priority'],
            'visibility' => (string) $row['visibility'],
            'due_at' => $this->rfc3339((string) $row['due_at']),
            'owner' => ['id' => (int) $row['owner_user_id'], 'name' => $owner ? (string) $owner->display_name : ''],
            'assignee' => (int) $row['assignee_user_id'] > 0 ? ['id' => (int) $row['assignee_user_id'], 'name' => $assignee ? (string) $assignee->display_name : ''] : null,
            'revision' => (int) $row['current_revision'],
            'decision_note' => (string) $row['decision_note'],
            'lock' => $this->lock_record($row),
            'permissions' => [
                'view' => $this->can_access($row, 'view'),
                'comment' => $this->can_access($row, 'comment'),
                'suggest' => $this->can_access($row, 'suggest'),
                'edit' => $this->can_access($row, 'edit'),
                'approve' => $this->can_access($row, 'approve'),
                'manage' => $this->is_owner($row) || current_user_can('manage_options'),
            ],
            'created_at' => $this->rfc3339((string) $row['created_at']),
            'updated_at' => $this->rfc3339((string) $row['updated_at']),
            'completed_at' => $this->rfc3339((string) $row['completed_at']),
        ];
    }

    private function comments_for(int $review_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT c.*, u.display_name FROM ' . self::comments_table() . ' c LEFT JOIN ' . $wpdb->users . ' u ON u.ID = c.user_id WHERE c.review_id = %d ORDER BY c.created_at ASC', $review_id), ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'comment_uuid' => (string) $row['comment_uuid'],
                'parent_id' => (int) $row['parent_id'],
                'author' => ['id' => (int) $row['user_id'], 'name' => (string) ($row['display_name'] ?? '')],
                'body' => wp_kses_post((string) $row['body']),
                'status' => (string) $row['status'],
                'anchor' => json_decode((string) $row['anchor_json'], true) ?: [],
                'created_at' => $this->rfc3339((string) $row['created_at']),
                'updated_at' => $this->rfc3339((string) $row['updated_at']),
            ];
        }, $rows);
    }

    private function suggestions_for(int $review_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT s.*, u.display_name FROM ' . self::suggestions_table() . ' s LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.user_id WHERE s.review_id = %d ORDER BY s.created_at DESC', $review_id), ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'suggestion_uuid' => (string) $row['suggestion_uuid'],
                'author' => ['id' => (int) $row['user_id'], 'name' => (string) ($row['display_name'] ?? '')],
                'suggestion_type' => (string) $row['suggestion_type'],
                'field_key' => (string) $row['field_key'],
                'original_text' => wp_kses_post((string) $row['original_text']),
                'proposed_text' => wp_kses_post((string) $row['proposed_text']),
                'rationale' => (string) $row['rationale'],
                'status' => (string) $row['status'],
                'decision_note' => (string) $row['decision_note'],
                'created_at' => $this->rfc3339((string) $row['created_at']),
                'updated_at' => $this->rfc3339((string) $row['updated_at']),
            ];
        }, $rows);
    }

    private function participants_for(int $review_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT p.*, u.display_name FROM ' . self::participants_table() . ' p LEFT JOIN ' . $wpdb->users . ' u ON u.ID = p.user_id WHERE p.review_id = %d ORDER BY p.role, p.created_at', $review_id), ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'participant_id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'name' => (string) ($row['display_name'] ?? ''),
                'email' => (string) $row['email'],
                'role' => (string) $row['role'],
                'role_label' => (string) (self::roles()[$row['role']] ?? $row['role']),
                'status' => (string) $row['status'],
                'accepted_at' => $this->rfc3339((string) $row['accepted_at']),
            ];
        }, $rows);
    }

    private function events_for(int $review_id, int $limit): array {
        global $wpdb;
        $limit = min(500, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT e.*, u.display_name FROM ' . self::events_table() . ' e LEFT JOIN ' . $wpdb->users . " u ON u.ID = e.user_id WHERE e.review_id = %d ORDER BY e.created_at DESC LIMIT {$limit}", $review_id), ARRAY_A) ?: [];
        return array_map(function (array $row): array {
            return [
                'event_type' => (string) $row['event_type'],
                'actor' => ['id' => (int) $row['user_id'], 'name' => (string) ($row['display_name'] ?? '')],
                'payload' => json_decode((string) $row['payload_json'], true) ?: [],
                'created_at' => $this->rfc3339((string) $row['created_at']),
            ];
        }, $rows);
    }

    private function add_owner_participant(array $review): void {
        global $wpdb;
        $user = get_userdata((int) $review['owner_user_id']);
        $wpdb->replace(self::participants_table(), [
            'review_id' => (int) $review['id'],
            'user_id' => (int) $review['owner_user_id'],
            'email' => $user ? (string) $user->user_email : '',
            'role' => 'approver',
            'status' => 'accepted',
            'invited_by' => (int) $review['owner_user_id'],
            'token_hash' => '',
            'expires_at' => null,
            'accepted_at' => current_time('mysql', true),
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s','%s','%d','%s','%s','%s','%s']);
    }

    private function can_access(array $review, string $action): bool {
        if (current_user_can('manage_options')) return true;
        $user_id = get_current_user_id();
        if ($user_id <= 0) return false;
        if ((int) $review['owner_user_id'] === $user_id) return true;
        if ((int) $review['assignee_user_id'] === $user_id && in_array($action, ['view','comment','suggest','edit'], true)) return true;
        if ((string) $review['visibility'] === 'organization' && current_user_can('edit_posts') && in_array($action, ['view','comment'], true)) return true;
        $role = $this->participant_role($review, $user_id);
        if ($role === '') return false;
        return match ($action) {
            'view' => true,
            'comment' => in_array($role, ['observer','reviewer','editor','approver'], true),
            'suggest' => in_array($role, ['reviewer','editor','approver'], true),
            'edit' => in_array($role, ['editor','approver'], true),
            'approve' => $role === 'approver',
            default => false,
        };
    }

    private function participant_role(array $review, int $user_id): string {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SELECT role FROM ' . self::participants_table() . ' WHERE review_id = %d AND user_id = %d AND status = %s', (int) $review['id'], $user_id, 'accepted'));
    }

    private function has_role(array $review, array $roles): bool {
        return in_array($this->participant_role($review, get_current_user_id()), $roles, true);
    }

    private function is_owner(array $review): bool {
        return (int) $review['owner_user_id'] === get_current_user_id();
    }

    private function lock_error(array $review): ?WP_Error {
        $locked_by = (int) $review['locked_by'];
        if ($locked_by <= 0 || $locked_by === get_current_user_id()) return null;
        $expires = !empty($review['lock_expires_at']) ? strtotime((string) $review['lock_expires_at'] . ' UTC') : 0;
        if ($expires <= time()) return null;
        return new WP_Error('sc_library_review_locked', __('Another editor currently holds the review lock.', 'sustainable-catalyst-library'), ['status' => 423, 'lock' => $this->lock_record($review)]);
    }

    private function lock_record(array $row): array {
        $locked_by = (int) ($row['locked_by'] ?? 0);
        $expires = !empty($row['lock_expires_at']) ? strtotime((string) $row['lock_expires_at'] . ' UTC') : 0;
        if ($locked_by <= 0 || $expires <= time()) return ['active' => false];
        $user = get_userdata($locked_by);
        return [
            'active' => true,
            'user_id' => $locked_by,
            'user_name' => $user ? (string) $user->display_name : '',
            'locked_at' => $this->rfc3339((string) $row['locked_at']),
            'expires_at' => $this->rfc3339((string) $row['lock_expires_at']),
            'mine' => $locked_by === get_current_user_id(),
        ];
    }

    private function log_event(int $review_id, string $type, array $payload): void {
        global $wpdb;
        $wpdb->insert(self::events_table(), [
            'review_id' => $review_id,
            'user_id' => get_current_user_id(),
            'event_type' => sanitize_key($type),
            'payload_json' => wp_json_encode($this->sanitize_recursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%s','%s']);
    }

    private function sync_workspace_collaborator(array $review, int $user_id, string $role): void {
        if ((string) $review['subject_type'] !== 'workspace' || (string) $review['workspace_uuid'] === '' || !class_exists('SC_Library_Workspaces')) return;
        global $wpdb;
        $workspace = $wpdb->get_row($wpdb->prepare('SELECT id, owner_user_id FROM ' . SC_Library_Workspaces::table() . ' WHERE workspace_uuid = %s', (string) $review['workspace_uuid']), ARRAY_A);
        if (!$workspace || (int) $workspace['owner_user_id'] === $user_id) return;
        $workspace_role = in_array($role, ['editor','approver'], true) ? 'editor' : 'viewer';
        $wpdb->replace(SC_Library_Workspaces::collaborators_table(), [
            'workspace_id' => (int) $workspace['id'],
            'user_id' => $user_id,
            'role' => $workspace_role,
            'invited_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
            'accepted_at' => current_time('mysql', true),
        ], ['%d','%d','%s','%d','%s','%s']);
    }

    private function sanitize_recursive($value) {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) $clean[sanitize_key((string) $key)] = $this->sanitize_recursive($item);
            return $clean;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
        return sanitize_text_field((string) $value);
    }

    private function rfc3339(string $value): ?string {
        if ($value === '' || $value === '0000-00-00 00:00:00') return null;
        return mysql_to_rfc3339($value);
    }

    private function not_found(): WP_Error {
        return new WP_Error('sc_library_review_not_found', __('The editorial record was not found.', 'sustainable-catalyst-library'), ['status' => 404]);
    }

    private function forbidden(): WP_Error {
        return new WP_Error('sc_library_review_forbidden', __('You do not have permission to perform this editorial action.', 'sustainable-catalyst-library'), ['status' => 403]);
    }
}
