<?php
if (!defined('ABSPATH')) {
    exit;
}

$status = (string) ($state['status'] ?? 'idle');
$status_label = match ($status) {
    'running' => __('Running', 'sustainable-catalyst-library'),
    'paused' => __('Paused', 'sustainable-catalyst-library'),
    'complete' => __('Complete', 'sustainable-catalyst-library'),
    'cancelled' => __('Cancelled', 'sustainable-catalyst-library'),
    default => __('Idle', 'sustainable-catalyst-library'),
};
?>
<div class="wrap sc-library-scanner" data-sc-library-scanner>
    <div class="sc-library-scanner__header">
        <div>
            <p class="sc-library-scanner__eyebrow"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
            <h1><?php esc_html_e('Index Scanner', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Rebuild, inspect, and repair the WordPress Library index independently of Render, PostgreSQL, workspaces, and document production.', 'sustainable-catalyst-library'); ?></p>
        </div>
        <div class="sc-library-scanner__status" data-sc-status-wrap>
            <span class="sc-library-scanner__status-dot" aria-hidden="true"></span>
            <span data-sc-status><?php echo esc_html($status_label); ?></span>
        </div>
    </div>

    <div class="sc-library-scanner__metrics" data-sc-diagnostic-metrics>
        <?php
        $cards = [
            __('Eligible records', 'sustainable-catalyst-library') => (int) ($diagnostics['eligible_records'] ?? 0),
            __('Indexed records', 'sustainable-catalyst-library') => (int) ($diagnostics['indexed_records'] ?? 0),
            __('Missing records', 'sustainable-catalyst-library') => (int) ($diagnostics['missing_records'] ?? 0),
            __('Outdated records', 'sustainable-catalyst-library') => (int) ($diagnostics['outdated_records'] ?? 0),
            __('Stale records', 'sustainable-catalyst-library') => (int) ($diagnostics['stale_records'] ?? 0),
            __('Relationships', 'sustainable-catalyst-library') => (int) ($diagnostics['relationships'] ?? 0),
        ];
        foreach ($cards as $label => $value) :
        ?>
            <div class="sc-library-scanner__metric">
                <span><?php echo esc_html($label); ?></span>
                <strong data-sc-metric="<?php echo esc_attr(sanitize_key($label)); ?>"><?php echo esc_html((string) $value); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sc-library-scanner__grid">
        <section class="sc-library-scanner__panel sc-library-scanner__panel--primary">
            <div class="sc-library-scanner__panel-head">
                <div>
                    <h2><?php esc_html_e('Scan and rebuild', 'sustainable-catalyst-library'); ?></h2>
                    <p><?php esc_html_e('The scanner works in bounded batches and saves progress after every batch. Closing the browser does not discard an incomplete scan.', 'sustainable-catalyst-library'); ?></p>
                </div>
            </div>

            <div class="sc-library-scanner__controls">
                <fieldset>
                    <legend><?php esc_html_e('Post types', 'sustainable-catalyst-library'); ?></legend>
                    <div class="sc-library-scanner__types">
                        <?php foreach ($post_types as $post_type) : ?>
                            <label>
                                <input type="checkbox" value="<?php echo esc_attr($post_type['name']); ?>" data-sc-post-type checked>
                                <span><strong><?php echo esc_html($post_type['label']); ?></strong><small><?php echo esc_html(sprintf(_n('%d eligible record', '%d eligible records', (int) $post_type['eligible'], 'sustainable-catalyst-library'), (int) $post_type['eligible'])); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="sc-library-scanner__control-row">
                    <label>
                        <span><?php esc_html_e('Batch size', 'sustainable-catalyst-library'); ?></span>
                        <select data-sc-batch-size>
                            <?php foreach ([25, 50, 100, 150, 250] as $size) : ?>
                                <option value="<?php echo esc_attr((string) $size); ?>" <?php selected($size, 50); ?>><?php echo esc_html((string) $size); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Scan mode', 'sustainable-catalyst-library'); ?></span>
                        <select data-sc-scan-mode>
                            <option value="full"><?php esc_html_e('Complete safe rebuild', 'sustainable-catalyst-library'); ?></option>
                            <option value="repair"><?php esc_html_e('Missing and outdated records', 'sustainable-catalyst-library'); ?></option>
                            <option value="missing"><?php esc_html_e('Missing records only', 'sustainable-catalyst-library'); ?></option>
                            <option value="outdated"><?php esc_html_e('Outdated records only', 'sustainable-catalyst-library'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="sc-library-scanner__buttons">
                    <button type="button" class="button button-primary" data-sc-start><?php esc_html_e('Start scan', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-sc-resume><?php esc_html_e('Resume', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-sc-pause><?php esc_html_e('Pause', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button button-link-delete" data-sc-cancel><?php esc_html_e('Cancel', 'sustainable-catalyst-library'); ?></button>
                </div>
            </div>

            <div class="sc-library-scanner__progress" aria-live="polite">
                <div class="sc-library-scanner__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) ($state['progress'] ?? 0)); ?>" data-sc-progressbar>
                    <span style="width:<?php echo esc_attr((string) ($state['progress'] ?? 0)); ?>%" data-sc-progress-fill></span>
                </div>
                <div class="sc-library-scanner__progress-copy">
                    <strong data-sc-progress-label><?php echo esc_html(sprintf('%s%%', (string) ($state['progress'] ?? 0))); ?></strong>
                    <span data-sc-progress-detail><?php echo esc_html(sprintf('%d / %d', (int) ($state['processed'] ?? 0), (int) ($state['total'] ?? 0))); ?></span>
                </div>
                <div class="sc-library-scanner__scan-metrics">
                    <span><?php esc_html_e('Indexed', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-indexed><?php echo esc_html((string) ($state['indexed'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Skipped', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-skipped><?php echo esc_html((string) ($state['skipped'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Failed', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-failed><?php echo esc_html((string) ($state['failed'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Removed', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-purged><?php echo esc_html((string) ($state['purged'] ?? 0)); ?></strong></span>
                </div>
                <p class="description" data-sc-scan-message>
                    <?php
                    if ($status === 'running') {
                        esc_html_e('An incomplete scan is saved and can be resumed.', 'sustainable-catalyst-library');
                    } elseif ($status === 'complete') {
                        esc_html_e('The last scan completed successfully.', 'sustainable-catalyst-library');
                    } else {
                        esc_html_e('No scan is currently running.', 'sustainable-catalyst-library');
                    }
                    ?>
                </p>
            </div>
        </section>

        <section class="sc-library-scanner__panel">
            <h2><?php esc_html_e('Targeted repair', 'sustainable-catalyst-library'); ?></h2>
            <p><?php esc_html_e('Repair one WordPress record or clean a specific index subsystem without running a complete rebuild.', 'sustainable-catalyst-library'); ?></p>

            <div class="sc-library-scanner__record-repair">
                <label for="sc-library-record-repair"><?php esc_html_e('Post ID or canonical URL', 'sustainable-catalyst-library'); ?></label>
                <div>
                    <input id="sc-library-record-repair" type="text" class="regular-text" data-sc-record placeholder="1842 or https://sustainablecatalyst.com/example/">
                    <button type="button" class="button" data-sc-reindex-record><?php esc_html_e('Reindex record', 'sustainable-catalyst-library'); ?></button>
                </div>
                <p class="description" data-sc-record-result></p>
            </div>

            <div class="sc-library-scanner__repair-buttons">
                <button type="button" class="button" data-sc-repair="schema"><?php esc_html_e('Repair index schema', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="stale"><?php esc_html_e('Remove stale records', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="relationships"><?php esc_html_e('Repair relationships', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="identifiers"><?php esc_html_e('Repair identifiers and outdated rows', 'sustainable-catalyst-library'); ?></button>
            </div>
        </section>
    </div>

    <section class="sc-library-scanner__panel">
        <div class="sc-library-scanner__panel-head">
            <div>
                <h2><?php esc_html_e('Index diagnostics', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Compare eligible WordPress content with the current Library index.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <button type="button" class="button" data-sc-refresh><?php esc_html_e('Refresh diagnostics', 'sustainable-catalyst-library'); ?></button>
        </div>

        <div class="sc-library-scanner__health" data-sc-health>
            <span class="<?php echo !empty($diagnostics['table_exists']) ? 'is-good' : 'is-bad'; ?>"><?php esc_html_e('Index table', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['table_exists']) ? esc_html__('Available', 'sustainable-catalyst-library') : esc_html__('Missing', 'sustainable-catalyst-library'); ?></span>
            <span class="<?php echo !empty($diagnostics['fulltext_index']) ? 'is-good' : 'is-warn'; ?>"><?php esc_html_e('Full-text index', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['fulltext_index']) ? esc_html__('Available', 'sustainable-catalyst-library') : esc_html__('Needs review', 'sustainable-catalyst-library'); ?></span>
            <span class="<?php echo !empty($diagnostics['daily_reconcile_scheduled']) ? 'is-good' : 'is-warn'; ?>"><?php esc_html_e('Daily reconciliation', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['daily_reconcile_scheduled']) ? esc_html__('Scheduled', 'sustainable-catalyst-library') : esc_html__('Not scheduled', 'sustainable-catalyst-library'); ?></span>
        </div>

        <div class="sc-library-scanner__table-wrap">
            <table class="widefat striped" data-sc-post-type-table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post type', 'sustainable-catalyst-library'); ?></th>
                        <th><?php esc_html_e('Eligible', 'sustainable-catalyst-library'); ?></th>
                        <th><?php esc_html_e('Indexed', 'sustainable-catalyst-library'); ?></th>
                        <th><?php esc_html_e('Missing', 'sustainable-catalyst-library'); ?></th>
                        <th><?php esc_html_e('Outdated', 'sustainable-catalyst-library'); ?></th>
                        <th><?php esc_html_e('Last indexed', 'sustainable-catalyst-library'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($diagnostics['post_types'] ?? []) as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['label']); ?></strong><br><code><?php echo esc_html($row['post_type']); ?></code></td>
                            <td><?php echo esc_html((string) $row['eligible']); ?></td>
                            <td><?php echo esc_html((string) $row['indexed']); ?></td>
                            <td><?php echo esc_html((string) $row['missing']); ?></td>
                            <td><?php echo esc_html((string) $row['outdated']); ?></td>
                            <td><?php echo esc_html($row['latest_indexed_at'] ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sc-library-scanner__issues" data-sc-issues>
            <?php
            $issue_groups = [
                __('Missing index records', 'sustainable-catalyst-library') => $diagnostics['missing_sample'] ?? [],
                __('Outdated index records', 'sustainable-catalyst-library') => $diagnostics['outdated_sample'] ?? [],
                __('Stale index records', 'sustainable-catalyst-library') => $diagnostics['stale_sample'] ?? [],
                __('Invalid index records', 'sustainable-catalyst-library') => $diagnostics['invalid_sample'] ?? [],
            ];
            foreach ($issue_groups as $title => $items) :
            ?>
                <details <?php echo $items ? 'open' : ''; ?>>
                    <summary><?php echo esc_html($title); ?> <span><?php echo esc_html((string) count($items)); ?></span></summary>
                    <?php if (!$items) : ?>
                        <p><?php esc_html_e('No sampled issues detected.', 'sustainable-catalyst-library'); ?></p>
                    <?php else : ?>
                        <ul>
                            <?php foreach ($items as $item) : ?>
                                <li>
                                    <code>#<?php echo esc_html((string) $item['post_id']); ?></code>
                                    <?php if (!empty($item['edit_url'])) : ?><a href="<?php echo esc_url($item['edit_url']); ?>"><?php echo esc_html($item['title']); ?></a><?php else : ?><?php echo esc_html($item['title']); ?><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sc-library-scanner__panel">
        <div class="sc-library-scanner__panel-head">
            <div>
                <h2><?php esc_html_e('Scan history', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('The latest scanner, repair, and single-record events are retained for troubleshooting.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=sc_library_download_scan_log'), 'sc_library_download_scan_log')); ?>"><?php esc_html_e('Download scan log', 'sustainable-catalyst-library'); ?></a>
        </div>
        <div class="sc-library-scanner__log" data-sc-log>
            <?php if (!$logs) : ?>
                <p><?php esc_html_e('No scanner events have been recorded yet.', 'sustainable-catalyst-library'); ?></p>
            <?php else : ?>
                <?php foreach (array_slice($logs, 0, 8) as $log) : ?>
                    <article>
                        <strong><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($log['event'] ?? 'event')))); ?></strong>
                        <time><?php echo esc_html((string) ($log['created_at'] ?? '')); ?></time>
                        <code><?php echo esc_html(wp_json_encode($log['context'] ?? [], JSON_UNESCAPED_SLASHES)); ?></code>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <noscript>
        <div class="notice notice-warning inline"><p><?php esc_html_e('JavaScript is required for resumable batch scanning. The synchronous rebuild button remains available on the main SC Library settings page.', 'sustainable-catalyst-library'); ?></p></div>
    </noscript>
</div>
