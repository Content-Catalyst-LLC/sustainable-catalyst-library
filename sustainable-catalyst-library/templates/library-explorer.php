<?php if (!defined('ABSPATH')) { exit; } ?>
<section
    id="<?php echo esc_attr($instance_id); ?>"
    class="sc-library-explorer"
    data-sc-library-explorer
    data-per-page="<?php echo esc_attr((string) $per_page); ?>"
    data-version="5.6.0"
>
    <?php if ($show_header) : ?>
        <header class="sc-library-explorer__hero">
            <div class="sc-library-explorer__hero-copy">
                <p class="sc-library-explorer__eyebrow"><?php esc_html_e('Public Knowledge', 'sustainable-catalyst-library'); ?></p>
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($intro); ?></p>
            </div>
            <div class="sc-library-explorer__state" data-explorer-state aria-live="polite">
                <span class="sc-library-explorer__pulse" aria-hidden="true"></span>
                <span><?php esc_html_e('Connecting to Library intelligence…', 'sustainable-catalyst-library'); ?></span>
            </div>
        </header>
    <?php endif; ?>

    <form class="sc-library-explorer__search" data-explorer-search role="search">
        <label>
            <span class="screen-reader-text"><?php esc_html_e('Search the Knowledge Library', 'sustainable-catalyst-library'); ?></span>
            <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
            <input type="search" data-explorer-q placeholder="<?php esc_attr_e('Search research, evidence, concepts, and publications', 'sustainable-catalyst-library'); ?>" autocomplete="off">
        </label>
        <button type="submit"><?php esc_html_e('Search', 'sustainable-catalyst-library'); ?></button>
    </form>

    <div class="sc-library-explorer__metrics" data-explorer-metrics aria-label="<?php esc_attr_e('Library scale', 'sustainable-catalyst-library'); ?>">
        <div><strong data-metric-records>—</strong><span><?php esc_html_e('published records', 'sustainable-catalyst-library'); ?></span></div>
        <div><strong data-metric-topics>—</strong><span><?php esc_html_e('active topics', 'sustainable-catalyst-library'); ?></span></div>
        <div><strong data-metric-chunks>—</strong><span><?php esc_html_e('searchable passages', 'sustainable-catalyst-library'); ?></span></div>
    </div>

    <section class="sc-library-explorer__discover" aria-labelledby="<?php echo esc_attr($instance_id); ?>-discover-title">
        <div class="sc-library-explorer__section-head">
            <div>
                <p><?php esc_html_e('Explore', 'sustainable-catalyst-library'); ?></p>
                <h3 id="<?php echo esc_attr($instance_id); ?>-discover-title"><?php esc_html_e('Start with a topic', 'sustainable-catalyst-library'); ?></h3>
            </div>
            <button type="button" class="sc-library-explorer__filter-toggle" data-explorer-filter-toggle aria-expanded="false" style="display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:38px!important;padding:8px 12px!important;border:1px solid #cfcfcf!important;background:#fff!important;color:#151515!important;-webkit-text-fill-color:#151515!important;visibility:visible!important;opacity:1!important;">
                <?php esc_html_e('Filters', 'sustainable-catalyst-library'); ?>
            </button>
        </div>
        <div class="sc-library-explorer__topic-strip" data-explorer-topic-strip aria-live="polite"></div>

        <div class="sc-library-explorer__filters" data-explorer-filters hidden>
            <label><span><?php esc_html_e('Record type', 'sustainable-catalyst-library'); ?></span><select data-filter-type><option value=""><?php esc_html_e('All types', 'sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('Topic', 'sustainable-catalyst-library'); ?></span><select data-filter-topic><option value=""><?php esc_html_e('All topics', 'sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('Source', 'sustainable-catalyst-library'); ?></span><select data-filter-source><option value=""><?php esc_html_e('All sources', 'sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('From year', 'sustainable-catalyst-library'); ?></span><select data-filter-year-from><option value=""><?php esc_html_e('Any year', 'sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('To year', 'sustainable-catalyst-library'); ?></span><select data-filter-year-to><option value=""><?php esc_html_e('Any year', 'sustainable-catalyst-library'); ?></option></select></label>
            <label><span><?php esc_html_e('Sort', 'sustainable-catalyst-library'); ?></span><select data-filter-sort>
                <option value="relevance"><?php esc_html_e('Most relevant', 'sustainable-catalyst-library'); ?></option>
                <option value="updated"><?php esc_html_e('Recently updated', 'sustainable-catalyst-library'); ?></option>
                <option value="newest"><?php esc_html_e('Newest published', 'sustainable-catalyst-library'); ?></option>
                <option value="oldest"><?php esc_html_e('Oldest published', 'sustainable-catalyst-library'); ?></option>
                <option value="title"><?php esc_html_e('Title A–Z', 'sustainable-catalyst-library'); ?></option>
            </select></label>
            <button type="button" class="sc-library-explorer__reset" data-explorer-reset style="display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:38px!important;padding:8px 12px!important;border:1px solid #cfcfcf!important;background:#fff!important;color:#151515!important;-webkit-text-fill-color:#151515!important;visibility:visible!important;opacity:1!important;"><?php esc_html_e('Reset filters', 'sustainable-catalyst-library'); ?></button>
        </div>
    </section>

    <section class="sc-library-explorer__featured" data-explorer-featured-section aria-labelledby="<?php echo esc_attr($instance_id); ?>-featured-title">
        <div class="sc-library-explorer__section-head">
            <div><p><?php esc_html_e('Discovery', 'sustainable-catalyst-library'); ?></p><h3 id="<?php echo esc_attr($instance_id); ?>-featured-title"><?php esc_html_e('Recently published', 'sustainable-catalyst-library'); ?></h3></div>
            <a href="<?php echo esc_url($librarian_url); ?>"><?php esc_html_e('Ask the Research Librarian', 'sustainable-catalyst-library'); ?> →</a>
        </div>
        <div class="sc-library-explorer__cards sc-library-explorer__cards--featured" data-explorer-featured aria-live="polite"></div>
    </section>

    <section class="sc-library-explorer__results" data-explorer-results-section hidden aria-labelledby="<?php echo esc_attr($instance_id); ?>-results-title">
        <div class="sc-library-explorer__section-head sc-library-explorer__section-head--results">
            <div>
                <p data-results-kicker><?php esc_html_e('Library Explorer', 'sustainable-catalyst-library'); ?></p>
                <h3 id="<?php echo esc_attr($instance_id); ?>-results-title" data-results-title><?php esc_html_e('Search results', 'sustainable-catalyst-library'); ?></h3>
            </div>
            <span data-results-count aria-live="polite"></span>
        </div>
        <div class="sc-library-explorer__active" data-active-filters hidden></div>
        <div class="sc-library-explorer__cards" data-explorer-results aria-live="polite"></div>
        <div class="sc-library-explorer__load-more-wrap"><button type="button" data-explorer-load-more hidden><?php esc_html_e('Load more', 'sustainable-catalyst-library'); ?></button></div>
    </section>

    <footer class="sc-library-explorer__footer">
        <span><?php esc_html_e('The Explorer loads small result sets on demand instead of rendering the full Library catalog.', 'sustainable-catalyst-library'); ?></span>
        <a href="<?php echo esc_url($legacy_url); ?>"><?php esc_html_e('Open local catalog', 'sustainable-catalyst-library'); ?></a>
    </footer>

    <div class="sc-library-explorer-drawer" data-explorer-drawer hidden>
        <button type="button" class="sc-library-explorer-drawer__backdrop" data-drawer-close aria-label="<?php esc_attr_e('Close record preview', 'sustainable-catalyst-library'); ?>"></button>
        <aside class="sc-library-explorer-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($instance_id); ?>-drawer-title">
            <div class="sc-library-explorer-drawer__bar"><span><?php esc_html_e('Knowledge record', 'sustainable-catalyst-library'); ?></span><button type="button" data-drawer-close aria-label="<?php esc_attr_e('Close record preview', 'sustainable-catalyst-library'); ?>">×</button></div>
            <div class="sc-library-explorer-drawer__content" data-drawer-content></div>
        </aside>
    </div>
</section>
