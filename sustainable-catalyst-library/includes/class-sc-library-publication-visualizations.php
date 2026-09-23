<?php
if (!defined('ABSPATH')) { exit; }

/**
 * v5.16.0 — Research Library publication visualization delivery.
 *
 * Renders only reviewed/public visualization specifications returned by the
 * Library Python backend. It does not generate claims, infer graph edges, or
 * execute Platform Core reasoning in WordPress.
 */
final class SC_Library_Publication_Visualizations {
    public const VERSION = '5.16.0';
    public const SHORTCODE = 'sc_library_publication_visualizations';
    private static bool $style_printed = false;

    public function register_hooks(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts = []): string {
        $atts = shortcode_atts([
            'record_id' => '',
            'limit' => 8,
            'empty' => 'hide',
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

        $payload = SC_Library_Python_Backend::publication_visualizations($record_id, min(20, max(1, (int) $atts['limit'])));
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        if (!$items) {
            return 'hide' === sanitize_key((string) $atts['empty']) ? '' : $this->empty_state();
        }

        $out = $this->styles();
        $out .= '<section class="sc-pubviz" data-record-id="' . esc_attr($record_id) . '">';
        $out .= '<div class="sc-pubviz__header"><p class="sc-pubviz__eyebrow">' . esc_html__('Knowledge Visualizations', 'sustainable-catalyst-library') . '</p>';
        $out .= '<h2>' . esc_html__('Explore this publication', 'sustainable-catalyst-library') . '</h2>';
        $out .= '<p>' . esc_html__('Reviewed visual maps derived from declared citations and source-anchored research candidates.', 'sustainable-catalyst-library') . '</p></div>';
        $out .= '<div class="sc-pubviz__grid">';
        foreach ($items as $item) {
            if (!is_array($item) || 'published' !== (string) ($item['review_state'] ?? '')) { continue; }
            $out .= $this->render_item($item);
        }
        $out .= '</div></section>';
        return $out;
    }

    private function render_item(array $item): string {
        $spec = isset($item['specification']) && is_array($item['specification']) ? $item['specification'] : [];
        $nodes = isset($spec['nodes']) && is_array($spec['nodes']) ? array_values($spec['nodes']) : [];
        $edges = isset($spec['edges']) && is_array($spec['edges']) ? array_values($spec['edges']) : [];
        $nodes = array_slice($nodes, 0, 40);
        $title = (string) ($item['title'] ?? __('Publication visualization', 'sustainable-catalyst-library'));
        $description = (string) ($item['description'] ?? '');
        $kind = sanitize_key((string) ($item['visualization_kind'] ?? 'visualization'));

        $out = '<article class="sc-pubviz__card sc-pubviz__card--' . esc_attr($kind) . '">';
        $out .= '<div class="sc-pubviz__card-head"><span>' . esc_html(str_replace('-', ' ', ucwords($kind, '-'))) . '</span><h3>' . esc_html($title) . '</h3>';
        if ($description !== '') { $out .= '<p>' . esc_html($description) . '</p>'; }
        $out .= '</div>';
        $out .= $this->render_svg($title, $nodes, $edges);
        $out .= '<details class="sc-pubviz__details"><summary>' . esc_html__('Accessible data view', 'sustainable-catalyst-library') . '</summary><ul>';
        foreach ($nodes as $node) {
            if (!is_array($node)) { continue; }
            $out .= '<li><strong>' . esc_html((string) ($node['kind'] ?? 'node')) . ':</strong> ' . esc_html((string) ($node['label'] ?? $node['id'] ?? '')) . '</li>';
        }
        $out .= '</ul></details></article>';
        return $out;
    }

    private function render_svg(string $title, array $nodes, array $edges): string {
        if (!$nodes) { return '<div class="sc-pubviz__empty">' . esc_html__('No reviewed nodes are available for this view yet.', 'sustainable-catalyst-library') . '</div>'; }
        $width = 720; $height = 420; $cx = 360; $cy = 210; $radius = 150;
        $positions = [];
        $count = count($nodes);
        foreach ($nodes as $i => $node) {
            $id = (string) ($node['id'] ?? ('node-' . $i));
            $is_root = !empty($node['root']) || 0 === $i;
            if ($is_root) { $positions[$id] = [$cx, $cy]; continue; }
            $denom = max(1, $count - 1);
            $angle = (2 * M_PI * max(0, $i - 1)) / $denom;
            $positions[$id] = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
        }
        $svg = '<div class="sc-pubviz__canvas"><svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr($title) . '"><title>' . esc_html($title) . '</title>';
        foreach ($edges as $edge) {
            if (!is_array($edge)) { continue; }
            $source = (string) ($edge['source'] ?? ''); $target = (string) ($edge['target'] ?? '');
            if (!isset($positions[$source], $positions[$target])) { continue; }
            [$x1,$y1] = $positions[$source]; [$x2,$y2] = $positions[$target];
            $svg .= '<line class="sc-pubviz__edge" x1="' . esc_attr((string) round($x1,2)) . '" y1="' . esc_attr((string) round($y1,2)) . '" x2="' . esc_attr((string) round($x2,2)) . '" y2="' . esc_attr((string) round($y2,2)) . '" />';
        }
        foreach ($nodes as $i => $node) {
            if (!is_array($node)) { continue; }
            $id = (string) ($node['id'] ?? ('node-' . $i)); if (!isset($positions[$id])) { continue; }
            [$x,$y] = $positions[$id]; $is_root = !empty($node['root']) || 0 === $i;
            $label = wp_strip_all_tags((string) ($node['label'] ?? $id));
            $short = function_exists('mb_strimwidth') ? mb_strimwidth($label, 0, 30, '…') : substr($label, 0, 30);
            $svg .= '<g class="sc-pubviz__node' . ($is_root ? ' is-root' : '') . '"><circle cx="' . esc_attr((string) round($x,2)) . '" cy="' . esc_attr((string) round($y,2)) . '" r="' . ($is_root ? '18' : '11') . '"><title>' . esc_html($label) . '</title></circle>';
            $svg .= '<text x="' . esc_attr((string) round($x,2)) . '" y="' . esc_attr((string) round($y + ($is_root ? 34 : 25),2)) . '" text-anchor="middle">' . esc_html($short) . '</text></g>';
        }
        $svg .= '</svg></div>';
        return $svg;
    }

    private function empty_state(): string {
        return '<div class="sc-pubviz__empty">' . esc_html__('No reviewed publication visualizations are available yet.', 'sustainable-catalyst-library') . '</div>';
    }

    private function styles(): string {
        if (self::$style_printed) { return ''; }
        self::$style_printed = true;
        return '<style id="sc-library-publication-visualizations-v5160">.sc-pubviz{margin:2rem 0}.sc-pubviz__header{max-width:780px;margin-bottom:1rem}.sc-pubviz__eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.75rem;font-weight:700}.sc-pubviz__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem}.sc-pubviz__card{border:1px solid currentColor;border-radius:14px;padding:1rem;background:var(--sc-pubviz-bg,#fff);color:var(--sc-pubviz-fg,#111)}.sc-pubviz__card-head span{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em}.sc-pubviz__card-head h3{margin:.35rem 0}.sc-pubviz__canvas{overflow:auto}.sc-pubviz svg{width:100%;height:auto;min-height:260px}.sc-pubviz__edge{stroke:currentColor;stroke-opacity:.28;stroke-width:1.3}.sc-pubviz__node circle{fill:currentColor;fill-opacity:.2;stroke:currentColor;stroke-width:1.5}.sc-pubviz__node.is-root circle{fill:currentColor;fill-opacity:.82}.sc-pubviz__node text{font-size:10px;fill:currentColor}.sc-pubviz__details{margin-top:.5rem}.sc-pubviz__details ul{max-height:220px;overflow:auto}.sc-pubviz__empty{padding:1rem;border:1px dashed currentColor;border-radius:10px}</style>';
    }
}
