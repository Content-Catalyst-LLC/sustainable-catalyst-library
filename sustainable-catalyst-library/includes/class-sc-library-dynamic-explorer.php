<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.6.0 R2 Dynamic Library Explorer integration.
 *
 * Python/PostgreSQL is the preferred public discovery/read model. WordPress
 * remains authoritative for publication state and provides a bounded local
 * fallback when the backend cannot be reached.
 */
final class SC_Library_Dynamic_Explorer {
    public const VERSION = '5.6.0.2';
    public const REST_NAMESPACE = 'sc-library/v1';
    private const DEFAULT_PER_PAGE = 12;

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function should_render(array $atts): bool {
        if (!empty($_GET['library_legacy'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }
        $mode = sanitize_key((string) ($atts['mode'] ?? ''));
        if (in_array($mode, ['documentation', 'registry', 'planner', 'roadmap', 'compact', 'full', 'search', 'domains', 'pathways', 'legacy'], true)) {
            return false;
        }
        return $mode === '' || in_array($mode, ['explorer', 'dynamic'], true);
    }

    public static function render_shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Knowledge Library',
            'intro' => 'Search research, evidence, publications, and connected knowledge without loading the entire Library at once.',
            'per_page' => (string) self::DEFAULT_PER_PAGE,
            'show_header' => 'true',
        ], $atts, 'sc_library');

        $instance_id = 'sc-library-explorer-' . wp_rand(1000, 999999);
        $title = sanitize_text_field((string) $atts['title']);
        $intro = sanitize_text_field((string) $atts['intro']);
        $per_page = min(30, max(6, absint($atts['per_page'])));
        $show_header = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
        $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/knowledge-libraries/'), PHP_URL_PATH); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $legacy_url = add_query_arg('library_legacy', '1', home_url($path ?: '/knowledge-libraries/'));
        $librarian_url = (string) get_option('sc_library_orchestrator_page_url', home_url('/research-librarian/'));

        wp_enqueue_style('sc-library', SC_LIBRARY_URL . 'assets/css/sc-library.css', [], SC_LIBRARY_VERSION);
        wp_enqueue_style('sc-library-dynamic-explorer-v560', SC_LIBRARY_URL . 'assets/css/sc-library-dynamic-explorer-v560.css', ['sc-library'], SC_LIBRARY_VERSION);
        wp_enqueue_script('sc-library-dynamic-explorer-v560', SC_LIBRARY_URL . 'assets/js/sc-library-dynamic-explorer-v560.js', [], SC_LIBRARY_VERSION, true);
        wp_localize_script('sc-library-dynamic-explorer-v560', 'SCLibraryExplorerV560', [
            'restBase' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/explorer')),
            'legacyUrl' => esc_url_raw($legacy_url),
            'librarianUrl' => esc_url_raw($librarian_url),
            'schema' => 'sc-library-explorer-ui/1.0',
            'version' => self::VERSION,
            'strings' => [
                'loading' => __('Loading Library intelligence…', 'sustainable-catalyst-library'),
                'searching' => __('Searching the Library…', 'sustainable-catalyst-library'),
                'empty' => __('No published Library records match these filters.', 'sustainable-catalyst-library'),
                'error' => __('Dynamic discovery is temporarily unavailable.', 'sustainable-catalyst-library'),
                'fallback' => __('Use the local Library catalog', 'sustainable-catalyst-library'),
                'loadMore' => __('Load more', 'sustainable-catalyst-library'),
                'quickView' => __('Quick view', 'sustainable-catalyst-library'),
            ],
        ]);

        ob_start();
        include SC_LIBRARY_DIR . 'templates/library-explorer.php';
        return (string) ob_get_clean();
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/explorer/bootstrap', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'bootstrap'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/explorer/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'search'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/explorer/records/(?P<record_id>[^/]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'record'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/explorer/records/(?P<record_id>[^/]+)/related', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'related'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/explorer/records/(?P<record_id>[^/]+)/timeline', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'timeline'],
        ]);
    }

    public function bootstrap(WP_REST_Request $request): WP_REST_Response {
        unset($request);
        $data = $this->backend_get('/v1/explorer/bootstrap', ['featured_limit' => 4, 'recent_limit' => 4]);
        if (!is_wp_error($data)) {
            $data['transport'] = 'python';
            return rest_ensure_response($data);
        }
        $fallback = $this->fallback_bootstrap();
        $fallback['transport'] = 'wordpress-fallback';
        $fallback['backend_error'] = $data->get_error_message();
        return rest_ensure_response($fallback);
    }

    public function search(WP_REST_Request $request): WP_REST_Response {
        $params = $this->search_params($request);
        $data = $this->backend_get('/v1/search', $params);
        if (!is_wp_error($data)) {
            $data['transport'] = 'python';
            return rest_ensure_response($data);
        }
        $fallback = $this->fallback_search($params);
        $fallback['transport'] = 'wordpress-fallback';
        $fallback['backend_error'] = $data->get_error_message();
        return rest_ensure_response($fallback);
    }

    public function record(WP_REST_Request $request): WP_REST_Response {
        $record_id = sanitize_text_field(rawurldecode((string) $request['record_id']));
        $data = $this->backend_get('/v1/records/' . rawurlencode($record_id), ['include_body' => 'false']);
        if (!is_wp_error($data)) {
            $data['transport'] = 'python';
            return rest_ensure_response($data);
        }
        $record = $this->fallback_record($record_id);
        if (!$record) {
            return new WP_REST_Response(['message' => __('Library record not found.', 'sustainable-catalyst-library')], 404);
        }
        return rest_ensure_response(['schema' => 'sc-library-record/1.0', 'record' => $record, 'transport' => 'wordpress-fallback']);
    }

    public function related(WP_REST_Request $request): WP_REST_Response {
        $record_id = sanitize_text_field(rawurldecode((string) $request['record_id']));
        $limit = min(12, max(1, absint($request->get_param('limit') ?: 6)));
        $data = $this->backend_get('/v1/records/' . rawurlencode($record_id) . '/related', ['limit' => $limit]);
        if (!is_wp_error($data)) {
            $data['transport'] = 'python';
            return rest_ensure_response($data);
        }
        return rest_ensure_response([
            'schema' => 'sc-library-related/1.0',
            'record_id' => $record_id,
            'results' => $this->fallback_related($record_id, $limit),
            'transport' => 'wordpress-fallback',
        ]);
    }

    public function timeline(WP_REST_Request $request): WP_REST_Response {
        $record_id = sanitize_text_field(rawurldecode((string) $request['record_id']));
        $limit = min(25, max(1, absint($request->get_param('limit') ?: 10)));
        $data = $this->backend_get('/v1/records/' . rawurlencode($record_id) . '/timeline', ['limit' => $limit]);
        if (!is_wp_error($data)) {
            $data['transport'] = 'python';
            return rest_ensure_response($data);
        }
        $record = $this->fallback_record($record_id);
        $versions = [];
        if ($record) {
            $versions[] = [
                'revision' => 1,
                'observed_at' => $record['source_updated_at'] ?? null,
                'title' => $record['title'],
                'publication_status' => 'published',
                'source_updated_at' => $record['source_updated_at'] ?? null,
            ];
        }
        return rest_ensure_response(['schema' => 'sc-library-record-timeline/1.0', 'record_id' => $record_id, 'versions' => $versions, 'transport' => 'wordpress-fallback']);
    }

    private function backend_get(string $path, array $params = []) {
        if (!class_exists('SC_Library_Python_Backend') || !SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_explorer_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'));
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) { return $response; }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            return new WP_Error('sc_library_explorer_backend_http', sprintf(__('Library backend returned HTTP %d.', 'sustainable-catalyst-library'), $code ?: 502));
        }
        return $data;
    }

    private function search_params(WP_REST_Request $request): array {
        $sort = sanitize_key((string) ($request->get_param('sort') ?: 'relevance'));
        if (!in_array($sort, ['relevance', 'updated', 'newest', 'oldest', 'title'], true)) { $sort = 'relevance'; }
        $params = [
            'q' => sanitize_text_field((string) $request->get_param('q')),
            'limit' => min(30, max(1, absint($request->get_param('limit') ?: self::DEFAULT_PER_PAGE))),
            'offset' => min(100000, max(0, absint($request->get_param('offset')))),
            'sort' => $sort,
        ];
        foreach (['object_type', 'source_key'] as $key) {
            $value = sanitize_key((string) $request->get_param($key));
            if ($value !== '') { $params[$key] = $value; }
        }
        $topic = sanitize_text_field((string) $request->get_param('topic'));
        if ($topic !== '') { $params['topic'] = $topic; }
        foreach (['year_from', 'year_to'] as $key) {
            $value = absint($request->get_param($key));
            if ($value >= 1000 && $value <= 3000) { $params[$key] = $value; }
        }
        return $params;
    }

    private function fallback_bootstrap(): array {
        $types = [];
        $public_records = 0;
        foreach (SC_Library_Python_Backend::post_types() as $post_type) {
            if (!post_type_exists($post_type)) { continue; }
            $counts = wp_count_posts($post_type);
            $count = isset($counts->publish) ? (int) $counts->publish : 0;
            if ($count < 1) { continue; }
            $obj = get_post_type_object($post_type);
            $types[] = ['object_type' => $post_type, 'count' => $count, 'label' => $obj ? $obj->labels->singular_name : $post_type];
            $public_records += $count;
        }
        $topics = [];
        $terms = taxonomy_exists('category') ? get_terms(['taxonomy' => 'category', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 30]) : [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) { $topics[] = ['topic' => $term->name, 'count' => (int) $term->count]; }
        }
        $featured = $this->fallback_search(['q' => '', 'limit' => 4, 'offset' => 0, 'sort' => 'newest']);
        $recent = $this->fallback_search(['q' => '', 'limit' => 4, 'offset' => 0, 'sort' => 'updated']);
        return [
            'schema' => 'sc-library-explorer-bootstrap/1.0',
            'stats' => ['records' => $public_records, 'public_records' => $public_records, 'chunks' => null, 'edges' => null, 'sources' => 1],
            'facets' => ['schema' => 'sc-library-facets/1.1', 'object_types' => $types, 'sources' => [['source_key' => 'wordpress-main', 'count' => $public_records]], 'topics' => $topics, 'years' => []],
            'featured' => $featured['results'],
            'recent' => $recent['results'],
            'backend' => ['read_model' => 'wordpress-local', 'progressive' => true],
        ];
    }

    private function fallback_search(array $params): array {
        $q = sanitize_text_field((string) ($params['q'] ?? ''));
        $limit = min(30, max(1, absint($params['limit'] ?? self::DEFAULT_PER_PAGE)));
        $offset = max(0, absint($params['offset'] ?? 0));
        $post_types = SC_Library_Python_Backend::post_types();
        $object_type = sanitize_key((string) ($params['object_type'] ?? ''));
        if ($object_type !== '' && in_array($object_type, $post_types, true)) { $post_types = [$object_type]; }
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            's' => $q,
            'ignore_sticky_posts' => true,
        ];
        $sort = sanitize_key((string) ($params['sort'] ?? 'relevance'));
        if ($sort === 'title') { $args['orderby'] = 'title'; $args['order'] = 'ASC'; }
        elseif ($sort === 'oldest') { $args['orderby'] = 'date'; $args['order'] = 'ASC'; }
        elseif ($sort === 'newest') { $args['orderby'] = 'date'; $args['order'] = 'DESC'; }
        else { $args['orderby'] = $q !== '' && $sort === 'relevance' ? 'relevance' : 'modified'; $args['order'] = 'DESC'; }
        $topic = sanitize_text_field((string) ($params['topic'] ?? ''));
        if ($topic !== '' && taxonomy_exists('category')) {
            $term = get_term_by('name', $topic, 'category');
            if ($term && !is_wp_error($term)) { $args['cat'] = (int) $term->term_id; }
        }
        $date_query = [];
        if (!empty($params['year_from'])) { $date_query[] = ['after' => ['year' => absint($params['year_from'])], 'inclusive' => true]; }
        if (!empty($params['year_to'])) { $date_query[] = ['before' => ['year' => absint($params['year_to']), 'month' => 12, 'day' => 31], 'inclusive' => true]; }
        if ($date_query) { $args['date_query'] = $date_query; }
        $query = new WP_Query($args);
        return [
            'schema' => 'sc-library-search/1.1',
            'query' => $q,
            'filters' => $params,
            'total' => (int) $query->found_posts,
            'limit' => $limit,
            'offset' => $offset,
            'results' => array_map(fn($post) => $this->post_result($post), $query->posts),
        ];
    }

    private function post_result(WP_Post $post): array {
        $body = trim(wp_strip_all_tags(strip_shortcodes((string) $post->post_content), true));
        $abstract = trim(wp_strip_all_tags((string) $post->post_excerpt, true));
        if ($abstract === '' && $body !== '') { $abstract = wp_trim_words($body, 48, '…'); }
        $terms = get_the_terms($post->ID, 'category');
        $topics = is_wp_error($terms) || !$terms ? [] : array_values(array_map(static fn($term) => (string) $term->name, $terms));
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        return [
            'record_id' => SC_Library_Python_Backend::record_id((int) $post->ID, $post->post_type),
            'object_type' => $post->post_type,
            'title' => get_the_title($post),
            'canonical_url' => get_permalink($post),
            'abstract' => $abstract,
            'source_key' => 'wordpress-main',
            'published_at' => get_post_time('c', true, $post),
            'source_updated_at' => get_post_modified_time('c', true, $post),
            'authors' => [(string) get_the_author_meta('display_name', (int) $post->post_author)],
            'topics' => $topics,
            'tags' => is_array($tags) ? array_values($tags) : [],
            'identifiers' => ['wordpress_post_id' => (string) $post->ID],
            'metadata' => ['wordpress_post_type' => $post->post_type],
            'score' => 0,
            'snippet' => $abstract,
        ];
    }

    private function fallback_record(string $record_id): ?array {
        $post_id = $this->record_post_id($record_id);
        if (!$post_id) { return null; }
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_status !== 'publish' || !in_array($post->post_type, SC_Library_Python_Backend::post_types(), true)) { return null; }
        $record = $this->post_result($post);
        $record['body_text'] = wp_trim_words(trim(wp_strip_all_tags(strip_shortcodes((string) $post->post_content), true)), 240, '…');
        $record['language'] = substr((string) get_locale(), 0, 2) ?: 'en';
        $record['publication_status'] = 'published';
        $record['revision'] = 1;
        $record['indexed_at'] = $record['source_updated_at'];
        $record['chunks'] = [];
        return $record;
    }

    private function fallback_related(string $record_id, int $limit): array {
        $post_id = $this->record_post_id($record_id);
        if (!$post_id) { return []; }
        $categories = wp_get_post_categories($post_id);
        if (!$categories) { return []; }
        $query = new WP_Query([
            'post_type' => SC_Library_Python_Backend::post_types(),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$post_id],
            'category__in' => $categories,
            'orderby' => 'modified',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
        ]);
        return array_map(fn($post) => $this->post_result($post), $query->posts);
    }

    private function record_post_id(string $record_id): int {
        if (preg_match('/:(\d+)$/', $record_id, $matches)) { return absint($matches[1]); }
        return 0;
    }
}
