<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.7.0 Institutional Research Sources bridge.
 *
 * The browser never calls university repositories directly. WordPress proxies
 * bounded public metadata through the Library backend, preserving a stable
 * Catalyst source contract and fail-closed upstream behavior.
 */
final class SC_Library_Institutional_Research_Sources {
    public const VERSION = '5.7.0';
    public const SOURCE_JHU = 'johns-hopkins-dataverse';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('sc_institutional_research_sources', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-institutional-research-v570', SC_LIBRARY_URL . 'assets/institutional-research-sources-v570.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-institutional-research-v570', SC_LIBRARY_URL . 'assets/institutional-research-sources-v570.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/institutional-sources', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'sources'],
        ]);
        register_rest_route('sc-library/v1', '/institutional-sources/(?P<source>[a-z0-9-]+)/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'search'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'object_type' => ['sanitize_callback' => 'sanitize_key', 'default' => 'dataset'],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 10],
                'start' => ['sanitize_callback' => 'absint', 'default' => 0],
            ],
        ]);
        register_rest_route('sc-library/v1', '/institutional-sources/(?P<source>[a-z0-9-]+)/record', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'record'],
            'args' => [
                'persistent_id' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
            ],
        ]);
    }

    public function sources(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/institutional-sources');
    }

    public function search(WP_REST_Request $request) {
        $source = sanitize_key((string) $request['source']);
        if (self::SOURCE_JHU !== $source) {
            return new WP_Error('sc_library_unknown_institutional_source', __('Unknown institutional research source.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $params = [
            'q' => (string) $request->get_param('q'),
            'object_type' => in_array((string) $request->get_param('object_type'), ['dataset', 'file', 'dataverse'], true) ? (string) $request->get_param('object_type') : 'dataset',
            'limit' => min(50, max(1, (int) $request->get_param('limit'))),
            'start' => min(100000, max(0, (int) $request->get_param('start'))),
        ];
        return $this->proxy('/v1/institutional-sources/' . rawurlencode($source) . '/search', $params);
    }

    public function record(WP_REST_Request $request) {
        $source = sanitize_key((string) $request['source']);
        if (self::SOURCE_JHU !== $source) {
            return new WP_Error('sc_library_unknown_institutional_source', __('Unknown institutional research source.', 'sustainable-catalyst-library'), ['status' => 404]);
        }
        $persistent_id = trim((string) $request->get_param('persistent_id'));
        if ('' === $persistent_id || strlen($persistent_id) > 300) {
            return new WP_Error('sc_library_invalid_persistent_id', __('A valid persistent identifier is required.', 'sustainable-catalyst-library'), ['status' => 400]);
        }
        return $this->proxy('/v1/institutional-sources/' . rawurlencode($source) . '/record', ['persistent_id' => $persistent_id]);
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_institutional_source_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) { $body = ['ok' => false, 'detail' => __('Institutional source returned an invalid response.', 'sustainable-catalyst-library')]; }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function render_shortcode(array $atts = []): string {
        $atts = shortcode_atts(['source' => self::SOURCE_JHU], $atts, 'sc_institutional_research_sources');
        $source = sanitize_key((string) $atts['source']);
        if (self::SOURCE_JHU !== $source) { $source = self::SOURCE_JHU; }
        $endpoint = rest_url('sc-library/v1/institutional-sources/' . $source . '/search');
        wp_enqueue_style('sc-library-institutional-research-v570');
        wp_enqueue_script('sc-library-institutional-research-v570');
        ob_start();
        ?>
        <section class="sc-library-institutional-source" data-sc-institutional-source="<?php echo esc_attr($source); ?>" data-endpoint="<?php echo esc_url($endpoint); ?>">
            <div class="sc-library-institutional-source__header">
                <p class="sc-library-institutional-source__kicker"><?php esc_html_e('Institutional Research Source', 'sustainable-catalyst-library'); ?></p>
                <h2><?php esc_html_e('Johns Hopkins Research Data', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Discover public research metadata from the Johns Hopkins Research Data Repository. Sustainable Catalyst preserves source identity, persistent identifiers, provenance, and reuse terms; source inclusion does not imply endorsement or partnership.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <form class="sc-library-institutional-source__search" role="search">
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Search Johns Hopkins research data', 'sustainable-catalyst-library'); ?></span>
                    <input type="search" name="q" placeholder="<?php echo esc_attr__('Search datasets, topics, authors…', 'sustainable-catalyst-library'); ?>">
                </label>
                <button type="submit"><?php esc_html_e('Search Hopkins', 'sustainable-catalyst-library'); ?></button>
            </form>
            <p class="sc-library-institutional-source__status" aria-live="polite"></p>
            <div class="sc-library-institutional-source__results"></div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
