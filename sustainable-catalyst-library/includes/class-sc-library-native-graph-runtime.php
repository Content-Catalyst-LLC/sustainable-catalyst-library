<?php
if (!defined('ABSPATH')) { exit; }

/** v5.32.0 — Rust Research Graph Runtime Foundation status console. */
final class SC_Library_Native_Graph_Runtime {
    public const VERSION = '5.32.0';
    public const SHORTCODE = 'sc_library_native_graph_runtime';
    public function register_hooks(): void { add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']); }
    private function enqueue_assets(): void {
        wp_enqueue_style('sc-library-native-graph-runtime-v5320', SC_LIBRARY_URL . 'assets/css/sc-library-native-graph-runtime-v5320.css', [], self::VERSION);
        wp_enqueue_script('sc-library-native-graph-runtime-v5320', SC_LIBRARY_URL . 'assets/js/sc-library-native-graph-runtime-v5320.js', [], self::VERSION, true);
    }
    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets(); $id='sc-native-graph-'.wp_generate_uuid4();
        $status=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/native-graph-runtime-status');
        $path=rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-evidence-pathfind');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-native-graph" data-sc-native-graph-root data-status-endpoint="<?php echo esc_url($status); ?>" data-path-endpoint="<?php echo esc_url($path); ?>">
          <header><p class="sc-native-graph__eyebrow">Rust Research Graph Runtime</p><h2>Native graph acceleration with Python research semantics</h2><p>Inspect native runtime availability and contract identity. Evidence-path responses remain governed by the Python Library backend and Platform Core research-object boundaries.</p></header>
          <div class="sc-native-graph__actions"><button type="button" data-sc-native-refresh>Check native runtime</button></div>
          <div class="sc-native-graph__status" data-sc-native-status>Runtime status not loaded.</div>
          <dl class="sc-native-graph__metrics" data-sc-native-metrics></dl>
          <footer>Guardrail: Rust accelerates deterministic graph structure only. It does not infer truth, causality, consensus, evidence quality, or new research claims. Python fallback preserves the public pathfinding contract if the native binary is unavailable.</footer>
        </section><?php return (string)ob_get_clean();
    }
}
