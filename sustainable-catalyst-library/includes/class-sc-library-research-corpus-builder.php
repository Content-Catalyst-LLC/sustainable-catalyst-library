<?php
if (!defined('ABSPATH')) { exit; }

/** v5.37.0 — Research Corpus Builder & Dataset Export. */
final class SC_Library_Research_Corpus_Builder {
    public const VERSION = '5.37.0';
    public const SHORTCODE = 'sc_library_research_corpus_builder';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-research-corpus-v5370', SC_LIBRARY_URL . 'assets/css/sc-library-research-corpus-v5370.css', [], self::VERSION);
        wp_enqueue_script('sc-library-research-corpus-v5370', SC_LIBRARY_URL . 'assets/js/sc-library-research-corpus-v5370.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-research-corpus-'.wp_generate_uuid4();
        $build=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/research-corpus-build');
        $export=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/research-corpus-export');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-research-corpus" data-sc-research-corpus-root data-build-endpoint="<?php echo esc_url($build); ?>" data-export-endpoint="<?php echo esc_url($export); ?>">
          <header>
            <p class="sc-research-corpus__eyebrow">Research Corpus Builder</p>
            <h2>Build a reproducible corpus and portable dataset</h2>
            <p>Define a record set, explicit selection rules and export fields. The Library creates a deterministic corpus manifest plus row-level source provenance.</p>
          </header>
          <div class="sc-research-corpus__grid">
            <label>Corpus title<input type="text" data-sc-corpus-title value="Research Corpus"></label>
            <label>Include record IDs<textarea data-sc-corpus-include rows="4" placeholder="One record ID per line; leave empty to use all provided records"></textarea></label>
            <label>Exclude record IDs<textarea data-sc-corpus-exclude rows="4" placeholder="One record ID per line"></textarea></label>
            <label>Dataset fields<textarea data-sc-corpus-fields rows="4">record_id
title
doi
url
published_at
source_type
authors
topics</textarea></label>
            <label class="sc-research-corpus__wide">Records JSON<textarea data-sc-corpus-records rows="10" placeholder='[{"record_id":"record:1","title":"Example publication","doi":"10.x/example"}]'></textarea></label>
            <label>Export format<select data-sc-corpus-format><option value="bundle">Bundle: JSON + JSONL + CSV</option><option value="json">JSON</option><option value="jsonl">JSONL</option><option value="csv">CSV</option></select></label>
          </div>
          <div class="sc-research-corpus__actions">
            <button type="button" data-sc-corpus-build>Build corpus</button>
            <button type="button" data-sc-corpus-export disabled>Build export</button>
          </div>
          <div class="sc-research-corpus__status" data-sc-corpus-status>Provide records to build a versioned corpus.</div>
          <pre class="sc-research-corpus__output" data-sc-corpus-output></pre>
          <footer>Corpus membership is a selection state, not a quality score, truth judgment, consensus claim, or automatic Platform Core promotion.</footer>
        </section><?php
        return (string) ob_get_clean();
    }
}
