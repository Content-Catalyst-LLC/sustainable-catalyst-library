<?php
if (!defined('ABSPATH')) { exit; }

/** Carbon & Nature Intelligence v0.1.0 — AFOLU & Nature-Based Solutions Knowledge Foundation. */
final class SC_Library_Carbon_Nature_Intelligence {
    public const VERSION = '0.1.0';
    public const SHORTCODE = 'sc_carbon_nature_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-carbon-nature-v010', SC_LIBRARY_URL . 'assets/css/sc-library-carbon-nature-v010.css', [], self::VERSION);
        wp_register_script('sc-library-carbon-nature-v010', SC_LIBRARY_URL . 'assets/js/sc-library-carbon-nature-v010.js', [], self::VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/carbon-nature', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/concepts', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'concepts'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'concept_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'domain' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/concept/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'concept'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/relationships', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'relationships'],
            'args' => [
                'subject' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'predicate' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'object' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/research-context', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'research_context'],
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 12],
            ],
        ]);
    }

    public function manifest(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/carbon-nature'); }
    public function concepts(WP_REST_Request $request) {
        $params = array_filter([
            'q' => trim((string)$request->get_param('q')),
            'concept_type' => sanitize_key((string)$request->get_param('concept_type')),
            'domain' => sanitize_key((string)$request->get_param('domain')),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/concepts', $params);
    }
    public function concept(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/concepts/' . rawurlencode(sanitize_key((string)$request['key'])));
    }
    public function relationships(WP_REST_Request $request) {
        $params = array_filter([
            'subject' => sanitize_key((string)$request->get_param('subject')),
            'predicate' => sanitize_key((string)$request->get_param('predicate')),
            'object' => sanitize_key((string)$request->get_param('object')),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/relationships', $params);
    }
    public function research_context(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/research-context', [
            'q' => trim((string)$request->get_param('q')),
            'limit' => min(30, max(1, (int)$request->get_param('limit'))),
        ]);
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, ['timeout' => 15, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_carbon_nature_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) { $body = ['ok' => false, 'detail' => __('Carbon & Nature Intelligence returned an invalid response.', 'sustainable-catalyst-library')]; }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Carbon & Nature Intelligence',
            'intro' => 'Explore the governed AFOLU and nature-based solutions knowledge foundation: carbon pools, greenhouse gases, land systems, interventions, indicators, MRV methodology families, integrity dimensions, co-benefits, risks, and policy context.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-carbon-nature-v010');
        wp_enqueue_script('sc-library-carbon-nature-v010');
        $concepts = rest_url('sc-library/v1/carbon-nature/concepts');
        $context = rest_url('sc-library/v1/carbon-nature/research-context');
        ob_start(); ?>
        <section class="sc-cn" data-sc-carbon-nature data-concepts-endpoint="<?php echo esc_url($concepts); ?>" data-context-endpoint="<?php echo esc_url($context); ?>">
            <header class="sc-cn__header">
                <p class="sc-cn__kicker"><?php esc_html_e('Library Domain Intelligence · v0.1.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>
            <div class="sc-cn__domains" aria-label="Carbon and Nature domains">
                <span>AFOLU</span><span>Nature-Based Solutions</span><span>Carbon Farming</span><span>Soil Organic Carbon</span><span>MRV</span><span>Carbon Markets &amp; Policy</span>
            </div>
            <form class="sc-cn__search" role="search">
                <label><span><?php esc_html_e('Concept, intervention, indicator, risk, or methodology', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" maxlength="500" placeholder="e.g. soil organic carbon, peatland restoration, permanence"></label>
                <button type="submit"><?php esc_html_e('Explore Foundation', 'sustainable-catalyst-library'); ?></button>
            </form>
            <div class="sc-cn__guardrail"><strong><?php esc_html_e('Knowledge foundation, not a carbon-credit calculator.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('Concept matches and relationships do not establish project eligibility, causality, additionality, permanence, certification, or quantified climate benefit.', 'sustainable-catalyst-library'); ?></div>
            <p class="sc-cn__status" aria-live="polite"></p>
            <div class="sc-cn__results"></div>
            <footer><?php esc_html_e('v0.1.0 establishes governed vocabulary and relationships. SOC calculation, project MRV, whole-farm GHG accounting, and AFOLU Research Librarian reasoning arrive in later subsystem releases.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
