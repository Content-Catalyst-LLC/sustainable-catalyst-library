<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Library v5.10.0 — Institutional Research Network II.
 *
 * Public, read-only proxy and interface over the governed Python backend.
 * Repository discovery is metadata discovery only; it does not establish
 * entitlement, reuse permission, affiliation, partnership, or endorsement.
 */
final class SC_Library_Institutional_Research_Network {
    public const VERSION = '5.10.0';
    public const SHORTCODE = 'sc_institutional_research_network';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style(
            'sc-library-institutional-network-v5100',
            SC_LIBRARY_URL . 'assets/css/sc-library-institutional-network-v5100.css',
            [],
            SC_LIBRARY_VERSION
        );
        wp_register_script(
            'sc-library-institutional-network-v5100',
            SC_LIBRARY_URL . 'assets/js/sc-library-institutional-network-v5100.js',
            [],
            SC_LIBRARY_VERSION,
            true
        );
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/institutional-research-network', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/institutional-research-network/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'search'],
            'args' => $this->query_args(),
        ]);
        register_rest_route('sc-library/v1', '/institutional-research-network/graph', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'graph'],
            'args' => $this->query_args(),
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    private function query_args(): array {
        return [
            'q' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn($value) => is_string($value) && trim($value) !== '' && strlen($value) <= 500,
            ],
            'sources' => [
                'required' => false,
                'sanitize_callback' => static function ($value) {
                    $parts = array_filter(array_map('sanitize_key', explode(',', (string)$value)));
                    return implode(',', array_slice(array_values(array_unique($parts)), 0, 8));
                },
                'default' => '',
            ],
            'limit_per_source' => [
                'required' => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn($value) => (int)$value >= 1 && (int)$value <= 25,
                'default' => 8,
            ],
        ];
    }

    public function manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/institutional-research-network');
    }

    public function search(WP_REST_Request $request) {
        return $this->proxy('/v1/institutional-research-network/search', $this->query_params($request));
    }

    public function graph(WP_REST_Request $request) {
        return $this->proxy('/v1/institutional-research-network/graph', $this->query_params($request));
    }

    /** @return array<string,mixed> */
    private function query_params(WP_REST_Request $request): array {
        $query = trim((string)$request->get_param('q'));
        $sources = trim((string)$request->get_param('sources'));
        $limit = min(25, max(1, (int)$request->get_param('limit_per_source')));
        $params = ['q' => $query, 'limit_per_source' => $limit];
        if ($sources !== '') { $params['sources'] = $sources; }
        return $params;
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error(
                'sc_library_backend_not_configured',
                __('Library backend is not configured.', 'sustainable-catalyst-library'),
                ['status' => 503]
            );
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error(
                'sc_library_institutional_network_unavailable',
                $response->get_error_message(),
                ['status' => 503]
            );
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = [
                'ok' => false,
                'detail' => __('Institutional Research Network returned an invalid response.', 'sustainable-catalyst-library'),
            ];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    /** @return array<int,array<string,string>> */
    public static function network_sources(): array {
        return [
            [
                'id' => 'mit',
                'name' => 'DSpace@MIT',
                'kind' => 'university',
                'type' => 'Institutional repository',
                'mode' => 'LIVE METADATA',
                'detail' => 'MIT research repository · DSpace REST discovery',
                'connector' => 'mit-dspace',
                'homepage' => 'https://dspace.mit.edu/',
            ],
            [
                'id' => 'harvard',
                'name' => 'Harvard Dataverse',
                'kind' => 'university',
                'type' => 'Research data repository',
                'mode' => 'LIVE METADATA',
                'detail' => 'Datasets · DOI metadata · license observations',
                'connector' => 'harvard-dataverse',
                'homepage' => 'https://dataverse.harvard.edu/',
            ],
            [
                'id' => 'johns-hopkins-dataverse',
                'name' => 'Johns Hopkins Research Data Repository',
                'kind' => 'university',
                'type' => 'Research data repository',
                'mode' => 'LIVE METADATA',
                'detail' => 'Datasets · persistent identifiers · provenance',
                'connector' => 'johns-hopkins-dataverse',
                'homepage' => 'https://archive.data.jhu.edu/',
            ],
            [
                'id' => 'ucd',
                'name' => 'Research Repository UCD',
                'kind' => 'university',
                'type' => 'Institutional repository',
                'mode' => 'BOUNDED HARVEST',
                'detail' => 'OAI-PMH metadata · bounded local filtering',
                'connector' => 'ucd-research-repository',
                'homepage' => 'https://researchrepository.ucd.ie/',
            ],
        ];
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Institutional Research Network',
            'intro' => 'Search governed public metadata across university repositories while preserving institutional identity, repository provenance, persistent identifiers, and per-record rights observations.',
        ], $atts, self::SHORTCODE);

        wp_enqueue_style('sc-library-institutional-network-v5100');
        wp_enqueue_script('sc-library-institutional-network-v5100');

        $graph_endpoint = rest_url('sc-library/v1/institutional-research-network/graph');
        $sources = self::network_sources();

        ob_start(); ?>
        <section class="sc-irn" data-sc-institutional-research-network data-graph-endpoint="<?php echo esc_url($graph_endpoint); ?>">
            <header class="sc-irn__header">
                <p class="sc-irn__kicker"><?php esc_html_e('Institutional Research Infrastructure', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-irn__source-strip" aria-label="Institutional sources">
                <?php foreach ($sources as $source) : ?>
                    <span data-source-key="<?php echo esc_attr($source['connector']); ?>"><?php echo esc_html($source['name']); ?></span>
                <?php endforeach; ?>
            </div>

            <form class="sc-irn__search" role="search">
                <label>
                    <span><?php esc_html_e('Research topic, title, author, DOI, or keyword', 'sustainable-catalyst-library'); ?></span>
                    <input type="search" name="q" required maxlength="500" placeholder="e.g. climate adaptation urban heat">
                </label>
                <fieldset>
                    <legend><?php esc_html_e('Sources', 'sustainable-catalyst-library'); ?></legend>
                    <?php foreach ($sources as $source) : ?>
                        <label class="sc-irn__check"><input type="checkbox" name="sources" value="<?php echo esc_attr($source['connector']); ?>" checked> <span><?php echo esc_html($source['name']); ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit"><?php esc_html_e('Search Research Network', 'sustainable-catalyst-library'); ?></button>
            </form>

            <div class="sc-irn__guardrail">
                <strong><?php esc_html_e('Metadata discovery is not entitlement.', 'sustainable-catalyst-library'); ?></strong>
                <?php esc_html_e('A visible record does not establish permission to download or reuse underlying content. Source inclusion does not imply affiliation, partnership, endorsement, or institutional approval.', 'sustainable-catalyst-library'); ?>
            </div>

            <p class="sc-irn__status" aria-live="polite"></p>
            <div class="sc-irn__metrics" hidden></div>
            <div class="sc-irn__sources" hidden></div>
            <div class="sc-irn__graph" hidden></div>
            <div class="sc-irn__results"></div>
            <footer><?php esc_html_e('Source identity and rights observations are preserved. Unknown rights remain review-required.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php
        return (string)ob_get_clean();
    }
}
