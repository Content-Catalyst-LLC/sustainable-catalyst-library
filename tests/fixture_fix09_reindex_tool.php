<?php
// SC-LIBRARY-FIX-09: drives tools/fix09-reindex-equations.php against an in-memory index table.
// php tests/fixture_fix09_reindex_tool.php <state.json> <mode> [backup-dir] [--without-fix]
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function wp_json_encode($v) { return json_encode($v); }
$stateFile = $argv[1];
$GLOBALS['S'] = json_decode(file_get_contents($stateFile), true);
function get_post_field($field, $id, $context = 'display') {
    if ($context !== 'raw') {
        throw new LogicException('Correction must use the same raw post content as indexing');
    }
    return $GLOBALS['S']['posts'][(string) $id] ?? '';
}
final class FakeWpdb {
    public $prefix = 'wp_';
    public function get_results($sql, $out) { return array_values($GLOBALS['S']['rows']); }
    public function get_var($sql) { $c = array_count_values(array_column($GLOBALS['S']['rows'], 'post_id')); return count(array_filter($c, fn($n) => $n > 1)); }
    public function update($table, $data, $where, $f = null, $wf = null) {
        $row = &$GLOBALS['S']['rows'][(string) $where['id']];
        if (!$row || $row['resource_flags'] !== $where['resource_flags']) { return 0; }
        $row['resource_flags'] = $data['resource_flags']; return 1;
    }
}
$wpdb = new FakeWpdb();
if (!in_array('--without-fix', $argv, true)) {
    require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-indexer.php';
}
register_shutdown_function(function () use ($stateFile) { file_put_contents($stateFile, json_encode($GLOBALS['S'])); });
$args = array_values(array_filter(array_slice($argv, 2), fn($a) => $a !== '--without-fix'));
require __DIR__ . '/../tools/fix09-reindex-equations.php';
