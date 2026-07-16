<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<main id="main-content" class="sc-fnd-archive">
    <?php echo SC_Library_Foundation_System_V200::instance()->render_archive(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
