<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sc-library-document-production" data-sc-library-documents-root>
    <header class="sc-library-document-production__header">
        <div>
            <p class="sc-library__eyebrow"><?php esc_html_e('Server-side publishing', 'sustainable-catalyst-library'); ?></p>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($intro); ?></p>
        </div>
        <button type="button" data-document-refresh-list><?php esc_html_e('Refresh jobs', 'sustainable-catalyst-library'); ?></button>
    </header>
    <div data-document-production-stage></div>
</section>
