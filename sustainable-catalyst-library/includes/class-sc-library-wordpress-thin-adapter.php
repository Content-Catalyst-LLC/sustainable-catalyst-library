<?php
if (!defined('ABSPATH')) { exit; }

final class SC_Library_WordPress_Thin_Adapter {
    public const VERSION = '6.2.0';
    public const SCHEMA = 'sc-library-wordpress-thin-adapter/1.0';
    public const CONSOLIDATION_SCHEMA = 'sc-library-wordpress-thin-adapter-consolidation-claim/1.0';
    public const ROLE = 'optional-adapter';

    public function register_hooks(): void {
        add_shortcode('sc_library_wordpress_adapter_status', [$this, 'shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_head', [$this, 'emit_adapter_meta'], 1);
    }

    public static function allowed_responsibilities(): array {
        return [
            'public-routing','seo-and-public-metadata','launch-and-embed-surfaces',
            'health-and-status-display','optional-identity-handoff',
            'legacy-presentation-compatibility','api-client-adaptation',
        ];
    }

    public static function prohibited_authorities(): array {
        return [
            'publication-catalog-write-authority','domain-logic-authority','research-object-authority',
            'research-state-authority','project-state-authority','collection-state-authority',
            'saved-research-state-authority','source-ingestion-authority','source-normalization-authority',
            'retrieval-orchestration-authority','search-ranking-authority','provenance-authority',
            'citation-authority','evidence-graph-authority','language-intelligence-authority',
            'original-language-authority','ocr-htr-transcription-authority','linguistic-corpus-authority',
            'cross-language-resolution-authority','translation-alignment-authority','document-intelligence-authority',
            'research-package-authority','reproducibility-authority','research-execution-authority',
            'research-job-authority','background-workflow-authority','worker-routing-authority',
            'pipeline-execution-authority','artifact-authority','pipeline-authority','compute-authority',
            'identity-authority','session-authority','credential-authority','federation-authority',
            'connector-routing-authority','connector-execution-policy-authority','global-source-registry-authority',
            'trust-policy-authority','research-interface-composition-authority','discovery-navigation-authority','platform-core-promotion-authority',
        ];
    }

    public static function local_contract(): array {
        return [
            'schema' => self::SCHEMA,
            'library_version' => SC_LIBRARY_VERSION,
            'adapter' => [
                'id' => 'wordpress','role' => self::ROLE,'authoritative' => false,
                'required_for_research_execution' => false,'required_for_library_web' => false,
                'consolidated' => true,
            ],
            'allowed_responsibilities' => self::allowed_responsibilities(),
            'prohibited_authorities' => self::prohibited_authorities(),
            'library_api' => '/api/library/v1',
            'wordpress_cookie_is_library_session' => false,
            'library_session_authority' => 'library-service',
            'legacy_compatibility_is_authoritative' => false,
            'thin_adapter_consolidated' => true,
        ];
    }

    public static function local_consolidation_claim(): array {
        return [
            'schema' => self::CONSOLIDATION_SCHEMA,
            'library_version' => SC_LIBRARY_VERSION,
            'wordpress_role' => self::ROLE,
            'wordpress_authoritative' => false,
            'legacy_domain_authority' => false,
            'legacy_compatibility_present' => true,
            'api_v1_required_for_domain_behavior' => true,
            'library_api_authority' => 'library-api',
            'php_domain_authority_files' => 0,
            'baseline_inventory' => [
                'source' => 'v5.78.0-php-domain-retirement-inventory',
                'php_include_files' => 144,
                'adapter_or_presentation_files' => 15,
                'retire_candidate_files' => 129,
                'mass_deletion_performed' => false,
            ],
            'allowed_responsibilities' => self::allowed_responsibilities(),
            'prohibited_authorities' => self::prohibited_authorities(),
            'next_gate' => 'v6.3.0-research-projects-saved-workspaces',
        ];
    }

    private static function backend_readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['state' => 'unavailable','wordpress_dependency_count' => 0,'adapter' => self::local_contract()['adapter']];
        }
        $response = wp_remote_get(
            SC_Library_Python_Backend::base_url() . '/api/library/v1/wordpress-adapter/readiness',
            ['timeout' => 8,'redirection' => 1,'headers' => ['Accept' => 'application/json']]
        );
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return ['state' => 'unavailable','wordpress_dependency_count' => 0,'adapter' => self::local_contract()['adapter']];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : ['state' => 'unavailable','wordpress_dependency_count' => 0,'adapter' => self::local_contract()['adapter']];
    }

    public function register_rest_routes(): void {
        register_rest_route('sc-library/v1', '/adapter', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response(['contract' => self::local_contract(),'backend' => self::backend_readiness()]),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('sc-library/v1', '/adapter/consolidation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response(['claim' => self::local_consolidation_claim(),'backend' => self::backend_readiness()]),
            'permission_callback' => '__return_true',
        ]);
    }

    public function emit_adapter_meta(): void {
        echo '<meta name="sc-library-wordpress-role" content="optional-adapter" />' . "\n";
        echo '<meta name="sc-library-research-authority" content="library-api" />' . "\n";
        echo '<meta name="sc-library-wordpress-thin-adapter-consolidated" content="true" />' . "\n";
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['title' => 'Knowledge Library WordPress Adapter'], $atts, 'sc_library_wordpress_adapter_status');
        $backend = self::backend_readiness();
        $contract = self::local_contract();
        ob_start(); ?>
        <section class="sc-library-wordpress-thin-adapter" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-kicker">Knowledge Library <?php echo esc_html(SC_LIBRARY_VERSION); ?></p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p>WordPress is an optional presentation and editorial adapter. Authoritative Library research state, execution, identity, sessions, artifacts, pipelines, federation, workflows, and Platform Core governance remain outside WordPress.</p>
            <dl>
                <dt>Adapter role</dt><dd><?php echo esc_html((string) $contract['adapter']['role']); ?></dd>
                <dt>Thin adapter consolidated</dt><dd>yes</dd>
                <dt>Backend state</dt><dd><?php echo esc_html((string) ($backend['state'] ?? 'unavailable')); ?></dd>
                <dt>WordPress authoritative</dt><dd>no</dd>
                <dt>WordPress required for research execution</dt><dd>no</dd>
            </dl>
        </section>
        <?php return (string) ob_get_clean();
    }
}
