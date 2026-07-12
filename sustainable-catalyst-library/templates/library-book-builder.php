<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section
    class="sc-library-book-launcher"
    data-sc-library-books-root
    data-book-open="<?php echo $book_builder_open ? '1' : '0'; ?>"
    data-book-id="<?php echo esc_attr($book_id); ?>"
>
    <div>
        <p class="sc-library__eyebrow"><?php esc_html_e('Custom research publishing', 'sustainable-catalyst-library'); ?></p>
        <h3><?php echo esc_html($book_builder_title); ?></h3>
        <p><?php echo esc_html($book_builder_intro); ?></p>
    </div>
    <div class="sc-library-book-launcher__actions">
        <button type="button" data-new-book><?php esc_html_e('New book', 'sustainable-catalyst-library'); ?></button>
        <button type="button" data-open-book-library><?php esc_html_e('Open saved books', 'sustainable-catalyst-library'); ?></button>
    </div>
    <div class="sc-library-books-inline" data-sc-library-books-stage></div>
</section>
