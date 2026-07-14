<?php
/**
 * PDF Document Family archive.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$term = get_queried_object();
get_header();
?>
<main id="primary" class="site-main sc-pdf-document-family-template">
    <div class="cc-research-library-brand cc-rl-v2">
        <section class="cc-rl-hero">
            <p class="cc-rl-kicker"><?php esc_html_e( 'PDF Document Family', 'sustainable-catalyst-library' ); ?></p>
            <h1><?php echo esc_html( $term && isset( $term->name ) ? $term->name : __( 'Documents', 'sustainable-catalyst-library' ) ); ?></h1>
            <?php if ( $term && ! empty( $term->description ) ) : ?><p class="cc-rl-lede"><?php echo esc_html( $term->description ); ?></p><?php endif; ?>
        </section>
        <section class="cc-rl-section cc-rl-section-white">
            <?php echo do_shortcode( sprintf( '[sc_pdf_document_library family="%s" show_header="false" per_page="12"]', esc_attr( $term && isset( $term->slug ) ? $term->slug : '' ) ) ); ?>
        </section>
    </div>
</main>
<?php
get_footer();
