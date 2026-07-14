<?php
/**
 * Public research source record.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();
    echo SC_Library_Citation_Source_Manager::render_public_source_page( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
endwhile;

get_footer();
