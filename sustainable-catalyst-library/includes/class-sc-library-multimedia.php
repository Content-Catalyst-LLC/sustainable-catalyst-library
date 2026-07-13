<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multimedia Studio, non-destructive clip definitions, evidence reels, and
 * optional Render video processing.
 */
final class SC_Library_Multimedia {
    public const ASSET_SCHEMA = 'sc-library-media-asset/1.0';
    public const CLIP_SCHEMA = 'sc-library-media-clip/1.0';
    public const REEL_SCHEMA = 'sc-library-media-reel/1.0';
    public const JOB_SCHEMA = 'sc-library-media-job/1.0';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('sc_library_refresh_media_job', [$this, 'cron_refresh'], 10, 1);
        add_shortcode('sc_library_multimedia_studio', [$this, 'render_shortcode']);
        add_shortcode('sc_library_evidence_reel', [$this, 'render_reel_shortcode']);
    }

    public static function enabled(): bool {
        return (bool) get_option('sc_library_enable_multimedia', 1);
    }

    public static function assets_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_media_assets';
    }

    public static function clips_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_media_clips';
    }

    public static function reels_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_media_reels';
    }

    public static function jobs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_media_jobs';
    }

    public static function rights_statuses(): array {
        return [
            'owned' => __('Owned by Sustainable Catalyst', 'sustainable-catalyst-library'),
            'licensed' => __('Licensed for this use', 'sustainable-catalyst-library'),
            'permission_granted' => __('Permission granted', 'sustainable-catalyst-library'),
            'public_domain' => __('Public domain', 'sustainable-catalyst-library'),
            'creative_commons' => __('Creative Commons', 'sustainable-catalyst-library'),
            'fair_use_excerpt' => __('Documented fair-use excerpt', 'sustainable-catalyst-library'),
            'unknown' => __('Rights not yet verified', 'sustainable-catalyst-library'),
        ];
    }

    public static function service_url(): string {
        $specific = trim((string) get_option('sc_library_media_service_url', ''));
        if ($specific !== '') {
            return untrailingslashit($specific);
        }
        if (class_exists('SC_Library_Document_Production')) {
            return untrailingslashit(SC_Library_Document_Production::service_url());
        }
        return untrailingslashit((string) get_option('sc_library_render_sync_url', ''));
    }

    public static function api_key(): string {
        $specific = trim((string) get_option('sc_library_media_service_api_key', ''));
        if ($specific !== '') {
            return $specific;
        }
        if (class_exists('SC_Library_Document_Production')) {
            return SC_Library_Document_Production::api_key();
        }
        return trim((string) get_option('sc_library_render_sync_api_key', ''));
    }

    public static function configured(): bool {
        return self::enabled() && self::service_url() !== '' && self::api_key() !== '';
    }

    public function register_settings(): void {
        register_setting('sc_library_settings', 'sc_library_enable_multimedia', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
        register_setting('sc_library_settings', 'sc_library_media_service_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('sc_library_settings', 'sc_library_media_service_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('sc_library_settings', 'sc_library_media_allow_remote_urls', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 0,
        ]);
        register_setting('sc_library_settings', 'sc_library_media_max_source_mb', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(2048, max(10, absint($value))),
            'default' => 500,
        ]);
        register_setting('sc_library_settings', 'sc_library_media_max_clip_minutes', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(240, max(1, absint($value))),
            'default' => 30,
        ]);
        register_setting('sc_library_settings', 'sc_library_media_retention_days', [
            'type' => 'integer',
            'sanitize_callback' => static fn($value) => min(365, max(1, absint($value))),
            'default' => 14,
        ]);
        register_setting('sc_library_settings', 'sc_library_media_auto_import', [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => $value ? 1 : 0,
            'default' => 1,
        ]);
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Multimedia Studio', 'sustainable-catalyst-library'),
            __('Multimedia Studio', 'sustainable-catalyst-library'),
            'edit_posts',
            'sc-library-multimedia',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-multimedia')) {
            return;
        }
        $this->enqueue_assets(true);
        wp_enqueue_media();
    }

    private function enqueue_assets(bool $admin = false): void {
        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-multimedia', SC_LIBRARY_URL . 'assets/css/sc-library-multimedia.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-multimedia', SC_LIBRARY_URL . 'assets/js/sc-library-multimedia.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-multimedia', 'SCMultimediaShared', [
            'version' => SC_LIBRARY_VERSION,
            'assetSchema' => self::ASSET_SCHEMA,
            'clipSchema' => self::CLIP_SCHEMA,
            'reelSchema' => self::REEL_SCHEMA,
            'jobSchema' => self::JOB_SCHEMA,
            'restBase' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/library/media')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'authenticated' => is_user_logged_in(),
            'canEdit' => current_user_can('edit_posts'),
            'canUpload' => current_user_can('upload_files'),
            'admin' => $admin,
            'configured' => self::configured(),
            'allowRemote' => (bool) get_option('sc_library_media_allow_remote_urls', 0),
            'rightsStatuses' => self::rights_statuses(),
            'maxClipMinutes' => (int) get_option('sc_library_media_max_clip_minutes', 30),
            'strings' => [
                'saving' => __('Saving multimedia record…', 'sustainable-catalyst-library'),
                'saved' => __('Multimedia record saved.', 'sustainable-catalyst-library'),
                'processing' => __('Submitting non-destructive clip job…', 'sustainable-catalyst-library'),
                'confirmDelete' => __('Delete this record? Original media in the WordPress Media Library will not be deleted.', 'sustainable-catalyst-library'),
                'rightsRequired' => __('Verify rights and provenance before server processing.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to use the Multimedia Studio.', 'sustainable-catalyst-library'));
        }
        global $wpdb;
        $metrics = [
            __('Media assets', 'sustainable-catalyst-library') => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::assets_table()),
            __('Clip definitions', 'sustainable-catalyst-library') => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::clips_table()),
            __('Evidence reels', 'sustainable-catalyst-library') => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::reels_table()),
            __('Processing jobs', 'sustainable-catalyst-library') => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::jobs_table()),
        ];
        ?>
        <div class="wrap sc-library-multimedia-admin">
            <h1><?php esc_html_e('Multimedia Studio and Video Snippet Production', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Register authorized media, define non-destructive transcript-linked clips, assemble evidence reels, and optionally render portable snippets through the Library service.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-multimedia__metrics">
                <?php foreach ($metrics as $label => $value) : ?>
                    <article><strong><?php echo esc_html(number_format_i18n($value)); ?></strong><span><?php echo esc_html($label); ?></span></article>
                <?php endforeach; ?>
            </div>
            <div class="notice notice-info inline"><p>
                <?php echo self::configured()
                    ? esc_html__('The optional media processor is configured. Original media remains unchanged; completed snippets can be imported into the WordPress Media Library.', 'sustainable-catalyst-library')
                    : esc_html__('The optional media processor is not configured. Asset, transcript, clip, annotation, and reel planning remain fully available in WordPress.', 'sustainable-catalyst-library'); ?>
            </p></div>
            <div data-sc-library-multimedia-root data-admin="1"></div>
        </div>
        <?php
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('The Multimedia Studio is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }
        $atts = shortcode_atts([
            'title' => 'Multimedia Studio',
            'intro' => 'Create source-aware clips, captions, annotations, and evidence reels from authorized media.',
            'mode' => 'studio',
        ], $atts, 'sc_library_multimedia_studio');
        $this->enqueue_assets(false);
        $multimedia_title = sanitize_text_field((string) $atts['title']);
        $multimedia_intro = sanitize_text_field((string) $atts['intro']);
        $multimedia_mode = sanitize_key((string) $atts['mode']);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-multimedia-studio.php';
        return (string) ob_get_clean();
    }

    public function render_reel_shortcode(array $atts = []): string {
        $atts = shortcode_atts(['id' => '', 'uuid' => ''], $atts, 'sc_library_evidence_reel');
        $uuid = sanitize_text_field((string) ($atts['uuid'] ?: $atts['id']));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
            return '<p>' . esc_html__('Evidence reel identifier is missing or invalid.', 'sustainable-catalyst-library') . '</p>';
        }
        $reel = $this->get_reel_row($uuid);
        if (!$reel || $reel['visibility'] !== 'public') {
            return '<p>' . esc_html__('Evidence reel not found.', 'sustainable-catalyst-library') . '</p>';
        }
        $clips = $this->reel_clips($reel);
        ob_start();
        ?>
        <section class="sc-library-public-reel">
            <p class="sc-library-multimedia__eyebrow"><?php esc_html_e('Sustainable Catalyst Evidence Reel', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html($reel['title']); ?></h2>
            <?php if ($reel['description'] !== '') : ?><p><?php echo esc_html($reel['description']); ?></p><?php endif; ?>
            <ol>
                <?php foreach ($clips as $clip) : ?>
                    <li>
                        <h3><?php echo esc_html($clip['title']); ?></h3>
                        <?php if ($clip['output_url'] !== '') : ?>
                            <?php if (($clip['media_type'] ?? 'video') === 'audio') : ?>
                                <audio controls preload="metadata" src="<?php echo esc_url($clip['output_url']); ?>"></audio>
                            <?php else : ?>
                                <video controls preload="metadata" src="<?php echo esc_url($clip['output_url']); ?>"<?php echo $clip['poster_url'] !== '' ? ' poster="' . esc_url($clip['poster_url']) . '"' : ''; ?>></video>
                            <?php endif; ?>
                        <?php else : ?>
                            <p><a href="<?php echo esc_url($clip['source_url']); ?>"><?php esc_html_e('Open source media', 'sustainable-catalyst-library'); ?></a> — <?php echo esc_html($this->time_label((int) $clip['start_ms']) . '–' . $this->time_label((int) $clip['end_ms'])); ?></p>
                        <?php endif; ?>
                        <?php if ($clip['transcript_excerpt'] !== '') : ?><blockquote><?php echo esc_html($clip['transcript_excerpt']); ?></blockquote><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        $ns = self::REST_NAMESPACE;
        register_rest_route($ns, '/library/media/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/library/media/assets', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_assets'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_asset'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/assets/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_asset'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_asset'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/clips', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_clips'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_clip'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/clips/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_clip'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_clip'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/clips/(?P<uuid>[a-f0-9-]{36})/process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_clip'],
            'permission_callback' => [$this, 'require_editor'],
        ]);
        register_rest_route($ns, '/library/media/reels', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_reels'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_reel'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/reels/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_reel'],
                'permission_callback' => [$this, 'require_editor'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_reel'],
                'permission_callback' => [$this, 'require_editor'],
            ],
        ]);
        register_rest_route($ns, '/library/media/reels/public/(?P<uuid>[a-f0-9-]{36})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'public_reel'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/library/media/jobs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'list_jobs'],
            'permission_callback' => [$this, 'require_editor'],
        ]);
        register_rest_route($ns, '/library/media/jobs/(?P<uuid>[a-f0-9-]{36})/refresh', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'refresh_job_route'],
            'permission_callback' => [$this, 'require_editor'],
        ]);
        register_rest_route($ns, '/library/media/jobs/(?P<uuid>[a-f0-9-]{36})/retry', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'retry_job_route'],
            'permission_callback' => [$this, 'require_editor'],
        ]);
    }

    public function require_editor(): bool|WP_Error {
        return current_user_can('edit_posts')
            ? true
            : new WP_Error('sc_library_media_forbidden', __('You do not have permission to edit Library multimedia.', 'sustainable-catalyst-library'), ['status' => 403]);
    }

    public function status(): WP_REST_Response {
        global $wpdb;
        return rest_ensure_response([
            'ok' => true,
            'version' => SC_LIBRARY_VERSION,
            'schemas' => [
                'asset' => self::ASSET_SCHEMA,
                'clip' => self::CLIP_SCHEMA,
                'reel' => self::REEL_SCHEMA,
                'job' => self::JOB_SCHEMA,
            ],
            'enabled' => self::enabled(),
            'configured' => self::configured(),
            'rights_statuses' => self::rights_statuses(),
            'counts' => [
                'assets' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::assets_table()),
                'clips' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::clips_table()),
                'reels' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::reels_table()),
                'jobs' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::jobs_table()),
            ],
        ]);
    }

    public function list_assets(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $limit = min(200, max(1, absint($request->get_param('per_page') ?: 100)));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::assets_table() . ' ORDER BY updated_at DESC LIMIT %d', $limit), ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::ASSET_SCHEMA, 'items' => array_map([$this, 'public_asset'], $rows)]);
    }

    public function create_asset(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $data = $this->sanitize_asset($request->get_json_params() ?: []);
        if (is_wp_error($data)) {
            return $data;
        }
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $row = array_merge($data, [
            'asset_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ok = $wpdb->insert(self::assets_table(), $row);
        if (!$ok) {
            return new WP_Error('sc_library_media_write', __('The media asset could not be saved.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        return new WP_REST_Response($this->public_asset($this->get_asset_row($uuid) ?: []), 201);
    }

    public function update_asset(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        $existing = $this->get_asset_row($uuid);
        if (!$existing) {
            return $this->not_found('asset');
        }
        $data = $this->sanitize_asset($request->get_json_params() ?: [], $existing);
        if (is_wp_error($data)) {
            return $data;
        }
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->update(self::assets_table(), $data, ['asset_uuid' => $uuid]);
        return rest_ensure_response($this->public_asset($this->get_asset_row($uuid) ?: []));
    }

    public function delete_asset(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        $asset = $this->get_asset_row($uuid);
        if (!$asset) {
            return $this->not_found('asset');
        }
        $clip_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::clips_table() . ' WHERE asset_uuid=%s', $uuid));
        if ($clip_count > 0) {
            return new WP_Error('sc_library_media_in_use', __('Delete or reassign the asset clips before deleting this media asset.', 'sustainable-catalyst-library'), ['status' => 409]);
        }
        $wpdb->delete(self::assets_table(), ['asset_uuid' => $uuid]);
        return rest_ensure_response(['deleted' => true, 'asset_uuid' => $uuid]);
    }

    private function sanitize_asset(array $input, array $existing = []): array|WP_Error {
        $attachment_id = absint($input['attachment_id'] ?? $existing['attachment_id'] ?? 0);
        $source_url = esc_url_raw((string) ($input['source_url'] ?? $existing['source_url'] ?? ''));
        $source_kind = sanitize_key((string) ($input['source_kind'] ?? $existing['source_kind'] ?? ($attachment_id ? 'attachment' : 'remote')));
        if (!in_array($source_kind, ['attachment', 'remote', 'external_reference'], true)) {
            $source_kind = 'attachment';
        }
        if ($attachment_id > 0) {
            $mime = (string) get_post_mime_type($attachment_id);
            if (!str_starts_with($mime, 'video/') && !str_starts_with($mime, 'audio/')) {
                return new WP_Error('sc_library_media_mime', __('Select a video or audio attachment.', 'sustainable-catalyst-library'), ['status' => 400]);
            }
            $source_url = (string) wp_get_attachment_url($attachment_id);
            $source_kind = 'attachment';
        } elseif ($source_kind === 'remote') {
            if (!get_option('sc_library_media_allow_remote_urls', 0) && !current_user_can('manage_options')) {
                return new WP_Error('sc_library_media_remote_disabled', __('Remote media URLs are disabled.', 'sustainable-catalyst-library'), ['status' => 403]);
            }
            if (!$this->safe_remote_url($source_url)) {
                return new WP_Error('sc_library_media_url', __('Use a valid public HTTPS media URL.', 'sustainable-catalyst-library'), ['status' => 400]);
            }
        } elseif ($source_url === '') {
            return new WP_Error('sc_library_media_source', __('A WordPress media attachment or source URL is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }

        $rights = sanitize_key((string) ($input['rights_status'] ?? $existing['rights_status'] ?? 'unknown'));
        if (!array_key_exists($rights, self::rights_statuses())) {
            $rights = 'unknown';
        }
        $media_type = sanitize_key((string) ($input['media_type'] ?? $existing['media_type'] ?? 'video'));
        if (!in_array($media_type, ['video', 'audio'], true)) {
            $media_type = 'video';
        }
        $visibility = sanitize_key((string) ($input['visibility'] ?? $existing['visibility'] ?? 'private'));
        if (!in_array($visibility, ['private', 'shared', 'public'], true)) {
            $visibility = 'private';
        }
        return [
            'title' => sanitize_text_field((string) ($input['title'] ?? $existing['title'] ?? __('Untitled media', 'sustainable-catalyst-library'))),
            'description' => sanitize_textarea_field((string) ($input['description'] ?? $existing['description'] ?? '')),
            'media_type' => $media_type,
            'source_kind' => $source_kind,
            'attachment_id' => $attachment_id,
            'source_url' => $source_url,
            'duration_ms' => max(0, (int) ($input['duration_ms'] ?? $existing['duration_ms'] ?? 0)),
            'rights_status' => $rights,
            'rights_holder' => sanitize_text_field((string) ($input['rights_holder'] ?? $existing['rights_holder'] ?? '')),
            'license_name' => sanitize_text_field((string) ($input['license_name'] ?? $existing['license_name'] ?? '')),
            'license_url' => esc_url_raw((string) ($input['license_url'] ?? $existing['license_url'] ?? '')),
            'rights_note' => sanitize_textarea_field((string) ($input['rights_note'] ?? $existing['rights_note'] ?? '')),
            'source_citation' => sanitize_textarea_field((string) ($input['source_citation'] ?? $existing['source_citation'] ?? '')),
            'transcript_text' => sanitize_textarea_field((string) ($input['transcript_text'] ?? $existing['transcript_text'] ?? '')),
            'transcript_vtt' => $this->sanitize_vtt((string) ($input['transcript_vtt'] ?? $existing['transcript_vtt'] ?? '')),
            'captions_url' => esc_url_raw((string) ($input['captions_url'] ?? $existing['captions_url'] ?? '')),
            'poster_attachment_id' => absint($input['poster_attachment_id'] ?? $existing['poster_attachment_id'] ?? 0),
            'poster_time_ms' => max(0, (int) ($input['poster_time_ms'] ?? $existing['poster_time_ms'] ?? 0)),
            'accessibility_text' => sanitize_textarea_field((string) ($input['accessibility_text'] ?? $existing['accessibility_text'] ?? '')),
            'visibility' => $visibility,
            'metadata_json' => wp_json_encode($this->sanitize_recursive((array) ($input['metadata'] ?? json_decode((string) ($existing['metadata_json'] ?? '{}'), true) ?: [])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    public function list_clips(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $asset_uuid = sanitize_text_field((string) $request->get_param('asset_uuid'));
        if ($asset_uuid !== '') {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::clips_table() . ' WHERE asset_uuid=%s ORDER BY updated_at DESC', $asset_uuid), ARRAY_A) ?: [];
        } else {
            $rows = $wpdb->get_results('SELECT * FROM ' . self::clips_table() . ' ORDER BY updated_at DESC LIMIT 250', ARRAY_A) ?: [];
        }
        return rest_ensure_response(['schema' => self::CLIP_SCHEMA, 'items' => array_map([$this, 'public_clip'], $rows)]);
    }

    public function create_clip(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $data = $this->sanitize_clip($request->get_json_params() ?: []);
        if (is_wp_error($data)) {
            return $data;
        }
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $ok = $wpdb->insert(self::clips_table(), array_merge($data, [
            'clip_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        if (!$ok) {
            return new WP_Error('sc_library_media_write', __('The clip definition could not be saved.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        return new WP_REST_Response($this->public_clip($this->get_clip_row($uuid) ?: []), 201);
    }

    public function update_clip(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        $existing = $this->get_clip_row($uuid);
        if (!$existing) {
            return $this->not_found('clip');
        }
        $data = $this->sanitize_clip($request->get_json_params() ?: [], $existing);
        if (is_wp_error($data)) {
            return $data;
        }
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->update(self::clips_table(), $data, ['clip_uuid' => $uuid]);
        return rest_ensure_response($this->public_clip($this->get_clip_row($uuid) ?: []));
    }

    public function delete_clip(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        if (!$this->get_clip_row($uuid)) {
            return $this->not_found('clip');
        }
        $wpdb->delete(self::clips_table(), ['clip_uuid' => $uuid]);
        return rest_ensure_response(['deleted' => true, 'clip_uuid' => $uuid]);
    }

    private function sanitize_clip(array $input, array $existing = []): array|WP_Error {
        $asset_uuid = sanitize_text_field((string) ($input['asset_uuid'] ?? $existing['asset_uuid'] ?? ''));
        $asset = $this->get_asset_row($asset_uuid);
        if (!$asset) {
            return new WP_Error('sc_library_media_asset', __('Select an existing media asset.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $start = max(0, (int) ($input['start_ms'] ?? $existing['start_ms'] ?? 0));
        $end = max(0, (int) ($input['end_ms'] ?? $existing['end_ms'] ?? 0));
        if ($end <= $start) {
            return new WP_Error('sc_library_media_timing', __('Clip end time must be later than its start time.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $max = max(1, (int) get_option('sc_library_media_max_clip_minutes', 30)) * 60 * 1000;
        if (($end - $start) > $max) {
            return new WP_Error('sc_library_media_duration', sprintf(__('Clips may not exceed %d minutes.', 'sustainable-catalyst-library'), (int) get_option('sc_library_media_max_clip_minutes', 30)), ['status' => 400]);
        }
        if ((int) $asset['duration_ms'] > 0 && $end > (int) $asset['duration_ms']) {
            return new WP_Error('sc_library_media_duration', __('The clip end time exceeds the recorded asset duration.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $status = sanitize_key((string) ($input['status'] ?? $existing['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'ready', 'processing', 'completed', 'error', 'archived'], true)) {
            $status = 'draft';
        }
        $visibility = sanitize_key((string) ($input['visibility'] ?? $existing['visibility'] ?? 'private'));
        if (!in_array($visibility, ['private', 'shared', 'public'], true)) {
            $visibility = 'private';
        }
        return [
            'asset_uuid' => $asset_uuid,
            'title' => sanitize_text_field((string) ($input['title'] ?? $existing['title'] ?? __('Untitled clip', 'sustainable-catalyst-library'))),
            'description' => sanitize_textarea_field((string) ($input['description'] ?? $existing['description'] ?? '')),
            'start_ms' => $start,
            'end_ms' => $end,
            'poster_time_ms' => max($start, min($end, (int) ($input['poster_time_ms'] ?? $existing['poster_time_ms'] ?? $start))),
            'transcript_excerpt' => sanitize_textarea_field((string) ($input['transcript_excerpt'] ?? $existing['transcript_excerpt'] ?? '')),
            'caption_text' => sanitize_textarea_field((string) ($input['caption_text'] ?? $existing['caption_text'] ?? '')),
            'annotations_json' => wp_json_encode($this->sanitize_recursive((array) ($input['annotations'] ?? json_decode((string) ($existing['annotations_json'] ?? '[]'), true) ?: [])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            'status' => $status,
            'visibility' => $visibility,
            'output_attachment_id' => absint($input['output_attachment_id'] ?? $existing['output_attachment_id'] ?? 0),
            'poster_attachment_id' => absint($input['poster_attachment_id'] ?? $existing['poster_attachment_id'] ?? 0),
            'remote_job_uuid' => sanitize_text_field((string) ($input['remote_job_uuid'] ?? $existing['remote_job_uuid'] ?? '')),
            'metadata_json' => wp_json_encode($this->sanitize_recursive((array) ($input['metadata'] ?? json_decode((string) ($existing['metadata_json'] ?? '{}'), true) ?: [])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    public function process_clip(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        $clip = $this->get_clip_row($uuid);
        if (!$clip) {
            return $this->not_found('clip');
        }
        $asset = $this->get_asset_row((string) $clip['asset_uuid']);
        if (!$asset) {
            return $this->not_found('asset');
        }
        if (!self::configured()) {
            return new WP_Error('sc_library_media_not_configured', __('The optional media processor is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        if (!in_array($asset['rights_status'], ['owned', 'licensed', 'permission_granted', 'public_domain', 'creative_commons', 'fair_use_excerpt'], true)) {
            return new WP_Error('sc_library_media_rights', __('Verify the media rights status before processing.', 'sustainable-catalyst-library'), ['status' => 409]);
        }
        $job_uuid = wp_generate_uuid4();
        $packet = [
            'schema' => self::JOB_SCHEMA,
            'job_uuid' => $job_uuid,
            'owner_external_id' => 'wordpress:' . get_current_user_id(),
            'asset_uuid' => $asset['asset_uuid'],
            'clip_uuid' => $clip['clip_uuid'],
            'title' => $clip['title'],
            'source_url' => $asset['source_url'],
            'source_kind' => $asset['source_kind'],
            'start_ms' => (int) $clip['start_ms'],
            'end_ms' => (int) $clip['end_ms'],
            'poster_time_ms' => (int) $clip['poster_time_ms'],
            'caption_text' => $clip['caption_text'],
            'transcript_excerpt' => $clip['transcript_excerpt'],
            'rights' => [
                'status' => $asset['rights_status'],
                'holder' => $asset['rights_holder'],
                'license' => $asset['license_name'],
                'license_url' => $asset['license_url'],
                'note' => $asset['rights_note'],
                'citation' => $asset['source_citation'],
            ],
            'options' => [
                'format' => 'mp4',
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'burn_captions' => !empty(($request->get_json_params() ?: [])['burn_captions']),
                'create_poster' => true,
                'retention_days' => (int) get_option('sc_library_media_retention_days', 14),
            ],
        ];
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(self::jobs_table(), [
            'job_uuid' => $job_uuid,
            'clip_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'status' => 'submitting',
            'progress' => 0,
            'attempt' => 0,
            'max_attempts' => 3,
            'request_json' => wp_json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'remote_job_uuid' => $job_uuid,
            'output_attachment_id' => 0,
            'poster_attachment_id' => 0,
            'output_sha256' => '',
            'output_bytes' => 0,
            'diagnostics_json' => '{}',
            'error_message' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'completed_at' => null,
        ]);
        if (!$inserted) {
            return new WP_Error('sc_library_media_job_write', __('The media processing job could not be recorded.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        $remote = $this->remote_request('POST', '/api/v1/media/jobs', $packet, get_current_user_id());
        if (is_wp_error($remote)) {
            $this->update_job($job_uuid, ['status' => 'error', 'error_message' => $remote->get_error_message()]);
            $wpdb->update(self::clips_table(), ['status' => 'error', 'remote_job_uuid' => $job_uuid, 'updated_at' => $now], ['clip_uuid' => $uuid]);
            return $remote;
        }
        $this->apply_remote_state($job_uuid, $remote);
        $wpdb->update(self::clips_table(), ['status' => 'processing', 'remote_job_uuid' => $job_uuid, 'updated_at' => $now], ['clip_uuid' => $uuid]);
        $this->schedule_refresh($job_uuid, 10);
        return new WP_REST_Response($this->public_job($this->get_job_row($job_uuid) ?: []), 202);
    }

    public function list_reels(): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::reels_table() . ' ORDER BY updated_at DESC LIMIT 200', ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::REEL_SCHEMA, 'items' => array_map([$this, 'public_reel_row'], $rows)]);
    }

    public function create_reel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $data = $this->sanitize_reel($request->get_json_params() ?: []);
        $uuid = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $ok = $wpdb->insert(self::reels_table(), array_merge($data, [
            'reel_uuid' => $uuid,
            'owner_user_id' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]));
        if (!$ok) {
            return new WP_Error('sc_library_media_write', __('The evidence reel could not be saved.', 'sustainable-catalyst-library'), ['status' => 500]);
        }
        return new WP_REST_Response($this->public_reel_row($this->get_reel_row($uuid) ?: []), 201);
    }

    public function update_reel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        $existing = $this->get_reel_row($uuid);
        if (!$existing) {
            return $this->not_found('reel');
        }
        $data = $this->sanitize_reel($request->get_json_params() ?: [], $existing);
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->update(self::reels_table(), $data, ['reel_uuid' => $uuid]);
        return rest_ensure_response($this->public_reel_row($this->get_reel_row($uuid) ?: []));
    }

    public function delete_reel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;
        $uuid = sanitize_text_field((string) $request['uuid']);
        if (!$this->get_reel_row($uuid)) {
            return $this->not_found('reel');
        }
        $wpdb->delete(self::reels_table(), ['reel_uuid' => $uuid]);
        return rest_ensure_response(['deleted' => true, 'reel_uuid' => $uuid]);
    }

    private function sanitize_reel(array $input, array $existing = []): array {
        $clip_uuids = [];
        foreach ((array) ($input['clip_uuids'] ?? json_decode((string) ($existing['clip_uuids_json'] ?? '[]'), true) ?: []) as $uuid) {
            $uuid = sanitize_text_field((string) $uuid);
            if (preg_match('/^[a-f0-9-]{36}$/i', $uuid) && $this->get_clip_row($uuid)) {
                $clip_uuids[] = $uuid;
            }
        }
        $visibility = sanitize_key((string) ($input['visibility'] ?? $existing['visibility'] ?? 'private'));
        if (!in_array($visibility, ['private', 'shared', 'public'], true)) {
            $visibility = 'private';
        }
        return [
            'title' => sanitize_text_field((string) ($input['title'] ?? $existing['title'] ?? __('Untitled evidence reel', 'sustainable-catalyst-library'))),
            'description' => sanitize_textarea_field((string) ($input['description'] ?? $existing['description'] ?? '')),
            'clip_uuids_json' => wp_json_encode(array_values(array_unique($clip_uuids))) ?: '[]',
            'visibility' => $visibility,
            'edition_mode' => sanitize_key((string) ($input['edition_mode'] ?? $existing['edition_mode'] ?? 'linked')),
            'metadata_json' => wp_json_encode($this->sanitize_recursive((array) ($input['metadata'] ?? json_decode((string) ($existing['metadata_json'] ?? '{}'), true) ?: [])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ];
    }

    public function public_reel(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = sanitize_text_field((string) $request['uuid']);
        $row = $this->get_reel_row($uuid);
        if (!$row || $row['visibility'] !== 'public') {
            return $this->not_found('reel');
        }
        $item = $this->public_reel_row($row);
        $item['clips'] = $this->reel_clips($row);
        return rest_ensure_response($item);
    }

    public function list_jobs(): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::jobs_table() . ' ORDER BY created_at DESC LIMIT 100', ARRAY_A) ?: [];
        return rest_ensure_response(['schema' => self::JOB_SCHEMA, 'items' => array_map([$this, 'public_job'], $rows)]);
    }

    public function refresh_job_route(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = sanitize_text_field((string) $request['uuid']);
        $job = $this->refresh_job($uuid);
        return is_wp_error($job) ? $job : rest_ensure_response($job);
    }

    public function retry_job_route(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = sanitize_text_field((string) $request['uuid']);
        $job = $this->get_job_row($uuid);
        if (!$job) {
            return $this->not_found('job');
        }
        if (($job['status'] ?? '') !== 'error') {
            return new WP_Error('sc_library_media_retry_state', __('Only failed media jobs can be retried.', 'sustainable-catalyst-library'), ['status' => 409]);
        }
        if ((int) ($job['attempt'] ?? 0) >= (int) ($job['max_attempts'] ?? 3)) {
            return new WP_Error('sc_library_media_retry_limit', __('This media job has reached its retry limit.', 'sustainable-catalyst-library'), ['status' => 409]);
        }
        $remote = $this->remote_request('POST', '/api/v1/media/jobs/' . rawurlencode($uuid) . '/retry', [], (int) $job['owner_user_id']);
        if (is_wp_error($remote)) {
            return $remote;
        }
        $this->apply_remote_state($uuid, $remote);
        $this->schedule_refresh($uuid, 10);
        return rest_ensure_response($this->public_job($this->get_job_row($uuid) ?: []));
    }

    public function cron_refresh(string $uuid): void {
        $this->refresh_job($uuid);
    }

    private function refresh_job(string $uuid): array|WP_Error {
        global $wpdb;
        $job = $this->get_job_row($uuid);
        if (!$job) {
            return $this->not_found('job');
        }
        if (in_array($job['status'], ['completed', 'imported', 'error', 'cancelled'], true)) {
            return $this->public_job($job);
        }
        $remote = $this->remote_request('GET', '/api/v1/media/jobs/' . rawurlencode($uuid), null, (int) $job['owner_user_id']);
        if (is_wp_error($remote)) {
            $this->update_job($uuid, ['status' => 'error', 'error_message' => $remote->get_error_message()]);
            return $remote;
        }
        $this->apply_remote_state($uuid, $remote);
        $job = $this->get_job_row($uuid) ?: [];
        if (($job['status'] ?? '') === 'completed' && get_option('sc_library_media_auto_import', 1)) {
            $video = $this->remote_request('GET', '/api/v1/media/jobs/' . rawurlencode($uuid) . '/video', null, (int) $job['owner_user_id'], true);
            $poster = $this->remote_request('GET', '/api/v1/media/jobs/' . rawurlencode($uuid) . '/poster', null, (int) $job['owner_user_id'], true);
            if (!is_wp_error($video) && !empty($video['body'])) {
                $clip = $this->get_clip_row((string) $job['clip_uuid']);
                $title = $clip ? $clip['title'] : __('Library media clip', 'sustainable-catalyst-library');
                $video_id = $this->import_binary((string) $video['body'], sanitize_file_name($title . '.mp4'), 'video/mp4', $title);
                $poster_id = 0;
                if (!is_wp_error($poster) && !empty($poster['body'])) {
                    $poster_id = $this->import_binary((string) $poster['body'], sanitize_file_name($title . '-poster.jpg'), 'image/jpeg', $title . ' poster');
                }
                if (!is_wp_error($video_id)) {
                    $this->update_job($uuid, ['status' => 'imported', 'output_attachment_id' => (int) $video_id, 'poster_attachment_id' => is_wp_error($poster_id) ? 0 : (int) $poster_id]);
                    $wpdb->update(self::clips_table(), [
                        'status' => 'completed',
                        'output_attachment_id' => (int) $video_id,
                        'poster_attachment_id' => is_wp_error($poster_id) ? 0 : (int) $poster_id,
                        'updated_at' => current_time('mysql', true),
                    ], ['clip_uuid' => $job['clip_uuid']]);
                    do_action('sc_library_media_clip_completed', [
                        'job_uuid' => $uuid,
                        'clip_uuid' => (string) $job['clip_uuid'],
                        'title' => (string) $title,
                        'attachment_id' => (int) $video_id,
                        'poster_attachment_id' => is_wp_error($poster_id) ? 0 : (int) $poster_id,
                        'url' => wp_get_attachment_url((int) $video_id),
                        'sha256' => (string) ($job['output_sha256'] ?? ''),
                    ]);
                }
            }
        }
        $job = $this->get_job_row($uuid) ?: [];
        if (in_array($job['status'] ?? '', ['queued', 'processing', 'submitting'], true)) {
            $this->schedule_refresh($uuid, 15);
        }
        return $this->public_job($job);
    }

    private function remote_request(string $method, string $path, ?array $packet, int $owner_user_id, bool $binary = false): array|WP_Error {
        $base = self::service_url();
        $key = self::api_key();
        if ($base === '' || $key === '') {
            return new WP_Error('sc_library_media_config', __('Media service URL or API key is missing.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $path = '/' . ltrim($path, '/');
        $body = $packet === null ? '' : (wp_json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $timestamp = (string) time();
        $signature_base = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $body;
        $response = wp_remote_request($base . $path, [
            'method' => strtoupper($method),
            'timeout' => 120,
            'headers' => [
                'Accept' => $binary ? '*/*' : 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $key,
                'X-SC-Owner' => 'wordpress:' . $owner_user_id,
                'X-SC-Library-Timestamp' => $timestamp,
                'X-SC-Library-Signature' => hash_hmac('sha256', $signature_base, $key),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($raw, true);
            return new WP_Error('sc_library_media_remote', is_array($decoded) ? (string) ($decoded['detail'] ?? ('Media service returned HTTP ' . $code)) : ('Media service returned HTTP ' . $code), ['status' => $code]);
        }
        if ($binary) {
            return ['body' => $raw, 'content_type' => (string) wp_remote_retrieve_header($response, 'content-type')];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function apply_remote_state(string $uuid, array $remote): void {
        global $wpdb;
        $status = sanitize_key((string) ($remote['status'] ?? 'queued'));
        if (!in_array($status, ['queued', 'processing', 'completed', 'error', 'cancelled'], true)) {
            $status = 'queued';
        }
        $this->update_job($uuid, [
            'status' => $status,
            'progress' => min(100, max(0, (int) ($remote['progress'] ?? 0))),
            'attempt' => max(0, (int) ($remote['attempt'] ?? 0)),
            'output_sha256' => sanitize_text_field((string) ($remote['output_sha256'] ?? '')),
            'output_bytes' => max(0, (int) ($remote['output_bytes'] ?? 0)),
            'diagnostics_json' => wp_json_encode(is_array($remote['diagnostics'] ?? null) ? $remote['diagnostics'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'error_message' => sanitize_textarea_field((string) ($remote['error'] ?? '')),
            'completed_at' => $status === 'completed' ? current_time('mysql', true) : null,
        ]);
        $job = $this->get_job_row($uuid);
        if ($job) {
            $wpdb->update(self::clips_table(), ['status' => $status === 'completed' ? 'completed' : $status, 'updated_at' => current_time('mysql', true)], ['clip_uuid' => $job['clip_uuid']]);
        }
    }

    private function update_job(string $uuid, array $fields): void {
        global $wpdb;
        $allowed = ['status', 'progress', 'attempt', 'output_attachment_id', 'poster_attachment_id', 'output_sha256', 'output_bytes', 'diagnostics_json', 'error_message', 'completed_at'];
        $data = ['updated_at' => current_time('mysql', true)];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $data[$field] = $fields[$field];
            }
        }
        $wpdb->update(self::jobs_table(), $data, ['job_uuid' => $uuid]);
    }

    private function schedule_refresh(string $uuid, int $seconds): void {
        if (!wp_next_scheduled('sc_library_refresh_media_job', [$uuid])) {
            wp_schedule_single_event(time() + max(5, $seconds), 'sc_library_refresh_media_job', [$uuid]);
        }
    }

    private function import_binary(string $body, string $filename, string $mime, string $title): int|WP_Error {
        if ($body === '') {
            return new WP_Error('sc_library_media_empty', __('The processor returned an empty media file.', 'sustainable-catalyst-library'));
        }
        $upload = wp_upload_bits($filename, null, $body);
        if (!empty($upload['error'])) {
            return new WP_Error('sc_library_media_upload', (string) $upload['error']);
        }
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $mime,
            'post_title' => sanitize_text_field($title),
            'post_status' => 'inherit',
        ], $upload['file']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata((int) $attachment_id, $upload['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $attachment_id, $metadata);
        }
        return (int) $attachment_id;
    }

    private function get_asset_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::assets_table() . ' WHERE asset_uuid=%s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function get_clip_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::clips_table() . ' WHERE clip_uuid=%s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function get_reel_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::reels_table() . ' WHERE reel_uuid=%s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function get_job_row(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::jobs_table() . ' WHERE job_uuid=%s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function public_asset(array $row): array {
        $attachment_id = (int) ($row['attachment_id'] ?? 0);
        $poster_id = (int) ($row['poster_attachment_id'] ?? 0);
        return [
            'schema' => self::ASSET_SCHEMA,
            'asset_uuid' => (string) ($row['asset_uuid'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'media_type' => (string) ($row['media_type'] ?? 'video'),
            'source_kind' => (string) ($row['source_kind'] ?? ''),
            'attachment_id' => $attachment_id,
            'source_url' => (string) ($row['source_url'] ?? ''),
            'duration_ms' => (int) ($row['duration_ms'] ?? 0),
            'rights_status' => (string) ($row['rights_status'] ?? 'unknown'),
            'rights_holder' => (string) ($row['rights_holder'] ?? ''),
            'license_name' => (string) ($row['license_name'] ?? ''),
            'license_url' => (string) ($row['license_url'] ?? ''),
            'rights_note' => (string) ($row['rights_note'] ?? ''),
            'source_citation' => (string) ($row['source_citation'] ?? ''),
            'transcript_text' => (string) ($row['transcript_text'] ?? ''),
            'transcript_vtt' => (string) ($row['transcript_vtt'] ?? ''),
            'captions_url' => (string) ($row['captions_url'] ?? ''),
            'poster_attachment_id' => $poster_id,
            'poster_url' => $poster_id ? (string) wp_get_attachment_url($poster_id) : '',
            'poster_time_ms' => (int) ($row['poster_time_ms'] ?? 0),
            'accessibility_text' => (string) ($row['accessibility_text'] ?? ''),
            'visibility' => (string) ($row['visibility'] ?? 'private'),
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'created_at' => $this->rfc3339((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->rfc3339((string) ($row['updated_at'] ?? '')),
        ];
    }

    private function public_clip(array $row): array {
        $asset = $this->get_asset_row((string) ($row['asset_uuid'] ?? ''));
        $output_id = (int) ($row['output_attachment_id'] ?? 0);
        $poster_id = (int) ($row['poster_attachment_id'] ?? 0);
        return [
            'schema' => self::CLIP_SCHEMA,
            'clip_uuid' => (string) ($row['clip_uuid'] ?? ''),
            'asset_uuid' => (string) ($row['asset_uuid'] ?? ''),
            'asset_title' => (string) ($asset['title'] ?? ''),
            'media_type' => (string) ($asset['media_type'] ?? 'video'),
            'source_url' => (string) ($asset['source_url'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'start_ms' => (int) ($row['start_ms'] ?? 0),
            'end_ms' => (int) ($row['end_ms'] ?? 0),
            'poster_time_ms' => (int) ($row['poster_time_ms'] ?? 0),
            'transcript_excerpt' => (string) ($row['transcript_excerpt'] ?? ''),
            'caption_text' => (string) ($row['caption_text'] ?? ''),
            'annotations' => json_decode((string) ($row['annotations_json'] ?? '[]'), true) ?: [],
            'status' => (string) ($row['status'] ?? 'draft'),
            'visibility' => (string) ($row['visibility'] ?? 'private'),
            'output_attachment_id' => $output_id,
            'output_url' => $output_id ? (string) wp_get_attachment_url($output_id) : '',
            'poster_attachment_id' => $poster_id,
            'poster_url' => $poster_id ? (string) wp_get_attachment_url($poster_id) : '',
            'remote_job_uuid' => (string) ($row['remote_job_uuid'] ?? ''),
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'created_at' => $this->rfc3339((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->rfc3339((string) ($row['updated_at'] ?? '')),
        ];
    }

    private function public_reel_row(array $row): array {
        $uuid = (string) ($row['reel_uuid'] ?? '');
        return [
            'schema' => self::REEL_SCHEMA,
            'reel_uuid' => $uuid,
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'clip_uuids' => json_decode((string) ($row['clip_uuids_json'] ?? '[]'), true) ?: [],
            'visibility' => (string) ($row['visibility'] ?? 'private'),
            'edition_mode' => (string) ($row['edition_mode'] ?? 'linked'),
            'shortcode' => $uuid ? '[sc_library_evidence_reel id="' . $uuid . '"]' : '',
            'metadata' => json_decode((string) ($row['metadata_json'] ?? '{}'), true) ?: [],
            'created_at' => $this->rfc3339((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->rfc3339((string) ($row['updated_at'] ?? '')),
        ];
    }

    private function public_job(array $row): array {
        $output_id = (int) ($row['output_attachment_id'] ?? 0);
        $poster_id = (int) ($row['poster_attachment_id'] ?? 0);
        return [
            'schema' => self::JOB_SCHEMA,
            'job_uuid' => (string) ($row['job_uuid'] ?? ''),
            'clip_uuid' => (string) ($row['clip_uuid'] ?? ''),
            'status' => (string) ($row['status'] ?? 'queued'),
            'progress' => (int) ($row['progress'] ?? 0),
            'attempt' => (int) ($row['attempt'] ?? 0),
            'max_attempts' => (int) ($row['max_attempts'] ?? 3),
            'output_attachment_id' => $output_id,
            'output_url' => $output_id ? (string) wp_get_attachment_url($output_id) : '',
            'poster_attachment_id' => $poster_id,
            'poster_url' => $poster_id ? (string) wp_get_attachment_url($poster_id) : '',
            'output_sha256' => (string) ($row['output_sha256'] ?? ''),
            'output_bytes' => (int) ($row['output_bytes'] ?? 0),
            'diagnostics' => json_decode((string) ($row['diagnostics_json'] ?? '{}'), true) ?: [],
            'error' => (string) ($row['error_message'] ?? ''),
            'created_at' => $this->rfc3339((string) ($row['created_at'] ?? '')),
            'updated_at' => $this->rfc3339((string) ($row['updated_at'] ?? '')),
            'completed_at' => $this->rfc3339((string) ($row['completed_at'] ?? '')),
        ];
    }

    private function reel_clips(array $reel): array {
        $items = [];
        foreach (json_decode((string) ($reel['clip_uuids_json'] ?? '[]'), true) ?: [] as $uuid) {
            $clip = $this->get_clip_row((string) $uuid);
            if ($clip) {
                $items[] = $this->public_clip($clip);
            }
        }
        return $items;
    }

    private function safe_remote_url(string $url): bool {
        if ($url === '' || !wp_http_validate_url($url)) {
            return false;
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return false;
        }
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    private function sanitize_vtt(string $value): string {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = array_slice(explode("\n", $value), 0, 10000);
        $clean = [];
        foreach ($lines as $line) {
            $clean[] = sanitize_textarea_field($line);
        }
        return trim(implode("\n", $clean));
    }

    private function sanitize_recursive(array $value): array {
        $out = [];
        foreach ($value as $key => $item) {
            $clean_key = array_is_list($value) ? $key : sanitize_key((string) $key);
            if (is_array($item)) {
                $out[$clean_key] = $this->sanitize_recursive($item);
            } elseif (is_bool($item) || is_int($item) || is_float($item) || $item === null) {
                $out[$clean_key] = $item;
            } else {
                $out[$clean_key] = sanitize_textarea_field((string) $item);
            }
        }
        return $out;
    }

    private function rfc3339(string $value): ?string {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $timestamp = strtotime($value . (str_contains($value, 'T') ? '' : ' UTC'));
        return $timestamp ? gmdate('c', $timestamp) : null;
    }

    private function time_label(int $milliseconds): string {
        $seconds = max(0, intdiv($milliseconds, 1000));
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private function not_found(string $type): WP_Error {
        return new WP_Error('sc_library_media_not_found', sprintf(__('Multimedia %s not found.', 'sustainable-catalyst-library'), $type), ['status' => 404]);
    }
}
