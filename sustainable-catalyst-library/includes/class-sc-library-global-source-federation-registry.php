<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.44.0 Global Source Federation Registry public/developer surface.
 *
 * Registry data is authoritative in backend v2.55.0. This WordPress class
 * renders a bounded view and never stores credentials or duplicates source
 * execution logic.
 */
final class SC_Library_Global_Source_Federation_Registry {
    public const VERSION = '5.44.0';
    public const SCHEMA = 'sc-library-global-source-federation-registry/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_global_source_registry', [$this, 'shortcode']);
    }

    private static function fetch_registry(int $limit = 24): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'sources'=>[], 'counts'=>['sources'=>0]];
        }
        $response = wp_remote_get(SC_Library_Python_Backend::base_url() . '/v1/global-source-federation/registry', [
            'timeout' => 12,
            'redirection' => 2,
            'headers' => ['Accept'=>'application/json'],
        ]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'sources'=>[], 'counts'=>['sources'=>0]];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'sources'=>[], 'counts'=>['sources'=>0]];
        }
        $body['sources'] = array_slice((array) ($body['sources'] ?? []), 0, max(1, min(60, $limit)));
        return $body;
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'title' => 'Global Source Federation Registry',
            'limit' => 24,
        ], $atts, 'sc_global_source_registry');
        $payload = self::fetch_registry((int) $atts['limit']);
        $sources = (array) ($payload['sources'] ?? []);
        ob_start();
        ?>
        <section class="sc-global-source-registry" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <header class="sc-global-source-registry__header">
                <p class="sc-global-source-registry__kicker"><?php esc_html_e('Knowledge Library v5.44.0', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                <p><?php esc_html_e('Canonical research-source identities and connector capabilities across the Library. Registry inclusion describes technical discovery coverage; it does not imply endorsement, partnership, source quality, evidence truth, or automatic import.', 'sustainable-catalyst-library'); ?></p>
            </header>
            <?php if (!$sources) : ?>
                <p><?php esc_html_e('The Global Source Federation Registry is currently unavailable or contains no published descriptors.', 'sustainable-catalyst-library'); ?></p>
            <?php else : ?>
                <div class="sc-global-source-registry__grid">
                    <?php foreach ($sources as $source) :
                        $source_id = sanitize_key((string) ($source['source_id'] ?? ''));
                        $name = sanitize_text_field((string) ($source['name'] ?? $source_id));
                        $family = sanitize_text_field((string) ($source['source_family'] ?? 'research-source'));
                        $access = sanitize_text_field((string) ($source['access_mode'] ?? 'declared'));
                        $types = array_slice(array_map('sanitize_text_field', (array) ($source['content_types'] ?? [])), 0, 5);
                        ?>
                        <article class="sc-global-source-registry__source" data-source-id="<?php echo esc_attr($source_id); ?>">
                            <h3><?php echo esc_html($name); ?></h3>
                            <p><strong><?php echo esc_html($family); ?></strong> · <?php echo esc_html($access); ?></p>
                            <?php if ($types) : ?><p><?php echo esc_html(implode(' · ', $types)); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
