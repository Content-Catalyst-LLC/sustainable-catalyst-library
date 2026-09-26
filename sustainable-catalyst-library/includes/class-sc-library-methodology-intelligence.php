<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.30.0 — Evidence Quality & Methodology Intelligence console.
 *
 * This surface reports structured methodology and appraisal-readiness signals.
 * It never converts reporting completeness into a universal quality score.
 */
final class SC_Library_Methodology_Intelligence {
    public const VERSION = '5.30.0';
    public const SHORTCODE = 'sc_library_methodology_intelligence';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-methodology-intelligence-v5300',
            SC_LIBRARY_URL . 'assets/css/sc-library-methodology-intelligence-v5300.css',
            [], self::VERSION
        );
        wp_enqueue_script(
            'sc-library-methodology-intelligence-v5300',
            SC_LIBRARY_URL . 'assets/js/sc-library-methodology-intelligence-v5300.js',
            [], self::VERSION, true
        );
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'max_publications' => '250',
            'source_key' => 'wordpress-main',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);
        $this->enqueue_assets();
        $id = 'sc-methodology-' . wp_generate_uuid4();
        $endpoint = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-methodology-intelligence');
        $max = min(1000, max(1, (int) $atts['max_publications']));
        $source = sanitize_text_field((string) $atts['source_key']) ?: 'wordpress-main';
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-methodology" data-sc-methodology-root
            data-endpoint="<?php echo esc_url($endpoint); ?>"
            data-source-key="<?php echo esc_attr($source); ?>"
            data-max-publications="<?php echo esc_attr((string) $max); ?>">
            <header class="sc-methodology__header">
                <p class="sc-methodology__eyebrow"><?php esc_html_e('Evidence Quality & Methodology Intelligence', 'sustainable-catalyst-library'); ?></p>
                <h2><?php esc_html_e('Compare how research was designed, measured, analyzed, and documented', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Inspect source-reported study design, population, sample, methods, uncertainty, limitations, disclosures, registration, data, code, and replication materials without turning reporting completeness into a truth or quality score.', 'sustainable-catalyst-library'); ?></p>
            </header>
            <div class="sc-methodology__controls">
                <label><span><?php esc_html_e('Study design', 'sustainable-catalyst-library'); ?></span>
                    <select data-sc-methodology-design><option value=""><?php esc_html_e('All designs', 'sustainable-catalyst-library'); ?></option></select>
                </label>
                <label><span><?php esc_html_e('Find publication', 'sustainable-catalyst-library'); ?></span>
                    <input type="search" data-sc-methodology-search placeholder="<?php echo esc_attr__('Title or record ID', 'sustainable-catalyst-library'); ?>">
                </label>
                <button type="button" data-sc-methodology-run><?php esc_html_e('Refresh analysis', 'sustainable-catalyst-library'); ?></button>
            </div>
            <div class="sc-methodology__status" data-sc-methodology-status><?php esc_html_e('Methodology corpus not loaded yet.', 'sustainable-catalyst-library'); ?></div>
            <div class="sc-methodology__metrics" data-sc-methodology-metrics></div>
            <div class="sc-methodology__grid">
                <div>
                    <h3><?php esc_html_e('Methodology profiles', 'sustainable-catalyst-library'); ?></h3>
                    <div class="sc-methodology__profiles" data-sc-methodology-profiles></div>
                </div>
                <aside>
                    <h3><?php esc_html_e('Design distribution', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-methodology-designs></div>
                    <h3><?php esc_html_e('Selected profile', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-methodology-detail><p><?php esc_html_e('Select a publication to inspect its reported methodology.', 'sustainable-catalyst-library'); ?></p></div>
                </aside>
            </div>
            <footer class="sc-methodology__guardrail">
                <?php esc_html_e('Guardrail: reporting coverage is documentation coverage only. It is not a quality grade, risk-of-bias judgment, causal-validity judgment, or truth score. Missing structured metadata does not prove a method was absent, and human appraisal remains required.', 'sustainable-catalyst-library'); ?>
            </footer>
        </section>
        <?php return (string) ob_get_clean();
    }
}
