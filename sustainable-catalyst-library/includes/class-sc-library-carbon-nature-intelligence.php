<?php
if (!defined('ABSPATH')) { exit; }

/** Carbon & Nature Intelligence v0.3.0 — Carbon Evidence & Methodology Graph. */
final class SC_Library_Carbon_Nature_Intelligence {
    public const VERSION = '0.3.0';
    public const SHORTCODE = 'sc_carbon_nature_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-carbon-nature-v030', SC_LIBRARY_URL . 'assets/css/sc-library-carbon-nature-v030.css', [], self::VERSION);
        wp_register_script('sc-library-carbon-nature-v030', SC_LIBRARY_URL . 'assets/js/sc-library-carbon-nature-v030.js', [], self::VERSION, true);
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
        register_rest_route('sc-library/v1', '/carbon-nature/evidence', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'evidence'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'record_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'authority_class' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'concept' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'measure' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'methodology' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/evidence/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'evidence_record'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/methodologies', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'methodologies'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'family' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'measure' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'outcome' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 50],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/methodology/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'methodology'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/evidence-graph', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'evidence_graph'],
            'args' => [
                'node_type' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'node_key' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'predicate' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 200],
            ],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/evidence-graph/neighborhood/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'evidence_graph_neighborhood'],
            'args' => [
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
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

    public function evidence(WP_REST_Request $request) {
        $params = array_filter([
            'q' => trim((string)$request->get_param('q')),
            'record_type' => sanitize_key((string)$request->get_param('record_type')),
            'authority_class' => sanitize_key((string)$request->get_param('authority_class')),
            'concept' => sanitize_key((string)$request->get_param('concept')),
            'measure' => sanitize_key((string)$request->get_param('measure')),
            'methodology' => sanitize_key((string)$request->get_param('methodology')),
            'limit' => min(100, max(1, (int)$request->get_param('limit'))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/evidence', $params);
    }

    public function evidence_record(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/evidence/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function methodologies(WP_REST_Request $request) {
        $params = array_filter([
            'q' => trim((string)$request->get_param('q')),
            'family' => sanitize_key((string)$request->get_param('family')),
            'measure' => sanitize_key((string)$request->get_param('measure')),
            'outcome' => sanitize_key((string)$request->get_param('outcome')),
            'limit' => min(100, max(1, (int)$request->get_param('limit'))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/methodologies', $params);
    }

    public function methodology(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/methodologies/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function evidence_graph(WP_REST_Request $request) {
        $params = array_filter([
            'node_type' => sanitize_key((string)$request->get_param('node_type')),
            'node_key' => sanitize_key((string)$request->get_param('node_key')),
            'predicate' => sanitize_key((string)$request->get_param('predicate')),
            'limit' => min(500, max(1, (int)$request->get_param('limit'))),
        ], static fn($value) => $value !== '');
        return $this->proxy('/v1/carbon-nature/evidence-graph', $params);
    }

    public function evidence_graph_neighborhood(WP_REST_Request $request) {
        return $this->proxy(
            '/v1/carbon-nature/evidence-graph/neighborhood/' . rawurlencode(sanitize_key((string)$request['key'])),
            ['limit' => min(300, max(1, (int)$request->get_param('limit')))]
        );
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
            'intro' => 'Explore governed AFOLU and nature-based carbon measures together with methodology profiles, evidence records, and explicit evidence-method relationships. Associations support research discovery; they do not determine project eligibility or validate claims.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-carbon-nature-v030');
        wp_enqueue_script('sc-library-carbon-nature-v030');
        $measures = rest_url('sc-library/v1/carbon-nature/measures');
        $evidence = rest_url('sc-library/v1/carbon-nature/evidence');
        $methodologies = rest_url('sc-library/v1/carbon-nature/methodologies');
        $graph = rest_url('sc-library/v1/carbon-nature/evidence-graph');
        $context = rest_url('sc-library/v1/carbon-nature/research-context');
        ob_start(); ?>
        <section class="sc-cn" data-sc-carbon-nature
            data-measures-endpoint="<?php echo esc_url($measures); ?>"
            data-evidence-endpoint="<?php echo esc_url($evidence); ?>"
            data-methodologies-endpoint="<?php echo esc_url($methodologies); ?>"
            data-graph-endpoint="<?php echo esc_url($graph); ?>"
            data-context-endpoint="<?php echo esc_url($context); ?>">
            <header class="sc-cn__header">
                <p class="sc-cn__kicker"><?php esc_html_e('Library Domain Intelligence · v0.3.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-cn__domains" aria-label="Carbon and Nature domains">
                <span>AFOLU</span><span>Nature-Based Solutions</span><span>Carbon Farming</span><span>Soil Organic Carbon</span><span>MRV</span><span>Evidence</span>
            </div>

            <div class="sc-cn__modebar" role="tablist" aria-label="Carbon and Nature explorers">
                <button type="button" class="sc-cn__mode is-active" data-cn-mode="graph" role="tab" aria-selected="true">Evidence &amp; Methodology Graph</button>
                <button type="button" class="sc-cn__mode" data-cn-mode="measures" role="tab" aria-selected="false">Measure Registry</button>
            </div>

            <div class="sc-cn__panel" data-cn-panel="graph">
                <div class="sc-cn__registry-heading">
                    <div><strong><?php esc_html_e('Carbon Evidence & Methodology Graph', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Typed relationships with explicit non-inference guardrails.', 'sustainable-catalyst-library'); ?></span></div>
                </div>
                <form class="sc-cn__graph-search" role="search">
                    <label class="sc-cn__query"><span><?php esc_html_e('Graph node key', 'sustainable-catalyst-library'); ?></span><input type="search" name="node_key" maxlength="180" placeholder="e.g. soil-organic-carbon, cover-crop-system, soc-direct-measurement"></label>
                    <label><span><?php esc_html_e('Node type', 'sustainable-catalyst-library'); ?></span><select name="node_type"><option value="">All node types</option><option value="concept">Concept</option><option value="measure">Measure</option><option value="methodology">Methodology</option><option value="evidence">Evidence</option></select></label>
                    <div class="sc-cn__actions"><button type="submit"><?php esc_html_e('Explore Graph', 'sustainable-catalyst-library'); ?></button><button type="reset" class="sc-cn__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button></div>
                </form>
                <div class="sc-cn__guardrail"><strong><?php esc_html_e('Graph edge ≠ proof or eligibility.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v0.3.0 links governed concepts, measures, methodology profiles, and evidence records. It does not automatically validate a claim, select an approved methodology, determine project eligibility, or quantify sequestration.', 'sustainable-catalyst-library'); ?></div>
                <p class="sc-cn__status" data-cn-graph-status aria-live="polite"></p>
                <div class="sc-cn__graph-summary" data-cn-graph-summary></div>
                <div class="sc-cn__graph-results" data-cn-graph-results></div>
            </div>

            <div class="sc-cn__panel" data-cn-panel="measures" hidden>
                <div class="sc-cn__registry-heading">
                    <div><strong><?php esc_html_e('Carbon Sequestration Measure Registry', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('v0.2.0 capability preserved and linked to evidence/methodology context.', 'sustainable-catalyst-library'); ?></span></div>
                </div>
                <form class="sc-cn__measure-search" role="search">
                    <label class="sc-cn__query"><span><?php esc_html_e('Measure or context', 'sustainable-catalyst-library'); ?></span><input type="search" name="q" maxlength="500" placeholder="e.g. cover crops, peatland, methane, biomass"></label>
                    <label><span><?php esc_html_e('Family', 'sustainable-catalyst-library'); ?></span><select name="family"><option value="">All families</option><option value="cropland-soil-carbon">Cropland soil carbon</option><option value="woody-biomass-and-agroforestry">Woody biomass &amp; agroforestry</option><option value="woody-biomass-and-forest">Woodland &amp; forest</option><option value="grassland">Grassland</option><option value="wetland-and-peatland">Wetland &amp; peatland</option></select></label>
                    <label><span><?php esc_html_e('System', 'sustainable-catalyst-library'); ?></span><select name="system"><option value="">All systems</option><option value="cropland">Cropland</option><option value="grassland">Grassland</option><option value="agroforestry-system">Agroforestry</option><option value="forest-woodland">Forest &amp; woodland</option><option value="wetland">Wetland</option><option value="peatland">Peatland</option><option value="livestock-system">Livestock system</option></select></label>
                    <label><span><?php esc_html_e('Carbon pool', 'sustainable-catalyst-library'); ?></span><select name="pool"><option value="">All pools</option><option value="soil-organic-carbon">Soil organic carbon</option><option value="aboveground-biomass">Aboveground biomass</option><option value="belowground-biomass">Belowground biomass</option><option value="litter">Litter</option><option value="dead-wood">Dead wood</option></select></label>
                    <label><span><?php esc_html_e('Gas', 'sustainable-catalyst-library'); ?></span><select name="gas"><option value="">All gases</option><option value="carbon-dioxide">CO₂</option><option value="methane">CH₄</option><option value="nitrous-oxide">N₂O</option></select></label>
                    <div class="sc-cn__actions"><button type="submit"><?php esc_html_e('Explore Measures', 'sustainable-catalyst-library'); ?></button><button type="reset" class="sc-cn__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button></div>
                </form>
                <p class="sc-cn__status" data-cn-measure-status aria-live="polite"></p>
                <div class="sc-cn__results" data-cn-measure-results></div>
            </div>

            <footer><?php esc_html_e('v0.3.0 establishes evidence and methodology graph structure. Project objects and provenance arrive in v0.4.0; AFOLU Research Librarian reasoning in v0.5.0; scientific calculation and project MRV remain later Lab/Workbench/Decision Studio capabilities.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }

}
