<?php
// SC-LIBRARY-FIX-09 (issue #9): equation detection in SC_Library_Indexer::resource_flags().
// Usage: php tests/fixture_fix09_equation_detection.php [path/to/class-sc-library-indexer.php]
// Runs the real private resource_flags() with minimal WordPress stubs; any PHP warning fails the run.
define('ABSPATH', __DIR__ . '/');
function get_post_meta($id, $key, $single = false) { return ''; }
function esc_url_raw($url) { return (string) $url; }
function get_post_type($id = null) { return 'post'; }
function wp_json_encode($v) { return json_encode($v); }

$file = $argv[1] ?? __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-indexer.php';
$warnings = [];
set_error_handler(function ($no, $msg, $f, $line) use (&$warnings) { $warnings[] = "$msg ($f:$line)"; return true; });
require_once $file;

$indexer = (new ReflectionClass('SC_Library_Indexer'))->newInstanceWithoutConstructor();
$flags = new ReflectionMethod('SC_Library_Indexer', 'resource_flags');
$flags->setAccessible(true);

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
for ($round = 1; $round <= 50; $round++) {          // repeated indexing must stay warning-free and stable
    foreach ($cases as $name => [$content, $expected]) {
        $result = $flags->invoke($indexer, 409, $content);
        $recorded = json_decode(wp_json_encode($result), true);   // what index_post() stores in resource_flags
        $returned = array_map('boolval', $recorded);              // what the REST item exposes as resources
        if ($returned['equations'] !== $expected) { $fail[$name] = 'equations=' . var_export($returned['equations'], true); }
        foreach (['code', 'video', 'dataset', 'workbench', 'decision_studio', 'site_intelligence', 'pdf'] as $other) {
            if (!array_key_exists($other, $returned)) { $fail[$name] = "missing flag $other"; }
        }
    }
}
if (method_exists('SC_Library_Indexer', 'contains_equation_markup') && SC_Library_Indexer::contains_equation_markup('plain text')) {
    $fail['helper'] = 'plain text classified as equation';
}
restore_error_handler();

foreach ($fail as $name => $why) { fwrite(STDERR, "FAIL $name: $why\n"); }
foreach (array_slice(array_unique($warnings), 0, 3) as $w) { fwrite(STDERR, "WARNING $w\n"); }
if ($fail || $warnings) { fwrite(STDERR, sprintf("FAIL - %d case(s), %d warning(s)\n", count($fail), count($warnings))); exit(1); }
echo 'PASS - issue #9 equation detection: ' . count($cases) . " cases x 50 rounds, 0 warnings\n";
