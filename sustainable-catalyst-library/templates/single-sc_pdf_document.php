<?php
/**
 * Single PDF Document template.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main sc-pdf-document-template">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php echo SC_Library_PDF_To_Document::render_single( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endwhile; ?>
</main>
<?php
get_footer();
