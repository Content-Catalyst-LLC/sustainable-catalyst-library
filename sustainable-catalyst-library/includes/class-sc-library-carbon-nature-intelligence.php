<?php
if (!defined('ABSPATH')) { exit; }

/** Carbon & Nature Intelligence v0.5.0 — AFOLU Research Librarian Intelligence. */
final class SC_Library_Carbon_Nature_Intelligence {
    public const VERSION = '0.5.0';
    public const SHORTCODE = 'sc_carbon_nature_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
        add_filter('sc_library_project_aware_guidance_packet', [$this, 'augment_project_aware_guidance_packet'], 40, 3);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-carbon-nature-v050', SC_LIBRARY_URL . 'assets/css/sc-library-carbon-nature-v050.css', [], self::VERSION);
        wp_register_script('sc-library-carbon-nature-v050', SC_LIBRARY_URL . 'assets/js/sc-library-carbon-nature-v050.js', [], self::VERSION, true);
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
        register_rest_route('sc-library/v1', '/carbon-nature/project-object-model', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'project_object_model'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/project-object-types', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'project_object_types'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/project-object-type/(?P<key>[a-z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'project_object_type'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/provenance-event-types', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'provenance_event_types'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/project-packet-template', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'project_packet_template'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/research-librarian', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'research_librarian_manifest'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/research-librarian/intents', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'research_intents'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/research-librarian/source-roles', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'research_source_roles'],
        ]);
        register_rest_route('sc-library/v1', '/carbon-nature/research-librarian/guidance', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'research_guidance'],
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 12],
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

    public function project_object_model(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/project-object-model');
    }

    public function project_object_types(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/project-object-types');
    }

    public function project_object_type(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/project-object-types/' . rawurlencode(sanitize_key((string)$request['key'])));
    }

    public function provenance_event_types(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/provenance-event-types');
    }

    public function project_packet_template(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/project-packet-template');
    }

    public function research_librarian_manifest(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/research-librarian');
    }

    public function research_intents(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/research-librarian/intents');
    }

    public function research_source_roles(WP_REST_Request $request) {
        unset($request);
        return $this->proxy('/v1/carbon-nature/research-librarian/source-roles');
    }

    public function research_guidance(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/research-librarian/guidance', [
            'q' => trim((string)$request->get_param('q')),
            'limit' => min(30, max(1, (int)$request->get_param('limit'))),
        ]);
    }

    public function research_context(WP_REST_Request $request) {
        return $this->proxy('/v1/carbon-nature/research-context', [
            'q' => trim((string)$request->get_param('q')),
            'limit' => min(30, max(1, (int)$request->get_param('limit'))),
        ]);
    }

    private function backend_array(string $path, array $params = []): ?array {
        if (!SC_Library_Python_Backend::configured()) { return null; }
        $url = SC_Library_Python_Backend::base_url() . $path;
        if ($params) { $url = add_query_arg($params, $url); }
        $response = wp_remote_get($url, ['timeout' => 8, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response) || 200 !== (int)wp_remote_retrieve_response_code($response)) { return null; }
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : null;
    }

    private static function looks_like_carbon_nature_query(string $prompt): bool {
        return 1 === preg_match('/\b(afolu|carbon|sequestration|soil organic|soc|cover crop|tillage|agroforestry|grassland|peat|wetland|methane|nitrous|co2|ghg|mrv|nature[- ]based|biodiversity|inventory|ipcc|credit|carbon market|permanence|leakage|additionality)\b/i', $prompt);
    }

    public function augment_project_aware_guidance_packet($packet, $user_id, $input) {
        unset($user_id);
        if (!is_array($packet)) { return $packet; }
        $prompt = trim(sanitize_textarea_field((string)($input['prompt'] ?? ($packet['prompt'] ?? ''))));
        if (strlen($prompt) < 3 || !self::looks_like_carbon_nature_query($prompt)) { return $packet; }
        $guidance = $this->backend_array('/v1/carbon-nature/research-librarian/guidance', ['q' => $prompt, 'limit' => 12]);
        if (!$guidance || empty($guidance['schema'])) { return $packet; }
        $packet['domain_intelligence'] = is_array($packet['domain_intelligence'] ?? null) ? $packet['domain_intelligence'] : [];
        $packet['domain_intelligence']['carbon_nature'] = [
            'schema' => (string)($guidance['schema'] ?? ''),
            'subsystem_version' => (string)($guidance['subsystem_version'] ?? self::VERSION),
            'reasoning_mode' => (string)($guidance['reasoning_mode'] ?? 'deterministic-domain-research-routing'),
            'detected_intents' => array_slice((array)($guidance['detected_intents'] ?? []), 0, 4),
            'research_question_frame' => array_slice((array)($guidance['research_question_frame'] ?? []), 0, 8),
            'source_plan' => (array)($guidance['source_plan'] ?? []),
            'evidence_gaps' => array_slice((array)($guidance['evidence_gaps'] ?? []), 0, 8),
            'freshness_review' => (array)($guidance['freshness_review'] ?? []),
            'handoffs' => array_slice((array)($guidance['handoffs'] ?? []), 0, 8),
            'guardrails' => (array)($guidance['guardrails'] ?? []),
            'content_fingerprint' => (string)($guidance['content_fingerprint'] ?? ''),
            'private_project_context_sent_to_library_backend' => false,
            'question_only_sent_to_library_backend' => true,
        ];
        $labels = [];
        foreach ((array)($guidance['detected_intents'] ?? []) as $intent) {
            if (!empty($intent['label'])) { $labels[] = sanitize_text_field((string)$intent['label']); }
        }
        $labels = array_slice(array_values(array_unique($labels)), 0, 3);
        $reason = $labels
            ? sprintf(__('Carbon & Nature v0.5.0 detected these AFOLU research intents: %s. Use the domain evidence/source plan and freshness flags before drawing a conclusion.', 'sustainable-catalyst-library'), implode(', ', $labels))
            : __('Carbon & Nature v0.5.0 recognized an AFOLU / nature-based carbon research question. Use the domain evidence/source plan and freshness flags before drawing a conclusion.', 'sustainable-catalyst-library');
        $domain_item = [
            'priority' => 15,
            'title' => __('Use AFOLU domain research guidance', 'sustainable-catalyst-library'),
            'reason' => $reason,
            'target' => '#carbon-nature-intelligence',
            'kind' => 'afolu_domain',
        ];
        $existing = is_array($packet['guidance'] ?? null) ? $packet['guidance'] : [];
        $existing[] = $domain_item;
        usort($existing, static fn($a, $b) => absint($a['priority'] ?? 100) <=> absint($b['priority'] ?? 100));
        $packet['guidance'] = array_slice($existing, 0, 8);
        if (is_array($packet['orchestrator_handoff'] ?? null)) {
            $packet['orchestrator_handoff']['carbon_nature_domain_context_included'] = false;
            $packet['orchestrator_handoff']['private_project_context_included'] = false;
        }
        $packet['checksum_sha256'] = hash('sha256', wp_json_encode($packet));
        return $packet;
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
            'intro' => 'Ask domain-specific AFOLU and nature-based carbon research questions, identify evidence and methodology needs, surface freshness and provenance gaps, and route work into the right Sustainable Catalyst research tools without turning deterministic guidance into a scientific conclusion.',
        ], $atts, self::SHORTCODE);
        wp_enqueue_style('sc-library-carbon-nature-v050');
        wp_enqueue_script('sc-library-carbon-nature-v050');
        $measures = rest_url('sc-library/v1/carbon-nature/measures');
        $evidence = rest_url('sc-library/v1/carbon-nature/evidence');
        $methodologies = rest_url('sc-library/v1/carbon-nature/methodologies');
        $graph = rest_url('sc-library/v1/carbon-nature/evidence-graph');
        $context = rest_url('sc-library/v1/carbon-nature/research-context');
        $object_model = rest_url('sc-library/v1/carbon-nature/project-object-model');
        $packet_template = rest_url('sc-library/v1/carbon-nature/project-packet-template');
        $guidance = rest_url('sc-library/v1/carbon-nature/research-librarian/guidance');
        ob_start(); ?>
        <section id="carbon-nature-intelligence" class="sc-cn" data-sc-carbon-nature
            data-measures-endpoint="<?php echo esc_url($measures); ?>"
            data-evidence-endpoint="<?php echo esc_url($evidence); ?>"
            data-methodologies-endpoint="<?php echo esc_url($methodologies); ?>"
            data-graph-endpoint="<?php echo esc_url($graph); ?>"
            data-context-endpoint="<?php echo esc_url($context); ?>"
            data-object-model-endpoint="<?php echo esc_url($object_model); ?>"
            data-packet-template-endpoint="<?php echo esc_url($packet_template); ?>"
            data-guidance-endpoint="<?php echo esc_url($guidance); ?>">
            <header class="sc-cn__header">
                <p class="sc-cn__kicker"><?php esc_html_e('Library Domain Intelligence · v0.5.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-cn__domains" aria-label="Carbon and Nature domains">
                <span>AFOLU</span><span>Nature-Based Solutions</span><span>Carbon Farming</span><span>Soil Organic Carbon</span><span>MRV</span><span>Evidence</span><span>Provenance</span><span>Research Librarian</span>
            </div>

            <div class="sc-cn__modebar" role="tablist" aria-label="Carbon and Nature explorers">
                <button type="button" class="sc-cn__mode is-active" data-cn-mode="librarian" role="tab" aria-selected="true">AFOLU Research Librarian</button>
                <button type="button" class="sc-cn__mode" data-cn-mode="projects" role="tab" aria-selected="false">Project Objects &amp; Provenance</button>
                <button type="button" class="sc-cn__mode" data-cn-mode="graph" role="tab" aria-selected="false">Evidence &amp; Methodology Graph</button>
                <button type="button" class="sc-cn__mode" data-cn-mode="measures" role="tab" aria-selected="false">Measure Registry</button>
            </div>

            <div class="sc-cn__panel" data-cn-panel="librarian">
                <div class="sc-cn__registry-heading">
                    <div><strong><?php esc_html_e('AFOLU Research Librarian Intelligence', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Deterministic domain routing across concepts, measures, evidence, methods, project objects, freshness, and research handoffs.', 'sustainable-catalyst-library'); ?></span></div>
                </div>
                <form class="sc-cn__librarian-search" role="search">
                    <label class="sc-cn__query"><span><?php esc_html_e('Research question', 'sustainable-catalyst-library'); ?></span><textarea name="q" rows="3" maxlength="500" required placeholder="e.g. How should I compare cover crops and reduced tillage for soil carbon MRV and national inventory reporting?"></textarea></label>
                    <div class="sc-cn__actions"><button type="submit"><?php esc_html_e('Build AFOLU Research Plan', 'sustainable-catalyst-library'); ?></button><button type="reset" class="sc-cn__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button></div>
                </form>
                <div class="sc-cn__guardrail"><strong><?php esc_html_e('Research guidance ≠ research conclusion.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v0.5.0 detects intent, frames questions, identifies source roles and gaps, flags time-sensitive evidence, and routes analysis. It does not invent sequestration rates, rank measures, approve methodologies, assert current rules without current sources, verify projects, or issue credits.', 'sustainable-catalyst-library'); ?></div>
                <p class="sc-cn__status" data-cn-librarian-status aria-live="polite"></p>
                <div class="sc-cn__librarian-summary" data-cn-librarian-summary></div>
                <div class="sc-cn__librarian-results" data-cn-librarian-results></div>
            </div>

            <div class="sc-cn__panel" data-cn-panel="projects" hidden>
                <div class="sc-cn__registry-heading">
                    <div><strong><?php esc_html_e('Carbon Project Object Model & Provenance', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Versioned research objects, explicit links, event lineage, and deterministic integrity fingerprints.', 'sustainable-catalyst-library'); ?></span></div>
                </div>
                <div class="sc-cn__guardrail"><strong><?php esc_html_e('Object validation ≠ scientific verification.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v0.4.0 capability remains available and defines/validates project structure and provenance. It does not persist project packets, approve MRV methods, establish additionality or permanence, calculate sequestration, verify a project, or issue credits.', 'sustainable-catalyst-library'); ?></div>
                <p class="sc-cn__status" data-cn-project-status aria-live="polite"></p>
                <div class="sc-cn__project-summary" data-cn-project-summary></div>
                <div class="sc-cn__project-results" data-cn-project-results></div>
            </div>

            <div class="sc-cn__panel" data-cn-panel="graph" hidden>
                <div class="sc-cn__registry-heading">
                    <div><strong><?php esc_html_e('Carbon Evidence & Methodology Graph', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Typed relationships with explicit non-inference guardrails.', 'sustainable-catalyst-library'); ?></span></div>
                </div>
                <form class="sc-cn__graph-search" role="search">
                    <label class="sc-cn__query"><span><?php esc_html_e('Graph node key', 'sustainable-catalyst-library'); ?></span><input type="search" name="node_key" maxlength="180" placeholder="e.g. soil-organic-carbon, cover-crop-system, soc-direct-measurement"></label>
                    <label><span><?php esc_html_e('Node type', 'sustainable-catalyst-library'); ?></span><select name="node_type"><option value="">All node types</option><option value="concept">Concept</option><option value="measure">Measure</option><option value="methodology">Methodology</option><option value="evidence">Evidence</option></select></label>
                    <div class="sc-cn__actions"><button type="submit"><?php esc_html_e('Explore Graph', 'sustainable-catalyst-library'); ?></button><button type="reset" class="sc-cn__secondary"><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button></div>
                </form>
                <div class="sc-cn__guardrail"><strong><?php esc_html_e('Graph edge ≠ proof or eligibility.', 'sustainable-catalyst-library'); ?></strong> <?php esc_html_e('v0.5.0 preserves governed concepts, measures, methodology profiles, evidence records, and project-object provenance context. It does not automatically validate a claim, select an approved methodology, determine project eligibility, or quantify sequestration.', 'sustainable-catalyst-library'); ?></div>
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

            <footer><?php esc_html_e('v0.5.0 establishes AFOLU Research Librarian intelligence over the v0.1–v0.4 Carbon & Nature knowledge foundation. Scientific calculation begins with the planned v0.6.0 Soil Organic Carbon Lab Foundation; project MRV construction, economics, and decision analysis remain governed later handoffs to Lab, Workbench, Site Intelligence, Workspace, and Decision Studio.', 'sustainable-catalyst-library'); ?></footer>
        </section>
        <?php return (string)ob_get_clean();
    }

}
