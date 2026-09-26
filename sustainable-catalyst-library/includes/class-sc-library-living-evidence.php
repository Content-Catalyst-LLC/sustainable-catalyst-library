<?php
if (!defined('ABSPATH')) { exit; }

/** v5.34.0 — Living Evidence & Research Evolution. */
final class SC_Library_Living_Evidence {
    public const VERSION = '5.34.0';
    public const SHORTCODE = 'sc_library_living_evidence';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-living-evidence-v5340', SC_LIBRARY_URL . 'assets/css/sc-library-living-evidence-v5340.css', [], self::VERSION);
        wp_enqueue_script('sc-library-living-evidence-v5340', SC_LIBRARY_URL . 'assets/js/sc-library-living-evidence-v5340.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-living-evidence-'.wp_generate_uuid4();
        $endpoint=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/living-evidence');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-living-evidence" data-sc-living-evidence-root data-endpoint="<?php echo esc_url($endpoint); ?>">
          <header>
            <p class="sc-living-evidence__eyebrow">Living Evidence &amp; Research Evolution</p>
            <h2>Compare review snapshots without rewriting the past</h2>
            <p>Inspect new records, source/version changes, explicit status events, screening changes, extraction changes and protocol drift as review-update candidates.</p>
          </header>
          <div class="sc-living-evidence__grid">
            <label>Baseline review JSON<textarea data-sc-living-baseline rows="11" placeholder='{"protocol":...,"records":...,"decisions":...}'></textarea></label>
            <label>Current review JSON<textarea data-sc-living-current rows="11" placeholder='{"protocol":...,"records":...,"decisions":...}'></textarea></label>
          </div>
          <label>Explicit change events JSON (optional)<textarea data-sc-living-events rows="5" placeholder='[{"record_id":"r1","event_type":"corrected","occurred_at":"2026-09-26"}]'></textarea></label>
          <div class="sc-living-evidence__actions"><button type="button" data-sc-living-run>Compare living evidence state</button></div>
          <div class="sc-living-evidence__status" data-sc-living-status>Provide baseline and current review snapshots.</div>
          <div class="sc-living-evidence__metrics" data-sc-living-metrics></div>
          <pre class="sc-living-evidence__output" data-sc-living-output></pre>
          <footer>Update candidates are review prompts, not evidence verdicts. Newer records do not automatically supersede earlier work, and prior snapshots remain part of the reproducible lineage.</footer>
        </section><?php
        return (string) ob_get_clean();
    }
}
