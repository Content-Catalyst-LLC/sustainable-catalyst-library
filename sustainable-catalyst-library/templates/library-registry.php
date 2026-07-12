<?php if (!defined('ABSPATH')) { exit; } ?>
<section id="<?php echo esc_attr($instance_id); ?>" class="sc-library-registry" data-sc-registry data-collection="<?php echo esc_attr($registry_collection); ?>" data-per-page="<?php echo esc_attr((string) $registry_per_page); ?>">
    <header class="sc-library-registry__header">
        <p class="sc-library-registry__eyebrow"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($registry_title); ?></h2>
        <?php if ($registry_intro !== '') : ?><p><?php echo esc_html($registry_intro); ?></p><?php endif; ?>
    </header>

    <?php if ($registry_show_tracker) : ?>
        <div class="sc-library-registry__summary" data-registry-summary aria-live="polite"></div>
    <?php endif; ?>

    <form class="sc-library-registry__controls" data-registry-form>
        <label class="sc-library-registry__search"><span class="screen-reader-text"><?php esc_html_e('Search the complete public registry', 'sustainable-catalyst-library'); ?></span><input type="search" name="search" placeholder="<?php esc_attr_e('Search posts, documents, plans, products, and article maps', 'sustainable-catalyst-library'); ?>" data-registry-search></label>
        <select name="state" data-registry-state><option value=""><?php esc_html_e('All states', 'sustainable-catalyst-library'); ?></option></select>
        <select name="type" data-registry-type><option value=""><?php esc_html_e('All record types', 'sustainable-catalyst-library'); ?></option></select>
        <select name="area" data-registry-area><option value=""><?php esc_html_e('All areas', 'sustainable-catalyst-library'); ?></option></select>
        <select name="product" data-registry-product><option value=""><?php esc_html_e('All products', 'sustainable-catalyst-library'); ?></option></select>
        <select name="sort" data-registry-sort>
            <option value="updated"><?php esc_html_e('Recently updated', 'sustainable-catalyst-library'); ?></option>
            <option value="title"><?php esc_html_e('Title A–Z', 'sustainable-catalyst-library'); ?></option>
            <option value="release"><?php esc_html_e('Expected release', 'sustainable-catalyst-library'); ?></option>
            <option value="state"><?php esc_html_e('Record state', 'sustainable-catalyst-library'); ?></option>
            <option value="oldest"><?php esc_html_e('Oldest first', 'sustainable-catalyst-library'); ?></option>
        </select>
        <label class="sc-library-registry__archive"><input type="checkbox" name="include_archived" value="1" checked data-registry-archived> <?php esc_html_e('Include historical records', 'sustainable-catalyst-library'); ?></label>
        <button type="submit"><?php esc_html_e('Search registry', 'sustainable-catalyst-library'); ?></button>
        <button type="button" class="sc-library-registry__reset" data-registry-reset><?php esc_html_e('Reset', 'sustainable-catalyst-library'); ?></button>
    </form>

    <div class="sc-library-registry__toolbar">
        <p data-registry-status aria-live="polite"></p>
        <div><button type="button" data-registry-export="csv"><?php esc_html_e('Export CSV', 'sustainable-catalyst-library'); ?></button><button type="button" data-registry-export="json"><?php esc_html_e('Export JSON', 'sustainable-catalyst-library'); ?></button></div>
    </div>
    <div class="sc-library-registry__results" data-registry-results aria-live="polite"></div>
    <nav class="sc-library-registry__pagination" data-registry-pagination aria-label="<?php esc_attr_e('Registry result pages', 'sustainable-catalyst-library'); ?>"></nav>
</section>
