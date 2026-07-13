<?php if (!defined('ABSPATH')) exit; ?>
<section class="sc-library-multimedia-shell" data-mode="<?php echo esc_attr($multimedia_mode); ?>">
    <header class="sc-library-multimedia-shell__header">
        <p class="sc-library-multimedia__eyebrow"><?php esc_html_e('Sustainable Catalyst Library', 'sustainable-catalyst-library'); ?></p>
        <h2><?php echo esc_html($multimedia_title); ?></h2>
        <p><?php echo esc_html($multimedia_intro); ?></p>
    </header>
    <?php if (!is_user_logged_in()) : ?>
        <p class="sc-library-multimedia__notice"><?php esc_html_e('Sign in to create or edit multimedia projects.', 'sustainable-catalyst-library'); ?></p>
    <?php endif; ?>
    <div data-sc-library-multimedia-root></div>
</section>
