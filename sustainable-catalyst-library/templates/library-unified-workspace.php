<?php if (!defined('ABSPATH')) { exit; } ?>
<section class="sc-library-unified-workspace sc-library-unified-workspace--<?php echo esc_attr($workspace_view); ?>">
    <header><p><?php esc_html_e('Sustainable Catalyst Research Layer', 'sustainable-catalyst-library'); ?></p><h2><?php echo esc_html($workspace_title); ?></h2><span><?php esc_html_e('A user-controlled environment for collecting, translating, mapping, analyzing, producing, and preserving research.', 'sustainable-catalyst-library'); ?></span></header>
    <nav aria-label="<?php esc_attr_e('Unified workspace views', 'sustainable-catalyst-library'); ?>">
        <?php foreach (['overview' => __('Overview', 'sustainable-catalyst-library'), 'notebook' => __('Notebook', 'sustainable-catalyst-library'), 'librarian' => __('Research Librarian', 'sustainable-catalyst-library'), 'graph' => __('Graph', 'sustainable-catalyst-library'), 'books' => __('Books', 'sustainable-catalyst-library'), 'editorial' => __('Editorial', 'sustainable-catalyst-library'), 'portability' => __('Portability', 'sustainable-catalyst-library')] as $view => $label) : ?>
            <a<?php echo $workspace_view === $view ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url(add_query_arg('workspace_view', $view)); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sc-library-unified-workspace__content">
        <?php if ($workspace_view === 'notebook') : ?>
            <?php echo do_shortcode('[sc_library_notebook]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($workspace_view === 'librarian') : ?>
            <?php echo do_shortcode('[sc_research_librarian_orchestrator]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($workspace_view === 'graph') : ?>
            <?php echo do_shortcode('[sc_library_knowledge_graph]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($workspace_view === 'books') : ?>
            <?php echo do_shortcode('[sc_library_book_builder]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($workspace_view === 'editorial') : ?>
            <?php echo do_shortcode('[sc_library_editorial_workflow]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($workspace_view === 'portability') : ?>
            <?php echo do_shortcode('[sc_library_portability]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <div class="sc-library-unified-workspace__cards">
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'notebook')); ?>"><strong><?php esc_html_e('Collect and annotate', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Save records, notes, sources, matrices, boards, and annotations.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'librarian')); ?>"><strong><?php esc_html_e('Ask and route', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Use site-scoped retrieval and confirmed actions.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'graph')); ?>"><strong><?php esc_html_e('Connect evidence', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Explore provenance-aware relationships and dependencies.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'books')); ?>"><strong><?php esc_html_e('Produce documents', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Build books, frozen editions, and source-aware reports.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'editorial')); ?>"><strong><?php esc_html_e('Review collaboratively', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Manage comments, suggestions, approvals, and attribution.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url(add_query_arg('workspace_view', 'portability')); ?>"><strong><?php esc_html_e('Export and preserve', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Create portable research-data and PostgreSQL-ready exports.', 'sustainable-catalyst-library'); ?></span></a>
            </div>
            <?php if (!is_user_logged_in()) : ?><p class="sc-library-unified-workspace__notice"><?php esc_html_e('Browser-local research tools remain available without an account. Sign in to save persistent cross-device revisions and collaborate.', 'sustainable-catalyst-library'); ?></p><?php endif; ?>
        <?php endif; ?>
    </div>
</section>
