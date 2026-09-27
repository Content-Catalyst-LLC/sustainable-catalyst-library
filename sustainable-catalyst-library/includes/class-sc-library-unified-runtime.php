<?php
if (!defined('ABSPATH')) { exit; }

/** v5.38.0 — Unified Research Runtime Contract. */
final class SC_Library_Unified_Runtime {
    public const VERSION = '5.38.0';
    public const SHORTCODE = 'sc_library_unified_runtime';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-unified-runtime-v5380', SC_LIBRARY_URL . 'assets/css/sc-library-unified-runtime-v5380.css', [], self::VERSION);
        wp_enqueue_script('sc-library-unified-runtime-v5380', SC_LIBRARY_URL . 'assets/js/sc-library-unified-runtime-v5380.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-unified-runtime-'.wp_generate_uuid4();
        $status=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/unified-runtime-status');
        $resolve=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/unified-runtime-resolve');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-unified-runtime" data-sc-unified-runtime-root data-status-endpoint="<?php echo esc_url($status); ?>" data-resolve-endpoint="<?php echo esc_url($resolve); ?>">
          <header>
            <p class="sc-unified-runtime__eyebrow">Unified Research Runtime</p>
            <h2>One governed contract across Python, Go and Rust</h2>
            <p>Inspect runtime availability and resolve a workload to its compatible execution engine before work is dispatched.</p>
          </header>
          <div class="sc-unified-runtime__cards" data-sc-runtime-cards></div>
          <div class="sc-unified-runtime__controls">
            <label>Workload<select data-sc-runtime-workload><option value="research-corpus-build">Research corpus build</option><option value="dataset-export">Dataset export</option><option value="ingestion-job-submit">Ingestion job submit</option><option value="native-graph-query">Native graph query</option></select></label>
            <label>Runtime preference<select data-sc-runtime-preference><option value="auto">Auto</option><option value="python">Python</option><option value="go">Go</option><option value="rust">Rust</option></select></label>
            <label class="sc-unified-runtime__check"><input type="checkbox" data-sc-runtime-fallback checked> Allow explicit compatible fallback</label>
          </div>
          <div class="sc-unified-runtime__actions"><button type="button" data-sc-runtime-refresh>Refresh runtime status</button><button type="button" data-sc-runtime-resolve>Resolve route</button></div>
          <div class="sc-unified-runtime__status" data-sc-runtime-status>Loading runtime contract…</div>
          <pre class="sc-unified-runtime__output" data-sc-runtime-output></pre>
          <footer>Runtime choice is an execution decision, not an evidence-quality, truth, causality, consensus, or Platform Core promotion decision.</footer>
        </section><?php
        return (string) ob_get_clean();
    }
}
