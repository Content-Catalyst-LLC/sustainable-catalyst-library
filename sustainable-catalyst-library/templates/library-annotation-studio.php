<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section
    class="sc-library-annotation-launcher"
    data-sc-library-annotation-root
    data-annotation-open="<?php echo $annotation_open ? '1' : '0'; ?>"
    data-annotation-id="<?php echo esc_attr($annotation_id); ?>"
    data-target-type="<?php echo esc_attr($target_type); ?>"
    data-target-id="<?php echo esc_attr($target_id); ?>"
    data-target-title="<?php echo esc_attr($target_title); ?>"
    data-target-url="<?php echo esc_url($target_url); ?>"
    data-target-excerpt="<?php echo esc_attr($target_excerpt); ?>"
>
    <div>
        <p class="sc-library__eyebrow"><?php esc_html_e('Annotation and handwriting layer', 'sustainable-catalyst-library'); ?></p>
        <h3><?php echo esc_html($annotation_title); ?></h3>
        <p><?php echo esc_html($annotation_intro); ?></p>
    </div>
    <div class="sc-library-annotation-launcher__actions">
        <button type="button" data-new-annotation><?php esc_html_e('New annotation', 'sustainable-catalyst-library'); ?></button>
        <button type="button" data-open-annotation-library><?php esc_html_e('Open saved annotations', 'sustainable-catalyst-library'); ?></button>
    </div>
</section>
