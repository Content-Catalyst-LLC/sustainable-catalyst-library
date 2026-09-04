<?php
if (!defined('ABSPATH')) { exit; }

/** v5.9.0 Biomedical Evidence Graph & Evidence Synthesis. */
final class SC_Library_Biomedical_Evidence_Graph {
    public const VERSION = '5.9.0';
    public const SHORTCODE = 'sc_biomedical_evidence_graph';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-biomedical-evidence-graph-v590', SC_LIBRARY_URL . 'assets/css/sc-library-biomedical-evidence-graph-v590.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-biomedical-evidence-graph-v590', SC_LIBRARY_URL . 'assets/js/sc-library-biomedical-evidence-graph-v590.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/biomedical-evidence-graph', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/biomedical-evidence-graph/build', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'build'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
                'literature_limit' => ['sanitize_callback' => 'absint', 'default' => 8],
                'trial_limit' => ['sanitize_callback' => 'absint', 'default' => 8],
                'concept_limit' => ['sanitize_callback' => 'absint', 'default' => 3],
                'regulatory_limit' => ['sanitize_callback' => 'absint', 'default' => 2],
            ],
        ]);
        register_rest_route('sc-library/v1', '/biomedical-evidence-graph/synthesis', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'synthesis'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
            ],
        ]);
        register_rest_route('sc-library/v1', '/biomedical-evidence-graph/trial/(?P<nct_id>NCT[0-9]{8})', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'trial'],
        ]);
    }

    public function manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/biomedical-evidence-graph');
    }

    public function build(WP_REST_Request $request) {
        return $this->proxy('/v1/biomedical-evidence-graph/build', [
            'q' => (string)$request->get_param('q'),
            'literature_limit' => min(20, max(1, (int)$request->get_param('literature_limit'))),
            'trial_limit' => min(20, max(1, (int)$request->get_param('trial_limit'))),
            'concept_limit' => min(5, max(1, (int)$request->get_param('concept_limit'))),
            'regulatory_limit' => min(5, max(1, (int)$request->get_param('regulatory_limit'))),
        ]);
    }

    public function synthesis(WP_REST_Request $request) {
        return $this->proxy('/v1/biomedical-evidence-graph/synthesis', [
            'q' => (string)$request->get_param('q'),
        ]);
    }

    public function trial(WP_REST_Request $request) {
        return $this->proxy('/v1/biomedical-evidence-graph/trial/' . rawurlencode(strtoupper((string)$request['nct_id'])));
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, ['timeout' => 25, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_biomedical_evidence_graph_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = ['ok' => false, 'detail' => __('Biomedical evidence graph returned an invalid response.', 'sustainable-catalyst-library')];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Biomedical Evidence Graph & Evidence Synthesis',
            'intro' => 'Connect biomedical literature, registered studies, outcomes, terminology candidates, and regulatory evidence through provenance-backed relationships, then summarize the evidence landscape without inventing causal or clinical conclusions.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-biomedical-evidence-graph-v590');
        wp_enqueue_script('sc-library-biomedical-evidence-graph-v590');
        ob_start(); ?>
        <section class="sc-beg" data-sc-biomedical-evidence-graph data-build-endpoint="<?php echo esc_url(rest_url('sc-library/v1/biomedical-evidence-graph/build')); ?>">
          <header class="sc-beg__header">
            <p class="sc-beg__kicker"><?php esc_html_e('Connected Evidence', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string)$atts['title']); ?></h2>
            <p><?php echo esc_html((string)$atts['intro']); ?></p>
          </header>
          <form class="sc-beg__search" role="search">
            <label><span><?php esc_html_e('Research question or biomedical topic', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" required placeholder="e.g. SGLT2 inhibitors heart failure outcomes"></label>
            <button type="submit"><?php esc_html_e('Build Evidence Graph', 'sustainable-catalyst-library'); ?></button>
          </form>
          <div class="sc-beg__guardrail">
            <strong><?php esc_html_e('Provenance-first synthesis.', 'sustainable-catalyst-library'); ?></strong>
            <?php esc_html_e('Edges represent explicit source relationships or query context. The graph does not assert semantic equivalence, causality, pooled effects, comparative effectiveness, a formal GRADE rating, or a clinical recommendation.', 'sustainable-catalyst-library'); ?>
          </div>
          <p class="sc-beg__status" aria-live="polite"></p>
          <div class="sc-beg__summary" hidden></div>
          <div class="sc-beg__canvas" hidden><svg class="sc-beg__svg" viewBox="0 0 1000 560" role="img" aria-label="Biomedical evidence relationship graph"></svg></div>
          <div class="sc-beg__synthesis" hidden></div>
          <div class="sc-beg__records"></div>
          <footer><?php esc_html_e('Research and evidence-review infrastructure only; not patient-specific diagnosis, treatment, or clinical decision support.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
