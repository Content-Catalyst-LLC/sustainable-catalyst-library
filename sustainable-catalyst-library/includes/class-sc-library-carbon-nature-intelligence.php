<?php
if (!defined('ABSPATH')) { exit; }

/** Carbon & Nature Intelligence v0.2.0 — Carbon Sequestration Measure Registry. */
final class SC_Library_Carbon_Nature_Intelligence {
    public const VERSION = '0.2.0';
    public const SHORTCODE = 'sc_carbon_nature_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-carbon-nature-v020', SC_LIBRARY_URL . 'assets/css/sc-library-carbon-nature-v020.css', [], self::VERSION);
        wp_register_script('sc-library-carbon-nature-v020', SC_LIBRARY_URL . 'assets/js/sc-library-carbon-nature-v020.js', [], self::VERSION, true);
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
        register_rest_route('sc-library/v1', '/carbon-nature/measures', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'measures'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'family' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'system' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'pool' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'gas' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'mrv_family' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/measure/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'measure'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/measures/compare', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'compare_measures'],
            'args' => [
                'keys' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
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

    public function measures(WP_REST_Request $request) {
        $params = array_filter([
            'q' => trim((string)$request->get_param('q')),
            'family' => sanitize_key((string)$request->get_param('family')),
            'system' => sanitize_key((string)$request->get_param('system')),
            'pool' => sanitize_key((string)$request->get_param('pool')),
            'gas' => sanitize_key((string)$request->get_param('gas')),
            'mrv_family' => sanitize_key((string)$request->get_param('mrv_family')),
            'limit' => min(100, max(1, (int)$request->get_param('limit'))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/measures', $params);
    }

    public function measure(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/measures/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function compare_measures(WP_REST_Request $request) {
        $keys = trim((string)$request->get_param('keys'));
        return $this->proxy('/v1/carbon-nature/measures/compare', ['keys' => $keys]);
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
        if (!is_array($body)) {
            $body = ['ok' => false, 'detail' => __('Carbon & Nature Intelligence returned an invalid response.', 'sustainable-catalyst-library')];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Carbon & Nature Intelligence',
            'intro' => 'Explore a governed registry of AFOLU and nature-based carbon sequestration measures, with explicit land-system, carbon-pool, greenhouse-gas, MRV, integrity, co-benefit, risk, and evidence context.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-carbon-nature-v020');
        wp_enqueue_script('sc-library-carbon-nature-v020');
        $measures = rest_url('sc-library/v1/carbon-nature/measures');
        $context = rest_url('sc-library/v1/carbon-nature/research-context');
        ob_start(); ?>
        <section class="sc-cn" data-sc-carbon-nature data-measures-endpoint="<?php echo esc_url($measures); ?>" data-context-endpoint="<?php echo esc_url($context); ?>">
            <header class="sc-cn__header">
                <p class="sc-cn__kicker"><?php esc_html_e('Library Domain Intelligence · v0.2.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>
            <div class="sc-cn__domains" aria-label="Carbon and Nature domains">
                <span>AFOLU</span><span>Nature-Based Solutions</span><span>Carbon Farming</span><span>Soil Organic Carbon</span><span>MRV</span><span>Integrity</span>
            </div>
            <div class="sc-cn__registry-heading">
                <div><strong><?php esc_html_e('Carbon Sequestration Measure Registry', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Structured discovery, not project scoring.', 'sustainable-catalyst-library'); ?></span></div>
            </div>
            <form class="sc-cn__measure-search" role="search">
                <label class="sc-cn__query"><span><?php esc_html_e('Measure or context', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" maxlength="500" placeholder="e.g. cover crops, peatland, methane, biomass"></label>
                <label><span><?php esc_html_e('Family', 'sustainable-catalyst-library'); ?></span><select name="family"><option value="">All families</option><option value="cropland-soil-carbon">Cropland soil carbon</option><option value="woody-biomass-and-agroforestry">Woody biomass &amp; agroforestry</option><option value="woody-biomass-and-forest">Woodland &amp; forest</option><option value="grassland">Grassland</option><option value="wetland-and-peatland">Wetland &amp; peatland</option></select></label>
                <label><span><?php esc_html_e('System', 'sustainable-catalyst-library'); ?></span><select name="system"><option value="">All systems</option><option value="cropland">Cropland</option><option value="grassland">Grassland</option><option value="agroforestry-system">Agroforestry</option><option value="forest-woodland">Forest &amp; woodland</option><option value="wetland">Wetland</option><option value="peatland">Peatland</option><option value="livestock-system">Livestock system</option></select></label>
                <label><span><?php esc_html_e('Carbon pool', 'sustainable-catalyst-library'); ?></span><select name="pool"><option value="">All pools</option><option value="soil-organic-carbon">Soil organic carbon</option><option value="aboveground-biomass">Aboveground biomass</option><option value="belowground-biomass">Belowground biomass</option><option value="litter">Litter</option><option value="dead-wood">Dead wood</option></select></label>
                <label><span><?php esc_html_e('Gas', 'sustainable-catalyst-library'); ?></span><select name="gas"><option value="">All gases</option><option value="carbon-dioxide">CO₂</option><option value="methane">CH₄</option><option value="nitrous-oxide">N₂O</option></select></label>
                <div class="sc-cn__actions"><button type="submit"><?php esc_html_e('Explore Measures', 'sustainable-catalyst-library'); ?></button><button type="reset" class="sc-cn__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button></div>
            </form>
            <div class="sc-cn__guardrail"><strong><?php esc_html_e('Registry entry ≠ project eligibility.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v0.2.0 does not rank measures, estimate tonnes of CO₂e, select a methodology, establish additionality or permanence, issue credits, or certify a project.', 'sustainable-catalyst-library'); ?></div>
            <p class="sc-cn__status" aria-live="polite"></p>
            <div class="sc-cn__results"></div>
            <footer><?php esc_html_e('v0.2.0 structures measure discovery and evidence requirements. Evidence-methodology graphing arrives in v0.3.0; project object/provenance modeling in v0.4.0; AFOLU Research Librarian reasoning in v0.5.0.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
