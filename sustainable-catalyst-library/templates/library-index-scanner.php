<?php
if (!defined('ABSPATH')) {
    exit;
}
$status = (string) ($state['status'] ?? 'idle');
$status_label = match ($status) {
    'running' => __('Running', 'sustainable-catalyst-library'),
    'paused' => __('Paused', 'sustainable-catalyst-library'),
    'complete' => __('Complete', 'sustainable-catalyst-library'),
    'complete_with_errors' => __('Complete with errors', 'sustainable-catalyst-library'),
    'incomplete' => __('Incomplete', 'sustainable-catalyst-library'),
    'cancelled' => __('Cancelled', 'sustainable-catalyst-library'),
    default => __('Idle', 'sustainable-catalyst-library'),
};
?>
<div class="wrap sc-library-scanner" data-sc-library-scanner>
    <div class="sc-library-scanner__header">
        <div>
            <p class="sc-library-scanner__eyebrow"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
            <h1><?php esc_html_e('Large-Library Index Scanner', 'sustainable-catalyst-library'); ?></h1>
            <p><?php esc_html_e('Discover and reconcile thousands of WordPress records with cursor-based database batches that are independent of theme queries, Render, PostgreSQL, workspaces, and document production.', 'sustainable-catalyst-library'); ?></p>
        </div>
        <div class="sc-library-scanner__status" data-sc-status-wrap data-status="<?php echo esc_attr($status); ?>">
            <span class="sc-library-scanner__status-dot" aria-hidden="true"></span>
            <span data-sc-status><?php echo esc_html($status_label); ?></span>
        </div>
    </div>

    <?php if (isset($_GET['server_scan'])) : ?>
        <div class="notice notice-info inline sc-library-scanner__notice">
            <p><strong><?php esc_html_e('Server-side reconciliation ran.', 'sustainable-catalyst-library'); ?></strong>
            <?php echo esc_html(sprintf(__('Processed %1$d of %2$d records in this pass. Continue the server scan if the status is still running.', 'sustainable-catalyst-library'), absint($_GET['processed'] ?? 0), absint($_GET['total'] ?? 0))); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($diagnostics['unconfigured_recommended'])) : ?>
        <div class="notice notice-warning inline sc-library-scanner__notice" data-sc-unconfigured-notice>
            <p><strong><?php esc_html_e('Published content exists in recommended post types that are not currently configured for the Library.', 'sustainable-catalyst-library'); ?></strong></p>
            <p><?php esc_html_e('Use “Select recommended types,” keep “Save selected types” enabled, and run a Complete safe rebuild.', 'sustainable-catalyst-library'); ?></p>
        </div>
    <?php endif; ?>

    <div class="sc-library-scanner__metrics" data-sc-diagnostic-metrics>
        <?php
        $cards = [
            ['key' => 'standard-posts', 'label' => __('Published Posts', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['standard_posts_published'] ?? 0)],
            ['key' => 'discovered-published', 'label' => __('All editorial records', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['discovered_published'] ?? 0)],
            ['key' => 'selected-published', 'label' => __('Selected scan scope', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['selected_published'] ?? 0)],
            ['key' => 'eligible-records', 'label' => __('Selected eligible', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['eligible_records'] ?? 0)],
            ['key' => 'indexed-records', 'label' => __('Indexed globally', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['indexed_records'] ?? 0)],
            ['key' => 'missing-records', 'label' => __('Missing in selected scope', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['missing_records'] ?? 0)],
            ['key' => 'excluded-records', 'label' => __('Selected exclusions', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['excluded_records'] ?? 0)],
            ['key' => 'failed-records', 'label' => __('Failed in last scan', 'sustainable-catalyst-library'), 'value' => (int) ($diagnostics['failed_records'] ?? 0)],
        ];
        foreach ($cards as $card) :
        ?>
            <div class="sc-library-scanner__metric">
                <span><?php echo esc_html($card['label']); ?></span>
                <strong data-sc-metric="<?php echo esc_attr($card['key']); ?>"><?php echo esc_html((string) $card['value']); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sc-library-scanner__grid">
        <section class="sc-library-scanner__panel sc-library-scanner__panel--primary">
            <div class="sc-library-scanner__panel-head">
                <div>
                    <h2><?php esc_html_e('Discover and reconcile the Library', 'sustainable-catalyst-library'); ?></h2>
                    <p><?php esc_html_e('The scanner queries records by ascending WordPress ID and stores only the last cursor, counters, and audit outcomes. It never stores a 2,000-record queue in a WordPress option.', 'sustainable-catalyst-library'); ?></p>
                </div>
            </div>

            <div class="sc-library-scanner__controls">
                <fieldset>
                    <div class="sc-library-scanner__legend-row">
                        <legend><?php esc_html_e('Discovered editorial post types', 'sustainable-catalyst-library'); ?></legend>
                        <div class="sc-library-scanner__selection-buttons">
                            <button type="button" class="button button-small" data-sc-select-recommended><?php esc_html_e('Select recommended types', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" class="button button-small" data-sc-select-all><?php esc_html_e('Select all discovered', 'sustainable-catalyst-library'); ?></button>
                            <button type="button" class="button button-small" data-sc-clear-types><?php esc_html_e('Clear', 'sustainable-catalyst-library'); ?></button>
                        </div>
                    </div>
                    <div class="sc-library-scanner__types" data-sc-type-list>
                        <?php foreach ($post_types as $post_type) : ?>
                            <label data-sc-type-card data-recommended="<?php echo !empty($post_type['recommended']) ? '1' : '0'; ?>">
                                <input type="checkbox" value="<?php echo esc_attr($post_type['name']); ?>" data-sc-post-type <?php checked(!empty($post_type['default_selected'])); ?>>
                                <span>
                                    <strong><?php echo esc_html($post_type['label']); ?></strong>
                                    <small><code><?php echo esc_html($post_type['name']); ?></code> · <?php echo esc_html(sprintf(_n('%d published record', '%d published records', (int) $post_type['published'], 'sustainable-catalyst-library'), (int) $post_type['published'])); ?></small>
                                    <?php if (!empty($post_type['recommended'])) : ?><em><?php esc_html_e('Recommended', 'sustainable-catalyst-library'); ?></em><?php endif; ?>
                                    <?php if (!empty($post_type['database_only'])) : ?><em><?php esc_html_e('Stored in database; type not registered on this request', 'sustainable-catalyst-library'); ?></em><?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="sc-library-scanner__persist"><input type="checkbox" data-sc-persist-types checked> <?php esc_html_e('Save selected types as the Library index configuration', 'sustainable-catalyst-library'); ?></label>
                </fieldset>

                <div class="sc-library-scanner__control-row">
                    <label>
                        <span><?php esc_html_e('Batch size', 'sustainable-catalyst-library'); ?></span>
                        <select data-sc-batch-size>
                            <?php foreach ([25, 50, 100, 150, 250, 500] as $size) : ?>
                                <option value="<?php echo esc_attr((string) $size); ?>" <?php selected($size, 50); ?>><?php echo esc_html((string) $size); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Scan mode', 'sustainable-catalyst-library'); ?></span>
                        <select data-sc-scan-mode>
                            <option value="full"><?php esc_html_e('Complete safe rebuild and reconciliation', 'sustainable-catalyst-library'); ?></option>
                            <option value="repair"><?php esc_html_e('Missing and outdated records', 'sustainable-catalyst-library'); ?></option>
                            <option value="missing"><?php esc_html_e('Missing records only', 'sustainable-catalyst-library'); ?></option>
                            <option value="outdated"><?php esc_html_e('Outdated records only', 'sustainable-catalyst-library'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="sc-library-scanner__buttons">
                    <button type="button" class="button button-primary" data-sc-start><?php esc_html_e('Start cursor scan', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-sc-resume><?php esc_html_e('Resume', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-sc-pause><?php esc_html_e('Pause', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button button-link-delete" data-sc-cancel><?php esc_html_e('Cancel', 'sustainable-catalyst-library'); ?></button>
                    <button type="button" class="button" data-sc-reset><?php esc_html_e('Reset scanner state', 'sustainable-catalyst-library'); ?></button>
                </div>

                <div class="sc-library-scanner__server-fallback">
                    <h3><?php esc_html_e('Server-side fallback', 'sustainable-catalyst-library'); ?></h3>
                    <p><?php esc_html_e('Use this when the browser/REST loop stalls. Each request processes bounded cursor batches for up to twelve seconds and preserves progress.', 'sustainable-catalyst-library'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sc_library_server_reconcile">
                        <input type="hidden" name="restart" value="1">
                        <?php wp_nonce_field('sc_library_server_reconcile'); ?>
                        <?php submit_button(__('Start complete server reconciliation', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sc_library_server_reconcile">
                        <?php wp_nonce_field('sc_library_server_reconcile'); ?>
                        <?php submit_button(__('Continue saved server reconciliation', 'sustainable-catalyst-library'), 'secondary', 'submit', false); ?>
                    </form>
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
                    <span><?php esc_html_e('Excluded', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-excluded><?php echo esc_html((string) ($state['excluded'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Failed', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-failed><?php echo esc_html((string) ($state['failed'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Accounted', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-accounted><?php echo esc_html((string) ($state['accounted'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Removed', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-purged><?php echo esc_html((string) ($state['purged'] ?? 0)); ?></strong></span>
                    <span><?php esc_html_e('Last cursor', 'sustainable-catalyst-library'); ?> <strong data-sc-scan-cursor><?php echo esc_html((string) ($state['cursor_id'] ?? 0)); ?></strong></span>
                </div>
                <p class="description" data-sc-accounting>
                    <?php echo !empty($state['accounting_ok']) ? esc_html__('Accounting is reconciled.', 'sustainable-catalyst-library') : esc_html__('Accounting does not reconcile.', 'sustainable-catalyst-library'); ?>
                </p>
                <p class="description" data-sc-scan-message>
                    <?php
                    if ($status === 'running') esc_html_e('An incomplete cursor scan is saved and can be resumed.', 'sustainable-catalyst-library');
                    elseif ($status === 'complete') esc_html_e('The last scan completed and reconciled successfully.', 'sustainable-catalyst-library');
                    elseif ($status === 'complete_with_errors') esc_html_e('The last scan completed with failed records.', 'sustainable-catalyst-library');
                    elseif ($status === 'incomplete') esc_html_e('The last scan did not reconcile all processed records.', 'sustainable-catalyst-library');
                    else esc_html_e('No scan is currently running.', 'sustainable-catalyst-library');
                    ?>
                </p>
            </div>
        </section>

        <section class="sc-library-scanner__panel">
            <h2><?php esc_html_e('Targeted repair', 'sustainable-catalyst-library'); ?></h2>
            <p><?php esc_html_e('Repair one WordPress record or a specific index subsystem without running a complete rebuild.', 'sustainable-catalyst-library'); ?></p>
            <div class="sc-library-scanner__record-repair">
                <label for="sc-library-record-repair"><?php esc_html_e('Post ID or canonical URL', 'sustainable-catalyst-library'); ?></label>
                <div>
                    <input id="sc-library-record-repair" type="text" class="regular-text" data-sc-record placeholder="1842 or https://sustainablecatalyst.com/example/">
                    <button type="button" class="button" data-sc-reindex-record><?php esc_html_e('Reindex record', 'sustainable-catalyst-library'); ?></button>
                </div>
                <p class="description" data-sc-record-result></p>
            </div>
            <div class="sc-library-scanner__repair-buttons">
                <button type="button" class="button" data-sc-repair="schema"><?php esc_html_e('Repair index and audit schema', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="stale"><?php esc_html_e('Remove stale records', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="relationships"><?php esc_html_e('Repair relationships', 'sustainable-catalyst-library'); ?></button>
                <button type="button" class="button" data-sc-repair="identifiers"><?php esc_html_e('Repair identifiers and outdated rows', 'sustainable-catalyst-library'); ?></button>
            </div>
        </section>
    </div>

    <section class="sc-library-scanner__panel">
        <div class="sc-library-scanner__panel-head">
            <div>
                <h2><?php esc_html_e('Database discovery and index diagnostics', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('Published counts come directly from the WordPress posts table and cannot be reduced by front-end query filters.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <button type="button" class="button" data-sc-refresh><?php esc_html_e('Refresh diagnostics', 'sustainable-catalyst-library'); ?></button>
        </div>

        <div class="sc-library-scanner__health" data-sc-health>
            <span class="<?php echo !empty($diagnostics['table_exists']) ? 'is-good' : 'is-bad'; ?>"><?php esc_html_e('Index table', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['table_exists']) ? esc_html__('Available', 'sustainable-catalyst-library') : esc_html__('Missing', 'sustainable-catalyst-library'); ?></span>
            <span class="<?php echo !empty($diagnostics['scan_items_table_exists']) ? 'is-good' : 'is-bad'; ?>"><?php esc_html_e('Scan audit table', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['scan_items_table_exists']) ? esc_html__('Available', 'sustainable-catalyst-library') : esc_html__('Missing', 'sustainable-catalyst-library'); ?></span>
            <span class="<?php echo !empty($diagnostics['fulltext_index']) ? 'is-good' : 'is-warn'; ?>"><?php esc_html_e('Full-text index', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['fulltext_index']) ? esc_html__('Available', 'sustainable-catalyst-library') : esc_html__('Needs review', 'sustainable-catalyst-library'); ?></span>
            <span class="<?php echo !empty($diagnostics['daily_reconcile_scheduled']) ? 'is-good' : 'is-warn'; ?>"><?php esc_html_e('Daily reconciliation', 'sustainable-catalyst-library'); ?>: <?php echo !empty($diagnostics['daily_reconcile_scheduled']) ? esc_html__('Scheduled', 'sustainable-catalyst-library') : esc_html__('Not scheduled', 'sustainable-catalyst-library'); ?></span>
        </div>

        <div class="sc-library-scanner__table-wrap">
            <table class="widefat striped" data-sc-post-type-table>
                <thead><tr>
                    <th><?php esc_html_e('Post type', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Configured', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Published', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Eligible', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Excluded', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Indexed', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Missing', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Outdated', 'sustainable-catalyst-library'); ?></th>
                    <th><?php esc_html_e('Last indexed', 'sustainable-catalyst-library'); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach (($diagnostics['post_types'] ?? []) as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['label']); ?></strong><br><code><?php echo esc_html($row['post_type']); ?></code><?php if (!empty($row['recommended'])) : ?><br><small><?php esc_html_e('Recommended', 'sustainable-catalyst-library'); ?></small><?php endif; ?></td>
                            <td><?php echo !empty($row['configured']) ? esc_html__('Yes', 'sustainable-catalyst-library') : esc_html__('No', 'sustainable-catalyst-library'); ?></td>
                            <td><?php echo esc_html((string) $row['discovered']); ?></td>
                            <td><?php echo esc_html((string) $row['eligible']); ?></td>
                            <td><?php echo esc_html((string) $row['excluded']); ?></td>
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
            foreach ($issue_groups as $title => $items) : ?>
                <details <?php echo $items ? 'open' : ''; ?>>
                    <summary><?php echo esc_html($title); ?> <span><?php echo esc_html((string) count($items)); ?></span></summary>
                    <?php if (!$items) : ?><p><?php esc_html_e('No sampled issues detected.', 'sustainable-catalyst-library'); ?></p>
                    <?php else : ?><ul><?php foreach ($items as $item) : ?><li><code>#<?php echo esc_html((string) $item['post_id']); ?></code> <?php if (!empty($item['edit_url'])) : ?><a href="<?php echo esc_url($item['edit_url']); ?>"><?php echo esc_html($item['title']); ?></a><?php else : ?><?php echo esc_html($item['title']); ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sc-library-scanner__panel">
        <div class="sc-library-scanner__panel-head">
            <div>
                <h2><?php esc_html_e('Scan history and full audit report', 'sustainable-catalyst-library'); ?></h2>
                <p><?php esc_html_e('The JSON report contains every processed post ID, outcome, exclusion reason, and failure reason for the current scan.', 'sustainable-catalyst-library'); ?></p>
            </div>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=sc_library_download_scan_log'), 'sc_library_download_scan_log')); ?>"><?php esc_html_e('Download full scan report', 'sustainable-catalyst-library'); ?></a>
        </div>
        <div class="sc-library-scanner__log" data-sc-log>
            <?php if (!$logs) : ?><p><?php esc_html_e('No scanner events have been recorded yet.', 'sustainable-catalyst-library'); ?></p>
            <?php else : foreach (array_slice($logs, 0, 8) as $log) : ?>
                <article><strong><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($log['event'] ?? 'event')))); ?></strong><time><?php echo esc_html((string) ($log['created_at'] ?? '')); ?></time><code><?php echo esc_html(wp_json_encode($log['context'] ?? [], JSON_UNESCAPED_SLASHES)); ?></code></article>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <noscript><div class="notice notice-warning inline"><p><?php esc_html_e('JavaScript is required for resumable cursor scanning. The synchronous rebuild button remains available on the main SC Library settings page.', 'sustainable-catalyst-library'); ?></p></div></noscript>
</div>
