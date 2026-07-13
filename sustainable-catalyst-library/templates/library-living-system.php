<?php if (!defined('ABSPATH')) { exit; } ?>
<section class="sc-library-living-system sc-library-living-system--<?php echo esc_attr($system_mode); ?>" data-sc-library-living-system>
    <header class="sc-library-living-system__hero">
        <p class="sc-library-living-system__eyebrow"><?php esc_html_e('Sustainable Catalyst Library v2.0', 'sustainable-catalyst-library'); ?></p>
        <h1><?php echo esc_html($system_title); ?></h1>
        <p><?php esc_html_e('A unified public and private knowledge environment for discovering, connecting, researching, analyzing, producing, publishing, and preserving Sustainable Catalyst knowledge.', 'sustainable-catalyst-library'); ?></p>
        <div class="sc-library-living-system__hero-actions">
            <a class="sc-library-system-button sc-library-system-button--primary" href="#sc-library-system-discover"><?php esc_html_e('Explore knowledge', 'sustainable-catalyst-library'); ?></a>
            <a class="sc-library-system-button" href="<?php echo esc_url($system_urls['research_librarian']); ?>"><?php esc_html_e('Open Research Librarian', 'sustainable-catalyst-library'); ?></a>
            <a class="sc-library-system-button" href="<?php echo esc_url($system_urls['graph']); ?>"><?php esc_html_e('Open Knowledge Graph', 'sustainable-catalyst-library'); ?></a>
        </div>
    </header>

    <nav class="sc-library-living-system__layers" aria-label="<?php esc_attr_e('Living Knowledge System layers', 'sustainable-catalyst-library'); ?>">
        <a href="#sc-library-system-public"><strong><?php esc_html_e('Public Knowledge', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Discover, read, connect, and verify.', 'sustainable-catalyst-library'); ?></span></a>
        <a href="#sc-library-system-research"><strong><?php esc_html_e('Research Workspace', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Collect, annotate, translate, map, and analyze.', 'sustainable-catalyst-library'); ?></span></a>
        <a href="#sc-library-system-institutional"><strong><?php esc_html_e('Institutional Operations', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Plan, review, publish, preserve, and govern.', 'sustainable-catalyst-library'); ?></span></a>
    </nav>

    <section class="sc-library-living-system__metrics" aria-label="<?php esc_attr_e('System summary', 'sustainable-catalyst-library'); ?>">
        <article><strong><?php echo esc_html(number_format_i18n((int) ($system_manifest['counts']['indexed_records'] ?? 0))); ?></strong><span><?php esc_html_e('Indexed records', 'sustainable-catalyst-library'); ?></span></article>
        <article><strong><?php echo esc_html(number_format_i18n((int) ($system_manifest['counts']['graph_nodes'] ?? 0))); ?></strong><span><?php esc_html_e('Graph entities', 'sustainable-catalyst-library'); ?></span></article>
        <article><strong><?php echo esc_html(number_format_i18n((int) ($system_manifest['counts']['pdf_pages'] ?? 0))); ?></strong><span><?php esc_html_e('Indexed PDF pages', 'sustainable-catalyst-library'); ?></span></article>
        <article><strong><?php echo esc_html(number_format_i18n((int) ($system_manifest['counts']['snapshots'] ?? 0))); ?></strong><span><?php esc_html_e('Preserved editions', 'sustainable-catalyst-library'); ?></span></article>
    </section>

    <section class="sc-library-living-system__journey" aria-labelledby="sc-library-system-journey-title">
        <div class="sc-library-living-system__section-head"><p><?php esc_html_e('Connected workflow', 'sustainable-catalyst-library'); ?></p><h2 id="sc-library-system-journey-title"><?php esc_html_e('From discovery to preservation', 'sustainable-catalyst-library'); ?></h2></div>
        <ol>
            <?php foreach ($system_journeys as $journey) : ?>
                <li><span><?php echo esc_html((string) $journey['label']); ?></span><p><?php echo esc_html((string) $journey['description']); ?></p></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php if (in_array($system_mode, ['complete', 'public'], true)) : ?>
        <section id="sc-library-system-public" class="sc-library-living-system__layer-section">
            <div class="sc-library-living-system__section-head"><p><?php esc_html_e('Public knowledge layer', 'sustainable-catalyst-library'); ?></p><h2><?php esc_html_e('Discover and connect canonical knowledge', 'sustainable-catalyst-library'); ?></h2><span><?php esc_html_e('Search publications, documents, article maps, concepts, relationships, roadmaps, and preserved editions.', 'sustainable-catalyst-library'); ?></span></div>
            <div class="sc-library-living-system__cards">
                <a href="<?php echo esc_url($system_urls['library']); ?>"><strong><?php esc_html_e('Research Library', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Search the complete indexed knowledge base.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['graph']); ?>"><strong><?php esc_html_e('Knowledge Graph', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Follow provenance-aware relationships.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['archive']); ?>"><strong><?php esc_html_e('Institutional Archive', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Review frozen editions and authority history.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['status']); ?>"><strong><?php esc_html_e('Library Status', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('See the public-safe production status.', 'sustainable-catalyst-library'); ?></span></a>
            </div>
        </section>
        <?php if ($show_library) : ?>
            <section id="sc-library-system-discover" class="sc-library-living-system__explorer" aria-label="<?php esc_attr_e('Embedded Library Explorer', 'sustainable-catalyst-library'); ?>">
                <?php echo do_shortcode('[sc_library mode="compact" initial_results="0" show_header="false" show_workspace="false"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($system_mode, ['complete', 'research'], true)) : ?>
        <section id="sc-library-system-research" class="sc-library-living-system__layer-section sc-library-living-system__layer-section--research">
            <div class="sc-library-living-system__section-head"><p><?php esc_html_e('Research workspace layer', 'sustainable-catalyst-library'); ?></p><h2><?php esc_html_e('Turn reading into structured inquiry', 'sustainable-catalyst-library'); ?></h2><span><?php esc_html_e('Save records, collect sources, annotate, translate, map, calculate, investigate, synthesize, and produce.', 'sustainable-catalyst-library'); ?></span></div>
            <div class="sc-library-living-system__cards">
                <a href="<?php echo esc_url(add_query_arg('library_workspace', 'notebook', $system_urls['library'])); ?>"><strong><?php esc_html_e('Research Notebook', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Collections, notes, sources, matrices, boards, annotations, and books.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['research_librarian']); ?>"><strong><?php esc_html_e('Research Librarian', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Site-scoped guidance and confirmed action packets.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['workbench']); ?>"><strong><?php esc_html_e('Workbench', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Calculations, models, code, and technical analysis.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['decision_studio']); ?>"><strong><?php esc_html_e('Decision Studio', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Evidence packets, scenarios, and decision synthesis.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['site_intelligence']); ?>"><strong><?php esc_html_e('Site Intelligence', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Geographic, indicator, event, and source-aware inquiry.', 'sustainable-catalyst-library'); ?></span></a>
                <a href="<?php echo esc_url($system_urls['lab']); ?>"><strong><?php esc_html_e('Sustainable Catalyst Lab', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Scientific, engineering, and experimental workflows.', 'sustainable-catalyst-library'); ?></span></a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (in_array($system_mode, ['complete', 'institutional'], true)) : ?>
        <section id="sc-library-system-institutional" class="sc-library-living-system__layer-section sc-library-living-system__layer-section--institutional">
            <div class="sc-library-living-system__section-head"><p><?php esc_html_e('Institutional operations layer', 'sustainable-catalyst-library'); ?></p><h2><?php esc_html_e('Plan, govern, publish, and preserve', 'sustainable-catalyst-library'); ?></h2><span><?php esc_html_e('Coordinate roadmaps, editorial review, APIs, exports, preservation, integrity, and launch readiness.', 'sustainable-catalyst-library'); ?></span></div>
            <div class="sc-library-living-system__component-grid">
                <?php foreach ($system_manifest['components'] as $component) : ?>
                    <article class="sc-library-living-system__component sc-library-living-system__component--<?php echo esc_attr((string) $component['status']); ?>">
                        <span><?php echo esc_html(ucfirst((string) $component['status'])); ?></span><h3><?php echo esc_html((string) $component['label']); ?></h3><p><?php echo esc_html((string) $component['description']); ?></p><strong><?php echo esc_html(number_format_i18n((int) $component['count'])); ?></strong><?php if (!empty($component['url'])) : ?><a href="<?php echo esc_url((string) $component['url']); ?>"><?php esc_html_e('Open', 'sustainable-catalyst-library'); ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="sc-library-living-system__institutional-actions">
                <a class="sc-library-system-button" href="<?php echo esc_url($system_urls['developers']); ?>"><?php esc_html_e('Developer Platform', 'sustainable-catalyst-library'); ?></a>
                <a class="sc-library-system-button" href="<?php echo esc_url($system_urls['archive']); ?>"><?php esc_html_e('Institutional Archive', 'sustainable-catalyst-library'); ?></a>
                <a class="sc-library-system-button" href="<?php echo esc_url($system_urls['status']); ?>"><?php esc_html_e('Production Status', 'sustainable-catalyst-library'); ?></a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($recent_events) : ?>
        <section class="sc-library-living-system__activity" aria-labelledby="sc-library-system-activity-title">
            <div class="sc-library-living-system__section-head"><p><?php esc_html_e('Public activity', 'sustainable-catalyst-library'); ?></p><h2 id="sc-library-system-activity-title"><?php esc_html_e('Recent Living Knowledge updates', 'sustainable-catalyst-library'); ?></h2></div>
            <ol>
                <?php foreach ($recent_events as $event) : ?><li><span><?php echo esc_html((string) $event['event_type']); ?></span><strong><?php echo esc_html((string) $event['title']); ?></strong><p><?php echo esc_html((string) $event['summary']); ?></p><time><?php echo esc_html((string) $event['created_at']); ?></time></li><?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
</section>
