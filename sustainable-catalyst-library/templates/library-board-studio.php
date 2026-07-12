<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="sc-library-board-launcher" data-sc-library-board-root data-board-type="<?php echo esc_attr($board_type); ?>" data-board-open="<?php echo $board_open ? '1' : '0'; ?>" data-board-id="<?php echo esc_attr($board_id); ?>">
    <div>
        <p class="sc-library__eyebrow"><?php esc_html_e('Visual research workspace', 'sustainable-catalyst-library'); ?></p>
        <h3><?php echo esc_html($board_title); ?></h3>
        <p><?php echo esc_html($board_intro); ?></p>
    </div>
    <div class="sc-library-board-launcher__actions">
        <button type="button" data-new-board="whiteboard"><?php esc_html_e('New Whiteboard', 'sustainable-catalyst-library'); ?></button>
        <button type="button" data-new-board="chalkboard"><?php esc_html_e('New Chalkboard', 'sustainable-catalyst-library'); ?></button>
        <button type="button" data-open-board-library><?php esc_html_e('Open saved boards', 'sustainable-catalyst-library'); ?></button>
    </div>
</section>
