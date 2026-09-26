<?php
if (!defined('ABSPATH')) { exit; }

/** v5.35.0 — Rust Evidence Graph Acceleration & Native Query Engine console. */
final class SC_Library_Native_Graph_Runtime {
    public const VERSION = '5.35.0';
    public const SHORTCODE = 'sc_library_native_graph_runtime';
    public const QUERY_SHORTCODE = 'sc_library_native_graph_query';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
        add_shortcode(self::QUERY_SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-native-graph-runtime-v5350',
            SC_LIBRARY_URL . 'assets/css/sc-library-native-graph-runtime-v5350.css',
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-native-graph-runtime-v5350',
            SC_LIBRARY_URL . 'assets/js/sc-library-native-graph-runtime-v5350.js',
            [],
            self::VERSION,
            true
        );
    }

    public function render_shortcode($atts=[]): string {
        $this->enqueue_assets();
        $id = 'sc-native-graph-' . wp_generate_uuid4();
        $status = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/native-graph-runtime-status');
        $query = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-native-graph-query');
        ob_start(); ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-native-graph" data-sc-native-graph-root data-status-endpoint="<?php echo esc_url($status); ?>" data-query-endpoint="<?php echo esc_url($query); ?>">
          <header>
            <p class="sc-native-graph__eyebrow">Rust Evidence Graph Runtime · v0.2</p>
            <h2>Native structural graph queries with Python research semantics</h2>
            <p>Explore policy-filtered graph structure using native Rust acceleration. Python remains responsible for relationship policy, evidence semantics, provenance, guardrails, and Platform Core handoffs.</p>
          </header>
          <div class="sc-native-graph__actions">
            <button type="button" data-sc-native-refresh>Check native runtime</button>
          </div>
          <div class="sc-native-graph__status" data-sc-native-status>Runtime status not loaded.</div>
          <dl class="sc-native-graph__metrics" data-sc-native-metrics></dl>
          <div class="sc-native-graph__query">
            <h3>Native Graph Query</h3>
            <div class="sc-native-graph__grid">
              <label>Operation
                <select data-sc-native-operation>
                  <option value="structural-stats">Structural statistics</option>
                  <option value="neighborhood">Neighborhood</option>
                  <option value="reachability">Reachability</option>
                  <option value="connected-components">Connected components</option>
                  <option value="subgraph">Induced subgraph</option>
                </select>
              </label>
              <label>Start node IDs
                <input type="text" data-sc-native-starts placeholder="publication:123, finding:456">
              </label>
              <label>Node IDs / subgraph scope
                <input type="text" data-sc-native-nodes placeholder="optional comma-separated IDs">
              </label>
              <label>Depth
                <input type="number" min="0" max="8" value="2" data-sc-native-depth>
              </label>
            </div>
            <label class="sc-native-graph__check"><input type="checkbox" data-sc-native-analytical> Include analytical/similarity relationships</label>
            <div class="sc-native-graph__actions"><button type="button" data-sc-native-run>Run native query</button></div>
            <pre class="sc-native-graph__result" data-sc-native-result>Run a query to inspect structural results.</pre>
          </div>
          <footer>Guardrail: graph connectivity, component membership, degree, and reachability are structural properties. They do not establish truth, causality, consensus, evidence quality, identity, or scientific importance. Analytical relationships remain opt-in.</footer>
        </section>
        <?php return (string) ob_get_clean();
    }
}
