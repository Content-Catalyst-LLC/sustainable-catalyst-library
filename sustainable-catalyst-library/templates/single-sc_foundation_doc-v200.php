<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$post_id = get_queried_object_id();
echo SC_Library_Foundation_System_V200::instance()->render_single($post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
get_footer();
