<?php
/**
 * Public semantic record template.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) {
    the_post();
    $post_type = get_post_type();
    $kind = SC_Library_Topics_Concepts_Relationships::post_kind( $post_type );
    echo SC_Library_Topics_Concepts_Relationships::render_public_record( $kind, get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

get_footer();
