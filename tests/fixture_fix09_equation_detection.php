<?php
// SC-LIBRARY-FIX-09 (issue #9): equation detection in SC_Library_Indexer::index_post() and REST row hydration.
// Usage: php tests/fixture_fix09_equation_detection.php [path/to/class-sc-library-indexer.php]
// Runs the index and REST code with WordPress/database stubs; any PHP warning fails the run.
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

final class SC_Library_Relationships {
    public function get_for_post($id, $public) { return []; }
}
final class FixtureIndexDatabase {
    public $posts = 'wp_posts';
    public $prefix = 'wp_';
    public $rows = [];
    public function get_results($sql, $format) { return []; }
    public function prepare($sql, $id) { return $id; }
    public function get_var($id) { return isset($this->rows[$id]) ? $id : null; }
    public function insert($table, $row, $formats) {
        $this->rows[$row['post_id']] = $row;
        return 1;
    }
    public function update($table, $row, $where, $formats, $where_formats) {
        $this->rows[$where['post_id']] = $row;
        return 1;
    }
}

function get_post($id) { return $GLOBALS['fixturePosts'][$id] ?? null; }
function get_post_types($args, $output) { return ['post']; }
function sanitize_key($value) { return $value; }
function apply_filters($hook, $value) { return $value; }
function wp_get_post_categories($id) { return []; }
function wp_get_post_tags($id, $args) { return []; }
function wp_get_post_terms($id, $taxonomy, $args) { return []; }
function is_wp_error($value) { return false; }
function absint($value) { return abs((int) $value); }
function strip_shortcodes($value) { return $value; }
function wp_strip_all_tags($value, $remove_breaks = false) { return strip_tags($value); }
function wp_list_pluck($values, $key) { return array_column($values, $key); }
function get_option($key, $default) { return $default; }
function has_excerpt($id) { return false; }
function wp_trim_words($value, $words, $more) { return $value; }
function get_the_title($id) { return 'Fixture post ' . $id; }
function get_permalink($id) { return 'https://example.test/' . $id; }
function get_gmt_from_date($date) { return $date; }
function current_time($format, $gmt) { return '2026-01-01 00:00:00'; }
function mysql_to_rfc3339($date) { return '2026-01-01T00:00:00Z'; }
function get_post_type_object($type) { return null; }
function __($text, $domain) { return $text; }
function get_post_meta($id, $key, $single = false) { return ''; }
function esc_url_raw($url) { return (string) $url; }
function get_post_type($id = null) { return 'post'; }
function wp_json_encode($v) { return json_encode($v); }

$file = $argv[1] ?? __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-indexer.php';
$warnings = [];
set_error_handler(function ($no, $msg, $f, $line) use (&$warnings) { $warnings[] = "$msg ($f:$line)"; return true; });
require_once $file;

require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-taxonomies.php';
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-rest.php';

$wpdb = new FixtureIndexDatabase();
$indexer = new SC_Library_Indexer(new SC_Library_Relationships());
$rest = (new ReflectionClass('SC_Library_REST'))->newInstanceWithoutConstructor();
$hydrate = new ReflectionMethod('SC_Library_REST', 'hydrate_row');

$cases = [
    'inline LaTeX'            => ['<p>Let \(x\) denote output and \(Y_t\) income.</p>', true],
    'display LaTeX'           => ["<div class=\"cc-equation\">\\[\r\nMQ_t = \\alpha_1 A_t + \\alpha_2 C_t\r\n\\]</div>", true],
    'dollar-delimited'        => ['<p>The identity $$x^2 + y^2 = r^2$$ holds.</p>', true],
    'latex indicator'         => ['<p>Equations are typeset with LaTeX.</p>', true],
    'mathjax indicator'       => ['<script src="https://cdn.example/mathjax/tex-chtml.js"></script>', true],
    'ordinary text'           => ['<p>Prices rose (sharply) in 2024 [see Table 2]; costs were $5 and $10.</p>', false],
    'ordinary text, no markup'=> ['Climate adaptation depends on institutions, finance and local capacity.', false],
    'escaped path, no TeX'    => ['<p>Saved to C:/data/run-1 (final).</p>', false],
];

$fail = [];
$fixturePosts = [];
$caseIds = array_flip(array_keys($cases));
for ($round = 1; $round <= 50; $round++) {
    foreach ($cases as $name => [$content, $expected]) {
        $id = 409 + $caseIds[$name];
        $fixturePosts[$id] = (object) [
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_content' => $content,
            'post_date' => '2026-01-01 00:00:00',
            'post_modified' => '2026-01-01 00:00:00',
        ];
        if (!$indexer->index_post($id, ['post']) || !isset($wpdb->rows[$id])) {
            $fail[$name] = 'index row missing';
            continue;
        }

        $row = $wpdb->rows[$id];
        $stored = json_decode($row['resource_flags'], true);
        $returned = $hydrate->invoke($rest, $row, false)['resources'];
        if (($stored['equations'] ?? null) !== $expected || ($returned['equations'] ?? null) !== $expected) {
            $fail[$name] = 'stored=' . var_export($stored['equations'] ?? null, true)
                . ', REST=' . var_export($returned['equations'] ?? null, true);
        }
        if ($returned !== $stored) {
            $fail[$name] = 'REST resource flags differ from stored flags';
        }
    }
}
if (count($wpdb->rows) !== count($cases)) {
    $fail['index row count'] = 'repeated indexing changed the number of rows';
}
if (method_exists('SC_Library_Indexer', 'contains_equation_markup') && SC_Library_Indexer::contains_equation_markup('plain text')) {
    $fail['helper'] = 'plain text classified as equation';
}
restore_error_handler();

foreach ($fail as $name => $why) { fwrite(STDERR, "FAIL $name: $why\n"); }
foreach (array_slice(array_unique($warnings), 0, 3) as $w) { fwrite(STDERR, "WARNING $w\n"); }
if ($fail || $warnings) { fwrite(STDERR, sprintf("FAIL - %d case(s), %d warning(s)\n", count($fail), count($warnings))); exit(1); }
echo 'PASS - issue #9 equation detection: ' . count($cases) . " cases x 50 rounds, 0 warnings\n";
