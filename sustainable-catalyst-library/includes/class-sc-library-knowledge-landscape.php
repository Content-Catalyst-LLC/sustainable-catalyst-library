<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.17.0 — Scientific publication knowledge landscape.
 *
 * Renders an interactive analytical graph from the Library Python backend.
 * Relationships remain typed by their actual basis: citation, reviewed concept
 * association, source-span co-occurrence, or real stored-embedding similarity.
 */
final class SC_Library_Knowledge_Landscape {
    public const VERSION = '5.17.0';
    public const SHORTCODE = 'sc_library_knowledge_landscape';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-knowledge-landscape-v5170',
            SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-landscape-v5170.css',
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-knowledge-landscape-v5170',
            SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-landscape-v5170.js',
            [],
            self::VERSION,
            true
        );
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'record_id' => '',
            'semantic_threshold' => '0.72',
            'max_neighbors' => '40',
            'max_topics' => '36',
            'height' => '680',
            'empty' => 'show',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);

        $record_id = sanitize_text_field((string) $atts['record_id']);
        if ($record_id === '' && function_exists('get_the_ID')) {
            $post_id = (int) get_the_ID();
            if ($post_id > 0) {
                $record_id = 'wordpress:' . (int) get_current_blog_id() . ':post:' . $post_id;
            }
        }
        if ($record_id === '' || !class_exists('SC_Library_Python_Backend')) {
            return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state();
        }

        $threshold = max(0.0, min(1.0, (float) $atts['semantic_threshold']));
        $neighbors = min(100, max(0, (int) $atts['max_neighbors']));
        $topics = min(100, max(1, (int) $atts['max_topics']));
        $height = min(960, max(480, (int) $atts['height']));
        $payload = SC_Library_Python_Backend::publication_knowledge_map($record_id, $threshold, $neighbors, $topics);
        $nodes = isset($payload['nodes']) && is_array($payload['nodes']) ? $payload['nodes'] : [];
        if (!$nodes) {
            return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state();
        }

        $this->enqueue_assets();
        $id = 'sc-kl-' . wp_generate_uuid4();
        $metrics = isset($payload['metrics']) && is_array($payload['metrics']) ? $payload['metrics'] : [];
        $semantic = isset($payload['semantic_analysis']) && is_array($payload['semantic_analysis']) ? $payload['semantic_analysis'] : [];
        $safe_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-kl" style="--sc-kl-height:<?php echo esc_attr((string) $height); ?>px" data-sc-kl-root>
            <header class="sc-kl__header">
                <div>
                    <p class="sc-kl__eyebrow"><?php esc_html_e('Publication Knowledge Analysis', 'sustainable-catalyst-library'); ?></p>
                    <h2><?php echo esc_html((string) ($payload['title'] ?? __('Scientific Knowledge Landscape', 'sustainable-catalyst-library'))); ?></h2>
                    <p><?php esc_html_e('Interactive semantic and graphical representation of source-grounded topics, publications, citations, and measured research relationships.', 'sustainable-catalyst-library'); ?></p>
                </div>
                <div class="sc-kl__status-row">
                    <span class="sc-kl__status is-good"><?php esc_html_e('Source-grounded', 'sustainable-catalyst-library'); ?></span>
                    <span class="sc-kl__status <?php echo !empty($semantic['available']) ? 'is-good' : 'is-neutral'; ?>">
                        <?php echo !empty($semantic['available']) ? esc_html__('Semantic vectors online', 'sustainable-catalyst-library') : esc_html__('Structural mode', 'sustainable-catalyst-library'); ?>
                    </span>
                </div>
            </header>

            <nav class="sc-kl__viewbar" aria-label="<?php esc_attr_e('Knowledge map views', 'sustainable-catalyst-library'); ?>">
                <button type="button" class="is-active" data-sc-kl-view="knowledge-landscape"><?php esc_html_e('Knowledge Landscape', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="topic-graph"><?php esc_html_e('Topic Graph', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="citation-overlay"><?php esc_html_e('Citation Overlay', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="semantic-overlay" <?php disabled(empty($semantic['available'])); ?>><?php esc_html_e('Semantic Overlay', 'sustainable-catalyst-library'); ?></button>
            </nav>

            <div class="sc-kl__workspace">
                <aside class="sc-kl__panel sc-kl__panel--left">
                    <div class="sc-kl__panel-title"><?php esc_html_e('Layers & Filters', 'sustainable-catalyst-library'); ?></div>
                    <fieldset class="sc-kl__fieldset">
                        <legend><?php esc_html_e('Node layers', 'sustainable-catalyst-library'); ?></legend>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="publication"> <span class="sc-kl__dot is-publication"></span><?php esc_html_e('Publications', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="topic"> <span class="sc-kl__dot is-topic"></span><?php esc_html_e('Topics & concepts', 'sustainable-catalyst-library'); ?></label>
                    </fieldset>
                    <fieldset class="sc-kl__fieldset">
                        <legend><?php esc_html_e('Relationships', 'sustainable-catalyst-library'); ?></legend>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="explicit-citation"> <?php esc_html_e('Citations', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="metadata-association"> <?php esc_html_e('Metadata topics', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-concept-association"> <?php esc_html_e('Reviewed concepts', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="source-span-cooccurrence"> <?php esc_html_e('Source co-occurrence', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="embedding-cosine-similarity" <?php disabled(empty($semantic['available'])); ?>> <?php esc_html_e('Semantic similarity', 'sustainable-catalyst-library'); ?></label>
                    </fieldset>
                    <div class="sc-kl__control">
                        <label for="<?php echo esc_attr($id); ?>-strength"><?php esc_html_e('Relationship strength', 'sustainable-catalyst-library'); ?></label>
                        <input id="<?php echo esc_attr($id); ?>-strength" type="range" min="0" max="1" step="0.05" value="0" data-sc-kl-strength>
                        <output data-sc-kl-strength-output>0.00</output>
                    </div>
                    <div class="sc-kl__legend">
                        <div><span class="sc-kl__line is-citation"></span><?php esc_html_e('Explicit citation', 'sustainable-catalyst-library'); ?></div>
                        <div><span class="sc-kl__line is-source"></span><?php esc_html_e('Source association', 'sustainable-catalyst-library'); ?></div>
                        <div><span class="sc-kl__line is-semantic"></span><?php esc_html_e('Analytical similarity', 'sustainable-catalyst-library'); ?></div>
                    </div>
                    <button type="button" class="sc-kl__reset" data-sc-kl-reset><?php esc_html_e('Reset view', 'sustainable-catalyst-library'); ?></button>
                </aside>

                <div class="sc-kl__stage-wrap">
                    <div class="sc-kl__toolbar">
                        <div class="sc-kl__toolbar-group">
                            <button type="button" data-sc-kl-layout="force" class="is-active"><?php esc_html_e('Network', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" data-sc-kl-layout="radial"><?php esc_html_e('Radial', 'sustainable-catalyst-library'); ?></button>
                        </div>
                        <div class="sc-kl__toolbar-group">
                            <button type="button" aria-label="Zoom in" data-sc-kl-zoom="in">+</button>
                            <button type="button" aria-label="Zoom out" data-sc-kl-zoom="out">−</button>
                            <button type="button" aria-label="Fit map" data-sc-kl-fit>⌗</button>
                        </div>
                    </div>
                    <div class="sc-kl__stage" tabindex="0" role="application" aria-label="<?php esc_attr_e('Interactive publication knowledge map', 'sustainable-catalyst-library'); ?>">
                        <svg class="sc-kl__svg" viewBox="0 0 1200 760" aria-hidden="true"></svg>
                        <div class="sc-kl__axis"><span>X — structural / semantic separation</span><span>Y — relational density</span></div>
                    </div>
                    <div class="sc-kl__metrics-strip">
                        <div><strong><?php echo esc_html((string) ($metrics['publication_count'] ?? 0)); ?></strong><span><?php esc_html_e('publications', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong><?php echo esc_html((string) ($metrics['topic_count'] ?? 0)); ?></strong><span><?php esc_html_e('topics', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong><?php echo esc_html((string) ($metrics['edge_count'] ?? 0)); ?></strong><span><?php esc_html_e('relationships', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong><?php echo esc_html((string) ($semantic['vector_count'] ?? 0)); ?></strong><span><?php esc_html_e('semantic vectors', 'sustainable-catalyst-library'); ?></span></div>
                    </div>
                </div>

                <aside class="sc-kl__panel sc-kl__panel--right">
                    <div class="sc-kl__panel-title"><?php esc_html_e('Research Inspector', 'sustainable-catalyst-library'); ?></div>
                    <div class="sc-kl__inspector" data-sc-kl-inspector>
                        <p class="sc-kl__muted"><?php esc_html_e('Select a node to inspect its research context, provenance, and relationship metrics.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                    <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Analysis Method', 'sustainable-catalyst-library'); ?></div>
                    <dl class="sc-kl__method">
                        <div><dt><?php esc_html_e('Citations', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Explicit', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Concepts', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Human reviewed', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Co-occurrence', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Source-span measured', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Semantic links', 'sustainable-catalyst-library'); ?></dt><dd><?php echo !empty($semantic['available']) ? esc_html__('Cosine similarity', 'sustainable-catalyst-library') : esc_html__('Unavailable', 'sustainable-catalyst-library'); ?></dd></div>
                    </dl>
                    <div class="sc-kl__integrity">
                        <strong><?php esc_html_e('Interpretation boundary', 'sustainable-catalyst-library'); ?></strong>
                        <p><?php esc_html_e('Analytical similarity and co-occurrence show measured relationships, not factual or causal claims.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                </aside>
            </div>
            <details class="sc-kl__accessible">
                <summary><?php esc_html_e('Accessible graph data', 'sustainable-catalyst-library'); ?></summary>
                <p><?php echo esc_html(sprintf(__('This view contains %1$d nodes and %2$d typed relationships.', 'sustainable-catalyst-library'), (int) ($metrics['node_count'] ?? 0), (int) ($metrics['edge_count'] ?? 0))); ?></p>
                <ul>
                    <?php foreach (array_slice($nodes, 0, 100) as $node) : if (!is_array($node)) { continue; } ?>
                        <li><strong><?php echo esc_html((string) ($node['kind'] ?? 'node')); ?>:</strong> <?php echo esc_html((string) ($node['label'] ?? $node['id'] ?? '')); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <script type="application/json" class="sc-kl__data"><?php echo $safe_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function empty_state(): string {
        return '<div class="sc-kl-empty">' . esc_html__('A scientific knowledge map is not available for this publication yet.', 'sustainable-catalyst-library') . '</div>';
    }
}
