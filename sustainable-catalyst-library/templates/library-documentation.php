<?php if (!defined('ABSPATH')) { exit; } ?>
<section
    id="<?php echo esc_attr($instance_id); ?>"
    class="sc-doc-library sc-doc-library--<?php echo esc_attr($mode); ?>"
    data-sc-doc-library
    data-per-page="<?php echo esc_attr((string) $per_page); ?>"
    data-include-archived="<?php echo $include_archived ? '1' : '0'; ?>"
    data-show-featured="<?php echo $show_featured ? '1' : '0'; ?>"
>
    <?php if ($show_header) : ?>
        <header class="sc-doc-library__header">
            <p class="sc-doc-library__eyebrow"><?php esc_html_e('Sustainable Catalyst Foundations', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html($title); ?></h2>
            <?php if ($intro !== '') : ?><p><?php echo esc_html($intro); ?></p><?php endif; ?>
        </header>
    <?php endif; ?>

    <div class="sc-doc-library__command">
        <form data-doc-search-form role="search">
            <label>
                <span class="screen-reader-text"><?php esc_html_e('Search documentation', 'sustainable-catalyst-library'); ?></span>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input type="search" data-doc-search autocomplete="off" placeholder="<?php echo esc_attr((string) get_option('sc_library_documentation_search_placeholder', 'Search titles, descriptions, keywords, and document text')); ?>">
            </label>
            <button type="submit"><?php esc_html_e('Search documentation', 'sustainable-catalyst-library'); ?></button>
        </form>
    </div>

    <div class="sc-doc-library__filters" aria-label="<?php esc_attr_e('Documentation filters', 'sustainable-catalyst-library'); ?>">
        <label><span><?php esc_html_e('Category', 'sustainable-catalyst-library'); ?></span><select data-doc-category><option value=""><?php esc_html_e('All documentation', 'sustainable-catalyst-library'); ?></option></select></label>
        <label><span><?php esc_html_e('Status', 'sustainable-catalyst-library'); ?></span><select data-doc-status><option value=""><?php esc_html_e('Current and living', 'sustainable-catalyst-library'); ?></option></select></label>
        <label><span><?php esc_html_e('Responsible area', 'sustainable-catalyst-library'); ?></span><select data-doc-area><option value=""><?php esc_html_e('All areas', 'sustainable-catalyst-library'); ?></option></select></label>
        <label class="sc-doc-library__archive-toggle"><input type="checkbox" data-doc-archived <?php checked($include_archived, true); ?>> <span><?php esc_html_e('Include superseded and archived', 'sustainable-catalyst-library'); ?></span></label>
        <button type="button" class="sc-doc-library__reset" data-doc-reset><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button>
    </div>

    <details class="sc-doc-library__authority-guide">
        <summary><strong><?php esc_html_e('How authoritative sources work', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Living pages, repositories, releases, PDFs, and archives have different roles.', 'sustainable-catalyst-library'); ?></span></summary>
        <div class="sc-doc-library__authority-grid">
            <div><strong><?php esc_html_e('Institution and product descriptions', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Current public webpage', 'sustainable-catalyst-library'); ?></span></div>
            <div><strong><?php esc_html_e('Technical behavior', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Repository documentation', 'sustainable-catalyst-library'); ?></span></div>
            <div><strong><?php esc_html_e('Methodology and boundaries', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Current methodology page', 'sustainable-catalyst-library'); ?></span></div>
            <div><strong><?php esc_html_e('Release state', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Repository release record', 'sustainable-catalyst-library'); ?></span></div>
            <div><strong><?php esc_html_e('Brand or policy snapshot', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Published PDF', 'sustainable-catalyst-library'); ?></span></div>
            <div><strong><?php esc_html_e('Historical brief', 'sustainable-catalyst-library'); ?></strong><span><?php esc_html_e('Archived PDF', 'sustainable-catalyst-library'); ?></span></div>
        </div>
    </details>

    <?php if ($show_featured) : ?>
        <section class="sc-doc-library__featured" data-doc-featured-region hidden>
            <div class="sc-doc-library__section-head"><div><p><?php esc_html_e('Featured living documentation', 'sustainable-catalyst-library'); ?></p><h3><?php esc_html_e('Current institutional references', 'sustainable-catalyst-library'); ?></h3></div><span><?php esc_html_e('Curated records', 'sustainable-catalyst-library'); ?></span></div>
            <div class="sc-doc-library__featured-grid" data-doc-featured></div>
        </section>
    <?php endif; ?>

    <section class="sc-doc-library__results-region" aria-labelledby="<?php echo esc_attr($instance_id); ?>-results-title">
        <div class="sc-doc-library__section-head">
            <div><p><?php esc_html_e('Documentation records', 'sustainable-catalyst-library'); ?></p><h3 id="<?php echo esc_attr($instance_id); ?>-results-title" data-doc-results-title><?php esc_html_e('Foundations documentation', 'sustainable-catalyst-library'); ?></h3></div>
            <label class="sc-doc-library__sort"><span><?php esc_html_e('Sort', 'sustainable-catalyst-library'); ?></span><select data-doc-sort><option value="updated"><?php esc_html_e('Recently updated', 'sustainable-catalyst-library'); ?></option><option value="title"><?php esc_html_e('Title A–Z', 'sustainable-catalyst-library'); ?></option><option value="oldest"><?php esc_html_e('Oldest published', 'sustainable-catalyst-library'); ?></option></select></label>
        </div>
        <div class="sc-doc-library__active" data-doc-active hidden></div>
        <div class="sc-doc-library__status" data-doc-loading aria-live="polite"></div>
        <div class="sc-doc-library__records" data-doc-records aria-live="polite"></div>
        <nav class="sc-doc-library__pagination" data-doc-pagination aria-label="<?php esc_attr_e('Documentation pages', 'sustainable-catalyst-library'); ?>"></nav>
    </section>
</section>
