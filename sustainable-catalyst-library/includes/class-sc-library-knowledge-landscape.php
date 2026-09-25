<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.24.0 — Cross-Publication Evidence Synthesis & Competing Hypotheses.
 *
 * Renders an interactive analytical graph from the Library Python backend.
 * Relationships remain typed by their actual basis: citation, reviewed concept
 * association, source-span co-occurrence, or real stored-embedding similarity.
 */
final class SC_Library_Knowledge_Landscape {
    public const VERSION = '5.24.0';
    public const SHORTCODE = 'sc_library_knowledge_landscape';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-knowledge-landscape-v5240',
            SC_LIBRARY_URL . 'assets/css/sc-library-knowledge-landscape-v5240.css',
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-knowledge-landscape-v5240',
            SC_LIBRARY_URL . 'assets/js/sc-library-knowledge-landscape-v5240.js',
            [],
            self::VERSION,
            true
        );
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'scope' => 'corpus',
            'record_id' => '',
            'source_key' => 'wordpress-main',
            'object_type' => '',
            'semantic_threshold' => '0.72',
            'max_neighbors' => '40',
            'max_publications' => '250',
            'max_topics' => '36',
            'height' => '680',
            'empty' => 'show',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);

        if (!class_exists('SC_Library_Python_Backend')) {
            return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state('backend');
        }

        $scope = sanitize_key((string) $atts['scope']);
        if (!in_array($scope, ['corpus', 'publication'], true)) { $scope = 'corpus'; }
        $threshold = max(0.0, min(1.0, (float) $atts['semantic_threshold']));
        $topics = min(100, max(1, (int) $atts['max_topics']));
        $height = min(960, max(480, (int) $atts['height']));

        $async_corpus = false;
        $async_config = [];
        if ('publication' === $scope) {
            $record_id = sanitize_text_field((string) $atts['record_id']);
            if ($record_id === '' && function_exists('get_the_ID')) {
                $post_id = (int) get_the_ID();
                if ($post_id > 0 && class_exists('SC_Library_Python_Backend')) {
                    $record_id = SC_Library_Python_Backend::record_id($post_id, get_post_type($post_id));
                }
            }
            if ($record_id === '') {
                return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state('publication');
            }
            $neighbors = min(100, max(0, (int) $atts['max_neighbors']));
            $payload = SC_Library_Python_Backend::publication_knowledge_map($record_id, $threshold, $neighbors, $topics);
            $nodes = isset($payload['nodes']) && is_array($payload['nodes']) ? $payload['nodes'] : [];
            if (!$nodes) {
                return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state($scope);
            }
        } else {
            // v5.20.0.1: corpus mode renders immediately and loads research-sized analytical
            // payloads asynchronously through the WordPress REST proxy. The proxy resolves
            // the canonical Publication Library manifest and sends it to Python via POST JSON.
            $async_corpus = true;
            $source_key = sanitize_text_field((string) $atts['source_key']) ?: 'wordpress-main';
            $object_type = sanitize_key((string) $atts['object_type']);
            $max_publications = min(1000, max(1, (int) $atts['max_publications']));
            $payload = [
                'schema' => 'sc-library-publication-corpus-knowledge-map/1.0',
                'scope' => 'corpus',
                'title' => __('Scientific Knowledge Landscape', 'sustainable-catalyst-library'),
                'nodes' => [],
                'edges' => [],
                'metrics' => [],
                'semantic_analysis' => ['available' => false, 'vector_count' => 0],
                'topic_regions' => [],
                'temporal_dynamics' => ['years' => []],
                'knowledge_terrain_4d' => ['topic_anchors' => []],
            ];
            $async_config = [
                'endpoint' => esc_url_raw(rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-corpus-knowledge-map')),
                'scope' => 'corpus',
                'source_key' => $source_key,
                'object_type' => $object_type,
                'semantic_threshold' => $threshold,
                'max_publications' => $max_publications,
                'max_topics_per_publication' => $topics,
                'timeout_ms' => 90000,
                'session_endpoint' => esc_url_raw(rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-visual-session-package')),
                'evidence_trace_endpoint' => esc_url_raw(rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/publication-visual-evidence-trace')),
            ];
            $nodes = [];
        }

        $this->enqueue_assets();
        $id = 'sc-kl-' . wp_generate_uuid4();
        $metrics = isset($payload['metrics']) && is_array($payload['metrics']) ? $payload['metrics'] : [];
        $semantic = isset($payload['semantic_analysis']) && is_array($payload['semantic_analysis']) ? $payload['semantic_analysis'] : [];
        $safe_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-kl" data-sc-kl-async="<?php echo $async_corpus ? '1' : '0'; ?>" style="--sc-kl-height:<?php echo esc_attr((string) $height); ?>px" data-sc-kl-root>
            <header class="sc-kl__header">
                <div>
                    <p class="sc-kl__eyebrow"><?php echo 'corpus' === $scope ? esc_html__('Publication Corpus Analysis', 'sustainable-catalyst-library') : esc_html__('Publication Knowledge Analysis', 'sustainable-catalyst-library'); ?></p>
                    <h2 data-sc-kl-title><?php echo esc_html((string) ($payload['title'] ?? __('Scientific Knowledge Landscape', 'sustainable-catalyst-library'))); ?></h2>
                    <p><?php esc_html_e('Linked scientific knowledge environment connecting 4D terrain, topic regions, publication relationships, citation flows, temporal dynamics, and visual queries across the Research Library corpus.', 'sustainable-catalyst-library'); ?></p>
                </div>
                <div class="sc-kl__status-row">
                    <?php if ('corpus' === $scope) : ?><span class="sc-kl__status is-good"><?php esc_html_e('Publication Library manifest', 'sustainable-catalyst-library'); ?></span><?php endif; ?>
                    <span class="sc-kl__status is-good"><?php esc_html_e('Source-grounded', 'sustainable-catalyst-library'); ?></span>
                    <span class="sc-kl__status <?php echo !empty($semantic['available']) ? 'is-good' : 'is-neutral'; ?>" data-sc-kl-semantic-status>
                        <?php echo !empty($semantic['available']) ? esc_html__('Semantic vectors online', 'sustainable-catalyst-library') : esc_html__('Structural mode', 'sustainable-catalyst-library'); ?>
                    </span>
                </div>
            </header>

            <nav class="sc-kl__viewbar" aria-label="<?php esc_attr_e('Knowledge map views', 'sustainable-catalyst-library'); ?>">
                <button type="button" class="is-active" data-sc-kl-view="knowledge-landscape"><?php esc_html_e('Knowledge Landscape', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="knowledge-terrain-4d"><?php esc_html_e('4D Knowledge Terrain', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="topic-graph"><?php esc_html_e('Topic Graph', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="citation-overlay"><?php esc_html_e('Citation Overlay', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="topic-regions"><?php esc_html_e('Topic Regions', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="temporal-dynamics"><?php esc_html_e('Temporal Dynamics', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="relationship-matrix"><?php esc_html_e('Relationship Matrix', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="semantic-overlay" <?php disabled(empty($semantic['available'])); ?>><?php esc_html_e('Semantic Overlay', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="findings-claims"><?php esc_html_e('Findings & Claims', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="contradiction-overlay"><?php esc_html_e('Contradictions', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="evidence-synthesis"><?php esc_html_e('Evidence Synthesis', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-kl-view="competing-hypotheses"><?php esc_html_e('Competing Hypotheses', 'sustainable-catalyst-library'); ?></button>
            </nav>

            <div class="sc-kl__workspace">
                <aside class="sc-kl__panel sc-kl__panel--left">
                    <div class="sc-kl__panel-title"><?php esc_html_e('Layers & Filters', 'sustainable-catalyst-library'); ?></div>
                    <fieldset class="sc-kl__fieldset">
                        <legend><?php esc_html_e('Node layers', 'sustainable-catalyst-library'); ?></legend>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="publication"> <span class="sc-kl__dot is-publication"></span><?php esc_html_e('Publications', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="topic"> <span class="sc-kl__dot is-topic"></span><?php esc_html_e('Topics & concepts', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="finding"> <span class="sc-kl__dot is-finding"></span><?php esc_html_e('Reviewed findings', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="claim"> <span class="sc-kl__dot is-claim"></span><?php esc_html_e('Accepted claims', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-node-kind="hypothesis"> <span class="sc-kl__dot is-hypothesis"></span><?php esc_html_e('Explicit hypotheses', 'sustainable-catalyst-library'); ?></label>
                    </fieldset>
                    <fieldset class="sc-kl__fieldset">
                        <legend><?php esc_html_e('Relationships', 'sustainable-catalyst-library'); ?></legend>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="explicit-citation"> <?php esc_html_e('Citations', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="metadata-association"> <?php esc_html_e('Metadata topics', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-concept-association"> <?php esc_html_e('Reviewed concepts', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="source-span-cooccurrence"> <?php esc_html_e('Source co-occurrence', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="publication-topic-cooccurrence"> <?php esc_html_e('Corpus topic co-occurrence', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="embedding-cosine-similarity" <?php disabled(empty($semantic['available'])); ?>> <?php esc_html_e('Semantic similarity', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-finding-evidence"> <?php esc_html_e('Finding evidence', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-claim-evidence"> <?php esc_html_e('Claim evidence', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-explicit-support"> <?php esc_html_e('Reviewed support', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="reviewed-explicit-contradiction"> <?php esc_html_e('Reviewed contradiction', 'sustainable-catalyst-library'); ?></label>
                        <label><input type="checkbox" checked data-sc-kl-edge-basis="explicit-hypothesis-membership"> <?php esc_html_e('Explicit hypothesis membership', 'sustainable-catalyst-library'); ?></label>
                    </fieldset>
                    <div class="sc-kl__terrain-controls" data-sc-kl-terrain-controls hidden>
                        <div class="sc-kl__control">
                            <label for="<?php echo esc_attr($id); ?>-elevation"><?php esc_html_e('Terrain elevation', 'sustainable-catalyst-library'); ?></label>
                            <select id="<?php echo esc_attr($id); ?>-elevation" data-sc-kl-elevation>
                                <option value="relationship_density"><?php esc_html_e('Relationship density', 'sustainable-catalyst-library'); ?></option>
                                <option value="publication_density"><?php esc_html_e('Publication density', 'sustainable-catalyst-library'); ?></option>
                                <option value="temporal_activity"><?php esc_html_e('Temporal activity', 'sustainable-catalyst-library'); ?></option>
                            </select>
                        </div>
                        <div class="sc-kl__control">
                            <label for="<?php echo esc_attr($id); ?>-time"><?php esc_html_e('Publication time', 'sustainable-catalyst-library'); ?></label>
                            <input id="<?php echo esc_attr($id); ?>-time" type="range" min="0" max="0" step="1" value="0" data-sc-kl-time>
                            <div class="sc-kl__time-row"><button type="button" data-sc-kl-play aria-label="Play time">▶</button><output data-sc-kl-time-output>—</output></div>
                        </div>
                    </div>
                    <div class="sc-kl__control">
                        <label for="<?php echo esc_attr($id); ?>-strength"><?php esc_html_e('Relationship strength', 'sustainable-catalyst-library'); ?></label>
                        <input id="<?php echo esc_attr($id); ?>-strength" type="range" min="0" max="1" step="0.05" value="0" data-sc-kl-strength>
                        <output data-sc-kl-strength-output>0.00</output>
                    </div>
                    <fieldset class="sc-kl__query" data-sc-kl-query-panel>
                        <legend><?php esc_html_e('Visual Query', 'sustainable-catalyst-library'); ?></legend>
                        <label for="<?php echo esc_attr($id); ?>-query-text"><?php esc_html_e('Find topic or publication', 'sustainable-catalyst-library'); ?></label>
                        <input id="<?php echo esc_attr($id); ?>-query-text" type="search" placeholder="<?php esc_attr_e('e.g. resilience, grid, AI', 'sustainable-catalyst-library'); ?>" data-sc-kl-query-text>
                        <label for="<?php echo esc_attr($id); ?>-query-mode"><?php esc_html_e('Selection behavior', 'sustainable-catalyst-library'); ?></label>
                        <select id="<?php echo esc_attr($id); ?>-query-mode" data-sc-kl-query-mode>
                            <option value="highlight"><?php esc_html_e('Highlight in every view', 'sustainable-catalyst-library'); ?></option>
                            <option value="isolate"><?php esc_html_e('Isolate matching research', 'sustainable-catalyst-library'); ?></option>
                        </select>
                        <div class="sc-kl__query-chips" data-sc-kl-query-chips><span><?php esc_html_e('No active selection', 'sustainable-catalyst-library'); ?></span></div>
                        <button type="button" class="sc-kl__query-clear" data-sc-kl-query-clear><?php esc_html_e('Clear visual query', 'sustainable-catalyst-library'); ?></button>
                    </fieldset>
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
                            <button type="button" data-sc-kl-layout="regions"><?php esc_html_e('Regions', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" data-sc-kl-layout="time"><?php esc_html_e('Time', 'sustainable-catalyst-library'); ?></button>
                        </div>
                        <div class="sc-kl__toolbar-group">
                            <button type="button" aria-label="Zoom in" data-sc-kl-zoom="in">+</button>
                            <button type="button" aria-label="Zoom out" data-sc-kl-zoom="out">−</button>
                            <button type="button" aria-label="Fit map" data-sc-kl-fit>⌗</button>
                        </div>
                    </div>
                    <div class="sc-kl__stage" tabindex="0" role="application" aria-label="<?php esc_attr_e('Interactive publication knowledge map', 'sustainable-catalyst-library'); ?>">
                        <?php if ($async_corpus) : ?>
                        <div class="sc-kl__load-state is-loading" data-sc-kl-load-state role="status" aria-live="polite">
                            <strong data-sc-kl-load-title><?php esc_html_e('Connecting to Publication Library…', 'sustainable-catalyst-library'); ?></strong>
                            <span data-sc-kl-load-detail><?php esc_html_e('Preparing the canonical publication corpus for scientific analysis.', 'sustainable-catalyst-library'); ?></span>
                            <button type="button" data-sc-kl-retry hidden><?php esc_html_e('Retry corpus load', 'sustainable-catalyst-library'); ?></button>
                        </div>
                        <?php endif; ?>
                        <svg class="sc-kl__svg" viewBox="0 0 1200 760" aria-hidden="true"></svg>
                        <canvas class="sc-kl__terrain" data-sc-kl-terrain hidden aria-label="Interactive 4D publication knowledge terrain"></canvas>
                        <div class="sc-kl__terrain-hud" data-sc-kl-terrain-hud hidden><span data-sc-kl-terrain-year>—</span><span data-sc-kl-terrain-metric>Relationship density</span><span><?php esc_html_e('Drag to orbit · Wheel to zoom', 'sustainable-catalyst-library'); ?></span></div>
                        <div class="sc-kl__matrix" data-sc-kl-matrix hidden></div>
                        <div class="sc-kl__axis" data-sc-kl-axis><span>X — structural / topic-region separation</span><span>Y — relational density</span></div>
                    </div>
                    <?php $regions = isset($payload['topic_regions']) && is_array($payload['topic_regions']) ? $payload['topic_regions'] : []; $temporal = isset($payload['temporal_dynamics']) && is_array($payload['temporal_dynamics']) ? $payload['temporal_dynamics'] : []; $years = isset($temporal['years']) && is_array($temporal['years']) ? $temporal['years'] : []; ?>
                    <div class="sc-kl__metrics-strip">
                        <div><strong data-sc-kl-metric="publication_count"><?php echo esc_html((string) ($metrics['publication_count'] ?? 0)); ?></strong><span><?php esc_html_e('publications', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="topic_count"><?php echo esc_html((string) ($metrics['topic_count'] ?? 0)); ?></strong><span><?php esc_html_e('topics', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="topic_regions"><?php echo esc_html((string) count($regions)); ?></strong><span><?php esc_html_e('topic regions', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="edge_count"><?php echo esc_html((string) ($metrics['edge_count'] ?? 0)); ?></strong><span><?php esc_html_e('relationships', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="semantic_vectors"><?php echo esc_html((string) ($semantic['vector_count'] ?? 0)); ?></strong><span><?php esc_html_e('semantic vectors', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="year_span"><?php echo $years ? esc_html((string) (max($years) - min($years) + 1)) : '0'; ?></strong><span><?php esc_html_e('year span', 'sustainable-catalyst-library'); ?></span></div>
                        <div><strong data-sc-kl-metric="terrain_anchors"><?php echo esc_html((string) count($payload['knowledge_terrain_4d']['topic_anchors'] ?? [])); ?></strong><span><?php esc_html_e('terrain anchors', 'sustainable-catalyst-library'); ?></span></div>
                    </div>
                </div>

                <aside class="sc-kl__panel sc-kl__panel--right">
                    <div class="sc-kl__panel-title"><?php esc_html_e('Research Inspector', 'sustainable-catalyst-library'); ?></div>
                    <div class="sc-kl__inspector" data-sc-kl-inspector>
                        <p class="sc-kl__muted"><?php esc_html_e('Select a node to inspect its research context, provenance, and relationship metrics.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                    <div class="sc-kl__evidence-trace" data-sc-kl-evidence-trace>
                        <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Evidence Trace', 'sustainable-catalyst-library'); ?></div>
                        <p class="sc-kl__muted"><?php esc_html_e('Select a topic, publication, region, or relationship, then trace why it appears in the visual model back to source records, passages, citations, and reviewed research candidates.', 'sustainable-catalyst-library'); ?></p>
                        <button type="button" data-sc-kl-trace><?php esc_html_e('Trace selection to sources', 'sustainable-catalyst-library'); ?></button>
                        <div class="sc-kl__trace-results" data-sc-kl-trace-results aria-live="polite"><span class="sc-kl__muted"><?php esc_html_e('No visual selection traced yet.', 'sustainable-catalyst-library'); ?></span></div>
                    </div>
                    <div class="sc-kl__synthesis-summary" data-sc-kl-synthesis-summary>
                        <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Cross-Publication Synthesis', 'sustainable-catalyst-library'); ?></div>
                        <p class="sc-kl__muted"><?php esc_html_e('Reviewed support/contradiction structures are descriptive. Hypotheses appear only when explicitly encoded in reviewed metadata; Platform Core remains the durable synthesis authority.', 'sustainable-catalyst-library'); ?></p>
                        <div class="sc-kl__synthesis-metrics">
                            <span><strong data-sc-kl-synthesis-metric="support_structure_count">0</strong> support structures</span>
                            <span><strong data-sc-kl-synthesis-metric="explicit_contradiction_relation_count">0</strong> contradictions</span>
                            <span><strong data-sc-kl-synthesis-metric="explicit_hypothesis_count">0</strong> explicit hypotheses</span>
                            <span><strong data-sc-kl-synthesis-metric="explicit_competing_hypothesis_set_count">0</strong> competing sets</span>
                        </div>
                    </div>
                    <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Linked Query State', 'sustainable-catalyst-library'); ?></div>
                    <div class="sc-kl__query-state" data-sc-kl-query-summary>
                        <p class="sc-kl__muted"><?php esc_html_e('Selections made in any scientific view will appear here and propagate to the others.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                    <button type="button" class="sc-kl__copy-query" data-sc-kl-copy-query><?php esc_html_e('Copy query state', 'sustainable-catalyst-library'); ?></button>
                    <div class="sc-kl__session-tools" data-sc-kl-session-tools>
                        <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Reproducible Session', 'sustainable-catalyst-library'); ?></div>
                        <p class="sc-kl__muted"><?php esc_html_e('Save and reopen the current view, query, terrain camera, filters, and publication-time context.', 'sustainable-catalyst-library'); ?></p>
                        <div class="sc-kl__session-actions">
                            <button type="button" data-sc-kl-session-save><?php esc_html_e('Save session', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" data-sc-kl-session-restore><?php esc_html_e('Restore last', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" data-sc-kl-session-download><?php esc_html_e('Download session', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" data-sc-kl-session-workspace><?php esc_html_e('Workspace package', 'sustainable-catalyst-library'); ?></button>
                        </div>
                        <span class="sc-kl__session-status" data-sc-kl-session-status aria-live="polite"></span>
                    </div>
                    <div class="sc-kl__panel-title sc-kl__panel-title--secondary"><?php esc_html_e('Analysis Method', 'sustainable-catalyst-library'); ?></div>
                    <dl class="sc-kl__method">
                        <div><dt><?php esc_html_e('Citations', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Explicit', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Concepts', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Human reviewed', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Co-occurrence', 'sustainable-catalyst-library'); ?></dt><dd><?php echo 'corpus' === $scope ? esc_html__('Corpus + source-span measured', 'sustainable-catalyst-library') : esc_html__('Source-span measured', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Semantic links', 'sustainable-catalyst-library'); ?></dt><dd data-sc-kl-semantic-method><?php echo !empty($semantic['available']) ? esc_html__('Cosine similarity', 'sustainable-catalyst-library') : esc_html__('Unavailable', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Topic regions', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Repeated measured co-occurrence', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Time dimension', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Publication dates', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('4D terrain', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Measured topology + selectable Z + time', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Linked views', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Shared deterministic query state', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Visual query', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Highlight or isolate; no source mutation', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Evidence trace', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Source records + passages + explicit citations + reviewed candidates', 'sustainable-catalyst-library'); ?></dd></div>
                        <div><dt><?php esc_html_e('Evidence synthesis', 'sustainable-catalyst-library'); ?></dt><dd><?php esc_html_e('Accepted reviewed relations; no inferred consensus or hypotheses', 'sustainable-catalyst-library'); ?></dd></div>
                    </dl>
                    <div class="sc-kl__integrity">
                        <strong><?php esc_html_e('Interpretation boundary', 'sustainable-catalyst-library'); ?></strong>
                        <p><?php esc_html_e('Analytical similarity and co-occurrence show measured relationships, not factual or causal claims.', 'sustainable-catalyst-library'); ?></p>
                    </div>
                </aside>
            </div>
            <details class="sc-kl__accessible">
                <summary><?php esc_html_e('Accessible graph data', 'sustainable-catalyst-library'); ?></summary>
                <p data-sc-kl-accessible-summary><?php echo esc_html(sprintf(__('This view contains %1$d nodes and %2$d typed relationships.', 'sustainable-catalyst-library'), (int) ($metrics['node_count'] ?? 0), (int) ($metrics['edge_count'] ?? 0))); ?></p>
                <ul data-sc-kl-accessible-list>
                    <?php foreach (array_slice($nodes, 0, 100) as $node) : if (!is_array($node)) { continue; } ?>
                        <li><strong><?php echo esc_html((string) ($node['kind'] ?? 'node')); ?>:</strong> <?php echo esc_html((string) ($node['label'] ?? $node['id'] ?? '')); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php if ($async_corpus) : ?>
            <script type="application/json" class="sc-kl__config"><?php echo wp_json_encode($async_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
            <?php endif; ?>
            <script type="application/json" class="sc-kl__data"><?php echo $safe_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function empty_state(string $scope = 'corpus'): string {
        if ('backend' === $scope) {
            $message = __('The Library analysis backend is not available.', 'sustainable-catalyst-library');
        } elseif ('publication' === $scope) {
            $message = __('No eligible published Library record was found for this publication.', 'sustainable-catalyst-library');
        } else {
            $message = __('No eligible published publications are currently indexed for this Research Library corpus.', 'sustainable-catalyst-library');
        }
        return '<div class="sc-kl-empty">' . esc_html($message) . '</div>';
    }
}
