<?php
if (!defined('ABSPATH')) { exit; }

/** v5.8.4 Biomedical Evidence Grading & Study Design Intelligence. */
final class SC_Library_Biomedical_Evidence_Grading {
    public const VERSION = '5.8.4';
    public const SHORTCODE = 'sc_biomedical_evidence_grading';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-evidence-grading-v584', SC_LIBRARY_URL . 'assets/css/sc-library-evidence-grading-v584.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-evidence-grading-v584', SC_LIBRARY_URL . 'assets/js/sc-library-evidence-grading-v584.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/evidence-grading', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/evidence-grading/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'search'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
                'literature_limit' => ['sanitize_callback' => 'absint', 'default' => 8],
                'trial_limit' => ['sanitize_callback' => 'absint', 'default' => 8],
            ],
        ]);
        register_rest_route('sc-library/v1', '/evidence-grading/trial/(?P<nct_id>NCT[0-9]{8})', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'trial'],
        ]);
    }

    public function manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/evidence-grading');
    }

    public function search(WP_REST_Request $request) {
        return $this->proxy('/v1/evidence-grading/search', [
            'q' => (string)$request->get_param('q'),
            'literature_limit' => min(20, max(1, (int)$request->get_param('literature_limit'))),
            'trial_limit' => min(20, max(1, (int)$request->get_param('trial_limit'))),
        ]);
    }

    public function trial(WP_REST_Request $request) {
        return $this->proxy('/v1/evidence-grading/trial/' . rawurlencode(strtoupper((string)$request['nct_id'])));
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, ['timeout' => 18, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_evidence_grading_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = ['ok' => false, 'detail' => __('Evidence grading returned an invalid response.', 'sustainable-catalyst-library')];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Biomedical Evidence Grading & Study Design',
            'intro' => 'Map biomedical literature and registered trials by study design, integrity signals, and certainty-review requirements without converting metadata into an automated clinical certainty score.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-evidence-grading-v584');
        wp_enqueue_script('sc-library-evidence-grading-v584');
        ob_start(); ?>
        <section class="sc-evidence-grading" data-sc-evidence-grading data-search-endpoint="<?php echo esc_url(rest_url('sc-library/v1/evidence-grading/search')); ?>">
          <header class="sc-evidence-grading__header">
            <p class="sc-evidence-grading__kicker"><?php esc_html_e('Evidence Intelligence', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string)$atts['title']); ?></h2>
            <p><?php echo esc_html((string)$atts['intro']); ?></p>
          </header>
          <form class="sc-evidence-grading__search" role="search">
            <label><span><?php esc_html_e('Research question or topic', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" required placeholder="e.g. semaglutide cardiovascular outcomes"></label>
            <button type="submit"><?php esc_html_e('Map Evidence', 'sustainable-catalyst-library'); ?></button>
          </form>
          <div class="sc-evidence-grading__guardrail">
            <strong><?php esc_html_e('Human appraisal required.', 'sustainable-catalyst-library'); ?></strong>
            <?php esc_html_e('Design classification and certainty-domain signals are metadata-derived. Sustainable Catalyst does not generate a formal GRADE certainty category or a formal risk-of-bias judgment automatically.', 'sustainable-catalyst-library'); ?>
          </div>
          <p class="sc-evidence-grading__status" aria-live="polite"></p>
          <div class="sc-evidence-grading__summary" hidden></div>
          <div class="sc-evidence-grading__results"></div>
          <footer><?php esc_html_e('Research and evidence-review infrastructure only; not patient-specific diagnosis, treatment, or clinical decision support.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
