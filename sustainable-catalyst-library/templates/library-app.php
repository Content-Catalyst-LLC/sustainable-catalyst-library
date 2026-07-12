<?php if (!defined('ABSPATH')) { exit; } ?>
<section
    id="<?php echo esc_attr($instance_id); ?>"
    class="sc-library sc-library--<?php echo esc_attr($mode); ?> sc-library--density-<?php echo esc_attr($density); ?>"
    data-sc-library
    data-mode="<?php echo esc_attr($mode); ?>"
    data-initial-category="<?php echo esc_attr($initial_category); ?>"
    data-per-page="<?php echo esc_attr((string) $per_page); ?>"
    data-initial-results="<?php echo $initial_results ? '1' : '0'; ?>"
>
    <?php if ($show_header && $mode !== 'pathways') : ?>
        <header class="sc-library__masthead">
            <p class="sc-library__eyebrow"><?php esc_html_e('Sustainable Catalyst Knowledge Base', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html($title); ?></h2>
            <?php if ($intro !== '') : ?><p><?php echo esc_html($intro); ?></p><?php endif; ?>
        </header>
    <?php endif; ?>

    <?php if (in_array($mode, ['compact', 'full', 'search'], true)) : ?>
        <form class="sc-library__command" data-library-form role="search">
            <label class="sc-library__search-field">
                <span class="screen-reader-text"><?php esc_html_e('Search the Research Library', 'sustainable-catalyst-library'); ?></span>
                <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input
                    type="search"
                    name="search"
                    placeholder="<?php echo esc_attr((string) get_option('sc_library_search_placeholder', 'Search concepts, series, methods, and publications')); ?>"
                    autocomplete="off"
                    data-library-search
                >
            </label>
            <button type="submit" class="sc-library__search-button"><?php esc_html_e('Search', 'sustainable-catalyst-library'); ?></button>
        </form>
    <?php endif; ?>

    <?php if (in_array($mode, ['compact', 'full', 'domains'], true)) : ?>
        <details class="sc-library__topics" data-topic-browser <?php echo in_array($mode, ['full', 'domains'], true) ? 'open' : ''; ?>>
            <summary>
                <span>
                    <strong><?php esc_html_e('Browse the knowledge architecture', 'sustainable-catalyst-library'); ?></strong>
                    <small><?php esc_html_e('Open a domain, then choose a topic or article map.', 'sustainable-catalyst-library'); ?></small>
                </span>
                <span class="sc-library__summary-action"><?php esc_html_e('Browse topics', 'sustainable-catalyst-library'); ?></span>
            </summary>
            <div class="sc-library__domain-grid" data-category-list aria-live="polite"></div>
        </details>
    <?php endif; ?>

    <?php if ($show_pathways && $pathways && in_array($mode, ['compact', 'full', 'pathways'], true)) : ?>
        <section class="sc-library__pathways" aria-labelledby="<?php echo esc_attr($instance_id); ?>-pathways-title">
            <div class="sc-library__section-line">
                <h3 id="<?php echo esc_attr($instance_id); ?>-pathways-title"><?php esc_html_e('Featured pathways', 'sustainable-catalyst-library'); ?></h3>
                <span><?php esc_html_e('Curated entry points', 'sustainable-catalyst-library'); ?></span>
            </div>
            <div class="sc-library__pathway-list">
                <?php foreach ($pathways as $pathway) : ?>
                    <a href="<?php echo esc_url($pathway['url']); ?>">
                        <strong><?php echo esc_html($pathway['title']); ?></strong>
                        <?php if ($pathway['description'] !== '') : ?><span><?php echo esc_html($pathway['description']); ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($mode !== 'pathways') : ?>
        <section class="sc-library__recent" data-library-recent hidden>
            <div class="sc-library__section-line">
                <h3><?php esc_html_e('Recently opened', 'sustainable-catalyst-library'); ?></h3>
                <button type="button" data-clear-recent><?php esc_html_e('Clear', 'sustainable-catalyst-library'); ?></button>
            </div>
            <div class="sc-library__recent-list" data-recent-list></div>
        </section>

        <section class="sc-library__results-region" data-results-region hidden aria-labelledby="<?php echo esc_attr($instance_id); ?>-results-title">
            <div class="sc-library__results-head">
                <div>
                    <p class="sc-library__results-kicker"><?php esc_html_e('Knowledge records', 'sustainable-catalyst-library'); ?></p>
                    <h3 id="<?php echo esc_attr($instance_id); ?>-results-title" data-results-title><?php esc_html_e('Search results', 'sustainable-catalyst-library'); ?></h3>
                </div>
                <div class="sc-library__results-tools">
                    <label>
                        <span class="screen-reader-text"><?php esc_html_e('Sort knowledge records', 'sustainable-catalyst-library'); ?></span>
                        <select name="sort" data-library-sort>
                            <option value="relevance"><?php esc_html_e('Most relevant', 'sustainable-catalyst-library'); ?></option>
                            <option value="updated"><?php esc_html_e('Recently updated', 'sustainable-catalyst-library'); ?></option>
                            <option value="newest"><?php esc_html_e('Newest published', 'sustainable-catalyst-library'); ?></option>
                            <option value="oldest"><?php esc_html_e('Oldest published', 'sustainable-catalyst-library'); ?></option>
                            <option value="title"><?php esc_html_e('Title A–Z', 'sustainable-catalyst-library'); ?></option>
                        </select>
                    </label>
                    <button type="button" class="sc-library__reset" data-clear-filters><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button>
                </div>
            </div>

            <div class="sc-library__active-filter" data-active-filter hidden></div>
            <div class="sc-library__status" data-library-status aria-live="polite"></div>
            <div class="sc-library__results" data-library-results aria-live="polite"></div>
            <nav class="sc-library__pagination" data-library-pagination aria-label="<?php esc_attr_e('Library result pages', 'sustainable-catalyst-library'); ?>"></nav>
        </section>

        <div class="sc-library-context" data-library-context hidden>
            <button class="sc-library-context__overlay" type="button" data-context-close aria-label="<?php esc_attr_e('Close record panel', 'sustainable-catalyst-library'); ?>"></button>
            <aside class="sc-library-context__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($instance_id); ?>-context-title">
                <button type="button" class="sc-library-context__close" data-context-close aria-label="<?php esc_attr_e('Close record panel', 'sustainable-catalyst-library'); ?>">×</button>
                <div data-context-content></div>
            </aside>
        </div>
    <?php endif; ?>
</section>
