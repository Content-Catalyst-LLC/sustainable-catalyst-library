<?php
if (!defined('ABSPATH')) { exit; }

/** Energy Systems Intelligence v0.1.0 — Sustainable Energy Knowledge Foundation. */
final class SC_Library_Energy_Systems_Intelligence {
    public const VERSION = '0.1.0';
    public const SHORTCODE = 'sc_energy_systems_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-energy-systems-v010', SC_LIBRARY_URL . 'assets/css/sc-library-energy-systems-v010.css', [], self::VERSION);
        wp_register_script('sc-library-energy-systems-v010', SC_LIBRARY_URL . 'assets/js/sc-library-energy-systems-v010.js', [], self::VERSION, true);
    }

    public function register_routes(): void {
        register_rest_route('sc-library/v1', '/energy-systems', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'manifest'],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/concepts', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'concepts'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'concept_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'domain' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'source' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/concept/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'concept'],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/relationships', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'relationships'],
            'args' => [
                'subject' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'predicate' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'object' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 250],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/sources', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'sources'],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/source/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'source'],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/knowledge-map', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'knowledge_map'],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/handoffs', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'handoffs'],
        ]);
    }

    public function manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/energy-systems');
    }

    public function concepts(WP_REST_Request $request) {
        $params = array_filter([
            'q' => trim((string)$request->get_param('q')),
            'concept_type' => sanitize_key((string)$request->get_param('concept_type')),
            'domain' => sanitize_key((string)$request->get_param('domain')),
            'source' => sanitize_key((string)$request->get_param('source')),
            'limit' => min(250, max(1, absint($request->get_param('limit') ?: 100))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/energy-systems/concepts', $params);
    }

    public function concept(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/concepts/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function relationships(WP_REST_Request $request) {
        $params = array_filter([
            'subject' => sanitize_key((string)$request->get_param('subject')),
            'predicate' => sanitize_key((string)$request->get_param('predicate')),
            'object' => sanitize_key((string)$request->get_param('object')),
            'limit' => min(500, max(1, absint($request->get_param('limit') ?: 250))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/energy-systems/relationships', $params);
    }

    public function sources(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/energy-systems/sources');
    }

    public function source(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/sources/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function knowledge_map(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/energy-systems/knowledge-map');
    }

    public function handoffs(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/energy-systems/handoffs');
    }

    private function proxy(string $path, array $params = []) {
        if (!SC_Library_Python_Backend::configured()) {
            return new WP_Error('sc_library_backend_not_configured', __('Library backend is not configured.', 'sustainable-catalyst-library'), ['status' => 503]);
        }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('sc_library_energy_systems_unavailable', $response->get_error_message(), ['status' => 503]);
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = ['ok' => false, 'detail' => __('Energy Systems Intelligence returned an invalid response.', 'sustainable-catalyst-library')];
        }
        return new WP_REST_Response($body, $code ?: 502);
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Energy Systems Intelligence',
            'intro' => 'Explore a governed sustainable-energy knowledge foundation spanning energy systems, resources and conversion, renewable technologies, biological carbon pathways, efficiency and economics, and sustainability metrics.',
        ], $atts, self::SHORTCODE);

        wp_enqueue_style('sc-library-energy-systems-v010');
        wp_enqueue_script('sc-library-energy-systems-v010');

        $manifest = rest_url('sc-library/v1/energy-systems');
        $concepts = rest_url('sc-library/v1/energy-systems/concepts');
        $relationships = rest_url('sc-library/v1/energy-systems/relationships');
        $sources = rest_url('sc-library/v1/energy-systems/sources');
        $map = rest_url('sc-library/v1/energy-systems/knowledge-map');
        $handoffs = rest_url('sc-library/v1/energy-systems/handoffs');

        ob_start(); ?>
        <section id="energy-systems-intelligence" class="sc-es" data-sc-energy-systems
            data-manifest-endpoint="<?php echo esc_url($manifest); ?>"
            data-concepts-endpoint="<?php echo esc_url($concepts); ?>"
            data-relationships-endpoint="<?php echo esc_url($relationships); ?>"
            data-sources-endpoint="<?php echo esc_url($sources); ?>"
            data-map-endpoint="<?php echo esc_url($map); ?>"
            data-handoffs-endpoint="<?php echo esc_url($handoffs); ?>">
            <header class="sc-es__header">
                <p class="sc-es__kicker"><?php esc_html_e('Library Domain Intelligence · v0.1.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-es__domains" aria-label="Energy Systems knowledge domains">
                <span>Energy Systems</span><span>Resources &amp; Conversion</span><span>Renewables</span><span>Bioenergy &amp; Carbon</span><span>Efficiency &amp; Economics</span><span>Sustainability Metrics</span>
            </div>

            <div class="sc-es__guardrail">
                <strong><?php esc_html_e('Knowledge foundation ≠ energy calculator or technology ranking.', 'sustainable-catalyst-library'); ?></strong>
                <?php esc_html_e('v0.1.0 preserves source provenance, terminology, typed relationships, source vintage, SDG mapping, and explicit platform handoffs. It does not activate historical conversion factors, calculate indicators, infer resource potential, model scenarios, rank technologies, or recommend policy.', 'sustainable-catalyst-library'); ?>
            </div>

            <div class="sc-es__modebar" role="tablist" aria-label="Energy Systems explorers">
                <button type="button" class="sc-es__mode is-active" data-es-mode="map" role="tab" aria-selected="true">Knowledge Map</button>
                <button type="button" class="sc-es__mode" data-es-mode="concepts" role="tab" aria-selected="false">Concept Registry</button>
                <button type="button" class="sc-es__mode" data-es-mode="sources" role="tab" aria-selected="false">Sources &amp; Provenance</button>
                <button type="button" class="sc-es__mode" data-es-mode="handoffs" role="tab" aria-selected="false">Platform Handoffs</button>
            </div>

            <div class="sc-es__panel" data-es-panel="map">
                <div class="sc-es__panel-heading"><strong>System knowledge map</strong><span>Six linked knowledge domains plus source-grounded relationships and module SDG mappings.</span></div>
                <p class="sc-es__status" data-es-map-status aria-live="polite">Loading knowledge map…</p>
                <div class="sc-es__map" data-es-map-results></div>
            </div>

            <div class="sc-es__panel" data-es-panel="concepts" hidden>
                <div class="sc-es__panel-heading"><strong>Governed concept registry</strong><span>Search concepts without converting a match into evidence, suitability, or a current-state assertion.</span></div>
                <form class="sc-es__search" data-es-concept-form role="search">
                    <label class="sc-es__query"><span>Concept or topic</span><input type="search" name="q" maxlength="500" placeholder="e.g. energy security, solar, biochar, decoupling"></label>
                    <label><span>Domain</span><select name="domain"><option value="">All domains</option><option value="energy-sustainable-development">Energy &amp; Sustainable Development</option><option value="resources-conversion-end-use">Resources, Conversion &amp; End Use</option><option value="renewable-technologies">Renewable Energy Technologies</option><option value="biological-carbon-bioenergy">Biological Carbon Capture, Storage &amp; Bioenergy</option><option value="efficiency-economics-analysis">Efficiency, Economics &amp; Analysis</option><option value="sustainability-metrics-impacts">Sustainability Metrics &amp; Environmental Impacts</option></select></label>
                    <div class="sc-es__actions"><button type="submit">Explore Concepts</button><button type="reset" class="sc-es__secondary">Reset</button></div>
                </form>
                <p class="sc-es__status" data-es-concept-status aria-live="polite"></p>
                <div class="sc-es__cards" data-es-concept-results></div>
            </div>

            <div class="sc-es__panel" data-es-panel="sources" hidden>
                <div class="sc-es__panel-heading"><strong>Sources &amp; provenance</strong><span>Historical and course sources retain their date and role; source presence does not make old numerical values current.</span></div>
                <p class="sc-es__status" data-es-source-status aria-live="polite">Loading source registry…</p>
                <div class="sc-es__cards" data-es-source-results></div>
            </div>

            <div class="sc-es__panel" data-es-panel="handoffs" hidden>
                <div class="sc-es__panel-heading"><strong>Cross-platform handoffs</strong><span>Available targets are distinguished from planned integrations so the interface never overstates capability.</span></div>
                <p class="sc-es__status" data-es-handoff-status aria-live="polite">Loading handoff registry…</p>
                <div class="sc-es__cards" data-es-handoff-results></div>
            </div>

            <footer>
                <strong>Next:</strong> v0.2.0 — Energy Units, Carbon Factors &amp; Conversion Registry. Numerical factors will be versioned by source, geography, year, methodology, and emissions boundary before Workbench calculations are activated.
            </footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
