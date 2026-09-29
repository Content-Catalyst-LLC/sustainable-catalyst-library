<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.45.0 Original-Language Corpus Ingestion & Preservation public status surface.
 *
 * Raw source bytes and text remain authoritative in backend v2.56.0. WordPress
 * exposes readiness only and never stores raw payloads, translations, or source credentials.
 */
final class SC_Library_Original_Language_Corpus {
    public const VERSION = '5.45.0';
    public const SCHEMA = 'sc-library-original-language-corpus-readiness/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_original_language_corpus_status', [$this, 'shortcode']);
    }

    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'counts'=>[]];
        }
        $response = wp_remote_get(SC_Library_Python_Backend::base_url() . '/v1/original-language-corpus/readiness', [
            'timeout' => 12,
            'redirection' => 2,
            'headers' => ['Accept'=>'application/json'],
        ]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'counts'=>[]];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'counts'=>[]];
    }

    public function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['title'=>'Original-Language Corpus Preservation'], $atts, 'sc_original_language_corpus_status');
        $payload = self::readiness();
        $counts = (array) ($payload['counts'] ?? []);
        $state = sanitize_text_field((string) ($payload['state'] ?? 'unavailable'));
        ob_start();
        ?>
        <section class="sc-original-language-corpus" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-original-language-corpus__kicker"><?php esc_html_e('Knowledge Library v5.45.0', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p><?php esc_html_e('Original-language source bytes and decoded text are preserved as canonical captures. Unicode normalization and later transliteration or translation remain separate derived representations with explicit lineage.', 'sustainable-catalyst-library'); ?></p>
            <dl>
                <dt><?php esc_html_e('State', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($state); ?></dd>
                <dt><?php esc_html_e('Captures', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['captures'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Representations', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['representations'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Transformations', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['transformations'] ?? 0))); ?></dd>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
