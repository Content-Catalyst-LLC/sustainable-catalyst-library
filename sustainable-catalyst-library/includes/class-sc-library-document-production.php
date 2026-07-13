<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-side book, PDF, and document production.
 *
 * WordPress owns permissions, job history, frozen edition records, and final
 * Media Library files. The optional Render service performs the expensive PDF
 * rendering and returns a versioned manifest and checksums.
 */
final class SC_Library_Document_Production {
    public const JOB_SCHEMA = 'sc-library-document-job/1.0';
    public const EDITION_SCHEMA = 'sc-library-edition/1.0';
    public const REST_NAMESPACE = 'sustainable-catalyst/v1';

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('sc_library_refresh_document_job', [$this, 'cron_refresh'], 10, 1);
        add_shortcode('sc_library_document_production', [$this, 'render_shortcode']);
    }

    public static function enabled(): bool {
        return class_exists('SC_Library_Books')
            && SC_Library_Books::enabled()
            && (bool) get_option('sc_library_enable_server_documents', 1);
    }

    public static function configured(): bool {
        return self::service_url() !== '' && self::api_key() !== '';
    }

    public static function service_url(): string {
        $specific = trim((string) get_option('sc_library_document_service_url', ''));
        if ($specific !== '') return untrailingslashit($specific);
        return untrailingslashit(trim((string) get_option('sc_library_render_sync_url', '')));
    }

    public static function api_key(): string {
        $specific = trim((string) get_option('sc_library_document_service_api_key', ''));
        if ($specific !== '') return $specific;
        return trim((string) get_option('sc_library_render_sync_api_key', ''));
    }

    public static function jobs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_document_jobs';
    }

    public static function editions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sc_library_document_editions';
    }

    public static function enqueue_assets(): void {
        if (!self::enabled()) return;
        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-documents', SC_LIBRARY_URL . 'assets/css/sc-library-documents.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-documents', SC_LIBRARY_URL . 'assets/js/sc-library-documents.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-documents', 'SCDocumentsShared', [
            'version' => SC_LIBRARY_VERSION,
            'jobSchema' => self::JOB_SCHEMA,
            'editionSchema' => self::EDITION_SCHEMA,
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library/documents')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'authenticated' => is_user_logged_in(),
            'configured' => self::configured(),
            'strings' => [
                'refreshing' => __('Refreshing document job…', 'sustainable-catalyst-library'),
                'retrying' => __('Retrying document job…', 'sustainable-catalyst-library'),
                'confirmDelete' => __('Delete this document job record? Imported PDF editions are not deleted.', 'sustainable-catalyst-library'),
            ],
        ]);
    }

    public function render_shortcode(array $atts = []): string {
        if (!self::enabled()) {
            return '<p>' . esc_html__('Server-side document production is currently disabled.', 'sustainable-catalyst-library') . '</p>';
        }
        self::enqueue_assets();
        $atts = shortcode_atts([
            'title' => 'Document Production Studio',
            'intro' => 'Review queued renders, frozen PDF editions, checksums, and production diagnostics.',
        ], $atts, 'sc_library_document_production');
        $title = sanitize_text_field((string) $atts['title']);
        $intro = sanitize_text_field((string) $atts['intro']);
        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-document-production.php';
        return (string) ob_get_clean();
    }

    public function admin_menu(): void {
        add_submenu_page(
            'sc-library',
            __('Document Production', 'sustainable-catalyst-library'),
            __('Document Production', 'sustainable-catalyst-library'),
            'edit_posts',
            'sc-library-document-production',
            [$this, 'render_admin_page']
        );
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'sc-library-document-production')) return;
        wp_enqueue_style('sc-library-documents-admin', SC_LIBRARY_URL . 'assets/css/sc-library-documents.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-documents-admin', SC_LIBRARY_URL . 'assets/js/sc-library-documents.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-documents-admin', 'SCDocumentsShared', [
            'version' => SC_LIBRARY_VERSION,
            'jobSchema' => self::JOB_SCHEMA,
            'editionSchema' => self::EDITION_SCHEMA,
            'restBase' => esc_url_raw(rest_url('sustainable-catalyst/v1/library/documents')),
            'nonce' => wp_create_nonce('wp_rest'),
            'authenticated' => true,
            'configured' => self::configured(),
            'strings' => [],
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('edit_posts')) wp_die(esc_html__('You do not have permission to view document production.', 'sustainable-catalyst-library'));
        $counts = $this->counts(current_user_can('manage_options') ? 0 : get_current_user_id());
        ?>
        <div class="wrap sc-library-documents-admin">
            <h1><?php esc_html_e('Library Document Production', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Monitor queued server renders, retry failures, import completed PDFs into the WordPress Media Library, and preserve frozen edition manifests.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-documents__metrics">
                <?php foreach ($counts as $label => $count) : ?>
                    <article><strong><?php echo esc_html(number_format_i18n($count)); ?></strong><span><?php echo esc_html($label); ?></span></article>
                <?php endforeach; ?>
            </div>
            <div class="notice <?php echo self::configured() ? 'notice-success' : 'notice-warning'; ?> inline">
                <p><?php echo self::configured()
                    ? esc_html__('The Render document service is configured. WordPress will import completed PDFs when automatic import is enabled.', 'sustainable-catalyst-library')
                    : esc_html__('The Render document service is not configured. Browser Print / Save as PDF remains available.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <div data-sc-library-documents-root data-admin="1"></div>
        </div>
        <?php
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/library/documents/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/documents/jobs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_jobs'],
                'permission_callback' => [$this, 'logged_in'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_job'],
                'permission_callback' => [$this, 'logged_in'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/documents/jobs/(?P<uuid>[a-f0-9-]{36})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_job'],
                'permission_callback' => [$this, 'job_permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_job'],
                'permission_callback' => [$this, 'job_permission'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/documents/jobs/(?P<uuid>[a-f0-9-]{36})/refresh', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'refresh_job'],
            'permission_callback' => [$this, 'job_permission'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/documents/jobs/(?P<uuid>[a-f0-9-]{36})/retry', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'retry_job'],
            'permission_callback' => [$this, 'job_permission'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/library/documents/editions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'list_editions'],
            'permission_callback' => [$this, 'logged_in'],
        ]);
    }

    public function logged_in(): bool {
        return is_user_logged_in();
    }

    public function job_permission(WP_REST_Request $request): bool {
        if (!is_user_logged_in()) return false;
        $row = $this->job((string) $request['uuid']);
        if (!$row) return false;
        return current_user_can('manage_options') || (int) $row['owner_user_id'] === get_current_user_id();
    }

    public function status(): WP_REST_Response {
        $remote = ['ok' => false, 'state' => self::configured() ? 'unavailable' : 'not_configured'];
        if (self::configured()) {
            $result = $this->remote_request('GET', '/health', null, 0, false);
            if (!is_wp_error($result)) {
                $remote = array_merge(['ok' => true, 'state' => 'online'], is_array($result) ? $result : []);
            }
        }
        return rest_ensure_response([
            'enabled' => self::enabled(),
            'configured' => self::configured(),
            'authenticated' => is_user_logged_in(),
            'job_schema' => self::JOB_SCHEMA,
            'edition_schema' => self::EDITION_SCHEMA,
            'auto_import' => (bool) get_option('sc_library_document_auto_import', 1),
            'remote' => $remote,
        ]);
    }

    public function create_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        if (!self::configured()) {
            return new WP_Error('sc_library_document_not_configured', __('The server document service is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $payload = $request->get_json_params();
        if (!is_array($payload)) $payload = [];
        $normalized = $this->normalize_packet($payload);
        if (is_wp_error($normalized)) return $normalized;

        $uuid = wp_generate_uuid4();
        $owner = get_current_user_id();
        $normalized['schema'] = self::JOB_SCHEMA;
        $normalized['job_uuid'] = $uuid;
        $normalized['owner_external_id'] = 'wordpress:' . $owner;
        $normalized['requested_at'] = gmdate(DATE_RFC3339);
        $json = wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return new WP_Error('sc_library_document_encode', __('The document packet could not be encoded.', 'sustainable-catalyst-library'), ['status' => 400]);
        $max_bytes = max(1, min(25, (int) get_option('sc_library_document_max_request_mb', 8))) * 1024 * 1024;
        if (strlen($json) > $max_bytes) return new WP_Error('sc_library_document_too_large', __('The document request exceeds the configured size limit.', 'sustainable-catalyst-library'), ['status' => 413]);
        $hash = hash('sha256', $json);
        $now = current_time('mysql', true);

        global $wpdb;
        $inserted = $wpdb->insert(self::jobs_table(), [
            'job_uuid' => $uuid,
            'owner_user_id' => $owner,
            'workspace_uuid' => sanitize_text_field((string) ($normalized['workspace_uuid'] ?? '')),
            'book_id' => sanitize_text_field((string) ($normalized['book']['id'] ?? '')),
            'title' => sanitize_text_field((string) ($normalized['book']['title'] ?? __('Untitled Research Book', 'sustainable-catalyst-library'))),
            'document_type' => 'pdf',
            'status' => 'submitting',
            'progress' => 0,
            'attempt' => 0,
            'max_attempts' => max(1, min(10, (int) get_option('sc_library_document_max_attempts', 3))),
            'request_json' => $json,
            'content_hash' => $hash,
            'remote_job_uuid' => $uuid,
            'renderer_version' => '',
            'output_attachment_id' => 0,
            'output_sha256' => '',
            'output_bytes' => 0,
            'manifest_json' => '',
            'diagnostics_json' => '',
            'error_message' => '',
            'poll_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'completed_at' => null,
        ]);
        if (!$inserted) return new WP_Error('sc_library_document_db', __('The document job could not be saved.', 'sustainable-catalyst-library'), ['status' => 500]);

        $remote = $this->remote_request('POST', '/api/v1/documents/jobs', $normalized, $owner);
        if (is_wp_error($remote)) {
            $this->update_job($uuid, ['status' => 'error', 'error_message' => $remote->get_error_message()]);
            return $remote;
        }
        $this->apply_remote_state($uuid, is_array($remote) ? $remote : []);
        $this->schedule_refresh($uuid, 8);
        $row = $this->job($uuid);
        return rest_ensure_response($this->public_job($row ?: []));
    }

    public function list_jobs(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $limit = min(100, max(1, absint($request->get_param('per_page') ?: 30)));
        if (current_user_can('manage_options') && $request->get_param('all')) {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::jobs_table() . ' ORDER BY created_at DESC LIMIT %d', $limit), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::jobs_table() . ' WHERE owner_user_id = %d ORDER BY created_at DESC LIMIT %d', get_current_user_id(), $limit), ARRAY_A);
        }
        return rest_ensure_response(['schema' => self::JOB_SCHEMA, 'items' => array_map([$this, 'public_job'], is_array($rows) ? $rows : [])]);
    }

    public function get_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $row = $this->job((string) $request['uuid']);
        if (!$row) return $this->not_found();
        return rest_ensure_response($this->public_job($row));
    }

    public function refresh_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = (string) $request['uuid'];
        $result = $this->refresh($uuid);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public function retry_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = (string) $request['uuid'];
        $row = $this->job($uuid);
        if (!$row) return $this->not_found();
        if ((int) $row['attempt'] >= (int) $row['max_attempts']) {
            return new WP_Error('sc_library_document_attempts', __('The maximum number of render attempts has been reached.', 'sustainable-catalyst-library'), ['status' => 409]);
        }
        $remote = $this->remote_request('POST', '/api/v1/documents/jobs/' . rawurlencode($uuid) . '/retry', [], (int) $row['owner_user_id']);
        if (is_wp_error($remote)) return $remote;
        $this->apply_remote_state($uuid, is_array($remote) ? $remote : []);
        $this->schedule_refresh($uuid, 8);
        return rest_ensure_response($this->public_job($this->job($uuid) ?: []));
    }

    public function delete_job(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $uuid = (string) $request['uuid'];
        $row = $this->job($uuid);
        if (!$row) return $this->not_found();
        $this->remote_request('DELETE', '/api/v1/documents/jobs/' . rawurlencode($uuid), null, (int) $row['owner_user_id']);
        global $wpdb;
        $wpdb->delete(self::jobs_table(), ['job_uuid' => $uuid], ['%s']);
        return rest_ensure_response(['deleted' => true, 'job_uuid' => $uuid]);
    }

    public function list_editions(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $limit = min(100, max(1, absint($request->get_param('per_page') ?: 30)));
        if (current_user_can('manage_options') && $request->get_param('all')) {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::editions_table() . ' ORDER BY frozen_at DESC LIMIT %d', $limit), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::editions_table() . ' WHERE owner_user_id = %d ORDER BY frozen_at DESC LIMIT %d', get_current_user_id(), $limit), ARRAY_A);
        }
        return rest_ensure_response(['schema' => self::EDITION_SCHEMA, 'items' => array_map([$this, 'public_edition'], is_array($rows) ? $rows : [])]);
    }

    public function cron_refresh(string $uuid): void {
        $this->refresh($uuid, true);
    }

    private function refresh(string $uuid, bool $cron = false): array|WP_Error {
        $row = $this->job($uuid);
        if (!$row) return $this->not_found();
        if (in_array((string) $row['status'], ['completed', 'imported', 'cancelled'], true)) return $this->public_job($row);
        $remote = $this->remote_request('GET', '/api/v1/documents/jobs/' . rawurlencode($uuid), null, (int) $row['owner_user_id']);
        if (is_wp_error($remote)) {
            $this->update_job($uuid, [
                'status' => $cron ? (string) $row['status'] : 'error',
                'error_message' => $remote->get_error_message(),
                'poll_count' => (int) $row['poll_count'] + 1,
            ]);
            return $cron ? $this->public_job($this->job($uuid) ?: []) : $remote;
        }
        $this->apply_remote_state($uuid, is_array($remote) ? $remote : []);
        $updated = $this->job($uuid);
        if (!$updated) return $this->not_found();
        if ((string) $updated['status'] === 'completed' && (bool) get_option('sc_library_document_auto_import', 1) && (int) $updated['output_attachment_id'] === 0) {
            $import = $this->import_pdf($updated);
            if (is_wp_error($import)) {
                $this->update_job($uuid, ['error_message' => $import->get_error_message()]);
            }
            $updated = $this->job($uuid) ?: $updated;
        }
        if (in_array((string) $updated['status'], ['queued', 'processing', 'submitting'], true) && (int) $updated['poll_count'] < 40) {
            $this->schedule_refresh($uuid, min(60, 12 + ((int) $updated['poll_count'] * 3)));
        }
        return $this->public_job($updated);
    }

    private function import_pdf(array $row): int|WP_Error {
        $remote = $this->remote_request('GET', '/api/v1/documents/jobs/' . rawurlencode((string) $row['job_uuid']) . '/download', null, (int) $row['owner_user_id'], true);
        if (is_wp_error($remote)) return $remote;
        $bytes = is_array($remote) ? (string) ($remote['body'] ?? '') : '';
        if ($bytes === '' || !str_starts_with($bytes, '%PDF-')) return new WP_Error('sc_library_document_invalid_pdf', __('The renderer did not return a valid PDF.', 'sustainable-catalyst-library'));
        $max = max(1, min(50, (int) get_option('sc_library_document_max_pdf_mb', 20))) * 1024 * 1024;
        if (strlen($bytes) > $max) return new WP_Error('sc_library_document_pdf_large', __('The generated PDF exceeds the configured import limit.', 'sustainable-catalyst-library'));
        $filename = sanitize_file_name(($row['title'] ?: 'sustainable-catalyst-book') . '-' . substr((string) $row['job_uuid'], 0, 8) . '.pdf');
        $upload = wp_upload_bits($filename, null, $bytes);
        if (!empty($upload['error'])) return new WP_Error('sc_library_document_upload', (string) $upload['error']);
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => 'application/pdf',
            'post_title' => sanitize_text_field((string) $row['title']),
            'post_status' => 'inherit',
            'post_author' => (int) $row['owner_user_id'],
        ], (string) $upload['file']);
        if (is_wp_error($attachment_id)) return $attachment_id;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata((int) $attachment_id, wp_generate_attachment_metadata((int) $attachment_id, (string) $upload['file']));
        update_post_meta((int) $attachment_id, '_sc_library_document_job_uuid', (string) $row['job_uuid']);
        update_post_meta((int) $attachment_id, '_sc_library_document_content_hash', (string) $row['content_hash']);
        update_post_meta((int) $attachment_id, '_sc_library_document_output_sha256', hash('sha256', $bytes));

        $edition_uuid = wp_generate_uuid4();
        $manifest = json_decode((string) $row['manifest_json'], true);
        if (!is_array($manifest)) $manifest = [];
        $manifest['schema'] = self::EDITION_SCHEMA;
        $manifest['edition_uuid'] = $edition_uuid;
        $manifest['wordpress_attachment_id'] = (int) $attachment_id;
        $manifest['wordpress_url'] = wp_get_attachment_url((int) $attachment_id);
        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        global $wpdb;
        $wpdb->insert(self::editions_table(), [
            'edition_uuid' => $edition_uuid,
            'job_uuid' => (string) $row['job_uuid'],
            'owner_user_id' => (int) $row['owner_user_id'],
            'workspace_uuid' => (string) $row['workspace_uuid'],
            'book_id' => (string) $row['book_id'],
            'title' => (string) $row['title'],
            'edition_label' => sanitize_text_field((string) ($manifest['edition'] ?? 'Edition')),
            'content_hash' => (string) $row['content_hash'],
            'output_sha256' => hash('sha256', $bytes),
            'output_attachment_id' => (int) $attachment_id,
            'manifest_json' => $manifest_json,
            'frozen_at' => current_time('mysql', true),
            'created_at' => current_time('mysql', true),
        ]);
        $this->update_job((string) $row['job_uuid'], [
            'status' => 'imported',
            'output_attachment_id' => (int) $attachment_id,
            'output_sha256' => hash('sha256', $bytes),
            'output_bytes' => strlen($bytes),
            'completed_at' => current_time('mysql', true),
        ]);
        return (int) $attachment_id;
    }

    private function normalize_packet(array $payload): array|WP_Error {
        $book = $payload['book'] ?? null;
        $sections = $payload['sections'] ?? null;
        if (!is_array($book) || !is_array($sections)) {
            return new WP_Error('sc_library_document_invalid', __('A valid book and normalized section list are required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        $clean_sections = [];
        $position = 0;
        foreach (array_slice($sections, 0, 500) as $section) {
            if (!is_array($section)) continue;
            $position++;
            $clean_sections[] = [
                'position' => $position,
                'type' => sanitize_key((string) ($section['type'] ?? 'section')),
                'title' => sanitize_text_field((string) ($section['title'] ?? sprintf(__('Section %d', 'sustainable-catalyst-library'), $position))),
                'html' => wp_kses_post((string) ($section['html'] ?? '')),
                'source_url' => esc_url_raw((string) ($section['source_url'] ?? '')),
                'citation' => sanitize_textarea_field((string) ($section['citation'] ?? '')),
                'alt_text' => sanitize_textarea_field((string) ($section['alt_text'] ?? '')),
                'metadata' => is_array($section['metadata'] ?? null) ? $this->sanitize_recursive($section['metadata']) : [],
            ];
        }
        if (!$clean_sections) return new WP_Error('sc_library_document_empty', __('The book has no renderable sections.', 'sustainable-catalyst-library'), ['status' => 400]);
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        return [
            'workspace_uuid' => sanitize_text_field((string) ($payload['workspace_uuid'] ?? '')),
            'book' => [
                'schema' => SC_Library_Books::SCHEMA,
                'id' => sanitize_text_field((string) ($book['id'] ?? '')),
                'title' => sanitize_text_field((string) ($book['title'] ?? __('Untitled Research Book', 'sustainable-catalyst-library'))),
                'subtitle' => sanitize_text_field((string) ($book['subtitle'] ?? '')),
                'editor' => sanitize_text_field((string) ($book['editor'] ?? '')),
                'edition' => sanitize_text_field((string) ($book['edition'] ?? 'First edition')),
                'description' => sanitize_textarea_field((string) ($book['description'] ?? '')),
                'theme' => sanitize_key((string) ($book['theme'] ?? 'institutional')),
                'page_size' => sanitize_key((string) ($book['pageSize'] ?? $book['page_size'] ?? 'letter')),
                'front_matter' => [
                    'preface' => sanitize_textarea_field((string) ($book['frontMatter']['preface'] ?? '')),
                    'introduction' => sanitize_textarea_field((string) ($book['frontMatter']['introduction'] ?? '')),
                ],
                'back_matter' => [
                    'conclusion' => sanitize_textarea_field((string) ($book['backMatter']['conclusion'] ?? '')),
                ],
            ],
            'sections' => $clean_sections,
            'options' => [
                'include_toc' => !empty($options['include_toc']),
                'include_manifest' => !empty($options['include_manifest']),
                'include_citations' => !empty($options['include_citations']),
                'include_indexes' => !empty($options['include_indexes']),
                'include_accessibility_notes' => !empty($options['include_accessibility_notes']),
                'grayscale' => !empty($options['grayscale']),
                'language' => sanitize_text_field((string) ($options['language'] ?? 'en-US')),
            ],
        ];
    }

    private function sanitize_recursive(array $value): array {
        $out = [];
        $is_list = array_is_list($value);
        foreach ($value as $key => $item) {
            $clean_key = $is_list ? $key : sanitize_key((string) $key);
            if (is_array($item)) $out[$clean_key] = $this->sanitize_recursive($item);
            elseif (is_bool($item) || is_int($item) || is_float($item) || $item === null) $out[$clean_key] = $item;
            else $out[$clean_key] = sanitize_textarea_field((string) $item);
        }
        return $out;
    }

    private function remote_request(string $method, string $path, ?array $packet, int $owner_user_id, bool $binary = false): array|WP_Error {
        $base = self::service_url();
        $key = self::api_key();
        if ($base === '' || $key === '') return new WP_Error('sc_library_document_config', __('Document service URL or API key is missing.', 'sustainable-catalyst-library'), ['status' => 503]);
        $path = '/' . ltrim($path, '/');
        $url = $base . $path;
        $body = $packet === null ? '' : (wp_json_encode($packet, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $timestamp = (string) time();
        $signature_base = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $body;
        $headers = [
            'Accept' => $binary ? 'application/pdf' : 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $key,
            'X-SC-Owner' => 'wordpress:' . $owner_user_id,
            'X-SC-Library-Timestamp' => $timestamp,
            'X-SC-Library-Signature' => hash_hmac('sha256', $signature_base, $key),
        ];
        $response = wp_remote_request($url, [
            'method' => strtoupper($method),
            'timeout' => min(120, max(5, (int) get_option('sc_library_document_timeout', 30))),
            'headers' => $headers,
            'body' => $body,
        ]);
        if (is_wp_error($response)) return $response;
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($raw, true);
            $message = is_array($decoded) ? (string) ($decoded['detail'] ?? ('Document service returned HTTP ' . $code)) : ('Document service returned HTTP ' . $code);
            return new WP_Error('sc_library_document_remote', $message, ['status' => $code]);
        }
        if ($binary) return ['body' => $raw, 'content_type' => (string) wp_remote_retrieve_header($response, 'content-type')];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function apply_remote_state(string $uuid, array $remote): void {
        $status = sanitize_key((string) ($remote['status'] ?? 'queued'));
        if (!in_array($status, ['queued','processing','completed','error','cancelled'], true)) $status = 'queued';
        $fields = [
            'status' => $status,
            'progress' => min(100, max(0, (int) ($remote['progress'] ?? 0))),
            'attempt' => max(0, (int) ($remote['attempt'] ?? 0)),
            'renderer_version' => sanitize_text_field((string) ($remote['renderer_version'] ?? '')),
            'output_sha256' => sanitize_text_field((string) ($remote['output_sha256'] ?? '')),
            'output_bytes' => max(0, (int) ($remote['output_bytes'] ?? 0)),
            'manifest_json' => wp_json_encode(is_array($remote['manifest'] ?? null) ? $remote['manifest'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'diagnostics_json' => wp_json_encode(is_array($remote['diagnostics'] ?? null) ? $remote['diagnostics'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'error_message' => sanitize_textarea_field((string) ($remote['error'] ?? '')),
            'poll_count' => ((int) (($this->job($uuid)['poll_count'] ?? 0))) + 1,
        ];
        if ($status === 'completed') $fields['completed_at'] = current_time('mysql', true);
        $this->update_job($uuid, $fields);
    }

    private function update_job(string $uuid, array $fields): void {
        global $wpdb;
        $allowed = ['status','progress','attempt','renderer_version','output_attachment_id','output_sha256','output_bytes','manifest_json','diagnostics_json','error_message','poll_count','completed_at'];
        $data = ['updated_at' => current_time('mysql', true)];
        foreach ($allowed as $field) if (array_key_exists($field, $fields)) $data[$field] = $fields[$field];
        $wpdb->update(self::jobs_table(), $data, ['job_uuid' => $uuid]);
    }

    private function schedule_refresh(string $uuid, int $seconds): void {
        if (!wp_next_scheduled('sc_library_refresh_document_job', [$uuid])) {
            wp_schedule_single_event(time() + max(5, $seconds), 'sc_library_refresh_document_job', [$uuid]);
        }
    }

    private function job(string $uuid): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::jobs_table() . ' WHERE job_uuid = %s', $uuid), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function public_job(array $row): array {
        if (!$row) return [];
        $attachment_id = (int) ($row['output_attachment_id'] ?? 0);
        return [
            'schema' => self::JOB_SCHEMA,
            'job_uuid' => (string) $row['job_uuid'],
            'title' => (string) $row['title'],
            'book_id' => (string) $row['book_id'],
            'status' => (string) $row['status'],
            'progress' => (int) $row['progress'],
            'attempt' => (int) $row['attempt'],
            'max_attempts' => (int) $row['max_attempts'],
            'content_hash' => (string) $row['content_hash'],
            'renderer_version' => (string) $row['renderer_version'],
            'output_sha256' => (string) $row['output_sha256'],
            'output_bytes' => (int) $row['output_bytes'],
            'output_attachment_id' => $attachment_id,
            'output_url' => $attachment_id ? wp_get_attachment_url($attachment_id) : '',
            'manifest' => json_decode((string) $row['manifest_json'], true) ?: [],
            'diagnostics' => json_decode((string) $row['diagnostics_json'], true) ?: [],
            'error' => (string) $row['error_message'],
            'created_at' => mysql_to_rfc3339((string) $row['created_at']),
            'updated_at' => mysql_to_rfc3339((string) $row['updated_at']),
            'completed_at' => !empty($row['completed_at']) ? mysql_to_rfc3339((string) $row['completed_at']) : null,
        ];
    }

    private function public_edition(array $row): array {
        $attachment_id = (int) $row['output_attachment_id'];
        return [
            'schema' => self::EDITION_SCHEMA,
            'edition_uuid' => (string) $row['edition_uuid'],
            'job_uuid' => (string) $row['job_uuid'],
            'title' => (string) $row['title'],
            'edition' => (string) $row['edition_label'],
            'book_id' => (string) $row['book_id'],
            'content_hash' => (string) $row['content_hash'],
            'output_sha256' => (string) $row['output_sha256'],
            'attachment_id' => $attachment_id,
            'url' => $attachment_id ? wp_get_attachment_url($attachment_id) : '',
            'manifest' => json_decode((string) $row['manifest_json'], true) ?: [],
            'frozen_at' => mysql_to_rfc3339((string) $row['frozen_at']),
        ];
    }

    private function counts(int $owner_user_id): array {
        global $wpdb;
        $where = $owner_user_id > 0 ? $wpdb->prepare(' WHERE owner_user_id = %d', $owner_user_id) : '';
        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::jobs_table() . $where . ' GROUP BY status', ARRAY_A);
        $map = [];
        foreach (is_array($rows) ? $rows : [] as $row) $map[(string) $row['status']] = (int) $row['total'];
        $editions = $owner_user_id > 0
            ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::editions_table() . ' WHERE owner_user_id = %d', $owner_user_id))
            : (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::editions_table());
        return [
            __('Queued', 'sustainable-catalyst-library') => ($map['queued'] ?? 0) + ($map['submitting'] ?? 0),
            __('Processing', 'sustainable-catalyst-library') => $map['processing'] ?? 0,
            __('Completed', 'sustainable-catalyst-library') => ($map['completed'] ?? 0) + ($map['imported'] ?? 0),
            __('Errors', 'sustainable-catalyst-library') => $map['error'] ?? 0,
            __('Frozen editions', 'sustainable-catalyst-library') => $editions,
        ];
    }

    private function not_found(): WP_Error {
        return new WP_Error('sc_library_document_not_found', __('Document job not found.', 'sustainable-catalyst-library'), ['status' => 404]);
    }
}
