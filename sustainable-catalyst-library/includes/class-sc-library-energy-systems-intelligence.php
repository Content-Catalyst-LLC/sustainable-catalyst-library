<?php
if (!defined('ABSPATH')) { exit; }

/** Energy Systems Intelligence v0.7.0 — Biological Carbon & Bioenergy Integration. */
final class SC_Library_Energy_Systems_Intelligence {
    public const VERSION = '0.7.0';
    public const SHORTCODE = 'sc_energy_systems_intelligence';

    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
    }

    public function register_assets(): void {
        wp_register_style('sc-library-energy-systems-v070', SC_LIBRARY_URL . 'assets/css/sc-library-energy-systems-v070.css', [], self::VERSION);
        wp_register_script('sc-library-energy-systems-v070', SC_LIBRARY_URL . 'assets/js/sc-library-energy-systems-v070.js', [], self::VERSION, true);
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
        register_rest_route('sc-library/v1', '/energy-systems/indicator-framework', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'indicator_framework']]);
        register_rest_route('sc-library/v1', '/energy-systems/indicators', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'indicators'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'dimension' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'theme' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'subtheme' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/indicator/(?P<code>[A-Za-z0-9]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'indicator']]);
        register_rest_route('sc-library/v1', '/energy-systems/indicator-observation-template/(?P<code>[A-Za-z0-9]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'indicator_observation_template']]);
        register_rest_route('sc-library/v1', '/energy-systems/technology-framework', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'technology_framework']]);
        register_rest_route('sc-library/v1', '/energy-systems/technologies', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'technologies'],
            'args' => [
                'q' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'family' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'output' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'resource_class' => ['sanitize_callback' => 'sanitize_key', 'default' => ''],
                'limit' => ['sanitize_callback' => 'absint', 'default' => 100],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/technology/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'technology']]);
        register_rest_route('sc-library/v1', '/energy-systems/resource-classes', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'resource_classes']]);
        register_rest_route('sc-library/v1', '/energy-systems/resource-class/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'resource_class']]);
        register_rest_route('sc-library/v1', '/energy-systems/technology-assessment-template/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'technology_assessment_template']]);
        register_rest_route('sc-library/v1', '/energy-systems/resource-observation-template/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'resource_observation_template']]);
        register_rest_route('sc-library/v1', '/energy-systems/technology-comparison-template', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'technology_comparison_template']]);
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
        register_rest_route('sc-library/v1', '/energy-systems/balance-framework', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'balance_framework']]);
        register_rest_route('sc-library/v1', '/energy-systems/conversion-chain', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'conversion_chain'],
            'args' => [
                'input_kwh' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'efficiencies' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'labels' => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/supply-demand-balance', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'supply_demand_balance'],
            'args' => [
                'domestic_supply_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'imports_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'storage_discharge_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'final_demand_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'exports_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'storage_charge_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'losses_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0'],
                'tolerance_kwh' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '0.001'],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/generation-estimate', [
            'methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'generation_estimate'],
            'args' => [
                'capacity_kw' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'capacity_factor_pct' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'hours' => ['sanitize_callback' => 'sanitize_text_field', 'default' => '8760'],
            ],
        ]);
        register_rest_route('sc-library/v1', '/energy-systems/balance-scenario-template', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'balance_scenario_template']]);
        register_rest_route('sc-library/v1', '/energy-systems/economics-framework', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'economics_framework']]);
        register_rest_route('sc-library/v1', '/energy-systems/energy-cost-comparison', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'energy_cost_comparison']]);
        register_rest_route('sc-library/v1', '/energy-systems/simple-payback', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'simple_payback']]);
        register_rest_route('sc-library/v1', '/energy-systems/npv', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'npv']]);
        register_rest_route('sc-library/v1', '/energy-systems/cost-benefit', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'cost_benefit']]);
        register_rest_route('sc-library/v1', '/energy-systems/cost-efficiency', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'cost_efficiency']]);
        register_rest_route('sc-library/v1', '/energy-systems/levelized-energy-cost', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'levelized_energy_cost']]);
        register_rest_route('sc-library/v1', '/energy-systems/economic-scenario-template', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'economic_scenario_template']]);
        register_rest_route('sc-library/v1', '/energy-systems/bioenergy-framework', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'bioenergy_framework']]);
        register_rest_route('sc-library/v1', '/energy-systems/bioenergy-feedstocks', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'bioenergy_feedstocks']]);
        register_rest_route('sc-library/v1', '/energy-systems/bioenergy-pathways', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'bioenergy_pathways']]);
        register_rest_route('sc-library/v1', '/energy-systems/bioenergy-pathway/(?P<key>[a-z0-9-]+)', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'bioenergy_pathway']]);
        register_rest_route('sc-library/v1', '/energy-systems/biological-carbon-bridges', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'biological_carbon_bridges']]);
        register_rest_route('sc-library/v1', '/energy-systems/feedstock-energy-estimate', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'feedstock_energy_estimate']]);
        register_rest_route('sc-library/v1', '/energy-systems/anaerobic-digestion-energy-estimate', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'anaerobic_digestion_energy_estimate']]);
        register_rest_route('sc-library/v1', '/energy-systems/biochar-carbon-estimate', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'biochar_carbon_estimate']]);
        register_rest_route('sc-library/v1', '/energy-systems/biomass-to-oil-energy-estimate', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'biomass_to_oil_energy_estimate']]);
        register_rest_route('sc-library/v1', '/energy-systems/bioenergy-scenario-template', ['methods' => $readable, 'permission_callback' => $open, 'callback' => [$this, 'bioenergy_scenario_template']]);
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
    public function indicator_framework(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/indicator-framework'); }
    public function indicators(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/indicators', array_filter([
            'q' => trim((string)$request->get_param('q')),
            'dimension' => sanitize_key((string)$request->get_param('dimension')),
            'theme' => sanitize_key((string)$request->get_param('theme')),
            'subtheme' => sanitize_key((string)$request->get_param('subtheme')),
            'limit' => min(100, max(1, absint($request->get_param('limit') ?: 100))),
        ], static fn($value) => $value !== ''));
    }
    public function indicator(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/indicators/' . rawurlencode(strtoupper(sanitize_text_field((string)$request['code']))));
    }
    public function indicator_observation_template(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/indicator-observation-template/' . rawurlencode(strtoupper(sanitize_text_field((string)$request['code']))));
    }
    public function technology_framework(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/technology-framework'); }
    public function technologies(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/technologies', array_filter([
            'q' => trim((string)$request->get_param('q')),
            'family' => sanitize_key((string)$request->get_param('family')),
            'output' => sanitize_key((string)$request->get_param('output')),
            'resource_class' => sanitize_key((string)$request->get_param('resource_class')),
            'limit' => min(100, max(1, absint($request->get_param('limit') ?: 100))),
        ], static fn($value) => $value !== ''));
    }
    public function technology(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/technologies/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function resource_classes(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/resource-classes'); }
    public function resource_class(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/resource-classes/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function technology_assessment_template(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/technology-assessment-template/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function resource_observation_template(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/resource-observation-template/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function technology_comparison_template(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/technology-comparison-template'); }
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
    public function balance_framework(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/balance-framework'); }
    public function conversion_chain(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/conversion-chain', [
            'input_kwh' => trim((string)$request->get_param('input_kwh')),
            'efficiencies' => trim((string)$request->get_param('efficiencies')),
            'labels' => trim((string)$request->get_param('labels')),
        ]);
    }
    public function supply_demand_balance(WP_REST_Request $request) {
        $params = [];
        foreach (['domestic_supply_kwh','imports_kwh','storage_discharge_kwh','final_demand_kwh','exports_kwh','storage_charge_kwh','losses_kwh','tolerance_kwh'] as $key) {
            $params[$key] = trim((string)$request->get_param($key));
        }
        return $this->proxy('/v1/energy-systems/supply-demand-balance', $params);
    }
    public function generation_estimate(WP_REST_Request $request) {
        return $this->proxy('/v1/energy-systems/generation-estimate', [
            'capacity_kw' => trim((string)$request->get_param('capacity_kw')),
            'capacity_factor_pct' => trim((string)$request->get_param('capacity_factor_pct')),
            'hours' => trim((string)$request->get_param('hours')),
        ]);
    }
    public function balance_scenario_template(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/balance-scenario-template'); }
    public function economics_framework(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/economics-framework'); }
    private function numeric_params(WP_REST_Request $request, array $keys): array { $params=[]; foreach($keys as $key){ $params[$key]=trim((string)$request->get_param($key)); } return $params; }
    public function energy_cost_comparison(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/energy-cost-comparison', $this->numeric_params($request, ['baseline_energy_kwh','baseline_price_per_kwh','candidate_energy_kwh','candidate_price_per_kwh','baseline_fixed_cost','candidate_fixed_cost','currency'])); }
    public function simple_payback(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/simple-payback', $this->numeric_params($request, ['initial_cost','annual_net_savings','currency'])); }
    public function npv(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/npv', $this->numeric_params($request, ['initial_cost','annual_net_cash_flow','discount_rate_pct','years','residual_value','currency'])); }
    public function cost_benefit(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/cost-benefit', $this->numeric_params($request, ['initial_cost','annual_cost','annual_benefit','discount_rate_pct','years','residual_value','currency'])); }
    public function cost_efficiency(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/cost-efficiency', $this->numeric_params($request, ['total_cost','energy_saved_kwh','co2e_avoided_kg','currency'])); }
    public function levelized_energy_cost(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/levelized-energy-cost', $this->numeric_params($request, ['initial_cost','annual_operating_cost','annual_energy_kwh','discount_rate_pct','years','residual_value','currency'])); }
    public function economic_scenario_template(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/economic-scenario-template'); }

    public function bioenergy_framework(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/bioenergy-framework'); }
    public function bioenergy_feedstocks(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/bioenergy-feedstocks', ['q' => trim((string)$request->get_param('q')), 'limit' => min(100, max(1, absint($request->get_param('limit') ?: 100)))]); }
    public function bioenergy_pathways(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/bioenergy-pathways', ['q' => trim((string)$request->get_param('q')), 'family' => sanitize_key((string)$request->get_param('family')), 'limit' => min(100, max(1, absint($request->get_param('limit') ?: 100)))]); }
    public function bioenergy_pathway(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/bioenergy-pathways/' . rawurlencode(sanitize_key((string)$request['key']))); }
    public function biological_carbon_bridges(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/biological-carbon-bridges'); }
    public function feedstock_energy_estimate(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/feedstock-energy-estimate', $this->numeric_params($request, ['mass_tonnes','energy_content_kwh_per_tonne','conversion_efficiency_pct'])); }
    public function anaerobic_digestion_energy_estimate(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/anaerobic-digestion-energy-estimate', $this->numeric_params($request, ['feedstock_mass_tonnes','biogas_yield_m3_per_tonne','methane_fraction_pct','methane_energy_kwh_per_m3','conversion_efficiency_pct'])); }
    public function biochar_carbon_estimate(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/biochar-carbon-estimate', $this->numeric_params($request, ['biochar_mass_kg','carbon_fraction_pct','stable_fraction_pct'])); }
    public function biomass_to_oil_energy_estimate(WP_REST_Request $request) { return $this->proxy('/v1/energy-systems/biomass-to-oil-energy-estimate', $this->numeric_params($request, ['feedstock_mass_tonnes','oil_yield_mass_pct','oil_energy_content_kwh_per_tonne','downstream_conversion_efficiency_pct'])); }
    public function bioenergy_scenario_template(WP_REST_Request $request) { unset($request); return $this->proxy('/v1/energy-systems/bioenergy-scenario-template'); }

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
            'intro' => 'Connect bioenergy pathways, explicit-input energy calculations, and biological carbon accounting to governed Carbon & Nature concepts while preserving the full Energy Systems knowledge, balance, technology, indicator, and economics stack.',
        ], $atts, self::SHORTCODE);

        wp_enqueue_style('sc-library-energy-systems-v070');
        wp_enqueue_script('sc-library-energy-systems-v070');

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
            data-indicator-framework-endpoint="<?php echo esc_url($ep('/indicator-framework')); ?>"
            data-indicators-endpoint="<?php echo esc_url($ep('/indicators')); ?>"
            data-indicator-endpoint="<?php echo esc_url($ep('/indicator')); ?>"
            data-indicator-template-endpoint="<?php echo esc_url($ep('/indicator-observation-template')); ?>"
            data-technology-framework-endpoint="<?php echo esc_url($ep('/technology-framework')); ?>"
            data-technologies-endpoint="<?php echo esc_url($ep('/technologies')); ?>"
            data-technology-endpoint="<?php echo esc_url($ep('/technology')); ?>"
            data-resource-classes-endpoint="<?php echo esc_url($ep('/resource-classes')); ?>"
            data-resource-class-endpoint="<?php echo esc_url($ep('/resource-class')); ?>"
            data-technology-assessment-endpoint="<?php echo esc_url($ep('/technology-assessment-template')); ?>"
            data-resource-observation-endpoint="<?php echo esc_url($ep('/resource-observation-template')); ?>"
            data-technology-comparison-endpoint="<?php echo esc_url($ep('/technology-comparison-template')); ?>"
            data-convert-endpoint="<?php echo esc_url($ep('/convert')); ?>"
            data-carbon-estimate-endpoint="<?php echo esc_url($ep('/carbon-estimate')); ?>"
            data-heat-estimate-endpoint="<?php echo esc_url($ep('/heat-content-estimate')); ?>"
            data-balance-framework-endpoint="<?php echo esc_url($ep('/balance-framework')); ?>"
            data-conversion-chain-endpoint="<?php echo esc_url($ep('/conversion-chain')); ?>"
            data-supply-demand-balance-endpoint="<?php echo esc_url($ep('/supply-demand-balance')); ?>"
            data-generation-estimate-endpoint="<?php echo esc_url($ep('/generation-estimate')); ?>"
            data-balance-scenario-endpoint="<?php echo esc_url($ep('/balance-scenario-template')); ?>"
            data-economics-framework-endpoint="<?php echo esc_url($ep('/economics-framework')); ?>"
            data-energy-cost-comparison-endpoint="<?php echo esc_url($ep('/energy-cost-comparison')); ?>"
            data-simple-payback-endpoint="<?php echo esc_url($ep('/simple-payback')); ?>"
            data-npv-endpoint="<?php echo esc_url($ep('/npv')); ?>"
            data-cost-benefit-endpoint="<?php echo esc_url($ep('/cost-benefit')); ?>"
            data-cost-efficiency-endpoint="<?php echo esc_url($ep('/cost-efficiency')); ?>"
            data-levelized-cost-endpoint="<?php echo esc_url($ep('/levelized-energy-cost')); ?>"
            data-economic-scenario-endpoint="<?php echo esc_url($ep('/economic-scenario-template')); ?>"
            data-bioenergy-framework-endpoint="<?php echo esc_url($ep('/bioenergy-framework')); ?>"
            data-bioenergy-feedstocks-endpoint="<?php echo esc_url($ep('/bioenergy-feedstocks')); ?>"
            data-bioenergy-pathways-endpoint="<?php echo esc_url($ep('/bioenergy-pathways')); ?>"
            data-biological-carbon-bridges-endpoint="<?php echo esc_url($ep('/biological-carbon-bridges')); ?>"
            data-feedstock-energy-endpoint="<?php echo esc_url($ep('/feedstock-energy-estimate')); ?>"
            data-anaerobic-digestion-energy-endpoint="<?php echo esc_url($ep('/anaerobic-digestion-energy-estimate')); ?>"
            data-biochar-carbon-endpoint="<?php echo esc_url($ep('/biochar-carbon-estimate')); ?>"
            data-biomass-to-oil-energy-endpoint="<?php echo esc_url($ep('/biomass-to-oil-energy-estimate')); ?>"
            data-bioenergy-scenario-endpoint="<?php echo esc_url($ep('/bioenergy-scenario-template')); ?>">
            <header class="sc-es__header">
                <p class="sc-es__kicker"><?php esc_html_e('Library Domain Intelligence · v0.7.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string)$atts['title']); ?></h2>
                <p><?php echo esc_html((string)$atts['intro']); ?></p>
            </header>

            <div class="sc-es__domains" aria-label="Energy Systems knowledge domains">
                <span>Energy Systems</span><span>Resources &amp; Conversion</span><span>Renewables</span><span>Bioenergy &amp; Carbon</span><span>Efficiency &amp; Economics</span><span>Sustainability Metrics</span>
            </div>

            <div class="sc-es__guardrail">
                <strong><?php esc_html_e('Bioenergy output ≠ carbon neutrality, verified removal, or project eligibility.', 'sustainable-catalyst-library'); ?></strong>
                <?php esc_html_e('v0.7.0 requires explicit yields, energy values, fractions, efficiencies, baselines, lifecycle boundaries, and evidence. It does not infer avoided emissions, digestate benefit, soil or forest carbon change, additionality, permanence, leakage, or carbon-credit eligibility.', 'sustainable-catalyst-library'); ?>
            </div>

            <div class="sc-es__modebar" role="tablist" aria-label="Energy Systems explorers">
                <button type="button" class="sc-es__mode is-active" data-es-mode="bioenergy" role="tab" aria-selected="true">Bioenergy &amp; Carbon</button>
                <button type="button" class="sc-es__mode" data-es-mode="economics" role="tab" aria-selected="false">Scenario Economics</button>
                <button type="button" class="sc-es__mode" data-es-mode="balance" role="tab" aria-selected="false">Energy Balance</button>
                <button type="button" class="sc-es__mode" data-es-mode="technologies" role="tab" aria-selected="false">Technologies &amp; Resources</button>
                <button type="button" class="sc-es__mode" data-es-mode="indicators" role="tab" aria-selected="false">Sustainability Indicators</button>
                <button type="button" class="sc-es__mode" data-es-mode="registry" role="tab" aria-selected="false">Numeric Registry</button>
                <button type="button" class="sc-es__mode" data-es-mode="map" role="tab" aria-selected="false">Knowledge Map</button>
                <button type="button" class="sc-es__mode" data-es-mode="concepts" role="tab" aria-selected="false">Concept Registry</button>
                <button type="button" class="sc-es__mode" data-es-mode="sources" role="tab" aria-selected="false">Sources &amp; Provenance</button>
                <button type="button" class="sc-es__mode" data-es-mode="handoffs" role="tab" aria-selected="false">Platform Handoffs</button>
            </div>

            <div class="sc-es__panel" data-es-panel="bioenergy">
                <div class="sc-es__panel-heading"><strong>Biological carbon &amp; bioenergy integration</strong><span>Link anaerobic digestion and digestate, biochar, biomass-to-oil, generic biomass energy, soil/forest carbon, and CO₂-to-energy to explicit-input energy accounting and governed Carbon &amp; Nature contexts.</span></div>
                <p class="sc-es__status" data-es-bioenergy-framework-status aria-live="polite">Loading bioenergy framework…</p>
                <div class="sc-es__bioenergy-summary" data-es-bioenergy-summary></div>

                <div class="sc-es__calc-grid sc-es__calc-grid--bioenergy">
                    <form class="sc-es__calculator" data-es-feedstock-energy-form><h3>Feedstock energy</h3><label><span>Feedstock mass (tonnes)</span><input name="mass_tonnes" value="10" inputmode="decimal" required></label><label><span>Energy content (kWh/tonne)</span><input name="energy_content_kwh_per_tonne" value="4000" inputmode="decimal" required></label><label><span>Conversion efficiency (%)</span><input name="conversion_efficiency_pct" value="80" inputmode="decimal" required></label><button type="submit">Estimate useful energy</button><div class="sc-es__result" data-es-feedstock-energy-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-ad-energy-form><h3>Anaerobic digestion energy</h3><label><span>Feedstock mass (tonnes)</span><input name="feedstock_mass_tonnes" value="10" inputmode="decimal" required></label><label><span>Biogas yield (m³/tonne)</span><input name="biogas_yield_m3_per_tonne" value="100" inputmode="decimal" required></label><label><span>Methane fraction (%)</span><input name="methane_fraction_pct" value="60" inputmode="decimal" required></label><label><span>Methane energy (kWh/m³)</span><input name="methane_energy_kwh_per_m3" value="10" inputmode="decimal" required></label><label><span>Conversion efficiency (%)</span><input name="conversion_efficiency_pct" value="40" inputmode="decimal" required></label><button type="submit">Estimate AD energy</button><div class="sc-es__result" data-es-ad-energy-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-biochar-form><h3>Biochar carbon accounting</h3><label><span>Biochar mass (kg)</span><input name="biochar_mass_kg" value="1000" inputmode="decimal" required></label><label><span>Carbon fraction (%)</span><input name="carbon_fraction_pct" value="70" inputmode="decimal" required></label><label><span>Stable fraction (%)</span><input name="stable_fraction_pct" value="80" inputmode="decimal" required></label><button type="submit">Estimate carbon equivalent</button><div class="sc-es__result" data-es-biochar-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-biomass-oil-form><h3>Biomass-to-oil energy</h3><label><span>Feedstock mass (tonnes)</span><input name="feedstock_mass_tonnes" value="10" inputmode="decimal" required></label><label><span>Oil yield by mass (%)</span><input name="oil_yield_mass_pct" value="30" inputmode="decimal" required></label><label><span>Oil energy content (kWh/tonne)</span><input name="oil_energy_content_kwh_per_tonne" value="9000" inputmode="decimal" required></label><label><span>Downstream efficiency (%)</span><input name="downstream_conversion_efficiency_pct" value="90" inputmode="decimal" required></label><button type="submit">Estimate product energy</button><div class="sc-es__result" data-es-biomass-oil-result aria-live="polite"></div></form>
                </div>

                <div class="sc-es__bioenergy-section"><div class="sc-es__panel-heading"><strong>Bioenergy pathways</strong><span>Pathway objects separate energy conversion from lifecycle and carbon claims.</span></div><div class="sc-es__cards" data-es-bioenergy-pathways></div></div>
                <div class="sc-es__bioenergy-section"><div class="sc-es__panel-heading"><strong>Feedstock classes</strong><span>Normalized feedstock objects require origin, mass/moisture basis, competing-use, land-use, and provenance context.</span></div><div class="sc-es__cards" data-es-bioenergy-feedstocks></div></div>
                <div class="sc-es__bioenergy-section"><div class="sc-es__panel-heading"><strong>Carbon &amp; Nature bridges</strong><span>Validated links resolve to existing Carbon &amp; Nature concepts and methodologies without importing quantitative carbon claims.</span></div><div class="sc-es__cards" data-es-biological-carbon-bridges></div></div>
                <div class="sc-es__bioenergy-contract" data-es-bioenergy-scenario-contract></div>
            </div>

            <div class="sc-es__panel" data-es-panel="economics" hidden>
                <div class="sc-es__panel-heading"><strong>Energy scenario economics</strong><span>Run transparent economic comparisons from explicit assumptions. No price feed, technology-cost database, financing model, or automatic investment recommendation is used.</span></div>
                <p class="sc-es__status" data-es-economics-framework-status aria-live="polite">Loading economics framework…</p><div class="sc-es__economics-summary" data-es-economics-summary></div>
                <div class="sc-es__calc-grid sc-es__calc-grid--economics">
                    <form class="sc-es__calculator" data-es-cost-compare-form><h3>Energy cost comparison</h3><div class="sc-es__field-grid sc-es__field-grid--economics"><label><span>Baseline energy (kWh)</span><input name="baseline_energy_kwh" value="10000" inputmode="decimal" required></label><label><span>Baseline price / kWh</span><input name="baseline_price_per_kwh" value="0.15" inputmode="decimal" required></label><label><span>Candidate energy (kWh)</span><input name="candidate_energy_kwh" value="8000" inputmode="decimal" required></label><label><span>Candidate price / kWh</span><input name="candidate_price_per_kwh" value="0.15" inputmode="decimal" required></label></div><button type="submit">Compare costs</button><div class="sc-es__result" data-es-cost-compare-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-payback-form><h3>Simple payback</h3><label><span>Initial cost</span><input name="initial_cost" value="5000" inputmode="decimal" required></label><label><span>Annual net savings</span><input name="annual_net_savings" value="1000" inputmode="decimal" required></label><button type="submit">Calculate payback</button><div class="sc-es__result" data-es-payback-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-npv-form><h3>Net present value</h3><label><span>Initial cost</span><input name="initial_cost" value="5000" inputmode="decimal" required></label><label><span>Annual net cash flow</span><input name="annual_net_cash_flow" value="1200" inputmode="decimal" required></label><label><span>Discount rate (%)</span><input name="discount_rate_pct" value="5" inputmode="decimal" required></label><label><span>Years</span><input name="years" value="10" inputmode="numeric" required></label><button type="submit">Calculate NPV</button><div class="sc-es__result" data-es-npv-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-cba-form><h3>Cost-benefit analysis</h3><label><span>Initial cost</span><input name="initial_cost" value="5000" inputmode="decimal" required></label><label><span>Annual cost</span><input name="annual_cost" value="200" inputmode="decimal" required></label><label><span>Annual benefit</span><input name="annual_benefit" value="1400" inputmode="decimal" required></label><label><span>Discount rate / years</span><div class="sc-es__inline-fields"><input name="discount_rate_pct" value="5" inputmode="decimal" required><input name="years" value="10" inputmode="numeric" required></div></label><button type="submit">Run cost-benefit</button><div class="sc-es__result" data-es-cba-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-cost-efficiency-form><h3>Cost efficiency</h3><label><span>Total cost</span><input name="total_cost" value="5000" inputmode="decimal" required></label><label><span>Energy saved (kWh)</span><input name="energy_saved_kwh" value="25000" inputmode="decimal"></label><label><span>CO₂e avoided (kg)</span><input name="co2e_avoided_kg" value="10000" inputmode="decimal"></label><button type="submit">Calculate ratios</button><div class="sc-es__result" data-es-cost-efficiency-result aria-live="polite"></div></form>
                    <form class="sc-es__calculator" data-es-levelized-form><h3>Levelized energy cost</h3><label><span>Initial cost</span><input name="initial_cost" value="100000" inputmode="decimal" required></label><label><span>Annual operating cost</span><input name="annual_operating_cost" value="3000" inputmode="decimal" required></label><label><span>Annual energy (kWh)</span><input name="annual_energy_kwh" value="50000" inputmode="decimal" required></label><label><span>Discount rate / years</span><div class="sc-es__inline-fields"><input name="discount_rate_pct" value="5" inputmode="decimal" required><input name="years" value="20" inputmode="numeric" required></div></label><button type="submit">Calculate levelized cost</button><div class="sc-es__result" data-es-levelized-result aria-live="polite"></div></form>
                </div><div class="sc-es__economic-contract" data-es-economic-scenario-contract></div>
            </div>

            <div class="sc-es__panel" data-es-panel="balance" hidden>
                <div class="sc-es__panel-heading"><strong>Energy balance &amp; systems modeling</strong><span>Run deterministic, transparent calculations from explicit inputs. The model keeps supply, conversion, losses, storage accounting, demand, and generation assumptions visible instead of silently assigning technology performance.</span></div>
                <p class="sc-es__status" data-es-balance-framework-status aria-live="polite">Loading balance framework…</p>
                <div class="sc-es__balance-summary" data-es-balance-summary></div>

                <div class="sc-es__calc-grid sc-es__calc-grid--balance">
                    <form class="sc-es__calculator" data-es-chain-form>
                        <h3>Conversion chain</h3>
                        <label><span>Starting energy (kWh)</span><input name="input_kwh" inputmode="decimal" placeholder="1000" required></label>
                        <label><span>Stage efficiencies (%)</span><input name="efficiencies" placeholder="90, 95, 97" required></label>
                        <label><span>Stage labels (optional)</span><input name="labels" placeholder="Conversion, Distribution, End use"></label>
                        <button type="submit">Run chain</button>
                        <div class="sc-es__result" data-es-chain-result aria-live="polite"></div>
                    </form>

                    <form class="sc-es__calculator sc-es__calculator--wide" data-es-balance-form>
                        <h3>Supply–demand balance</h3>
                        <div class="sc-es__field-grid">
                            <label><span>Domestic supply (kWh)</span><input name="domestic_supply_kwh" inputmode="decimal" placeholder="1000" required></label>
                            <label><span>Imports</span><input name="imports_kwh" inputmode="decimal" value="0"></label>
                            <label><span>Storage discharge</span><input name="storage_discharge_kwh" inputmode="decimal" value="0"></label>
                            <label><span>Final demand</span><input name="final_demand_kwh" inputmode="decimal" placeholder="900" required></label>
                            <label><span>Exports</span><input name="exports_kwh" inputmode="decimal" value="0"></label>
                            <label><span>Storage charge</span><input name="storage_charge_kwh" inputmode="decimal" value="0"></label>
                            <label><span>Losses</span><input name="losses_kwh" inputmode="decimal" value="0"></label>
                            <label><span>Balance tolerance (kWh)</span><input name="tolerance_kwh" inputmode="decimal" value="0.001"></label>
                        </div>
                        <button type="submit">Check balance</button>
                        <div class="sc-es__result" data-es-balance-result aria-live="polite"></div>
                    </form>

                    <form class="sc-es__calculator" data-es-generation-form>
                        <h3>Generation from capacity factor</h3>
                        <label><span>Capacity (kW)</span><input name="capacity_kw" inputmode="decimal" placeholder="1000" required></label>
                        <label><span>Capacity factor (%)</span><input name="capacity_factor_pct" inputmode="decimal" placeholder="35" required></label>
                        <label><span>Hours</span><input name="hours" inputmode="decimal" value="8760" required></label>
                        <button type="submit">Estimate generation</button>
                        <div class="sc-es__result" data-es-generation-result aria-live="polite"></div>
                    </form>
                </div>

                <div class="sc-es__scenario-contract" data-es-scenario-contract></div>
            </div>

            <div class="sc-es__panel" data-es-panel="technologies" hidden>
                <div class="sc-es__panel-heading"><strong>Renewable technology &amp; resource model</strong><span>Seven governed renewable technology families and six normalized resource classes. The model structures evidence needed for later Lab, Site Intelligence, Workbench, and Decision Studio analysis without inventing universal performance values or suitability rankings.</span></div>
                <p class="sc-es__status" data-es-technology-framework-status aria-live="polite">Loading technology framework…</p>
                <div class="sc-es__technology-summary" data-es-technology-summary></div>
                <form class="sc-es__technology-search" data-es-technology-form role="search">
                    <label><span>Technology or resource</span><input type="search" name="q" maxlength="500" placeholder="e.g. photovoltaic, marine, bioenergy"></label>
                    <label><span>Family</span><select name="family"><option value="">All families</option><option value="solar">Solar</option><option value="wind">Wind</option><option value="hydro">Hydro</option><option value="marine">Marine</option><option value="bioenergy">Bioenergy</option></select></label>
                    <div class="sc-es__actions"><button type="submit">Explore Technologies</button><button type="reset" class="sc-es__secondary">Reset</button></div>
                </form>
                <p class="sc-es__status" data-es-technology-status aria-live="polite"></p>
                <div class="sc-es__cards sc-es__technology-cards" data-es-technology-results></div>
                <div class="sc-es__technology-detail" data-es-technology-detail hidden></div>
                <div class="sc-es__resource-section"><div class="sc-es__panel-heading"><strong>Renewable resource classes</strong><span>Resource observations retain geography, period, metric, unit, method, source vintage, uncertainty, and provenance. A resource observation is not automatically technical or economic potential.</span></div><div class="sc-es__cards" data-es-resource-results></div></div>
            </div>

            <div class="sc-es__panel" data-es-panel="indicators" hidden>
                <div class="sc-es__panel-heading"><strong>Energy sustainability indicator framework</strong><span>Thirty source-grounded EISD definitions organized across social, economic, and environmental dimensions. Exact official formulas remain gated until the corresponding methodology sheets are loaded.</span></div>
                <p class="sc-es__status" data-es-indicator-framework-status aria-live="polite">Loading indicator framework…</p>
                <div class="sc-es__indicator-summary" data-es-indicator-summary></div>
                <div class="sc-es__framework" data-es-indicator-framework></div>
                <form class="sc-es__indicator-search" data-es-indicator-form role="search">
                    <label><span>Indicator, topic or code</span><input type="search" name="q" maxlength="500" placeholder="e.g. ECO13, affordability, air quality"></label>
                    <label><span>Dimension</span><select name="dimension"><option value="">All dimensions</option><option value="social">Social</option><option value="economic">Economic</option><option value="environmental">Environmental</option></select></label>
                    <div class="sc-es__actions"><button type="submit">Filter Indicators</button><button type="reset" class="sc-es__secondary">Reset</button></div>
                </form>
                <p class="sc-es__status" data-es-indicator-status aria-live="polite"></p>
                <div class="sc-es__cards sc-es__indicator-cards" data-es-indicator-results></div>
                <div class="sc-es__indicator-detail" data-es-indicator-detail hidden></div>
            </div>

            <div class="sc-es__panel" data-es-panel="registry" hidden>
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
                <div class="sc-es__panel-heading"><strong>Cross-platform handoffs</strong><span>v0.7.0 adds validated Energy Systems ↔ Carbon & Nature bioenergy/carbon bridges plus portable bioenergy-carbon scenario contracts while preserving economics, balance, renewable-resource, indicator, Lab, Workbench, Site Intelligence, and Decision Studio handoffs. Separate products are not modified by this Library release.</span></div>
                <p class="sc-es__status" data-es-handoff-status aria-live="polite">Loading handoff registry…</p>
                <div class="sc-es__cards" data-es-handoff-results></div>
            </div>

            <footer><strong>Next:</strong> v0.8.0 — Global Energy Intelligence. The next release should connect governed current energy datasets, geography, country/system profiles, generation and consumption trends, resource observations, and source freshness to Site Intelligence without treating historical course sources as current operational data.</footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
