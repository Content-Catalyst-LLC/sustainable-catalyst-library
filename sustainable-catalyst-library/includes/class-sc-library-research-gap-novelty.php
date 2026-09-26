<?php
if (!defined('ABSPATH')) { exit; }

/** v5.31.0 — Research Gap & Novelty Discovery console. */
final class SC_Library_Research_Gap_Novelty {
    public const VERSION = '5.31.0';
    public const SHORTCODE = 'sc_library_research_gap_novelty';

    public function register_hooks(): void { add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']); }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-research-gap-novelty-v5310', SC_LIBRARY_URL . 'assets/css/sc-library-research-gap-novelty-v5310.css', [], self::VERSION);
        wp_enqueue_script('sc-library-research-gap-novelty-v5310', SC_LIBRARY_URL . 'assets/js/sc-library-research-gap-novelty-v5310.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts(['max_publications'=>'250','source_key'=>'wordpress-main'], is_array($atts)?$atts:[], self::SHORTCODE);
        $this->enqueue_assets();
        $id='sc-gap-novelty-'.wp_generate_uuid4();
        $endpoint=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-research-gap-novelty');
        $max=min(1000,max(1,(int)$atts['max_publications']));
        $source=sanitize_text_field((string)$atts['source_key']) ?: 'wordpress-main';
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-gap-novelty" data-sc-gap-novelty-root
          data-endpoint="<?php echo esc_url($endpoint); ?>" data-source-key="<?php echo esc_attr($source); ?>" data-max-publications="<?php echo esc_attr((string)$max); ?>">
          <header class="sc-gap-novelty__header">
            <p class="sc-gap-novelty__eyebrow"><?php esc_html_e('Research Gap & Novelty Discovery','sustainable-catalyst-library'); ?></p>
            <h2><?php esc_html_e('Find defensible research leads without confusing corpus gaps with global novelty','sustainable-catalyst-library'); ?></h2>
            <p><?php esc_html_e('Inspect candidate evidence-linkage gaps, competing-evidence review priorities, narrow methodology coverage, reporting gaps, temporal coverage gaps, rare topic combinations, and recent corpus-emergence signals.','sustainable-catalyst-library'); ?></p>
          </header>
          <div class="sc-gap-novelty__controls">
            <label><span><?php esc_html_e('Signal type','sustainable-catalyst-library'); ?></span><select data-sc-gap-kind><option value=""><?php esc_html_e('All signals','sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('Find topic or record','sustainable-catalyst-library'); ?></span><input type="search" data-sc-gap-search placeholder="<?php echo esc_attr__('Topic, record ID, or signal','sustainable-catalyst-library'); ?>"></label>
            <button type="button" data-sc-gap-run><?php esc_html_e('Refresh discovery','sustainable-catalyst-library'); ?></button>
          </div>
          <div class="sc-gap-novelty__status" data-sc-gap-status><?php esc_html_e('Discovery corpus not loaded yet.','sustainable-catalyst-library'); ?></div>
          <div class="sc-gap-novelty__metrics" data-sc-gap-metrics></div>
          <div class="sc-gap-novelty__grid">
            <div><h3><?php esc_html_e('Candidate research gaps','sustainable-catalyst-library'); ?></h3><div data-sc-gap-list></div></div>
            <div><h3><?php esc_html_e('Novelty-discovery leads','sustainable-catalyst-library'); ?></h3><div data-sc-novelty-list></div></div>
            <aside><h3><?php esc_html_e('Selected signal','sustainable-catalyst-library'); ?></h3><div data-sc-gap-detail><p><?php esc_html_e('Select a signal to inspect its corpus basis and caveats.','sustainable-catalyst-library'); ?></p></div></aside>
          </div>
          <footer class="sc-gap-novelty__guardrail"><?php esc_html_e('Guardrail: a gap in the indexed corpus is not proof of a gap in the global literature, and a rare or recent combination is not a novelty claim. External literature search and human scholarly assessment are required before asserting novelty.','sustainable-catalyst-library'); ?></footer>
        </section><?php return (string)ob_get_clean();
    }
}
