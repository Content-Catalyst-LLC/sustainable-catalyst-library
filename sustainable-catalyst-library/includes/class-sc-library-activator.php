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

    public static function repair_schema(): void {
        self::create_tables();
        self::install_defaults();
        self::schedule_reconcile();
    }

    private static function create_tables(): void {
        global $wpdb;

        $index_table = $wpdb->prefix . 'sc_library_index';
        $relationships_table = $wpdb->prefix . 'sc_library_relationships';
        $workspaces_table = $wpdb->prefix . 'sc_library_workspaces';
        $workspace_revisions_table = $wpdb->prefix . 'sc_library_workspace_revisions';
        $workspace_collaborators_table = $wpdb->prefix . 'sc_library_workspace_collaborators';
        $workspace_sync_log_table = $wpdb->prefix . 'sc_library_workspace_sync_log';
        $document_jobs_table = $wpdb->prefix . 'sc_library_document_jobs';
        $document_editions_table = $wpdb->prefix . 'sc_library_document_editions';
        $scan_items_table = $wpdb->prefix . 'sc_library_scan_items';
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

        $workspaces_sql = "CREATE TABLE {$workspaces_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'private',
            schema_version VARCHAR(64) NOT NULL,
            workspace_json LONGTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            last_synced_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
            render_etag VARCHAR(191) NOT NULL DEFAULT '',
            sync_status VARCHAR(24) NOT NULL DEFAULT 'local',
            sync_error TEXT NULL,
            last_synced_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_uuid (workspace_uuid),
            KEY owner_user_id (owner_user_id),
            KEY visibility (visibility),
            KEY sync_status (sync_status),
            KEY updated_at (updated_at)
        ) {$charset};";

        $workspace_revisions_sql = "CREATE TABLE {$workspace_revisions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            revision BIGINT UNSIGNED NOT NULL,
            content_hash CHAR(64) NOT NULL,
            workspace_json LONGTEXT NOT NULL,
            change_type VARCHAR(32) NOT NULL DEFAULT 'updated',
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_revision (workspace_id, revision),
            KEY workspace_id (workspace_id),
            KEY created_at (created_at)
        ) {$charset};";

        $workspace_collaborators_sql = "CREATE TABLE {$workspace_collaborators_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'viewer',
            invited_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY workspace_user (workspace_id, user_id),
            KEY user_id (user_id),
            KEY role (role)
        ) {$charset};";

        $workspace_sync_log_sql = "CREATE TABLE {$workspace_sync_log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid CHAR(36) NOT NULL,
            direction VARCHAR(16) NOT NULL,
            status VARCHAR(24) NOT NULL,
            response_code INT NOT NULL DEFAULT 0,
            message TEXT NULL,
            content_hash CHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY workspace_uuid (workspace_uuid),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $document_jobs_sql = "CREATE TABLE {$document_jobs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid VARCHAR(64) NOT NULL DEFAULT '',
            book_id VARCHAR(191) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL,
            document_type VARCHAR(24) NOT NULL DEFAULT 'pdf',
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress INT NOT NULL DEFAULT 0,
            attempt INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            request_json LONGTEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            remote_job_uuid CHAR(36) NOT NULL,
            renderer_version VARCHAR(64) NOT NULL DEFAULT '',
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            output_sha256 CHAR(64) NOT NULL DEFAULT '',
            output_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            manifest_json LONGTEXT NULL,
            diagnostics_json LONGTEXT NULL,
            error_message TEXT NULL,
            poll_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_uuid (job_uuid),
            KEY owner_user_id (owner_user_id),
            KEY workspace_uuid (workspace_uuid),
            KEY book_id (book_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $document_editions_sql = "CREATE TABLE {$document_editions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            edition_uuid CHAR(36) NOT NULL,
            job_uuid CHAR(36) NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            workspace_uuid VARCHAR(64) NOT NULL DEFAULT '',
            book_id VARCHAR(191) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL,
            edition_label VARCHAR(191) NOT NULL DEFAULT '',
            content_hash CHAR(64) NOT NULL,
            output_sha256 CHAR(64) NOT NULL,
            output_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            manifest_json LONGTEXT NOT NULL,
            frozen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY edition_uuid (edition_uuid),
            KEY job_uuid (job_uuid),
            KEY owner_user_id (owner_user_id),
            KEY book_id (book_id),
            KEY content_hash (content_hash),
            KEY frozen_at (frozen_at)
        ) {$charset};";


        $scan_items_sql = "CREATE TABLE {$scan_items_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_id CHAR(36) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(64) NOT NULL DEFAULT '',
            outcome VARCHAR(24) NOT NULL,
            reason VARCHAR(191) NOT NULL DEFAULT '',
            title TEXT NULL,
            processed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY scan_post (scan_id, post_id),
            KEY scan_id (scan_id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY outcome (outcome),
            KEY processed_at (processed_at)
        ) {$charset};";

        dbDelta($index_sql);
        dbDelta($relationships_sql);
        dbDelta($workspaces_sql);
        dbDelta($workspace_revisions_sql);
        dbDelta($workspace_collaborators_sql);
        dbDelta($workspace_sync_log_sql);
        dbDelta($document_jobs_sql);
        dbDelta($document_editions_sql);
        dbDelta($scan_items_sql);
    }

    private static function install_defaults(): void {
        add_option('sc_library_post_types', ['post']);
        add_option('sc_library_items_per_page', 10);
        add_option('sc_library_enable_tags', 1);
        add_option('sc_library_last_full_index', '');
        add_option('sc_library_scan_state', [], '', false);
        add_option('sc_library_scan_logs', [], '', false);
        add_option('sc_library_last_scan_summary', [], '', false);
        add_option('sc_library_default_mode', 'compact');
        add_option('sc_library_initial_results', 0);
        add_option('sc_library_result_density', 'compact');
        add_option('sc_library_excerpt_words', 28);
        add_option('sc_library_show_pathways', 1);
        add_option('sc_library_search_placeholder', 'Search concepts, series, methods, and publications');
        add_option('sc_library_workbench_url', home_url('/workbench/'));
        add_option('sc_library_enable_notebook', 1);
        add_option('sc_library_notebook_storage_mode', 'local');
        add_option('sc_library_enable_persistent_workspaces', 1);
        add_option('sc_library_workspace_storage_mode', 'hybrid');
        add_option('sc_library_workspace_auto_sync', 0);
        add_option('sc_library_workspace_max_mb', 8);
        add_option('sc_library_render_sync_url', '');
        add_option('sc_library_render_sync_api_key', '');
        add_option('sc_library_render_sync_timeout', 8);
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
        add_option('sc_library_enable_server_documents', 1);
        add_option('sc_library_document_service_url', '');
        add_option('sc_library_document_service_api_key', '');
        add_option('sc_library_document_timeout', 30);
        add_option('sc_library_document_auto_import', 1);
        add_option('sc_library_document_max_request_mb', 8);
        add_option('sc_library_document_max_pdf_mb', 20);
        add_option('sc_library_document_max_attempts', 3);
        add_option('sc_library_document_retention_days', 30);
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
        wp_clear_scheduled_hook('sc_library_sync_workspace');
    }
}
