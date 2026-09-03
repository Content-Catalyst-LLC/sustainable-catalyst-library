<?php
if (!defined('ABSPATH')) { exit; }

/** v5.8.3 Clinical Study & Trial Intelligence. */
final class SC_Library_Clinical_Trial_Intelligence {
    public const VERSION = '5.8.3';
    public const SHORTCODE = 'sc_clinical_trial_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-clinical-trials-v583', SC_LIBRARY_URL . 'assets/css/sc-library-clinical-trials-v583.css', [], SC_LIBRARY_VERSION);
        wp_register_script('sc-library-clinical-trials-v583', SC_LIBRARY_URL . 'assets/js/sc-library-clinical-trials-v583.js', [], SC_LIBRARY_VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/clinical-trials', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/clinical-trials/search', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'search'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'condition' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'intervention' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'sponsor' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'location' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'status' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'phase' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'study_type' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 10],
                'cursor' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            ],
        ]);
        register_rest_route('sc-library/v1', '/clinical-trials/compare', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'compare'],
            'args' => [
                'nct_ids' => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
            ],
        ]);
        register_rest_route('sc-library/v1', '/clinical-trials/(?P<nct_id>NCT[0-9]{8})', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'detail'],
        ]);
    }

    public function manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/clinical-trials');
    }

    public function search(WP_REST_Request $request) {
        $params = [];
        foreach (['q','condition','intervention','sponsor','location','status','phase','study_type','cursor'] as $key) {
            $value = (string)$request->get_param($key);
            if ($value !== '') { $params[$key] = $value; }
        }
        $params['limit'] = min(50, max(1, (int)$request->get_param('limit')));
        return $this->proxy('/v1/clinical-trials/search', $params);
    }

    public function compare(WP_REST_Request $request) {
        return $this->proxy('/v1/clinical-trials/compare', ['nct_ids' => (string)$request->get_param('nct_ids')]);
    }

    public function detail(WP_REST_Request $request) {
        $nct = strtoupper((string)$request['nct_id']);
        return $this->proxy('/v1/clinical-trials/' . rawurlencode($nct));
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, ['timeout' => 15, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_clinical_trials_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = ['ok' => false, 'detail' => __('Clinical trial intelligence returned an invalid response.', 'sustainable-catalyst-library')];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Clinical Study & Trial Intelligence',
            'intro' => 'Compare registered studies by design, population, interventions, endpoints, results state, sponsors, and linked publications.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-clinical-trials-v583');
        wp_enqueue_script('sc-library-clinical-trials-v583');
        $search = rest_url('sc-library/v1/clinical-trials/search');
        $compare = rest_url('sc-library/v1/clinical-trials/compare');
        $detail = rest_url('sc-library/v1/clinical-trials/');
        ob_start(); ?>
        <section class="sc-clinical-trials" data-sc-clinical-trials data-search-endpoint="<?php echo esc_url($search); ?>" data-compare-endpoint="<?php echo esc_url($compare); ?>" data-detail-endpoint="<?php echo esc_url($detail); ?>">
          <header class="sc-clinical-trials__header">
            <p class="sc-clinical-trials__kicker"><?php esc_html_e('Clinical Evidence Infrastructure', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string)$atts['title']); ?></h2>
            <p><?php echo esc_html((string)$atts['intro']); ?></p>
          </header>
          <form class="sc-clinical-trials__search" role="search">
            <label class="sc-clinical-trials__wide"><span><?php esc_html_e('Trial query', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" placeholder="Condition, intervention, NCT ID, trial title…"></label>
            <label><span><?php esc_html_e('Condition', 'sustainable-catalyst-library'); ?></span><input type="text" name="condition" placeholder="e.g. heart failure"></label>
            <label><span><?php esc_html_e('Intervention', 'sustainable-catalyst-library'); ?></span><input type="text" name="intervention" placeholder="e.g. semaglutide"></label>
            <label><span><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></span><select name="status"><option value="">Any status</option><option value="RECRUITING">Recruiting</option><option value="NOT_YET_RECRUITING">Not yet recruiting</option><option value="ACTIVE_NOT_RECRUITING">Active, not recruiting</option><option value="COMPLETED">Completed</option><option value="TERMINATED">Terminated</option><option value="WITHDRAWN">Withdrawn</option><option value="SUSPENDED">Suspended</option></select></label>
            <label><span><?php esc_html_e('Phase', 'sustainable-catalyst-library'); ?></span><select name="phase"><option value="">Any phase</option><option value="EARLY_PHASE1">Early Phase 1</option><option value="PHASE1">Phase 1</option><option value="PHASE2">Phase 2</option><option value="PHASE3">Phase 3</option><option value="PHASE4">Phase 4</option><option value="NA">N/A</option></select></label>
            <details class="sc-clinical-trials__advanced"><summary><?php esc_html_e('More filters', 'sustainable-catalyst-library'); ?></summary><div>
              <label><span><?php esc_html_e('Sponsor', 'sustainable-catalyst-library'); ?></span><input type="text" name="sponsor"></label>
              <label><span><?php esc_html_e('Location', 'sustainable-catalyst-library'); ?></span><input type="text" name="location"></label>
              <label><span><?php esc_html_e('Study type', 'sustainable-catalyst-library'); ?></span><select name="study_type"><option value="">Any type</option><option value="INTERVENTIONAL">Interventional</option><option value="OBSERVATIONAL">Observational</option><option value="EXPANDED_ACCESS">Expanded access</option></select></label>
            </div></details>
            <div class="sc-clinical-trials__actions"><button type="submit"><?php esc_html_e('Search Trials', 'sustainable-catalyst-library'); ?></button><button type="button" class="sc-clinical-trials__compare" disabled><?php esc_html_e('Compare Selected', 'sustainable-catalyst-library'); ?></button></div>
          </form>
          <p class="sc-clinical-trials__notice"><?php esc_html_e('Registry records, posted results, and linked publications are distinct evidence objects. No linked publication does not prove that a study is unpublished. Research use only; not patient-specific clinical decision support.', 'sustainable-catalyst-library'); ?></p>
          <p class="sc-clinical-trials__status" aria-live="polite"></p>
          <div class="sc-clinical-trials__comparison" hidden></div>
          <div class="sc-clinical-trials__results"></div>
          <footer><?php esc_html_e('ClinicalTrials.gov data are stewarded by the U.S. National Library of Medicine. Source inclusion does not imply endorsement.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
