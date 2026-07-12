<?php
if (!defined('ABSPATH')) exit;
$portability_title = $portability_title ?? __('Portable Research Data', 'sustainable-catalyst-library');
$portability_intro = $portability_intro ?? '';
$portability_show_server_links = $portability_show_server_links ?? true;
?>
<section class="sc-library-portability" data-sc-library-portability>
    <header class="sc-library-portability__header">
        <p class="sc-library__eyebrow"><?php esc_html_e('Open and portable knowledge', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($portability_title); ?></h2>
        <?php if ($portability_intro) : ?><p><?php echo esc_html($portability_intro); ?></p><?php endif; ?>
    </header>
    <div class="sc-library-portability__notice" data-portability-notice hidden aria-live="polite"></div>
    <div class="sc-library-portability__grid">
        <article>
            <h3><?php esc_html_e('PostgreSQL workspace export', 'sustainable-catalyst-library'); ?></h3>
            <p><?php esc_html_e('Create SQL for this browser’s collections, saved records, notes, sources, Translation Matrices, boards, annotations, custom books, and connected-tool handoffs.', 'sustainable-catalyst-library'); ?></p>
            <button type="button" data-portability-export="postgresql"><?php esc_html_e('Download workspace SQL', 'sustainable-catalyst-library'); ?></button>
        </article>
        <article>
            <h3><?php esc_html_e('JSONL workspace export', 'sustainable-catalyst-library'); ?></h3>
            <p><?php esc_html_e('Create line-delimited JSON records for data engineering, analytics, migration, and inspection.', 'sustainable-catalyst-library'); ?></p>
            <button type="button" data-portability-export="jsonl"><?php esc_html_e('Download workspace JSONL', 'sustainable-catalyst-library'); ?></button>
        </article>
        <article>
            <h3><?php esc_html_e('Versioned workspace manifest', 'sustainable-catalyst-library'); ?></h3>
            <p><?php esc_html_e('Download the complete portable JSON workspace with its current schema and object counts.', 'sustainable-catalyst-library'); ?></p>
            <button type="button" data-portability-export="manifest"><?php esc_html_e('Download workspace JSON', 'sustainable-catalyst-library'); ?></button>
        </article>
        <article>
            <h3><?php esc_html_e('PostgreSQL schema', 'sustainable-catalyst-library'); ?></h3>
            <p><?php esc_html_e('Download the normalized server and workspace schema without data.', 'sustainable-catalyst-library'); ?></p>
            <button type="button" data-portability-export="schema"><?php esc_html_e('Download schema.sql', 'sustainable-catalyst-library'); ?></button>
        </article>
    </div>
    <?php if ($portability_show_server_links) : ?>
        <aside class="sc-library-portability__boundary">
            <strong><?php esc_html_e('Server and browser boundary', 'sustainable-catalyst-library'); ?></strong>
            <p><?php esc_html_e('Published records, documentation, plans, taxonomies, relationships, and resources are exported from WordPress administration. Private Notebook objects remain in this browser and are exported with the tools above.', 'sustainable-catalyst-library'); ?></p>
            <?php if (current_user_can('manage_options')) : ?><a href="<?php echo esc_url(admin_url('admin.php?page=sc-library-portability')); ?>"><?php esc_html_e('Open administrator export studio →', 'sustainable-catalyst-library'); ?></a><?php endif; ?>
        </aside>
    <?php endif; ?>
</section>
