<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.47.0 Linguistic Corpus Objects, Concordance & KWIC public status surface.
 *
 * WordPress exposes readiness only. Corpus source text, persisted token streams,
 * concordance windows, and signed corpus creation remain backend-authoritative.
 */
final class SC_Library_Linguistic_Corpus {
    public const VERSION = '5.47.0';
    public const SCHEMA = 'sc-library-linguistic-corpus-readiness/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_linguistic_corpus_status', [$this, 'shortcode']);
    }

    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'counts'=>[]];
        }
        $response = wp_remote_get(SC_Library_Python_Backend::base_url() . '/v1/linguistic-corpus/readiness', [
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
        $atts = shortcode_atts(['title'=>'Linguistic Corpus, Concordance & KWIC'], $atts, 'sc_linguistic_corpus_status');
        $payload = self::readiness();
        $counts = (array) ($payload['counts'] ?? []);
        $state = sanitize_text_field((string) ($payload['state'] ?? 'unavailable'));
        $tokenizer = (array) ($payload['tokenizer'] ?? []);
        ob_start();
        ?>
        <section class="sc-linguistic-corpus" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-linguistic-corpus__kicker"><?php esc_html_e('Knowledge Library v5.47.0', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p><?php esc_html_e('Reproducible corpus, document, and token objects preserve character offsets and source-representation lineage for concordance, KWIC, and frequency analysis. Tokenization is an operational segmentation profile, not morphological, syntactic, semantic, or truth analysis.', 'sustainable-catalyst-library'); ?></p>
            <dl>
                <dt><?php esc_html_e('State', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($state); ?></dd>
                <dt><?php esc_html_e('Corpora', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['corpora'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Documents', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['documents'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Tokens', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['tokens'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Tokenizer', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ($tokenizer['profile'] ?? 'unicode-word-v1')); ?></dd>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
