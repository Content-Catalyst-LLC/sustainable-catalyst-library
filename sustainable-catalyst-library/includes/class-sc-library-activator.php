<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Activator {
    public static function activate(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sc_library_index';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(64) NOT NULL,
            title TEXT NOT NULL,
            excerpt LONGTEXT NULL,
            searchable_text LONGTEXT NULL,
            permalink TEXT NOT NULL,
            primary_category_id BIGINT UNSIGNED NULL,
            category_ids LONGTEXT NULL,
            tag_ids LONGTEXT NULL,
            published_at DATETIME NULL,
            modified_at DATETIME NULL,
            indexed_at DATETIME NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'publish',
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY post_type (post_type),
            KEY primary_category_id (primary_category_id),
            KEY published_at (published_at),
            KEY modified_at (modified_at),
            FULLTEXT KEY sc_library_search (title, excerpt, searchable_text)
        ) {$charset};";

        dbDelta($sql);

        add_option('sc_library_version', SC_LIBRARY_VERSION);
        add_option('sc_library_post_types', ['post']);
        add_option('sc_library_items_per_page', 12);
        add_option('sc_library_enable_tags', 1);
        add_option('sc_library_last_full_index', '');

        if (!wp_next_scheduled('sc_library_daily_reconcile')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sc_library_daily_reconcile');
        }

        set_transient('sc_library_activation_notice', 1, 60);
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('sc_library_daily_reconcile');
    }
}
