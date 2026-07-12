<?php if (!defined('ABSPATH')) { exit; } ?>
<section class="sc-library" data-sc-library>
    <header class="sc-library__hero">
        <p class="sc-library__eyebrow">Sustainable Catalyst Open Knowledge Lab</p>
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($show_intro) : ?>
            <p>Explore Sustainable Catalyst publications through structured categories, full-library search, and persistent filters.</p>
        <?php endif; ?>
    </header>

    <div class="sc-library__shell">
        <aside class="sc-library__sidebar" aria-label="Library categories">
            <div class="sc-library__sidebar-heading">
                <h3>Categories</h3>
                <button type="button" class="sc-library__clear" data-clear-filters>Clear filters</button>
            </div>
            <nav data-category-list>
                <button type="button" class="sc-library__category is-active" data-category-id="0">All publications</button>
            </nav>
        </aside>

        <div class="sc-library__main">
            <form class="sc-library__controls" data-library-form>
                <label class="sc-library__search">
                    <span class="screen-reader-text">Search the Library</span>
                    <input type="search" name="search" placeholder="Search titles, excerpts, and article text" autocomplete="off">
                </label>
                <label>
                    <span class="screen-reader-text">Sort results</span>
                    <select name="sort">
                        <option value="newest">Newest first</option>
                        <option value="updated">Recently updated</option>
                        <option value="oldest">Oldest first</option>
                        <option value="title">Title A–Z</option>
                    </select>
                </label>
                <button type="submit" class="sc-library__submit">Search</button>
            </form>

            <div class="sc-library__status" data-library-status aria-live="polite"></div>
            <div class="sc-library__grid" data-library-results></div>
            <nav class="sc-library__pagination" data-library-pagination aria-label="Library result pages"></nav>
        </div>
    </div>
</section>
