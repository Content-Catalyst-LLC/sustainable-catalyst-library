<?php
/**
 * Single Foundation Document Page template.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main sc-foundation-document-main">
    <?php
    while ( have_posts() ) {
        the_post();
        echo SC_Library_Foundation_Pages::render_single_document( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
</main>
<?php
get_footer();
