<?php
if (!defined('ABSPATH')) { exit; }
final class SC_Library_Runtime_Authority {
    public const VERSION = '5.58.0';
    public const SCHEMA = 'sc-library-runtime-authority-readiness/1.0';
    public function register_hooks(): void {
        add_shortcode('sc_library_runtime_authority_status', [$this, 'shortcode']);
    }
    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return [
                'schema' => self::SCHEMA,
                'state' => 'unavailable',
                'wordpress' => ['role' => 'publishing-routing-embed-adapter', 'authoritative' => false, 'required_for_research_execution' => false],
                'wordpress_dependency_count' => 0,
            ];
        }
        $response = wp_remote_get(
            SC_Library_Python_Backend::base_url() . '/v1/runtime-authority/readiness',
            ['timeout' => 12, 'redirection' => 2, 'headers' => ['Accept' => 'application/json']]
        );
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return ['schema' => self::SCHEMA, 'state' => 'unavailable', 'wordpress_dependency_count' => 0];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : ['schema' => self::SCHEMA, 'state' => 'unavailable', 'wordpress_dependency_count' => 0];
    }
    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['title' => 'Library Runtime Authority'], $atts, 'sc_library_runtime_authority_status');
        $status = self::readiness();
        $wordpress = is_array($status['wordpress'] ?? null) ? $status['wordpress'] : [];
        ob_start(); ?>
        <section class="sc-library-runtime-authority" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-kicker">Knowledge Library v5.58.0</p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p>The Library API, backend, PostgreSQL, workers, storage, pipelines, compute, federation, and Platform Core contracts are the authoritative research runtime. WordPress is a non-authoritative publishing, routing, and embed adapter.</p>
            <dl>
                <dt>Runtime state</dt><dd><?php echo esc_html((string) ($status['state'] ?? 'unavailable')); ?></dd>
                <dt>WordPress role</dt><dd><?php echo esc_html((string) ($wordpress['role'] ?? 'publishing-routing-embed-adapter')); ?></dd>
                <dt>WordPress authoritative</dt><dd><?php echo !empty($wordpress['authoritative']) ? 'yes' : 'no'; ?></dd>
                <dt>WordPress required for research execution</dt><dd><?php echo !empty($wordpress['required_for_research_execution']) ? 'yes' : 'no'; ?></dd>
            </dl>
        </section>
        <?php return (string) ob_get_clean();
    }
}
