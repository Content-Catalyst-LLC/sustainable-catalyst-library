<?php
/**
 * Public PDF Document Repository.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

status_header( 200 );
get_header();
?>
<main id="primary" class="site-main sc-document-repository-template">
    <?php echo SC_Library_Document_Public_Repository::render_repository_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
