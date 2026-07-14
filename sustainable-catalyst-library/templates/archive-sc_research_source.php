<?php
/**
 * Public research source archive.
 *
 * @package Sustainable_Catalyst_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

echo SC_Library_Citation_Source_Manager::render_archive_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
