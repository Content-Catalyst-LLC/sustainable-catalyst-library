<?php
if (!defined('ABSPATH')) { exit; }

/** Energy Systems Intelligence v0.2.0 — Energy Units, Carbon Factors & Conversion Registry. */
final class SC_Library_Energy_Systems_Intelligence {
    public const VERSION = '0.2.0';
    public const SHORTCODE = 'sc_energy_systems_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-energy-systems-v020', SC_LIBRARY_URL . 'assets/css/sc-library-energy-systems-v020.css', [], self::VERSION);
        wp_register_script('sc-library-energy-systems-v020', SC_LIBRARY_URL . 'assets/js/sc-library-energy-systems-v020.js', [], self::VERSION, true);
    }

    public function register_routes(): void {
        $readable = WP_REST_Server::READABLE;
        $open = '__return_true';
        register_rest_route('sc-library/v1', '/energy-systems', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'manifest']]);
        register_rest_route('sc-library/v1', '/energy-systems/concepts', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'concepts'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'concept_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'domain' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'source' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/concept/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'concept']]);
        register_rest_route('sc-library/v1', '/energy-systems/relationships', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'relationships'],
            'args' => [
                'subject' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'predicate' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'object' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 250],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/sources', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'sources']]);
        register_rest_route('sc-library/v1', '/energy-systems/source/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'source']]);
        register_rest_route('sc-library/v1', '/energy-systems/knowledge-map', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'knowledge_map']]);
        register_rest_route('sc-library/v1', '/energy-systems/handoffs', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'handoffs']]);
        register_rest_route('sc-library/v1', '/energy-systems/registry', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'registry']]);
        register_rest_route('sc-library/v1', '/energy-systems/units', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'units']]);
        register_rest_route('sc-library/v1', '/energy-systems/conversion-factors', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'conversion_factors']]);
        register_rest_route('sc-library/v1', '/energy-systems/carbon-factors', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'carbon_factors'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'fuel' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'unit' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/heat-content-factors', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'heat_content_factors'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'fuel' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'unit' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/methodology-rules', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'methodology_rules']]);
        register_rest_route('sc-library/v1', '/energy-systems/convert', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'convert'],
            'args' => [
                'value' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'from' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
                'to' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/carbon-estimate', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'carbon_estimate'],
            'args' => [
                'factor_key' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
                'quantity' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/heat-content-estimate', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'heat_content_estimate'],
            'args' => [
                'factor_key' => ['required' => true, 'sanitize_callback' => 'sanitize_key'],
                'quantity' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function manifest(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems'); }
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
    public function concept(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/concepts/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function relationships(WP_REST_Request $request) {
        $params = array_filter([
            'subject' => sanitize_key((string)$request->get_param('subject')),
            'predicate' => sanitize_key((string)$request->get_param('predicate')),
            'object' => sanitize_key((string)$request->get_param('object')),
            'limit' => min(500, max(1, absint($request->get_param('limit') ?: 250))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/energy-systems/relationships', $params);
    }
    public function sources(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/sources'); }
    public function source(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/sources/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function knowledge_map(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/knowledge-map'); }
    public function handoffs(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/handoffs'); }
    public function registry(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/registry'); }
    public function units(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/units'); }
    public function conversion_factors(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/conversion-factors'); }
    public function carbon_factors(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/carbon-factors', array_filter([
            'q' => trim((string)$request->get_param('q')),
            'fuel' => trim((string)$request->get_param('fuel')),
            'unit' => sanitize_key((string)$request->get_param('unit')),
            'limit' => min(250, max(1, absint($request->get_param('limit') ?: 100))),
        ], static fn($value) => $value !== ''));
    }
    public function heat_content_factors(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/heat-content-factors', array_filter([
            'q' => trim((string)$request->get_param('q')),
            'fuel' => trim((string)$request->get_param('fuel')),
            'unit' => sanitize_key((string)$request->get_param('unit')),
            'limit' => min(250, max(1, absint($request->get_param('limit') ?: 100))),
        ], static fn($value) => $value !== ''));
    }
    public function methodology_rules(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/methodology-rules'); }
    public function convert(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/convert', [
            'value' => trim((string)$request->get_param('value')),
            'from' => sanitize_key((string)$request->get_param('from')),
            'to' => sanitize_key((string)$request->get_param('to')),
        ]);
    }
    public function carbon_estimate(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/carbon-estimate', [
            'factor_key' => sanitize_key((string)$request->get_param('factor_key')),
            'quantity' => trim((string)$request->get_param('quantity')),
        ]);
    }
    public function heat_content_estimate(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/heat-content-estimate', [
            'factor_key' => sanitize_key((string)$request->get_param('factor_key')),
            'quantity' => trim((string)$request->get_param('quantity')),
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
            'intro' => 'Explore the sustainable-energy knowledge foundation and a source-bound registry for energy units, 2020 direct carbon factors, and gross calorific values.',
        ], $atts, self::SHORTCODE);

        wp_enqueue_style('sc-library-energy-systems-v020');
        wp_enqueue_script('sc-library-energy-systems-v020');

        $ep = static fn(string $path): string => rest_url('sc-library/v1/energy-systems' . $path);
        ob_start(); ?>
        <section id="energy-systems-intelligence" class="sc-es" data-sc-energy-systems
            data-map-endpoint="<?php echo esc_url($ep('/knowledge-map')); ?>"
            data-concepts-endpoint="<?php echo esc_url($ep('/concepts')); ?>"
            data-sources-endpoint="<?php echo esc_url($ep('/sources')); ?>"
            data-handoffs-endpoint="<?php echo esc_url($ep('/handoffs')); ?>"
            data-registry-endpoint="<?php echo esc_url($ep('/registry')); ?>"
            data-units-endpoint="<?php echo esc_url($ep('/units')); ?>"
            data-conversion-factors-endpoint="<?php echo esc_url($ep('/conversion-factors')); ?>"
            data-carbon-factors-endpoint="<?php echo esc_url($ep('/carbon-factors')); ?>"
            data-heat-factors-endpoint="<?php echo esc_url($ep('/heat-content-factors')); ?>"
            data-methodology-endpoint="<?php echo esc_url($ep('/methodology-rules')); ?>"
            data-convert-endpoint="<?php echo esc_url($ep('/convert')); ?>"
            data-carbon-estimate-endpoint="<?php echo esc_url($ep('/carbon-estimate')); ?>"
            data-heat-estimate-endpoint="<?php echo esc_url($ep('/heat-content-estimate')); ?>">
            <header class="sc-es__header">
                <p class="sc-es__kicker"><?php esc_html_e('Library Domain Intelligence · v0.2.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-es__domains" aria-label="Energy Systems knowledge domains">
                <span>Energy Systems</span><span>Resources &amp; Conversion</span><span>Renewables</span><span>Bioenergy &amp; Carbon</span><span>Efficiency &amp; Economics</span><span>Sustainability Metrics</span>
            </div>

            <div class="sc-es__guardrail">
                <strong><?php esc_html_e('Source-bound calculation ≠ current emissions inventory.', 'sustainable-catalyst-library'); ?></strong>
                <?php esc_html_e('v0.2.0 activates only the numerical records explicitly supported by the supplied 2020 conversion guide. Every factor retains source, year, geography or source context, emissions boundary, and methodology. No factor is silently promoted to a current default.', 'sustainable-catalyst-library'); ?>
            </div>

            <div class="sc-es__modebar" role="tablist" aria-label="Energy Systems explorers">
                <button type="button" class="sc-es__mode is-active" data-es-mode="registry" role="tab" aria-selected="true">Numeric Registry</button>
                <button type="button" class="sc-es__mode" data-es-mode="map" role="tab" aria-selected="false">Knowledge Map</button>
                <button type="button" class="sc-es__mode" data-es-mode="concepts" role="tab" aria-selected="false">Concept Registry</button>
                <button type="button" class="sc-es__mode" data-es-mode="sources" role="tab" aria-selected="false">Sources &amp; Provenance</button>
                <button type="button" class="sc-es__mode" data-es-mode="handoffs" role="tab" aria-selected="false">Platform Handoffs</button>
            </div>

            <div class="sc-es__panel" data-es-panel="registry">
                <div class="sc-es__panel-heading"><strong>Energy units, carbon factors &amp; conversion registry</strong><span>Reference calculations are bound to the selected source-vintage record. They do not imply present-day UK grid or fuel values.</span></div>
                <p class="sc-es__status" data-es-registry-status aria-live="polite">Loading numerical registry…</p>
                <div class="sc-es__registry-summary" data-es-registry-summary></div>

                <div class="sc-es__calc-grid">
                    <form class="sc-es__calculator" data-es-convert-form>
                        <h3>Energy unit conversion</h3>
                        <label><span>Value</span><input name="value" inputmode="decimal" value="100000" required></label>
                        <label><span>From</span><select name="from" data-es-unit-from required></select></label>
                        <label><span>To</span><select name="to" data-es-unit-to required></select></label>
                        <button type="submit">Convert</button>
                        <div class="sc-es__result" data-es-convert-result aria-live="polite"></div>
                    </form>
                    <form class="sc-es__calculator" data-es-carbon-form>
                        <h3>Direct carbon estimate</h3>
                        <label><span>2020 factor</span><select name="factor_key" data-es-carbon-select required></select></label>
                        <label><span>Quantity</span><input name="quantity" inputmode="decimal" value="100" required></label>
                        <button type="submit">Apply factor</button>
                        <div class="sc-es__result" data-es-carbon-result aria-live="polite"></div>
                    </form>
                    <form class="sc-es__calculator" data-es-heat-form>
                        <h3>Gross heat-content estimate</h3>
                        <label><span>2020 factor</span><select name="factor_key" data-es-heat-select required></select></label>
                        <label><span>Quantity</span><input name="quantity" inputmode="decimal" value="10" required></label>
                        <button type="submit">Apply factor</button>
                        <div class="sc-es__result" data-es-heat-result aria-live="polite"></div>
                    </form>
                </div>

                <div class="sc-es__registry-columns">
                    <div><h3>Energy conversions</h3><div class="sc-es__cards sc-es__cards--compact" data-es-conversion-list></div></div>
                    <div><h3>Direct carbon factors</h3><div class="sc-es__cards sc-es__cards--compact" data-es-carbon-list></div></div>
                    <div><h3>Gross calorific values</h3><div class="sc-es__cards sc-es__cards--compact" data-es-heat-list></div></div>
                </div>
                <div class="sc-es__methodology"><h3>Methodology boundaries</h3><div class="sc-es__cards" data-es-methodology-list></div></div>
            </div>

            <div class="sc-es__panel" data-es-panel="map" hidden>
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
                <div class="sc-es__panel-heading"><strong>Cross-platform handoffs</strong><span>v0.2.0 exposes a Workbench-ready registry contract without claiming the separate Workbench product has been upgraded yet.</span></div>
                <p class="sc-es__status" data-es-handoff-status aria-live="polite">Loading handoff registry…</p>
                <div class="sc-es__cards" data-es-handoff-results></div>
            </div>

            <footer><strong>Next:</strong> v0.3.0 — Energy Sustainability Indicators. The next release will operationalize governed social, economic, and environmental indicators while preserving the same source-vintage and methodology boundaries.</footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
