<?php
/**
 * Public Knowledge Pathway template.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main sc-knowledge-pathway-page">
    <?php while ( have_posts() ) : the_post(); ?>
        <?php echo SC_Library_Knowledge_Pathways_Article_Maps::render_pathway( get_the_ID(), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endwhile; ?>
</main>
<?php
get_footer();
