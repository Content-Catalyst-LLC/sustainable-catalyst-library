<?php if (!defined('ABSPATH')) { exit; } ?>
<section id="<?php echo esc_attr($tracker_id); ?>" class="sc-library-roadmap-tracker" data-sc-roadmap-tracker data-collection="<?php echo esc_attr($tracker_collection); ?>">
    <header>
        <p class="sc-library-roadmap-tracker__eyebrow"><?php esc_html_e('Public Knowledge Roadmap', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($tracker_title); ?></h2>
        <?php if ($tracker_intro !== '') : ?><p><?php echo esc_html($tracker_intro); ?></p><?php endif; ?>
    </header>
    <div data-tracker-status aria-live="polite"></div>
    <div class="sc-library-roadmap-tracker__summary" data-tracker-summary></div>
    <details open><summary><?php esc_html_e('Records by area', 'sustainable-catalyst-library'); ?></summary><div data-tracker-areas></div></details>
    <details><summary><?php esc_html_e('Records by product', 'sustainable-catalyst-library'); ?></summary><div data-tracker-products></div></details>
    <details><summary><?php esc_html_e('Article-map progress', 'sustainable-catalyst-library'); ?></summary><div data-tracker-maps></div></details>
</section>
