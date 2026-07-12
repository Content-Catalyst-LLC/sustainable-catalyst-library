<?php if (!defined('ABSPATH')) { exit; } ?>
<section class="sc-library-integrations-shell" data-sc-library-integrations data-integration-open="<?php echo $integration_open ? '1' : '0'; ?>">
    <header class="sc-library-integrations__header">
        <div>
            <p class="sc-library__eyebrow"><?php esc_html_e('Cross-application research layer', 'sustainable-catalyst-library'); ?></p>
            <h2><?php echo esc_html($integration_title); ?></h2>
            <p><?php echo esc_html($integration_intro); ?></p>
        </div>
        <button type="button" data-integration-refresh><?php esc_html_e('Check connections', 'sustainable-catalyst-library'); ?></button>
    </header>
    <div class="sc-library-integrations__targets" data-integration-targets aria-live="polite"></div>
    <div class="sc-library-integrations__builder" data-integration-builder></div>
    <div class="sc-library-integrations__history" data-integration-history></div>
</section>
