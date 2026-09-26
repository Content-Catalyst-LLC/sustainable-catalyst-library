<?php
if (!defined('ABSPATH')) { exit; }

/** v5.33.0 — Reproducible Literature Review Engine. */
final class SC_Library_Literature_Review {
    public const VERSION = '5.33.0';
    public const SHORTCODE = 'sc_library_literature_review';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-literature-review-v5330', SC_LIBRARY_URL . 'assets/css/sc-library-literature-review-v5330.css', [], self::VERSION);
        wp_enqueue_script('sc-library-literature-review-v5330', SC_LIBRARY_URL . 'assets/js/sc-library-literature-review-v5330.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-literature-review-'.wp_generate_uuid4();
        $build=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/literature-review-build');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-literature-review" data-sc-literature-review-root data-build-endpoint="<?php echo esc_url($build); ?>">
          <header>
            <p class="sc-literature-review__eyebrow">Reproducible Literature Review</p>
            <h2>Protocol, screening, extraction and review-state lineage</h2>
            <p>Build a deterministic review snapshot from an explicit research question, search strategy, criteria, records and human screening decisions.</p>
          </header>
          <div class="sc-literature-review__grid">
            <label>Review title<input type="text" data-sc-review-title value="Literature Review"></label>
            <label>Research question<textarea data-sc-review-question rows="3"></textarea></label>
            <label>Inclusion criteria<textarea data-sc-review-inclusion rows="4" placeholder="One criterion per line"></textarea></label>
            <label>Exclusion criteria<textarea data-sc-review-exclusion rows="4" placeholder="One criterion per line"></textarea></label>
            <label>Search query<textarea data-sc-review-query rows="3"></textarea></label>
            <label>Source / database<input type="text" data-sc-review-source placeholder="PubMed, Library search, institutional repository…"></label>
          </div>
          <div class="sc-literature-review__actions"><button type="button" data-sc-review-build>Build review snapshot</button></div>
          <div class="sc-literature-review__status" data-sc-review-status>Enter a protocol to create a reproducible review snapshot.</div>
          <pre class="sc-literature-review__output" data-sc-review-output></pre>
          <footer>Screening and inclusion/exclusion remain explicit human decisions. Review membership does not determine truth, evidence quality, consensus, or causality.</footer>
        </section><?php
        return (string) ob_get_clean();
    }
}
