<?php
if (!defined('ABSPATH')) { exit; }

/** v5.39.0 — Cross-Runtime Reproducibility & Execution Lineage. */
final class SC_Library_Execution_Lineage {
    public const VERSION = '5.39.0';
    public const SHORTCODE = 'sc_library_execution_lineage';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-execution-lineage-v5390', SC_LIBRARY_URL . 'assets/css/sc-library-execution-lineage-v5390.css', [], self::VERSION);
        wp_enqueue_script('sc-library-execution-lineage-v5390', SC_LIBRARY_URL . 'assets/js/sc-library-execution-lineage-v5390.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-execution-lineage-'.wp_generate_uuid4();
        $status=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/runtime-reproducibility-status');
        $record=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/runtime-reproducibility-record');
        $verify=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/runtime-reproducibility-verify');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-execution-lineage" data-sc-execution-lineage-root data-status-endpoint="<?php echo esc_url($status); ?>" data-record-endpoint="<?php echo esc_url($record); ?>" data-verify-endpoint="<?php echo esc_url($verify); ?>">
          <header>
            <p class="sc-execution-lineage__eyebrow">Reproducibility &amp; Execution Lineage</p>
            <h2>Trace how research work moved through Python, Go and Rust</h2>
            <p>Inspect runtime/environment fingerprints, create replayable execution records, and verify deterministic workloads without turning matching output into a scientific-equivalence claim.</p>
          </header>
          <div class="sc-execution-lineage__cards" data-sc-lineage-cards></div>
          <div class="sc-execution-lineage__controls">
            <label>Workload<select data-sc-lineage-workload><option value="research-corpus-build">Research corpus build</option><option value="dataset-export">Dataset export</option><option value="ingestion-job-submit">Ingestion job submit</option><option value="native-graph-query">Native graph query</option></select></label>
            <label>Runtime<select data-sc-lineage-runtime><option value="auto">Auto</option><option value="python">Python</option><option value="go">Go</option><option value="rust">Rust</option></select></label>
          </div>
          <div class="sc-execution-lineage__actions"><button type="button" data-sc-lineage-refresh>Refresh status</button></div>
          <div class="sc-execution-lineage__status" data-sc-lineage-status>Loading reproducibility contract…</div>
          <pre class="sc-execution-lineage__output" data-sc-lineage-output></pre>
          <footer>Observed output matches are reproducibility evidence, not proof of truth, scientific validity, causal correctness, or cross-runtime equivalence.</footer>
        </section><?php
        return (string) ob_get_clean();
    }
}
