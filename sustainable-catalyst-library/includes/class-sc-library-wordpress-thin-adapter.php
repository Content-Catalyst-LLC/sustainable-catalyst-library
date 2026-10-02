<?php
if (!defined('ABSPATH')) { exit; }

final class SC_Library_WordPress_Thin_Adapter {
    public const VERSION = '5.76.0';
    public const SCHEMA = 'sc-library-wordpress-thin-adapter/1.0';
    public const ROLE = 'thin-adapter';

    public function register_hooks(): void {
        add_shortcode('sc_library_wordpress_adapter_status', [$this, 'shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_head', [$this, 'emit_adapter_meta'], 1);
    }

    public static function local_contract(): array {
        return [
            'schema' => self::SCHEMA,
            'library_version' => SC_LIBRARY_VERSION,
            'adapter' => [
                'id' => 'wordpress',
                'role' => self::ROLE,
                'authoritative' => false,
                'required_for_research_execution' => false,
                'required_for_library_web' => false,
            ],
            'allowed_responsibilities' => [
                'public-routing',
                'seo-and-public-metadata',
                'launch-and-embed-surfaces',
                'health-and-status-display',
                'optional-identity-handoff',
                'legacy-presentation-compatibility',
                'api-client-adaptation',
            ],
            'prohibited_authorities' => [
                'publication-catalog-write-authority',
                'domain-logic-authority',
                'research-object-authority',
                'research-state-authority',
                'project-state-authority',
                'collection-state-authority',
                'saved-research-state-authority',
                'source-ingestion-authority',
                'source-normalization-authority',
                'retrieval-orchestration-authority',
                'search-ranking-authority',
                'provenance-authority',
                'citation-authority',
                'evidence-graph-authority',
                'language-intelligence-authority',
                'original-language-authority',
                'ocr-htr-transcription-authority',
                'linguistic-corpus-authority',
                'cross-language-resolution-authority',
                'translation-alignment-authority',
                'document-intelligence-authority',
                'research-package-authority',
                'reproducibility-authority',
                'research-execution-authority',
                'research-job-authority',
                'artifact-authority',
                'pipeline-authority',
                'compute-authority',
                'identity-authority',
                'session-authority',
                'credential-authority',
                'federation-authority',
                'trust-policy-authority',
                'platform-core-promotion-authority',
            ],
            'library_api' => '/api/library/v1',
            'wordpress_cookie_is_library_session' => false,
            'library_session_authority' => 'library-service',
            'legacy_compatibility_is_authoritative' => false,
        ];
    }

    private static function backend_readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['state' => 'unavailable', 'wordpress_dependency_count' => 0, 'adapter' => self::local_contract()['adapter']];
        }
        $response = wp_remote_get(
            SC_Library_Python_Backend::base_url() . '/api/library/v1/wordpress-adapter/readiness',
            ['timeout' => 8, 'redirection' => 1, 'headers' => ['Accept' => 'application/json']]
        );
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return ['state' => 'unavailable', 'wordpress_dependency_count' => 0, 'adapter' => self::local_contract()['adapter']];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : ['state' => 'unavailable', 'wordpress_dependency_count' => 0, 'adapter' => self::local_contract()['adapter']];
    }

    public function register_rest_routes(): void {
        register_rest_route('sc-library/v1', '/adapter', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn() => rest_ensure_response([
                'contract' => self::local_contract(),
                'backend' => self::backend_readiness(),
            ]),
            'permission_callback' => '__return_true',
        ]);
    }

    public function emit_adapter_meta(): void {
        echo '<meta name="sc-library-wordpress-role" content="thin-adapter" />' . "\n";
        echo '<meta name="sc-library-research-authority" content="library-api" />' . "\n";
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['title' => 'Knowledge Library WordPress Adapter'], $atts, 'sc_library_wordpress_adapter_status');
        $backend = self::backend_readiness();
        $contract = self::local_contract();
        ob_start(); ?>
        <section class="sc-library-wordpress-thin-adapter" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-kicker">Knowledge Library <?php echo esc_html(SC_LIBRARY_VERSION); ?></p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p>WordPress is a thin presentation adapter. Authoritative research state, execution, identities, sessions, artifacts, pipelines, federation, and Platform Core governance remain outside WordPress.</p>
            <dl>
                <dt>Adapter role</dt><dd><?php echo esc_html((string) $contract['adapter']['role']); ?></dd>
                <dt>Backend state</dt><dd><?php echo esc_html((string) ($backend['state'] ?? 'unavailable')); ?></dd>
                <dt>WordPress authoritative</dt><dd>no</dd>
                <dt>WordPress required for research execution</dt><dd>no</dd>
            </dl>
        </section>
        <?php return (string) ob_get_clean();
    }
}
