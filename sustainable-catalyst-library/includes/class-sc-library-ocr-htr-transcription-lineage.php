<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.46.0 OCR, HTR & Transcription Lineage public status surface.
 *
 * WordPress exposes bounded readiness only. Source media bytes, derived text,
 * model credentials, and signed persistence remain backend-authoritative.
 */
final class SC_Library_OCR_HTR_Transcription_Lineage {
    public const VERSION = '5.46.0';
    public const SCHEMA = 'sc-library-ocr-htr-transcription-readiness/1.0';

    public function register_hooks(): void {
        add_shortcode('sc_ocr_htr_transcription_lineage_status', [$this, 'shortcode']);
    }

    private static function readiness(): array {
        if (!SC_Library_Python_Backend::configured()) {
            return ['schema'=>self::SCHEMA, 'state'=>'unavailable', 'counts'=>[]];
        }
        $response = wp_remote_get(SC_Library_Python_Backend::base_url() . '/v1/ocr-htr-transcription/readiness', [
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
        $atts = shortcode_atts(['title'=>'OCR, HTR & Transcription Lineage'], $atts, 'sc_ocr_htr_transcription_lineage_status');
        $payload = self::readiness();
        $counts = (array) ($payload['counts'] ?? []);
        $state = sanitize_text_field((string) ($payload['state'] ?? 'unavailable'));
        ob_start();
        ?>
        <section class="sc-ocr-htr-transcription-lineage" data-schema="<?php echo esc_attr(self::SCHEMA); ?>">
            <p class="sc-ocr-htr-transcription-lineage__kicker"><?php esc_html_e('Knowledge Library v5.46.0', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html((string) $atts['title']); ?></h2>
            <p><?php esc_html_e('OCR, handwritten-text recognition, and transcription outputs remain derived text objects with preserved source-media fingerprints, engine/model identity, segment geometry or timecodes, confidence measurements, and explicit review state.', 'sustainable-catalyst-library'); ?></p>
            <dl>
                <dt><?php esc_html_e('State', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html($state); ?></dd>
                <dt><?php esc_html_e('Source assets', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['source_assets'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('OCR runs', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['ocr_runs'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('HTR runs', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['htr_runs'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Transcription runs', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['transcription_runs'] ?? 0))); ?></dd>
                <dt><?php esc_html_e('Segments', 'sustainable-catalyst-library'); ?></dt><dd><?php echo esc_html((string) ((int) ($counts['segments'] ?? 0))); ?></dd>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
