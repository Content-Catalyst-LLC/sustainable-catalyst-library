<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SC_Library_Activator {
    public static function activate(): void {
        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
        update_option('sc_library_version', SC_LIBRARY_VERSION);
        update_option('sc_library_flush_rewrite', 1, false);
        set_transient('sc_library_activation_notice', 1, 90);
    }

    public static function maybe_upgrade(): void {
        $installed = (string) get_option('sc_library_version', '0.0.0');
        if (version_compare($installed, SC_LIBRARY_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
        update_option('sc_library_version', SC_LIBRARY_VERSION);
        update_option('sc_library_flush_rewrite', 1, false);
        set_transient('sc_library_upgrade_notice', 1, 90);
    }

    private static function create_tables(): void {
        global $wpdb;

        $index_table = $wpdb->prefix . 'sc_library_index';
        $relationships_table = $wpdb->prefix . 'sc_library_relationships';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $index_sql = "CREATE TABLE {$index_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            record_identifier VARCHAR(191) NULL,
            post_type VARCHAR(64) NOT NULL,
            title TEXT NOT NULL,
            excerpt LONGTEXT NULL,
            searchable_text LONGTEXT NULL,
            permalink TEXT NOT NULL,
            primary_category_id BIGINT UNSIGNED NULL,
            primary_domain_id BIGINT UNSIGNED NULL,
            category_ids LONGTEXT NULL,
            tag_ids LONGTEXT NULL,
            series_term_id BIGINT UNSIGNED NULL,
            series_order DECIMAL(12,3) NOT NULL DEFAULT 0,
            concept_ids LONGTEXT NULL,
            resource_flags LONGTEXT NULL,
            published_at DATETIME NULL,
            modified_at DATETIME NULL,
            indexed_at DATETIME NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'publish',
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY record_identifier (record_identifier),
            KEY post_type (post_type),
            KEY primary_category_id (primary_category_id),
            KEY primary_domain_id (primary_domain_id),
            KEY series_term_id (series_term_id),
            KEY series_order (series_order),
            KEY published_at (published_at),
            KEY modified_at (modified_at),
            FULLTEXT KEY sc_library_search (title, excerpt, searchable_text)
        ) {$charset};";

        $relationships_sql = "CREATE TABLE {$relationships_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_post_id BIGINT UNSIGNED NOT NULL,
            target_post_id BIGINT UNSIGNED NOT NULL,
            relationship_type VARCHAR(64) NOT NULL,
            note TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_target_type (source_post_id, target_post_id, relationship_type),
            KEY source_post_id (source_post_id),
            KEY target_post_id (target_post_id),
            KEY relationship_type (relationship_type)
        ) {$charset};";

        dbDelta($index_sql);
        dbDelta($relationships_sql);
    }

    private static function install_defaults(): void {
        add_option('sc_library_post_types', ['post']);
        add_option('sc_library_items_per_page', 10);
        add_option('sc_library_enable_tags', 1);
        add_option('sc_library_last_full_index', '');
        add_option('sc_library_default_mode', 'compact');
        add_option('sc_library_initial_results', 0);
        add_option('sc_library_result_density', 'compact');
        add_option('sc_library_excerpt_words', 28);
        add_option('sc_library_show_pathways', 1);
        add_option('sc_library_search_placeholder', 'Search concepts, series, methods, and publications');
        add_option('sc_library_workbench_url', home_url('/workbench/'));
        add_option('sc_library_enable_notebook', 1);
        add_option('sc_library_notebook_storage_mode', 'local');
        add_option('sc_library_enable_translation_matrix', 1);
        add_option('sc_library_default_matrix_template', 'technical_translation');
        add_option('sc_library_enable_boards', 1);
        add_option('sc_library_default_board_type', 'whiteboard');
        add_option('sc_library_enable_integrations', 1);
        add_option('sc_library_enable_annotations', 1);
        add_option('sc_library_default_annotation_page_style', 'reader');
        add_option('sc_library_enable_books', 1);
        add_option('sc_library_default_book_theme', 'institutional');
        add_option('sc_library_default_book_page_size', 'letter');
        add_option('sc_library_enable_documentation', 1);
        add_option('sc_library_enable_planner', 1);
        add_option('sc_library_enable_portability', 1);
        add_option('sc_library_release_capacity_threshold', 40);
        add_option('sc_library_default_effort_unit', 'points');
        add_option('sc_library_on_time_tolerance_days', 7);
        add_option('sc_library_export_schema_name', 'sustainable_catalyst_library');
        add_option('sc_library_registry_include_planned', 1);
        add_option('sc_library_registry_include_archived', 1);
        add_option('sc_library_main_page_url', home_url('/research-library/'));
        add_option('sc_library_documentation_search_placeholder', 'Search titles, descriptions, keywords, and document text');
        add_option('sc_library_documentation_include_archived', 0);
        add_option('sc_library_workbench_health_url', '');
        add_option('sc_library_decision_studio_url', home_url('/decision-studio/'));
        add_option('sc_library_decision_studio_health_url', '');
        add_option('sc_library_site_intelligence_url', home_url('/site-intelligence/'));
        add_option('sc_library_site_intelligence_health_url', '');
        add_option('sc_library_featured_pathways', implode("\n", [
            'Systems Thinking|/systems-thinking/|Feedback, resilience, leverage points, and complex change.',
            'Mathematical Thinking|/mathematical-thinking/|Symbols, models, uncertainty, and formal reasoning.',
            'Algorithms & Computational Reasoning|/algorithms-computational-reasoning/|Procedure, data structures, complexity, and responsible computation.',
            'Sustainable Development|/sustainable-development/|Ecological limits, institutions, justice, and long-term wellbeing.',
        ]));
    }

    private static function schedule_reconcile(): void {
        if (!wp_next_scheduled('sc_library_daily_reconcile')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sc_library_daily_reconcile');
        }
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('sc_library_daily_reconcile');
    }
}
