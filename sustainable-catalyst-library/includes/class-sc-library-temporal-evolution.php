<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.29.0 — Temporal Knowledge & Research Evolution console.
 *
 * Historical availability and retrospective status are intentionally separate.
 * The interface never rewrites source records or treats chronology as causality.
 */
final class SC_Library_Temporal_Evolution {
    public const VERSION = '5.29.0';
    public const SHORTCODE = 'sc_library_temporal_evolution';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-temporal-evolution-v5290',
            SC_LIBRARY_URL . 'assets/css/sc-library-temporal-evolution-v5290.css',
            [], self::VERSION
        );
        wp_enqueue_script(
            'sc-library-temporal-evolution-v5290',
            SC_LIBRARY_URL . 'assets/js/sc-library-temporal-evolution-v5290.js',
            [], self::VERSION, true
        );
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'max_publications' => '250',
            'source_key' => 'wordpress-main',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);
        $this->enqueue_assets();
        $id = 'sc-temporal-' . wp_generate_uuid4();
        $endpoint = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-temporal-evolution');
        $max = min(1000, max(1, (int) $atts['max_publications']));
        $source = sanitize_text_field((string) $atts['source_key']) ?: 'wordpress-main';
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-temporal" data-sc-temporal-root
            data-endpoint="<?php echo esc_url($endpoint); ?>"
            data-source-key="<?php echo esc_attr($source); ?>"
            data-max-publications="<?php echo esc_attr((string) $max); ?>">
            <header class="sc-temporal__header">
                <p class="sc-temporal__eyebrow"><?php esc_html_e('Temporal Knowledge & Research Evolution', 'sustainable-catalyst-library'); ?></p>
                <h2><?php esc_html_e('Inspect what entered the research record, when, and how its status changed', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Build date-bounded knowledge snapshots from explicit publication, indexing, version, correction, retraction, withdrawal, supersession, dataset, supplement, and review events.', 'sustainable-catalyst-library'); ?></p>
            </header>
            <div class="sc-temporal__controls">
                <label><span><?php esc_html_e('As-of date', 'sustainable-catalyst-library'); ?></span><input type="date" data-sc-temporal-asof></label>
                <label><span><?php esc_html_e('Lens', 'sustainable-catalyst-library'); ?></span>
                    <select data-sc-temporal-lens>
                        <option value="historical-availability"><?php esc_html_e('Historical availability', 'sustainable-catalyst-library'); ?></option>
                        <option value="retrospective-status"><?php esc_html_e('Retrospective status', 'sustainable-catalyst-library'); ?></option>
                    </select>
                </label>
                <button type="button" data-sc-temporal-run><?php esc_html_e('Build snapshot', 'sustainable-catalyst-library'); ?></button>
            </div>
            <div class="sc-temporal__compare">
                <label><span><?php esc_html_e('Compare from', 'sustainable-catalyst-library'); ?></span><input type="date" data-sc-temporal-from></label>
                <label><span><?php esc_html_e('Compare to', 'sustainable-catalyst-library'); ?></span><input type="date" data-sc-temporal-to></label>
                <button type="button" data-sc-temporal-compare><?php esc_html_e('Compare states', 'sustainable-catalyst-library'); ?></button>
            </div>
            <div class="sc-temporal__status" data-sc-temporal-status><?php esc_html_e('Temporal corpus not loaded yet.', 'sustainable-catalyst-library'); ?></div>
            <div class="sc-temporal__metrics" data-sc-temporal-metrics></div>
            <div class="sc-temporal__grid">
                <div>
                    <h3><?php esc_html_e('Research chronology', 'sustainable-catalyst-library'); ?></h3>
                    <div class="sc-temporal__timeline" data-sc-temporal-timeline></div>
                </div>
                <aside>
                    <h3><?php esc_html_e('Knowledge state', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-temporal-snapshot></div>
                    <h3><?php esc_html_e('Change set', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-temporal-change></div>
                </aside>
            </div>
            <footer class="sc-temporal__guardrail">
                <?php esc_html_e('Guardrail: a historical snapshot shows recorded availability, not universal researcher awareness or truth. Later corrections are shown backward only when the retrospective-status lens is selected. Temporal proximity is never treated as causality.', 'sustainable-catalyst-library'); ?>
            </footer>
        </section>
        <?php return (string) ob_get_clean();
    }
}
