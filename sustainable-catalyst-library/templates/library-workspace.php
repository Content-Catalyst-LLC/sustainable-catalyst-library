<?php
if (!defined('ABSPATH')) {
    exit;
}
$workspace_title = $workspace_title ?? __('Research Notebook', 'sustainable-catalyst-library');
$workspace_intro = $workspace_intro ?? __('Combine publications, notes, sources, Technical Translation Matrices, Whiteboards, and Chalkboards in a portable research workspace.', 'sustainable-catalyst-library');
$workspace_standalone = $workspace_standalone ?? false;
$workspace_open = $workspace_open ?? false;
$workspace_initial_tab = $workspace_initial_tab ?? 'overview';
$matrix_enabled = SC_Library_Notebook::matrix_enabled();
$boards_enabled = class_exists('SC_Library_Boards') && SC_Library_Boards::enabled();
$integrations_enabled = class_exists('SC_Library_Integrations') && SC_Library_Integrations::enabled();
$annotations_enabled = class_exists('SC_Library_Annotations') && SC_Library_Annotations::enabled();
$books_enabled = class_exists('SC_Library_Books') && SC_Library_Books::enabled();
?>
<section class="sc-library-workspace-shell<?php echo $workspace_standalone ? ' sc-library-workspace-shell--standalone' : ''; ?>" data-sc-library-workspace-root data-workspace-standalone="<?php echo $workspace_standalone ? '1' : '0'; ?>" data-workspace-initial-tab="<?php echo esc_attr($workspace_initial_tab); ?>">
    <div class="sc-library-workspace-launcher">
        <div>
            <p class="sc-library__eyebrow"><?php esc_html_e('Personal research layer', 'sustainable-catalyst-library'); ?></p>
            <h3><?php echo esc_html($workspace_title); ?></h3>
            <p><?php echo esc_html($workspace_intro); ?></p>
        </div>
        <div class="sc-library-workspace-launcher__actions">
            <span class="sc-library-workspace-count" data-workspace-count>0 items</span>
            <button type="button" class="sc-library-workspace-launcher__button" data-workspace-open="overview"><?php esc_html_e('Open Notebook', 'sustainable-catalyst-library'); ?></button>
            <button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="note"><?php esc_html_e('New note', 'sustainable-catalyst-library'); ?></button>
            <?php if ($matrix_enabled) : ?><button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="matrix"><?php esc_html_e('New matrix', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
            <?php if ($boards_enabled) : ?><button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="whiteboard"><?php esc_html_e('New Whiteboard', 'sustainable-catalyst-library'); ?></button><button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="chalkboard"><?php esc_html_e('New Chalkboard', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
            <?php if ($annotations_enabled) : ?><button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="annotation"><?php esc_html_e('New annotation', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
            <?php if ($books_enabled) : ?><button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="book"><?php esc_html_e('New book', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
            <button type="button" class="sc-library-workspace-launcher__secondary" data-workspace-quick="source"><?php esc_html_e('Add source', 'sustainable-catalyst-library'); ?></button>
        </div>
    </div>

    <div class="sc-library-workspace" data-library-workspace <?php echo $workspace_open ? '' : 'hidden'; ?>>
        <?php if (!$workspace_standalone) : ?><button type="button" class="sc-library-workspace__overlay" data-workspace-close aria-label="<?php esc_attr_e('Close Research Notebook', 'sustainable-catalyst-library'); ?>"></button><?php endif; ?>
        <aside class="sc-library-workspace__panel" role="dialog" aria-modal="<?php echo $workspace_standalone ? 'false' : 'true'; ?>" aria-labelledby="sc-library-workspace-title">
            <header class="sc-library-workspace__header">
                <div><p class="sc-library__eyebrow"><?php esc_html_e('Local-first workspace', 'sustainable-catalyst-library'); ?></p><h2 id="sc-library-workspace-title"><?php echo esc_html($workspace_title); ?></h2></div>
                <?php if (!$workspace_standalone) : ?><button type="button" class="sc-library-workspace__close" data-workspace-close aria-label="<?php esc_attr_e('Close Research Notebook', 'sustainable-catalyst-library'); ?>">×</button><?php endif; ?>
            </header>
            <p class="sc-library-workspace__privacy"><?php esc_html_e('This v1.9 workspace is stored only in this browser unless you export it. It does not change the original publication. Matrices, boards, annotations, and book projects retain source links, handwriting, layers, accessibility transcriptions, edition manifests, and export metadata.', 'sustainable-catalyst-library'); ?></p>
            <nav class="sc-library-workspace__tabs" aria-label="<?php esc_attr_e('Research Notebook sections', 'sustainable-catalyst-library'); ?>">
                <button type="button" data-workspace-tab="overview"><?php esc_html_e('Overview', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-workspace-tab="collections"><?php esc_html_e('Collections', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-workspace-tab="notes"><?php esc_html_e('Notes', 'sustainable-catalyst-library'); ?></button>
                <button type="button" data-workspace-tab="sources"><?php esc_html_e('Sources', 'sustainable-catalyst-library'); ?></button>
                <?php if ($matrix_enabled) : ?><button type="button" data-workspace-tab="matrices"><?php esc_html_e('Translation Matrix', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
                <?php if ($boards_enabled) : ?><button type="button" data-workspace-tab="boards"><?php esc_html_e('Boards', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
                <?php if ($annotations_enabled) : ?><button type="button" data-workspace-tab="annotations"><?php esc_html_e('Annotations', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
                <?php if ($books_enabled) : ?><button type="button" data-workspace-tab="books"><?php esc_html_e('Books', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
                <?php if ($integrations_enabled) : ?><button type="button" data-workspace-tab="integrations"><?php esc_html_e('Connected Tools', 'sustainable-catalyst-library'); ?></button><?php endif; ?>
                <button type="button" data-workspace-tab="portability"><?php esc_html_e('Import / Export', 'sustainable-catalyst-library'); ?></button>
            </nav>
            <div class="sc-library-workspace__notice" data-workspace-notice hidden aria-live="polite"></div>
            <div class="sc-library-workspace__content" data-workspace-content></div>
        </aside>
    </div>
</section>
