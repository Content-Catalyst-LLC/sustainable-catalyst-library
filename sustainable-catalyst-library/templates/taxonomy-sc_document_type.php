<?php
/**
 * Public Document Type archive.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$term = get_queried_object();
get_header();
?>
<main id="primary" class="site-main sc-document-type-template">
    <?php echo SC_Library_Document_Public_Repository::render_type_page( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
