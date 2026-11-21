<?php
/**
 * SC-LIBRARY-FIX-09 (issue #9): correct the stored `equations` resource flag of existing Library index rows.
 *
 *   wp eval-file tools/fix09-reindex-equations.php dry-run
 *   wp eval-file tools/fix09-reindex-equations.php apply    <backup-dir>
 *   wp eval-file tools/fix09-reindex-equations.php verify   [<backup-dir>]
 *   wp eval-file tools/fix09-reindex-equations.php rollback <backup-dir>
 *
 * Incremental and in place: only the `equations` key inside `resource_flags` changes, and only where it differs from
 * what the fixed indexer computes from the post's current content. No row is inserted or deleted; searchable text,
 * excerpts, taxonomy ids, timestamps (`indexed_at`) and every other flag are left exactly as they are. Each update is
 * a compare-and-set on the row's previous `resource_flags`, so a row re-indexed concurrently is skipped, not
 * overwritten. `apply` writes the previous value of every changed row to <backup-dir> first; `rollback` restores those
 * values on rows that still hold the value written by `apply`.
 */
if (!defined('ABSPATH')) { exit(1); }

$mode = $args[0] ?? 'dry-run';
$dir = rtrim((string) ($args[1] ?? ''), '/');
$fail = static function (string $msg): void { fwrite(STDERR, "REFUSED: $msg\n"); exit(2); };
if (!class_exists('SC_Library_Indexer') || !method_exists('SC_Library_Indexer', 'contains_equation_markup')) {
    $fail('the installed Library plugin does not contain the issue #9 fix (SC_Library_Indexer::contains_equation_markup)');
}

global $wpdb;
$table = $wpdb->prefix . 'sc_library_index';
$backup = $dir !== '' ? "$dir/fix09-resource-flags-before.json" : '';

$state = static function () use ($wpdb, $table): array {
    $rows = $wpdb->get_results("SELECT id, post_id, resource_flags FROM {$table} ORDER BY id", ARRAY_A) ?: [];
    $dups = (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT post_id FROM {$table} GROUP BY post_id HAVING COUNT(*) > 1) d");
    $out = ['rows' => count($rows), 'duplicates' => $dups, 'true' => 0, 'mismatch' => [], 'unreadable' => 0, 'all' => []];
    foreach ($rows as $row) {
        $flags = json_decode((string) $row['resource_flags'], true);
        if (!is_array($flags)) { $out['unreadable']++; continue; }
        $expected = SC_Library_Indexer::contains_equation_markup((string) get_post_field('post_content', (int) $row['post_id']));
        $out['true'] += $expected ? 1 : 0;
        $out['all'][(int) $row['id']] = $row;
        if (($flags['equations'] ?? null) !== $expected) {
            $flags['equations'] = $expected;
            $out['mismatch'][(int) $row['id']] = ['old' => (string) $row['resource_flags'], 'new' => wp_json_encode($flags), 'to' => $expected];
        }
    }
    return $out;
};
$summary = static function (string $label, array $s): void {
    $up = count(array_filter($s['mismatch'], static fn($m) => $m['to']));
    printf("%s: rows=%d duplicate_post_ids=%d unreadable=%d expected_equations=%d to_correct=%d (false->true %d, true->false %d)\n",
        $label, $s['rows'], $s['duplicates'], $s['unreadable'], $s['true'], count($s['mismatch']), $up, count($s['mismatch']) - $up);
};

switch ($mode) {
    case 'dry-run':
        $summary('dry-run', $state());
        echo "nothing written\n";
        break;

    case 'apply':
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) { $fail('apply needs an existing writable backup directory'); }
        if (file_exists($backup)) { $fail("$backup already exists; refusing to overwrite an earlier backup"); }
        $before = $state();
        $summary('before', $before);
        if ($before['duplicates'] !== 0) { $fail('duplicate post_id rows present; resolve before correcting flags'); }
        $saved = array_map(static fn($m) => ['old' => $m['old'], 'new' => $m['new']], $before['mismatch']);
        if (file_put_contents($backup, wp_json_encode(['table' => $table, 'rows' => $saved]), LOCK_EX) === false) { $fail('backup not written'); }
        @chmod($backup, 0600);
        echo 'backup=', $backup, ' sha256=', hash_file('sha256', $backup), ' rows=', count($saved), "\n";
        $updated = 0; $skipped = 0;
        foreach (array_chunk($before['mismatch'], 200, true) as $chunk) {
            foreach ($chunk as $id => $m) {
                $n = $wpdb->update($table, ['resource_flags' => $m['new']], ['id' => $id, 'resource_flags' => $m['old']], ['%s'], ['%d', '%s']);
                $n === 1 ? $updated++ : $skipped++;
            }
        }
        $after = $state();
        $summary('after', $after);
        printf("updated=%d skipped_changed_concurrently=%d\n", $updated, $skipped);
        $ok = $after['rows'] === $before['rows'] && $after['duplicates'] === 0 && count($after['mismatch']) === 0;
        echo $ok ? "APPLY OK\n" : "APPLY INCOMPLETE: run verify, then rollback if needed\n";
        exit($ok ? 0 : 1);

    case 'verify':
        $s = $state();
        $summary('verify', $s);
        $problems = [];
        if ($s['duplicates'] !== 0) { $problems[] = 'duplicate post_id rows'; }
        if ($s['mismatch']) { $problems[] = count($s['mismatch']) . ' rows still differ'; }
        if ($backup !== '' && is_readable($backup)) {   // every other flag of a corrected row is exactly as before
            $saved = json_decode((string) file_get_contents($backup), true)['rows'] ?? [];
            foreach ($saved as $id => $m) {
                $now = json_decode((string) ($s['all'][(int) $id]['resource_flags'] ?? ''), true);
                $old = json_decode($m['old'], true);
                if (!is_array($now)) { $problems[] = "row $id missing"; continue; }
                unset($now['equations'], $old['equations']);
                if ($now != $old) { $problems[] = "row $id: other flags changed"; }
            }
            echo 'backup rows checked=', count($saved), "\n";
        }
        foreach (array_slice($problems, 0, 5) as $p) { echo "PROBLEM $p\n"; }
        echo $problems ? "VERIFY FAILED\n" : "VERIFY OK\n";
        exit($problems ? 1 : 0);

    case 'rollback':
        if (!is_readable($backup)) { $fail("no backup at $backup"); }
        $saved = json_decode((string) file_get_contents($backup), true)['rows'] ?? [];
        $restored = 0; $skipped = 0;
        foreach ($saved as $id => $m) {
            $n = $wpdb->update($table, ['resource_flags' => $m['old']], ['id' => (int) $id, 'resource_flags' => $m['new']], ['%s'], ['%d', '%s']);
            $n === 1 ? $restored++ : $skipped++;
        }
        printf("ROLLBACK restored=%d skipped_changed_since=%d\n", $restored, $skipped);
        break;

    default:
        $fail("unknown mode $mode (dry-run|apply|verify|rollback)");
}
