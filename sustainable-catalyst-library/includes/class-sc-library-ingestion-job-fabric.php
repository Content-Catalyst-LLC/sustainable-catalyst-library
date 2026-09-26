<?php
if (!defined('ABSPATH')) { exit; }

/** v5.36.0 — Go Research Ingestion & Job Fabric console. */
final class SC_Library_Ingestion_Job_Fabric {
    public const VERSION = '5.36.0';
    public const SHORTCODE = 'sc_library_ingestion_job_fabric';

    public function register_hooks(): void { add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']); }

    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-ingestion-fabric-v5360', SC_LIBRARY_URL . 'assets/css/sc-library-ingestion-fabric-v5360.css', [], self::VERSION);
        wp_enqueue_script('sc-library-ingestion-fabric-v5360', SC_LIBRARY_URL . 'assets/js/sc-library-ingestion-fabric-v5360.js', [], self::VERSION, true);
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id='sc-ingestion-fabric-' . wp_generate_uuid4();
        $status=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/ingestion-fabric-status');
        $jobs=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/ingestion-jobs');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-ingestion-fabric" data-sc-ingestion-fabric data-status-endpoint="<?php echo esc_url($status); ?>" data-jobs-endpoint="<?php echo esc_url($jobs); ?>">
          <header><p class="sc-ingestion-fabric__eyebrow">Go Research Ingestion Runtime · v0.1</p><h2>Concurrent ingestion and research-job coordination</h2><p>Queue connector, OCR, parsing, extraction, identity, embedding, and indexing work through the Go execution fabric while Python retains research interpretation and validation.</p></header>
          <div class="sc-ingestion-fabric__actions"><button type="button" data-sc-ingestion-refresh>Check fabric</button><button type="button" data-sc-ingestion-jobs>Refresh jobs</button></div>
          <div data-sc-ingestion-status>Fabric status not loaded.</div>
          <dl class="sc-ingestion-fabric__metrics" data-sc-ingestion-metrics></dl>
          <pre class="sc-ingestion-fabric__jobs" data-sc-ingestion-output>No jobs loaded.</pre>
          <footer>Guardrail: queue state and successful execution are operational facts only. They do not verify a source, establish evidence quality or truth, or promote governed Platform Core objects.</footer>
        </section>
        <?php return (string)ob_get_clean();
    }
}
