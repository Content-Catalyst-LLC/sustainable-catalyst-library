<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.28.0 — Research Retrieval Evaluation & Adaptive Ranking console.
 *
 * Feedback remains session/local-browser scoped by default. The console does not
 * persist relevance judgments into source records and does not modify evidence,
 * claims, findings, or Platform Core objects.
 */
final class SC_Library_Retrieval_Evaluation {
    public const VERSION = '5.28.0';
    public const SHORTCODE = 'sc_library_retrieval_evaluation';

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'sc-library-retrieval-evaluation-v5280',
            SC_LIBRARY_URL . 'assets/css/sc-library-retrieval-evaluation-v5280.css',
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'sc-library-retrieval-evaluation-v5280',
            SC_LIBRARY_URL . 'assets/js/sc-library-retrieval-evaluation-v5280.js',
            [],
            self::VERSION,
            true
        );
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'limit' => '10',
            'mode' => 'hybrid',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);
        $limit = min(30, max(3, (int) $atts['limit']));
        $mode = sanitize_key((string) $atts['mode']);
        if (!in_array($mode, ['hybrid', 'lexical', 'semantic'], true)) { $mode = 'hybrid'; }
        $this->enqueue_assets();
        $id = 'sc-retrieval-eval-' . wp_generate_uuid4();
        $search = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/search');
        $evaluate = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/retrieval-evaluation');
        $profile = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/retrieval-adaptive-profile');
        $adaptive = rest_url(SC_Library_Python_Backend::REST_NAMESPACE . '/backend/search/adaptive');
        ob_start();
        ?>
        <section id="<?php echo esc_attr($id); ?>" class="sc-reval"
            data-sc-reval-root
            data-search-endpoint="<?php echo esc_url($search); ?>"
            data-evaluate-endpoint="<?php echo esc_url($evaluate); ?>"
            data-profile-endpoint="<?php echo esc_url($profile); ?>"
            data-adaptive-endpoint="<?php echo esc_url($adaptive); ?>"
            data-limit="<?php echo esc_attr((string) $limit); ?>"
            data-mode="<?php echo esc_attr($mode); ?>">
            <header class="sc-reval__header">
                <p class="sc-reval__eyebrow"><?php esc_html_e('Research Retrieval Evaluation', 'sustainable-catalyst-library'); ?></p>
                <h2><?php esc_html_e('Measure search quality before adapting ranking', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Judge retrieved sources, inspect precision/recall-style metrics and evidence coverage, then build a bounded reranking profile. Feedback never changes evidence truth or deletes results.', 'sustainable-catalyst-library'); ?></p>
            </header>
            <div class="sc-reval__query">
                <label>
                    <span><?php esc_html_e('Research query', 'sustainable-catalyst-library'); ?></span>
                    <input type="search" data-sc-reval-query placeholder="<?php esc_attr_e('e.g. urban heat mitigation health outcomes', 'sustainable-catalyst-library'); ?>">
                </label>
                <button type="button" data-sc-reval-search><?php esc_html_e('Run baseline search', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-sc-reval-adaptive disabled><?php esc_html_e('Run adaptive search', 'sustainable-catalyst-library'); ?></button>
            </div>
            <div class="sc-reval__status" data-sc-reval-status><?php esc_html_e('No benchmark run yet.', 'sustainable-catalyst-library'); ?></div>
            <div class="sc-reval__grid">
                <div>
                    <h3><?php esc_html_e('Ranked results & judgments', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-reval-results class="sc-reval__results"></div>
                </div>
                <aside>
                    <h3><?php esc_html_e('Evaluation', 'sustainable-catalyst-library'); ?></h3>
                    <div data-sc-reval-metrics class="sc-reval__metrics"><p><?php esc_html_e('Judge results, then evaluate.', 'sustainable-catalyst-library'); ?></p></div>
                    <div class="sc-reval__actions">
                        <button type="button" data-sc-reval-evaluate disabled><?php esc_html_e('Evaluate ranking', 'sustainable-catalyst-library'); ?></button>
                        <button type="button" data-sc-reval-profile disabled><?php esc_html_e('Build adaptive profile', 'sustainable-catalyst-library'); ?></button>
                    </div>
                    <div data-sc-reval-profile-state class="sc-reval__profile"></div>
                </aside>
            </div>
            <footer class="sc-reval__guardrail">
                <?php esc_html_e('Guardrail: relevance feedback changes ranking only. It does not validate claims, suppress source records, or modify Platform Core governance.', 'sustainable-catalyst-library'); ?>
            </footer>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
